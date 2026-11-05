<template>
  <div v-if="isMobileOpen" class="sidebar-backdrop" data-close-guard="off" @click="closeMobile"></div>

  <aside class="sidebar" :class="{ 'mobile-open': isMobileOpen }" aria-label="Admin navigation">
    <div class="brand">
      <img src="/bloomcraft-blankBg.png" alt="" width="36" height="36" class="brand-logo" />
      <div class="brand-text">
        <span class="brand-name">BloomCraft</span>
        <span class="brand-tag">Admin console</span>
      </div>
    </div>

    <nav class="nav">
      <template v-if="!adminAccess.access.loaded">
        <div v-for="n in 3" :key="n" class="nav-group">
          <span class="sk sk-label"></span>
          <span v-for="m in 3" :key="m" class="sk sk-item"></span>
        </div>
      </template>

      <template v-else>
        <div v-for="group in visibleGroups" :key="group.label" class="nav-group">
          <p class="nav-label">{{ group.label }}</p>
          <router-link
            v-for="item in group.items"
            :key="item.path"
            :to="item.path"
            class="nav-item"
            active-class="active"
          >
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="ICONS[item.icon]"></svg>
            </span>
            <span class="nav-text">{{ item.label }}</span>
            <span v-if="badge(item)" class="nav-badge" :title="item.badgeTitle">{{ badge(item) > 99 ? "99+" : badge(item) }}</span>
          </router-link>
        </div>
      </template>
    </nav>

    <!-- Appearance: light / dark and the theme color, saved in this browser -->
    <Transition name="ap-pop">
      <section v-if="appearanceOpen" id="admin-appearance" ref="appearanceEl" class="ap" role="dialog" aria-label="Appearance" @keydown.esc.stop="closeAppearance(true)">
        <h3 class="ap-title">Appearance</h3>

        <p class="ap-label" id="ap-mode-label">Mode</p>
        <div class="ap-seg" role="radiogroup" aria-labelledby="ap-mode-label">
          <button
            v-for="m in MODES"
            :key="m.value"
            type="button"
            role="radio"
            :aria-checked="theme.mode.value === m.value"
            :class="{ on: theme.mode.value === m.value }"
            @click="theme.setMode(m.value)"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="m.icon"></svg>
            {{ m.label }}
          </button>
        </div>

        <p class="ap-label" id="ap-color-label">Theme color</p>
        <div class="ap-swatches" role="radiogroup" aria-labelledby="ap-color-label">
          <button
            v-for="p in ADMIN_ACCENT_PRESETS"
            :key="p.value"
            type="button"
            role="radio"
            class="ap-swatch"
            :style="{ '--sw': p.value }"
            :aria-checked="theme.accent.value === p.value"
            :aria-label="p.name"
            :title="p.name"
            @click="theme.setAccent(p.value)"
          >
            <svg v-if="theme.accent.value === p.value" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
          </button>
          <!-- Any color -->
          <label class="ap-swatch ap-custom" :class="{ on: isCustomAccent }" :style="isCustomAccent ? { '--sw': theme.accent.value } : null" title="Pick any color">
            <input type="color" :value="theme.accent.value" aria-label="Pick any theme color" @input="theme.setAccent($event.target.value)" />
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
          </label>
        </div>

        <div class="ap-foot">
          <span class="ap-preview" aria-hidden="true"><i></i>Buttons and highlights</span>
          <button v-if="theme.accent.value !== DEFAULT_ADMIN_ACCENT" type="button" class="ap-reset" @click="theme.resetAccent()">Reset color</button>
        </div>
      </section>
    </Transition>

    <div class="profile">
      <span class="profile-avatar">{{ initials }}</span>
      <div class="profile-text">
        <span class="profile-name">{{ adminAccess.access.name || "Administrator" }}</span>
        <span class="profile-role">{{ adminAccess.access.role || "Admin" }}</span>
      </div>
      <button
        ref="appearanceBtn"
        type="button"
        class="logout-btn ap-toggle"
        :class="{ on: appearanceOpen }"
        title="Appearance"
        aria-label="Appearance: theme color and dark mode"
        :aria-expanded="appearanceOpen"
        aria-controls="admin-appearance"
        @click="appearanceOpen ? closeAppearance() : (appearanceOpen = true)"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="ICONS.palette"></svg>
      </button>
      <button type="button" class="logout-btn" :disabled="signingOut" :title="signingOut ? 'Signing out…' : 'Sign out'" :aria-label="signingOut ? 'Signing out' : 'Sign out'" @click="handleLogout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" v-html="ICONS.logout"></svg>
      </button>
    </div>
    <p v-if="signingOut" class="signing-out" role="status">Signing out…</p>
  </aside>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import { useSidebarState } from "../../composables/useSidebarState";
