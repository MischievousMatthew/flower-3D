<template>
  <div class="inv-page">
    

    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">
          {{ selectedWarehouse ? selectedWarehouse.name : "Warehouse Storage" }}
        </h1>
        <p class="page-sub">
          {{
            selectedWarehouse
              ? "Storage locations linked to this warehouse"
              : "Select a warehouse to view its storage locations"
          }}
        </p>
      </div>
      <div class="header-actions">
        <router-link
          to="/erp/procurement/supply-chain/warehouse/floor"
          class="btn-secondary"
        >
          <svg
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            width="15"
          >
            <rect x="2" y="2" width="7" height="7" rx="1.5" />
            <rect x="11" y="2" width="7" height="7" rx="1.5" />
            <rect x="2" y="11" width="7" height="7" rx="1.5" />
            <rect x="11" y="11" width="7" height="7" rx="1.5" />
          </svg>
          Full Floor View
        </router-link>
      </div>
    </div>

    <!-- Summary cards (shown when warehouse selected) -->
    <div v-if="whFilter && locations.length" class="summary-strip">
      <div class="sum-card">
        <div class="sum-icon teal">
          <svg
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            width="18"
          >
            <path d="M2 5h16M2 10h16M2 15h10" />
          </svg>
        </div>
        <div>
          <div class="sum-val">{{ locations.length }}</div>
          <div class="sum-lbl">Locations</div>
        </div>
      </div>
      <div class="sum-card">
        <div class="sum-icon blue">
          <svg
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            width="18"
          >
            <path
              d="M10 2v16M2 10h16M4.93 4.93l10.14 10.14M15.07 4.93L4.93 15.07"
            />
          </svg>
        </div>
        <div>
          <div class="sum-val">{{ summary.refrigerated }}</div>
          <div class="sum-lbl">Refrigerated</div>
        </div>
      </div>
      <div class="sum-card">
        <div class="sum-icon green">
          <svg
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            width="18"
          >
            <rect x="3" y="3" width="14" height="14" rx="2" />
            <path d="M7 10l2 2 4-4" />
          </svg>
        </div>
        <div>
          <div class="sum-val">{{ summary.active_batches }}</div>
          <div class="sum-lbl">Active Batches</div>
        </div>
      </div>
      <div class="sum-card">
        <div class="sum-icon purple">
          <svg
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            width="18"
          >
            <path d="M2 10h16M2 6h16M2 14h10" />
          </svg>
        </div>
        <div>
          <div class="sum-val">{{ summary.total_units }}</div>
          <div class="sum-lbl">Total Units</div>
        </div>
      </div>
    </div>

    <!-- Table Card -->
    <div class="card">
      <!-- Toolbar -->
      <div class="card-toolbar">
        <div class="toolbar-left">
          <select
            v-model="whFilter"
            class="select-filter"
            @change="onWarehouseChange"
          >
            <option value="">Select Warehouse…</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">
              {{ w.name }}
            </option>
          </select>
          <div v-if="whFilter" class="search-box">
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
              v-model="searchQuery"
              placeholder="Search location…"
              @input="applySearch"
            />
          </div>
        </div>
      </div>

      <!-- Table -->
      <table class="data-table">
        <thead>
          <tr>
            <th>Location</th>
            <th>Code</th>
            <th>Zone</th>
            <th>Type</th>
            <th>Capacity</th>
            <th>Batches</th>
            <th>Condition Breakdown</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="8" class="loading-cell">
              <div class="spinner"></div>
            </td>
          </tr>
          <tr v-else-if="!whFilter">
            <td colspan="8" class="empty-cell">
              Select a warehouse to view its storage locations
            </td>
          </tr>
          <tr v-else-if="!filteredLocations.length">
            <td colspan="8" class="empty-cell">
              No storage locations linked to this warehouse yet.
              <router-link
                to="/erp/procurement/supply-chain/warehouses"
                class="link"
              >
                Go link one →
              </router-link>
            </td>
          </tr>
          <tr v-for="loc in filteredLocations" :key="loc.id" class="data-row">
            <!-- Location name -->
            <td>
              <div class="loc-cell">
                <div
                  class="loc-icon"
                  :class="loc.is_refrigerated ? 'cold' : 'warm'"
                >
                  <svg
                    v-if="loc.is_refrigerated"
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="14"
                  >
                    <path
                      d="M10 2v16M2 10h16M4.93 4.93l10.14 10.14M15.07 4.93L4.93 15.07"
                    />
                  </svg>
                  <svg
                    v-else
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="14"
                  >
                    <rect x="2" y="4" width="16" height="13" rx="2" />
                    <path d="M2 9h16" />
                  </svg>
                </div>
                <div>
                  <div class="loc-name">{{ loc.name }}</div>
                  <div v-if="loc.is_full" class="loc-full-tag">Full</div>
                </div>
              </div>
            </td>

            <!-- Code -->
            <td>
              <code class="code-pill">{{ loc.code }}</code>
            </td>

            <!-- Zone -->
            <td class="muted">{{ loc.zone || "—" }}</td>

            <!-- Type -->
            <td>
              <span
                class="type-chip"
                :class="loc.is_refrigerated ? 'cold' : 'normal'"
              >
                {{ loc.is_refrigerated ? "❄ Refrigerated" : "Standard" }}
              </span>
            </td>

            <!-- Capacity -->
            <td>
              <div v-if="loc.capacity_units" class="cap-cell">
                <div class="cap-bar">
                  <div
                    class="cap-fill"
                    :class="capacityClass(loc.capacity_pct)"
                    :style="{
                      width: Math.min(loc.capacity_pct ?? 0, 100) + '%',
                    }"
                  ></div>
                </div>
                <span class="cap-text" :class="capacityClass(loc.capacity_pct)">
                  {{ loc.current_units ?? 0 }}/{{ loc.capacity_units }}
                </span>
              </div>
              <span v-else class="muted"
                >{{ loc.current_units ?? 0 }} stored</span
              >
            </td>

            <!-- Active batches -->
            <td>
              <span
                class="batch-num"
                :class="
                  (loc.active_batches ?? 0) > 0 ? 'has-batches' : 'no-batches'
                "
              >
                {{ loc.active_batches ?? 0 }}
              </span>
            </td>

            <!-- Condition breakdown -->
            <td>
              <div class="cond-row">
                <div class="cond-item">
                  <span class="cond-dot fresh"></span>
                  <span class="cond-num">{{
                    loc.batch_summary?.fresh ?? 0
                  }}</span>
                </div>
                <div class="cond-item">
                  <span class="cond-dot aging"></span>
                  <span class="cond-num">{{
                    loc.batch_summary?.aging ?? 0
                  }}</span>
                </div>
                <div class="cond-item">
                  <span class="cond-dot wilting"></span>
                  <span class="cond-num">{{
                    loc.batch_summary?.wilting ?? 0
                  }}</span>
                </div>
                <div class="cond-item">
                  <span class="cond-dot spoiled"></span>
                  <span class="cond-num">{{
                    loc.batch_summary?.spoiled ?? 0
                  }}</span>
                </div>
              </div>
            </td>

            <!-- Actions -->
            <td>
              <div class="action-cell">
                <!-- Eye → FloorView filtered to this location -->
                <button
                  class="action-btn eye"
                  title="View products in this storage"
                  @click="goToFloor(loc)"
                >
                  <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="14"
                  >
                    <path d="M1 10s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7z" />
                    <circle cx="10" cy="10" r="3" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";

