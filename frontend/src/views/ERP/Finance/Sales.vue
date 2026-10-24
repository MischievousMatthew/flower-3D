<template>
  <HrPage title="Sales & revenue" subtitle="What the shop sold, with discounts, refunds and how customers paid kept apart.">
    <template #actions>
      <RangePicker @change="reloadAll" />
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="reloadAll">Refresh</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="reloadAll">Try again</button>
    </div>

    <!-- Headline numbers -->
    <section class="fx-kpis" aria-label="Sales this period">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article v-for="k in kpis" :key="k.key" class="sp-card fx-kpi" :class="{ hero: k.hero }">
          <span class="fx-kpi-label"><span class="sr-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong>{{ money(k.value) }}</strong>
          <span class="fx-kpi-sub"><Trend :now="k.value" :before="k.before" :cost="k.cost" /><span>{{ k.sub }}</span></span>
        </article>
      </template>
    </section>

    <div class="sr-two">
      <!-- Sales statement -->
      <section class="sp-card" aria-label="Sales summary">
        <div class="sp-card-head">
          <div><h2>Sales summary</h2><p v-if="data">{{ data.range.label }} · compared with {{ data.previous_range.label }}</p></div>
        </div>
        <div v-if="!data" class="bc-skeleton sr-sk"></div>
        <table v-else class="sr-statement">
          <thead><tr><th></th><th class="num">This period</th><th class="num hide-sm">Before</th><th class="num hide-sm">Change</th></tr></thead>
          <tbody>
            <tr v-for="r in statement" :key="r.key" :class="[r.kind, { indent: r.indent }]">
              <td>{{ r.label }}<small v-if="r.hint">{{ r.hint }}</small></td>
              <td class="num" :class="{ neg: r.sign < 0 && r.now }">{{ signed(r, r.now) }}</td>
              <td class="num hide-sm muted">{{ signed(r, r.before) }}</td>
              <td class="num hide-sm"><Trend :now="r.now" :before="r.before" :cost="r.sign < 0" /></td>
            </tr>
          </tbody>
        </table>
        <p v-if="cur.discounts_partial" class="sp-hint sr-note">
          Discounts are recorded for orders placed from now on. Older orders in this period didn't save their regular price, so their discounts aren't counted and gross sales may be a little low.
        </p>
      </section>

      <!-- How customers paid -->
      <section class="sp-card" aria-label="Payment methods">
        <div class="sp-card-head"><div><h2>How customers paid</h2><p>Sales by payment method</p></div></div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <template v-else>
          <div v-if="cur.collected > 0" class="sr-split" role="img" :aria-label="`Cash on delivery ${pct(cur.cod.amount)}%, e-wallet ${pct(cur.ewallet.amount)}%`">
            <span class="cod" :style="{ width: `${pct(cur.cod.amount)}%` }"></span>
            <span class="ew" :style="{ width: `${pct(cur.ewallet.amount)}%` }"></span>
          </div>
          <div class="sr-pay">
            <div>
              <span class="sr-pay-label"><i class="cod"></i>Cash on delivery</span>
              <strong>{{ money(cur.cod.amount) }}</strong>
              <span class="sr-pay-sub">{{ countText(cur.cod.orders, "order") }} · {{ pct(cur.cod.amount) }}%</span>
            </div>
            <div>
              <span class="sr-pay-label"><i class="ew"></i>E-wallet</span>
              <strong>{{ money(cur.ewallet.amount) }}</strong>
              <span class="sr-pay-sub">{{ countText(cur.ewallet.orders, "order") }} · {{ pct(cur.ewallet.amount) }}%</span>
            </div>
          </div>
          <ul v-if="data.ewallet_methods.length" class="sr-methods">
            <li v-for="m in data.ewallet_methods" :key="m.method"><span>{{ PAYMENT_LABEL[m.method] ?? m.method }}</span><span class="muted">{{ countText(m.orders, "order") }}</span><b>{{ money(m.amount) }}</b></li>
          </ul>
          <div class="sr-collect" :class="{ none: !data.cod_uncollected.orders }">
            <span class="fx-icon tone-amber" v-html="ICONS.clock"></span>
            <div>
              <strong>{{ money(data.cod_uncollected.amount) }} still to collect</strong>
              <span>{{ countText(data.cod_uncollected.orders, "cash-on-delivery order") }} not paid yet</span>
            </div>
          </div>
        </template>
      </section>
    </div>

    <!-- Completed and cancelled -->
    <section class="sp-card sr-outcomes" aria-label="Orders placed">
      <div class="sp-card-head">
        <div><h2>Orders placed</h2><p>How the orders placed in this period turned out</p></div>
        <div v-if="data && out.placed" class="sr-rates">
          <span>Completed <b>{{ out.completion_rate }}%</b></span>
          <span>Cancelled <b>{{ out.cancellation_rate }}%</b></span>
        </div>
      </div>
      <div class="sr-outcome-grid">
        <template v-if="!data">
          <div v-for="n in 4" :key="n" class="sr-outcome"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div>
        </template>
        <button v-for="o in outcomeTiles" v-else :key="o.key" type="button" class="sr-outcome" :class="[o.tone, { on: orderStatus === o.key && view === 'orders' }]" @click="showOrders(o.key)">
          <span class="sr-outcome-label"><i></i>{{ o.label }}</span>
          <strong>{{ o.orders }}</strong>
          <span class="sr-outcome-sub">{{ money(o.amount) }}<template v-if="o.before !== null"> · <Trend :now="o.orders" :before="o.before" :cost="o.cost" /></template></span>
        </button>
      </div>
    </section>

    <!-- Sales by date, product and order -->
    <section class="sp-card sr-detail" aria-label="Sales detail">
      <div class="sr-detail-head">
        <div class="sp-seg" role="tablist" aria-label="View sales">
          <button v-for="v in VIEWS" :key="v.key" type="button" role="tab" :aria-selected="view === v.key" :class="{ on: view === v.key }" @click="setView(v.key)">{{ v.label }}</button>
        </div>
        <p v-if="data && view === 'date'" class="sp-hint">One row per {{ data.by_date.unit }}</p>
      </div>

      <!-- By date -->
      <template v-if="view === 'date'">
        <div v-if="!data" class="bc-skeleton sr-sk-chart"></div>
        <template v-else>
          <BarChart :rows="dateRows" :series="DATE_SERIES" :extra="{ key: 'net_sales', label: 'Net sales' }" :height="220" />
          <div class="sr-scroll">
            <table class="sr-table">
              <thead><tr><th>{{ unitLabel }}</th><th class="num">Orders</th><th class="num">Gross sales</th><th class="num hide-sm">Discounts</th><th class="num hide-sm">Refunds</th><th class="num">Net sales</th></tr></thead>
              <tbody>
                <tr v-for="r in dateTable" :key="r.key">
                  <td>{{ r.label }}</td>
                  <td class="num">{{ r.orders }}</td>
                  <td class="num">{{ money(r.gross_sales) }}</td>
                  <td class="num hide-sm" :class="{ neg: r.discounts }">{{ r.discounts ? `−${money(r.discounts)}` : "—" }}</td>
                  <td class="num hide-sm" :class="{ neg: r.refunds }">{{ r.refunds ? `−${money(r.refunds)}` : "—" }}</td>
                  <td class="num strong">{{ money(r.net_sales) }}</td>
                </tr>
              </tbody>
            </table>
            <p v-if="!dateTable.length" class="sp-hint sr-empty-line">No sales in this period.</p>
          </div>
        </template>
      </template>

      <!-- By product -->
      <template v-else-if="view === 'product'">
        <div v-if="!data" class="bc-skeleton sr-sk"></div>
        <div v-else-if="!data.by_product.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.flower"></span>
          <h3>Nothing sold in this period</h3>
          <p>Products appear here once their orders are paid.</p>
        </div>
        <div v-else class="sr-scroll">
          <table class="sr-table">
            <thead><tr><th>Product</th><th class="num">Sold</th><th class="num hide-sm">Orders</th><th class="num hide-sm">Discounts</th><th class="num">Sales</th><th class="hide-sm sr-share-h">Share</th></tr></thead>
            <tbody>
              <tr v-for="p in data.by_product" :key="p.product_id ?? p.name">
                <td class="strong">{{ p.name }}</td>
                <td class="num">{{ p.quantity }}</td>
                <td class="num hide-sm">{{ p.orders }}</td>
                <td class="num hide-sm" :class="{ neg: p.discounts }">{{ p.discounts ? `−${money(p.discounts)}` : "—" }}</td>
                <td class="num strong">{{ money(p.sales) }}</td>
                <td class="hide-sm"><span class="sr-share"><span :style="{ width: `${p.share}%` }"></span></span><small>{{ p.share }}%</small></td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- By order -->
      <template v-else>
        <div class="sp-toolbar sr-toolbar">
          <select v-model="orderStatus" class="sp-select" aria-label="Order outcome" @change="loadOrders(1)">
            <option value="">Every order</option>
            <option v-for="o in OUTCOMES" :key="o.key" :value="o.key">{{ o.label }}</option>
          </select>
          <select v-model="orderPayment" class="sp-select" aria-label="Payment method" @change="loadOrders(1)">
            <option value="">Any payment</option>
            <option value="cod">Cash on delivery</option>
            <option value="ewallet">E-wallet</option>
          </select>
          <label class="sp-search">
            <span v-html="ICONS.search"></span>
            <input v-model="orderSearch" class="sp-input" type="search" placeholder="Order number or customer" aria-label="Search orders" />
          </label>
        </div>
        <ul v-if="!orders" class="fx-list">
          <li v-for="n in 5" :key="n" class="sr-order"><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <div v-else-if="!orders.orders.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.receipt"></span>
          <h3>{{ orderStatus || orderPayment || orderSearch ? "No orders match" : "No orders placed in this period" }}</h3>
          <p>{{ orderStatus || orderPayment || orderSearch ? "Try another filter." : "Orders appear here as customers place them." }}</p>
        </div>
        <template v-else>
          <ul class="fx-list" :class="{ dim: ordersLoading }">
            <li v-for="o in orders.orders" :key="o.id" class="sr-order">
              <div class="grow">
                <strong>{{ o.order_number }} <span class="sr-customer">· {{ o.customer }}</span></strong>
                <span class="sr-items">{{ o.items || "No items" }} · {{ countText(o.item_count, "item") }}</span>
                <span class="sr-meta">Placed {{ formatDate(o.placed_at, true) }} · {{ PAYMENT_LABEL[o.payment_method] ?? o.payment_method }} · {{ o.payment_status === "paid" ? "Paid" : o.payment_status === "refunded" ? "Refunded" : "Not paid yet" }}</span>
              </div>
              <span class="sp-chip" :class="OUTCOME_TONE[o.status_group]">{{ OUTCOME_LABEL[o.status_group] }}</span>
              <div class="sr-order-amt">
                <strong>{{ money(o.total) }}</strong>
                <small v-if="o.discount">−{{ money(o.discount) }} discount</small>
                <small v-if="o.refunded" class="neg">−{{ money(o.refunded) }} refunded</small>
              </div>
            </li>
          </ul>
          <footer v-if="orders.meta.last_page > 1" class="fx-pager">
            <span>{{ orders.meta.total }} orders · page {{ orders.meta.page }} of {{ orders.meta.last_page }}</span>
            <div>
              <button type="button" class="sp-icon-btn" :disabled="orders.meta.page <= 1" aria-label="Previous page" @click="loadOrders(orders.meta.page - 1)"><span v-html="ICONS.chevronLeft"></span></button>
              <button type="button" class="sp-icon-btn" :disabled="orders.meta.page >= orders.meta.last_page" aria-label="Next page" @click="loadOrders(orders.meta.page + 1)"><span v-html="ICONS.chevronRight"></span></button>
            </div>
          </footer>
        </template>
      </template>
    </section>
  </HrPage>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref, watch } from "vue";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import RangePicker from "../../../components/vendor/finance/RangePicker.vue";
