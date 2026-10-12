<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <header class="content-header">
        <div class="header-left">
          <button class="mobile-toggle" @click="toggleMobile" aria-label="Toggle Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" /></svg>
          </button>
          <div>
            <h1 class="page-title">Reports &amp; Analytics</h1>
            <p class="page-subtitle">
              <template v-if="report">{{ report.period.label }} · compared with the {{ UNIT_LABEL[unit].toLowerCase() }} period before</template>
              <template v-else>Sales, best sellers, vendors, cancellations and subscriptions</template>
            </p>
          </div>
        </div>
        <div v-if="canExport" class="header-right">
          <button type="button" class="ghost-btn" :disabled="exporting.csv || !report" @click="download('csv')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
            {{ exporting.csv ? "Preparing…" : "CSV" }}
          </button>
          <button type="button" class="primary-btn" :disabled="exporting.pdf || !report" @click="download('pdf')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /></svg>
            {{ exporting.pdf ? "Building PDF…" : "PDF report" }}
          </button>
        </div>
      </header>

      <div class="page-body">
        <!-- Controls -->
        <section class="controls">
          <div class="seg" role="tablist" aria-label="Group by">
            <button v-for="u in UNITS" :key="u.value" type="button" role="tab" class="seg-btn" :class="{ active: unit === u.value }" :aria-selected="unit === u.value" @click="setUnit(u.value)">{{ u.label }}</button>
          </div>
          <div class="range">
            <label><span>From</span><input v-model="from" type="date" class="input" :max="to || undefined" /></label>
            <label><span>To</span><input v-model="to" type="date" class="input" :min="from || undefined" /></label>
            <button v-if="from || to" type="button" class="link-btn" @click="resetRange">Default range</button>
          </div>
        </section>

        <section v-if="error && !report" class="panel center">
          <h2 class="panel-title">The report couldn't be built</h2>
          <button type="button" class="primary-btn" @click="load">Try again</button>
        </section>

        <template v-else>
          <!-- KPIs -->
          <section class="kpi-grid">
            <template v-if="!report">
              <div v-for="n in 8" :key="n" class="kpi"><span class="sk" style="height: 11px; width: 50%"></span><span class="sk" style="height: 24px; width: 70%; margin-top: 10px"></span></div>
            </template>
            <div v-for="k in kpis" v-else :key="k.key" class="kpi" :class="{ feature: k.feature }">
              <span class="kpi-label">{{ k.label }}</span>
              <span class="kpi-value">{{ k.value }}</span>
              <span class="kpi-change" :class="k.tone">{{ k.change }}</span>
            </div>
          </section>

          <!-- Sales over time -->
          <section class="panel">
            <div class="panel-head">
              <div>
                <h2 class="panel-title">{{ UNIT_LABEL[unit] }} sales</h2>
                <p class="panel-sub">Paid orders by the day payment was confirmed</p>
              </div>
              <div class="seg small">
                <button v-for="m in SALES_METRICS" :key="m.value" type="button" class="seg-btn" :class="{ active: salesMetric === m.value }" @click="salesMetric = m.value">{{ m.label }}</button>
              </div>
            </div>
            <div class="chart-wrap tall">
              <div v-if="!report" class="sk fill"></div>
              <canvas v-show="report" ref="salesCanvas"></canvas>
            </div>
          </section>

          <!-- Best sellers -->
          <section class="split">
            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Best-selling flowers</h2>
                  <p class="panel-sub">By quantity sold in paid orders</p>
                </div>
              </div>
              <div v-if="!report" class="sk-list"><span v-for="n in 5" :key="n" class="sk sk-row"></span></div>
              <p v-else-if="!best.products.length" class="empty">No paid sales in this period.</p>
              <ol v-else class="rank-list">
                <li v-for="(p, i) in best.products" :key="p.product_id ?? p.name">
                  <span class="rank">{{ i + 1 }}</span>
                  <div class="rank-main">
                    <span class="strong">{{ p.name }}</span>
                    <span class="sub">{{ p.vendor }} · {{ p.category }}</span>
                    <div class="bar-track"><span :style="{ width: barPct(p.quantity, best.products[0].quantity) }"></span></div>
                  </div>
                  <div class="rank-right">
                    <span class="strong">{{ p.quantity }} sold</span>
                    <span class="sub">{{ money(p.revenue) }} · {{ p.share }}%</span>
                  </div>
                </li>
              </ol>
            </div>
            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Best-selling categories</h2>
                  <p class="panel-sub">Share of product revenue</p>
                </div>
                <div class="seg small">
                  <button v-for="g in GROUPINGS" :key="g.value" type="button" class="seg-btn" :class="{ active: grouping === g.value }" @click="grouping = g.value">{{ g.label }}</button>
                </div>
              </div>
              <div v-if="!report" class="sk donut"></div>
              <p v-else-if="!best[grouping].length" class="empty">{{ grouping === "occasions" ? "No sold products were tagged with occasions." : "Nothing sold in this period." }}</p>
              <template v-else>
                <div class="donut-wrap"><canvas ref="mixCanvas"></canvas></div>
                <ul class="legend">
                  <li v-for="(row, i) in best[grouping]" :key="row.value">
                    <i :style="{ background: PALETTE[i % PALETTE.length] }"></i>
                    <span class="legend-name">{{ row.label }}</span>
                    <span class="sub-inline">{{ row.quantity }} sold</span>
                    <span class="strong">{{ row.share }}%</span>
                  </li>
                </ul>
              </template>
            </div>
          </section>

          <!-- Vendor comparison -->
          <section class="panel">
            <div class="panel-head">
              <div>
                <h2 class="panel-title">Vendor performance</h2>
                <p class="panel-sub">Rates are out of finished orders. Growth compares sales with the previous period.</p>
              </div>
            </div>
            <div v-if="!report" class="sk-list"><span v-for="n in 3" :key="n" class="sk sk-row"></span></div>
            <p v-else-if="!sortedVendors.length" class="empty">No vendor activity in this period.</p>
            <template v-else>
              <div v-if="sortedVendors.length > 1" class="chart-wrap short"><canvas ref="vendorCanvas"></canvas></div>
              <div class="table-scroll">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Vendor</th>
                      <th v-for="c in VENDOR_COLUMNS" :key="c.key" class="num sortable" :class="{ sorted: vendorSort.key === c.key }" @click="sortVendors(c.key)">
                        {{ c.label }} <span v-if="vendorSort.key === c.key">{{ vendorSort.desc ? "↓" : "↑" }}</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="v in sortedVendors" :key="v.vendor.id">
                      <td>
                        <div class="vendor-cell">
                          <VendorAvatar :vendor="v.vendor" :size="28" />
                          <span class="strong">{{ v.vendor.store_name }}</span>
                        </div>
                      </td>
                      <td class="num strong">{{ money(v.sales) }}<div class="share"><span :style="{ width: v.share + '%' }"></span></div></td>
                      <td class="num"><span v-if="v.growth !== null" class="chip" :class="v.growth >= 0 ? 'up' : 'down'">{{ v.growth >= 0 ? "▲" : "▼" }} {{ Math.abs(v.growth) }}%</span><span v-else class="sub-inline">—</span></td>
                      <td class="num">{{ v.paid_orders }}<span class="sub">of {{ v.orders_placed }} placed</span></td>
                      <td class="num">{{ money(v.average_order) }}</td>
                      <td class="num" :class="rateTone(v.completion_rate, true)">{{ pct(v.completion_rate) }}</td>
                      <td class="num" :class="rateTone(v.cancellation_rate)">{{ pct(v.cancellation_rate) }}</td>
                      <td class="num" :class="rateTone(v.refund_rate)">{{ pct(v.refund_rate) }}</td>
                      <td class="num">{{ hours(v.avg_fulfilment_hours) }}</td>
                      <td class="num">{{ v.rating === null ? "—" : `★ ${v.rating}` }}<span v-if="v.reviews" class="sub">{{ v.reviews }} reviews</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
          </section>

          <!-- Cancellations & refunds -->
          <section class="split">
            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Cancellation &amp; refund rates</h2>
                  <p class="panel-sub">Out of orders placed in each {{ unit }} that have finished</p>
                </div>
              </div>
              <div class="chart-wrap"><div v-if="!report" class="sk fill"></div><canvas v-show="report" ref="rateCanvas"></canvas></div>
            </div>
            <div class="panel">
              <h2 class="panel-title">Where orders fall through</h2>
              <div v-if="!report" class="sk-list"><span v-for="n in 3" :key="n" class="sk sk-row"></span></div>
              <template v-else>
                <div class="mini-stats">
                  <div><span class="sub">Finished orders</span><span class="strong big">{{ cancel.finished }}</span></div>
                  <div><span class="sub">Cancelled</span><span class="strong big down">{{ cancel.cancelled }}</span></div>
                  <div><span class="sub">Refunded / returned</span><span class="strong big warn">{{ cancel.refunded }}</span></div>
                </div>
                <table class="data-table compact">
                  <thead><tr><th>Payment method</th><th class="num">Orders</th><th class="num">Cancelled</th><th class="num">Rate</th></tr></thead>
                  <tbody>
                    <tr v-for="m in cancel.by_method" :key="m.method">
                      <td>{{ METHOD[m.method] ?? m.method }}</td><td class="num">{{ m.orders }}</td><td class="num">{{ m.cancelled }}</td><td class="num" :class="rateTone(m.cancellation_rate)">{{ pct(m.cancellation_rate) }}</td>
                    </tr>
                  </tbody>
                </table>
                <p class="sub note">
                  Return / refund requests: {{ cancel.requests.returns + cancel.requests.refunds }}
                  ({{ cancel.requests.approved }} approved, {{ cancel.requests.rejected }} rejected, {{ cancel.requests.open }} open)
                </p>
              </template>
            </div>
          </section>

          <!-- Subscriptions -->
          <section class="panel">
            <div class="panel-head">
              <div>
                <h2 class="panel-title">Subscription revenue &amp; growth</h2>
                <p class="panel-sub">What vendors paid BloomCraft, and new paying vendors</p>
              </div>
              <router-link to="/admin/subscription-revenue" class="link-btn">Subscription details →</router-link>
            </div>
            <div v-if="!report" class="sk-list"><span class="sk sk-row tall"></span></div>
            <template v-else>
              <div class="sub-kpis">
                <div><span class="sub">Revenue</span><span class="strong big">{{ money(subs.revenue) }}</span><span class="kpi-change" :class="growthTone(subs.growth)">{{ subs.growth === null ? "No earlier revenue" : `${subs.growth >= 0 ? "▲" : "▼"} ${Math.abs(subs.growth)}% vs ${money(subs.previous)}` }}</span></div>
                <div><span class="sub">Monthly recurring</span><span class="strong big">{{ money(subs.mrr) }}</span><span class="sub">{{ money(subs.arr) }} a year</span></div>
                <div><span class="sub">Paying vendors</span><span class="strong big">{{ subs.paying_vendors }}</span><span class="sub">+{{ subs.new_paying_vendors }} new this period</span></div>
                <div><span class="sub">By plan</span>
                  <span v-for="p in subs.by_plan" :key="p.plan" class="plan-row">{{ p.plan }} <b>{{ money(p.revenue) }}</b></span>
                  <span v-if="!subs.by_plan.length" class="sub">No payments</span>
                </div>
              </div>
              <div class="chart-wrap"><canvas ref="subCanvas"></canvas></div>
            </template>
          </section>
        </template>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import { useSidebarState } from "../../composables/useSidebarState";
