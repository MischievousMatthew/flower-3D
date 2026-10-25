<template>
  <HrPage title="Supply Chain Dashboard" subtitle="Purchase orders, supplier deliveries on the way, stock running low, and what had to be thrown away.">
    <template #actions>
      <div class="sc-range" role="group" aria-label="Period">
        <button v-for="r in RANGES" :key="r.days" type="button" :class="{ on: days === r.days }" :aria-pressed="days === r.days" @click="setDays(r.days)">{{ r.label }}</button>
      </div>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="load"><span v-html="ICONS.restore"></span>Refresh</button>
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
        <component :is="k.to ? 'router-link' : 'article'" v-for="k in kpis" :key="k.key" :to="k.to" class="sp-card fx-kpi sc-kpi" :class="{ hero: k.hero, link: !!k.to }">
          <span class="fx-kpi-label"><span class="sc-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong>{{ k.value }}</strong>
          <span class="fx-kpi-sub">
            <span v-if="k.trend" class="fx-trend" :class="k.trend.cls">{{ k.trend.text }}</span>
            <span>{{ k.sub }}</span>
          </span>
          <div v-if="k.split" class="sc-split" role="img" :aria-label="k.split.map((s) => `${s.count} ${s.label}`).join(', ')">
            <span v-for="s in k.split" :key="s.label" :class="s.cls" :style="{ flexGrow: s.count }"></span>
          </div>
        </component>
      </template>
    </section>

    <!-- Alerts -->
    <section class="sp-card sc-alerts" aria-label="Needs attention">
      <div class="sp-card-head"><div><h2>Needs attention</h2><p>Shortages, stock past its date and unusual waste</p></div></div>
      <ul v-if="!data" class="sc-alert-list">
        <li v-for="n in 3" :key="n" class="sc-alert"><div class="bc-skeleton sc-alert-ic"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
      </ul>
      <div v-else-if="!alerts.length" class="sc-clear">
        <span class="sc-clear-ic" v-html="ICONS.check"></span>
        <div><strong>All clear</strong><span>No shortages, nothing past its date, and waste is normal.</span></div>
      </div>
      <ul v-else class="sc-alert-list">
        <li v-for="a in alerts" :key="a.key" class="sc-alert" :class="a.level">
          <span class="sc-alert-ic" v-html="ICONS[a.icon]"></span>
          <div class="grow">
            <strong>{{ a.title }}</strong>
            <span>{{ a.detail }}</span>
          </div>
          <span class="sp-chip" :class="a.level === 'critical' ? 'red' : 'amber'">{{ a.level === "critical" ? "Act now" : "Soon" }}</span>
          <router-link v-if="a.to" :to="a.to" class="sp-btn sp-btn-ghost sp-btn-sm">{{ a.action }}</router-link>
        </li>
      </ul>
    </section>

    <div class="sc-grid">
      <!-- Incoming deliveries -->
      <section class="sp-card" aria-label="Incoming supplier deliveries">
        <div class="sp-card-head">
          <div><h2>Incoming supplier deliveries</h2><p>On the road first, then orders the supplier is preparing</p></div>
          <router-link v-if="canView('sc_orders')" :to="{ name: 'OrderList' }" class="sp-btn sp-btn-ghost sp-btn-sm">All orders</router-link>
        </div>
        <ul v-if="!data" class="sc-list">
          <li v-for="n in 4" :key="n" class="sc-row"><div class="bc-skeleton sc-logo"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <div v-else-if="!data.incoming.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.truck"></span>
          <h3>Nothing on the way</h3>
          <p>Orders show up here once a supplier starts preparing them.</p>
        </div>
        <ul v-else class="sc-list" :class="{ dim: loading }">
          <li v-for="o in data.incoming" :key="o.id">
            <component :is="canView('sc_orders') ? 'router-link' : 'div'" :to="{ name: 'OrderDetail', params: { id: o.id } }" class="sc-row" :class="{ link: canView('sc_orders') }">
              <Logo :name="o.supplier" :src="o.supplier_logo" />
              <div class="grow">
                <strong>{{ o.supplier || "Supplier removed" }}</strong>
                <span>{{ o.order_number }} · {{ o.quantity ? `${o.quantity.toLocaleString("en-PH")} units` : `${o.lines} ${o.lines === 1 ? "item" : "items"}` }} · {{ money(o.total_amount) }}</span>
                <span v-if="o.status === 'shipped'" class="sc-sub">
                  {{ o.carrier ? `With ${o.carrier}` : "Shipped" }}{{ o.shipped_date ? ` since ${shortDate(o.shipped_date)}` : "" }}{{ o.tracking_number ? ` · #${o.tracking_number}` : "" }}
                </span>
                <span v-else class="sc-sub">Ordered {{ ago(o.ordered_at) }}</span>
              </div>
              <span class="sp-chip" :class="o.status === 'shipped' ? 'blue' : 'violet'">{{ o.status === "shipped" ? "On the way" : "Preparing" }}</span>
            </component>
          </li>
        </ul>
      </section>

      <!-- Purchase orders over time -->
      <section class="sp-card" aria-label="Purchase orders">
        <div class="sp-card-head"><div><h2>Purchase orders</h2><p>Orders placed per day, last {{ days }} days</p></div></div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <template v-else>
          <TrendLine :points="data.orders.per_day" unit="orders" />
          <ul class="sc-status">
            <li v-for="s in poStatuses" :key="s.key">
              <span class="sc-swatch" :class="s.cls"></span>
              <span>{{ s.label }}</span>
              <strong>{{ s.count }}</strong>
            </li>
          </ul>
        </template>
      </section>
    </div>

    <div class="sc-grid even">
      <!-- Shortages -->
      <section class="sp-card" aria-label="Low and out of stock">
        <div class="sp-card-head">
          <div><h2>Low &amp; out of stock</h2><p>At or under each product's minimum level</p></div>
          <router-link v-if="canView('inventory_products')" :to="{ name: 'Products' }" class="sp-btn sp-btn-ghost sp-btn-sm">Products</router-link>
        </div>
        <ul v-if="!data" class="sc-list">
          <li v-for="n in 4" :key="n" class="sc-row"><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w70"></div></div></li>
        </ul>
        <div v-else-if="!data.stock.items.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.box"></span>
          <h3>{{ data.stock.products ? "Everything is stocked" : "No products yet" }}</h3>
          <p>{{ data.stock.products ? `All ${data.stock.products} products are above their minimum.` : "Products you add appear here when they run low." }}</p>
        </div>
        <ul v-else class="sc-list">
          <li v-for="p in data.stock.items" :key="p.id" class="sc-row stock">
            <div class="grow">
              <strong>{{ p.name }}<span v-if="p.sku" class="muted"> · {{ p.sku }}</span></strong>
              <div class="sc-meter" :class="p.state" role="img" :aria-label="`${p.quantity} of a minimum ${p.minimum}`">
                <span :style="{ width: `${Math.min(100, (p.quantity / p.minimum) * 100)}%` }"></span>
              </div>
            </div>
            <div class="sc-qty">
              <strong>{{ p.quantity.toLocaleString("en-PH") }}</strong>
              <span>min {{ p.minimum }}</span>
            </div>
            <span class="sp-chip" :class="p.state === 'out' ? 'red' : 'amber'">{{ p.state === "out" ? "Out of stock" : "Low" }}</span>
          </li>
        </ul>
      </section>

      <!-- Suppliers -->
      <section class="sp-card" aria-label="Supplier performance">
        <div class="sp-card-head">
          <div><h2>Supplier performance</h2><p>Orders that arrived and how long they took</p></div>
          <router-link v-if="canView('suppliers')" :to="{ name: 'SupplierList' }" class="sp-btn sp-btn-ghost sp-btn-sm">Suppliers</router-link>
        </div>
        <ul v-if="!data" class="sc-list">
          <li v-for="n in 4" :key="n" class="sc-row"><div class="bc-skeleton sc-logo"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <div v-else-if="!data.suppliers.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.store"></span>
          <h3>No suppliers yet</h3>
          <p>Add a supplier to start tracking their deliveries.</p>
        </div>
        <ul v-else class="sc-list">
          <li v-for="s in data.suppliers" :key="s.id" class="sc-row">
            <Logo :name="s.name" :src="s.logo" />
            <div class="grow">
              <strong>{{ s.name }}</strong>
              <span>{{ s.orders }} {{ s.orders === 1 ? "order" : "orders" }} · {{ money(s.value) }}<template v-if="s.open"> · {{ s.open }} still open</template></span>
              <div v-if="s.fill_rate !== null" class="sc-meter good" role="img" :aria-label="`${s.fill_rate}% arrived`"><span :style="{ width: `${s.fill_rate}%` }"></span></div>
            </div>
            <dl class="sc-facts">
              <div><dt>Arrived</dt><dd>{{ s.fill_rate === null ? "—" : `${Math.round(s.fill_rate)}%` }}</dd></div>
              <div><dt>Takes</dt><dd>{{ s.avg_lead_days === null ? "—" : `${s.avg_lead_days} ${s.avg_lead_days === 1 ? "day" : "days"}` }}</dd></div>
            </dl>
            <span v-if="s.problems" class="sp-chip red">{{ s.problems }} failed</span>
          </li>
        </ul>
      </section>
    </div>

    <!-- Wastage -->
    <section class="sp-card" aria-label="Wastage">
      <div class="sp-card-head">
        <div><h2>Wastage</h2><p>Stems thrown away, last {{ days }} days</p></div>
        <router-link v-if="canView('warehouse')" :to="{ name: 'WarehouseFloor' }" class="sp-btn sp-btn-ghost sp-btn-sm">Floor view</router-link>
      </div>
      <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
      <div v-else class="sc-waste">
        <div class="sc-waste-chart">
          <dl class="sc-waste-nums">
            <div><dt>Thrown away</dt><dd>{{ data.wastage.units.toLocaleString("en-PH") }} <small>stems</small></dd><span v-if="wasteTrend" class="fx-trend" :class="wasteTrend.cls">{{ wasteTrend.text }}</span></div>
            <div><dt>What it cost</dt><dd>{{ money(data.wastage.cost) }}</dd><small>at purchase price</small></div>
          </dl>
          <TrendLine :points="data.wastage.per_day" unit="stems" />
        </div>
        <div class="sc-waste-side">
          <h3>Most wasted</h3>
          <p v-if="!data.wastage.top.length" class="sp-hint">Nothing thrown away in this period.</p>
          <ul v-else class="sc-mini">
            <li v-for="w in data.wastage.top" :key="w.name">
              <span>{{ w.name }}</span>
              <div class="sc-bar"><span :style="{ width: `${(w.units / data.wastage.top[0].units) * 100}%` }"></span></div>
              <strong>{{ w.units }}</strong>
            </li>
          </ul>
          <h3>Past or near its date</h3>
          <p v-if="!data.floor.batches.length" class="sp-hint">Everything on the floor is still fresh.</p>
          <ul v-else class="sc-mini dates">
            <li v-for="b in data.floor.batches" :key="b.id">
              <span>{{ b.product }}<small v-if="b.where"> · {{ b.where }}</small></span>
              <strong>{{ b.units }}</strong>
              <span class="sp-chip" :class="b.state === 'spoiled' ? 'red' : 'amber'">{{ b.state === "spoiled" ? `${Math.abs(b.days)}d past` : b.days === 0 ? "Today" : "Tomorrow" }}</span>
            </li>
          </ul>
        </div>
      </div>
    </section>
  </HrPage>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from "vue";
