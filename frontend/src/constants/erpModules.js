// src/constants/erpModules.js
// Single source of truth for every ERP module exposed through the RBAC system.
// Used by: StaffList RBAC table, DynamicSidebar nav filtering, router guards.

export const ERP_MODULES = [
  {
    key: "hr_dashboard",
    label: "HR Dashboard",
    icon: "dashboard",
    group: "HR",
    path: "/erp/hr",
  },
  {
    key: "employees",
    label: "Employees",
    icon: "employees",
    group: "HR",
    path: "/erp/hr/employees",
    children: [
      { label: "Directory", path: "/erp/hr/employees/directory" },
      { label: "Profiles", path: "/erp/hr/employees/profiles" },
    ],
  },
  {
    key: "attendance",
    label: "Attendance",
    icon: "attendance",
    group: "HR",
    path: "/erp/hr/attendance",
    children: [
      { label: "Logs", path: "/erp/hr/attendance/logs" },
      { label: "QR Scanner", path: "/erp/hr/attendance/qrscanner" },
    ],
  },
  {
    key: "payroll",
    label: "Payroll",
    icon: "payroll",
    group: "HR",
    path: "/erp/hr/payroll",
    children: [
      { label: "Payroll list", path: "/erp/hr/payroll/list" },
      { label: "Create payroll", path: "/erp/hr/payroll/create" },
    ],
  },
  {
    key: "leave",
    label: "Leave Management",
    icon: "leave",
    group: "HR",
    path: "/erp/hr/leave",
    children: [
      { label: "Leave requests", path: "/erp/hr/leave/management-requests" },
      { label: "QR scanner", path: "/erp/hr/leave/qr-request" },
    ],
  },

  {
    key: "finance_dashboard",
    label: "Finance Dashboard",
    icon: "dashboard",
    group: "Finance",
    path: "/erp/finance/dashboard",
  },
  {
    key: "funding_requests",
    label: "Funding Requests",
    icon: "funding",
    group: "Finance",
    path: "/erp/finance/funding-requests",
  },
  {
    key: "payroll_requests",
    label: "Payroll Requests",
    icon: "payroll",
    group: "Finance",
    path: "/erp/finance/payroll-requests",
  },
  {
    key: "crm",
    label: "Chat",
    icon: "crm",
    group: "CRM",
    path: "/erp/crm/chat",
  },

  {
    key: "inventory_products",
    label: "Inventory Products",
    icon: "products",
    group: "Procurement",
    path: "/erp/procurement/inventory/products",
    children: [
      { label: "Products", path: "/erp/procurement/inventory/products" },
      { label: "Add Product", path: "/erp/procurement/inventory/add-product" },
    ],
  },
  {
    key: "inventory_funding",
    label: "Inventory Funding",
    icon: "funding",
    group: "Procurement",
    path: "/erp/procurement/inventory/funding-request",
  },

  {
    key: "sc_dashboard",
    label: "SC Dashboard",
    icon: "dashboard",
    group: "Supply Chain",
    path: "/erp/procurement/supply-chain/dashboard",
  },
  {
    key: "suppliers",
    label: "Suppliers",
    icon: "suppliers",
    group: "Supply Chain",
    path: "/erp/procurement/supply-chain/suppliers",
  },
  {
    key: "warehouse",
    label: "Warehouse",
    icon: "warehouse",
    group: "Supply Chain",
    path: "/erp/procurement/supply-chain/warehouse",
    children: [
      { label: "Overview", path: "/erp/procurement/supply-chain/warehouse" },
      {
        label: "Inventory",
        path: "/erp/procurement/supply-chain/warehouse/inventory",
      },
      {
        label: "Floor view",
        path: "/erp/procurement/supply-chain/warehouse/floor",
      },
      {
        label: "Receive batches",
        path: "/erp/procurement/supply-chain/warehouse/batches-receive",
      },
      {
        label: "Locations",
        path: "/erp/procurement/supply-chain/warehouse/locations",
      },
    ],
  },
  {
    key: "sc_orders",
    label: "SC Orders",
    icon: "orders",
    group: "Supply Chain",
    path: "/erp/procurement/supply-chain/orders",
  },
  {
    key: "deliveries",
    label: "Deliveries",
    icon: "deliveries",
    group: "Supply Chain",
    path: "/erp/procurement/supply-chain/deliveries",
  },
  {
    key: "order_scan",
    label: "Order Scan",
    icon: "scan",
    group: "Supply Chain",
    path: "/erp/procurement/supply-chain/scan",
    children: [
      { label: "To process", path: "/erp/procurement/supply-chain/scan/process" },
      { label: "To ship", path: "/erp/procurement/supply-chain/scan/ship" },
      { label: "To receive", path: "/erp/procurement/supply-chain/scan/receive" },
    ],
  },
];

export const MODULE_KEYS = ERP_MODULES.map((m) => m.key);

export function getModulesByGroup() {
  const groups = {};
  for (const mod of ERP_MODULES) {
    if (!groups[mod.group]) groups[mod.group] = [];
    groups[mod.group].push(mod);
  }
  return groups;
}

export function findModule(key) {
  return ERP_MODULES.find((m) => m.key === key) ?? null;
}
