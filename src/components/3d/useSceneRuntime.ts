"use client";

import {
  useEffect,
  useRef,
  useState,
  useSyncExternalStore,
  type RefObject,
} from "react";

function useMediaQuery(query: string): boolean {
  return useSyncExternalStore(
    (onChange) => {
      const media = window.matchMedia(query);
      media.addEventListener("change", onChange);
      return () => media.removeEventListener("change", onChange);
    },
    () => window.matchMedia(query).matches,
    () => false,
  );
}

export function usePrefersReducedMotion(): boolean {
  return useMediaQuery("(prefers-reduced-motion: reduce)");
}

/** True on desktop-class pointers — gates the pointer parallax. */
export function useFinePointer(): boolean {
  return useMediaQuery("(hover: hover) and (pointer: fine)");
}

let webglSupport: boolean | null = null;

function detectWebGL(): boolean {
  if (webglSupport !== null) return webglSupport;

  try {
    const canvas = document.createElement("canvas");
    const context =
      canvas.getContext("webgl2") ??
      canvas.getContext("webgl") ??
      canvas.getContext("experimental-webgl");

    webglSupport = Boolean(context);

    /** Release the probe context immediately. */
    (context as WebGLRenderingContext | null)
      ?.getExtension("WEBGL_lose_context")
      ?.loseContext();
  } catch {
    webglSupport = false;
  }

  return webglSupport;
}

const noopSubscribe = () => () => {};

/** WebGL availability + data-saver opt-out. `false` → render the fallback. */
export function useCanRender3D(): boolean {
  return useSyncExternalStore(
    noopSubscribe,
    () => {
      if (!detectWebGL()) return false;

      const connection = (
        navigator as Navigator & { connection?: { saveData?: boolean } }
      ).connection;

      return !connection?.saveData;
    },
    () => true,
  );
}

type InViewOptions = {
  rootMargin?: string;
  threshold?: number;
  /** Latch to `true` the first time the element intersects. */
  once?: boolean;
};

export function useInView<T extends Element>({
  rootMargin = "0px",
  threshold = 0,
  once = false,
}: InViewOptions = {}): { ref: RefObject<T | null>; inView: boolean } {
  const ref = useRef<T | null>(null);
  const [inView, setInView] = useState(false);

  useEffect(() => {
    const element = ref.current;

    if (!element || typeof IntersectionObserver === "undefined") {
      const frame = requestAnimationFrame(() => setInView(true));
      return () => cancelAnimationFrame(frame);
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (!entry) return;

        if (entry.isIntersecting) {
          setInView(true);
          if (once) observer.disconnect();
        } else if (!once) {
          setInView(false);
        }
      },
      { rootMargin, threshold },
    );

    observer.observe(element);

    return () => observer.disconnect();
  }, [rootMargin, threshold, once]);

  return { ref, inView };
}
