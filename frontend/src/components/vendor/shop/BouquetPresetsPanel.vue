<template>
  <section id="cz-panel-presets" class="bp" role="tabpanel" aria-labelledby="cz-tab-presets">
    <div class="bp-head">
      <div>
        <h2>Occasions &amp; sizes</h2>
        <p>Customers start the 3D customizer by picking an occasion, then how many flowers. Set each occasion's base price, bouquet style and sizes, or add occasions of your own. Bigger bouquets get a bigger wrapper automatically.</p>
      </div>
      <div class="bp-actions">
        <button type="button" class="sp-btn sp-btn-ghost" :disabled="!presets || customCount >= MAX_CUSTOM" :title="customCount >= MAX_CUSTOM ? `Up to ${MAX_CUSTOM} occasions of your own` : ''" @click="addOccasion">+ Add an occasion</button>
      </div>
    </div>

    <p class="sp-hint bp-note">The base price is what a bouquet starts at. It includes the wrapper, ribbon and fillers. Flowers and the message card are added on top.</p>
    <p v-if="errors.presets" class="sp-banner error bp-banner">{{ errors.presets }}</p>
    <p v-if="loadError" class="sp-banner error bp-banner">{{ loadError }}</p>

    <!-- Loading: skeleton cards -->
    <div v-if="!presets && !loadError" class="bp-grid" aria-busy="true" aria-label="Loading occasions">
      <div v-for="n in 6" :key="n" class="sp-card bp-card">
        <div class="bc-skeleton sp-sk-line w50"></div>
        <div class="bc-skeleton sp-sk-line w70"></div>
        <div class="bc-skeleton sp-sk-line w30"></div>
      </div>
    </div>

    <div v-else-if="presets" class="bp-grid">
      <article v-for="p in presets" :key="p.key" :ref="(el) => (cards[p.key] = el)" :data-change="p.key" class="sp-card bp-card" :class="{ off: !p.enabled }">
        <header class="bp-card-head">
          <span class="bp-icon" v-html="OCCASION_ICONS[occasionIcon(p)] || OCCASION_ICONS.flower"></span>
          <div class="bp-title">
            <h3>{{ p.label || "New occasion" }}</h3>
            <small>{{ p.custom ? "Your occasion" : "BloomCraft occasion" }} · {{ styleLabel(p.design) }}</small>
          </div>
          <label class="bp-toggle" :title="p.enabled && enabledCount === 1 ? 'Keep at least one occasion on' : p.enabled ? 'Customers can pick this occasion' : 'Hidden from customers'">
            <span class="sp-switch"><input type="checkbox" :checked="p.enabled" :aria-label="`Offer ${p.label || 'this occasion'}`" @change="toggle(p, $event)" /><span></span></span>
          </label>
        </header>

        <!-- The shop's own occasion: name, icon and words -->
        <template v-if="p.custom">
          <label class="bp-field">
            <span class="bp-label">Name</span>
            <input v-model="p.label" class="sp-input" :class="{ invalid: errors[`${p.key}.label`] }" maxlength="40" placeholder="e.g. Debut, Mother's Day" />
            <small v-if="errors[`${p.key}.label`]" class="sp-error">{{ errors[`${p.key}.label`] }}</small>
          </label>
          <div class="bp-field">
            <span class="bp-label">Icon</span>
            <div class="bp-icons" role="radiogroup" :aria-label="`${p.label || 'Occasion'} icon`">
              <button
                v-for="key in OCCASION_ICON_KEYS"
                :key="key"
                type="button"
                role="radio"
                class="bp-icon-pick"
                :class="{ on: p.icon === key }"
                :aria-checked="p.icon === key"
                :aria-label="key"
                @click="p.icon = key"
                v-html="OCCASION_ICONS[key]"
              ></button>
            </div>
          </div>
          <label class="bp-field">
            <span class="bp-label">Description <small>(optional)</small></span>
            <input v-model="p.description" class="sp-input" maxlength="160" placeholder="Shown to customers under the name" />
          </label>
        </template>

        <div class="bp-row">
          <label class="bp-field">
            <span class="bp-label">Base price</span>
            <span class="bp-money" :class="{ invalid: errors[`${p.key}.base_price`] }">
              <span aria-hidden="true">₱</span>
              <input v-model="p.base_price" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" :aria-label="`${p.label || 'Occasion'} base price`" />
            </span>
          </label>
          <label class="bp-field">
            <span class="bp-label">Bouquet style</span>
            <select v-model="p.design" class="sp-select" :aria-label="`${p.label || 'Occasion'} bouquet style`" @change="fitParts(p)">
              <optgroup label="BloomCraft styles">
                <option v-for="(d, key) in DESIGNS" :key="key" :value="key">{{ d.label }}</option>
              </optgroup>
              <optgroup v-if="wrapModels.length" label="Your 3D wrappers">
                <option v-for="m in wrapModels" :key="m.id" :value="`model_${m.id}`">{{ m.name }}</option>
              </optgroup>
            </select>
            <button type="button" class="bp-upload-link" @click="emit('upload-wrapper')">
              {{ wrapModels.length ? "Add another 3D wrapper" : "Upload your own 3D wrapper" }}
            </button>
          </label>
        </div>
        <small v-if="errors[`${p.key}.base_price`] || errors[`${p.key}.design`]" class="sp-error">{{ errors[`${p.key}.base_price`] || errors[`${p.key}.design`] }}</small>

        <div class="bp-parts">
          <label class="bp-part" :class="{ na: !designParts(p.design).wrapper }">
            <span>
              <b>Customers choose the {{ p.design === "basket" ? "basket" : "wrapper" }} colour</b>
              <small>{{ wrapperHint(p) }}</small>
            </span>
            <span class="sp-switch"><input v-model="p.wrapper" type="checkbox" :disabled="!designParts(p.design).wrapper" :aria-label="`Customers choose the wrapper colour for ${p.label || 'this occasion'}`" /><span></span></span>
          </label>
          <label class="bp-part" :class="{ na: !designParts(p.design).ribbon }">
            <span>
              <b>Ribbon</b>
              <small>{{ !designParts(p.design).ribbon ? "This style has no ribbon." : p.ribbon ? "Customers pick a ribbon colour." : "No ribbon on this bouquet." }}</small>
            </span>
            <span class="sp-switch"><input v-model="p.ribbon" type="checkbox" :disabled="!designParts(p.design).ribbon" :aria-label="`Ribbon for ${p.label || 'this occasion'}`" /><span></span></span>
          </label>
        </div>

        <!-- What one bouquet of this occasion takes from stock -->
        <!-- Each size, and the stock one bouquet of that size takes: a bigger bouquet
             uses more wrapper paper and more of each filler -->
        <div class="bp-sizes" :aria-label="`${p.label} sizes`">
          <span class="bp-label">Sizes and the stock each uses</span>
          <div class="bp-size-table" role="table" :aria-label="`${p.label || 'Occasion'} sizes`">
            <div class="bp-size-row bp-size-head" role="row">
              <span role="columnheader">Size</span>
              <span role="columnheader" :class="{ na: !p.wrapper }">Wrapper sheets <small>per layer</small></span>
              <span role="columnheader">Filler <small>each placed</small></span>
              <span role="columnheader" class="bc-sr-only">Remove</span>
            </div>
            <div v-for="(size, i) in p.sizes" :key="size" class="bp-size-row" role="row">
              <span role="cell" class="bp-size-name"><b>{{ size }}</b> {{ size === 1 ? "flower" : "flowers" }} <small>{{ sizeName(p.sizes, i) }}</small></span>
              <span role="cell">
                <input
                  v-model.number="p.size_uses[size].wrapper"
                  class="sp-input"
                  type="number" min="1" :max="MAX_USE" step="1" inputmode="numeric"
                  :disabled="!p.wrapper"
                  :aria-label="`Wrapper sheets per layer for the ${size}-flower ${p.label || 'bouquet'}`"
                />
              </span>
              <span role="cell">
                <input
                  v-model.number="p.size_uses[size].filler"
                  class="sp-input"
                  type="number" min="1" :max="MAX_USE" step="1" inputmode="numeric"
                  :aria-label="`How much of a filler each placement uses in the ${size}-flower ${p.label || 'bouquet'}`"
                />
              </span>
              <span role="cell">
                <button
                  type="button"
                  class="bp-chip-x"
                  :disabled="p.sizes.length === 1"
                  :title="p.sizes.length === 1 ? 'Keep at least one size' : `Remove ${size}`"
                  :aria-label="`Remove the ${size}-flower size`"
                  @click="removeSize(p, size)"
                >×</button>
              </span>
            </div>
          </div>
          <small class="bp-uses-note" role="status">{{ usesNote(p) }}</small>
          <small v-if="errors[`${p.key}.size_uses`]" class="sp-error">{{ errors[`${p.key}.size_uses`] }}</small>

          <!-- The ribbon is one amount for every size -->
          <label v-if="p.ribbon" class="bp-ribbon-use">
            <span>Ribbon per bouquet <small>(any size)</small></span>
            <input v-model.number="p.uses.ribbon" class="sp-input" type="number" min="1" :max="MAX_USE" step="1" inputmode="numeric" />
          </label>
          <small v-if="errors[`${p.key}.uses`]" class="sp-error">{{ errors[`${p.key}.uses`] }}</small>

          <form v-if="p.sizes.length < limits.max_sizes" class="bp-add" @submit.prevent="addSize(p)">
            <input
              v-model.number="drafts[p.key]"
              type="number"
              min="1"
              :max="limits.max_size"
              step="1"
              class="sp-input"
              :placeholder="`1–${limits.max_size}`"
              :aria-label="`New ${p.label} size`"
            />
            <button type="submit" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="!drafts[p.key]">+ Add size</button>
          </form>
          <small v-else class="sp-hint">Up to {{ limits.max_sizes }} sizes per occasion.</small>
          <small v-if="errors[`${p.key}.sizes`]" class="sp-error">{{ errors[`${p.key}.sizes`] }}</small>
        </div>

        <button v-if="p.custom" type="button" class="bp-remove" @click="removeOccasion(p)">Remove this occasion</button>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from "vue";
