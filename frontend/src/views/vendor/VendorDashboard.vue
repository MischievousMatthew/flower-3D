<template>
  <div class="vd-page">
    <VendorSidebar />
    <vendorHeader title="Dashboard" />
    <main class="vd-main">
      <!-- Heading -->
      <header class="vd-head">
        <div>
          <h1>{{ greeting }}</h1>
          <p>Here's how your shop is doing{{ data ? ` · ${rangeText}` : "" }}.</p>
        </div>
        <div class="vd-range" role="tablist" aria-label="Time range">
          <button
            v-for="r in RANGES"
            :key="r.value"
            type="button"
            role="tab"
            :aria-selected="range === r.value"
            :class="{ on: range === r.value }"
            @click="setRange(r.value)"
          >
            {{ r.label }}
          </button>
        </div>
      </header>

      <p v-if="error" class="vd-error">
        {{ error }} <button type="button" @click="load">Try again</button>
      </p>

      <!-- KPI cards -->
      <section class="vd-kpis" aria-label="Key numbers">
        <template v-if="!data">
          <div v-for="n in 4" :key="n" class="vd-card vd-kpi"><div class="bc-skeleton sk-line w40"></div><div class="bc-skeleton sk-big"></div><div class="bc-skeleton sk-line w60"></div></div>
        </template>
        <template v-else>
          <article class="vd-card vd-kpi">
            <div class="kpi-top"><span class="kpi-icon green" v-html="ICONS.sales"></span><span class="kpi-label">Total sales</span></div>
            <template v-if="data.sales">
              <strong class="kpi-value">{{ money(data.sales.total) }}</strong>
              <span class="kpi-sub">
                <Trend :value="data.sales.change_pct" />
                {{ data.sales.count }} {{ data.sales.count === 1 ? "sale" : "sales" }} · avg {{ money(data.sales.average) }}
              </span>
            </template>
            <LockedNote v-else module="orders" />
          </article>

          <article class="vd-card vd-kpi">
            <div class="kpi-top"><span class="kpi-icon teal" v-html="ICONS.revenue"></span><span class="kpi-label">Revenue</span></div>
            <template v-if="data.revenue">
              <strong class="kpi-value">{{ money(data.revenue.net) }}</strong>
              <span class="kpi-sub">
                <Trend :value="data.revenue.change_pct" />
                <template v-if="data.revenue.refunded">{{ money(data.revenue.refunded) }} refunded</template>
                <template v-else>received, after refunds</template>
              </span>
            </template>
            <LockedNote v-else module="finance" />
          </article>

          <article class="vd-card vd-kpi">
            <div class="kpi-top"><span class="kpi-icon blue" v-html="ICONS.orders"></span><span class="kpi-label">Orders</span></div>
            <template v-if="data.orders">
              <strong class="kpi-value">{{ data.orders.total }}</strong>
              <span class="kpi-sub">
                <router-link :to="{ name: 'VendorReservation' }" class="kpi-link" :class="{ warn: data.orders.pending }">
                  {{ data.orders.pending }} pending
                </router-link>
                · {{ data.orders.processing }} in progress · {{ data.orders.completed }} completed
              </span>
            </template>
            <LockedNote v-else module="orders" />
          </article>

          <article class="vd-card vd-kpi">
            <div class="kpi-top"><span class="kpi-icon amber" v-html="ICONS.products"></span><span class="kpi-label">Products</span></div>
            <template v-if="data.products">
              <strong class="kpi-value">{{ data.products.total }}</strong>
              <span class="kpi-sub">
                {{ data.products.active }} live
                <template v-if="stockAlerts"> · <span class="warn-text">{{ stockAlerts }} need restocking</span></template>
                <template v-else> · stock looks good</template>
              </span>
            </template>
            <LockedNote v-else module="products" />
          </article>
        </template>
      </section>

      <div class="vd-grid">
        <!-- Sales chart -->
        <section class="vd-card vd-chart span-2" aria-label="Sales chart">
          <div class="card-head">
            <div>
              <h2>Sales</h2>
              <p>{{ data?.chart?.unit === "month" ? "Per month" : "Per day" }}, paid or completed orders</p>
            </div>
            <div v-if="data?.chart" class="chart-total">
              <strong>{{ money(data.sales.total) }}</strong>
              <span>{{ data.sales.count }} orders</span>
            </div>
          </div>
          <div v-if="!data" class="bc-skeleton sk-chart"></div>
          <LockedNote v-else-if="!data.chart" module="orders" block />
          <div v-else class="chart-wrap" @mouseleave="hover = null">
            <svg :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none" class="chart-svg" aria-hidden="true">
              <defs>
                <linearGradient id="vdArea" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#48bb78" stop-opacity="0.32" />
                  <stop offset="100%" stop-color="#48bb78" stop-opacity="0" />
                </linearGradient>
              </defs>
              <line v-for="g in 4" :key="g" x1="0" :x2="W" :y1="(H - PAD) * (g / 4)" :y2="(H - PAD) * (g / 4)" class="grid" />
              <path :d="chart.area" fill="url(#vdArea)" />
              <path :d="chart.line" class="line" />
              <g v-if="hover !== null">
                <line :x1="chart.xs[hover]" :x2="chart.xs[hover]" y1="0" :y2="H - PAD" class="cursor" />
                <circle :cx="chart.xs[hover]" :cy="chart.ys[hover]" r="5" class="dot" />
              </g>
            </svg>
            <div class="chart-hit">
              <span v-for="(p, i) in points" :key="p.key" @mouseenter="hover = i" @focus="hover = i" tabindex="0"></span>
            </div>
            <div
              v-if="hover !== null"
              class="chart-tip"
              :style="{ left: `${(chart.xs[hover] / W) * 100}%` }"
            >
              <strong>{{ money(points[hover].sales) }}</strong>
              <span>{{ points[hover].label }} · {{ points[hover].orders }} {{ points[hover].orders === 1 ? "order" : "orders" }}</span>
            </div>
            <div class="chart-axis">
              <template v-for="(p, i) in points" :key="p.key">
                <span v-if="showLabel(i)" :style="axisStyle(i)" :class="{ minor: minorLabel(i) }">{{ p.label }}</span>
              </template>
            </div>
            <p v-if="!data.sales.count" class="chart-empty">No sales in this period yet.</p>
          </div>
        </section>

        <!-- Subscription -->
        <section class="vd-card vd-plan" aria-label="Subscription">
          <template v-if="!data">
            <div class="bc-skeleton sk-line w40"></div><div class="bc-skeleton sk-big"></div><div class="bc-skeleton sk-block"></div>
          </template>
          <template v-else>
            <div class="plan-top">
              <span class="plan-eyebrow">Your plan</span>
              <span class="plan-status" :class="`st-${planState}`">{{ PLAN_STATUS[planState] }}</span>
            </div>
            <h2 class="plan-name">{{ sub.plan_name ? `${sub.plan_name}` : "No plan yet" }}</h2>
            <p class="plan-meta">
              <template v-if="sub.status === 'trialing' && sub.days_left !== null">Free trial · {{ sub.days_left }} {{ sub.days_left === 1 ? "day" : "days" }} left</template>
              <template v-else-if="sub.ends_at && sub.active">Renews {{ formatDate(sub.ends_at) }}</template>
              <template v-else-if="sub.ends_at">Ended {{ formatDate(sub.ends_at) }}</template>
              <template v-else-if="sub.monthly_price">{{ money(sub.monthly_price) }}/month</template>
              <template v-else>Choose a plan to unlock the shop modules.</template>
            </p>
            <div v-if="sub.status === 'trialing' && sub.days_left !== null" class="plan-bar">
              <span :style="{ width: `${Math.min(100, (sub.days_left / 30) * 100)}%` }"></span>
            </div>
            <ul v-if="limits.length" class="plan-limits">
              <li v-for="l in limits" :key="l.key">
                <span>{{ l.label }}</span>
                <span class="lim-num">{{ l.usage }}<template v-if="!l.unlimited"> / {{ l.limit }}</template></span>
                <div v-if="!l.unlimited" class="lim-bar"><span :class="{ full: l.usage >= l.limit }" :style="{ width: `${l.limit ? Math.min(100, (l.usage / l.limit) * 100) : 100}%` }"></span></div>
              </li>
            </ul>
            <router-link to="/pricing" class="plan-btn">{{ sub.active ? "Compare plans" : "View plans" }}</router-link>
          </template>
        </section>

        <!-- Top-selling flowers -->
        <section class="vd-card" aria-label="Top-selling flowers">
          <div class="card-head"><div><h2>Top-selling flowers</h2><p>By quantity sold</p></div></div>
          <ul v-if="!data" class="vd-list"><li v-for="n in 4" :key="n"><div class="bc-skeleton sk-thumb"></div><div class="sk-grow"><div class="bc-skeleton sk-line w60"></div><div class="bc-skeleton sk-line w40"></div></div></li></ul>
          <LockedNote v-else-if="!data.top_flowers" module="orders" block />
          <p v-else-if="!data.top_flowers.length" class="vd-empty">No flowers sold in this period yet.</p>
          <ol v-else class="vd-list ranked">
            <li v-for="(f, i) in data.top_flowers" :key="i">
              <span class="rank">{{ i + 1 }}</span>
              <img v-if="f.image" :src="f.image" :alt="f.name" class="thumb" loading="lazy" />
              <span v-else class="thumb blank" v-html="ICONS.flower"></span>
              <div class="li-main">
                <strong>{{ f.name }}</strong>
                <span>{{ f.category ? titleCase(f.category) : "Flower" }} · {{ money(f.total) }}</span>
              </div>
              <div class="li-end">
                <strong>{{ f.quantity }}</strong><span>sold</span>
              </div>
              <div class="share"><span :style="{ width: `${(f.quantity / topQty) * 100}%` }"></span></div>
            </li>
          </ol>
        </section>

        <!-- Low stock -->
        <section class="vd-card" aria-label="Stock alerts">
          <div class="card-head">
            <div><h2>Stock alerts</h2><p>Low or out of stock</p></div>
            <router-link v-if="data?.products" :to="{ name: 'VendorInventory' }" class="head-link">Inventory →</router-link>
          </div>
          <ul v-if="!data" class="vd-list"><li v-for="n in 3" :key="n"><div class="bc-skeleton sk-thumb"></div><div class="sk-grow"><div class="bc-skeleton sk-line w60"></div><div class="bc-skeleton sk-line w40"></div></div></li></ul>
          <LockedNote v-else-if="!data.products" module="products" block />
          <div v-else-if="!data.products.alerts.length" class="vd-ok">
            <span v-html="ICONS.check"></span>
            <p>Every product is above its minimum stock.</p>
          </div>
          <ul v-else class="vd-list">
            <li v-for="(p, i) in data.products.alerts" :key="i">
              <img v-if="p.image" :src="p.image" :alt="p.name" class="thumb" loading="lazy" />
              <span v-else class="thumb blank" v-html="ICONS.products"></span>
              <div class="li-main">
                <strong>{{ p.name }}</strong>
                <span>{{ p.min !== null ? `Minimum ${p.min}` : "No minimum set" }}</span>
              </div>
              <span class="stock-pill" :class="p.out ? 'out' : 'low'">{{ p.out ? "Out of stock" : `${p.stock} left` }}</span>
            </li>
          </ul>
        </section>

        <!-- Quick actions -->
        <section class="vd-card" aria-label="Quick actions">
          <div class="card-head"><div><h2>Quick actions</h2><p>Jump straight in</p></div></div>
          <div class="vd-actions">
            <button v-for="a in ACTIONS" :key="a.label" type="button" class="action" :class="{ locked: isLocked(a.module) }" @click="go(a)">
              <span class="action-icon" :class="a.tone" v-html="ICONS[a.icon]"></span>
              <span class="action-label">{{ a.label }}</span>
              <span v-if="isLocked(a.module)" class="action-lock" aria-label="Not in your plan">🔒</span>
            </button>
          </div>
        </section>

        <!-- Recent transactions -->
        <section class="vd-card span-2" aria-label="Recent transactions">
          <div class="card-head">
            <div><h2>Recent transactions</h2><p>Where your money came from and where it went</p></div>
            <div v-if="data?.transactions?.length" class="txn-totals">
              <span class="in">In {{ money(txnTotals.in) }}</span>
              <span class="out">Out {{ money(txnTotals.out) }}</span>
            </div>
          </div>
          <ul v-if="!data" class="vd-list"><li v-for="n in 4" :key="n"><div class="bc-skeleton sk-thumb round"></div><div class="sk-grow"><div class="bc-skeleton sk-line w60"></div><div class="bc-skeleton sk-line w40"></div></div></li></ul>
          <LockedNote v-else-if="!data.transactions" module="finance" block />
          <p v-else-if="!data.transactions.length" class="vd-empty">No transactions yet. They appear here once customers pay.</p>
          <ul v-else class="vd-list">
            <li v-for="t in data.transactions" :key="t.key">
              <span class="txn-icon" :class="t.type" v-html="t.type === 'credit' ? ICONS.down : ICONS.up"></span>
              <div class="li-main">
                <strong>
                  {{ t.title }}
                  <span class="txn-kind" :class="t.type">{{ t.type === "credit" ? "Income" : "Expense" }}</span>
                </strong>
                <span class="txn-party">{{ t.party }}</span>
                <span>
                  <template v-if="t.order_number">{{ t.order_number }} · </template>{{ formatDate(t.created_at, true) }}
                </span>
              </div>
              <span class="txn-amt" :class="t.type">{{ t.type === "credit" ? "+" : "−" }}{{ money(t.amount) }}</span>
            </li>
          </ul>
        </section>

        <!-- Recent orders -->
        <section class="vd-card" aria-label="Recent orders">
          <div class="card-head">
            <div><h2>Latest orders</h2><p>Most recent first</p></div>
            <router-link v-if="data?.orders" :to="{ name: 'VendorReservation' }" class="head-link">All orders →</router-link>
          </div>
          <ul v-if="!data" class="vd-list"><li v-for="n in 4" :key="n"><div class="sk-grow"><div class="bc-skeleton sk-line w60"></div><div class="bc-skeleton sk-line w40"></div></div></li></ul>
          <LockedNote v-else-if="!data.orders" module="orders" block />
          <p v-else-if="!data.orders.recent.length" class="vd-empty">No orders yet.</p>
          <ul v-else class="vd-list compact">
            <li v-for="o in data.orders.recent" :key="o.order_number">
              <div class="li-main">
                <strong>{{ o.customer || "Customer" }}</strong>
                <span>{{ o.order_number }} · {{ timeAgo(o.created_at) }}</span>
              </div>
              <div class="li-end">
                <strong>{{ money(o.total) }}</strong>
                <span class="order-pill" :class="`os-${o.status}`">{{ titleCase(o.status) }}</span>
              </div>
            </li>
          </ul>
        </section>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import api from "../../plugins/axios";
