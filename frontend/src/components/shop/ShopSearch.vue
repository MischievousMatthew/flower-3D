<template>
  <div ref="rootEl" class="ss" :class="{ open: showPanel }">
    <label class="ss-field">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" /></svg>
      <input
        ref="inputEl"
        :value="modelValue"
        type="search"
        inputmode="search"
        enterkeyhint="search"
        maxlength="100"
        autocomplete="off"
        placeholder="Search roses, weddings, red…"
        aria-label="Search flowers"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="showPanel"
        aria-controls="ss-panel"
        :aria-activedescendant="activeId || undefined"
        @input="onInput"
        @focus="openPanel"
        @keydown="onKey"
      />
      <button v-if="modelValue" type="button" class="ss-clear" aria-label="Clear search" @click="clear">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7l10 10M17 7 7 17" /></svg>
      </button>
    </label>

    <div v-if="showPanel" id="ss-panel" class="ss-panel" role="listbox" aria-label="Search suggestions" @mousedown.prevent>
      <!-- Nothing typed: what others search for, and what you looked at -->
      <template v-if="!query">
        <section v-if="popularItems.length" class="ss-sec">
          <h3 class="ss-head">Popular searches</h3>
          <div class="ss-chips">
            <button
              v-for="item in popularItems"
              :id="item.id"
              :key="item.id"
              type="button"
              role="option"
              class="ss-chip"
              :class="{ active: activeId === item.id }"
              :aria-selected="activeId === item.id"
              @click="pick(item)"
              @mousemove="hover(item)"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 17 6-6 4 4 8-8" /><path d="M15 7h6v6" /></svg>
              {{ item.label }}
            </button>
          </div>
        </section>
        <div v-else-if="popularLoading" class="ss-sec">
          <h3 class="ss-head">Popular searches</h3>
          <div class="ss-chips"><span v-for="n in 5" :key="n" class="bc-skeleton ss-chip-skel"></span></div>
        </div>

        <section v-if="recentItems.length" class="ss-sec">
          <h3 class="ss-head">Recently viewed</h3>
          <div class="ss-recent">
            <button
              v-for="item in recentItems"
              :id="item.id"
              :key="item.id"
              type="button"
              role="option"
              class="ss-card"
              :class="{ active: activeId === item.id }"
              :aria-selected="activeId === item.id"
              @click="pick(item)"
              @mousemove="hover(item)"
            >
              <span class="ss-card-img">
                <img v-if="imageOf(item.product)" :src="sizedImage(imageOf(item.product), 160)" alt="" loading="lazy" />
              </span>
              <span class="ss-card-name">{{ item.label }}</span>
              <span class="ss-card-price">{{ peso(priceOf(item.product)) }}</span>
            </button>
          </div>
        </section>

        <p v-if="!popularItems.length && !popularLoading && !recentItems.length" class="ss-note">
          Search by flower, occasion or colour.
        </p>
      </template>

      <!-- Typing: matching flowers, then flower names, occasions and colours -->
      <template v-else>
        <section class="ss-sec">
          <h3 class="ss-head">Products</h3>
          <div v-if="productsLoading && !productItems.length" class="ss-list">
            <div v-for="n in 3" :key="n" class="ss-row ss-row--skel" aria-hidden="true">
              <span class="bc-skeleton ss-thumb"></span>
              <span class="bc-skeleton ss-line"></span>
            </div>
          </div>
          <ul v-else-if="productItems.length" class="ss-list">
            <li v-for="item in productItems" :key="item.id">
              <button
                :id="item.id"
                type="button"
                role="option"
                class="ss-row"
                :class="{ active: activeId === item.id }"
                :aria-selected="activeId === item.id"
                @click="pick(item)"
                @mousemove="hover(item)"
              >
                <span class="ss-thumb">
                  <img v-if="imageOf(item.product)" :src="sizedImage(imageOf(item.product), 96)" alt="" loading="lazy" />
                </span>
                <span class="ss-row-main">
                  <span class="ss-name"><template v-for="(part, i) in mark(item.label)" :key="i"><b v-if="part.hit">{{ part.text }}</b><template v-else>{{ part.text }}</template></template></span>
                  <small v-if="item.product.vendor_name">{{ item.product.vendor_name }}</small>
                </span>
                <span class="ss-price">{{ peso(priceOf(item.product)) }}</span>
              </button>
            </li>
          </ul>
          <p v-else class="ss-note ss-note--tight">No flowers named like that. Try a flower, occasion or colour below.</p>
        </section>

        <section v-for="group in matchGroups" :key="group.key" class="ss-sec">
          <h3 class="ss-head">{{ group.title }}</h3>
          <ul class="ss-list">
            <li v-for="item in group.items" :key="item.id">
              <button
                :id="item.id"
                type="button"
                role="option"
                class="ss-row ss-row--small"
                :class="{ active: activeId === item.id }"
                :aria-selected="activeId === item.id"
                @click="pick(item)"
                @mousemove="hover(item)"
              >
                <span class="ss-icon" :class="item.type" aria-hidden="true">
                  <i v-if="item.type === 'color'" :style="{ background: swatchFor(item.value) }"></i>
                  <svg v-else-if="item.type === 'flower'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2.5" /><path d="M12 15.5A3.5 3.5 0 1 1 8.5 12 3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 1 1 3.5 3.5 3.5 3.5 0 1 1-3.5 3.5" /></svg>
                  <svg v-else viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M4 10h16M9 3v4M15 3v4" /></svg>
                </span>
                <span class="ss-name"><template v-for="(part, i) in mark(item.label)" :key="i"><b v-if="part.hit">{{ part.text }}</b><template v-else>{{ part.text }}</template></template></span>
                <span class="ss-kind">{{ group.kind }}</span>
              </button>
            </li>
          </ul>
        </section>

        <button
          :id="searchAllItem.id"
          type="button"
          role="option"
          class="ss-all"
          :class="{ active: activeId === searchAllItem.id }"
          :aria-selected="activeId === searchAllItem.id"
          @click="pick(searchAllItem)"
          @mousemove="hover(searchAllItem)"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" /></svg>
          Search all flowers for “{{ query }}”
          <kbd>Enter</kbd>
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import api from "../../plugins/axios.js";
import { sizedImage } from "../../utils/cloudinaryImage.js";
import { recentlyViewed } from "../../utils/recentlyViewed.js";

