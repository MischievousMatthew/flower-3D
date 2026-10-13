<template>
  <ShopLayout section="staff" title="Attendance" subtitle="Time-in and time-out records from the ERP scanner. Late and missing time-outs are flagged against each person's schedule.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-primary" :disabled="!data" @click="openForm()"><span v-html="ICONS.plus"></span>Add record</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Today -->
    <section class="sp-stats" aria-label="Attendance summary">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <div class="sp-stat">
          <span class="sp-stat-icon tone-green" v-html="ICONS.check"></span>
          <div><strong>{{ s.present_today }}<small v-if="s.expected_today"> / {{ s.expected_today }}</small></strong><span>Timed in today</span></div>
        </div>
        <div class="sp-stat">
          <span class="sp-stat-icon tone-blue" v-html="ICONS.clock"></span>
          <div><strong>{{ s.still_in }}</strong><span>Still at work</span></div>
        </div>
        <button type="button" class="sp-stat" :class="{ on: flag === 'late' }" @click="flag = flag === 'late' ? '' : 'late'">
          <span class="sp-stat-icon tone-amber" v-html="ICONS.alert"></span>
          <div><strong>{{ s.late }}</strong><span>Late in this period</span></div>
        </button>
        <button type="button" class="sp-stat" :class="{ on: flag === 'missing_out' }" @click="flag = flag === 'missing_out' ? '' : 'missing_out'">
          <span class="sp-stat-icon tone-red" v-html="ICONS.minus"></span>
          <div><strong>{{ s.missing_out }}</strong><span>No time-out</span></div>
        </button>
      </template>
    </section>

    <p v-if="data && s.not_in_yet" class="sp-banner warn at-note">
      <span v-html="ICONS.user"></span>{{ s.not_in_yet }} {{ s.not_in_yet === 1 ? "person who works today hasn't" : "people who work today haven't" }} timed in yet.
    </p>

    <section class="sp-card">
      <div class="sp-toolbar at-toolbar">
        <div class="sp-seg" role="tablist" aria-label="Period">
          <button v-for="p in PERIODS" :key="p.key" type="button" role="tab" :class="{ on: period === p.key }" :aria-selected="period === p.key" @click="setPeriod(p.key)">{{ p.label }}</button>
        </div>
        <div class="at-filters">
          <template v-if="period === 'custom'">
            <input v-model="from" type="date" class="sp-input at-date" aria-label="From" :max="to" @change="load" />
            <span class="st-sub">to</span>
            <input v-model="to" type="date" class="sp-input at-date" aria-label="To" :min="from" :max="today" @change="load" />
          </template>
          <select v-model="person" class="sp-select" aria-label="Person" @change="load">
            <option value="">Everyone</option>
            <option v-for="e in data?.employees ?? []" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
        </div>
      </div>
      <p class="st-sub at-summary" v-if="data">
        {{ rangeLabel }} · {{ visible.length }} {{ visible.length === 1 ? "record" : "records" }} · {{ hoursText(totalHours) }} worked
        <button v-if="flag" type="button" class="st-link" @click="flag = ''">Show all</button>
      </p>

      <div v-if="!data" class="at-sk">
        <div v-for="n in 5" :key="n" class="at-sk-row"><div class="bc-skeleton st-avatar sm"></div><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div>
      </div>
      <div v-else-if="!visible.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.clock"></span>
        <h3>{{ data.records.length ? "Nothing flagged" : "No attendance in this period" }}</h3>
        <p>{{ data.records.length ? "Everyone timed in and out on schedule." : data.employees.length ? "People time in and out with the QR scanner in the ERP. You can also add a record yourself." : "Attendance is kept for people with an HR profile. Your HR staff add profiles in the ERP." }}</p>
      </div>
      <div v-else class="at-days">
        <section v-for="d in byDay" :key="d.date" class="at-day">
          <h3>{{ dayLabel(d.date) }} <span>{{ d.rows.length }} {{ d.rows.length === 1 ? "person" : "people" }} · {{ hoursText(d.hours) }}</span></h3>
          <ul>
            <li v-for="r in d.rows" :key="r.id" class="at-row">
              <div class="st-person at-who">
                <span class="st-avatar sm" :style="{ background: personColor(r.employee) }">{{ initials(r.employee) }}</span>
                <div>
                  <strong>{{ r.employee }}</strong>
                  <small>{{ r.expected ? `Scheduled ${range12(r.expected)}` : "Not scheduled" }}</small>
                </div>
              </div>
              <div class="at-times">
                <span><small>In</small>{{ time12(r.time_in) || "—" }}</span>
                <span class="arrow" aria-hidden="true">→</span>
                <span><small>Out</small>{{ r.time_out ? time12(r.time_out) : "—" }}</span>
              </div>
              <div class="at-hours">{{ r.hours !== null ? hoursText(r.hours) : "" }}</div>
              <div class="at-flags">
                <span v-if="r.status === 'open'" class="sp-chip blue">At work</span>
                <span v-else-if="r.status === 'missing_out'" class="sp-chip red">No time-out</span>
                <span v-if="r.late_minutes" class="sp-chip amber">{{ lateText(r.late_minutes) }}</span>
                <span v-else-if="r.late_minutes === 0" class="sp-chip green">On time</span>
                <span v-if="r.verified" class="sp-chip slate" title="Their face was checked when they scanned">Face checked</span>
                <span v-if="r.by_owner" class="sp-chip violet">Edited by you</span>
              </div>
              <button type="button" class="sp-icon-btn" :aria-label="`Edit ${r.employee}'s attendance on ${dayLabel(r.date)}`" @click="openForm(r)"><span v-html="ICONS.edit"></span></button>
              <p v-if="r.note" class="at-note-line">{{ r.note }}</p>
            </li>
          </ul>
        </section>
      </div>
    </section>

    <!-- Add / correct -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="form" class="sp-backdrop" @click="closeForm"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="form" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="at-title" novalidate @submit.prevent="save">
            <div class="sp-modal-head">
              <div>
                <h3 id="at-title">{{ form.id ? "Correct attendance" : "Add an attendance record" }}</h3>
                <p>{{ form.id ? `${form.employee} · ${dayLabel(form.date)}` : "For a day someone forgot to scan." }}</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeForm"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sp-grid-2">
              <template v-if="!form.id">
                <label class="sp-field">
                  <span>Person</span>
                  <select v-model="form.employee_id" class="sp-select" :class="{ invalid: errors.employee_id }">
                    <option :value="null" disabled>Choose…</option>
                    <option v-for="e in data.employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                  </select>
                  <small v-if="errors.employee_id" class="sp-error">{{ errors.employee_id }}</small>
                </label>
                <label class="sp-field">
                  <span>Day</span>
                  <input v-model="form.date" type="date" class="sp-input" :class="{ invalid: errors.date }" :max="today" />
                  <small v-if="errors.date" class="sp-error">{{ errors.date }}</small>
                </label>
              </template>
              <label class="sp-field">
                <span>Time in</span>
                <input v-model="form.time_in" type="time" class="sp-input" :class="{ invalid: errors.time_in }" />
                <small v-if="errors.time_in" class="sp-error">{{ errors.time_in }}</small>
              </label>
              <label class="sp-field">
                <span>Time out <small>(optional)</small></span>
                <input v-model="form.time_out" type="time" class="sp-input" :class="{ invalid: errors.time_out }" />
                <small v-if="errors.time_out" class="sp-error">{{ errors.time_out }}</small>
              </label>
              <label class="sp-field sp-span-all">
                <span>Reason <small>(optional)</small></span>
                <input v-model="form.note" class="sp-input" maxlength="200" placeholder="e.g. Forgot to scan out" />
              </label>
            </div>
            <p class="sp-hint">The record is marked as edited by you.</p>
            <p v-if="formError" class="sp-error">{{ formError }}</p>
            <div class="sp-modal-foot">
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
import { hoursText, lateText, personColor, range12, time12 } from "../../../components/vendor/staff/staffUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";

