import { createRouter, createWebHistory } from "vue-router";
import { useAuth } from "../composables/useAuth";
import { useAssignment } from "../composables/useAssignment";
import {
  clearStoredAuth,
  getPreferredAuthToken,
  getPreferredUserType,
} from "../utils/authSession";
import { opaquePathFor, opaqueRouteTokens } from "./opaqueRoutes";
import { useAdminAccess } from "../composables/useAdminAccess";
import { HIDDEN_ID_KEYS, hideIdsInLocation } from "../utils/hiddenIds";

const adminAccess = useAdminAccess();
import { ACCESS_REUSE_MS, loadSubscriptionAccess, subscriptionAllowsModule } from "../composables/useSubscriptionAccess";
import { SUBSCRIPTION_MODULES as MODULE } from "../constants/subscriptionModules";

const routes = [
  // ===== PUBLIC =====
  {
    path: "/",
    meta: { public: true },
    children: [
      {
        path: "",
        name: "Home",
        component: () => import("../views/guest/LandingPage.vue"),
      },
      {
        path: "pricing",
        component: () => import("../views/guest/PricingPage.vue"),
        // Pricing is a public landing-page URL, so it remains readable and
        // does not need an opaque token registry entry.
        meta: { public: true, preservePublicPath: true },
      },
      {
        // The Journal keeps readable URLs so articles can be shared
        path: "journal",
        component: () => import("../views/guest/Journal.vue"),
        meta: { public: true, preservePublicPath: true },
      },
      {
        path: "journal/:slug",
        component: () => import("../views/guest/JournalArticle.vue"),
        meta: { public: true, preservePublicPath: true },
      },
      {
        path: "shop",
        name: "Shop",
        component: () => import("../views/authenticated/Shop.vue"),
        meta: { public: true, onlyCustomerOrGuest: true },
      },
      {
        path: "product/:id",
        name: "ProductDetails",
        component: () => import("../views/authenticated/ProductDetails.vue"),
        meta: { public: true, onlyCustomerOrGuest: true },
      },
      {
        path: "customize/flower",
        name: "FlowerCustomizerStore",
        component: () => import("../views/authenticated/FlowerCustomizer.vue"),
        meta: { public: true, onlyCustomerOrGuest: true },
      },
      {
        path: "customize/:id?",
        name: "FlowerCustomizer",
        component: () => import("../views/authenticated/FlowerCustomizer.vue"),
        meta: { public: true, onlyCustomerOrGuest: true },
      },
      {
        path: "store",
        name: "VendorStorefront",
        component: () => import("../views/authenticated/VendorStoreFront.vue"),
        meta: { public: true },
      },
      {
        path: "guest/login",
        name: "Login",
        component: () => import("../views/guest/Login.vue"),
      },
      {
        path: "guest/register",
        name: "Register",
        component: () => import("../views/guest/Register.vue"),
      },
      {
        path: "guest/forgot-password",
        name: "ForgotPassword",
        component: () => import("../views/guest/ForgotPassword.vue"),
      },
      {
        path: "guest/vendor_register",
        name: "Vendor_Register",
        component: () => import("../views/guest/VendorRegistration.vue"),
      },
      {
        path: "vendor/resubmission/:token",
        name: "VendorResubmission",
        component: () => import("../views/guest/VendorResubmission.vue"),
        meta: { public: true },
      },
      {
        path: "leaves",
        name: "PublicLeaveQRRequest",
        component: () =>
          import("../views/ERP/HR/Public/LeaveRequestScanner.vue"),
        meta: { public: true, title: "Leave Request" },
      },
    ],
  },

  // ===== CUSTOMER =====
  {
    path: "/customer",
    meta: { requiresAuth: true },
    children: [
      {
        path: "profile",
        name: "Profile",
        component: () => import("../views/authenticated/Profile.vue"),
      },
      {
        path: "cart",
        name: "Cart",
        component: () => import("../views/authenticated/Cart.vue"),
      },
      {
        path: "checkout",
        name: "Checkout",
        component: () => import("../views/authenticated/Checkout.vue"),
      },
      {
        path: "chat",
        name: "Chat",
        component: () => import("../views/authenticated/CustomerChat.vue"),
      },
      {
        path: "orders",
        name: "OrderTracking",
        component: () => import("../views/authenticated/OrderTracking.vue"),
      },
      {
        path: "support",
        name: "CustomerSupport",
        component: () => import("../views/authenticated/CustomerSupport.vue"),
      },
    ],
  },

  // ===== VENDOR =====
  {
    path: "/vendor",
    meta: { requiresAuth: true, requiresVendor: true },
    redirect: "/vendor/products",
    children: [
      {
        path: "products",
        name: "VendorProducts",
        meta: { subscriptionModule: MODULE.PRODUCTS },
        component: () => import("../views/vendor/shop/Products.vue"),
      },
      {
        path: "shop",
        name: "VendorShop",
        component: () => import("../views/vendor/shop/MyShop.vue"),
      },
      {
        path: "inventory",
        name: "VendorInventory",
        meta: { subscriptionModule: MODULE.PRODUCTS },
        component: () => import("../views/vendor/shop/Inventory.vue"),
      },
      {
        path: "categories",
        name: "VendorCategories",
        meta: { subscriptionModule: MODULE.PRODUCTS },
        component: () => import("../views/vendor/shop/Categories.vue"),
      },
      {
        // Turns a flower or bouquet photo into a 3D model (free AI in the browser)
        path: "photo-to-3d",
        name: "VendorPhotoTo3D",
        // Business plan and up (the guard sends other plans to their profile)
        meta: { subscriptionModule: MODULE.PHOTO_TO_3D },
        component: () => import("../views/vendor/shop/PhotoTo3D.vue"),
      },
      {
        path: "promotions",
        name: "VendorPromotions",
        meta: { subscriptionModule: MODULE.PRODUCTS },
        component: () => import("../views/vendor/shop/Promotions.vue"),
      },
      {
        path: "reservation",
        name: "VendorReservation",
        meta: { subscriptionModule: MODULE.ORDERS },
        component: () => import("../views/vendor/orders/Orders.vue"),
      },
      {
        // The order to show travels in history state (hidden IDs), not the URL
        path: "order",
        name: "VendorOrderDetails",
        meta: { subscriptionModule: MODULE.ORDERS },
        component: () => import("../views/vendor/orders/OrderDetails.vue"),
      },
      {
        path: "deliveries",
        name: "VendorDeliverySchedule",
        meta: { subscriptionModule: MODULE.ORDERS },
        component: () => import("../views/vendor/orders/Deliveries.vue"),
      },
      {
        path: "returns",
        name: "VendorReturns",
        meta: { subscriptionModule: MODULE.ORDERS },
        component: () => import("../views/vendor/orders/Returns.vue"),
      },
      {
        path: "calendar",
        name: "VendorCalendar",
        meta: { subscriptionModule: MODULE.CALENDAR },
        component: () => import("../views/vendor/orders/Reservations.vue"),
      },
      {
        path: "add-product",
        name: "VendorAddProduct",
        meta: { subscriptionModule: MODULE.PRODUCTS },
        component: () => import("../views/vendor/AddProduct.vue"),
      },
      {
        path: "customization",
        name: "VendorCustomization",
        meta: { subscriptionModule: MODULE.PRODUCTS },
        component: () => import("../views/vendor/Customization.vue"),
      },
      {
        path: "chat",
        name: "VendorChat",
        meta: { subscriptionModule: MODULE.CRM },
        component: () => import("../views/vendor/crm/CustomerChat.vue"),
      },
      {
        path: "inquiries",
        name: "VendorInquiries",
        meta: { subscriptionModule: MODULE.CRM },
        component: () => import("../views/vendor/crm/Inquiries.vue"),
      },
      {
        path: "reviews",
        name: "VendorReviews",
        meta: { subscriptionModule: MODULE.PRODUCTS },
        component: () => import("../views/vendor/crm/Reviews.vue"),
      },
      {
        // Always available, whatever the plan
        path: "notifications",
        name: "VendorNotifications",
        component: () => import("../views/vendor/crm/Notifications.vue"),
      },
      {
        // Always available, whatever the plan
        path: "support",
        name: "VendorSupport",
        component: () => import("../views/vendor/VendorSupport.vue"),
      },
      {
        path: "finance/transactions",
        name: "VendorBooksSales",
        meta: { subscriptionModule: MODULE.FINANCE },
        component: () => import("../views/vendor/finance/Transactions.vue"),
      },
      {
        path: "finance/expenses",
        name: "VendorBooksExpenses",
        meta: { subscriptionModule: MODULE.FINANCE },
        component: () => import("../views/vendor/finance/Expenses.vue"),
      },
      {
        path: "finance/profit-loss",
        name: "VendorBooksProfit",
        meta: { subscriptionModule: MODULE.FINANCE },
        component: () => import("../views/vendor/finance/ProfitLoss.vue"),
      },
      {
        path: "finance/reports",
        name: "VendorBooksReports",
        meta: { subscriptionModule: MODULE.FINANCE },
        component: () => import("../views/vendor/finance/Reports.vue"),
      },
      {
        path: "finance/refunds",
        name: "VendorBooksRefunds",
        meta: { subscriptionModule: MODULE.FINANCE },
        component: () => import("../views/vendor/finance/Refunds.vue"),
      },
      {
        // The vendor's home overview. Open to every plan; each card follows the
        // plan's modules. ERP Finance still uses ERP/Finance/Dashboard.vue.
        path: "finance-dashboard",
        name: "VendorFinanceDashboard",
        component: () => import("../views/vendor/VendorDashboard.vue"),
      },
      {
        path: "profile",
        name: "VendorProfile",
        component: () => import("../views/vendor/VendorProfile.vue"),
      },
      // Staff & operations (VendorStaffList is the Employees page; it replaced the old staff list)
      {
        path: "staff-list",
        name: "VendorStaffList",
        meta: { subscriptionModule: MODULE.STAFF },
        component: () => import("../views/vendor/staff/Employees.vue"),
      },
      {
        path: "staff/roles",
        name: "VendorStaffRoles",
        meta: { subscriptionModule: MODULE.STAFF },
        component: () => import("../views/vendor/staff/Roles.vue"),
      },
      {
        path: "staff/attendance",
        name: "VendorStaffAttendance",
        meta: { subscriptionModule: MODULE.ATTENDANCE },
        component: () => import("../views/vendor/staff/Attendance.vue"),
      },
      {
        path: "staff/attendance/review",
        name: "VendorStaffAttendanceReview",
        meta: { subscriptionModule: MODULE.ATTENDANCE },
        component: () => import("../views/vendor/staff/AttendanceReview.vue"),
      },
      {
        path: "staff/attendance/devices",
        name: "VendorStaffAttendanceDevices",
        meta: { subscriptionModule: MODULE.ATTENDANCE },
        component: () => import("../views/vendor/staff/AttendanceDevices.vue"),
      },
      {
        path: "staff/schedules",
        name: "VendorStaffSchedules",
        meta: { subscriptionModule: MODULE.STAFF },
        component: () => import("../views/vendor/staff/Schedules.vue"),
      },
      {
        path: "staff/leave",
        name: "VendorStaffLeave",
        meta: { subscriptionModule: MODULE.LEAVE },
        component: () => import("../views/vendor/staff/Leave.vue"),
      },
      {
        path: "staff/planner",
        name: "VendorStaffPlanner",
        meta: { subscriptionModule: MODULE.STAFF },
        component: () => import("../views/vendor/staff/ShiftPlanner.vue"),
      },
      {
        path: "staff/payroll",
        name: "VendorStaffPayroll",
        meta: { subscriptionModule: MODULE.PAYROLL },
        component: () => import("../views/vendor/staff/Payroll.vue"),
      },
      {
        path: "staff/activity",
        name: "VendorStaffActivity",
        meta: { subscriptionModule: MODULE.STAFF },
        component: () => import("../views/vendor/staff/Activity.vue"),
      },
      // Subscription & account: open on every plan, even an expired one
      {
        path: "account/subscription",
        name: "VendorSubscription",
        component: () => import("../views/vendor/account/Subscription.vue"),
      },
      {
        path: "account/billing",
        name: "VendorBilling",
        component: () => import("../views/vendor/account/Billing.vue"),
      },
      {
        path: "account/usage",
        name: "VendorUsage",
        component: () => import("../views/vendor/account/Usage.vue"),
      },
      {
        path: "account/shop-settings",
        name: "VendorShopSettings",
        component: () => import("../views/vendor/account/ShopSettings.vue"),
      },
      {
        path: "account/security",
        name: "VendorSecurity",
        component: () => import("../views/vendor/account/Security.vue"),
      },
      {
        path: "force-change-password",
        name: "VendorForceChangePassword",
        component: () => import("../views/vendor/ForceChangePassword.vue"),
      },
    ],
  },

  // ===== ERP =====
  {
    path: "/erp",
    meta: { requiresAuth: true },
    children: [
      // ================= FINANCE =================
      {
        path: "finance",
        meta: { requiresDepartment: ["Finance"], subscriptionModule: MODULE.FINANCE },
        component: () => import("../views/ERP/Finance/FinanceLayout.vue"),
        children: [
          {
            path: "dashboard",
            name: "FinanceDashboard",
            // Follows the ERP light/dark switch
            meta: { erpTheme: true },
            component: () => import("../views/ERP/Finance/Dashboard.vue"),
          },
          {
            path: "sales",
            name: "FinanceSales",
            meta: { erpTheme: true },
            component: () => import("../views/ERP/Finance/Sales.vue"),
          },
          {
            path: "payments",
            name: "FinancePayments",
            meta: { erpTheme: true },
            component: () => import("../views/ERP/Finance/Payments.vue"),
          },
          {
            path: "expenses",
            name: "FinanceExpenses",
            meta: { erpTheme: true },
            component: () => import("../views/ERP/Finance/Expenses.vue"),
          },
          {
            path: "profit-loss",
            name: "FinanceProfitLoss",
            meta: { erpTheme: true },
            component: () => import("../views/ERP/Finance/ProfitLoss.vue"),
          },
          {
            path: "refunds",
            name: "FinanceRefunds",
            meta: { erpTheme: true },
            component: () => import("../views/ERP/Finance/Refunds.vue"),
          },
          {
            path: "reports",
            name: "FinanceReports",
            meta: { erpTheme: true },
            component: () => import("../views/ERP/Finance/Reports.vue"),
          },
          {
            path: "subscription",
            name: "FinanceSubscription",
            meta: { erpTheme: true },
            component: () => import("../views/ERP/Finance/SubscriptionBilling.vue"),
          },
          {
            path: "funding-requests",
            name: "FinanceFundingRequests",
            component: () => import("../views/ERP/Finance/FundingRequest.vue"),
          },
          {
            path: "funding-request/:id",
            name: "FinanceFundingRequestDetails",
            props: (route) => ({ id: route.params.id, context: "finance" }),
            component: () =>
              import("../views/ERP/Procurement/Inventory/FundingRequestDetails.vue"),
          },
          {
            path: "payroll-requests",
            name: "FinancePayrollRequests",
            component: () => import("../views/ERP/Finance/PayrollRequest.vue"),
          },
        ],
      },

      // ================= PROCUREMENT =================
      {
        path: "procurement",
        meta: { requiresDepartment: ["Procurement", "Purchasing"] },
        component: () =>
          import("../views/ERP/Procurement/ProcurementLayout.vue"),
        children: [
          // ───────── INVENTORY MANAGER ─────────
          {
            path: "inventory",
            meta: { requiresInventoryManager: true, subscriptionModule: MODULE.PRODUCTS },
            children: [
              {
                path: "funding-request",
                name: "FundingRequest",
                meta: { requiresInventoryManager: true, subscriptionModule: MODULE.FINANCE },
                component: () =>
                  import("../views/ERP/Procurement/Inventory/FundingRequest.vue"),
              },
              {
                path: "funding-request/create",
                name: "CreateFundingRequest",
                meta: { requiresInventoryManager: true, subscriptionModule: MODULE.FINANCE },
                component: () =>
                  import("../views/ERP/Procurement/Inventory/CreateFundingRequest.vue"),
              },
              {
                path: "funding-request/edit/:id",
                name: "EditFundingRequest",
                meta: { requiresInventoryManager: true, subscriptionModule: MODULE.FINANCE },
                props: true,
                component: () =>
                  import("../views/ERP/Procurement/Inventory/EditFundingRequest.vue"),
              },
              {
                path: "funding-request/details/:id",
                name: "FundingRequestDetails",
                meta: { requiresInventoryManager: true, subscriptionModule: MODULE.FINANCE },
                props: (route) => ({
                  id: route.params.id,
                  context: "inventory",
                }),
                component: () =>
                  import("../views/ERP/Procurement/Inventory/FundingRequestDetails.vue"),
              },
              {
                path: "products",
                name: "Products",
                meta: { requiresInventoryManager: true },
                props: () => ({ context: "inventory" }),
                component: () =>
                  import("../views/ERP/Procurement/Inventory/ViewProduct.vue"),
              },
              {
                path: "add-product",
                name: "AddProduct",
                meta: { requiresInventoryManager: true },
                props: () => ({ context: "inventory" }),
                component: () =>
                  import("../views/ERP/Procurement/Inventory/AddProduct.vue"),
              },
            ],
          },

          // ───────── SUPPLY CHAIN COORDINATOR ─────────
              {
                path: "supply-chain",
            meta: { requiresDepartment: ["Procurement"], subscriptionModule: MODULE.SUPPLY_CHAIN },
            redirect: "/erp/procurement/supply-chain/dashboard",
            children: [
              {
                path: "dashboard",
                name: "SupplyChainDashboard",
                component: () =>
                  import("../views/ERP/Procurement/SupplyChain/Analytics/Dashboard.vue"),
              },

              // Suppliers
              {
                path: "suppliers",
                meta: { subscriptionModule: MODULE.SUPPLIERS },
                children: [
                  {
                    path: "",
                    name: "SupplierList",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Suppliers/SupplierList.vue"),
                  },
                  {
                    path: "create",
                    name: "SupplierCreate",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Suppliers/SupplierCreate.vue"),
                  },
                  {
                    path: ":id/edit",
                    name: "SupplierEdit",
                    props: true,
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Suppliers/SupplierEdit.vue"),
                  },
                ],
              },

              // Warehouse
              {
                path: "warehouse",
                meta: { subscriptionModule: MODULE.WAREHOUSE },
                children: [
                  {
                    path: "",
                    name: "WarehouseList",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Warehouse/WarehouseList.vue"),
                  },
                  {
                    path: "inventory",
                    name: "WarehouseInventory",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Warehouse/WarehouseInventory.vue"),
                  },
                  {
                    path: "add-item",
                    name: "AddWarehouseItem",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Warehouse/AddItem.vue"),
                  },
                  {
                    path: "floor",
                    name: "WarehouseFloor",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Warehouse/FloorView.vue"),
                  },
                  {
                    path: "batches-receive",
                    name: "BatchesToReceive",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Warehouse/ReceiveBatch.vue"),
                  },
                  {
                    path: "locations",
                    name: "WarehouseLocations",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Warehouse/WarehouseLocation.vue"),
                  },
                  {
                    path: "scanner",
                    name: "WarehouseScanner",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Warehouse/WarehouseScanner.vue"),
                  },
                ],
              },

              // Orders
              {
                path: "orders",
                meta: { subscriptionModule: MODULE.ORDERS },
                children: [
                  {
                    path: "",
                    name: "OrderList",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Orders/OrderList.vue"),
                  },
                  {
                    path: "create",
                    name: "OrderCreate",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Orders/CreateOrder.vue"),
                  },
                  {
                    path: "detail/:id",
                    name: "OrderDetail",
                    props: true,
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Orders/OrderDetails.vue"),
                  },
                ],
              },

              // Logistics
              {
                path: "logistics",
                meta: { subscriptionModule: MODULE.LOGISTICS },
                children: [
                  {
                    path: "",
                    name: "ShipmentList",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Logistics/ShipmentList.vue"),
                  },
                  {
                    path: ":id",
                    name: "ShipmentTracking",
                    props: true,
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Logistics/ShipmentTracking.vue"),
                  },
                ],
              },

              // Delivery
              {
                path: "deliveries",
                meta: { subscriptionModule: MODULE.DELIVERIES },
                children: [
                  {
                    path: "",
                    name: "DeliveryList",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Delivery/SCOrders.vue"),
                  },
                  {
                    path: "vendor-orders",
                    name: "VendorDeliveries",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Delivery/VendorOrders.vue"),
                  },
                ],
              },

              // Scanner
              {
                path: "scan",
                meta: { subscriptionModule: MODULE.SCANNING },
                redirect: "/erp/procurement/supply-chain/scan/process",
                children: [
                  {
                    path: "process",
                    name: "ScanToProcess",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Scanner/ToProcess.vue"),
                  },
                  {
                    path: "ship",
                    name: "ScanToShip",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Scanner/ToShip.vue"),
                  },
                  {
                    path: "receive",
                    name: "ScanToReceive",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Scanner/ToReceive.vue"),
                  },
                  {
                    path: "completed",
                    name: "ScanCompleted",
                    component: () =>
                      import("../views/ERP/Procurement/SupplyChain/Scanner/Completed.vue"),
                  },
                ],
              },
            ],
          },
        ],
      },

      {
        path: "crm",
        meta: { subscriptionModule: MODULE.CRM },
        children: [
          {
            path: "chat",
            name: "CRMChat",
            component: () => import("../views/ERP/CRM/Chat.vue"),
          },
        ],
      },

      // ================= HR =================
      {
        path: "hr",
        meta: { requiresDepartment: ["HR", "Human Resources"], subscriptionModule: MODULE.HR },
        name: "HR",
        component: () => import("../views/ERP/HR/HRLayout.vue"),
        children: [
          {
            path: "",
            name: "HRDashboard",
            component: () => import("../views/ERP/HR/HRDashboard.vue"),
          },
          {
            path: "employees",
            name: "Employees",
            // erpTheme: these pages follow the ERP light/dark switch
            meta: { subscriptionModule: MODULE.EMPLOYEES, erpTheme: true },
            children: [
              {
                path: "directory",
                name: "EmployeeDirectory",
                component: () =>
                  import("../views/ERP/HR/Employees/Directory.vue"),
              },
              {
                path: "profiles",
                name: "EmployeeProfiles",
                component: () =>
                  import("../views/ERP/HR/Employees/Profiles.vue"),
              },
            ],
          },
          {
            path: "schedules",
            name: "ShiftSchedules",
            meta: { subscriptionModule: MODULE.STAFF, erpTheme: true },
            component: () => import("../views/ERP/HR/Schedules/ShiftPlanner.vue"),
          },
          {
            path: "attendance",
            name: "Attendance",
            meta: { subscriptionModule: MODULE.ATTENDANCE, erpTheme: true },
            children: [
              {
                path: "logs",
                name: "AttendanceLogs",
                component: () => import("../views/ERP/HR/Attendance/Logs.vue"),
              },
              {
                path: "review",
                name: "AttendanceReview",
                component: () => import("../views/ERP/HR/Attendance/Review.vue"),
              },
              {
                path: "reports",
                name: "AttendanceReports",
                component: () => import("../views/ERP/HR/Attendance/Reports.vue"),
              },
              {
                path: "qrscanner",
                name: "AttendanceQRScanner",
                component: () =>
                  import("../views/ERP/HR/Attendance/QRScanner.vue"),
              },
            ],
          },
          {
            path: "payroll",
            name: "Payroll",
            meta: { subscriptionModule: MODULE.PAYROLL, erpTheme: true },
            children: [
              {
                path: "list",
                name: "PayrollList",
                component: () =>
                  import("../views/ERP/HR/Payroll/PayrollList.vue"),
              },
              {
                path: "create",
                name: "PayrollCreate",
                component: () =>
                  import("../views/ERP/HR/Payroll/PayrollCreate.vue"),
              },
            ],
          },
          {
            path: "leave",
            name: "LeaveManagement",
            meta: { subscriptionModule: MODULE.LEAVE, erpTheme: true },
            children: [
              {
                path: "employee-request",
                name: "EmployeeLeaveRequest",
                redirect: "/leaves",
              },
              {
                path: "management-requests",
                name: "LeaveRequests",
                component: () =>
                  import("../views/ERP/HR/Leaves/LeaveManagement.vue"),
              },
              {
                path: "qr-request",
                name: "LeaveQRRequest",
                redirect: "/leaves",
              },
            ],
          },
        ],
      },
    ],
  },

  // ===== ADMIN =====
  // Order follows the admin sidebar. Some pages share a component and show
  // the part named by `section`.
  {
    path: "/admin",
    meta: { requiresAuth: true, requiresAdmin: true },
    redirect: "/admin/dashboard",
    children: [
      // Overview
      {
        path: "dashboard",
        name: "AdminDashboard",
        component: () => import("../views/admin/Dashboard.vue"),
      },
      // Marketplace
      {
        path: "orders",
        name: "AdminOrders",
        component: () => import("../views/admin/Orders.vue"),
      },
      {
        path: "product-approval",
        name: "ProductApproval",
        component: () => import("../views/admin/Products.vue"),
        props: { section: "listings" },
      },
      {
        path: "categories",
        name: "AdminCategories",
        component: () => import("../views/admin/Products.vue"),
        props: { section: "lists" },
      },
      {
        path: "website",
        name: "AdminContent",
        component: () => import("../views/admin/Content.vue"),
      },
      {
        path: "reviews",
        name: "AdminReviews",
        component: () => import("../views/admin/Reviews.vue"),
      },
      // Finance
      {
        path: "finance",
        name: "AdminFinance",
        component: () => import("../views/admin/Finance.vue"),
      },
      {
        path: "refunds",
        name: "AdminRefunds",
        component: () => import("../views/admin/Refunds.vue"),
      },
      {
        path: "subscription-revenue",
        name: "AdminSubscriptionRevenue",
        component: () => import("../views/admin/SubscriptionRevenue.vue"),
      },
      {
        path: "analytics",
        name: "AdminAnalytics",
        component: () => import("../views/admin/Analytics.vue"),
      },
      // People & operations
      {
        path: "vendor-requests",
        name: "VendorRequest",
        component: () => import("../views/admin/VendorRequest.vue"),
      },
      {
        path: "customers",
        name: "AdminCustomers",
        component: () => import("../views/admin/Customers.vue"),
      },
      {
        path: "support",
        name: "AdminSupport",
        component: () => import("../views/admin/Support.vue"),
      },
      {
        path: "audit-logs",
        name: "AdminAuditLogs",
        component: () => import("../views/admin/Security.vue"),
        props: { section: "audit" },
      },
      {
        path: "security",
        name: "AdminSecurity",
        component: () => import("../views/admin/Security.vue"),
        props: { section: "access" },
      },
      {
        path: "settings",
        name: "AdminSettings",
        component: () => import("../views/admin/Security.vue"),
        props: { section: "settings" },
      },
      {
        path: "health",
        name: "AdminHealth",
        component: () => import("../views/admin/Security.vue"),
        props: { section: "health" },
      },
      // Older pages, now merged into the ones above
      {
        path: "reports",
        redirect: (to) => ({ path: "/admin/reviews", query: { ...to.query, tab: "reports" } }),
      },
      {
        path: "login-logs",
        redirect: (to) => ({ path: "/admin/audit-logs", query: { ...to.query, tab: "history" } }),
      },
    ],
  },

  // 404 Catch All
  {
    path: "/:pathMatch(.*)*",
    name: "NotFound",
    component: () => import("../views/guest/NotFound.vue"),
  },
];

