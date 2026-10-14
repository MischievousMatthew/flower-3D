<template>
  <ShopLayout section="account" title="Shop Settings" subtitle="How you take orders: delivery, business hours, days off and payment preferences. Checkout follows these right away.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="!dirty || saving" @click="reset">Discard</button>
      <button type="submit" form="settings-form" class="sp-btn sp-btn-primary" :disabled="!form || !dirty || saving">
        <span v-html="ICONS.check"></span>{{ saving ? "Saving…" : "Save changes" }}
      </button>
    </template>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="load">Try again</button>
    </div>

    <form id="settings-form" class="ss-form" novalidate @submit.prevent="save">
      <template v-if="!form && !loadError">
        <div v-for="n in 4" :key="n" class="sp-card"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-block"></div></div>
      </template>

      <template v-else-if="form">
        <!-- Taking orders -->
        <section class="sp-card ss-status" :class="{ paused: !form.preferences.accepting_orders }">
          <label class="ss-status-row">
            <span class="sp-stat-icon" :class="form.preferences.accepting_orders ? 'tone-green' : 'tone-amber'" v-html="form.preferences.accepting_orders ? ICONS.play : ICONS.pause"></span>
            <span class="ss-status-text">
              <b>{{ form.preferences.accepting_orders ? "Taking new orders" : "New orders paused" }}</b>
              <small>{{ form.preferences.accepting_orders ? "Customers can check out from your shop." : "Customers can still browse, but checkout is closed until you turn this back on." }}</small>
            </span>
            <span class="sp-switch"><input v-model="form.preferences.accepting_orders" type="checkbox" aria-label="Taking new orders" /><span></span></span>
          </label>
          <div v-if="!form.preferences.accepting_orders" class="sp-grid-2 ss-away">
            <label class="sp-field sp-span-all">
              <span>Message for customers</span>
              <input v-model="form.preferences.away_message" class="sp-input" maxlength="200" placeholder="e.g. We're on a short break and back soon. Thank you!" />
              <small>Shown when someone tries to check out. {{ (form.preferences.away_message || "").length }}/200</small>
            </label>
            <label class="sp-field">
              <span>Back on (optional)</span>
              <input v-model="form.preferences.back_on" type="date" class="sp-input" :min="tomorrow" :class="{ invalid: errors['preferences.back_on'] }" />
              <small v-if="errors['preferences.back_on']" class="sp-error">{{ errors["preferences.back_on"] }}</small>
              <small v-else>Orders reopen by themselves on this day.</small>
            </label>
          </div>
          <p v-if="!form.preferences.accepting_orders" class="sp-hint ss-note">Orders you already have aren't affected.</p>
        </section>

        <div class="ac-cols">
          <!-- Delivery -->
          <section class="sp-card">
            <div class="sp-card-head"><div><h2>Delivery options</h2><p>You deliver every order yourself. Fees are worked out per order at checkout.</p></div></div>
            <div class="ss-rows">
              <label class="ss-row">
                <span class="ss-row-text"><b>Same-day delivery</b><small>{{ sameDayHint }}</small></span>
                <span class="sp-switch"><input v-model="form.same_day_delivery" type="checkbox" aria-label="Same-day delivery" /><span></span></span>
              </label>
            </div>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span>Preparation time</span>
                <select v-model.number="form.lead_time_days" class="sp-select">
                  <option :value="0">Ready the same day</option>
                  <option v-for="d in leadDays" :key="d" :value="d">{{ d }} day{{ d === 1 ? "" : "s" }}</option>
                </select>
                <small>Earliest date customers can pick: {{ earliestText }}</small>
              </label>
              <label class="sp-field">
                <span>Orders per day</span>
                <input v-model.number="form.max_orders_per_day" type="number" min="1" max="500" class="sp-input" :class="{ invalid: errors.max_orders_per_day }" />
                <small v-if="errors.max_orders_per_day" class="sp-error">{{ errors.max_orders_per_day }}</small>
                <small v-else>A day is shown as fully booked after this many.</small>
              </label>
              <label class="sp-field sp-span-all">
                <span>Areas you deliver to</span>
                <input v-model.trim="form.service_areas" class="sp-input" maxlength="500" placeholder="e.g. Imus, Bacoor, Dasmariñas" />
              </label>
              <label class="sp-field sp-span-all">
                <span>Cancellation &amp; refund policy</span>
                <textarea v-model="form.cancellation_policy" class="sp-textarea" rows="4" maxlength="1000" :class="{ invalid: errors.cancellation_policy }" placeholder="e.g. Free cancellation up to 24 hours before delivery. Custom bouquets can't be refunded once prepared."></textarea>
                <small v-if="errors.cancellation_policy" class="sp-error">{{ errors.cancellation_policy }}</small>
                <small v-else>{{ (form.cancellation_policy || "").length }}/1000 · Customers see this before they pay.</small>
              </label>
            </div>
          </section>

          <div class="ss-side">
            <!-- Payments -->
            <section class="sp-card">
              <div class="sp-card-head"><div><h2>Shop preferences</h2><p>How customers can pay you.</p></div></div>
              <div class="ss-rows">
                <div class="ss-row">
                  <span class="ss-row-text"><b>E-wallet (GCash, Maya)</b><small>{{ settings.payment_details_completed ? "On. Paid online through PayMongo." : "Add your payout details to your profile to turn this on." }}</small></span>
                  <span class="sp-chip" :class="settings.payment_details_completed ? 'green' : 'slate'">{{ settings.payment_details_completed ? "On" : "Off" }}</span>
                </div>
                <label class="ss-row" :class="{ disabled: !settings.payment_details_completed && form.preferences.accept_cod }">
                  <span class="ss-row-text"><b>Cash on delivery</b><small>Customers pay your rider in cash.</small></span>
                  <span class="sp-switch"><input v-model="form.preferences.accept_cod" type="checkbox" aria-label="Cash on delivery" :disabled="!settings.payment_details_completed && form.preferences.accept_cod" /><span></span></span>
                </label>
                <small v-if="errors['preferences.accept_cod']" class="sp-error">{{ errors["preferences.accept_cod"] }}</small>
                <small v-else-if="!settings.payment_details_completed" class="sp-hint">Cash on delivery stays on until e-wallet payments are set up, so customers always have a way to pay.</small>
              </div>
            </section>

            <!-- Days off -->
            <section class="sp-card">
              <div class="sp-card-head"><div><h2>Days off</h2><p>Holidays and days you're away. Customers can't book these.</p></div></div>
              <div class="ss-closed-add">
                <input v-model="newClosed.date" type="date" class="sp-input" :min="settings.today" :max="maxClosed" aria-label="Date" />
                <input v-model.trim="newClosed.reason" class="sp-input" maxlength="255" placeholder="Reason (optional)" aria-label="Reason" />
                <button type="button" class="sp-btn sp-btn-dark sp-btn-sm" :disabled="!newClosed.date || closedBusy" @click="addClosed"><span v-html="ICONS.plus"></span>Add</button>
              </div>
              <p v-if="closedError" class="sp-hint">{{ closedError }}</p>
              <div v-else-if="closed === null" class="bc-skeleton sp-sk-line"></div>
              <p v-else-if="!closed.length" class="sp-hint">No days off coming up.</p>
              <ul v-else class="ss-closed">
                <li v-for="c in closed" :key="c.id">
                  <span class="ss-closed-date"><b>{{ dayName(c.closed_date) }}</b><small>{{ longDate(c.closed_date) }}</small></span>
                  <span class="ss-closed-reason">{{ c.reason || "Closed" }}</span>
                  <button type="button" class="sp-icon-btn" :aria-label="`Reopen ${longDate(c.closed_date)}`" :disabled="closedBusy" @click="removeClosed(c)"><span v-html="ICONS.close"></span></button>
                </li>
              </ul>
            </section>
          </div>
        </div>

        <!-- Hours -->
        <section class="sp-card">
          <div class="sp-card-head">
            <div><h2>Business hours</h2><p>Shown on your storefront. Same-day orders close at your closing time.</p></div>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="copyFirst">Copy first open day to all</button>
          </div>
          <ul class="ss-hours">
            <li v-for="d in week" :key="d.day" :class="{ closed: !d.open }">
              <label class="ss-hours-day">
                <span class="sp-switch"><input v-model="d.open" type="checkbox" :aria-label="`Open on ${d.day}`" /><span></span></span>
                <strong>{{ d.day }}</strong>
              </label>
              <template v-if="d.open">
                <input v-model="d.opening_time" type="time" class="sp-input ss-time" :aria-label="`${d.day} opening time`" required />
                <span class="ss-to">to</span>
                <input v-model="d.closing_time" type="time" class="sp-input ss-time" :class="{ invalid: d.opening_time >= d.closing_time }" :aria-label="`${d.day} closing time`" required />
              </template>
              <span v-else class="ss-closed-text">Closed</span>
            </li>
          </ul>
          <p v-if="errors.operating_schedules" class="sp-error">{{ errors.operating_schedules }}</p>
          <p v-else-if="hoursSummary" class="sp-hint ss-note">{{ hoursSummary }}</p>
        </section>
      </template>
    </form>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { onBeforeRouteLeave } from "vue-router";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { hoursText, schedulesFrom, to12, weekFrom, weekFromText } from "../../../components/vendor/shop/businessHours";
