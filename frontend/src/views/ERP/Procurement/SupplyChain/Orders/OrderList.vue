<template>
  <div class="order-page">
    
    <div class="page-header">
      <div>
        <h1 class="page-title">Purchase Orders</h1>
        <p class="page-sub">Manage and track all procurement orders</p>
      </div>
      <button class="btn-primary" @click="openFundingModal">
        <svg viewBox="0 0 20 20" fill="currentColor" width="15">
          <path
            d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
          />
        </svg>
        Order from Funding
      </button>
    </div>

    <!-- Status Pipeline -->
    <div class="pipeline">
      <div
        v-for="step in pipeline"
        :key="step.status"
        class="pipe-step"
        :class="{ active: statusFilter === step.status }"
        @click="setFilter(step.status)"
      >
        <div
          class="pipe-count"
          :style="{ color: step.color, background: step.bg }"
        >
          {{ step.count }}
        </div>
        <div class="pipe-label">{{ step.label }}</div>
      </div>
    </div>

    <div class="card">
      <div class="card-toolbar">
        <div class="toolbar-left">
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
              v-model="searchQuery"
              placeholder="Search order number or supplier…"
              @input="debouncedSearch"
            />
          </div>
          <select
            v-model="supplierFilter"
            class="select-filter"
            @change="fetchOrders"
          >
            <option value="">All Suppliers</option>
            <option v-for="s in suppliers" :key="s.id" :value="s.id">
              {{ s.company_name }}
            </option>
          </select>
        </div>
      </div>

      <table class="data-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Supplier</th>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="loading-cell">
              <div class="spinner"></div>
            </td>
          </tr>
          <tr v-for="order in orders" :key="order.id" class="data-row">
            <td>
              <span
                class="order-link"
                style="cursor: pointer"
                @click="goToDetail(order)"
              >
                {{ order.order_number }}
              </span>
            </td>
            <td>
              <div class="supplier-cell">
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
                      border-radius: 7px;
                    "
                  />
                  <span
                    v-else
                    style="font-weight: 700; color: #059669; font-size: 12px"
                  >
                    {{ order.supplier?.company_name?.[0] }}
                  </span>
                </div>
                <span>{{ order.supplier?.company_name }}</span>
              </div>
            </td>
            <td class="muted">{{ order.items?.length ?? 0 }} items</td>
            <td class="amount-cell">₱{{ formatAmount(order.total_amount) }}</td>
            <td>
              <div class="status-step">
                <span class="status-dot-sm" :class="order.status"></span>
                <span class="status-lbl" :class="order.status">{{
                  order.status
                }}</span>
              </div>
            </td>
            <td class="muted">{{ formatDate(order.created_at) }}</td>
            <td>
              <div class="action-cell">
                <button class="action-btn" @click="goToDetail(order)">
                  <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="14"
                  >
                    <path d="M2 10s3-7 8-7 8 7 8 7-3 7-8 7-8-7-8-7z" />
                    <circle cx="10" cy="10" r="3" />
                  </svg>
                </button>
                <button class="action-btn" @click="openStatusModal(order)">
                  <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="14"
                  >
                    <path d="M3 10h14M10 3l7 7-7 7" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="pagination" v-if="meta.last_page > 1">
        <button
          class="page-btn"
          :disabled="meta.current_page === 1"
          @click="goPage(meta.current_page - 1)"
        >
          ‹
        </button>
        <span class="page-info"
          >{{ meta.current_page }} / {{ meta.last_page }}</span
        >
        <button
          class="page-btn"
          :disabled="meta.current_page === meta.last_page"
          @click="goPage(meta.current_page + 1)"
        >
          ›
        </button>
      </div>
    </div>

    <!-- Status Update Modal -->
    <transition name="modal-fade">
      <div
        v-if="statusModal.order"
        class="modal-overlay"
        @click.self="statusModal.order = null"
      >
        <div class="modal">
          <div class="modal-header">
            <h3>Update Status — {{ statusModal.order?.order_number }}</h3>
            <button class="modal-close" @click="statusModal.order = null">
              ×
            </button>
          </div>
          <div class="modal-body">
            <div class="status-options">
              <label
                v-for="s in allowedTransitions(statusModal.order?.status)"
                :key="s"
                class="status-opt"
                :class="{ chosen: statusModal.next === s }"
              >
                <input
                  type="radio"
                  v-model="statusModal.next"
                  :value="s"
                  hidden
                />
                <span class="opt-dot" :class="s"></span>
                {{ s }}
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-cancel" @click="statusModal.order = null">
              Cancel
            </button>
            <button
              class="btn-primary"
              :disabled="!statusModal.next"
              @click="applyStatus"
            >
              Update
            </button>
          </div>
        </div>
      </div>
    </transition>
    <!-- Funding → Order Modal -->
    <transition name="modal-fade">
      <div
        v-if="fundingModal.open"
        class="modal-overlay"
        @click.self="fundingModal.open = false"
      >
        <div class="modal modal-wide">
          <div class="modal-header">
            <h3>Create Order from Approved Funding</h3>
            <button class="modal-close" @click="fundingModal.open = false">
              ×
            </button>
          </div>

          <div class="modal-body" v-if="fundingModal.loading">
            <div class="modal-loading"><div class="spinner"></div></div>
          </div>

          <div class="modal-body" v-else>
            <!-- Step 1: Pick funding request -->
            <div class="modal-section-label">
              1. Select Approved Funding Request
            </div>
            <div class="funding-list">
              <div
                v-for="fr in fundingModal.requests"
                :key="fr.id"
                class="funding-card"
                :class="{ chosen: fundingModal.selected?.id === fr.id }"
                @click="
                  fundingModal.selected = fr;
                  fundingModal.supplier_id = '';
                "
              >
                <div class="fc-top">
                  <span class="fc-id">{{ fr.finance_request_id }}</span>
                  <span class="fc-badge approved">Approved</span>
                </div>
                <div class="fc-product">{{ fr.product_name }}</div>
                <div class="fc-meta">
                  <span
                    >Qty:
                    <strong>{{
                      fr.recommended_qty ?? fr.requested_qty
                    }}</strong>
                    {{ fr.uom }}</span
                  >
                  <span
                    >₱{{ Number(fr.estimated_unit_cost).toLocaleString() }} /
                    unit</span
                  >
                  <span
                    >Total:
                    <strong
                      >₱{{
                        Number(
                          (fr.recommended_qty ?? fr.requested_qty) *
                            fr.estimated_unit_cost,
                        ).toLocaleString()
                      }}</strong
                    ></span
                  >
                </div>
              </div>
            </div>

            <!-- Step 2: Pick supplier -->
            <template v-if="fundingModal.selected">
              <div class="modal-section-label" style="margin-top: 20px">
                2. Select Supplier
              </div>
              <div class="supplier-options">
                <label
                  v-for="s in suppliers"
                  :key="s.id"
                  class="supplier-opt"
                  :class="{ chosen: fundingModal.supplier_id === s.id }"
                >
                  <input
                    type="radio"
                    v-model="fundingModal.supplier_id"
                    :value="s.id"
                    hidden
                  />
                  <div
                    class="sopt-avatar"
                    :style="{
                      background: s.logo_url ? 'transparent' : '#ecfdf5',
                    }"
                  >
                    <img
                      v-if="s.logo_url"
                      :src="s.logo_url"
                      style="
                        width: 100%;
                        height: 100%;
                        object-fit: cover;
                        border-radius: 8px;
                      "
                    />
                    <span v-else style="font-weight: 700; color: #059669">{{
                      s.company_name?.[0]
                    }}</span>
                  </div>
                  <div>
                    <div class="sopt-name">{{ s.company_name }}</div>
                    <div class="sopt-email">{{ s.email }}</div>
                  </div>
                  <div
                    class="sopt-check"
                    v-if="fundingModal.supplier_id === s.id"
                  >
                    ✓
                  </div>
                </label>
              </div>

              <!-- Order preview -->
              <div class="order-preview" v-if="fundingModal.supplier_id">
                <div class="preview-label">Order Preview</div>
                <div class="preview-row">
                  <span>Product</span
                  ><strong>{{ fundingModal.selected.product_name }}</strong>
                </div>
                <div class="preview-row">
                  <span>Quantity</span
                  ><strong
                    >{{
                      fundingModal.selected.recommended_qty ??
                      fundingModal.selected.requested_qty
                    }}
                    {{ fundingModal.selected.uom }}</strong
                  >
                </div>
                <div class="preview-row">
                  <span>Unit Price</span
                  ><strong
                    >₱{{
                      Number(
                        fundingModal.selected.estimated_unit_cost,
                      ).toLocaleString()
                    }}</strong
                  >
                </div>
                <div class="preview-row total">
                  <span>Total</span
                  ><strong
                    >₱{{
                      Number(
                        (fundingModal.selected.recommended_qty ??
                          fundingModal.selected.requested_qty) *
                          fundingModal.selected.estimated_unit_cost,
                      ).toLocaleString()
                    }}</strong
                  >
                </div>
                <div class="preview-row">
                  <span>Supplier</span
                  ><strong>{{
                    suppliers.find((s) => s.id === fundingModal.supplier_id)
                      ?.company_name
                  }}</strong>
                </div>
              </div>
            </template>
          </div>

          <div class="modal-footer">
            <button class="btn-cancel" @click="fundingModal.open = false">
              Cancel
            </button>
            <button
              class="btn-primary"
              :disabled="
                !fundingModal.selected ||
                !fundingModal.supplier_id ||
                fundingModal.submitting
              "
              @click="createOrderFromFunding"
            >
              {{ fundingModal.submitting ? "Creating…" : "Create Order" }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import { orderService } from "../../../../../services/orderService";
import { supplierService } from "../../../../../services/supplierService";

import { useRouter } from "vue-router";
const router = useRouter();

function goToDetail(order) {
  router.push({
    name: "OrderDetail",
    state: { order: JSON.stringify(order) },
  });
}

const orders = ref([]);
const suppliers = ref([]);
const loading = ref(false);
const searchQuery = ref("");
const statusFilter = ref("");
const supplierFilter = ref("");
const meta = ref({ current_page: 1, last_page: 1 });
const statusModal = reactive({ order: null, next: "" });

// — Funding-to-Order modal state —
const fundingModal = reactive({
  open: false,
  loading: false,
  submitting: false,
  requests: [], // approved funding requests
  selected: null, // chosen FundingRequest object
  supplier_id: "", // chosen supplier id
});

const TRANSITIONS = {
  pending: ["processing", "completed"],
  processing: ["shipped"],
  shipped: ["received"],
  received: ["completed"],
  completed: [],
};

const COLORS = {
  pending: { color: "#f59e0b", bg: "#fffbeb" },
  processing: { color: "#3b82f6", bg: "#eff6ff" },
  shipped: { color: "#8b5cf6", bg: "#f5f3ff" },
  received: { color: "#06b6d4", bg: "#ecfeff" },
  completed: { color: "#10b981", bg: "#ecfdf5" },
};

const pipeline = ref([
  { status: "pending", label: "Pending", count: 0, ...COLORS.pending },
  { status: "processing", label: "Processing", count: 0, ...COLORS.processing },
  { status: "shipped", label: "Shipped", count: 0, ...COLORS.shipped },
  { status: "received", label: "Received", count: 0, ...COLORS.received },
  { status: "completed", label: "Completed", count: 0, ...COLORS.completed },
]);

let searchTimeout = null;
const debouncedSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(fetchOrders, 400);
};

