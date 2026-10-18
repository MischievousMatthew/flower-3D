<template>
  <div class="sp">
    <label v-if="kind !== 'card'" class="sp-search">
      <span class="bc-sr-only">Search {{ kind === "filler" ? "fillers" : "flowers" }}</span>
      <CustomizerIcon name="search" />
      <input
        :value="search"
        type="search"
        :placeholder="kind === 'filler' ? 'Search fillers' : 'Search flowers'"
        @input="$emit('update:search', $event.target.value)"
      />
    </label>

    <ul v-if="loading" class="sp-list" aria-busy="true" aria-label="Loading">
      <li v-for="n in 4" :key="n" class="sp-row is-skeleton">
        <span class="sp-thumb bc-skeleton"></span>
        <span class="sp-copy">
          <span class="bc-skeleton sp-skel"></span>
          <span class="bc-skeleton sp-skel short"></span>
        </span>
      </li>
    </ul>

    <p v-else-if="!items.length" class="sp-empty">{{ emptyText }}</p>

    <ul v-else class="sp-list">
      <li v-for="item in items" :key="`${kind}-${item.id}`">
        <div
          role="button"
          tabindex="0"
          class="sp-row"
          :class="{ active: pendingId === item.id, disabled: !canUse(item), placed: countOf(item.id) > 0 }"
          :aria-disabled="!canUse(item)"
          :draggable="canUse(item)"
          @dragstart="$emit('dragstart', $event, item)"
          @dragend="$emit('dragend')"
          @click="$emit('pick', item)"
          @keydown.enter.prevent="$emit('pick', item)"
          @keydown.space.prevent="$emit('pick', item)"
        >
          <span class="sp-thumb">
            <img v-if="item.image" :src="item.image" :alt="item.product_name" loading="lazy" />
            <CustomizerIcon v-else :name="kind === 'filler' ? 'leaf' : kind === 'card' ? 'card' : 'flower'" />
          </span>
          <span class="sp-copy">
            <strong>{{ item.product_name }}</strong>
            <small>{{ meta(item) }}</small>
          </span>
          <span class="sp-end">
            <span v-if="countOf(item.id)" class="sp-count" :aria-label="`${countOf(item.id)} on the bouquet`">{{ countOf(item.id) }}</span>
            <span v-else-if="canUse(item)" class="sp-add">Add</span>
            <span v-else class="sp-flag">{{ unavailableText(item) }}</span>
            <button
              v-if="showFill && canUse(item)"
              type="button"
              class="sp-fill"
              :title="`Fill the rest of the bouquet with ${item.product_name}`"
              @click.stop="$emit('fill', item)"
              @keydown.enter.stop
              @keydown.space.stop
            >Fill the rest</button>
          </span>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import CustomizerIcon from "./CustomizerIcon.js";

const props = defineProps({
  items: { type: Array, required: true },
  kind: { type: String, default: "flower" }, // "flower" | "filler" | "card"
  loading: { type: Boolean, default: false },
  search: { type: String, default: "" },
  emptyText: { type: String, default: "" },
  countOf: { type: Function, required: true },
  canUse: { type: Function, required: true },
  pendingId: { type: [Number, String], default: null },
  showFill: { type: Boolean, default: false },
  priceLabel: { type: Function, default: null },
  unavailableText: { type: Function, default: () => "Sold out" },
});
defineEmits(["update:search", "pick", "fill", "dragstart", "dragend"]);

const peso = (value) => `₱${Number(value || 0).toLocaleString("en-PH", { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;

function meta(item) {
  if (props.priceLabel) return props.priceLabel(item);
  const price = props.kind === "filler" && !Number(item.price) ? "Free" : peso(item.price);
  return item.quantity_in_stock != null ? `${price} · ${item.quantity_in_stock} left` : price;
}
</script>

<style scoped>
.sp-search {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 0 10px;
  margin-bottom: 4px;
  border-bottom: 1px solid var(--line);
  color: var(--ink-soft);
}

.sp-search input {
  flex: 1;
  min-width: 0;
  border: 0;
  background: none;
  font: inherit;
  font-size: 13px;
  color: var(--ink);
  outline: none;
}

.sp-search input::placeholder {
  color: var(--ink-soft);
}

.sp-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.sp-row {
  display: grid;
  grid-template-columns: 46px minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 11px 8px;
  margin: 0 -8px;
  border-bottom: 1px solid var(--line-soft);
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s ease;
}

.sp-row:hover {
  background: rgba(30, 27, 24, 0.03);
}

.sp-row.active {
  background: rgba(184, 101, 88, 0.08);
}

.sp-row.disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.sp-row:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: -2px;
}

.sp-thumb {
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  overflow: hidden;
  border-radius: 8px;
  background: var(--ivory-deep);
  color: var(--ink-soft);
}

.sp-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sp-copy {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.sp-copy strong {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--ink);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sp-copy small {
  font-size: 11.5px;
  color: var(--ink-soft);
}

.sp-end {
  display: grid;
  justify-items: end;
  gap: 4px;
}

.sp-add {
  font-size: 12px;
  font-weight: 500;
  color: var(--rosewood-dark);
}

.sp-count {
  display: grid;
  place-items: center;
  min-width: 24px;
  height: 24px;
  padding: 0 7px;
  border-radius: 12px;
  background: rgba(184, 101, 88, 0.12);
  font-size: 12px;
  font-weight: 600;
  line-height: 1;
  color: var(--rosewood-dark);
}

.sp-flag {
  font-size: 11px;
  color: var(--ink-soft);
}

.sp-fill {
  padding: 0;
  border: 0;
  border-bottom: 1px solid transparent;
  background: none;
  font: inherit;
  font-size: 11px;
  color: var(--ink-soft);
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.2s ease, color 0.2s ease;
}

.sp-row:hover .sp-fill,
.sp-row:focus-within .sp-fill,
.sp-fill:focus-visible {
  opacity: 1;
}

.sp-fill:hover {
  color: var(--rosewood-dark);
  border-bottom-color: currentColor;
}

.sp-empty {
  margin: 18px 0;
  font-size: 13px;
  color: var(--ink-soft);
}

.sp-skel {
  display: block;
  width: 70%;
  height: 10px;
  border-radius: 4px;
}

.sp-skel.short {
  width: 40%;
}

@media (hover: none) {
  .sp-fill {
    opacity: 1;
  }
}
</style>
