<template>
  <div class="dashboard">
    
    <div class="dash-header">
      <div>
        <h1 class="dash-title">Dashboard</h1>
        <p class="dash-sub">
          {{ greeting }}, {{ userName }} · {{ todayLabel }}
        </p>
      </div>
      <div class="dash-actions">
        <div class="date-filter">
          <input type="date" v-model="dateFrom" @change="reload" />
          <span class="date-sep">—</span>
          <input type="date" v-model="dateTo" @change="reload" />
        </div>
        <router-link to="/erp/orders/create" class="btn-primary">
          <svg viewBox="0 0 20 20" fill="currentColor" width="14">
            <path
              d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
            />
          </svg>
          New Order
        </router-link>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="skeleton-grid">
      <div v-for="i in 4" :key="i" class="skeleton-card"></div>
    </div>

    <!-- KPI Cards -->
    <div v-else class="kpi-grid">
      <div
        class="kpi-card"
        v-for="kpi in kpis"
        :key="kpi.label"
        :style="{ '--accent': kpi.color }"
      >
        <div class="kpi-top">
          <div class="kpi-icon" :style="{ background: kpi.bg }">
            {{ kpi.emoji }}
          </div>
          <span class="kpi-label">{{ kpi.label }}</span>
        </div>
        <div class="kpi-value">{{ kpi.value }}</div>
        <div class="kpi-trend" :class="kpi.trend >= 0 ? 'up' : 'down'">
          {{ kpi.trend >= 0 ? "↑" : "↓" }} {{ Math.abs(kpi.trend) }}% This Month
        </div>
        <!-- Sparkline -->
        <div class="kpi-spark">
          <svg
            viewBox="0 0 80 28"
            fill="none"
            width="80"
            height="28"
            preserveAspectRatio="none"
          >
            <defs>
              <linearGradient
                :id="`grad-${kpi.label}`"
                x1="0"
                y1="0"
                x2="0"
                y2="1"
              >
                <stop offset="0%" :stop-color="kpi.color" stop-opacity="0.3" />
                <stop offset="100%" :stop-color="kpi.color" stop-opacity="0" />
              </linearGradient>
            </defs>
            <polygon
              :points="sparkArea(kpi.sparkData, kpi.color)"
              :fill="`url(#grad-${kpi.label})`"
            />
            <polyline
              :points="sparkPoints(kpi.sparkData)"
              :stroke="kpi.color"
              stroke-width="2"
              fill="none"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </div>
      </div>
    </div>

    <!-- Main Charts Row -->
    <div class="charts-row">
      <!-- Recent Shipments (left) -->
      <div class="chart-card wide">
        <div class="chart-header">
          <h3>Recent Shipments</h3>
          <router-link to="/erp/logistics" class="view-all"
            >View All →</router-link
          >
        </div>

        <div v-if="visibleRecentShipments.length === 0" class="empty-chart">
          No shipments yet
        </div>

        <div
          v-for="ship in visibleRecentShipments"
          :key="ship.id"
          class="shipment-row"
        >
          <div class="ship-left">
            <div class="ship-num">#{{ ship.tracking_number?.slice(-7) }}</div>
            <span class="ship-badge-sm" :class="ship.status">{{
              formatStatus(ship.status)
            }}</span>
          </div>
          <div class="ship-address">
            <svg
              viewBox="0 0 12 16"
              fill="currentColor"
              width="9"
              style="color: #9ca3af"
            >
              <path
                d="M6 0C3.24 0 1 2.24 1 5c0 3.75 5 11 5 11s5-7.25 5-11c0-2.76-2.24-5-5-5z"
              />
            </svg>
            {{
              ship.purchase_order?.supplier?.address?.slice(0, 36) ||
              "789 Front Street West, Toronto"
            }}
          </div>
          <div class="ship-eta">
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="12"
            >
              <rect x="3" y="4" width="14" height="13" rx="2" />
              <path d="M3 8h14M7 2v4M13 2v4" />
            </svg>
            ETA: {{ etaLabel(ship) }}
          </div>
          <!-- Progress track -->
          <div class="ship-progress-track">
            <div
              v-for="(step, i) in shipSteps"
              :key="step.status"
              class="sp-step"
              :class="{
                done: isShipDone(ship.status, step.status),
                current: ship.status === step.status,
              }"
            >
              <div class="sp-dot">
                <svg
                  v-if="
                    isShipDone(ship.status, step.status) ||
                    ship.status === step.status
                  "
                  viewBox="0 0 10 10"
                  fill="currentColor"
                  width="8"
                >
                  <circle cx="5" cy="5" r="5" />
                </svg>
              </div>
              <div
                v-if="i < shipSteps.length - 1"
                class="sp-line"
                :class="{
                  filled: isShipDone(ship.status, shipSteps[i + 1]?.status),
                }"
              ></div>
              <span class="sp-label">{{ step.label }}</span>
            </div>
          </div>
          <!-- Vehicle image placeholder -->
          <div class="ship-vehicle" :class="ship.vehicle_type || 'truck'">
            <span v-if="(ship.vehicle_type || 'truck') === 'truck'">🚚</span>
            <span v-else-if="ship.vehicle_type === 'air'">✈️</span>
            <span v-else>🚢</span>
          </div>
        </div>
      </div>

      <!-- Real-Time Overview (right) -->
      <div class="chart-card narrow">
        <div class="chart-header">
          <h3>Live Overview</h3>
        </div>

        <!-- Inventory donut -->
        <div class="inv-ring-wrap">
          <svg viewBox="0 0 120 120" width="110">
            <circle
              cx="60"
              cy="60"
              r="46"
              fill="none"
              stroke="#f3f4f6"
              stroke-width="12"
            />
            <circle
              cx="60"
              cy="60"
              r="46"
              fill="none"
              stroke="#10b981"
              stroke-width="12"
              :stroke-dasharray="`${inventoryArc} ${289 - inventoryArc}`"
              stroke-dashoffset="72"
              stroke-linecap="round"
            />
            <text
              x="60"
              y="55"
              text-anchor="middle"
              font-size="20"
              font-weight="800"
              fill="#111827"
            >
              {{ inventoryData.total_skus ?? 0 }}
            </text>
            <text
              x="60"
              y="68"
              text-anchor="middle"
              font-size="8"
              fill="#9ca3af"
            >
              Total SKUs
            </text>
          </svg>
          <div class="ring-legend">
            <div class="rl-item">
              <span class="rl-dot ok"></span>In Stock
              <strong>{{ inStockCount }}</strong>
            </div>
            <div class="rl-item">
              <span class="rl-dot warn"></span>Low Stock
              <strong>{{ inventoryData.low_stock_items?.length ?? 0 }}</strong>
            </div>
            <div class="rl-item">
              <span class="rl-dot danger"></span>Out of Stock
              <strong>{{
                inventoryData.out_of_stock_items?.length ?? 0
              }}</strong>
            </div>
          </div>
        </div>

        <div class="divider"></div>

        <!-- Live shipment list -->
        <div class="section-label">Active Shipments</div>
        <div
          v-for="ship in visibleRecentShipments.slice(0, 4)"
          :key="ship.id"
          class="live-ship-row"
        >
          <div class="live-icon">
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
          </div>
          <div class="live-info">
            <span class="live-num">#{{ ship.tracking_number?.slice(-7) }}</span>
            <span class="live-type">Food Materials</span>
          </div>
          <span class="live-badge" :class="ship.status">{{
            formatStatus(ship.status)
          }}</span>
        </div>
      </div>
    </div>

    <!-- Bottom Row: Supplier Performance + Order Stats -->
    <div class="bottom-row">
      <!-- Supplier Performance Table -->
      <div class="chart-card">
        <div class="chart-header">
          <h3>Supplier Performance</h3>
          <router-link to="/erp/suppliers" class="view-all"
            >View All →</router-link
          >
        </div>
        <table class="perf-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Supplier</th>
              <th>Orders</th>
              <th>GMV</th>
              <th>Completion</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!visibleSupplierPerf.length">
              <td colspan="5" class="empty-row">No data</td>
            </tr>
            <tr
              v-for="(sup, i) in visibleSupplierPerf.slice(0, 6)"
              :key="sup.supplier_id"
              class="perf-row"
            >
              <td class="rank-num">{{ i + 1 }}</td>
              <td>
                <div class="perf-sup">
                  <div
                    class="perf-ava"
                    :style="{ background: avatarBg(sup.company_name) }"
                  >
                    {{ sup.company_name?.[0] }}
                  </div>
                  <div>
                    <div class="perf-name">{{ sup.company_name }}</div>
                    <div class="perf-city">{{ sup.location || "—" }}</div>
                  </div>
                </div>
              </td>
              <td class="center">{{ sup.total_orders ?? 0 }}</td>
              <td class="amount">${{ formatAmount(sup.total_gmv) }}</td>
              <td>
                <div class="completion-wrap">
                  <div class="comp-bar">
                    <div
                      class="comp-fill"
                      :style="{
                        width: (sup.completion_rate ?? 0) + '%',
                        background: compColor(sup.completion_rate),
                      }"
                    ></div>
                  </div>
                  <span
                    class="comp-pct"
                    :style="{ color: compColor(sup.completion_rate) }"
                    >{{ Math.round(sup.completion_rate ?? 0) }}%</span
                  >
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Order Status Breakdown -->
      <div class="chart-card narrow-card">
        <div class="chart-header">
          <h3>Orders by Status</h3>
        </div>

        <div class="status-donut">
          <svg viewBox="0 0 120 120" width="100">
            <circle
              cx="60"
              cy="60"
              r="46"
              fill="none"
              stroke="#f3f4f6"
              stroke-width="18"
            />
            <circle
              v-for="seg in orderSegments"
              :key="seg.status"
              cx="60"
              cy="60"
              r="46"
              fill="none"
              :stroke="seg.color"
              stroke-width="18"
              :stroke-dasharray="`${seg.arc} ${289}`"
              :stroke-dashoffset="seg.offset"
              stroke-linecap="butt"
            />
            <text
              x="60"
              y="55"
              text-anchor="middle"
              font-size="18"
              font-weight="800"
              fill="#111827"
            >
              {{ totalOrders }}
            </text>
            <text
              x="60"
              y="68"
              text-anchor="middle"
              font-size="8"
              fill="#9ca3af"
            >
              Total Orders
            </text>
          </svg>
        </div>

        <div class="status-breakdown">
          <div v-for="s in orderStatuses" :key="s.status" class="sb-row">
            <span class="sb-dot" :style="{ background: s.color }"></span>
            <span class="sb-label">{{ s.label }}</span>
            <div class="sb-track">
              <div
                class="sb-fill"
                :style="{ width: s.pct + '%', background: s.color }"
              ></div>
            </div>
            <span class="sb-val">{{ s.count }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { analyticsService } from "../../../../../services/analyticsService";


const loading = ref(true);
const dateFrom = ref("");
const dateTo = ref("");
const summary = ref({});
const inventoryData = ref({});
const supplierPerf = ref([]);
const recentShipments = ref([]);

const visibleSupplierPerf = computed(() =>
  Array.isArray(supplierPerf.value) ? supplierPerf.value : [],
);

const visibleRecentShipments = computed(() =>
  Array.isArray(recentShipments.value) ? recentShipments.value : [],
);

const userName = "Daniel";

const greeting = computed(() => {
  const h = new Date().getHours();
  if (h < 12) return "Good morning";
  if (h < 17) return "Good afternoon";
  return "Good evening";
});

const todayLabel = computed(() =>
  new Date().toLocaleDateString("en-US", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  }),
);

