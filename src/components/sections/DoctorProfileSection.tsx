import { ArrowUpLeft, ArrowUpRight } from "lucide-react";
import { useTranslations } from "next-intl";

import { Reveal } from "@/components/motion/Reveal";
import { Button } from "@/components/ui/Button";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { doctorProfile } from "@/content/doctor";
import { Link } from "@/i18n/navigation";
import { formatYearLabel } from "@/lib/calendar";
import { clinicCopy } from "@/lib/clinicCopy";
import { isRtl, pick, type Locale } from "@/lib/locale";

export type DoctorProfileSectionProps = {
  locale: Locale;
  /** Homepage already shows the portrait, so the repeat is omitted. */
  compact?: boolean;
};

const MAX_EDUCATION = 4;
const MAX_ACHIEVEMENTS = 3;

export function DoctorProfileSection({ locale, compact = false }: DoctorProfileSectionProps) {
  const t = useTranslations();
  const Arrow = isRtl(locale) ? ArrowUpLeft : ArrowUpRight;
  const clinic = clinicCopy[locale];

  const education = doctorProfile.education.slice(0, MAX_EDUCATION);
  /** Unverified claims (e.g. faculty status) are never surfaced on the homepage. */
  const achievements = doctorProfile.achievements
    .filter((item) => item.status !== "unverified")
    .slice(0, MAX_ACHIEVEMENTS);

  return (
    <section
      id="surgeon"
      className="relative scroll-mt-24 py-12 md:py-24 lg:py-32"
    >
      <Container className={compact ? "" : "grid items-center gap-14 lg:grid-cols-12 lg:gap-16"}>
        {compact ? null : (
        <Reveal className="lg:col-span-5">
          <figure className="relative mx-auto aspect-[4/5] w-full max-w-md overflow-hidden rounded-2xl border border-border bg-[#f3f1eb]">
            <img
              src="/visuals/doctor-profile.webp"
              data-cms-src="visual.doctorProfile"
              alt={doctorProfile.name[locale]}
              width={1179}
              height={1334}
              decoding="async"
              className="h-full w-full object-contain object-bottom"
            />
          </figure>
        </Reveal>
        )}

        <div className={compact ? "" : "lg:col-span-7"}>
          {compact ? null : (
          <Reveal>
            <SectionHeading
              eyebrow={t("doctor.eyebrow")}
              title={doctorProfile.name[locale]}
              description={t("doctor.bioLead")}
              cms={{ eyebrow: "doctor.eyebrow", title: "doctor.name", description: "doctor.bioLead" }}
            />
          </Reveal>
          )}

          <div className={compact ? "grid gap-10 md:grid-cols-2" : "mt-12 grid gap-10 md:grid-cols-2"}>
            <div>
              <h3 className="eyebrow mb-5 text-[0.6875rem]" data-cms="doctor.educationTitle">
                {t("doctor.educationTitle")}
              </h3>
              <ul className="space-y-5">
                {education.map((entry, index) => (
                  <li key={`${entry.year ?? ""}-${entry.degree.en}`}>
                    <Reveal
                      delay={index * 0.07}
                      className="relative border-s border-border ps-5"
                    >
                      <span
                        aria-hidden
                        className="absolute -start-[0.2rem] top-2 size-1.5 rounded-full bg-accent"
                      />
                      {entry.year ? (
                        <p className="text-xs tracking-wide text-muted tabular-nums" data-cms={`doctor.edu.${index + 1}.year`}>
                          {formatYearLabel(entry.year, locale)}
                        </p>
                      ) : null}
                      <p className="text-[0.9375rem] leading-snug font-medium text-text" data-cms={`doctor.edu.${index + 1}.degree`}>
                        {pick(entry.degree, locale)}
                      </p>
                      <p className="mt-0.5 text-sm text-muted" data-cms={`doctor.edu.${index + 1}.institution`}>
                        {pick(entry.institution, locale)}
                      </p>
                    </Reveal>
                  </li>
                ))}
              </ul>
            </div>

            <div>
              <h3 className="eyebrow mb-5 text-[0.6875rem]" data-cms="doctor.achievementsTitle">
                {t("doctor.achievementsTitle")}
              </h3>
              <ul className="space-y-4">
                {achievements.map((item, index) => (
                  <li key={item.en}>
                    <Reveal
                      delay={index * 0.07}
                      className="flex gap-3 text-[0.9375rem] leading-snug text-text"
                    >
                      <span
                        aria-hidden
                        className="mt-2 size-1 shrink-0 rounded-full bg-medical"
                      />
                      <span data-cms={`doctor.achievement.${index + 1}`}>{pick(item, locale)}</span>
                    </Reveal>
                  </li>
                ))}
              </ul>
            </div>
          </div>

          <Reveal delay={0.15} className="mt-8 flex flex-wrap items-center gap-3">
            <Button asChild size="lg">
              <Link href="/about">
                {t("common.learnMore")}
                <Arrow aria-hidden />
              </Link>
            </Button>
            <Button asChild size="lg" variant="outline">
              <a href={clinic.instagramUrl} data-cms-href="clinic.instagramUrl" target="_blank" rel="noopener noreferrer">
                <span data-cms="doctor.instagramCta">{t("doctor.instagramCta")}</span>
                <span dir="ltr" className="text-sm opacity-70" data-cms="clinic.instagram">
                  {clinic.instagram}
                </span>
              </a>
            </Button>
          </Reveal>
        </div>
      </Container>
    </section>
  );
}