import { toast } from "@/utils/snackbar";
import api from "../../../plugins/axios";
import { OCCASION_ICON_KEYS, designParts, occasionIcon, sizeName } from "../../../constants/bouquetPresets";
import { OCCASION_ICONS } from "../../../constants/occasionIcons";
import { DESIGNS } from "../../../utils/bouquetDesigns/index.js";
import { useUnsavedChanges } from "../../../composables/useUnsavedChanges";
import "./shop.css";

const props = defineProps({
  // The shop's own 3D wrappers (Shop → 3D Bouquets → 3D wrappers), offered as styles
  wrapModels: { type: Array, default: () => [] },
});
const emit = defineEmits(["count", "upload-wrapper"]);

/** A style's name: a BloomCraft design, or one of the shop's 3D wrappers */
function styleLabel(design) {
  if (DESIGNS[design]) return DESIGNS[design].label;
  const id = Number(String(design || "").replace("model_", ""));
  return props.wrapModels.find((m) => Number(m.id) === id)?.name || "Your 3D wrapper";
}

const MAX_CUSTOM = 10;
const EDITABLE = ["enabled", "sizes", "base_price", "design", "wrapper", "ribbon", "uses", "size_uses", "label", "icon", "description"];

// Stock one bouquet takes (1 to 50): the ribbon for the occasion; wrapper sheets
// per layer and filler per placement for each size
const MAX_USE = 50;
const usesOf = (uses) => ({ wrapper: Number(uses?.wrapper) || 1, filler: Number(uses?.filler) || 1, ribbon: Number(uses?.ribbon) || 1 });
/** Every size with its own wrapper sheets and filler (1 each for a size not set yet) */
function sizeUsesOf(sizes, saved, uses) {
  return Object.fromEntries(
    sizes.map((s) => [s, { wrapper: Number(saved?.[s]?.wrapper) || uses.wrapper || 1, filler: Number(saved?.[s]?.filler) || uses.filler || 1 }]),
  );
}
/** "Grand: a filler placed twice uses 6 (2 × 3)": worked out from the biggest size, as the shop types */
function usesNote(p) {
  const big = p.sizes[p.sizes.length - 1];
  const f = Number(p.size_uses?.[big]?.filler);
  if (!Number.isInteger(f) || f < 1) return "Type how much of a filler each placement uses, for every size.";
  const name = p.sizes.length > 1 ? `In the ${big}-flower bouquet, a` : "A";
  return `${name} filler placed twice uses ${2 * f} of it (2 × ${f}).`;
}

