<!-- How many of each flower colour are in stock (shown when 2+ colours are
     picked). The product's stock is the total of these. -->
<template>
  <div class="cs" role="group" :aria-labelledby="labelId">
    <div class="cs-head">
      <span :id="labelId" class="cs-label">Stock per colour</span>
      <span class="cs-total">Total in stock: <strong>{{ total }}</strong></span>
    </div>
    <ul class="cs-list">
      <li v-for="(c, i) in modelValue" :key="c.value" class="cs-row">
        <span class="cs-swatch" :style="{ '--sw': colorMeta(c.value)?.hex }" aria-hidden="true"></span>
        <label :for="`${labelId}-${i}`" class="cs-name">{{ colorName(c) || "Other" }}</label>
        <input
          :id="`${labelId}-${i}`"
          :value="c.quantity ?? ''"
          type="number"
          min="0"
          step="1"
          inputmode="numeric"
          class="fx-input cs-input"
          :class="{ invalid: !!error && !validQty(c.quantity) }"
          :aria-label="`${colorName(c) || 'Other'} in stock`"
          @input="setQty(i, $event.target.value)"
        />
      </li>
    </ul>
    <small v-if="error" class="fx-error">{{ error }}</small>
    <small v-else class="cs-help">Each colour sells out on its own. Customers pick a colour when they order.</small>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { colorMeta, colorName, totalOf } from "../../../utils/flowerColors.js";

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  error: { type: String, default: "" },
});
const emit = defineEmits(["update:modelValue"]);

const labelId = `cs-${Math.random().toString(36).slice(2, 8)}`;
const total = computed(() => totalOf(props.modelValue));
const validQty = (q) => q !== "" && q !== null && Number.isInteger(Number(q)) && Number(q) >= 0;

function setQty(index, raw) {
  const quantity = raw === "" ? "" : Math.max(0, Math.floor(Number(raw)));
  emit(
    "update:modelValue",
    props.modelValue.map((c, i) => (i === index ? { ...c, quantity } : c)),
  );
}
</script>

<style scoped>
.cs {
  display: grid;
  gap: 10px;
}

.cs-head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: baseline;
  gap: 8px;
}

.cs-label {
  color: var(--v-text-2, var(--v-text));
  font-size: 13px;
  font-weight: 600;
}

.cs-total {
  color: var(--v-muted);
  font-size: 13px;
}

.cs-total strong {
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}

.cs-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.cs-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 6px 6px 12px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-subtle, transparent);
}

.cs-swatch {
  flex: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--sw);
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
}

.cs-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: var(--v-text);
  font-size: 13.5px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cs-input {
  flex: none;
  width: 92px;
  min-height: 40px;
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.cs-help {
  color: var(--v-muted);
}

/* Same look as the vendor inputs, without needing the shop stylesheet (the ERP uses this too) */
.fx-input {
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
</style>
