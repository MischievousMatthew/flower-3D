<template>
  <HrPage title="HR dashboard" subtitle="Who works here, who is in today, and what is waiting for a decision.">
    <template #actions>
      <span v-if="data" class="hd-date">{{ longDay(data.date) }}</span>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="load"><span v-html="ICONS.restore"></span>Refresh</button>
      <button v-if="canExport" type="button" class="sp-btn sp-btn-primary" @click="openExport"><span v-html="ICONS.download"></span>Export</button>
    </template>

    <!-- Export the HR report for a period -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="exportOpen" class="sp-backdrop" @click="exportOpen = false"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="exportOpen" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="hx-title" novalidate @submit.prevent="downloadReport" @keydown.esc="exportOpen = false">
            <div class="sp-modal-head">
              <div><h3 id="hx-title">Export HR report</h3><p>A spreadsheet (CSV) that opens in Excel or Google Sheets.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="exportOpen = false"><span v-html="ICONS.close"></span></button>
            </div>

            <div class="hx-body">
              <div class="sp-field">
                <span class="sp-label" id="hx-period">Period</span>
                <div class="sp-seg" role="radiogroup" aria-labelledby="hx-period">
                  <button v-for="p in PERIODS" :key="p.key" type="button" role="radio" :aria-checked="exp.period === p.key" :class="{ on: exp.period === p.key }" @click="setPeriod(p.key)">{{ p.label }}</button>
                </div>
              </div>
              <div v-if="exp.period === 'custom'" class="hx-dates">
                <label class="sp-field"><span class="sp-label">From</span><input v-model="exp.from" type="date" class="sp-input" :max="exp.to || todayYmd" /></label>
                <label class="sp-field"><span class="sp-label">To</span><input v-model="exp.to" type="date" class="sp-input" :min="exp.from" :max="todayYmd" /></label>
              </div>
              <p class="sp-hint hx-range">{{ rangeText }}</p>

              <fieldset class="hx-parts">
                <legend class="sp-label">What's in it</legend>
                <label v-for="s in SECTIONS" :key="s.key" class="hx-part" :class="{ on: exp.sections[s.key] }">
                  <input v-model="exp.sections[s.key]" type="checkbox" />
                  <span class="hx-tick" aria-hidden="true" v-html="ICONS.check"></span>
                  <span class="hx-part-text"><strong>{{ s.label }}</strong><small>{{ s.detail }}</small></span>
                </label>
              </fieldset>
            </div>

            <p v-if="exp.error" class="sp-error" role="alert">{{ exp.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="exportOpen = false">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="exp.busy || !anySection"><span v-html="ICONS.download"></span>{{ exp.busy ? "Preparing…" : "Download CSV" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Today at a glance -->
    <section class="fx-kpis" aria-label="Today">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article v-for="k in kpis" :key="k.key" class="sp-card fx-kpi" :class="{ hero: k.hero }">
          <span class="fx-kpi-label"><span class="hd-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong>{{ k.value }}</strong>
          <span class="fx-kpi-sub">{{ k.sub }}</span>
          <div v-if="k.bar !== undefined" class="hd-meter" role="img" :aria-label="`${k.bar}% of the team`"><span :style="{ width: `${k.bar}%` }"></span></div>
        </article>
      </template>
    </section>

    <!-- Decisions waiting -->
    <section v-if="!data || waiting.length" class="hd-waiting" aria-label="Waiting for a decision">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card hd-wait"><div class="bc-skeleton fx-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <router-link v-for="w in waiting" :key="w.key" :to="w.to" class="sp-card hd-wait" :class="{ quiet: !w.count }">
          <span class="fx-icon" :class="`tone-${w.tone}`" v-html="ICONS[w.icon]"></span>
          <div class="grow">
            <span class="hd-wait-label">{{ w.label }}</span>
            <strong>{{ w.count }}</strong>
            <span class="hd-wait-sub">{{ w.count ? w.sub : "Nothing waiting" }}</span>
          </div>
          <span class="hd-go" v-html="ICONS.chevronRight"></span>
        </router-link>
      </template>
    </section>

    <div class="hd-grid">
      <!-- Today's clock-ins -->
      <section class="sp-card hd-activity" aria-label="Today's attendance">
        <div class="sp-card-head">
          <div><h2>Today's attendance</h2><p>Latest clock-ins and clock-outs</p></div>
          <router-link v-if="canOpen('attendance')" :to="{ name: 'AttendanceLogs' }" class="sp-btn sp-btn-ghost sp-btn-sm">Daily log</router-link>
        </div>
        <ul v-if="!data" class="hd-list">
          <li v-for="n in 4" :key="n" class="hd-row"><div class="bc-skeleton hd-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <div v-else-if="!data.activity.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.clock"></span>
          <h3>No one has clocked in yet</h3>
          <p>Scans from the shop's attendance device show up here as they happen.</p>
        </div>
        <ul v-else class="hd-list" :class="{ dim: loading }">
          <li v-for="a in data.activity" :key="a.id" class="hd-row">
            <Avatar :name="a.name" :src="a.avatar" />
            <div class="grow">
              <strong>{{ a.name }}</strong>
              <span>In {{ clock(a.time_in) }}<template v-if="a.time_out"> · Out {{ clock(a.time_out) }}</template></span>
            </div>
            <span v-if="a.flagged" class="sp-chip amber">To review</span>
            <span class="sp-chip" :class="a.time_out ? 'slate' : 'green'">{{ a.time_out ? "Clocked out" : "Working" }}</span>
          </li>
        </ul>
      </section>

      <!-- The past week -->
      <section class="sp-card" aria-label="Attendance this week">
        <div class="sp-card-head">
          <div><h2>Last 7 days</h2><p>People who clocked in each day</p></div>
        </div>
        <div v-if="!data" class="bc-skeleton hd-sk-chart"></div>
        <template v-else>
          <div class="hd-week" role="img" :aria-label="weekLabel">
            <div v-for="d in week" :key="d.date" class="hd-day" :class="{ today: d.today }">
              <span class="hd-day-n">{{ d.present }}</span>
              <div class="hd-day-bar"><span :style="{ height: `${d.pct}%` }"></span></div>
              <span class="hd-day-l">{{ d.label }}</span>
            </div>
          </div>
          <p class="sp-hint hd-week-note">{{ data.headcount ? `Out of ${data.headcount} active ${data.headcount === 1 ? "person" : "people"}` : "Add employees to see attendance here" }}</p>
        </template>
      </section>
    </div>

    <div class="hd-grid two">
      <!-- Leave -->
      <section class="sp-card" aria-label="Leave">
        <div class="sp-card-head">
          <div><h2>On leave</h2><p>Today and the next two weeks</p></div>
          <router-link v-if="canOpen('leave_management')" :to="{ name: 'LeaveRequests' }" class="sp-btn sp-btn-ghost sp-btn-sm">Leave & overtime</router-link>
        </div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <p v-else-if="!data.leave_today.length && !data.upcoming_leave.length" class="sp-hint">No approved leave today or in the next two weeks.</p>
        <ul v-else class="hd-list">
          <li v-for="l in data.leave_today" :key="`t${l.id}`" class="hd-row">
            <Avatar :name="l.name" :src="l.avatar" />
            <div class="grow"><strong>{{ l.name }}</strong><span>{{ leaveLabel(l.type) }} · until {{ shortDay(l.end_date) }}</span></div>
            <span class="sp-chip violet">Today</span>
          </li>
          <li v-for="l in data.upcoming_leave" :key="`u${l.id}`" class="hd-row">
            <Avatar :name="l.name" :src="l.avatar" />
            <div class="grow"><strong>{{ l.name }}</strong><span>{{ leaveLabel(l.type) }} · {{ dayRange(l.start_date, l.end_date) }}</span></div>
            <span class="sp-chip slate">Upcoming</span>
          </li>
        </ul>
      </section>

      <!-- Payroll -->
      <section class="sp-card hd-pay" aria-label="Payroll">
        <div class="sp-card-head">
          <div><h2>Last payroll</h2><p>The most recent approved pay period</p></div>
          <router-link v-if="canOpen('payroll')" :to="{ name: 'PayrollList' }" class="sp-btn sp-btn-ghost sp-btn-sm">Payroll</router-link>
        </div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <p v-else-if="!data.payroll" class="sp-hint">No payroll has been approved yet.</p>
        <dl v-else class="hd-pay-grid">
          <div class="wide"><dt>Period</dt><dd>{{ dayRange(data.payroll.period_start, data.payroll.period_end) }}</dd></div>
          <div><dt>Net pay</dt><dd>{{ money(data.payroll.total) }}</dd></div>
          <div><dt>People paid</dt><dd>{{ data.payroll.people }}</dd></div>
        </dl>
      </section>
    </div>
  </HrPage>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from "vue";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../components/vendor/finance/financeUi";
import { useAssignment } from "../../../composables/useAssignment";
import "../../../components/vendor/finance/finance.css";

const { can, canView } = useAssignment();
const canOpen = (module) => canView(module);
const canExport = computed(() => can("hr_dashboard", "export"));

const data = ref(null);
const error = ref("");
const loading = ref(false);

const Avatar = defineComponent({
  props: { name: String, src: String },
  setup(p) {
    const failed = ref(false);
    return () =>
      p.src && !failed.value
        ? h("img", { class: "hd-avatar", src: p.src, alt: "", onError: () => (failed.value = true) })
        : h("span", { class: "hd-avatar", "aria-hidden": "true" }, (p.name || "?").split(/\s+/).map((w) => w[0]).slice(0, 2).join("").toUpperCase());
  },
});

const pct = (n, of) => (of > 0 ? Math.min(100, Math.round((n / of) * 100)) : 0);
const people = (n) => `${n} ${n === 1 ? "person" : "people"}`;

const kpis = computed(() => {
  const d = data.value;
  return [
    { key: "team", label: "Team", icon: "users", tone: "blue", value: d.headcount, sub: d.scheduled !== null ? `${people(d.scheduled)} scheduled today` : "active employees" },
    { key: "in", label: "In today", icon: "clock", tone: "green", value: d.present, hero: true, sub: `${pct(d.present, d.headcount)}% of the team · ${d.clocked_in} still working`, bar: pct(d.present, d.headcount) },
    { key: "leave", label: "On leave", icon: "calendar", tone: "violet", value: d.on_leave, sub: d.on_leave ? "approved leave today" : "no one is on leave" },
    { key: "out", label: "Not in yet", icon: "alert", tone: "amber", value: d.not_in, sub: d.not_in ? "no scan and no leave today" : "everyone is accounted for" },
  ];
});

const waiting = computed(() => {
  const w = data.value?.waiting ?? {};
  return [
    { key: "leave", module: "leave_management", label: "Leave requests", icon: "calendar", tone: "violet", count: w.leave, sub: "waiting for approval", to: { name: "LeaveRequests" } },
    { key: "overtime", module: "leave_management", label: "Overtime requests", icon: "clock", tone: "teal", count: w.overtime, sub: "waiting for approval", to: { name: "LeaveRequests" } },
    { key: "attendance", module: "attendance", label: "Attendance to review", icon: "eye", tone: "amber", count: w.attendance, sub: "flagged scans or corrections", to: { name: "AttendanceReview" } },
    { key: "payroll", module: "payroll", label: "Payroll", icon: "wallet", tone: "blue", count: w.payroll, sub: "waiting for Finance", to: { name: "PayrollList" } },
  ].filter((x) => canOpen(x.module));
});

const week = computed(() => {
  const rows = data.value?.week ?? [];
  const top = Math.max(1, data.value?.headcount ?? 0, ...rows.map((r) => r.present));
  return rows.map((r) => ({
    ...r,
    pct: Math.max(r.present ? 6 : 0, Math.round((r.present / top) * 100)),
    label: new Date(`${r.date}T00:00:00`).toLocaleDateString("en-PH", { weekday: "short" }),
    today: r.date === data.value.date,
  }));
});
const weekLabel = computed(() => week.value.map((d) => `${d.label} ${d.present}`).join(", "));

const asDate = (s) => new Date(`${s}T00:00:00`);
const longDay = (s) => asDate(s).toLocaleDateString("en-PH", { weekday: "long", month: "long", day: "numeric" });
const shortDay = (s) => asDate(s).toLocaleDateString("en-PH", { month: "short", day: "numeric" });
const dayRange = (a, b) => (a === b ? shortDay(a) : `${shortDay(a)} – ${shortDay(b)}`);
const leaveLabel = (t) => (t ? t.replace(/_/g, " ").replace(/^\w/, (c) => c.toUpperCase()) : "Leave");
function clock(t) {
  if (!t) return "";
  const [hh, mm] = t.split(":").map(Number);
  return `${hh % 12 || 12}:${String(mm).padStart(2, "0")} ${hh < 12 ? "AM" : "PM"}`;
}

async function load() {
  error.value = "";
  loading.value = true;
  try {
    const res = await api.get("hr/dashboard");
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load the HR dashboard.");
  } finally {
    loading.value = false;
  }
}
onMounted(load);

// ── Export ──────────────────────────────────────────────────────────────
// One CSV with a section per part: the summary, a row per person, the
// approved leave, and what's waiting. Pay columns come only for people who
// may see payroll (the server leaves them out otherwise).
const PERIODS = [
  { key: "week", label: "This week" },
  { key: "month", label: "This month" },
  { key: "last", label: "Last month" },
  { key: "custom", label: "Custom" },
];
const SECTIONS = [
  { key: "summary", label: "Summary", detail: "Team size, attendance rate, lates, absences, leave, overtime and total net pay" },
  { key: "people", label: "Per employee", detail: "Days present and absent, lates, hours, overtime, leave days and pay for each person" },
  { key: "leave", label: "Approved leave", detail: "Who was off, the type of leave, dates and days" },
  { key: "waiting", label: "Waiting for a decision", detail: "Leave, overtime, attendance and payroll still to approve" },
];
const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
const todayYmd = ymd(new Date());
const exportOpen = ref(false);
const exp = reactive({
  period: "month",
  from: "",
  to: "",
  busy: false,
  error: "",
  sections: Object.fromEntries(SECTIONS.map((s) => [s.key, true])),
});
const anySection = computed(() => Object.values(exp.sections).some(Boolean));
const rangeText = computed(() => {
  if (!exp.from || !exp.to) return "Choose both dates.";
  const days = Math.round((asDate(exp.to) - asDate(exp.from)) / 86400000) + 1;
  return `${longDate(exp.from)} to ${longDate(exp.to)} · ${days} ${days === 1 ? "day" : "days"}`;
});
const longDate = (s) => asDate(s).toLocaleDateString("en-PH", { month: "long", day: "numeric", year: "numeric" });

function setPeriod(key) {
  exp.period = key;
  exp.error = "";
  const now = new Date();
  if (key === "week") {
    const start = new Date(now);
    start.setDate(now.getDate() - ((now.getDay() + 6) % 7)); // Monday
    [exp.from, exp.to] = [ymd(start), todayYmd];
  } else if (key === "month") {
    [exp.from, exp.to] = [ymd(new Date(now.getFullYear(), now.getMonth(), 1)), todayYmd];
  } else if (key === "last") {
    [exp.from, exp.to] = [ymd(new Date(now.getFullYear(), now.getMonth() - 1, 1)), ymd(new Date(now.getFullYear(), now.getMonth(), 0))];
  }
}

function openExport() {
  if (!exp.from) setPeriod("month");
  exp.error = "";
  exportOpen.value = true;
}

// Quote every cell; a leading = + - @ could run as a spreadsheet formula
const csvCell = (v) => {
  const s = v === null || v === undefined ? "" : String(v);
  return `"${(/^[=+\-@]/.test(s) ? `'${s}` : s).replace(/"/g, '""')}"`;
};
const yesNo = (b) => (b ? "Yes" : "No");
const orDash = (v) => (v === null || v === undefined || v === "" ? "—" : v);

function reportRows(r) {
  const s = r.summary;
  const rows = [["BloomCraft HR report"], ["Period", `${longDate(r.from)} to ${longDate(r.to)}`], ["Downloaded", new Date().toLocaleString("en-PH")], []];
  if (exp.sections.summary) {
    rows.push(["SUMMARY"], ["Measure", "Value"]);
    rows.push(
      ["Active employees", s.headcount],
      ...Object.entries(s.by_status || {}).map(([status, n]) => [`  ${status}`, n]),
      ["Hired in this period", s.hired],
      ["Scheduled working days", s.scheduled],
      ["Days present", s.present],
      ["Days absent", s.absent],
      ["Attendance rate", s.attendance_rate === null ? "—" : `${s.attendance_rate}%`],
      ["Times late", s.late],
      ["Minutes late", s.late_minutes],
      ["Hours worked", s.hours],
      ["Approved overtime (hours)", s.overtime_hours],
      ["Leave days taken", s.leave_days],
    );
    if (r.with_pay) rows.push(["Net pay (approved payroll)", s.net_pay]);
    rows.push([]);
  }
  if (exp.sections.people) {
    const head = ["Name", "Position", "Status", "Date hired", "Scheduled days", "Present", "Absent", "On leave", "Attendance rate", "Times late", "Minutes late", "Left early", "No time out", "Hours worked", "Overtime (hours)", "Leave days"];
    if (r.with_pay) head.push("Gross pay", "Deductions", "Net pay");
    rows.push(["PER EMPLOYEE"], head);
    for (const p of r.people) {
      const row = [p.name, orDash(p.position), orDash(p.status), orDash(p.date_hired), p.scheduled, p.present, p.absent, p.on_leave, p.attendance_rate === null ? "—" : `${p.attendance_rate}%`, p.late, p.late_minutes, p.undertime, p.missing_out, p.hours, p.overtime_hours, p.leave_days];
      if (r.with_pay) row.push(p.gross_pay, p.deductions, p.net_pay);
      rows.push(row);
    }
    if (!r.people.length) rows.push(["No employees yet"]);
    rows.push([]);
  }
  if (exp.sections.leave) {
    rows.push(["APPROVED LEAVE"], ["Name", "Type", "From", "To", "Days in this period", "Paid"]);
    for (const l of r.leave) rows.push([l.name, leaveLabel(l.type), l.start_date, l.end_date, l.days, yesNo(l.paid)]);
    if (!r.leave.length) rows.push(["No approved leave in this period"]);
    rows.push([]);
  }
  if (exp.sections.waiting) {
    const w = s.waiting || {};
    rows.push(["WAITING FOR A DECISION (as of today)"], ["Item", "Count"], ["Leave requests", w.leave ?? 0], ["Overtime requests", w.overtime ?? 0], ["Attendance to review", w.attendance ?? 0], ["Payroll waiting for Finance", w.payroll ?? 0]);
  }
  return rows;
}

async function downloadReport() {
  if (!exp.from || !exp.to) return (exp.error = "Choose both dates.");
  if (exp.from > exp.to) return (exp.error = "The start date is after the end date.");
  exp.busy = true;
  exp.error = "";
  try {
    const res = await api.get("hr/dashboard/export", { params: { from: exp.from, to: exp.to } });
    const csv = "﻿" + reportRows(res.data.data).map((row) => row.map(csvCell).join(",")).join("\r\n");
    const a = document.createElement("a");
    a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
    a.download = `hr-report-${exp.from}-to-${exp.to}.csv`;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    exportOpen.value = false;
  } catch (e) {
    exp.error = e?.response?.data?.errors?.from?.[0] || apiMessage(e, "Couldn't prepare the report. Please try again.");
  } finally {
    exp.busy = false;
  }
}
</script>

<style scoped>
.hd-date {
  align-self: center;
  font-size: 13px;
  color: var(--v-muted);
}
.fx-kpi.hero {
  background: linear-gradient(150deg, var(--bc-accent-soft) 0%, var(--v-surface) 60%);
  border-color: var(--bc-accent-muted);
}
.hd-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.hd-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.hd-meter {
  height: 6px;
  margin-top: 4px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.hd-meter span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--bc-accent);
}

/* Waiting */
.hd-waiting {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}
.hd-wait {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  color: inherit;
  text-decoration: none;
  transition: border-color 0.15s, transform 0.15s, box-shadow 0.15s;
}
a.hd-wait:hover {
  border-color: var(--v-border-strong);
  transform: translateY(-1px);
  box-shadow: 0 10px 24px -14px rgba(15, 23, 42, 0.35);
}
.hd-wait.quiet .fx-icon {
  opacity: 0.6;
}
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.hd-wait-label {
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-text-2);
}
.hd-wait strong {
  font-size: 20px;
  font-weight: 600;
  color: var(--v-text);
}
.hd-wait-sub {
  font-size: 12px;
  color: var(--v-muted);
}
.hd-go {
  display: inline-flex;
  color: var(--v-faint);
}
.hd-go :deep(svg) {
  width: 16px;
  height: 16px;
}

/* Grids */
.hd-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 16px;
  align-items: start;
  margin-bottom: 16px;
}
.hd-grid.two {
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}
.hd-grid > section {
  min-width: 0;
}

/* Lists */
.hd-list {
  list-style: none;
  margin: 0;
  padding: 0;
  transition: opacity 0.2s;
}
.hd-list.dim {
  opacity: 0.55;
}
.hd-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 2px;
  border-top: 1px solid var(--v-border);
}
.hd-row:first-child {
  border-top: 0;
}
.hd-row strong {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.hd-row .grow span {
  font-size: 12px;
  color: var(--v-muted);
}
.hd-row .sp-chip {
  flex: none;
}
:deep(.hd-avatar) {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 11px;
  object-fit: cover;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 12.5px;
  font-weight: 600;
}

/* Week chart */
.hd-week {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 8px;
  height: 190px;
}
.hd-day {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-width: 0;
}
.hd-day-n {
  font-size: 12px;
  font-weight: 600;
  color: var(--v-text-2);
}
.hd-day-bar {
  flex: 1;
  display: flex;
  align-items: flex-end;
  width: 100%;
  max-width: 34px;
  border-radius: 10px;
  overflow: hidden;
  background: var(--v-subtle);
}
.hd-day-bar span {
  display: block;
  width: 100%;
  border-radius: 10px;
  background: color-mix(in srgb, var(--bc-accent) 55%, transparent);
  transition: height 0.4s ease;
}
.hd-day.today .hd-day-bar span {
  background: var(--bc-accent);
}
.hd-day-l {
  font-size: 11.5px;
  color: var(--v-faint);
}
.hd-day.today .hd-day-l {
  font-weight: 600;
  color: var(--v-text);
}
.hd-week-note {
  margin: 14px 0 0;
}
.hd-sk-chart {
  height: 220px;
}

/* Payroll */
.hd-pay-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin: 0;
}
.hd-pay-grid div {
  padding: 14px 16px;
  border-radius: 14px;
  background: var(--v-subtle);
}
.hd-pay-grid .wide {
  grid-column: 1 / -1;
}
.hd-pay-grid dt {
  font-size: 12px;
  color: var(--v-muted);
}
.hd-pay-grid dd {
  margin: 2px 0 0;
  font-size: 17px;
  font-weight: 600;
  color: var(--v-text);
}

