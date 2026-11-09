import type {Metadata} from 'next';

import {routing} from '@/i18n/routing';

const DEFAULT_SITE_URL =
  process.env.NEXT_PUBLIC_SITE_URL ?? 'https://drmehrshadjafari.ir';

const OPEN_GRAPH_LOCALE: Record<string, string> = {
  fa: 'fa_IR',
  en: 'en_US',
  ar: 'ar_AR',
  zh: 'zh_CN'
};

function openGraphLocale(locale: string): string {
  return OPEN_GRAPH_LOCALE[locale] ?? 'en_US';
}

/** Real portrait. 1179×1334. The desk can replace it per page. */
export const SHARE_IMAGE = {
  url: '/visuals/doctor-portrait.webp',
  width: 1179,
  height: 1334
} as const;

export type BuildPageMetadataInput = {
  locale: string;
  /** Path without locale prefix, e.g. `/treatments/wisdom-tooth-surgery` */
  pathname: string;
  title: string;
  description: string;
  /** Accessible name for the share image. */
  imageAlt: string;
  keywords?: string[];
  openGraph?: {
    title?: string;
    description?: string;
    type?: 'website' | 'article';
    images?: string[];
  };
  robots?: Metadata['robots'];
};

/** Build a locale-prefixed path for App Router i18n (`localePrefix: 'always'`). */
export function localePath(locale: string, pathname: string): string {
  const base = pathname === '/' ? '' : pathname.replace(/\/+$/, '');
  // Static export uses `trailingSlash: true`, so canonical URLs end with `/`.
  return `/${locale}${base}/`;
}

/** Absolute URL for a localized path. */
export function absoluteLocaleUrl(locale: string, pathname: string): string {
  const path = localePath(locale, pathname);
  return new URL(path, DEFAULT_SITE_URL).toString();
}

/** hreflang alternate language URLs for a given logical pathname. */
export function buildAlternates(pathname: string): Metadata['alternates'] {
  const languages: Record<string, string> = {};

  for (const locale of routing.locales) {
    languages[locale] = absoluteLocaleUrl(locale, pathname);
  }

  languages['x-default'] = absoluteLocaleUrl(
    routing.defaultLocale,
    pathname
  );

  return {
    canonical: absoluteLocaleUrl(routing.defaultLocale, pathname),
    languages
  };
}

export function buildPageMetadata({
  locale,
  pathname,
  title,
  description,
  imageAlt,
  keywords,
  openGraph,
  robots
}: BuildPageMetadataInput): Metadata {
  const canonical = absoluteLocaleUrl(locale, pathname);
  const images = openGraph?.images?.length
    ? openGraph.images.map((url) => ({url}))
    : [{...SHARE_IMAGE, alt: imageAlt}];
  const shareUrl = images[0]?.url ?? SHARE_IMAGE.url;

  return {
    title,
    description,
    keywords,
    alternates: {
      ...buildAlternates(pathname),
      canonical
    },
    openGraph: {
      title: openGraph?.title ?? title,
      description: openGraph?.description ?? description,
      url: canonical,
      locale: openGraphLocale(locale),
      alternateLocale: routing.locales
        .filter((item) => item !== locale)
        .map((item) => openGraphLocale(item)),
      type: openGraph?.type ?? 'website',
      siteName: 'JAFARI',
      images
    },
    twitter: {
      card: 'summary_large_image',
      title: openGraph?.title ?? title,
      description: openGraph?.description ?? description,
      images: [shareUrl]
    },
    robots: robots ?? {
      index: true,
      follow: true
    }
  };
}

export {DEFAULT_SITE_URL};
