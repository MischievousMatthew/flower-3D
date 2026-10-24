<template>
  <HrPage title="Finance dashboard" subtitle="Sales, spending and profit for the shop, and money still on its way in or out.">
    <template #actions>
      <RangePicker @change="load" />
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="load">Refresh</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Headline numbers for the period -->
    <section class="fx-kpis" aria-label="This period">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article v-for="k in kpis" :key="k.key" class="sp-card fx-kpi" :class="{ hero: k.hero }">
          <span class="fx-kpi-label"><span class="fd-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong :class="{ neg: k.value < 0 }">{{ money(k.value) }}</strong>
          <span class="fx-kpi-sub">
            <Trend :now="k.value" :before="k.before" :cost="k.cost" />
            <span>{{ k.sub }}</span>
          </span>
        </article>
      </template>
    </section>

    <!-- Money still on its way -->
    <section class="fd-pending" aria-label="Pending">
      <template v-if="!data">
        <div v-for="n in 3" :key="n" class="sp-card fd-pend"><div class="bc-skeleton fx-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <article class="sp-card fd-pend">
          <span class="fx-icon tone-amber" v-html="ICONS.clock"></span>
          <div class="grow">
            <span class="fd-pend-label">Pending payments</span>
            <strong>{{ money(data.pending_payments.amount) }}</strong>
            <span class="fd-pend-sub">{{ countText(data.pending_payments.orders, "cash-on-delivery order") }} not collected yet</span>
          </div>
        </article>
        <article class="sp-card fd-pend">
          <span class="fx-icon tone-pink" v-html="ICONS.restore"></span>
          <div class="grow">
            <span class="fd-pend-label">Pending refunds</span>
            <strong>{{ money(data.pending_refunds.amount) }}</strong>
            <span class="fd-pend-sub">
              <template v-if="data.pending_refunds.count">{{ data.pending_refunds.agreed_count }} agreed · {{ data.pending_refunds.negotiating_count }} still being agreed</template>
              <template v-else>No refunds waiting</template>
            </span>
          </div>
        </article>
        <article class="sp-card fd-pend">
          <span class="fx-icon tone-blue" v-html="ICONS.box"></span>
          <div class="grow">
            <span class="fd-pend-label">Stock purchased</span>
            <strong>{{ money(cur.stock_purchases) }}</strong>
            <span class="fd-pend-sub">Counted as a cost when it sells</span>
          </div>
        </article>
      </template>
    </section>

    <!-- Twelve months of revenue against expenses -->
    <section class="sp-card fd-chart" aria-label="Monthly revenue and expenses">
      <div class="sp-card-head">
        <div>
          <h2>Monthly revenue and expenses</h2>
          <p>Net revenue against cost of items sold, payroll and recorded expenses, over the last 12 months</p>
        </div>
      </div>
      <div v-if="!data" class="bc-skeleton fd-sk-chart"></div>
      <template v-else>
        <BarChart :rows="trendRows" :series="TREND_SERIES" :extra="{ key: 'net_profit', label: 'Profit' }" :height="240" />
        <dl class="fd-year">
          <div><dt>12-month revenue</dt><dd>{{ money(year.revenue) }}</dd></div>
          <div><dt>12-month expenses</dt><dd>{{ money(year.expenses) }}</dd></div>
          <div><dt>12-month profit</dt><dd :class="{ neg: year.profit < 0 }">{{ money(year.profit) }}</dd></div>
        </dl>
      </template>
    </section>

    <div class="fd-grid">
      <!-- Latest money movements -->
      <section class="sp-card fd-recent" aria-label="Recent transactions">
        <div class="sp-card-head">
          <div><h2>Recent transactions</h2><p v-if="data">Latest money in and out · {{ data.range.label }}</p></div>
        </div>
        <ul v-if="!data" class="fx-list">
          <li v-for="n in 5" :key="n" class="fx-row"><div class="bc-skeleton fx-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <div v-else-if="!data.recent.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.receipt"></span>
          <h3>No transactions in this period</h3>
          <p>Customer payments, refunds, payroll and expenses appear here as they happen.</p>
        </div>
        <ul v-else class="fx-list" :class="{ dim: loading }">
          <li v-for="e in data.recent" :key="e.key" class="fx-row">
            <span class="fx-icon" :class="`tone-${KIND[e.kind]?.tone}`" v-html="ICONS[KIND[e.kind]?.icon || 'receipt']"></span>
            <div class="fx-main">
              <strong>{{ e.title }}</strong>
              <span class="fx-party">{{ e.party }}</span>
              <span class="fx-meta">
                <template v-if="e.order_number">{{ e.order_number }} · </template>
                <template v-if="e.payment_method">{{ PAYMENT_LABEL[e.payment_method] ?? e.payment_method }} · </template>{{ formatDate(e.created_at, true) }}
              </span>
            </div>
            <span class="sp-chip fx-chip hide-sm" :class="KIND[e.kind]?.tone">{{ KIND[e.kind]?.label ?? e.category_label }}</span>
            <span class="fx-amt" :class="e.direction">{{ e.direction === "in" ? "+" : "−" }}{{ money(e.amount) }}</span>
          </li>
        </ul>
      </section>

      <!-- What the expenses are made of -->
      <section class="sp-card" aria-label="Expense breakdown">
        <div class="sp-card-head">
          <div><h2>Where the money went</h2><p>Expenses this period</p></div>
        </div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <p v-else-if="!breakdown.length" class="sp-hint">No expenses in this period.</p>
        <ul v-else class="fd-bars">
          <li v-for="b in breakdown" :key="b.key">
            <div class="fd-bar-top"><span>{{ b.label }}</span><b>{{ money(b.amount) }}</b></div>
            <div class="fd-bar" role="img" :aria-label="`${b.label} ${b.pct}% of expenses`"><span :style="{ width: `${b.pct}%`, background: b.color }"></span></div>
            <small>{{ b.pct }}%</small>
          </li>
        </ul>
        <p v-if="cur.cogs_estimated" class="sp-hint fd-note">
          Some older orders didn't save what their items cost, so their cost uses the products' current cost. Profit is an estimate.
        </p>
      </section>
    </div>
  </HrPage>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from "vue";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import RangePicker from "../../../components/vendor/finance/RangePicker.vue";
