<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <header class="content-header">
        <div class="header-left">
          <button class="mobile-toggle" @click="toggleMobile" aria-label="Toggle Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" /></svg>
          </button>
          <div>
            <h1 class="page-title">Support Tickets</h1>
            <p class="page-subtitle">Support requests, complaints, alerts, activity and announcements</p>
          </div>
        </div>
        <button type="button" class="ghost-btn" @click="refresh">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" /></svg>
          Refresh
        </button>
      </header>

      <div class="page-body">
        <!-- Summary -->
        <section class="stat-grid">
          <template v-if="!summary">
            <div v-for="n in 4" :key="n" class="stat"><span class="sk" style="height: 12px; width: 50%"></span><span class="sk" style="height: 22px; width: 30%; margin-top: 8px"></span></div>
          </template>
          <template v-else>
            <button type="button" class="stat" :class="{ hot: summary.critical_alerts }" @click="setTab('alerts')">
              <span class="stat-label">Alerts</span>
              <span class="stat-value">{{ summary.alerts }}</span>
              <span class="stat-hint">{{ summary.critical_alerts ? `${summary.critical_alerts} critical` : "Nothing critical" }}</span>
            </button>
            <button type="button" class="stat" @click="setTab('inbox')">
              <span class="stat-label">Waiting for a reply</span>
              <span class="stat-value">{{ summary.open_tickets }}</span>
              <span class="stat-hint">{{ summary.high_priority }} high priority · {{ summary.waiting_tickets }} waiting on them</span>
            </button>
            <button type="button" class="stat" @click="setTab('complaints')">
              <span class="stat-label">Open complaints</span>
              <span class="stat-value">{{ summary.open_complaints }}</span>
              <span class="stat-hint">Returns, refunds and reports</span>
            </button>
            <button type="button" class="stat" :class="{ hot: summary.failed_payments_7d }" @click="setTab('alerts')">
              <span class="stat-label">Failed payments</span>
              <span class="stat-value">{{ summary.failed_payments_7d }}</span>
              <span class="stat-hint">In the last 7 days</span>
            </button>
          </template>
        </section>

        <nav class="tabs" role="tablist">
          <button v-for="t in TABS" :key="t.key" type="button" role="tab" class="tab" :class="{ active: tab === t.key }" :aria-selected="tab === t.key" @click="setTab(t.key)">
            {{ t.label }}
          </button>
        </nav>

        <!-- ═══ Alerts ═══ -->
        <template v-if="tab === 'alerts'">
          <section class="panel">
            <h2 class="panel-title">Needs attention</h2>
            <div v-if="loading.alerts && !alerts.items.length" class="sk-list"><span v-for="n in 4" :key="n" class="sk sk-row"></span></div>
            <p v-else-if="!alerts.items.length" class="calm">✓ Nothing needs attention right now.</p>
            <ul v-else class="alert-list">
              <li v-for="a in alerts.items" :key="a.key" class="alert" :class="`sev-${a.severity}`">
                <span class="alert-count">{{ a.count }}</span>
                <div class="alert-main">
                  <span class="strong">{{ a.title }}</span>
                  <span class="sub">{{ a.detail }}</span>
                </div>
                <span class="pill" :class="`sev-pill-${a.severity}`">{{ SEVERITY[a.severity] }}</span>
                <button type="button" class="mini-btn" @click="go(a.link)">Open →</button>
              </li>
            </ul>
          </section>

          <section class="split">
            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Failed payments</h2>
                  <p class="panel-sub">Last 30 days. Gateway references are masked.</p>
                </div>
                <router-link :to="{ path: '/admin/finance' }" class="link-btn">Transactions →</router-link>
              </div>
              <p v-if="!alerts.failed.length" class="empty">No failed payments.</p>
              <ul v-else class="row-list">
                <li v-for="p in alerts.failed" :key="p.key" tabindex="0" @click="openPayment(p)" @keydown.enter="openPayment(p)">
                  <div class="row-main">
                    <span class="strong">{{ p.source === "subscription" ? `${p.vendor.store_name} subscription` : `${p.customer?.name || "Customer"} → ${p.vendor.store_name}` }}</span>
                    <span class="sub">{{ p.reference }} · {{ p.method_label }}<template v-if="p.gateway_ref"> · {{ p.gateway_ref }}</template></span>
                  </div>
                  <div class="row-right">
                    <span class="strong">{{ money(p.amount) }}</span>
                    <span class="sub">{{ formatDateTime(p.occurred_at) }}</span>
                  </div>
                </li>
              </ul>
            </div>
            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Server errors</h2>
                  <p class="panel-sub">Logged by the backend in the last 24 hours</p>
                </div>
              </div>
              <p v-if="!alerts.errors.length" class="calm small">✓ No errors logged.</p>
              <ul v-else class="error-list">
                <li v-for="(e, i) in alerts.errors" :key="i">
                  <span class="pill" :class="e.level === 'error' ? 'sev-pill-warning' : 'sev-pill-critical'">{{ e.level }}</span>
                  <div class="row-main">
                    <code>{{ e.message }}</code>
                    <span class="sub">{{ formatDateTime(e.at) }}</span>
                  </div>
                </li>
              </ul>
            </div>
          </section>
        </template>

        <!-- ═══ Support inbox ═══ -->
        <section v-else-if="tab === 'inbox'" class="inbox">
          <div class="panel inbox-list">
            <div class="inbox-filters">
              <input v-model="ticketFilters.search" type="search" class="input" placeholder="Search subject, reference or name" />
              <div class="filter-row">
                <select v-model="ticketFilters.status" class="input">
                  <option value="active">Open &amp; waiting</option>
                  <option value="open">Waiting for us</option>
                  <option value="waiting">Waiting for them</option>
                  <option value="resolved">Solved</option>
                  <option value="closed">Closed</option>
                  <option value="">All</option>
                </select>
                <select v-model="ticketFilters.role" class="input">
                  <option value="">Vendors &amp; customers</option>
                  <option value="vendor">Vendors</option>
                  <option value="customer">Customers</option>
                </select>
              </div>
            </div>
            <div v-if="loading.tickets && !tickets.length" class="sk-list"><span v-for="n in 5" :key="n" class="sk sk-row"></span></div>
            <p v-else-if="!tickets.length" class="empty">No requests here.</p>
            <ul v-else class="ticket-list">
              <li v-for="t in tickets" :key="t.id">
                <button type="button" class="ticket" :class="{ active: ticket?.id === t.id || openTicketId === t.id }" @click="openTicket(t.id)">
                  <span class="ticket-top">
                    <span class="who">
                      <span class="role" :class="t.requester.role">{{ t.requester.role === "vendor" ? "Vendor" : "Customer" }}</span>
                      {{ t.requester.name }}
                    </span>
                    <span class="sub">{{ timeAgo(t.last_activity_at) }}</span>
                  </span>
                  <span class="ticket-subject">
                    <span v-if="t.priority === 'high' && t.status === 'open'" class="urgent" title="High priority">●</span>
                    {{ t.subject }}
                  </span>
                  <span class="ticket-meta">
                    <span class="mono">{{ t.reference }}</span> · {{ t.issue_label || t.category_label }}
                    <span class="status" :class="`st-${t.status}`">{{ STATUS[t.status] }}</span>
                  </span>
                </button>
              </li>
            </ul>
          </div>

          <div class="panel inbox-view">
            <p v-if="!openTicketId" class="empty">Choose a request to read and reply.</p>
            <template v-else-if="!ticket">
              <span v-for="n in 3" :key="n" class="sk sk-row" style="margin-bottom: 10px"></span>
            </template>
            <template v-else>
              <div class="view-head">
                <div>
                  <span class="sub mono">{{ ticket.reference }} · {{ ticket.category_label }}<template v-if="ticket.issue_label">: {{ ticket.issue_label }}</template><template v-if="ticket.desired_outcome_label"> · wants {{ ticket.desired_outcome_label.toLowerCase() }}</template></span>
                  <h2>{{ ticket.subject }}</h2>
                  <span class="sub">
                    From <strong>{{ ticket.requester.name }}</strong>
                    <template v-if="ticket.requester.person && ticket.requester.role === 'vendor'"> ({{ ticket.requester.person }})</template>
                    · {{ ticket.requester.role }}
                    <span v-if="ticket.requester_suspended" class="pill sev-pill-critical">Suspended</span>
                  </span>
                </div>
                <div class="view-controls">
                  <select :value="ticket.priority" class="input small" aria-label="Priority" @change="updateTicket({ priority: $event.target.value })">
                    <option value="low">Low priority</option>
                    <option value="normal">Normal priority</option>
                    <option value="high">High priority</option>
                  </select>
                  <button v-if="ticket.status !== 'closed'" type="button" class="mini-btn" @click="updateTicket({ status: 'closed' })">Close</button>
                  <button v-else type="button" class="mini-btn" @click="updateTicket({ status: 'open' })">Reopen</button>
                </div>
              </div>
              <div class="context">
                <router-link v-if="ticket.order" :to="{ path: '/admin/orders', query: { order: ticket.order.id } }" class="context-chip">Order {{ ticket.order.order_number }} · {{ money(ticket.order.total_amount) }} →</router-link>
                <router-link
                  :to="ticket.requester.role === 'vendor' ? { path: '/admin/vendor-requests', query: { tab: 'active', vendor: ticket.requester.id } } : { path: '/admin/customers', query: { customer: ticket.requester.id } }"
                  class="context-chip"
                >{{ ticket.requester.role === "vendor" ? "Vendor profile" : "Customer profile" }} →</router-link>
                <span v-if="ticket.other_requests.length" class="sub">{{ ticket.other_requests.length }} earlier request{{ ticket.other_requests.length === 1 ? "" : "s" }}</span>
              </div>
              <ol class="messages">
                <li v-for="m in ticket.messages" :key="m.id" :class="{ admin: m.from_admin, auto: m.automated }">
                  <span class="msg-who">{{ m.author }} · {{ formatDateTime(m.created_at) }}</span>
                  <p v-if="m.body" class="bubble">{{ m.body }}</p>
                  <MessagePhotos :photos="m.attachments" :align-end="m.from_admin" />
                </li>
              </ol>
              <form v-if="ticket.status !== 'closed'" class="reply" @submit.prevent="sendReply">
                <textarea v-model="replyText" class="input" rows="4" maxlength="5000" placeholder="Write a reply. They'll get a notification."></textarea>
                <PhotoPicker v-model="replyPhotos" :disabled="busy" />
                <div class="reply-row">
                  <label class="sub">
                    After sending:
                    <select v-model="replyStatus" class="input small">
                      <option value="waiting">Wait for their answer</option>
                      <option value="resolved">Mark solved</option>
                      <option value="open">Keep it open</option>
                    </select>
                  </label>
                  <button type="submit" class="primary-btn" :disabled="busy || (replyText.trim().length < 2 && !replyPhotos.length)">{{ busy ? "Sending…" : "Send reply" }}</button>
                </div>
              </form>
              <p v-else class="sub">This request is closed. Reopen it to reply.</p>
            </template>
          </div>
        </section>

        <!-- ═══ Complaints ═══ -->
        <section v-else-if="tab === 'complaints'" class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Customer complaints</h2>
              <p class="panel-sub">Return and refund requests (decided by the vendor) and product reports.</p>
            </div>
            <div class="seg">
              <button v-for="f in COMPLAINT_FILTERS" :key="f.value" type="button" class="seg-btn" :class="{ active: complaintStatus === f.value }" @click="complaintStatus = f.value">{{ f.label }}</button>
            </div>
          </div>
          <div v-if="loading.complaints && !complaints.length" class="sk-list"><span v-for="n in 4" :key="n" class="sk sk-row"></span></div>
          <p v-else-if="!complaints.length" class="empty">No complaints here.</p>
          <ul v-else class="row-list">
            <li v-for="c in complaints" :key="c.key" tabindex="0" @click="openComplaint(c)" @keydown.enter="openComplaint(c)">
              <span class="kind" :class="`k-${c.kind}`">{{ KIND[c.kind] }}</span>
              <div class="row-main">
                <span class="strong">{{ c.customer?.name || "Customer" }} · {{ c.subject }}</span>
                <span class="sub ellipsis">“{{ c.reason }}”<template v-if="c.vendor"> · {{ c.vendor.store_name }}</template></span>
              </div>
              <div class="row-right">
                <span class="pill" :class="`cs-${c.status}`">{{ COMPLAINT_STATUS[c.status] ?? c.status }}</span>
                <span class="sub">{{ timeAgo(c.created_at) }}</span>
              </div>
            </li>
          </ul>
        </section>

        <!-- ═══ Activity ═══ -->
        <section v-else-if="tab === 'activity'" class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Order &amp; subscription activity</h2>
              <p class="panel-sub">Important events across the platform, newest first</p>
            </div>
            <div class="filter-row">
              <div class="seg">
                <button v-for="f in EVENT_TYPES" :key="f.value" type="button" class="seg-btn" :class="{ active: eventType === f.value }" @click="eventType = f.value">{{ f.label }}</button>
              </div>
              <select v-model.number="eventDays" class="input small">
                <option :value="1">Last 24 hours</option>
                <option :value="7">Last 7 days</option>
                <option :value="30">Last 30 days</option>
                <option :value="90">Last 90 days</option>
              </select>
            </div>
          </div>
          <div v-if="eventCounts" class="chips">
            <span v-for="(count, kind) in eventCounts" :key="kind" class="chip" :class="`tone-${EVENT[kind]?.tone ?? 'info'}`">{{ EVENT[kind]?.label ?? kind }} <b>{{ count }}</b></span>
          </div>
          <div v-if="loading.events && !events.length" class="sk-list"><span v-for="n in 6" :key="n" class="sk sk-row"></span></div>
          <p v-else-if="!events.length" class="empty">Nothing happened in this period.</p>
          <div v-else class="timeline">
            <div v-for="day in eventDaysGrouped" :key="day.label" class="day">
              <span class="day-label">{{ day.label }}</span>
              <ol>
                <li v-for="(e, i) in day.items" :key="i" :class="`tone-${e.tone}`" tabindex="0" @click="go(e.link)" @keydown.enter="go(e.link)">
                  <span class="ev-dot"></span>
                  <span class="ev-time">{{ formatTime(e.at) }}</span>
                  <span class="ev-title">{{ e.title }}</span>
                  <span v-if="e.subject" class="sub mono">{{ e.subject }}</span>
                </li>
              </ol>
            </div>
          </div>
        </section>

        <!-- ═══ Announcements ═══ -->
        <section v-else-if="tab === 'announcements'" class="split">
          <form class="panel" @submit.prevent="sendBroadcast">
            <h2 class="panel-title">Send an announcement</h2>
            <p class="panel-sub">Appears in their notification bell straight away. Suspended accounts are skipped.</p>
            <div class="field">
              <span>Send to</span>
              <div class="seg wide">
                <button v-for="a in AUDIENCES" :key="a.value" type="button" class="seg-btn" :class="{ active: compose.audience === a.value }" @click="compose.audience = a.value">
                  {{ a.label }} <span class="sub-inline">{{ broadcasts.sizes[a.value] ?? "" }}</span>
                </button>
              </div>
            </div>
            <label class="field"><span>Title</span><input v-model="compose.title" class="input" maxlength="140" required placeholder="e.g. Holiday delivery schedule" /></label>
            <label class="field"><span>Message</span><textarea v-model="compose.body" class="input" rows="5" maxlength="2000" required></textarea></label>
            <label class="field"><span>Link (optional)</span><input v-model="compose.link_url" class="input" placeholder="/journal/… or https://…" /></label>
            <label class="check"><input v-model="compose.important" type="checkbox" /> Mark as important</label>
            <p v-if="composeError" class="form-error">{{ composeError }}</p>
            <div class="form-actions">
              <button type="submit" class="primary-btn" :disabled="busy">{{ busy ? "Sending…" : `Send to ${broadcasts.sizes[compose.audience] ?? 0} people` }}</button>
            </div>
          </form>
          <div class="panel">
            <h2 class="panel-title">Sent</h2>
            <div v-if="loading.broadcasts && !broadcasts.items.length" class="sk-list"><span v-for="n in 3" :key="n" class="sk sk-row"></span></div>
            <p v-else-if="!broadcasts.items.length" class="empty">Nothing sent yet.</p>
            <ul v-else class="broadcast-list">
              <li v-for="b in broadcasts.items" :key="b.id">
                <div class="row-main">
                  <span class="strong">{{ b.title }} <span v-if="b.priority === 'important'" class="pill sev-pill-critical">Important</span></span>
                  <span class="sub">{{ AUDIENCE_LABEL[b.audience] }} · {{ b.sent_by }} · {{ formatDateTime(b.created_at) }}</span>
                  <div class="read-bar"><span :style="{ width: (b.recipients ? Math.round((b.read / b.recipients) * 100) : 0) + '%' }"></span></div>
                  <span class="sub">{{ b.read }} of {{ b.recipients }} read</span>
                </div>
                <button type="button" class="mini-btn danger" @click="retract(b)">Withdraw</button>
              </li>
            </ul>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "@/utils/snackbar";