import api from "../../../../../plugins/axios";
import HrPage from "../../../../../components/hr/HrPage.vue";
import TrendLine from "../../../../../components/procurement/TrendLine.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../../../components/vendor/finance/financeUi";
import { useAssignment } from "../../../../../composables/useAssignment";
import "../../../../../components/vendor/finance/finance.css";

const RANGES = [
  { days: 7, label: "7 days" },
  { days: 30, label: "30 days" },
  { days: 90, label: "90 days" },
];

const { canView } = useAssignment();
const data = ref(null);
const error = ref("");
const loading = ref(false);
const days = ref(readDays());

const Logo = defineComponent({
  props: { name: String, src: String },
  setup(p) {
    const failed = ref(false);
    return () =>
      p.src && !failed.value
        ? h("img", { class: "sc-logo", src: p.src, alt: "", onError: () => (failed.value = true) })
        : h("span", { class: "sc-logo", "aria-hidden": "true" }, (p.name || "?").split(/\s+/).filter(Boolean).map((w) => w[0]).slice(0, 2).join("").toUpperCase());
  },
});

const trend = (now, before, goodWhenUp = true) => {
  if (!before) return now ? { text: "New", cls: "flat" } : null;
  const c = Math.round(((now - before) / before) * 100);
  const up = c > 0;
  return { text: `${up ? "▲" : c < 0 ? "▼" : "•"} ${Math.abs(c)}%`, cls: c === 0 ? "flat" : up === goodWhenUp ? "up" : "down" };
};

