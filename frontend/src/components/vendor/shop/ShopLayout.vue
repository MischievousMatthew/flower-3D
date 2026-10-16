<template>
  <div ref="root" :class="embedded ? 'sl-embedded' : 'sl-page'">
    <VendorSidebar v-if="!embedded" />
    <!-- The header sits inside the content column so the fixed sidebar never covers it -->
    <vendorHeader v-if="!embedded" :title="title" />
    <main :class="{ 'sl-main': !embedded }">
      <nav class="sl-tabs" :aria-label="NAV_LABEL[section] ?? 'Shop and product management'">
        <template v-for="t in tabs" :key="t.name">
          <!-- A tab outside the plan says which plan it needs instead of navigating -->
          <button v-if="locked(t.module)" type="button" class="sl-tab locked" :title="upgradeText(t.module)" @click="toast.info(upgradeText(t.module))">
            <span class="sl-tab-icon" v-html="ICONS[t.icon]"></span>
            <span>{{ t.label }}</span>
            <span aria-label="Not in your plan">🔒</span>
          </button>
          <router-link v-else :to="{ name: t.name }" class="sl-tab" :class="{ on: isOn(t) }" :aria-current="isOn(t) ? 'page' : undefined">
            <span class="sl-tab-icon" v-html="ICONS[t.icon]"></span>
            <span>{{ t.label }}</span>
          </router-link>
        </template>
      </nav>

      <header v-if="!embedded" class="sl-head">
        <div>
          <h1>{{ title }}</h1>
          <p v-if="subtitle">{{ subtitle }}</p>
        </div>
        <div class="sl-actions"><slot name="actions" /></div>
      </header>

      <slot />
    </main>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import vendorHeader from "../../../layouts/vendorHeader.vue";
import VendorSidebar from "../../../layouts/Sidebar/VendorSidebar.vue";
import { toast } from "vue3-toastify";
import { subscriptionUpgradeMessage, useSubscriptionAccess } from "../../../composables/useSubscriptionAccess";
import { SHOP_ICONS as ICONS } from "./shopIcons";

const props = defineProps({
  // Which group of tabs to show: shop, orders, finance, staff, crm or account
  section: { type: String, default: "shop" },
  title: { type: String, default: "" },
  subtitle: { type: String, default: "" },
  // Only the tab strip, for pages that keep their own layout (3D Bouquets)
  embedded: { type: Boolean, default: false },
});

const route = useRoute();
const { subscriptionAccess, loadSubscriptionAccess, subscriptionAllowsModule } = useSubscriptionAccess();

const TABS = [
  { name: "VendorShop", label: "Shop Profile", icon: "store" },
  { name: "VendorProducts", label: "Products", icon: "flower", module: "products" },
  { name: "VendorInventory", label: "Inventory", icon: "box", module: "products" },
  { name: "VendorCategories", label: "Categories & Occasions", icon: "tag", module: "products" },
  { name: "VendorCustomization", label: "3D Bouquets", icon: "cube", module: "products" },
  { name: "VendorPhotoTo3D", label: "Photo to 3D", icon: "sparkle", module: "products" },
  { name: "VendorPromotions", label: "Promotions", icon: "percent", module: "products" },
];

