<template>
  <div :class="{ 'vendor-finance-shell': isVendorView }">
    <vendorHeader v-if="isVendorView" title="Finance Overview" />
    <div :class="{ 'vendor-finance-layout': isVendorView }">
      <VendorSidebar v-if="isVendorView" />
      <div :class="{ 'vendor-finance-content': isVendorView }">
        <div class="financial-dashboard">
          <div class="dashboard-content">
      <!-- Header -->
      <div class="dashboard-header">
        <div class="header-left">
          <h1 class="page-title">Dashboard</h1>
          <p class="page-subtitle">Financial Overview & Analytics</p>
        </div>
        <div class="header-right">
          <button class="refresh-btn" @click="loadAll" :disabled="loading.any">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              :class="{ spinning: loading.any }"
            >
              <polyline points="23 4 23 10 17 10"></polyline>
              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            Refresh
          </button>
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="kpi-grid">
        <!-- Cash Balance -->
        <div class="kpi-card" :class="{ 'kpi-loading': loading.overview }">
          <div class="kpi-header">
            <div class="kpi-icon balance">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                <line x1="2" y1="10" x2="22" y2="10"></line>
              </svg>
            </div>
            <div class="kpi-skeleton-bar" v-if="loading.overview"></div>
          </div>
          <div class="kpi-content">
            <h3 class="kpi-label">Cash Balance</h3>
            <div class="kpi-value" v-if="!loading.overview">
              ₱{{ overview.cash_balance?.amount ?? "0.00" }}
            </div>
            <div class="kpi-value-skeleton" v-else></div>
            <div class="kpi-change neutral" v-if="!loading.overview">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
              >
                <line x1="5" y1="12" x2="19" y2="12"></line>
              </svg>
              <span>Current balance</span>
            </div>
            <div class="kpi-change-skeleton" v-else></div>
          </div>
        </div>

        <!-- Completed Orders -->
        <div class="kpi-card" :class="{ 'kpi-loading': loading.overview }">
          <div class="kpi-header">
            <div class="kpi-icon inventory">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"
                ></path>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                <line x1="12" y1="22.08" x2="12" y2="12"></line>
              </svg>
            </div>
          </div>
          <div class="kpi-content">
            <h3 class="kpi-label">Completed Orders</h3>
            <div class="kpi-value" v-if="!loading.overview">
              {{ overview.completed_orders?.count ?? 0 }}
            </div>
            <div class="kpi-value-skeleton" v-else></div>
            <div
              v-if="!loading.overview"
              class="kpi-change"
              :class="changeClass(overview.completed_orders?.change_pct)"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
              >
                <polyline
                  :points="
                    (overview.completed_orders?.change_pct ?? 0) >= 0
                      ? '18 15 12 9 6 15'
                      : '6 9 12 15 18 9'
                  "
                ></polyline>
              </svg>
              <span
                >{{ formatChange(overview.completed_orders?.change_pct) }} vs
                last month</span
              >
            </div>
            <div class="kpi-change-skeleton" v-else></div>
          </div>
        </div>

        <!-- Total Revenue -->
        <div class="kpi-card" :class="{ 'kpi-loading': loading.overview }">
          <div class="kpi-header">
            <div class="kpi-icon earnings">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path
                  d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"
                ></path>
              </svg>
            </div>
          </div>
          <div class="kpi-content">
            <h3 class="kpi-label">Total Revenue</h3>
            <div class="kpi-value" v-if="!loading.overview">
              ₱{{ overview.total_revenue?.amount ?? "0.00" }}
            </div>
            <div class="kpi-value-skeleton" v-else></div>
            <div
              v-if="!loading.overview"
              class="kpi-change"
              :class="changeClass(overview.total_revenue?.change_pct)"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
              >
                <polyline
                  :points="
                    (overview.total_revenue?.change_pct ?? 0) >= 0
                      ? '18 15 12 9 6 15'
                      : '6 9 12 15 18 9'
                  "
                ></polyline>
              </svg>
              <span
                >{{ formatChange(overview.total_revenue?.change_pct) }} vs last
                month</span
              >
            </div>
            <div class="kpi-change-skeleton" v-else></div>
          </div>
        </div>

        <!-- Net Profit -->
        <div class="kpi-card" :class="{ 'kpi-loading': loading.overview }">
          <div class="kpi-header">
            <div class="kpi-icon profit">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
              </svg>
            </div>
          </div>
          <div class="kpi-content">
            <h3 class="kpi-label">Net Profit</h3>
            <div class="kpi-value" v-if="!loading.overview">
              ₱{{ overview.net_profit?.amount ?? "0.00" }}
            </div>
            <div class="kpi-value-skeleton" v-else></div>
            <div v-if="!loading.overview" class="kpi-change neutral">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
              >
                <line x1="5" y1="12" x2="19" y2="12"></line>
              </svg>
              <span>Revenue minus refunds</span>
            </div>
            <div class="kpi-change-skeleton" v-else></div>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="content-grid">
        <!-- Recent Transactions -->
        <div class="card recent-card">
          <div class="card-header">
            <h2>Recent Transactions</h2>
            <div class="card-actions">
              <div class="filter-tabs">
                <button
                  :class="{ active: transactionFilter === 'all' }"
                  @click="setTransactionFilter('all')"
                >
                  All
                </button>
                <button
                  :class="{ active: transactionFilter === 'credit' }"
                  @click="setTransactionFilter('credit')"
                >
                  Income
                </button>
                <button
                  :class="{ active: transactionFilter === 'debit' }"
                  @click="setTransactionFilter('debit')"
                >
                  Expense
                </button>
              </div>
              <button
                class="view-all-btn"
                @click="loadMoreTransactions"
                :disabled="
                  loading.transactions ||
                  txMeta.current_page >= txMeta.last_page
                "
              >
                {{
                  txMeta.current_page >= txMeta.last_page
                    ? "All loaded"
                    : "Load More"
                }}
              </button>
            </div>
          </div>

          <!-- Error state -->
          <div v-if="errors.transactions" class="error-state">
            <span>⚠️ {{ errors.transactions }}</span>
            <button @click="loadTransactions">Retry</button>
          </div>

          <div class="table-wrapper" v-else>
            <table class="transactions-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Order No.</th>
                  <th>Description</th>
                  <th>Type</th>
                  <th>Amount</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <!-- Skeleton rows -->
                <tr
                  v-if="loading.transactions"
                  v-for="n in 6"
                  :key="'sk' + n"
                  class="skeleton-row"
                >
                  <td><div class="cell-skeleton"></div></td>
                  <td><div class="cell-skeleton"></div></td>
                  <td><div class="cell-skeleton wide"></div></td>
                  <td><div class="cell-skeleton short"></div></td>
                  <td><div class="cell-skeleton short"></div></td>
                  <td><div class="cell-skeleton short"></div></td>
                </tr>

                <!-- Empty state -->
                <tr v-else-if="transactions.length === 0">
                  <td colspan="6" class="empty-state">
                    <div class="empty-icon">📊</div>
                    <p>No transactions yet.</p>
                    <small>Orders, refunds, procurement, and payroll will appear here.</small>
                  </td>
                </tr>

                <!-- Data rows -->
                <tr
                  v-else
                  v-for="tx in transactions"
                  :key="tx.id"
                  class="clickable-row"
                >
                  <td>{{ tx.date }}</td>
                  <td class="serial-cell">{{ tx.serial_no }}</td>
                  <td class="org-cell">{{ tx.description }}</td>
                  <td>
                    <span
                      class="type-badge"
                      :class="tx.category === 'income' ? 'receipt' : 'payment'"
                    >
                      {{ tx.type_label ?? (tx.category === "income" ? "Revenue" : "Expense") }}
                    </span>
                  </td>
                  <td
                    class="amount-cell"
                    :class="tx.category === 'income' ? 'positive' : 'negative'"
                  >
                    {{ tx.category === "expense" ? "-" : "+" }}₱{{ tx.amount }}
                  </td>
                  <td>
                    <span
                      class="status-badge"
                      :class="tx.status.toLowerCase()"
                      >{{ tx.status }}</span
                    >
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination info -->
          <div
            class="table-footer"
            v-if="!loading.transactions && transactions.length > 0"
          >
            <span class="table-count"
              >Showing {{ transactions.length }} of
              {{ txMeta.total }} transactions</span
            >
          </div>
        </div>

        <!-- Income & Expense Breakdown -->
        <div class="breakdown-section">
          <div class="card income-card">
            <div class="card-header">
              <h2>Income</h2>
              <select
                v-model="overviewPeriod"
                class="time-selector"
                @change="loadOverview"
              >
                <option value="this_month">This Month</option>
                <option value="last_month">Last Month</option>
                <option value="this_quarter">This Quarter</option>
                <option value="this_year">This Year</option>
              </select>
            </div>
            <div class="chart-container">
              <div class="donut-chart income-chart">
                <svg viewBox="0 0 200 200">
                  <circle
                    cx="100"
                    cy="100"
                    r="80"
                    fill="none"
                    stroke="#e2e8f0"
                    stroke-width="40"
                  ></circle>
                  <circle
                    cx="100"
                    cy="100"
                    r="80"
                    fill="none"
                    stroke="#4299e1"
                    stroke-width="40"
                    :stroke-dasharray="`${incomeArc.revenue} ${incomeArc.circumference}`"
                    :stroke-dashoffset="incomeArc.offset"
                    transform="rotate(-90 100 100)"
                  ></circle>
                </svg>
                <div class="donut-center" v-if="!loading.overview">
                  <span class="donut-value"
                    >PHP {{ shortNumber(overview.total_revenue?.raw ?? 0) }}</span
                  >
                  <span class="donut-label">{{ overviewPeriodLabel }}</span>
                </div>
              </div>
              <div class="chart-legend">
                <div
                  v-for="item in incomeSummary"
                  :key="item.label"
                  class="legend-item"
                >
                  <span class="legend-dot" :class="item.dotClass"></span>
                  <span class="legend-label">{{ item.label }}</span>
                  <span class="legend-value">{{ item.value }}</span>
                </div>
              </div>
            </div>
          </div>
          <div class="card expense-card">
            <div class="card-header">
              <h2>Expense Breakdown</h2>
              <select v-model="overviewPeriod" class="time-selector" @change="loadOverview">
                <option value="this_month">This Month</option>
                <option value="last_month">Last Month</option>
                <option value="this_quarter">This Quarter</option>
                <option value="this_year">This Year</option>
              </select>
            </div>
            <div class="chart-container">
              <div class="donut-chart expense-chart">
                <svg viewBox="0 0 200 200">
                  <circle
                    cx="100"
                    cy="100"
                    r="80"
                    fill="none"
                    stroke="#e2e8f0"
                    stroke-width="40"
                  ></circle>
                  <circle
                    cx="100"
                    cy="100"
                    r="80"
                    fill="none"
                    stroke="#fc8181"
                    stroke-width="40"
                    :stroke-dasharray="`${expenseArc.refund} ${expenseArc.circumference}`"
                    :stroke-dashoffset="expenseArc.offset1"
                    transform="rotate(-90 100 100)"
                  ></circle>
                  <circle
                    cx="100"
                    cy="100"
                    r="80"
                    fill="none"
                    stroke="#f6ad55"
                    stroke-width="40"
                    :stroke-dasharray="`${expenseArc.procurement} ${expenseArc.circumference}`"
                    :stroke-dashoffset="expenseArc.offset2"
                    transform="rotate(-90 100 100)"
                  ></circle>
                  <circle
                    cx="100"
                    cy="100"
                    r="80"
                    fill="none"
                    stroke="#9f7aea"
                    stroke-width="40"
                    :stroke-dasharray="`${expenseArc.payroll} ${expenseArc.circumference}`"
                    :stroke-dashoffset="expenseArc.offset3"
                    transform="rotate(-90 100 100)"
                  ></circle>
                </svg>
                <div class="donut-center" v-if="!loading.overview">
                  <span class="donut-value"
                    >PHP {{ shortNumber(overview.expenses?.total ?? 0) }}</span
                  >
                  <span class="donut-label">{{ overviewPeriodLabel }}</span>
                </div>
              </div>
              <div class="chart-legend">
                <div
                  v-for="item in expenseLegendItems"
                  :key="item.label"
                  class="legend-item"
                >
                  <span class="legend-dot" :class="item.dotClass"></span>
                  <span class="legend-label">{{ item.label }}</span>
                  <span class="legend-value">{{ item.value }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Cashflow Report -->
      <div class="card cashflow-card">
        <div class="card-header">
          <h2>Cashflow Report</h2>
          <div class="card-actions">
            <div class="chart-filters">
              <button
                class="filter-chip"
                :class="{ active: showIncome }"
                @click="showIncome = !showIncome"
              >
                <span class="chip-dot income-dot"></span>Income
              </button>
              <button
                class="filter-chip"
                :class="{ active: showExpense }"
                @click="showExpense = !showExpense"
              >
                <span class="chip-dot expense-dot"></span>Expense
              </button>
            </div>
            <select
              v-model="cashflowPeriod"
              class="time-selector"
              @change="loadCashflow"
            >
              <option value="this_month">This Month</option>
              <option value="last_month">Last Month</option>
              <option value="this_quarter">This Quarter</option>
              <option value="this_year">This Year</option>
            </select>
          </div>
        </div>

        <div class="chart-area">
          <!-- Loading skeleton -->
          <div v-if="loading.cashflow" class="cashflow-skeleton">
            <div
              class="cashflow-skel-bar"
              v-for="n in 12"
              :key="n"
              :style="{ height: Math.random() * 60 + 20 + '%' }"
            ></div>
          </div>

          <!-- Empty state -->
          <div v-else-if="cashflowData.length === 0" class="chart-empty">
            <p>No cashflow data for this period.</p>
          </div>

          <!-- Bar chart -->
          <div v-else class="bar-chart">
            <div class="chart-grid">
              <div class="grid-line"><span class="grid-label">{{ cashflowScaleLabels[0] }}</span></div>
              <div class="grid-line"><span class="grid-label">{{ cashflowScaleLabels[1] }}</span></div>
              <div class="grid-line"><span class="grid-label">{{ cashflowScaleLabels[2] }}</span></div>
              <div class="grid-line"><span class="grid-label">{{ cashflowScaleLabels[3] }}</span></div>
              <div class="grid-line"><span class="grid-label">{{ cashflowScaleLabels[4] }}</span></div>
            </div>
            <div class="chart-bars">
              <div
                v-for="bar in cashflowData"
                :key="bar.month"
                class="bar-group"
              >
                <div
                  class="bar-wrapper"
                  @mouseenter="showTooltip(bar, $event)"
                  @mouseleave="hideTooltip"
                >
                  <div
                    v-if="showIncome"
                    class="bar income-bar"
                    :style="{ height: bar.income + '%' }"
                  ></div>
                  <div
                    v-if="showExpense"
                    class="bar expense-bar"
                    :style="{ height: bar.expense + '%' }"
                  ></div>
                </div>
                <span class="bar-label">{{ bar.month }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Tooltip -->
        <div
          v-if="tooltip.visible"
          class="chart-tooltip"
          :style="{ left: tooltip.x + 'px', top: tooltip.y + 'px' }"
        >
          <div class="tooltip-header">{{ tooltip.data.month }}</div>
          <div class="tooltip-row">
            <span class="tooltip-label"
              ><span class="tooltip-dot income-dot"></span>Income</span
            >
            <span class="tooltip-value"
              >₱{{ tooltip.data.incomeAmount?.toLocaleString() }}</span
            >
          </div>
          <div class="tooltip-row">
            <span class="tooltip-label"
              ><span class="tooltip-dot expense-dot"></span>Expense</span
            >
            <span class="tooltip-value"
              >₱{{ tooltip.data.expenseAmount?.toLocaleString() }}</span
            >
          </div>
        </div>
      </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import api from "../../../plugins/axios";
import vendorHeader from "../../../layouts/vendorHeader.vue";
import VendorSidebar from "../../../layouts/Sidebar/VendorSidebar.vue";
import { useAuth } from "../../../composables/useAuth";

const { user } = useAuth();
const isVendorView = computed(() => user.value?.role === "vendor");

// ── State ──────────────────────────────────────────────────────────────────
const vendorName = ref("Vendor");
const transactionFilter = ref("all");
const overviewPeriod = ref("this_month");
const cashflowPeriod = ref("this_month");
const showIncome = ref(true);
const showExpense = ref(true);

const overview = ref({});
const transactions = ref([]);
const cashflowData = ref([]);
const cashflowMeta = ref({ max_value: 0 });

const txMeta = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
  per_page: 15,
});

