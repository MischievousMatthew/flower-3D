<template>
  <div class="form-page">
    
    <div class="form-header">
      <router-link
        to="/erp/procurement/supply-chain/warehouses"
        class="back-link"
      >
        <svg
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          width="16"
        >
          <path d="M13 16l-6-6 6-6" />
        </svg>
        Back to Warehouse
      </router-link>
      <h1 class="form-title">
        Add Warehouse Item
        <span v-if="warehouseName" class="wh-label"
          >&#x2014; {{ warehouseName }}</span
        >
      </h1>
    </div>

    <div class="form-grid">
      <div class="form-card">
        <div class="section-title">
          <div class="section-dot"></div>
          Select Product
        </div>

        <div class="field">
          <label>Product <span class="req">*</span></label>
          <div class="product-search-wrap">
            <div class="input-addon" :class="{ 'is-error': errors.product_id }">
              <svg
                viewBox="0 0 20 20"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                width="14"
                class="search-icon"
              >
                <circle cx="9" cy="9" r="6" />
                <path d="m14 14 3 3" />
              </svg>
              <input
                v-model="productSearch"
                :placeholder="
                  selectedProduct
                    ? selectedProduct.product_name
                    : 'Search by name or SKU...'
                "
                :class="{ 'has-value': !!selectedProduct }"
                @focus="showDropdown = true"
                @input="onProductInput"
                @blur="onSearchBlur"
                autocomplete="off"
              />
              <button
                v-if="selectedProduct"
                class="clear-btn"
                type="button"
                @mousedown.prevent="clearProduct"
              >
                &times;
              </button>
            </div>

            <transition name="dropdown-fade">
              <div
                v-if="
                  showDropdown && (productResults.length || productsLoading)
                "
                class="product-dropdown"
              >
                <div v-if="productsLoading" class="dd-loading">
                  <div class="spinner-xs"></div>
                  Searching...
                </div>
                <button
                  v-else
                  v-for="p in productResults"
                  :key="p.id"
                  class="dd-item"
                  type="button"
                  @mousedown.prevent="pickProduct(p)"
                >
                  <div class="dd-img">
                    <img
                      v-if="p.image_url"
                      :src="p.image_url"
                      :alt="p.product_name"
                    />
                    <span v-else>{{ p.product_name?.[0] }}</span>
                  </div>
                  <div class="dd-info">
                    <div class="dd-name">{{ p.product_name }}</div>
                    <div class="dd-meta">
                      <code>{{ p.sku }}</code>
                      <span v-if="p.flower_type" class="dd-tag flower">{{
                        p.flower_type
                      }}</span>
                      <span v-if="p.color" class="dd-tag color">{{
                        p.color
                      }}</span>
                    </div>
                  </div>
                  <div class="dd-stock" :class="stockClass(p.stock_count)">
                    {{ p.stock_count ?? 0 }}<span>units</span>
                  </div>
                </button>
              </div>
            </transition>
          </div>
          <span v-if="errors.product_id" class="err-msg">{{
            errors.product_id
          }}</span>
        </div>

        <transition name="fade-slide">
          <div v-if="selectedProduct" class="selected-strip">
            <div class="strip-img">
              <img
                v-if="selectedProduct.image_url"
                :src="selectedProduct.image_url"
                :alt="selectedProduct.product_name"
              />
              <span v-else>{{ selectedProduct.product_name?.[0] }}</span>
            </div>
            <div class="strip-info">
              <span class="strip-name">{{ selectedProduct.product_name }}</span>
              <code class="strip-sku">{{ selectedProduct.sku }}</code>
            </div>
            <div class="strip-right">
              <span
                class="strip-stat"
                :class="stockClass(selectedProduct.stock_count)"
              >
                {{ selectedProduct.stock_count ?? 0 }} on-paper
              </span>
              <span
                v-if="(selectedProduct.warehouse_units_stored ?? 0) > 0"
                class="strip-stat blue"
              >
                {{ selectedProduct.warehouse_units_stored }} already stored
              </span>
            </div>
            <div class="strip-tags">
              <span v-if="selectedProduct.flower_type" class="tag flower">{{
                selectedProduct.flower_type
              }}</span>
              <span v-if="selectedProduct.color" class="tag color">{{
                selectedProduct.color
              }}</span>
              <span
                v-if="selectedProduct.requires_refrigeration"
                class="tag cold"
                >&#x2744; Refrigerated</span
              >
            </div>
          </div>
        </transition>

        <div class="section-title">
          <div class="section-dot"></div>
          Storage Location
          <span class="req" style="margin-left: 2px; font-weight: 400">*</span>
        </div>

        <div v-if="locationsLoading" class="loading-inline">
          <div class="spinner-xs"></div>
          Loading locations...
        </div>
        <div v-else-if="!locations.length" class="alert-warn">
          <svg
            viewBox="0 0 20 20"
            fill="none"
            stroke="#f59e0b"
            stroke-width="1.5"
            width="14"
          >
            <circle cx="10" cy="10" r="7" />
            <path d="M10 7v3M10 13h.01" />
          </svg>
          No active storage locations.
          <router-link
            to="/erp/procurement/supply-chain/warehouse/locations"
            class="link"
            >Manage &#x2192;</router-link
          >
        </div>
        <div v-else class="location-grid">
          <button
            v-for="loc in locations"
            :key="loc.id"
            type="button"
            class="loc-card"
            :class="{
              selected: form.warehouse_location_id === loc.id,
              full: loc.is_full,
            }"
            :disabled="loc.is_full"
            @click="form.warehouse_location_id = loc.id"
          >
            <div class="loc-name">{{ loc.name }}</div>
            <code class="loc-code">{{ loc.code }}</code>
            <div class="loc-badges">
              <span v-if="loc.is_refrigerated" class="loc-badge cold"
                >&#x2744;</span
              >
              <span v-if="loc.zone" class="loc-badge">{{ loc.zone }}</span>
              <span v-if="loc.is_full" class="loc-badge danger">Full</span>
            </div>
            <div class="loc-check" v-if="form.warehouse_location_id === loc.id">
              <svg viewBox="0 0 16 16" fill="currentColor" width="10">
                <path
                  fill-rule="evenodd"
                  d="M13.707 4.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-3-3a1 1 0 011.414-1.414L6 10.586l6.293-6.293a1 1 0 011.414 0z"
                />
              </svg>
            </div>
          </button>
        </div>
        <span v-if="errors.warehouse_location_id" class="err-msg">{{
          errors.warehouse_location_id
        }}</span>

        <div class="section-title">
          <div class="section-dot"></div>
          Batch Details
        </div>

        <div class="field-group">
          <div class="field">
            <label>Quantity Received <span class="req">*</span></label>
            <div
              class="qty-control"
              :class="{ 'is-error': errors.qty_received }"
            >
              <button
                type="button"
                class="qty-btn"
                @click="adjustQty(-1)"
                :disabled="form.qty_received <= 1"
              >
                &#x2212;
              </button>
              <input
                v-model.number="form.qty_received"
                type="number"
                min="1"
                placeholder="0"
                @blur="
                  if (!form.qty_received || form.qty_received < 1)
                    form.qty_received = 1;
                "
              />
              <button type="button" class="qty-btn" @click="adjustQty(1)">
                +
              </button>
            </div>
            <span v-if="errors.qty_received" class="err-msg">{{
              errors.qty_received
            }}</span>
          </div>
          <div class="field">
            <label>Received Date <span class="req">*</span></label>
            <input
              v-model="form.received_date"
              type="date"
              :class="{ error: errors.received_date }"
            />
            <span v-if="errors.received_date" class="err-msg">{{
              errors.received_date
            }}</span>
          </div>
        </div>

        <div class="field-group">
          <div class="field">
            <label>Harvest Date</label>
            <input v-model="form.harvest_date" type="date" />
          </div>
          <div class="field">
            <label>Expiration Date</label>
            <input v-model="form.expiration_date" type="date" />
          </div>
        </div>

        <div class="field-group">
          <div class="field">
            <label>Freshness Days</label>
            <input
              v-model.number="form.freshness_days"
              type="number"
              min="1"
              placeholder="e.g. 7"
            />
            <span class="field-hint">Used to compute freshness condition</span>
          </div>
          <div class="field">
            <label>Lot Number</label>
            <input v-model="form.lot_number" placeholder="e.g. LOT-2024-001" />
          </div>
        </div>

        <transition name="fade">
          <div v-if="freshnessPreview" class="freshness-bar-wrap">
            <div class="fb-track">
              <div
                class="fb-fill"
                :class="freshnessPreview.condition"
                :style="{ width: freshnessPreview.pct + '%' }"
              ></div>
            </div>
            <div class="fb-meta">
              <span class="fb-chip" :class="freshnessPreview.condition">{{
                freshnessPreview.label
              }}</span>
              <span class="fb-days"
                >{{ freshnessPreview.daysLeft }} days until expiry</span
              >
            </div>
          </div>
        </transition>

        <div class="field">
          <label>Notes</label>
          <textarea
            v-model="form.notes"
            rows="2"
            class="textarea"
            placeholder="Optional notes about this batch..."
          ></textarea>
        </div>
      </div>

      <div class="side-panel">
        <div class="panel-card preview-card">
          <div class="panel-title">Batch Preview</div>
          <div class="preview-item">
            <div class="preview-icon">
              <img
                v-if="selectedProduct?.image_url"
                :src="selectedProduct.image_url"
                :alt="selectedProduct.product_name"
              />
              <span v-else>{{
                selectedProduct?.product_name?.[0] || "?"
              }}</span>
            </div>
            <div class="preview-name">
              {{ selectedProduct?.product_name || "No product selected" }}
            </div>
            <code class="preview-sku">{{ selectedProduct?.sku || "SKU" }}</code>
            <div class="preview-qty">
              <span class="qty-badge">{{ form.qty_received || 0 }} units</span>
            </div>
            <div v-if="selectedLocationLabel" class="preview-meta">
              <svg
                viewBox="0 0 16 16"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                width="11"
              >
                <path d="M2 6l6-4 6 4v8H2z" />
                <rect x="5" y="9" width="3" height="5" rx="0.5" />
              </svg>
              {{ selectedLocationLabel }}
            </div>
            <div v-if="form.expiration_date" class="preview-meta muted">
              Expires {{ form.expiration_date }}
            </div>
          </div>
        </div>

        <div class="panel-card actions-card">
          <button
            class="btn-submit"
            :disabled="submitting || !isFormReady"
            @click="submit"
          >
            <div v-if="submitting" class="spinner-xs white"></div>
            {{ submitting ? "Adding..." : "Add to Warehouse" }}
          </button>
          <router-link
            to="/erp/procurement/supply-chain/warehouses"
            class="btn-cancel"
            >Cancel</router-link
          >
        </div>

        <div class="panel-card checklist-card">
          <div class="panel-title">Required</div>
          <div class="check-row" :class="{ done: !!selectedProduct }">
            <div class="check-dot" :class="{ done: !!selectedProduct }"></div>
            Product selected
          </div>
          <div
            class="check-row"
            :class="{ done: !!form.warehouse_location_id }"
          >
            <div
              class="check-dot"
              :class="{ done: !!form.warehouse_location_id }"
            ></div>
            Storage location
          </div>
          <div class="check-row" :class="{ done: form.qty_received >= 1 }">
            <div
              class="check-dot"
              :class="{ done: form.qty_received >= 1 }"
            ></div>
            Quantity &ge; 1
          </div>
          <div class="check-row" :class="{ done: !!form.received_date }">
            <div
              class="check-dot"
              :class="{ done: !!form.received_date }"
            ></div>
            Received date
          </div>
        </div>
      </div>
    </div>

    <transition name="toast-slide">
      <div v-if="toast.show" class="toast" :class="toast.type">
        {{ toast.message }}
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { warehouseService } from "../../../../../services/warehouseService";
import { warehouseLocationService } from "../../../../../services/warehouseBatchService";


