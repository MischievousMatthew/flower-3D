// Shared bits for the Staff & operations pages: how permissions are worded
// (like the admin console's Roles & permissions), times and people.
import { ERP_MODULES } from "../../../constants/erpModules";

const GROUP_ORDER = ["HR", "Finance", "Procurement", "Supply Chain", "CRM"];
const GROUP_LABEL = { HR: "People & HR", Finance: "Money", Procurement: "Stock & buying", "Supply Chain": "Suppliers & deliveries", CRM: "Customers" };

/** What each action means, per area. Anything not listed falls back to a generic sentence. */
const ACTION_TEXT = {
  hr_dashboard: { view: "See the HR overview", export: "Download HR reports" },
  employees: { view: "See employee profiles", create: "Add employee profiles", edit: "Edit employee profiles", delete: "Remove employee profiles", export: "Download employee QR codes", print: "Print employee details" },
  schedules: { view: "See the shift schedule", create: "Put people on shifts", edit: "Create shifts, swap and cover shifts", delete: "Remove shifts and people from shifts" },
  attendance: { view: "See attendance", create: "Time people in and out at a shop scanner", edit: "Ask to correct attendance", delete: "Delete attendance records", approve: "Approve flagged scans and corrections" },
  payroll: { view: "See payroll", create: "Create payroll", delete: "Delete payroll", approve: "Send payroll to finance" },
  leave_management: { view: "See leave and overtime requests", create: "File leave or overtime for someone", edit: "Set leave rules, adjust balances, cancel approved leave", approve: "Approve leave and overtime requests", reject: "Decline leave and overtime requests", delete: "Delete leave requests" },
  finance_dashboard: { view: "See the finance overview" },
  sales_revenue: { view: "See sales, discounts, refunds and orders" },
  payments: { view: "See payments, fees and reconciliation", edit: "Record cash-on-delivery collections and settlements" },
  expenses: { view: "See expenses and receipts", create: "Record expenses", edit: "Edit expenses and their files", delete: "Delete expenses" },
  profit_loss: { view: "See profit and loss" },
  refunds: { view: "See refund requests and reconciliation", edit: "Record refunds as sent" },
  financial_reports: { view: "See financial reports", export: "Download reports as CSV or PDF" },
  subscription_billing: { view: "See the shop plan, charges and invoices" },
  funding_requests: { view: "See funding requests", approve: "Approve funding", reject: "Decline funding" },
  payroll_requests: { view: "See payroll waiting for payment", edit: "Mark payroll as paid", approve: "Approve payroll for payment", reject: "Send payroll back" },
  crm_dashboard: { view: "See the customer overview" },
  crm_reviews: { view: "See reviews and feedback trends", create: "Reply to reviews" },
  crm_segments: { view: "See customer groups", create: "Send offers to a group" },
  crm_cases: { view: "See complaints and support cases", create: "Record cases and reply to customers", edit: "Change status and priority, assign, link orders and refunds" },
  crm_customers: { view: "See customer profiles and purchase history", create: "Add notes and special dates", edit: "Edit preferences and pin notes", delete: "Delete notes and dates" },
  crm: { view: "Read customer chats and questions", create: "Reply, answer questions and send order updates" },
  inventory_products: { view: "See products", create: "Add products", edit: "Edit products and stock", delete: "Delete products" },
  inventory_funding: { view: "See stock budgets", create: "Request stock budgets", edit: "Edit stock budgets", delete: "Delete stock budgets" },
  sc_dashboard: { view: "See the supply chain overview" },
  suppliers: { view: "See suppliers", create: "Add suppliers", edit: "Edit suppliers", delete: "Remove suppliers" },
  warehouse: { view: "See the warehouse", create: "Receive stock", edit: "Move and edit stock", delete: "Remove warehouse records" },
  sc_orders: { view: "See supplier orders", create: "Place supplier orders", edit: "Edit supplier orders", delete: "Cancel supplier orders" },
  deliveries: { view: "See deliveries and returns", edit: "Update delivery status", approve: "Accept returns and deliveries", reject: "Decline returns and deliveries", export: "Download delivery lists", print: "Print delivery slips" },
  order_scan: { view: "See the order scanner", edit: "Scan orders to move them along" },
};
/** One word per action, for short summaries ("See, add, edit"). */
export const ACTION_WORD = { view: "see", create: "add", edit: "edit", delete: "delete", approve: "approve", reject: "decline", export: "download", print: "print", manage: "manage" };
const GENERIC = { view: "See", create: "Add", edit: "Edit", delete: "Delete", approve: "Approve", reject: "Decline", export: "Download", print: "Print", manage: "Manage" };

/**
 * The permission catalog from the server ([{module, actions, available}]),
 * grouped and worded for people: [{ group, label, areas: [{ module, label, available, perms: [{key, label}] }] }]
 */