const loading = reactive({
  overview: false,
  transactions: false,
  cashflow: false,
  get any() {
    return this.overview || this.transactions || this.cashflow;
  },
});
const errors = reactive({ overview: null, transactions: null, cashflow: null });

const tooltip = ref({ visible: false, x: 0, y: 0, data: {} });

const overviewPeriodLabel = computed(() => {
  return overview.value.selected_period
    ? formatPeriodLabel(overview.value.selected_period)
    : formatPeriodLabel(overviewPeriod.value);
});

const expenseArc = computed(() => {
  const circumference = 2 * Math.PI * 80;
  const refund = Number(overview.value.expenses?.refund ?? 0);
  const procurement = Number(overview.value.expenses?.procurement ?? 0);
  const payroll = Number(overview.value.expenses?.payroll ?? 0);
  const total = refund + procurement + payroll;

  const refundPct = total > 0 ? Math.round((refund / total) * 100) : 0;
  const procurementPct = total > 0 ? Math.round((procurement / total) * 100) : 0;
  const payrollPct = Math.max(0, 100 - refundPct - procurementPct);

  return {
    circumference,
    refund: (refundPct / 100) * circumference,
    procurement: (procurementPct / 100) * circumference,
    payroll: (payrollPct / 100) * circumference,
    offset1: 0,
    offset2: -((refundPct / 100) * circumference),
    offset3: -((refundPct + procurementPct) / 100) * circumference,
    refundPct,
    procurementPct,
    payrollPct,
  };
});

