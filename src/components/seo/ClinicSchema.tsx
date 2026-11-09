import { getTranslations } from "next-intl/server";

import { clinicGraph } from "@/lib/clinic-schema";
import type { Locale } from "@/lib/locale";

export async function ClinicSchema({ locale }: { locale: Locale }) {
  const t = await getTranslations({ locale, namespace: "common" });
  const json = JSON.stringify(clinicGraph(locale, t("medicalCodeLabel"))).replaceAll("<", "\\u003c");

  return <script id="jafari-clinic" type="application/ld+json" dangerouslySetInnerHTML={{ __html: json }} />;
}