export function groupCatalog(catalog) {
  const byKey = Object.fromEntries(ERP_MODULES.map((m) => [m.key, m]));
  const groups = {};
  for (const item of catalog ?? []) {
    const meta = byKey[item.module];
    const group = meta?.group ?? "Other";
    (groups[group] ??= []).push({
      module: item.module,
      label: AREA_LABEL[item.module] ?? meta?.label ?? item.module,
      description: meta?.description ?? "",
      available: item.available,
      perms: item.actions.map((a) => ({
        key: `${item.module}.${a}`,
        action: a,
        label: ACTION_TEXT[item.module]?.[a] ?? `${GENERIC[a] ?? a} ${String(meta?.label ?? "").toLowerCase()}`,
      })),
    });
  }
  // Areas in the plan first; locked ones last so they don't crowd what you can use
  const rank = (g) => (groups[g].some((a) => a.available) ? 0 : 100) + ((GROUP_ORDER.indexOf(g) + 99) % 99);
  return Object.keys(groups)
    .sort((a, b) => rank(a) - rank(b))
    .map((g) => ({ group: g, label: GROUP_LABEL[g] ?? g, areas: [...groups[g]].sort((x, y) => Number(y.available) - Number(x.available)) }));
}

const AREA_LABEL = {
  hr_dashboard: "HR overview",
  leave_management: "Leave",
  finance_dashboard: "Finance overview",
  sales_revenue: "Sales & revenue",
  payments: "Payments",
  expenses: "Expenses",
  profit_loss: "Profit & loss",
  refunds: "Refunds",
  financial_reports: "Financial reports",
  subscription_billing: "Subscription & billing",
  payroll_requests: "Paying payroll",
  inventory_products: "Products",
  inventory_funding: "Stock budgets",
  sc_dashboard: "Supply chain overview",
  sc_orders: "Supplier orders",
  order_scan: "Order scanner",
  crm_dashboard: "Customer overview",
  crm_customers: "Customer profiles",
  crm_cases: "Support cases",
  crm_segments: "Segments & offers",
  crm_reviews: "Reviews & feedback",
  crm: "Customer chat",
};

/** Ready-made starting points when creating a role. */
export const ROLE_PRESETS = [
  { name: "Florist", color: "#48bb78", description: "Makes bouquets and keeps products and stock up to date.", permissions: ["inventory_products.view", "inventory_products.create", "inventory_products.edit", "order_scan.view", "order_scan.edit"] },
  { name: "Delivery rider", color: "#4299e1", description: "Picks up and delivers orders.", permissions: ["deliveries.view", "deliveries.edit", "deliveries.print", "order_scan.view", "order_scan.edit"] },
  { name: "Cashier", color: "#ed8936", description: "Talks to customers and handles returns.", permissions: ["crm.view", "crm.create", "deliveries.view", "deliveries.approve", "deliveries.reject"] },
  { name: "Store manager", color: "#9f7aea", description: "Runs the shop day to day.", permissions: ["inventory_products.view", "inventory_products.create", "inventory_products.edit", "inventory_products.delete", "deliveries.view", "deliveries.edit", "deliveries.approve", "deliveries.reject", "deliveries.export", "deliveries.print", "order_scan.view", "order_scan.edit", "suppliers.view", "suppliers.create", "suppliers.edit", "crm.view", "crm.create", "attendance.view", "attendance.edit", "leave_management.view", "leave_management.approve", "leave_management.reject"] },
];

export const ROLE_COLORS = ["#48bb78", "#4299e1", "#9f7aea", "#ed8936", "#ed64a6", "#38b2ac", "#e53e3e", "#718096"];

/** "08:30" → "8:30 AM" */
export function time12(hm) {
  if (!hm) return "";
  const [h, m] = hm.split(":").map(Number);
  return `${((h + 11) % 12) + 1}:${String(m).padStart(2, "0")} ${h < 12 ? "AM" : "PM"}`;
}
/** "08:00–16:00" → "8:00 AM – 4:00 PM" */
export function range12(text) {
  if (!text) return "";
  const [a, b] = text.split("–");
  return `${time12(a)} – ${time12(b)}`;
}

export function hoursText(h) {
  if (h === null || h === undefined) return "—";
  const total = Math.round(Number(h) * 60);
  const hh = Math.floor(total / 60);
  const mm = total % 60;
  return hh ? `${hh}h${mm ? ` ${mm}m` : ""}` : `${mm}m`;
}

export function lateText(mins) {
  if (!mins) return "";
  return mins >= 60 ? `${Math.floor(mins / 60)}h ${mins % 60 ? `${mins % 60}m ` : ""}late` : `${mins}m late`;
}

export function relativeTime(iso) {
  if (!iso) return "";
  const diff = (Date.now() - new Date(iso).getTime()) / 1000;
  if (diff < 60) return "just now";
  if (diff < 3600) return `${Math.floor(diff / 60)} min ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} h ago`;
  if (diff < 86400 * 7) {
    const d = Math.floor(diff / 86400);
    return d === 1 ? "yesterday" : `${d} days ago`;
  }
  return new Date(iso).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
}

export function clockTime(iso) {
  return new Date(iso).toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" });
}

/** A stable soft colour for someone without a role colour. */
export function personColor(name) {
  let n = 0;
  for (const ch of String(name ?? "")) n = (n * 31 + ch.charCodeAt(0)) >>> 0;
  return ROLE_COLORS[n % ROLE_COLORS.length];
}

export const STATUS_TONE = { Active: "green", "On Leave": "amber", Resign: "slate" };
export const STATUS_LABEL = { Active: "Active", "On Leave": "On leave", Resign: "Left the shop" };
