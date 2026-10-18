<template>
  <section class="sc" :class="[`side-${side}`, { docked }]" :style="docked ? null : position" aria-label="Selected item">
    <header class="sc-head">
      <h3>{{ title }}</h3>
      <span v-if="subtitle" class="sc-sub">{{ subtitle }}</span>
    </header>

    <div class="sc-row">
      <span>Turn</span>
      <span class="sc-stepper">
        <button type="button" aria-label="Turn left 15 degrees" :disabled="locked" @click="$emit('nudge-turn', -15)"><CustomizerIcon name="minus" /></button>
        <output>{{ Math.round(turn) }}&deg;</output>
        <button type="button" aria-label="Turn right 15 degrees" :disabled="locked" @click="$emit('nudge-turn', 15)"><CustomizerIcon name="plus" /></button>
      </span>
    </div>

    <div v-if="mode === 'flower'" class="sc-row">
      <label for="sc-size">Size</label>
      <span class="sc-size">
        <input
          id="sc-size"
          type="range"
          min="0.25"
          max="2"
          step="0.01"
          :disabled="locked"
          :value="scale"
          :style="fill(scale, 0.25, 2)"
          @input="$emit('scale', Number($event.target.value))"
        />
        <span class="sc-size-field" :class="{ disabled: locked }">
          <input
            type="number"
            inputmode="decimal"
            min="0.25"
            max="2"
            step="0.01"
            aria-label="Size, from 0.25 to 2"
            :disabled="locked"
            :value="sizeDraft ?? Number(scale).toFixed(2)"
            @focus="sizeDraft = Number(scale).toFixed(2)"
            @input="sizeDraft = $event.target.value"
            @keydown.enter.prevent="commitSize($event)"
            @keydown.esc.stop="cancelSize($event)"
            @blur="commitSize($event)"
          />
          <span aria-hidden="true">&times;</span>
        </span>
      </span>
    </div>
    <p v-if="mode === 'flower'" class="sc-note-size">Size is for the preview only. It doesn't change the real flower's size.</p>

    <button type="button" class="sc-row sc-more" :aria-expanded="open" @click="open = !open">
      <span>{{ mode === "flower" ? "Tilt & position" : "Position" }}</span>
      <span class="sc-more-link">Fine-tune <CustomizerIcon :name="open ? 'arrowUp' : 'chevronRight'" /></span>
    </button>

    <div v-if="open" class="sc-fine">
      <template v-if="mode === 'flower'">
        <div v-for="axis in axes" :key="axis.key" class="sc-axis">
          <div class="sc-axis-head">
            <label :for="`sc-rot-${axis.key}`">{{ axis.label }}</label>
            <output>{{ Math.round(rotation[`${axis.key}Deg`] || 0) }}&deg;</output>
          </div>
          <div class="sc-axis-row">
            <button type="button" :aria-label="`${axis.minus} 15 degrees`" :disabled="locked" @click="$emit('nudge-rotate', axis.key, -15)"><CustomizerIcon name="minus" /></button>
            <input
              :id="`sc-rot-${axis.key}`"
              type="range"
              min="-180"
              max="180"
              :disabled="locked"
              :value="rotation[`${axis.key}Deg`] || 0"
              :style="fill(rotation[`${axis.key}Deg`] || 0, -180, 180)"
              @input="$emit('rotate', axis.key, Number($event.target.value))"
            />
            <button type="button" :aria-label="`${axis.plus} 15 degrees`" :disabled="locked" @click="$emit('nudge-rotate', axis.key, 15)"><CustomizerIcon name="plus" /></button>
          </div>
        </div>
      </template>

      <div class="sc-move">
        <div class="sc-pad" :class="{ locked: moveLocked }">
          <button type="button" class="up" aria-label="Move up" title="Up (↑)" :disabled="moveLocked" @click="$emit('nudge', 'up', $event)"><CustomizerIcon name="arrowUp" /></button>
          <button type="button" class="left" aria-label="Move left" title="Left (←)" :disabled="moveLocked" @click="$emit('nudge', 'left', $event)"><CustomizerIcon name="arrowLeft" /></button>
          <button type="button" class="right" aria-label="Move right" title="Right (→)" :disabled="moveLocked" @click="$emit('nudge', 'right', $event)"><CustomizerIcon name="arrowRight" /></button>
          <button type="button" class="down" aria-label="Move down" title="Down (↓)" :disabled="moveLocked" @click="$emit('nudge', 'down', $event)"><CustomizerIcon name="arrowDown" /></button>
        </div>
        <p>Hold <kbd>Shift</kbd> for small steps. <kbd>E</kbd> / <kbd>Q</kbd> move it away from or toward you.</p>
      </div>

      <button type="button" class="sc-link" :disabled="mode === 'flower' && locked" @click="$emit('reset')">
        <CustomizerIcon name="reset" /> {{ mode === "flower" ? "Reset size and angle" : "Put the bouquet back in the centre" }}
      </button>
    </div>

    <footer class="sc-actions">
      <button v-if="mode === 'flower'" type="button" class="sc-link" :aria-pressed="locked" @click="$emit('lock')">
        <CustomizerIcon :name="locked ? 'lock' : 'unlock'" /> {{ locked ? "Unlock" : "Lock" }}
      </button>
      <button v-if="mode === 'flower'" type="button" class="sc-link danger" @click="$emit('remove')">
        <CustomizerIcon name="trash" /> Remove
      </button>
      <span v-if="mode === 'bouquet'" class="sc-note">Your flowers stay where you placed them.</span>
      <button type="button" class="sc-close" aria-label="Deselect (Esc)" title="Deselect (Esc)" @click="$emit('close')"><CustomizerIcon name="x" /></button>
    </footer>
  </section>
