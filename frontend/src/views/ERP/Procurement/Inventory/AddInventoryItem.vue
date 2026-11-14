<template>
  <HrPage title="Add product" subtitle="Pick what you're adding. Flowers and bouquets get the full product form; wrappers, fillers, ribbons and cards are the same items as the vendor's 3D Bouquet Management.">
    <template #actions>
      <router-link :to="{ name: 'InventoryStock' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.arrowLeft"></span>Back to stock</router-link>
    </template>

    <!-- What kind of product -->
    <section class="ai-kinds" role="radiogroup" aria-label="What are you adding?">
      <button
        v-for="k in KINDS"
        :key="k.key"
        type="button"
        role="radio"
        class="ai-kind"
        :class="{ on: kind === k.key }"
        :aria-checked="kind === k.key"
        @click="pick(k)"
      >
        <span class="sp-stat-icon" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>
        <span class="ai-kind-text">
          <strong>{{ k.label }}</strong>
          <small>{{ k.hint }}</small>
        </span>
        <span v-if="k.opens" class="ai-go" v-html="ICONS.chevronRight" aria-hidden="true"></span>
      </button>
    </section>

    <!-- Wrapper, filler, ribbon or card: the 3D Bouquet Management form and list for that kind -->
    <section v-if="PART_KINDS.includes(kind)" class="ai-part" :aria-label="`Add a ${current.label.toLowerCase()}`">
      <Customization erp :type="kind" />
    </section>
    <div v-else class="sp-card ai-hint">
      <span class="sp-empty-icon" v-html="ICONS.plus"></span>
      <p>Choose a kind above to start.</p>
    </div>
  </HrPage>
</template>

<script setup>
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import HrPage from "../../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../../components/vendor/shop/shopIcons";
import Customization from "../../../vendor/Customization.vue";

// The same kinds the vendor adds: products (flowers and bouquets), then the 3D bouquet parts
const KINDS = [
  { key: "flower", label: "Flower or bouquet", hint: "Single flowers and ready bouquets, with photos, price and stock.", icon: "flower", tone: "pink", opens: true },
  { key: "wrapper", label: "Wrapper", hint: "Wrapping paper customers pick for their bouquet.", icon: "note", tone: "amber" },
  { key: "filler", label: "Filler", hint: "Greenery and small blooms placed around the flowers.", icon: "sparkle", tone: "green" },
  { key: "ribbon", label: "Ribbon", hint: "Ribbons tied around the bouquet.", icon: "tag", tone: "violet" },
  { key: "card", label: "Card", hint: "Greeting cards with a message, priced by size.", icon: "chat", tone: "blue" },
  { key: "supply", label: "Vase or supply", hint: "Vases and other supplies the shop keeps but doesn't sell.", icon: "box", tone: "slate", opens: true },
];
const PART_KINDS = ["wrapper", "filler", "ribbon", "card"];

const route = useRoute();
const router = useRouter();
const kind = ref(PART_KINDS.includes(String(route.query.type)) ? String(route.query.type) : "");
const current = computed(() => KINDS.find((k) => k.key === kind.value) ?? KINDS[0]);

function pick(k) {
  if (k.key === "flower") return router.push({ name: "AddProduct" });
  if (k.key === "supply") return router.push({ name: "InventoryStock", query: { add: "material" } });
  kind.value = k.key;
  // Keep the choice in the address, so a refresh stays on it
  router.replace({ query: { ...route.query, type: k.key } });
}
</script>

<style scoped>
.ai-kinds {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 18px;
}
.ai-kind {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
  background: var(--v-surface);
  color: var(--v-text);
  font-family: "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
}
.ai-kind:hover {
  border-color: var(--bc-accent-border);
  transform: translateY(-1px);
}
.ai-kind.on {
  border-color: var(--bc-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 18%, transparent);
}
.ai-kind:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.ai-kind-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.ai-kind-text strong {
  font-size: 14px;
  font-weight: 600;
}
.ai-kind-text small {
  font-size: 12px;
  line-height: 1.45;
  color: var(--v-muted);
}
.ai-go {
  flex: none;
  color: var(--v-faint);
}
.ai-go :deep(svg) {
  width: 16px;
  height: 16px;
}
.ai-hint {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 40px 20px;
  color: var(--v-muted);
  text-align: center;
}
.ai-hint p {
  margin: 0;
  font-size: 13px;
}
@media (max-width: 1000px) {
  .ai-kinds {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 560px) {
  .ai-kinds {
    grid-template-columns: 1fr;
  }
}
@media (prefers-reduced-motion: reduce) {
  .ai-kind {
    transition: none;
  }
  .ai-kind:hover {
    transform: none;
  }
}
</style>
