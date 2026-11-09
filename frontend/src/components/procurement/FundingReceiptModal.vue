<template>
  <Teleport to="body">
    <div v-if="open" class="rc-root">
      <div class="sp-backdrop rc-noprint" @click="$emit('close')"></div>
      <div class="sp-modal-wrap">
        <div class="sp-modal rc" role="dialog" aria-modal="true" aria-labelledby="rc-title">
          <div class="rc-top">
            <div>
              <span class="rc-brand">BloomCraft</span>
              <h3 id="rc-title">Payment receipt</h3>
            </div>
            <span class="rc-paid"><span v-html="ICONS.check"></span>Paid</span>
          </div>

          <div class="rc-amount">
            <span>Amount paid</span>
            <strong>{{ peso(payment.amount) }}</strong>
          </div>

          <dl class="rc-rows">
            <div><dt>Receipt no.</dt><dd class="mono">{{ payment.receipt_number }}</dd></div>
            <div><dt>Date and time</dt><dd>{{ when }}</dd></div>
            <div><dt>Paid through</dt><dd>{{ payment.payment_channel || "Bank or e-wallet" }}{{ viaPayMongo ? " via PayMongo" : "" }}</dd></div>
            <div><dt>{{ viaPayMongo ? "PayMongo payment ID" : "Reference no." }}</dt><dd class="mono">{{ payment.reference || "—" }}</dd></div>
            <div v-if="payment.recorded_by_name"><dt>Paid by</dt><dd>{{ payment.recorded_by_name }}</dd></div>
            <div><dt>To</dt><dd>{{ pay.order?.supplier_name || "Supplier" }}</dd></div>
            <div v-if="pay.order?.payout"><dt>Their account</dt><dd>{{ pay.order.payout.provider }} {{ masked }}</dd></div>
            <div><dt>For</dt><dd>{{ [pay.order?.order_number, request.finance_request_id].filter(Boolean).join(" · ") }}</dd></div>
            <div v-if="request.product_name"><dt>Items</dt><dd>{{ request.product_name }}</dd></div>
          </dl>

          <p class="rc-foot">Issued by BloomCraft for this shop's records. {{ viaPayMongo ? "The payment was processed by PayMongo." : "Recorded by Finance from the bank or e-wallet confirmation." }}</p>

          <div class="sp-modal-foot rc-noprint">
            <button type="button" class="sp-btn sp-btn-ghost" @click="$emit('close')">Close</button>
            <button type="button" class="sp-btn sp-btn-primary" @click="print"><span v-html="ICONS.print"></span>Print or save as PDF</button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed } from "vue";
import { SHOP_ICONS as ICONS } from "../vendor/shop/shopIcons";
import { peso } from "./fundingPayment";

const props = defineProps({
  open: { type: Boolean, default: false },
  payment: { type: Object, default: () => ({}) },
  request: { type: Object, default: () => ({}) },
  pay: { type: Object, default: () => ({}) },
});
defineEmits(["close"]);

const viaPayMongo = computed(() => String(props.payment.note || "").startsWith("Paid through PayMongo"));
const when = computed(() => {
  const d = props.payment.recorded_at || props.payment.paid_on;
  return d ? new Date(d).toLocaleString("en-PH", { month: "long", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "—";
});
const masked = computed(() => {
  const n = String(props.pay.order?.payout?.account_number || "");
  const digits = n.replace(/\D/g, "");
  return n.includes("•") ? n : digits ? `•••• ${digits.slice(-4)}` : "";
});
const print = () => window.print();
</script>

<style scoped>
.rc {
  max-width: 460px;
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12.5px;
}
.rc-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}
.rc-brand {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--bc-accent-deep);
}
.rc-top h3 {
  margin: 2px 0 0;
  font-size: 18px;
  color: var(--v-text);
}
.rc-paid {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--v-green-soft);
  color: var(--v-green-text);
  font-size: 12.5px;
  font-weight: 600;
}
.rc-paid :deep(svg) {
  width: 14px;
  height: 14px;
}
.rc-amount {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  margin-bottom: 14px;
  padding: 16px;
  border-radius: 14px;
  background: var(--v-subtle);
}
.rc-amount span {
  font-size: 12px;
  color: var(--v-muted);
}
.rc-amount strong {
  font-size: 28px;
  font-weight: 700;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.rc-rows {
  display: grid;
  margin: 0 0 14px;
}
.rc-rows div {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  padding: 9px 0;
  border-bottom: 1px dashed var(--v-border);
  font-size: 13px;
}
.rc-rows dt {
  flex: none;
  color: var(--v-muted);
}
.rc-rows dd {
  margin: 0;
  font-weight: 600;
  color: var(--v-text);
  text-align: right;
  overflow-wrap: anywhere;
}
.rc-foot {
  margin: 0;
  font-size: 11.5px;
  line-height: 1.5;
  color: var(--v-muted);
}
</style>

<style>
/* Printing shows only the receipt */
@media print {
  body > *:not(.rc-root) {
    display: none !important;
  }
  .rc-root .rc-noprint {
    display: none !important;
  }
  .rc-root .sp-modal-wrap {
    position: static !important;
    display: block !important;
    padding: 0 !important;
  }
  .rc-root .sp-modal {
    max-width: none !important;
    box-shadow: none !important;
    border: 0 !important;
  }
}
</style>