import BarChart from "../../../components/vendor/finance/BarChart.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { PAYMENT_LABEL, change, financeRange, formatDate, money } from "../../../components/vendor/finance/financeUi";
import { useAuth } from "../../../composables/useAuth";
import "../../../components/vendor/finance/finance.css";

const VIEWS = [
  { key: "date", label: "By date" },
  { key: "product", label: "By product" },
  { key: "orders", label: "By order" },
];
const OUTCOMES = [
  { key: "completed", label: "Completed", tone: "green" },
  { key: "in_progress", label: "In progress", tone: "blue" },
  { key: "cancelled", label: "Cancelled", tone: "red", cost: true },
  { key: "returned", label: "Returned or refunded", tone: "pink", cost: true },
];
const OUTCOME_LABEL = Object.fromEntries(OUTCOMES.map((o) => [o.key, o.label]));
const OUTCOME_TONE = Object.fromEntries(OUTCOMES.map((o) => [o.key, o.tone]));
const DATE_SERIES = [
  { key: "cod", label: "Cash on delivery", color: "#f6ad55" },
  { key: "ewallet", label: "E-wallet", color: "#63b3ed" },
];

const { user } = useAuth();
// Finance staff use the ERP route (it checks their Sales & revenue permission); the shop owner uses the vendor one
const base = computed(() => (user.value?.type === "employee" ? "finance/sales" : "vendor/books/sales"));

