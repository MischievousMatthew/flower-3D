<template>
  <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />

  <!-- Backdrop for mobile -->
  <div v-if="isMobileOpen" class="sidebar-backdrop" @click="closeMobile"></div>

  <aside class="sidebar" :class="{ 'mobile-open': isMobileOpen }">
    <div class="logo-section">
      <div class="logo">
        <span class="logo-icon"
          ><img
            src="../../../public/bloomcraft-blankBg.png"
            alt="Bloomcraft Logo"
            width="50"
            height="50"
        /></span>
        <span class="logo-text">BloomCraft Admin</span>
      </div>
    </div>

    <div class="business-info">
      <div class="business-avatar">A</div>
      <div class="business-details">
        <div class="business-name">Admin Panel</div>
        <div class="business-address">Administrator Dashboard</div>
      </div>
    </div>

    <div class="menu-section">
      <p class="menu-label">MENU</p>
      <nav class="nav-menu">
        <router-link
          to="/admin/dashboard"
          class="nav-item"
          active-class="active"
        >
          <span class="nav-icon">📊</span>
          <span>Dashboard</span>
        </router-link>
        <router-link
          to="/admin/vendor-requests"
          class="nav-item"
          active-class="active"
        >
          <span class="nav-icon">🏪</span>
          <span>Vendor Requests</span>
          <span v-if="notificationCounts.vendorRequests > 0" class="nav-badge">
            {{ notificationCounts.vendorRequests > 99 ? "99+" : notificationCounts.vendorRequests }}
          </span>
        </router-link>
        <router-link
          to="/admin/vendor-requests"
          class="nav-item"
          active-class="active"
        >
          <span class="nav-icon">👥</span>
          <span>Vendors</span>
        </router-link>
        <router-link
          to="/admin/product-approval"
          class="nav-item"
          active-class="active"
        >
          <span class="nav-icon">✅</span>
          <span>Product Approval</span>
          <span v-if="notificationCounts.productApproval > 0" class="nav-badge">
            {{ notificationCounts.productApproval > 99 ? "99+" : notificationCounts.productApproval }}
          </span>
        </router-link>
        <router-link to="/admin/reports" class="nav-item" active-class="active">
          <span class="nav-icon">📦</span>
          <span>Reported Products</span>
        </router-link>
      </nav>
    </div>

    <!-- <div class="menu-section">
      <p class="menu-label">REPORTS</p>
      <nav class="nav-menu">
        <router-link
          to="/admin/analytics"
          class="nav-item"
          active-class="active"
        >
          <span class="nav-icon">📈</span>
          <span>Analytics</span>
        </router-link>
        <router-link
          to="/admin/reporting"
          class="nav-item"
          active-class="active"
        >
          <span class="nav-icon">📋</span>
          <span>Reports</span>
        </router-link>
      </nav>
    </div> -->

    <div class="sidebar-footer">
      <button @click="handleLogout" class="logout-btn" :disabled="isLoading">
        <span class="logout-icon">🚪</span>
        <span>Logout</span>
        <span v-if="isLoading" class="logout-spinner"></span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import { useSidebarState } from "../../composables/useSidebarState";
import api from "../../plugins/axios";
import LoadingOverlay from "../components/LoadingOverlay.vue";

const { logout, user } = useAuth();
const router = useRouter();
const route = useRoute();
const { isMobileOpen, closeMobile } = useSidebarState();
const isLoading = ref(false);
const isLoadingMessage = ref("Loading...");
const notificationCounts = ref({
  vendorRequests: 0,
  productApproval: 0,
});
let notificationInterval = null;

const handleLogout = async () => {
  if (isLoading.value) return;

  isLoading.value = true;

  try {
    isLoadingMessage.value = "Logging out...";
    await new Promise((resolve) => setTimeout(resolve, 300));

    await logout();

    localStorage.removeItem("auth_token");
    localStorage.removeItem("user");
    localStorage.removeItem("admin_token");
    localStorage.removeItem("admin_data");

    sessionStorage.setItem("logout_initiated_admin", Date.now().toString());

    await new Promise((resolve) => setTimeout(resolve, 700));
  } catch (error) {
    console.error("Logout error:", error);
    isLoading.value = false;
  }
};

const checkLogoutState = () => {
  const logoutInitiated = sessionStorage.getItem("logout_initiated_admin");
  if (logoutInitiated) {
    const logoutTime = parseInt(logoutInitiated);
    const currentTime = Date.now();

    if (currentTime - logoutTime < 5000) {
      isLoading.value = true;

      setTimeout(() => {
        isLoading.value = false;
        sessionStorage.removeItem("logout_initiated_admin");
      }, 1000);
    } else {
      sessionStorage.removeItem("logout_initiated_admin");
    }
  }
};

