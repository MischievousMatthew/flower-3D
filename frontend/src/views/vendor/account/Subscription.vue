<template>
  <ShopLayout section="account" title="Subscription Plan" subtitle="Your current plan, what it includes, and what you'd get by moving to another one.">
    <template #actions>
      <router-link :to="{ name: 'VendorBilling' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.receipt"></span>Billing history</router-link>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <div v-if="data?.pending_checkout" class="sp-banner warn">
      <span v-html="ICONS.clock"></span>
      You started paying for the {{ data.pending_checkout.plan_name }} plan but didn't finish.
      <a :href="data.pending_checkout.checkout_url" class="sp-btn sp-btn-sm sp-btn-dark sb-banner-btn">Continue payment</a>
    </div>

    <!-- Current plan -->
    <section class="sp-card sb-hero" :class="current ? `tone-${statusTone}` : 'empty'">
      <template v-if="!data">
        <div class="bc-skeleton sp-sk-line w30"></div>
        <div class="bc-skeleton sp-sk-line w50"></div>
        <div class="bc-skeleton sp-sk-block"></div>
      </template>
      <template v-else-if="current">
        <div class="sb-hero-main">
          <p class="sb-eyebrow">Your plan</p>
          <h2>{{ current.plan_name }} <span class="sp-chip" :class="statusTone">{{ statusLabel }}</span></h2>
          <p class="sb-price">
            <template v-if="current.trial">Free until {{ longDate(current.ends_at) }}, then {{ pesoWhole(current.monthly_price) }}/month</template>
            <template v-else-if="current.monthly_price !== null">{{ pesoWhole(current.monthly_price) }}<small>/month</small></template>
            <template v-else>Custom pricing</template>
          </p>
        </div>
        <div class="sb-period">
          <template v-if="current.active">
            <div class="sb-period-row"><span>{{ current.trial ? "Trial ends" : "Paid until" }}</span><b>{{ longDate(current.ends_at) }}</b></div>
            <div class="ac-meter" :class="{ near: current.days_left <= 7 }" role="progressbar" :aria-valuenow="current.days_left" aria-valuemin="0" :aria-valuemax="periodDays" :aria-label="`${current.days_left} days left`">
              <i :style="{ width: `${periodPct}%` }"></i>
            </div>
            <p class="sp-hint">{{ current.days_left }} day{{ current.days_left === 1 ? "" : "s" }} left. {{ renewHint }}</p>
          </template>
          <template v-else>
            <p class="sb-ended">Your plan ended on {{ longDate(current.ends_at) }}. Paid features are locked until you renew; your products, orders and data are all kept.</p>
          </template>
          <div class="sb-hero-actions">
            <button v-if="canRenew" type="button" class="sp-btn sp-btn-primary" :disabled="!!busy" @click="checkout(current.plan_key)">
              <span v-html="ICONS.card"></span>{{ busy === current.plan_key ? "Opening payment…" : current.trial ? `Pay for ${current.plan_name}` : `Renew ${current.plan_name}` }}
            </button>
            <a href="#compare" class="sp-btn sp-btn-ghost">Compare plans</a>
          </div>
        </div>
      </template>
      <template v-else>
        <div class="sb-hero-main">
          <p class="sb-eyebrow">Your plan</p>
          <h2>No plan yet</h2>
          <p class="sp-hint">Pick a plan below to start selling. Business comes with a free month if you haven't tried it.</p>
        </div>
      </template>
    </section>

    <!-- Benefits and restrictions -->
    <div class="ac-cols sb-feats">
      <section class="sp-card">
        <div class="sp-card-head"><div><h2>Included in your plan</h2><p>{{ included.length }} of {{ data?.modules.length ?? "…" }} features</p></div></div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <div v-else-if="!included.length" class="sp-empty sb-empty">
          <span class="sp-empty-icon" v-html="ICONS.lock"></span>
          <p>{{ current ? "Renew your plan to unlock your features again." : "Choose a plan to unlock features." }}</p>
        </div>
        <ul v-else class="ac-feats">
          <li v-for="m in included" :key="m.key">
            <span class="ac-feat-icon tone-green" v-html="ICONS.check"></span>
            <span><b>{{ m.label }}</b><small>{{ m.description }}</small></span>
          </li>
        </ul>
      </section>
      <section class="sp-card">
        <div class="sp-card-head"><div><h2>Not in your plan</h2><p>Move to a higher plan to use these.</p></div></div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <div v-else-if="!locked.length" class="sp-empty sb-empty">
          <span class="sp-empty-icon" v-html="ICONS.sparkle"></span>
          <p>You have every feature BloomCraft offers.</p>
        </div>
        <ul v-else class="ac-feats">
          <li v-for="m in locked" :key="m.key" class="off">
            <span class="ac-feat-icon tone-slate" v-html="ICONS.lock"></span>
            <span><b>{{ m.label }}</b><small>{{ m.description }}</small></span>
            <span v-if="m.required_plan" class="sp-chip slate ac-need">{{ m.required_plan }}+</span>
          </li>
        </ul>
      </section>
    </div>

    <!-- Plan comparison -->
    <h3 id="compare" class="st-section-title">Compare plans</h3>
    <div class="sb-plans">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-block"></div></div>
      </template>
      <article v-for="p in data?.plans ?? []" v-else :key="p.key" class="sp-card sb-plan" :class="{ current: isCurrent(p) }">
        <span v-if="isCurrent(p)" class="sp-chip green sb-plan-tag">Your plan</span>
        <span v-else-if="p.key === 'professional'" class="sp-chip violet sb-plan-tag">Most complete</span>
        <h3>{{ p.name }}</h3>
        <p class="sb-plan-price">
          <template v-if="p.custom_pricing">From {{ pesoWhole(p.minimum_monthly_price) }}<small>/month</small></template>
          <template v-else>{{ pesoWhole(p.monthly_price) }}<small>/month</small></template>
        </p>
        <ul class="sb-plan-limits">
          <li v-for="[key, label, unit] in LIMIT_ROWS" :key="key">
            <span>{{ label }}</span><b :class="{ 'ac-muted': p.limits[key] === 0 }">{{ limitText(p.limits[key], unit) }}</b>
          </li>
          <li><span>Features</span><b>{{ p.modules.length }} of {{ data.modules.length }}</b></li>
        </ul>
        <p v-if="gains(p).length" class="sb-plan-gains">
          <b>{{ isUpgrade(p) ? "Adds" : "Includes" }}:</b> {{ gains(p).join(", ") }}
        </p>
        <p v-else-if="losses(p).length" class="sb-plan-gains loses"><b>You'd lose:</b> {{ losses(p).join(", ") }}</p>

        <div class="sb-plan-foot">
          <button v-if="isCurrent(p) && !canRenew" type="button" class="sp-btn sp-btn-ghost" disabled><span v-html="ICONS.check"></span>Current plan</button>
          <router-link v-else-if="p.custom_pricing" :to="{ name: 'VendorSupport' }" class="sp-btn sp-btn-ghost">Talk to us</router-link>
          <button v-else-if="trialFor(p)" type="button" class="sp-btn sp-btn-primary" :disabled="!!busy" @click="startTrial">
            {{ busy === "trial" ? "Starting…" : "Start 1-month free trial" }}
          </button>
          <button v-else type="button" class="sp-btn" :class="isUpgrade(p) || isCurrent(p) ? 'sp-btn-primary' : 'sp-btn-ghost'" :disabled="!!busy" @click="choose(p)">
            {{ busy === p.key ? "Opening payment…" : actionLabel(p) }}
          </button>
        </div>
      </article>
    </div>
    <p class="sp-hint sb-foot">Payments go through PayMongo (GCash or Maya) and cover one month from the day you pay. Switching plans takes effect right away and starts a new month.</p>

    <!-- Confirm a downgrade -->
    <Transition name="sp-fade"><div v-if="downgrade" class="sp-backdrop" @click="downgrade = null"></div></Transition>
    <Transition name="sp-pop">
      <div v-if="downgrade" class="sp-modal-wrap">
        <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="dg-title">
          <div class="sp-modal-head">
            <div>
              <h3 id="dg-title">Move to {{ downgrade.name }}?</h3>
              <p>As soon as you pay, these stop working: {{ losses(downgrade).join(", ") }}. Your data stays and comes back if you upgrade again.</p>
            </div>
          </div>
          <div class="sp-modal-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="downgrade = null">Keep {{ current?.plan_name }}</button>
            <button type="button" class="sp-btn sp-btn-dark" :disabled="!!busy" @click="checkout(downgrade.key)">{{ busy ? "Opening payment…" : `Pay for ${downgrade.name}` }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { LIMIT_ROWS, PLAN_STATUS, limitText, longDate, pesoWhole, startPlanCheckout } from "../../../components/vendor/account/accountUi";
import { loadSubscriptionAccess } from "../../../composables/useSubscriptionAccess";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";
import "../../../components/vendor/account/account.css";

const route = useRoute();
const router = useRouter();
const data = ref(null);
const error = ref("");
const busy = ref(null);
const downgrade = ref(null);

const current = computed(() => data.value?.current ?? null);
const statusLabel = computed(() => (PLAN_STATUS[current.value?.status] ?? [current.value?.status])[0]);
const statusTone = computed(() => (PLAN_STATUS[current.value?.status] ?? [null, "slate"])[1]);
const included = computed(() => (data.value?.modules ?? []).filter((m) => m.included));
const locked = computed(() => (data.value?.modules ?? []).filter((m) => !m.included));

// Length of the current period, for the "days left" bar
const periodDays = computed(() => {
  const c = current.value;
  if (!c?.period_start || !c?.ends_at) return 30;
  return Math.max(1, Math.round((new Date(c.ends_at) - new Date(c.period_start)) / 86400000));
});
const periodPct = computed(() => Math.min(100, Math.max(3, (current.value.days_left / periodDays.value) * 100)));
const renewHint = computed(() => {
  const c = current.value;
  if (c.trial) return "Pay any time before it ends to keep everything running.";
  return c.days_left <= 7 ? "Renew now so nothing gets locked." : "Plans don't renew on their own; you'll get a reminder here near the end.";
});
// A paid-for plan can be renewed or paid for in its last week, after it ends, or during a trial
const canRenew = computed(() => {
  const c = current.value;
  if (!c || c.monthly_price === null) return false;
  return !c.active || c.trial || c.days_left <= 7;
});

const isCurrent = (p) => current.value?.plan_key === p.key && current.value.active;
const isUpgrade = (p) => !current.value?.active || p.rank > current.value.rank;
const currentModules = computed(() => new Set(included.value.map((m) => m.key)));
const labelOf = (key) => data.value.modules.find((m) => m.key === key)?.label ?? key;
const gains = (p) => (isCurrent(p) ? [] : p.modules.filter((k) => !currentModules.value.has(k)).map(labelOf));
const losses = (p) => (isCurrent(p) || !current.value?.active ? [] : [...currentModules.value].filter((k) => !p.modules.includes(k)).map(labelOf));
const trialFor = (p) => p.key === "business" && p.free_trial_available && data.value.business_trial_eligible && !current.value?.active;
const actionLabel = (p) => {
  if (isCurrent(p)) return current.value.trial ? `Pay for ${p.name}` : `Renew ${p.name}`;
  if (!current.value?.active) return `Choose ${p.name}`;
  return isUpgrade(p) ? `Upgrade to ${p.name}` : `Switch to ${p.name}`;
};

async function load() {
  error.value = "";
  try {
    const res = await api.get("vendor/account/subscription");
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load your subscription.");
  }
}

function choose(p) {
  if (losses(p).length) downgrade.value = p;
  else checkout(p.key);
}

async function checkout(planKey) {
  busy.value = planKey;
  try {
    await startPlanCheckout(planKey);
  } catch (e) {
    toast.error(apiMessage(e, e.message || "Couldn't start the payment."));
    busy.value = null;
  }
}

async function startTrial() {
  busy.value = "trial";
  try {
    await api.post("/vendor/subscription/business-trial");
    toast.success("Your free month of Business has started.");
    await Promise.all([load(), loadSubscriptionAccess().catch(() => {})]);
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't start the trial."));
  } finally {
    busy.value = null;
  }
}

// Back from PayMongo: confirm the payment with the server before saying anything
async function confirmReturningCheckout() {
  let pending;
  try {
    pending = JSON.parse(sessionStorage.getItem("vendor_subscription_checkout") || "null");
  } catch (_) {
    pending = null;
  }
  if (!pending?.checkoutId) return;
  try {
    const { data: res } = await api.get(`/vendor/subscription/checkout/${pending.checkoutId}`);
    if (res?.data?.status === "paid") {
      toast.success("Payment received. Your plan is active.");
      sessionStorage.removeItem("vendor_subscription_checkout");
      await Promise.all([load(), loadSubscriptionAccess().catch(() => {})]);
    } else if (["failed", "cancelled"].includes(res?.data?.status)) {
      sessionStorage.removeItem("vendor_subscription_checkout");
    }
  } catch (_) {
    // Try again on the next visit
  }
}

onMounted(async () => {
  await load();
  // The pricing page already confirmed this payment before sending the vendor here
  if (route.query.subscription_success) {
    sessionStorage.removeItem("vendor_subscription_checkout");
    toast.success(route.query.subscription_success === "business_trial" ? "Your free month of Business has started." : "Payment received. Your plan is active.");
    router.replace({ query: {} });
  } else {
    confirmReturningCheckout();
  }
});
</script>

<style scoped>
.sb-hero {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 380px);
  gap: 24px;
  align-items: center;
  margin-bottom: 16px;
  background: linear-gradient(135deg, var(--v-subtle) 0%, var(--v-surface) 60%);
}
.sb-hero.tone-red,
.sb-hero.tone-slate {
  background: linear-gradient(135deg, var(--v-red-soft) 0%, var(--v-surface) 60%);
}
.sb-hero.tone-violet {
  background: linear-gradient(135deg, var(--v-purple-soft) 0%, var(--v-surface) 60%);
}
.sb-hero.empty {
  grid-template-columns: 1fr;
}
.sb-eyebrow {
  margin: 0 0 4px;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--v-faint);
}
.sb-hero h2 {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin: 0;
  font-size: 26px;
  font-weight: 600;
  letter-spacing: -0.01em;
}
.sb-price {
  margin: 6px 0 0;
  font-size: 18px;
  font-weight: 600;
  color: var(--v-text);
}
.sb-price small {
  font-size: 13px;
  font-weight: 500;
  color: var(--v-muted);
}
.sb-period {
  display: grid;
  gap: 10px;
}
.sb-period-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: var(--v-text-2);
}
.sb-period .sp-hint {
  margin: 0;
}
.sb-ended {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--v-red-text);
}
.sb-hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}
.sb-banner-btn {
  margin-left: auto;
  text-decoration: none;
}
.sb-feats {
  margin-bottom: 8px;
}
.sb-empty {
  padding: 18px 0;
}
.sb-plans {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}
.sb-plan {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.sb-plan.current {
  border-color: var(--bc-accent);
  box-shadow: 0 0 0 1px var(--bc-accent);
}
.sb-plan-tag {
  position: absolute;
  top: 14px;
  right: 14px;
}
.sb-plan h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
}
.sb-plan-price {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
}
.sb-plan-price small {
  font-size: 12px;
  font-weight: 500;
  color: var(--v-muted);
}
.sb-plan-limits {
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 12.5px;
}
.sb-plan-limits li {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding: 6px 0;
  border-top: 1px solid var(--v-border);
}
.sb-plan-limits span {
  color: var(--v-muted);
}
.sb-plan-limits b {
  font-weight: 600;
  text-align: right;
}
.sb-plan-gains {
  margin: 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--v-text-2);
}
.sb-plan-gains.loses {
  color: var(--v-amber-text);
}
.sb-plan-foot {
  margin-top: auto;
  padding-top: 4px;
}
.sb-plan-foot .sp-btn {
  width: 100%;
  text-decoration: none;
}
.sb-foot {
  margin: 14px 0 0;
}
@media (max-width: 1100px) {
  .sb-plans {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 760px) {
  .sb-hero {
    grid-template-columns: minmax(0, 1fr);
  }
  .sb-plans {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