const kpis = computed(() => {
  const o = data.value.orders;
  const s = data.value.stock;
  const w = data.value.wastage;
  const short = s.out_of_stock + s.low_stock;
  const sc = canView("sc_orders") ? { name: "OrderList" } : null;
  return [
    {
      key: "orders", label: "Purchase orders", icon: "receipt", tone: "blue", value: o.this_period.toLocaleString("en-PH"), to: sc,
      trend: trend(o.this_period, o.previous_period), sub: `in ${days.value} days · ${o.total.toLocaleString("en-PH")} all time`,
    },
    {
      key: "pending", label: "Pending deliveries", icon: "truck", tone: "violet", value: o.open.toLocaleString("en-PH"), hero: o.open > 0, to: sc,
      sub: o.open ? `${money(o.open_value)} not arrived yet` : "Everything ordered has arrived",
      split: o.open ? [
        { label: "waiting to be confirmed", count: o.by_status.pending, cls: "s-wait" },
        { label: "being prepared", count: o.by_status.processing, cls: "s-prep" },
        { label: "on the way", count: o.by_status.shipped, cls: "s-road" },
      ] : null,
    },
    {
      key: "stock", label: "Low & out of stock", icon: "box", tone: short ? "amber" : "green", value: short.toLocaleString("en-PH"),
      to: canView("inventory_products") ? { name: "Products" } : null,
      sub: short ? `${s.out_of_stock} out · ${s.low_stock} running low` : `All ${s.products} products stocked`,
    },
    {
      key: "waste", label: "Wastage", icon: "trash", tone: "red", value: `${w.units.toLocaleString("en-PH")} stems`,
      trend: trend(w.units, w.previous_units, false), sub: `${money(w.cost)} thrown away`,
    },
  ];
});