const presets = ref(null);
const saved = ref("[]");
const loadError = ref("");
const saving = ref(false);
const limits = reactive({ max_size: 36, max_sizes: 4 });
const drafts = reactive({});
const errors = reactive({});
const cards = {};

const snapshot = () => JSON.stringify(presets.value ?? []);
// One change per occasion card; a removed occasion points back at the list
const { markClean, dirty } = useUnsavedChanges({
  fields: () =>
    (presets.value ?? []).map((p) => ({
      key: p.key,
      label: p.label || "New occasion",
      value: EDITABLE.map((f) => (f === "base_price" ? Number(p.base_price || 0) : f === "uses" || f === "size_uses" ? JSON.stringify(p[f]) : p[f])),
      fallback: "#cz-panel-presets",
    })),
  save: () => save(),
  discard: () => discard(),
  saving,
});
const enabledCount = computed(() => presets.value?.filter((p) => p.enabled).length ?? 0);
const customCount = computed(() => presets.value?.filter((p) => p.custom).length ?? 0);

function wrapperHint(p) {
  if (!designParts(p.design).wrapper) return "A hand-tied bouquet has no wrapper.";
  if (p.wrapper) return "Customers pick from your wrappers.";
  return p.design === "basket" ? "The basket keeps its natural colour." : "The wrapper keeps the style's own colour.";
}

