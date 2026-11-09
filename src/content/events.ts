export type ScientificEvent = {
  id: string;
  slug: string;
  title: {fa: string; en: string; ar?: string; zh?: string};
  description: {fa: string; en: string; ar?: string; zh?: string};
  role: {fa: string; en: string; ar?: string; zh?: string};
  startDate: string;
  endDate?: string;
  venue: {fa: string; en: string; ar?: string; zh?: string};
  city: {fa: string; en: string; ar?: string; zh?: string};
  source: string;
  sourceUrl: string;
  status: 'reported' | 'verified';
  tags: string[];
};

export const scientificEvents: ScientificEvent[] = [
  {
    id: 'omfs-2025-bio3-live',
    slug: 'omfs-2025-bio3-live-surgery',
    title: {
      fa: 'لایو جراحی Bio3 FIX — کنگره جراحان دهان، فک و صورت ۱۴۰۳',
      en: 'Bio3 FIX live surgery — OMFS Congress 2025 (1403)',
      ar: 'جراحة مباشرة Bio3 FIX — مؤتمر جراحي الفم والفك والوجه ۱۴۰۳',
      zh: 'Bio3 FIX 直播手术 — 1403 年口腔颌面外科大会'
    },
    description: {
      fa: 'لایو جراحی Fresh Socket با ایمپلنت Bio3 FIX همراه با بارگذاری فوری (Immediate Loading) در چارچوب بیست و دومین کنگره بین‌المللی انجمن جراحان دهان، فک و صورت.',
      en: 'Fresh-socket live surgery with Bio3 FIX implant and immediate loading, as part of the 22nd International Congress of Iranian Oral & Maxillofacial Surgeons.',
      ar: 'جراحة مباشرة لسنخ حديث مع زراعة Bio3 FIX وتحميل فوري، ضمن المؤتمر الدولي الثاني والعشرين لجمعية جراحي الفم والفك والوجه.',
      zh: '在第二十二届伊朗口腔颌面外科医师协会国际大会上，进行 Bio3 FIX 新鲜牙槽窝种植并即刻负重的直播手术。'
    },
    role: {
      fa: 'جراح زنده (همراه دکتر نازیلا نجاری — بارگذاری فوری)',
      en: 'Live surgeon (with Dr. Nazila Najari — immediate loading)',
      ar: 'جراح مباشر (مع الدكتورة نازيلا نجاري — تحميل فوري)',
      zh: '直播手术术者（与纳齐拉·纳贾里医生一同完成即刻负重）'
    },
    startDate: '1403-12-02',
    endDate: '1403-12-03',
    venue: {
      fa: 'هتل المپیک تهران، سالن سیحون',
      en: 'Olympic Hotel Tehran, Sihon Hall',
      ar: 'فندق أولمبيك طهران، قاعة سيحون',
      zh: '德黑兰奥林匹克酒店，锡洪厅'
    },
    city: {fa: 'تهران', en: 'Tehran', ar: 'طهران', zh: '德黑兰'},
    source: 'دیان سلامت دندان',
    sourceUrl:
      'https://diansalamat.com/blog/immediate-loading-with-bio3-fix.p6224',
    status: 'reported',
    tags: ['bio3', 'live-surgery', 'implant', 'congress']
  },
  {
    id: 'omfs-congress-2025',
    slug: '22nd-omfs-congress-olympic-hotel',
    title: {
      fa: 'بیست و دومین کنگره جراحان دهان، فک و صورت ایران',
      en: '22nd Iranian OMFS International Congress',
      ar: 'المؤتمر الدولي الثاني والعشرون لجراحي الفم والفك والوجه في إيران',
      zh: '第二十二届伊朗口腔颌面外科国际大会'
    },
    description: {
      fa: 'کنگره بین‌المللی همراه با همایش ایمپلنت خلیج فارس و سمپوزیوم محققان جوان؛ از ۳۰ بهمن تا ۳ اسفند ۱۴۰۳ در هتل المپیک.',
      en: 'International congress alongside the Persian Gulf Implant Conference and Young Researchers Symposium; 30 Bahman–3 Esfand 1403 at Olympic Hotel.',
      ar: 'مؤتمر دولي إلى جانب مؤتمر زراعة الخليج وملتقى الباحثين الشباب؛ من ٣٠ بهمن إلى ٣ إسفند ۱۴۰۳ في فندق أولمبيك.',
      zh: '与波斯湾种植会议和青年研究者研讨会一同举行；1403 年 11 月 30 日至 12 月 3 日，奥林匹克酒店。'
    },
    role: {
      fa: 'شرکت‌کننده / سخنران و جراح زنده (برنامه Bio3)',
      en: 'Participant / speaker and live surgeon (Bio3 program)',
      ar: 'مشارك / محاضر وجراح مباشر (برنامج Bio3)',
      zh: '参会者 / 讲者与直播手术术者（Bio3 环节）'
    },
    startDate: '1403-11-30',
    endDate: '1403-12-03',
    venue: {
      fa: 'هتل المپیک تهران',
      en: 'Olympic Hotel Tehran',
      ar: 'فندق أولمبيك طهران',
      zh: '德黑兰奥林匹克酒店'
    },
    city: {fa: 'تهران', en: 'Tehran', ar: 'طهران', zh: '德黑兰'},
    source: 'دیان سلامت دندان',
    sourceUrl:
      'https://diansalamat.com/blog/oral-maxillofacial-congress-2025.p6102',
    status: 'reported',
    tags: ['congress', 'omfs']
  },
  {
    id: 'bio3-espinas-1403',
    slug: 'bio3-implants-event-espinas-palace',
    title: {
      fa: 'ایونت Bio3 Implants — هتل اسپیناس پالاس',
      en: 'Bio3 Implants Event — Espinas Palace Hotel',
      ar: 'فعالية Bio3 Implants — فندق إسبيناس بالاس',
      zh: 'Bio3 Implants 活动 — 埃斯皮纳斯皇宫酒店'
    },
    description: {
      fa: 'رویداد معرفی سیستم Bio3 FIX و موضوعات Immediate / Early / Delayed Loading با سخنرانی مشترک دکتر مهرشاد جعفری و دکتر نازیلا نجاری.',
      en: 'Bio3 FIX system introduction and immediate/early/delayed loading topics, with a joint lecture by Dr. Mehrshad Jafari and Dr. Nazila Najari.',
      ar: 'تعريف بنظام Bio3 FIX وموضوعات التحميل الفوري والمبكر والمتأخر، بمحاضرة مشتركة للدكتور مهرشاد جعفري والدكتورة نازيلا نجاري.',
      zh: '介绍 Bio3 FIX 系统以及即刻、早期和延期负重，由迈赫尔沙德·贾法里医生与纳齐拉·纳贾里医生联合讲课。'
    },
    role: {
      fa: 'سخنران — «Immediate vs Early vs Delayed Loading and Bio3 Fix Implant System Presentation»',
      en: 'Speaker — “Immediate vs Early vs Delayed Loading and Bio3 Fix Implant System Presentation”',
      ar: 'محاضر — «Immediate vs Early vs Delayed Loading and Bio3 Fix Implant System Presentation»',
      zh: '讲者 — «Immediate vs Early vs Delayed Loading and Bio3 Fix Implant System Presentation»'
    },
    startDate: '1403-04-14',
    venue: {
      fa: 'هتل اسپیناس پالاس تهران',
      en: 'Espinas Palace Hotel, Tehran',
      ar: 'فندق إسبيناس بالاس، طهران',
      zh: '德黑兰埃斯皮纳斯皇宫酒店'
    },
    city: {fa: 'تهران', en: 'Tehran', ar: 'طهران', zh: '德黑兰'},
    source: 'دیان سلامت دندان',
    sourceUrl: 'https://diansalamat.com/blog/bio3-implants-event-2.p5620',
    status: 'reported',
    tags: ['bio3', 'espinas', 'implant', 'workshop']
  },
  {
    id: 'omfs-2026-bio3-live',
    slug: '23rd-omfs-bio3-live-surgery',
    title: {
      fa: 'لایو جراحی Bio3 FIX — بیست و سومین کنگره جراحان دهان، فک و صورت',
      en: 'Bio3 FIX live surgery — 23rd OMFS Congress',
      ar: 'جراحة مباشرة Bio3 FIX — المؤتمر الثالث والعشرون لجراحي الفم والفك والوجه',
      zh: 'Bio3 FIX 直播手术 — 第二十三届口腔颌面外科大会'
    },
    description: {
      fa: 'جراحی زنده کاشت و بازسازی فوری با سیستم Bio3 FIX، در برنامه کنگره.',
      en: 'Live surgery with immediate placement and restoration using Bio3 FIX, on the congress program.',
      ar: 'جراحة مباشرة للزراعة والترميم الفوري بنظام Bio3 FIX، ضمن برنامج المؤتمر.',
      zh: '大会日程中的 Bio3 FIX 即刻种植与修复直播手术。'
    },
    role: {
      fa: 'جراح زنده (همراه دکتر نازیلا نجاری)',
      en: 'Live surgeon (with Dr. Nazila Najari)',
      ar: 'جراح مباشر (مع الدكتورة نازيلا نجاري)',
      zh: '直播手术术者（与纳齐拉·纳贾里医生）'
    },
    startDate: '1405-05-15',
    venue: {
      fa: 'تهران',
      en: 'Tehran',
      ar: 'طهران',
      zh: '德黑兰'
    },
    city: {fa: 'تهران', en: 'Tehran', ar: 'طهران', zh: '德黑兰'},
    source: 'دیان سلامت دندان',
    sourceUrl: 'https://diansalamat.com/blog/omfs2026-bio3-live-surgery.p7425',
    status: 'reported',
    tags: ['bio3', 'live-surgery', 'congress']
  }
];
