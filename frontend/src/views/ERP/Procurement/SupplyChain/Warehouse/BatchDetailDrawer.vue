<template>
  <div class="drawer-inner">
    
    <!-- Header -->
    <div class="drawer-head">
      <div>
        <h2 class="drawer-title">Batch Detail</h2>
        <code class="drawer-batch">{{ batch.batch_number }}</code>
      </div>
      <button class="close-btn" @click="$emit('close')">
        <svg
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          width="18"
        >
          <path d="M6 6l8 8M14 6l-8 8" />
        </svg>
      </button>
    </div>

    <!-- Product info -->
    <div class="section product-row">
      <div class="product-avatar">
        <img
          v-if="localBatch.product?.image_url"
          :src="localBatch.product.image_url"
          alt=""
        />
        <span v-else>{{ localBatch.product?.product_name?.[0] ?? "?" }}</span>
      </div>
      <div>
        <div class="product-name">
          {{ localBatch.product?.product_name ?? "—" }}
        </div>
        <div class="product-sku">
          {{ localBatch.product?.sku }} · {{ localBatch.product?.flower_type }}
        </div>
        <div class="product-tags">
          <span
            v-if="localBatch.product?.requires_refrigeration"
            class="tag cold"
            >❄ Refrigerated</span
          >
          <span v-if="localBatch.product?.is_fragile" class="tag fragile"
            >⚠ Fragile</span
          >
        </div>
      </div>
    </div>

    <!-- Condition badge -->
    <div class="section">
      <div class="section-label">Current Condition</div>
      <div class="condition-row">
        <span
          class="condition-big"
          :style="{
            background: meta(localBatch.computed_condition).bg,
            color: meta(localBatch.computed_condition).text,
            border: `1.5px solid ${meta(localBatch.computed_condition).border}`,
          }"
        >
          <span
            class="cond-dot"
            :style="{ background: meta(localBatch.computed_condition).dot }"
          ></span>
          {{ meta(localBatch.computed_condition).label }}
        </span>
        <div
          v-if="localBatch.days_remaining !== null"
          class="expiry-info"
          :class="daysClass(localBatch.days_remaining)"
        >
          {{ daysLabel(localBatch.days_remaining) }}
          <span v-if="localBatch.expiration_date" class="expiry-date"
            >({{ fmtDate(localBatch.expiration_date) }})</span
          >
        </div>
      </div>
      <div
        v-if="localBatch.freshness_days && localBatch.days_remaining !== null"
        class="freshness-row"
      >
        <div class="freshness-bar">
          <div
            class="freshness-fill"
            :style="{
              width: freshnessPct(localBatch) + '%',
              background: meta(localBatch.computed_condition).dot,
            }"
          ></div>
        </div>
        <span>{{ freshnessPct(localBatch) }}% freshness remaining</span>
      </div>
    </div>

    <!-- Stats grid -->
    <div class="section stats-grid">
      <div class="stat-box">
        <div class="stat-num">{{ localBatch.qty_remaining }}</div>
        <div class="stat-lbl">Units Remaining</div>
      </div>
      <div class="stat-box">
        <div class="stat-num">{{ localBatch.qty_received }}</div>
        <div class="stat-lbl">Units Received</div>
      </div>
      <div class="stat-box">
        <div class="stat-num">
          {{ localBatch.qty_received - localBatch.qty_remaining }}
        </div>
        <div class="stat-lbl">Units Removed</div>
      </div>
      <div class="stat-box">
        <div class="stat-num">{{ localBatch.freshness_days ?? "—" }}</div>
        <div class="stat-lbl">Freshness Days</div>
      </div>
    </div>

    <!-- Date info -->
    <div class="section info-list">
      <div class="info-row">
        <span>Lot Number</span><span>{{ localBatch.lot_number ?? "—" }}</span>
      </div>
      <div class="info-row">
        <span>Received</span
        ><span>{{ fmtDate(localBatch.received_date) }}</span>
      </div>
      <div class="info-row">
        <span>Harvest Date</span
        ><span>{{ fmtDate(localBatch.harvest_date) }}</span>
      </div>
      <div class="info-row">
        <span>Expiration</span>
        <span :class="daysClass(localBatch.days_remaining)">{{
          fmtDate(localBatch.expiration_date)
        }}</span>
      </div>
      <div class="info-row">
        <span>Location</span>
        <span>{{
          localBatch.location?.name ?? localBatch.storage_location ?? "—"
        }}</span>
      </div>
    </div>

    <div v-if="localBatch.notes" class="section notes-box">
      <div class="section-label">Notes</div>
      <p>{{ localBatch.notes }}</p>
    </div>

    <!-- ── Actions ─────────────────────────────────────────────────── -->
    <div class="section actions-section">
      <div class="section-label">Actions</div>
      <div class="action-grid">
        <div class="action-card" @click="openAction('condition')">
          <div class="action-icon green">
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="18"
            >
              <circle cx="10" cy="10" r="7" />
              <path d="M7 10l2 2 4-4" />
            </svg>
          </div>
          <span>Update Condition</span>
        </div>
        <div class="action-card" @click="openAction('cull')">
          <div class="action-icon red">
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="18"
            >
              <path d="M6 6l8 8M14 6l-8 8" />
              <circle cx="10" cy="10" r="7" />
            </svg>
          </div>
          <span>Cull Units</span>
        </div>
        <div class="action-card" @click="openAction('transfer')">
          <div class="action-icon blue">
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="18"
            >
              <path d="M4 10h12M10 4l6 6-6 6" />
            </svg>
          </div>
          <span>Transfer Location</span>
        </div>
        <div class="action-card" @click="openAction('logs')">
          <div class="action-icon gray">
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="18"
            >
              <path d="M4 6h12M4 10h12M4 14h8" />
            </svg>
          </div>
          <span>View Logs</span>
        </div>
      </div>
    </div>

    <!-- ── Update Condition Panel ──────────────────────────────────── -->
    <transition name="panel-slide">
      <div v-if="activeAction === 'condition'" class="action-panel">
        <div class="panel-header">
          <span>Update Condition</span>
          <button @click="activeAction = null">×</button>
        </div>
        <div class="condition-options">
          <button
            v-for="c in conditions"
            :key="c"
            class="cond-btn"
            :class="{ selected: conditionForm.condition === c }"
            :style="
              conditionForm.condition === c
                ? {
                    background: meta(c).bg,
                    borderColor: meta(c).dot,
                    color: meta(c).text,
                  }
                : {}
            "
            @click="conditionForm.condition = c"
          >
            <span
              class="cond-dot-sm"
              :style="{ background: meta(c).dot }"
            ></span>
            {{ meta(c).label }}
          </button>
        </div>
        <div class="form-field">
          <label>Notes (optional)</label>
          <textarea
            v-model="conditionForm.notes"
            rows="2"
            placeholder="Reason for condition change…"
          ></textarea>
        </div>
        <button
          class="submit-btn green"
          :disabled="!conditionForm.condition || submitting"
          @click="submitCondition"
        >
          {{ submitting ? "Saving…" : "Update Condition" }}
        </button>
      </div>
    </transition>

    <!-- ── Cull Panel ──────────────────────────────────────────────── -->
    <transition name="panel-slide">
      <div v-if="activeAction === 'cull'" class="action-panel">
        <div class="panel-header">
          <span>Cull Units</span>
          <button @click="activeAction = null">×</button>
        </div>

        <!-- Availability indicator -->
        <div class="cull-availability">
          <div class="avail-bar">
            <div
              class="avail-fill"
              :style="{ width: cullFillPct + '%' }"
              :class="cullFillClass"
            ></div>
          </div>
          <div class="avail-labels">
            <span>
              Culling
              <strong
                :class="cullForm.qty > localBatch.qty_remaining ? 'over' : ''"
              >
                {{ cullForm.qty || 0 }}
              </strong>
            </span>
            <span
              >Available: <strong>{{ localBatch.qty_remaining }}</strong></span
            >
          </div>
        </div>

        <div class="form-field">
          <label>Quantity to Cull <span class="req">*</span></label>
          <div class="qty-input-wrap">
            <button
              class="qty-btn"
              @click="decrementCull"
              :disabled="!cullForm.qty || cullForm.qty <= 1"
            >
              −
            </button>
            <input
              v-model.number="cullForm.qty"
              type="number"
              min="1"
              :max="localBatch.qty_remaining"
              placeholder="0"
              class="qty-input"
              :class="{ 'input-error': cullQtyError }"
              @input="onCullQtyInput"
              @blur="onCullQtyBlur"
            />
            <button
              class="qty-btn"
              @click="incrementCull"
              :disabled="cullForm.qty >= localBatch.qty_remaining"
            >
              +
            </button>
            <button
              class="qty-btn max-btn"
              @click="
                cullForm.qty = localBatch.qty_remaining;
                cullQtyError = '';
              "
            >
              Max
            </button>
          </div>
          <span v-if="cullQtyError" class="err-msg">{{ cullQtyError }}</span>
        </div>

        <div class="form-field">
          <label>Notes (optional)</label>
          <textarea
            v-model="cullForm.notes"
            rows="2"
            placeholder="Reason for culling…"
          ></textarea>
        </div>

        <button
          class="submit-btn red"
          :disabled="!cullForm.qty || !!cullQtyError || submitting"
          @click="submitCull"
        >
          {{
            submitting
              ? "Saving…"
              : `Cull ${cullForm.qty || 0} of ${localBatch.qty_remaining} Units`
          }}
        </button>
      </div>
    </transition>

    <!-- ── Transfer Panel ──────────────────────────────────────────── -->
    <transition name="panel-slide">
      <div v-if="activeAction === 'transfer'" class="action-panel">
        <div class="panel-header">
          <span>Transfer Location</span>
          <button @click="activeAction = null">×</button>
        </div>
        <p class="panel-note">
          Current location:
          <strong>{{ localBatch.storage_location ?? "None" }}</strong>
        </p>
        <div class="form-field">
          <label>New Location <span class="req">*</span></label>
          <select v-model="transferForm.location_id">
            <option value="" disabled>Select location…</option>
            <option v-for="l in locations" :key="l.id" :value="l.id">
              {{ l.name }} {{ l.is_refrigerated ? "❄" : "" }}
              {{ l.capacity_units ? `(${l.capacity_units} cap)` : "" }}
            </option>
          </select>
        </div>
        <div class="form-field">
          <label>Notes (optional)</label>
          <textarea
            v-model="transferForm.notes"
            rows="2"
            placeholder="Reason for transfer…"
          ></textarea>
        </div>
        <button
          class="submit-btn blue"
          :disabled="!transferForm.location_id || submitting"
          @click="submitTransfer"
        >
          {{ submitting ? "Saving…" : "Transfer Batch" }}
        </button>
      </div>
    </transition>

    <!-- ── Logs Panel ──────────────────────────────────────────────── -->
    <transition name="panel-slide">
      <div v-if="activeAction === 'logs'" class="action-panel logs-panel">
        <div class="panel-header">
          <span>Batch Logs</span>
          <button @click="activeAction = null">×</button>
        </div>
        <div v-if="logsLoading" class="logs-loading">
          <div class="mini-spinner"></div>
        </div>
        <div v-else-if="!logs.length" class="logs-empty">
          No log entries yet.
        </div>
        <div v-else class="logs-list">
          <div v-for="log in logs" :key="log.id" class="log-entry">
            <div class="log-dot" :class="logDotClass(log.event_type)"></div>
            <div class="log-body">
              <div class="log-event">{{ formatEvent(log.event_type) }}</div>
              <div
                v-if="log.condition_before && log.condition_after"
                class="log-detail"
              >
                {{ meta(log.condition_before).label }} →
                {{ meta(log.condition_after).label }}
              </div>
              <div v-if="log.qty_change" class="log-detail">
                Qty: {{ log.qty_change > 0 ? "+" : "" }}{{ log.qty_change }} →
                {{ log.qty_after }} remaining
              </div>
              <div
                v-if="log.from_location || log.to_location"
                class="log-detail"
              >
                {{ log.from_location || "?" }} → {{ log.to_location || "?" }}
              </div>
              <div v-if="log.notes" class="log-note">{{ log.notes }}</div>
              <div class="log-time">
                {{ log.performer?.name ?? "System" }} ·
                {{ fmtDateTime(log.created_at) }}
              </div>
            </div>
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
import { ref, reactive, computed, watch } from "vue";
import {
  warehouseBatchService,
  conditionMeta,
  daysRemainingLabel,
  daysRemainingClass,
  CONDITIONS,
} from "../../../../../services/warehouseBatchService";


