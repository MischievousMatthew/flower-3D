<template>
  <ShopLayout section="finance" title="Sales & Transactions" subtitle="Customer payments, order totals and every peso that moved in or out of your shop.">
    <template #actions>
      <RangePicker @change="reload" />
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Summary -->
    <section class="fx-kpis" aria-label="Summary">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article class="sp-card fx-kpi">
          <span class="fx-kpi-label"><i class="dot green"></i>Sales</span>
          <strong>{{ money(s.sales) }}</strong>
          <span class="fx-kpi-sub">{{ s.orders }} paid {{ s.orders === 1 ? "order" : "orders" }}</span>
        </article>
        <article class="sp-card fx-kpi">
          <span class="fx-kpi-label"><i class="dot blue"></i>Average order</span>
          <strong>{{ money(s.average_order) }}</strong>
          <span class="fx-kpi-sub">per paid order</span>
        </article>
        <article class="sp-card fx-kpi">
          <span class="fx-kpi-label"><i class="dot teal"></i>Money in</span>
          <strong :class="{ pos: s.money_in }">{{ s.money_in ? "+" : "" }}{{ money(s.money_in) }}</strong>
          <span class="fx-kpi-sub">payments &amp; adjustments</span>
        </article>
        <article class="sp-card fx-kpi">
          <span class="fx-kpi-label"><i class="dot red"></i>Money out</span>
          <strong :class="{ neg: s.money_out }">{{ s.money_out ? "−" : "" }}{{ money(s.money_out) }}</strong>
          <span class="fx-kpi-sub">refunds, stock, payroll, expenses</span>
        </article>
      </template>
    </section>

    <div class="fx-layout">
      <!-- Transactions -->
      <section class="sp-card fx-list-card" aria-label="Transactions">
        <div class="sp-toolbar fx-toolbar">
          <div class="sp-seg" role="tablist" aria-label="Direction">
            <button v-for="d in DIRECTIONS" :key="d.key" type="button" role="tab" :aria-selected="direction === d.key" :class="{ on: direction === d.key }" @click="setDirection(d.key)">{{ d.label }}</button>
          </div>
          <select v-model="kind" class="sp-select fx-kind" aria-label="Type" @change="reload">
            <option value="">Every type</option>
            <option v-for="(k, key) in KIND" :key="key" :value="key">{{ k.label }}{{ data?.kinds?.[key] ? ` (${data.kinds[key]})` : "" }}</option>
          </select>
          <label class="sp-search">
            <span v-html="ICONS.search"></span>
            <input v-model="search" class="sp-input" type="search" placeholder="Customer, order or description" aria-label="Search transactions" />
          </label>
        </div>

        <ul v-if="!data" class="fx-list">
          <li v-for="n in 6" :key="n" class="fx-row"><div class="bc-skeleton fx-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <div v-else-if="!data.entries.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.receipt"></span>
          <h3>{{ search || kind || direction ? "Nothing matches" : "No transactions in this period" }}</h3>
          <p>{{ search || kind || direction ? "Try another filter." : "Customer payments appear here once orders are paid or delivered." }}</p>
        </div>
        <ul v-else class="fx-list" :class="{ dim: loading }">
          <li v-for="e in data.entries" :key="e.key" class="fx-row">
            <span class="fx-icon" :class="`tone-${KIND[e.kind]?.tone}`" v-html="ICONS[KIND[e.kind]?.icon || 'receipt']"></span>
            <div class="fx-main">
              <strong>{{ e.title }}</strong>
              <span class="fx-party">{{ e.party }}</span>
              <span class="fx-meta">
                <template v-if="e.order_number">{{ e.order_number }} · </template>
                <template v-if="e.payment_method">{{ PAYMENT_LABEL[e.payment_method] ?? e.payment_method }} · </template>{{ formatDate(e.created_at, true) }}
              </span>
            </div>
            <span class="sp-chip fx-chip" :class="KIND[e.kind]?.tone">{{ KIND[e.kind]?.label ?? e.category_label }}</span>
            <span class="fx-amt" :class="e.direction">{{ e.direction === "in" ? "+" : "−" }}{{ money(e.amount) }}</span>
          </li>
        </ul>
        <footer v-if="data && data.meta.last_page > 1" class="fx-pager">
          <span>{{ data.meta.total }} transactions · page {{ data.meta.page }} of {{ data.meta.last_page }}</span>
          <div>
            <button type="button" class="sp-icon-btn" :disabled="page <= 1" aria-label="Previous page" @click="go(page - 1)"><span v-html="ICONS.chevronLeft"></span></button>
            <button type="button" class="sp-icon-btn" :disabled="page >= data.meta.last_page" aria-label="Next page" @click="go(page + 1)"><span v-html="ICONS.chevronRight"></span></button>
          </div>
        </footer>
      </section>

      <!-- How customers paid -->
      <aside class="fx-side">
        <section class="sp-card" aria-label="How customers paid">
          <div class="sp-card-head"><div><h2>How customers paid</h2><p>Sales in this period</p></div></div>
          <template v-if="data">
            <div class="split-bar" role="img" :aria-label="`Cash on delivery ${money(s.cod)}, online ${money(s.online)}`">
              <span class="cod" :style="{ width: `${share(s.cod)}%` }"></span>
              <span class="online" :style="{ width: `${share(s.online)}%` }"></span>
            </div>
            <ul class="split-legend">
              <li><i class="cod"></i><span>Cash on delivery</span><b>{{ money(s.cod) }}</b></li>
              <li><i class="online"></i><span>Online payments</span><b>{{ money(s.online) }}</b></li>
            </ul>
          </template>
          <div v-else class="bc-skeleton sp-sk-block"></div>
        </section>
        <section class="sp-card" aria-label="Net cash flow">
          <div class="sp-card-head"><div><h2>Net cash flow</h2><p>Money in minus money out</p></div></div>
          <strong v-if="data" class="net" :class="{ neg: net < 0, zero: !net }">{{ net < 0 ? "−" : net > 0 ? "+" : "" }}{{ money(Math.abs(net)) }}</strong>
          <div v-else class="bc-skeleton fx-sk-big"></div>
          <router-link :to="{ name: 'VendorBooksProfit' }" class="sp-btn sp-btn-ghost sp-btn-sm full">See profit &amp; loss</router-link>
        </section>
      </aside>
    </div>
  </ShopLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import RangePicker from "../../../components/vendor/finance/RangePicker.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { KIND, PAYMENT_LABEL, financeRange, formatDate, money } from "../../../components/vendor/finance/financeUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/finance/finance.css";

