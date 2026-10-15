<template>
  <ShopLayout title="Promotions" subtitle="Seasonal offers across several products, and sale prices on single products. Prices change on their own when an offer starts and ends.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-primary" @click="openEditor()"><span v-html="ICONS.plus"></span>New promotion</button>
    </template>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="loadAll">Try again</button>
    </div>

    <section class="sp-stats" aria-label="Promotion summary">
      <template v-if="!promotions || !products">
        <div v-for="n in 4" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <div class="sp-stat"><span class="sp-stat-icon tone-green" v-html="ICONS.play"></span><div><strong>{{ byState.running }}</strong><span>Running now</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon tone-blue" v-html="ICONS.calendar"></span><div><strong>{{ byState.scheduled }}</strong><span>Scheduled</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon tone-violet" v-html="ICONS.percent"></span><div><strong>{{ onSale.length }}</strong><span>Products on sale now</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon tone-slate" v-html="ICONS.history"></span><div><strong>{{ byState.ended + byState.paused }}</strong><span>Paused or ended</span></div></div>
      </template>
    </section>

    <div class="promo-layout">
      <!-- Seasonal offers -->
      <section class="sp-card" aria-label="Seasonal offers">
        <div class="sp-card-head">
          <div>
            <h2>Seasonal offers</h2>
            <p>A percentage or amount off a category, an occasion, chosen products or everything.</p>
          </div>
        </div>

        <div v-if="!promotions" class="promo-list">
          <div v-for="n in 3" :key="n" class="promo"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w70"></div><div class="bc-skeleton sp-sk-line w30"></div></div>
        </div>
        <div v-else-if="!promotions.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.sparkle"></span>
          <h3>No promotions yet</h3>
          <p>Plan ahead for Valentine's, Mother's Day or Christmas. Set the dates and the prices change by themselves.</p>
          <div class="presets">
            <button v-for="p in PRESETS" :key="p.name" type="button" class="sp-pick" @click="openEditor(null, p)">{{ p.name }}</button>
          </div>
        </div>
        <ul v-else class="promo-list">
          <li v-for="p in promotions" :key="p.id" class="promo" :class="p.state">
            <div class="promo-top">
              <span class="promo-off">{{ offText(p) }}</span>
              <div class="promo-title">
                <strong>{{ p.name }}</strong>
                <span>{{ appliesText(p) }}</span>
              </div>
              <span class="sp-chip" :class="STATE[p.state].tone">{{ STATE[p.state].label }}</span>
            </div>
            <div class="promo-dates">
              <span v-html="ICONS.calendar"></span>
              {{ range(p) }}
              <template v-if="p.state === 'running'"> · ends {{ relative(p.ends_at) }}</template>
              <template v-else-if="p.state === 'scheduled'"> · starts {{ relative(p.starts_at) }}</template>
            </div>
            <div v-if="p.state === 'running'" class="promo-progress"><span :style="{ width: `${progress(p)}%` }"></span></div>
            <p class="promo-products">
              <template v-if="p.product_count">
                {{ p.product_count }} live {{ p.product_count === 1 ? "product" : "products" }}: {{ p.product_names.join(", ") }}<template v-if="p.product_count > p.product_names.length">…</template>
              </template>
              <template v-else>No live products match this yet.</template>
              <span v-if="p.skipped" class="warn"> · {{ p.skipped }} cost less than the discount and stay full price</span>
            </p>
            <div class="promo-actions">
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="openEditor(p)"><span v-html="ICONS.edit"></span>Edit</button>
              <button v-if="p.state !== 'ended'" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busyId === p.id" @click="toggle(p)">
                <span v-html="p.is_active ? ICONS.pause : ICONS.play"></span>{{ p.is_active ? "Pause" : "Resume" }}
              </button>
              <button type="button" class="sp-btn sp-btn-danger sp-btn-sm" :disabled="busyId === p.id" @click="confirming = p"><span v-html="ICONS.trash"></span>Delete</button>
            </div>
          </li>
        </ul>
      </section>

      <!-- Single-product sales -->
      <section class="sp-card" aria-label="Product sale prices">
        <div class="sp-card-head">
          <div>
            <h2>Product sale prices</h2>
            <p>One product at a time, optionally with dates.</p>
          </div>
          <select class="sp-select add-sale" value="" aria-label="Put a product on sale" :disabled="!products" @change="pickSale">
            <option value="">+ Put a product on sale</option>
            <option v-for="p in notOnSale" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <ul v-if="!products" class="sale-list">
          <li v-for="n in 3" :key="n"><div class="bc-skeleton sp-thumb"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <p v-else-if="!withSale.length" class="sp-hint none">No product has its own sale price. Pick one above.</p>
        <ul v-else class="sale-list">
          <li v-for="p in withSale" :key="p.id">
            <img v-if="p.image" :src="p.image" :alt="p.name" class="sp-thumb" loading="lazy" />
            <span v-else class="sp-thumb blank" v-html="ICONS.image"></span>
            <div class="sale-main">
              <strong>{{ p.name }}</strong>
              <span>
                <b>{{ money(p.own_discount) }}</b> <s>{{ money(p.price) }}</s> · {{ saleWindow(p) }}
              </span>
            </div>
            <span class="sp-chip" :class="saleState(p).tone">{{ saleState(p).label }}</span>
            <button type="button" class="sp-icon-btn" :aria-label="`Edit sale for ${p.name}`" @click="saleFor = p"><span v-html="ICONS.edit"></span></button>
          </li>
        </ul>
      </section>
    </div>

    <SalePriceModal :product="saleFor" @close="saleFor = null" @saved="replace" />

    <!-- Promotion editor -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="editor" class="sp-backdrop" @click="editor = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="editor" class="sp-modal-wrap">
          <form class="sp-modal wide" role="dialog" aria-modal="true" aria-labelledby="promo-title" @submit.prevent="savePromotion">
            <div class="sp-modal-head">
              <div>
                <h3 id="promo-title">{{ editor.id ? "Edit promotion" : "New promotion" }}</h3>
                <p>Customers see the lower price, with the regular price crossed out.</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="editor = null"><span v-html="ICONS.close"></span></button>
            </div>

            <div v-if="!editor.id" class="presets">
              <button v-for="p in PRESETS" :key="p.name" type="button" class="sp-pick" @click="applyPreset(p)">{{ p.name }}</button>
            </div>

            <div class="sp-grid-2">
              <label class="sp-field sp-span-all">
                <span>Name</span>
                <input v-model.trim="editor.name" class="sp-input" maxlength="120" placeholder="e.g. Valentine's Week" required />
              </label>
              <div class="sp-field">
                <span class="sp-label">Discount</span>
                <div class="off-input">
                  <div class="sp-seg">
                    <button type="button" :class="{ on: editor.discount_type === 'percent' }" @click="editor.discount_type = 'percent'">%</button>
                    <button type="button" :class="{ on: editor.discount_type === 'fixed' }" @click="editor.discount_type = 'fixed'">₱</button>
                  </div>
                  <input v-model.number="editor.discount_value" type="number" min="1" :max="editor.discount_type === 'percent' ? 90 : undefined" step="any" class="sp-input" required :aria-label="editor.discount_type === 'percent' ? 'Percent off' : 'Amount off'" />
                </div>
              </div>
              <div class="sp-field">
                <span class="sp-label">Applies to</span>
                <select v-model="editor.applies_to" class="sp-select" @change="editor.targets = []">
                  <option value="all">Every product</option>
                  <option value="category">Categories</option>
                  <option value="occasion">Occasions</option>
                  <option value="products">Chosen products</option>
                </select>
              </div>
              <div v-if="editor.applies_to !== 'all'" class="sp-field sp-span-all">
                <span class="sp-label">Choose {{ editor.applies_to === "products" ? "products" : editor.applies_to === "category" ? "categories" : "occasions" }}</span>
                <div class="target-picks">
                  <button
                    v-for="t in targetOptions"
                    :key="t.value"
                    type="button"
                    class="sp-pick"
                    :class="{ on: editor.targets.includes(String(t.value)) }"
                    @click="toggleTarget(t.value)"
                  >{{ t.label }}</button>
                </div>
              </div>
              <label class="sp-field">
                <span>Starts</span>
                <input v-model="editor.starts_at" type="datetime-local" class="sp-input" required />
              </label>
              <label class="sp-field">
                <span>Ends</span>
                <input v-model="editor.ends_at" type="datetime-local" class="sp-input" :min="editor.starts_at" required />
              </label>
            </div>

            <div class="promo-preview">
              <strong>{{ preview.count }} live {{ preview.count === 1 ? "product" : "products" }}</strong> will be discounted
              <template v-if="preview.example"> · e.g. {{ preview.example.name }}: <s>{{ money(preview.example.price) }}</s> <b>{{ money(preview.example.after) }}</b></template>
            </div>
            <p v-if="editorError" class="sp-error">{{ editorError }}</p>

            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="editor = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="saving">{{ saving ? "Saving…" : editor.id ? "Save" : "Create promotion" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete confirmation -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="confirming" class="sp-backdrop" @click="confirming = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="confirming" class="sp-modal-wrap">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="pdel-title">
            <div class="sp-modal-head">
              <div>
                <h3 id="pdel-title">Delete “{{ confirming.name }}”?</h3>
                <p>Prices go back to normal straight away. Orders already placed keep the price they were bought at.</p>
              </div>
            </div>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="confirming = null">Cancel</button>
              <button type="button" class="sp-btn sp-btn-danger" :disabled="busyId === confirming.id" @click="remove(confirming)">Delete</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import SalePriceModal from "../../../components/vendor/shop/SalePriceModal.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage, formatDate, money, useVendorCatalog } from "../../../components/vendor/shop/useVendorCatalog";