async function fetchOrders(page = 1) {
  loading.value = true;
  try {
    const params = { page, per_page: 15 };
    if (statusFilter.value) params.status = statusFilter.value;
    if (supplierFilter.value) params.supplier_id = supplierFilter.value;
    if (searchQuery.value) params.search = searchQuery.value;

    // Fetch filtered list + per-status counts in parallel
    const [res, ...countResults] = await Promise.all([
      orderService.list(params),
      orderService.list({ status: "pending", per_page: 1 }),
      orderService.list({ status: "processing", per_page: 1 }),
      orderService.list({ status: "shipped", per_page: 1 }),
      orderService.list({ status: "received", per_page: 1 }),
      orderService.list({ status: "completed", per_page: 1 }),
    ]);

    orders.value = res.data?.data ?? res.data ?? res ?? [];
    meta.value = res.data?.meta ?? res.meta ?? meta.value;

    const statuses = [
      "pending",
      "processing",
      "shipped",
      "received",
      "completed",
    ];
    statuses.forEach((status, i) => {
      const p = pipeline.value.find((p) => p.status === status);
      if (p)
        p.count =
          countResults[i]?.data?.meta?.total ??
          countResults[i]?.data?.total ??
          0;
    });
  } catch {
    showToast("Failed to load orders", "error");
  } finally {
    loading.value = false;
  }
}

