<template>
  <div class="floor-page">
    <!-- ── Header ── -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Warehouse Floor</h1>
        <p class="page-sub">Live freshness overview of all active batches</p>
      </div>
      <div class="header-actions"></div>
    </div>

    <!-- ── Summary Strip ───────────────────────────────────────────────── -->
    <div class="summary-strip">
      <button
        v-for="s in summaryCards"
        :key="s.key"
        class="sum-card"
        :class="{ active: conditionFilter === s.key }"
        @click="toggleConditionFilter(s.key)"
      >
        <div class="sum-dot" :style="{ background: s.dotColor }"></div>
        <div>
          <div
            class="sum-val"
            :style="{
              color: conditionFilter === s.key ? s.dotColor : '#111827',
            }"
          >
            {{ summary[s.key] ?? 0 }}
          </div>
          <div class="sum-lbl">{{ s.label }}</div>
        </div>
        <div
          v-if="s.key === 'expiring_today' && (summary.expiring_today ?? 0) > 0"
          class="pulse-dot"
        ></div>
      </button>
    </div>

    <!-- ── Filters ─────────────────────────────────────────────────────── -->
    <div class="filter-bar">
      <div class="search-box">
        <svg
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          width="14"
        >
          <circle cx="9" cy="9" r="6" />
          <path d="m14 14 3 3" />
        </svg>
        <input
          v-model="search"
          placeholder="Search product, SKU, batch…"
          @input="debouncedFetch"
        />
      </div>
      <select
        v-model="locationFilter"
        class="filter-select"
        @change="fetchFloor"
      >
        <option value="">All Locations</option>
        <option v-for="l in locations" :key="l.id" :value="l.id">
          {{ l.name }}
        </option>
      </select>
      <select
        v-model="conditionFilter"
        class="filter-select"
        @change="fetchFloor"
      >
        <option value="">All Conditions</option>
        <option value="fresh">Fresh</option>
        <option value="aging">Aging</option>
        <option value="wilting">Wilting</option>
        <option value="spoiled">Spoiled</option>
      </select>
      <button v-if="hasFilters" class="clear-btn" @click="clearFilters">
        <svg
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          width="13"
        >
          <path d="M6 6l8 8M14 6l-8 8" />
        </svg>
        Clear
      </button>
    </div>

    <!-- ── Batch Grid ──────────────────────────────────────────────────── -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Loading batches…</span>
    </div>

    <div v-else-if="!batches.length" class="empty-state">
      <svg
        viewBox="0 0 48 48"
        fill="none"
        stroke="#d1d5db"
        stroke-width="1.5"
        width="52"
      >
        <rect x="8" y="16" width="32" height="26" rx="3" />
        <path d="M16 16V12a8 8 0 0116 0v4" />
        <path d="M24 28v4M24 24v1" />
      </svg>
      <p>No batches found</p>
      <router-link to="/erp/warehouse/batches/receive" class="btn-primary"
        >Receive First Batch</router-link
      >
    </div>

    <div v-else class="batch-grid">
      <div
        v-for="batch in batches"
        :key="batch.id"
        class="batch-card"
        :class="batch.computed_condition"
        @click="openDetail(batch)"
      >
        <!-- Condition stripe -->
        <div
          class="condition-stripe"
          :style="{ background: conditionMeta(batch.computed_condition).dot }"
        ></div>

        <!-- Card header -->
        <div class="card-top">
          <div class="product-thumb">
            <img
              v-if="batch.product?.image_url"
              :src="batch.product.image_url"
              :alt="batch.product?.product_name"
            />
            <span v-else>{{ batch.product?.product_name?.[0] ?? "?" }}</span>
          </div>
          <div class="card-title-group">
            <div class="card-product">
              {{ batch.product?.product_name ?? "—" }}
            </div>
            <code class="card-batch">{{ batch.batch_number }}</code>
          </div>
          <span
            class="condition-pill"
            :style="{
              background: conditionMeta(batch.computed_condition).bg,
              color: conditionMeta(batch.computed_condition).text,
              border: `1px solid ${conditionMeta(batch.computed_condition).border}`,
            }"
          >
            <span
              class="pill-dot"
              :style="{
                background: conditionMeta(batch.computed_condition).dot,
              }"
            ></span>
            {{ conditionMeta(batch.computed_condition).label }}
          </span>
        </div>

        <!-- Stats row -->
        <div class="card-stats">
          <div class="stat">
            <span class="stat-val">{{ batch.qty_remaining }}</span>
            <span class="stat-lbl">Units</span>
          </div>
          <div class="stat-sep"></div>
          <div class="stat">
            <span class="stat-val" :class="daysClass(batch.days_remaining)">
              {{ daysLabel(batch.days_remaining) }}
            </span>
            <span class="stat-lbl">Freshness</span>
          </div>
          <div class="stat-sep"></div>
          <div class="stat">
            <span class="stat-val">{{ batch.location?.name ?? "—" }}</span>
            <span class="stat-lbl">Location</span>
          </div>
        </div>

        <!-- Freshness bar -->
        <div
          v-if="batch.days_remaining !== null && batch.freshness_days"
          class="freshness-bar-wrap"
        >
          <div class="freshness-bar">
            <div
              class="freshness-fill"
              :style="{
                width: freshnessPercent(batch) + '%',
                background: conditionMeta(batch.computed_condition).dot,
              }"
            ></div>
          </div>
          <span class="freshness-pct">{{ freshnessPercent(batch) }}%</span>
        </div>

        <!-- Card footer -->
        <div class="card-footer">
          <span class="footer-loc">
            <svg
              viewBox="0 0 12 16"
              fill="currentColor"
              width="9"
              style="color: #9ca3af"
            >
              <path
                d="M6 0C3.24 0 1 2.24 1 5c0 3.75 5 11 5 11s5-7.25 5-11c0-2.76-2.24-5-5-5zm0 7.5A2.5 2.5 0 113.5 5 2.5 2.5 0 016 7.5z"
              />
            </svg>
            {{ batch.storage_location ?? "No location" }}
          </span>
          <span class="footer-date"
            >Rec. {{ formatDate(batch.received_date) }}</span
          >
        </div>
      </div>
    </div>

    <!-- ── Batch Detail Drawer ─────────────────────────────────────────── -->
    <transition name="drawer-slide">
      <div
        v-if="activeDrawer"
        class="drawer-overlay"
        @click.self="activeDrawer = null"
      >
        <div class="drawer">
          <BatchDetailDrawer
            :batch="activeDrawer"
            :locations="locations"
            @close="activeDrawer = null"
            @updated="onBatchUpdated"
          />
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import {
  warehouseBatchService,
  warehouseLocationService,
  conditionMeta,
  daysRemainingLabel,
  daysRemainingClass,
} from "../../../../../services/warehouseBatchService";
import BatchDetailDrawer from "./BatchDetailDrawer.vue";

