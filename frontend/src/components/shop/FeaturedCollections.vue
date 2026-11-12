<template>
  <section class="fc" aria-labelledby="fc-title">
    <div class="bc-container">
      <header class="bc-sec-head">
        <div>
          <p class="bc-sec-eyebrow">Featured collections</p>
          <h2 id="fc-title" class="bc-sec-title">Loved by <em>our customers</em></h2>
        </div>
        <button type="button" class="bc-sec-link" @click="$emit('view-all', tab.view)">
          View all {{ tab.label.toLowerCase() }} <span aria-hidden="true">→</span>
        </button>
      </header>

      <div class="fc-tabs" role="tablist" aria-label="Collections">
        <button
          v-for="t in tabs"
          :id="`fc-tab-${t.key}`"
          :key="t.key"
          type="button"
          role="tab"
          class="fc-tab"
          :class="{ on: current === t.key }"
          :aria-selected="current === t.key"
          aria-controls="fc-panel"
          :tabindex="current === t.key ? 0 : -1"
          @click="current = t.key"
          @keydown.right.prevent="move(1)"
          @keydown.left.prevent="move(-1)"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true"><path v-for="(d, i) in t.icon" :key="i" :d="d" /></svg>
          {{ t.label }}
        </button>
      </div>

      <div id="fc-panel" role="tabpanel" :aria-labelledby="`fc-tab-${current}`" aria-live="polite">
        <div v-if="loading[current]" class="fc-grid" aria-label="Loading flowers">
          <span v-for="n in 4" :key="n" class="bc-skeleton fc-skel"></span>
        </div>
        <div v-else-if="lists[current]?.length" class="fc-grid">
          <ShopProductTile
            v-for="product in lists[current]"
            :key="`${current}-${product.id}`"
            :product="product"
            :context-tag="tab.tag"
            :adding="addingId === product.id"
            @open="$emit('open', $event)"
            @add="(p, ctx) => $emit('add', p, ctx)"
          />
        </div>
        <p v-else class="fc-empty">{{ tab.empty }}</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import api from "../../plugins/axios.js";
import ShopProductTile from "./ShopProductTile.vue";

/**
 * Best Sellers, New Arrivals, Trending and Top Rated, one tab each (plus the
 * admin's "In season" picks first when there are any). Each tab loads the
 * first time it's opened.
 */
const props = defineProps({
  featured: { type: Array, default: () => [] }, // Admin → Website → Featured
  ownerIds: { type: Array, default: null }, // only these shops (the map's circle), null for all
  addingId: { type: [Number, String], default: null },
});
defineEmits(["open", "add", "view-all"]);

const PER_TAB = 8;
const ICONS = {
  leaf: ["M5 19c0-8 5-14 14-14 0 9-6 14-14 14z", "M5 19l7-7"],
  medal: ["M12 15a5 5 0 1 0 0-10 5 5 0 0 0 0 10z", "M9 14l-2 7 5-3 5 3-2-7"],
  spark: ["M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"],
  trend: ["M3 17l6-6 4 4 8-8", "M15 7h6v6"],
  star: ["M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"],
};
const ALL_TABS = [
  { key: "season", label: "In season", tag: "recommended", icon: ICONS.leaf, view: "season", empty: "" },
  { key: "best", label: "Best sellers", tag: "best_seller", icon: ICONS.medal, view: "best", params: { best_sellers: 1, sort_by: "popular_desc" }, empty: "Best sellers show up once flowers start selling." },
  { key: "new", label: "New arrivals", tag: "new_arrival", icon: ICONS.spark, view: "new", params: { sort_by: "created_at_desc" }, empty: "New flowers show up here as shops add them." },
  { key: "trending", label: "Trending", tag: "trending", icon: ICONS.trend, view: "trending", params: { sort_by: "popular_desc" }, empty: "Nothing is trending yet." },
  { key: "top", label: "Top rated", tag: "top_rated", icon: ICONS.star, view: "top", params: { sort_by: "rating_desc" }, empty: "Top rated flowers show up once customers leave reviews." },
];
const tabs = computed(() => ALL_TABS.filter((t) => t.key !== "season" || props.featured.length));
const current = ref(props.featured.length ? "season" : "best");
const tab = computed(() => ALL_TABS.find((t) => t.key === current.value));

const lists = reactive({ season: [], best: [], new: [], trending: [], top: [] });
const loading = reactive({ season: false, best: false, new: false, trending: false, top: false });
const loaded = new Set();
let generation = 0;

const visible = (p) => ["per_piece", "bouquet", "", null, undefined].includes(p?.selling_type);

async function load(key) {
  const def = ALL_TABS.find((t) => t.key === key);
  if (!def?.params || loaded.has(key)) return;
  loaded.add(key);
  const mine = generation;
  loading[key] = !lists[key].length;
  try {
    const { data } = await api.get("customer/products", {
      params: {
        page: 1,
        per_page: key === "best" ? 24 : PER_TAB * 2,
        in_stock_only: 1,
        ...def.params,
        ...(props.ownerIds ? { owner_ids: props.ownerIds.length ? props.ownerIds.join(",") : "0" } : {}),
      },
    });
    if (mine !== generation) return;
    let list = (Array.isArray(data?.data?.data) ? data.data.data : []).filter(visible);
    if (key === "best") list = list.filter((p) => Number(p.sold_count) > 0);
    if (key === "top") list = list.filter((p) => Number(p.total_reviews) > 0);
    lists[key] = list.slice(0, PER_TAB);
  } catch {
    if (mine === generation) {
      lists[key] = [];
      loaded.delete(key);
    }
  } finally {
    if (mine === generation) loading[key] = false;
  }
}

watch(() => props.featured, (list) => {
  lists.season = list.slice(0, PER_TAB);
  if (list.length && !loaded.size && current.value === "best") current.value = "season";
  if (!list.length && current.value === "season") current.value = "best";
}, { immediate: true });
watch(current, (key) => load(key), { immediate: true });
// The map's circle changed: reload what's open, the rest on their next visit
watch(() => (props.ownerIds ? props.ownerIds.join() : "all"), (now, before) => {
  if (now === before) return;
  generation++;
  loaded.clear();
  load(current.value);
});

function move(step) {
  const list = tabs.value;
  const i = list.findIndex((t) => t.key === current.value);
  current.value = list[(i + step + list.length) % list.length].key;
  document.getElementById(`fc-tab-${current.value}`)?.focus();
}
</script>

<style scoped>
.fc {
  padding: clamp(56px, 6vw, 88px) 0 0;
}

.fc-tabs {
  display: flex;
  gap: 8px;
  margin: 0 0 22px;
  overflow-x: auto;
  scrollbar-width: none;
}

.fc-tabs::-webkit-scrollbar {
  display: none;
}

.fc-tab {
  display: inline-flex;
  flex: none;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 18px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--paper);
  color: var(--ink);
  font: 500 13.5px var(--font-sans);
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.fc-tab:hover {
  border-color: var(--ink);
}

.fc-tab.on {
  border-color: var(--leaf-deep);
  background: var(--leaf-deep);
  color: var(--paper);
}

.fc-tab svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fc-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
}

.fc-skel {
  height: 330px;
  border-radius: 14px;
}

.fc-empty {
  padding: 28px 0;
  font-size: 14px;
  color: var(--ink-soft);
}

.fc :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

@media (max-width: 960px) {
  .fc-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .fc-grid {
    display: flex;
    overflow-x: auto;
    padding-bottom: 6px;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
  }

  .fc-grid > * {
    flex: 0 0 72%;
    scroll-snap-align: start;
  }
}
</style>
