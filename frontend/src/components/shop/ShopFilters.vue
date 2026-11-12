<template>
  <div class="sf-wrap" :class="{ open }">
    <div class="sf-scrim" aria-hidden="true" @click="$emit('close')"></div>
    <aside class="sf" aria-label="Filters" :role="open ? 'dialog' : null" :aria-modal="open ? 'true' : null">
      <div class="sf-top">
        <h3>Filters</h3>
        <button v-if="activeCount" type="button" class="sf-reset" @click="$emit('clear')">Clear all</button>
        <button type="button" class="sf-close" aria-label="Close filters" @click="$emit('close')">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
        </button>
      </div>

      <!-- Availability -->
      <fieldset class="sf-group">
        <legend>Availability</legend>
        <label class="sf-check">
          <input type="checkbox" :checked="filters.inStockOnly" @change="$emit('flag', 'inStockOnly', $event.target.checked)" />
          <span>In stock only</span>
        </label>
        <label class="sf-check">
          <input type="checkbox" :checked="filters.onSale" @change="$emit('flag', 'onSale', $event.target.checked)" />
          <span>On sale</span>
        </label>
      </fieldset>

      <!-- Price -->
      <fieldset class="sf-group">
        <legend>Price</legend>
        <div class="sf-quick">
          <button
            v-for="b in BUDGETS"
            :key="b.label"
            type="button"
            class="sf-chip"
            :class="{ on: filters.priceMin === b.min && filters.priceMax === b.max }"
            :aria-pressed="filters.priceMin === b.min && filters.priceMax === b.max"
            @click="$emit('price', b.min, b.max)"
          >{{ b.label }}</button>
        </div>
        <form class="sf-price" @submit.prevent="applyPrice">
          <label><span class="bc-sr-only">Minimum price</span><input v-model="min" type="number" min="0" inputmode="numeric" placeholder="Min ₱" /></label>
          <span aria-hidden="true">–</span>
          <label><span class="bc-sr-only">Maximum price</span><input v-model="max" type="number" min="0" inputmode="numeric" placeholder="Max ₱" /></label>
          <button type="submit" class="sf-go">Go</button>
        </form>
      </fieldset>

      <fieldset v-for="group in groups" :key="group.key" class="sf-group">
        <legend>{{ group.label }}</legend>
        <ul class="sf-list" :class="{ swatches: group.key === 'color' }">
          <li v-for="item in shown(group)" :key="item.value">
            <label class="sf-check">
              <input type="checkbox" :checked="(filters[group.key] || []).includes(item.value)" @change="$emit('toggle', group.key, item.value)" />
              <span v-if="group.key === 'color'" class="sf-dot" :style="{ background: flowerSwatch(item.value) }" aria-hidden="true"></span>
              <span>{{ item.label }}</span>
            </label>
          </li>
        </ul>
        <button v-if="group.items.length > LIMIT" type="button" class="sf-more" :aria-expanded="!!expanded[group.key]" @click="expanded[group.key] = !expanded[group.key]">
          {{ expanded[group.key] ? "Show less" : `Show all ${group.items.length}` }}
        </button>
      </fieldset>

      <div class="sf-foot">
        <button type="button" class="sf-done" @click="$emit('close')">Show {{ total }} {{ total === 1 ? "flower" : "flowers" }}</button>
      </div>
    </aside>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import { flowerSwatch } from "../../utils/flowerColors.js";

/**
 * The catalog's filters: a column beside the flowers on wide screens, a
 * drawer (opened by the Filters button) on phones.
 */
const props = defineProps({
  options: { type: Object, default: () => ({}) },
  filters: { type: Object, required: true },
  activeCount: { type: Number, default: 0 },
  total: { type: Number, default: 0 },
  open: { type: Boolean, default: false },
});
const emit = defineEmits(["toggle", "flag", "price", "clear", "close"]);

