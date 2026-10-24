<template>
  <HrPage title="Reviews & feedback" subtitle="What customers say in product reviews and support cases: how happy they are over time, the complaints that keep coming up, and every review to reply to.">
    <template #actions>
      <div class="sp-seg" role="tablist" aria-label="View">
        <button type="button" role="tab" :aria-selected="view === 'insights'" :class="{ on: view === 'insights' }" @click="view = 'insights'">Insights</button>
        <button type="button" role="tab" :aria-selected="view === 'reviews'" :class="{ on: view === 'reviews' }" @click="view = 'reviews'">All reviews</button>
      </div>
    </template>

    <VendorReviews v-if="view === 'reviews'" erp bare />

    <template v-else>
      <div class="rf-bar">
        <label class="rf-range">
          <span class="sp-label">Period</span>
          <select v-model.number="months" class="sp-select" @change="load">
            <option :value="3">Last 3 months</option>
            <option :value="6">Last 6 months</option>
            <option :value="12">Last 12 months</option>
          </select>
        </label>
        <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="load"><span v-html="ICONS.restore"></span>Refresh</button>
      </div>

      <div v-if="error" class="sp-banner error">
        <span v-html="ICONS.alert"></span>{{ error }}
        <button type="button" @click="load">Try again</button>
      </div>

      <!-- Satisfaction -->
      <section class="fx-kpis" aria-label="Satisfaction">
        <template v-if="!data">
          <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div></div>
        </template>
        <template v-else>
          <article v-for="k in kpis" :key="k.key" class="sp-card fx-kpi" :class="{ hero: k.hero }">
            <span class="fx-kpi-label"><span class="rf-ic" :class="`tone-${k.tone}`" v-html="k.icon"></span>{{ k.label }}</span>
            <strong>{{ k.value }}</strong>
            <span class="fx-kpi-sub">
              <span v-if="k.trend" class="fx-trend" :class="k.trend.cls">{{ k.trend.text }}</span>
              <span>{{ k.sub }}</span>
            </span>
          </article>
        </template>
      </section>

      <div class="rf-grid">
        <!-- Trend -->
        <section class="sp-card rf-trend" aria-label="Feedback by month">
          <div class="sp-card-head">
            <div><h2>Feedback by month</h2><p>Reviews by how happy customers were, the average rating, and support cases</p></div>
          </div>
          <div v-if="!data" class="bc-skeleton rf-sk-chart"></div>
          <template v-else>
            <ul class="rf-legend" aria-hidden="true">
              <li><i class="happy"></i>4–5 stars</li>
              <li><i class="mixed"></i>3 stars</li>
              <li><i class="unhappy"></i>1–2 stars</li>
              <li><i class="case"></i>Support cases</li>
            </ul>
            <div class="rf-chart">
              <div v-for="m in data.trend" :key="m.key" class="rf-col" role="img" :aria-label="colLabel(m)">
                <span class="rf-avg">{{ m.average !== null ? m.average.toFixed(1) : "—" }}</span>
                <div class="rf-stack">
                  <span class="unhappy" :style="{ height: `${pctOf(m.unhappy)}%` }"></span>
                  <span class="mixed" :style="{ height: `${pctOf(m.reviews - m.happy - m.unhappy)}%` }"></span>
                  <span class="happy" :style="{ height: `${pctOf(m.happy)}%` }"></span>
                </div>
                <span class="rf-n">{{ m.reviews }}</span>
                <span class="rf-case" :class="{ some: m.cases }">{{ m.cases }}</span>
                <span class="rf-m">{{ m.short }}</span>
              </div>
            </div>
            <p class="sp-hint rf-note">Top number: average rating that month. Under each bar: reviews, then support cases.</p>
          </template>
        </section>

        <!-- Weakest products -->
        <section class="sp-card" aria-label="Lowest-rated products">
          <div class="sp-card-head"><div><h2>Needs attention</h2><p>Lowest-rated products with 2+ reviews</p></div></div>
          <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
          <p v-else-if="!data.products.length" class="sp-hint">Not enough reviews yet.</p>
          <ul v-else class="rf-products">
            <li v-for="p in data.products" :key="p.id">
              <span class="grow"><strong>{{ p.name }}</strong><small>{{ p.count }} reviews<template v-if="p.low"> · {{ p.low }} unhappy</template></small></span>
              <span class="rf-score" :class="scoreTone(p.average)"><span v-html="CRM_ICONS.star"></span>{{ p.average.toFixed(1) }}</span>
            </li>
          </ul>
        </section>
      </div>

      <!-- Recurring complaints -->
      <section class="sp-card" aria-label="Recurring complaints">
        <div class="sp-card-head">
          <div><h2>What customers keep complaining about</h2><p>Found in reviews of 3 stars or less and in support cases. "Recurring" means it came up {{ 2 }} or more times.</p></div>
        </div>
        <div v-if="!data" class="rf-themes"><div v-for="n in 3" :key="n" class="bc-skeleton sp-sk-block"></div></div>
        <div v-else-if="!data.themes.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.check"></span>
          <h3>No complaints in this period</h3>
          <p>Nothing in low reviews or support cases matched a common problem.</p>
        </div>
        <div v-else class="rf-themes">
          <article v-for="t in data.themes" :key="t.key" class="rf-theme" :class="{ recurring: t.recurring }">
            <header>
              <div class="grow">
                <strong>{{ t.label }}</strong>
                <small>{{ t.reviews }} {{ t.reviews === 1 ? "review" : "reviews" }} · {{ t.cases }} {{ t.cases === 1 ? "case" : "cases" }}</small>
              </div>
              <span class="rf-count">{{ t.count }}</span>
            </header>
            <div class="rf-tags">
              <span v-if="t.recurring" class="sp-chip red"><span v-html="ICONS.alert"></span>Recurring</span>
              <span class="sp-chip" :class="t.recent > t.before ? 'amber' : 'slate'">{{ t.recent }} in the last 30 days{{ t.recent > t.before ? " · rising" : "" }}</span>
              <span v-for="p in t.products" :key="p" class="sp-chip slate">{{ p }}</span>
            </div>
            <ul class="rf-quotes">
              <li v-for="(q, i) in t.examples" :key="i">
                <p>“{{ q.quote }}”</p>
                <small>
                  <template v-if="q.source === 'review'">{{ q.rating }}-star review<template v-if="q.product"> · {{ q.product }}</template></template>
                  <template v-else>Support case <span class="mono">{{ q.reference }}</span></template>
                  · {{ shortDate(q.at) }}
                </small>
              </li>
            </ul>
            <footer>
              <button v-if="t.reviews" type="button" class="st-link" @click="view = 'reviews'">Reply to low reviews</button>
              <router-link v-if="t.cases && canSeeCases" :to="{ name: 'CRMCases' }" class="st-link">Open support cases</router-link>
            </footer>
          </article>
        </div>
      </section>
    </template>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import VendorReviews from "../../vendor/crm/Reviews.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { CRM_ICONS } from "../../../components/crm/customerPrefs";
