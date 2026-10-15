<template>
  <ShopLayout section="orders" title="Reservations" subtitle="Scheduled flower orders by day. Close days you can't take orders, and keep an eye on how full each day is.">
    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="loadMonth">Try again</button>
    </div>

    <div class="rv-layout">
      <!-- Calendar -->
      <section class="sp-card rv-cal" aria-label="Calendar">
        <header class="rv-cal-head">
          <div>
            <h2>{{ monthTitle }}</h2>
            <p v-if="cal">{{ monthTotal }} {{ monthTotal === 1 ? "order" : "orders" }} booked · up to {{ cal.capacity }} a day</p>
          </div>
          <div class="rv-nav">
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="goToday">Today</button>
            <button type="button" class="sp-icon-btn" aria-label="Previous month" @click="shiftMonth(-1)"><span v-html="ICONS.chevronLeft"></span></button>
            <button type="button" class="sp-icon-btn" aria-label="Next month" @click="shiftMonth(1)"><span v-html="ICONS.chevronRight"></span></button>
          </div>
        </header>

        <div class="rv-week" aria-hidden="true">
          <span v-for="d in WEEKDAYS" :key="d">{{ d }}</span>
        </div>
        <div class="rv-grid" role="grid" :aria-busy="!cal">
          <span v-for="n in leadingBlanks" :key="`b${n}`" class="rv-blank"></span>
          <template v-if="cal">
            <button
              v-for="d in cal.days"
              :key="d.date"
              type="button"
              role="gridcell"
              class="rv-day"
              :class="dayClass(d)"
              :aria-selected="selected === d.date"
              :aria-label="dayAria(d)"
              @click="select(d.date)"
            >
              <span class="rv-num">{{ Number(d.date.slice(8)) }}</span>
              <span v-if="d.closed" class="rv-tag closed">Closed</span>
              <span v-else-if="d.weekly_closed" class="rv-tag off">Day off</span>
              <template v-else-if="d.total">
                <span class="rv-count">{{ d.total }}<small> {{ d.total === 1 ? "order" : "orders" }}</small></span>
                <span class="rv-bar"><span :class="loadTone(d)" :style="{ width: `${Math.min(100, (d.total / cal.capacity) * 100)}%` }"></span></span>
              </template>
            </button>
          </template>
          <template v-else>
            <span v-for="n in 30" :key="`s${n}`" class="bc-skeleton rv-day-sk"></span>
          </template>
        </div>
        <div class="rv-legend">
          <span><i class="lg-light"></i>Light</span>
          <span><i class="lg-busy"></i>Busy</span>
          <span><i class="lg-full"></i>Full</span>
          <span><i class="lg-closed"></i>Closed</span>
        </div>
      </section>

      <!-- Selected day -->
      <aside class="rv-side">
        <section class="sp-card" aria-live="polite">
          <div class="sp-card-head">
            <div>
              <h2>{{ dayLabel(selected, { long: true }) }}</h2>
              <p>{{ selectedSummary }}</p>
            </div>
          </div>

          <div v-if="selectedDay?.closed" class="closed-box">
            <span v-html="ICONS.lock"></span>
            <div>
              <strong>Closed for orders</strong>
              <span>{{ selectedDay.closed.reason || "No reason given" }}</span>
            </div>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busy" @click="reopen(selectedDay.closed.id)">Reopen</button>
          </div>
          <div v-else-if="canClose" class="close-row">
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="closing = true">
              <span v-html="ICONS.lock"></span>Close this day
            </button>
            <small v-if="selectedDay?.weekly_closed">This is already outside your business hours.</small>
          </div>

          <ul v-if="dayOrders === null" class="day-orders">
            <li v-for="n in 3" :key="n"><div class="bc-skeleton sp-thumb"></div><div class="grow"><div class="bc-skeleton sp-sk-line w70"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
          </ul>
          <div v-else-if="!dayOrders.length" class="sp-empty small">
            <span class="sp-empty-icon" v-html="ICONS.calendar"></span>
            <p>No orders booked for this day.</p>
          </div>
          <ul v-else class="day-orders">
            <li v-for="o in dayOrders" :key="o.id">
              <button type="button" @click="open(o)">
                <img v-if="o.first_item?.image" :src="o.first_item.image" :alt="o.first_item.name" class="sp-thumb" loading="lazy" />
                <span v-else class="sp-thumb blank" v-html="ICONS.flower"></span>
                <span class="do-text">
                  <strong>{{ o.customer }}</strong>
                  <span>{{ o.items_count }} × {{ o.item_names }}</span>
                </span>
                <span class="do-side">
                  <span class="sp-chip" :class="STATUS_TONE[o.status]">{{ o.status_label }}</span>
                  <small>{{ money(o.total) }}</small>
                </span>
              </button>
            </li>
          </ul>
        </section>

        <section class="sp-card" aria-label="Upcoming closed days">
          <div class="sp-card-head"><div><h2>Upcoming closed days</h2><p>Customers can't book these</p></div></div>
          <p v-if="!cal" class="sp-hint">Loading…</p>
          <p v-else-if="!cal.upcoming_closed.length" class="sp-hint">None. Close a day from the calendar.</p>
          <ul v-else class="closed-list">
            <li v-for="c in cal.upcoming_closed" :key="c.id">
              <span class="cl-date">{{ dayLabel(c.date) }}</span>
              <span class="cl-reason">{{ c.reason || TYPE_LABEL[c.type] || "Closed" }}</span>
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busy" @click="reopen(c.id)">Reopen</button>
            </li>
          </ul>
        </section>
      </aside>
    </div>

    <!-- Close a day -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="closing" class="sp-backdrop" @click="closing = false"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="closing" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="close-title" @submit.prevent="closeDay">
            <div class="sp-modal-head">
              <div>
                <h3 id="close-title">Close {{ dayLabel(selected, { long: true }) }}?</h3>
                <p>Customers won't be able to book deliveries for this day.</p>
              </div>
            </div>
            <div class="close-types">
              <button v-for="(label, key) in TYPE_LABEL" :key="key" type="button" class="sp-pick" :class="{ on: closeForm.type === key }" @click="closeForm.type = key">{{ label }}</button>
            </div>
            <label class="sp-field">
              <span>Reason <small>(optional, only you see it)</small></span>
              <input v-model.trim="closeForm.reason" class="sp-input" maxlength="255" placeholder="e.g. Family event, restocking day" />
            </label>
            <p v-if="selectedDay?.total" class="sp-banner warn tight">
              <span v-html="ICONS.alert"></span>{{ selectedDay.total }} {{ selectedDay.total === 1 ? "order is" : "orders are" }} already booked for this day and will still need delivering.
            </p>
            <p v-if="closeError" class="sp-error">{{ closeError }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="closing = false">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-dark" :disabled="busy">{{ busy ? "Saving…" : "Close day" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { STATUS_TONE, dayLabel, daysFromToday, money, todayKey } from "../../../components/vendor/orders/orderUi";
import "../../../components/vendor/shop/shop.css";

const router = useRouter();
const WEEKDAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
const TYPE_LABEL = { manual: "Day off", holiday: "Holiday", emergency: "Emergency" };

const month = ref(todayKey().slice(0, 7));
const selected = ref(todayKey());
const cal = ref(null);
const dayOrders = ref(null);
const error = ref("");
const busy = ref(false);
const closing = ref(false);
const closeForm = reactive({ type: "manual", reason: "" });
const closeError = ref("");
let daySeq = 0;

const monthTitle = computed(() =>
  new Date(`${month.value}-01T00:00:00`).toLocaleDateString("en-PH", { month: "long", year: "numeric" }),
);
const leadingBlanks = computed(() => (new Date(`${month.value}-01T00:00:00`).getDay() + 6) % 7);
const monthTotal = computed(() => (cal.value?.days ?? []).reduce((n, d) => n + d.total, 0));
const selectedDay = computed(() => cal.value?.days.find((d) => d.date === selected.value) ?? null);
const canClose = computed(() => {
  const n = daysFromToday(selected.value);
  return n !== null && n >= 0 && n <= 92;
});
const selectedSummary = computed(() => {
  const d = selectedDay.value;
  if (!d) return "";
  if (!d.total) return d.closed ? "Closed" : "No bookings yet";
  return `${d.total} of ${cal.value.capacity} slots booked · ${money(d.value)}`;
});

const loadTone = (d) => {
  const r = d.total / cal.value.capacity;
  return r >= 1 ? "full" : r >= 0.6 ? "busy" : "light";
};
const dayClass = (d) => ({
  today: d.date === todayKey(),
  past: daysFromToday(d.date) < 0,
  on: selected.value === d.date,
  closed: !!d.closed,
  off: !d.closed && d.weekly_closed,
  has: d.total > 0,
});
const dayAria = (d) =>
  `${dayLabel(d.date, { long: true })}: ${d.closed ? "closed" : `${d.total} ${d.total === 1 ? "order" : "orders"}`}`;

async function loadMonth() {
  error.value = "";
  try {
    const { data } = await api.get("vendor/fulfillment/calendar", { params: { month: month.value } });
    cal.value = data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load the calendar.");
  }
}
async function loadDay() {
  const mine = ++daySeq;
  dayOrders.value = null;
  try {
    const { data } = await api.get("vendor/fulfillment/orders", {
      params: { from: selected.value, to: selected.value, sort: "oldest", per_page: 50 },
    });
    if (mine === daySeq) dayOrders.value = data.data;
  } catch {
    if (mine === daySeq) dayOrders.value = [];
  }
}
function select(date) {
  selected.value = date;
  loadDay();
}
function shiftMonth(n) {
  const d = new Date(`${month.value}-01T00:00:00`);
  d.setMonth(d.getMonth() + n);
  month.value = d.toLocaleDateString("en-CA").slice(0, 7);
  cal.value = null;
  loadMonth();
}
function goToday() {
  const t = todayKey();
  if (month.value !== t.slice(0, 7)) {
    month.value = t.slice(0, 7);
    cal.value = null;
    loadMonth();
  }
  select(t);
}

async function closeDay() {
  if (busy.value) return;
  busy.value = true;
  closeError.value = "";
  try {
    await api.post("vendor/reservations/close-date", { date: selected.value, type: closeForm.type, reason: closeForm.reason || null });
    toast.success(`${dayLabel(selected.value)} is closed for bookings.`);
    closing.value = false;
    closeForm.reason = "";
    await loadMonth();
  } catch (e) {
    closeError.value = apiMessage(e, "Couldn't close this day.");
  } finally {
    busy.value = false;
  }
}
async function reopen(id) {
  if (busy.value) return;
  busy.value = true;
  try {
    await api.delete(`vendor/reservations/close-date/${id}`);
    toast.success("Day reopened for bookings.");
    await loadMonth();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't reopen this day."));
  } finally {
    busy.value = false;
  }
}

const open = (o) => router.push({ name: "VendorOrderDetails", query: { order: o.id } });

onMounted(() => {
  loadMonth();
  loadDay();
});
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
}
.rv-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 16px;
  align-items: start;
}
.rv-cal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 16px;
}
.rv-cal-head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: var(--v-text);
}
.rv-cal-head p {
  margin: 2px 0 0;
  font-size: 12.5px;
  color: var(--v-muted);
}
.rv-nav {
  display: flex;
  gap: 6px;
}
.rv-week,
.rv-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 6px;
}
.rv-week {
  margin-bottom: 6px;
}
.rv-week span {
  text-align: center;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-faint);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.rv-day,
