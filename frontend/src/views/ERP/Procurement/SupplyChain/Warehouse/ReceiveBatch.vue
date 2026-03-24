<template>
  <div class="form-page">
    
    <div class="form-header">
      <router-link
        to="/erp/procurement/supply-chain/warehouse/floor"
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
        Back to Floor View
      </router-link>
      <h1 class="form-title">Receive New Batch</h1>
    </div>

    <div class="form-grid">
      <!-- ── Left: Form ──────────────────────────────────────────────── -->
      <div class="form-card">
        <!-- Product -->
        <div class="section-title">
          <div class="section-dot"></div>
          Product
        </div>
        <div class="field">
          <label>Product <span class="req">*</span></label>
          <div class="search-select-wrap">
            <input
              v-model="productSearch"
              :class="{ error: errors.product_id }"
              placeholder="Search by name or SKU…"
              @input="onProductInput"
              @focus="showProductDropdown = true"
              @blur="onProductBlur"
              autocomplete="off"
            />
            <div
              v-if="
                showProductDropdown &&
                (productResults.length || productsLoading)
              "
              class="dropdown"
            >
              <div v-if="productsLoading" class="dd-loading">
                <div class="spinner-xs"></div>
                Searching...
              </div>
              <div
                v-else
                v-for="p in productResults"
                :key="p.id"
                class="dropdown-item"
                @mousedown.prevent="selectProduct(p)"
              >
                <div class="di-name">{{ p.product_name }}</div>
                <code class="di-sku">{{ p.sku }}</code>
              </div>
            </div>
          </div>
          <span v-if="errors.product_id" class="err-msg">{{
            errors.product_id
          }}</span>
        </div>

        <div v-if="selectedProduct" class="product-preview">
          <div class="pp-icon">
            <img
              v-if="selectedProduct.image_url"
              :src="selectedProduct.image_url"
              alt=""
            />
            <span v-else>{{ selectedProduct.product_name?.[0] }}</span>
          </div>
          <div>
            <div class="pp-name">{{ selectedProduct.product_name }}</div>
            <div class="pp-meta">
              {{ selectedProduct.sku }}
              <span v-if="selectedProduct.flower_type">
                · {{ selectedProduct.flower_type }}</span
              >
              <span v-if="selectedProduct.color">
                · {{ selectedProduct.color }}</span
              >
            </div>
          </div>
          <button class="pp-clear" @click="clearProduct">×</button>
        </div>

        <!-- Quantities & Dates -->
        <div class="section-title" style="margin-top: 8px">
          <div class="section-dot"></div>
          Quantities & Dates
        </div>
        <div class="field-group">
          <div class="field">
            <label>Quantity Received <span class="req">*</span></label>
            <input
              v-model.number="form.qty_received"
              :class="{ error: errors.qty_received }"
              type="number"
              min="1"
              placeholder="e.g. 100"
            />
            <span v-if="errors.qty_received" class="err-msg">{{
              errors.qty_received
            }}</span>
          </div>
          <div class="field">
            <label>Received Date <span class="req">*</span></label>
            <input
              v-model="form.received_date"
              :class="{ error: errors.received_date }"
              type="date"
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
            <input
              v-model="form.expiration_date"
              type="date"
              @change="calcFreshnessDays"
            />
          </div>
        </div>

        <div class="field-group">
          <div class="field">
            <label>Freshness Days</label>
            <input
              v-model.number="form.freshness_days"
              type="number"
              min="1"
              max="365"
              placeholder="Auto-calculated"
            />
            <span class="field-hint">Expected shelf life for this batch</span>
          </div>
          <div class="field">
            <label>Lot Number</label>
            <input
              v-model="form.lot_number"
              placeholder="Supplier lot / PO ref"
            />
          </div>
        </div>

        <!-- Location -->
        <div class="section-title" style="margin-top: 8px">
          <div class="section-dot"></div>
          Storage Location
        </div>

        <div v-if="locationsLoading" class="loading-inline">
          <div class="spinner-xs dark"></div>
          Loading locations...
        </div>
        <div v-else-if="!locations.length" class="alert-warn">
          No active storage locations found.
          <router-link
            to="/erp/procurement/supply-chain/warehouse/locations"
            class="link"
            >Manage →</router-link
          >
        </div>
        <div v-else class="field">
          <label>Warehouse Location</label>
          <select v-model="form.warehouse_location_id">
            <option value="">No location assigned</option>
            <option
              v-for="l in locations"
              :key="l.id"
              :value="l.id"
              :disabled="l.is_full"
            >
              {{ l.name }}
              {{ l.is_refrigerated ? "❄" : "" }}
              {{
                l.capacity_units
                  ? `(${l.current_units ?? "?"}/${l.capacity_units})`
                  : ""
              }}
              {{ l.is_full ? "— FULL" : "" }}
            </option>
          </select>
          <div v-if="selectedLocation" class="location-preview">
            <span class="lp-code">{{ selectedLocation.code }}</span>
            <span v-if="selectedLocation.is_refrigerated" class="lp-badge cold"
              >❄ Refrigerated</span
            >
            <span v-if="selectedLocation.zone" class="lp-badge">{{
              selectedLocation.zone
            }}</span>
            <span v-if="selectedLocation.capacity_units" class="lp-badge"
              >Cap: {{ selectedLocation.capacity_units }}</span
            >
          </div>
        </div>

        <!-- Notes -->
        <div class="field">
          <label>Notes</label>
          <textarea
            v-model="form.notes"
            rows="3"
            placeholder="Any notes about this batch…"
            style="resize: vertical"
          ></textarea>
        </div>
      </div>

      <!-- ── Right: Preview + Actions ───────────────────────────────── -->
      <div class="side-panel">
        <div class="panel-card preview-card">
          <div class="panel-title">Batch Preview</div>
          <div class="preview-product">
            <div class="prev-icon">
              {{ selectedProduct?.product_name?.[0] ?? "?" }}
            </div>
            <div class="prev-name">
              {{ selectedProduct?.product_name ?? "Select a product" }}
            </div>
          </div>
          <div class="preview-rows">
            <div class="prev-row">
              <span>Quantity</span>
              <span class="bold">{{ form.qty_received || 0 }} units</span>
            </div>
            <div class="prev-row">
              <span>Received</span>
              <span>{{ form.received_date || "—" }}</span>
            </div>
            <div class="prev-row">
              <span>Expires</span>
              <span :class="{ 'text-danger': !form.expiration_date }">{{
                form.expiration_date || "Not set"
              }}</span>
            </div>
            <div class="prev-row">
              <span>Freshness</span>
              <span>{{
                form.freshness_days ? form.freshness_days + " days" : "—"
              }}</span>
            </div>
            <div class="prev-row">
              <span>Location</span>
              <span>{{ selectedLocation?.name ?? "Not assigned" }}</span>
            </div>
          </div>
          <div v-if="form.freshness_days" class="preview-bar-section">
            <div class="prev-bar-label">
              <span>Initial Condition</span>
              <span class="fresh-label">Fresh ✓</span>
            </div>
            <div class="prev-bar">
              <div
                class="prev-bar-fill"
                style="width: 100%; background: #10b981"
              ></div>
            </div>
          </div>
        </div>

        <div class="panel-card actions-card">
          <button
            class="btn-submit"
            :disabled="submitting || submitted"
            @click="submit"
          >
            <div v-if="submitting" class="spinner-xs"></div>
            {{
              submitting
                ? "Receiving…"
                : submitted
                  ? "Received ✓"
                  : "Receive Batch"
            }}
          </button>
          <router-link
            to="/erp/procurement/supply-chain/warehouse/floor"
            class="btn-cancel"
            >Cancel</router-link
          >
        </div>

        <div
          v-if="
            selectedProduct?.requires_refrigeration &&
            selectedLocation &&
            !selectedLocation.is_refrigerated
          "
          class="warning-card"
        >
          <svg
            viewBox="0 0 20 20"
            fill="none"
            stroke="#b45309"
            stroke-width="1.5"
            width="18"
          >
            <path d="M10 3l7 13H3L10 3z" />
            <path d="M10 8v4M10 14h.01" />
          </svg>
          <p>
            This product requires refrigeration, but the selected location is
            not refrigerated.
          </p>
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
import { useRouter } from "vue-router";
import {
  warehouseBatchService,
  warehouseLocationService,
} from "../../../../../services/warehouseBatchService";
import { warehouseService } from "../../../../../services/warehouseService";