import { warehouseService } from "../../../../../services/warehouseService";
import { warehouseLocationService } from "../../../../../services/warehouseBatchService";

const router = useRouter();
const route = useRoute();

const warehouses = ref([]);
const locations = ref([]);
const loading = ref(false);
const whFilter = ref("");
const searchQuery = ref("");

// ── Computed ──────────────────────────────────────────────────────────────────

const selectedWarehouse = computed(
  () => warehouses.value.find((w) => w.id == whFilter.value) ?? null,
);

const summary = computed(() => ({
  refrigerated: locations.value.filter((l) => l.is_refrigerated).length,
  active_batches: locations.value.reduce(
    (s, l) => s + (l.active_batches ?? 0),
    0,
  ),
  total_units: locations.value.reduce((s, l) => s + (l.current_units ?? 0), 0),
}));

const filteredLocations = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return locations.value;
  return locations.value.filter(
    (l) =>
      l.name?.toLowerCase().includes(q) ||
      l.code?.toLowerCase().includes(q) ||
      l.zone?.toLowerCase().includes(q),
  );
});

// ── Fetch ─────────────────────────────────────────────────────────────────────

async function fetchWarehouses() {
  try {
    const res = await warehouseService.list();
    warehouses.value = res.data?.data ?? res.data ?? res ?? [];
  } catch (e) {
    console.error("fetchWarehouses", e);
  }
}

async function fetchLocations() {
  if (!whFilter.value) {
    locations.value = [];
    return;
  }
  loading.value = true;
  try {
    // Fetch all locations filtered by warehouse_id.
    // warehouseLocationService.list() → GET /warehouse/locations?warehouse_id=X&with_stats=true
    const res = await warehouseLocationService.list({
      warehouse_id: whFilter.value,
      with_stats: true,
    });
    locations.value = res.data?.data ?? res.data ?? res ?? [];
  } catch (e) {
    console.error("fetchLocations", e);
    locations.value = [];
  } finally {
    loading.value = false;
  }
}

function onWarehouseChange() {
  searchQuery.value = "";
  fetchLocations();
}

function applySearch() {
  // filteredLocations is computed — no extra fetch needed
}

// ── Navigation ────────────────────────────────────────────────────────────────

