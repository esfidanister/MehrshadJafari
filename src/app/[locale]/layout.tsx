import type { Metadata } from "next";
import { NextIntlClientProvider } from "next-intl";
import { getMessages, getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";
import type { ReactNode } from "react";

import { Footer } from "@/components/layout/Footer";
import { Header } from "@/components/layout/Header";
import { ClinicSchema } from "@/components/seo/ClinicSchema";
import { AppearanceProvider } from "@/components/providers/Appearance";
import { SmoothScroll } from "@/components/providers/SmoothScroll";
import { routing } from "@/i18n/routing";
import { fontVariables } from "@/lib/fonts";
import { getDir, isAppLocale } from "@/lib/i18n-helpers";
import type { Locale } from "@/lib/locale";
import { DEFAULT_SITE_URL } from "@/lib/seo";

import "../globals.css";

type Props = {
  children: ReactNode;
  params: Promise<{ locale: string }>;
};

export const dynamicParams = false;

export function generateStaticParams() {
  return routing.locales.map((locale) => ({ locale }));
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  if (!isAppLocale(locale)) return {};

  const t = await getTranslations({ locale, namespace: "seo" });

  return {
    metadataBase: new URL(DEFAULT_SITE_URL),
    title: t("siteName"),
    description: t("defaultDescription"),
    applicationName: "JAFARI",
  };
}

export default async function LocaleLayout({ children, params }: Props) {
  const { locale } = await params;
  if (!isAppLocale(locale)) notFound();

  setRequestLocale(locale);
  const messages = await getMessages();

  return (
    <html
      lang={locale}
      dir={getDir(locale)}
      className={`${fontVariables} h-full antialiased`}
      data-motion="quiet"
      data-hero="portrait"
      data-story="sticky"
      data-articles="cards"
      data-contact="band"
      suppressHydrationWarning
    >
      <body className="flex min-h-full flex-col" suppressHydrationWarning>
        <ClinicSchema locale={locale as Locale} />
        <NextIntlClientProvider locale={locale} messages={messages}>
          <AppearanceProvider>
          <SmoothScroll>
            <Header />
            <main id="main" className="flex-1">
              {children}
            </main>
            <Footer />
          </SmoothScroll>
          </AppearanceProvider>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
