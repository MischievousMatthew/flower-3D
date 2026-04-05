<template>
  <div class="hr-sidebar">
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
              d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"
            />
          </svg>
        </div>
        <div class="logo-text">
          <div class="company-name">HR PORTAL</div>
          <div class="branch-name">Management</div>
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
      <input type="text" placeholder="Search" v-model="searchQuery" />
      <span class="search-shortcut">⌘F</span>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
      <!-- Dashboard -->
      <router-link
        to="/erp/hr"
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

      <!-- Employee Management -->
      <div class="nav-group">
        <button
          class="nav-item expandable"
          :class="{
            active: expandedModules.includes('employees'),
            'is-parent-active': activeModule === 'employees',
          }"
          @click="toggleModule('employees')"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path
              d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"
            />
          </svg>
          <span>Employee Management</span>
          <svg
            class="chevron"
            :class="{ rotated: expandedModules.includes('employees') }"
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>
        <div class="sub-menu" v-show="expandedModules.includes('employees')">
          <router-link to="/erp/hr/employees/directory" class="sub-item"
            >Employee Directory</router-link
          >
          <router-link to="/erp/hr/employees/profiles" class="sub-item"
            >Employee Profiles</router-link
          >
        </div>
      </div>

      <!-- Attendance -->
      <div class="nav-group">
        <button
          class="nav-item expandable"
          :class="{
            active: expandedModules.includes('attendance'),
            'is-parent-active': activeModule === 'attendance',
          }"
          @click="toggleModule('attendance')"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path
              d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"
            />
          </svg>
          <span>Attendance</span>
          <svg
            class="chevron"
            :class="{ rotated: expandedModules.includes('attendance') }"
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>
        <div class="sub-menu" v-show="expandedModules.includes('attendance')">
          <router-link to="/erp/hr/attendance/logs" class="sub-item"
            >Attendance Logs</router-link
          >
          <router-link to="/erp/hr/attendance/qrscanner" class="sub-item"
            >QR Scanner</router-link
          >
        </div>
      </div>

      <!-- Payroll -->
      <div class="nav-group">
        <button
          class="nav-item expandable"
          :class="{
            active: expandedModules.includes('payroll'),
            'is-parent-active': activeModule === 'payroll',
          }"
          @click="toggleModule('payroll')"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 512 512"
          >
            <path
              fill="currentColor"
              d="M258 21.89c-.5 0-1.2 0-1.8.12c-4.6.85-10.1 5.1-13.7 14.81c-3.8 9.7-4.6 23.53-1.3 38.34c3.4 14.63 10.4 27.24 18.2 34.94c7.6 7.7 14.5 9.8 19.1 9c4.8-.7 10.1-5.1 13.7-14.7c3.8-9.64 4.8-23.66 1.4-38.35c-3.5-14.8-10.4-27.29-18.2-34.94c-6.6-6.8-12.7-9.22-17.4-9.22M373.4 151.4c-11 .3-24.9 3.2-38.4 8.9c-15.6 6.8-27.6 15.9-34.2 24.5c-6.6 8.3-7.2 14.6-5.1 18.3c2.2 3.7 8.3 7.2 20 7.7c11.7.7 27.5-2.2 43-8.8c15.5-6.7 27.7-15.9 34.3-24.3c6.6-8.3 7.1-14.8 5-18.5c-2.1-3.8-8.3-7.1-20-7.5c-1.6-.3-3-.3-4.6-.3m-136.3 92.9c-6.6.1-12.6.9-18 2.3c-11.8 3-18.6 8.4-20.8 14.9c-2.5 6.5 0 14.3 7.8 22.7c8.2 8.2 21.7 16.1 38.5 20.5c16.7 4.4 32.8 4.3 44.8 1.1c12.1-3.1 18.9-8.6 21.1-15c2.3-6.5 0-14.2-8.1-22.7c-7.9-8.2-21.4-16.1-38.2-20.4c-9.5-2.5-18.8-3.5-27.1-3.4m160.7 58.1L336 331.7c4.2.2 14.7.5 14.7.5l6.6 8.7l54.7-28.5zm-54.5.1l-57.4 27.2c5.5.3 18.5.5 23.7.8l49.8-23.6zm92.6 10.8l-70.5 37.4l14.5 18.7l74.5-44.6zm-278.8 9.1a40.3 40.3 0 0 0-9 1c-71.5 16.5-113.7 17.9-126.2 17.9H18v107.5s11.6-1.7 30.9-1.8c37.3 0 103 6.4 167 43.8c3.4 2.1 10.7 2.9 19.8 2.9c24.3 0 61.2-5.8 69.7-9C391 452.6 494 364.5 494 364.5l-32.5-28.4s-79.8 50.9-89.9 55.8c-91.1 44.7-164.9 16.8-164.9 16.8s119.9 3 158.4-27.3l-22.6-34s-82.8-2.3-112.3-6.2c-15.4-2-48.7-18.8-73.1-18.8"
            />
          </svg>
          <span>Payroll Management</span>
          <svg
            class="chevron"
            :class="{ rotated: expandedModules.includes('payroll') }"
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>
        <div class="sub-menu" v-show="expandedModules.includes('payroll')">
          <router-link to="/erp/hr/payroll/list" class="sub-item"
            >Payroll List</router-link
          >
          <router-link to="/erp/hr/payroll/create" class="sub-item"
            >Create Payroll</router-link
          >
        </div>
      </div>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
      <div class="user-profile">
        <div class="user-avatar">{{ userInitials }}</div>
        <div class="user-info">
          <div class="user-name">{{ userName }}</div>
          <div class="user-role">HR Department</div>
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
import { ref, computed, watch } from "vue";
import { useRoute } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import LoadingOverlay from "../components/LoadingOverlay.vue";
import RoleSwitcher from "../components/RoleSwitcher.vue";

