// Shared bits for the vendor Customer relationships pages.

/** "route:VendorOrderDetails?order=12" → a router location; other links pass through */
export function notificationTarget(link) {
  if (!link) return null;
  if (!link.startsWith("route:")) return link;
  const [name, qs] = link.slice(6).split("?");
  const query = Object.fromEntries(new URLSearchParams(qs ?? ""));
  return { name, query };
}

export const NOTIFICATION_KINDS = {
  order: { label: "Orders", icon: "receipt", tone: "green" },
  payment: { label: "Payments", icon: "card", tone: "blue" },
  stock: { label: "Stock", icon: "box", tone: "amber" },
  message: { label: "Messages", icon: "chat", tone: "violet" },
  inquiry: { label: "Questions", icon: "help", tone: "teal" },
  review: { label: "Reviews", icon: "star", tone: "pink" },
  return: { label: "Returns", icon: "restore", tone: "red" },
  announcement: { label: "BloomCraft", icon: "megaphone", tone: "slate" },
  support: { label: "Support", icon: "note", tone: "slate" },
};

export function timeAgo(iso) {
  if (!iso) return "";
  const diff = (Date.now() - new Date(iso).getTime()) / 1000;
  if (diff < 45) return "just now";
  if (diff < 3600) return `${Math.max(1, Math.floor(diff / 60))}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 86400 * 7) {
    const d = Math.floor(diff / 86400);
    return d === 1 ? "yesterday" : `${d}d ago`;
  }
  return new Date(iso).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
}

export function dayHeading(iso) {
  const d = new Date(iso);
  const today = new Date();
  const y = new Date();
  y.setDate(today.getDate() - 1);
  if (d.toDateString() === today.toDateString()) return "Today";
  if (d.toDateString() === y.toDateString()) return "Yesterday";
  return d.toLocaleDateString("en-PH", { weekday: "long", month: "long", day: "numeric", year: d.getFullYear() === today.getFullYear() ? undefined : "numeric" });
}

export const clock = (iso) => new Date(iso).toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" });

export const initialsOf = (name) =>
  String(name ?? "?").split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase() || "?";

const PALETTE = ["#48bb78", "#4299e1", "#9f7aea", "#ed8936", "#ed64a6", "#38b2ac", "#e53e3e", "#718096"];
export function colorFor(name) {
  let n = 0;
  for (const ch of String(name ?? "")) n = (n * 31 + ch.charCodeAt(0)) >>> 0;
  return PALETTE[n % PALETTE.length];
}

/** Ready answers vendors use a lot; the composer inserts them. */
export const QUICK_REPLIES = [
  "Hi! Thanks for reaching out. How can I help?",
  "Yes, that's available. Would you like me to reserve it for you?",
  "Your order is being prepared and will be ready on schedule.",
  "We deliver within the city. The fee is shown at checkout.",
  "Thank you for your order! Let us know if you need anything else.",
];

export const ORDER_STATUS = {
  pending: ["Pending", "amber"],
  confirmed: ["Confirmed", "blue"],
  processing: ["Preparing", "blue"],
  ready: ["Ready", "teal"],
  out_for_delivery: ["Out for delivery", "violet"],
  delivered: ["Delivered", "green"],
  completed: ["Completed", "green"],
  cancelled: ["Cancelled", "slate"],
  refunded: ["Refunded", "red"],
  failed: ["Payment failed", "red"],
  payment_failed: ["Payment failed", "red"],
};
