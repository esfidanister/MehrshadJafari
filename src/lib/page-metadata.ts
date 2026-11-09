import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";

import { buildPageMetadata } from "@/lib/seo";
import { seoPages, type SeoPageKey } from "@/lib/site";

/** Name already used on the portrait. The desk does not invent a second caption. */
export async function shareImageAlt(locale: string): Promise<string> {
  const t = await getTranslations({ locale, namespace: "common" });
  return `${t("doctorName")}, ${t("specialty")}`;
}

/** Metadata for a top-level page, driven by `seo.pages.<key>` messages. */
export async function getPageMetadata(
  locale: string,
  key: SeoPageKey,
): Promise<Metadata> {
  const t = await getTranslations({ locale, namespace: "seo.pages" });

  return buildPageMetadata({
    locale,
    pathname: seoPages[key],
    title: t(`${key}.title`),
    description: t(`${key}.description`),
    imageAlt: await shareImageAlt(locale),
  });
}