import { useSidebarState } from "../../composables/useSidebarState";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import api from "../../plugins/axios";
import { linkedId } from "../../utils/hiddenIds";
import PhotoPicker from "../../components/support/PhotoPicker.vue";
import MessagePhotos from "../../components/support/MessagePhotos.vue";

const { toggleMobile } = useSidebarState();
const route = useRoute();
const router = useRouter();

const TABS = [
  { key: "inbox", label: "Tickets" },
  { key: "complaints", label: "Complaints" },
  { key: "alerts", label: "Alerts" },
  { key: "activity", label: "Activity" },
  { key: "announcements", label: "Announcements" },
];
const SEVERITY = { critical: "Critical", warning: "Warning", info: "FYI" };
const STATUS = { open: "Waiting for us", waiting: "Waiting for them", resolved: "Solved", closed: "Closed" };
const KIND = { return: "Return", refund: "Refund", product_report: "Report" };
const COMPLAINT_STATUS = { open: "Open", approved: "Approved", rejected: "Declined", upheld: "Upheld", dismissed: "Dismissed" };
const COMPLAINT_FILTERS = [
  { value: "pending", label: "Open" },
  { value: "resolved", label: "Resolved" },
  { value: "", label: "All" },
];
const EVENT_TYPES = [
  { value: "all", label: "All" },
  { value: "orders", label: "Orders" },
  { value: "subscriptions", label: "Subscriptions" },
];
const EVENT = {
  order_placed: { label: "Orders placed", tone: "info" },
  order_paid: { label: "Payments", tone: "good" },
  order_delivered: { label: "Delivered", tone: "good" },
  order_cancelled: { label: "Cancelled", tone: "muted" },
  order_refunded: { label: "Refunded / returned", tone: "warn" },
  payment_failed: { label: "Failed payments", tone: "bad" },
  return_requested: { label: "Return requests", tone: "warn" },
  subscription_paid: { label: "Subscriptions paid", tone: "good" },
  subscription_failed: { label: "Subscription failures", tone: "bad" },
  trial_started: { label: "Trials started", tone: "info" },
  subscription_ended: { label: "Plans ended", tone: "warn" },
};
const AUDIENCES = [
  { value: "vendors", label: "Vendors" },
  { value: "customers", label: "Customers" },
  { value: "all", label: "Everyone" },
];
const AUDIENCE_LABEL = { vendors: "Vendors", customers: "Customers", all: "Everyone" };

