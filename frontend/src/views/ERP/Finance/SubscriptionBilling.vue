<template>
  <HrPage title="Subscription & billing" subtitle="The shop's BloomCraft plan, when it renews, and every charge with its invoice.">
    <template #actions>
      <router-link v-if="!isEmployee" :to="{ name: 'VendorSubscription' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.sparkle"></span>Manage plan</router-link>
      <span v-else class="sp-chip slate sb-readonly"><span v-html="ICONS.lock"></span>Read-only · the shop owner manages the plan</span>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>
    <template v-if="sub && bill">
      <div v-if="cur && !cur.active" class="sp-banner error"><span v-html="ICONS.alert"></span>The plan has {{ cur.status === "expired" ? "expired" : "stopped" }}. Paid features are paused until it's renewed.</div>
      <div v-else-if="cur?.trial && cur.days_left <= 3" class="sp-banner warn"><span v-html="ICONS.clock"></span>The free trial ends in {{ countText(cur.days_left, "day") }}. Choose a plan before then to keep everything running.</div>
      <div v-if="bill.summary.failed" class="sp-banner warn">
        <span v-html="ICONS.alert"></span>{{ countText(bill.summary.failed, "subscription payment") }} didn't go through ({{ peso(bill.summary.failed_amount) }}).
        <button type="button" @click="filter = 'failed'">Show them</button>
      </div>
    </template>

    <!-- Current plan -->
    <section class="sp-card sb-plan" :class="cur ? PLAN_STATUS[cur.status]?.[1] : ''" aria-label="Current plan">
      <template v-if="!sub">
        <div class="bc-skeleton sp-sk-line w30"></div>
        <div class="bc-skeleton fx-sk-big"></div>
        <div class="bc-skeleton sp-sk-line w50"></div>
      </template>
      <div v-else-if="!cur" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.sparkle"></span>
        <h3>No plan yet</h3>
        <p>The shop hasn't picked a BloomCraft plan.</p>
      </div>
      <template v-else>
        <div class="sb-plan-main">
          <div>
            <span class="sb-plan-label">Current plan</span>
            <h2>{{ cur.plan_name }}</h2>
            <div class="sb-plan-tags">
              <span class="sp-chip" :class="PLAN_STATUS[cur.status]?.[1] ?? 'slate'">{{ PLAN_STATUS[cur.status]?.[0] ?? cur.status }}</span>
              <span v-if="cur.monthly_price !== null" class="sb-price">{{ cur.trial ? "Free while on trial" : `${pesoWhole(cur.monthly_price)} / month` }}</span>
            </div>
          </div>
          <div class="sb-when">
            <span>{{ cur.trial ? "Trial ends" : cur.active ? "Renews on" : "Ended on" }}</span>
            <strong>{{ longDate(cur.ends_at) }}</strong>
            <small v-if="cur.active">{{ countText(cur.days_left, "day") }} left<template v-if="bill?.summary.renew_amount && !cur.trial"> · {{ pesoWhole(bill.summary.renew_amount) }} due</template></small>
          </div>
        </div>
        <div v-if="cur.period_start && cur.ends_at" class="sb-period">
          <div class="sb-bar" role="img" :aria-label="`${periodPct}% of this period used`"><span :style="{ width: `${periodPct}%` }"></span></div>
          <div class="sb-period-dates"><span>{{ shortDate(cur.period_start) }}</span><span>{{ shortDate(cur.ends_at) }}</span></div>
        </div>
        <div v-if="sub.modules?.length" class="sb-modules">
          <span v-for="m in sub.modules" :key="m.key" class="sb-mod" :class="{ off: !m.included }" :title="m.included ? m.description : `Needs the ${m.required_plan} plan`">
            <span v-html="ICONS[m.included ? 'check' : 'lock']"></span>{{ m.label }}
          </span>
        </div>
      </template>
    </section>

    <!-- Numbers -->
    <section class="fx-kpis" aria-label="Billing">
      <template v-if="!bill">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div></div>
      </template>
      <template v-else>
        <article class="sp-card fx-kpi"><span class="fx-kpi-label"><span class="sb-ic tone-green" v-html="ICONS.card"></span>Paid in total</span><strong>{{ peso(bill.summary.total_paid) }}</strong><span class="fx-kpi-sub">{{ countText(bill.summary.payments, "payment") }}</span></article>
        <article class="sp-card fx-kpi"><span class="fx-kpi-label"><span class="sb-ic tone-violet" v-html="ICONS.history"></span>Last payment</span><strong>{{ bill.summary.last_paid_at ? shortDate(bill.summary.last_paid_at) : "—" }}</strong><span class="fx-kpi-sub">{{ bill.summary.plan_name ?? "" }}</span></article>
        <article class="sp-card fx-kpi"><span class="fx-kpi-label"><span class="sb-ic tone-red" v-html="ICONS.alert"></span>Failed payments</span><strong>{{ bill.summary.failed }}</strong><span class="fx-kpi-sub">{{ bill.summary.pending ? `${bill.summary.pending} still open` : "none waiting" }}</span></article>
        <article class="sp-card fx-kpi"><span class="fx-kpi-label"><span class="sb-ic tone-amber" v-html="ICONS.sparkle"></span>Free trials</span><strong>{{ bill.summary.trials }}</strong><span class="fx-kpi-sub">{{ bill.summary.trial_ends_at ? `current one ends ${shortDate(bill.summary.trial_ends_at)}` : "none running" }}</span></article>
      </template>
    </section>

    <!-- History -->
    <section class="sp-card sb-history" aria-label="Charges and payments">
      <div class="sp-card-head">
        <div><h2>Charges and payments</h2><p>Plans are paid a month at a time through PayMongo</p></div>
        <div class="sp-seg" role="tablist" aria-label="Show">
          <button v-for="f in FILTERS" :key="f.key" type="button" role="tab" :aria-selected="filter === f.key" :class="{ on: filter === f.key }" @click="filter = f.key">{{ f.label }}<span class="count">{{ count(f.key) }}</span></button>
        </div>
      </div>
      <ul v-if="!bill" class="sb-rows">
        <li v-for="n in 4" :key="n" class="sb-row"><div class="bc-skeleton fx-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
      </ul>
      <div v-else-if="!rows.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.receipt"></span>
        <h3>{{ bill.items.length ? "Nothing in this view" : "No charges yet" }}</h3>
        <p>{{ bill.items.length ? "Try another filter." : "Plan payments and free trials show up here." }}</p>
      </div>
      <ul v-else class="sb-rows">
        <li v-for="r in rows" :key="r.id" class="sb-row">
          <span class="fx-icon" :class="`tone-${PAYMENT_STATUS[r.status]?.[1] ?? 'slate'}`" v-html="ICONS[r.type === 'trial' ? 'sparkle' : 'card']"></span>
          <div class="grow">
            <strong>{{ r.plan_name }} plan<span class="muted">{{ r.type === "trial" ? " · free trial" : " · 1 month" }}</span></strong>
            <span class="sb-sub">{{ shortDate(r.date) }}<template v-if="r.period"> · covers {{ periodText(r.period) }}</template><template v-if="r.reference"> · <span class="mono">{{ r.reference }}</span></template></span>
          </div>
          <span class="sp-chip" :class="PAYMENT_STATUS[r.status]?.[1] ?? 'slate'">{{ PAYMENT_STATUS[r.status]?.[0] ?? r.status }}</span>
          <strong class="sb-amt">{{ r.type === "trial" ? "Free" : peso(r.amount) }}</strong>
          <div class="sb-act">
            <button v-if="r.invoice" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="invoiceFor = r"><span v-html="ICONS.receipt"></span>Invoice</button>
          </div>
        </li>
      </ul>
    </section>

    <Teleport to="body">
      <InvoiceModal :row="invoiceFor" :base="billingBase" @close="invoiceFor = null" />
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import InvoiceModal from "../../../components/vendor/account/InvoiceModal.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { PAYMENT_STATUS, PLAN_STATUS, longDate, peso, pesoWhole, periodText, shortDate } from "../../../components/vendor/account/accountUi";
import { useAuth } from "../../../composables/useAuth";
import "../../../components/vendor/finance/finance.css";
import "../../../components/vendor/account/account.css";

