<template>
  <HrPage title="CRM dashboard" subtitle="Who buys from the shop, who comes back, what customers are waiting on, and how happy they are.">
    <template #actions>
      <RangePicker @change="load" />
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="load"><span v-html="ICONS.restore"></span>Refresh</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Customers -->
    <section class="fx-kpis" aria-label="Customers">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article v-for="k in kpis" :key="k.key" class="sp-card fx-kpi" :class="{ hero: k.hero }">
          <span class="fx-kpi-label"><span class="cd-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong>{{ k.value }}</strong>
          <span class="fx-kpi-sub">
            <span v-if="k.trend" class="fx-trend" :class="k.trend.cls">{{ k.trend.text }}</span>
            <span>{{ k.sub }}</span>
          </span>
          <div v-if="k.bar !== undefined" class="cd-meter" role="img" :aria-label="`${k.bar}%`"><span :style="{ width: `${k.bar}%` }"></span></div>
        </article>
      </template>
    </section>

    <!-- Waiting on the shop -->
    <section class="cd-attn" aria-label="Needs attention">
      <template v-if="!data">
        <div v-for="n in 3" :key="n" class="sp-card cd-tile"><div class="bc-skeleton fx-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <component :is="t.to ? 'router-link' : 'div'" v-for="t in tiles" :key="t.key" :to="t.to" class="sp-card cd-tile" :class="{ quiet: !t.count, link: !!t.to }">
          <span class="fx-icon" :class="`tone-${t.tone}`" v-html="ICONS[t.icon]"></span>
          <div class="grow">
            <span class="cd-tile-label">{{ t.label }}</span>
            <strong>{{ t.count }}</strong>
            <span class="cd-tile-sub" :class="{ warn: t.warn }">{{ t.sub }}</span>
          </div>
          <span v-if="t.to" class="cd-go" v-html="ICONS.chevronRight"></span>
        </component>
      </template>
    </section>

    <div class="cd-grid">
      <!-- Top customers -->
      <section class="sp-card" aria-label="Top customers">
        <div class="sp-card-head"><div><h2>Top customers</h2><p>By everything they've spent with the shop</p></div></div>
        <ul v-if="!data" class="cd-list">
          <li v-for="n in 5" :key="n" class="cd-row"><div class="bc-skeleton cd-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <div v-else-if="!data.top_customers.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.users"></span>
          <h3>No customers yet</h3>
          <p>People appear here after their first paid order.</p>
        </div>
        <ol v-else class="cd-list" :class="{ dim: loading }">
          <li v-for="(c, i) in data.top_customers" :key="c.id" class="cd-row">
            <span class="cd-rank" :class="`r${i + 1}`">{{ i + 1 }}</span>
            <Avatar :name="c.name" :src="c.avatar" />
            <div class="grow">
              <strong>{{ c.name }}</strong>
              <span>{{ c.orders }} {{ c.orders === 1 ? "order" : "orders" }} · last {{ ago(c.last_order_at) }}</span>
            </div>
            <div class="cd-spent">
              <strong>{{ money(c.spent) }}</strong>
              <div class="cd-spent-bar"><span :style="{ width: `${share(c.spent)}%` }"></span></div>
            </div>
          </li>
        </ol>
      </section>

      <!-- Satisfaction -->
      <section class="sp-card" aria-label="Satisfaction">
        <div class="sp-card-head"><div><h2>Satisfaction</h2><p>Product reviews in this period</p></div></div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <template v-else>
          <div class="cd-score">
            <div class="cd-score-big">
              <strong>{{ sat.average !== null ? sat.average.toFixed(1) : "—" }}</strong>
              <span class="cd-stars" :aria-label="`${sat.average ?? 0} out of 5`">
                <span v-for="n in 5" :key="n" :class="{ on: (sat.average ?? 0) >= n - 0.25 }" v-html="CRM_ICONS.star"></span>
              </span>
              <small>{{ sat.count }} {{ sat.count === 1 ? "review" : "reviews" }}<template v-if="ratingTrend"> · <span class="fx-trend" :class="ratingTrend.cls">{{ ratingTrend.text }}</span></template></small>
            </div>
            <ul class="cd-dist" :aria-label="distSummary">
              <li v-for="d in sat.distribution" :key="d.stars">
                <span class="cd-dist-l">{{ d.stars }}<span v-html="CRM_ICONS.star"></span></span>
                <div class="cd-dist-bar"><span :style="{ width: `${sat.count ? (d.count / sat.count) * 100 : 0}%` }" :class="`s${d.stars}`"></span></div>
                <small>{{ d.count }}</small>
              </li>
            </ul>
          </div>
          <dl class="cd-facts">
            <div><dt>Happy customers</dt><dd>{{ pct(sat.happy_rate) }}</dd><small>gave 4 or 5 stars</small></div>
            <div><dt>All-time rating</dt><dd>{{ sat.all_time_average !== null ? `${sat.all_time_average.toFixed(1)} / 5` : "—" }}</dd><small>{{ sat.all_time_count }} reviews</small></div>
          </dl>
        </template>
      </section>
    </div>

    <div class="cd-grid even">
      <!-- Responses -->
      <section class="sp-card" aria-label="Response times">
        <div class="sp-card-head"><div><h2>How fast customers hear back</h2><p>Chat replies and answered questions in this period</p></div></div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <dl v-else class="cd-resp">
          <div><dt>Average chat reply</dt><dd>{{ duration(data.response.avg_reply_minutes) }}</dd><small>{{ data.response.replied }} {{ data.response.replied === 1 ? "reply" : "replies" }} · {{ data.response.conversations }} {{ data.response.conversations === 1 ? "chat" : "chats" }}</small></div>
          <div><dt>Replied within an hour</dt><dd>{{ pct(data.response.within_hour_rate) }}</dd><div class="cd-meter"><span :style="{ width: `${data.response.within_hour_rate ?? 0}%` }"></span></div></div>
          <div><dt>Average time to answer a question</dt><dd>{{ data.inquiries.avg_answer_hours !== null ? duration(data.inquiries.avg_answer_hours * 60) : "—" }}</dd><small>{{ data.inquiries.answered }} answered · {{ data.inquiries.received }} asked</small></div>
          <div><dt>Chats waiting for a reply</dt><dd :class="{ warn: data.response.waiting }">{{ data.response.waiting }}</dd><small>last message was from the customer</small></div>
        </dl>
      </section>

      <!-- Inquiries -->
      <section class="sp-card" aria-label="Recent inquiries">
        <div class="sp-card-head">
          <div><h2>Recent inquiries</h2><p>Product questions, unanswered first</p></div>
          <router-link v-if="isOwner" :to="{ name: 'VendorInquiries' }" class="sp-btn sp-btn-ghost sp-btn-sm">Answer questions</router-link>
        </div>
        <ul v-if="!data" class="cd-list">
          <li v-for="n in 4" :key="n" class="cd-row"><div class="bc-skeleton cd-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w70"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <p v-else-if="!data.inquiries.recent.length" class="sp-hint">No questions from customers yet.</p>
        <ul v-else class="cd-list">
          <li v-for="q in data.inquiries.recent" :key="q.id" class="cd-row top">
            <Avatar :name="q.customer" :src="q.avatar" />
            <div class="grow">
              <strong>{{ q.customer }}<span v-if="q.product" class="muted"> · {{ q.product }}</span></strong>
              <span class="cd-q">“{{ q.question }}”</span>
              <span>{{ ago(q.asked_at) }}</span>
            </div>
            <span class="sp-chip" :class="q.status === 'open' ? (q.overdue ? 'red' : 'amber') : 'green'">{{ q.status === "open" ? (q.overdue ? "Overdue" : "Waiting") : q.status === "answered" ? "Answered" : "Closed" }}</span>
          </li>
        </ul>
      </section>
    </div>

    <!-- Latest reviews -->
    <section v-if="data?.satisfaction.latest.length" class="sp-card" aria-label="Latest reviews">
      <div class="sp-card-head"><div><h2>Latest reviews</h2><p>What customers said most recently</p></div></div>
      <div class="cd-reviews">
        <article v-for="r in data.satisfaction.latest" :key="r.id" class="cd-review">
          <span class="cd-stars sm" role="img" :aria-label="`${r.rating} out of 5 stars`"><span v-for="n in 5" :key="n" :class="{ on: r.rating >= n }" v-html="CRM_ICONS.star"></span></span>
          <p>{{ r.comment || "No comment, just stars." }}</p>
          <small>{{ r.customer }}<template v-if="r.product"> · {{ r.product }}</template> · {{ ago(r.at) }}</small>
        </article>
      </div>
    </section>
  </HrPage>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from "vue";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import RangePicker from "../../../components/vendor/finance/RangePicker.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { financeRange, money } from "../../../components/vendor/finance/financeUi";
