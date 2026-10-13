<template>
  <ShopLayout section="orders" title="Returns & Refunds" subtitle="Settle each request with the customer: agree on a refund or replacement, send it, and they confirm.">
    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <section class="sp-stats" aria-label="Requests summary">
      <template v-if="!counts">
        <div v-for="n in 4" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <div class="sp-stat" :class="{ alert: counts.needs_shop }"><span class="sp-stat-icon tone-amber" v-html="ICONS.alert"></span><div><strong>{{ counts.needs_shop }}</strong><span>Waiting on you</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon tone-blue" v-html="ICONS.history"></span><div><strong>{{ counts.open }}</strong><span>Open</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon tone-green" v-html="ICONS.check"></span><div><strong>{{ counts.completed }}</strong><span>Settled</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon tone-slate" v-html="ICONS.close"></span><div><strong>{{ counts.rejected }}</strong><span>Declined or withdrawn</span></div></div>
      </template>
    </section>

    <div class="rt-layout">
      <div class="rt-main">
        <div class="sp-toolbar">
          <div class="tabs-scroll">
            <div class="sp-seg" role="tablist" aria-label="Filter requests">
              <button v-for="t in TABS" :key="t.key" type="button" role="tab" :aria-selected="tab === t.key" :class="{ on: tab === t.key }" @click="setTab(t.key)">
                {{ t.label }}<span v-if="counts" class="count">{{ counts[t.count] ?? 0 }}</span>
              </button>
            </div>
          </div>
          <label class="sp-search">
            <span v-html="ICONS.search"></span>
            <input v-model="search" class="sp-input" type="search" placeholder="Order number or customer" aria-label="Search requests" />
          </label>
        </div>

        <section class="sp-card list-card" aria-label="Requests">
          <ul v-if="cases === null" class="rt-list">
            <li v-for="n in 4" :key="n" class="rt-row"><div class="grow"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-line w70"></div></div></li>
          </ul>
          <div v-else-if="!cases.length" class="sp-empty">
            <span class="sp-empty-icon" v-html="ICONS.check"></span>
            <h3>{{ search ? "No requests match" : "No requests here" }}</h3>
            <p>{{ search ? "Try another search." : "When a customer asks for a return or refund it shows up here." }}</p>
          </div>
          <ul v-else class="rt-list">
            <li v-for="c in cases" :key="c.id">
              <button type="button" class="rt-row" :class="{ on: openId === c.id, mine: c.next_actor === 'vendor' }" @click="openCase(c)">
                <span class="rt-type" :class="c.type">{{ c.type === "return" ? "Return" : "Refund" }}</span>
                <span class="rt-text">
                  <span class="rt-top">
                    <strong>{{ c.customer?.name || "Customer" }}</strong>
                    <span class="rt-num">{{ c.order?.order_number }}</span>
                  </span>
                  <span class="rt-reason">{{ c.reason_label }}<template v-if="c.reason"> · “{{ c.reason }}”</template></span>
                  <span class="rt-terms">{{ terms(c) }}</span>
                </span>
                <span class="rt-side">
                  <span class="sp-chip" :class="stageTone(c)">{{ CASE_STAGE[c.stage] ?? c.status }}</span>
                  <small>{{ timeAgo(c.last_activity_at) }}</small>
                </span>
              </button>
            </li>
          </ul>
          <footer v-if="meta.last_page > 1" class="pager">
            <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <div>
              <button type="button" class="sp-icon-btn" :disabled="meta.current_page <= 1" aria-label="Previous page" @click="go(meta.current_page - 1)"><span v-html="ICONS.chevronLeft"></span></button>
              <button type="button" class="sp-icon-btn" :disabled="meta.current_page >= meta.last_page" aria-label="Next page" @click="go(meta.current_page + 1)"><span v-html="ICONS.chevronRight"></span></button>
            </div>
          </footer>
        </section>
      </div>

      <aside class="sp-card rt-policy" aria-label="How returns work">
        <div class="sp-card-head"><div><h2>How it works</h2><p>BloomCraft's return &amp; refund policy</p></div></div>
        <ol>
          <li><b>1</b><span>The customer asks for a full refund, part of the money back, or a replacement, after the order is delivered.</span></li>
          <li><b>2</b><span>You accept, offer something else, or decline with a reason. You can message each other.</span></li>
          <li><b>3</b><span>If the item comes back, confirm when it arrives. Then send the refund (with a reference) or the replacement.</span></li>
          <li><b>4</b><span>The customer confirms they received it. Only then is a refund taken off your revenue, for the agreed amount.</span></li>
        </ol>
        <p class="sp-hint">While a request is open, the order's status can't be changed. BloomCraft can read every request.</p>
      </aside>
    </div>

    <!-- Case drawer -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="openId" class="sp-backdrop" @click="closeCase"></div></Transition>
      <Transition name="sp-slide">
        <aside v-if="openId" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="rt-title">
          <header class="sp-drawer-head">
            <div>
              <h3 id="rt-title">{{ active?.order?.order_number || "Request" }}</h3>
              <p>{{ active?.customer?.name }}</p>
            </div>
            <div class="drawer-actions">
              <button v-if="active?.order?.id" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="openOrder(active.order.id)">Open order</button>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeCase"><span v-html="ICONS.close"></span></button>
            </div>
          </header>
          <div class="sp-drawer-body rt-drawer">
            <ReturnCasePanel :case-id="openId" role="vendor" api-base="/sc/order-requests" @changed="load" />
          </div>
        </aside>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import ReturnCasePanel from "../../../components/returns/ReturnCasePanel.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { CASE_STAGE, money, timeAgo } from "../../../components/vendor/orders/orderUi";
import { linkedId } from "../../../utils/hiddenIds";
import "../../../components/vendor/shop/shop.css";

