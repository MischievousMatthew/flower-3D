<template>
  <div class="completed-page">
    
    <div class="page-header">
      <div>
        <h1 class="page-title">Completed</h1>
        <p class="page-sub">Completed orders and received shipments history</p>
      </div>
      <div class="header-actions">
        <div class="date-filter">
          <input type="date" v-model="dateFrom" @change="reload" />
          <span class="sep">—</span>
          <input type="date" v-model="dateTo" @change="reload" />
        </div>
      </div>
    </div>

    <!-- Summary Strip -->
    <div class="summary-strip">
      <div class="sum-card" v-for="s in summaryCards" :key="s.label">
        <div class="sum-icon" :style="{ background: s.bg, color: s.color }">
          {{ s.icon }}
        </div>
        <div>
          <div class="sum-val" :style="{ color: s.color }">{{ s.value }}</div>
          <div class="sum-lbl">{{ s.label }}</div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button
        class="tab"
        :class="{ active: activeTab === 'orders' }"
        @click="activeTab = 'orders'"
      >
        <svg
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          width="14"
        >
          <rect x="3" y="3" width="14" height="14" rx="2" />
          <path d="M7 7h6M7 10h6M7 13h4" />
        </svg>
        Completed Orders
        <span class="tab-count">{{ completedOrders.length }}</span>
      </button>
      <button
        class="tab"
        :class="{ active: activeTab === 'shipments' }"
        @click="activeTab = 'shipments'"
      >
        <svg
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          width="14"
        >
          <rect x="1" y="5" width="10" height="10" rx="1" />
          <path d="M11 8h5l2 3v4h-7V8z" />
          <circle cx="4" cy="15" r="2" />
          <circle cx="15" cy="15" r="2" />
        </svg>
        Received Shipments
        <span class="tab-count">{{ completedShipments.length }}</span>
      </button>
    </div>

    <!-- Orders Table -->
    <div v-if="activeTab === 'orders'" class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Supplier</th>
            <th>Items</th>
            <th>Total</th>
            <th>Completed</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loadingOrders">
            <td colspan="6" class="loading-cell">
              <div class="spinner"></div>
            </td>
          </tr>
          <tr v-else-if="!completedOrders.length">
            <td colspan="6" class="empty-cell">
              No completed orders in this period
            </td>
          </tr>
          <tr v-for="o in completedOrders" :key="o.id" class="data-row">
            <td>
              <div class="order-num-cell">
                <div class="done-check">✓</div>
                <router-link :to="`/erp/orders/${o.id}`" class="order-link">{{
                  o.order_number
                }}</router-link>
              </div>
            </td>
            <td>
              <div class="supplier-cell">
                <div class="sup-ava">{{ o.supplier?.company_name?.[0] }}</div>
                <span>{{ o.supplier?.company_name }}</span>
              </div>
            </td>
            <td class="muted">{{ o.items?.length ?? 0 }} items</td>
            <td class="amount">${{ formatAmount(o.total_amount) }}</td>
            <td class="muted">{{ formatDate(o.updated_at) }}</td>
            <td>
              <router-link
                :to="`/erp/orders/${o.id}`"
                class="action-btn"
                title="View"
              >
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
              </router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Shipments Table -->
    <div v-if="activeTab === 'shipments'" class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Tracking #</th>
            <th>Carrier</th>
            <th>Order</th>
            <th>Supplier</th>
            <th>Received</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loadingShipments">
            <td colspan="6" class="loading-cell">
              <div class="spinner"></div>
            </td>
          </tr>
          <tr v-else-if="!completedShipments.length">
            <td colspan="6" class="empty-cell">
              No received shipments in this period
            </td>
          </tr>
          <tr v-for="s in completedShipments" :key="s.id" class="data-row">
            <td>
              <div class="tracking-cell">
                <div class="delivered-icon">✓</div>
                <code class="tracking-code">{{ s.tracking_number }}</code>
              </div>
            </td>
            <td>
              <div class="carrier-chip">
                <span>{{ s.carrier }}</span>
              </div>
            </td>
            <td>
              <code class="order-ref">{{
                s.purchase_order?.order_number
              }}</code>
            </td>
            <td class="muted">
              {{ s.purchase_order?.supplier?.company_name }}
            </td>
            <td class="muted">
              {{ formatDate(s.received_date || s.updated_at) }}
            </td>
            <td>
              <router-link :to="`/erp/logistics/${s.id}`" class="action-btn">
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
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { scanService } from "../../../../../services/scanService";


const activeTab = ref("orders");
const completedOrders = ref([]);
const completedShipments = ref([]);
const loadingOrders = ref(false);
const loadingShipments = ref(false);
const dateFrom = ref("");
const dateTo = ref("");

const summaryCards = computed(() => [
  {
    label: "Completed Orders",
    value: completedOrders.value.length,
    icon: "✓",
    color: "#10b981",
    bg: "#ecfdf5",
  },
  {
    label: "Received Shipments",
    value: completedShipments.value.length,
    icon: "📦",
    color: "#3b82f6",
    bg: "#eff6ff",
  },
  {
    label: "Total GMV",
    value:
      "$" +
      formatAmount(
        completedOrders.value.reduce((s, o) => s + (o.total_amount || 0), 0),
      ),
    icon: "💰",
    color: "#f59e0b",
    bg: "#fffbeb",
  },
  {
    label: "Avg Order Value",
    value: completedOrders.value.length
      ? "$" +
        formatAmount(
          completedOrders.value.reduce((s, o) => s + (o.total_amount || 0), 0) /
            completedOrders.value.length,
        )
      : "—",
    icon: "📊",
    color: "#8b5cf6",
    bg: "#f5f3ff",
  },
]);

