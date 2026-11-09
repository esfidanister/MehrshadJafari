import type { ReactNode } from "react";

import { Reveal } from "@/components/motion/Reveal";
import { Container } from "@/components/ui/Container";
import { cn } from "@/lib/utils";

export type PageHeroProps = {
  eyebrow?: ReactNode;
  title: ReactNode;
  description?: ReactNode;
  children?: ReactNode;
  className?: string;
  cms?: { eyebrow?: string; title?: string; description?: string };
};

/** Editorial page header used by every inner page. */
export function PageHero({
  eyebrow,
  title,
  description,
  children,
  className,
  cms,
}: PageHeroProps) {
  return (
    <section
      className={cn(
        "relative isolate overflow-hidden border-b border-border bg-surface",
        className,
      )}
    >
      <div
        aria-hidden
        className="pointer-events-none absolute -top-40 -end-32 -z-10 size-[34rem] rounded-full bg-turquoise/25 blur-3xl"
      />
      <div
        aria-hidden
        className="pointer-events-none absolute -bottom-48 start-1/4 -z-10 size-[26rem] rounded-full bg-accent/15 blur-3xl"
      />

      <Container className="py-16 md:py-24">
        <Reveal className="flex max-w-3xl flex-col gap-5">
          {eyebrow ? (
            <p className="eyebrow" {...(cms?.eyebrow ? { "data-cms": cms.eyebrow } : {})}>
              {eyebrow}
            </p>
          ) : null}
          <h1
            className="text-display font-medium tracking-tight text-text"
            {...(cms?.title ? { "data-cms": cms.title } : {})}
          >
            {title}
          </h1>
          {description ? (
            <p
              className="max-w-2xl text-lg leading-relaxed text-muted md:text-xl"
              {...(cms?.description ? { "data-cms": cms.description } : {})}
            >
              {description}
            </p>
          ) : null}
          {children ? <div className="mt-3">{children}</div> : null}
        </Reveal>
      </Container>
    </section>
  );
}
