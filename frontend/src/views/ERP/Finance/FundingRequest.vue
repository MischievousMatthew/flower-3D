<template>
  <HrPage title="Funding requests" subtitle="Money Procurement needs to restock. The assigned approver checks the products, the date and how it's paid, then approves or rejects.">
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
        <div class="fr-tools">
          <label class="sp-search">
            <span v-html="ICONS.search"></span>
            <input v-model="searchQuery" class="sp-input" type="search" placeholder="Request, product, person or bank" aria-label="Search funding requests" />
          </label>
          <select v-model="sortBy" class="sp-select fr-sort" aria-label="Sort by">
            <option value="newest">Newest first</option>
            <option value="needed">Needed soonest</option>
            <option value="urgent">Most urgent</option>
            <option value="amount">Largest amount</option>
          </select>
        </div>
      </div>

      <ul v-if="!loaded" class="fr-rows">
        <li v-for="n in 4" :key="n" class="fr-row"><div class="bc-skeleton fx-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
      </ul>
      <div v-else-if="!filteredRequests.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.send"></span>
        <h3>{{ searchQuery || activeStatusTab !== "all" ? "No requests match" : "No funding requests yet" }}</h3>
        <p>{{ searchQuery || activeStatusTab !== "all" ? "Try another status or search." : "Requests appear here when Procurement sends one for review." }}</p>
      </div>
      <ul v-else class="fr-rows" :class="{ dim: loading }">
        <li v-for="r in filteredRequests" :key="r.id" class="fr-row" :class="{ mine: r.request_status === 'Pending' && isAssignedApprover(r) }" tabindex="0" @click="viewRequest(r.id)" @keydown.enter="viewRequest(r.id)">
          <span class="fx-icon" :class="`tone-${STATUS[displayRequestStatus(r)]?.tone ?? 'slate'}`" v-html="ICONS[STATUS[displayRequestStatus(r)]?.icon ?? 'box']"></span>
          <div class="grow">
            <div class="fr-title">
              <strong>{{ r.product_name || "Untitled request" }}</strong>
              <span v-if="r.urgency_level && r.urgency_level !== 'Normal'" class="sp-chip" :class="URGENCY[r.urgency_level]?.tone">{{ r.urgency_level }}</span>
            </div>
            <span class="fr-facts">
              <span><span v-html="ICONS.box"></span>{{ productCount(r) }}</span>
              <span v-if="r.required_delivery_date" :class="neededTone(r)"><span v-html="ICONS.calendar"></span>{{ neededText(r) }}</span>
              <span v-if="payLabel(r)"><span v-html="ICONS[r.payment_method === 'ewallet' ? 'wallet' : 'card']"></span>{{ payLabel(r) }}</span>
              <span v-if="r.reason_for_shortage" class="fr-reason">{{ REASONS[r.reason_for_shortage] || r.reason_for_shortage }}</span>
            </span>
            <span v-if="r.business_justification" class="fr-why">“{{ r.business_justification }}”</span>
            <span class="fr-meta">
              <span class="mono">{{ r.finance_request_id }}</span> · sent {{ formatDate(r.submitted_to_accounting_at || r.request_date) }} by {{ r.submitted_by_name || "Procurement" }}
              · approver <b :class="{ you: isAssignedApprover(r) }">{{ isAssignedApprover(r) ? "you" : r.approver_name || "not assigned" }}</b>
            </span>
          </div>
          <div class="fr-amt">
            <span v-if="paymentStage(r.payment)" class="sp-chip" :class="paymentStage(r.payment).tone" :title="paymentStage(r.payment).text">{{ paymentStage(r.payment).label }}</span>
            <span v-else class="sp-chip" :class="STATUS[displayRequestStatus(r)]?.tone ?? 'slate'">{{ displayRequestStatus(r) }}</span>
            <strong>{{ peso(r.request_status === "Approved" ? r.approved_amount : r.estimated_total_cost) }}</strong>
            <small v-if="r.request_status === 'Approved'">{{ r.payment?.net_paid > 0 && !r.payment?.legacy ? `${peso(r.payment.net_paid)} paid` : "budget" }}<template v-if="r.payment?.overdue"> · overdue</template></small>
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
            <button v-if="r.payment?.stage === 'to_pay'" type="button" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="!canPay" :title="canPay ? 'Record the payment to the supplier' : PERMISSION_TOOLTIPS.approve" @click="openPay(r, 'pay')">Pay</button>
            <button v-if="r.payment?.stage === 'supplier_owes'" type="button" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="!canPay" @click="openPay(r, 'credit')">Settle</button>
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
          <div class="sp-modal wide fr-modal" role="dialog" aria-modal="true" aria-labelledby="fr-title">
            <div class="sp-modal-head">
              <div>
                <h3 id="fr-title">{{ review.product_name || "Funding request" }}</h3>
                <p><span class="mono">{{ review.finance_request_id }}</span> · from {{ review.submitted_by_name || "Procurement" }} · sent {{ formatDate(review.submitted_to_accounting_at || review.request_date) }}</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeReviewModal"><span v-html="ICONS.close"></span></button>
            </div>

            <div class="fr-tags">
              <span v-if="review.urgency_level" class="sp-chip" :class="URGENCY[review.urgency_level]?.tone">{{ review.urgency_level }}</span>
              <span v-if="review.reason_for_shortage" class="sp-chip slate">{{ REASONS[review.reason_for_shortage] || review.reason_for_shortage }}</span>
            </div>

            <dl class="fr-sum">
              <div><dt>Asked for</dt><dd>{{ peso(review.estimated_total_cost) }}</dd></div>
              <div><dt>Needed by</dt><dd :class="neededTone(review)">{{ review.required_delivery_date ? formatDate(review.required_delivery_date) : "—" }}</dd></div>
              <div><dt>Pay through</dt><dd>{{ payLabel(review) || "—" }}</dd></div>
              <div><dt>Products</dt><dd>{{ lines.length || productCount(review) }}</dd></div>
            </dl>

            <div class="fr-brief">
              <div><span class="fr-brief-label">Why it's needed</span><p>{{ review.business_justification || "—" }}</p></div>
              <div v-if="review.approval_impact" class="good"><span class="fr-brief-label">If approved</span><p>{{ review.approval_impact }}</p></div>
              <div v-if="review.rejection_risk" class="warn"><span class="fr-brief-label">If rejected or delayed</span><p>{{ review.rejection_risk }}</p></div>
              <div v-if="review.additional_notes"><span class="fr-brief-label">Notes</span><p>{{ review.additional_notes }}</p></div>
            </div>

            <!-- Products -->
            <div class="fr-table-wrap">
              <div v-if="loadingLines" class="fr-lines-sk"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div>
              <table v-else class="fr-table">
                <thead>
                  <tr>
                    <th scope="col">Product</th>
                    <th scope="col" class="num">Asked</th>
                    <th scope="col" class="num">Unit cost</th>
                    <th v-if="decision === 'approve'" scope="col" class="num">Approve</th>
                    <th scope="col" class="num">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="l in lines" :key="l.key" :class="{ cut: decision === 'approve' && Number(l.approve) < Number(l.quantity) }">
                    <td><b>{{ l.product_name }}</b><small v-if="l.current_stock !== null && l.current_stock !== undefined">{{ l.current_stock }} in stock when asked</small></td>
                    <td class="num">{{ l.quantity }} {{ (l.uom || "units").toLowerCase() }}</td>
                    <td class="num">{{ peso(l.unit_cost) }}</td>
                    <td v-if="decision === 'approve'" class="num">
                      <input v-model.number="l.approve" class="sp-input fr-qty" type="number" min="0" :max="l.quantity" step="1" inputmode="numeric" :aria-label="`Approved quantity of ${l.product_name}`" @input="syncAmount" />
                    </td>
                    <td class="num">{{ peso((decision === "approve" ? Number(l.approve) || 0 : Number(l.quantity) || 0) * Number(l.unit_cost || 0)) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr v-if="Number(review.logistics_cost) > 0"><td :colspan="decision === 'approve' ? 4 : 3">Delivery fee</td><td class="num">{{ peso(review.logistics_cost) }}</td></tr>
                  <tr v-if="Number(review.tax_vat_estimate) > 0"><td :colspan="decision === 'approve' ? 4 : 3">Tax or VAT</td><td class="num">{{ peso(review.tax_vat_estimate) }}</td></tr>
                  <tr class="total"><td :colspan="decision === 'approve' ? 4 : 3">{{ decision === "approve" ? "Approving" : "Total asked" }}</td><td class="num">{{ peso(decision === "approve" ? computedAmount : review.estimated_total_cost) }}</td></tr>
                </tfoot>
              </table>
            </div>

            <div class="sp-field">
              <span class="sp-label">Your decision</span>
              <div class="fr-decide">
                <button type="button" class="sp-pick" :class="{ on: decision === 'approve' }" :disabled="!canApproveFunding" :title="canApproveFunding ? '' : PERMISSION_TOOLTIPS.approve" @click="choose('approve')"><span v-html="ICONS.check"></span>Approve</button>
                <button type="button" class="sp-pick reject" :class="{ on: decision === 'reject' }" :disabled="!canRejectFunding" :title="canRejectFunding ? '' : PERMISSION_TOOLTIPS.reject" @click="choose('reject')"><span v-html="ICONS.close"></span>Reject</button>
              </div>
            </div>

            <div v-if="decision === 'approve'" class="sp-grid-2 fr-decision">
              <p class="fr-budget-note sp-span-all"><span v-html="ICONS.wallet"></span>Approving sets a budget. Nothing is paid now: you pay the supplier once they confirm or deliver, as their payment terms say, and only for what they can supply.</p>
              <p class="sp-hint sp-span-all">Lower a product's quantity above to approve only part of it. The budget follows.</p>
              <label class="sp-field">
                <span class="sp-label">Budget (₱)</span>
                <input v-model.number="review.approved_amount" class="sp-input" type="number" min="0" step="0.01" inputmode="decimal" @input="amountTouched = true" />
                <small v-if="amountTouched && Number(review.approved_amount) !== computedAmount" class="sp-hint">Products come to {{ peso(computedAmount) }}. <button type="button" class="fr-link" @click="resetAmount">Use that</button></small>
              </label>
              <div class="fr-paybox">
                <span class="fx-icon" v-html="ICONS[review.payment_method === 'ewallet' ? 'wallet' : 'card']"></span>
                <div><span class="sp-label">Will be paid through</span><b>{{ payLabel(review) || "Not given" }}</b></div>
              </div>
              <small v-if="Number(review.approved_amount) > computedAmount" class="sp-error sp-span-all">The budget can't be more than {{ peso(computedAmount) }}: the approved products plus the delivery fee.</small>
              <label class="sp-field sp-span-all">
                <span class="sp-label">Remarks <small>(optional)</small></span>
                <textarea v-model="review.finance_remarks" class="sp-textarea" rows="2" maxlength="500" placeholder="Any conditions, like buying in two batches"></textarea>
              </label>
            </div>
            <div v-else-if="decision === 'reject'" class="sp-grid-2 fr-decision">
              <label class="sp-field sp-span-all">
                <span class="sp-label">Reason</span>
                <select v-model="review.rejection_reason" class="sp-select">
                  <option value="">Choose a reason</option>
                  <option v-for="r in REJECT_REASONS" :key="r" :value="r">{{ r }}</option>
                </select>
              </label>
              <label class="sp-field sp-span-all">
                <span class="sp-label">Notes for Procurement</span>
                <textarea v-model="review.rejection_notes" class="sp-textarea" rows="3" maxlength="500" placeholder="What would need to change for this to be approved"></textarea>
              </label>
            </div>

            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="closeReviewModal">Cancel</button>
              <button
                type="button"
                class="sp-btn"
                :class="decision === 'reject' ? 'sp-btn-danger' : 'sp-btn-primary'"
                :disabled="!decision || submitting || !canSubmitDecision"
                :title="decisionPermissionTooltip"
                @click="submitDecision"
              >{{ submitting ? "Saving…" : decision === "reject" ? "Reject request" : `Approve ${peso(review.approved_amount)} budget` }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <FundingPayModal :open="!!paying" :request="paying?.request ?? {}" :mode="paying?.mode ?? 'pay'" @close="paying = null" @done="afterPay" />
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { toast } from "@/utils/snackbar";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { PERMISSION_TOOLTIPS, useAssignment } from "../../../composables/useAssignment";
import { useAuth } from "../../../composables/useAuth";
import FundingPayModal from "../../../components/procurement/FundingPayModal.vue";
import { paymentStage } from "../../../components/procurement/fundingPayment";
import "../../../components/vendor/finance/finance.css";

const STATUS_TABS = [
  { label: "All", value: "all" },
  { label: "Pending", value: "Pending" },
  { label: "To pay", value: "to_pay" },
  { label: "Approved", value: "Approved" },
  { label: "Rejected", value: "Rejected" },
];
const STATUS = {
  Pending: { tone: "amber", icon: "clock" },
  Approved: { tone: "green", icon: "check" },
  Paid: { tone: "teal", icon: "wallet" },
  Rejected: { tone: "red", icon: "close" },
};
const URGENCY = { Normal: { tone: "slate", rank: 0 }, Urgent: { tone: "amber", rank: 1 }, Critical: { tone: "red", rank: 2 } };
// Same wording Procurement picks from
const REASONS = {
  "New Order": "Customer orders to fill",
  "Seasonal Demand": "Season or holiday demand",
  Spoilage: "Stock spoiled or damaged",
  "Forecast Error": "Sold faster than expected",
  "Supplier Delay": "Supplier delivery was late",
};
const REJECT_REASONS = ["Not enough cash right now", "Over this month's budget", "We already have enough stock", "Supplier price is too high", "Needed-by date can't be met", "Duplicate request", "Details are missing or wrong", "Other"];

const router = useRouter();
const { can } = useAssignment();
const { user } = useAuth();

const searchQuery = ref("");
const activeStatusTab = ref("all");
const sortBy = ref("newest");
const loading = ref(false);
const loaded = ref(false);
const error = ref("");
const requests = ref([]);
const showReviewModal = ref(false);
const submitting = ref(false);
const loadingLines = ref(false);
const amountTouched = ref(false);
const decision = ref(null);
const lines = ref([]);

const emptyReview = () => ({ id: null, approver_id: null, approved_amount: 0, finance_remarks: "", rejection_reason: "", rejection_notes: "" });
const review = ref(emptyReview());

const canApproveFunding = computed(() => can("funding_requests", "approve"));
const canRejectFunding = computed(() => can("funding_requests", "reject"));
const currentEmployeeId = computed(() => (user.value?.type === "employee" ? Number(user.value?.id || 0) : 0));
const isAssignedApprover = (request) => !!currentEmployeeId.value && Number(request?.approver_id || 0) === currentEmployeeId.value;
const canReviewRequest = (request) => (canApproveFunding.value || canRejectFunding.value) && isAssignedApprover(request);
const canSubmitDecision = computed(
  () => isAssignedApprover(review.value) && (decision.value === "approve" ? canApproveFunding.value : decision.value === "reject" && canRejectFunding.value),
);
const decisionPermissionTooltip = computed(() => {
  if (decision.value === "approve" && !canApproveFunding.value) return PERMISSION_TOOLTIPS.approve;
  if (decision.value === "reject" && !canRejectFunding.value) return PERMISSION_TOOLTIPS.reject;
  return "";
});

const displayRequestStatus = (r) => (r?.request_status === "Approved" && (r?.payment?.stage === "paid" || (!r?.payment && r?.payment_status === "paid")) ? "Paid" : r?.request_status || "Unknown");
// Needs Finance to move money: pay the supplier, or settle what they owe
const needsMoney = (r) => ["to_pay", "supplier_owes"].includes(r?.payment?.stage);
const canPay = computed(() => can("funding_requests", "approve"));
const paying = ref(null);
function openPay(r, mode) {
  if (!canPay.value) return toast.error(PERMISSION_TOOLTIPS.approve);
  paying.value = { request: r, mode };
}
async function afterPay() {
  paying.value = null;
  await fetchRequests();
}
const payLabel = (r) => (r?.payment_channel ? `${r.payment_method === "ewallet" ? "E-wallet" : "Bank"} · ${r.payment_channel}` : r?.payment_terms ? `Terms: ${r.payment_terms}` : "");
const productCount = (r) => {
  const n = Number(r?.items_count) || (r?.product_name ? 1 : 0);
  return `${n} ${n === 1 ? "product" : "products"}`;
};

const startOfToday = () => {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  return d;
};
const daysUntil = (ymd) => Math.round((new Date(`${String(ymd).slice(0, 10)}T00:00:00`) - startOfToday()) / 86400000);
function neededText(r) {
  const d = daysUntil(r.required_delivery_date);
  const when = formatDate(r.required_delivery_date);
  if (r.request_status !== "Pending") return `Needed by ${when}`;
  if (d < 0) return `Was needed ${when}`;
  if (d === 0) return "Needed today";
  if (d === 1) return "Needed tomorrow";
  return `Needed in ${d} days`;
}
const neededTone = (r) => {
  if (!r?.required_delivery_date || r.request_status !== "Pending") return "";
  const d = daysUntil(r.required_delivery_date);
  return d < 0 ? "neg" : d <= 2 ? "mid" : "";
};

const filteredRequests = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  const rows = requests.value.filter(
    (r) =>
      (activeStatusTab.value === "all" || (activeStatusTab.value === "to_pay" ? needsMoney(r) : r.request_status === activeStatusTab.value)) &&
      (!q || [r.finance_request_id, r.product_name, r.submitted_by_name, r.payment_channel, r.business_justification].some((v) => String(v ?? "").toLowerCase().includes(q))),
  );
  const by = {
    newest: (a, b) => String(b.submitted_to_accounting_at || b.created_at).localeCompare(String(a.submitted_to_accounting_at || a.created_at)),
    needed: (a, b) => String(a.required_delivery_date || "9999").localeCompare(String(b.required_delivery_date || "9999")),
    urgent: (a, b) => (URGENCY[b.urgency_level]?.rank ?? 0) - (URGENCY[a.urgency_level]?.rank ?? 0) || String(a.required_delivery_date || "9999").localeCompare(String(b.required_delivery_date || "9999")),
    amount: (a, b) => Number(b.estimated_total_cost || 0) - Number(a.estimated_total_cost || 0),
  }[sortBy.value];
  return [...rows].sort(by);
});
const getStatusCount = (status) => (status === "all" ? requests.value.length : requests.value.filter((r) => (status === "to_pay" ? needsMoney(r) : r.request_status === status)).length);

const sum = (rows, key = "estimated_total_cost") => rows.reduce((s, r) => s + Number(r[key] || 0), 0);
const kpis = computed(() => {
  const pending = requests.value.filter((r) => r.request_status === "Pending");
  const approved = requests.value.filter((r) => r.request_status === "Approved");
  const mine = pending.filter(isAssignedApprover);
  const toPay = approved.filter((r) => r.payment?.stage === "to_pay");
  const owed = approved.filter((r) => r.payment?.stage === "supplier_owes");
  const overdue = toPay.filter((r) => r.payment?.overdue).length;
  return [
    { key: "pending", label: "Waiting for review", icon: "clock", tone: "amber", value: pending.length, sub: `${peso(sum(pending))} asked for` },
    { key: "mine", label: "Assigned to you", icon: "user", tone: "violet", value: mine.length, hero: mine.length > 0, sub: mine.length ? "need your decision" : "nothing for you to review" },
    { key: "topay", label: "To pay suppliers", icon: "wallet", tone: overdue ? "red" : "green", value: peso(toPay.reduce((s, r) => s + Number(r.payment.to_pay || 0), 0)), sub: toPay.length ? `${toPay.length} ready to pay${overdue ? `, ${overdue} overdue` : ""}` : "nothing to pay right now" },
    { key: "owed", label: "Suppliers owe you", icon: "alert", tone: owed.length ? "red" : "teal", value: peso(owed.reduce((s, r) => s + Number(r.payment.supplier_owes || 0), 0)), sub: owed.length ? `after ${owed.length} short ${owed.length === 1 ? "delivery" : "deliveries"}` : "no short deliveries" },
  ];
});

const peso = (n) => `₱${Number(n || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const formatDate = (d) => (d ? new Date(String(d).length === 10 ? `${d}T00:00:00` : d).toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" }) : "—");

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

// Products' total at the approved quantities, plus the delivery fee
const computedAmount = computed(() => {
  const products = lines.value.reduce((s, l) => s + (Number(l.approve) || 0) * Number(l.unit_cost || 0), 0);
  const extra = Number(review.value.logistics_cost || 0) + Number(review.value.tax_vat_estimate || 0);
  return Math.round((products + extra) * 100) / 100;
});
function syncAmount() {
  if (!amountTouched.value) review.value.approved_amount = computedAmount.value;
}
function resetAmount() {
  amountTouched.value = false;
  review.value.approved_amount = computedAmount.value;
}
function choose(d) {
  decision.value = d;
  if (d === "approve") syncAmount();
}

// Requests made before several products were allowed hold one product on the request
const legacyLine = (r) => (r.product_name ? [{ key: "one", id: null, product_name: r.product_name, uom: r.uom, current_stock: r.current_stock, quantity: Number(r.requested_qty) || 0, approve: Number(r.requested_qty) || 0, unit_cost: Number(r.estimated_unit_cost || 0) }] : []);

async function openReviewModal(r) {
  if (!canReviewRequest(r)) {
    toast.error("Only the assigned approver can review this request");
    return;
  }
  review.value = { ...emptyReview(), ...r, approved_amount: Number(r.estimated_total_cost || 0) };
  decision.value = null;
  amountTouched.value = false;
  lines.value = legacyLine(r);
  showReviewModal.value = true;
  if (!Number(r.items_count)) return;
  loadingLines.value = true;
  try {
    const { data } = await api.get(`finance/funding-requests/${r.id}`);
    if (showReviewModal.value && review.value.id === r.id && data.data?.items?.length) {
      lines.value = data.data.items.map((i) => ({ key: i.id, id: i.id, product_name: i.product_name, uom: i.uom, current_stock: i.current_stock, quantity: Number(i.quantity), approve: Number(i.quantity), unit_cost: Number(i.unit_cost) }));
      syncAmount();
    }
  } catch {
    // The request's own summary is still shown
  } finally {
    loadingLines.value = false;
  }
}
function closeReviewModal() {
  showReviewModal.value = false;
  review.value = emptyReview();
  lines.value = [];
  decision.value = null;
}

async function submitDecision() {
  const f = review.value;
  if (!canSubmitDecision.value) {
    toast.error(decisionPermissionTooltip.value || "Only the assigned approver can decide");
    return;
  }
  if (decision.value === "approve") {
    const bad = lines.value.find((l) => !Number.isInteger(Number(l.approve)) || Number(l.approve) < 0 || Number(l.approve) > l.quantity);
    if (bad) return toast.error(`Approve between 0 and ${bad.quantity} of ${bad.product_name}`);
    if (!lines.value.some((l) => Number(l.approve) > 0)) return toast.error("Approve at least one product, or reject the request");
    if (!(Number(f.approved_amount) > 0)) return toast.error("Enter a budget above ₱0, or reject the request");
    if (Number(f.approved_amount) > computedAmount.value + 0.001) return toast.error(`The budget can be up to ${peso(computedAmount.value)}`);
  }
  if (decision.value === "reject" && (!f.rejection_reason || !f.rejection_notes?.trim())) {
    toast.error("Choose a reason and add a note");
    return;
  }
  submitting.value = true;
  try {
    const payload =
      decision.value === "approve"
        ? {
            approved_quantity: lines.value.reduce((s, l) => s + Number(l.approve || 0), 0),
            approved_amount: Number(f.approved_amount),
            finance_remarks: f.finance_remarks,
            items: lines.value.filter((l) => l.id).map((l) => ({ id: l.id, approved_quantity: Number(l.approve) })),
          }
        : { rejection_reason: f.rejection_reason, rejection_notes: f.rejection_notes };
    const { data } = await api.post(`finance/funding-requests/${f.id}/${decision.value}`, payload);
    toast.success(data.message || (decision.value === "approve" ? "Request approved" : "Request rejected"));
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
.fr-tools {
  display: flex;
  flex: 0 1 460px;
  gap: 8px;
}
.fr-tools .sp-search {
  flex: 1;
}
.fr-sort {
  flex: 0 0 160px;
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
  padding: 14px 10px;
  border-top: 1px solid var(--v-border);
  border-radius: 12px;
  cursor: pointer;
  transition: background 0.15s;
}
.fr-row.mine {
  box-shadow: inset 3px 0 0 var(--bc-accent);
}
.fr-row:hover,
.fr-row:focus-visible {
  background: var(--v-subtle);
  outline: none;
}
.fr-row:focus-visible {
  box-shadow: inset 0 0 0 2px var(--bc-accent);
}
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.fr-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.fr-title strong {
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
}
.fr-facts {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 14px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.fr-facts > span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.fr-facts :deep(svg) {
  width: 14px;
  height: 14px;
  color: var(--v-muted);
}
.fr-facts .neg :deep(svg),
.fr-facts .mid :deep(svg) {
  color: currentColor;
}
.fr-reason {
  color: var(--v-muted);
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
  font-size: 12.5px;
  font-style: italic;
  color: var(--v-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.95em;
}
.fr-amt {
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  min-width: 130px;
  text-align: right;
}
.fr-amt strong {
  font-size: 15px;
  font-weight: 600;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.fr-amt small {
  font-size: 11.5px;
  color: var(--v-muted);
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
.fr-modal {
  max-width: 760px;
}
.fr-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}
.fr-tags:empty {
  display: none;
}
.fr-sum {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin: 0 0 14px;
}
.fr-sum div {
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--v-subtle);
  min-width: 0;
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
  overflow-wrap: anywhere;
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
.fr-table-wrap {
  margin-bottom: 16px;
  overflow-x: auto;
  border: 1px solid var(--v-border);
  border-radius: 12px;
}
.fr-lines-sk {
  display: grid;
  gap: 8px;
  padding: 14px;
}
.fr-table {
  width: 100%;
  min-width: 480px;
  border-collapse: collapse;
  font-size: 13px;
}
.fr-table th {
  padding: 9px 12px;
  background: var(--v-subtle);
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-muted);
  text-align: left;
}
.fr-table td {
  padding: 10px 12px;
  border-top: 1px solid var(--v-border);
  color: var(--v-text);
  vertical-align: middle;
}
.fr-table td b {
  display: block;
  font-weight: 600;
}
.fr-table td small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.fr-table .num {
  text-align: right;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.fr-table tr.cut td:last-child {
  color: var(--v-amber-text);
}
.fr-table tfoot td {
  color: var(--v-text-2);
}
.fr-table tfoot .total td {
  font-size: 14.5px;
  font-weight: 600;
  color: var(--v-text);
}
.fr-qty {
  width: 86px;
  min-height: 36px;
  text-align: right;
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
.fr-decision {
  margin-top: 14px;
}
.fr-decision > .sp-hint {
  margin: 0;
}
.fr-paybox {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 12px;
  background: var(--v-subtle);
  align-self: end;
  min-height: 44px;
}
.fr-paybox > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.fr-paybox b {
  font-size: 13.5px;
  color: var(--v-text);
}
.fr-budget-note {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--bc-accent-soft);
  font-size: 12.5px;
  line-height: 1.5;
  color: var(--v-text-2);
}
.fr-budget-note :deep(svg) {
  flex: none;
  width: 16px;
  height: 16px;
  margin-top: 1px;
  color: var(--bc-accent-deep);
}
.fr-link {
  padding: 0;
  border: 0;
  background: none;
  color: var(--bc-accent-deep);
  font: inherit;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

@media (prefers-reduced-motion: reduce) {
  .fr-row,
  .fr-rows {
    transition: none;
  }
}
@media (max-width: 900px) {
  .fr-row {
    flex-wrap: wrap;
  }
  .fr-row .grow {
    flex-basis: calc(100% - 60px);
  }
  .fr-amt {
    flex-direction: row;
    align-items: center;
    margin-left: 54px;
    min-width: 0;
  }
  .fr-actions {
    margin-left: auto;
  }
  .fr-why {
    white-space: normal;
  }
  .fr-tools {
    flex-basis: 100%;
  }
}
@media (max-width: 640px) {
  .fr-sum {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .fr-tools {
    flex-wrap: wrap;
  }
  .fr-sort {
    flex: 1 1 100%;
  }
  .fr-amt {
    margin-left: 0;
    width: 100%;
    justify-content: space-between;
  }
  .fr-actions {
    width: 100%;
    justify-content: flex-end;
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
