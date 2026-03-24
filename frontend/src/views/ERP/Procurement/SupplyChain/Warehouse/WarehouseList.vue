<template>
  <div class="wh-page">
    

    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Warehouses</h1>
        <p class="page-sub">Manage storage facilities</p>
      </div>
      <div class="header-actions">
        <router-link
          to="/erp/procurement/supply-chain/warehouse/inventory"
          class="btn-secondary"
        >
          <svg
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            width="15"
          >
            <rect x="3" y="3" width="14" height="14" rx="2" />
            <path d="M7 7h6M7 10h6M7 13h4" />
          </svg>
          View Inventory
        </router-link>
        <button class="btn-primary" @click="openCreate">
          <svg viewBox="0 0 20 20" fill="currentColor" width="15">
            <path
              d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
            />
          </svg>
          New Warehouse
        </button>
      </div>
    </div>

    <!-- Warehouse grid -->
    <div v-if="loading" class="loading-state"><div class="spinner"></div></div>
    <div v-else class="wh-grid">
      <div v-for="wh in warehouses" :key="wh.id" class="wh-card">
        <div class="wh-card-header">
          <div class="wh-icon">
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="22"
            >
              <path d="M3 12l9-7 9 7v9H3z" />
              <rect x="9" y="15" width="6" height="6" rx="1" />
            </svg>
          </div>
          <div class="wh-meta">
            <h3 class="wh-name">{{ wh.name }}</h3>
            <p class="wh-loc">
              <svg viewBox="0 0 12 16" fill="currentColor" width="9">
                <path
                  d="M6 0C3.24 0 1 2.24 1 5c0 3.75 5 11 5 11s5-7.25 5-11c0-2.76-2.24-5-5-5zm0 7.5A2.5 2.5 0 113.5 5 2.5 2.5 0 016 7.5z"
                />
              </svg>
              {{ wh.location }}
            </p>
          </div>
          <button class="wh-menu-btn">&#x22EF;</button>
        </div>

        <div class="wh-stats">
          <div class="wh-stat">
            <span class="wh-stat-num">{{ wh.items_count ?? 0 }}</span>
            <span class="wh-stat-lbl">SKUs</span>
          </div>
          <div class="wh-stat-div"></div>
          <div class="wh-stat">
            <span class="wh-stat-num">{{ wh.total_units ?? 0 }}</span>
            <span class="wh-stat-lbl">Units</span>
          </div>
          <div class="wh-stat-div"></div>
          <div class="wh-stat">
            <span
              class="wh-stat-num"
              :class="{ warn: (wh.low_stock ?? 0) > 0 }"
              >{{ wh.low_stock ?? 0 }}</span
            >
            <span class="wh-stat-lbl">Low Stock</span>
          </div>
        </div>

        <div class="wh-manager">
          <div class="manager-avatar">{{ wh.manager?.[0]?.toUpperCase() }}</div>
          <div>
            <span class="manager-label">Manager</span>
            <span class="manager-name">{{ wh.manager }}</span>
          </div>
        </div>

        <div class="wh-actions">
          <button class="wh-btn" @click="goViewStorage(wh)">
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="13"
            >
              <rect x="3" y="3" width="14" height="14" rx="2" />
              <path d="M7 7h6M7 10h6M7 13h4" />
            </svg>
            View Storage
          </button>
          <button class="wh-btn green" @click="openLinkLocation(wh)">
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="13"
            >
              <path
                d="M8 4H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-3M12 2h6m0 0v6m0-6L10 10"
              />
            </svg>
            Add Location
          </button>
        </div>
      </div>

      <div v-if="!warehouses.length" class="empty-card">
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
        <p>No warehouses yet</p>
        <button class="btn-primary" @click="openCreate">
          Create your first warehouse
        </button>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         CREATE WAREHOUSE MODAL  (2 steps)
         Step 1 — warehouse details
         Step 2 — pick & link storage locations
    ════════════════════════════════════════════════════════════════ -->
    <transition name="modal-fade">
      <div v-if="showCreate" class="modal-overlay" @click.self="closeCreate">
        <div class="modal modal-wide">
          <!-- Header with step indicator -->
          <div class="modal-header">
            <div>
              <h3>
                {{
                  createStep === 1 ? "New Warehouse" : "Link Storage Locations"
                }}
              </h3>
              <p class="modal-sub">
                <span
                  class="step-pip"
                  :class="{ active: createStep === 1 }"
                ></span>
                <span
                  class="step-pip"
                  :class="{ active: createStep === 2 }"
                ></span>
                Step {{ createStep }} of 2
              </p>
            </div>
            <button class="modal-close" @click="closeCreate">&times;</button>
          </div>

          <!-- ── STEP 1: Warehouse details ─────────────────────────── -->
          <div v-if="createStep === 1" class="modal-body">
            <div class="field">
              <label>Warehouse Name <span class="req">*</span></label>
              <input
                v-model="newWh.name"
                placeholder="e.g. North Distribution Center"
                :class="{ error: createErrors.name }"
                @keydown.enter="goToStep2"
              />
              <span v-if="createErrors.name" class="err-msg">{{
                createErrors.name
              }}</span>
            </div>
            <div class="field">
              <label>Location / Address <span class="req">*</span></label>
              <input
                v-model="newWh.location"
                placeholder="City, State, Country"
                :class="{ error: createErrors.location }"
              />
              <span v-if="createErrors.location" class="err-msg">{{
                createErrors.location
              }}</span>
            </div>
            <div class="field">
              <label>Manager</label>
              <input v-model="newWh.manager" placeholder="Manager full name" />
            </div>
          </div>

          <!-- ── STEP 2: Pick storage locations ───────────────────── -->
          <div v-else class="modal-body step2-body">
            <p class="step2-hint">
              Select existing storage locations to link to
              <strong>{{ newWh.name }}</strong
              >. You can link more later from the warehouse card.
            </p>

            <!-- Search -->
            <div class="loc-search">
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
                v-model="locSearch"
                placeholder="Search by name, code, zone…"
              />
            </div>

            <!-- Loading -->
            <div v-if="locLoading" class="loc-loading">
              <div class="spinner-sm"></div>
              Loading locations…
            </div>

            <!-- No locations exist at all -->
            <div v-else-if="!allLocations.length" class="loc-empty">
              No storage locations found.
              <router-link
                to="/erp/procurement/supply-chain/warehouse/locations"
                class="inline-link"
                @click="closeCreate"
              >
                Create one first →
              </router-link>
            </div>

            <!-- Location picker -->
            <div v-else class="loc-list">
              <button
                v-for="loc in filteredAllLocations"
                :key="loc.id"
                type="button"
                class="loc-row"
                :class="{
                  selected: selectedLocIds.includes(loc.id),
                  taken: loc.warehouse_id && !selectedLocIds.includes(loc.id),
                }"
                @click="toggleLoc(loc)"
              >
                <div
                  class="loc-row-icon"
                  :class="loc.is_refrigerated ? 'cold' : 'warm'"
                >
                  <svg
                    v-if="loc.is_refrigerated"
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="13"
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
                    width="13"
                  >
                    <rect x="2" y="4" width="16" height="13" rx="2" />
                    <path d="M2 9h16" />
                  </svg>
                </div>

                <div class="loc-row-info">
                  <span class="loc-row-name">{{ loc.name }}</span>
                  <code class="loc-row-code">{{ loc.code }}</code>
                </div>

                <div class="loc-row-badges">
                  <span v-if="loc.is_refrigerated" class="badge cold"
                    >❄ Cold</span
                  >
                  <span v-if="loc.zone" class="badge">{{ loc.zone }}</span>
                  <span v-if="loc.capacity_units" class="badge"
                    >Cap: {{ loc.capacity_units }}</span
                  >
                  <span v-if="loc.warehouse_id" class="badge warn">In use</span>
                </div>

                <!-- Checkmark when selected -->
                <div class="loc-check" v-if="selectedLocIds.includes(loc.id)">
                  <svg viewBox="0 0 16 16" fill="currentColor" width="10">
                    <path
                      fill-rule="evenodd"
                      d="M13.707 4.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-3-3a1 1 0 011.414-1.414L6 10.586l6.293-6.293a1 1 0 011.414 0z"
                    />
                  </svg>
                </div>
              </button>

              <div v-if="!filteredAllLocations.length" class="loc-empty">
                No locations match your search.
              </div>
            </div>

            <!-- Selected count -->
            <div v-if="selectedLocIds.length" class="selected-hint">
              {{ selectedLocIds.length }} location{{
                selectedLocIds.length > 1 ? "s" : ""
              }}
              selected
            </div>
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <button
              class="btn-cancel"
              @click="createStep === 1 ? closeCreate() : createStep--"
            >
              {{ createStep === 1 ? "Cancel" : "← Back" }}
            </button>

            <!-- Step 1 → Next -->
            <button
              v-if="createStep === 1"
              class="btn-primary"
              @click="goToStep2"
            >
              Next: Link Storage →
            </button>

            <!-- Step 2 → Save -->
            <button
              v-else
              class="btn-primary"
              :disabled="creating"
              @click="saveWarehouse"
            >
              <div v-if="creating" class="spinner-xs"></div>
              {{ creating ? "Creating…" : "Create Warehouse" }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ═══════════════════════════════════════════════════════════════
         LINK LOCATION MODAL  (for existing warehouses)
    ════════════════════════════════════════════════════════════════ -->
    <transition name="modal-fade">
      <div v-if="showLink" class="modal-overlay" @click.self="closeLink">
        <div class="modal modal-wide">
          <div class="modal-header">
            <div>
              <h3>Add Storage Location</h3>
              <p class="modal-sub" v-if="targetWh">
                Linking to <strong>{{ targetWh.name }}</strong>
              </p>
            </div>
            <button class="modal-close" @click="closeLink">&times;</button>
          </div>

          <div class="modal-body">
            <div class="loc-search">
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
                v-model="linkSearch"
                placeholder="Search by name, code, zone…"
              />
            </div>

            <div v-if="locLoading" class="loc-loading">
              <div class="spinner-sm"></div>
              Loading locations…
            </div>

            <div v-else-if="!allLocations.length" class="loc-empty">
              No storage locations found.
              <router-link
                to="/erp/procurement/supply-chain/warehouse/locations"
                class="inline-link"
                @click="closeLink"
              >
                Create one first →
              </router-link>
            </div>

            <div v-else class="loc-list">
              <!-- Already linked -->
              <div v-if="linkedToTarget.length">
                <div class="loc-group-lbl">
                  Already linked to {{ targetWh?.name }}
                </div>
                <button
                  v-for="loc in linkedToTarget"
                  :key="loc.id"
                  type="button"
                  class="loc-row linked"
                  disabled
                >
                  <div class="loc-row-icon warm">
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                      width="13"
                    >
                      <path d="M7 10l2 2 4-4" />
                      <circle cx="10" cy="10" r="7" />
                    </svg>
                  </div>
                  <div class="loc-row-info">
                    <span class="loc-row-name">{{ loc.name }}</span>
                    <code class="loc-row-code">{{ loc.code }}</code>
                  </div>
                  <div class="loc-row-badges">
                    <span v-if="loc.is_refrigerated" class="badge cold"
                      >❄ Cold</span
                    >
                    <span v-if="loc.zone" class="badge">{{ loc.zone }}</span>
                  </div>
                  <span class="already-tag">Linked</span>
                </button>
              </div>

              <!-- Available to link -->
              <div v-if="availableForLink.length">
                <div class="loc-group-lbl" style="margin-top: 10px">
                  Available locations
                </div>
                <button
                  v-for="loc in availableForLink"
                  :key="loc.id"
                  type="button"
                  class="loc-row"
                  :class="{ selected: linkSelectedId === loc.id }"
                  @click="linkSelectedId = loc.id"
                >
                  <div
                    class="loc-row-icon"
                    :class="loc.is_refrigerated ? 'cold' : 'warm'"
                  >
                    <svg
                      v-if="loc.is_refrigerated"
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                      width="13"
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
                      width="13"
                    >
                      <rect x="2" y="4" width="16" height="13" rx="2" />
                      <path d="M2 9h16" />
                    </svg>
                  </div>
                  <div class="loc-row-info">
                    <span class="loc-row-name">{{ loc.name }}</span>
                    <code class="loc-row-code">{{ loc.code }}</code>
                  </div>
                  <div class="loc-row-badges">
                    <span v-if="loc.is_refrigerated" class="badge cold"
                      >❄ Cold</span
                    >
                    <span v-if="loc.zone" class="badge">{{ loc.zone }}</span>
                    <span v-if="loc.capacity_units" class="badge"
                      >Cap: {{ loc.capacity_units }}</span
                    >
                    <span v-if="loc.warehouse_id" class="badge warn"
                      >In use</span
                    >
                  </div>
                  <div class="loc-check" v-if="linkSelectedId === loc.id">
                    <svg viewBox="0 0 16 16" fill="currentColor" width="10">
                      <path
                        fill-rule="evenodd"
                        d="M13.707 4.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-3-3a1 1 0 011.414-1.414L6 10.586l6.293-6.293a1 1 0 011.414 0z"
                      />
                    </svg>
                  </div>
                </button>
              </div>

              <div
                v-if="!linkedToTarget.length && !availableForLink.length"
                class="loc-empty"
              >
                No locations match your search.
              </div>
            </div>

            <p v-if="linkError" class="err-msg">{{ linkError }}</p>
          </div>

          <div class="modal-footer">
            <button class="btn-cancel" @click="closeLink">Cancel</button>
            <button
              class="btn-primary"
              :disabled="!linkSelectedId || linking"
              @click="confirmLink"
            >
              <div v-if="linking" class="spinner-xs"></div>
              {{ linking ? "Linking…" : "Link Location" }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Toast -->
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
import { warehouseService } from "../../../../../services/warehouseService";
import { warehouseLocationService } from "../../../../../services/warehouseBatchService";


const router = useRouter();

// ── State ─────────────────────────────────────────────────────────────────────

const warehouses = ref([]);
const loading = ref(false);
const toast = ref({ show: false, type: "success", message: "" });

// Create modal (2-step)
const showCreate = ref(false);
const createStep = ref(1);
const creating = ref(false);
const newWh = ref({ name: "", location: "", manager: "" });
const createErrors = reactive({});
const allLocations = ref([]); // loaded on step 2
const locLoading = ref(false);
const locSearch = ref("");
const selectedLocIds = ref([]); // multi-select for new warehouse

// Link modal (existing warehouse)
const showLink = ref(false);
const targetWh = ref(null);
const linkSearch = ref("");
const linkSelectedId = ref(null);
const linking = ref(false);
const linkError = ref("");

// ── Computed ──────────────────────────────────────────────────────────────────

// Create modal step 2 — filter all locations by search
const filteredAllLocations = computed(() => {
  const q = locSearch.value.trim().toLowerCase();
  if (!q) return allLocations.value;
  return allLocations.value.filter(
    (l) =>
      l.name?.toLowerCase().includes(q) ||
      l.code?.toLowerCase().includes(q) ||
      l.zone?.toLowerCase().includes(q),
  );
});

// Link modal — already linked to target warehouse
const linkedToTarget = computed(() => {
  const q = linkSearch.value.trim().toLowerCase();
  return allLocations.value
    .filter((l) => l.warehouse_id == targetWh.value?.id)
    .filter(
      (l) =>
        !q ||
        l.name?.toLowerCase().includes(q) ||
        l.code?.toLowerCase().includes(q),
    );
});

// Link modal — not yet linked to target warehouse
const availableForLink = computed(() => {
  const q = linkSearch.value.trim().toLowerCase();
  return allLocations.value
    .filter((l) => l.warehouse_id != targetWh.value?.id)
    .filter(
      (l) =>
        !q ||
        l.name?.toLowerCase().includes(q) ||
        l.code?.toLowerCase().includes(q),
    );
});

// ── Warehouses ────────────────────────────────────────────────────────────────

async function fetchWarehouses() {
  loading.value = true;
  try {
    const res = await warehouseService.list();
    warehouses.value = res.data?.data ?? res.data ?? [];
  } finally {
    loading.value = false;
  }
}

// ── Create modal ──────────────────────────────────────────────────────────────

function openCreate() {
  createStep.value = 1;
  newWh.value = { name: "", location: "", manager: "" };
  selectedLocIds.value = [];
  locSearch.value = "";
  allLocations.value = [];
  Object.keys(createErrors).forEach((k) => delete createErrors[k]);
  showCreate.value = true;
}

function closeCreate() {
  showCreate.value = false;
}

function validateStep1() {
  Object.keys(createErrors).forEach((k) => delete createErrors[k]);
  if (!newWh.value.name.trim()) createErrors.name = "Name is required";
  if (!newWh.value.location.trim())
    createErrors.location = "Location is required";
  return !Object.keys(createErrors).length;
}

async function goToStep2() {
  if (!validateStep1()) return;
  createStep.value = 2;
  await loadAllLocations();
}

async function loadAllLocations() {
  locLoading.value = true;
  try {
    const res = await warehouseLocationService.list();
    allLocations.value = res.data?.data ?? res.data ?? [];
  } catch (e) {
    allLocations.value = [];
  } finally {
    locLoading.value = false;
  }
}

function toggleLoc(loc) {
  const idx = selectedLocIds.value.indexOf(loc.id);
  if (idx === -1) selectedLocIds.value.push(loc.id);
  else selectedLocIds.value.splice(idx, 1);
}

async function saveWarehouse() {
  creating.value = true;
  try {
    // 1. Create the warehouse
    const res = await warehouseService.create({
      name: newWh.value.name.trim(),
      location: newWh.value.location.trim(),
      manager: newWh.value.manager.trim() || undefined,
    });
    const newId = res.data?.data?.id ?? res.data?.id ?? res.data?.id;

    // 2. Link each selected location → PATCH warehouse_id onto location
    if (newId && selectedLocIds.value.length) {
      await Promise.all(
        selectedLocIds.value.map((locId) =>
          warehouseLocationService.update(locId, { warehouse_id: newId }),
        ),
      );
    }

    showToast(
      selectedLocIds.value.length
        ? `Warehouse created with ${selectedLocIds.value.length} location(s) linked!`
        : "Warehouse created!",
    );
    closeCreate();
    fetchWarehouses();
  } catch (e) {
    showToast(
      e?.response?.data?.message ?? "Failed to create warehouse",
      "error",
    );
  } finally {
    creating.value = false;
  }
}

// ── Link modal (existing warehouse) ──────────────────────────────────────────

async function openLinkLocation(wh) {
  targetWh.value = wh;
  linkSelectedId.value = null;
  linkSearch.value = "";
  linkError.value = "";
  showLink.value = true;
  await loadAllLocations();
}

function closeLink() {
  showLink.value = false;
  targetWh.value = null;
  linkSelectedId.value = null;
  linkSearch.value = "";
  linkError.value = "";
  allLocations.value = [];
}

async function confirmLink() {
  if (!linkSelectedId.value) return;
  linking.value = true;
  linkError.value = "";
  try {
    await warehouseLocationService.update(linkSelectedId.value, {
      warehouse_id: targetWh.value.id,
    });
    const loc = allLocations.value.find((l) => l.id === linkSelectedId.value);
    showToast(`"${loc?.name}" linked to ${targetWh.value.name}!`);
    closeLink();
    fetchWarehouses();
  } catch (e) {
    linkError.value = e?.response?.data?.message ?? "Failed to link location";
  } finally {
    linking.value = false;
  }
}

// ── Navigation ────────────────────────────────────────────────────────────────

function goViewStorage(wh) {
  router.push({ name: "WarehouseInventory", query: { warehouse: wh.id } });
}

// ── Toast ─────────────────────────────────────────────────────────────────────

function showToast(msg, type = "success") {
  toast.value = { show: true, type, message: msg };
  setTimeout(() => (toast.value.show = false), 3500);
}

onMounted(fetchWarehouses);
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.wh-page {
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
  font-family: inherit;
}
.btn-primary:hover {
  background: #059669;
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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

.loading-state {
  display: flex;
  justify-content: center;
  padding: 80px;
}
.spinner {
  width: 32px;
  height: 32px;
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

/* Warehouse grid */
.wh-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
}
.wh-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e8ecf0;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  transition:
    box-shadow 0.2s,
    transform 0.2s;
}
.wh-card:hover {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}
.wh-card-header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.wh-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: #ecfdf5;
  color: #10b981;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.wh-meta {
  flex: 1;
}
.wh-name {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.wh-loc {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #9ca3af;
  margin-top: 3px;
}
.wh-menu-btn {
  border: none;
  background: none;
  cursor: pointer;
  font-size: 18px;
  color: #9ca3af;
  padding: 4px;
}
.wh-stats {
  display: flex;
  align-items: center;
  justify-content: space-around;
  background: #f9fafb;
  border-radius: 10px;
  padding: 12px;
}
.wh-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}
.wh-stat-num {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
}
.wh-stat-num.warn {
  color: #f59e0b;
}
.wh-stat-lbl {
  font-size: 11px;
  color: #9ca3af;
}
.wh-stat-div {
  width: 1px;
  height: 28px;
  background: #e5e7eb;
}
.wh-manager {
  display: flex;
  align-items: center;
  gap: 10px;
}
.manager-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #dbeafe;
  color: #1d4ed8;
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.wh-manager > div {
  display: flex;
  flex-direction: column;
}
.manager-label {
  font-size: 11px;
  color: #9ca3af;
}
.manager-name {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}
.wh-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.wh-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1.5px solid #e5e7eb;
  background: #fff;
  color: #374151;
  font-size: 13px;
  font-weight: 500;
  font-family: "Poppins", sans-serif;
  cursor: pointer;
  transition: background 0.15s;
}
.wh-btn:hover {
  background: #f9fafb;
}
.wh-btn.green {
  background: #ecfdf5;
  border-color: #a7f3d0;
  color: #059669;
}
.wh-btn.green:hover {
  background: #d1fae5;
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
  background: rgba(0, 0, 0, 0.45);
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal {
  background: #fff;
  border-radius: 16px;
  width: 480px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.18);
}
.modal-wide {
  width: 560px;
}
.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 20px 22px 16px;
  border-bottom: 1px solid #f0f2f5;
  flex-shrink: 0;
}
.modal-header h3 {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.modal-sub {
  font-size: 12px;
  color: #9ca3af;
  margin: 5px 0 0;
  display: flex;
  align-items: center;
  gap: 5px;
}
.step-pip {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #e5e7eb;
  display: inline-block;
}
.step-pip.active {
  background: #10b981;
}
.modal-close {
  border: none;
  background: none;
  font-size: 22px;
  color: #9ca3af;
  cursor: pointer;
  flex-shrink: 0;
}
.modal-body {
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  overflow-y: auto;
  flex: 1;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 22px;
  border-top: 1px solid #f0f2f5;
  flex-shrink: 0;
}

/* Step 2 */
.step2-body {
  gap: 12px;
}
.step2-hint {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
  line-height: 1.5;
}

/* Location search */
.loc-search {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  background: #f9fafb;
  flex-shrink: 0;
}
.loc-search input {
  border: none;
  background: none;
  outline: none;
  font-size: 13.5px;
  color: #111827;
  flex: 1;
  font-family: inherit;
}
.loc-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #9ca3af;
  font-size: 13px;
}
.spinner-sm {
  width: 18px;
  height: 18px;
  border: 2px solid #e5e7eb;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
.loc-empty {
  font-size: 13px;
  color: #9ca3af;
  padding: 6px 0;
}
.inline-link {
  color: #10b981;
  font-weight: 600;
  text-decoration: none;
  margin-left: 4px;
}

.loc-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.loc-group-lbl {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
  padding: 4px 0 6px;
}

.loc-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
  text-align: left;
  font-family: inherit;
  transition: all 0.12s;
  width: 100%;
}
.loc-row:hover:not(:disabled):not(.selected):not(.linked) {
  border-color: #10b981;
  background: #f9fafb;
}
.loc-row.selected {
  border-color: #10b981;
  background: #ecfdf5;
}
.loc-row.linked {
  opacity: 0.55;
  cursor: default;
  background: #f9fafb;
}
.loc-row.taken {
  opacity: 0.75;
}

