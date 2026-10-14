<template>
  <ShopLayout section="account" title="Usage & Limits" :subtitle="subtitle">
    <template #actions>
      <router-link :to="{ name: 'VendorSubscription' }" class="sp-btn sp-btn-primary"><span v-html="ICONS.sparkle"></span>{{ data && !data.active ? "Choose a plan" : "Get more room" }}</router-link>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>
    <div v-if="data && !data.active" class="sp-banner warn">
      <span v-html="ICONS.lock"></span>You don't have an active plan, so these are the Starter limits and paid features are locked.
    </div>
    <div v-else-if="atLimit.length" class="sp-banner warn">
      <span v-html="ICONS.alert"></span>You've used all your {{ atLimit.map((r) => r.label.toLowerCase()).join(" and ") }}. Upgrade to add more.
    </div>

    <!-- Limits -->
    <div class="us-grid">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-line"></div></div>
      </template>
      <section v-for="r in data?.resources ?? []" v-else :key="r.key" class="sp-card us-card">
        <div class="us-head">
          <span class="sp-stat-icon" :class="`tone-${tone(r)}`" v-html="ICONS[RESOURCE_ICONS[r.key]]"></span>
          <h2>{{ r.label }}</h2>
          <span v-if="!r.unlimited && r.limit > 0" class="sp-chip" :class="tone(r)">{{ pct(r) }}%</span>
        </div>
        <p class="us-count ac-num">
          <strong>{{ r.usage.toLocaleString() }}</strong>
          <span v-if="r.unlimited">of unlimited</span>
          <span v-else-if="r.limit === 0">· not in your plan</span>
          <span v-else>of {{ r.limit.toLocaleString() }}</span>
        </p>
        <div class="ac-meter" :class="meterClass(r)" role="meter" :aria-valuenow="r.usage" aria-valuemin="0" :aria-valuemax="r.limit ?? r.usage" :aria-label="r.label">
          <i :style="{ width: `${r.unlimited ? 100 : r.limit === 0 ? 0 : Math.min(100, pct(r))}%` }"></i>
        </div>
        <p class="sp-hint us-hint">{{ r.hint }}</p>
        <p v-if="r.next_plan && (pct(r) >= 80 || r.limit === 0)" class="us-next">
          <span v-html="ICONS.sparkle"></span>{{ r.next_plan.name }} gives you {{ r.next_plan.limit === null ? "unlimited" : r.next_plan.limit.toLocaleString() }}.
        </p>
      </section>
    </div>

    <!-- Paid features -->
    <section class="sp-card">
      <div class="sp-card-head">
        <div><h2>Paid features</h2><p>{{ data ? `${included} of ${data.features.length} available on your plan` : "Loading…" }}</p></div>
        <div class="sp-seg" role="tablist" aria-label="Show">
          <button v-for="f in FILTERS" :key="f.key" type="button" role="tab" :class="{ on: filter === f.key }" :aria-selected="filter === f.key" @click="filter = f.key">{{ f.label }}</button>
        </div>
      </div>
      <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
      <ul v-else class="us-feats">
        <li v-for="f in features" :key="f.key" :class="{ off: !f.included }">
          <span class="ac-feat-icon" :class="f.included ? 'tone-green' : 'tone-slate'" v-html="f.included ? ICONS.check : ICONS.lock"></span>
          <span class="us-feat-text"><b>{{ f.label }}</b><small>{{ f.description }}</small></span>
          <span v-if="f.included" class="sp-chip green">On</span>
          <span v-else class="sp-chip slate" :title="`Included from the ${f.required_plan} plan`">{{ f.required_plan }}+</span>
        </li>
      </ul>
    </section>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/account/account.css";

const RESOURCE_ICONS = { products: "flower", staff_employees: "users", warehouses: "box", branches: "store" };
const FILTERS = [
  { key: "all", label: "All" },
  { key: "on", label: "In my plan" },
  { key: "off", label: "Locked" },
];

const data = ref(null);
const error = ref("");
const filter = ref("all");

const subtitle = computed(() =>
  data.value?.plan_name
    ? `What your ${data.value.plan_name} plan allows and how much of it you're using.`
    : "What your plan allows and how much of it you're using.",
);
const pct = (r) => (r.limit ? Math.round((r.usage / r.limit) * 100) : 0);
const tone = (r) => (r.unlimited ? "green" : r.limit === 0 ? "slate" : pct(r) >= 100 ? "red" : pct(r) >= 80 ? "amber" : "green");
const meterClass = (r) => (r.unlimited ? "open" : { near: pct(r) >= 80 && pct(r) < 100, full: pct(r) >= 100 });
const atLimit = computed(() => (data.value?.resources ?? []).filter((r) => !r.unlimited && r.limit > 0 && !r.can_add && r.key !== "branches"));
const included = computed(() => (data.value?.features ?? []).filter((f) => f.included).length);
const features = computed(() =>
  (data.value?.features ?? []).filter((f) => filter.value === "all" || (filter.value === "on") === f.included),
);

async function load() {
  error.value = "";
  try {
    const res = await api.get("vendor/account/usage");
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load your usage.");
  }
}

onMounted(load);
</script>

<style scoped>
.us-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 16px;
}
.us-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.us-head {
  display: flex;
  align-items: center;
  gap: 10px;
}
.us-head h2 {
  flex: 1;
  margin: 0;
  font-size: 14px;
  font-weight: 600;
}
.us-count {
  display: flex;
  align-items: baseline;
  gap: 6px;
  margin: 0;
  font-size: 13px;
  color: #718096;
}
.us-count strong {
  font-size: 26px;
  font-weight: 600;
  color: #1a202c;
}
.us-hint {
  margin: 0;
}
.us-next {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: auto 0 0;
  font-size: 12px;
  font-weight: 500;
  color: #6b46c1;
}
.us-next :deep(svg) {
  flex: none;
  width: 14px;
  height: 14px;
}
.us-feats {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 24px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.us-feats li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid #f4f6f8;
}
.us-feat-text {
  flex: 1;
  min-width: 0;
}
.us-feat-text b {
  display: block;
  font-size: 13px;
  font-weight: 600;
}
.us-feat-text small {
  display: block;
  font-size: 12px;
  color: #718096;
}
.us-feats li.off b {
  color: #718096;
}
@media (max-width: 1100px) {
  .us-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 700px) {
  .us-grid,
  .us-feats {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
