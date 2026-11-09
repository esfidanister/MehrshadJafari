<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$locale = cms_locale((string) ($_GET['locale'] ?? 'fa'));
$fa = $locale === 'fa';
$dir = $locale === 'fa' || $locale === 'ar' ? 'rtl' : 'ltr';
$other = $fa ? 'en' : 'fa';
$origin = cms_origin();
$rows = [];
$title = cms_line($locale, 'گالری تصاویر', 'Picture gallery', 'معرض الصور', '图片廊');
$description = cms_line(
    $locale,
    'تصویرهایی که مطب برای دیدن انتخاب کرده است. این صفحه تشخیص یا نمونه نتیجه درمان نیست.',
    'Pictures the clinic has chosen to show. This page is not a diagnosis or a promise of results.',
    'صور اختارتها العيادة للعرض. هذه الصفحة ليست تشخيصاً ولا وعداً بنتيجة علاج.',
    '这是诊所选择公开展示的图片。本页不是诊断，也不承诺治疗效果。'
);

if (in_array('sqlite', PDO::getAvailableDrivers(), true) && cms_installed()) {
    try {
        $rows = cms_db()->query('SELECT path, alt_fa, alt_en, caption_fa, caption_en, width, height FROM media WHERE show_gallery = 1 ORDER BY id DESC')->fetchAll();
        $seo = cms_db()->prepare('SELECT title, description FROM seo WHERE path = ? AND locale = ?');
        $seo->execute(['/gallery/', $locale]);
        $row = $seo->fetch();
        if ($row && trim((string) $row['title']) !== '') {
            $title = trim((string) $row['title']);
        }
        if ($row && trim((string) $row['description']) !== '') {
            $description = trim((string) $row['description']);
        }
    } catch (Throwable $ignored) {
        $rows = [];
    }
}

$frames = [];
foreach ($rows as $image) {
    $path = cms_media_path((string) $image['path']);
    if ($path === '') {
        continue;
    }
    $alt = trim((string) ($image[$fa ? 'alt_fa' : 'alt_en'] ?? ''));
    if ($alt === '') {
        $alt = trim((string) ($image[$fa ? 'alt_en' : 'alt_fa'] ?? ''));
    }
    $caption = trim((string) ($image[$fa ? 'caption_fa' : 'caption_en'] ?? ''));
    $width = (int) $image['width'];
    $height = (int) $image['height'];
    $frames[] = [
        'path' => $path,
        'alt' => $alt,
        'caption' => $caption,
        'width' => $width,
        'height' => $height,
        'wide' => $width > $height && $width > 0,
    ];
}

$canonical = $origin . '/' . $locale . '/gallery/';
$alternate = $origin . '/' . $other . '/gallery/';
$count = count($frames);
if ($count === 0) {
    header('X-Robots-Tag: noindex, follow');
}
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

$links = [
    ['href' => '/' . $locale . '/', 'label' => cms_line($locale, 'خانه', 'Home', 'الرئيسية', '首页'), 'current' => false],
    ['href' => '/' . $locale . '/gallery/', 'label' => cms_line($locale, 'گالری', 'Gallery', 'المعرض', '图库'), 'current' => true],
    ['href' => '/' . $locale . '/column/', 'label' => cms_line($locale, 'یادداشت پزشک', 'Notes', 'ملاحظات الطبيب', '医生手记'), 'current' => false],
    ['href' => '/' . $locale . '/appointment/', 'label' => cms_line($locale, 'نوبت', 'Book', 'الموعد', '预约'), 'current' => false],
    ['href' => '/' . $other . '/gallery/', 'label' => $fa ? 'English' : 'فارسی', 'current' => false],
];

