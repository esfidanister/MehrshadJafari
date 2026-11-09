"use client";

import { useState } from "react";
import { useLocale, useTranslations } from "next-intl";

import { treatments } from "@/content/treatments";
import { formatWhen } from "@/lib/calendar";
import { pick, type Locale } from "@/lib/locale";

type Result = {
  status: string;
  service: string;
  serviceLabel?: string;
  kind?: string;
  kindLabel?: string;
  day: string;
  time: string;
};

export function TrackForm() {
  const t = useTranslations("track");
  const booking = useTranslations("booking");
  const locale = useLocale() as Locale;
  const [phone, setPhone] = useState("");
  const [code, setCode] = useState("");
  const [pending, setPending] = useState(false);
  const [missing, setMissing] = useState(false);
  const [result, setResult] = useState<Result | null>(null);

  const statusLabel: Record<string, string> = {
    new: t("statusNew"),
    confirmed: t("statusConfirmed"),
    cancelled: t("statusCancelled"),
    done: t("statusDone"),
    arrived: t("statusArrived"),
    noshow: t("statusNoshow"),
  };
  const statusKey: Record<string, string> = {
    new: "track.statusNew",
    confirmed: "track.statusConfirmed",
    cancelled: "track.statusCancelled",
    done: "track.statusDone",
    arrived: "track.statusArrived",
    noshow: "track.statusNoshow",
  };

  function statusText(status: string) {
    const key = statusKey[status];
    if (typeof document !== "undefined" && key) {
      const text = document.querySelector(`[data-cms="${key}"]`)?.textContent?.trim();
      if (text) return text;
    }
    return statusLabel[status] ?? status;
  }

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setMissing(false);
    setResult(null);
    setPending(true);
    try {
      const body = new FormData();
      body.set("phone", phone.trim());
      body.set("code", code.trim());
      body.set("locale", locale);
      const response = await fetch("/cms/track.php", { method: "POST", body });
      const data = (await response.json()) as { ok?: boolean; visit?: Result };
      if (!response.ok || !data.ok || !data.visit) {
        setMissing(true);
        return;
      }
      setResult(data.visit);
    } catch {
      setMissing(true);
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="grid items-start gap-6 md:grid-cols-2">
      <form onSubmit={submit} className="rounded-[1.75rem] border border-border bg-surface p-6 shadow-[0_24px_50px_-36px_rgb(24_40_50/0.45)] md:p-8">
        <span hidden data-cms="track.statusNew">{t("statusNew")}</span>
        <span hidden data-cms="track.statusConfirmed">{t("statusConfirmed")}</span>
        <span hidden data-cms="track.statusCancelled">{t("statusCancelled")}</span>
        <span hidden data-cms="track.statusDone">{t("statusDone")}</span>
        <span hidden data-cms="track.statusArrived">{t("statusArrived")}</span>
        <span hidden data-cms="track.statusNoshow">{t("statusNoshow")}</span>
        <label className="block text-sm text-muted">
          <span data-cms="track.mobile">{t("mobile")}</span>
          <input required dir="ltr" inputMode="tel" value={phone} onChange={(event) => setPhone(event.target.value)} className="mt-1 w-full rounded-2xl border border-border bg-bg px-4 py-3 text-text outline-none transition focus:border-medical focus:bg-white" />
        </label>
        <label className="mt-4 block text-sm text-muted">
          <span data-cms="track.code">{t("code")}</span>
          <input required dir="ltr" value={code} onChange={(event) => setCode(event.target.value.toUpperCase())} className="mt-1 w-full rounded-2xl border border-border bg-bg px-4 py-3 tracking-[0.14em] text-text outline-none transition focus:border-medical focus:bg-white" />
        </label>
        <button type="submit" disabled={pending} className="mt-6 w-full rounded-full bg-[#14242c] px-5 py-3.5 text-sm font-medium text-white transition hover:bg-medical disabled:opacity-60">
          <span data-cms="track.submit">{t("submit")}</span>
        </button>
      </form>
      <div className="rounded-[1.75rem] bg-[#14242c] p-6 text-white md:p-8">
        {result ? (
          <dl className="space-y-3 text-sm">
            <div>
              <dt className="text-white/60" data-cms="track.status">{t("status")}</dt>
              <dd className="text-lg text-white">{statusText(result.status)}</dd>
            </div>
            {result.kindLabel ? (
              <div>
                <dt className="text-white/60" data-cms="booking.stepKind">{booking("stepKind")}</dt>
                <dd className="text-white">{result.kindLabel}</dd>
              </div>
            ) : null}
            <div>
              <dt className="text-white/60" data-cms="track.service">{t("service")}</dt>
              <dd className="text-white">
                {result.serviceLabel
                  ? result.serviceLabel
                  : result.service === "unsure"
                    ? booking("unsure")
                    : pick(treatments.find((item) => item.slug === result.service)?.title ?? { fa: result.service, en: result.service }, locale)}
              </dd>
            </div>
            <div>
              <dt className="text-white/60" data-cms="track.when">{t("when")}</dt>
              <dd className="text-lg text-[#9ccdd0]">{formatWhen(result.day, locale)} · <span dir="ltr">{result.time}</span></dd>
            </div>
          </dl>
        ) : (
          <p className="text-sm leading-relaxed text-white/75" data-cms={missing ? "track.missing" : "track.empty"}>
            {missing ? t("missing") : t("empty")}
          </p>
        )}
      </div>
    </div>
  );
}
