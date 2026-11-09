import { CalendarCheck } from "lucide-react";
import { getTranslations } from "next-intl/server";

import { Container } from "@/components/ui/Container";
import { buttonVariants } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import { cn } from "@/lib/utils";

/** Closing call-to-action band: booking + phone. */
export async function CtaBand({ locale }: { locale: string }) {
  const t = await getTranslations({ locale });

  return (
    <section className="py-[var(--spacing-section)]">
      <Container>
        <div className="relative isolate overflow-hidden rounded-2xl bg-text px-7 py-14 text-white md:px-16 md:py-20">
          <div
            aria-hidden
            className="pointer-events-none absolute -top-24 -end-24 -z-10 size-96 rounded-full bg-medical/50 blur-3xl"
          />
          <div className="flex flex-col gap-8 md:flex-row md:items-end md:justify-between">
            <div className="max-w-xl">
              <p className="eyebrow text-turquoise" data-cms="appointment.eyebrow">{t("appointment.eyebrow")}</p>
              <h2 className="mt-4 text-title font-medium tracking-tight text-white" data-cms="appointment.title">
                {t("appointment.title")}
              </h2>
              <p className="mt-4 text-base leading-relaxed text-white/70" data-cms="appointment.description">
                {t("appointment.description")}
              </p>
            </div>
            <div className="flex flex-col gap-3 sm:flex-row">
              <Link
                href="/appointment"
                className={cn(
                  buttonVariants({ size: "lg" }),
                  "bg-white text-text hover:bg-white/90",
                )}
              >
                <CalendarCheck aria-hidden />
                <span data-cms="common.bookNow">{t("common.bookNow")}</span>
              </Link>
            </div>
          </div>
        </div>
      </Container>
    </section>
  );
}
