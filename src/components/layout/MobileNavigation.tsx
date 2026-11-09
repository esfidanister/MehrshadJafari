"use client";

import { useEffect, useRef } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";

import { useAppearance } from "@/components/providers/Appearance";
import { Link } from "@/i18n/navigation";
import { EASE_OUT_SOFT } from "@/lib/motion";
import { formatIndex, type Locale } from "@/lib/locale";
import { cn } from "@/lib/utils";

import { LanguageSwitcher } from "./LanguageSwitcher";
import { NAV_GROUPS } from "./navItems";

export type MobileNavigationItem = {
  key: string;
  href: string;
  label: string;
  active: boolean;
};

export type MobileNavigationProps = {
  id: string;
  open: boolean;
  onClose: () => void;
  items: ReadonlyArray<MobileNavigationItem>;
  locale: Locale;
  cta: { href: string; label: string };
  brand: string;
  specialty: string;
  closeLabel: string;
  groups: { care: string; library: string };
};

export function MobileNavigation({
  id,
  open,
  onClose,
  items,
  locale,
  cta,
  brand,
  specialty,
  closeLabel,
  groups,
}: MobileNavigationProps) {
  const reduceMotion = useReducedMotion();
  const look = useAppearance();
  const quiet = reduceMotion === true || look.motion === "off";
  const panel = useRef<HTMLDivElement>(null);
  const byKey = new Map(items.map((item) => [item.key, item]));
  const headings = { care: groups.care, library: groups.library };

  useEffect(() => {
    if (!open) return;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") onClose();
    };

    document.addEventListener("keydown", onKeyDown);
    const focusTimer = window.setTimeout(() => {
      panel.current?.querySelector<HTMLElement>("a[href]")?.focus();
    }, 60);

    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener("keydown", onKeyDown);
      window.clearTimeout(focusTimer);
    };
  }, [open, onClose]);

  return (
    <AnimatePresence>
      {open ? (
        <motion.div
          key="mobile-nav"
          id={id}
          ref={panel}
          data-lenis-prevent
          role="dialog"
          aria-modal="true"
          aria-label={brand}
          className="fixed inset-x-0 top-[4.5rem] bottom-0 z-40 flex flex-col bg-[#f4f2ec]"
          initial={quiet ? false : { opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          exit={quiet ? { opacity: 0 } : { opacity: 0, y: 10 }}
          transition={{ duration: 0.32, ease: EASE_OUT_SOFT }}
        >
          <div className="mx-auto min-h-0 w-full max-w-5xl flex-1 overflow-y-auto px-[var(--spacing-gutter)] py-8 md:py-12">
            <div className="grid gap-8 md:grid-cols-2 md:gap-16">
              {NAV_GROUPS.map((group) => (
                <section key={group.id} className="self-start rounded-[1.75rem] bg-white p-3 shadow-[0_24px_50px_-36px_rgb(24_40_50/0.35)] md:p-5">
                  <p className="px-3 pt-2 text-xs tracking-[0.2em] text-accent" data-cms={`nav.group${group.id === "care" ? "Care" : "Library"}`}>
                    {headings[group.id]}
                  </p>
                  <ul className="mt-2">
                    {group.keys.map((key, index) => {
                      const item = byKey.get(key);
                      if (!item) return null;
                      return (
                        <li key={key}>
                          <Link
                            href={item.href}
                            onClick={onClose}
                            aria-current={item.active ? "page" : undefined}
                            className={cn(
                              "flex items-center gap-4 rounded-2xl px-3 py-3 text-xl font-medium tracking-tight transition duration-300 md:text-2xl",
                              item.active ? "bg-[#f4f2ec] text-medical" : "text-text hover:bg-[#f4f2ec] hover:text-medical",
                            )}
                          >
                            <span className="w-8 text-xs font-normal tracking-[0.16em] text-muted tabular-nums" aria-hidden>
                              {formatIndex(index + 1, locale)}
                            </span>
                            <span className="flex-1" data-cms={`nav.${item.key}`}>{item.label}</span>
                          </Link>
                        </li>
                      );
                    })}
                  </ul>
                </section>
              ))}
            </div>
          </div>

          <div className="shrink-0 border-t border-border bg-[#f4f2ec] px-[var(--spacing-gutter)] py-4">
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <Link
                href={cta.href}
                onClick={onClose}
                className="inline-flex items-center justify-center rounded-full bg-[#14242c] px-6 py-3 text-sm font-medium text-white transition hover:bg-medical"
              >
                <span data-cms="nav.appointment">{cta.label}</span>
              </Link>
              <LanguageSwitcher variant="row" />
            </div>
            <p className="mx-auto mt-3 w-full max-w-5xl text-sm text-muted">
              <span className="tracking-[0.22em] text-text" dir="ltr">{brand}</span>
              <span className="mx-2 text-border">/</span>
              <span data-cms="common.specialty">{specialty}</span>
            </p>
          </div>
          <span className="sr-only">{closeLabel}</span>
        </motion.div>
      ) : null}
    </AnimatePresence>
  );
}
