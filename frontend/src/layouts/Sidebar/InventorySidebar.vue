<template>
  <div class="inventory-sidebar">
    <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />

    <!-- Logo Section -->
    <div class="sidebar-header">
      <div class="logo-container">
        <div class="logo-circle">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="white"
          >
            <path
              d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"
            ></path>
            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"></path>
            <line x1="12" y1="12" x2="12" y2="17"></line>
            <line x1="9.5" y1="14.5" x2="14.5" y2="14.5"></line>
          </svg>
        </div>
        <div class="logo-text">
          <div class="company-name">Inventory</div>
          <div class="branch-name">Inventory Portal</div>
        </div>
      </div>
    </div>

    <!-- ✅ Role Switcher — shows only when employee has multiple assignments -->
    <RoleSwitcher />

    <!-- Search Box -->
    <div class="search-box">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
      >
        <circle cx="11" cy="11" r="8"></circle>
        <path d="m21 21-4.35-4.35"></path>
      </svg>
      <input type="text" placeholder="Search Stockwise" v-model="searchQuery" />
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
      <router-link
        to="/erp/procurement/inventory/dashboard"
        class="nav-item"
        :class="{ active: isDashboardActive }"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="currentColor"
        >
          <path
            d="M4 13h6c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v8c0 .55.45 1 1 1zm0 8h6c.55 0 1-.45 1-1v-4c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v4c0 .55.45 1 1 1zm10 0h6c.55 0 1-.45 1-1v-8c0-.55-.45-1-1-1h-6c-.55 0-1 .45-1 1v8c0 .55.45 1 1 1zM13 4v4c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1h-6c-.55 0-1 .45-1 1z"
          />
        </svg>
        <span>Dashboard</span>
      </router-link>

      <router-link
        to="/erp/procurement/inventory/products"
        class="nav-item"
        :class="{ active: isProductsActive }"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="currentColor"
        >
          <path
            d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l7.59-7.59L21 8l-9 9z"
          />
        </svg>
        <span>Products</span>
      </router-link>

      <router-link
        to="/erp/procurement/inventory/stock"
        class="nav-item"
        :class="{ active: isStockActive }"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="currentColor"
        >
          <path
            d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"
          />
          <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
        </svg>
        <span>Stock Management</span>
      </router-link>

      <router-link
        to="/erp/procurement/inventory/funding-request"
        class="nav-item"
        :class="{ active: isFundingRequestsActive }"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="currentColor"
        >
          <path
            d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM17 12h-4v4h-2v-4H7v-2h4V6h2v4h4v2z"
          />
        </svg>
        <span>Funding Requests</span>
      </router-link>

      <router-link
        to="/erp/procurement/inventory/purchase-orders"
        class="nav-item"
        :class="{ active: isPurchaseOrdersActive }"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="currentColor"
        >
          <path
            d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"
          />
        </svg>
        <span>Purchase Orders</span>
      </router-link>

      <router-link
        to="/erp/procurement/inventory/suppliers"
        class="nav-item"
        :class="{ active: isSuppliersActive }"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="currentColor"
        >
          <path
            d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"
          />
        </svg>
        <span>Suppliers</span>
      </router-link>

      <router-link
        to="/erp/procurement/inventory/reports"
        class="nav-item"
        :class="{ active: isReportsActive }"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="currentColor"
        >
          <path
            d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"
          />
        </svg>
        <span>Reports</span>
      </router-link>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
      <div class="user-profile">
        <div class="user-avatar">{{ userInitials }}</div>
        <div class="user-info">
          <div class="user-name">{{ userName }}</div>
          <div class="user-role">Inventory Department</div>
        </div>
      </div>
      <button class="logout-btn" @click="handleLogout">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
        <span>Logout</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { useRoute } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import LoadingOverlay from "../components/LoadingOverlay.vue";
import RoleSwitcher from "../components/RoleSwitcher.vue";

const route = useRoute();
const { logout } = useAuth();

const searchQuery = ref("");
const isLoading = ref(false);
const isLoadingMessage = ref("Loading...");

