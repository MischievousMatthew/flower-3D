<template>
  <footer class="or">
    <div class="or-head">
      <button
        type="button"
        class="or-toggle"
        :aria-expanded="open"
        aria-controls="or-breakdown"
        :disabled="!extras.length"
        @click="open = !open"
      >
        <span class="or-label">Total</span>
        <strong>{{ peso(total) }}</strong>
        <svg v-if="extras.length" class="or-chev" :class="{ 'is-open': open }" viewBox="0 0 16 16" aria-hidden="true">
          <path d="M4 10l4-4 4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
      <span class="or-stems">{{ stems }} of {{ maxStems }} stems</span>
    </div>
    <div class="or-meter" role="progressbar" :aria-valuenow="stems" aria-valuemin="0" :aria-valuemax="maxStems" aria-label="Stems placed">
      <i :style="{ width: `${maxStems ? Math.min(stems / maxStems, 1) * 100 : 0}%` }"></i>
    </div>

    <dl v-show="open" id="or-breakdown" class="or-lines">
      <div v-for="line in extras" :key="line.label">
        <dt>{{ line.label }}</dt>
        <dd>{{ line.amount ? peso(line.amount) : "Included" }}</dd>
      </div>
    </dl>

    <div class="or-actions">
      <button type="button" class="or-btn or-btn-outline" :disabled="disabled || busy" @click="$emit('add-to-cart')">
        {{ editing ? "Update cart" : "Add to cart" }}
      </button>
      <button type="button" class="or-btn or-btn-dark" :disabled="disabled || busy" @click="$emit('checkout')">
        {{ busy ? "Processing…" : "Checkout" }}
      </button>
    </div>
    <p v-if="!stems" class="or-hint">Add at least one stem to continue.</p>
  </footer>
</template>

<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  stems: { type: Number, required: true },
  maxStems: { type: Number, required: true },
  lines: { type: Array, default: () => [] },
  total: { type: Number, required: true },
  busy: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  editing: { type: Boolean, default: false },
  format: { type: Function, default: null },
});
defineEmits(["add-to-cart", "checkout"]);

// The breakdown stays folded so the step list above keeps its room
const open = ref(false);

// The stems line already shows above the meter; the rest are the extras
const extras = computed(() => props.lines.slice(1));
const peso = (value) => (props.format ? props.format(value) : `₱${Number(value || 0).toFixed(2)}`);
</script>

<style scoped>
.or {
  padding: 12px 26px 14px;
  border-top: 1px solid var(--line);
  background: var(--paper);
  font-size: 12.5px;
  color: var(--ink-soft);
}

.or-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.or-toggle {
  display: inline-flex;
  align-items: baseline;
  gap: 8px;
  min-height: 32px;
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.or-toggle:disabled {
  cursor: default;
}

.or-toggle:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
  border-radius: 4px;
}

.or-label {
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.or-toggle strong {
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 500;
  line-height: 1;
  color: var(--ink);
}

.or-chev {
  width: 14px;
  height: 14px;
  align-self: center;
  transform: rotate(180deg);
  transition: transform 0.2s ease;
}

.or-chev.is-open {
  transform: none;
}

.or-stems {
  color: var(--ink);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.or-lines > div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.or-meter {
  height: 2px;
  margin: 6px 0 10px;
  background: var(--line);
}

.or-meter i {
  display: block;
  height: 100%;
  background: var(--leaf);
  transition: width 0.3s ease;
}

.or-lines {
  display: grid;
  gap: 3px;
  margin: 0 0 10px;
}

.or-lines dt {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.or-lines dd {
  flex-shrink: 0;
  margin: 0;
  color: var(--ink);
}

.or-actions {
  display: grid;
  grid-template-columns: 1fr 1.35fr;
  gap: 8px;
}

.or-btn {
  height: 40px;
  padding: 0 14px;
  border-radius: 10px;
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: 500;
  letter-spacing: 0.02em;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.or-btn-dark {
  border: 1px solid var(--ink);
  background: var(--ink);
  color: var(--paper);
}

.or-btn-dark:hover:not(:disabled) {
  border-color: var(--leaf-deep);
  background: var(--leaf-deep);
}

.or-btn-outline {
  border: 1px solid var(--line);
  background: transparent;
  color: var(--ink);
}

.or-btn-outline:hover:not(:disabled) {
  border-color: var(--ink);
}

.or-btn:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.or-btn:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

.or-hint {
  margin: 6px 0 0;
  font-size: 11.5px;
  text-align: center;
}

@media (prefers-reduced-motion: reduce) {
  .or-chev,
  .or-meter i {
    transition: none;
  }
}
</style>
