export type Locale = "fa" | "en" | "ar" | "zh";

export const LOCALES: readonly Locale[] = ["fa", "en", "ar", "zh"] as const;

export function isLocale(value: string): value is Locale {
  return value === "fa" || value === "en" || value === "ar" || value === "zh";
}

export function isRtl(locale: Locale): boolean {
  return locale === "fa" || locale === "ar";
}

export type Copy = { fa: string; en: string; ar?: string; zh?: string };

/** Persian first, then the locale's own text, then English. */
export function pick(value: Copy, locale: Locale): string {
  if (locale === "fa") return value.fa;
  if (locale === "ar") return value.ar || value.en;
  if (locale === "zh") return value.zh || value.en;
  return value.en;
}

/** Zero-padded, locale-aware number — "01" / "۰۱". */
export function formatIndex(value: number, locale: Locale): string {
  const intl = locale === "fa" ? "fa-IR" : locale === "ar" ? "ar" : locale === "zh" ? "zh-CN" : "en-US";
  return new Intl.NumberFormat(intl, {
    minimumIntegerDigits: 2,
    useGrouping: false,
  }).format(value);
}

export function formatNumber(
  value: number,
  locale: Locale,
  options?: Intl.NumberFormatOptions,
): string {
  const intl = locale === "fa" ? "fa-IR" : locale === "ar" ? "ar" : locale === "zh" ? "zh-CN" : "en-US";
  return new Intl.NumberFormat(intl, options).format(value);
}

import { formatWhen } from "@/lib/calendar";

/** ISO or Jalali date → Shamsi in Persian, Gregorian in English. */
export function formatDate(iso: string, locale: Locale): string {
  return formatWhen(iso, locale);
}