const route = useRoute();
const { logout, user } = useAuth();

const searchQuery = ref("");
const isLoading = ref(false);
const isLoadingMessage = ref("Loading...");
const expandedModules = ref([]);

const isDashboardActive = computed(() => route.path === "/erp/hr");

const activeModule = computed(() => {
  const segments = route.path.split("/").filter(Boolean);
  if (segments.includes("employees")) return "employees";
  if (segments.includes("attendance")) return "attendance";
  if (segments.includes("payroll")) return "payroll";
  return null;
});

const userName = computed(() => {
  try {
    const u = JSON.parse(localStorage.getItem("user") ?? "{}");
    return u.name || u.username || "User";
  } catch {
    return "User";
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

// Auto-expand parent group when a child route is active
watch(
  () => route.path,
  (path) => {
    const seg = path.split("/").filter(Boolean);
    ["employees", "attendance", "payroll"].forEach((mod) => {
      if (seg.includes(mod) && !expandedModules.value.includes(mod)) {
        expandedModules.value.push(mod);
      }
    });
  },
  { immediate: true },
);

watch(
  activeModule,
  (mod) => {
    if (mod && !expandedModules.value.includes(mod))
      expandedModules.value.push(mod);
  },
  { immediate: true },
);

function toggleModule(module) {
  const idx = expandedModules.value.indexOf(module);
  const hasActiveChild = activeModule.value === module;
  if (idx > -1) {
    if (hasActiveChild) return;
    expandedModules.value.splice(idx, 1);
  } else {
    expandedModules.value = [module];
  }
}

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

.hr-sidebar {
  position: fixed;
  left: 0;
  top: 0;
  width: 260px;
  height: 100vh;
  background: #ffffff;
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  z-index: 100;
}
.hr-sidebar::-webkit-scrollbar {
  width: 6px;
}
.hr-sidebar::-webkit-scrollbar-track {
  background: transparent;
}
.hr-sidebar::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 3px;
}
.hr-sidebar::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

.sidebar-header {
  padding: 20px 16px;
  border-bottom: 1px solid #f3f4f6;
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
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
  color: #111827;
  letter-spacing: 0.5px;
}
.branch-name {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
}

.search-box {
  margin: 10px 12px 4px;
  padding: 8px 12px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}
.search-box:focus-within {
  background: #fff;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}
.search-box svg {
  color: #9ca3af;
  flex-shrink: 0;
}
.search-box input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  color: #111827;
}
.search-box input::placeholder {
  color: #9ca3af;
}
.search-shortcut {
  font-size: 11px;
  color: #9ca3af;
  padding: 2px 6px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  font-weight: 500;
}

.sidebar-nav {
  flex: 1;
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: none;
  background: transparent;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  width: 100%;
  position: relative;
}
.nav-item:hover {
  background: #f3f4f6;
  color: #111827;
}
.nav-item.active,
.nav-item.router-link-exact-active {
  background: #d1fae5 !important;
  color: #065f46 !important;
}
.nav-item.is-parent-active {
  background: #ecfdf5 !important;
  color: #059669 !important;
}
.nav-item.is-parent-active::before {
  content: "";
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 4px;
  height: 20px;
  background: #059669;
  border-radius: 0 2px 2px 0;
}
.nav-item svg {
  flex-shrink: 0;
}
.nav-item span {
  flex: 1;
}
.nav-item.expandable {
  justify-content: space-between;
}

.chevron {
  transition: transform 0.3s;
  flex-shrink: 0;
}
.chevron.rotated {
  transform: rotate(180deg);
}

.nav-group {
  display: flex;
  flex-direction: column;
}
.sub-menu {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-left: 42px;
  margin: 4px 0;
}
.sub-item {
  padding: 8px 12px;
  font-size: 13px;
  color: #6b7280;
  text-decoration: none;
  border-radius: 6px;
  transition: all 0.2s;
  font-weight: 500;
}
.sub-item:hover {
  background: #f3f4f6;
  color: #374151;
}
.sub-item.router-link-active,
.sub-item.router-link-exact-active {
  background: #d1fae5 !important;
  color: #065f46 !important;
  font-weight: 600;
}

.sidebar-footer {
  margin-top: auto;
  padding: 16px;
  border-top: 1px solid #f3f4f6;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.user-profile {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px;
  border-radius: 8px;
  transition: background-color 0.2s;
}
.user-profile:hover {
  background: #f9fafb;
}
.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.user-role {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: transparent;
  border: 1px solid #fca5a5;
  border-radius: 8px;
  color: #dc2626;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  width: 100%;
  font-family: "Poppins", sans-serif;
}
.logout-btn:hover {
  background: #fee2e2;
  border-color: #f87171;
}
.logout-btn:active {
  transform: translateY(1px);
}
.logout-btn svg {
  flex-shrink: 0;
}

@media (max-width: 768px) {
  .hr-sidebar {
    transform: translateX(-100%);
  }
}
</style>