const batches = ref([]);
const summary = ref({});
const locations = ref([]);
const loading = ref(false);
const search = ref("");
const locationFilter = ref("");
const conditionFilter = ref("");
const activeDrawer = ref(null);

const summaryCards = [
  { key: "fresh", label: "Fresh", dotColor: "#16a34a" },
  { key: "aging", label: "Aging", dotColor: "#ca8a04" },
  { key: "wilting", label: "Wilting", dotColor: "#ea580c" },
  { key: "spoiled", label: "Spoiled", dotColor: "#dc2626" },
  { key: "expiring_today", label: "Exp. Today", dotColor: "#ef4444" },
];

const hasFilters = computed(
  () => search.value || locationFilter.value || conditionFilter.value,
);

function toggleConditionFilter(key) {
  if (key === "expiring_today") return; // not a filterable condition
  conditionFilter.value = conditionFilter.value === key ? "" : key;
  fetchFloor();
}

function clearFilters() {
  search.value = "";
  locationFilter.value = "";
  conditionFilter.value = "";
  fetchFloor();
}

let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(fetchFloor, 400);
}

async function fetchFloor() {
  loading.value = true;
  try {
    const params = {};
    if (search.value) params.search = search.value;
    if (locationFilter.value) params.location_id = locationFilter.value;
    if (conditionFilter.value) params.condition = conditionFilter.value;
    const res = await warehouseBatchService.floorView(params);
    batches.value = res.data?.batches ?? res.batches ?? [];
    summary.value = res.data?.summary ?? res.summary ?? {};
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function fetchLocations() {
  try {
    const res = await warehouseLocationService.list();
    locations.value = res.data?.data ?? res.data ?? res ?? [];
  } catch (e) {
    console.error(e);
  }
}

function openDetail(batch) {
  activeDrawer.value = batch;
}

function onBatchUpdated(updated) {
  const idx = batches.value.findIndex((b) => b.id === updated.id);
  if (idx !== -1) batches.value[idx] = updated;
  activeDrawer.value = updated;
}

function freshnessPercent(batch) {
  if (!batch.freshness_days || batch.days_remaining === null) return 0;
  return Math.max(
    0,
    Math.min(
      100,
      Math.round((batch.days_remaining / batch.freshness_days) * 100),
    ),
  );
}

function daysLabel(days) {
  return daysRemainingLabel(days);
}
function daysClass(days) {
  return daysRemainingClass(days);
}
function formatDate(d) {
  return d
    ? new Date(d).toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
      })
    : "—";
}
const { conditionMeta: _cm } = { conditionMeta };

onMounted(() => {
  fetchFloor();
  fetchLocations();
});
</script>

