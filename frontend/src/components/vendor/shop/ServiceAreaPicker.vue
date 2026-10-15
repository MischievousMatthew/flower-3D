<template>
  <div class="sa" role="group" :aria-label="label">
    <section v-for="g in SERVICE_AREA_GROUPS" :key="g.province" class="sa-group">
      <div class="sa-head">
        <b>{{ g.province }}</b>
        <span class="sa-count">{{ countText(g) }}</span>
        <label class="sa-whole">
          <span class="sp-switch"><input type="checkbox" :checked="has(g.province)" :aria-label="`All of ${g.province}`" @change="toggleWhole(g)" /><span></span></span>
          All of {{ g.province }}
        </label>
      </div>
      <div v-if="!has(g.province)" class="sa-places">
        <button
          v-for="p in g.places"
          :key="p"
          type="button"
          class="sp-pick"
          :class="{ on: has(p) }"
          :aria-pressed="has(p)"
          @click="toggle(p)"
        >
          <span v-if="has(p)" class="sa-check" v-html="ICONS.check"></span>{{ p }}
        </button>
      </div>
    </section>
    <p v-if="unknown.length" class="sp-hint sa-note">
      No longer on the list and removed when you save: {{ unknown.join(", ") }}.
    </p>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { SHOP_ICONS as ICONS } from "./shopIcons";
import { SERVICE_AREA_GROUPS, joinServiceAreas, parseServiceAreas, unknownServiceAreas } from "../../../constants/serviceAreas";

// v-model is the stored string, e.g. "Cavite, Makati"
const model = defineModel({ type: String, default: "" });
defineProps({ label: { type: String, default: "Areas you deliver to" } });

const selected = computed(() => parseServiceAreas(model.value));
// Captured once: old free-text entries the picker can't represent
const unknown = unknownServiceAreas(model.value);

const has = (name) => selected.value.includes(name);
const set = (list) => (model.value = joinServiceAreas(list));

function toggle(place) {
  set(has(place) ? selected.value.filter((x) => x !== place) : [...selected.value, place]);
}
function toggleWhole(g) {
  const rest = selected.value.filter((x) => x !== g.province && !g.places.includes(x));
  set(has(g.province) ? rest : [...rest, g.province]);
}
function countText(g) {
  if (has(g.province)) return "Everywhere";
  const n = g.places.filter(has).length;
  return n ? `${n} selected` : "None";
}
</script>

<style scoped>
.sa {
  display: grid;
  gap: 10px;
}
.sa-group {
  padding: 12px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-subtle);
}
.sa-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}
.sa-head b {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.sa-count {
  font-size: 12px;
  color: var(--v-muted);
}
.sa-whole {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
  font-size: 12.5px;
  color: var(--v-text-2);
  cursor: pointer;
}
.sa-places {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 10px;
}
.sa-places .sp-pick {
  padding: 5px 11px;
  font-size: 12px;
}
.sa-check {
  display: inline-flex;
}
.sa-check :deep(svg) {
  width: 12px;
  height: 12px;
}
.sa-note {
  margin: 0;
}
</style>
