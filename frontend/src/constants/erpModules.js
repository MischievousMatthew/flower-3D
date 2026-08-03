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
    description:
      "Gives an overview of employee activities, attendance, leave, and other important HR information.",
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
    description:
      "Allows you to add, update, and manage employee information, roles, and employment status.",
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
    description:
      "Allows you to view and manage employee attendance, working hours, and attendance records.",
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
    description:
      "Allows you to manage employee salaries, deductions, and payroll-related records.",
  },
  {
    key: "leave_management",
    label: "Leave Management",
    icon: "leave",
    group: "HR",
    path: "/erp/hr/leave/management-requests",
    description:
      "Allows you to review employee leave requests and manage approved or pending leaves.",
  },

  {
    key: "finance_dashboard",
    label: "Finance Dashboard",
    icon: "dashboard",
    group: "Finance",
    path: "/erp/finance/dashboard",
    description:
      "Provides an overview of the company's financial activities, transactions, and important financial information.",
  },
  {
    key: "funding_requests",
    label: "Funding Requests",
    icon: "funding",
    group: "Finance",
    path: "/erp/finance/funding-requests",
    description:
      "Allows you to manage bills, supplier payments, funding requests, and other amounts the company needs to pay.",
  },
  {
    key: "payroll_requests",
    label: "Payroll Requests",
    icon: "payroll",
    group: "Finance",
    path: "/erp/finance/payroll-requests",
    description:
      "Allows you to review, approve, and manage payroll funding and salary disbursement requests.",
  },
  {
    key: "crm",
    label: "Chat",
    icon: "crm",
    group: "CRM",
    path: "/erp/crm/chat",
    description:
      "Allows you to manage customer interaction, support messaging, and communication.",
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
    description:
      "Allows you to manage product quantities, stock levels, product listings, and inventory records.",
  },
  {
    key: "inventory_funding",
    label: "Inventory Funding",
    icon: "funding",
    group: "Procurement",
    path: "/erp/procurement/inventory/funding-request",
    description:
      "Allows employees to create and manage requests for products, materials, or services needed by the company.",
  },

  {
    key: "sc_dashboard",
    label: "SC Dashboard",
    icon: "dashboard",
    group: "Supply Chain",
    path: "/erp/procurement/supply-chain/dashboard",
    description:
      "Provides an overview of inventory, warehouse activities, deliveries, and other supply chain operations.",
  },
  {
    key: "suppliers",
    label: "Suppliers",
    icon: "suppliers",
    group: "Supply Chain",
    path: "/erp/procurement/supply-chain/suppliers",
    description:
      "Allows you to manage supplier information, contacts, and supplier records.",
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
    description:
      "Allows you to manage storage locations, warehouse items, and product movement.",
  },
  {
    key: "sc_orders",
    label: "SC Orders",
    icon: "orders",
    group: "Supply Chain",
    path: "/erp/procurement/supply-chain/orders",
    description:
      "Allows you to view and manage customer orders throughout the fulfillment process.",
  },
  {
    key: "deliveries",
    label: "Deliveries",
    icon: "deliveries",
    group: "Supply Chain",
    path: "/erp/procurement/supply-chain/deliveries",
    description:
      "Allows you to manage deliveries and track orders as they move toward customers.",
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
    description:
      "Allows you to scan order items, monitor available stock, and process shipments.",
  },
];

export const MODULE_KEYS = ERP_MODULES.map((m) => m.key);

// Permissions are intentionally limited to actions implemented by each module.
export const MODULE_PERMISSIONS = {
  hr_dashboard: ["view", "export"],
  employees: ["view", "create", "edit", "delete", "export", "print"],
  attendance: ["view", "create", "edit", "delete"],
  payroll: ["view", "create", "delete", "approve"],
  leave_management: ["view", "approve", "reject", "delete"],
  finance_dashboard: ["view"],
  funding_requests: ["view", "approve", "reject"],
  payroll_requests: ["view", "edit", "approve", "reject"],
  crm: ["view", "create"],
  inventory_products: ["view", "create", "edit", "delete"],
  inventory_funding: ["view", "create", "edit", "delete"],
  sc_dashboard: ["view"],
  suppliers: ["view", "create", "edit", "delete"],
  warehouse: ["view", "create", "edit", "delete"],
  sc_orders: ["view", "create", "edit", "delete"],
  deliveries: ["view", "edit", "approve", "reject", "export", "print"],
  order_scan: ["view", "edit"],
};

export const PERMISSION_DETAILS = {
  view: {
    label: "View",
    description: "Allows the employee to view this information.",
  },
  create: {
    label: "Create",
    description: "Allows the employee to create new records.",
  },
  edit: {
    label: "Edit",
    description: "Allows the employee to modify existing records.",
  },
  delete: {
    label: "Delete",
    description: "Allows the employee to delete records.",
  },
  approve: {
    label: "Approve",
    description: "Allows the employee to approve requests or transactions.",
  },
  reject: {
    label: "Reject",
    description: "Allows the employee to reject requests or transactions.",
  },
  export: {
    label: "Export",
    description: "Allows the employee to export data.",
  },
  print: {
    label: "Print",
    description: "Allows the employee to print records or reports.",
  },
  manage: {
    label: "Manage",
    description: "Allows the employee to manage this module and its settings.",
  },
};

export function getModulePermissions(moduleKey) {
  return MODULE_PERMISSIONS[moduleKey] ?? [];
}

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
