<template>
  <ShopLayout section="orders" title="Deliveries" subtitle="What's going out and when, and when to start preparing each order.">
    <template #actions>
      <div class="sp-seg" role="tablist" aria-label="How far ahead">
        <button v-for="d in RANGES" :key="d" type="button" role="tab" :aria-selected="days === d" :class="{ on: days === d }" @click="setDays(d)">{{ d }} days</button>
      </div>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Summary -->
    <!-- Summary: each tile filters the list below; pressing it again shows everything -->
    <section class="dv-stats" aria-label="Delivery summary, choose one to filter the list">
      <template v-if="!data">
        <div v-for="n in 5" :key="n" class="dv-tile"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton dv-sk-num"></div><div class="bc-skeleton sp-sk-line w70"></div></div>
      </template>
      <template v-else>
        <button
          v-for="s in STATS"
          :key="s.key"
          type="button"
          class="dv-tile"
          :class="[`tone-${s.tone}`, { alert: s.key === 'overdue' && data.stats.overdue, zero: !data.stats[s.key], on: filter === s.key }]"
          :aria-pressed="filter === s.key"
          @click="toggleFilter(s.key)"
        >
          <span class="dv-tile-label"><span class="dv-tile-icon" v-html="ICONS[s.icon]"></span>{{ s.label }}</span>
          <strong>{{ data.stats[s.key] }}</strong>
          <span class="dv-tile-hint">{{ filter === s.key ? "Showing these · tap to show all" : s.hint }}</span>
        </button>
      </template>
    </section>

    <div class="dv-layout">
      <div class="dv-main">
        <!-- One tile picked: just those orders -->
        <section v-if="data && activeStat" class="sp-card dv-day" :class="{ overdue: filter === 'overdue' }" :aria-label="activeStat.label">
          <header class="dv-day-head">
            <span class="dv-date" :class="[`tone-${activeStat.tone}`, 'dv-filter-icon']" aria-hidden="true"><span v-html="ICONS[activeStat.icon]"></span></span>
            <div class="dv-day-title">
              <h2>{{ activeStat.label }}</h2>
              <p>{{ filtered.length ? `${filtered.length} ${filtered.length === 1 ? "order" : "orders"} · ${activeStat.hint.toLowerCase()}` : activeStat.empty }}</p>
            </div>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="filter = null">Show all</button>
          </header>
          <p v-if="!filtered.length" class="dv-none">No orders here right now. Pick another tile, or show all to see every day.</p>
          <ul v-else class="dv-list">
            <DeliveryRow v-for="o in filtered" :key="o.id" :order="o" show-date @open="open" @act="act" />
          </ul>
        </section>

        <template v-if="!activeStat">
        <!-- Overdue -->
        <section v-if="data?.overdue.length" class="sp-card dv-day overdue" aria-label="Overdue">
          <header class="dv-day-head">
            <span class="dv-date late" aria-hidden="true"><span v-html="ICONS.alert"></span></span>
            <div class="dv-day-title"><h2>Overdue</h2><p>Past their delivery date and not with the customer yet</p></div>
            <span class="sp-chip red">{{ data.overdue.length }}</span>
          </header>
          <ul class="dv-list">
            <DeliveryRow v-for="o in data.overdue" :key="o.id" :order="o" show-date @open="open" @act="act" />
          </ul>
        </section>

        <!-- Loading -->
        <template v-if="!data">
          <div v-for="n in 3" :key="n" class="sp-card dv-day"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-block"></div></div>
        </template>

        <!-- Days -->
        <template v-else>
          <section v-for="d in visibleDays" :key="d.date" class="sp-card dv-day" :class="{ today: d.date === data.today }" :aria-label="dayLabel(d.date, { long: true })">
            <header class="dv-day-head">
              <span class="dv-date" :class="{ today: d.date === data.today }" aria-hidden="true">
                <small>{{ weekday(d.date) }}</small>
                <b>{{ dayNum(d.date) }}</b>
              </span>
              <div class="dv-day-title">
                <h2>{{ dayLabel(d.date, { long: true }) }}<span v-if="d.date === data.today" class="dv-today">Today</span></h2>
                <p>{{ d.orders.length ? `${d.orders.length} ${d.orders.length === 1 ? "delivery" : "deliveries"}` : "Nothing going out" }}</p>
              </div>
              <div v-if="d.orders.length" class="cap" :title="`${d.orders.length} of ${data.fulfillment.max_orders_per_day} orders a day`">
                <small>{{ d.orders.length >= data.fulfillment.max_orders_per_day ? "Fully booked" : `${data.fulfillment.max_orders_per_day - d.orders.length} spots left` }}</small>
                <span class="cap-bar"><span :class="{ full: d.orders.length >= data.fulfillment.max_orders_per_day }" :style="{ width: `${Math.min(100, (d.orders.length / data.fulfillment.max_orders_per_day) * 100)}%` }"></span></span>
              </div>
            </header>
            <p v-if="!d.orders.length" class="dv-none">Nothing to prepare or send this day.</p>
            <ul v-if="d.orders.length" class="dv-list">
              <DeliveryRow v-for="o in d.orders" :key="o.id" :order="o" @open="open" @act="act" />
            </ul>
          </section>
          <p v-if="hiddenEmpty" class="sp-hint quiet">{{ hiddenEmpty }} other {{ hiddenEmpty === 1 ? "day has" : "days have" }} no deliveries.</p>
        </template>
        </template>
      </div>

      <!-- Side -->
      <aside class="dv-side">
        <section class="sp-card" aria-label="How you deliver">
          <div class="sp-card-head"><div><h2>How you deliver</h2><p>From your shop settings</p></div></div>
          <template v-if="data">
            <ul class="how">
              <li><span v-html="ICONS.truck"></span><div><strong>{{ data.fulfillment.handled_by }}</strong><small>Delivery to the customer's address</small></div></li>
              <li><span v-html="ICONS.clock"></span><div><strong>{{ data.fulfillment.lead_time_days ? `${data.fulfillment.lead_time_days}-day lead time` : "No lead time" }}</strong><small>Earliest date customers can book</small></div></li>
              <li><span v-html="ICONS.sparkle"></span><div><strong>{{ data.fulfillment.same_day ? "Same-day delivery on" : "No same-day delivery" }}</strong><small>Closes at your closing time</small></div></li>
              <li><span v-html="ICONS.box"></span><div><strong>Up to {{ data.fulfillment.max_orders_per_day }} orders a day</strong><small>Fully booked days are closed to customers</small></div></li>
            </ul>
            <router-link :to="{ name: 'VendorShop' }" class="sp-btn sp-btn-ghost sp-btn-sm full">Business hours in My Shop</router-link>
          </template>
          <div v-else><div class="bc-skeleton sp-sk-block"></div></div>
        </section>
        <section class="sp-card legend" aria-label="What to do">
          <div class="sp-card-head"><div><h2>Order flow</h2></div></div>
          <ol class="flow">
            <li><span class="flow-dot amber"></span><div><b>Pending</b><small>Confirm it and start preparing</small></div></li>
            <li><span class="flow-dot blue"></span><div><b>Preparing</b><small>Arrange and pack it</small></div></li>
            <li><span class="flow-dot violet"></span><div><b>Ready</b><small>Take a photo, then send it out</small></div></li>
            <li><span class="flow-dot teal"></span><div><b>Out for delivery</b><small>The customer marks it received (or it completes after 3 days)</small></div></li>
            <li><span class="flow-dot green"></span><div><b>Completed</b><small>The customer has it</small></div></li>
          </ol>
          <label class="done-toggle">
            <span class="sp-switch"><input v-model="includeDone" type="checkbox" @change="load" /><span></span></span>
            Show delivered orders too
          </label>
        </section>
      </aside>
    </div>

    <OrderActionModal :order="acting?.order" :action="acting?.action" @close="acting = null" @done="load" />
  </ShopLayout>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import OrderActionModal from "../../../components/vendor/orders/OrderActionModal.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { STATUS_TONE, dayLabel, daysFromToday, money, shouldPrepare } from "../../../components/vendor/orders/orderUi";
