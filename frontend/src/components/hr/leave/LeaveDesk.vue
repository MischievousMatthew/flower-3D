<template>
  <div class="ld">
    <div class="sp-seg ld-tabs" role="tablist" aria-label="Leave sections">
      <button v-for="t in tabs" :key="t.key" type="button" role="tab" :class="{ on: tab === t.key }" :aria-selected="tab === t.key" @click="setTab(t.key)">
        {{ t.label }}<span v-if="t.count" class="count">{{ t.count }}</span>
      </button>
    </div>

    <!-- ── Requests ── -->
    <template v-if="tab === 'requests'">
      <section class="sp-stats ld-stats" aria-label="Leave summary">
        <template v-if="!stats">
          <div v-for="n in 4" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
        </template>
        <template v-else>
          <button type="button" class="sp-stat" :class="{ on: filters.status === 'pending' }" @click="filters.status = 'pending'">
            <span class="sp-stat-icon tone-amber" v-html="ICONS.clock"></span>
            <div><strong>{{ stats.pending }}</strong><span>Waiting for you</span></div>
          </button>
          <div class="sp-stat">
            <span class="sp-stat-icon tone-blue" v-html="ICONS.user"></span>
            <div><strong>{{ stats.on_leave_today }}</strong><span>On leave today</span></div>
          </div>
          <div class="sp-stat">
            <span class="sp-stat-icon tone-green" v-html="ICONS.check"></span>
            <div><strong>{{ fmt(stats.total_days_this_month) }}</strong><span>Leave days approved this month</span></div>
          </div>
          <button type="button" class="sp-stat" @click="setTab('overtime')">
            <span class="sp-stat-icon tone-violet" v-html="ICONS.history"></span>
            <div><strong>{{ stats.overtime_pending }}</strong><span>Overtime requests waiting</span></div>
          </button>
        </template>
      </section>

      <section class="sp-card">
        <div class="sp-toolbar ld-toolbar">
          <div class="sp-seg" role="tablist" aria-label="Status">
            <button v-for="s in STATUS_FILTERS" :key="s.key" type="button" role="tab" :class="{ on: filters.status === s.key }" @click="filters.status = s.key">{{ s.label }}</button>
          </div>
          <div class="ld-filters">
            <label class="sp-search"><span v-html="ICONS.search"></span><input v-model="search" class="sp-input" type="search" placeholder="Find someone" aria-label="Find someone" /></label>
            <select v-model="filters.leave_type" class="sp-select" aria-label="Kind of leave">
              <option value="">Every kind</option>
              <option v-for="p in policies" :key="p.leave_type" :value="p.leave_type">{{ p.label }}</option>
            </select>
            <select v-model.number="filters.year" class="sp-select" aria-label="Year">
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
            <button v-if="perms.create" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="openFile('leave')"><span v-html="ICONS.plus"></span>File leave</button>
          </div>
        </div>

        <p v-if="error" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ error }}<button type="button" @click="loadLeaves">Try again</button></p>
        <ul v-else-if="!leaves" class="ld-sk"><li v-for="n in 5" :key="n"><div class="bc-skeleton st-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li></ul>
        <div v-else-if="!shownLeaves.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.calendar"></span>
          <h3>{{ filters.status === "pending" ? "Nothing waiting" : "No leave requests" }}</h3>
          <p>Staff file leave from the shop's leave page — put the QR poster up from the "QR poster" tab.</p>
        </div>
        <ul v-else class="ld-list">
          <li v-for="l in shownLeaves" :key="l.id">
            <button type="button" class="ld-row" @click="openLeave(l)">
              <span class="st-avatar" :style="{ background: personColor(l.employee) }">{{ initialsOf(l.employee) }}</span>
              <span class="ld-main">
                <b>{{ l.employee }}</b>
                <small>{{ l.label }} · {{ dateRange(l.start_date, l.end_date) }} · {{ daysText(l.days) }}{{ l.is_paid ? "" : " · unpaid" }}</small>
              </span>
              <span class="ld-tags">
                <span v-if="l.document" class="ld-doc" title="Has a document" v-html="ICONS.clip"></span>
                <span v-if="!l.badge_verified" class="sp-chip amber" title="Filed with an old badge">Old badge</span>
                <span class="sp-chip" :class="LEAVE_STATUS[l.status].tone">{{ LEAVE_STATUS[l.status].label }}</span>
              </span>
            </button>
          </li>
        </ul>
      </section>
    </template>

    <!-- ── Overtime requests ── -->
    <section v-else-if="tab === 'overtime'" class="sp-card">
      <div class="sp-toolbar ld-toolbar">
        <div class="sp-seg" role="tablist" aria-label="Status">
          <button v-for="s in STATUS_FILTERS" :key="s.key" type="button" role="tab" :class="{ on: otStatus === s.key }" @click="otStatus = s.key">{{ s.label }}</button>
        </div>
        <button v-if="perms.create" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="openFile('overtime')"><span v-html="ICONS.plus"></span>File overtime</button>
      </div>
      <p class="sp-hint ld-hint">Overtime asked for ahead of time. Once approved, staying late that day up to the approved time is approved automatically at time-out. Overtime nobody asked for shows up in Attendance → Review.</p>
      <ul v-if="!overtime" class="ld-sk"><li v-for="n in 3" :key="n"><div class="bc-skeleton st-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div></div></li></ul>
      <div v-else-if="!shownOvertime.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.history"></span>
        <h3>No overtime requests</h3>
        <p>Staff ask for overtime from the shop's leave page.</p>
      </div>
      <article v-for="o in shownOvertime" :key="o.id" class="ld-ot">
        <header>
          <span class="st-avatar" :style="{ background: personColor(o.employee) }">{{ initialsOf(o.employee) }}</span>
          <div class="ld-main">
            <b>{{ o.employee }}</b>
            <small>{{ dateRange(o.date, o.date) }} · {{ durationText(o.minutes) }} after the shift · filed by {{ o.filed_by_name || "them" }}</small>
          </div>
          <span class="sp-chip" :class="LEAVE_STATUS[o.status].tone">{{ LEAVE_STATUS[o.status].label }}</span>
        </header>
        <p class="ld-reason">“{{ o.reason }}”</p>
        <p v-if="o.review_note" class="ld-review"><b>{{ o.reviewer }}:</b> {{ o.review_note }}</p>
        <div v-if="o.status === 'pending'" class="ld-decide">
          <input v-model="notes[`o${o.id}`]" class="sp-input" maxlength="1000" placeholder="Note (needed to decline)" :aria-label="`Note for ${o.employee}`" />
          <button v-if="perms.edit" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busy" @click="cancelOvertime(o)">Cancel</button>
          <button v-if="perms.reject" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busy" @click="decideOvertime(o, 'rejected')">Decline</button>
          <button v-if="perms.approve" type="button" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="busy" @click="decideOvertime(o, 'approved')">Approve</button>
        </div>
      </article>
    </section>

    <!-- ── Balances ── -->
    <section v-else-if="tab === 'balances'" class="sp-card">
      <div class="sp-toolbar ld-toolbar">
        <p class="sp-hint ld-hint">Days left this year for each kind of leave. Waiting requests already count.{{ perms.edit ? " Click a number to add or take away days." : "" }}</p>
        <select v-model.number="balanceYear" class="sp-select ld-year" aria-label="Year" @change="loadBalances">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div v-if="!balances" class="ld-sk-block"><div v-for="n in 4" :key="n" class="bc-skeleton sp-sk-line w70"></div></div>
      <div v-else-if="!balances.people.length" class="sp-empty"><h3>No one yet</h3><p>Add HR profiles to track leave.</p></div>
      <div v-else class="st-scroll">
        <table class="st-table ld-bal">
          <thead>
            <tr><th>Person</th><th v-for="t in balanceTypes" :key="t.leave_type">{{ t.label.replace(" leave", "") }}</th></tr>
          </thead>
          <tbody>
            <tr v-for="p in balances.people" :key="p.employee_id">
              <td><div class="st-person"><span class="st-avatar sm" :style="{ background: personColor(p.name) }">{{ initialsOf(p.name) }}</span><div><strong>{{ p.name }}</strong><small>{{ p.position || "—" }}</small></div></div></td>
              <td v-for="t in balanceTypes" :key="t.leave_type">
                <component :is="perms.edit ? 'button' : 'span'" type="button" class="ld-cell" :class="cellTone(p, t.leave_type)" @click="perms.edit && openAdjust(p, t)">
                  <b>{{ cellMain(p, t.leave_type) }}</b>
                  <small>{{ cellSub(p, t.leave_type) }}</small>
                </component>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ── Rules ── -->
    <section v-else-if="tab === 'rules'" class="sp-card">
      <p class="sp-hint ld-hint">What each kind of leave allows. Changes apply to new requests; leave already approved stays as it was.</p>
      <div v-if="!policyDraft" class="ld-sk-block"><div v-for="n in 6" :key="n" class="bc-skeleton sp-sk-line w70"></div></div>
      <div v-else class="st-scroll">
        <table class="st-table ld-rules">
          <thead><tr><th>Kind of leave</th><th>Offered</th><th>Days a year</th><th>Paid</th><th>Needs a document</th></tr></thead>
          <tbody>
            <tr v-for="p in policyDraft" :key="p.leave_type" :class="{ off: !p.enabled }">
              <td><strong>{{ p.label }}</strong></td>
              <td><label class="sp-switch"><input v-model="p.enabled" type="checkbox" :disabled="!perms.edit" :aria-label="`Offer ${p.label}`" /><span></span></label></td>
              <td><input v-model="p.annual_days" type="number" min="0" max="365" step="0.5" class="sp-input ld-days" placeholder="No limit" :disabled="!perms.edit || !p.enabled" :aria-label="`${p.label} days a year`" /></td>
              <td><label class="sp-switch"><input v-model="p.is_paid" type="checkbox" :disabled="!perms.edit || !p.enabled" :aria-label="`${p.label} is paid`" /><span></span></label></td>
              <td><label class="sp-switch"><input v-model="p.requires_document" type="checkbox" :disabled="!perms.edit || !p.enabled" :aria-label="`${p.label} needs a document`" /><span></span></label></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="perms.edit && policyDraft" class="ld-save">
        <small class="sp-hint">Leave "Days a year" empty for no limit. Philippine law: 5 days service incentive leave, 105 days maternity, 7 days paternity.</small>
        <button type="button" class="sp-btn sp-btn-primary" :disabled="busy" @click="savePolicies">{{ busy ? "Saving…" : "Save rules" }}</button>
      </div>
    </section>

    <!-- ── QR poster ── -->
    <LeavePoster v-else :load="api.poster" />

    <!-- Leave detail -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="detail" class="sp-backdrop" @click="detail = null"></div></Transition>
      <Transition name="sp-slide">
        <aside v-if="detail" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="ld-detail">
          <div class="sp-drawer-head">
            <div class="st-person">
              <span class="st-avatar" :style="{ background: personColor(detail.employee) }">{{ initialsOf(detail.employee) }}</span>
              <div>
                <h3 id="ld-detail">{{ detail.employee }}</h3>
                <p>{{ detail.label }} · {{ dateRange(detail.start_date, detail.end_date) }}</p>
              </div>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="detail = null"><span v-html="ICONS.close"></span></button>
          </div>
          <div class="sp-drawer-body">
            <div class="ld-facts">
              <span class="sp-chip" :class="LEAVE_STATUS[detail.status].tone">{{ LEAVE_STATUS[detail.status].label }}</span>
              <span class="sp-chip slate">{{ daysText(detail.days).replace("day", "working day") }}</span>
              <span class="sp-chip" :class="detail.is_paid ? 'green' : 'slate'">{{ detail.is_paid ? "Paid" : "Unpaid" }}</span>
            </div>
            <p v-if="!detail.badge_verified" class="sp-banner warn"><span v-html="ICONS.alert"></span>Filed with an old badge, so it isn't certain they filed it themselves. Check with them before approving.</p>

            <h4 class="st-section-title">Reason</h4>
            <p class="ld-reason">“{{ detail.reason }}”</p>
            <button v-if="detail.document" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busy" @click="openDocument(detail)"><span v-html="ICONS.clip"></span>Open {{ detail.document }}</button>

            <template v-if="detailBalance">
              <h4 class="st-section-title">{{ detail.label }} this year</h4>
              <p class="ld-balance">
                <template v-if="detailBalance.allowance === null">No yearly limit · {{ daysText(detailBalance.used) }} used</template>
                <template v-else>{{ daysText(detailBalance.available) }} left of {{ daysText(detailBalance.allowance) }} ({{ daysText(detailBalance.used) }} used, {{ daysText(detailBalance.pending) }} waiting — this request included)</template>
              </p>
            </template>

            <h4 class="st-section-title">History</h4>
            <RequestHistory :history="detail.history" />

            <template v-if="detail.status === 'pending' && (perms.approve || perms.reject)">
              <h4 class="st-section-title">Your decision</h4>
              <textarea v-model="decisionNote" class="sp-textarea" maxlength="1000" placeholder="Note to them (needed to decline)"></textarea>
            </template>
            <p v-if="drawerError" class="sp-error">{{ drawerError }}</p>
          </div>
          <div class="sp-drawer-foot">
            <button v-if="perms.edit && (detail.status === 'pending' || (detail.status === 'approved' && detail.start_date > today))" type="button" class="sp-btn sp-btn-ghost ld-left" :disabled="busy" @click="cancelLeave(detail)">Cancel leave</button>
            <template v-if="detail.status === 'pending'">
              <button v-if="perms.reject" type="button" class="sp-btn sp-btn-ghost" :disabled="busy" @click="decide('rejected')">Decline</button>
              <button v-if="perms.approve" type="button" class="sp-btn sp-btn-primary" :disabled="busy" @click="decide('approved')">{{ busy ? "Saving…" : "Approve" }}</button>
            </template>
            <button v-else type="button" class="sp-btn sp-btn-ghost" @click="detail = null">Close</button>
          </div>
        </aside>
      </Transition>
    </Teleport>

    <!-- File for someone -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="fileForm" class="sp-backdrop" @click="fileForm = null"></div></Transition>
      <Transition name="sp-slide">
        <form v-if="fileForm" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="ld-file" novalidate @submit.prevent="sendFile">
          <div class="sp-drawer-head">
            <div>
              <h3 id="ld-file">{{ fileForm.kind === "leave" ? "File leave for someone" : "File overtime for someone" }}</h3>
              <p>For when they can't use the leave page. It still needs approval.</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="fileForm = null"><span v-html="ICONS.close"></span></button>
          </div>
          <div class="sp-drawer-body">
            <div class="sp-grid-2">
              <label class="sp-field sp-span-all">
                <span class="sp-label">Person</span>
                <select v-model="fileForm.employee_id" class="sp-select" :class="{ invalid: fileErrors.employee_id }">
                  <option :value="null" disabled>Choose…</option>
                  <option v-for="p in people" :key="p.employee_id" :value="p.employee_id">{{ p.name }}</option>
                </select>
              </label>
              <template v-if="fileForm.kind === 'leave'">
                <label class="sp-field sp-span-all">
                  <span class="sp-label">Kind of leave</span>
                  <select v-model="fileForm.leave_type" class="sp-select" :class="{ invalid: fileErrors.leave_type }">
                    <option value="" disabled>Choose…</option>
                    <option v-for="p in policies.filter((x) => x.enabled)" :key="p.leave_type" :value="p.leave_type">{{ p.label }}</option>
                  </select>
                </label>
                <label class="sp-field"><span class="sp-label">First day</span><input v-model="fileForm.start_date" type="date" class="sp-input" :class="{ invalid: fileErrors.start_date }" /></label>
                <label class="sp-field"><span class="sp-label">Last day</span><input v-model="fileForm.end_date" type="date" class="sp-input" :min="fileForm.start_date" :class="{ invalid: fileErrors.end_date }" /></label>
                <label class="sp-field sp-span-all">
                  <span class="sp-label">Document <small>(optional unless the rules need one)</small></span>
                  <input type="file" class="sp-input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" @change="fileForm.document = $event.target.files?.[0] ?? null" />
                </label>
              </template>
              <template v-else>
                <label class="sp-field"><span class="sp-label">Day</span><input v-model="fileForm.date" type="date" class="sp-input" :class="{ invalid: fileErrors.date }" /></label>
                <label class="sp-field">
                  <span class="sp-label">After the shift</span>
                  <select v-model.number="fileForm.minutes" class="sp-select">
                    <option v-for="m in [30, 60, 90, 120, 180, 240, 300, 360]" :key="m" :value="m">{{ durationText(m) }}</option>
                  </select>
                </label>
              </template>
              <label class="sp-field sp-span-all">
                <span class="sp-label">Reason</span>
                <textarea v-model="fileForm.reason" class="sp-textarea" maxlength="1000" :class="{ invalid: fileErrors.reason }"></textarea>
              </label>
            </div>
            <p v-if="fileError" class="sp-error">{{ fileError }}</p>
          </div>
          <div class="sp-drawer-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="fileForm = null">Cancel</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="busy">{{ busy ? "Filing…" : "File it" }}</button>
          </div>
        </form>
      </Transition>
    </Teleport>

    <!-- Adjust a balance -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="adjust" class="sp-backdrop" @click="adjust = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="adjust" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="ld-adjust" novalidate @submit.prevent="sendAdjust">
            <div class="sp-modal-head"><div><h3 id="ld-adjust">{{ adjust.name }} · {{ adjust.label }}</h3><p>{{ balanceYear }}: {{ cellMain(adjust.person, adjust.leave_type) }} {{ cellSub(adjust.person, adjust.leave_type) }}</p></div></div>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span class="sp-label">Days</span>
                <input v-model.number="adjust.days" type="number" step="0.5" min="-365" max="365" class="sp-input" placeholder="e.g. 2 or -1" />
                <small class="sp-hint">Negative takes days away.</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Why</span>
                <input v-model="adjust.note" class="sp-input" maxlength="255" placeholder="e.g. Carried over from 2025" />
              </label>
            </div>
            <p v-if="adjustError" class="sp-error">{{ adjustError }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="adjust = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="busy || !adjust.days || !adjust.note.trim()">Save</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { toast } from "vue3-toastify";
import { SHOP_ICONS as ICONS } from "../../vendor/shop/shopIcons";
import { personColor } from "../../vendor/staff/staffUi";
import { initialsOf } from "../hrUi";
import RequestHistory from "./RequestHistory.vue";
import LeavePoster from "./LeavePoster.vue";
import { LEAVE_STATUS, dateRange, daysText, durationText } from "../../../services/leaveDesk";

const props = defineProps({
  api: { type: Object, required: true },
  perms: { type: Object, default: () => ({ view: true, create: true, edit: true, approve: true, reject: true, delete: true }) },
});

const STATUS_FILTERS = [
  { key: "pending", label: "Waiting" },
  { key: "approved", label: "Approved" },
  { key: "", label: "All" },
];
const thisYear = new Date().getFullYear();
const years = [thisYear + 1, thisYear, thisYear - 1, thisYear - 2];
const today = new Date().toISOString().slice(0, 10);

const tab = ref("requests");
const stats = ref(null);
const leaves = ref(null);
const overtime = ref(null);
const balances = ref(null);
const policies = ref([]);
const policyDraft = ref(null);
const error = ref("");
const busy = ref(false);
const search = ref("");
const filters = reactive({ status: "pending", leave_type: "", year: thisYear });
const otStatus = ref("pending");
const balanceYear = ref(thisYear);
const notes = reactive({});
const detail = ref(null);
const decisionNote = ref("");
const drawerError = ref("");
const fileForm = ref(null);
const fileErrors = reactive({});
const fileError = ref("");
const adjust = ref(null);
const adjustError = ref("");

const tabs = computed(() => [
  { key: "requests", label: "Leave requests", count: stats.value?.pending },
  { key: "overtime", label: "Overtime requests", count: stats.value?.overtime_pending },
  { key: "balances", label: "Balances" },
  { key: "rules", label: "Leave rules" },
  { key: "poster", label: "QR poster" },
]);
const shownLeaves = computed(() => {
  const q = search.value.trim().toLowerCase();
  return (leaves.value ?? []).filter((l) => !q || l.employee.toLowerCase().includes(q));
});
const shownOvertime = computed(() => (overtime.value ?? []).filter((o) => !otStatus.value || o.status === otStatus.value));
const people = computed(() => balances.value?.people ?? []);
const balanceTypes = computed(() => policies.value.filter((p) => p.enabled));
const detailBalance = computed(() => {
  if (!detail.value || !balances.value) return null;
  return people.value.find((p) => p.employee_id === detail.value.employee_id)?.balances.find((b) => b.leave_type === detail.value.leave_type) ?? null;
});

const fmt = (n) => (Number(n) % 1 ? Number(n).toFixed(1) : String(Number(n ?? 0)));
const bal = (p, type) => p.balances.find((b) => b.leave_type === type);
const cellMain = (p, type) => {
  const b = bal(p, type);
  return !b ? "—" : b.available === null ? `${fmt(b.used)} used` : `${fmt(b.available)} left`;
};
const cellSub = (p, type) => {
  const b = bal(p, type);
  return !b ? "" : b.allowance === null ? "no limit" : `of ${fmt(b.allowance)}${b.pending ? ` · ${fmt(b.pending)} waiting` : ""}`;
};
const cellTone = (p, type) => {
  const b = bal(p, type);
  return !b || b.available === null ? "" : b.available <= 0 ? "out" : b.available <= 1 ? "low" : "";
};

function message(e, fallback) {
  const list = e?.response?.data?.errors;
  return (list && Object.values(list).flat()[0]) || e?.response?.data?.message || fallback;
}

async function loadStats() {
  try {
    stats.value = (await props.api.stats()).data;
  } catch (_) {
    stats.value = { pending: 0, on_leave_today: 0, total_days_this_month: 0, overtime_pending: 0 };
  }
}

async function loadLeaves() {
  error.value = "";
  leaves.value = null;
  try {
    leaves.value = (await props.api.list({ status: filters.status || undefined, leave_type: filters.leave_type || undefined, year: filters.year })).data;
  } catch (e) {
    leaves.value = [];
    error.value = message(e, "Couldn't load leave requests.");
  }
}

async function loadOvertime() {
  try {
    overtime.value = (await props.api.overtime()).data;
  } catch (e) {
    overtime.value = [];
    toast.error(message(e, "Couldn't load overtime requests."));
  }
}

async function loadBalances() {
  balances.value = null;
  try {
    balances.value = (await props.api.balances(balanceYear.value)).data;
  } catch (e) {
    balances.value = { people: [] };
    toast.error(message(e, "Couldn't load balances."));
  }
}

async function loadPolicies() {
  try {
    policies.value = (await props.api.policies()).data;
    policyDraft.value = policies.value.map((p) => ({ ...p, annual_days: p.annual_days ?? "" }));
  } catch (e) {
    toast.error(message(e, "Couldn't load the leave rules."));
  }
}

function setTab(key) {
  tab.value = key;
  if (key === "overtime" && !overtime.value) loadOvertime();
  if (key === "balances" && !balances.value) loadBalances();
}

watch(filters, loadLeaves);

// ── Leave decisions ──

function openLeave(l) {
  detail.value = l;
  decisionNote.value = "";
  drawerError.value = "";
  if (!balances.value || balances.value.year !== Number(l.start_date.slice(0, 4))) {
    balanceYear.value = Number(l.start_date.slice(0, 4));
    loadBalances();
  }
}

async function act(fn, done) {
  busy.value = true;
  drawerError.value = "";
  try {
    const res = await fn();
    if (res?.message) toast.success(res.message);
    await done?.(res);
  } catch (e) {
    drawerError.value = message(e, "Couldn't save that.");
    toast.error(drawerError.value);
  } finally {
    busy.value = false;
  }
}

function decide(status) {
  if (status === "rejected" && !decisionNote.value.trim()) {
    drawerError.value = "Add a note saying why you're declining it.";
    return;
  }
  act(() => props.api.decide(detail.value.id, status, decisionNote.value.trim() || null), async () => {
    detail.value = null;
    await Promise.all([loadLeaves(), loadStats(), balances.value && loadBalances()]);
  });
}

function cancelLeave(l) {
  act(() => props.api.cancel(l.id, decisionNote.value.trim() || null), async () => {
    detail.value = null;
    await Promise.all([loadLeaves(), loadStats()]);
  });
}

async function openDocument(l) {
  const win = window.open("", "_blank");
  try {
    const url = await props.api.document(l.id);
    if (win) {
      win.opener = null;
      win.location.href = url;
    }
  } catch (e) {
    win?.close();
    toast.error(message(e, "Couldn't open the document."));
  }
}

// ── Overtime decisions ──

function decideOvertime(o, status) {
  const note = (notes[`o${o.id}`] ?? "").trim();
  if (status === "rejected" && !note) return toast.info("Add a note saying why you're declining it.");
  act(() => props.api.decideOvertime(o.id, status, note || null), () => Promise.all([loadOvertime(), loadStats()]));
}

function cancelOvertime(o) {
  act(() => props.api.cancelOvertime(o.id), () => Promise.all([loadOvertime(), loadStats()]));
}

// ── Filing for someone ──

async function openFile(kind) {
  if (!balances.value) await loadBalances();
  Object.keys(fileErrors).forEach((k) => delete fileErrors[k]);
  fileError.value = "";
  fileForm.value = { kind, employee_id: null, leave_type: "", start_date: "", end_date: "", reason: "", document: null, date: "", minutes: 60 };
}

async function sendFile() {
  const f = fileForm.value;
  busy.value = true;
  fileError.value = "";
  Object.keys(fileErrors).forEach((k) => delete fileErrors[k]);
  try {
    const res = f.kind === "leave"
      ? await props.api.file(f)
      : await props.api.fileOvertime({ employee_id: f.employee_id, date: f.date, minutes: f.minutes, reason: f.reason });
    toast.success(res.message);
    fileForm.value = null;
    await Promise.all([f.kind === "leave" ? loadLeaves() : loadOvertime(), loadStats(), loadBalances()]);
  } catch (e) {
    const list = e?.response?.data?.errors ?? {};
    for (const [k, v] of Object.entries(list)) fileErrors[k] = [].concat(v)[0];
    fileError.value = message(e, "Couldn't file it.");
  } finally {
    busy.value = false;
  }
}

// ── Balances & rules ──

function openAdjust(person, type) {
  adjustError.value = "";
  adjust.value = { person, name: person.name, employee_id: person.employee_id, leave_type: type.leave_type, label: type.label, days: null, note: "" };
}

async function sendAdjust() {
  const a = adjust.value;
  busy.value = true;
  adjustError.value = "";
  try {
    const res = await props.api.adjust({ employee_id: a.employee_id, leave_type: a.leave_type, year: balanceYear.value, days: a.days, note: a.note.trim() });
    toast.success(res.message);
    adjust.value = null;
    await loadBalances();
  } catch (e) {
    adjustError.value = message(e, "Couldn't update the balance.");
  } finally {
    busy.value = false;
  }
}

async function savePolicies() {
  busy.value = true;
  try {
    const res = await props.api.savePolicies(policyDraft.value.map((p) => ({
      leave_type: p.leave_type,
      annual_days: p.annual_days === "" || p.annual_days === null ? null : Number(p.annual_days),
      is_paid: !!p.is_paid,
      enabled: !!p.enabled,
      requires_document: !!p.requires_document,
    })));
    toast.success(res.message);
    await loadPolicies();
    balances.value = null;
  } catch (e) {
    toast.error(message(e, "Couldn't save the rules."));
  } finally {
    busy.value = false;
  }
}

onMounted(() => {
  loadStats();
  loadLeaves();
  loadPolicies();
});
</script>

<style scoped>
.ld-tabs {
  display: flex;
  width: fit-content;
  max-width: 100%;
  margin-bottom: 16px;
  overflow-x: auto;
}
.ld-tabs button {
  white-space: nowrap;
}
.ld-stats {
  margin-bottom: 16px;
}
.ld-stats .grow {
  flex: 1;
}
.ld-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 12px;
}
.ld-filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.ld-filters .sp-select {
  width: auto;
}
.ld-filters .sp-search {
  width: 200px;
}
.ld-hint {
  margin: 0 0 12px;
}
.ld-sk,
.ld-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.ld-sk li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
}
.ld-sk .grow {
  flex: 1;
}
.ld-sk-block {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ld-row {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 12px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-surface);
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.ld-row:hover,
.ld-row:focus-visible {
  border-color: var(--bc-accent);
  box-shadow: 0 4px 14px var(--v-shadow);
}
.ld-main {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}
.ld-main b {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.ld-main small {
  font-size: 12px;
  color: var(--v-muted);
}
.ld-tags {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 6px;
}
.ld-doc :deep(svg) {
  width: 15px;
  height: 15px;
  color: var(--v-muted);
}
.ld-ot {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px 0;
  border-top: 1px solid var(--v-border);
}
.ld-ot:first-of-type {
  border-top: 0;
}
.ld-ot header {
  display: flex;
  align-items: center;
  gap: 12px;
}
.ld-reason {
  margin: 0;
  font-size: 13px;
  font-style: italic;
  color: var(--v-text-2);
}
.ld-review {
  margin: 0;
  padding: 8px 10px;
  border-radius: 10px;
  background: var(--v-subtle);
  font-size: 12.5px;
}
.ld-decide {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}
.ld-decide .sp-input {
  flex: 1 1 220px;
  max-width: 360px;
  padding: 7px 10px;
}
.ld-year {
  width: auto;
}
.ld-bal th,
.ld-rules th {
  white-space: nowrap;
}
.ld-cell {
  display: flex;
  flex-direction: column;
  padding: 6px 8px;
  border: 0;
  border-radius: 10px;
  background: none;
  font: inherit;
  text-align: left;
}
button.ld-cell {
  cursor: pointer;
}
button.ld-cell:hover {
  background: var(--v-subtle);
}
.ld-cell b {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.ld-cell small {
  font-size: 11px;
  color: var(--v-muted);
}
.ld-cell.low b {
  color: var(--v-amber-text);
}
.ld-cell.out b {
  color: var(--v-red-text);
}
.ld-rules tr.off td {
  opacity: 0.55;
}
.ld-days {
  width: 110px;
  padding: 7px 10px;
}
.ld-save {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 14px;
}
.ld-facts {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}
.ld-balance {
  margin: 0;
  font-size: 13px;
  color: var(--v-text-2);
}
.ld-left {
  margin-right: auto;
}
.st-section-title {
  margin-top: 18px;
}
@media (max-width: 760px) {
  .ld-filters,
  .ld-filters .sp-search {
    width: 100%;
  }
}
</style>
