import type { HTMLAttributes, ReactNode } from "react";

import { cn } from "@/lib/utils";

export type SectionHeadingAlign = "start" | "center" | "end";

export type SectionHeadingProps = HTMLAttributes<HTMLDivElement> & {
  eyebrow?: ReactNode;
  title: ReactNode;
  description?: ReactNode;
  align?: SectionHeadingAlign;
  /** Renders title as the only h-level element when set */
  titleAs?: "h1" | "h2" | "h3";
  cms?: { eyebrow?: string; title?: string; description?: string };
};

const alignMap: Record<SectionHeadingAlign, string> = {
  start: "items-start text-start",
  center: "items-center text-center",
  end: "items-end text-end",
};

const descriptionAlignMap: Record<SectionHeadingAlign, string> = {
  start: "ms-0 me-auto max-w-2xl",
  center: "mx-auto max-w-2xl",
  end: "ms-auto me-0 max-w-2xl",
};

export function SectionHeading({
  eyebrow,
  title,
  description,
  align = "start",
  titleAs: TitleTag = "h2",
  cms,
  className,
  ...props
}: SectionHeadingProps) {
  return (
    <div
      className={cn("flex flex-col gap-4", alignMap[align], className)}
      {...props}
    >
      {eyebrow ? (
        <p className="eyebrow" data-slot="eyebrow" {...(cms?.eyebrow ? { "data-cms": cms.eyebrow } : {})}>
          {eyebrow}
        </p>
      ) : null}

      <TitleTag
        className={cn(
          "text-title font-medium tracking-tight text-text",
          align === "center" && "max-w-3xl text-balance",
        )}
        data-slot="title"
        {...(cms?.title ? { "data-cms": cms.title } : {})}
      >
        {title}
      </TitleTag>

      {description ? (
        <p
          className={cn(
            "text-base leading-relaxed text-muted md:text-lg",
            descriptionAlignMap[align],
          )}
          data-slot="description"
          {...(cms?.description ? { "data-cms": cms.description } : {})}
        >
          {description}
        </p>
      ) : null}
    </div>
  );
}