const router = useRouter();

const locations = ref([]);
const locationsLoading = ref(false); // ← shows loading state in UI
const submitting = ref(false);
const errors = reactive({});
const toast = ref({ show: false, type: "success", message: "" });

const productSearch = ref("");
const productResults = ref([]);
const productsLoading = ref(false);
const showProductDropdown = ref(false);
const selectedProduct = ref(null);

const form = reactive({
  product_id: null,
  qty_received: null,
  received_date: new Date().toISOString().split("T")[0],
  harvest_date: "",
  expiration_date: "",
  freshness_days: null,
  warehouse_location_id: "",
  lot_number: "",
  notes: "",
  source_order_id: null, // set from prefill — tells backend to complete the order
});

const selectedLocation = computed(
  () =>
    locations.value.find((l) => l.id === form.warehouse_location_id) ?? null,
);

// ── Product search ────────────────────────────────────────────────────────────

let searchTimer = null;

function onProductInput() {
  selectedProduct.value = null;
  form.product_id = null;
  clearTimeout(searchTimer);
  if (!productSearch.value.trim()) {
    productResults.value = [];
    return;
  }
  searchTimer = setTimeout(doSearch, 300);
}

async function doSearch() {
  productsLoading.value = true;
  try {
    // FIXED: was api.get("/vendor/products") — wrong portal, 401/empty.
    // Now uses warehouseService.searchProducts → GET /procurement/inventory/products
    const res = await warehouseService.searchProducts({
      search: productSearch.value,
      per_page: 10,
    });
    const body = res.data;
    const raw = body?.data?.data ?? body?.data ?? body ?? [];
    productResults.value = raw.map((p) => ({
      ...p,
      stock_count: p.stock_count ?? p.quantity_in_stock ?? 0,
    }));
  } catch (e) {
    console.error("Product search failed:", e);
    productResults.value = [];
  } finally {
    productsLoading.value = false;
  }
}

