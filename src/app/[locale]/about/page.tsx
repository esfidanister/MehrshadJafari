import type { Metadata } from "next";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";

import { Container } from "@/components/ui/Container";
import { CtaBand } from "@/components/ui/CtaBand";
import { InstagramIcon } from "@/components/ui/InstagramIcon";
import { doctorProfile } from "@/content/doctor";
import { scientificEvents } from "@/content/events";
import { formatWhen, formatYearLabel } from "@/lib/calendar";
import { clinicCopy } from "@/lib/clinicCopy";
import { isAppLocale, pick } from "@/lib/i18n-helpers";
import { getPageMetadata } from "@/lib/page-metadata";
import { cn } from "@/lib/utils";

type Props = { params: Promise<{ locale: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  if (!isAppLocale(locale)) return {};
  return getPageMetadata(locale, "doctor");
}

function bookRole(role: string, locale: string) {
  const labels = {
    translation: { fa: "ترجمه", en: "Translation", ar: "ترجمة", zh: "翻译" },
    collaboration: { fa: "کار مشترک", en: "Shared work", ar: "عمل مشترك", zh: "合著" },
    authored: { fa: "نوشته", en: "Written", ar: "تأليف", zh: "撰写" },
  } as const;
  const row = role === "translation" || role === "collaboration" ? labels[role] : labels.authored;
  return pick(row, locale);
}

export default async function AboutPage({ params }: Props) {
  const { locale } = await params;
  if (!isAppLocale(locale)) notFound();
  setRequestLocale(locale);

  const t = await getTranslations({ locale });
  const p = doctorProfile;
  const clinic = clinicCopy[locale];
  const achievements = p.achievements.filter((item) => item.status !== "unverified");

  return (
    <>
      <section className="border-b border-border bg-surface">
        <Container className="grid items-center gap-10 py-14 md:py-20 lg:grid-cols-12 lg:gap-16 lg:py-24">
          <figure className="lg:order-2 lg:col-span-5">
            <img
              src="/visuals/doctor-portrait.webp"
              data-cms-src="visual.doctor"
              alt={pick(p.name, locale)}
              width={1179}
              height={1334}
              decoding="async"
              className="aspect-[4/5] w-full rounded-[1.75rem] border border-border bg-[#f3f1eb] object-contain object-bottom"
            />
          </figure>
          <div className="lg:order-1 lg:col-span-7">
            <p className="eyebrow" data-cms="doctor.eyebrow">{t("doctor.eyebrow")}</p>
            <h1 className="mt-4 text-display font-medium tracking-tight text-text" data-cms="doctor.name">
              {pick(p.name, locale)}
            </h1>
            <p className="mt-3 text-lg text-medical" data-cms="common.specialty">{t("common.specialty")}</p>
            <p className="mt-6 max-w-xl text-base leading-relaxed text-muted" data-cms="doctor.bioLead">
              {t("doctor.bioLead")}
            </p>
            <p className="mt-5 max-w-2xl text-lg leading-relaxed text-text/85" data-cms="doctor.summary">
              {pick(p.summary, locale)}
            </p>
            <p className="mt-8 text-sm text-muted">
              <span data-cms="common.medicalCodeLabel">{t("common.medicalCodeLabel")}</span>
              {": "}
              <span dir="ltr" className="tabular-nums text-text" data-cms="clinic.medicalCode">{clinic.medicalCode}</span>
            </p>
          </div>
        </Container>
      </section>

      <section className="border-b border-border py-[var(--spacing-section)]">
        <Container>
          <h2 className="text-title font-medium tracking-tight text-text" data-cms="doctor.approachTitle">{t("doctor.approachTitle")}</h2>
          <p className="mt-4 max-w-2xl text-base leading-relaxed text-muted" data-cms="doctor.approachLead">{t("doctor.approachLead")}</p>
          <ul className="mt-10 grid gap-4 md:grid-cols-2">
            {([1, 2, 3, 4] as const).map((n) => (
              <li key={n} className="rounded-2xl border border-border bg-bg px-6 py-7">
                <h3 className="text-lg font-medium text-text" data-cms={`doctor.approach${n}Title`}>{t(`doctor.approach${n}Title`)}</h3>
                <p className="mt-2 text-sm leading-relaxed text-muted" data-cms={`doctor.approach${n}Text`}>{t(`doctor.approach${n}Text`)}</p>
              </li>
            ))}
          </ul>
        </Container>
      </section>

      <section className="py-[var(--spacing-section)]">
        <Container>
          <h2 className="text-title font-medium tracking-tight text-text">
            <span data-cms="doctor.educationTitle">{t("doctor.educationTitle")}</span>
          </h2>
          <ol className="mt-10 border-s border-border ps-6 md:ps-10">
            {p.education.map((item, i) => (
              <li key={i} className="relative grid gap-1 border-b border-border/70 py-7 last:border-b-0 md:grid-cols-[7rem_1fr] md:gap-8">
                <span aria-hidden className="absolute -start-[1.65rem] top-9 size-2.5 rounded-full border-2 border-medical bg-bg md:-start-[2.65rem]" />
                <p className="text-sm tabular-nums text-accent" data-cms={`doctor.edu.${i + 1}.year`}>{formatYearLabel(item.year ?? "", locale)}</p>
                <div>
                  <h3 className="text-lg font-medium text-text" data-cms={`doctor.edu.${i + 1}.degree`}>{pick(item.degree, locale)}</h3>
                  <p className="mt-1 text-base text-muted" data-cms={`doctor.edu.${i + 1}.institution`}>{pick(item.institution, locale)}</p>
                </div>
              </li>
            ))}
          </ol>
        </Container>
      </section>

      <section className="border-y border-border bg-surface py-[var(--spacing-section)]">
        <Container>
          <h2 className="text-title font-medium tracking-tight text-text">
            <span data-cms="doctor.achievementsTitle">{t("doctor.achievementsTitle")}</span>
          </h2>
          <ul className="mt-10 grid gap-4 md:grid-cols-2">
            {achievements.map((item, i) => (
              <li key={i} className="rounded-2xl border border-border bg-bg px-6 py-7 text-base leading-relaxed text-text" data-cms={`doctor.achievement.${i + 1}`}>
                {pick(item, locale)}
              </li>
            ))}
          </ul>
        </Container>
      </section>

      <section className="py-[var(--spacing-section)]">
        <Container>
          <h2 className="text-title font-medium tracking-tight text-text">
            <span data-cms="doctor.eventsTitle">{t("doctor.eventsTitle")}</span>
          </h2>
          <ul className="mt-10 grid gap-6 md:grid-cols-2">
            {scientificEvents.map((event) => (
              <li key={event.id} className="overflow-hidden rounded-2xl border border-border bg-surface">
                <img
                  src="/visuals/media-frame.svg"
                  data-cms-src={`doctor.event.${event.id}.image`}
                  alt=""
                  width={160}
                  height={200}
                  className="aspect-[16/9] w-full bg-[#f3f1eb] object-cover"
                />
                <div className="space-y-2 p-6">
                  <p className="text-xs tabular-nums text-accent" dir="ltr">
                    {event.endDate ? `${formatWhen(event.startDate, locale)} – ${formatWhen(event.endDate, locale)}` : formatWhen(event.startDate, locale)}
                  </p>
                  <h3 className="text-lg font-medium leading-snug text-text" data-cms={`doctor.event.${event.id}.title`}>
                    {pick(event.title, locale)}
                  </h3>
                  <p className="text-sm leading-relaxed text-muted" data-cms={`doctor.event.${event.id}.description`}>{pick(event.description, locale)}</p>
                  <p className="text-sm leading-relaxed text-muted" data-cms={`doctor.event.${event.id}.role`}>{pick(event.role, locale)}</p>
                  <p className="text-sm text-muted">
                    <span data-cms={`doctor.event.${event.id}.venue`}>{pick(event.venue, locale)}</span>
                    {pick(event.city, locale) !== pick(event.venue, locale) ? (
                      <>
                        {" · "}
                        <span data-cms={`doctor.event.${event.id}.city`}>{pick(event.city, locale)}</span>
                      </>
                    ) : (
                      <span aria-hidden data-cms={`doctor.event.${event.id}.city`}>{pick(event.city, locale)}</span>
                    )}
                  </p>
                  <div className="flex flex-wrap gap-4 pt-2 text-sm">
                    <a
                      href={event.sourceUrl}
                      data-cms-href={`doctor.event.${event.id}.url`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-medical underline-offset-4 hover:underline"
                    >
                      <span data-cms="doctor.eventDetail">{t("doctor.eventDetail")}</span>
                    </a>
                    <a
                      hidden
                      href="#media"
                      data-cms-href={`doctor.event.${event.id}.video`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-medical underline-offset-4 hover:underline"
                    >
                      <span data-cms="doctor.watchFilm">{t("doctor.watchFilm")}</span>
                    </a>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        </Container>
      </section>

      <section className="border-t border-border bg-surface py-[var(--spacing-section)]">
        <Container>
          <h2 className="text-title font-medium tracking-tight text-text">
            <span data-cms="doctor.booksTitle">{t("doctor.booksTitle")}</span>
          </h2>
          <p className="mt-3 max-w-2xl text-sm leading-relaxed text-muted" data-cms="doctor.booksNote">
            {t("doctor.booksCollaborativeNote")}
          </p>
          <ul className="mt-10 grid gap-4">
            {p.books.map((book, i) => {
              const people = book.coAuthors?.join(locale === "fa" ? "، " : ", ") ?? "";
              return (
                <li key={i} className="grid grid-cols-[5.5rem_1fr] items-center gap-4 rounded-2xl border border-border bg-bg p-4 sm:grid-cols-[7rem_1fr] sm:gap-6 sm:p-5">
                  <img
                    src="/visuals/media-frame.svg"
                    data-cms-src={`doctor.book.${i + 1}.image`}
                    alt={pick(book.title, locale)}
                    width={160}
                    height={200}
                    className="aspect-[3/4] w-full rounded-xl bg-[#f3f1eb] object-cover"
                  />
                  <div>
                    <h3 className="text-base font-medium leading-snug text-text sm:text-lg" data-cms={`doctor.book.${i + 1}.title`}>
                      {pick(book.title, locale)}
                    </h3>
                    <p className="mt-1.5 text-xs text-medical" data-cms={`doctor.book.${i + 1}.role`}>{bookRole(book.role, locale)}</p>
                    {people ? (
                      <p className="mt-1.5 text-sm text-muted" data-cms={`doctor.book.${i + 1}.people`}>{people}</p>
                    ) : (
                      <p className="mt-1.5 text-sm text-muted" data-cms={`doctor.book.${i + 1}.people`} />
                    )}
                    <a
                      hidden
                      href="#media"
                      data-cms-href={`doctor.book.${i + 1}.video`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="mt-3 inline-block text-sm text-medical underline-offset-4 hover:underline"
                    >
                      <span data-cms="doctor.watchFilm">{t("doctor.watchFilm")}</span>
                    </a>
                  </div>
                </li>
              );
            })}
          </ul>
        </Container>
      </section>

      <section className="py-[var(--spacing-section)]">
        <Container className="grid gap-6 md:grid-cols-2">
          <div className="rounded-2xl border border-border p-8">
            <h2 className="text-xl font-medium text-text" data-cms="doctor.podcastTitle">{t("doctor.podcastTitle")}</h2>
            <p className="mt-3 text-sm leading-relaxed text-muted" data-cms="doctor.podcastEpisode">{pick(p.podcast.title, locale)}</p>
            <a
              href={p.podcast.url}
              data-cms-href="doctor.podcastUrl"
              target="_blank"
              rel="noopener noreferrer"
              className={cn("mt-6 inline-flex text-sm text-medical underline-offset-4 hover:underline")}
            >
              <span data-cms="doctor.podcastCta">{t("doctor.podcastCta")}</span>
            </a>
          </div>
          <div className="rounded-2xl border border-border p-8">
            <InstagramIcon className="size-6 text-medical" />
            <h2 className="mt-4 text-xl font-medium text-text" data-cms="doctor.instagramCta">{t("doctor.instagramCta")}</h2>
            <p className="mt-2 text-sm text-muted" dir="ltr" data-cms="clinic.instagram">{clinic.instagram}</p>
            <a
              href={clinic.instagramUrl}
              data-cms-href="clinic.instagramUrl"
              target="_blank"
              rel="noopener noreferrer"
              className="mt-6 inline-flex text-sm text-medical underline-offset-4 hover:underline"
            >
              <span data-cms="doctor.instagramCta">{t("doctor.instagramCta")}</span>
            </a>
          </div>
        </Container>
      </section>

      <CtaBand locale={locale} />
    </>
  );
}
