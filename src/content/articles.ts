export type ArticleCopy = { fa: string; en: string; ar?: string; zh?: string };

export type ArticleStub = {
  slug: string;
  title: ArticleCopy;
  excerpt: ArticleCopy;
  category: ArticleCopy;
  categoryId: "implant" | "jaw" | "recovery" | "pathology";
  publishedAt: string;
  readingTimeMinutes: number;
  body?: ArticleCopy;
  educationalNote: ArticleCopy;
};

const educationalNote = {
  fa: 'مطلب آموزشی بیمار — جایگزین مشاوره پزشکی نیست.',
  en: 'Patient education — not a substitute for medical advice.',
  ar: 'مادة تثقيفية للمريض، وليست بديلاً عن الاستشارة الطبية.',
  zh: '这是患者教育内容，不能代替医疗咨询。'
};

export const articles: ArticleStub[] = [
  {
    slug: 'when-is-wisdom-tooth-removal-considered',
    title: {
      fa: 'چه زمانی درباره برداشتن دندان عقل صحبت می‌شود؟',
      en: 'When Is Wisdom Tooth Removal Discussed?',
      ar: 'متى يُبحث في قلع ضرس العقل؟',
      zh: '什么时候会讨论拔智齿？'
    },
    excerpt: {
      fa: 'نشانه‌هایی که معمولاً پزشک را به ارزیابی دقیق‌تر دندان عقل هدایت می‌کند — بدون ترس‌اندازی.',
      en: 'Signs that often lead to a closer wisdom-tooth evaluation—without scare tactics.',
      ar: 'علامات تدفع الطبيب عادة إلى تقييم أدق لضرس العقل، من غير تهويل.',
      zh: '哪些情况会让医生进一步评估智齿。不制造恐惧。'
    },
    category: {
      fa: 'دندان عقل',
      en: 'Wisdom Teeth',
      ar: 'ضرس العقل',
      zh: '智齿'
    },
    categoryId: 'recovery' as const,
    publishedAt: '2026-01-15',
    readingTimeMinutes: 6,
    educationalNote
  },
  {
    slug: 'implant-healing-what-patients-should-know',
    title: {
      fa: 'بهبود بعد از ایمپلنت: آنچه بیماران باید بدانند',
      en: 'After Implant Surgery: What Patients Should Know',
      ar: 'الالتئام بعد الزراعة: ما ينبغي أن يعرفه المريض',
      zh: '种植之后的愈合：患者需要知道的事'
    },
    excerpt: {
      fa: 'رژیم نرم، بهداشت حول پایه ایمپلنت و ویزیت‌های پیگیری — مرور کوتاه مراحل بهبود.',
      en: 'Soft diet, hygiene around the implant site, and follow-ups—a short healing overview.',
      ar: 'طعام لين، ونظافة حول الزراعة، وزيارات المتابعة. نظرة قصيرة على الالتئام.',
      zh: '软食、种植体周围的清洁和复查。简要说明愈合过程。'
    },
    category: {
      fa: 'ایمپلنت',
      en: 'Dental Implants',
      ar: 'زراعة الأسنان',
      zh: '种植牙'
    },
    categoryId: 'implant' as const,
    publishedAt: '2026-02-02',
    readingTimeMinutes: 7,
    educationalNote
  },
  {
    slug: 'orthognathic-care-team-and-timeline',
    title: {
      fa: 'جراحی فک: نقش تیم درمان و زمان‌بندی کلی',
      en: 'Jaw Surgery: Care Team Roles and General Timeline',
      ar: 'جراحة الفك: دور الفريق والمسار العام',
      zh: '颌骨手术：团队分工和大致时间'
    },
    excerpt: {
      fa: 'چرا ارتودنتیست و جراح فک با هم کار می‌کنند و بیمار در هر مرحله چه انتظاری دارد.',
      en: 'Why orthodontists and surgeons collaborate, and what to expect at each stage.',
      ar: 'لماذا يعمل أخصائي التقويم وجراح الفك معاً، وماذا يتوقع المريض في كل مرحلة.',
      zh: '为什么正畸医生和颌面外科医生一起工作，以及每个阶段患者可以期待什么。'
    },
    category: {
      fa: 'ارتوگناتیک',
      en: 'Orthognathic Surgery',
      ar: 'جراحة الفك',
      zh: '正颌手术'
    },
    categoryId: 'jaw' as const,
    publishedAt: '2026-03-10',
    readingTimeMinutes: 8,
    educationalNote
  },
  {
    slug: 'oral-lesions-when-to-seek-evaluation',
    title: {
      fa: 'زخم یا توده دهانی: چه موقع باید معاینه شوید؟',
      en: 'Mouth Sores or Lumps: When to Seek Evaluation',
      ar: 'قرحة أو كتلة في الفم: متى يجب الفحص؟',
      zh: '口腔溃疡或肿块：什么时候该来检查？'
    },
    excerpt: {
      fa: 'علائم هشداردهنده‌ای که نباید نادیده گرفته شوند و تفاوت پیگیری سرپایی با نمونه‌برداری.',
      en: 'Warning signs not to ignore, and when monitoring differs from biopsy.',
      ar: 'علامات لا ينبغي تجاهلها، ومتى تختلف المتابعة عن الخزعة.',
      zh: '哪些信号不该忽略，以及观察和活检有什么不同。'
    },
    category: {
      fa: 'پاتولوژی دهان',
      en: 'Oral Pathology',
      ar: 'أمراض الفم',
      zh: '口腔病理'
    },
    categoryId: 'pathology' as const,
    publishedAt: '2026-04-05',
    readingTimeMinutes: 5,
    educationalNote
  },
  {
    slug: 'implant-from-consult-to-crown',
    title: {
      fa: 'ایمپلنت دندان؛ از اولین مشاوره تا روکش',
      en: 'A dental implant, from the first visit to the crown',
      ar: 'زراعة السن، من أول زيارة حتى التاج',
      zh: '种植牙：从第一次咨询到牙冠'
    },
    excerpt: {
      fa: 'ایمپلنت چیست، چه مرحله‌هایی دارد و چرا زمان دقیق را معاینه مشخص می‌کند.',
      en: 'What an implant is, which stages it has, and why the exact timing is decided at the exam.',
      ar: 'ما الزراعة، وما مراحلها، ولماذا يُحدَّد الوقت في الفحص.',
      zh: '种植是什么、有哪些阶段，以及为什么具体时间要在检查时确定。'
    },
    category: { fa: 'ایمپلنت و بازسازی', en: 'Implants and rebuilding', ar: 'الزراعة وإعادة البناء', zh: '种植与重建' },
    categoryId: 'implant',
    publishedAt: '2026-10-19',
    readingTimeMinutes: 4,
    body: {
      fa: 'ایمپلنت پایه‌ای است که جای ریشه ازدست‌رفته را می‌گیرد. بعد از جوش‌خوردن با استخوان، روکش روی آن می‌نشیند.\n\nاز جراحی تا روکش معمولاً چند ماه طول می‌کشد. اگر استخوان کم باشد و پیوند یا سینوس لیفت لازم شود، این فاصله بیشتر می‌شود. عدد دقیق را بعد از دیدن تصویر و دهان شما می‌گویم، نه در یک صفحه.',
      en: 'An implant is a fixture that takes the place of a missing root. After it bonds with the bone, a crown sits on it.\n\nFrom surgery to the crown usually takes several months. If the bone is thin and a graft or sinus lift is needed, that gap is longer. I give a precise timeline only after seeing you and the images, not from a web page.',
      ar: 'الزراعة قاعدة تحل محل الجذر المفقود. بعد التحامها بالعظم يوضع التاج.\n\nمن الجراحة إلى التاج تمر عادة بضعة أشهر. إذا قلّ العظم واحتاج الأمر إلى طعم أو رفع جيب، تطول المدة. الوقت الدقيق يُقال بعد رؤيتكم والصورة.',
      zh: '种植体用来代替缺失的牙根。它与骨骼结合之后，再安装牙冠。\n\n从手术到牙冠通常要几个月。如果骨量不足、需要植骨或上颌窦提升，时间会更长。具体安排要在看过您和影像之后才能说。'
    },
    educationalNote
  },
  {
    slug: 'after-wisdom-tooth-removal-day-by-day',
    title: {
      fa: 'مراقبت بعد از جراحی دندان عقل',
      en: 'Care after wisdom-tooth surgery',
      ar: 'العناية بعد جراحة ضرس العقل',
      zh: '智齿手术后的护理'
    },
    excerpt: {
      fa: 'گاز، کمپرس سرد، غذای نرم و نشانه‌هایی که باید به مطب خبر بدهید.',
      en: 'Gauze, a cold compress, soft food, and the signs that should bring you back to the clinic.',
      ar: 'الشاش، الكماد البارد، الطعام اللين، والعلامات التي تستدعي الاتصال بالعيادة.',
      zh: '纱布、冷敷、软食，以及需要联系诊室的信号。'
    },
    category: { fa: 'مراقبت و بهبودی', en: 'Care and recovery', ar: 'العناية والشفاء', zh: '护理与恢复' },
    categoryId: 'recovery',
    publishedAt: '2026-10-14',
    readingTimeMinutes: 4,
    body: {
      fa: 'در ساعت‌های اول، گاز را با فشار آرام نگه دارید و آب دهان را تف نکنید. نی، شست‌وشوی شدید و غذای داغ را کنار بگذارید. کمپرس سرد از بیرون گونه، در نوبت‌های کوتاه، به تورم کمک می‌کند.\n\nخونریزی شدید که با گاز آرام نمی‌گیرد، تنگی نفس، تورم سریع گردن یا تب بالا را همان روز به مطب یا اورژانس برسانید. دستور داروی شما شخصی است و از این صفحه کپی نمی‌شود.',
      en: 'In the first hours, hold gauze with gentle pressure and do not spit. Skip straws, forceful rinsing, and hot food. A cold compress on the cheek, in short turns, helps with swelling.\n\nHeavy bleeding that gauze does not slow, trouble breathing, fast swelling of the neck, or a high fever should go to the clinic or an emergency department the same day. Your medicines are personal and are not copied from this page.',
      ar: 'في الساعات الأولى أبقوا الشاش بضغط لطيف ولا تبصقوا. ابتعدوا عن المصاصة والمضمضة القوية والطعام الساخن. الكماد البارد على الخد لفترات قصيرة يخفف التورم.\n\nالنزيف الغزير الذي لا يهدئه الشاش، أو ضيق النفس، أو تورم سريع في العنق، أو حرارة عالية، يحتاج مراجعة في اليوم نفسه. أدويتكم خاصة بكم.',
      zh: '最初几小时请轻轻咬住纱布，不要吐口水。先不要用吸管、用力漱口或吃烫的食物。脸颊短时间冷敷有助于减轻肿胀。\n\n如果纱布止不住明显出血，或出现呼吸困难、颈部迅速肿胀、高热，请当天联系诊室或去急诊。药物是针对您个人的，不能从这一页抄。'
    },
    educationalNote
  },
  {
    slug: 'who-needs-orthognathic-surgery',
    title: {
      fa: 'جراحی فک برای چه کسی مطرح می‌شود؟',
      en: 'When is jaw surgery discussed?',
      ar: 'متى تُطرح جراحة الفك؟',
      zh: '什么时候会讨论颌骨手术？'
    },
    excerpt: {
      fa: 'وقتی مشکل فقط در ردیف دندان‌ها نیست و فک بالا و پایین با هم جور نیستند.',
      en: 'When the problem is not only the teeth, and the upper and lower jaws do not meet well.',
      ar: 'حين لا تكون المشكلة في الأسنان وحدها، ولا يلتقي الفكان جيداً.',
      zh: '当问题不只在牙齿，上下颌对合不好的时候。'
    },
    category: { fa: 'جراحی فک و مفصل', en: 'Jaw and joint', ar: 'الفك والمفصل', zh: '颌骨与关节' },
    categoryId: 'jaw',
    publishedAt: '2026-10-07',
    readingTimeMinutes: 4,
    body: {
      fa: 'گاهی ارتودنسی به‌تنهایی دندان‌ها را ردیف می‌کند، اما جویدن، تنفس یا تقارن فک همچنان مشکل دارد. در این حالت جراح و ارتودنتیست با هم زمان‌بندی را می‌چینند.\n\nجراحی برای همه کسی که فکش کمی نامتقارن است لازم نیست. تصمیم بعد از معاینه، عکس و حرف زدن درباره انتظار شما گرفته می‌شود.',
      en: 'Sometimes orthodontics alone lines up the teeth, while biting, breathing, or jaw balance is still a problem. Then the surgeon and orthodontist set the timing together.\n\nSurgery is not required for every jaw that looks a little uneven. The decision comes after the exam, the images, and a talk about what you expect.',
      ar: 'قد يصف التقويم الأسنان وحدها، ويبقى المضغ أو التنفس أو توازن الفك مشكلة. عندها يرتب الجراح وأخصائي التقويم الوقت معاً.\n\nليست كل فك غير متوازن قليلاً بحاجة إلى جراحة. القرار بعد الفحص والصورة والحديث عما تتوقعون.',
      zh: '有时正畸只能排齐牙齿，咀嚼、呼吸或颌骨平衡仍然有问题。这时外科医生和正畸医生会一起安排时间。\n\n并不是看起来有一点不对称的颌骨都需要手术。决定要在检查、影像和谈过您的期待之后做出。'
    },
    educationalNote
  },
  {
    slug: 'bone-graft-and-sinus-lift',
    title: {
      fa: 'پیوند استخوان و سینوس لیفت',
      en: 'Bone graft and sinus lift',
      ar: 'طعم العظم ورفع الجيب',
      zh: '植骨与上颌窦提升'
    },
    excerpt: {
      fa: 'وقتی استخوان فک برای نگه داشتن ایمپلنت کافی نیست.',
      en: 'When the jaw bone is not enough to hold an implant.',
      ar: 'حين لا يكفي عظم الفك لحمل الزراعة.',
      zh: '当颌骨不足以固定种植体时。'
    },
    category: { fa: 'ایمپلنت و بازسازی', en: 'Implants and rebuilding', ar: 'الزراعة وإعادة البناء', zh: '种植与重建' },
    categoryId: 'implant',
    publishedAt: '2026-09-30',
    readingTimeMinutes: 3,
    body: {
      fa: 'بعد کشیدن دندان، استخوان آن ناحیه می‌تواند کم شود. اگر حجم استخوان برای ایمپلنت کم باشد، گاهی اول پیوند انجام می‌شود و کاشت به بعد موکول می‌شود.\n\nسینوس لیفت برای فک بالا و وقتی کف سینوس پایین آمده است مطرح می‌شود. لازم بودن آن را عکس و معاینه می‌گوید، نه یک عنوان در سایت.',
      en: 'After a tooth is removed, the bone in that area can shrink. If there is not enough bone for an implant, a graft sometimes comes first and placement waits.\n\nA sinus lift is discussed for the upper jaw when the sinus floor sits low. Whether you need it comes from the images and the exam, not from a heading on a website.',
      ar: 'بعد قلع السن قد ينقص عظم تلك المنطقة. إذا لم يكفِ الحجم للزراعة، يُجرى الطعم أحياناً أولاً ويؤجَّل الغرس.\n\nرفع الجيب يُطرح للفك العلوي حين ينخفض قاع الجيب. الحاجة إليه تُعرف من الصورة والفحص.',
      zh: '拔牙之后，那一处的骨头可能会变少。如果骨量不够种种植体，有时要先植骨，种植往后放。\n\n上颌窦底偏低时，才会讨论上颌窦提升。是否需要，要看影像和检查，而不是看网页上的标题。'
    },
    educationalNote
  },
  {
    slug: 'jaw-joint-pain',
    title: {
      fa: 'درد مفصل فک',
      en: 'Jaw-joint pain',
      ar: 'ألم مفصل الفك',
      zh: '颞下颌关节疼痛'
    },
    excerpt: {
      fa: 'کلیک، درد جلوی گوش و قفل شدن دهان؛ چه وقت جدی است.',
      en: 'A click, pain in front of the ear, and a jaw that locks; when it is serious.',
      ar: 'طقطقة، وألم أمام الأذن، وفك ينقفل؛ متى يكون الأمر جاداً.',
      zh: '弹响、耳前疼痛、嘴巴锁住；什么时候需要重视。'
    },
    category: { fa: 'جراحی فک و مفصل', en: 'Jaw and joint', ar: 'الفك والمفصل', zh: '颌骨与关节' },
    categoryId: 'jaw',
    publishedAt: '2026-09-22',
    readingTimeMinutes: 3,
    body: {
      fa: 'صدای مفصل، به‌تنهایی، بیماری نیست. اگر درد، محدودیت باز شدن دهان یا قفل شدن تکرار شود، معاینه لازم است.\n\nبسیاری از افراد اول با محافظ شب، اصلاح عادت و فیزیوتراپی آرام می‌گیرند. جراحی مفصل گزینه آخر است و فقط وقتی روش‌های ساده‌تر جواب نداده‌اند مطرح می‌شود.',
      en: 'A joint sound, by itself, is not a disease. If pain, limited opening, or locking keeps returning, an exam is needed.\n\nMany people settle first with a night guard, habit changes, and gentle physiotherapy. Joint surgery is a last option, and it is discussed only when simpler care has not helped.',
      ar: 'صوت المفصل وحده ليس مرضاً. إذا تكرر الألم أو ضاق فتح الفم أو الانقفال، يلزم الفحص.\n\nكثير من الناس يتحسنون أولاً بواقية ليلية وتعديل العادة وعلاج طبيعي لطيف. جراحة المفصل خيار أخير.',
      zh: '关节有声音本身不是病。如果疼痛、张口受限或锁住反复出现，就需要检查。\n\n很多人先用夜间护具、改掉习惯和轻柔的物理治疗就能缓解。关节手术是最后的选择，只在较简单的方法没有帮助时才讨论。'
    },
    educationalNote
  },
  {
    slug: 'ten-points-for-easier-recovery',
    title: {
      fa: 'ده نکته برای راحت‌تر گذراندن روزهای بعد از جراحی',
      en: 'Ten plain points for the days after surgery',
      ar: 'عشر ملاحظات لأيام ما بعد الجراحة',
      zh: '手术后那几天的十件小事'
    },
    excerpt: {
      fa: 'خواب، غذا، بهداشت و کارهایی که دوره را سخت‌تر می‌کنند.',
      en: 'Sleep, food, hygiene, and the habits that make recovery harder.',
      ar: 'النوم والطعام والنظافة والعادات التي تصعّب الشفاء.',
      zh: '睡眠、饮食、卫生，以及会让恢复更难的习惯。'
    },
    category: { fa: 'مراقبت و بهبودی', en: 'Care and recovery', ar: 'العناية والشفاء', zh: '护理与恢复' },
    categoryId: 'recovery',
    publishedAt: '2026-09-11',
    readingTimeMinutes: 3,
    body: {
      fa: 'سر را کمی بالاتر بگذارید، غذای نرم و ولرم بخورید، سیگار را کنار بگذارید و محل جراحی را با زبان یا دست وارسی نکنید. داروها را همان‌طور که نوشته شده مصرف کنید، نه بیشتر.\n\nمسواک بقیه دهان را ادامه بدهید. اگر درد به‌جای کم شدن زیاد شد، یا طعم بد و تب آمد، همان را به مطب بگویید. این فهرست جای دستور بعد از عمل شما را نمی‌گیرد.',
      en: 'Keep your head a little raised, eat soft lukewarm food, leave cigarettes aside, and do not inspect the site with your tongue or fingers. Take medicines as written, not more.\n\nKeep brushing the rest of the mouth. If pain rises instead of settling, or a bad taste and fever appear, tell the clinic. This list does not replace your own after-care note.',
      ar: 'اجعلوا الرأس أعلى قليلاً، وكلوا طعاماً ليناً فاتراً، واتركوا التدخين، ولا تفحصوا مكان الجراحة باللسان أو الإصبع. خذوا الدواء كما كُتب لا أكثر.\n\nاستمروا في تفريش باقي الفم. إذا زاد الألم أو جاء طعم سيئ وحرارة، أخبروا العيادة. هذه القائمة لا تغني عن تعليماتكم.',
      zh: '头稍微垫高，吃温软的食物，先不要吸烟，也不要用舌头或手指去看手术的地方。药按写好的量吃，不要加。\n\n其余牙齿照常刷。如果疼痛不减反增，或出现异味和发热，请告诉诊室。这份清单不能代替发给您本人的术后说明。'
    },
    educationalNote
  }
];

export function getArticleBySlug(slug: string): ArticleStub | undefined {
  return articles.find((a) => a.slug === slug);
}