import vendorHeader from "../../layouts/vendorHeader.vue";
import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";
import { useVendorProfile } from "../../composables/useVendorProfile";
import "../../components/vendor/shop/shop.css";

const router = useRouter();
const { vendorProfile } = useVendorProfile({ autoFetch: true, showToast: false });

const RANGES = [
  { value: "7d", label: "7 days" },
  { value: "30d", label: "30 days" },
  { value: "month", label: "This month" },
  { value: "year", label: "This year" },
];
const MODULE_NAME = { orders: "Orders", products: "Products", finance: "Finance" };
const PLAN_STATUS = { active: "Active", trial: "Free trial", inactive: "Inactive", none: "No plan" };
const LIMIT_LABEL = { branches: "Branches", warehouses: "Warehouses", staff_employees: "Staff" };

const icon = (d) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${d}</svg>`;
const ICONS = {
  sales: icon('<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>'),
  revenue: icon('<rect x="2" y="6" width="20" height="13" rx="2"/><circle cx="12" cy="12.5" r="2.5"/><path d="M6 10v.01M18 15v.01"/>'),
  orders: icon('<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>'),
  products: icon('<path d="m21 8-9-5-9 5 9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>'),
  flower: icon('<circle cx="12" cy="9" r="2.5"/><path d="M12 6.5a3 3 0 1 1 3 3 3 3 0 1 1-3 3 3 3 0 1 1-3-3 3 3 0 1 1 3-3Z"/><path d="M12 13v8M12 17c-2 0-3.5-1-4-3M12 18c2 0 3.5-1 4-3"/>'),
  plus: icon('<path d="M12 5v14M5 12h14"/>'),
  calendar: icon('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'),
  cube: icon('<path d="M21 16V8l-9-5-9 5v8l9 5 9-5Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>'),
  chat: icon('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>'),
  team: icon('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'),
  help: icon('<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>'),
  check: icon('<circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/>'),
  down: icon('<path d="M12 5v14M5 12l7 7 7-7"/>'),
  up: icon('<path d="M12 19V5M5 12l7-7 7 7"/>'),
};
const ACTIONS = [
  { label: "Add product", icon: "plus", tone: "green", to: { name: "VendorAddProduct" }, module: "products" },
  { label: "Manage orders", icon: "orders", tone: "blue", to: { name: "VendorReservation" }, module: "orders" },
  { label: "Reservations", icon: "calendar", tone: "violet", to: { name: "VendorCalendar" }, module: "calendar" },
  { label: "3D bouquets", icon: "cube", tone: "teal", to: { name: "VendorCustomization" }, module: "products" },
  { label: "Customer chat", icon: "chat", tone: "amber", to: { name: "VendorChat" }, module: "crm" },
  { label: "Employees", icon: "team", tone: "rose", to: { name: "VendorStaffList" } },
  { label: "Help & support", icon: "help", tone: "slate", to: { name: "VendorSupport" } },
];

const range = ref("30d");
const data = ref(null);
const error = ref("");
const hover = ref(null);
const modules = ref(null);
let requestSeq = 0;

// Small presentational pieces
const Trend = defineComponent({
  props: { value: { type: Number, default: null } },
  setup: (props) => () =>
    props.value === null
      ? h("span", { class: "trend new" }, "New")
      : h("span", { class: ["trend", props.value > 0 ? "up" : props.value < 0 ? "down" : "flat"] },
          `${props.value > 0 ? "▲" : props.value < 0 ? "▼" : "•"} ${Math.abs(props.value)}%`),
});
const LockedNote = defineComponent({
  props: { module: String, block: Boolean },
  setup: (props) => () =>
    h("p", { class: ["locked-note", { block: props.block }] }, [
      h("span", "🔒"),
      ` ${MODULE_NAME[props.module] ?? "This module"} isn't in your plan. `,
      h("a", { href: "/pricing", onClick: (e) => { e.preventDefault(); router.push("/pricing"); } }, "See plans"),
    ]),
});

