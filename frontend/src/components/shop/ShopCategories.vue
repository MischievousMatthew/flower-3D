<template>
  <section class="sc" aria-labelledby="sc-title">
    <div class="bc-container">
      <header class="bc-sec-head">
        <div>
          <p class="bc-sec-eyebrow">Shop by category</p>
          <h2 id="sc-title" class="bc-sec-title">Find flowers <em>your way</em></h2>
        </div>
        <div class="sc-tabs" role="tablist" aria-label="Browse by">
          <button
            v-for="tab in visibleTabs"
            :id="`sc-tab-${tab.key}`"
            :key="tab.key"
            type="button"
            role="tab"
            class="sc-tab"
            :class="{ on: current === tab.key }"
            :aria-selected="current === tab.key"
            :aria-controls="`sc-panel`"
            :tabindex="current === tab.key ? 0 : -1"
            @click="current = tab.key"
            @keydown.right.prevent="move(1)"
            @keydown.left.prevent="move(-1)"
          >
            {{ tab.label }}
          </button>
        </div>
      </header>

      <div id="sc-panel" role="tabpanel" class="sc-rail" :aria-labelledby="`sc-tab-${current}`">
        <!-- One row that scrolls sideways; arrows when it doesn't all fit -->
        <template v-if="!loading && scrollable">
          <button type="button" class="sc-arrow prev" aria-label="Scroll back" :disabled="atStart" @click="slide(-1)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
          </button>
          <button type="button" class="sc-arrow next" aria-label="Scroll for more" :disabled="atEnd" @click="slide(1)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
          </button>
        </template>

        <ul v-if="loading" class="sc-grid" aria-label="Loading categories">
          <li v-for="n in 10" :key="n"><span class="bc-skeleton sc-skel"></span></li>
        </ul>

        <!-- Colors: a swatch for each -->
        <ul v-else-if="current === 'color' && items.length" ref="railEl" class="sc-swatches" @scroll.passive="measure">
          <li v-for="item in items" :key="item.value">
            <button type="button" class="sc-swatch" :class="{ on: isActive(item) }" :aria-pressed="isActive(item)" @click="pick(item)">
              <span class="sc-dot" :style="{ background: swatch(item.value) }" aria-hidden="true"></span>
              {{ item.label }}
            </button>
          </li>
        </ul>

        <!-- Flower types, occasions and arrangements: picture tiles -->
        <ul v-else-if="items.length" ref="railEl" class="sc-grid" @scroll.passive="measure">
          <li v-for="(item, i) in items" :key="item.value">
            <button type="button" class="sc-tile" :class="{ on: isActive(item) }" :aria-pressed="isActive(item)" @click="pick(item)">
              <span class="sc-photo" :style="{ '--tint': TINTS[i % TINTS.length] }">
                <img v-if="item.image" :src="item.image" alt="" loading="lazy" decoding="async" />
                <span v-else-if="current === 'occasion'" class="sc-icon" aria-hidden="true" v-html="occasionIconFor(item.value)"></span>
                <span v-else class="sc-initial" aria-hidden="true">{{ item.label.charAt(0) }}</span>
              </span>
              <span class="sc-name">{{ item.label }}</span>
              <span v-if="item.count != null" class="sc-count">{{ item.count ? `${item.count} ${item.count === 1 ? "flower" : "flowers"}` : "Coming soon" }}</span>
            </button>
          </li>
        </ul>
        <p v-else class="sc-empty">Nothing here yet. Categories appear as shops add flowers.</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { occasionIconFor } from "../../constants/occasionIcons.js";
import { flowerSwatch } from "../../utils/flowerColors.js";

/**
 * Shop by category: flower types, occasions, colors and arrangements, each a
 * tab. Picking one filters the catalog below (emits `pick` with its filter).
 */