const props = defineProps({
  modelValue: { type: String, default: "" },
  // [{ value, label }] from the shop's lists
  flowers: { type: Array, default: () => [] },
  occasions: { type: Array, default: () => [] },
  colors: { type: Array, default: () => [] },
});
// pick: { type: "product" | "flower" | "occasion" | "color" | "term", value, label, product? }
const emit = defineEmits(["update:modelValue", "submit", "clear", "pick"]);

const PER_GROUP = 4;
const rootEl = ref(null);
const inputEl = ref(null);
const open = ref(false);
const activeId = ref("");
const popular = ref(null); // loaded the first time the box opens
const popularLoading = ref(false);
const recent = ref([]);
const products = ref([]);
const productsLoading = ref(false);
const productsFor = ref(""); // the text the product list belongs to

const query = computed(() => props.modelValue.trim());
const showPanel = computed(() => open.value);

// Lower case, accents dropped: "Café" finds "cafe"
const plain = (t) => String(t ?? "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").trim();

// ── Items, in the order the arrow keys go through them ───────────────
const popularItems = computed(() => (popular.value || []).map((term, i) => ({ id: `ss-pop-${i}`, type: "term", value: term, label: term })));
const recentItems = computed(() =>
  recent.value.slice(0, 4).map((p) => ({ id: `ss-rec-${p.id}`, type: "product", value: p.id, label: p.product_name, product: p })),
);
const productItems = computed(() =>
  productsFor.value === query.value || !productsLoading.value
    ? products.value.map((p) => ({ id: `ss-prd-${p.id}`, type: "product", value: p.id, label: p.product_name, product: p }))
    : [],
);

