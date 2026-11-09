"use client";

import { useCallback, useEffect, useId, useRef, useState, useSyncExternalStore } from "react";
import { Menu, X } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";

import { Button } from "@/components/ui/Button";
import { Container } from "@/components/ui/Container";
import { Link, usePathname } from "@/i18n/navigation";
import type { Locale } from "@/lib/locale";
import { cn } from "@/lib/utils";

import { LanguageSwitcher } from "./LanguageSwitcher";
import { MobileNavigation } from "./MobileNavigation";
import { APPOINTMENT_HREF, NAV_ITEMS, PRIMARY_NAV, isActivePath } from "./navItems";

/** Height of the bar (4.5rem). Full-bleed heroes pull up by the same amount. */
const HEADER_HEIGHT_CLASS = "h-[4.5rem]";

const SCROLL_THRESHOLD = 16;

function subscribeToScroll(onChange: () => void) {
  window.addEventListener("scroll", onChange, { passive: true });

  return () => window.removeEventListener("scroll", onChange);
}

const getScrolled = () => window.scrollY > SCROLL_THRESHOLD;
const getServerScrolled = () => false;

function Logo({ label }: { label: string }) {
  return (
    <Link
      href="/"
      aria-label={label}
      className="group inline-flex items-center gap-3 rounded-sm focus-visible:outline-offset-4"
    >
      <svg
        viewBox="0 0 28 28"
        className="size-7 text-medical transition-transform duration-700 ease-[var(--ease-clinical)] group-hover:-translate-y-0.5"
        fill="none"
        aria-hidden
      >
        <path
          d="M5 7c0 8.2 3.6 14 9 14s9-5.8 9-14"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinecap="round"
        />
        <circle cx="5" cy="6" r="1.6" fill="currentColor" />
        <circle cx="23" cy="6" r="1.6" fill="currentColor" />
        <circle cx="14" cy="24.2" r="1" fill="var(--color-accent)" />
      </svg>
      <span
        dir="ltr"
        className="font-sans text-[1.0625rem] leading-none font-medium tracking-[0.34em] text-text"
      >
        <span data-cms="common.brand">{label}</span>
      </span>
    </Link>
  );
}

export function Header() {
  const locale = useLocale() as Locale;
  const pathname = usePathname();
  const t = useTranslations();
  const menuId = useId();
  const burger = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);

  const scrolled = useSyncExternalStore(
    subscribeToScroll,
    getScrolled,
    getServerScrolled,
  );

  const closeMenu = useCallback(() => setOpen(false), []);

  /** Close the sheet if the viewport grows past the mobile breakpoint. */
  useEffect(() => {
    const query = window.matchMedia("(min-width: 1024px)");
    const onChange = () => {
      if (query.matches) setOpen(false);
    };

    query.addEventListener("change", onChange);

    return () => query.removeEventListener("change", onChange);
  }, []);

  /** Return focus to the toggle after the sheet closes. */
  const wasOpen = useRef(false);
  useEffect(() => {
    if (wasOpen.current && !open) burger.current?.focus();
    wasOpen.current = open;
  }, [open]);

  const items = NAV_ITEMS.map((item) => ({
    ...item,
    label: t(`nav.${item.key}`),
    active: isActivePath(pathname, item.href),
  }));

  const solid = scrolled || open;

  return (
    <header
      className={cn("sticky top-0 z-50 w-full", HEADER_HEIGHT_CLASS)}
      data-scrolled={scrolled}
    >
      {/* Background layer: transparent at the top, frosted once scrolling. */}
      <div
        aria-hidden
        className={cn(
          "absolute inset-0 -z-10 border-b transition-[background-color,border-color,backdrop-filter,box-shadow] duration-500 ease-[var(--ease-clinical)]",
          solid
            ? "border-border/80 bg-bg/80 shadow-[0_10px_30px_-24px_rgb(24_40_50/0.35)] backdrop-blur-xl backdrop-saturate-150"
            : "border-transparent bg-transparent",
        )}
      />

      <Container className="relative z-50 flex h-full items-center justify-between gap-6">
        <Logo label={t("common.brand")} />

        <nav aria-label={t("common.brand")} className="hidden lg:block">
          <ul className="flex items-center gap-1">
            {items.filter((item) => PRIMARY_NAV.includes(item.key as (typeof PRIMARY_NAV)[number])).map((item) => (
              <li key={item.key}>
                <Link
                  href={item.href}
                  aria-current={item.active ? "page" : undefined}
                  className={cn(
                    "relative inline-flex h-10 items-center rounded-md px-3.5 text-[0.9375rem] tracking-tight",
                    "transition-colors duration-300",
                    "after:absolute after:inset-x-3.5 after:bottom-1.5 after:h-px after:origin-center after:scale-x-0 after:bg-medical",
                    "after:transition-transform after:duration-500 after:ease-[var(--ease-clinical)]",
                    "hover:text-medical hover:after:scale-x-100",
                    item.active
                      ? "font-medium text-medical after:scale-x-100"
                      : "text-text/80",
                  )}
                >
                  <span data-cms={`nav.${item.key}`}>{item.label}</span>
                </Link>
              </li>
            ))}
          </ul>
        </nav>

        <div className="flex items-center gap-2.5 sm:gap-3">
          <LanguageSwitcher className="hidden sm:inline-flex" />

          <Button asChild size="sm" className="hidden sm:inline-flex">
            <Link href={APPOINTMENT_HREF}>
              <span data-cms="nav.appointment">{t("nav.appointment")}</span>
            </Link>
          </Button>

          <button
            ref={burger}
            type="button"
            aria-label={open ? t("nav.menuClose") : t("nav.menuOpen")}
            aria-expanded={open}
            aria-controls={menuId}
            onClick={() => setOpen((value) => !value)}
            className={cn(
              "relative inline-flex h-10 items-center justify-center gap-2 rounded-full border border-border/80 bg-surface/70 px-3 text-text backdrop-blur-sm",
              "transition-colors duration-300 hover:border-medical/40 hover:text-medical",
              "focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-medical/30",
            )}
          >
            {open ? (
              <X className="size-[1.15rem]" strokeWidth={1.5} aria-hidden />
            ) : (
              <Menu className="size-[1.15rem]" strokeWidth={1.5} aria-hidden />
            )}
            <span className="hidden text-sm lg:inline" data-cms="nav.directory">{t("nav.directory")}</span>
          </button>
        </div>
      </Container>

      <MobileNavigation
        id={menuId}
        open={open}
        onClose={closeMenu}
        items={items}
        locale={locale}
        cta={{ href: APPOINTMENT_HREF, label: t("nav.appointment") }}
        brand={t("common.brand")}
        specialty={t("common.specialty")}
        closeLabel={t("nav.menuClose")}
        groups={{ care: t("nav.groupCare"), library: t("nav.groupLibrary") }}
      />
    </header>
  );
}