const router = useRouter();
const TABS = [
  { key: "open", label: "Open", count: "open" },
  { key: "pending", label: "Agreeing", count: "pending" },
  { key: "approved", label: "Agreed · in progress", count: "approved" },
  { key: "completed", label: "Settled", count: "completed" },
  { key: "rejected", label: "Declined", count: "rejected" },
  { key: "", label: "All", count: "all" },
];

const tab = ref("open");
const search = ref("");
const cases = ref(null);
const counts = ref(null);
const meta = ref({ current_page: 1, last_page: 1 });
const error = ref("");
const openId = ref(null);
const page = ref(1);
let timer = null;
let seq = 0;

const active = computed(() => cases.value?.find((c) => c.id === openId.value) ?? null);

async function load() {
  const mine = ++seq;
  error.value = "";
  try {
    const { data } = await api.get("sc/order-requests", {
      params: { status: tab.value || undefined, search: search.value.trim() || undefined, page: page.value, per_page: 15 },
    });
    if (mine !== seq) return;
    cases.value = data.data;
    counts.value = data.counts;
    meta.value = data.meta;
  } catch (e) {
    if (mine !== seq) return;
    error.value = apiMessage(e, "Couldn't load return and refund requests.");
    cases.value ??= [];
  }
}
function setTab(key) {
  tab.value = key;
  page.value = 1;
  cases.value = null;
  load();
}
function go(p) {
  page.value = p;
  load();
}
watch(search, () => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    page.value = 1;
    load();
  }, 300);
});

// A full refund is the whole order (older requests didn't store the amount)
const describe = (c, res, amount) =>
  res === "replacement"
    ? "a replacement"
    : `${res === "full_refund" ? "a full refund" : "a partial refund"} of ${money(res === "full_refund" ? amount || c.order?.total_amount : amount)}`;
function terms(c) {
  if (c.settled) return `Agreed: ${describe(c, c.settled.resolution, c.settled.amount)}`;
  if (c.offer) return `${c.offer.by === "vendor" ? "You offered" : "Customer asks for"} ${describe(c, c.offer.resolution, c.offer.amount)}`;
  if (c.closed_reason) return c.closed_reason;
  return `Asked for ${describe(c, c.requested.resolution, c.requested.amount)}`;
}
function stageTone(c) {
  if (c.next_actor === "vendor") return "amber";
  if (c.status === "completed") return "green";
  if (["rejected", "withdrawn"].includes(c.status)) return "slate";
  return "blue";
}

const openCase = (c) => (openId.value = c.id);
const closeCase = () => (openId.value = null);
const openOrder = (id) => router.push({ name: "VendorOrderDetails", query: { order: id } });

onMounted(async () => {
  // Linked from an order: show all of that order's requests and open the latest
  const fromOrder = linkedId("order");
  if (fromOrder) tab.value = "";
  await load();
  if (fromOrder) {
    const hit = cases.value?.find((c) => c.order?.id === fromOrder);
    if (hit) openId.value = hit.id;
  }
});
onBeforeUnmount(() => clearTimeout(timer));
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
}
.sp-stat.alert {
  border-color: #fbd38d;
  background: #fffcf5;
}
.rt-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 16px;
  align-items: start;
}
.rt-main {
  min-width: 0;
}
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
.list-card {
  padding: 6px;
}
.rt-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.rt-list li + li {
  border-top: 1px solid #f2f5f3;
}
.rt-row {
  display: flex;
  align-items: center;
  gap: 14px;
  width: 100%;
  padding: 12px;
  border: 0;
  border-radius: 13px;
  background: none;
  font-family: "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s;
}
.rt-row:hover,
.rt-row.on {
  background: #f8fcf9;
}
.rt-row.mine {
  box-shadow: inset 3px 0 0 #f6ad55;
}
.rt-type {
  flex: none;
  display: grid;
  place-items: center;
  width: 64px;
  height: 40px;
  border-radius: 12px;
  font-size: 11.5px;
  font-weight: 700;
}
.rt-type.return {
  background: #f3f0ff;
  color: #6b46c1;
}
.rt-type.refund {
  background: #fff0f6;
  color: #b83280;
}
.rt-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.rt-top {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
}
.rt-top strong {
  font-size: 13.5px;
  font-weight: 600;
  color: #1a202c;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rt-num {
  flex: none;
  font-size: 11.5px;
  color: #a0aec0;
}
.rt-reason,
.rt-terms {
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rt-reason {
  color: #4a5568;
}
.rt-terms {
  color: #718096;
}
.rt-side {
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}
.rt-side small {
  font-size: 11px;
  color: #a0aec0;
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
.rt-policy {
  position: sticky;
  top: 16px;
}
.rt-policy ol {
  list-style: none;
  margin: 0 0 12px;
  padding: 0;
  display: grid;
  gap: 12px;
}
.rt-policy li {
  display: flex;
  gap: 10px;
  font-size: 12.5px;
  line-height: 1.5;
  color: #4a5568;
}
.rt-policy b {
  flex: none;
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #e6f9ee;
  color: #22753f;
  font-size: 11.5px;
}
.drawer-actions {
  display: flex;
  gap: 6px;
}
.rt-drawer {
  --return-accent: #38a169;
  --return-accent-soft: #f0fff4;
}
@media (max-width: 1100px) {
  .rt-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .rt-policy {
    position: static;
  }
}
@media (max-width: 640px) {
  .rt-row {
    flex-wrap: wrap;
  }
  .rt-type {
    width: auto;
    height: auto;
    padding: 4px 10px;
  }
  .rt-text {
    flex-basis: 100%;
  }
  .rt-side {
    flex-direction: row;
    align-items: center;
  }
}
</style>
