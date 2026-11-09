import { redirect } from "next/navigation";

/**
 * Static export has no middleware, so `/` forwards to the default locale.
 * `scripts/prepare-cpanel.mjs` also rewrites `out/index.html` with a
 * hard meta-refresh/JS redirect for hosts that serve it directly.
 */
export default function RootPage() {
  redirect("/fa/");
}
