import type { ReactNode } from "react";

/**
 * Passthrough root layout.
 *
 * `<html>` / `<body>` live in `app/[locale]/layout.tsx` so `lang` and `dir`
 * can be set per locale (next-intl pattern for static export, no middleware).
 */
export default function RootLayout({ children }: { children: ReactNode }) {
  return children;
}