</template>

<script setup>
import { ref } from "vue";
import CustomizerIcon from "./CustomizerIcon.js";

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: "" },
  mode: { type: String, default: "flower" }, // "flower" | "bouquet"
  locked: { type: Boolean, default: false },
  scale: { type: Number, default: 1 },
  rotation: { type: Object, default: () => ({}) },
  turn: { type: Number, default: 0 },
  axes: { type: Array, default: () => [] },
  moveLocked: { type: Boolean, default: false },
  position: { type: Object, default: null },
  side: { type: String, default: "right" },
  docked: { type: Boolean, default: false },
});
const emit = defineEmits(["nudge-turn", "scale", "rotate", "nudge-rotate", "nudge", "lock", "remove", "reset", "close"]);

const open = ref(false);

// Typed size: applied on Enter or leaving the box, kept within the slider's range
const sizeDraft = ref(null);

function commitSize(event) {
  if (sizeDraft.value === null) return;
  const typed = Number.parseFloat(String(sizeDraft.value).replace(",", "."));
  sizeDraft.value = null;
  if (Number.isFinite(typed)) emit("scale", Math.round(Math.min(Math.max(typed, 0.25), 2) * 100) / 100);
  if (event.type === "keydown") event.target.blur();
}

function cancelSize(event) {
  sizeDraft.value = null;
  event.target.blur();
}

// Fills the slider track up to the thumb
function fill(value, min, max) {
  const percent = ((Number(value) - min) / (max - min)) * 100;
  return { "--fill": `${Math.min(Math.max(percent, 0), 100)}%` };
}
</script>

<style scoped>
.sc {
  position: absolute;
  z-index: 6;
  width: 232px;
  padding: 14px 16px 10px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--paper);
  box-shadow: 0 18px 40px -18px rgba(30, 27, 24, 0.28);
  font-size: 12.5px;
  color: var(--ink-soft);
  transition: left 0.12s ease-out, top 0.12s ease-out;
}

/* Small pointer toward the selection */
.sc::before {
  content: "";
  position: absolute;
  top: 24px;
  width: 12px;
  height: 12px;
  background: var(--paper);
  border: 1px solid var(--line);
  transform: rotate(45deg);
}

.sc.side-right::before {
  left: -7px;
  border-top: 0;
  border-right: 0;
}

.sc.side-left::before {
  right: -7px;
  border-bottom: 0;
  border-left: 0;
}

.sc.docked {
  left: 12px;
  right: 12px;
  bottom: 12px;
  width: auto;
}

.sc.docked::before {
  display: none;
}

.sc-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
  padding-bottom: 6px;
}

.sc-head h3 {
  overflow: hidden;
  margin: 0;
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 500;
  color: var(--ink);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sc-sub {
  flex-shrink: 0;
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.sc-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  width: 100%;
  min-height: 38px;
  padding: 0;
  border: 0;
  border-top: 1px solid var(--line-soft);
  background: none;
  font: inherit;
  color: inherit;
  text-align: left;
}

.sc-stepper {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--ink);
}