import { useAssignment } from "../../../composables/useAssignment";
import "../../../components/vendor/finance/finance.css";

const { can } = useAssignment();
const canSeeCases = computed(() => can("crm_cases", "view"));

const view = ref("insights");
const months = ref(6);
const data = ref(null);
const error = ref("");
const loading = ref(false);

const sat = computed(() => data.value?.satisfaction ?? {});
const pct = (n) => (n === null || n === undefined ? "—" : `${Math.round(n)}%`);
const delta = (now, before, unit) => {
  if (now === null || before === null || now === undefined || before === undefined) return null;
  const d = Math.round((now - before) * 10) / 10;
  return { text: `${d > 0 ? "▲" : d < 0 ? "▼" : "•"} ${Math.abs(d)}${unit} vs before`, cls: d > 0 ? "up" : d < 0 ? "down" : "flat" };
};
const kpis = computed(() => {
  const s = sat.value;
  return [
    { key: "avg", label: "Average rating", icon: CRM_ICONS.star, tone: "amber", value: s.average !== null ? `${s.average.toFixed(1)} / 5` : "—", sub: `${s.count} reviews`, trend: delta(s.recent_average, s.previous_average, "") },
    { key: "happy", label: "Satisfied customers", icon: ICONS.check, tone: "green", value: pct(s.happy_rate), sub: "gave 4 or 5 stars", hero: true, trend: delta(s.recent_happy_rate, s.previous_happy_rate, " pts") },
    { key: "answered", label: "Low reviews answered", icon: ICONS.chat, tone: "blue", value: pct(s.answered_rate), sub: "of reviews with 3 stars or less" },
    { key: "waiting", label: "Unhappy, no reply yet", icon: ICONS.alert, tone: "red", value: s.unanswered_low ?? 0, sub: s.unanswered_low ? "1–2 star reviews to answer" : "every unhappy review answered" },
  ];
});

