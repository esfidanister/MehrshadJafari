export type PatientReview = {
  id: string;
  date: string;
  text: string;
  source: 'nobat.ir';
  /** Original rating not always listed per review on Nobat.ir */
  rating?: number;
  topic?: 'implant' | 'wisdom-tooth' | 'general' | 'cyst' | 'extraction';
};

export type ReviewAggregate = {
  averageRating: number;
  totalCount: number;
  source: 'nobat.ir';
  importedAt: string;
  disclaimer: {
    fa: string;
    en: string;
  };
};

/**
 * Selected patient comments imported from Nobat.ir listing for Dr. Seyyed Mehrshad Jafari.
 * Persian text is preserved verbatim from the platform.
 */
export const reviewAggregate: ReviewAggregate = {
  averageRating: 4.9,
  totalCount: 102,
  source: 'nobat.ir',
  importedAt: '2026-11-07',
  disclaimer: {
    fa: 'این میانگین را بیماران در نوبت‌ایر ثبت کرده‌اند.',
    en: 'Patients recorded this average on Nobat.ir.'
  }
};

export const patientReviews: PatientReview[] = [
  {
    id: 'nobat-001',
    date: '1403/11/30',
    text: 'دو واحد ایمپلنت داشتم به شدت راضی بود',
    source: 'nobat.ir',
    topic: 'implant'
  },
  {
    id: 'nobat-002',
    date: '1403/11/29',
    text: 'من جراحی دندان عقل انجام دادم بسیار عالیی بودن.',
    source: 'nobat.ir',
    topic: 'wisdom-tooth'
  },
  {
    id: 'nobat-003',
    date: '1404/09/11',
    text: 'مشکل دندان عقل روی عصب داشتم و ایشون عالی بودند واقعا محشرو از صمیم قلب ممنونم ازشون',
    source: 'nobat.ir',
    topic: 'wisdom-tooth'
  },
  {
    id: 'nobat-004',
    date: '1403/09/24',
    text: 'من برای دندان عقلم که مشکل داشت و هیچ دکتری حاضر به جراحیش نبود به ایشون مراجعه کردم و فوق العاده بود تو ده قیقه و خیلی عالی جراحی انجام دادند',
    source: 'nobat.ir',
    topic: 'wisdom-tooth'
  },
  {
    id: 'nobat-005',
    date: '1404/08/11',
    text: 'ایمپلنت انجام دادم .هشت عدد بسیار دکتر عالی هم از اخلاق و هم از کار ایمپلنت.',
    source: 'nobat.ir',
    topic: 'implant'
  },
  {
    id: 'nobat-006',
    date: '1404/01/18',
    text: 'جراحی ریشه داشتم و برام ایمپلنت انجام دادن هرچی از جراحی و بعدش مترسیدم هیچ بود نه درد نه کبودی هر چی منتظر درد شدم بیامد که نیامد',
    source: 'nobat.ir',
    topic: 'implant'
  },
  {
    id: 'nobat-007',
    date: '1404/04/30',
    text: 'جراحی دندان عقل. عالی و سریع بدون درد.',
    source: 'nobat.ir',
    topic: 'wisdom-tooth'
  },
  {
    id: 'nobat-008',
    date: '1403/08/29',
    text: 'چند سال پیش پیش ایشون دو واحد ایمپلنت انجام دادم ، کارشون بی نظیره واقعا ایشون فرزند استاد بزرگ مرحوم دکتر مهدی جعفری هستند و بی نظیرن، اقوام هم بهشون معرفی کردم همه راضی هستند',
    source: 'nobat.ir',
    topic: 'implant'
  },
  {
    id: 'nobat-009',
    date: '1403/09/27',
    text: 'بهترین جراحی که تو زندگیم دیدم واقعا حرفه ای هستن',
    source: 'nobat.ir',
    topic: 'general'
  },
  {
    id: 'nobat-010',
    date: '1404/02/09',
    text: 'ایمپلنت داشتیم من و همسرم - کارشون واخلاقشون عالی بود- در ضمن قیمت هم بسیار منصفانه بود بعد از کلی سرچ ایشون رو پیدا کردیم. آذزوی سلامتی براشون داریم',
    source: 'nobat.ir',
    topic: 'implant'
  },
  {
    id: 'nobat-011',
    date: '1401/03/11',
    text: 'کیست دهانی نتیجه عالی',
    source: 'nobat.ir',
    topic: 'cyst'
  },
  {
    id: 'nobat-012',
    date: '1404/08/27',
    text: 'فکم عقب برای درمان به ایشون مراجعه می کنم ولی دوتا از دندون عقل هام که وضعیت خیلی بدی داشتن رو خیلی خوب جراحی کردند',
    source: 'nobat.ir',
    topic: 'wisdom-tooth'
  }
];
