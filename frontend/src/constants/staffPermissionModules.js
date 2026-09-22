import { SUBSCRIPTION_MODULES as SUB } from "./subscriptionModules";

// Presentation catalog for the existing EmployeeModulePermission records.
// `permissionModule` is the existing backend RBAC key; this never creates a
// second employee permission system.
export const STAFF_PERMISSION_MODULES = [
  { key: "products", label: "Products", group: "Vendor", permissionModule: "inventory_products", subscriptionModule: SUB.PRODUCTS },
  { key: "reservations_orders", label: "Reservations / Orders", group: "Vendor", permissionModule: "sc_orders", subscriptionModule: SUB.ORDERS },
  { key: "calendar", label: "Calendar", group: "Vendor", permissionModule: "sc_orders", subscriptionModule: SUB.CALENDAR },
  { key: "chat", label: "Chat", group: "Vendor", permissionModule: "crm", subscriptionModule: SUB.CRM },
  { key: "finance", label: "Finance", group: "Vendor", permissionModule: "finance_dashboard", subscriptionModule: SUB.FINANCE },
  { key: "procurement", label: "Procurement", group: "ERP", permissionModule: "inventory_products", subscriptionModule: SUB.PROCUREMENT },
  { key: "suppliers", label: "Suppliers", group: "ERP", permissionModule: "suppliers", subscriptionModule: SUB.SUPPLIERS },
  { key: "warehouse", label: "Warehouse", group: "ERP", permissionModule: "warehouse", subscriptionModule: SUB.WAREHOUSE },
  { key: "supply_chain", label: "Supply Chain", group: "ERP", permissionModule: "sc_dashboard", subscriptionModule: SUB.SUPPLY_CHAIN },
  { key: "orders", label: "Orders", group: "ERP", permissionModule: "sc_orders", subscriptionModule: SUB.ORDERS },
  { key: "logistics", label: "Logistics", group: "ERP", permissionModule: "deliveries", subscriptionModule: SUB.LOGISTICS },
  { key: "deliveries", label: "Deliveries", group: "ERP", permissionModule: "deliveries", subscriptionModule: SUB.DELIVERIES },
  { key: "scanning", label: "Scanning", group: "ERP", permissionModule: "order_scan", subscriptionModule: SUB.SCANNING },
  { key: "crm", label: "CRM", group: "ERP", permissionModule: "crm", subscriptionModule: SUB.CRM },
  { key: "hr", label: "HR", group: "ERP", permissionModule: "hr_dashboard", subscriptionModule: SUB.HR },
  { key: "employees", label: "Employees", group: "ERP", permissionModule: "employees", subscriptionModule: SUB.EMPLOYEES },
  { key: "attendance", label: "Attendance", group: "ERP", permissionModule: "attendance", subscriptionModule: SUB.ATTENDANCE },
  { key: "payroll", label: "Payroll", group: "ERP", permissionModule: "payroll", subscriptionModule: SUB.PAYROLL },
  { key: "leave", label: "Leave", group: "ERP", permissionModule: "leave_management", subscriptionModule: SUB.LEAVE },
];

export const staffPermissionGroups = () => STAFF_PERMISSION_MODULES.reduce((groups, module) => {
  (groups[module.group] ??= []).push(module);
  return groups;
}, {});