const data = ref(null);
const error = ref("");
const loading = ref(false);
const view = ref("date");

const orders = ref(null);
const ordersLoading = ref(false);
const orderStatus = ref("");
const orderPayment = ref("");
const orderSearch = ref("");

const cur = computed(() => data.value?.current ?? {});
const prev = computed(() => data.value?.previous ?? {});
const out = computed(() => data.value?.outcomes ?? { placed: 0, groups: {} });

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
const pct = (v) => (cur.value.collected > 0 ? Math.round((v / cur.value.collected) * 100) : 0);

const kpis = computed(() => {
  const c = cur.value;
  const p = prev.value;
  const share = (v) => (c.gross_sales > 0 ? Math.round((v / c.gross_sales) * 1000) / 10 : 0);
  return [
    { key: "gross", label: "Gross sales", icon: "receipt", tone: "green", value: c.gross_sales, before: p.gross_sales, sub: `${countText(c.orders ?? 0, "paid order")}, at regular prices` },
    { key: "discounts", label: "Discounts", icon: "tag", tone: "amber", value: c.discounts, before: p.discounts, cost: true, sub: c.discounts ? `${share(c.discounts)}% of gross sales` : "no discounts given" },
    { key: "refunds", label: "Refunds", icon: "restore", tone: "pink", value: c.refunds, before: p.refunds, cost: true, sub: c.refund_count ? `${countText(c.refund_count, "refund")} paid back` : "no refunds" },
    { key: "net", label: "Net sales", icon: "wallet", tone: "teal", value: c.net_sales, before: p.net_sales, hero: true, sub: `average order ${money(c.average_order)}` },
  ];
});