const tab = ref(TABS.some((t) => t.key === route.query.tab) ? route.query.tab : "inbox");
const loading = reactive({ alerts: false, tickets: false, complaints: false, events: false, broadcasts: false });
const busy = ref(false);
const summary = ref(null);

// ── Summary ──────────────────────────────────────────────────────
const fetchSummary = async () => {
  try {
    summary.value = (await api.get("/admin/support/summary")).data.data;
  } catch {
    // the cards just stay empty
  }
};

// ── Alerts ───────────────────────────────────────────────────────
const alerts = reactive({ items: [], failed: [], errors: [] });
const fetchAlerts = async () => {
  loading.alerts = true;
  try {
    const { data } = await api.get("/admin/support/alerts");
    Object.assign(alerts, { items: data.data, failed: data.failed_payments, errors: data.system_errors });
  } catch {
    toast.error("Alerts couldn't be loaded.");
  } finally {
    loading.alerts = false;
  }
};
const go = (link) => {
  if (!link) return;
  if (link.path === "/admin/support") setTab(link.query?.tab ?? "alerts");
  else router.push(link);
};
const openPayment = (p) => (p.source === "order" ? router.push({ path: "/admin/orders", query: { order: p.source_id } }) : router.push("/admin/subscription-revenue"));

// ── Inbox ────────────────────────────────────────────────────────
const tickets = ref([]);
const ticket = ref(null);
const openTicketId = ref(linkedId("ticket"));
const ticketFilters = reactive({ search: "", status: "active", role: "" });
const replyText = ref("");
const replyPhotos = ref([]);
const replyStatus = ref("waiting");

