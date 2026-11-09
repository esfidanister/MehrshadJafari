<?php
declare(strict_types=1);

function cms_studio_topics(): array
{
    return [
        ['دندان عقل', 'چه زمانی درباره دندان عقل نهفته با پزشک صحبت می‌شود'],
        ['دندان عقل', 'مراقبت روزهای بعد از جراحی دندان عقل'],
        ['ایمپلنت', 'ارزیابی استخوان و لثه پیش از ایمپلنت، به زبان ساده'],
        ['ایمپلنت', 'مراقبت هفته اول بعد از کاشت ایمپلنت'],
        ['ارتوگناتیک', 'جراحی فک و نقش ارتودنسی در یک مسیر درمانی'],
        ['پاتولوژی دهان', 'زخم دهانی که خوب نمی‌شود؛ چه زمانی معاینه لازم است'],
        ['آموزش عمومی', 'بی‌حسی دندان و مراقبت بعد از جراحی دهان'],
        ['آموزش عمومی', 'پرسش‌هایی که در ویزیت اول جراحی فک مفید است'],
    ];
}

function cms_studio_pages(): array
{
    return [
        ['دندان عقل', 'wisdom', '/services/wisdom-tooth-surgery/', 'جراحی دندان عقل', 'Wisdom tooth surgery'],
        ['ایمپلنت', 'implant', '/services/dental-implants/', 'ایمپلنت دندانی', 'Dental implants'],
        ['فک', 'jaw', '/services/orthognathic-surgery/', 'جراحی فک', 'Jaw surgery'],
        ['ارتودنسی', 'orthodontic', '/services/orthognathic-surgery/', 'جراحی فک', 'Jaw surgery'],
        ['زخم', 'ulcer', '/services/oral-pathology/', 'پاتولوژی دهان', 'Oral pathology'],
        ['ضایعه', 'lesion', '/services/oral-pathology/', 'پاتولوژی دهان', 'Oral pathology'],
        ['توده', 'lump', '/services/oral-pathology/', 'پاتولوژی دهان', 'Oral pathology'],
        ['آناتومی', 'anatomy', '/anatomy/', 'راهنمای آموزشی فک', 'Jaw anatomy guide'],
        ['عصب', 'nerve', '/anatomy/', 'راهنمای آموزشی فک', 'Jaw anatomy guide'],
    ];
}

function cms_pubmed_ids(string $term, int $max): array
{
    $url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&retmode=json&retmax='
        . $max . '&sort=relevance&tool=JafariClinicDesk&term=' . rawurlencode($term);
    $response = cms_http($url, null, [], 15);
    if (!$response['ok']) {
        return ['ok' => false, 'ids' => [], 'error' => 'PubMed الان پاسخ نداد. متن شما نگه داشته شد و مقاله‌ای ذخیره نشد.'];
    }
    $data = json_decode($response['body'], true);
    $ids = $data['esearchresult']['idlist'] ?? [];
    if (!is_array($ids)) {
        return ['ok' => false, 'ids' => [], 'error' => 'پاسخ PubMed قابل خواندن نبود. مقاله‌ای ذخیره نشد.'];
    }
    $clean = [];
    foreach ($ids as $id) {
        $id = (string) $id;
        if (preg_match('/^\d{1,12}$/', $id)) {
            $clean[] = $id;
        }
    }
    return ['ok' => true, 'ids' => $clean, 'error' => ''];
}

function cms_studio_query(string $topic, string $manual): array
{
    $manual = trim($manual);
    if ($manual !== '' && preg_match('/[A-Za-z]{3}/', $manual)) {
        return ['ok' => true, 'query' => cms_plain($manual, 160), 'error' => ''];
    }
    $result = cms_ai_complete([
        ['role' => 'system', 'content' => 'Turn a clinic topic into one PubMed search. JSON key query: 4 to 8 English words, no operators, no claims, no statistics.'],
        ['role' => 'user', 'content' => $topic],
    ], 0.2, true, 20, 200);
    if (!$result['ok']) {
        return ['ok' => false, 'query' => '', 'error' => 'عبارت انگلیسی ساخته نشد. ' . $result['error']];
    }
    $data = $result['data'];
    $query = trim((string) ($data['query'] ?? ''));
    if (!preg_match('/[A-Za-z]{3}/', $query)) {
        return ['ok' => false, 'query' => '', 'error' => 'عبارت انگلیسی جست‌وجو ساخته نشد. آن را خودتان در فرم بنویسید.'];
    }
    return ['ok' => true, 'query' => cms_plain($query, 160), 'error' => ''];
}

