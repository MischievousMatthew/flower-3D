<template>
  <div class="sc-sidebar">
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
              d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"
            />
          </svg>
        </div>
        <div class="logo-text">
          <div class="company-name">SC PORTAL</div>
          <div class="branch-name">Supply Chain</div>
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
        to="/erp/procurement/supply-chain/dashboard"
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

      <!-- Suppliers -->
      <router-link
        to="/erp/procurement/supply-chain/suppliers"
        class="nav-item"
        active-class="active"
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
        <span>Suppliers</span>
      </router-link>

      <!-- Warehouse dropdown -->
      <div class="nav-group">
        <button
          class="nav-item expandable"
          :class="{
            active: expandedModules.includes('warehouse'),
            'is-parent-active': activeModule === 'warehouse',
          }"
          @click="toggleModule('warehouse')"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
          >
            <path
              fill="currentColor"
              stroke-width="0.5"
              stroke="currentColor"
              d="M22 19V8.35c0-.82-.5-1.55-1.26-1.86l-8-3.2c-.48-.19-1.01-.19-1.49 0l-8 3.2C2.5 6.8 2 7.54 2 8.35V19c0 1.1.9 2 2 2h3v-9h10v9h3c1.1 0 2-.9 2-2m-11 0H9v2h2zm2-3h-2v2h2zm2 3h-2v2h2z"
            />
          </svg>
          <span>Warehouse</span>
          <svg
            class="chevron"
            :class="{ rotated: expandedModules.includes('warehouse') }"
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
        <div class="sub-menu" v-show="expandedModules.includes('warehouse')">
          <router-link
            to="/erp/procurement/supply-chain/warehouse"
            class="sub-item"
            active-class=""
            exact-active-class="sub-item-active"
            ><span>Overview</span></router-link
          >
          <router-link
            to="/erp/procurement/supply-chain/warehouse/inventory"
            class="sub-item"
            ><span>Inventory</span></router-link
          >
          <router-link
            to="/erp/procurement/supply-chain/warehouse/floor"
            class="sub-item"
            ><span>Floor View</span></router-link
          >
          <router-link
            to="/erp/procurement/supply-chain/warehouse/batches-receive"
            class="sub-item"
            ><span>Receive Batches</span></router-link
          >
          <router-link
            to="/erp/procurement/supply-chain/warehouse/locations"
            class="sub-item"
            ><span>Locations</span></router-link
          >
        </div>
      </div>

      <!-- Orders -->
      <router-link
        to="/erp/procurement/supply-chain/orders"
        class="nav-item"
        active-class="active"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="currentColor"
        >
          <path
            d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"
          />
        </svg>
        <span>Orders</span>
      </router-link>

      <!-- Deliveries -->
      <router-link
        to="/erp/procurement/supply-chain/deliveries"
        class="nav-item"
        active-class="active"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
        >
          <path
            fill="currentColor"
            stroke-width="0.5"
            stroke="currentColor"
            d="M1.75 13.325q-.425 0-.712-.287t-.288-.713t.288-.712t.712-.288h3.5q.425 0 .713.288t.287.712t-.288.713t-.712.287zm3.125 5.8Q4 18.25 4 17H2.75q-.5 0-.8-.375t-.175-.85l.225-.95h3.125q1.05 0 1.775-.725t.725-1.775q0-.325-.075-.6t-.2-.55h.95q1.05 0 1.775-.725t.725-1.775t-.725-1.775T8.3 6.175H4.5l.15-.6q.15-.7.688-1.137T6.6 4h10.15q.5 0 .8.375t.175.85L17.075 8H19q.475 0 .9.213t.7.587l1.875 2.475q.275.35.35.763t0 .837L22.15 16.2q-.075.35-.35.575t-.625.225H20q0 1.25-.875 2.125T17 20t-2.125-.875T14 17h-4q0 1.25-.875 2.125T7 20t-2.125-.875M3.75 9.675q-.425 0-.712-.288t-.288-.712t.288-.712t.712-.288h4.5q.425 0 .713.288t.287.712t-.288.713t-.712.287zM7 18q.425 0 .713-.288T8 17t-.288-.712T7 16t-.712.288T6 17t.288.713T7 18m10 0q.425 0 .713-.288T18 17t-.288-.712T17 16t-.712.288T16 17t.288.713T17 18m-1.075-5h4.825l.1-.525L19 10h-2.375z"
          />
        </svg>
        <span>Deliveries</span>
      </router-link>

      <!-- Order Scan dropdown -->
      <div class="nav-group">
        <button
          class="nav-item expandable"
          :class="{
            active: expandedModules.includes('scan'),
            'is-parent-active': activeModule === 'scan',
          }"
          @click="toggleModule('scan')"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path
              d="M2 6h2v12H2zm3 0h1v12H5zm2 0h3v12H7zm4 0h1v12h-1zm3 0h2v12h-2zm3 0h1v12h-1zm2 0h2v12h-2zM0 4v16h24V4H0zm22 14H2V6h20v12z"
            />
          </svg>
          <span>Order Scan</span>
          <span class="badge" v-if="totalPending > 0">{{ totalPending }}</span>
          <svg
            class="chevron"
            :class="{ rotated: expandedModules.includes('scan') }"
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
        <div class="sub-menu" v-show="expandedModules.includes('scan')">
          <router-link
            to="/erp/procurement/supply-chain/scan/process"
            class="sub-item"
          >
            <span>To Process</span>
            <span v-if="counts.process" class="chip amber">{{
              counts.process
            }}</span>
          </router-link>
          <router-link
            to="/erp/procurement/supply-chain/scan/ship"
            class="sub-item"
          >
            <span>To Ship</span>
            <span v-if="counts.ship" class="chip blue">{{ counts.ship }}</span>
          </router-link>
          <router-link
            to="/erp/procurement/supply-chain/scan/receive"
            class="sub-item"
          >
            <span>To Receive</span>
            <span v-if="counts.receive" class="chip green">{{
              counts.receive
            }}</span>
          </router-link>
          <router-link
            to="/erp/procurement/supply-chain/scan/completed"
            class="sub-item"
          >
            <span>Completed</span>
          </router-link>
        </div>
      </div>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
      <div class="user-profile">
        <div class="user-avatar">{{ userInitials }}</div>
        <div class="user-info">
          <div class="user-name">{{ userName }}</div>
          <div class="user-role">Supply Chain</div>
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
const { logout } = useAuth();

