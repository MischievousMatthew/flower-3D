<template>
  <HrPage title="Refunds" subtitle="Refund requests from customers, what was decided, the money sent back, and whether it all matches the books.">
    <template #actions>
      <RangePicker @change="load(1)" />
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="refresh()">Refresh</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="refresh()">Try again</button>
    </div>

    <!-- Headline numbers -->
    <section class="fx-kpis" aria-label="Refunds this period">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article v-for="k in kpis" :key="k.key" class="sp-card fx-kpi" :class="{ hero: k.hero }">
          <span class="fx-kpi-label"><span class="rf-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong>{{ k.value }}</strong>
          <span class="fx-kpi-sub">{{ k.sub }}</span>
        </article>
      </template>
    </section>

    <!-- Where the cases are -->
    <section class="sp-card rf-flow-card" aria-label="Refund pipeline">
      <div class="sp-card-head"><div><h2>Where every refund stands</h2><p>Cases opened in this period, plus any still open from before</p></div></div>
      <div v-if="!data" class="bc-skeleton sp-sk-line w50"></div>
      <ol v-else class="rf-flow">
        <li v-for="(s, i) in FLOW" :key="s" :class="[STATUS[s].tone, { on: status === s }]">
          <button type="button" @click="setStatus(status === s ? '' : s)">
            <span class="rf-step">{{ i + 1 }}</span>
            <span class="rf-step-label">{{ STATUS[s].label }}</span>
            <strong>{{ sum.statuses[s].count }}</strong>
            <small>{{ money(sum.statuses[s].amount) }}</small>
            <em>{{ STATUS[s].hint }}</em>
          </button>
        </li>
        <li class="slate closed" :class="{ on: status === 'closed' }">
          <button type="button" @click="setStatus(status === 'closed' ? '' : 'closed')">
            <span class="rf-step-label">Declined or withdrawn</span>
            <strong>{{ sum.statuses.closed.count }}</strong>
            <em>no money back</em>
          </button>
        </li>
      </ol>
    </section>

    <div class="rf-two">
      <!-- Reconciliation -->
      <section class="sp-card" aria-label="Reconciliation">
        <div class="sp-card-head">
          <div><h2>Reconciliation</h2><p>Agreed refunds checked against the sales records and the books</p></div>
          <button v-if="data && rec.issue_count" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="setStatus('issues')">Review {{ rec.issue_count }}</button>
        </div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <template v-else>
          <div class="rf-rec">
            <div class="rf-meter" role="img" :aria-label="`${rec.matched} of ${rec.checked} match`"><span :style="{ width: `${matchPct}%` }"></span></div>
            <p><b>{{ rec.matched }}</b> of {{ rec.checked }} refunds match<template v-if="rec.issue_count"> · <span class="warn">{{ rec.issue_count }} to check</span></template></p>
          </div>
          <ul v-if="rec.unmatched_books.length" class="rf-issues">
            <li v-for="u in rec.unmatched_books" :key="`${u.order_id}-${u.at}`">
              <span class="rf-issue-ic" v-html="ICONS.alert"></span>
              <div class="grow"><strong>{{ u.order_number ?? "No order" }}</strong><span>{{ u.message }} · {{ formatDate(u.at) }}</span></div>
              <b>{{ money(u.amount) }}</b>
            </li>
          </ul>
          <p v-else-if="!rec.issue_count && rec.checked" class="sp-hint rf-ok"><span v-html="ICONS.check"></span>Every agreed refund lines up with its order and the books.</p>
        </template>
      </section>

      <!-- Why and how -->
      <section class="sp-card" aria-label="Reasons">
        <div class="sp-card-head"><div><h2>Why customers asked</h2><p>And how the money went back</p></div></div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <p v-else-if="!sum.reasons.length" class="sp-hint">No refund requests in this period.</p>
        <template v-else>
          <ul class="rf-reasons">
            <li v-for="r in sum.reasons" :key="r.label">
              <div class="rf-reason-top"><span>{{ r.label }}</span><b>{{ countText(r.count, "case") }}</b></div>
              <div class="rf-bar"><span :style="{ width: `${reasonPct(r.count)}%` }"></span></div>
            </li>
          </ul>
          <div v-if="sum.methods.length" class="rf-methods">
            <span v-for="m in sum.methods" :key="m.label" class="sp-chip slate">{{ m.label }} · {{ money(m.amount) }}</span>
          </div>
        </template>
      </section>
    </div>

    <!-- Cases -->
    <section class="sp-card rf-list" aria-label="Refund cases">
      <div class="rf-list-head">
        <div class="sp-seg rf-tabs" role="tablist" aria-label="Status">
          <button type="button" role="tab" :aria-selected="!status" :class="{ on: !status }" @click="setStatus('')">All</button>
          <button v-for="s in [...FLOW, 'closed']" :key="s" type="button" role="tab" :aria-selected="status === s" :class="{ on: status === s }" @click="setStatus(s)">{{ STATUS[s].label }}</button>
          <button type="button" role="tab" :aria-selected="status === 'issues'" :class="{ on: status === 'issues' }" @click="setStatus('issues')">Needs a look<span v-if="rec.issue_count" class="count">{{ rec.issue_count }}</span></button>
        </div>
        <label class="sp-search">
          <span v-html="ICONS.search"></span>
          <input v-model="search" class="sp-input" type="search" placeholder="Order, customer or reference" aria-label="Search refunds" />
        </label>
      </div>

      <ul v-if="!data" class="rf-rows">
        <li v-for="n in 4" :key="n" class="rf-row"><div class="bc-skeleton fx-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
      </ul>
      <div v-else-if="!data.rows.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS[status === 'issues' ? 'check' : 'restore']"></span>
        <h3>{{ status === "issues" ? "Nothing to check" : search || status ? "No refunds match" : "No refund requests" }}</h3>
        <p>{{ status === "issues" ? "Every refund matches its order and the books." : search || status ? "Try another status or search." : "Refund requests appear here when customers open them from their orders." }}</p>
      </div>
      <template v-else>
        <ul class="rf-rows" :class="{ dim: loading }">
          <li v-for="r in list.rows.value" :key="r.id" class="rf-row">
            <span class="fx-icon" :class="`tone-${STATUS[r.status].tone}`" v-html="ICONS.restore"></span>
            <div class="grow">
              <strong>{{ r.order.order_number }} <span class="muted">· {{ r.customer }}</span><span v-if="r.carried_over" class="sp-chip slate rf-mini">from before</span></strong>
              <span class="rf-reason">{{ r.reason_label }}<template v-if="r.reason"> · “{{ r.reason }}”</template></span>
              <span class="rf-sub">
                Order {{ money(r.order.total) }} · {{ PAY[r.order.payment_method] ?? r.order.payment_method }}<template v-if="r.order.paid_at"> · paid {{ formatDate(r.order.paid_at) }}</template> · opened {{ formatDate(r.opened_at) }}
                <template v-if="r.refund_method_label"> · sent by {{ r.refund_method_label }}<template v-if="r.refund_reference"> ({{ r.refund_reference }})</template></template>
              </span>
              <span v-for="i in r.issues" :key="i.code" class="rf-issue-msg">{{ i.message }}</span>
            </div>
            <div class="rf-state">
              <span class="sp-chip" :class="STATUS[r.status].tone">{{ STATUS[r.status].label }}</span>
              <small>{{ STAGE[r.stage] ?? "" }}</small>
            </div>
            <div class="rf-amt">
              <strong>{{ money(r.amount) }}</strong>
              <small>{{ RESOLUTION[r.resolution] ?? "" }}{{ r.agreed ? " · agreed" : " · asked" }}</small>
              <small v-if="r.books" class="pos">on the books {{ money(r.books.amount) }}</small>
            </div>
            <div class="rf-actions">
              <button v-if="canRecord && r.stage === 'awaiting_refund'" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="openSent(r)">Record as sent</button>
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="openDetail(r)">Details</button>
            </div>
          </li>
        </ul>
        <ShowMore v-if="data && data.rows.length" tone="vendor" :has-more="list.hasMore.value" :loading="list.loadingMore.value" :shown="list.rows.value.length" :total="list.total.value" @more="list.showMore()" />
      </template>
    </section>

    <!-- Detail -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="detailRow" class="sp-backdrop" @click="detailRow = null"></div></Transition>
      <Transition name="sp-slide">
        <aside v-if="detailRow" class="sp-drawer rf-drawer" role="dialog" aria-modal="true" aria-labelledby="rf-d-title">
          <div class="sp-drawer-head">
            <div>
              <h3 id="rf-d-title">{{ detailRow.order.order_number }}</h3>
              <p>{{ detailRow.customer }} · {{ detailRow.type === "return" ? "Return and refund" : "Refund" }}</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="detailRow = null"><span v-html="ICONS.close"></span></button>
          </div>
          <div class="sp-drawer-body">
            <div class="rf-d-status" :class="STATUS[detailRow.status].tone">
              <span class="sp-chip" :class="STATUS[detailRow.status].tone">{{ STATUS[detailRow.status].label }}</span>
              <strong>{{ money(detailRow.amount) }}</strong>
              <small>{{ STAGE[detailRow.stage] ?? "" }}</small>
            </div>

            <h4>Linked records</h4>
            <dl class="rf-d-list">
              <div><dt>Order</dt><dd>{{ detailRow.order.order_number }} · {{ money(detailRow.order.total) }}</dd></div>
              <div><dt>Payment</dt><dd>{{ PAY[detailRow.order.payment_method] ?? detailRow.order.payment_method }} · {{ detailRow.order.payment_status }}<template v-if="detailRow.order.paid_at"> on {{ formatDate(detailRow.order.paid_at) }}</template></dd></div>
              <div v-if="detailRow.order.payment_reference"><dt>Payment reference</dt><dd class="mono">{{ detailRow.order.payment_reference }}</dd></div>
              <div><dt>Sale on the books</dt><dd>{{ detailRow.order.payment_booked === null ? "Not recorded" : money(detailRow.order.payment_booked) }}</dd></div>
              <div><dt>Refund on the books</dt><dd>{{ detailRow.books ? `${money(detailRow.books.amount)} on ${formatDate(detailRow.books.at)}` : "Not yet" }}</dd></div>
              <div v-if="detailRow.refund_method_label"><dt>Sent by</dt><dd>{{ detailRow.refund_method_label }}<template v-if="detailRow.refund_reference"> · <span class="mono">{{ detailRow.refund_reference }}</span></template></dd></div>
            </dl>
            <ul v-if="detailRow.issues.length" class="rf-d-issues">
              <li v-for="i in detailRow.issues" :key="i.code"><span v-html="ICONS.alert"></span>{{ i.message }}</li>
            </ul>

            <h4>Request and decisions</h4>
            <div v-if="!detail" class="bc-skeleton sp-sk-block"></div>
            <template v-else>
              <p class="rf-d-reason"><b>{{ detail.reason_label }}</b><template v-if="detail.reason"> · {{ detail.reason }}</template></p>
              <a v-if="detail.media_url" :href="detail.media_url" target="_blank" rel="noopener" class="rf-d-media"><span v-html="ICONS.image"></span>Photo or video from the customer</a>
              <ol class="rf-timeline">
                <li v-for="m in detail.messages" :key="m.id" :class="m.author_role">
                  <span class="dot"></span>
                  <div>
                    <strong>{{ KIND[m.kind] ?? humanize(m.kind) }}<template v-if="m.amount"> · {{ money(m.amount) }}</template></strong>
                    <span class="who">{{ m.author_name }} · {{ formatDate(m.at, true) }}</span>
                    <p v-if="m.body">{{ m.body }}</p>
                  </div>
                </li>
              </ol>
            </template>
          </div>
          <div v-if="canRecord && detailRow.stage === 'awaiting_refund'" class="sp-drawer-foot">
            <button type="button" class="sp-btn sp-btn-primary" @click="openSent(detailRow)">Record refund as sent</button>
          </div>
        </aside>
      </Transition>
    </Teleport>

    <!-- Record as sent -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="sentForm" class="sp-backdrop rf-top" @click="sentForm = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="sentForm" class="sp-modal-wrap rf-top">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="rf-s-title" novalidate @submit.prevent="saveSent">
            <div class="sp-modal-head">
              <div>
                <h3 id="rf-s-title">Record refund as sent</h3>
                <p>{{ sentForm.order_number }} · {{ money(sentForm.amount) }} to {{ sentForm.customer }}</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="sentForm = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sp-field">
              <span class="sp-label">Sent by</span>
              <div class="rf-picks">
                <button v-for="m in data?.refund_methods ?? []" :key="m.value" type="button" class="sp-pick" :class="{ on: sentForm.method === m.value }" @click="sentForm.method = m.value">{{ m.label }}</button>
              </div>
            </div>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span>Reference <small>(optional)</small></span>
                <input v-model="sentForm.reference" class="sp-input" maxlength="120" placeholder="Transfer or GCash reference" />
              </label>
              <label class="sp-field">
                <span>Note <small>(optional)</small></span>
                <input v-model="sentForm.note" class="sp-input" maxlength="500" />
              </label>
            </div>
            <p class="sp-hint">The customer is told it was sent. It reaches the books once they confirm they received it.</p>
            <p v-if="formError" class="sp-error">{{ formError }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="sentForm = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="saving || !sentForm.method">{{ saving ? "Saving…" : "Record as sent" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { toast } from "@/utils/snackbar";
import api from "../../../plugins/axios";
import ShowMore from "../../../components/common/ShowMore.vue";
import { useShowMoreInto } from "../../../composables/useShowMore";
import HrPage from "../../../components/hr/HrPage.vue";
import RangePicker from "../../../components/vendor/finance/RangePicker.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { PAYMENT_LABEL, financeRange, formatDate, money } from "../../../components/vendor/finance/financeUi";
import { useAuth } from "../../../composables/useAuth";
import { useAssignment } from "../../../composables/useAssignment";
import "../../../components/vendor/finance/finance.css";

const STATUS = {
  requested: { label: "Requested", tone: "amber", hint: "being agreed with the customer" },
  approved: { label: "Approved", tone: "blue", hint: "agreed, waiting to be paid" },
  processing: { label: "Processing", tone: "violet", hint: "sent, customer to confirm" },
  completed: { label: "Completed", tone: "green", hint: "received and on the books" },
  closed: { label: "Closed", tone: "slate", hint: "declined or withdrawn" },
};
const FLOW = ["requested", "approved", "processing", "completed"];
const STAGE = {
  awaiting_vendor: "Waiting for the shop's answer",
  awaiting_customer: "Waiting for the customer",
  awaiting_return: "Item on its way back",
  awaiting_refund: "Ready to pay back",
  confirm_refund: "Customer to confirm",
  completed: "Done",
  rejected: "Declined by the shop",
  withdrawn: "Withdrawn by the customer",
};
const RESOLUTION = { full_refund: "Full refund", partial_refund: "Partial refund", replacement: "Replacement" };
const PAY = { ...PAYMENT_LABEL, ewallet: "E-wallet" };
const KIND = {
  opened: "Request opened",
  offer: "Offer made",
  counter: "Counter-offer",
  accepted: "Offer accepted",
  rejected: "Declined",
  withdrawn: "Withdrawn",
  message: "Message",
  item_received: "Returned item received",
  refund_sent: "Refund sent",
  refund_confirmed: "Refund confirmed by the customer",
  refund_missing: "Customer hasn't received it",
  replacement_sent: "Replacement sent",
};
const humanize = (k) => (k ? k.charAt(0).toUpperCase() + k.slice(1).replace(/_/g, " ") : "Update");

const { user } = useAuth();
const { can } = useAssignment();
const isEmployee = computed(() => user.value?.type === "employee");
// Finance staff use the ERP routes (they check the Refunds permission); the shop owner uses the vendor ones
const base = computed(() => (isEmployee.value ? "finance/refunds" : "vendor/books/refund-cases"));
const canRecord = computed(() => !isEmployee.value || can("refunds", "edit"));

const data = ref(null);
const error = ref("");
const loading = ref(false);
const page = ref(1);
const status = ref("");
const search = ref("");

const detailRow = ref(null);
const detail = ref(null);
const sentForm = ref(null);
const saving = ref(false);
const formError = ref("");

const sum = computed(() => data.value?.summary ?? { statuses: {}, reasons: [], methods: [] });
const rec = computed(() => data.value?.reconciliation ?? { checked: 0, matched: 0, issue_count: 0, unmatched_books: [] });
const matchPct = computed(() => (rec.value.checked ? Math.round((rec.value.matched / rec.value.checked) * 100) : 100));
const countText = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;
const reasonPct = (n) => {
  const top = Math.max(...sum.value.reasons.map((r) => r.count), 1);
  return Math.round((n / top) * 100);
};

const kpis = computed(() => {
  const s = sum.value;
  const owedCount = s.statuses.approved.count + s.statuses.processing.count;
  return [
    { key: "refunded", label: "Refunded", icon: "restore", tone: "pink", value: money(s.refunded), sub: s.refund_rate === null ? "no sales to compare" : `${s.refund_rate}% of ${money(s.sales)} sales` },
    { key: "owed", label: "Owed to customers", icon: "wallet", tone: "blue", value: money(s.owed), sub: `${countText(owedCount, "refund")} agreed, not yet confirmed` },
    { key: "waiting", label: "Waiting for a decision", icon: "clock", tone: "amber", value: String(s.statuses.requested.count), sub: `${money(s.statuses.requested.amount)} asked for` },
    { key: "time", label: "Time to complete", icon: "check", tone: "green", hero: true, value: s.average_days === null ? "—" : `${s.average_days} days`, sub: "from request to confirmed, on average" },
  ];
});

// 10 refunds first, then 10 more, then 20 at a time
const list = useShowMoreInto(data, "rows", async (p) =>
  (await api.get(base.value, {
    params: {
      from: financeRange.from,
      to: financeRange.to,
      page: p,
      status: status.value && status.value !== "issues" ? status.value : undefined,
      issues: status.value === "issues" ? 1 : undefined,
      search: search.value.trim() || undefined,
    },
  })).data.data,
);
/** Reloads after a change, keeping as many refunds showing */
const refresh = () => list.refresh().catch((e) => (error.value = apiMessage(e, "Couldn't load refunds for this period.")));
async function load() {
  error.value = "";
  loading.value = true;
  try {
    await list.load();
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load refunds for this period.");
  } finally {
    loading.value = false;
  }
}

function setStatus(s) {
  status.value = s;
  load(1);
}

async function openDetail(r) {
  detailRow.value = r;
  detail.value = null;
  try {
    const res = await api.get(`${base.value}/${r.id}`);
    if (detailRow.value?.id === r.id) detail.value = res.data.data;
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't load this refund."));
  }
}

function openSent(r) {
  formError.value = "";
  sentForm.value = { id: r.id, order_number: r.order.order_number, customer: r.customer, amount: r.amount, method: r.order.payment_method === "cod" ? "cash" : "gcash", reference: "", note: "" };
}

async function saveSent() {
  saving.value = true;
  formError.value = "";
  const f = sentForm.value;
  try {
    const res = await api.post(`${base.value}/${f.id}/sent`, { method: f.method, reference: f.reference.trim() || undefined, note: f.note.trim() || undefined });
    toast.success(res.data.message);
    sentForm.value = null;
    detailRow.value = null;
    refresh();
  } catch (e) {
    formError.value = apiMessage(e, "Couldn't record the refund.");
  } finally {
    saving.value = false;
  }
}

let searchTimer = null;
watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => load(1), 300);
});

