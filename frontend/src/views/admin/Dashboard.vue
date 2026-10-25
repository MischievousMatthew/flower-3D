<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <header class="content-header">
        <div class="header-left">
          <button class="mobile-toggle" @click="toggleMobile" aria-label="Toggle Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="3" y1="12" x2="21" y2="12" />
              <line x1="3" y1="6" x2="21" y2="6" />
              <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
          </button>
          <div>
            <h1 class="page-title">{{ greeting }}</h1>
            <p class="page-subtitle">
              Here's how BloomCraft is doing
              <template v-if="data">· updated {{ updatedLabel }}</template>
            </p>
          </div>
        </div>
        <div class="header-right">
          <div class="range-group" role="tablist" aria-label="Date range">
            <button
              v-for="option in RANGES"
              :key="option.value"
              type="button"
              role="tab"
              class="range-btn"
              :class="{ active: range === option.value }"
              :aria-selected="range === option.value"
              @click="setRange(option.value)"
            >
              {{ option.label }}
            </button>
          </div>
          <button type="button" class="ghost-btn" :disabled="loading" @click="load">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" /></svg>
            <span>{{ loading ? "Refreshing" : "Refresh" }}</span>
          </button>
        </div>
      </header>

      <div class="page-body">
        <section v-if="loadError && !data" class="panel error-panel">
          <h2 class="panel-title">The dashboard couldn't be loaded</h2>
          <p class="panel-sub">{{ loadError }}</p>
          <button type="button" class="primary-btn" @click="load">Try again</button>
        </section>

        <template v-else>
          <!-- Needs attention -->
          <section v-if="data && attentionItems.length" class="attention">
            <span class="attention-label">Needs your attention</span>
            <router-link v-for="item in attentionItems" :key="item.key" :to="item.to" class="attention-chip" :class="item.tone">
              <span class="attention-count">{{ item.count }}</span>
              {{ item.label }}
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="9 18 15 12 9 6" /></svg>
            </router-link>
          </section>
          <section v-else-if="data" class="attention calm">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="20 6 9 17 4 12" /></svg>
            All caught up. No applications, reports, disputes or order issues waiting.
          </section>

          <!-- Headline KPIs -->
          <section class="kpi-row">
            <template v-if="!data">
              <div v-for="n in 4" :key="n" class="kpi-card">
                <div class="sk sk-line" style="width: 45%"></div>
                <div class="sk sk-value"></div>
                <div class="sk sk-line" style="width: 65%"></div>
              </div>
            </template>
            <template v-else>
              <router-link to="/admin/finance" class="kpi-card kpi-feature">
                <div class="kpi-top">
                  <span class="kpi-label">Total sales · {{ rangeLabel }}</span>
                  <span class="kpi-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18" /><polyline points="17 6 23 6 23 12" /></svg></span>
                </div>
                <span class="kpi-value">{{ money(k.total_sales) }}</span>
                <div class="kpi-footer">
                  <span v-if="delta(k.total_sales, k.total_sales_previous)" class="badge on-dark">{{ delta(k.total_sales, k.total_sales_previous).text }}</span>
                  <span class="kpi-note">{{ plural(k.paid_orders, "paid order") }} across all vendors</span>
                </div>
              </router-link>

              <router-link to="/admin/subscription-revenue" class="kpi-card">
                <div class="kpi-top">
                  <span class="kpi-label">Platform revenue</span>
                  <span class="kpi-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" /><line x1="1" y1="10" x2="23" y2="10" /></svg></span>
                </div>
                <span class="kpi-value">{{ money(k.platform_revenue) }}</span>
                <div class="kpi-footer">
                  <span v-if="delta(k.platform_revenue, k.platform_revenue_previous)" class="badge" :class="delta(k.platform_revenue, k.platform_revenue_previous).cls">
                    {{ delta(k.platform_revenue, k.platform_revenue_previous).text }}
                  </span>
                  <span class="kpi-note">{{ money(k.mrr) }} monthly recurring</span>
                </div>
              </router-link>

              <router-link to="/admin/orders" class="kpi-card">
                <div class="kpi-top">
                  <span class="kpi-label">Orders</span>
                  <span class="kpi-icon blue"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" /><line x1="3" y1="6" x2="21" y2="6" /><path d="M16 10a4 4 0 0 1-8 0" /></svg></span>
                </div>
                <span class="kpi-value">{{ k.orders }}</span>
                <div class="kpi-footer">
                  <span class="badge warn">{{ k.pending_orders }} pending</span>
                  <span class="kpi-note">{{ k.in_progress_orders }} in progress</span>
                </div>
              </router-link>

              <router-link to="/admin/subscription-revenue" class="kpi-card">
                <div class="kpi-top">
                  <span class="kpi-label">Active subscriptions</span>
                  <span class="kpi-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg></span>
                </div>
                <span class="kpi-value">{{ k.active_subscriptions }}</span>
                <div class="kpi-footer">
                  <span class="kpi-note">{{ k.paying_subscriptions }} paying · {{ k.trialing_subscriptions }} on free trial</span>
                </div>
              </router-link>
            </template>
          </section>

          <!-- People & averages -->
          <section class="metric-row">
            <template v-if="!data">
              <div v-for="n in 4" :key="n" class="metric-card">
                <div class="sk sk-avatar"></div>
                <div style="flex: 1"><div class="sk sk-line" style="width: 50%"></div><div class="sk sk-line" style="width: 30%"></div></div>
              </div>
            </template>
            <template v-else>
              <div class="metric-card">
                <span class="metric-icon violet"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg></span>
                <div>
                  <span class="metric-label">Registered customers</span>
                  <span class="metric-value">{{ k.customers }}</span>
                  <span class="metric-note">+{{ k.new_customers }} {{ rangeShort }}</span>
                </div>
              </div>
              <router-link to="/admin/vendor-requests" class="metric-card link">
                <span class="metric-icon pink"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" /></svg></span>
                <div>
                  <span class="metric-label">Vendors</span>
                  <span class="metric-value">{{ k.vendors }}</span>
                  <span class="metric-note">+{{ k.new_vendors }} {{ rangeShort }}</span>
                </div>
              </router-link>
              <div class="metric-card">
                <span class="metric-icon sky"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg></span>
                <div>
                  <span class="metric-label">Average order value</span>
                  <span class="metric-value">{{ money(k.average_order_value) }}</span>
                  <span class="metric-note">Paid orders · {{ rangeShort }}</span>
                </div>
              </div>
              <div class="metric-card">
                <span class="metric-icon lime"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12" /></svg></span>
                <div>
                  <span class="metric-label">Lifetime sales</span>
                  <span class="metric-value">{{ money(k.lifetime_sales) }}</span>
                  <span class="metric-note">Since the first order</span>
                </div>
              </div>
            </template>
          </section>

          <!-- Chart + insights -->
          <section class="split-row">
            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Performance</h2>
                  <p class="panel-sub">{{ seriesSubtitle }}</p>
                </div>
                <div class="seg" role="tablist" aria-label="Chart metric">
                  <button v-for="m in CHART_METRICS" :key="m.value" type="button" class="seg-btn" :class="{ active: chartMetric === m.value }" @click="chartMetric = m.value">
                    {{ m.label }}
                  </button>
                </div>
              </div>
              <div class="chart-wrap">
                <div v-if="!data" class="sk sk-block"></div>
                <canvas v-show="data" ref="chartCanvas"></canvas>
              </div>
            </div>

            <div class="panel insights">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Insights</h2>
                  <p class="panel-sub">What stands out · {{ rangeLabel }}</p>
                </div>
              </div>
              <template v-if="!data">
                <div v-for="n in 4" :key="n" class="insight"><div class="sk sk-line" style="width: 60%"></div><div class="sk sk-line"></div></div>
              </template>
              <p v-else-if="!data.insights.length" class="empty-msg">Not enough activity yet to say much.</p>
              <ul v-else class="insight-list">
                <li v-for="(insight, i) in data.insights" :key="i" class="insight" :class="insight.tone">
                  <span class="insight-icon">
                    <svg v-if="insight.tone === 'good'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18" /></svg>
                    <svg v-else-if="insight.tone === 'warn'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /><circle cx="12" cy="12" r="10" /></svg>
                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M9 18h6" /><path d="M10 22h4" /><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z" /></svg>
                  </span>
                  <div>
                    <span class="insight-title">{{ insight.title }}</span>
                    <span class="insight-detail">{{ insight.detail }}</span>
                  </div>
                </li>
              </ul>
            </div>
          </section>

          <!-- Leaders + status mix -->
          <section class="three-row">
            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Top products</h2>
                  <p class="panel-sub">By quantity sold · {{ rangeShort }}</p>
                </div>
              </div>
              <template v-if="!data"><div v-for="n in 4" :key="n" class="sk sk-line" style="margin-bottom: 14px"></div></template>
              <p v-else-if="!data.top_products.length" class="empty-msg">No products sold in this period</p>
              <ol v-else class="rank-list">
                <li v-for="(p, i) in data.top_products" :key="p.product_id ?? i">
                  <span class="rank">{{ i + 1 }}</span>
                  <div class="rank-main">
                    <span class="rank-name">{{ p.name }}</span>
                    <div class="rank-bar"><span :style="{ width: barWidth(p.quantity, data.top_products[0].quantity) }"></span></div>
                  </div>
                  <div class="rank-right">
                    <span class="strong">{{ p.quantity }} sold</span>
                    <span class="sub">{{ money(p.revenue) }}</span>
                  </div>
                </li>
              </ol>
            </div>

            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Top vendors</h2>
                  <p class="panel-sub">By paid sales · {{ rangeShort }}</p>
                </div>
              </div>
              <template v-if="!data"><div v-for="n in 4" :key="n" class="sk sk-line" style="margin-bottom: 14px"></div></template>
              <p v-else-if="!data.top_vendors.length" class="empty-msg">No vendor sales in this period</p>
              <ol v-else class="rank-list">
                <li v-for="(v, i) in data.top_vendors" :key="v.vendor_id">
                  <VendorAvatar :vendor="v.vendor" :size="30" />
                  <div class="rank-main">
                    <span class="rank-name">{{ v.vendor.store_name }}</span>
                    <div class="rank-bar"><span :style="{ width: barWidth(v.sales, data.top_vendors[0].sales) }"></span></div>
                  </div>
                  <div class="rank-right">
                    <span class="strong">{{ money(v.sales) }}</span>
                    <span class="sub">{{ plural(v.orders, "order") }}</span>
                  </div>
                </li>
              </ol>
            </div>

            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Order status</h2>
                  <p class="panel-sub">Orders placed · {{ rangeShort }}</p>
                </div>
              </div>
              <template v-if="!data"><div class="sk sk-donut"></div></template>
              <p v-else-if="!data.status_mix.length" class="empty-msg">No orders in this period</p>
              <template v-else>
                <div class="donut-wrap">
                  <canvas ref="statusCanvas"></canvas>
                  <div class="donut-center">
                    <span class="donut-value">{{ k.orders }}</span>
                    <span class="donut-caption">ORDERS</span>
                  </div>
                </div>
                <ul class="legend-list">
                  <li v-for="row in data.status_mix" :key="row.status">
                    <i :style="{ background: STATUS_COLORS[row.status] ?? '#94a3b8' }"></i>
                    <span class="legend-name">{{ STATUS_LABELS[row.status] ?? row.status }}</span>
                    <span class="legend-num">{{ row.count }}</span>
                  </li>
                </ul>
              </template>
            </div>
          </section>

          <!-- Recent activity -->
          <section class="split-row even">
            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Recent orders</h2>
                  <p class="panel-sub">Latest across all vendors</p>
                </div>
                <router-link to="/admin/orders" class="view-all">View all →</router-link>
              </div>
              <template v-if="!data">
                <div v-for="n in 5" :key="n" class="row-skeleton"><div class="sk sk-avatar"></div><div style="flex: 1"><div class="sk sk-line" style="width: 50%"></div><div class="sk sk-line" style="width: 30%"></div></div></div>
              </template>
              <p v-else-if="!data.recent_orders.length" class="empty-msg">No orders yet</p>
              <ul v-else class="activity-list">
                <li v-for="o in data.recent_orders" :key="o.id" tabindex="0" @click="openOrder(o.id)" @keydown.enter="openOrder(o.id)">
                  <VendorAvatar :vendor="o.vendor" :size="34" />
                  <div class="activity-main">
                    <span class="activity-title mono">{{ o.order_number }}</span>
                    <span class="sub">{{ o.customer || "Customer" }} → {{ o.vendor.store_name }} · {{ timeAgo(o.created_at) }}</span>
                  </div>
                  <div class="activity-right">
                    <span class="strong">{{ money(o.total_amount) }}</span>
                    <span class="pill" :class="`st-${o.status}`">{{ STATUS_LABELS[o.status] ?? o.status }}</span>
                  </div>
                </li>
              </ul>
            </div>

            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Recent transactions</h2>
                  <p class="panel-sub">Order and subscription payments</p>
                </div>
                <router-link to="/admin/finance" class="view-all">View all →</router-link>
              </div>
              <template v-if="!data">
                <div v-for="n in 5" :key="n" class="row-skeleton"><div class="sk sk-avatar"></div><div style="flex: 1"><div class="sk sk-line" style="width: 50%"></div><div class="sk sk-line" style="width: 30%"></div></div></div>
              </template>
              <p v-else-if="!data.recent_transactions.length" class="empty-msg">No payments yet</p>
              <ul v-else class="activity-list">
                <li v-for="t in data.recent_transactions" :key="t.key" tabindex="0" @click="openTransaction(t)" @keydown.enter="openTransaction(t)">
                  <span class="tx-icon" :class="t.source">
                    <svg v-if="t.source === 'subscription'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                    <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" /><line x1="1" y1="10" x2="23" y2="10" /></svg>
                  </span>
                  <div class="activity-main">
                    <span class="activity-title">{{ t.source === "subscription" ? `${t.vendor.store_name} subscription` : `${t.customer?.name || "Customer"} paid ${t.vendor.store_name}` }}</span>
                    <span class="sub">{{ t.method_label }} · {{ timeAgo(t.occurred_at) }}<template v-if="t.gateway_ref"> · {{ t.gateway_ref }}</template></span>
                  </div>
                  <div class="activity-right">
                    <span class="strong" :class="{ struck: t.state === 'voided' || t.state === 'failed' }">{{ money(t.amount) }}</span>
                    <span class="pill" :class="`ps-${t.state}`">{{ STATE_LABELS[t.state] }}</span>
                  </div>
                </li>
              </ul>
            </div>
          </section>
        </template>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { loadChartJs } from "../../utils/chartjs";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import { useSidebarState } from "../../composables/useSidebarState";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import VendorAvatar from "./components/VendorAvatar.vue";
