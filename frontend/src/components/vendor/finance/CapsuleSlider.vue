<!-- Segmented control: a row of choices in one rounded track; the picked one
     sits on a raised tile that slides over to it (a radio group).
     options: [{ key, label }]; v-model is the picked key.
     Arrow keys / Home / End move along it; it scrolls inside itself on small screens. -->
<template>
  <div class="cs-scroll">
    <div ref="root" class="cs" role="radiogroup" :aria-label="label" @keydown="onKey">
      <span class="cs-thumb" :class="{ ready: thumb.ready }" :style="{ width: `${thumb.width}px`, transform: `translateX(${thumb.left}px)` }" aria-hidden="true"></span>
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
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: String, default: "" },
  options: { type: Array, required: true },
  // What the choices are, for screen readers: "Period", "Group by"
  label: { type: String, required: true },
});
const emit = defineEmits(["update:modelValue"]);

const root = ref(null);
const activeIndex = computed(() => Math.max(0, props.options.findIndex((o) => o.key === props.modelValue)));

// Each choice is as wide as its words, so the tile is measured from the picked button.
// It only starts sliding after the first measure, so it doesn't fly in on load.
const thumb = reactive({ left: 0, width: 0, ready: false });
function measure() {
  const btn = root.value?.querySelectorAll(".cs-opt")[activeIndex.value];
  if (!btn) return;
  thumb.left = btn.offsetLeft;
  thumb.width = btn.offsetWidth;
  if (!thumb.ready) requestAnimationFrame(() => (thumb.ready = true));
}
let observer = null;
onMounted(() => {
  measure();
  // Fonts loading or the page resizing changes the widths
  if (typeof ResizeObserver !== "undefined") {
    observer = new ResizeObserver(measure);
    observer.observe(root.value);
  }
  document.fonts?.ready?.then(measure);
});
onBeforeUnmount(() => observer?.disconnect());
watch(() => [activeIndex.value, props.options.map((o) => o.label).join("|")], () => nextTick(measure));

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
  border-radius: 14px;
}
.cs-scroll::-webkit-scrollbar {
  display: none;
}
.cs {
  /* Light: grey track, white tile. Dark values below. */
  --cs-track: var(--v-subtle-2);
  --cs-border: var(--v-border);
  --cs-thumb: var(--v-surface);
  --cs-thumb-shadow: 0 1px 2px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(15, 23, 42, 0.1);
  position: relative;
  display: inline-flex;
  gap: 2px;
  padding: 5px;
  border: 1px solid var(--cs-border);
  border-radius: 14px;
  background: var(--cs-track);
  isolation: isolate;
}
:global(html[data-vendor-theme="dark"]) .cs,
:global(html[data-admin-theme="dark"]) .cs {
  --cs-track: var(--v-bg);
  --cs-border: var(--v-border-strong);
  --cs-thumb: var(--v-raised);
  --cs-thumb-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04), 0 1px 2px rgba(0, 0, 0, 0.35);
}
.cs-thumb {
  position: absolute;
  z-index: -1;
  top: 5px;
  bottom: 5px;
  left: 0;
  border-radius: 10px;
  background: var(--cs-thumb);
  box-shadow: var(--cs-thumb-shadow);
}
.cs-thumb.ready {
  transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1), width 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}
.cs-opt {
  min-height: 36px;
  padding: 7px 16px;
  border: 0;
  border-radius: 10px;
  background: none;
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  font-weight: 500;
  color: var(--v-muted);
  white-space: nowrap;
  cursor: pointer;
  transition: color 0.2s;
}
.cs-opt:hover:not(.on) {
  color: var(--v-text);
}
.cs-opt.on {
  color: var(--v-text);
  font-weight: 600;
}
.cs-opt:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 1px;
}
@media (prefers-reduced-motion: reduce) {
  .cs-thumb.ready,
  .cs-opt {
    transition: none;
  }
}
</style>
