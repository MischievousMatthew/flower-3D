<template>
  <div class="detail-page" v-if="order">
    
    <div class="page-header">
      <span
        class="back-link"
        style="cursor: pointer"
        @click="router.push({ name: 'OrderList' })"
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
        Back to Orders
      </span>
      <div class="header-row">
        <div>
          <h1 class="page-title">{{ order.order_number }}</h1>
          <p class="page-sub">Created {{ formatDate(order.created_at) }}</p>
        </div>
        <div class="header-actions">
          <span class="status-badge big" :class="order.status">{{
            order.status
          }}</span>

          <button
            v-if="order.status === 'received'"
            class="btn-receive"
            @click="goToReceiveBatch"
          >
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="14"
            >
              <path d="M3 10h14M10 3l7 7-7 7" />
            </svg>
            Receive to Warehouse
          </button>

          <button
            v-if="nextStatuses.length"
            class="btn-primary"
            @click="showStatusModal = true"
          >
            Update Status
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
        </div>
      </div>
    </div>

    <!-- Status Timeline -->
    <div class="timeline-card">
      <div
        v-for="(step, i) in timeline"
        :key="step.status"
        class="tl-step"
        :class="{
          done: isStepDone(step.status),
          current: order.status === step.status,
        }"
      >
        <div class="tl-dot">
          <svg
            v-if="isStepDone(step.status)"
            viewBox="0 0 20 20"
            fill="currentColor"
            width="10"
          >
            <path
              d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z"
            />
          </svg>
        </div>
        <div
          v-if="i < timeline.length - 1"
          class="tl-line"
          :class="{ done: isStepDone(timeline[i + 1]?.status) }"
        ></div>
        <span class="tl-label">{{ step.label }}</span>
      </div>
    </div>

    <div class="detail-grid">
      <!-- Left Column -->
      <div class="left-col">
        <div class="card">
          <div class="card-header">
            <h3>Order Items</h3>
            <span class="item-count">{{ order.items?.length ?? 0 }} items</span>
          </div>
          <table class="items-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in order.items" :key="item.id">
                <td>
                  <span class="product-name">{{ item.product_name }}</span>
                </td>
                <td class="center">{{ item.quantity }}</td>
                <td class="muted">₱{{ formatAmount(item.price) }}</td>
                <td class="amount">
                  ₱{{
                    formatAmount(item.subtotal ?? item.quantity * item.price)
                  }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="total-lbl">Order Total</td>
                <td class="total-val">
                  ₱{{ formatAmount(order.total_amount) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div v-if="order.shipment" class="card">
          <div class="card-header"><h3>Shipment</h3></div>
          <div class="shipment-info">
            <div class="si-row">
              <span>Tracking #</span
              ><code>{{ order.shipment.tracking_number }}</code>
            </div>
            <div class="si-row">
              <span>Carrier</span><span>{{ order.shipment.carrier }}</span>
            </div>
            <div class="si-row">
              <span>Status</span>
              <span class="status-badge sm" :class="order.shipment.status">{{
                order.shipment.status
              }}</span>
            </div>
            <div class="si-row" v-if="order.shipment.shipped_date">
              <span>Shipped</span
              ><span>{{ formatDate(order.shipment.shipped_date) }}</span>
            </div>
            <div class="si-row" v-if="order.shipment.received_date">
              <span>Received</span
              ><span>{{ formatDate(order.shipment.received_date) }}</span>
            </div>
            <router-link
              :to="`/erp/procurement/supply-chain/logistics/${order.shipment.id}`"
              class="view-shipment-btn"
              >View Shipment Details →</router-link
            >
          </div>
        </div>
      </div>

      <!-- Right Column -->
      <div class="right-col">
        <div class="card info-card">
          <div class="card-header"><h3>Supplier</h3></div>
          <div class="supplier-block">
            <div
              class="sup-avatar"
              :style="{
                background: order.supplier?.logo_url
                  ? 'transparent'
                  : '#ecfdf5',
              }"
            >
              <img
                v-if="order.supplier?.logo_url"
                :src="order.supplier.logo_url"
                style="
                  width: 100%;
                  height: 100%;
                  object-fit: cover;
                  border-radius: 10px;
                "
              />
              <span
                v-else
                style="font-size: 18px; font-weight: 700; color: #059669"
                >{{ order.supplier?.company_name?.[0] }}</span
              >
            </div>
            <div class="sup-details">
              <div class="sup-name">{{ order.supplier?.company_name }}</div>
              <div class="sup-contact">
                {{ order.supplier?.contact_person }}
              </div>
              <a :href="`mailto:${order.supplier?.email}`" class="sup-email">{{
                order.supplier?.email
              }}</a>
              <div class="sup-phone">{{ order.supplier?.phone }}</div>
            </div>
          </div>
        </div>

        <div class="card meta-card">
          <div class="card-header"><h3>Details</h3></div>
          <div class="meta-rows">
            <div class="meta-row">
              <span>Order #</span><code>{{ order.order_number }}</code>
            </div>
            <div class="meta-row">
              <span>Status</span
              ><span class="status-badge sm" :class="order.status">{{
                order.status
              }}</span>
            </div>
            <div class="meta-row">
              <span>Created</span
              ><span>{{ formatDate(order.created_at) }}</span>
            </div>
            <div class="meta-row">
              <span>Items</span><span>{{ order.items?.length ?? 0 }}</span>
            </div>
            <div class="meta-row">
              <span>Total</span
              ><strong>₱{{ formatAmount(order.total_amount) }}</strong>
            </div>
          </div>
        </div>

        <div v-if="order.status === 'completed'" class="card batch-info-card">
          <div class="card-header"><h3>Warehouse Stock</h3></div>
          <div class="batch-info-body">
            <svg
              viewBox="0 0 20 20"
              fill="currentColor"
              width="32"
              style="color: #10b981"
            >
              <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                clip-rule="evenodd"
              />
            </svg>
            <p class="batch-info-text">
              Stock has been automatically added to the warehouse.<br />
              Lot: <code>{{ order.order_number }}</code>
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Status Modal -->
    <transition name="modal-fade">
      <div
        v-if="showStatusModal"
        class="modal-overlay"
        @click.self="showStatusModal = false"
      >
        <div class="modal">
          <div class="modal-header">
            <h3>Update Order Status</h3>
            <button class="modal-close" @click="showStatusModal = false">
              ×
            </button>
          </div>
          <div class="modal-body">
            <div class="status-options">
              <label
                v-for="s in nextStatuses"
                :key="s"
                class="status-opt"
                :class="{ chosen: nextStatus === s }"
              >
                <input type="radio" v-model="nextStatus" :value="s" hidden />
                <span class="opt-dot" :class="s"></span>
                {{ s }}
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-cancel" @click="showStatusModal = false">
              Cancel
            </button>
            <button
              class="btn-primary sm"
              :disabled="!nextStatus || updating"
              @click="applyStatus"
            >
              {{ updating ? "Updating…" : "Update" }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>

  <div v-else class="loading-state"><div class="spinner"></div></div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { orderService } from "../../../../../services/orderService";

const props = defineProps({
  id: {
    type: [String, Number],
    default: null,
  },
});

const router = useRouter();
const route = useRoute();
const order = ref(null);
const showStatusModal = ref(false);
const nextStatus = ref("");
const updating = ref(false);

const TRANSITIONS = {
  pending: ["processing", "completed"],
  processing: ["shipped"],
  shipped: ["received"],
  received: ["completed"],
  completed: [],
};

const TIMELINE = [
  { status: "pending", label: "Pending" },
  { status: "processing", label: "Processing" },
  { status: "shipped", label: "Shipped" },
  { status: "received", label: "Received" },
  { status: "completed", label: "Completed" },
];

const timeline = TIMELINE;

const ORDER_IDX = computed(() =>
  TIMELINE.findIndex((t) => t.status === order.value?.status),
);

const nextStatuses = computed(() => TRANSITIONS[order.value?.status] || []);

function isStepDone(status) {
  const idx = TIMELINE.findIndex((t) => t.status === status);
  return idx <= ORDER_IDX.value;
}

// ── Navigate to ReceiveBatch pre-filled with this order's data ────────────────
function goToReceiveBatch() {
  router.push({
    name: "BatchesToReceive",
    state: {
      prefill: JSON.stringify({
        order_id: order.value.id,
        order_number: order.value.order_number,
        supplier: order.value.supplier,
        // FIXED: map items to include product_id and all fields ReceiveBatch
        // needs so it can set the product directly without a fragile name-search.
        items: (order.value.items ?? []).map((item) => ({
          id: item.id,
          product_id: item.product_id, // ← key field
          product_name: item.product_name,
          sku: item.sku ?? "",
          flower_type: item.flower_type ?? null,
          color: item.color ?? null,
          image_url: item.image_url ?? null,
          requires_refrigeration: item.requires_refrigeration ?? false,
          quantity_in_stock: item.quantity_in_stock ?? 0,
          quantity: item.quantity,
          price: item.price,
        })),
      }),
    },
  });
}

async function applyStatus() {
  if (!nextStatus.value || updating.value) return;
  updating.value = true;
  try {
    await orderService.updateStatus(order.value.id, nextStatus.value);
    order.value.status = nextStatus.value;
    showStatusModal.value = false;
    nextStatus.value = "";
  } catch (e) {
    alert(e?.response?.data?.message ?? "Failed to update status");
  } finally {
    updating.value = false;
  }
}

function formatAmount(n) {
  return Number(n || 0).toLocaleString("en-US", { minimumFractionDigits: 2 });
}

function formatDate(d) {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

onMounted(() => {
  const orderId = props.id ?? route.params.id;

  if (!orderId) {
    router.replace({ name: "OrderList" });
    return;
  }

  orderService
    .find(orderId)
    .then((res) => {
      order.value = res.data?.data ?? res.data ?? null;
      if (!order.value) {
        router.replace({ name: "OrderList" });
      }
    })
    .catch(() => {
      router.replace({ name: "OrderList" });
    });
});
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.detail-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
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

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #6b7280;
  text-decoration: none;
  font-size: 13px;
  margin-bottom: 6px;
}
.header-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}
.page-title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
  font-family: monospace;
}
.page-sub {
  font-size: 13px;
  color: #6b7280;
  margin: 2px 0 0;
}
.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
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
  font-family: "Poppins", sans-serif;
}
.btn-primary.sm {
  padding: 8px 16px;
  font-size: 13px;
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-receive {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 18px;
  background: #ecfdf5;
  color: #059669;
  border: 1.5px solid #6ee7b7;
  border-radius: 9px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  font-family: "Poppins", sans-serif;
}
.btn-receive:hover {
  background: #d1fae5;
  border-color: #34d399;
}

.timeline-card {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 14px;
  padding: 24px 32px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  overflow-x: auto;
}
.tl-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  position: relative;
  flex: 1;
}
.tl-step:not(:last-child)::after {
  content: "";
  position: absolute;
  top: 14px;
  left: 50%;
  width: 100%;
  height: 2px;
  background: #e5e7eb;
  z-index: 0;
}
.tl-step.done:not(:last-child)::after {
  background: #10b981;
}
.tl-dot {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid #e5e7eb;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}
.tl-step.done .tl-dot {
  border-color: #10b981;
  background: #10b981;
}
.tl-step.current .tl-dot {
  border-color: #10b981;
}
.tl-line {
  display: none;
}
.tl-label {
  font-size: 11.5px;
  font-weight: 500;
  color: #9ca3af;
  white-space: nowrap;
  text-align: center;
}
.tl-step.done .tl-label {
  color: #10b981;
}
.tl-step.current .tl-label {
  color: #111827;
  font-weight: 700;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 20px;
  align-items: start;
}
.left-col,
.right-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e8ecf0;
  overflow: hidden;
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #f0f2f5;
}
.card-header h3 {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.item-count {
  font-size: 12px;
  color: #6b7280;
  background: #f3f4f6;
  padding: 2px 8px;
  border-radius: 10px;
}

.items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.items-table th {
  padding: 10px 16px;
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: #f9fafb;
}
.items-table td {
  padding: 11px 16px;
  border-top: 1px solid #f3f4f6;
}
.items-table tfoot td {
  border-top: 2px solid #e5e7eb;
}
.product-name {
  font-weight: 500;
  color: #111827;
}
.center {
  text-align: center;
  color: #374151;
}
.muted {
  color: #6b7280;
}
.amount {
  font-weight: 600;
  color: #374151;
  font-variant-numeric: tabular-nums;
}
.total-lbl {
  text-align: right;
  font-weight: 600;
  color: #374151;
}
.total-val {
  font-weight: 800;
  font-size: 16px;
  color: #111827;
  font-variant-numeric: tabular-nums;
}

.shipment-info {
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.si-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13.5px;
}
.si-row span:first-child {
  color: #6b7280;
}
.si-row code {
  font-family: monospace;
  font-size: 12.5px;
  background: #f3f4f6;
  padding: 2px 7px;
  border-radius: 5px;
  color: #374151;
}
.view-shipment-btn {
  display: inline-flex;
  margin-top: 4px;
  color: #10b981;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
}

.supplier-block {
  padding: 16px 20px;
  display: flex;
  gap: 14px;
  align-items: flex-start;
}
.sup-avatar {
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
  flex-shrink: 0;
  overflow: hidden;
}
.sup-details {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.sup-name {
  font-weight: 700;
  color: #111827;
  font-size: 14px;
}
.sup-contact {
  color: #374151;
  font-size: 13px;
}
.sup-email {
  color: #10b981;
  font-size: 12px;
  text-decoration: none;
}
.sup-phone {
  color: #6b7280;
  font-size: 12px;
}

.meta-rows {
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.meta-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
}
.meta-row span:first-child {
  color: #6b7280;
}
.meta-row code {
  font-family: monospace;
  font-size: 12px;
  background: #f3f4f6;
  padding: 2px 6px;
  border-radius: 4px;
}
.meta-row strong {
  font-size: 15px;
  color: #111827;
}

.batch-info-body {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 20px;
}
.batch-info-text {
  font-size: 13px;
  color: #374151;
  line-height: 1.6;
  margin: 0;
}
.batch-info-text code {
  font-family: monospace;
  background: #f3f4f6;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 12px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}
.status-badge.big {
  font-size: 13px;
  padding: 6px 14px;
}
.status-badge.sm {
  font-size: 11px;
  padding: 3px 8px;
}
.status-badge.pending {
  background: #fef3c7;
  color: #92400e;
}
.status-badge.processing {
  background: #dbeafe;
  color: #1d4ed8;
}
.status-badge.shipped {
  background: #ede9fe;
  color: #6d28d9;
}
.status-badge.received {
  background: #e0f2fe;
  color: #0369a1;
}
.status-badge.completed {
  background: #dcfce7;
  color: #16a34a;
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
  width: 360px;
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
  font-size: 15px;
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
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid #f0f2f5;
}
.status-options {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.status-opt {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  cursor: pointer;
  font-size: 13.5px;
  font-weight: 500;
  text-transform: capitalize;
}
.status-opt.chosen {
  border-color: #10b981;
  background: #ecfdf5;
}
.opt-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.opt-dot.pending {
  background: #f59e0b;
}
.opt-dot.processing {
  background: #3b82f6;
}
.opt-dot.shipped {
  background: #8b5cf6;
}
.opt-dot.received {
  background: #06b6d4;
}
.opt-dot.completed {
  background: #10b981;
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
  font-family: "Poppins", sans-serif;
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
