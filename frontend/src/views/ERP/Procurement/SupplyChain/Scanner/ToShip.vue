<template>
  <div class="scan-page">
    
    <div class="page-header">
      <div>
        <h1 class="page-title">To Ship</h1>
        <p class="page-sub">
          Scan a delivery barcode to dispatch it and mark as In Transit
        </p>
      </div>
      <span class="queue-badge blue"
        >{{ queue.length }} orders ready to ship</span
      >
    </div>

    <div class="scan-layout">
      <div class="scan-col">
        <div class="dark-card">
          <div class="dark-card-header">
            <div class="dc-title">
              <svg viewBox="0 0 24 24" width="16" fill="currentColor">
                <path
                  fill-rule="evenodd"
                  d="M2 6h1v12H2zm2 0h2v12H4zm4 0h1v12H8zm2 0h3v12h-3zm4 0h1v12h-1zm3 0h1v12h-1zm2 0h1v12h-1zm2 0h1v12h-1z"
                />
              </svg>
              Delivery Barcode Scanner
            </div>
            <span class="live-dot blue"></span>
          </div>
          <div class="dark-card-body">
            <ScannerInput
              ref="scannerRef"
              placeholder="Scan delivery barcode to dispatch…"
              :auto-focus="true"
              @scan="handleScan"
            />
          </div>
        </div>

        <transition name="result-panel">
          <div v-if="lastResult" class="dark-card result-card">
            <div class="dark-card-header">
              <div class="dc-title">Last Dispatched</div>
              <span class="result-time">{{ lastTime }}</span>
            </div>
            <div class="result-body">
              <div class="result-ref blue">{{ lastResult.delivery_id }}</div>
              <div class="result-row">
                <span class="rl">Order</span>
                <span class="rv">{{
                  lastResult.order?.order_number ?? "—"
                }}</span>
              </div>
              <div class="result-row">
                <span class="rl">Address</span>
                <span class="rv">{{
                  lastResult.order?.delivery_address ?? "—"
                }}</span>
              </div>
              <div class="result-row">
                <span class="rl">Contact</span>
                <span class="rv">{{
                  lastResult.order?.delivery_contact_number ?? "—"
                }}</span>
              </div>
              <div class="result-row">
                <span class="rl">New Status</span>
                <span class="rv" style="color: #3b82f6">→ In Transit ✓</span>
              </div>
            </div>
          </div>
        </transition>

        <div class="dark-card" v-if="scanLog.length">
          <div class="dark-card-header">
            <div class="dc-title">Dispatch Log</div>
            <button class="clear-btn" @click="scanLog = []">Clear</button>
          </div>
          <div class="scan-log-list">
            <div v-for="e in scanLog" :key="e.id" class="log-entry">
              <span class="log-time">{{ e.time }}</span>
              <span class="log-barcode">{{ e.barcode }}</span>
              <span class="log-msg">{{ e.message }}</span>
              <span class="log-dot" :class="e.type"></span>
            </div>
          </div>
        </div>
      </div>

      <div class="info-col">
        <div class="light-card">
          <div class="lc-header">
            <div class="lc-title">Ship Instructions</div>
            <span class="count-badge blue">{{ queue.length }}</span>
          </div>
          <div class="instructions">
            <div class="step">
              <div class="step-num blue">1</div>
              <div>Pick up the order and locate its barcode label</div>
            </div>
            <div class="step">
              <div class="step-num blue">2</div>
              <div>Scan the barcode on the packaging</div>
            </div>
            <div class="step">
              <div class="step-num blue">3</div>
              <div>
                Status updates to <strong>In Transit</strong> automatically
              </div>
            </div>
          </div>
        </div>

        <div class="light-card">
          <div class="lc-header"><div class="lc-title">Ready to Ship</div></div>
          <div v-if="loadingQueue" class="queue-loading">
            <div class="spinner"></div>
          </div>
          <div v-else-if="!queue.length" class="queue-empty">
            No orders waiting to ship
          </div>
          <div v-else class="queue-list">
            <div
              v-for="d in queue"
              :key="d.id"
              class="queue-item"
              :class="{ done: shippedIds.has(d.id) }"
            >
              <div class="qi-left">
                <div
                  class="qi-icon blue"
                  :class="{ done: shippedIds.has(d.id) }"
                >
                  <svg
                    v-if="shippedIds.has(d.id)"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    width="10"
                  >
                    <path
                      d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z"
                    />
                  </svg>
                  <svg
                    v-else
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="10"
                  >
                    <rect x="1" y="5" width="10" height="10" rx="1" />
                    <path d="M11 8h5l2 3v4h-7V8z" />
                    <circle cx="4" cy="15" r="2" />
                    <circle cx="15" cy="15" r="2" />
                  </svg>
                </div>
                <div>
                  <div class="qi-ref" :class="{ strike: shippedIds.has(d.id) }">
                    {{ d.order?.order_number ?? d.delivery_id }}
                  </div>
                  <div class="qi-sub">
                    {{ d.order?.delivery_address ?? "—" }}
                  </div>
                </div>
              </div>
              <div class="qi-right">
                <span class="qi-amt">₱{{ fmt(d.order?.total_amount) }}</span>
                <span class="done-tag blue" v-if="shippedIds.has(d.id)"
                  >Shipped ✓</span
                >
              </div>
            </div>
          </div>
        </div>

        <div class="light-card" v-if="shippedIds.size > 0">
          <div class="session-stats">
            <div class="ss-item">
              <span class="ss-val blue">{{ shippedIds.size }}</span
              ><span class="ss-lbl">Shipped</span>
            </div>
            <div class="ss-div"></div>
            <div class="ss-item">
              <span class="ss-val">{{ queue.length - shippedIds.size }}</span
              ><span class="ss-lbl">Remaining</span>
            </div>
            <div class="ss-div"></div>
            <div class="ss-item">
              <span class="ss-val blue">{{ pct }}%</span
              ><span class="ss-lbl">Done</span>
            </div>
          </div>
          <div class="prog-wrap">
            <div class="prog-bar">
              <div class="prog-fill blue" :style="{ width: pct + '%' }"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { toast } from "vue3-toastify";