const router = useRouter();
const route = useRoute();

// FIXED: was computed(() => Number(route.params.warehouseId)) which read a
// URL param that no longer exists. Now reads from history.state set by
// WarehouseList.vue's goToAddItem(). Falls back to route.params for any
// other entry points that may still pass it as a param.
const warehouseId = computed(
  () => history.state?.warehouseId ?? Number(route.params.warehouseId) ?? null,
);
const warehouseName = computed(() => history.state?.warehouseName ?? "");

const submitting = ref(false);
const errors = reactive({});
const toast = ref({ show: false, type: "success", message: "" });

const productSearch = ref("");
const productResults = ref([]);
const productsLoading = ref(false);
const showDropdown = ref(false);
const selectedProduct = ref(null);

const locations = ref([]);
const locationsLoading = ref(false);

const form = reactive({
  product_id: null,
  warehouse_location_id: null,
  qty_received: 1,
  received_date: new Date().toISOString().slice(0, 10),
  harvest_date: "",
  expiration_date: "",
  freshness_days: null,
  lot_number: "",
  notes: "",
});

const isFormReady = computed(
  () =>
    !!selectedProduct.value &&
    !!form.warehouse_location_id &&
    form.qty_received >= 1 &&
    !!form.received_date,
);

const selectedLocationLabel = computed(() => {
  const loc = locations.value.find((l) => l.id === form.warehouse_location_id);
  return loc ? `${loc.name} (${loc.code})` : "";
});

