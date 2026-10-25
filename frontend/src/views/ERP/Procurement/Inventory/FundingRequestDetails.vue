<template>
  <HrPage :title="request?.product_name || 'Funding request'" :subtitle="request ? `${request.finance_request_id} · requested ${formatDate(request.request_date)} by ${request.submitted_by_name || 'someone'}` : 'Loading the request…'">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" @click="goBack"><span v-html="ICONS.arrowLeft"></span>Back to the list</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="fetchRequest">Try again</button>
    </div>

    <template v-if="!request && !error">
      <div class="sp-card fd-skel"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      <div v-for="n in 3" :key="n" class="sp-card fd-skel"><div class="bc-skeleton sp-sk-block"></div></div>
    </template>

    <template v-else-if="request">
      <!-- Where it stands -->
      <section class="sp-card fd-status">
        <span class="fx-icon" :class="`tone-${status.tone}`" v-html="ICONS[status.icon]"></span>
        <div class="grow">
          <div class="fd-status-top">
            <span class="sp-chip" :class="status.tone">{{ displayRequestStatus(request) }}</span>
            <strong>{{ peso(request.request_status === "Approved" ? request.approved_amount : request.estimated_total_cost) }}</strong>
          </div>
          <p>{{ statusText }}</p>
        </div>
        <div v-if="request.request_status === 'Draft' && isInventoryContext" class="fd-status-actions">
          <button type="button" class="sp-btn sp-btn-ghost" :disabled="!canEditFunding" @click="editRequest"><span v-html="ICONS.edit"></span>Edit</button>
          <button type="button" class="sp-btn sp-btn-primary" :disabled="!canEditFunding || submitting" @click="submitRequest"><span v-html="ICONS.send"></span>{{ submitting ? "Sending…" : "Send to Finance" }}</button>
        </div>
      </section>

      <!-- Finance's decision -->
      <section v-if="['Approved', 'Rejected'].includes(request.request_status)" class="sp-card fd-decision" :class="request.request_status === 'Rejected' ? 'bad' : 'good'">
        <div class="sp-card-head"><div><h2>Finance decision</h2><p>{{ displayRequestStatus(request) }} by {{ request.reviewed_by_name || "Finance" }} · {{ formatDateTime(request.accounting_decision_at) }}</p></div></div>
        <dl class="fd-grid">
          <template v-if="request.request_status === 'Approved'">
            <div><dt>Approved quantity</dt><dd>{{ request.approved_quantity }} {{ request.uom }}</dd></div>
            <div><dt>Approved amount</dt><dd>{{ peso(request.approved_amount) }}</dd></div>
          </template>
          <template v-else>
            <div><dt>Reason</dt><dd class="neg">{{ request.rejection_reason || "—" }}</dd></div>
            <div class="wide"><dt>Notes</dt><dd>{{ request.rejection_notes || "—" }}</dd></div>
          </template>
          <div v-if="request.accounting_remarks" class="wide"><dt>Remarks</dt><dd>{{ request.accounting_remarks }}</dd></div>
        </dl>
      </section>

      <!-- The case for it -->
      <section class="sp-card">
        <div class="sp-card-head"><div><h2>Decision brief</h2><p>Why Procurement is asking, in their words</p></div></div>
        <div class="fd-brief">
          <div><span class="fd-brief-label">What and why</span><p>{{ request.business_justification || "—" }}</p></div>
          <div class="good"><span class="fd-brief-label">If approved</span><p>{{ request.approval_impact || "—" }}</p></div>
          <div class="warn"><span class="fd-brief-label">If rejected or delayed</span><p>{{ request.rejection_risk || "—" }}</p></div>
          <div v-if="request.additional_notes"><span class="fd-brief-label">Other notes</span><p>{{ request.additional_notes }}</p></div>
        </div>
      </section>

      <div class="fd-cols">
        <section v-for="s in sections" :key="s.title" class="sp-card">
          <div class="sp-card-head"><div><h2>{{ s.title }}</h2><p v-if="s.sub">{{ s.sub }}</p></div></div>
          <span v-if="s.chip" class="sp-chip teal fd-chip">{{ s.chip }}</span>
          <dl class="fd-grid">
            <div v-for="row in s.rows" :key="row[0]" :class="{ wide: row[3] === 'wide' }">
              <dt>{{ row[0] }}</dt>
              <dd :class="row[2]">{{ row[1] }}</dd>
            </div>
          </dl>
        </section>
      </div>
    </template>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "@/utils/snackbar";
import api from "../../../../plugins/axios";
import HrPage from "../../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../components/vendor/shop/useVendorCatalog";
import { useAssignment } from "../../../../composables/useAssignment";
import "../../../../components/vendor/finance/finance.css";

