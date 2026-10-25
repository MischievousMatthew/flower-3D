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
            <h1 class="page-title">Transaction Management</h1>
            <p class="page-subtitle">
              PayMongo and cash-on-delivery payments, vendor sales and platform earnings
              <template v-if="overview">· updated {{ updatedLabel }}</template>
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
          <button type="button" class="ghost-btn" :disabled="loading" @click="refresh">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" /></svg>
            <span>{{ loading ? "Refreshing" : "Refresh" }}</span>
          </button>
          <button type="button" class="primary-btn" :disabled="exporting.report" @click="exportReport">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="12" y1="18" x2="12" y2="12" /><polyline points="9 15 12 18 15 15" /></svg>
            <span>{{ exporting.report ? "Preparing…" : "Export report" }}</span>
          </button>
        </div>
      </header>

      <div class="page-body">
        <div class="trust-row">
          <span class="trust-chip">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
            View only. Payments, refunds and balances can't be changed here.
          </span>
          <span class="trust-chip">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>
            Payment references are masked
          </span>
        </div>

        <section v-if="loadError && !overview" class="panel error-panel">
          <h2 class="panel-title">The finance figures couldn't be loaded</h2>
          <p class="panel-sub">{{ loadError }}</p>
          <button type="button" class="primary-btn" @click="refresh">Try again</button>
        </section>

        <template v-else>
          <!-- Headline -->
          <section class="kpi-row">
            <template v-if="!overview">
              <div v-for="n in 4" :key="n" class="kpi-card">
                <div class="sk sk-line" style="width: 45%"></div>
                <div class="sk sk-value"></div>
                <div class="sk sk-line" style="width: 65%"></div>
              </div>
            </template>
            <template v-else>
              <div class="kpi-card kpi-feature">
                <span class="kpi-label">Total processed · {{ rangeLabel }}</span>
                <span class="kpi-value">{{ money(k.total_processed) }}</span>
                <span class="kpi-note">Vendor sales + platform earnings</span>
              </div>

              <div class="kpi-card">
                <div class="kpi-top">
                  <span class="kpi-label">Vendor sales</span>
                  <span class="dot-key" style="--c: #7c6ff7"></span>
                </div>
                <span class="kpi-value">{{ money(k.vendor_sales) }}</span>
                <div class="kpi-footer">
                  <span v-if="change(k.vendor_sales, k.previous?.vendor_sales)" class="badge" :class="change(k.vendor_sales, k.previous?.vendor_sales).cls">
                    {{ change(k.vendor_sales, k.previous?.vendor_sales).text }}
                  </span>
                  <span class="kpi-note">{{ plural(k.vendor_sales_orders, "paid order") }} · {{ money(k.vendor_sales_net) }} after refunds</span>
                </div>
              </div>

              <div class="kpi-card">
                <div class="kpi-top">
                  <span class="kpi-label">Platform earnings</span>
                  <span class="dot-key" style="--c: #22c55e"></span>
                </div>
                <span class="kpi-value">{{ money(k.platform_earnings) }}</span>
                <div class="kpi-footer">
                  <span v-if="change(k.platform_earnings, k.previous?.platform_earnings)" class="badge" :class="change(k.platform_earnings, k.previous?.platform_earnings).cls">
                    {{ change(k.platform_earnings, k.previous?.platform_earnings).text }}
                  </span>
                  <span class="kpi-note">{{ plural(k.platform_payments, "subscription payment") }}</span>
                </div>
              </div>

              <div class="kpi-card">
                <div class="kpi-top">
                  <span class="kpi-label">Refunds</span>
                  <span class="dot-key" style="--c: #f43f5e"></span>
                </div>
                <span class="kpi-value">{{ money(k.refunds) }}</span>
                <div class="kpi-footer">
                  <span class="kpi-note">{{ plural(k.refund_count, "refund") }} · {{ plural(k.open_disputes, "open dispute") }}</span>
                  <router-link to="/admin/refunds" class="kpi-link">Refund Management →</router-link>
                </div>
              </div>
            </template>
          </section>

          <!-- Payment health -->
          <section class="metric-row">
            <template v-if="!overview">
              <div v-for="n in 5" :key="n" class="metric-card">
                <div class="sk sk-line" style="width: 55%"></div>
                <div class="sk sk-line" style="width: 35%; height: 18px"></div>
              </div>
            </template>
            <template v-else>
              <div class="metric-card">
                <span class="metric-label">Payment success rate</span>
                <span class="metric-value">{{ k.success_rate === null ? "—" : k.success_rate + "%" }}</span>
                <span class="metric-note">Successful vs failed · {{ rangeLabel }}</span>
              </div>
              <div class="metric-card">
                <span class="metric-label">Cash to collect</span>
                <span class="metric-value">{{ money(k.pending_cod.amount) }}</span>
                <span class="metric-note">{{ plural(k.pending_cod.count, "COD order") }} not yet paid</span>
              </div>
              <div class="metric-card">
                <span class="metric-label">Awaiting PayMongo</span>
                <span class="metric-value">{{ money(k.pending_paymongo.amount) }}</span>
                <span class="metric-note">{{ plural(k.pending_paymongo.count, "online payment") }} not completed</span>
              </div>
              <div class="metric-card">
                <span class="metric-label">Held in vendor balances</span>
                <span class="metric-value">{{ money(k.vendor_balances) }}</span>
                <span class="metric-note">Owed to vendors right now</span>
              </div>
              <router-link class="metric-card link" to="/admin/refunds">
                <span class="metric-label">Open disputes</span>
                <span class="metric-value">{{ k.open_disputes }}</span>
                <span class="metric-note">Review in Orders →</span>
              </router-link>
            </template>
          </section>

          <!-- Charts -->
          <section class="split-row">
            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Money in</h2>
                  <p class="panel-sub">{{ seriesSubtitle }}</p>
                </div>
                <div class="legend-inline">
                  <span><i style="background: #7c6ff7"></i>Vendor sales</span>
                  <span><i style="background: #22c55e"></i>Platform earnings</span>
                  <span><i class="line" style="background: #f43f5e"></i>Refunds</span>
                </div>
              </div>
              <div class="chart-wrap">
                <div v-if="!overview" class="sk sk-block"></div>
                <canvas v-show="overview" ref="flowCanvas"></canvas>
              </div>
              <p class="footnote">
                Vendors keep the full amount of their sales; BloomCraft earns only from vendor subscriptions.
              </p>
            </div>

            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">How customers paid</h2>
                  <p class="panel-sub">Paid vendor sales · {{ rangeLabel }}</p>
                </div>
              </div>
              <template v-if="!overview">
                <div class="sk sk-donut"></div>
              </template>
              <template v-else-if="!overview.methods.length">
                <p class="empty-msg">No paid orders in this period</p>
              </template>
              <template v-else>
                <div class="donut-wrap">
                  <canvas ref="methodCanvas"></canvas>
                  <div class="donut-center">
                    <span class="donut-value">{{ moneyShort(k.vendor_sales) }}</span>
                    <span class="donut-caption">SALES</span>
                  </div>
                </div>
                <div class="split-bar" aria-hidden="true">
                  <span :style="{ width: share(k.cod_sales, k.vendor_sales) + '%', background: '#f59e0b' }"></span>
                  <span :style="{ width: share(k.paymongo_sales, k.vendor_sales) + '%', background: '#7c6ff7' }"></span>
                </div>
                <div class="split-legend">
                  <span><i style="background: #f59e0b"></i>Cash on delivery <b>{{ money(k.cod_sales) }}</b></span>
                  <span><i style="background: #7c6ff7"></i>PayMongo <b>{{ money(k.paymongo_sales) }}</b></span>
                </div>
                <ul class="legend-list">
                  <li v-for="(m, i) in overview.methods" :key="m.method">
                    <i :style="{ background: METHOD_COLORS[i % METHOD_COLORS.length] }"></i>
                    <span class="legend-name">{{ METHOD_LABELS[m.method] ?? m.method }}</span>
                    <span class="legend-meta">{{ m.count }}</span>
                    <span class="legend-num">{{ money(m.amount) }}</span>
                  </li>
                </ul>
              </template>
            </div>
          </section>

          <!-- Payment outcomes -->
          <section class="panel">
            <div class="panel-head">
              <div>
                <h2 class="panel-title">Payment outcomes</h2>
                <p class="panel-sub">Every payment started · {{ rangeLabel }}</p>
              </div>
            </div>
            <div class="state-grid">
              <template v-if="!overview">
                <div v-for="n in 5" :key="n" class="state-card"><div class="sk sk-line"></div><div class="sk sk-line" style="width: 50%"></div></div>
              </template>
              <button
                v-for="s in overview?.states ?? []"
                v-else
                :key="s.state"
                type="button"
                class="state-card"
                :class="`state-${s.state}`"
                @click="showState(s.state)"
              >
                <span class="state-top"><span class="pill" :class="`ps-${s.state}`">{{ STATE_LABELS[s.state] }}</span></span>
                <span class="state-count">{{ s.count }}</span>
                <span class="state-amount">{{ money(s.amount) }}</span>
              </button>
            </div>
          </section>

          <!-- Tabs -->
          <section class="panel" ref="tabsPanel">
            <div class="tabs-head">
              <div class="tabs" role="tablist">
                <button v-for="t in TABS" :key="t.key" type="button" role="tab" class="tab" :class="{ active: tab === t.key }" :aria-selected="tab === t.key" @click="tab = t.key">
                  {{ t.label }}
                  <span v-if="t.key === 'transactions' && meta.total" class="tab-count">{{ meta.total }}</span>
                </button>
              </div>
              <button v-if="tab === 'transactions'" type="button" class="ghost-btn" :disabled="exporting.transactions || !meta.total" @click="exportTransactions">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
                <span>{{ exporting.transactions ? "Exporting…" : "Export CSV" }}</span>
              </button>
            </div>

            <!-- Transactions -->
            <template v-if="tab === 'transactions'">
              <div class="toolbar">
                <div class="search-wrap">
                  <svg class="search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
                  <input v-model="tx.search" type="search" class="search-input" placeholder="Reference, vendor or customer" aria-label="Search payments" />
                </div>
                <div class="toolbar-filters">
                  <select v-model="tx.source" class="select" aria-label="Type">
                    <option value="">All types</option>
                    <option value="order">Vendor sales</option>
                    <option value="subscription">Subscriptions</option>
                  </select>
                  <select v-model="tx.channel" class="select" aria-label="Channel">
                    <option value="">COD & PayMongo</option>
                    <option value="cod">Cash on delivery</option>
                    <option value="paymongo">PayMongo</option>
                  </select>
                  <select v-model="tx.state" class="select" aria-label="Status">
                    <option value="">Any status</option>
                    <option v-for="(label, value) in STATE_LABELS" :key="value" :value="value">{{ label }}</option>
                  </select>
                  <label class="date-field"><span>From</span><input v-model="tx.from" type="date" class="select" :max="tx.to || undefined" /></label>
                  <label class="date-field"><span>To</span><input v-model="tx.to" type="date" class="select" :min="tx.from || undefined" /></label>
                  <select v-model="tx.sort" class="select" aria-label="Sort">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="amount_high">Highest amount</option>
                    <option value="amount_low">Lowest amount</option>
                  </select>
                  <button v-if="txFiltered" type="button" class="link-btn" @click="clearTx">Clear</button>
                </div>
              </div>

              <div class="table-scroll">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Payment</th>
                      <th>Vendor</th>
                      <th>Customer</th>
                      <th>Method</th>
                      <th>Status</th>
                      <th class="num">Amount</th>
                      <th>Gateway ref.</th>
                    </tr>
                  </thead>
                  <tbody v-if="txLoading && !rows.length">
                    <tr v-for="n in 8" :key="n"><td v-for="c in 8" :key="c"><div class="sk sk-line"></div></td></tr>
                  </tbody>
                  <tbody v-else-if="txError && !rows.length">
                    <tr><td colspan="8" class="empty-cell">The payments couldn't be loaded. <button type="button" class="link-btn" @click="txList.refresh()">Try again</button></td></tr>
                  </tbody>
                  <tbody v-else-if="!rows.length">
                    <tr><td colspan="8" class="empty-cell">{{ txFiltered ? "No payments match these filters." : "No payments yet." }}</td></tr>
                  </tbody>
                  <tbody v-else :class="{ dim: txLoading }">
                    <tr v-for="row in txList.rows.value" :key="row.key" class="click-row" tabindex="0" @click="openSource(row)" @keydown.enter="openSource(row)">
                      <td class="muted">{{ formatDateTime(row.occurred_at) }}</td>
                      <td>
                        <span class="type-chip" :class="row.source">{{ row.source === "subscription" ? "Subscription" : "Vendor sale" }}</span>
                        <span class="mono sub">{{ row.reference }}</span>
                      </td>
                      <td>
                        <div class="vendor-cell">
                          <VendorAvatar :vendor="row.vendor" :size="28" />
                          <span class="cell-main">{{ row.vendor.store_name }}</span>
                        </div>
                      </td>
                      <td>
                        <template v-if="row.customer">
                          <span class="cell-main">{{ row.customer.name || "—" }}</span>
                          <span class="sub">{{ row.customer.email }}</span>
                        </template>
                        <span v-else class="sub">BloomCraft</span>
                      </td>
                      <td>
                        <span class="cell-main">{{ row.method_label }}</span>
                        <span class="sub">{{ row.channel === "cod" ? "Cash" : "PayMongo" }}</span>
                      </td>
                      <td><span class="pill" :class="`ps-${row.state}`">{{ STATE_LABELS[row.state] }}</span></td>
                      <td class="num strong" :class="{ struck: row.state === 'voided' || row.state === 'failed' }">{{ money(row.amount) }}</td>
                      <td>
                        <span v-if="row.gateway_ref" class="masked" title="Masked for security">
                          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                          {{ row.gateway_ref }}
                        </span>
                        <span v-else class="sub">—</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <ShowMore v-if="rows.length" tone="admin" :has-more="txList.hasMore.value" :loading="txList.loadingMore.value" :shown="txList.rows.value.length" :total="txList.total.value" @more="txList.showMore()" />
            </template>

            <!-- Vendor sales -->
            <template v-else-if="tab === 'vendors'">
              <p class="panel-sub tab-intro">Sales each vendor received · {{ rangeLabel }}. Balances and amounts awaiting payment are as of now.</p>
              <div class="table-scroll">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Vendor</th>
                      <th class="num">Paid orders</th>
                      <th class="num">Gross sales</th>
                      <th>COD / PayMongo</th>
                      <th class="num">Refunds</th>
                      <th class="num">Net sales</th>
                      <th class="num">Awaiting payment</th>
                      <th class="num">Paid BloomCraft</th>
                      <th class="num">Balance</th>
                    </tr>
                  </thead>
                  <tbody v-if="vendorsLoading && !vendorRows.length">
                    <tr v-for="n in 3" :key="n"><td v-for="c in 9" :key="c"><div class="sk sk-line"></div></td></tr>
                  </tbody>
                  <tbody v-else-if="!vendorRows.length">
                    <tr><td colspan="9" class="empty-cell">No vendors yet.</td></tr>
                  </tbody>
                  <tbody v-else :class="{ dim: vendorsLoading }">
                    <tr v-for="v in vendorRows" :key="v.vendor.id">
                      <td>
                        <div class="vendor-cell">
                          <VendorAvatar :vendor="v.vendor" :size="30" />
                          <div>
                            <span class="cell-main">{{ v.vendor.store_name }}</span>
                            <span class="sub">{{ v.vendor.email }}</span>
                          </div>
                        </div>
                      </td>
                      <td class="num">{{ v.orders }}</td>
                      <td class="num strong">{{ money(v.gross) }}</td>
                      <td>
                        <div class="mini-split" :title="`COD ${money(v.cod)} · PayMongo ${money(v.paymongo)}`">
                          <span :style="{ width: share(v.cod, v.gross) + '%', background: '#f59e0b' }"></span>
                          <span :style="{ width: share(v.paymongo, v.gross) + '%', background: '#7c6ff7' }"></span>
                        </div>
                        <span class="sub">{{ v.gross ? `${share(v.cod, v.gross)}% cash` : "—" }}</span>
                      </td>
                      <td class="num" :class="{ neg: v.refunds }">{{ v.refunds ? "−" + money(v.refunds) : money(0) }}</td>
                      <td class="num strong">{{ money(v.net) }}</td>
                      <td class="num muted">{{ money(v.pending) }}</td>
                      <td class="num earn">{{ money(v.subscription_paid) }}</td>
                      <td class="num">{{ money(v.balance) }}</td>
                    </tr>
                  </tbody>
                  <tfoot v-if="vendorRows.length > 1">
                    <tr>
                      <td>Total</td>
                      <td class="num">{{ vendorTotals.orders }}</td>
                      <td class="num">{{ money(vendorTotals.gross) }}</td>
                      <td></td>
                      <td class="num">{{ money(vendorTotals.refunds) }}</td>
                      <td class="num">{{ money(vendorTotals.net) }}</td>
                      <td class="num">{{ money(vendorTotals.pending) }}</td>
                      <td class="num">{{ money(vendorTotals.subscription_paid) }}</td>
                      <td class="num">{{ money(vendorTotals.balance) }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
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
import { toast } from "@/utils/snackbar";
import { useSidebarState } from "../../composables/useSidebarState";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import VendorAvatar from "./components/VendorAvatar.vue";
import api from "../../plugins/axios";
import ShowMore from "../../components/common/ShowMore.vue";
import { useShowMoreRows } from "../../composables/useShowMore";

const { toggleMobile } = useSidebarState();
const route = useRoute();
const router = useRouter();

const RANGES = [
  { value: "7d", label: "7D", long: "last 7 days" },
  { value: "30d", label: "30D", long: "last 30 days" },
  { value: "90d", label: "90D", long: "last 90 days" },
  { value: "12m", label: "12M", long: "last 12 months" },
  { value: "ytd", label: "YTD", long: "this year" },
  { value: "all", label: "All", long: "all time" },
];
const TABS = [
  { key: "transactions", label: "Transactions" },
  { key: "vendors", label: "Vendor sales" },
];
const STATE_LABELS = {
  successful: "Successful",
  pending: "Pending",
  failed: "Failed",
  refunded: "Refunded",
  voided: "Cancelled",
};
const METHOD_LABELS = { cod: "Cash on delivery", ewallet: "E-wallet", bank_transfer: "Bank transfer", gcash: "GCash", maya: "Maya", card: "Card" };
const METHOD_COLORS = ["#f59e0b", "#7c6ff7", "#38bdf8", "#22c55e", "#f43f5e", "#94a3b8"];

// ── Overview ─────────────────────────────────────────────────────
const range = ref("30d");
const overview = ref(null);
const loading = ref(false);
const loadError = ref("");
const now = ref(Date.now());
const k = computed(() => overview.value?.kpis ?? {});
const rangeLabel = computed(() => RANGES.find((r) => r.value === range.value)?.long ?? "");

const updatedLabel = computed(() => {
  const seconds = Math.max(0, Math.round((now.value - new Date(overview.value.generated_at)) / 1000));
  if (seconds < 60) return "just now";
  const minutes = Math.round(seconds / 60);
  return minutes < 60 ? `${minutes} min ago` : `${Math.round(minutes / 60)} h ago`;
});
const seriesSubtitle = computed(() => {
  const unit = overview.value?.series?.unit;
  return `${unit === "day" ? "Daily" : unit === "week" ? "Weekly" : "Monthly"} totals · ${rangeLabel.value}`;
});

let overviewRequest = 0;
const fetchOverview = async () => {
  const id = ++overviewRequest;
  loading.value = true;
  loadError.value = "";
  try {
    const { data } = await api.get("/admin/finance/overview", { params: { range: range.value } });
    if (id !== overviewRequest) return;
    overview.value = data.data;
    now.value = Date.now();
    await nextTick();
    await renderCharts();
  } catch (error) {
    if (id !== overviewRequest) return;
    loadError.value = error.response?.data?.message || "Check your connection and try again.";
    if (overview.value) toast.error("The finance figures couldn't be refreshed.");
  } finally {
    if (id === overviewRequest) loading.value = false;
  }
};

const setRange = (value) => {
  if (range.value === value) return;
  range.value = value;
  fetchOverview();
  if (tab.value === "vendors" || vendorsLoaded) fetchVendors();
};

const refresh = () => {
  fetchOverview();
  txList.refresh();
  if (vendorsLoaded) fetchVendors();
};

// ── Charts ───────────────────────────────────────────────────────
const flowCanvas = ref(null);
const methodCanvas = ref(null);
let flowChart = null;
let methodChart = null;

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

const renderCharts = async () => {
  try {
    await loadChartJs();
  } catch {
    return; // the figures still show without the charts
  }
  renderFlow();
  renderMethods();
};

const renderFlow = () => {
  if (!flowCanvas.value || !overview.value) return;
  const points = overview.value.series.points;
  flowChart?.destroy();
  flowChart = new window.Chart(flowCanvas.value, {
    type: "bar",
    data: {
      labels: points.map((p) => p.label),
      datasets: [
        { label: "Vendor sales", data: points.map((p) => p.vendor_sales), backgroundColor: "#7c6ff7", borderRadius: 4, maxBarThickness: 30, stack: "in", order: 2 },
        { label: "Platform earnings", data: points.map((p) => p.platform_earnings), backgroundColor: "#22c55e", borderRadius: 4, maxBarThickness: 30, stack: "in", order: 2 },
        {
          type: "line",
          label: "Refunds",
          data: points.map((p) => p.refunds),
          borderColor: "#f43f5e",
          backgroundColor: "#f43f5e",
          pointRadius: 0,
          pointHoverRadius: 4,
          borderWidth: 2,
          tension: 0.3,
          order: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: "index", intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${money(c.raw)}` } },
      },
      scales: {
        x: { stacked: true, grid: { display: false }, ticks: { color: "#94a3b8", font: { size: 11 }, maxRotation: 0, autoSkipPadding: 12 } },
        y: { stacked: true, beginAtZero: true, grid: { color: "#f1f5f9" }, ticks: { color: "#94a3b8", font: { size: 11 }, callback: (v) => moneyShort(v) } },
      },
    },
  });
};

const renderMethods = () => {
  methodChart?.destroy();
  methodChart = null;
  if (!methodCanvas.value || !overview.value?.methods.length) return;
  const methods = overview.value.methods;
  methodChart = new window.Chart(methodCanvas.value, {
    type: "doughnut",
    data: {
      labels: methods.map((m) => METHOD_LABELS[m.method] ?? m.method),
      datasets: [{ data: methods.map((m) => m.amount), backgroundColor: methods.map((_, i) => METHOD_COLORS[i % METHOD_COLORS.length]), borderWidth: 0, hoverOffset: 6 }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "74%",
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ` ${c.label}: ${money(c.raw)}` } } },
    },
  });
};

// ── Tabs ─────────────────────────────────────────────────────────
const tab = ref("transactions");
const tabsPanel = ref(null);

// Transactions
const blankTx = () => ({ search: "", source: "", channel: "", state: "", from: "", to: "", sort: "newest" });
const tx = reactive(blankTx());
const rows = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const txLoading = ref(false);
const txError = ref(false);
const txFiltered = computed(() => Boolean(tx.search.trim() || tx.source || tx.channel || tx.state || tx.from || tx.to));
const txParams = () => Object.fromEntries(Object.entries({ ...tx, search: tx.search.trim() }).filter(([, v]) => v !== ""));

let txRequest = 0;
const fetchTransactions = async (page = 1) => {
  const id = ++txRequest;
  txLoading.value = true;
  txError.value = false;
  try {
    const { data } = await api.get("/admin/finance/transactions", { params: { ...txParams(), page, per_page: 15 } });
    if (id !== txRequest) return;
    rows.value = page > 1 ? [...rows.value, ...(data.data ?? [])] : data.data;
    meta.value = data.meta;
  } catch {
    if (id !== txRequest) return;
    txError.value = true;
    if (rows.value.length) toast.error("The payments couldn't be refreshed.");
  } finally {
    if (id === txRequest) txLoading.value = false;
  }
};
// 10 rows first, then 10 more, then 20 at a time
const txList = useShowMoreRows(rows, meta, fetchTransactions);

let txTimer = null;
watch(
  () => ({ ...tx }),
  (next, prev) => {
    clearTimeout(txTimer);
    txTimer = setTimeout(() => txList.load(), next.search !== prev.search ? 300 : 0);
  },
);
const clearTx = () => Object.assign(tx, blankTx());

const showState = async (state) => {
  Object.assign(tx, { ...blankTx(), state });
  tab.value = "transactions";
  await nextTick();
  tabsPanel.value?.scrollIntoView({ behavior: "smooth", block: "start" });
};

const openSource = (row) => {
  if (row.source === "order") goToOrder(row.source_id);
  else router.push("/admin/subscription-revenue");
};
const goToOrder = (id, orderTab) =>
  router.push({ path: "/admin/orders", query: { order: id, ...(orderTab ? { tab: orderTab } : {}) } });

// Vendor sales
const vendorRows = ref([]);
const vendorsLoading = ref(false);
let vendorsLoaded = false;
const vendorTotals = computed(() =>
  vendorRows.value.reduce(
    (t, v) => {
      for (const key of ["orders", "gross", "refunds", "net", "pending", "subscription_paid", "balance"]) t[key] += Number(v[key]) || 0;
      return t;
    },
    { orders: 0, gross: 0, refunds: 0, net: 0, pending: 0, subscription_paid: 0, balance: 0 },
  ),
);
const fetchVendors = async () => {
  vendorsLoading.value = true;
  try {
    const { data } = await api.get("/admin/finance/vendors", { params: { range: range.value } });
    vendorRows.value = data.data;
    vendorsLoaded = true;
  } catch {
    toast.error("Vendor sales couldn't be loaded.");
  } finally {
    vendorsLoading.value = false;
  }
};

watch(tab, (value) => {
  if (value === "vendors" && !vendorsLoaded) fetchVendors();
});

// ── Exports ──────────────────────────────────────────────────────
const exporting = reactive({ report: false, transactions: false });

const download = async (url, params, fallbackName, key) => {
  exporting[key] = true;
  try {
    const response = await api.get(url, { params, responseType: "blob" });
    const disposition = response.headers?.["content-disposition"] || "";
    const name = disposition.match(/filename="?([^";]+)"?/)?.[1] || fallbackName;
    const href = URL.createObjectURL(new Blob([response.data], { type: "text/csv" }));
    const link = document.createElement("a");
    link.href = href;
    link.download = name;
    link.click();
    URL.revokeObjectURL(href);
  } catch {
    toast.error("The export couldn't be created. Try again.");
  } finally {
    exporting[key] = false;
  }
};
const today = () => new Date().toISOString().slice(0, 10);
const exportReport = () =>
  download("/admin/finance/report/export", { range: range.value }, `bloomcraft-financial-report-${range.value}-${today()}.csv`, "report");
const exportTransactions = () =>
  download("/admin/finance/transactions/export", txParams(), `bloomcraft-transactions-${today()}.csv`, "transactions");

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
const share = (part, whole) => (Number(whole) > 0 ? Math.round((Number(part) / Number(whole)) * 100) : 0);
const humanize = (s) => (s ? String(s).replace(/_/g, " ").replace(/^\w/, (c) => c.toUpperCase()) : "—");
const change = (current, previous) => {
  if (previous === null || previous === undefined) return null;
  if (previous === 0) return current > 0 ? { text: "New", cls: "up" } : null;
  const pct = ((current - previous) / previous) * 100;
  if (Math.abs(pct) < 0.05) return { text: "0%", cls: "flat" };
  return { text: `${pct > 0 ? "▲" : "▼"} ${Math.abs(pct).toFixed(1)}%`, cls: pct > 0 ? "up" : "down" };
};
const formatDate = (v) => (v ? new Date(v).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—");
const formatDateTime = (v) =>
  v ? new Date(v).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "—";
const timeAgo = (v) => {
  const days = Math.floor((Date.now() - new Date(v)) / 86_400_000);
  if (days < 1) return "since today";
  if (days < 31) return `${days} day${days === 1 ? "" : "s"}`;
  const months = Math.floor(days / 30);
  return `${months} month${months === 1 ? "" : "s"}`;
};

// ── Lifecycle ────────────────────────────────────────────────────
let clock = null;
onMounted(() => {
  // Refunds moved to their own page
  if (route.query.tab === "refunds") return router.replace("/admin/refunds");
  if (route.query.tab === "vendors") tab.value = "vendors";
  fetchOverview();
  txList.load();
  clock = setInterval(() => (now.value = Date.now()), 30_000);
});
onBeforeUnmount(() => {
  clearInterval(clock);
  clearTimeout(txTimer);
  flowChart?.destroy();
  methodChart?.destroy();
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

/* ── Buttons ────────────────────────────────────────── */
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
  transition: background 0.15s, border-color 0.15s, color 0.15s;
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
  padding: 4px 6px;
}
.link-btn:hover {
  text-decoration: underline;
}

/* ── Trust chips ────────────────────────────────────── */
.trust-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 18px;
}
.trust-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 11px;
  border-radius: 20px;
  background: #fff;
  border: 1px solid #e9edf3;
  color: #475569;
  font-size: 12px;
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
.sk-donut {
  width: 170px;
  height: 170px;
  border-radius: 50%;
  margin: 10px auto;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

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
  min-width: 0;
}
.kpi-feature {
  background: radial-gradient(120% 140% at 100% 0%, #8f84ff 0%, #6d5ff0 45%, #4c3fd0 100%);
  border-color: transparent;
  color: #fff;
}
.kpi-feature .kpi-label,
.kpi-feature .kpi-note {
  color: rgba(255, 255, 255, 0.82);
}
.kpi-feature .kpi-value {
  color: #fff;
}
.kpi-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.kpi-label {
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
}
.dot-key {
  width: 10px;
  height: 10px;
  border-radius: 3px;
  background: var(--c);
}
.kpi-value {
  font-size: 26px;
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

/* ── Metric cards ───────────────────────────────────── */
.metric-row {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 14px;
}
.metric-card {
  display: flex;
  flex-direction: column;
  gap: 3px;
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 14px 16px;
  text-decoration: none;
  min-width: 0;
}
.metric-card.link {
  transition: border-color 0.15s, box-shadow 0.15s;
}
.metric-card.link:hover {
  border-color: #c7c2fb;
  box-shadow: 0 4px 16px rgba(124, 111, 247, 0.12);
}
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
}
.metric-note {
  font-size: 11.5px;
  color: #94a3b8;
}
.metric-card.link .metric-note {
  color: #6d5ff0;
}

/* ── Panels ─────────────────────────────────────────── */
.split-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
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
.error-panel {
  text-align: center;
  padding: 48px 20px;
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
.empty-msg.small {
  padding: 14px 0;
}
.footnote {
  margin: 12px 0 0;
  font-size: 11.5px;
  color: #94a3b8;
}

/* ── Charts ─────────────────────────────────────────── */
.chart-wrap {
  position: relative;
  height: 280px;
}
.legend-inline {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
  font-size: 12px;
  color: #64748b;
}
.legend-inline span,
.split-legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.legend-inline i,
.split-legend i,
.legend-list i {
  width: 10px;
  height: 10px;
  border-radius: 3px;
  display: inline-block;
  flex-shrink: 0;
}
.legend-inline i.line {
  height: 3px;
  width: 14px;
  border-radius: 2px;
}
.donut-wrap {
  position: relative;
  height: 180px;
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
  font-size: 19px;
  font-weight: 700;
  color: #1e2739;
}
.donut-caption {
  font-size: 10.5px;
  color: #94a3b8;
  letter-spacing: 0.1em;
}
.split-bar,
.mini-split {
  display: flex;
  height: 8px;
  border-radius: 8px;
  overflow: hidden;
  background: #f1f5f9;
  margin-top: 16px;
}
.mini-split {
  width: 110px;
  height: 6px;
  margin: 0 0 4px;
}
.split-legend {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 8px;
  font-size: 12px;
  color: #64748b;
}
.split-legend b {
  color: #1e2739;
  font-weight: 600;
}
.legend-list {
  list-style: none;
  padding: 12px 0 0;
  margin: 12px 0 0;
  border-top: 1px solid #f1f5f9;
  display: flex;
  flex-direction: column;
  gap: 9px;
}
.legend-list li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}
.legend-name {
  flex: 1;
  color: #475569;
}
.legend-meta {
  color: #94a3b8;
  font-size: 12px;
}
.legend-num {
  font-weight: 600;
  color: #1e2739;
  font-variant-numeric: tabular-nums;
}

/* ── Payment outcomes ───────────────────────────────── */
.state-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
}
.state-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 14px;
  border-radius: 12px;
  border: 1px solid #eef1f6;
  background: #fbfcfe;
  cursor: pointer;
  text-align: left;
  transition: border-color 0.15s, transform 0.15s;
}
.state-card:hover {
  border-color: #c7c2fb;
  transform: translateY(-1px);
}
.state-count {
  font-size: 22px;
  font-weight: 700;
  color: #1e2739;
  margin-top: 8px;
}
.state-amount {
  font-size: 12px;
  color: #64748b;
  font-variant-numeric: tabular-nums;
}

/* ── Tabs & toolbar ─────────────────────────────────── */
.tabs-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.tabs {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}
.tab {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  border: none;
  background: transparent;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
}
.tab.active {
  background: #ede9fe;
  color: #5b4fc9;
}
.tab-count {
  font-size: 11px;
  font-weight: 600;
  background: #fff;
  border-radius: 20px;
  padding: 1px 7px;
}
.tab-count.warn {
  background: #ffe4e6;
  color: #be123c;
}
.tab-intro {
  margin: -4px 0 12px;
}
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}
.toolbar-filters {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.search-wrap {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1 1 240px;
  max-width: 340px;
}
.search-ico {
  position: absolute;
  left: 11px;
  color: #94a3b8;
  pointer-events: none;
}
.search-input,
.select {
  padding: 8px 11px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  font-size: 12.5px;
  color: #1e2739;
  background: #fff;
}
.search-input {
  width: 100%;
  padding-left: 32px;
  background: #f8fafc;
}
.search-input:focus,
.select:focus {
  outline: none;
  border-color: #7c6ff7;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(124, 111, 247, 0.12);
}
.date-field {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: #94a3b8;
}
.date-field .select {
  padding: 6px 8px;
}

/* ── Tables ─────────────────────────────────────────── */
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
.data-table th:first-child { border-radius: 8px 0 0 8px; }
.data-table th:last-child { border-radius: 0 8px 8px 0; }
.data-table td {
  padding: 11px 10px;
  border-bottom: 1px solid #f1f5f9;
  white-space: nowrap;
  vertical-align: middle;
  color: #1e2739;
}
.data-table tfoot td {
  font-weight: 700;
  border-bottom: none;
  border-top: 2px solid #e9edf3;
}
.data-table tbody.dim {
  opacity: 0.55;
}
.click-row {
  cursor: pointer;
}
.click-row:hover td,
.click-row:focus-visible td {
  background: #fafaff;
}
.click-row:focus-visible {
  outline: 2px solid #7c6ff7;
  outline-offset: -2px;
}
.empty-cell {
  text-align: center;
  color: #94a3b8 !important;
  padding: 36px 10px !important;
}
.num {
  text-align: right !important;
  font-variant-numeric: tabular-nums;
}
.strong {
  font-weight: 600;
}
.muted {
  color: #64748b !important;
}
.neg {
  color: #be123c !important;
}
.earn {
  color: #15803d !important;
}
.struck {
  color: #94a3b8 !important;
  text-decoration: line-through;
}
.cell-main {
  display: block;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 220px;
}
.sub {
  display: block;
  font-size: 11.5px;
  color: #94a3b8;
}
.mono {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace !important;
  font-size: 12px;
}
.vendor-cell {
  display: flex;
  align-items: center;
  gap: 9px;
}
.type-chip {
  display: inline-block;
  font-size: 10.5px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 6px;
  margin-bottom: 3px;
  background: #ede9fe;
  color: #5b4fc9;
}
.type-chip.subscription {
  background: #dcfce7;
  color: #15803d;
}
.masked {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace !important;
  font-size: 11.5px;
  color: #64748b;
  background: #f8fafc;
  border: 1px solid #eef1f6;
  border-radius: 6px;
  padding: 2px 7px;
}

/* ── Pills ──────────────────────────────────────────── */
.pill {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
.ps-successful { background: #dcfce7; color: #15803d; }
.ps-pending { background: #fef3c7; color: #b45309; }
.ps-failed { background: #fee2e2; color: #b91c1c; }
.ps-refunded { background: #ede9fe; color: #6d28d9; }
.ps-voided { background: #f1f5f9; color: #64748b; }

/* ── Pager ──────────────────────────────────────────── */
.pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid #f1f5f9;
}
.pager-info {
  font-size: 12px;
  color: #64748b;
}
.pager-btns {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* ── Responsive ─────────────────────────────────────── */
@media (max-width: 1400px) {
  .metric-row {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
.kpi-link {
  margin-left: auto;
  font-size: 11.5px;
  font-weight: 600;
  color: #6d5ff0;
  text-decoration: none;
  white-space: nowrap;
}
.kpi-link:hover {
  text-decoration: underline;
}

@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
  .kpi-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .state-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 1100px) {
  .split-row {
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
  .state-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .range-group {
    width: 100%;
    overflow-x: auto;
  }
  .search-wrap {
    max-width: none;
  }
  .toolbar-filters .select {
    flex: 1;
  }
  .chart-wrap {
    height: 230px;
  }
  .row-list li {
    flex-wrap: wrap;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
}
</style>
