<template>
  <ShopLayout section="staff" title="Payroll" subtitle="Pay worked out from attendance, leave and each person's rate. Approve it, then mark it paid once the money is sent.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-primary" :disabled="!data" @click="openCreate"><span v-html="ICONS.plus"></span>Create payroll</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <section class="fx-kpis" aria-label="Payroll summary">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <button type="button" class="sp-card fx-kpi pr-kpi" :class="{ on: status === 'pending' }" @click="status = status === 'pending' ? '' : 'pending'">
          <span class="fx-kpi-label"><i class="dot amber"></i>Waiting for approval</span>
          <strong>{{ money(data.totals.pending) }}</strong>
          <span class="fx-kpi-sub">{{ count("pending") }}</span>
        </button>
        <button type="button" class="sp-card fx-kpi pr-kpi" :class="{ on: status === 'approved' }" @click="status = status === 'approved' ? '' : 'approved'">
          <span class="fx-kpi-label"><i class="dot blue"></i>Approved, not paid yet</span>
          <strong>{{ money(data.totals.approved) }}</strong>
          <span class="fx-kpi-sub">{{ count("approved") }}</span>
        </button>
        <button type="button" class="sp-card fx-kpi pr-kpi" :class="{ on: status === 'paid' }" @click="status = status === 'paid' ? '' : 'paid'">
          <span class="fx-kpi-label"><i class="dot green"></i>Paid</span>
          <strong class="pos">{{ money(data.totals.paid) }}</strong>
          <span class="fx-kpi-sub">{{ count("paid") }}</span>
        </button>
        <button type="button" class="sp-card fx-kpi pr-kpi" :class="{ on: status === 'rejected' }" @click="status = status === 'rejected' ? '' : 'rejected'">
          <span class="fx-kpi-label"><i class="dot red"></i>Sent back</span>
          <strong>{{ data.counts.rejected ?? 0 }}</strong>
          <span class="fx-kpi-sub">Need fixing before they're paid</span>
        </button>
      </template>
    </section>

    <section class="sp-card">
      <div class="sp-toolbar pr-toolbar">
        <div class="sp-seg" role="tablist" aria-label="Status">
          <button v-for="t in STATUS_TABS" :key="t.key" type="button" role="tab" :class="{ on: status === t.key }" :aria-selected="status === t.key" @click="status = t.key">
            {{ t.label }} <span v-if="data" class="count">{{ t.key ? data.counts[t.key] ?? 0 : allCount }}</span>
          </button>
        </div>
        <div class="pr-period">
          <select v-model="period" class="sp-select" aria-label="Period" @change="setPeriod">
            <option value="this_month">This month</option>
            <option value="last_month">Last month</option>
            <option value="this_year">This year</option>
            <option value="last_year">Last year</option>
            <option value="custom">Custom</option>
          </select>
          <template v-if="period === 'custom'">
            <input v-model="from" type="date" class="sp-input pr-date" aria-label="From" :max="to" @change="load" />
            <input v-model="to" type="date" class="sp-input pr-date" aria-label="To" :min="from" @change="load" />
          </template>
        </div>
      </div>

      <Transition name="sp-fade">
        <div v-if="picked.length" class="pr-bulk">
          <span>{{ picked.length }} selected · {{ money(pickedTotal) }}</span>
          <div>
            <button v-if="pickedAll('pending')" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="ask('approve', pickedRows)">Approve</button>
            <button v-if="pickedAll('pending')" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="ask('reject', pickedRows)">Send back</button>
            <button v-if="pickedAll('approved')" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="ask('paid', pickedRows)">Mark as paid</button>
            <button type="button" class="st-link" @click="picked = []">Clear</button>
          </div>
        </div>
      </Transition>

      <div v-if="!data" class="pr-sk"><div v-for="n in 4" :key="n" class="bc-skeleton sp-sk-block"></div></div>
      <div v-else-if="!rows.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.card"></span>
        <h3>{{ data.records.length || status ? "Nothing here" : "No payroll in this period" }}</h3>
        <p>{{ status ? "No payroll with this status in this period." : data.profiles.length ? "Create a payroll to work out someone's pay from their attendance." : "Payroll needs an HR profile with a salary. Your HR staff add those in the ERP." }}</p>
      </div>
      <div v-else class="st-scroll">
        <table class="st-table cards pr-table">
          <colgroup>
            <col style="width: 40px" />
            <col style="width: 26%" />
            <col style="width: 18%" />
            <col style="width: 11%" />
            <col style="width: 13%" />
            <col style="width: 14%" />
            <col style="width: 18%" />
          </colgroup>
          <thead>
            <tr>
              <th><input type="checkbox" :checked="allPicked" :indeterminate.prop="picked.length && !allPicked" :disabled="!actionable.length" aria-label="Select all" @change="toggleAll($event.target.checked)" /></th>
              <th>Person</th><th>Period</th><th class="num">Days</th><th class="num">Deductions</th><th class="num">Net pay</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id" class="clickable" @click="detail = r">
              <td @click.stop>
                <input v-if="r.status === 'pending' || r.status === 'approved'" v-model="picked" type="checkbox" :value="r.id" :aria-label="`Select ${r.employee}`" />
              </td>
              <td>
                <div class="st-person">
                  <span class="st-avatar sm" :style="{ background: personColor(r.employee) }">{{ initials(r.employee) }}</span>
                  <div><strong>{{ r.employee }}</strong><small>{{ r.position || "—" }}</small></div>
                </div>
              </td>
              <td data-label="Period">{{ periodText(r) }}</td>
              <td data-label="Days" class="num">{{ fmtNum(r.days_worked) }}<span class="muted"> / {{ fmtNum(r.expected_days) }}</span></td>
              <td data-label="Deductions" class="num" :class="{ muted: !r.deductions }">{{ r.deductions ? `−${money(r.deductions)}` : "—" }}</td>
              <td data-label="Net pay" class="num"><b>{{ money(r.net) }}</b></td>
              <td data-label="Status" @click.stop>
                <div class="pr-status">
                  <span class="sp-chip" :class="TONE[r.status]">{{ LABEL[r.status] }}</span>
                  <button v-if="r.status === 'pending'" type="button" class="st-link" @click="ask('approve', [r])">Approve</button>
                  <button v-else-if="r.status === 'approved'" type="button" class="st-link" @click="ask('paid', [r])">Mark paid</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Details -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="detail" class="sp-backdrop" @click="detail = null"></div></Transition>
      <Transition name="sp-slide">
        <aside v-if="detail" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="prd-title">
          <div class="sp-drawer-head">
            <div>
              <h3 id="prd-title">{{ detail.employee }}</h3>
              <p>{{ periodText(detail) }} · {{ RATE[detail.salary_type] ?? "Pay" }}</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="detail = null"><span v-html="ICONS.close"></span></button>
          </div>
          <div class="sp-drawer-body">
            <div class="pr-net">
              <span>Net pay</span>
              <strong>{{ money(detail.net) }}</strong>
              <span class="sp-chip" :class="TONE[detail.status]">{{ LABEL[detail.status] }}<template v-if="detail.paid_at"> · {{ formatDate(detail.paid_at) }}</template></span>
            </div>
            <h4 class="st-section-title">Attendance</h4>
            <dl class="st-dl">
              <dt>Days worked</dt><dd>{{ fmtNum(detail.days_worked) }} of {{ fmtNum(detail.expected_days) }}</dd>
              <dt>Hours</dt><dd>{{ hoursText(detail.hours) }}</dd>
              <dt>Paid leave</dt><dd>{{ fmtNum(detail.paid_leave_days) }} {{ detail.paid_leave_days === 1 ? "day" : "days" }}</dd>
              <dt>Absent</dt><dd>{{ fmtNum(detail.absent_days) }} {{ detail.absent_days === 1 ? "day" : "days" }}</dd>
            </dl>
            <h4 class="st-section-title">Pay</h4>
            <dl class="st-dl pr-money">
              <dt>Gross pay</dt><dd>{{ money(detail.gross) }}</dd>
              <dt>Absences &amp; unpaid leave</dt><dd>−{{ money(Math.max(0, detail.deductions - detail.contributions)) }}</dd>
              <dt>SSS, PhilHealth &amp; Pag-IBIG</dt><dd>{{ detail.contributions ? `−${money(detail.contributions)}` : "Not included" }}</dd>
              <dt class="total">Net pay</dt><dd class="total">{{ money(detail.net) }}</dd>
            </dl>
            <template v-if="detail.notes || detail.review_note">
              <h4 class="st-section-title">Notes</h4>
              <p v-if="detail.notes" class="pr-note">{{ detail.notes }}</p>
              <p v-if="detail.review_note" class="pr-note review"><b>{{ detail.status === "rejected" ? "Why it was sent back" : "Approval note" }}:</b> {{ detail.review_note }}</p>
            </template>
          </div>
          <div v-if="detail.status === 'pending' || detail.status === 'approved'" class="sp-drawer-foot">
            <template v-if="detail.status === 'pending'">
              <button type="button" class="sp-btn sp-btn-ghost" @click="ask('reject', [detail])">Send back</button>
              <button type="button" class="sp-btn sp-btn-primary" @click="ask('approve', [detail])">Approve</button>
            </template>
            <button v-else type="button" class="sp-btn sp-btn-primary" @click="ask('paid', [detail])">Mark as paid</button>
          </div>
        </aside>
      </Transition>
    </Teleport>

    <!-- Approve / send back / paid -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="confirm" class="sp-backdrop" @click="closeConfirm"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="confirm" class="sp-modal-wrap">
          <form class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="prc-title" @submit.prevent="runAction">
            <div class="sp-modal-head">
              <div>
                <h3 id="prc-title">{{ ACTION[confirm.action].title(confirm.rows.length) }}</h3>
                <p>{{ confirm.rows.length === 1 ? `${confirm.rows[0].employee} · ${periodText(confirm.rows[0])}` : `${confirm.rows.length} payrolls` }} · {{ money(confirm.rows.reduce((n, r) => n + r.net, 0)) }}</p>
              </div>
            </div>
            <p class="sp-hint">{{ ACTION[confirm.action].hint }}</p>
            <label v-if="confirm.action !== 'paid'" class="sp-field">
              <span>{{ confirm.action === "reject" ? "What needs fixing?" : "Note" }} <small v-if="confirm.action !== 'reject'">(optional)</small></span>
              <textarea v-model="confirm.note" class="sp-textarea" rows="2" maxlength="1000" :class="{ invalid: confirm.error && confirm.action === 'reject' }"></textarea>
            </label>
            <p v-if="confirm.error" class="sp-error">{{ confirm.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="closeConfirm">Cancel</button>
              <button type="submit" class="sp-btn" :class="confirm.action === 'reject' ? 'sp-btn-danger' : 'sp-btn-primary'" :disabled="saving">{{ saving ? "Saving…" : ACTION[confirm.action].button }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Create -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="create" class="sp-backdrop" @click="closeCreate"></div></Transition>
      <Transition name="sp-slide">
        <form v-if="create" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="prn-title" novalidate @submit.prevent="submitCreate">
          <div class="sp-drawer-head">
            <div>
              <h3 id="prn-title">Create payroll</h3>
              <p>Worked out from their attendance, leave and rate. It waits for your approval before it's paid.</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeCreate"><span v-html="ICONS.close"></span></button>
          </div>
          <div class="sp-drawer-body">
            <div class="sp-grid-2">
              <label class="sp-field sp-span-all">
                <span>Person</span>
                <select v-model="create.employee_id" class="sp-select" :class="{ invalid: create.errors.employee_id }" @change="preview">
                  <option :value="null" disabled>Choose…</option>
                  <option v-for="p in data.profiles" :key="p.id" :value="p.id" :disabled="!p.ready">
                    {{ p.name }}{{ p.ready ? ` · ${money(p.salary)} ${PER[p.salary_type] ?? ""}` : " · no salary set" }}
                  </option>
                </select>
                <small v-if="create.errors.employee_id" class="sp-error">{{ create.errors.employee_id }}</small>
              </label>
              <label class="sp-field">
                <span>From</span>
                <input v-model="create.period_start" type="date" class="sp-input" @change="preview" />
              </label>
              <label class="sp-field">
                <span>To</span>
                <input v-model="create.period_end" type="date" class="sp-input" :class="{ invalid: create.errors.period_end }" :min="create.period_start" @change="preview" />
                <small v-if="create.errors.period_end" class="sp-error">{{ create.errors.period_end }}</small>
              </label>
              <div class="pr-quick sp-span-all">
                <button v-for="q in QUICK" :key="q.key" type="button" class="sp-pick" @click="quick(q.key)">{{ q.label }}</button>
              </div>
              <label class="pr-switch sp-span-all">
                <span class="sp-switch"><input v-model="create.include_contributions" type="checkbox" @change="preview" /><span></span></span>
                <span><b>Deduct SSS, PhilHealth and Pag-IBIG</b><small>The employee's share, worked out from their monthly pay.</small></span>
              </label>
              <label class="sp-field sp-span-all">
                <span>Note <small>(optional)</small></span>
                <textarea v-model="create.notes" class="sp-textarea" rows="2" maxlength="1000"></textarea>
              </label>
            </div>

            <h4 class="st-section-title">Preview</h4>
            <p v-if="!create.employee_id" class="sp-hint">Choose a person to see their pay.</p>
            <div v-else-if="create.loading" class="bc-skeleton sp-sk-block"></div>
            <p v-else-if="create.previewError" class="sp-banner warn"><span v-html="ICONS.alert"></span>{{ create.previewError }}</p>
            <template v-else-if="create.result">
              <dl class="st-dl">
                <dt>Days worked</dt><dd>{{ create.result.actual_work_days }} of {{ create.result.expected_work_days }}</dd>
                <dt>Hours</dt><dd>{{ create.result.total_hours_worked }}</dd>
                <dt>Paid leave</dt><dd>{{ create.result.paid_leave_days }} {{ Number(create.result.paid_leave_days) === 1 ? "day" : "days" }}</dd>
                <dt>Absent</dt><dd>{{ create.result.absent_days }} {{ Number(create.result.absent_days) === 1 ? "day" : "days" }}</dd>
              </dl>
              <dl class="st-dl pr-money pr-gap">
                <dt>Gross pay</dt><dd>₱{{ create.result.gross_salary }}</dd>
                <dt>Absences &amp; unpaid leave</dt><dd>−₱{{ minus(create.result.absent_deduction, create.result.unpaid_leave_deduction) }}</dd>
                <dt>SSS, PhilHealth &amp; Pag-IBIG</dt><dd>{{ create.include_contributions ? `−₱${create.result.total_contributions}` : "Not included" }}</dd>
                <dt class="total">Net pay</dt><dd class="total">₱{{ create.result.net_salary }}</dd>
              </dl>
            </template>
            <p v-if="create.error" class="sp-error">{{ create.error }}</p>
          </div>
          <div class="sp-drawer-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="closeCreate">Cancel</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="saving || !create.result">{{ saving ? "Creating…" : "Create payroll" }}</button>
          </div>
        </form>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { formatDate, money, ymd } from "../../../components/vendor/finance/financeUi";
import { hoursText, personColor } from "../../../components/vendor/staff/staffUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/finance/finance.css";
import "../../../components/vendor/staff/staff.css";

const STATUS_TABS = [
  { key: "", label: "All" },
  { key: "pending", label: "Waiting" },
  { key: "approved", label: "Approved" },
  { key: "paid", label: "Paid" },
  { key: "rejected", label: "Sent back" },
];
const LABEL = { pending: "Waiting for approval", approved: "Approved", paid: "Paid", rejected: "Sent back" };
const TONE = { pending: "amber", approved: "blue", paid: "green", rejected: "red" };
const PER = { daily: "a day", weekly: "a week", monthly: "a month", hourly: "an hour" };
const RATE = { daily: "Daily rate", weekly: "Weekly rate", monthly: "Monthly salary", hourly: "Hourly rate" };
const QUICK = [
  { key: "first_half", label: "1st–15th" },
  { key: "second_half", label: "16th–end" },
  { key: "this_month", label: "This month" },
  { key: "last_month", label: "Last month" },
  { key: "this_week", label: "This week" },
];
const ACTION = {
  approve: { title: (n) => (n === 1 ? "Approve this payroll?" : `Approve ${n} payrolls?`), hint: "Approved payroll counts as a cost in Profit & Loss and is ready to pay. Mark it paid once the money is sent.", button: "Approve" },
  reject: { title: (n) => (n === 1 ? "Send this payroll back?" : `Send ${n} payrolls back?`), hint: "Whoever made it can fix it and create it again.", button: "Send back" },
  paid: { title: (n) => (n === 1 ? "Mark as paid?" : `Mark ${n} payrolls as paid?`), hint: "Only do this after the money has been sent to them.", button: "Mark as paid" },
};

const data = ref(null);
const error = ref("");
const status = ref("");
const period = ref("this_year");
const from = ref("");
const to = ref("");
const picked = ref([]);
const detail = ref(null);
const confirm = ref(null);
const create = ref(null);
const saving = ref(false);

const rows = computed(() => (data.value?.records ?? []).filter((r) => !status.value || r.status === status.value));
const allCount = computed(() => Object.values(data.value?.counts ?? {}).reduce((a, b) => a + b, 0));
const actionable = computed(() => rows.value.filter((r) => r.status === "pending" || r.status === "approved"));
const allPicked = computed(() => actionable.value.length > 0 && actionable.value.every((r) => picked.value.includes(r.id)));
const pickedRows = computed(() => (data.value?.records ?? []).filter((r) => picked.value.includes(r.id)));
const pickedTotal = computed(() => pickedRows.value.reduce((n, r) => n + r.net, 0));
const pickedAll = (s) => pickedRows.value.length > 0 && pickedRows.value.every((r) => r.status === s);

watch(status, () => (picked.value = []));

const count = (s) => {
  const n = data.value?.counts?.[s] ?? 0;
  return `${n} ${n === 1 ? "payroll" : "payrolls"}`;
};
const fmtNum = (n) => (Number.isInteger(Number(n)) ? Number(n) : Number(n).toFixed(1));
const initials = (name) => String(name).split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase();
const minus = (a, b) => (parseFloat(String(a).replace(/,/g, "")) + parseFloat(String(b).replace(/,/g, ""))).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function periodText(r) {
  const s = new Date(`${r.period_start}T00:00:00`);
  const e = new Date(`${r.period_end}T00:00:00`);
  if (r.period_start === r.period_end) return s.toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
  const sameMonth = s.getMonth() === e.getMonth() && s.getFullYear() === e.getFullYear();
  return sameMonth
    ? `${s.toLocaleDateString("en-PH", { month: "short", day: "numeric" })}–${e.getDate()}, ${e.getFullYear()}`
    : `${s.toLocaleDateString("en-PH", { month: "short", day: "numeric" })} – ${e.toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" })}`;
}

function rangeFor(key) {
  const now = new Date();
  const y = now.getFullYear();
  const m = now.getMonth();
  switch (key) {
    case "this_month":
      return [ymd(new Date(y, m, 1)), ymd(new Date(y, m + 1, 0))];
    case "last_month":
      return [ymd(new Date(y, m - 1, 1)), ymd(new Date(y, m, 0))];
    case "last_year":
      return [ymd(new Date(y - 1, 0, 1)), ymd(new Date(y - 1, 11, 31))];
    default:
      return [ymd(new Date(y, 0, 1)), ymd(new Date(y, 11, 31))];
  }
}
function setPeriod() {
  if (period.value !== "custom") [from.value, to.value] = rangeFor(period.value);
  load();
}

async function load() {
  error.value = "";
  if (from.value > to.value) return;
  try {
    const res = await api.get("vendor/staff/payroll", { params: { from: from.value, to: to.value } });
    data.value = res.data.data;
    picked.value = picked.value.filter((id) => data.value.records.some((r) => r.id === id && (r.status === "pending" || r.status === "approved")));
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load payroll.");
  }
}

function toggleAll(on) {
  picked.value = on ? actionable.value.map((r) => r.id) : [];
}

function ask(action, list) {
  // Only rows that can take this action
  const want = action === "paid" ? "approved" : "pending";
  confirm.value = { action, rows: list.filter((r) => r.status === want), note: "", error: "" };
}
function closeConfirm() {
  if (!saving.value) confirm.value = null;
}
async function runAction() {
  const c = confirm.value;
  if (c.action === "reject" && !c.note.trim()) {
    c.error = "Say what needs fixing.";
    return;
  }
  saving.value = true;
  c.error = "";
  try {
    const { data: res } = await api.post(`vendor/staff/payroll/${c.action}`, { ids: c.rows.map((r) => r.id), note: c.note.trim() || null });
    toast.success(res.message);
    confirm.value = null;
    detail.value = null;
    picked.value = [];
    await load();
  } catch (e) {
    c.error = apiMessage(e, "That didn't work.");
  } finally {
    saving.value = false;
  }
}

function openCreate() {
  const [s, e] = periodFor("first_half");
  create.value = { employee_id: null, period_start: s, period_end: e, include_contributions: false, notes: "", result: null, loading: false, previewError: "", error: "", errors: {} };
}
function closeCreate() {
  if (!saving.value) create.value = null;
}
function periodFor(key) {
  const now = new Date();
  const y = now.getFullYear();
  const m = now.getMonth();
  switch (key) {
    case "first_half":
      return now.getDate() > 15 ? [ymd(new Date(y, m, 1)), ymd(new Date(y, m, 15))] : [ymd(new Date(y, m - 1, 16)), ymd(new Date(y, m, 0))];
    case "second_half":
      return now.getDate() > 15 ? [ymd(new Date(y, m, 16)), ymd(new Date(y, m + 1, 0))] : [ymd(new Date(y, m - 1, 16)), ymd(new Date(y, m, 0))];
    case "last_month":
      return [ymd(new Date(y, m - 1, 1)), ymd(new Date(y, m, 0))];
    case "this_week": {
      const mon = new Date(now);
      mon.setDate(now.getDate() - ((now.getDay() + 6) % 7));
      const sun = new Date(mon);
      sun.setDate(mon.getDate() + 6);
      return [ymd(mon), ymd(sun)];
    }
    default:
      return [ymd(new Date(y, m, 1)), ymd(new Date(y, m + 1, 0))];
  }
}
function quick(key) {
  [create.value.period_start, create.value.period_end] = periodFor(key);
  preview();
}

let seq = 0;
async function preview() {
  const c = create.value;
  c.errors = {};
  c.error = "";
  c.previewError = "";
  c.result = null;
  if (!c.employee_id || !c.period_start || !c.period_end) return;
  if (c.period_end < c.period_start) {
    c.errors.period_end = "The period must end on or after its start.";
    return;
  }
  const mine = ++seq;
  c.loading = true;
  try {
    const { data: res } = await api.post("vendor/staff/payroll/preview", {
      employee_id: c.employee_id, period_start: c.period_start, period_end: c.period_end, include_contributions: c.include_contributions,
    });
    if (mine === seq && create.value) c.result = res.data;
  } catch (e) {
    if (mine === seq && create.value) c.previewError = apiMessage(e, "Couldn't work out the pay.");
  } finally {
    if (mine === seq && create.value) c.loading = false;
  }
}

async function submitCreate() {
  const c = create.value;
  if (!c.result || saving.value) return;
  saving.value = true;
  c.error = "";
  try {
    const { data: res } = await api.post("vendor/staff/payroll", {
      employee_id: c.employee_id, period_start: c.period_start, period_end: c.period_end, include_contributions: c.include_contributions, notes: c.notes.trim() || null,
    });
    toast.success(res.message);
    create.value = null;
    status.value = "";
    await load();
  } catch (e) {
    c.error = apiMessage(e, "Couldn't create the payroll.");
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  [from.value, to.value] = rangeFor("this_year");
  load();
});
</script>

<style scoped>
.pr-kpi {
  border: 1px solid var(--v-border);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.pr-kpi:hover,
.pr-kpi.on {
  border-color: var(--bc-accent-border);
  box-shadow: 0 4px 14px color-mix(in srgb, var(--bc-accent) 12%, transparent);
}
.pr-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 10px;
}
.pr-period {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.pr-period .sp-select {
  width: auto;
  min-width: 140px;
}
.pr-date {
  width: 150px;
}
.pr-bulk {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 10px;
  padding: 10px 14px;
  border-radius: 12px;
  background: var(--bc-accent-soft);
  font-size: 13px;
  font-weight: 500;
  color: var(--bc-accent-strong);
}
.pr-bulk div {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.pr-sk {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.pr-table input[type="checkbox"] {
  accent-color: var(--bc-accent);
}
.pr-status {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px 10px;
}
.pr-net {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  padding: 16px;
  border-radius: 14px;
  background: var(--v-subtle);
}
.pr-net > span:first-child {
  font-size: 12.5px;
  color: var(--v-muted);
}
.pr-net strong {
  font-size: 26px;
  font-weight: 600;
  letter-spacing: -0.01em;
}
.pr-money dd {
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.pr-money .total {
  padding-top: 10px;
  border-top: 1px solid var(--v-border);
  font-weight: 600;
  color: var(--v-text);
}
.pr-gap {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px dashed var(--v-border-strong);
}
.pr-note {
  margin: 0 0 8px;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--v-subtle);
  font-size: 13px;
  color: var(--v-text);
  overflow-wrap: anywhere;
}
.pr-note.review {
  background: var(--v-amber-soft);
}
.pr-quick {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: -4px;
}
.pr-switch {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  cursor: pointer;
}
.pr-switch b {
  display: block;
  font-size: 13px;
  font-weight: 600;
}
.pr-switch small {
  font-size: 12px;
  color: var(--v-muted);
}
</style>
