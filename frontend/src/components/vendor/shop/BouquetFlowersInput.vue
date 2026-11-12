<!-- The flowers in a bouquet and how many stems of each: shown to customers
     ("What's inside") and used by procurement to work out how many stems to
     buy. Rows are [{ flower, color, count }]. -->
<template>
  <div class="bf" role="group" :aria-labelledby="labelId">
    <div class="bf-head">
      <span :id="labelId" class="bf-label">Flowers in this bouquet</span>
      <span v-if="stems" class="bf-total">{{ stems }} {{ stems === 1 ? "stem" : "stems" }} in all</span>
    </div>
    <p class="bf-help">
      List each flower, how many pieces go into one bouquet, and what you paid for one piece (your buying cost, not your
      selling price). Procurement uses this to know how many stems to buy. Customers never see the costs.
    </p>

    <ul v-if="modelValue.length" class="bf-list">
      <li v-for="(row, i) in modelValue" :key="i" class="bf-row">
        <label class="bf-field bf-flower">
          <span>Flower</span>
          <input
            :value="row.flower"
            class="fx-input"
            :class="{ invalid: !!error && !String(row.flower || '').trim() }"
            maxlength="80"
            list="bf-flower-names"
            placeholder="e.g. Rose"
            @input="update(i, { flower: $event.target.value })"
          />
        </label>
        <label class="bf-field bf-color">
          <span>Colour <small>(optional)</small></span>
          <input
            :value="row.color"
            class="fx-input"
            maxlength="40"
            list="bf-color-names"
            placeholder="e.g. Red"
            @input="update(i, { color: $event.target.value })"
          />
        </label>
        <label class="bf-field bf-count">
          <span>Pieces</span>
          <input
            :value="row.count ?? ''"
            type="number"
            min="1"
            max="1000"
            step="1"
            inputmode="numeric"
            class="fx-input"
            :class="{ invalid: !!error && !(Number(row.count) >= 1) }"
            @input="update(i, { count: $event.target.value === '' ? '' : Math.floor(Number($event.target.value)) })"
          />
        </label>
        <label class="bf-field bf-cost">
          <span>Cost per piece</span>
          <span class="bf-peso">
            <i aria-hidden="true">₱</i>
            <input
              :value="row.cost ?? ''"
              type="number"
              min="0"
              step="0.01"
              inputmode="decimal"
              class="fx-input"
              :class="{ invalid: !!error && row.cost !== '' && row.cost != null && !(Number(row.cost) >= 0) }"
              placeholder="0.00"
              :aria-label="`What one ${row.flower || 'flower'} cost you`"
              @input="update(i, { cost: $event.target.value })"
            />
          </span>
        </label>
        <button type="button" class="bf-remove" :aria-label="`Remove ${row.flower || 'this flower'}`" @click="remove(i)">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
        </button>
        <small v-if="rowCost(row) !== null" class="bf-line">
          {{ row.count }} × {{ peso(row.cost) }} = <b>{{ peso(rowCost(row)) }}</b>
        </small>
      </li>
    </ul>

    <div v-if="cost > 0" class="bf-sum">
      <span>Flowers cost <b>{{ peso(cost) }}</b> per bouquet<template v-if="unpriced"> · {{ unpriced }} {{ unpriced === 1 ? "flower has" : "flowers have" }} no cost yet</template></span>
      <button
        v-if="costValue !== undefined && Math.abs(Number(costValue || 0) - cost) >= 0.005"
        type="button"
        class="bf-use"
        @click="emit('use-cost', Math.round(cost * 100) / 100)"
      >
        Use as your cost
      </button>
    </div>

    <button type="button" class="bf-add" :disabled="modelValue.length >= 30" @click="add">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
      Add a flower
    </button>
    <small v-if="error" class="fx-error">{{ error }}</small>

    <datalist id="bf-flower-names">
      <option v-for="f in FLOWER_NAMES" :key="f" :value="f" />
    </datalist>
    <datalist id="bf-color-names">
      <option v-for="c in COLOR_NAMES" :key="c" :value="c" />
    </datalist>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { FLOWER_COLORS } from "../../../utils/flowerColors.js";
import { flowersCost, rowCost } from "../../../utils/bouquetComposition.js";

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  error: { type: String, default: "" },
  // The product's own "your cost" field; when given, a button offers to fill it with the flowers' cost
  costValue: { type: [Number, String, null], default: undefined },
});
const emit = defineEmits(["update:modelValue", "use-cost"]);

