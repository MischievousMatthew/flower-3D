<template>
  <nav class="cs-steps" aria-label="Bouquet steps" :style="{ gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))` }">
    <button
      v-for="step in steps"
      :key="step.key"
      type="button"
      class="cs-step"
      :class="{ on: step.key === active, done: done.includes(step.key) }"
      :aria-current="step.key === active ? 'step' : undefined"
      @click="$emit('select', step.key)"
    >
      <span class="cs-num">{{ step.number }}</span>
      <span class="cs-label">{{ step.label }}</span>
    </button>
  </nav>
</template>

<script setup>
defineProps({
  steps: { type: Array, required: true },
  active: { type: String, required: true },
  done: { type: Array, default: () => [] },
});
defineEmits(["select"]);
</script>

<style scoped>
.cs-steps {
  display: grid;
  border-bottom: 1px solid var(--line);
}

.cs-step {
  display: grid;
  justify-items: center;
  gap: 3px;
  padding: 12px 2px 10px;
  border: 0;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  background: none;
  color: var(--ink-soft);
  font-family: var(--font-sans);
  font-size: 11.5px;
  cursor: pointer;
  transition: color 0.2s ease, border-color 0.2s ease;
}

.cs-num {
  font-family: var(--font-display);
  font-size: 18px;
  line-height: 1;
  color: rgba(30, 27, 24, 0.35);
  transition: color 0.2s ease;
}

.cs-step:hover {
  color: var(--ink);
}

.cs-step.done .cs-num {
  color: var(--ink);
}

.cs-step.on {
  color: var(--ink);
  border-bottom-color: var(--rosewood);
}

.cs-step.on .cs-num {
  color: var(--rosewood-dark);
}

.cs-step:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: -2px;
}

.cs-label {
  white-space: nowrap;
}
</style>