import "../../../components/vendor/shop/shop.css";

const router = useRouter();
const RANGES = [7, 14, 30];
const DONE = ["completed", "cancelled", "returned", "refunded"];
// Each tile's test matches how the server counts it (VendorFulfillmentController::schedule)
const STATS = [
  { key: "today", label: "Due today", icon: "calendar", tone: "green", hint: "Going out today", empty: "Nothing due today", test: (o, today) => o.delivery_date === today && !DONE.includes(o.status) },
  { key: "prepare_now", label: "Start preparing", icon: "clock", tone: "amber", hint: "Needs work now", empty: "Nothing to start preparing", test: (o, today) => ["pending", "to_processed"].includes(o.status) && !!o.prepare_by && o.prepare_by <= today },
  { key: "ready", label: "Ready to send", icon: "box", tone: "violet", hint: "Photo, then send out", empty: "Nothing ready to send", test: (o) => o.status === "to_ship" },
  { key: "out", label: "Out for delivery", icon: "truck", tone: "teal", hint: "Waiting for the customer", empty: "Nothing out for delivery", test: (o) => o.status === "to_received" },
  { key: "overdue", label: "Overdue", icon: "alert", tone: "red", hint: "Past their date", empty: "Nothing overdue", test: (o, today) => o.delivery_date < today && !DONE.includes(o.status) },
];
const weekday = (ymd) => new Date(`${ymd}T00:00:00`).toLocaleDateString("en-PH", { weekday: "short" });
const dayNum = (ymd) => new Date(`${ymd}T00:00:00`).getDate();

