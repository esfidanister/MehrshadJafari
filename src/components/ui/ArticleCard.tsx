import Image from "next/image";
import Link from "next/link";
import { CalendarDays, Clock3 } from "lucide-react";

import { cn } from "@/lib/utils";

export type ArticleCardProps = {
  href: string;
  title: string;
  excerpt: string;
  imageSrc?: string;
  imageAlt?: string;
  category?: string;
  publishedAt?: string;
  /** Machine-readable date (ISO) for the <time> element. */
  dateTime?: string;
  readingTime?: string;
  className?: string;
  cms?: { title?: string; excerpt?: string; category?: string };
};

export function ArticleCard({
  href,
  title,
  excerpt,
  imageSrc,
  imageAlt = "",
  category,
  publishedAt,
  dateTime,
  readingTime,
  className,
  cms,
}: ArticleCardProps) {
  return (
    <article
      className={cn(
        "group grid gap-5 border-b border-border pb-8 md:grid-cols-[minmax(0,1fr)_12.5rem] md:gap-8",
        className,
      )}
    >
      <div className="flex min-w-0 flex-col gap-3">
        {category ? (
          <p className="eyebrow text-[0.6875rem] text-accent" {...(cms?.category ? { "data-cms": cms.category } : {})}>{category}</p>
        ) : null}

        <h3 className="text-xl font-medium tracking-tight text-text transition-colors duration-300 group-hover:text-medical md:text-2xl">
          <Link href={href} className="focus-visible:rounded-sm focus-visible:outline-offset-4">
            <span {...(cms?.title ? { "data-cms": cms.title } : {})}>{title}</span>
          </Link>
        </h3>

        <p className="line-clamp-3 text-sm leading-relaxed text-muted md:text-base" {...(cms?.excerpt ? { "data-cms": cms.excerpt } : {})}>
          {excerpt}
        </p>

        {(publishedAt || readingTime) && (
          <ul className="mt-1 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-muted">
            {publishedAt ? (
              <li className="inline-flex items-center gap-1.5">
                <CalendarDays className="size-3.5 text-medical" aria-hidden />
                <time dateTime={dateTime ?? publishedAt}>{publishedAt}</time>
              </li>
            ) : null}
            {readingTime ? (
              <li className="inline-flex items-center gap-1.5">
                <Clock3 className="size-3.5 text-medical" aria-hidden />
                <span>{readingTime}</span>
              </li>
            ) : null}
          </ul>
        )}
      </div>

      {imageSrc ? (
        <Link
          href={href}
          className={cn(
            "relative aspect-[4/3] overflow-hidden rounded-lg border border-border bg-bg",
            "transition-[border-color] duration-300 group-hover:border-medical/30",
          )}
        >
          <Image
            src={imageSrc}
            alt={imageAlt || title}
            fill
            sizes="200px"
            className="object-cover transition-transform duration-700 ease-[var(--ease-clinical)] group-hover:scale-[1.04]"
          />
        </Link>
      ) : null}
    </article>
  );
}
