<template>
  <HrPage title="HR dashboard" subtitle="Who works here, who is in today, and what is waiting for a decision.">
    <template #actions>
      <span v-if="data" class="hd-date">{{ longDay(data.date) }}</span>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="load"><span v-html="ICONS.restore"></span>Refresh</button>
    </template>

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
import { computed, defineComponent, h, onMounted, ref } from "vue";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../components/vendor/finance/financeUi";
import { useAssignment } from "../../../composables/useAssignment";
import "../../../components/vendor/finance/finance.css";

const { canView } = useAssignment();
const canOpen = (module) => canView(module);

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
