<template>
  <div class="an" :class="[tone, { compact, error }]" :role="tone === 'info' ? 'status' : 'alert'" aria-live="polite">
    <span class="an-icon" aria-hidden="true" v-html="ICONS[tone] ?? ICONS.info"></span>
    <!-- Compact: one short paragraph with the action at the end -->
    <p v-if="compact" class="an-line">
      <b>{{ title }}.</b>
      {{ body }}<template v-if="left > 0"> Try again in <b class="an-num">{{ formatWait(left) }}</b>.</template>
      <span v-if="$slots.default" class="an-actions"><slot /></span>
    </p>
    <div v-else class="an-body">
      <p class="an-title">{{ title }}</p>
      <p v-if="body" class="an-text">{{ body }}</p>
      <p v-if="left > 0" class="an-wait">You can try again in <b>{{ formatWait(left) }}</b></p>
      <div v-if="$slots.default" class="an-actions"><slot /></div>
    </div>
  </div>
</template>

<script setup>
// A calm explanation of what went wrong and what to do next. Tones are tinted
// paper, not alarm red: info (neutral), warn (amber), lock (waiting), offline,
// success. A countdown ticks down and emits "done" when waiting is over.
import { onBeforeUnmount, ref, watch } from "vue";
import { formatWait } from "../../utils/loginProblems";

const props = defineProps({
  tone: { type: String, default: "info" },
  title: { type: String, required: true },
  body: { type: String, default: "" },
  countdown: { type: Number, default: 0 },
  // A small one-paragraph version, for forms where space is fixed
  compact: { type: Boolean, default: false },
  // Something went wrong (not just information): a red outline
  error: { type: Boolean, default: false },
});
const emit = defineEmits(["done"]);

const left = ref(0);
let timer = null;
let endAt = 0;
watch(
  () => props.countdown,
  (s) => {
    clearInterval(timer);
    left.value = s || 0;
    if (!s) return;
    endAt = Date.now() + s * 1000;
    timer = setInterval(() => {
      left.value = Math.max(0, Math.round((endAt - Date.now()) / 1000));
      if (!left.value) {
        clearInterval(timer);
        emit("done");
      }
    }, 1000);
  },
  { immediate: true },
);
onBeforeUnmount(() => clearInterval(timer));

const svg = (d) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${d}</svg>`;
const ICONS = {
  info: svg('<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>'),
  warn: svg('<path d="M12 4 2.8 19.5h18.4Z"/><path d="M12 10v4M12 17h.01"/>'),
  lock: svg('<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 1.5M9 2h6"/>'),
  offline: svg('<path d="M2 8.8a15 15 0 0 1 20 0M5 12.6a10 10 0 0 1 14 0M8.5 16.4a5 5 0 0 1 7 0"/><path d="M12 20h.01"/>'),
  success: svg('<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16.5 9.5"/>'),
};
</script>

<style scoped>
.an {
  display: flex;
  gap: 14px;
  padding: 16px 18px;
  border-radius: 16px;
  background: #efebe5;
  color: var(--ink);
}
.an-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #fff;
  color: var(--ink);
}
.an-icon :deep(svg) {
  width: 19px;
  height: 19px;
}
.an-body {
  min-width: 0;
}
.an-title {
  margin: 0;
  font-size: 15px;
  font-weight: 500;
  line-height: 1.35;
}
.an-text {
  margin: 4px 0 0;
  font-size: 14px;
  line-height: 1.55;
  color: var(--ink-soft);
}
.an-wait {
  margin: 8px 0 0;
  font-size: 14px;
  color: var(--ink-soft);
}
.an-wait b {
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  color: var(--ink);
}
.an-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 18px;
  margin-top: 10px;
  font-size: 14px;
}
/* Drawn inside the note, so its size never changes */
.error {
  box-shadow: inset 0 0 0 1.5px #d64545;
}
.compact {
  align-items: flex-start;
  gap: 10px;
  padding: 10px 14px;
  border-radius: 12px;
}
.compact .an-icon {
  width: 24px;
  height: 24px;
}
.compact .an-icon :deep(svg) {
  width: 15px;
  height: 15px;
}
.an-line {
  margin: 2px 0 0;
  font-size: 14px;
  line-height: 1.5;
  color: var(--ink-soft);
}
.an-line > b {
  font-weight: 500;
  color: var(--ink);
}
.an-line .an-num {
  font-variant-numeric: tabular-nums;
}
.an-line .an-actions {
  display: inline;
  margin: 0 0 0 6px;
  font-size: 13px;
}
.an-line .an-actions > * + * {
  margin-left: 12px;
}
.warn {
  background: #f6eedd;
}
.warn .an-icon {
  color: #8a5a12;
}
.lock {
  background: #f4e7e2;
}
.lock .an-icon {
  color: var(--rosewood-dark);
}
.offline {
  background: #e7ebef;
}
.offline .an-icon {
  color: #3d5a73;
}
.success {
  background: #e6efe7;
}
.success .an-icon {
  color: #3f7a55;
}
</style>