const freshnessPreview = computed(() => {
  if (!form.expiration_date) return null;
  const daysLeft = Math.round(
    (new Date(form.expiration_date) - new Date()) / 86400000,
  );
  let condition, label, pct;
  if (daysLeft < 0) {
    condition = "spoiled";
    label = "Already Expired";
    pct = 0;
  } else if (form.freshness_days) {
    const r = daysLeft / form.freshness_days;
    if (r > 0.6) {
      condition = "fresh";
      label = "Fresh";
      pct = 90;
    } else if (r > 0.4) {
      condition = "aging";
      label = "Aging";
      pct = 55;
    } else if (r > 0.2) {
      condition = "wilting";
      label = "Wilting";
      pct = 30;
    } else {
      condition = "spoiled";
      label = "Spoiled";
      pct = 10;
    }
  } else {
    if (daysLeft > 5) {
      condition = "fresh";
      label = "Fresh";
      pct = 90;
    } else if (daysLeft > 2) {
      condition = "aging";
      label = "Aging";
      pct = 55;
    } else if (daysLeft > 0) {
      condition = "wilting";
      label = "Wilting";
      pct = 30;
    } else {
      condition = "spoiled";
      label = "Spoiled";
      pct = 10;
    }
  }
  return { condition, label, pct, daysLeft };
});

