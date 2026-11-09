<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function cms_public_ready(array $row): bool
{
    return (int) ($row['ai_draft'] ?? 0) !== 1 || (int) ($row['physician_review'] ?? 0) === 1;
}

function cms_visible_posts(string $kind, string $locale): array
{
    $rows = [];
    foreach (cms_published($kind) as $row) {
        if (cms_own($row, 'title', $locale) !== '' && cms_public_ready($row)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function cms_public(string $kind): void
{
    $locale = cms_locale((string) ($_GET['locale'] ?? 'fa'));
    $slug = isset($_GET['slug']) ? cms_slug((string) $_GET['slug']) : '';
    $dir = $locale === 'fa' || $locale === 'ar' ? 'rtl' : 'ltr';
    $other = $locale === 'fa' ? 'en' : 'fa';
    $path = $kind === 'doctor' ? 'column' : 'briefing';
    $origin = cms_origin();
    $fa = $locale === 'fa';
    $list = $slug === '' && cms_installed() ? cms_visible_posts($kind, $locale) : [];
    $post = [];
    if ($slug !== '' && cms_installed()) {
        $found = cms_published($kind, $slug);
        if ($found && cms_own($found, 'title', $locale) !== '' && cms_public_ready($found)) {
            $post = $found;
        }
    }
    $missing = $slug !== '' && !$post;
    if ($missing) {
        http_response_code(404);
    }

    $pageTitle = $kind === 'doctor'
        ? cms_line($locale, 'یادداشت‌های پزشک', "Surgeon's notes", 'ملاحظات الطبيب', '医生手记')
        : cms_line($locale, 'مرور منابع جهان', 'World brief', 'مصادر العالم', '世界文献');
    $pageDescription = $kind === 'doctor'
        ? cms_line($locale, 'یادداشت‌هایی که دکتر سید مهرشاد جعفری منتشر می‌کند. جایگزین معاینه نیست.', 'Notes published by Dr. Seyed Mehrshad Jafari. Not a substitute for a visit.', 'ملاحظات ينشرها الدكتور سيد مهرشاد جعفري. ليست بديلاً عن الفحص.', '这是塞义德·迈赫尔沙德·贾法里医生发布的笔记，不能代替面诊。')
        : cms_line($locale, 'خلاصه آموزشی منابع عمومی. تشخیص یا طرح درمان نیست.', 'Educational summaries of public sources. Not a diagnosis or a treatment plan.', 'ملخص تعليمي لمصادر عامة. ليس تشخيصاً ولا خطة علاج.', '公开资料的教学摘要。不是诊断，也不是治疗方案。');

    if (cms_installed()) {
        $seo = cms_db()->prepare('SELECT title, description FROM seo WHERE path = ? AND locale = ?');
        $seo->execute(['/' . $path . '/', $locale]);
        $seoRow = $seo->fetch();
        if ($seoRow) {
            if (trim((string) $seoRow['title']) !== '') {
                $pageTitle = trim((string) $seoRow['title']);
            }
            if (trim((string) $seoRow['description']) !== '') {
                $pageDescription = trim((string) $seoRow['description']);
            }
        }
    }

    $title = $pageTitle;
    $description = $pageDescription;
    $noindex = $missing;
    if ($post) {
        $ownTitle = cms_own($post, 'title', $locale);
        $ownSeo = cms_own($post, 'seo_title', $locale);
        $ownDescription = cms_own($post, 'seo_description', $locale);
        $title = ($ownSeo !== '' ? $ownSeo : $ownTitle) . ' | JAFARI';
        $description = $ownDescription !== '' ? $ownDescription : cms_own($post, 'excerpt', $locale);
        if ($description === '') {
            $description = $pageDescription;
        }
    }

    $canonical = $origin . '/' . $locale . '/' . $path . '/' . ($post ? $post['slug'] . '/' : '');
    $otherTitle = $post ? cms_own($post, 'title', $other) : 'list';
    $alternate = $origin . '/' . $other . '/' . $path . '/' . ($post ? $post['slug'] . '/' : '');
    $showAlternate = !$post || $otherTitle !== '';
    $cover = $post ? cms_media_path((string) ($post['cover'] ?? '')) : '';
    $sourceUrl = $post ? cms_https_url((string) ($post['source_url'] ?? '')) : '';
    $shareUrl = rawurlencode($canonical);
    $shareText = rawurlencode($title);

    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('X-Robots-Tag: ' . ($noindex ? 'noindex, follow' : 'index, follow'));
    ?>
<!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= e($dir) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= cms_google_meta() ?>
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <?php if ($noindex): ?><meta name="robots" content="noindex, follow"><?php endif; ?>
  <link rel="canonical" href="<?= e($canonical) ?>">
  <link rel="alternate" hreflang="<?= e($locale) ?>" href="<?= e($canonical) ?>">
  <?php if ($showAlternate): ?><link rel="alternate" hreflang="<?= e($other) ?>" href="<?= e($alternate) ?>"><?php endif; ?>
  <?php
    $defaultLocale = 'fa';
    if ($post && cms_own($post, 'title', 'fa') === '' && cms_own($post, 'title', 'en') !== '') {
        $defaultLocale = 'en';
    }
  ?>
  <link rel="alternate" hreflang="x-default" href="<?= e($origin . '/' . $defaultLocale . '/' . $path . '/' . ($post ? $post['slug'] . '/' : '')) ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:type" content="<?= $post ? 'article' : 'website' ?>">
  <meta property="og:locale" content="<?= e($locale === 'fa' ? 'fa_IR' : ($locale === 'ar' ? 'ar_AR' : ($locale === 'zh' ? 'zh_CN' : 'en_US'))) ?>">
  <meta property="og:image" content="<?= e($origin . ($cover !== '' ? $cover : '/visuals/doctor-portrait.webp')) ?>">
  <?php if ($cover === ''): ?>
  <meta property="og:image:width" content="1179">
  <meta property="og:image:height" content="1334">
  <?php endif; ?>
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="<?= e($origin . ($cover !== '' ? $cover : '/visuals/doctor-portrait.webp')) ?>">
  <link rel="stylesheet" href="/cms/blog.css">
  <?php if ($post): ?>
  <script type="application/ld+json"><?= json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'Article',
      'headline' => cms_own($post, 'title', $locale),
      'description' => $description,
      'inLanguage' => $locale,
      'datePublished' => $post['published_at'] ?: $post['updated_at'],
      'dateModified' => $post['updated_at'],
      'mainEntityOfPage' => $canonical,
      'author' => $kind === 'doctor'
          ? ['@type' => 'Person', 'name' => 'دکتر سید مهرشاد جعفری']
          : ['@type' => 'Organization', 'name' => 'JAFARI'],
      'publisher' => ['@type' => 'Organization', 'name' => 'JAFARI'],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <?php endif; ?>
  <?= cms_clinic_script($locale) ?>
</head>
<body>
<a class="skip" href="#content"><?= e(cms_line($locale, 'رفتن به مطلب', 'Skip to content', 'انتقل إلى النص', '跳到正文')) ?></a>
<header class="bar">
  <div class="bar-inner">
    <a class="brand" href="/<?= e($locale) ?>/">JAFARI</a>
    <nav class="nav" aria-label="JAFARI">
      <a href="/<?= e($locale) ?>/"><?= e(cms_line($locale, 'خانه', 'Home', 'الرئيسية', '首页')) ?></a>
      <a href="/<?= e($locale) ?>/column/"<?= $path === 'column' ? ' aria-current="page"' : '' ?>><?= e(cms_line($locale, 'یادداشت پزشک', 'Notes', 'ملاحظات الطبيب', '医生手记')) ?></a>
      <a href="/<?= e($locale) ?>/briefing/"<?= $path === 'briefing' ? ' aria-current="page"' : '' ?>><?= e(cms_line($locale, 'مرور جهان', 'World brief', 'مصادر العالم', '世界文献')) ?></a>
      <a href="/<?= e($locale) ?>/articles/"><?= e(cms_line($locale, 'مجله', 'Journal', 'المجلة', '杂志')) ?></a>
      <a href="/<?= e($locale) ?>/appointment/"><?= e(cms_line($locale, 'نوبت', 'Book', 'الموعد', '预约')) ?></a>
      <?php if ($showAlternate): ?><a href="<?= e($alternate) ?>"><?= $fa ? 'English' : 'فارسی' ?></a><?php endif; ?>
    </nav>
  </div>
</header>
<main id="content">
<?php if ($missing): ?>
  <section class="hero wrap">
    <p class="kicker">JAFARI</p>
    <h1><?= e(cms_line($locale, 'این مطلب منتشر نشده است', 'This piece is not published', 'هذه المادة غير منشورة', '这篇文章尚未发布')) ?></h1>
    <p class="lede"><?= e(cms_line($locale, 'یا هنوز برای این زبان نوشته نشده است.', 'It is unpublished, or this language has not been written.', 'أو أنها لم تُكتب بهذه اللغة بعد.', '或者还没有写成这个语言。')) ?></p>
  </section>
<?php elseif ($post): ?>
  <article class="article">
    <p class="kicker"><?= e((string) ($post['category'] ?? '') !== '' ? $post['category'] : ($kind === 'doctor' ? cms_line($locale, 'یادداشت پزشک', "Surgeon's note", 'ملاحظة الطبيب', '医生手记') : cms_line($locale, 'مرور منبع', 'Source brief', 'ملخص المصدر', '文献摘要'))) ?></p>
    <h1><?= e(cms_own($post, 'title', $locale)) ?></h1>
    <p class="meta"><?= e(cms_when((string) ($post['published_at'] ?: $post['updated_at']), $locale)) ?> · <?= (int) cms_minutes(cms_own($post, 'body', $locale)) ?> <?= e(cms_line($locale, 'دقیقه مطالعه', 'min read', 'دقائق قراءة', '分钟阅读')) ?></p>
    <?php if ($cover !== ''): ?><div class="article-cover"><img src="<?= e($cover) ?>" alt="<?= e((string) ($post['image_alt'] ?? '')) ?>"></div><?php endif; ?>
    <p class="note"><?php
      $sourced = (string) ($post['sources_json'] ?? '') !== '' && (string) ($post['sources_json'] ?? '') !== '[]';
      if ($kind === 'doctor' && $sourced) {
          echo e(cms_line($locale, 'متن آموزشی بر اساس چکیده‌های PubMed، بازبینی‌شده پیش از انتشار. جایگزین معاینه یا توصیه شخصی نیست.', 'Educational text based on PubMed abstracts and reviewed before publication. Not a visit and not personal advice.', 'نص تعليمي مبني على ملخصات PubMed، روجع قبل النشر. ليس بديلاً عن الفحص أو نصيحة شخصية.', '本文依据 PubMed 摘要编写，并在发布前经过审阅。不能代替面诊或个人建议。'));
      } elseif ($kind === 'doctor') {
          echo e(cms_line($locale, 'یادداشت آموزشی دکتر سید مهرشاد جعفری. جایگزین معاینه حضوری نیست.', 'An educational note by Dr. Seyed Mehrshad Jafari. It does not replace an in-person visit.', 'ملاحظة تعليمية للدكتور سيد مهرشاد جعفري. لا تغني عن الفحص الحضوري.', '这是塞义德·迈赫尔沙德·贾法里医生的教学笔记，不能代替面诊。'));
      } else {
          echo e(cms_line($locale, 'خلاصه آموزشی یک منبع عمومی است. نوشته پژوهشی دکتر نیست و تشخیص برای شما نیست.', 'An educational summary of a public source. Not the doctor’s research paper and not a diagnosis for you.', 'ملخص تعليمي لمصدر عام. ليس بحث الطبيب وليس تشخيصاً لك.', '这是一篇公开资料的教学摘要。不是医生的研究论文，也不是对您的诊断。'));
      }
    ?></p>
    <?php if ($kind === 'briefing' && $sourceUrl !== ''): ?>
      <p class="source"><?= e(cms_line($locale, 'منبع', 'Source', 'المصدر', '来源')) ?>: <a href="<?= e($sourceUrl) ?>" rel="noopener noreferrer"><?= e($post['source_name'] ?: $sourceUrl) ?></a></p>
    <?php endif; ?>
    <div class="body"><?= cms_rich(cms_own($post, 'body', $locale)) ?></div>
    <?php
      $sourceItems = json_decode((string) ($post['sources_json'] ?? ''), true);
      if ((int) ($post['physician_review'] ?? 0) === 1 && (string) ($post['reviewed_at'] ?? '') !== ''):
    ?>
      <p class="meta"><?= e(cms_line($locale, 'بازبینی پزشک پیش از انتشار: ', 'Physician review before publication: ', 'مراجعة الطبيب قبل النشر: ', '发布前的医师审阅：')) ?><?= e(substr((string) $post['reviewed_at'], 0, 10)) ?></p>
    <?php endif; ?>
    <?php if (is_array($sourceItems) && $sourceItems): ?>
      <section class="source">
        <h2><?= e(cms_line($locale, 'منابع', 'Sources', 'المصادر', '参考文献')) ?></h2>
        <ul>
          <?php foreach ($sourceItems as $source): if (!is_array($source) || empty($source['url'])) continue; ?>
            <li><a href="<?= e((string) $source['url']) ?>" rel="noopener noreferrer"><?= e((string) ($source['title'] ?? $source['url'])) ?></a>
              <?= e(trim((string) ($source['publisher'] ?? '') . ' · ' . (string) ($source['date'] ?? '') . ' · ' . (string) ($fa ? ($source['type'] ?? '') : ($source['type_en'] ?? $source['type'] ?? '')))) ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
    <div class="share">
      <button type="button" id="copy-link"><?= e(cms_line($locale, 'کپی پیوند', 'Copy link', 'نسخ الرابط', '复制链接')) ?></button>
      <a href="https://t.me/share/url?url=<?= e($shareUrl) ?>&text=<?= e($shareText) ?>"><?= e(cms_line($locale, 'تلگرام', 'Telegram', 'تيليغرام', 'Telegram')) ?></a>
      <a href="https://wa.me/?text=<?= e($shareText) ?>%20<?= e($shareUrl) ?>">WhatsApp</a>
    </div>
    <?php if ($kind === 'doctor'): ?>
      <aside class="author">
        <strong><?= e(cms_line($locale, 'دکتر سید مهرشاد جعفری', 'Dr. Seyed Mehrshad Jafari', 'الدكتور سيد مهرشاد جعفري', '塞义德·迈赫尔沙德·贾法里医生')) ?></strong>
        <p class="meta"><?= e(cms_line($locale, 'متخصص جراحی دهان، فک و صورت · نظام پزشکی ۱۴۱۱۳۰', 'Oral and maxillofacial surgeon · Medical council 141130', 'اختصاصي جراحة الفم والفك والوجه · رقم النقابة ۱۴۱۱۳۰', '口腔颌面外科医师 · 医委会编号 141130')) ?></p>
      </aside>
    <?php endif; ?>
  </article>
  <?php
    $related = array_slice(array_values(array_filter(
        cms_visible_posts($kind, $locale),
        static fn (array $item): bool => $item['slug'] !== $post['slug']
    )), 0, 3);
  ?>
  <?php if ($related): ?>
    <section class="related">
      <h2><?= e(cms_line($locale, 'ادامه مطالعه', 'Continue reading', 'تابع القراءة', '继续阅读')) ?></h2>
      <div class="grid">
        <?php foreach ($related as $item): ?>
          <a class="card" href="/<?= e($locale) ?>/<?= e($path) ?>/<?= e($item['slug']) ?>/">
            <div class="pad">
              <h2><?= e(cms_own($item, 'title', $locale)) ?></h2>
              <p class="excerpt"><?= e(cms_own($item, 'excerpt', $locale)) ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
  <script nonce="<?= e(cms_csp_nonce()) ?>">
    document.getElementById("copy-link")?.addEventListener("click", function () {
      navigator.clipboard?.writeText(location.href);
      this.textContent = <?= json_encode(cms_line($locale, 'کپی شد', 'Copied', 'تم النسخ', '已复制'), JSON_UNESCAPED_UNICODE) ?>;
    });
  </script>
<?php else: ?>
  <section class="hero wrap">
    <p class="kicker">JAFARI</p>
    <h1><?= e($pageTitle) ?></h1>
    <p class="lede"><?= e($pageDescription) ?></p>
  </section>
  <div class="wrap">
    <?php if (!$list): ?>
      <p class="empty"><?= e(cms_line($locale, 'هنوز مطلبی برای این زبان منتشر نشده است.', 'Nothing is published in this language yet.', 'لم يُنشر شيء بهذه اللغة بعد.', '这个语言还没有发布内容。')) ?></p>
    <?php else: ?>
      <?php $feature = null; foreach ($list as $item) { if ((int) ($item['featured'] ?? 0) === 1) { $feature = $item; break; } } $feature = $feature ?: $list[0]; ?>
      <article class="feature">
        <div class="cover">
          <?php $featureCover = cms_media_path((string) ($feature['cover'] ?? '')); ?>
          <?php if ($featureCover !== ''): ?><img src="<?= e($featureCover) ?>" alt=""><?php endif; ?>
        </div>
        <div class="copy">
          <span class="pill"><?= e((string) ($feature['category'] ?? '') !== '' ? $feature['category'] : cms_line($locale, 'منتخب', 'Featured', 'مختار', '精选')) ?></span>
          <h2><a class="title" href="/<?= e($locale) ?>/<?= e($path) ?>/<?= e($feature['slug']) ?>/"><?= e(cms_own($feature, 'title', $locale)) ?></a></h2>
          <p class="excerpt"><?= e(cms_own($feature, 'excerpt', $locale)) ?></p>
          <p class="meta"><?= e(cms_when((string) ($feature['published_at'] ?: $feature['updated_at']), $locale)) ?></p>
        </div>
      </article>
      <div class="grid">
        <?php foreach ($list as $item): if ($item['id'] === $feature['id']) continue; ?>
          <?php $cardCover = cms_media_path((string) ($item['cover'] ?? '')); ?>
          <a class="card" href="/<?= e($locale) ?>/<?= e($path) ?>/<?= e($item['slug']) ?>/">
            <?php if ($cardCover !== ''): ?><div class="cover"><img src="<?= e($cardCover) ?>" alt=""></div><?php endif; ?>
            <div class="pad">
              <?php if ((string) ($item['category'] ?? '') !== ''): ?><span class="pill"><?= e($item['category']) ?></span><?php endif; ?>
              <h2><?= e(cms_own($item, 'title', $locale)) ?></h2>
              <p class="excerpt"><?= e(cms_own($item, 'excerpt', $locale)) ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
</main>
<footer>
  <div class="wrap">
    <p><?= e(cms_line($locale, 'محتوای آموزشی — جایگزین تشخیص پزشکی نیست. شماره نظام پزشکی ۱۴۱۱۳۰.', 'Educational content. Not a medical diagnosis. Medical council ID 141130.', 'محتوى تعليمي، وليس تشخيصاً طبياً. رقم النقابة ۱۴۱۱۳۰.', '教学内容，不是医学诊断。医委会编号 141130。')) ?></p>
    <a href="/<?= e($locale) ?>/contact/"><?= e(cms_line($locale, 'تماس', 'Contact', 'اتصال', '联系')) ?></a>
  </div>
</footer>
</body>
</html>
<?php
}
