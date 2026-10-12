<template>
  <section v-if="promotions.length" class="promos" aria-labelledby="promos-title">
    <div class="bc-container">
      <div class="bc-section-head promos-head">
        <p class="bc-label">Promotions</p>
        <h2 id="promos-title">Offers <em>in bloom</em></h2>
      </div>
      <ul class="promo-grid">
        <li v-for="promo in promotions" :key="promo.id" class="promo" :class="{ 'has-image': promo.image_url }">
          <img v-if="promo.image_url" :src="promo.image_url" :alt="promo.title" class="promo-img" loading="lazy" />
          <div class="promo-body">
            <h3>{{ promo.title }}</h3>
            <p v-if="promo.message" class="promo-msg">{{ promo.message }}</p>
            <div class="promo-foot">
              <button v-if="promo.promo_code" type="button" class="promo-code" :aria-label="`Copy code ${promo.promo_code}`" @click="copy(promo.promo_code)">
                <span>{{ copied === promo.promo_code ? "Copied!" : promo.promo_code }}</span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>
              </button>
              <button v-if="promo.link_type" type="button" class="bc-link promo-link" @click="followLink(router, promo)">
                {{ promo.link_label || "Shop the offer" }} →
              </button>
            </div>
            <p v-if="promo.ends_at" class="promo-ends">Ends {{ endsLabel(promo.ends_at) }}</p>
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useSiteContent } from "../../composables/useSiteContent";

/** Promotions from Admin → Website */
defineProps({ promotions: { type: Array, required: true } });
const router = useRouter();
const { followLink } = useSiteContent();
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
.promos {
  padding: clamp(40px, 6vw, 80px) 0;
}
.promos-head {
  margin-bottom: 32px;
}
.promo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.promo {
  display: flex;
  flex-direction: column;
  border-radius: 22px;
  overflow: hidden;
  background: var(--paper);
  border: 1px solid var(--line);
}
.promo:not(.has-image) {
  background: linear-gradient(140deg, #f7e7e1 0%, var(--paper) 70%);
}
.promo-img {
  width: 100%;
  aspect-ratio: 16 / 9;
  object-fit: cover;
}
.promo-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  flex: 1;
}
.promo-body h3 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 30px;
  font-weight: 500;
  line-height: 1.1;
}
.promo-msg {
  margin: 0;
  color: var(--ink-soft);
  font-size: 14.5px;
}
.promo-foot {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  margin-top: auto;
  padding-top: 6px;
}
.promo-code {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1.5px dashed var(--rosewood);
  background: #fff;
  color: var(--rosewood-dark);
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  font-size: 13.5px;
  font-weight: 600;
  letter-spacing: 0.06em;
  cursor: pointer;
}
.promo-link {
  border: none;
  background: none;
  padding: 0;
  font: inherit;
  cursor: pointer;
}
.promo-ends {
  margin: 0;
  font-size: 12px;
  color: var(--ink-soft);
}
</style>