import { useAssignment } from "../../../composables/useAssignment";
import { useAuth } from "../../../composables/useAuth";
import { CRM_ICONS } from "../../../components/crm/customerPrefs";
import "../../../components/vendor/finance/finance.css";

const { canView } = useAssignment();
const { user } = useAuth();
const isOwner = computed(() => user.value?.role === "vendor");

const data = ref(null);
const error = ref("");
const loading = ref(false);

const Avatar = defineComponent({
  props: { name: String, src: String },
  setup(p) {
    const failed = ref(false);
    return () =>
      p.src && !failed.value
        ? h("img", { class: "cd-avatar", src: p.src, alt: "", onError: () => (failed.value = true) })
        : h("span", { class: "cd-avatar", "aria-hidden": "true" }, (p.name || "?").split(/\s+/).filter(Boolean).map((w) => w[0]).slice(0, 2).join("").toUpperCase());
  },
});

const pct = (n) => (n === null || n === undefined ? "—" : `${Math.round(n)}%`);
const people = (n) => `${n} ${n === 1 ? "person" : "people"}`;
const trend = (now, before) => {
  if (!before) return now ? { text: "New", cls: "flat" } : null;
  const c = Math.round(((now - before) / before) * 100);
  return { text: `${c > 0 ? "▲" : c < 0 ? "▼" : "•"} ${Math.abs(c)}%`, cls: c > 0 ? "up" : c < 0 ? "down" : "flat" };
};

