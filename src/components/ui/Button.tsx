import {
  cloneElement,
  forwardRef,
  isValidElement,
  type ButtonHTMLAttributes,
  type ReactElement,
} from "react";

import { cn } from "@/lib/utils";

const variantStyles = {
  primary:
    "bg-medical text-white border border-transparent hover:bg-[color-mix(in_srgb,var(--color-medical)_88%,black)] active:bg-[color-mix(in_srgb,var(--color-medical)_92%,black)]",
  secondary:
    "bg-surface text-text border border-border hover:border-medical/35 hover:text-medical",
  ghost:
    "bg-transparent text-text border border-transparent hover:bg-border/50 hover:text-medical",
  outline:
    "bg-transparent text-medical border border-medical/40 hover:border-medical hover:bg-medical/5",
} as const;

const sizeStyles = {
  sm: "h-9 px-4 text-sm gap-1.5 rounded-md",
  md: "h-11 px-5 text-sm gap-2 rounded-md",
  lg: "h-12 px-7 text-base gap-2.5 rounded-lg",
} as const;

export type ButtonVariant = keyof typeof variantStyles;
export type ButtonSize = keyof typeof sizeStyles;

export function buttonVariants({
  variant = "primary",
  size = "md",
  className,
}: {
  variant?: ButtonVariant;
  size?: ButtonSize;
  className?: string;
} = {}) {
  return cn(
    "inline-flex items-center justify-center font-medium tracking-tight",
    "transition-[color,background-color,border-color,box-shadow,transform] duration-300 ease-[var(--ease-clinical)]",
    "disabled:pointer-events-none disabled:opacity-45",
    "focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-medical/30 focus-visible:ring-offset-2 focus-visible:ring-offset-bg",
    "[&_svg]:pointer-events-none [&_svg]:size-[1.05em] [&_svg]:shrink-0",
    variantStyles[variant],
    sizeStyles[size],
    className,
  );
}

export type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: ButtonVariant;
  size?: ButtonSize;
  asChild?: boolean;
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  (
    {
      className,
      variant = "primary",
      size = "md",
      asChild = false,
      children,
      type = "button",
      ...props
    },
    ref,
  ) => {
    const classes = buttonVariants({ variant, size, className });

    if (asChild) {
      if (!isValidElement(children)) {
        throw new Error("Button with asChild expects a single valid element child.");
      }

      const child = children as ReactElement<{ className?: string; ref?: unknown }>;

      return cloneElement(child, {
        ...props,
        className: cn(classes, child.props.className),
        ref,
      });
    }

    return (
      <button ref={ref} type={type} className={classes} {...props}>
        {children}
      </button>
    );
  },
);

Button.displayName = "Button";