import "../../../components/vendor/shop/shop.css";

const { products, error: catalogError, load, replace, taxonomies, categoryLabel } = useVendorCatalog();

const STATE = {
  running: { label: "Running", tone: "green" },
  scheduled: { label: "Scheduled", tone: "blue" },
  paused: { label: "Paused", tone: "amber" },
  ended: { label: "Ended", tone: "slate" },
};

// Upcoming dates for the common flower seasons (next occurrence from today)
function nextDate(month, day, lengthDays) {
  const now = new Date();
  let end = new Date(now.getFullYear(), month - 1, day, 23, 59);
  if (end < now) end = new Date(now.getFullYear() + 1, month - 1, day, 23, 59);
  const start = new Date(end);
  start.setDate(start.getDate() - lengthDays + 1);
  start.setHours(0, 0, 0, 0);
  return [start, end];
}
function secondSundayOfMay() {
  const now = new Date();
  for (const year of [now.getFullYear(), now.getFullYear() + 1]) {
    const d = new Date(year, 4, 1);
    d.setDate(1 + ((7 - d.getDay()) % 7) + 7);
    if (d >= now) return [d.getMonth() + 1, d.getDate()];
  }
  return [5, 10];
}
const PRESETS = [
  { name: "Valentine's Week", occasion: "Valentine", dates: () => nextDate(2, 14, 7), value: 15 },
  { name: "Mother's Day", occasion: "Mother's Day", dates: () => nextDate(...secondSundayOfMay(), 5), value: 10 },
  { name: "All Souls' Day", occasion: "Sympathy", dates: () => nextDate(11, 2, 4), value: 10 },
  { name: "Christmas Sale", occasion: "Christmas", dates: () => nextDate(12, 25, 10), value: 15 },
  { name: "Weekend Flash Sale", dates: () => { const s = new Date(); s.setDate(s.getDate() + ((6 - s.getDay() + 7) % 7)); s.setHours(0, 0, 0, 0); const e = new Date(s); e.setDate(e.getDate() + 1); e.setHours(23, 59); return [s, e]; }, value: 20 },
];