const days = ref(7);
const includeDone = ref(false);
const data = ref(null);
const error = ref("");
const acting = ref(null);

// Today and tomorrow always show; other empty days are summed up in one line
const visibleDays = computed(() => (data.value?.days ?? []).filter((d, i) => i < 2 || d.orders.length));
const hiddenEmpty = computed(() => (data.value?.days ?? []).length - visibleDays.value.length);

// The tile picked above the list (null: every day, as usual)
const filter = ref(null);
const activeStat = computed(() => STATS.find((s) => s.key === filter.value) ?? null);
const toggleFilter = (key) => (filter.value = filter.value === key ? null : key);
// Every order on the page, overdue first, then by delivery date
const filtered = computed(() => {
  if (!data.value || !activeStat.value) return [];
  const all = [...data.value.overdue, ...data.value.days.flatMap((d) => d.orders)];
  const seen = new Set();
  return all
    .filter((o) => !seen.has(o.id) && seen.add(o.id) && activeStat.value.test(o, data.value.today))
    .sort((a, b) => a.delivery_date.localeCompare(b.delivery_date));
});

async function load() {
  error.value = "";
  try {
    const res = await api.get("vendor/fulfillment/schedule", { params: { days: days.value, include_done: includeDone.value ? 1 : 0 } });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load your deliveries.");
  }
}
function setDays(d) {
  days.value = d;
  load();
}
const open = (o) => router.push({ name: "VendorOrderDetails", query: { order: o.id } });
const act = (order, action) => (acting.value = { order, action });

// One order in the schedule
const DeliveryRow = defineComponent({
  props: { order: Object, showDate: Boolean },
  emits: ["open", "act"],
  setup(props, { emit }) {
    return () => {
      const o = props.order;
      const prepNote = ["pending", "to_processed"].includes(o.status) && o.preparation_days
        ? (shouldPrepare(o) ? "Start preparing now" : `Start preparing ${dayLabel(o.prepare_by)}`)
        : null;
      return h("li", { class: "dv-row" }, [
        h("button", { type: "button", class: "dv-row-main", onClick: () => emit("open", o) }, [
          o.first_item?.image
            ? h("img", { src: o.first_item.image, alt: o.first_item.name, class: "sp-thumb", loading: "lazy" })
            : h("span", { class: "sp-thumb blank", innerHTML: ICONS.flower }),
          h("span", { class: "dv-row-text" }, [
            h("span", { class: "dv-row-top" }, [
              h("strong", o.customer),
              h("span", { class: "dv-num" }, o.order_number),
            ]),
            h("span", { class: "dv-items" }, `${o.items_count} × ${o.item_names}`),
            h("span", { class: "dv-meta" }, [
              props.showDate
                ? daysFromToday(o.delivery_date) < 0 && !DONE.includes(o.status)
                  ? h("span", { class: "dv-late" }, `${dayLabel(o.delivery_date)} · ${Math.abs(daysFromToday(o.delivery_date))} d late`)
                  : h("span", { class: "dv-when" }, `Delivery ${dayLabel(o.delivery_date)}`)
                : null,
              o.address ? h("span", { class: "dv-addr" }, o.address) : null,
              prepNote ? h("span", { class: ["dv-prep", { now: shouldPrepare(o) }] }, prepNote) : null,
              o.status === "to_ship" && !o.photo_url ? h("span", { class: "sp-chip amber" }, "Needs a photo") : null,
              o.photo_url && ["to_processed", "to_ship"].includes(o.status) ? h("span", { class: "sp-chip teal" }, "Photo taken") : null,
            ]),
          ]),
        ]),
        h("div", { class: "dv-row-side" }, [
          h("span", { class: "dv-total" }, money(o.total)),
          h("span", { class: ["sp-chip", STATUS_TONE[o.status]] }, o.status_label),
          o.next.length
            ? h("button", { type: "button", class: "sp-btn sp-btn-primary sp-btn-sm", onClick: () => emit("act", o, o.next[0]) }, o.next[0].label)
            : o.status === "to_received"
              ? h("span", { class: "dv-wait", title: "The customer marks it received, or it completes by itself 3 days after it went out" }, "Waiting for the customer")
              : h("button", { type: "button", class: "sp-btn sp-btn-ghost sp-btn-sm", onClick: () => emit("open", o) }, "Details"),
        ]),
      ]);
    };
  },
});

