<template>
  <div class="loc-page">
    
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Storage Locations</h1>
        <p class="page-sub">
          Manage zones like Cooler A, Shelf B2, Cold Room 1
        </p>
      </div>
      <button class="btn-primary" @click="openCreate">
        <svg viewBox="0 0 20 20" fill="currentColor" width="15">
          <path
            d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
          />
        </svg>
        New Location
      </button>
    </div>

    <!-- Filters -->
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
          placeholder="Search locations…"
          @input="applyFilters"
        />
      </div>
      <div class="toggle-group">
        <button
          :class="['tgl', refrigFilter === '' && 'active']"
          @click="
            refrigFilter = '';
            applyFilters();
          "
        >
          All
        </button>
        <button
          :class="['tgl', refrigFilter === 'cold' && 'active']"
          @click="
            refrigFilter = 'cold';
            applyFilters();
          "
        >
          ❄ Cold
        </button>
        <button
          :class="['tgl', refrigFilter === 'ambient' && 'active']"
          @click="
            refrigFilter = 'ambient';
            applyFilters();
          "
        >
          Ambient
        </button>
      </div>
      <label class="toggle-label">
        <input
          type="checkbox"
          v-model="showInactive"
          @change="fetchLocations"
        />
        Show Inactive
      </label>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state"><div class="spinner"></div></div>

    <!-- Grid -->
    <div v-else class="loc-grid">
      <div
        v-for="loc in filtered"
        :key="loc.id"
        class="loc-card"
        :class="{ inactive: !loc.is_active }"
      >
        <!-- Top -->
        <div class="loc-top">
          <div
            class="loc-icon"
            :class="loc.is_refrigerated ? 'cold' : 'ambient'"
          >
            <svg
              v-if="loc.is_refrigerated"
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="18"
            >
              <path d="M10 2v16M2 10h16M5 5l10 10M15 5L5 15" />
            </svg>
            <svg
              v-else
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="18"
            >
              <path d="M3 12l9-7 9 7v9H3z" />
              <rect x="9" y="15" width="6" height="6" rx="1" />
            </svg>
          </div>
          <div class="loc-info">
            <h3 class="loc-name">{{ loc.name }}</h3>
            <div class="loc-meta">
              <code class="loc-code">{{ loc.code }}</code>
              <span v-if="loc.zone" class="loc-zone">{{ loc.zone }}</span>
            </div>
          </div>
          <div class="loc-badges">
            <span v-if="loc.is_refrigerated" class="badge cold"
              >❄ Refrigerated</span
            >
            <span
              class="badge"
              :class="loc.is_active ? 'active-badge' : 'inactive-badge'"
            >
              {{ loc.is_active ? "Active" : "Inactive" }}
            </span>
          </div>
        </div>

        <!-- Capacity -->
        <div v-if="loc.capacity_units" class="capacity-section">
          <div class="cap-label">
            <span>Capacity</span>
            <span :class="loc.is_full ? 'text-danger' : 'text-ok'">
              {{ loc.current_units ?? "—" }} / {{ loc.capacity_units }} units
              {{ loc.is_full ? "(FULL)" : "" }}
            </span>
          </div>
          <div class="cap-bar">
            <div
              class="cap-fill"
              :style="{
                width: capPct(loc) + '%',
                background: capColor(loc),
              }"
            ></div>
          </div>
        </div>

        <!-- Batch summary (shown if has stats) -->
        <div v-if="loc.batch_summary" class="batch-summary">
          <div class="bs-item fresh">
            <span class="bs-num">{{ loc.batch_summary.fresh }}</span>
            <span class="bs-lbl">Fresh</span>
          </div>
          <div class="bs-item aging">
            <span class="bs-num">{{ loc.batch_summary.aging }}</span>
            <span class="bs-lbl">Aging</span>
          </div>
          <div class="bs-item wilting">
            <span class="bs-num">{{ loc.batch_summary.wilting }}</span>
            <span class="bs-lbl">Wilting</span>
          </div>
          <div class="bs-item spoiled">
            <span class="bs-num">{{ loc.batch_summary.spoiled }}</span>
            <span class="bs-lbl">Spoiled</span>
          </div>
        </div>

        <!-- Actions -->
        <div class="loc-actions">
          <button class="la-btn" @click="openEdit(loc)" title="Edit">
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="14"
            >
              <path d="M13 4l3 3-9 9-4 1 1-4 9-9z" />
            </svg>
          </button>
          <button
            class="la-btn"
            @click="toggleActive(loc)"
            :title="loc.is_active ? 'Deactivate' : 'Activate'"
          >
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="14"
            >
              <circle cx="10" cy="10" r="7" />
              <path v-if="loc.is_active" d="M7 10l2 2 4-4" />
              <path v-else d="M7 7l6 6M13 7l-6 6" />
            </svg>
          </button>
          <button
            class="la-btn danger"
            @click="confirmDelete(loc)"
            title="Delete"
          >
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="14"
            >
              <path d="M5 7h10l-1 9H6L5 7zM3 7h14M8 7V5h4v2" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Empty -->
      <div v-if="!filtered.length && !loading" class="empty-card">
        <svg
          viewBox="0 0 48 48"
          fill="none"
          stroke="#d1d5db"
          stroke-width="1.5"
          width="48"
        >
          <path d="M6 24l18-14 18 14v20H6z" />
          <rect x="18" y="30" width="12" height="14" rx="2" />
        </svg>
        <p>No locations found</p>
        <button class="btn-primary" @click="openCreate">
          Add First Location
        </button>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <transition name="modal-fade">
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ editingLoc ? "Edit Location" : "New Location" }}</h3>
            <button class="modal-close" @click="closeModal">×</button>
          </div>
          <div class="modal-body">
            <div class="field-group">
              <div class="field">
                <label>Name <span class="req">*</span></label>
                <input
                  v-model="locForm.name"
                  :class="{ error: errors.name }"
                  placeholder="e.g. Cooler A"
                />
                <span v-if="errors.name" class="err-msg">{{
                  errors.name
                }}</span>
              </div>
              <div class="field">
                <label>Code <span class="req">*</span></label>
                <input
                  v-model="locForm.code"
                  :class="{ error: errors.code }"
                  placeholder="e.g. COOL-A"
                  @blur="locForm.code = locForm.code.toUpperCase()"
                />
                <span v-if="errors.code" class="err-msg">{{
                  errors.code
                }}</span>
              </div>
            </div>
            <div class="field-group">
              <div class="field">
                <label>Zone</label>
                <input
                  v-model="locForm.zone"
                  placeholder="e.g. Cold Storage, Dry Zone"
                />
              </div>
              <div class="field">
                <label>Capacity (units)</label>
                <input
                  v-model.number="locForm.capacity_units"
                  type="number"
                  min="1"
                  placeholder="Optional"
                />
              </div>
            </div>
            <div class="checkbox-row">
              <label class="checkbox-label">
                <input type="checkbox" v-model="locForm.is_refrigerated" />
                <span>Refrigerated location</span>
                <span class="check-hint">Enable for coolers, cold rooms</span>
              </label>
              <label class="checkbox-label">
                <input type="checkbox" v-model="locForm.is_active" />
                <span>Active</span>
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-cancel" @click="closeModal">Cancel</button>
            <button
              class="btn-primary"
              :disabled="saving"
              @click="saveLocation"
            >
              {{
                saving
                  ? "Saving…"
                  : editingLoc
                    ? "Save Changes"
                    : "Create Location"
              }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Delete confirm -->
    <transition name="modal-fade">
      <div
        v-if="deletingLoc"
        class="modal-overlay"
        @click.self="deletingLoc = null"
      >
        <div class="modal confirm-modal">
          <div class="modal-header">
            <h3>Delete Location?</h3>
            <button class="modal-close" @click="deletingLoc = null">×</button>
          </div>
          <div class="modal-body">
            <p class="confirm-text">
              Are you sure you want to delete
              <strong>{{ deletingLoc.name }}</strong
              >? This cannot be undone. Locations with active batches cannot be
              deleted.
            </p>
          </div>
          <div class="modal-footer">
            <button class="btn-cancel" @click="deletingLoc = null">
              Cancel
            </button>
            <button
              class="btn-danger"
              :disabled="saving"
              @click="deleteLocation"
            >
              {{ saving ? "Deleting…" : "Delete" }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <transition name="toast-slide">
      <div v-if="toast.show" class="toast" :class="toast.type">
        {{ toast.message }}
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { warehouseLocationService } from "../../../../../services/warehouseBatchService";


const locations = ref([]);
const loading = ref(false);
const saving = ref(false);
const search = ref("");
const refrigFilter = ref("");
const showInactive = ref(false);
const showModal = ref(false);
const editingLoc = ref(null);
const deletingLoc = ref(null);
const errors = reactive({});
const toast = ref({ show: false, type: "success", message: "" });

const locForm = reactive({
  name: "",
  code: "",
  zone: "",
  capacity_units: null,
  is_refrigerated: false,
  is_active: true,
});

const filtered = computed(() => {
  return locations.value.filter((l) => {
    const matchSearch =
      !search.value ||
      l.name.toLowerCase().includes(search.value.toLowerCase()) ||
      l.code.toLowerCase().includes(search.value.toLowerCase()) ||
      (l.zone ?? "").toLowerCase().includes(search.value.toLowerCase());
    const matchRefrig =
      refrigFilter.value === ""
        ? true
        : refrigFilter.value === "cold"
          ? l.is_refrigerated
          : !l.is_refrigerated;
    return matchSearch && matchRefrig;
  });
});

function applyFilters() {
  /* filtered is computed, no-op needed */
}

async function fetchLocations() {
  loading.value = true;
  try {
    const params = {};
    if (showInactive.value) params.include_inactive = 1;
    const res = await warehouseLocationService.list(params);
    locations.value = res.data?.data ?? res.data ?? res ?? [];
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editingLoc.value = null;
  Object.assign(locForm, {
    name: "",
    code: "",
    zone: "",
    capacity_units: null,
    is_refrigerated: false,
    is_active: true,
  });
  showModal.value = true;
}

function openEdit(loc) {
  editingLoc.value = loc;
  Object.assign(locForm, {
    name: loc.name,
    code: loc.code,
    zone: loc.zone ?? "",
    capacity_units: loc.capacity_units,
    is_refrigerated: loc.is_refrigerated,
    is_active: loc.is_active,
  });
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  Object.keys(errors).forEach((k) => delete errors[k]);
}

async function saveLocation() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  if (!locForm.name) {
    errors.name = "Name is required";
    return;
  }
  if (!locForm.code) {
    errors.code = "Code is required";
    return;
  }
  saving.value = true;
  try {
    if (editingLoc.value) {
      await warehouseLocationService.update(editingLoc.value.id, locForm);
      showToast("Location updated!");
    } else {
      await warehouseLocationService.create(locForm);
      showToast("Location created!");
    }
    closeModal();
    fetchLocations();
  } catch (e) {
    Object.assign(errors, e?.errors ?? {});
    showToast(e?.message ?? "Failed", "error");
  } finally {
    saving.value = false;
  }
}

async function toggleActive(loc) {
  try {
    const res = await warehouseLocationService.toggle(loc.id);
    loc.is_active = res.data?.is_active ?? !loc.is_active;
    showToast(`Location ${loc.is_active ? "activated" : "deactivated"}.`);
  } catch (e) {
    showToast(e?.message ?? "Failed", "error");
  }
}

function confirmDelete(loc) {
  deletingLoc.value = loc;
}

async function deleteLocation() {
  saving.value = true;
  try {
    await warehouseLocationService.destroy(deletingLoc.value.id);
    showToast("Location deleted.");
    deletingLoc.value = null;
    fetchLocations();
  } catch (e) {
    showToast(e?.message ?? "Cannot delete this location", "error");
    deletingLoc.value = null;
  } finally {
    saving.value = false;
  }
}

function capPct(loc) {
  if (!loc.capacity_units || !loc.current_units) return 0;
  return Math.min(
    100,
    Math.round((loc.current_units / loc.capacity_units) * 100),
  );
}
function capColor(loc) {
  const pct = capPct(loc);
  if (pct >= 90) return "#ef4444";
  if (pct >= 70) return "#f59e0b";
  return "#10b981";
}

function showToast(msg, type = "success") {
  toast.value = { show: true, type, message: msg };
  setTimeout(() => (toast.value.show = false), 3000);
}

onMounted(fetchLocations);
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.loc-page {
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
  transition: background 0.15s;
}
.btn-primary:hover {
  background: #059669;
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

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
  min-width: 200px;
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
.toggle-group {
  display: flex;
  gap: 2px;
  background: #f3f4f6;
  border-radius: 8px;
  padding: 3px;
}
.tgl {
  padding: 5px 14px;
  border-radius: 6px;
  border: none;
  background: none;
  font-size: 12.5px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.15s;
}
.tgl.active {
  background: #fff;
  color: #111827;
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
.toggle-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #374151;
  cursor: pointer;
}

.loading-state {
  display: flex;
  justify-content: center;
  padding: 80px;
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

.loc-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 14px;
}

.loc-card {
  background: #fff;
  border: 1.5px solid #e8ecf0;
  border-radius: 14px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  transition: box-shadow 0.2s;
}
.loc-card:hover {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}
.loc-card.inactive {
  opacity: 0.65;
}

.loc-top {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.loc-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.loc-icon.cold {
  background: #dbeafe;
  color: #2563eb;
}
.loc-icon.ambient {
  background: #ecfdf5;
  color: #10b981;
}
.loc-info {
  flex: 1;
}
.loc-name {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.loc-meta {
  display: flex;
  gap: 6px;
  align-items: center;
  margin-top: 3px;
}
.loc-code {
  font-family: monospace;
  font-size: 11px;
  background: #f3f4f6;
  padding: 1px 5px;
  border-radius: 4px;
  color: #6b7280;
}
.loc-zone {
  font-size: 11.5px;
  color: #9ca3af;
}
.loc-badges {
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: flex-end;
}
.badge {
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 10.5px;
  font-weight: 600;
}
.badge.cold {
  background: #dbeafe;
  color: #1d4ed8;
}
.badge.active-badge {
  background: #dcfce7;
  color: #16a34a;
}
.badge.inactive-badge {
  background: #f3f4f6;
  color: #6b7280;
}

.capacity-section {
}
.cap-label {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  margin-bottom: 5px;
}
.cap-label span:first-child {
  color: #6b7280;
}
.text-ok {
  color: #16a34a;
  font-weight: 600;
}
.text-danger {
  color: #ef4444;
  font-weight: 600;
}
.cap-bar {
  height: 5px;
  background: #f3f4f6;
  border-radius: 4px;
  overflow: hidden;
}
.cap-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.4s;
}

.batch-summary {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}
.bs-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 6px;
  border-radius: 8px;
}
.bs-item.fresh {
  background: #f0fdf4;
}
.bs-item.aging {
  background: #fefce8;
}
.bs-item.wilting {
  background: #fff7ed;
}
.bs-item.spoiled {
  background: #fef2f2;
}
.bs-num {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
}
.bs-lbl {
  font-size: 10px;
  color: #9ca3af;
}

.loc-actions {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
}
.la-btn {
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
}
.la-btn:hover {
  background: #f9fafb;
  color: #374151;
}
.la-btn.danger:hover {
  background: #fee2e2;
  color: #dc2626;
  border-color: #fca5a5;
}

.empty-card {
  grid-column: 1/-1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  padding: 60px 20px;
  background: #fff;
  border-radius: 14px;
  border: 2px dashed #e5e7eb;
}
.empty-card p {
  font-size: 15px;
  color: #9ca3af;
  margin: 0;
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal {
  background: #fff;
  border-radius: 14px;
  width: 480px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}
.confirm-modal {
  width: 400px;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
  border-bottom: 1px solid #f0f2f5;
}
.modal-header h3 {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.modal-close {
  border: none;
  background: none;
  font-size: 22px;
  color: #9ca3af;
  cursor: pointer;
}
.modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid #f0f2f5;
}
.confirm-text {
  font-size: 13.5px;
  color: #374151;
  line-height: 1.6;
  margin: 0;
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
.field select {
  padding: 9px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  font-size: 13.5px;
  color: #111827;
  outline: none;
  font-family: inherit;
}
.field input:focus {
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

.checkbox-row {
  display: flex;
  gap: 20px;
}
.checkbox-label {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13.5px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
}
.checkbox-label input {
  margin-right: 6px;
}
.check-hint {
  font-size: 11.5px;
  color: #9ca3af;
  font-weight: 400;
}

.btn-cancel {
  padding: 9px 18px;
  border-radius: 8px;
  border: 1.5px solid #e5e7eb;
  background: #fff;
  color: #374151;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
}
.btn-danger {
  padding: 9px 18px;
  border-radius: 8px;
  border: none;
  background: #ef4444;
  color: #fff;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
}
.btn-danger:hover:not(:disabled) {
  background: #dc2626;
}
.btn-danger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
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
