// Words, colours and date helpers shared by the Orders & Fulfillment pages.

export const STATUS_TONE = {
  pending: "amber",
  to_processed: "blue",
  to_ship: "violet",
  to_received: "teal",
  completed: "green",
  cancelled: "slate",
  returned: "red",
  refunded: "red",
};

// One line explaining each stage, shown under the status on the details page
export const STATUS_HINT = {
  pending: "New order. Confirm it to start preparing.",
  to_processed: "Being prepared. Mark it ready once it's arranged and packed.",
  to_ship: "Ready. Send it out when the rider leaves.",
  to_received: "On its way to the customer.",
  completed: "Delivered.",
  cancelled: "This order was cancelled.",
  returned: "The customer sent this order back.",
  refunded: "This order was refunded.",
};

export const CASE_STAGE = {
  awaiting_vendor: "Needs your reply",
  awaiting_customer: "Waiting for customer",
  awaiting_return: "Item coming back",
  awaiting_refund: "Send the refund",
  confirm_refund: "Refund sent",
  awaiting_replacement: "Send replacement",
  confirm_replacement: "Replacement sent",
  completed: "Settled",
  rejected: "Declined",
  withdrawn: "Withdrawn",
};

export const PAYMENT_LABEL = { cod: "Cash on delivery", ewallet: "E-wallet", gcash: "GCash", maya: "Maya", card: "Card" };

const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
export const money = (v) => peso.format(Number(v || 0));

const MANILA = "Asia/Manila";
export const todayKey = () => new Date().toLocaleDateString("en-CA", { timeZone: MANILA });
const addDays = (key, n) => {
  const d = new Date(`${key}T00:00:00`);
  d.setDate(d.getDate() + n);
  return d.toLocaleDateString("en-CA");
};

/** "Today", "Tomorrow", "Yesterday", "Mon, Sep 29" */
export function dayLabel(key, { long = false } = {}) {
  if (!key) return "No date";
  const today = todayKey();
  if (key === today) return "Today";
  if (key === addDays(today, 1)) return "Tomorrow";
  if (key === addDays(today, -1)) return "Yesterday";
  const d = new Date(`${key}T00:00:00`);
  return d.toLocaleDateString("en-PH", long
    ? { weekday: "long", month: "long", day: "numeric" }
    : { weekday: "short", month: "short", day: "numeric" });
}

/** Whole days from today to a date (negative = past) */
export function daysFromToday(key) {
  if (!key) return null;
  return Math.round((new Date(`${key}T00:00:00`) - new Date(`${todayKey()}T00:00:00`)) / 86_400_000);
}

export function timeAgo(iso) {
  if (!iso) return "";
  const mins = Math.round((Date.now() - new Date(iso)) / 60000);
  if (mins < 1) return "just now";
  if (mins < 60) return `${mins} min ago`;
  const hours = Math.round(mins / 60);
  if (hours < 24) return `${hours} h ago`;
  const days = Math.round(hours / 24);
  return days < 30 ? `${days} d ago` : new Date(iso).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
}

export const formatDateTime = (iso) =>
  iso ? new Date(iso).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "";

/** Is this order late for its delivery date? */
export const isLate = (o) =>
  !!o.delivery_date && !["completed", "cancelled", "returned", "refunded"].includes(o.status) && daysFromToday(o.delivery_date) < 0;

/** Should preparation have started by now? */
export const shouldPrepare = (o) =>
  ["pending", "to_processed"].includes(o.status) && !!o.prepare_by && daysFromToday(o.prepare_by) <= 0;

export function parseCustomizations(value) {
  if (value && typeof value === "object") return value;
  try {
    return typeof value === "string" ? JSON.parse(value) : {};
  } catch {
    return {};
  }
}