const sat = computed(() => data.value?.satisfaction ?? {});
const distSummary = computed(() => (sat.value.distribution ?? []).map((d) => `${d.count} gave ${d.stars} ${d.stars === 1 ? "star" : "stars"}`).join(", "));
const ratingTrend = computed(() => {
  const s = sat.value;
  if (s.average === null || s.previous_average === null || s.average === undefined) return null;
  const d = Math.round((s.average - s.previous_average) * 10) / 10;
  return { text: `${d > 0 ? "▲" : d < 0 ? "▼" : "•"} ${Math.abs(d).toFixed(1)} vs before`, cls: d > 0 ? "up" : d < 0 ? "down" : "flat" };
});

const kpis = computed(() => {
  const c = data.value.customers;
  return [
    { key: "total", label: "Customers", icon: "users", tone: "blue", value: c.total, sub: `${people(c.active)} bought this period` },
    { key: "new", label: "New customers", icon: "sparkle", tone: "green", value: c.new, hero: true, trend: trend(c.new, c.previous_new), sub: "first order this period" },
    { key: "repeat", label: "Repeat customers", icon: "restore", tone: "violet", value: c.repeat, sub: `${pct(c.repeat_rate)} of customers ordered again · ${c.returning} came back this period`, bar: c.repeat_rate ?? 0 },
    { key: "retention", label: "Retention", icon: "chart", tone: "teal", value: pct(c.retention_rate), sub: c.previous_buyers ? `${c.retained} of ${c.previous_buyers} buyers from the period before came back` : "no buyers in the period before", bar: c.retention_rate ?? 0 },
  ];
});

