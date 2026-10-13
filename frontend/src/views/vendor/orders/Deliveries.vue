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
    <section class="dv-stats" aria-label="Delivery summary">
      <template v-if="!data">
        <div v-for="n in 5" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <div v-for="s in STATS" :key="s.key" class="sp-stat" :class="{ alert: s.key === 'overdue' && data.stats.overdue }">
          <span class="sp-stat-icon" :class="s.tone" v-html="ICONS[s.icon]"></span>
          <div><strong>{{ data.stats[s.key] }}</strong><span>{{ s.label }}</span></div>
        </div>
      </template>
    </section>

    <div class="dv-layout">
      <div class="dv-main">
        <!-- Overdue -->
        <section v-if="data?.overdue.length" class="sp-card dv-day overdue" aria-label="Overdue">
          <header class="dv-day-head">
            <div><h2>Overdue</h2><p>Past their delivery date and not delivered yet</p></div>
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
              <div>
                <h2>{{ dayLabel(d.date, { long: true }) }}</h2>
                <p>{{ d.orders.length ? `${d.orders.length} ${d.orders.length === 1 ? "delivery" : "deliveries"}` : "Nothing going out" }}</p>
              </div>
              <div v-if="d.orders.length" class="cap" :title="`${d.orders.length} of ${data.fulfillment.max_orders_per_day} orders a day`">
                <span class="cap-bar"><span :class="{ full: d.orders.length >= data.fulfillment.max_orders_per_day }" :style="{ width: `${Math.min(100, (d.orders.length / data.fulfillment.max_orders_per_day) * 100)}%` }"></span></span>
                <small>{{ d.orders.length }}/{{ data.fulfillment.max_orders_per_day }}</small>
              </div>
            </header>
            <ul v-if="d.orders.length" class="dv-list">
              <DeliveryRow v-for="o in d.orders" :key="o.id" :order="o" @open="open" @act="act" />
            </ul>
          </section>
          <p v-if="hiddenEmpty" class="sp-hint quiet">{{ hiddenEmpty }} other {{ hiddenEmpty === 1 ? "day has" : "days have" }} no deliveries.</p>
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
          <ol>
            <li><span class="sp-chip amber">Pending</span>Confirm it</li>
            <li><span class="sp-chip blue">Preparing</span>Arrange &amp; pack</li>
            <li><span class="sp-chip violet">Ready</span>Hand to the rider</li>
            <li><span class="sp-chip teal">Out for delivery</span>Mark delivered</li>
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
const STATS = [
  { key: "today", label: "Due today", icon: "calendar", tone: "tone-green" },
  { key: "prepare_now", label: "Start preparing", icon: "clock", tone: "tone-amber" },
  { key: "ready", label: "Ready to send", icon: "box", tone: "tone-violet" },
  { key: "out", label: "Out for delivery", icon: "truck", tone: "tone-blue" },
  { key: "overdue", label: "Overdue", icon: "alert", tone: "tone-red" },
];

const days = ref(7);
const includeDone = ref(false);
const data = ref(null);
const error = ref("");
const acting = ref(null);

// Today and tomorrow always show; other empty days are summed up in one line
const visibleDays = computed(() => (data.value?.days ?? []).filter((d, i) => i < 2 || d.orders.length));
const hiddenEmpty = computed(() => (data.value?.days ?? []).length - visibleDays.value.length);

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
              props.showDate ? h("span", { class: "dv-late" }, `${dayLabel(o.delivery_date)} · ${Math.abs(daysFromToday(o.delivery_date))} d late`) : null,
              o.address ? h("span", { class: "dv-addr" }, o.address) : null,
              prepNote ? h("span", { class: ["dv-prep", { now: shouldPrepare(o) }] }, prepNote) : null,
            ]),
          ]),
        ]),
        h("div", { class: "dv-row-side" }, [
          h("span", { class: "dv-total" }, money(o.total)),
          h("span", { class: ["sp-chip", STATUS_TONE[o.status]] }, o.status_label),
          o.next.length
            ? h("button", { type: "button", class: "sp-btn sp-btn-primary sp-btn-sm", onClick: () => emit("act", o, o.next[0]) }, o.next[0].label)
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
.dv-stats .sp-stat {
  min-width: 0;
}
.dv-stats .sp-stat.alert {
  border-color: #feb2b2;
  background: #fffafa;
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
  border-color: #9ae6b4;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}
.dv-day.overdue {
  border-color: #feb2b2;
  background: #fffafa;
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
  color: #1a202c;
}
.dv-day-head p {
  margin: 2px 0 0;
  font-size: 12px;
  color: #718096;
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
  background: #edf2f7;
  overflow: hidden;
}
.cap-bar span {
  display: block;
  height: 100%;
  background: #48bb78;
}
.cap-bar span.full {
  background: #ed8936;
}
.cap small {
  font-size: 11.5px;
  color: #718096;
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
  border-top: 1px solid #f2f5f3;
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
  color: #1a202c;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
:deep(.dv-num) {
  flex: none;
  font-size: 11px;
  color: #a0aec0;
}
:deep(.dv-items),
:deep(.dv-addr) {
  font-size: 12px;
  color: #4a5568;
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
  color: #718096;
  max-width: 100%;
}
:deep(.dv-prep) {
  font-size: 12px;
  font-weight: 500;
  color: #718096;
}
:deep(.dv-prep.now) {
  color: #b45309;
}
:deep(.dv-late) {
  font-size: 12px;
  font-weight: 600;
  color: #c53030;
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
  color: #1a202c;
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
  background: #f0fff4;
  color: #2f855a;
}
.how :deep(svg) {
  width: 16px;
  height: 16px;
}
.how strong {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #1a202c;
}
.how small {
  font-size: 11.5px;
  color: #718096;
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
  color: #4a5568;
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
  color: #2d3748;
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
