const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

const JALALI_MONTHS = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];
const GREGORIAN_MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

function div(value: number, by: number): number {
  return Math.trunc(value / by);
}

function jalCal(year: number): { leap: number; gy: number; march: number } {
  const gy = year + 621;
  let leapJ = -14;
  let jp = BREAKS[0];
  let jump = 0;
  if (year < jp || year >= BREAKS[BREAKS.length - 1]) {
    throw new Error("Invalid Jalali year");
  }
  for (let i = 1; i < BREAKS.length; i += 1) {
    const jm = BREAKS[i];
    jump = jm - jp;
    if (year < jm) break;
    leapJ += div(jump, 33) * 8 + div(jump % 33, 4);
    jp = jm;
  }
  let n = year - jp;
  leapJ += div(n, 33) * 8 + div((n % 33) + 3, 4);
  if (jump % 33 === 4 && jump - n === 4) leapJ += 1;
  const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
  const march = 20 + leapJ - leapG;
  if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
  let leap = (((n + 1) % 33) - 1) % 4;
  if (leap === -1) leap = 4;
  return { leap, gy, march };
}

function gregorianDayNumber(gy: number, gm: number, gd: number): number {
  let day = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * ((gm + 9) % 12) + 2, 5) + gd - 34840408;
  day = day - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
  return day;
}

function dayNumberToGregorian(jdn: number): { gy: number; gm: number; gd: number } {
  let j = 4 * jdn + 139361631;
  j += div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
  const i = div((j % 1461), 4) * 5 + 308;
  const gd = div(i % 153, 5) + 1;
  const gm = (div(i, 153) % 12) + 1;
  const gy = div(j, 1461) - 100100 + div(8 - gm, 6);
  return { gy, gm, gd };
}

function jalaliDayNumber(jy: number, jm: number, jd: number): number {
  const cal = jalCal(jy);
  return gregorianDayNumber(cal.gy, 3, cal.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
}

export function gregorianToJalali(gy: number, gm: number, gd: number): { jy: number; jm: number; jd: number } {
  const jdn = gregorianDayNumber(gy, gm, gd);
  const g = dayNumberToGregorian(jdn);
  let jy = g.gy - 621;
  const cal = jalCal(jy);
  const start = gregorianDayNumber(g.gy, 3, cal.march);
  let k = jdn - start;
  if (k >= 0) {
    if (k <= 185) {
      return { jy, jm: 1 + div(k, 31), jd: (k % 31) + 1 };
    }
    k -= 186;
  } else {
    jy -= 1;
    k += 179;
    if (cal.leap === 1) k += 1;
  }
  return { jy, jm: 7 + div(k, 30), jd: (k % 30) + 1 };
}

/** Months 1–6 have 31 days, 7–11 have 30, and Esfand has 29 or 30. */
export function jalaliMonthLength(year: number, month: number): number {
  if (month <= 6) return 31;
  if (month <= 11) return 30;
  return jalCal(year).leap === 0 ? 30 : 29;
}

export function jalaliToGregorian(jy: number, jm: number, jd: number): { gy: number; gm: number; gd: number } {
  return dayNumberToGregorian(jalaliDayNumber(jy, jm, jd));
}

export function faDigits(value: string): string {
  return value.replace(/\d/g, (digit) => "۰۱۲۳۴۵۶۷۸۹"[Number(digit)]);
}

type Parsed = { year: number; month: number; day: number; hour?: number; minute?: number; jalali: boolean };

function parseWhen(input: string): Parsed | null {
  const match = input.trim().match(/^(\d{4})[/-](\d{1,2})[/-](\d{1,2})(?:[T\s](\d{1,2}):(\d{2}))?/);
  if (!match) return null;
  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  if (month < 1 || month > 12 || day < 1 || day > 31) return null;
  return {
    year,
    month,
    day,
    hour: match[4] === undefined ? undefined : Number(match[4]),
    minute: match[5] === undefined ? undefined : Number(match[5]),
    jalali: year < 1700,
  };
}

/** Persian pages show a Shamsi date. English pages show a Gregorian date. */
export function formatWhen(input: string, locale: string): string {
  const parsed = parseWhen(input);
  if (!parsed) return input;
  const gregorian = parsed.jalali
    ? jalaliToGregorian(parsed.year, parsed.month, parsed.day)
    : { gy: parsed.year, gm: parsed.month, gd: parsed.day };
  const time = parsed.hour === undefined ? "" : `، ${String(parsed.hour).padStart(2, "0")}:${String(parsed.minute).padStart(2, "0")}`;
  if (locale === "fa") {
    const jalali = parsed.jalali
      ? { jy: parsed.year, jm: parsed.month, jd: parsed.day }
      : gregorianToJalali(gregorian.gy, gregorian.gm, gregorian.gd);
    const body = `${jalali.jd} ${JALALI_MONTHS[jalali.jm - 1]} ${jalali.jy}`;
    return faDigits(time ? `${body}${time}` : body);
  }
  const clock = parsed.hour === undefined ? "" : `, ${String(parsed.hour).padStart(2, "0")}:${String(parsed.minute).padStart(2, "0")}`;
  if (locale === "ar" || locale === "zh") {
    const formatted = new Intl.DateTimeFormat(locale === "ar" ? "ar" : "zh-CN", {
      day: "numeric",
      month: "long",
      year: "numeric",
      timeZone: "UTC",
    }).format(new Date(Date.UTC(gregorian.gy, gregorian.gm - 1, gregorian.gd)));
    return clock ? `${formatted}${clock}` : formatted;
  }
  return `${gregorian.gd} ${GREGORIAN_MONTHS[gregorian.gm - 1]} ${gregorian.gy}${clock}`;
}

export function formatYear(date: Date, locale: string): string {
  if (locale === "fa") {
    const jalali = gregorianToJalali(date.getFullYear(), date.getMonth() + 1, date.getDate());
    return faDigits(String(jalali.jy));
  }
  return String(date.getFullYear());
}

/** A stored Jalali year stays Shamsi in Persian and becomes the matching Gregorian year in English. */
export function formatYearLabel(value: string, locale: string): string {
  const western = value.replace(/[۰-۹]/g, (digit) => String("۰۱۲۳۴۵۶۷۸۹".indexOf(digit)));
  if (!/^\d{4}$/.test(western)) return locale === "fa" ? faDigits(value) : value;
  const year = Number(western);
  if (locale !== "fa") {
    return year < 1700 ? String(jalaliToGregorian(year, 1, 1).gy) : String(year);
  }
  return faDigits(String(year));
}
