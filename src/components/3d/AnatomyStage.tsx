"use client";

import { Suspense, useEffect, useRef, useState } from "react";
import { Canvas, useFrame } from "@react-three/fiber";
import { ContactShadows } from "@react-three/drei";
import { MathUtils, NeutralToneMapping, PerspectiveCamera, Vector3 } from "three";

import { Container } from "@/components/ui/Container";
import type { Locale } from "@/lib/locale";
import { cn } from "@/lib/utils";

import {
  applyJawFocus,
  useJawAsset,
  type JawFocus,
} from "./JawAsset";
import { SceneFallback } from "./SceneFallback";
import { useCanRender3D, usePrefersReducedMotion } from "./useSceneRuntime";

/**
 * Chin is toward −Z. The file cameras sit behind the condyles, so these
 * poses look at the face of the jaw from outside the mesh.
 */
const POSES: {
  focus: JawFocus;
  fa: string;
  en: string;
  position: [number, number, number];
  target: [number, number, number];
  fov: number;
}[] = [
  {
    focus: "overview",
    fa: "نمای کلی",
    en: "Overview",
    position: [7.2, 3.4, -15.2],
    target: [0, 1.35, -1.05],
    fov: 32,
  },
  {
    focus: "overview",
    fa: "مفصل فک",
    en: "TMJ",
    position: [-6.2, 5.6, -4.8],
    target: [-2.3, 3.2, -0.3],
    fov: 24,
  },
  {
    focus: "nerve",
    fa: "دندان عقل",
    en: "Wisdom tooth",
    position: [8.4, 2.2, -4.6],
    target: [1.7, 0.7, -0.7],
    fov: 26,
  },
  {
    focus: "implant",
    fa: "ایمپلنت",
    en: "Implant",
    position: [3.6, 1.4, -7.4],
    target: [1, 0.55, -1.35],
    fov: 24,
  },
  {
    focus: "surgery",
    fa: "صفحات فک",
    en: "Plates",
    position: [2.4, 0.9, -8.4],
    target: [0.15, 0.35, -1.8],
    fov: 26,
  },
];

const desired = new Vector3();
const look = new Vector3();
const aim = new PerspectiveCamera(32, 1, 0.1, 100);

function ModelRig({ index, onReady }: { index: number; onReady: () => void }) {
  const { scene } = useJawAsset();
  const snapped = useRef(false);

  useEffect(() => {
    applyJawFocus(scene, POSES[index]?.focus ?? "overview");
  }, [index, scene]);

  useFrame(({ camera }, delta) => {
    const pose = POSES[index] ?? POSES[0];
    desired.set(...pose.position);
    look.set(...pose.target);
    aim.position.copy(desired);
    aim.up.set(0, 1, 0);
    aim.lookAt(look);

    const perspective = camera as typeof camera & { fov: number };
    if (!snapped.current) {
      camera.position.copy(desired);
      camera.quaternion.copy(aim.quaternion);
      perspective.fov = pose.fov;
      camera.updateProjectionMatrix();
      snapped.current = true;
      onReady();
      return;
    }

    const lambda = 3.2;
    camera.position.x = MathUtils.damp(camera.position.x, desired.x, lambda, delta);
    camera.position.y = MathUtils.damp(camera.position.y, desired.y, lambda, delta);
    camera.position.z = MathUtils.damp(camera.position.z, desired.z, lambda, delta);
    camera.quaternion.slerp(aim.quaternion, 1 - Math.exp(-lambda * delta));
    if (Math.abs(perspective.fov - pose.fov) > 0.05) {
      perspective.fov = MathUtils.damp(perspective.fov, pose.fov, lambda, delta);
      camera.updateProjectionMatrix();
    }
  });

  return <primitive object={scene} />;
}

export function AnatomyStage({ locale }: { locale: Locale }) {
  const fa = locale === "fa";
  const [index, setIndex] = useState(0);
  const [painted, setPainted] = useState(false);
  const reduce = usePrefersReducedMotion();
  const can3d = useCanRender3D();

  return (
    <section className="bg-bg pb-8 pt-6 md:pb-12" aria-label={fa ? "مدل فک" : "Jaw model"}>
      <Container>
        <div className="overflow-hidden rounded-[1.75rem] border border-border bg-[#f4f1ea]">
          <div className="relative h-[26rem] sm:h-[34rem] lg:h-[40rem]">
            {!painted ? (
              <SceneFallback className="absolute inset-0" loading={can3d} />
            ) : null}
            {can3d ? (
              <Canvas
                className={cn(
                  "absolute inset-0 transition-opacity duration-700",
                  painted ? "opacity-100" : "opacity-0",
                )}
                dpr={[1, 1.5]}
                camera={{ fov: 32, near: 0.1, far: 100, position: [7.2, 3.4, -15.2] }}
                gl={{ antialias: true, alpha: true, powerPreference: "high-performance" }}
                onCreated={({ gl }) => {
                  gl.toneMapping = NeutralToneMapping;
                  gl.toneMappingExposure = 1.08;
                }}
              >
                <color attach="background" args={["#f4f1ea"]} />
                <hemisphereLight args={["#fffaf3", "#c6aa86", 0.85]} />
                <directionalLight position={[4, 8, -12]} intensity={2.6} color="#fff6ea" />
                <directionalLight position={[-6, 3, -6]} intensity={0.85} color="#d5ebea" />
                <ContactShadows
                  position={[0, -1.65, -1]}
                  opacity={0.28}
                  scale={14}
                  blur={2.4}
                  far={4}
                  color="#1c2e34"
                />
                <Suspense fallback={null}>
                  <ModelRig index={reduce ? 0 : index} onReady={() => setPainted(true)} />
                </Suspense>
              </Canvas>
            ) : null}
          </div>

          <div className="flex flex-wrap gap-2 border-t border-border bg-white/80 px-4 py-3">
            {POSES.map((view, viewIndex) => (
              <button
                key={view.en}
                type="button"
                onClick={() => setIndex(viewIndex)}
                className={cn(
                  "rounded-full border px-3 py-1.5 text-sm transition-colors",
                  index === viewIndex
                    ? "border-medical bg-medical text-white"
                    : "border-border text-muted hover:border-medical/40 hover:text-text",
                )}
                aria-pressed={index === viewIndex}
              >
                {fa ? view.fa : view.en}
              </button>
            ))}
          </div>
        </div>
        <p className="mt-3 text-xs leading-relaxed text-muted">
          {fa
            ? "مدل آموزشی فک پایین. اسکن بیمار نیست و برای تشخیص یا طرح جراحی استفاده نمی‌شود."
            : "Educational mandible. Not a patient scan, and not for diagnosis or surgical planning."}
        </p>
      </Container>
    </section>
  );
}