let ticketRequest = 0;
const fetchTickets = async () => {
  const id = ++ticketRequest;
  loading.tickets = true;
  try {
    const params = Object.fromEntries(Object.entries({ ...ticketFilters, search: ticketFilters.search.trim() }).filter(([, v]) => v !== ""));
    const { data } = await api.get("/admin/support/tickets", { params });
    if (id === ticketRequest) tickets.value = data.data;
  } catch {
    toast.error("The inbox couldn't be loaded.");
  } finally {
    if (id === ticketRequest) loading.tickets = false;
  }
};
let filterTimer = null;
watch(
  () => ({ ...ticketFilters }),
  () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(fetchTickets, 300);
  },
);

const loadTicket = async (id) => {
  try {
    const { data } = await api.get(`/admin/support/tickets/${id}`);
    if (openTicketId.value === id) ticket.value = data.data;
  } catch {
    toast.error("That request couldn't be opened.");
  }
};
const openTicket = (id) => {
  openTicketId.value = id;
  if (ticket.value?.id !== id) ticket.value = null;
  replyText.value = "";
  replyStatus.value = "waiting";
  router.replace({ query: { ...route.query, tab: "inbox", ticket: id } });
  loadTicket(id);
};
const sendReply = async () => {
  busy.value = true;
  try {
    // Photos can't go as JSON: send a form
    const form = new FormData();
    if (replyText.value.trim()) form.append("message", replyText.value.trim());
    form.append("status", replyStatus.value);
    replyPhotos.value.forEach((p) => form.append("attachments[]", p.file));
    const { data } = await api.post(`/admin/support/tickets/${ticket.value.id}/reply`, form, { timeout: 120000 });
    toast.success(data.message);
    replyText.value = "";
    replyPhotos.value = [];
    await Promise.all([loadTicket(ticket.value.id), fetchTickets(), fetchSummary()]);
  } catch (e) {
    toast.error(e.response?.data?.message || "The reply couldn't be sent.");
  } finally {
    busy.value = false;
  }
};
const updateTicket = async (changes) => {
  try {
    await api.post(`/admin/support/tickets/${ticket.value.id}/status`, changes);
    await Promise.all([loadTicket(ticket.value.id), fetchTickets(), fetchSummary()]);
  } catch {
    toast.error("That change couldn't be saved.");
  }
};

