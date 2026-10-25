<template>
  <HrPage title="Financial reports" subtitle="Sales, expenses, profit and loss, payments and refunds, and tax, ready to read here or download as CSV or PDF.">
    <!-- Which report -->
    <nav class="rp-types" aria-label="Report">
      <button v-for="t in TYPES" :key="t.key" type="button" class="rp-type" :class="{ on: type === t.key }" :aria-pressed="type === t.key" @click="setType(t.key)">
        <span class="rp-type-ic" :class="`tone-${t.tone}`" v-html="ICONS[t.icon]"></span>
        <span class="rp-type-text"><b>{{ t.label }}</b><small>{{ t.hint }}</small></span>
      </button>
    </nav>

    <!-- Options and downloads -->
    <section class="sp-card rp-bar" aria-label="Report options">
      <div class="rp-options">
        <RangePicker @change="load" />
        <div v-if="current.units" class="sp-seg" role="radiogroup" aria-label="Rows">
          <button v-for="u in UNITS" :key="u.key" type="button" role="radio" :aria-checked="unit === u.key" :class="{ on: unit === u.key }" @click="setOption('unit', u.key)">{{ u.label }}</button>
        </div>
        <div v-if="type === 'profit_loss'" class="sp-seg" role="radiogroup" aria-label="Count flower costs">
          <button type="button" role="radio" :aria-checked="basis === 'sold'" :class="{ on: basis === 'sold' }" @click="setOption('basis', 'sold')">Flowers when sold</button>
          <button type="button" role="radio" :aria-checked="basis === 'bought'" :class="{ on: basis === 'bought' }" @click="setOption('basis', 'bought')">When bought</button>
        </div>
        <select v-if="type === 'tax'" :value="taxMode" class="sp-select rp-tax" aria-label="Tax type" @change="setOption('taxMode', $event.target.value)">
          <option value="percentage">Percentage tax · 3% (non-VAT)</option>
          <option value="vat">VAT · 12% (VAT-registered)</option>
          <option value="income8">8% income tax option</option>
        </select>
      </div>
      <div v-if="canExport" class="rp-downloads">
        <button type="button" class="sp-btn sp-btn-ghost" :disabled="!!downloading || !report" @click="download('csv')"><span v-html="ICONS.download"></span>{{ downloading === "csv" ? "Preparing…" : "CSV" }}</button>
        <button type="button" class="sp-btn sp-btn-primary" :disabled="!!downloading || !report" @click="download('pdf')"><span v-html="ICONS.download"></span>{{ downloading === "pdf" ? "Preparing…" : "PDF" }}</button>
      </div>
    </section>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- The report -->
    <article class="rp-doc" :class="{ dim: loading && report }" aria-live="polite">
      <template v-if="!report">
        <div class="bc-skeleton sp-sk-line w30"></div>
        <div class="rp-tiles"><div v-for="n in 4" :key="n" class="sp-card rp-tile"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton fx-sk-big"></div></div></div>
        <div class="sp-card"><div class="bc-skeleton rp-sk"></div></div>
      </template>
      <template v-else>
        <header class="rp-head">
          <div>
            <span class="rp-shop">{{ report.shop }}</span>
            <h2>{{ report.title }}</h2>
            <p>{{ report.range.label }}<template v-if="report.subtitle"> · {{ report.subtitle }}</template></p>
          </div>
        </header>
        <div v-for="n in report.notes" :key="n" class="sp-banner warn rp-note"><span v-html="ICONS.alert"></span>{{ n }}</div>

        <section class="rp-tiles" aria-label="Summary">
          <div v-for="s in report.summary" :key="s[0]" class="sp-card rp-tile">
            <span class="rp-tile-label">{{ s[0] }}</span>
            <strong :class="{ neg: s[2] === 'money' && s[1] < 0 }">{{ fmt(s[1], s[2]) }}</strong>
            <small v-if="s[3]">{{ s[3] }}</small>
          </div>
        </section>

        <section v-for="t in report.tables" :key="t.title" class="sp-card rp-table-card" :aria-label="t.title">
          <div class="sp-card-head"><div><h2>{{ t.title }}</h2><p>{{ countText(t.rows.length, "row") }}</p></div></div>
          <p v-if="!t.rows.length" class="sp-hint">Nothing in this period.</p>
          <div v-else class="rp-scroll">
            <table class="rp-table">
              <thead><tr><th v-for="c in t.columns" :key="c[0]" :class="align(c[2])">{{ c[1] }}</th></tr></thead>
              <tbody>
                <tr v-for="(row, i) in t.rows" :key="i" :class="row.kind ? `k-${row.kind}` : ''">
                  <td v-for="c in t.columns" :key="c[0]" :class="[align(c[2]), { neg: c[2] === 'money' && row[c[0]] < 0 }]">{{ fmt(row[c[0]], c[2]) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="t.totals">
                <tr><td v-for="c in t.columns" :key="c[0]" :class="align(c[2])">{{ t.totals[c[0]] === undefined || t.totals[c[0]] === null ? "" : fmt(t.totals[c[0]], c[2]) }}</td></tr>
              </tfoot>
            </table>
          </div>
        </section>
      </template>
    </article>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { toast } from "@/utils/snackbar";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import RangePicker from "../../../components/vendor/finance/RangePicker.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { financeRange, money } from "../../../components/vendor/finance/financeUi";
import { useAuth } from "../../../composables/useAuth";
import { useAssignment } from "../../../composables/useAssignment";
import "../../../components/vendor/finance/finance.css";

const TYPES = [
  { key: "sales", label: "Sales", hint: "Daily to yearly", icon: "receipt", tone: "green", units: true },
  { key: "expenses", label: "Expenses", hint: "By category", icon: "card", tone: "amber" },
  { key: "profit_loss", label: "Profit & loss", hint: "Statement & trend", icon: "chart", tone: "violet", units: true },
  { key: "payments_refunds", label: "Payments & refunds", hint: "Fees, COD, refunds", icon: "wallet", tone: "blue" },
  { key: "tax", label: "Tax summary", hint: "3%, VAT or 8%", icon: "percent", tone: "pink" },
];
const UNITS = [
  { key: "day", label: "Daily" },
  { key: "week", label: "Weekly" },
  { key: "month", label: "Monthly" },
  { key: "year", label: "Yearly" },
];
const STORE = "bc.finance.reports";

const { user } = useAuth();
const { can } = useAssignment();
const isEmployee = computed(() => user.value?.type === "employee");
// Finance staff use the ERP routes (they check the Financial reports permission); the shop owner uses the vendor ones
const base = computed(() => (isEmployee.value ? "finance/reports" : "vendor/books/financial-reports"));
const canExport = computed(() => !isEmployee.value || can("financial_reports", "export"));

const saved = (() => {
  try {
    return JSON.parse(sessionStorage.getItem(STORE) || "{}");
  } catch {
    return {};
  }
})();
const type = ref(TYPES.some((t) => t.key === saved.type) ? saved.type : "sales");
const unit = ref(saved.unit ?? "day");
const basis = ref(saved.basis ?? "sold");
const taxMode = ref(saved.taxMode ?? "percentage");

const report = ref(null);
const error = ref("");
const loading = ref(false);
const downloading = ref("");

const current = computed(() => TYPES.find((t) => t.key === type.value));
const countText = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;
const align = (t) => (["money", "int", "percent"].includes(t) ? "num" : "");
const fmt = (v, t) => {
  if (v === null || v === undefined || v === "") return "—";
  switch (t) {
    case "money":
      return money(v);
    case "int":
      return Number(v).toLocaleString("en-PH");
    case "percent":
      return `${Math.round(Number(v) * 10) / 10}%`;
    case "date":
      return new Date(`${String(v).slice(0, 10)}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
    default:
      return String(v);
  }
};

const params = () => ({ from: financeRange.from, to: financeRange.to, unit: unit.value, basis: basis.value, tax_mode: taxMode.value });

function remember() {
  try {
    sessionStorage.setItem(STORE, JSON.stringify({ type: type.value, unit: unit.value, basis: basis.value, taxMode: taxMode.value }));
  } catch {
    /* private mode */
  }
}

async function load() {
  error.value = "";
  loading.value = true;
  try {
    const res = await api.get(`${base.value}/${type.value}`, { params: params() });
    report.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't build this report.");
  } finally {
    loading.value = false;
  }
}

function setType(t) {
  if (type.value === t) return;
  type.value = t;
  report.value = null;
  remember();
  load();
}

function setOption(key, value) {
  ({ unit, basis, taxMode })[key].value = value;
  remember();
  load();
}

async function download(format) {
  downloading.value = format;
  try {
    const res = await api.get(`${base.value}/${type.value}/export`, { params: { ...params(), format }, responseType: "blob" });
    const name = /filename="?([^";]+)"?/.exec(res.headers["content-disposition"] ?? "")?.[1] ?? `bloomcraft-${type.value}.${format}`;
    const url = URL.createObjectURL(res.data);
    const a = Object.assign(document.createElement("a"), { href: url, download: name });
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (e) {
    // Errors come back as a blob; read the message out of it
    let message = "Couldn't download the report.";
    try {
      message = JSON.parse(await e.response.data.text()).message || message;
    } catch {
      /* not JSON */
    }
    toast.error(message);
  } finally {
    downloading.value = "";
  }
}

onMounted(load);
</script>

<style scoped>
/* Report types */
.rp-types {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 14px;
}
.rp-type {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border: 1px solid var(--v-border-strong);
  border-radius: 14px;
  background: var(--v-surface);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, transform 0.15s;
}
.rp-type:hover {
  border-color: var(--bc-accent-border);
  transform: translateY(-1px);
}
.rp-type.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  box-shadow: 0 4px 14px color-mix(in srgb, var(--bc-accent) 14%, transparent);
}
.rp-type-ic {
  flex: none;
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
}
.rp-type-ic :deep(svg) {
  width: 17px;
  height: 17px;
}
.rp-type-text {
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.rp-type-text b {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.rp-type-text small {
  font-size: 11.5px;
  color: var(--v-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Options bar */
.rp-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  margin-bottom: 18px;
}
.rp-options {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}
.rp-tax {
  width: auto;
}
.rp-downloads {
  display: flex;
  gap: 8px;
}
.rp-downloads .sp-btn :deep(svg) {
  width: 15px;
  height: 15px;
}

/* Document */
.rp-doc {
  display: grid;
  gap: 14px;
  transition: opacity 0.2s;
}
.rp-doc.dim {
  opacity: 0.55;
}
.rp-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
.rp-shop {
  font-size: 11.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--bc-accent-strong);
}
.rp-head h2 {
  margin: 2px 0 2px;
  font-size: 20px;
  font-weight: 600;
  color: var(--v-text);
}
.rp-head p {
  margin: 0;
  font-size: 13px;
  color: var(--v-muted);
}
.rp-note {
  margin: 0;
}
.rp-tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 12px;
}
.rp-tile {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 14px 16px;
}
.rp-tile-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--v-text-2);
}
.rp-tile strong {
  font-size: 19px;
  font-weight: 600;
  color: var(--v-text);
  line-height: 1.25;
}
.rp-tile small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.neg {
  color: var(--v-red-text) !important;
}
.rp-sk {
  height: 280px;
}
.rp-table-card {
  min-width: 0;
}
.rp-scroll {
  overflow-x: auto;
}
.rp-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.rp-table th {
  padding: 0 10px 10px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-faint);
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.rp-table td {
  padding: 9px 10px;
  border-top: 1px solid var(--v-border);
  color: var(--v-text-2);
}
.rp-table .num {
  text-align: right;
  white-space: nowrap;
}
.rp-table tr.k-sub td {
  font-weight: 600;
  color: var(--v-text);
  background: var(--v-subtle);
}
.rp-table tr.k-total td {
  font-size: 14.5px;
  font-weight: 700;
  color: var(--v-text);
  background: var(--bc-accent-soft);
  border-top: 2px solid var(--bc-accent-muted);
}
.rp-table tfoot td {
  font-weight: 700;
  color: var(--v-text);
  background: var(--v-subtle);
  border-top: 2px solid var(--v-border-strong);
}

@media (max-width: 1200px) {
  .rp-types {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 720px) {
  .rp-types {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .rp-downloads {
    width: 100%;
  }
  .rp-tiles {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .rp-tile strong {
    font-size: 16px;
  }
  /* The table scrolls sideways instead of squeezing its text */
  .rp-table td {
    white-space: nowrap;
  }
  .rp-downloads .sp-btn {
    flex: 1;
    justify-content: center;
  }
}
</style>