const FILTERS = [
  { key: "all", label: "All" },
  { key: "paid", label: "Paid" },
  { key: "failed", label: "Failed" },
  { key: "pending", label: "Open" },
  { key: "trial", label: "Trials" },
];
const MATCH = {
  all: () => true,
  paid: (r) => r.status === "paid",
  failed: (r) => ["failed", "expired", "cancelled"].includes(r.status),
  pending: (r) => r.status === "pending",
  trial: (r) => r.type === "trial",
};

const { user } = useAuth();
const isEmployee = computed(() => user.value?.type === "employee");
// Finance staff read the shop's plan through the ERP routes; the owner uses their own account routes
const subscriptionUrl = computed(() => (isEmployee.value ? "finance/subscription" : "vendor/account/subscription"));
const billingBase = computed(() => (isEmployee.value ? "finance/billing" : "vendor/account/billing"));

const sub = ref(null);
const bill = ref(null);
const error = ref("");
const filter = ref("all");
const invoiceFor = ref(null);

const cur = computed(() => sub.value?.current ?? null);
const rows = computed(() => (bill.value?.items ?? []).filter(MATCH[filter.value]));
const count = (key) => (bill.value?.items ?? []).filter(MATCH[key]).length;
const countText = (n, word, plural) => `${n} ${n === 1 ? word : plural ?? `${word}s`}`;
const periodPct = computed(() => {
  const c = cur.value;
  if (!c?.period_start || !c?.ends_at) return 0;
  const start = new Date(c.period_start).getTime();
  const end = new Date(c.ends_at).getTime();
  return Math.max(0, Math.min(100, Math.round(((Date.now() - start) / (end - start)) * 100)));
});