onMounted(() => load(1));
</script>

<style scoped>
.fx-kpi.hero {
  background: linear-gradient(150deg, var(--bc-accent-soft) 0%, var(--v-surface) 60%);
  border-color: var(--bc-accent-muted);
}
.rf-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.rf-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.muted {
  color: var(--v-faint);
  font-weight: 400;
}
.warn {
  color: var(--v-amber-text);
}
.pos {
  color: var(--v-green-text);
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 11.5px;
}

/* Pipeline */
.rf-flow-card {
  margin-bottom: 18px;
}
.rf-flow {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr)) minmax(0, 0.8fr);
  gap: 10px;
}
.rf-flow li {
  position: relative;
}
.rf-flow li:not(:nth-last-child(-n + 2))::after {
  content: "";
  position: absolute;
  top: 24px;
  right: -10px;
  width: 10px;
  height: 2px;
  background: var(--v-border-strong);
}
.rf-flow button {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 12px 14px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-subtle);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, transform 0.15s;
}
.rf-flow button:hover,
.rf-flow li.on button {
  border-color: var(--bc-accent);
  transform: translateY(-1px);
}
.rf-flow li.closed button {
  background: transparent;
  border-style: dashed;
}
.rf-step {
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  margin-bottom: 4px;
  border-radius: 50%;
  font-size: 11px;
  font-weight: 600;
}
.rf-flow .amber .rf-step { background: var(--v-amber-soft); color: var(--v-amber-text); }
.rf-flow .blue .rf-step { background: var(--v-blue-soft); color: var(--v-blue-text); }
.rf-flow .violet .rf-step { background: var(--v-purple-soft); color: var(--v-purple-text); }
.rf-flow .green .rf-step { background: var(--v-green-soft); color: var(--v-green-text); }
.rf-step-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--v-text-2);
}
.rf-flow strong {
  font-size: 22px;
  font-weight: 600;
  color: var(--v-text);
  line-height: 1.2;
}
.rf-flow small {
  font-size: 12px;
  color: var(--v-text-2);
}
.rf-flow em {
  font-style: normal;
  font-size: 11.5px;
  color: var(--v-faint);
}

