"use client";

import { useState } from "react";
import { useLocale, useTranslations } from "next-intl";

type Turn = { role: "person" | "desk"; text: string };

export function ConsultChat() {
  const t = useTranslations("consult");
  const locale = useLocale();
  const prompts = [
    { q: t("q1"), a: t("a1") },
    { q: t("q2"), a: t("a2") },
    { q: t("q3"), a: t("a3") },
    { q: t("q4"), a: t("a4") },
  ];
  const [turns, setTurns] = useState<Turn[]>([{ role: "desk", text: t("greeting") }]);
  const [draft, setDraft] = useState("");
  const [pending, setPending] = useState(false);

  function ask(question: string, answer?: string) {
    const text = question.trim().slice(0, 800);
    if (!text || pending) return;
    setTurns((current) => [...current, { role: "person", text }]);
    setDraft("");
    const known = answer ?? prompts.find((item) => item.q === text)?.a;
    if (known) {
      setTurns((current) => [...current, { role: "desk", text: known }]);
      return;
    }
    setPending(true);
    const body = new FormData();
    body.set("locale", locale);
    body.set("message", text);
    fetch("/cms/chat.php", { method: "POST", body })
      .then(async (response) => {
        const data = (await response.json()) as { ok?: boolean; reply?: string };
        const reply = data.ok && data.reply ? data.reply : t("fallback");
        setTurns((current) => [...current, { role: "desk", text: reply }]);
      })
      .catch(() => {
        setTurns((current) => [...current, { role: "desk", text: t("fallback") }]);
      })
      .finally(() => setPending(false));
  }

  return (
    <div className="flex min-h-[40rem] flex-col overflow-hidden rounded-[1.75rem] border border-border bg-surface shadow-[0_30px_60px_-40px_rgb(24_40_50/0.55)]">
      <div className="flex items-center justify-between gap-4 bg-[#14242c] px-5 py-4 text-white">
        <div>
          <p className="flex items-center gap-2 text-sm font-medium">
            <span className="size-2 rounded-full bg-[#9ccdd0] motion-safe:animate-pulse" aria-hidden />
            <span data-cms="consult.panelTitle">{t("panelTitle")}</span>
          </p>
          <p className="mt-1 text-xs text-white/70" data-cms="consult.panelStatus">{t("panelStatus")}</p>
        </div>
        <button
          type="button"
          className="rounded-full border border-white/20 px-3 py-1.5 text-sm text-white/80 transition hover:border-white hover:text-white"
          onClick={() => setTurns([{ role: "desk", text: t("greeting") }])}
        >
          <span data-cms="consult.newChat">{t("newChat")}</span>
        </button>
      </div>
      <div className="flex flex-1 flex-col gap-4 overflow-y-auto bg-[radial-gradient(42rem_16rem_at_100%_0%,rgb(156_205_208/0.28),transparent_60%)] px-5 py-6">
        {turns.map((turn, index) => (
          <p
            key={`${turn.role}-${index}`}
            className={
              turn.role === "person"
                ? "ms-auto max-w-[80%] rounded-[1.4rem] rounded-ee-md bg-medical px-4 py-3 text-sm leading-relaxed text-white"
                : "me-auto max-w-[80%] rounded-[1.4rem] rounded-es-md border border-border bg-white px-4 py-3 text-sm leading-relaxed text-text"
            }
            {...(index === 0 && turn.role === "desk" ? { "data-cms": "consult.greeting" } : {})}
          >
            {turn.text}
          </p>
        ))}
        {pending ? <p className="text-xs text-muted" data-cms="consult.thinking">{t("thinking")}</p> : null}
      </div>
      <div className="grid gap-2 border-t border-border p-4 sm:grid-cols-2">
        {prompts.map((item, index) => (
          <button
            key={item.q}
            type="button"
            className="rounded-2xl border border-border bg-bg px-4 py-3 text-start text-sm leading-relaxed text-text transition duration-300 hover:border-medical hover:bg-white"
            onClick={(event) => {
              const question = event.currentTarget.querySelector("[data-cms]")?.textContent?.trim();
              const answer = event.currentTarget.querySelector("[data-answer]")?.textContent?.trim();
              ask(question || item.q, answer || item.a);
            }}
          >
            <span data-cms={`consult.q${index + 1}`}>{item.q}</span>
            <span hidden data-answer data-cms={`consult.a${index + 1}`}>{item.a}</span>
          </button>
        ))}
      </div>
      <form
        className="border-t border-border p-4"
        onSubmit={(event) => {
          event.preventDefault();
          ask(draft);
        }}
      >
        <div className="flex gap-2">
          <label className="sr-only" htmlFor="consult-draft">{t("placeholder")}</label>
          <input
            id="consult-draft"
            value={draft}
            maxLength={800}
            onChange={(event) => setDraft(event.target.value)}
            placeholder={t("placeholder")}
            className="min-w-0 flex-1 rounded-full border border-border bg-bg px-4 py-3 text-sm text-text outline-none transition focus:border-medical focus:bg-white"
          />
          <button type="submit" className="rounded-full bg-[#14242c] px-5 py-3 text-sm font-medium text-white transition hover:bg-medical" data-cms="consult.send">
            {t("send")}
          </button>
        </div>
        <p className="mt-2 text-xs text-muted">
          <span data-cms="consult.disclaimer">{t("disclaimer")}</span>
          <span dir="ltr"> · {draft.length.toLocaleString(locale === "fa" ? "fa-IR" : locale === "ar" ? "ar" : locale === "zh" ? "zh-CN" : "en-US")}/{Number(800).toLocaleString(locale === "fa" ? "fa-IR" : locale === "ar" ? "ar" : locale === "zh" ? "zh-CN" : "en-US")}</span>
        </p>
      </form>
    </div>
  );
}