// ── KPI Cards ────────────────────────────────────────────────────────────────

const kpis = computed(() => {
  const o = summary.value.orders || {};
  const s = summary.value.shipments || {};
  return [
    {
      label: "Total Shipments",
      emoji: "📦",
      value: formatBig(s.total ?? 0),
      trend: 21.01,
      color: "#10b981",
      bg: "#ecfdf5",
      sparkData: [14, 18, 13, 22, 17, 26, 20, 28, 24, 30],
    },
    {
      label: "Out for Delivery",
      emoji: "🚚",
      value: formatBig(s.by_status?.out_for_delivery ?? 6500),
      trend: 21.01,
      color: "#3b82f6",
      bg: "#eff6ff",
      sparkData: [8, 12, 10, 15, 11, 14, 13, 18, 14, 16],
    },
    {
      label: "In Transit",
      emoji: "✈️",
      value: formatBig(s.by_status?.in_transit ?? 5000),
      trend: 21.01,
      color: "#8b5cf6",
      bg: "#f5f3ff",
      sparkData: [20, 16, 22, 18, 25, 21, 27, 23, 29, 26],
    },
    {
      label: "Pending",
      emoji: "🕐",
      value: formatBig(o.by_status?.pending ?? 0),
      trend: -2.5,
      color: "#f59e0b",
      bg: "#fffbeb",
      sparkData: [30, 26, 28, 22, 25, 20, 22, 18, 20, 15],
    },
  ];
});

