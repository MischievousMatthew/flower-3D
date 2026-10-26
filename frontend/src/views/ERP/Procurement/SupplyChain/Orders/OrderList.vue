<template>
  <HrPage title="Purchase orders" subtitle="Write orders to suppliers, get them approved, send them, and track them until everything has arrived.">
    <template #actions>
      <button v-if="can('sc_orders', 'create')" type="button" class="sp-btn sp-btn-ghost" @click="openFunding"><span v-html="ICONS.wallet"></span>From approved funding</button>
      <router-link v-if="can('sc_orders', 'create')" :to="{ name: 'OrderCreate' }" class="sp-btn sp-btn-primary"><span v-html="ICONS.plus"></span>New purchase order</router-link>
    </template>

    <!-- Stages: also the filter -->
    <section class="ol-stages" aria-label="Stages">
      <template v-if="!counts">
        <div v-for="n in 6" :key="n" class="sp-card ol-stage"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton fx-sk-big"></div></div>
      </template>
      <template v-else>
        <button type="button" class="sp-card ol-stage" :class="{ on: !stage && !overdue }" :aria-pressed="!stage && !overdue" @click="setStage('')">
          <span class="ol-stage-label">All orders</span>
          <strong>{{ counts.total }}</strong>
        </button>
        <button v-for="s in PO_STAGES" :key="s.key" type="button" class="sp-card ol-stage" :class="[{ on: stage === s.key }, `t-${s.tone}`]" :aria-pressed="stage === s.key" @click="setStage(s.key)">
          <span class="ol-stage-label"><span class="ol-dot"></span>{{ s.label }}</span>
          <strong>{{ counts.stages?.[s.key] ?? 0 }}</strong>
        </button>
      </template>
    </section>

    <div class="sp-card ol-bar">
      <label class="sp-search ol-search">
        <span v-html="ICONS.search"></span>
        <input v-model="search" class="sp-input" type="search" placeholder="Order number, supplier or item" aria-label="Search purchase orders" @input="debouncedLoad" />
      </label>
      <select v-model="supplierId" class="sp-select ol-select" aria-label="Supplier" @change="load">
        <option value="">Every supplier</option>
        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.company_name }}</option>
      </select>
      <label class="ol-date"><span>From</span><input v-model="from" type="date" class="sp-input" :max="to || undefined" @change="load" /></label>
      <label class="ol-date"><span>To</span><input v-model="to" type="date" class="sp-input" :min="from || undefined" @change="load" /></label>
      <button v-if="counts?.overdue" type="button" class="ol-late" :class="{ on: overdue }" :aria-pressed="overdue" @click="toggleOverdue">
        <span v-html="ICONS.alert"></span>{{ counts.overdue }} late
      </button>
      <button v-if="filtered" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="clearFilters">Clear</button>
    </div>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="load">Try again</button>
    </div>

    <section class="sp-card ol-card" aria-label="Purchase orders">
      <div v-if="orders === null" class="ol-rows">
        <div v-for="n in 6" :key="n" class="ol-row sk"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div>
      </div>
      <div v-else-if="!orders.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.receipt"></span>
        <h3>{{ filtered ? "No orders match" : "No purchase orders yet" }}</h3>
        <p>{{ filtered ? "Try another stage, supplier, date or search." : "Write your first order to a supplier. It can wait as a draft, go for approval, or be sent straight away." }}</p>
        <button v-if="filtered" type="button" class="sp-btn sp-btn-ghost" @click="clearFilters">Clear filters</button>
        <router-link v-else-if="can('sc_orders', 'create')" :to="{ name: 'OrderCreate' }" class="sp-btn sp-btn-primary"><span v-html="ICONS.plus"></span>New purchase order</router-link>
      </div>
      <template v-else>
        <div class="ol-head" aria-hidden="true">
          <span>Order</span><span>Supplier &amp; items</span><span>Dates</span><span class="num">Total</span><span>Stage</span>
        </div>
        <ul class="ol-rows" :class="{ dim: pager.loading.value }">
          <li v-for="o in orders" :key="o.id">
            <router-link :to="{ name: 'OrderDetail', params: { id: o.id } }" class="ol-row">
              <span class="ol-no">
                <b>{{ o.order_number || `PO-${o.id}` }}</b>
                <small>{{ o.created_by_name ? `by ${o.created_by_name}` : "" }}</small>
              </span>
              <span class="ol-sup">
                <b>{{ o.supplier?.company_name || "Supplier removed" }}</b>
                <small>{{ itemsText(o) }}</small>
              </span>
              <span class="ol-dates">
                <small>Ordered {{ poDate(o.order_date || o.created_at) }}</small>
                <small v-if="o.expected_date" :class="{ late: isLate(o) }">{{ dueText(o.expected_date, o.status) || `Expected ${poDate(o.expected_date)}` }}</small>
              </span>
              <span class="ol-total num"><b>{{ money(o.total_amount) }}</b></span>
              <span class="ol-stagecell">
                <span class="sp-chip" :class="stageOf(o.status).tone">{{ stageOf(o.status).label }}</span>
                <small v-if="ORDERED_STEP[o.status]">{{ ORDERED_STEP[o.status] }}</small>
                <span v-if="o.status === 'partially_received'" class="ol-prog" role="img" :aria-label="`${received(o)}% received`"><span :style="{ width: `${received(o)}%` }"></span></span>
              </span>
            </router-link>
          </li>
        </ul>
        <ShowMore tone="vendor" :has-more="pager.hasMore.value" :loading="pager.loadingMore.value" :shown="orders.length" :total="pager.total.value" @more="pager.showMore()" />
      </template>
    </section>

    <!-- From approved funding -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="funding" class="sp-backdrop" @click="funding = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="funding" class="sp-modal-wrap">
          <div class="sp-modal wide" role="dialog" aria-modal="true" aria-labelledby="ol-fund-title">
            <div class="sp-modal-head">
              <div><h3 id="ol-fund-title">Order from approved funding</h3><p>Finance already approved these, so the order goes straight to the supplier.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="funding = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div v-if="funding.loading" class="ol-fund-sk"><div v-for="n in 3" :key="n" class="bc-skeleton sp-sk-block"></div></div>
            <div v-else-if="!funding.requests.length" class="sp-empty">
              <span class="sp-empty-icon" v-html="ICONS.wallet"></span>
              <h3>Nothing waiting</h3>
              <p>Approved funding requests that don't have an order yet show up here.</p>
            </div>
            <template v-else>
              <span class="sp-label">1. Pick the funding request</span>
              <div class="ol-fund-list" role="radiogroup" aria-label="Funding requests">
                <label v-for="fr in funding.requests" :key="fr.id" class="ol-fund" :class="{ on: funding.selected?.id === fr.id }">
                  <input v-model="funding.selected" type="radio" name="ol-fund" :value="fr" />
                  <span class="ol-fund-top"><b>{{ fr.product_name }}</b><span class="sp-chip green">Approved</span></span>
                  <small>{{ fr.finance_request_id }} · {{ (fr.recommended_qty ?? fr.requested_qty)?.toLocaleString("en-PH") }} {{ fr.uom || "units" }} × {{ money(fr.estimated_unit_cost) }}</small>
                  <strong>{{ money((fr.recommended_qty ?? fr.requested_qty) * fr.estimated_unit_cost) }}</strong>
                </label>
              </div>
              <label v-if="funding.selected" class="sp-field">
                <span class="sp-label">2. Pick the supplier</span>
                <select v-model="funding.supplierId" class="sp-select">
                  <option value="">Choose an active supplier</option>
                  <option v-for="s in activeSuppliers" :key="s.id" :value="s.id">{{ s.company_name }}</option>
                </select>
              </label>
            </template>
            <p v-if="funding.error" class="sp-error">{{ funding.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="funding = null">Cancel</button>
              <button v-if="funding.requests.length" type="button" class="sp-btn sp-btn-primary" :disabled="!funding.selected || !funding.supplierId || funding.busy" @click="orderFromFunding">{{ funding.busy ? "Creating…" : "Create order" }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import HrPage from "../../../../../components/hr/HrPage.vue";
import ShowMore from "../../../../../components/common/ShowMore.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../../../components/vendor/finance/financeUi";
import { PO_STAGES, ORDERED_STEP, stageOf, poDate, dueText, daysUntil, isReceivable } from "../../../../../components/procurement/poUi";
import { useShowMorePaged } from "../../../../../composables/useShowMore";
import { useAssignment } from "../../../../../composables/useAssignment";
import { orderService } from "../../../../../services/orderService";
import { supplierService } from "../../../../../services/supplierService";
import { toast } from "@/utils/snackbar";
import "../../../../../components/vendor/finance/finance.css";

const router = useRouter();
const { can } = useAssignment();
const orders = ref(null);
const counts = ref(null);
const suppliers = ref([]);
const loadError = ref("");
const stage = ref("");
const overdue = ref(false);
const search = ref("");
const supplierId = ref("");
const from = ref("");
const to = ref("");
const funding = ref(null);

const filtered = computed(() => !!(stage.value || overdue.value || search.value.trim() || supplierId.value || from.value || to.value));
const activeSuppliers = computed(() => suppliers.value.filter((s) => s.status === "active"));

const itemsText = (o) => {
  const items = o.items ?? [];
  if (!items.length) return "No items yet";
  const first = items[0];
  const rest = items.length - 1;
  return `${first.product_name} × ${Number(first.quantity).toLocaleString("en-PH")}${rest ? ` + ${rest} more` : ""}`;
};
const received = (o) => {
  const items = o.items ?? [];
  const total = items.reduce((s, i) => s + Number(i.quantity), 0);
  return total ? Math.round((items.reduce((s, i) => s + Number(i.received_qty ?? 0), 0) / total) * 100) : 0;
};
const isLate = (o) => isReceivable(o.status) && daysUntil(o.expected_date) < 0;

async function fetchPage(page) {
  const res = await orderService.list({
    page,
    per_page: 20,
    stage: stage.value || undefined,
    overdue: overdue.value ? 1 : undefined,
    search: search.value.trim() || undefined,
    supplier_id: supplierId.value || undefined,
    from: from.value || undefined,
    to: to.value || undefined,
  });
  const body = res.data ?? {};
  counts.value = body.counts ?? counts.value;
  return { items: body.data ?? [], lastPage: body.last_page ?? 1, total: body.total };
}
const pager = useShowMorePaged(fetchPage);
watch(() => pager.visible.value, (rows) => {
  if (orders.value !== null) orders.value = rows;
});

async function load() {
  loadError.value = "";
  try {
    await pager.load();
    if (pager.failure.value) throw pager.failure.value;
    orders.value = pager.visible.value;
  } catch (e) {
    orders.value = orders.value ?? [];
    counts.value = counts.value ?? { total: 0, stages: {}, overdue: 0 };
    loadError.value = apiMessage(e, "Couldn't load the purchase orders.");
  }
}

let timer = null;
function debouncedLoad() {
  clearTimeout(timer);
  timer = setTimeout(load, 350);
}
function setStage(key) {
  stage.value = key;
  overdue.value = false;
  load();
}
function toggleOverdue() {
  overdue.value = !overdue.value;
  if (overdue.value) stage.value = "";
  load();
}
function clearFilters() {
  stage.value = "";
  overdue.value = false;
  search.value = "";
  supplierId.value = "";
  from.value = "";
  to.value = "";
  load();
}

// ─── From approved funding (the existing flow) ───
async function openFunding() {
  funding.value = { loading: true, requests: [], selected: null, supplierId: "", busy: false, error: "" };
  try {
    const res = await orderService.approvedFundingRequests();
    const raw = res.data?.data ?? res.data ?? [];
    funding.value.requests = Array.isArray(raw) ? raw : [];
  } catch (e) {
    funding.value.error = apiMessage(e, "Couldn't load the approved funding requests.");
  } finally {
    if (funding.value) funding.value.loading = false;
  }
}
async function orderFromFunding() {
  const f = funding.value;
  f.busy = true;
  f.error = "";
  try {
    const res = await orderService.createFromFunding(f.selected.id, f.supplierId);
    funding.value = null;
    toast.success(`${res.data?.order_number ?? "The order"} was created and is with the supplier.`);
    if (res.data?.id) router.push({ name: "OrderDetail", params: { id: res.data.id } });
    else load();
  } catch (e) {
    f.busy = false;
    f.error = apiMessage(e, "Couldn't create the order.");
  }
}

onMounted(async () => {
  load();
  try {
    const res = await supplierService.list({ per_page: 100 });
    suppliers.value = res.data?.data ?? [];
  } catch {
    suppliers.value = [];
  }
});
onBeforeUnmount(() => clearTimeout(timer));
</script>

<style scoped>
/* Stages */
.ol-stages {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 14px;
}
.ol-stage {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 14px 16px;
  border: 1px solid var(--v-border);
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.ol-stage:hover {
  border-color: var(--v-border-strong);
}
.ol-stage.on {
  border-color: var(--bc-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 18%, transparent);
}
.ol-stage:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.ol-stage-label {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-text-2);
}
.ol-stage strong {
  font-size: 22px;
  font-weight: 600;
  color: var(--v-text);
}
.ol-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--v-faint);
}
.t-amber .ol-dot { background: #f59e0b; }
.t-blue .ol-dot { background: #3b82f6; }
.t-violet .ol-dot { background: #8b5cf6; }
.t-green .ol-dot { background: #10b981; }

/* Filters */
.ol-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  padding: 12px;
  margin-bottom: 16px;
}
.ol-search {
  flex: 1;
  min-width: 220px;
}
.ol-select {
  width: auto;
  min-width: 170px;
}
.ol-date {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--v-muted);
}
.ol-date .sp-input {
  width: 150px;
}
.ol-late {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 40px;
  padding: 0 12px;
  border: 1px solid color-mix(in srgb, var(--v-red-text) 40%, transparent);
  border-radius: 10px;
  background: var(--v-red-soft);
  color: var(--v-red-text);
  font: inherit;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
}
.ol-late.on {
  background: var(--v-red-text);
  color: #fff;
}
.ol-late :deep(svg) {
  width: 15px;
  height: 15px;
}

/* Rows */
.ol-card {
  padding: 8px;
}
.ol-head,
.ol-row {
  display: grid;
  grid-template-columns: minmax(150px, 0.9fr) minmax(0, 1.6fr) minmax(150px, 0.9fr) minmax(110px, 0.6fr) minmax(150px, 0.8fr);
  align-items: center;
  gap: 14px;
  padding: 12px 14px;
}
.ol-head {
  padding-top: 10px;
  padding-bottom: 8px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-muted);
}
.num {
  text-align: right;
}
.ol-rows {
  list-style: none;
  margin: 0;
  padding: 0;
  transition: opacity 0.2s;
}
.ol-rows.dim {
  opacity: 0.55;
}
.ol-rows > li + li {
  border-top: 1px solid var(--v-border);
}
.ol-row {
  border-radius: 12px;
  color: inherit;
  text-decoration: none;
  transition: background 0.15s;
}
a.ol-row:hover {
  background: var(--v-subtle);
}
a.ol-row:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: -2px;
}
.ol-row.sk {
  grid-template-columns: 1fr 2fr 1fr;
}
.ol-row > span {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.ol-row b {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ol-row small {
  overflow: hidden;
  font-size: 12px;
  color: var(--v-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ol-row small.late {
  color: var(--v-red-text);
  font-weight: 600;
}
.ol-total {
  align-items: flex-end;
}
.ol-stagecell {
  align-items: flex-start;
}
.ol-prog {
  width: 100%;
  max-width: 140px;
  height: 5px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.ol-prog span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #8b5cf6;
}

/* Funding */
.ol-fund-sk {
  display: grid;
  gap: 10px;
}
.ol-fund-list {
  display: grid;
  gap: 8px;
  max-height: 300px;
  margin: 6px 0 16px;
  overflow-y: auto;
}
.ol-fund {
  position: relative;
  display: grid;
  gap: 3px;
  padding: 12px 14px;
  border: 1.5px solid var(--v-border);
  border-radius: 12px;
  cursor: pointer;
}
.ol-fund.on {
  border-color: var(--bc-accent);
  background: color-mix(in srgb, var(--bc-accent) 7%, transparent);
}
.ol-fund:focus-within {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.ol-fund input {
  position: absolute;
  opacity: 0;
}
.ol-fund-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.ol-fund b {
  font-size: 13.5px;
  color: var(--v-text);
}
.ol-fund small {
  font-size: 12px;
  color: var(--v-muted);
}
.ol-fund strong {
  font-size: 14px;
  color: var(--v-text);
}

@media (max-width: 1180px) {
  .ol-stages {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 900px) {
  .ol-head {
    display: none;
  }
  .ol-row {
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 6px 12px;
  }
  .ol-sup,
  .ol-dates {
    grid-column: 1;
  }
  .ol-total {
    grid-column: 2;
    grid-row: 1;
  }
  .ol-stagecell {
    grid-column: 2;
    grid-row: 2 / span 2;
    align-items: flex-end;
  }
}
@media (max-width: 640px) {
  .ol-stages {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .ol-date,
  .ol-select {
    flex: 1;
  }
  .ol-date .sp-input {
    width: 100%;
  }
}
</style>
