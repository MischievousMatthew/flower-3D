<template>
  <div class="vendor-orders-page">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">My Orders</h1>
        <p class="page-sub">Manage and scan your delivery orders</p>
      </div>
      <button class="btn-scan" @click="openScanner(null)">
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          width="16"
        >
          <rect x="3" y="3" width="5" height="5" rx="1" />
          <rect x="16" y="3" width="5" height="5" rx="1" />
          <rect x="3" y="16" width="5" height="5" rx="1" />
          <path d="M16 16h5v5M21 16v.01M16 21h.01" />
          <path d="M8 3v2M3 8h2M8 21v-2M3 16h2M16 8h2M21 8h.01" />
        </svg>
        Scan Delivery
      </button>
    </div>

    <!-- Status chips -->
    <div class="status-chips">
      <button
        v-for="tab in statusTabs"
        :key="tab.value"
        class="chip"
        :class="[tab.color, { active: activeTab === tab.value }]"
        @click="
          activeTab = tab.value;
          fetchOrders();
        "
      >
        {{ tab.label }}
        <span class="chip-count">{{ counts[tab.value] ?? 0 }}</span>
      </button>
    </div>

    <!-- Search -->
    <div class="search-row">
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
          placeholder="Search order ID or delivery ID…"
          @input="debouncedFetch"
        />
      </div>
    </div>

    <!-- Orders grid -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
    </div>

    <div v-else-if="!orders.length" class="empty-state">
      <svg
        viewBox="0 0 48 48"
        fill="none"
        stroke="#d1d5db"
        stroke-width="2"
        width="48"
      >
        <rect x="8" y="8" width="32" height="32" rx="4" />
        <path d="M16 24h16M16 30h10" />
      </svg>
      <p>No orders found</p>
    </div>

    <div v-else class="orders-grid">
      <div v-for="d in orders" :key="d.id" class="order-card">
        <div class="order-card-header">
          <div>
            <div class="order-id">
              #{{ d.order?.order_number ?? d.order?.id ?? "—" }}
            </div>
            <div class="delivery-id">{{ d.delivery_id }}</div>
          </div>
          <span class="status-badge" :class="d.status">{{
            d.status_label
          }}</span>
        </div>

        <!-- Items preview -->
        <div class="items-preview" v-if="d.order?.items?.length">
          <div
            v-for="item in d.order.items.slice(0, 3)"
            :key="item.id"
            class="item-row"
          >
            <img
              v-if="item.product_image"
              :src="item.product_image"
              class="item-thumb"
            />
            <div class="item-thumb placeholder" v-else>
              {{ item.product_name?.[0] }}
            </div>
            <div class="item-info">
              <span class="item-name">{{ item.product_name }}</span>
              <span class="item-meta"
                >{{ item.color }} · x{{ item.quantity }} · ₱{{
                  Number(item.unit_price).toFixed(2)
                }}</span
              >
            </div>
            <span class="item-sub"
              >₱{{ Number(item.subtotal).toFixed(2) }}</span
            >
          </div>
          <div v-if="d.order.items.length > 3" class="more-items">
            +{{ d.order.items.length - 3 }} more items
          </div>
        </div>

        <!-- Total & actions -->
        <div class="order-card-footer">
          <div class="order-total">
            Total:
            <strong
              >₱{{ Number(d.order?.total_amount ?? 0).toFixed(2) }}</strong
            >
          </div>
          <div class="card-actions">
            <button class="btn-qr" @click="showQR(d)" title="View QR Code">
              <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                <path
                  d="M3 3h5v5H3V3zm2 2v1h1V5H5zm7-2h5v5h-5V3zm2 2v1h1V5h-1zM3 12h5v5H3v-5zm2 2v1h1v-1H5zm8 3h2v2h-2v-2zm-3-3h2v2h-2v-2zm3 0h2v2h-2v-2zm-6-3h2v2h-2v-2zm3 0h2v2h-2v-2z"
                />
              </svg>
            </button>
            <button
              class="btn-scan-order"
              @click="openScanner(d)"
              :disabled="
                ['completed', 'returned', 'refunded'].includes(d.status)
              "
            >
              <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                width="14"
              >
                <path
                  d="M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2"
                />
                <path d="M8 12h8" />
              </svg>
              Scan
            </button>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="progress-track">
          <div
            v-for="(step, i) in steps"
            :key="step.value"
            class="progress-step"
            :class="{
              done: stepIndex(d.status) >= i,
              current: stepIndex(d.status) === i,
            }"
          >
            <div class="step-dot"></div>
            <span class="step-label">{{ step.label }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="meta.last_page > 1">
      <button
        class="page-btn"
        :disabled="meta.current_page === 1"
        @click="fetchOrders(meta.current_page - 1)"
      >
        ‹
      </button>
      <span class="page-info"
        >{{ meta.current_page }} / {{ meta.last_page }}</span
      >
      <button
        class="page-btn"
        :disabled="meta.current_page === meta.last_page"
        @click="fetchOrders(meta.current_page + 1)"
      >
        ›
      </button>
    </div>

    <!-- ── Scanner Modal ────────────────────────────────────────────────────── -->
    <teleport to="body">
      <div v-if="scanModal.open" class="modal-backdrop" @click.self="closeScan">
        <div class="scan-modal">
          <div class="scan-modal-header">
            <h2>Scan Delivery</h2>
            <button class="modal-close" @click="closeScan">✕</button>
          </div>

          <div v-if="!scanResult" class="scan-body">
            <!-- Scanner page selector -->
            <div class="scanner-page-row">
              <label class="sp-label">Advance to:</label>
              <div class="sp-options">
                <button
                  v-for="opt in scannerPageOptions"
                  :key="opt.page"
                  class="sp-btn"
                  :class="{ active: selectedScannerPage === opt.page }"
                  @click="selectedScannerPage = opt.page"
                >
                  {{ opt.label }}
                </button>
              </div>
            </div>

            <p class="scan-hint">Enter or scan the delivery barcode below</p>
            <div class="scan-input-row">
              <input
                ref="barcodeInput"
                v-model="barcodeValue"
                placeholder="DLV-XXXXXXXXXX"
                class="barcode-input"
                @keyup.enter="submitScan"
                autofocus
              />
              <button
                class="btn-submit-scan"
                @click="submitScan"
                :disabled="scanning"
              >
                <span v-if="scanning" class="mini-spinner"></span>
                <span v-else>Scan</span>
              </button>
            </div>

            <div v-if="scanError" class="scan-error">{{ scanError }}</div>

            <!-- Pre-fill from order context -->
            <div v-if="scanModal.delivery?.barcode" class="prefill-hint">
              <span>Auto-fill: </span>
              <button
                class="prefill-btn"
                @click="barcodeValue = scanModal.delivery.barcode"
              >
                {{ scanModal.delivery.barcode }}
              </button>
            </div>
          </div>

          <!-- Scan Result -->
          <div v-else class="scan-result">
            <div class="result-icon success">✓</div>
            <h3>Delivery Updated!</h3>
            <div class="result-row">
              <span>Delivery ID</span>
              <strong>{{ scanResult.delivery?.delivery_id }}</strong>
            </div>
            <div class="result-row">
              <span>New Status</span>
              <span class="status-badge" :class="scanResult.delivery?.status">{{
                scanResult.delivery?.status_label
              }}</span>
            </div>
            <div class="result-row">
              <span>Order Status</span>
              <strong>{{ scanResult.order_status }}</strong>
            </div>
            <button class="btn-done" @click="closeScan">Done</button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- ── QR Modal ────────────────────────────────────────────────────────── -->
    <teleport to="body">
      <div
        v-if="qrModal.open"
        class="modal-backdrop"
        @click.self="qrModal.open = false"
      >
        <div class="qr-modal">
          <div class="scan-modal-header">
            <h2>Delivery QR Code</h2>
            <button class="modal-close" @click="qrModal.open = false">✕</button>
          </div>
          <div class="qr-body">
            <div class="qr-code-display" ref="qrContainer"></div>
            <div class="qr-label">{{ qrModal.delivery?.delivery_id }}</div>
            <div class="qr-barcode-text">{{ qrModal.delivery?.barcode }}</div>
            <button class="btn-print" @click="printQR">Print QR</button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Toast -->
    <transition name="toast-slide">
      <div v-if="toast.show" class="toast" :class="toast.type">
        {{ toast.message }}
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from "vue";
import { deliveryService } from "../../../../../services/deliveryService";

const orders = ref([]);
const loading = ref(false);
const search = ref("");
const activeTab = ref("all");
const meta = ref({ current_page: 1, last_page: 1 });
const counts = ref({});
const toast = ref({ show: false, type: "success", message: "" });

const scanModal = ref({ open: false, delivery: null });
const barcodeValue = ref("");
const scanning = ref(false);
const scanError = ref("");
const scanResult = ref(null);
const barcodeInput = ref(null);
const selectedScannerPage = ref("to_process");

const qrModal = ref({ open: false, delivery: null });
const qrContainer = ref(null);

// ── Updated to match new status enum ──────────────────────────────────────────
const steps = [
  { value: "pending", label: "Pending" },
  { value: "to_processed", label: "Processing" },
  { value: "to_ship", label: "To Ship" },
  { value: "to_received", label: "In Transit" },
  { value: "completed", label: "Delivered" },
];

const statusTabs = [
  { value: "all", label: "All", color: "chip-gray" },
  { value: "pending", label: "Pending", color: "chip-amber" },
  { value: "to_processed", label: "Processing", color: "chip-blue" },
  { value: "to_ship", label: "To Ship", color: "chip-indigo" },
  { value: "to_received", label: "In Transit", color: "chip-purple" },
  { value: "completed", label: "Completed", color: "chip-green" },
  { value: "returned", label: "Returned", color: "chip-red" },
];

// scanner_page options shown inside the scan modal
const scannerPageOptions = [
  { page: "to_process", label: "Mark Processed" },
  { page: "to_ship", label: "Mark To Ship" },
  { page: "to_receive", label: "Mark Received" },
  { page: "completed", label: "Complete" },
];

function stepIndex(status) {
  return steps.findIndex((s) => s.value === status);
}

let fetchTimer = null;
function debouncedFetch() {
  clearTimeout(fetchTimer);
  fetchTimer = setTimeout(() => fetchOrders(), 400);
}

async function fetchOrders(page = 1) {
  loading.value = true;
  try {
    const params = { page, per_page: 12 };
    if (activeTab.value !== "all") params.status = activeTab.value;
    if (search.value) params.search = search.value;

    const res = await deliveryService.vendorOrders(params);
    const raw = Array.isArray(res) ? res : (res.data ?? []);
    orders.value = raw.filter((d) => d != null);
    meta.value = res.meta ?? meta.value;
    counts.value = res.status_counts ?? {};
  } catch {
    showToast("Failed to load orders", "error");
  } finally {
    loading.value = false;
  }
}

function openScanner(delivery) {
  scanModal.value = { open: true, delivery };
  barcodeValue.value = delivery?.barcode ?? "";
  scanError.value = "";
  scanResult.value = null;

  // Auto-select the next logical scanner page based on delivery's current status
  const nextPageMap = {
    pending: "to_process",
    to_processed: "to_ship",
    to_ship: "to_receive",
    to_received: "completed",
  };
  selectedScannerPage.value = delivery
    ? (nextPageMap[delivery.status] ?? "to_process")
    : "to_process";

  nextTick(() => barcodeInput.value?.focus());
}

function closeScan() {
  scanModal.value.open = false;
  if (scanResult.value) fetchOrders();
}

async function submitScan() {
  if (!barcodeValue.value.trim()) return;
  scanning.value = true;
  scanError.value = "";
  try {
    // selectedScannerPage is always a valid key: to_process | to_ship | to_receive | completed
    const res = await deliveryService.scan(
      barcodeValue.value.trim(),
      selectedScannerPage.value,
    );
    // api.js interceptor returns response.data directly, so res IS the data
    scanResult.value = res?.data ?? res;
    showToast("Delivery status updated!");
  } catch (e) {
    // api.js interceptor rejects with response.data, so e.message is the error string
    scanError.value =
      e?.message ?? e?.error ?? "Scan failed. Check the barcode and try again.";
  } finally {
    scanning.value = false;
  }
}

async function showQR(delivery) {
  qrModal.value = { open: true, delivery };
  await nextTick();
  renderQR(delivery.barcode);
}

function renderQR(value) {
  if (!qrContainer.value) return;
  qrContainer.value.innerHTML = "";
  const img = document.createElement("img");
  img.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(value)}`;
  img.alt = value;
  img.style.cssText = "width:200px;height:200px;border-radius:8px;";
  img.onerror = () => {
    qrContainer.value.innerHTML = `<div style="font-family:monospace;font-size:18px;padding:20px;background:#f3f4f6;border-radius:8px;">${value}</div>`;
  };
  qrContainer.value.appendChild(img);
}

function printQR() {
  const win = window.open("", "_blank");
  win.document.write(`
    <html><body style="text-align:center;font-family:sans-serif;padding:40px">
      <h2>${qrModal.value.delivery?.delivery_id}</h2>
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrModal.value.delivery?.barcode)}" />
      <p style="font-size:14px;color:#555">${qrModal.value.delivery?.barcode}</p>
      <script>window.onload=()=>window.print()<\/script>
    </body></html>
  `);
}

function showToast(msg, type = "success") {
  toast.value = { show: true, type, message: msg };
  setTimeout(() => (toast.value.show = false), 3000);
}

onMounted(fetchOrders);
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.vendor-orders-page {
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

.btn-scan {
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
  transition:
    background 0.15s,
    transform 0.1s;
}
.btn-scan:hover {
  background: #059669;
  transform: translateY(-1px);
}

/* Status chips */
.status-chips {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 20px;
  border: 1.5px solid transparent;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.chip-count {
  background: rgba(0, 0, 0, 0.08);
  border-radius: 10px;
  padding: 1px 7px;
  font-size: 11px;
}
.chip-gray {
  background: #f3f4f6;
  color: #374151;
}
.chip-amber {
  background: #fef3c7;
  color: #92400e;
}
.chip-blue {
  background: #dbeafe;
  color: #1d4ed8;
}
.chip-indigo {
  background: #e0e7ff;
  color: #3730a3;
}
.chip-purple {
  background: #ede9fe;
  color: #6d28d9;
}
.chip-green {
  background: #dcfce7;
  color: #166534;
}
.chip-red {
  background: #fee2e2;
  color: #991b1b;
}
.chip.active {
  border-color: currentColor;
}

/* Search */
.search-row {
  display: flex;
}
.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 9px;
  min-width: 300px;
}
.search-box input {
  border: none;
  background: none;
  outline: none;
  font-size: 13px;
  color: #374151;
  flex: 1;
}
.search-box input::placeholder {
  color: #9ca3af;
}

/* Loading / Empty */
.loading-state {
  display: flex;
  justify-content: center;
  padding: 60px;
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
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 60px;
  color: #9ca3af;
}

/* Orders grid */
.orders-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 16px;
}

.order-card {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 14px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  transition: box-shadow 0.15s;
}
.order-card:hover {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.07);
}

.order-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}
.order-id {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
}
.delivery-id {
  font-size: 11.5px;
  color: #9ca3af;
  margin-top: 2px;
}

/* Status badge — updated to new status names */
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 600;
}
.status-badge.pending {
  background: #fef3c7;
  color: #92400e;
}
.status-badge.to_processed {
  background: #dbeafe;
  color: #1d4ed8;
}
.status-badge.to_ship {
  background: #e0e7ff;
  color: #3730a3;
}
.status-badge.to_received {
  background: #ede9fe;
  color: #6d28d9;
}
.status-badge.completed {
  background: #dcfce7;
  color: #166534;
}
.status-badge.returned {
  background: #fee2e2;
  color: #991b1b;
}
.status-badge.refunded {
  background: #f3f4f6;
  color: #374151;
}

/* Items preview */
.items-preview {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.item-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.item-thumb {
  width: 36px;
  height: 36px;
  border-radius: 7px;
  object-fit: cover;
  flex-shrink: 0;
}
.item-thumb.placeholder {
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  color: #6b7280;
}
.item-info {
  flex: 1;
  min-width: 0;
}
.item-name {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.item-meta {
  font-size: 11.5px;
  color: #9ca3af;
}
.item-sub {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  white-space: nowrap;
}
.more-items {
  font-size: 12px;
  color: #6b7280;
  text-align: center;
  padding: 4px;
}

.order-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.order-total {
  font-size: 13.5px;
  color: #374151;
}
.order-total strong {
  color: #111827;
}

.card-actions {
  display: flex;
  gap: 8px;
}
.btn-qr {
  width: 32px;
  height: 32px;
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
.btn-qr:hover {
  border-color: #a5b4fc;
  color: #4f46e5;
  background: #eef2ff;
}
.btn-scan-order {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: #10b981;
  color: #fff;
  border: none;
  border-radius: 7px;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-scan-order:hover:not(:disabled) {
  background: #059669;
}
.btn-scan-order:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* Progress track — updated for 5 steps */
.progress-track {
  display: flex;
  border-top: 1px solid #f3f4f6;
  padding-top: 12px;
}
.progress-step {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
  position: relative;
}
.progress-step::before {
  content: "";
  position: absolute;
  top: 7px;
  left: 50%;
  right: -50%;
  height: 2px;
  background: #e5e7eb;
  z-index: 0;
}
.progress-step:last-child::before {
  display: none;
}
.progress-step.done::before {
  background: #10b981;
}
.step-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid #e5e7eb;
  background: #fff;
  z-index: 1;
  transition: all 0.2s;
}
.progress-step.done .step-dot {
  background: #10b981;
  border-color: #10b981;
}
.progress-step.current .step-dot {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}
.step-label {
  font-size: 10px;
  color: #9ca3af;
  font-weight: 500;
  text-align: center;
}
.progress-step.done .step-label,
.progress-step.current .step-label {
  color: #10b981;
}

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 8px 0;
}
.page-btn {
  width: 30px;
  height: 30px;
  border-radius: 7px;
  border: 1px solid #e5e7eb;
  background: #fff;
  cursor: pointer;
  font-size: 16px;
}
.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.page-info {
  font-size: 13px;
  color: #6b7280;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.scan-modal,
.qr-modal {
  background: #fff;
  border-radius: 16px;
  width: 440px;
  max-width: 95vw;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  overflow: hidden;
}
.scan-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px;
  border-bottom: 1px solid #f0f2f5;
}
.scan-modal-header h2 {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.modal-close {
  background: none;
  border: none;
  font-size: 18px;
  color: #9ca3af;
  cursor: pointer;
}

/* Scanner page selector */
.scanner-page-row {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.sp-label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.sp-options {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.sp-btn {
  padding: 6px 14px;
  border-radius: 20px;
  border: 1.5px solid #e5e7eb;
  background: #f9fafb;
  font-size: 12.5px;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  transition: all 0.15s;
}
.sp-btn:hover {
  border-color: #10b981;
  color: #059669;
  background: #ecfdf5;
}
.sp-btn.active {
  border-color: #10b981;
  color: #fff;
  background: #10b981;
}

.scan-body {
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.scan-hint {
  font-size: 13.5px;
  color: #6b7280;
  margin: 0;
}
.scan-input-row {
  display: flex;
  gap: 8px;
}
.barcode-input {
  flex: 1;
  padding: 10px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  font-size: 14px;
  font-family: monospace;
  outline: none;
  transition: border 0.15s;
}
.barcode-input:focus {
  border-color: #10b981;
}
.btn-submit-scan {
  padding: 10px 20px;
  background: #10b981;
  color: #fff;
  border: none;
  border-radius: 9px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
}
.btn-submit-scan:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.mini-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}
.scan-error {
  background: #fee2e2;
  color: #991b1b;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
}
.prefill-hint {
  font-size: 12.5px;
  color: #6b7280;
  display: flex;
  align-items: center;
  gap: 8px;
}
.prefill-btn {
  background: #f3f4f6;
  border: none;
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 12px;
  font-family: monospace;
  cursor: pointer;
  color: #374151;
}

/* Scan result */
.scan-result {
  padding: 32px 22px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}
.result-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}
.result-icon.success {
  background: #dcfce7;
  color: #16a34a;
}
.scan-result h3 {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.result-row {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13.5px;
}
.result-row span {
  color: #6b7280;
}
.btn-done {
  width: 100%;
  padding: 11px;
  background: #111827;
  color: #fff;
  border: none;
  border-radius: 9px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 8px;
}

/* QR modal */
.qr-body {
  padding: 28px 22px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}
.qr-code-display {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 200px;
}
.qr-label {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
}
.qr-barcode-text {
  font-family: monospace;
  font-size: 12px;
  color: #6b7280;
  background: #f3f4f6;
  padding: 6px 12px;
  border-radius: 6px;
}
.btn-print {
  padding: 9px 24px;
  background: #4f46e5;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
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
  z-index: 1100;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
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
