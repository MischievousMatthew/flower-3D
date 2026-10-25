// The ERP workspace sidebar, built from ERP_MODULES so HR, Finance and the
// rest share one menu. The header reads it too, for its "Section / Page" title.
import { ERP_MODULES } from "../../constants/erpModules";

// Sidebar headings for each module group
const SECTION_LABELS = {
  HR: "People",
  Finance: "Money",
  CRM: "Customers",
  Procurement: "Procurement",
  "Supply Chain": "Supply chain",
};

// ERP icon names → vendor portal icons (shopIcons.js)
const ICON_FOR = {
  dashboard: "home",
  employees: "users",
  attendance: "clock",
  schedules: "grid",
  payroll: "wallet",
  leave: "calendar",
  sales: "chart",
  payments: "card",
  expenses: "receipt",
  profit: "percent",
  refunds: "restore",
  reports: "note",
  billing: "sparkle",
  funding: "send",
  crm: "chat",
  inquiries: "help",
  cases: "alert",
  segments: "grid",
  reviews: "star",
  products: "flower",
  suppliers: "store",
  warehouse: "box",
  orders: "list",
  deliveries: "truck",
  scan: "eye",
};

// Pages outside a module's own paths that still belong to it
const EXTRA_PATHS = {
  funding_requests: ["/erp/finance/funding-request/"],
};

export const ERP_NAV = (() => {
  const sections = new Map();
  for (const mod of ERP_MODULES) {
    const group = mod.navGroup ?? mod.group;
    const label = SECTION_LABELS[group] ?? group;
    if (!sections.has(label)) sections.set(label, { label, group, items: [] });
    sections.get(label).items.push({
      key: mod.key,
      // The permission that opens it (usually the module itself)
      access: mod.access ?? mod.key,
      label: mod.label,
      icon: ICON_FOR[mod.icon] ?? "grid",
      // A module with sub-pages has no page of its own, so open its first one
      path: mod.children?.[0]?.path ?? mod.path,
      paths: [mod.path, ...(mod.children ?? []).map((c) => c.path), ...(EXTRA_PATHS[mod.key] ?? [])],
    });
  }
  return [...sections.values()];
})();

const covers = (base, path) => path === base || path.startsWith(base.endsWith("/") ? base : `${base}/`);

/** The section and item a path belongs to: the item with the longest matching path wins */
export function erpPlaceFor(path) {
  let best = null;
  let bestLength = -1;
  for (const section of ERP_NAV) {
    for (const item of section.items) {
      for (const base of item.paths) {
        if (covers(base, path) && base.length > bestLength) {
          best = { section, item };
          bestLength = base.length;
        }
      }
    }
  }
  return best;
}
