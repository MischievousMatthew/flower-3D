<template>
  <ShopLayout section="finance" title="Profit & Loss" subtitle="Your sales minus refunds, the cost of what you sold, and your recorded expenses.">
    <template #actions>
      <RangePicker @change="load" />
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Headline numbers -->
    <section class="fx-kpis" aria-label="Summary">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article v-for="k in KPIS" :key="k.key" class="sp-card fx-kpi" :class="{ hero: k.key === 'net_profit' }">
          <span class="fx-kpi-label"><i class="dot" :class="k.dot"></i>{{ k.label }}</span>
          <strong :class="{ neg: cur[k.key] < 0 }">{{ money(cur[k.key]) }}</strong>
          <span class="fx-kpi-sub">
            <Trend :now="cur[k.key]" :before="prev[k.key]" :cost="k.cost" />
            <template v-if="k.margin && cur[k.margin] !== null"> · {{ cur[k.margin] }}% margin</template>
          </span>
        </article>
      </template>
    </section>

    <div class="pl-layout">
      <!-- Statement -->
      <section class="sp-card pl-card" aria-label="Statement">
        <div class="sp-card-head">
          <div><h2>Statement</h2><p v-if="data">{{ data.range.label }} · compared with {{ data.previous_range.label }}</p></div>
        </div>
        <div v-if="!data" class="bc-skeleton pl-sk"></div>
        <div v-else class="pl-scroll">
          <table class="pl">
            <thead>
              <tr><th></th><th class="num">This period</th><th class="num hide-sm">Before</th><th class="num hide-sm">Change</th></tr>
            </thead>
            <tbody>
              <template v-for="r in statement" :key="r.key">
                <tr :class="[r.kind, { indent: r.indent, loss: r.kind === 'total' && r.now < 0 }]">
                  <td>
                    {{ r.label }}
                    <small v-if="r.hint" class="pl-hint">{{ r.hint }}</small>
                  </td>
                  <td class="num" :class="{ neg: r.sign < 0 && r.now, red: r.kind === 'total' && r.now < 0 }">{{ signed(r, r.now) }}</td>
                  <td class="num hide-sm muted">{{ signed(r, r.before) }}</td>
                  <td class="num hide-sm"><Trend :now="r.now" :before="r.before" :cost="r.sign < 0" /></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <p v-if="cur.cogs_estimated" class="sp-hint pl-note">
          Older orders didn't save what the flowers cost you, so their cost of goods uses your products' current cost. New orders keep the cost at the time of sale.
        </p>
      </section>

      <!-- Side -->
      <aside class="pl-side">
        <section class="sp-card" aria-label="Where the money went">
          <div class="sp-card-head"><div><h2>Where your sales went</h2><p>Out of every ₱100 of net sales</p></div></div>
          <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
          <template v-else-if="cur.net_sales > 0">
            <div class="stack" role="img" :aria-label="split.map((p) => `${p.label} ${p.pct}%`).join(', ')">
              <span v-for="p in split" :key="p.key" :class="p.key" :style="{ width: `${p.width}%` }"></span>
            </div>
            <ul class="stack-legend">
              <li v-for="p in split" :key="p.key"><i :class="p.key"></i><span>{{ p.label }}</span><b>₱{{ p.pct }}</b></li>
              <li v-if="cur.net_profit < 0" class="loss"><i class="loss"></i><span>Loss (costs above sales)</span><b>₱{{ Math.round((-cur.net_profit / cur.net_sales) * 100) }}</b></li>
            </ul>
          </template>
          <p v-else class="sp-hint">No sales in this period yet.</p>
        </section>
        <section class="sp-card memo" aria-label="Also in this period">
          <div class="sp-card-head"><div><h2>Also in this period</h2><p>Not part of profit</p></div></div>
          <dl v-if="data">
            <div><dt>Stock purchases</dt><dd>{{ money(cur.stock_purchases) }}</dd></div>
            <div><dt>Withdrawals</dt><dd>{{ money(cur.withdrawals) }}</dd></div>
            <div><dt>Adjustments</dt><dd>{{ cur.adjustments_in || cur.adjustments_out ? `+${money(cur.adjustments_in)} / −${money(cur.adjustments_out)}` : money(0) }}</dd></div>
          </dl>
          <p class="sp-hint">Stock you buy is counted as cost of goods when it sells.</p>
        </section>
      </aside>
    </div>

    <!-- Trend -->
    <section class="sp-card pl-trend" aria-label="Last six months">
      <div class="sp-card-head"><div><h2>Last 6 months</h2><p>Net sales against what you spent (cost of goods and expenses)</p></div></div>
      <div v-if="!data" class="bc-skeleton pl-sk"></div>
      <BarChart v-else :rows="trendRows" :series="TREND_SERIES" :extra="{ key: 'net_profit', label: 'Net profit' }" :height="230" />
    </section>
  </ShopLayout>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from "vue";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import RangePicker from "../../../components/vendor/finance/RangePicker.vue";
