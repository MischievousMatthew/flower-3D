<template>
  <section class="fp" id="featured" aria-labelledby="fp-title">
    <div class="bc-container fp-layout">
      <div class="fp-intro">
        <div class="fp-heading">
          <h2 id="fp-title">In bloom this <em>{{ seasonMonth }}</em></h2>
          <p class="fp-lede">Flowers at their best right now, picked by the BloomCraft team.</p>
        </div>
        <div class="fp-intro-foot">
          <button type="button" class="fp-browse" @click="$emit('browse')">
            Browse all flowers
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </button>
          <div v-if="scrollable" class="fp-arrows">
            <button type="button" class="fp-arrow" aria-label="Previous picks" :disabled="atStart" @click="slide(-1)">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
            </button>
            <button type="button" class="fp-arrow" aria-label="More picks" :disabled="atEnd" @click="slide(1)">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
            </button>
          </div>
        </div>
      </div>

      <ul ref="trackEl" class="fp-track" @scroll.passive="measure">
        <li v-for="product in products" :key="product.id" class="fp-item">
          <article :ref="(el) => (cardEls[product.id] = el)" class="fp-card">
            <button type="button" class="fp-photo" :aria-label="`View ${product.product_name}`" @click="$emit('open', product)">
              <img
                :ref="(el) => (imageEls[product.id] = el)"
                :src="sizedImage(imageOf(product), 300)"
                :srcset="sizedSrcset(imageOf(product), 300)"
                :alt="product.product_name"
                loading="lazy"
                @error="onImageError"
              />
              <span v-if="badge(product)" class="fp-badge" :class="badge(product).tone">{{ badge(product).label }}</span>
            </button>

            <div class="fp-body">
              <button v-if="product.vendor_name" type="button" class="fp-shop" @click="$emit('vendor', product.vendor_id)">
                {{ product.vendor_name }}
              </button>
              <h3 class="fp-name">
                <button type="button" @click="$emit('open', product)">{{ product.product_name }}</button>
              </h3>
              <p class="fp-stats">
                <span class="fp-star" aria-hidden="true">★</span>
                <strong>{{ product.average_rating ? Number(product.average_rating).toFixed(1) : "New" }}</strong>
                <template v-if="product.sold_count"> · {{ sold(product.sold_count) }} sold</template>
              </p>

              <div class="fp-buy">
                <p class="fp-price">
                  <span class="fp-now">₱{{ money(product.discount_price || product.selling_price) }}</span>
                  <span v-if="product.discount_price" class="fp-was">₱{{ money(product.selling_price) }}</span>
                </p>
                <button
                  type="button"
                  class="fp-cart"
                  :aria-label="`Add ${product.product_name} to cart`"
                  :title="inStock(product) ? 'Add to cart' : 'Sold out'"
                  :disabled="!inStock(product) || addingId === product.id"
                  @click="$emit('add', product, { imageEl: imageEls[product.id], cardEl: cardEls[product.id] })"
                >
                  <span v-if="addingId === product.id" class="fp-cart-dots" aria-hidden="true">···</span>
                  <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.4 11.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L21 8H6.2" /><circle cx="9.5" cy="20" r="1.2" /><circle cx="17.5" cy="20" r="1.2" /></svg>
                </button>
              </div>
            </div>
          </article>
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { sizedImage, sizedSrcset } from "../../utils/cloudinaryImage.js";

/**
 * "In bloom this <month>" (Admin → Website → Featured): the heading on the left and
 * compact product cards on the right, in a row that scrolls when there are
 * more than fit. One pick stays a normal-sized card.
 */
const props = defineProps({
  products: { type: Array, default: () => [] },
  addingId: { type: [Number, String], default: null },
});
defineEmits(["open", "add", "buy", "vendor", "browse"]);

// The month in the heading follows the calendar: "In bloom this November"
const seasonMonth = new Date().toLocaleDateString("en-PH", { month: "long" });

const imageEls = {};
const cardEls = {};
const trackEl = ref(null);
const scrollable = ref(false);
const atStart = ref(true);
const atEnd = ref(false);

const FALLBACK = "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=800&h=1000&fit=crop";
function imageOf(product) {
  if (product?.primary_image?.image_url) return product.primary_image.image_url;
  const images = product?.images || [];
  return (images.find((i) => i.is_primary) || images[0])?.image_url || FALLBACK;
}
function onImageError(event) {
  if (event.target.src !== FALLBACK) event.target.src = FALLBACK;
}

const money = (value) => Number(value || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const sold = (n) => (n >= 1000 ? `${(n / 1000).toFixed(n % 1000 ? 1 : 0)}k` : String(n));
const inStock = (product) => Number(product.quantity_in_stock ?? 1) > 0;

// The same badges as the product tiles (ShopProductTile)
function badge(product) {
  if (!inStock(product)) return { label: "Sold out", tone: "muted" };
  if (Number(product.discount_price) > 0 && Number(product.discount_price) < Number(product.selling_price)) return { label: "On sale", tone: "sale" };
  if ((product.sold_count ?? 0) >= 10) return { label: "Best seller", tone: "best" };
  return null;
}

// Arrows show only when the cards don't all fit
function measure() {
  const el = trackEl.value;
  if (!el) return;
  scrollable.value = el.scrollWidth > el.clientWidth + 4;
  atStart.value = el.scrollLeft <= 4;
  atEnd.value = el.scrollLeft + el.clientWidth >= el.scrollWidth - 4;
}

function slide(direction) {
  const el = trackEl.value;
  if (!el) return;
  const card = el.querySelector(".fp-item");
  const step = card ? card.getBoundingClientRect().width + 20 : el.clientWidth * 0.8;
  el.scrollBy({ left: direction * step, behavior: "smooth" });
}

let resize = null;
onMounted(() => {
  measure();
  resize = new ResizeObserver(measure);
  if (trackEl.value) resize.observe(trackEl.value);
});
onBeforeUnmount(() => resize?.disconnect());
watch(
  () => props.products.length,
  async () => {
    await nextTick();
    measure();
  },
);
</script>

<style scoped>
.fp {
  padding: clamp(24px, 3vw, 40px) 0 clamp(8px, 1.5vw, 16px);
}

/* A heading row, then a row of small cards */
.fp-layout {
  display: block;
}

.fp-intro {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px 24px;
  margin-bottom: 14px;
}

/* ── Intro ─────────────────────────────── */
.fp-intro h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: clamp(26px, 2.6vw, 32px);
  font-weight: 500;
  line-height: 1.05;
  color: var(--ink);
}

