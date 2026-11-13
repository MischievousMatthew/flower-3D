<!-- A "?" next to a label: hover, tap or Tab to it to read what the field does.
     Put it beside the label, not inside it (a button in a label would focus the field). -->
<template>
  <span class="it" :class="`it--${align}`">
    <button type="button" class="it-btn" :aria-describedby="id" :aria-label="label">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></svg>
    </button>
    <span :id="id" role="tooltip" class="it-box"><slot /></span>
  </span>
</template>

<script setup>
defineProps({
  // What the "?" is about, for screen readers: "About ready the same day"
  label: { type: String, required: true },
  // Which side of the "?" the box lines up with, so it stays on screen
  align: { type: String, default: "center" }, // "start" | "center" | "end"
});
const id = `it-${Math.random().toString(36).slice(2, 9)}`;
</script>

<style scoped>
.it {
  position: relative;
  display: inline-flex;
}
.it-btn {
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
.it-btn svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
}
.it-btn:hover,
.it-btn:focus-visible {
  color: var(--v-text);
}
.it-btn:focus-visible {
  outline: 2px solid var(--bc-accent, var(--v-green-text));
  outline-offset: 1px;
}
.it-box {
  position: absolute;
  bottom: calc(100% + 8px);
  z-index: 30;
  width: max-content;
  max-width: min(300px, 72vw);
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--v-ink, #1f2937);
  color: var(--v-ink-text, #fff);
  font-size: 12.5px;
  font-weight: 400;
  line-height: 1.5;
  text-align: left;
  box-shadow: var(--v-shadow, 0 10px 30px rgba(0, 0, 0, 0.2));
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.15s ease, visibility 0.15s;
  pointer-events: none;
}
.it--center .it-box {
  left: 50%;
  transform: translateX(-50%);
}
.it--start .it-box {
  left: -8px;
}
.it--end .it-box {
  right: -8px;
}
.it:hover .it-box,
.it:focus-within .it-box {
  opacity: 1;
  visibility: visible;
}
@media (prefers-reduced-motion: reduce) {
  .it-box {
    transition: none;
  }
}
</style>