function selectProduct(p) {
  selectedProduct.value = p;
  form.product_id = p.id;
  productSearch.value = p.product_name;
  showProductDropdown.value = false;
  if (p.freshness_days) form.freshness_days = p.freshness_days;
}

function clearProduct() {
  selectedProduct.value = null;
  form.product_id = null;
  productSearch.value = "";
  productResults.value = [];
}

function onProductBlur() {
  setTimeout(() => {
    showProductDropdown.value = false;
  }, 150);
}

// ── Freshness auto-calc ───────────────────────────────────────────────────────

function calcFreshnessDays() {
  if (form.expiration_date && form.received_date) {
    const diff = Math.ceil(
      (new Date(form.expiration_date) - new Date(form.received_date)) /
        86400000,
    );
    if (diff > 0) form.freshness_days = diff;
  }
}

// ── Validation & submit ───────────────────────────────────────────────────────

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  if (!form.product_id) errors.product_id = "Product is required";
  if (!form.qty_received || form.qty_received < 1)
    errors.qty_received = "Must be at least 1";
  if (!form.received_date) errors.received_date = "Date is required";
  return !Object.keys(errors).length;
}

const submitted = ref(false); // guard: prevent double-submit / resubmit after success

async function submit() {
  if (!validate()) return;
  if (submitted.value) return; // already succeeded — block resubmit
  submitting.value = true;
  try {
    const payload = { ...form };
    if (!payload.warehouse_location_id) delete payload.warehouse_location_id;
    if (!payload.harvest_date) delete payload.harvest_date;
    if (!payload.expiration_date) delete payload.expiration_date;
    if (!payload.freshness_days) delete payload.freshness_days;
    if (!payload.lot_number) delete payload.lot_number;
    if (!payload.notes) delete payload.notes;
    if (!payload.source_order_id) delete payload.source_order_id;
    // source_order_id is kept when set — backend uses it to complete the order

    await warehouseBatchService.receive(payload);
    submitted.value = true; // lock out further submits
    showToast("Batch received successfully!");
    setTimeout(
      () => router.push("/erp/procurement/supply-chain/warehouse/floor"),
      1200,
    );
  } catch (e) {
    Object.assign(errors, e?.errors ?? {});
    showToast(
      e?.response?.data?.message ?? e?.message ?? "Failed to receive batch",
      "error",
    );
  } finally {
    submitting.value = false;
  }
}