const joinRoutePath = (parentPath, childPath) => {
  if (childPath.startsWith("/")) return childPath;
  if (!parentPath || parentPath === "/") return childPath ? `/${childPath}` : "/";
  return childPath ? `${parentPath.replace(/\/$/, "")}/${childPath}` : parentPath;
};

const parameterSuffix = (path) =>
  path.split("/").filter((segment) => segment.startsWith(":")).join("/");

const routeAuthContext = (canonicalPath) => {
  if (canonicalPath === "/erp" || canonicalPath.startsWith("/erp/")) return "employee";
  if (
    ["/admin", "/vendor", "/customer"].some(
      (prefix) => canonicalPath === prefix || canonicalPath.startsWith(`${prefix}/`),
    )
  ) {
    return "user";
  }
  return null;
};

// Parent layouts are never linked directly. Their opaque paths only prevent a
// readable parent URL from being exposed when a legacy parent URL is opened.
const opaqueLayoutPath = (canonicalPath, index) =>
  `/L${Math.abs([...canonicalPath].reduce((hash, char) => ((hash * 31) + char.charCodeAt(0)) | 0, 17)).toString(36)}${index.toString(36)}z9Qk7Wm2Vr8Hf4Xc6P`;

const legacyAliases = new Map();

const buildOpaqueRoutes = (records, parentPath = "", parentIndex = 0) =>
  records.map((record, index) => {
    const canonicalPath = joinRoutePath(parentPath, record.path);
    const isCatchAll = record.name === "NotFound";
    const clone = {
      ...record,
      meta: {
        ...record.meta,
        canonicalPath,
        authContext: routeAuthContext(canonicalPath),
      },
    };

    if (record.meta?.preservePublicPath) {
      clone.path = canonicalPath;
    } else if (!isCatchAll && record.name && opaqueRouteTokens[record.name]) {
      clone.path = opaquePathFor(record.name, parameterSuffix(canonicalPath) ? `/${parameterSuffix(canonicalPath)}` : "");
      legacyAliases.set(canonicalPath, {
        path: canonicalPath,
        name: `Legacy_${record.name}`,
        redirect: (to) => ({ name: record.name, params: to.params, query: to.query, hash: to.hash }),
      });
    } else if (!isCatchAll) {
      clone.path = opaqueLayoutPath(canonicalPath, parentIndex + index);
      if (record.redirect) {
        legacyAliases.set(canonicalPath, {
          path: canonicalPath,
          name: `LegacyLayout_${parentIndex}_${index}`,
          redirect: record.redirect,
        });
      }
    }

    if (record.children) {
      clone.children = buildOpaqueRoutes(record.children, canonicalPath, (parentIndex + 1) * 31 + index);
    }

    return clone;
  });

