import { hasLocale } from "next-intl";

import { formatWhen } from "@/lib/calendar";
import { routing } from "@/i18n/routing";

export type AppLocale = (typeof routing.locales)[number];

export type Bilingual = { fa: string; en: string; ar?: string; zh?: string };

export function isAppLocale(value: string): value is AppLocale {
  return hasLocale(routing.locales, value);
}

export function getDir(locale: string): "rtl" | "ltr" {
  return locale === "fa" || locale === "ar" ? "rtl" : "ltr";
}

/** Persian first, then Arabic or Chinese when present, otherwise English. */
export function pick(value: Bilingual, locale: string): string {
  if (locale === "fa") return value.fa;
  if (locale === "ar") return value.ar || value.en;
  if (locale === "zh") return value.zh || value.en;
  return value.en;
}

export function formatDate(iso: string, locale: string): string {
  return formatWhen(iso, locale);
}

/** Locale-aware number (Persian digits for `fa`). */
export function formatNumber(value: number | string, locale: string): string {
  const intl = locale === "fa" ? "fa-IR" : locale === "ar" ? "ar" : locale === "zh" ? "zh-CN" : "en-US";
  return new Intl.NumberFormat(intl, {
    maximumFractionDigits: 1,
  }).format(Number(value));
}

export function toPersianDigits(value: string): string {
  return value.replace(/\d/g, (d) => "۰۱۲۳۴۵۶۷۸۹"[Number(d)]);
}

/** tel: href from a local Iranian number like 021-22579316. */
export function telHref(phone: string): string {
  const digits = phone.replace(/\D/g, "").replace(/^0/, "");
  return `tel:+98${digits}`;
}