// ── Inventory ────────────────────────────────────────────────────────────────

const inStockCount = computed(() => {
  const total = inventoryData.value.total_skus ?? 0;
  const low = inventoryData.value.low_stock_items?.length ?? 0;
  const out = inventoryData.value.out_of_stock_items?.length ?? 0;
  return Math.max(0, total - low - out);
});

const inventoryArc = computed(() => {
  const total = inventoryData.value.total_skus ?? 1;
  const inStock = inStockCount.value;
  return Math.round((inStock / total) * 289);
});

// ── Orders ───────────────────────────────────────────────────────────────────

const orderStatusConfig = [
  { status: "pending", label: "Pending", color: "#f59e0b" },
  { status: "processing", label: "Processing", color: "#3b82f6" },
  { status: "shipped", label: "Shipped", color: "#8b5cf6" },
  { status: "received", label: "Received", color: "#06b6d4" },
  { status: "completed", label: "Completed", color: "#10b981" },
];

const totalOrders = computed(() => {
  const byStatus = summary.value.orders?.by_status || {};
  return Object.values(byStatus).reduce((a, b) => a + b, 0);
});

const orderStatuses = computed(() => {
  const byStatus = summary.value.orders?.by_status || {};
  const total = totalOrders.value || 1;
  return orderStatusConfig.map((cfg) => ({
    ...cfg,
    count: byStatus[cfg.status] ?? 0,
    pct: Math.round(((byStatus[cfg.status] ?? 0) / total) * 100),
  }));
});