const top = computed(() => Math.max(1, ...(data.value?.trend ?? []).map((m) => m.reviews)));
const pctOf = (n) => Math.max(0, (n / top.value) * 100);
const colLabel = (m) => `${m.label}: ${m.reviews} reviews (${m.happy} happy, ${m.unhappy} unhappy), average ${m.average ?? "none"}, ${m.cases} support cases`;
const scoreTone = (a) => (a >= 4 ? "good" : a >= 3 ? "mid" : "bad");
const shortDate = (d) => (d ? new Date(d).toLocaleDateString("en-PH", { month: "short", day: "numeric" }) : "");

async function load() {
  error.value = "";
  loading.value = true;
  try {
    const res = await api.get("crm/feedback", { params: { months: months.value } });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load feedback.");
  } finally {
    loading.value = false;
  }
}
onMounted(load);
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.rf-bar {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}
.rf-range {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 190px;
}
.fx-kpi.hero {
  background: linear-gradient(150deg, var(--bc-accent-soft) 0%, var(--v-surface) 60%);
  border-color: var(--bc-accent-muted);
}
.fx-kpi-sub {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.rf-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.rf-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.tone-red {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.rf-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
  margin-bottom: 16px;
}
.rf-grid > section {
  min-width: 0;
}

/* Chart */
.rf-sk-chart {
  height: 240px;
}
.rf-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 16px;
  margin: 0 0 12px;
  padding: 0;
  list-style: none;
  font-size: 12px;
  color: var(--v-muted);
}
.rf-legend li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.rf-legend i {
  width: 10px;
  height: 10px;
  border-radius: 3px;
}
.happy {
  background: #48bb78;
}
.mixed {
  background: #f6ad55;
}
.unhappy {
  background: #fc8181;
}
.rf-legend i.case {
  border-radius: 50%;
  background: var(--v-purple-text);
}
.rf-chart {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(0, 1fr);
  gap: 8px;
  height: 230px;
}
.rf-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 0;
}
.rf-avg {
  font-size: 12px;
  font-weight: 600;
  color: var(--v-text);
}
.rf-stack {
  flex: 1;
  display: flex;
  flex-direction: column-reverse;
  width: 100%;
  max-width: 36px;
  border-radius: 8px;
  overflow: hidden;
  background: var(--v-subtle);
}
.rf-stack span {
  display: block;
  width: 100%;
  transition: height 0.4s ease;
}
.rf-n {
  font-size: 11.5px;
  color: var(--v-text-2);
}
.rf-case {
  display: grid;
  place-items: center;
  min-width: 18px;
  height: 18px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--v-subtle);
  color: var(--v-faint);
  font-size: 10.5px;
  font-weight: 600;
}
.rf-case.some {
  background: var(--v-purple-soft);
  color: var(--v-purple-text);
}
.rf-m {
  font-size: 11.5px;
  color: var(--v-muted);
}
.rf-note {
  margin: 12px 0 0;
}

/* Products */
.rf-products {
  list-style: none;
  margin: 0;
  padding: 0;
}
.rf-products li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  border-top: 1px solid var(--v-border);
}
.rf-products li:first-child {
  border-top: 0;
}
.rf-products strong {
  overflow: hidden;
  font-size: 13.5px;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rf-products small {
  font-size: 12px;
  color: var(--v-muted);
}
.rf-score {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 12.5px;
  font-weight: 600;
}
.rf-score :deep(svg) {
  width: 12px;
  height: 12px;
}
.rf-score.good {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.rf-score.mid {
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.rf-score.bad {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}

/* Themes */
.rf-themes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 12px;
}
.rf-theme {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
}
.rf-theme.recurring {
  border-color: color-mix(in srgb, var(--v-red-text) 35%, transparent);
}
.rf-theme header {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.rf-theme header strong {
  font-size: 14px;
  color: var(--v-text);
}
.rf-theme header small {
  font-size: 12px;
  color: var(--v-muted);
}
.rf-count {
  font-size: 24px;
  font-weight: 700;
  line-height: 1;
  color: var(--v-text);
}
.rf-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.rf-tags .sp-chip :deep(svg) {
  width: 12px;
  height: 12px;
  margin-right: 3px;
  vertical-align: -2px;
}
.rf-quotes {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.rf-quotes li {
  padding: 8px 10px;
  border-left: 3px solid var(--v-border-strong);
  border-radius: 0 10px 10px 0;
  background: var(--v-subtle);
}
.rf-quotes p {
  margin: 0;
  font-size: 12.5px;
  font-style: italic;
  line-height: 1.5;
  color: var(--v-text-2);
}
.rf-quotes small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.rf-theme footer {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 14px;
  margin-top: auto;
}
.rf-theme footer .st-link {
  min-height: 32px;
}

@media (max-width: 1100px) {
  .rf-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 560px) {
  .fx-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  .rf-themes {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