/** Switch off parts the chosen style's model doesn't have */
function fitParts(p) {
  const can = designParts(p.design);
  if (!can.wrapper) p.wrapper = false;
  if (!can.ribbon) p.ribbon = false;
}

function clearErrors() {
  Object.keys(errors).forEach((k) => delete errors[k]);
}

function setPresets(list) {
  presets.value = list.map((p) => {
    const uses = usesOf(p.uses);
    return { ...p, sizes: [...p.sizes], base_price: Number(p.base_price || 0) || "", uses, size_uses: sizeUsesOf(p.sizes, p.size_uses, uses) };
  });
  saved.value = snapshot();
  markClean();
  emit("count", enabledCount.value);
}

async function load() {
  loadError.value = "";
  try {
    const { data } = await api.get("vendor/bouquet-presets");
    Object.assign(limits, data.limits || {});
    setPresets(data.data || []);
  } catch (e) {
    loadError.value = e?.response?.data?.message || "Couldn't load your occasions. Refresh to try again.";
  }
}

function toggle(p, event) {
  if (p.enabled && enabledCount.value === 1) {
    event.target.checked = true;
    errors.presets = "Keep at least one occasion on, so customers can build a bouquet.";
    return;
  }
  delete errors.presets;
  p.enabled = event.target.checked;
}

function addSize(p) {
  delete errors[`${p.key}.sizes`];
  const n = Number(drafts[p.key]);
  if (!Number.isInteger(n) || n < 1 || n > limits.max_size) {
    errors[`${p.key}.sizes`] = `Use a whole number from 1 to ${limits.max_size}.`;
    return;
  }
  if (p.sizes.includes(n)) {
    errors[`${p.key}.sizes`] = `${n} is already one of the sizes.`;
    return;
  }
  p.sizes = [...p.sizes, n].sort((a, b) => a - b);
  // A new size starts at 1 sheet per layer and 1 per filler; the shop raises it for a big one
  p.size_uses = { ...p.size_uses, [n]: { wrapper: 1, filler: 1 } };
  drafts[p.key] = null;
}

function removeSize(p, size) {
  if (p.sizes.length === 1) return;
  p.sizes = p.sizes.filter((s) => s !== size);
  const { [size]: _removed, ...rest } = p.size_uses || {};
  p.size_uses = rest;
  delete errors[`${p.key}.sizes`];
}