<script>
// Re-export so template can use it
import {
  conditionMeta,
  daysRemainingLabel,
  daysRemainingClass,
} from "../../../../../services/warehouseBatchService";
export default {
  methods: { conditionMeta, daysRemainingLabel, daysRemainingClass },
};
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.floor-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Header */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}
.page-title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.page-sub {
  font-size: 13px;
  color: #6b7280;
  margin: 2px 0 0;
}
.header-actions {
  display: flex;
  gap: 10px;
  align-items: center;
}
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 18px;
  background: #10b981;
  color: #fff;
  border: none;
  border-radius: 9px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.15s;
}
.btn-primary:hover {
  background: #059669;
}
.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 16px;
  background: #fff;
  color: #374151;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  text-decoration: none;
}
.btn-secondary:hover {
  background: #f9fafb;
}

/* Summary strip */
.summary-strip {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
}
.sum-card {
  background: #fff;
  border: 1.5px solid #e8ecf0;
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  position: relative;
  transition:
    border-color 0.15s,
    box-shadow 0.15s;
  text-align: left;
}
.sum-card.active {
  border-color: #10b981;
  box-shadow: 0 0 0 3px #d1fae5;
}
.sum-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.sum-val {
  font-size: 22px;
  font-weight: 700;
  line-height: 1;
}
.sum-lbl {
  font-size: 11.5px;
  color: #6b7280;
  margin-top: 2px;
}
.pulse-dot {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #ef4444;
  animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse {
  0%,
  100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.4);
    opacity: 0.6;
  }
}

/* Filters */
.filter-bar {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}
.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #fff;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  flex: 1;
  min-width: 220px;
}
.search-box input {
  border: none;
  outline: none;
  font-size: 13.5px;
  color: #111827;
  background: none;
  width: 100%;
  font-family: inherit;
}
.filter-select {
  padding: 8px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  font-size: 13px;
  color: #374151;
  background: #fff;
  outline: none;
  cursor: pointer;
}
.clear-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 8px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  background: #fff;
  color: #6b7280;
  font-size: 13px;
  cursor: pointer;
}
.clear-btn:hover {
  background: #f9fafb;
  color: #374151;
}

/* States */
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 80px 20px;
  color: #9ca3af;
}
.empty-state p {
  font-size: 15px;
  margin: 0;
}
.spinner {
  width: 30px;
  height: 30px;
  border: 3px solid #e5e7eb;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Batch grid */
.batch-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 14px;
}
.batch-card {
  background: #fff;
  border: 1.5px solid #e8ecf0;
  border-radius: 14px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  cursor: pointer;
  transition:
    box-shadow 0.2s,
    transform 0.2s;
  position: relative;
  overflow: hidden;
}
.batch-card:hover {
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.09);
  transform: translateY(-2px);
}
.batch-card.spoiled {
  border-color: #fca5a5;
}
.batch-card.wilting {
  border-color: #fdba74;
}

.condition-stripe {
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  bottom: 0;
  border-radius: 14px 0 0 14px;
}

.card-top {
  display: flex;
  align-items: center;
  gap: 10px;
}
.product-thumb {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 16px;
  color: #374151;
  overflow: hidden;
  flex-shrink: 0;
}
.product-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.card-title-group {
  flex: 1;
  min-width: 0;
}
.card-product {
  font-size: 13.5px;
  font-weight: 600;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.card-batch {
  font-family: monospace;
  font-size: 11px;
  background: #f3f4f6;
  padding: 1px 5px;
  border-radius: 4px;
  color: #6b7280;
}

.condition-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
  flex-shrink: 0;
}
.pill-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.card-stats {
  display: flex;
  align-items: center;
}
.stat {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}
.stat-val {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}
.stat-val.ok {
  color: #16a34a;
}
.stat-val.warn {
  color: #ca8a04;
}
.stat-val.warn-red {
  color: #ea580c;
}
.stat-val.danger {
  color: #dc2626;
}
.stat-lbl {
  font-size: 10.5px;
  color: #9ca3af;
}
.stat-sep {
  width: 1px;
  height: 28px;
  background: #e5e7eb;
}

.freshness-bar-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}
.freshness-bar {
  flex: 1;
  height: 4px;
  background: #f3f4f6;
  border-radius: 4px;
  overflow: hidden;
}
.freshness-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.4s;
}
.freshness-pct {
  font-size: 11px;
  color: #6b7280;
  min-width: 30px;
  text-align: right;
}

.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11.5px;
  color: #9ca3af;
  border-top: 1px solid #f3f4f6;
  padding-top: 8px;
}
.footer-loc {
  display: flex;
  align-items: center;
  gap: 4px;
}

/* Drawer */
.drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 300;
  display: flex;
  align-items: stretch;
  justify-content: flex-end;
}
.drawer {
  width: 480px;
  background: #fff;
  height: 100%;
  overflow-y: auto;
  box-shadow: -8px 0 40px rgba(0, 0, 0, 0.15);
}

.drawer-slide-enter-active,
.drawer-slide-leave-active {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.drawer-slide-enter-from,
.drawer-slide-leave-to {
  opacity: 0;
  transform: translateX(40px);
}
</style>
