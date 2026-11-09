import type { Metadata } from "next";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";

import { Reveal } from "@/components/motion/Reveal";
import { ArticleCard } from "@/components/ui/ArticleCard";
import { Container } from "@/components/ui/Container";
import { SnapRail } from "@/components/ui/SnapRail";
import { CtaBand } from "@/components/ui/CtaBand";
import { PageHero } from "@/components/ui/PageHero";
import { articles } from "@/content/articles";
import { formatDate, formatNumber, isAppLocale, pick } from "@/lib/i18n-helpers";
import { getPageMetadata } from "@/lib/page-metadata";

type Props = { params: Promise<{ locale: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  if (!isAppLocale(locale)) return {};
  return getPageMetadata(locale, "journal");
}

export default async function ArticlesPage({ params }: Props) {
  const { locale } = await params;
  if (!isAppLocale(locale)) notFound();
  setRequestLocale(locale);

  const t = await getTranslations({ locale });
  const sorted = [...articles].sort((a, b) =>
    b.publishedAt.localeCompare(a.publishedAt),
  );
  const groups = [
    { id: "recovery" as const, visual: "/visuals/wisdom.webp" },
    { id: "implant" as const, visual: "/visuals/implant.webp" },
    { id: "jaw" as const, visual: "/visuals/orthognathic.webp" },
    { id: "pathology" as const, visual: "/visuals/jaw-studio.webp" },
  ];

  return (
    <>
      <PageHero
        eyebrow={t("journal.eyebrow")}
        title={t("journal.title")}
        description={t("journal.description")}
        cms={{ eyebrow: "journal.eyebrow", title: "journal.title", description: "journal.description" }}
      />

      <section className="py-[var(--spacing-section)]">
        <Container className="flex max-w-5xl flex-col gap-16">
          {groups.map((group) => {
            const items = sorted.filter((article) => article.categoryId === group.id);
            if (!items.length) return null;
            return (
              <div key={group.id}>
                <h2 className="mb-6 text-sm font-medium tracking-[0.16em] text-medical">
                  {pick(items[0].category, locale)}
                </h2>
                <SnapRail label={pick(items[0].category, locale)} cols={2} className="article-list">
                  {items.map((article, i) => (
                    <Reveal key={article.slug} delay={i * 0.05} className="h-full">
                      <ArticleCard
                        href={`/${locale}/articles/${article.slug}/`}
                        title={pick(article.title, locale)}
                        excerpt={pick(article.excerpt, locale)}
                        imageSrc={group.visual}
                        imageAlt={pick(article.title, locale)}
                        category={pick(article.category, locale)}
                        publishedAt={formatDate(article.publishedAt, locale)}
                        dateTime={article.publishedAt}
                        readingTime={t("journal.readTime", {
                          minutes: formatNumber(article.readingTimeMinutes, locale),
                        })}
                        className="h-full rounded-[1.25rem] border border-border bg-surface p-5 max-md:border-b-0 max-md:[&>a]:order-first md:rounded-none md:border-0 md:bg-transparent md:p-0"
                        cms={{
                          title: `article.${article.slug}.title`,
                          excerpt: `article.${article.slug}.excerpt`,
                          category: `article.${article.slug}.category`,
                        }}
                      />
                    </Reveal>
                  ))}
                </SnapRail>
              </div>
            );
          })}
        </Container>
      </section>

      <CtaBand locale={locale} />
    </>
  );
}
