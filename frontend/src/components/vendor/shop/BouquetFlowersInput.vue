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
      The flowers and how many go in one bouquet. Cost is what you paid for one (customers don't see it).
      <template v-if="flowers">Link a flower to your stock and selling a bouquet takes it from there.</template>
    </p>

    <ul v-if="flowerRows.length" class="bf-list">
      <li v-for="{ row, i } in flowerRows" :key="i" class="bf-row" :class="{ linked: !!row.product_id }">
        <label v-if="flowers" class="bf-field bf-link">
          <span>Take from stock <small>(optional)</small></span>
          <select
            :value="row.product_id || ''"
            class="fx-input"
            :aria-label="`Link ${row.flower || 'this flower'} to a single flower in stock`"
            @change="setLink(i, $event.target.value)"
          >
            <option value="">Not linked</option>
            <option v-for="f in singleFlowers" :key="f.id" :value="f.id">
              {{ f.name }}{{ f.color && !f.colors?.length ? ` (${f.color})` : "" }} · {{ f.stock }} in stock
            </option>
          </select>
        </label>
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
            :list="linkedColors(row).length ? `bf-colors-${i}` : 'bf-color-names'"
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
        <small v-if="linkOf(row)" class="bf-line bf-line-link">
          Each bouquet takes {{ row.count }} from <b>{{ linkOf(row).name }}</b>
          ({{ flowerStockFor(linkOf(row), row.color) }} {{ linkedColors(row).length && row.color ? `${row.color} ` : "" }}in stock)
          · enough for <b>{{ Math.floor(flowerStockFor(linkOf(row), row.color) / Math.max(1, Number(row.count) || 1)) }}</b>
        </small>
        <datalist v-if="linkedColors(row).length" :id="`bf-colors-${i}`">
          <option v-for="c in linkedColors(row)" :key="c.name" :value="c.name" />
        </datalist>
      </li>
    </ul>

    <button type="button" class="bf-add" :disabled="modelValue.length >= 30" @click="add">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
      Add a flower
    </button>

    <!-- Wrappers: sheets of the shop's wrappers (the same stock as 3D Bouquet Management and Inventory) -->
    <template v-if="flowers">
      <div class="bf-head bf-head-wrap">
        <span class="bf-label">Wrappers</span>
        <span v-if="sheets" class="bf-total">{{ sheets }} {{ sheets === 1 ? "sheet" : "sheets" }}</span>
      </div>
      <ul v-if="wrapperRows.length" class="bf-list">
        <li v-for="{ row, i } in wrapperRows" :key="i" class="bf-row bf-row-wrap linked">
          <label class="bf-field">
            <span>Wrapper</span>
            <select
              :value="row.product_id || ''"
              class="fx-input"
              :class="{ invalid: !!error && !row.product_id }"
              aria-label="Which wrapper"
              @change="setWrapper(i, $event.target.value)"
            >
              <option value="" disabled>Pick a wrapper</option>
              <option v-for="w in wrappers" :key="w.id" :value="w.id">{{ w.name }} · {{ w.stock }} {{ w.stock === 1 ? "sheet" : "sheets" }}</option>
            </select>
          </label>
          <label class="bf-field bf-count">
            <span>Sheets</span>
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
          <button type="button" class="bf-remove" :aria-label="`Remove ${row.flower || 'this wrapper'}`" @click="remove(i)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
          </button>
        </li>
      </ul>
      <button v-if="wrappers.length" type="button" class="bf-add" :disabled="modelValue.length >= 30" @click="addWrapper">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
        Add a wrapper
      </button>
      <small v-else class="bf-help">No wrappers in stock yet. Add one with its sheets in 3D Bouquet Management.</small>

      <!-- Fillers: bouquets' worth of the shop's fillers (the same stock as 3D Bouquet Management and Inventory) -->
      <div class="bf-head bf-head-wrap">
        <span class="bf-label">Fillers</span>
        <span v-if="fillerRows.length" class="bf-total">{{ fillerRows.length }} {{ fillerRows.length === 1 ? "filler" : "fillers" }}</span>
      </div>
      <ul v-if="fillerRows.length" class="bf-list">
        <li v-for="{ row, i } in fillerRows" :key="i" class="bf-row bf-row-wrap linked">
          <label class="bf-field">
            <span>Filler</span>
            <select
              :value="row.product_id || ''"
              class="fx-input"
              :class="{ invalid: !!error && !row.product_id }"
              aria-label="Which filler"
              @change="setMaterial(i, fillers, $event.target.value)"
            >
              <option value="" disabled>Pick a filler</option>
              <option v-for="f in fillers" :key="f.id" :value="f.id">{{ f.name }} · {{ f.stock }} left</option>
            </select>
          </label>
          <label class="bf-field bf-count">
            <span>Per bouquet</span>
            <input
              :value="row.count ?? ''"
              type="number"
              min="1"
              max="1000"
              step="1"
              inputmode="numeric"
              class="fx-input"
              :class="{ invalid: !!error && !(Number(row.count) >= 1) }"
              :aria-label="`How many ${row.flower || 'of this filler'} one bouquet uses`"
              @input="update(i, { count: $event.target.value === '' ? '' : Math.floor(Number($event.target.value)) })"
            />
          </label>
          <button type="button" class="bf-remove" :aria-label="`Remove ${row.flower || 'this filler'}`" @click="remove(i)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
          </button>
        </li>
      </ul>
      <!-- Worked out from each row: "With 40 left and 2 per bouquet, you can make 20." -->
      <small v-for="line in fillerLines" :key="line.i" class="bf-help" role="status">{{ line.text }}</small>
      <button v-if="fillers.length" type="button" class="bf-add" :disabled="modelValue.length >= 30" @click="addMaterial('filler', fillers)">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
        Add a filler
      </button>
      <small v-else class="bf-help">No counted fillers yet. In 3D Bouquet Management, fill in a filler's sacks to count its stock.</small>
    </template>

    <p v-if="linkedMake !== null" class="bf-linked-note" role="status">
      You can make <b>{{ linkedMake }} {{ linkedMake === 1 ? "bouquet" : "bouquets" }}</b> with the stock you linked.
    </p>

    <div v-if="cost > 0" class="bf-sum">
      <span>{{ wrapperRows.length || fillerRows.length ? "Flowers and materials cost" : "Flowers cost" }} <b>{{ peso(cost) }}</b> per bouquet<template v-if="unpriced"> · {{ unpriced }} {{ unpriced === 1 ? "flower has" : "flowers have" }} no cost yet</template></span>
      <button
        v-if="costValue !== undefined && Math.abs(Number(costValue || 0) - cost) >= 0.005"
        type="button"
        class="bf-use"
        @click="emit('use-cost', Math.round(cost * 100) / 100)"
      >
        Use as your cost
      </button>
    </div>

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
import { MATERIAL_KINDS, flowerStockFor, flowersCost, rowCost, stockFromLinks } from "../../../utils/bouquetComposition.js";

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  error: { type: String, default: "" },
  // The shop's single flowers a row can be linked to (linkable-flowers); null hides linking
  flowers: { type: Array, default: null },
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

// Flower, wrapper and filler rows live in one list; each keeps its index in it
const rowsOf = (test) => computed(() => props.modelValue.map((row, i) => ({ row, i })).filter(({ row }) => test(row)));
const flowerRows = rowsOf((r) => !MATERIAL_KINDS.includes(r.kind));
const wrapperRows = rowsOf((r) => r.kind === "wrapper");
const fillerRows = rowsOf((r) => r.kind === "filler");
const singleFlowers = computed(() => (props.flowers || []).filter((f) => !MATERIAL_KINDS.includes(f.kind)));
const wrappers = computed(() => (props.flowers || []).filter((f) => f.kind === "wrapper"));
const fillers = computed(() => (props.flowers || []).filter((f) => f.kind === "filler"));

/** A filler row: which of the shop's fillers, and how many of it one bouquet uses */
function addMaterial(kind, list) {
  const first = list[0];
  emit("update:modelValue", [
    ...props.modelValue,
    { kind, flower: first?.name || "", color: "", count: 1, cost: first?.cost ?? "", product_id: first?.id ?? null },
  ]);
}
/** Under the filler rows: what each one's stock and amount make, as the vendor types */
const fillerLines = computed(() =>
  fillerRows.value.map(({ row, i }) => {
    const filler = fillers.value.find((f) => f.id === Number(row.product_id));
    const per = Number(row.count);
    if (!filler) return { i, text: "Pick a filler, then type how many of it one bouquet uses." };
    if (!Number.isInteger(per) || per < 1) return { i, text: `Type how many ${filler.name} one bouquet uses.` };
    const left = Number(filler.stock) || 0;
    const make = Math.floor(left / per);
    const name = fillerRows.value.length > 1 ? `${filler.name}: ` : "";
    return { i, text: `${name}With ${left} left and ${per} per bouquet, you can make ${make} ${make === 1 ? "bouquet" : "bouquets"}.` };
  }),
);
function setMaterial(index, list, value) {
  const m = list.find((x) => x.id === Number(value));
  if (m) update(index, { product_id: m.id, flower: m.name, cost: m.cost ?? "" });
}

const stems = computed(() => flowerRows.value.reduce((sum, { row }) => sum + (Number(row.count) > 0 ? Number(row.count) : 0), 0));
const sheets = computed(() => wrapperRows.value.reduce((sum, { row }) => sum + (Number(row.count) > 0 ? Number(row.count) : 0), 0));

/** A wrapper row: which of the shop's wrappers, and how many sheets one bouquet uses */
const addWrapper = () => {
  const first = wrappers.value[0];
  emit("update:modelValue", [
    ...props.modelValue,
    { kind: "wrapper", flower: first?.name || "", color: "", count: 1, cost: first?.cost ?? "", product_id: first?.id ?? null },
  ]);
};
function setWrapper(index, value) {
  const w = wrappers.value.find((x) => x.id === Number(value));
  if (w) update(index, { product_id: w.id, flower: w.name, cost: w.cost ?? "" });
}

const update = (index, patch) =>
  emit("update:modelValue", props.modelValue.map((r, i) => (i === index ? { ...r, ...patch } : r)));
const add = () => emit("update:modelValue", [...props.modelValue, { flower: "", color: "", count: 1, cost: "", product_id: null }]);

// ── Linking a row to a single flower in stock ──
const linkOf = (row) => (row.product_id && props.flowers ? props.flowers.find((f) => f.id === Number(row.product_id)) : null);
const linkedColors = (row) => linkOf(row)?.colors || [];
const linkedMake = computed(() => stockFromLinks(props.modelValue, props.flowers));

/** Linking fills in what's still empty: the flower's name, its colour and what it cost */
function setLink(index, value) {
  const id = value ? Number(value) : null;
  const row = props.modelValue[index];
  const flower = id ? props.flowers?.find((f) => f.id === id) : null;
  const patch = { product_id: id };
  if (flower) {
    if (!String(row.flower || "").trim()) patch.flower = flower.name;
    if (!String(row.color || "").trim()) patch.color = flower.colors?.length ? flower.colors[0].name : flower.color || "";
    if ((row.cost === "" || row.cost == null) && flower.cost != null) patch.cost = flower.cost;
  }
  update(index, patch);
}
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

/* One field under another, full width, so every label and placeholder fits;
   the remove button sits in the top-right corner */
.bf-row {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 10px;
  padding: 12px;
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
  text-align: left;
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

.bf-link {
  grid-column: 1 / -1;
}

/* Wrappers: which wrapper, sheets, remove */
.bf-head-wrap {
  margin-top: 6px;
}

/* The first field leaves room for the remove button beside it */
.bf-row > .bf-field:first-of-type {
  margin-right: 48px;
}

.bf-row > .bf-remove {
  position: absolute;
  top: 8px;
  right: 8px;
}

.bf-row.linked {
  border-color: color-mix(in srgb, var(--bc-accent, #4db173) 45%, var(--v-border));
}

.bf-line-link {
  color: var(--v-text-2, var(--v-text));
}

.bf-linked-note {
  margin: 0;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--v-blue-soft, var(--v-subtle));
  color: var(--v-text-2, var(--v-text));
  font-size: 13px;
  line-height: 1.5;
}

.bf-linked-note b {
  color: var(--v-text);
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

</style>