const promotions = ref(null);
const promoError = ref("");
const busyId = ref(null);
const confirming = ref(null);
const saleFor = ref(null);
const loadError = computed(() => promoError.value || (!products.value ? catalogError.value : ""));

async function loadPromotions() {
  promoError.value = "";
  try {
    const { data } = await api.get("vendor/promotions");
    promotions.value = data.data;
  } catch (e) {
    promoError.value = apiMessage(e, "Couldn't load your promotions.");
    promotions.value ??= [];
  }
}
function loadAll() {
  load();
  loadPromotions();
}

const byState = computed(() => {
  const counts = { running: 0, scheduled: 0, paused: 0, ended: 0 };
  (promotions.value ?? []).forEach((p) => counts[p.state]++);
  return counts;
});
const onSale = computed(() => (products.value ?? []).filter((p) => p.status === "active" && p.sale_price));
const withSale = computed(() => (products.value ?? []).filter((p) => p.own_discount !== null));
const notOnSale = computed(() => (products.value ?? []).filter((p) => p.own_discount === null));

const offText = (p) => (p.discount_type === "percent" ? `${p.discount_value}% off` : `${money(p.discount_value)} off`);
function appliesText(p) {
  if (p.applies_to === "all") return "Every product";
  if (p.applies_to === "category") return p.targets.map(categoryLabel).join(", ");
  if (p.applies_to === "occasion") return `Tagged ${p.targets.join(", ")}`;
  return `${p.targets.length} chosen ${p.targets.length === 1 ? "product" : "products"}`;
}
const range = (p) => `${formatDate(p.starts_at, { month: "short", day: "numeric" })} – ${formatDate(p.ends_at, { month: "short", day: "numeric", year: "numeric" })}`;
function relative(iso) {
  const ms = new Date(iso) - Date.now();
  const hours = Math.round(Math.abs(ms) / 3_600_000);
  const text = hours < 24 ? `${Math.max(1, hours)} h` : `${Math.round(hours / 24)} days`;
  return ms >= 0 ? `in ${text}` : `${text} ago`;
}
const progress = (p) => {
  const start = new Date(p.starts_at).getTime();
  const end = new Date(p.ends_at).getTime();
  return Math.min(100, Math.max(0, ((Date.now() - start) / (end - start)) * 100));
};

