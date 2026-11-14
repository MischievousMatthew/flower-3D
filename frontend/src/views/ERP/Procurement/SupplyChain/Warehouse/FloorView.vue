<template>
  <HrPage title="Warehouse floor" subtitle="Every batch on the floor, how fresh it is, and what to use or clear first.">
    <template #actions>
      <router-link :to="{ name: 'BatchesToReceive' }" class="sp-btn sp-btn-primary"><span v-html="ICONS.plus"></span>Receive batch</router-link>
      <button type="button" class="sp-icon-btn sp-refresh" :disabled="loading" aria-label="Refresh" title="Refresh" @click="fetchFloor"><span v-html="ICONS.restore"></span></button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="fetchFloor">Try again</button>
    </div>

    <!-- Summary: tap a condition to show only those batches -->
    <section class="sp-stats fl-stats" aria-label="Freshness summary">
      <template v-if="!loaded">
        <div v-for="n in 5" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <component
          :is="s.filter ? 'button' : 'div'"
          v-for="s in SUMMARY"
          :key="s.key"
          :type="s.filter ? 'button' : undefined"
          class="sp-stat fl-stat"
          :class="{ on: s.filter && conditionFilter === s.key, alert: s.key === 'expiring_today' && summary.expiring_today > 0 }"
          :aria-pressed="s.filter ? conditionFilter === s.key : undefined"
          @click="s.filter && toggleConditionFilter(s.key)"
        >
          <span class="sp-stat-icon" :class="`tone-${s.tone}`" v-html="ICONS[s.icon]"></span>
          <div>
            <strong>{{ (summary[s.key] ?? 0).toLocaleString("en-PH") }}</strong>
            <span>{{ s.label }}</span>
          </div>
        </component>
      </template>
    </section>

    <section class="sp-card fl-panel">
      <div class="sp-toolbar fl-toolbar">
        <label class="sp-search fl-search">
          <span v-html="ICONS.search"></span>
          <input v-model="search" class="sp-input" type="search" placeholder="Product, SKU or batch number" aria-label="Search batches" @input="debouncedFetch" />
        </label>
        <select v-model="locationFilter" class="sp-select" aria-label="Zone" @change="fetchFloor">
          <option value="">All zones</option>
          <option v-for="l in locations" :key="l.id" :value="String(l.id)">{{ l.name }}</option>
        </select>
        <select v-model="conditionFilter" class="sp-select" aria-label="Condition" @change="fetchFloor">
          <option value="">Any condition</option>
          <option v-for="c in CONDITIONS" :key="c" :value="c">{{ conditionMeta(c).label }}</option>
        </select>
        <button v-if="hasFilters" type="button" class="sp-btn sp-btn-ghost" @click="clearFilters"><span v-html="ICONS.close"></span>Clear</button>
        <span v-if="loaded && batches.length" class="fl-count">{{ batches.length }} {{ batches.length === 1 ? "batch" : "batches" }}</span>
      </div>

      <!-- Loading -->
      <div v-if="!loaded" class="fl-grid" aria-hidden="true">
        <div v-for="n in 6" :key="n" class="fl-card sk">
          <div class="bc-skeleton fl-media"></div>
          <div class="fl-body"><div class="bc-skeleton sp-sk-line w70"></div><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line"></div></div>
        </div>
      </div>

      <!-- Empty -->
      <div v-else-if="!batches.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.box"></span>
        <h3>{{ hasFilters ? "No batches match" : "The floor is empty" }}</h3>
        <p>{{ hasFilters ? "Try another search, zone or condition." : "Batches show up here once a supplier delivery is received." }}</p>
        <button v-if="hasFilters" type="button" class="sp-btn sp-btn-ghost" @click="clearFilters">Clear filters</button>
        <router-link v-else :to="{ name: 'BatchesToReceive' }" class="sp-btn sp-btn-primary"><span v-html="ICONS.plus"></span>Receive a batch</router-link>
      </div>

      <!-- Batches -->
      <div v-else class="fl-grid" :class="{ dim: loading }">
        <article
          v-for="batch in batches"
          :key="batch.id"
          class="fl-card"
          :class="`c-${toneOf(batch.computed_condition)}`"
          role="button"
          tabindex="0"
          :aria-label="`${batch.product?.product_name ?? 'Batch'} ${batch.batch_number}, ${conditionMeta(batch.computed_condition).label}, ${daysRemainingLabel(batch.days_remaining)}`"
          @click="openDetail(batch)"
          @keydown.enter.prevent="openDetail(batch)"
          @keydown.space.prevent="openDetail(batch)"
        >
          <div class="fl-media">
            <img v-if="batch.product?.image_url" :src="batch.product.image_url" :alt="batch.product?.product_name ?? ''" loading="lazy" decoding="async" />
            <span v-else class="fl-media-blank" v-html="ICONS.flower"></span>
            <span class="sp-chip fl-cond" :class="toneOf(batch.computed_condition)"><i></i>{{ conditionMeta(batch.computed_condition).label }}</span>
            <span class="fl-qty"><b>{{ Number(batch.qty_remaining ?? 0).toLocaleString("en-PH") }}</b> left</span>
          </div>

          <div class="fl-body">
            <div class="fl-title">
              <h3>{{ batch.product?.product_name ?? "Unnamed product" }}</h3>
              <code>{{ batch.batch_number }}</code>
            </div>

            <div class="fl-fresh">
              <div class="fl-fresh-row">
                <span class="fl-days" :class="daysRemainingClass(batch.days_remaining)"><span v-html="ICONS.clock"></span>{{ daysRemainingLabel(batch.days_remaining) }}</span>
                <span v-if="hasMeter(batch)" class="fl-pct">{{ freshnessPercent(batch) }}%</span>
              </div>
              <div v-if="hasMeter(batch)" class="fl-meter" role="img" :aria-label="`${freshnessPercent(batch)}% of its fresh life left`">
                <span :style="{ width: `${freshnessPercent(batch)}%` }"></span>
              </div>
            </div>

            <dl class="fl-facts">
              <div><dt>Zone</dt><dd>{{ batch.location?.name ?? "—" }}</dd></div>
              <div><dt>Spot</dt><dd>{{ batch.storage_location || "Not set" }}</dd></div>
              <div><dt>Received</dt><dd>{{ formatDate(batch.received_date) }}</dd></div>
            </dl>
          </div>
        </article>
      </div>
    </section>

    <!-- Batch details -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="activeDrawer" class="sp-backdrop" @click="activeDrawer = null"></div></Transition>
      <Transition name="fl-slide">
        <aside v-if="activeDrawer" class="fl-drawer" role="dialog" aria-modal="true" aria-label="Batch details">
          <BatchDetailDrawer :batch="activeDrawer" :locations="locations" @close="activeDrawer = null" @updated="onBatchUpdated" />
        </aside>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import HrPage from "../../../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import {
  warehouseBatchService,
  warehouseLocationService,
  conditionMeta,
  daysRemainingLabel,
  daysRemainingClass,
} from "../../../../../services/warehouseBatchService";
import BatchDetailDrawer from "./BatchDetailDrawer.vue";

