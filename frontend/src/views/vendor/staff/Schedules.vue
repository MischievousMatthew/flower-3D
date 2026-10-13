<template>
  <ShopLayout section="staff" title="Schedules" subtitle="Who works when. Everyone starts from their usual hours; change any day, give a day off, or repeat last week.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="!data || copying" @click="copyWeek"><span v-html="ICONS.history"></span>{{ copying ? "Copying…" : "Repeat last week" }}</button>
    </template>

    <div v-if="settingUp" class="sp-banner info"><span v-html="ICONS.clock"></span>{{ settingUp }}</div>
    <div v-else-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <section v-if="!settingUp" class="sp-card">
      <div class="sc-head">
        <div class="sc-nav">
          <button type="button" class="sp-icon-btn" aria-label="Previous week" @click="shift(-7)"><span v-html="ICONS.left"></span></button>
          <div class="sc-week">
            <strong>{{ weekLabel }}</strong>
            <span>{{ isThisWeek ? "This week" : weekOffsetText }}</span>
          </div>
          <button type="button" class="sp-icon-btn" aria-label="Next week" @click="shift(7)"><span v-html="ICONS.right"></span></button>
          <button v-if="!isThisWeek" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="goThisWeek">This week</button>
        </div>
        <div class="sc-legend">
          <span><i class="usual"></i>Usual hours</span>
          <span><i class="custom"></i>Changed</span>
          <span><i class="off"></i>Day off</span>
          <b v-if="data">{{ hoursText(data.total_hours) }} scheduled</b>
        </div>
      </div>

      <div v-if="!data" class="sc-sk">
        <div v-for="n in 4" :key="n" class="bc-skeleton sp-sk-block"></div>
      </div>
      <div v-else-if="!data.people.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.calendar"></span>
        <h3>Nobody to schedule</h3>
        <p>Schedules are kept for people with an HR profile. Your HR staff add profiles, with usual hours and rest days, in the ERP.</p>
      </div>
      <div v-else class="sc-scroll">
        <table class="sc-grid">
          <thead>
            <tr>
              <th class="sc-person-col">Person</th>
              <th v-for="d in data.days" :key="d" :class="{ today: d === today }">
                <span>{{ weekday(d) }}</span><b>{{ dayNum(d) }}</b>
              </th>
              <th class="sc-total">Hours</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in data.people" :key="p.id">
              <th class="sc-person-col" scope="row">
                <div class="st-person">
                  <span class="st-avatar sm" :style="{ background: p.role?.color || personColor(p.name) }">
                    <img v-if="p.avatar" :src="p.avatar" alt="" />
                    <template v-else>{{ p.initials }}</template>
                  </span>
                  <div>
                    <strong>{{ p.name }}</strong>
                    <small>{{ p.role?.name || p.position || "No role" }}</small>
                  </div>
                </div>
              </th>
              <td v-for="c in p.days" :key="c.date" :class="{ today: c.date === today }">
                <button
                  type="button"
                  class="sc-cell"
                  :class="[c.off ? 'off' : c.source, { past: c.date < today }]"
                  :aria-label="`${p.name}, ${longDay(c.date)}: ${c.off ? 'day off' : range12(`${c.start}–${c.end}`)}. Change`"
                  @click="openCell(p, c)"
                >
                  <template v-if="c.off">Off</template>
                  <template v-else>
                    <span class="t">{{ short(c.start) }}–{{ short(c.end) }}</span>
                    <span v-if="c.label" class="l">{{ c.label }}</span>
                  </template>
                </button>
              </td>
              <td class="sc-total">{{ hoursText(p.week_hours) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Edit a day -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="form" class="sp-backdrop" @click="closeForm"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="form" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="sc-title" novalidate @submit.prevent="save">
            <div class="sp-modal-head">
              <div>
                <h3 id="sc-title">{{ form.person.name }}</h3>
                <p>{{ longDay(form.date) }} · usually {{ usualText(form.person, form.date) }}</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeForm"><span v-html="ICONS.close"></span></button>
            </div>

            <div class="sp-seg sc-mode" role="radiogroup" aria-label="This day">
              <button type="button" role="radio" :class="{ on: form.mode === 'hours' }" :aria-checked="form.mode === 'hours'" @click="form.mode = 'hours'">Working</button>
              <button type="button" role="radio" :class="{ on: form.mode === 'off' }" :aria-checked="form.mode === 'off'" @click="form.mode = 'off'">Day off</button>
            </div>

            <div v-if="form.mode === 'hours'" class="sp-grid-2 sc-gap">
              <label class="sp-field">
                <span>Starts</span>
                <input v-model="form.start" type="time" class="sp-input" :class="{ invalid: errors.start_time }" />
                <small v-if="errors.start_time" class="sp-error">{{ errors.start_time }}</small>
              </label>
              <label class="sp-field">
                <span>Ends</span>
                <input v-model="form.end" type="time" class="sp-input" :class="{ invalid: errors.end_time }" />
                <small v-if="errors.end_time" class="sp-error">{{ errors.end_time }}</small>
              </label>
              <label class="sp-field sp-span-all">
                <span>Label <small>(optional)</small></span>
                <input v-model="form.label" class="sp-input" maxlength="40" placeholder="e.g. Market run, Opening" />
              </label>
            </div>

            <div class="sp-field sc-gap">
              <span class="sp-label">Also apply to</span>
              <div class="sc-days">
                <label v-for="d in data.days" :key="d" class="sc-day" :class="{ on: form.dates.includes(d), self: d === form.date }">
                  <input v-model="form.dates" type="checkbox" :value="d" :disabled="d === form.date" />
                  <span>{{ weekday(d) }}</span>
                </label>
              </div>
            </div>
            <p v-if="formError" class="sp-error">{{ formError }}</p>

            <div class="sp-modal-foot sc-foot">
              <button v-if="form.shiftId" type="button" class="sp-btn sp-btn-ghost" :disabled="saving" @click="reset">Back to usual hours</button>
              <span class="grow"></span>
              <button type="button" class="sp-btn sp-btn-ghost" @click="closeForm">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="saving">{{ saving ? "Saving…" : "Save" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { ymd } from "../../../components/vendor/finance/financeUi";
import { hoursText, personColor, range12 } from "../../../components/vendor/staff/staffUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";

const today = ymd(new Date());
const mondayOf = (date) => {
  const d = new Date(`${date}T00:00:00`);
  d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
  return ymd(d);
};

const data = ref(null);
const error = ref("");
const settingUp = ref("");
const week = ref(mondayOf(today));
const copying = ref(false);
const form = ref(null);
const errors = reactive({});
const formError = ref("");
const saving = ref(false);

const isThisWeek = computed(() => week.value === mondayOf(today));
const weekLabel = computed(() => {
  const s = new Date(`${week.value}T00:00:00`);
  const e = new Date(s);
  e.setDate(s.getDate() + 6);
  const opts = { month: "short", day: "numeric" };
  return `${s.toLocaleDateString("en-PH", opts)} – ${e.toLocaleDateString("en-PH", { ...opts, year: "numeric" })}`;
});
const weekOffsetText = computed(() => {
  const diff = Math.round((new Date(`${week.value}T00:00:00`) - new Date(`${mondayOf(today)}T00:00:00`)) / (7 * 86400000));
  if (diff === 1) return "Next week";
  if (diff === -1) return "Last week";
  return diff > 0 ? `In ${diff} weeks` : `${-diff} weeks ago`;
});

const weekday = (d) => new Date(`${d}T00:00:00`).toLocaleDateString("en-PH", { weekday: "short" });
const dayNum = (d) => new Date(`${d}T00:00:00`).getDate();
const longDay = (d) => new Date(`${d}T00:00:00`).toLocaleDateString("en-PH", { weekday: "long", month: "short", day: "numeric" });
/** "08:00" → "8", "13:30" → "1:30p" — compact for the grid */
function short(hm) {
  const [h, m] = hm.split(":").map(Number);
  const h12 = ((h + 11) % 12) + 1;
  return `${h12}${m ? `:${String(m).padStart(2, "0")}` : ""}${h < 12 ? "a" : "p"}`;
}
function usualText(person, date) {
  const day = new Date(`${date}T00:00:00`).toLocaleDateString("en-US", { weekday: "long" });
  if (!person.usual_hours) return "no set hours";
  return person.rest_days.includes(day) ? "a rest day" : range12(person.usual_hours);
}

function shift(days) {
  const d = new Date(`${week.value}T00:00:00`);
  d.setDate(d.getDate() + days);
  week.value = ymd(d);
  load();
}
function goThisWeek() {
  week.value = mondayOf(today);
  load();
}

async function load() {
  error.value = "";
  try {
    const res = await api.get("vendor/staff/schedules", { params: { week: week.value } });
    data.value = res.data.data;
  } catch (e) {
    if (e?.response?.data?.code === "staff_setting_up") settingUp.value = e.response.data.message;
    else error.value = apiMessage(e, "Couldn't load schedules.");
  }
}

function openCell(person, cell) {
  Object.keys(errors).forEach((k) => delete errors[k]);
  formError.value = "";
  const [us, ue] = (person.usual_hours ?? "08:00–17:00").split("–");
  form.value = {
    person,
    date: cell.date,
    shiftId: cell.shift_id,
    mode: cell.off ? "off" : "hours",
    start: cell.start ?? us,
    end: cell.end ?? ue,
    label: cell.label ?? "",
    dates: [cell.date],
  };
}
function closeForm() {
  if (!saving.value) form.value = null;
}

async function save() {
  if (saving.value) return;
  Object.keys(errors).forEach((k) => delete errors[k]);
  formError.value = "";
  const f = form.value;
  const off = f.mode === "off";
  if (!off) {
    if (!f.start) errors.start_time = "Enter a start time.";
    if (!f.end) errors.end_time = "Enter an end time.";
    else if (f.start && f.end <= f.start) errors.end_time = "It must end after it starts.";
    if (Object.keys(errors).length) return;
  }
  saving.value = true;
  try {
    const { data: res } = await api.post("vendor/staff/shifts", {
      employee_id: f.person.id,
      dates: f.dates,
      is_off: off,
      start_time: off ? null : f.start,
      end_time: off ? null : f.end,
      label: off ? null : f.label.trim() || null,
    });
    toast.success(res.message);
    form.value = null;
    await load();
  } catch (e) {
    const fieldErrors = e?.response?.data?.errors ?? {};
    for (const [key, list] of Object.entries(fieldErrors)) errors[key] = [].concat(list)[0];
    formError.value = Object.keys(fieldErrors).length && (errors.start_time || errors.end_time) ? "" : apiMessage(e, "Couldn't save the schedule.");
  } finally {
    saving.value = false;
  }
}

async function reset() {
  saving.value = true;
  try {
    const { data: res } = await api.delete(`vendor/staff/shifts/${form.value.shiftId}`);
    toast.success(res.message);
    form.value = null;
    await load();
  } catch (e) {
    formError.value = apiMessage(e, "Couldn't change it back.");
  } finally {
    saving.value = false;
  }
}

async function copyWeek() {
  copying.value = true;
  try {
    const { data: res } = await api.post("vendor/staff/shifts/copy-week", { week: week.value });
    toast.success(res.message);
    await load();
  } catch (e) {
    toast.info(apiMessage(e, "Couldn't copy last week."));
  } finally {
    copying.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.sc-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 16px;
}
.sc-nav {
  display: flex;
  align-items: center;
  gap: 10px;
}
.sc-week {
  min-width: 170px;
  text-align: center;
}
.sc-week strong {
  display: block;
  font-size: 14.5px;
  font-weight: 600;
}
.sc-week span {
  font-size: 12px;
  color: #718096;
}
.sc-legend {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 12px;
  color: #718096;
}
.sc-legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sc-legend i {
  width: 12px;
  height: 12px;
  border-radius: 4px;
}
.sc-legend i.usual { background: #f7fafc; border: 1px solid #e2e8f0; }
.sc-legend i.custom { background: #f0fff4; border: 1px solid #68d391; }
.sc-legend i.off { background: repeating-linear-gradient(45deg, #f1f5f9, #f1f5f9 3px, #fff 3px, #fff 6px); border: 1px solid #e2e8f0; }
.sc-legend b {
  color: #2d3748;
  font-weight: 600;
}
.sc-sk {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.sc-scroll {
  overflow-x: auto;
  margin: 0 -18px;
  padding: 0 18px 4px;
}
.sc-grid {
  width: 100%;
  min-width: 860px;
  border-collapse: separate;
  border-spacing: 0;
  table-layout: fixed;
}
.sc-grid th,
.sc-grid td {
  padding: 4px;
}
.sc-grid thead th {
  padding-bottom: 10px;
  font-weight: 500;
  text-align: center;
}
.sc-grid thead th span {
  display: block;
  font-size: 11.5px;
  color: #a0aec0;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.sc-grid thead th b {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  margin-top: 2px;
  border-radius: 50%;
  font-size: 13.5px;
  font-weight: 600;
  color: #2d3748;
}
.sc-grid thead th.today b {
  background: #48bb78;
  color: #fff;
}
.sc-person-col {
  width: 210px;
  text-align: left !important;
}
.sc-grid tbody th {
  padding-right: 12px;
  font-weight: 400;
}
.sc-grid tbody tr + tr > * {
  border-top: 1px solid #f4f6f8;
}
.sc-grid td.today {
  background: #f8fdf9;
}
.sc-total {
  width: 74px;
  text-align: right !important;
  font-size: 13px;
  font-weight: 600;
  color: #2d3748;
  font-variant-numeric: tabular-nums;
}
.sc-grid thead .sc-total {
  font-size: 11.5px;
  font-weight: 500;
  color: #a0aec0;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  vertical-align: top;
}
.sc-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1px;
  width: 100%;
  min-height: 46px;
  padding: 4px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f7fafc;
  font-family: inherit;
  font-size: 12px;
  color: #2d3748;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.sc-cell:hover {
  border-color: #48bb78;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.15);
}
.sc-cell .t {
  font-weight: 600;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.sc-cell .l {
  max-width: 100%;
  font-size: 10.5px;
  color: #2f855a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sc-cell.custom {
  border-color: #68d391;
  background: #f0fff4;
}
.sc-cell.off {
  background: repeating-linear-gradient(45deg, #f7fafc, #f7fafc 4px, #fff 4px, #fff 8px);
  color: #a0aec0;
  font-weight: 500;
}
.sc-cell.past {
  opacity: 0.7;
}
.sc-mode {
  margin-bottom: 4px;
}
.sc-gap {
  margin-top: 14px;
}
.sc-days {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.sc-day {
  display: inline-flex;
  align-items: center;
  padding: 6px 11px;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  font-size: 12.5px;
  color: #4a5568;
  cursor: pointer;
}
.sc-day input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.sc-day.on {
  border-color: #48bb78;
  background: #f0fff4;
  color: #22543d;
}
.sc-day.self {
  cursor: default;
  font-weight: 600;
}
.sc-day:has(input:focus-visible) {
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.3);
}
.sc-foot {
  flex-wrap: wrap;
}
.sc-foot .grow {
  flex: 1;
}
</style>