import { longDate } from "../../../components/vendor/account/accountUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/account/account.css";

const LEAD_DAYS = [1, 2, 3, 4, 5, 7, 10, 14];
const FIELDS = ["same_day_delivery", "lead_time_days", "max_orders_per_day", "service_areas", "cancellation_policy"];

const settings = ref(null);
const form = ref(null);
const week = ref([]);
const loadError = ref("");
const saving = ref(false);
const errors = reactive({});
let snapshot = "";

const closed = ref(null);
const closedError = ref("");
const closedBusy = ref(false);
const newClosed = reactive({ date: "", reason: "" });

const state = () => JSON.stringify([form.value, week.value]);
const dirty = computed(() => !!form.value && state() !== snapshot);

const addDays = (iso, n) => {
  const d = new Date(`${iso}T00:00:00`);
  d.setDate(d.getDate() + n);
  return d.toISOString().slice(0, 10);
};
const tomorrow = computed(() => (settings.value ? addDays(settings.value.today, 1) : ""));
const maxClosed = computed(() => (settings.value ? addDays(settings.value.today, 90) : ""));
const dayName = (iso) => new Date(`${String(iso).slice(0, 10)}T00:00:00`).toLocaleDateString("en-PH", { weekday: "short" });

// Mirrors checkout: same day if allowed, otherwise tomorrow plus the preparation days
const earliestText = computed(() => {
  const f = form.value;
  if (f.same_day_delivery) return "today, until closing time";
  const days = (Number(f.lead_time_days) || 0) + 1;
  return days === 1 ? "tomorrow" : `${days} days from today`;
});
const sameDayHint = computed(() => {
  const today = week.value[(new Date(new Date().toLocaleString("en-US", { timeZone: "Asia/Manila" })).getDay() + 6) % 7];
  if (!form.value.same_day_delivery) return "Off. The earliest delivery is tomorrow.";
  return today?.open ? `Customers can order for today until ${to12(today.closing_time)}.` : "Customers can order for today on days you're open, until closing time.";
});
// The saved value is always offered, even if it isn't one of the usual choices
const leadDays = computed(() => [...new Set([...LEAD_DAYS, form.value.lead_time_days].filter((d) => d > 0))].sort((a, b) => a - b));
const hoursSummary = computed(() => hoursText(week.value));