/** Options whose label has every typed word; ones starting with it first */
function matches(list, type) {
  const q = plain(query.value);
  if (!q) return [];
  const words = q.split(/\s+/);
  return list
    .map((o) => ({ o, name: plain(o.label) }))
    .filter(({ name }) => words.every((w) => name.includes(w)))
    .sort((a, b) => Number(!a.name.startsWith(q)) - Number(!b.name.startsWith(q)) || a.name.localeCompare(b.name))
    .slice(0, PER_GROUP)
    .map(({ o }) => ({ id: `ss-${type}-${o.value}`, type, value: o.value, label: o.label }));
}
const matchGroups = computed(() =>
  [
    { key: "flower", title: "Flower names", kind: "Flower", items: matches(props.flowers, "flower") },
    { key: "occasion", title: "Occasions", kind: "Occasion", items: matches(props.occasions, "occasion") },
    { key: "color", title: "Colours", kind: "Colour", items: matches(props.colors, "color") },
  ].filter((g) => g.items.length),
);
const searchAllItem = computed(() => ({ id: "ss-all", type: "search", value: query.value, label: query.value }));

const flatItems = computed(() =>
  query.value
    ? [...productItems.value, ...matchGroups.value.flatMap((g) => g.items), searchAllItem.value]
    : [...popularItems.value, ...recentItems.value],
);

// ── Product suggestions: asked a moment after typing stops; only the
// answer for the latest text is used ───────────────────────────────────
let timer = 0;
let seq = 0;
watch(query, (text) => {
  clearTimeout(timer);
  activeId.value = "";
  if (!text) {
    seq++;
    products.value = [];
    productsFor.value = "";
    productsLoading.value = false;
    return;
  }
  productsLoading.value = true;
  timer = setTimeout(async () => {
    const mine = ++seq;
    try {
      const { data } = await api.get("customer/products", { params: { search: text, per_page: 5, page: 1, sort_by: "popular_desc", in_stock_only: 0 } });
      if (mine !== seq) return;
      products.value = Array.isArray(data?.data?.data) ? data.data.data.slice(0, 5) : [];
      productsFor.value = text;
    } catch {
      if (mine === seq) products.value = [];
    } finally {
      if (mine === seq) productsLoading.value = false;
    }
  }, 200);
});

async function loadPopular() {
  if (popular.value || popularLoading.value) return;
  popularLoading.value = true;
  try {
    const { data } = await api.get("customer/search/popular");
    popular.value = Array.isArray(data?.data) ? data.data : [];
  } catch {
    popular.value = [];
  } finally {
    popularLoading.value = false;
  }
}

function openPanel() {
  recent.value = recentlyViewed();
  open.value = true;
  loadPopular();
}
function closePanel() {
  open.value = false;
  activeId.value = "";
}

function onInput(event) {
  emit("update:modelValue", event.target.value);
  if (!open.value) openPanel();
}
function clear() {
  emit("clear");
  inputEl.value?.focus();
}

// Highlighted with the arrow keys (Enter follows it) or only under the mouse
// (Enter ignores it and searches, like "Search all flowers")
let chosenByKeys = false;
function hover(item) {
  activeId.value = item.id;
  chosenByKeys = false;
}

function pick(item) {
  closePanel();
  inputEl.value?.blur();
  if (item.type === "search") emit("submit");
  else emit("pick", item);
}

function onKey(event) {
  const items = flatItems.value;
  if (event.key === "ArrowDown" || event.key === "ArrowUp") {
    event.preventDefault();
    if (!open.value) return openPanel();
    if (!items.length) return;
    const at = items.findIndex((i) => i.id === activeId.value);
    const next = event.key === "ArrowDown" ? (at + 1) % items.length : at <= 0 ? items.length - 1 : at - 1;
    activeId.value = items[next].id;
    chosenByKeys = true;
    document.getElementById(activeId.value)?.scrollIntoView({ block: "nearest" });
  } else if (event.key === "Enter") {
    event.preventDefault();
    // Enter searches all flowers, unless something was picked with the arrow keys
    const item = chosenByKeys ? items.find((i) => i.id === activeId.value) : null;
    if (item) pick(item);
    else {
      closePanel();
      emit("submit");
    }
  } else if (event.key === "Escape") {
    if (open.value) {
      event.preventDefault();
      closePanel();
    }
  } else if (event.key === "Tab") {
    closePanel();
  }
}

