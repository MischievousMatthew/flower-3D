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
  PHOTO_TO_3D: "photo_to_3d",
});

// Existing employee-RBAC keys resolve to the Stage 2 subscription keys.
export const subscriptionModuleFor = (module) =>
  ({
    hr_dashboard: SUBSCRIPTION_MODULES.HR,
    leave_management: SUBSCRIPTION_MODULES.LEAVE,
    finance_dashboard: SUBSCRIPTION_MODULES.FINANCE,
    sales_revenue: SUBSCRIPTION_MODULES.FINANCE,
    payments: SUBSCRIPTION_MODULES.FINANCE,
    expenses: SUBSCRIPTION_MODULES.FINANCE,
    profit_loss: SUBSCRIPTION_MODULES.FINANCE,
    refunds: SUBSCRIPTION_MODULES.FINANCE,
    financial_reports: SUBSCRIPTION_MODULES.FINANCE,
    subscription_billing: SUBSCRIPTION_MODULES.FINANCE,
    funding_requests: SUBSCRIPTION_MODULES.FINANCE,
    payroll_requests: SUBSCRIPTION_MODULES.FINANCE,
    crm_dashboard: SUBSCRIPTION_MODULES.CRM,
    crm_customers: SUBSCRIPTION_MODULES.CRM,
    crm_cases: SUBSCRIPTION_MODULES.CRM,
    crm_segments: SUBSCRIPTION_MODULES.CRM,
    crm_reviews: SUBSCRIPTION_MODULES.CRM,
    inventory_products: SUBSCRIPTION_MODULES.PRODUCTS,
    inventory_funding: SUBSCRIPTION_MODULES.FINANCE,
    sc_dashboard: SUBSCRIPTION_MODULES.SUPPLY_CHAIN,
    sc_orders: SUBSCRIPTION_MODULES.ORDERS,
    order_scan: SUBSCRIPTION_MODULES.SCANNING,
    // The ERP shift planner is the vendor's Staff → Schedules feature
    schedules: SUBSCRIPTION_MODULES.STAFF,
  })[module] ?? module;
