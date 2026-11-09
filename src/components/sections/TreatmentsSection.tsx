import { ArrowUpLeft, ArrowUpRight } from "lucide-react";
import { useTranslations } from "next-intl";
import Image from "next/image";

import { Reveal } from "@/components/motion/Reveal";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { SnapRail } from "@/components/ui/SnapRail";
import { treatments } from "@/content/treatments";
import { Link } from "@/i18n/navigation";
import { formatIndex, isRtl, pick, type Locale } from "@/lib/locale";
import { treatmentVisual } from "@/lib/treatment-visuals";

export type TreatmentsSectionProps = {
  locale: Locale;
  limit?: number;
};

export function TreatmentsSection({ locale, limit }: TreatmentsSectionProps) {
  const t = useTranslations();
  const Arrow = isRtl(locale) ? ArrowUpLeft : ArrowUpRight;

  return (
    <section
      id="treatments"
      className="relative scroll-mt-24 border-y border-border bg-surface py-12 md:py-24 lg:py-32"
    >
      <Container>
        <div className="flex flex-col gap-8 md:flex-row md:items-end md:justify-between">
          <Reveal className="max-w-2xl">
            <SectionHeading
              eyebrow={t("treatments.eyebrow")}
              title={t("treatments.title")}
              description={t("treatments.description")}
              cms={{ eyebrow: "treatments.eyebrow", title: "treatments.title", description: "treatments.description" }}
            />
          </Reveal>

          <Reveal delay={0.1}>
            <Link
              href="/services"
              className="group inline-flex items-center gap-2 text-sm font-medium text-medical"
            >
              <span className="border-b border-medical/30 pb-0.5 transition-colors group-hover:border-medical" data-cms="common.viewAll">
                {t("common.viewAll")}
              </span>
              <Arrow className="size-4" aria-hidden />
            </Link>
          </Reveal>
        </div>

        <SnapRail label={t("treatments.title")} cols={limit ? "feature" : 2} className="mt-10 md:mt-16">
          {(limit ? treatments.slice(0, limit) : treatments).map((treatment, index) => {
            const wide = index === 0 && Boolean(limit);
            const visual = treatmentVisual(treatment.slug);
            return (
              <Reveal key={treatment.slug} delay={(index % 2) * 0.08} className="h-full">
                <Link
                  href={`/services/${treatment.slug}`}
                  className={
                    wide
                      ? "group grid h-full overflow-hidden rounded-[1.5rem] border border-border bg-surface focus-visible:z-10 md:grid-cols-2"
                      : "group flex h-full flex-col overflow-hidden rounded-[1.5rem] border border-border bg-surface focus-visible:z-10"
                  }
                >
                  <div className={wide ? "relative aspect-[16/10] overflow-hidden bg-[#07141a] md:aspect-auto md:min-h-full" : "relative aspect-[16/10] overflow-hidden bg-[#07141a]"}>
                    <Image
                      src={visual.src}
                      data-cms-src={visual.cms}
                      alt=""
                      fill
                      sizes="(max-width: 768px) 100vw, 50vw"
                      className={`${visual.fit} transition-transform duration-700 ease-[var(--ease-clinical)] group-hover:scale-[1.04]`}
                    />
                    <span className="absolute start-4 top-4 rounded-full bg-white/80 px-3 py-1 text-xs tracking-[0.18em] text-medical backdrop-blur-sm">
                      {formatIndex(index + 1, locale)}
                    </span>
                  </div>
                  <div className="flex items-end justify-between gap-4 p-6 md:p-7">
                    <div className="space-y-2">
                      <h3 className="text-2xl font-medium tracking-tight text-text transition-colors duration-300 group-hover:text-medical" data-cms={`service.${treatment.slug}.title`}>
                        {pick(treatment.title, locale)}
                      </h3>
                      <p className="line-clamp-3 max-w-md text-sm leading-relaxed text-muted md:text-base" data-cms={`service.${treatment.slug}.summary`}>
                        {pick(treatment.shortDescription, locale)}
                      </p>
                    </div>
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-full border border-border text-muted transition-transform duration-500 group-hover:-translate-y-1 group-hover:border-medical group-hover:bg-medical group-hover:text-white">
                      <Arrow className="size-[1.1rem]" aria-hidden />
                      <span className="sr-only" data-cms="treatments.cta">{t("treatments.cta")}</span>
                    </span>
                  </div>
                </Link>
              </Reveal>
            );
          })}
        </SnapRail>
      </Container>
    </section>
  );
}
