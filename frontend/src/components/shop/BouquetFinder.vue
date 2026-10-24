<template>
  <aside class="bf" aria-labelledby="bf-title">
    <div class="bf-head">
      <span class="bf-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M7 10l5 11 5-11" /><circle cx="9" cy="7" r="2.5" /><circle cx="15" cy="7" r="2.5" /><circle cx="12" cy="4.5" r="2.5" /></svg>
      </span>
      <div>
        <h2 id="bf-title">Find Your Perfect Bouquet</h2>
        <p>Tell us the moment, budget and color. We'll pick the best matches from our shops.</p>
      </div>
    </div>

    <form class="bf-form" @submit.prevent="find">
      <label class="bf-field">
        <span class="bc-sr-only">Occasion</span>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.6-7 10-7 10z" /></svg>
        <select v-model="form.occasion">
          <option value="">Any occasion</option>
          <option v-for="o in occasions" :key="o" :value="o">{{ o }}</option>
        </select>
      </label>
      <label class="bf-field">
        <span class="bc-sr-only">Budget</span>
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M9.5 8h3.2a2.3 2.3 0 0 1 0 4.6H9.5V8zM9.5 12.6V16M8 10.3h7" /></svg>
        <select v-model="form.budget">
          <option value="">Any budget</option>
          <option v-for="b in BUDGETS" :key="b.key" :value="b.key">{{ b.label }}</option>
        </select>
      </label>
      <label class="bf-field">
        <span class="bc-sr-only">Flower color</span>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18c1 0 1.5-.8 1.5-1.5 0-1.2-1-1.5-1-2.5 0-.8.7-1.5 1.5-1.5H16a5 5 0 0 0 5-5c0-4.1-4-7.5-9-7.5z" /><circle cx="7.5" cy="11" r="1" /><circle cx="10" cy="7" r="1" /><circle cx="15" cy="7.5" r="1" /></svg>
        <select v-model="form.color">
          <option value="">Any color</option>
          <option v-for="c in colors" :key="c" :value="c">{{ capitalize(c) }}</option>
        </select>
      </label>
      <button type="submit" class="bf-submit" :disabled="loading">{{ loading ? "Finding bouquets…" : "Get recommendations" }}</button>
    </form>

    <div v-if="loading" class="bf-results" aria-label="Finding matches">
      <span v-for="n in 2" :key="n" class="bc-skeleton bf-skel"></span>
    </div>
    <template v-else-if="searched">
      <p v-if="!results.length" class="bf-note">No exact matches yet. Try a different budget or color.</p>
      <div v-else class="bf-results">
        <ShopProductTile
          v-for="p in results"
          :key="p.id"
          :product="p"
          compact
          :adding="addingId === p.id"
          @open="$emit('open', $event)"
          @add="(product, ctx) => $emit('add', product, ctx)"
        />
      </div>
      <button v-if="results.length" type="button" class="bf-more" @click="$emit('see-all', filters())">
        See all matches <span aria-hidden="true">→</span>
      </button>
    </template>
    <p v-else class="bf-how">Picks come from what shops have in stock right now.</p>
  </aside>
</template>

<script setup>
import { reactive, ref } from "vue";
import api from "../../plugins/axios";
import ShopProductTile from "./ShopProductTile.vue";

/** Recommends in-stock products for an occasion, budget and flower color */
defineProps({
  occasions: { type: Array, default: () => [] },
  colors: { type: Array, default: () => [] },
  addingId: { type: [Number, String], default: null },
});
defineEmits(["open", "add", "see-all"]);

const BUDGETS = [
  { key: "under-500", label: "Under ₱500", min: null, max: 500 },
  { key: "500-1000", label: "₱500 – ₱1,000", min: 500, max: 1000 },
  { key: "1000-2000", label: "₱1,000 – ₱2,000", min: 1000, max: 2000 },
  { key: "2000-plus", label: "₱2,000 and up", min: 2000, max: null },
];