const catchAllRoute = routes.find((route) => route.name === "NotFound");
const applicationRoutes = routes.filter((route) => route.name !== "NotFound");
const opaqueRoutes = buildOpaqueRoutes(applicationRoutes);

const router = createRouter({
  history: createWebHistory(),
  routes: [...opaqueRoutes, ...legacyAliases.values(), catchAllRoute],
  scrollBehavior() {
    return { top: 0 };
  },
});

// Existing components can continue to use their current string paths. Router
// links, push(), and replace() are normalized before the browser sees them.
const rawResolve = router.resolve.bind(router);
const resolveOpaqueTarget = (location) => {
  let target = location;

  for (let attempt = 0; attempt < 3; attempt += 1) {
    const resolved = rawResolve(target);
    const redirect = resolved.matched.at(-1)?.redirect;
    if (!redirect) return target;
    target = typeof redirect === "function" ? redirect(resolved) : redirect;
  }

  return target;
};

const rawPush = router.push.bind(router);
const rawReplace = router.replace.bind(router);
// Record IDs in a link (?order=5) move into history state so the address
// bar never shows them. Pages read them back with linkedId().
const asObject = (location) => {
  if (typeof location !== "string" || !location.includes("?")) return location;
  const { path, query, hash } = rawResolve(location);
  return { path, query, hash };
};
const normalizeTarget = (location) => {
  const target = asObject(resolveOpaqueTarget(location));
  const state = typeof location === "object" ? location?.state : undefined;
  return hideIdsInLocation(state && typeof target === "object" ? { ...target, state: { ...state, ...(target.state ?? {}) } } : target);
};
router.resolve = (location, currentLocation) => rawResolve(normalizeTarget(location), currentLocation);
router.push = (location) => rawPush(normalizeTarget(location));
router.replace = (location) => rawReplace(normalizeTarget(location));

