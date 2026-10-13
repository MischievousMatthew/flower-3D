<template>
  <ShopLayout title="Products" subtitle="Add, edit, archive and manage your flower listings.">
    <template #actions>
      <router-link :to="{ name: 'VendorAddProduct' }" class="sp-btn sp-btn-primary">
        <span v-html="ICONS.plus"></span> Add product
      </router-link>
    </template>

    <div v-if="error && !products" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Summary -->
    <section class="sp-stats" aria-label="Product summary">
      <template v-if="!products">
        <div v-for="n in 4" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <button v-for="s in STAT_CARDS" :key="s.key" type="button" class="sp-stat" :class="{ on: status === s.key }" @click="status = s.key">
          <span class="sp-stat-icon" :class="s.tone" v-html="ICONS[s.icon]"></span>
          <div><strong>{{ counts[s.key] }}</strong><span>{{ s.label }}</span></div>
        </button>
      </template>
    </section>

    <!-- Toolbar -->
    <div class="sp-toolbar">
      <div class="sp-seg" role="tablist" aria-label="Filter by status">
        <button v-for="f in STATUS_FILTERS" :key="f.key" type="button" role="tab" :aria-selected="status === f.key" :class="{ on: status === f.key }" @click="status = f.key">
          {{ f.label }}<span v-if="products" class="count">{{ counts[f.key] }}</span>
        </button>
      </div>
      <label class="sp-search">
        <span v-html="ICONS.search"></span>
        <input v-model="search" class="sp-input" type="search" placeholder="Search name, SKU or category" aria-label="Search products" />
      </label>
      <select v-model="category" class="sp-select narrow" aria-label="Category">
        <option value="">All categories</option>
        <option v-for="c in usedCategories" :key="c" :value="c">{{ categoryLabel(c) }}</option>
      </select>
      <select v-model="sort" class="sp-select narrow" aria-label="Sort">
        <option value="updated">Recently updated</option>
        <option value="name">Name A–Z</option>
        <option value="price_low">Price: low to high</option>
        <option value="price_high">Price: high to low</option>
        <option value="stock_low">Stock: lowest first</option>
      </select>
      <div class="view-toggle" role="group" aria-label="Layout">
        <button type="button" class="sp-icon-btn" :class="{ on: view === 'grid' }" aria-label="Grid" @click="setView('grid')"><span v-html="ICONS.grid"></span></button>
        <button type="button" class="sp-icon-btn" :class="{ on: view === 'list' }" aria-label="List" @click="setView('list')"><span v-html="ICONS.list"></span></button>
      </div>
    </div>

    <!-- Bulk bar -->
    <Transition name="sp-pop">
      <div v-if="selected.length" class="bulk-bar" role="region" aria-label="Selected products">
        <label class="bulk-all">
          <input type="checkbox" :checked="allVisibleSelected" @change="toggleAllVisible" />
          {{ selected.length }} selected
        </label>
        <div class="bulk-actions">
          <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="bulkBusy" @click="bulk('archive')"><span v-html="ICONS.archive"></span>Archive</button>
          <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="bulkBusy" @click="bulk('restore')"><span v-html="ICONS.restore"></span>Make live</button>
          <select class="sp-select sp-btn-sm bulk-select" :disabled="bulkBusy" aria-label="Move to category" @change="bulkSelect($event, 'set_category')">
            <option value="">Move to category…</option>
            <option v-for="c in taxonomies.categories" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
          <select class="sp-select sp-btn-sm bulk-select" :disabled="bulkBusy" aria-label="Tag an occasion" @change="bulkSelect($event, 'add_occasion')">
            <option value="">Tag an occasion…</option>
            <option v-for="o in taxonomies.occasions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
          <button type="button" class="sp-btn sp-btn-sm link" @click="selected = []">Clear</button>
        </div>
      </div>
    </Transition>

    <!-- Loading -->
    <div v-if="!products" class="p-grid">
      <div v-for="n in 8" :key="n" class="p-card"><div class="bc-skeleton p-img"></div><div class="p-body"><div class="bc-skeleton sp-sk-line w70"></div><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
    </div>

    <!-- Empty -->
    <div v-else-if="!visible.length" class="sp-card">
      <div class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.flower"></span>
        <template v-if="products.length">
          <h3>No products match</h3>
          <p>Try another filter or search.</p>
          <button type="button" class="sp-btn sp-btn-ghost" @click="resetFilters">Clear filters</button>
        </template>
        <template v-else>
          <h3>Add your first product</h3>
          <p>List a bouquet, single stems or a 3D-customizable flower. Customers see it as soon as you publish.</p>
          <router-link :to="{ name: 'VendorAddProduct' }" class="sp-btn sp-btn-primary"><span v-html="ICONS.plus"></span>Add product</router-link>
        </template>
      </div>
    </div>

    <!-- Grid -->
    <div v-else-if="view === 'grid'" class="p-grid">
      <article v-for="p in visible" :key="p.id" class="p-card" :class="{ picked: isSelected(p), dim: p.status !== 'active' }">
        <div class="p-media" @click="editing = p.id">
          <img v-if="p.image" :src="p.image" :alt="p.name" loading="lazy" class="p-img" />
          <span v-else class="p-img blank" v-html="ICONS.image"></span>
          <label class="p-check" @click.stop>
            <input type="checkbox" :checked="isSelected(p)" :aria-label="`Select ${p.name}`" @change="toggle(p)" />
          </label>
          <div class="p-badges">
            <span v-if="p.sale_price" class="sp-chip pink">−{{ pct(p) }}%</span>
            <span v-if="p.has_model" class="sp-chip blue">3D</span>
          </div>
          <span class="sp-chip p-status" :class="statusOf(p).tone">{{ statusOf(p).label }}</span>
        </div>
        <div class="p-body">
          <div class="p-title">
            <h3 :title="p.name">{{ p.name }}</h3>
            <div class="p-menu-wrap">
              <button type="button" class="p-more" :aria-label="`More for ${p.name}`" aria-haspopup="menu" @click.stop="menuFor = menuFor === p.id ? null : p.id">
                <span v-html="ICONS.more"></span>
              </button>
              <Transition name="sp-pop">
                <div v-if="menuFor === p.id" class="p-menu" role="menu" @click.stop>
                  <button type="button" role="menuitem" @click="act('edit', p)"><span v-html="ICONS.edit"></span>Edit details</button>
                  <button type="button" role="menuitem" @click="act('sale', p)"><span v-html="ICONS.percent"></span>Sale price</button>
                  <router-link role="menuitem" :to="{ name: 'VendorInventory' }"><span v-html="ICONS.box"></span>Adjust stock</router-link>
                  <button v-if="p.status === 'active'" type="button" role="menuitem" @click="act('archive', p)"><span v-html="ICONS.archive"></span>Archive</button>
                  <button v-else type="button" role="menuitem" @click="act('restore', p)"><span v-html="ICONS.restore"></span>Make live</button>
                  <button type="button" role="menuitem" class="danger" @click="act('delete', p)"><span v-html="ICONS.trash"></span>Delete</button>
                </div>
              </Transition>
            </div>
          </div>
          <p class="p-meta">{{ categoryLabel(p.category) }}<template v-if="p.sku"> · {{ p.sku }}</template></p>
          <div class="p-foot">
            <div class="p-price">
              <strong>{{ money(p.sale_price ?? p.price) }}</strong>
              <span v-if="p.sale_price" class="old">{{ money(p.price) }}</span>
            </div>
            <span class="p-stock" :class="p.stock_state">{{ stockText(p) }}</span>
          </div>
          <p v-if="takenDown(p)" class="p-flag"><span v-html="ICONS.alert"></span>{{ moderationText(p) }}</p>
          <p v-else-if="p.promotion_name" class="p-promo">{{ p.promotion_name }}</p>
        </div>
      </article>
    </div>

    <!-- List -->
    <div v-else class="sp-card p-table-card">
      <table class="p-table">
        <thead>
          <tr>
            <th class="c-check"><input type="checkbox" :checked="allVisibleSelected" aria-label="Select all" @change="toggleAllVisible" /></th>
            <th>Product</th>
            <th>Category</th>
            <th class="num">Price</th>
            <th class="num">Stock</th>
            <th>Status</th>
            <th class="c-act"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in visible" :key="p.id" :class="{ picked: isSelected(p) }">
            <td class="c-check"><input type="checkbox" :checked="isSelected(p)" :aria-label="`Select ${p.name}`" @change="toggle(p)" /></td>
            <td>
              <button type="button" class="p-row-main" @click="editing = p.id">
                <img v-if="p.image" :src="p.image" :alt="p.name" class="sp-thumb" loading="lazy" />
                <span v-else class="sp-thumb blank" v-html="ICONS.image"></span>
                <span class="p-row-text">
                  <strong>{{ p.name }}</strong>
                  <span>{{ p.sku || sellingType(p.selling_type) }}<template v-if="p.has_model"> · 3D</template></span>
                </span>
              </button>
            </td>
            <td>{{ categoryLabel(p.category) }}</td>
            <td class="num">
              <strong>{{ money(p.sale_price ?? p.price) }}</strong>
              <span v-if="p.sale_price" class="old">{{ money(p.price) }}</span>
            </td>
            <td class="num"><span class="p-stock" :class="p.stock_state">{{ stockText(p) }}</span></td>
            <td><span class="sp-chip" :class="statusOf(p).tone">{{ statusOf(p).label }}</span></td>
            <td class="c-act">
              <button type="button" class="sp-icon-btn" :aria-label="`Edit ${p.name}`" @click="editing = p.id"><span v-html="ICONS.edit"></span></button>
              <button type="button" class="sp-icon-btn" :aria-label="`Sale price for ${p.name}`" @click="saleFor = p"><span v-html="ICONS.percent"></span></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <ProductEditor :product-id="editing" @close="editing = null" @saved="load" />
    <SalePriceModal :product="saleFor" @close="saleFor = null" @saved="replace" />

    <!-- Delete confirmation -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="deleting" class="sp-backdrop" @click="deleting = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="deleting" class="sp-modal-wrap">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="del-title">
            <div class="sp-modal-head">
              <div>
                <h3 id="del-title">Delete {{ deleting.name }}?</h3>
                <p>It disappears from your shop and its photos and 3D model are removed. Past orders keep their details.</p>
              </div>
            </div>
            <p class="sp-hint">If you might sell it again, archive it instead.</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" :disabled="busy" @click="archiveInstead">Archive instead</button>
              <button type="button" class="sp-btn sp-btn-danger" :disabled="busy" @click="confirmDelete">{{ busy ? "Deleting…" : "Delete" }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import ProductEditor from "../../../components/vendor/shop/ProductEditor.vue";
import SalePriceModal from "../../../components/vendor/shop/SalePriceModal.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage, money, useVendorCatalog } from "../../../components/vendor/shop/useVendorCatalog";
import "../../../components/vendor/shop/shop.css";

const { products, error, load, replace, remove, taxonomies, categoryLabel } = useVendorCatalog();

const STAT_CARDS = [
  { key: "all", label: "All products", icon: "flower", tone: "tone-green" },
  { key: "live", label: "Live in your shop", icon: "check", tone: "tone-blue" },
  { key: "draft", label: "Drafts", icon: "edit", tone: "tone-amber" },
  { key: "archived", label: "Archived", icon: "archive", tone: "tone-slate" },
];
const STATUS_FILTERS = [
  { key: "all", label: "All" },
  { key: "live", label: "Live" },
  { key: "draft", label: "Drafts" },
  { key: "archived", label: "Archived" },
  { key: "flagged", label: "Taken down" },
];
const TAKEN_DOWN = ["hidden", "resubmitted", "removed"];

const status = ref("all");
const search = ref("");
const category = ref("");
const sort = ref("updated");
const view = ref(readView());
const selected = ref([]);
const editing = ref(null);
const saleFor = ref(null);
const deleting = ref(null);
const menuFor = ref(null);
const busy = ref(false);
const bulkBusy = ref(false);

function readView() {
  try {
    return localStorage.getItem("bc.vendor.products.view") === "list" ? "list" : "grid";
  } catch {
    return "grid";
  }
}
function setView(v) {
  view.value = v;
  try {
    localStorage.setItem("bc.vendor.products.view", v);
  } catch {
    /* private mode */
  }
}

const takenDown = (p) => TAKEN_DOWN.includes(p.moderation_status);
const inStatus = (p, key) =>
  key === "all" ||
  (key === "live" && p.status === "active" && !takenDown(p)) ||
  (key === "draft" && p.status === "draft") ||
  (key === "archived" && ["inactive", "discontinued"].includes(p.status)) ||
  (key === "flagged" && takenDown(p));

const counts = computed(() =>
  Object.fromEntries(STATUS_FILTERS.map((f) => [f.key, (products.value ?? []).filter((p) => inStatus(p, f.key)).length])),
);
const usedCategories = computed(() => [...new Set((products.value ?? []).map((p) => p.category).filter(Boolean))]);

const visible = computed(() => {
  const q = search.value.trim().toLowerCase();
  const list = (products.value ?? []).filter(
    (p) =>
      inStatus(p, status.value) &&
      (!category.value || p.category === category.value) &&
      (!q || [p.name, p.sku, categoryLabel(p.category)].some((v) => v && v.toLowerCase().includes(q))),
  );
  const price = (p) => p.sale_price ?? p.price;
  const sorters = {
    updated: (a, b) => new Date(b.updated_at) - new Date(a.updated_at),
    name: (a, b) => a.name.localeCompare(b.name),
    price_low: (a, b) => price(a) - price(b),
    price_high: (a, b) => price(b) - price(a),
    stock_low: (a, b) => a.stock - b.stock,
  };
  return [...list].sort(sorters[sort.value]);
});

const isSelected = (p) => selected.value.includes(p.id);
const toggle = (p) => {
  selected.value = isSelected(p) ? selected.value.filter((id) => id !== p.id) : [...selected.value, p.id];
};
const allVisibleSelected = computed(() => visible.value.length > 0 && visible.value.every(isSelected));
const toggleAllVisible = () => {
  const ids = visible.value.map((p) => p.id);
  selected.value = allVisibleSelected.value
    ? selected.value.filter((id) => !ids.includes(id))
    : [...new Set([...selected.value, ...ids])];
};
watch(products, (list) => {
  const ids = new Set((list ?? []).map((p) => p.id));
  selected.value = selected.value.filter((id) => ids.has(id));
});

function resetFilters() {
  status.value = "all";
  search.value = "";
  category.value = "";
}

const pct = (p) => Math.round((1 - p.sale_price / p.price) * 100);
const stockText = (p) => (p.stock_state === "out" ? "Out of stock" : `${p.stock} in stock`);
const sellingType = (t) => ({ per_piece: "Per piece", per_piece_customizable: "3D customizable", bouquet: "Bouquet" })[t] ?? "Product";
function statusOf(p) {
  if (p.moderation_status === "removed") return { label: "Removed", tone: "red" };
  if (p.moderation_status === "hidden") return { label: "Taken down", tone: "red" };
  if (p.moderation_status === "resubmitted") return { label: "Awaiting review", tone: "amber" };
  if (p.status === "draft") return { label: "Draft", tone: "amber" };
  if (p.status !== "active") return { label: "Archived", tone: "slate" };
  return { label: "Live", tone: "green" };
}
function moderationText(p) {
  if (p.moderation_status === "resubmitted") return "Fixed and waiting for BloomCraft to review.";
  if (p.moderation_status === "removed") return p.moderation_reason || "Removed by BloomCraft.";
  return p.moderation_reason ? `Taken down: ${p.moderation_reason}` : "Taken down by BloomCraft. Edit it to send it for review.";
}

async function setStatus(p, statusValue) {
  try {
    const { data } = await api.post("vendor/catalog/products/bulk", { ids: [p.id], action: statusValue === "active" ? "restore" : "archive" });
    toast.success(statusValue === "active" ? `${p.name} is live.` : `${p.name} archived.`);
    if (data.changed) await load();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't update the product."));
  }
}

function act(action, p) {
  menuFor.value = null;
  if (action === "edit") editing.value = p.id;
  else if (action === "sale") saleFor.value = p;
  else if (action === "archive") setStatus(p, "inactive");
  else if (action === "restore") setStatus(p, "active");
  else if (action === "delete") deleting.value = p;
}

async function confirmDelete() {
  if (busy.value) return;
  busy.value = true;
  try {
    await api.delete(`vendor/products/${deleting.value.id}`);
    toast.success(`${deleting.value.name} deleted.`);
    remove(deleting.value.id);
    deleting.value = null;
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't delete the product."));
  } finally {
    busy.value = false;
  }
}
async function archiveInstead() {
  const p = deleting.value;
  deleting.value = null;
  await setStatus(p, "inactive");
}

async function bulk(action, value = null) {
  if (bulkBusy.value || !selected.value.length) return;
  bulkBusy.value = true;
  try {
    const { data } = await api.post("vendor/catalog/products/bulk", { ids: selected.value, action, value });
    toast.success(data.message);
    await load();
    if (action === "archive" || action === "restore") selected.value = [];
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't update those products."));
  } finally {
    bulkBusy.value = false;
  }
}
function bulkSelect(event, action) {
  const value = event.target.value;
  event.target.value = "";
  if (value) bulk(action, value);
}

const closeMenu = () => (menuFor.value = null);
onMounted(() => {
  load();
  document.addEventListener("click", closeMenu);
});
onBeforeUnmount(() => document.removeEventListener("click", closeMenu));
</script>

<style scoped>
.grow {
  flex: 1;
}
.narrow {
  width: auto;
  min-width: 150px;
}
.view-toggle {
  display: flex;
  gap: 4px;
}

/* Bulk bar */
.bulk-bar {
  position: sticky;
  top: 12px;
  z-index: 20;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  margin-bottom: 16px;
  border-radius: 14px;
  background: #1a202c;
  color: #fff;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.2);
}
.bulk-all {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 500;
}
.bulk-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-left: auto;
}
.bulk-select {
  width: auto;
  padding: 6px 10px;
}
.bulk-actions .link {
  background: none;
  color: #cbd5e0;
}

