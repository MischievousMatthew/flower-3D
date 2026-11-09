<template>
  <Teleport to="body">
    <Transition name="sp-fade"><div v-if="open" class="sp-backdrop" @click="close"></div></Transition>
    <Transition name="sp-pop">
      <div v-if="open" class="sp-modal-wrap">
        <form class="sp-modal fp" role="dialog" aria-modal="true" aria-labelledby="fp-title" novalidate @submit.prevent="submit">
          <div class="sp-modal-head">
            <div>
              <h3 id="fp-title">{{ isCredit ? "What the supplier owes" : "Pay the supplier" }}</h3>
              <p><span class="mono">{{ request.finance_request_id }}</span> · {{ request.product_name }}<template v-if="p.order"> · {{ p.order.order_number }}</template></p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="close"><span v-html="ICONS.close"></span></button>
          </div>

          <!-- How the amount was worked out -->
          <dl class="fp-calc">
            <div><dt>Approved budget</dt><dd>{{ peso(p.budget) }}</dd></div>
            <div v-if="p.timing === 'before_delivery' && p.confirmed_value !== null && p.confirmed_value !== undefined">
              <dt>Supplier confirmed</dt><dd>{{ peso(p.confirmed_value) }}</dd>
            </div>
            <div v-if="p.received_value > 0 || p.timing === 'after_delivery'"><dt>Received</dt><dd>{{ peso(p.received_value) }}</dd></div>
            <div v-if="p.delivery_fee > 0"><dt>Delivery fee</dt><dd>{{ peso(p.delivery_fee) }}</dd></div>
            <div v-if="p.net_paid > 0"><dt>Already paid</dt><dd>− {{ peso(p.net_paid) }}</dd></div>
            <div class="total">
              <dt>{{ isCredit ? "Supplier owes" : "Left to pay" }}</dt>
              <dd>{{ peso(isCredit ? p.supplier_owes : p.to_pay) }}</dd>
            </div>
          </dl>
          <div v-if="!isCredit" class="fp-dest" :class="{ none: !payout }">
            <span class="fx-icon" v-html="ICONS[payout?.method === 'ewallet' ? 'wallet' : 'card']"></span>
            <div v-if="payout" class="grow">
              <span class="sp-label">Send to</span>
              <b>{{ payout.provider }} · {{ payout.account_number }}</b>
              <small>{{ payout.account_name || "No name given" }}{{ p.order?.payout_from_order ? "" : " · from their last order" }}</small>
            </div>
            <div v-else class="grow">
              <span class="sp-label">Send to</span>
              <b>No account from the supplier yet</b>
              <small>They can add it from the order link in their email. Ask them for it before paying.</small>
            </div>
            <button v-if="payout" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="copyNumber">{{ copied ? "Copied" : "Copy number" }}</button>
          </div>

          <p v-if="!isCredit && p.released > 0" class="fp-note"><span v-html="ICONS.check"></span>{{ peso(p.released) }} of the budget isn't needed and won't be paid.</p>
          <p v-if="!isCredit && p.due_date" class="fp-note" :class="{ bad: p.overdue }"><span v-html="ICONS.calendar"></span>{{ p.overdue ? "Was due" : "Due by" }} {{ dateText(p.due_date) }}<template v-if="p.supplier_terms"> ({{ p.supplier_terms }})</template></p>

          <div v-if="isCredit" class="fp-choice" role="radiogroup" aria-label="What happened">
            <label class="fp-opt" :class="{ on: form.outcome === 'refund' }">
              <input v-model="form.outcome" type="radio" value="refund" />
              <b>They refunded it</b><small>Money came back to our bank or e-wallet</small>
            </label>
            <label class="fp-opt" :class="{ on: form.outcome === 'credit' }">
              <input v-model="form.outcome" type="radio" value="credit" />
              <b>Keep it as credit</b><small>Taken off a later order with them</small>
            </label>
          </div>

          <div class="sp-grid-2">
            <label v-if="!isCredit || form.outcome === 'refund'" class="sp-field">
              <span class="sp-label">{{ isCredit ? "Amount refunded" : "Amount paid" }} (₱)</span>
              <input v-model.number="form.amount" class="sp-input" :class="{ invalid: err.amount }" type="number" min="0.01" step="0.01" inputmode="decimal" />
              <small v-if="err.amount" class="sp-error">{{ err.amount }}</small>
            </label>
            <label v-if="!isCredit || form.outcome === 'refund'" class="sp-field">
              <span class="sp-label">{{ isCredit ? "Date received" : "Date paid" }}</span>
              <input v-model="form.paid_on" class="sp-input" :class="{ invalid: err.paid_on }" type="date" :max="today" />
              <small v-if="err.paid_on" class="sp-error">{{ err.paid_on }}</small>
            </label>
            <label v-if="!isCredit || form.outcome === 'refund'" class="sp-field">
              <span class="sp-label">{{ isCredit ? "Into" : "Paid through" }}</span>
              <select v-model="form.payment_channel" class="sp-select">
                <optgroup label="Bank"><option v-for="c in CHANNELS.bank" :key="c" :value="c">{{ c }}</option></optgroup>
                <optgroup label="E-wallet"><option v-for="c in CHANNELS.ewallet" :key="c" :value="c">{{ c }}</option></optgroup>
              </select>
              <small v-if="!isCredit && request.payment_channel && form.payment_channel !== request.payment_channel" class="sp-hint">Procurement asked for {{ request.payment_channel }}.</small>
            </label>
            <label v-if="!isCredit || form.outcome === 'refund'" class="sp-field">
              <span class="sp-label">Reference number</span>
              <input v-model.trim="form.reference" class="sp-input" :class="{ invalid: err.reference }" maxlength="100" placeholder="From the receipt or confirmation" />
              <small v-if="err.reference" class="sp-error">{{ err.reference }}</small>
            </label>
            <label class="sp-field sp-span-all">
              <span class="sp-label">{{ isCredit && form.outcome === "credit" ? "How it will be used" : "Note" }} <small v-if="!(isCredit && form.outcome === 'credit')">(optional)</small></span>
              <textarea v-model.trim="form.note" class="sp-textarea" :class="{ invalid: err.note }" rows="2" maxlength="500" :placeholder="isCredit && form.outcome === 'credit' ? 'e.g. Taken off our next rose order' : 'Anything worth keeping with the payment'"></textarea>
              <small v-if="err.note" class="sp-error">{{ err.note }}</small>
            </label>
          </div>

          <p v-if="formError" class="sp-error" role="alert">{{ formError }}</p>
          <div class="sp-modal-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="close">Cancel</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="busy">
              {{ busy ? "Saving…" : isCredit ? (form.outcome === "refund" ? "Record refund" : "Keep as credit") : `Mark ${peso(form.amount)} as paid` }}
            </button>
          </div>
        </form>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import api from "../../plugins/axios";
