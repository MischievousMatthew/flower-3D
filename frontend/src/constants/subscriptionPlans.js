// Vendor subscription plans shown on the pricing page and the landing page.
// Checkout itself stays in PricingPage.selectPlan(); this module only holds
// the plan data plus the "pending plan" a visitor picked before logging in.

export const SUBSCRIPTION_PLANS = [
  { key: "starter", name: "STARTER", price: "₱999", priceSuffix: "/month", description: "Essential ecommerce and day-to-day business tools for getting started.", highlights: ["Ecommerce functionality", "Products, reservations, calendar, and chat", "Basic business management"], action: "Get started" },
  { key: "business", name: "BUSINESS", price: "₱2,999", priceSuffix: "/month", description: "Everything in Starter with the foundational ERP tools for a connected business.", highlights: ["Everything in Starter", "Basic ERP functionality", "Finance, procurement, and supplier workflows"], action: "Choose Business" },
  { key: "professional", name: "PROFESSIONAL", price: "₱6,999", priceSuffix: "/month", description: "The complete normal BloomCraft ERP for teams running end-to-end operations.", highlights: ["Everything in Business", "Full ERP functionality", "Warehouse, supply chain, CRM, and HR"], action: "Choose Professional", featured: true },
  { key: "enterprise", name: "ENTERPRISE", price: "₱15,000+", priceSuffix: "/month", description: "Professional ERP capabilities with enterprise-scale flexibility and support.", highlights: ["Everything in Professional", "Multi-branch and multi-warehouse", "Custom integrations and dedicated support"], action: "Talk to us" },
];

export const findPlan = (key) => SUBSCRIPTION_PLANS.find((plan) => plan.key === key) || null;

// ---- Pending plan ----------------------------------------------------------
// A visitor without a session picks a plan on the landing page, then logs in
// (or registers as a vendor and waits for approval). The first vendor login
// afterwards sends them to /pricing?plan=<key>, which starts that checkout.
const PENDING_PLAN_KEY = "bloomcraft_pending_plan";
// Vendor applications need admin approval, which can take days.
const PENDING_PLAN_TTL_MS = 30 * 24 * 60 * 60 * 1000;

export function savePendingPlan(key) {
  if (!findPlan(key)) return;
  try {
    localStorage.setItem(PENDING_PLAN_KEY, JSON.stringify({ key, savedAt: Date.now() }));
  } catch (_) {
    // Storage unavailable: the visitor can still pick the plan on /pricing.
  }
}

export function readPendingPlan() {
  try {
    const raw = localStorage.getItem(PENDING_PLAN_KEY);
    if (!raw) return null;
    const { key, savedAt } = JSON.parse(raw);
    if (!findPlan(key) || Date.now() - Number(savedAt) > PENDING_PLAN_TTL_MS) {
      clearPendingPlan();
      return null;
    }
    return key;
  } catch (_) {
    clearPendingPlan();
    return null;
  }
}

export function clearPendingPlan() {
  try {
    localStorage.removeItem(PENDING_PLAN_KEY);
  } catch (_) {
    // Ignore storage failures.
  }
}