function apply(data) {
  settings.value = data;
  form.value = {
    ...Object.fromEntries(FIELDS.map((k) => [k, data[k] ?? ""])),
    preferences: { ...data.preferences, away_message: data.preferences.away_message ?? "", back_on: data.preferences.back_on ?? "" },
  };
  week.value = data.operating_schedules?.length ? weekFrom(data.operating_schedules) : weekFromText(data.operating_hours);
  snapshot = state();
}

async function load() {
  loadError.value = "";
  try {
    const { data } = await api.get("vendor/account/shop-settings");
    apply(data.data);
  } catch (e) {
    loadError.value = apiMessage(e, "Couldn't load your shop settings.");
  }
}
function reset() {
  if (settings.value) apply(settings.value);
  Object.keys(errors).forEach((k) => delete errors[k]);
}

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  const f = form.value;
  if (!(f.max_orders_per_day >= 1 && f.max_orders_per_day <= 500)) errors.max_orders_per_day = "Use a number from 1 to 500.";
  if (!String(f.cancellation_policy || "").trim()) errors.cancellation_policy = "Tell customers your cancellation and refund policy.";
  if (!week.value.some((d) => d.open)) errors.operating_schedules = "Open on at least one day.";
  else if (week.value.some((d) => d.open && d.opening_time >= d.closing_time)) errors.operating_schedules = "Closing time must be after opening time.";
  if (!f.preferences.accepting_orders && f.preferences.back_on && f.preferences.back_on <= settings.value.today) errors["preferences.back_on"] = "Pick a date after today.";
  return !Object.keys(errors).length;
}

async function save() {
  if (saving.value || !validate()) {
    if (Object.keys(errors).length) toast.error(Object.values(errors)[0]);
    return;
  }
  saving.value = true;
  try {
    const p = form.value.preferences;
    const { data } = await api.put("vendor/account/shop-settings", {
      ...form.value,
      service_areas: form.value.service_areas || null,
      operating_schedules: schedulesFrom(week.value),
      preferences: { ...p, away_message: p.away_message || null, back_on: p.back_on || null },
    });
    apply(data.data);
    toast.success(data.message);
  } catch (e) {
    for (const [key, list] of Object.entries(e?.response?.data?.errors ?? {})) {
      errors[key.startsWith("operating_schedules") ? "operating_schedules" : key] = [].concat(list)[0];
    }
    toast.error(apiMessage(e, "Couldn't save your settings."));
  } finally {
    saving.value = false;
  }
}

