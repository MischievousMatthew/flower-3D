<template>
  <div class="scanner-page">
    
    <!-- Header -->
    <div class="page-header">
      <router-link to="/erp/warehouse/floor" class="back-link">
        <svg
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          width="16"
        >
          <path d="M13 16l-6-6 6-6" />
        </svg>
        Back to Floor
      </router-link>
      <h1 class="page-title">Batch Scanner</h1>
      <p class="page-sub">Scan a batch barcode to view its current status</p>
    </div>

    <div class="scanner-layout">
      <!-- Left: Scanner -->
      <div class="scanner-col">
        <div class="scanner-card">
          <div v-if="!scanning" class="scan-idle">
            <div class="scan-icon">
              <svg
                viewBox="0 0 48 48"
                fill="none"
                stroke="#10b981"
                stroke-width="1.5"
                width="52"
              >
                <rect x="4" y="4" width="12" height="12" rx="2" />
                <rect x="32" y="4" width="12" height="12" rx="2" />
                <rect x="4" y="32" width="12" height="12" rx="2" />
                <path
                  d="M32 32h4m8 0h-4m-4 4v4m0-8v-4M20 4v4M4 20h4M20 20H4M24 8v12H12"
                />
              </svg>
            </div>
            <p class="idle-text">Ready to scan</p>
            <button class="btn-scan" @click="startScan">
              <svg
                viewBox="0 0 20 20"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                width="16"
              >
                <rect x="3" y="3" width="5" height="5" rx="1" />
                <rect x="12" y="3" width="5" height="5" rx="1" />
                <rect x="3" y="12" width="5" height="5" rx="1" />
                <path d="M12 12h2m3 0h-1m-2 3h5m-5-5v5" />
              </svg>
              Open Scanner
            </button>
            <div class="or-divider"><span>or enter manually</span></div>
            <div class="manual-input">
              <input
                v-model="manualBarcode"
                placeholder="Paste / type barcode…"
                @keydown.enter="lookupManual"
              />
              <button
                @click="lookupManual"
                :disabled="!manualBarcode || looking"
              >
                {{ looking ? "…" : "Go" }}
              </button>
            </div>
          </div>

          <!-- Active QR Scanner -->
          <div v-if="scanning">
            <QRScanner
              @scan-success="onScanSuccess"
              @scan-error="onScanError"
              @close="scanning = false"
            />
          </div>
        </div>

        <!-- Scan history -->
        <div v-if="history.length" class="history-card">
          <div class="history-title">Recent Scans</div>
          <div class="history-list">
            <div
              v-for="(h, i) in history"
              :key="i"
              class="history-item"
              @click="activeBatch = h.batch"
            >
              <div
                class="hi-dot"
                :style="{
                  background: conditionMeta(h.batch?.computed_condition).dot,
                }"
              ></div>
              <div class="hi-info">
                <div class="hi-product">
                  {{ h.batch?.product?.product_name ?? "Unknown" }}
                </div>
                <code class="hi-batch">{{ h.barcode }}</code>
              </div>
              <div class="hi-time">{{ fmtTime(h.scannedAt) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Result -->
      <div class="result-col">
        <!-- Loading -->
        <div v-if="looking" class="result-card loading-result">
          <div class="spinner"></div>
          <span>Looking up batch…</span>
        </div>

        <!-- Not found -->
        <div v-else-if="notFound" class="result-card not-found">
          <svg
            viewBox="0 0 48 48"
            fill="none"
            stroke="#ef4444"
            stroke-width="1.5"
            width="48"
          >
            <circle cx="24" cy="24" r="20" />
            <path d="M16 16l16 16M32 16L16 32" />
          </svg>
          <p class="nf-title">Batch Not Found</p>
          <p class="nf-sub">
            Barcode: <code>{{ lastBarcode }}</code>
          </p>
          <button class="btn-scan small" @click="resetResult">
            Scan Again
          </button>
        </div>

        <!-- Found batch -->
        <div v-else-if="activeBatch" class="result-card found-result">
          <!-- Condition banner -->
          <div
            class="result-banner"
            :style="{
              background: conditionMeta(activeBatch.computed_condition).bg,
            }"
          >
            <div class="rb-left">
              <span
                class="rb-dot"
                :style="{
                  background: conditionMeta(activeBatch.computed_condition).dot,
                }"
              ></span>
              <span
                class="rb-label"
                :style="{
                  color: conditionMeta(activeBatch.computed_condition).text,
                }"
              >
                {{ conditionMeta(activeBatch.computed_condition).label }}
              </span>
            </div>
            <div
              class="rb-expiry"
              :class="daysClass(activeBatch.days_remaining)"
            >
              {{ daysLabel(activeBatch.days_remaining) }}
            </div>
          </div>

          <!-- Product -->
          <div class="r-section r-product">
            <div class="rp-thumb">
              <img
                v-if="activeBatch.product?.image_url"
                :src="activeBatch.product.image_url"
                alt=""
              />
              <span v-else>{{
                activeBatch.product?.product_name?.[0] ?? "?"
              }}</span>
            </div>
            <div>
              <div class="rp-name">{{ activeBatch.product?.product_name }}</div>
              <div class="rp-sku">
                {{ activeBatch.product?.sku }} ·
                {{ activeBatch.product?.flower_type }}
              </div>
              <div class="product-tags" style="margin-top: 4px">
                <span
                  v-if="activeBatch.product?.requires_refrigeration"
                  class="tag cold"
                  >❄ Refrigerated</span
                >
                <span v-if="activeBatch.product?.is_fragile" class="tag fragile"
                  >⚠ Fragile</span
                >
              </div>
            </div>
          </div>

          <!-- Batch info -->
          <div class="r-section r-info">
            <div class="ri-row">
              <span>Batch</span><code>{{ activeBatch.batch_number }}</code>
            </div>
            <div class="ri-row">
              <span>Barcode</span><code>{{ activeBatch.barcode }}</code>
            </div>
            <div class="ri-row">
              <span>Lot</span><span>{{ activeBatch.lot_number ?? "—" }}</span>
            </div>
            <div class="ri-row">
              <span>Qty Remaining</span>
              <span class="bold-val"
                >{{ activeBatch.qty_remaining }} /
                {{ activeBatch.qty_received }}</span
              >
            </div>
            <div class="ri-row">
              <span>Location</span>
              <span>{{
                activeBatch.location?.name ??
                activeBatch.storage_location ??
                "—"
              }}</span>
            </div>
            <div class="ri-row">
              <span>Received</span
              ><span>{{ fmtDate(activeBatch.received_date) }}</span>
            </div>
            <div class="ri-row">
              <span>Harvest</span
              ><span>{{ fmtDate(activeBatch.harvest_date) }}</span>
            </div>
            <div class="ri-row">
              <span>Expiration</span>
              <span :class="daysClass(activeBatch.days_remaining)">{{
                fmtDate(activeBatch.expiration_date)
              }}</span>
            </div>
          </div>

          <!-- Freshness bar -->
          <div
            v-if="
              activeBatch.freshness_days && activeBatch.days_remaining !== null
            "
            class="r-section"
          >
            <div class="fresh-label-row">
              <span>Freshness</span>
              <span>{{ freshnessPct(activeBatch) }}%</span>
            </div>
            <div class="fresh-bar">
              <div
                class="fresh-fill"
                :style="{
                  width: freshnessPct(activeBatch) + '%',
                  background: conditionMeta(activeBatch.computed_condition).dot,
                }"
              ></div>
            </div>
          </div>

          <!-- Quick actions -->
          <div class="r-section r-actions">
            <div class="ra-title">Quick Actions</div>
            <div class="ra-grid">
              <button class="ra-btn green" @click="quickAction('condition')">
                Update Condition
              </button>
              <button class="ra-btn red" @click="quickAction('cull')">
                Cull Units
              </button>
              <button class="ra-btn blue" @click="quickAction('transfer')">
                Transfer
              </button>
              <button class="ra-btn gray" @click="startScan">Scan Next</button>
            </div>
          </div>
        </div>

        <!-- Default: no scan yet -->
        <div v-else class="result-card empty-result">
          <svg
            viewBox="0 0 48 48"
            fill="none"
            stroke="#e5e7eb"
            stroke-width="1.5"
            width="56"
          >
            <rect x="4" y="4" width="12" height="12" rx="2" />
            <rect x="32" y="4" width="12" height="12" rx="2" />
            <rect x="4" y="32" width="12" height="12" rx="2" />
            <path d="M32 32h16M32 40h16M32 48h8" />
          </svg>
          <p>Scan or enter a barcode to look up a batch</p>
        </div>
      </div>
    </div>

    <!-- Quick Action Modal -->
    <transition name="modal-fade">
      <div
        v-if="actionType && activeBatch"
        class="modal-overlay"
        @click.self="actionType = null"
      >
        <div class="modal">
          <div class="modal-header">
            <h3>{{ actionTitle }}</h3>
            <button class="modal-close" @click="actionType = null">×</button>
          </div>
          <div class="modal-body">
            <!-- Condition -->
            <template v-if="actionType === 'condition'">
              <div class="condition-options">
                <button
                  v-for="c in conditions"
                  :key="c"
                  class="cond-btn"
                  :class="{ selected: actionForm.condition === c }"
                  :style="
                    actionForm.condition === c
                      ? {
                          background: conditionMeta(c).bg,
                          borderColor: conditionMeta(c).dot,
                          color: conditionMeta(c).text,
                        }
                      : {}
                  "
                  @click="actionForm.condition = c"
                >
                  <span
                    class="cdot"
                    :style="{ background: conditionMeta(c).dot }"
                  ></span>
                  {{ conditionMeta(c).label }}
                </button>
              </div>
            </template>
            <!-- Cull -->
            <template v-else-if="actionType === 'cull'">
              <p class="action-note">
                Available: <strong>{{ activeBatch.qty_remaining }}</strong>
              </p>
              <div class="field">
                <label>Quantity <span class="req">*</span></label>
                <input
                  v-model.number="actionForm.qty"
                  type="number"
                  min="1"
                  :max="activeBatch.qty_remaining"
                />
              </div>
            </template>
            <!-- Transfer -->
            <template v-else-if="actionType === 'transfer'">
              <p class="action-note">
                Current:
                <strong>{{ activeBatch.storage_location ?? "None" }}</strong>
              </p>
              <div class="field">
                <label>New Location <span class="req">*</span></label>
                <select v-model="actionForm.location_id">
                  <option value="" disabled>Select…</option>
                  <option v-for="l in locations" :key="l.id" :value="l.id">
                    {{ l.name }}
                  </option>
                </select>
              </div>
            </template>
            <div class="field" style="margin-top: 12px">
              <label>Notes</label>
              <textarea
                v-model="actionForm.notes"
                rows="2"
                placeholder="Optional reason…"
              ></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-cancel" @click="actionType = null">
              Cancel
            </button>
            <button
              class="btn-primary"
              :disabled="saving"
              @click="submitAction"
            >
              {{ saving ? "Saving…" : "Confirm" }}
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
import QRScanner from "../../../../../layouts/components/QRScanner.vue";
import {
  warehouseBatchService,
  warehouseLocationService,
  conditionMeta,
  daysRemainingLabel,
  daysRemainingClass,
  CONDITIONS,
} from "../../../../../services/warehouseBatchService";