async function load() {
  const seq = ++requestSeq;
  error.value = "";
  try {
    const res = await api.get("vendor/dashboard/overview", { params: { range: range.value } });
    if (seq !== requestSeq) return;
    data.value = res.data.data;
    hover.value = null;
  } catch (e) {
    if (seq !== requestSeq) return;
    error.value = e?.response?.data?.message || "Couldn't load your dashboard.";
  }
}
function setRange(value) {
  if (range.value === value) return;
  range.value = value;
  data.value = null;
  load();
}

const sub = computed(() => data.value?.subscription ?? {});
const planState = computed(() => {
  if (!sub.value.plan_name) return "none";
  if (!sub.value.active) return "inactive";
  return sub.value.status === "trialing" ? "trial" : "active";
});
const limits = computed(() =>
  Object.entries(sub.value.limits ?? {}).map(([key, l]) => ({ key, ...l, label: LIMIT_LABEL[key] ?? l.label }))
);
const txnTotals = computed(() =>
  (data.value?.transactions ?? []).reduce(
    (acc, t) => (t.type === "credit" ? { ...acc, in: acc.in + t.amount } : { ...acc, out: acc.out + t.amount }),
    { in: 0, out: 0 },
  ),
);
const stockAlerts = computed(() => (data.value?.products ? data.value.products.low_stock + data.value.products.out_of_stock : 0));
const topQty = computed(() => Math.max(1, ...(data.value?.top_flowers ?? []).map((f) => f.quantity)));
const isLocked = (module) => !!module && !!data.value && (data.value.locked.includes(module) || (modules.value && !modules.value.includes(module)));

