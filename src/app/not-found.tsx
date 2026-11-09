import Link from "next/link";

import { fontVariables } from "@/lib/fonts";

import "./globals.css";

/**
 * Global 404 (exported as `404.html`). Apache serves it for any unknown URL,
 * so it must stand alone with its own <html> and work without locale context.
 */
export default function GlobalNotFound() {
  return (
    <html lang="fa" dir="rtl" className={`${fontVariables} h-full antialiased`}>
      <body className="flex min-h-full items-center justify-center px-6">
        <main className="flex max-w-xl flex-col gap-6 py-24">
          <p className="eyebrow" dir="ltr">
            404 · JAFARI
          </p>
          <h1 className="text-display font-medium tracking-tight text-text">
            صفحه پیدا نشد
          </h1>
          <p className="text-lg text-muted">
            نشانی وارد شده وجود ندارد یا جابه‌جا شده است.
          </p>
          <p className="text-base text-muted" dir="ltr">
            The page you are looking for could not be found.
          </p>
          <div className="mt-2 flex flex-wrap gap-3">
            <Link
              href="/fa/"
              className="inline-flex h-12 items-center rounded-lg bg-medical px-7 text-base font-medium text-white"
            >
              صفحه اصلی
            </Link>
            <Link
              href="/en/"
              className="inline-flex h-12 items-center rounded-lg border border-border bg-surface px-7 text-base font-medium text-text"
              dir="ltr"
            >
              English home
            </Link>
            <Link
              href="/ar/"
              className="inline-flex h-12 items-center rounded-lg border border-border bg-surface px-7 text-base font-medium text-text"
            >
              العربية
            </Link>
            <Link
              href="/zh/"
              className="inline-flex h-12 items-center rounded-lg border border-border bg-surface px-7 text-base font-medium text-text"
              dir="ltr"
            >
              中文
            </Link>
          </div>
        </main>
      </body>
    </html>
  );
}