function cms_pubmed_sources(string $query): array
{
    $query = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
    if (mb_strlen($query) < 3) {
        return ['ok' => false, 'error' => 'موضوع را حداقل با سه نویسه بنویسید.', 'items' => [], 'note' => ''];
    }
    $first = cms_pubmed_ids($query . ' AND (guideline[pt] OR review[pt] OR systematic[sb])', 5);
    if (!$first['ok']) {
        return ['ok' => false, 'error' => $first['error'], 'items' => [], 'note' => ''];
    }
    $ids = $first['ids'];
    $broad = false;
    if (count($ids) < 2) {
        $second = cms_pubmed_ids($query, 5);
        if (!$second['ok'] && !$ids) {
            return ['ok' => false, 'error' => $second['error'], 'items' => [], 'note' => ''];
        }
        if ($second['ok'] && count($second['ids']) > count($ids)) {
            $ids = $second['ids'];
            $broad = true;
        }
    }
    if (!$ids) {
        return ['ok' => false, 'error' => 'در PubMed منبعی برای این موضوع پیدا نشد. مقاله ساخته نشد تا ادعای بدون منبع ساخته نشود.', 'items' => [], 'note' => ''];
    }
    $response = cms_http(
        'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&retmode=xml&tool=JafariClinicDesk&id=' . implode(',', $ids),
        null,
        [],
        20
    );
    if (!$response['ok'] || $response['body'] === '') {
        return ['ok' => false, 'error' => 'PubMed الان پاسخ نداد. متن شما نگه داشته شد و مقاله‌ای ذخیره نشد.', 'items' => [], 'note' => ''];
    }
    if (!function_exists('simplexml_load_string')) {
        return ['ok' => false, 'error' => 'افزونه simplexml در PHP خاموش است. در Select PHP Version آن را روشن کنید. مقاله‌ای ذخیره نشد.', 'items' => [], 'note' => ''];
    }
    $xml = @simplexml_load_string($response['body'], 'SimpleXMLElement', LIBXML_NONET);
    if ($xml === false) {
        return ['ok' => false, 'error' => 'پاسخ PubMed قابل خواندن نبود. مقاله‌ای ذخیره نشد.', 'items' => [], 'note' => ''];
    }
    $items = [];
    foreach ($xml->PubmedArticle as $article) {
        $cite = $article->MedlineCitation ?? null;
        if ($cite === null) {
            continue;
        }
        $pmid = trim((string) $cite->PMID);
        $title = trim((string) $cite->Article->ArticleTitle);
        if (!preg_match('/^\d{1,12}$/', $pmid) || $title === '') {
            continue;
        }
        $bits = [];
        foreach ($cite->Article->Abstract->AbstractText ?? [] as $node) {
            $label = trim((string) $node['Label']);
            $text = trim((string) $node);
            if ($text !== '') {
                $bits[] = ($label !== '' ? $label . ': ' : '') . $text;
            }
        }
        $abstract = trim(implode(' ', $bits));
        if (mb_strlen($abstract) > 900) {
            $abstract = mb_substr($abstract, 0, 900) . '…';
        }
        $types = [];
        foreach ($cite->Article->PublicationTypeList->PublicationType ?? [] as $node) {
            $types[] = trim((string) $node);
        }
        $kind = 'مقاله';
        $joined = implode(' ', $types);
        if (stripos($joined, 'Guideline') !== false) {
            $kind = 'راهنمای بالینی';
        } elseif (stripos($joined, 'Review') !== false) {
            $kind = 'مرور';
        }
        $year = trim((string) $cite->Article->Journal->JournalIssue->PubDate->Year);
        if ($year === '') {
            $year = trim((string) $cite->Article->Journal->JournalIssue->PubDate->MedlineDate);
        }
        $items[] = [
            'pmid' => $pmid,
            'title' => $title,
            'publisher' => trim((string) $cite->Article->Journal->Title),
            'date' => $year,
            'url' => 'https://pubmed.ncbi.nlm.nih.gov/' . $pmid . '/',
            'type' => $kind,
            'type_en' => $kind === 'راهنمای بالینی' ? 'Guideline' : ($kind === 'مرور' ? 'Review' : 'Article'),
            'abstract' => $abstract,
        ];
    }
    if (count($items) < 2) {
        return ['ok' => false, 'error' => 'کمتر از دو منبع قابل‌بررسی پیدا شد. پیش‌نویس ساخته نشد.', 'items' => $items, 'note' => ''];
    }
    $note = 'جست‌وجوی PubMed در ' . gmdate('Y-m-d') . ' برای «' . $query . '».';
    if ($broad) {
        $note .= ' راهنمای بالینی کافی در نتایج اول نبود؛ مرورها و مقالات مرتبط آمده‌اند.';
    }
    if (count($items) < 3) {
        $note .= ' تعداد منبع کم است و نباید از آن نتیجه قطعی ساخته شود.';
    }
    return ['ok' => true, 'error' => '', 'items' => $items, 'note' => $note];
}

