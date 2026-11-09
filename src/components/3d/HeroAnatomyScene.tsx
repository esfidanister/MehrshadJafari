"use client";

import { Suspense, useEffect, useRef, useState, type RefObject } from "react";
import { ContactShadows } from "@react-three/drei";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { MathUtils, NeutralToneMapping, Quaternion, Vector3, type Group } from "three";
import type { MotionValue } from "framer-motion";

import { cn } from "@/lib/utils";

import { applyJawFocus, focusForProgress, useJawAsset, type JawFocus } from "./JawAsset";
import { SceneFallback } from "./SceneFallback";
import {
  useCanRender3D,
  useFinePointer,
  useInView,
  usePrefersReducedMotion,
} from "./useSceneRuntime";

export type HeroAnatomySceneProps = {
  /**
   * 0 ? 1 progress of the hero scrolling out of view. Accepts a Framer Motion
   * value (read per-frame, no React re-renders) or a plain number.
   */
  scrollProgress?: MotionValue<number> | number;
  /** Hero idle orbit, or a four-beat camera path for the pinned story. */
  variant?: "hero" | "story";
  className?: string;
};

type PointerState = { x: number; y: number };

const FOV = 22;
const FOCUS_POINT = new Vector3(0, 1.25, -1.05);

function readProgress(value: HeroAnatomySceneProps["scrollProgress"]): number {
  if (value === undefined) return 0;
  const raw = typeof value === "number" ? value : value.get();

  return MathUtils.clamp(raw, 0, 1);
}

/** Wakes the render loop when visibility / motion preferences change. */
function FrameKicker({ token }: { token: string }) {
  const invalidate = useThree((state) => state.invalidate);

  useEffect(() => {
    invalidate();
  }, [token, invalidate]);

  return null;
}

type RigProps = {
  scrollProgress: HeroAnatomySceneProps["scrollProgress"];
  pointer: RefObject<PointerState>;
  reduceMotion: boolean;
  variant: "hero" | "story";
  onReady: () => void;
};

function HeroRig({ scrollProgress, pointer, reduceMotion, variant, onReady }: RigProps) {
  const group = useRef<Group>(null);
  const { scene, stops } = useJawAsset();
  const focus = useRef<JawFocus>("overview");
  const desired = useRef(new Vector3());
  const desiredQuat = useRef(new Quaternion());

  useEffect(() => {
    onReady();
  }, [onReady]);

  useFrame((state, delta) => {
    const target = group.current;
    if (!target || stops.length < 2) return;

    const { camera, clock } = state;
    const t = reduceMotion ? 0 : clock.elapsedTime;
    const progress = readProgress(scrollProgress);
    const sway = pointer.current.x;

    const nextFocus = variant === "story" ? focusForProgress(progress) : "overview";
    if (focus.current !== nextFocus) {
      focus.current = nextFocus;
      applyJawFocus(scene, nextFocus);
    }

    const scaled = (variant === "story" ? progress : 0) * (stops.length - 1);
    const index = Math.min(stops.length - 2, Math.floor(scaled));
    const mix = scaled - index;
    const from = stops[index];
    const to = stops[index + 1];

    desired.current.lerpVectors(from.position, to.position, mix);
    desiredQuat.current.copy(from.quaternion).slerp(to.quaternion, mix);
    // Authored cameras are telephoto. Ease back so the skull fits the column.
    const pull = variant === "hero" ? 1.38 : 1.12;
    desired.current.sub(FOCUS_POINT).multiplyScalar(pull).add(FOCUS_POINT);

    if (variant === "hero") {
      const idle = Math.sin(t * 0.28) * 0.12 + sway * 0.08;
      target.rotation.y = MathUtils.damp(target.rotation.y, idle, 2.4, delta);
    } else {
      target.rotation.y = MathUtils.damp(target.rotation.y, 0, 4, delta);
    }

    const lambda = reduceMotion ? 60 : 3.4;
    camera.position.x = MathUtils.damp(camera.position.x, desired.current.x, lambda, delta);
    camera.position.y = MathUtils.damp(camera.position.y, desired.current.y, lambda, delta);
    camera.position.z = MathUtils.damp(camera.position.z, desired.current.z, lambda, delta);
    camera.quaternion.slerp(desiredQuat.current, 1 - Math.exp(-lambda * delta));
  });

  return (
    <group ref={group}>
      <primitive object={scene} />
    </group>
  );
}

