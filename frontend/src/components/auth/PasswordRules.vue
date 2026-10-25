<template>
  <div class="pr" :class="`pr-${tone}`">
    <!-- How much of the list is done, in words as well as the bar -->
    <div class="pr-meter">
      <div
        class="pr-track"
        role="progressbar"
        aria-label="Password requirements met"
        :aria-valuenow="percent"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuetext="`${percent}%, ${status}`"
      >
        <i :class="level" :style="{ width: `${percent}%` }"></i>
      </div>
      <span class="pr-percent" :class="level" aria-hidden="true">{{ percent }}% · {{ status }}</span>
    </div>
    <ul class="pr-list" aria-label="Password rules">
      <li v-for="r in rules" :key="r.label" :class="{ met: r.met }">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path v-if="r.met" d="m6 12.5 4 4 8-9" />
          <circle v-else cx="12" cy="12" r="3.2" />
        </svg>
        {{ r.label }}<span class="pr-sr">{{ r.met ? " — done" : " — not yet" }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
// The same rules the server checks, ticked off while typing, with how much
// of the list is done. Used on every form that sets a password.
import { computed } from "vue";
import { passwordPercent, passwordRules } from "../../utils/passwordRules";

const props = defineProps({
  password: { type: String, default: "" },
  // 8 for customers, shops and staff; 10 for administrators
  min: { type: Number, default: 8 },
  // Matches the page: customer (navy/green), admin (purple) or vendor (vendor tokens, dark mode)
  tone: { type: String, default: "customer" },
});
const rules = computed(() => passwordRules(props.password, props.min));
const percent = computed(() => passwordPercent(props.password, props.min));
const level = computed(() => (percent.value === 100 ? "done" : percent.value >= 50 ? "close" : "low"));
const status = computed(() => {
  if (!props.password) return "start typing";
  if (percent.value === 100) return "all requirements met";
  const left = rules.value.filter((r) => !r.met).length;
  return `${left} ${left === 1 ? "requirement" : "requirements"} left`;
});
</script>

<style scoped>
.pr {
  --pr-text: var(--ink-soft, #64748b);
  --pr-met: #3f7a55;
  --pr-track: rgba(54, 63, 78, 0.1);
  --pr-low: #c2410c;
  --pr-close: #b7791f;
  --pr-done: #2f8f5b;
  display: grid;
  gap: 8px;
  margin: 2px 0 0;
}
.pr-admin {
  --pr-text: #64748b;
  --pr-met: #15803d;
  --pr-track: #eef0f4;
  --pr-done: #16a34a;
}
.pr-vendor {
  --pr-text: var(--v-muted, #64748b);
  --pr-met: var(--v-green-text, #2f855a);
  --pr-track: var(--v-subtle, #eef0f4);
  --pr-done: var(--v-green-text, #2f855a);
}
.pr-meter {
  display: flex;
  align-items: center;
  gap: 10px;
}
.pr-track {
  flex: 1;
  height: 6px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--pr-track);
}
.pr-track i {
  display: block;
  height: 100%;
  border-radius: inherit;
  transition: width 0.25s ease, background-color 0.25s ease;
}
.pr-track i.low { background: var(--pr-low); }
.pr-track i.close { background: var(--pr-close); }
.pr-track i.done { background: var(--pr-done); }
.pr-percent {
  flex: none;
  font-size: 12px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  color: var(--pr-text);
}
.pr-percent.done {
  color: var(--pr-met);
}
.pr-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 14px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 13px;
  color: var(--pr-text);
}
.pr-list li {
  display: flex;
  align-items: center;
  gap: 7px;
  transition: color 0.2s;
}
.pr-list svg {
  flex: none;
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.pr-list circle {
  fill: currentColor;
  stroke: none;
  opacity: 0.45;
}
.pr-list .met {
  color: var(--pr-met);
}
.pr-sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
@media (max-width: 420px) {
  .pr-list {
    grid-template-columns: 1fr;
  }
}
@media (prefers-reduced-motion: reduce) {
  .pr-track i {
    transition: none;
  }
}
</style>