import { useAdminAccess } from "../../composables/useAdminAccess";
import { ADMIN_ACCENT_PRESETS, DEFAULT_ADMIN_ACCENT, useAdminTheme } from "../../composables/useAdminTheme";
import api from "../../plugins/axios";

// Lucide-style outline icons (24×24, stroke)
const ICONS = {
  dashboard: '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
  orders: '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
  products: '<circle cx="12" cy="12" r="3"/><path d="M12 16.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 1 1 4.5 4.5 4.5 4.5 0 1 1-4.5 4.5"/><path d="M12 7.5V9"/><path d="M7.5 12H9"/><path d="M16.5 12H15"/><path d="M12 16.5V15"/>',
  categories: '<path d="M9 5H2v7l6.29 6.29c.94.94 2.48.94 3.42 0l3.58-3.58c.94-.94.94-2.48 0-3.42L9 5Z"/><path d="M6 9.01V9"/><path d="m15 5 6.3 6.3a2.4 2.4 0 0 1 0 3.4L17 19"/>',
  promotions: '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
  reviews: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="m12 6.5 1.1 2.2 2.4.35-1.75 1.7.42 2.4L12 12l-2.17 1.15.42-2.4-1.75-1.7 2.4-.35z"/>',
  transactions: '<path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
  refunds: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 8v8"/><path d="M14.5 9.5h-3.25a1.75 1.75 0 0 0 0 3.5h1.5a1.75 1.75 0 0 1 0 3.5H9.5"/>',
  subscriptions: '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/><path d="M6 15h4"/>',
  analytics: '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
  vendors: '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2 2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"/>',
  customers: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  support: '<path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm0 0a9 9 0 1 1 18 0m0 0v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Z"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/>',
  audit: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
  admins: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
  settings: '<line x1="4" x2="4" y1="21" y2="14"/><line x1="4" x2="4" y1="10" y2="3"/><line x1="12" x2="12" y1="21" y2="12"/><line x1="12" x2="12" y1="8" y2="3"/><line x1="20" x2="20" y1="21" y2="16"/><line x1="20" x2="20" y1="12" y2="3"/><line x1="2" x2="6" y1="14" y2="14"/><line x1="10" x2="14" y1="8" y2="8"/><line x1="18" x2="22" y1="16" y2="16"/>',
  health: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
  logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
  palette: '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.65-.75 1.65-1.69 0-.44-.18-.84-.44-1.13-.29-.29-.44-.65-.44-1.13a1.64 1.64 0 0 1 1.67-1.67h2c3.05 0 5.56-2.5 5.56-5.55C21.97 6.01 17.46 2 12 2z"/>',
};

