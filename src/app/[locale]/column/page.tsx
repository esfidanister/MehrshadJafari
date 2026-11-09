import type { Metadata } from "next";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";

import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/ui/PageHero";
import { Link } from "@/i18n/navigation";
import { isAppLocale } from "@/lib/i18n-helpers";
import { getPageMetadata } from "@/lib/page-metadata";

type Props = { params: Promise<{ locale: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  if (!isAppLocale(locale)) return {};
  return getPageMetadata(locale, "column");
}

export default async function ColumnPage({ params }: Props) {
  const { locale } = await params;
  if (!isAppLocale(locale)) notFound();
  setRequestLocale(locale);
  const t = await getTranslations({ locale, namespace: "columnPage" });
  const steps = [
    [t("step1"), t("step1Text")],
    [t("step2"), t("step2Text")],
    [t("step3"), t("step3Text")],
  ];

  return (
    <>
      <PageHero
        eyebrow={t("eyebrow")}
        title={t("title")}
        description={t("description")}
        cms={{ eyebrow: "columnPage.eyebrow", title: "columnPage.title", description: "columnPage.description" }}
      />
      <section className="py-12 md:py-16">
        <Container className="grid items-start gap-8 lg:grid-cols-12">
          <ol className="overflow-hidden rounded-[1.75rem] border border-border bg-surface lg:col-span-7">
            {steps.map(([title, text], index) => (
              <li key={title} className="grid grid-cols-[3.5rem_1fr] gap-4 border-b border-border p-6 last:border-b-0 md:p-8">
                <p className="text-sm tracking-[0.2em] text-accent" dir="ltr">0{index + 1}</p>
                <div>
                  <h2 className="text-2xl font-medium tracking-tight text-text" data-cms={`columnPage.step${index + 1}`}>{title}</h2>
                  <p className="mt-3 text-sm leading-relaxed text-muted" data-cms={`columnPage.step${index + 1}Text`}>{text}</p>
                </div>
              </li>
            ))}
          </ol>
          <div className="flex flex-col justify-between gap-8 rounded-[1.75rem] bg-[#14242c] p-8 text-white lg:col-span-5 lg:min-h-full">
            <p className="text-lg leading-relaxed text-white/80" data-cms="columnPage.empty">{t("empty")}</p>
            <div className="flex flex-wrap gap-3">
              <Link href="/articles" className="rounded-full bg-[#9ccdd0] px-5 py-2.5 text-sm font-medium text-[#14242c]">
                {t("journal")}
              </Link>
              <Link href="/briefing" className="rounded-full border border-white/25 px-5 py-2.5 text-sm text-white">
                {t("briefing")}
              </Link>
            </div>
          </div>
        </Container>
      </section>
    </>
  );
}
