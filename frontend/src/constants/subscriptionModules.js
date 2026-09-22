// Mirrors backend/app/Subscriptions/SubscriptionPlans.php module keys.
// Plan definitions remain server-side; this is only the route-key contract.
export const SUBSCRIPTION_MODULES = Object.freeze({
  PRODUCTS: "products",
  RESERVATIONS: "reservations",
  CALENDAR: "calendar",
  FINANCE: "finance",
  STAFF: "staff",
  PROCUREMENT: "procurement",
  SUPPLIERS: "suppliers",
  WAREHOUSE: "warehouse",
  SUPPLY_CHAIN: "supply_chain",
  ORDERS: "orders",
  LOGISTICS: "logistics",
  DELIVERIES: "deliveries",
  SCANNING: "scanning",
  CRM: "crm",
  HR: "hr",
  EMPLOYEES: "employees",
  ATTENDANCE: "attendance",
  PAYROLL: "payroll",
  LEAVE: "leave",
});

// Existing employee-RBAC keys resolve to the Stage 2 subscription keys.
export const subscriptionModuleFor = (module) =>
  ({
    hr_dashboard: SUBSCRIPTION_MODULES.HR,
    leave_management: SUBSCRIPTION_MODULES.LEAVE,
    finance_dashboard: SUBSCRIPTION_MODULES.FINANCE,
    funding_requests: SUBSCRIPTION_MODULES.FINANCE,
    payroll_requests: SUBSCRIPTION_MODULES.FINANCE,
    inventory_products: SUBSCRIPTION_MODULES.PRODUCTS,
    inventory_funding: SUBSCRIPTION_MODULES.FINANCE,
    sc_dashboard: SUBSCRIPTION_MODULES.SUPPLY_CHAIN,
    sc_orders: SUBSCRIPTION_MODULES.ORDERS,
    order_scan: SUBSCRIPTION_MODULES.SCANNING,
  })[module] ?? module;
