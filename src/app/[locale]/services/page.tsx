import { ArrowUpLeft, ArrowUpRight } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";

import { Reveal } from "@/components/motion/Reveal";
import { Container } from "@/components/ui/Container";
import { SnapRail } from "@/components/ui/SnapRail";
import { CtaBand } from "@/components/ui/CtaBand";
import { PageHero } from "@/components/ui/PageHero";
import { treatments } from "@/content/treatments";
import { Link } from "@/i18n/navigation";
import { getDir, isAppLocale, pick } from "@/lib/i18n-helpers";
import { getPageMetadata } from "@/lib/page-metadata";
import { treatmentVisual } from "@/lib/treatment-visuals";

type Props = { params: Promise<{ locale: string }> };

const SERVICE_GROUPS: { id: "groupSurgery" | "groupImplant" | "groupJaw" | "groupPathology"; slugs: string[] }[] = [
  { id: "groupSurgery", slugs: ["wisdom-tooth-surgery", "facial-fractures"] },
  { id: "groupImplant", slugs: ["dental-implants", "bone-graft-sinus-lift", "all-on-4"] },
  { id: "groupJaw", slugs: ["orthognathic-surgery", "cosmetic-jaw", "tmj"] },
  { id: "groupPathology", slugs: ["oral-pathology"] },
];

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  if (!isAppLocale(locale)) return {};
  return getPageMetadata(locale, "treatments");
}

export default async function ServicesPage({ params }: Props) {
  const { locale } = await params;
  if (!isAppLocale(locale)) notFound();
  setRequestLocale(locale);

  const t = await getTranslations({ locale });
  const Arrow = getDir(locale) === "rtl" ? ArrowUpLeft : ArrowUpRight;

  return (
    <>
      <PageHero
        eyebrow={t("treatments.eyebrow")}
        title={t("treatments.title")}
        description={t("treatments.description")}
        cms={{ eyebrow: "treatments.eyebrow", title: "treatments.title", description: "treatments.description" }}
      />

      <section className="py-12 md:py-16">
        <Container className="flex flex-col gap-14">
          {SERVICE_GROUPS.map((group) => {
            const items = group.slugs
              .map((slug) => treatments.find((item) => item.slug === slug))
              .filter((item) => item !== undefined);
            return (
              <div key={group.id}>
                <h2 className="mb-5 text-sm font-medium tracking-[0.16em] text-medical" data-cms={`treatments.${group.id}`}>
                  {t(`treatments.${group.id}`)}
                </h2>
                <SnapRail label={t(`treatments.${group.id}`)} cols={items.length > 1 ? "feature" : 2}>
                  {items.map((item, i) => {
                      const visual = treatmentVisual(item.slug);
                      return (
                      <Reveal key={item.slug} className="h-full">
                        <Link
                          href={`/services/${item.slug}`}
                          className={
                            i === 0 && items.length > 1
                              ? "group grid h-full overflow-hidden rounded-[1.75rem] border border-border bg-surface shadow-[0_24px_50px_-36px_rgb(24_40_50/0.45)] md:grid-cols-2"
                              : "group flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-border bg-surface shadow-[0_24px_50px_-36px_rgb(24_40_50/0.35)]"
                          }
                        >
                          <div className={i === 0 && items.length > 1 ? "relative aspect-[16/10] overflow-hidden bg-[#071014] md:aspect-auto md:min-h-72" : "relative aspect-[16/10] overflow-hidden bg-[#071014]"}>
                            <img
                              src={visual.src}
                              alt=""
                              data-cms-src={visual.cms}
                              className={`absolute inset-0 size-full ${visual.fit} transition duration-700 motion-reduce:transition-none motion-safe:group-hover:scale-[1.04]`}
                            />
                          </div>
                          <div className="flex flex-1 flex-col justify-end gap-4 p-6 md:p-8">
                            <h3 className="text-2xl font-medium tracking-tight text-text transition-colors group-hover:text-medical md:text-3xl" data-cms={`service.${item.slug}.title`}>
                              {pick(item.title, locale)}
                            </h3>
                            <p className="text-base leading-relaxed text-muted" data-cms={`service.${item.slug}.summary`}>
                              {pick(item.shortDescription, locale)}
                            </p>
                            <span className="inline-flex items-center gap-2 text-sm font-medium text-medical">
                              {t("treatments.cta")}
                              <Arrow className="size-4 transition-transform duration-300 group-hover:-translate-y-0.5" aria-hidden />
                            </span>
                          </div>
                        </Link>
                      </Reveal>
                      );
                  })}
                </SnapRail>
              </div>
            );
          })}
          <p className="text-sm text-muted">{t("common.educationalOnly")}</p>
        </Container>
      </section>

      <CtaBand locale={locale} />
    </>
  );
}