async function load() {
  error.value = "";
  try {
    const [s, b] = await Promise.all([api.get(subscriptionUrl.value), api.get(billingBase.value)]);
    sub.value = s.data.data;
    bill.value = b.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load the subscription.");
  }
}

onMounted(load);
</script>

<style scoped>
.muted {
  color: var(--v-faint);
  font-weight: 400;
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 11px;
}
.sb-readonly {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
}
.sb-readonly :deep(svg) {
  width: 13px;
  height: 13px;
}
.sb-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.sb-ic :deep(svg) {
  width: 15px;
  height: 15px;
}

/* Current plan */
.sb-plan {
  margin-bottom: 16px;
  padding: 22px 24px;
  background: linear-gradient(140deg, var(--bc-accent-soft) 0%, var(--v-surface) 55%);
  border-color: var(--bc-accent-muted);
}
.sb-plan.red {
  background: linear-gradient(140deg, var(--v-red-soft) 0%, var(--v-surface) 55%);
  border-color: var(--v-red-soft);
}
.sb-plan.violet {
  background: linear-gradient(140deg, var(--v-purple-soft) 0%, var(--v-surface) 55%);
}
.sb-plan-main {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-end;
  gap: 16px;
}
.sb-plan-label {
  font-size: 11.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--v-faint);
}
.sb-plan h2 {
  margin: 2px 0 8px;
  font-size: 28px;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--v-text);
}
.sb-plan-tags {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}
.sb-price {
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text-2);
}
.sb-when {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}
.sb-when span {
  font-size: 12px;
  color: var(--v-muted);
}
.sb-when strong {
  font-size: 18px;
  font-weight: 600;
  color: var(--v-text);
}
.sb-when small {
  font-size: 12px;
  color: var(--v-text-2);
}
.sb-period {
  margin-top: 18px;
}
.sb-bar {
  height: 8px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.sb-bar span {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: var(--bc-accent);
}
.sb-period-dates {
  display: flex;
  justify-content: space-between;
  margin-top: 6px;
  font-size: 11.5px;
  color: var(--v-faint);
}
.sb-modules {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 18px;
  padding-top: 16px;
  border-top: 1px solid var(--v-border);
}
.sb-mod {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--v-surface);
  border: 1px solid var(--v-border-strong);
  font-size: 12px;
  font-weight: 500;
  color: var(--v-text);
}
.sb-mod :deep(svg) {
  width: 12px;
  height: 12px;
  color: var(--v-green-text);
}
.sb-mod.off {
  color: var(--v-faint);
  border-style: dashed;
}
.sb-mod.off :deep(svg) {
  color: var(--v-faint);
}

/* History */
.sb-history {
  min-width: 0;
}
.sb-rows {
  list-style: none;
  margin: 0;
  padding: 0;
}
.sb-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 6px;
  border-top: 1px solid var(--v-border);
  border-radius: 10px;
  transition: background 0.15s;
}
.sb-row:hover {
  background: var(--v-subtle);
}
.sb-row .grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.sb-row strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.sb-sub {
  font-size: 12px;
  color: var(--v-faint);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sb-amt {
  flex: none;
  width: 110px;
  text-align: right;
}
.sb-act {
  flex: none;
  width: 100px;
  display: flex;
  justify-content: flex-end;
}
.sb-act .sp-btn :deep(svg) {
  width: 14px;
  height: 14px;
}

@media (max-width: 720px) {
  .sb-plan-main {
    align-items: flex-start;
  }
  .sb-when {
    align-items: flex-start;
  }
  .sb-row {
    flex-wrap: wrap;
  }
  .sb-row .grow {
    flex-basis: calc(100% - 60px);
  }
  .sb-row .sp-chip {
    margin-left: 56px;
  }
  .sb-amt {
    width: auto;
    margin-left: auto;
  }
  .sb-act {
    width: auto;
    margin-left: 56px;
  }
  .sb-act:empty {
    display: none;
  }
  .sb-history .sp-card-head {
    flex-wrap: wrap;
  }
}
</style>
