<template>
  <ShopLayout section="account" title="Billing History" subtitle="Every subscription payment and free trial, with an invoice for each payment.">
    <template #actions>
      <router-link :to="{ name: 'VendorSubscription' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.sparkle"></span>Manage plan</router-link>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <div class="sp-stats">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w70"></div></div></div>
      </template>
      <template v-else>
        <div class="sp-stat"><span class="sp-stat-icon tone-green" v-html="ICONS.card"></span><div><strong class="ac-num">{{ peso(s.total_paid) }}</strong><span>Paid in total</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon tone-blue" v-html="ICONS.receipt"></span><div><strong class="ac-num">{{ s.payments }}</strong><span>Payment{{ s.payments === 1 ? "" : "s" }}</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon tone-violet" v-html="ICONS.history"></span><div><strong>{{ s.last_paid_at ? shortDate(s.last_paid_at) : "—" }}</strong><span>Last payment</span></div></div>
        <div class="sp-stat">
          <span class="sp-stat-icon" :class="s.renews_at ? 'tone-amber' : 'tone-slate'" v-html="ICONS.calendar"></span>
          <div><strong>{{ s.renews_at ? shortDate(s.renews_at) : "—" }}</strong><span>{{ s.renews_at ? `Next payment due · ${pesoWhole(s.renew_amount)}` : "No payment due" }}</span></div>
        </div>
      </template>
    </div>

    <section class="sp-card">
      <div class="sp-card-head">
        <div><h2>Payments</h2><p>Plans are paid a month at a time through PayMongo.</p></div>
        <div class="sp-seg" role="tablist" aria-label="Show">
          <button v-for="f in FILTERS" :key="f.key" type="button" role="tab" :class="{ on: filter === f.key }" :aria-selected="filter === f.key" @click="filter = f.key">
            {{ f.label }}<span class="count">{{ count(f.key) }}</span>
          </button>
        </div>
      </div>

      <div v-if="!data" class="bl-sk">
        <div v-for="n in 4" :key="n" class="bc-skeleton sp-sk-line"></div>
      </div>
      <div v-else-if="!rows.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.receipt"></span>
        <h3>{{ data.items.length ? "Nothing in this view" : "No payments yet" }}</h3>
        <p>{{ data.items.length ? "Try another filter." : "When you pay for a plan, the payment and its invoice show up here." }}</p>
        <router-link v-if="!data.items.length" :to="{ name: 'VendorSubscription' }" class="sp-btn sp-btn-primary">See plans</router-link>
      </div>
      <div v-else class="st-scroll">
        <table class="st-table cards">
          <colgroup><col style="width: 15%" /><col /><col style="width: 22%" /><col style="width: 13%" /><col style="width: 15%" /><col style="width: 15%" /></colgroup>
          <thead>
            <tr><th>Date</th><th>Description</th><th>Covers</th><th class="num">Amount</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id">
              <td data-label="Date">{{ shortDate(r.date) }}</td>
              <td>
                <strong class="bl-desc">{{ r.plan_name }} plan{{ r.type === "trial" ? " · free trial" : " · 1 month" }}</strong>
                <small v-if="r.reference" class="st-sub">{{ r.reference }}</small>
              </td>
              <td data-label="Covers" class="st-sub">{{ periodText(r.period) }}</td>
              <td data-label="Amount" class="num">{{ r.type === "trial" ? "Free" : peso(r.amount) }}</td>
              <td><span class="sp-chip" :class="status(r)[1]">{{ status(r)[0] }}</span></td>
              <td class="bl-act">
                <button v-if="r.invoice" type="button" class="st-link" @click="openInvoice(r)">View invoice</button>
                <a v-else-if="r.checkout_url" :href="r.checkout_url" class="st-link">Finish paying</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Invoice -->
    <Transition name="sp-fade"><div v-if="invoiceFor" class="sp-backdrop" @click="closeInvoice"></div></Transition>
    <Transition name="sp-pop">
      <div v-if="invoiceFor" class="sp-modal-wrap">
        <div class="sp-modal wide" role="dialog" aria-modal="true" aria-labelledby="inv-title">
          <div class="sp-modal-head">
            <div><h3 id="inv-title">Invoice {{ invoiceFor.reference }}</h3><p>Paid {{ longDate(invoiceFor.date) }}</p></div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeInvoice"><span v-html="ICONS.close"></span></button>
          </div>

          <div v-if="invoiceError" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ invoiceError }}</div>
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
            <button type="button" class="sp-btn sp-btn-ghost" @click="closeInvoice">Close</button>
            <button type="button" class="sp-btn sp-btn-dark" :disabled="!invoice" @click="printInvoice"><span v-html="ICONS.print"></span>Print or save as PDF</button>
          </div>
        </div>
      </div>
    </Transition>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { PAYMENT_STATUS, longDate, peso, pesoWhole, periodText, shortDate } from "../../../components/vendor/account/accountUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";
import "../../../components/vendor/account/account.css";

const FILTERS = [
  { key: "all", label: "All" },
  { key: "paid", label: "Paid" },
  { key: "other", label: "Unpaid & trials" },
];

const data = ref(null);
const error = ref("");
const filter = ref("all");
const invoiceFor = ref(null);
const invoice = ref(null);
const invoiceError = ref("");
const invoiceEl = ref(null);

const s = computed(() => data.value.summary);
const matches = (r, key) => key === "all" || (key === "paid" ? r.status === "paid" : r.status !== "paid");
const rows = computed(() => (data.value?.items ?? []).filter((r) => matches(r, filter.value)));
const count = (key) => (data.value?.items ?? []).filter((r) => matches(r, key)).length;
const status = (r) => PAYMENT_STATUS[r.status] ?? [r.status, "slate"];

async function load() {
  error.value = "";
  try {
    const res = await api.get("vendor/account/billing");
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load your billing history.");
  }
}

async function openInvoice(r) {
  invoiceFor.value = r;
  invoice.value = null;
  invoiceError.value = "";
  try {
    const res = await api.get(`vendor/account/billing/${r.id}/invoice`);
    invoice.value = res.data.data;
  } catch (e) {
    invoiceError.value = apiMessage(e, "Couldn't load this invoice.");
  }
}
function closeInvoice() {
  invoiceFor.value = null;
}

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

onMounted(load);
</script>

<style scoped>
.grow {
  flex: 1;
}
.bl-sk {
  display: grid;
  gap: 12px;
}
.bl-desc {
  display: block;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.bl-act {
  text-align: right;
}
.bl-act a {
  text-decoration: none;
}

.bl-invoice {
  padding: 22px;
  border: 1px solid #edf2f7;
  border-radius: 14px;
  font-size: 13px;
  color: #1a202c;
}
.bl-inv-head {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #edf2f7;
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
  color: #718096;
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
  color: #a0aec0;
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
  color: #a0aec0;
}
.bl-inv-to span {
  color: #4a5568;
}
.bl-inv-lines {
  width: 100%;
  border-collapse: collapse;
}
.bl-inv-lines th {
  padding: 8px 0;
  border-bottom: 1px solid #edf2f7;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  text-align: left;
  color: #a0aec0;
}
.bl-inv-lines td {
  padding: 12px 8px 12px 0;
  vertical-align: top;
}
.bl-inv-lines tfoot td {
  padding-top: 12px;
  border-top: 1px solid #1a202c;
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
  color: #718096;
}
@media (max-width: 960px) {
  .sp-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 760px) {
  .bl-act {
    text-align: left;
  }
}
</style>
