"use client";

import { useLocale, useTranslations } from "next-intl";

import { Container } from "@/components/ui/Container";
import { doctorProfile } from "@/content/doctor";
import { Link } from "@/i18n/navigation";
import { clinicCopy } from "@/lib/clinicCopy";
import type { Locale } from "@/lib/locale";
import { cn } from "@/lib/utils";

import { CopyrightYear } from "./CopyrightYear";
import { APPOINTMENT_HREF, NAV_ITEMS } from "./navItems";

function InstagramGlyph({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden
    >
      <rect x="3.5" y="3.5" width="17" height="17" rx="5" />
      <circle cx="12" cy="12" r="3.9" />
      <circle cx="17.2" cy="6.8" r="0.6" fill="currentColor" stroke="none" />
    </svg>
  );
}

const footerLink = cn(
  "inline-flex py-1.5 text-sm text-white/70",
  "transition-colors duration-300 hover:text-[#9ccdd0]",
  "focus-visible:rounded-sm",
);

export type FooterProps = {
  locale?: Locale;
  className?: string;
};

export function Footer({ locale: localeProp, className }: FooterProps) {
  const t = useTranslations();
  const activeLocale = useLocale() as Locale;
  const locale = localeProp ?? activeLocale;
  const clinic = clinicCopy[locale];

  return (
    <footer className={cn("relative mt-auto bg-[#14242c] text-white", className)}>
      <div className="footer-rule" aria-hidden />
      <Container className="footer-arrive py-8 md:py-20">
        <div className="flex flex-wrap items-end justify-between gap-4 lg:grid lg:grid-cols-12 lg:gap-10">
          <div className="lg:col-span-6">
            <p dir="ltr" className="text-sm tracking-[0.42em] text-[#9ccdd0]">
              <span data-cms="common.brand">{t("common.brand")}</span>
            </p>
            <p className="mt-3 max-w-xl text-2xl leading-snug font-medium tracking-tight md:mt-5 md:text-4xl" data-cms="footer.tagline">
              {t("footer.tagline")}
            </p>
            <p className="mt-4 text-sm text-white/70">
              <span data-cms="doctor.name">{doctorProfile.name[locale]}</span>
              <span aria-hidden className="mx-2 text-white/25">/</span>
              <span data-cms="common.specialty">{t("common.specialty")}</span>
            </p>
          </div>
          <div className="lg:col-span-6 lg:justify-self-end">
            <Link
              href={APPOINTMENT_HREF}
              className="inline-flex rounded-full bg-white px-6 py-3 text-sm font-medium text-[#14242c] transition duration-300 hover:bg-[#9ccdd0]"
            >
              <span data-cms="nav.appointment">{t("nav.appointment")}</span>
            </Link>
          </div>
        </div>

        <div className="mt-6 grid gap-5 border-t border-white/10 pt-5 md:mt-14 md:grid-cols-12 md:gap-10 md:pt-10">
          <div className="md:col-span-5">
            <p className="text-xs tracking-[0.18em] text-[#c6aa86]" data-cms="footer.clinic">{t("footer.clinic")}</p>
            <p className="mt-2 max-w-sm text-sm leading-snug text-white/80" data-cms="clinic.address">{clinic.address}</p>
            <div className="mt-3 flex flex-wrap items-baseline gap-x-4 gap-y-1 text-sm text-white/70">
              <p className="space-y-0.5 text-end tabular-nums" dir="ltr">
                <span className="block" data-cms="clinic.phone">{clinic.phone}</span>
                <span className="block" data-cms="clinic.phoneAlt">{clinic.phoneAlt}</span>
              </p>
              <p>
                <span data-cms="contact.hoursLabel">{t("contact.hoursLabel")}</span>
                {": "}
                <span data-cms="clinic.hours">{clinic.hours}</span>
              </p>
            </div>
            <p className="mt-3 hidden text-sm text-white/70 has-[[data-cms=clinic.email]:not(:empty)]:block">
              <span data-cms="contact.email">{t("contact.email")}</span>
              {": "}
              <a href="#contact" data-cms-mail="clinic.email" className="text-white hover:text-[#9ccdd0]">
                <span dir="ltr" data-cms="clinic.email" />
              </a>
            </p>
          </div>

          <nav aria-label={t("footer.pages")} className="md:col-span-4">
            <p className="text-xs tracking-[0.18em] text-[#c6aa86]" data-cms="footer.pages">{t("footer.pages")}</p>
            <ul className="mt-2 grid grid-cols-2 gap-x-6 md:mt-4">
              {NAV_ITEMS.map((item) => (
                <li key={item.key}>
                  <Link href={item.href} className={footerLink}>
                    <span data-cms={`nav.${item.key}`}>{t(`nav.${item.key}`)}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </nav>

          <div className="hidden md:col-span-3 md:block">
            <p className="text-xs tracking-[0.18em] text-[#c6aa86]" data-cms="footer.follow">{t("footer.follow")}</p>
            <a
              href={clinic.instagramUrl}
              data-cms-href="clinic.instagramUrl"
              target="_blank"
              rel="noopener noreferrer"
              className="mt-4 inline-flex items-center gap-2 rounded-full border border-white/15 px-4 py-2 text-sm text-white/80 transition duration-300 hover:border-[#9ccdd0] hover:text-[#9ccdd0]"
            >
              <InstagramGlyph className="size-4" />
              <span dir="ltr" data-cms="clinic.instagram">{clinic.instagram}</span>
              <span className="sr-only">Instagram</span>
            </a>
          </div>
        </div>

        <div className="mt-6 flex flex-col gap-3 border-t border-white/10 pt-4 text-xs leading-relaxed text-white/55 md:mt-12 md:flex-row md:items-end md:justify-between md:gap-4 md:pt-6">
          <div className="max-w-xl space-y-2">
            <p data-cms="footer.disclaimer">{t("footer.disclaimer")}</p>
            <p>
              <span data-cms="common.medicalCodeLabel">{t("common.medicalCodeLabel")}</span>
              {": "}
              <span dir="ltr" className="tabular-nums text-white/80" data-cms="clinic.medicalCode">{clinic.medicalCode}</span>
            </p>
          </div>
          <div className="space-y-2 md:text-end">
            <a
              href={clinic.instagramUrl}
              data-cms-href="clinic.instagramUrl"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 text-sm text-white/80 md:hidden"
            >
              <InstagramGlyph className="size-4" />
              <span dir="ltr" data-cms="clinic.instagram">{clinic.instagram}</span>
              <span className="sr-only">Instagram</span>
            </a>
            <p>
              &copy; <CopyrightYear locale={locale} />{" "}
              <span dir="ltr" className="tracking-[0.18em]" data-cms="common.brand">{t("common.brand")}</span>
              . <span data-cms="footer.rights">{t("footer.rights")}</span>
            </p>
            <p>
              <a
                href={clinic.creditUrl}
                data-cms-href="footer.creditUrl"
                target="_blank"
                rel="noopener noreferrer"
                className="text-white/70 transition-colors hover:text-[#c6aa86]"
              >
                <span data-cms="footer.credit">{clinic.credit}</span>
              </a>
            </p>
          </div>
        </div>
      </Container>
    </footer>
  );
}
