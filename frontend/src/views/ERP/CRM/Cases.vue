<template>
  <HrPage title="Support cases" subtitle="Complaints and service issues from customers. They follow the same case on their Help page and are told when you reply or resolve it.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="load(page)"><span v-html="ICONS.restore"></span>Refresh</button>
      <button v-if="canCreate" type="button" class="sp-btn sp-btn-primary" @click="openNew"><span v-html="ICONS.plus"></span>Record a case</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load(page)">Try again</button>
    </div>

    <!-- At a glance -->
    <section class="fx-kpis" aria-label="Cases at a glance">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div></div>
      </template>
      <template v-else>
        <button v-for="k in kpis" :key="k.key" type="button" class="sp-card fx-kpi ca-kpi" :class="{ on: k.on }" @click="k.go()">
          <span class="fx-kpi-label"><span class="ca-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong>{{ k.value }}</strong>
          <span class="fx-kpi-sub">{{ k.sub }}</span>
        </button>
      </template>
    </section>

    <section class="sp-card ca-list" aria-label="Cases">
      <div class="ca-head">
        <div class="sp-seg" role="tablist" aria-label="Status">
          <button v-for="s in TABS" :key="s.value" type="button" role="tab" :aria-selected="status === s.value" :class="{ on: status === s.value }" @click="setStatus(s.value)">
            {{ s.label }}<span v-if="data" class="count">{{ data.counts[s.value] }}</span>
          </button>
        </div>
        <div class="ca-filters">
          <label class="ca-filter">
            <span class="sp-label">Priority</span>
            <select v-model="priority" class="sp-select" @change="load(1)">
              <option value="">Any</option>
              <option v-for="p in PRIORITIES" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
          </label>
          <label class="ca-filter">
            <span class="sp-label">Assigned to</span>
            <select v-model="assigned" class="sp-select" @change="load(1)">
              <option value="">Anyone</option>
              <option v-if="isEmployee" value="me">Me</option>
              <option value="none">No one yet</option>
              <option v-for="s in data?.staff ?? []" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
            </select>
          </label>
          <label class="sp-search ca-search">
            <span v-html="ICONS.search"></span>
            <input v-model="search" class="sp-input" type="search" placeholder="Case, customer or order" aria-label="Search cases" @input="onSearch" />
          </label>
        </div>
      </div>

      <ul v-if="!data" class="ca-rows">
        <li v-for="n in 5" :key="n" class="ca-row sk"><div class="bc-skeleton ca-pri-sk"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
      </ul>
      <div v-else-if="!data.rows.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.check"></span>
        <h3>{{ status === "active" && !priority && !assigned && !search ? "No open cases" : "No cases match" }}</h3>
        <p>{{ status === "active" && !priority && !assigned && !search ? "Complaints customers file about your orders show up here, and so do ones you record." : "Try another status or filter." }}</p>
      </div>
      <template v-else>
        <ul class="ca-rows" :class="{ dim: loading }">
          <li v-for="c in data.rows" :key="c.id">
            <button type="button" class="ca-row" :class="{ on: selectedId === c.id }" @click="open(c.id)">
              <span class="ca-pri" :class="c.priority" :title="`${PRIORITY[c.priority].label} priority`">
                <span v-html="PRIORITY[c.priority].icon"></span><b>{{ PRIORITY[c.priority].label }}</b>
              </span>
              <span class="grow">
                <strong>{{ c.subject }}</strong>
                <small><span class="mono">{{ c.reference }}</span> · {{ c.customer?.name || "Customer" }} · {{ c.category_label }}<template v-if="c.order_number"> · {{ c.order_number }}</template></small>
              </span>
              <span class="ca-who hide-sm">
                <span v-if="c.assignee" class="ca-assignee"><span class="ca-avatar" aria-hidden="true">{{ initials(c.assignee.name) }}</span><span class="ca-assignee-name">{{ c.assignee.name.split(" ")[0] }}</span></span>
                <span v-else class="ca-unassigned">Unassigned</span>
              </span>
              <span class="ca-state">
                <span class="sp-chip" :class="STATUS[c.status].tone">{{ STATUS[c.status].label }}</span>
                <small>{{ ago(c.last_activity_at) }}</small>
              </span>
            </button>
          </li>
        </ul>
        <footer v-if="data.meta.last_page > 1" class="ca-pager">
          <span>{{ data.meta.total }} cases · page {{ data.meta.page }} of {{ data.meta.last_page }}</span>
          <div>
            <button type="button" class="sp-icon-btn" :disabled="page <= 1" aria-label="Previous page" @click="load(page - 1)"><span v-html="ICONS.chevronLeft"></span></button>
            <button type="button" class="sp-icon-btn" :disabled="page >= data.meta.last_page" aria-label="Next page" @click="load(page + 1)"><span v-html="ICONS.chevronRight"></span></button>
          </div>
        </footer>
      </template>
    </section>

    <!-- Case -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="selectedId" class="sp-backdrop" @click="selectedId = null"></div></Transition>
      <Transition name="sp-slide">
        <aside v-if="selectedId" class="sp-drawer ca-drawer" role="dialog" aria-modal="true" aria-labelledby="ca-title">
          <div class="sp-drawer-head">
            <div>
              <h3 id="ca-title">{{ detail?.subject || "Loading…" }}</h3>
              <p v-if="detail"><span class="mono">{{ detail.reference }}</span> · {{ detail.category_label }} · {{ detail.recorded_by_shop ? "recorded by the shop" : "filed by the customer" }} {{ ago(detail.created_at) }}</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="selectedId = null"><span v-html="ICONS.close"></span></button>
          </div>

          <div class="sp-drawer-body">
            <div v-if="detailError" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ detailError }}</div>
            <div v-else-if="!detail" class="ca-sk"><div v-for="n in 5" :key="n" class="bc-skeleton sp-sk-line"></div></div>
            <template v-else>
              <!-- Who, and how to reach them -->
              <section class="ca-cust">
                <span class="ca-avatar lg">{{ initials(detail.customer?.name) }}</span>
                <span class="grow">
                  <strong>{{ detail.customer?.name || "Customer" }}</strong>
                  <small>{{ [detail.contact.email, detail.contact.phone].filter(Boolean).join(" · ") }}</small>
                </span>
              </section>

              <!-- Status, priority, owner -->
              <div class="ca-controls">
                <label class="sp-field">
                  <span class="sp-label">Status</span>
                  <select class="sp-select" :value="detail.status" :disabled="!canEdit || busy === 'update'" @change="update({ status: $event.target.value })">
                    <option v-for="(s, key) in STATUS" :key="key" :value="key">{{ s.label }}</option>
                  </select>
                </label>
                <label class="sp-field">
                  <span class="sp-label">Priority</span>
                  <select class="sp-select" :value="detail.priority" :disabled="!canEdit || busy === 'update'" @change="update({ priority: $event.target.value })">
                    <option v-for="p in PRIORITIES" :key="p.value" :value="p.value">{{ p.label }}</option>
                  </select>
                </label>
                <label class="sp-field">
                  <span class="sp-label">Assigned to</span>
                  <select class="sp-select" :value="detail.assignee?.id ?? ''" :disabled="!canEdit || busy === 'update'" @change="update({ assigned_employee_id: $event.target.value ? Number($event.target.value) : null })">
                    <option value="">No one yet</option>
                    <option v-for="s in data?.staff ?? []" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </label>
              </div>

              <!-- Linked order and refund -->
              <h4 class="st-section-title">Linked records</h4>
              <div class="ca-links">
                <div class="ca-link-card">
                  <span class="ca-link-icon" v-html="ICONS.receipt"></span>
                  <div class="grow">
                    <span class="sp-label">Order</span>
                    <select class="sp-select" :value="detail.order?.id ?? ''" :disabled="!canEdit || busy === 'update'" aria-label="Linked order" @change="update({ order_id: $event.target.value ? Number($event.target.value) : null })">
                      <option value="">Not linked</option>
                      <option v-for="o in detail.links.orders" :key="o.id" :value="o.id">{{ o.order_number }} · {{ money(o.total) }}</option>
                    </select>
                    <small v-if="detail.order">{{ statusText(detail.order.status) }} · {{ detail.order.payment_status }} · placed {{ shortDate(detail.order.created_at) }}</small>
                  </div>
                </div>
                <div class="ca-link-card">
                  <span class="ca-link-icon" v-html="ICONS.restore"></span>
                  <div class="grow">
                    <span class="sp-label">Refund or return</span>
                    <select class="sp-select" :value="detail.refund?.id ?? ''" :disabled="!canEdit || busy === 'update' || !detail.links.refunds.length" aria-label="Linked refund or return" @change="update({ order_request_id: $event.target.value ? Number($event.target.value) : null })">
                      <option value="">{{ detail.links.refunds.length ? "Not linked" : "They have no refund requests" }}</option>
                      <option v-for="r in detail.links.refunds" :key="r.id" :value="r.id">{{ r.order_number }} · {{ r.type }} · {{ statusText(r.status) }}</option>
                    </select>
                    <small v-if="detail.refund">{{ statusText(detail.refund.type) }} · {{ statusText(detail.refund.status) }} · {{ money(detail.refund.amount) }}</small>
                    <router-link v-if="detail.refund && canSeeRefunds" :to="{ name: 'FinanceRefunds' }" class="st-link">Open in Refunds</router-link>
                  </div>
                </div>
              </div>

              <!-- Conversation and history -->
              <h4 class="st-section-title">Conversation</h4>
              <ol class="ca-thread">
                <li v-for="m in detail.messages" :key="m.id" :class="[m.side, { internal: m.internal }]">
                  <div class="ca-msg-head">
                    <span v-if="m.internal" class="ca-note-tag"><span v-html="ICONS.lock"></span>Team note</span>
                    <b>{{ m.author }}</b>
                    <small>{{ shortDate(m.created_at, true) }}</small>
                  </div>
                  <p>{{ m.body }}</p>
                </li>
              </ol>

              <form v-if="canCreate && detail.status !== 'closed'" class="ca-reply" @submit.prevent="reply">
                <div class="sp-seg ca-mode" role="radiogroup" aria-label="Who sees it">
                  <button type="button" role="radio" :aria-checked="!internal" :class="{ on: !internal }" @click="internal = false">Reply to customer</button>
                  <button type="button" role="radio" :aria-checked="internal" :class="{ on: internal }" @click="internal = true"><span v-html="ICONS.lock"></span>Team note</button>
                </div>
                <label class="sp-field">
                  <span class="sp-label">{{ internal ? "Note for your team (the customer won't see it)" : `Message to ${firstName}` }}</span>
                  <textarea v-model="draft" class="sp-textarea" rows="3" maxlength="5000" :placeholder="internal ? 'e.g. Checked the cooler, batch was fine. Rider left it in the sun.' : 'e.g. We are so sorry. A fresh bouquet is on its way today.'"></textarea>
                </label>
                <div class="ca-reply-foot">
                  <small class="sp-hint">{{ internal ? "Saved to the case history only." : `${firstName} gets a notification and sees it on their Help page.` }}</small>
                  <button type="submit" class="sp-btn sp-btn-primary" :disabled="busy === 'reply' || draft.trim().length < 2"><span v-html="ICONS.send"></span>{{ busy === "reply" ? "Saving…" : internal ? "Add note" : "Send reply" }}</button>
                </div>
              </form>
              <p v-else-if="detail.status === 'closed'" class="sp-hint">This case is closed. Change its status to reply again.</p>
            </template>
          </div>
        </aside>
      </Transition>
    </Teleport>

    <!-- Record a case -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="form" class="sp-backdrop" @click="form = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="form" class="sp-modal-wrap">
          <form class="sp-modal wide" role="dialog" aria-modal="true" aria-labelledby="nf-title" novalidate @submit.prevent="create">
            <div class="sp-modal-head">
              <div><h3 id="nf-title">Record a case</h3><p>For a complaint made in the shop, by phone or in chat. The customer can follow it on their Help page.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="form = null"><span v-html="ICONS.close"></span></button>
            </div>

            <div class="sp-field">
              <span class="sp-label">Customer</span>
              <div v-if="form.customer" class="ca-picked">
                <span class="ca-avatar">{{ initials(form.customer.name) }}</span>
                <span class="grow"><b>{{ form.customer.name }}</b><small>{{ form.customer.email }}</small></span>
                <button type="button" class="st-link" @click="form.customer = null; form.links = null">Change</button>
              </div>
              <template v-else>
                <label class="sp-search">
                  <span v-html="ICONS.search"></span>
                  <input v-model="form.query" class="sp-input" type="search" placeholder="Name, email or phone" aria-label="Find a customer" @input="findCustomers" />
                </label>
                <ul v-if="form.results.length" class="ca-results">
                  <li v-for="c in form.results" :key="c.id"><button type="button" @click="pickCustomer(c)"><span class="ca-avatar">{{ initials(c.name) }}</span><span class="grow"><b>{{ c.name }}</b><small>{{ c.email }}</small></span></button></li>
                </ul>
                <small v-else-if="form.query.trim().length >= 2 && !form.searching" class="sp-hint">No customers match. Only people who ordered, chatted or asked the shop a question are listed.</small>
              </template>
              <small v-if="form.errors.customer_id" class="sp-error">{{ form.errors.customer_id }}</small>
            </div>

            <div class="sp-grid-2">
              <label class="sp-field sp-span-all">
                <span class="sp-label">What happened</span>
                <input v-model="form.subject" class="sp-input" maxlength="160" placeholder="e.g. Roses arrived wilted" />
                <small v-if="form.errors.subject" class="sp-error">{{ form.errors.subject }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Type</span>
                <select v-model="form.category" class="sp-select">
                  <option v-for="c in data?.categories ?? []" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
              </label>
              <label class="sp-field">
                <span class="sp-label">Priority</span>
                <select v-model="form.priority" class="sp-select">
                  <option v-for="p in PRIORITIES" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
              </label>
              <label class="sp-field">
                <span class="sp-label">Order <small>(optional)</small></span>
                <select v-model="form.order_id" class="sp-select" :disabled="!form.links">
                  <option value="">{{ form.customer ? "Not linked" : "Pick a customer first" }}</option>
                  <option v-for="o in form.links?.orders ?? []" :key="o.id" :value="o.id">{{ o.order_number }} · {{ money(o.total) }}</option>
                </select>
              </label>
              <label class="sp-field">
                <span class="sp-label">Refund or return <small>(optional)</small></span>
                <select v-model="form.order_request_id" class="sp-select" :disabled="!form.links?.refunds.length">
                  <option value="">Not linked</option>
                  <option v-for="r in form.links?.refunds ?? []" :key="r.id" :value="r.id">{{ r.order_number }} · {{ r.type }} · {{ statusText(r.status) }}</option>
                </select>
              </label>
              <label class="sp-field sp-span-all">
                <span class="sp-label">Assign to <small>(optional)</small></span>
                <select v-model="form.assigned_employee_id" class="sp-select">
                  <option value="">No one yet</option>
                  <option v-for="s in data?.staff ?? []" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </label>
              <label class="sp-field sp-span-all">
                <span class="sp-label">Details</span>
                <textarea v-model="form.message" class="sp-textarea" rows="4" maxlength="5000" placeholder="What the customer told you, and what you've done so far. They can read this."></textarea>
                <small v-if="form.errors.message" class="sp-error">{{ form.errors.message }}</small>
              </label>
            </div>
            <p v-if="form.error" class="sp-error">{{ form.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="form = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="form.busy">{{ form.busy ? "Saving…" : "Record case" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../components/vendor/finance/financeUi";
import { useAssignment } from "../../../composables/useAssignment";
import { useAuth } from "../../../composables/useAuth";
import "../../../components/vendor/finance/finance.css";

const arrow = (d) => `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${d}</svg>`;
const PRIORITY = {
  urgent: { label: "Urgent", icon: arrow('<path d="m17 11-5-5-5 5"/><path d="m17 18-5-5-5 5"/>') },
  high: { label: "High", icon: arrow('<path d="m18 15-6-6-6 6"/>') },
  normal: { label: "Normal", icon: arrow('<path d="M5 12h14"/>') },
  low: { label: "Low", icon: arrow('<path d="m6 9 6 6 6-6"/>') },
};
const PRIORITIES = ["urgent", "high", "normal", "low"].map((value) => ({ value, label: PRIORITY[value].label }));
const STATUS = {
  open: { label: "Open", tone: "amber" },
  in_progress: { label: "In progress", tone: "blue" },
  waiting: { label: "Waiting on customer", tone: "violet" },
  resolved: { label: "Resolved", tone: "green" },
  closed: { label: "Closed", tone: "slate" },
};
const TABS = [
  { value: "active", label: "Active" },
  { value: "open", label: "Open" },
  { value: "in_progress", label: "In progress" },
  { value: "waiting", label: "Waiting" },
  { value: "resolved", label: "Resolved" },
  { value: "closed", label: "Closed" },
];

const { can } = useAssignment();
const { user } = useAuth();
const canCreate = computed(() => can("crm_cases", "create"));
const canEdit = computed(() => can("crm_cases", "edit"));
const canSeeRefunds = computed(() => can("refunds", "view"));
const isEmployee = computed(() => user.value?.type === "employee");

const data = ref(null);
const error = ref("");
const loading = ref(false);
const page = ref(1);
const status = ref("active");
const priority = ref("");
const assigned = ref("");
const search = ref("");

const selectedId = ref(null);
const detail = ref(null);
const detailError = ref("");
const busy = ref("");
const draft = ref("");
const internal = ref(false);
const form = ref(null);

const firstName = computed(() => detail.value?.customer?.name?.split(/\s+/)[0] || "the customer");
const kpis = computed(() => {
  const d = data.value;
  return [
    { key: "open", label: "New, not started", icon: "alert", tone: "amber", value: d.counts.open, sub: "open cases nobody has answered", on: status.value === "open", go: () => setStatus("open") },
    { key: "progress", label: "In progress", icon: "clock", tone: "blue", value: d.counts.in_progress, sub: `${d.counts.waiting} waiting on the customer`, on: status.value === "in_progress", go: () => setStatus("in_progress") },
    { key: "urgent", label: "Urgent or high", icon: "activity", tone: "red", value: d.urgent, sub: "still active", on: priority.value === "urgent", go: () => { priority.value = "urgent"; setStatus("active"); } },
    { key: "unassigned", label: "Unassigned", icon: "users", tone: "violet", value: d.unassigned, sub: "active cases without an owner", on: assigned.value === "none", go: () => { assigned.value = "none"; setStatus("active"); } },
  ];
});

const initials = (name) => (name || "?").split(/\s+/).filter(Boolean).map((w) => w[0]).slice(0, 2).join("").toUpperCase();
const statusText = (s) => (s ? s.replace(/_/g, " ").replace(/^\w/, (c) => c.toUpperCase()) : "");
const shortDate = (d, time = false) =>
  d ? new Date(d).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", ...(time ? { hour: "numeric", minute: "2-digit" } : {}) }) : "";
function ago(at) {
  if (!at) return "";
  const m = Math.round((Date.now() - new Date(at).getTime()) / 60000);
  if (m < 1) return "just now";
  if (m < 60) return `${m} min ago`;
  if (m < 1440) return `${Math.floor(m / 60)} h ago`;
  return `${Math.floor(m / 1440)} d ago`;
}

async function load(p = 1) {
  error.value = "";
  loading.value = true;
  try {
    const res = await api.get("crm/cases", { params: { page: p, status: status.value, priority: priority.value || undefined, assigned: assigned.value || undefined, search: search.value.trim() || undefined } });
    data.value = res.data.data;
    page.value = p;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load support cases.");
  } finally {
    loading.value = false;
  }
}
let searchTimer = null;
function onSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => load(1), 300);
}
function setStatus(s) {
  status.value = s;
  load(1);
}

async function open(id) {
  selectedId.value = id;
  detail.value = null;
  detailError.value = "";
  draft.value = "";
  internal.value = false;
  try {
    const res = await api.get(`crm/cases/${id}`);
    if (selectedId.value === id) detail.value = res.data.data;
  } catch (e) {
    if (selectedId.value === id) detailError.value = apiMessage(e, "Couldn't load this case.");
  }
}

async function update(changes) {
  busy.value = "update";
  try {
    const res = await api.patch(`crm/cases/${selectedId.value}`, changes);
    detail.value = res.data.data;
    toast.success("Case updated.");
    load(page.value);
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't update the case."));
    open(selectedId.value);
  } finally {
    busy.value = "";
  }
}

async function reply() {
  busy.value = "reply";
  try {
    const res = await api.post(`crm/cases/${selectedId.value}/reply`, { message: draft.value.trim(), internal: internal.value });
    detail.value = res.data.data;
    toast.success(internal.value ? "Note added." : `Sent. ${firstName.value} will be notified.`);
    draft.value = "";
    load(page.value);
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't save that."));
  } finally {
    busy.value = "";
  }
}

// ── Record a case ──
function openNew() {
  form.value = {
    query: "", results: [], searching: false, customer: null, links: null,
    subject: "", category: data.value?.categories?.[0]?.value ?? "complaint", priority: "normal",
    order_id: "", order_request_id: "", assigned_employee_id: "", message: "",
    busy: false, error: "", errors: {},
  };
}
let findTimer = null;
function findCustomers() {
  clearTimeout(findTimer);
  const q = form.value.query.trim();
  if (q.length < 2) {
    form.value.results = [];
    return;
  }
  form.value.searching = true;
  findTimer = setTimeout(async () => {
    try {
      const res = await api.get("crm/customers", { params: { search: q } });
      if (form.value) form.value.results = res.data.data.rows.slice(0, 6);
    } catch {
      if (form.value) form.value.results = [];
    } finally {
      if (form.value) form.value.searching = false;
    }
  }, 300);
}
async function pickCustomer(c) {
  form.value.customer = c;
  form.value.results = [];
  try {
    const res = await api.get("crm/cases/links", { params: { customer_id: c.id } });
    if (form.value?.customer?.id === c.id) form.value.links = res.data.data;
  } catch {
    if (form.value) form.value.links = { orders: [], refunds: [] };
  }
}
async function create() {
  const f = form.value;
  f.errors = {};
  if (!f.customer) f.errors.customer_id = "Pick the customer.";
  if (f.subject.trim().length < 4) f.errors.subject = "Say what happened in a few words.";
  if (f.message.trim().length < 10) f.errors.message = "Add a little more detail.";
  if (Object.keys(f.errors).length) return;
  f.busy = true;
  f.error = "";
  try {
    const res = await api.post("crm/cases", {
      customer_id: f.customer.id, subject: f.subject.trim(), category: f.category, priority: f.priority, message: f.message.trim(),
      order_id: f.order_id || null, order_request_id: f.order_request_id || null, assigned_employee_id: f.assigned_employee_id || null,
    });
    toast.success(res.data.message || "Case recorded.");
    form.value = null;
    await load(1);
    open(res.data.data.id);
  } catch (e) {
    const errors = e?.response?.data?.errors ?? {};
    for (const [k, v] of Object.entries(errors)) f.errors[k] = [].concat(v)[0];
    f.error = Object.keys(errors).length ? "" : apiMessage(e, "Couldn't record the case.");
  } finally {
    if (form.value) form.value.busy = false;
  }
}

onMounted(() => load(1));
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.95em;
}
.ca-kpi {
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, transform 0.15s;
}
.ca-kpi:hover,
.ca-kpi.on {
  border-color: var(--bc-accent);
}
.ca-kpi:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.ca-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.ca-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.tone-red {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}