const props = defineProps({
  options: { type: Object, default: () => ({}) }, // the /customer/products/filters reply
  images: { type: Object, default: () => ({}) }, // occasion -> photo
  typeImages: { type: Object, default: () => ({}) }, // flower type / category -> photo
  active: { type: Object, default: () => ({}) }, // { category: [], flowerType: [], occasion: [], color: [] }
  loading: { type: Boolean, default: false },
});
const emit = defineEmits(["pick"]);

const TABS = [
  { key: "occasion", label: "Occasions", source: "occasions", labels: "occasions" },
  { key: "category", label: "Arrangements", source: "categories", labels: "categories" },
  { key: "flowerType", label: "Flower types", source: "flower_types", labels: "flower_types" },
  { key: "color", label: "Colors", source: "colors" },
];
const TINTS = ["#f6e6e1", "#e7f2ea", "#efe9f6", "#fbf0dc", "#e5eef6", "#f4e8ee"];

const words = (s) => String(s).replace(/[-_]/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
const listFor = (tab) =>
  (props.options[tab.source] || []).map((value) => ({
    value,
    label: (tab.labels && props.options.labels?.[tab.labels]?.[value]) || words(value),
    image: tab.key === "occasion" ? props.images[value] : props.typeImages[`${tab.key}:${value}`],
    count: tab.key === "occasion" && props.options.occasion_counts ? Number(props.options.occasion_counts[value] || 0) : null,
  }));

// Tabs with nothing in them are hidden (once loaded)
const visibleTabs = computed(() => (props.loading ? TABS : TABS.filter((t) => listFor(t).length)));
const current = ref("occasion");
watch(visibleTabs, (tabs) => {
  if (tabs.length && !tabs.some((t) => t.key === current.value)) current.value = tabs[0].key;
}, { immediate: true });
const items = computed(() => {
  const tab = TABS.find((t) => t.key === current.value);
  return tab ? listFor(tab) : [];
});

function move(step) {
  const tabs = visibleTabs.value;
  const i = tabs.findIndex((t) => t.key === current.value);
  current.value = tabs[(i + step + tabs.length) % tabs.length].key;
  document.getElementById(`sc-tab-${current.value}`)?.focus();
}

// ── The sideways row: arrows, and whether they can go further ──
const railEl = ref(null);
const scrollable = ref(false);
const atStart = ref(true);
const atEnd = ref(false);
function measure() {
  const el = railEl.value;
  if (!el) return (scrollable.value = false);
  scrollable.value = el.scrollWidth > el.clientWidth + 4;
  atStart.value = el.scrollLeft <= 4;
  atEnd.value = el.scrollLeft + el.clientWidth >= el.scrollWidth - 4;
}
function slide(direction) {
  const el = railEl.value;
  if (!el) return;
  const reduce = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  el.scrollBy({ left: direction * Math.max(el.clientWidth * 0.8, 200), behavior: reduce ? "auto" : "smooth" });
}
let resize = null;
onMounted(() => {
  if (typeof ResizeObserver !== "undefined") resize = new ResizeObserver(measure);
});
onBeforeUnmount(() => resize?.disconnect());
watch(railEl, (el, old) => {
  if (old) resize?.unobserve(old);
  if (el) resize?.observe(el);
});
// A new tab or new items: start at the left and check again
watch(() => [current.value, items.value.length, props.loading], async () => {
  await nextTick();
  if (railEl.value) railEl.value.scrollLeft = 0;
  measure();
});

const isActive = (item) => (props.active[current.value] || []).includes(item.value);
const pick = (item) => emit("pick", { type: current.value, value: item.value });

const swatch = flowerSwatch;
</script>

<style scoped>
.sc {
  padding: clamp(40px, 5vw, 64px) 0 0;
}

.sc-tabs {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 4px;
  padding: 4px;
  border: 1px solid var(--line-soft);
  border-radius: 999px;
  background: var(--paper);
}

.sc-tab {
  min-height: 40px;
  padding: 0 18px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--ink-soft);
  font: 500 13px var(--font-sans);
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease;
}

.sc-tab:hover {
  color: var(--ink);
}