import BarChart from "../../../components/vendor/finance/BarChart.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { change, financeRange, money } from "../../../components/vendor/finance/financeUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/finance/finance.css";

const KPIS = [
  { key: "net_sales", label: "Net sales", dot: "green" },
  { key: "gross_profit", label: "Gross profit", dot: "teal", margin: "gross_margin" },
  { key: "operating_expenses", label: "Expenses", dot: "amber", cost: true },
  { key: "net_profit", label: "Net profit", dot: "violet", margin: "net_margin" },
];
const TREND_SERIES = [
  { key: "net_sales", label: "Net sales", color: "#48bb78" },
  { key: "spent", label: "Cost of goods + expenses", color: "#f6ad55" },
];

const data = ref(null);
const error = ref("");

const cur = computed(() => data.value?.current ?? {});
const prev = computed(() => data.value?.previous ?? {});

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

const statement = computed(() => {
  const c = cur.value;
  const p = prev.value;
  const cats = new Map();
  for (const x of [...(p.expenses_by_category ?? []), ...(c.expenses_by_category ?? [])]) cats.set(x.category, x.label);
  const catAmount = (t, key) => (t.expenses_by_category ?? []).find((x) => x.category === key)?.amount ?? 0;
  return [
    { key: "sales", label: "Sales", now: c.sales, before: p.sales, sign: 1, hint: `${c.orders} paid ${c.orders === 1 ? "order" : "orders"}` },
    { key: "refunds", label: "Refunds", now: c.refunds, before: p.refunds, sign: -1, indent: true },
    { key: "net_sales", label: "Net sales", now: c.net_sales, before: p.net_sales, sign: 1, kind: "sub" },
    { key: "cogs", label: "Cost of goods sold", now: c.cogs, before: p.cogs, sign: -1, indent: true, hint: "What the flowers you sold cost you" },
    { key: "gross", label: "Gross profit", now: c.gross_profit, before: p.gross_profit, sign: 1, kind: "sub" },
    { key: "payroll", label: "Payroll", now: c.payroll, before: p.payroll, sign: -1, indent: true },
    ...[...cats].map(([key, label]) => ({ key: `cat-${key}`, label, now: catAmount(c, key), before: catAmount(p, key), sign: -1, indent: true })),
    { key: "net", label: "Net profit", now: c.net_profit, before: p.net_profit, sign: 1, kind: "total" },
  ];
});

// Costs show as −₱…; a total shows its own sign (a loss is −₱…)
const signed = (r, v) => {
  const neg = r.sign < 0 ? !!v : v < 0;
  return `${neg ? "−" : ""}${money(Math.abs(v || 0))}`;
};

const split = computed(() => {
  const c = cur.value;
  if (!(c.net_sales > 0)) return [];
  const parts = [
    { key: "cogs", label: "Cost of goods", value: c.cogs },
    { key: "opex", label: "Expenses", value: c.operating_expenses },
    { key: "profit", label: c.net_profit >= 0 ? "Profit" : "Loss", value: Math.abs(c.net_profit) },
  ];
  // When costs exceed sales the bar shows costs only (the loss is the overflow past ₱100)
  const whole = Math.max(c.net_sales, c.cogs + c.operating_expenses);
  return parts
    .filter((x) => x.value > 0 && (x.key !== "profit" || c.net_profit >= 0))
    .map((x) => ({ ...x, pct: Math.round((x.value / c.net_sales) * 100), width: (x.value / whole) * 100 }));
});