/* List */
.ca-list {
  min-width: 0;
}
.ca-head {
  display: grid;
  gap: 12px;
  margin-bottom: 10px;
}
.ca-filters {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 10px;
}
.ca-filter {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 150px;
}
.ca-search {
  flex: 1 1 240px;
}
.sp-seg .count {
  margin-left: 6px;
  padding: 0 7px;
  border-radius: 999px;
  background: var(--v-subtle-2);
  font-size: 11px;
}
.ca-rows {
  list-style: none;
  margin: 0;
  padding: 0;
  transition: opacity 0.2s;
}
.ca-rows.dim {
  opacity: 0.55;
}
.ca-rows li + li {
  border-top: 1px solid var(--v-border);
}
.ca-row {
  display: flex;
  align-items: center;
  gap: 14px;
  width: 100%;
  min-height: 60px;
  padding: 12px 8px;
  border: 0;
  border-radius: 12px;
  background: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s;
}
li > .ca-row:hover,
.ca-row.on {
  background: var(--v-subtle);
}
.ca-row.sk {
  cursor: default;
}
.ca-row strong {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ca-row small {
  overflow: hidden;
  font-size: 12px;
  color: var(--v-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}
/* Priority: icon and word, never color alone */
.ca-pri {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  width: 82px;
  padding: 4px 8px;
  border-radius: 8px;
  background: var(--v-subtle);
  color: var(--v-muted);
  font-size: 11.5px;
}
.ca-pri b {
  font-weight: 600;
}
.ca-pri :deep(svg) {
  display: block;
}
.ca-pri.urgent {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.ca-pri.high {
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.ca-pri.normal {
  background: var(--v-blue-soft);
  color: var(--v-blue-text);
}
.ca-pri-sk {
  width: 82px;
  height: 26px;
  border-radius: 8px;
}
.ca-who {
  flex: none;
}
.ca-avatar {
  display: inline-grid;
  place-items: center;
  flex: none;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 12px;
  font-weight: 600;
}
.ca-avatar.lg {
  width: 44px;
  height: 44px;
  border-radius: 13px;
  font-size: 15px;
}
.ca-assignee {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.ca-unassigned {
  font-size: 12px;
  font-style: italic;
  color: var(--v-faint);
}
.ca-state {
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  min-width: 120px;
}
.ca-state small {
  font-size: 11.5px;
}
.ca-pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid var(--v-border);
  font-size: 12.5px;
  color: var(--v-muted);
}
.ca-pager div {
  display: flex;
  gap: 6px;
}

/* Drawer */
.ca-drawer {
  width: min(620px, 100vw);
}
.ca-sk {
  display: grid;
  gap: 12px;
}
.ca-cust {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--v-subtle);
}
.ca-cust strong {
  font-size: 14px;
  color: var(--v-text);
}
.ca-cust small {
  font-size: 12px;
  color: var(--v-muted);
}
.ca-controls {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.ca-links {
  display: grid;
  gap: 10px;
}
.ca-link-card {
  display: flex;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
}
.ca-link-card .grow {
  gap: 6px;
}
.ca-link-card small {
  font-size: 12px;
  color: var(--v-muted);
}
.ca-link-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 11px;
  background: var(--v-subtle);
  color: var(--v-muted);
}
.ca-link-icon :deep(svg) {
  width: 17px;
  height: 17px;
}
.ca-thread {
  list-style: none;
  margin: 0 0 16px;
  padding: 0;
  display: grid;
  gap: 10px;
}
.ca-thread li {
  max-width: 92%;
  padding: 10px 14px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-surface);
}
.ca-thread li.shop {
  justify-self: end;
  border-color: color-mix(in srgb, var(--bc-accent) 35%, transparent);
  background: var(--bc-accent-soft);
}
.ca-thread li.admin {
  border-color: color-mix(in srgb, var(--v-blue-text) 30%, transparent);
  background: var(--v-blue-soft);
}
.ca-thread li.internal {
  justify-self: stretch;
  max-width: none;
  border-style: dashed;
  border-color: var(--v-amber-text);
  background: var(--v-amber-soft);
}
.ca-msg-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 8px;
  margin-bottom: 4px;
}
.ca-msg-head b {
  font-size: 12.5px;
  color: var(--v-text);
}
.ca-msg-head small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.ca-note-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 1px 8px;
  border-radius: 999px;
  background: var(--v-surface);
  color: var(--v-amber-text);
  font-size: 11px;
  font-weight: 600;
}
.ca-note-tag :deep(svg) {
  width: 11px;
  height: 11px;
}
.ca-thread p {
  margin: 0;
  font-size: 13px;
  line-height: 1.55;
  color: var(--v-text);
  white-space: pre-wrap;
}
.ca-reply {
  display: grid;
  gap: 10px;
  padding-top: 14px;
  border-top: 1px solid var(--v-border);
}
.ca-mode {
  justify-self: start;
}
.ca-mode :deep(svg) {
  width: 13px;
  height: 13px;
}
.ca-reply-foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