const LIMIT = 6;
const BUDGETS = [
  { label: "Under ₱500", min: null, max: 500 },
  { label: "₱500 – ₱1,000", min: 500, max: 1000 },
  { label: "₱1,000 – ₱2,000", min: 1000, max: 2000 },
  { label: "₱2,000+", min: 2000, max: null },
];
const words = (s) => String(s).replace(/[-_]/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
const list = (source, labels) =>
  (props.options[source] || []).map((value) => ({ value, label: (labels && props.options.labels?.[labels]?.[value]) || words(value) }));
const groups = computed(() =>
  [
    { key: "category", label: "Arrangement", items: list("categories", "categories") },
    { key: "flowerType", label: "Flower type", items: list("flower_types", "flower_types") },
    { key: "occasion", label: "Occasion", items: list("occasions", "occasions") },
    { key: "color", label: "Color", items: list("colors") },
  ].filter((g) => g.items.length),
);
const expanded = reactive({});
// Selected values stay visible even when the list is folded
const shown = (group) =>
  expanded[group.key] ? group.items : group.items.filter((item, i) => i < LIMIT || (props.filters[group.key] || []).includes(item.value));

const min = ref("");
const max = ref("");
watch(() => [props.filters.priceMin, props.filters.priceMax], ([a, b]) => {
  min.value = a ?? "";
  max.value = b ?? "";
}, { immediate: true });
function applyPrice() {
  const lo = min.value === "" ? null : Math.max(0, Number(min.value));
  const hi = max.value === "" ? null : Math.max(0, Number(max.value));
  emit("price", lo, hi !== null && lo !== null && hi < lo ? lo : hi);
}
</script>

<style scoped>
.sf {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 20px;
  border: 1px solid var(--line-soft);
  border-radius: 20px;
  background: var(--paper);
}

.sf-top {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
}

.sf-top h3 {
  margin: 0;
  font-family: var(--font-sans);
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
}

.sf-reset {
  margin-left: auto;
  padding: 0;
  border: 0;
  background: none;
  color: var(--leaf-deep);
  font: 500 12.5px var(--font-sans);
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}

.sf-close,
.sf-scrim,
.sf-foot {
  display: none;
}

.sf-group {
  min-width: 0;
  margin: 0;
  padding: 14px 0;
  border: 0;
  border-top: 1px solid var(--line-soft);
}

.sf-group legend {
  float: left;
  width: 100%;
  margin-bottom: 8px;
  padding: 0;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ink-soft);
}

.sf-list {
  clear: both;
  margin: 0;
  padding: 0;
  list-style: none;
}

.sf-check {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 36px;
  font-size: 13.5px;
  color: var(--ink);
  cursor: pointer;
}

.sf-check input {
  width: 18px;
  height: 18px;
  margin: 0;
  accent-color: var(--leaf-deep);
  cursor: pointer;
}

.sf-dot {
  width: 16px;
  height: 16px;
  border: 1px solid var(--line);
  border-radius: 50%;
}

.sf-more {
  margin-top: 4px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--leaf-deep);
  font: 500 12.5px var(--font-sans);
  cursor: pointer;
}

.sf-quick {
  display: flex;
  flex-wrap: wrap;
  clear: both;
  gap: 6px;
  margin-bottom: 10px;
}

.sf-chip {
  min-height: 32px;
  padding: 0 12px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--paper);
  color: var(--ink);
  font: 500 12px var(--font-sans);
  cursor: pointer;
}

.sf-chip:hover {
  border-color: var(--ink);
}

.sf-chip.on {
  border-color: var(--leaf-deep);
  background: var(--leaf-deep);
  color: var(--paper);
}

.sf-price {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--ink-soft);
}

.sf-price label {
  flex: 1;
  min-width: 0;
}

.sf-price input {
  width: 100%;
  height: 40px;
  padding: 0 10px;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: var(--paper);
  color: var(--ink);
  font: 13px var(--font-sans);
}

.sf-go {
  height: 40px;
  padding: 0 14px;
  border: 0;
  border-radius: 10px;
  background: var(--ink);
  color: var(--paper);
  font: 500 13px var(--font-sans);
  cursor: pointer;
}

.sf :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

/* Phones and tablets: a drawer from the left */
@media (max-width: 1024px) {
  .sf-wrap {
    position: fixed;
    inset: 0;
    z-index: 1500;
    pointer-events: none;
  }

  .sf-scrim {
    position: absolute;
    inset: 0;
    display: block;
    background: rgba(38, 45, 57, 0.45);
    opacity: 0;
    transition: opacity 0.25s ease;
  }

  .sf {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: min(360px, 88vw);
    overflow-y: auto;
    border-radius: 0 20px 20px 0;
    transform: translateX(-102%);
    transition: transform 0.3s var(--ease-out);
  }

  .sf-wrap.open {
    pointer-events: auto;
  }

  .sf-wrap.open .sf-scrim {
    opacity: 1;
  }

  .sf-wrap.open .sf {
    transform: none;
  }

  .sf-close {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    margin-left: 4px;
    border: 0;
    border-radius: 50%;
    background: var(--ivory);
    color: var(--ink);
    cursor: pointer;
  }

  .sf-reset + .sf-close {
    margin-left: 4px;
  }

  .sf-top h3 + .sf-close {
    margin-left: auto;
  }

  .sf-close svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
  }

  .sf-foot {
    position: sticky;
    bottom: -20px;
    display: block;
    margin: auto -20px -20px;
    padding: 14px 20px 20px;
    background: var(--paper);
    border-top: 1px solid var(--line-soft);
  }

  .sf-done {
    width: 100%;
    height: 48px;
    border: 0;
    border-radius: 12px;
    background: var(--leaf-deep);
    color: var(--paper);
    font: 600 14px var(--font-sans);
    cursor: pointer;
  }
}

@media (prefers-reduced-motion: reduce) {
  .sf,
  .sf-scrim {
    transition: none;
  }
}
</style>
