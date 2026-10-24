<template>
  <HrPage title="Profit & loss" subtitle="Revenue against every cost (flowers, packaging, payment fees, payroll and recorded expenses), with gross and operating profit.">
    <template #actions>
      <div class="sp-seg pl-basis" role="radiogroup" aria-label="Count flower costs">
        <button v-for="b in BASES" :key="b.key" type="button" role="radio" :aria-checked="basis === b.key" :class="{ on: basis === b.key }" :title="b.hint" @click="setBasis(b.key)">{{ b.label }}</button>
      </div>
      <RangePicker @change="load" />
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>
    <div v-if="data && cur.uncosted_items" class="sp-banner warn pl-banner">
      <span v-html="ICONS.alert"></span>
      <span>{{ countText(cur.uncosted_items, "item") }} sold without a cost price, so their flowers aren't in the cost of goods and profit reads high. Set cost prices on those products, or count flowers when bought.</span>
      <button type="button" @click="setBasis('bought')">Count when bought</button>
    </div>

    <!-- Headline numbers -->
    <section class="fx-kpis" aria-label="This period">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article v-for="k in kpis" :key="k.key" class="sp-card fx-kpi" :class="{ hero: k.hero, loss: k.hero && k.value < 0 }">
          <span class="fx-kpi-label"><span class="pl-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong :class="{ neg: k.value < 0 }">{{ money(k.value) }}</strong>
          <span class="fx-kpi-sub"><Trend :now="k.value" :before="k.before" :cost="k.cost" /><span>{{ k.sub }}</span></span>
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
                <tr v-if="r.kind === 'heading'" class="heading"><td colspan="4">{{ r.label }}</td></tr>
                <tr v-else :class="[r.kind, { indent: r.indent, loss: r.kind === 'total' && r.now < 0 }]">
                  <td>{{ r.label }}<small v-if="r.hint" class="pl-hint">{{ r.hint }}</small></td>
                  <td class="num" :class="{ neg: r.sign < 0 && r.now, red: r.kind !== 'line' && r.now < 0 }">{{ signed(r, r.now) }}</td>
                  <td class="num hide-sm muted">{{ signed(r, r.before) }}</td>
                  <td class="num hide-sm"><Trend :now="r.now" :before="r.before" :cost="r.sign < 0" /></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <p v-if="data && cur.online_sales && cur.fees_known < cur.online_sales" class="sp-hint pl-note">
          PayMongo's fee is on file for {{ cur.fees_known }} of {{ cur.online_sales }} online payments; the rest add no fee yet.
        </p>
        <p v-if="data && cur.cogs_estimated" class="sp-hint pl-note">
          Older orders didn't save what their flowers cost, so they use the products' current cost.
        </p>
      </section>

      <aside class="pl-side">
        <!-- Revenue vs costs -->
        <section class="sp-card" aria-label="Revenue against costs">
          <div class="sp-card-head"><div><h2>Revenue against costs</h2><p>Out of every ₱100 of net revenue</p></div></div>
          <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
          <template v-else-if="cur.net_sales > 0">
            <div class="pl-vs">
              <div><span>Revenue</span><b>{{ money(cur.net_sales) }}</b></div>
              <div><span>Costs</span><b>{{ money(totalCosts(cur)) }}</b></div>
            </div>
            <div class="stack" role="img" :aria-label="split.map((p) => `${p.label} ${p.pct}%`).join(', ')">
              <span v-for="p in split" :key="p.key" :class="p.key" :style="{ width: `${p.width}%` }"></span>
            </div>
            <ul class="stack-legend">
              <li v-for="p in split" :key="p.key"><i :class="p.key"></i><span>{{ p.label }}</span><b>₱{{ p.pct }}</b></li>
              <li v-if="cur.net_profit < 0" class="loss"><i class="loss"></i><span>Loss (costs above revenue)</span><b>₱{{ Math.round((-cur.net_profit / cur.net_sales) * 100) }}</b></li>
            </ul>
          </template>
          <p v-else class="sp-hint">No revenue in this period yet.</p>
        </section>

        <!-- Biggest costs -->
        <section class="sp-card" aria-label="Biggest costs">
          <div class="sp-card-head"><div><h2>Biggest costs</h2><p>Where most of the money goes</p></div></div>
          <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
          <p v-else-if="!data.top_costs.length" class="sp-hint">No costs in this period.</p>
          <ol v-else class="pl-top">
            <li v-for="(c, i) in data.top_costs.slice(0, 6)" :key="c.key">
              <span class="pl-rank">{{ i + 1 }}</span>
              <div class="grow">
                <div class="pl-top-row"><span>{{ c.label }}</span><b>{{ money(c.amount) }}</b></div>
                <div class="pl-bar"><span :class="c.group" :style="{ width: `${c.share}%` }"></span></div>
              </div>
              <small>{{ c.share }}%</small>
            </li>
          </ol>
        </section>
      </aside>
    </div>

    <!-- Monthly performance -->
    <section class="sp-card pl-monthly" aria-label="Monthly performance">
      <div class="sp-card-head">
        <div><h2>Monthly performance</h2><p>The last 12 months: net revenue against all costs</p></div>
        <div class="sp-seg" role="tablist" aria-label="Show as">
          <button type="button" role="tab" :aria-selected="monthView === 'chart'" :class="{ on: monthView === 'chart' }" @click="monthView = 'chart'">Chart</button>
          <button type="button" role="tab" :aria-selected="monthView === 'table'" :class="{ on: monthView === 'table' }" @click="monthView = 'table'">Table</button>
        </div>
      </div>
      <div v-if="!data" class="bc-skeleton pl-sk"></div>
      <BarChart v-else-if="monthView === 'chart'" :rows="monthRows" :series="MONTH_SERIES" :extra="{ key: 'net_profit', label: 'Profit' }" :height="240" />
      <div v-else class="pl-scroll">
        <table class="pl-table">
          <thead><tr><th>Month</th><th class="num">Net revenue</th><th class="num hide-sm">Gross profit</th><th class="num hide-sm">Costs</th><th class="num">Profit</th><th class="num">Margin</th></tr></thead>
          <tbody>
            <tr v-for="m in [...data.monthly].reverse()" :key="m.key">
              <td>{{ m.label }}</td>
              <td class="num">{{ money(m.net_sales) }}</td>
              <td class="num hide-sm">{{ money(m.gross_profit) }}</td>
              <td class="num hide-sm">{{ money(m.cogs + m.expenses) }}</td>
              <td class="num strong" :class="{ neg: m.net_profit < 0 }">{{ money(m.net_profit) }}</td>
              <td class="num">{{ m.net_margin === null ? "—" : `${m.net_margin}%` }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr><td>12 months</td><td class="num">{{ money(sumMonths("net_sales")) }}</td><td class="num hide-sm">{{ money(sumMonths("gross_profit")) }}</td><td class="num hide-sm">{{ money(sumMonths("cogs") + sumMonths("expenses")) }}</td><td class="num" :class="{ neg: sumMonths('net_profit') < 0 }">{{ money(sumMonths("net_profit")) }}</td><td class="num">{{ sumMonths("net_sales") > 0 ? `${Math.round((sumMonths("net_profit") / sumMonths("net_sales")) * 1000) / 10}%` : "—" }}</td></tr>
          </tfoot>
        </table>
      </div>
    </section>

    <!-- Yearly performance -->
    <section class="pl-years" aria-label="Yearly performance">
      <template v-if="!data">
        <div v-for="n in 3" :key="n" class="sp-card pl-year"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <article v-for="(y, i) in data?.yearly ?? []" v-else :key="y.year" class="sp-card pl-year" :class="{ now: y.to_date }">
        <header>
          <h3>{{ y.year }}</h3>
          <span v-if="y.to_date" class="sp-chip green">So far</span>
        </header>
        <strong :class="{ neg: y.net_profit < 0 }">{{ money(y.net_profit) }}</strong>
        <span class="pl-year-sub">profit · {{ y.net_margin === null ? "no revenue" : `${y.net_margin}% margin` }}</span>
        <dl>
          <div><dt>Net revenue</dt><dd>{{ money(y.net_sales) }}</dd></div>
          <div><dt>Gross profit</dt><dd>{{ money(y.gross_profit) }}</dd></div>
          <div><dt>Costs</dt><dd>{{ money(y.cogs + y.operating_expenses) }}</dd></div>
        </dl>
        <span v-if="i > 0" class="pl-year-vs">Revenue <Trend :now="y.net_sales" :before="data.yearly[i - 1].net_sales" /> vs {{ data.yearly[i - 1].year }}</span>
      </article>
    </section>
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
import { change, financeRange, money } from "../../../components/vendor/finance/financeUi";
import { useAuth } from "../../../composables/useAuth";
import "../../../components/vendor/finance/finance.css";

const BASES = [
  { key: "sold", label: "Flower cost when sold", hint: "Cost of goods is what the items sold cost (needs cost prices on products)" },
  { key: "bought", label: "When bought", hint: "Cost of goods is the flowers and stock bought in the period" },
];
const MONTH_SERIES = [
  { key: "revenue", label: "Net revenue", color: "#48bb78" },
  { key: "costs", label: "All costs", color: "#f6ad55" },
];
const BASIS_STORE = "bc.finance.pl.basis";

const { user } = useAuth();
// Finance staff use the ERP route (it checks their Profit & loss permission); the shop owner uses the vendor one
const endpoint = computed(() => (user.value?.type === "employee" ? "finance/profit-loss" : "vendor/books/profit-loss"));

const readBasis = () => {
  try {
    return sessionStorage.getItem(BASIS_STORE) === "bought" ? "bought" : "sold";
  } catch {
    return "sold";
  }
};
const basis = ref(readBasis());
const data = ref(null);
const error = ref("");
const monthView = ref("chart");

const cur = computed(() => data.value?.current ?? {});
const prev = computed(() => data.value?.previous ?? {});
const totalCosts = (t) => (t.cogs ?? 0) + (t.operating_expenses ?? 0);

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
    { key: "revenue", label: "Net revenue", icon: "wallet", tone: "green", value: c.net_sales, before: p.net_sales, sub: `after ${money(c.refunds)} refunds` },
    { key: "costs", label: "Total costs", icon: "receipt", tone: "amber", value: totalCosts(c), before: totalCosts(p), cost: true, sub: `flowers & items ${money(c.cogs)}` },
    { key: "gross", label: "Gross profit", icon: "chart", tone: "teal", value: c.gross_profit, before: p.gross_profit, sub: c.gross_margin === null ? "no revenue yet" : `${c.gross_margin}% margin` },
    { key: "operating", label: "Operating profit", icon: "sparkle", tone: "violet", value: c.operating_profit, before: p.operating_profit, hero: true, sub: c.net_margin === null ? "estimated" : `${c.net_margin}% · estimated` },
  ];
});