import { useAdminAccess } from "../../composables/useAdminAccess";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import VendorAvatar from "./components/VendorAvatar.vue";
import api from "../../plugins/axios";

const { toggleMobile } = useSidebarState();
const route = useRoute();
const router = useRouter();
const adminAccess = useAdminAccess();
const canExport = computed(() => adminAccess.can("reports.export"));

const UNITS = [
  { value: "day", label: "Daily" },
  { value: "week", label: "Weekly" },
  { value: "month", label: "Monthly" },
  { value: "year", label: "Yearly" },
];
const UNIT_LABEL = { day: "Daily", week: "Weekly", month: "Monthly", year: "Yearly" };
const SALES_METRICS = [
  { value: "sales", label: "Sales" },
  { value: "paid_orders", label: "Orders" },
  { value: "average_order", label: "Avg order" },
];
const GROUPINGS = [
  { value: "categories", label: "Categories" },
  { value: "flower_types", label: "Flower types" },
  { value: "occasions", label: "Occasions" },
];
const VENDOR_COLUMNS = [
  { key: "sales", label: "Sales" },
  { key: "growth", label: "Growth" },
  { key: "paid_orders", label: "Orders" },
  { key: "average_order", label: "Avg order" },
  { key: "completion_rate", label: "Completed" },
  { key: "cancellation_rate", label: "Cancelled" },
  { key: "refund_rate", label: "Refunded" },
  { key: "avg_fulfilment_hours", label: "Fulfilment" },
  { key: "rating", label: "Rating" },
];
const METHOD = { cod: "Cash on delivery", ewallet: "E-wallet", bank_transfer: "Bank transfer", gcash: "GCash", maya: "Maya", card: "Card" };
const PALETTE = ["#7c6ff7", "#22c55e", "#f59e0b", "#38bdf8", "#f43f5e", "#a78bfa", "#14b8a6", "#fb923c", "#94a3b8", "#e879f9"];

