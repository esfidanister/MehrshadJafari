export type BookingKind = { key: string; label: string; minutes: number };
export type BookingTopic = { key: string; label: string };
export type BookingBusy = { day: string; start: string; minutes: number };
export type BookingPlace = {
  key: string;
  label: string;
  kind: "clinic" | "hospital" | "other";
  address: string;
  weekdays: number[];
  start: string;
  end: string;
};

export type BookingDeskData = {
  slots: string[];
  closedWeekdays: number[];
  blocked: string[];
  capacity: number;
  horizon: number;
  leadDays: number;
  open: boolean;
  cutoff: number;
  email: "off" | "optional" | "required";
  reason: "off" | "optional" | "required";
  busy: BookingBusy[];
  kinds: BookingKind[];
  topics: BookingTopic[];
  places: BookingPlace[];
};

const DEFAULT_SLOTS = ["16:00", "16:30", "17:00", "17:30", "18:00", "18:30", "19:00", "19:30", "20:00"];

function clock(value: string): number {
  const [hour, minute] = value.split(":").map(Number);
  return hour * 60 + minute;
}

export function timeCovers(start: string, minutes: number, slot: string): boolean {
  const from = clock(start);
  const point = clock(slot);
  const span = Math.max(10, minutes);
  return Number.isFinite(from) && Number.isFinite(point) && point >= from && point < from + span;
}

export function slotTaken(day: string, time: string, minutes: number, desk: BookingDeskData): boolean {
  const wanted = desk.slots.filter((slot) => timeCovers(time, minutes, slot));
  return wanted.some((slot) => {
    const count = desk.busy.filter((item) => item.day === day && timeCovers(item.start, item.minutes, slot)).length;
    return count >= desk.capacity;
  });
}

function namedRows(value: unknown): BookingTopic[] {
  if (!Array.isArray(value)) return [];
  return value.flatMap((item) => {
    if (!item || typeof item !== "object") return [];
    const row = item as { key?: unknown; label?: unknown };
    if (typeof row.key !== "string" || typeof row.label !== "string") return [];
    return [{ key: row.key, label: row.label }];
  });
}

export function readBookingDesk(fallbackKinds: BookingKind[], fallbackTopics: BookingTopic[]): BookingDeskData {
  const empty: BookingDeskData = {
    slots: DEFAULT_SLOTS,
    closedWeekdays: [5],
    blocked: [],
    capacity: 1,
    horizon: 90,
    leadDays: 0,
    open: true,
    cutoff: 24,
    email: "optional",
    reason: "optional",
    busy: [],
    kinds: fallbackKinds,
    topics: fallbackTopics,
    places: [],
  };
  if (typeof document === "undefined") return empty;
  const node = document.getElementById("jafari-booking");
  if (!node?.textContent) return empty;
  try {
    const parsed = JSON.parse(node.textContent) as Partial<BookingDeskData> & { slots?: unknown; kinds?: unknown; busy?: unknown; topics?: unknown; places?: unknown };
    const slots = Array.isArray(parsed.slots)
      ? parsed.slots.filter((item): item is string => typeof item === "string" && /^\d{2}:\d{2}$/.test(item))
      : [];
    const kinds = Array.isArray(parsed.kinds)
      ? parsed.kinds.flatMap((item) => {
          if (!item || typeof item !== "object") return [];
          const row = item as { key?: unknown; label?: unknown; minutes?: unknown };
          if (typeof row.key !== "string" || typeof row.label !== "string") return [];
          const minutes = typeof row.minutes === "number" ? row.minutes : 30;
          return [{ key: row.key, label: row.label, minutes }];
        })
      : [];
    const topics = namedRows(parsed.topics);
    const places = Array.isArray(parsed.places)
      ? parsed.places.flatMap((item) => {
          if (!item || typeof item !== "object") return [];
          const row = item as { key?: unknown; label?: unknown; kind?: unknown; address?: unknown; weekdays?: unknown; start?: unknown; end?: unknown };
          if (typeof row.key !== "string" || typeof row.label !== "string" || !/^\d{2}:\d{2}$/.test(String(row.start)) || !/^\d{2}:\d{2}$/.test(String(row.end))) return [];
          const kind: BookingPlace["kind"] = row.kind === "hospital" ? "hospital" : row.kind === "other" ? "other" : "clinic";
          const weekdays = Array.isArray(row.weekdays) ? row.weekdays.filter((day): day is number => typeof day === "number" && day >= 0 && day <= 6) : [];
          if (!weekdays.length) return [];
          return [{ key: row.key, label: row.label, kind, address: typeof row.address === "string" ? row.address : "", weekdays, start: String(row.start), end: String(row.end) }];
        })
      : [];
    const busy = Array.isArray(parsed.busy)
      ? parsed.busy.flatMap((item) => {
          if (!item || typeof item !== "object") return [];
          const row = item as { day?: unknown; start?: unknown; minutes?: unknown };
          if (typeof row.day !== "string" || typeof row.start !== "string") return [];
          return [{ day: row.day, start: row.start, minutes: typeof row.minutes === "number" ? row.minutes : 30 }];
        })
      : [];
    const horizon = typeof parsed.horizon === "number" && parsed.horizon >= 7 ? Math.min(365, Math.round(parsed.horizon)) : 90;
    const leadDays = typeof parsed.leadDays === "number" && parsed.leadDays >= 0 ? Math.min(60, Math.round(parsed.leadDays)) : 0;
    const email = parsed.email === "off" || parsed.email === "required" ? parsed.email : "optional";
    const reason = parsed.reason === "off" || parsed.reason === "required" ? parsed.reason : "optional";
    return {
      slots: slots.length ? slots : DEFAULT_SLOTS,
      closedWeekdays: Array.isArray(parsed.closedWeekdays)
        ? parsed.closedWeekdays.filter((day): day is number => typeof day === "number" && day >= 0 && day <= 6)
        : [5],
      blocked: Array.isArray(parsed.blocked) ? parsed.blocked.filter((day): day is string => typeof day === "string") : [],
      capacity: typeof parsed.capacity === "number" && parsed.capacity > 0 ? Math.min(8, parsed.capacity) : 1,
      horizon,
      leadDays: leadDays > horizon ? horizon : leadDays,
      open: parsed.open !== false,
      cutoff: typeof parsed.cutoff === "number" && parsed.cutoff >= 0 && parsed.cutoff <= 23 ? Math.round(parsed.cutoff) : 24,
      email,
      reason,
      busy,
      kinds: kinds.length ? kinds : fallbackKinds,
      topics: topics.length ? topics : fallbackTopics,
      places,
    };
  } catch {
    return empty;
  }
}