const incomeArc = computed(() => {
  const circumference = 2 * Math.PI * 80;
  const revenue = Number(overview.value.total_revenue?.raw ?? 0);

  return {
    circumference,
    revenue: revenue > 0 ? circumference : 0,
    offset: 0,
  };
});

const incomeSummary = computed(() => [
  {
    label: "Revenue",
    value: formatCurrency(overview.value.total_revenue?.raw ?? 0),
    dotClass: "sales",
  },
  {
    label: "Net Profit",
    value: formatCurrency(overview.value.net_profit?.raw ?? 0),
    dotClass: "service",
  },
  {
    label: "Completed Orders",
    value: String(overview.value.completed_orders?.count ?? 0),
    dotClass: "payroll-dot",
  },
]);

const expenseLegendItems = computed(() => [
  {
    label: "Refunds",
    value: `${formatCurrency(overview.value.expenses?.refund ?? 0)} (${expenseArc.value.refundPct}%)`,
    dotClass: "sales-exp",
  },
  {
    label: "Procurement",
    value: `${formatCurrency(overview.value.expenses?.procurement ?? 0)} (${expenseArc.value.procurementPct}%)`,
    dotClass: "procurement-dot",
  },
  {
    label: "Payroll",
    value: `${formatCurrency(overview.value.expenses?.payroll ?? 0)} (${expenseArc.value.payrollPct}%)`,
    dotClass: "payroll-dot",
  },
]);