import ScannerInput from "../../../../../layouts/components/ScannerInput.vue";
import { deliveryService } from "../../../../../services/deliveryService";


const TODAY_KEY = `shippedIds_${new Date().toISOString().slice(0, 10)}`;

function loadShippedIds() {
  try {
    const saved = localStorage.getItem(TODAY_KEY);
    return saved ? new Set(JSON.parse(saved)) : new Set();
  } catch {
    return new Set();
  }
}

function saveShippedIds(ids) {
  try {
    localStorage.setItem(TODAY_KEY, JSON.stringify([...ids]));
  } catch {}
}

const scannerRef = ref(null);
const queue = ref([]);
const loadingQueue = ref(false);
const lastResult = ref(null);
const lastTime = ref("");
const scanLog = ref([]);
const shippedIds = ref(loadShippedIds());
let logId = 0;
let pollTimer = null;

const pct = computed(() => {
  if (!queue.value.length) return 0;
  return Math.round((shippedIds.value.size / queue.value.length) * 100);
});

async function loadQueue() {
  try {
    const res = await deliveryService.scOrders({
      status: "to_processed",
      per_page: 100,
    });
    const raw = res?.data?.data ?? res?.data ?? res ?? [];
    queue.value = Array.isArray(raw) ? raw : [];
  } catch (e) {
    console.error("[Queue] Load failed:", e);
  }
}

async function handleScan(barcode) {
  scannerRef.value?.setScanning(true);
  try {
    const code = barcode.trim().toUpperCase();
    const res = await deliveryService.scan(code, "to_ship");
    const delivery = res?.data?.delivery ?? res?.delivery ?? res;

    lastResult.value = delivery;
    lastTime.value = new Date().toLocaleTimeString();
    shippedIds.value = new Set([...shippedIds.value, delivery.id]);
    saveShippedIds(shippedIds.value);

    const orderNum =
      delivery.order?.order_number ?? delivery.delivery_id ?? code;
    addLog(code, "ok", `${orderNum} → In Transit`);
    scannerRef.value?.showResult("ok", `${orderNum} dispatched`);
    toast.success("Order dispatched successfully", { autoClose: 2500 });

    await loadQueue();
  } catch (e) {
    const msg = e?.response?.data?.message ?? e?.message ?? "Scan failed";
    addLog(barcode, "err", msg);
    scannerRef.value?.showResult("err", msg);
  } finally {
    scannerRef.value?.setScanning(false);
  }
}

