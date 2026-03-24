<template>
  <div class="finance-sidebar">
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
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
          </svg>
        </div>
        <div class="logo-text">
          <div class="company-name">FINNESE</div>
          <div class="branch-name">Finance Portal</div>
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
      <input type="text" placeholder="Search Finnese" v-model="searchQuery" />
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
      <router-link
        to="/erp/finance/dashboard"
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
        to="/erp/finance/funding-requests"
        class="nav-item"
        :class="{ active: isFundingRequestActive }"
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
        <span>Funding Request</span>
      </router-link>

      <router-link
        to="/erp/finance/payroll-requests"
        class="nav-item"
        :class="{ active: isPayrollRequestActive }"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="currentColor"
        >
          <path
            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1 1.05.82 1.87 2.65 1.87 1.96 0 2.4-.98 2.4-1.59 0-.83-.44-1.61-2.67-2.14-2.48-.6-4.18-1.62-4.18-3.67 0-1.72 1.39-2.84 3.11-3.21V4h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.87-2.22-1.87-1.5 0-2.4.68-2.4 1.64 0 .84.65 1.39 2.67 1.91s4.18 1.39 4.18 3.91c-.01 1.83-1.38 2.83-3.12 3.16z"
          />
        </svg>
        <span>Payroll Requests</span>
      </router-link>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
      <div class="user-profile">
        <div class="user-avatar">{{ userInitials }}</div>
        <div class="user-info">
          <div class="user-name">{{ userName }}</div>
          <div class="user-role">Finance Department</div>
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
  () => route.path === "/erp/finance/dashboard",
);
const isFundingRequestActive = computed(() =>
  route.path.startsWith("/erp/finance/funding-requests"),
);
const isPayrollRequestActive = computed(() =>
  route.path.startsWith("/erp/finance/payroll-requests"),
);

const userName = computed(() => {
  try {
    const u = JSON.parse(localStorage.getItem("user") ?? "{}");
    return u.name || u.username || "Finance Manager";
  } catch {
    return "Finance Manager";
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

.finance-sidebar {
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
.finance-sidebar::-webkit-scrollbar {
  width: 6px;
}
.finance-sidebar::-webkit-scrollbar-track {
  background: transparent;
}
.finance-sidebar::-webkit-scrollbar-thumb {
  background: #cbd5e0;
  border-radius: 3px;
}
.finance-sidebar::-webkit-scrollbar-thumb:hover {
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
  background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
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
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
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
  background: #f7fafc;
  color: #1a202c;
}
.nav-item.active,
.nav-item.router-link-exact-active {
  background: #c6f6d5 !important;
  color: #22543d !important;
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
  background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
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
  .finance-sidebar {
    transform: translateX(-100%);
  }
}
</style>