const cashflowScaleLabels = computed(() => {
  const maxValue = Number(cashflowMeta.value.max_value ?? 0);

  return [1, 0.75, 0.5, 0.25, 0].map((step) =>
    formatCompactCurrency(maxValue * step)
  );
});

async function loadOverview() {
  loading.overview = true;
  errors.overview = null;
  try {
    const res = await api.get("vendor/finance/overview", {
      params: { period: overviewPeriod.value },
    });
    if (res.data.success) {
      overview.value = res.data.data;
    }
  } catch (e) {
    errors.overview = "Failed to load overview data.";
    console.error("loadOverview:", e);
  } finally {
    loading.overview = false;
  }
}

async function loadTransactions(page = 1) {
  loading.transactions = true;
  errors.transactions = null;
  try {
    const type =
      transactionFilter.value === "all" ? undefined : transactionFilter.value;
    const res = await api.get("vendor/finance/transactions", {
      params: { page, per_page: 15, type },
    });
    if (res.data.success) {
      if (page === 1) {
        transactions.value = res.data.data;
      } else {
        transactions.value.push(...res.data.data);
      }
      Object.assign(txMeta, res.data.meta);
    }
  } catch (e) {
    errors.transactions = "Failed to load transactions.";
    console.error("loadTransactions:", e);
  } finally {
    loading.transactions = false;
  }
}