const PERIODS = [
  { key: "today", label: "Today" },
  { key: "week", label: "This week" },
  { key: "last_week", label: "Last week" },
  { key: "month", label: "This month" },
  { key: "custom", label: "Custom" },
];
const today = ymd(new Date());

const data = ref(null);
const error = ref("");
const period = ref("week");
const from = ref("");
const to = ref("");
const person = ref("");
const flag = ref("");
const form = ref(null);
const errors = reactive({});
const formError = ref("");
const saving = ref(false);

const s = computed(() => data.value?.stats ?? {});
const visible = computed(() =>
  (data.value?.records ?? []).filter((r) => (flag.value === "late" ? r.late_minutes > 0 : flag.value === "missing_out" ? r.status === "missing_out" : true)),
);
const totalHours = computed(() => visible.value.reduce((n, r) => n + (r.hours ?? 0), 0));
const byDay = computed(() => {
  const days = new Map();
  for (const r of visible.value) {
    if (!days.has(r.date)) days.set(r.date, { date: r.date, rows: [], hours: 0 });
    const d = days.get(r.date);
    d.rows.push(r);
    d.hours += r.hours ?? 0;
  }
  return [...days.values()];
});
const rangeLabel = computed(() => {
  if (!data.value) return "";
  const { from: f, to: t } = data.value.range;
  return f === t ? dayLabel(f) : `${shortDate(f)} – ${shortDate(t)}`;
});

function rangeFor(key) {
  const now = new Date();
  const monday = new Date(now);
  monday.setDate(now.getDate() - ((now.getDay() + 6) % 7));
  switch (key) {
    case "today":
      return [today, today];
    case "last_week": {
      const s0 = new Date(monday);
      s0.setDate(monday.getDate() - 7);
      const e0 = new Date(monday);
      e0.setDate(monday.getDate() - 1);
      return [ymd(s0), ymd(e0)];
    }
    case "month":
      return [ymd(new Date(now.getFullYear(), now.getMonth(), 1)), today];
    default:
      return [ymd(monday), today];
  }
}
function setPeriod(key) {
  period.value = key;
  if (key !== "custom") [from.value, to.value] = rangeFor(key);
  load();
}

