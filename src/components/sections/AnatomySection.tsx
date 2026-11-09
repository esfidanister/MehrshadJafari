"use client";

import { ArrowUpLeft, ArrowUpRight } from "lucide-react";
import { useTranslations } from "next-intl";

import { Reveal } from "@/components/motion/Reveal";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Link } from "@/i18n/navigation";
import { isRtl, type Locale } from "@/lib/locale";

import { sectionCopy } from "./sectionCopy";

export type AnatomySectionProps = {
  locale: Locale;
};

const LEGEND = [
  { key: "mandible", dot: "bg-text" },
  { key: "wisdomTooth", dot: "bg-accent" },
  { key: "nerve", dot: "bg-medical" },
] as const;

export function AnatomySection({ locale }: AnatomySectionProps) {
  const t = useTranslations("anatomy");
  const copy = sectionCopy[locale].anatomy;
  const Arrow = isRtl(locale) ? ArrowUpLeft : ArrowUpRight;

  const labels = {
    mandible: t("labels.mandible"),
    wisdomTooth: t("labels.wisdomTooth"),
    nerve: t("labels.nerve"),
  };

  return (
    <section
      id="anatomy"
      className="relative scroll-mt-24 py-12 md:py-24 lg:py-32"
    >
      <Container className="grid items-center gap-8 lg:grid-cols-12 lg:gap-10">
        <Reveal className="space-y-6 lg:col-span-5 lg:space-y-10">
          <SectionHeading
            eyebrow={t("eyebrow")}
            title={t("title")}
            description={t("description")}
            cms={{ eyebrow: "anatomy.eyebrow", title: "anatomy.title", description: "anatomy.description" }}
          />

          <div>
            <p className="eyebrow mb-4 text-[0.6875rem] text-muted">
              {copy.legendTitle}
            </p>
            <ul className="flex flex-col gap-3">
              {LEGEND.map(({ key, dot }) => (
                <li
                  key={key}
                  className="flex items-center gap-3 text-[0.9375rem] text-text"
                >
                  <span
                    aria-hidden
                    className={`size-2 rounded-full ring-4 ring-white ${dot}`}
                  />
                  {labels[key]}
                </li>
              ))}
            </ul>
          </div>

          <div className="space-y-5">
            <p className="max-w-md border-s-2 border-accent/60 ps-4 text-sm leading-relaxed text-muted">
              {t("disclaimer")}
            </p>
            <Link
              href="/anatomy"
              className="group inline-flex items-center gap-2 text-sm font-medium text-medical"
            >
              <span className="border-b border-medical/30 pb-0.5 transition-colors group-hover:border-medical">
                {copy.explore}
              </span>
              <Arrow
                className="size-4 transition-transform duration-300 group-hover:-translate-y-0.5 group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5"
                aria-hidden
              />
            </Link>
          </div>
        </Reveal>

        <Reveal
          delay={0.12}
          variants={{
            hidden: { opacity: 0, y: 32, scale: 0.985 },
            visible: { opacity: 1, y: 0, scale: 1 },
          }}
          className="lg:col-span-7"
        >
          <div className="relative isolate aspect-[16/10] overflow-hidden rounded-2xl border border-border bg-[#071014] lg:aspect-auto lg:h-[32rem]">
            <img
              src="/visuals/wisdom.webp"
              data-cms-src="visual.home.anatomy"
              alt=""
              className="h-full w-full object-cover"
            />
          </div>
        </Reveal>
      </Container>
    </section>
  );
}