function gallery_links(array $links): string
{
    $html = '';
    foreach ($links as $link) {
        $html .= '<a href="' . e($link['href']) . '"' . ($link['current'] ? ' aria-current="page"' : '') . '>' . e($link['label']) . '</a>';
    }
    return $html;
}
?>
<!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= e($dir) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= cms_google_meta() ?>
  <title><?= e($title) ?> | JAFARI</title>
  <meta name="description" content="<?= e($description) ?>">
  <?php if ($count === 0): ?><meta name="robots" content="noindex, follow"><?php endif; ?>
  <link rel="canonical" href="<?= e($canonical) ?>">
  <link rel="alternate" hreflang="<?= e($locale) ?>" href="<?= e($canonical) ?>">
  <link rel="alternate" hreflang="<?= e($other) ?>" href="<?= e($alternate) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= e($origin . '/fa/gallery/') ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= e($origin . '/visuals/doctor-portrait.webp') ?>">
  <meta property="og:image:width" content="1179">
  <meta property="og:image:height" content="1334">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="<?= e($origin . '/visuals/doctor-portrait.webp') ?>">
  <link rel="stylesheet" href="/cms/gallery.css">
  <?= cms_clinic_script($locale) ?>
</head>
<body>
<header class="bar">
  <div class="bar-inner">
    <a class="brand" href="/<?= e($locale) ?>/">
      <svg viewBox="0 0 28 28" aria-hidden="true"><path d="M5 7c0 8.2 3.6 14 9 14s9-5.8 9-14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="5" cy="6" r="1.6" fill="currentColor"/><circle cx="23" cy="6" r="1.6" fill="currentColor"/><circle cx="14" cy="24.2" r="1" class="gold"/></svg>
      <span>JAFARI</span>
    </a>
    <nav class="nav" aria-label="JAFARI"><?= gallery_links($links) ?></nav>
    <details class="drawer">
      <summary><?= e(cms_line($locale, 'فهرست', 'Menu', 'القائمة', '菜单')) ?></summary>
      <div class="drawer-links"><?= gallery_links($links) ?></div>
    </details>
  </div>
</header>
<main>
  <section class="hero wrap">
    <p class="kicker"><?= e(cms_line($locale, 'دیوار مطب', 'Clinic wall', 'جدار العيادة', '诊所墙')) ?></p>
    <h1><?= e($title) ?></h1>
    <p class="lede"><?= e($description) ?></p>
    <?php if ($count > 0): ?>
      <p class="count"><?= e((string) $count) ?> <?= e(cms_line($locale, 'تصویر انتخاب‌شده', $count === 1 ? 'selected picture' : 'selected pictures', 'صورة مختارة', '张已选图片')) ?></p>
    <?php endif; ?>
  </section>
  <section class="wrap">
    <?php if ($count === 0): ?>
      <div class="wall">
        <?php
          $model = cms_line($locale, 'مدل آموزشی', 'Teaching model', 'نموذج تعليمي', '教学模型');
          $portrait = cms_line($locale, 'پرتره', 'Portrait', 'الصورة', '肖像');
          $care = cms_line($locale, 'درمان', 'Treatment', 'العلاج', '治疗');
          $studio = [
              ['/visuals/jaw-studio.webp', cms_line($locale, 'مدل آموزشی فک', 'Educational jaw model', 'نموذج تعليمي للفك', '颌骨教学模型'), true, $model],
              ['/visuals/doctor-portrait.webp', cms_line($locale, 'پرتره پزشک', 'The surgeon’s portrait', 'صورة الطبيب', '医生肖像'), false, $portrait],
              ['/visuals/wisdom.webp', cms_line($locale, 'آموزش دندان عقل', 'Wisdom tooth teaching image', 'صورة تعليمية لضرس العقل', '智齿教学图'), false, $care],
              ['/visuals/implant.webp', cms_line($locale, 'آموزش ایمپلنت', 'Implant teaching image', 'صورة تعليمية للزراعة', '种植教学图'), true, $care],
              ['/visuals/orthognathic.webp', cms_line($locale, 'آموزش جراحی فک', 'Jaw surgery teaching image', 'صورة تعليمية لجراحة الفك', '颌骨手术教学图'), false, $care],
          ];
          foreach ($studio as $index => $frame):
        ?>
          <figure class="frame<?= $frame[2] ? ' wide' : '' ?>">
            <button type="button" data-full="<?= e($frame[0]) ?>" data-alt="<?= e($frame[1]) ?>" data-caption="<?= e($frame[1]) ?>">
              <img src="<?= e($frame[0]) ?>" alt="<?= e($frame[1]) ?>" loading="lazy" decoding="async">
              <span class="index"><?= e(sprintf('%02d', $index + 1)) ?></span>
            </button>
            <figcaption><?= e($frame[3]) ?> · <?= e($frame[1]) ?></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <p class="empty"><?= e(cms_line($locale, 'این قاب‌ها تصویر آموزشی همین سایت‌اند، نه عکس بیمار. عکسی که مطب در میز عمومی کند، جای آن‌ها را می‌گیرد.', 'These frames are teaching images from this website, not patient photos. A photo the clinic marks as public replaces them.', 'هذه الإطارات صور تعليمية من الموقع، وليست صور مرضى. الصورة العامة من المكتب تحل محلها.', '这些是本网站的教学图片，不是患者照片。诊所标为公开的照片会替换它们。')) ?></p>
    <?php else: ?>
      <div class="wall">
        <?php foreach ($frames as $index => $frame): ?>
          <figure class="frame<?= $frame['wide'] ? ' wide' : '' ?>">
            <button type="button" data-full="<?= e($frame['path']) ?>" data-alt="<?= e($frame['alt']) ?>" data-caption="<?= e($frame['caption']) ?>">
              <img src="<?= e($frame['path']) ?>" alt="<?= e($frame['alt']) ?>"<?php if ($frame['width'] > 0 && $frame['height'] > 0): ?> width="<?= $frame['width'] ?>" height="<?= $frame['height'] ?>"<?php endif; ?> loading="lazy" decoding="async">
              <span class="index"><?= e(sprintf('%02d', $index + 1)) ?></span>
            </button>
            <?php if ($frame['caption'] !== ''): ?><figcaption><?= e($frame['caption']) ?></figcaption><?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<footer>
  <div class="wrap">
    <p><?= e(cms_line($locale, 'محتوای آموزشی — جایگزین تشخیص پزشکی نیست. شماره نظام پزشکی ۱۴۱۱۳۰.', 'Educational content. Not a medical diagnosis. Medical council ID 141130.', 'محتوى تعليمي، وليس تشخيصاً طبياً. رقم النقابة ۱۴۱۱۳۰.', '教学内容，不是医学诊断。医委会编号 141130。')) ?></p>
    <a href="/<?= e($locale) ?>/contact/"><?= e(cms_line($locale, 'تماس', 'Contact', 'اتصال', '联系')) ?></a>
  </div>
