"use client";

import { motion, useReducedMotion } from "framer-motion";
import { useTranslations } from "next-intl";

import { cn } from "@/lib/utils";

export type SceneLoaderProps = {
  className?: string;
  /** Hide the visible caption (still announced to screen readers). */
  silent?: boolean;
};

/** Soft, breathing ring — a quiet "preparing the model" indicator. */
export function SceneLoader({ className, silent = false }: SceneLoaderProps) {
  const t = useTranslations("common");
  const reduceMotion = useReducedMotion();

  return (
    <div
      role="status"
      aria-live="polite"
      className={cn(
        "pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-5",
        className,
      )}
    >
      <span className="relative flex size-14 items-center justify-center">
        <motion.span
          aria-hidden
          className="absolute inset-0 rounded-full border border-turquoise/70"
          animate={
            reduceMotion
              ? undefined
              : { scale: [0.82, 1.15, 0.82], opacity: [0.9, 0.2, 0.9] }
          }
          transition={{ duration: 3.2, ease: "easeInOut", repeat: Infinity }}
        />
        <motion.span
          aria-hidden
          className="absolute inset-3 rounded-full border border-medical/40"
          animate={reduceMotion ? undefined : { rotate: 360 }}
          transition={{ duration: 9, ease: "linear", repeat: Infinity }}
          style={{ borderTopColor: "var(--color-medical)" }}
        />
        <span aria-hidden className="size-1.5 rounded-full bg-medical/70" />
      </span>

      <span
        className={cn(
          "text-xs tracking-[0.18em] text-muted uppercase",
          silent && "sr-only",
        )}
      >
        {t("loading")}
      </span>
    </div>
  );
}
