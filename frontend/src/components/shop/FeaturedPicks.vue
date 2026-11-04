<template>
  <section class="fp" id="featured" aria-labelledby="fp-title">
    <div class="bc-container">
      <div class="fp-head">
        <p class="bc-label">Featured picks</p>
        <h2 id="fp-title">Chosen by <em>BloomCraft</em></h2>
        <p class="fp-lede">Flowers our team loves right now, from shops near you.</p>
      </div>

      <div class="fp-grid" :class="{ single: products.length === 1 }">
        <article
          v-for="product in products"
          :key="product.id"
          :ref="(el) => (cardEls[product.id] = el)"
          class="fp-card"
        >
          <button type="button" class="fp-photo" :aria-label="`View ${product.product_name}`" @click="$emit('open', product)">
            <img
              :ref="(el) => (imageEls[product.id] = el)"
              :src="imageOf(product)"
              :alt="product.product_name"
              loading="lazy"
              @error="onImageError"
            />
            <span class="fp-badge" :class="badge(product).tone">{{ badge(product).label }}</span>
          </button>

          <div class="fp-body">
            <p v-if="product.category" class="fp-category">{{ product.category }}</p>
            <h3 class="fp-name">
              <button type="button" @click="$emit('open', product)">{{ product.product_name }}</button>
            </h3>
            <button v-if="product.vendor_name" type="button" class="fp-shop" @click="$emit('vendor', product.vendor_id)">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10h16l-1.2-4.8A1.6 1.6 0 0 0 17.2 4H6.8a1.6 1.6 0 0 0-1.6 1.2zM5 10v9h14v-9M9 19v-5h6v5" /></svg>
              {{ product.vendor_name }}
            </button>

            <p class="fp-stats">
              <span class="fp-star" aria-hidden="true">★</span>
              <strong>{{ product.average_rating ? Number(product.average_rating).toFixed(1) : "New" }}</strong>
              <template v-if="product.total_reviews"> · {{ product.total_reviews }} {{ product.total_reviews === 1 ? "review" : "reviews" }}</template>
              <template v-if="product.sold_count"> · {{ sold(product.sold_count) }} sold</template>
            </p>

            <p v-if="product.description" class="fp-desc">{{ product.description }}</p>

            <div class="fp-buy">
              <p class="fp-price">
                <span class="fp-now">₱{{ money(product.discount_price || product.selling_price) }}</span>
                <span v-if="product.discount_price" class="fp-was">₱{{ money(product.selling_price) }}</span>
              </p>
              <span class="fp-stock" :class="stock(product).tone">{{ stock(product).label }}</span>
            </div>

            <div class="fp-actions">
              <button
                type="button"
                class="fp-btn fp-btn--primary"
                :disabled="!inStock(product) || addingId === product.id"
                @click="$emit('add', product, { imageEl: imageEls[product.id], cardEl: cardEls[product.id] })"
              >
                {{ addingId === product.id ? "Adding" : "Add to cart" }}
              </button>
              <button type="button" class="fp-btn fp-btn--ghost" :disabled="!inStock(product)" @click="$emit('buy', product)">Buy now</button>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
/**
 * "Chosen by BloomCraft" (Admin → Website): wide cards with the photo beside
 * the details, so one pick or a few both fill the row.
 */
defineProps({
  products: { type: Array, default: () => [] },
  addingId: { type: [Number, String], default: null },
});
defineEmits(["open", "add", "buy", "vendor"]);

const imageEls = {};
const cardEls = {};

const FALLBACK = "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=800&h=800&fit=crop";
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

function badge(product) {
  if (product.discount_price) return { label: "Sale", tone: "sale" };
  if ((product.sold_count ?? 0) >= 10) return { label: "Best seller", tone: "best" };
  return { label: "New", tone: "new" };
}

function stock(product) {
  const q = Number(product.quantity_in_stock ?? 0);
  if (q <= 0) return { label: "Out of stock", tone: "out" };
  if (q <= 10) return { label: `Only ${q} left`, tone: "low" };
  return { label: "In stock", tone: "in" };
}
</script>

<style scoped>
.fp {
  padding: clamp(28px, 4vw, 56px) 0 clamp(12px, 2vw, 24px);
}

.fp-head {
  margin-bottom: 22px;
}

.fp-head h2 {
  margin: 4px 0 0;
  font-family: var(--font-display);
  font-size: clamp(30px, 3.6vw, 44px);
  font-weight: 500;
  line-height: 1.1;
  color: var(--ink);
}

