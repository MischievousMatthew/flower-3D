// Shared wording and helpers for the supplier pages (list, profile, form).

export const SUPPLIER_TYPES = [
  { value: "flower_farm", label: "Flower farm", plural: "Flower farms", icon: "flower", tone: "green", hint: "Grows and harvests flowers or greenery" },
  { value: "wholesaler", label: "Wholesaler", plural: "Wholesalers", icon: "store", tone: "blue", hint: "Sells flowers from many growers, e.g. Dangwa" },
  { value: "packaging", label: "Packaging", plural: "Packaging", icon: "box", tone: "violet", hint: "Wraps, boxes, ribbons, sleeves" },
  { value: "supplies", label: "Supply vendor", plural: "Supply vendors", icon: "tag", tone: "amber", hint: "Foam, vases, tools, cards and other supplies" },
];

export const typeOf = (value) => SUPPLIER_TYPES.find((t) => t.value === value) ?? null;

export const STATUS = {
  active: { label: "Active", tone: "green" },
  inactive: { label: "Inactive", tone: "slate" },
  blacklisted: { label: "Blacklisted", tone: "red" },
};

export const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
export const shortDay = (d) => d.slice(0, 3);

export const UNITS = [
  { value: "stem", label: "per stem" },
  { value: "bunch", label: "per bunch" },
  { value: "box", label: "per box" },
  { value: "piece", label: "per piece" },
  { value: "roll", label: "per roll" },
  { value: "pack", label: "per pack" },
  { value: "kg", label: "per kg" },
];
export const unitLabel = (u) => UNITS.find((x) => x.value === u)?.label ?? `per ${u}`;

export const CATALOG_CATEGORIES = [
  { value: "flowers", label: "Flowers" },
  { value: "greenery", label: "Greenery" },
  { value: "packaging", label: "Packaging" },
  { value: "supplies", label: "Supplies" },
];

/** "Tue & Fri", "Mon–Fri", "Every day" */
export function daysText(days) {
  const list = DAYS.filter((d) => (days ?? []).includes(d));
  if (!list.length) return "";
  if (list.length === 7) return "Every day";
  const idx = list.map((d) => DAYS.indexOf(d));
  const consecutive = idx.every((v, i) => i === 0 || v === idx[i - 1] + 1);
  if (consecutive && list.length >= 3) return `${shortDay(list[0])}–${shortDay(list.at(-1))}`;
  return list.length === 2 ? `${shortDay(list[0])} & ${shortDay(list[1])}` : list.map(shortDay).join(", ");
}

export const leadText = (days) =>
  days === null || days === undefined || days === "" ? "" : Number(days) === 0 ? "Same day" : `${days} ${Number(days) === 1 ? "day" : "days"}`;

/** "2:00 PM" from "14:00" */
export function timeText(t) {
  if (!t) return "";
  const [h, m] = t.split(":").map(Number);
  return `${h % 12 || 12}:${String(m).padStart(2, "0")} ${h >= 12 ? "PM" : "AM"}`;
}

// Letters only, so "Wrap & Ribbon Co." becomes "WR", not "W&"
export const initials = (name) =>
  (name || "?").split(/\s+/).map((w) => w.replace(/[^\p{L}\p{N}]/gu, "")).filter(Boolean).map((w) => w[0]).slice(0, 2).join("").toUpperCase() || "?";