// Eye icon → FloorView pre-filtered to this storage location
function goToFloor(loc) {
  router.push({
    name: "WarehouseFloor",
    query: { location_id: loc.id },
  });
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function capacityClass(pct) {
  if (!pct && pct !== 0) return "";
  if (pct >= 90) return "danger";
  if (pct >= 70) return "warn";
  return "ok";
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(async () => {
  await fetchWarehouses();

  const q = route.query.warehouse;
  if (q) {
    whFilter.value = Number(q);
    await fetchLocations();
  }
});
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.inv-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

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
  transition: background 0.15s;
}
.btn-secondary:hover {
  background: #f9fafb;
}

/* Summary strip */
.summary-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}
.sum-card {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 12px;
  padding: 16px 18px;
  display: flex;
  align-items: center;
  gap: 14px;
}
.sum-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.sum-icon.teal {
  background: #ecfdf5;
  color: #10b981;
}
.sum-icon.blue {
  background: #eff6ff;
  color: #3b82f6;
}
.sum-icon.green {
  background: #f0fdf4;
  color: #16a34a;
}
.sum-icon.purple {
  background: #faf5ff;
  color: #7c3aed;
}
.sum-val {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  line-height: 1;
}
.sum-lbl {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

/* Card */
.card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e8ecf0;
  overflow: hidden;
}

.card-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
  border-bottom: 1px solid #f0f2f5;
}
.toolbar-left {
  display: flex;
  gap: 10px;
  align-items: center;
}

.select-filter {
  padding: 7px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  color: #374151;
  background: #fff;
  outline: none;
  cursor: pointer;
  font-family: inherit;
}
.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 12px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}
.search-box input {
  border: none;
  background: none;
  outline: none;
  font-size: 13px;
  color: #374151;
  width: 180px;
  font-family: inherit;
}

/* Table */
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.data-table th {
  padding: 10px 16px;
  text-align: left;
  font-size: 11.5px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-bottom: 1px solid #e8ecf0;
  background: #f9fafb;
}
.data-row td {
  padding: 13px 16px;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}
.data-row:last-child td {
  border-bottom: none;
}
.data-row:hover td {
  background: #fafafa;
}

.loading-cell,
.empty-cell {
  text-align: center;
  padding: 56px 16px !important;
  color: #9ca3af;
}
.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #e5e7eb;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  margin: 0 auto;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Location cell */
.loc-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}
.loc-icon {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.loc-icon.cold {
  background: #dbeafe;
  color: #1d4ed8;
}
.loc-icon.warm {
  background: #d1fae5;
  color: #059669;
}
.loc-name {
  font-weight: 600;
  color: #111827;
  font-size: 13.5px;
}
.loc-full-tag {
  display: inline-block;
  margin-top: 2px;
  font-size: 10.5px;
  font-weight: 600;
  background: #fee2e2;
  color: #dc2626;
  padding: 1px 6px;
  border-radius: 4px;
}

.code-pill {
  font-family: monospace;
  font-size: 12.5px;
  background: #f3f4f6;
  padding: 2px 7px;
  border-radius: 5px;
  color: #374151;
}
.muted {
  color: #6b7280;
  font-size: 13px;
}
.link {
  color: #10b981;
  font-weight: 600;
  text-decoration: none;
  margin-left: 4px;
}

.type-chip {
  display: inline-block;
  font-size: 11.5px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 20px;
}
.type-chip.cold {
  background: #dbeafe;
  color: #1d4ed8;
}
.type-chip.normal {
  background: #f3f4f6;
  color: #6b7280;
}

/* Capacity */
.cap-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 90px;
}
.cap-bar {
  height: 4px;
  background: #f3f4f6;
  border-radius: 4px;
  overflow: hidden;
}
.cap-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s;
}
.cap-fill.ok {
  background: #10b981;
}
.cap-fill.warn {
  background: #f59e0b;
}
.cap-fill.danger {
  background: #ef4444;
}
.cap-text {
  font-size: 12px;
  font-weight: 600;
}
.cap-text.ok {
  color: #059669;
}
.cap-text.warn {
  color: #d97706;
}
.cap-text.danger {
  color: #dc2626;
}

/* Batch count */
.batch-num {
  font-size: 16px;
  font-weight: 700;
}
.batch-num.has-batches {
  color: #111827;
}
.batch-num.no-batches {
  color: #d1d5db;
}

/* Condition dots row */
.cond-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.cond-item {
  display: flex;
  align-items: center;
  gap: 4px;
}
.cond-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.cond-dot.fresh {
  background: #16a34a;
}
.cond-dot.aging {
  background: #ca8a04;
}
.cond-dot.wilting {
  background: #ea580c;
}
.cond-dot.spoiled {
  background: #dc2626;
}
.cond-num {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

/* Actions */
.action-cell {
  display: flex;
  gap: 4px;
}
.action-btn {
  width: 30px;
  height: 30px;
  border-radius: 7px;
  border: 1px solid #e5e7eb;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #6b7280;
  transition: all 0.15s;
}
.action-btn.eye:hover {
  background: #eff6ff;
  color: #2563eb;
  border-color: #93c5fd;
}
</style>