const orderSegments = computed(() => {
  const total = totalOrders.value || 1;
  let offset = 72;
  return orderStatuses.value.map((s) => {
    const arc = Math.round((s.count / total) * 289);
    const seg = { ...s, arc, offset };
    offset = offset - arc;
    return seg;
  });
});

// ── Shipment Steps ───────────────────────────────────────────────────────────

const shipSteps = [
  { status: "pending", label: "Order Placed" },
  { status: "in_transit", label: "In Transit" },
  { status: "out_for_delivery", label: "Out for Delivery" },
  { status: "delivered", label: "Delivered" },
];

const SHIP_ORDER = ["pending", "in_transit", "out_for_delivery", "delivered"];

function isShipDone(current, step) {
  return SHIP_ORDER.indexOf(step) <= SHIP_ORDER.indexOf(current);
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function formatBig(n) {
  return Number(n).toLocaleString("en-US");
}
function formatAmount(n) {
  return Number(n || 0).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
function formatStatus(s) {
  return (s || "").replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase());
}

function avatarBg(name) {
  const palette = [
    "#dbeafe",
    "#dcfce7",
    "#fef3c7",
    "#fce7f3",
    "#ede9fe",
    "#e0f2fe",
    "#fef9c3",
    "#ffedd5",
  ];
  return palette[(name?.charCodeAt(0) ?? 0) % palette.length];
}

function compColor(rate) {
  if (rate >= 80) return "#10b981";
  if (rate >= 50) return "#f59e0b";
  return "#ef4444";
}

function etaLabel(ship) {
  const d = ship.shipped_date;
  if (!d) return "TBD";
  const from = new Date(d);
  const to = new Date(d);
  to.setDate(to.getDate() + 5);
  const fmt = (d) =>
    d.toLocaleDateString("en-US", { month: "short", day: "numeric" });
  return `${fmt(from)} - ${fmt(to)}`;
}

function sparkPoints(data) {
  if (!data?.length) return "";
  const max = Math.max(...data);
  const min = Math.min(...data);
  const range = max - min || 1;
  return data
    .map((v, i) => {
      const x = (i / (data.length - 1)) * 80;
      const y = 26 - ((v - min) / range) * 24;
      return `${x},${y}`;
    })
    .join(" ");
}

function sparkArea(data, color) {
  if (!data?.length) return "";
  const max = Math.max(...data);
  const min = Math.min(...data);
  const range = max - min || 1;
  const pts = data.map((v, i) => {
    const x = (i / (data.length - 1)) * 80;
    const y = 26 - ((v - min) / range) * 24;
    return `${x},${y}`;
  });
  return `${pts.join(" ")} 80,28 0,28`;
}

// ── Data Fetching ────────────────────────────────────────────────────────────

async function reload() {
  loading.value = true;
  try {
    const params = {};
    if (dateFrom.value) params.from = dateFrom.value;
    if (dateTo.value) params.to = dateTo.value;

    const [sumRes, invRes, supRes] = await Promise.all([
      analyticsService.summary(params),
      analyticsService.inventory(params),
      analyticsService.supplierPerformance(params),
    ]);

    const summaryPayload = sumRes?.data ?? sumRes ?? {};
    const inventoryPayload = invRes?.data ?? invRes ?? {};
    const supplierPayload = supRes?.data ?? supRes ?? {};

    summary.value = summaryPayload;
    inventoryData.value = inventoryPayload;
    supplierPerf.value = Array.isArray(supplierPayload)
      ? supplierPayload
      : Array.isArray(supplierPayload.suppliers)
        ? supplierPayload.suppliers
        : Array.isArray(supplierPayload.data)
          ? supplierPayload.data
          : [];

    // Extract recent shipments from summary if available
    recentShipments.value = Array.isArray(summaryPayload.recent_shipments)
      ? summaryPayload.recent_shipments
      : generateMockShipments();
  } catch (e) {
    console.error("Dashboard load error:", e);
    // Fallback to mock data for demo
    summary.value = mockSummary();
    inventoryData.value = mockInventory();
    supplierPerf.value = mockSuppliers();
    recentShipments.value = generateMockShipments();
  } finally {
    loading.value = false;
  }
}

// ── Mock Data (for demo/development) ────────────────────────────────────────

function mockSummary() {
  return {
    orders: {
      total: 843,
      by_status: {
        pending: 156,
        processing: 231,
        shipped: 188,
        received: 94,
        completed: 174,
      },
    },
    shipments: {
      total: 26666,
      by_status: {
        pending: 26666,
        in_transit: 5000,
        out_for_delivery: 6500,
        delivered: 14000,
      },
    },
  };
}

function mockInventory() {
  return {
    total_skus: 1284,
    total_units: 48320,
    low_stock_items: [
      {
        item_id: 1,
        sku: "BOLT-M8-100",
        product_name: "Industrial Bolt M8",
        quantity: 4,
        warehouse: "North DC",
      },
      {
        item_id: 2,
        sku: "GSKT-L-200",
        product_name: "Rubber Gasket L",
        quantity: 7,
        warehouse: "East DC",
      },
      {
        item_id: 3,
        sku: "PIPE-2IN-050",
        product_name: '2" Steel Pipe',
        quantity: 2,
        warehouse: "West DC",
      },
    ],
    out_of_stock_items: [
      {
        item_id: 4,
        sku: "VLVE-BRS-010",
        product_name: 'Brass Valve 1"',
        warehouse: "North DC",
      },
    ],
  };
}

function mockSuppliers() {
  return [
    {
      supplier_id: 1,
      company_name: "Meta Supplies",
      location: "San Jose, CA",
      total_orders: 142,
      total_gmv: 284000,
      completion_rate: 94,
    },
    {
      supplier_id: 2,
      company_name: "PakTextile Co.",
      location: "Karachi, PK",
      total_orders: 98,
      total_gmv: 156000,
      completion_rate: 87,
    },
    {
      supplier_id: 3,
      company_name: "FedEx Freight",
      location: "Memphis, TN",
      total_orders: 76,
      total_gmv: 320000,
      completion_rate: 98,
    },
    {
      supplier_id: 4,
      company_name: "RapidRoute Logistics",
      location: "Toronto, CA",
      total_orders: 54,
      total_gmv: 98000,
      completion_rate: 62,
    },
    {
      supplier_id: 5,
      company_name: "FreshLogix",
      location: "Berlin, DE",
      total_orders: 43,
      total_gmv: 74000,
      completion_rate: 78,
    },
    {
      supplier_id: 6,
      company_name: "GlobalTex Ltd.",
      location: "Istanbul, TR",
      total_orders: 39,
      total_gmv: 61000,
      completion_rate: 85,
    },
  ];
}

function generateMockShipments() {
  return [
    {
      id: 1,
      tracking_number: "FDX-3752584",
      status: "in_transit",
      vehicle_type: "truck",
      shipped_date: new Date().toISOString(),
      purchase_order: {
        supplier: { address: "789 Front Street West, Toronto" },
      },
    },
    {
      id: 2,
      tracking_number: "DHL-3752585",
      status: "out_for_delivery",
      vehicle_type: "air",
      shipped_date: new Date().toISOString(),
      purchase_order: {
        supplier: { address: "789 Front Street West, Toronto" },
      },
    },
    {
      id: 3,
      tracking_number: "MSK-3752586",
      status: "pending",
      vehicle_type: "ship",
      shipped_date: null,
      purchase_order: {
        supplier: { address: "789 Front Street West, Toronto" },
      },
    },
  ];
}

onMounted(reload);
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

/* ── Page ───────────────────────────────────────────────────────────────────── */
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.dash-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}
.dash-title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.dash-sub {
  font-size: 13px;
  color: #6b7280;
  margin: 2px 0 0;
}

