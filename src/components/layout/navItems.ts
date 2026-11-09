/** Shared by Header, MobileNavigation and Footer. Keys map to `nav.*` messages. */
export const NAV_ITEMS = [
  { key: "doctor", href: "/about" },
  { key: "treatments", href: "/services" },
  { key: "anatomy", href: "/anatomy" },
  { key: "journal", href: "/articles" },
  { key: "consult", href: "/consult" },
  { key: "gallery", href: "/gallery" },
  { key: "column", href: "/column" },
  { key: "briefing", href: "/briefing" },
  { key: "reviews", href: "/reviews" },
  { key: "contact", href: "/contact" },
] as const;

export type NavKey = (typeof NAV_ITEMS)[number]["key"];

/** Links that stay in the bar. The rest open from the directory. */
export const PRIMARY_NAV: NavKey[] = ["doctor", "treatments", "consult", "contact"];

export const NAV_GROUPS: { id: "care" | "library"; keys: NavKey[] }[] = [
  { id: "care", keys: ["doctor", "treatments", "anatomy", "consult", "reviews", "contact"] },
  { id: "library", keys: ["journal", "gallery", "column", "briefing"] },
];

export const APPOINTMENT_HREF = "/appointment";

/** `pathname` from next-intl has no locale prefix. */
export function isActivePath(pathname: string, href: string): boolean {
  if (href === "/") return pathname === "/";

  return pathname === href || pathname.startsWith(`${href}/`);
}