export function HeroAnatomyScene({
  scrollProgress,
  variant = "hero",
  className,
}: HeroAnatomySceneProps) {
  const reduceMotion = usePrefersReducedMotion();
  const finePointer = useFinePointer();
  const canRender3D = useCanRender3D();
  // Hero is above the fold — start as visible so the first frames paint
  // before IntersectionObserver settles (avoids a blank white canvas).
  const { ref, inView } = useInView<HTMLDivElement>({ rootMargin: "120px" });
  const [ready, setReady] = useState(false);
  const [painted, setPainted] = useState(false);
  const pointer = useRef<PointerState>({ x: 0, y: 0 });
  const visible = inView || !painted;

  const parallax = finePointer && !reduceMotion;

  useEffect(() => {
    if (!parallax) {
      pointer.current = { x: 0, y: 0 };
      return;
    }

    const onMove = (event: PointerEvent) => {
      pointer.current = {
        x: (event.clientX / window.innerWidth) * 2 - 1,
        y: (event.clientY / window.innerHeight) * 2 - 1,
      };
    };

    window.addEventListener("pointermove", onMove, { passive: true });

    return () => window.removeEventListener("pointermove", onMove);
  }, [parallax]);

  const frameloop = !visible ? "never" : reduceMotion ? "demand" : "always";
  const showCanvas = painted && canRender3D;

  return (
    <div
      ref={ref}
      className={cn("relative h-full w-full", className)}
      aria-hidden
    >
      {/* Soft clinical well so ivory bone reads against the page */}
      <div
        className="pointer-events-none absolute inset-[6%] -z-10 rounded-full bg-[radial-gradient(closest-side,rgb(57_127_145/0.1),rgb(247_248_246/0)_72%)]"
      />

      {/* Poster frame - remains until the first WebGL frame is painted. */}
      <div
        className={cn(
          "absolute inset-0 transition-opacity duration-700 ease-[var(--ease-clinical)]",
          showCanvas ? "pointer-events-none opacity-0" : "opacity-100",
        )}
      >
        <SceneFallback loading={canRender3D && !painted} />
      </div>

      {canRender3D ? (
        <Canvas
          className={cn(
            "transition-opacity duration-700 ease-[var(--ease-clinical)]",
            showCanvas ? "opacity-100" : "opacity-0",
          )}
          frameloop={frameloop}
          dpr={[1, 1.5]}
          shadows="percentage"
          camera={{ fov: FOV, near: 0.1, far: 100, position: [7, 4.2, 9] }}
          gl={{
            antialias: true,
            alpha: true,
            powerPreference: "high-performance",
            preserveDrawingBuffer: true,
          }}
          onCreated={({ gl }) => {
            gl.toneMapping = NeutralToneMapping;
            gl.toneMappingExposure = 1.12;
            setReady(true);
          }}
          style={{ position: "absolute", inset: 0, touchAction: "auto" }}
        >
          <FrameKicker token={`${frameloop}-${ready}`} />
          <hemisphereLight args={["#f7fbff", "#c6aa86", 0.9]} />
          <directionalLight position={[5, 8, 6]} intensity={2.6} color="#fff6ea" />
          <directionalLight position={[-6, 4, -2]} intensity={1.8} color="#7ed7e2" />
          <directionalLight position={[0, 6, -6]} intensity={0.55} color="#ffffff" />
          <ContactShadows
            position={[0, -0.75, -1.2]}
            opacity={0.38}
            scale={16}
            blur={2.6}
            far={5}
            resolution={512}
            color="#1c2e34"
          />
          <Suspense fallback={null}>
            <HeroRig
              scrollProgress={scrollProgress}
              pointer={pointer}
              reduceMotion={reduceMotion}
              variant={variant}
              onReady={() => {
                requestAnimationFrame(() => setPainted(true));
              }}
            />
          </Suspense>
        </Canvas>
      ) : null}
    </div>
  );
}

export default HeroAnatomyScene;
