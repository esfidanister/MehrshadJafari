import Image from "next/image";
import Link from "next/link";
import type { ReactNode } from "react";
import { ArrowUpLeft, ArrowUpRight } from "lucide-react";

import { cn } from "@/lib/utils";

export type TreatmentCardProps = {
  href: string;
  title: string;
  description: string;
  imageSrc: string;
  imageAlt: string;
  eyebrow?: string;
  className?: string;
  /** Use when layout is RTL for correct link icon direction */
  rtl?: boolean;
  footer?: ReactNode;
};

export function TreatmentCard({
  href,
  title,
  description,
  imageSrc,
  imageAlt,
  eyebrow,
  className,
  rtl = false,
  footer,
}: TreatmentCardProps) {
  const Arrow = rtl ? ArrowUpLeft : ArrowUpRight;

  return (
    <article
      className={cn(
        "group relative flex flex-col overflow-hidden rounded-xl border border-border bg-surface",
        "transition-[border-color,box-shadow] duration-500 ease-[var(--ease-clinical)]",
        "hover:border-medical/25 hover:shadow-[0_12px_40px_-24px_rgb(24_40_50/0.35)]",
        className,
      )}
    >
      <Link href={href} className="flex flex-col focus-visible:outline-none">
        <div className="relative aspect-[5/4] overflow-hidden bg-bg">
          <Image
            src={imageSrc}
            alt={imageAlt}
            fill
            sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
            className="object-cover transition-transform duration-700 ease-[var(--ease-clinical)] group-hover:scale-[1.03]"
          />
          <div
            aria-hidden
            className="pointer-events-none absolute inset-0 bg-gradient-to-t from-text/15 via-transparent to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"
          />
        </div>

        <div className="flex flex-1 flex-col gap-3 p-5 md:p-6">
          {eyebrow ? <p className="eyebrow text-[0.6875rem]">{eyebrow}</p> : null}

          <h3 className="text-lg font-medium tracking-tight text-text transition-colors duration-300 group-hover:text-medical md:text-xl">
            {title}
          </h3>

          <p className="line-clamp-3 text-sm leading-relaxed text-muted md:text-base">
            {description}
          </p>

          <span className="mt-auto inline-flex items-center gap-2 pt-2 text-sm font-medium text-medical">
            <span className="border-b border-medical/25 pb-0.5 transition-[border-color,gap] duration-300 group-hover:border-medical">
              View treatment
            </span>
            <Arrow
              className="size-4 transition-transform duration-300 group-hover:-translate-y-0.5 group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5"
              aria-hidden
            />
          </span>
        </div>
      </Link>

      {footer ? (
        <div className="border-t border-border/80 px-5 py-4 text-sm text-muted md:px-6">
          {footer}
        </div>
      ) : null}
    </article>
  );
}
