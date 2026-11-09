"use client";

import {
  ArrowUpLeft,
  ArrowUpRight,
  Clock,
  MapPin,
  Phone,
} from "lucide-react";
import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";

import { useAppearance } from "@/components/providers/Appearance";
import { DeskForm } from "@/components/forms/DeskForm";
import { Reveal } from "@/components/motion/Reveal";
import { Button } from "@/components/ui/Button";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { doctorProfile } from "@/content/doctor";
import { Link } from "@/i18n/navigation";
import { clinicCopy } from "@/lib/clinicCopy";
import { isRtl, pick, type Locale } from "@/lib/locale";

import { sectionCopy } from "./sectionCopy";

export type ContactSectionProps = {
  locale: Locale;
  /** Show the section title. The contact page already has a hero, so it leaves this off. */
  heading?: boolean;
  /** Homepage can stay a short band. The contact page always keeps the form. */
  place?: "home" | "page";
};

export function ContactSection({
  locale,
  heading = false,
  place = "page",
}: ContactSectionProps) {
  const t = useTranslations();
  const copy = sectionCopy[locale].contact;
  const rtl = isRtl(locale);
  const Arrow = rtl ? ArrowUpLeft : ArrowUpRight;
  const clinic = clinicCopy[locale];
  const look = useAppearance();
  const [settled, setSettled] = useState(false);
  useEffect(() => setSettled(true), []);
  const full = place !== "home" || (settled && look.contact === "full");

  if (!full) {
    return (
      <section id="contact" className="relative scroll-mt-24 border-t border-border bg-surface py-12 md:py-20">
        <Container>
          {heading ? (
            <SectionHeading
              eyebrow={t("contact.eyebrow")}
              title={t("contact.title")}
              description={t("contact.homeLead")}
              cms={{ eyebrow: "contact.eyebrow", title: "contact.title", description: "contact.homeLead" }}
            />
          ) : null}
          <dl className="mt-6 divide-y divide-border overflow-hidden rounded-2xl border border-border bg-bg md:grid md:grid-cols-3 md:divide-y-0">
            <div className="px-5 py-3 md:py-5">
              <dt className="text-xs text-muted" data-cms="contact.addressLabel">{t("contact.addressLabel")}</dt>
              <dd className="mt-1 text-sm leading-relaxed text-text" data-cms="clinic.address">{clinic.address}</dd>
            </div>
            <div className="px-5 py-3 md:border-s md:border-border md:py-5">
              <dt className="text-xs text-muted" data-cms="contact.hoursLabel">{t("contact.hoursLabel")}</dt>
              <dd className="mt-1 text-sm leading-relaxed text-text" data-cms="clinic.hours">{clinic.hours}</dd>
            </div>
            <div className="px-5 py-3 md:border-s md:border-border md:py-5">
              <dt className="text-xs text-muted" data-cms="contact.phoneLabel">{t("contact.phoneLabel")}</dt>
              <dd className="mt-1 space-y-1 text-end text-sm tabular-nums text-text" dir="ltr">
                <span className="block" data-cms="clinic.phone">{clinic.phone}</span>
                <span className="block" data-cms="clinic.phoneAlt">{clinic.phoneAlt}</span>
              </dd>
            </div>
          </dl>
          <div className="mt-6 flex flex-wrap gap-3">
            <Button asChild>
              <Link href="/appointment">
                <span data-cms="common.bookNow">{t("common.bookNow")}</span>
              </Link>
            </Button>
            <Button asChild variant="outline">
              <Link href="/contact">
                <span data-cms="contact.formTitle">{t("contact.formTitle")}</span>
              </Link>
            </Button>
          </div>
        </Container>
      </section>
    );
  }

  return (
    <section
      id="contact"
      className="relative scroll-mt-24 border-t border-border bg-surface py-16 md:py-24"
    >
      <Container className="grid gap-14 lg:grid-cols-12 lg:gap-12">
        {/* ——— Information ——— */}
        <div className="space-y-10 lg:col-span-5">
          {heading ? (
            <Reveal>
              <SectionHeading
                eyebrow={t("contact.eyebrow")}
                title={t("contact.title")}
                description={t("contact.description")}
                cms={{ eyebrow: "contact.eyebrow", title: "contact.title", description: "contact.description" }}
              />
            </Reveal>
          ) : null}

          <Reveal delay={0.08} className="space-y-5 rounded-xl border border-border bg-bg p-6 md:p-7">
            <dl className="space-y-5 text-[0.9375rem]">
              <div className="flex gap-3">
                <MapPin className="mt-0.5 size-[1.15rem] shrink-0 text-medical" strokeWidth={1.5} aria-hidden />
                <div>
                  <dt className="text-xs text-muted" data-cms="contact.addressLabel">{t("contact.addressLabel")}</dt>
                  <dd className="mt-1 leading-relaxed text-text" data-cms="clinic.address">{clinic.address}</dd>
                </div>
              </div>
              <div className="flex gap-3">
                <Clock className="mt-0.5 size-[1.15rem] shrink-0 text-medical" strokeWidth={1.5} aria-hidden />
                <div>
                  <dt className="text-xs text-muted" data-cms="contact.hoursLabel">{t("contact.hoursLabel")}</dt>
                  <dd className="mt-1 leading-relaxed text-text" data-cms="clinic.hours">{clinic.hours}</dd>
                </div>
              </div>
              <div className="flex gap-3">
                <Phone className="mt-0.5 size-[1.15rem] shrink-0 text-medical" strokeWidth={1.5} aria-hidden />
                <div>
                  <dt className="text-xs text-muted" data-cms="contact.phoneLabel">{t("contact.phoneLabel")}</dt>
                  <dd className="mt-1 space-y-1 text-start tabular-nums text-text">
                    <span dir="ltr" className="block" data-cms="clinic.phone">{clinic.phone}</span>
                    <span dir="ltr" className="block" data-cms="clinic.phoneAlt">{clinic.phoneAlt}</span>
                  </dd>
                </div>
              </div>
              <div className="hidden gap-3 has-[[data-cms=clinic.email]:not(:empty)]:flex">
                <div className="ms-[1.9rem]">
                  <dt className="text-xs text-muted" data-cms="contact.email">{t("contact.email")}</dt>
                  <dd className="mt-1">
                    <a href="#contact" data-cms-mail="clinic.email" className="text-text hover:text-medical">
                      <span dir="ltr" data-cms="clinic.email" />
                    </a>
                  </dd>
                </div>
              </div>
            </dl>

            <p className="border-t border-border pt-4 text-[0.8125rem] leading-relaxed text-muted">
              <span data-cms="doctor.locationConfirmNote">{t("doctor.locationConfirmNote")}</span>{" "}
              <span data-cms="contact.conflictNote">{t("contact.conflictNote")}</span>
            </p>
          </Reveal>

          <Reveal delay={0.12} className="space-y-4">
            <h3 className="eyebrow text-[0.6875rem]">{copy.bookingTitle}</h3>
            <ul className="flex flex-col gap-2.5">
              {doctorProfile.bookingUrls.map((link, index) => (
                <li key={link.platform}>
                  <a
                    href={link.url}
                    data-cms-href={`booking.${index + 1}.url`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="group flex items-center justify-between gap-4 rounded-lg border border-border bg-bg px-4 py-3.5 transition-[border-color,background-color] duration-300 hover:border-medical/40 hover:bg-surface"
                  >
                    <span className="min-w-0">
                      <span className="block text-[0.9375rem] font-medium text-text group-hover:text-medical" data-cms={`booking.${index + 1}.label`}>
                        {pick(link.label, locale)}
                      </span>
                      {link.status !== "verified" && link.status !== "reported" ? (
                        <span className="mt-0.5 block text-xs text-muted">
                          {copy.unverifiedLink}
                        </span>
                      ) : null}
                    </span>
                    <Arrow className="size-4 shrink-0 text-muted transition-colors group-hover:text-medical" aria-hidden />
                    <span className="sr-only">(opens in a new tab)</span>
                  </a>
                </li>
              ))}
            </ul>
            <p className="text-[0.8125rem] leading-relaxed text-muted">
              {t("appointment.unverifiedPlatformNote")}
            </p>
            <Button asChild variant="outline">
              <Link href="/appointment">
                <span data-cms="common.bookNow">{t("common.bookNow")}</span>
              </Link>
            </Button>
          </Reveal>
        </div>

        <Reveal delay={0.1} className="lg:col-span-7">
          <div className="rounded-2xl border border-border bg-bg p-6 sm:p-8 md:p-10">
            <DeskForm
              locale={locale}
              kind="contact"
              title={t("contact.formTitle")}
              titleCms="contact.formTitle"
              intro={t("contact.formNote")}
              introCms="contact.formNote"
            />
            <p className="mt-4 text-[0.8125rem] text-muted" data-cms="common.educationalOnly">
              {t("common.educationalOnly")}
            </p>
          </div>
        </Reveal>
      </Container>
    </section>
  );
}
