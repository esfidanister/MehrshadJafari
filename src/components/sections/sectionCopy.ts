import type { Locale } from "@/lib/locale";

/**
 * Small UI strings that have no entry in `messages/*.json` yet (carousel
 * controls, form feedback, topic chips). Move into the message files when
 * convenient — keys are intentionally flat and self-explanatory.
 */
export type SectionCopy = {
  reviews: {
    previous: string;
    next: string;
    carouselLabel: string;
    slideOf: (current: number, total: number) => string;
    originalPersian: string;
    outOfFive: string;
    topics: Record<
      "implant" | "wisdom-tooth" | "general" | "cyst" | "extraction",
      string
    >;
  };
  hero: {
    scroll: string;
    aboutLink: string;
  };
  anatomy: {
    legendTitle: string;
    explore: string;
  };
  contact: {
    reportedPhone: string;
    reportedHours: string;
    sources: (count: number) => string;
    bookingTitle: string;
    unverifiedLink: string;
    formIntro: string;
    namePlaceholder: string;
    phonePlaceholder: string;
    messagePlaceholder: string;
    preparedTitle: string;
    preparedBody: string;
    mailtoTitle: string;
    mailtoBody: string;
    copy: string;
    copied: string;
    editAgain: string;
    mailSubject: (name: string) => string;
    mailBodyLabels: { name: string; phone: string; message: string };
    instagram: string;
  };
};

const fa: SectionCopy = {
  reviews: {
    previous: "نظر قبلی",
    next: "نظر بعدی",
    carouselLabel: "نظرات بیماران",
    slideOf: (current, total) => `نظر ${current} از ${total}`,
    originalPersian: "متن اصلی نظر",
    outOfFive: "از ۵",
    topics: {
      implant: "ایمپلنت",
      "wisdom-tooth": "دندان عقل",
      general: "تجربه کلی",
      cyst: "کیست دهانی",
      extraction: "جراحی دندان",
    },
  },
  hero: {
    scroll: "ادامه",
    aboutLink: "آشنایی با دکتر",
  },
  anatomy: {
    legendTitle: "در این راهنما",
    explore: "ورود به صفحه آناتومی",
  },
  contact: {
    reportedPhone: "تلفن",
    reportedHours: "ساعت کاری",
    sources: (count) => `${count} منبع`,
    bookingTitle: "نوبت‌دهی آنلاین",
    unverifiedLink: "لینک سامانه",
    formIntro:
      "پیام را مطب می‌خواند و برای هماهنگی با شما تماس می‌گیرد. این فرم جای معاینه نیست.",
    namePlaceholder: "مثلاً علی رضایی",
    phonePlaceholder: "۰۹۱۲۰۰۰۰۰۰۰",
    messagePlaceholder: "موضوع یا پرسش خود را بنویسید…",
    preparedTitle: "پیام شما آماده شد",
    preparedBody:
      "این وب‌سایت پیام را ذخیره یا ارسال نمی‌کند. متن را کپی کنید و از پلتفرم نوبت‌دهی بفرستید.",
    mailtoTitle: "برنامه ایمیل شما باز شد",
    mailtoBody:
      "اگر برنامه ایمیل باز نشد، متن زیر را کپی کنید و از راه دیگری ارسال کنید.",
    copy: "کپی متن پیام",
    copied: "کپی شد",
    editAgain: "ویرایش پیام",
    mailSubject: (name) => `پیام از وب‌سایت JAFARI — ${name}`,
    mailBodyLabels: { name: "نام", phone: "تلفن", message: "پیام" },
    instagram: "پیام در اینستاگرام",
  },
};

const en: SectionCopy = {
  reviews: {
    previous: "Previous review",
    next: "Next review",
    carouselLabel: "Patient reviews",
    slideOf: (current, total) => `Review ${current} of ${total}`,
    originalPersian: "Original Persian text",
    outOfFive: "out of 5",
    topics: {
      implant: "Implant",
      "wisdom-tooth": "Wisdom tooth",
      general: "General",
      cyst: "Oral cyst",
      extraction: "Extraction",
    },
  },
  hero: {
    scroll: "Scroll",
    aboutLink: "Meet the surgeon",
  },
  anatomy: {
    legendTitle: "In this guide",
    explore: "Open the anatomy guide",
  },
  contact: {
    reportedPhone: "Phone",
    reportedHours: "Hours",
    sources: (count) => `${count} source${count === 1 ? "" : "s"}`,
    bookingTitle: "Online booking",
    unverifiedLink: "Platform link",
    formIntro:
      "The clinic reads this message and will get back to you. This form is not an examination.",
    namePlaceholder: "e.g. Ali Rezaei",
    phonePlaceholder: "0912 000 0000",
    messagePlaceholder: "Tell us what you would like to ask…",
    preparedTitle: "Your message is ready",
    preparedBody:
      "This website does not store or send messages. Copy the text and send it through a booking platform.",
    mailtoTitle: "Your email app should be opening",
    mailtoBody:
      "If nothing opened, copy the text below and send it another way.",
    copy: "Copy message",
    copied: "Copied",
    editAgain: "Edit message",
    mailSubject: (name) => `Website enquiry from ${name} — JAFARI`,
    mailBodyLabels: { name: "Name", phone: "Phone", message: "Message" },
    instagram: "Message on Instagram",
  },
};

