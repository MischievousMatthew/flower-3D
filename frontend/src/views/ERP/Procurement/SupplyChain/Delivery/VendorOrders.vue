<template>
  <HrPage title="My deliveries" subtitle="Your orders on their way to customers: prepare them, photograph them, send them out and scan their labels.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="fetchOrders"><span v-html="ICONS.restore"></span>Refresh</button>
      <button type="button" class="sp-btn sp-btn-primary" @click="openScanner()"><span v-html="ICONS.grid"></span>Scan a label</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="fetchOrders">Try again</button>
    </div>

    <!-- Due today and Overdue -->
    <section class="vd-due" aria-label="Due today and overdue">
      <button
        v-for="t in DUE_TILES"
        :key="t.key"
        type="button"
        class="vd-due-tile"
        :class="[`tone-${t.tone}`, { on: activeTab === t.key, hot: t.key === 'overdue' && counts.overdue, zero: !counts[t.key] }]"
        @click="setTab(t.key)"
      >
        <span class="vd-due-icon" v-html="ICONS[t.icon]"></span>
        <span class="vd-due-text">
          <span class="vd-due-label">{{ t.label }}</span>
          <span class="vd-due-hint">{{ counts[t.key] ? t.hint : t.empty }}</span>
        </span>
        <strong>{{ counts[t.key] ?? 0 }}</strong>
      </button>
    </section>

    <!-- Stages -->
    <section class="vd-pipe" aria-label="Orders by stage">
      <button v-for="(st, i) in PIPELINE" :key="st.key" type="button" class="vd-pipe-step" :class="[`tone-${st.tone}`, { on: activeTab === st.key }]" @click="setTab(st.key)">
        <span class="vd-pipe-label"><span class="vd-pipe-dot"></span>{{ st.label }}</span>
        <strong>{{ counts[st.key] ?? 0 }}</strong>
        <span class="vd-pipe-hint">{{ st.hint }}</span>
        <span v-if="i < PIPELINE.length - 1" class="vd-pipe-arrow" aria-hidden="true" v-html="ICONS.chevronRight"></span>
      </button>
    </section>

    <!-- Tabs and search -->
    <div class="vd-toolbar">
      <div class="vd-tabs">
        <div class="sp-seg" role="tablist" aria-label="Filter deliveries">
          <button v-for="t in TABS" :key="t.value" type="button" role="tab" :aria-selected="activeTab === t.value" :class="{ on: activeTab === t.value }" @click="setTab(t.value)">
            {{ t.label }}<span class="count">{{ counts[t.value] ?? 0 }}</span>
          </button>
        </div>
      </div>
      <label class="sp-search vd-search">
        <span v-html="ICONS.search"></span>
        <input v-model="search" class="sp-input" type="search" placeholder="Order number or delivery ID" aria-label="Search deliveries" @input="debouncedFetch" />
      </label>
    </div>

    <!-- List -->
    <section class="sp-card vd-list-card" :class="TAB_TONE[activeTab] ? ['toned', `tone-${TAB_TONE[activeTab]}`] : null" aria-label="Deliveries">
      <ul v-if="loading && !orders.length" class="vd-list">
        <li v-for="n in 5" :key="n" class="vd-row">
          <div class="bc-skeleton vd-thumb"></div>
          <div class="vd-grow"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-line w70"></div></div>
          <div class="bc-skeleton vd-sk-btn"></div>
        </li>
      </ul>
      <div v-else-if="!orders.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.truck"></span>
        <h3>{{ search ? "No deliveries match" : activeTab === "all" ? "No deliveries yet" : `Nothing ${TAB_EMPTY[activeTab] ?? "here"}` }}</h3>
        <p>{{ search ? "Try another order number." : "Orders show up here once customers check out." }}</p>
      </div>
      <ul v-else class="vd-list" :class="{ dim: loading }">
        <li v-for="d in orders" :key="d.id" class="vd-row">
          <div class="vd-main">
            <span class="vd-thumb-wrap">
              <img v-if="d.photo_url || firstImage(d)" :src="d.photo_url || firstImage(d)" :alt="d.photo_url ? 'Photo of the order' : itemNames(d)" class="vd-thumb" loading="lazy" />
              <span v-else class="vd-thumb blank" v-html="ICONS.flower"></span>
              <span v-if="(d.order?.items_count ?? 0) > 1" class="vd-thumb-count">×{{ d.order.items_count }}</span>
            </span>
            <span class="vd-text">
              <span class="vd-top">
                <strong>{{ d.order?.delivery_contact_name || "Customer" }}</strong>
                <span class="vd-num">{{ d.order?.order_number ?? d.delivery_id }}</span>
              </span>
              <span class="vd-items">{{ itemNames(d) || "—" }}</span>
              <span class="vd-meta">
                <span v-if="d.order?.reservation_date" class="vd-date"><span v-html="ICONS.truck"></span>{{ dayText(d.order.reservation_date) }}</span>
                <span v-if="lateDays(d) > 0" class="sp-chip red">{{ lateDays(d) }} {{ lateDays(d) === 1 ? "day" : "days" }} late</span>
                <span v-else-if="lateDays(d) === 0" class="sp-chip green">Due today</span>
                <span v-if="d.status === 'to_ship' && !d.photo_url" class="sp-chip amber">Needs a photo</span>
                <span v-else-if="d.photo_url && ['to_processed', 'to_ship'].includes(d.status)" class="sp-chip teal">Photo taken</span>
              </span>
            </span>
          </div>
          <div class="vd-side">
            <div class="vd-money">
              <strong>{{ peso(d.order?.total_amount) }}</strong>
              <span>{{ PAYMENT[d.order?.payment_method] ?? d.order?.payment_method ?? "—" }}<template v-if="d.order?.payment_status === 'paid'"> · paid</template></span>
            </div>
            <span class="sp-chip" :class="STATUS_TONE[d.status] ?? 'slate'">{{ STATUS_LABEL[d.status] ?? d.status_label }}</span>
            <div class="vd-actions">
              <button v-if="NEXT[d.status]" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="act(d)">{{ NEXT[d.status].label }}</button>
              <span v-else-if="d.status === 'to_received'" class="vd-wait" title="The customer marks it received, or it completes by itself 3 days after it went out">Waiting for the customer</span>
              <button type="button" class="sp-icon-btn" title="Delivery label" :aria-label="`Delivery label for ${d.order?.order_number ?? d.delivery_id}`" @click="barcode.open(d.order?.id, d.delivery_id)">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" width="16" height="16" aria-hidden="true"><path d="M3 4v12M6 4v12M8.5 4v12M11 4v12M14 4v12M17 4v12" /></svg>
              </button>
            </div>
          </div>
        </li>
      </ul>
      <ShowMore v-if="orders.length" tone="vendor" :has-more="pager.hasMore.value" :loading="pager.loadingMore.value" :shown="orders.length" :total="pager.total.value" @more="pager.showMore()" />
    </section>

    <!-- Next step (asks for the photo before it goes out) -->
    <OrderActionModal :order="acting?.order" :action="acting?.action" @close="acting = null" @done="fetchOrders" />
    <BarcodeModal ref="barcode" />

    <!-- Scan a label -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="scan.open" class="sp-backdrop" @click="closeScan"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="scan.open" class="sp-modal-wrap">
          <form class="sp-modal vd-scan" role="dialog" aria-modal="true" aria-labelledby="vd-scan-title" @submit.prevent="submitScan">
            <div class="sp-modal-head">
              <div>
                <h3 id="vd-scan-title">Scan a delivery label</h3>
                <p>Scan it with a barcode scanner, or type the code. Each scan moves the order to its next step.</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeScan"><span v-html="ICONS.close"></span></button>
            </div>
            <label class="sp-field">
              <span class="sp-label">Code on the label</span>
              <input ref="scanInput" v-model.trim="scan.code" class="sp-input vd-scan-input" placeholder="DLV-XXXXXXXXXX" autocomplete="off" :disabled="scan.busy" />
            </label>
            <p v-if="scan.error" class="sp-error" role="alert">{{ scan.error }}</p>
            <div v-if="scan.result" class="vd-scan-ok" role="status">
              <span v-html="ICONS.check"></span>
              <div><b>{{ scan.result.order?.order_number ?? scan.result.delivery_id }}</b><small>Now {{ STATUS_LABEL[scan.result.status] ?? scan.result.status_label }}</small></div>
            </div>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="closeScan">Done</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="scan.busy || !scan.code">{{ scan.busy ? "Scanning…" : "Scan" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import HrPage from "../../../../../components/hr/HrPage.vue";
import ShowMore from "../../../../../components/common/ShowMore.vue";
import OrderActionModal from "../../../../../components/vendor/orders/OrderActionModal.vue";
import BarcodeModal from "../../../../../layouts/components/BarcodeModal.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { useShowMorePaged } from "../../../../../composables/useShowMore";
import { deliveryService } from "../../../../../services/deliveryService";
import "../../../../../components/vendor/finance/finance.css";

// The same stages and words as the vendor's Orders page
const STATUS_LABEL = { pending: "Pending", to_processed: "Preparing", to_ship: "Ready", to_received: "Out for delivery", completed: "Completed", returned: "Returned", refunded: "Refunded" };
const STATUS_TONE = { pending: "amber", to_processed: "blue", to_ship: "violet", to_received: "teal", completed: "green", returned: "red", refunded: "red" };
const PAYMENT = { cod: "Cash on delivery", ewallet: "E-wallet", gcash: "GCash", maya: "Maya", card: "Card" };
const DUE_TILES = [
  { key: "due_today", label: "Due today", hint: "Going out today", empty: "Nothing due today", icon: "calendar", tone: "green" },
  { key: "overdue", label: "Overdue", hint: "Past their delivery date", empty: "Nothing late", icon: "alert", tone: "red" },
];
const DUE_FILTER = { due_today: "today", overdue: "overdue" };
// The colour of each stage, for the list's glow
const TAB_TONE = { pending: "amber", to_processed: "blue", to_ship: "violet", to_received: "teal", completed: "green", returned: "red", due_today: "green", overdue: "red" };
const PIPELINE = [
  { key: "pending", label: "Pending", hint: "Confirm them", tone: "amber" },
  { key: "to_processed", label: "Preparing", hint: "Being arranged", tone: "blue" },
  { key: "to_ship", label: "Ready", hint: "Photo, then send out", tone: "violet" },
  { key: "to_received", label: "Out for delivery", hint: "Customer confirms (or 3 days)", tone: "teal" },
  { key: "completed", label: "Completed", hint: "Customer has it", tone: "green" },
];
const TABS = [
  { value: "all", label: "All" },
  { value: "due_today", label: "Due today" },
  { value: "overdue", label: "Overdue" },
  ...PIPELINE.map((p) => ({ value: p.key, label: p.label })),
  { value: "returned", label: "Returned" },
];
const TAB_EMPTY = { pending: "pending", to_processed: "being prepared", to_ship: "ready", to_received: "out for delivery", completed: "completed yet", returned: "returned", due_today: "due today", overdue: "late" };
// The one next step from each stage; the customer completes it once it's out
const NEXT = {
  pending: { to: "to_processed", label: "Confirm & start preparing" },
  to_processed: { to: "to_ship", label: "Mark as ready" },
  to_ship: { to: "to_received", label: "Send out for delivery" },
};

const orders = ref([]);
const counts = ref({});
const loading = ref(false);
const error = ref("");
const search = ref("");
const activeTab = ref("all");
const acting = ref(null);
const barcode = ref(null);
const scanInput = ref(null);
const scan = reactive({ open: false, code: "", busy: false, error: "", result: null });
let fetchTimer = null;

const peso = (n) => `₱${Number(n || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const itemNames = (d) => (d.order?.items ?? []).map((i) => i.product_name).filter(Boolean).join(", ");
const firstImage = (d) => (d.order?.items ?? []).find((i) => i.product_image)?.product_image ?? "";
const ymd = (v) => String(v || "").slice(0, 10);
function dayText(date) {
  const d = ymd(date);
  if (!d) return "";
  const days = Math.round((new Date(`${d}T00:00:00`) - new Date(new Date().toDateString())) / 86400000);
  if (days === 0) return "Today";
  if (days === 1) return "Tomorrow";
  if (days === -1) return "Yesterday";
  return new Date(`${d}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric" });
}
// Days past the delivery date for an order still on its way (0 = due today, -1 = not due yet)
function lateDays(d) {
  if (["completed", "returned", "refunded"].includes(d.status)) return -1;
  const date = ymd(d.order?.reservation_date);
  if (!date) return -1;
  const days = Math.round((new Date(new Date().toDateString()) - new Date(`${date}T00:00:00`)) / 86400000);
  return days >= 0 ? days : -1;
}

/** One page of this shop's deliveries */
async function fetchOrderPage(page) {
  const params = { page, per_page: 12 };
  if (DUE_FILTER[activeTab.value]) params.due = DUE_FILTER[activeTab.value];
  else if (activeTab.value !== "all") params.status = activeTab.value;
  if (search.value.trim()) params.search = search.value.trim();
  const res = await deliveryService.vendorOrders(params);
  const body = res?.data ?? res;
  counts.value = body?.status_counts ?? {};
  const meta = body?.meta ?? { last_page: 1 };
  return { items: (body?.data ?? []).filter(Boolean), lastPage: meta.last_page ?? 1, total: meta.total };
}
const pager = useShowMorePaged(fetchOrderPage);
watch(() => pager.visible.value, (rows) => (orders.value = rows));

async function fetchOrders() {
  loading.value = true;
  error.value = "";
  await pager.load();
  if (pager.error?.value || pager.failure?.value) error.value = "Couldn't load your deliveries.";
  loading.value = false;
}
function setTab(key) {
  activeTab.value = key;
  fetchOrders();
}
function debouncedFetch() {
  clearTimeout(fetchTimer);
  fetchTimer = setTimeout(fetchOrders, 350);
}

// The vendor's next-step window wants the order in its own shape
function act(d) {
  acting.value = {
    order: {
      id: d.order?.id,
      order_number: d.order?.order_number ?? d.delivery_id,
      customer: d.order?.delivery_contact_name || "Customer",
      status: d.status,
      status_label: STATUS_LABEL[d.status],
      photo_url: d.photo_url,
    },
    action: NEXT[d.status],
  };
}

async function openScanner() {
  Object.assign(scan, { open: true, code: "", busy: false, error: "", result: null });
  await nextTick();
  scanInput.value?.focus();
}
function closeScan() {
  const changed = !!scan.result;
  scan.open = false;
  if (changed) fetchOrders();
}
async function submitScan() {
  if (!scan.code) return;
  scan.busy = true;
  scan.error = "";
  scan.result = null;
  try {
    // "auto": one step forward from wherever it is, like Order Scan
    const res = await deliveryService.scan(scan.code, "auto");
    const body = res?.data ?? res;
    scan.result = body?.delivery ?? body;
    scan.code = "";
    await nextTick();
    scanInput.value?.focus();
  } catch (e) {
    scan.error = apiMessage(e, "That code didn't scan. Check it and try again.");
  } finally {
    scan.busy = false;
  }
}

onMounted(fetchOrders);
onBeforeUnmount(() => clearTimeout(fetchTimer));
</script>

<style scoped>
.tone-green { --tone: #68d391; --tone-soft: var(--v-green-soft); --tone-ink: var(--v-green-text); }
.tone-red { --tone: #fc8181; --tone-soft: var(--v-red-soft); --tone-ink: var(--v-red-text); }
.tone-amber { --tone: #f6ad55; --tone-soft: var(--v-amber-soft); --tone-ink: var(--v-amber-text); }
.tone-blue { --tone: #63b3ed; --tone-soft: var(--v-blue-soft); --tone-ink: var(--v-blue-text); }
.tone-violet { --tone: #b794f4; --tone-soft: var(--v-purple-soft); --tone-ink: var(--v-purple-text); }
.tone-teal { --tone: #4fd1c5; --tone-soft: var(--v-teal-soft); --tone-ink: var(--v-teal-text); }

/* Due today and Overdue */
.vd-due {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 14px;
}
.vd-due-tile {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
  background: var(--v-surface);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.vd-due-tile:hover,
.vd-due-tile.on {
  border-color: var(--tone);
  box-shadow: 0 4px 14px rgba(16, 24, 40, 0.06);
}
.vd-due-tile.on,
.vd-due-tile.hot {
  border-color: var(--tone);
  background: var(--tone-soft);
}
.vd-due-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--tone-soft);
  color: var(--tone-ink);
}
.vd-due-tile.hot .vd-due-icon,
.vd-due-tile.on .vd-due-icon {
  background: var(--v-surface);
}
.vd-due-icon :deep(svg) {
  width: 20px;
  height: 20px;
}
.vd-due-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.vd-due-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--tone-ink);
}
.vd-due-hint {
  font-size: 12px;
  color: var(--v-muted);
}
.vd-due-tile strong {
  font-size: 30px;
  font-weight: 600;
  line-height: 1;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.vd-due-tile.zero strong {
  color: var(--v-faint);
}

/* Stages */
.vd-pipe {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 14px;
}
.vd-pipe-step {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  padding: 14px 16px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
  background: var(--v-surface);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.vd-pipe-step:hover,
.vd-pipe-step.on {
  border-color: var(--tone);
  box-shadow: 0 4px 14px rgba(16, 24, 40, 0.06);
}
.vd-pipe-step.on {
  background: var(--tone-soft);
}
.vd-due-tile:focus-visible,
.vd-pipe-step:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.vd-pipe-label {
  display: flex;
  align-items: center;
  gap: 7px;
  overflow: hidden;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--tone-ink);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.vd-pipe-dot {
  flex: none;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--tone);
}
.vd-pipe-step strong {
  font-size: 24px;
  font-weight: 600;
  line-height: 1.2;
  color: var(--v-text);
}
.vd-pipe-hint {
  font-size: 11.5px;
  color: var(--v-faint);
}
.vd-pipe-arrow {
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
  background: var(--v-subtle);
  color: var(--v-faint);
}
.vd-pipe-arrow :deep(svg) {
  width: 14px;
  height: 14px;
}

/* Tabs and search */
.vd-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 14px;
}
.vd-tabs {
  max-width: 100%;
  overflow-x: auto;
  scrollbar-width: none;
}
.vd-tabs .sp-seg {
  flex-wrap: nowrap;
}
.vd-tabs .sp-seg button {
  white-space: nowrap;
}
.vd-search {
  flex: 0 1 300px;
}

/* List */
/* The list takes the colour of the stage you picked: a soft glow, not a loud one */
.vd-list-card {
  transition: border-color 0.25s, box-shadow 0.25s;
}
.vd-list-card.toned {
  border-color: color-mix(in srgb, var(--tone) 40%, var(--v-border));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--tone) 9%, transparent), 0 12px 32px -20px color-mix(in srgb, var(--tone) 70%, transparent);
}
.vd-list-card.toned .vd-row {
  background: color-mix(in srgb, var(--tone) 4%, transparent);
  box-shadow: inset 3px 0 0 color-mix(in srgb, var(--tone) 65%, transparent);
}
.vd-list-card.toned .vd-row:hover {
  background: color-mix(in srgb, var(--tone) 9%, transparent);
}
.vd-list-card.toned .vd-row + .vd-row {
  margin-top: 6px;
  border-top-color: transparent;
}
@media (prefers-reduced-motion: reduce) {
  .vd-list-card {
    transition: none;
  }
}

.vd-list-card {
  padding: 6px;
}
.vd-list {
  margin: 0;
  padding: 0;
  list-style: none;
  transition: opacity 0.2s;
}
.vd-list.dim {
  opacity: 0.55;
}
.vd-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px;
  border-radius: 14px;
}
.vd-row + .vd-row {
  border-top: 1px solid var(--v-border);
}
.vd-row:hover {
  background: var(--v-subtle);
}
.vd-main {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 14px;
}
.vd-thumb-wrap {
  position: relative;
  flex: none;
}
.vd-thumb {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 12px;
  object-fit: cover;
  background: var(--v-subtle);
}
.vd-thumb.blank {
  color: var(--v-faint);
}
.vd-thumb.blank :deep(svg) {
  width: 24px;
  height: 24px;
}
.vd-thumb-count {
  position: absolute;
  right: -6px;
  bottom: -6px;
  padding: 1px 6px;
  border: 2px solid var(--v-surface);
  border-radius: 999px;
  background: var(--v-text);
  color: var(--v-surface);
  font-size: 10.5px;
  font-weight: 600;
}
.vd-grow {
  flex: 1;
  min-width: 0;
}
.vd-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.vd-top {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px;
}
.vd-top strong {
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
}
.vd-num {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12px;
  color: var(--v-muted);
}
.vd-items {
  overflow: hidden;
  font-size: 13px;
  color: var(--v-text-2);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.vd-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 10px;
  font-size: 12px;
  color: var(--v-muted);
}
.vd-date {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--v-text-2);
  font-weight: 500;
}
.vd-date :deep(svg) {
  width: 13px;
  height: 13px;
}
.vd-side {
  flex: none;
  display: flex;
  align-items: center;
  gap: 14px;
}
.vd-money {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 110px;
  text-align: right;
}
.vd-money strong {
  font-size: 14px;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.vd-money span {
  font-size: 11.5px;
  color: var(--v-muted);
}
.vd-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.vd-wait {
  padding: 6px 10px;
  border-radius: 8px;
  background: var(--v-subtle);
  font-size: 12px;
  color: var(--v-muted);
  white-space: nowrap;
}
.vd-sk-btn {
  width: 110px;
  height: 34px;
  border-radius: 10px;
}

/* Scan */
.vd-scan {
  max-width: 440px;
}
.vd-scan-input {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 16px;
  letter-spacing: 0.06em;
}
.vd-scan-ok {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.vd-scan-ok :deep(svg) {
  width: 18px;
  height: 18px;
}
.vd-scan-ok div {
  display: flex;
  flex-direction: column;
}
.vd-scan-ok b {
  font-size: 13.5px;
  color: var(--v-text);
}
.vd-scan-ok small {
  font-size: 12px;
}

@media (max-width: 1280px) {
  .vd-pipe {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  .vd-pipe-arrow {
    display: none;
  }
}
@media (max-width: 760px) {
  .vd-pipe {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .vd-row {
    flex-wrap: wrap;
  }
  .vd-side {
    width: 100%;
    flex-wrap: wrap;
    justify-content: space-between;
    padding-left: 70px;
  }
  .vd-money {
    align-items: flex-start;
    text-align: left;
  }
  .vd-search {
    flex-basis: 100%;
  }
}
@media (max-width: 560px) {
  .vd-due {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (prefers-reduced-motion: reduce) {
  .vd-due-tile,
  .vd-pipe-step,
  .vd-list {
    transition: none;
  }
}
</style>
