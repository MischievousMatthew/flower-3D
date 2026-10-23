<template>
  <ul class="pr" aria-label="Password rules">
    <li v-for="r in rules" :key="r.label" :class="{ met: r.met }">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path v-if="r.met" d="m6 12.5 4 4 8-9" />
        <circle v-else cx="12" cy="12" r="3.2" />
      </svg>
      {{ r.label }}<span class="bc-sr-only">{{ r.met ? " — done" : " — not yet" }}</span>
    </li>
  </ul>
</template>

<script setup>
// The same rules the server checks, ticked off while typing
import { computed } from "vue";
import { passwordRules } from "../../utils/passwordRules";

const props = defineProps({ password: { type: String, default: "" } });
const rules = computed(() => passwordRules(props.password));
</script>

<style scoped>
.pr {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 14px;
  margin: 2px 0 0;
  padding: 0;
  list-style: none;
  font-size: 13px;
  color: var(--ink-soft);
}
.pr li {
  display: flex;
  align-items: center;
  gap: 7px;
  transition: color 0.2s;
}
.pr svg {
  flex: none;
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.pr circle {
  fill: currentColor;
  stroke: none;
  opacity: 0.45;
}
.pr .met {
  color: #3f7a55;
}
</style>