import api from "../../plugins/axios";

const { toggleMobile } = useSidebarState();
const router = useRouter();

const RANGES = [
  { value: "7d", label: "7D", long: "last 7 days", short: "in 7 days" },
  { value: "30d", label: "30D", long: "last 30 days", short: "in 30 days" },
  { value: "90d", label: "90D", long: "last 90 days", short: "in 90 days" },
  { value: "12m", label: "12M", long: "last 12 months", short: "in 12 months" },
  { value: "ytd", label: "YTD", long: "this year", short: "this year" },
  { value: "all", label: "All", long: "all time", short: "all time" },
];
const CHART_METRICS = [
  { value: "revenue", label: "Revenue" },
  { value: "orders", label: "Orders" },
  { value: "new_customers", label: "Customers" },
];
const STATUS_LABELS = {
  pending: "Pending",
  processing: "Processing",
  out_for_delivery: "Out for delivery",
  completed: "Completed",
  cancelled: "Cancelled",
  failed: "Failed",
  returned: "Returned",
  refunded: "Refunded",
};
const STATUS_COLORS = {
  pending: "#f59e0b",
  processing: "#3b82f6",
  out_for_delivery: "#38bdf8",
  completed: "#22c55e",
  cancelled: "#cbd5e1",
  failed: "#f87171",
  returned: "#a78bfa",
  refunded: "#8b5cf6",
};
const STATE_LABELS = { successful: "Paid", pending: "Pending", failed: "Failed", refunded: "Refunded", voided: "Cancelled" };