const props = defineProps({
  id: { type: [String, Number], default: null },
  // 'finance' opens it from Finance (review only); 'inventory' from Procurement, where a draft can be edited and sent
  context: { type: String, default: "inventory", validator: (v) => ["finance", "inventory"].includes(v) },
});

const STATUS = {
  Draft: { tone: "slate", icon: "edit" },
  Pending: { tone: "amber", icon: "clock" },
  Approved: { tone: "green", icon: "check" },
  Paid: { tone: "teal", icon: "wallet" },
  Rejected: { tone: "red", icon: "close" },
};

const router = useRouter();
const route = useRoute();
const { can } = useAssignment();

const request = ref(null);
const error = ref("");
const submitting = ref(false);

const isInventoryContext = computed(() => props.context === "inventory");
const canEditFunding = computed(() => can("inventory_funding", "edit"));
const requestId = () => props.id ?? route.params.id ?? window.history.state?.requestId;

const displayRequestStatus = (r) => (r?.request_status === "Approved" && r?.payment_status === "paid" ? "Paid" : r?.request_status || "Unknown");
const status = computed(() => STATUS[displayRequestStatus(request.value)] ?? STATUS.Draft);
const statusText = computed(() => {
  const r = request.value;
  const by = r.reviewed_by_name || "Finance";
  if (displayRequestStatus(r) === "Paid") return `Paid by ${by} on ${formatDate(r.paid_at || r.accounting_decision_at)}.`;
  if (r.request_status === "Approved") return `Approved by ${by} on ${formatDate(r.accounting_decision_at)}.`;
  if (r.request_status === "Rejected") return `Rejected by ${by} on ${formatDate(r.accounting_decision_at)}.`;
  if (r.request_status === "Pending") return `Waiting for ${r.approver_name || r.accounting_manager_name || "the Finance approver"} to review it${r.submitted_to_accounting_at ? ` · sent ${formatDateTime(r.submitted_to_accounting_at)}` : ""}.`;
  return canEditFunding.value && isInventoryContext.value ? "A draft. Edit it, then send it to Finance." : "A draft that hasn't been sent to Finance yet.";
});

const qty = (n) => `${n ?? 0} ${request.value?.uom ?? ""}`.trim();
const marginTone = (m) => (m >= 30 ? "pos" : m >= 20 ? "mid" : "neg");
const sections = computed(() => {
  const r = request.value;
  const drop = (rows) => rows.filter((x) => x[1] !== null && x[1] !== undefined && x[1] !== "");
  return [
    {
      title: "Product and supplier",
      rows: drop([
        ["Category", r.flower_category],
        ["Variant or grade", r.variant],
        ["Requested", qty(r.requested_qty)],
        ["Minimum order", r.moq ? qty(r.moq) : "—"],
        ["Needed by", formatDate(r.required_delivery_date)],
        ["Preferred supplier", r.preferred_supplier || "—"],
        ["Other suppliers", r.alternative_suppliers || "—", "", "wide"],
      ]),
    },
    {
      title: "Stock",
      sub: "Why the shop is running short",
      rows: drop([
        ["In stock", qty(r.current_stock)],
        ["Reserved", qty(r.reserved_stock)],
        ["Free to use", qty(r.net_available_stock)],
        ["Needed", qty(r.required_quantity)],
        ["Short by", qty(r.stock_shortage_qty), "neg"],
        ["Already ordered", qty(r.incoming_stock)],
        ["Reason", r.reason_for_shortage, "", "wide"],
      ]),
    },
    {
      title: "Money",
      rows: drop([
        ["Unit cost", peso(r.estimated_unit_cost)],
        ["Total cost", peso(r.estimated_total_cost)],
        ["Selling price", peso(r.expected_selling_price)],
        ["Expected revenue", peso(r.expected_revenue)],
        ["Gross margin", `${r.estimated_gross_margin ?? 0}%`, marginTone(r.estimated_gross_margin)],
        ["Payment terms", r.payment_terms],
        r.tax_vat_estimate ? ["Tax or VAT", peso(r.tax_vat_estimate)] : null,
        r.logistics_cost ? ["Delivery cost", peso(r.logistics_cost)] : null,
      ].filter(Boolean)),
    },
    {
      title: "Urgency and risk",
      rows: drop([
        ["Urgency", r.urgency_level],
        ["Shelf life", r.shelf_life ? `${r.shelf_life} days` : null],
        ["Expected spoilage", r.expected_spoilage !== null && r.expected_spoilage !== undefined ? `${r.expected_spoilage}%` : null],
        ["Demand confidence", r.demand_confidence],
        r.missed_sales_impact ? ["Sales at risk", peso(r.missed_sales_impact), "neg"] : null,
        ["Season", r.seasonal_tag],
      ].filter(Boolean)),
    },
    {
      title: "Recommendation",
      sub: "What the requester suggests Finance approves",
      chip: r.finance_recommendation,
      rows: drop([
        ["Quantity", qty(r.recommended_qty)],
        ["Budget", peso(r.recommended_budget)],
        r.price_ceiling ? ["Price ceiling", peso(r.price_ceiling)] : null,
        ["Suggested supplier", r.suggested_supplier],
      ].filter(Boolean)),
    },
    {
      title: "Request details",
      rows: drop([
        ["Request ID", r.finance_request_id],
        ["Sales order", r.related_sales_order_id || "—"],
        ["Requested by", r.submitted_by_name],
        ["Approver", r.approver_name || r.accounting_manager_name || "Not assigned"],
        ["Sent to Finance", r.submitted_to_accounting_at ? formatDateTime(r.submitted_to_accounting_at) : "Not yet"],
      ]),
    },
  ].filter((s) => s.rows.length);
});

