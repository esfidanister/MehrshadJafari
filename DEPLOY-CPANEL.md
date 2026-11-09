# Deploying JAFARI to cPanel (static hosting)

The site is a **static export** of Next.js (`output: 'export'`). No Node.js server is needed on the host: Apache just serves the files in `out/`.

## 1. Build

```bash
# optional: set your real domain (used for canonical URLs, sitemap, robots)
# PowerShell:  $env:NEXT_PUBLIC_SITE_URL="https://your-domain.ir"
# bash:        export NEXT_PUBLIC_SITE_URL=https://your-domain.ir

npm install
npm run build:cpanel      # same as: npm run export
```

`build:cpanel` runs `next build` and then `scripts/prepare-cpanel.mjs`, which:

- writes `out/index.html` (redirects `/` to `/fa/`)
- copies `public/.htaccess` to `out/.htaccess`
- writes `out/robots.txt` and generates `out/sitemap.xml` (fa + en, with hreflang)
- copies RSC prefetch payloads to the file names the browser requests (needed when building on Windows)

Default site URL is `https://drmehrshadjafari.ir`. Override with `NEXT_PUBLIC_SITE_URL` before building if the domain changes.

## 2. Upload

Build also writes `jafari-cpanel.zip` in the project folder. That zip has the site and the desk, and it never contains the clinic database.

1. In cPanel open **File Manager** and go to `public_html` (or the addon-domain folder).
2. Upload `jafari-cpanel.zip` and choose **Extract**. Extract here, not into a new folder.
3. Show hidden files and confirm `.htaccess` is in the web root.
4. In **Select PHP Version**, enable `pdo_sqlite`, `sqlite3`, and `gd`. Do not create a MySQL database.

Expected layout:

```
public_html/
├─ .htaccess
├─ index.html          (redirect to /fa/)
├─ 404.html
├─ robots.txt
├─ sitemap.xml
├─ _next/              (hashed JS/CSS/fonts)
├─ fa/…                (Persian, RTL)
└─ en/…                (English, LTR)
```

## 3. میز مطب، فقط یک بار

بعد از آپلود، در مرورگر باز کنید:

`https://drmehrshadjafari.ir/manage/`

اگر PHP یا پوشه داده آماده نباشد، همان صفحه می‌گوید کدام گزینه را در cPanel روشن کنید و دکمه ساخت را نشان نمی‌دهد. نام کاربری انگلیسی و رمز را دو بار بنویسید. پایگاه SQLite همان لحظه ساخته می‌شود. فایل نصب را خود صفحه می‌سازد و بعد از ساخت مالک پاک می‌کند. لازم نیست در File Manager فایلی بسازید و لازم نیست MySQL بسازید.

پایگاه فایل `clinic.sqlite` است. اگر هاست اجازه بدهد، میز آن را بیرون از سایت، در پوشه `jafari-private` کنار `public_html` می‌سازد. اگر اجازه ندهد، فایل داخل `cms/data` می‌ماند و از وب بسته است. در هر دو حالت لازم نیست از بخش MySQL دیتابیس بسازید. بدون `gd` سایت باز می‌شود، ولی عکس به WebP تبدیل نمی‌شود.

میز این کارها را دارد:

- خانه میز: نوبت تازه، پیام، پیش‌نویس و وضعیت گوگل.
- یادداشت پزشک: می‌نویسید و فقط با وضعیت «منتشر شود» در `/fa/column/` دیده می‌شود.
- مرور جهان: منبع عمومی را به پیش‌نویس تبدیل می‌کند. تا خودتان منتشر نکنید روی سایت نمی‌آید.
- گالری: هر آپلود WebP و کم‌حجم می‌شود. فقط عکسی که «در گالری عمومی» باشد در `/fa/gallery/` دیده می‌شود.
- سئو و گوگل: کد HTML سرچ کنسول و عنوان هر صفحه. نقشه سایت را بعد از تأیید دامنه در سرچ کنسول ثبت کنید.
- کرون اختیاری است. آدرسش در تنظیمات میز است و فقط پیش‌نویس می‌سازد.

## 4. After upload

- Enable **SSL** before upload (cPanel → *SSL/TLS Status* → *Run AutoSSL*). `.htaccess` already redirects HTTP to HTTPS and sends HSTS for this host only. Without a certificate, that redirect has nowhere valid to land. Do not add a second Content-Security-Policy in cPanel. PHP pages already send one with a per-request script nonce, and a second policy is enforced together with it.
- Check `https://your-domain/` → redirects to `/fa/`; `https://your-domain/en/` loads English.
- Check a missing URL shows the custom `404.html`.
- In the desk open سئو و گوگل, paste the Search Console HTML-tag code, then submit both `https://your-domain/sitemap.xml` and `https://your-domain/cms/sitemap.php`.
- Do not delete `cms/data`, `cms/media`, or the `jafari-private` folder next to `public_html`. They hold the desk, articles, gallery, and the Google code.

## 5. Updating the site

1. In the desk, open تنظیمات and download a backup.
2. Run `npm run build:cpanel` again.
3. Upload the new `jafari-cpanel.zip` and extract it over `public_html`.
4. Do not delete `cms/data`, `cms/media`, or `jafari-private` first. Extract replaces same-named files and leaves the database, because the zip does not contain it.
5. Open `/manage/` once. New database columns are added by themselves. Articles, appointments, and the gallery stay.

If `public_html` was emptied, leave `jafari-private` in place. The desk finds that database again. HTML is served with `no-cache`; `_next/static` assets are content-hashed and cached for a year.

## Notes & limits of static hosting

- No middleware / server code: locale is part of the URL (`/fa/…`, `/en/…`); `/` is a plain redirect.
- The marketing pages are static. The clinic desk (`/manage/`), surgeon notes (`/fa/column/`), and world briefs (`/fa/briefing/`) are PHP and work on ordinary cPanel hosting. Re-extract the zip over `public_html`. Do not delete `cms/data`, `cms/media`, or `jafari-private`, or the articles and uploaded photos disappear.
- Images use `images.unoptimized`; add pre-optimized files under `public/`.
- Deploying under a sub-folder (e.g. `/jafari/`) needs `basePath` in `next.config.ts` and a matching `RewriteRule` in `.htaccess`.
