"use client";

import { useCallback, useEffect, useRef, useState, type KeyboardEvent } from "react";
import { useReducedMotion } from "framer-motion";
import { ChevronLeft, ChevronRight, Quote } from "lucide-react";
import { useTranslations } from "next-intl";

import { Reveal } from "@/components/motion/Reveal";
import { Container } from "@/components/ui/Container";
import { Link } from "@/i18n/navigation";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { patientReviews, reviewAggregate } from "@/content/reviews";
import { formatWhen } from "@/lib/calendar";
import { formatNumber, isRtl, type Locale } from "@/lib/locale";
import { cn } from "@/lib/utils";

import { sectionCopy } from "./sectionCopy";

export type ReviewsSectionProps = {
  locale: Locale;
};

function Rating({ value, label }: { value: number; label: string }) {
  const full = Math.round(value);

  return (
    <div role="img" aria-label={label} className="flex items-center gap-1">
      {Array.from({ length: 5 }).map((_, index) => (
        <span
          key={index}
          aria-hidden
          className={cn(
            "size-2 rounded-full",
            index < full ? "bg-accent" : "bg-border",
          )}
        />
      ))}
    </div>
  );
}

type ScrollState = { index: number; canPrev: boolean; canNext: boolean };

export function ReviewsSection({ locale }: ReviewsSectionProps) {
  const t = useTranslations("reviews");
  const common = useTranslations("common");
  const copy = sectionCopy[locale].reviews;
  const reduceMotion = useReducedMotion();
  const rtl = isRtl(locale);
  const track = useRef<HTMLUListElement>(null);
  const total = patientReviews.length;

  const [state, setState] = useState<ScrollState>({
    index: 0,
    canPrev: false,
    canNext: true,
  });

  const getStep = useCallback(() => {
    const list = track.current;
    const first = list?.firstElementChild as HTMLElement | null;

    if (!list || !first) return 0;

    const gap = parseFloat(getComputedStyle(list).columnGap) || 0;

    return first.offsetWidth + gap;
  }, []);

  const measure = useCallback(() => {
    const list = track.current;
    const step = getStep();

    if (!list || step === 0) return;

    const offset = Math.abs(list.scrollLeft);
    const max = list.scrollWidth - list.clientWidth;
    const next: ScrollState = {
      index: Math.min(total - 1, Math.round(offset / step)),
      canPrev: offset > 4,
      canNext: offset < max - 4,
    };

    setState((previous) =>
      previous.index === next.index &&
      previous.canPrev === next.canPrev &&
      previous.canNext === next.canNext
        ? previous
        : next,
    );
  }, [getStep, total]);

  useEffect(() => {
    const list = track.current;
    if (!list) return;

    const observer = new ResizeObserver(measure);
    observer.observe(list);

    return () => observer.disconnect();
  }, [measure]);

  const go = useCallback(
    (direction: 1 | -1) => {
      const list = track.current;
      if (!list) return;

      const step = getStep();
      const sign = rtl ? -1 : 1;

      list.scrollBy({
        left: direction * sign * step,
        behavior: reduceMotion ? "auto" : "smooth",
      });
    },
    [getStep, reduceMotion, rtl],
  );

  const onKeyDown = (event: KeyboardEvent<HTMLElement>) => {
    if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") return;

    event.preventDefault();

    const visualForward = event.key === "ArrowRight";
    /** "Next" is visually on the right in LTR and on the left in RTL. */
    go(visualForward === !rtl ? 1 : -1);
  };

  const PrevIcon = rtl ? ChevronRight : ChevronLeft;
  const NextIcon = rtl ? ChevronLeft : ChevronRight;

  const average = formatNumber(reviewAggregate.averageRating, locale, {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  });

  const controlClass = cn(
    "inline-flex size-12 items-center justify-center rounded-full border border-border bg-surface text-text",
    "transition-[border-color,color,background-color,opacity] duration-300 ease-[var(--ease-clinical)]",
    "hover:border-medical hover:bg-medical hover:text-white",
    "focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-medical/30",
    "disabled:pointer-events-none disabled:opacity-35",
  );

  return (
    <section
      id="reviews"
      dir={rtl ? "rtl" : "ltr"}
      className="relative scroll-mt-24 overflow-hidden border-y border-border bg-surface py-12 md:py-24 lg:py-32"
    >
      <Container>
        <div className="grid gap-6 lg:grid-cols-12 lg:items-end lg:gap-10">
          <Reveal className="lg:col-span-7">
            <SectionHeading
              eyebrow={t("eyebrow")}
              title={t("title")}
              description={t("description")}
              cms={{ eyebrow: "reviews.eyebrow", title: "reviews.title", description: "reviews.description" }}
            />
          </Reveal>

          <Reveal
            delay={0.1}
            className="flex items-center gap-6 lg:col-span-5 lg:justify-end"
          >
            <div className="flex items-baseline gap-2">
              <span className="text-5xl leading-none font-light tracking-tight text-text tabular-nums md:text-6xl">
                {average}
              </span>
              <span className="text-sm text-muted">{copy.outOfFive}</span>
            </div>
            <div className="space-y-2 border-s border-border ps-6">
              <Rating
                value={reviewAggregate.averageRating}
                label={`${average} ${copy.outOfFive}`}
              />
              <p className="text-sm text-muted">
                {t("countLabel")}:{" "}
                <span className="font-medium text-text tabular-nums">
                  {formatNumber(reviewAggregate.totalCount, locale)}
                </span>
              </p>
              <p className="max-w-56 text-xs leading-relaxed text-muted" data-cms="reviews.importedDataNote">
                {t("importedDataNote")}
              </p>
            </div>
          </Reveal>
        </div>

        <section
          aria-roledescription="carousel"
          aria-label={copy.carouselLabel}
          onKeyDown={onKeyDown}
          className="mt-8 md:mt-16"
        >
          <ul
            ref={track}
            tabIndex={0}
            onScroll={measure}
            className={cn(
              "-mx-[clamp(1.25rem,4vw,2.5rem)] flex snap-x snap-mandatory gap-6 overflow-x-auto px-[clamp(1.25rem,4vw,2.5rem)] pb-2",
              "scroll-ps-[clamp(1.25rem,4vw,2.5rem)] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden",
              "rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-medical",
            )}
          >
            {patientReviews.map((review, index) => (
              <li
                key={review.id}
                role="group"
                aria-roledescription="slide"
                aria-label={copy.slideOf(index + 1, total)}
                className="flex basis-[88%] shrink-0 snap-start sm:basis-[calc(50%-0.75rem)] lg:basis-[calc(33.333%-1rem)]"
              >
                <figure className="flex w-full flex-col gap-6 rounded-xl border border-border bg-bg p-7 md:p-8">
                  <Quote
                    className="size-7 text-medical"
                    strokeWidth={1.25}
                    aria-hidden
                  />

                  <blockquote
                    lang="fa"
                    dir="rtl"
                    className="font-persian line-clamp-5 flex-1 text-[1.0625rem] leading-[1.9] text-text"
                  >
                    {review.text}
                  </blockquote>

                  <figcaption className="flex flex-col gap-3 border-t border-border pt-5">
                    <div className="flex items-center justify-between gap-3">
                      {review.topic ? (
                        <span className="inline-flex items-center rounded-full bg-medical/8 px-3 py-1 text-xs font-medium text-medical">
                          {copy.topics[review.topic]}
                        </span>
                      ) : (
                        <span />
                      )}
                      <time
                        lang="fa"
                        className="text-xs text-muted tabular-nums"
                        dir="ltr"
                      >
                        {formatWhen(review.date, locale)}
                      </time>
                    </div>
                    <p className="text-xs text-muted" data-cms="reviews.sourceLabel">
                      {t("sourceLabel")}
                      {locale !== "fa" ? ` · ${copy.originalPersian}` : null}
                    </p>
                  </figcaption>
                </figure>
              </li>
            ))}
          </ul>

          <div className="mt-8 flex items-center justify-between gap-6">
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => go(-1)}
                disabled={!state.canPrev}
                aria-label={copy.previous}
                className={controlClass}
              >
                <PrevIcon className="size-5" strokeWidth={1.5} aria-hidden />
              </button>
              <button
                type="button"
                onClick={() => go(1)}
                disabled={!state.canNext}
                aria-label={copy.next}
                className={controlClass}
              >
                <NextIcon className="size-5" strokeWidth={1.5} aria-hidden />
              </button>
            </div>

            <div className="flex flex-1 items-center justify-end gap-4">
              <div
                aria-hidden
                className="h-px max-w-48 flex-1 overflow-hidden bg-border"
              >
                <div
                  className="h-full origin-left bg-medical transition-transform duration-500 ease-[var(--ease-clinical)] rtl:origin-right"
                  style={{ transform: `scaleX(${(state.index + 1) / total})` }}
                />
              </div>
              <p
                className="text-sm text-muted tabular-nums"
                aria-live="polite"
                aria-atomic
              >
                {formatNumber(state.index + 1, locale)} /{" "}
                {formatNumber(total, locale)}
              </p>
            </div>
          </div>
        </section>

        <p className="mt-6">
          <Link href="/reviews" className="text-sm font-medium text-medical underline-offset-4 hover:underline">
            <span data-cms="common.viewAll">{common("viewAll")}</span>
          </Link>
        </p>

        <Reveal className="mt-8 grid gap-2 border-t border-border pt-5 text-[0.8125rem] leading-relaxed text-muted md:mt-12 md:grid-cols-2 md:gap-8 md:pt-6">
          <p data-cms="reviews.importedDataNote">
            {t("importedDataNote")}
          </p>
          <p data-cms="reviews.patientExperienceDisclaimer">{t("patientExperienceDisclaimer")}</p>
        </Reveal>
      </Container>
    </section>
  );
}
