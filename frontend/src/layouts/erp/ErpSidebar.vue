<template>
  <div v-if="isMobileOpen" class="es-backdrop" @click="closeMobile"></div>

  <aside class="es" :class="{ 'mobile-open': isMobileOpen }" aria-label="Workspace navigation">
    <!-- Whose workspace this is -->
    <div class="es-brand">
      <span class="es-logo">
        <img v-if="logoUrl" :src="logoUrl" :alt="`${shopName} logo`" @error="logoFailed = true" />
        <span v-else>{{ shopInitials }}</span>
      </span>
      <span class="es-brand-text">
        <b>{{ shopName }}</b>
        <small>{{ subtitle }}</small>
      </span>
    </div>

    <nav class="es-nav">
      <section v-for="section in sections" :key="section.label" class="es-section">
        <h2 class="es-label">{{ section.label }}</h2>
        <router-link
          v-for="item in section.items"
          :key="item.key"
          :to="item.path"
          class="es-item"
          active-class=""
          exact-active-class=""
          :class="{ active: activeKey === item.key, locked: isLocked(item.access) }"
          :aria-current="activeKey === item.key ? 'page' : undefined"
          :title="isLocked(item.access) ? subscriptionUpgradeMessage(subscriptionAccess, item.access) : undefined"
          @click="onNavigate($event, item.access)"
        >
          <span class="es-icon" v-html="ICONS[item.icon]"></span>
          <span class="es-text">{{ item.label }}</span>
          <span v-if="isLocked(item.access)" class="es-lock" aria-label="Not in your plan" v-html="ICONS.lock"></span>
          <span v-else-if="badgeFor(item.key) > 0" class="es-badge" :aria-label="`${badgeFor(item.key)} unread`">{{ badgeFor(item.key) > 99 ? "99+" : badgeFor(item.key) }}</span>
        </router-link>
      </section>
      <p v-if="!sections.length" class="es-empty">No pages have been shared with your role yet. Ask the shop owner to add some.</p>
    </nav>

    <div v-if="lockedModule" class="es-hint" role="status">
      <span>{{ subscriptionUpgradeMessage(subscriptionAccess, lockedModule) }}</span>
      <div class="es-hint-actions">
        <button type="button" class="es-hint-go" @click="viewPlans">View plans</button>
        <button type="button" class="es-hint-dismiss" @click="lockedModule = null">Dismiss</button>
      </div>
    </div>

    <div class="es-foot">
      <router-link v-if="isOwner" :to="{ name: 'VendorFinanceDashboard' }" class="es-item">
        <span class="es-icon" v-html="ICONS.store"></span>
        <span class="es-text">Back to shop portal</span>
      </router-link>
      <button v-else type="button" class="es-item" :disabled="signingOut" @click="signOut">
        <span class="es-icon" v-html="ICONS.logout"></span>
        <span class="es-text">{{ signingOut ? "Signing out…" : "Sign out" }}</span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import { useAssignment } from "../../composables/useAssignment";
import { useChatNotifications } from "../../composables/useChatNotifications";
import { useSupplyChainNotifications } from "../../composables/useSupplyChainNotifications";
import { useSidebarState } from "../../composables/useSidebarState";
import { useSubscriptionAccess } from "../../composables/useSubscriptionAccess";
import { useVendorProfile } from "../../composables/useVendorProfile";
import { SHOP_ICONS as ICONS } from "../../components/vendor/shop/shopIcons";
import { ERP_NAV, erpPlaceFor } from "./erpNav";

const route = useRoute();
const router = useRouter();
const { user, logout } = useAuth();
const { canView } = useAssignment();
const { unreadChatCount } = useChatNotifications();
const { scOrdersBadgeCount } = useSupplyChainNotifications();
const { isMobileOpen, closeMobile } = useSidebarState();
const { ACCESS_REUSE_MS, subscriptionAccess, loadSubscriptionAccess, subscriptionAllowsModule, subscriptionUpgradeMessage } = useSubscriptionAccess();
const { vendorProfile, fetchProfile } = useVendorProfile({ autoFetch: false, showToast: false });

const lockedModule = ref(null);
const logoFailed = ref(false);
const signingOut = ref(false);
let accessTimer = null;

const isOwner = computed(() => user.value?.role === "vendor");

// The owner sees every page; staff see the pages their role allows, HR and Finance alike
const sections = computed(() =>
  ERP_NAV.map((s) => ({ ...s, items: s.items.filter((i) => isOwner.value || canView(i.access)) })).filter((s) => s.items.length),
);
const activeKey = computed(() => erpPlaceFor(route.meta.canonicalPath || route.path)?.item.key ?? null);

const shopName = computed(() => {
  if (isOwner.value) return vendorProfile.value?.store_name?.trim() || user.value?.vendor_data?.store_name || "My shop";
  return user.value?.shop_name || "Workspace";
});
const subtitle = computed(() => {
  if (isOwner.value) return "Owner · Workspace";
  return user.value?.role_name ? `${user.value.name} · ${user.value.role_name}` : user.value?.name || "Staff";
});
const shopInitials = computed(() => {
  const words = shopName.value.split(/\s+/).filter((w) => /\w/.test(w));
  return (words.length >= 2 ? `${words[0][0]}${words[1][0]}` : shopName.value.slice(0, 1)).toUpperCase() || "B";
});
const logoUrl = computed(() => {
  if (logoFailed.value) return "";
  return (isOwner.value ? vendorProfile.value?.store_logo_url : user.value?.shop_logo)?.trim() || "";
});