const isDashboardActive = computed(
  () => route.path === "/erp/procurement/inventory/dashboard",
);
const isProductsActive = computed(() =>
  route.path.startsWith("/erp/procurement/inventory/products"),
);
const isStockActive = computed(() =>
  route.path.startsWith("/erp/procurement/inventory/stock"),
);
const isFundingRequestsActive = computed(() =>
  route.path.startsWith("/erp/procurement/inventory/funding-request"),
);
const isPurchaseOrdersActive = computed(() =>
  route.path.startsWith("/erp/procurement/inventory/purchase-orders"),
);
const isSuppliersActive = computed(() =>
  route.path.startsWith("/erp/procurement/inventory/suppliers"),
);
const isReportsActive = computed(() =>
  route.path.startsWith("/erp/procurement/inventory/reports"),
);

const userName = computed(() => {
  try {
    const u = JSON.parse(localStorage.getItem("user") ?? "{}");
    return u.name || u.username || "Inventory Manager";
  } catch {
    return "Inventory Manager";
  }
});

const userInitials = computed(() =>
  userName.value
    .split(" ")
    .map((w) => w[0])
    .join("")
    .toUpperCase()
    .slice(0, 2),
);

const handleLogout = async () => {
  if (isLoading.value) return;
  isLoading.value = true;
  isLoadingMessage.value = "Logging out...";
  try {
    await logout();
  } catch (e) {
    console.error(e);
  } finally {
    isLoading.value = false;
  }
};
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.inventory-sidebar {
  position: fixed;
  left: 0;
  top: 0;
  width: 260px;
  height: 100vh;
  background: #ffffff;
  border-right: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  z-index: 100;
}
.inventory-sidebar::-webkit-scrollbar {
  width: 6px;
}
.inventory-sidebar::-webkit-scrollbar-track {
  background: transparent;
}
.inventory-sidebar::-webkit-scrollbar-thumb {
  background: #cbd5e0;
  border-radius: 3px;
}
.inventory-sidebar::-webkit-scrollbar-thumb:hover {
  background: #a0aec0;
}

.sidebar-header {
  padding: 20px 16px;
  border-bottom: 1px solid #f7fafc;
}
.logo-container {
  display: flex;
  align-items: center;
  gap: 12px;
}
.logo-circle {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: linear-gradient(135deg, #4299e1 0%, #2b6cb0 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.logo-text {
  display: flex;
  flex-direction: column;
}
.company-name {
  font-size: 14px;
  font-weight: 700;
  color: #1a202c;
  letter-spacing: 0.5px;
}
.branch-name {
  font-size: 12px;
  color: #718096;
  font-weight: 500;
}

.search-box {
  margin: 10px 12px 4px;
  padding: 10px 14px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  display: flex;
  align-items: center;
  gap: 10px;
  transition: all 0.2s;
}
.search-box:focus-within {
  background: #fff;
  border-color: #4299e1;
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}
.search-box svg {
  color: #a0aec0;
  flex-shrink: 0;
}
.search-box input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  color: #1a202c;
  font-family: "Poppins", sans-serif;
}
.search-box input::placeholder {
  color: #a0aec0;
}

.sidebar-nav {
  flex: 1;
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border: none;
  background: transparent;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  width: 100%;
}
.nav-item:hover {
  background: #ebf8ff;
  color: #2b6cb0;
}
.nav-item.active,
.nav-item.router-link-exact-active {
  background: #bee3f8 !important;
  color: #2c5282 !important;
  font-weight: 600;
}
.nav-item svg {
  flex-shrink: 0;
}
.nav-item span {
  flex: 1;
}

.sidebar-footer {
  margin-top: auto;
  padding: 16px;
  border-top: 1px solid #f7fafc;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.user-profile {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px;
  border-radius: 10px;
  transition: background-color 0.2s;
}
.user-profile:hover {
  background: #f7fafc;
}
.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #4299e1 0%, #2b6cb0 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 14px;
  flex-shrink: 0;
}
.user-info {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}
.user-name {
  font-size: 14px;
  font-weight: 600;
  color: #1a202c;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.user-role {
  font-size: 12px;
  color: #718096;
  font-weight: 500;
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: transparent;
  border: 1px solid #fc8181;
  border-radius: 10px;
  color: #c53030;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  width: 100%;
  font-family: "Poppins", sans-serif;
}
.logout-btn:hover {
  background: #fed7d7;
  border-color: #f56565;
  color: #9b2c2c;
}
.logout-btn:active {
  transform: translateY(1px);
}
.logout-btn svg {
  flex-shrink: 0;
}

@media (max-width: 768px) {
  .inventory-sidebar {
    transform: translateX(-100%);
  }
}
</style>
