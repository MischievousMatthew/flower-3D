<template>
  <ShopLayout title="Categories & Occasions" subtitle="Organize your flowers and bouquets so customers find them when they browse or shop for an occasion.">
    <div v-if="error && !products" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <div class="sp-seg kind-tabs" role="tablist" aria-label="Group by">
      <button type="button" role="tab" :aria-selected="kind === 'category'" :class="{ on: kind === 'category' }" @click="setKind('category')">
        Categories<span class="count">{{ taxonomies.categories.length }}</span>
      </button>
      <button type="button" role="tab" :aria-selected="kind === 'occasion'" :class="{ on: kind === 'occasion' }" @click="setKind('occasion')">
        Occasions<span class="count">{{ taxonomies.occasions.length }}</span>
      </button>
    </div>

    <p class="sp-hint kind-note">
      <template v-if="kind === 'category'">Each product sits in one category. The list is shared by every BloomCraft shop; to suggest a new one, contact support.</template>
      <template v-else>A product can be tagged with up to 2 occasions. Customers filter by these when they shop for an event.</template>
    </p>

    <div v-if="needsAttention" class="sp-banner warn">
      <span v-html="ICONS.alert"></span>
      {{ needsAttention }} {{ needsAttention === 1 ? "product has" : "products have" }} {{ kind === "category" ? "no category" : "no occasion" }}.
      <button type="button" @click="open(UNSET)">Sort {{ needsAttention === 1 ? "it" : "them" }}</button>
    </div>

    <div class="cat-layout">
      <!-- Groups -->
      <div class="cat-grid">
        <template v-if="!products">
          <div v-for="n in 8" :key="n" class="cat-card"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div><div class="cat-stack"><div v-for="m in 3" :key="m" class="bc-skeleton cat-dot"></div></div></div>
        </template>
        <button
          v-for="g in groups"
          v-else
          :key="g.value"
          type="button"
          class="cat-card"
          :class="{ on: active?.value === g.value, empty: !g.items.length }"
          @click="open(g)"
        >
          <span class="cat-icon" :style="{ background: g.color.bg, color: g.color.fg }" v-html="kind === 'category' ? ICONS.tag : ICONS.sparkle"></span>
          <strong>{{ g.label }}</strong>
          <span class="cat-count">{{ g.items.length }} {{ g.items.length === 1 ? "product" : "products" }}</span>
          <div v-if="g.items.length" class="cat-stack">
            <template v-for="p in g.items.slice(0, 4)" :key="p.id">
              <img v-if="p.image" :src="p.image" :alt="p.name" class="cat-dot" loading="lazy" />
              <span v-else class="cat-dot blank"></span>
            </template>
            <span v-if="g.items.length > 4" class="cat-more">+{{ g.items.length - 4 }}</span>
          </div>
          <span v-else class="cat-empty">Nothing here yet</span>
        </button>
      </div>

      <!-- Selected group -->
      <aside class="sp-card cat-panel" aria-live="polite">
        <template v-if="!active">
          <div class="sp-empty">
            <span class="sp-empty-icon" v-html="kind === 'category' ? ICONS.tag : ICONS.sparkle"></span>
            <h3>Pick a {{ kind }}</h3>
            <p>See which products are in it and add or move products in a couple of clicks.</p>
          </div>
        </template>
        <template v-else>
          <div class="sp-card-head">
            <div>
              <h2>{{ active.label }}</h2>
              <p>{{ activeItems.length }} {{ activeItems.length === 1 ? "product" : "products" }}<template v-if="active.description"> · {{ active.description }}</template></p>
            </div>
            <button v-if="active.value !== UNSET.value" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="openPicker"><span v-html="ICONS.plus"></span>Add products</button>
          </div>
          <div v-if="!activeItems.length" class="sp-empty small">
            <p>{{ active.value === UNSET.value ? "Everything is sorted." : "No products here yet." }}</p>
          </div>
          <ul v-else class="cat-items">
            <li v-for="p in activeItems" :key="p.id">
              <img v-if="p.image" :src="p.image" :alt="p.name" class="sp-thumb" loading="lazy" />
              <span v-else class="sp-thumb blank" v-html="ICONS.image"></span>
              <div class="cat-item-main">
                <strong>{{ p.name }}</strong>
                <span>
                  <template v-if="kind === 'category'">{{ p.occasion_tags.join(", ") || "No occasion" }}</template>
                  <template v-else>{{ categoryLabel(p.category) }}</template>
                </span>
              </div>
              <!-- Category: move elsewhere. Occasion: untag. -->
              <select
                v-if="kind === 'category'"
                class="sp-select move"
                :value="p.category ?? ''"
                :disabled="busy"
                :aria-label="`Move ${p.name} to another category`"
                @change="move(p, $event)"
              >
                <option value="" disabled>Choose…</option>
                <option v-for="c in taxonomies.categories" :key="c.value" :value="c.value">{{ c.label }}</option>
              </select>
              <template v-else-if="active.value === UNSET.value">
                <select class="sp-select move" value="" :disabled="busy" :aria-label="`Tag ${p.name}`" @change="tag(p, $event)">
                  <option value="" disabled>Tag…</option>
                  <option v-for="o in taxonomies.occasions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </template>
              <button v-else type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busy" @click="untag(p)">Remove</button>
            </li>
          </ul>
        </template>
      </aside>
    </div>

    <!-- Product picker -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="picking" class="sp-backdrop" @click="picking = false"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="picking" class="sp-modal-wrap">
          <div class="sp-modal wide" role="dialog" aria-modal="true" aria-labelledby="pick-title">
            <div class="sp-modal-head">
              <div>
                <h3 id="pick-title">Add to {{ active.label }}</h3>
                <p v-if="kind === 'category'">Products move here from their current category.</p>
                <p v-else>Products that already have 2 occasions can't take another one.</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="picking = false"><span v-html="ICONS.close"></span></button>
            </div>
            <label class="sp-search pick-search">
              <span v-html="ICONS.search"></span>
              <input v-model="pickSearch" class="sp-input" type="search" placeholder="Search products" aria-label="Search products" />
            </label>
            <ul class="pick-list">
              <li v-for="p in pickable" :key="p.id">
                <label :class="{ disabled: full(p) }">
                  <input type="checkbox" :value="p.id" v-model="picked" :disabled="full(p)" />
                  <img v-if="p.image" :src="p.image" :alt="p.name" class="sp-thumb" loading="lazy" />
                  <span v-else class="sp-thumb blank" v-html="ICONS.image"></span>
                  <span class="cat-item-main">
                    <strong>{{ p.name }}</strong>
                    <span>{{ kind === "category" ? categoryLabel(p.category) : p.occasion_tags.join(", ") || "No occasion" }}</span>
                  </span>
                </label>
              </li>
              <li v-if="!pickable.length" class="pick-none">Every product is already here.</li>
            </ul>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="picking = false">Cancel</button>
              <button type="button" class="sp-btn sp-btn-primary" :disabled="!picked.length || busy" @click="addPicked">
                {{ busy ? "Saving…" : `Add ${picked.length || ""} ${picked.length === 1 ? "product" : "products"}` }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage, useVendorCatalog } from "../../../components/vendor/shop/useVendorCatalog";