function saleWindow(p) {
  const opts = { month: "short", day: "numeric" };
  if (p.discount_starts_at && p.discount_ends_at) return `${formatDate(p.discount_starts_at, opts)} – ${formatDate(p.discount_ends_at, opts)}`;
  if (p.discount_ends_at) return `until ${formatDate(p.discount_ends_at, opts)}`;
  if (p.discount_starts_at) return `from ${formatDate(p.discount_starts_at, opts)}`;
  return "no end date";
}
function saleState(p) {
  const now = Date.now();
  if (p.discount_starts_at && new Date(p.discount_starts_at) > now) return { label: "Scheduled", tone: "blue" };
  if (p.discount_ends_at && new Date(p.discount_ends_at) < now) return { label: "Ended", tone: "slate" };
  return p.status === "active" ? { label: "On sale", tone: "green" } : { label: "Not live", tone: "slate" };
}
function pickSale(event) {
  const id = Number(event.target.value);
  event.target.value = "";
  saleFor.value = (products.value ?? []).find((p) => p.id === id) ?? null;
}

async function toggle(p) {
  busyId.value = p.id;
  try {
    const { data } = await api.post(`vendor/promotions/${p.id}/toggle`);
    toast.success(data.message);
    await Promise.all([loadPromotions(), load()]);
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't update the promotion."));
  } finally {
    busyId.value = null;
  }
}
async function remove(p) {
  busyId.value = p.id;
  try {
    await api.delete(`vendor/promotions/${p.id}`);
    toast.success("Promotion deleted.");
    confirming.value = null;
    await Promise.all([loadPromotions(), load()]);
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't delete the promotion."));
  } finally {
    busyId.value = null;
  }
}