const statement = computed(() => {
  const c = cur.value;
  const p = prev.value;
  const cats = new Map();
  for (const x of [...(p.expenses_by_category ?? []), ...(c.expenses_by_category ?? [])]) cats.set(x.category, x.label);
  const catAmount = (t, key) => (t.expenses_by_category ?? []).find((x) => x.category === key)?.amount ?? 0;
  const bought = c.cost_basis === "bought";
  return [
    { kind: "heading", key: "h-rev", label: "Revenue" },
    { key: "sales", label: "Sales", now: c.sales, before: p.sales, sign: 1, kind: "line", hint: `${countText(c.orders ?? 0, "paid order")}` },
    { key: "refunds", label: "Refunds", now: c.refunds, before: p.refunds, sign: -1, indent: true, kind: "line" },
    { key: "net", label: "Net revenue", now: c.net_sales, before: p.net_sales, sign: 1, kind: "sub" },
    { kind: "heading", key: "h-cogs", label: "Cost of goods" },
    { key: "cogs", label: bought ? "Flowers and stock bought" : "Flowers and items sold", hint: bought ? "Flowers recorded in Expenses and approved funding requests" : "What the items sold cost the shop", now: c.cogs, before: p.cogs, sign: -1, indent: true, kind: "line" },
    { key: "gross", label: "Gross profit", now: c.gross_profit, before: p.gross_profit, sign: 1, kind: "sub" },
    { kind: "heading", key: "h-op", label: "Operating expenses" },
    { key: "payroll", label: "Payroll", now: c.payroll, before: p.payroll, sign: -1, indent: true, kind: "line" },
    { key: "fees", label: "Payment fees", hint: "PayMongo processing fees", now: c.payment_fees, before: p.payment_fees, sign: -1, indent: true, kind: "line" },
    ...[...cats].map(([key, label]) => ({ key: `cat-${key}`, label, now: catAmount(c, key), before: catAmount(p, key), sign: -1, indent: true, kind: "line" })),
    { key: "opex", label: "Total operating expenses", now: c.operating_expenses, before: p.operating_expenses, sign: -1, kind: "sub" },
    { key: "profit", label: "Operating profit (estimated)", hint: "After refunds, flower costs, packaging, payment fees and every recorded expense", now: c.operating_profit, before: p.operating_profit, sign: 1, kind: "total" },
  ];
});