async function loadCashflow() {
  loading.cashflow = true;
  errors.cashflow = null;
  try {
    const res = await api.get("vendor/finance/cashflow", {
      params: { period: cashflowPeriod.value },
    });
    if (res.data.success) {
      cashflowData.value = res.data.data;
      cashflowMeta.value = res.data.meta ?? { max_value: 0 };
    }
  } catch (e) {
    errors.cashflow = "Failed to load cashflow data.";
    console.error("loadCashflow:", e);
  } finally {
    loading.cashflow = false;
  }
}

function loadAll() {
  loadOverview();
  loadTransactions(1);
  loadCashflow();
}

function setTransactionFilter(type) {
  transactionFilter.value = type;
  loadTransactions(1);
}

function loadMoreTransactions() {
  if (txMeta.current_page < txMeta.last_page) {
    loadTransactions(txMeta.current_page + 1);
  }
}

function changeClass(pct) {
  if (pct == null) return "neutral";
  return pct > 0 ? "positive" : pct < 0 ? "negative" : "neutral";
}

function formatChange(pct) {
  if (pct == null) return "--";
  return (pct > 0 ? "+" : "") + pct + "%";
}

function shortNumber(n) {
  const num = parseFloat(n) || 0;
  if (num >= 1_000_000) return (num / 1_000_000).toFixed(1) + "M";
  if (num >= 1_000) return (num / 1_000).toFixed(1) + "K";
  return num.toFixed(2);
}

