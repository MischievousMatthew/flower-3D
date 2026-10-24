<template>
  <div class="ti" :class="{ disabled, focus }" @click="input?.focus()">
    <span v-for="(t, i) in modelValue" :key="t" class="ti-tag">
      {{ t }}
      <button v-if="!disabled" type="button" :aria-label="`Remove ${t}`" @click.stop="remove(i)">×</button>
    </span>
    <input
      v-if="!disabled && modelValue.length < max"
      ref="input"
      v-model="draft"
      class="ti-in"
      type="text"
      :placeholder="modelValue.length ? '' : placeholder"
      :aria-label="label"
      maxlength="40"
      @keydown.enter.prevent="add()"
      @keydown.,.prevent="add()"
      @keydown.backspace="!draft && modelValue.length && remove(modelValue.length - 1)"
      @focus="focus = true"
      @blur="add(); focus = false"
    />
  </div>
  <!-- Suggestions while typing; mousedown keeps the focus in the box -->
  <div v-if="!disabled && focus && open.length" class="ti-suggest">
    <button v-for="s in open" :key="s" type="button" @mousedown.prevent="pick(s)">+ {{ s }}</button>
  </div>
</template>

<script setup>
// Short words (flowers, colors, occasions) typed and kept as chips: Enter or a
// comma adds one, Backspace on an empty box removes the last. Colors come from
// --ti-* variables, falling back to the vendor --v-* tokens.
import { computed, ref } from "vue";

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  suggestions: { type: Array, default: () => [] },
  placeholder: { type: String, default: "Type and press Enter" },
  label: { type: String, default: "" },
  max: { type: Number, default: 12 },
  disabled: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue"]);

const input = ref(null);
const draft = ref("");
const focus = ref(false);

const has = (t) => props.modelValue.some((x) => x.toLowerCase() === t.toLowerCase());
const open = computed(() => {
  const q = draft.value.trim().toLowerCase();
  return props.suggestions.filter((s) => !has(s) && (!q || s.toLowerCase().includes(q))).slice(0, 8);
});

function add(value = draft.value) {
  const t = value.trim().replace(/\s+/g, " ").slice(0, 40);
  draft.value = "";
  if (!t || has(t) || props.modelValue.length >= props.max) return;
  emit("update:modelValue", [...props.modelValue, t]);
}
const pick = (s) => add(s);
const remove = (i) => emit("update:modelValue", props.modelValue.filter((_, j) => j !== i));
</script>

<style scoped>
.ti {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  min-height: 44px;
  padding: 7px 9px;
  border: 1px solid var(--ti-border, var(--v-border-strong));
  border-radius: var(--ti-radius, 12px);
  background: var(--ti-bg, var(--v-input));
  cursor: text;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.ti.focus {
  border-color: var(--ti-accent, var(--bc-accent));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--ti-accent, var(--bc-accent)) 18%, transparent);
}
.ti.disabled {
  min-height: 0;
  padding: 0;
  border: 0;
  background: none;
  cursor: default;
}
.ti-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--ti-tag-bg, var(--bc-accent-soft));
  color: var(--ti-tag-text, var(--bc-accent-deep));
  font-size: 12.5px;
  font-weight: 500;
  line-height: 1.3;
}
.ti-tag button {
  position: relative;
  padding: 0 0 0 2px;
  border: 0;
  background: none;
  color: inherit;
  font-size: 15px;
  line-height: 1;
  cursor: pointer;
  opacity: 0.7;
}
/* Invisible 44px hit area around the small × */
.ti-tag button::after {
  content: "";
  position: absolute;
  inset: -14px -12px;
}
.ti-tag button:hover {
  opacity: 1;
}
/* Beats page-wide input styles (.sp-field input and friends) */
.ti input.ti-in {
  flex: 1;
  min-width: 120px;
  height: auto;
  min-height: 0;
  margin: 0;
  padding: 3px 2px;
  border: 0;
  border-radius: 0;
  outline: 0;
  box-shadow: none;
  background: none;
  color: var(--ti-text, var(--v-text));
  font: inherit;
  font-size: 13px;
}
.ti-suggest {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}
.ti-suggest button {
  padding: 4px 10px;
  border: 1px dashed var(--ti-border, var(--v-border-strong));
  border-radius: 999px;
  background: none;
  color: var(--ti-muted, var(--v-muted));
  font: inherit;
  font-size: 12px;
  cursor: pointer;
}
.ti-suggest button:hover {
  border-color: var(--ti-accent, var(--bc-accent));
  color: var(--ti-accent, var(--bc-accent));
}
</style>