// Costs show as −₱…; subtotals and totals show their own sign
const signed = (r, v) => {
  const neg = r.sign < 0 ? !!v : v < 0;
  return `${neg ? "−" : ""}${money(Math.abs(v || 0))}`;
};

const split = computed(() => {
  const c = cur.value;
  if (!(c.net_sales > 0)) return [];
  const parts = [
    { key: "cogs", label: c.cost_basis === "bought" ? "Flowers & stock" : "Flowers & items", value: c.cogs },
    { key: "opex", label: "Operating expenses", value: c.operating_expenses },
    { key: "profit", label: "Profit", value: Math.max(0, c.net_profit) },
  ];
  const whole = Math.max(c.net_sales, c.cogs + c.operating_expenses);
  return parts.filter((x) => x.value > 0).map((x) => ({ ...x, pct: Math.round((x.value / c.net_sales) * 100), width: (x.value / whole) * 100 }));
});

const monthRows = computed(() =>
  (data.value?.monthly ?? []).map((m) => ({ key: m.key, label: m.label, short: m.label.slice(0, 3), revenue: Math.max(0, m.net_sales), costs: m.cogs + m.expenses, net_profit: m.net_profit })),
);
const sumMonths = (k) => (data.value?.monthly ?? []).reduce((s, m) => s + (m[k] ?? 0), 0);

