<!-- A pill of choices whose highlight slides to the picked one (a radio group).
     options: [{ key, label }]; v-model is the picked key.
     Arrow keys / Home / End move along it; it scrolls inside itself on small screens. -->
<template>
  <div class="cs-scroll">
    <div ref="root" class="cs" role="radiogroup" :aria-label="label" :style="{ '--n': options.length, '--i': activeIndex, '--min': `${minWidth}px` }" @keydown="onKey">
      <span class="cs-thumb" aria-hidden="true"></span>
      <button
        v-for="(o, i) in options"
        :key="o.key"
        type="button"
        role="radio"
        class="cs-opt"
        :class="{ on: i === activeIndex }"
        :aria-checked="i === activeIndex"
        :tabindex="i === activeIndex ? 0 : -1"
        @click="choose(o.key)"
      >{{ o.label }}</button>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, ref } from "vue";

const props = defineProps({
  modelValue: { type: String, default: "" },
  options: { type: Array, required: true },
  // What the choices are, for screen readers: "Period", "Group by"
  label: { type: String, required: true },
  // Narrowest a choice gets, so short words still make an even pill
  minWidth: { type: Number, default: 84 },
});
const emit = defineEmits(["update:modelValue"]);

const root = ref(null);
const activeIndex = computed(() => Math.max(0, props.options.findIndex((o) => o.key === props.modelValue)));

function choose(key) {
  emit("update:modelValue", key);
}

function onKey(e) {
  const n = props.options.length;
  const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
  const next = step === undefined ? { Home: 0, End: n - 1 }[e.key] : (activeIndex.value + step + n) % n;
  if (next === undefined) return;
  e.preventDefault();
  choose(props.options[next].key);
  nextTick(() => root.value?.querySelectorAll(".cs-opt")[next]?.focus());
}
</script>

<style scoped>
.cs-scroll {
  max-width: 100%;
  overflow-x: auto;
  scrollbar-width: none;
  border-radius: 999px;
}
.cs-scroll::-webkit-scrollbar {
  display: none;
}
.cs {
  position: relative;
  display: grid;
  grid-template-columns: repeat(var(--n), minmax(var(--min), 1fr));
  padding: 4px;
  border: 1px solid var(--v-border-strong);
  border-radius: 999px;
  background: var(--v-subtle);
  isolation: isolate;
}
.cs-thumb {
  position: absolute;
  z-index: -1;
  top: 4px;
  bottom: 4px;
  left: 4px;
  width: calc((100% - 8px) / var(--n));
  border-radius: 999px;
  background: var(--bc-accent);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.18);
  transform: translateX(calc(var(--i) * 100%));
  transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}
.cs-opt {
  min-height: 34px;
  padding: 6px 12px;
  border: 0;
  border-radius: 999px;
  background: none;
  font-family: "Poppins", sans-serif;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-text-2);
  white-space: nowrap;
  cursor: pointer;
  transition: color 0.2s;
}
.cs-opt:hover:not(.on) {
  color: var(--v-text);
}
.cs-opt.on {
  color: var(--bc-accent-ink, #fff);
  font-weight: 600;
}
.cs-opt:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 1px;
}
@media (prefers-reduced-motion: reduce) {
  .cs-thumb,
  .cs-opt {
    transition: none;
  }
}
</style>
