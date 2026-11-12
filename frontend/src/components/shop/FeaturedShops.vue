<template>
  <section class="fs" aria-labelledby="fs-title">
    <header class="fs-head">
      <h2 id="fs-title">All flower shops <small v-if="total">{{ total }}</small></h2>
      <p>Nearest first when you've pinned your address.</p>
    </header>

    <ul v-if="loading" class="fs-list" aria-label="Loading shops">
      <li v-for="n in 4" :key="n" class="bc-skeleton fs-skel"></li>
    </ul>
    <ul v-else-if="shops.length" class="fs-list">
      <li v-for="shop in ordered" :key="shop.id">
        <article class="fs-card" @click="$emit('visit', shop.id)">
          <!-- Cover photo, or a soft panel with the shop's initial -->
          <div class="fs-media">
            <img v-if="shop.store_cover_url" :src="shop.store_cover_url" alt="" class="fs-cover" loading="lazy" decoding="async" />
            <span v-else class="fs-cover fs-cover--empty" aria-hidden="true">{{ shop.store_name?.charAt(0) || "B" }}</span>
            <span v-if="nearby[shop.id] != null" class="fs-near">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11z" /><circle cx="12" cy="10" r="2.2" /></svg>
              Near you · {{ distance(nearby[shop.id]) }}
            </span>
          </div>

          <div class="fs-body">
            <span class="fs-logo">
              <img v-if="shop.store_logo_path" :src="shop.store_logo_path" alt="" loading="lazy" />
              <span v-else aria-hidden="true">{{ shop.store_name?.charAt(0) || "B" }}</span>
            </span>

            <div class="fs-name-row">
              <h3>{{ shop.store_name }}</h3>
              <span v-if="shop.verification_level === 'verified'" class="fs-verified" title="Verified florist">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                <span class="bc-sr-only">Verified</span>
              </span>
            </div>
            <p v-if="shop.store_address" class="fs-place">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11z" /><circle cx="12" cy="10" r="2.2" /></svg>
              <span>{{ shop.store_address }}</span>
            </p>

            <ul class="fs-tags">
              <li v-if="nearby[shop.id] == null && distances[shop.id] != null">{{ distance(distances[shop.id]) }}</li>
              <li v-if="shop.same_day_delivery" class="green">Same-day delivery</li>
              <li v-if="Number(shop.price_min) > 0">From ₱{{ Math.round(shop.price_min).toLocaleString() }}</li>
              <li v-if="shop.taking_orders === false" class="paused">Not taking orders now</li>
            </ul>

            <button type="button" class="fs-visit" @click.stop="$emit('visit', shop.id)">
              Visit shop
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            </button>
          </div>
        </article>
      </li>
      <!-- The next shops on their way -->
      <template v-if="loadingMore">
        <li v-for="n in Math.min(4, remaining)" :key="`more-${n}`" class="bc-skeleton fs-skel" aria-hidden="true"></li>
      </template>
    </ul>
    <div v-if="!loading && shops.length && remaining > 0" class="fs-more">
      <button type="button" class="fs-more-btn" :disabled="loadingMore" @click="$emit('more')">
        {{ loadingMore ? "Loading shops…" : "Explore more shops" }}
        <span v-if="!loadingMore" class="fs-more-count">{{ remaining }} more</span>
        <svg v-if="!loadingMore" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
      </button>
    </div>
    <div v-else-if="failed" class="fs-empty fs-empty--row" role="alert">
      <span>We couldn't load the shops just now.</span>
      <button type="button" class="fs-link" @click="$emit('retry')">Try again</button>
    </div>
    <p v-else-if="!loading && !shops.length" class="fs-empty">Shops will appear here once they open on BloomCraft.</p>
  </section>
</template>

<script setup>
import { computed } from "vue";

/**
 * Shops as cards: the cover photo on top, the logo overlapping it, then the
 * name, address and what they offer. Shops near the customer's pinned
 * location come first, tagged "Near you".
 */
const props = defineProps({
  shops: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  nearby: { type: Object, default: () => ({}) }, // shops inside the map's circle: id → km (tagged "Near you")
  distances: { type: Object, default: () => ({}) }, // every shop: id → km, as the map measures it
  byRoad: { type: Boolean, default: true },
  failed: { type: Boolean, default: false }, // the list couldn't be loaded
  total: { type: Number, default: 0 }, // shops in all, shown 8 at a time
  loadingMore: { type: Boolean, default: false },
});
defineEmits(["visit", "retry", "more"]);

// The page asks the server for the nearest shops first, so the order is kept as is
const ordered = computed(() => props.shops);
const remaining = computed(() => Math.max(0, props.total - props.shops.length));
const distance = (km) => {
  const text = km < 1 ? `${Math.round(km * 1000)} m` : `${Number(km).toFixed(1)} km`;
  return props.byRoad ? text : `${text} away`;
};
</script>

<style scoped>
.fs,
.fs * {
  box-sizing: border-box;
}

.fs-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 6px 16px;
  margin-bottom: 18px;
}

.fs-head h2 {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  font-family: var(--font-display);
  font-size: clamp(26px, 2.6vw, 32px);
  font-weight: 500;
  color: var(--ink);
}

.fs-head h2 small {
  padding: 2px 10px;
  border-radius: 999px;
  background: var(--ivory-deep);
  font-family: var(--font-sans);
  font-size: 12.5px;
  font-weight: 600;
  color: var(--ink);
}

.fs-head p {
  margin: 0;
  font-size: 13px;
  color: var(--ink-soft);
}