const CONDITIONS = ["fresh", "aging", "wilting", "spoiled"];
// The colour of each condition, from the theme tokens (light and dark)
const TONES = { fresh: "green", aging: "amber", wilting: "pink", spoiled: "red" };
const toneOf = (c) => TONES[c] ?? "slate";
const SUMMARY = [
  { key: "fresh", label: "Fresh", icon: "flower", tone: "green", filter: true },
  { key: "aging", label: "Aging", icon: "clock", tone: "amber", filter: true },
  { key: "wilting", label: "Wilting", icon: "activity", tone: "pink", filter: true },
  { key: "spoiled", label: "Spoiled", icon: "trash", tone: "red", filter: true },
  { key: "expiring_today", label: "Expire today", icon: "alert", tone: "violet", filter: false },
];

const route = useRoute();
const batches = ref([]);
const summary = ref({});
const locations = ref([]);
const loading = ref(false);
const loaded = ref(false);
const error = ref("");
const search = ref("");
const locationFilter = ref("");
const conditionFilter = ref("");
const activeDrawer = ref(null);

const hasFilters = computed(() => !!(search.value || locationFilter.value || conditionFilter.value));

function toggleConditionFilter(key) {
  conditionFilter.value = conditionFilter.value === key ? "" : key;
  fetchFloor();
}

