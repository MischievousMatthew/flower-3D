<template>
  <ShopLayout section="orders" title="Orders" subtitle="View and process customer orders, from new to delivered.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="load"><span v-html="ICONS.restore"></span>Refresh</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Pipeline -->
    <section class="pipe" aria-label="Orders by stage">
      <template v-if="!counts">
        <div v-for="n in 4" :key="n" class="pipe-step"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton pipe-sk"></div></div>
      </template>
      <button
        v-for="(s, i) in PIPELINE"
        v-else
        :key="s.key"
        type="button"
        class="pipe-step"
        :class="[`tone-${s.tone}`, { on: tab === s.key }]"
        @click="setTab(s.key)"
      >
        <span class="pipe-label"><span class="pipe-dot"></span>{{ s.label }}</span>
        <strong>{{ counts[s.key] ?? 0 }}</strong>
        <span class="pipe-hint">{{ s.hint }}</span>
        <span v-if="i < PIPELINE.length - 1" class="pipe-arrow" aria-hidden="true" v-html="ICONS.chevronRight"></span>
      </button>
    </section>

    <div v-if="counts?.needs_action" class="sp-banner warn">
      <span v-html="ICONS.alert"></span>
      {{ counts.needs_action }} {{ counts.needs_action === 1 ? "order needs" : "orders need" }} something from you.
      <button type="button" @click="setTab('pending')">Start with new orders</button>
    </div>

    <!-- Toolbar -->
    <div class="sp-toolbar">
      <div class="tabs-scroll">
        <div class="sp-seg" role="tablist" aria-label="Filter orders">
          <button v-for="t in TABS" :key="t.key" type="button" role="tab" :aria-selected="tab === t.key" :class="{ on: tab === t.key }" @click="setTab(t.key)">
            {{ t.label }}<span v-if="counts" class="count">{{ counts[t.key] ?? 0 }}</span>
          </button>
        </div>
      </div>
    </div>
    <div class="sp-toolbar">
      <label class="sp-search">
        <span v-html="ICONS.search"></span>
        <input v-model="search" class="sp-input" type="search" placeholder="Order number, customer, phone or flower" aria-label="Search orders" />
      </label>
      <label class="date-field">
        <span>Delivery from</span>
        <input v-model="from" type="date" class="sp-input" @change="reload" />
      </label>
      <label class="date-field">
        <span>to</span>
        <input v-model="to" type="date" class="sp-input" :min="from || undefined" @change="reload" />
      </label>
      <select v-model="sort" class="sp-select narrow" aria-label="Sort" @change="reload">
        <option value="newest">Newest first</option>
        <option value="delivery_soon">Delivery date: soonest</option>
        <option value="oldest">Oldest first</option>
        <option value="total_high">Highest total</option>
      </select>
      <button v-if="from || to || search" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="clearFilters">Clear</button>
    </div>

    <!-- List -->
    <section class="sp-card list-card" aria-label="Orders">
      <ul v-if="loading && !orders.length" class="olist">
        <li v-for="n in 5" :key="n" class="orow sk">
          <div class="bc-skeleton sp-thumb"></div>
          <div class="grow"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-line w70"></div></div>
          <div class="bc-skeleton sk-btn"></div>
        </li>
      </ul>

      <div v-else-if="!orders.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.receipt"></span>
        <h3>{{ filtered ? "No orders match" : tab === "all" ? "No orders yet" : `Nothing ${TAB_EMPTY[tab] ?? "here"}` }}</h3>
        <p>{{ filtered ? "Try another search or date range." : tab === "all" ? "New orders appear here as soon as customers check out." : "You're all caught up." }}</p>
        <button v-if="filtered" type="button" class="sp-btn sp-btn-ghost" @click="clearFilters">Clear filters</button>
      </div>

      <ul v-else class="olist" :class="{ dim: loading }">
        <li v-for="o in orders" :key="o.id" class="orow" :class="{ late: isLate(o) }">
          <button type="button" class="orow-main" @click="open(o)">
            <span class="thumb-wrap">
              <img v-if="o.first_item?.image" :src="o.first_item.image" :alt="o.first_item.name" class="sp-thumb" loading="lazy" />
              <span v-else class="sp-thumb blank" v-html="ICONS.flower"></span>
              <span v-if="o.items_count > 1" class="thumb-count">×{{ o.items_count }}</span>
            </span>
            <span class="orow-text">
              <span class="orow-top">
                <strong>{{ o.customer }}</strong>
                <span class="on-num">{{ o.order_number }}</span>
              </span>
              <span class="orow-items">{{ o.item_names || "—" }}<template v-if="o.has_custom_bouquet"> · <em>custom 3D bouquet</em></template></span>
              <span class="orow-meta">
                <span class="meta-date" :class="{ late: isLate(o), soon: isSoon(o) }">
                  <span v-html="ICONS.truck"></span>{{ dayLabel(o.delivery_date) }}
                </span>
                <span v-if="isLate(o)" class="sp-chip red">Late</span>
                <span v-else-if="shouldPrepare(o)" class="sp-chip amber">Prepare now</span>
                <span v-if="o.return_case" class="sp-chip pink">{{ o.return_case.type === "return" ? "Return" : "Refund" }}: {{ CASE_STAGE[o.return_case.stage] ?? o.return_case.status }}</span>
              </span>
            </span>
          </button>

          <div class="orow-side">
            <div class="orow-money">
              <strong>{{ money(o.total) }}</strong>
              <span>{{ PAYMENT_LABEL[o.payment_method] ?? o.payment_method }}<template v-if="o.payment_status === 'paid'"> · paid</template></span>
            </div>
            <span class="sp-chip" :class="STATUS_TONE[o.status]">{{ o.status_label }}</span>
            <div class="orow-actions">
              <button
                v-if="o.next.length"
                type="button"
                class="sp-btn sp-btn-primary sp-btn-sm"
                @click="act(o, o.next[0])"
              >{{ o.next[0].label }}</button>
              <button v-else-if="o.return_case?.next_actor === 'vendor'" type="button" class="sp-btn sp-btn-dark sp-btn-sm" @click="openReturns(o)">Reply to request</button>
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="open(o)">Details</button>
            </div>
          </div>
        </li>
      </ul>

      <footer v-if="meta.last_page > 1" class="pager">
        <span>{{ (meta.page - 1) * meta.per_page + 1 }}–{{ Math.min(meta.page * meta.per_page, meta.total) }} of {{ meta.total }}</span>
        <div>
          <button type="button" class="sp-icon-btn" :disabled="meta.page <= 1 || loading" aria-label="Previous page" @click="go(meta.page - 1)"><span v-html="ICONS.chevronLeft"></span></button>
          <button type="button" class="sp-icon-btn" :disabled="meta.page >= meta.last_page || loading" aria-label="Next page" @click="go(meta.page + 1)"><span v-html="ICONS.chevronRight"></span></button>
        </div>
      </footer>
    </section>

    <OrderActionModal :order="acting?.order" :action="acting?.action" @close="acting = null" @done="load" />
  </ShopLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import OrderActionModal from "../../../components/vendor/orders/OrderActionModal.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { CASE_STAGE, PAYMENT_LABEL, STATUS_TONE, dayLabel, daysFromToday, isLate, money, shouldPrepare } from "../../../components/vendor/orders/orderUi";