const greeting = computed(() => {
  const hour = new Date().getHours();
  const part = hour < 12 ? "Good morning" : hour < 18 ? "Good afternoon" : "Good evening";
  const name = vendorProfile.value?.store_name?.trim();
  return name ? `${part}, ${name}` : part;
});
const rangeText = computed(() => {
  if (!data.value) return "";
  const opts = { month: "short", day: "numeric" };
  const from = new Date(`${data.value.from}T00:00:00`).toLocaleDateString("en-PH", opts);
  const to = new Date(`${data.value.to}T00:00:00`).toLocaleDateString("en-PH", opts);
  return `${from} – ${to}`;
});

function go(action) {
  if (isLocked(action.module)) {
    router.push("/pricing");
    return;
  }
  router.push(action.to);
}

// Chart geometry
const W = 640;
const H = 220;
const PAD = 8;
const points = computed(() => data.value?.chart?.points ?? []);
const chart = computed(() => {
  const pts = points.value;
  const max = Math.max(1, ...pts.map((p) => p.sales)) * 1.12;
  const step = pts.length > 1 ? W / (pts.length - 1) : 0;
  const xs = pts.map((_, i) => (pts.length > 1 ? i * step : W / 2));
  const ys = pts.map((p) => (H - PAD) - (p.sales / max) * (H - PAD - 6));
  if (!pts.length) return { xs, ys, line: "", area: "" };
  let line = `M${xs[0]},${ys[0]}`;
  for (let i = 1; i < pts.length; i++) {
    const cx = (xs[i - 1] + xs[i]) / 2;
    line += ` C${cx},${ys[i - 1]} ${cx},${ys[i]} ${xs[i]},${ys[i]}`;
  }
  const area = `${line} L${xs[xs.length - 1]},${H - PAD} L${xs[0]},${H - PAD} Z`;
  return { xs, ys, line, area };
});
// Evenly spaced dates (never two crowded together), first and last always shown
const axisIndexes = computed(() => {
  const n = points.value.length;
  const count = Math.min(n, 7);
  if (count <= 1) return new Set(n ? [0] : []);
  return new Set(Array.from({ length: count }, (_, k) => Math.round((k * (n - 1)) / (count - 1))));
});
const showLabel = (i) => axisIndexes.value.has(i);
// Every other middle date, dropped on narrow screens
const minorLabel = (i) => {
  const list = [...axisIndexes.value];
  const k = list.indexOf(i);
  return k > 0 && k < list.length - 1 && k % 2 === 1;
};
// Place each date under its point; the ends hug the card edges so nothing spills out
const axisStyle = (i) => {
  const n = points.value.length;
  if (n <= 1) return { left: "50%", transform: "translateX(-50%)" };
  if (i === 0) return { left: "0" };
  if (i === n - 1) return { right: "0" };
  return { left: `${(i / (n - 1)) * 100}%`, transform: "translateX(-50%)" };
};

