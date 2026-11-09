"use client";

import { ArrowUpLeft, ArrowUpRight } from "lucide-react";
import { useTranslations } from "next-intl";

import { Button } from "@/components/ui/Button";
import { Container } from "@/components/ui/Container";
import { doctorProfile } from "@/content/doctor";
import { Link } from "@/i18n/navigation";
import { clinicCopy } from "@/lib/clinicCopy";
import { isRtl, type Locale } from "@/lib/locale";

import { sectionCopy } from "./sectionCopy";

export type HeroSectionProps = {
  locale: Locale;
};

export function HeroSection({ locale }: HeroSectionProps) {
  const t = useTranslations();
  const copy = sectionCopy[locale].hero;
  const Arrow = isRtl(locale) ? ArrowUpLeft : ArrowUpRight;
  const clinic = clinicCopy[locale];

  return (
    <section
      aria-labelledby="home-title"
      className="relative isolate -mt-[4.5rem] overflow-hidden"
    >
      <div aria-hidden className="pointer-events-none absolute inset-0 -z-10">
        <div className="absolute -top-32 end-[-8%] size-[34rem] rounded-full bg-[radial-gradient(closest-side,rgb(156_205_208/0.34),transparent)]" />
        <div className="absolute bottom-[-12rem] start-[-8%] size-[26rem] rounded-full bg-[radial-gradient(closest-side,rgb(198_170_134/0.16),transparent)]" />
      </div>

      <Container className="grid items-center gap-10 pt-28 pb-16 lg:grid-cols-12 lg:gap-14 lg:pt-32 lg:pb-24">
        <figure className="hero-face lg:col-span-5">
          <img
            src="/visuals/doctor-portrait.webp"
            data-cms-src="visual.doctor"
            alt={doctorProfile.name[locale]}
            width={1179}
            height={1334}
            fetchPriority="high"
            decoding="async"
            className="aspect-[4/5] w-full rounded-[2rem] border border-white bg-[#f3f1eb] object-contain object-bottom shadow-[0_46px_80px_-40px_rgb(24_40_50/0.62)]"
          />
        </figure>

        <figure className="hero-studio lg:col-span-6">
          <img
            src="/visuals/jaw-studio.webp"
            data-cms-src="visual.hero"
            alt=""
            width={1600}
            height={1200}
            decoding="async"
            className="h-[22rem] w-full rounded-[1.75rem] border border-border bg-white object-contain sm:h-[28rem] lg:h-[min(72svh,40rem)]"
          />
        </figure>

        <div className="hero-copy flex flex-col gap-6 lg:col-span-7">
          <p className="eyebrow inline-flex items-center gap-3">
            <span aria-hidden className="h-px w-8 bg-medical/60" />
            <span data-cms="hero.eyebrow">{t("hero.eyebrow")}</span>
          </p>
          <h1 id="home-title" className="text-display leading-[1.08] font-medium tracking-tight text-text" data-cms="doctor.name">
            {doctorProfile.name[locale]}
          </h1>
          <p className="text-lg text-medical" data-cms="hero.headline">{t("hero.headline")}</p>
          <p className="max-w-xl text-base leading-relaxed text-muted md:text-lg" data-cms="hero.subheadline">
            {t("hero.subheadline")}
          </p>
          <div className="flex flex-wrap items-center gap-x-6 gap-y-4">
            <Button asChild size="lg">
              <Link href="/appointment">
                <span data-cms="hero.ctaPrimary">{t("hero.ctaPrimary")}</span>
                <Arrow aria-hidden />
              </Link>
            </Button>
            <Button asChild size="lg" variant="secondary">
              <Link href="/services">
                <span data-cms="hero.ctaSecondary">{t("hero.ctaSecondary")}</span>
              </Link>
            </Button>
            <Link
              href="/about"
              className="group inline-flex items-center gap-1.5 text-sm font-medium text-text transition-colors hover:text-medical"
            >
              <span className="border-b border-text/20 pb-0.5 transition-colors group-hover:border-medical">
                <span data-cms="hero.aboutLink">{copy.aboutLink}</span>
              </span>
            </Link>
          </div>
          <div className="mt-2 flex flex-wrap gap-x-8 gap-y-3 border-t border-border pt-5 text-sm text-muted">
            <p>
              <span data-cms="common.medicalCodeLabel">{t("common.medicalCodeLabel")}</span>
              {": "}
              <span dir="ltr" className="tabular-nums text-text" data-cms="clinic.medicalCode">{clinic.medicalCode}</span>
            </p>
            <p>
              <span data-cms="contact.hoursLabel">{t("contact.hoursLabel")}</span>
              {": "}
              <span data-cms="clinic.hours">{clinic.hours}</span>
            </p>
          </div>
        </div>
      </Container>
    </section>
  );
}