// ── Appearance ────────────────────────────────────────────────────────
const MODES = [
  { value: "light", label: "Light", icon: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>' },
  { value: "dark", label: "Dark", icon: '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>' },
  { value: "system", label: "Device", icon: '<rect width="20" height="14" x="2" y="3" rx="2"/><path d="M8 21h8M12 17v4"/>' },
];
const theme = useAdminTheme();
const isCustomAccent = computed(() => !ADMIN_ACCENT_PRESETS.some((p) => p.value === theme.accent.value));
const appearanceOpen = ref(false);
const appearanceEl = ref(null);
const appearanceBtn = ref(null);
function closeAppearance(focusButton = false) {
  appearanceOpen.value = false;
  if (focusButton) appearanceBtn.value?.focus();
}
// A click anywhere else closes it
function onDocPointer(event) {
  if (!appearanceOpen.value) return;
  if (appearanceEl.value?.contains(event.target) || appearanceBtn.value?.contains(event.target)) return;
  closeAppearance();
}
onMounted(() => document.addEventListener("pointerdown", onDocPointer));
onUnmounted(() => document.removeEventListener("pointerdown", onDocPointer));

const GROUPS = [
  {
    label: "Overview",
    items: [{ label: "Dashboard", path: "/admin/dashboard", icon: "dashboard" }],
  },
  {
    label: "Marketplace",
    items: [
      { label: "Order Management", path: "/admin/orders", icon: "orders" },
      { label: "Product Management", path: "/admin/product-approval", icon: "products" },
      { label: "Category Management", path: "/admin/categories", icon: "categories" },
      { label: "Promotion Management", path: "/admin/website", icon: "promotions" },
      { label: "Review Management", path: "/admin/reviews", icon: "reviews", badgeKey: "reports", badgeTitle: "Reported listings waiting for review" },
    ],
  },
  {
    label: "Finance",
    items: [
      { label: "Transaction Management", path: "/admin/finance", icon: "transactions" },
      { label: "Refund Management", path: "/admin/refunds", icon: "refunds" },
      { label: "Subscription Management", path: "/admin/subscription-revenue", icon: "subscriptions" },
      { label: "Reports & Analytics", path: "/admin/analytics", icon: "analytics" },
    ],
  },
  {
    label: "People & Operations",
    items: [
      { label: "Vendor Management", path: "/admin/vendor-requests", icon: "vendors", badgeKey: "vendors", badgeTitle: "Vendor applications waiting for review" },
      { label: "Customer Management", path: "/admin/customers", icon: "customers" },
      { label: "Support Tickets", path: "/admin/support", icon: "support" },
      { label: "Audit Logs", path: "/admin/audit-logs", icon: "audit" },
      { label: "Admin Users & Permissions", path: "/admin/security", icon: "admins" },
      { label: "System Settings", path: "/admin/settings", icon: "settings" },
      { label: "System Health", path: "/admin/health", icon: "health" },
    ],
  },
];

const { logout } = useAuth();
const adminAccess = useAdminAccess();
const route = useRoute();
const { isMobileOpen, closeMobile } = useSidebarState();

adminAccess.load().catch(() => {});

const canSee = (path) => {
  const permission = adminAccess.permissionForPath(path);
  return !permission || adminAccess.can(permission);
};
const visibleGroups = computed(() =>
  GROUPS.map((g) => ({ ...g, items: g.items.filter((i) => canSee(i.path)) })).filter((g) => g.items.length),
);
const initials = computed(() =>
  (adminAccess.access.name || "A")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join(""),
);

// ── Badges ───────────────────────────────────────────────────────
const counts = reactive({ vendors: 0, reports: 0 });
const badge = (item) => (item.badgeKey ? counts[item.badgeKey] : 0);
let timer = null;
const loadCounts = async () => {
  if (!adminAccess.access.loaded) return;
  const jobs = [];
  if (canSee("/admin/vendor-requests")) {
    jobs.push(api.get("/admin/vendor-applications/statistics").then(({ data }) => (counts.vendors = Number(data?.pending ?? 0))));
  }
  if (canSee("/admin/reviews")) {
    jobs.push(api.get("/admin/reviews/summary").then(({ data }) => (counts.reports = Number(data?.data?.pending_reports ?? 0))));
  }
  await Promise.allSettled(jobs);
};
watch(() => adminAccess.access.loaded, (loaded) => loaded && loadCounts(), { immediate: true });

// ── Sign out ─────────────────────────────────────────────────────
const signingOut = ref(false);
const handleLogout = async () => {
  if (signingOut.value) return;
  signingOut.value = true;
  try {
    await logout();
    ["auth_token", "user", "admin_token", "admin_data"].forEach((key) => localStorage.removeItem(key));
  } catch (error) {
    console.error("Logout error:", error);
    signingOut.value = false;
  }
};

onMounted(() => {
  timer = window.setInterval(loadCounts, 60000);
});
onUnmounted(() => window.clearInterval(timer));

watch(
  () => route.path,
  () => {
    if (isMobileOpen.value) closeMobile();
  },
);
</script>

<style scoped>
* {
  font-family: "Poppins", "Segoe UI", sans-serif;
  box-sizing: border-box;
}

.sidebar {
  width: 240px;
  height: 100vh;
  position: fixed;
  left: 0;
  top: 0;
  z-index: 200;
  display: flex;
  flex-direction: column;
  background: var(--a-surface);
  border-right: 1px solid var(--a-border);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Brand */
.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 18px 18px 14px;
}
.brand-logo {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  object-fit: contain;
  background: var(--a-accent-soft);
  padding: 3px;
}
.brand-text {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
}
.brand-name {
  font-size: 15.5px;
  font-weight: 700;
  color: var(--a-text);
  letter-spacing: -0.2px;
}
.brand-tag {
  font-size: 11px;
  font-weight: 500;
  color: var(--a-accent-text);
}

/* Navigation */
.nav {
  flex: 1;
  overflow-y: auto;
  padding: 6px 12px 12px;
  scrollbar-width: thin;
  scrollbar-color: var(--a-border) transparent;
}
.nav-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-top: 14px;
}
.nav-group:first-child {
  margin-top: 4px;
}
.nav-label {
  margin: 0 0 4px;
  padding: 0 10px;
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  color: var(--a-faint);
}
.nav-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 10px;
  color: var(--a-muted);
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
}
.nav-item:hover {
  background: var(--a-accent-soft);
  color: var(--a-text);
}
.nav-item:focus-visible {
  outline: 2px solid var(--a-accent);
  outline-offset: 1px;
}
.nav-icon {
  width: 30px;
  height: 30px;
  flex-shrink: 0;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--a-subtle);
  color: var(--a-muted);
  transition: background 0.15s, color 0.15s;
}
.nav-icon svg {
  width: 17px;
  height: 17px;
}
.nav-item:hover .nav-icon {
  background: var(--a-accent-soft-2);
  color: var(--a-accent-text);
}
.nav-text {
  flex: 1;
  min-width: 0;
  line-height: 1.25;
}
.nav-item.active {
  background: linear-gradient(135deg, var(--a-accent-soft), var(--a-accent-soft));
  color: var(--a-accent-text);
  font-weight: 600;
}
.nav-item.active::before {
  content: "";
  position: absolute;
  left: -12px;
  top: 8px;
  bottom: 8px;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: var(--a-accent);
}
.nav-item.active .nav-icon {
  background: linear-gradient(135deg, var(--a-accent-light), var(--a-accent-hover));
  color: var(--a-accent-ink);
  box-shadow: 0 4px 10px rgba(109, 95, 240, 0.28);
}
.nav-badge {
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: #f43f5e;
  color: #fff;
  font-size: 10.5px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* Skeleton while permissions load */
.sk {
  display: block;
  border-radius: 8px;
  background: linear-gradient(100deg, var(--a-subtle-2) 30%, var(--a-subtle) 50%, var(--a-subtle-2) 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
.sk-label {
  width: 40%;
  height: 9px;
  margin: 6px 10px 8px;
}
.sk-item {
  height: 34px;
  margin-bottom: 4px;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

/* Profile */
.profile {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 8px 12px 14px;
  padding: 10px;
  border: 1px solid var(--a-border);
  border-radius: 14px;
  background: var(--a-subtle);
}
.profile-avatar {
  width: 34px;
  height: 34px;
  flex-shrink: 0;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--a-accent-light), var(--a-accent-deep));
  color: var(--a-accent-ink);
  font-size: 12.5px;
  font-weight: 600;
}
.profile-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}
.profile-name,
.profile-role {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.profile-name {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--a-text);
}
.profile-role {
  font-size: 11px;
  color: var(--a-muted);
}
.logout-btn {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  border: 1px solid transparent;
  border-radius: 9px;
  background: transparent;
  color: var(--a-faint);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s;
}
.logout-btn svg {
  width: 17px;
  height: 17px;
}
.logout-btn:hover:not(:disabled) {
  color: var(--a-red-text);
  background: var(--a-red-soft);
  border-color: var(--a-red-border);
}
.logout-btn:disabled {
  opacity: 0.5;
  cursor: default;
}
.logout-btn:focus-visible {
  outline: 2px solid var(--a-accent);
  outline-offset: 2px;
}
.ap-toggle:hover:not(:disabled),
.ap-toggle.on {
  color: var(--a-accent-text);
  background: var(--a-accent-soft);
  border-color: var(--a-accent-muted);
}

/* Appearance panel, just above the profile */
.ap {
  position: absolute;
  left: 12px;
  right: 12px;
  bottom: 78px;
  z-index: 5;
  padding: 14px;
  border: 1px solid var(--a-border);
  border-radius: 14px;
  background: var(--a-surface);
  box-shadow: 0 18px 40px -16px rgba(15, 23, 42, 0.35);
}
.ap-title {
  margin: 0 0 10px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--a-text);
}
.ap-label {
  margin: 10px 0 6px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--a-faint);
}
.ap-seg {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 3px;
  padding: 3px;
  border-radius: 10px;
  background: var(--a-subtle-2);
}
.ap-seg button {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  min-height: 44px;
  padding: 6px 4px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  font: inherit;
  font-size: 11.5px;
  font-weight: 500;
  color: var(--a-muted);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.ap-seg button svg {
  width: 16px;
  height: 16px;
}
.ap-seg button:hover {
  color: var(--a-text);
}
.ap-seg button.on {
  background: var(--a-surface);
  color: var(--a-accent-text);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
}
.ap-seg button:focus-visible,
.ap-swatch:focus-visible,
.ap-custom:has(input:focus-visible),
.ap-reset:focus-visible {
  outline: 2px solid var(--a-accent);
  outline-offset: 2px;
}
.ap-swatches {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 8px;
}
.ap-swatch {
  position: relative;
  display: grid;
  place-items: center;
  width: 100%;
  aspect-ratio: 1;
  min-height: 32px;
  border: 2px solid transparent;
  border-radius: 50%;
  background: var(--sw);
  color: #fff;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08);
  transition: transform 0.15s;
}
.ap-swatch:hover {
  transform: scale(1.08);
}
.ap-swatch[aria-checked="true"],
.ap-custom.on {
  border-color: var(--a-surface);
  box-shadow: 0 0 0 2px var(--sw);
}
.ap-swatch svg {
  width: 14px;
  height: 14px;
}
.ap-custom {
  background: conic-gradient(#f43f5e, #f59e0b, #22c55e, #0ea5e9, #8b5cf6, #f43f5e);
  overflow: hidden;
}
.ap-custom.on {
  background: var(--sw);
}
.ap-custom input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}
.ap-custom svg {
  pointer-events: none;
  filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.35));
}
.ap-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 12px;
}
.ap-preview {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: var(--a-muted);
}
.ap-preview i {
  width: 22px;
  height: 10px;
  border-radius: 999px;
  background: var(--a-accent);
}
.ap-reset {
  min-height: 32px;
  padding: 0 8px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  font: inherit;
  font-size: 12px;
  font-weight: 500;
  color: var(--a-accent-text);
  cursor: pointer;
}
.ap-reset:hover {
  background: var(--a-accent-soft);
}
.ap-pop-enter-active,
.ap-pop-leave-active {
  transition: opacity 0.16s ease, transform 0.16s ease;
}
.ap-pop-enter-from,
.ap-pop-leave-to {
  opacity: 0;
  transform: translateY(6px);
}
@media (prefers-reduced-motion: reduce) {
  .ap-pop-enter-active,
  .ap-pop-leave-active,
  .ap-swatch {
    transition: none;
  }
}
.signing-out {
  margin: -8px 14px 12px;
  font-size: 11.5px;
  color: var(--a-muted);
  text-align: center;
}

@media (max-width: 1200px) {
  .sidebar {
    width: 200px;
  }
  .nav-text {
    font-size: 12.5px;
  }
}
@media (max-width: 968px) {
  .sidebar {
    width: 280px;
    transform: translateX(-100%);
    box-shadow: 4px 0 24px rgba(15, 23, 42, 0.12);
  }
  .sidebar.mobile-open {
    transform: translateX(0) !important;
    z-index: 1000;
  }
}
.sidebar-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(2px);
  z-index: 999;
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
  .sidebar {
    transition: none;
  }
}
</style>