async function load() {
  error.value = "";
  if (from.value > to.value) return;
  try {
    const res = await api.get("vendor/staff/attendance", { params: { from: from.value, to: to.value, employee_id: person.value || undefined } });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load attendance.");
  }
}

function dayLabel(date) {
  if (date === today) return "Today";
  const y = new Date();
  y.setDate(y.getDate() - 1);
  if (date === ymd(y)) return "Yesterday";
  return new Date(`${date}T00:00:00`).toLocaleDateString("en-PH", { weekday: "long", month: "short", day: "numeric" });
}
const shortDate = (date) => new Date(`${date}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
const initials = (name) => String(name).split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase();

function openForm(r = null) {
  Object.keys(errors).forEach((k) => delete errors[k]);
  formError.value = "";
  form.value = r
    ? { id: r.id, employee: r.employee, date: r.date, time_in: r.time_in ?? "", time_out: r.time_out ?? "", note: "" }
    : { id: null, employee_id: person.value || null, date: today, time_in: "08:00", time_out: "", note: "" };
}
function closeForm() {
  if (!saving.value) form.value = null;
}

async function save() {
  if (saving.value) return;
  Object.keys(errors).forEach((k) => delete errors[k]);
  formError.value = "";
  const f = form.value;
  if (!f.id && !f.employee_id) errors.employee_id = "Choose who this is for.";
  if (!f.id && !f.date) errors.date = "Pick the day.";
  if (!f.time_in) errors.time_in = "Enter the time in.";
  if (f.time_out && f.time_in && f.time_out <= f.time_in) errors.time_out = "Time out must be after time in.";
  if (Object.keys(errors).length) return;

  saving.value = true;
  const body = { time_in: f.time_in, time_out: f.time_out || null, note: f.note.trim() || null };
  try {
    const { data: res } = f.id
      ? await api.put(`vendor/staff/attendance/${f.id}`, body)
      : await api.post("vendor/staff/attendance", { ...body, employee_id: f.employee_id, date: f.date });
    toast.success(res.message);
    form.value = null;
    await load();
  } catch (e) {
    const fieldErrors = e?.response?.data?.errors ?? {};
    for (const [key, list] of Object.entries(fieldErrors)) errors[key] = [].concat(list)[0];
    formError.value = Object.keys(fieldErrors).length ? "" : apiMessage(e, "Couldn't save.");
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  [from.value, to.value] = rangeFor("week");
  load();
});
</script>

<style scoped>
.at-note {
  margin: -4px 0 16px;
}
.at-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}
.at-filters {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.at-filters .sp-select {
  width: auto;
  min-width: 160px;
}
.at-date {
  width: 150px;
}
.at-summary {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin: 12px 0 0;
}
.sp-stat strong small {
  font-size: 13px;
  font-weight: 500;
  color: #a0aec0;
}
.at-sk-row {
  display: grid;
  grid-template-columns: 30px 1fr 1fr;
  align-items: center;
  gap: 14px;
  padding: 12px 0;
}
.at-days {
  display: flex;
  flex-direction: column;
  gap: 18px;
  margin-top: 16px;
}
.at-day h3 {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 4px 10px;
  margin: 0 0 6px;
  font-size: 13.5px;
  font-weight: 600;
  color: #1a202c;
}
.at-day h3 span {
  font-size: 12px;
  font-weight: 400;
  color: #a0aec0;
}
.at-day ul {
  margin: 0;
  padding: 0;
  list-style: none;
  border: 1px solid #edf2f7;
  border-radius: 14px;
  overflow: hidden;
}
.at-row {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(170px, 1fr) 70px minmax(0, 1.4fr) 34px;
  align-items: center;
  gap: 14px;
  padding: 11px 14px;
  background: #fff;
}
.at-row + .at-row {
  border-top: 1px solid #f4f6f8;
}
.at-times {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.at-times small {
  display: block;
  font-size: 10.5px;
  color: #a0aec0;
}
.at-times .arrow {
  color: #cbd5e0;
}
.at-hours {
  font-size: 13px;
  font-weight: 600;
  color: #2d3748;
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.at-flags {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  min-width: 0;
}
.at-note-line {
  grid-column: 1 / -1;
  margin: -4px 0 0 41px;
  font-size: 12px;
  color: #718096;
  overflow-wrap: anywhere;
}
@media (max-width: 900px) {
  .at-row {
    grid-template-columns: minmax(0, 1fr) auto 34px;
  }
  .at-who {
    grid-column: 1;
  }
  .at-times {
    grid-column: 1;
    grid-row: 2;
  }
  .at-hours {
    grid-column: 2;
    grid-row: 1;
  }
  .at-flags {
    grid-column: 1 / -1;
  }
  .at-row > .sp-icon-btn {
    grid-column: 3;
    grid-row: 1;
  }
  .at-note-line {
    margin-left: 0;
  }
}
</style>
