"use client";

import { Component, type ErrorInfo, type ReactNode } from "react";
import dynamic from "next/dynamic";

import { cn } from "@/lib/utils";

import type { AnatomyStorySceneProps } from "./AnatomyStoryScene";
import type { HeroAnatomySceneProps } from "./HeroAnatomyScene";
import { SceneFallback } from "./SceneFallback";
import { SceneLoader } from "./SceneLoader";

/** Poster frame + soft loader while the three.js chunk streams in. */
function SceneLoading() {
  return (
    <div className="relative h-full w-full">
      <SceneFallback loading />
      <SceneLoader silent />
    </div>
  );
}

const HeroAnatomyScene = dynamic(
  () => import("./HeroAnatomyScene").then((mod) => mod.HeroAnatomyScene),
  { ssr: false, loading: SceneLoading },
);

const AnatomyStoryScene = dynamic(
  () => import("./AnatomyStoryScene").then((mod) => mod.AnatomyStoryScene),
  { ssr: false, loading: SceneLoading },
);

type BoundaryProps = { children: ReactNode; fallback: ReactNode };
type BoundaryState = { failed: boolean };

/** Any WebGL / chunk failure degrades to the SVG silhouette. */
class SceneBoundary extends Component<BoundaryProps, BoundaryState> {
  state: BoundaryState = { failed: false };

  static getDerivedStateFromError(): BoundaryState {
    return { failed: true };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    if (process.env.NODE_ENV !== "production") {
      console.warn("[3D scene] falling back to static poster", error, info);
    }
  }

  render() {
    return this.state.failed ? this.props.fallback : this.props.children;
  }
}

export type DynamicHeroSceneProps = HeroAnatomySceneProps;

/** Client-only (ssr: false) hero scene with an elegant fallback. */
export function DynamicHeroScene({ className, ...props }: DynamicHeroSceneProps) {
  return (
    <div className={cn("h-full w-full", className)}>
      <SceneBoundary fallback={<SceneFallback />}>
        <HeroAnatomyScene {...props} />
      </SceneBoundary>
    </div>
  );
}

export type DynamicAnatomySceneProps = AnatomyStorySceneProps;

export function DynamicAnatomyScene({
  className,
  ...props
}: DynamicAnatomySceneProps) {
  return (
    <div className={cn("h-full w-full", className)}>
      <SceneBoundary fallback={<SceneFallback />}>
        <AnatomyStoryScene {...props} />
      </SceneBoundary>
    </div>
  );
}

export default DynamicHeroScene;
