<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/registry.php';

$locale = cms_locale((string) ($_GET['locale'] ?? 'fa'));
$path = '/' . trim((string) ($_GET['path'] ?? ''), '/');
if ($path !== '/') {
    $path .= '/';
}
if (!preg_match('#^/[a-z0-9/_-]*$#', $path)) {
    http_response_code(400);
    exit;
}

$root = dirname(__DIR__);
$relative = '/' . $locale . ($path === '/' ? '/index.html' : rtrim($path, '/') . '/index.html');
$rootReal = realpath($root);
$file = realpath($root . $relative);
if (
    $rootReal === false
    || $file === false
    || !str_starts_with($file, $rootReal . DIRECTORY_SEPARATOR)
    || !is_file($file)
) {
    http_response_code(404);
    $missing = $root . DIRECTORY_SEPARATOR . '404.html';
    if (is_file($missing)) {
        $page = file_get_contents($missing);
        echo is_string($page) ? cms_csp_stamp($page) : '';
    }
    exit;
}

$html = file_get_contents($file);
if (!is_string($html)) {
    http_response_code(500);
    exit;
}

$text = [];
$images = [];
$links = [];
$seo = [];
$geo = '';
if (in_array('sqlite', PDO::getAvailableDrivers(), true) && cms_installed()) {
    try {
        $types = [];
        foreach (cms_registry() as $row) {
            $types[$row['key']] = $row['type'];
        }
        foreach (cms_slot_map($locale) as $key => $value) {
            if (!isset($types[$key])) {
                continue;
            }
            if ($types[$key] === 'image') {
                $safe = cms_media_path($value);
                if ($safe !== '') {
                    $images[$key] = $safe;
                }
            } elseif ($types[$key] === 'link') {
                $href = cms_safe_href($value);
                if ($href !== '') {
                    $links[$key] = $href;
                }
            } else {
                $text[$key] = $value;
            }
        }
        $seo = cms_seo_map($locale);
        $geo = cms_geo_block();
    } catch (Throwable $ignored) {
        $text = [];
        $images = [];
        $links = [];
        $seo = [];
        $geo = '';
    }
}

if ($text) {
    $html = cms_apply_slots($html, $text);
    $html = cms_apply_mails($html, $text);
}
if ($links) {
    $html = cms_apply_hrefs($html, $links);
}
if ($images) {
    $html = cms_apply_images($html, $images);
}

