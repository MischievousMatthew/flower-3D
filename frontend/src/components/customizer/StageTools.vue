<template>
  <div v-if="ready" class="st-stack" role="toolbar" aria-label="Move, rotate and size" aria-orientation="vertical">
    <button
      type="button"
      class="st-tool"
      :class="{ on: bouquetSelected }"
      :data-tip="bouquetSelected ? 'Deselect bouquet (Esc)' : 'Select whole bouquet'"
      :aria-pressed="bouquetSelected"
      aria-label="Select whole bouquet"
      @click="$emit('toggle-bouquet')"
    >
      <CustomizerIcon name="bouquet" />
    </button>
    <hr />
    <button
      v-for="tool in modes"
      :key="tool.value"
      type="button"
      class="st-tool"
      :class="{ on: hasTarget && mode === tool.value }"
      :data-tip="`${tool.label} (${tool.key})`"
      :aria-label="tool.label"
      :aria-pressed="mode === tool.value"
      :disabled="!hasTarget || (tool.value === 'scale' && bouquetSelected)"
      @click="$emit('mode', tool.value)"
    >
      <CustomizerIcon :name="tool.icon" />
    </button>
    <hr />
    <button type="button" class="st-tool" data-tip="Focus view (F)" aria-label="Focus the view on the selection" :disabled="!hasTarget" @click="$emit('focus')">
      <CustomizerIcon name="target" />
    </button>
  </div>

  <div class="st-view">
    <button type="button" class="st-tool bare" data-tip="Start over" aria-label="Start over" :disabled="!canReset" @click="$emit('reset')">
      <CustomizerIcon name="reset" />
    </button>
    <button type="button" class="st-tool bare" data-tip="Save as image" aria-label="Save as image" :disabled="!ready" @click="$emit('screenshot')">
      <CustomizerIcon name="camera" />
    </button>
    <button
      type="button"
      class="st-tool bare"
      :data-tip="fullscreen ? 'Exit full screen' : 'Full screen'"
      :aria-label="fullscreen ? 'Exit full screen' : 'Full screen'"
      :disabled="!ready"
      @click="$emit('fullscreen')"
    >
      <CustomizerIcon :name="fullscreen ? 'minimize' : 'maximize'" />
    </button>
  </div>
</template>

<script setup>
import CustomizerIcon from "./CustomizerIcon.js";

defineProps({
  modes: { type: Array, required: true }, // [{ value, label, key, icon }]
  mode: { type: String, required: true },
  hasTarget: { type: Boolean, default: false },
  bouquetSelected: { type: Boolean, default: false },
  ready: { type: Boolean, default: false },
  fullscreen: { type: Boolean, default: false },
  canReset: { type: Boolean, default: false },
});
defineEmits(["mode", "toggle-bouquet", "focus", "reset", "screenshot", "fullscreen"]);
</script>

<style scoped>
.st-stack {
  position: absolute;
  top: 22px;
  left: 22px;
  z-index: 5;
  display: grid;
  gap: 2px;
  padding: 4px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: rgba(250, 251, 250, 0.92);
  backdrop-filter: blur(6px);
}

.st-stack hr {
  margin: 3px 5px;
  border: 0;
  border-top: 1px solid var(--line);
}

.st-view {
  position: absolute;
  top: 22px;
  right: 22px;
  z-index: 5;
  display: flex;
  gap: 2px;
}

.st-tool {
  position: relative;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  padding: 0;
  border: 0;
  border-radius: 8px;
  background: none;
  color: var(--ink-soft);
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease;
}

.st-tool:hover:not(:disabled) {
  background: rgba(54, 63, 78, 0.06);
  color: var(--ink);
}

.st-tool.on {
  background: var(--ink);
  color: var(--paper);
}

.st-tool.on:hover:not(:disabled) {
  background: var(--ink);
  color: var(--paper);
}

.st-tool:disabled {
  cursor: not-allowed;
  opacity: 0.35;
}

.st-tool:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 1px;
}

/* Tooltip: to the right of the stack, below the view icons */
.st-tool::after {
  content: attr(data-tip);
  position: absolute;
  z-index: 10;
  padding: 5px 9px;
  border-radius: 6px;
  background: var(--ink);
  color: var(--paper);
  font: 500 11px/1.2 var(--font-sans);
  white-space: nowrap;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.15s ease;
}

.st-stack .st-tool::after {
  left: calc(100% + 10px);
  top: 50%;
  transform: translateY(-50%);
}

.st-view .st-tool::after {
  top: calc(100% + 8px);
  right: 0;
}

.st-tool:is(:hover, :focus-visible)::after {
  opacity: 1;
}

@media (hover: none) {
  .st-tool::after {
    display: none;
  }
}
</style>