const ORDER_TABS = [
  { name: "VendorReservation", label: "Orders", icon: "receipt", module: "orders", also: ["VendorOrderDetails"] },
  { name: "VendorDeliverySchedule", label: "Deliveries", icon: "truck", module: "orders" },
  { name: "VendorCalendar", label: "Reservations", icon: "calendar", module: "calendar" },
  { name: "VendorReturns", label: "Returns & Refunds", icon: "restore", module: "orders" },
];
const FINANCE_TABS = [
  { name: "VendorBooksSales", label: "Sales & Transactions", icon: "receipt", module: "finance" },
  { name: "VendorBooksExpenses", label: "Expenses", icon: "card", module: "finance" },
  { name: "VendorBooksProfit", label: "Profit & Loss", icon: "percent", module: "finance" },
  { name: "VendorBooksReports", label: "Reports", icon: "note", module: "finance" },
  { name: "VendorBooksRefunds", label: "Refunds", icon: "restore", module: "finance" },
];
const STAFF_TABS = [
  { name: "VendorStaffList", label: "Employees", icon: "users", module: "staff" },
  { name: "VendorStaffRoles", label: "Roles & Permissions", icon: "shield", module: "staff" },
  { name: "VendorStaffAttendance", label: "Attendance", icon: "clock", module: "attendance" },
  { name: "VendorStaffSchedules", label: "Schedules", icon: "calendar", module: "staff" },
  { name: "VendorStaffPayroll", label: "Payroll", icon: "card", module: "payroll" },
  { name: "VendorStaffActivity", label: "Activity Logs", icon: "activity", module: "staff" },
];
const CRM_TABS = [
  { name: "VendorChat", label: "Customer Chat", icon: "chat", module: "crm" },
  { name: "VendorInquiries", label: "Inquiries", icon: "help", module: "crm" },
  { name: "VendorReviews", label: "Reviews & Ratings", icon: "star", module: "products" },
  { name: "VendorNotifications", label: "Notifications", icon: "bell" },
];
// Open on every plan, even an expired one, so the vendor can always renew
const ACCOUNT_TABS = [
  { name: "VendorSubscription", label: "Subscription Plan", icon: "sparkle" },
  { name: "VendorBilling", label: "Billing History", icon: "receipt" },
  { name: "VendorUsage", label: "Usage & Limits", icon: "activity" },
  { name: "VendorShopSettings", label: "Shop Settings", icon: "store" },
  { name: "VendorSecurity", label: "Security", icon: "lock" },
];
const NAV_LABEL = { account: "Subscription and account", shop: "Shop and product management", orders: "Orders and fulfillment", finance: "Finance and business", staff: "Staff and operations", crm: "Customer relationships" };
const tabs = computed(() => ({ orders: ORDER_TABS, finance: FINANCE_TABS, staff: STAFF_TABS, crm: CRM_TABS, account: ACCOUNT_TABS })[props.section] ?? TABS);
const isOn = (t) => route.name === t.name || (t.also ?? []).includes(route.name);

const locked = (module) =>
  !!module && !!subscriptionAccess.value && !subscriptionAllowsModule(subscriptionAccess.value, module);

const upgradeText = (module) => subscriptionUpgradeMessage(subscriptionAccess.value, module);

// A photo that fails to load becomes a plain placeholder instead of showing its alt text
const root = ref(null);
const onImageError = (e) => {
  const img = e.target;
  if (img?.tagName !== "IMG" || img.dataset.broken) return;
  img.dataset.broken = "1";
  img.alt = "";
  // A transparent pixel: keeps the tile without the browser's broken-image outline
  img.src = "data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==";
};

onMounted(() => {
  if (!subscriptionAccess.value) loadSubscriptionAccess().catch(() => {});
  root.value?.addEventListener("error", onImageError, true);
});
onBeforeUnmount(() => root.value?.removeEventListener("error", onImageError, true));
</script>

<style scoped>
.sl-page {
  margin-left: 260px;
  min-height: 100vh;
  background: var(--v-bg);
  font-family: "Poppins", sans-serif;
  color: var(--v-text);
  transition: margin-left 0.3s ease;
}
.sl-main {
  max-width: 1240px;
  margin: 0 auto;
  padding: 22px 32px 64px;
}
.sl-embedded {
  margin-bottom: 18px;
}
.sl-tabs {
  display: flex;
  gap: 4px;
  padding: 5px;
  margin-bottom: 22px;
  overflow-x: auto;
  background: var(--v-surface);
  border: 1px solid var(--v-border-strong);
  border-radius: 14px;
  scrollbar-width: none;
}
.sl-tabs::-webkit-scrollbar {
  display: none;
}
.sl-tab {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 14px;
  border-radius: 10px;
  color: var(--v-text-2);
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  white-space: nowrap;
  transition: background 0.15s, color 0.15s;
}
button.sl-tab {
  border: 0;
  background: none;
  cursor: pointer;
}
.sl-tab:hover {
  background: var(--v-subtle);
  color: var(--v-text);
}
.sl-tab.on {
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
  box-shadow: 0 2px 8px color-mix(in srgb, var(--bc-accent) 30%, transparent);
}
.sl-tab.locked {
  opacity: 0.55;
}
.sl-tab-icon {
  display: inline-flex;
}
.sl-tab-icon :deep(svg) {
  width: 16px;
  height: 16px;
}
.sl-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 14px;
  margin-bottom: 20px;
}
.sl-head h1 {
  margin: 0;
  font-size: 24px;
  font-weight: 600;
  letter-spacing: -0.01em;
}
.sl-head p {
  margin: 4px 0 0;
  max-width: 640px;
  font-size: 13.5px;
  color: var(--v-muted);
}
.sl-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
@media (max-width: 968px) {
  .sl-page {
    margin-left: 0;
  }
  .sl-main {
    padding: 16px 16px 48px;
  }
}
</style>