.fs-list {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.fs-skel {
  height: 320px;
  border-radius: 22px;
}

.fs-card {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
  border: 1px solid var(--line-soft);
  border-radius: 22px;
  background: var(--paper);
  cursor: pointer;
  transition: box-shadow 0.25s ease, transform 0.25s ease;
}

.fs-card:hover {
  box-shadow: 0 22px 44px -28px rgba(38, 45, 57, 0.45);
  transform: translateY(-3px);
}

.fs-media {
  position: relative;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  background: var(--ivory-deep);
}

.fs-cover {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s var(--ease-out);
}

.fs-card:hover .fs-cover {
  transform: scale(1.05);
}

.fs-cover--empty {
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, #dcefe2, #f6ece6);
  font-family: var(--font-display);
  font-size: 72px;
  font-style: italic;
  color: rgba(46, 122, 79, 0.35);
}

.fs-near {
  position: absolute;
  top: 12px;
  left: 12px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 11px 5px 8px;
  border-radius: 999px;
  background: var(--leaf-deep);
  font-size: 12px;
  font-weight: 500;
  color: var(--paper);
  box-shadow: 0 6px 14px -8px rgba(38, 45, 57, 0.6);
}

.fs-near svg {
  width: 13px;
  height: 13px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
}

.fs-body {
  position: relative;
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 6px;
  padding: 34px 16px 16px;
}

/* The logo sits half over the photo */
.fs-logo {
  position: absolute;
  top: -28px;
  left: 16px;
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  overflow: hidden;
  border: 3px solid var(--paper);
  border-radius: 16px;
  background: #e9f5ed;
  box-shadow: 0 8px 18px -10px rgba(38, 45, 57, 0.5);
  font-family: var(--font-display);
  font-size: 26px;
  font-style: italic;
  color: var(--leaf-deep);
}

.fs-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.fs-name-row {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.fs-name-row h3 {
  overflow: hidden;
  margin: 0;
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 500;
  line-height: 1.15;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--ink);
}

.fs-verified {
  display: grid;
  flex: none;
  place-items: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--leaf);
  color: var(--paper);
}

.fs-verified svg {
  width: 12px;
  height: 12px;
  fill: none;
  stroke: currentColor;
  stroke-width: 3;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fs-place {
  display: flex;
  align-items: flex-start;
  gap: 5px;
  margin: 0;
  font-size: 12.5px;
  line-height: 1.45;
  color: var(--ink-soft);
}

.fs-place span {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.fs-place svg {
  flex-shrink: 0;
  width: 13px;
  height: 13px;
  margin-top: 2px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
}

.fs-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 4px 0 0;
  padding: 0;
  list-style: none;
}

.fs-tags li {
  padding: 3px 9px;
  border-radius: 999px;
  background: var(--ivory);
  font-size: 11.5px;
  font-weight: 500;
  color: var(--ink);
}

.fs-tags li.green {
  background: #e9f5ed;
  color: var(--leaf-deep);
}

.fs-tags li.paused {
  background: #fdf4e4;
  color: #8a5a10;
}

.fs-visit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 44px;
  margin-top: auto;
  padding: 0 16px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--paper);
  color: var(--ink);
  font: 500 13.5px var(--font-sans);
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.fs-tags + .fs-visit,
.fs-place + .fs-visit {
  margin-top: 10px;
}

.fs-card:hover .fs-visit,
.fs-visit:hover {
  border-color: var(--ink);
  background: var(--ink);
  color: var(--paper);
}

.fs-visit svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fs-empty {
  margin: 0;
  font-size: 13px;
  color: var(--ink-soft);
}

.fs-more {
  display: flex;
  justify-content: center;
  margin-top: 26px;
}

.fs-more-btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 48px;
  padding: 0 22px 0 26px;
  border: 1px solid var(--ink);
  border-radius: 999px;
  background: transparent;
  font: inherit;
  font-size: 14px;
  font-weight: 500;
  color: var(--ink);
  cursor: pointer;
  transition: background 0.2s var(--ease-out), color 0.2s var(--ease-out);
}

.fs-more-btn:hover:not(:disabled) {
  background: var(--ink);
  color: #fff;
}

.fs-more-btn:disabled {
  opacity: 0.7;
  cursor: default;
}

.fs-more-count {
  padding: 2px 9px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--leaf) 16%, transparent);
  font-size: 12px;
  color: var(--leaf-deep);
}

.fs-more-btn:hover:not(:disabled) .fs-more-count {
  background: rgba(255, 255, 255, 0.16);
  color: #fff;
}

.fs-more-btn svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fs-empty--row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 12px;
}

.fs-link {
  min-height: 44px;
  padding: 0 4px;
  border: 0;
  background: none;
  font: inherit;
  font-weight: 500;
  color: var(--leaf-deep);
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}

.fs-link:hover {
  color: var(--ink);
}

.fs :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

@media (max-width: 1080px) {
  .fs-list {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 860px) {
  .fs-list {
    display: flex;
    overflow-x: auto;
    padding-bottom: 8px;
    scroll-snap-type: x mandatory;
    scrollbar-width: thin;
  }

  .fs-list > li {
    flex: 0 0 min(280px, 78%);
    scroll-snap-align: start;
  }
}

@media (prefers-reduced-motion: reduce) {
  .fs-card,
  .fs-cover,
  .fs-visit {
    transition: none;
  }

  .fs-card:hover {
    transform: none;
  }
}
</style>