const props = defineProps({ batch: Object, locations: Array });
const emit = defineEmits(["close", "updated"]);

const localBatch = ref({ ...props.batch });
const activeAction = ref(null);
const submitting = ref(false);
const logs = ref([]);
const logsLoading = ref(false);

const conditions = CONDITIONS.filter((c) => c !== "discarded");

const conditionForm = reactive({ condition: "", notes: "" });
const cullForm = reactive({ qty: null, notes: "" });
const cullQtyError = ref("");
const transferForm = reactive({ location_id: "", notes: "" });
const toast = ref({ show: false, type: "success", message: "" });

// ── Cull helpers ──────────────────────────────────────────────────────────────

// Visual fill bar: shows how much of qty_remaining you're culling
const cullFillPct = computed(() => {
  const qty = cullForm.qty || 0;
  const max = localBatch.value.qty_remaining;
  if (!max) return 0;
  return Math.min(100, Math.round((qty / max) * 100));
});

const cullFillClass = computed(() => {
  if (cullFillPct.value >= 100) return "danger";
  if (cullFillPct.value >= 70) return "warn";
  return "ok";
});

function onCullQtyInput() {
  cullQtyError.value = "";
  const qty = cullForm.qty;
  const max = localBatch.value.qty_remaining;

  if (qty === null || qty === "") return;
  if (!Number.isInteger(qty) || qty < 1) {
    cullQtyError.value = "Must be a whole number of at least 1.";
    return;
  }
  if (qty > max) {
    cullQtyError.value = `Cannot cull ${qty} — only ${max} unit${max === 1 ? "" : "s"} remaining.`;
  }
}