const statement = computed(() => {
  const c = cur.value;
  const p = prev.value;
  return [
    { key: "gross", label: "Gross sales", hint: "Items at their regular prices", now: c.gross_sales, before: p.gross_sales, sign: 1 },
    { key: "discounts", label: "Discounts", hint: "Sale and promotion prices", now: c.discounts, before: p.discounts, sign: -1, indent: true },
    { key: "delivery", label: "Delivery fees", now: c.delivery_fees, before: p.delivery_fees, sign: 1, indent: true },
    ...(c.tax || p.tax ? [{ key: "tax", label: "Tax", now: c.tax, before: p.tax, sign: 1, indent: true }] : []),
    { key: "collected", label: "Collected from customers", now: c.collected, before: p.collected, sign: 1, kind: "sub" },
    { key: "refunds", label: "Refunds", hint: "Paid back in this period", now: c.refunds, before: p.refunds, sign: -1, indent: true },
    { key: "net", label: "Net sales", now: c.net_sales, before: p.net_sales, sign: 1, kind: "total" },
  ];
});

// Costs show as −₱…
const signed = (r, v) => `${r.sign < 0 && v ? "−" : ""}${money(Math.abs(v || 0))}`;

const outcomeTiles = computed(() =>
  OUTCOMES.map((o) => ({
    ...o,
    orders: out.value.groups[o.key]?.orders ?? 0,
    amount: out.value.groups[o.key]?.amount ?? 0,
    before: data.value?.previous_outcomes ? data.value.previous_outcomes.groups[o.key]?.orders ?? 0 : null,
  })),
);

