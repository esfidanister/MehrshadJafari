import { Quote } from "lucide-react";

import { cn } from "@/lib/utils";

export type ReviewCardProps = {
  quote: string;
  name: string;
  role?: string;
  treatment?: string;
  rating?: number;
  className?: string;
};

function Stars({ rating }: { rating: number }) {
  const safe = Math.min(5, Math.max(0, Math.round(rating)));

  return (
    <div
      className="flex items-center gap-0.5"
      role="img"
      aria-label={`${safe} out of 5 stars`}
    >
      {Array.from({ length: 5 }).map((_, index) => (
        <span
          key={index}
          className={cn(
            "size-1.5 rounded-full",
            index < safe ? "bg-accent" : "bg-border",
          )}
          aria-hidden
        />
      ))}
    </div>
  );
}

export function ReviewCard({
  quote,
  name,
  role,
  treatment,
  rating,
  className,
}: ReviewCardProps) {
  return (
    <figure
      className={cn(
        "flex h-full flex-col gap-6 rounded-xl border border-border bg-surface p-6 md:p-8",
        className,
      )}
    >
      <Quote
        className="size-8 text-medical"
        strokeWidth={1.25}
        aria-hidden
      />

      <blockquote className="flex-1 text-base leading-relaxed text-text md:text-lg">
        <p className="text-pretty">&ldquo;{quote}&rdquo;</p>
      </blockquote>

      <figcaption className="flex flex-col gap-2 border-t border-border pt-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-sm font-medium text-text">{name}</p>
            {role ? <p className="text-xs text-muted">{role}</p> : null}
          </div>
          {typeof rating === "number" ? <Stars rating={rating} /> : null}
        </div>
        {treatment ? (
          <p className="text-xs tracking-wide text-medical uppercase">
            {treatment}
          </p>
        ) : null}
      </figcaption>
    </figure>
  );
}
