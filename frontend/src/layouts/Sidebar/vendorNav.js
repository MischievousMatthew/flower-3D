// The vendor sidebar, in one place. The navbar reads it too, for its
// "Section / Page" breadcrumb.
//
// name:   route the item opens
// match:  other routes that keep the item highlighted (its sub-pages)
// module: subscription module it needs; locked items show a 🔒 instead
// badge:  key into the sidebar's live counts

export const VENDOR_NAV = [
  {
    label: "Overview",
    items: [{ name: "VendorFinanceDashboard", label: "Dashboard", icon: "home" }],
  },
  {
    label: "My Shop",
    items: [
      { name: "VendorShop", label: "Shop Profile", icon: "store" },
      { name: "VendorProducts", label: "Products", icon: "flower", module: "products", match: ["VendorAddProduct", "VendorCategories"] },
      { name: "VendorInventory", label: "Inventory", icon: "box", module: "products" },
      { name: "VendorCustomization", label: "3D Bouquet Management", icon: "cube", module: "products" },
      { name: "VendorPromotions", label: "Promotions", icon: "percent", module: "products" },
    ],
  },
  {
    label: "Sales & Orders",
    items: [
      { name: "VendorReservation", label: "Orders", icon: "receipt", module: "orders", match: ["VendorOrderDetails", "VendorReturns"], badge: "orders" },
      { name: "VendorCalendar", label: "Reservations", icon: "calendar", module: "calendar" },
      { name: "VendorDeliverySchedule", label: "Delivery Management", icon: "truck", module: "orders" },
      { name: "VendorBooksSales", label: "Finance & Transactions", icon: "wallet", module: "finance", match: ["VendorBooksExpenses", "VendorBooksProfit", "VendorBooksRefunds"] },
      { name: "VendorBooksReports", label: "Reports", icon: "chart", module: "finance" },
    ],
  },
  {
    label: "Business Operations",
    items: [
      { name: "VendorStaffList", label: "Employees & Permissions", icon: "users", module: "staff", match: ["VendorStaffRoles", "VendorStaffPayroll", "VendorStaffActivity"] },
      { name: "VendorStaffAttendance", label: "Attendance & Schedules", icon: "clock", module: "attendance", match: ["VendorStaffSchedules"] },
      { name: "VendorChat", label: "Customer Chat", icon: "chat", module: "crm", match: ["VendorInquiries"] },
      { name: "VendorReviews", label: "Reviews", icon: "star", module: "products" },
      { name: "VendorNotifications", label: "Notifications", icon: "bell" },
    ],
  },
  {
    label: "Account",
    items: [
      { name: "VendorSubscription", label: "Subscription & Billing", icon: "sparkle", match: ["VendorBilling", "VendorUsage"], badge: "plan" },
      { name: "VendorShopSettings", label: "Settings", icon: "gear", match: ["VendorSecurity", "VendorProfile", "VendorSupport"] },
    ],
  },
];

/** The section and item a route belongs to, for highlighting and the breadcrumb */
export function navPlaceFor(routeName) {
  for (const section of VENDOR_NAV) {
    for (const item of section.items) {
      if (item.name === routeName || item.match?.includes(routeName)) return { section, item };
    }
  }
  return null;
}