import "../../../components/vendor/shop/shop.css";

const { products, error, load, taxonomies, categoryLabel } = useVendorCatalog();

const UNSET = { value: "__none__", label: "Not sorted yet", description: null };
const PALETTE = [
  { bg: "#e6f9ee", fg: "#22753f" }, { bg: "#fff0f6", fg: "#b83280" }, { bg: "#ebf4ff", fg: "#2b6cb0" },
  { bg: "#fff6e5", fg: "#b45309" }, { bg: "#f3f0ff", fg: "#6b46c1" }, { bg: "#e6fffa", fg: "#2c7a7b" },
];

const kind = ref("category");
const activeValue = ref(null);
const busy = ref(false);
const picking = ref(false);
const picked = ref([]);
const pickSearch = ref("");

const list = computed(() => (kind.value === "category" ? taxonomies.categories : taxonomies.occasions));
const inGroup = (p, value) => {
  if (value === UNSET.value) {
    return kind.value === "category"
      ? !p.category || !taxonomies.categories.some((c) => c.value === p.category)
      : !p.occasion_tags.length;
  }
  return kind.value === "category" ? p.category === value : p.occasion_tags.includes(value);
};
const groups = computed(() =>
  list.value.map((item, i) => ({
    ...item,
    color: PALETTE[i % PALETTE.length],
    items: (products.value ?? []).filter((p) => inGroup(p, item.value)),
  })),
);
const needsAttention = computed(() => (products.value ?? []).filter((p) => inGroup(p, UNSET.value)).length);
const active = computed(() =>
  activeValue.value === UNSET.value ? UNSET : list.value.find((item) => item.value === activeValue.value) ?? null,
);
const activeItems = computed(() => (active.value ? (products.value ?? []).filter((p) => inGroup(p, active.value.value)) : []));

