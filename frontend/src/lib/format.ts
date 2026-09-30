import type { Language } from "../i18n";
const locale = (language: Language) => (language === "bn" ? "bn-BD" : "en-BD");
/** Exact paisa formatting, including values beyond Number.MAX_SAFE_INTEGER. */
export function formatMoney(minor: string, language: Language = "bn"): string {
  if (!/^\d+$/.test(minor))
    throw new Error("Money must be a nonnegative integer minor-unit string");
  const value = BigInt(minor);
  const whole = new Intl.NumberFormat(locale(language), {
    maximumFractionDigits: 0,
  }).format(value / 100n);
  const digits = String(value % 100n)
    .padStart(2, "0")
    .replace(/\d/g, (d) => (language === "bn" ? "০১২৩৪৫৬৭৮৯"[Number(d)] : d));
  return `৳${whole}.${digits}`;
}
export function formatDate(
  value: string | null,
  language: Language = "bn",
): string {
  if (value === null) return "—";
  const dateOnly = /^\d{4}-\d{2}-\d{2}$/.test(value);
  if (!dateOnly && !/T.*(?:Z|[+-]\d{2}:\d{2})$/.test(value))
    throw new Error("Date needs an explicit timezone");
  const date = new Date(dateOnly ? `${value}T00:00:00+06:00` : value);
  if (
    Number.isNaN(date.valueOf()) ||
    (dateOnly &&
      new Intl.DateTimeFormat("en-CA", {
        timeZone: "Asia/Dhaka",
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
      }).format(date) !== value)
  )
    throw new Error("Invalid date");
  return new Intl.DateTimeFormat(locale(language), {
    timeZone: "Asia/Dhaka",
    day: "numeric",
    month: "short",
    year: "numeric",
  }).format(date);
}