const dateRows = computed(() => data.value?.by_date.rows ?? []);
// The table lists only the days/weeks/months that had something in them, newest first
const dateTable = computed(() => [...dateRows.value].filter((r) => r.orders || r.refunds).reverse());
const unitLabel = computed(() => ({ day: "Day", week: "Week", month: "Month" })[data.value?.by_date.unit] ?? "Date");

async function load() {
  error.value = "";
  loading.value = true;
  try {
    const res = await api.get(base.value, { params: { from: financeRange.from, to: financeRange.to } });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load sales for this period.");
  } finally {
    loading.value = false;
  }
}

async function loadOrders(page = 1) {
  ordersLoading.value = true;
  try {
    const res = await api.get(`${base.value}/orders`, {
      params: { from: financeRange.from, to: financeRange.to, page, status: orderStatus.value || undefined, payment: orderPayment.value || undefined, search: orderSearch.value.trim() || undefined },
    });
    orders.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load the orders.");
  } finally {
    ordersLoading.value = false;
  }
}

function setView(key) {
  view.value = key;
  if (key === "orders" && !orders.value) loadOrders(1);
}

// A tile opens the order list filtered to that outcome
function showOrders(group) {
  orderStatus.value = orderStatus.value === group && view.value === "orders" ? "" : group;
  view.value = "orders";
  loadOrders(1);
}

function reloadAll() {
  load();
  if (view.value === "orders" || orders.value) loadOrders(1);
}

let searchTimer = null;
watch(orderSearch, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadOrders(1), 300);
});

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
.sr-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.sr-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.muted {
  color: var(--v-faint);
}
.neg {
  color: var(--v-red-text);
}
.strong {
  font-weight: 600;
  color: var(--v-text);
}

.sr-two {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 16px;
  align-items: start;
  margin-bottom: 18px;
}
.sr-sk {
  height: 240px;
}
.sr-sk-chart {
  height: 260px;
}

