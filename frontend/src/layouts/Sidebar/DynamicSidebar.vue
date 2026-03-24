<template>
  <!-- Outer wrapper handles overflow:visible so the toggle button isn't clipped -->
  <div class="sidebar-outer">
    <!-- Toggle arrow — sits on the right edge of the outer wrapper -->
    <button
      class="collapse-toggle"
      :style="{ left: isCollapsed ? '54px' : '238px' }"
      @click="isCollapsed = !isCollapsed"
      :title="isCollapsed ? 'Expand' : 'Collapse'"
    >
      <svg
        width="13"
        height="13"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2.5"
        :style="{
          transform: isCollapsed ? 'rotate(180deg)' : 'rotate(0deg)',
          transition: 'transform 0.25s ease',
        }"
      >
        <polyline points="15 18 9 12 15 6" />
      </svg>
    </button>

    <!-- Inner sidebar — overflow:hidden so border stays crisp -->
    <div class="dynamic-sidebar" :class="{ collapsed: isCollapsed }">
      <LoadingOverlay :visible="isLoading" message="Logging out..." />

      <!-- ── User Header ──────────────────────────────── -->
      <div class="sidebar-header">
        <div class="user-avatar-wrap">
          <div class="user-avatar" :style="{ background: themeGradient }">
            {{ userInitials }}
          </div>
          <div class="avatar-badge" :style="{ background: themeGradient }">
            <svg
              width="7"
              height="7"
              viewBox="0 0 24 24"
              fill="none"
              stroke="white"
              stroke-width="3"
            >
              <path
                d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"
              />
            </svg>
          </div>
        </div>
        <div class="user-info" v-if="!isCollapsed">
          <div class="user-name" :title="userName">{{ userName }}</div>
          <div class="user-email" :title="userEmail">{{ userEmail }}</div>
        </div>
      </div>

      <!-- ── Search ──────────────────────────────────── -->
      <div class="search-wrap" v-if="!isCollapsed">
        <div class="search-box">
          <svg
            width="13"
            height="13"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input type="text" placeholder="Search" class="search-input" />
          <span class="search-kbd">K</span>
        </div>
      </div>
      <div class="collapsed-center" v-else>
        <button class="icon-btn" title="Search">
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
        </button>
      </div>

      <!-- ── Role Switcher ───────────────────────────── -->
      <div class="switcher-wrap" v-if="hasManyAssignments && !isCollapsed">
        <RoleSwitcher />
      </div>

      <!-- ── Nav ────────────────────────────────────── -->
      <nav class="sidebar-nav">
        <template
          v-for="item in currentConfig.nav"
          :key="item.path ?? item.label"
        >
          <router-link
            v-if="item.path"
            :to="item.path"
            class="nav-item"
            :exact-active-class="'active'"
            :title="isCollapsed ? item.label : undefined"
          >
            <span class="nav-icon" v-html="getIcon(item.icon)"></span>
            <span class="nav-label" v-if="!isCollapsed">{{ item.label }}</span>
          </router-link>

          <div v-else class="nav-group">
            <button
              class="nav-item expandable"
              :class="{ 'is-parent-active': isGroupActive(item) }"
              @click="!isCollapsed && toggleGroup(item.label)"
              :title="isCollapsed ? item.label : undefined"
            >
              <span class="nav-icon" v-html="getIcon(item.icon)"></span>
              <span class="nav-label" v-if="!isCollapsed">{{
                item.label
              }}</span>
              <svg
                v-if="!isCollapsed"
                class="chevron"
                :class="{ rotated: expandedGroups.includes(item.label) }"
                width="13"
                height="13"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
              >
                <polyline points="6 9 12 15 18 9" />
              </svg>
            </button>

            <div
              class="sub-menu"
              v-show="expandedGroups.includes(item.label) && !isCollapsed"
            >
              <span class="bracket-line"></span>
              <div class="sub-items">
                <router-link
                  v-for="child in item.children"
                  :key="child.path"
                  :to="child.path"
                  class="sub-item"
                  active-class="active"
                  >{{ child.label }}</router-link
                >
              </div>
            </div>
          </div>
        </template>
      </nav>

      <!-- ── Footer ─────────────────────────────────── -->
      <div class="sidebar-footer">
        <div class="footer-divider"></div>
        <button
          class="nav-item logout-item"
          @click="handleLogout"
          title="Logout"
        >
          <span class="nav-icon">
            <svg
              width="17"
              height="17"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.75"
            >
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16 17 21 12 16 7" />
              <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
          </span>
          <span class="nav-label" v-if="!isCollapsed">Logout</span>
        </button>
      </div>
    </div>
    <!-- end .dynamic-sidebar -->
  </div>
  <!-- end .sidebar-outer -->
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { useRoute } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import { useAssignment } from "../../composables/useAssignment";
import { useSidebarState } from "../../composables/useSidebarState";
import RoleSwitcher from "../components/RoleSwitcher.vue";
import LoadingOverlay from "../components/LoadingOverlay.vue";

