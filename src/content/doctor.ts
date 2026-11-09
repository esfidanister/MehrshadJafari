export type VerificationStatus = 'verified' | 'reported' | 'unverified';

export type BilingualText = {
  fa: string;
  en: string;
  ar?: string;
  zh?: string;
};

export type SourcedClaim = {
  fa: string;
  en: string;
  ar?: string;
  zh?: string;
  source: string;
  status: VerificationStatus;
  notes?: BilingualText;
};

export type EducationEntry = {
  year?: string;
  degree: BilingualText;
  institution: BilingualText;
  detail?: BilingualText;
  source: string;
  status: VerificationStatus;
};

export type BookRole = "authored" | "collaboration" | "translation";

export type BookEntry = {
  title: BilingualText;
  collaborative: boolean;
  role: BookRole;
  coAuthors?: string[];
  publisher?: string;
  source: string;
};

export type PracticeLocation = {
  label: BilingualText;
  address: BilingualText;
  phone?: string;
  neighborhood?: BilingualText;
  hours?: BilingualText;
  source: string;
  confirmed: false;
  needsConfirmation?: BilingualText;
};

export type BookingLink = {
  platform: 'nobat.ir' | 'paziresh24' | 'drdr';
  label: BilingualText;
  url: string;
  status: VerificationStatus;
  notes?: BilingualText;
};

export type PodcastAppearance = {
  platform: 'castbox';
  topic: BilingualText;
  title: BilingualText;
  url: string;
  source: string;
  status: VerificationStatus;
  notes?: BilingualText;
};

export type DoctorProfile = {
  brand: string;
  medicalCode: string;
  name: BilingualText;
  specialty: BilingualText;
  tagline: BilingualText;
  summary: BilingualText;
  education: EducationEntry[];
  achievements: SourcedClaim[];
  books: BookEntry[];
  social: {
    instagram: {
      handle: string;
      url: string;
    };
  };
  locations: PracticeLocation[];
  locationConflictsNote: BilingualText;
  bookingUrls: BookingLink[];
  podcast: PodcastAppearance;
  disclaimers: BilingualText;
};

