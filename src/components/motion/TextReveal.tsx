"use client";

import {
  Children,
  isValidElement,
  cloneElement,
  type ReactElement,
  type ReactNode,
} from "react";
import { motion, useReducedMotion } from "framer-motion";

import { staggerContainer, staggerItem, viewportOnce } from "@/lib/motion";
import { cn } from "@/lib/utils";

export type TextRevealProps = {
  children: ReactNode;
  className?: string;
  as?: "div" | "ul" | "ol";
  stagger?: number;
};

/**
 * Staggered children reveal. Keeps a stable DOM structure across SSR/CSR so
 * `useReducedMotion()` cannot flip between plain and motion elements mid-hydrate.
 */
export function TextReveal({
  children,
  className,
  stagger = 0.08,
}: TextRevealProps) {
  const reduceMotion = useReducedMotion();
  const quiet = reduceMotion === true;
  const items = Children.toArray(children).filter(Boolean);

  return (
    <motion.div
      className={cn(className)}
      initial={quiet ? false : "hidden"}
      whileInView={quiet ? undefined : "visible"}
      viewport={quiet ? undefined : viewportOnce}
      variants={
        quiet
          ? undefined
          : {
              ...staggerContainer,
              visible: {
                transition: {
                  staggerChildren: stagger,
                  delayChildren: 0.04,
                },
              },
            }
      }
    >
      {items.map((child, index) => {
        if (isValidElement(child)) {
          const element = child as ReactElement<{ className?: string }>;
          return (
            <motion.div
              key={element.key ?? index}
              variants={quiet ? undefined : staggerItem}
            >
              {cloneElement(element, {
                className: cn(element.props.className),
              })}
            </motion.div>
          );
        }

        return (
          <motion.span
            key={index}
            variants={quiet ? undefined : staggerItem}
            className="block"
          >
            {child}
          </motion.span>
        );
      })}
    </motion.div>
  );
}

export function TextRevealItem({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  const reduceMotion = useReducedMotion();
  const quiet = reduceMotion === true;

  return (
    <motion.span
      variants={quiet ? undefined : staggerItem}
      className={cn("block", className)}
    >
      {children}
    </motion.span>
  );
}