const ar: SectionCopy = {
  reviews: {
    previous: "الرأي السابق",
    next: "الرأي التالي",
    carouselLabel: "آراء المرضى",
    slideOf: (current, total) => `الرأي ${current} من ${total}`,
    originalPersian: "النص الفارسي الأصلي",
    outOfFive: "من ٥",
    topics: {
      implant: "زراعة",
      "wisdom-tooth": "ضرس العقل",
      general: "تجربة عامة",
      cyst: "كيس فموي",
      extraction: "قلع سن",
    },
  },
  hero: { scroll: "متابعة", aboutLink: "التعرّف على الطبيب" },
  anatomy: { legendTitle: "في هذا الدليل", explore: "افتح دليل التشريح" },
  contact: {
    reportedPhone: "الهاتف",
    reportedHours: "ساعات العمل",
    sources: (count) => `${count} مصادر`,
    bookingTitle: "حجز عبر الإنترنت",
    unverifiedLink: "رابط المنصة",
    formIntro: "تقرأ العيادة الرسالة وتتصل بكم للتنسيق. هذا النموذج ليس فحصاً.",
    namePlaceholder: "مثال: علي رضائي",
    phonePlaceholder: "0912 000 0000",
    messagePlaceholder: "اكتبوا موضوعكم أو سؤالكم…",
    preparedTitle: "الرسالة جاهزة",
    preparedBody: "هذا الموقع لا يخزّن الرسالة. انسخوا النص وأرسلوه عبر منصة الحجز.",
    mailtoTitle: "يُفترض أن يفتح تطبيق البريد",
    mailtoBody: "إذا لم يفتح شيء، انسخوا النص وأرسلوه بطريقة أخرى.",
    copy: "نسخ الرسالة",
    copied: "تم النسخ",
    editAgain: "تعديل الرسالة",
    mailSubject: (name) => `رسالة من موقع JAFARI — ${name}`,
    mailBodyLabels: { name: "الاسم", phone: "الهاتف", message: "الرسالة" },
    instagram: "مراسلة على إنستغرام",
  },
};

const zh: SectionCopy = {
  reviews: {
    previous: "上一条",
    next: "下一条",
    carouselLabel: "患者评价",
    slideOf: (current, total) => `第 ${current} 条，共 ${total} 条`,
    originalPersian: "波斯语原文",
    outOfFive: "满分 5 分",
    topics: {
      implant: "种植",
      "wisdom-tooth": "智齿",
      general: "总体体验",
      cyst: "口腔囊肿",
      extraction: "拔牙",
    },
  },
  hero: { scroll: "继续", aboutLink: "认识医生" },
  anatomy: { legendTitle: "本指南包含", explore: "打开解剖指南" },
  contact: {
    reportedPhone: "电话",
    reportedHours: "门诊时间",
    sources: (count) => `${count} 个来源`,
    bookingTitle: "在线预约",
    unverifiedLink: "平台链接",
    formIntro: "诊室会阅读这条消息并与您联系。此表不是检查。",
    namePlaceholder: "例如：阿里·礼萨伊",
    phonePlaceholder: "0912 000 0000",
    messagePlaceholder: "请写下您想问的事…",
    preparedTitle: "消息已备好",
    preparedBody: "本网站不保存这条消息。请复制文字，通过预约平台发送。",
    mailtoTitle: "正在打开邮件应用",
    mailtoBody: "如果没有打开，请复制下面的文字，用其他方式发送。",
    copy: "复制消息",
    copied: "已复制",
    editAgain: "继续编辑",
    mailSubject: (name) => `JAFARI 网站留言 — ${name}`,
    mailBodyLabels: { name: "姓名", phone: "电话", message: "留言" },
    instagram: "在 Instagram 上留言",
  },
};

export const sectionCopy: Record<Locale, SectionCopy> = { fa, en, ar, zh };