function onCullQtyBlur() {
  // Auto-clamp on blur so the user doesn't leave with an invalid value
  if (cullForm.qty !== null && cullForm.qty > localBatch.value.qty_remaining) {
    cullForm.qty = localBatch.value.qty_remaining;
    cullQtyError.value = "";
  }
  if (cullForm.qty !== null && cullForm.qty < 1) {
    cullForm.qty = 1;
    cullQtyError.value = "";
  }
}

function incrementCull() {
  const next = (cullForm.qty || 0) + 1;
  if (next <= localBatch.value.qty_remaining) {
    cullForm.qty = next;
    cullQtyError.value = "";
  }
}

function decrementCull() {
  const next = (cullForm.qty || 0) - 1;
  if (next >= 1) {
    cullForm.qty = next;
    cullQtyError.value = "";
  }
}

// ── Open action (reset cull error on open) ────────────────────────────────────

function openAction(name) {
  activeAction.value = name;
  cullQtyError.value = "";
  cullForm.qty = null;
  cullForm.notes = "";
}

// ── Watch logs ────────────────────────────────────────────────────────────────

watch(activeAction, async (val) => {
  if (val === "logs") await fetchLogs();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function meta(c) {
  return conditionMeta(c);
}
function daysLabel(d) {
  return daysRemainingLabel(d);
}
function daysClass(d) {
  return daysRemainingClass(d);
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
function fmtDateTime(d) {
  if (!d) return "—";
  return new Date(d).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}
function freshnessPct(b) {
  if (!b.freshness_days || b.days_remaining === null) return 0;
  return Math.max(
    0,
    Math.min(100, Math.round((b.days_remaining / b.freshness_days) * 100)),
  );
}
function formatEvent(type) {
  const map = {
    CONDITION_UPDATED: "Condition Updated",
    QUANTITY_ADJUSTED: "Quantity Adjusted",
    TRANSFERRED: "Transferred",
    CULLED: "Culled",
    SCANNED: "Scanned",
    NOTE: "Note Added",
  };
  return map[type] ?? type;
}
function logDotClass(type) {
  if (type === "CULLED" || type === "CONDITION_UPDATED") return "red";
  if (type === "TRANSFERRED") return "blue";
  if (type === "SCANNED") return "gray";
  return "green";
}

// ── API calls ─────────────────────────────────────────────────────────────────

async function fetchLogs() {
  logsLoading.value = true;
  try {
    const res = await warehouseBatchService.logs(localBatch.value.id);
    logs.value = res.data ?? res ?? [];
  } catch (e) {
    console.error(e);
  } finally {
    logsLoading.value = false;
  }
}

async function submitCondition() {
  submitting.value = true;
  try {
    const res = await warehouseBatchService.updateCondition(
      localBatch.value.id,
      conditionForm.condition,
      conditionForm.notes,
    );
    localBatch.value = res.data ?? res;
    emit("updated", localBatch.value);
    showToast("Condition updated!");
    activeAction.value = null;
    conditionForm.condition = "";
    conditionForm.notes = "";
  } catch (e) {
    showToast(e?.response?.data?.message ?? e?.message ?? "Failed", "error");
  } finally {
    submitting.value = false;
  }
}

async function submitCull() {
  // Final client-side guard before sending
  cullQtyError.value = "";
  const qty = cullForm.qty;
  const max = localBatch.value.qty_remaining;

  if (!qty || qty < 1) {
    cullQtyError.value = "Enter a quantity to cull.";
    return;
  }
  if (!Number.isInteger(qty)) {
    cullQtyError.value = "Must be a whole number.";
    return;
  }
  if (qty > max) {
    cullQtyError.value = `Cannot cull ${qty} — only ${max} unit${max === 1 ? "" : "s"} remaining.`;
    return;
  }

  submitting.value = true;
  try {
    const res = await warehouseBatchService.cull(
      localBatch.value.id,
      qty,
      cullForm.notes,
    );
    localBatch.value = res.data ?? res;
    emit("updated", localBatch.value);
    showToast(`${qty} unit${qty === 1 ? "" : "s"} culled.`);
    activeAction.value = null;
    cullForm.qty = null;
    cullForm.notes = "";
    cullQtyError.value = "";
  } catch (e) {
    // Show the server's message (e.g. "only X remaining")
    const msg =
      e?.response?.data?.message ??
      e?.response?.data?.errors?.qty?.[0] ??
      e?.message ??
      "Failed to cull";
    cullQtyError.value = msg;
    showToast(msg, "error");
  } finally {
    submitting.value = false;
  }
}

async function submitTransfer() {
  submitting.value = true;
  try {
    const res = await warehouseBatchService.transfer(
      localBatch.value.id,
      transferForm.location_id,
      transferForm.notes,
    );
    localBatch.value = res.data ?? res;
    emit("updated", localBatch.value);
    showToast("Batch transferred!");
    activeAction.value = null;
    transferForm.location_id = "";
    transferForm.notes = "";
  } catch (e) {
    showToast(e?.response?.data?.message ?? e?.message ?? "Failed", "error");
  } finally {
    submitting.value = false;
  }
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

.drawer-inner {
  display: flex;
  flex-direction: column;
  gap: 0;
  padding-bottom: 60px;
}

/* Header */
.drawer-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 20px 20px 16px;
  border-bottom: 1px solid #f0f2f5;
  position: sticky;
  top: 0;
  background: #fff;
  z-index: 10;
}
.drawer-title {
  font-size: 17px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 2px;
}
.drawer-batch {
  font-family: monospace;
  font-size: 12px;
  background: #f3f4f6;
  padding: 2px 6px;
  border-radius: 4px;
  color: #6b7280;
}
.close-btn {
  border: none;
  background: #f3f4f6;
  border-radius: 8px;
  width: 32px;
  height: 32px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
}
.close-btn:hover {
  background: #e5e7eb;
  color: #374151;
}

/* Sections */
.section {
  padding: 16px 20px;
  border-bottom: 1px solid #f0f2f5;
}
.section-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: #9ca3af;
  margin-bottom: 10px;
}

/* Product row */
.product-row {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}
.product-avatar {
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
.product-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.product-name {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
}
.product-sku {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}
.product-tags {
  display: flex;
  gap: 6px;
  margin-top: 6px;
}
.tag {
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
}
.tag.cold {
  background: #eff6ff;
  color: #1d4ed8;
}
.tag.fragile {
  background: #fffbeb;
  color: #92400e;
}

/* Condition */
.condition-row {
  display: flex;
  align-items: center;
  gap: 12px;
}
.condition-big {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
}
.cond-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.expiry-info {
  font-size: 13px;
  font-weight: 600;
}
.expiry-info.ok {
  color: #16a34a;
}
.expiry-info.warn {
  color: #ca8a04;
}
.expiry-info.warn-red {
  color: #ea580c;
}
.expiry-info.danger {
  color: #dc2626;
}
.expiry-date {
  font-size: 12px;
  font-weight: 400;
  color: #6b7280;
}
.freshness-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
  font-size: 12px;
  color: #6b7280;
}
.freshness-bar {
  flex: 1;
  height: 6px;
  background: #f3f4f6;
  border-radius: 6px;
  overflow: hidden;
}
.freshness-fill {
  height: 100%;
  border-radius: 6px;
  transition: width 0.4s;
}

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}
.stat-box {
  background: #f9fafb;
  border-radius: 10px;
  padding: 10px 12px;
  text-align: center;
}
.stat-num {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
}
.stat-lbl {
  font-size: 10.5px;
  color: #9ca3af;
  margin-top: 2px;
}

/* Info list */
.info-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.info-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}
.info-row span:first-child {
  color: #6b7280;
}
.info-row span:last-child {
  font-weight: 500;
  color: #374151;
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

.notes-box p {
  font-size: 13px;
  color: #374151;
  margin: 0;
  line-height: 1.6;
}

/* Actions grid */
.action-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}
.action-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  transition: background 0.15s;
  background: #fff;
}
.action-card:hover {
  background: #f9fafb;
  border-color: #d1d5db;
}
.action-icon {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.action-icon.green {
  background: #dcfce7;
  color: #16a34a;
}
.action-icon.red {
  background: #fee2e2;
  color: #dc2626;
}
.action-icon.blue {
  background: #dbeafe;
  color: #2563eb;
}
.action-icon.gray {
  background: #f3f4f6;
  color: #6b7280;
}

/* Action panels */
.action-panel {
  margin: 0 20px 16px;
  background: #f9fafb;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.panel-header span {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}
.panel-header button {
  border: none;
  background: none;
  font-size: 20px;
  color: #9ca3af;
  cursor: pointer;
  line-height: 1;
}
.panel-note {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 12px;
}

.condition-options {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}
.cond-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  border: 1.5px solid #e5e7eb;
  background: #fff;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  color: #374151;
  transition: all 0.15s;
}
.cond-btn:hover {
  border-color: #d1d5db;
}
.cond-dot-sm {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

/* ── Cull availability bar ───────────────────────────────────────────────── */
.cull-availability {
  margin-bottom: 14px;
}
.avail-bar {
  height: 6px;
  background: #e5e7eb;
  border-radius: 6px;
  overflow: hidden;
  margin-bottom: 6px;
}
.avail-fill {
  height: 100%;
  border-radius: 6px;
  transition:
    width 0.25s,
    background 0.25s;
}
.avail-fill.ok {
  background: #10b981;
}
.avail-fill.warn {
  background: #f59e0b;
}
.avail-fill.danger {
  background: #ef4444;
}
.avail-labels {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #6b7280;
}
.avail-labels strong {
  color: #111827;
}
.avail-labels strong.over {
  color: #ef4444;
}

/* ── Qty input row ───────────────────────────────────────────────────────── */
.qty-input-wrap {
  display: flex;
  gap: 6px;
  align-items: center;
}
.qty-btn {
  width: 32px;
  height: 36px;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  font-size: 16px;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 0.12s;
}
.qty-btn:hover:not(:disabled) {
  background: #f3f4f6;
}
.qty-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.qty-btn.max-btn {
  width: auto;
  padding: 0 10px;
  font-size: 11.5px;
  font-weight: 700;
  color: #059669;
  border-color: #6ee7b7;
  background: #ecfdf5;
}
.qty-btn.max-btn:hover {
  background: #d1fae5;
}
.qty-input {
  flex: 1;
  padding: 8px 11px;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  outline: none;
  font-family: inherit;
  text-align: center;
}
.qty-input:focus {
  border-color: #10b981;
}
.qty-input.input-error {
  border-color: #ef4444;
  background: #fff5f5;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-bottom: 12px;
}
.form-field label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}
.form-field input,
.form-field select,
.form-field textarea {
  padding: 8px 11px;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13.5px;
  color: #111827;
  outline: none;
  font-family: inherit;
  background: #fff;
}
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
  border-color: #10b981;
}
.req {
  color: #ef4444;
}
.err-msg {
  font-size: 12px;
  color: #ef4444;
  font-weight: 500;
  margin-top: 2px;
}