function clearFilters() {
  search.value = "";
  locationFilter.value = "";
  conditionFilter.value = "";
  fetchFloor();
}

let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(fetchFloor, 400);
}

async function fetchFloor() {
  loading.value = true;
  error.value = "";
  try {
    const params = {};
    if (search.value) params.search = search.value;
    if (locationFilter.value) params.location_id = locationFilter.value;
    if (conditionFilter.value) params.condition = conditionFilter.value;
    const res = await warehouseBatchService.floorView(params);
    batches.value = res.data?.batches ?? res.batches ?? [];
    summary.value = res.data?.summary ?? res.summary ?? {};
  } catch (e) {
    error.value = "Couldn't load the warehouse floor. Check your connection and try again.";
  } finally {
    loading.value = false;
    loaded.value = true;
  }
}

async function fetchLocations() {
  try {
    const res = await warehouseLocationService.list();
    locations.value = res.data?.data ?? res.data ?? res ?? [];
  } catch {
    locations.value = [];
  }
}

function openDetail(batch) {
  activeDrawer.value = batch;
}

function onBatchUpdated(updated) {
  const idx = batches.value.findIndex((b) => b.id === updated.id);
  if (idx !== -1) batches.value[idx] = updated;
  activeDrawer.value = updated;
  fetchFloor();
}

const hasMeter = (batch) => batch.days_remaining !== null && batch.days_remaining !== undefined && !!batch.freshness_days;
function freshnessPercent(batch) {
  if (!hasMeter(batch)) return 0;
  return Math.max(0, Math.min(100, Math.round((batch.days_remaining / batch.freshness_days) * 100)));
}

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString("en-PH", { month: "short", day: "numeric" }) : "—";
}

onMounted(() => {
  if (route.query.location_id) locationFilter.value = String(route.query.location_id);
  if (route.query.condition) conditionFilter.value = String(route.query.condition);
  fetchFloor();
  fetchLocations();
});
</script>

<style scoped>
/* Summary: five tiles */
.fl-stats {
  grid-template-columns: repeat(5, minmax(0, 1fr));
}
.fl-stat.alert {
  border-color: color-mix(in srgb, var(--v-purple-text) 45%, transparent);
}
.fl-stat.alert .sp-stat-icon {
  animation: fl-pulse 2s ease-in-out infinite;
}
@keyframes fl-pulse {
  50% { box-shadow: 0 0 0 6px color-mix(in srgb, var(--v-purple-text) 15%, transparent); }
}

/* Toolbar */
.fl-toolbar .sp-select {
  width: auto;
  min-width: 140px;
}
.fl-search {
  flex: 1 1 260px;
}
.fl-count {
  margin-left: auto;
  font-size: 12.5px;
  color: var(--v-muted);
  white-space: nowrap;
}

