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
        <ul v-else-if="occasions.length" class="so-list">
          <li v-for="occasion in occasions" :key="occasion">
            <button
              type="button"
              class="so-item"
              :class="{ active: active === occasion }"
              :aria-pressed="active === occasion"
              @click="$emit('pick', occasion)"
            >
              <span class="so-photo">
                <img v-if="images[occasion]" :src="images[occasion]" :alt="''" loading="lazy" />
                <svg v-else viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 7.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 1 1 0 5zM16.5 12a2.5 2.5 0 1 1 5 0 2.5 2.5 0 1 1-5 0zM12 16.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 1 1 0-5zM7.5 12a2.5 2.5 0 1 1-5 0 2.5 2.5 0 1 1 5 0z" />
                  <circle cx="12" cy="12" r="2" />
                </svg>
              </span>
              <span class="so-name">{{ occasion }}</span>
            </button>
          </li>
        </ul>
        <p v-else class="so-empty">Occasions appear here as shops tag their flowers.</p>
      </div>

      <!-- Promotions (Admin → Website), one at a time -->
      <aside v-if="promotions.length" class="so-promo" :class="{ 'has-image': promo.image_url }" aria-label="Offers">
        <img v-if="promo.image_url" :src="promo.image_url" :alt="''" class="so-promo-img" />
        <div class="so-promo-copy">
          <p class="so-promo-label">{{ promo.ends_at ? `Ends ${endsLabel(promo.ends_at)}` : "Limited time offer" }}</p>
          <h3>{{ promo.title }}</h3>
          <p v-if="promo.message" class="so-promo-msg">{{ promo.message }}</p>
          <div class="so-promo-actions">
            <button v-if="promo.link_type" type="button" class="so-pill" @click="followLink(router, promo)">
              {{ promo.link_label || "Shop the offer" }} <span aria-hidden="true">→</span>
            </button>
            <button v-if="promo.promo_code" type="button" class="so-code" :aria-label="`Copy code ${promo.promo_code}`" @click="copy(promo.promo_code)">
              {{ copied === promo.promo_code ? "Copied!" : promo.promo_code }}
            </button>
          </div>
        </div>
        <div v-if="promotions.length > 1" class="so-promo-nav">
          <button type="button" aria-label="Previous offer" @click="step(-1)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg></button>
          <button type="button" aria-label="Next offer" @click="step(1)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg></button>
        </div>
      </aside>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useSiteContent } from "../../composables/useSiteContent";

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
const router = useRouter();
const { followLink } = useSiteContent();

const index = ref(0);
const promo = computed(() => props.promotions[index.value] || props.promotions[0] || {});
const step = (d) => {
  const n = props.promotions.length;
  index.value = (((index.value + d) % n) + n) % n;
};
watch(() => props.promotions.length, () => (index.value = 0));

const copied = ref(null);
const copy = async (code) => {
  try {
    await navigator.clipboard.writeText(code);
    copied.value = code;
    setTimeout(() => (copied.value = null), 1600);
  } catch {
    // clipboard unavailable; the code is visible anyway
  }
};
const endsLabel = (value) => new Date(value).toLocaleDateString("en-PH", { month: "long", day: "numeric" });
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

.so-card,
.so-promo {
  border: 1px solid var(--line-soft);
  border-radius: 16px;
}

.so-card {
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
  scrollbar-width: thin;
  /* Fade the last circle so it reads as "more to scroll" */
  -webkit-mask-image: linear-gradient(90deg, #000 88%, transparent);
  mask-image: linear-gradient(90deg, #000 88%, transparent);
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

.so-promo {
  position: relative;
  display: flex;
  align-items: center;
  min-height: 210px;
  overflow: hidden;
  background: linear-gradient(120deg, #dcefe2 0%, #ecf6ef 55%, #e2f1e7 100%);
}

.so-promo-img {
  position: absolute;
  top: 0;
  right: 0;
  width: 58%;
  height: 100%;
  object-fit: cover;
  -webkit-mask-image: linear-gradient(90deg, transparent 0%, #000 35%);
  mask-image: linear-gradient(90deg, transparent 0%, #000 35%);
}

.so-promo-copy {
  position: relative;
  max-width: 62%;
  padding: 26px;
}

.so-promo:not(.has-image) .so-promo-copy {
  max-width: 100%;
}

.so-promo-label {
  margin: 0 0 6px;
  font-size: 12px;
  color: var(--leaf-deep);
}

.so-promo h3 {
  margin: 0 0 8px;
  font-family: var(--font-display);
  font-size: 32px;
  font-weight: 500;
  line-height: 1.1;
  color: var(--leaf-deep);
}

.so-promo-msg {
  margin: 0 0 16px;
  font-size: 13px;
  line-height: 1.6;
  color: var(--ink);
}

.so-promo-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.so-code {
  height: 36px;
  padding: 0 14px;
  border: 1px dashed var(--leaf-deep);
  border-radius: 999px;
  background: rgba(250, 251, 250, 0.7);
  color: var(--leaf-deep);
  font: 600 12px var(--font-sans);
  letter-spacing: 0.08em;
  cursor: pointer;
}

.so-promo-nav {
  position: absolute;
  right: 14px;
  bottom: 14px;
  display: flex;
  gap: 6px;
}

.so-promo-nav button {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: rgba(250, 251, 250, 0.92);
  color: var(--ink);
  cursor: pointer;
}

.so-promo-nav svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
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

  .so-promo-copy {
    max-width: 100%;
  }

  .so-promo-img {
    opacity: 0.35;
    width: 100%;
  }
}
</style>
