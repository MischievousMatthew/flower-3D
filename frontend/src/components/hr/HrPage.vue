<template>
  <div class="hp">
    <nav v-if="tabs.length" class="hp-tabs" aria-label="Employees">
      <router-link v-for="t in tabs" :key="t.name" :to="{ name: t.name }" class="hp-tab" :class="{ on: route.name === t.name }" :aria-current="route.name === t.name ? 'page' : undefined">
        <span class="hp-tab-icon" v-html="ICONS[t.icon]"></span>{{ t.label }}
      </router-link>
    </nav>

    <header class="hp-head">
      <div>
        <h1>{{ title }}</h1>
        <p v-if="subtitle">{{ subtitle }}</p>
      </div>
      <div class="hp-actions"><slot name="actions" /></div>
    </header>

    <slot />
  </div>
</template>

<script setup>
// The vendor portal's page look (header, tabs, sp-/st- classes) for ERP HR pages.
// Colors come from the --v-* tokens, so the page follows the ERP light/dark switch.
import { useRoute } from "vue-router";
import { SHOP_ICONS as ICONS } from "../vendor/shop/shopIcons";
import "../vendor/shop/shop.css";
import "../vendor/staff/staff.css";

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: "" },
  tabs: { type: Array, default: () => [] },
});

const route = useRoute();
</script>

<style scoped>
.hp {
  max-width: 1240px;
  margin: 0 auto;
  font-family: "Poppins", sans-serif;
  color: var(--v-text);
}
.hp-tabs {
  display: flex;
  gap: 4px;
  width: fit-content;
  max-width: 100%;
  padding: 5px;
  margin-bottom: 22px;
  overflow-x: auto;
  background: var(--v-surface);
  border: 1px solid var(--v-border-strong);
  border-radius: 14px;
  scrollbar-width: none;
}
.hp-tab {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 14px;
  border-radius: 10px;
  color: var(--v-text-2);
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  white-space: nowrap;
  transition: background 0.15s, color 0.15s;
}
.hp-tab:hover {
  background: var(--v-subtle);
  color: var(--v-text);
}
.hp-tab.on {
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
  box-shadow: 0 2px 8px color-mix(in srgb, var(--bc-accent) 30%, transparent);
}
.hp-tab-icon {
  display: inline-flex;
}
.hp-tab-icon :deep(svg) {
  width: 16px;
  height: 16px;
}
.hp-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 14px;
  margin-bottom: 20px;
}
.hp-head h1 {
  margin: 0;
  font-size: 24px;
  font-weight: 600;
  letter-spacing: -0.01em;
  color: var(--v-text);
}
.hp-head p {
  margin: 4px 0 0;
  max-width: 640px;
  font-size: 13.5px;
  color: var(--v-muted);
}
.hp-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
