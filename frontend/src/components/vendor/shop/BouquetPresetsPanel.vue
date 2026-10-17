<template>
  <section id="cz-panel-presets" class="bp" role="tabpanel" aria-labelledby="cz-tab-presets">
    <div class="bp-head">
      <div>
        <h2>Occasions &amp; sizes</h2>
        <p>Customers start the 3D customizer by picking an occasion, then how many flowers. Turn occasions on or off and choose the sizes you offer. Bigger bouquets get a bigger wrapper automatically.</p>
      </div>
      <div class="bp-actions">
        <button type="button" class="sp-btn sp-btn-ghost" :disabled="!dirty || saving" @click="discard">Discard changes</button>
        <button type="button" class="sp-btn sp-btn-primary" :disabled="!dirty || saving || !presets" @click="save">{{ saving ? "Saving…" : "Save" }}</button>
      </div>
    </div>

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
      <article v-for="p in presets" :key="p.key" class="sp-card bp-card" :class="{ off: !p.enabled }">
        <header class="bp-card-head">
          <span class="bp-icon" v-html="OCCASION_ICONS[OCCASION_INFO[p.key]?.icon] || OCCASION_ICONS.flower"></span>
          <div class="bp-title">
            <h3>{{ p.label }}</h3>
            <small>{{ DESIGNS[p.design]?.label ?? "Classic cone" }} · {{ buildText(p) }}</small>
          </div>
          <label class="bp-toggle" :title="p.enabled && enabledCount === 1 ? 'Keep at least one occasion on' : p.enabled ? 'Customers can pick this occasion' : 'Hidden from customers'">
            <span class="bc-sr-only">Offer {{ p.label }}</span>
            <span class="sp-switch"><input type="checkbox" :checked="p.enabled" :aria-label="`Offer ${p.label}`" @change="toggle(p, $event)" /><span></span></span>
          </label>
        </header>

        <div class="bp-sizes" :aria-label="`${p.label} sizes`">
          <span class="bp-label">Sizes (flowers per bouquet)</span>
          <ul>
            <li v-for="(size, i) in p.sizes" :key="size" class="bp-chip">
              <span><b>{{ size }}</b> {{ size === 1 ? "flower" : "flowers" }}</span>
              <small>{{ sizeName(p.sizes, i) }}</small>
              <button
                type="button"
                class="bp-chip-x"
                :disabled="p.sizes.length === 1"
                :title="p.sizes.length === 1 ? 'Keep at least one size' : `Remove ${size}`"
                :aria-label="`Remove the ${size}-flower size`"
                @click="removeSize(p, size)"
              >×</button>
            </li>
          </ul>
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
          <small v-if="errors[p.key]" class="sp-error">{{ errors[p.key] }}</small>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import { OCCASION_INFO, sizeName } from "../../../constants/bouquetPresets";
import { OCCASION_ICONS } from "../../../constants/occasionIcons";
import { DESIGNS } from "../../../utils/bouquetDesigns/index.js";
import "./shop.css";

const emit = defineEmits(["count"]);

const presets = ref(null);
const saved = ref("");
const loadError = ref("");
const saving = ref(false);
const limits = reactive({ max_size: 36, max_sizes: 4 });
const drafts = reactive({});
const errors = reactive({});

const snapshot = () => JSON.stringify(presets.value?.map((p) => ({ k: p.key, e: p.enabled, s: p.sizes })));
const dirty = computed(() => !!presets.value && snapshot() !== saved.value);
const enabledCount = computed(() => presets.value?.filter((p) => p.enabled).length ?? 0);

const buildText = (p) =>
  p.wrapper && p.ribbon ? "Wrapper and ribbon" : p.wrapper ? "Wrapper, no ribbon" : p.ribbon ? "Hand-tied: ribbon only, no wrapper" : "Stems only";

function clearErrors() {
  Object.keys(errors).forEach((k) => delete errors[k]);
}

function setPresets(list) {
  presets.value = list.map((p) => ({ ...p, sizes: [...p.sizes] }));
  saved.value = snapshot();
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
  delete errors[p.key];
  const n = Number(drafts[p.key]);
  if (!Number.isInteger(n) || n < 1 || n > limits.max_size) {
    errors[p.key] = `Use a whole number from 1 to ${limits.max_size}.`;
    return;
  }
  if (p.sizes.includes(n)) {
    errors[p.key] = `${n} is already one of the sizes.`;
    return;
  }
  p.sizes = [...p.sizes, n].sort((a, b) => a - b);
  drafts[p.key] = null;
}

function removeSize(p, size) {
  if (p.sizes.length === 1) return;
  p.sizes = p.sizes.filter((s) => s !== size);
  delete errors[p.key];
}

function discard() {
  clearErrors();
  setPresets(JSON.parse(saved.value).map((s) => ({ ...presets.value.find((p) => p.key === s.k), enabled: s.e, sizes: s.s })));
}

async function save() {
  if (saving.value || !dirty.value) return;
  clearErrors();
  saving.value = true;
  try {
    const body = Object.fromEntries(presets.value.map((p) => [p.key, { enabled: p.enabled, sizes: p.sizes }]));
    const { data } = await api.put("vendor/bouquet-presets", { presets: body });
    setPresets(data.data || presets.value);
    toast.success(data.message || "Occasion presets saved.");
  } catch (e) {
    const fieldErrors = e?.response?.data?.errors;
    if (fieldErrors) {
      for (const [field, list] of Object.entries(fieldErrors)) errors[field.split(".")[0]] = [].concat(list)[0];
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
.bp-sizes ul {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.bp-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 6px 6px 12px;
  border: 1px solid var(--v-border);
  border-radius: 10px;
  background: var(--v-subtle);
  font-size: 12.5px;
  color: var(--v-text-2);
}
.bp-chip b {
  color: var(--v-text);
}
.bp-chip small {
  color: var(--v-muted);
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
</style>