function formatCurrency(value) {
  const num = Number(value ?? 0);
  return `PHP ${num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatCompactCurrency(value) {
  const num = Number(value ?? 0);
  if (num <= 0) return "PHP 0";
  return `PHP ${shortNumber(num)}`;
}

function formatPeriodLabel(period) {
  switch (period) {
    case "last_month":
      return "Last Month";
    case "this_quarter":
      return "This Quarter";
    case "this_year":
      return "This Year";
    default:
      return "This Month";
  }
}

function showTooltip(data, event) {
  const rect = event.currentTarget.getBoundingClientRect();
  tooltip.value = {
    visible: true,
    x: rect.left + rect.width / 2,
    y: rect.top - 10,
    data,
  };
}

function hideTooltip() {
  tooltip.value.visible = false;
}

// ── Lifecycle ──────────────────────────────────────────────────────────────
onMounted(() => {
  loadAll();
});
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.vendor-finance-shell {
  min-height: 100vh;
  background: #f7fafc;
}

.vendor-finance-layout {
  display: flex;
  min-height: calc(100vh - 74px);
}

.vendor-finance-content {
  margin-left: 260px;
  flex: 1;
  padding: 24px;
}

.vendor-finance-shell .financial-dashboard {
  min-height: 100%;
}

.financial-dashboard {
  display: flex;
  min-height: 100vh;
  background: #f7fafc;
  font-family: "Poppins", sans-serif;
}

.dashboard-content {
  flex: 1;
  padding: 32px 20px;
}

/* ── Header ─────────────────────────────────────────────────────────── */
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
}
.header-left {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.page-title {
  font-size: 32px;
  font-weight: 700;
  color: #1a202c;
  letter-spacing: -0.5px;
}
.page-subtitle {
  font-size: 14px;
  color: #718096;
  font-weight: 500;
}
.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.refresh-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}
.refresh-btn:hover:not(:disabled) {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}
.refresh-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
.spinning {
  animation: spin 0.8s linear infinite;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px 8px 16px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}
.user-details {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}
.user-name {
  font-size: 14px;
  font-weight: 600;
  color: #1a202c;
}
.user-role {
  font-size: 12px;
  color: #718096;
}
.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #e2e8f0;
}

/* ── KPI Grid ────────────────────────────────────────────────────────── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
  margin-bottom: 32px;
}

.kpi-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 24px;
  transition: all 0.3s;
  position: relative;
  overflow: hidden;
}
.kpi-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
}

.kpi-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.kpi-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}
.kpi-icon.balance {
  background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
}
.kpi-icon.inventory {
  background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}
.kpi-icon.earnings {
  background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
}
.kpi-icon.profit {
  background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);
}

.kpi-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.kpi-label {
  font-size: 13px;
  font-weight: 600;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.kpi-value {
  font-size: 28px;
  font-weight: 700;
  color: #1a202c;
}

.kpi-change {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 600;
}
.kpi-change.positive {
  color: #38a169;
}
.kpi-change.negative {
  color: #e53e3e;
}
.kpi-change.neutral {
  color: #718096;
}

/* Skeleton shimmer */
@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
.kpi-value-skeleton {
  height: 32px;
  width: 140px;
  border-radius: 8px;
  background: linear-gradient(90deg, #f0f2f5 25%, #e8ecf0 50%, #f0f2f5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}
.kpi-change-skeleton {
  height: 18px;
  width: 120px;
  border-radius: 6px;
  background: linear-gradient(90deg, #f0f2f5 25%, #e8ecf0 50%, #f0f2f5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  margin-top: 2px;
}
.kpi-skeleton-bar {
  height: 14px;
  width: 60px;
  border-radius: 6px;
  background: linear-gradient(90deg, #f0f2f5 25%, #e8ecf0 50%, #f0f2f5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}
.cell-skeleton {
  height: 14px;
  border-radius: 6px;
  background: linear-gradient(90deg, #f0f2f5 25%, #e8ecf0 50%, #f0f2f5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  width: 80%;
}
.cell-skeleton.wide {
  width: 95%;
}
.cell-skeleton.short {
  width: 55%;
}

/* ── Content Grid ────────────────────────────────────────────────────── */
.content-grid {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 24px;
  margin-bottom: 24px;
}

.card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 24px;
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.card-header h2 {
  font-size: 18px;
  font-weight: 700;
  color: #1a202c;
}
.card-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.filter-tabs {
  display: flex;
  gap: 4px;
  padding: 4px;
  background: #f7fafc;
  border-radius: 8px;
}
.filter-tabs button {
  padding: 6px 16px;
  background: transparent;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #718096;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}
.filter-tabs button.active {
  background: #fff;
  color: #48bb78;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.view-all-btn {
  padding: 8px 16px;
  background: transparent;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}
.view-all-btn:hover:not(:disabled) {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}
.view-all-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.time-selector {
  padding: 8px 12px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  cursor: pointer;
  font-family: "Poppins", sans-serif;
}
.time-selector:focus {
  outline: none;
  border-color: #48bb78;
}

/* ── Table ───────────────────────────────────────────────────────────── */
.table-wrapper {
  overflow-x: auto;
}
.transactions-table {
  width: 100%;
  border-collapse: collapse;
}
.transactions-table thead {
  background: #f7fafc;
  border-bottom: 2px solid #e2e8f0;
}
.transactions-table th {
  padding: 12px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.transactions-table tbody tr {
  border-bottom: 1px solid #f7fafc;
  transition: all 0.2s;
}
.transactions-table tbody tr.clickable-row {
  cursor: pointer;
}
.transactions-table tbody tr:hover {
  background: #f7fafc;
}
.transactions-table td {
  padding: 14px 16px;
  font-size: 14px;
  color: #2d3748;
}
.serial-cell {
  font-family: monospace;
  font-size: 13px;
  color: #718096;
}
.org-cell {
  font-weight: 500;
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.amount-cell {
  font-weight: 600;
  font-family: monospace;
}
.amount-cell.positive {
  color: #38a169;
}
.amount-cell.negative {
  color: #e53e3e;
}

.type-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 16px;
  font-size: 12px;
  font-weight: 600;
}
.type-badge.receipt {
  background: #c6f6d5;
  color: #22543d;
}
.type-badge.payment {
  background: #fed7e2;
  color: #702459;
}
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 16px;
  font-size: 12px;
  font-weight: 600;
}
.status-badge.completed {
  background: #c6f6d5;
  color: #22543d;
}
.status-badge.paid {
  background: #c6f6d5;
  color: #22543d;
}
.status-badge.pending {
  background: #feebc8;
  color: #7c2d12;
}
.status-badge.failed {
  background: #fed7e2;
  color: #742a2a;
}

.table-footer {
  padding: 12px 0 0;
  text-align: right;
}
.table-count {
  font-size: 12px;
  color: #a0aec0;
}

.empty-state {
  text-align: center;
  padding: 48px 20px;
  color: #a0aec0;
}
.empty-icon {
  font-size: 40px;
  margin-bottom: 12px;
}
.empty-state p {
  font-size: 15px;
  margin-bottom: 4px;
  color: #718096;
}
.empty-state small {
  font-size: 13px;
}

.error-state {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  background: #fff5f5;
  border: 1px solid #fed7d7;
  border-radius: 10px;
  font-size: 14px;
  color: #c53030;
}
.error-state button {
  padding: 6px 14px;
  background: #e53e3e;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  font-family: "Poppins", sans-serif;
}

/* ── Breakdown ───────────────────────────────────────────────────────── */
.breakdown-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.income-card,
.expense-card {
  padding: 20px;
}
.chart-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}
.donut-chart {
  width: 180px;
  height: 180px;
  position: relative;
}
.donut-chart svg {
  width: 100%;
  height: 100%;
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
  font-size: 18px;
  font-weight: 700;
  color: #1a202c;
}
.donut-label {
  font-size: 11px;
  color: #718096;
  font-weight: 500;
}
.chart-legend {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 10px;
}
.legend-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}
.legend-dot.sales {
  background: #4299e1;
}
.legend-dot.service {
  background: #ed8936;
}
.legend-dot.sales-exp {
  background: #fc8181;
}
.legend-label {
  flex: 1;
  font-size: 14px;
  color: #4a5568;
  font-weight: 500;
}
.legend-value {
  font-size: 14px;
  font-weight: 700;
  color: #1a202c;
}
.legend-dot.procurement-dot {
  background: #f6ad55;
}
.legend-dot.payroll-dot {
  background: #9f7aea;
}

/* ── Cashflow ────────────────────────────────────────────────────────── */
.cashflow-card {
}
.chart-filters {
  display: flex;
  gap: 8px;
}
.filter-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}
.filter-chip.active {
  background: #fff;
  border-color: #48bb78;
  color: #48bb78;
}
.chip-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.chip-dot.income-dot {
  background: #4299e1;
}
.chip-dot.expense-dot {
  background: #ed8936;
}

.chart-area {
  margin-top: 24px;
}
.chart-empty {
  text-align: center;
  padding: 60px;
  color: #a0aec0;
  font-size: 14px;
}

.bar-chart {
  position: relative;
  height: 300px;
  padding: 20px 0;
}
.chart-grid {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 45px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.grid-line {
  position: relative;
  border-top: 1px solid #f7fafc;
  width: 100%;
}
.grid-line:last-child {
  border-top: 2px solid #cbd5e0;
}
.grid-label {
  position: absolute;
  font-size: 11px;
  color: #a0aec0;
  font-weight: 500;
  left: 0;
  transform: translateY(-50%);
}
.chart-bars {
  display: flex;
  justify-content: space-around;
  align-items: flex-end;
  height: 100%;
  padding: 0 40px;
  position: relative;
}
.bar-group {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  align-items: center;
  height: 100%;
}
.bar-wrapper {
  display: flex;
  align-items: flex-end;
  gap: 3px;
  height: calc(100% - 30px);
  width: 100%;
  justify-content: center;
  cursor: pointer;
}
.bar {
  width: 14px;
  border-radius: 4px 4px 0 0;
  transition: all 0.3s;
  min-height: 4px;
}
.bar:hover {
  filter: brightness(1.1);
}
.income-bar {
  background: linear-gradient(180deg, #4299e1 0%, #3182ce 100%);
}
.expense-bar {
  background: linear-gradient(180deg, #ed8936 0%, #dd6b20 100%);
}
.bar-label {
  margin-top: 10px;
  font-size: 11px;
  color: #718096;
  font-weight: 600;
}

/* Cashflow loading skeleton */
.cashflow-skeleton {
  display: flex;
  justify-content: space-around;
  align-items: flex-end;
  height: 260px;
  padding: 0 40px;
}
.cashflow-skel-bar {
  width: 14px;
  border-radius: 4px 4px 0 0;
  background: linear-gradient(90deg, #f0f2f5 25%, #e8ecf0 50%, #f0f2f5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

/* ── Tooltip ─────────────────────────────────────────────────────────── */
.chart-tooltip {
  position: fixed;
  transform: translate(-50%, -100%) translateY(-12px);
  background: #1a202c;
  color: white;
  padding: 12px 16px;
  border-radius: 10px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  z-index: 1000;
  pointer-events: none;
  min-width: 180px;
}
.chart-tooltip::after {
  content: "";
  position: absolute;
  bottom: -6px;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 0;
  border-left: 6px solid transparent;
  border-right: 6px solid transparent;
  border-top: 6px solid #1a202c;
}
.tooltip-header {
  font-size: 13px;
  font-weight: 700;
  margin-bottom: 8px;
  padding-bottom: 8px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.tooltip-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
  font-size: 13px;
}
.tooltip-label {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #cbd5e0;
}
.tooltip-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.tooltip-value {
  font-weight: 700;
}

/* ── Responsive ──────────────────────────────────────────────────────── */
@media (max-width: 1400px) {
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .content-grid {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 768px) {
  .vendor-finance-content {
    margin-left: 0;
    padding: 16px;
  }
  .dashboard-content {
    padding: 20px;
  }
  .kpi-grid {
    grid-template-columns: 1fr;
  }
  .dashboard-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
}

@media (max-width: 968px) {
  .vendor-finance-content {
    margin-left: 0;
  }
}
</style>