.dash-actions {
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
.date-sep {
  color: #9ca3af;
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
}
.btn-primary:hover {
  background: #059669;
}

/* ── Skeleton ──────────────────────────────────────────────────────────────── */
.skeleton-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}
.skeleton-card {
  height: 130px;
  border-radius: 14px;
  background: linear-gradient(90deg, #f3f4f6 25%, #e9ebee 50%, #f3f4f6 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
@keyframes shimmer {
  to {
    background-position: -200% 0;
  }
}

/* ── KPI Grid ──────────────────────────────────────────────────────────────── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.kpi-card {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 14px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  overflow: hidden;
  position: relative;
  transition:
    box-shadow 0.2s,
    transform 0.2s;
}
.kpi-card:hover {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.kpi-top {
  display: flex;
  align-items: center;
  gap: 10px;
}
.kpi-icon {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}
.kpi-label {
  font-size: 12.5px;
  font-weight: 500;
  color: #6b7280;
}
.kpi-value {
  font-size: 28px;
  font-weight: 800;
  color: #111827;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}
.kpi-trend {
  font-size: 11.5px;
  font-weight: 600;
}
.kpi-trend.up {
  color: #10b981;
}
.kpi-trend.down {
  color: #ef4444;
}
.kpi-spark {
  margin-top: 4px;
}

/* ── Charts Row ────────────────────────────────────────────────────────────── */
.charts-row {
  display: grid;
  grid-template-columns: 1fr 260px;
  gap: 16px;
}

.chart-card {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 14px;
  overflow: hidden;
}
.chart-card.wide {
}
.chart-card.narrow {
}
.chart-card.narrow-card {
  width: 280px;
  flex-shrink: 0;
}

.chart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #f0f2f5;
}
.chart-header h3 {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.view-all {
  font-size: 12.5px;
  color: #10b981;
  text-decoration: none;
  font-weight: 500;
}
.view-all:hover {
  text-decoration: underline;
}

.empty-chart {
  text-align: center;
  padding: 40px;
  color: #9ca3af;
  font-size: 14px;
}

/* ── Shipment Rows ─────────────────────────────────────────────────────────── */
.shipment-row {
  padding: 16px 20px;
  border-bottom: 1px solid #f3f4f6;
  display: grid;
  grid-template-columns: 180px 1fr 140px 1fr 50px;
  align-items: center;
  gap: 12px;
}
.shipment-row:last-child {
  border-bottom: none;
}

.ship-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.ship-num {
  font-weight: 700;
  color: #111827;
  font-family: monospace;
  font-size: 13.5px;
}

.ship-badge-sm {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
.ship-badge-sm.pending {
  background: #fef3c7;
  color: #92400e;
}
.ship-badge-sm.in_transit {
  background: #dbeafe;
  color: #1d4ed8;
}
.ship-badge-sm.out_for_delivery {
  background: #ede9fe;
  color: #6d28d9;
}
.ship-badge-sm.delivered {
  background: #dcfce7;
  color: #16a34a;
}
.ship-badge-sm.failed {
  background: #fee2e2;
  color: #dc2626;
}

.ship-address {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: #6b7280;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.ship-eta {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: #6b7280;
  white-space: nowrap;
}

/* Progress track inside shipment row */
.ship-progress-track {
  display: flex;
  align-items: flex-start;
  gap: 0;
  overflow: hidden;
}
.sp-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  flex: 1;
  position: relative;
}
.sp-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid #e5e7eb;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
  z-index: 1;
}
.sp-step.done .sp-dot {
  background: #10b981;
  border-color: #10b981;
}
.sp-step.current .sp-dot {
  border-color: #10b981;
  background: #ecfdf5;
}
.sp-line {
  position: absolute;
  top: 7px;
  left: calc(50% + 8px);
  right: calc(-50% + 8px);
  height: 2px;
  background: #e5e7eb;
}
.sp-line.filled {
  background: #10b981;
}
.sp-label {
  font-size: 9.5px;
  color: #9ca3af;
  white-space: nowrap;
  text-align: center;
  margin-top: 2px;
}
.sp-step.done .sp-label {
  color: #10b981;
}
.sp-step.current .sp-label {
  color: #111827;
  font-weight: 600;
}

.ship-vehicle {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  background: #f9fafb;
}

/* ── Live Overview Panel ───────────────────────────────────────────────────── */
.inv-ring-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 18px 20px;
}
.ring-legend {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.rl-item {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12.5px;
  color: #374151;
}
.rl-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
}
.rl-dot.ok {
  background: #10b981;
}
.rl-dot.warn {
  background: #f59e0b;
}
.rl-dot.danger {
  background: #ef4444;
}
.rl-item strong {
  font-weight: 700;
  margin-left: auto;
}

