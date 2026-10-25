<template>
  <span ref="root" class="it" @mouseenter="open = true" @mouseleave="open = false">
    <button
      type="button"
      class="it-btn"
      :aria-label="`About: ${label}`"
      :aria-expanded="open ? 'true' : 'false'"
      :aria-describedby="open ? id : undefined"
      @click.prevent.stop="open = !open"
      @focus="open = true"
      @blur="open = false"
      @keydown.esc="open = false"
    >
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v5" /><circle cx="12" cy="7.8" r="0.6" /></svg>
    </button>
    <span v-show="open" :id="id" role="tooltip" class="it-tip"><slot /></span>
  </span>
</template>

<script setup>
// A small (i) next to a setting. Opens on hover, keyboard focus or tap; Esc or
// a tap elsewhere closes it. The text is the same for every way of opening.
import { onBeforeUnmount, onMounted, ref } from "vue";

defineProps({ label: { type: String, required: true } });

const id = `it-${Math.random().toString(36).slice(2, 9)}`;
const open = ref(false);
const root = ref(null);
const closeOutside = (e) => {
  if (open.value && root.value && !root.value.contains(e.target)) open.value = false;
};
onMounted(() => document.addEventListener("click", closeOutside));
onBeforeUnmount(() => document.removeEventListener("click", closeOutside));
</script>

<style scoped>
.it {
  position: relative;
  display: inline-flex;
  vertical-align: middle;
}
/* 28px visible, with padding the tap area reaches 40px */
.it-btn {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  margin: -6px -4px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: #94a3b8;
  cursor: help;
}
.it-btn:hover,
.it-btn[aria-expanded="true"] {
  color: #5b4fc9;
  background: #f1efff;
}
.it-btn:focus-visible {
  outline: 2px solid #7c6ff7;
  outline-offset: 1px;
}
.it-btn svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
}
.it-tip {
  position: absolute;
  z-index: 30;
  top: calc(100% + 8px);
  left: 50%;
  width: min(300px, 80vw);
  padding: 10px 12px;
  border-radius: 10px;
  background: #1e2739;
  color: #f8fafc;
  font-size: 12.5px;
  font-weight: 400;
  line-height: 1.5;
  text-transform: none;
  letter-spacing: normal;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
  transform: translateX(-50%);
}
.it-tip::before {
  content: "";
  position: absolute;
  top: -5px;
  left: 50%;
  width: 10px;
  height: 10px;
  background: #1e2739;
  transform: translateX(-50%) rotate(45deg);
}
@media (max-width: 640px) {
  .it-tip {
    left: auto;
    right: -8px;
    transform: none;
  }
  .it-tip::before {
    left: auto;
    right: 14px;
    transform: rotate(45deg);
  }
}
</style>