const form = reactive({ occasion: "", budget: "", color: "" });
const results = ref([]);
const loading = ref(false);
const searched = ref(false);

// The same filter values the shop's product list understands
function filters() {
  const budget = BUDGETS.find((b) => b.key === form.budget);
  return {
    occasion: form.occasion || null,
    color: form.color || null,
    priceMin: budget?.min ?? null,
    priceMax: budget?.max ?? null,
  };
}

async function find() {
  const f = filters();
  const params = { per_page: 4, page: 1, sort_by: "popular_desc", in_stock_only: 1 };
  if (f.occasion) params.occasion = f.occasion;
  if (f.color) params.color = f.color;
  if (f.priceMin !== null) params.min_price = f.priceMin;
  if (f.priceMax !== null) params.max_price = f.priceMax;
  loading.value = true;
  try {
    const { data } = await api.get("customer/products", { params });
    const list = Array.isArray(data?.data?.data) ? data.data.data : [];
    results.value = list.filter((p) => ["per_piece", "bouquet", "", null, undefined].includes(p?.selling_type)).slice(0, 4);
  } catch {
    results.value = [];
  } finally {
    loading.value = false;
    searched.value = true;
  }
}

const capitalize = (s) => String(s).charAt(0).toUpperCase() + String(s).slice(1);
</script>

<style scoped>
.bf,
.bf * {
  box-sizing: border-box;
}

.bf {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px 22px;
  border: 1px solid #efdcd5;
  border-radius: 16px;
  background: linear-gradient(170deg, #edf7f0 0%, var(--paper) 60%);
}

.bf-head {
  display: flex;
  gap: 14px;
}

.bf-icon {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: rgba(77, 177, 115, 0.12);
}

.bf-icon svg,
.bf-field svg {
  fill: none;
  stroke: var(--leaf-deep);
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.bf-icon svg {
  width: 24px;
  height: 24px;
}

.bf-head h2 {
  margin: 0 0 4px;
  font-family: var(--font-display);
  font-size: 24px;
  font-weight: 500;
  line-height: 1.15;
  color: var(--ink);
}

.bf-head p {
  margin: 0;
  font-size: 12.5px;
  line-height: 1.55;
  color: var(--ink-soft);
}

.bf-form {
  display: grid;
  gap: 10px;
}

.bf-field {
  position: relative;
  display: flex;
  align-items: center;
}

.bf-field svg {
  position: absolute;
  left: 14px;
  width: 18px;
  height: 18px;
  pointer-events: none;
}

.bf-field select {
  width: 100%;
  height: 44px;
  padding: 0 36px 0 42px;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: var(--paper) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236e6761' stroke-width='2' stroke-linecap='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 12px center / 16px;
  font: 13px var(--font-sans);
  color: var(--ink);
  appearance: none;
  cursor: pointer;
}

.bf-field select:focus {
  border-color: var(--leaf);
  outline: none;
}

.bf-submit {
  height: 46px;
  margin-top: 4px;
  border: 0;
  border-radius: 999px;
  background: var(--leaf-deep);
  color: var(--paper);
  font: 500 14px var(--font-sans);
  cursor: pointer;
  transition: background 0.2s ease;
}

.bf-submit:hover:not(:disabled) {
  background: var(--ink);
}

.bf-submit:disabled {
  opacity: 0.7;
}

.bf-results {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.bf-skel {
  height: 190px;
  border-radius: 12px;
}

.bf-note,
.bf-how {
  margin: 0;
  font-size: 12.5px;
  text-align: center;
  color: var(--ink-soft);
}

.bf-more {
  align-self: center;
  padding: 0 0 2px;
  border: 0;
  border-bottom: 1px solid currentColor;
  background: none;
  color: var(--leaf-deep);
  font: 500 13px var(--font-sans);
  cursor: pointer;
}

.bf :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}
</style>