import { SHOP_ICONS as ICONS } from "../vendor/shop/shopIcons";
import { apiMessage } from "../vendor/shop/useVendorCatalog";
import { toast } from "@/utils/snackbar";
import { peso, dateText } from "./fundingPayment";

const props = defineProps({
  open: { type: Boolean, default: false },
  // The funding request, with its `payment` state
  request: { type: Object, default: () => ({}) },
  // pay | credit
  mode: { type: String, default: "pay" },
});
const emit = defineEmits(["close", "done"]);

// Matches FundingRequestController::PAYMENT_CHANNELS
const CHANNELS = {
  bank: ["BDO", "BPI", "Metrobank", "Landbank", "PNB", "Security Bank", "UnionBank", "RCBC", "China Bank", "EastWest Bank", "Other bank"],
  ewallet: ["GCash", "Maya", "GrabPay", "ShopeePay", "Coins.ph", "Other e-wallet"],
};
const pad = (n) => String(n).padStart(2, "0");
const now = new Date();
const today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;

const p = computed(() => props.request?.payment ?? {});
// Where the supplier asked to be paid
const payout = computed(() => p.value.order?.payout ?? null);
const copied = ref(false);
async function copyNumber() {
  try {
    await navigator.clipboard.writeText(String(payout.value.account_number).replace(/s+/g, ""));
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
  } catch {
    toast.info("Copy it by hand: " + payout.value.account_number);
  }
}
const isCredit = computed(() => props.mode === "credit");
const busy = ref(false);
const formError = ref("");
const err = reactive({});
const form = reactive({ outcome: "refund", amount: 0, paid_on: today, payment_channel: "", reference: "", note: "" });