const unit = ref(UNITS.some((u) => u.value === route.query.unit) ? route.query.unit : "day");
const from = ref(typeof route.query.from === "string" ? route.query.from : "");
const to = ref(typeof route.query.to === "string" ? route.query.to : "");
const report = ref(null);
const error = ref(false);
const salesMetric = ref("sales");
const grouping = ref("categories");
const vendorSort = reactive({ key: "sales", desc: true });
const exporting = reactive({ csv: false, pdf: false });

const best = computed(() => report.value?.best_sellers ?? { products: [], categories: [], flower_types: [], occasions: [] });
const cancel = computed(() => report.value?.cancellations);
const subs = computed(() => report.value?.subscriptions);
const sortedVendors = computed(() => {
  const list = [...(report.value?.vendors ?? [])];
  const k = vendorSort.key;
  return list.sort((a, b) => {
    const x = a[k] ?? -Infinity;
    const y = b[k] ?? -Infinity;
    return vendorSort.desc ? y - x : x - y;
  });
});

// ── KPIs ─────────────────────────────────────────────────────────
const change = (now, before, { lowerIsBetter = false, isRate = false } = {}) => {
  if (before === null || before === undefined) return { text: "No earlier data", tone: "flat" };
  if (isRate) {
    const diff = Math.round((now - before) * 10) / 10;
    if (!diff) return { text: "No change", tone: "flat" };
    const good = lowerIsBetter ? diff < 0 : diff > 0;
    return { text: `${diff > 0 ? "▲" : "▼"} ${Math.abs(diff)} pts`, tone: good ? "up" : "down" };
  }
  if (!before) return now ? { text: "New this period", tone: "up" } : { text: "No change", tone: "flat" };
  const p = ((now - before) / before) * 100;
  if (Math.abs(p) < 0.05) return { text: "No change", tone: "flat" };
  const good = lowerIsBetter ? p < 0 : p > 0;
  return { text: `${p > 0 ? "▲" : "▼"} ${Math.abs(p).toFixed(1)}%`, tone: good ? "up" : "down" };
};
const kpis = computed(() => {
  const s = report.value?.summary;
  if (!s) return [];
  const row = (key, label, value, now, before, opts = {}) => {
    const c = change(now, before, opts);
    return { key, label, value, change: c.text, tone: c.tone, feature: opts.feature };
  };
  return [
    row("sales", "Sales", money(s.sales), s.sales, s.sales_previous, { feature: true }),
    row("paid", "Paid orders", s.paid_orders, s.paid_orders, s.paid_orders_previous),
    row("aov", "Average order", money(s.average_order), s.average_order, s.average_order_previous),
    row("placed", "Orders placed", s.orders_placed, s.orders_placed, s.orders_placed_previous),
    row("cancel", "Cancellation rate", pct(s.cancellation_rate), s.cancellation_rate ?? 0, s.cancellation_rate_previous, { lowerIsBetter: true, isRate: true }),
    row("refund", "Refund / return rate", pct(s.refund_rate), s.refund_rate ?? 0, s.refund_rate_previous, { lowerIsBetter: true, isRate: true }),
    row("subs", "Subscription revenue", money(s.subscription_revenue), s.subscription_revenue, s.subscription_revenue_previous),
    row("customers", "New customers", s.new_customers, s.new_customers, s.new_customers_previous),
  ];
});

