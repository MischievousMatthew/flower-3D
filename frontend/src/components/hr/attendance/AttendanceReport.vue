<template>
  <div class="ar">
    <section class="sp-card">
      <div class="sp-toolbar ar-toolbar">
        <div class="sp-seg" role="tablist" aria-label="Period">
          <button v-for="p in PERIODS" :key="p.key" type="button" role="tab" :class="{ on: period === p.key }" :aria-selected="period === p.key" @click="setPeriod(p.key)">{{ p.label }}</button>
        </div>
        <div class="ar-filters">
          <template v-if="period === 'custom'">
            <input v-model="from" type="date" class="sp-input" aria-label="From" :max="to" @change="load" />
            <input v-model="to" type="date" class="sp-input" aria-label="To" :min="from" :max="today" @change="load" />
          </template>
          <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="!report" @click="download('people')"><span v-html="ICONS.download"></span>Summary CSV</button>
          <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="!report" @click="download('days')"><span v-html="ICONS.download"></span>Daily CSV</button>
        </div>
      </div>
      <p class="st-sub ar-range">{{ rangeLabel }}<template v-if="report"> · late after {{ report.grace_minutes }} min grace</template></p>

      <p v-if="error" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ error }}<button type="button" @click="load">Try again</button></p>
      <div v-else-if="!report" class="ar-sk">
        <div v-for="n in 5" :key="n" class="bc-skeleton sp-sk-line" :class="n % 2 ? 'w70' : 'w50'"></div>
      </div>
      <div v-else-if="!report.people.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.chart"></span>
        <h3>No one to report on</h3>
        <p>Add HR profiles with usual hours to see attendance here.</p>
      </div>
      <template v-else>
        <dl class="ar-totals">
          <div><dt>Attendance rate</dt><dd>{{ rate(report.totals) }}</dd></div>
          <div><dt>Hours worked</dt><dd>{{ hoursText(report.totals.hours) }}</dd></div>
          <div><dt>Late arrivals</dt><dd>{{ report.totals.late }} <small>({{ minutesText(report.totals.late_minutes) }})</small></dd></div>
          <div><dt>Left early</dt><dd>{{ report.totals.undertime }} <small>({{ minutesText(report.totals.undertime_minutes) }})</small></dd></div>
          <div><dt>Absences</dt><dd>{{ report.totals.absent }}</dd></div>
          <div><dt>Overtime approved</dt><dd>{{ minutesText(report.totals.overtime_approved_minutes ?? 0) }} <small v-if="report.totals.overtime_pending_minutes">({{ minutesText(report.totals.overtime_pending_minutes) }} waiting)</small></dd></div>
          <div><dt>Needs review</dt><dd>{{ report.totals.needs_review }}</dd></div>
        </dl>
        <div class="st-scroll">
          <table class="st-table cards">
            <thead>
              <tr><th>Person</th><th>Rate</th><th>Present</th><th>Absent</th><th>On leave</th><th>Late</th><th>Left early</th><th>No time out</th><th>Overtime</th><th>Hours</th></tr>
            </thead>
            <tbody>
              <tr v-for="p in report.people" :key="p.employee_id">
                <td>
                  <div class="st-person">
                    <span class="st-avatar sm" :style="{ background: personColor(p.name) }">{{ initialsOf(p.name) }}</span>
                    <div><strong>{{ p.name }}</strong><small>{{ p.position || "—" }}</small></div>
                  </div>
                </td>
                <td data-label="Rate">
                  <div class="ar-rate">
                    <span class="ar-meter"><i :style="{ width: rate(p) }" :class="rateTone(p)"></i></span>{{ rate(p) }}
                  </div>
                </td>
                <td data-label="Present">{{ p.present }} / {{ p.scheduled }}</td>
                <td data-label="Absent"><span :class="{ 'ar-bad': p.absent }">{{ p.absent }}</span></td>
                <td data-label="On leave">{{ p.on_leave }}</td>
                <td data-label="Late">{{ p.late }}<small v-if="p.late_minutes" class="ar-sub"> · {{ minutesText(p.late_minutes) }}</small></td>
                <td data-label="Left early">{{ p.undertime }}<small v-if="p.undertime_minutes" class="ar-sub"> · {{ minutesText(p.undertime_minutes) }}</small></td>
                <td data-label="No time out">{{ p.missing_out }}</td>
                <td data-label="Overtime">
                  {{ p.overtime_approved_minutes ? minutesText(p.overtime_approved_minutes) : "—" }}
                  <small v-if="p.overtime_pending_minutes" class="ar-sub"> · {{ minutesText(p.overtime_pending_minutes) }} waiting</small>
                </td>
                <td data-label="Hours">{{ hoursText(p.hours) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { SHOP_ICONS as ICONS } from "../../vendor/shop/shopIcons";
import { formatDate, ymd } from "../../vendor/finance/financeUi";
import { hoursText, personColor } from "../../vendor/staff/staffUi";
import { initialsOf } from "../hrUi";

const props = defineProps({
  // (from, to) => report data, from the vendor or ERP endpoint
  fetch: { type: Function, required: true },
});

const PERIODS = [
  { key: "week", label: "This week" },
  { key: "month", label: "This month" },
  { key: "last", label: "Last month" },
  { key: "custom", label: "Custom" },
];
const today = ymd(new Date());

const report = ref(null);
const error = ref("");
const period = ref("month");
const from = ref("");
const to = ref("");

const rangeLabel = computed(() => (from.value && to.value ? `${formatDate(from.value)} – ${formatDate(to.value)}` : ""));
const rate = (p) => (p.scheduled ? `${Math.round((Math.min(p.present, p.scheduled) / p.scheduled) * 100)}%` : "—");
const rateTone = (p) => {
  const r = p.scheduled ? p.present / p.scheduled : 1;
  return r >= 0.95 ? "good" : r >= 0.8 ? "ok" : "bad";
};
const minutesText = (m) => (!m ? "0 min" : m >= 60 ? `${Math.floor(m / 60)} h${m % 60 ? ` ${m % 60} min` : ""}` : `${m} min`);

function setPeriod(key) {
  period.value = key;
  const now = new Date();
  if (key === "week") {
    const start = new Date(now);
    start.setDate(now.getDate() - ((now.getDay() + 6) % 7));
    from.value = ymd(start);
    to.value = today;
  } else if (key === "month") {
    from.value = ymd(new Date(now.getFullYear(), now.getMonth(), 1));
    to.value = today;
  } else if (key === "last") {
    from.value = ymd(new Date(now.getFullYear(), now.getMonth() - 1, 1));
    to.value = ymd(new Date(now.getFullYear(), now.getMonth(), 0));
  }
  load();
}

async function load() {
  error.value = "";
  report.value = null;
  try {
    report.value = await props.fetch(from.value, to.value);
  } catch (e) {
    error.value = e?.response?.data?.errors?.from?.[0] || e?.response?.data?.message || "Couldn't load the report.";
  }
}

const csvCell = (v) => {
  const s = v === null || v === undefined ? "" : String(v);
  // Quote everything; neutralise spreadsheet formulas
  return `"${(/^[=+\-@]/.test(s) ? `'${s}` : s).replace(/"/g, '""')}"`;
};

function download(kind) {
  const r = report.value;
  const rows = kind === "people"
    ? [
        ["Name", "Position", "Scheduled days", "Present", "Absent", "On leave", "Late (times)", "Late (minutes)", "Left early (times)", "Left early (minutes)", "No time out", "Overtime approved (minutes)", "Overtime waiting (minutes)", "Hours", "Needs review"],
        ...r.people.map((p) => [p.name, p.position, p.scheduled, p.present, p.absent, p.on_leave, p.late, p.late_minutes, p.undertime, p.undertime_minutes, p.missing_out, p.overtime_approved_minutes, p.overtime_pending_minutes, p.hours, p.needs_review]),
      ]
    : [
        ["Date", "Name", "Scheduled", "Time in", "Time out", "Hours", "Status", "Late (minutes)", "Left early (minutes)", "Overtime (minutes)", "Overtime status", "Overtime approved (minutes)", "Review"],
        ...[...r.days].reverse().map((d) => [d.date, d.employee, d.expected, d.time_in, d.time_out, d.hours, d.status, d.late_minutes, d.undertime_minutes, d.overtime_minutes, d.overtime_status, d.overtime_approved_minutes, d.review_status]),
      ];
  const blob = new Blob(["﻿" + rows.map((row) => row.map(csvCell).join(",")).join("\r\n")], { type: "text/csv;charset=utf-8" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = `attendance-${kind === "people" ? "summary" : "daily"}-${from.value}-to-${to.value}.csv`;
  a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

onMounted(() => setPeriod("month"));
</script>

<style scoped>
.ar-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}
.ar-filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.ar-filters .sp-input {
  width: auto;
}
.ar-range {
  margin: 8px 0 14px;
}
.ar-sk {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.ar-totals {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 10px;
  margin: 0 0 16px;
}
.ar-totals div {
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--v-subtle);
}
.ar-totals dt {
  font-size: 11.5px;
  color: var(--v-muted);
}
.ar-totals dd {
  margin: 4px 0 0;
  font-size: 18px;
  font-weight: 600;
  color: var(--v-text);
}
.ar-totals small {
  font-size: 12px;
  font-weight: 400;
  color: var(--v-muted);
}
.ar-rate {
  display: flex;
  align-items: center;
  gap: 8px;
  font-variant-numeric: tabular-nums;
}
.ar-meter {
  flex: none;
  width: 56px;
  height: 6px;
  border-radius: 999px;
  background: var(--v-subtle-2);
  overflow: hidden;
}
.ar-meter i {
  display: block;
  height: 100%;
  border-radius: inherit;
}
.ar-meter i.good { background: #48bb78; }
.ar-meter i.ok { background: #ed8936; }
.ar-meter i.bad { background: #e53e3e; }
.ar-bad {
  font-weight: 600;
  color: var(--v-red-text);
}
.ar-sub {
  color: var(--v-muted);
}
</style>