import BarChart from "../../../components/vendor/finance/BarChart.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { KIND, PAYMENT_LABEL, change, financeRange, formatDate, money } from "../../../components/vendor/finance/financeUi";
import { useAuth } from "../../../composables/useAuth";
import "../../../components/vendor/finance/finance.css";

const TREND_SERIES = [
  { key: "revenue", label: "Net revenue", color: "#48bb78" },
  { key: "expenses", label: "Expenses", color: "#f6ad55" },
];
const EXPENSE_COLORS = ["#f6ad55", "#b794f4", "#63b3ed", "#4fd1c5", "#fc8181", "#f687b3", "#a0aec0"];

const { user } = useAuth();
// Finance staff use the ERP route (it checks their Finance dashboard permission); the shop owner uses the vendor one
const endpoint = computed(() => (user.value?.type === "employee" ? "finance/dashboard" : "vendor/books/dashboard"));

const data = ref(null);
const error = ref("");
const loading = ref(false);

const cur = computed(() => data.value?.current ?? {});
const prev = computed(() => data.value?.previous ?? {});
const totalExpenses = (t) => (t.cogs ?? 0) + (t.operating_expenses ?? 0);

// ▲/▼ against the previous period; for costs, going up is bad
const Trend = defineComponent({
  props: { now: Number, before: Number, cost: Boolean },
  setup: (p) => () => {
    const c = change(p.now ?? 0, p.before ?? 0);
    if (c === null) return h("span", { class: "fx-trend flat" }, p.now ? "New" : "—");
    const good = p.cost ? c <= 0 : c >= 0;
    return h("span", { class: ["fx-trend", c === 0 ? "flat" : good ? "up" : "down"] }, `${c > 0 ? "▲" : c < 0 ? "▼" : "•"} ${Math.abs(c)}%`);
  },
});

const countText = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;

const kpis = computed(() => {
  const c = cur.value;
  const p = prev.value;
  return [
    { key: "sales", label: "Total sales", icon: "receipt", tone: "green", value: c.sales, before: p.sales, sub: countText(c.orders ?? 0, "paid order") },
    { key: "net", label: "Net revenue", icon: "wallet", tone: "teal", value: c.net_sales, before: p.net_sales, sub: c.refunds ? `after ${money(c.refunds)} in refunds` : "no refunds" },
    { key: "expenses", label: "Total expenses", icon: "card", tone: "amber", value: totalExpenses(c), before: totalExpenses(p), cost: true, sub: `items ${money(c.cogs)} · running ${money(c.operating_expenses)}` },
    { key: "profit", label: "Estimated profit", icon: "chart", tone: "violet", value: c.net_profit, before: p.net_profit, hero: true, sub: c.net_margin !== null && c.net_margin !== undefined ? `${c.net_margin}% of net revenue` : "no revenue yet" },
  ];
});

