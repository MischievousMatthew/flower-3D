<template>
  <!--
    Page numbers under an ERP / vendor portal list:
      Showing 1–10 of 26        ‹ Prev  1 2 3  Next ›
    <PageNumbers :page="meta.current_page" :pages="meta.last_page" :total="meta.total"
                 :per-page="10" :shown="rows.length" :loading="loading" label="Deliveries pages" @go="goTo" />
  -->
  <nav v-if="shown" class="pn" :class="{ 'pn--admin': tone === 'admin' }" :aria-label="label">
    <span class="pn-count" aria-live="polite">
      Showing <b>{{ from }}–{{ to }}</b> of <b>{{ total }}</b>
    </span>
    <div v-if="pages > 1" class="pn-btns">
      <button type="button" class="pn-btn pn-step" :disabled="page <= 1 || loading" aria-label="Previous page" @click="go(page - 1)">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        <span>Prev</span>
      </button>
      <template v-for="it in items" :key="it.key">
        <span v-if="it.gap" class="pn-gap" aria-hidden="true">…</span>
        <button
          v-else
          type="button"
          class="pn-btn"
          :class="{ active: it.page === page }"
          :aria-current="it.page === page ? 'page' : null"
          :aria-label="`Page ${it.page}`"
          :disabled="loading && it.page !== page"
          @click="go(it.page)"
        >{{ it.page }}</button>
      </template>
      <button type="button" class="pn-btn pn-step" :disabled="page >= pages || loading" aria-label="Next page" @click="go(page + 1)">
        <span>Next</span>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
      </button>
    </div>
  </nav>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  page: { type: Number, default: 1 },
  pages: { type: Number, default: 1 },
  total: { type: Number, default: 0 },
  perPage: { type: Number, default: 10 },
  shown: { type: Number, default: 0 }, // rows on this page
  loading: { type: Boolean, default: false },
  label: { type: String, default: "Pages" },
  tone: { type: String, default: "vendor" }, // "vendor" (portal/ERP) or "admin" (admin panel colors)
});
const emit = defineEmits(["go"]);

const from = computed(() => (props.page - 1) * props.perPage + 1);
const to = computed(() => Math.min(from.value + props.shown - 1, props.total || from.value + props.shown - 1));

// 1 … 4 5 6 … 12: the first, the last, and one either side of the current one
const items = computed(() => {
  const last = Math.max(1, props.pages);
  const cur = props.page;
  const keep = new Set([1, last, cur - 1, cur, cur + 1].filter((n) => n >= 1 && n <= last));
  if (cur <= 3) [2, 3, 4].forEach((n) => n <= last && keep.add(n));
  if (cur >= last - 2) [last - 1, last - 2, last - 3].forEach((n) => n >= 1 && keep.add(n));
  const nums = [...keep].sort((a, b) => a - b);
  const out = [];
  nums.forEach((n, i) => {
    if (i && n - nums[i - 1] > 1) out.push({ gap: true, key: `gap-${n}` });
    out.push({ page: n, key: n });
  });
  return out;
});

function go(n) {
  if (n < 1 || n > props.pages || n === props.page || props.loading) return;
  emit("go", n);
}
</script>

<style scoped>
/* Colors: the portal's --v-* tokens, or the admin panel's --a-* ones */
.pn {
  --pn-border: var(--v-border);
  --pn-border-strong: var(--v-border-strong);
  --pn-surface: var(--v-surface);
  --pn-hover: var(--v-subtle-2);
  --pn-text: var(--v-text);
  --pn-text-2: var(--v-text-2);
  --pn-muted: var(--v-muted);
  --pn-faint: var(--v-faint);
  --pn-accent: var(--bc-accent);
  --pn-accent-ink: var(--bc-accent-ink);
}
.pn--admin {
  --pn-border: var(--a-border);
  --pn-border-strong: var(--a-border-strong);
  --pn-surface: var(--a-surface);
  --pn-hover: var(--a-subtle-2);
  --pn-text: var(--a-text);
  --pn-text-2: var(--a-text-2);
  --pn-muted: var(--a-muted);
  --pn-faint: var(--a-faint);
  --pn-accent: var(--a-accent);
  --pn-accent-ink: var(--a-accent-ink);
}
.pn {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px 16px;
  margin-top: 6px;
  padding: 12px 10px 6px;
  border-top: 1px solid var(--pn-border);
}
.pn-count {
  font-size: 12.5px;
  color: var(--pn-muted);
  font-variant-numeric: tabular-nums;
}
.pn-count b {
  color: var(--pn-text);
  font-weight: 600;
}
.pn-btns {
  display: flex;
  align-items: center;
  gap: 4px;
}
.pn-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  min-width: 36px;
  height: 36px;
  padding: 0 10px;
  border: 1px solid var(--pn-border);
  border-radius: 10px;
  background: var(--pn-surface);
  color: var(--pn-text-2);
  font: 600 13px var(--font-sans, "Poppins", system-ui, sans-serif);
  font-variant-numeric: tabular-nums;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}
.pn-btn svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.pn-btn:hover:not(:disabled):not(.active) {
  border-color: var(--pn-border-strong);
  background: var(--pn-hover);
  color: var(--pn-text);
}
.pn-btn.active {
  border-color: var(--pn-accent);
  background: var(--pn-accent);
  color: var(--pn-accent-ink);
  cursor: default;
}
.pn-btn:disabled:not(.active) {
  opacity: 0.45;
  cursor: not-allowed;
}
.pn-btn:focus-visible {
  outline: 2px solid var(--pn-accent);
  outline-offset: 2px;
}
.pn-gap {
  min-width: 20px;
  text-align: center;
  color: var(--pn-faint);
}
@media (max-width: 640px) {
  .pn {
    justify-content: center;
  }
  .pn-count {
    width: 100%;
    text-align: center;
  }
  /* Arrows only on phones, so the numbers fit */
  .pn-step span {
    display: none;
  }
  .pn-btn {
    min-width: 40px;
    height: 40px;
  }
}
</style>