/* Record a case */
.ca-picked {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
}
.ca-picked b,
.ca-results b {
  font-size: 13px;
  color: var(--v-text);
}
.ca-picked small,
.ca-results small {
  font-size: 12px;
  color: var(--v-muted);
}
.ca-results {
  list-style: none;
  margin: 6px 0 0;
  padding: 0;
  border: 1px solid var(--v-border);
  border-radius: 12px;
}
.ca-results li + li {
  border-top: 1px solid var(--v-border);
}
.ca-results button {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 48px;
  padding: 8px 12px;
  border: 0;
  background: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.ca-results button:hover {
  background: var(--bc-accent-soft);
}
.sp-label small {
  font-weight: 400;
  color: var(--v-faint);
}

@media (max-width: 720px) {
  .hide-sm {
    display: none;
  }
  .ca-row {
    flex-wrap: wrap;
  }
  .ca-row .grow {
    flex-basis: calc(100% - 100px);
  }
  .ca-state {
    flex-direction: row;
    align-items: center;
    min-width: 0;
    margin-left: 96px;
  }
  .ca-controls {
    grid-template-columns: minmax(0, 1fr);
  }
  .ca-filter {
    flex: 1 1 140px;
  }
}
@media (max-width: 560px) {
  .fx-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
}
</style>