const peso = (n) => `₱${Number(n || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const formatDate = (d) => (d ? new Date(d).toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" }) : "—");
const formatDateTime = (d) => (d ? new Date(d).toLocaleString("en-PH", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" }) : "—");

async function fetchRequest() {
  error.value = "";
  const id = requestId();
  if (!id) {
    error.value = "No request selected. Go back and pick one.";
    return;
  }
  try {
    // Finance has its own route, so reviewers don't need Procurement's permission
    const base = props.context === "finance" ? "finance/funding-requests" : "procurement/inventory/funding-requests";
    const { data } = await api.get(`${base}/${id}`);
    request.value = data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load this funding request.");
  }
}

const goBack = () => router.push(props.context === "finance" ? "/erp/finance/funding-requests" : "/erp/procurement/inventory/funding-request");

function editRequest() {
  if (!canEditFunding.value) return toast.error("You don't have permission to edit funding requests");
  router.push({ name: "EditFundingRequest", params: { id: requestId() } });
}

async function submitRequest() {
  if (!canEditFunding.value) return toast.error("You don't have permission to send funding requests");
  if (!confirm("Send this request to Finance?")) return;
  submitting.value = true;
  try {
    const { data } = await api.post(`procurement/inventory/funding-requests/${requestId()}/submit`);
    toast.success(data.message || "Sent to Finance");
    await fetchRequest();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't send the request."));
  } finally {
    submitting.value = false;
  }
}

onMounted(fetchRequest);
</script>

<style scoped>
.fd-skel {
  display: grid;
  gap: 10px;
  margin-bottom: 16px;
}
.fd-status {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
  padding: 18px 20px;
}
.grow {
  flex: 1;
  min-width: 0;
}
.fd-status-top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}
.fd-status-top strong {
  font-size: 20px;
  font-weight: 600;
  color: var(--v-text);
}
.fd-status p {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--v-muted);
}
.fd-status-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.fd-decision {
  margin-bottom: 16px;
  border-left: 4px solid var(--v-green-text);
}
.fd-decision.bad {
  border-left-color: var(--v-red-text);
}
.sp-card + .sp-card,
.fd-cols {
  margin-top: 16px;
}
.fd-cols {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  align-items: start;
}
.fd-cols .sp-card {
  margin: 0;
  min-width: 0;
}
.fd-chip {
  display: inline-block;
  margin-bottom: 12px;
}
.fd-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px 18px;
  margin: 0;
}
.fd-grid .wide {
  grid-column: 1 / -1;
}
.fd-grid dt {
  font-size: 11.5px;
  color: var(--v-muted);
}
.fd-grid dd {
  margin: 2px 0 0;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--v-text);
  overflow-wrap: anywhere;
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
.fd-brief {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 10px;
}
.fd-brief > div {
  padding: 12px 14px;
  border-left: 3px solid var(--v-border-strong);
  border-radius: 0 12px 12px 0;
  background: var(--v-subtle);
}
.fd-brief > div.good {
  border-left-color: var(--v-green-text);
}
.fd-brief > div.warn {
  border-left-color: var(--v-amber-text);
}
.fd-brief-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--v-faint);
}
.fd-brief p {
  margin: 4px 0 0;
  font-size: 13px;
  line-height: 1.55;
  color: var(--v-text-2);
}
@media (max-width: 900px) {
  .fd-cols {
    grid-template-columns: minmax(0, 1fr);
  }
  .fd-status {
    flex-wrap: wrap;
  }
}
@media (max-width: 520px) {
  .fd-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
