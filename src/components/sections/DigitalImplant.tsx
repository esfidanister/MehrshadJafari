"use client";

import { useCallback, useRef, useState } from "react";
import { useTranslations } from "next-intl";

import { Container } from "@/components/ui/Container";

const MIN = 0.12;
const MAX = 0.88;

/**
 * Educational implant viewer. Both sides are the same teaching picture.
 * The marker only changes the grade of that picture. It is not a CBCT scan.
 */
export function DigitalImplant() {
  const t = useTranslations("implant");
  const stage = useRef<HTMLDivElement>(null);
  const [split, setSplit] = useState(0.56);
  const [dragging, setDragging] = useState(false);

  const move = useCallback((clientX: number) => {
    const node = stage.current;
    if (!node) return;
    const rect = node.getBoundingClientRect();
    if (rect.width <= 0) return;
    const next = (clientX - rect.left) / rect.width;
    setSplit(Math.min(MAX, Math.max(MIN, next)));
  }, []);

  return (
    <section id="implant" className="scroll-mt-24 bg-[#10181c] py-12 text-white md:py-20" aria-labelledby="implant-title">
      <Container>
        <div className="grid items-end gap-6 lg:grid-cols-12">
          <div className="lg:col-span-7">
            <p className="text-sm tracking-[0.18em] text-[#9ccdd0]" data-cms="implant.eyebrow">{t("eyebrow")}</p>
            <h2 id="implant-title" className="mt-3 max-w-xl text-3xl font-medium tracking-tight md:text-5xl" data-cms="implant.title">
              {t("title")}
            </h2>
          </div>
          <p className="max-w-md text-base leading-relaxed text-white/70 lg:col-span-5" data-cms="implant.description">
            {t("description")}
          </p>
        </div>

        <div className="mt-10 rounded-[1.75rem] border border-white/10 bg-[#071014] p-2 shadow-[0_40px_90px_-48px_rgb(0_0_0/0.8)] sm:p-3">
          <div
            ref={stage}
            dir="ltr"
            className="relative aspect-[16/10] cursor-ew-resize overflow-hidden rounded-[1.35rem] bg-[#071014] select-none"
            onPointerDown={(event) => {
              event.currentTarget.setPointerCapture(event.pointerId);
              setDragging(true);
              move(event.clientX);
            }}
            onPointerMove={(event) => {
              if (!dragging && event.buttons !== 1) return;
              move(event.clientX);
            }}
            onPointerUp={() => setDragging(false)}
            onPointerCancel={() => setDragging(false)}
          >
            <img
              src="/visuals/implant.webp"
              data-cms-src="visual.implant"
              alt={t("imageAlt")}
              width={1600}
              height={1000}
              draggable={false}
              className="absolute inset-0 size-full object-cover"
            />
            <img
              src="/visuals/implant.webp"
              data-cms-src="visual.implant"
              alt=""
              width={1600}
              height={1000}
              draggable={false}
              className="pointer-events-none absolute inset-0 size-full object-cover contrast-125 grayscale saturate-0"
              style={{ clipPath: `inset(0 0 0 ${split * 100}%)` }}
            />
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(120%_80%_at_50%_40%,transparent_40%,rgb(7_16_20/0.45))]" />

            <div className="pointer-events-none absolute inset-x-4 top-4 flex items-start justify-between gap-3 text-[0.68rem] tracking-[0.16em] text-white">
              <p className="rounded-full border border-white/20 bg-black/45 px-3 py-1.5 backdrop-blur-md" data-cms="implant.scan">
                {t("scan")}
              </p>
              <p className="rounded-full border border-white/20 bg-black/45 px-3 py-1.5 backdrop-blur-md" data-cms="implant.badge">
                {t("badge")}
              </p>
            </div>

            <p
              className="pointer-events-none absolute bottom-16 left-4 text-xs tracking-[0.14em] text-white/90 transition-opacity"
              style={{ opacity: split > 0.22 ? 1 : 0 }}
              data-cms="implant.sideTeach"
            >
              {t("sideTeach")}
            </p>
            <p
              className="pointer-events-none absolute right-4 bottom-16 text-xs tracking-[0.14em] text-white/90 transition-opacity"
              style={{ opacity: split < 0.78 ? 1 : 0 }}
              data-cms="implant.sidePlain"
            >
              {t("sidePlain")}
            </p>

            <div className="absolute inset-y-8 z-10" style={{ left: `${split * 100}%` }}>
              <div className="absolute inset-y-0 left-1/2 w-[2px] -translate-x-1/2 bg-white shadow-[0_0_22px_rgb(156_205_208)]" />
              <button
                type="button"
                role="slider"
                aria-valuemin={Math.round(MIN * 100)}
                aria-valuemax={Math.round(MAX * 100)}
                aria-valuenow={Math.round(split * 100)}
                aria-label={t("hint")}
                data-dragging={dragging ? "true" : "false"}
                className="implant-handle absolute top-1/2 left-1/2 grid size-14 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border border-white/70 bg-white text-[#14242c] shadow-[0_12px_30px_-12px_rgb(0_0_0/0.8)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#9ccdd0]"
                onKeyDown={(event) => {
                  if (event.key === "ArrowLeft") setSplit((value) => Math.max(MIN, value - 0.04));
                  if (event.key === "ArrowRight") setSplit((value) => Math.min(MAX, value + 0.04));
                }}
              >
                <span className="flex items-center gap-1" aria-hidden>
                  <span className="block size-1.5 rotate-45 border-b border-l border-[#14242c]" />
                  <span className="h-4 w-px bg-[#397f91]" />
                  <span className="block size-1.5 rotate-45 border-t border-r border-[#14242c]" />
                </span>
              </button>
            </div>

            <div className="pointer-events-none absolute inset-x-4 bottom-4 flex items-end justify-between gap-3 text-[0.68rem] tracking-[0.14em] text-white">
              <p className="rounded-full border border-white/20 bg-black/45 px-3 py-1.5 backdrop-blur-md" data-cms="implant.guided">
                {t("guided")}
              </p>
              <p className="rounded-full border border-white/20 bg-black/45 px-3 py-1.5 backdrop-blur-md">
                <span data-cms="implant.precision">{t("precision")}</span>
                <span className="ms-3 text-[#c6aa86]" data-cms="implant.mark">{t("mark")}</span>
              </p>
            </div>
          </div>
        </div>

        <div className="mt-5 flex flex-col gap-2 text-sm leading-relaxed text-white/55 sm:flex-row sm:items-start sm:justify-between">
          <p data-cms="implant.hint">{t("hint")}</p>
          <p className="max-w-md sm:text-end" data-cms="implant.disclaimer">{t("disclaimer")}</p>
        </div>
      </Container>
    </section>
  );
}
