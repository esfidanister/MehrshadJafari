import { Info } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";

import { AnatomyExplorer } from "@/components/sections/AnatomyExplorer";
import { Container } from "@/components/ui/Container";
import { CtaBand } from "@/components/ui/CtaBand";
import { PageHero } from "@/components/ui/PageHero";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { treatments } from "@/content/treatments";
import { Link } from "@/i18n/navigation";
import { isAppLocale, pick } from "@/lib/i18n-helpers";
import type { Locale } from "@/lib/locale";
import { getPageMetadata } from "@/lib/page-metadata";

type Props = { params: Promise<{ locale: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  if (!isAppLocale(locale)) return {};
  return getPageMetadata(locale, "anatomy");
}

export default async function AnatomyPage({ params }: Props) {
  const { locale } = await params;
  if (!isAppLocale(locale)) notFound();
  setRequestLocale(locale);

  const t = await getTranslations({ locale });

  return (
    <>
      <PageHero
        eyebrow={t("anatomy.eyebrow")}
        title={t("anatomy.title")}
        description={t("anatomy.description")}
        cms={{ eyebrow: "anatomy.eyebrow", title: "anatomy.title", description: "anatomy.description" }}
      />

      <AnatomyExplorer locale={locale as Locale} />

      <section className="pb-[var(--spacing-section)]">
        <Container>
          <div className="flex items-start gap-3 rounded-lg border border-border bg-surface p-5 text-sm leading-relaxed text-muted">
            <Info className="mt-0.5 size-4 shrink-0 text-medical" aria-hidden />
            <p data-cms="anatomy.disclaimer">{t("anatomy.disclaimer")}</p>
          </div>
        </Container>
      </section>

      <section className="border-y border-border bg-surface py-[var(--spacing-section)]">
        <Container>
          <SectionHeading
            eyebrow={t("treatments.eyebrow")}
            title={t("treatments.title")}
            description={t("treatments.description")}
            cms={{ eyebrow: "treatments.eyebrow", title: "treatments.title", description: "treatments.description" }}
          />
          <ul className="mt-10 grid gap-4 sm:grid-cols-2">
            {treatments.map((item) => (
              <li key={item.slug}>
                <Link
                  href={`/services/${item.slug}`}
                  className="block rounded-xl border border-border p-6 transition-colors hover:border-medical/40"
                >
                  <p className="text-lg font-medium text-text" data-cms={`service.${item.slug}.title`}>
                    {pick(item.title, locale)}
                  </p>
                  <p className="mt-2 text-sm leading-relaxed text-muted" data-cms={`service.${item.slug}.summary`}>
                    {pick(item.shortDescription, locale)}
                  </p>
                </Link>
              </li>
            ))}
          </ul>
        </Container>
      </section>

      <CtaBand locale={locale} />
    </>
  );
}
