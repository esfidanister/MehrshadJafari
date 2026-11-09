"use client";

import { forwardRef, useEffect, useMemo } from "react";
import {
  Color,
  DoubleSide,
  MeshBasicMaterial,
  MeshPhysicalMaterial,
  type Group,
} from "three";
import { useFrame } from "@react-three/fiber";

import { MANDIBLE_CENTER, getMandibleParts } from "./mandibleGeometry";

/** Slightly warmer / deeper ivory so form reads on bright clinical backgrounds. */
export const IVORY = "#E6DCC8";

export type MandibleModelProps = {
  /** Render the inferior alveolar nerve as a soft x-ray overlay. */
  showNerve?: boolean;
  /** Animate the nerve glow. */
  pulse?: boolean;
};

/**
 * The ivory mandible, centred on its own visual midpoint so that parents can
 * rotate it around the origin without wobble.
 */
export const MandibleModel = forwardRef<Group, MandibleModelProps>(
  function MandibleModel({ showNerve = false, pulse = false }, ref) {
    const parts = useMemo(() => getMandibleParts(), []);

    const materials = useMemo(() => {
      const bone = new MeshPhysicalMaterial({
        color: new Color(IVORY),
        vertexColors: true,
        roughness: 0.62,
        metalness: 0,
        clearcoat: 0.14,
        clearcoatRoughness: 0.62,
        sheen: 0.35,
        sheenRoughness: 0.6,
        sheenColor: new Color("#f3e6cf"),
        envMapIntensity: 0.7,
      });

      const enamel = new MeshPhysicalMaterial({
        color: new Color("#f7f4ef"),
        vertexColors: true,
        roughness: 0.34,
        metalness: 0,
        clearcoat: 0.55,
        clearcoatRoughness: 0.28,
        envMapIntensity: 0.85,
      });

      const nerve = new MeshBasicMaterial({
        color: new Color("#3f9db4"),
        transparent: true,
        opacity: 0.8,
        depthTest: false,
        depthWrite: false,
        toneMapped: false,
        side: DoubleSide,
      });

      return { bone, enamel, nerve };
    }, []);

    useEffect(
      () => () => {
        materials.bone.dispose();
        materials.enamel.dispose();
        materials.nerve.dispose();
      },
      [materials],
    );

    useFrame(({ clock }) => {
      if (!showNerve || !pulse) return;
      materials.nerve.opacity = 0.62 + Math.sin(clock.elapsedTime * 1.6) * 0.18;
    });

    return (
      <group ref={ref}>
        <group position={[-MANDIBLE_CENTER.x, -MANDIBLE_CENTER.y, -MANDIBLE_CENTER.z]}>
          <mesh
            geometry={parts.bone}
            material={materials.bone}
            castShadow
            receiveShadow
            dispose={null}
          />
          <mesh
            geometry={parts.teeth}
            material={materials.enamel}
            castShadow
            receiveShadow
            dispose={null}
          />
          {showNerve ? (
            <mesh
              geometry={parts.nerve}
              material={materials.nerve}
              renderOrder={10}
              dispose={null}
            />
          ) : null}
        </group>
      </group>
    );
  },
);
