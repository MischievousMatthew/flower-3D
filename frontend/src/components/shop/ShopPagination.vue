<template>
  <nav v-if="last > 1" class="pg" aria-label="Pages">
    <button type="button" class="pg-step" :disabled="page <= 1" aria-label="Previous page" @click="go(page - 1)">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
      <span class="pg-word">Previous</span>
    </button>
    <ol class="pg-list">
      <li v-for="(item, i) in items" :key="i">
        <span v-if="item === '…'" class="pg-gap" aria-hidden="true">…</span>
        <button
          v-else
          type="button"
          class="pg-num"
          :class="{ on: item === page }"
          :aria-current="item === page ? 'page' : null"
          :aria-label="`Page ${item}`"
          @click="go(item)"
        >{{ item }}</button>
      </li>
    </ol>
    <button type="button" class="pg-step" :disabled="page >= last" aria-label="Next page" @click="go(page + 1)">
      <span class="pg-word">Next</span>
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
    </button>
  </nav>
</template>

<script setup>
import { computed } from "vue";

/** Numbered pages: 1 … 4 5 6 … 12 */
const props = defineProps({
  page: { type: Number, required: true },
  last: { type: Number, required: true },
});
const emit = defineEmits(["go"]);

const items = computed(() => {
  const { page, last } = props;
  const wanted = new Set([1, last, page - 1, page, page + 1].filter((n) => n >= 1 && n <= last));
  if (page <= 3) [2, 3, 4].forEach((n) => n <= last && wanted.add(n));
  if (page >= last - 2) [last - 1, last - 2, last - 3].forEach((n) => n >= 1 && wanted.add(n));
  const sorted = [...wanted].sort((a, b) => a - b);
  const out = [];
  sorted.forEach((n, i) => {
    if (i && n - sorted[i - 1] > 1) out.push("…");
    out.push(n);
  });
  return out;
});
const go = (n) => n !== props.page && n >= 1 && n <= props.last && emit("go", n);
</script>

<style scoped>
.pg {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 36px;
}

.pg-list {
  display: flex;
  align-items: center;
  gap: 4px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pg-num,
.pg-step {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-width: 44px;
  height: 44px;
  padding: 0 12px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--paper);
  color: var(--ink);
  font: 500 13.5px var(--font-sans);
  font-variant-numeric: tabular-nums;
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease, color 0.2s ease;
}

.pg-num:hover,
.pg-step:hover:not(:disabled) {
  border-color: var(--ink);
}

.pg-num.on {
  border-color: var(--ink);
  background: var(--ink);
  color: var(--paper);
}

.pg-step:disabled {
  opacity: 0.4;
  cursor: default;
}

.pg-step svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pg-gap {
  display: inline-block;
  width: 24px;
  text-align: center;
  color: var(--ink-soft);
}

.pg :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

@media (max-width: 560px) {
  .pg-word {
    display: none;
  }

  .pg-step {
    padding: 0;
  }

  .pg-num {
    min-width: 40px;
    padding: 0 8px;
  }
}
</style>
