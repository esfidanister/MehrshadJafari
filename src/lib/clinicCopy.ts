import type { Locale } from "@/lib/locale";

/** Visible clinic facts. The desk can replace every one of these. */
export const clinicCopy: Record<
  Locale,
  {
    address: string;
    phone: string;
    phoneAlt: string;
    hours: string;
    instagram: string;
    instagramUrl: string;
    medicalCode: string;
    credit: string;
    creditUrl: string;
  }
> = {
  fa: {
    address:
      "تهران، پاسداران، بوستان ششم، جنب مدرسه خدیجه کبری، پلاک ۱۳۵، طبقه ۲، واحد ۸",
    phone: "021-22579316",
    phoneAlt: "021-22579315",
    hours: "شنبه تا پنجشنبه، با هماهنگی قبلی",
    instagram: "@dr.s.m.jafari",
    instagramUrl: "https://www.instagram.com/dr.s.m.jafari/",
    medicalCode: "141130",
    credit: "طراح سایت صادق فلاحی",
    creditUrl: "https://sadeqh.ir/",
  },
  en: {
    address:
      "Tehran, Pasdaran, Boostan 6th, next to Khadijah School, No. 135, 2nd floor, Unit 8",
    phone: "021-22579316",
    phoneAlt: "021-22579315",
    hours: "Saturday to Thursday, by prior arrangement",
    instagram: "@dr.s.m.jafari",
    instagramUrl: "https://www.instagram.com/dr.s.m.jafari/",
    medicalCode: "141130",
    credit: "Site designer: Sadegh Fallahi",
    creditUrl: "https://sadeqh.ir/",
  },
  ar: {
    address: "طهران، باسداران، بستان السادس، جوار مدرسة خديجة الكبرى، رقم ۱۳۵، الطابق ۲، وحدة ۸",
    phone: "021-22579316",
    phoneAlt: "021-22579315",
    hours: "من السبت إلى الخميس، بموعد مسبق",
    instagram: "@dr.s.m.jafari",
    instagramUrl: "https://www.instagram.com/dr.s.m.jafari/",
    medicalCode: "141130",
    credit: "تصميم الموقع: صادق فلاحي",
    creditUrl: "https://sadeqh.ir/",
  },
  zh: {
    address: "德黑兰，帕斯达兰，第六花园，哈蒂彻学校旁，135号，2层，8单元",
    phone: "021-22579316",
    phoneAlt: "021-22579315",
    hours: "周六至周四，需提前预约",
    instagram: "@dr.s.m.jafari",
    instagramUrl: "https://www.instagram.com/dr.s.m.jafari/",
    medicalCode: "141130",
    credit: "网站设计：萨德格·法拉希",
    creditUrl: "https://sadeqh.ir/",
  },
};