function cms_similar_posts(string $topic): array
{
    $topic = trim($topic);
    if (mb_strlen($topic) < 4) {
        return [];
    }
    $needle = mb_strtolower($topic);
    $found = [];
    foreach (cms_db()->query('SELECT id, kind, title_fa, title_en, status, slug FROM posts ORDER BY id DESC LIMIT 80') as $row) {
        $title = mb_strtolower(trim($row['title_fa'] . ' ' . $row['title_en']));
        if ($title === '') {
            continue;
        }
        if (mb_strpos($title, $needle) !== false || (mb_strlen($row['title_fa']) >= 12 && mb_strpos($needle, mb_strtolower($row['title_fa'])) !== false)) {
            $found[] = $row;
        }
    }
    return array_slice($found, 0, 5);
}

function cms_jafari_image_prompt(string $topic): string
{
    $topic = trim(preg_replace('/\s+/u', ' ', $topic) ?? $topic);
    return 'Authentic clinical-luxury photograph for the website of Dr. Seyed Mehrshad Jafari, oral and maxillofacial surgeon (export 1920×1080): a quiet ivory studio still life that illustrates the educational subject «' . $topic . '» without showing a patient, a face, a smile transformation, blood, an open surgical field, or a before-and-after result. Use a real jaw model, dental stone cast, or a gloved hand only as an anonymous detail; soft daylight, medical blue #397F91 and warm stone #C6AA86 as small accents, tack-sharp subject, shallow depth of field. No diagnosis, no promise of outcome, no other clinic name. A discreet mark «JAFARI» with small Persian «جعفری» sits in the far bottom corner, about 3 percent of the frame, never over the subject. Native 16:9, web-ready, no collage and no plastic skin.';
}

function cms_studio_links(string $text, string $locale = 'fa'): string
{
    $lines = [];
    $seen = [];
    $needle = $locale === 'en' ? mb_strtolower($text) : $text;
    foreach (cms_studio_pages() as $page) {
        $path = ($locale === 'en' ? '/en' : '/fa') . $page[2];
        $word = $locale === 'en' ? $page[1] : $page[0];
        $label = $locale === 'en' ? $page[4] : $page[3];
        if (isset($seen[$path]) || ($locale === 'en' ? !str_contains($needle, $word) : mb_strpos($text, $word) === false)) {
            continue;
        }
        $seen[$path] = true;
        $lines[] = '- [' . $label . '](' . $path . ')';
    }
    if (!$lines) {
        return '';
    }
    $heading = $locale === 'en' ? 'On this website' : 'مطالعه در همین سایت';
    return "\n\n## " . $heading . "\n" . implode("\n", $lines);
}

