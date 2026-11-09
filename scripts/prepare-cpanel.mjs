#!/usr/bin/env node
/**
 * Post-build step for cPanel / Apache static hosting.
 *
 * Run after `next build` (output: "export" -> ./out):
 *   1. Writes out/index.html -> redirect to /fa/
 *   2. Copies public/.htaccess -> out/.htaccess
 *   3. Writes out/robots.txt
 *   4. Generates out/sitemap.xml from every exported page (with hreflang)
 *
 * Site URL: NEXT_PUBLIC_SITE_URL (or SITE_URL), default https://drmehrshadjafari.ir
 */
import { execFileSync } from "node:child_process";
import { copyFileSync, existsSync, mkdirSync, readdirSync, rmSync, statSync, unlinkSync, writeFileSync } from "node:fs";
import { dirname, join, relative, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const outDir = join(root, "out");
const publicDir = join(root, "public");

const DEFAULT_LOCALE = "fa";
const LOCALES = ["fa", "en", "ar", "zh"];
const SITE_URL = (
  process.env.NEXT_PUBLIC_SITE_URL ||
  process.env.SITE_URL ||
  "https://drmehrshadjafari.ir"
).replace(/\/+$/, "");

if (!existsSync(outDir)) {
  console.error("[prepare-cpanel] ./out not found. Run `next build` first.");
  process.exit(1);
}

// 1. Root redirect ------------------------------------------------------------
const target = `/${DEFAULT_LOCALE}/`;
const indexHtml = `<!doctype html>
<html lang="${DEFAULT_LOCALE}" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>JAFARI</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, follow">
  <meta http-equiv="refresh" content="0; url=${target}">
  <link rel="canonical" href="${SITE_URL}${target}">
  <script>window.location.replace(${JSON.stringify(target)});</script>
</head>
<body>
  <p><a href="${target}">JAFARI &rarr; ${target}</a></p>
</body>
</html>
`;
writeFileSync(join(outDir, "index.html"), indexHtml);
console.log("[prepare-cpanel] out/index.html -> redirect to " + target);

// 2. .htaccess ----------------------------------------------------------------
const htaccessSrc = join(publicDir, ".htaccess");
if (existsSync(htaccessSrc)) {
  copyFileSync(htaccessSrc, join(outDir, ".htaccess"));
  console.log("[prepare-cpanel] copied public/.htaccess -> out/.htaccess");
} else {
  console.warn("[prepare-cpanel] public/.htaccess missing - skipped");
}

// 3. robots.txt ---------------------------------------------------------------
writeFileSync(
  join(outDir, "robots.txt"),
  `User-agent: *\nAllow: /\nDisallow: /manage/\nDisallow: /cms/data/\n\nSitemap: ${SITE_URL}/sitemap.xml\nSitemap: ${SITE_URL}/cms/sitemap.php\n`,
);
console.log("[prepare-cpanel] out/robots.txt written");

// 3b. Flatten RSC prefetch payloads ----------------------------------------------
// Builds made on Windows can emit `__next.$d$locale/services/__PAGE__.txt`, but
// the client requests `__next.$d$locale.services.__PAGE__.txt`. Copy to the
// dotted name so link prefetching doesn't 404 (no-op when already flattened).
function flattenRscPayloads(dir) {
  let copied = 0;
  for (const name of readdirSync(dir)) {
    const full = join(dir, name);
    if (!statSync(full).isDirectory()) continue;
    if (name === "_next") continue;
    if (name.startsWith("__next.")) {
      const walk = (current, segments) => {
        for (const child of readdirSync(current)) {
          const childPath = join(current, child);
          if (statSync(childPath).isDirectory()) {
            walk(childPath, [...segments, child]);
          } else {
            const flat = join(dir, [name, ...segments, child].join("."));
            if (!existsSync(flat)) {
              copyFileSync(childPath, flat);
              copied++;
            }
          }
        }
      };
      walk(full, []);
    } else {
      copied += flattenRscPayloads(full);
    }
  }
  return copied;
}
console.log(
  `[prepare-cpanel] flattened ${flattenRscPayloads(outDir)} RSC prefetch payload(s)`,
);

// 4. sitemap.xml --------------------------------------------------------------
function collectPages(dir, pages = []) {
  for (const name of readdirSync(dir)) {
    if (name === "_next" || name.startsWith(".")) continue;
    const full = join(dir, name);
    if (statSync(full).isDirectory()) {
      collectPages(full, pages);
    } else if (name === "index.html") {
      pages.push(relative(outDir, dir).split(sep).join("/"));
    }
  }
  return pages;
}

// "fa/services/dental-implants" -> { locale: "fa", path: "services/dental-implants" }
const entries = collectPages(outDir)
  .map((rel) => {
    const [locale, ...rest] = rel.split("/");
    return LOCALES.includes(locale) ? { locale, path: rest.join("/") } : null;
  })
  .filter(Boolean);

const byPath = new Map();
for (const { locale, path } of entries) {
  if (!byPath.has(path)) byPath.set(path, new Set());
  byPath.get(path).add(locale);
}

const urlFor = (locale, path) =>
  `${SITE_URL}/${locale}/${path ? path + "/" : ""}`;

const today = new Date().toISOString().slice(0, 10);
const urls = [];
for (const [path, locales] of [...byPath].sort(([a], [b]) => a.localeCompare(b))) {
  for (const locale of LOCALES) {
    if (!locales.has(locale)) continue;
    const alternates = LOCALES.filter((l) => locales.has(l))
      .map(
        (l) =>
          `    <xhtml:link rel="alternate" hreflang="${l}" href="${urlFor(l, path)}"/>`,
      )
      .concat(
        `    <xhtml:link rel="alternate" hreflang="x-default" href="${urlFor(DEFAULT_LOCALE, path)}"/>`,
      )
      .join("\n");
    urls.push(
      `  <url>\n    <loc>${urlFor(locale, path)}</loc>\n    <lastmod>${today}</lastmod>\n${alternates}\n  </url>`,
    );
  }
}

writeFileSync(
  join(outDir, "sitemap.xml"),
  `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">\n${urls.join("\n")}\n</urlset>\n`,
);
console.log(`[prepare-cpanel] out/sitemap.xml written (${urls.length} URLs, ${SITE_URL})`);

if (!existsSync(join(outDir, "404.html"))) {
  console.warn("[prepare-cpanel] warning: out/404.html not found");
}

function scrubDatabase(dir) {
  if (!existsSync(dir)) return 0;
  let removed = 0;
  for (const name of readdirSync(dir)) {
    const full = join(dir, name);
    if (name === "backups" && statSync(full).isDirectory()) {
      rmSync(full, { recursive: true, force: true });
      removed++;
      continue;
    }
    if (name === "location.txt" || name === "install.token" || name.includes(".sqlite")) {
      unlinkSync(full);
      removed++;
    }
  }
  return removed;
}

const dataDir = join(outDir, "cms", "data");
const mediaDir = join(outDir, "cms", "media");
mkdirSync(dataDir, { recursive: true });
mkdirSync(mediaDir, { recursive: true });
console.log(`[prepare-cpanel] removed ${scrubDatabase(dataDir)} database file(s) from the upload`);
writeFileSync(
  join(dataDir, "KEEP.txt"),
  "این پوشه را هنگام به‌روزرسانی پاک نکنید.\nاگر پایگاه به پوشه jafari-private کنار public_html منتقل شده، آن پوشه را هم پاک نکنید.\nفایل clinic.sqlite مقاله، نوبت، گالری و تنظیمات میز است.\n",
);
writeFileSync(
  join(mediaDir, "KEEP.txt"),
  "عکس‌های آپلودشده اینجاست. هنگام به‌روزرسانی این پوشه را پاک نکنید.\n",
);
writeFileSync(
  join(outDir, "UPDATE.txt"),
  `به‌روزرسانی سایت جعفری

قبل از بار اول، گواهی SSL را در cPanel فعال کنید. این بسته درخواست HTTP را به HTTPS می‌فرستد. در پنل هاست Content-Security-Policy دوم نگذارید.

بار اول، برای دامنه drmehrshadjafari.ir
1. در cPanel اول SSL/TLS Status را باز کنید و Run AutoSSL را بزنید. صبر کنید تا قفل دامنه سبز شود. گواهی Self-signed را رها نکنید.
2. در جستجوی cPanel بنویسید Select PHP Version. گزینه‌های pdo_sqlite و sqlite3 و mbstring و gd و simplexml را روشن کنید. PHP روی ۸.۱ یا جدیدتر بماند.
3. MySQL نسازید و phpMyAdmin را برای این سایت باز نکنید.
4. محتوای این بسته را در public_html از حالت فشرده خارج کنید، از جمله فایل مخفی .htaccess. اگر پوشهٔ اضافه ساخته شد، فایل‌ها را به خود public_html بیاورید.
5. آدرس /manage/ را باز کنید. نام کاربری انگلیسی و رمز را دو بار بنویسید و ساختن میز را بزنید. پایگاه خودش ساخته می‌شود.

به‌روزرسانی بعدی
1. در میز، تنظیمات، یک نسخه پشتیبان بگیرید.
2. همین بسته را دوباره روی public_html از حالت فشرده خارج کنید.
3. پوشه‌های cms/data و cms/media و پوشه jafari-private را پاک نکنید.
4. Extract فایل‌های هم‌نام را عوض می‌کند و پایگاه را، چون داخل بسته نیست، دست نمی‌زند.
5. یک بار /manage/ را باز کنید. ستون‌های تازه پایگاه خودشان اضافه می‌شوند.

اگر public_html را کامل پاک کردید، پوشه jafari-private کنار آن را پاک نکنید. میز همان پایگاه را دوباره پیدا می‌کند.
`,
);

const zipPath = join(root, "jafari-cpanel.zip");
try {
  if (existsSync(zipPath)) unlinkSync(zipPath);
  execFileSync("tar", ["-a", "-c", "-f", zipPath, "-C", outDir, "."], { stdio: "inherit" });
  console.log("[prepare-cpanel] jafari-cpanel.zip is ready in the project folder.");
} catch (error) {
  console.warn("[prepare-cpanel] zip was not created. Upload the contents of ./out instead.");
  console.warn(error instanceof Error ? error.message : error);
}
console.log("[prepare-cpanel] done. Upload jafari-cpanel.zip to public_html and extract it there.");