.loc-row-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.loc-row-icon.cold {
  background: #dbeafe;
  color: #1d4ed8;
}
.loc-row-icon.warm {
  background: #d1fae5;
  color: #059669;
}
.loc-row-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.loc-row-name {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.loc-row-code {
  font-family: monospace;
  font-size: 11px;
  color: #9ca3af;
}
.loc-row-badges {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  flex-shrink: 0;
}
.badge {
  font-size: 10.5px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 5px;
  background: #f3f4f6;
  color: #6b7280;
}
.badge.cold {
  background: #dbeafe;
  color: #1d4ed8;
}
.badge.warn {
  background: #fef3c7;
  color: #92400e;
}
.loc-check {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #10b981;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.already-tag {
  font-size: 11px;
  font-weight: 600;
  color: #059669;
  background: #dcfce7;
  padding: 2px 8px;
  border-radius: 10px;
  white-space: nowrap;
  flex-shrink: 0;
}

.selected-hint {
  font-size: 12.5px;
  font-weight: 600;
  color: #059669;
  padding: 2px 0;
}

/* Fields */
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
.field input {
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

.btn-cancel {
  padding: 9px 18px;
  border-radius: 8px;
  border: 1.5px solid #e5e7eb;
  background: #fff;
  color: #374151;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  font-family: inherit;
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

/* Toast */
.toast {
  position: fixed;
  bottom: 28px;
  left: 50%;
  transform: translateX(-50%);
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 500;
  z-index: 999;
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
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
