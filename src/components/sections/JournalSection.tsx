import { CalendarDays, Clock3, ArrowUpLeft, ArrowUpRight } from "lucide-react";
import { useTranslations } from "next-intl";

import { Reveal } from "@/components/motion/Reveal";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { SnapRail } from "@/components/ui/SnapRail";
import { articles } from "@/content/articles";
import { Link } from "@/i18n/navigation";
import {
  formatDate,
  formatNumber,
  isRtl,
  pick,
  type Locale,
} from "@/lib/locale";

export type JournalSectionProps = {
  locale: Locale;
};

const FEATURED_COUNT = 3;

export function JournalSection({ locale }: JournalSectionProps) {
  const t = useTranslations();
  const Arrow = isRtl(locale) ? ArrowUpLeft : ArrowUpRight;

  const latest = [...articles]
    .sort((a, b) => b.publishedAt.localeCompare(a.publishedAt))
    .slice(0, FEATURED_COUNT);

  return (
    <section
      id="journal"
      className="relative scroll-mt-24 py-12 md:py-24 lg:py-32"
    >
      <Container>
        <div className="flex flex-col gap-8 md:flex-row md:items-end md:justify-between">
          <Reveal className="max-w-2xl">
            <SectionHeading
              eyebrow={t("journal.eyebrow")}
              title={t("journal.title")}
              description={t("journal.description")}
              cms={{ eyebrow: "journal.eyebrow", title: "journal.title", description: "journal.description" }}
            />
          </Reveal>

          <Reveal delay={0.1}>
            <Link
              href="/articles"
              className="group inline-flex items-center gap-2 text-sm font-medium text-medical"
            >
              <span className="border-b border-medical/30 pb-0.5 transition-colors group-hover:border-medical">
                {t("common.viewAll")}
              </span>
              <Arrow className="size-4" aria-hidden />
            </Link>
          </Reveal>
        </div>

        <SnapRail label={t("journal.title")} cols={3} className="article-cards mt-10 md:mt-16">
          {latest.map((article, index) => (
              <Reveal key={article.slug} delay={index * 0.08} className="flex h-full w-full">
                <article className="group relative flex h-full w-full flex-col gap-5 rounded-[1.35rem] border border-border bg-surface p-6 transition-[border-color,box-shadow,transform] duration-500 ease-[var(--ease-clinical)] hover:-translate-y-1 hover:border-medical/30 hover:shadow-[0_24px_48px_-32px_rgb(24_40_50/0.35)] md:p-8">
                  <p className="eyebrow text-[0.6875rem] text-accent" data-cms={`article.${article.slug}.category`}>
                    {pick(article.category, locale)}
                  </p>

                  <h3 className="text-xl leading-snug font-medium tracking-tight text-text transition-colors duration-300 group-hover:text-medical md:text-[1.375rem]">
                    <Link
                      href={`/articles/${article.slug}`}
                      className="after:absolute after:inset-0 after:content-[''] focus-visible:outline-none"
                    >
                      <span data-cms={`article.${article.slug}.title`}>{pick(article.title, locale)}</span>
                    </Link>
                  </h3>

                  <p className="line-clamp-3 text-[0.9375rem] leading-relaxed text-muted" data-cms={`article.${article.slug}.excerpt`}>
                    {pick(article.excerpt, locale)}
                  </p>

                  <ul className="mt-auto flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-border pt-5 text-xs text-muted">
                    <li className="inline-flex items-center gap-1.5">
                      <CalendarDays
                        className="size-3.5 text-medical"
                        aria-hidden
                      />
                      <time dateTime={article.publishedAt}>
                        {formatDate(article.publishedAt, locale)}
                      </time>
                    </li>
                    <li className="inline-flex items-center gap-1.5">
                      <Clock3 className="size-3.5 text-medical" aria-hidden />
                      {t("journal.readTime", {
                        minutes: formatNumber(article.readingTimeMinutes, locale),
                      })}
                    </li>
                  </ul>
                </article>
              </Reveal>
          ))}
        </SnapRail>

        <SnapRail label={t("journal.title")} cols={2} className="mt-6 md:mt-10">
          <a
            href={`/${locale}/column/`}
            className="rounded-xl border border-border bg-surface p-6 transition-colors hover:border-medical/40"
          >
            <p className="text-lg font-medium text-text" data-cms="journal.columnTitle">{t("journal.columnTitle")}</p>
            <p className="mt-2 text-sm leading-relaxed text-muted" data-cms="journal.columnText">{t("journal.columnText")}</p>
          </a>
          <a
            href={`/${locale}/briefing/`}
            className="rounded-xl border border-border bg-surface p-6 transition-colors hover:border-medical/40"
          >
            <p className="text-lg font-medium text-text" data-cms="journal.briefingTitle">{t("journal.briefingTitle")}</p>
            <p className="mt-2 text-sm leading-relaxed text-muted" data-cms="journal.briefingText">{t("journal.briefingText")}</p>
          </a>
        </SnapRail>
      </Container>
    </section>
  );
}
