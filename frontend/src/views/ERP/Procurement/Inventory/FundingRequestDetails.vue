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
            <div><dt>Approved quantity</dt><dd>{{ request.approved_quantity }} {{ request.uom || "units" }}</dd></div>
            <div><dt>{{ pay && !pay.legacy ? "Budget" : "Approved amount" }}</dt><dd>{{ peso(request.approved_amount) }}</dd></div>
          </template>
          <template v-else>
            <div><dt>Reason</dt><dd class="neg">{{ request.rejection_reason || "—" }}</dd></div>
            <div class="wide"><dt>Notes</dt><dd>{{ request.rejection_notes || "—" }}</dd></div>
          </template>
          <div v-if="request.accounting_remarks" class="wide"><dt>Remarks</dt><dd>{{ request.accounting_remarks }}</dd></div>
        </dl>
      </section>

      <!-- Paying the supplier -->
      <section v-if="pay && stage" class="sp-card fd-pay">
        <div class="sp-card-head">
          <div><h2>Payment</h2><p>{{ pay.legacy ? "Approved before supplier payments were tracked" : timingText(pay) }}<template v-if="pay.supplier_terms"> · terms: {{ pay.supplier_terms }}</template></p></div>
          <div v-if="!isInventoryContext" class="fd-pay-actions">
            <button v-if="pay.stage === 'to_pay'" type="button" class="sp-btn sp-btn-primary" :disabled="!canPay" :title="canPay ? '' : PERMISSION_TOOLTIPS.approve" @click="payMode = 'pay'"><span v-html="ICONS.wallet"></span>Pay {{ peso(pay.to_pay) }}</button>
            <button v-if="pay.stage === 'supplier_owes'" type="button" class="sp-btn sp-btn-primary" :disabled="!canPay" @click="payMode = 'credit'">Settle {{ peso(pay.supplier_owes) }}</button>
          </div>
        </div>

        <div class="fd-pay-state" :class="stage.tone">
          <span class="fx-icon" :class="`tone-${stage.tone}`" v-html="ICONS[stage.icon]"></span>
          <div><b>{{ stage.label }}</b><p>{{ stage.text }}</p></div>
        </div>

        <p v-if="pay.order && !pay.legacy" class="fd-payout">
          <span v-html="ICONS[pay.order.payout?.method === 'ewallet' ? 'wallet' : 'card']"></span>
          <template v-if="pay.order.payout">Supplier is paid into <b>{{ pay.order.payout.provider }} {{ pay.order.payout.account_number }}</b><template v-if="pay.order.payout.account_name"> · {{ pay.order.payout.account_name }}</template></template>
          <template v-else>The supplier hasn't given an account to be paid into yet.</template>
        </p>

        <ol v-if="!pay.legacy" class="fd-steps" aria-label="Payment steps">
          <li v-for="st in steps" :key="st.key" :class="st.state"><span class="fd-dot" aria-hidden="true"></span><b>{{ st.label }}</b><small>{{ st.sub }}</small></li>
        </ol>

        <dl v-if="!pay.legacy" class="fd-money">
          <div><dt>Budget</dt><dd>{{ peso(pay.budget) }}</dd></div>
          <div v-if="pay.confirmed_value !== null && pay.confirmed_value !== undefined"><dt>Supplier confirmed</dt><dd>{{ peso(pay.confirmed_value) }}</dd></div>
          <div v-if="pay.received_value > 0"><dt>Received</dt><dd>{{ peso(pay.received_value) }}</dd></div>
          <div v-if="pay.due !== null && pay.due !== undefined"><dt>Due{{ pay.due_date ? ` by ${dateText(pay.due_date)}` : "" }}</dt><dd :class="{ neg: pay.overdue }">{{ peso(pay.due) }}</dd></div>
          <div><dt>Paid</dt><dd>{{ peso(pay.net_paid) }}</dd></div>
          <div v-if="pay.released > 0"><dt>Not needed</dt><dd class="muted">{{ peso(pay.released) }}</dd></div>
          <div v-if="pay.supplier_owes > 0"><dt>Supplier owes</dt><dd class="neg">{{ peso(pay.supplier_owes) }}</dd></div>
        </dl>

        <ul v-if="pay.payments?.length" class="fd-payments">
          <li v-for="m in pay.payments" :key="m.id">
            <span class="sp-chip" :class="PAYMENT_KIND[m.kind]?.tone">{{ PAYMENT_KIND[m.kind]?.label || m.kind }}</span>
            <div class="grow">
              <b>{{ m.kind === "refund" ? "+" : "" }}{{ peso(m.amount) }}<template v-if="m.payment_channel"> · {{ m.payment_channel }}</template></b>
              <small>{{ dateText(m.paid_on) }}<template v-if="m.reference"> · ref. {{ m.reference }}</template><template v-if="m.recorded_by_name"> · {{ m.recorded_by_name }}</template></small>
              <small v-if="m.note">{{ m.note }}</small>
            </div>
            <button v-if="m.kind === 'payment'" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="receipt = m"><span v-html="ICONS.receipt"></span>Receipt</button>
          </li>
        </ul>
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
                <td class="num">{{ l.qty }} {{ (l.uom || "").toLowerCase() }}<small v-if="l.approved !== null && l.approved !== undefined && Number(l.approved) !== Number(l.qty)" class="fd-approved">{{ l.approved }} approved</small></td>
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
    <FundingReceiptModal :open="!!receipt" :payment="receipt ?? {}" :request="request ?? {}" :pay="pay ?? {}" @close="receipt = null" />
    <FundingPayModal :open="!!payMode" :request="request ?? {}" :mode="payMode || 'pay'" @close="payMode = ''" @done="afterPay" />
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
import { PERMISSION_TOOLTIPS, useAssignment } from "../../../../composables/useAssignment";
import FundingPayModal from "../../../../components/procurement/FundingPayModal.vue";
import FundingReceiptModal from "../../../../components/procurement/FundingReceiptModal.vue";
import { paymentStage, timingText, dateText, PAYMENT_KIND } from "../../../../components/procurement/fundingPayment";
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

