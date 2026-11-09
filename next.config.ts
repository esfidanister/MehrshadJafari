import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const nextConfig: NextConfig = {
  // Static HTML export for cPanel / Apache hosting (output goes to ./out).
  output: "export",
  // /fa/about/ -> out/fa/about/index.html (works on any static host).
  trailingSlash: true,
  // next/image default loader needs a server; disable for static export.
  images: { unoptimized: true },
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default withNextIntl(nextConfig);
