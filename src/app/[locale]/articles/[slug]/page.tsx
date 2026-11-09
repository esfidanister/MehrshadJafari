import { CalendarDays, Clock3 } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";

import { Reveal } from "@/components/motion/Reveal";
import { buttonVariants } from "@/components/ui/Button";
import { Container } from "@/components/ui/Container";
import { CtaBand } from "@/components/ui/CtaBand";
import { articles, getArticleBySlug } from "@/content/articles";
import { getTreatmentBySlug } from "@/content/treatments";
import { Link } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";
import { formatDate, formatNumber, isAppLocale, pick } from "@/lib/i18n-helpers";
import { shareImageAlt } from "@/lib/page-metadata";
import { buildPageMetadata } from "@/lib/seo";
import { articleTreatmentMap } from "@/lib/site";

type Props = { params: Promise<{ locale: string; slug: string }> };

export const dynamicParams = false;

export function generateStaticParams() {
  return routing.locales.flatMap((locale) =>
    articles.map((article) => ({ locale, slug: article.slug })),
  );
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale, slug } = await params;
  const article = getArticleBySlug(slug);
  if (!isAppLocale(locale) || !article) return {};

  const t = await getTranslations({ locale, namespace: "seo" });

  return buildPageMetadata({
    locale,
    pathname: `/articles/${slug}`,
    title: `${pick(article.title, locale)} | ${t("siteName")}`,
    description: pick(article.excerpt, locale),
    imageAlt: await shareImageAlt(locale),
    openGraph: { type: "article" },
  });
}

export default async function ArticlePage({ params }: Props) {
  const { locale, slug } = await params;
  const article = getArticleBySlug(slug);
  if (!isAppLocale(locale) || !article) notFound();
  setRequestLocale(locale);

  const t = await getTranslations({ locale });
  const treatment = getTreatmentBySlug(articleTreatmentMap[slug] ?? "");
  const more = articles.filter((a) => a.slug !== slug).slice(0, 3);

  return (
    <>
      <article>
        <header className="border-b border-border bg-surface">
          <Container className="max-w-3xl py-16 md:py-24">
            <Reveal className="flex flex-col gap-6">
              <p className="eyebrow" data-cms={`article.${slug}.category`}>{pick(article.category, locale)}</p>
              <h1 className="text-display font-medium tracking-tight text-text" data-cms={`article.${slug}.title`}>
                {pick(article.title, locale)}
              </h1>
              <ul className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-muted">
                <li className="inline-flex items-center gap-2">
                  <CalendarDays className="size-4 text-medical" aria-hidden />
                  <time dateTime={article.publishedAt}>
                    {formatDate(article.publishedAt, locale)}
                  </time>
                </li>
                <li className="inline-flex items-center gap-2">
                  <Clock3 className="size-4 text-medical" aria-hidden />
                  {t("journal.readTime", {
                    minutes: formatNumber(article.readingTimeMinutes, locale),
                  })}
                </li>
              </ul>
            </Reveal>
          </Container>
        </header>

        <Container className="max-w-3xl py-14 md:py-20">
          <div className="prose-clinical">
            <p className="text-xl leading-loose text-text/85" data-cms={`article.${slug}.excerpt`}>
              {pick(article.excerpt, locale)}
            </p>
            {article.body
              ? pick(article.body, locale)
                  .split(/\n\n+/)
                  .filter(Boolean)
                  .map((paragraph) => <p key={paragraph.slice(0, 24)}>{paragraph}</p>)
              : null}

            {treatment ? (
              <>
                <h2>{pick(treatment.title, locale)}</h2>
                <p>{pick(treatment.description, locale)}</p>
                <h2>{t("treatments.faqTitle")}</h2>
                <dl className="flex flex-col gap-6">
                  {treatment.faq.map((item, i) => (
                    <div key={i}>
                      <dt className="font-medium text-text">
                        {pick(item.question, locale)}
                      </dt>
                      <dd className="mt-1">{pick(item.answer, locale)}</dd>
                    </div>
                  ))}
                </dl>
              </>
            ) : null}
          </div>

          <p className="mt-12 rounded-lg border border-accent/40 bg-accent/10 p-5 text-sm leading-relaxed text-text/80">
            {pick(article.educationalNote, locale)}
          </p>

          {treatment ? (
            <div className="mt-8">
              <Link
                href={`/services/${treatment.slug}`}
                className={buttonVariants({ variant: "outline" })}
              >
                {t("treatments.cta")}: {pick(treatment.title, locale)}
              </Link>
            </div>
          ) : null}
        </Container>
      </article>

      <section className="border-t border-border bg-surface py-[var(--spacing-section)]">
        <Container className="max-w-3xl">
          <p className="eyebrow">{t("journal.latest")}</p>
          <ul className="mt-6 divide-y divide-border border-y border-border">
            {more.map((item) => (
              <li key={item.slug}>
                <Link
                  href={`/articles/${item.slug}`}
                  className="block py-5 text-lg text-text transition-colors hover:text-medical"
                >
                  {pick(item.title, locale)}
                </Link>
              </li>
            ))}
          </ul>
        </Container>
      </section>

      <CtaBand locale={locale} />
    </>
  );
}
