"use client";

import {
  Suspense,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ElementRef,
  type RefObject,
} from "react";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { Html, OrbitControls } from "@react-three/drei";
import { MathUtils, NeutralToneMapping, Vector3, type Group } from "three";


import { cn } from "@/lib/utils";

import { applyJawFocus, useJawAsset } from "./JawAsset";
import {
  MANDIBLE_CENTER,
  type MandibleAnchor,
} from "./mandibleGeometry";
import { SceneFallback } from "./SceneFallback";
import {
  useCanRender3D,
  useInView,
  usePrefersReducedMotion,
} from "./useSceneRuntime";

export type AnatomyStoryLabels = {
  mandible: string;
  wisdomTooth: string;
  nerve: string;
};

export type AnatomyStorySceneProps = {
  labels: AnatomyStoryLabels;
  /** Show the inferior alveolar nerve overlay. Default true. */
  showNerve?: boolean;
  className?: string;
};

type OrbitControlsImpl = ElementRef<typeof OrbitControls>;

const FOV = 32;
const HALF_WIDTH = 2.0;
const TARGET = new Vector3(0, -0.04, 0);

type HotspotTone = "ivory" | "medical" | "accent";

const toneClass: Record<HotspotTone, string> = {
  ivory: "bg-text",
  medical: "bg-medical",
  accent: "bg-accent",
};

type HotspotProps = {
  anchor: MandibleAnchor;
  label: string;
  tone: HotspotTone;
};

/** Camera-facing DOM label pinned to a point on the jaw. */
function Hotspot({ anchor, label, tone }: HotspotProps) {
  const group = useRef<Group>(null);
  const element = useRef<HTMLDivElement>(null);
  const scratch = useMemo(() => ({ world: new Vector3(), toCamera: new Vector3() }), []);

  useFrame(({ camera }) => {
    const target = group.current;
    const node = element.current;
    if (!target || !node) return;

    target.getWorldPosition(scratch.world);
    scratch.toCamera.copy(camera.position).sub(scratch.world).normalize();

    const facing = anchor.normal.dot(scratch.toCamera) > 0.12;
    const next = facing ? "true" : "false";

    if (node.dataset.visible !== next) node.dataset.visible = next;
  });

  return (
    <group
      ref={group}
      position={[
        anchor.position.x - MANDIBLE_CENTER.x,
        anchor.position.y - MANDIBLE_CENTER.y,
        anchor.position.z - MANDIBLE_CENTER.z,
      ]}
    >
      <Html center zIndexRange={[20, 0]} style={{ pointerEvents: "none" }}>
        <div
          ref={element}
          data-visible="true"
          className="flex items-center gap-2.5 whitespace-nowrap opacity-100 transition-opacity duration-500 data-[visible=false]:opacity-0"
        >
          <span className="relative flex size-3 items-center justify-center">
            <span
              className={cn(
                "absolute inline-flex size-full animate-ping rounded-full opacity-40",
                toneClass[tone],
              )}
            />
            <span
              className={cn(
                "relative size-2 rounded-full ring-2 ring-white",
                toneClass[tone],
              )}
            />
          </span>
          <span className="rounded-full border border-border bg-white/90 px-3 py-1 text-[0.6875rem] font-medium tracking-wide text-text shadow-[0_6px_20px_-10px_rgb(24_40_50/0.5)] backdrop-blur-sm">
            {label}
          </span>
        </div>
      </Html>
    </group>
  );
}

/** Keeps the orbit radius in step with the container's aspect ratio. */
function OrbitFit({ controls }: { controls: RefObject<OrbitControlsImpl | null> }) {
  const lastAspect = useRef(0);

  useFrame(({ camera, size }) => {
    const aspect = size.width / Math.max(size.height, 1);
    if (Math.abs(aspect - lastAspect.current) < 0.001) return;
    lastAspect.current = aspect;

    const tanHalfFov = Math.tan(MathUtils.degToRad(FOV) / 2);
    const distance = Math.max(5.6, HALF_WIDTH / (tanHalfFov * aspect));

    camera.position.sub(TARGET).setLength(distance).add(TARGET);
    controls.current?.update();
  });

  return null;
}

/** Let vertical swipes scroll the page on touch devices; drag rotates. */
function TouchActionFix() {
  const applied = useRef(false);

  /** OrbitControls resets `touch-action` on connect ? override afterwards. */
  useFrame(({ gl }) => {
    if (applied.current) return;
    applied.current = true;
    gl.domElement.style.touchAction = "pan-y";
  });

  return null;
}

function ExplorerModel({ showNerve }: { showNerve: boolean }) {
  const { scene } = useJawAsset();

  useEffect(() => {
    applyJawFocus(scene, showNerve ? "nerve" : "overview");
  }, [scene, showNerve]);

  return <primitive object={scene} />;
}

function WakeOnChange({ token }: { token: string }) {
  const invalidate = useThree((state) => state.invalidate);

  useEffect(() => {
    invalidate();
  }, [token, invalidate]);

  return null;
}

export function AnatomyStoryScene({
  labels,
  showNerve = true,
  className,
}: AnatomyStorySceneProps) {
  const reduceMotion = usePrefersReducedMotion();
  const canRender3D = useCanRender3D();
  const { ref, inView } = useInView<HTMLDivElement>({ rootMargin: "120px" });
  const [ready, setReady] = useState(false);
  const controls = useRef<OrbitControlsImpl | null>(null);

  const frameloop = !inView ? "never" : reduceMotion ? "demand" : "always";

  return (
    <div ref={ref} className={cn("relative h-full w-full", className)}>
      <div
        className={cn(
          "absolute inset-0 transition-opacity duration-1000 ease-[var(--ease-clinical)]",
          ready && canRender3D ? "opacity-0" : "opacity-100",
        )}
      >
        <SceneFallback loading={!ready && canRender3D} />
      </div>

      {canRender3D ? (
        <Canvas
          className={cn(
            "transition-opacity duration-1000 ease-[var(--ease-clinical)]",
            ready ? "opacity-100" : "opacity-0",
          )}
          frameloop={frameloop}
          dpr={[1, 1.5]}
          shadows="percentage"
          camera={{
            fov: 22,
            near: 0.1,
            far: 100,
            position: [7, 4.2, 9],
          }}
          gl={{
            antialias: true,
            alpha: true,
            powerPreference: "high-performance",
          }}
          onCreated={({ gl }) => {
            gl.toneMapping = NeutralToneMapping;
            gl.toneMappingExposure = 1;
            setReady(true);
          }}
          style={{ position: "absolute", inset: 0 }}
        >
          <WakeOnChange token={frameloop} />
          <hemisphereLight args={["#f7fbff", "#c6aa86", 0.9]} />
          <directionalLight position={[5, 8, 6]} intensity={2.6} color="#fff6ea" />
          <directionalLight position={[-6, 4, -2]} intensity={1.8} color="#7ed7e2" />

          <Suspense fallback={null}>
            <ExplorerModel showNerve={showNerve} />
          </Suspense>

          <OrbitControls
            ref={controls}
            target={[0, 1.35, -1.1]}
            enableZoom={false}
            enablePan={false}
            enableDamping
            dampingFactor={0.08}
            rotateSpeed={0.7}
            minPolarAngle={0.45}
            maxPolarAngle={1.55}
            autoRotate={!reduceMotion}
            autoRotateSpeed={0.45}
          />
          <TouchActionFix />
        </Canvas>
      ) : null}
    </div>
  );
}

export default AnatomyStoryScene;