watch(
  () => props.open,
  (open) => {
    if (!open) return;
    Object.keys(err).forEach((k) => delete err[k]);
    formError.value = "";
    Object.assign(form, {
      outcome: "refund",
      amount: isCredit.value ? p.value.supplier_owes : p.value.to_pay,
      paid_on: today,
      payment_channel: props.request.payment_channel || "BDO",
      reference: "",
      note: "",
    });
  },
);

const methodOf = (channel) => (CHANNELS.ewallet.includes(channel) ? "ewallet" : "bank");

function validate() {
  Object.keys(err).forEach((k) => delete err[k]);
  const money = !isCredit.value || form.outcome === "refund";
  const max = isCredit.value ? p.value.supplier_owes : p.value.to_pay;
  if (money) {
    if (!(Number(form.amount) > 0)) err.amount = "Enter an amount above ₱0.";
    else if (Number(form.amount) > Number(max) + 0.001) err.amount = `It can be up to ${peso(max)}.`;
    if (!form.paid_on) err.paid_on = "Pick the date.";
    else if (form.paid_on > today) err.paid_on = "It can't be in the future.";
    if (!form.reference) err.reference = "Enter the reference number, so it can be matched to the statement.";
  } else if (!form.note) {
    err.note = "Say how the credit will be used.";
  }
  return !Object.keys(err).length;
}

async function submit() {
  formError.value = "";
  if (!validate()) return;
  busy.value = true;
  const money = !isCredit.value || form.outcome === "refund";
  const body = {
    ...(isCredit.value ? { outcome: form.outcome } : {}),
    ...(money ? { amount: Number(form.amount), paid_on: form.paid_on, reference: form.reference, payment_channel: form.payment_channel, payment_method: methodOf(form.payment_channel) } : {}),
    note: form.note || null,
  };
  try {
    const { data } = await api.post(`finance/funding-requests/${props.request.id}/${isCredit.value ? "supplier-credit" : "pay"}`, body);
    toast.success(isCredit.value ? (form.outcome === "refund" ? "Refund recorded" : "Kept as credit with the supplier") : `${peso(form.amount)} recorded as paid`);
    emit("done", data.data);
  } catch (e) {
    for (const [k, v] of Object.entries(e?.response?.data?.errors ?? {})) err[k] = Array.isArray(v) ? v[0] : v;
    formError.value = apiMessage(e, "Couldn't save it.");
  } finally {
    busy.value = false;
  }
}

function close() {
  if (!busy.value) emit("close");
}
</script>

<style scoped>
.fp {
  max-width: 560px;
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.95em;
}
.fp-calc {
  display: grid;
  gap: 6px;
  margin: 0 0 12px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--v-subtle);
}
.fp-calc div {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 13px;
}
.fp-calc dt {
  color: var(--v-muted);
}
.fp-calc dd {
  margin: 0;
  font-weight: 600;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.fp-calc .total {
  margin-top: 4px;
  padding-top: 8px;
  border-top: 1px solid var(--v-border);
}
.fp-calc .total dd {
  font-size: 17px;
}
.fp-dest {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
  padding: 12px 14px;
  border: 1px solid var(--bc-accent);
  border-radius: 12px;
  background: var(--bc-accent-soft);
}
.fp-dest.none {
  border-color: var(--v-amber-text);
  background: var(--v-amber-soft);
}
.fp-dest .grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.fp-dest b {
  font-size: 14px;
  color: var(--v-text);
  overflow-wrap: anywhere;
}
.fp-dest small {
  font-size: 12px;
  color: var(--v-text-2);
}
.fp-note {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 10px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.fp-note.bad {
  color: var(--v-red-text);
}
.fp-note :deep(svg) {
  width: 14px;
  height: 14px;
  flex: none;
}
.fp-choice {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 14px;
}
.fp-opt {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px;
  border: 1px solid var(--v-border-strong);
  border-radius: 12px;
  background: var(--v-surface);
  cursor: pointer;
}
.fp-opt input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.fp-opt:has(input:focus-visible) {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.fp-opt b {
  font-size: 13px;
  color: var(--v-text);
}
.fp-opt small {
  font-size: 12px;
  color: var(--v-muted);
}
.fp-opt.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
@media (max-width: 520px) {
  .fp-choice {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