let searchTimer = null;
function onProductInput() {
  selectedProduct.value = null;
  form.product_id = null;
  clearTimeout(searchTimer);
  if (!productSearch.value.trim()) {
    productResults.value = [];
    return;
  }
  searchTimer = setTimeout(searchProducts, 300);
}

async function searchProducts() {
  productsLoading.value = true;
  try {
    // FIXED: was catalogProducts(warehouseId, ...) which only returns products
    // already stored in that warehouse. searchProducts() hits the full product
    // catalog so you can add any product to the warehouse.
    const res = await warehouseService.searchProducts({
      search: productSearch.value,
      per_page: 8,
    });
    const body = res.data;
    const raw = body?.data?.data ?? body?.data ?? body ?? [];
    // Normalize: products table uses `quantity_in_stock`, UI uses `stock_count`
    productResults.value = raw.map((p) => ({
      ...p,
      stock_count: p.stock_count ?? p.quantity_in_stock ?? 0,
    }));
  } catch {
    productResults.value = [];
  } finally {
    productsLoading.value = false;
  }
}

function pickProduct(p) {
  selectedProduct.value = p;
  form.product_id = p.id;
  productSearch.value = p.product_name;
  showDropdown.value = false;
}

function clearProduct() {
  selectedProduct.value = null;
  form.product_id = null;
  productSearch.value = "";
  productResults.value = [];
}

function onSearchBlur() {
  setTimeout(() => {
    showDropdown.value = false;
  }, 150);
}

async function loadLocations() {
  locationsLoading.value = true;
  try {
    const res = await warehouseLocationService.list();
    locations.value = res.data?.data ?? res.data ?? res ?? [];
  } catch {
    locations.value = [];
  } finally {
    locationsLoading.value = false;
  }
}