// Editor
const editor = ref(null);
const editorError = ref("");
const saving = ref(false);
const pad = (n) => String(n).padStart(2, "0");
const toLocal = (d) => {
  const x = new Date(d);
  return `${x.getFullYear()}-${pad(x.getMonth() + 1)}-${pad(x.getDate())}T${pad(x.getHours())}:${pad(x.getMinutes())}`;
};

function openEditor(p = null, preset = null) {
  editorError.value = "";
  if (p) {
    editor.value = {
      id: p.id,
      name: p.name,
      discount_type: p.discount_type,
      discount_value: p.discount_value,
      applies_to: p.applies_to,
      targets: p.targets.map(String),
      starts_at: toLocal(p.starts_at),
      ends_at: toLocal(p.ends_at),
    };
    return;
  }
  const start = new Date();
  const end = new Date(start.getTime() + 7 * 86_400_000);
  editor.value = {
    id: null,
    name: "",
    discount_type: "percent",
    discount_value: 10,
    applies_to: "all",
    targets: [],
    starts_at: toLocal(start),
    ends_at: toLocal(end),
  };
  if (preset) applyPreset(preset);
}
function applyPreset(preset) {
  const [start, end] = preset.dates();
  const hasOccasion = preset.occasion && taxonomies.occasions.some((o) => o.value === preset.occasion);
  Object.assign(editor.value, {
    name: preset.name,
    discount_type: "percent",
    discount_value: preset.value,
    applies_to: hasOccasion ? "occasion" : "all",
    targets: hasOccasion ? [preset.occasion] : [],
    starts_at: toLocal(start),
    ends_at: toLocal(end),
  });
}

const targetOptions = computed(() => {
  if (!editor.value) return [];
  if (editor.value.applies_to === "category") return taxonomies.categories;
  if (editor.value.applies_to === "occasion") return taxonomies.occasions;
  return (products.value ?? []).map((p) => ({ value: String(p.id), label: p.name }));
});
function toggleTarget(value) {
  const v = String(value);
  const t = editor.value.targets;
  const i = t.indexOf(v);
  if (i >= 0) t.splice(i, 1);
  else t.push(v);
}

const preview = computed(() => {
  const e = editor.value;
  if (!e) return { count: 0 };
  const covers = (p) =>
    e.applies_to === "all" ||
    (e.applies_to === "category" && e.targets.includes(p.category)) ||
    (e.applies_to === "occasion" && p.occasion_tags.some((t) => e.targets.includes(t))) ||
    (e.applies_to === "products" && e.targets.includes(String(p.id)));
  const after = (price) => {
    const v = Number(e.discount_value) || 0;
    const x = Math.round((e.discount_type === "percent" ? price * (1 - Math.min(v, 100) / 100) : price - v) * 100) / 100;
    return x > 0 && x < price ? x : null;
  };
  const hits = (products.value ?? []).filter((p) => p.status === "active" && covers(p) && after(p.price) !== null);
  const first = hits[0];
  return { count: hits.length, example: first ? { name: first.name, price: first.price, after: after(first.price) } : null };
});