.divider {
  height: 1px;
  background: #f0f2f5;
  margin: 0 20px;
}

.section-label {
  font-size: 11px;
  font-weight: 700;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 12px 20px 6px;
}

.live-ship-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 20px;
  border-bottom: 1px solid #f3f4f6;
}
.live-ship-row:last-child {
  border-bottom: none;
}
.live-icon {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  flex-shrink: 0;
}
.live-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
  flex: 1;
}
.live-num {
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  font-family: monospace;
}
.live-type {
  font-size: 11px;
  color: #9ca3af;
}
.live-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  border-radius: 20px;
  font-size: 10.5px;
  font-weight: 600;
  white-space: nowrap;
}
.live-badge.pending {
  background: #fef3c7;
  color: #92400e;
}
.live-badge.in_transit {
  background: #dbeafe;
  color: #1d4ed8;
}
.live-badge.out_for_delivery {
  background: #ede9fe;
  color: #6d28d9;
}
.live-badge.delivered {
  background: #dcfce7;
  color: #16a34a;
}

/* ── Bottom Row ────────────────────────────────────────────────────────────── */
.bottom-row {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 16px;
}

/* Supplier Performance Table */
.perf-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.perf-table th {
  padding: 10px 16px;
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: #f9fafb;
  border-bottom: 1px solid #e8ecf0;
}
.perf-row td {
  padding: 12px 16px;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}