function copyFirst() {
  const first = week.value.find((d) => d.open);
  if (!first) return;
  week.value.forEach((d) => {
    d.open = true;
    d.opening_time = first.opening_time;
    d.closing_time = first.closing_time;
  });
}

// Days off save straight away (they have their own endpoints)
async function loadClosed() {
  closedError.value = "";
  try {
    const { data } = await api.get("vendor/reservations/closed-dates");
    closed.value = data.data ?? [];
  } catch (e) {
    closed.value = [];
    closedError.value = e?.response?.status === 403 ? "Days off need an active plan." : apiMessage(e, "Couldn't load your days off.");
  }
}
async function addClosed() {
  closedBusy.value = true;
  try {
    await api.post("vendor/reservations/close-date", { date: newClosed.date, reason: newClosed.reason || null, type: "manual" });
    toast.success(`${longDate(newClosed.date)} is now a day off.`);
    newClosed.date = "";
    newClosed.reason = "";
    await loadClosed();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't add that day."));
  } finally {
    closedBusy.value = false;
  }
}
async function removeClosed(c) {
  closedBusy.value = true;
  try {
    await api.delete(`vendor/reservations/close-date/${c.id}`);
    closed.value = closed.value.filter((x) => x.id !== c.id);
    toast.success(`${longDate(c.closed_date)} is open again.`);
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't reopen that day."));
  } finally {
    closedBusy.value = false;
  }
}

onBeforeRouteLeave(() => {
  if (dirty.value && !saving.value) return window.confirm("You have unsaved shop settings. Leave without saving?");
  return true;
});

onMounted(() => {
  load();
  loadClosed();
});
</script>

<style scoped>
.ss-form {
  display: grid;
  gap: 16px;
}
.ss-status {
  border-color: #c6f6d5;
}
.ss-status.paused {
  border-color: #fbd38d;
  background: #fffdf7;
}
.ss-status-row {
  display: flex;
  align-items: center;
  gap: 14px;
  cursor: pointer;
}
.ss-status-text {
  flex: 1;
  min-width: 0;
}
.ss-status-text b {
  display: block;
  font-size: 15px;
  font-weight: 600;
}
.ss-status-text small {
  font-size: 12.5px;
  color: #718096;
}
.ss-away {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid #f6e7c8;
}
.ss-note {
  margin: 10px 0 0;
}
.ss-side {
  display: grid;
  gap: 16px;
}
.ss-rows {
  display: grid;
  margin-bottom: 14px;
}
.ss-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  cursor: pointer;
}
.ss-row + .ss-row {
  border-top: 1px solid #f4f6f8;
}
.ss-row.disabled {
  cursor: not-allowed;
  opacity: 0.7;
}
.ss-row-text {
  flex: 1;
  min-width: 0;
}
.ss-row-text b {
  display: block;
  font-size: 13px;
  font-weight: 600;
}
.ss-row-text small {
  font-size: 12px;
  color: #718096;
}
.ss-closed-add {
  display: grid;
  grid-template-columns: 150px minmax(0, 1fr) auto;
  gap: 8px;
  margin-bottom: 12px;
}
.ss-closed {
  margin: 0;
  padding: 0;
  list-style: none;
}
.ss-closed li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
  border-top: 1px solid #f4f6f8;
}
.ss-closed-date {
  display: grid;
  width: 120px;
  flex: none;
}
.ss-closed-date b {
  font-size: 12px;
  font-weight: 600;
  color: #a0aec0;
  text-transform: uppercase;
}
.ss-closed-date small {
  font-size: 13px;
  color: #1a202c;
}
.ss-closed-reason {
  flex: 1;
  min-width: 0;
  font-size: 13px;
  color: #4a5568;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ss-hours {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 14px;
}
.ss-hours li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 12px;
  background: #fbfdfc;
}
.ss-hours li.closed {
  background: #f8f9fa;
}
.ss-hours-day {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 140px;
  cursor: pointer;
}
.ss-hours-day strong {
  font-size: 13px;
  font-weight: 500;
  color: #1a202c;
}
.ss-time {
  width: 120px;
  padding: 7px 10px;
}
.ss-to {
  font-size: 12px;
  color: #a0aec0;
}
.ss-closed-text {
  font-size: 12.5px;
  color: #a0aec0;
}
@media (max-width: 1100px) {
  .ss-hours {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 560px) {
  .ss-hours li {
    flex-wrap: wrap;
  }
  .ss-hours-day {
    width: 100%;
  }
  .ss-time {
    flex: 1;
    width: auto;
  }
  .ss-closed-add {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
