"use client";

import { useEffect, useState } from "react";

import { formatYear } from "@/lib/calendar";
import { type Locale } from "@/lib/locale";

/**
 * Starts at a build-time constant and refreshes on the client — avoids reading
 * the clock during prerender (static export) while staying current.
 */
const BUILD_YEAR: Record<Locale, string> = { fa: "۱۴۰۵", en: "2026", ar: "2026", zh: "2026" };

export function CopyrightYear({ locale }: { locale: Locale }) {
  const [year, setYear] = useState(BUILD_YEAR[locale]);

  useEffect(() => {
    const frame = requestAnimationFrame(() => setYear(formatYear(new Date(), locale)));
    return () => cancelAnimationFrame(frame);
  }, [locale]);

  return <>{year}</>;
}