.perf-row:last-child td {
  border-bottom: none;
}
.perf-row:hover td {
  background: #fafafa;
}
.empty-row {
  text-align: center;
  padding: 40px 16px !important;
  color: #9ca3af;
}

.rank-num {
  font-size: 12px;
  font-weight: 700;
  color: #9ca3af;
  text-align: center;
}
.perf-sup {
  display: flex;
  align-items: center;
  gap: 10px;
}
.perf-ava {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 13px;
  color: #374151;
  flex-shrink: 0;
}
.perf-name {
  font-weight: 600;
  color: #111827;
  font-size: 13px;
}
.perf-city {
  font-size: 11.5px;
  color: #9ca3af;
  margin-top: 1px;
}
.center {
  text-align: center;
  color: #374151;
}
.amount {
  font-weight: 600;
  color: #374151;
  font-variant-numeric: tabular-nums;
}

.completion-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}
.comp-bar {
  flex: 1;
  height: 5px;
  background: #f3f4f6;
  border-radius: 3px;
  overflow: hidden;
  min-width: 60px;
}
.comp-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.5s;
}
.comp-pct {
  font-size: 12px;
  font-weight: 700;
  min-width: 35px;
  text-align: right;
}

/* Order Status Donut */
.status-donut {
  display: flex;
  justify-content: center;
  padding: 18px 20px 10px;
}

.status-breakdown {
  padding: 0 20px 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.sb-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
}
.sb-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.sb-label {
  color: #374151;
  min-width: 72px;
  font-weight: 500;
}
.sb-track {
  flex: 1;
  height: 4px;
  background: #f3f4f6;
  border-radius: 2px;
  overflow: hidden;
}
.sb-fill {
  height: 100%;
  border-radius: 2px;
  transition: width 0.6s;
}
.sb-val {
  font-weight: 700;
  color: #374151;
  min-width: 28px;
  text-align: right;
  font-variant-numeric: tabular-nums;
}
</style>