$pathname = '/' . $locale . ($path === '/' ? '/' : $path);
$entry = $seo[$pathname] ?? null;
if (is_array($entry)) {
    if (($entry['title'] ?? '') !== '') {
        $title = e($entry['title']);
        $html = preg_replace('/<title>[^<]*<\/title>/', '<title>' . $title . '</title>', $html, 1) ?? $html;
        $html = preg_replace('/<meta property="og:title" content="[^"]*"/', '<meta property="og:title" content="' . $title . '"', $html, 1) ?? $html;
        $twitterTitle = '<meta name="twitter:title" content="' . $title . '">';
        if (preg_match('/<meta name="twitter:title" content="/', $html)) {
            $html = preg_replace('/<meta name="twitter:title" content="[^"]*"\s*\/?>/', $twitterTitle, $html, 1) ?? $html;
        } else {
            $html = str_replace('</head>', $twitterTitle . '</head>', $html);
        }
    }
    if (($entry['description'] ?? '') !== '') {
        $description = e($entry['description']);
        $html = preg_replace('/<meta name="description" content="[^"]*"/', '<meta name="description" content="' . $description . '"', $html, 1) ?? $html;
        $html = preg_replace('/<meta property="og:description" content="[^"]*"/', '<meta property="og:description" content="' . $description . '"', $html, 1) ?? $html;
        $twitterDescription = '<meta name="twitter:description" content="' . $description . '">';
        if (preg_match('/<meta name="twitter:description" content="/', $html)) {
            $html = preg_replace('/<meta name="twitter:description" content="[^"]*"\s*\/?>/', $twitterDescription, $html, 1) ?? $html;
        } else {
            $html = str_replace('</head>', $twitterDescription . '</head>', $html);
        }
    }
    if (($entry['robots'] ?? '') === 'noindex') {
        $html = str_replace('</head>', '<meta name="robots" content="noindex, follow"></head>', $html);
    }
    $og = cms_media_path((string) ($entry['og'] ?? ''));
    if ($og !== '') {
        $absolute = e(cms_origin() . $og);
        $image = '<meta property="og:image" content="' . $absolute . '">';
        if (preg_match('/<meta property="og:image" content="/', $html)) {
            $html = preg_replace('/<meta property="og:image" content="[^"]*"\s*\/?>/', $image, $html, 1) ?? $html;
            $html = preg_replace('/<meta property="og:image:(?:width|height)" content="[^"]*"\s*\/?>\s*/', '', $html) ?? $html;
        } else {
            $html = str_replace('</head>', $image . '</head>', $html);
        }
        $twitter = '<meta name="twitter:image" content="' . $absolute . '">';
        if (preg_match('/<meta name="twitter:image" content="/', $html)) {
            $html = preg_replace('/<meta name="twitter:image" content="[^"]*"\s*\/?>/', $twitter, $html, 1) ?? $html;
        } else {
            $html = str_replace('</head>', $twitter . '</head>', $html);
        }
    }
}
$html = cms_put_clinic_schema($html, $locale, $text, $images, $links);
if ($geo !== '') {
    $html = str_replace('</head>', $geo . '</head>', $html);
}
$verify = cms_google_meta();
if ($verify !== '') {
    $html = str_replace('</head>', $verify . '</head>', $html);
}
$look = cms_look();
foreach (['motion', 'hero', 'story', 'articles'] as $key) {
    $html = preg_replace('/\bdata-' . $key . '="[^"]*"/', 'data-' . $key . '="' . $look[$key] . '"', $html, 1) ?? $html;
}
$slots = ['16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00'];
$booking = cms_installed() ? cms_booking_public($locale) : ['slots' => $slots, 'closedWeekdays' => [5], 'blocked' => [], 'capacity' => 1, 'horizon' => 90, 'leadDays' => 0, 'open' => true, 'cutoff' => 24, 'email' => 'optional', 'reason' => 'optional', 'busy' => [], 'kinds' => [], 'topics' => []];
$deskJson = json_encode($booking, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$html = str_replace('</head>', '<meta name="jafari-appearance" content="' . e($look['token']) . '"><script type="application/json" id="jafari-booking">' . $deskJson . '</script></head>', $html);
$orderJson = json_encode(['home' => $look['home'], 'hide' => $look['hide']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$lookScript = '<script id="cms-look">(function(){var d=' . $orderJson . ';function apply(){document.querySelectorAll("[data-home-section]").forEach(function(el){var id=el.getAttribute("data-home-section");var i=d.home.indexOf(id);var order=i>=0?String(i+1):"40";if(el.style.order!==order)el.style.order=order;var hide=id!=="hero"&&d.hide.indexOf(id)>=0;if(el.hidden!==hide)el.hidden=hide;});}apply();new MutationObserver(apply).observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:["hidden","style"]});})();</script>';
$html = str_replace('</body>', $lookScript . '</body>', $html);

if ($text || $images || $links || $seo) {
    $json = json_encode(
        ['text' => $text, 'images' => $images, 'links' => $links, 'seo' => $seo],
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    $script = '<script id="cms-live">(function(){var d=' . $json . ';var busy=false;function apply(){if(busy)return;busy=true;try{document.querySelectorAll("[data-cms]").forEach(function(el){var v=d.text[el.getAttribute("data-cms")];if(typeof v==="string"&&el.textContent!==v)el.textContent=v;});document.querySelectorAll("[data-cms-src]").forEach(function(el){var v=d.images[el.getAttribute("data-cms-src")];if(typeof v==="string"&&el.getAttribute("src")!==v){el.setAttribute("src",v);el.removeAttribute("srcset");}});document.querySelectorAll("[data-cms-href]").forEach(function(el){var v=d.links&&d.links[el.getAttribute("data-cms-href")];if(typeof v==="string"&&v){if(el.getAttribute("href")!==v)el.setAttribute("href",v);el.removeAttribute("hidden");}});document.querySelectorAll("[data-cms-mail]").forEach(function(el){var v=d.text[el.getAttribute("data-cms-mail")];if(typeof v==="string"&&/^[A-Za-z0-9._%+\\-]+@[A-Za-z0-9.\\-]+\\.[A-Za-z]{2,}$/.test(v)){var h="mailto:"+v;if(el.getAttribute("href")!==h)el.setAttribute("href",h);}});var path=location.pathname.endsWith("/")?location.pathname:location.pathname+"/";var seo=d.seo[path];if(seo&&seo.title)document.title=seo.title;if(seo&&seo.description){var m=document.querySelector(\'meta[name="description"]\');if(m)m.setAttribute("content",seo.description);}}finally{busy=false;}}apply();new MutationObserver(apply).observe(document.documentElement,{childList:true,subtree:true});})();</script>';
    $html = str_replace('</body>', $script . '</body>', $html);
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
echo cms_csp_stamp($html);