// ── Loading ──────────────────────────────────────────────────────
const params = () => ({ unit: unit.value, from: from.value || undefined, to: to.value || undefined });
let requestId = 0;
const load = async () => {
  const id = ++requestId;
  error.value = false;
  try {
    const { data } = await api.get("/admin/analytics", { params: params() });
    if (id !== requestId) return;
    report.value = data.data;
    await nextTick();
    renderCharts();
  } catch {
    if (id !== requestId) return;
    error.value = true;
    if (report.value) toast.error("The report couldn't be refreshed.");
  }
};

const setUnit = (value) => {
  unit.value = value;
  from.value = "";
  to.value = "";
};
const resetRange = () => {
  from.value = "";
  to.value = "";
};
let timer = null;
watch([unit, from, to], () => {
  router.replace({ query: { ...route.query, ...params() } });
  clearTimeout(timer);
  timer = setTimeout(load, 250);
});

// ── Export ───────────────────────────────────────────────────────
const download = async (format) => {
  exporting[format] = true;
  try {
    const response = await api.get("/admin/analytics/export", { params: { ...params(), format }, responseType: "blob" });
    const name =
      (response.headers?.["content-disposition"] || "").match(/filename="?([^";]+)"?/)?.[1] ||
      `bloomcraft-report-${unit.value}-${report.value?.period.from}-to-${report.value?.period.to}.${format}`;
    const href = URL.createObjectURL(new Blob([response.data], { type: format === "pdf" ? "application/pdf" : "text/csv" }));
    const link = document.createElement("a");
    link.href = href;
    link.download = name;
    link.click();
    URL.revokeObjectURL(href);
  } catch {
    toast.error(`The ${format.toUpperCase()} couldn't be created.`);
  } finally {
    exporting[format] = false;
  }
};

