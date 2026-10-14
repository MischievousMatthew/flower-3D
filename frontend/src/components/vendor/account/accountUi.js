// Shared bits for the vendor Subscription & account pages.
import api from "../../../plugins/axios";

export const PLAN_STATUS = {
  active: ["Active", "green"],
  trialing: ["Free trial", "violet"],
  pending: ["Waiting for payment", "amber"],
  past_due: ["Payment overdue", "red"],
  cancelled: ["Cancelled", "slate"],
  expired: ["Expired", "red"],
};

export const PAYMENT_STATUS = {
  paid: ["Paid", "green"],
  pending: ["Awaiting payment", "amber"],
  cancelled: ["Cancelled", "slate"],
  failed: ["Failed", "red"],
  expired: ["Not completed", "slate"],
  trial: ["Free trial", "violet"],
};

const pesoFmt = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", maximumFractionDigits: 2 });
export const peso = (v) => pesoFmt.format(Number(v || 0));
export const pesoWhole = (v) => pesoFmt.format(Math.round(Number(v || 0))).replace(/\.00$/, "");

export const longDate = (iso) =>
  iso ? new Date(iso).toLocaleDateString("en-PH", { month: "long", day: "numeric", year: "numeric" }) : "—";
export const shortDate = (iso) =>
  iso ? new Date(iso).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—";
export const dateTime = (iso) =>
  iso ? new Date(iso).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "—";
export const periodText = (p) => (p?.[0] ? `${shortDate(p[0])} – ${shortDate(p[1])}` : "—");

/** "50 products", "Unlimited" or "Not included" for a plan limit */
export function limitText(limit, unit) {
  if (limit === null || limit === undefined) return "Unlimited";
  if (limit === 0) return "Not included";
  return `${limit.toLocaleString()} ${unit}`;
}

export const LIMIT_ROWS = [
  ["products", "Products", "products"],
  ["staff_employees", "Employee accounts", "employees"],
  ["warehouses", "Warehouses", "warehouses"],
  ["branches", "Shop locations", "locations"],
];

/**
 * Starts a PayMongo checkout for a plan and leaves for it. The checkout ID is
 * remembered so the Subscription page can confirm the payment on return.
 */
export async function startPlanCheckout(planKey) {
  const { data } = await api.post("/vendor/subscription/checkout", { plan_key: planKey });
  if (!data?.data?.checkout_url) throw new Error(data?.message || "Couldn't start the payment.");
  sessionStorage.setItem("vendor_subscription_checkout", JSON.stringify({ checkoutId: data.data.checkout_id, planKey: data.data.plan_key }));
  window.location.assign(data.data.checkout_url);
}
