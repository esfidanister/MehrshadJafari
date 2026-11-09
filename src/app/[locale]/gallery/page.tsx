import type { Metadata } from "next";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";

import { GalleryWall } from "@/components/gallery/GalleryWall";
import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/ui/PageHero";
import { isAppLocale } from "@/lib/i18n-helpers";
import { getPageMetadata } from "@/lib/page-metadata";

type Props = { params: Promise<{ locale: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  if (!isAppLocale(locale)) return {};
  return getPageMetadata(locale, "gallery");
}

export default async function GalleryPage({ params }: Props) {
  const { locale } = await params;
  if (!isAppLocale(locale)) notFound();
  setRequestLocale(locale);
  const t = await getTranslations({ locale, namespace: "galleryPage" });
  const frames = [
    { src: "/visuals/jaw-studio.webp", label: t("jaw"), cms: "galleryPage.jaw", category: "model" as const, wide: true },
    { src: "/visuals/doctor-portrait.webp", label: t("portrait"), cms: "galleryPage.portrait", category: "portrait" as const },
    { src: "/visuals/wisdom.webp", label: t("wisdom"), cms: "galleryPage.wisdom", category: "care" as const },
    { src: "/visuals/implant.webp", label: t("implant"), cms: "galleryPage.implant", category: "care" as const, wide: true },
    { src: "/visuals/orthognathic.webp", label: t("ortho"), cms: "galleryPage.ortho", category: "care" as const },
  ];

  return (
    <>
      <PageHero
        eyebrow={t("eyebrow")}
        title={t("title")}
        description={t("description")}
        cms={{ eyebrow: "galleryPage.eyebrow", title: "galleryPage.title", description: "galleryPage.description" }}
      />
      <section className="py-12 md:py-16">
        <Container>
          <GalleryWall frames={frames} />
          <p className="mt-8 max-w-2xl text-sm leading-relaxed text-muted" data-cms="galleryPage.note">{t("note")}</p>
        </Container>
      </section>
    </>
  );
}
