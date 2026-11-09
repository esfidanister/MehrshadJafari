"use client";

import { Globe } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";

import { Link, usePathname } from "@/i18n/navigation";
import type { Locale } from "@/lib/locale";
import { cn } from "@/lib/utils";

const LOCALES: { id: Locale; label: string }[] = [
  { id: "fa", label: "فارسی" },
  { id: "en", label: "English" },
  { id: "ar", label: "العربية" },
  { id: "zh", label: "中文" },
];

export type LanguageSwitcherProps = {
  className?: string;
  variant?: "menu" | "row";
};

/** Four languages, same page. Real links, so it works without JavaScript. */
export function LanguageSwitcher({ className, variant = "menu" }: LanguageSwitcherProps) {
  const locale = useLocale() as Locale;
  const pathname = usePathname();
  const t = useTranslations("common");
  const current = LOCALES.find((item) => item.id === locale) ?? LOCALES[0];

  if (variant === "row") {
    return (
      <ul className={cn("flex flex-wrap gap-2", className)} aria-label={t("languageMenu")}>
        {LOCALES.map((item) => (
          <li key={item.id}>
            <Link
              href={pathname}
              locale={item.id}
              hrefLang={item.id}
              lang={item.id}
              prefetch={false}
              aria-current={item.id === locale ? "true" : undefined}
              className={cn(
                "inline-flex rounded-full border px-3.5 py-1.5 text-sm",
                item.id === locale
                  ? "border-[#14242c] bg-[#14242c] text-white"
                  : "border-border bg-white text-text hover:border-medical",
              )}
            >
              {item.label}
            </Link>
          </li>
        ))}
      </ul>
    );
  }

  return (
    <details className={cn("group relative", className)}>
      <summary
        className={cn(
          "flex h-9 cursor-pointer list-none items-center gap-2 rounded-full border border-border/80 bg-surface/60 px-3.5",
          "text-[0.8125rem] font-medium text-text [&::-webkit-details-marker]:hidden",
        )}
      >
        <Globe className="size-3.5 text-medical" strokeWidth={1.5} aria-hidden />
        <span>{current.label}</span>
        <span className="sr-only">{t("languageMenu")}</span>
      </summary>
      <ul className="absolute end-0 z-50 mt-2 min-w-36 rounded-2xl border border-border bg-surface p-1.5 shadow-[0_16px_40px_-28px_rgb(24_40_50)]">
        {LOCALES.map((item) => (
          <li key={item.id}>
            <Link
              href={pathname}
              locale={item.id}
              hrefLang={item.id}
              lang={item.id}
              prefetch={false}
              aria-current={item.id === locale ? "true" : undefined}
              className={cn(
                "block rounded-xl px-3 py-2 text-sm",
                item.id === locale ? "bg-medical/10 text-medical" : "text-text hover:bg-bg",
              )}
            >
              {item.label}
            </Link>
          </li>
        ))}
      </ul>
    </details>
  );
}
