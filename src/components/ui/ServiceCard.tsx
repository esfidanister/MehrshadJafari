import { ArrowUpLeft, ArrowUpRight } from "lucide-react";

import { Link } from "@/i18n/navigation";
import { cn } from "@/lib/utils";

export type ServiceCardProps = {
  href: string;
  index: number;
  title: string;
  description: string;
  cta: string;
  rtl?: boolean;
  className?: string;
};

/** Text-first treatment card (no photography required). */
export function ServiceCard({
  href,
  index,
  title,
  description,
  cta,
  rtl = false,
  className,
}: ServiceCardProps) {
  const Arrow = rtl ? ArrowUpLeft : ArrowUpRight;

  return (
    <article
      className={cn(
        "group relative flex h-full flex-col overflow-hidden rounded-xl border border-border bg-surface p-7 md:p-8",
        "transition-[border-color,box-shadow] duration-500 ease-[var(--ease-clinical)]",
        "hover:border-medical/30 hover:shadow-[0_18px_44px_-28px_rgb(24_40_50/0.4)]",
        className,
      )}
    >
      <span
        aria-hidden
        className="mb-8 font-sans text-sm tracking-[0.2em] text-accent"
        dir="ltr"
      >
        {String(index + 1).padStart(2, "0")}
      </span>

      <h3 className="text-xl font-medium tracking-tight text-text transition-colors duration-300 group-hover:text-medical md:text-2xl">
        <Link
          href={href}
          className="after:absolute after:inset-0 focus-visible:outline-none"
        >
          {title}
        </Link>
      </h3>

      <p className="mt-3 flex-1 text-sm leading-relaxed text-muted md:text-base">
        {description}
      </p>

      <span className="mt-8 inline-flex items-center gap-2 text-sm font-medium text-medical">
        <span className="border-b border-medical/25 pb-0.5 transition-colors duration-300 group-hover:border-medical">
          {cta}
        </span>
        <Arrow
          className="size-4 transition-transform duration-300 group-hover:-translate-y-0.5"
          aria-hidden
        />
      </span>
    </article>
  );
}