async function savePromotion() {
  if (saving.value) return;
  editorError.value = "";
  const e = editor.value;
  if (new Date(e.ends_at) <= new Date(e.starts_at)) {
    editorError.value = "The promotion must end after it starts.";
    return;
  }
  if (e.applies_to !== "all" && !e.targets.length) {
    editorError.value = "Choose at least one to apply it to.";
    return;
  }
  saving.value = true;
  try {
    const body = {
      name: e.name,
      discount_type: e.discount_type,
      discount_value: e.discount_value,
      applies_to: e.applies_to,
      targets: e.applies_to === "all" ? [] : e.targets,
      starts_at: new Date(e.starts_at).toISOString(),
      ends_at: new Date(e.ends_at).toISOString(),
    };
    const { data } = e.id ? await api.put(`vendor/promotions/${e.id}`, body) : await api.post("vendor/promotions", body);
    toast.success(data.message);
    editor.value = null;
    await Promise.all([loadPromotions(), load()]);
  } catch (err) {
    editorError.value = apiMessage(err, "Couldn't save the promotion.");
  } finally {
    saving.value = false;
  }
}

onMounted(loadAll);
</script>

<style scoped>
.grow {
  flex: 1;
}
.promo-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
.presets,
.target-picks {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.presets {
  justify-content: center;
  margin: 8px 0 4px;
}
.sp-modal .presets {
  justify-content: flex-start;
  margin: 0 0 16px;
}
.target-picks {
  max-height: 160px;
  overflow-y: auto;
}
.promo-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 12px;
}
.promo {
  padding: 16px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
  background: var(--v-surface);
}
.promo.running {
  border-color: var(--v-green-soft);
  background: linear-gradient(135deg, var(--v-green-soft) 0%, var(--v-surface) 60%);
}
.promo.ended,
.promo.paused {
  background: var(--v-subtle);
}
.promo-top {
  display: flex;
  align-items: center;
  gap: 12px;
}
.promo-off {
  flex: none;
  padding: 8px 10px;
  border-radius: 12px;
  background: var(--v-pink-soft);
  color: var(--v-pink-text);
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
}
.promo.ended .promo-off,
.promo.paused .promo-off {
  background: var(--v-subtle);
  color: var(--v-muted);
}
.promo-title {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.promo-title strong {
  font-size: 14.5px;
  font-weight: 600;
  color: var(--v-text);
}
.promo-title span {
  font-size: 12px;
  color: var(--v-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.promo-dates {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.promo-dates :deep(svg) {
  width: 14px;
  height: 14px;
  color: var(--v-faint);
}
.promo-progress {
  height: 5px;
  margin-top: 8px;
  border-radius: 999px;
  background: var(--v-subtle-2);
  overflow: hidden;
}
.promo-progress span {
  display: block;
  height: 100%;
  background: var(--bc-accent);
}
.promo-products {
  margin: 10px 0 0;
  font-size: 12px;
  color: var(--v-muted);
}
.promo-products .warn {
  color: var(--v-amber-text);
}
.promo-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 12px;
}
.add-sale {
  width: auto;
  max-width: 220px;
  padding: 7px 10px;
  font-size: 12.5px;
}
.none {
  margin: 0;
  padding: 18px 0;
  text-align: center;
}
.sale-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.sale-list li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 2px;
  border-bottom: 1px solid var(--v-border);
}
.sale-list li:last-child {
  border-bottom: 0;
}
.sale-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.sale-main strong {
  font-size: 13px;
  font-weight: 500;
  color: var(--v-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sale-main span {
  font-size: 12px;
  color: var(--v-muted);
}
.sale-main b {
  color: var(--bc-accent-strong);
}
.sale-main s {
  color: var(--v-faint);
}
.off-input {
  display: flex;
  gap: 8px;
}
.off-input .sp-seg {
  flex: none;
}
.promo-preview {
  margin-top: 16px;
  padding: 12px 14px;
  border-radius: 13px;
  background: var(--bc-accent-soft);
  font-size: 13px;
  color: var(--v-text);
}
.promo-preview s {
  color: var(--v-faint);
}
.promo-preview b {
  color: var(--bc-accent-strong);
}
@media (max-width: 1100px) {
  .promo-layout {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