// Clicking anywhere else closes it
function onDocDown(event) {
  if (open.value && rootEl.value && !rootEl.value.contains(event.target)) closePanel();
}
onMounted(() => document.addEventListener("pointerdown", onDocDown));
onBeforeUnmount(() => {
  document.removeEventListener("pointerdown", onDocDown);
  clearTimeout(timer);
});

// ── Display helpers ──────────────────────────────────────────────────
/** The label split so the typed words show in bold */
function mark(label) {
  const text = String(label ?? "");
  const words = plain(query.value).split(/\s+/).filter(Boolean);
  if (!words.length) return [{ text, hit: false }];
  const lower = plain(text);
  const hits = new Array(text.length).fill(false);
  for (const w of words) {
    let from = lower.indexOf(w);
    while (from !== -1) {
      for (let i = from; i < from + w.length && i < hits.length; i++) hits[i] = true;
      from = lower.indexOf(w, from + w.length);
    }
  }
  const parts = [];
  for (let i = 0; i < text.length; i++) {
    const last = parts[parts.length - 1];
    if (last && last.hit === hits[i]) last.text += text[i];
    else parts.push({ text: text[i], hit: hits[i] });
  }
  return parts;
}
const imageOf = (p) => p?.primary_image?.image_url || p?.images?.[0]?.image_url || null;
const priceOf = (p) => {
  const sale = p?.has_discount && Number(p.discount_price) > 0 && Number(p.discount_price) < Number(p.selling_price);
  return sale ? Number(p.discount_price) : Number(p?.selling_price || 0);
};
const peso = (v) => `₱${Number(v || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
function swatchFor(name) {
  const css = String(name || "").toLowerCase().replace(/[^a-z]/g, "");
  return css && typeof CSS !== "undefined" && CSS.supports("color", css) ? css : "conic-gradient(#f4a7b9, #f9e27d, #a7d8f4, #c7a7f4, #f4a7b9)";
}
</script>

<style scoped>
.ss {
  position: relative;
  flex: 1 1 260px;
  max-width: 460px;
}

.ss-field {
  position: relative;
  display: flex;
  align-items: center;
}

.ss-field > svg {
  position: absolute;
  left: 14px;
  width: 18px;
  height: 18px;
  fill: none;
  stroke: var(--ink-soft);
  stroke-width: 1.7;
  stroke-linecap: round;
  pointer-events: none;
}

.ss-field input {
  width: 100%;
  min-height: 44px;
  padding: 0 42px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: #ffffff;
  color: var(--ink);
  font: inherit;
  font-size: 14px;
  appearance: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.ss-field input::-webkit-search-cancel-button {
  display: none;
}

.ss-field input::placeholder {
  color: var(--ink-soft);
}

.ss-field input:focus {
  border-color: var(--leaf);
  outline: none;
  box-shadow: 0 0 0 4px rgba(77, 177, 115, 0.14);
}

.ss-clear {
  position: absolute;
  right: 6px;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 50%;
  background: none;
  color: var(--ink-soft);
  cursor: pointer;
}

.ss-clear:hover {
  background: rgba(54, 63, 78, 0.06);
  color: var(--ink);
}

.ss svg {
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ss-clear svg {
  width: 16px;
  height: 16px;
}

/* The panel under the box */
.ss-panel {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  z-index: 60;
  display: grid;
  gap: 4px;
  width: min(560px, calc(100vw - 32px));
  max-height: min(70vh, 560px);
  padding: 10px;
  overflow-y: auto;
  border: 1px solid var(--line);
  border-radius: 18px;
  background: #ffffff;
  box-shadow: 0 18px 48px rgba(30, 39, 57, 0.16), 0 2px 6px rgba(30, 39, 57, 0.06);
  animation: ss-in 0.14s ease-out;
}

@keyframes ss-in {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
}

.ss-sec {
  display: grid;
  gap: 4px;
  padding: 4px 0;
}

.ss-sec + .ss-sec {
  padding-top: 8px;
  border-top: 1px solid rgba(54, 63, 78, 0.07);
}

.ss-head {
  margin: 0;
  padding: 2px 8px;
  color: var(--ink-soft);
  font-family: var(--font-sans);
  font-size: 11px;
  font-weight: 600;
  line-height: 1.4;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.ss-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 2px 6px 4px;
}

.ss-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 34px;
  padding: 0 12px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: #ffffff;
  color: var(--ink);
  font: inherit;
  font-size: 13px;
  text-transform: capitalize;
  cursor: pointer;
  transition: border-color 0.12s ease, background 0.12s ease;
}

.ss-chip svg {
  width: 14px;
  height: 14px;
  color: var(--leaf-deep);
}

.ss-chip.active {
  border-color: var(--ink);
  background: rgba(54, 63, 78, 0.04);
}

.ss-chip-skel {
  width: 84px;
  height: 34px;
  border-radius: 999px;
}

/* Recently viewed: small cards in a row */
.ss-recent {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  padding: 2px 6px 4px;
}

.ss-card {
  display: grid;
  gap: 4px;
  padding: 6px;
  border: 1px solid transparent;
  border-radius: 14px;
  background: none;
  color: var(--ink);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.ss-card.active {
  border-color: var(--line);
  background: rgba(54, 63, 78, 0.04);
}

.ss-card-img {
  aspect-ratio: 1;
  overflow: hidden;
  border-radius: 10px;
  background: var(--ivory-deep);
}

.ss-card-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.ss-card-name {
  overflow: hidden;
  font-size: 12.5px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ss-card-price {
  color: var(--ink-soft);
  font-size: 12px;
}

/* Rows */
.ss-list {
  display: grid;
  gap: 2px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.ss-row {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  min-height: 52px;
  padding: 6px 8px;
  border: 0;
  border-radius: 12px;
  background: none;
  color: var(--ink);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.ss-row--small {
  min-height: 40px;
}

.ss-row.active {
  background: rgba(54, 63, 78, 0.05);
}

.ss-row--skel {
  cursor: default;
}

.ss-thumb {
  flex: none;
  width: 44px;
  height: 44px;
  overflow: hidden;
  border-radius: 10px;
  background: var(--ivory-deep);
}

.ss-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.ss-line {
  width: 55%;
  height: 12px;
  border-radius: 6px;
}

.ss-row-main {
  display: grid;
  flex: 1;
  min-width: 0;
}

.ss-row-main small {
  overflow: hidden;
  color: var(--ink-soft);
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ss-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: var(--ink-soft);
  font-size: 13.5px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* The words typed show in bold; the rest of the suggestion is quieter */
.ss-name b {
  color: var(--ink);
  font-weight: 600;
}

.ss-name:not(:has(b)) {
  color: var(--ink);
}

.ss-price {
  flex: none;
  font-size: 13px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.ss-icon {
  display: grid;
  flex: none;
  place-items: center;
  width: 30px;
  height: 30px;
  border-radius: 9px;
  background: rgba(54, 63, 78, 0.05);
  color: var(--leaf-deep);
}

.ss-icon svg {
  width: 16px;
  height: 16px;
}

.ss-icon i {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.12);
}

.ss-kind {
  flex: none;
  color: var(--ink-soft);
  font-size: 11.5px;
}

.ss-all {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  margin-top: 4px;
  padding: 0 10px;
  border: 0;
  border-top: 1px solid rgba(54, 63, 78, 0.07);
  border-radius: 0 0 10px 10px;
  background: none;
  color: var(--ink);
  font: inherit;
  font-size: 13.5px;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}

.ss-all svg {
  width: 16px;
  height: 16px;
  color: var(--ink-soft);
}

.ss-all.active {
  border-radius: 12px;
  background: rgba(54, 63, 78, 0.05);
}

.ss-all kbd {
  margin-left: auto;
  padding: 2px 6px;
  border: 1px solid var(--line);
  border-radius: 6px;
  color: var(--ink-soft);
  font: inherit;
  font-size: 11px;
}

.ss-note {
  margin: 0;
  padding: 14px 10px;
  color: var(--ink-soft);
  font-size: 13px;
  text-align: center;
}

.ss-note--tight {
  padding: 6px 8px 8px;
  text-align: left;
}

.ss :is(button):focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

@media (max-width: 640px) {
  .ss {
    max-width: none;
  }

  .ss-panel {
    width: 100%;
  }

  .ss-recent {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ss-all kbd {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ss-panel {
    animation: none;
  }
}
</style>