// Formatting
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", maximumFractionDigits: 2 });
const money = (v) => peso.format(Number(v || 0));
const titleCase = (s) => String(s ?? "").replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
const formatDate = (iso, withTime = false) =>
  iso
    ? new Date(iso).toLocaleString("en-PH", withTime
      ? { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" }
      : { month: "short", day: "numeric", year: "numeric" })
    : "";
function timeAgo(iso) {
  const mins = Math.round((Date.now() - new Date(iso)) / 60000);
  if (mins < 1) return "just now";
  if (mins < 60) return `${mins} min ago`;
  const hours = Math.round(mins / 60);
  if (hours < 24) return `${hours} h ago`;
  const days = Math.round(hours / 24);
  return days < 30 ? `${days} d ago` : formatDate(iso);
}

onMounted(async () => {
  load();
  try {
    const res = await api.get("subscription/access");
    modules.value = res.data?.data?.modules ?? null;
  } catch {
    modules.value = null;
  }
});
</script>

<style scoped>
.vd-page {
  margin-left: 260px;
  min-height: 100vh;
  background: #f6f8f7;
  font-family: "Poppins", sans-serif;
  color: #1a202c;
  transition: margin-left 0.3s ease;
}
.vd-main {
  max-width: 1240px;
  margin: 0 auto;
  padding: 28px 32px 56px;
}

/* Heading */
.vd-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 22px;
}
.vd-head h1 {
  margin: 0;
  font-size: 24px;
  font-weight: 600;
  letter-spacing: -0.01em;
}
.vd-head p {
  margin: 4px 0 0;
  font-size: 13.5px;
  color: #718096;
}
.vd-range {
  display: inline-flex;
  padding: 4px;
  gap: 2px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}
