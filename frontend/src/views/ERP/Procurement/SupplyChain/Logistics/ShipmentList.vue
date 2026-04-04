<template>
  <div class="ship-page">
    
    <div class="page-header">
      <div>
        <h1 class="page-title">Shipments</h1>
        <p class="page-sub">Track all outbound and inbound deliveries</p>
      </div>
      <button class="btn-primary" @click="showCreate = true">
        <svg viewBox="0 0 20 20" fill="currentColor" width="15">
          <path
            d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
          />
        </svg>
        Create Shipment
      </button>
    </div>

    <!-- Status Filters -->
    <div class="status-filter-row">
      <button
        v-for="tab in statusTabs"
        :key="tab.value"
        class="stab"
        :class="{ active: activeStatus === tab.value }"
        @click="
          activeStatus = tab.value;
          fetchShipments();
        "
      >
        <span class="stab-dot" :class="tab.value"></span>
        {{ tab.label }}
        <span class="stab-count">{{ tab.count }}</span>
      </button>
    </div>

    <div class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Tracking #</th>
            <th>Order</th>
            <th>Carrier</th>
            <th>Status</th>
            <th>Shipped</th>
            <th>Expected</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="loading-cell">
              <div class="spinner"></div>
            </td>
          </tr>
          <tr v-for="s in shipments" :key="s.id" class="data-row">
            <td>
              <router-link
                :to="`/erp/procurement/supply-chain/logistics/${s.id}`"
                class="tracking-link"
              >
                <svg
                  viewBox="0 0 20 20"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  width="12"
                >
                  <rect x="1" y="4" width="10" height="12" rx="1" />
                  <path d="M11 7h5l2 4v4h-7V7z" />
                  <circle cx="4" cy="16" r="2" />
                  <circle cx="16" cy="16" r="2" />
                </svg>
                {{ s.tracking_number }}
              </router-link>
            </td>
            <td>
              <code class="order-ref">{{
                s.purchase_order?.order_number
              }}</code>
            </td>
            <td class="muted">{{ s.carrier }}</td>
            <td>
              <span class="ship-badge" :class="s.status">{{
                formatStatus(s.status)
              }}</span>
            </td>
            <td class="muted">
              {{ s.shipped_date ? formatDate(s.shipped_date) : "—" }}
            </td>
            <td class="muted">—</td>
            <td>
              <div class="action-cell">
                <router-link
                  :to="`/erp/procurement/supply-chain/logistics/${s.id}`"
                  class="action-btn"
                  title="Track"
                >
                  <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="14"
                  >
                    <path d="M10 3a7 7 0 100 14A7 7 0 0010 3z" />
                    <path d="M10 7v4l2 2" />
                  </svg>
                </router-link>
                <button
                  v-if="s.status === 'pending'"
                  class="action-btn ok"
                  @click="ship(s)"
                  title="Mark Shipped"
                >
                  <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="14"
                  >
                    <path d="M4 10h12M10 4l6 6-6 6" />
                  </svg>
                </button>
                <button
                  v-if="['in_transit', 'out_for_delivery'].includes(s.status)"
                  class="action-btn green"
                  @click="receive(s)"
                  title="Mark Received"
                >
                  <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="14"
                  >
                    <path d="M5 10l4 4 6-6" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Shipment Modal -->
    <transition name="modal-fade">
      <div
        v-if="showCreate"
        class="modal-overlay"
        @click.self="showCreate = false"
      >
        <div class="modal">
          <div class="modal-header">
            <h3>Create Shipment</h3>
            <button class="modal-close" @click="showCreate = false">×</button>
          </div>
          <div class="modal-body">
            <div class="field">
              <label>Purchase Order <span class="req">*</span></label>
              <select v-model="newShip.purchase_order_id">
                <option value="" disabled>Select order…</option>
                <option v-for="o in availableOrders" :key="o.id" :value="o.id">
                  {{ o.order_number }}
                </option>
              </select>
            </div>
            <div class="field">
              <label>Carrier <span class="req">*</span></label>
              <input
                v-model="newShip.carrier"
                placeholder="e.g. FedEx, DHL, UPS"
              />
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-cancel" @click="showCreate = false">
              Cancel
            </button>
            <button class="btn-primary sm" @click="createShipment">
              Create
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { shipmentService } from "../../../../../services/shipmentService";
import { orderService } from "../../../../../services/orderService";


const shipments = ref([]);
const availableOrders = ref([]);
const loading = ref(false);
const activeStatus = ref("");
const showCreate = ref(false);
const newShip = ref({ purchase_order_id: "", carrier: "" });