const isLoading = ref(false);
const isLoadingMessage = ref("Loading...");
const searchQuery = ref("");
const expandedModules = ref([]);

const counts = ref({ process: 3, ship: 4, receive: 2 });
const totalPending = computed(
  () => counts.value.process + counts.value.ship + counts.value.receive,
);

const isDashboardActive = computed(
  () => route.path === "/erp/procurement/supply-chain/dashboard",
);

const activeModule = computed(() => {
  const p = route.path;
  if (p.includes("/scan")) return "scan";
  if (p.includes("/warehouse")) return "warehouse";
  return null;
});

watch(
  () => route.path,
  (path) => {
    if (path.includes("/scan") && !expandedModules.value.includes("scan"))
      expandedModules.value.push("scan");
    if (
      path.includes("/warehouse") &&
      !expandedModules.value.includes("warehouse")
    )
      expandedModules.value.push("warehouse");
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
    expandedModules.value.push(module);
  }
}

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

async function handleLogout() {
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
}
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.sc-sidebar {
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
.sc-sidebar::-webkit-scrollbar {
  width: 6px;
}
.sc-sidebar::-webkit-scrollbar-track {
  background: transparent;
}
.sc-sidebar::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 3px;
}
.sc-sidebar::-webkit-scrollbar-thumb:hover {
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
  font-family: inherit;
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

.badge {
  background: #10b981;
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 10px;
  line-height: 1.5;
  margin-left: auto;
  margin-right: 6px;
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
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  font-size: 13px;
  color: #6b7280;
  font-weight: 500;
  text-decoration: none;
  border-radius: 6px;
  transition: all 0.2s;
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
.sub-item-active {
  background: #d1fae5 !important;
  color: #065f46 !important;
  font-weight: 600;
}

.chip {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 8px;
  line-height: 1.5;
  flex-shrink: 0;
}
.chip.amber {
  background: #fef3c7;
  color: #92400e;
}
.chip.blue {
  background: #dbeafe;
  color: #1e40af;
}
.chip.green {
  background: #d1fae5;
  color: #065f46;
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
  transition: background 0.2s;
}
.user-profile:hover {
  background: #f9fafb;
}
.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
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
  font-family: inherit;
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
  .sc-sidebar {
    transform: translateX(-100%);
  }
}
</style>
