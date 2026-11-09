"use client";

import { useLocale } from "next-intl";

import { buttonVariants } from "@/components/ui/Button";
import { Container } from "@/components/ui/Container";
import { Link } from "@/i18n/navigation";

const copy = {
  fa: {
    title: "صفحه پیدا نشد",
    text: "نشانی وارد شده وجود ندارد یا جابه‌جا شده است.",
    cta: "بازگشت به صفحه اصلی",
  },
  en: {
    title: "Page not found",
    text: "The address you entered does not exist or has moved.",
    cta: "Back to home",
  },
  ar: {
    title: "الصفحة غير موجودة",
    text: "العنوان الذي أدخلته غير موجود أو نُقل.",
    cta: "العودة إلى الرئيسية",
  },
  zh: {
    title: "找不到页面",
    text: "您输入的地址不存在，或已经移动。",
    cta: "返回首页",
  },
} as const;

export default function LocaleNotFound() {
  const locale = useLocale();
  const c = locale === "fa" || locale === "ar" || locale === "zh" ? copy[locale] : copy.en;

  return (
    <Container className="flex min-h-[60vh] flex-col items-start justify-center gap-6 py-24">
      <p className="eyebrow">404</p>
      <h1 className="text-display font-medium tracking-tight text-text">
        {c.title}
      </h1>
      <p className="max-w-md text-lg text-muted">{c.text}</p>
      <Link href="/" className={buttonVariants({ size: "lg" })}>
        {c.cta}
      </Link>
    </Container>
  );
}