onMounted(async () => {
  await loadLocations();
  const prefill = history.state?.prefill;
  if (prefill) {
    try {
      const d = JSON.parse(prefill);
      if (d.product_id) {
        selectedProduct.value = {
          id: d.product_id,
          product_name: d.product_name,
          sku: d.sku,
          image_url: d.image_url ?? null,
          stock_count: 0,
          warehouse_units_stored: 0,
        };
        form.product_id = d.product_id;
        productSearch.value = d.product_name;
      }
    } catch (_) {}
  }
});

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  if (!form.product_id) errors.product_id = "Please select a product";
  if (!form.warehouse_location_id)
    errors.warehouse_location_id = "Please select a storage location";
  if (!form.qty_received || form.qty_received < 1)
    errors.qty_received = "Quantity must be at least 1";
  if (!form.received_date) errors.received_date = "Received date is required";
  return !Object.keys(errors).length;
}

async function submit() {
  if (!validate()) return;
  submitting.value = true;
  try {
    const payload = {
      product_id: form.product_id,
      warehouse_location_id: form.warehouse_location_id,
      qty_received: form.qty_received,
      received_date: form.received_date,
    };
    if (form.harvest_date) payload.harvest_date = form.harvest_date;
    if (form.expiration_date) payload.expiration_date = form.expiration_date;
    if (form.freshness_days) payload.freshness_days = form.freshness_days;
    if (form.lot_number) payload.lot_number = form.lot_number;
    if (form.notes) payload.notes = form.notes;

    await warehouseService.addItem(warehouseId.value, payload);
    showToast("Item added to warehouse!");
    setTimeout(
      () => router.push("/erp/procurement/supply-chain/warehouses"),
      1000,
    );
  } catch (e) {
    showToast(
      e?.response?.data?.message ?? e?.message ?? "Failed to add item",
      "error",
    );
  } finally {
    submitting.value = false;
  }
}

function adjustQty(delta) {
  form.qty_received = Math.max(1, (form.qty_received ?? 1) + delta);
}

function stockClass(qty) {
  const n = qty ?? 0;
  if (n <= 0) return "danger";
  if (n <= 10) return "warn";
  return "ok";
}

function showToast(msg, type = "success") {
  toast.value = { show: true, type, message: msg };
  setTimeout(() => (toast.value.show = false), 3000);
}
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.form-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 900px;
}
.form-header {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #6b7280;
  text-decoration: none;
  font-size: 13px;
}
.back-link:hover {
  color: #111827;
}
.form-title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.wh-label {
  font-size: 16px;
  font-weight: 500;
  color: #6b7280;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 240px;
  gap: 20px;
  align-items: start;
}

.form-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e8ecf0;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: #111827;
}
.section-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  flex-shrink: 0;
}

.product-search-wrap {
  position: relative;
}
.input-addon {
  display: flex;
  align-items: center;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  overflow: hidden;
  transition: border-color 0.15s;
  background: #fff;
}
.input-addon:focus-within {
  border-color: #10b981;
}
.input-addon.is-error {
  border-color: #ef4444;
}
.search-icon {
  color: #9ca3af;
  margin-left: 10px;
  flex-shrink: 0;
}
.input-addon input {
  flex: 1;
  border: none;
  padding: 9px 10px;
  font-size: 13.5px;
  outline: none;
  font-family: inherit;
  color: #374151;
  background: transparent;
}
.input-addon input.has-value {
  font-weight: 600;
  color: #059669;
}
.clear-btn {
  padding: 0 12px;
  border: none;
  background: none;
  font-size: 18px;
  color: #9ca3af;
  cursor: pointer;
  line-height: 1;
  align-self: stretch;
}
.clear-btn:hover {
  color: #374151;
}