import "../../../components/vendor/shop/shop.css";

const router = useRouter();

const PIPELINE = [
  { key: "pending", label: "Pending", hint: "Confirm them", tone: "amber" },
  { key: "to_processed", label: "Preparing", hint: "Being arranged", tone: "blue" },
  { key: "to_ship", label: "Ready", hint: "Waiting for rider", tone: "violet" },
  { key: "to_received", label: "Out for delivery", hint: "On the way", tone: "teal" },
];
const TABS = [
  { key: "all", label: "All" },
  { key: "pending", label: "Pending" },
  { key: "to_processed", label: "Preparing" },
  { key: "to_ship", label: "Ready" },
  { key: "to_received", label: "Out for delivery" },
  { key: "completed", label: "Completed" },
  { key: "cancelled", label: "Cancelled" },
  { key: "returns", label: "Returns & refunds" },
];
const TAB_EMPTY = { pending: "pending", to_processed: "being prepared", to_ship: "ready", to_received: "out for delivery", completed: "completed yet", cancelled: "cancelled", returns: "with a return or refund" };

const tab = ref(readTab());
const search = ref("");
const from = ref("");
const to = ref("");
const sort = ref("newest");
const page = ref(1);
const orders = ref([]);
const counts = ref(null);
const meta = ref({ page: 1, per_page: 12, total: 0, last_page: 1 });
const loading = ref(false);
const error = ref("");
const acting = ref(null);
let seq = 0;
let searchTimer = null;