/* Grid */
.p-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
}
.p-card {
  display: flex;
  flex-direction: column;
  border: 1px solid #edf1ef;
  border-radius: 18px;
  background: #fff;
  overflow: visible;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
}
.p-card:hover {
  border-color: #c6f6d5;
  box-shadow: 0 10px 26px rgba(16, 24, 40, 0.07);
  transform: translateY(-2px);
}
.p-card.picked {
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.18);
}
.p-card.dim .p-img {
  filter: grayscale(0.4);
  opacity: 0.8;
}
.p-media {
  position: relative;
  cursor: pointer;
}
.p-img {
  display: block;
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  border-radius: 17px 17px 0 0;
  background: #f0fff4;
}
.p-img.blank {
  display: grid;
  place-items: center;
  color: #9ae6b4;
}
.p-img.blank :deep(svg) {
  width: 34px;
  height: 34px;
}
.p-check {
  position: absolute;
  top: 10px;
  left: 10px;
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 9px;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
  cursor: pointer;
}
.p-check input {
  width: 16px;
  height: 16px;
  margin: 0;
  accent-color: #48bb78;
  cursor: pointer;
}
.p-badges {
  position: absolute;
  top: 10px;
  right: 10px;
  display: flex;
  gap: 5px;
}
.p-status {
  position: absolute;
  left: 10px;
  bottom: 10px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}