const tiles = computed(() => {
  const i = data.value.issues;
  return [
    {
      key: "inquiries", label: "Unanswered questions", icon: "help", tone: "amber", count: i.unanswered_inquiries,
      sub: i.overdue_inquiries ? `${i.overdue_inquiries} waiting over a day` : i.unanswered_inquiries ? "all asked within a day" : "Every question answered",
      warn: i.overdue_inquiries > 0, to: isOwner.value ? { name: "VendorInquiries" } : null,
    },
    {
      key: "requests", label: "Open refunds & returns", icon: "restore", tone: "pink", count: i.open_requests,
      sub: i.open_requests ? `oldest from ${ago(i.oldest_request_at)}` : "Nothing open",
      to: canView("refunds") ? { name: "FinanceRefunds" } : null,
    },
    {
      key: "chats", label: "Unread chats", icon: "chat", tone: "blue", count: i.unread_chats,
      sub: i.unread_chats ? `${i.unread_messages} unread ${i.unread_messages === 1 ? "message" : "messages"}` : "You're all caught up",
      to: canView("crm") ? { name: "CRMChat" } : null,
    },
  ];
});

const share = (spent) => {
  const top = data.value?.top_customers?.[0]?.spent || 1;
  return Math.max(4, Math.round((spent / top) * 100));
};

function ago(at) {
  if (!at) return "—";
  const mins = Math.round((Date.now() - new Date(at).getTime()) / 60000);
  if (mins < 60) return mins <= 1 ? "just now" : `${mins} min ago`;
  const hrs = Math.round(mins / 60);
  if (hrs < 24) return `${hrs} ${hrs === 1 ? "hour" : "hours"} ago`;
  const days = Math.round(hrs / 24);
  if (days < 30) return `${days} ${days === 1 ? "day" : "days"} ago`;
  return new Date(at).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
}
function duration(mins) {
  if (mins === null || mins === undefined) return "—";
  if (mins < 60) return `${Math.max(1, Math.round(mins))} min`;
  const hrs = mins / 60;
  if (hrs < 24) return `${hrs.toFixed(hrs < 10 ? 1 : 0)} hr`;
  return `${(hrs / 24).toFixed(1)} days`;
}

async function load() {
  error.value = "";
  loading.value = true;
  try {
    const res = await api.get("crm/dashboard", { params: { from: financeRange.from, to: financeRange.to } });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load the CRM dashboard.");
  } finally {
    loading.value = false;
  }
}
onMounted(load);
</script>

<style scoped>
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
.cd-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.cd-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.cd-meter {
  height: 6px;
  margin-top: 6px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.cd-meter span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--bc-accent);
  transition: width 0.4s ease;
}

/* Needs attention */
.cd-attn {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}
.cd-tile {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  color: inherit;
  text-decoration: none;
  transition: border-color 0.15s, transform 0.15s, box-shadow 0.15s;
}
.cd-tile.link:hover {
  border-color: var(--v-border-strong);
  transform: translateY(-1px);
  box-shadow: 0 10px 24px -14px rgba(15, 23, 42, 0.35);
}
.cd-tile.quiet .fx-icon {
  opacity: 0.6;
}
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.cd-tile-label {
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-text-2);
}
.cd-tile strong {
  font-size: 20px;
  font-weight: 600;
  color: var(--v-text);
}
.cd-tile-sub {
  font-size: 12px;
  color: var(--v-muted);
}
.cd-tile-sub.warn {
  color: var(--v-red-text);
  font-weight: 500;
}
.cd-go {
  display: inline-flex;
  color: var(--v-faint);
}
.cd-go :deep(svg) {
  width: 16px;
  height: 16px;
}

/* Grids */
.cd-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
  margin-bottom: 16px;
}
.cd-grid.even {
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}
.cd-grid > section {
  min-width: 0;
}

