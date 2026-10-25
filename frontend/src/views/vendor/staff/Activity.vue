<template>
  <ShopLayout section="staff" title="Activity Logs" subtitle="Important things your employees changed in the ERP, newest first. Pages they only looked at aren't listed.">
    <div v-if="settingUp" class="sp-banner info"><span v-html="ICONS.clock"></span>{{ settingUp }}</div>
    <div v-else-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load()">Try again</button>
    </div>

    <section v-if="!settingUp" class="sp-card">
      <div class="sp-toolbar ac-toolbar">
        <p class="st-sub ac-count">
          <template v-if="data">{{ data.meta.total }} {{ data.meta.total === 1 ? "change" : "changes" }}<template v-if="filtered"> match</template> · {{ data.today }} today</template>
          <span v-else class="bc-skeleton sp-sk-line w30 ac-sk-inline"></span>
        </p>
        <div class="ac-filters">
          <select v-model="who" class="sp-select" aria-label="Person" @change="load()">
            <option value="">Everyone</option>
            <option v-for="e in data?.employees ?? []" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
          <select v-model="area" class="sp-select" aria-label="Area" @change="load()">
            <option value="">All areas</option>
            <option v-for="a in data?.areas ?? []" :key="a" :value="a">{{ a }}</option>
          </select>
          <input v-model="from" type="date" class="sp-input ac-date" aria-label="From" :max="to || today" @change="load()" />
          <input v-model="to" type="date" class="sp-input ac-date" aria-label="To" :min="from" :max="today" @change="load()" />
          <button v-if="filtered" type="button" class="st-link" @click="clear">Clear</button>
        </div>
      </div>

      <div v-if="!data" class="ac-list">
        <div v-for="n in 6" :key="n" class="ac-row"><div class="bc-skeleton st-avatar sm"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </div>
      <div v-else-if="!data.entries.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.activity"></span>
        <h3>{{ filtered ? "Nothing matches" : "No activity yet" }}</h3>
        <p>{{ filtered ? "Try another person, area or date." : "When your employees time in, update deliveries, edit products or approve requests in the ERP, it shows up here." }}</p>
      </div>
      <div v-else class="ac-days">
        <section v-for="d in byDay" :key="d.day" class="ac-day">
          <h3>{{ d.label }}</h3>
          <ol class="ac-list">
            <li v-for="e in d.entries" :key="e.key" class="ac-row">
              <span class="st-avatar sm" :style="{ background: personColor(e.who) }">{{ initials(e.who) }}</span>
              <div class="ac-main">
                <p><b>{{ e.who }}</b> {{ lower(e.action) }}</p>
                <small><span class="sp-chip slate">{{ e.area }}</span></small>
              </div>
              <time :datetime="e.at" :title="new Date(e.at).toLocaleString('en-PH')">{{ clockTime(e.at) }}</time>
            </li>
          </ol>
        </section>
      </div>

      <ShowMore v-if="data && data.entries.length" tone="vendor" :has-more="list.hasMore.value" :loading="list.loadingMore.value" :shown="list.rows.value.length" :total="list.total.value" @more="list.showMore()" />
    </section>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import api from "../../../plugins/axios";
import ShowMore from "../../../components/common/ShowMore.vue";
import { useShowMoreInto } from "../../../composables/useShowMore";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { ymd } from "../../../components/vendor/finance/financeUi";
import { clockTime, personColor } from "../../../components/vendor/staff/staffUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";

const today = ymd(new Date());
const data = ref(null);
// 10 changes first, then 10 more, then 20 at a time
const list = useShowMoreInto(data, "entries", async (page) =>
  (await api.get("vendor/staff/activity", { params: { page, employee_id: who.value || undefined, area: area.value || undefined, from: from.value || undefined, to: to.value || undefined } })).data.data,
);
const error = ref("");
const settingUp = ref("");
const loading = ref(false);
const who = ref("");
const area = ref("");
const from = ref("");
const to = ref("");

const filtered = computed(() => !!(who.value || area.value || from.value || to.value));
const byDay = computed(() => {
  const days = new Map();
  for (const e of list.rows.value) {
    const day = ymd(new Date(e.at));
    if (!days.has(day)) days.set(day, { day, label: dayLabel(day), entries: [] });
    days.get(day).entries.push(e);
  }
  return [...days.values()];
});

function dayLabel(day) {
  if (day === today) return "Today";
  const y = new Date();
  y.setDate(y.getDate() - 1);
  if (day === ymd(y)) return "Yesterday";
  return new Date(`${day}T00:00:00`).toLocaleDateString("en-PH", { weekday: "long", month: "long", day: "numeric", year: "numeric" });
}
const initials = (name) => String(name).split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase();
/** "Timed in" reads as "timed in" after the person's name; keep proper names after a colon as they are */
const lower = (text) => text.charAt(0).toLowerCase() + text.slice(1);

async function load() {
  error.value = "";
  loading.value = true;
  try {
    await list.load();
  } catch (e) {
    if (e?.response?.data?.code === "staff_setting_up") settingUp.value = e.response.data.message;
    else error.value = apiMessage(e, "Couldn't load the activity log.");
  } finally {
    loading.value = false;
  }
}
function clear() {
  who.value = "";
  area.value = "";
  from.value = "";
  to.value = "";
  load();
}

onMounted(() => load());
</script>

<style scoped>
.ac-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 8px;
}
.ac-count {
  margin: 0;
}
.ac-sk-inline {
  display: inline-block;
  width: 140px;
}
.ac-filters {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.ac-filters .sp-select {
  width: auto;
  min-width: 140px;
}
.ac-date {
  width: 150px;
}
.ac-days {
  display: flex;
  flex-direction: column;
  gap: 18px;
  margin-top: 10px;
}
.ac-day h3 {
  margin: 0 0 4px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--v-faint);
}
.ac-list {
  margin: 0;
  padding: 0;
  list-style: none;
}
.ac-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 0;
}
.ac-row + .ac-row {
  border-top: 1px solid var(--v-border);
}
.ac-row .grow,
.ac-main {
  flex: 1;
  min-width: 0;
}
.ac-main p {
  margin: 0;
  font-size: 13.5px;
  color: var(--v-text);
  overflow-wrap: anywhere;
}
.ac-main b {
  font-weight: 600;
  color: var(--v-text);
}
.ac-main small {
  display: block;
  margin-top: 4px;
}
.ac-row time {
  flex: none;
  font-size: 12px;
  color: var(--v-faint);
  font-variant-numeric: tabular-nums;
}
.ac-pages {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid var(--v-border);
}
</style>
