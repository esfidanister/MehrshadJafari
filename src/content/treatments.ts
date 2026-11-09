type Copy = { fa: string; en: string; ar?: string; zh?: string };

export type TreatmentFaq = {
  question: Copy;
  answer: Copy;
};

export type Treatment = {
  slug: string;
  title: Copy;
  shortDescription: Copy;
  description: Copy;
  faq: TreatmentFaq[];
  educationalDisclaimer: Copy;
};

const educationalDisclaimer = {
  fa: 'این مطالب صرفاً آموزشی هستند و تشخیص یا پیش‌بینی نتیجه درمان برای فرد شما را شامل نمی‌شوند. تصمیم درمانی پس از معاینه حضوری اتخاذ می‌شود.',
  en: 'This information is educational only and does not diagnose conditions or predict individual outcomes. Treatment decisions are made after an in-person evaluation.',
  ar: 'هذه المادة تعليمية فقط، ولا تشخّص حالتكم ولا تتنبأ بنتيجة علاجكم. القرار بعد الفحص الحضوري.',
  zh: '这些内容只用于教育，不能诊断您的情况，也不能预测治疗结果。治疗决定在当面检查之后做出。'
};

export const treatments: Treatment[] = [
  {
    slug: 'wisdom-tooth-surgery',
    title: {
      fa: 'جراحی دندان عقل',
      en: 'Wisdom Tooth Surgery',
      ar: 'جراحة ضرس العقل',
      zh: '智齿手术'
    },
    shortDescription: {
      fa: 'ارزیابی موقعیت دندان عقل، برنامه‌ریزی جراحی و مراقبت‌های پس از عمل.',
      en: 'Assessment of wisdom tooth position, surgical planning, and post-operative care.',
      ar: 'تقييم موضع ضرس العقل، وتخطيط الجراحة، والعناية بعد العملية.',
      zh: '评估智齿位置，安排手术，并说明术后护理。'
    },
    description: {
      fa: 'دندان‌های عقل اغلب در اواخر نوجوانی یا اوایل بزرگسالی رشد می‌کنند. گاهی به‌دلیل کمبود فضا، نزدیکی به عصب یا التهاب لثه، نیاز به جراحی تحت بی‌حسی موضعی یا سدیشن بررسی می‌شود. پزشک با تصویربرداری و معاینه، ریسک‌ها و گزینه‌های زمانی برداشتن دندان را توضیح می‌دهد.',
      en: 'Wisdom teeth usually erupt in late adolescence or early adulthood. Limited space, nerve proximity, or gum inflammation may lead to a surgical plan under local anesthesia or sedation. Imaging and clinical examination help explain timing, risks, and alternatives—always on an individual basis.',
      ar: 'تبزغ أضراس العقل غالباً في أواخر المراهقة أو بداية البلوغ. قد يُبحث في الجراحة تحت التخدير الموضعي أو التهدئة إذا ضاق المكان أو اقترب العصب أو التهب اللثة. يشرح الطبيب التوقيت والمخاطر بعد الصورة والفحص.',
      zh: '智齿多在青春期后期或成年早期萌出。如果空间不足、靠近神经或牙龈发炎，会在局部麻醉或镇静下讨论手术。医生会根据影像和检查说明时机、风险和其他选择。'
    },
    faq: [
      {
        question: {
          fa: 'آیا همه دندان‌های عقل باید کشیده شوند؟',
          en: 'Do all wisdom teeth need to be removed?',
          ar: 'هل يجب قلع كل أضراس العقل؟',
          zh: '所有智齿都必须拔掉吗？'
        },
        answer: {
          fa: 'خیر. اگر دندان سالم، تمیزپذیر و بدون مشکل لثه باشد، ممکن است فقط پیگیری دوره‌ای کافی باشد.',
          en: 'No. If the tooth is healthy, cleansable, and free of gum problems, periodic monitoring may be enough.',
          ar: 'لا. إذا كان السن سليماً وقابلاً للتنظيف وبلا مشكلة في اللثة، فقد تكفي المتابعة.',
          zh: '不必。如果牙齿健康、能清洁、牙龈没有问题，定期观察就够了。'
        }
      },
      {
        question: {
          fa: 'بعد از جراحی چه انتظاری منطقی است؟',
          en: 'What is reasonable to expect after surgery?',
          ar: 'ماذا يُتوقع بعد الجراحة؟',
          zh: '手术后通常会怎样？'
        },
        answer: {
          fa: 'تورم خفیف، ناراحتی موقت و رژیم نرم در چند روز اول شایع است. دستورات دارویی و بهداشت دهان شخصی‌سازی می‌شود.',
          en: 'Mild swelling, temporary discomfort, and a soft diet are common in the first days. Medication and oral-hygiene instructions are tailored to you.',
          ar: 'تورم خفيف وانزعاج مؤقت وطعام لين في الأيام الأولى أمور شائعة. تعليمات الدواء ونظافة الفم تُفصَّل لكم.',
          zh: '头几天轻度肿胀、暂时不适和软食很常见。用药和口腔清洁会按您的情况说明。'
        }
      },
      {
        question: {
          fa: 'چه زمانی باید فوراً با مطب تماس بگیرم؟',
          en: 'When should I contact the clinic urgently?',
          ar: 'متى أتصل بالعيادة فوراً؟',
          zh: '什么情况要马上联系诊所？'
        },
        answer: {
          fa: 'تب بالا، خونریزی شدید پایدار، تنگی نفس یا درد رو به افزایش غیرقابل کنترل نیاز به ارزیابی فوری دارد.',
          en: 'High fever, heavy persistent bleeding, breathing difficulty, or uncontrolled worsening pain need prompt evaluation.',
          ar: 'الحمى العالية والنزف الشديد المستمر وضيق النفس أو الألم المتزايد غير المسيطر عليه تحتاج تقييماً عاجلاً.',
          zh: '高热、持续大量出血、呼吸困难或越来越重且压不住的疼痛，需要尽快评估。'
        }
      }
    ],
    educationalDisclaimer
  },
  {
    slug: 'dental-implants',
    title: {
      fa: 'ایمپلنت دندانی',
      en: 'Dental Implants',
      ar: 'زراعة الأسنان',
      zh: '种植牙'
    },
    shortDescription: {
      fa: 'جایگزینی ریشه دندان از دست‌رفته با پایه تیتانیومی و روکش ثابت.',
      en: 'Replacing a missing tooth root with a titanium fixture and fixed crown.',
      ar: 'استبدال جذر السن المفقود بقاعدة تيتانيوم وتاج ثابت.',
      zh: '用钛金属植体和固定牙冠替代缺失的牙根。'
    },
    description: {
      fa: 'ایمپلنت پس از ارزیابی استخوان فک، سلامت لثه و شرایط عمومی بیمار برنامه‌ریزی می‌شود. مراحل معمول شامل کاشت، دوره بهبود استخوانی و روکش نهایی است. در برخی موارد کاشت فوری یا بارگذاری زودهنگام قابل بحث است، اما برای هر فک و هر دندان متفاوت است.',
      en: 'Implant treatment is planned after assessing jaw bone, gum health, and general health. Typical steps include placement, bone healing, and the final crown. Immediate placement or early loading may be discussed in selected cases—it varies for each patient.',
      ar: 'تُخطط الزراعة بعد تقييم عظم الفك وصحة اللثة والحالة العامة. الخطوات المعتادة هي الغرس، ثم التئام العظم، ثم التاج. قد يُبحث الغرس الفوري أو التحميل المبكر في حالات مختارة، ويختلف من فك إلى فك.',
      zh: '种植要在评估颌骨、牙龈和全身情况之后安排。常见步骤是植入、骨愈合，然后做牙冠。少数情况下会讨论即刻种植或早期负重，每个人都不一样。'
    },
    faq: [
      {
        question: {
          fa: 'ایمپلنت برای همه مناسب است؟',
          en: 'Are implants suitable for everyone?',
          ar: 'هل الزراعة مناسبة للجميع؟',
          zh: '每个人都适合种植吗？'
        },
        answer: {
          fa: 'نیاز به حداقل استخوان و کنترل بیماری‌های زمینه‌ای دارد. گاهی قبل از کاشت نیاز به پیوند استخوان یا درمان لثه مطرح می‌شود.',
          en: 'They require adequate bone and controlled medical conditions. Bone grafting or gum treatment may be needed first.',
          ar: 'تحتاج حداً كافياً من العظم وضبط الأمراض المرافقة. قد يُطرح طعم العظم أو علاج اللثة قبل الغرس.',
          zh: '需要足够的骨量，全身疾病也要控制住。有时要先植骨或治疗牙龈。'
        }
      },
      {
        question: {
          fa: 'دوره بهبود چقدر طول می‌کشد؟',
          en: 'How long is the healing period?',
          ar: 'كم تستغرق فترة الالتئام؟',
          zh: '愈合要多久？'
        },
        answer: {
          fa: 'بسته به ناحیه (فک بالا یا پایین) و کیفیت استخوان، چند هفته تا چند ماه طول می‌کشد. زمان دقیق پس از معاینه گفته می‌شود.',
          en: 'Depending on site (upper or lower jaw) and bone quality, healing may take weeks to months. Your timeline is discussed after evaluation.',
          ar: 'حسب الموضع، الفك العلوي أو السفلي، وجودة العظم، من أسابيع إلى أشهر. الوقت الدقيق بعد الفحص.',
          zh: '看部位是上颌还是下颌，也看骨的质量，从几周到几个月。具体时间在检查后说明。'
        }
      },
      {
        question: {
          fa: 'مراقبت روزانه چگونه است؟',
          en: 'What about daily care?',
          ar: 'كيف تكون العناية اليومية؟',
          zh: '每天怎么护理？'
        },
        answer: {
          fa: 'بهداشت دهان و استفاده از ابزارهای کمکی بین‌دندانی مانند نخ، برس بین‌دندانی و در صورت نیاز Water Flosser توصیه می‌شود.',
          en: 'Good oral hygiene and interdental cleaning (floss, interdental brushes, water flosser if advised) are recommended.',
          ar: 'تُنصح نظافة الفم والتنظيف بين الأسنان بالخيط والفرشاة البينية، وجهاز الماء إذا نُصحتم به.',
          zh: '建议做好口腔清洁，并用牙线、牙间刷，必要时用水牙线清理牙缝。'
        }
      }
    ],
    educationalDisclaimer
  },
  {
    slug: 'orthognathic-surgery',
    title: {
      fa: 'جراحی ارتوگناتیک (اصلاح ناهنجاری فک)',
      en: 'Orthognathic (Jaw) Surgery',
      ar: 'جراحة تقويم الفكين',
      zh: '正颌手术'
    },
    shortDescription: {
      fa: 'هماهنگی جراحی فک با ارتودنسی برای بهبود عملکرد جویدن، صحبت و تنفس.',
      en: 'Surgical jaw correction coordinated with orthodontics for function and balance.',
      ar: 'تنسيق جراحة الفك مع التقويم لتحسين المضغ والكلام والتنفس.',
      zh: '与正畸配合的颌骨手术，为了咀嚼、说话和呼吸。'
    },
    description: {
      fa: 'وقتی ناهماهنگی فک‌ها علاوه بر ظاهر، روی جویدن، مفصل فک یا تنفس اثر بگذارد، تیم جراح و ارتودنتیست برنامه چندمرحله‌ای طراحی می‌کند. هدف اصلی در این صفحه آموزش فرآیند کلی است؛ گزینه‌های درمانی فقط پس از مدل‌سازی و تصویربرداری تخصصی مشخص می‌شوند.',
      en: 'When jaw disharmony affects chewing, the TMJ, or breathing—not only appearance—a surgeon and orthodontist may plan staged care. This page explains the general process; specific options emerge only after specialized imaging and work-up.',
      ar: 'حين يؤثر اختلاف الفكين على المضغ أو المفصل أو التنفس، لا على الشكل وحده، يضع الجراح وأخصائي التقويم خطة على مراحل. هذه الصفحة تشرح المسار العام. الخيارات تتضح بعد الصور والدراسة.',
      zh: '当上下颌不协调已经影响咀嚼、关节或呼吸，而不只是外观时，外科医生和正畸医生会一起做分阶段计划。本页只说明整体过程。具体方案要等影像和检查之后。'
    },
    faq: [
      {
        question: {
          fa: 'آیا همیشه قبل از جراحی ارتودنسی لازم است؟',
          en: 'Is orthodontics always required before surgery?',
          ar: 'هل التقويم لازم دائماً قبل الجراحة؟',
          zh: '手术前一定要先正畸吗？'
        },
        answer: {
          fa: 'در بیشتر موارد بله؛ زمان‌بندی دقیق (قبل، حین یا بعد از جراحی) به طرح درمان بستگی دارد.',
          en: 'In most cases yes; exact timing (before, during, or after surgery) depends on the treatment plan.',
          ar: 'في أغلب الحالات نعم. التوقيت الدقيق، قبل الجراحة أو خلالها أو بعدها، يعتمد على الخطة.',
          zh: '多数情况需要。具体是在手术前、手术中还是手术后，要看治疗计划。'
        }
      },
      {
        question: {
          fa: 'بستری در بیمارستان لازم است؟',
          en: 'Is hospital admission required?',
          ar: 'هل يلزم البقاء في المستشفى؟',
          zh: '需要住院吗？'
        },
        answer: {
          fa: 'بسته به گستردگی جراحی و سلامت عمومی، ممکن است یک شب بستری یا مراقبت سرپایی توصیه شود.',
          en: 'Depending on surgical extent and health status, one night in hospital or outpatient care may be recommended.',
          ar: 'حسب اتساع الجراحة والصحة العامة، قد تُنصح ليلة في المستشفى أو رعاية دون مبيت.',
          zh: '看手术范围和全身情况，可能建议住一夜，也可能当天返回。'
        }
      },
      {
        question: {
          fa: 'چه عوارضی باید بشناسم؟',
          en: 'What complications should I be aware of?',
          ar: 'ما المضاعفات التي ينبغي معرفتها؟',
          zh: '需要了解哪些反应？'
        },
        answer: {
          fa: 'کرختی موقت، تورم و محدودیت باز کردن دهان شایع است؛ ریسک‌های نادر در ویزیت با جزئیات بیان می‌شود.',
          en: 'Temporary numbness, swelling, and limited opening are common; rare risks are reviewed in detail at consultation.',
          ar: 'الخدر المؤقت والتورم ومحدودية فتح الفم شائعة. المخاطر النادرة تُشرح بالتفصيل في الزيارة.',
          zh: '暂时麻木、肿胀和张口受限比较常见。少见的风险会在面诊时逐项说明。'
        }
      }
    ],
    educationalDisclaimer
  },
  {
    slug: 'oral-pathology',
    title: {
      fa: 'پاتولوژی دهان و ضایعات فکی',
      en: 'Oral Pathology & Jaw Lesions',
      ar: 'أمراض الفم وآفات الفك',
      zh: '口腔病理与颌骨病变'
    },
    shortDescription: {
      fa: 'بررسی توده‌ها، زخم‌های پایدار و ضایعات دهانی-فکی.',
      en: 'Evaluation of persistent sores, lumps, and oral-maxillofacial lesions.',
      ar: 'فحص الكتل والقروح المستمرة وآفات الفم والفك.',
      zh: '检查肿块、久不愈合的溃疡和口颌病变。'
    },
    description: {
      fa: 'هر ضایعه‌ای که بیش از دو هفته بهبود نیابد، خونریزی کند یا به‌سرعت رشد کند باید معاینه شود. ممکن است نیاز به نمونه‌برداری (بیوپسی) یا تصویربرداری باشد. تشخیص نهایی فقط در آزمایشگاه پاتولوژی ثبت می‌شود و این صفحه جایگزین آن نیست.',
      en: 'Lesions that do not heal within two weeks, bleed, or grow quickly should be examined. Biopsy or imaging may be needed. A definitive diagnosis comes only from pathology—this page is not a substitute.',
      ar: 'كل آفة لا تلتئم خلال أسبوعين، أو تنزف، أو تكبر بسرعة، يجب فحصها. قد يلزم خزعة أو صورة. التشخيص النهائي من مختبر الأمراض فقط، وهذه الصفحة لا تغني عنه.',
      zh: '超过两周不愈合、出血或长得很快的病变应该检查。可能需要活检或影像。最终诊断只由病理科做出，本页不能代替。'
    },
    faq: [
      {
        question: {
          fa: 'آیا همه توده‌ها سرطانی هستند؟',
          en: 'Are all lumps cancerous?',
          ar: 'هل كل الكتل سرطانية؟',
          zh: '所有肿块都是癌吗？'
        },
        answer: {
          fa: 'خیر. بسیاری از ضایعات خوش‌خیم‌اند، اما تمایز آن‌ها فقط پس از ارزیابی بالینی و در صورت نیاز آزمایش بافت ممکن است.',
          en: 'No. Many lesions are benign, but telling them apart requires clinical assessment and sometimes tissue testing.',
          ar: 'لا. كثير من الآفات سليمة، لكن التمييز يتم بعد الفحص السريري وأحياناً فحص النسيج.',
          zh: '不是。很多病变是良性的，但要靠临床检查，必要时再做组织化验才能区分。'
        }
      },
      {
        question: {
          fa: 'بیوپسی دردناک است؟',
          en: 'Is a biopsy painful?',
          ar: 'هل الخزعة مؤلمة؟',
          zh: '活检疼吗？'
        },
        answer: {
          fa: 'معمولاً با بی‌حسی موضعی انجام می‌شود. دستورالعمل مراقبت بعد از نمونه‌برداری به شما داده می‌شود.',
          en: 'It is usually done with local anesthesia. Post-biopsy care instructions are provided.',
          ar: 'تُجرى عادة تحت التخدير الموضعي، وتُعطى تعليمات العناية بعدها.',
          zh: '通常在局部麻醉下进行，之后会给您护理说明。'
        }
      },
      {
        question: {
          fa: 'کیست دهانی چیست؟',
          en: 'What is an oral cyst?',
          ar: 'ما كيس الفم؟',
          zh: '口腔囊肿是什么？'
        },
        answer: {
          fa: 'کیست کیسه پر از مایع در استخوان یا بافت نرم است. اندازه و محل آن تعیین می‌کند که آیا تحت نظر باشد یا جراحی نیاز دارد.',
          en: 'A cyst is a fluid-filled sac in bone or soft tissue. Size and location determine monitoring versus surgical treatment.',
          ar: 'الكيس جيب مملوء بسائل في العظم أو النسيج الرخو. حجمه وموضعه يحددان المتابعة أو الجراحة.',
          zh: '囊肿是骨内或软组织里的液性囊。是观察还是手术，要看大小和位置。'
        }
      }
    ],
    educationalDisclaimer
  },
  {
    slug: 'bone-graft-sinus-lift',
    title: { fa: 'پیوند استخوان و سینوس لیفت', en: 'Bone graft and sinus lift', ar: 'طعم العظم ورفع الجيب', zh: '植骨与上颌窦提升' },
    shortDescription: {
      fa: 'وقتی استخوان برای نگه داشتن ایمپلنت کافی نیست.',
      en: 'When there is not enough bone to hold an implant.',
      ar: 'حين لا يكفي العظم لحمل الزراعة.',
      zh: '当骨量不足以固定种植体时。'
    },
    description: {
      fa: 'بعد از دست دادن دندان، استخوان همان ناحیه ممکن است تحلیل برود. اگر برای کاشت کافی نباشد، گاهی اول پیوند انجام می‌شود. سینوس لیفت مخصوص فک بالاست و فقط وقتی کف سینوس مانع کاشت باشد مطرح می‌شود. لازم بودن هر کدام را معاینه و تصویر می‌گوید.',
      en: 'After a tooth is lost, the bone there may shrink. If it cannot hold an implant, a graft sometimes comes first. A sinus lift belongs to the upper jaw, and only when the sinus floor is in the way. The exam and the images decide which one you need.',
      ar: 'بعد فقدان السن قد يتراجع العظم. إذا لم يحتمل الزراعة، يُجرى الطعم أحياناً أولاً. رفع الجيب للفك العلوي فقط، وحين يعترض قاع الجيب الغرس. الفحص والصورة يقرران.',
      zh: '牙齿缺失后，局部骨头可能萎缩。如果托不住种植体，有时要先植骨。上颌窦提升只用于上颌，而且只在窦底挡住种植位置时才讨论。是否需要，由检查和影像决定。'
    },
    faq: [
      {
        question: { fa: 'آیا همه ایمپلنت‌ها پیوند می‌خواهند؟', en: 'Does every implant need a graft?', ar: 'هل كل زراعة تحتاج طعماً؟', zh: '是不是每次种植都要植骨？' },
        answer: { fa: 'خیر. اگر حجم و کیفیت استخوان مناسب باشد، کاشت بدون پیوند برنامه‌ریزی می‌شود.', en: 'No. If the volume and quality of bone are suitable, placement is planned without a graft.', ar: 'لا. إذا ناسب حجم العظم وجودته، تُخطط الزراعة بلا طعم.', zh: '不是。如果骨量和质量合适，就按不植骨来计划。' }
      }
    ],
    educationalDisclaimer
  },
  {
    slug: 'all-on-4',
    title: { fa: 'ایمپلنت تمام فک', en: 'Full-arch implants', ar: 'زراعة الفك الكامل', zh: '全颌种植' },
    shortDescription: {
      fa: 'دندان ثابت برای یک فک، روی تعداد محدودی پایه.',
      en: 'Fixed teeth for one jaw, on a limited number of fixtures.',
      ar: 'أسنان ثابتة لفك واحد على عدد محدود من القواعد.',
      zh: '用有限的种植体，为一整排颌骨做固定牙。'
    },
    description: {
      fa: 'برای کسی که بیشتر دندان‌های یک فک را از دست داده، گاهی به‌جای دست‌دندان متحرک، پل ثابت روی چند ایمپلنت بررسی می‌شود. نام تجاری یک سیستم، وعده نتیجه برای شما نیست. استخوان، لثه و سلامت عمومی باید در معاینه مناسب باشند.',
      en: 'For someone who has lost most teeth in one jaw, a fixed bridge on several implants is sometimes discussed instead of a removable denture. A brand name is not a promise for you. Bone, gum, and general health have to be suitable at the exam.',
      ar: 'لمن فقد أكثر أسنان فك واحد، يُبحث أحياناً عن جسر ثابت على عدة زراعات بدل طقم متحرك. اسم النظام التجاري ليس وعداً لكم. العظم واللثة والصحة العامة تُقاس في الفحص.',
      zh: '如果一整排牙大多缺失，有时会讨论用几颗种植体支持固定桥，而不是活动假牙。某个商品名不是对您的承诺。骨头、牙龈和全身情况要在检查时合适才行。'
    },
    faq: [
      {
        question: { fa: 'آیا همان روز دندان می‌گذارید؟', en: 'Are the teeth placed the same day?', ar: 'هل توضع الأسنان في اليوم نفسه؟', zh: '当天就能装上牙齿吗？' },
        answer: { fa: 'گاهی پروتز موقت همان روز ممکن است و گاهی نیست. این را ثبات پایه‌ها در جراحی مشخص می‌کند.', en: 'A temporary prosthesis is sometimes possible the same day, and sometimes not. Stability of the fixtures during surgery decides that.', ar: 'قد يمكن وضع تعويض مؤقت في اليوم نفسه، وقد لا يمكن. ثبات القواعد أثناء الجراحة هو الذي يقرر.', zh: '有时当天可以戴临时修复体，有时不行。这要看手术中种植体稳不稳。' }
      }
    ],
    educationalDisclaimer
  },
  {
    slug: 'facial-fractures',
    title: { fa: 'شکستگی فک و صورت', en: 'Facial fractures', ar: 'كسور الفك والوجه', zh: '颌面骨折' },
    shortDescription: {
      fa: 'ترمیم شکستگی بعد از ضربه، با هدف برگشت جویدن و فرم.',
      en: 'Repair after injury, aiming to restore biting and form.',
      ar: 'إصلاح الكسر بعد الرض، لإعادة المضغ والشكل.',
      zh: '外伤后的修复，目标是恢复咀嚼和外形。'
    },
    description: {
      fa: 'شکستگی فک، گونه یا دندان بعد از تصادف یا ضربه باید زود دیده شود، به‌خصوص اگر دهان درست بسته نمی‌شود، بی‌حسی لب مانده، یا تنفس سخت شده است. درمان از مهار ساده تا جراحی با صفحه متغیر است و فوریت را معاینه تعیین می‌کند.',
      en: 'A fracture of the jaw, cheek, or teeth after a fall or impact should be seen early, especially if the bite does not meet, lip numbness remains, or breathing is difficult. Care ranges from simple fixation to surgery with plates, and urgency is decided at the exam.',
      ar: 'كسر الفك أو الوجنة أو الأسنان بعد السقوط أو الضربة يُرى مبكراً، خصوصاً إذا لم ينطبق الفم، أو بقي الخدر في الشفة، أو صعب التنفس. العلاج من تثبيت بسيط إلى جراحة بصفائح، والإلحاح يقرره الفحص.',
      zh: '摔倒或撞击后的颌骨、颧骨或牙齿骨折应尽早查看，尤其是咬合对不上、嘴唇还麻，或呼吸困难时。处理可以从简单固定到用钛板手术，紧不紧急由检查决定。'
    },
    faq: [
      {
        question: { fa: 'آیا هر ضربه به صورت جراحی می‌خواهد؟', en: 'Does every facial impact need surgery?', ar: 'هل كل رض على الوجه يحتاج جراحة؟', zh: '脸上每一下撞击都要手术吗？' },
        answer: { fa: 'خیر. بعضی شکستگی‌ها با محدودیت حرکت و پیگیری جوش می‌خورند. جابه‌جایی و مشکل تنفس مسیر را عوض می‌کند.', en: 'No. Some fractures heal with limited movement and follow-up. Displacement and breathing trouble change the path.', ar: 'لا. بعض الكسور تلتئم مع تقييد الحركة والمتابعة. الانزياح ومشكلة التنفس يغيران المسار.', zh: '不是。有些骨折靠限制活动和复查就能愈合。移位和呼吸问题会改变处理方式。' }
      }
    ],
    educationalDisclaimer
  },
  {
    slug: 'tmj',
    title: { fa: 'اختلال مفصل فک', en: 'Jaw-joint disorders', ar: 'اضطرابات مفصل الفك', zh: '颞下颌关节紊乱' },
    shortDescription: {
      fa: 'درد، صدا و محدودیت حرکت مفصل؛ اغلب اول بدون جراحی.',
      en: 'Pain, sound, and limited movement; usually non-surgical at first.',
      ar: 'ألم وصوت ومحدودية حركة؛ وغالباً بلا جراحة في البداية.',
      zh: '疼痛、弹响和张口受限；通常先不做手术。'
    },
    description: {
      fa: 'مفصل جلوی گوش می‌تواند صدا بدهد، درد بگیرد یا دهان را محدود کند. قدم اول معمولاً معاینه، توضیح عادت‌ها، محافظ شب و درمان ساده‌تر است. جراحی مفصل وقتی مطرح می‌شود که این مسیر آرام‌بخش نبوده و علت ساختاری مشخص شده باشد.',
      en: 'The joint in front of the ear can click, hurt, or limit opening. The first step is usually an exam, a talk about habits, a night guard, and simpler care. Joint surgery is discussed when that path has not helped and a structural cause is clear.',
      ar: 'مفصل أمام الأذن قد يطقطق أو يوجع أو يحد الفتح. الخطوة الأولى عادة فحص وحديث عن العادات وواقية ليلية وعلاج أبسط. جراحة المفصل تُطرح إذا لم يفد هذا المسار وظهر سبب بنيوي.',
      zh: '耳前的关节可能弹响、疼痛或限制张口。第一步通常是检查、谈谈习惯、夜间护具和更简单的处理。只有这条路没有帮助、而且结构原因明确时，才讨论关节手术。'
    },
    faq: [
      {
        question: { fa: 'صدای کلیک یعنی باید عمل شوم؟', en: 'Does a click mean I need an operation?', ar: 'هل الطقطقة تعني أنني أحتاج عملية؟', zh: '有弹响就要手术吗？' },
        answer: { fa: 'خیر. صدای تنها، بدون درد و بدون قفل شدن، اغلب فقط توضیح و پیگیری می‌خواهد.', en: 'No. A sound alone, without pain or locking, usually needs an explanation and follow-up.', ar: 'لا. الصوت وحده، بلا ألم ولا انقفال، يحتاج غالباً إلى شرح ومتابعة.', zh: '不是。只有声音、没有疼痛也不锁住，通常解释清楚并复查就够了。' }
      }
    ],
    educationalDisclaimer
  },
  {
    slug: 'cosmetic-jaw',
    title: { fa: 'جراحی فرم فک و چانه', en: 'Jaw and chin form', ar: 'شكل الفك والذقن', zh: '颌骨与下巴外形' },
    shortDescription: {
      fa: 'اصلاح فرم چانه و خط فک وقتی استخوان علت ناهماهنگی است.',
      en: 'Chin and jaw-line changes when bone is the reason for the imbalance.',
      ar: 'تعديل الذقن وخط الفك حين يكون العظم سبب عدم التوازن.',
      zh: '当不协调来自骨头时，调整下巴和下颌缘。'
    },
    description: {
      fa: 'بعضی ناهماهنگی‌های چهره از فرم استخوان چانه یا فک می‌آید، نه از دندان تنها. اگر این جراحی مطرح شود، درباره تغییر فرم، محدودیت‌ها و دوره بهبودی قبل از هر تصمیمی حرف می‌زنیم. این صفحه نمونه قبل و بعد نشان نمی‌دهد و نتیجه‌ای را وعده نمی‌کند.',
      en: 'Some facial imbalance comes from the shape of the chin or jaw bone, not from the teeth alone. If this surgery is discussed, we talk about the change in form, the limits, and recovery before any decision. This page shows no before-and-after and promises no result.',
      ar: 'بعض عدم التوازن يأتي من شكل عظم الذقن أو الفك لا من الأسنان وحدها. إذا طُرحت الجراحة نتحدث عن تغير الشكل وحدوده ومدة الشفاء قبل أي قرار. هذه الصفحة لا تعرض صوراً قبل وبعد ولا تعد بنتيجة.',
      zh: '有些面部不协调来自下巴或颌骨的形状，而不只是牙齿。如果讨论这类手术，会先谈外形会怎么变、有什么限制、恢复要多久，然后再决定。本页不放前后对比，也不承诺结果。'
    },
    faq: [
      {
        question: { fa: 'آیا این جراحی جای ارتودنسی را می‌گیرد؟', en: 'Does this replace orthodontics?', ar: 'هل تغني هذه الجراحة عن التقويم؟', zh: '这种手术能代替正畸吗？' },
        answer: { fa: 'نه همیشه. اگر دندان‌ها هم نامرتب باشند، ارتودنتیست در طرح شریک است.', en: 'Not always. If the teeth are also out of line, the orthodontist is part of the plan.', ar: 'ليس دائماً. إذا كانت الأسنان غير مرتبة أيضاً، يكون أخصائي التقويم شريكاً في الخطة.', zh: '不一定。如果牙齿也不齐，正畸医生要一起参与方案。' }
      }
    ],
    educationalDisclaimer
  }
];

export function getTreatmentBySlug(slug: string): Treatment | undefined {
  return treatments.find((t) => t.slug === slug);
}
