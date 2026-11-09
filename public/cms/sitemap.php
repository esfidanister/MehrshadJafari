<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');
$origin = cms_origin();
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
if (cms_installed()) {
    $gallery = (int) cms_db()->query('SELECT COUNT(*) FROM media WHERE show_gallery = 1')->fetchColumn();
    if ($gallery > 0) {
        foreach (['fa', 'en', 'ar', 'zh'] as $locale) {
            echo '  <url><loc>' . e($origin . '/' . $locale . '/gallery/') . "</loc></url>\n";
        }
    }
    foreach (['column', 'briefing'] as $path) {
        foreach (['fa', 'en', 'ar', 'zh'] as $locale) {
            $loc = $origin . '/' . $locale . '/' . $path . '/';
            echo '  <url><loc>' . e($loc) . "</loc></url>\n";
        }
    }
    $stmt = cms_db()->query("SELECT kind, slug, updated_at, title_fa, title_en, ai_draft, physician_review FROM posts WHERE status = 'published' ORDER BY updated_at DESC");
    foreach ($stmt as $row) {
        if ((int) ($row['ai_draft'] ?? 0) === 1 && (int) ($row['physician_review'] ?? 0) !== 1) {
            continue;
        }
        $path = $row['kind'] === 'doctor' ? 'column' : 'briefing';
        $last = substr((string) $row['updated_at'], 0, 10);
        foreach (['fa', 'en'] as $locale) {
            if (trim((string) $row['title_' . $locale]) === '') {
                continue;
            }
            $loc = $origin . '/' . $locale . '/' . $path . '/' . $row['slug'] . '/';
            echo '  <url><loc>' . e($loc) . '</loc><lastmod>' . e($last) . "</lastmod></url>\n";
        }
    }
}
echo "</urlset>\n";
