"use client";

import { useEffect, useRef, useState, type RefObject } from "react";

import { useAppearance } from "@/components/providers/Appearance";
import { Container } from "@/components/ui/Container";
import type { Locale } from "@/lib/locale";
import { cn } from "@/lib/utils";

const STORY_FRAMES = [
  { src: "/visuals/jaw-studio.webp", cms: "visual.story.overview", fit: "object-contain" },
  { src: "/visuals/wisdom.webp", cms: "visual.story.nerve", fit: "object-cover object-[center_78%]" },
  { src: "/visuals/wisdom.webp", cms: "visual.story.wisdom", fit: "object-cover object-[24%_30%]" },
  { src: "/visuals/implant.webp", cms: "visual.story.implant", fit: "object-contain" },
  { src: "/visuals/orthognathic.webp", cms: "visual.story.plates", fit: "object-contain" },
] as const;

const CHAPTERS = {
  fa: [
    { index: "۰۱", kicker: "نمای کلی", title: "استخوان فک، نقطه شروع دقت", body: "این تصویر آموزشی فرم قوس، دندان‌ها و زاویه فک را نشان می‌دهد. رادیوگرافی بیمار نیست." },
    { index: "۰۲", kicker: "عصب آلوئولار تحتانی", title: "مسیر عصب، قبل از هر برش", body: "نزدیکی ریشه به کانال عصب یکی از دلایل اصلی برنامه‌ریزی دقیق جراحی دندان عقل است." },
    { index: "۰۳", kicker: "ناحیه خلفی", title: "دندان عقل در فضای محدود", body: "زاویه رویش، عمق نهفتگی و رابطه با دندان مجاور برای هر بیمار جداگانه بررسی می‌شود." },
    { index: "۰۴", kicker: "ایمپلنت", title: "پیچ، اباتمنت و روکش", body: "کاشت فقط وقتی مطرح می‌شود که حجم استخوان و شرایط لثه در معاینه مناسب باشد." },
    { index: "۰۵", kicker: "فیکساسیون", title: "صفحات آموزشی جراحی فک", body: "جای صفحات و پیچ‌ها در این تصویر آموزشی است و طرح درمان واقعی هر بیمار را نشان نمی‌دهد." },
  ],
  en: [
    { index: "01", kicker: "Overview", title: "The jaw is where precision starts", body: "This educational picture shows the arch, the teeth, and the gonial angle. It is not a patient's radiograph." },
    { index: "02", kicker: "Inferior alveolar nerve", title: "The nerve path, before any cut", body: "Root proximity to the nerve canal is a core reason wisdom-tooth surgery is planned on imaging." },
    { index: "03", kicker: "Posterior segment", title: "A wisdom tooth in limited space", body: "Angulation, impaction depth, and the neighbouring tooth are assessed individually." },
    { index: "04", kicker: "Implant", title: "Screw, abutment, crown", body: "An implant is considered only when bone volume and the gums are suitable on examination." },
    { index: "05", kicker: "Fixation", title: "Educational jaw-surgery plates", body: "Plate and screw placement in this picture is instructional. It is not a patient's surgical plan." },
  ],
  ar: [
    { index: "٠١", kicker: "نظرة عامة", title: "عظم الفك هو بداية الدقة", body: "هذه الصورة التعليمية تُظهر القوس والأسنان وزاوية الفك. ليست صورة مريض." },
    { index: "٠٢", kicker: "العصب السنخي السفلي", title: "مسار العصب قبل أي شق", body: "قرب الجذر من قناة العصب سبب رئيسي لتخطيط جراحة ضرس العقل على الصورة." },
    { index: "٠٣", kicker: "المنطقة الخلفية", title: "ضرس العقل في مساحة ضيقة", body: "زاوية البزوغ وعمق الانطمار وعلاقة السن المجاور تُقاس لكل شخص على حدة." },
    { index: "٠٤", kicker: "الزراعة", title: "البرغي والدعامة والتاج", body: "الزراعة تُطرح فقط حين يناسب حجم العظم وحال اللثة في الفحص." },
    { index: "٠٥", kicker: "التثبيت", title: "صفائح تعليمية لجراحة الفك", body: "مكان الصفائح والبراغي في هذه الصورة تعليمي، وليس خطة مريض." },
  ],
  zh: [
    { index: "01", kicker: "总览", title: "精确从颌骨开始", body: "这张教学图展示牙弓、牙齿和下颌角。不是患者的影像。" },
    { index: "02", kicker: "下牙槽神经", title: "下刀之前先看神经", body: "牙根靠近神经管，是智齿手术要按影像计划的主要原因。" },
    { index: "03", kicker: "后牙区", title: "空间有限的智齿", body: "萌出角度、阻生深度和邻牙关系，每个人单独评估。" },
    { index: "04", kicker: "种植", title: "螺钉、基台和牙冠", body: "只有检查时骨量和牙龈合适，才考虑种植。" },
    { index: "05", kicker: "固定", title: "颌骨手术的教学板", body: "图里钢板和螺钉的位置是教学用的，不是某位患者的手术方案。" },
  ],
} as const;