const route = useRoute();
const { logout, user } = useAuth();
const { activeAssignment, activeRoleSlug, hasManyAssignments } =
  useAssignment();
const { isCollapsed } = useSidebarState();

const isLoading = ref(false);
const expandedGroups = ref([]);

// ── Icons ────────────────────────────────────────────────────────────────
const ICONS = {
  dashboard: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>`,
  employees: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`,
  attendance: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>`,
  payroll: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>`,
  leave: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`,
  funding: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>`,
  products: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>`,
  suppliers: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>`,
  warehouse: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>`,
  orders: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>`,
  deliveries: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>`,
  scan: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>`,
  default: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="4"/></svg>`,
};
function getIcon(key) {
  return ICONS[key] ?? ICONS.default;
}

// ── Nav configs ──────────────────────────────────────────────────────────
const NAV_CONFIGS = {
  "hr-manager": {
    title: "HR Portal",
    gradient: "linear-gradient(135deg, #48bb78, #38a169)",
    nav: [
      { label: "Dashboard", path: "/erp/hr", icon: "dashboard" },
      {
        label: "Employees",
        icon: "employees",
        children: [
          { label: "Directory", path: "/erp/hr/employees/directory" },
          { label: "Profiles", path: "/erp/hr/employees/profiles" },
        ],
      },
      {
        label: "Attendance",
        icon: "attendance",
        children: [
          { label: "Logs", path: "/erp/hr/attendance/logs" },
          { label: "QR Scanner", path: "/erp/hr/attendance/qrscanner" },
        ],
      },
      {
        label: "Payroll",
        icon: "payroll",
        children: [
          { label: "Payroll list", path: "/erp/hr/payroll/list" },
          { label: "Create payroll", path: "/erp/hr/payroll/create" },
        ],
      },
      {
        label: "Leave",
        icon: "leave",
        children: [
          {
            label: "Leave requests",
            path: "/erp/hr/leave/management-requests",
          },
          { label: "QR scanner", path: "/erp/hr/leave/qr-request" },
        ],
      },
    ],
  },
  "finance-manager": {
    title: "Finance Portal",
    gradient: "linear-gradient(135deg, #48bb78, #38a169)",
    nav: [
      { label: "Dashboard", path: "/erp/finance/dashboard", icon: "dashboard" },
      {
        label: "Funding requests",
        path: "/erp/finance/funding-requests",
        icon: "funding",
      },
      {
        label: "Payroll requests",
        path: "/erp/finance/payroll-requests",
        icon: "payroll",
      },
    ],
  },
  "inventory-manager": {
    title: "Inventory Portal",
    gradient: "linear-gradient(135deg, #48bb78, #38a169)",
    nav: [
      {
        label: "Products",
        path: "/erp/procurement/inventory/products",
        icon: "products",
      },
      {
        label: "Add Products",
        path: "/erp/procurement/inventory/add-product",
        icon: "add",
      },
      {
        label: "Funding Requests",
        path: "/erp/procurement/inventory/funding-request",
        icon: "funding",
      },
    ],
  },
  "supply-chain-coordinator": {
    title: "SC Portal",
    gradient: "linear-gradient(135deg, #48bb78, #38a169)",
    nav: [
      {
        label: "Dashboard",
        path: "/erp/procurement/supply-chain/dashboard",
        icon: "dashboard",
      },
      {
        label: "Suppliers",
        path: "/erp/procurement/supply-chain/suppliers",
        icon: "suppliers",
      },
      {
        label: "Warehouse",
        icon: "warehouse",
        children: [
          {
            label: "Overview",
            path: "/erp/procurement/supply-chain/warehouse",
          },
          {
            label: "Inventory",
            path: "/erp/procurement/supply-chain/warehouse/inventory",
          },
          {
            label: "Floor view",
            path: "/erp/procurement/supply-chain/warehouse/floor",
          },
          {
            label: "Receive batches",
            path: "/erp/procurement/supply-chain/warehouse/batches-receive",
          },
          {
            label: "Locations",
            path: "/erp/procurement/supply-chain/warehouse/locations",
          },
        ],
      },
      {
        label: "Orders",
        path: "/erp/procurement/supply-chain/orders",
        icon: "orders",
      },
      {
        label: "Deliveries",
        path: "/erp/procurement/supply-chain/deliveries",
        icon: "deliveries",
      },
      {
        label: "Order scan",
        icon: "scan",
        children: [
          {
            label: "To process",
            path: "/erp/procurement/supply-chain/scan/process",
          },
          { label: "To ship", path: "/erp/procurement/supply-chain/scan/ship" },
          {
            label: "To receive",
            path: "/erp/procurement/supply-chain/scan/receive",
          },
          // {
          //   label: "Completed",
          //   path: "/erp/procurement/supply-chain/scan/completed",
          // },
        ],
      },
    ],
  },
};

const DEFAULT_CONFIG = {
  title: "ERP",
  gradient: "linear-gradient(135deg, #48bb78, #38a169)",
  nav: [],
};

const currentConfig = computed(
  () => NAV_CONFIGS[activeRoleSlug.value] ?? DEFAULT_CONFIG,
);
const themeGradient = computed(() => currentConfig.value.gradient);

watch(activeRoleSlug, () => {
  expandedGroups.value = [];
});

watch(
  () => route.path,
  (path) => {
    for (const item of currentConfig.value.nav ?? []) {
      if (item.children?.some((c) => path.startsWith(c.path))) {
        if (!expandedGroups.value.includes(item.label))
          expandedGroups.value.push(item.label);
      }
    }
  },
  { immediate: true },
);

function isGroupActive(item) {
  return item.children?.some((c) => route.path.startsWith(c.path)) ?? false;
}
function toggleGroup(label) {
  const idx = expandedGroups.value.indexOf(label);
  idx > -1
    ? expandedGroups.value.splice(idx, 1)
    : expandedGroups.value.push(label);
}

const userName = computed(() => user.value?.name ?? "User");
const userEmail = computed(() => user.value?.email ?? "");
const userInitials = computed(() =>
  userName.value
    .split(" ")
    .map((w) => w[0])
    .join("")
    .toUpperCase()
    .slice(0, 2),
);

async function handleLogout() {
  isLoading.value = true;
  try {
    await logout();
  } finally {
    isLoading.value = false;
  }
}
</script>

<style scoped>
.sidebar-outer {
  position: relative;
  height: 100vh;
  width: fit-content;
  /* overflow visible so the toggle circle is never clipped */
  overflow: visible;
  flex-shrink: 0;
}

.collapse-toggle {
  /* Fixed positioning means it always floats above everything —
     no parent overflow, no z-index stacking context from layouts
     can block its click events */
  position: fixed;
  top: 22px;
  width: 24px;
  height: 24px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #718096;
  z-index: 9999;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
  transition:
    box-shadow 0.15s,
    color 0.15s,
    left 0.25s ease;
  pointer-events: all;
}
.collapse-toggle:hover {
  color: #2d3748;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.14);
}

/* ── Inner sidebar: overflow hidden keeps the right border clean ── */
.dynamic-sidebar {
  --primary: #48bb78;
  --primary-dk: #38a169;
  --secondary: #2d3748;
  --text-main: #2d3748;
  --text-muted: #718096;
  --text-light: #a0aec0;
  --border: #edf2f7;
  --hover-bg: #f7fafc;
  --active-bg: #48bb78;
  --active-txt: #ffffff;
  --r: 10px;

  width: 250px;
  min-width: 250px;
  height: 100vh;
  background: #fff;
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow: hidden; /* crisp border, no content leak */
  transition:
    width 0.25s ease,
    min-width 0.25s ease;
}

.dynamic-sidebar.collapsed {
  width: 66px;
  min-width: 66px;
}

/* ── Header ───────────────────────────────────── */
.sidebar-header {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 20px 14px 14px;
  flex-shrink: 0;
}
.dynamic-sidebar.collapsed .sidebar-header {
  justify-content: center;
  padding: 20px 0 14px;
}

.user-avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
}

.avatar-badge {
  position: absolute;
  bottom: -3px;
  right: -3px;
  width: 14px;
  height: 14px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1.5px solid #fff;
}

.user-info {
  flex: 1;
  min-width: 0;
  padding-top: 1px;
}

/* 2-line name: wrap instead of truncate */
.user-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-main);
  /* Allow up to 2 lines, then ellipsis */
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.35;
  word-break: break-word;
}

.user-email {
  font-size: 11.5px;
  color: var(--text-light);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-top: 2px;
}

/* ── Search ───────────────────────────────────── */
.search-wrap {
  padding: 0 12px 12px;
  flex-shrink: 0;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--hover-bg);
  border: 1px solid var(--border);
  border-radius: 7px;
  padding: 7px 10px;
}
.search-box svg {
  color: var(--text-light);
  flex-shrink: 0;
}

.search-input {
  flex: 1;
  border: none;
  background: none;
  outline: none;
  font-size: 12.5px;
  color: var(--text-main);
}
.search-input::placeholder {
  color: var(--text-light);
}

.search-kbd {
  font-size: 10px;
  font-weight: 700;
  color: var(--text-light);
  background: #edf2f7;
  padding: 2px 5px;
  border-radius: 4px;
  font-family: monospace;
}

.collapsed-center {
  display: flex;
  justify-content: center;
  padding: 0 0 12px;
}

.icon-btn {
  width: 36px;
  height: 36px;
  border-radius: var(--r);
  border: none;
  background: transparent;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition:
    background 0.15s,
    color 0.15s;
}
.icon-btn:hover {
  background: var(--hover-bg);
  color: var(--text-main);
}

/* ── Switcher ─────────────────────────────────── */
.switcher-wrap {
  padding: 0 12px 10px;
  flex-shrink: 0;
}

/* ── Nav ──────────────────────────────────────── */
.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  /* Horizontal padding creates the gap so border-radius on items is visible */
  padding: 4px 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.sidebar-nav::-webkit-scrollbar {
  width: 3px;
}
.sidebar-nav::-webkit-scrollbar-thumb {
  background: var(--border);
  border-radius: 3px;
}

.dynamic-sidebar.collapsed .sidebar-nav {
  padding: 4px 0;
  align-items: center;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: var(--r);
  color: var(--text-muted);
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  transition:
    background 0.15s,
    color 0.15s;
  background: transparent;
  border: none;
  /* Use box-sizing border-box + no explicit width so it fills the padded container */
  width: 100%;
  box-sizing: border-box;
  cursor: pointer;
  text-align: left;
  white-space: nowrap;
}

.dynamic-sidebar.collapsed .nav-item {
  width: 42px;
  height: 42px;
  padding: 0;
  justify-content: center;
}

.nav-item:hover {
  background: var(--hover-bg);
  color: var(--text-main);
}

/* Active: green background, white text + white icon */
.nav-item.active {
  background: var(--active-bg);
  color: var(--active-txt);
  font-weight: 600;
}
.nav-item.active .nav-icon svg {
  stroke: #fff;
}

.nav-item.is-parent-active {
  color: var(--text-main);
  font-weight: 600;
}

.nav-icon {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.nav-label {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
}

.chevron {
  flex-shrink: 0;
  opacity: 0.4;
  transition: transform 0.25s ease;
}
.chevron.rotated {
  transform: rotate(180deg);
}

/* ── Sub-menu with bracket ────────────────────── */
.sub-menu {
  display: flex;
  flex-direction: row;
  padding-left: 28px;
  margin: 2px 0 4px;
}

.bracket-line {
  width: 1.5px;
  background: #cbd5e0;
  border-radius: 2px;
  margin-right: 10px;
  flex-shrink: 0;
}

.sub-items {
  display: flex;
  flex-direction: column;
  flex: 1;
  gap: 1px;
}

.sub-item {
  display: block;
  padding: 7px 10px;
  color: var(--text-muted);
  text-decoration: none;
  font-size: 12.5px;
  font-weight: 400;
  border-radius: 6px;
  transition:
    background 0.15s,
    color 0.15s;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sub-item:hover {
  background: var(--hover-bg);
  color: var(--text-main);
}
.sub-item.active {
  color: var(--primary-dk);
  font-weight: 600;
  background: rgba(72, 187, 120, 0.1);
}

/* ── Footer ───────────────────────────────────── */
.sidebar-footer {
  flex-shrink: 0;
  padding: 0 12px 14px;
}

.footer-divider {
  height: 1px;
  background: var(--border);
  margin-bottom: 6px;
}

.logout-item {
  color: var(--text-muted);
}
.logout-item:hover {
  background: #fff5f5;
  color: #e53e3e;
}

.dynamic-sidebar.collapsed .logout-item {
  width: 42px;
  height: 42px;
  padding: 0;
  justify-content: center;
  margin: 0 auto;
}
</style>