const scanning = ref(false);
const looking = ref(false);
const notFound = ref(false);
const activeBatch = ref(null);
const lastBarcode = ref("");
const manualBarcode = ref("");
const history = ref([]);
const locations = ref([]);
const actionType = ref(null);
const saving = ref(false);
const toast = ref({ show: false, type: "success", message: "" });
const actionForm = reactive({
  condition: "",
  qty: null,
  location_id: "",
  notes: "",
});

const conditions = CONDITIONS.filter((c) => c !== "discarded");

const actionTitle = computed(() => {
  if (actionType.value === "condition") return "Update Condition";
  if (actionType.value === "cull") return "Cull Units";
  if (actionType.value === "transfer") return "Transfer Location";
  return "";
});

function startScan() {
  scanning.value = true;
}

async function onScanSuccess(barcode) {
  scanning.value = false;
  await lookup(barcode);
}

function onScanError(err) {
  scanning.value = false;
  showToast(err, "error");
}

async function lookupManual() {
  if (!manualBarcode.value.trim()) return;
  await lookup(manualBarcode.value.trim());
  manualBarcode.value = "";
}

async function lookup(barcode) {
  lastBarcode.value = barcode;
  looking.value = true;
  notFound.value = false;
  activeBatch.value = null;
  try {
    const res = await warehouseBatchService.scan(barcode);
    const result = res.data ?? res;
    if (result.found) {
      activeBatch.value = result.batch;
      history.value.unshift({
        barcode,
        batch: result.batch,
        scannedAt: new Date(),
      });
      if (history.value.length > 10) history.value.pop();
    } else {
      notFound.value = true;
    }
  } catch (e) {
    notFound.value = true;
  } finally {
    looking.value = false;
  }
}