const displayRequestStatus = (r) => (r?.request_status === "Approved" && (r?.payment ? r.payment.stage === "paid" : r?.payment_status === "paid") ? "Paid" : r?.request_status || "Unknown");

// Paying the supplier
const pay = computed(() => request.value?.payment ?? null);
const stage = computed(() => paymentStage(pay.value));
const canPay = computed(() => can("funding_requests", "approve"));
const payMode = ref("");
const receipt = ref(null);
async function afterPay() {
  payMode.value = "";
  await fetchRequest();
}
const steps = computed(() => {
  const p = pay.value;
  if (!p) return [];
  const o = p.order;
  const confirmed = !!o?.supplier_confirmed_at && o.status !== "cancelled";
  const delivered = ["completed"].includes(o?.status);
  const paid = ["paid", "supplier_owes"].includes(p.stage) || p.net_paid > 0;
  const before = p.timing === "before_delivery";
  const st = (done, current) => (done ? "done" : current ? "current" : "");
  const list = [
    { key: "budget", label: "Budget approved", sub: peso(p.budget), state: "done" },
    { key: "order", label: "Purchase order", sub: o ? `${o.order_number} · ${o.supplier_name || "supplier"}` : "Not made yet", state: st(!!o, !o) },
    { key: "confirm", label: "Supplier confirms", sub: confirmed ? dateText(o.supplier_confirmed_at) : o?.status === "cancelled" ? "Cancelled" : "Waiting", state: st(confirmed, !!o && !confirmed) },
  ];
  const payStep = { key: "pay", label: "Paid", sub: paid ? peso(p.net_paid) : p.stage === "to_pay" ? `${peso(p.to_pay)} to pay` : "Not yet", state: st(paid && p.stage !== "to_pay", p.stage === "to_pay") };
  const deliverStep = { key: "deliver", label: "Delivered", sub: delivered ? dateText(o.completed_at) : "Not yet", state: st(delivered, confirmed && !delivered) };
  return before ? [...list, payStep, deliverStep] : [...list, deliverStep, payStep];
});
const status = computed(() => STATUS[displayRequestStatus(request.value)] ?? STATUS.Draft);
const statusText = computed(() => {
  const r = request.value;
  const by = r.reviewed_by_name || "Finance";
  if (displayRequestStatus(r) === "Paid") return `Approved by ${by}; the supplier was paid${r.paid_at ? ` on ${formatDate(r.paid_at)}` : ""}.`;
  if (r.request_status === "Approved") return `Approved by ${by} on ${formatDate(r.accounting_decision_at)}.${stage.value ? ` ${stage.value.label}.` : ""}`;
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
      approved: i.approved_quantity,
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
.fd-payout {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin: -6px 0 16px;
  font-size: 13px;
  color: var(--v-text-2);
}
.fd-payout :deep(svg) {
  width: 16px;
  height: 16px;
  color: var(--v-muted);
}
.fd-payout b {
  color: var(--v-text);
}
.fd-pay-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.fd-pay-state {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 16px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--v-subtle);
}
.fd-pay-state b {
  font-size: 14px;
  color: var(--v-text);
}
.fd-pay-state p {
  margin: 2px 0 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--v-text-2);
}
.fd-steps {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 8px;
  margin: 0 0 16px;
  padding: 0;
  list-style: none;
}
.fd-steps li {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-top: 18px;
  min-width: 0;
}
.fd-steps li::before {
  content: "";
  position: absolute;
  top: 5px;
  left: 12px;
  right: -8px;
  height: 2px;
  background: var(--v-border);
}
.fd-steps li:last-child::before {
  display: none;
}
.fd-steps li.done::before {
  background: var(--bc-accent);
}
.fd-dot {
  position: absolute;
  top: 0;
  left: 0;
  width: 12px;
  height: 12px;
  border: 2px solid var(--v-border-strong);
  border-radius: 50%;
  background: var(--v-surface);
}
.fd-steps li.done .fd-dot {
  border-color: var(--bc-accent);
  background: var(--bc-accent);
}
.fd-steps li.current .fd-dot {
  border-color: var(--v-amber-text);
  background: var(--v-amber-soft);
}
.fd-steps b {
  font-size: 12.5px;
  color: var(--v-text);
}
.fd-steps small {
  overflow: hidden;
  font-size: 11.5px;
  color: var(--v-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fd-steps li:not(.done):not(.current) b {
  color: var(--v-muted);
}
.fd-money {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 10px;
  margin: 0;
}
.fd-money div {
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--v-subtle);
}
.fd-money dt {
  font-size: 11.5px;
  color: var(--v-muted);
}
.fd-money dd {
  margin: 2px 0 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.fd-money dd.muted {
  color: var(--v-muted);
}
.fd-payments {
  display: grid;
  gap: 8px;
  margin: 16px 0 0;
  padding: 0;
  list-style: none;
}
.fd-payments li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
}
.fd-payments .grow {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.fd-payments b {
  font-size: 13.5px;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.fd-payments small {
  font-size: 12px;
  color: var(--v-muted);
}
@media (max-width: 720px) {
  .fd-steps {
    grid-template-columns: minmax(0, 1fr);
    gap: 0;
  }
  .fd-steps li {
    padding: 0 0 12px 22px;
  }
  .fd-steps li::before {
    top: 14px;
    bottom: 0;
    left: 5px;
    right: auto;
    width: 2px;
    height: auto;
  }
  .fd-dot {
    top: 2px;
  }
  .fd-steps small {
    white-space: normal;
  }
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
.fd-approved {
  display: block;
  color: var(--v-amber-text) !important;
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