// ── Complaints ───────────────────────────────────────────────────
const complaints = ref([]);
const complaintStatus = ref("pending");
const fetchComplaints = async () => {
  loading.complaints = true;
  try {
    complaints.value = (await api.get("/admin/customers/complaints", { params: { status: complaintStatus.value || undefined } })).data.data;
  } catch {
    toast.error("Complaints couldn't be loaded.");
  } finally {
    loading.complaints = false;
  }
};
watch(complaintStatus, fetchComplaints);
const openComplaint = (c) => (c.order_id ? router.push({ path: "/admin/orders", query: { order: c.order_id } }) : router.push({ path: "/admin/reviews", query: { tab: "reports" } }));

// ── Activity ─────────────────────────────────────────────────────
const events = ref([]);
const eventCounts = ref(null);
const eventType = ref("all");
const eventDays = ref(7);
const fetchEvents = async () => {
  loading.events = true;
  try {
    const { data } = await api.get("/admin/support/events", { params: { type: eventType.value, days: eventDays.value } });
    events.value = data.data;
    eventCounts.value = data.counts;
  } catch {
    toast.error("Activity couldn't be loaded.");
  } finally {
    loading.events = false;
  }
};
watch([eventType, eventDays], fetchEvents);
const eventDaysGrouped = computed(() => {
  const groups = new Map();
  for (const e of events.value) {
    const label = new Date(e.at).toLocaleDateString("en-PH", { weekday: "long", month: "long", day: "numeric" });
    if (!groups.has(label)) groups.set(label, []);
    groups.get(label).push(e);
  }
  return [...groups].map(([label, items]) => ({ label, items }));
});

// ── Announcements ────────────────────────────────────────────────
const broadcasts = reactive({ items: [], sizes: {} });
const compose = reactive({ audience: "vendors", title: "", body: "", link_url: "", important: false });
const composeError = ref("");
const fetchBroadcasts = async () => {
  loading.broadcasts = true;
  try {
    const { data } = await api.get("/admin/broadcasts");
    Object.assign(broadcasts, { items: data.data, sizes: data.audience_sizes });
  } catch {
    toast.error("Announcements couldn't be loaded.");
  } finally {
    loading.broadcasts = false;
  }
};
const sendBroadcast = async () => {
  composeError.value = "";
  busy.value = true;
  try {
    const { data } = await api.post("/admin/broadcasts", {
      audience: compose.audience,
      title: compose.title,
      body: compose.body,
      link_url: compose.link_url.trim() || null,
      priority: compose.important ? "important" : "normal",
    });
    toast.success(data.message);
    Object.assign(compose, { title: "", body: "", link_url: "", important: false });
    fetchBroadcasts();
  } catch (e) {
    const errors = e.response?.data?.errors;
    composeError.value = errors ? Object.values(errors)[0]?.[0] : e.response?.data?.message || "It couldn't be sent.";
  } finally {
    busy.value = false;
  }
};
const retract = async (b) => {
  try {
    const { data } = await api.delete(`/admin/broadcasts/${b.id}`);
    toast.success(data.message);
    broadcasts.items = broadcasts.items.filter((x) => x.id !== b.id);
  } catch {
    toast.error("It couldn't be withdrawn.");
  }
};

