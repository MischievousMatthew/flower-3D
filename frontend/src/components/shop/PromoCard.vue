<template>
  <!-- Promotions (Admin → Promotion Management), one at a time -->
  <aside v-if="promotions.length" class="so-promo" :class="{ 'has-image': promo.image_url }" aria-label="Offers">
    <img v-if="promo.image_url" :src="promo.image_url" :alt="''" class="so-promo-img" />
    <div class="so-promo-copy">
      <p class="so-promo-label">{{ promo.ends_at ? `Ends ${endsLabel(promo.ends_at)}` : "Limited time offer" }}</p>
      <h3>{{ promo.title }}</h3>
      <p v-if="promo.message" class="so-promo-msg">{{ promo.message }}</p>
      <div class="so-promo-actions">
        <button v-if="promo.link_type" type="button" class="so-pill" @click="openLink(promo)">
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
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useSiteContent } from "../../composables/useSiteContent";

/**
 * The promotion card beside "Shop by occasion". With `preview` (Admin →
 * Promotion Management review step) the button doesn't navigate.
 */
const props = defineProps({
  promotions: { type: Array, default: () => [] },
  preview: { type: Boolean, default: false },
});
const router = useRouter();
const { followLink } = useSiteContent();

const index = ref(0);
const promo = computed(() => props.promotions[index.value] || props.promotions[0] || {});
const step = (d) => {
  const n = props.promotions.length;
  index.value = (((index.value + d) % n) + n) % n;
};
watch(() => props.promotions.length, () => (index.value = 0));

const openLink = (item) => {
  if (!props.preview) followLink(router, item);
};

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
.so-promo {
  position: relative;
  display: flex;
  align-items: center;
  min-height: 210px;
  overflow: hidden;
  border: 1px solid var(--line-soft);
  border-radius: 16px;
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

.so-promo :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

@media (max-width: 560px) {
  .so-promo-copy {
    max-width: 100%;
  }

  .so-promo-img {
    opacity: 0.35;
    width: 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .so-pill {
    transition: none;
  }
}
</style>
