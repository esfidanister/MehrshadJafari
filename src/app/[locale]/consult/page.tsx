import type { Metadata } from "next";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";

import { ConsultChat } from "@/components/consult/ConsultChat";
import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/ui/PageHero";
import { isAppLocale } from "@/lib/i18n-helpers";
import { getPageMetadata } from "@/lib/page-metadata";

type Props = { params: Promise<{ locale: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  if (!isAppLocale(locale)) return {};
  return getPageMetadata(locale, "consult");
}

export default async function ConsultPage({ params }: Props) {
  const { locale } = await params;
  if (!isAppLocale(locale)) notFound();
  setRequestLocale(locale);
  const t = await getTranslations({ locale });

  return (
    <>
      <PageHero
        eyebrow={t("consult.eyebrow")}
        title={t("consult.title")}
        description={t("consult.description")}
        cms={{ eyebrow: "consult.eyebrow", title: "consult.title", description: "consult.description" }}
      />
      <section className="py-12 md:py-16">
        <Container className="grid items-start gap-8 lg:grid-cols-12">
          <div className="lg:col-span-8">
            <ConsultChat />
          </div>
          <aside className="space-y-4 lg:sticky lg:top-24 lg:col-span-4">
            <ol className="overflow-hidden rounded-[1.75rem] border border-border bg-surface">
              {[t("consult.note1"), t("consult.note2"), t("consult.note3")].map((note, index) => (
                <li key={note} className="flex gap-4 border-b border-border px-5 py-4 last:border-b-0">
                  <span className="text-sm tracking-[0.18em] text-accent" dir="ltr">0{index + 1}</span>
                  <p className="text-sm leading-relaxed text-muted" data-cms={`consult.note${index + 1}`}>{note}</p>
                </li>
              ))}
            </ol>
            <div className="rounded-[1.75rem] bg-[#14242c] p-6 text-white">
              <h2 className="text-base font-medium" data-cms="consult.emergencyTitle">{t("consult.emergencyTitle")}</h2>
              <p className="mt-3 text-sm leading-relaxed text-white/75" data-cms="consult.emergencyBody">{t("consult.emergencyBody")}</p>
              <a href="tel:115" className="mt-5 inline-flex rounded-full bg-[#9ccdd0] px-5 py-2.5 text-sm font-medium text-[#14242c]">115</a>
            </div>
          </aside>
        </Container>
      </section>
    </>
  );
}