const full = (p) => kind.value === "occasion" && p.occasion_tags.length >= 2;
const pickable = computed(() => {
  const q = pickSearch.value.trim().toLowerCase();
  return (products.value ?? [])
    .filter((p) => !inGroup(p, active.value.value) && (!q || p.name.toLowerCase().includes(q)))
    .sort((a, b) => Number(full(a)) - Number(full(b)) || a.name.localeCompare(b.name));
});

function setKind(k) {
  kind.value = k;
  activeValue.value = null;
}
function open(group) {
  activeValue.value = group.value;
}
function openPicker() {
  picked.value = [];
  pickSearch.value = "";
  picking.value = true;
}

async function run(ids, action, value, success) {
  if (busy.value) return false;
  busy.value = true;
  try {
    const { data } = await api.post("vendor/catalog/products/bulk", { ids, action, value });
    toast.success(success ?? data.message);
    await load();
    return true;
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't update the product."));
    return false;
  } finally {
    busy.value = false;
  }
}
const labelFor = (value) => list.value.find((x) => x.value === value)?.label ?? value;
function move(p, event) {
  run([p.id], "set_category", event.target.value, `${p.name} moved to ${labelFor(event.target.value)}.`);
}
function tag(p, event) {
  run([p.id], "add_occasion", event.target.value, `${p.name} tagged for ${labelFor(event.target.value)}.`);
}
function untag(p) {
  run([p.id], "remove_occasion", active.value.value, `${p.name} removed from ${active.value.label}.`);
}
async function addPicked() {
  const ok = await run(picked.value, kind.value === "category" ? "set_category" : "add_occasion", active.value.value);
  if (ok) picking.value = false;
}

onMounted(load);
</script>

<style scoped>
.kind-tabs {
  margin-bottom: 10px;
}
.kind-note {
  margin: 0 0 16px;
  font-size: 12.5px;
}
.cat-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 380px;
  gap: 16px;
  align-items: start;
}
.cat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 12px;
}
.cat-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 16px;
  border: 1px solid #edf1ef;
  border-radius: 16px;
  background: #fff;
  font-family: "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
}
.cat-card:hover {
  border-color: #c6f6d5;
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(16, 24, 40, 0.06);
}
.cat-card.on {
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.16);
}
.cat-card.empty strong {
  color: #718096;
}
.cat-icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  margin-bottom: 6px;
  border-radius: 11px;
}
.cat-icon :deep(svg) {
  width: 17px;
  height: 17px;
}
.cat-card strong {
  font-size: 14px;
  font-weight: 600;
  color: #1a202c;
}
.cat-count {
  font-size: 12px;
  color: #718096;
}
.cat-stack {
  display: flex;
  align-items: center;
  margin-top: 8px;
}
.cat-dot {
  width: 28px;
  height: 28px;
  margin-right: -8px;
  border: 2px solid #fff;
  border-radius: 50%;
  object-fit: cover;
  background: #f0fff4;
}
.cat-dot.blank {
  display: inline-block;
}
.cat-more {
  margin-left: 14px;
  font-size: 11.5px;
  font-weight: 600;
  color: #718096;
}
.cat-empty {
  margin-top: 8px;
  font-size: 11.5px;
  color: #a0aec0;
}
.cat-panel {
  position: sticky;
  top: 16px;
  max-height: calc(100vh - 32px);
  overflow-y: auto;
}
.cat-items,
.pick-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.cat-items li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 2px;
  border-bottom: 1px solid #f4f6f5;
}
.cat-items li:last-child {
  border-bottom: 0;
}
.cat-item-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.cat-item-main strong {
  font-size: 13px;
  font-weight: 500;
  color: #1a202c;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cat-item-main span {
  font-size: 11.5px;
  color: #a0aec0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.move {
  width: 130px;
  padding: 6px 8px;
  font-size: 12px;
}
.sp-empty.small {
  padding: 20px 10px;
}
.pick-search {
  display: block;
  margin-bottom: 10px;
}
.pick-list {
  max-height: 46vh;
  overflow-y: auto;
  border: 1px solid #edf2f7;
  border-radius: 13px;
}
.pick-list label {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-bottom: 1px solid #f4f6f5;
  cursor: pointer;
}
.pick-list li:last-child label {
  border-bottom: 0;
}
.pick-list label:hover {
  background: #f8fcf9;
}
.pick-list label.disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.pick-list input {
  accent-color: #48bb78;
  width: 16px;
  height: 16px;
}
.pick-none {
  padding: 20px;
  text-align: center;
  font-size: 13px;
  color: #a0aec0;
}
@media (max-width: 1100px) {
  .cat-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .cat-panel {
    position: static;
    max-height: none;
  }
}
@media (max-width: 520px) {
  .cat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
