"use client";

import Lenis from "lenis";
import { useEffect, type ReactNode } from "react";

import "lenis/dist/lenis.css";

import { useAppearance } from "@/components/providers/Appearance";

export type SmoothScrollProps = {
  children: ReactNode;
};

const WHEEL_DURATION = {
  quiet: 0.7,
  gentle: 1.1,
} as const;

/**
 * Wheel smoothing follows the desk motion setting.
 * Touch stays native. "off" and reduced-motion leave the browser scroll alone.
 */
export function SmoothScroll({ children }: SmoothScrollProps) {
  const look = useAppearance();

  useEffect(() => {
    const root = document.documentElement;
    const media = window.matchMedia("(prefers-reduced-motion: reduce)");
    let lenis: Lenis | null = null;

    const stop = () => {
      lenis?.destroy();
      lenis = null;
      root.classList.remove("lenis", "lenis-smooth");
    };

    const start = () => {
      stop();
      const fromDesk = root.dataset.motion;
      const motion = fromDesk === "quiet" || fromDesk === "gentle" || fromDesk === "off" ? fromDesk : look.motion;
      if (motion === "off" || media.matches) return;

      lenis = new Lenis({
        duration: WHEEL_DURATION[motion],
        easing: (t) => Math.min(1, 1.001 - 2 ** (-10 * t)),
        orientation: "vertical",
        gestureOrientation: "vertical",
        smoothWheel: true,
        syncTouch: false,
        wheelMultiplier: motion === "gentle" ? 0.9 : 1,
        touchMultiplier: 1,
        autoRaf: true,
      });
    };

    start();
    const observer = new MutationObserver(start);
    observer.observe(root, { attributes: true, attributeFilter: ["data-motion"] });
    media.addEventListener("change", start);

    return () => {
      media.removeEventListener("change", start);
      observer.disconnect();
      stop();
    };
  }, [look.motion]);

  return children;
}