async function load() {
  error.value = "";
  try {
    const res = await api.get(endpoint.value, { params: { from: financeRange.from, to: financeRange.to, basis: basis.value } });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load profit and loss.");
  }
}

function setBasis(b) {
  basis.value = b;
  try {
    sessionStorage.setItem(BASIS_STORE, b);
  } catch {
    /* private mode */
  }
  load();
}

onMounted(load);
</script>

<style scoped>
.fx-kpi.hero {
  background: linear-gradient(150deg, var(--bc-accent-soft) 0%, var(--v-surface) 60%);
  border-color: var(--bc-accent-muted);
}
.fx-kpi.hero.loss {
  background: linear-gradient(150deg, var(--v-red-soft) 0%, var(--v-surface) 60%);
  border-color: var(--v-red-soft);
}
.fx-kpi strong.neg,
.neg {
  color: var(--v-red-text);
}
.fx-kpi-sub {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.pl-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.pl-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.pl-basis button {
  white-space: nowrap;
}
.pl-banner {
  align-items: center;
}
.pl-banner > span:nth-child(2) {
  flex: 1;
}
.muted {
  color: var(--v-faint);
}
.strong {
  font-weight: 600;
  color: var(--v-text);
}

.pl-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 16px;
  align-items: start;
  margin-bottom: 18px;
}
.pl-card {
  min-width: 0;
}
.pl-sk {
  height: 280px;
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
  padding: 9px 10px;
  border-top: 1px solid var(--v-border);
  color: var(--v-text);
  vertical-align: top;
}
.pl .num {
  text-align: right;
  white-space: nowrap;
}
.pl tr.heading td {
  padding-top: 16px;
  border-top: 0;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--v-faint);
}
.pl tr.indent td:first-child {
  padding-left: 24px;
  color: var(--v-text-2);
}
.pl tr.sub td {
  font-weight: 600;
  background: var(--v-subtle);
}
.pl tr.total td {
  font-size: 15px;
  font-weight: 700;
  background: var(--bc-accent-soft);
  border-top: 2px solid var(--bc-accent-muted);
}
.pl tr.total.loss td {
  background: var(--v-red-soft);
  border-top-color: var(--v-red-soft);
}
.pl td.neg,
.pl td.red {
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
  margin: 10px 0 0;
}