const filtered = computed(() => !!(search.value || from.value || to.value));

function readTab() {
  try {
    const saved = sessionStorage.getItem("bc.vendor.orders.tab");
    return TABS.some((t) => t.key === saved) ? saved : "all";
  } catch {
    return "all";
  }
}

async function load() {
  const mine = ++seq;
  loading.value = true;
  error.value = "";
  try {
    const { data } = await api.get("vendor/fulfillment/orders", {
      params: {
        tab: tab.value,
        search: search.value.trim() || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
        sort: sort.value,
        page: page.value,
      },
    });
    if (mine !== seq) return;
    orders.value = data.data;
    counts.value = data.counts;
    meta.value = data.meta;
  } catch (e) {
    if (mine !== seq) return;
    error.value = apiMessage(e, "Couldn't load your orders.");
  } finally {
    if (mine === seq) loading.value = false;
  }
}
function reload() {
  page.value = 1;
  load();
}
function setTab(key) {
  tab.value = key;
  try {
    sessionStorage.setItem("bc.vendor.orders.tab", key);
  } catch {
    /* private mode */
  }
  reload();
}
function go(p) {
  page.value = p;
  load();
  window.scrollTo({ top: 0, behavior: "smooth" });
}
function clearFilters() {
  search.value = "";
  from.value = "";
  to.value = "";
  reload();
}
watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(reload, 300);
});

// Due today or tomorrow and still to be delivered
const isSoon = (o) => {
  const n = daysFromToday(o.delivery_date);
  return n !== null && n >= 0 && n <= 1 && !["completed", "cancelled", "returned", "refunded"].includes(o.status);
};
const open = (o) => router.push({ name: "VendorOrderDetails", query: { order: o.id } });
const openReturns = (o) => router.push({ name: "VendorReturns", query: { order: o.id } });
const act = (order, action) => (acting.value = { order, action });

onMounted(load);
onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
}
.narrow {
  width: auto;
  min-width: 170px;
}

