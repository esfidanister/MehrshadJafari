"use client";

import type { ReactNode } from "react";
import {
  motion,
  useReducedMotion,
  type HTMLMotionProps,
  type Variants,
} from "framer-motion";

import { fadeUp, transition, viewportOnce } from "@/lib/motion";
import { cn } from "@/lib/utils";
import { useAppearance } from "@/components/providers/Appearance";

export type RevealProps = Omit<HTMLMotionProps<"div">, "children"> & {
  children?: ReactNode;
  delay?: number;
  variants?: Variants;
};

/**
 * Always renders the same motion.div tree on server and client to avoid
 * hydration mismatches from `useReducedMotion()` (null → boolean).
 */
export function Reveal({
  children,
  className,
  delay = 0,
  variants = fadeUp,
  ...props
}: RevealProps) {
  const reduceMotion = useReducedMotion();
  const look = useAppearance();
  const quiet = reduceMotion === true || look.motion === "off";
  const distance = look.motion === "gentle" ? 28 : 12;
  const motionVariants = variants === fadeUp
    ? {
        hidden: { opacity: 0, y: distance },
        visible: { opacity: 1, y: 0, transition: { ...transition.default, duration: look.motion === "gentle" ? 0.65 : 0.45 } },
      }
    : variants;

  return (
    <motion.div
      className={cn(className)}
      initial={quiet ? false : "hidden"}
      whileInView={quiet ? undefined : "visible"}
      viewport={quiet ? undefined : viewportOnce}
      variants={quiet ? undefined : motionVariants}
      transition={quiet ? undefined : { ...transition.default, delay }}
      {...props}
    >
      {children}
    </motion.div>
  );
}
