import { useTranslations } from "next-intl";

import { Reveal } from "@/components/motion/Reveal";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { formatIndex, type Locale } from "@/lib/locale";

export type IntroductionSectionProps = {
  locale: Locale;
};

const PILLARS = ["pillar1", "pillar2", "pillar3"] as const;

export function IntroductionSection({ locale }: IntroductionSectionProps) {
  const t = useTranslations("intro");

  return (
    <section
      id="introduction"
      className="relative scroll-mt-24 border-y border-border bg-surface py-12 md:py-24 lg:py-32"
    >
      <Container className="grid gap-14 lg:grid-cols-12 lg:gap-10">
        <Reveal className="lg:col-span-5">
          <SectionHeading
            eyebrow={t("eyebrow")}
            title={t("title")}
            description={t("description")}
            cms={{ eyebrow: "intro.eyebrow", title: "intro.title", description: "intro.description" }}
          />
        </Reveal>

        <ol className="lg:col-span-6 lg:col-start-7">
          {PILLARS.map((pillar, index) => (
            <li key={pillar}>
              <Reveal
                delay={index * 0.08}
                className="group grid grid-cols-[3.5rem_1fr] gap-4 border-t border-border py-8 first:border-t-0 first:pt-0 md:grid-cols-[4.5rem_1fr] md:py-10"
              >
                <span
                  aria-hidden
                  className="text-3xl leading-none font-light tracking-tight text-medical tabular-nums md:text-4xl"
                >
                  {formatIndex(index + 1, locale)}
                </span>
                <div className="space-y-3">
                  <h3 className="text-xl font-medium tracking-tight text-text md:text-2xl" data-cms={`intro.${pillar}Title`}>
                    {t(`${pillar}Title`)}
                  </h3>
                  <p className="max-w-md text-base leading-relaxed text-muted" data-cms={`intro.${pillar}Text`}>
                    {t(`${pillar}Text`)}
                  </p>
                </div>
              </Reveal>
            </li>
          ))}
        </ol>
      </Container>
    </section>
  );
}
