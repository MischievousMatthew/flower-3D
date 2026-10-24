// src/constants/erpModules.js
// Single source of truth for every ERP module exposed through the RBAC system.
// Used by: vendor Roles & permissions (Staff & operations), DynamicSidebar nav filtering, router guards.

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
      { label: "Daily log", path: "/erp/hr/attendance/logs" },
      { label: "Review", path: "/erp/hr/attendance/review" },
      { label: "Reports", path: "/erp/hr/attendance/reports" },
      { label: "Scanner", path: "/erp/hr/attendance/qrscanner" },
    ],
    description:
      "Allows you to view and manage employee attendance, working hours, and attendance records.",
  },
  {
    key: "schedules",
    label: "Schedules",
    icon: "schedules",
    group: "HR",
    path: "/erp/hr/schedules",
    description:
      "Create shifts, put people on them by drag and drop, cover no-shows, and see who's free.",
  },
  {
    key: "payroll",
    label: "Payroll",
    icon: "payroll",
    group: "HR",
    path: "/erp/hr/payroll",
    children: [
      { label: "Payrolls", path: "/erp/hr/payroll/list" },
      { label: "New payroll", path: "/erp/hr/payroll/create" },
    ],
    description:
      "Allows you to manage employee salaries, deductions, and payroll-related records.",
  },
  {
    key: "leave_management",
    label: "Leave & Overtime",
    icon: "leave",
    group: "HR",
    path: "/erp/hr/leave/management-requests",
    description:
      "Approve leave and overtime requests, track leave balances and rules, and print the leave QR poster.",
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
    key: "sales_revenue",
    label: "Sales & Revenue",
    icon: "sales",
    group: "Finance",
    path: "/erp/finance/sales",
    description:
      "Shows completed and cancelled orders, sales by date, product and order, gross sales, discounts and refunds, and cash-on-delivery against e-wallet sales.",
  },
  {
    key: "payments",
    label: "Payments",
    icon: "payments",
    group: "Finance",
    path: "/erp/finance/payments",
    description:
      "Shows PayMongo and cash-on-delivery payments with their status and fees, matches them against orders and the books, and records cash-on-delivery collections and settlements.",
  },
  {
    key: "expenses",
    label: "Expenses",
    icon: "expenses",
    group: "Finance",
    path: "/erp/finance/expenses",
    description:
      "Records flowers, supplies, packaging, delivery, utilities, rent and other costs by category, with receipts and documents attached.",
  },
  {
    key: "profit_loss",
    label: "Profit & Loss",
    icon: "profit",
    group: "Finance",
    path: "/erp/finance/profit-loss",
    description:
      "Compares revenue against costs with gross and operating profit, month by month and year by year, counting flower costs, packaging, payment fees and recorded expenses.",
  },
  {
    key: "refunds",
    label: "Refunds",
    icon: "refunds",
    group: "Finance",
    path: "/erp/finance/refunds",
    description:
      "Tracks refund requests from request to completion with their orders and payments, records refunds as sent, and reconciles them with the books.",
  },
  {
    key: "financial_reports",
    label: "Reports",
    icon: "reports",
    group: "Finance",
    path: "/erp/finance/reports",
    description:
      "Daily to yearly sales, expense reports, profit and loss statements, payment and refund summaries, and tax summaries, downloadable as CSV or PDF.",
  },
  {
    key: "subscription_billing",
    label: "Subscription & Billing",
    icon: "billing",
    group: "Finance",
    path: "/erp/finance/subscription",
    description:
      "Shows the BloomCraft plan of the shop, renewal and trial dates, subscription charges, failed payments and invoices. Read-only.",
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
    key: "crm_dashboard",
    label: "CRM Dashboard",
    icon: "dashboard",
    group: "CRM",
    path: "/erp/crm/dashboard",
    description:
      "Shows customers, repeat buyers, inquiries, open issues, top customers and satisfaction.",
  },
  {
    key: "crm_customers",
    label: "Customers",
    icon: "employees",
    group: "CRM",
    path: "/erp/crm/customers",
    description:
      "Customer profiles, contact details, purchase history, preferences, special dates and internal notes.",
  },
  {
    key: "crm_reviews",
    label: "Reviews & Feedback",
    icon: "reviews",
    group: "CRM",
    path: "/erp/crm/reviews",
    description: "Product reviews and replies, satisfaction over time, and complaints that keep coming up.",
  },
  {
    key: "crm_segments",
    label: "Segments & Offers",
    icon: "segments",
    group: "CRM",
    path: "/erp/crm/segments",
    description: "Groups customers by what they buy (new, repeat, frequent, events, inactive) and sends offers to those who said yes.",
  },
  {
    key: "crm_cases",
    label: "Support Cases",
    icon: "cases",
    group: "CRM",
    path: "/erp/crm/cases",
    description: "Complaints and service issues: status, priority, who's on it, and the linked order or refund.",
  },
  {
    // Uses the Chat permission (crm); there is no separate one for questions
    key: "crm_inquiries",
    access: "crm",
    label: "Questions",
    icon: "inquiries",
    group: "CRM",
    path: "/erp/crm/inquiries",
    description: "Answer customers' questions about products and orders.",
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
  // Keep in sync with backend app/Constants/ErpModule.php MODULE_PERMISSIONS
  attendance: ["view", "create", "edit", "delete", "approve"],
  schedules: ["view", "create", "edit", "delete"],
  payroll: ["view", "create", "delete", "approve"],
  leave_management: ["view", "create", "edit", "approve", "reject", "delete"],
  finance_dashboard: ["view"],
  sales_revenue: ["view"],
  payments: ["view", "edit"],
  expenses: ["view", "create", "edit", "delete"],
  profit_loss: ["view"],
  refunds: ["view", "edit"],
  financial_reports: ["view", "export"],
  subscription_billing: ["view"],
  funding_requests: ["view", "approve", "reject"],
  payroll_requests: ["view", "edit", "approve", "reject"],
  crm_dashboard: ["view"],
  crm_customers: ["view", "create", "edit", "delete"],
  crm_cases: ["view", "create", "edit"],
  crm_segments: ["view", "create"],
  crm_reviews: ["view", "create"],
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
