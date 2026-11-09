import { Star } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";

import { Reveal } from "@/components/motion/Reveal";
import { Container } from "@/components/ui/Container";
import { CtaBand } from "@/components/ui/CtaBand";
import { PageHero } from "@/components/ui/PageHero";
import { ReviewCard } from "@/components/ui/ReviewCard";
import { SnapRail } from "@/components/ui/SnapRail";
import { patientReviews, reviewAggregate } from "@/content/reviews";
import { formatWhen } from "@/lib/calendar";
import { formatNumber, isAppLocale } from "@/lib/i18n-helpers";
import { getPageMetadata } from "@/lib/page-metadata";

type Props = { params: Promise<{ locale: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  if (!isAppLocale(locale)) return {};
  return getPageMetadata(locale, "reviews");
}

export default async function ReviewsPage({ params }: Props) {
  const { locale } = await params;
  if (!isAppLocale(locale)) notFound();
  setRequestLocale(locale);

  const t = await getTranslations({ locale });

  return (
    <>
      <PageHero
        eyebrow={t("reviews.eyebrow")}
        title={t("reviews.title")}
        description={t("reviews.description")}
        cms={{ eyebrow: "reviews.eyebrow", title: "reviews.title", description: "reviews.description" }}
      >
        <dl className="flex flex-wrap gap-x-12 gap-y-5">
          <div>
            <dt className="text-xs text-muted">{t("reviews.aggregateLabel")}</dt>
            <dd className="mt-1 flex items-center gap-2 text-4xl font-medium text-text">
              <Star className="size-6 fill-accent text-accent" aria-hidden />
              {formatNumber(reviewAggregate.averageRating, locale)}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-muted">{t("reviews.countLabel")}</dt>
            <dd className="mt-1 text-4xl font-medium text-text">
              {formatNumber(reviewAggregate.totalCount, locale)}
            </dd>
          </div>
        </dl>
      </PageHero>

      <section className="py-[var(--spacing-section)]">
        <Container className="flex flex-col gap-14">
          {(["implant", "wisdom-tooth", "cyst", "general"] as const).map((topic) => {
            const items = patientReviews.filter((review) => review.topic === topic);
            if (!items.length) return null;
            const topicKey = {
              implant: "reviews.topicImplant",
              "wisdom-tooth": "reviews.topicWisdom",
              cyst: "reviews.topicCyst",
              general: "reviews.topicGeneral",
            }[topic];
            return (
              <div key={topic}>
                <h2 className="mb-5 text-sm font-medium tracking-[0.16em] text-medical" data-cms={topicKey}>{t(topicKey)}</h2>
                <SnapRail label={t(topicKey)} cols={2}>
                  {items.map((review, i) => (
                    <Reveal key={review.id} delay={(i % 2) * 0.06} className="h-full">
                      <div lang="fa" dir="rtl" className="h-full">
                        <ReviewCard
                          quote={review.text}
                          name="nobat.ir"
                          role={formatWhen(review.date, locale)}
                          className="h-full rounded-[1.5rem]"
                        />
                      </div>
                    </Reveal>
                  ))}
                </SnapRail>
              </div>
            );
          })}

          <div className="mt-10 flex flex-col gap-2 border-t border-border pt-6 text-sm leading-relaxed text-muted">
            <p data-cms="reviews.importedDataNote">{t("reviews.importedDataNote")}</p>
            <p data-cms="reviews.patientExperienceDisclaimer">{t("reviews.patientExperienceDisclaimer")}</p>
          </div>
        </Container>
      </section>

      <CtaBand locale={locale} />
    </>
  );
}