type Chapter = { index: string; kicker: string; title: string; body: string };

function storyCopy(locale: Locale) {
  if (locale === "fa") {
    return {
      label: "آموزش تصویری",
      note: "مدل آموزشی است. اسکن بیمار نیست و برای تشخیص یا طرح جراحی استفاده نمی‌شود.",
      aria: "روایت آموزشی آناتومی",
    };
  }
  if (locale === "ar") {
    return {
      label: "شرح تعليمي",
      note: "نموذج تعليمي. ليس فحص مريض، ولا يُستخدم للتشخيص أو خطة الجراحة.",
      aria: "شرح تشريحي تعليمي",
    };
  }
  if (locale === "zh") {
    return {
      label: "图解",
      note: "这是教学模型。不是患者扫描，不能用于诊断或手术方案。",
      aria: "解剖教学",
    };
  }
  return {
    label: "A closer look",
    note: "Educational model only. Not a patient scan, and not for diagnosis or surgical planning.",
    aria: "Educational anatomy sequence",
  };
}

export function CinematicSequence({ locale }: { locale: Locale }) {
  const look = useAppearance();
  const chapters = CHAPTERS[locale];
  const copy = storyCopy(locale);

  if (look.story === "stacked") {
    return <StackedStory chapters={chapters} copy={copy} />;
  }

  return <StickyStory chapters={chapters} copy={copy} motion={look.motion} />;
}

function Frame({
  active,
  motion,
}: {
  active: number;
  motion: "quiet" | "gentle" | "off";
}) {
  return (
    <div className="relative aspect-[16/10] overflow-hidden rounded-[1.6rem] bg-[#f4f1ea] shadow-[0_40px_90px_-48px_rgb(0_0_0/0.8)] ring-1 ring-white/15 lg:aspect-[4/3]">
      {STORY_FRAMES.map((frame, index) => (
        <img
          key={frame.cms}
          src={frame.src}
          data-cms-src={frame.cms}
          alt=""
          width={1200}
          height={900}
          className={cn(
            "absolute inset-0 size-full",
            frame.fit,
            motion === "off" && "transition-none",
            motion === "quiet" && "transition-opacity duration-500",
            motion === "gentle" && "transition-[opacity,transform] duration-700 ease-out",
            index === active ? "scale-100 opacity-100" : motion === "gentle" ? "scale-[1.04] opacity-0" : "opacity-0",
          )}
        />
      ))}
    </div>
  );
}

function MobileStory({
  chapters,
  active,
  trackRef,
}: {
  chapters: readonly Chapter[];
  active: number;
  trackRef: RefObject<HTMLUListElement | null>;
}) {
  return (
    <ul ref={trackRef} className="snap-rail mt-6 lg:hidden">
      {chapters.map((chapter, index) => (
        <li key={chapter.index} data-slide className="snap-rail-item">
          <article
            className={cn(
              "h-full rounded-[1.35rem] border p-5",
              index === active ? "border-[#9CCDD0] bg-white/10" : "border-white/15 bg-white/5",
            )}
          >
            <p className="text-sm text-[#C6AA86]">{chapter.index}</p>
            <p className="mt-2 text-sm text-[#9CCDD0]">{chapter.kicker}</p>
            <h2 className="mt-2 text-2xl font-medium tracking-tight text-balance text-white">{chapter.title}</h2>
            <p className="mt-3 line-clamp-3 text-sm leading-relaxed text-white/75">{chapter.body}</p>
          </article>
        </li>
      ))}
    </ul>
  );
}