.sc-tab.on {
  background: var(--ink);
  color: var(--paper);
}

/* One row of tiles that scrolls left and right */
.sc-rail {
  position: relative;
}

.sc-grid,
.sc-swatches {
  display: flex;
  gap: 14px;
  margin: 0;
  padding: 2px 2px 8px;
  overflow-x: auto;
  list-style: none;
  scroll-snap-type: x proximity;
  scroll-padding-inline: 2px;
  scrollbar-width: none;
}

.sc-grid::-webkit-scrollbar,
.sc-swatches::-webkit-scrollbar {
  display: none;
}

.sc-grid > li {
  flex: 0 0 112px;
  scroll-snap-align: start;
}

.sc-swatches > li {
  flex: none;
  scroll-snap-align: start;
}

.sc-arrow {
  position: absolute;
  top: 38px;
  z-index: 2;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: var(--paper);
  color: var(--ink);
  box-shadow: 0 8px 20px -12px rgba(38, 45, 57, 0.5);
  cursor: pointer;
  transition: opacity 0.2s ease, border-color 0.2s ease;
}

.sc-arrow:hover:not(:disabled) {
  border-color: var(--ink);
}

.sc-arrow:disabled {
  opacity: 0;
  pointer-events: none;
}

.sc-arrow.prev {
  left: -14px;
}

.sc-arrow.next {
  right: -14px;
}

.sc-arrow svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}


.sc-rail:has(.sc-swatches) .sc-arrow {
  top: 4px;
}

.sc-tile {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  width: 100%;
  padding: 0 0 4px;
  border: 0;
  background: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.sc-photo {
  position: relative;
  display: grid;
  place-items: center;
  width: 100%;
  aspect-ratio: 1;
  margin-bottom: 6px;
  overflow: hidden;
  border: 2px solid transparent;
  border-radius: 18px;
  background: var(--tint);
  transition: border-color 0.2s ease, box-shadow 0.25s ease;
}

.sc-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s var(--ease-out);
}

.sc-tile:hover .sc-photo {
  box-shadow: 0 18px 36px -24px rgba(54, 63, 78, 0.45);
}

.sc-tile:hover img {
  transform: scale(1.06);
}

.sc-tile.on .sc-photo {
  border-color: var(--leaf);
}

.sc-icon {
  color: var(--leaf-deep);
}

.sc-icon :deep(svg) {
  width: 32px;
  height: 32px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.4;
}

.sc-initial {
  font-family: var(--font-display);
  font-size: 42px;
  font-style: italic;
  color: var(--leaf-deep);
}

.sc-name {
  width: 100%;
  overflow: hidden;
  font-size: 13px;
  font-weight: 500;
  color: var(--ink);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sc-count {
  font-size: 11.5px;
  color: var(--ink-soft);
}

.sc-skel {
  display: block;
  aspect-ratio: 1;
  border-radius: 18px;
}

.sc-swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.sc-swatch {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 48px;
  padding: 0 20px 0 8px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--paper);
  color: var(--ink);
  font: 500 13.5px var(--font-sans);
  cursor: pointer;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.sc-swatch:hover {
  border-color: var(--ink);
}

.sc-swatch.on {
  border-color: var(--leaf);
  box-shadow: 0 0 0 1px var(--leaf);
}

.sc-dot {
  width: 32px;
  height: 32px;
  border: 1px solid var(--line);
  border-radius: 50%;
}

.sc-empty {
  font-size: 14px;
  color: var(--ink-soft);
}

.sc :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

@media (max-width: 640px) {
  .sc-tabs {
    width: 100%;
    flex-wrap: nowrap;
    overflow-x: auto;
    scrollbar-width: none;
  }

  .sc-tab {
    flex: none;
  }

  .sc-grid > li {
    flex-basis: 96px;
  }

  /* Phones scroll by swiping */
  .sc-arrow {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .sc-photo img,
  .sc-photo {
    transition: none;
  }
}
</style>