const cost = computed(() => flowersCost(props.modelValue));
const unpriced = computed(
  () => props.modelValue.filter((r) => String(r.flower || "").trim() && (r.cost === "" || r.cost == null)).length,
);
const peso = (v) => `₱${Number(v || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const labelId = `bf-${Math.random().toString(36).slice(2, 8)}`;
// Suggestions only: any flower or colour can be typed
const FLOWER_NAMES = ["Rose", "Tulip", "Sunflower", "Lily", "Carnation", "Chrysanthemum", "Gerbera", "Orchid", "Hydrangea", "Peony", "Baby's breath", "Eucalyptus", "Statice", "Aster", "Daisy", "Lisianthus"];
const COLOR_NAMES = FLOWER_COLORS.filter((c) => c.value !== "other").map((c) => c.label);

const stems = computed(() => props.modelValue.reduce((sum, r) => sum + (Number(r.count) > 0 ? Number(r.count) : 0), 0));

const update = (index, patch) =>
  emit("update:modelValue", props.modelValue.map((r, i) => (i === index ? { ...r, ...patch } : r)));
const add = () => emit("update:modelValue", [...props.modelValue, { flower: "", color: "", count: 1, cost: "" }]);
const remove = (index) => emit("update:modelValue", props.modelValue.filter((_, i) => i !== index));
</script>

<style scoped>
.bf {
  display: grid;
  gap: 10px;
}

.bf-head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: baseline;
  gap: 8px;
}

.bf-label {
  color: var(--v-text-2, var(--v-text));
  font-size: 13px;
  font-weight: 600;
}

.bf-total {
  color: var(--v-muted);
  font-size: 13px;
  font-variant-numeric: tabular-nums;
}

.bf-help {
  margin: 0;
  color: var(--v-muted);
  font-size: 12.5px;
  line-height: 1.5;
}

.bf-list {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.bf-row {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 1.3fr) 84px 124px 44px;
  gap: 8px;
  align-items: end;
  padding: 10px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-subtle, transparent);
}

.bf-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.bf-field > span {
  color: var(--v-text-2, var(--v-text));
  font-size: 12px;
  font-weight: 500;
}

.bf-field small {
  color: var(--v-faint, var(--v-muted));
  font-weight: 400;
}

.bf-count .fx-input,
.bf-cost .fx-input {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

/* ₱ sign inside the cost box */
.bf-peso {
  position: relative;
  display: block;
}

.bf-peso i {
  position: absolute;
  left: 11px;
  top: 50%;
  color: var(--v-muted);
  font-size: 13px;
  font-style: normal;
  transform: translateY(-50%);
  pointer-events: none;
}

.bf-peso .fx-input {
  padding-left: 24px;
}

.bf-line {
  grid-column: 1 / -1;
  color: var(--v-muted);
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}

.bf-line b {
  color: var(--v-text);
  font-weight: 600;
}

.bf-sum {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 8px 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--v-green-soft, var(--v-subtle));
  color: var(--v-text-2, var(--v-text));
  font-size: 13px;
}

.bf-sum b {
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}

.bf-use {
  min-height: 36px;
  padding: 0 12px;
  border: 1px solid var(--v-border-strong);
  border-radius: 10px;
  background: var(--v-surface);
  color: var(--v-text);
  font: 500 12.5px "Poppins", sans-serif;
  cursor: pointer;
}

.bf-use:hover {
  border-color: var(--bc-accent, var(--v-green-text));
}

.bf-use:focus-visible {
  outline: 2px solid var(--bc-accent, var(--v-green-text));
  outline-offset: 2px;
}

.bf-remove {
  display: grid;
  place-items: center;
  width: 44px;
  height: 42px;
  padding: 0;
  border: 1px solid transparent;
  border-radius: 10px;
  background: none;
  color: var(--v-muted);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.bf-remove:hover {
  background: var(--v-red-soft, rgba(220, 38, 38, 0.08));
  color: var(--v-red-text);
}

.bf-remove svg,
.bf-add svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
}

.bf-add {
  display: inline-flex;
  align-items: center;
  justify-self: start;
  gap: 6px;
  min-height: 40px;
  padding: 0 14px;
  border: 1px dashed var(--v-border-strong);
  border-radius: 10px;
  background: none;
  color: var(--v-text);
  font: 500 13px "Poppins", sans-serif;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}

.bf-add:hover:not(:disabled) {
  border-color: var(--bc-accent, var(--v-green-text));
  background: var(--v-subtle, transparent);
}

.bf-add:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.bf-remove:focus-visible,
.bf-add:focus-visible {
  outline: 2px solid var(--bc-accent, var(--v-green-text));
  outline-offset: 2px;
}

/* Same look as the vendor inputs, without needing the shop stylesheet (the ERP uses this too) */
.fx-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--v-border-strong);
  border-radius: 11px;
  background: var(--v-input, var(--v-surface));
  color: var(--v-text);
  font-family: "Poppins", sans-serif;
  font-size: 13.5px;
  outline: none;
  box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.fx-input:focus {
  border-color: var(--bc-accent, var(--v-green-text));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent, #4db173) 15%, transparent);
}

.fx-input.invalid {
  border-color: #fc8181;
}

.fx-error {
  color: var(--v-red-text);
  font-size: 12px;
}

/* Narrow: flower and colour on top, pieces / cost / remove below */
@media (max-width: 720px) {
  .bf-row {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 44px;
  }

  .bf-flower {
    grid-column: 1 / 2;
  }

  .bf-color {
    grid-column: 2 / -1;
  }

  .bf-count {
    grid-column: 1 / 2;
  }

  .bf-cost {
    grid-column: 2 / 3;
  }
}
</style>
