"use client";

import {
  createContext,
  useContext,
  useLayoutEffect,
  useSyncExternalStore,
  type ReactNode,
} from "react";

export const HOME_SECTIONS = [
  "hero",
  "intro",
  "treatments",
  "implant",
  "surgeon",
  "reviews",
  "journal",
  "story",
  "anatomy",
  "contact",
] as const;

export type HomeSection = (typeof HOME_SECTIONS)[number];

export type Appearance = {
  motion: "quiet" | "gentle" | "off";
  hero: "portrait" | "studio";
  story: "stacked" | "sticky";
  articles: "cards" | "rows";
  contact: "band" | "full";
  home: HomeSection[];
  hide: HomeSection[];
};

export const defaultAppearance: Appearance = {
  motion: "quiet",
  hero: "portrait",
  story: "sticky",
  articles: "cards",
  contact: "band",
  home: [...HOME_SECTIONS],
  hide: [],
};

const AppearanceContext = createContext<Appearance>(defaultAppearance);

function pick<T extends string>(value: string | undefined, allowed: readonly T[], fallback: T): T {
  return allowed.includes(value as T) ? (value as T) : fallback;
}

export function readAppearance(source?: string): Appearance {
  const meta = typeof document === "undefined"
    ? ""
    : document.querySelector('meta[name="jafari-appearance"]')?.getAttribute("content") ?? "";
  const dataset = typeof document === "undefined" ? undefined : document.documentElement.dataset;
  const composed = dataset?.motion
    ? `motion=${dataset.motion},hero=${dataset.hero ?? "portrait"},story=${dataset.story ?? "sticky"},articles=${dataset.articles ?? "cards"},contact=${dataset.contact ?? "band"},home=${dataset.home ?? ""},hide=${dataset.hide ?? ""}`
    : "";
  const text = source || meta || dataset?.look || composed;
  const parts = Object.fromEntries(text.split(",").filter(Boolean).map((pair) => {
    const [key, value = ""] = pair.split("=");
    return [key, value];
  }));
  const home = (parts.home ?? "").split(".").filter((id): id is HomeSection =>
    (HOME_SECTIONS as readonly string[]).includes(id),
  );
  const hide = (parts.hide ?? "").split(".").filter((id): id is HomeSection =>
    id !== "hero" && (HOME_SECTIONS as readonly string[]).includes(id),
  );
  const ordered = HOME_SECTIONS.filter((id) => home.includes(id));
  for (const id of HOME_SECTIONS) {
    if (!ordered.includes(id)) ordered.push(id);
  }
  return {
    motion: pick(parts.motion, ["quiet", "gentle", "off"] as const, "quiet"),
    hero: pick(parts.hero, ["portrait", "studio"] as const, "portrait"),
    story: pick(parts.story, ["stacked", "sticky"] as const, "sticky"),
    articles: pick(parts.articles, ["cards", "rows"] as const, "cards"),
    contact: pick(parts.contact, ["band", "full"] as const, "band"),
    home: home.length ? ordered : [...HOME_SECTIONS],
    hide,
  };
}

export function appearanceToken(look: Appearance): string {
  return [
    `motion=${look.motion}`,
    `hero=${look.hero}`,
    `story=${look.story}`,
    `articles=${look.articles}`,
    `contact=${look.contact}`,
    `home=${look.home.join(".")}`,
    `hide=${look.hide.join(".")}`,
  ].join(",");
}

let cachedToken = "";
let cachedLook: Appearance = defaultAppearance;

function clientLook(): Appearance {
  const next = readAppearance();
  const token = appearanceToken(next);
  if (token === cachedToken) return cachedLook;
  cachedToken = token;
  cachedLook = next;
  return cachedLook;
}

function subscribeAppearance() {
  return () => {};
}

export function AppearanceProvider({ children }: { children: ReactNode }) {
  const look = useSyncExternalStore(subscribeAppearance, clientLook, () => defaultAppearance);

  useLayoutEffect(() => {
    const next = clientLook();
    const root = document.documentElement;
    root.dataset.motion = next.motion;
    root.dataset.hero = next.hero;
    root.dataset.story = next.story;
    root.dataset.articles = next.articles;
    root.dataset.contact = next.contact;
    root.dataset.look = appearanceToken(next);
  }, [look]);

  return <AppearanceContext.Provider value={look}>{children}</AppearanceContext.Provider>;
}

export function useAppearance(): Appearance {
  return useContext(AppearanceContext);
}