async function addOccasion() {
  if (customCount.value >= MAX_CUSTOM) return;
  const key = `custom_${Math.random().toString(36).slice(2, 10).padEnd(8, "0")}`;
  presets.value.push({
    key, label: "", custom: true, icon: "flower", description: "",
    enabled: true, sizes: [6, 12], base_price: "", design: "classic", wrapper: true, ribbon: true,
    uses: usesOf(null),
    size_uses: sizeUsesOf([6, 12], null, usesOf(null)),
  });
  await nextTick();
  const card = cards[key];
  card?.scrollIntoView({ behavior: "smooth", block: "center" });
  card?.querySelector("input")?.focus({ preventScroll: true });
}

function removeOccasion(p) {
  if (p.enabled && enabledCount.value === 1) {
    errors.presets = "Keep at least one occasion on, so customers can build a bouquet.";
    return;
  }
  presets.value = presets.value.filter((x) => x.key !== p.key);
  Object.keys(errors).filter((k) => k.startsWith(`${p.key}.`)).forEach((k) => delete errors[k]);
}

function discard() {
  clearErrors();
  presets.value = JSON.parse(saved.value);
}

async function save() {
  if (saving.value || !dirty.value) return;
  clearErrors();
  const nameless = presets.value.find((p) => p.custom && !p.label.trim());
  if (nameless) {
    errors[`${nameless.key}.label`] = "Give the occasion a name.";
    cards[nameless.key]?.scrollIntoView({ behavior: "smooth", block: "center" });
    return;
  }
  saving.value = true;
  try {
    const body = Object.fromEntries(
      presets.value.map((p) => [
        p.key,
        Object.fromEntries(EDITABLE.filter((f) => p.custom || !["label", "icon", "description"].includes(f)).map((f) => [f, f === "base_price" ? Number(p.base_price || 0) : p[f]])),
      ]),
    );
    const { data } = await api.put("vendor/bouquet-presets", { presets: body });
    setPresets(data.data || presets.value);
    toast.success(data.message || "Occasions saved.");
  } catch (e) {
    const fieldErrors = e?.response?.data?.errors;
    if (fieldErrors) {
      for (const [field, list] of Object.entries(fieldErrors)) errors[field] = [].concat(list)[0];
      toast.error("Some occasions need fixing. Check the messages in red.");
    } else {
      toast.error(e?.response?.data?.message || "Couldn't save your occasions.");
    }
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.bp {
  display: grid;
  gap: 16px;
}
.bp-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}
.bp-head h2 {
  margin: 0 0 4px;
  font-size: 17px;
  color: var(--v-text);
}
.bp-head p {
  max-width: 640px;
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--v-muted);
}
.bp-actions {
  display: flex;
  gap: 8px;
}
.bp-banner {
  margin: 0;
}
.bp-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 14px;
}
.bp-card {
  display: grid;
  gap: 14px;
  align-content: start;
  transition: opacity 0.15s;
}
.bp-card.off {
  opacity: 0.6;
}
.bp-card-head {
  display: flex;
  align-items: center;
  gap: 12px;
}
.bp-icon {
  display: grid;
  place-items: center;
  flex: none;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
}
.bp-icon :deep(svg) {
  width: 20px;
  height: 20px;
}
.bp-title {
  flex: 1;
  min-width: 0;
}
.bp-title h3 {
  margin: 0;
  font-size: 14px;
  color: var(--v-text);
}
.bp-title small {
  font-size: 12px;
  color: var(--v-muted);
}
.bp-sizes {
  display: grid;
  gap: 8px;
}
.bp-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--v-text-2);
}
.bp-chip-x {
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  padding: 0;
  border: 0;
  border-radius: 6px;
  background: none;
  color: var(--v-muted);
  font-size: 16px;
  line-height: 1;
  cursor: pointer;
}
.bp-chip-x:hover:not(:disabled) {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.bp-chip-x:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}
.bp-add {
  display: flex;
  gap: 8px;
}
.bp-add .sp-input {
  width: 110px;
}
.bp-actions {
  flex-wrap: wrap;
}
.bp-note {
  margin: -6px 0 0;
}
.bp-field {
  display: grid;
  /* Rows keep their own height: the price box doesn't stretch to match the style column */
  align-content: start;
  gap: 6px;
  min-width: 0;
}
.bp-field small {
  font-weight: 400;
  color: var(--v-muted);
}
.bp-row {
  display: grid;
  grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
  align-items: start;
  gap: 10px;
}
/* Narrow cards: price above style, so nothing overlaps */
@media (max-width: 520px) {
  .bp-row {
    grid-template-columns: minmax(0, 1fr);
  }
}
.bp-money {
  display: flex;
  align-items: center;
  gap: 6px;
  /* Stays inside its column (a number box would otherwise push it wider) */
  min-width: 0;
  min-height: 44px;
  padding: 0 12px;
  border: 1px solid var(--v-border);
  border-radius: 10px;
  background: var(--v-raised);
  color: var(--v-muted);
}
.bp-money:focus-within {
  border-color: var(--bc-accent);
}
.bp-money.invalid {
  border-color: var(--v-red-text);
}
.bp-money input {
  flex: 1;
  width: 100%;
  min-width: 0;
  border: 0;
  background: none;
  color: var(--v-text);
  font: inherit;
  font-size: 14px;
  outline: none;
}
.bp-parts {
  display: grid;
  gap: 2px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  overflow: hidden;
}
.bp-part {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  background: var(--v-subtle);
  cursor: pointer;
}
.bp-part > span:first-child {
  display: grid;
  gap: 2px;
  min-width: 0;
}
.bp-part b {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.bp-part small {
  font-size: 12px;
  color: var(--v-muted);
}
.bp-part.na {
  cursor: default;
}
.bp-part.na b {
  color: var(--v-muted);
}
.bp-icons {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.bp-icon-pick {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 1px solid var(--v-border);
  border-radius: 10px;
  background: var(--v-subtle);
  color: var(--v-muted);
  cursor: pointer;
  transition: border-color 0.15s, color 0.15s;
}
.bp-icon-pick :deep(svg) {
  width: 20px;
  height: 20px;
}
.bp-icon-pick:hover {
  color: var(--v-text);
}
.bp-icon-pick.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
}
.bp-icon-pick:focus-visible,
.bp-remove:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.bp-remove {
  justify-self: start;
  min-height: 36px;
  padding: 0 4px;
  border: 0;
  background: none;
  color: var(--v-red-text);
  font: inherit;
  font-size: 13px;
  cursor: pointer;
}
.bp-remove:hover {
  text-decoration: underline;
}
@media (max-width: 420px) {
  .bp-row {
    grid-template-columns: 1fr;
  }
}
/* Sizes and the stock each uses: Size | Wrapper sheets | Filler | × */
.bp-size-table {
  display: grid;
  gap: 6px;
}
.bp-size-row {
  display: grid;
  grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 1fr) 32px;
  align-items: center;
  gap: 8px;
  padding: 6px 6px 6px 12px;
  border: 1px solid var(--v-border);
  border-radius: 10px;
  background: var(--v-subtle);
}
.bp-size-head {
  padding-top: 0;
  padding-bottom: 0;
  border: 0;
  background: none;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-text-2);
}
.bp-size-head small {
  display: block;
  font-weight: 400;
  color: var(--v-muted);
}
.bp-size-head .na {
  color: var(--v-muted);
}
.bp-size-name {
  min-width: 0;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.bp-size-name b {
  color: var(--v-text);
}
.bp-size-name small {
  display: block;
  color: var(--v-muted);
}
.bp-size-row .sp-input {
  width: 100%;
  min-height: 40px;
  padding: 0 10px;
}
.bp-ribbon-use {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 4px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--v-text);
}
.bp-ribbon-use small {
  font-weight: 400;
  color: var(--v-muted);
}
.bp-ribbon-use .sp-input {
  width: 90px;
  min-height: 40px;
}
.bp-uses-note {
  color: var(--v-muted);
  font-size: 12px;
}
.bp-upload-link {
  justify-self: start;
  margin-top: 6px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--v-accent, var(--bc-accent));
  font: inherit;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
}
.bp-upload-link:hover {
  text-decoration: underline;
}
.bp-upload-link:focus-visible {
  outline: 2px solid var(--v-accent, var(--bc-accent));
  outline-offset: 2px;
  border-radius: 4px;
}
</style>