function showToast(msg, type = "success") {
  toast.value = { show: true, type, message: msg };
  setTimeout(() => (toast.value.show = false), 3000);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(async () => {
  // Load warehouse locations
  // FIXED: warehouseLocationService now points to the correct backend path
  // (see warehouseBatchService.js: LOC = "/procurement/supply-chain/warehouse/locations")
  locationsLoading.value = true;
  try {
    const res = await warehouseLocationService.list();
    locations.value = res.data?.data ?? res.data ?? res ?? [];
  } catch (e) {
    console.error("Failed to load locations:", e);
  } finally {
    locationsLoading.value = false;
  }

  // Pre-fill from Order Detail navigation
  const raw = history.state?.prefill;
  if (!raw) return;
  try {
    const prefill = JSON.parse(raw);
    const firstItem = prefill.items?.[0];
    if (!firstItem) return;

    form.qty_received = firstItem.quantity;
    form.lot_number = prefill.order_number;
    form.source_order_id = prefill.order_id ?? null; // ← auto-complete the order on submit
    form.notes = `Received from Order ${prefill.order_number} — ${prefill.supplier?.company_name ?? ""}`;

    // ── Case 1: product_id is present on the item (migration has run) ──
    // Set the product directly — no API call needed.
    if (firstItem.product_id) {
      selectProduct({
        id: firstItem.product_id,
        product_name: firstItem.product_name,
        sku: firstItem.sku ?? "",
        flower_type: firstItem.flower_type ?? null,
        color: firstItem.color ?? null,
        image_url: firstItem.image_url ?? null,
        requires_refrigeration: firstItem.requires_refrigeration ?? false,
        stock_count: firstItem.quantity_in_stock ?? 0,
      });
      return;
    }

    // ── Case 2: product_id missing — use doSearch() directly ──
    // Reuse the same function the manual search input uses so there is
    // one code path and one set of response-parsing logic.
    productSearch.value = firstItem.product_name;
    await doSearch();

    const needle = firstItem.product_name.trim().toLowerCase();
    const match =
      productResults.value.find(
        (p) => p.product_name?.trim().toLowerCase() === needle,
      ) ??
      productResults.value.find((p) =>
        p.product_name?.trim().toLowerCase().includes(needle),
      ) ??
      productResults.value[0];

    if (match) {
      selectProduct(match);
    } else {
      console.warn(
        "[ReceiveBatch] prefill: no match for",
        firstItem.product_name,
        "— results:",
        productResults.value.length,
      );
      showProductDropdown.value = productResults.value.length > 0;
    }
  } catch (e) {
    console.error("Prefill parse error:", e);
  }
});
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
.field select,
.field textarea {
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
.field select:focus,
.field textarea:focus {
  border-color: #10b981;
}
.field input.error {
  border-color: #ef4444;
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
  margin-left: 4px;
}

.search-select-wrap {
  position: relative;
}
.dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #fff;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  z-index: 100;
  max-height: 220px;
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
.dropdown-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  cursor: pointer;
}
.dropdown-item:hover {
  background: #f9fafb;
}
.di-name {
  font-size: 13.5px;
  font-weight: 500;
  color: #111827;
}
.di-sku {
  font-family: monospace;
  font-size: 12px;
  background: #f3f4f6;
  padding: 2px 6px;
  border-radius: 4px;
  color: #6b7280;
}

.product-preview {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #f0fdf4;
  border: 1.5px solid #a7f3d0;
  border-radius: 10px;
  padding: 12px;
}
.pp-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: #ecfdf5;
  color: #059669;
  font-size: 18px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
}
.pp-icon img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.pp-name {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}
.pp-meta {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}
.pp-clear {
  margin-left: auto;
  border: none;
  background: #e5e7eb;
  border-radius: 6px;
  width: 26px;
  height: 26px;
  cursor: pointer;
  font-size: 16px;
  color: #374151;
}

.location-preview {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 6px;
}
.lp-code {
  font-family: monospace;
  font-size: 12px;
  background: #f3f4f6;
  padding: 2px 7px;
  border-radius: 5px;
  color: #374151;
}
.lp-badge {
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 11px;
  background: #e0f2fe;
  color: #0369a1;
}
.lp-badge.cold {
  background: #dbeafe;
  color: #1d4ed8;
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

.preview-product {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
}
.prev-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: #ecfdf5;
  color: #059669;
  font-size: 22px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.prev-name {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  text-align: center;
}

.preview-rows {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.prev-row {
  display: flex;
  justify-content: space-between;
  font-size: 12.5px;
}
.prev-row span:first-child {
  color: #6b7280;
}
.prev-row .bold {
  font-weight: 600;
  color: #111827;
}
.text-danger {
  color: #ef4444 !important;
}

.preview-bar-section {
  margin-top: 12px;
}
.prev-bar-label {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}
.fresh-label {
  color: #16a34a;
  font-weight: 600;
}
.prev-bar {
  height: 5px;
  background: #f3f4f6;
  border-radius: 4px;
  overflow: hidden;
}
.prev-bar-fill {
  height: 100%;
  border-radius: 4px;
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
  font-family: "Poppins", sans-serif;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
}
.btn-submit:hover:not(:disabled) {
  background: #059669;
}
.btn-submit:disabled {
  opacity: 0.6;
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
  text-align: center;
  text-decoration: none;
}

.warning-card {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  background: #fffbeb;
  border: 1.5px solid #fcd34d;
  border-radius: 10px;
  padding: 12px;
}
.warning-card p {
  font-size: 12.5px;
  color: #92400e;
  margin: 0;
  line-height: 1.5;
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
.spinner-xs.dark {
  border-color: #e5e7eb;
  border-top-color: #6b7280;
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
.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.3s;
}
.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(12px);
}
</style>