const range = ref("30d");
const data = ref(null);
const loading = ref(false);
const loadError = ref("");
const chartMetric = ref("revenue");
const now = ref(Date.now());

const k = computed(() => data.value?.kpis ?? {});
const rangeLabel = computed(() => RANGES.find((r) => r.value === range.value)?.long ?? "");
const rangeShort = computed(() => RANGES.find((r) => r.value === range.value)?.short ?? "");
const greeting = computed(() => {
  const hour = new Date(now.value).getHours();
  return hour < 12 ? "Good morning" : hour < 18 ? "Good afternoon" : "Good evening";
});
const updatedLabel = computed(() => {
  const seconds = Math.max(0, Math.round((now.value - new Date(data.value.generated_at)) / 1000));
  if (seconds < 60) return "just now";
  const minutes = Math.round(seconds / 60);
  return minutes < 60 ? `${minutes} min ago` : `${Math.round(minutes / 60)} h ago`;
});
const seriesSubtitle = computed(() => {
  const unit = data.value?.series?.unit;
  return `${unit === "day" ? "Daily" : unit === "week" ? "Weekly" : "Monthly"} · ${rangeLabel.value}`;
});

const attentionItems = computed(() => {
  const a = data.value?.attention;
  if (!a) return [];
  return [
    { key: "apps", count: a.vendor_applications, label: "vendor applications", to: "/admin/vendor-requests", tone: "violet" },
    { key: "reports", count: a.product_reports, label: "product reports", to: "/admin/reviews?tab=reports", tone: "red" },
    { key: "disputes", count: a.open_disputes, label: "open disputes", to: "/admin/orders?tab=disputes", tone: "rose" },
    { key: "issues", count: a.order_issues, label: "orders need a look", to: "/admin/orders?tab=issues", tone: "amber" },
  ].filter((item) => item.count > 0);
});