.sc-stepper output {
  min-width: 40px;
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.sc button:not(.sc-row):not(.sc-link):not(.sc-close) {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  padding: 0;
  border: 1px solid var(--line);
  border-radius: 7px;
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.sc button:not(.sc-row):not(.sc-link):not(.sc-close):hover:not(:disabled) {
  border-color: var(--ink);
}

.sc button:disabled {
  cursor: not-allowed;
  opacity: 0.4;
}

.sc button .fc-icon {
  width: 14px;
  height: 14px;
}

.sc-size {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--ink);
}

.sc-size-field {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  height: 26px;
  padding: 0 6px 0 4px;
  border: 1px solid var(--line);
  border-radius: 7px;
  color: var(--ink-soft);
  transition: border-color 0.2s ease;
}

.sc-size-field:focus-within {
  border-color: var(--ink);
}

.sc-size-field.disabled {
  opacity: 0.4;
}

.sc-size-field input {
  width: 36px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--ink);
  font: inherit;
  font-variant-numeric: tabular-nums;
  text-align: right;
  outline: none;
  appearance: textfield;
  -moz-appearance: textfield;
}

.sc-size-field input::-webkit-outer-spin-button,
.sc-size-field input::-webkit-inner-spin-button {
  margin: 0;
  appearance: none;
}

.sc .sc-size-field input:focus-visible {
  outline: none;
}

.sc-note-size {
  margin: -4px 0 8px;
  font-size: 11px;
  line-height: 1.45;
  color: var(--ink-soft);
}

.sc input[type="range"] {
  width: 96px;
  height: 2px;
  margin: 0;
  appearance: none;
  border-radius: 2px;
  background: linear-gradient(to right, var(--ink) var(--fill, 50%), var(--line) var(--fill, 50%));
  cursor: pointer;
}

.sc input[type="range"]::-webkit-slider-thumb {
  width: 13px;
  height: 13px;
  appearance: none;
  border: 1.5px solid var(--ink);
  border-radius: 50%;
  background: var(--paper);
}

.sc input[type="range"]::-moz-range-thumb {
  width: 11px;
  height: 11px;
  border: 1.5px solid var(--ink);
  border-radius: 50%;
  background: var(--paper);
}

.sc input[type="range"]:disabled {
  cursor: not-allowed;
  opacity: 0.4;
}

.sc-more {
  cursor: pointer;
}

.sc-more-link {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  color: var(--ink);
}

.sc-more-link .fc-icon {
  width: 14px;
  height: 14px;
}

.sc-fine {
  display: grid;
  gap: 10px;
  padding: 4px 0 10px;
}

.sc-axis {
  display: grid;
  gap: 4px;
}

.sc-axis-head {
  display: flex;
  justify-content: space-between;
}

.sc-axis-head output {
  color: var(--ink);
  font-variant-numeric: tabular-nums;
}

.sc-axis-row {
  display: grid;
  grid-template-columns: 26px 1fr 26px;
  gap: 8px;
  align-items: center;
}

.sc-axis-row input[type="range"] {
  width: 100%;
}

.sc-move {
  display: grid;
  justify-items: center;
  gap: 8px;
  padding-top: 4px;
}

.sc-move p {
  margin: 0;
  text-align: center;
  font-size: 11px;
  line-height: 1.5;
}

.sc-pad {
  display: grid;
  grid-template-areas: ". up ." "left . right" ". down .";
  grid-template-columns: repeat(3, 26px);
  gap: 2px;
}

.sc-pad .up { grid-area: up; }
.sc-pad .left { grid-area: left; }
.sc-pad .right { grid-area: right; }
.sc-pad .down { grid-area: down; }

kbd {
  padding: 0 5px;
  border: 1px solid var(--line);
  border-bottom-width: 2px;
  border-radius: 4px;
  background: var(--paper);
  font: 10.5px var(--font-sans);
  color: var(--ink);
}

.sc-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 0;
  border: 0;
  background: none;
  font: inherit;
  font-size: 12px;
  color: var(--ink-soft);
  cursor: pointer;
}

.sc-link:hover:not(:disabled) {
  color: var(--ink);
}

.sc-link.danger {
  color: var(--rosewood-dark);
}

.sc-link .fc-icon {
  width: 14px;
  height: 14px;
}

.sc-actions {
  display: flex;
  align-items: center;
  gap: 16px;
  padding-top: 8px;
  border-top: 1px solid var(--line-soft);
}

.sc-note {
  font-size: 11.5px;
}

.sc-close {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  margin-left: auto;
  padding: 0;
  border: 0;
  border-radius: 7px;
  background: none;
  color: var(--ink-soft);
  cursor: pointer;
}

.sc-close:hover {
  background: rgba(30, 27, 24, 0.05);
  color: var(--ink);
}

.sc-close .fc-icon {
  width: 14px;
  height: 14px;
}

.sc :focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 1px;
}
</style>
