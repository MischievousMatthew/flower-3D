<template>
  <ShopLayout section="finance" title="Refund Tracking" subtitle="Money given back to customers, refunds still to send, and balance adjustments.">
    <template #actions>
      <RangePicker @change="load" />
      <router-link :to="{ name: 'VendorReturns' }" class="sp-btn sp-btn-ghost">Manage requests</router-link>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <section class="fx-kpis" aria-label="Summary">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article class="sp-card fx-kpi">
          <span class="fx-kpi-label"><i class="dot red"></i>Refunded</span>
          <strong>{{ money(st.refunded) }}</strong>
          <span class="fx-kpi-sub">{{ st.refund_count }} {{ st.refund_count === 1 ? "refund" : "refunds" }} confirmed</span>
        </article>
        <article class="sp-card fx-kpi">
          <span class="fx-kpi-label"><i class="dot violet"></i>Refund rate</span>
          <strong>{{ st.refund_rate === null ? "—" : `${st.refund_rate}%` }}</strong>
          <span class="fx-kpi-sub">of sales in this period</span>
        </article>
        <article class="sp-card fx-kpi" :class="{ alert: st.pending_count }">
          <span class="fx-kpi-label"><i class="dot amber"></i>Waiting to be refunded</span>
          <strong>{{ money(st.pending) }}</strong>
          <span class="fx-kpi-sub">{{ st.pending_count }} agreed, not confirmed yet</span>
        </article>
        <article class="sp-card fx-kpi">
          <span class="fx-kpi-label"><i class="dot blue"></i>Being agreed</span>
          <strong>{{ money(st.negotiating) }}</strong>
          <span class="fx-kpi-sub">{{ st.negotiating_count }} {{ st.negotiating_count === 1 ? "request" : "requests" }} still open</span>
        </article>
      </template>
    </section>

    <div class="rf-layout">
      <div class="rf-main">
        <!-- Owed -->
        <section class="sp-card" aria-label="Refunds to send">
          <div class="sp-card-head"><div><h2>Refunds to send or confirm</h2><p>Agreed with the customer. They're not taken off your revenue until the customer confirms.</p></div></div>
          <ul v-if="!data" class="rf-list"><li v-for="n in 2" :key="n" class="rf-row"><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li></ul>
          <div v-else-if="!data.pending.length && !data.negotiating.length" class="sp-empty small">
            <span class="sp-empty-icon" v-html="ICONS.check"></span>
            <p>Nothing owed right now.</p>
          </div>
          <ul v-else class="rf-list">
            <li v-for="c in [...data.pending, ...data.negotiating]" :key="c.id" class="rf-row">
              <span class="fx-icon" :class="c.stage === 'confirm_refund' ? 'tone-blue' : c.stage.startsWith('awaiting') && data.pending.includes(c) ? 'tone-amber' : 'tone-slate'" v-html="ICONS.restore"></span>
              <div class="rf-main-text">
                <strong>{{ c.customer }}</strong>
                <span>{{ c.order_number }} · {{ c.resolution === "full_refund" ? "Full refund" : "Partial refund" }} · since {{ formatDate(c.since) }}</span>
              </div>
              <span class="sp-chip" :class="STAGE_TONE[c.stage] ?? 'slate'">{{ STAGE[c.stage] ?? c.stage }}</span>
              <span class="rf-amt">{{ money(c.amount) }}</span>
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="openCase(c)">Open</button>
            </li>
          </ul>
        </section>

        <!-- History -->
        <section class="sp-card" aria-label="Refund history">
          <div class="sp-card-head"><div><h2>Refunds &amp; adjustments</h2><p v-if="data">{{ data.range.label }}</p></div></div>
          <ul v-if="!data" class="rf-list"><li v-for="n in 3" :key="n" class="rf-row"><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li></ul>
          <div v-else-if="!data.history.length" class="sp-empty small">
            <span class="sp-empty-icon" v-html="ICONS.restore"></span>
            <p>No refunds or adjustments in this period.</p>
          </div>
          <ul v-else class="rf-list">
            <li v-for="e in data.history" :key="e.key" class="rf-row">
              <span class="fx-icon" :class="e.kind === 'refund' ? 'tone-pink' : 'tone-slate'" v-html="ICONS[e.kind === 'refund' ? 'restore' : 'edit']"></span>
              <div class="rf-main-text">
                <strong>{{ e.title }}</strong>
                <span>{{ e.party }}<template v-if="e.order_number"> · {{ e.order_number }}</template></span>
                <small>{{ formatDate(e.created_at, true) }}<template v-if="e.refund_method"> · via {{ METHOD[e.refund_method] ?? e.refund_method }}</template></small>
              </div>
              <span class="rf-amt" :class="e.direction">{{ e.direction === "in" ? "+" : "−" }}{{ money(e.amount) }}</span>
            </li>
          </ul>
        </section>
      </div>

      <aside class="rf-side">
        <section class="sp-card" aria-label="How refunds were sent">
          <div class="sp-card-head"><div><h2>How refunds were sent</h2></div></div>
          <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
          <p v-else-if="!data.by_method.length" class="sp-hint">No refunds sent in this period.</p>
          <ul v-else class="method-list">
            <li v-for="m in data.by_method" :key="m.method">
              <div class="m-top"><span>{{ m.label }}</span><b>{{ money(m.amount) }}</b></div>
              <div class="m-bar"><span :style="{ width: `${(m.amount / maxMethod) * 100}%` }"></span></div>
              <small>{{ m.count }} {{ m.count === 1 ? "refund" : "refunds" }}</small>
            </li>
          </ul>
        </section>
        <section class="sp-card" aria-label="Other outcomes">
          <div class="sp-card-head"><div><h2>Other outcomes</h2></div></div>
          <dl v-if="data" class="facts">
            <div><dt>Replacements sent instead</dt><dd>{{ st.replacements }}</dd></div>
            <div><dt>Adjustments in</dt><dd :class="{ pos: st.adjustments_in }">{{ st.adjustments_in ? "+" : "" }}{{ money(st.adjustments_in) }}</dd></div>
            <div><dt>Adjustments out</dt><dd :class="{ neg: st.adjustments_out }">{{ st.adjustments_out ? "−" : "" }}{{ money(st.adjustments_out) }}</dd></div>
          </dl>
          <p class="sp-hint">Only refunds a customer confirmed count against your profit.</p>
        </section>
      </aside>
    </div>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import RangePicker from "../../../components/vendor/finance/RangePicker.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { financeRange, formatDate, money } from "../../../components/vendor/finance/financeUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/finance/finance.css";

