"use client";

import {
  Children,
  useCallback,
  useEffect,
  useRef,
  useState,
  type KeyboardEvent,
  type ReactNode,
} from "react";
import { useReducedMotion } from "framer-motion";
import { useLocale } from "next-intl";

import { useAppearance } from "@/components/providers/Appearance";
import { formatIndex, isRtl, type Locale } from "@/lib/locale";
import { cn } from "@/lib/utils";

export type SnapRailProps = {
  label: string;
  children: ReactNode;
  /** Desktop column count. `feature` lets the first card span the row. */
  cols?: 2 | 3 | "feature";
  className?: string;
};

/** Horizontal snap rail on small screens, grid from the `md` breakpoint. */
export function SnapRail({ label, children, cols = 2, className }: SnapRailProps) {
  const locale = useLocale() as Locale;
  const rtl = isRtl(locale);
  const reduceMotion = useReducedMotion();
  const look = useAppearance();
  const quiet = reduceMotion === true || look.motion === "off";
  const track = useRef<HTMLUListElement>(null);
  const items = Children.toArray(children);
  const [index, setIndex] = useState(0);

  const readIndex = useCallback(() => {
    const list = track.current;
    const first = list?.firstElementChild as HTMLElement | null;
    if (!list || !first || getComputedStyle(list).display !== "flex") return;

    const gap = parseFloat(getComputedStyle(list).columnGap) || 0;
    const step = first.offsetWidth + gap;
    if (!step) return;

    const next = Math.min(items.length - 1, Math.round(Math.abs(list.scrollLeft) / step));
    setIndex((previous) => (previous === next ? previous : next));
  }, [items.length]);

  useEffect(() => {
    const list = track.current;
    if (!list) return;

    const observer = new ResizeObserver(readIndex);
    observer.observe(list);
    return () => observer.disconnect();
  }, [readIndex]);

  const go = (nextIndex: number) => {
    const list = track.current;
    const child = list?.children[nextIndex] as HTMLElement | undefined;
    if (!list || !child || getComputedStyle(list).display !== "flex") return;

    const gap = parseFloat(getComputedStyle(list).columnGap) || 0;
    const sign = rtl ? -1 : 1;
    list.scrollTo({
      left: sign * nextIndex * (child.offsetWidth + gap),
      behavior: quiet ? "auto" : "smooth",
    });
  };

  const onKeyDown = (event: KeyboardEvent<HTMLUListElement>) => {
    if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") return;
    const list = track.current;
    if (!list || getComputedStyle(list).display !== "flex") return;

    event.preventDefault();
    const forward = event.key === "ArrowRight";
    const delta = forward === !rtl ? 1 : -1;
    go(Math.min(items.length - 1, Math.max(0, index + delta)));
  };

  return (
    <div>
      <ul
        ref={track}
        tabIndex={0}
        aria-roledescription="carousel"
        aria-label={label}
        data-cols={cols}
        data-desk="grid"
        onScroll={readIndex}
        onKeyDown={onKeyDown}
        className={cn("snap-rail rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-medical", className)}
      >
        {items.map((child, itemIndex) => (
          <li
            key={itemIndex}
            className="snap-rail-item"
            role="group"
            aria-roledescription="slide"
            aria-label={`${formatIndex(itemIndex + 1, locale)} / ${formatIndex(items.length, locale)}`}
          >
            {child}
          </li>
        ))}
      </ul>

      {items.length > 1 ? (
        <div className="mt-4 flex items-center justify-between gap-4 md:hidden">
          <div className="flex items-center">
            {items.map((_, itemIndex) => (
              <button
                key={itemIndex}
                type="button"
                aria-label={formatIndex(itemIndex + 1, locale)}
                aria-current={itemIndex === index ? "true" : undefined}
                onClick={() => go(itemIndex)}
                className="inline-flex h-6 min-w-6 items-center justify-center rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-medical"
              >
                <span
                  aria-hidden
                  className={cn(
                    "h-1.5 rounded-full bg-border",
                    quiet ? "transition-none" : "transition-[width,background-color] duration-300",
                    itemIndex === index ? "w-7 bg-medical" : "w-1.5",
                  )}
                />
              </button>
            ))}
          </div>
          <p className="text-xs tabular-nums text-muted" dir="ltr">
            {formatIndex(index + 1, locale)}
            <span className="px-1 text-border">/</span>
            {formatIndex(items.length, locale)}
          </p>
        </div>
      ) : null}
    </div>
  );
}
