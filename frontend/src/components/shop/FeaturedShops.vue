<template>
  <section class="fs" aria-labelledby="fs-title">
    <div class="fs-head">
      <h2 id="fs-title">Flower Shops</h2>
    </div>

    <ul v-if="loading" class="fs-list" aria-label="Loading shops">
      <li v-for="n in 4" :key="n" class="bc-skeleton fs-skel"></li>
    </ul>
    <ul v-else-if="shops.length" class="fs-list">
      <li v-for="shop in ordered" :key="shop.id">
        <article class="fs-card" @click="$emit('visit', shop.id)">
          <img v-if="shop.store_cover_url" :src="shop.store_cover_url" :alt="''" class="fs-cover" loading="lazy" />
          <div v-else class="fs-cover fs-cover--logo">
            <img v-if="shop.store_logo_path" :src="shop.store_logo_path" :alt="''" loading="lazy" />
            <span v-else>{{ shop.store_name?.charAt(0) || "B" }}</span>
          </div>
          <div class="fs-shade" aria-hidden="true"></div>
          <span v-if="nearby[shop.id] != null" class="fs-near">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11z" /><circle cx="12" cy="10" r="2.2" /></svg>
            Near you · {{ distance(nearby[shop.id]) }}
          </span>
          <span v-if="shop.verification_level === 'verified'" class="fs-verified">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
            Verified
          </span>
          <div class="fs-copy">
            <h3>{{ shop.store_name }}</h3>
            <p v-if="shop.store_address" class="fs-place">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11z" /><circle cx="12" cy="10" r="2.2" /></svg>
              {{ shop.store_address }}
            </p>
            <div class="fs-foot">
              <span class="fs-meta">{{ shop.same_day_delivery ? "Same-day delivery" : `From ₱${Math.round(shop.price_min || 0).toLocaleString()}` }}</span>
              <button type="button" class="fs-visit" @click.stop="$emit('visit', shop.id)">Visit shop</button>
            </div>
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
    <div v-else-if="nearKm != null" class="fs-empty fs-empty--row">
      <span>No shops within {{ nearKm }} km of your address yet.</span>
      <button type="button" class="fs-link" @click="$emit('show-all')">Show all shops</button>
    </div>
    <p v-else class="fs-empty">Shops will appear here once they open on BloomCraft.</p>
  </section>
</template>

<script setup>
import { computed } from "vue";

/**
 * Shops as photo cards (cover photo, or the logo on a soft background).
 * Shops near the customer's pinned location come first, tagged "Near you".
 */
const props = defineProps({
  shops: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  nearby: { type: Object, default: () => ({}) }, // shop id → distance in km
  byRoad: { type: Boolean, default: true },
  failed: { type: Boolean, default: false }, // the list couldn't be loaded
  nearKm: { type: Number, default: null }, // only shops this close are asked for
  total: { type: Number, default: 0 }, // shops in all, shown 8 at a time
  loadingMore: { type: Boolean, default: false },
});
defineEmits(["visit", "retry", "show-all", "more"]);

// Near ones first. Shops added by "Explore more" stay after the ones already shown.
const ordered = computed(() => {
  const first = props.shops.slice(0, 8);
  const byNear = (a, b) => (props.nearby[a.id] ?? Infinity) - (props.nearby[b.id] ?? Infinity);
  return [...first.sort(byNear), ...props.shops.slice(8)];
});
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
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.fs-head h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 500;
  color: var(--ink);
}

.fs-list {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 18px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.fs-skel {
  height: 170px;
  border-radius: 12px;
}

.fs-card {
  position: relative;
  height: 170px;
  overflow: hidden;
  border-radius: 12px;
  background: var(--espresso, #2c241f);
  cursor: pointer;
}

.fs-cover {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s var(--ease-out);
}

.fs-card:hover .fs-cover {
  transform: scale(1.05);
}

.fs-cover--logo {
  display: grid;
  place-items: center;
  background: radial-gradient(circle at 30% 30%, #4db173 0%, #6e3a33 100%);
}

.fs-cover--logo {
  align-items: start;
  padding-top: 18px;
}

.fs-cover--logo img {
  width: 58px;
  height: 58px;
  border: 3px solid rgba(250, 251, 250, 0.85);
  border-radius: 50%;
  object-fit: cover;
}

.fs-cover--logo span {
  font-family: var(--font-display);
  font-size: 46px;
  color: var(--paper);
}

.fs-shade {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(30, 36, 46, 0) 30%, rgba(30, 36, 46, 0.78) 100%);
}

.fs-near {
  position: absolute;
  top: 10px;
  left: 10px;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px 4px 8px;
  border-radius: 999px;
  background: var(--leaf-deep);
  font-size: 12px;
  font-weight: 500;
  color: var(--paper);
  box-shadow: 0 6px 14px -8px rgba(54, 63, 78, 0.6);
}

.fs-near svg {
  width: 13px;
  height: 13px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
}

.fs-verified {
  position: absolute;
  top: 10px;
  right: 10px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px 4px 8px;
  border-radius: 999px;
  background: var(--leaf-deep);
  font-size: 12px;
  font-weight: 600;
  color: #fff;
  box-shadow: 0 6px 14px -8px rgba(54, 63, 78, 0.6);
}

.fs-verified svg {
  width: 13px;
  height: 13px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.4;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fs-copy {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  padding: 12px 14px;
  color: var(--paper);
}

.fs-copy h3 {
  overflow: hidden;
  margin: 0 0 2px;
  font-family: var(--font-display);
  font-size: 19px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--paper);
}

.fs-place {
  display: flex;
  align-items: center;
  gap: 4px;
  overflow: hidden;
  margin: 0 0 8px;
  font-size: 12.5px;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: rgba(250, 251, 250, 0.85);
}

.fs-place svg {
  width: 13px;
  height: 13px;
  flex-shrink: 0;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
}

.fs-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.fs-meta {
  font-size: 12.5px;
  color: rgba(250, 251, 250, 0.85);
}

.fs-visit {
  flex-shrink: 0;
  height: 28px;
  padding: 0 12px;
  border: 0;
  border-radius: 999px;
  background: var(--paper);
  color: var(--ink);
  font: 500 12.5px var(--font-sans);
  cursor: pointer;
}

.fs-visit:hover {
  background: var(--leaf-deep);
  color: var(--paper);
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

@media (max-width: 960px) {
  .fs-list {
    display: flex;
    overflow-x: auto;
    scrollbar-width: thin;
  }

  .fs-list > li {
    flex: 0 0 240px;
  }
}
</style>