function useStoryRail(
  trackRef: RefObject<HTMLUListElement | null>,
  onActive: (index: number) => void,
) {
  const onActiveRef = useRef(onActive);
  onActiveRef.current = onActive;

  useEffect(() => {
    const root = trackRef.current;
    if (!root) return;

    const slides = [...root.querySelectorAll<HTMLElement>("[data-slide]")];
    const observer = new IntersectionObserver(
      (entries) => {
        const best = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
        if (!best) return;
        const index = slides.indexOf(best.target as HTMLElement);
        if (index >= 0) onActiveRef.current(index);
      },
      { root, threshold: [0.6, 0.85] },
    );
    slides.forEach((slide) => observer.observe(slide));
    return () => observer.disconnect();
  }, [trackRef]);
}

function scrollStory(track: HTMLUListElement | null, index: number, motion: "quiet" | "gentle" | "off") {
  const slide = track?.querySelectorAll<HTMLElement>("[data-slide]")[index];
  if (!track || !slide || getComputedStyle(track).display === "none") return false;

  const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
  const sign = document.dir === "rtl" ? -1 : 1;
  track.scrollTo({
    left: sign * index * (slide.offsetWidth + gap),
    behavior: motion === "off" ? "auto" : "smooth",
  });
  return true;
}

function StickyStory({
  chapters,
  copy,
  motion,
}: {
  chapters: readonly Chapter[];
  copy: ReturnType<typeof storyCopy>;
  motion: "quiet" | "gentle" | "off";
}) {
  const [active, setActive] = useState(0);
  const items = useRef<Array<HTMLElement | null>>([]);
  const mobileTrack = useRef<HTMLUListElement>(null);
  const select = useRef(setActive);
  select.current = setActive;
  useStoryRail(mobileTrack, (index) => select.current(index));

  useEffect(() => {
    const nodes = items.current.filter((node): node is HTMLElement => node !== null);
    if (!nodes.length) return;
    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
        if (!visible) return;
        const index = nodes.indexOf(visible.target as HTMLElement);
        if (index >= 0) setActive(index);
      },
      { rootMargin: "-30% 0px -40% 0px", threshold: [0.35, 0.6] },
    );
    nodes.forEach((node) => observer.observe(node));
    return () => observer.disconnect();
  }, [chapters]);

  return (
    <section id="precision-sequence" className="relative bg-[#10262c] text-white" aria-label={copy.aria}>
      <div aria-hidden className="pointer-events-none absolute inset-0 overflow-hidden">
        <div className="absolute -top-32 end-[-10%] size-[36rem] rounded-full bg-[radial-gradient(closest-side,rgb(156_205_208/0.16),transparent)]" />
        <div className="absolute bottom-[-18rem] start-[-12%] size-[28rem] rounded-full bg-[radial-gradient(closest-side,rgb(198_170_134/0.12),transparent)]" />
      </div>
      <Container className="relative grid gap-8 py-12 md:py-24 lg:grid-cols-12 lg:gap-16 lg:py-28">
        <div className="min-w-0 lg:col-span-6 lg:sticky lg:top-28 lg:self-start">
          <p className="text-xs tracking-[0.28em] text-[#9CCDD0] uppercase">{copy.label}</p>
          <div className="mt-5">
            <Frame active={active} motion={motion} />
          </div>
          <div className="mt-5 flex items-center justify-between gap-4">
            <div className="flex items-center" dir="ltr">
              {chapters.map((chapter, index) => (
                <button
                  key={chapter.index}
                  type="button"
                  aria-label={chapter.title}
                  aria-current={index === active ? "step" : undefined}
                  onClick={() => {
                    if (scrollStory(mobileTrack.current, index, motion)) {
                      setActive(index);
                      return;
                    }
                    items.current[index]?.scrollIntoView({ behavior: motion === "off" ? "auto" : "smooth", block: "center" });
                  }}
                  className="group inline-flex h-6 min-w-6 items-center justify-center rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#9CCDD0]"
                >
                  <span
                    aria-hidden
                    className={cn(
                      "h-1.5 rounded-full",
                      motion === "off" ? "transition-none" : "transition-[width,background-color] duration-500",
                      index === active ? "w-8 bg-[#9CCDD0]" : "w-2.5 bg-white/25 group-hover:bg-white/50",
                    )}
                  />
                </button>
              ))}
            </div>
            <p className="text-sm tabular-nums text-white/55" dir="ltr">
              {String(active + 1).padStart(2, "0")} / {String(chapters.length).padStart(2, "0")}
            </p>
          </div>
          <MobileStory chapters={chapters} active={active} trackRef={mobileTrack} />
          <p className="mt-4 max-w-sm text-xs leading-relaxed text-white/55 lg:hidden">{copy.note}</p>
        </div>
        <ol className="hidden lg:col-span-6 lg:block">
          {chapters.map((chapter, index) => (
            <li
              key={chapter.index}
              ref={(node) => {
                items.current[index] = node;
              }}
              className={cn(
                "flex flex-col justify-start border-s-2 py-8 ps-6 transition-colors duration-500 lg:min-h-[46svh] lg:py-12",
                index === active ? "border-[#9CCDD0]" : "border-white/15",
              )}
            >
              <p className="text-sm text-[#C6AA86]">{chapter.index}</p>
              <p className="mt-3 text-sm text-[#9CCDD0]">{chapter.kicker}</p>
              <h2 className="mt-3 max-w-lg text-3xl font-medium tracking-tight text-balance text-white md:text-4xl">{chapter.title}</h2>
              <p className="mt-4 max-w-md text-base leading-relaxed text-white/75">{chapter.body}</p>
            </li>
          ))}
          <li className="pt-2 pb-4">
            <p className="max-w-sm text-xs leading-relaxed text-white/55">{copy.note}</p>
          </li>
        </ol>
      </Container>
    </section>
  );
}