// ── Charts ───────────────────────────────────────────────────────
const salesCanvas = ref(null);
const mixCanvas = ref(null);
const vendorCanvas = ref(null);
const rateCanvas = ref(null);
const subCanvas = ref(null);
const charts = {};

const loadChartJs = () =>
  new Promise((resolve, reject) => {
    if (window.Chart) return resolve();
    const existing = document.querySelector("script[data-chartjs]");
    const script = existing || document.createElement("script");
    script.addEventListener("load", resolve);
    script.addEventListener("error", reject);
    if (!existing) {
      script.src = "https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js";
      script.dataset.chartjs = "true";
      document.head.appendChild(script);
    }
  });

const draw = (key, canvas, config) => {
  charts[key]?.destroy();
  charts[key] = null;
  if (canvas && window.Chart) charts[key] = new window.Chart(canvas, config);
};
const axis = (format) => ({
  grid: { color: "#f1f5f9" },
  beginAtZero: true,
  ticks: { color: "#94a3b8", font: { size: 11 }, callback: format },
});
const xAxis = { grid: { display: false }, ticks: { color: "#94a3b8", font: { size: 11 }, maxRotation: 0, autoSkipPadding: 12 } };

const renderSales = () => {
  if (!report.value) return;
  const points = report.value.series;
  const metric = salesMetric.value;
  const isMoney = metric !== "paid_orders";
  draw("sales", salesCanvas.value, {
    type: "bar",
    data: {
      labels: points.map((p) => p.label),
      datasets: [
        { label: SALES_METRICS.find((m) => m.value === metric).label, data: points.map((p) => p[metric]), backgroundColor: "#7c6ff7", borderRadius: 4, maxBarThickness: 32 },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${isMoney ? money(c.raw) : c.raw}` } } },
      scales: { x: xAxis, y: axis((v) => (isMoney ? short(v) : v)) },
    },
  });
};
const renderMix = () => {
  const rows = best.value[grouping.value] ?? [];
  draw("mix", rows.length ? mixCanvas.value : null, {
    type: "doughnut",
    data: { labels: rows.map((r) => r.label), datasets: [{ data: rows.map((r) => r.revenue), backgroundColor: rows.map((_, i) => PALETTE[i % PALETTE.length]), borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: "68%", plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ` ${c.label}: ${money(c.raw)}` } } } },
  });
};
const renderVendors = () => {
  const list = (report.value?.vendors ?? []).slice(0, 10);
  draw("vendors", list.length > 1 ? vendorCanvas.value : null, {
    type: "bar",
    data: { labels: list.map((v) => v.vendor.store_name), datasets: [{ label: "Sales", data: list.map((v) => v.sales), backgroundColor: list.map((_, i) => PALETTE[i % PALETTE.length]), borderRadius: 4 }] },
    options: {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ` ${money(c.raw)}` } } },
      scales: { x: axis((v) => short(v)), y: { grid: { display: false }, ticks: { color: "#475569", font: { size: 11 } } } },
    },
  });
};
const renderRates = () => {
  if (!report.value) return;
  const points = report.value.series;
  draw("rates", rateCanvas.value, {
    type: "line",
    data: {
      labels: points.map((p) => p.label),
      datasets: [
        { label: "Cancellation %", data: points.map((p) => p.cancellation_rate), borderColor: "#f43f5e", backgroundColor: "#f43f5e", spanGaps: true, tension: 0.3, pointRadius: 3 },
        { label: "Refund / return %", data: points.map((p) => p.refund_rate), borderColor: "#f59e0b", backgroundColor: "#f59e0b", spanGaps: true, tension: 0.3, pointRadius: 3 },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: "index", intersect: false },
      plugins: { legend: { position: "bottom", labels: { boxWidth: 10, boxHeight: 10, color: "#64748b", font: { size: 11 } } } },
      scales: { x: xAxis, y: { ...axis((v) => `${v}%`), suggestedMax: 100 } },
    },
  });
};
const renderSubs = () => {
  if (!report.value) return;
  const points = report.value.series;
  draw("subs", subCanvas.value, {
    type: "bar",
    data: {
      labels: points.map((p) => p.label),
      datasets: [
        { label: "Subscription revenue", data: points.map((p) => p.subscription_revenue), backgroundColor: "#22c55e", borderRadius: 4, maxBarThickness: 32, yAxisID: "y", order: 2 },
        { type: "line", label: "New paying vendors", data: points.map((p) => p.new_paying_vendors), borderColor: "#7c6ff7", backgroundColor: "#7c6ff7", tension: 0.3, pointRadius: 3, yAxisID: "y1", order: 1 },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: "index", intersect: false },
      plugins: {
        legend: { position: "bottom", labels: { boxWidth: 10, boxHeight: 10, color: "#64748b", font: { size: 11 } } },
        tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${c.datasetIndex === 0 ? money(c.raw) : c.raw}` } },
      },
      scales: { x: xAxis, y: axis((v) => short(v)), y1: { position: "right", beginAtZero: true, grid: { display: false }, ticks: { precision: 0, color: "#a78bfa" } } },
    },
  });
};
const renderCharts = async () => {
  try {
    await loadChartJs();
  } catch {
    return; // tables still show without charts
  }
  renderSales();
  renderMix();
  renderVendors();
  renderRates();
  renderSubs();
};
watch(salesMetric, renderSales);
watch(grouping, async () => {
  await nextTick();
  renderMix();
});