const checkAuthState = () => {
  const token =
    localStorage.getItem("auth_token") || localStorage.getItem("admin_token");
  if (!token && isLoading.value) {
    setTimeout(() => {
      isLoading.value = false;
    }, 1000);
  }
};

const handleBeforeUnload = () => {
  if (isLoading.value) {
    sessionStorage.setItem("admin_logout_was_showing", "true");
  }
};

const handlePageLoad = () => {
  const logoutWasShowing = sessionStorage.getItem("admin_logout_was_showing");
  if (logoutWasShowing === "true") {
    isLoading.value = false;
    sessionStorage.removeItem("admin_logout_was_showing");
  }
};

const loadSidebarNotifications = async () => {
  try {
    const [vendorStatsResponse, productStatsResponse] = await Promise.all([
      api.get("/admin/vendor-applications/statistics"),
      api.get("/admin/products/statistics"),
    ]);

    notificationCounts.value.vendorRequests = Number(
      vendorStatsResponse.data?.pending ?? 0,
    );
    notificationCounts.value.productApproval = Number(
      productStatsResponse.data?.data?.draft_products ?? 0,
    );
  } catch (error) {
    console.error("Error loading admin sidebar notifications:", error);
  }
};

onMounted(() => {
  checkLogoutState();
  checkAuthState();
  handlePageLoad();
  loadSidebarNotifications();
  notificationInterval = window.setInterval(loadSidebarNotifications, 60000);

  window.addEventListener("beforeunload", handleBeforeUnload);
});

onUnmounted(() => {
  window.removeEventListener("beforeunload", handleBeforeUnload);
  if (notificationInterval) {
    window.clearInterval(notificationInterval);
  }
});

// Close sidebar on route change (mobile)
watch(
  () => route.path,
  () => {
    if (isMobileOpen.value) closeMobile();
  },
);
</script>

<style scoped>
* {
  font-family: "Poppins", sans-serif;
}

/* Sidebar Styles */
.sidebar {
  width: 240px;
  height: 100vh;
  background: #ffffff;
  border-right: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  position: fixed;
  left: 0;
  top: 0;
  overflow-y: auto;
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.logo-section {
  padding: 20px 24px;
  border-bottom: 1px solid #e2e8f0;
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;
}

.logo-icon {
  font-size: 24px;
}

.logo-text {
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
}

.business-info {
  padding: 16px 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid #e2e8f0;
}

.business-avatar {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #2d3748; /* Admin theme color */
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 14px;
}

.business-details {
  flex: 1;
}

.business-name {
  font-size: 13px;
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 2px;
}

.business-address {
  font-size: 11px;
  color: #718096;
}

.menu-section {
  margin: 20px 0;
}

.menu-label {
  font-size: 11px;
  font-weight: 600;
  color: #9ca3af;
  letter-spacing: 0.5px;
  padding: 0 24px 12px;
  margin: 0;
}

.nav-menu {
  display: flex;
  flex-direction: column;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 24px;
  color: #718096;
  text-decoration: none;
  font-size: 14px;
  transition: all 0.2s;
  cursor: pointer;
  margin-bottom: 2px;
}

.nav-badge {
  margin-left: auto;
  min-width: 22px;
  height: 22px;
  border-radius: 999px;
  background: #dc2626;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 7px;
}

.nav-item:hover {
  background: #f7fafc;
  color: #2d3748;
}

.nav-item.active {
  background: #2d3748;
  color: white;
  border-left: 4px solid #48bb78;
}

.nav-icon {
  font-size: 18px;
  width: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sidebar-footer {
  margin-top: auto;
  padding: 16px 24px 24px;
  border-top: 1px solid #e2e8f0;
}

.logout-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 12px 16px;
  background: transparent;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  color: #e53e3e;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  position: relative;
}

.logout-btn:hover:not(:disabled) {
  background: #fff5f5;
  border-color: #e53e3e;
  transform: translateY(-2px);
  box-shadow: 0 2px 8px rgba(229, 62, 62, 0.15);
}

.logout-btn:active:not(:disabled) {
  transform: translateY(0);
}

.logout-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.logout-icon {
  font-size: 18px;
}

.logout-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(229, 62, 62, 0.3);
  border-top: 2px solid #e53e3e;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-left: 8px;
}

/* Scrollbar styling */
.sidebar::-webkit-scrollbar {
  width: 4px;
}

.sidebar::-webkit-scrollbar-track {
  background: #f7fafc;
}

.sidebar::-webkit-scrollbar-thumb {
  background: #cbd5e0;
  border-radius: 2px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
  background: #a0aec0;
}

/* Responsive Styles */
@media (max-width: 1200px) {
  .sidebar {
    width: 200px;
  }
}

@media (max-width: 968px) {
  .sidebar {
    transform: translateX(-100%);
    width: 280px;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
  }

  .sidebar.mobile-open {
    transform: translateX(0) !important;
    z-index: 1000;
  }
}

.sidebar-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(2px);
  z-index: 999;
}
</style>