// Existing RBAC keys for direct employee page navigation. Subscription access
// is checked separately from this map by the Stage 3 guard above.
const employeeModuleForPath = (path) => {
  const mappings = [
    ["/erp/procurement/supply-chain/warehouse", "warehouse"],
    ["/erp/procurement/supply-chain/suppliers", "suppliers"],
    ["/erp/procurement/supply-chain/orders", "sc_orders"],
    ["/erp/procurement/supply-chain/logistics", "deliveries"],
    ["/erp/procurement/supply-chain/deliveries", "deliveries"],
    ["/erp/procurement/supply-chain/scan", "order_scan"],
    ["/erp/procurement/supply-chain/dashboard", "sc_dashboard"],
    ["/erp/procurement/inventory/funding-request", "inventory_funding"],
    ["/erp/procurement/inventory", "inventory_products"],
    ["/erp/finance/sales", "sales_revenue"],
    ["/erp/finance/payments", "payments"],
    ["/erp/finance/expenses", "expenses"],
    ["/erp/finance/profit-loss", "profit_loss"],
    ["/erp/finance/refunds", "refunds"],
    ["/erp/finance/reports", "financial_reports"],
    ["/erp/finance/subscription", "subscription_billing"],
    ["/erp/finance/funding", "funding_requests"],
    ["/erp/finance/payroll", "payroll_requests"],
    ["/erp/finance", "finance_dashboard"],
    ["/erp/crm", "crm"],
    ["/erp/hr/employees", "employees"],
    ["/erp/hr/attendance", "attendance"],
    ["/erp/hr/payroll", "payroll"],
    ["/erp/hr/leave", "leave_management"],
    ["/erp/hr", "hr_dashboard"],
  ];
  return mappings.find(([prefix]) => path.startsWith(prefix))?.[1] ?? null;
};