async function reload() {
  const params = {};
  if (dateFrom.value) params.from = dateFrom.value;
  if (dateTo.value) params.to = dateTo.value;

  loadingOrders.value = true;
  loadingShipments.value = true;

  try {
    const [oRes, sRes] = await Promise.all([
      scanService.completedOrders(params),
      scanService.completedShipments(params),
    ]);
    completedOrders.value = oRes.data || oRes;
    completedShipments.value = sRes.data || sRes;
  } catch {
    // Mock data
    completedOrders.value = [
      {
        id: 30,
        order_number: "PO-20250520-00030",
        supplier: { company_name: "Meta Supplies" },
        items: [{}, {}, {}],
        total_amount: 12400,
        updated_at: "2025-05-20T10:00:00Z",
      },
      {
        id: 31,
        order_number: "PO-20250521-00031",
        supplier: { company_name: "PakTextile Co." },
        items: [{}, {}],
        total_amount: 8750,
        updated_at: "2025-05-21T14:30:00Z",
      },
      {
        id: 32,
        order_number: "PO-20250522-00032",
        supplier: { company_name: "FedEx Freight" },
        items: [{}, {}, {}, {}],
        total_amount: 34200,
        updated_at: "2025-05-22T09:15:00Z",
      },
      {
        id: 33,
        order_number: "PO-20250524-00033",
        supplier: { company_name: "GlobalTex Ltd." },
        items: [{}],
        total_amount: 3100,
        updated_at: "2025-05-24T16:45:00Z",
      },
      {
        id: 34,
        order_number: "PO-20250525-00034",
        supplier: { company_name: "FreshLogix" },
        items: [{}, {}],
        total_amount: 6800,
        updated_at: "2025-05-25T11:20:00Z",
      },
    ];
    completedShipments.value = [
      {
        id: 40,
        tracking_number: "FDX-20250520-001",
        carrier: "FedEx",
        purchase_order: {
          order_number: "PO-20250520-00030",
          supplier: { company_name: "Meta Supplies" },
        },
        received_date: "2025-05-20",
      },
      {
        id: 41,
        tracking_number: "DHL-20250521-002",
        carrier: "DHL",
        purchase_order: {
          order_number: "PO-20250521-00031",
          supplier: { company_name: "PakTextile Co." },
        },
        received_date: "2025-05-21",
      },
      {
        id: 42,
        tracking_number: "UPS-20250522-003",
        carrier: "UPS",
        purchase_order: {
          order_number: "PO-20250522-00032",
          supplier: { company_name: "FedEx Freight" },
        },
        received_date: "2025-05-22",
      },
    ];
  } finally {
    loadingOrders.value = false;
    loadingShipments.value = false;
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

onMounted(reload);
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.completed-page {
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
  align-items: center;
  gap: 12px;
}
.date-filter {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 14px;
  background: #fff;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
}
.date-filter input {
  border: none;
  outline: none;
  font-size: 12.5px;
  color: #374151;
  background: transparent;
  font-family: inherit;
}
.sep {
  color: #9ca3af;
}

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
  font-size: 18px;
  flex-shrink: 0;
}
.sum-val {
  font-size: 20px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}
.sum-lbl {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

.tabs {
  display: flex;
  gap: 4px;
}
.tab {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 18px;
  border-radius: 9px;
  border: 1.5px solid #e5e7eb;
  background: #fff;
  font-size: 13.5px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.15s;
}
.tab:hover {
  border-color: #d1d5db;
  color: #374151;
}
.tab.active {
  border-color: #10b981;
  background: #ecfdf5;
  color: #059669;
}
.tab-count {
  background: #f3f4f6;
  border-radius: 10px;
  padding: 1px 7px;
  font-size: 11.5px;
  font-weight: 600;
}
.tab.active .tab-count {
  background: #d1fae5;
  color: #059669;
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
.loading-cell,
.empty-cell {
  text-align: center;
  padding: 48px 16px !important;
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

.order-num-cell {
  display: flex;
  align-items: center;
  gap: 9px;
}
.done-check {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #dcfce7;
  color: #16a34a;
  font-size: 10px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.order-link {
  font-family: monospace;
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  text-decoration: none;
}
.order-link:hover {
  color: #10b981;
}

.supplier-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}
.sup-ava {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  background: #ecfdf5;
  color: #059669;
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.muted {
  color: #6b7280;
}
.amount {
  font-weight: 700;
  color: #111827;
  font-variant-numeric: tabular-nums;
}

.tracking-cell {
  display: flex;
  align-items: center;
  gap: 9px;
}
.delivered-icon {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #dcfce7;
  color: #16a34a;
  font-size: 10px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tracking-code {
  font-family: monospace;
  font-size: 12.5px;
  font-weight: 700;
  color: #374151;
}

.carrier-chip {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  background: #f3f4f6;
  border-radius: 6px;
  font-size: 12.5px;
  font-weight: 600;
  color: #374151;
}
.order-ref {
  font-family: monospace;
  font-size: 12px;
  background: #f3f4f6;
  padding: 2px 7px;
  border-radius: 5px;
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
</style>
