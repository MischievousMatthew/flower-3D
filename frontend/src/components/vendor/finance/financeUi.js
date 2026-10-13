// Shared bits for the Finance & business pages: the date range everyone uses,
// money formatting and how each kind of entry looks.
import { reactive } from "vue";

const pad = (n) => String(n).padStart(2, "0");
export const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

export const PRESETS = [
  { key: "this_month", label: "This month" },
  { key: "last_month", label: "Last month" },
  { key: "last_30", label: "Last 30 days" },
  { key: "this_quarter", label: "This quarter" },
  { key: "this_year", label: "This year" },
  { key: "custom", label: "Custom" },
];

export function presetRange(key) {
  const now = new Date();
  const y = now.getFullYear();
  const m = now.getMonth();
  switch (key) {
    case "last_month":
      return { from: ymd(new Date(y, m - 1, 1)), to: ymd(new Date(y, m, 0)) };
    case "last_30": {
      const start = new Date(now);
      start.setDate(start.getDate() - 29);
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
    if (saved?.preset && saved.from && saved.to) {
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