function resetResult() {
  notFound.value = false;
  activeBatch.value = null;
  lastBarcode.value = "";
}

function quickAction(type) {
  actionType.value = type;
  actionForm.condition = "";
  actionForm.qty = null;
  actionForm.location_id = "";
  actionForm.notes = "";
}

async function submitAction() {
  saving.value = true;
  try {
    let res;
    if (actionType.value === "condition") {
      if (!actionForm.condition) {
        saving.value = false;
        return;
      }
      res = await warehouseBatchService.updateCondition(
        activeBatch.value.id,
        actionForm.condition,
        actionForm.notes,
      );
    } else if (actionType.value === "cull") {
      if (!actionForm.qty) {
        saving.value = false;
        return;
      }
      res = await warehouseBatchService.cull(
        activeBatch.value.id,
        actionForm.qty,
        actionForm.notes,
      );
    } else if (actionType.value === "transfer") {
      if (!actionForm.location_id) {
        saving.value = false;
        return;
      }
      res = await warehouseBatchService.transfer(
        activeBatch.value.id,
        actionForm.location_id,
        actionForm.notes,
      );
    }
    activeBatch.value = res?.data ?? res;
    actionType.value = null;
    showToast("Done!");
  } catch (e) {
    showToast(e?.message ?? "Failed", "error");
  } finally {
    saving.value = false;
  }
}