.product-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #fff;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  z-index: 100;
  max-height: 300px;
  overflow-y: auto;
}
.dd-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 16px;
  color: #9ca3af;
  font-size: 13px;
}
.dd-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  width: 100%;
  border: none;
  background: transparent;
  cursor: pointer;
  font-family: inherit;
  text-align: left;
  transition: background 0.1s;
}
.dd-item:hover {
  background: #f9fafb;
}
.dd-img {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  flex-shrink: 0;
  overflow: hidden;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 700;
  color: #9ca3af;
}
.dd-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.dd-info {
  flex: 1;
  min-width: 0;
}
.dd-name {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.dd-meta {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-top: 2px;
  flex-wrap: wrap;
}
.dd-meta code {
  font-size: 11px;
  background: #f3f4f6;
  padding: 1px 5px;
  border-radius: 4px;
  color: #374151;
}
.dd-tag {
  font-size: 10.5px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 10px;
  text-transform: capitalize;
}
.dd-tag.flower {
  background: #ede9fe;
  color: #7c3aed;
}
.dd-tag.color {
  background: #fce7f3;
  color: #be185d;
}
.dd-stock {
  font-size: 15px;
  font-weight: 700;
  text-align: right;
  flex-shrink: 0;
  line-height: 1.2;
}
.dd-stock span {
  font-size: 10px;
  font-weight: 400;
  color: #9ca3af;
  display: block;
}
.dd-stock.ok {
  color: #111827;
}
.dd-stock.warn {
  color: #f59e0b;
}
.dd-stock.danger {
  color: #ef4444;
}

.selected-strip {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  background: #f0fdf4;
  border: 1.5px solid #a7f3d0;
  border-radius: 10px;
  padding: 10px 14px;
}
.strip-img {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  flex-shrink: 0;
  overflow: hidden;
  background: #d1fae5;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 700;
  color: #059669;
}
.strip-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.strip-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
  flex: 1;
  min-width: 0;
}
.strip-name {
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.strip-sku {
  font-family: monospace;
  font-size: 11.5px;
  color: #6b7280;
}
.strip-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}
.strip-stat {
  font-size: 11.5px;
  font-weight: 600;
}
.strip-stat.ok {
  color: #059669;
}
.strip-stat.warn {
  color: #f59e0b;
}
.strip-stat.danger {
  color: #ef4444;
}
.strip-stat.blue {
  color: #3b82f6;
}
.strip-tags {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}
.tag {
  font-size: 10.5px;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 20px;
  text-transform: capitalize;
}
.tag.flower {
  background: #ede9fe;
  color: #7c3aed;
}
.tag.color {
  background: #fce7f3;
  color: #be185d;
}
.tag.cold {
  background: #eff6ff;
  color: #1d4ed8;
}

.loading-inline {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #9ca3af;
  font-size: 13px;
}
.alert-warn {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 9px;
  padding: 10px 14px;
  font-size: 13px;
  color: #6b7280;
}
.link {
  color: #10b981;
  text-decoration: none;
  font-weight: 600;
}

.location-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 8px;
}
.loc-card {
  padding: 10px 12px;
  border-radius: 9px;
  border: 2px solid #e5e7eb;
  background: #fff;
  cursor: pointer;
  text-align: left;
  position: relative;
  font-family: inherit;
  transition: all 0.15s;
}
.loc-card:hover:not(:disabled) {
  border-color: #10b981;
  background: #f9fafb;
}
.loc-card.selected {
  border-color: #10b981;
  background: #ecfdf5;
}
.loc-card.full {
  opacity: 0.5;
  cursor: not-allowed;
}
.loc-name {
  font-size: 12.5px;
  font-weight: 700;
  color: #111827;
}
.loc-code {
  font-size: 11px;
  font-family: monospace;
  color: #9ca3af;
}
.loc-badges {
  display: flex;
  gap: 3px;
  flex-wrap: wrap;
  margin-top: 5px;
}
.loc-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 1.5px 5px;
  border-radius: 4px;
  background: #f3f4f6;
  color: #6b7280;
}
.loc-badge.cold {
  background: #eff6ff;
  color: #1d4ed8;
}
.loc-badge.danger {
  background: #fee2e2;
  color: #dc2626;
}
.loc-check {
  position: absolute;
  top: 7px;
  right: 7px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #10b981;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.field-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.field label {
  font-size: 12.5px;
  font-weight: 600;
  color: #374151;
}
.field input,
.textarea {
  padding: 9px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  font-size: 13.5px;
  color: #111827;
  outline: none;
  font-family: inherit;
  background: #fff;
  transition: border-color 0.15s;
}
.field input:focus,
.textarea:focus {
  border-color: #10b981;
}
.field input.error {
  border-color: #ef4444;
}
.textarea {
  resize: vertical;
}
.req {
  color: #ef4444;
}
.err-msg {
  font-size: 11.5px;
  color: #ef4444;
}
.field-hint {
  font-size: 11.5px;
  color: #9ca3af;
}