/* Lists */
.cd-list {
  list-style: none;
  margin: 0;
  padding: 0;
  transition: opacity 0.2s;
}
.cd-list.dim {
  opacity: 0.55;
}
.cd-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 2px;
  border-top: 1px solid var(--v-border);
}
.cd-row:first-child {
  border-top: 0;
}
.cd-row.top {
  align-items: flex-start;
}
.cd-row strong {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cd-row .grow > span {
  font-size: 12px;
  color: var(--v-muted);
}
.cd-row .sp-chip {
  flex: none;
}
.muted {
  font-weight: 500;
  color: var(--v-muted);
}
.cd-q {
  display: -webkit-box;
  overflow: hidden;
  color: var(--v-text-2) !important;
  font-size: 12.5px !important;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
:deep(.cd-avatar) {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 11px;
  object-fit: cover;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 12.5px;
  font-weight: 600;
}
.cd-rank {
  flex: none;
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--v-subtle);
  color: var(--v-muted);
  font-size: 11px;
  font-weight: 700;
}
.cd-rank.r1 {
  background: #f6c344;
  color: #4a3300;
}
.cd-rank.r2 {
  background: #cbd5e1;
  color: #1e293b;
}
.cd-rank.r3 {
  background: #e8a87c;
  color: #4a2310;
}
.cd-spent {
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 5px;
  width: 120px;
}
.cd-spent strong {
  font-size: 13.5px;
}
.cd-spent-bar {
  width: 100%;
  height: 5px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.cd-spent-bar span {
  display: block;
  height: 100%;
  margin-left: auto;
  border-radius: inherit;
  background: var(--bc-accent);
}

/* Satisfaction */
.cd-score {
  display: grid;
  grid-template-columns: 130px minmax(0, 1fr);
  gap: 18px;
  align-items: center;
}
.cd-score-big {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 14px 8px;
  border-radius: 16px;
  background: var(--v-subtle);
  text-align: center;
}
.cd-score-big strong {
  font-size: 34px;
  font-weight: 700;
  line-height: 1;
  color: var(--v-text);
}
.cd-score-big small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.cd-stars {
  display: inline-flex;
  gap: 2px;
  color: var(--v-subtle-2);
}
.cd-stars > span {
  display: inline-flex;
}
.cd-stars :deep(svg) {
  width: 15px;
  height: 15px;
}
.cd-stars.sm :deep(svg) {
  width: 13px;
  height: 13px;
}
.cd-dist-l {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}
.cd-dist-l :deep(svg) {
  width: 11px;
  height: 11px;
  color: #f5b301;
}
.cd-stars .on {
  color: #f5b301;
}
.cd-dist {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 7px;
}
.cd-dist li {
  display: grid;
  grid-template-columns: 26px minmax(0, 1fr) 24px;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--v-text-2);
}
.cd-dist small {
  text-align: right;
  color: var(--v-faint);
}
.cd-dist-bar {
  height: 8px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.cd-dist-bar span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #48bb78;
}
.cd-dist-bar .s3 {
  background: #f6ad55;
}
.cd-dist-bar .s2,
.cd-dist-bar .s1 {
  background: #fc8181;
}
.cd-facts,
.cd-resp {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin: 16px 0 0;
}
.cd-resp {
  margin: 0;
}
.cd-facts div,
.cd-resp div {
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--v-subtle);
}
.cd-resp .cd-meter {
  padding: 0;
  background: var(--v-subtle-2);
}
.cd-facts dt,
.cd-resp dt {
  font-size: 12px;
  color: var(--v-muted);
}
.cd-facts dd,
.cd-resp dd {
  margin: 2px 0;
  font-size: 18px;
  font-weight: 600;
  color: var(--v-text);
}
.cd-resp dd.warn {
  color: var(--v-red-text);
}
.cd-facts small,
.cd-resp small {
  font-size: 11.5px;
  color: var(--v-faint);
}

/* Reviews */
.cd-reviews {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 12px;
}
.cd-review {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px 16px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
}
.cd-review p {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--v-text-2);
}
.cd-review small {
  font-size: 11.5px;
  color: var(--v-faint);
}

@media (max-width: 1100px) {
  .cd-grid,
  .cd-grid.even {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 900px) {
  .cd-attn {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 560px) {
  .fx-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  .fx-kpi {
    padding: 14px;
  }
  .cd-score {
    grid-template-columns: minmax(0, 1fr);
  }
  .cd-spent {
    width: 90px;
  }
  .cd-facts,
  .cd-resp {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
