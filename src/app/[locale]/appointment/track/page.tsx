import type { Metadata } from "next";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";

import { TrackForm } from "@/components/booking/TrackForm";
import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/ui/PageHero";
import { isAppLocale } from "@/lib/i18n-helpers";
import { getPageMetadata } from "@/lib/page-metadata";

type Props = { params: Promise<{ locale: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  if (!isAppLocale(locale)) return {};
  return getPageMetadata(locale, "track");
}

export default async function TrackPage({ params }: Props) {
  const { locale } = await params;
  if (!isAppLocale(locale)) notFound();
  setRequestLocale(locale);
  const t = await getTranslations({ locale });

  return (
    <>
      <PageHero
        eyebrow={t("track.eyebrow")}
        title={t("track.title")}
        description={t("track.description")}
        cms={{ eyebrow: "track.eyebrow", title: "track.title", description: "track.description" }}
      />
      <section className="py-[var(--spacing-section)]">
        <Container className="max-w-4xl">
          <TrackForm />
          <p className="mt-8 text-sm">
            <a href="/care/" className="font-medium text-medical" data-cms="booking.careLink">{t("booking.careLink")}</a>
          </p>
        </Container>
      </section>
    </>
  );
}