/* Pipeline */
.pipe {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.pipe-step {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  padding: 14px 16px;
  border: 1px solid #edf1ef;
  border-radius: 16px;
  background: #fff;
  font-family: "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.pipe-step:hover,
.pipe-step.on {
  border-color: var(--tone);
  box-shadow: 0 4px 14px rgba(16, 24, 40, 0.06);
}
.pipe-step.on {
  background: var(--tone-soft);
}
.tone-amber { --tone: #f6ad55; --tone-soft: #fffaf0; --tone-ink: #b45309; }
.tone-blue { --tone: #63b3ed; --tone-soft: #f0f7ff; --tone-ink: #2b6cb0; }
.tone-violet { --tone: #b794f4; --tone-soft: #f7f3ff; --tone-ink: #6b46c1; }
.tone-teal { --tone: #4fd1c5; --tone-soft: #effcfa; --tone-ink: #2c7a7b; }
.pipe-label {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--tone-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pipe-dot {
  flex: none;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--tone);
}
.pipe-step strong {
  font-size: 24px;
  font-weight: 600;
  color: #1a202c;
  line-height: 1.2;
}
.pipe-hint {
  font-size: 11.5px;
  color: #a0aec0;
}
.pipe-arrow {
  position: absolute;
  right: -15px;
  top: 50%;
  z-index: 1;
  display: grid;
  place-items: center;
  width: 18px;
  height: 18px;
  transform: translateY(-50%);
  border-radius: 50%;
  background: #f6f8f7;
  color: #cbd5e0;
}
.pipe-arrow :deep(svg) {
  width: 14px;
  height: 14px;
}
.pipe-sk {
  height: 26px;
  width: 40%;
  margin-top: 6px;
}

/* Toolbar */
.tabs-scroll {
  max-width: 100%;
  overflow-x: auto;
  scrollbar-width: none;
}
.tabs-scroll::-webkit-scrollbar {
  display: none;
}
.tabs-scroll .sp-seg {
  flex-wrap: nowrap;
}
.tabs-scroll .sp-seg button {
  white-space: nowrap;
}
.date-field {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: #718096;
  white-space: nowrap;
}
.date-field .sp-input {
  width: 150px;
  padding: 8px 10px;
}

/* List */
.list-card {
  padding: 6px;
}
.olist {
  list-style: none;
  margin: 0;
  padding: 0;
  transition: opacity 0.2s;
}
.olist.dim {
  opacity: 0.55;
}
.orow {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px;
  border-radius: 14px;
  border-bottom: 1px solid #f2f5f3;
  transition: background 0.15s;
}
.orow:last-child {
  border-bottom: 0;
}
.orow:hover {
  background: #f8fcf9;
}
.orow.late {
  box-shadow: inset 3px 0 0 #fc8181;
}
.orow.sk {
  pointer-events: none;
}
.sk-btn {
  width: 110px;
  height: 30px;
}
.orow-main {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 0;
  border: 0;
  background: none;
  font-family: "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
}
.thumb-wrap {
  position: relative;
  flex: none;
}
.thumb-wrap .sp-thumb {
  width: 52px;
  height: 52px;
  border-radius: 14px;
}
.thumb-count {
  position: absolute;
  right: -6px;
  bottom: -6px;
  padding: 1px 6px;
  border: 2px solid #fff;
  border-radius: 999px;
  background: #1a202c;
  color: #fff;
  font-size: 10.5px;
  font-weight: 600;
}
.orow-text {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.orow-top {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
}
.orow-top strong {
  font-size: 14px;
  font-weight: 600;
  color: #1a202c;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.on-num {
  flex: none;
  font-size: 11.5px;
  color: #a0aec0;
  font-variant-numeric: tabular-nums;
}
.orow-items {
  font-size: 12.5px;
  color: #4a5568;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.orow-items em {
  font-style: normal;
  color: #6b46c1;
}
.orow-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-top: 3px;
}
.meta-date {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 500;
  color: #4a5568;
}
.meta-date :deep(svg) {
  width: 14px;
  height: 14px;
  color: #a0aec0;
}
.meta-date.soon {
  color: #b45309;
}
.meta-date.late {
  color: #c53030;
}
.orow-side {
  flex: none;
  display: flex;
  align-items: center;
  gap: 14px;
}
.orow-money {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  width: 120px;
}
.orow-money strong {
  font-size: 14px;
  font-weight: 600;
  color: #1a202c;
}
.orow-money span {
  font-size: 11.5px;
  color: #a0aec0;
  white-space: nowrap;
}
.orow-side > .sp-chip {
  width: 124px;
  justify-content: center;
}
/* Fixed width so money and status line up on every row */
.orow-actions {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
  width: 290px;
}
.pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px 6px;
  font-size: 12.5px;
  color: #718096;
}
.pager div {
  display: flex;
  gap: 6px;
}
.pager .sp-icon-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@media (max-width: 1180px) {
  .orow {
    flex-wrap: wrap;
  }
  .orow-main {
    flex-basis: 100%;
  }
  .orow-side {
    width: 100%;
    padding-left: 66px;
    flex-wrap: wrap;
  }
  .orow-money {
    align-items: flex-start;
    width: auto;
  }
  .orow-side > .sp-chip {
    width: auto;
  }
  .orow-actions {
    width: auto;
    margin-left: auto;
  }
}
@media (max-width: 900px) {
  .pipe {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .pipe-arrow {
    display: none;
  }
}
@media (max-width: 640px) {
  .orow-side {
    padding-left: 0;
  }
  .orow-actions {
    width: 100%;
    margin-left: 0;
  }
  .orow-actions .sp-btn {
    flex: 1;
  }
  .date-field {
    flex: 1;
  }
  .date-field .sp-input {
    width: 100%;
  }
  .narrow {
    flex: 1;
  }
}
</style>
