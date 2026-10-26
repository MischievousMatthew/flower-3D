<template>
  <HrPage title="Completed" subtitle="Deliveries the customer has received, newest first, with returns and refunds." :tabs="SCAN_TABS">
    <section class="fx-kpis" aria-label="Summary">
      <template v-if="!counts">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div></div>
      </template>
      <template v-else>
        <button v-for="k in kpis" :key="k.label" type="button" class="sp-card fx-kpi cp-kpi" :class="{ on: k.status && status === k.status }" :disabled="!k.status" :aria-pressed="k.status ? status === k.status : undefined" @click="k.status && setStatus(k.status)">
          <span class="fx-kpi-label"><span class="cp-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong>{{ k.value }}</strong>
          <span class="fx-kpi-sub">{{ k.sub }}</span>
        </button>
      </template>
    </section>

    <div class="sp-card cp-bar">
      <label class="sp-search cp-search">
        <span v-html="ICONS.search"></span>
        <input v-model="search" class="sp-input" type="search" placeholder="Order number or delivery code" aria-label="Search deliveries" @input="debouncedLoad" />
      </label>
    </div>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="load">Try again</button>
    </div>

    <section class="sp-card cp-card" aria-label="Deliveries">
      <ul v-if="rows === null" class="cp-rows"><li v-for="n in 6" :key="n" class="cp-row"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></li></ul>
      <div v-else-if="!rows.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.check"></span>
        <h3>{{ search ? "Nothing matches" : `No ${STATUS[status].plural} yet` }}</h3>
        <p>{{ search ? "Try another order number or code." : "Deliveries show up here once the customer confirms they got them." }}</p>
      </div>
      <template v-else>
        <ul class="cp-rows">
          <li v-for="d in rows" :key="d.id" class="cp-row">
            <span class="cp-ic big" :class="`tone-${STATUS[d.status]?.tone ?? 'green'}`" v-html="ICONS[STATUS[d.status]?.icon ?? 'check']"></span>
            <span class="grow">
              <b>{{ d.order?.order_number ?? d.delivery_id }}</b>
              <small>{{ d.order?.delivery_contact_name ?? "Customer" }}<template v-if="d.order?.delivery_address"> · {{ d.order.delivery_address }}</template></small>
            </span>
            <span class="cp-meta">
              <small>{{ d.order?.delivered_at ? `Delivered ${when(d.order.delivered_at)}` : `Updated ${when(d.updated_at)}` }}</small>
              <small v-if="d.last_scanned_by">Last scan by {{ d.last_scanned_by }}</small>
            </span>
            <span class="cp-amt">{{ money(d.order?.total_amount) }}</span>
            <span class="sp-chip" :class="STATUS[d.status]?.tone ?? 'slate'">{{ d.status_label ?? STATUS[d.status]?.label ?? d.status }}</span>
          </li>
        </ul>
        <ShowMore tone="vendor" :has-more="pager.hasMore.value" :loading="pager.loadingMore.value" :shown="rows.length" :total="pager.total.value" @more="pager.showMore()" />
      </template>
    </section>
  </HrPage>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import HrPage from "../../../../../components/hr/HrPage.vue";
import ShowMore from "../../../../../components/common/ShowMore.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../../../components/vendor/finance/financeUi";
import { useShowMorePaged } from "../../../../../composables/useShowMore";
import { deliveryService } from "../../../../../services/deliveryService";
import "../../../../../components/vendor/finance/finance.css";

const SCAN_TABS = [
  { name: "ScanToProcess", label: "To process", icon: "box" },
  { name: "ScanToShip", label: "To ship", icon: "truck" },
  { name: "ScanToReceive", label: "To receive", icon: "pin" },
  { name: "ScanCompleted", label: "Completed", icon: "check" },
];
const STATUS = {
  completed: { label: "Completed", plural: "completed deliveries", tone: "green", icon: "check" },
  returned: { label: "Returned", plural: "returns", tone: "amber", icon: "restore" },
  refunded: { label: "Refunded", plural: "refunds", tone: "red", icon: "wallet" },
};

const status = ref("completed");
const search = ref("");
const rows = ref(null);
const counts = ref(null);
const loadError = ref("");

const kpis = computed(() => {
  const c = counts.value;
  const delivered = c.completed + c.returned + c.refunded;
  return [
    { status: "completed", label: "Completed", icon: "check", tone: "green", value: c.completed, sub: "received by the customer" },
    { status: "returned", label: "Returned", icon: "restore", tone: "amber", value: c.returned, sub: "sent back" },
    { status: "refunded", label: "Refunded", icon: "wallet", tone: "red", value: c.refunded, sub: "money given back" },
    { status: null, label: "Kept", icon: "sparkle", tone: "teal", value: delivered ? `${Math.round((c.completed / delivered) * 100)}%` : "—", sub: "of delivered orders" },
  ];
});
const when = (d) => (d ? new Date(d).toLocaleString("en-PH", { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" }) : "—");

async function fetchPage(page) {
  const res = await deliveryService.scOrders({ status: status.value, search: search.value.trim() || undefined, per_page: 20, page });
  const body = res.data ?? {};
  counts.value = body.status_counts ?? counts.value;
  return { items: body.data ?? [], lastPage: body.meta?.last_page ?? body.pagination?.last_page ?? 1, total: body.meta?.total ?? body.pagination?.total };
}
const pager = useShowMorePaged(fetchPage);
watch(() => pager.visible.value, (v) => {
  if (rows.value !== null) rows.value = v;
});
async function load() {
  loadError.value = "";
  try {
    await pager.load();
    if (pager.failure.value) throw pager.failure.value;
    rows.value = pager.visible.value;
  } catch (e) {
    rows.value = rows.value ?? [];
    counts.value = counts.value ?? { completed: 0, returned: 0, refunded: 0 };
    loadError.value = apiMessage(e, "Couldn't load the deliveries.");
  }
}
function setStatus(s) {
  status.value = s;
  load();
}
let timer = null;
function debouncedLoad() {
  clearTimeout(timer);
  timer = setTimeout(load, 350);
}
onMounted(load);
onBeforeUnmount(() => clearTimeout(timer));
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.cp-kpi {
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.cp-kpi:disabled {
  cursor: default;
}
.cp-kpi.on {
  border-color: var(--bc-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 18%, transparent);
}
.cp-kpi:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.cp-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.cp-ic.big {
  flex: none;
  width: 38px;
  height: 38px;
  border-radius: 11px;
}
.cp-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.cp-bar {
  padding: 12px;
  margin-bottom: 16px;
}
.cp-search {
  max-width: 380px;
}
.cp-card {
  padding: 8px 16px;
}
.cp-rows {
  list-style: none;
  margin: 0;
  padding: 0;
}
.cp-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 0;
}
.cp-row + .cp-row {
  border-top: 1px solid var(--v-border);
}
.cp-row b {
  font-size: 13.5px;
  color: var(--v-text);
}
.cp-row small {
  overflow: hidden;
  font-size: 12px;
  color: var(--v-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cp-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  min-width: 0;
}
.cp-amt {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
@media (max-width: 760px) {
  .cp-row {
    flex-wrap: wrap;
  }
  .cp-meta {
    align-items: flex-start;
    order: 5;
    width: 100%;
    padding-left: 52px;
  }
}
</style>