/* Export dialog (teleported, so these are global-safe names) */
.hx-body {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.hx-dates {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.hx-range {
  margin: -4px 0 0;
}
.hx-parts {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  border: 0;
}
.hx-parts legend {
  margin-bottom: 8px;
  padding: 0;
}
.hx-part {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  min-height: 44px;
  padding: 12px 14px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-surface);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.hx-part:hover {
  border-color: var(--v-border-strong);
}
.hx-part.on {
  border-color: var(--bc-accent-muted);
  background: var(--bc-accent-soft);
}
.hx-part input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.hx-part:has(input:focus-visible) {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.hx-tick {
  flex: none;
  display: grid;
  place-items: center;
  width: 20px;
  height: 20px;
  margin-top: 1px;
  border: 1.5px solid var(--v-border-strong);
  border-radius: 6px;
  color: transparent;
  transition: background 0.15s, border-color 0.15s;
}
.hx-tick :deep(svg) {
  width: 13px;
  height: 13px;
}
.hx-part.on .hx-tick {
  border-color: var(--bc-accent);
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.hx-part-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.hx-part-text strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.hx-part-text small {
  font-size: 12px;
  line-height: 1.45;
  color: var(--v-muted);
}

@media (max-width: 1100px) {
  .hd-grid,
  .hd-grid.two {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 640px) {
  .hd-date {
    display: none;
  }
  .hd-row .sp-chip.amber {
    display: none;
  }
}
/* Counts are short, so two cards fit side by side on a phone */
@media (max-width: 560px) {
  .fx-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  .fx-kpi {
    padding: 14px;
  }
}
</style>
