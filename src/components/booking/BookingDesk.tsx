"use client";

import { useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";

import { treatments } from "@/content/treatments";
import { readBookingDesk, slotTaken } from "@/lib/booking-desk";
import {
  faDigits,
  formatWhen,
  gregorianToJalali,
  jalaliMonthLength,
  jalaliToGregorian,
} from "@/lib/calendar";
import { pick, type Locale } from "@/lib/locale";

const WEEK_FA = ["ش", "ی", "د", "س", "چ", "پ", "ج"];
const WEEK_AR = ["س", "ح", "ن", "ث", "ر", "خ", "ج"];
const WEEK_EN = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
const WEEK_ZH = ["一", "二", "三", "四", "五", "六", "日"];

type Month = { year: number; month: number };

function tehranToday(): Date {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Tehran",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date());
  const [year, month, day] = parts.split("-").map(Number);
  return new Date(year, month - 1, day);
}

function tehranHour(): number {
  const hour = new Intl.DateTimeFormat("en-GB", {
    timeZone: "Asia/Tehran",
    hour: "2-digit",
    hourCycle: "h23",
  }).format(new Date());
  const value = Number(hour);
  return Number.isFinite(value) ? value : 0;
}

function iso(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${date.getFullYear()}-${month}-${day}`;
}

function deskText(key: string, fallback: string) {
  if (typeof document === "undefined") return fallback;
  const text = document.querySelector(`[data-cms="${key}"]`)?.textContent?.trim();
  return text || fallback;
}

function addDays(date: Date, days: number): Date {
  const next = new Date(date);
  next.setDate(next.getDate() + days);
  return next;
}

export function BookingDesk() {
  const locale = useLocale() as Locale;
  const t = useTranslations();
  const shamsi = locale === "fa";
  const saturday = locale === "fa" || locale === "ar";
  const today = tehranToday();
  const start = shamsi
    ? gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate())
    : null;
  const [cursor, setCursor] = useState<Month>(
    start ? { year: start.jy, month: start.jm } : { year: today.getFullYear(), month: today.getMonth() + 1 },
  );
  const [kind, setKind] = useState("exam");
  const [place, setPlace] = useState("");
  const [service, setService] = useState("unsure");
  const [day, setDay] = useState("");
  const [time, setTime] = useState("");
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [email, setEmail] = useState("");
  const [reason, setReason] = useState("");
  const [consent, setConsent] = useState(false);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState("");
  const [code, setCode] = useState("");
  const desk = useMemo(
    () =>
      readBookingDesk(
        [
          { key: "exam", label: t("booking.kindExam"), minutes: 30 },
          { key: "postop", label: t("booking.kindPostop"), minutes: 20 },
          { key: "consult", label: t("booking.kindConsult"), minutes: 30 },
          { key: "surgery", label: t("booking.kindSurgery"), minutes: 60 },
        ],
        [
          { key: "unsure", label: t("booking.unsure") },
          ...treatments.map((item) => ({ key: item.slug, label: pick(item.title, locale) })),
        ],
      ),
    [locale, t],
  );
  const firstDay = addDays(today, desk.leadDays);
  const last = addDays(today, desk.horizon);
  const chosenPlace = desk.places.find((item) => item.key === place) ?? desk.places[0];
  const slots = chosenPlace ? desk.slots.filter((slot) => slot >= chosenPlace.start && slot < chosenPlace.end) : desk.slots;
  const chosenKind = desk.kinds.find((item) => item.key === kind) ?? desk.kinds[0];
  const placeWord = {
    clinic: { fa: "مطب", en: "Clinic", ar: "عيادة", zh: "诊所" },
    hospital: { fa: "بیمارستان", en: "Hospital", ar: "مستشفى", zh: "医院" },
    other: { fa: "جای دیگر", en: "Other place", ar: "مكان آخر", zh: "其他地点" },
  } as const;

  const weeks = saturday
    ? locale === "ar" ? WEEK_AR : WEEK_FA
    : locale === "zh" ? WEEK_ZH : WEEK_EN;

  const cells = useMemo(() => {
    const count = shamsi ? jalaliMonthLength(cursor.year, cursor.month) : new Date(cursor.year, cursor.month, 0).getDate();
    const first = shamsi
      ? jalaliToGregorian(cursor.year, cursor.month, 1)
      : { gy: cursor.year, gm: cursor.month, gd: 1 };
    const firstDate = new Date(first.gy, first.gm - 1, first.gd);
    const jsDay = firstDate.getDay();
    const lead = saturday ? (jsDay + 1) % 7 : (jsDay + 6) % 7;
    const blanks = Array.from({ length: lead }, () => null);
    const days = Array.from({ length: count }, (_, index) => {
      const point = shamsi
        ? jalaliToGregorian(cursor.year, cursor.month, index + 1)
        : { gy: cursor.year, gm: cursor.month, gd: index + 1 };
      const date = new Date(point.gy, point.gm - 1, point.gd);
      const value = iso(date);
      const blocked = chosenPlace
        ? !chosenPlace.weekdays.includes(date.getDay()) || desk.blocked.includes(value)
        : desk.closedWeekdays.includes(date.getDay()) || desk.blocked.includes(value);
      const free = slots.some((slot) => !slotTaken(value, slot, chosenKind?.minutes ?? 30, desk));
      const pastCutoff = desk.cutoff < 24 && value === iso(today) && tehranHour() >= desk.cutoff;
      const open = date >= firstDay && date <= last && !blocked && free && !pastCutoff;
      return { label: index + 1, value, open, today: value === iso(today) };
    });
    return [...blanks, ...days];
  }, [chosenKind?.minutes, chosenPlace, cursor, desk, firstDay, last, saturday, shamsi, slots, today]);

  const nextOpen = useMemo(() => {
    const found: { day: string; time: string }[] = [];
    for (let cursorDate = new Date(firstDay); cursorDate <= last && found.length < 3; cursorDate = addDays(cursorDate, 1)) {
      const value = iso(cursorDate);
      const closed = chosenPlace
        ? !chosenPlace.weekdays.includes(cursorDate.getDay()) || desk.blocked.includes(value)
        : desk.closedWeekdays.includes(cursorDate.getDay()) || desk.blocked.includes(value);
      const pastCutoff = desk.cutoff < 24 && value === iso(today) && tehranHour() >= desk.cutoff;
      if (closed || pastCutoff) continue;
      const slot = slots.find((item) => !slotTaken(value, item, chosenKind?.minutes ?? 30, desk));
      if (slot) found.push({ day: value, time: slot });
    }
    return found;
  }, [chosenKind?.minutes, chosenPlace, desk, firstDay, last, slots, today]);

  const monthLabel = shamsi
    ? `${["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"][cursor.month - 1]} ${faDigits(String(cursor.year))}`
    : new Intl.DateTimeFormat(locale === "zh" ? "zh-CN" : locale === "ar" ? "ar" : "en-GB", {
        month: "long",
        year: "numeric",
      }).format(new Date(cursor.year, cursor.month - 1, 1));

  function shift(delta: number) {
    setCursor((current) => {
      const index = current.year * 12 + (current.month - 1) + delta;
      return { year: Math.floor(index / 12), month: (index % 12) + 1 };
    });
  }

  const services = desk.topics.map((item) => ({ id: item.key, label: item.label }));
  const activeService = services.some((item) => item.id === service) ? service : (services[0]?.id ?? "unsure");
  const chosen = services.find((item) => item.id === activeService);
  function chooseKind(next: string) {
    const minutes = desk.kinds.find((item) => item.key === next)?.minutes ?? 30;
    setKind(next);
    if (day && time && slotTaken(day, time, minutes, desk)) setTime("");
  }
  const chosenDay = day ? formatWhen(day, locale) : "";
  function mark(value: number) {
    if (locale === "fa") return faDigits(String(value));
    if (locale === "ar") return "٠١٢٣٤٥٦٧٨٩"[value] ?? String(value);
    return String(value);
  }

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setError("");
    const digits = phone.replace(/\D/g, "");
    const normalized = digits.startsWith("98") ? `0${digits.slice(2)}` : digits;
    const emailOk = desk.email !== "required" || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
    const reasonOk = desk.reason !== "required" || reason.trim().length >= 2;
    if (!day || !time || name.trim().length < 2 || normalized.length < 10 || normalized.length > 11 || !emailOk || !reasonOk || !consent) {
      setError(deskText("booking.error", t("booking.error")));
      return;
    }
    setPending(true);
    try {
      const body = new FormData();
      body.set("locale", locale);
      body.set("kind", chosenKind?.key ?? "exam");
      if (chosenPlace) body.set("place", chosenPlace.key);
      body.set("service", activeService);
      body.set("day", day);
      body.set("time", time);
      body.set("name", name.trim());
      body.set("phone", normalized);
      body.set("email", desk.email === "off" ? "" : email.trim());
      body.set("message", desk.reason === "off" ? "" : reason.trim());
      body.set("consent", "1");
      const response = await fetch("/cms/book.php", { method: "POST", body });
      const data = (await response.json()) as { ok?: boolean; code?: string; error?: string };
      if (!response.ok || !data.ok || !data.code) {
        const errorKey =
          data.error === "full" ? "booking.slotFull" : data.error === "closed" ? "booking.dayClosed" : data.error === "phone" ? "booking.phoneLimit" : data.error === "paused" ? "booking.paused" : "booking.error";
        setError(deskText(errorKey, t(errorKey)));
        return;
      }
      setCode(data.code);
    } catch {
      setError(deskText("booking.error", t("booking.error")));
    } finally {
      setPending(false);
    }
  }

  if (!desk.open) {
    return (
      <div className="rounded-[1.75rem] border border-border bg-surface p-8">
        <p className="max-w-xl text-base leading-relaxed text-muted" data-cms="booking.paused">{t("booking.paused")}</p>
      </div>
    );
  }

  if (code) {
    return (
      <div className="rounded-[1.5rem] border border-border bg-surface p-8">
        <h2 className="text-2xl font-medium text-text" data-cms="booking.sentTitle">{t("booking.sentTitle")}</h2>
        <p className="mt-3 max-w-xl text-sm leading-relaxed text-muted" data-cms="booking.sentBody">{t("booking.sentBody")}</p>
        <p className="mt-6 text-xs text-muted" data-cms="booking.codeLabel">{t("booking.codeLabel")}</p>
        <p dir="ltr" className="mt-1 text-3xl font-medium tracking-[0.18em] text-text">{code}</p>
        <a href={`/${locale}/appointment/track/`} className="mt-6 inline-flex text-sm font-medium text-medical">
          <span data-cms="booking.trackLink">{t("booking.trackLink")}</span>
        </a>
        <a href="/care/" className="mt-3 block text-sm font-medium text-medical">
          <span data-cms="booking.careLink">{t("booking.careLink")}</span>
        </a>
      </div>
    );
  }

  return (
    <form onSubmit={submit} className="grid items-start gap-6 lg:grid-cols-12">
      <span hidden data-cms="booking.error">{t("booking.error")}</span>
      <span hidden data-cms="booking.dayClosed">{t("booking.dayClosed")}</span>
      <span hidden data-cms="booking.slotFull">{t("booking.slotFull")}</span>
      <span hidden data-cms="booking.phoneLimit">{t("booking.phoneLimit")}</span>
      <span hidden data-cms="booking.paused">{t("booking.paused")}</span>
      <div className="grid gap-6 lg:col-span-8">
        {desk.places.length ? (
          <fieldset className="rounded-[1.75rem] border border-border bg-surface p-6 shadow-[0_24px_50px_-36px_rgb(24_40_50/0.45)] md:p-8">
            <legend className="px-1 text-sm font-medium text-text">{locale === "fa" ? "مکان نوبت" : locale === "ar" ? "مكان الموعد" : locale === "zh" ? "就诊地点" : "Where"}</legend>
            <div className="mt-4 flex flex-wrap gap-2">
              {desk.places.map((item) => (
                <button
                  key={item.key}
                  type="button"
                  aria-pressed={chosenPlace?.key === item.key}
                  onClick={() => {
                    setPlace(item.key);
                    setTime("");
                  }}
                  className={
                    chosenPlace?.key === item.key
                      ? "rounded-full border border-medical bg-medical px-4 py-2 text-sm text-white transition duration-150"
                      : "rounded-full border border-border bg-bg px-4 py-2 text-sm text-text transition duration-150 hover:border-medical"
                  }
                >
                  {placeWord[item.kind][locale]} · {item.label}
                </button>
              ))}
            </div>
            {chosenPlace?.address ? <p className="mt-3 text-sm text-muted">{chosenPlace.address}</p> : null}
            {nextOpen.length ? (
              <div className="mt-4">
                <p className="text-sm text-muted">{locale === "fa" ? "نزدیک‌ترین وقت آزاد" : locale === "ar" ? "أقرب موعد متاح" : locale === "zh" ? "最近的空档" : "Next open time"}</p>
                <div className="mt-2 flex flex-wrap gap-2">
                  {nextOpen.map((item) => (
                    <button
                      key={`${item.day}-${item.time}`}
                      type="button"
                      onClick={() => {
                        setDay(item.day);
                        setTime(item.time);
                      }}
                      className="rounded-2xl border border-border bg-bg px-3 py-2 text-sm text-text transition duration-150 hover:border-medical"
                    >
                      {formatWhen(item.day, locale)} · <span dir="ltr">{item.time}</span>
                    </button>
                  ))}
                </div>
              </div>
            ) : null}
          </fieldset>
        ) : null}

        <fieldset className="rounded-[1.75rem] border border-border bg-surface p-6 shadow-[0_24px_50px_-36px_rgb(24_40_50/0.45)] md:p-8">
          <legend className="px-1 text-sm font-medium text-text">
            <span data-cms="booking.stepKind">{t("booking.stepKind")}</span>
            <span className="ms-2 text-muted">{mark(1)}</span>
          </legend>
          <div className="mt-4 flex flex-wrap gap-2">
            {desk.kinds.map((item) => (
              <button
                key={item.key}
                type="button"
                aria-pressed={chosenKind?.key === item.key}
                onClick={() => chooseKind(item.key)}
                className={
                  chosenKind?.key === item.key
                    ? "rounded-full border border-medical bg-medical px-4 py-2 text-sm text-white shadow-[0_10px_24px_-16px_rgb(57_127_145/0.9)] transition duration-300"
                    : "rounded-full border border-border bg-bg px-4 py-2 text-sm text-text transition duration-300 hover:border-medical hover:bg-white"
                }
              >
                {item.label}
              </button>
            ))}
          </div>
        </fieldset>

        <fieldset className="rounded-[1.75rem] border border-border bg-surface p-6 shadow-[0_24px_50px_-36px_rgb(24_40_50/0.45)] md:p-8">
          <legend className="px-1 text-sm font-medium text-text">
            <span data-cms="booking.stepService">{t("booking.stepService")}</span>
            <span className="ms-2 text-muted">{mark(2)}</span>
          </legend>
          <div className="mt-4 flex flex-wrap gap-2">
            {services.map((item) => (
              <button
                key={item.id}
                type="button"
                aria-pressed={activeService === item.id}
                onClick={() => setService(item.id)}
                className={
                  activeService === item.id
                    ? "rounded-full border border-medical bg-medical px-4 py-2 text-sm text-white shadow-[0_10px_24px_-16px_rgb(57_127_145/0.9)] transition duration-300"
                    : "rounded-full border border-border bg-bg px-4 py-2 text-sm text-text transition duration-300 hover:border-medical hover:bg-white"
                }
              >
                {item.label}
              </button>
            ))}
          </div>
        </fieldset>

        <fieldset className="rounded-[1.75rem] border border-border bg-surface p-6 shadow-[0_24px_50px_-36px_rgb(24_40_50/0.45)] md:p-8">
          <legend className="px-1 text-sm font-medium text-text">
            <span data-cms="booking.stepWhen">{t("booking.stepWhen")}</span>
            <span className="ms-2 text-muted">{mark(3)}</span>
          </legend>
          <div className="mt-4">
            <div className="mx-auto max-w-md">
              <div className="mb-4 flex items-center justify-between gap-3">
                <button type="button" className="grid size-9 place-items-center rounded-full border border-border text-lg leading-none text-text transition hover:border-medical hover:text-medical" onClick={() => shift(-1)}>
                  <span className="sr-only" data-cms="booking.prevMonth">{t("booking.prevMonth")}</span>
                  ‹
                </button>
                <p className="text-base font-medium text-text">{monthLabel}</p>
                <button type="button" className="grid size-9 place-items-center rounded-full border border-border text-lg leading-none text-text transition hover:border-medical hover:text-medical" onClick={() => shift(1)}>
                  <span className="sr-only" data-cms="booking.nextMonth">{t("booking.nextMonth")}</span>
                  ›
                </button>
              </div>
              <div className="grid grid-cols-7 gap-1 text-center text-xs text-muted">
                {weeks.map((name) => (
                  <span key={name}>{name}</span>
                ))}
              </div>
              <div className="mt-2 grid grid-cols-7 gap-1">
                {cells.map((cell, index) =>
                  cell ? (
                    <button
                      key={cell.value}
                      type="button"
                      disabled={!cell.open}
                      aria-pressed={day === cell.value}
                      onClick={() => {
                        setDay(cell.value);
                        setTime("");
                      }}
                      className={
                        day === cell.value
                          ? "aspect-square rounded-full bg-medical text-sm text-white shadow-[0_10px_20px_-14px_rgb(57_127_145/1)] transition duration-300"
                          : cell.today
                            ? "aspect-square rounded-full text-sm text-medical ring-1 ring-medical transition duration-300"
                            : cell.open
                              ? "aspect-square rounded-full text-sm text-text transition duration-300 hover:bg-medical/10"
                              : "aspect-square rounded-full text-sm text-muted/35"
                      }
                    >
                      {shamsi ? faDigits(String(cell.label)) : cell.label}
                      {cell.today ? <span className="sr-only" data-cms="booking.today">{t("booking.today")}</span> : null}
                    </button>
                  ) : (
                    <span key={`blank-${index}`} />
                  ),
                )}
              </div>
            </div>
            <div className="mt-6 border-t border-border pt-5">
              {day ? (
                <>
                  <p className="text-lg font-medium text-text">{chosenDay}</p>
                  <p className="mt-1 text-sm text-muted" data-cms="booking.timeLabel">{t("booking.timeLabel")}</p>
                  <div className="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-5">
                    {slots.map((slot) => {
                      const taken = slotTaken(day, slot, chosenKind?.minutes ?? 30, desk);
                      return (
                      <button
                        key={slot}
                        type="button"
                        dir="ltr"
                        disabled={taken}
                        aria-pressed={time === slot}
                        onClick={() => setTime(slot)}
                        className={
                          time === slot
                            ? "rounded-2xl bg-medical px-2 py-3 text-sm text-white transition duration-300"
                            : taken
                              ? "rounded-2xl border border-border bg-bg px-2 py-3 text-sm text-muted/40"
                              : "rounded-2xl border border-border bg-bg px-2 py-3 text-sm text-text transition duration-300 hover:border-medical"
                        }
                      >
                        {slot}
                      </button>
                      );
                    })}
                  </div>
                </>
              ) : (
                <p className="rounded-2xl border border-dashed border-border bg-bg px-4 py-8 text-center text-sm text-muted" data-cms="booking.pickDay">{t("booking.pickDay")}</p>
              )}
            </div>
          </div>
        </fieldset>

        <fieldset className="rounded-[1.75rem] border border-border bg-surface p-6 shadow-[0_24px_50px_-36px_rgb(24_40_50/0.45)] md:p-8">
          <legend className="px-1 text-sm font-medium text-text">
            <span data-cms="booking.stepYou">{t("booking.stepYou")}</span>
            <span className="ms-2 text-muted">{mark(4)}</span>
          </legend>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <label className="text-sm text-muted">
              <span data-cms="form.name">{t("form.name")}</span>
              <input required value={name} onChange={(event) => setName(event.target.value)} className="mt-1 w-full rounded-2xl border border-border bg-bg px-4 py-3 text-text outline-none transition focus:border-medical focus:bg-white" />
            </label>
            <label className="text-sm text-muted">
              <span data-cms="form.phone">{t("form.phone")}</span>
              <input required dir="ltr" inputMode="tel" value={phone} onChange={(event) => setPhone(event.target.value)} className="mt-1 w-full rounded-2xl border border-border bg-bg px-4 py-3 text-text outline-none transition focus:border-medical focus:bg-white" />
              <span className="mt-1 block text-xs" data-cms="booking.mobileHint">{t("booking.mobileHint")}</span>
            </label>
            {desk.email !== "off" ? (
              <label className="text-sm text-muted">
                <span data-cms="form.email">{t("form.email")}</span>
                <input required={desk.email === "required"} type="email" dir="ltr" value={email} onChange={(event) => setEmail(event.target.value)} className="mt-1 w-full rounded-2xl border border-border bg-bg px-4 py-3 text-text outline-none transition focus:border-medical focus:bg-white" />
              </label>
            ) : null}
            {desk.reason !== "off" ? (
              <label className="text-sm text-muted sm:col-span-2">
                <span data-cms="booking.reason">{t("booking.reason")}</span>
                <textarea required={desk.reason === "required"} value={reason} maxLength={400} onChange={(event) => setReason(event.target.value)} className="mt-1 min-h-28 w-full rounded-2xl border border-border bg-bg px-4 py-3 text-text outline-none transition focus:border-medical focus:bg-white" />
                <span className="mt-1 block text-xs" data-cms="booking.reasonHint">{t("booking.reasonHint")}</span>
              </label>
            ) : null}
          </div>
          <label className="mt-4 flex items-start gap-3 text-sm text-text">
            <input type="checkbox" checked={consent} onChange={(event) => setConsent(event.target.checked)} className="mt-1" />
            <span data-cms="form.consent">{t("form.consent")}</span>
          </label>
        </fieldset>
      </div>

      <aside className="h-fit rounded-[1.75rem] border border-border bg-[#14242c] p-6 text-white shadow-[0_24px_50px_-36px_rgb(24_40_50/0.7)] lg:sticky lg:top-24 lg:col-span-4 md:p-8">
        <h2 className="text-lg font-medium" data-cms="booking.summary">{t("booking.summary")}</h2>
        <p className="mt-6 text-3xl font-medium leading-snug">{chosenDay || "—"}</p>
        <p dir="ltr" className="mt-2 text-4xl font-medium tracking-tight text-[#9ccdd0]">{time || "—"}</p>
        <dl className="mt-6 space-y-4 text-sm">
          <div>
            <dt className="text-white/60" data-cms="booking.stepKind">{t("booking.stepKind")}</dt>
            <dd className="mt-1 text-base">{chosenKind?.label}</dd>
          </div>
          <div>
            <dt className="text-white/60" data-cms="booking.stepService">{t("booking.stepService")}</dt>
            <dd className="mt-1 text-base">{chosen?.label}</dd>
          </div>
          <div>
            <dt className="text-white/60" data-cms="booking.dateLabel">{t("booking.dateLabel")}</dt>
            <dd className="mt-1 text-base">{chosenDay || "—"}</dd>
          </div>
        </dl>
        <ul className="mt-6 space-y-2 border-t border-white/10 pt-5 text-sm leading-relaxed text-white/70">
          <li data-cms="booking.free">{t("booking.free")}</li>
          <li data-cms="booking.confirmCall">{t("booking.confirmCall")}</li>
          <li data-cms="booking.cancelNote">{t("booking.cancelNote")}</li>
        </ul>
        {error ? <p className="mt-4 text-sm text-[#f0c7c3]">{error}</p> : null}
        <button type="submit" disabled={pending} className="mt-6 w-full rounded-full bg-white px-5 py-3.5 text-sm font-medium text-[#14242c] transition hover:bg-[#9ccdd0] disabled:opacity-60">
          <span data-cms="booking.submit" hidden={pending}>{t("booking.submit")}</span>
          <span data-cms="common.sending" hidden={!pending}>{t("common.sending")}</span>
        </button>
        <a href={`/${locale}/appointment/track/`} className="mt-4 block text-center text-sm text-[#9ccdd0]">
          <span data-cms="booking.trackLink">{t("booking.trackLink")}</span>
        </a>
        <a href="/care/" className="mt-2 block text-center text-sm text-white/80">
          <span data-cms="booking.careLink">{t("booking.careLink")}</span>
        </a>
      </aside>
    </form>
  );
}
