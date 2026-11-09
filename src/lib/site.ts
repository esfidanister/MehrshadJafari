import { doctorProfile } from "@/content/doctor";

/** Primary navigation. `key` maps to the `nav.*` messages. */
export const navItems = [
  { key: "home", href: "/" },
  { key: "treatments", href: "/services" },
  { key: "doctor", href: "/about" },
  { key: "anatomy", href: "/anatomy" },
  { key: "reviews", href: "/reviews" },
  { key: "journal", href: "/articles" },
  { key: "contact", href: "/contact" },
] as const;

export type NavKey = (typeof navItems)[number]["key"];

/** Maps an `seo.pages.*` message key to the logical pathname (without locale). */
export const seoPages = {
  home: "/",
  treatments: "/services",
  doctor: "/about",
  anatomy: "/anatomy",
  reviews: "/reviews",
  journal: "/articles",
  contact: "/contact",
  appointment: "/appointment",
  consult: "/consult",
  track: "/appointment/track",
  gallery: "/gallery",
  briefing: "/briefing",
  column: "/column",
} as const;

export type SeoPageKey = keyof typeof seoPages;

/** Which treatment page best matches an educational article. */
export const articleTreatmentMap: Record<string, string> = {
  "when-is-wisdom-tooth-removal-considered": "wisdom-tooth-surgery",
  "implant-healing-what-patients-should-know": "dental-implants",
  "orthognathic-care-team-and-timeline": "orthognathic-surgery",
  "oral-lesions-when-to-seek-evaluation": "oral-pathology",
  "implant-from-consult-to-crown": "dental-implants",
  "after-wisdom-tooth-removal-day-by-day": "wisdom-tooth-surgery",
  "who-needs-orthognathic-surgery": "orthognathic-surgery",
  "bone-graft-and-sinus-lift": "bone-graft-sinus-lift",
  "jaw-joint-pain": "tmj",
  "ten-points-for-easier-recovery": "wisdom-tooth-surgery",
};

export const primaryClinic = doctorProfile.locations[0];
export const clinicHours = doctorProfile.locations.find((l) => l.hours)?.hours;
export const clinicPhone = primaryClinic.phone ?? "";

export const bookingLinks = doctorProfile.bookingUrls;
export const instagram = doctorProfile.social.instagram;

export function mapsSearchUrl(address: string): string {
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
}