let requestId = 0;
const load = async () => {
  const id = ++requestId;
  loading.value = true;
  loadError.value = "";
  try {
    const { data: response } = await api.get("/admin/dashboard", { params: { range: range.value } });
    if (id !== requestId) return;
    data.value = response.data;
    now.value = Date.now();
    await nextTick();
    await renderCharts();
  } catch (error) {
    if (id !== requestId) return;
    loadError.value = error.response?.data?.message || "Check your connection and try again.";
    if (data.value) toast.error("The dashboard couldn't be refreshed.");
  } finally {
    if (id === requestId) loading.value = false;
  }
};

const setRange = (value) => {
  if (range.value === value) return;
  range.value = value;
  load();
};

const openOrder = (id) => router.push({ path: "/admin/orders", query: { order: id } });
const openTransaction = (t) => (t.source === "order" ? openOrder(t.source_id) : router.push("/admin/subscription-revenue"));

// ── Charts ───────────────────────────────────────────────────────
const chartCanvas = ref(null);
const statusCanvas = ref(null);
let mainChart = null;
let statusChart = null;

const renderCharts = async () => {
  try {
    await loadChartJs();
  } catch {
    return; // the numbers still show without charts
  }
  renderMain();
  renderStatus();
};

const renderMain = () => {
  if (!chartCanvas.value || !data.value || !window.Chart) return;
  const points = data.value.series.points;
  const metric = chartMetric.value;
  const isMoney = metric === "revenue";
  mainChart?.destroy();

  const datasets = isMoney
    ? [
        { label: "Vendor sales", data: points.map((p) => p.sales), backgroundColor: "#7c6ff7", borderRadius: 5, maxBarThickness: 34, stack: "money" },
        { label: "Platform revenue", data: points.map((p) => p.platform_revenue), backgroundColor: "#22c55e", borderRadius: 5, maxBarThickness: 34, stack: "money" },
      ]
    : [
        {
          type: "line",
          label: metric === "orders" ? "Orders" : "New customers",
          data: points.map((p) => p[metric]),
          borderColor: "#7c6ff7",
          backgroundColor: "rgba(124, 111, 247, 0.12)",
          fill: true,
          tension: 0.35,
          pointRadius: 3,
          pointBackgroundColor: "#fff",
          pointBorderWidth: 2,
          borderWidth: 2,
        },
      ];

  mainChart = new window.Chart(chartCanvas.value, {
    type: "bar",
    data: { labels: points.map((p) => p.label), datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: "index", intersect: false },
      plugins: {
        legend: { display: isMoney, position: "bottom", labels: { boxWidth: 10, boxHeight: 10, color: "#64748b", font: { size: 11 } } },
        tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${isMoney ? money(c.raw) : c.raw}` } },
      },
      scales: {
        x: { stacked: isMoney, grid: { display: false }, ticks: { color: "#94a3b8", font: { size: 11 }, maxRotation: 0, autoSkipPadding: 12 } },
        y: {
          stacked: isMoney,
          beginAtZero: true,
          grid: { color: "#f1f5f9" },
          ticks: { color: "#94a3b8", font: { size: 11 }, precision: 0, callback: (v) => (isMoney ? moneyShort(v) : v) },
        },
      },
    },
  });
};

const renderStatus = () => {
  statusChart?.destroy();
  statusChart = null;
  if (!statusCanvas.value || !data.value?.status_mix.length || !window.Chart) return;
  const mix = data.value.status_mix;
  statusChart = new window.Chart(statusCanvas.value, {
    type: "doughnut",
    data: {
      labels: mix.map((r) => STATUS_LABELS[r.status] ?? r.status),
      datasets: [{ data: mix.map((r) => r.count), backgroundColor: mix.map((r) => STATUS_COLORS[r.status] ?? "#94a3b8"), borderWidth: 0, hoverOffset: 5 }],
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: "72%", plugins: { legend: { display: false } } },
  });
};

watch(chartMetric, renderMain);

// ── Formatting ───────────────────────────────────────────────────
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
const money = (v) => (v === null || v === undefined ? "—" : peso.format(Number(v)));
const moneyShort = (value) => {
  const n = Number(value) || 0;
  if (Math.abs(n) >= 1_000_000) return `₱${(n / 1_000_000).toFixed(1).replace(/\.0$/, "")}M`;
  if (Math.abs(n) >= 1_000) return `₱${(n / 1_000).toFixed(1).replace(/\.0$/, "")}K`;
  return `₱${n.toFixed(0)}`;
};
const plural = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;
const barWidth = (value, max) => `${max > 0 ? Math.max(4, Math.round((value / max) * 100)) : 0}%`;
const delta = (current, previous) => {
  if (previous === null || previous === undefined) return null;
  if (previous === 0) return current > 0 ? { text: "New", cls: "up" } : null;
  const pct = ((current - previous) / previous) * 100;
  if (Math.abs(pct) < 0.05) return { text: "0%", cls: "flat" };
  return { text: `${pct > 0 ? "▲" : "▼"} ${Math.abs(pct).toFixed(1)}%`, cls: pct > 0 ? "up" : "down" };
};
const timeAgo = (value) => {
  if (!value) return "";
  const minutes = Math.floor((Date.now() - new Date(value)) / 60_000);
  if (minutes < 1) return "just now";
  if (minutes < 60) return `${minutes} min ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} h ago`;
  const days = Math.floor(hours / 24);
  if (days < 31) return `${days} day${days === 1 ? "" : "s"} ago`;
  return new Date(value).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
};

let clock = null;
onMounted(() => {
  load();
  clock = setInterval(() => (now.value = Date.now()), 30_000);
});
onBeforeUnmount(() => {
  clearInterval(clock);
  mainChart?.destroy();
  statusChart?.destroy();
});
</script>

<style scoped>
* {
  font-family: "Poppins", "Segoe UI", sans-serif;
  box-sizing: border-box;
}

/* ── Layout ─────────────────────────────────────────── */
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: #f8fafc;
}
.main-content {
  margin-left: 240px;
  flex: 1;
  min-width: 0;
  min-height: 100vh;
  transition: margin-left 0.3s;
}
.page-body {
  padding: 0 28px 40px;
}
.page-body > section {
  margin-top: 18px;
}

/* ── Header ─────────────────────────────────────────── */
.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 20px 28px;
  background: #fff;
  border-bottom: 1px solid #e9edf3;
  position: sticky;
  top: 0;
  z-index: 100;
}
.header-left,
.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 4px;
}
.page-title {
  font-size: 22px;
  font-weight: 600;
  color: #1e2739;
  margin: 0;
  line-height: 1.2;
}
.page-subtitle {
  font-size: 12px;
  color: #94a3b8;
  margin: 2px 0 0;
}
.range-group,
.seg {
  display: inline-flex;
  padding: 3px;
  background: #f1f5f9;
  border-radius: 9px;
  gap: 2px;
}
.range-btn,
.seg-btn {
  border: none;
  background: transparent;
  padding: 6px 11px;
  border-radius: 7px;
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
}
.range-btn.active,
.seg-btn.active {
  background: #fff;
  color: #5b4fc9;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}
.ghost-btn,
.primary-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 13px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}
.ghost-btn {
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
}
.ghost-btn:hover:not(:disabled) {
  border-color: #c7c2fb;
  color: #5b4fc9;
}
.ghost-btn:disabled {
  opacity: 0.55;
  cursor: default;
}
.primary-btn {
  border: none;
  background: #7c6ff7;
  color: #fff;
}

/* ── Skeleton ───────────────────────────────────────── */
.sk {
  border-radius: 6px;
  background: linear-gradient(100deg, #eef1f6 30%, #f8fafc 50%, #eef1f6 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
.sk-line {
  height: 12px;
  margin: 6px 0;
}
.sk-value {
  height: 30px;
  width: 70%;
  margin: 14px 0 12px;
}
.sk-block {
  height: 100%;
  border-radius: 10px;
}
.sk-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  flex-shrink: 0;
}
.sk-donut {
  width: 150px;
  height: 150px;
  border-radius: 50%;
  margin: 10px auto;
}
.row-skeleton {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

/* ── Attention ──────────────────────────────────────── */
.attention {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  padding: 10px 14px;
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
}
.attention.calm {
  color: #15803d;
  font-size: 12.5px;
  font-weight: 500;
  background: #f0fdf4;
  border-color: #dcfce7;
}
.attention-label {
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  margin-right: 4px;
}
.attention-chip {
  --c: #7c6ff7;
  --bg: #f5f3ff;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 5px 10px 5px 5px;
  border-radius: 20px;
  background: var(--bg);
  color: #1e2739;
  font-size: 12.5px;
  text-decoration: none;
  transition: transform 0.15s;
}
.attention-chip:hover {
  transform: translateY(-1px);
}
.attention-chip svg {
  color: var(--c);
}
.attention-count {
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  border-radius: 11px;
  background: var(--c);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.attention-chip.red { --c: #dc2626; --bg: #fef2f2; }
.attention-chip.rose { --c: #e11d48; --bg: #fff1f2; }
.attention-chip.amber { --c: #d97706; --bg: #fffbeb; }

/* ── KPI cards ──────────────────────────────────────── */
.kpi-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}
.kpi-card {
  display: flex;
  flex-direction: column;
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 14px;
  padding: 18px 20px;
  text-decoration: none;
  min-width: 0;
  transition: box-shadow 0.2s, transform 0.2s;
}
a.kpi-card:hover {
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.07);
  transform: translateY(-2px);
}
.kpi-feature {
  background: radial-gradient(120% 140% at 100% 0%, #8f84ff 0%, #6d5ff0 45%, #4c3fd0 100%);
  border-color: transparent;
}
.kpi-feature .kpi-label,
.kpi-feature .kpi-note {
  color: rgba(255, 255, 255, 0.82);
}
.kpi-feature .kpi-value {
  color: #fff;
}
.kpi-feature .kpi-icon {
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
}
.kpi-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.kpi-label {
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
}
.kpi-icon {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #ede9fe;
  color: #7c6ff7;
  flex-shrink: 0;
}
.kpi-icon.green { background: #dcfce7; color: #16a34a; }
.kpi-icon.blue { background: #dbeafe; color: #2563eb; }
.kpi-icon.amber { background: #fef3c7; color: #d97706; }
.kpi-value {
  font-size: 27px;
  font-weight: 700;
  color: #1e2739;
  margin: 12px 0 10px;
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}
.kpi-footer {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.kpi-note {
  font-size: 11.5px;
  color: #94a3b8;
}
.badge {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 20px;
}
.badge.up { background: #dcfce7; color: #15803d; }
.badge.down { background: #fee2e2; color: #b91c1c; }
.badge.flat { background: #f1f5f9; color: #64748b; }
.badge.warn { background: #fef3c7; color: #b45309; }
.badge.on-dark { background: rgba(255, 255, 255, 0.2); color: #fff; }

/* ── Metric cards ───────────────────────────────────── */
.metric-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}
.metric-card {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 14px;
  padding: 14px 16px;
  text-decoration: none;
  min-width: 0;
}
.metric-card.link:hover {
  border-color: #c7c2fb;
}
.metric-card > div {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.metric-icon {
  width: 40px;
  height: 40px;
  border-radius: 11px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.metric-icon.violet { background: #ede9fe; color: #7c3aed; }
.metric-icon.pink { background: #fce7f3; color: #db2777; }
.metric-icon.sky { background: #e0f2fe; color: #0284c7; }
.metric-icon.lime { background: #ecfccb; color: #4d7c0f; }
.metric-label {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}
.metric-value {
  font-size: 19px;
  font-weight: 700;
  color: #1e2739;
  font-variant-numeric: tabular-nums;
  line-height: 1.3;
}
.metric-note {
  font-size: 11.5px;
  color: #94a3b8;
}

/* ── Panels ─────────────────────────────────────────── */
.split-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 16px;
}
.split-row.even {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.three-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}
.panel {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 14px;
  padding: 20px;
  min-width: 0;
}
.panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
.panel-title {
  font-size: 15px;
  font-weight: 600;
  color: #1e2739;
  margin: 0;
}
.panel-sub {
  font-size: 12px;
  color: #94a3b8;
  margin: 2px 0 0;
}
.view-all {
  font-size: 12px;
  font-weight: 500;
  color: #6d5ff0;
  text-decoration: none;
  white-space: nowrap;
}
.view-all:hover {
  text-decoration: underline;
}
.error-panel {
  text-align: center;
  padding: 48px 20px;
  margin-top: 18px;
}
.error-panel .primary-btn {
  margin-top: 16px;
}
.empty-msg {
  font-size: 13px;
  color: #94a3b8;
  text-align: center;
  padding: 28px 0;
  margin: 0;
}
.chart-wrap {
  position: relative;
  height: 300px;
}

/* ── Insights ───────────────────────────────────────── */
.insight-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.insight {
  --c: #6d5ff0;
  --bg: #f5f3ff;
  display: flex;
  gap: 11px;
  padding: 11px 12px;
  border-radius: 12px;
  background: #fbfbfe;
  border: 1px solid #f1f1f9;
}
.insight.good { --c: #16a34a; --bg: #dcfce7; }
.insight.warn { --c: #d97706; --bg: #fef3c7; }
.insight-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--bg);
  color: var(--c);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.insight > div {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.insight-title {
  font-size: 13px;
  font-weight: 600;
  color: #1e2739;
}
.insight-detail {
  font-size: 12px;
  color: #64748b;
  margin-top: 1px;
}

/* ── Rankings ───────────────────────────────────────── */
.rank-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.rank-list li {
  display: flex;
  align-items: center;
  gap: 11px;
}
.rank {
  width: 26px;
  height: 26px;
  border-radius: 8px;
  background: #f5f3ff;
  color: #6d5ff0;
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.rank-main {
  flex: 1;
  min-width: 0;
}
.rank-name {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: #1e2739;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rank-bar {
  height: 5px;
  margin-top: 6px;
  border-radius: 5px;
  background: #f1f5f9;
  overflow: hidden;
}
.rank-bar span {
  display: block;
  height: 100%;
  border-radius: 5px;
  background: linear-gradient(90deg, #a79ffb, #6d5ff0);
}
.rank-right,
.activity-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  text-align: right;
  font-size: 13px;
  white-space: nowrap;
  gap: 2px;
}
.strong {
  font-weight: 600;
  color: #1e2739;
  font-variant-numeric: tabular-nums;
}
.struck {
  color: #94a3b8;
  text-decoration: line-through;
}
.sub {
  display: block;
  font-size: 11.5px;
  color: #94a3b8;
}
.mono {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace !important;
  font-size: 12.5px;
}

/* Donut */
.donut-wrap {
  position: relative;
  height: 160px;
}
.donut-center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}
.donut-value {
  font-size: 22px;
  font-weight: 700;
  color: #1e2739;
}
.donut-caption {
  font-size: 10px;
  color: #94a3b8;
  letter-spacing: 0.1em;
}
.legend-list {
  list-style: none;
  padding: 0;
  margin: 16px 0 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px 14px;
}
.legend-list li {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12.5px;
}
.legend-list i {
  width: 9px;
  height: 9px;
  border-radius: 3px;
  flex-shrink: 0;
}
.legend-name {
  flex: 1;
  color: #475569;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.legend-num {
  font-weight: 600;
  color: #1e2739;
}

/* ── Activity ───────────────────────────────────────── */
.activity-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.activity-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 8px;
  margin: 0 -8px;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.12s;
}
.activity-list li + li {
  border-top: 1px solid #f4f6f9;
}
.activity-list li:hover,
.activity-list li:focus-visible {
  background: #fafaff;
  outline: none;
}
.activity-main {
  flex: 1;
  min-width: 0;
}
.activity-title {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: #1e2739;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.activity-main .sub {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.tx-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: #ede9fe;
  color: #6d5ff0;
}
.tx-icon.subscription {
  background: #dcfce7;
  color: #16a34a;
}
.pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 10.5px;
  font-weight: 600;
}
.st-pending { background: #fef3c7; color: #b45309; }
.st-processing { background: #dbeafe; color: #1d4ed8; }
.st-out_for_delivery { background: #e0f2fe; color: #0369a1; }
.st-completed { background: #dcfce7; color: #15803d; }
.st-cancelled,
.st-failed { background: #f1f5f9; color: #64748b; }
.st-returned,
.st-refunded { background: #ede9fe; color: #6d28d9; }
.ps-successful { background: #dcfce7; color: #15803d; }
.ps-pending { background: #fef3c7; color: #b45309; }
.ps-failed { background: #fee2e2; color: #b91c1c; }
.ps-refunded { background: #ede9fe; color: #6d28d9; }
.ps-voided { background: #f1f5f9; color: #64748b; }

/* ── Responsive ─────────────────────────────────────── */
@media (max-width: 1360px) {
  .three-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
  .kpi-row,
  .metric-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 1100px) {
  .split-row,
  .split-row.even,
  .three-row {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 968px) {
  .main-content {
    margin-left: 0;
  }
  .mobile-toggle {
    display: flex;
  }
}
@media (max-width: 640px) {
  .content-header {
    padding: 16px;
  }
  .page-body {
    padding: 0 16px 32px;
  }
  .kpi-row,
  .metric-row {
    grid-template-columns: minmax(0, 1fr);
  }
  .range-group {
    width: 100%;
    overflow-x: auto;
  }
  .chart-wrap {
    height: 240px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
  a.kpi-card:hover,
  .attention-chip:hover {
    transform: none;
  }
}
</style>
