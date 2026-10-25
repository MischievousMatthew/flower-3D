<template>
  <!-- Backdrop for mobile -->
  <div v-if="isMobileOpen" class="sidebar-backdrop" data-close-guard="off" @click="closeMobile"></div>

  <aside class="sidebar" :class="{ 'mobile-open': isMobileOpen }" :style="accentVars" aria-label="Vendor navigation">
    <!-- Shop identity -->
    <router-link :to="{ name: 'VendorShop' }" class="brand">
      <span class="brand-logo">
        <img v-if="sidebarLogoUrl" :src="sidebarLogoUrl" :alt="`${businessName} logo`" @error="logoLoadFailed = true" />
        <span v-else>{{ businessInitial }}</span>
      </span>
      <span class="brand-text">
        <b>{{ businessName }}</b>
        <small>{{ businessAddress }}</small>
      </span>
    </router-link>

    <nav class="nav">
      <section v-for="section in VENDOR_NAV" :key="section.label" class="nav-section">
        <h2 class="nav-label">{{ section.label }}</h2>
        <router-link
          v-for="item in section.items"
          :key="item.name"
          :to="{ name: item.name }"
          class="nav-item"
          active-class=""
          exact-active-class=""
          :class="{ active: activeItem === item, locked: isLocked(item.module) }"
          :aria-current="activeItem === item ? 'page' : undefined"
          :title="lockTooltip(item.module)"
          @click="handleNavigation($event, item.module)"
        >
          <span class="nav-icon" v-html="ICONS[item.icon]"></span>
          <span class="nav-text">{{ item.label }}</span>
          <span v-if="isLocked(item.module)" class="nav-lock" aria-label="Not in your plan" v-html="ICONS.lock"></span>
          <span v-else-if="item.badge === 'orders' && orderCount > 0" class="nav-badge">{{ orderCount > 99 ? "99+" : orderCount }}</span>
          <span v-else-if="item.badge === 'plan' && planAlert" class="nav-badge warn" :title="planAlert">!</span>
        </router-link>
      </section>
    </nav>

    <div v-if="lockedModule" class="upgrade-hint" role="status">
      <span>{{ lockTooltip(lockedModule) }}</span>
      <div class="upgrade-actions">
        <button type="button" class="upgrade-go" @click="viewPlans">View plans</button>
        <button type="button" class="upgrade-dismiss" @click="lockedModule = null">Dismiss</button>
      </div>
    </div>

    <!-- Theme color: sidebar tabs and the buttons on every vendor page -->
    <div ref="colorRef" class="sidebar-foot">
      <button type="button" class="color-toggle" :aria-expanded="showColors" aria-controls="sidebar-colors" @click="showColors = !showColors">
        <span class="nav-icon" v-html="ICONS.palette"></span>
        <span class="nav-text">Theme color</span>
        <span class="color-dot" :style="{ background: accent }"></span>
      </button>
      <Transition name="pop">
        <div v-if="showColors" id="sidebar-colors" class="color-pop" role="dialog" aria-label="Theme color">
          <p class="color-title">Theme color</p>
          <p class="color-hint">Used for buttons, tabs and highlights on every page.</p>
          <div class="swatches">
            <button
              v-for="c in ACCENT_PRESETS"
              :key="c.value"
              type="button"
              class="swatch"
              :class="{ on: accent === c.value }"
              :style="{ background: c.value }"
              :aria-label="c.name"
              :aria-pressed="accent === c.value"
              :title="c.name"
              @click="setAccent(c.value)"
            ></button>
          </div>
          <label class="custom-color">
            <input type="color" :value="accent" aria-label="Any color" @input="setAccent($event.target.value)" />
            <span>Any color</span>
            <code>{{ accent.toUpperCase() }}</code>
          </label>
          <button v-if="accent !== DEFAULT_ACCENT" type="button" class="color-reset" @click="resetAccent">Back to default</button>
        </div>
      </Transition>
    </div>
  </aside>
</template>

<script>
// The sidebar remounts with every vendor page; keep the badge between them
const orderCountCache = { userId: null, count: 0, at: 0 };
const ORDER_COUNT_REUSE_MS = 60000;
</script>

