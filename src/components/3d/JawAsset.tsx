"use client";

import { useLayoutEffect, useMemo } from "react";
import { useGLTF } from "@react-three/drei";
import { Quaternion, Vector3, type Object3D } from "three";

export const JAW_URL = "/models/jaw.glb";

export const SCROLL_CAMERAS = [
  "Cam_01_Hero",
  "Cam_02_TMJ",
  "Cam_03_Wisdom",
  "Cam_04_Implant",
  "Cam_05_Plates",
] as const;

export type JawFocus = "overview" | "nerve" | "implant" | "surgery";

export type CameraStop = {
  position: Vector3;
  quaternion: Quaternion;
  fov: number;
};

const HIDDEN = new Set(["Key_Light", "Teal_Rim", "Fill_Light"]);

export function focusForProgress(progress: number): JawFocus {
  if (progress < 0.42) return "overview";
  if (progress < 0.62) return "nerve";
  if (progress < 0.82) return "implant";
  return "surgery";
}

/** Shows implant, plates, and the cutaway only on the chapters that explain them. */
export function applyJawFocus(root: Object3D, focus: JawFocus) {
  root.traverse((obj) => {
    const name = obj.name;
    if (/^Implant_/.test(name)) obj.visible = focus === "implant";
    else if (/^(Orthognathic_|Plates_)/.test(name)) obj.visible = focus === "surgery";
    else if (/Cutaway|Trabecular/.test(name)) obj.visible = focus === "nerve";
  });
}

export function useJawAsset() {
  const gltf = useGLTF(JAW_URL);
  // Each canvas needs its own graph. Geometries stay shared.
  const scene = useMemo(() => gltf.scene.clone(true), [gltf.scene]);

  const stops = useMemo<CameraStop[]>(() => {
    scene.updateMatrixWorld(true);
    return SCROLL_CAMERAS.map((name) => {
      const node = scene.getObjectByName(name);
      const fov =
        node && "fov" in node && typeof node.fov === "number" ? node.fov : 28;
      return {
        position: node ? node.getWorldPosition(new Vector3()) : new Vector3(8.8, 6.5, 11),
        quaternion: node
          ? node.getWorldQuaternion(new Quaternion())
          : new Quaternion(),
        fov,
      };
    });
  }, [scene]);

  useLayoutEffect(() => {
    scene.traverse((obj) => {
      if (
        obj.name.startsWith("Scroll_") ||
        obj.name.startsWith("Cam_") ||
        obj.name.startsWith("Light_") ||
        HIDDEN.has(obj.name)
      ) {
        obj.visible = false;
      }
      const mesh = obj as Object3D & { castShadow?: boolean; receiveShadow?: boolean };
      mesh.castShadow = true;
      mesh.receiveShadow = true;
    });
    applyJawFocus(scene, "overview");
  }, [scene]);

  return { scene, stops };
}

useGLTF.preload(JAW_URL);
