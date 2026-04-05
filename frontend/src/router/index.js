import { createRouter, createWebHistory } from "vue-router";
import { useAuth } from "../composables/useAuth";
import { useAssignment } from "../composables/useAssignment";
import { getPreferredAuthToken, getPreferredUserType } from "../utils/authSession";

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
        path: "shop",
        name: "Shop",
        component: () => import("../views/authenticated/Shop.vue"),
        meta: { public: true, onlyCustomerOrGuest: true },
      },
      {
        path: "customize/flower",
        name: "FlowerCustomizerStore",
        component: () =>
          import("../views/authenticated/FlowerCustomizer.vue"),
        meta: { public: true, onlyCustomerOrGuest: true },
      },
      {
        path: "customize/:id?",
        name: "FlowerCustomizer",
        component: () =>
          import("../views/authenticated/FlowerCustomizer.vue"),
        meta: { public: true, onlyCustomerOrGuest: true },
      },
      {
        path: "store/:id",
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
        path: "guest/vendor_register",
        name: "Vendor_Register",
        component: () => import("../views/guest/VendorRegistration.vue"),
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
        component: () => import("../views/vendor/ViewProduct.vue"),
      },
      {
        path: "reservation",
        name: "VendorReservation",
        component: () => import("../views/vendor/AllOrders.vue"),
      },
      {
        path: "calendar",
        name: "VendorCalendar",
        component: () => import("../views/vendor/Reservation.vue"),
      },
      {
        path: "add-product",
        name: "VendorAddProduct",
        component: () => import("../views/vendor/AddProduct.vue"),
      },
      {
        path: "chat",
        name: "VendorChat",
        component: () => import("../views/vendor/VendorChat.vue"),
      },
      {
        path: "finance-dashboard",
        name: "VendorFinanceDashboard",
        component: () => import("../views/ERP/Finance/Dashboard.vue"),
      },
      {
        path: "profile",
        name: "VendorProfile",
        component: () => import("../views/vendor/VendorProfile.vue"),
      },
      {
        path: "staff-list",
        name: "VendorStaffList",
        component: () => import("../views/vendor/StaffList.vue"),
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
        meta: { requiresDepartment: ["Finance"] },
        component: () => import("../views/ERP/Finance/FinanceLayout.vue"),
        children: [
          {
            path: "dashboard",
            name: "FinanceDashboard",
            component: () => import("../views/ERP/Finance/Dashboard.vue"),
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
            meta: { requiresInventoryManager: true },
            children: [
              {
                path: "funding-request",
                name: "FundingRequest",
                meta: { requiresInventoryManager: true },
                component: () =>
                  import("../views/ERP/Procurement/Inventory/FundingRequest.vue"),
              },
              {
                path: "funding-request/create",
                name: "CreateFundingRequest",
                meta: { requiresInventoryManager: true },
                component: () =>
                  import("../views/ERP/Procurement/Inventory/CreateFundingRequest.vue"),
              },
              {
                path: "funding-request/edit/:id",
                name: "EditFundingRequest",
                meta: { requiresInventoryManager: true },
                props: true,
                component: () =>
                  import("../views/ERP/Procurement/Inventory/EditFundingRequest.vue"),
              },
              {
                path: "funding-request/details/:id",
                name: "FundingRequestDetails",
                meta: { requiresInventoryManager: true },
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
            meta: { requiresDepartment: ["Procurement"] },
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
        meta: { requiresDepartment: ["HR", "Human Resources"] },
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
            path: "attendance",
            name: "Attendance",
            children: [
              {
                path: "logs",
                name: "AttendanceLogs",
                component: () => import("../views/ERP/HR/Attendance/Logs.vue"),
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
            children: [
              {
                path: "employee-request",
                name: "EmployeeLeaveRequest",
                alias: "/leaves",
                component: () =>
                  import("../views/ERP/HR/Public/LeaveRequest.vue"),
                meta: { requiresAuth: false, title: "Leave Request" },
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
                component: () =>
                  import("../views/ERP/HR/Leaves/LeaveRequestScanner.vue"),
              },
            ],
          },
        ],
      },
    ],
  },

  // ===== ADMIN =====
  {
    path: "/admin",
    meta: { requiresAuth: true, requiresAdmin: true },
    redirect: "/admin/vendor-requests",
    children: [
      {
        path: "dashboard",
        name: "AdminDashboard",
        component: () => import("../views/admin/Dashboard.vue"),
      },
      {
        path: "vendor-requests",
        name: "VendorRequest",
        component: () => import("../views/admin/VendorRequest.vue"),
      },
      {
        path: "product-approval",
        name: "ProductApproval",
        component: () => import("../views/admin/ProductApproval.vue"),
      },
      {
        path: "reports",
        name: "ReportedProducts",
        component: () => import("../views/admin/ReportedProducts.vue"),
      },
      {
        path: "login-logs",
        name: "AdminLoginLogs",
        component: () => import("../views/admin/LoginLogs.vue"),
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

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 };
  },
});

/**
 * Global Route Guard
 */
router.beforeEach(async (to, from, next) => {
  const auth = useAuth();
  const userType = getPreferredUserType(to.path);
  const token = getPreferredAuthToken(to.path);
  const clearStoredAuth = () => {
    localStorage.removeItem("auth_token");
    localStorage.removeItem("employee_token");
    localStorage.removeItem("vendor_token");
    localStorage.removeItem("user");
    localStorage.removeItem("user_type");
  };

  if (to.meta.public) {
    return next();
  }

  if (!token) {
    auth.user.value = null;

    if (to.meta.requiresAuth) {
      if (to.path !== "/guest/login") {
        localStorage.setItem("redirectAfterLogin", to.fullPath);
      }
      return next("/guest/login");
    }

    return next();
  }

  // Load user if not in memory
  if (!auth.user.value) {
    try {
      await auth.loadUser(to.path);

      // Bootstrap assignments from user data if it's an employee
      if (userType === "employee") {
        const { loadAssignments } = useAssignment();
        // Since loadUser populates auth.user.value:
        await loadAssignments(auth.user.value);
      }
    } catch (err) {
      const status = err?.response?.status;
      console.warn("Auth load failed", { status, path: to.path });

      if (status === 401) {
        clearStoredAuth();
        auth.user.value = null;

        if (
          to.meta.requiresAuth ||
          to.meta.requiresAdmin ||
          to.meta.requiresVendor ||
          to.meta.requiresDepartment
        ) {
          return next("/guest/login");
        }

        return next();
      }

      // For transient bootstrap failures, preserve the local session state
      // instead of forcing a logout on refresh.
      if (auth.user.value && token) {
        return next();
      }

      if (
        to.meta.requiresAuth ||
        to.meta.requiresAdmin ||
        to.meta.requiresVendor ||
        to.meta.requiresDepartment
      ) {
        return next("/guest/login");
      }

      return next();
    }
  }

  const user = auth.user.value;
  const role = user?.role;

  // ── Employee routing: use module-based permissions ──────────────────────
  if (userType === "employee") {
    const assignment = useAssignment();

    // If no permissions at all, redirect to login
    if (!assignment.hasAnyPermission.value) {
      return next("/guest/login");
    }

    // If employee is trying to access a non-ERP path, send them home
    if (
      !to.path.startsWith("/erp") &&
      to.path !== "/guest/login"
    ) {
      return next(assignment.getDefaultRoute());
    }

    // Enforce module-based access per ERP section
    if (to.path.startsWith("/erp/hr") && !assignment.hasGroupAccess("HR")) {
      return next(assignment.getDefaultRoute());
    }
    if (
      to.path.startsWith("/erp/finance") &&
      !assignment.hasGroupAccess("Finance")
    ) {
      return next(assignment.getDefaultRoute());
    }
    if (
      to.path.startsWith("/erp/procurement/inventory") &&
      !assignment.hasGroupAccess("Procurement")
    ) {
      return next(assignment.getDefaultRoute());
    }
    if (
      to.path.startsWith("/erp/procurement/supply-chain") &&
      !assignment.hasGroupAccess("Supply Chain")
    ) {
      return next(assignment.getDefaultRoute());
    }
    if (to.path.startsWith("/erp/crm") && !assignment.canView("crm")) {
      return next(assignment.getDefaultRoute());
    }

    // Pass through if the path matches the permissions or is generic
    return next();
  }

  // ── Non-employee routing (owner, admin, customer) ─────────────────────
  if (to.path.startsWith("/erp")) {
    if (role === "admin") return next("/admin/vendor-requests");
    if (role === "vendor") return next("/vendor/products");
    if (role === "customer") return next("/shop");
    return next("/guest/login");
  }

  // Handle vendor forced password change
  const needsPasswordChange = user?.vendor_data?.needs_password_change === true;
  if (role === "vendor" && needsPasswordChange) {
    if (
      to.path !== "/vendor/force-change-password" &&
      to.path !== "/guest/login"
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

  if (to.meta.requiresVendor && role !== "vendor") {
    if (role === "admin") return next("/admin/vendor-requests");
    if (role === "customer") return next("/shop");
    return next("/guest/login");
  }

  if (
    to.meta.onlyCustomerOrGuest &&
    userType !== "customer" &&
    !to.meta.public
  ) {
    if (role === "admin") return next("/admin/vendor-requests");
    if (role === "vendor") return next("/vendor/products");
    return next("/guest/login");
  }

  next();
});

export default router;