export const doctorProfile: DoctorProfile = {
  brand: 'JAFARI',
  medicalCode: '141130',
  name: {
    fa: 'دکتر سید مهرشاد جعفری',
    en: 'Dr. Seyed Mehrshad Jafari',
    ar: 'الدكتور سيد مهرشاد جعفري',
    zh: '赛义德·梅尔沙德·贾法里医生'
  },
  specialty: {
    fa: 'متخصص جراحی دهان، فک و صورت',
    en: 'Oral & Maxillofacial Surgeon',
    ar: 'اختصاصي جراحة الفم والفك والوجه',
    zh: '口腔颌面外科医师'
  },
  tagline: {
    fa: 'هنر جراحی، دقت در هر جزئیات',
    en: 'The Art of Surgery. Precision in Every Detail.',
    ar: 'فن الجراحة، والدقة في كل تفصيل.',
    zh: '手术的技艺，在于每一处精确。'
  },
  summary: {
    fa: 'جراح دهان، فک و صورت. بیشتر وقتم صرف دندان عقل، ایمپلنت، اصلاح فک و بررسی ضایعه‌های دهان می‌شود. دوست دارم بیمار قبل از هر تصمیمی بداند چه چیزی لازم است و چه چیزی لازم نیست.',
    en: 'I am an oral and maxillofacial surgeon. Most of my work is wisdom teeth, implants, jaw correction, and oral lesions. I want patients to know what is needed, and what is not, before any decision.',
    ar: 'أنا جراح فم وفك ووجه. أكثر عملي على ضرس العقل والزراعة وتعديل الفك وفحص آفات الفم. أحب أن يعرف المريض ما يلزم وما لا يلزم قبل أي قرار.',
    zh: '我是口腔颌面外科医生。工作主要是智齿、种植、颌骨矫正和口腔病变检查。我希望患者在做任何决定之前，先知道什么是需要的，什么是不需要的。'
  },
  education: [
    {
      year: '۱۳۸۶',
      degree: {
        fa: 'رتبه برتر کنکور سراسری',
        en: 'Top rank, national university entrance exam',
        ar: 'مرتبة متقدمة في امتحان الدخول الوطني',
        zh: '全国高考名列前茅'
      },
      institution: {
        fa: 'کنکور سراسری',
        en: 'National University Entrance Exam',
        ar: 'امتحان الدخول الوطني',
        zh: '全国大学入学考试'
      },
      source: 'dentistprofessors.ir / dandoonpezeshki.com (third-party bios)',
      status: 'reported'
    },
    {
      year: '۱۳۹۲',
      degree: {
        fa: 'دکترای دندانپزشکی عمومی',
        en: 'Doctor of Dental Surgery (DDS)',
        ar: 'دكتوراه طب الأسنان',
        zh: '口腔医学博士'
      },
      institution: {
        fa: 'دانشگاه علوم پزشکی شهید بهشتی',
        en: 'Shahid Beheshti University of Medical Sciences',
        ar: 'جامعة الشهيد بهشتي للعلوم الطبية',
        zh: '沙希德·贝赫什提医科大学'
      },
      source: 'dentistprofessors.ir / doctoreto.com',
      status: 'reported'
    },
    {
      year: '۱۳۹۲',
      degree: {
        fa: 'رتبه ۲۰ آزمون دستیاری',
        en: 'Rank 20, specialty entrance exam',
        ar: 'المرتبة ٢٠ في امتحان الإقامة التخصصية',
        zh: '专科住院医师考试第20名'
      },
      institution: {
        fa: 'آزمون ورود به دستیاری تخصصی',
        en: 'Specialty residency entrance exam',
        ar: 'امتحان دخول الإقامة التخصصية',
        zh: '专科住院医师入学考试'
      },
      source: 'dentistprofessors.ir / dandoonpezeshki.com',
      status: 'reported'
    },
    {
      year: '۱۳۹۷',
      degree: {
        fa: 'تخصص جراحی دهان، فک و صورت',
        en: 'Oral & Maxillofacial Surgery specialty',
        ar: 'اختصاص جراحة الفم والفك والوجه',
        zh: '口腔颌面外科专科'
      },
      institution: {
        fa: 'دوره دستیاری',
        en: 'Specialty residency',
        ar: 'فترة الإقامة التخصصية',
        zh: '专科住院医师培训'
      },
      detail: {
        fa: 'برخی پروفایل‌ها (مثلاً doctoreto) فارغ‌التحصیلی تخصص را نیز به شهید بهشتی نسبت می‌دهند. این تعارض باید با مدارک رسمی یکسان‌سازی شود.',
        en: 'Some profiles (e.g. Doctoreto) also attribute specialty training to Shahid Beheshti. This conflict should be reconciled with official credentials.'
      },
      source: 'dentistprofessors.ir / doctoreto.com / dandoonpezeshki.com',
      status: 'reported'
    }
  ],
  achievements: [
    {
      fa: 'رتبه دوم بورد تخصصی کشوری',
      en: 'Second place, national specialty board',
      ar: 'المرتبة الثانية في البورد التخصصي الوطني',
      zh: '全国专科委员会考试第二名',
      source: 'dandoonpezeshki.com / abslang.ir',
      status: 'reported'
    },
    {
      fa: 'عضو کمیته علمی انجمن جراحان دهان، فک و صورت',
      en: 'Scientific committee, Iranian Association of Oral and Maxillofacial Surgeons',
      ar: 'عضو اللجنة العلمية لجمعية جراحي الفم والفك والوجه',
      zh: '伊朗口腔颌面外科医师协会科学委员会成员',
      source: 'dandoonpezeshki.com',
      status: 'reported'
    },
    {
      fa: 'هیئت علمی دانشگاه تهران',
      en: 'University of Tehran faculty member',
      source: 'dandoonpezeshki.com / doctoreto.com',
      status: 'unverified',
      notes: {
        fa: 'وضعیت فعلی عضویت هیئت علمی تأیید نشده است. doctoreto عبارت «استادیار سابق» را به کار برده است — فقط در صورت تأیید رسمی در وب‌سایت نمایش داده شود.',
        en: 'Current faculty status is not verified. Doctoreto lists “former assistant professor”—display only after official confirmation.'
      }
    },
    {
      fa: 'مدرس دوره‌های ایمپلنت و جراحی زنده',
      en: 'Implant course faculty and live-surgery surgeon',
      ar: 'محاضر دورات الزراعة وجراح العمليات المباشرة',
      zh: '种植课程讲师与直播手术术者',
      source: 'diansalamat.com / public congress programs',
      status: 'reported'
    },
    {
      fa: 'نویسنده و مترجم کتاب‌های تخصصی جراحی',
      en: 'Author and translator of specialty surgery books',
      ar: 'مؤلف ومترجم كتب الجراحة التخصصية',
      zh: '专科手术书籍的作者与译者',
      source: 'mahdroo.ir / doctoreto.com',
      status: 'reported'
    }
  ],
  books: [
    {
      title: {
        fa: 'درسنامه جراحی دهان، فک و صورت (هاپ) — جلد ۱',
        en: 'Oral & Maxillofacial Surgery Handbook (HAAP), Vol. 1',
        ar: 'مقرر جراحة الفم والفك والوجه (هاپ) — المجلد ١',
        zh: '口腔颌面外科手册（HAAP）第 1 卷'
      },
      collaborative: true,
      role: 'collaboration',
      coAuthors: [
        'علیرضا جهانگیرنیا',
        'امیدرضا فضلی‌صالحی'
      ],
      source: 'mahdroo.ir'
    },
    {
      title: {
        fa: 'درسنامه جراحی دهان، فک و صورت (هاپ) — جلد ۲',
        en: 'Oral & Maxillofacial Surgery Handbook (HAAP), Vol. 2',
        ar: 'مقرر جراحة الفم والفك والوجه (هاپ) — المجلد ٢',
        zh: '口腔颌面外科手册（HAAP）第 2 卷'
      },
      collaborative: true,
      role: 'collaboration',
      coAuthors: ['امیدرضا فضلی‌صالحی'],
      source: 'mahdroo.ir'
    },
    {
      title: {
        fa: 'جراحی نوین دهان، فک و صورت',
        en: 'Contemporary Oral & Maxillofacial Surgery',
        ar: 'جراحة الفم والفك والوجه المعاصرة',
        zh: '当代口腔颌面外科'
      },
      collaborative: true,
      role: 'collaboration',
      coAuthors: ['امید سلطانی‌نیا', 'محمد اسمعیلی‌نژاد'],
      source: 'mahdroo.ir'
    },
    {
      title: {
        fa: '۳۰۰ نکته منتخب (گلچین بهترین‌ها) جراحی',
        en: '300 Selected Surgery Pearls',
        ar: '٣٠٠ ملاحظة مختارة في الجراحة',
        zh: '300 条精选手术要点'
      },
      collaborative: false,
      role: 'authored',
      source: 'mahdroo.ir'
    },
    {
      title: {
        fa: 'اورژانس‌های پزشکی در مطب دندانپزشکی مالامد ۲۰۱۵',
        en: 'Medical Emergencies in the Dental Office (Malamed 2015)',
        ar: 'الطوارئ الطبية في عيادة الأسنان، مالاميد ٢٠١٥',
        zh: '牙科诊室医疗急救（Malamed 2015）'
      },
      collaborative: true,
      role: 'translation',
      coAuthors: [
        'پدرام بخشایی',
        'مصطفی آلام',
        'مهرداد دهقان‌پور'
      ],
      source: 'mahdroo.ir'
    },
    {
      title: {
        fa: 'جراحی دهان، فک و صورت (فونسکا ۲۰۱۸) — جلد ۱ و ۲',
        en: 'Oral & Maxillofacial Surgery (Fonseca 2018), Vols. 1–2',
        ar: 'جراحة الفم والفك والوجه (فونسيكا ٢٠١٨) — المجلدان ١ و٢',
        zh: '口腔颌面外科（Fonseca 2018）第 1–2 卷'
      },
      collaborative: true,
      role: 'translation',
      coAuthors: ['مهرداد دهقانپور', 'سیدمهدی جعفری'],
      source: 'mahdroo.ir'
    },
    {
      title: {
        fa: 'اصول کشیدن دندان',
        en: 'Principles of Tooth Extraction',
        ar: 'مبادئ قلع الأسنان',
        zh: '拔牙原则'
      },
      collaborative: true,
      role: 'collaboration',
      coAuthors: [
        'یاسمن ایزدپناه',
        'هامون صبری',
        'علیرضا جهانگیرنیا'
      ],
      source: 'mahdroo.ir'
    },
    {
      title: {
        fa: 'برگزیده ترومای دهان، فک و صورت: فونسکا ۲۰۱۳',
        en: 'Selected Oral & Maxillofacial Trauma: Fonseca 2013',
        ar: 'مختارات رضوح الفم والفك والوجه: فونسيكا ٢٠١٣',
        zh: '口腔颌面创伤精选：Fonseca 2013'
      },
      collaborative: true,
      role: 'translation',
      coAuthors: ['بهناز پوریان', 'آرش خجسته', 'نیما صادقی'],
      source: 'mahdroo.ir'
    }
  ],
  social: {
    instagram: {
      handle: '@dr.s.m.jafari',
      url: 'https://www.instagram.com/dr.s.m.jafari/'
    }
  },
  locations: [
    {
      label: {
        fa: 'مطب پاسداران (nobat.ir)',
        en: 'Pasdaran office (nobat.ir listing)'
      },
      address: {
        fa: 'تهران، پاسداران، بوستان ششم، جنب مدرسه خدیجه کبری، پلاک ۱۳۵، طبقه ۲، واحد ۸',
        en: 'Tehran, Pasdaran, Boostan 6th, next to Khadijah School, No. 135, 2nd floor, Unit 8'
      },
      phone: '021-22579316',
      neighborhood: {
        fa: 'پاسداران',
        en: 'Pasdaran'
      },
      source: 'nobat.ir/q7x4m7gjy',
      confirmed: false
    },
    {
      label: {
        fa: 'مطب (dandoonpezeshki.com)',
        en: 'Office (dandoonpezeshki.com)'
      },
      address: {
        fa: 'تهران، پاسداران، بوستان ششم، جنب مدرسه خدیجه کبری، پلاک ۱۳۵، طبقه ۲، واحد ۸',
        en: 'Tehran, Pasdaran, Boostan 6th, next to Khadijah School, No. 135, 2nd floor, Unit 8'
      },
      phone: '02122579316',
      source: 'dandoonpezeshki.com/doctors/mehrshad-jafari',
      confirmed: false
    },
    {
      label: {
        fa: 'ساعات کاری (getzoop.com)',
        en: 'Hours (getzoop.com)'
      },
      address: {
        fa: 'همان آدرس پاسداران — طبق زوپ',
        en: 'Same Pasdaran address per Zoop listing'
      },
      phone: '02122579316',
      hours: {
        fa: 'شنبه تا چهارشنبه، ۱۶:۳۰ تا ۲۰:۰۰ (طبق زوپ)',
        en: 'Saturday–Wednesday, 16:30–20:00 (per Zoop)'
      },
      source: 'getzoop.com/drmehrshadjafari',
      confirmed: false,
      needsConfirmation: {
        fa: 'ساعات و شماره تماس با مطب هماهنگ شود.',
        en: 'Clinic hours and phone should be confirmed with the office.'
      }
    }
  ],
  locationConflictsNote: {
    fa: 'چند منبع (nobat.ir، dandoonpezeshki، getzoop) آدرس مشابه پاسداران را گزارش کرده‌اند؛ doctoreto در زمان بررسی آدرس/تلفن ثبت نکرده بود. همچنین برخی دایرکتوری‌ها محله‌های دیگر (مثل آجودانیه) را در برچسب جستجو ذکر کرده‌اند — فقط آدرس تأییدشده مطب در سایت نهایی استفاده شود.',
    en: 'Several sources (Nobat.ir, dandoonpezeshki, Zoop) report a similar Pasdaran address; Doctoreto had no address/phone on file at review time. Some directories mention other neighborhoods (e.g. Aghdasieh) in search tags—use only the clinic-confirmed address on the live site.'
  },
  bookingUrls: [
    {
      platform: 'nobat.ir',
      label: {
        fa: 'نوبت‌دهی نوبت‌ایر',
        en: 'Book on Nobat.ir'
      },
      url: 'https://nobat.ir/q7x4m7gjy',
      status: 'reported'
    },
    {
      platform: 'paziresh24',
      label: {
        fa: 'پروفایل در پذیرش۲۴',
        en: 'Profile on Paziresh24'
      },
      url: 'https://www.paziresh24.com/dr/دکتر-مهرشاد-جعفری-3/',
      status: 'reported'
    },
    {
      platform: 'drdr',
      label: {
        fa: 'پروفایل در دکتردکتر',
        en: 'Profile on DrDr'
      },
      url: 'https://drdr.ir/dr/49557/دکتر-سید-مهرشاد-جعفری/',
      status: 'reported'
    }
  ],
  podcast: {
    platform: 'castbox',
    topic: {
      fa: 'جراحی دندان عقل',
      en: 'Wisdom tooth surgery'
    },
    title: {
      fa: 'گفت‌وگو درباره جراحی دندان عقل',
      en: 'A conversation about wisdom tooth surgery'
    },
    url: 'https://lnkd.in/dQR8iXSX',
    source:
      'linkedin.com/posts/salarchaychisalmasi_podcast-farsi-wisdomtoothsurgery (2025-10-02)',
    status: 'reported',
    notes: {
      fa: 'لینک از اعلام عمومی پادکست (کست‌باکس) است؛ URL مستقیم اپیزود در کست‌باکس باید تأیید شود.',
      en: 'Link from the public Castbox episode announcement; direct Castbox episode URL should be confirmed.'
    }
  },
  disclaimers: {
    fa: 'محتوای این صفحه آموزشی و معرفی خدمات است و جایگزین ویزیت، تشخیص یا درمان حضوری نیست. مدارک و عناوین صرفاً در صورت تأیید رسمی مطب منتشر شوند.',
    en: 'This content is educational and introductory; it does not replace an in-person visit, diagnosis, or treatment. Credentials and titles should be published only after official clinic confirmation.'
  }
};