const sortVendors = (key) => {
  if (vendorSort.key === key) vendorSort.desc = !vendorSort.desc;
  else Object.assign(vendorSort, { key, desc: true });
};

// ── Formatting ───────────────────────────────────────────────────
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
const money = (v) => peso.format(Number(v || 0));
const short = (value) => {
  const n = Number(value) || 0;
  if (n >= 1_000_000) return `₱${(n / 1_000_000).toFixed(1).replace(/\.0$/, "")}M`;
  if (n >= 1_000) return `₱${(n / 1_000).toFixed(1).replace(/\.0$/, "")}K`;
  return `₱${n.toFixed(0)}`;
};
const pct = (v) => (v === null || v === undefined ? "—" : `${v}%`);
const hours = (h) => (h === null || h === undefined ? "—" : h < 48 ? `${Math.round(h)} h` : `${(h / 24).toFixed(1)} d`);
const barPct = (v, max) => `${max ? Math.max(4, Math.round((v / max) * 100)) : 0}%`;
const rateTone = (v, higherIsBetter = false) => {
  if (v === null || v === undefined) return "";
  if (higherIsBetter) return v >= 80 ? "good" : v < 50 ? "bad" : "";
  return v >= 30 ? "bad" : v >= 15 ? "warn" : "";
};
const growthTone = (g) => (g === null ? "flat" : g >= 0 ? "up" : "down");

