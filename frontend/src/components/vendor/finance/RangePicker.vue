<template>
  <div class="rp">
    <select :value="financeRange.preset" class="sp-select rp-preset" aria-label="Period" @change="pick($event.target.value)">
      <option v-for="p in PRESETS" :key="p.key" :value="p.key">{{ p.label }}</option>
    </select>
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
import { PRESETS, financeRange, presetRange, setRange, ymd } from "./financeUi";

const emit = defineEmits(["change"]);
const today = ymd(new Date());

const label = computed(() => {
  const f = new Date(`${financeRange.from}T00:00:00`);
  const t = new Date(`${financeRange.to}T00:00:00`);
  const sameYear = f.getFullYear() === t.getFullYear();
  const opts = { month: "short", day: "numeric" };
  return `${f.toLocaleDateString("en-PH", sameYear ? opts : { ...opts, year: "numeric" })} – ${t.toLocaleDateString("en-PH", { ...opts, year: "numeric" })}`;
});

function pick(key) {
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
}
.rp-preset {
  width: auto;
  min-width: 150px;
  padding: 8px 12px;
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
  .rp-preset,
  .rp-date {
    flex: 1;
    min-width: 0;
    width: auto;
  }
}
</style>
