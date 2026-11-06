<template>
  <section class="so" :class="{ 'so--solo': !promotions.length }" aria-label="Shop by occasion">
    <div class="bc-container so-grid">
      <div class="so-card">
        <div class="so-head">
          <h2>Shop by Occasion</h2>
          <p>Find the perfect flowers for your special moment.</p>
          <button type="button" class="so-pill" @click="$emit('browse')">Browse all occasions <span aria-hidden="true">→</span></button>
        </div>

        <ul v-if="loading" class="so-list" aria-label="Loading occasions">
          <li v-for="n in 6" :key="n" class="so-item">
            <span class="so-photo bc-skeleton"></span>
            <span class="bc-skeleton so-skel"></span>
          </li>
        </ul>
        <div v-if="!loading && occasions.length && scrollable" class="so-arrows">
          <button type="button" class="so-arrow" aria-label="Previous occasions" :disabled="atStart" @click="slide(-1)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
          </button>
          <button type="button" class="so-arrow" aria-label="More occasions" :disabled="atEnd" @click="slide(1)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
          </button>
        </div>
        <ul v-if="!loading && occasions.length" ref="listEl" class="so-list" :class="{ 'at-end': atEnd }" @scroll.passive="measure">
          <li v-for="occasion in occasions" :key="occasion">
            <button
              type="button"
              class="so-item"
              :class="{ active: active === occasion }"
              :aria-pressed="active === occasion"
              @click="$emit('pick', occasion)"
            >
              <span class="so-photo">
                <img v-if="usePhotos && images[occasion]" :src="images[occasion]" :alt="''" loading="lazy" />
                <span v-else class="so-icon" v-html="occasionIconFor(occasion)"></span>
              </span>
              <span class="so-name">{{ occasion }}</span>
            </button>
          </li>
        </ul>
        <p v-if="!loading && !occasions.length" class="so-empty">Occasions appear here as shops tag their flowers.</p>
      </div>

      <PromoCard :promotions="promotions" />
    </div>
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { occasionIconFor } from "../../constants/occasionIcons.js";
import PromoCard from "./PromoCard.vue";

/**
 * Occasion circles (photo from a product tagged with that occasion) beside
 * the admin's promotions, shown one at a time.
 */
const props = defineProps({
  occasions: { type: Array, default: () => [] },
  images: { type: Object, default: () => ({}) },
  active: { type: String, default: null },
  loading: { type: Boolean, default: false },
  promotions: { type: Array, default: () => [] },
});
defineEmits(["pick", "browse"]);

// Product photos only when they tell the occasions apart (several different
// photos); otherwise every occasion gets its own drawn icon
const usePhotos = computed(() => {
  const photos = props.occasions.map((name) => props.images[name]).filter(Boolean);
  return photos.length >= 4 && new Set(photos).size === photos.length;
});

// Arrows show when the row doesn't fit
const listEl = ref(null);
const scrollable = ref(false);
const atStart = ref(true);
const atEnd = ref(false);
function measure() {
  const el = listEl.value;
  if (!el) return;
  scrollable.value = el.scrollWidth > el.clientWidth + 4;
  atStart.value = el.scrollLeft <= 4;
  atEnd.value = el.scrollLeft + el.clientWidth >= el.scrollWidth - 4;
}
function slide(direction) {
  const el = listEl.value;
  if (el) el.scrollBy({ left: direction * Math.max(el.clientWidth * 0.7, 200), behavior: "smooth" });
}
let resize = null;
onMounted(() => {
  resize = new ResizeObserver(measure);
  watch(listEl, (el) => el && resize.observe(el), { immediate: true });
});
onBeforeUnmount(() => resize?.disconnect());
watch(
  () => [props.occasions.length, props.loading],
  async () => {
    await nextTick();
    measure();
  },
);

</script>

<style scoped>
.so,
.so * {
  box-sizing: border-box;
}

.so {
  margin-top: 22px;
}

.so-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr);
  gap: 22px;
}

.so--solo .so-grid {
  grid-template-columns: minmax(0, 1fr);
}

.so-card {
  border: 1px solid var(--line-soft);
  border-radius: 16px;
  padding: 26px 30px;
  background: var(--paper);
}

.so-head h2 {
  margin: 0 0 4px;
  font-family: var(--font-display);
  font-size: 30px;
  font-weight: 500;
  color: var(--ink);
}

.so-head p {
  margin: 0 0 14px;
  font-size: 13px;
  color: var(--ink-soft);
}

.so-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 36px;
  padding: 0 18px;
  border: 0;
  border-radius: 999px;
  background: var(--leaf-deep);
  color: var(--paper);
  font: 500 12.5px var(--font-sans);
  cursor: pointer;
  transition: background 0.2s ease;
}

.so-pill:hover {
  background: var(--ink);
}

.so-list {
  display: flex;
  gap: 18px;
  margin: 22px 0 0;
  padding: 0 0 4px;
  overflow-x: auto;
  list-style: none;
  scroll-behavior: smooth;
  scrollbar-width: none;
  /* Fade the last circle so it reads as "more to scroll" (not once at the end) */
  -webkit-mask-image: linear-gradient(90deg, #000 88%, transparent);
  mask-image: linear-gradient(90deg, #000 88%, transparent);
}

.so-list::-webkit-scrollbar {
  display: none;
}

.so-list.at-end {
  -webkit-mask-image: none;
  mask-image: none;
}

.so-card {
  position: relative;
}

.so-arrows {
  position: absolute;
  top: 26px;
  right: 30px;
  display: flex;
  gap: 8px;
}

.so-arrow {
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

.so-arrow:hover:not(:disabled) {
  border-color: var(--ink);
}

.so-arrow:disabled {
  cursor: default;
  opacity: 0.35;
}

.so-arrow:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

.so-arrow svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.so-icon {
  display: grid;
  place-items: center;
  color: var(--leaf-deep);
}

.so-icon :deep(svg) {
  width: 30px;
  height: 30px;
  stroke-width: 1.5;
}

@media (max-width: 640px) {
  .so-arrows {
    display: none;
  }
}

.so-item {
  display: grid;
  justify-items: center;
  gap: 8px;
  width: 84px;
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  cursor: pointer;
}

.so-photo {
  display: grid;
  place-items: center;
  width: 68px;
  height: 68px;
  overflow: hidden;
  border: 2px solid transparent;
  border-radius: 50%;
  background: #f3e3dd;
  transition: border-color 0.2s ease, transform 0.2s ease;
}

.so-item:hover .so-photo {
  transform: translateY(-2px);
}

.so-item.active .so-photo {
  border-color: var(--leaf);
}

.so-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.so-photo svg {
  width: 30px;
  height: 30px;
  fill: none;
  stroke: var(--leaf-deep);
  stroke-width: 1.4;
}

.so-name {
  font-size: 12px;
  text-align: center;
  color: var(--ink);
}

.so-skel {
  width: 56px;
  height: 9px;
  border-radius: 4px;
}

.so-empty {
  margin: 20px 0 0;
  font-size: 13px;
  color: var(--ink-soft);
}

.so :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

@media (max-width: 960px) {
  .so-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 560px) {
  .so-card {
    padding: 22px 18px;
  }
}
</style>