const router = useRouter();
const STAGE = {
  awaiting_vendor: "Needs your reply",
  awaiting_customer: "Waiting for customer",
  awaiting_return: "Item coming back",
  awaiting_refund: "Send the refund",
  confirm_refund: "Sent, awaiting confirmation",
};
const STAGE_TONE = { awaiting_vendor: "amber", awaiting_refund: "amber", awaiting_return: "violet", awaiting_customer: "slate", confirm_refund: "blue" };
const METHOD = { gcash: "GCash", maya: "Maya", bank_transfer: "bank transfer", cash: "cash", other: "other" };

const data = ref(null);
const error = ref("");
const st = computed(() => data.value?.stats ?? {});
const maxMethod = computed(() => Math.max(1, ...(data.value?.by_method ?? []).map((m) => m.amount)));

async function load() {
  error.value = "";
  try {
    const res = await api.get("vendor/books/refunds", { params: { from: financeRange.from, to: financeRange.to } });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load refunds.");
  }
}
const openCase = (c) => router.push({ name: "VendorReturns", query: { order: c.order_id } });

onMounted(load);
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
}
.fx-kpi.alert {
  border-color: #fbd38d;
  background: #fffcf5;
}
.rf-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 16px;
  align-items: start;
}
.rf-main {
  display: grid;
  gap: 16px;
  min-width: 0;
}
.rf-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.rf-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 2px;
  border-top: 1px solid #f2f5f3;
}
.rf-main-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.rf-main-text strong {
  font-size: 13.5px;
  font-weight: 600;
  color: #1a202c;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rf-main-text span {
  font-size: 12.5px;
  color: #4a5568;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rf-main-text small {
  font-size: 11.5px;
  color: #a0aec0;
}
.rf-amt {
  flex: none;
  min-width: 90px;
  text-align: right;
  font-size: 14px;
  font-weight: 600;
  color: #1a202c;
  white-space: nowrap;
}
.rf-amt.out {
  color: #c53030;
}
.rf-amt.in {
  color: #22753f;
}
.sp-empty.small {
  padding: 22px 10px;
}
.rf-side {
  position: sticky;
  top: 16px;
  display: grid;
  gap: 14px;
}
.method-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 12px;
}
.m-top {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 12.5px;
  color: #4a5568;
}
.m-top b {
  color: #1a202c;
}
.m-bar {
  height: 7px;
  margin: 5px 0 3px;
  border-radius: 999px;
  background: #edf2f7;
  overflow: hidden;
}
.m-bar span {
  display: block;
  height: 100%;
  background: #f687b3;
}
.method-list small {
  font-size: 11.5px;
  color: #a0aec0;
}
.facts {
  margin: 0 0 10px;
  display: grid;
  gap: 8px;
}
.facts div {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 12.5px;
  color: #4a5568;
}
.facts dd {
  margin: 0;
  font-weight: 600;
  color: #1a202c;
  white-space: nowrap;
}
.facts .pos {
  color: #22753f;
}
.facts .neg {
  color: #c53030;
}
@media (max-width: 1100px) {
  .rf-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .rf-side {
    position: static;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 720px) {
  .rf-side {
    grid-template-columns: minmax(0, 1fr);
  }
  .rf-row {
    flex-wrap: wrap;
  }
  .rf-main-text {
    flex-basis: calc(100% - 52px);
  }
  .rf-row > .sp-chip {
    margin-left: 52px;
  }
  .rf-amt {
    margin-left: auto;
    min-width: 0;
  }
}
</style>
