<template>
  <section class="fs" aria-labelledby="fs-title">
    <div class="fs-head">
      <h2 id="fs-title">Featured Flower Shops</h2>
      <button type="button" class="fs-all" @click="$emit('view-all')">View all shops <span aria-hidden="true">→</span></button>
    </div>

    <ul v-if="loading" class="fs-list" aria-label="Loading shops">
      <li v-for="n in 4" :key="n" class="bc-skeleton fs-skel"></li>
    </ul>
    <ul v-else-if="shops.length" class="fs-list">
      <li v-for="shop in shops.slice(0, 4)" :key="shop.id">
        <article class="fs-card" @click="$emit('visit', shop.id)">
          <img v-if="shop.store_cover_url" :src="shop.store_cover_url" :alt="''" class="fs-cover" loading="lazy" />
          <div v-else class="fs-cover fs-cover--logo">
            <img v-if="shop.store_logo_path" :src="shop.store_logo_path" :alt="''" loading="lazy" />
            <span v-else>{{ shop.store_name?.charAt(0) || "B" }}</span>
          </div>
          <div class="fs-shade" aria-hidden="true"></div>
          <span v-if="shop.verification_level === 'verified'" class="fs-verified">Verified</span>
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
    </ul>
    <p v-else class="fs-empty">Shops will appear here once they open on BloomCraft.</p>
  </section>
</template>

<script setup>
/** Four shops as photo cards (cover photo, or the logo on a soft background) */
defineProps({
  shops: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});
defineEmits(["visit", "view-all"]);
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

.fs-all {
  padding: 0;
  border: 0;
  background: none;
  font: 500 12.5px var(--font-sans);
  color: var(--rosewood-dark);
  cursor: pointer;
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
  background: radial-gradient(circle at 30% 30%, #b86558 0%, #6e3a33 100%);
}

.fs-cover--logo {
  align-items: start;
  padding-top: 18px;
}

.fs-cover--logo img {
  width: 58px;
  height: 58px;
  border: 3px solid rgba(250, 248, 244, 0.85);
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
  background: linear-gradient(180deg, rgba(20, 15, 12, 0) 30%, rgba(20, 15, 12, 0.78) 100%);
}

.fs-verified {
  position: absolute;
  top: 10px;
  right: 10px;
  padding: 3px 9px;
  border-radius: 999px;
  background: rgba(250, 248, 244, 0.92);
  font-size: 10.5px;
  font-weight: 500;
  color: var(--rosewood-dark);
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
  font-size: 11.5px;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: rgba(250, 248, 244, 0.85);
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
  font-size: 11.5px;
  color: rgba(250, 248, 244, 0.85);
}

.fs-visit {
  flex-shrink: 0;
  height: 28px;
  padding: 0 12px;
  border: 0;
  border-radius: 999px;
  background: var(--paper);
  color: var(--ink);
  font: 500 11.5px var(--font-sans);
  cursor: pointer;
}

.fs-visit:hover {
  background: var(--rosewood-dark);
  color: var(--paper);
}

.fs-empty {
  margin: 0;
  font-size: 13px;
  color: var(--ink-soft);
}

.fs :focus-visible {
  outline: 2px solid var(--rosewood);
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
