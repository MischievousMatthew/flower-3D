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

    <InvoiceModal :row="invoiceFor" @close="invoiceFor = null" />
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import InvoiceModal from "../../../components/vendor/account/InvoiceModal.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { PAYMENT_STATUS, peso, pesoWhole, periodText, shortDate } from "../../../components/vendor/account/accountUi";
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

function openInvoice(r) {
  invoiceFor.value = r;
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
