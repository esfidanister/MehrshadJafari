import { ChevronDown } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";

import { Reveal } from "@/components/motion/Reveal";
import { buttonVariants } from "@/components/ui/Button";
import { Container } from "@/components/ui/Container";
import { CtaBand } from "@/components/ui/CtaBand";
import { PageHero } from "@/components/ui/PageHero";
import { getTreatmentBySlug, treatments } from "@/content/treatments";
import { Link } from "@/i18n/navigation";
import { isAppLocale, pick } from "@/lib/i18n-helpers";
import { routing } from "@/i18n/routing";
import { shareImageAlt } from "@/lib/page-metadata";
import { buildPageMetadata } from "@/lib/seo";
import { treatmentVisual } from "@/lib/treatment-visuals";
import { cn } from "@/lib/utils";

type Props = { params: Promise<{ locale: string; slug: string }> };

export const dynamicParams = false;

export function generateStaticParams() {
  return routing.locales.flatMap((locale) =>
    treatments.map((item) => ({ locale, slug: item.slug })),
  );
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale, slug } = await params;
  const treatment = getTreatmentBySlug(slug);
  if (!isAppLocale(locale) || !treatment) return {};

  const t = await getTranslations({ locale, namespace: "seo" });

  return buildPageMetadata({
    locale,
    pathname: `/services/${slug}`,
    title: `${pick(treatment.title, locale)} | ${t("siteName")}`,
    description: pick(treatment.shortDescription, locale),
    imageAlt: await shareImageAlt(locale),
  });
}

export default async function ServiceDetailPage({ params }: Props) {
  const { locale, slug } = await params;
  const treatment = getTreatmentBySlug(slug);
  if (!isAppLocale(locale) || !treatment) notFound();
  setRequestLocale(locale);

  const t = await getTranslations({ locale });
  const others = treatments.filter((item) => item.slug !== slug);

  return (
    <>
      <PageHero
        eyebrow={t("treatments.eyebrow")}
        title={pick(treatment.title, locale)}
        description={pick(treatment.shortDescription, locale)}
        cms={{
          eyebrow: "treatments.eyebrow",
          title: `service.${slug}.title`,
          description: `service.${slug}.summary`,
        }}
      >
        <Link
          href="/appointment"
          className={buttonVariants({ size: "lg" })}
        >
          {t("common.bookNow")}
        </Link>
      </PageHero>

      <section className="py-[var(--spacing-section)]">
        <Container className="mb-12">
          <div className="overflow-hidden rounded-[1.5rem] border border-border bg-[#07141a]">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={treatmentVisual(slug).src}
              data-cms-src={treatmentVisual(slug).cms}
              alt=""
              className={`aspect-[16/9] w-full bg-[#07141a] ${treatmentVisual(slug).fit}`}
            />
          </div>
        </Container>
        <Container className="grid gap-16 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
          <div className="flex flex-col gap-14">
            <Reveal>
              <div className="prose-clinical max-w-prose">
                <p className="text-lg leading-loose text-text/80" data-cms={`service.${slug}.description`}>
                  {pick(treatment.description, locale)}
                </p>
              </div>
            </Reveal>

            <Reveal>
              <h2 className="text-title font-medium tracking-tight text-text">
                {t("treatments.faqTitle")}
              </h2>
              <div className="mt-8 divide-y divide-border border-y border-border">
                {treatment.faq.map((item, i) => (
                  <details key={i} className="group py-5">
                    <summary className="flex cursor-pointer list-none items-center justify-between gap-6 text-lg font-medium text-text marker:content-none [&::-webkit-details-marker]:hidden">
                      {pick(item.question, locale)}
                      <ChevronDown
                        className="size-5 shrink-0 text-medical transition-transform duration-300 group-open:rotate-180"
                        aria-hidden
                      />
                    </summary>
                    <p className="mt-4 max-w-prose text-base leading-relaxed text-muted">
                      {pick(item.answer, locale)}
                    </p>
                  </details>
                ))}
              </div>
            </Reveal>

            <p className="rounded-lg border border-accent/40 bg-accent/10 p-5 text-sm leading-relaxed text-text/80">
              {pick(treatment.educationalDisclaimer, locale)}
            </p>
          </div>

          <aside className="lg:sticky lg:top-28 lg:self-start">
            <p className="eyebrow">{t("treatments.title")}</p>
            <ul className="mt-5 divide-y divide-border border-y border-border">
              {others.map((item) => (
                <li key={item.slug}>
                  <Link
                    href={`/services/${item.slug}`}
                    className={cn(
                      "block py-4 text-base text-text transition-colors hover:text-medical",
                    )}
                  >
                    {pick(item.title, locale)}
                  </Link>
                </li>
              ))}
            </ul>
          </aside>
        </Container>
      </section>

      <CtaBand locale={locale} />
    </>
  );
}