/* Statement */
.sr-statement {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.sr-statement th {
  padding: 0 10px 10px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-faint);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.sr-statement td {
  padding: 10px;
  border-top: 1px solid var(--v-border);
  color: var(--v-text);
  vertical-align: top;
}
.sr-statement td small {
  display: block;
  margin-top: 1px;
  font-size: 11.5px;
  color: var(--v-faint);
}
.sr-statement .num {
  text-align: right;
  white-space: nowrap;
}
.sr-statement tr.indent td:first-child {
  padding-left: 26px;
  color: var(--v-text-2);
}
.sr-statement tr.sub td {
  font-weight: 600;
  background: var(--v-subtle);
}
.sr-statement tr.total td {
  font-size: 15px;
  font-weight: 700;
  background: var(--bc-accent-soft);
  border-top: 2px solid var(--bc-accent-muted);
}
.sr-note {
  margin: 12px 0 0;
}

/* Payment methods */
.sr-split {
  display: flex;
  height: 12px;
  margin-bottom: 14px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.sr-split .cod,
.sr-pay i.cod {
  background: #f6ad55;
}
.sr-split .ew,
.sr-pay i.ew {
  background: #63b3ed;
}
.sr-pay {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.sr-pay > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.sr-pay-label {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.sr-pay i {
  width: 10px;
  height: 10px;
  border-radius: 3px;
}
.sr-pay strong {
  font-size: 17px;
  font-weight: 600;
  color: var(--v-text);
}
.sr-pay-sub {
  font-size: 12px;
  color: var(--v-muted);
}
.sr-methods {
  list-style: none;
  margin: 14px 0 0;
  padding: 0;
  display: grid;
  gap: 6px;
}
.sr-methods li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  gap: 10px;
  padding: 7px 10px;
  border-radius: 9px;
  background: var(--v-subtle);
  font-size: 12.5px;
  color: var(--v-text-2);
}
.sr-methods b {
  color: var(--v-text);
}
.sr-collect {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--v-border);
}
.sr-collect > div {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.sr-collect strong {
  font-size: 13.5px;
  color: var(--v-text);
}
.sr-collect span:not(.fx-icon) {
  font-size: 12px;
  color: var(--v-muted);
}
.sr-collect.none {
  opacity: 0.7;
}

/* Outcomes */
.sr-outcomes {
  margin-bottom: 18px;
}
.sr-rates {
  display: flex;
  gap: 14px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.sr-rates b {
  color: var(--v-text);
}
.sr-outcome-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}
.sr-outcome {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 14px 16px;
  border: 1px solid var(--v-border-strong);
  border-radius: 14px;
  background: var(--v-surface);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.sr-outcome:hover,
.sr-outcome.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.sr-outcome-label {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-text-2);
}
.sr-outcome-label i {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.sr-outcome.green i { background: #48bb78; }
.sr-outcome.blue i { background: #63b3ed; }
.sr-outcome.red i { background: #fc8181; }
.sr-outcome.pink i { background: #f687b3; }
.sr-outcome strong {
  font-size: 22px;
  font-weight: 600;
  color: var(--v-text);
  line-height: 1.2;
}
.sr-outcome-sub {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--v-muted);
}

/* Detail */
.sr-detail {
  min-width: 0;
}
.sr-detail-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 16px;
}
.sr-detail-head .sp-hint {
  margin: 0;
}
.sr-scroll {
  overflow-x: auto;
  margin-top: 16px;
}
.sr-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.sr-table th {
  padding: 0 10px 10px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-faint);
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.sr-table td {
  padding: 10px;
  border-top: 1px solid var(--v-border);
  color: var(--v-text-2);
  white-space: nowrap;
}
.sr-table .num {
  text-align: right;
}
.sr-share-h {
  width: 140px;
}
.sr-share {
  display: inline-block;
  width: 80px;
  height: 6px;
  margin-right: 8px;
  vertical-align: middle;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.sr-share span {
  display: block;
  height: 100%;
  background: var(--bc-accent);
}
.sr-table small {
  font-size: 11.5px;
  color: var(--v-faint);
}
.sr-empty-line {
  margin: 12px 4px 0;
}

/* Orders */
.sr-toolbar {
  margin-bottom: 6px;
}
.sr-toolbar .sp-select {
  width: auto;
  flex: none;
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
.sr-order {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 4px;
  border-top: 1px solid var(--v-border);
}
.sr-order .grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.sr-order .grow > * {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sr-order strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.sr-customer {
  font-weight: 500;
  color: var(--v-text-2);
}
.sr-items {
  font-size: 12.5px;
  color: var(--v-text);
}
.sr-meta {
  font-size: 11.5px;
  color: var(--v-faint);
}
.sr-order-amt {
  flex: none;
  min-width: 110px;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}
.sr-order-amt strong {
  font-size: 14px;
}
.sr-order-amt small {
  font-size: 11.5px;
  color: var(--v-amber-text);
}
.sr-order-amt small.neg {
  color: var(--v-red-text);
}
.fx-pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid var(--v-border);
  font-size: 12.5px;
  color: var(--v-muted);
}
.fx-pager div {
  display: flex;
  gap: 6px;
}

@media (max-width: 1100px) {
  .sr-two {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 900px) {
  .sr-outcome-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 720px) {
  .hide-sm {
    display: none;
  }
  .sr-order {
    flex-wrap: wrap;
  }
  .sr-order .grow {
    flex-basis: 100%;
  }
  .sr-order-amt {
    margin-left: auto;
  }
}
</style>