.fp-head h2 em {
  color: var(--leaf-deep);
}

.fp-lede {
  margin: 8px 0 0;
  font-size: 14.5px;
  color: var(--ink-soft);
}

.fp-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 520px), 1fr));
  gap: 20px;
}

.fp-card {
  display: grid;
  grid-template-columns: minmax(180px, 42%) 1fr;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 20px;
  background: #fff;
  transition: box-shadow 0.2s ease, border-color 0.2s ease;
}

.fp-grid.single .fp-card {
  grid-template-columns: minmax(220px, 38%) 1fr;
}

.fp-card:hover {
  border-color: rgba(46, 122, 79, 0.35);
  box-shadow: 0 22px 44px -30px rgba(54, 63, 78, 0.45);
}

.fp-photo {
  position: relative;
  display: block;
  min-height: 280px;
  padding: 0;
  overflow: hidden;
  border: 0;
  background: var(--ivory);
  cursor: pointer;
}

.fp-photo img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s var(--ease-out, ease-out);
}

.fp-card:hover .fp-photo img {
  transform: scale(1.04);
}

.fp-badge {
  position: absolute;
  top: 14px;
  left: 14px;
  padding: 5px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  color: var(--ink);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.fp-badge.sale {
  background: #b3413b;
  color: #fff;
}

.fp-badge.best {
  background: var(--leaf-deep);
  color: #fff;
}

.fp-body {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
  padding: clamp(18px, 2.4vw, 30px);
}

.fp-category {
  margin: 0;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--ink-soft);
}

.fp-name {
  margin: 0;
  font-family: var(--font-display);
  font-size: clamp(26px, 2.6vw, 34px);
  font-weight: 500;
  line-height: 1.1;
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

.fp-shop {
  display: inline-flex;
  align-self: flex-start;
  align-items: center;
  gap: 6px;
  min-height: 32px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--leaf-deep);
  font: inherit;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
}

.fp-shop:hover {
  text-decoration: underline;
}

.fp-shop svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fp-stats {
  margin: 0;
  font-size: 13px;
  color: var(--ink-soft);
}

.fp-stats strong {
  color: var(--ink);
  font-weight: 600;
}

.fp-star {
  margin-right: 4px;
  color: var(--gold);
}

.fp-desc {
  display: -webkit-box;
  margin: 2px 0 0;
  overflow: hidden;
  font-size: 14px;
  line-height: 1.6;
  color: var(--ink-soft);
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.fp-buy {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px 12px;
  margin-top: auto;
  padding-top: 14px;
  border-top: 1px solid var(--line-soft);
}

.fp-price {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin: 0;
}

.fp-now {
  font-size: 22px;
  font-weight: 600;
  color: var(--ink);
  font-variant-numeric: tabular-nums;
}

.fp-was {
  font-size: 14px;
  color: var(--ink-soft);
  text-decoration: line-through;
}

.fp-stock {
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 500;
}

.fp-stock.in {
  background: rgba(77, 177, 115, 0.12);
  color: var(--leaf-deep);
}

.fp-stock.low {
  background: rgba(212, 178, 122, 0.2);
  color: #8a6420;
}

.fp-stock.out {
  background: rgba(54, 63, 78, 0.08);
  color: var(--ink-soft);
}

.fp-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 6px;
}

.fp-btn {
  flex: 1 1 140px;
  min-height: 46px;
  padding: 0 20px;
  border-radius: 999px;
  font: inherit;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}

.fp-btn--primary {
  border: 0;
  background: var(--leaf-deep);
  color: #fff;
}

.fp-btn--primary:hover:not(:disabled) {
  background: var(--ink);
}

.fp-btn--ghost {
  border: 1px solid var(--line);
  background: transparent;
  color: var(--ink);
}

.fp-btn--ghost:hover:not(:disabled) {
  border-color: var(--ink);
}

.fp-btn:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.fp-photo:focus-visible,
.fp-name button:focus-visible,
.fp-shop:focus-visible,
.fp-btn:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

@media (max-width: 640px) {
  .fp-card,
  .fp-grid.single .fp-card {
    grid-template-columns: 1fr;
  }

  .fp-photo {
    min-height: 0;
    aspect-ratio: 4 / 3;
  }
}

@media (prefers-reduced-motion: reduce) {
  .fp-photo img,
  .fp-card {
    transition: none;
  }
}
</style>