function setFilter(status) {
  statusFilter.value = statusFilter.value === status ? "" : status;
  fetchOrders();
}

function openStatusModal(order) {
  statusModal.order = order;
  statusModal.next = "";
}

function allowedTransitions(status) {
  return TRANSITIONS[status] || [];
}

async function applyStatus() {
  if (!statusModal.next) return;
  try {
    await orderService.updateStatus(statusModal.order.id, statusModal.next);
    statusModal.order.status = statusModal.next;
    statusModal.order = null;
    showToast("Status updated");
  } catch {
    showToast("Failed to update status", "error");
  }
}

// ─── Funding → Order flow ────────────────────────────────────────────────────

async function openFundingModal() {
  fundingModal.open = true;
  fundingModal.selected = null;
  fundingModal.supplier_id = "";
  fundingModal.loading = true;
  try {
    const res = await orderService.approvedFundingRequests();
    const raw = res?.data?.data ?? res?.data ?? res ?? [];
    fundingModal.requests = Array.isArray(raw) ? raw : [];
    if (!fundingModal.requests.length) {
      showToast("No approved funding requests without orders", "warn");
      fundingModal.open = false;
    }
  } catch {
    showToast("Failed to load funding requests", "error");
    fundingModal.open = false;
  } finally {
    fundingModal.loading = false;
  }
}

