"use client";

import { ContactShadows, Environment, Lightformer } from "@react-three/drei";

import { MANDIBLE_BOTTOM_Y, MANDIBLE_CENTER } from "./mandibleGeometry";

type StudioLightingProps = {
  /** Re-render the contact shadow every frame (false → bake once). */
  animateShadow?: boolean;
  /** How far the contact shadow sits below the model's lowest point. */
  shadowGap?: number;
};

/**
 * Soft, clinical studio: a self-contained lightformer environment (no network
 * HDR fetch — safe for static hosting), a warm key with soft shadow-mapping
 * for cavity darkening, cool fill + turquoise rim, and drei ContactShadows.
 */
export function StudioLighting({
  animateShadow = true,
  shadowGap = 0.16,
}: StudioLightingProps) {
  const floorY = MANDIBLE_BOTTOM_Y - MANDIBLE_CENTER.y - shadowGap;

  return (
    <>
      <hemisphereLight args={["#f7fafb", "#b7aaa0", 0.7]} />

      <directionalLight
        position={[3.4, 5.2, 4.2]}
        intensity={2.4}
        color="#fff6ea"
        castShadow
        shadow-mapSize={[1024, 1024]}
        shadow-camera-near={0.5}
        shadow-camera-far={16}
        shadow-camera-left={-3.2}
        shadow-camera-right={3.2}
        shadow-camera-top={3.2}
        shadow-camera-bottom={-3.2}
        shadow-bias={-0.0004}
        shadow-normalBias={0.02}
        shadow-radius={5}
      />
      <directionalLight position={[-4.5, 2.2, 2.4]} intensity={0.95} color="#d7e8eb" />
      <directionalLight position={[0.5, 3, -5.5]} intensity={1.85} color="#7eb8bc" />

      <Environment resolution={256} environmentIntensity={0.75}>
        <Lightformer
          form="rect"
          intensity={2.4}
          color="#ffffff"
          position={[0, 5.5, 3]}
          scale={[9, 4, 1]}
          target={[0, 0, 0]}
        />
        <Lightformer
          form="rect"
          intensity={1.2}
          color="#e9f5f6"
          position={[-5.5, 1.5, 2]}
          scale={[4, 6, 1]}
          target={[0, 0, 0]}
        />
        <Lightformer
          form="ring"
          intensity={1.1}
          color="#9ccdd0"
          position={[0, 2.5, -6.5]}
          scale={5}
          target={[0, 0, 0]}
        />
        <Lightformer
          form="rect"
          intensity={0.8}
          color="#f6e7d0"
          position={[5.5, -0.5, 3]}
          scale={[3, 4, 1]}
          target={[0, 0, 0]}
        />
      </Environment>

      <ContactShadows
        position={[0, floorY, 0]}
        opacity={0.55}
        scale={7.5}
        blur={2.4}
        far={2.1}
        resolution={512}
        color="#1c2e34"
        frames={animateShadow ? Infinity : 1}
      />
    </>
  );
}
