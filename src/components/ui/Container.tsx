import type { HTMLAttributes, ReactNode } from "react";

import { cn } from "@/lib/utils";

export type ContainerProps = HTMLAttributes<HTMLDivElement> & {
  as?: "div" | "section" | "main" | "article";
  children: ReactNode;
  /** When true, removes horizontal max-width constraint (padding only). */
  fluid?: boolean;
};

export function Container({
  as: Component = "div",
  className,
  children,
  fluid = false,
  ...props
}: ContainerProps) {
  return (
    <Component
      className={cn(
        "mx-auto w-full ps-[var(--spacing-gutter)] pe-[var(--spacing-gutter)]",
        !fluid && "max-w-content",
        className,
      )}
      {...props}
    >
      {children}
    </Component>
  );
}