const poStatuses = computed(() => {
  const b = data.value.orders.by_status;
  return [
    { key: "pending", label: "Waiting to be confirmed", count: b.pending, cls: "s-wait" },
    { key: "processing", label: "Being prepared", count: b.processing, cls: "s-prep" },
    { key: "shipped", label: "On the way", count: b.shipped, cls: "s-road" },
    { key: "done", label: "Arrived", count: b.received + b.completed, cls: "s-done" },
  ];
});

const wasteTrend = computed(() => trend(data.value.wastage.units, data.value.wastage.previous_units, false));

const alerts = computed(() => {
  const d = data.value;
  const list = [];
  const products = canView("inventory_products") ? { name: "Products" } : null;
  const floor = canView("warehouse") ? { name: "WarehouseFloor" } : null;
  const outs = d.stock.items.filter((p) => p.state === "out");
  const lows = d.stock.items.filter((p) => p.state === "low");
  const names = (rows, total) => `${rows.slice(0, 3).map((p) => p.name).join(", ")}${total > 3 ? ` and ${total - 3} more` : ""}`;

  if (d.stock.out_of_stock) {
    list.push({ key: "out", level: "critical", icon: "box", title: `${d.stock.out_of_stock} ${d.stock.out_of_stock === 1 ? "product is" : "products are"} out of stock`, detail: `${names(outs, d.stock.out_of_stock)}. Customers can't order these until you restock.`, to: products, action: "Restock" });
  }
  if (d.floor.spoiled_units) {
    list.push({ key: "spoiled", level: "critical", icon: "trash", title: `${d.floor.spoiled_units} stems are past their date`, detail: "They're still counted as stock. Cull them on the floor so orders don't use them.", to: floor, action: "Open floor" });
  }
  if (d.stock.low_stock) {
    list.push({ key: "low", level: "warning", icon: "alert", title: `${d.stock.low_stock} ${d.stock.low_stock === 1 ? "product is" : "products are"} running low`, detail: `${names(lows, d.stock.low_stock)} ${d.stock.low_stock === 1 ? "is" : "are"} at or under the minimum.`, to: products, action: "Review" });
  }
  if (d.floor.expiring_units) {
    list.push({ key: "expiring", level: "warning", icon: "clock", title: `${d.floor.expiring_units} stems expire by tomorrow`, detail: "Use them in today's orders first.", to: floor, action: "Open floor" });
  }
  const w = d.wastage;
  if (w.previous_units && w.units > w.previous_units * 1.25 && w.units - w.previous_units >= 5) {
    list.push({ key: "waste", level: "warning", icon: "activity", title: "More waste than usual", detail: `${w.units} stems thrown away, up from ${w.previous_units} in the ${days.value} days before.`, to: floor, action: "Open floor" });
  }
  return list;
});