// ── Tabs ─────────────────────────────────────────────────────────
const loadTab = (key) => {
  if (key === "alerts") fetchAlerts();
  if (key === "inbox") {
    fetchTickets();
    if (openTicketId.value) openTicket(openTicketId.value);
  }
  if (key === "complaints") fetchComplaints();
  if (key === "activity") fetchEvents();
  if (key === "announcements") fetchBroadcasts();
};
const setTab = (key) => {
  tab.value = key;
  router.replace({ query: { ...route.query, tab: key, ...(key === "inbox" ? {} : { ticket: null }) } });
  loadTab(key);
};
const refresh = () => {
  fetchSummary();
  loadTab(tab.value);
};

// ── Formatting ───────────────────────────────────────────────────
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
const money = (v) => peso.format(Number(v || 0));
const formatDateTime = (v) =>
  v ? new Date(v).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "—";
const formatTime = (v) => new Date(v).toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" });
const timeAgo = (v) => {
  if (!v) return "";
  const minutes = Math.floor((Date.now() - new Date(v)) / 60_000);
  if (minutes < 60) return minutes < 1 ? "just now" : `${minutes} min ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} h ago`;
  const days = Math.floor(hours / 24);
  return `${days} day${days === 1 ? "" : "s"} ago`;
};

onMounted(() => {
  fetchSummary();
  loadTab(tab.value);
});
</script>

<style scoped>
* {
  font-family: "Poppins", "Segoe UI", sans-serif;
  box-sizing: border-box;
}
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: var(--a-subtle);
}
.main-content {
  margin-left: 240px;
  flex: 1;
  min-width: 0;
  min-height: 100vh;
}
.page-body {
  padding: 18px 28px 40px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 20px 28px;
  background: var(--a-surface);
  border-bottom: 1px solid var(--a-border);
  position: sticky;
  top: 0;
  z-index: 100;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: var(--a-muted);
  cursor: pointer;
  padding: 4px;
}
.page-title {
  font-size: 22px;
  font-weight: 600;
  color: var(--a-text);
  margin: 0;
  line-height: 1.2;
}
.page-subtitle {
  font-size: 12px;
  color: var(--a-faint);
  margin: 2px 0 0;
}

/* Buttons */
.ghost-btn,
.primary-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 9px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}
.ghost-btn {
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  color: var(--a-text-2);
}
.primary-btn {
  border: none;
  background: var(--a-accent);
  color: var(--a-accent-ink);
}
.primary-btn:hover:not(:disabled) {
  background: var(--a-accent-deep);
}
.primary-btn:disabled {
  opacity: 0.55;
  cursor: default;
}
.mini-btn {
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  border-radius: 8px;
  padding: 6px 11px;
  font-size: 12px;
  font-weight: 500;
  color: var(--a-text-2);
  cursor: pointer;
  white-space: nowrap;
}
.mini-btn:hover {
  border-color: var(--a-accent-muted);
  color: var(--a-accent-text);
}
.mini-btn.danger {
  color: var(--a-red-text);
}
.link-btn {
  color: var(--a-accent-text);
  font-size: 12.5px;
  font-weight: 500;
  text-decoration: none;
}

/* Skeleton */
.sk {
  display: block;
  border-radius: 8px;
  background: linear-gradient(100deg, var(--a-subtle-2) 30%, var(--a-subtle) 50%, var(--a-subtle-2) 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
.sk-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.sk-row {
  height: 54px;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

/* Stats */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}
.stat {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  text-align: left;
  cursor: pointer;
}
.stat:hover {
  border-color: var(--a-accent-muted);
}
.stat.hot {
  border-color: var(--a-red-border);
  background: var(--a-red-soft);
}
.stat-label {
  font-size: 12px;
  color: var(--a-muted);
  font-weight: 500;
}
.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: var(--a-text);
}
.stat.hot .stat-value {
  color: var(--a-red-text);
}
.stat-hint {
  font-size: 11.5px;
  color: var(--a-faint);
}

/* Tabs */
.tabs {
  display: flex;
  gap: 6px;
  overflow-x: auto;
}
.tab {
  padding: 9px 14px;
  border-radius: 10px;
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  color: var(--a-text-2);
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
  cursor: pointer;
}
.tab.active {
  background: var(--a-accent-soft-2);
  border-color: var(--a-accent-muted);
  color: var(--a-accent-text);
}

/* Panels */
.panel {
  background: var(--a-surface);
  border: 1px solid var(--a-border);
  border-radius: 14px;
  padding: 18px 20px;
  min-width: 0;
}
.panel-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.panel-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--a-text);
  margin: 0 0 4px;
}
.panel-sub {
  font-size: 12px;
  color: var(--a-faint);
  margin: 0;
}
.split {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  align-items: start;
}
.empty {
  text-align: center;
  color: var(--a-faint);
  font-size: 13px;
  padding: 28px 0;
  margin: 0;
}
.calm {
  margin: 0;
  padding: 14px 16px;
  border-radius: 10px;
  background: var(--a-green-soft);
  color: var(--a-green-text);
  font-size: 13px;
  font-weight: 500;
}
.calm.small {
  padding: 10px 12px;
}
.strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--a-text);
}
.sub {
  display: block;
  font-size: 11.5px;
  color: var(--a-faint);
}
.sub-inline {
  font-size: 11px;
  color: var(--a-faint);
}
.mono {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
}
.ellipsis {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 10.5px;
  font-weight: 600;
  white-space: nowrap;
  background: var(--a-subtle-2);
  color: var(--a-muted);
}
.sev-pill-critical { background: var(--a-red-soft); color: var(--a-red-text); }
.sev-pill-warning { background: var(--a-amber-soft); color: var(--a-amber-text); }
.sev-pill-info { background: var(--a-blue-soft); color: var(--a-blue-text); }
.cs-open { background: var(--a-amber-soft); color: var(--a-amber-text); }
.cs-approved,
.cs-upheld { background: var(--a-green-soft); color: var(--a-green-text); }

