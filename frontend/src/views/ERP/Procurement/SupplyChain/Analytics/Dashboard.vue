<template>
  <div class="dashboard">
    <div class="dash-header">
      <div>
        <h1 class="dash-title">Dashboard</h1>
        <p class="dash-sub">{{ greeting }}, {{ userName }} · {{ todayLabel }}</p>
      </div>
      <div class="dash-actions">
        <div class="date-filter">
          <input v-model="dateFrom" type="date" @change="reload" />
          <span class="date-sep">-</span>
          <input v-model="dateTo" type="date" @change="reload" />
        </div>
        <router-link to="/erp/procurement/supply-chain/orders" class="btn-primary">
          <svg viewBox="0 0 20 20" fill="currentColor" width="14"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/></svg>
          New Order
        </router-link>
      </div>
    </div>

    <div v-if="loading" class="skeleton-grid"><div v-for="i in 4" :key="i" class="skeleton-card"></div></div>

    <div v-else class="kpi-grid">
      <div v-for="kpi in kpis" :key="kpi.label" class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-icon" :style="{ background: kpi.bg }">{{ kpi.emoji }}</div>
          <span class="kpi-label">{{ kpi.label }}</span>
        </div>
        <div class="kpi-value">{{ kpi.value }}</div>
        <div class="kpi-trend" :class="kpi.trendClass">{{ kpi.trendText }}</div>
        <div class="kpi-spark">
          <svg viewBox="0 0 80 28" fill="none" width="80" height="28" preserveAspectRatio="none">
            <defs>
              <linearGradient :id="`grad-${kpi.label}`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="kpi.color" stop-opacity="0.3" />
                <stop offset="100%" :stop-color="kpi.color" stop-opacity="0" />
              </linearGradient>
            </defs>
            <polygon :points="sparkArea(kpi.sparkData)" :fill="`url(#grad-${kpi.label})`" />
            <polyline :points="sparkPoints(kpi.sparkData)" :stroke="kpi.color" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
      </div>
    </div>

    <div class="charts-row">
      <div class="chart-card wide">
        <div class="chart-header">
          <h3>Recent Orders</h3>
          <router-link to="/erp/procurement/supply-chain/orders" class="view-all">View All →</router-link>
        </div>
        <div v-if="visibleRecentOrders.length === 0" class="empty-chart">No recent supply-chain orders yet</div>
        <div v-for="order in visibleRecentOrders" :key="order.id" class="shipment-row">
          <div class="ship-left">
            <div class="ship-num">{{ orderLabel(order) }}</div>
            <span class="ship-badge-sm" :class="order.status">{{ formatStatus(order.status) }}</span>
          </div>
          <div class="ship-address">
            <svg viewBox="0 0 12 16" fill="currentColor" width="9" style="color:#9ca3af"><path d="M6 0C3.24 0 1 2.24 1 5c0 3.75 5 11 5 11s5-7.25 5-11c0-2.76-2.24-5-5-5z"/></svg>
            {{ orderAddress(order) }}
          </div>
          <div class="ship-eta">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="12"><rect x="3" y="4" width="14" height="13" rx="2" /><path d="M3 8h14M7 2v4M13 2v4" /></svg>
            Ordered: {{ orderDateLabel(order.created_at) }}
          </div>
          <div class="ship-progress-track">
            <div v-for="(step,index) in shipSteps" :key="step.status" class="sp-step" :class="{ done: isShipDone(order.status, step.status), current: order.status === step.status }">
              <div class="sp-dot">
                <svg v-if="isShipDone(order.status, step.status) || order.status === step.status" viewBox="0 0 10 10" fill="currentColor" width="8"><circle cx="5" cy="5" r="5" /></svg>
              </div>
              <div v-if="index < shipSteps.length - 1" class="sp-line" :class="{ filled: isShipDone(order.status, shipSteps[index + 1]?.status) }"></div>
              <span class="sp-label">{{ step.label }}</span>
            </div>
          </div>
          <div class="ship-vehicle">
            <div class="perf-ava-wrap ship-supplier-logo">
              <img v-if="getSupplierLogo(order.supplier) && !brokenSupplierLogos.has(order.supplier?.id)" :src="getSupplierLogo(order.supplier)" :alt="order.supplier?.company_name || 'Supplier'" class="perf-ava-img" @error="markSupplierLogoBroken(order.supplier?.id)" />
              <div v-else class="perf-ava" :style="{ background: avatarBg(order.supplier?.company_name) }">{{ order.supplier?.company_name?.[0] || "S" }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="chart-card narrow">
        <div class="chart-header"><h3>Warehouse Floor Overview</h3></div>
        <div class="inv-ring-wrap">
          <svg viewBox="0 0 120 120" width="110">
            <circle cx="60" cy="60" r="46" fill="none" stroke="#f3f4f6" stroke-width="12" />
            <circle cx="60" cy="60" r="46" fill="none" stroke="#10b981" stroke-width="12" :stroke-dasharray="`${inventoryArc} ${289 - inventoryArc}`" stroke-dashoffset="72" stroke-linecap="round" />
            <text x="60" y="55" text-anchor="middle" font-size="20" font-weight="800" fill="#111827">{{ formatCompactNumber(inventoryData.total_flowers ?? inventoryData.total_units ?? 0) }}</text>
            <text x="60" y="68" text-anchor="middle" font-size="8" fill="#9ca3af">Flower Units</text>
          </svg>
          <div class="ring-legend">
            <div class="rl-item"><span class="rl-dot ok"></span>Fresh <strong>{{ floorSummary.fresh }}</strong></div>
            <div class="rl-item"><span class="rl-dot warn"></span>Aging <strong>{{ floorSummary.aging }}</strong></div>
            <div class="rl-item"><span class="rl-dot warn-red"></span>Wilting <strong>{{ floorSummary.wilting }}</strong></div>
            <div class="rl-item"><span class="rl-dot danger"></span>Spoiled <strong>{{ floorSummary.spoiled }}</strong></div>
            <div class="rl-item"><span class="rl-dot alert"></span>Expired Today <strong>{{ floorSummary.expiring_today }}</strong></div>
          </div>
        </div>
        <div class="divider"></div>
        <div class="section-label">Warehouse Snapshot</div>
        <div v-for="warehouse in visibleWarehouseOverview.slice(0, 4)" :key="warehouse.warehouse_id" class="live-ship-row">
          <div class="live-icon"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="14"><path d="M2 8.5 10 3l8 5.5" /><path d="M4 8v8h12V8" /><path d="M8 16v-4h4v4" /></svg></div>
          <div class="live-info">
            <span class="live-num">{{ warehouse.warehouse_name || warehouse.warehouse?.name || "Unnamed Warehouse" }}</span>
            <span class="live-type">{{ warehouse.total_flowers ?? warehouse.total_units ?? 0 }} flowers · {{ warehouse.total_skus ?? 0 }} SKUs</span>
          </div>
          <span class="live-badge" :class="warehouseStatusClass(warehouse)">{{ warehouseStatusLabel(warehouse) }}</span>
        </div>
        <div v-if="!visibleWarehouseOverview.length" class="empty-chart">No warehouse data yet</div>
      </div>
    </div>

    <div class="bottom-row">
      <div class="chart-card">
        <div class="chart-header">
          <h3>Supplier Performance</h3>
          <router-link to="/erp/procurement/supply-chain/suppliers" class="view-all">View All →</router-link>
        </div>
        <table class="perf-table">
          <thead><tr><th>#</th><th>Supplier</th><th>Orders</th><th>GMV</th><th>Completion</th></tr></thead>
          <tbody>
            <tr v-if="!visibleSupplierPerf.length"><td colspan="5" class="empty-row">No data</td></tr>
            <tr v-for="(sup,index) in visibleSupplierPerf.slice(0, 6)" :key="sup.supplier_id" class="perf-row">
              <td class="rank-num">{{ index + 1 }}</td>
              <td>
                <div class="perf-sup">
                  <div class="perf-ava-wrap">
                    <img v-if="getSupplierLogo(sup) && !brokenSupplierLogos.has(sup.supplier_id)" :src="getSupplierLogo(sup)" :alt="sup.company_name" class="perf-ava-img" @error="markSupplierLogoBroken(sup.supplier_id)" />
                    <div v-else class="perf-ava" :style="{ background: avatarBg(sup.company_name) }">{{ sup.company_name?.[0] }}</div>
                  </div>
                  <div><div class="perf-name">{{ sup.company_name }}</div><div class="perf-city">{{ sup.location || "-" }}</div></div>
                </div>
              </td>
              <td class="center">{{ sup.total_orders ?? 0 }}</td>
              <td class="amount">₱{{ formatAmount(sup.total_gmv) }}</td>
              <td><div class="completion-wrap"><div class="comp-bar"><div class="comp-fill" :style="{ width: `${Math.round(sup.completion_rate ?? 0)}%`, background: compColor(sup.completion_rate) }"></div></div><span class="comp-pct" :style="{ color: compColor(sup.completion_rate) }">{{ Math.round(sup.completion_rate ?? 0) }}%</span></div></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="chart-card narrow-card">
        <div class="chart-header"><h3>Deliveries by Status</h3></div>
        <div class="status-donut">
          <svg viewBox="0 0 120 120" width="100">
            <circle cx="60" cy="60" r="46" fill="none" stroke="#f3f4f6" stroke-width="18" />
            <circle v-for="segment in orderSegments" :key="segment.status" cx="60" cy="60" r="46" fill="none" :stroke="segment.color" stroke-width="18" :stroke-dasharray="`${segment.arc} 289`" :stroke-dashoffset="segment.offset" stroke-linecap="butt" />
            <text x="60" y="55" text-anchor="middle" font-size="18" font-weight="800" fill="#111827">{{ totalShipments }}</text>
            <text x="60" y="68" text-anchor="middle" font-size="8" fill="#9ca3af">Total Deliveries</text>
          </svg>
        </div>
        <div class="status-breakdown">
          <div v-for="status in deliveryStatuses" :key="status.status" class="sb-row">
            <span class="sb-dot" :style="{ background: status.color }"></span>
            <span class="sb-label">{{ status.label }}</span>
            <div class="sb-track"><div class="sb-fill" :style="{ width: `${status.pct}%`, background: status.color }"></div></div>
            <span class="sb-val">{{ status.count }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { analyticsService } from "../../../../../services/analyticsService";

const loading = ref(true);
const dateFrom = ref("");
const dateTo = ref("");
const summary = ref({});
const inventoryData = ref({});
const supplierPerf = ref([]);
const brokenSupplierLogos = ref(new Set());

const deliveryStatusConfig = [
  { status: "pending", label: "Pending", color: "#f59e0b" },
  { status: "in_transit", label: "In Transit", color: "#3b82f6" },
  { status: "out_for_delivery", label: "Out for Delivery", color: "#8b5cf6" },
  { status: "delivered", label: "Delivered", color: "#10b981" },
  { status: "failed", label: "Failed", color: "#ef4444" },
  { status: "returned", label: "Returned", color: "#6b7280" },
];
const shipSteps = [
  { status: "pending", label: "Pending", color: "#f59e0b" },
  { status: "processing", label: "Processing", color: "#3b82f6" },
  { status: "shipped", label: "Shipped", color: "#8b5cf6" },
  { status: "received", label: "Received", color: "#06b6d4" },
  { status: "completed", label: "Completed", color: "#10b981" },
];
const SHIP_ORDER = shipSteps.map((step) => step.status);

const userName = computed(() => getStoredUserName());
const visibleSupplierPerf = computed(() => (Array.isArray(supplierPerf.value) ? supplierPerf.value : []));
const visibleRecentOrders = computed(() => (Array.isArray(summary.value?.recent_orders) ? summary.value.recent_orders : []));
const visibleWarehouseOverview = computed(() => {
  const warehouses = Array.isArray(inventoryData.value?.by_warehouse) ? inventoryData.value.by_warehouse : [];
  return [...warehouses].sort((a, b) => Number(b.total_flowers ?? b.total_units ?? 0) - Number(a.total_flowers ?? a.total_units ?? 0));
});
const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour < 12) return "Good morning";
  if (hour < 17) return "Good afternoon";
  return "Good evening";
});
const todayLabel = computed(() => new Date().toLocaleDateString("en-US", { weekday: "long", day: "numeric", month: "long", year: "numeric" }));
const orderCounts = computed(() => {
  const raw = summary.value?.orders?.by_status ?? {};
  return Object.fromEntries(
    Object.entries(raw).map(([status, value]) => {
      if (typeof value === "number") return [status, value];
      if (value && typeof value === "object") return [status, Number(value.count ?? 0)];
      return [status, Number(value ?? 0)];
    }),
  );
});
const shipmentCounts = computed(() => {
  const raw = summary.value?.shipments?.by_status ?? {};
  return Object.fromEntries(
    Object.entries(raw).map(([status, value]) => [status, Number(value ?? 0)]),
  );
});
function statusCount(status) {
  const raw = orderCounts.value?.[status];
  if (typeof raw === "number") return raw;
  if (raw && typeof raw === "object") return Number(raw.count ?? 0);
  return Number(raw ?? 0);
}
const deliveryStatuses = computed(() => {
  const total = deliveryStatusConfig.reduce((sum, cfg) => sum + Number(shipmentCounts.value?.[cfg.status] ?? 0), 0) || 1;
  return deliveryStatusConfig.map((cfg) => {
    const count = Number(shipmentCounts.value?.[cfg.status] ?? 0);
    return { ...cfg, count, pct: Math.round((count / total) * 100) };
  });
});
const totalShipments = computed(() => deliveryStatuses.value.reduce((sum, status) => sum + status.count, 0));
const orderSegments = computed(() => {
  const total = totalShipments.value || 1;
  let offset = 72;
  return deliveryStatuses.value.map((status) => {
    const arc = Math.round((status.count / total) * 289);
    const segment = { ...status, arc, offset };
    offset -= arc;
    return segment;
  });
});
const floorSummary = computed(() => ({
  fresh: Number(inventoryData.value?.floor_summary?.fresh ?? 0),
  aging: Number(inventoryData.value?.floor_summary?.aging ?? 0),
  wilting: Number(inventoryData.value?.floor_summary?.wilting ?? 0),
  spoiled: Number(inventoryData.value?.floor_summary?.spoiled ?? 0),
  expiring_today: Number(inventoryData.value?.floor_summary?.expiring_today ?? 0),
}));
const inStockCount = computed(() => {
  return floorSummary.value.fresh
    + floorSummary.value.aging
    + floorSummary.value.wilting
    + floorSummary.value.spoiled;
});
const inventoryArc = computed(() => {
  const total = inStockCount.value || 1;
  return Math.round((inStockCount.value / total) * 289);
});
const kpis = computed(() => {
  const totalWarehouses = visibleWarehouseOverview.value.length;
  const flowerUnits = Number(inventoryData.value?.total_flowers ?? inventoryData.value?.total_units ?? 0);
  const atRiskFlowers = floorSummary.value.spoiled + floorSummary.value.expiring_today;
  return [
    { label: "Purchase Orders", emoji: "✅", value: formatBig(orderCounts.value.completed ?? 0), trendText: "Completed orders", trendClass: "up", color: "#10b981", bg: "#ecfdf5", sparkData: sparkSeed(orderCounts.value.completed ?? 0, 6) },
    { label: "Processing Orders", emoji: "🚚", value: formatBig(orderCounts.value.processing ?? 0), trendText: `${orderCounts.value.pending ?? 0} pending next`, trendClass: "neutral", color: "#3b82f6", bg: "#eff6ff", sparkData: sparkSeed(orderCounts.value.processing ?? 0, 4) },
    { label: "Flower Stocks", emoji: "🌸", value: formatBig(flowerUnits), trendText: atRiskFlowers > 0 ? `${atRiskFlowers} at risk today` : `${floorSummary.value.fresh} fresh on floor`, trendClass: atRiskFlowers > 0 ? "down" : "up", color: "#8b5cf6", bg: "#f5f3ff", sparkData: sparkSeed(flowerUnits || 0, 8) },
    { label: "Warehouses", emoji: "🏬", value: formatBig(totalWarehouses), trendText: `${visibleWarehouseOverview.value.length} snapshots live`, trendClass: "neutral", color: "#f59e0b", bg: "#fffbeb", sparkData: sparkSeed(totalWarehouses || 0, 3) },
  ];
});