function freshnessPct(b) {
  if (!b.freshness_days || b.days_remaining === null) return 0;
  return Math.max(
    0,
    Math.min(100, Math.round((b.days_remaining / b.freshness_days) * 100)),
  );
}
function fmtDate(d) {
  return d
    ? new Date(d).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
      })
    : "—";
}
function fmtTime(d) {
  return d
    ? new Date(d).toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit",
      })
    : "";
}
function daysLabel(d) {
  return daysRemainingLabel(d);
}
function daysClass(d) {
  return daysRemainingClass(d);
}

function showToast(msg, type = "success") {
  toast.value = { show: true, type, message: msg };
  setTimeout(() => (toast.value.show = false), 3000);
}

onMounted(async () => {
  try {
    const res = await warehouseLocationService.list();
    locations.value = res.data?.data ?? res.data ?? res ?? [];
  } catch (e) {}
});
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.scanner-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.page-header {
  display: flex;
  flex-direction: column;
  gap: 6px;
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
.page-title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.page-sub {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}

.scanner-layout {
  display: grid;
  grid-template-columns: 420px 1fr;
  gap: 20px;
  align-items: start;
}

/* Scanner col */
.scanner-col {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.scanner-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e8ecf0;
  overflow: hidden;
}

.scan-idle {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 40px 24px;
}
.scan-icon {
  width: 90px;
  height: 90px;
  border-radius: 20px;
  background: #ecfdf5;
  display: flex;
  align-items: center;
  justify-content: center;
}
.idle-text {
  font-size: 15px;
  font-weight: 600;
  color: #374151;
  margin: 0;
}

.btn-scan {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 24px;
  background: #10b981;
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-scan:hover {
  background: #059669;
}
.btn-scan.small {
  padding: 8px 18px;
  font-size: 13px;
}

.or-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  color: #9ca3af;
  font-size: 12.5px;
}
.or-divider::before,
.or-divider::after {
  content: "";
  flex: 1;
  height: 1px;
  background: #e5e7eb;
}

.manual-input {
  display: flex;
  width: 100%;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  overflow: hidden;
}
.manual-input input {
  flex: 1;
  padding: 9px 12px;
  border: none;
  outline: none;
  font-size: 13.5px;
  color: #111827;
  font-family: inherit;
}
.manual-input button {
  padding: 0 16px;
  background: #111827;
  color: #fff;
  border: none;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
}
.manual-input button:hover:not(:disabled) {
  background: #1f2937;
}
.manual-input button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* History */
.history-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e8ecf0;
  padding: 16px;
}
.history-title {
  font-size: 12.5px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 10px;
}
.history-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.history-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s;
}
.history-item:hover {
  background: #f9fafb;
}
.hi-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.hi-info {
  flex: 1;
}
.hi-product {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}
.hi-batch {
  font-family: monospace;
  font-size: 11px;
  color: #9ca3af;
}
.hi-time {
  font-size: 11.5px;
  color: #9ca3af;
}

