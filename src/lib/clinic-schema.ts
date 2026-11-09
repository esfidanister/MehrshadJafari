import { doctorProfile } from "@/content/doctor";
import { clinicCopy } from "@/lib/clinicCopy";
import { pick, type Locale } from "@/lib/locale";
import { absoluteLocaleUrl, DEFAULT_SITE_URL, SHARE_IMAGE } from "@/lib/seo";

/** The city already written at the start of each published address line. */
const CITY: Record<Locale, string> = {
  fa: "تهران",
  en: "Tehran",
  ar: "طهران",
  zh: "德黑兰",
};

const ORAL_SURGERY = "https://schema.org/OralSurgery";

/** Physician and clinic facts that are already visible on the site. */
export function clinicGraph(locale: Locale, codeLabel: string) {
  const clinic = clinicCopy[locale];
  const home = absoluteLocaleUrl(locale, "/");
  const physician = `${home}#physician`;
  const practice = `${home}#clinic`;
  const image = new URL(SHARE_IMAGE.url, DEFAULT_SITE_URL).toString();
  const name = pick(doctorProfile.name, locale);
  const specialty = pick(doctorProfile.specialty, locale);

  return {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Physician",
        "@id": physician,
        name,
        description: specialty,
        medicalSpecialty: [ORAL_SURGERY, specialty],
        identifier: {
          "@type": "PropertyValue",
          name: codeLabel,
          value: clinic.medicalCode,
        },
        image,
        url: home,
        sameAs: [clinic.instagramUrl],
        worksFor: { "@id": practice },
      },
      {
        "@type": "MedicalClinic",
        "@id": practice,
        name,
        url: home,
        image,
        medicalSpecialty: ORAL_SURGERY,
        address: {
          "@type": "PostalAddress",
          streetAddress: clinic.address,
          addressLocality: CITY[locale],
          addressCountry: "IR",
        },
        telephone: [clinic.phone, clinic.phoneAlt],
        employee: { "@id": physician },
      },
    ],
  };
}