function cms_studio_write(string $topic, string $title, string $lang, string $audience, string $length, array $sources, string $note): array
{
    $pack = [];
    foreach ($sources as $source) {
        $pack[] = 'PMID ' . $source['pmid'] . ' | ' . $source['type'] . ' | ' . $source['date'] . ' | ' . $source['publisher'] . "\n" . $source['title'] . "\n" . $source['abstract'] . "\n" . $source['url'];
    }
    $words = ['short' => 'about 700 Persian words', 'medium' => 'about 1100 Persian words', 'long' => 'about 1500 Persian words'][$length] ?? 'about 1100 Persian words';
    $languages = $lang === 'en' ? 'Write the full article in English. Leave Persian fields empty.' : ($lang === 'both' ? 'Write a full Persian article and a full English article from the same sources.' : 'Write the full article in Persian. Also provide an English title and a short English excerpt for the address, and leave the English body empty.');
    $result = cms_ai_complete([
        [
            'role' => 'system',
            'content' => 'You draft patient-education articles for an oral and maxillofacial surgery website. Use only the supplied PubMed abstracts. Do not invent statistics, doses, cure rates, quotes, authors, or sources. Do not say the surgeon personally reviewed this draft. Do not promise search ranking, a treatment outcome, or a personal plan. If the abstracts disagree or are too thin, say so in limits and avoid a firm conclusion. Answer the topic in the opening. Use plain language, natural wording, and only sections the abstracts support, such as causes, symptoms, diagnosis, treatment options, prevention, when to see a surgeon, and warnings. Add a short FAQ only from those abstracts. JSON keys: title_fa, title_en, excerpt_fa, excerpt_en, body_fa, body_en, seo_title_fa, seo_title_en, seo_description_fa, seo_description_en, slug, category, limits. SEO titles stay near 60 characters and descriptions near 150. Bodies use blank lines between paragraphs and ## headings. Cite a sentence with (PMID 123) only when that PMID is supplied. End by saying this is education, not a diagnosis.',
        ],
        [
            'role' => 'user',
            'content' => "Topic: {$topic}\nSuggested title: {$title}\nAudience: {$audience}\nLength: {$words}\nLanguage: {$languages}\nSearch note: {$note}\n\nSources:\n" . implode("\n\n", $pack),
        ],
    ], 0.2, true, 40, 4000);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'data' => [], 'model' => ''];
    }
    $data = $result['data'];
    if (!is_array($data) || (trim((string) ($data['title_fa'] ?? '')) === '' && trim((string) ($data['title_en'] ?? '')) === '')) {
        return ['ok' => false, 'error' => 'پاسخ هوش مصنوعی عنوان قابل استفاده نداشت. مقاله‌ای ذخیره نشد.', 'data' => []];
    }
    $allowed = [];
    foreach ($sources as $source) {
        $allowed[$source['pmid']] = true;
    }
    foreach (['body_fa', 'body_en'] as $field) {
        $body = (string) ($data[$field] ?? '');
        $body = preg_replace_callback('/PMID\s*(\d{1,12})/', static function (array $match) use ($allowed): string {
            return isset($allowed[$match[1]]) ? 'PMID ' . $match[1] : '';
        }, $body) ?? $body;
        $data[$field] = trim($body);
    }
    return ['ok' => true, 'error' => '', 'data' => $data, 'model' => $result['model']];
}