/* Result col */
.result-col {
}
.result-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e8ecf0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.loading-result {
  align-items: center;
  justify-content: center;
  gap: 14px;
  padding: 80px 24px;
  font-size: 14px;
  color: #6b7280;
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

.not-found {
  align-items: center;
  gap: 14px;
  padding: 60px 24px;
  text-align: center;
}
.nf-title {
  font-size: 16px;
  font-weight: 700;
  color: #ef4444;
  margin: 0;
}
.nf-sub {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}
.nf-sub code {
  font-family: monospace;
  background: #f3f4f6;
  padding: 2px 6px;
  border-radius: 4px;
}

.empty-result {
  align-items: center;
  gap: 12px;
  padding: 80px 24px;
  text-align: center;
  color: #9ca3af;
  font-size: 14px;
}
.empty-result p {
  margin: 0;
}

/* Found result */
.result-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
}
.rb-left {
  display: flex;
  align-items: center;
  gap: 8px;
}
.rb-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.rb-label {
  font-size: 15px;
  font-weight: 700;
}
.rb-expiry {
  font-size: 13px;
  font-weight: 600;
}
.rb-expiry.ok {
  color: #16a34a;
}
.rb-expiry.warn {
  color: #ca8a04;
}
.rb-expiry.warn-red {
  color: #ea580c;
}
.rb-expiry.danger {
  color: #dc2626;
}

.r-section {
  padding: 16px 20px;
  border-bottom: 1px solid #f3f4f6;
}
.r-product {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}
.rp-thumb {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
  color: #374151;
  overflow: hidden;
  flex-shrink: 0;
}
.rp-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.rp-name {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
}
.rp-sku {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}
.product-tags {
  display: flex;
  gap: 6px;
}
.tag {
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
}
.tag.cold {
  background: #dbeafe;
  color: #1d4ed8;
}
.tag.fragile {
  background: #fffbeb;
  color: #92400e;
}

.r-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ri-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}
.ri-row span:first-child {
  color: #6b7280;
}
.ri-row code {
  font-family: monospace;
  font-size: 12px;
  background: #f3f4f6;
  padding: 1px 6px;
  border-radius: 4px;
}
.bold-val {
  font-weight: 700;
  color: #111827;
}
.ok {
  color: #16a34a !important;
}
.warn {
  color: #ca8a04 !important;
}
.warn-red {
  color: #ea580c !important;
}
.danger {
  color: #dc2626 !important;
}

.fresh-label-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 5px;
}
.fresh-bar {
  height: 5px;
  background: #f3f4f6;
  border-radius: 4px;
  overflow: hidden;
}
.fresh-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.4s;
}

.r-actions {
}
.ra-title {
  font-size: 12px;
  font-weight: 600;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  margin-bottom: 10px;
}
.ra-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.ra-btn {
  padding: 9px;
  border-radius: 9px;
  border: 1.5px solid transparent;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.ra-btn.green {
  background: #dcfce7;
  color: #16a34a;
  border-color: #a7f3d0;
}
.ra-btn.green:hover {
  background: #bbf7d0;
}
.ra-btn.red {
  background: #fee2e2;
  color: #dc2626;
  border-color: #fca5a5;
}
.ra-btn.red:hover {
  background: #fecaca;
}
.ra-btn.blue {
  background: #dbeafe;
  color: #2563eb;
  border-color: #93c5fd;
}
.ra-btn.blue:hover {
  background: #bfdbfe;
}
.ra-btn.gray {
  background: #f3f4f6;
  color: #374151;
  border-color: #e5e7eb;
}
.ra-btn.gray:hover {
  background: #e5e7eb;
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
  width: 440px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
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
  gap: 12px;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid #f0f2f5;
}

.condition-options {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.cond-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 13px;
  border-radius: 20px;
  border: 1.5px solid #e5e7eb;
  background: #fff;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  color: #374151;
}
.cdot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}
.action-note {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
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
}
.field input:focus,
.field select:focus,
.field textarea:focus {
  border-color: #10b981;
}
.req {
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
}
.btn-primary:hover:not(:disabled) {
  background: #059669;
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
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