async function createOrderFromFunding() {
  if (!fundingModal.selected || !fundingModal.supplier_id) return;
  fundingModal.submitting = true;
  try {
    await orderService.createFromFunding(
      fundingModal.selected.id,
      fundingModal.supplier_id,
    );
    showToast(
      `Order created from ${fundingModal.selected.finance_request_id}!`,
    );
    fundingModal.open = false;
    fetchOrders();
  } catch (e) {
    showToast(e?.response?.data?.message || "Failed to create order", "error");
  } finally {
    fundingModal.submitting = false;
  }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function goPage(p) {
  fetchOrders(p);
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

function showToast(msg, type = "success") {
  const map = {
    success: toast.success,
    error: toast.error,
    warn: toast.warning,
  };
  (map[type] ?? toast.success)(msg, {
    autoClose: 3000,
    position: toast.POSITION.TOP_RIGHT,
  });
}

onMounted(async () => {
  fetchOrders();
  try {
    const res = await supplierService.list({ status: "active", per_page: 100 });
    const raw = res?.data?.data ?? res?.data ?? res ?? [];
    suppliers.value = Array.isArray(raw) ? raw : [];
  } catch {}
});
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.order-page {
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
  text-decoration: none;
}
.btn-primary:hover {
  background: #059669;
}
.btn-primary:disabled {
  opacity: 0.6;
}

.pipeline {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 10px;
}
.pipe-step {
  background: #fff;
  border: 1.5px solid #e8ecf0;
  border-radius: 12px;
  padding: 14px 16px;
  cursor: pointer;
  transition: all 0.15s;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.pipe-step:hover {
  border-color: #d1d5db;
}
.pipe-step.active {
  border-color: #10b981;
}
.pipe-count {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.pipe-label {
  font-size: 12.5px;
  font-weight: 500;
  color: #374151;
  text-transform: capitalize;
}

.card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e8ecf0;
  overflow: hidden;
}
.card-toolbar {
  display: flex;
  justify-content: space-between;
  padding: 14px 20px;
  border-bottom: 1px solid #f0f2f5;
}
.toolbar-left {
  display: flex;
  gap: 10px;
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
  width: 220px;
}
.search-box svg {
  color: #9ca3af;
}
.select-filter {
  padding: 7px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  color: #374151;
  background: #fff;
  outline: none;
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

.order-link {
  font-weight: 700;
  color: #111827;
  text-decoration: none;
  font-family: monospace;
}
.order-link:hover {
  color: #10b981;
}
.supplier-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}
.sup-avatar {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  background: #ecfdf5;
  color: #059669;
  border: 2px solid #b0b1b1;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.muted {
  color: #6b7280;
}
.amount-cell {
  font-weight: 700;
  color: #111827;
  font-variant-numeric: tabular-nums;
}

.status-step {
  display: flex;
  align-items: center;
  gap: 6px;
}
.status-dot-sm {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-dot-sm.pending {
  background: #f59e0b;
}
.status-dot-sm.processing {
  background: #3b82f6;
}
.status-dot-sm.shipped {
  background: #8b5cf6;
}
.status-dot-sm.received {
  background: #06b6d4;
}
.status-dot-sm.completed {
  background: #10b981;
}
.status-lbl {
  font-size: 12.5px;
  font-weight: 500;
  text-transform: capitalize;
}
.status-lbl.pending {
  color: #f59e0b;
}
.status-lbl.processing {
  color: #3b82f6;
}
.status-lbl.shipped {
  color: #8b5cf6;
}
.status-lbl.received {
  color: #06b6d4;
}
.status-lbl.completed {
  color: #10b981;
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
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 12px 20px;
  border-top: 1px solid #f0f2f5;
}
.page-btn {
  width: 30px;
  height: 30px;
  border-radius: 7px;
  border: 1px solid #e5e7eb;
  background: #fff;
  cursor: pointer;
  font-size: 16px;
  color: #374151;
  display: flex;
  align-items: center;
  justify-content: center;
}
.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.page-info {
  font-size: 13px;
  color: #6b7280;
}

/* Status modal */
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
  width: 380px;
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
  color: #374151;
  text-transform: capitalize;
  transition: all 0.15s;
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
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
.modal-wide {
  width: 560px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
}
.modal-body {
  overflow-y: auto;
  padding: 20px;
  flex: 1;
}
.modal-loading {
  display: flex;
  justify-content: center;
  padding: 40px;
}
.modal-section-label {
  font-size: 12px;
  font-weight: 700;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 10px;
}

.funding-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.funding-card {
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px 14px;
  cursor: pointer;
  transition: all 0.15s;
}
.funding-card:hover {
  border-color: #10b981;
  background: #f0fdf4;
}
.funding-card.chosen {
  border-color: #10b981;
  background: #ecfdf5;
}
.fc-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}
.fc-id {
  font-family: monospace;
  font-size: 13px;
  font-weight: 700;
  color: #111827;
}
.fc-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 10px;
}
.fc-badge.approved {
  background: #dcfce7;
  color: #16a34a;
}
.fc-product {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}
.fc-meta {
  display: flex;
  gap: 16px;
  font-size: 12.5px;
  color: #6b7280;
}

.supplier-options {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 220px;
  overflow-y: auto;
}
.supplier-opt {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
  position: relative;
}
.supplier-opt:hover {
  border-color: #10b981;
  background: #f0fdf4;
}
.supplier-opt.chosen {
  border-color: #10b981;
  background: #ecfdf5;
}
.sopt-avatar {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #ecfdf5;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}
.sopt-name {
  font-size: 13.5px;
  font-weight: 600;
  color: #111827;
}
.sopt-email {
  font-size: 12px;
  color: #9ca3af;
}
.sopt-check {
  margin-left: auto;
  color: #10b981;
  font-weight: 700;
}

.order-preview {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px;
  margin-top: 16px;
}
.preview-label {
  font-size: 12px;
  font-weight: 700;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 10px;
}
.preview-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: #374151;
  padding: 4px 0;
}
.preview-row.total {
  border-top: 1px solid #e5e7eb;
  margin-top: 6px;
  padding-top: 10px;
  font-size: 14px;
}
</style>