function StackedStory({
  chapters,
  copy,
}: {
  chapters: readonly Chapter[];
  copy: ReturnType<typeof storyCopy>;
}) {
  const [active, setActive] = useState(0);
  const mobileTrack = useRef<HTMLUListElement>(null);
  const select = useRef(setActive);
  select.current = setActive;
  useStoryRail(mobileTrack, (index) => select.current(index));

  return (
    <section id="precision-sequence" className="bg-[#10262c] text-white" aria-label={copy.aria}>
      <Container className="py-12 md:py-28">
        <p className="text-xs tracking-[0.28em] text-[#9CCDD0] uppercase">{copy.label}</p>
        <div className="mt-6 lg:hidden">
          <Frame active={active} motion="quiet" />
          <MobileStory chapters={chapters} active={active} trackRef={mobileTrack} />
          <p className="mt-4 max-w-sm text-xs leading-relaxed text-white/55">{copy.note}</p>
        </div>
        <ol className="mt-12 hidden flex-col gap-16 lg:flex">
          {chapters.map((chapter, index) => (
            <li key={chapter.index} className="grid items-center gap-8 lg:grid-cols-12 lg:gap-14">
              <figure className="lg:col-span-6">
                <img
                  src={STORY_FRAMES[index].src}
                  data-cms-src={STORY_FRAMES[index].cms}
                  alt=""
                  width={1200}
                  height={900}
                  className={cn("aspect-[4/3] w-full rounded-[1.75rem] bg-[#f4f1ea]", STORY_FRAMES[index].fit)}
                />
              </figure>
              <div className="lg:col-span-6">
                <p className="text-sm text-[#C6AA86]">{chapter.index}</p>
                <p className="mt-2 text-sm text-[#9CCDD0]">{chapter.kicker}</p>
                <h2 className="mt-3 text-3xl font-medium tracking-tight text-balance md:text-4xl">{chapter.title}</h2>
                <p className="mt-4 max-w-md text-base leading-relaxed text-white/75">{chapter.body}</p>
              </div>
            </li>
          ))}
        </ol>
        <p className="mt-14 max-w-sm text-xs leading-relaxed text-white/55">{copy.note}</p>
      </Container>
    </section>
  );
}
