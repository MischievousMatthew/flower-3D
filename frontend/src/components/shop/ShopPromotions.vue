<template>
  <!-- Discount campaigns and offers (Admin → Promotion Management), plus the live sale -->
  <section class="sp" aria-label="Offers and promotions">
    <div class="bc-container sp-grid" :class="`n${cards.length}`">
      <article
        v-for="(card, i) in cards"
        :key="card.key"
        class="sp-card"
        :class="[`tone-${i % 3}`, { 'has-image': card.image }]"
      >
        <img v-if="card.image" :src="card.image" alt="" class="sp-img" loading="lazy" decoding="async" />
        <div class="sp-copy">
          <p class="sp-label">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12V4h8l10 10-8 8z" /><circle cx="7.5" cy="8.5" r="1.3" /></svg>
            {{ card.label }}
          </p>
          <h3>{{ card.title }}</h3>
          <p v-if="card.message" class="sp-msg">{{ card.message }}</p>
          <div class="sp-actions">
            <button v-if="card.action" type="button" class="sp-btn" @click="card.action()">
              {{ card.actionLabel }} <span aria-hidden="true">→</span>
            </button>
            <button v-if="card.code" type="button" class="sp-code" :aria-label="`Copy code ${card.code}`" @click="copy(card.code)">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2" /><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3" /></svg>
              {{ copied === card.code ? "Copied!" : card.code }}
            </button>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import { useSiteContent } from "../../composables/useSiteContent";

/**
 * Up to three offer cards under the hero: the admin's promotions (with their
 * code to copy), topped up with "Flowers on sale" and the 3D designer.
 */
const props = defineProps({
  promotions: { type: Array, default: () => [] },
});
const emit = defineEmits(["sale", "design"]);
const router = useRouter();
const { followLink } = useSiteContent();

const endsLabel = (value) => new Date(value).toLocaleDateString("en-PH", { month: "long", day: "numeric" });
const cards = computed(() => {
  const list = props.promotions.slice(0, 3).map((p) => ({
    key: `promo-${p.id ?? p.title}`,
    label: p.ends_at ? `Ends ${endsLabel(p.ends_at)}` : "Limited time offer",
    title: p.title,
    message: p.message,
    image: p.image_url,
    code: p.promo_code,
    action: p.link_type ? () => followLink(router, p) : null,
    actionLabel: p.link_label || "Shop the offer",
  }));
  if (list.length < 3)
    list.push({
      key: "sale",
      label: "Price drops",
      title: "Flowers on sale",
      message: "Fresh blooms from local shops, selling below their usual price right now.",
      action: () => emit("sale"),
      actionLabel: "Shop the sale",
    });
  if (list.length < 3)
    list.push({
      key: "design",
      label: "Only on BloomCraft",
      title: "Design it in 3D",
      message: "Pick the flowers, wrap and ribbon, and see your bouquet before you order.",
      action: () => emit("design"),
      actionLabel: "Start designing",
    });
  return list;
});

const copied = ref(null);
async function copy(code) {
  try {
    await navigator.clipboard.writeText(code);
    copied.value = code;
    setTimeout(() => (copied.value = null), 1600);
  } catch {
    // clipboard unavailable; the code is visible anyway
  }
}
</script>

<style scoped>
.sp {
  padding-top: 24px;
}

.sp-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}

.sp-grid.n1 {
  grid-template-columns: minmax(0, 1fr);
}

.sp-grid.n2 {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.sp-card {
  position: relative;
  display: flex;
  min-height: 190px;
  overflow: hidden;
  border-radius: 20px;
  isolation: isolate;
}

.tone-0 {
  background: linear-gradient(135deg, #dcefe2, #f1f8f3);
}

.tone-1 {
  background: linear-gradient(135deg, #f8e4dc, #fdf3ef);
}

.tone-2 {
  background: linear-gradient(135deg, #e3e6ef, #f4f5f9);
}

.sp-img {
  position: absolute;
  inset: 0;
  z-index: -2;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.has-image::after {
  content: "";
  position: absolute;
  inset: 0;
  z-index: -1;
  background: linear-gradient(90deg, rgba(38, 45, 57, 0.82), rgba(38, 45, 57, 0.25));
}

.sp-copy {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  padding: 24px 26px;
}

.sp-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--leaf-deep);
}

.sp-label svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linejoin: round;
}

.sp-copy h3 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 600;
  line-height: 1.1;
  color: var(--ink);
}

.sp-msg {
  max-width: 34ch;
  margin: 0;
  font-size: 13px;
  line-height: 1.55;
  color: var(--ink-soft);
}

.has-image .sp-label {
  color: var(--leaf-light);
}

.has-image h3 {
  color: var(--paper);
}

.has-image .sp-msg {
  color: rgba(250, 251, 250, 0.85);
}

.sp-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: auto;
  padding-top: 10px;
}

.sp-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 20px;
  border: 0;
  border-radius: 999px;
  background: var(--ink);
  color: var(--paper);
  font: 500 13px var(--font-sans);
  cursor: pointer;
  transition: background 0.2s ease, transform 0.2s ease;
}

.sp-btn:hover {
  background: var(--leaf-deep);
}

.has-image .sp-btn {
  background: var(--paper);
  color: var(--ink);
}

.sp-code {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 44px;
  padding: 0 16px;
  border: 1.5px dashed currentColor;
  border-radius: 999px;
  background: transparent;
  color: var(--ink);
  font: 600 13px var(--font-sans);
  letter-spacing: 0.06em;
  cursor: pointer;
}

.has-image .sp-code {
  color: var(--paper);
}

.sp-code svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
}

.sp :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

@media (max-width: 960px) {
  .sp-grid,
  .sp-grid.n2 {
    display: flex;
    overflow-x: auto;
    padding-bottom: 6px;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
  }

  .sp-card {
    flex: 0 0 min(340px, 84%);
    scroll-snap-align: start;
  }
}
</style>
