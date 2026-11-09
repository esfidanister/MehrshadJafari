"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";

export type GalleryCategory = "model" | "portrait" | "care";

export type GalleryFrame = {
  src: string;
  label: string;
  category: GalleryCategory;
  cms: string;
  wide?: boolean;
};

const FILTERS: Array<"all" | GalleryCategory> = ["all", "model", "portrait", "care"];

export function GalleryWall({ frames }: { frames: GalleryFrame[] }) {
  const t = useTranslations("galleryPage");
  const [filter, setFilter] = useState<(typeof FILTERS)[number]>("all");
  const [open, setOpen] = useState<number | null>(null);
  const visible = frames.filter((frame) => filter === "all" || frame.category === filter);
  const current = open === null ? null : visible[open];
  const labels: Record<(typeof FILTERS)[number], string> = {
    all: t("all"),
    model: t("catModel"),
    portrait: t("catPortrait"),
    care: t("catCare"),
  };
  const labelKeys: Record<(typeof FILTERS)[number], string> = {
    all: "galleryPage.all",
    model: "galleryPage.catModel",
    portrait: "galleryPage.catPortrait",
    care: "galleryPage.catCare",
  };

  useEffect(() => {
    if (open === null) return;
    function onKey(event: KeyboardEvent) {
      if (event.key === "Escape") setOpen(null);
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open]);

  return (
    <>
      <div className="mb-6 flex flex-wrap gap-2" role="tablist" aria-label={t("filterLabel")}>
        {FILTERS.map((item) => (
          <button
            key={item}
            type="button"
            role="tab"
            aria-selected={filter === item}
            onClick={() => {
              setFilter(item);
              setOpen(null);
            }}
            className={
              filter === item
                ? "rounded-full bg-medical px-4 py-2 text-sm text-white"
                : "rounded-full border border-border bg-surface px-4 py-2 text-sm text-text transition hover:border-medical"
            }
          >
            <span data-cms={labelKeys[item]}>{labels[item]}</span>
          </button>
        ))}
      </div>
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
        {visible.map((frame, index) => (
          <button
            key={`${frame.category}-${frame.src}`}
            type="button"
            className={
              frame.wide
                ? "group relative col-span-2 aspect-[16/10] overflow-hidden rounded-[1.5rem] bg-[#071014] text-start"
                : "group relative aspect-[4/5] overflow-hidden rounded-[1.5rem] bg-[#071014] text-start"
            }
            onClick={() => setOpen(index)}
          >
            <img
              src={frame.src}
              alt={frame.label}
              className="h-full w-full object-cover transition duration-700 motion-reduce:transition-none motion-safe:group-hover:scale-[1.04]"
            />
            <span className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#071014]/85 to-transparent p-4 text-sm text-white">
              <span className="mb-1 block text-xs tracking-[0.16em] text-[#c6aa86]" data-cms={labelKeys[frame.category]}>{labels[frame.category]}</span>
              <span data-cms={frame.cms}>{frame.label}</span>
            </span>
            <span className="sr-only" data-cms="galleryPage.open">{t("open")}</span>
          </button>
        ))}
      </div>
      {current ? (
        <div className="fixed inset-0 z-50 grid place-items-center bg-[#071014]/80 p-4" role="dialog" aria-modal="true" aria-label={current.label}>
          <button type="button" className="absolute inset-0" aria-label={t("close")} onClick={() => setOpen(null)} />
          <figure className="relative z-10 w-full max-w-4xl">
            <img src={current.src} alt={current.label} className="max-h-[78vh] w-full rounded-[1.5rem] object-contain" />
            <figcaption className="mt-3 flex items-center justify-between gap-4 text-sm text-white">
              <span>
                <span className="me-3 text-[#c6aa86]" data-cms={labelKeys[current.category]}>{labels[current.category]}</span>
                <span data-cms={current.cms}>{current.label}</span>
              </span>
              <button type="button" className="rounded-full border border-white/30 px-4 py-1.5" onClick={() => setOpen(null)}>
                <span data-cms="galleryPage.close">{t("close")}</span>
              </button>
            </figcaption>
          </figure>
        </div>
      ) : null}
    </>
  );
}
