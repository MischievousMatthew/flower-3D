<template>
  <ShopLayout title="Inventory" subtitle="Stock on hand, quick adjustments with a reason, and alerts before you run out.">
    <template #actions>
      <router-link v-if="tab === 'products'" :to="{ name: 'VendorAddProduct' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.plus"></span>Add product</router-link>
      <router-link v-else :to="{ name: 'VendorCustomization' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.plus"></span>Add in 3D Bouquet Management</router-link>
    </template>

    <!-- What to show: the products, or the wrappers, fillers and ribbons the shop uses -->
    <nav class="inv-tabs" role="tablist" aria-label="Inventory sections">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        role="tab"
        :aria-selected="tab === t.key"
        :class="{ on: tab === t.key }"
        @click="setTab(t.key)"
      >
        {{ t.label }}<span v-if="products" class="count">{{ t.count }}</span>
      </button>
    </nav>

    <div v-if="error && !products" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Summary -->
    <section class="sp-stats" aria-label="Stock summary">
      <template v-if="!products">
        <div v-for="n in 4" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <button type="button" class="sp-stat" :class="{ on: filter === 'all' && !search }" :title="`Show all ${tabInfo.noun}`" @click="showAll">
          <span class="sp-stat-icon tone-green" v-html="ICONS.box"></span>
          <div><strong>{{ totals.units.toLocaleString() }}</strong><span>{{ tab === "products" ? "Items" : "Left" }} in stock</span></div>
        </button>
        <div class="sp-stat">
          <span class="sp-stat-icon tone-blue" v-html="ICONS.tag"></span>
          <div><strong>{{ money(totals.value) }}</strong><span>Stock value at cost</span></div>
        </div>
        <button type="button" class="sp-stat" :class="{ on: filter === 'low' }" @click="filter = 'low'">
          <span class="sp-stat-icon tone-amber" v-html="ICONS.alert"></span>
          <div><strong>{{ counts.low }}</strong><span>Running low</span></div>
        </button>
        <button type="button" class="sp-stat" :class="{ on: filter === 'out' }" @click="filter = 'out'">
          <span class="sp-stat-icon tone-red" v-html="ICONS.minus"></span>
          <div><strong>{{ counts.out }}</strong><span>Out of stock</span></div>
        </button>
      </template>
    </section>

    <div class="inv-layout">
      <!-- Stock table -->
      <section ref="tableCard" class="sp-card inv-table-card" data-inv-table aria-label="Stock levels">
        <div class="sp-toolbar inv-toolbar">
          <div class="sp-seg" role="tablist" aria-label="Filter by stock">
            <button v-for="f in FILTERS" :key="f.key" type="button" role="tab" :aria-selected="filter === f.key" :class="{ on: filter === f.key }" @click="filter = f.key">
              {{ f.label }}<span v-if="products" class="count">{{ counts[f.key] }}</span>
            </button>
          </div>
          <label class="sp-search">
            <span v-html="ICONS.search"></span>
            <input v-model="search" class="sp-input" type="search" :placeholder="`Search ${tabInfo.noun}`" :aria-label="`Search ${tabInfo.noun}`" />
          </label>
        </div>

        <div v-if="!products" class="inv-rows">
          <div v-for="n in 6" :key="n" class="inv-row"><div class="bc-skeleton sp-thumb"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w70"></div></div></div>
        </div>
        <div v-else-if="!rows.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.check"></span>
          <h3>{{ tabItems.length ? "Nothing here" : `No ${tabInfo.noun} yet` }}</h3>
          <p>{{ tabItems.length ? (filter === "all" ? `No ${tabInfo.noun} match your search.` : `No ${tabInfo.noun} in this group right now.`) : tabInfo.empty }}</p>
          <router-link v-if="!tabItems.length && tab !== 'products'" :to="{ name: 'VendorCustomization' }" class="sp-btn sp-btn-ghost sp-btn-sm">Open 3D Bouquet Management</router-link>
        </div>
        <ul v-else class="inv-rows">
          <li v-for="p in rows" :key="p.id" class="inv-row" :data-change="`stock_${p.id}`" :class="{ focus: historyFor?.id === p.id, pending: pending[p.id] }">
            <img v-if="p.image" :src="p.image" :alt="p.name" class="sp-thumb" loading="lazy" />
            <span v-else class="sp-thumb blank" v-html="ICONS.image"></span>
            <div class="inv-main">
              <div class="inv-name">
                <strong>{{ p.name }}</strong>
                <span class="sp-chip" :class="STATE[p.stock_state].tone">{{ STATE[p.stock_state].label }}</span>
                <span v-if="p.status !== 'active'" class="sp-chip slate">{{ p.status === "draft" ? "Draft" : "Archived" }}</span>
                <span v-if="p.material && tab === 'other'" class="sp-chip slate" title="A material the shop uses, not sold on its own">{{ p.stock_kind === "wrapping" ? "Wrapping" : "Material" }}</span>
              </div>
              <div class="inv-meter" :title="meterTitle(p)">
                <span :class="p.stock_state" :style="{ width: `${meter({ ...p, stock: shown(p) })}%` }"></span>
                <i v-if="p.min_stock" :style="{ left: `${minMark(p)}%` }"></i>
              </div>
              <div class="inv-sub">
                <span>
                  <strong>{{ shown(p) }}</strong> {{ unitLabel(p, shown(p)) }}in stock
                  <em v-if="p.pack_size" class="inv-packs">{{ packText(p, shown(p)) }} · {{ p.pack_size }} per {{ p.pack_unit }}</em>
                  <em v-if="pending[p.id]" class="inv-pending" :class="pending[p.id] > 0 ? 'up' : 'down'" :title="`Was ${p.stock}. Not saved yet`">
                    {{ pending[p.id] > 0 ? "+" : "−" }}{{ Math.abs(pending[p.id]) }} not saved
                  </em>
                </span>
                <span>Alert at {{ p.min_stock ?? 0 }}</span>
                <span v-if="p.max_stock">Max {{ p.max_stock }}</span>
              </div>
            </div>
            <p v-if="p.stock_from_flowers" class="inv-linked">Stock comes from its linked flowers. Change the flowers' stock instead.</p>
            <div v-else class="inv-quick" role="group" :aria-label="`Quick change for ${p.name}`">
              <button type="button" class="sp-icon-btn" :disabled="shown(p) <= 0 || savingStock" :aria-label="`Remove one ${p.name}`" @click="quick(p, -1)"><span v-html="ICONS.minus"></span></button>
              <button type="button" class="sp-icon-btn" :disabled="savingStock" :aria-label="`Add one ${p.name}`" @click="quick(p, 1)"><span v-html="ICONS.plus"></span></button>
            </div>
            <div class="inv-actions">
              <button v-if="!p.stock_from_flowers" type="button" class="sp-btn sp-btn-dark sp-btn-sm" @click="openAdjust(p)">Adjust</button>
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="openLevels(p)">Alerts</button>
              <button type="button" class="sp-icon-btn" :class="{ on: historyFor?.id === p.id }" :aria-label="`History for ${p.name}`" @click="showHistory(p)"><span v-html="ICONS.history"></span></button>
            </div>
          </li>
        </ul>
      </section>

      <!-- History -->
      <aside class="sp-card inv-history" aria-label="Stock history">
        <div class="sp-card-head">
          <div>
            <h2>{{ historyFor ? historyFor.name : "Recent changes" }}</h2>
            <p>{{ historyFor ? "Manual stock changes for this product" : "Manual stock changes across your shop" }}</p>
          </div>
          <button v-if="historyFor" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="showHistory(null)">All</button>
        </div>
        <ul v-if="history === null" class="hist">
          <li v-for="n in 5" :key="n"><div class="grow"><div class="bc-skeleton sp-sk-line w70"></div><div class="bc-skeleton sp-sk-line w50"></div></div></li>
        </ul>
        <p v-else-if="historyError" class="sp-error">{{ historyError }}</p>
        <div v-else-if="!history.length" class="sp-empty small">
          <span class="sp-empty-icon" v-html="ICONS.history"></span>
          <p>No changes yet. Each adjustment you make is listed here with its reason.</p>
        </div>
        <ul v-else class="hist">
          <li v-for="h in history" :key="h.key">
            <span class="hist-change" :class="h.change > 0 ? 'up' : 'down'">{{ h.change > 0 ? "+" : "" }}{{ h.change }}</span>
            <div class="hist-main">
              <strong>{{ historyFor ? h.reason_label : h.product_name }}</strong>
              <span>
                <template v-if="!historyFor">{{ h.reason_label }} · </template>{{ h.before }} → {{ h.after }} · {{ when(h.created_at) }}
              </span>
              <em v-if="h.note">“{{ h.note }}”</em>
            </div>
          </li>
        </ul>
      </aside>
    </div>

    <!-- Adjust modal -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="adjusting" class="sp-backdrop" @click="adjusting = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="adjusting" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="adj-title" @submit.prevent="submitAdjust">
            <div class="sp-modal-head">
              <div>
                <h3 id="adj-title">Adjust stock</h3>
                <p>{{ adjusting.name }} · {{ adjusting.stock }} {{ unitWord(adjusting, adjusting.stock) }} in stock now</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="adjusting = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="adj-modes">
              <button v-for="m in MODES" :key="m.key" type="button" class="sp-pick" :class="{ on: adj.mode === m.key }" @click="setMode(m.key)">{{ m.label }}</button>
            </div>
            <!-- Bought in rolls: count in sheets or in rolls -->
            <div v-if="adjusting.pack_size" class="adj-modes">
              <button type="button" class="sp-pick" :class="{ on: adj.unit === 'piece' }" @click="adj.unit = 'piece'">{{ unitWord(adjusting, 2) }}</button>
              <button type="button" class="sp-pick" :class="{ on: adj.unit === 'pack' }" @click="adj.unit = 'pack'">{{ plural(adjusting.pack_unit, 2) }} of {{ adjusting.pack_size }}</button>
            </div>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span>{{ adj.mode === "set" ? "New stock count" : "Quantity" }}<template v-if="adjusting.pack_size"> ({{ adj.unit === "pack" ? plural(adjusting.pack_unit, 2) : unitWord(adjusting, 2) }})</template></span>
                <input v-model.number="adj.quantity" type="number" min="0" step="1" class="sp-input" required />
                <small v-if="adjusting.pack_size && adj.unit === 'pack' && adj.quantity > 0">= {{ adj.quantity * adjusting.pack_size }} {{ unitWord(adjusting, 2) }}</small>
              </label>
              <label class="sp-field">
                <span>Reason</span>
                <select v-model="adj.reason" class="sp-select" required>
                  <option v-for="r in reasons" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
              </label>
              <label class="sp-field sp-span-all">
                <span>Note <small>(optional)</small></span>
                <input v-model.trim="adj.note" class="sp-input" maxlength="500" placeholder="e.g. Delivery from supplier" />
              </label>
            </div>
            <div class="adj-preview" :class="{ bad: adjAfter < 0 }">
              <span>{{ adjusting.stock }}</span><span class="arrow">→</span><strong>{{ adjAfter < 0 ? "Not enough stock" : adjAfter }}</strong>
            </div>
            <p v-if="adjError" class="sp-error">{{ adjError }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="adjusting = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="adjBusy || adjAfter < 0 || adjAfter === adjusting.stock">{{ adjBusy ? "Saving…" : "Save" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Alert levels modal -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="leveling" class="sp-backdrop" @click="leveling = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="leveling" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="lvl-title" @submit.prevent="submitLevels">
            <div class="sp-modal-head">
              <div>
                <h3 id="lvl-title">Stock alerts</h3>
                <p>{{ leveling.name }}</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="leveling = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span>Warn me at</span>
                <input v-model.number="levels.min" type="number" min="0" step="1" class="sp-input" required />
                <small>Shows as “Running low” at or below this</small>
              </label>
              <label class="sp-field">
                <span>Maximum to keep <small>(optional)</small></span>
                <input v-model.number="levels.max" type="number" :min="levels.min || 0" step="1" class="sp-input" />
                <small>Used for the stock bar</small>
              </label>
            </div>
            <p v-if="levelsError" class="sp-error">{{ levelsError }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="leveling = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="levelsBusy">{{ levelsBusy ? "Saving…" : "Save" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "@/utils/snackbar";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage, money, useVendorCatalog } from "../../../components/vendor/shop/useVendorCatalog";
import { useUnsavedChanges } from "../../../composables/useUnsavedChanges";
import "../../../components/vendor/shop/shop.css";

const { products, reasons, error, load, replace } = useVendorCatalog();

const FILTERS = [
  { key: "all", label: "All" },
  { key: "low", label: "Low" },
  { key: "out", label: "Out" },
  { key: "ok", label: "Healthy" },
];
const STATE = {
  ok: { label: "In stock", tone: "green" },
  low: { label: "Running low", tone: "amber" },
  out: { label: "Out of stock", tone: "red" },
};
const MODES = [
  { key: "add", label: "Add stock" },
  { key: "remove", label: "Remove stock" },
  { key: "set", label: "Set exact count" },
];

const filter = ref("all");
const search = ref("");
const tableCard = ref(null);

// "Items in stock" card: list every product and bring the list into view
const showAll = () => {
  filter.value = "all";
  search.value = "";
  tableCard.value?.scrollIntoView({ behavior: "smooth", block: "start" });
};
const history = ref(null);
const historyError = ref("");
const historyFor = ref(null);

// Materials (wrapping…) are counted here too: the same stock items as ERP Stock
// & replenishment and 3D Bouquet Management, so all three show one number
const materials = ref([]);
const loadMaterials = () =>
  api
    .get("vendor/catalog/materials")
    .then(({ data }) => (materials.value = data.data ?? []))
    .catch(() => {}); // the products still show
const allItems = computed(() => (products.value ? [...products.value, ...materials.value] : null));

// ─── Sections: Products, and the wrappers, fillers and ribbons from 3D Bouquet Management ───
const TABS = {
  products: { label: "Products", noun: "products", empty: "Add a product and its stock shows up here." },
  wrappers: { label: "Wrappers", noun: "wrappers", empty: "Wrappers show here once their sheets are counted in 3D Bouquet Management." },
  fillers: { label: "Fillers", noun: "fillers", empty: "Fillers show here once their sacks are counted in 3D Bouquet Management." },
  ribbons: { label: "Ribbons", noun: "ribbons", empty: "Ribbons show here once their rolls are counted in 3D Bouquet Management." },
  other: { label: "Other materials", noun: "materials", empty: "Other materials you count in ERP Stock show here." },
};
const KIND_TAB = { wrapping: "wrappers", greenery: "fillers", ribbons: "ribbons" };
const tabOf = (p) => (!p.material ? "products" : KIND_TAB[p.stock_kind] || "other");

const route = useRoute();
const router = useRouter();
const tab = ref(TABS[route.query.tab] ? String(route.query.tab) : "products");
function setTab(key) {
  tab.value = key;
  filter.value = "all";
  search.value = "";
  historyFor.value = null;
  // Kept in the address, so a refresh or a shared link opens the same section
  router.replace({ query: { ...route.query, tab: key === "products" ? undefined : key } }).catch(() => {});
}
const tabInfo = computed(() => TABS[tab.value]);
const tabs = computed(() =>
  Object.entries(TABS)
    .map(([key, t]) => ({ key, label: t.label, count: (allItems.value ?? []).filter((p) => tabOf(p) === key).length }))
    // "Other materials" only when the shop has some (vases, supplies… from ERP Stock)
    .filter((t) => t.key !== "other" || t.count > 0 || tab.value === "other"),
);
const tabItems = computed(() => (allItems.value ?? []).filter((p) => tabOf(p) === tab.value));
const items = computed(() => (allItems.value ? tabItems.value : null));
/** Put an item returned by the API back in its list */
function replaceItem(p) {
  if (!p.material) return replace(p);
  const i = materials.value.findIndex((m) => m.id === p.id);
  if (i >= 0) materials.value.splice(i, 1, p);
}
const plural = (word, n) => (n === 1 || /s$/i.test(String(word || "")) ? word : `${word}s`);
/** What it's counted in: "sheets" for a wrapper, nothing extra for products */
// A filler or ribbon is counted in "portions" behind the scenes; shops just see units (and sacks / rolls)
const unitWord = (p, n) => (p.unit === "portion" ? plural("unit", n) : p.unit ? plural(p.unit, n) : n === 1 ? "item" : "items");
/** "sheets " before "in stock"; nothing for products, or for fillers and ribbons (just "60 in stock") */
const unitLabel = (p, n) => (p.unit && p.unit !== "portion" ? `${unitWord(p, n)} ` : "");
/** 95 sheets of 50 per roll → "≈ 1 roll + 45 sheets" */
function packText(p, n) {
  const packs = Math.floor(n / p.pack_size);
  const rest = n % p.pack_size;
  if (!packs) return `less than 1 ${p.pack_unit}`;
  return `≈ ${packs} ${plural(p.pack_unit, packs)}${rest ? ` + ${rest} ${unitLabel(p, rest)}`.trimEnd() : ""}`;
}

const counts = computed(() => {
  const list = items.value ?? [];
  return {
    all: list.length,
    low: list.filter((p) => p.stock_state === "low").length,
    out: list.filter((p) => p.stock_state === "out").length,
    ok: list.filter((p) => p.stock_state === "ok").length,
  };
});
const totals = computed(() => ({
  units: (items.value ?? []).reduce((n, p) => n + Math.max(0, p.stock), 0),
  value: (items.value ?? []).reduce((n, p) => n + (p.stock_value || 0), 0),
}));
const rows = computed(() => {
  const q = search.value.trim().toLowerCase();
  const order = { out: 0, low: 1, ok: 2 };
  return (items.value ?? [])
    .filter((p) => (filter.value === "all" || p.stock_state === filter.value) && (!q || p.name.toLowerCase().includes(q) || (p.sku ?? "").toLowerCase().includes(q)))
    .sort((a, b) => order[a.stock_state] - order[b.stock_state] || a.stock - b.stock);
});

// Bar: stock against the max (or twice the alert level), with a tick at the alert level
const scale = (p) => Math.max(p.max_stock || 0, (p.min_stock || 0) * 2, p.stock, 1);
const meter = (p) => Math.min(100, (Math.max(0, p.stock) / scale(p)) * 100);
const minMark = (p) => Math.min(100, ((p.min_stock || 0) / scale(p)) * 100);
const meterTitle = (p) => `${p.stock} in stock${p.min_stock ? `, alert at ${p.min_stock}` : ""}${p.max_stock ? `, max ${p.max_stock}` : ""}`;

function when(iso) {
  const d = new Date(iso);
  const mins = Math.round((Date.now() - d) / 60000);
  if (mins < 1) return "just now";
  if (mins < 60) return `${mins} min ago`;
  if (mins < 60 * 24) return `${Math.round(mins / 60)} h ago`;
  return d.toLocaleDateString("en-PH", { month: "short", day: "numeric" });
}

async function loadHistory() {
  historyError.value = "";
  history.value = null;
  try {
    const { data } = await api.get("vendor/catalog/stock-history", {
      params: { product_id: historyFor.value?.id, limit: 40 },
    });
    history.value = data.data;
  } catch (e) {
    historyError.value = apiMessage(e, "Couldn't load the history.");
    history.value = [];
  }
}
function showHistory(p) {
  historyFor.value = p && historyFor.value?.id !== p.id ? p : null;
  loadHistory();
}

// Adjust
const adjusting = ref(null);
const adj = reactive({ mode: "add", unit: "piece", quantity: 1, reason: "restock", note: "" });
const adjBusy = ref(false);
const adjError = ref("");
const adjAfter = computed(() => {
  if (!adjusting.value) return 0;
  const q = (Number(adj.quantity) || 0) * (adj.unit === "pack" ? adjusting.value.pack_size || 1 : 1);
  return adj.mode === "add" ? adjusting.value.stock + q : adj.mode === "remove" ? adjusting.value.stock - q : q;
});
function openAdjust(p) {
  adjusting.value = p;
  Object.assign(adj, { mode: "add", unit: "piece", quantity: 1, reason: "restock", note: "" });
  adjError.value = "";
}
function setMode(mode) {
  adj.mode = mode;
  adj.reason = { add: "restock", remove: "damaged", set: "count" }[mode];
  if (mode === "set") {
    adj.unit = "piece";
    adj.quantity = adjusting.value.stock;
  }
}
async function sendAdjust(p, body) {
  const { data } = await api.post(`vendor/catalog/products/${p.id}/stock`, body);
  replaceItem(data.data);
  if (!historyFor.value || historyFor.value.id === p.id) {
    history.value = [data.adjustment, ...(history.value ?? [])].slice(0, 40);
  }
  return data;
}
async function submitAdjust() {
  if (adjBusy.value) return;
  adjBusy.value = true;
  adjError.value = "";
  try {
    await sendAdjust(adjusting.value, { ...adj, unit: adjusting.value.pack_size ? adj.unit : null, note: adj.note || null });
    toast.success(`${adjusting.value.name}: ${adjAfter.value} in stock.`);
    adjusting.value = null;
  } catch (e) {
    adjError.value = apiMessage(e, "Couldn't update the stock.");
  } finally {
    adjBusy.value = false;
  }
}
// − / + add up per product until Save in the bar at the bottom
// (more = restocked, fewer = sold in store, as the quick buttons always meant)
const pending = reactive({}); // product id → change not saved yet
const savingStock = ref(false);
const shown = (p) => p.stock + (pending[p.id] || 0);

function quick(p, delta) {
  if (savingStock.value || shown(p) + delta < 0) return;
  const next = (pending[p.id] || 0) + delta;
  if (next) pending[p.id] = next;
  else delete pending[p.id];
}

async function saveStock() {
  if (savingStock.value) return;
  savingStock.value = true;
  let saved = 0;
  try {
    for (const [id, delta] of Object.entries(pending)) {
      const p = items.value?.find((x) => String(x.id) === id);
      if (!p) {
        delete pending[id];
        continue;
      }
      try {
        await sendAdjust(p, delta > 0
          ? { mode: "add", quantity: delta, reason: "restock" }
          : { mode: "remove", quantity: -delta, reason: "sold_offline" });
        delete pending[id];
        saved++;
      } catch (e) {
        // This one stays unsaved; the rest carry on
        toast.error(`${p.name}: ${apiMessage(e, "couldn't update the stock.")}`);
      }
    }
    if (saved) toast.success(saved === 1 ? "Stock updated." : `Stock updated for ${saved} products.`);
  } finally {
    savingStock.value = false;
  }
}

const { markClean } = useUnsavedChanges({
  fields: () =>
    Object.entries(pending).map(([id, delta]) => {
      const p = items.value?.find((x) => String(x.id) === id);
      return { key: `stock_${id}`, label: `${p?.name ?? "Product"} (${delta > 0 ? "+" : "−"}${Math.abs(delta)})`, value: delta, fallback: "[data-inv-table]" };
    }),
  save: saveStock,
  discard: () => Object.keys(pending).forEach((id) => delete pending[id]),
  saving: savingStock,
});
// Nothing is waiting when the page opens; anything pressed after that counts
onMounted(markClean);

// Alert levels
const leveling = ref(null);
const levels = reactive({ min: 0, max: null });
const levelsBusy = ref(false);
const levelsError = ref("");
function openLevels(p) {
  leveling.value = p;
  levels.min = p.min_stock ?? 0;
  levels.max = p.max_stock;
  levelsError.value = "";
}
async function submitLevels() {
  if (levelsBusy.value) return;
  levelsBusy.value = true;
  levelsError.value = "";
  try {
    const { data } = await api.patch(`vendor/catalog/products/${leveling.value.id}/stock-levels`, {
      min_stock_level: levels.min || 0,
      max_stock_level: levels.max === "" || levels.max === null ? null : levels.max,
    });
    replaceItem(data.data);
    toast.success(data.message);
    leveling.value = null;
  } catch (e) {
    levelsError.value = apiMessage(e, "Couldn't save the alert levels.");
  } finally {
    levelsBusy.value = false;
  }
}

onMounted(() => {
  load();
  loadMaterials();
  loadHistory();
});
</script>

<style scoped>
.grow {
  flex: 1;
}

/* Sections: Products · Wrappers · Fillers · Ribbons (underlined tabs, scroll sideways on a phone) */
.inv-tabs {
  display: flex;
  gap: 4px;
  margin-bottom: 18px;
  border-bottom: 1px solid var(--v-border);
  overflow-x: auto;
  scrollbar-width: none;
}
.inv-tabs::-webkit-scrollbar {
  display: none;
}
.inv-tabs button {
  display: inline-flex;
  flex: none;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  margin-bottom: -1px;
  padding: 0 14px;
  border: 0;
  border-bottom: 2px solid transparent;
  background: none;
  color: var(--v-text-2);
  font-family: "Poppins", sans-serif;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
}
.inv-tabs button:hover {
  color: var(--v-text);
}
.inv-tabs button.on {
  border-bottom-color: var(--bc-accent);
  color: var(--v-text);
  font-weight: 600;
}
.inv-tabs button:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: -2px;
  border-radius: 8px 8px 0 0;
}
.inv-tabs .count {
  padding: 1px 7px;
  border-radius: 999px;
  background: var(--v-subtle-2, rgba(0, 0, 0, 0.06));
  color: var(--v-text-2);
  font-size: 11.5px;
  font-weight: 500;
}
.inv-tabs button.on .count {
  background: var(--bc-accent-muted);
  color: var(--bc-accent-strong);
}
@media (prefers-reduced-motion: reduce) {
  .inv-tabs button {
    transition: none;
  }
}
.inv-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 16px;
  align-items: start;
}
.inv-table-card {
  padding: 16px;
}
.inv-toolbar {
  margin-bottom: 8px;
}
.inv-rows {
  list-style: none;
  margin: 0;
  padding: 0;
}
.inv-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 8px;
  border-bottom: 1px solid var(--v-border);
  border-radius: 12px;
  transition: background 0.15s;
}
.inv-row:last-child {
  border-bottom: 0;
}
.inv-row:hover,
.inv-row.focus {
  background: var(--v-subtle);
}
.inv-main {
  flex: 1;
  min-width: 0;
}
.inv-name {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.inv-name strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  margin-right: 2px;
}
.inv-meter {
  position: relative;
  height: 6px;
  margin: 8px 0 6px;
  border-radius: 999px;
  background: var(--v-subtle-2);
  max-width: 360px;
}
.inv-meter span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #48bb78;
  transition: width 0.3s;
}
.inv-meter span.low {
  background: #ed8936;
}
.inv-meter span.out {
  background: #e53e3e;
}
.inv-meter i {
  position: absolute;
  top: -3px;
  width: 2px;
  height: 12px;
  border-radius: 2px;
  background: #4a5568;
  opacity: 0.5;
}
.inv-sub {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 12px;
  color: var(--v-muted);
}
.inv-sub strong {
  color: var(--v-text);
}
.inv-quick {
  display: flex;
  gap: 4px;
}
/* A bouquet whose stock comes from its linked flowers: no − / + */
.inv-linked {
  max-width: 180px;
  margin: 0;
  color: var(--v-muted);
  font-size: 12px;
  line-height: 1.4;
}
/* A − / + change waiting for Save */
.inv-row.pending {
  background: var(--v-amber-soft);
}
/* A wrapper bought in rolls: "≈ 2 rolls + 20 sheets · 50 per roll" */
.inv-packs {
  margin-left: 6px;
  color: var(--v-muted);
  font-style: normal;
  font-size: 12px;
}
.inv-pending {
  margin-left: 6px;
  padding: 1px 7px;
  border-radius: 999px;
  font-size: 11.5px;
  font-style: normal;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.inv-pending.up {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.inv-pending.down {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.inv-quick .sp-icon-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.inv-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

/* History */
.inv-history {
  position: sticky;
  top: 16px;
  max-height: calc(100vh - 32px);
  overflow-y: auto;
}
.hist {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 2px;
}
.hist li {
  display: flex;
  gap: 12px;
  padding: 10px 4px;
  border-bottom: 1px solid var(--v-border);
}
.hist li:last-child {
  border-bottom: 0;
}
.hist-change {
  flex: none;
  display: grid;
  place-items: center;
  min-width: 42px;
  height: 28px;
  padding: 0 6px;
  border-radius: 9px;
  font-size: 12.5px;
  font-weight: 700;
}
.hist-change.up {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.hist-change.down {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.hist-main {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.hist-main strong {
  font-size: 13px;
  font-weight: 500;
  color: var(--v-text);
}
.hist-main span {
  font-size: 11.5px;
  color: var(--v-muted);
}
.hist-main em {
  margin-top: 2px;
  font-size: 12px;
  color: var(--v-text-2);
}
.sp-empty.small {
  padding: 24px 10px;
}

/* Modals */
.adj-modes {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}
.adj-preview {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 16px;
  padding: 12px 14px;
  border-radius: 13px;
  background: var(--bc-accent-soft);
  font-size: 15px;
  color: var(--v-text-2);
}
.adj-preview strong {
  font-size: 18px;
  color: var(--bc-accent-strong);
}
.adj-preview .arrow {
  color: var(--v-faint);
}
.adj-preview.bad {
  background: var(--v-red-soft);
}
.adj-preview.bad strong {
  font-size: 14px;
  color: var(--v-red-text);
}
@media (max-width: 1100px) {
  .inv-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .inv-history {
    position: static;
    max-height: none;
  }
}
@media (max-width: 720px) {
  .inv-row {
    flex-wrap: wrap;
  }
  .inv-main {
    flex-basis: calc(100% - 60px);
  }
  .inv-quick {
    margin-left: 58px;
  }
  .inv-actions {
    margin-left: auto;
  }
}
</style>
