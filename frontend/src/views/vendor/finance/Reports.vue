<template>
  <ShopLayout section="finance" title="Financial Reports" subtitle="Daily, weekly, monthly and yearly totals you can export.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="!!exporting || !data" @click="exportAs('csv')">
        <span v-html="ICONS.download"></span>{{ exporting === "csv" ? "Preparing…" : "CSV" }}
      </button>
      <button type="button" class="sp-btn sp-btn-dark" :disabled="!!exporting || !data" @click="exportAs('pdf')">
        <span v-html="ICONS.print"></span>{{ exporting === "pdf" ? "Preparing…" : "PDF" }}
      </button>
    </template>

    <div class="sp-toolbar">
      <div class="sp-seg" role="tablist" aria-label="Group by">
        <button v-for="u in UNITS" :key="u.key" type="button" role="tab" :aria-selected="unit === u.key" :class="{ on: unit === u.key }" @click="setUnit(u.key)">{{ u.label }}</button>
      </div>
      <RangePicker @change="notice = ''; load()" />
    </div>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>
    <div v-else-if="notice" class="sp-banner info">
      <span v-html="ICONS.sparkle"></span>{{ notice }}
    </div>

    <!-- Totals -->
    <section class="rp-totals" aria-label="Totals">
      <template v-if="!data">
        <div v-for="n in 6" :key="n" class="rp-total"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w70"></div></div>
      </template>
      <template v-else>
        <div v-for="t in TOTALS" :key="t.key" class="rp-total" :class="{ hero: t.key === 'net_profit' }">
          <span>{{ t.label }}</span>
          <strong :class="{ neg: (t.cost && totals[t.key] > 0) || totals[t.key] < 0 }">{{ (t.cost && totals[t.key]) || totals[t.key] < 0 ? "−" : "" }}{{ t.money === false ? totals[t.key] : money(Math.abs(totals[t.key])) }}</strong>
        </div>
      </template>
    </section>

    <!-- Chart -->
    <section class="sp-card rp-chart" aria-label="Chart">
      <div class="sp-card-head">
        <div><h2>{{ UNITS.find((u) => u.key === unit).label }} net sales and costs</h2><p v-if="data">{{ data.range.label }}</p></div>
      </div>
      <div v-if="!data" class="bc-skeleton rp-sk"></div>
      <p v-else-if="!data.rows.length" class="sp-hint">No periods in this range.</p>
      <BarChart v-else :rows="chartRows" :series="SERIES" :extra="{ key: 'net_profit', label: 'Net profit' }" :height="220" />
    </section>

    <!-- Table -->
    <section class="sp-card rp-table-card" aria-label="Breakdown">
      <div class="sp-card-head">
        <div><h2>Breakdown</h2><p>{{ shownRows.length }} of {{ data?.rows.length ?? 0 }} {{ unitWord }}</p></div>
        <label class="hide-empty">
          <span class="sp-switch"><input v-model="hideEmpty" type="checkbox" /><span></span></span>
          Hide quiet {{ unitWord }}
        </label>
      </div>
      <div v-if="!data" class="bc-skeleton rp-sk"></div>
      <div v-else class="rp-scroll">
        <table class="rp">
          <thead>
            <tr>
              <th>Period</th>
              <th class="num">Orders</th>
              <th class="num">Sales</th>
              <th class="num">Refunds</th>
              <th class="num">Net sales</th>
              <th class="num">Cost of goods</th>
              <th class="num">Expenses</th>
              <th class="num">Net profit</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in shownRows" :key="r.key">
              <td class="period">{{ r.label }}</td>
              <td class="num">{{ r.orders }}</td>
              <td class="num">{{ money(r.sales) }}</td>
              <td class="num" :class="{ muted: !r.refunds }">{{ r.refunds ? `−${money(r.refunds)}` : "—" }}</td>
              <td class="num">{{ money(r.net_sales) }}</td>
              <td class="num" :class="{ muted: !r.cogs }">{{ r.cogs ? `−${money(r.cogs)}` : "—" }}</td>
              <td class="num" :class="{ muted: !r.expenses }">{{ r.expenses ? `−${money(r.expenses)}` : "—" }}</td>
              <td class="num strong" :class="{ neg: r.net_profit < 0, pos: r.net_profit > 0 }">{{ money(r.net_profit) }}</td>
            </tr>
            <tr v-if="!shownRows.length">
              <td colspan="8" class="empty">Nothing happened in this range.</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td>Total</td>
              <td class="num">{{ totals.orders }}</td>
              <td class="num">{{ money(totals.sales) }}</td>
              <td class="num">−{{ money(totals.refunds) }}</td>
              <td class="num">{{ money(totals.net_sales) }}</td>
              <td class="num">−{{ money(totals.cogs) }}</td>
              <td class="num">−{{ money(totals.operating_expenses) }}</td>
              <td class="num" :class="{ neg: totals.net_profit < 0 }">{{ money(totals.net_profit) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import RangePicker from "../../../components/vendor/finance/RangePicker.vue";
import BarChart from "../../../components/vendor/finance/BarChart.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { financeRange, money } from "../../../components/vendor/finance/financeUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/finance/finance.css";

const UNITS = [
  { key: "day", label: "Daily" },
  { key: "week", label: "Weekly" },
  { key: "month", label: "Monthly" },
  { key: "year", label: "Yearly" },
];
const TOTALS = [
  { key: "orders", label: "Paid orders", money: false },
  { key: "sales", label: "Sales" },
  { key: "refunds", label: "Refunds", cost: true },
  { key: "cogs", label: "Cost of goods", cost: true },
  { key: "operating_expenses", label: "Expenses", cost: true },
  { key: "net_profit", label: "Net profit" },
];
const SERIES = [
  { key: "net_sales", label: "Net sales", color: "#48bb78" },
  { key: "spent", label: "Cost of goods + expenses", color: "#f6ad55" },
];

const unit = ref(readUnit());
const data = ref(null);
const error = ref("");
const notice = ref("");
const exporting = ref(null);
const hideEmpty = ref(true);

function readUnit() {
  try {
    const u = sessionStorage.getItem("bc.vendor.reports.unit");
    return UNITS.some((x) => x.key === u) ? u : "day";
  } catch {
    return "day";
  }
}

const totals = computed(() => data.value?.totals ?? {});
const unitWord = computed(() => ({ day: "days", week: "weeks", month: "months", year: "years" })[unit.value]);
const shownRows = computed(() => (data.value?.rows ?? []).filter((r) => !hideEmpty.value || r.orders || r.sales || r.refunds || r.expenses));
const chartRows = computed(() =>
  (data.value?.rows ?? []).map((r) => ({
    key: r.key,
    label: r.label,
    short: unit.value === "day" ? r.label.replace(/^\w+, /, "") : unit.value === "month" ? r.label.slice(0, 3) : unit.value === "week" ? r.label.split(" – ")[0] : r.label,
    net_sales: Math.max(0, r.net_sales),
    spent: r.cogs + r.expenses,
    net_profit: r.net_profit,
  })),
);

async function load() {
  error.value = "";
  try {
    const res = await api.get("vendor/books/reports", { params: { unit: unit.value, from: financeRange.from, to: financeRange.to } });
    data.value = res.data.data;
  } catch (e) {
    // Range too long for this grouping: step up to the next one by itself
    const next = { day: "week", week: "month", month: "year" }[unit.value];
    if (e?.response?.status === 422 && next) {
      notice.value = `${UNITS.find((u) => u.key === unit.value).label} view covers a shorter range, so this shows ${UNITS.find((u) => u.key === next).label.toLowerCase()} totals.`;
      unit.value = next;
      return load();
    }
    error.value = apiMessage(e, "Couldn't load the report.");
    data.value = { rows: [], totals: {}, range: { label: "" } };
  }
}
function setUnit(u) {
  notice.value = "";
  unit.value = u;
  try {
    sessionStorage.setItem("bc.vendor.reports.unit", u);
  } catch {
    /* private mode */
  }
  data.value = null;
  load();
}

async function exportAs(format) {
  exporting.value = format;
  try {
    const res = await api.get("vendor/books/reports/export", {
      params: { format, unit: unit.value, from: financeRange.from, to: financeRange.to },
      responseType: "blob",
    });
    const url = URL.createObjectURL(res.data);
    const a = document.createElement("a");
    a.href = url;
    a.download = `bloomcraft-report-${financeRange.from}-to-${financeRange.to}.${format}`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (e) {
    let message = "Couldn't export the report.";
    try {
      message = JSON.parse(await e?.response?.data?.text?.())?.message || message;
    } catch {
      /* not JSON */
    }
    toast.error(message);
  } finally {
    exporting.value = null;
  }
}

onMounted(load);
</script>

<style scoped>
.rp-totals {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 1px;
  margin-bottom: 16px;
  border: 1px solid #edf1ef;
  border-radius: 16px;
  overflow: hidden;
  background: #edf1ef;
}
.rp-total {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  padding: 14px 16px;
  background: #fff;
}
.rp-total span {
  font-size: 12px;
  color: #718096;
}
.rp-total strong {
  font-size: 17px;
  font-weight: 600;
  color: #1a202c;
  overflow-wrap: anywhere;
}
.rp-total strong.neg {
  color: #c53030;
}
.rp-total.hero {
  background: var(--bc-accent-soft);
}
.rp-chart {
  margin-bottom: 16px;
  min-width: 0;
}
.rp-sk {
  height: 220px;
}
.rp-table-card {
  min-width: 0;
}
.hide-empty {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12.5px;
  color: #4a5568;
  white-space: nowrap;
  cursor: pointer;
}
.rp-scroll {
  max-height: 520px;
  overflow: auto;
  border: 1px solid #f2f5f3;
  border-radius: 12px;
}
.rp {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
  font-size: 13px;
}
.rp th {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: 10px 12px;
  background: var(--bc-accent-soft);
  font-size: 11.5px;
  font-weight: 600;
  color: #718096;
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.rp td {
  padding: 9px 12px;
  border-top: 1px solid #f2f5f3;
  color: #2d3748;
  white-space: nowrap;
}
.rp .num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.rp .period {
  font-weight: 500;
  color: #1a202c;
}
.rp .muted {
  color: #cbd5e0;
}
.rp .strong {
  font-weight: 600;
}
.rp .pos {
  color: #22753f;
}
.rp .neg {
  color: #c53030;
}
.rp tbody tr:hover td {
  background: #fafcfb;
}
.rp tfoot td {
  position: sticky;
  bottom: 0;
  padding: 11px 12px;
  border-top: 2px solid var(--bc-accent-muted);
  background: var(--bc-accent-soft);
  font-weight: 700;
  color: #1a202c;
}
.rp .empty {
  padding: 28px;
  text-align: center;
  color: #a0aec0;
  white-space: normal;
}
@media (max-width: 1100px) {
  .rp-totals {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 560px) {
  .rp-totals {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