/* Alerts */
.alert-list {
  list-style: none;
  margin: 10px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.alert {
  --c: var(--a-blue-text);
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--a-border);
  border-left: 4px solid var(--c);
  background: var(--a-subtle);
}
.alert.sev-critical { --c: var(--a-red-text); background: var(--a-red-soft); }
.alert.sev-warning { --c: var(--a-amber-text); }
.alert-count {
  min-width: 42px;
  font-size: 22px;
  font-weight: 700;
  color: var(--c);
  text-align: center;
}
.alert-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.row-list {
  list-style: none;
  margin: 0;
  padding: 0;
  border: 1px solid var(--a-border);
  border-radius: 12px;
  overflow: hidden;
}
.row-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 14px;
  border-bottom: 1px solid var(--a-border);
  cursor: pointer;
}
.row-list li:last-child {
  border-bottom: none;
}
.row-list li:hover {
  background: var(--a-subtle);
}
.row-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.row-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
  white-space: nowrap;
}
.error-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.error-list li {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}
.error-list code {
  font-size: 11.5px;
  color: var(--a-text-2);
  background: var(--a-subtle);
  border: 1px solid var(--a-border);
  border-radius: 6px;
  padding: 4px 6px;
  overflow-wrap: anywhere;
}
.kind {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  padding: 3px 7px;
  border-radius: 6px;
  white-space: nowrap;
}
.k-return { background: var(--a-accent-soft-2); color: var(--a-purple-text); }
.k-refund { background: var(--a-blue-soft); color: var(--a-blue-text); }
.k-product_report { background: var(--a-red-soft); color: var(--a-red-text); }

