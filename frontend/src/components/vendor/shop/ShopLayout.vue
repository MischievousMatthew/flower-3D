<template>
  <vendorHeader v-if="!embedded" :title="title" />
  <div :class="embedded ? 'sl-embedded' : 'sl-page'">
    <VendorSidebar v-if="!embedded" />
    <main :class="{ 'sl-main': !embedded }">
      <nav class="sl-tabs" :aria-label="section === 'orders' ? 'Orders and fulfillment' : 'Shop and product management'">
        <router-link
          v-for="t in tabs"
          :key="t.name"
          :to="{ name: t.name }"
          class="sl-tab"
          :class="{ on: isOn(t), locked: locked(t.module) }"
          :aria-current="isOn(t) ? 'page' : undefined"
        >
          <span class="sl-tab-icon" v-html="ICONS[t.icon]"></span>
          <span>{{ t.label }}</span>
          <span v-if="locked(t.module)" aria-label="Not in your plan">🔒</span>
        </router-link>
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
import { computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import vendorHeader from "../../../layouts/vendorHeader.vue";
import VendorSidebar from "../../../layouts/Sidebar/VendorSidebar.vue";
import { useSubscriptionAccess } from "../../../composables/useSubscriptionAccess";
import { SHOP_ICONS as ICONS } from "./shopIcons";

const props = defineProps({
  // Which group of tabs to show: shop (Shop & products) or orders (Orders & fulfillment)
  section: { type: String, default: "shop" },
  title: { type: String, default: "" },
  subtitle: { type: String, default: "" },
  // Only the tab strip, for pages that keep their own layout (3D Bouquets)
  embedded: { type: Boolean, default: false },
});

const route = useRoute();
const { subscriptionAccess, loadSubscriptionAccess, subscriptionAllowsModule } = useSubscriptionAccess();

const TABS = [
  { name: "VendorShop", label: "My Shop", icon: "store" },
  { name: "VendorProducts", label: "Products", icon: "flower", module: "products" },
  { name: "VendorInventory", label: "Inventory", icon: "box", module: "products" },
  { name: "VendorCategories", label: "Categories & Occasions", icon: "tag", module: "products" },
  { name: "VendorCustomization", label: "3D Bouquets", icon: "cube", module: "products" },
  { name: "VendorPromotions", label: "Promotions", icon: "percent", module: "products" },
];

const ORDER_TABS = [
  { name: "VendorReservation", label: "Orders", icon: "receipt", module: "orders", also: ["VendorOrderDetails"] },
  { name: "VendorDeliverySchedule", label: "Deliveries", icon: "truck", module: "orders" },
  { name: "VendorCalendar", label: "Reservations", icon: "calendar", module: "calendar" },
  { name: "VendorReturns", label: "Returns & Refunds", icon: "restore", module: "orders" },
];
const tabs = computed(() => (props.section === "orders" ? ORDER_TABS : TABS));
const isOn = (t) => route.name === t.name || (t.also ?? []).includes(route.name);

const locked = (module) =>
  !!module && !!subscriptionAccess.value && !subscriptionAllowsModule(subscriptionAccess.value, module);

onMounted(() => {
  if (!subscriptionAccess.value) loadSubscriptionAccess().catch(() => {});
});
</script>

<style scoped>
.sl-page {
  margin-left: 260px;
  min-height: 100vh;
  background: #f6f8f7;
  font-family: "Poppins", sans-serif;
  color: #1a202c;
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
  background: #fff;
  border: 1px solid #e8eeeb;
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
  color: #4a5568;
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  white-space: nowrap;
  transition: background 0.15s, color 0.15s;
}
.sl-tab:hover {
  background: #f3f8f5;
  color: #1a202c;
}
.sl-tab.on {
  background: #48bb78;
  color: #fff;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
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
  color: #718096;
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