.fp-intro h2 em {
  color: var(--leaf-deep);
}

.fp-lede {
  margin: 4px 0 0;
  font-size: 14px;
  line-height: 1.65;
  color: var(--ink-soft);
}




.fp-intro-foot {
  display: flex;
  align-items: center;
  gap: 10px;
}

.fp-browse {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 20px;
  border: 1px solid var(--ink);
  border-radius: 999px;
  background: transparent;
  color: var(--ink);
  font: inherit;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.18s ease, color 0.18s ease;
}

.fp-browse:hover {
  background: var(--ink);
  color: #fff;
}

.fp-browse svg,
.fp-arrow svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fp-arrows {
  display: flex;
  gap: 8px;
}

.fp-arrow {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: #fff;
  color: var(--ink);
  cursor: pointer;
  transition: border-color 0.18s ease;
}

.fp-arrow:hover:not(:disabled) {
  border-color: var(--ink);
}

.fp-arrow:disabled {
  cursor: default;
  opacity: 0.35;
}

/* ── Cards ─────────────────────────────── */
.fp-track {
  display: flex;
  gap: 14px;
  margin: 0;
  padding: 4px 2px 10px;
  overflow-x: auto;
  list-style: none;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
}

.fp-track::-webkit-scrollbar {
  display: none;
}

.fp-item {
  flex: 0 0 196px;
  scroll-snap-align: start;
}

.fp-card {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 16px;
  background: #fff;
  transition: transform 0.25s var(--ease-out, ease-out), box-shadow 0.25s ease;
}

.fp-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 24px 40px -28px rgba(54, 63, 78, 0.5);
}

.fp-photo {
  position: relative;
  display: block;
  aspect-ratio: 1 / 1;
  margin: 6px 6px 0;
  padding: 0;
  overflow: hidden;
  border: 0;
  border-radius: 12px;
  background: var(--ivory);
  cursor: pointer;
}

.fp-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s var(--ease-out, ease-out);
}

.fp-card:hover .fp-photo img {
  transform: scale(1.05);
}

/* Same badge as the product tiles */
.fp-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  padding: 4px 10px;
  border-radius: 999px;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.fp-badge.best {
  background: var(--leaf-deep);
}

.fp-badge.sale {
  background: #b3413b;
}

.fp-badge.muted {
  background: rgba(54, 63, 78, 0.78);
}

.fp-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px 12px;
}

.fp-shop {
  align-self: flex-start;
  padding: 0;
  border: 0;
  background: none;
  color: var(--leaf-deep);
  font: inherit;
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.02em;
  cursor: pointer;
}

.fp-shop:hover {
  text-decoration: underline;
}

.fp-name {
  margin: 0;
  font-family: var(--font-display);
  font-size: 19px;
  font-weight: 500;
  line-height: 1.15;
}

.fp-name button {
  padding: 0;
  border: 0;
  background: none;
  color: var(--ink);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.fp-name button:hover {
  color: var(--leaf-deep);
}

.fp-stats {
  margin: 0;
  font-size: 12.5px;
  color: var(--ink-soft);
}

.fp-stats strong {
  color: var(--ink);
  font-weight: 600;
}

.fp-star {
  margin-right: 3px;
  color: var(--gold);
}

.fp-buy {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: auto;
  padding-top: 8px;
}

.fp-price {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 6px;
  margin: 0;
}

.fp-now {
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
  font-variant-numeric: tabular-nums;
}

.fp-was {
  font-size: 13px;
  color: var(--ink-soft);
  text-decoration: line-through;
}

.fp-cart {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 0;
  border-radius: 50%;
  background: var(--leaf-deep);
  color: #fff;
  cursor: pointer;
  transition: background-color 0.18s ease, transform 0.18s ease;
}

.fp-cart:hover:not(:disabled) {
  background: var(--ink);
  transform: scale(1.05);
}

.fp-cart:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.fp-cart svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fp-cart-dots {
  font-size: 16px;
  font-weight: 700;
  letter-spacing: 1px;
}

.fp-browse:focus-visible,
.fp-arrow:focus-visible,
.fp-photo:focus-visible,
.fp-shop:focus-visible,
.fp-name button:focus-visible,
.fp-cart:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

@media (max-width: 640px) {
  .fp-item {
    flex-basis: min(44vw, 180px);
  }

  .fp-browse {
    min-height: 40px;
    padding: 0 14px;
    font-size: 13px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .fp-card,
  .fp-photo img,
  .fp-cart {
    transition: none;
  }

  .fp-card:hover {
    transform: none;
  }
}
</style>