.rf-two {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 18px;
}

/* Reconciliation */
.rf-rec {
  display: flex;
  align-items: center;
  gap: 14px;
}
.rf-rec p {
  margin: 0;
  font-size: 13px;
  color: var(--v-text-2);
  white-space: nowrap;
}
.rf-rec b {
  color: var(--v-text);
}
.rf-meter {
  flex: 1;
  height: 10px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-red-soft);
}
.rf-meter span {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: #48bb78;
}
.rf-issues {
  list-style: none;
  margin: 14px 0 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.rf-issues li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--v-amber-soft) 45%, transparent);
  border: 1px solid var(--v-amber-soft);
}
.rf-issues .grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.rf-issues strong {
  font-size: 13px;
  color: var(--v-text);
}
.rf-issues .grow span {
  font-size: 12px;
  color: var(--v-amber-text);
}
.rf-issues b {
  font-size: 13px;
  color: var(--v-text);
}
.rf-issue-ic {
  flex: none;
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.rf-issue-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.rf-ok {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 14px 0 0;
  color: var(--v-green-text);
}
.rf-ok :deep(svg) {
  width: 16px;
  height: 16px;
}

/* Reasons */
.rf-reasons {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 10px;
}
.rf-reason-top {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 12.5px;
  color: var(--v-text-2);
  margin-bottom: 4px;
}
.rf-reason-top b {
  color: var(--v-text);
  font-weight: 600;
}
.rf-bar {
  height: 6px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.rf-bar span {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: #f687b3;
}
.rf-methods {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid var(--v-border);
}

/* List */
.rf-list {
  min-width: 0;
}
.rf-list-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}
.rf-tabs {
  flex: none;
}
.rf-rows {
  list-style: none;
  margin: 0;
  padding: 0;
  transition: opacity 0.2s;
}
.rf-rows.dim {
  opacity: 0.55;
}
.rf-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 6px;
  border-top: 1px solid var(--v-border);
  border-radius: 10px;
  transition: background 0.15s;
}
.rf-row:hover {
  background: var(--v-subtle);
}
.rf-row .grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.rf-row .grow > * {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rf-row strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.rf-mini {
  margin-left: 6px;
  padding: 1px 7px;
  font-size: 10.5px;
  vertical-align: middle;
}
.rf-reason {
  font-size: 12.5px;
  color: var(--v-text);
}
.rf-sub {
  font-size: 11.5px;
  color: var(--v-faint);
}
.rf-issue-msg {
  font-size: 12px;
  color: var(--v-amber-text);
  white-space: normal !important;
}
.rf-state {
  flex: none;
  width: 150px;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
}
.rf-state small {
  font-size: 11px;
  color: var(--v-faint);
  text-align: right;
}
.rf-amt {
  flex: none;
  width: 140px;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}
.rf-amt strong {
  font-size: 14px;
}
.rf-amt small {
  font-size: 11.5px;
  color: var(--v-muted);
  white-space: nowrap;
}
.rf-amt small.pos {
  color: var(--v-green-text);
}
.rf-actions {
  flex: none;
  width: 196px;
  display: flex;
  justify-content: flex-end;
  gap: 6px;
}
.fx-pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid var(--v-border);
  font-size: 12.5px;
  color: var(--v-muted);
}
.fx-pager div {
  display: flex;
  gap: 6px;
}