const STATUSES = [
  "pending",
  "in_transit",
  "out_for_delivery",
  "delivered",
  "failed",
  "returned",
];
const statusTabs = ref(
  STATUSES.map((s) => ({ value: s, label: formatStatus(s), count: 0 })),
);
statusTabs.value.unshift({ value: "", label: "All", count: 0 });

function formatStatus(s) {
  return (s || "").replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase());
}

async function fetchShipments() {
  loading.value = true;
  try {
    const params = {};
    if (activeStatus.value) params.status = activeStatus.value;
    const res = await shipmentService.list(params);
    shipments.value = res.data || res;
    // Update counts
    statusTabs.value.forEach((t) => {
      t.count = t.value
        ? shipments.value.filter((s) => s.status === t.value).length
        : shipments.value.length;
    });
  } finally {
    loading.value = false;
  }
}

async function ship(s) {
  await shipmentService.markShipped(s.id);
  s.status = "in_transit";
  s.shipped_date = new Date().toISOString();
}

async function receive(s) {
  await shipmentService.markReceived(s.id);
  s.status = "delivered";
  s.received_date = new Date().toISOString();
}

async function createShipment() {
  await shipmentService.create({ ...newShip.value, items: [] });
  showCreate.value = false;
  newShip.value = { purchase_order_id: "", carrier: "" };
  fetchShipments();
}

function formatDate(d) {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

onMounted(async () => {
  fetchShipments();
  const res = await orderService.list({ status: "processing", per_page: 100 });
  availableOrders.value = res.data || res;
});
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.ship-page {
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
}
.btn-primary.sm {
  padding: 8px 16px;
  font-size: 13px;
}

.status-filter-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.stab {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 7px 14px;
  border-radius: 8px;
  border: 1.5px solid #e5e7eb;
  background: #fff;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.15s;
}
.stab:hover {
  border-color: #d1d5db;
  color: #374151;
}
.stab.active {
  border-color: #10b981;
  background: #ecfdf5;
  color: #059669;
}
.stab-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}
.stab-dot.pending {
  background: #f59e0b;
}
.stab-dot.in_transit {
  background: #3b82f6;
}
.stab-dot.out_for_delivery {
  background: #8b5cf6;
}
.stab-dot.delivered {
  background: #10b981;
}
.stab-dot.failed {
  background: #ef4444;
}
.stab-dot.returned {
  background: #6b7280;
}
.stab-count {
  background: #f3f4f6;
  border-radius: 10px;
  padding: 1px 7px;
  font-size: 11px;
}
.stab.active .stab-count {
  background: #d1fae5;
}

.card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e8ecf0;
  overflow: hidden;
}
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
  padding: 12px 16px;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}
.data-row:last-child td {
  border-bottom: none;
}
.data-row:hover td {
  background: #fafafa;
}
.loading-cell {
  text-align: center;
  padding: 48px 16px !important;
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

.tracking-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-family: monospace;
  font-size: 12.5px;
  font-weight: 700;
  color: #374151;
  text-decoration: none;
}
.tracking-link:hover {
  color: #10b981;
}
.order-ref {
  font-family: monospace;
  font-size: 12px;
  background: #f3f4f6;
  padding: 2px 7px;
  border-radius: 5px;
}
.muted {
  color: #6b7280;
}

.ship-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}
.ship-badge.pending {
  background: #fef3c7;
  color: #92400e;
}
.ship-badge.in_transit {
  background: #dbeafe;
  color: #1d4ed8;
}
.ship-badge.out_for_delivery {
  background: #ede9fe;
  color: #6d28d9;
}
.ship-badge.delivered {
  background: #dcfce7;
  color: #16a34a;
}
.ship-badge.failed {
  background: #fee2e2;
  color: #dc2626;
}
.ship-badge.returned {
  background: #f3f4f6;
  color: #6b7280;
}

.action-cell {
  display: flex;
  gap: 4px;
}
.action-btn {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  border: 1px solid #e5e7eb;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #6b7280;
  text-decoration: none;
}
.action-btn:hover {
  background: #f9fafb;
  color: #374151;
}
.action-btn.ok {
  border-color: #a7f3d0;
  color: #059669;
}
.action-btn.green {
  border-color: #10b981;
  background: #ecfdf5;
  color: #059669;
}

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
  width: 420px;
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
  gap: 14px;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid #f0f2f5;
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
  background: #fff;
}
.field input:focus,
.field select:focus {
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
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
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