.p-body {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 14px 14px;
}
.p-title {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 6px;
}
.p-title h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #1a202c;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.p-menu-wrap {
  position: relative;
}
.p-more {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  padding: 0;
  border: 0;
  border-radius: 8px;
  background: none;
  color: #718096;
  cursor: pointer;
}
.p-more:hover {
  background: #f1f5f3;
}
.p-more :deep(svg) {
  width: 16px;
  height: 16px;
}
.p-menu {
  position: absolute;
  right: 0;
  top: 32px;
  z-index: 30;
  display: flex;
  flex-direction: column;
  min-width: 180px;
  padding: 6px;
  border: 1px solid #edf2f7;
  border-radius: 13px;
  background: #fff;
  box-shadow: 0 14px 34px rgba(15, 23, 42, 0.16);
}
.p-menu button,
.p-menu a {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 10px;
  border: 0;
  border-radius: 9px;
  background: none;
  color: #2d3748;
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
}
.p-menu button:hover,
.p-menu a:hover {
  background: #f3f8f5;
}
.p-menu .danger {
  color: #c53030;
}
.p-menu :deep(svg) {
  width: 15px;
  height: 15px;
}
.p-meta {
  margin: 0;
  font-size: 12px;
  color: #718096;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.p-foot {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 8px;
  margin-top: 6px;
}
.p-price strong {
  font-size: 15px;
  font-weight: 600;
  color: #1a202c;
}
.old {
  margin-left: 6px;
  font-size: 12px;
  color: #a0aec0;
  text-decoration: line-through;
}
.p-stock {
  font-size: 11.5px;
  font-weight: 600;
  color: #2f855a;
  white-space: nowrap;
}
.p-stock.low {
  color: #b45309;
}
.p-stock.out {
  color: #c53030;
}
.p-flag {
  display: flex;
  gap: 6px;
  margin: 6px 0 0;
  padding: 7px 9px;
  border-radius: 10px;
  background: #fff5f5;
  color: #c53030;
  font-size: 11.5px;
  line-height: 1.4;
}
.p-flag :deep(svg) {
  flex: none;
  width: 14px;
  height: 14px;
}
.p-promo {
  margin: 4px 0 0;
  font-size: 11.5px;
  font-weight: 500;
  color: #b83280;
}