.qty-control {
  display: flex;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  overflow: hidden;
  background: #fff;
  transition: border-color 0.15s;
}
.qty-control:focus-within {
  border-color: #10b981;
}
.qty-control.is-error {
  border-color: #ef4444;
}
.qty-btn {
  width: 36px;
  border: none;
  background: #f9fafb;
  font-size: 18px;
  color: #374151;
  cursor: pointer;
  transition: background 0.1s;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.qty-btn:hover:not(:disabled) {
  background: #e5e7eb;
}
.qty-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.qty-control input {
  flex: 1;
  border: none;
  text-align: center;
  font-size: 16px;
  font-weight: 700;
  color: #111827;
  outline: none;
  font-family: inherit;
  padding: 9px 0;
  background: transparent;
  min-width: 0;
}

.freshness-bar-wrap {
  background: #f9fafb;
  border-radius: 9px;
  padding: 12px 14px;
}
.fb-track {
  height: 5px;
  background: #e5e7eb;
  border-radius: 3px;
  overflow: hidden;
}
.fb-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.4s;
}
.fb-fill.fresh {
  background: #10b981;
}
.fb-fill.aging {
  background: #f59e0b;
}
.fb-fill.wilting {
  background: #f97316;
}
.fb-fill.spoiled {
  background: #ef4444;
}
.fb-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 8px;
}
.fb-chip {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 20px;
  text-transform: capitalize;
}
.fb-chip.fresh {
  background: #dcfce7;
  color: #166534;
}
.fb-chip.aging {
  background: #fef9c3;
  color: #854d0e;
}
.fb-chip.wilting {
  background: #ffedd5;
  color: #9a3412;
}
.fb-chip.spoiled {
  background: #fee2e2;
  color: #991b1b;
}
.fb-days {
  font-size: 12px;
  color: #6b7280;
}

.side-panel {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.panel-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e8ecf0;
  padding: 18px;
}
.panel-title {
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 14px;
}

.preview-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}
.preview-icon {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: #ecfdf5;
  color: #059669;
  font-size: 22px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.preview-icon img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.preview-name {
  font-size: 13.5px;
  font-weight: 600;
  color: #111827;
  text-align: center;
}
.preview-sku {
  font-family: monospace;
  font-size: 12px;
  background: #f3f4f6;
  padding: 2px 8px;
  border-radius: 5px;
  color: #374151;
}
.qty-badge {
  background: #dcfce7;
  color: #16a34a;
  padding: 3px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}
.preview-meta {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  color: #6b7280;
  text-align: center;
}
.preview-meta.muted {
  color: #9ca3af;
}

.actions-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.btn-submit {
  width: 100%;
  padding: 11px;
  border-radius: 10px;
  border: none;
  background: #10b981;
  color: #fff;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.15s;
  font-family: inherit;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
}
.btn-submit:hover:not(:disabled) {
  background: #059669;
}
.btn-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.btn-cancel {
  display: block;
  width: 100%;
  padding: 10px;
  border-radius: 10px;
  border: 1.5px solid #e5e7eb;
  background: #fff;
  color: #374151;
  font-weight: 500;
  font-size: 13.5px;
  cursor: pointer;
  text-align: center;
  text-decoration: none;
  transition: background 0.15s;
}
.btn-cancel:hover {
  background: #f9fafb;
}

.checklist-card {
  display: flex;
  flex-direction: column;
  gap: 9px;
}
.check-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #9ca3af;
  transition: color 0.2s;
}
.check-row.done {
  color: #059669;
  font-weight: 600;
}
.check-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  border: 2px solid #d1d5db;
  flex-shrink: 0;
  transition: all 0.2s;
}
.check-dot.done {
  background: #10b981;
  border-color: #10b981;
}

.spinner-xs {
  width: 13px;
  height: 13px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  flex-shrink: 0;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.toast {
  position: fixed;
  bottom: 28px;
  left: 50%;
  transform: translateX(-50%);
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 500;
  z-index: 500;
  white-space: nowrap;
}
.toast.success {
  background: #111827;
  color: #fff;
}
.toast.error {
  background: #ef4444;
  color: #fff;
}
.toast.warn {
  background: #f59e0b;
  color: #fff;
}
.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.3s;
}
.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(12px);
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.2s;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: all 0.15s;
}
.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
