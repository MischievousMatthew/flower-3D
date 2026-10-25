<template>
  <div class="pf">
    <input
      :id="id"
      ref="input"
      :value="modelValue"
      :type="shown ? 'text' : 'password'"
      class="af-input pf-input"
      :autocomplete="autocomplete"
      :placeholder="placeholder || ' '"
      :aria-invalid="invalid ? 'true' : 'false'"
      :aria-describedby="describedby || undefined"
      v-bind="$attrs"
      @input="$emit('update:modelValue', $event.target.value)"
      @keyup="readCaps"
      @keydown="readCaps"
      @blur="caps = false"
    />
    <div class="pf-tools">
      <!-- Caps Lock on: a small key icon inside the box, next to the eye -->
      <span v-if="caps" class="pf-caps" title="Caps Lock is on" aria-hidden="true">
        <svg viewBox="0 0 24 24"><rect x="2.5" y="2.5" width="19" height="19" rx="4" /><path d="M12 6.5 7 12h3v4h4v-4h3Z" /></svg>
      </span>
      <button type="button" class="pf-eye" :aria-label="shown ? 'Hide password' : 'Show password'" :aria-pressed="shown" @click="shown = !shown">
        <svg v-if="!shown" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
        <svg v-else viewBox="0 0 24 24"><path d="M3 3l18 18M10.6 5.1A9.7 9.7 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7c1.8 0 3.4-.6 4.7-1.4M9.9 9.9a3 3 0 0 0 4.2 4.2" /></svg>
      </button>
    </div>
    <span class="pf-sr" aria-live="polite">{{ caps ? "Caps Lock is on" : "" }}</span>
  </div>
</template>

<script setup>
// A password input with a show/hide toggle and a Caps Lock warning — the two
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
  padding-right: 88px;
}
.pf-tools {
  position: absolute;
  top: 50%;
  right: 6px;
  display: flex;
  align-items: center;
  gap: 2px;
  transform: translateY(-50%);
}
.pf-caps {
  display: grid;
  place-items: center;
  width: 28px;
  height: 40px;
  color: #1f2430;
}
.pf-caps svg {
  width: 20px;
  height: 20px;
}
.pf-caps rect {
  fill: currentColor;
}
.pf-caps path {
  fill: #fff;
}
.pf-eye {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: #1f2430;
  cursor: pointer;
}
.pf-eye:hover {
  background: rgba(31, 36, 48, 0.06);
}
.pf-eye:focus-visible {
  outline: 2px solid var(--leaf-deep);
}
.pf-eye svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.pf-sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
</style>