function addLog(barcode, type, message) {
  scanLog.value.unshift({
    id: ++logId,
    barcode,
    type,
    message,
    time: new Date().toLocaleTimeString("en-US", {
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    }),
  });
  if (scanLog.value.length > 30) scanLog.value.pop();
}

function fmt(n) {
  return Number(n || 0).toLocaleString("en-US", { minimumFractionDigits: 2 });
}

onMounted(async () => {
  loadingQueue.value = true;
  await loadQueue();
  loadingQueue.value = false;
  pollTimer = setInterval(loadQueue, 10000);
});

onUnmounted(() => clearInterval(pollTimer));
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.scan-page {
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

.queue-badge {
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 12.5px;
  font-weight: 600;
}
.queue-badge.amber {
  background: #fef3c7;
  color: #92400e;
}
.queue-badge.blue {
  background: #dbeafe;
  color: #1d4ed8;
}
.queue-badge.purple {
  background: #ede9fe;
  color: #6d28d9;
}

.scan-layout {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 20px;
  align-items: start;
}
.scan-col,
.info-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Dark cards */
.dark-card {
  background: #0f1923;
  border: 1px solid #1e2a35;
  border-radius: 16px;
  overflow: hidden;
}
.dark-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid #1e2a35;
}
.dc-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  font-weight: 700;
  color: #94a3b8;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}
.dark-card-body {
  padding: 18px;
}
.result-time {
  font-size: 11.5px;
  color: #3a4e60;
  font-family: monospace;
}
.clear-btn {
  border: 1px solid #1e2a35;
  background: none;
  color: #3a4e60;
  font-size: 12px;
  padding: 3px 10px;
  border-radius: 6px;
  cursor: pointer;
}
.clear-btn:hover {
  color: #ef4444;
  border-color: #ef4444;
}

/* Live dot */
.live-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  animation: live-pulse 2s infinite;
}
.live-dot.amber {
  background: #f59e0b;
  box-shadow: 0 0 8px #f59e0b;
}
.live-dot.blue {
  background: #3b82f6;
  box-shadow: 0 0 8px #3b82f6;
}
.live-dot.purple {
  background: #8b5cf6;
  box-shadow: 0 0 8px #8b5cf6;
}
@keyframes live-pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

/* Result card */
.result-body {
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.result-ref {
  font-size: 18px;
  font-weight: 800;
  font-family: monospace;
  letter-spacing: 0.05em;
}
.result-ref.amber {
  color: #f59e0b;
}
.result-ref.blue {
  color: #3b82f6;
}
.result-ref.purple {
  color: #8b5cf6;
}
.result-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}
.rl {
  color: #3a4e60;
}
.rv {
  font-weight: 600;
  color: #94a3b8;
}
.note-banner {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border-radius: 8px;
  background: rgba(139, 92, 246, 0.1);
  border: 1px solid rgba(139, 92, 246, 0.25);
  color: #a78bfa;
  font-size: 12px;
}

/* Scan log */
.scan-log-list {
  max-height: 180px;
  overflow-y: auto;
}
.log-entry {
  display: grid;
  grid-template-columns: 70px 1fr 1fr 10px;
  align-items: center;
  gap: 10px;
  padding: 8px 18px;
  border-bottom: 1px solid #0c1118;
  font-size: 12px;
}
.log-entry:last-child {
  border-bottom: none;
}
.log-time {
  font-family: monospace;
  color: #3a4e60;
  font-size: 11px;
}
.log-barcode {
  font-family: monospace;
  color: #94a3b8;
}
.log-msg {
  color: #64748b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.log-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  justify-self: end;
}
.log-dot.ok {
  background: #10b981;
}
.log-dot.err {
  background: #ef4444;
}