.submit-btn {
  width: 100%;
  padding: 10px;
  border-radius: 9px;
  border: none;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
  color: #fff;
  font-family: inherit;
}
.submit-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.submit-btn.green {
  background: #10b981;
}
.submit-btn.green:hover:not(:disabled) {
  background: #059669;
}
.submit-btn.red {
  background: #ef4444;
}
.submit-btn.red:hover:not(:disabled) {
  background: #dc2626;
}
.submit-btn.blue {
  background: #3b82f6;
}
.submit-btn.blue:hover:not(:disabled) {
  background: #2563eb;
}

/* Logs */
.logs-loading {
  display: flex;
  justify-content: center;
  padding: 20px;
}
.mini-spinner {
  width: 22px;
  height: 22px;
  border: 2px solid #e5e7eb;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
.logs-empty {
  font-size: 13px;
  color: #9ca3af;
  text-align: center;
  padding: 16px;
}
.logs-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.log-entry {
  display: flex;
  gap: 10px;
}
.log-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-top: 5px;
  flex-shrink: 0;
}
.log-dot.green {
  background: #10b981;
}
.log-dot.red {
  background: #ef4444;
}
.log-dot.blue {
  background: #3b82f6;
}
.log-dot.gray {
  background: #9ca3af;
}
.log-body {
  flex: 1;
}
.log-event {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}
.log-detail {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}
.log-note {
  font-size: 12px;
  color: #374151;
  font-style: italic;
  margin-top: 2px;
}
.log-time {
  font-size: 11px;
  color: #9ca3af;
  margin-top: 3px;
}

/* Panel slide */
.panel-slide-enter-active,
.panel-slide-leave-active {
  transition: all 0.2s;
}
.panel-slide-enter-from,
.panel-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* Toast */
.toast {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  padding: 11px 22px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 500;
  z-index: 9999;
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
  transition: all 0.25s;
}
.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(10px);
}
</style>