</footer>
<dialog id="light" aria-label="<?= e(cms_line($locale, 'نمایش تصویر', 'Picture', 'عرض الصورة', '查看图片')) ?>">
  <div class="stage">
    <button type="button" id="light-close"><?= e(cms_line($locale, 'بستن', 'Close', 'إغلاق', '关闭')) ?></button>
    <img alt="">
    <p id="light-caption"></p>
  </div>
  <?php if ($count > 1): ?>
    <div class="light-nav">
      <button type="button" id="light-prev"><?= e(cms_line($locale, 'قبلی', 'Previous', 'السابق', '上一张')) ?></button>
      <button type="button" id="light-next"><?= e(cms_line($locale, 'بعدی', 'Next', 'التالي', '下一张')) ?></button>
    </div>
  <?php endif; ?>
</dialog>
<script nonce="<?= e(cms_csp_nonce()) ?>">
(function () {
  var dialog = document.getElementById("light");
  var image = dialog.querySelector("img");
  var caption = document.getElementById("light-caption");
  var buttons = Array.prototype.slice.call(document.querySelectorAll("[data-full]"));
  var index = 0;
  function show(next) {
    if (!buttons.length) return;
    index = (next + buttons.length) % buttons.length;
    var button = buttons[index];
    image.src = button.getAttribute("data-full");
    image.alt = button.getAttribute("data-alt") || "";
    caption.textContent = button.getAttribute("data-caption") || "";
    if (!dialog.open) dialog.showModal();
  }
  buttons.forEach(function (button, item) {
    button.addEventListener("click", function () { show(item); });
  });
  document.getElementById("light-close").addEventListener("click", function () { dialog.close(); });
  var prev = document.getElementById("light-prev");
  var next = document.getElementById("light-next");
  if (prev) prev.addEventListener("click", function () { show(index - 1); });
  if (next) next.addEventListener("click", function () { show(index + 1); });
  dialog.addEventListener("click", function (event) {
    if (event.target === dialog) dialog.close();
  });
  dialog.addEventListener("cancel", function () { dialog.close(); });
})();
</script>
</body>
</html>
