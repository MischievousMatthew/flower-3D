<template>
  <div class="sm" :class="`sm-${tone}`">
    <template v-if="hasMore">
      <button type="button" class="sm-btn" :disabled="loading" :aria-busy="loading ? 'true' : 'false'" @click="$emit('more')">
        {{ loading ? "Loading…" : "Show more" }}
      </button>
      <small v-if="total" class="sm-count" aria-live="polite">Showing {{ shown }} of {{ total }}</small>
    </template>
    <p v-else-if="shown" class="sm-done" role="status">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 12.5 4 4 8-9" /></svg>
      {{ doneText }}
    </p>
  </div>
</template>

<script setup>
// The end of every long list: "Show more" while there's more, then
// "You're all caught up". Rows are added 10, then 10, then 20 at a time
// (composables/useShowMore.js).
defineProps({
  hasMore: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  shown: { type: Number, default: 0 },
  total: { type: Number, default: 0 },
  // admin (purple), vendor (vendor colors, dark mode) or customer (navy/green)
  tone: { type: String, default: "vendor" },
  doneText: { type: String, default: "You're all caught up" },
});
defineEmits(["more"]);
</script>

<style scoped>
.sm {
  --sm-text: var(--v-muted, #64748b);
  --sm-border: var(--v-border, #e2e8f0);
  --sm-bg: var(--v-surface, #fff);
  --sm-hover: var(--v-subtle, #f8fafc);
  --sm-accent: var(--v-green-text, #2f855a);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 18px 0 6px;
}
.sm-admin {
  --sm-text: #64748b;
  --sm-border: #e2e8f0;
  --sm-bg: #fff;
  --sm-hover: #f5f3ff;
  --sm-accent: #5b4fc9;
}
.sm-customer {
  --sm-text: var(--ink-soft, #5c6573);
  --sm-border: var(--line, #e3e6e1);
  --sm-bg: #fff;
  --sm-hover: var(--ivory-deep, #f3f1ec);
  --sm-accent: var(--leaf-deep, #2f8f5b);
}
.sm-btn {
  min-width: 160px;
  min-height: 44px;
  padding: 0 22px;
  border: 1px solid var(--sm-border);
  border-radius: 999px;
  background: var(--sm-bg);
  color: var(--sm-accent);
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}
.sm-btn:hover:not(:disabled) {
  background: var(--sm-hover);
  border-color: var(--sm-accent);
}
.sm-btn:disabled {
  opacity: 0.7;
  cursor: default;
}
.sm-btn:focus-visible {
  outline: 2px solid var(--sm-accent);
  outline-offset: 2px;
}
.sm-count {
  font-size: 12px;
  color: var(--sm-text);
}
.sm-done {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  margin: 0;
  font-size: 13px;
  font-weight: 500;
  color: var(--sm-text);
}
.sm-done svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: var(--sm-accent);
  stroke-width: 2.4;
  stroke-linecap: round;
  stroke-linejoin: round;
}
@media (prefers-reduced-motion: reduce) {
  .sm-btn {
    transition: none;
  }
}
</style>