const trendRows = computed(() =>
  (data.value?.trend ?? []).map((r) => ({
    key: r.key,
    label: r.label,
    short: r.label.slice(0, 3),
    revenue: Math.max(0, r.net_sales),
    expenses: r.cogs + r.expenses,
    net_profit: r.net_profit,
  })),
);

const year = computed(() => {
  const rows = data.value?.trend ?? [];
  const revenue = rows.reduce((s, r) => s + r.net_sales, 0);
  const expenses = rows.reduce((s, r) => s + r.cogs + r.expenses, 0);
  return { revenue, expenses, profit: rows.reduce((s, r) => s + r.net_profit, 0) };
});

const breakdown = computed(() => {
  const c = cur.value;
  const parts = [
    { key: "cogs", label: "Cost of items sold", amount: c.cogs ?? 0 },
    { key: "payroll", label: "Payroll", amount: c.payroll ?? 0 },
    { key: "fees", label: "Payment fees", amount: c.payment_fees ?? 0 },
    ...(c.expenses_by_category ?? []).map((x) => ({ key: `cat-${x.category}`, label: x.label, amount: x.amount })),
  ].filter((x) => x.amount > 0);
  const total = parts.reduce((s, x) => s + x.amount, 0);
  return parts
    .sort((a, b) => b.amount - a.amount)
    .map((x, i) => ({ ...x, pct: Math.round((x.amount / total) * 100), color: EXPENSE_COLORS[i % EXPENSE_COLORS.length] }));
});

async function load() {
  error.value = "";
  loading.value = true;
  try {
    const res = await api.get(endpoint.value, { params: { from: financeRange.from, to: financeRange.to } });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load the finance dashboard.");
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.fx-kpi.hero {
  background: linear-gradient(150deg, var(--bc-accent-soft) 0%, var(--v-surface) 60%);
  border-color: var(--bc-accent-muted);
}
.fx-kpi-sub {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.fd-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.fd-ic :deep(svg) {
  width: 15px;
  height: 15px;
}

/* Pending */
.fd-pending {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}
.fd-pend {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
}
.fd-pend .grow,
.fx-row .grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.fd-pend-label {
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-text-2);
}
.fd-pend strong {
  font-size: 18px;
  font-weight: 600;
  color: var(--v-text);
}
.fd-pend-sub {
  font-size: 12px;
  color: var(--v-muted);
}

/* Chart */
.fd-chart {
  min-width: 0;
  margin-bottom: 18px;
}
.fd-sk-chart {
  height: 280px;
}
.fd-year {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin: 16px 0 0;
  padding-top: 14px;
  border-top: 1px solid var(--v-border);
}
.fd-year div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.fd-year dt {
  font-size: 12px;
  color: var(--v-muted);
}
.fd-year dd {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--v-text);
}
.fd-year dd.neg,
.fx-kpi strong.neg {
  color: var(--v-red-text);
}

/* Recent + breakdown */
.fd-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 16px;
  align-items: start;
}
.fd-recent {
  min-width: 0;
}
.fx-list {
  list-style: none;
  margin: 0;
  padding: 0;
  transition: opacity 0.2s;
}
.fx-list.dim {
  opacity: 0.55;
}
.fx-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 4px;
  border-top: 1px solid var(--v-border);
}
.fx-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.fx-main strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fx-party {
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fx-meta {
  font-size: 11.5px;
  color: var(--v-faint);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fx-chip {
  flex: none;
}
.fx-amt {
  flex: none;
  min-width: 100px;
  text-align: right;
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
}
.fx-amt.in {
  color: var(--v-green-text);
}
.fx-amt.out {
  color: var(--v-red-text);
}
.fd-bars {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 14px;
}
.fd-bars li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 38px;
  align-items: center;
  gap: 4px 10px;
}
.fd-bar-top {
  grid-column: 1 / -1;
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.fd-bar-top b {
  color: var(--v-text);
  font-weight: 600;
}
.fd-bar {
  height: 8px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.fd-bar span {
  display: block;
  height: 100%;
  border-radius: 999px;
}
.fd-bars small {
  font-size: 11.5px;
  color: var(--v-faint);
  text-align: right;
}
.fd-note {
  margin: 14px 0 0;
}

@media (max-width: 1100px) {
  .fd-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 900px) {
  .fd-pending {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 720px) {
  .fd-year {
    grid-template-columns: minmax(0, 1fr);
  }
  .hide-sm {
    display: none;
  }
}
</style>