.rv-day-sk {
  min-width: 0;
  min-height: 84px;
  border-radius: 13px;
}
.rv-day {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 8px;
  border: 1px solid var(--v-border);
  background: var(--v-surface);
  font-family: "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
  overflow: hidden;
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}
.rv-day:hover {
  border-color: var(--bc-accent-border);
}
.rv-day.past {
  background: var(--v-subtle);
}
.rv-day.past .rv-num {
  color: var(--v-faint);
}
.rv-day.today .rv-num {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.rv-day.on {
  border-color: var(--bc-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 18%, transparent);
}
.rv-day.closed {
  background: repeating-linear-gradient(135deg, var(--v-red-soft), var(--v-red-soft) 6px, var(--v-surface) 6px, var(--v-surface) 12px);
}
.rv-day.off {
  background: var(--v-subtle);
}
.rv-num {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
  line-height: 24px;
}
.rv-count {
  font-size: 15px;
  font-weight: 600;
  color: var(--v-text);
  line-height: 1.1;
  white-space: nowrap;
}
.rv-count small {
  margin-left: 3px;
  font-size: 10.5px;
  font-weight: 500;
  color: var(--v-muted);
}
.rv-bar {
  width: 100%;
  height: 4px;
  margin-top: auto;
  border-radius: 999px;
  background: var(--v-subtle-2);
  overflow: hidden;
}
.rv-bar span {
  display: block;
  height: 100%;
}
.rv-bar .light { background: #68d391; }
.rv-bar .busy { background: #f6ad55; }
.rv-bar .full { background: #fc8181; }
.rv-tag {
  max-width: 100%;
  padding: 2px 7px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rv-tag.closed {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.rv-tag.off {
  background: var(--v-subtle-2);
  color: var(--v-muted);
}
.rv-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  margin-top: 14px;
  font-size: 11.5px;
  color: var(--v-muted);
}
.rv-legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.rv-legend i {
  width: 14px;
  height: 6px;
  border-radius: 999px;
}
.lg-light { background: #68d391; }
.lg-busy { background: #f6ad55; }
.lg-full { background: #fc8181; }
.lg-closed { background: repeating-linear-gradient(135deg, #feb2b2, #feb2b2 2px, var(--v-surface) 2px, var(--v-surface) 4px); }

/* Side */
.rv-side {
  position: sticky;
  top: 16px;
  display: grid;
  gap: 14px;
}
.closed-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  margin-bottom: 12px;
  border-radius: 13px;
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.closed-box > span {
  display: inline-flex;
}
.closed-box :deep(svg) {
  width: 18px;
  height: 18px;
}
.closed-box div {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.closed-box strong {
  font-size: 13px;
}
.closed-box div span {
  font-size: 12px;
  color: var(--v-red-text);
  overflow-wrap: anywhere;
}
.close-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.close-row small {
  font-size: 11.5px;
  color: var(--v-faint);
}
.day-orders,
.closed-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.day-orders li {
  display: flex;
  align-items: center;
  gap: 10px;
  border-top: 1px solid var(--v-border);
}
.day-orders li > button {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 2px;
  border: 0;
  background: none;
  font-family: "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
}
.day-orders li > button:hover strong {
  color: var(--bc-accent-strong);
}
.do-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.do-text strong {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.do-text span {
  font-size: 12px;
  color: var(--v-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.do-side {
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
}
.do-side small {
  font-size: 11.5px;
  color: var(--v-text-2);
}
.sp-empty.small {
  padding: 22px 10px;
}
.closed-list li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 0;
  border-top: 1px solid var(--v-border);
}
.cl-date {
  flex: none;
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.cl-reason {
  flex: 1;
  min-width: 0;
  font-size: 12px;
  color: var(--v-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.close-types {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}
.tight {
  margin: 14px 0 0;
}
@media (max-width: 1100px) {
  .rv-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .rv-side {
    position: static;
  }
}
@media (max-width: 640px) {
  .rv-week,
  .rv-grid {
    gap: 3px;
  }
  .rv-day,
  .rv-day-sk {
    min-height: 58px;
    padding: 5px;
    border-radius: 9px;
  }
  .rv-count small,
  .rv-tag {
    display: none;
  }
  .rv-count {
    font-size: 13px;
  }
  .rv-day.closed .rv-num {
    color: var(--v-red-text);
  }
}
</style>
