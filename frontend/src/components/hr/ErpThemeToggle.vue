<template>
  <div class="et" role="radiogroup" aria-label="Appearance">
    <button
      v-for="o in OPTIONS"
      :key="o.value"
      type="button"
      role="radio"
      class="et-opt"
      :class="{ on: mode === o.value }"
      :aria-checked="mode === o.value"
      :title="o.title"
      @click="setMode(o.value)"
    >
      <span v-html="o.icon"></span>
      <span class="et-label">{{ o.label }}</span>
    </button>
  </div>
</template>

<script setup>
import { useErpTheme } from "../../composables/useErpTheme";

const { mode, setMode } = useErpTheme();

const svg = (body) => `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${body}</svg>`;
const OPTIONS = [
  { value: "light", label: "Light", title: "Light mode", icon: svg('<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>') },
  { value: "dark", label: "Dark", title: "Dark mode", icon: svg('<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>') },
  { value: "system", label: "Auto", title: "Match this device", icon: svg('<rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>') },
];
</script>

<style scoped>
.et {
  display: inline-flex;
  gap: 2px;
  padding: 3px;
  border: 1px solid var(--v-border);
  border-radius: 11px;
  background: var(--v-subtle);
  font-family: "Poppins", sans-serif;
}
.et-opt {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border: 0;
  border-radius: 8px;
  background: none;
  color: var(--v-muted);
  font: inherit;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.et-opt :deep(svg) {
  display: block;
}
.et-opt:hover {
  color: var(--v-text);
}
.et-opt.on {
  background: var(--v-surface);
  color: var(--v-text);
  box-shadow: 0 1px 3px var(--v-shadow);
}
.et-opt:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 1px;
}
@media (max-width: 560px) {
  .et-label {
    display: none;
  }
}
</style>
