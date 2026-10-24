<template>
  <div class="pf">
    <input
      :id="id"
      ref="input"
      :value="modelValue"
      :type="shown ? 'text' : 'password'"
      class="af-input pf-input"
      :autocomplete="autocomplete"
      :placeholder="placeholder"
      :aria-invalid="invalid ? 'true' : 'false'"
      :aria-describedby="describedby || undefined"
      v-bind="$attrs"
      @input="$emit('update:modelValue', $event.target.value)"
      @keyup="readCaps"
      @keydown="readCaps"
      @blur="caps = false"
    />
    <button type="button" class="pf-eye" :aria-label="shown ? 'Hide password' : 'Show password'" :aria-pressed="shown" @click="shown = !shown">
      <svg v-if="!shown" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
      <svg v-else viewBox="0 0 24 24"><path d="M3 3l18 18M10.6 5.1A9.7 9.7 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7c1.8 0 3.4-.6 4.7-1.4M9.9 9.9a3 3 0 0 0 4.2 4.2" /></svg>
    </button>
  </div>
  <!-- Always takes its line, so nothing below jumps when Caps Lock turns on -->
  <p class="af-note pf-caps" :class="{ on: caps }" :aria-hidden="!caps" aria-live="polite">
    <svg viewBox="0 0 24 24"><path d="M12 4 4 12h4v6h8v-6h4Z" /></svg>Caps Lock is on
  </p>
</template>

<script setup>
// A password input with a show/hide toggle and a Caps Lock hint — the two
// things behind most "my password doesn't work" moments.
import { ref } from "vue";

defineOptions({ inheritAttrs: false });
defineProps({
  modelValue: { type: String, default: "" },
  id: { type: String, default: undefined },
  autocomplete: { type: String, default: "current-password" },
  placeholder: { type: String, default: "" },
  invalid: { type: Boolean, default: false },
  describedby: { type: String, default: "" },
});
defineEmits(["update:modelValue"]);

const shown = ref(false);
const caps = ref(false);
const input = ref(null);
const readCaps = (e) => {
  if (typeof e.getModifierState === "function") caps.value = e.getModifierState("CapsLock");
};
defineExpose({ focus: () => input.value?.focus() });
</script>

<style scoped>
.pf {
  position: relative;
}
.pf-input {
  padding-right: 52px;
}
.pf-eye {
  position: absolute;
  top: 50%;
  right: 6px;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: var(--ink-soft);
  cursor: pointer;
  transform: translateY(-50%);
}
.pf-eye:hover {
  color: var(--ink);
  background: var(--ivory);
}
.pf-eye:focus-visible {
  outline: 2px solid var(--leaf-deep);
}
.pf-eye svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.pf-caps {
  min-height: 19px;
  margin-top: -2px;
  color: #8a5a12;
  visibility: hidden;
}
.pf-caps.on {
  visibility: visible;
}
</style>