/**
 * Global Route Guard
 */
// Where a signed-in customer, vendor or admin goes instead of a page that isn't
// theirs (null = the page is fine). Uses the stored account, so it never waits
// on the network.
const SIGNED_IN_HOMES = { customer: "/shop", vendor: "/vendor/finance-dashboard", admin: "/admin/dashboard" };
function signedInHomeFor(to, user) {
  let role = user?.role;
  if (!role) {
    try {
      role = JSON.parse(localStorage.getItem("user") || "null")?.role;
    } catch {
      role = null;
    }
  }
  const home = SIGNED_IN_HOMES[role];
  if (!home) return null;
  if (to.name === "Home") return home;
  if ((role === "vendor" || role === "admin") && to.meta.onlyCustomerOrGuest) return home;
  return null;
}

router.beforeEach(async (to, from, next) => {
  // A URL opened directly (bookmark, new tab) may still carry IDs: move them out
  if (HIDDEN_ID_KEYS.some((key) => key in to.query)) {
    return next({ ...hideIdsInLocation({ path: to.path, query: to.query, hash: to.hash }), replace: true });
  }

  const auth = useAuth();
  const canonicalPath = to.meta.canonicalPath || to.path;
  const authContext = to.meta.authContext || canonicalPath;
  const userType = getPreferredUserType(authContext);
  const token = getPreferredAuthToken(authContext);

  // Signed-in accounts stay in their own area: customers skip the landing page
  // for the shop; vendors and admins skip the landing page and the shop pages
  // for their dashboard.
  const roleHome = token && userType !== "employee" ? signedInHomeFor(to, auth.user.value) : null;
  if (roleHome && roleHome !== canonicalPath) {
    return next({ path: roleHome, replace: true });
  }

  if (to.meta.public) {
    return next();
  }

  if (!token) {
    auth.user.value = null;

    if (to.meta.requiresAuth) {
      if (canonicalPath !== "/guest/login") {
        localStorage.setItem("redirectAfterLogin", to.fullPath);
      }
      return next("/guest/login");
    }

    return next();
  }

  // Load user if not in memory (happens on refresh)
  if (!auth.user.value) {
    try {
      await auth.loadUser(to.path);
      // Wait for loadUser to complete, which now hydrates auth.user.value
      // from localStorage immediately.
    } catch (err) {
      const status = err?.response?.status;

      // If it's a definitive 401, clear and redirect
      if (status === 401) {
        clearStoredAuth();
        auth.user.value = null;
        if (
          to.meta.requiresAuth ||
          to.meta.requiresAdmin ||
          to.meta.requiresVendor
        ) {
          return next("/guest/login");
        }
        return next();
      }

      // For other errors (network timeout, 500), if we have a user in memory
      // from localStorage, we permit the navigation to proceed rather than
      // forcing a logout.
      if (
        !auth.user.value &&
        (to.meta.requiresAuth ||
          to.meta.requiresAdmin ||
          to.meta.requiresVendor)
      ) {
        return next("/guest/login");
      }
    }
  }

  // After loading attempt, check if we need to bootstrap assignments for employees
  if (auth.user.value && userType === "employee") {
    const assignment = useAssignment();
    if (!assignment.hasAnyPermission.value) {
      await assignment.loadAssignments(auth.user.value);
    }
  }

  const user = auth.user.value;
  const role = user?.role;
  const subscriptionModule = to.meta.subscriptionModule;

  // Subscription access belongs to the vendor company. It applies before the
  // existing employee permission checks, so an employee cannot reach a module
  // merely because their individual role allows it.
  if (subscriptionModule && (role === "vendor" || userType === "employee")) {
    try {
      const access = await loadSubscriptionAccess({ maxAge: ACCESS_REUSE_MS });
      if (!subscriptionAllowsModule(access, subscriptionModule)) {
        return next(userType === "employee" ? "/guest/login" : "/vendor/profile");
      }
    } catch (err) {
      // Subscription state cannot be safely inferred from stale browser data.
      return next(userType === "employee" ? "/guest/login" : "/vendor/profile");
    }
  }

  // ── Employee routing: use module-based permissions ──────────────────────
  if (userType === "employee") {
    const assignment = useAssignment();

    // If no permissions at all, redirect to login
    if (!assignment.hasAnyPermission.value) {
      return next("/guest/login");
    }

    // If employee is trying to access a non-ERP path, send them home
    if (!canonicalPath.startsWith("/erp") && canonicalPath !== "/guest/login") {
      return next(assignment.getDefaultRoute());
    }

    // Enforce module-based access per ERP section
    if (canonicalPath.startsWith("/erp/hr") && !assignment.hasGroupAccess("HR")) {
      return next(assignment.getDefaultRoute());
    }
    if (
      canonicalPath.startsWith("/erp/finance") &&
      !assignment.hasGroupAccess("Finance")
    ) {
      return next(assignment.getDefaultRoute());
    }
    if (
      canonicalPath.startsWith("/erp/procurement/inventory") &&
      !assignment.hasGroupAccess("Procurement")
    ) {
      return next(assignment.getDefaultRoute());
    }
    if (
      canonicalPath.startsWith("/erp/procurement/supply-chain") &&
      !assignment.hasGroupAccess("Supply Chain")
    ) {
      return next(assignment.getDefaultRoute());
    }
    if (canonicalPath.startsWith("/erp/crm") && !assignment.canView("crm")) {
      return next(assignment.getDefaultRoute());
    }

    const requiredEmployeeModule = employeeModuleForPath(canonicalPath);
    if (requiredEmployeeModule && !assignment.canView(requiredEmployeeModule)) {
      return next(assignment.getDefaultRoute());
    }

    // Pass through if the path matches the permissions or is generic
    return next();
  }

  // ── Non-employee routing (owner, admin, customer) ─────────────────────
  if (canonicalPath.startsWith("/erp")) {
    if (role === "admin") return next("/admin/dashboard");
    if (role === "vendor") return next();
    if (role === "customer") return next("/shop");
    return next("/guest/login");
  }

  // Handle vendor forced password change
  const needsPasswordChange = user?.vendor_data?.needs_password_change === true;
  if (role === "vendor" && needsPasswordChange) {
    if (
      canonicalPath !== "/vendor/force-change-password" &&
      canonicalPath !== "/guest/login"
    ) {
      return next("/vendor/force-change-password");
    }
  }

  if (to.meta.requiresAdmin && role !== "admin") {
    if (role === "vendor") {
      return needsPasswordChange
        ? next("/vendor/force-change-password")
        : next("/vendor/products");
    }
    if (role === "customer") return next("/shop");
    return next("/guest/login");
  }

  // Admin pages also need a permission from the admin's role
  if (to.meta.requiresAdmin && role === "admin") {
    const required = adminAccess.permissionForPath(canonicalPath);
    if (required) {
      try {
        await adminAccess.load();
      } catch {
        return next();
      }
      if (!adminAccess.can(required)) {
        const fallback = adminAccess.firstAllowedPath();
        return fallback && fallback !== canonicalPath ? next(fallback) : next("/guest/login");
      }
    }
  }

  if (to.meta.requiresVendor && role !== "vendor") {
    if (role === "admin") return next("/admin/dashboard");
    if (role === "customer") return next("/shop");
    return next("/guest/login");
  }

  if (
    to.meta.onlyCustomerOrGuest &&
    userType !== "customer" &&
    !to.meta.public
  ) {
    if (role === "admin") return next("/admin/dashboard");
    if (role === "vendor") return next("/vendor/products");
    return next("/guest/login");
  }

  next();
});

export default router;