const DIRECTIONS = [
  { key: "", label: "All" },
  { key: "in", label: "Money in" },
  { key: "out", label: "Money out" },
];

const data = ref(null);
const error = ref("");
const loading = ref(false);
const direction = ref("");
const kind = ref("");
const search = ref("");
const page = ref(1);
let seq = 0;
let timer = null;

const s = computed(() => data.value?.stats ?? {});
const net = computed(() => (s.value.money_in || 0) - (s.value.money_out || 0));
const share = (v) => (s.value.sales ? (v / s.value.sales) * 100 : 0);

async function load() {
  const mine = ++seq;
  loading.value = true;
  error.value = "";
  try {
    const res = await api.get("vendor/books/transactions", {
      params: { from: financeRange.from, to: financeRange.to, direction: direction.value || undefined, kind: kind.value || undefined, search: search.value.trim() || undefined, page: page.value },
    });
    if (mine === seq) data.value = res.data.data;
  } catch (e) {
    if (mine === seq) error.value = apiMessage(e, "Couldn't load your transactions.");
  } finally {
    if (mine === seq) loading.value = false;
  }
}
function reload() {
  page.value = 1;
  load();
}
function setDirection(d) {
  direction.value = d;
  reload();
}
function go(p) {
  page.value = p;
  load();
}
watch(search, () => {
  clearTimeout(timer);
  timer = setTimeout(reload, 300);
});

onMounted(load);
onBeforeUnmount(() => clearTimeout(timer));
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
}
.fx-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 16px;
  align-items: start;
}
.fx-list-card {
  padding: 16px;
}
.fx-toolbar {
  margin-bottom: 8px;
}
.fx-kind {
  width: auto;
  min-width: 160px;
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
  border-top: 1px solid #f2f5f3;
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
  color: #1a202c;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fx-party {
  font-size: 12.5px;
  font-weight: 500;
  color: #2d3748;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fx-meta {
  font-size: 11.5px;
  color: #a0aec0;
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
  color: #22753f;
}
.fx-amt.out {
  color: #c53030;
}
.fx-pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid #f2f5f3;
  font-size: 12.5px;
  color: #718096;
}
.fx-pager div {
  display: flex;
  gap: 6px;
}
.fx-side {
  position: sticky;
  top: 16px;
  display: grid;
  gap: 14px;
}
.split-bar {
  display: flex;
  height: 12px;
  border-radius: 999px;
  overflow: hidden;
  background: #edf2f7;
}
.split-bar .cod {
  background: #48bb78;
}
.split-bar .online {
  background: #63b3ed;
}
.split-legend {
  list-style: none;
  margin: 14px 0 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.split-legend li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: #4a5568;
}
.split-legend span {
  flex: 1;
  min-width: 0;
}
.split-legend b {
  color: #1a202c;
  white-space: nowrap;
}
.split-legend i {
  width: 10px;
  height: 10px;
  border-radius: 3px;
}
.split-legend i.cod {
  background: #48bb78;
}
.split-legend i.online {
  background: #63b3ed;
}
.net {
  display: block;
  margin-bottom: 14px;
  font-size: 24px;
  font-weight: 600;
  color: #22753f;
}
.net.zero {
  color: #1a202c;
}
.net.neg {
  color: #c53030;
}
.full {
  width: 100%;
}
@media (max-width: 1100px) {
  .fx-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .fx-side {
    position: static;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 720px) {
  .fx-side {
    grid-template-columns: minmax(0, 1fr);
  }
  .fx-row {
    flex-wrap: wrap;
  }
  .fx-main {
    flex-basis: calc(100% - 52px);
  }
  .fx-chip {
    margin-left: 52px;
  }
  .fx-amt {
    margin-left: auto;
    min-width: 0;
  }
  .fx-kind {
    flex: 1;
  }
}
</style>