/* Inbox */
.inbox {
  display: grid;
  grid-template-columns: minmax(280px, 380px) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
.inbox-filters {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 12px;
}
.filter-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: center;
}
.filter-row .input {
  flex: 1;
}
.input {
  padding: 8px 11px;
  border: 1px solid var(--a-border);
  border-radius: 9px;
  font-size: 12.5px;
  color: var(--a-text);
  background: var(--a-surface);
  width: 100%;
}
.input.small {
  width: auto;
  padding: 6px 9px;
  font-size: 12px;
}
.input:focus {
  outline: none;
  border-color: var(--a-accent);
  box-shadow: 0 0 0 3px rgba(124, 111, 247, 0.12);
}
textarea.input {
  resize: vertical;
}
.ticket-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-height: 64vh;
  overflow-y: auto;
}
.ticket {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid transparent;
  background: transparent;
  text-align: left;
  cursor: pointer;
}
.ticket:hover {
  background: var(--a-subtle);
}
.ticket.active {
  background: var(--a-accent-soft);
  border-color: var(--a-accent-muted);
}
.ticket-top {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: center;
}
.ticket-top .sub {
  display: inline;
}
.who {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--a-text);
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.role {
  font-size: 9.5px;
  font-weight: 700;
  text-transform: uppercase;
  padding: 2px 6px;
  border-radius: 5px;
}
.role.vendor { background: var(--a-accent-soft-2); color: var(--a-purple-text); }
.role.customer { background: var(--a-pink-soft); color: var(--a-pink-text); }
.ticket-subject {
  font-size: 13px;
  color: var(--a-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.urgent {
  color: var(--a-red-text);
  font-size: 10px;
}
.ticket-meta {
  font-size: 11px;
  color: var(--a-faint);
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}
.status {
  margin-left: auto;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 20px;
}
.st-open { background: var(--a-amber-soft); color: var(--a-amber-text); }
.st-waiting { background: var(--a-blue-soft); color: var(--a-blue-text); }
.st-resolved { background: var(--a-green-soft); color: var(--a-green-text); }
.st-closed { background: var(--a-subtle-2); color: var(--a-muted); }
.view-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--a-border);
}
.view-head h2 {
  margin: 4px 0;
  font-size: 17px;
  color: var(--a-text);
}
.view-head .sub {
  display: block;
}
.view-controls {
  display: flex;
  gap: 8px;
  align-items: flex-start;
}
.context {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: center;
  margin: 12px 0;
}
.context .sub {
  display: inline;
}
.context-chip {
  font-size: 12px;
  padding: 5px 10px;
  border-radius: 20px;
  background: var(--a-accent-soft);
  color: var(--a-accent-text);
  text-decoration: none;
}
.messages {
  list-style: none;
  margin: 0 0 14px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 46vh;
  overflow-y: auto;
}
.messages li {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-width: 85%;
  /* Fit the words, not the whole width (own messages and notes are aligned below) */
  align-self: flex-start;
  align-items: flex-start;
}
/* Automatic replies the requester was sent */
.messages li.auto {
  align-self: center;
  align-items: center;
  max-width: 92%;
}
.messages li.auto .bubble {
  background: transparent;
  border: 1px dashed var(--a-border-strong);
  color: var(--a-muted);
  font-size: 13px;
}
.messages li.admin {
  align-self: flex-end;
  align-items: flex-end;
}
.msg-who {
  font-size: 11px;
  color: var(--a-faint);
}
.bubble {
  margin: 0;
  padding: 10px 14px;
  border-radius: 14px;
  background: var(--a-subtle-2);
  font-size: 13.5px;
  line-height: 1.55;
  color: var(--a-text);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.admin .bubble {
  background: var(--a-accent-soft-2);
}
.reply {
  display: flex;
  flex-direction: column;
  gap: 8px;
  border-top: 1px solid var(--a-border);
  padding-top: 12px;
}
.reply-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.reply-row label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

/* Activity */
.seg {
  display: inline-flex;
  padding: 3px;
  background: var(--a-subtle-2);
  border-radius: 9px;
  gap: 2px;
}
.seg.wide {
  display: flex;
}
.seg.wide .seg-btn {
  flex: 1;
}
.seg-btn {
  border: none;
  background: transparent;
  padding: 6px 12px;
  border-radius: 7px;
  font-size: 12px;
  font-weight: 500;
  color: var(--a-muted);
  cursor: pointer;
}
.seg-btn.active {
  background: var(--a-surface);
  color: var(--a-accent-text);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}
.chips {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.chip {
  font-size: 11.5px;
  padding: 4px 10px;
  border-radius: 20px;
  background: var(--a-subtle-2);
  color: var(--a-text-2);
}
.chip b {
  margin-left: 2px;
}
.chip.tone-good { background: var(--a-green-soft); color: var(--a-green-text); }
.chip.tone-bad { background: var(--a-red-soft); color: var(--a-red-text); }
.chip.tone-warn { background: var(--a-amber-soft); color: var(--a-amber-text); }
.chip.tone-info { background: var(--a-blue-soft); color: var(--a-blue-text); }
.timeline {
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.day-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--a-faint);
  margin-bottom: 6px;
}
.timeline ol {
  list-style: none;
  margin: 0;
  padding: 0;
  border-left: 2px solid var(--a-border);
  margin-left: 5px;
}
.timeline li {
  --dot: #0ea5e9;
  position: relative;
  display: flex;
  align-items: baseline;
  gap: 10px;
  padding: 7px 10px 7px 18px;
  cursor: pointer;
  border-radius: 0 8px 8px 0;
  flex-wrap: wrap;
}
.timeline li:hover {
  background: var(--a-subtle);
}
.timeline li.tone-good { --dot: #16a34a; }
.timeline li.tone-bad { --dot: #dc2626; }
.timeline li.tone-warn { --dot: #d97706; }
.timeline li.tone-muted { --dot: #94a3b8; }
.ev-dot {
  position: absolute;
  left: -6px;
  top: 13px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--a-surface);
  border: 3px solid var(--dot);
}
.ev-time {
  font-size: 11.5px;
  color: var(--a-faint);
  min-width: 64px;
}
.ev-title {
  flex: 1;
  font-size: 13px;
  color: var(--a-text);
}
.timeline .sub {
  display: inline;
}

/* Announcements */
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 12px;
  font-size: 12px;
  font-weight: 500;
  color: var(--a-text-2);
}
.check {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
  font-size: 13px;
  color: var(--a-text-2);
}
.check input {
  accent-color: var(--a-accent);
}
.form-error {
  color: var(--a-red-text);
  font-size: 12.5px;
  margin: 10px 0 0;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
.broadcast-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.broadcast-list li {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 12px;
  border: 1px solid var(--a-border);
  border-radius: 12px;
}
.read-bar {
  height: 5px;
  border-radius: 5px;
  background: var(--a-subtle-2);
  overflow: hidden;
  margin-top: 6px;
}
.read-bar span {
  display: block;
  height: 100%;
  background: var(--a-accent);
}

@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
}
@media (max-width: 1100px) {
  .inbox,
  .split {
    grid-template-columns: minmax(0, 1fr);
  }
  .stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 968px) {
  .main-content {
    margin-left: 0;
  }
  .mobile-toggle {
    display: flex;
  }
}
@media (max-width: 640px) {
  .content-header {
    padding: 16px;
  }
  .page-body {
    padding: 14px 16px 32px;
  }
  .alert {
    flex-wrap: wrap;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
}
</style>