.vd-range button {
  border: 0;
  background: none;
  padding: 7px 14px;
  border-radius: 9px;
  font: inherit;
  font-size: 12.5px;
  font-weight: 500;
  color: #4a5568;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.vd-range button:hover {
  color: #1a202c;
}
.vd-range button.on {
  background: #48bb78;
  color: #fff;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.35);
}
.vd-error {
  padding: 12px 16px;
  margin: 0 0 18px;
  border-radius: 12px;
  background: #fff5f5;
  color: #c53030;
  font-size: 13.5px;
}
.vd-error button {
  margin-left: 8px;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

/* Cards */
.vd-card {
  background: #fff;
  border: 1px solid #edf1ef;
  border-radius: 18px;
  padding: 20px;
  box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
  min-width: 0;
}
.card-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 14px;
}
.card-head h2 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
}
.card-head p {
  margin: 2px 0 0;
  font-size: 12px;
  color: #718096;
}
.head-link {
  font-size: 12.5px;
  font-weight: 500;
  color: #2f855a;
  text-decoration: none;
  white-space: nowrap;
}
.head-link:hover {
  text-decoration: underline;
}

/* KPIs */
.vd-kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}
.vd-kpi {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.kpi-top {
  display: flex;
  align-items: center;
  gap: 10px;
}
.kpi-icon {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 11px;
}
.kpi-icon :deep(svg),
.action-icon :deep(svg),
.thumb.blank :deep(svg),
.txn-icon :deep(svg),
.vd-ok :deep(svg) {
  width: 18px;
  height: 18px;
}
.green { background: #e8f8ef; color: #2f855a; }
.teal { background: #e6fffa; color: #2c7a7b; }
.blue { background: #ebf4ff; color: #2b6cb0; }
.amber { background: #fffaf0; color: #c05621; }
.violet { background: #f3f0ff; color: #6b46c1; }
.rose { background: #fff5f7; color: #b83280; }
.slate { background: #f1f5f9; color: #475569; }
.kpi-label {
  font-size: 13px;
  font-weight: 500;
  color: #4a5568;
}
.kpi-value {
  font-size: 26px;
  font-weight: 600;
  letter-spacing: -0.02em;
  line-height: 1.15;
}
.kpi-sub {
  font-size: 12px;
  color: #718096;
  line-height: 1.5;
}
.kpi-link {
  color: inherit;
  font-weight: 500;
  text-decoration: none;
}
.kpi-link.warn {
  color: #c05621;
  font-weight: 600;
}
.kpi-link:hover {
  text-decoration: underline;
}
.warn-text {
  color: #c05621;
  font-weight: 600;
}
:deep(.trend) {
  display: inline-block;
  margin-right: 4px;
  padding: 1px 7px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
}
:deep(.trend.up) { background: #e8f8ef; color: #2f855a; }
:deep(.trend.down) { background: #fff5f5; color: #c53030; }
:deep(.trend.flat),
:deep(.trend.new) { background: #f1f5f9; color: #64748b; }
:deep(.locked-note) {
  margin: 4px 0 0;
  font-size: 12.5px;
  color: #718096;
}
:deep(.locked-note.block) {
  padding: 26px 12px;
  text-align: center;
  background: #f8faf9;
  border-radius: 12px;
}
:deep(.locked-note a) {
  color: #2f855a;
  font-weight: 600;
}

/* Grid */
.vd-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}
.span-2 {
  grid-column: span 2;
}

/* Chart */
.chart-total {
  text-align: right;
}
.chart-total strong {
  display: block;
  font-size: 18px;
  font-weight: 600;
}
.chart-total span {
  font-size: 12px;
  color: #718096;
}
.chart-wrap {
  position: relative;
}
.chart-svg {
  display: block;
  width: 100%;
  height: 220px;
  overflow: visible;
}
.chart-svg .grid {
  stroke: #edf2f7;
  stroke-width: 1;
  vector-effect: non-scaling-stroke;
}
.chart-svg .line {
  fill: none;
  stroke: #38a169;
  stroke-width: 2.5;
  vector-effect: non-scaling-stroke;
}
.chart-svg .cursor {
  stroke: #9ae6b4;
  stroke-dasharray: 4 4;
  vector-effect: non-scaling-stroke;
}
.chart-svg .dot {
  fill: #fff;
  stroke: #38a169;
  stroke-width: 3;
  vector-effect: non-scaling-stroke;
}
.chart-hit {
  position: absolute;
  inset: 0 0 24px;
  display: flex;
}
.chart-hit span {
  flex: 1;
  outline: none;
}
.chart-tip {
  position: absolute;
  top: -6px;
  transform: translateX(-50%);
  padding: 7px 11px;
  border-radius: 10px;
  background: #1a202c;
  color: #fff;
  font-size: 12px;
  white-space: nowrap;
  pointer-events: none;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
}
.chart-tip strong {
  display: block;
  font-size: 13px;
}
.chart-tip span {
  color: #cbd5e0;
}
.chart-axis {
  position: relative;
  height: 16px;
  margin-top: 8px;
  font-size: 11px;
  color: #a0aec0;
  overflow: hidden;
}
.chart-axis span {
  position: absolute;
  top: 0;
  white-space: nowrap;
}
@media (max-width: 560px) {
  .chart-axis .minor {
    display: none;
  }
}
.chart-empty {
  position: absolute;
  inset: 0 0 24px;
  display: grid;
  place-items: center;
  margin: 0;
  font-size: 13px;
  color: #a0aec0;
  pointer-events: none;
}

/* Plan */
.vd-plan {
  display: flex;
  flex-direction: column;
  gap: 10px;
  background: linear-gradient(160deg, #f0fff4 0%, #fff 55%);
}
.plan-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.plan-eyebrow {
  font-size: 12px;
  font-weight: 500;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.plan-status {
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 600;
}
.st-active { background: #c6f6d5; color: #22543d; }
.st-trial { background: #fefcbf; color: #744210; }
.st-inactive { background: #fed7d7; color: #822727; }
.st-none { background: #edf2f7; color: #4a5568; }
.plan-name {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
}
.plan-meta {
  margin: 0;
  font-size: 13px;
  color: #4a5568;
}
.plan-bar,
.lim-bar {
  height: 6px;
  border-radius: 999px;
  background: #e2e8f0;
  overflow: hidden;
}
.plan-bar span,
.lim-bar span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #48bb78;
}
.lim-bar span.full {
  background: #ed8936;
}
.plan-limits {
  list-style: none;
  margin: 6px 0 0;
  padding: 0;
  display: grid;
  gap: 10px;
}
.plan-limits li {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 4px 8px;
  font-size: 12.5px;
  color: #4a5568;
}
.plan-limits .lim-bar {
  grid-column: 1 / -1;
}
.lim-num {
  font-weight: 600;
  color: #1a202c;
}
.plan-btn {
  margin-top: auto;
  padding: 10px;
  border-radius: 11px;
  background: #1a202c;
  color: #fff;
  text-align: center;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.15s;
}
.plan-btn:hover {
  background: #2d3748;
}

/* Lists */
.vd-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 4px;
}
.vd-list li {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 9px 8px;
  border-radius: 12px;
  transition: background 0.15s;
}
.vd-list li:hover {
  background: #f8faf9;
}
.vd-list.compact li {
  padding: 8px;
}
.ranked li {
  padding-bottom: 14px;
}
.rank {
  width: 18px;
  font-size: 12px;
  font-weight: 600;
  color: #a0aec0;
  text-align: center;
}
.thumb {
  flex: none;
  width: 40px;
  height: 40px;
  border-radius: 11px;
  object-fit: cover;
  background: #f0fff4;
}
.thumb.blank {
  display: grid;
  place-items: center;
  color: #48bb78;
}
.li-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.li-main strong {
  font-size: 13.5px;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.li-main span {
  font-size: 12px;
  color: #718096;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.li-end {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}
.li-end strong {
  font-size: 13.5px;
  font-weight: 600;
}
.li-end > span {
  font-size: 11.5px;
  color: #a0aec0;
}
.share {
  position: absolute;
  left: 38px;
  right: 8px;
  bottom: 5px;
  height: 3px;
  border-radius: 999px;
  background: #edf2f7;
}
.share span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #68d391;
}
.stock-pill,
.order-pill {
  flex: none;
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 600;
}
.stock-pill.low { background: #fffaf0; color: #c05621; }
.stock-pill.out { background: #fff5f5; color: #c53030; }
.order-pill { background: #edf2f7; color: #4a5568; }
.os-pending { background: #fefcbf; color: #744210; }
.os-processing { background: #ebf4ff; color: #2b6cb0; }
.os-completed { background: #c6f6d5; color: #22543d; }
.os-cancelled,
.os-returned,
.os-refunded { background: #fff5f5; color: #c53030; }
.txn-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
}
.txn-icon.credit { background: #e8f8ef; color: #2f855a; }
.txn-icon.debit { background: #fff5f5; color: #c53030; }
.txn-kind {
  margin-left: 6px;
  padding: 1px 7px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 600;
  vertical-align: 1px;
}
.txn-kind.credit { background: #e8f8ef; color: #2f855a; }
.txn-kind.debit { background: #fff5f5; color: #c53030; }
.li-main .txn-party {
  color: #2d3748;
  font-weight: 500;
}
.txn-totals {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
  font-size: 12px;
  font-weight: 600;
}
.txn-totals span {
  padding: 4px 10px;
  border-radius: 999px;
  white-space: nowrap;
}
.txn-totals .in { background: #e8f8ef; color: #2f855a; }
.txn-totals .out { background: #fff5f5; color: #c53030; }
.txn-amt {
  font-size: 13.5px;
  font-weight: 600;
  white-space: nowrap;
}
.txn-amt.credit { color: #2f855a; }
.txn-amt.debit { color: #c53030; }
.vd-empty {
  margin: 0;
  padding: 26px 12px;
  text-align: center;
  font-size: 13px;
  color: #a0aec0;
}
.vd-ok {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 22px 12px;
  color: #48bb78;
}
.vd-ok :deep(svg) {
  width: 30px;
  height: 30px;
}
.vd-ok p {
  margin: 0;
  font-size: 13px;
  color: #718096;
}

/* Quick actions */
.vd-actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.action {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border: 1px solid #edf2f7;
  border-radius: 13px;
  background: #fff;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, transform 0.15s, box-shadow 0.15s;
}
.action:hover {
  border-color: #9ae6b4;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.12);
}
.action.locked {
  opacity: 0.6;
}
.action-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 10px;
}
.action-label {
  font-size: 12.5px;
  font-weight: 500;
  color: #2d3748;
  line-height: 1.3;
}
.action-lock {
  position: absolute;
  top: 6px;
  right: 8px;
  font-size: 10px;
}

/* Skeletons */
.sk-line {
  height: 12px;
  margin: 4px 0;
}
.sk-big {
  height: 28px;
  width: 55%;
}
.sk-block {
  height: 90px;
}
.sk-chart {
  height: 240px;
}
.sk-thumb {
  flex: none;
  width: 40px;
  height: 40px;
}
.sk-thumb.round {
  border-radius: 50%;
}
.sk-grow {
  flex: 1;
}
.w40 { width: 40%; }
.w60 { width: 60%; }

/* Responsive */
@media (max-width: 1180px) {
  .vd-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .vd-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 968px) {
  .vd-page {
    margin-left: 0;
  }
  .vd-main {
    padding: 20px 16px 48px;
  }
}
@media (max-width: 720px) {
  .vd-kpis,
  .vd-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .span-2 {
    grid-column: auto;
  }
  .vd-range {
    width: 100%;
  }
  .vd-range button {
    flex: 1;
    padding: 7px 6px;
  }
}
</style>