/* Batch cards */
.fl-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 14px;
  transition: opacity 0.15s;
}
.fl-grid.dim {
  opacity: 0.6;
}
.fl-card {
  --fl-tone: var(--v-muted);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--v-border);
  border-radius: 16px;
  background: var(--v-surface);
  cursor: pointer;
  transition: border-color 0.18s, box-shadow 0.18s, transform 0.18s;
}
.fl-card.c-green { --fl-tone: var(--v-green-text); }
.fl-card.c-amber { --fl-tone: var(--v-amber-text); }
.fl-card.c-pink { --fl-tone: var(--v-pink-text); }
.fl-card.c-red { --fl-tone: var(--v-red-text); }
.fl-card:not(.sk):hover {
  border-color: color-mix(in srgb, var(--fl-tone) 45%, var(--v-border));
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.12);
  transform: translateY(-2px);
}
.fl-card:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.fl-card.sk {
  cursor: default;
}

.fl-media {
  position: relative;
  height: 132px;
  background: var(--v-subtle);
}
.fl-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.fl-media::after {
  /* Keeps the chips readable over any photo */
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.28) 0%, transparent 40%, transparent 60%, rgba(0, 0, 0, 0.45) 100%);
  pointer-events: none;
}
.fl-card.sk .fl-media::after {
  display: none;
}
.fl-media-blank {
  display: grid;
  place-items: center;
  height: 100%;
  color: var(--v-faint);
}
.fl-media-blank :deep(svg) {
  width: 34px;
  height: 34px;
}
.fl-cond {
  position: absolute;
  z-index: 1;
  top: 10px;
  left: 10px;
  gap: 6px;
  font-weight: 600;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
}
.fl-cond i {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
}
.fl-qty {
  position: absolute;
  z-index: 1;
  right: 10px;
  bottom: 8px;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.88);
}
.fl-qty b {
  font-size: 20px;
  font-weight: 700;
  color: #fff;
}

.fl-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 12px;
  padding: 14px;
}
.fl-title h3 {
  margin: 0;
  overflow: hidden;
  font-size: 14.5px;
  font-weight: 600;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fl-title code {
  display: block;
  margin-top: 3px;
  overflow: hidden;
  font-size: 11.5px;
  color: var(--v-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.fl-fresh {
  display: grid;
  gap: 7px;
}
.fl-fresh-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.fl-days {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.fl-days :deep(svg) {
  width: 15px;
  height: 15px;
}
.fl-days.ok { color: var(--v-green-text); }
.fl-days.warn { color: var(--v-amber-text); }
.fl-days.warn-red,
.fl-days.danger { color: var(--v-red-text); }
.fl-pct {
  font-size: 12px;
  font-weight: 600;
  color: var(--v-muted);
}
.fl-meter {
  height: 6px;
  overflow: hidden;
  border-radius: 99px;
  background: var(--v-subtle-2);
}
.fl-meter span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--fl-tone);
  transition: width 0.4s ease;
}

.fl-facts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin: auto 0 0;
  padding-top: 12px;
  border-top: 1px solid var(--v-border);
}
.fl-facts div {
  min-width: 0;
}
.fl-facts dt {
  font-size: 11px;
  color: var(--v-muted);
}
.fl-facts dd {
  margin: 2px 0 0;
  overflow: hidden;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Details drawer */
.fl-drawer {
  position: fixed;
  z-index: 1201;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(480px, 100vw);
  overflow-y: auto;
  background: var(--v-surface);
  box-shadow: -12px 0 40px rgba(0, 0, 0, 0.22);
}
.fl-slide-enter-active,
.fl-slide-leave-active {
  transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.25s;
}
.fl-slide-enter-from,
.fl-slide-leave-to {
  opacity: 0;
  transform: translateX(40px);
}

@media (max-width: 1100px) {
  .fl-stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 640px) {
  .fl-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .fl-toolbar .sp-select {
    flex: 1 1 140px;
  }
  .fl-count {
    width: 100%;
    margin-left: 0;
  }
}
@media (prefers-reduced-motion: reduce) {
  .fl-card,
  .fl-meter span,
  .fl-slide-enter-active,
  .fl-slide-leave-active {
    transition: none;
  }
  .fl-card:not(.sk):hover {
    transform: none;
  }
  .fl-stat.alert .sp-stat-icon {
    animation: none;
  }
}
</style>
