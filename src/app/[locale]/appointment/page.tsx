import { ArrowUpLeft, ArrowUpRight, ClipboardList, Phone } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";

import { BookingDesk } from "@/components/booking/BookingDesk";
import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/ui/PageHero";
import { getDir, isAppLocale, pick } from "@/lib/i18n-helpers";
import { getPageMetadata } from "@/lib/page-metadata";
import { clinicCopy } from "@/lib/clinicCopy";
import { bookingLinks } from "@/lib/site";

type Props = { params: Promise<{ locale: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  if (!isAppLocale(locale)) return {};
  return getPageMetadata(locale, "appointment");
}

export default async function AppointmentPage({ params }: Props) {
  const { locale } = await params;
  if (!isAppLocale(locale)) notFound();
  setRequestLocale(locale);

  const t = await getTranslations({ locale });
  const Arrow = getDir(locale) === "rtl" ? ArrowUpLeft : ArrowUpRight;
  const clinic = clinicCopy[locale];

  return (
    <>
      <PageHero
        eyebrow={t("appointment.eyebrow")}
        title={t("appointment.title")}
        description={t("appointment.description")}
        cms={{ eyebrow: "appointment.eyebrow", title: "appointment.title", description: "appointment.description" }}
      />

      <section className="border-b border-border bg-surface">
        <Container className="flex flex-wrap items-center gap-3 py-4">
          {bookingLinks.map((link, i) => (
            <a
              key={link.platform}
              href={link.url}
              data-cms-href={`booking.${i + 1}.url`}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 rounded-full border border-border bg-bg px-4 py-2 text-sm text-text transition duration-300 hover:border-medical hover:text-medical"
            >
              <span data-cms={`booking.${i + 1}.label`}>{pick(link.label, locale)}</span>
              <Arrow className="size-3.5" aria-hidden />
            </a>
          ))}
        </Container>
      </section>

      <section className="py-12 md:py-16">
        <Container>
          <BookingDesk />
        </Container>
      </section>

      <section className="border-t border-border bg-surface py-[var(--spacing-section)]">
        <Container className="grid gap-12 md:grid-cols-2">
          <div>
            <ClipboardList className="size-6 text-medical" aria-hidden />
            <h2 className="mt-5 text-title font-medium tracking-tight text-text" data-cms="appointment.firstVisitTitle">
              {t("appointment.firstVisitTitle")}
            </h2>
            <p className="mt-4 max-w-md text-base leading-relaxed text-muted" data-cms="appointment.firstVisitText">
              {t("appointment.firstVisitText")}
            </p>
          </div>

          <div className="flex flex-col gap-5 rounded-xl border border-border p-8">
            <Phone className="size-6 text-medical" aria-hidden />
            <p className="text-base leading-relaxed text-muted" data-cms="appointment.orCall">
              {t("appointment.orCall")}
            </p>
            <p className="text-3xl font-medium tracking-tight text-text" dir="ltr" data-cms="clinic.phone">
              {clinic.phone}
            </p>
            <p className="text-xs text-muted" data-cms="contact.phoneLabel">{t("contact.phoneLabel")}</p>
            <p className="text-sm text-muted">
              <span data-cms="contact.hoursLabel">{t("contact.hoursLabel")}</span>
              {": "}
              <span data-cms="clinic.hours">{clinic.hours}</span>
            </p>
            <p className="text-xs leading-relaxed text-muted" data-cms="contact.conflictNote">
              {t("contact.conflictNote")}
            </p>
          </div>
        </Container>
      </section>
    </>
  );
}
