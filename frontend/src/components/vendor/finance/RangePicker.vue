<template>
  <div class="rp">
    <!-- Capsule slider: the highlight slides to the picked period -->
    <CapsuleSlider :model-value="financeRange.preset" :options="options" label="Period" @update:model-value="pick" />
    <template v-if="financeRange.preset === 'custom'">
      <input :value="financeRange.from" type="date" class="sp-input rp-date" aria-label="From" :max="financeRange.to" @change="custom('from', $event.target.value)" />
      <span class="rp-to">to</span>
      <input :value="financeRange.to" type="date" class="sp-input rp-date" aria-label="To" :min="financeRange.from" :max="today" @change="custom('to', $event.target.value)" />
    </template>
    <span v-else class="rp-label">{{ label }}</span>
  </div>
</template>

<script setup>
import { computed } from "vue";
import CapsuleSlider from "./CapsuleSlider.vue";
import { PRESETS, financeRange, presetRange, rangeLabel, setRange, ymd } from "./financeUi";

const emit = defineEmits(["change"]);
const today = ymd(new Date());

const options = PRESETS.map((p) => ({ key: p.key, label: p.short || p.label }));
const label = computed(() => rangeLabel(financeRange));

function pick(key) {
  if (key === financeRange.preset && key !== "custom") return;
  setRange(key === "custom" ? { preset: "custom" } : { preset: key, ...presetRange(key) });
  if (key !== "custom") emit("change");
}
function custom(field, value) {
  if (!value) return;
  setRange({ preset: "custom", [field]: value });
  if (financeRange.from <= financeRange.to) emit("change");
}
</script>

<style scoped>
.rp {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  min-width: 0;
  max-width: 100%;
}
.rp-date {
  width: 150px;
  padding: 8px 10px;
}
.rp-to {
  font-size: 12.5px;
  color: var(--v-muted);
}
.rp-label {
  font-size: 12.5px;
  color: var(--v-muted);
  white-space: nowrap;
}
@media (max-width: 640px) {
  .rp {
    width: 100%;
  }
  .rp-date {
    flex: 1;
    min-width: 0;
    width: auto;
  }
}
</style>
