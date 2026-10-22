<template>
  <HrPage title="Daily log" subtitle="Who came in, when, and who was late, left early or didn't come — against each person's schedule." :tabs="ATTENDANCE_TABS">
    <template #actions>
      <button v-if="canEdit" type="button" class="sp-btn sp-btn-ghost" @click="openCorrection()"><span v-html="ICONS.plus"></span>Add a missing day</button>
      <router-link :to="{ name: 'AttendanceQRScanner' }" class="sp-btn sp-btn-primary dl-link"><span v-html="ICONS.grid"></span>Open scanner</router-link>
    </template>

    <section class="sp-stats" aria-label="Summary">
      <template v-if="!report">
        <div v-for="n in 6" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <button v-for="t in tiles" :key="t.key" type="button" class="sp-stat" :class="{ on: flag === t.key }" @click="flag = flag === t.key ? '' : t.key">
          <span class="sp-stat-icon" :class="`tone-${t.tone}`" v-html="ICONS[t.icon]"></span>
          <div><strong>{{ t.value }}</strong><span>{{ t.label }}</span></div>
        </button>
      </template>
    </section>

    <section class="sp-card">
      <div class="sp-toolbar dl-toolbar">
        <div class="sp-seg" role="tablist" aria-label="Period">
          <button v-for="p in PERIODS" :key="p.key" type="button" role="tab" :class="{ on: period === p.key }" :aria-selected="period === p.key" @click="setPeriod(p.key)">{{ p.label }}</button>
        </div>
        <div class="dl-filters">
          <template v-if="period === 'custom'">
            <input v-model="from" type="date" class="sp-input" aria-label="From" :max="to" @change="load" />
            <input v-model="to" type="date" class="sp-input" aria-label="To" :min="from" :max="today" @change="load" />
          </template>
          <select v-model="person" class="sp-select" aria-label="Person">
            <option value="">Everyone</option>
            <option v-for="p in report?.people ?? []" :key="p.employee_id" :value="p.employee_id">{{ p.name }}</option>
          </select>
        </div>
      </div>

      <p v-if="error" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ error }}<button type="button" @click="load">Try again</button></p>
      <ul v-else-if="!report" class="dl-sk">
        <li v-for="n in 6" :key="n"><div class="bc-skeleton st-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
      </ul>
      <div v-else-if="!rows.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.calendar"></span>
        <h3>{{ report.days.length ? "Nothing matches" : "No attendance in this period" }}</h3>
        <p>{{ report.days.length ? "Try another filter or person." : "Days show up once someone scans in, or when a scheduled day passes." }}</p>
      </div>
      <div v-else class="st-scroll">
        <table class="st-table cards">
          <colgroup>
            <col style="width: 25%" />
            <col style="width: 14%" />
            <col style="width: 20%" />
            <col style="width: 9%" />
            <col style="width: 26%" />
            <col style="width: 6%" />
          </colgroup>
          <thead><tr><th>Person</th><th>Day</th><th>In – out</th><th>Hours</th><th>Notes</th><th><span class="dl-sr">Actions</span></th></tr></thead>
          <tbody>
            <tr v-for="d in rows" :key="`${d.employee_id}-${d.date}`">
              <td>
                <div class="st-person">
                  <span class="st-avatar sm" :style="{ background: personColor(d.employee) }">{{ initialsOf(d.employee) }}</span>
                  <div><strong>{{ d.employee }}</strong><small>{{ d.expected ? `Scheduled ${range12(d.expected)}` : "Not scheduled" }}</small></div>
                </div>
              </td>
              <td data-label="Day">{{ dayLabel(d.date) }}</td>
              <td data-label="In – out">
                <template v-if="d.time_in">{{ time12(d.time_in) }} – {{ d.time_out ? time12(d.time_out) : "…" }}</template>
                <span v-else class="muted">—</span>
              </td>
              <td data-label="Hours">{{ d.hours !== null ? hoursText(d.hours) : "—" }}</td>
              <td data-label="Notes">
                <div class="dl-chips">
                  <span class="sp-chip" :class="STATUS[d.status]?.tone ?? 'slate'">{{ STATUS[d.status]?.label ?? d.status }}</span>
                  <span v-if="d.late_minutes" class="sp-chip amber">{{ lateText(d.late_minutes) }}</span>
                  <span v-if="d.undertime_minutes" class="sp-chip violet">Left {{ minutesText(d.undertime_minutes) }} early</span>
                  <span v-if="d.overtime_status === 'pending'" class="sp-chip violet">{{ minutesText(d.overtime_minutes) }} overtime · waiting</span>
                  <span v-else-if="d.overtime_status === 'approved'" class="sp-chip green">{{ minutesText(d.overtime_approved_minutes) }} overtime</span>
                  <span v-else-if="d.overtime_status === 'rejected'" class="sp-chip slate">Overtime not approved</span>
                  <span v-if="d.review_status === 'pending'" class="sp-chip amber" :title="d.review_reasons.join(' ')">Needs review</span>
                  <span v-if="d.source === 'correction'" class="sp-chip blue">Corrected</span>
                </div>
              </td>
              <td class="num">
                <button v-if="canEdit && d.status !== 'on_leave'" type="button" class="sp-icon-btn" :aria-label="`Ask to correct ${d.employee} on ${d.date}`" title="Ask for a correction" @click="openCorrection(d)"><span v-html="ICONS.edit"></span></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Correction request -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="form" class="sp-backdrop" @click="closeForm"></div></Transition>
      <Transition name="sp-slide">
        <form v-if="form" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="dl-form-title" novalidate @submit.prevent="send">
          <div class="sp-drawer-head">
            <div>
              <h3 id="dl-form-title">{{ form.locked ? `Correct ${form.employeeName}` : "Add a missing day" }}</h3>
              <p>Someone else — the shop owner or a manager with approval access — approves it before it changes the record.</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeForm"><span v-html="ICONS.close"></span></button>
          </div>
          <div class="sp-drawer-body">
            <div class="sp-grid-2">
              <label class="sp-field sp-span-all">
                <span class="sp-label">Person</span>
                <select v-model="form.employee_id" class="sp-select" :disabled="form.locked" :class="{ invalid: errors.employee_id }">
                  <option :value="null" disabled>Choose…</option>
                  <option v-for="p in report?.people ?? []" :key="p.employee_id" :value="p.employee_id">{{ p.name }}</option>
                </select>
                <small v-if="errors.employee_id" class="sp-error">{{ errors.employee_id }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Day</span>
                <input v-model="form.date" type="date" class="sp-input" :max="today" :disabled="form.locked" :class="{ invalid: errors.date }" />
                <small v-if="errors.date" class="sp-error">{{ errors.date }}</small>
              </label>
              <div></div>
              <label class="sp-field">
                <span class="sp-label">Time in</span>
                <input v-model="form.time_in" type="time" class="sp-input" :class="{ invalid: errors.time_in }" />
                <small v-if="errors.time_in" class="sp-error">{{ errors.time_in }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Time out <small>(optional)</small></span>
                <input v-model="form.time_out" type="time" class="sp-input" :class="{ invalid: errors.time_out }" />
                <small v-if="errors.time_out" class="sp-error">{{ errors.time_out }}</small>
              </label>
              <label class="sp-field sp-span-all">
                <span class="sp-label">Why</span>
                <textarea v-model="form.reason" class="sp-textarea" maxlength="500" placeholder="e.g. The scanner was down from 8 to 9 AM; seen at the counter by the manager." :class="{ invalid: errors.reason }"></textarea>
                <small v-if="errors.reason" class="sp-error">{{ errors.reason }}</small>
              </label>
            </div>
            <p v-if="formError" class="sp-error">{{ formError }}</p>
          </div>
          <div class="sp-drawer-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="closeForm">Cancel</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="saving">{{ saving ? "Sending…" : "Send for approval" }}</button>
          </div>
        </form>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { toast } from "vue3-toastify";
import HrPage from "../../../../components/hr/HrPage.vue";
import { ATTENDANCE_TABS, initialsOf } from "../../../../components/hr/hrUi";
import { SHOP_ICONS as ICONS } from "../../../../components/vendor/shop/shopIcons";
import { ymd } from "../../../../components/vendor/finance/financeUi";
import { hoursText, lateText, personColor, range12, time12 } from "../../../../components/vendor/staff/staffUi";
import { useAssignment } from "../../../../composables/useAssignment";
import attendanceApi from "../../../../services/attendanceApi";

const PERIODS = [
  { key: "today", label: "Today" },
  { key: "week", label: "This week" },
  { key: "month", label: "This month" },
  { key: "custom", label: "Custom" },
];
const STATUS = {
  present: { label: "Present", tone: "green" },
  in: { label: "At work", tone: "blue" },
  missing_out: { label: "No time out", tone: "red" },
  absent: { label: "Absent", tone: "red" },
  on_leave: { label: "On leave", tone: "teal" },
  not_in_yet: { label: "Not in yet", tone: "slate" },
  rejected: { label: "Rejected", tone: "slate" },
};

const { can } = useAssignment();
const canEdit = computed(() => can("attendance", "edit"));
const today = ymd(new Date());

const report = ref(null);
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

const t = computed(() => report.value?.totals ?? {});
const tiles = computed(() => [
  { key: "present", label: "Days present", value: t.value.present ?? 0, tone: "green", icon: "check" },
  { key: "late", label: "Late arrivals", value: t.value.late ?? 0, tone: "amber", icon: "clock" },
  { key: "undertime", label: "Left early", value: t.value.undertime ?? 0, tone: "violet", icon: "logout" },
  { key: "absent", label: "Absences", value: t.value.absent ?? 0, tone: "red", icon: "minus" },
  { key: "review", label: "Needs review", value: t.value.needs_review ?? 0, tone: "amber", icon: "eye" },
  { key: "overtime", label: "Overtime waiting", value: t.value.overtime_pending_minutes ? minutesText(t.value.overtime_pending_minutes) : 0, tone: "blue", icon: "clock" },
]);
const rows = computed(() =>
  (report.value?.days ?? []).filter((d) => {
    if (person.value && d.employee_id !== person.value) return false;
    switch (flag.value) {
      case "present": return ["present", "in", "missing_out"].includes(d.status);
      case "late": return d.late_minutes > 0;
      case "undertime": return d.undertime_minutes > 0;
      case "absent": return d.status === "absent";
      case "review": return d.review_status === "pending";
      case "overtime": return d.overtime_status === "pending";
      default: return true;
    }
  }),
);

const minutesText = (m) => (m >= 60 ? `${Math.floor(m / 60)} h ${m % 60 ? `${m % 60} min` : ""}`.trim() : `${m} min`);
function dayLabel(date) {
  const d = new Date(`${date}T00:00:00`);
  return date === today ? "Today" : d.toLocaleDateString("en-PH", { weekday: "short", month: "short", day: "numeric" });
}

function setPeriod(key) {
  period.value = key;
  const now = new Date();
  if (key === "today") from.value = today;
  if (key === "week") {
    const start = new Date(now);
    start.setDate(now.getDate() - ((now.getDay() + 6) % 7));
    from.value = ymd(start);
  }
  if (key === "month") from.value = ymd(new Date(now.getFullYear(), now.getMonth(), 1));
  if (key !== "custom") to.value = today;
  load();
}

async function load() {
  error.value = "";
  report.value = null;
  try {
    report.value = await attendanceApi.report(from.value, to.value);
  } catch (e) {
    error.value = e?.response?.data?.errors?.from?.[0] || e?.response?.data?.message || "Couldn't load the daily log.";
  }
}

function openCorrection(d = null) {
  Object.keys(errors).forEach((k) => delete errors[k]);
  formError.value = "";
  form.value = d
    ? { locked: true, employeeName: d.employee, employee_id: d.employee_id, date: d.date, time_in: d.time_in ?? d.expected?.slice(0, 5) ?? "", time_out: d.time_out ?? "", reason: "" }
    : { locked: false, employee_id: person.value || null, date: today, time_in: "", time_out: "", reason: "" };
}

function closeForm() {
  if (!saving.value) form.value = null;
}

async function send() {
  const f = form.value;
  Object.keys(errors).forEach((k) => delete errors[k]);
  if (!f.employee_id) errors.employee_id = "Choose who it's for.";
  if (!f.time_in) errors.time_in = "Enter the time in.";
  if (f.time_out && f.time_out <= f.time_in) errors.time_out = "Time out must be after time in.";
  if (f.reason.trim().length < 5) errors.reason = "Say why the times need fixing.";
  if (Object.keys(errors).length) return;
  saving.value = true;
  formError.value = "";
  try {
    const res = await attendanceApi.requestCorrection({ employee_id: f.employee_id, date: f.date, time_in: f.time_in, time_out: f.time_out || null, reason: f.reason.trim() });
    toast.success(res.message);
    form.value = null;
  } catch (e) {
    const list = e?.response?.data?.errors ?? {};
    for (const [k, v] of Object.entries(list)) errors[k] = [].concat(v)[0];
    formError.value = Object.keys(list).length ? "" : e?.response?.data?.message || "Couldn't send it.";
  } finally {
    saving.value = false;
  }
}

onMounted(() => setPeriod("week"));
</script>

<style scoped>
.dl-link {
  text-decoration: none;
}
.sp-stats {
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  margin-bottom: 18px;
}
.sp-stat .grow {
  flex: 1;
}
.dl-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 10px;
}
.dl-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.dl-filters .sp-input,
.dl-filters .sp-select {
  width: auto;
  min-width: 140px;
}
.dl-sk {
  margin: 0;
  padding: 0;
  list-style: none;
}
.dl-sk li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
}
.dl-sk .grow {
  flex: 1;
}
.dl-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}
.muted {
  color: var(--v-faint);
}
.dl-sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}
</style>
