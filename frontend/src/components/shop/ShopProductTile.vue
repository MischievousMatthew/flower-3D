<template>
  <article class="pt" :class="{ compact }">
    <button type="button" class="pt-media" :aria-label="`View ${product.product_name}`" @click="$emit('open', product)">
      <img v-if="image" ref="imageEl" :src="image" :alt="product.product_name" loading="lazy" />
      <span v-else class="pt-noimg" aria-hidden="true">✿</span>
      <span v-if="badge" class="pt-badge" :class="badge.tone">{{ badge.text }}</span>
    </button>
    <div class="pt-body">
      <button type="button" class="pt-name" @click="$emit('open', product)">{{ product.product_name }}</button>
      <p class="pt-price">
        <template v-if="onSale">
          <s>{{ peso(product.selling_price) }}</s>
          <strong class="sale">{{ peso(product.discount_price) }}</strong>
        </template>
        <strong v-else>{{ peso(product.selling_price) }}</strong>
      </p>
      <p v-if="Number(product.total_reviews)" class="pt-rating">
        <span aria-hidden="true">★</span> {{ Number(product.average_rating || 0).toFixed(1) }}
        <small>({{ product.total_reviews }})</small>
      </p>
      <p v-else class="pt-rating muted">New</p>
      <button
        type="button"
        class="pt-cart"
        :disabled="adding || product.quantity_in_stock === 0"
        :aria-label="product.quantity_in_stock === 0 ? `${product.product_name} is sold out` : `Add ${product.product_name} to cart`"
        :title="product.quantity_in_stock === 0 ? 'Sold out' : 'Add to cart'"
        @click="$emit('add', product, { imageEl })"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.4 11.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L21 8H6.2" /><circle cx="9.5" cy="20" r="1.2" /><circle cx="17.5" cy="20" r="1.2" /></svg>
      </button>
    </div>
  </article>
</template>

<script setup>
import { computed, ref } from "vue";

/** Small product card for the shop's Top Selling row and the bouquet finder */
const props = defineProps({
  product: { type: Object, required: true },
  bestSeller: { type: Boolean, default: false },
  adding: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
});
defineEmits(["open", "add"]);

const imageEl = ref(null);
const image = computed(() => props.product.primary_image?.image_url || props.product.images?.[0]?.image_url || null);
const onSale = computed(
  () => props.product.has_discount && Number(props.product.discount_price) > 0 && Number(props.product.discount_price) < Number(props.product.selling_price),
);
const badge = computed(() => {
  if (props.product.quantity_in_stock === 0) return { text: "Sold out", tone: "muted" };
  if (onSale.value) return { text: "On sale", tone: "sale" };
  if (props.bestSeller) return { text: "Best seller", tone: "best" };
  return null;
});
const peso = (value) => `₱${Number(value || 0).toLocaleString("en-PH", { maximumFractionDigits: 2 })}`;
</script>

<style scoped>
.pt,
.pt * {
  box-sizing: border-box;
}

.pt {
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--line-soft);
  border-radius: 12px;
  background: var(--paper);
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.pt:hover {
  box-shadow: 0 16px 34px -22px rgba(30, 27, 24, 0.35);
  transform: translateY(-2px);
}

.pt-media {
  position: relative;
  display: block;
  aspect-ratio: 5 / 3.4;
  width: 100%;
  padding: 0;
  overflow: hidden;
  border: 0;
  background: var(--ivory-deep);
  cursor: pointer;
}

.pt.compact .pt-media {
  aspect-ratio: 1;
}

.pt-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s var(--ease-out);
}

.pt:hover .pt-media img {
  transform: scale(1.04);
}

.pt-noimg {
  display: grid;
  place-items: center;
  height: 100%;
  font-size: 30px;
  color: var(--rosewood);
}

.pt-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 500;
  color: #fff;
}

.pt-badge.best {
  background: #c9874a;
}

.pt-badge.sale {
  background: var(--rosewood-dark);
}

.pt-badge.muted {
  background: rgba(30, 27, 24, 0.6);
}

.pt-body {
  position: relative;
  display: grid;
  gap: 4px;
  padding: 12px 12px 14px;
}

.pt-name {
  overflow: hidden;
  padding: 0 34px 0 0;
  border: 0;
  background: none;
  font: 500 13px var(--font-sans);
  color: var(--ink);
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
}

.pt-price {
  display: flex;
  align-items: baseline;
  gap: 6px;
  margin: 0;
  font-size: 14px;
  color: var(--ink);
}

.pt-price strong {
  font-weight: 600;
}

.pt-price strong.sale {
  color: var(--rosewood-dark);
}

.pt-price s {
  font-size: 12px;
  color: var(--ink-soft);
}

.pt-rating {
  margin: 0;
  font-size: 12px;
  color: var(--ink);
}

.pt-rating span {
  color: #e2a23b;
}

.pt-rating small {
  color: var(--ink-soft);
}

.pt-rating.muted {
  color: var(--ink-soft);
}

.pt-cart {
  position: absolute;
  right: 12px;
  bottom: 14px;
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  padding: 0;
  border: 1px solid var(--line);
  border-radius: 9px;
  background: var(--paper);
  color: var(--rosewood-dark);
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.pt-cart:hover:not(:disabled) {
  border-color: var(--rosewood-dark);
  background: var(--rosewood-dark);
  color: var(--paper);
}

.pt-cart:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.pt-cart svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pt :focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 2px;
}
</style>