<script setup>
import { computed, ref, onMounted, onUnmounted, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import { useSidebarState } from "../../composables/useSidebarState";
import { useVendorProfile } from "../../composables/useVendorProfile";
import { useSubscriptionAccess } from "../../composables/useSubscriptionAccess";
import { ACCENT_PRESETS, DEFAULT_ACCENT, useVendorTheme } from "../../composables/useVendorTheme";
import { SHOP_ICONS as ICONS } from "../../components/vendor/shop/shopIcons";
import { VENDOR_NAV, navPlaceFor } from "./vendorNav";
import api from "../../plugins/axios";

const router = useRouter();
const route = useRoute();
const { isMobileOpen, closeMobile } = useSidebarState();
const { accent, accentVars, setAccent, resetAccent } = useVendorTheme();
const { ACCESS_REUSE_MS, subscriptionAccess, loadSubscriptionAccess, subscriptionAllowsModule, subscriptionUpgradeMessage } = useSubscriptionAccess();
const { vendorProfile, fetchProfile } = useVendorProfile({ autoFetch: false, showToast: false });

const logoLoadFailed = ref(false);
const { user } = useAuth();
const cachedCount = () => (orderCountCache.userId === user.value?.id ? orderCountCache.count : 0);
const orderCount = ref(cachedCount());
const lockedModule = ref(null);
const showColors = ref(false);
const colorRef = ref(null);
let notificationInterval = null;
let subscriptionInterval = null;

const businessName = computed(() => vendorProfile.value?.store_name?.trim() || vendorProfile.value?.owner_name?.trim() || "Vendor Store");
const businessAddress = computed(() => vendorProfile.value?.store_address?.trim() || vendorProfile.value?.service_areas?.trim() || "No store address set");
const businessInitial = computed(() => {
  const words = businessName.value.split(/\s+/).filter(Boolean);
  return (words.length >= 2 ? `${words[0][0]}${words[1][0]}` : businessName.value.slice(0, 1)).toUpperCase() || "V";
});
const sidebarLogoUrl = computed(() => (logoLoadFailed.value ? "" : vendorProfile.value?.store_logo_url?.trim() || ""));

const activeItem = computed(() => navPlaceFor(route.name)?.item ?? null);

const isLocked = (module) => !!module && subscriptionAccess.value !== null && !subscriptionAllowsModule(subscriptionAccess.value, module);
const lockTooltip = (module) => (isLocked(module) ? subscriptionUpgradeMessage(subscriptionAccess.value, module) : undefined);

const handleNavigation = (event, module) => {
  if (!isLocked(module)) return;
  event.preventDefault();
  lockedModule.value = module;
};
const viewPlans = () => {
  lockedModule.value = null;
  router.push({ name: "VendorSubscription" });
};

// A "!" on Subscription & Billing when there's no active plan or it ends within a week
const planAlert = computed(() => {
  const access = subscriptionAccess.value;
  if (!access) return "";
  if (!access.subscription_active) return access.plan_key ? "Your plan has ended" : "Choose a plan";
  const ends = access.status === "trialing" ? access.trial_ends_at : access.subscription_ends_at;
  if (!ends) return "";
  const days = Math.ceil((new Date(ends) - Date.now()) / 86400000);
  return days <= 7 ? `Your plan ends in ${Math.max(0, days)} day${days === 1 ? "" : "s"}` : "";
});

const loadOrderCount = async ({ reuse = false } = {}) => {
  const userId = user.value?.id;
  if (reuse && orderCountCache.userId === userId && Date.now() - orderCountCache.at < ORDER_COUNT_REUSE_MS) return;
  try {
    const response = await api.get("/vendor/orders/statistics");
    const status = response.data?.data?.status_breakdown ?? {};
    orderCount.value = Number(status.pending ?? 0) + Number(status.processing ?? 0);
    Object.assign(orderCountCache, { userId, count: orderCount.value, at: Date.now() });
  } catch (_) {
    // The badge just stays as it was
  }
};

const onOutsideClick = (e) => {
  if (showColors.value && colorRef.value && !colorRef.value.contains(e.target)) showColors.value = false;
};

onMounted(() => {
  fetchProfile().catch(() => {});
  loadSubscriptionAccess({ maxAge: ACCESS_REUSE_MS }).catch(() => {});
  loadOrderCount({ reuse: true });
  notificationInterval = window.setInterval(() => loadOrderCount(), 60000);
  subscriptionInterval = window.setInterval(() => loadSubscriptionAccess().catch(() => {}), 60000);
  document.addEventListener("click", onOutsideClick);
});

onUnmounted(() => {
  window.clearInterval(notificationInterval);
  window.clearInterval(subscriptionInterval);
  document.removeEventListener("click", onOutsideClick);
});

watch(
  () => route.path,
  () => {
    lockedModule.value = null;
    loadSubscriptionAccess({ maxAge: ACCESS_REUSE_MS }).catch(() => {});
    if (isMobileOpen.value) closeMobile();
  },
);
watch(() => vendorProfile.value?.store_logo_url, () => (logoLoadFailed.value = false));
</script>

<style scoped>
.sidebar {
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
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Identity */
.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 14px 14px 6px;
  padding: 10px;
  border-radius: 16px;
  background: var(--v-subtle);
  text-decoration: none;
  transition: background 0.15s;
}
.brand:hover {
  background: var(--v-subtle);
}
.brand-logo {
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
.brand-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.brand-text {
  min-width: 0;
}
.brand-text b,
.brand-text small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.brand-text b {
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
}
.brand-text small {
  font-size: 11.5px;
  color: var(--v-faint);
}

/* Navigation */
.nav {
  flex: 1;
  overflow-y: auto;
  padding: 4px 14px 12px;
  scrollbar-width: thin;
  scrollbar-color: var(--v-border) transparent;
}
.nav-section + .nav-section {
  margin-top: 6px;
}
.nav-label {
  margin: 0;
  padding: 14px 12px 6px;
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--v-faint);
}
.nav-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 11px;
  margin-bottom: 2px;
  padding: 9px 12px;
  border-radius: 12px;
  color: var(--v-text-2);
  font-size: 13.5px;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s, color 0.15s, box-shadow 0.15s;
}
.nav-item:hover {
  background: var(--sb-accent-soft);
  color: var(--v-text);
}
.nav-item:hover .nav-icon {
  color: var(--sb-accent);
}
.nav-item.active {
  background: var(--sb-accent);
  color: var(--sb-accent-ink);
  box-shadow: 0 6px 16px -6px var(--sb-accent-glow);
}
.nav-item.active .nav-icon {
  color: inherit;
}
.nav-item:focus-visible {
  outline: 2px solid var(--sb-accent);
  outline-offset: 2px;
}
.nav-item.locked {
  opacity: 0.5;
}
.nav-item.locked:hover {
  background: var(--v-subtle);
  color: var(--v-text-2);
}
.nav-item.locked:hover .nav-icon {
  color: inherit;
}
.nav-icon {
  flex: none;
  display: inline-flex;
  color: var(--v-faint);
  transition: color 0.15s;
}
.nav-icon :deep(svg) {
  width: 18px;
  height: 18px;
}
.nav-text {
  flex: 1;
  min-width: 0;
  line-height: 1.3;
  overflow-wrap: anywhere;
}
.nav-lock {
  display: inline-flex;
}
.nav-lock :deep(svg) {
  width: 14px;
  height: 14px;
}
.nav-badge {
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
.nav-badge.warn {
  background: #f59e0b;
}
.nav-item.active .nav-badge {
  background: var(--v-surface);
  color: var(--v-red-text);
}

/* Locked-module hint */
.upgrade-hint {
  display: grid;
  gap: 8px;
  margin: 0 14px 10px;
  padding: 12px;
  border: 1px solid #fde68a;
  border-radius: 12px;
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
  font-size: 12px;
  line-height: 1.45;
}
.upgrade-actions {
  display: flex;
  gap: 8px;
}
.upgrade-hint button {
  border: 0;
  border-radius: 8px;
  padding: 6px 10px;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}
.upgrade-go {
  background: #d97706;
  color: #fff;
}
.upgrade-dismiss {
  background: none;
  color: var(--v-amber-text);
}

/* Tab color */
.sidebar-foot {
  position: relative;
  padding: 10px 14px 14px;
  border-top: 1px solid var(--v-border);
}
.color-toggle {
  display: flex;
  align-items: center;
  gap: 11px;
  width: 100%;
  padding: 9px 12px;
  border: 0;
  border-radius: 12px;
  background: none;
  color: var(--v-text-2);
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}
.color-toggle:hover {
  background: var(--sb-accent-soft);
}
.color-dot {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  box-shadow: 0 0 0 2px var(--v-surface), 0 0 0 3px var(--v-border-strong);
}
.color-pop {
  position: absolute;
  left: 14px;
  right: 14px;
  bottom: calc(100% - 4px);
  padding: 14px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
  background: var(--v-raised);
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.16);
}
.color-title {
  margin: 0 0 2px;
  font-size: 12px;
  font-weight: 600;
  color: var(--v-text-2);
}
.color-hint {
  margin: 0 0 10px;
  font-size: 11px;
  line-height: 1.4;
  color: var(--v-muted);
}
.swatches {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 8px;
}
.swatch {
  width: 100%;
  aspect-ratio: 1;
  border: 0;
  border-radius: 50%;
  cursor: pointer;
  transition: transform 0.12s;
}
.swatch:hover {
  transform: scale(1.1);
}
.swatch.on {
  box-shadow: 0 0 0 2px var(--v-surface), 0 0 0 4px var(--v-text);
}
.custom-color {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 12px;
  padding: 8px 10px;
  border: 1px solid var(--v-border);
  border-radius: 10px;
  font-size: 12.5px;
  color: var(--v-text-2);
  cursor: pointer;
}
.custom-color input {
  width: 26px;
  height: 26px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: none;
  cursor: pointer;
}
.custom-color input::-webkit-color-swatch-wrapper {
  padding: 0;
}
.custom-color input::-webkit-color-swatch {
  border: 0;
  border-radius: 50%;
}
.custom-color span {
  flex: 1;
}
.custom-color code {
  font-size: 11.5px;
  color: var(--v-faint);
}
.color-reset {
  margin-top: 8px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--v-muted);
  font: inherit;
  font-size: 12px;
  text-decoration: underline;
  cursor: pointer;
}
.pop-enter-active,
.pop-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}
.pop-enter-from,
.pop-leave-to {
  opacity: 0;
  transform: translateY(6px);
}

/* Mobile */
@media (max-width: 968px) {
  .sidebar {
    width: 280px;
    transform: translateX(-100%);
  }
  .sidebar.mobile-open {
    z-index: 1000;
    transform: translateX(0);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);
  }
}
.sidebar-backdrop {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(2px);
}
</style>
