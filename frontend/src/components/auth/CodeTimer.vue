<template>
  <p class="af-note ct" :class="{ bad: expired, soon: !expired && left <= 30 }" role="timer" aria-live="off">
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="13" r="8" /><path d="M12 9v4l2.5 1.5M9 2h6" /></svg>
    <template v-if="!expired">Code expires in <b>{{ formatWait(left) }}</b></template>
    <template v-else>This code has expired. Send a new one.</template>
  </p>
</template>

<script setup>
// "Code expires in 2:59", counting down from when the code was sent.
// Restarts whenever `until` changes (a new code was sent).
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { formatWait } from "../../utils/loginProblems";

const props = defineProps({
  // When the code stops working (ms since epoch)
  until: { type: Number, required: true },
});
const emit = defineEmits(["expired"]);

const left = ref(0);
const expired = computed(() => left.value <= 0);
let timer = null;

const tick = () => {
  left.value = Math.max(0, Math.round((props.until - Date.now()) / 1000));
  if (left.value <= 0) {
    clearInterval(timer);
    emit("expired");
  }
};
watch(
  () => props.until,
  () => {
    clearInterval(timer);
    tick();
    if (left.value > 0) timer = setInterval(tick, 1000);
  },
  { immediate: true },
);
onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.ct b {
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  color: var(--ink);
}
.ct.soon,
.ct.soon b {
  color: #8a5a12;
}
</style>