/* Light cards */
.light-card {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 14px;
  overflow: hidden;
}
.lc-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid #f0f2f5;
}
.lc-title {
  font-size: 13.5px;
  font-weight: 700;
  color: #111827;
}

.count-badge {
  padding: 3px 9px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 600;
}
.count-badge.amber {
  background: #fef3c7;
  color: #92400e;
}
.count-badge.blue {
  background: #dbeafe;
  color: #1d4ed8;
}
.count-badge.purple {
  background: #ede9fe;
  color: #6d28d9;
}

/* Instructions */
.instructions {
  padding: 14px 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.step {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13px;
  color: #374151;
  line-height: 1.5;
}
.step-num {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.step-num.amber {
  background: #fef3c7;
  color: #92400e;
}
.step-num.blue {
  background: #dbeafe;
  color: #1d4ed8;
}
.step-num.purple {
  background: #ede9fe;
  color: #6d28d9;
}
.step-num.auto {
  background: #fef9c3;
  color: #854d0e;
}
.connector-step {
  padding: 0;
}
.ws-connector {
  width: 2px;
  height: 14px;
  background: #e5e7eb;
  margin-left: 11px;
}

/* Queue */
.queue-loading {
  display: flex;
  justify-content: center;
  padding: 32px;
}
.queue-empty {
  text-align: center;
  padding: 24px;
  color: #9ca3af;
  font-size: 13px;
}
.queue-list {
  max-height: 280px;
  overflow-y: auto;
}
.queue-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 11px 18px;
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.12s;
}
.queue-item:last-child {
  border-bottom: none;
}
.queue-item.done {
  opacity: 0.5;
}

.qi-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.qi-icon {
  width: 26px;
  height: 26px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s;
}
.qi-icon.amber {
  background: #fef3c7;
  color: #d97706;
}
.qi-icon.blue {
  background: #eff6ff;
  color: #3b82f6;
}
.qi-icon.purple {
  background: #f5f3ff;
  color: #7c3aed;
}
.qi-icon.done {
  background: #dcfce7;
  color: #16a34a;
}
.qi-ref {
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  font-family: monospace;
}
.qi-ref.strike {
  text-decoration: line-through;
  color: #9ca3af;
}
.qi-sub {
  font-size: 11.5px;
  color: #9ca3af;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 160px;
}
.qi-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
}
.qi-amt {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.done-tag {
  font-size: 10.5px;
  font-weight: 600;
}
.done-tag.amber {
  color: #f59e0b;
}
.done-tag.blue {
  color: #3b82f6;
}
.done-tag.purple {
  color: #8b5cf6;
}
.transit-badge {
  font-size: 10.5px;
  font-weight: 600;
  color: #3b82f6;
  background: #dbeafe;
  padding: 2px 7px;
  border-radius: 10px;
}

/* Stats */
.session-stats {
  display: flex;
  align-items: center;
  justify-content: space-around;
  padding: 16px;
}
.ss-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
}
.ss-val {
  font-size: 20px;
  font-weight: 800;
  color: #111827;
}
.ss-val.amber {
  color: #f59e0b;
}
.ss-val.blue {
  color: #3b82f6;
}
.ss-val.purple {
  color: #8b5cf6;
}
.ss-lbl {
  font-size: 11px;
  color: #9ca3af;
}
.ss-div {
  width: 1px;
  height: 30px;
  background: #e5e7eb;
}

.prog-wrap {
  padding: 0 16px 14px;
}
.prog-bar {
  height: 5px;
  background: #f3f4f6;
  border-radius: 3px;
  overflow: hidden;
}
.prog-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.5s;
}
.prog-fill.amber {
  background: #f59e0b;
}
.prog-fill.blue {
  background: #3b82f6;
}
.prog-fill.purple {
  background: #8b5cf6;
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

.result-panel-enter-active {
  transition: all 0.3s;
}
.result-panel-enter-from {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