const trendRows = computed(() =>
  (data.value?.trend ?? []).map((r) => ({
    key: r.key,
    label: r.label,
    short: r.label.slice(0, 3),
    net_sales: Math.max(0, r.net_sales),
    spent: r.cogs + r.expenses,
    net_profit: r.net_profit,
  })),
);

async function load() {
  error.value = "";
  try {
    const res = await api.get("vendor/books/profit-loss", { params: { from: financeRange.from, to: financeRange.to } });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load your profit and loss.");
  }
}

onMounted(load);
</script>

<style scoped>
.fx-kpi.hero {
  background: linear-gradient(150deg, var(--bc-accent-soft) 0%, var(--v-surface) 60%);
  border-color: var(--bc-accent-muted);
}
.fx-kpi strong.neg {
  color: var(--v-red-text);
}
.pl-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 16px;
  align-items: start;
  margin-bottom: 16px;
}
.pl-card {
  min-width: 0;
}
.pl-sk {
  height: 260px;
}
.pl-scroll {
  overflow-x: auto;
}
.pl {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.pl th {
  padding: 0 10px 10px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-faint);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.pl td {
  padding: 10px;
  border-top: 1px solid var(--v-border);
  color: var(--v-text);
  vertical-align: top;
}
.pl .num {
  text-align: right;
  white-space: nowrap;
}
.pl .muted {
  color: var(--v-faint);
}
.pl tr.indent td:first-child {
  padding-left: 26px;
  color: var(--v-text-2);
}
.pl tr.sub td {
  font-weight: 600;
  color: var(--v-text);
  background: var(--v-subtle);
}
.pl tr.total td {
  font-size: 15px;
  font-weight: 700;
  color: var(--v-text);
  background: var(--bc-accent-soft);
  border-top: 2px solid var(--bc-accent-muted);
}
.pl tr.total.loss td {
  background: var(--v-red-soft);
  border-top-color: var(--v-red-soft);
}
.pl td.neg {
  color: var(--v-red-text);
}
.pl tr.total td.red {
  color: var(--v-red-text);
}
.pl-hint {
  display: block;
  margin-top: 1px;
  font-size: 11.5px;
  font-weight: 400;
  color: var(--v-faint);
}
.pl-note {
  margin: 12px 0 0;
}
.pl-side {
  display: grid;
  gap: 14px;
}
.stack {
  display: flex;
  height: 14px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.stack .cogs,
.stack-legend i.cogs {
  background: #63b3ed;
}
.stack .opex,
.stack-legend i.opex {
  background: #f6ad55;
}
.stack .profit,
.stack-legend i.profit {
  background: #48bb78;
}
.stack-legend {
  list-style: none;
  margin: 14px 0 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.stack-legend li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.stack-legend li.loss {
  color: var(--v-red-text);
}
.stack-legend li.loss b {
  color: var(--v-red-text);
}
.stack-legend i.loss {
  background: #fc8181;
}
.stack-legend span {
  flex: 1;
}
.stack-legend b {
  color: var(--v-text);
}
.stack-legend i {
  width: 10px;
  height: 10px;
  border-radius: 3px;
}
.memo dl {
  margin: 0 0 10px;
  display: grid;
  gap: 8px;
}
.memo dl div {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.memo dd {
  margin: 0;
  font-weight: 600;
  color: var(--v-text);
  text-align: right;
}
.pl-trend {
  min-width: 0;
}
@media (max-width: 1100px) {
  .pl-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .pl-side {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 720px) {
  .pl-side {
    grid-template-columns: minmax(0, 1fr);
  }
  .hide-sm {
    display: none;
  }
}
</style>
