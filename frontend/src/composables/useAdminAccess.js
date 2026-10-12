import { reactive } from "vue";
import api from "../plugins/axios";

/**
 * The signed-in admin's role and permissions (Security → Roles).
 * The backend enforces every permission; this only decides what to show.
 */
const access = reactive({ loaded: false, id: null, role: null, isSuperAdmin: false, permissions: [], name: null });
let request = null;

/**
 * Admin pages in sidebar order, with the permission each one needs.
 * An array means any one of them is enough.
 */
export const ADMIN_PAGES = [
  { path: "/admin/dashboard", permission: "dashboard.view" },
  { path: "/admin/orders", permission: "orders.view" },
  { path: "/admin/product-approval", permission: "products.view" },
  { path: "/admin/categories", permission: "products.taxonomy" },
  { path: "/admin/website", permission: "content.manage" },
  { path: "/admin/reviews", permission: "products.view" },
  { path: "/admin/finance", permission: "finance.view" },
  { path: "/admin/refunds", permission: "finance.view" },
  { path: "/admin/subscription-revenue", permission: "revenue.view" },
  { path: "/admin/analytics", permission: "reports.view" },
  { path: "/admin/vendor-requests", permission: "vendors.view" },
  { path: "/admin/customers", permission: "customers.view" },
  { path: "/admin/support", permission: "support.view" },
  { path: "/admin/audit-logs", permission: "security.view" },
  { path: "/admin/security", permission: "security.view" },
  { path: "/admin/settings", permission: ["security.view", "content.manage"] },
  { path: "/admin/health", permission: "security.view" },
];

const load = (force = false) => {
  if (force) request = null;
  request ??= api
    .get("/admin/security/me")
    .then(({ data }) => {
      Object.assign(access, {
        loaded: true,
        id: data.data.id,
        role: data.data.role,
        isSuperAdmin: data.data.is_super_admin,
        permissions: data.data.permissions ?? [],
        name: data.data.name,
      });
      return access;
    })
    .catch((error) => {
      request = null;
      throw error;
    });
  return request;
};

const can = (permission) =>
  Array.isArray(permission) ? permission.some((p) => can(p)) : access.isSuperAdmin || access.permissions.includes(permission);

const permissionForPath = (path) => ADMIN_PAGES.find((p) => path === p.path || path.startsWith(`${p.path}/`))?.permission ?? null;

const firstAllowedPath = () => ADMIN_PAGES.find((p) => can(p.permission))?.path ?? null;

const reset = () => {
  request = null;
  Object.assign(access, { loaded: false, id: null, role: null, isSuperAdmin: false, permissions: [], name: null });
};

export function useAdminAccess() {
  return { access, load, can, permissionForPath, firstAllowedPath, reset };
}
