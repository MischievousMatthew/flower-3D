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

      <!-- What's being bought -->
      <section class="sp-card">
        <div class="sp-card-head"><div><h2>Products</h2><p>{{ lines.length }} {{ lines.length === 1 ? "product" : "products" }} · needed by {{ formatDate(request.required_delivery_date) }}</p></div></div>
        <div class="fd-table-wrap">
          <table class="fd-table">
            <thead>
              <tr><th scope="col">Product</th><th scope="col" class="num">Quantity</th><th scope="col" class="num">Unit cost</th><th scope="col" class="num">Amount</th></tr>
            </thead>
            <tbody>
              <tr v-for="l in lines" :key="l.key">
                <td><b>{{ l.name }}</b><small v-if="l.sub">{{ l.sub }}</small></td>
                <td class="num">{{ l.qty }} {{ (l.uom || "").toLowerCase() }}</td>
                <td class="num">{{ peso(l.cost) }}</td>
                <td class="num">{{ peso(l.total) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr v-if="Number(request.logistics_cost) > 0"><td colspan="3">Delivery fee</td><td class="num">{{ peso(request.logistics_cost) }}</td></tr>
              <tr v-if="Number(request.tax_vat_estimate) > 0"><td colspan="3">Tax or VAT</td><td class="num">{{ peso(request.tax_vat_estimate) }}</td></tr>
              <tr class="total"><td colspan="3">Total requested</td><td class="num">{{ peso(request.estimated_total_cost) }}</td></tr>
            </tfoot>
          </table>
        </div>
      </section>

      <!-- Why -->
      <section class="sp-card">
        <div class="sp-card-head"><div><h2>Why it's needed</h2><p>In Procurement's words</p></div></div>
        <div class="fd-why">
          <span v-if="request.urgency_level" class="sp-chip" :class="URGENCY_TONE[request.urgency_level]">{{ request.urgency_level }}</span>
          <span v-if="request.reason_for_shortage" class="sp-chip slate">{{ REASON_LABEL[request.reason_for_shortage] || request.reason_for_shortage }}</span>
        </div>
        <div class="fd-brief">
          <div><span class="fd-brief-label">Reason</span><p>{{ request.business_justification || "—" }}</p></div>
          <div v-if="request.approval_impact" class="good"><span class="fd-brief-label">If approved</span><p>{{ request.approval_impact }}</p></div>
          <div v-if="request.rejection_risk" class="warn"><span class="fd-brief-label">If rejected or delayed</span><p>{{ request.rejection_risk }}</p></div>
          <div v-if="request.additional_notes"><span class="fd-brief-label">Notes</span><p>{{ request.additional_notes }}</p></div>
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

const URGENCY_TONE = { Normal: "slate", Urgent: "amber", Critical: "red" };
const REASON_LABEL = {
  "New Order": "Customer orders to fill",
  "Seasonal Demand": "Season or holiday demand",
  Spoilage: "Stock spoiled or damaged",
  "Forecast Error": "Sold faster than expected",
  "Supplier Delay": "Supplier delivery was late",
};
const METHOD_LABEL = { bank: "Bank transfer", ewallet: "E-wallet" };

// One row per product; requests made before several products were allowed have one
const lines = computed(() => {
  const r = request.value;
  if (r.items?.length) {
    return r.items.map((i) => ({
      key: i.id,
      name: i.product_name,
      sub: [i.sku, i.current_stock !== null && i.current_stock !== undefined ? `${i.current_stock} in stock when requested` : null].filter(Boolean).join(" · "),
      qty: i.quantity,
      uom: i.uom,
      cost: i.unit_cost,
      total: i.line_total,
    }));
  }
  if (!r.product_name) return [];
  const qty = Number(r.requested_qty) || 0;
  return [{ key: "one", name: r.product_name, sub: [r.flower_category, r.variant].filter(Boolean).join(" · "), qty, uom: r.uom, cost: r.estimated_unit_cost, total: qty * Number(r.estimated_unit_cost || 0) }];
});

const sections = computed(() => {
  const r = request.value;
  const has = (v) => v !== null && v !== undefined && v !== "" && !(typeof v === "number" && v === 0);
  const rows = (list) => list.filter((x) => has(x[1]));
  const qty = (n) => (has(n) ? `${n} ${r.uom ?? ""}`.trim() : null);
  return [
    {
      title: "Payment",
      rows: rows([
        ["Paid by", METHOD_LABEL[r.payment_method]],
        [r.payment_method === "ewallet" ? "E-wallet" : "Bank", r.payment_channel],
        ["Payment terms", r.payment_terms],
        ["Total requested", peso(r.estimated_total_cost)],
      ]),
    },
    {
      title: "Request details",
      rows: rows([
        ["Request ID", r.finance_request_id],
        ["Requested by", r.submitted_by_name],
        ["Approver", r.approver_name || r.accounting_manager_name || "Not assigned"],
        ["Needed by", formatDate(r.required_delivery_date)],
        ["Sent to Finance", r.submitted_to_accounting_at ? formatDateTime(r.submitted_to_accounting_at) : "Not yet"],
        ["Sales order", r.related_sales_order_id],
      ]),
    },
    {
      // Only older requests filled these in
      title: "More details",
      sub: "From the older request form",
      chip: r.finance_recommendation,
      rows: rows([
        ["Preferred supplier", r.preferred_supplier && !r.items?.length ? r.preferred_supplier : null],
        ["Other suppliers", r.alternative_suppliers],
        ["Already ordered", Number(r.incoming_stock) ? qty(r.incoming_stock) : null],
        ["Short by", Number(r.stock_shortage_qty) ? qty(r.stock_shortage_qty) : null, "neg"],
        ["Selling price", Number(r.expected_selling_price) ? peso(r.expected_selling_price) : null],
        ["Gross margin", Number(r.estimated_gross_margin) ? `${r.estimated_gross_margin}%` : null],
        ["Expected spoilage", Number(r.expected_spoilage) ? `${r.expected_spoilage}%` : null],
        ["Demand confidence", r.demand_confidence],
        ["Sales at risk", Number(r.missed_sales_impact) ? peso(r.missed_sales_impact) : null, "neg"],
        ["Season", r.seasonal_tag],
        ["Price ceiling", Number(r.price_ceiling) ? peso(r.price_ceiling) : null],
        ["Suggested supplier", r.suggested_supplier],
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
.sk-line {
  height: 12px;
  margin: 6px auto;
  border-radius: 6px;
  width: 60%;
}
.sk-short {
  width: 35%;
}
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
.fd-why {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}
.fd-why:empty {
  display: none;
}
.fd-table-wrap {
  overflow-x: auto;
}
.fd-table {
  width: 100%;
  min-width: 460px;
  border-collapse: collapse;
  font-size: 13px;
}
.fd-table th {
  padding: 8px 10px;
  border-bottom: 1px solid var(--v-border);
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-muted);
  text-align: left;
}
.fd-table td {
  padding: 10px;
  border-bottom: 1px solid var(--v-border);
  color: var(--v-text);
  vertical-align: top;
}
.fd-table td b {
  display: block;
  font-weight: 600;
}
.fd-table td small {
  font-size: 12px;
  color: var(--v-muted);
}
.fd-table .num {
  text-align: right;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.fd-table tfoot td {
  border-bottom: 0;
  color: var(--v-text-2);
}
.fd-table tfoot .total td {
  border-top: 1px solid var(--v-border-strong);
  font-size: 15px;
  font-weight: 600;
  color: var(--v-text);
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
