"use client";

import { Reveal } from "@/components/motion/Reveal";
import { Container } from "@/components/ui/Container";
import { formatIndex, pick, type Locale } from "@/lib/locale";

const PLATES = [
  {
    src: "/visuals/jaw-studio.webp",
    imageKey: "visual.plate.jaw",
    titleKey: "anatomy.plate.jaw.title",
    bodyKey: "anatomy.plate.jaw.body",
    title: {
      fa: "فک پایین",
      en: "Mandible",
      ar: "الفك السفلي",
      zh: "下颌骨",
    },
    body: {
      fa: "تصویر آموزشی استخوان و دندان‌ها. اسکن بیمار نیست و محل سوراخ چانه را نباید از این تصویر تشخیص داد.",
      en: "Educational picture of bone and teeth. Not a patient scan, and not a way to locate the mental foramen.",
      ar: "صورة تعليمية للعظم والأسنان. ليست فحص مريض، ولا يُحدد منها مكان الثقب الذقني.",
      zh: "这是骨骼和牙齿的教学图。不是患者扫描，不能用来判断颏孔位置。",
    },
    light: true,
  },
  {
    src: "/visuals/wisdom.webp",
    imageKey: "visual.plate.wisdom",
    titleKey: "anatomy.plate.wisdom.title",
    bodyKey: "anatomy.plate.wisdom.body",
    title: {
      fa: "دندان عقل و عصب",
      en: "Wisdom tooth and nerve",
      ar: "ضرس العقل والعصب",
      zh: "智齿与神经",
    },
    body: {
      fa: "موقعیت دندان نهفته نسبت به دندان مجاور و کانال عصب. این تصویر آموزشی است، نه رادیوگرافی بیمار.",
      en: "An impacted tooth against its neighbour and the nerve canal. Educational illustration, not a patient's radiograph.",
      ar: "موضع السن المنطمر بالنسبة للسن المجاور وقناة العصب. صورة تعليمية، وليست صورة مريض.",
      zh: "阻生牙相对邻牙和神经管的位置。这是教学图，不是患者的影像。",
    },
    light: false,
  },
  {
    src: "/visuals/implant.webp",
    imageKey: "visual.plate.implant",
    titleKey: "anatomy.plate.implant.title",
    bodyKey: "anatomy.plate.implant.body",
    title: {
      fa: "ایمپلنت",
      en: "Implant",
      ar: "الزراعة",
      zh: "种植体",
    },
    body: {
      fa: "پیچ داخل استخوان، اباتمنت و روکش. تصمیم کاشت بعد از معاینه استخوان و لثه گرفته می‌شود.",
      en: "Fixture in bone, abutment, and crown. Placement is decided after the bone and gums are examined.",
      ar: "البرغي داخل العظم، والدعامة، والتاج. قرار الزراعة يُتخذ بعد فحص العظم واللثة.",
      zh: "骨内螺钉、基台和牙冠。是否种植，要等检查骨骼和牙龈之后再决定。",
    },
    light: false,
  },
  {
    src: "/visuals/orthognathic.webp",
    imageKey: "visual.plate.ortho",
    titleKey: "anatomy.plate.ortho.title",
    bodyKey: "anatomy.plate.ortho.body",
    title: {
      fa: "جراحی فک",
      en: "Jaw surgery",
      ar: "جراحة الفك",
      zh: "颌骨手术",
    },
    body: {
      fa: "صفحات و پیچ‌ها فقط الگوی آموزشی فیکساسیون‌اند و طرح جراحی یک بیمار مشخص نیستند.",
      en: "Plates and screws are an educational fixation pattern, not one patient's surgical plan.",
      ar: "الصفائح والبراغي نموذج تعليمي للتثبيت، وليست خطة جراحة مريض بعينه.",
      zh: "钢板和螺钉只是固定方式的教学示意，不是某一位患者的手术方案。",
    },
    light: false,
  },
] as const;

export type AnatomyExplorerProps = {
  locale: Locale;
};

/** Large educational plates. The primitive GLB is not shown here. */
const PLATE_COPY = {
  fa: {
    eyebrow: "آناتومی آموزشی",
    title: "چهار تصویر آموزشی؛ هیچ‌کدام رادیوگرافی یا طرح جراحی نیست",
  },
  en: {
    eyebrow: "Educational anatomy",
    title: "Four educational pictures. None is a radiograph or a surgical plan.",
  },
  ar: {
    eyebrow: "تشريح تعليمي",
    title: "أربع صور تعليمية. لا واحدة منها صورة شعاعية أو خطة جراحة.",
  },
  zh: {
    eyebrow: "教学解剖",
    title: "四张教学图。都不是影像，也不是手术方案。",
  },
} as const;

export function AnatomyExplorer({ locale }: AnatomyExplorerProps) {
  const intro = PLATE_COPY[locale];

  return (
    <section className="bg-bg py-16 md:py-24">
      <Container className="flex flex-col gap-16 md:gap-24">
        <Reveal className="max-w-3xl">
          <p className="eyebrow mb-3" data-cms="anatomy.heroEyebrow">{intro.eyebrow}</p>
          <h2 className="text-title font-medium tracking-tight text-text" data-cms="anatomy.heroTitle">
            {intro.title}
          </h2>
          <nav className="mt-6 flex flex-wrap gap-2">
            {PLATES.map((plate, index) => (
              <a
                key={plate.imageKey}
                href={`#plate-${index + 1}`}
                className="rounded-full border border-border bg-surface px-4 py-2 text-sm text-text transition hover:border-medical"
              >
                <span className="me-2 text-accent" dir="ltr">{formatIndex(index + 1, locale)}</span>
                {pick(plate.title, locale)}
              </a>
            ))}
          </nav>
        </Reveal>

        {PLATES.map((plate, index) => (
          <Reveal key={plate.src}>
            <article id={`plate-${index + 1}`} className="grid scroll-mt-28 items-center gap-8 lg:grid-cols-12 lg:gap-12">
              <div
                className={
                  index % 2 === 0
                    ? "overflow-hidden rounded-[1.75rem] border border-border lg:col-span-7"
                    : "overflow-hidden rounded-[1.75rem] border border-border lg:col-span-7 lg:col-start-6 lg:row-start-1"
                }
                style={{ background: plate.light ? "#ffffff" : "#071014" }}
              >
                <img
                  src={plate.src}
                  data-cms-src={plate.imageKey}
                  alt={pick(plate.title, locale)}
                  className="aspect-[16/10] w-full object-contain"
                />
              </div>
              <div className={index % 2 === 0 ? "lg:col-span-5" : "lg:col-span-4 lg:col-start-1 lg:row-start-1"}>
                <p className="text-sm tracking-[0.22em] text-accent">
                  {formatIndex(index + 1, locale)}
                </p>
                <h3 className="mt-3 text-3xl font-medium tracking-tight text-text" data-cms={plate.titleKey}>
                  {pick(plate.title, locale)}
                </h3>
                <p className="mt-4 max-w-md text-base leading-relaxed text-muted" data-cms={plate.bodyKey}>
                  {pick(plate.body, locale)}
                </p>
              </div>
            </article>
          </Reveal>
        ))}
      </Container>
    </section>
  );
}
