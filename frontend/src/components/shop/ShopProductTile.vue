<template>
  <article class="pt" :class="{ compact }">
    <button type="button" class="pt-media" :aria-label="`View ${product.product_name}`" @click="$emit('open', product)">
      <img v-if="image" ref="imageEl" :src="sizedImage(image, 320)" :srcset="sizedSrcset(image, 320)" :alt="product.product_name" loading="lazy" decoding="async" />
      <span v-else class="pt-noimg" aria-hidden="true">✿</span>
      <!-- One tag, top left: the highest-ranked one this product has (the admin sets the ranking) -->
      <span v-if="tag" class="pt-tags">
        <ProductTag :tag="tag" on-photo />
      </span>
    </button>
    <div class="pt-body">
      <button type="button" class="pt-name" :title="product.product_name" @click="$emit('open', product)">{{ product.product_name }}</button>

      <!-- Stars and reviews, then how many sold -->
      <p class="pt-meta">
        <template v-if="reviews">
          <span class="pt-star" aria-hidden="true">★</span>
          <strong>{{ rating.toFixed(1) }}</strong>
          <span class="pt-soft">({{ reviews }})</span>
          <span class="sr-only">{{ rating.toFixed(1) }} out of 5 from {{ reviews }} {{ reviews === 1 ? "review" : "reviews" }}</span>
        </template>
        <span v-else class="pt-soft">No reviews yet</span>
        <template v-if="sold">
          <span class="pt-dot" aria-hidden="true"></span>
          <span class="pt-soft">{{ soldText }} sold</span>
        </template>
      </p>

      <!-- Price, the original price and the discount when on sale -->
      <p class="pt-price">
        <strong :class="{ sale: onSale }">{{ peso(price) }}</strong>
        <template v-if="onSale">
          <s>{{ peso(product.selling_price) }}</s>
          <span class="pt-off">−{{ discount }}%</span>
        </template>
      </p>

      <p class="pt-stock" :class="stock">
        <i aria-hidden="true"></i>{{ stockText }}
      </p>

      <div class="pt-actions">
        <button type="button" class="pt-view" @click="$emit('open', product)">View details</button>
        <button
          type="button"
          class="pt-cart"
          :disabled="adding || soldOut"
          :aria-label="soldOut ? `${product.product_name} is sold out` : `Add ${product.product_name} to cart`"
          :title="soldOut ? 'Sold out' : 'Add to cart'"
          @click="$emit('add', product, { imageEl })"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.4 11.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L21 8H6.2" /><circle cx="9.5" cy="20" r="1.2" /><circle cx="17.5" cy="20" r="1.2" /></svg>
        </button>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed, ref } from "vue";
import { sizedImage, sizedSrcset } from "../../utils/cloudinaryImage.js";
import ProductTag, { LOW_STOCK, cardTag, useTagOrder } from "./ProductTag.vue";

/** Small product card for the shop's Top Selling row and the bouquet finder */
const props = defineProps({
  product: { type: Object, required: true },
  bestSeller: { type: Boolean, default: false },
  adding: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
  // The row it sits in can add its own tag: "you_may_like"
  contextTag: { type: String, default: "" },
});
defineEmits(["open", "add"]);

const imageEl = ref(null);
const image = computed(() => props.product.primary_image?.image_url || props.product.images?.[0]?.image_url || null);
const onSale = computed(
  () => props.product.has_discount && Number(props.product.discount_price) > 0 && Number(props.product.discount_price) < Number(props.product.selling_price),
);
/**
 * The one tag on this card (see cardTag): the row's own, else Out of stock,
 * else one of the product's tags, changing every hour. A product saved
 * before tags existed falls back to Sale / Best seller.
 */