onMounted(load);
</script>

<style scoped>
.grow {
  flex: 1;
}
.dv-stats {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 18px;
}
/* Summary tiles, like the stage cards on Orders */
.dv-tile {
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
}
button.dv-tile {
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
}
button.dv-tile:hover {
  border-color: var(--tone);
}
button.dv-tile:focus-visible {
  outline: 2px solid var(--tone);
  outline-offset: 2px;
}
button.dv-tile.on {
  border-color: var(--tone);
  box-shadow: 0 0 0 2px var(--tone), 0 0 0 5px color-mix(in srgb, var(--tone) 18%, transparent);
}
.dv-filter-icon {
  border-color: transparent;
  background: var(--tone-soft);
  color: var(--tone-ink);
}
.dv-filter-icon :deep(svg) {
  width: 20px;
  height: 20px;
}
:deep(.dv-when) {
  font-size: 12px;
  font-weight: 500;
  color: var(--v-text-2);
}
@media (prefers-reduced-motion: reduce) {
  button.dv-tile {
    transition: none;
  }
}
.tone-green { --tone: #68d391; --tone-soft: var(--v-green-soft); --tone-ink: var(--v-green-text); }
.tone-amber { --tone: #f6ad55; --tone-soft: var(--v-amber-soft); --tone-ink: var(--v-amber-text); }
.tone-violet { --tone: #b794f4; --tone-soft: var(--v-purple-soft); --tone-ink: var(--v-purple-text); }
.tone-teal { --tone: #4fd1c5; --tone-soft: var(--v-teal-soft); --tone-ink: var(--v-teal-text); }
.tone-red { --tone: #fc8181; --tone-soft: var(--v-red-soft); --tone-ink: var(--v-red-text); }
.dv-tile.alert {
  border-color: var(--tone);
  background: var(--tone-soft);
}
.dv-tile-label {
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
.dv-tile-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border-radius: 8px;
  background: var(--tone-soft);
}
.dv-tile-icon :deep(svg) {
  width: 14px;
  height: 14px;
}
.dv-tile strong {
  margin-top: 4px;
  font-size: 26px;
  font-weight: 600;
  line-height: 1.15;
  color: var(--v-text);
}
.dv-tile.zero strong {
  color: var(--v-faint);
}
.dv-tile-hint {
  font-size: 11.5px;
  color: var(--v-faint);
}
.dv-sk-num {
  width: 40%;
  height: 26px;
  margin: 6px 0;
}

/* Date badge on each day */
.dv-date {
  flex: none;
  display: grid;
  place-items: center;
  align-content: center;
  width: 48px;
  height: 52px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-subtle);
  line-height: 1.1;
}
.dv-date small {
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--v-muted);
}
.dv-date b {
  font-size: 19px;
  font-weight: 700;
  color: var(--v-text);
}
.dv-date.today {
  border-color: var(--bc-accent);
  background: var(--bc-accent);
}
.dv-date.today small,
.dv-date.today b {
  color: var(--bc-accent-ink, #fff);
}
.dv-date.late {
  border-color: var(--v-red-text);
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.dv-date.late :deep(svg) {
  width: 20px;
  height: 20px;
}
.dv-day-title {
  flex: 1;
  min-width: 0;
}
.dv-today {
  margin-left: 8px;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 11px;
  font-weight: 600;
  vertical-align: middle;
}
.dv-none {
  margin: 0;
  padding: 2px 0 2px 62px;
  font-size: 12.5px;
  color: var(--v-faint);
}
.dv-wait {
  padding: 6px 10px;
  border-radius: 8px;
  background: var(--v-subtle);
  font-size: 12px;
  color: var(--v-muted);
  white-space: nowrap;
}

/* The order flow, as a short timeline */
.legend .flow {
  position: relative;
  display: grid;
  gap: 12px;
  margin: 0 0 14px;
  padding: 0;
  list-style: none;
}
.legend .flow li {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.legend .flow li:not(:last-child)::after {
  content: "";
  position: absolute;
  left: 5px;
  top: 16px;
  bottom: -12px;
  width: 2px;
  background: var(--v-border);
}
.flow-dot {
  flex: none;
  width: 12px;
  height: 12px;
  margin-top: 3px;
  border-radius: 50%;
}
.flow-dot.amber { background: #f6ad55; }
.flow-dot.blue { background: #63b3ed; }
.flow-dot.violet { background: #b794f4; }
.flow-dot.teal { background: #4fd1c5; }
.flow-dot.green { background: #68d391; }
.flow b {
  display: block;
  font-size: 13px;
  color: var(--v-text);
}
.flow small {
  font-size: 12px;
  line-height: 1.45;
  color: var(--v-muted);
}
.dv-stats .sp-stat {
  min-width: 0;
}
.dv-stats .sp-stat.alert {
  border-color: #feb2b2;
  background: var(--v-subtle);
}
.dv-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 16px;
  align-items: start;
}
.dv-main {
  display: grid;
  gap: 14px;
  min-width: 0;
}
.dv-day {
  padding: 16px 16px 8px;
}
.dv-day.today {
  border-color: var(--bc-accent-border);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 10%, transparent);
}
.dv-day.overdue {
  border-color: #feb2b2;
  background: var(--v-subtle);
}
.dv-day-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}
.dv-day-head h2 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--v-text);
}
.dv-day-head p {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--v-muted);
}
.cap {
  display: flex;
  align-items: center;
  gap: 8px;
}
.cap-bar {
  width: 90px;
  height: 6px;
  border-radius: 999px;
  background: var(--v-subtle-2);
  overflow: hidden;
}
.cap-bar span {
  display: block;
  height: 100%;
  background: var(--bc-accent);
}
.cap-bar span.full {
  background: #ed8936;
}
.cap small {
  font-size: 11.5px;
  color: var(--v-muted);
  font-variant-numeric: tabular-nums;
}
.dv-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
:deep(.dv-row) {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 4px;
  border-top: 1px solid var(--v-border);
}
:deep(.dv-row-main) {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0;
  border: 0;
  background: none;
  font-family: "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
}
:deep(.dv-row-text) {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}
:deep(.dv-row-top) {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
}
:deep(.dv-row-top strong) {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
:deep(.dv-num) {
  flex: none;
  font-size: 11px;
  color: var(--v-faint);
}
:deep(.dv-items),
:deep(.dv-addr) {
  font-size: 12px;
  color: var(--v-text-2);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
:deep(.dv-meta) {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  min-width: 0;
}
:deep(.dv-addr) {
  color: var(--v-muted);
  max-width: 100%;
}
:deep(.dv-prep) {
  font-size: 12px;
  font-weight: 500;
  color: var(--v-muted);
}
:deep(.dv-prep.now) {
  color: var(--v-amber-text);
}
:deep(.dv-late) {
  font-size: 12px;
  font-weight: 600;
  color: var(--v-red-text);
}
:deep(.dv-row-side) {
  flex: none;
  display: flex;
  align-items: center;
  gap: 10px;
}
:deep(.dv-total) {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
  min-width: 80px;
  text-align: right;
}
.quiet {
  margin: 0;
  text-align: center;
}
.dv-side {
  position: sticky;
  top: 16px;
  display: grid;
  gap: 14px;
}
.how {
  list-style: none;
  margin: 0 0 14px;
  padding: 0;
  display: grid;
  gap: 12px;
}
.how li {
  display: flex;
  gap: 10px;
}
.how li > span {
  flex: none;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
}
.how :deep(svg) {
  width: 16px;
  height: 16px;
}
.how strong {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.how small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.full {
  width: 100%;
}
.legend ol {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.legend li {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.legend .sp-chip {
  min-width: 118px;
  justify-content: center;
}
.done-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 16px;
  font-size: 13px;
  color: var(--v-text);
  cursor: pointer;
}
@media (max-width: 1180px) {
  .dv-stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 1100px) {
  .dv-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .dv-side {
    position: static;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 720px) {
  .dv-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .dv-side {
    grid-template-columns: minmax(0, 1fr);
  }
  :deep(.dv-row) {
    flex-wrap: wrap;
  }
  :deep(.dv-row-side) {
    width: 100%;
    justify-content: flex-end;
  }
  :deep(.dv-total) {
    min-width: 0;
    margin-right: auto;
    text-align: left;
  }
}
</style>
