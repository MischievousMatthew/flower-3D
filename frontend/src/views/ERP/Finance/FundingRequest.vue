<template>
  <HrPage title="Funding requests" subtitle="Stock purchases Procurement wants to make. The assigned approver reviews each one and approves or rejects it.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="fetchRequests"><span v-html="ICONS.restore"></span>Refresh</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="fetchRequests">Try again</button>
    </div>

    <!-- Totals -->
    <section class="fx-kpis" aria-label="Summary">
      <template v-if="!loaded">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article v-for="k in kpis" :key="k.key" class="sp-card fx-kpi" :class="{ hero: k.hero }">
          <span class="fx-kpi-label"><span class="fr-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong>{{ k.value }}</strong>
          <span class="fx-kpi-sub">{{ k.sub }}</span>
        </article>
      </template>
    </section>

    <section class="sp-card fr-list" aria-label="Funding requests">
      <div class="fr-list-head">
        <div class="sp-seg" role="tablist" aria-label="Status">
          <button v-for="tab in STATUS_TABS" :key="tab.value" type="button" role="tab" :aria-selected="activeStatusTab === tab.value" :class="{ on: activeStatusTab === tab.value }" @click="activeStatusTab = tab.value">
            {{ tab.label }}<span class="count">{{ getStatusCount(tab.value) }}</span>
          </button>
        </div>
        <label class="sp-search">
          <span v-html="ICONS.search"></span>
          <input v-model="searchQuery" class="sp-input" type="search" placeholder="Request, product or person" aria-label="Search funding requests" />
        </label>
      </div>

      <ul v-if="!loaded" class="fr-rows">
        <li v-for="n in 4" :key="n" class="fr-row"><div class="bc-skeleton fx-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
      </ul>
      <div v-else-if="!filteredRequests.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.send"></span>
        <h3>{{ searchQuery || activeStatusTab !== "all" ? "No requests match" : "No funding requests yet" }}</h3>
        <p>{{ searchQuery || activeStatusTab !== "all" ? "Try another status or search." : "Requests appear here when Procurement asks for money to buy stock." }}</p>
      </div>
      <ul v-else class="fr-rows" :class="{ dim: loading }">
        <li v-for="r in filteredRequests" :key="r.id" class="fr-row" tabindex="0" @click="viewRequest(r.id)" @keydown.enter="viewRequest(r.id)">
          <span class="fx-icon" :class="`tone-${STATUS[displayRequestStatus(r)]?.tone ?? 'slate'}`" v-html="ICONS.box"></span>
          <div class="grow">
            <strong>{{ r.product_name }} <span class="muted">· {{ r.requested_qty }} {{ r.uom }}</span></strong>
            <span class="fr-sub">{{ [r.flower_category, r.variant].filter(Boolean).join(" · ") }}</span>
            <span class="fr-meta">
              <span class="mono">{{ r.finance_request_id }}</span> · {{ formatDate(r.request_date) }} · from {{ r.submitted_by_name }}
              · approver <b :class="{ you: isAssignedApprover(r) }">{{ isAssignedApprover(r) ? "you" : r.approver_name || "not assigned" }}</b>
            </span>
            <span v-if="r.business_justification" class="fr-why">“{{ truncateText(r.business_justification, 110) }}”</span>
          </div>
          <div class="fr-state">
            <span class="sp-chip" :class="STATUS[displayRequestStatus(r)]?.tone ?? 'slate'">{{ displayRequestStatus(r) }}</span>
          </div>
          <div class="fr-amt">
            <strong>{{ peso(r.estimated_total_cost) }}</strong>
            <small :class="marginTone(r.estimated_gross_margin)">{{ r.estimated_gross_margin }}% margin</small>
          </div>
          <div class="fr-actions" @click.stop>
            <button
              v-if="r.request_status === 'Pending'"
              type="button"
              class="sp-btn sp-btn-primary sp-btn-sm"
              :disabled="!canReviewRequest(r)"
              :title="canReviewRequest(r) ? 'Review this request' : 'Only the assigned approver can review this request'"
              @click="openReviewModal(r)"
            >Review</button>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="viewRequest(r.id)">Details</button>
          </div>
        </li>
      </ul>
    </section>

    <!-- Review -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="showReviewModal" class="sp-backdrop" @click="closeReviewModal"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="showReviewModal" class="sp-modal-wrap">
          <div class="sp-modal wide" role="dialog" aria-modal="true" aria-labelledby="fr-title">
            <div class="sp-modal-head">
              <div>
                <h3 id="fr-title">Review {{ reviewForm.product_name }}</h3>
                <p><span class="mono">{{ reviewForm.finance_request_id }}</span> · from {{ reviewForm.submitted_by_name }}</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeReviewModal"><span v-html="ICONS.close"></span></button>
            </div>

            <dl class="fr-sum">
              <div><dt>Quantity</dt><dd>{{ reviewForm.requested_qty }} {{ reviewForm.uom }}</dd></div>
              <div><dt>Total cost</dt><dd>{{ peso(reviewForm.estimated_total_cost) }}</dd></div>
              <div><dt>Margin</dt><dd :class="marginTone(reviewForm.estimated_gross_margin)">{{ reviewForm.estimated_gross_margin }}%</dd></div>
              <div><dt>Approver</dt><dd>{{ reviewForm.approver_name || "Not assigned" }}</dd></div>
            </dl>

            <div class="fr-brief">
              <div><span class="fr-brief-label">What and why</span><p>{{ reviewForm.business_justification || "—" }}</p></div>
              <div class="good"><span class="fr-brief-label">If approved</span><p>{{ reviewForm.approval_impact || "—" }}</p></div>
              <div class="warn"><span class="fr-brief-label">If rejected or delayed</span><p>{{ reviewForm.rejection_risk || "—" }}</p></div>
              <div v-if="reviewForm.additional_notes"><span class="fr-brief-label">Other notes</span><p>{{ reviewForm.additional_notes }}</p></div>
            </div>

            <div v-if="reviewForm.finance_recommendation" class="fr-rec">
              <span class="sp-chip teal">{{ reviewForm.finance_recommendation }}</span>
              <span>Recommended {{ reviewForm.recommended_qty }} {{ reviewForm.uom }} for {{ peso(reviewForm.recommended_budget) }}<template v-if="reviewForm.price_ceiling"> · price ceiling {{ peso(reviewForm.price_ceiling) }}</template></span>
            </div>

            <div class="sp-field">
              <span class="sp-label">Your decision</span>
              <div class="fr-decide">
                <button type="button" class="sp-pick" :class="{ on: reviewForm.decision === 'approve' }" :disabled="!canApproveFunding" :title="canApproveFunding ? '' : PERMISSION_TOOLTIPS.approve" @click="reviewForm.decision = 'approve'"><span v-html="ICONS.check"></span>Approve</button>
                <button type="button" class="sp-pick reject" :class="{ on: reviewForm.decision === 'reject' }" :disabled="!canRejectFunding" :title="canRejectFunding ? '' : PERMISSION_TOOLTIPS.reject" @click="reviewForm.decision = 'reject'"><span v-html="ICONS.close"></span>Reject</button>
              </div>
            </div>

            <div v-if="reviewForm.decision === 'approve'" class="sp-grid-2">
              <label class="sp-field">
                <span class="sp-label">Approved quantity ({{ reviewForm.uom }})</span>
                <input v-model.number="reviewForm.approved_quantity" class="sp-input" type="number" min="0" :max="reviewForm.requested_qty" />
              </label>
              <label class="sp-field">
                <span class="sp-label">Approved amount (₱)</span>
                <input v-model.number="reviewForm.approved_amount" class="sp-input" type="number" min="0" step="0.01" />
              </label>
              <label class="sp-field sp-span-all">
                <span class="sp-label">Remarks (optional)</span>
                <textarea v-model="reviewForm.finance_remarks" class="sp-textarea" rows="3" placeholder="Any conditions, like buying in two batches"></textarea>
              </label>
            </div>
            <div v-else-if="reviewForm.decision === 'reject'" class="sp-grid-2">
              <label class="sp-field sp-span-all">
                <span class="sp-label">Reason</span>
                <select v-model="reviewForm.rejection_reason" class="sp-select">
                  <option value="">Choose a reason</option>
                  <option v-for="r in REJECT_REASONS" :key="r" :value="r">{{ r }}</option>
                </select>
              </label>
              <label class="sp-field sp-span-all">
                <span class="sp-label">Notes for Procurement</span>
                <textarea v-model="reviewForm.rejection_notes" class="sp-textarea" rows="4" placeholder="What would need to change for this to be approved"></textarea>
              </label>
            </div>

            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="closeReviewModal">Cancel</button>
              <button
                type="button"
                class="sp-btn"
                :class="reviewForm.decision === 'reject' ? 'sp-btn-danger' : 'sp-btn-primary'"
                :disabled="!reviewForm.decision || submitting || !canSubmitDecision"
                :title="decisionPermissionTooltip"
                @click="submitDecision"
              >{{ submitting ? "Saving…" : reviewForm.decision === "reject" ? "Reject request" : "Approve request" }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { PERMISSION_TOOLTIPS, useAssignment } from "../../../composables/useAssignment";
import { useAuth } from "../../../composables/useAuth";
import "../../../components/vendor/finance/finance.css";

const STATUS_TABS = [
  { label: "All", value: "all" },
  { label: "Pending", value: "Pending" },
  { label: "Approved", value: "Approved" },
  { label: "Rejected", value: "Rejected" },
];
const STATUS = {
  Pending: { tone: "amber" },
  Approved: { tone: "green" },
  Paid: { tone: "teal" },
  Rejected: { tone: "red" },
};
const REJECT_REASONS = ["Insufficient Cash", "Budget Exceeded", "Low Profit Margin", "Overstock Risk", "High Spoilage Risk", "Supplier Price Too High", "Duplicate Request", "Other"];

const router = useRouter();
const { can } = useAssignment();
const { user } = useAuth();

const searchQuery = ref("");
const activeStatusTab = ref("all");
const loading = ref(false);
const loaded = ref(false);
const error = ref("");
const requests = ref([]);
const showReviewModal = ref(false);
const submitting = ref(false);

const emptyReview = () => ({ id: null, decision: null, approver_id: null, approved_quantity: 0, approved_amount: 0, finance_remarks: "", rejection_reason: "", rejection_notes: "" });
const reviewForm = ref(emptyReview());

const canApproveFunding = computed(() => can("funding_requests", "approve"));
const canRejectFunding = computed(() => can("funding_requests", "reject"));
const currentEmployeeId = computed(() => (user.value?.type === "employee" ? Number(user.value?.id || 0) : 0));
const isAssignedApprover = (request) => !!currentEmployeeId.value && Number(request?.approver_id || 0) === currentEmployeeId.value;
const canReviewRequest = (request) => (canApproveFunding.value || canRejectFunding.value) && isAssignedApprover(request);
const canSubmitDecision = computed(
  () =>
    isAssignedApprover(reviewForm.value) &&
    (reviewForm.value.decision === "approve" ? canApproveFunding.value : reviewForm.value.decision === "reject" && canRejectFunding.value),
);
const decisionPermissionTooltip = computed(() => {
  if (reviewForm.value.decision === "approve" && !canApproveFunding.value) return PERMISSION_TOOLTIPS.approve;
  if (reviewForm.value.decision === "reject" && !canRejectFunding.value) return PERMISSION_TOOLTIPS.reject;
  return "";
});

const displayRequestStatus = (r) => (r?.request_status === "Approved" && r?.payment_status === "paid" ? "Paid" : r?.request_status || "Unknown");

const filteredRequests = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  return requests.value.filter(
    (r) =>
      (activeStatusTab.value === "all" || r.request_status === activeStatusTab.value) &&
      (!q || [r.finance_request_id, r.product_name, r.submitted_by_name].some((v) => String(v ?? "").toLowerCase().includes(q))),
  );
});
const getStatusCount = (status) => (status === "all" ? requests.value.length : requests.value.filter((r) => r.request_status === status).length);