useTagOrder();
const tag = computed(() =>
  cardTag({
    contextTag: props.contextTag,
    soldOut: Number(props.product.quantity_in_stock) === 0,
    tags: Array.isArray(props.product.tags) ? props.product.tags : [onSale.value && "sale", props.bestSeller && "best_seller"],
    productId: props.product.id,
  }),
);
// What the card shows under the name
const price = computed(() => (onSale.value ? Number(props.product.discount_price) : Number(props.product.selling_price || 0)));
const discount = computed(() => Math.max(1, Math.round((1 - price.value / Number(props.product.selling_price)) * 100)));
const rating = computed(() => Number(props.product.average_rating || 0));
const reviews = computed(() => Number(props.product.total_reviews || 0));
const sold = computed(() => Number(props.product.sold_count || 0));
const soldText = computed(() => (sold.value >= 1000 ? `${(sold.value / 1000).toFixed(sold.value % 1000 >= 100 ? 1 : 0)}k` : String(sold.value)));
const stockCount = computed(() => Number(props.product.quantity_in_stock ?? 0));
const soldOut = computed(() => stockCount.value <= 0);
const stock = computed(() => (soldOut.value ? "out" : stockCount.value <= LOW_STOCK ? "low" : "in"));
const stockText = computed(() => (soldOut.value ? "Sold out" : stock.value === "low" ? `Only ${stockCount.value} left` : "In stock"));

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
  box-shadow: 0 16px 34px -22px rgba(54, 63, 78, 0.35);
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
  color: var(--leaf);
}

/* Same badge as the featured cards (FeaturedPicks) */
.pt-tags {
  position: absolute;
  top: 10px;
  left: 10px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  pointer-events: none;
}

.pt-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 5px;
  padding: 12px;
}

.pt-name {
  overflow: hidden;
  padding: 0;
  border: 0;
  background: none;
  font: 500 13.5px var(--font-sans);
  color: var(--ink);
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
}

.pt-name:hover {
  color: var(--leaf-deep);
}

.pt-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  margin: 0;
  font-size: 12px;
  color: var(--ink);
}

.pt-meta strong {
  font-weight: 600;
}

.pt-star {
  color: #e2a23b;
}

.pt-soft {
  color: var(--ink-soft);
}

.pt-dot {
  width: 3px;
  height: 3px;
  margin: 0 2px;
  border-radius: 50%;
  background: var(--ink-soft);
  opacity: 0.6;
}

.pt-price {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 6px;
  margin: 2px 0 0;
  color: var(--ink);
}

.pt-price strong {
  font-size: 15px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.pt-price strong.sale {
  color: #b4332a;
}

.pt-price s {
  font-size: 12px;
  color: var(--ink-soft);
}

.pt-off {
  padding: 1px 6px;
  border-radius: 5px;
  background: #fdecea;
  color: #b4332a;
  font-size: 11px;
  font-weight: 600;
}

/* In stock / Only 3 left / Sold out */
.pt-stock {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 11.5px;
  color: var(--leaf-deep);
}

.pt-stock i {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--leaf);
}

.pt-stock.low {
  color: #a55a12;
}

.pt-stock.low i {
  background: #d18a1c;
}

.pt-stock.out {
  color: #b4332a;
}

.pt-stock.out i {
  background: #b4332a;
}

/* View details + quick add, at the bottom of every card */
.pt-actions {
  display: flex;
  gap: 6px;
  margin-top: auto;
  padding-top: 8px;
}

.pt-view {
  flex: 1;
  min-height: 40px;
  padding: 0 10px;
  border: 1px solid var(--line);
  border-radius: 9px;
  background: var(--paper);
  color: var(--ink);
  font: 500 12.5px var(--font-sans);
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease;
}

.pt-view:hover {
  border-color: var(--ink);
}

.pt-cart {
  display: grid;
  flex: none;
  place-items: center;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 0;
  border-radius: 9px;
  background: var(--leaf-deep);
  color: var(--paper);
  cursor: pointer;
  transition: background 0.2s ease;
}

.pt-cart:hover:not(:disabled) {
  background: var(--ink);
}

.pt-cart:disabled {
  background: var(--ivory-deep);
  color: var(--ink-soft);
  cursor: not-allowed;
}

.pt-cart svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

.pt :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}
</style>
