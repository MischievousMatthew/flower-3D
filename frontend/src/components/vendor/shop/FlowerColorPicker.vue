<!-- Pick every colour a product's flowers come in. The first one picked is the
     main colour; with 2 or more, each keeps its own stock (see ColorStockInputs). -->
<template>
  <div class="fc">
    <div class="fc-head">
      <span :id="labelId" class="fc-label">Flower colours</span>
      <span class="fc-tip">
        <button type="button" class="fc-tip-btn" :aria-describedby="tipId" aria-label="About flower colours">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></svg>
        </button>
        <span :id="tipId" role="tooltip" class="fc-tip-box">
          These colours are for the flowers only, not the wrapping or ribbon. Pick every colour you sell it in: each
          colour keeps its own stock, so you'll enter a different quantity for each one.
        </span>
      </span>
    </div>

    <div class="fc-swatches" role="group" :aria-labelledby="labelId">
      <button
        v-for="c in FLOWER_COLORS"
        :key="c.value"
        type="button"
        role="checkbox"
        class="fc-swatch"
        :class="{ on: isOn(c.value), invalid: !!error && !modelValue.length }"
        :aria-checked="isOn(c.value)"
        :title="c.label"
        :style="{ '--sw': c.hex }"
        @click="toggle(c.value)"
      >
        <span class="fc-sr">{{ c.label }}</span>
        <svg v-if="isOn(c.value)" viewBox="0 0 24 24" aria-hidden="true" :class="{ dark: LIGHT.has(c.value) }">
          <path d="m6 12.5 4 4 8-9" />
        </svg>
      </button>
    </div>

    <small v-if="error" class="fx-error">{{ error }}</small>
    <small v-else class="fc-help">{{ summary }}</small>

    <label v-if="other" class="fc-other">
      <span>Which colour is Other?</span>
      <input
        :value="other.name"
        class="fx-input"
        :class="{ invalid: !!error && !String(other.name || '').trim() }"
        maxlength="100"
        placeholder="e.g. Burgundy, Lavender"
        @input="setOtherName($event.target.value)"
      />
    </label>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { FLOWER_COLORS, colorName } from "../../../utils/flowerColors.js";

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  error: { type: String, default: "" },
});
const emit = defineEmits(["update:modelValue"]);

const uid = Math.random().toString(36).slice(2, 8);
const labelId = `fc-label-${uid}`;
const tipId = `fc-tip-${uid}`;
// Pale swatches get a dark check mark
const LIGHT = new Set(["white", "cream", "yellow"]);

const isOn = (value) => props.modelValue.some((c) => c.value === value);
const other = computed(() => props.modelValue.find((c) => c.value === "other") || null);

const summary = computed(() => {
  const list = props.modelValue;
  if (!list.length) return "Pick every colour the flowers come in.";
  const names = list.map((c) => colorName(c) || "Other").join(", ");
  return list.length === 1 ? `${names} · pick more if you sell other colours` : `${names} · enter the stock for each colour below`;
});

function toggle(value) {
  const list = props.modelValue;
  emit(
    "update:modelValue",
    isOn(value) ? list.filter((c) => c.value !== value) : [...list, { value, name: "", quantity: list.length ? 0 : null }],
  );
}

function setOtherName(name) {
  emit(
    "update:modelValue",
    props.modelValue.map((c) => (c.value === "other" ? { ...c, name } : c)),
  );
}
</script>

<style scoped>
.fc {
  display: grid;
  gap: 8px;
}

.fc-head {
  display: flex;
  align-items: center;
  gap: 6px;
}

.fc-label {
  color: var(--v-text-2, var(--v-text));
  font-size: 13px;
  font-weight: 600;
}

.fc-tip {
  position: relative;
  display: inline-flex;
}

.fc-tip-btn {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: none;
  color: var(--v-muted);
  cursor: help;
}

.fc-tip-btn svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
}

.fc-tip-btn:hover,
.fc-tip-btn:focus-visible {
  color: var(--v-text);
}

.fc-tip-btn:focus-visible {
  outline: 2px solid var(--bc-accent, var(--v-green-text));
  outline-offset: 1px;
}

.fc-tip-box {
  position: absolute;
  left: 50%;
  bottom: calc(100% + 8px);
  z-index: 30;
  width: max-content;
  max-width: min(300px, 80vw);
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--v-ink, #1f2937);
  color: #fff;
  font-size: 12.5px;
  font-weight: 400;
  line-height: 1.5;
  box-shadow: var(--v-shadow, 0 10px 30px rgba(0, 0, 0, 0.2));
  opacity: 0;
  visibility: hidden;
  transform: translate(-50%, 4px);
  transition: opacity 0.15s ease, transform 0.15s ease, visibility 0.15s;
  pointer-events: none;
}

.fc-tip:hover .fc-tip-box,
.fc-tip:focus-within .fc-tip-box {
  opacity: 1;
  visibility: visible;
  transform: translate(-50%, 0);
}

.fc-swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

/* 30px swatch inside a 44px tap area */
.fc-swatch {
  position: relative;
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  margin: 2px;
  padding: 0;
  border: 2px solid var(--v-surface);
  border-radius: 50%;
  background: var(--sw);
  box-shadow: 0 0 0 1px var(--v-border-strong);
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.fc-swatch::before {
  content: "";
  position: absolute;
  inset: -7px;
}

.fc-swatch:hover {
  transform: scale(1.08);
}

.fc-swatch.on {
  box-shadow: 0 0 0 2px var(--bc-accent, var(--v-green-text));
  transform: scale(1.1);
}

.fc-swatch.invalid {
  box-shadow: 0 0 0 1px var(--v-red-text);
}

.fc-swatch:focus-visible {
  outline: 2px solid var(--bc-accent, var(--v-green-text));
  outline-offset: 3px;
}

.fc-swatch svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: #fff;
  stroke-width: 2.6;
  stroke-linecap: round;
  stroke-linejoin: round;
  filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.35));
}

.fc-swatch svg.dark {
  stroke: #1f2937;
  filter: none;
}

.fc-help {
  color: var(--v-muted);
}

.fc-other {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 320px;
  margin-top: 4px;
}

.fc-other > span {
  color: var(--v-text-2, var(--v-text));
  font-size: 12.5px;
  font-weight: 500;
}

/* Same look as the vendor inputs, without needing the shop stylesheet (the ERP uses this too) */
.fx-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--v-border-strong);
  border-radius: 11px;
  background: var(--v-input, var(--v-surface));
  color: var(--v-text);
  font-family: "Poppins", sans-serif;
  font-size: 13.5px;
  outline: none;
  box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.fx-input:focus {
  border-color: var(--bc-accent, var(--v-green-text));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent, #4db173) 15%, transparent);
}

.fx-input.invalid {
  border-color: #fc8181;
}

.fx-error {
  color: var(--v-red-text);
  font-size: 12px;
}

.fc-sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

@media (prefers-reduced-motion: reduce) {
  .fc-swatch,
  .fc-tip-box {
    transition: none;
  }
}
</style>
