<template>
  <Transition name="sp-fade"><div v-if="row" class="sp-backdrop" @click="emit('close')"></div></Transition>
  <Transition name="sp-pop">
    <div v-if="row" class="sp-modal-wrap">
      <div class="sp-modal wide" role="dialog" aria-modal="true" aria-labelledby="inv-title">
        <div class="sp-modal-head">
          <div><h3 id="inv-title">Invoice {{ row.reference }}</h3><p>Paid {{ longDate(row.date) }}</p></div>
          <button type="button" class="sp-icon-btn" aria-label="Close" @click="emit('close')"><span v-html="ICONS.close"></span></button>
        </div>

        <div v-if="error" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ error }}</div>
        <div v-else-if="!invoice" class="bl-sk"><div v-for="n in 5" :key="n" class="bc-skeleton sp-sk-line"></div></div>
        <article v-else ref="invoiceEl" class="bl-invoice">
          <header class="bl-inv-head">
            <div>
              <b class="bl-brand">BloomCraft</b>
              <span>Subscription invoice</span>
            </div>
            <div class="bl-inv-meta">
              <span class="sp-chip green">Paid</span>
              <dl>
                <dt>Invoice no.</dt><dd>{{ invoice.number }}</dd>
                <dt>Date</dt><dd>{{ longDate(invoice.issued_at) }}</dd>
              </dl>
            </div>
          </header>
          <section class="bl-inv-to">
            <small>Billed to</small>
            <b>{{ invoice.bill_to.name }}</b>
            <span v-if="invoice.bill_to.owner !== invoice.bill_to.name">{{ invoice.bill_to.owner }}</span>
            <span>{{ invoice.bill_to.email }}</span>
            <span v-if="invoice.bill_to.address">{{ invoice.bill_to.address }}</span>
          </section>
          <table class="bl-inv-lines">
            <thead><tr><th>Description</th><th>Period</th><th class="num">Amount</th></tr></thead>
            <tbody>
              <tr v-for="(l, i) in invoice.lines" :key="i">
                <td>{{ l.description }}</td>
                <td>{{ periodText(l.period) }}</td>
                <td class="num">{{ peso(l.amount) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr><td colspan="2">Total paid</td><td class="num">{{ peso(invoice.total) }}</td></tr>
            </tfoot>
          </table>
          <p class="bl-inv-pay">Paid with {{ invoice.payment.method }}<template v-if="invoice.payment.reference"> · Payment {{ invoice.payment.reference }}</template></p>
        </article>

        <div class="sp-modal-foot">
          <button type="button" class="sp-btn sp-btn-ghost" @click="emit('close')">Close</button>
          <button type="button" class="sp-btn sp-btn-dark" :disabled="!invoice" @click="printInvoice"><span v-html="ICONS.print"></span>Print or save as PDF</button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, watch } from "vue";
import api from "../../../plugins/axios";
import { SHOP_ICONS as ICONS } from "../shop/shopIcons";
import { apiMessage } from "../shop/useVendorCatalog";
import { longDate, peso, periodText } from "./accountUi";

// row: the billing item whose invoice to show (null hides the modal); base: where billing lives for this user
const props = defineProps({
  row: { type: Object, default: null },
  base: { type: String, default: "vendor/account/billing" },
});
const emit = defineEmits(["close"]);

const invoice = ref(null);
const error = ref("");
const invoiceEl = ref(null);

watch(
  () => props.row,
  async (row) => {
    invoice.value = null;
    error.value = "";
    if (!row) return;
    try {
      const res = await api.get(`${props.base}/${row.id}/invoice`);
      if (props.row?.id === row.id) invoice.value = res.data.data;
    } catch (e) {
      error.value = apiMessage(e, "Couldn't load this invoice.");
    }
  },
  { immediate: true },
);

// Prints just the invoice, in its own window, so the dashboard around it isn't printed
function printInvoice() {
  const win = window.open("", "_blank", "width=820,height=900");
  if (!win) return;
  const styles = [...document.querySelectorAll("style, link[rel=stylesheet]")].map((el) => el.outerHTML).join("");
  win.document.write(`<!doctype html><html><head><title>Invoice ${invoice.value.number}</title>${styles}<style>body{margin:32px;font-family:Poppins,system-ui,sans-serif;color:#1a202c}</style></head><body>${invoiceEl.value.outerHTML}</body></html>`);
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 300);
}
</script>

<style scoped>
.bl-sk {
  display: grid;
  gap: 12px;
}
.bl-invoice {
  padding: 22px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  font-size: 13px;
  color: var(--v-text);
}
.bl-inv-head {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--v-border);
}
.bl-inv-head > div:first-child {
  display: grid;
  gap: 2px;
}
.bl-brand {
  font-size: 20px;
  font-weight: 700;
  color: var(--bc-accent-deep);
}
.bl-inv-head span {
  color: var(--v-muted);
}
.bl-inv-meta {
  display: grid;
  justify-items: end;
  gap: 8px;
}
.bl-inv-meta dl {
  display: grid;
  grid-template-columns: auto auto;
  gap: 2px 12px;
  margin: 0;
  text-align: right;
}
.bl-inv-meta dt {
  color: var(--v-faint);
}
.bl-inv-meta dd {
  margin: 0;
  font-weight: 600;
}
.bl-inv-to {
  display: grid;
  gap: 2px;
  padding: 16px 0;
}
.bl-inv-to small {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--v-faint);
}
.bl-inv-to span {
  color: var(--v-text-2);
}
.bl-inv-lines {
  width: 100%;
  border-collapse: collapse;
}
.bl-inv-lines th {
  padding: 8px 0;
  border-bottom: 1px solid var(--v-border);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  text-align: left;
  color: var(--v-faint);
}
.bl-inv-lines td {
  padding: 12px 8px 12px 0;
  vertical-align: top;
}
.bl-inv-lines tfoot td {
  padding-top: 12px;
  border-top: 1px solid var(--v-text);
  font-weight: 700;
}
.bl-inv-lines .num {
  text-align: right;
  white-space: nowrap;
  padding-right: 0;
  font-variant-numeric: tabular-nums;
}
.bl-inv-pay {
  margin: 16px 0 0;
  font-size: 12px;
  color: var(--v-muted);
}
</style>