const sum = (rows) => rows.reduce((s, r) => s + Number(r.estimated_total_cost || 0), 0);
const kpis = computed(() => {
  const pending = requests.value.filter((r) => r.request_status === "Pending");
  const approved = requests.value.filter((r) => r.request_status === "Approved");
  const mine = pending.filter(isAssignedApprover);
  return [
    { key: "pending", label: "Waiting for review", icon: "clock", tone: "amber", value: pending.length, sub: `${peso(sum(pending))} requested` },
    { key: "mine", label: "Assigned to you", icon: "user", tone: "violet", value: mine.length, hero: mine.length > 0, sub: mine.length ? "need your decision" : "nothing for you to review" },
    { key: "approved", label: "Approved", icon: "check", tone: "green", value: approved.length, sub: `${peso(sum(approved))} in total` },
    { key: "paid", label: "Paid out", icon: "wallet", tone: "teal", value: approved.filter((r) => r.payment_status === "paid").length, sub: "approved and paid" },
  ];
});

const peso = (n) => `₱${Number(n || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const formatDate = (d) => (d ? new Date(d).toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" }) : "—");
const truncateText = (t, n) => (!t || t.length <= n ? t || "" : `${t.slice(0, n).trimEnd()}…`);
const marginTone = (m) => (m >= 30 ? "pos" : m >= 20 ? "mid" : "neg");

async function fetchRequests() {
  loading.value = true;
  error.value = "";
  try {
    const { data } = await api.get("/finance/funding-requests");
    requests.value = data.data ?? [];
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load funding requests.");
  } finally {
    loading.value = false;
    loaded.value = true;
  }
}

const viewRequest = (id) => router.push({ name: "FinanceFundingRequestDetails", params: { id } });

function openReviewModal(r) {
  if (!canReviewRequest(r)) {
    toast.error("Only the assigned approver can review this request");
    return;
  }
  reviewForm.value = { ...emptyReview(), ...r, id: r.id, approved_quantity: r.requested_qty, approved_amount: r.estimated_total_cost };
  showReviewModal.value = true;
}
function closeReviewModal() {
  showReviewModal.value = false;
  reviewForm.value = emptyReview();
}

async function submitDecision() {
  const f = reviewForm.value;
  if (!canSubmitDecision.value) {
    toast.error(decisionPermissionTooltip.value || "Only the assigned approver can decide");
    return;
  }
  if (f.decision === "approve" && (!f.approved_quantity || !f.approved_amount)) {
    toast.error("Enter the approved quantity and amount");
    return;
  }
  if (f.decision === "reject" && (!f.rejection_reason || !f.rejection_notes?.trim())) {
    toast.error("Choose a reason and add a note");
    return;
  }
  submitting.value = true;
  try {
    const payload =
      f.decision === "approve"
        ? { approved_quantity: f.approved_quantity, approved_amount: f.approved_amount, finance_remarks: f.finance_remarks }
        : { rejection_reason: f.rejection_reason, rejection_notes: f.rejection_notes };
    const { data } = await api.post(`finance/funding-requests/${f.id}/${f.decision}`, payload);
    toast.success(data.message || (f.decision === "approve" ? "Request approved" : "Request rejected"));
    closeReviewModal();
    await fetchRequests();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't save the decision."));
  } finally {
    submitting.value = false;
  }
}

onMounted(fetchRequests);
</script>

<style scoped>
.fx-kpi.hero {
  background: linear-gradient(150deg, var(--bc-accent-soft) 0%, var(--v-surface) 60%);
  border-color: var(--bc-accent-muted);
}
.fr-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.fr-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.fr-list {
  min-width: 0;
}
.fr-list-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}
.fr-list-head .sp-search {
  flex: 0 1 300px;
}
.sp-seg .count {
  margin-left: 6px;
  padding: 0 7px;
  border-radius: 999px;
  background: var(--v-subtle-2);
  font-size: 11px;
}
.fr-rows {
  list-style: none;
  margin: 0;
  padding: 0;
  transition: opacity 0.2s;
}
.fr-rows.dim {
  opacity: 0.55;
}
.fr-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 8px;
  border-top: 1px solid var(--v-border);
  border-radius: 12px;
  cursor: pointer;
  transition: background 0.15s;
}
.fr-row:hover,
.fr-row:focus-visible {
  background: var(--v-subtle);
  outline: none;
}
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.fr-row strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.muted {
  font-weight: 500;
  color: var(--v-muted);
}
.fr-sub {
  font-size: 12.5px;
  color: var(--v-text-2);
}
.fr-meta {
  font-size: 11.5px;
  color: var(--v-faint);
}
.fr-meta b {
  font-weight: 600;
  color: var(--v-text-2);
}
.fr-meta b.you {
  color: var(--bc-accent-deep);
}
.fr-why {
  overflow: hidden;
  font-size: 12px;
  font-style: italic;
  color: var(--v-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.95em;
}
.fr-state,
.fr-amt {
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  text-align: right;
}
.fr-state small,
.fr-amt small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.fr-amt {
  min-width: 120px;
}
.fr-amt strong {
  font-size: 14px;
}
.pos {
  color: var(--v-green-text) !important;
}
.mid {
  color: var(--v-amber-text) !important;
}
.neg {
  color: var(--v-red-text) !important;
}
.fr-actions {
  flex: none;
  display: flex;
  gap: 6px;
}

/* Review */
.fr-sum {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin: 0 0 16px;
}
.fr-sum div {
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--v-subtle);
}
.fr-sum dt {
  font-size: 11.5px;
  color: var(--v-muted);
}
.fr-sum dd {
  margin: 2px 0 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
}
.fr-brief {
  display: grid;
  gap: 8px;
  margin-bottom: 14px;
}
.fr-brief > div {
  padding: 10px 14px;
  border-left: 3px solid var(--v-border-strong);
  border-radius: 0 10px 10px 0;
  background: var(--v-subtle);
}
.fr-brief > div.good {
  border-left-color: var(--v-green-text);
}
.fr-brief > div.warn {
  border-left-color: var(--v-amber-text);
}
.fr-brief-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--v-faint);
}
.fr-brief p {
  margin: 2px 0 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--v-text-2);
}
.fr-rec {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.fr-decide {
  display: flex;
  gap: 8px;
}
.fr-decide :deep(svg) {
  width: 14px;
  height: 14px;
}
.sp-pick.reject.on {
  background: var(--v-red-soft);
  border-color: var(--v-red-text);
  color: var(--v-red-text);
}

@media (max-width: 900px) {
  .fr-row {
    flex-wrap: wrap;
  }
  .fr-row .grow {
    flex-basis: calc(100% - 60px);
  }
  .fr-state {
    align-items: flex-start;
    margin-left: 54px;
  }
  .fr-amt {
    margin-left: auto;
  }
  .fr-actions {
    width: 100%;
    justify-content: flex-end;
  }
  .fr-why {
    white-space: normal;
  }
}
@media (max-width: 640px) {
  .fr-sum {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .fr-list-head .sp-search {
    flex-basis: 100%;
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
