<template>
  <div ref="root" class="sm" :class="`sm-${tone}`">
    <template v-if="hasMore">
      <!-- Scrolling here loads the next rows; the small circle says more is coming -->
      <span class="sm-spin" role="status" :aria-label="loading ? 'Loading more' : 'More below'"></span>
      <small v-if="total" class="sm-count" aria-live="polite">Showing {{ shown }} of {{ total }}</small>
      <!-- For keyboards and screen readers, which don't scroll it into view -->
      <button type="button" class="sm-sr" :disabled="loading" @click="more">Load more</button>
    </template>
    <p v-else-if="shown" class="sm-done" role="status">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 12.5 4 4 8-9" /></svg>
      {{ doneText }}
    </p>
  </div>
</template>

<script setup>
// The end of every long list. While there's more, a small loading circle sits
// at the bottom and the next rows load as soon as it scrolls into view (no
// button to press); then "You're all caught up". Rows still arrive 10, then
// 10, then 20 at a time (composables/useShowMore.js).
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";

const props = defineProps({
  hasMore: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  shown: { type: Number, default: 0 },
  total: { type: Number, default: 0 },
  // admin (purple), vendor (vendor colors, dark mode) or customer (navy/green)
  tone: { type: String, default: "vendor" },
  doneText: { type: String, default: "You're all caught up" },
});
const emit = defineEmits(["more"]);

const root = ref(null);
let observer = null;
let inView = false;
let waiting = false;

function more() {
  if (!props.hasMore || props.loading || waiting) return;
  waiting = true;
  emit("more");
  // Give the new rows a moment to render, then keep going while the circle is still on screen
  setTimeout(async () => {
    waiting = false;
    await nextTick();
    if (inView) more();
  }, 350);
}

onMounted(() => {
  if (typeof IntersectionObserver === "undefined") return;
  observer = new IntersectionObserver(
    ([entry]) => {
      inView = entry.isIntersecting;
      if (inView) more();
    },
    // Start a little before it's fully on screen, so scrolling feels continuous
    { rootMargin: "0px 0px 240px 0px" },
  );
  if (root.value) observer.observe(root.value);
});

// A load that finished while the circle is still visible fetches the next rows
watch(
  () => props.loading,
  (now, before) => {
    if (before && !now && inView) setTimeout(more, 150);
  },
);

onBeforeUnmount(() => observer?.disconnect());
</script>

<style scoped>
.sm {
  --sm-text: var(--v-muted, #64748b);
  --sm-track: var(--v-border, #e2e8f0);
  --sm-accent: var(--v-green-text, #2f855a);
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 18px 0 6px;
}
.sm-admin {
  --sm-text: #64748b;
  --sm-track: #e2e8f0;
  --sm-accent: #5b4fc9;
}
.sm-customer {
  --sm-text: var(--ink-soft, #5c6573);
  --sm-track: var(--line, #e3e6e1);
  --sm-accent: var(--leaf-deep, #2f8f5b);
}
.sm-spin {
  width: 22px;
  height: 22px;
  border: 2.5px solid var(--sm-track);
  border-top-color: var(--sm-accent);
  border-radius: 50%;
  animation: sm-turn 0.8s linear infinite;
}
@keyframes sm-turn {
  to {
    transform: rotate(360deg);
  }
}
.sm-count {
  font-size: 12px;
  color: var(--sm-text);
}
.sm-sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
  border: 0;
  padding: 0;
}
.sm-sr:focus-visible {
  position: static;
  width: auto;
  height: auto;
  clip: auto;
  padding: 6px 14px;
  border: 1px solid var(--sm-accent);
  border-radius: 999px;
  background: transparent;
  color: var(--sm-accent);
  font: inherit;
  font-size: 13px;
  font-weight: 600;
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
  .sm-spin {
    animation-duration: 2.4s;
  }
}
</style>