function cms_studio_store(string $topic, array $input, array $sources, string $note, array $data): int
{
    $linksFa = cms_studio_links($topic . ' ' . (string) ($data['body_fa'] ?? ''), 'fa');
    $linksEn = cms_studio_links((string) ($data['body_en'] ?? ''), 'en');
    $limits = trim((string) ($data['limits'] ?? ''));
    if ($limits !== '') {
        $note .= ' محدودیت: ' . $limits;
    }
    $lang = (string) ($input['lang'] ?? 'fa');
    $slug = cms_slug((string) ($data['slug'] ?? ''));
    if ($slug === '') {
        $slug = cms_slug((string) ($data['title_en'] ?? '')) ?: ('note-' . date('YmdHis'));
    }
    if ($lang === 'fa') {
        $data['title_en'] = $data['excerpt_en'] = $data['body_en'] = $data['seo_title_en'] = $data['seo_description_en'] = '';
    } elseif ($lang === 'en') {
        $data['title_fa'] = $data['excerpt_fa'] = $data['body_fa'] = $data['seo_title_fa'] = $data['seo_description_fa'] = '';
    }
    $bodyFa = trim((string) ($data['body_fa'] ?? ''));
    $bodyEn = trim((string) ($data['body_en'] ?? ''));
    if ($bodyFa !== '' && mb_strpos($bodyFa, 'جایگزین معاینه') === false) {
        $bodyFa .= "\n\nاین متن آموزشی است و جایگزین معاینه یا طرح درمان شخصی نیست.";
    }
    if ($bodyEn !== '' && stripos($bodyEn, 'not a diagnosis') === false) {
        $bodyEn .= "\n\nThis is educational and is not a diagnosis or a personal treatment plan.";
    }
    if ($bodyFa !== '') {
        $bodyFa .= $linksFa;
    }
    if ($bodyEn !== '') {
        $bodyEn .= $linksEn;
    }
    $needsFa = $lang !== 'en';
    $needsEn = $lang !== 'fa';
    if (($needsFa && ($bodyFa === '' || trim((string) ($data['title_fa'] ?? '')) === '')) || ($needsEn && ($bodyEn === '' || trim((string) ($data['title_en'] ?? '')) === ''))) {
        return 0;
    }
    $base = $slug;
    $n = 2;
    $check = cms_db()->prepare('SELECT id FROM posts WHERE kind = ? AND slug = ?');
    while (true) {
        $check->execute(['doctor', $slug]);
        if (!$check->fetch()) {
            break;
        }
        $slug = $base . '-' . $n;
        $n++;
    }
    $now = cms_now();
    $stmt = cms_db()->prepare('INSERT INTO posts (kind, slug, status, title_fa, title_en, excerpt_fa, excerpt_en, body_fa, body_en, seo_title_fa, seo_title_en, seo_description_fa, seo_description_en, source_name, source_url, source_date, category, created_at, updated_at, sources_json, image_prompt, image_alt, studio_note, search_query, ai_draft, physician_review, reviewed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        'doctor',
        $slug,
        'draft',
        cms_plain((string) ($data['title_fa'] ?? ''), 180),
        cms_plain((string) ($data['title_en'] ?? ''), 180),
        cms_plain((string) ($data['excerpt_fa'] ?? ''), 400),
        cms_plain((string) ($data['excerpt_en'] ?? ''), 400),
        cms_plain($bodyFa, 20000),
        cms_plain($bodyEn, 20000),
        cms_plain((string) ($data['seo_title_fa'] ?? ''), 180),
        cms_plain((string) ($data['seo_title_en'] ?? ''), 180),
        cms_plain((string) ($data['seo_description_fa'] ?? ''), 300),
        cms_plain((string) ($data['seo_description_en'] ?? ''), 300),
        'PubMed',
        (string) ($sources[0]['url'] ?? ''),
        gmdate('Y-m-d'),
        cms_plain((string) ($input['category'] !== '' ? $input['category'] : ($data['category'] ?? '')), 80),
        $now,
        $now,
        json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        cms_jafari_image_prompt($topic),
        cms_plain((string) (($data['title_fa'] ?? '') !== '' ? $data['title_fa'] : $topic), 180),
        cms_plain($note, 1000),
        cms_plain((string) ($input['query'] ?? $topic), 180),
        1,
        0,
        '',
    ]);
    return (int) cms_db()->lastInsertId();
}
