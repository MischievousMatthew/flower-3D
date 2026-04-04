<template>
  <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />
  
  <!-- Backdrop for mobile -->
  <div 
    v-if="isMobileOpen" 
    class="sidebar-backdrop" 
    @click="closeMobile"
  ></div>

  <aside class="sidebar" :class="{ 'mobile-open': isMobileOpen }">
    <div class="logo-section">
      <div class="logo">
        <span class="logo-icon">
          <img
            v-if="sidebarLogoUrl"
            :src="sidebarLogoUrl"
            :alt="`${businessName} logo`"
            class="logo-image"
            @error="handleLogoError"
          />
          <img
            v-else
            src="../../../public/bloomcraft-blankBg.png"
            alt="Bloomcraft Logo"
            class="logo-image"
          />
        </span>
        <span class="logo-text">{{ businessName }}</span>
      </div>
    </div>

    <div class="business-info">
      <div class="business-avatar">{{ businessInitial }}</div>
      <div class="business-details">
        <div class="business-name">{{ businessName }}</div>
        <div class="business-address">{{ businessAddress }}</div>
      </div>
    </div>

    <nav class="nav-menu">
      <router-link to="/vendor/products" class="nav-item" active-class="active">
        <span class="nav-icon">📊</span>
        <span>Dashboard</span>
      </router-link>

      <div class="nav-section">
        <div class="section-label">SHOP</div>
        <router-link
          to="/vendor/reservation"
          class="nav-item"
          active-class="active"
        >
          <span class="nav-icon">🛍️</span>
            <span>Orders</span>
            <span v-if="notificationCounts.orders > 0" class="nav-badge">
              {{ notificationCounts.orders > 99 ? "99+" : notificationCounts.orders }}
            </span>
          </router-link>
        <router-link
          to="/vendor/calendar"
          class="nav-item"
          active-class="active"
        >
          <span class="nav-icon">📅</span>
          <span>Calendar</span>
        </router-link>
        <!-- <router-link to="/customers" class="nav-item">
          <span class="nav-icon">👥</span>
          <span>Customers</span>
        </router-link>
        <router-link to="/arrangements" class="nav-item">
          <span class="nav-icon">💐</span>
          <span>Arrangements</span>
        </router-link> -->
        <router-link
          to="/vendor/staff-list"
          class="nav-item"
          active-class="active"
        >
          <span class="nav-icon">👨‍💼</span>
          <span>Staff List</span>
        </router-link>
      </div>

      <div class="nav-section">
        <div class="section-label">FINANCE</div>
        <router-link to="/vendor/finance-dashboard" class="nav-item" active-class="active">
          <span class="nav-icon">📈</span>
          <span>Finance Overview</span>
        </router-link>
      </div>

      <div class="nav-section">
        <div class="section-label">PHYSICAL ASSET</div>
        <router-link
          to="/vendor/products"
          class="nav-item"
          active-class="active"
        >
          <span class="nav-icon">📦</span>
          <span>Stocks</span>
        </router-link>
        <router-link to="/vendor/chat" class="nav-item" active-class="active">
          <span class="nav-icon">💬</span>
          <span>Chat</span>
        </router-link>
      </div>

      <!-- <router-link to="/report" class="nav-item">
        <span class="nav-icon">📋</span>
        <span>Report</span>
      </router-link>
      <router-link to="/support" class="nav-item">
        <span class="nav-icon">💬</span>
        <span>Customer Support</span>
      </router-link> -->
    </nav>

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
import { computed, ref, onMounted, onUnmounted, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import { useSidebarState } from "../../composables/useSidebarState";
import { useVendorProfile } from "../../composables/useVendorProfile";
import api from "../../plugins/axios";
import LoadingOverlay from "../components/LoadingOverlay.vue";

const { logout } = useAuth();
const router = useRouter();
const route = useRoute();
const { isMobileOpen, closeMobile } = useSidebarState();
const { vendorProfile, fetchProfile } = useVendorProfile({ autoFetch: false, showToast: false });

const isLoading = ref(null);
const isLoadingMessage = ref("");
const logoLoadFailed = ref(false);
const notificationCounts = ref({
  orders: 0,
});
let notificationInterval = null;

const businessName = computed(() => {
  return (
    vendorProfile.value?.store_name?.trim() ||
    vendorProfile.value?.owner_name?.trim() ||
    "Vendor Store"
  );
});

const businessAddress = computed(() => {
  return (
    vendorProfile.value?.store_address?.trim() ||
    vendorProfile.value?.service_areas?.trim() ||
    "No store address set"
  );
});

const businessInitial = computed(() => {
  const source = businessName.value.trim();
  if (!source) return "V";

  const words = source.split(/\s+/).filter(Boolean);
  if (words.length >= 2) {
    return `${words[0][0]}${words[1][0]}`.toUpperCase();
  }

  return source.slice(0, 1).toUpperCase();
});

const sidebarLogoUrl = computed(() => {
  if (logoLoadFailed.value) return "";
  return vendorProfile.value?.store_logo_url?.trim() || "";
});

isLoading.value = false;

const handleLogoError = () => {
  logoLoadFailed.value = true;
};

const handleLogout = async () => {
  if (isLoading.value) return;

  isLoading.value = true;
  isLoadingMessage.value = "Logging out...";

  try {
    await logout();
  } catch (error) {
    console.error("Logout error:", error);
  } finally {
    isLoading.value = false;
  }
};

const checkLogoutState = () => {
  const logoutInitiated = sessionStorage.getItem("logout_initiated_vendor");
  if (logoutInitiated) {
    const logoutTime = parseInt(logoutInitiated);
    const currentTime = Date.now();

    if (currentTime - logoutTime < 5000) {
      isLoading.value = true;

      setTimeout(() => {
        isLoading.value = false;
        sessionStorage.removeItem("logout_initiated_vendor");
      }, 1000);
    } else {
      sessionStorage.removeItem("logout_initiated_vendor");
    }
  }
};

const checkAuthState = () => {
  const token =
    localStorage.getItem("auth_token") || localStorage.getItem("vendor_token");
  if (!token && isLoading.value) {
    setTimeout(() => {
      isLoading.value = false;
    }, 1000);
  }
};

const handleBeforeUnload = () => {
  if (isLoading.value) {
    sessionStorage.setItem("vendor_logout_was_showing", "true");
  }
};

const handlePageLoad = () => {
  const logoutWasShowing = sessionStorage.getItem("vendor_logout_was_showing");
  if (logoutWasShowing === "true") {
    isLoading.value = false;
    sessionStorage.removeItem("vendor_logout_was_showing");
  }
};

const loadSidebarNotifications = async () => {
  try {
    const response = await api.get("/vendor/orders/statistics");
    const statusBreakdown = response.data?.data?.status_breakdown ?? {};

    notificationCounts.value.orders =
      Number(statusBreakdown.pending ?? 0) +
      Number(statusBreakdown.processing ?? 0);
  } catch (error) {
    console.error("Error loading vendor sidebar notifications:", error);
  }
};

onMounted(() => {
  checkLogoutState();
  checkAuthState();
  handlePageLoad();
  fetchProfile().catch(() => {});
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
watch(() => route.path, () => {
  if (isMobileOpen.value) closeMobile();
});

watch(
  () => vendorProfile.value?.store_logo_url,
  () => {
    logoLoadFailed.value = false;
  },
);
</script>

<style scoped>
.sidebar {
  position: fixed;
  width: 260px;
  height: 100vh;
  background: #ffffff;
  border-right: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  left: 0;
  top: 0;
  overflow-y: auto;
  z-index: 300;
}

.logo-section {
  padding: 20px 24px;
  border-bottom: 1px solid #e2e8f0;
  height: 43px;
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;
}

.logo-icon {
  font-size: 24px;
}

.logo-image {
  width: 50px;
  height: 50px;
  object-fit: cover;
  border-radius: 14px;
  display: block;
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
  background: #d1fae5;
  color: #065f46;
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

.nav-menu {
  flex: 1;
  padding: 8px 16px;
}

.nav-section {
  margin-bottom: 20px;
}

.section-label {
  font-size: 11px;
  font-weight: 600;
  color: #9ca3af;
  letter-spacing: 0.5px;
  padding: 12px 12px 8px;
  margin-bottom: 4px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  margin-bottom: 2px;
  border-radius: 8px;
  color: #718096;
  text-decoration: none;
  font-size: 14px;
  transition: all 0.2s;
  cursor: pointer;
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
  background: #48bb78;
  color: white;
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

@media (max-width: 1200px) {
  .sidebar {
    width: 200px;
  }
}

@media (max-width: 968px) {
  .sidebar {
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    width: 280px;
  }

  .sidebar.mobile-open {
    transform: translateX(0) !important;
    z-index: 1000;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
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

/* Animation for mobile menu */
.sidebar {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
</style>