/* Drawer */
.rf-drawer {
  width: min(520px, 100%);
}
.rf-drawer h4 {
  margin: 20px 0 8px;
  font-size: 11.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--v-faint);
}
.rf-d-status {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px;
  border-radius: 14px;
  background: var(--v-subtle);
}
.rf-d-status strong {
  font-size: 20px;
  color: var(--v-text);
}
.rf-d-status small {
  margin-left: auto;
  font-size: 12px;
  color: var(--v-muted);
  text-align: right;
}
.rf-d-list {
  margin: 0;
  display: grid;
  gap: 8px;
}
.rf-d-list div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
}
.rf-d-list dt {
  color: var(--v-muted);
}
.rf-d-list dd {
  margin: 0;
  color: var(--v-text);
  text-align: right;
}
.rf-d-issues {
  list-style: none;
  margin: 12px 0 0;
  padding: 0;
  display: grid;
  gap: 6px;
}
.rf-d-issues li {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 10px;
  background: var(--v-amber-soft);
  font-size: 12.5px;
  color: var(--v-amber-text);
}
.rf-d-issues :deep(svg) {
  flex: none;
  width: 14px;
  height: 14px;
}
.rf-d-reason {
  margin: 0 0 8px;
  font-size: 13px;
  color: var(--v-text-2);
}
.rf-d-reason b {
  color: var(--v-text);
}
.rf-d-media {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 10px;
  font-size: 12.5px;
  color: var(--bc-accent-strong);
}
.rf-d-media :deep(svg) {
  width: 14px;
  height: 14px;
}
.rf-timeline {
  list-style: none;
  margin: 0;
  padding: 0 0 0 4px;
}
.rf-timeline li {
  position: relative;
  display: flex;
  gap: 12px;
  padding-bottom: 14px;
}
.rf-timeline li:not(:last-child)::before {
  content: "";
  position: absolute;
  left: 5px;
  top: 14px;
  bottom: 0;
  width: 2px;
  background: var(--v-border);
}
.rf-timeline .dot {
  flex: none;
  width: 12px;
  height: 12px;
  margin-top: 3px;
  border-radius: 50%;
  background: var(--v-blue-soft);
  border: 2px solid var(--v-blue-text);
}
.rf-timeline li.vendor .dot {
  background: var(--v-green-soft);
  border-color: var(--v-green-text);
}
.rf-timeline div {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.rf-timeline strong {
  font-size: 13px;
  color: var(--v-text);
}
.rf-timeline .who {
  font-size: 11.5px;
  color: var(--v-faint);
}
.rf-timeline p {
  margin: 4px 0 0;
  padding: 8px 10px;
  border-radius: 10px;
  background: var(--v-subtle);
  font-size: 12.5px;
  color: var(--v-text-2);
}
.sp-modal > .sp-field {
  margin-bottom: 14px;
}
.rf-picks {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.rf-top {
  z-index: 1300;
}

@media (max-width: 1100px) {
  .rf-flow {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  .rf-flow li::after {
    display: none;
  }
  .rf-two {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 720px) {
  .rf-flow {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .rf-rec {
    flex-direction: column;
    align-items: stretch;
  }
  .rf-rec p {
    white-space: normal;
  }
  .rf-row {
    flex-wrap: wrap;
  }
  .rf-row .grow {
    flex-basis: calc(100% - 60px);
  }
  .rf-state {
    width: auto;
    flex-direction: row;
    align-items: center;
    margin-left: 56px;
  }
  .rf-amt {
    width: auto;
    margin-left: auto;
  }
  .rf-actions {
    width: 100%;
    justify-content: flex-start;
    padding-left: 56px;
  }
}
</style>
