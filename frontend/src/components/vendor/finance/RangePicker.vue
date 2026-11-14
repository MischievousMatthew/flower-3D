<template>
  <div class="rp">
    <!-- Capsule slider: the highlight slides to the picked period -->
    <div class="rp-cap-scroll">
      <div ref="capsule" class="rp-cap" role="radiogroup" aria-label="Period" :style="{ '--n': PRESETS.length, '--i': activeIndex }" @keydown="onKey">
        <span class="rp-thumb" aria-hidden="true"></span>
        <button
          v-for="(p, i) in PRESETS"
          :key="p.key"
          type="button"
          role="radio"
          class="rp-opt"
          :class="{ on: i === activeIndex }"
          :aria-checked="i === activeIndex"
          :tabindex="i === activeIndex ? 0 : -1"
          @click="pick(p.key)"
        >{{ p.short || p.label }}</button>
      </div>
    </div>
    <template v-if="financeRange.preset === 'custom'">
      <input :value="financeRange.from" type="date" class="sp-input rp-date" aria-label="From" :max="financeRange.to" @change="custom('from', $event.target.value)" />
      <span class="rp-to">to</span>
      <input :value="financeRange.to" type="date" class="sp-input rp-date" aria-label="To" :min="financeRange.from" :max="today" @change="custom('to', $event.target.value)" />
    </template>
    <span v-else class="rp-label">{{ label }}</span>
  </div>
</template>

<script setup>
import { computed, nextTick, ref } from "vue";
import { PRESETS, financeRange, presetRange, rangeLabel, setRange, ymd } from "./financeUi";

const emit = defineEmits(["change"]);
const today = ymd(new Date());

const label = computed(() => rangeLabel(financeRange));
const capsule = ref(null);
const activeIndex = computed(() => Math.max(0, PRESETS.findIndex((p) => p.key === financeRange.preset)));

// Arrow keys move along the capsule, like any radio group
function onKey(e) {
  const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
  let next = step === undefined ? { Home: 0, End: PRESETS.length - 1 }[e.key] : (activeIndex.value + step + PRESETS.length) % PRESETS.length;
  if (next === undefined) return;
  e.preventDefault();
  pick(PRESETS[next].key);
  nextTick(() => capsule.value?.querySelectorAll(".rp-opt")[next]?.focus());
}

function pick(key) {
  if (key === financeRange.preset && key !== "custom") return;
  setRange(key === "custom" ? { preset: "custom" } : { preset: key, ...presetRange(key) });
  if (key !== "custom") emit("change");
}
function custom(field, value) {
  if (!value) return;
  setRange({ preset: "custom", [field]: value });
  if (financeRange.from <= financeRange.to) emit("change");
}
</script>

<style scoped>
.rp {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
/* Capsule slider */
.rp-cap-scroll {
  max-width: 100%;
  overflow-x: auto;
  scrollbar-width: none;
  border-radius: 999px;
}
.rp-cap-scroll::-webkit-scrollbar {
  display: none;
}
.rp-cap {
  position: relative;
  display: grid;
  grid-template-columns: repeat(var(--n), minmax(84px, 1fr));
  padding: 4px;
  border: 1px solid var(--v-border-strong);
  border-radius: 999px;
  background: var(--v-subtle);
  isolation: isolate;
}
.rp-thumb {
  position: absolute;
  z-index: -1;
  top: 4px;
  bottom: 4px;
  left: 4px;
  width: calc((100% - 8px) / var(--n));
  border-radius: 999px;
  background: var(--bc-accent);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.18);
  transform: translateX(calc(var(--i) * 100%));
  transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}
.rp-opt {
  min-height: 34px;
  padding: 6px 12px;
  border: 0;
  border-radius: 999px;
  background: none;
  font-family: "Poppins", sans-serif;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-text-2);
  white-space: nowrap;
  cursor: pointer;
  transition: color 0.2s;
}
.rp-opt:hover:not(.on) {
  color: var(--v-text);
}
.rp-opt.on {
  color: var(--bc-accent-ink, #fff);
  font-weight: 600;
}
.rp-opt:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 1px;
}
@media (prefers-reduced-motion: reduce) {
  .rp-thumb,
  .rp-opt {
    transition: none;
  }
}
.rp-date {
  width: 150px;
  padding: 8px 10px;
}
.rp-to {
  font-size: 12.5px;
  color: var(--v-muted);
}
.rp-label {
  font-size: 12.5px;
  color: var(--v-muted);
  white-space: nowrap;
}
@media (max-width: 640px) {
  .rp {
    width: 100%;
  }
  .rp-cap-scroll {
    width: 100%;
  }
  .rp-date {
    flex: 1;
    min-width: 0;
    width: auto;
  }
}
</style>
