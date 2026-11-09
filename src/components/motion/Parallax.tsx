"use client";

import { useEffect, useRef, useState, type ReactNode } from "react";
import {
  motion,
  useReducedMotion,
  useScroll,
  useSpring,
  useTransform,
} from "framer-motion";

import { cn } from "@/lib/utils";

export type ParallaxProps = {
  children: ReactNode;
  className?: string;
  /** Vertical shift in pixels at full scroll through the element */
  offset?: number;
  disabledOnMobile?: boolean;
};

export function Parallax({
  children,
  className,
  offset = 36,
  disabledOnMobile = true,
}: ParallaxProps) {
  const ref = useRef<HTMLDivElement>(null);
  const reduceMotion = useReducedMotion();
  const [isMobile, setIsMobile] = useState(false);

  useEffect(() => {
    const query = window.matchMedia("(max-width: 767px)");

    const update = () => setIsMobile(query.matches);
    update();
    query.addEventListener("change", update);
    return () => query.removeEventListener("change", update);
  }, []);

  const { scrollYProgress } = useScroll({
    target: ref,
    offset: ["start end", "end start"],
  });

  const rawY = useTransform(scrollYProgress, [0, 1], [offset * 0.5, -offset * 0.5]);
  const y = useSpring(rawY, { stiffness: 90, damping: 22, mass: 0.6 });

  const disabled = reduceMotion || (disabledOnMobile && isMobile);

  if (disabled) {
    return (
      <div ref={ref} className={cn(className)}>
        {children}
      </div>
    );
  }

  return (
    <div ref={ref} className={cn("relative overflow-hidden", className)}>
      <motion.div style={{ y }} className="will-change-transform">
        {children}
      </motion.div>
    </div>
  );
}
