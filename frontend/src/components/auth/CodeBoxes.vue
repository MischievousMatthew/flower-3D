<template>
  <div class="cb" :class="{ bad: invalid, shake }" role="group" :aria-label="label" @paste.prevent="paste">
    <input
      v-for="i in 6"
      :key="i"
      :ref="(el) => (boxes[i - 1] = el)"
      :value="vals[i - 1]"
      class="cb-box"
      :type="masked ? 'password' : 'text'"
      inputmode="numeric"
      maxlength="6"
      :autocomplete="i === 1 ? 'one-time-code' : 'off'"
      :aria-label="`Digit ${i} of 6`"
      :aria-invalid="invalid ? 'true' : 'false'"
      :disabled="disabled"
      @input="type(i - 1, $event)"
      @keydown.backspace="back(i - 1, $event)"
      @keydown.left.prevent="boxes[i - 2]?.focus()"
      @keydown.right.prevent="boxes[i]?.focus()"
      @focus="$event.target.select()"
    />
  </div>
</template>

<script setup>
// Six boxes for a one-time code. Typing moves along, backspace moves back,
// pasting fills them all, and "complete" fires when the sixth digit lands.
import { reactive, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: String, default: "" },
  masked: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  label: { type: String, default: "6-digit code" },
});
const emit = defineEmits(["update:modelValue", "complete"]);

const boxes = [];
// The boxes' own copy, updated at once so fast typing never reads a stale value
const vals = reactive(Array(6).fill(""));
const shake = ref(false);
watch(
  () => props.modelValue,
  (v) => {
    if (v === vals.join("")) return;
    for (let i = 0; i < 6; i++) vals[i] = v[i] ?? "";
  },
  { immediate: true },
);

function publish() {
  const v = vals.join("");
  emit("update:modelValue", v);
  if (/^\d{6}$/.test(v)) emit("complete", v);
}
function fillFrom(i, digits) {
  digits.split("").forEach((d, k) => {
    if (i + k < 6) vals[i + k] = d;
  });
  publish();
  boxes[Math.min(i + digits.length, 6) - 1]?.focus();
}
function type(i, e) {
  const digits = e.target.value.replace(/\D/g, "");
  // A phone's code autofill (or a fast paste) drops the whole code into one box
  if (digits.length > 1) {
    e.target.value = vals[i];
    fillFrom(i, digits.slice(0, 6 - i));
    return;
  }
  vals[i] = digits;
  e.target.value = digits;
  publish();
  if (digits && i < 5) boxes[i + 1]?.focus();
}
function back(i, e) {
  if (!vals[i] && i > 0) {
    e.preventDefault();
    vals[i - 1] = "";
    publish();
    boxes[i - 1]?.focus();
  }
}
function paste(e) {
  const v = (e.clipboardData?.getData("text") ?? "").replace(/\D/g, "").slice(0, 6);
  if (!v) return;
  vals.fill("");
  fillFrom(0, v);
}
// A wrong code shakes once, so it's noticed without a red banner
watch(
  () => props.invalid,
  (bad) => {
    if (!bad || window.matchMedia?.("(prefers-reduced-motion: reduce)").matches) return;
    shake.value = true;
    setTimeout(() => (shake.value = false), 450);
  },
);
defineExpose({ focus: () => boxes[0]?.focus() });
</script>

<style scoped>
.cb {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 10px;
  max-width: 360px;
}
.cb-box {
  width: 100%;
  height: 52px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: #fff;
  font: 500 20px/1 var(--font-sans);
  text-align: center;
  color: var(--ink);
  caret-color: var(--leaf-deep);
}
.cb-box:focus {
  outline: none;
  border-color: var(--leaf-deep);
  box-shadow: 0 0 0 4px rgba(46, 122, 79, 0.12);
}
.bad .cb-box {
  border-color: rgba(46, 122, 79, 0.55);
  background: #f6fbf8;
}
.shake {
  animation: cb-shake 0.4s ease;
}
@keyframes cb-shake {
  20%,
  60% {
    transform: translateX(-6px);
  }
  40%,
  80% {
    transform: translateX(6px);
  }
}
@media (max-width: 400px) {
  .cb {
    gap: 6px;
  }
  .cb-box {
    height: 50px;
    font-size: 20px;
  }
}
</style>