/* List */
.p-table-card {
  padding: 0;
  overflow-x: auto;
}
.p-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.p-table th {
  padding: 12px 14px;
  text-align: left;
  font-size: 11.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #718096;
  border-bottom: 1px solid #edf2f7;
  white-space: nowrap;
}
.p-table td {
  padding: 10px 14px;
  border-bottom: 1px solid #f4f6f5;
  vertical-align: middle;
}
.p-table tr:last-child td {
  border-bottom: 0;
}
.p-table tbody tr:hover,
.p-table tr.picked {
  background: #f8fcf9;
}
.p-table .num {
  text-align: right;
  white-space: nowrap;
}
.c-check {
  width: 36px;
}
.c-check input {
  accent-color: #48bb78;
}
.c-act {
  width: 92px;
  white-space: nowrap;
  text-align: right;
}
.c-act .sp-icon-btn + .sp-icon-btn {
  margin-left: 4px;
}
.p-row-main {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0;
  border: 0;
  background: none;
  font-family: "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
}
.p-row-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.p-row-text strong {
  font-size: 13.5px;
  font-weight: 500;
  color: #1a202c;
}
.p-row-text span {
  font-size: 12px;
  color: #a0aec0;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}
@media (max-width: 720px) {
  .narrow {
    flex: 1;
  }
  .p-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  .bulk-actions {
    margin-left: 0;
  }
}
@media (max-width: 420px) {
  .p-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