function readDays() {
  try {
    const v = Number(localStorage.getItem("sc-dashboard-days"));
    return RANGES.some((r) => r.days === v) ? v : 30;
  } catch {
    return 30;
  }
}
function setDays(v) {
  if (days.value === v) return;
  days.value = v;
  try {
    localStorage.setItem("sc-dashboard-days", String(v));
  } catch {
    /* only a convenience */
  }
  load();
}

function shortDate(d) {
  return new Date(d).toLocaleDateString("en-PH", { month: "short", day: "numeric" });
}
function ago(at) {
  if (!at) return "—";
  const daysAgo = Math.floor((Date.now() - new Date(at).getTime()) / 86400000);
  if (daysAgo <= 0) return "today";
  if (daysAgo === 1) return "yesterday";
  if (daysAgo < 30) return `${daysAgo} days ago`;
  return shortDate(at);
}

async function load() {
  error.value = "";
  loading.value = true;
  try {
    const res = await api.get("procurement/supply-chain/analytics/dashboard", { params: { days: days.value } });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load the supply chain dashboard.");
  } finally {
    loading.value = false;
  }
}
onMounted(load);
</script>

<style scoped>
.sc-range {
  display: inline-flex;
  padding: 3px;
  border: 1px solid var(--v-border-strong);
  border-radius: 12px;
  background: var(--v-surface);
}
.sc-range button {
  min-height: 34px;
  padding: 0 12px;
  border: 0;
  border-radius: 9px;
  background: none;
  color: var(--v-text-2);
  font: inherit;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.sc-range button:hover {
  color: var(--v-text);
}
.sc-range button.on {
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.sc-range button:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}

/* KPIs */
.sc-kpi {
  color: inherit;
  text-decoration: none;
  transition: border-color 0.15s, transform 0.15s, box-shadow 0.15s;
}
.sc-kpi.link:hover {
  border-color: var(--v-border-strong);
  transform: translateY(-1px);
  box-shadow: 0 10px 24px -14px rgba(15, 23, 42, 0.35);
}
.sc-kpi.hero {
  background: linear-gradient(150deg, var(--bc-accent-soft) 0%, var(--v-surface) 60%);
  border-color: var(--bc-accent-muted);
}
.fx-kpi-sub {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.sc-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.sc-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.sc-split {
  display: flex;
  gap: 2px;
  height: 6px;
  margin-top: 8px;
  border-radius: 999px;
  overflow: hidden;
}
.sc-split span {
  flex-basis: 0;
  min-width: 4px;
}

/* Status colors, shared by the split bar and the legend */
.s-wait { background: #f59e0b; }
.s-prep { background: #8b5cf6; }
.s-road { background: #3b82f6; }
.s-done { background: #10b981; }

/* Alerts */
.sc-alerts {
  margin-bottom: 16px;
}
.sc-alert-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.sc-alert {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid var(--v-border);
  border-left-width: 3px;
  border-radius: 12px;
}
.sc-alert.critical {
  border-left-color: var(--v-red-text);
  background: color-mix(in srgb, var(--v-red-soft) 45%, transparent);
}
.sc-alert.warning {
  border-left-color: #d97706;
  background: color-mix(in srgb, var(--v-amber-soft, #fef3c7) 40%, transparent);
}
.sc-alert-ic {
  flex: none;
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: var(--v-subtle);
  color: var(--v-text-2);
}
.sc-alert.critical .sc-alert-ic {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.sc-alert.warning .sc-alert-ic {
  background: var(--v-amber-soft, #fef3c7);
  color: var(--v-amber-text, #b45309);
}
.sc-alert-ic :deep(svg) {
  width: 17px;
  height: 17px;
}
.sc-alert strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.sc-alert .grow > span {
  font-size: 12.5px;
  color: var(--v-text-2);
}
.sc-alert .sp-chip,
.sc-alert .sp-btn {
  flex: none;
}
.sc-clear {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border-radius: 12px;
  background: var(--v-green-soft);
}
.sc-clear-ic {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--v-surface);
  color: var(--v-green-text);
}
.sc-clear-ic :deep(svg) {
  width: 18px;
  height: 18px;
}
.sc-clear div {
  display: flex;
  flex-direction: column;
}
.sc-clear strong {
  font-size: 14px;
  color: var(--v-green-text);
}
.sc-clear span {
  font-size: 12.5px;
  color: var(--v-text-2);
}

/* Grids */
.sc-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
  margin-bottom: 16px;
}
.sc-grid.even {
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}
.sc-grid > section {
  min-width: 0;
}
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

/* Lists */
.sc-list {
  list-style: none;
  margin: 0;
  padding: 0;
  transition: opacity 0.2s;
}
.sc-list.dim {
  opacity: 0.55;
}
.sc-list > li + li {
  border-top: 1px solid var(--v-border);
}
.sc-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 4px;
  color: inherit;
  text-decoration: none;
  border-radius: 10px;
}
.sc-row.link:hover {
  background: var(--v-subtle);
}
.sc-row strong {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sc-row .grow > span {
  font-size: 12px;
  color: var(--v-muted);
}
.sc-row .grow > .sc-sub {
  color: var(--v-text-2);
}
.sc-row .sp-chip {
  flex: none;
}
.muted {
  font-weight: 500;
  color: var(--v-muted);
}
:deep(.sc-logo) {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 11px;
  object-fit: cover;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 12.5px;
  font-weight: 600;
}
.sc-meter {
  height: 6px;
  margin-top: 6px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.sc-meter span {
  display: block;
  height: 100%;
  min-width: 3px;
  border-radius: inherit;
  transition: width 0.4s ease;
}
.sc-meter.low span { background: #f59e0b; }
.sc-meter.out span { background: var(--v-red-text); }
.sc-meter.good span { background: var(--bc-accent); }
.sc-qty {
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 52px;
}
.sc-qty strong {
  font-size: 15px;
}
.sc-qty span {
  font-size: 11.5px;
  color: var(--v-faint);
}
.sc-facts {
  flex: none;
  display: flex;
  gap: 14px;
  margin: 0;
}
.sc-facts div {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}
.sc-facts dt {
  font-size: 11px;
  color: var(--v-faint);
}
.sc-facts dd {
  margin: 0;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}

/* PO status legend */
.sc-status {
  list-style: none;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px 16px;
  margin: 16px 0 0;
  padding: 14px 0 0;
  border-top: 1px solid var(--v-border);
}
.sc-status li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.sc-status strong {
  margin-left: auto;
  color: var(--v-text);
}
.sc-swatch {
  width: 10px;
  height: 10px;
  border-radius: 3px;
}

/* Wastage */
.sc-waste {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
  gap: 24px;
}
.sc-waste-nums {
  display: flex;
  flex-wrap: wrap;
  gap: 28px;
  margin: 0 0 14px;
}
.sc-waste-nums div {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
}
.sc-waste-nums dt {
  font-size: 12px;
  color: var(--v-muted);
}
.sc-waste-nums dd {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
  color: var(--v-text);
}
.sc-waste-nums dd small,
.sc-waste-nums > div > small {
  font-size: 12px;
  font-weight: 500;
  color: var(--v-faint);
}
.sc-waste-side h3 {
  margin: 0 0 8px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--v-muted);
}
.sc-waste-side h3 + * {
  margin-bottom: 18px;
}
.sc-mini {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 9px;
}
.sc-mini li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 90px 34px;
  align-items: center;
  gap: 10px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.sc-mini.dates li {
  grid-template-columns: minmax(0, 1fr) 34px auto;
}
.sc-mini li > span:first-child {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sc-mini small {
  color: var(--v-faint);
}
.sc-mini strong {
  text-align: right;
  color: var(--v-text);
}
.sc-bar {
  height: 6px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.sc-bar span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--v-red-text);
}

@media (max-width: 1100px) {
  .sc-grid,
  .sc-grid.even,
  .sc-waste {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 640px) {
  .sc-alert {
    flex-wrap: wrap;
  }
  .sc-alert .grow {
    flex-basis: calc(100% - 46px);
  }
  .sc-alert .sp-chip {
    margin-left: 46px;
  }
  .sc-facts {
    display: none;
  }
  .sc-status {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (prefers-reduced-motion: reduce) {
  .sc-kpi,
  .sc-meter span {
    transition: none;
  }
}
</style>