const isLocked = (key) => subscriptionAccess.value !== null && !subscriptionAllowsModule(subscriptionAccess.value, key);
const badgeFor = (key) => (key === "crm" ? unreadChatCount.value : key === "sc_orders" ? scOrdersBadgeCount.value : 0);

const onNavigate = (event, key) => {
  if (!isLocked(key)) return;
  event.preventDefault();
  lockedModule.value = key;
};
const viewPlans = () => {
  lockedModule.value = null;
  router.push(isOwner.value ? { name: "VendorSubscription" } : "/pricing");
};

async function signOut() {
  signingOut.value = true;
  try {
    await logout();
  } finally {
    signingOut.value = false;
  }
}

onMounted(() => {
  if (isOwner.value) fetchProfile().catch(() => {});
  loadSubscriptionAccess({ maxAge: ACCESS_REUSE_MS }).catch(() => {});
  accessTimer = window.setInterval(() => loadSubscriptionAccess().catch(() => {}), 60000);
});
onUnmounted(() => window.clearInterval(accessTimer));

watch(
  () => route.path,
  () => {
    lockedModule.value = null;
    if (isMobileOpen.value) closeMobile();
  },
);
</script>

<style scoped>
.es {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 300;
  display: flex;
  flex-direction: column;
  width: 260px;
  height: 100vh;
  background: var(--v-surface);
  border-right: 1px solid var(--v-border);
  font-family: "Poppins", sans-serif;
  box-sizing: border-box;
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.es-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 14px 14px 6px;
  padding: 10px;
  border-radius: 16px;
  background: var(--v-subtle);
}
.es-logo {
  flex: none;
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  overflow: hidden;
  border-radius: 12px;
  background: var(--v-green-soft);
  color: var(--v-green-text);
  font-size: 15px;
  font-weight: 600;
}
.es-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.es-brand-text {
  min-width: 0;
}
.es-brand-text b,
.es-brand-text small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.es-brand-text b {
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
}
.es-brand-text small {
  font-size: 11.5px;
  color: var(--v-faint);
}

.es-nav {
  flex: 1;
  overflow-y: auto;
  padding: 4px 14px 12px;
  scrollbar-width: thin;
  scrollbar-color: var(--v-border) transparent;
}
.es-section + .es-section {
  margin-top: 6px;
}
.es-label {
  margin: 0;
  padding: 14px 12px 6px;
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--v-faint);
}
.es *,
.es *::before,
.es *::after {
  box-sizing: border-box;
}
.es-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 11px;
  width: 100%;
  margin-bottom: 2px;
  padding: 9px 12px;
  border: 0;
  border-radius: 12px;
  background: none;
  color: var(--v-text-2);
  font: inherit;
  font-size: 13.5px;
  font-weight: 500;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.15s, color 0.15s, box-shadow 0.15s;
}
.es-item:hover {
  background: var(--bc-accent-soft);
  color: var(--v-text);
}
.es-item:hover .es-icon {
  color: var(--bc-accent);
}
.es-item.active {
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
  box-shadow: 0 6px 16px -6px color-mix(in srgb, var(--bc-accent) 70%, transparent);
}
.es-item.active .es-icon {
  color: inherit;
}
.es-item:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.es-item.locked {
  opacity: 0.5;
}
.es-item.locked:hover {
  background: var(--v-subtle);
  color: var(--v-text-2);
}
.es-item.locked:hover .es-icon {
  color: var(--v-faint);
}
.es-icon {
  flex: none;
  display: inline-flex;
  color: var(--v-faint);
  transition: color 0.15s;
}
.es-icon :deep(svg) {
  width: 18px;
  height: 18px;
}
.es-text {
  flex: 1;
  min-width: 0;
  line-height: 1.3;
  overflow-wrap: anywhere;
}
.es-lock {
  display: inline-flex;
}
.es-lock :deep(svg) {
  width: 14px;
  height: 14px;
}
.es-badge {
  flex: none;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: #e53e3e;
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  line-height: 20px;
  text-align: center;
}
.es-item.active .es-badge {
  background: var(--v-surface);
  color: var(--v-red-text);
}
.es-empty {
  margin: 16px 12px;
  font-size: 12.5px;
  line-height: 1.5;
  color: var(--v-muted);
}

.es-hint {
  display: grid;
  gap: 8px;
  margin: 0 14px 10px;
  padding: 12px;
  border: 1px solid color-mix(in srgb, var(--v-amber-text) 30%, transparent);
  border-radius: 12px;
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
  font-size: 12px;
  line-height: 1.45;
}
.es-hint-actions {
  display: flex;
  gap: 8px;
}
.es-hint button {
  border: 0;
  border-radius: 8px;
  padding: 6px 10px;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}
.es-hint-go {
  background: #d97706;
  color: #fff;
}
.es-hint-dismiss {
  background: none;
  color: var(--v-amber-text);
}

.es-foot {
  padding: 10px 14px 14px;
  border-top: 1px solid var(--v-border);
}
.es-foot .es-item {
  margin: 0;
  font-size: 13px;
}

@media (max-width: 968px) {
  .es {
    width: 280px;
    transform: translateX(-100%);
  }
  .es.mobile-open {
    z-index: 1000;
    transform: translateX(0);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.2);
  }
}
.es-backdrop {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(2px);
}
</style>