/* Side */
.pl-side {
  display: grid;
  gap: 14px;
}
.pl-vs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 12px;
}
.pl-vs div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.pl-vs span {
  font-size: 12px;
  color: var(--v-muted);
}
.pl-vs b {
  font-size: 16px;
  font-weight: 600;
  color: var(--v-text);
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
  background: #f687b3;
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
.stack-legend li.loss,
.stack-legend li.loss b {
  color: var(--v-red-text);
}
.stack-legend i.loss {
  background: #fc8181;
}
.pl-top {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 12px;
}
.pl-top li {
  display: flex;
  align-items: center;
  gap: 10px;
}
.pl-rank {
  flex: none;
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--v-subtle-2);
  font-size: 11px;
  font-weight: 600;
  color: var(--v-text-2);
}
.pl-top .grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.pl-top-row {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.pl-top-row b {
  color: var(--v-text);
}
.pl-bar {
  height: 6px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.pl-bar span {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: #f6ad55;
}
.pl-bar span.cost_of_goods {
  background: #f687b3;
}
.pl-top small {
  flex: none;
  width: 40px;
  text-align: right;
  font-size: 11.5px;
  color: var(--v-faint);
}

/* Monthly */
.pl-monthly {
  min-width: 0;
  margin-bottom: 18px;
}
.pl-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.pl-table th {
  padding: 0 10px 10px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-faint);
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.pl-table td {
  padding: 9px 10px;
  border-top: 1px solid var(--v-border);
  color: var(--v-text-2);
  white-space: nowrap;
}
.pl-table .num {
  text-align: right;
}
.pl-table tfoot td {
  font-weight: 700;
  color: var(--v-text);
  background: var(--v-subtle);
  border-top: 2px solid var(--v-border-strong);
}
.pl-table td.neg {
  color: var(--v-red-text);
}

/* Yearly */
.pl-years {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}
.pl-year {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 18px;
}
.pl-year.now {
  border-color: var(--bc-accent-muted);
  background: linear-gradient(160deg, var(--bc-accent-soft) 0%, var(--v-surface) 55%);
}
.pl-year header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.pl-year h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text-2);
}
.pl-year strong {
  font-size: 22px;
  font-weight: 600;
  color: var(--v-text);
  line-height: 1.2;
}
.pl-year-sub {
  font-size: 12px;
  color: var(--v-muted);
}
.pl-year dl {
  margin: 10px 0 0;
  padding-top: 10px;
  border-top: 1px solid var(--v-border);
  display: grid;
  gap: 6px;
}
.pl-year dl div {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.pl-year dd {
  margin: 0;
  font-weight: 600;
  color: var(--v-text);
}
.pl-year-vs {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  font-size: 12px;
  color: var(--v-muted);
}

@media (max-width: 1100px) {
  .pl-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .pl-side {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 900px) {
  .pl-years {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 720px) {
  .pl-side {
    grid-template-columns: minmax(0, 1fr);
  }
  .hide-sm {
    display: none;
  }
  .pl-banner {
    flex-wrap: wrap;
  }
  .pl-banner > span:nth-child(2) {
    flex-basis: calc(100% - 30px);
  }
  .pl-banner button {
    margin-left: 28px;
  }
  .pl-monthly .sp-card-head {
    flex-wrap: wrap;
  }
}
</style>
