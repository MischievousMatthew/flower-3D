// Shared bits for the Finance & business pages: the date range everyone uses,
// money formatting and how each kind of entry looks.
import { reactive } from "vue";

const pad = (n) => String(n).padStart(2, "0");
export const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

export const PRESETS = [
  // short: the word on the capsule slider (the period picker)
  { key: "today", label: "Today", short: "Day" },
  { key: "this_week", label: "This week", short: "Week" },
  { key: "this_month", label: "This month", short: "Month" },
  { key: "this_quarter", label: "This quarter", short: "Quarter" },
  { key: "this_year", label: "This year", short: "Year" },
  { key: "custom", label: "Custom", short: "Custom" },
];

export function presetRange(key) {
  const now = new Date();
  const y = now.getFullYear();
  const m = now.getMonth();
  switch (key) {
    case "today":
      return { from: ymd(now), to: ymd(now) };
    case "this_week": {
      // Weeks start on Monday, like the server's weekly totals
      const start = new Date(now);
      start.setDate(start.getDate() - ((start.getDay() + 6) % 7));
      return { from: ymd(start), to: ymd(now) };
    }
    case "this_quarter":
      return { from: ymd(new Date(y, Math.floor(m / 3) * 3, 1)), to: ymd(now) };
    case "this_year":
      return { from: ymd(new Date(y, 0, 1)), to: ymd(now) };
    default:
      return { from: ymd(new Date(y, m, 1)), to: ymd(now) };
  }
}

const STORE = "bc.vendor.finance.range";
function initial() {
  try {
    const saved = JSON.parse(sessionStorage.getItem(STORE) || "null");
    // A preset that no longer exists (e.g. the old "Last 30 days") falls back to This month
    if (saved?.preset && saved.from && saved.to && PRESETS.some((p) => p.key === saved.preset)) {
      return saved.preset === "custom" ? saved : { preset: saved.preset, ...presetRange(saved.preset) };
    }
  } catch {
    /* ignore */
  }
  return { preset: "this_month", ...presetRange("this_month") };
}

/** One date range shared by every finance page, so switching tabs keeps it. */
export const financeRange = reactive(initial());
export function setRange(next) {
  Object.assign(financeRange, next);
  try {
    sessionStorage.setItem(STORE, JSON.stringify({ ...financeRange }));
  } catch {
    /* private mode */
  }
}

/** "Nov 14, 2026" for one day, else "Nov 10 – Nov 14, 2026" */
export function rangeLabel(range = financeRange) {
  const f = new Date(`${range.from}T00:00:00`);
  const t = new Date(`${range.to}T00:00:00`);
  const opts = { month: "short", day: "numeric" };
  const end = t.toLocaleDateString("en-PH", { ...opts, year: "numeric" });
  if (range.from === range.to) return end;
  return `${f.toLocaleDateString("en-PH", f.getFullYear() === t.getFullYear() ? opts : { ...opts, year: "numeric" })} – ${end}`;
}

/** What the CSV / PDF buttons will download, e.g. "This week (Nov 10 – Nov 14, 2026)" */
export function exportTitle(format, range = financeRange) {
  const preset = PRESETS.find((p) => p.key === range.preset);
  const name = preset && preset.key !== "custom" ? `${preset.label} (${rangeLabel(range)})` : rangeLabel(range);
  return `Download ${format.toUpperCase()}: ${name} only`;
}

/** A download name that says which period it covers */
export function exportFileName(prefix, format, range = financeRange) {
  const span = range.from === range.to ? range.from : `${range.from}-to-${range.to}`;
  const tag = range.preset && range.preset !== "custom" ? `${range.preset.replace(/_/g, "-")}-` : "";
  return `${prefix}-${tag}${span}.${format}`;
}

const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
export const money = (v) => peso.format(Number(v || 0));
/** ₱1.2k / ₱3.4M for chart labels */
export const shortMoney = (v) => {
  const n = Math.abs(Number(v || 0));
  const sign = Number(v) < 0 ? "−" : "";
  if (n >= 1_000_000) return `${sign}₱${(n / 1_000_000).toFixed(1).replace(/\.0$/, "")}M`;
  if (n >= 1000) return `${sign}₱${(n / 1000).toFixed(1).replace(/\.0$/, "")}k`;
  return `${sign}₱${Math.round(n)}`;
};

export const KIND = {
  sale: { label: "Sales", tone: "green", icon: "receipt" },
  refund: { label: "Refunds", tone: "pink", icon: "restore" },
  stock: { label: "Stock purchases", tone: "blue", icon: "box" },
  stock_refund: { label: "Supplier refunds", tone: "teal", icon: "restore" },
  payroll: { label: "Payroll", tone: "violet", icon: "user" },
  expense: { label: "Expenses", tone: "amber", icon: "card" },
  withdrawal: { label: "Withdrawals", tone: "slate", icon: "truck" },
  adjustment: { label: "Adjustments", tone: "slate", icon: "edit" },
};

export const PAYMENT_LABEL = { cod: "Cash on delivery", ewallet: "E-wallet", gcash: "GCash", maya: "Maya", bank_transfer: "Bank transfer", cash: "Cash", card: "Card", other: "Other" };

export const formatDate = (iso, withTime = false) =>
  iso
    ? new Date(iso).toLocaleString("en-PH", withTime
      ? { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }
      : { month: "short", day: "numeric", year: "numeric" })
    : "";

/** % change, or null when there's nothing to compare with */
export const change = (now, before) => (before ? Math.round(((now - before) / Math.abs(before)) * 1000) / 10 : null);