function getStoredUserName() {
  try {
    const raw = localStorage.getItem("auth_user") || localStorage.getItem("employee_user") || localStorage.getItem("user");
    const parsed = raw ? JSON.parse(raw) : null;
    return parsed?.first_name || parsed?.name || parsed?.username || parsed?.email?.split?.("@")?.[0] || "User";
  } catch {
    return "User";
  }
}
function isShipDone(current, step) { return SHIP_ORDER.indexOf(step) <= SHIP_ORDER.indexOf(current); }
function orderLabel(order) { return order?.order_number || `PO-${String(order?.id ?? "").padStart(5, "0")}`; }
function orderAddress(order) {
  return (
    order?.supplier?.address ||
    order?.supplier?.location ||
    order?.supplier?.company_name ||
    "No supplier address"
  );
}
function orderDateLabel(value) { return value ? new Date(value).toLocaleDateString("en-US", { month: "short", day: "numeric" }) : "No date"; }
function formatBig(value) { return Number(value || 0).toLocaleString("en-US"); }
function formatCompactNumber(value) {
  const numeric = Number(value || 0);
  return new Intl.NumberFormat("en-US", { notation: numeric >= 1000 ? "compact" : "standard", maximumFractionDigits: 1 }).format(numeric);
}
function formatAmount(value) { return Number(value || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function getSupplierLogo(supplier) { return supplier?.logo_url || supplier?.logo || supplier?.company_logo || supplier?.image_url || ""; }
function warehouseStatusLabel(warehouse) {
  const totalFlowers = Number(warehouse?.total_flowers ?? warehouse?.total_units ?? 0);
  if (totalFlowers <= 0) return "Empty";
  if (totalFlowers < 25) return "Low Volume";
  return "Stocked";
}
function warehouseStatusClass(warehouse) {
  const label = warehouseStatusLabel(warehouse);
  if (label === "Empty") return "pending";
  if (label === "Low Volume") return "received";
  return "completed";
}
function markSupplierLogoBroken(supplierId) {
  if (!supplierId) return;
  const next = new Set(brokenSupplierLogos.value);
  next.add(supplierId);
  brokenSupplierLogos.value = next;
}
function formatStatus(status) { return (status || "").replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase()); }
function avatarBg(name) {
  const palette = ["#dbeafe", "#dcfce7", "#fef3c7", "#fce7f3", "#ede9fe", "#e0f2fe", "#fef9c3", "#ffedd5"];
  return palette[(name?.charCodeAt(0) ?? 0) % palette.length];
}
function compColor(rate) { if (rate >= 80) return "#10b981"; if (rate >= 50) return "#f59e0b"; return "#ef4444"; }
function sparkSeed(value, variance) {
  const numericValue = Number(value);
  const numericVariance = Number(variance);
  const base = Number.isFinite(numericValue) ? Math.max(1, numericValue) : 1;
  const spread = Number.isFinite(numericVariance) ? numericVariance : 0;
  return Array.from({ length: 10 }, (_, index) => Math.max(1, Math.round(base * (0.45 + index * 0.05) + spread * ((index % 3) + 1))));
}
function sparkPoints(data) {
  if (!data?.length) return "";
  const safe = data.map((value) => {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : 0;
  });
  const max = Math.max(...safe); const min = Math.min(...safe); const range = max - min || 1;
  return safe.map((value, index) => {
    const x = (index / (data.length - 1)) * 80;
    const y = 26 - ((value - min) / range) * 24;
    return `${x},${y}`;
  }).join(" ");
}
function sparkArea(data) {
  if (!data?.length) return "";
  const safe = data.map((value) => {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : 0;
  });
  const max = Math.max(...safe); const min = Math.min(...safe); const range = max - min || 1;
  const points = safe.map((value, index) => {
    const x = (index / (data.length - 1)) * 80;
    const y = 26 - ((value - min) / range) * 24;
    return `${x},${y}`;
  });
  return `${points.join(" ")} 80,28 0,28`;
}

async function reload() {
  loading.value = true;
  try {
    const params = {};
    if (dateFrom.value) params.from = dateFrom.value;
    if (dateTo.value) params.to = dateTo.value;
    const [summaryResponse, inventoryResponse, supplierResponse] = await Promise.all([
      analyticsService.summary(params),
      analyticsService.inventory(params),
      analyticsService.supplierPerformance(params),
    ]);
    const summaryPayload = summaryResponse?.data ?? summaryResponse ?? {};
    const inventoryPayload = inventoryResponse?.data ?? inventoryResponse ?? {};
    const supplierPayload = supplierResponse?.data ?? supplierResponse ?? {};
    summary.value = summaryPayload;
    inventoryData.value = inventoryPayload;
    brokenSupplierLogos.value = new Set();
    supplierPerf.value = Array.isArray(supplierPayload) ? supplierPayload : Array.isArray(supplierPayload.data) ? supplierPayload.data : Array.isArray(supplierPayload.suppliers) ? supplierPayload.suppliers : [];
  } catch (error) {
    console.error("Dashboard load error:", error);
    summary.value = mockSummary();
    inventoryData.value = mockInventory();
    supplierPerf.value = mockSuppliers();
  } finally {
    loading.value = false;
  }
}

function mockSummary() {
  return {
    orders: { total: 8, by_status: { pending: 4, processing: 1, shipped: 0, received: 0, completed: 3 } },
    suppliers: { total: 2 },
    warehouses: { total: 2 },
    recent_orders: [
      { id: 101, order_number: "PO-000101", status: "pending", created_at: new Date().toISOString(), supplier: { id: 1, company_name: "BloomPetal Wholesale", address: "Lot 12 Orchid St, Flower Market, Imus", logo_url: "" } },
      { id: 102, order_number: "PO-000102", status: "completed", created_at: new Date().toISOString(), supplier: { id: 2, company_name: "Wervr", address: "Cavite Distribution Hub", logo_url: "" } },
    ],
  };
}
function mockInventory() {
  return {
    total_skus: 32,
    total_units: 840,
    total_flowers: 840,
    floor_summary: { fresh: 18, aging: 7, wilting: 4, spoiled: 2, expiring_today: 3 },
    low_stock_items: [],
    out_of_stock_items: [],
    by_warehouse: [
      { warehouse_id: 1, warehouse_name: "Main Warehouse", total_units: 540, total_flowers: 540, total_skus: 20, out_of_stock_count: 0 },
      { warehouse_id: 2, warehouse_name: "North Warehouse", total_units: 300, total_flowers: 300, total_skus: 12, out_of_stock_count: 0 },
    ],
  };
}
function mockSuppliers() {
  return [
    { supplier_id: 1, company_name: "BloomPetal Wholesale", location: "Imus, Cavite", logo_url: "", total_orders: 2, total_gmv: 3200, completion_rate: 100 },
    { supplier_id: 2, company_name: "Wervr", location: "Cavite", logo_url: "", total_orders: 1, total_gmv: 0, completion_rate: 0 },
  ];
}

onMounted(reload);
</script>

<style scoped>
*{box-sizing:border-box;font-family:"Poppins",sans-serif}.dashboard{display:flex;flex-direction:column;gap:20px}.dash-header{display:flex;align-items:flex-start;justify-content:space-between}.dash-title{font-size:22px;font-weight:700;color:#111827;margin:0}.dash-sub{font-size:13px;color:#6b7280;margin:2px 0 0}.dash-actions{display:flex;align-items:center;gap:12px}.date-filter{display:flex;align-items:center;gap:8px;padding:7px 14px;background:#fff;border:1.5px solid #e5e7eb;border-radius:9px}.date-filter input{border:none;outline:none;font-size:12.5px;color:#374151;background:transparent;font-family:inherit}.date-sep{color:#9ca3af}.btn-primary{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:#10b981;color:#fff;border:none;border-radius:9px;font-size:13.5px;font-weight:600;cursor:pointer;text-decoration:none}.btn-primary:hover{background:#059669}.skeleton-grid,.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.skeleton-card{height:130px;border-radius:14px;background:linear-gradient(90deg,#f3f4f6 25%,#e9ebee 50%,#f3f4f6 75%);background-size:200% 100%;animation:shimmer 1.5s infinite}@keyframes shimmer{to{background-position:-200% 0}}.kpi-card,.chart-card{background:#fff;border:1px solid #e8ecf0;border-radius:14px;overflow:hidden}.kpi-card{padding:18px 20px;display:flex;flex-direction:column;gap:6px;transition:box-shadow .2s,transform .2s}.kpi-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.08);transform:translateY(-2px)}.kpi-top{display:flex;align-items:center;gap:10px}.kpi-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px}.kpi-label{font-size:12.5px;font-weight:500;color:#6b7280}.kpi-value{font-size:28px;font-weight:800;color:#111827;line-height:1}.kpi-trend{font-size:11.5px;font-weight:600}.kpi-trend.up{color:#10b981}.kpi-trend.down{color:#ef4444}.kpi-trend.neutral{color:#6b7280}.kpi-spark{margin-top:4px}.charts-row{display:grid;grid-template-columns:1fr 260px;gap:16px}.chart-card.narrow-card{width:280px;flex-shrink:0}.chart-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #f0f2f5}.chart-header h3{font-size:14px;font-weight:700;color:#111827;margin:0}.view-all{font-size:12.5px;color:#10b981;text-decoration:none;font-weight:500}.empty-chart{text-align:center;padding:40px;color:#9ca3af;font-size:14px}.shipment-row{padding:16px 20px;border-bottom:1px solid #f3f4f6;display:grid;grid-template-columns:180px 1fr 140px 1fr 56px;align-items:center;gap:12px}.shipment-row:last-child,.live-ship-row:last-child,.perf-row:last-child td{border-bottom:none}.ship-left,.kpi-top,.dash-actions,.perf-sup,.completion-wrap,.rl-item,.live-ship-row,.ship-address,.ship-eta{display:flex;align-items:center;gap:10px}.ship-num{font-weight:700;color:#111827;font-family:monospace;font-size:13.5px}.ship-badge-sm,.live-badge{display:inline-flex;align-items:center;padding:3px 8px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap}.ship-badge-sm.pending,.live-badge.pending{background:#fef3c7;color:#92400e}.ship-badge-sm.processing{background:#dbeafe;color:#1d4ed8}.ship-badge-sm.shipped{background:#ede9fe;color:#6d28d9}.ship-badge-sm.received,.live-badge.received{background:#cffafe;color:#0e7490}.ship-badge-sm.completed,.live-badge.completed{background:#dcfce7;color:#16a34a}.ship-address,.ship-eta{font-size:12px;color:#6b7280;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.ship-progress-track{display:flex;align-items:flex-start;overflow:hidden}.sp-step{display:flex;flex-direction:column;align-items:center;gap:4px;flex:1;position:relative}.sp-dot{width:16px;height:16px;border-radius:50%;border:2px solid #e5e7eb;background:#fff;display:flex;align-items:center;justify-content:center;color:#fff;z-index:1}.sp-step.done .sp-dot{background:#10b981;border-color:#10b981}.sp-step.current .sp-dot{border-color:#10b981;background:#ecfdf5}.sp-line{position:absolute;top:7px;left:calc(50% + 8px);right:calc(-50% + 8px);height:2px;background:#e5e7eb}.sp-line.filled{background:#10b981}.sp-label{font-size:9.5px;color:#9ca3af;white-space:nowrap;text-align:center;margin-top:2px}.sp-step.done .sp-label{color:#10b981}.sp-step.current .sp-label{color:#111827;font-weight:600}.ship-vehicle{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#f9fafb}.inv-ring-wrap{display:flex;align-items:center;justify-content:center;gap:16px;padding:18px 20px}.ring-legend,.status-breakdown,.live-info{display:flex;flex-direction:column}.ring-legend,.status-breakdown{gap:10px}.rl-dot,.sb-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}.rl-dot.ok{background:#10b981}.rl-dot.warn{background:#f59e0b}.rl-dot.danger{background:#ef4444}.rl-item{font-size:12.5px;color:#374151}.rl-item strong{font-weight:700;margin-left:auto}.divider{height:1px;background:#f0f2f5;margin:0 20px}.section-label{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;padding:12px 20px 6px}.live-ship-row{padding:9px 20px;border-bottom:1px solid #f3f4f6}.live-icon{width:30px;height:30px;border-radius:8px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;color:#6b7280;flex-shrink:0}.live-info{gap:1px;flex:1}.live-num{font-size:13px;font-weight:700;color:#111827;font-family:monospace}.live-type,.perf-city{font-size:11px;color:#9ca3af}.bottom-row{display:grid;grid-template-columns:1fr 300px;gap:16px}.perf-table{width:100%;border-collapse:collapse;font-size:13.5px}.perf-table th{padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;background:#f9fafb;border-bottom:1px solid #e8ecf0}.perf-row td{padding:12px 16px;border-bottom:1px solid #f3f4f6;vertical-align:middle}.perf-row:hover td{background:#fafafa}.empty-row{text-align:center;padding:40px 16px!important;color:#9ca3af}.rank-num{font-size:12px;font-weight:700;color:#9ca3af;text-align:center}.perf-ava-wrap{width:32px;height:32px;border-radius:8px;overflow:hidden;flex-shrink:0;border:1px solid #e5e7eb;background:#fff}.ship-supplier-logo{width:38px;height:38px}.perf-ava-img{width:100%;height:100%;object-fit:contain;display:block;background:#fff}.perf-ava{width:100%;height:100%;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#374151}.perf-name{font-weight:600;color:#111827;font-size:13px}.center{text-align:center;color:#374151}.amount{font-weight:600;color:#374151}.comp-bar{flex:1;height:5px;background:#f3f4f6;border-radius:3px;overflow:hidden;min-width:60px}.comp-fill,.sb-fill{height:100%;border-radius:2px}.status-donut{display:flex;justify-content:center;padding:18px 20px 10px}.status-breakdown{padding:0 20px 18px}.sb-row{display:flex;align-items:center;gap:8px;font-size:12.5px}.sb-label{color:#374151;min-width:72px;font-weight:500}.sb-track{flex:1;height:4px;background:#f3f4f6;border-radius:2px;overflow:hidden}.sb-val{font-weight:700;color:#374151;min-width:28px;text-align:right}.ship-supplier-logo .perf-ava{font-size:12px}
</style>
