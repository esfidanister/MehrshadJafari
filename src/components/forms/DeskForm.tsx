"use client";

import { useId, useState, useSyncExternalStore, type FormEvent } from "react";
import { Check } from "lucide-react";
import { useTranslations } from "next-intl";

import { usePathname } from "@/i18n/navigation";

import { Button } from "@/components/ui/Button";
import { cn } from "@/lib/utils";

const inputClass = cn(
  "w-full rounded-lg border border-border bg-bg px-4 py-3 text-base text-text placeholder:text-muted/70",
  "transition-[border-color,box-shadow,background-color] duration-300",
  "hover:border-medical/30 focus:border-medical focus:bg-surface focus:ring-[3px] focus:ring-medical/20 focus:outline-none",
  "aria-[invalid=true]:border-[#b4574f] aria-[invalid=true]:ring-[#b4574f]/15",
);

const services = [
  ["wisdom-tooth-surgery", "جراحی دندان عقل", "Wisdom tooth surgery"],
  ["dental-implants", "ایمپلنت دندانی", "Dental implants"],
  ["orthognathic-surgery", "جراحی فک", "Jaw surgery"],
  ["oral-pathology", "پاتولوژی دهان", "Oral pathology"],
] as const;

function subscribeToNavigation(onStoreChange: () => void) {
  window.addEventListener("popstate", onStoreChange);
  return () => window.removeEventListener("popstate", onStoreChange);
}

function queryMatches(name: string, expected: string) {
  return new URLSearchParams(window.location.search).get(name) === expected;
}

export function DeskForm({
  locale,
  kind,
  title,
  intro,
  titleCms,
  introCms,
}: {
  locale: string;
  kind: "contact" | "appointment";
  title: string;
  intro: string;
  titleCms?: string;
  introCms?: string;
}) {
  const t = useTranslations();
  const path = usePathname();
  const uid = useId();
  const [error, setError] = useState("");
  const sent = useSyncExternalStore(
    subscribeToNavigation,
    () => queryMatches("sent", kind),
    () => false,
  );
  const failed = useSyncExternalStore(
    subscribeToNavigation,
    () => queryMatches("error", "1"),
    () => false,
  );

  const next =
    path === "/" ? `/${locale}/` : `/${locale}${path.endsWith("/") ? path : `${path}/`}`;

  const onSubmit = (event: FormEvent<HTMLFormElement>) => {
    const data = new FormData(event.currentTarget);
    const name = String(data.get("name") ?? "").trim();
    const phone = String(data.get("phone") ?? "").replace(/\D/g, "");
    const message = String(data.get("message") ?? "").trim();
    const consent = data.get("consent");
    const service = String(data.get("service") ?? "");
    const preferred = String(data.get("preferred") ?? "").trim();
    if (name.length < 2 || phone.length < 8) {
      event.preventDefault();
      setError(t("form.validation.phoneInvalid"));
      return;
    }
    if (kind === "contact" && message.length < 10) {
      event.preventDefault();
      setError(t("form.validation.messageMin"));
      return;
    }
    if (kind === "appointment" && (service === "" || preferred === "")) {
      event.preventDefault();
      setError(locale === "fa" ? "موضوع و زمان تقریبی را بنویسید." : "Choose a subject and a rough time.");
      return;
    }
    if (consent !== "1") {
      event.preventDefault();
      setError(t("form.validation.consentRequired"));
      return;
    }
  };

  if (sent) {
    return (
      <div role="status" className="rounded-2xl border border-medical/25 bg-medical/5 p-6">
        <p className="flex items-center gap-2 font-medium text-text">
          <Check className="size-4 text-medical" aria-hidden />
          <span data-cms="form.success">{t("form.success")}</span>
        </p>
      </div>
    );
  }

  return (
    <form action="/cms/lead.php" method="post" onSubmit={onSubmit} className="space-y-5" noValidate>
      <div>
        <h3 className="text-2xl font-medium tracking-tight text-text" {...(titleCms ? { "data-cms": titleCms } : {})}>{title}</h3>
        <p className="mt-2 text-sm leading-relaxed text-muted" {...(introCms ? { "data-cms": introCms } : {})}>{intro}</p>
      </div>
      {failed || error ? (
        <p role="alert" className="text-sm text-[#b4574f]">
          {error || t("form.error")}
        </p>
      ) : null}
      <input type="hidden" name="kind" value={kind} />
      <input type="hidden" name="locale" value={locale} />
      <input type="hidden" name="next" value={next} />
      <input className="hidden" tabIndex={-1} autoComplete="off" name="company" aria-hidden defaultValue="" />
      <div className="grid gap-5 sm:grid-cols-2">
        <label className="space-y-2 text-sm font-medium" htmlFor={`${uid}-name`}>
          {t("form.name")}
          <input id={`${uid}-name`} name="name" required autoComplete="name" className={inputClass} />
        </label>
        <label className="space-y-2 text-sm font-medium" htmlFor={`${uid}-phone`}>
          {t("form.phone")}
          <input id={`${uid}-phone`} name="phone" required dir="ltr" autoComplete="tel" inputMode="tel" className={inputClass} />
        </label>
      </div>
      {kind === "appointment" ? (
        <div className="grid gap-5 sm:grid-cols-2">
          <label className="space-y-2 text-sm font-medium" htmlFor={`${uid}-service`}>
            {t("form.treatmentInterest")}
            <select id={`${uid}-service`} name="service" required className={inputClass} defaultValue="">
              <option value="">{t("form.selectPlaceholder")}</option>
              {services.map(([value, fa, en]) => (
                <option key={value} value={value}>
                  {locale === "fa" ? fa : en}
                </option>
              ))}
            </select>
          </label>
          <label className="space-y-2 text-sm font-medium" htmlFor={`${uid}-preferred`}>
            {locale === "fa" ? "زمان تقریبی" : "Rough time"}
            <input id={`${uid}-preferred`} name="preferred" required className={inputClass} />
          </label>
        </div>
      ) : null}
      <label className="space-y-2 text-sm font-medium" htmlFor={`${uid}-message`}>
        {t("form.message")}
        <textarea id={`${uid}-message`} name="message" rows={5} className={cn(inputClass, "resize-y")} />
      </label>
      <label className="flex items-start gap-3 text-sm leading-relaxed text-muted">
        <input type="checkbox" name="consent" value="1" className="mt-1" required />
        {t("form.consent")}
      </label>
      <Button type="submit" size="lg">
        {t("common.submit")}
      </Button>
    </form>
  );
}
