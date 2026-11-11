<template>
  <section class="fp" id="featured" aria-labelledby="fp-title">
    <div class="bc-container fp-layout">
      <div class="fp-intro">
        <h2 id="fp-title">In bloom this <em>{{ seasonMonth }}</em></h2>
        <p class="fp-lede">Flowers at their best right now, picked by the BloomCraft team from shops near you.</p>
        <ul class="fp-notes">
          <li>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.4 6.7 19.4l1.2-6L3.4 9.3l6-.7z" /></svg>
            Picked for the season
          </li>
          <li>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10h16l-1.2-4.8A1.6 1.6 0 0 0 17.2 4H6.8a1.6 1.6 0 0 0-1.6 1.2zM5 10v9h14v-9M9 19v-5h6v5" /></svg>
            From local flower shops
          </li>
        </ul>
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
          <!-- The same card as the rest of the shop; these are BloomCraft's picks, so they say so -->
          <ShopProductTile
            :product="product"
            context-tag="recommended"
            :adding="addingId === product.id"
            @open="$emit('open', $event)"
            @add="(p, ctx) => $emit('add', p, ctx)"
          />
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup>
import ShopProductTile from "./ShopProductTile.vue";
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";

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

const trackEl = ref(null);
const scrollable = ref(false);
const atStart = ref(true);
const atEnd = ref(false);

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
  padding: clamp(32px, 4.5vw, 64px) 0 clamp(16px, 2vw, 28px);
}

.fp-layout {
  display: grid;
  grid-template-columns: minmax(240px, 320px) minmax(0, 1fr);
  align-items: center;
  gap: clamp(24px, 4vw, 64px);
}

/* ── Intro ─────────────────────────────── */
.fp-intro h2 {
  margin: 6px 0 0;
  font-family: var(--font-display);
  font-size: clamp(32px, 3.6vw, 46px);
  font-weight: 500;
  line-height: 1.05;
  color: var(--ink);
}

.fp-intro h2 em {
  color: var(--leaf-deep);
}

.fp-lede {
  margin: 14px 0 0;
  font-size: 15px;
  line-height: 1.65;
  color: var(--ink-soft);
}

.fp-notes {
  display: grid;
  gap: 8px;
  margin: 18px 0 0;
  padding: 0;
  list-style: none;
}

.fp-notes li {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13.5px;
  color: var(--ink);
}

.fp-notes svg {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  padding: 5px;
  box-sizing: content-box;
  border-radius: 50%;
  background: rgba(77, 177, 115, 0.12);
  fill: none;
  stroke: var(--leaf-deep);
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fp-intro-foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 26px;
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
  display: flex;
  flex: 0 0 210px;
  scroll-snap-align: start;
}

/* The shared card fills its slot, like in Top Selling */
.fp-item > * {
  flex: 1;
  min-width: 0;
}

@media (max-width: 860px) {
  .fp-layout {
    grid-template-columns: 1fr;
    gap: 22px;
  }

  .fp-notes {
    display: none;
  }

  .fp-intro-foot {
    margin-top: 16px;
  }

  .fp-item {
    flex-basis: min(44vw, 180px);
  }
}

</style>