onMounted(() => {
  adminAccess.load().catch(() => {});
  load();
});
onBeforeUnmount(() => {
  clearTimeout(timer);
  Object.values(charts).forEach((c) => c?.destroy());
});
</script>

<style scoped>
* {
  font-family: "Poppins", "Segoe UI", sans-serif;
  box-sizing: border-box;
}
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
}
.page-body {
  padding: 18px 28px 40px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
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
.ghost-btn,
.primary-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 9px;
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
.primary-btn {
  border: none;
  background: #7c6ff7;
  color: #fff;
}
.primary-btn:hover:not(:disabled) {
  background: #5b4fc9;
}
.ghost-btn:disabled,
.primary-btn:disabled {
  opacity: 0.55;
  cursor: default;
}
.link-btn {
  border: none;
  background: none;
  color: #6d5ff0;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  text-decoration: none;
}

/* Controls */
.controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.seg {
  display: inline-flex;
  padding: 3px;
  background: #eef1f6;
  border-radius: 10px;
  gap: 2px;
}
.seg.small {
  background: #f1f5f9;
}
.seg-btn {
  border: none;
  background: transparent;
  padding: 7px 14px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
}
.seg.small .seg-btn {
  padding: 5px 10px;
  font-size: 12px;
}
.seg-btn.active {
  background: #fff;
  color: #5b4fc9;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}
.range {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.range label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #64748b;
}
.input {
  padding: 7px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  font-size: 12.5px;
  color: #1e2739;
  background: #fff;
}
.input:focus {
  outline: none;
  border-color: #7c6ff7;
}

/* Skeletons */
.sk {
  display: block;
  border-radius: 8px;
  background: linear-gradient(100deg, #eef1f6 30%, #f8fafc 50%, #eef1f6 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
.sk.fill {
  height: 100%;
}
.sk.donut {
  width: 170px;
  height: 170px;
  border-radius: 50%;
  margin: 10px auto;
}
.sk-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.sk-row {
  height: 46px;
}
.sk-row.tall {
  height: 200px;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

/* KPIs */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}
.kpi {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid #e9edf3;
  background: #fff;
  min-width: 0;
}
.kpi.feature {
  background: radial-gradient(120% 140% at 100% 0%, #8f84ff 0%, #6d5ff0 45%, #4c3fd0 100%);
  border-color: transparent;
}
.kpi.feature .kpi-label,
.kpi.feature .kpi-value,
.kpi.feature .kpi-change {
  color: #fff !important;
}
.kpi-label {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}
.kpi-value {
  font-size: 22px;
  font-weight: 700;
  color: #1e2739;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}
.kpi-change {
  font-size: 11.5px;
  font-weight: 600;
}
.up { color: #15803d; }
.down { color: #b91c1c; }
.flat { color: #94a3b8; }
.warn { color: #b45309; }

/* Panels */
.panel {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 14px;
  padding: 18px 20px;
  min-width: 0;
}
.panel.center {
  text-align: center;
  padding: 40px;
}
.panel-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.panel-title {
  font-size: 15px;
  font-weight: 600;
  color: #1e2739;
  margin: 0 0 4px;
}
.panel-sub {
  font-size: 12px;
  color: #94a3b8;
  margin: 0;
}
.split {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  align-items: start;
}
.empty {
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
  padding: 26px 0;
  margin: 0;
}
.chart-wrap {
  position: relative;
  height: 240px;
}
.chart-wrap.tall {
  height: 300px;
}
.chart-wrap.short {
  height: 180px;
  margin-bottom: 14px;
}
.donut-wrap {
  position: relative;
  height: 180px;
}
.strong {
  font-weight: 600;
  color: #1e2739;
  font-size: 13px;
}
.strong.big {
  font-size: 20px;
}
.sub {
  display: block;
  font-size: 11.5px;
  color: #94a3b8;
  font-weight: 400;
}
.sub.note {
  margin-top: 12px;
}
.sub-inline {
  font-size: 11.5px;
  color: #94a3b8;
}

/* Rankings */
.rank-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.rank-list li {
  display: flex;
  align-items: center;
  gap: 12px;
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
.rank-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  white-space: nowrap;
}
.bar-track {
  height: 5px;
  margin-top: 6px;
  border-radius: 5px;
  background: #f1f5f9;
  overflow: hidden;
}
.bar-track span {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, #a79ffb, #6d5ff0);
  border-radius: 5px;
}
.legend {
  list-style: none;
  margin: 14px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.legend li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}
.legend i {
  width: 10px;
  height: 10px;
  border-radius: 3px;
  flex-shrink: 0;
}
.legend-name {
  flex: 1;
  color: #475569;
}

/* Tables */
.table-scroll {
  overflow-x: auto;
  margin: 0 -6px;
}
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.data-table th {
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 9px 10px;
  background: #f8fafc;
  white-space: nowrap;
}
.data-table th.sortable {
  cursor: pointer;
  user-select: none;
}
.data-table th.sorted {
  color: #5b4fc9;
}
.data-table td {
  padding: 10px;
  border-bottom: 1px solid #f1f5f9;
  white-space: nowrap;
  vertical-align: middle;
  color: #1e2739;
}
.data-table.compact td {
  padding: 7px 10px;
}
.num {
  text-align: right !important;
  font-variant-numeric: tabular-nums;
}
td.good { color: #15803d; font-weight: 600; }
td.bad { color: #b91c1c; font-weight: 600; }
td.warn { color: #b45309; font-weight: 600; }
.vendor-cell {
  display: flex;
  align-items: center;
  gap: 9px;
}
.share {
  height: 4px;
  margin-top: 4px;
  border-radius: 4px;
  background: #f1f5f9;
  overflow: hidden;
  margin-left: auto;
  width: 90px;
}
.share span {
  display: block;
  height: 100%;
  background: #7c6ff7;
}
.chip {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
}
.chip.up { background: #dcfce7; }
.chip.down { background: #fee2e2; }

/* Cancellations / subscriptions */
.mini-stats,
.sub-kpis {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 10px 0 14px;
}
.sub-kpis {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.mini-stats > div,
.sub-kpis > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px;
  border: 1px solid #eef1f6;
  border-radius: 12px;
}
.plan-row {
  font-size: 12.5px;
  color: #475569;
}
.plan-row b {
  color: #1e2739;
}

@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
  .kpi-grid,
  .sub-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 1100px) {
  .split {
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
    padding: 14px 16px 32px;
  }
  .kpi-grid,
  .mini-stats,
  .sub-kpis {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
}
</style>
