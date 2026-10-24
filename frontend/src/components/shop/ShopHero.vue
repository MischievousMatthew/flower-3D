<template>
  <section class="sh" aria-roledescription="carousel" aria-label="Featured" @mouseenter="paused = true" @mouseleave="paused = false">
    <div class="sh-track" :style="{ transform: `translateX(-${active * 100}%)` }">
      <article
        v-for="(slide, i) in slides"
        :key="slide.id"
        class="sh-slide"
        :aria-hidden="i !== active"
        :aria-label="`${i + 1} of ${slides.length}`"
      >
        <img class="sh-img" :src="slide.image_url" :alt="slide.title" :loading="i === 0 ? 'eager' : 'lazy'" />
        <div class="sh-fade" aria-hidden="true"></div>
        <div class="bc-container sh-inner">
          <div class="sh-copy">
            <p v-if="slide.eyebrow" class="sh-eyebrow">{{ slide.eyebrow }}</p>
            <h1 class="sh-title">
              <template v-for="(part, p) in titleParts(slide.title)" :key="p">
                <em v-if="p === 1">{{ part }}</em><template v-else>{{ part }}</template>
              </template>
            </h1>
            <p v-if="slide.subtitle" class="sh-sub">{{ slide.subtitle }}</p>
            <div class="sh-actions">
              <button type="button" class="sh-btn sh-btn--solid" :tabindex="i === active ? 0 : -1" @click="primary(slide)">
                {{ slide.button_label || "Shop now" }} <span aria-hidden="true">→</span>
              </button>
              <button type="button" class="sh-btn sh-btn--ghost" :tabindex="i === active ? 0 : -1" @click="$emit('design')">
                Explore 3D Designer
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>

    <div class="sh-badge" aria-hidden="true">
      <svg viewBox="0 0 24 24"><path d="M4 21V9l8-6 8 6v12" /><path d="M9 21v-6h6v6" /><path d="M9.5 10.5a2.5 2.5 0 0 1 5 0c0 1.8-2.5 3-2.5 3s-2.5-1.2-2.5-3z" /></svg>
      <span><strong>Support local florists</strong><small>Real shops. Real people.</small></span>
    </div>

    <template v-if="slides.length > 1">
      <button type="button" class="sh-arrow prev" aria-label="Previous slide" @click="go(active - 1)">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
      </button>
      <button type="button" class="sh-arrow next" aria-label="Next slide" @click="go(active + 1)">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
      </button>
      <div class="sh-dots">
        <button
          v-for="(s, i) in slides"
          :key="s.id"
          type="button"
          :class="{ on: i === active }"
          :aria-label="`Show slide ${i + 1}`"
          :aria-current="i === active"
          @click="go(i)"
        ></button>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useSiteContent } from "../../composables/useSiteContent";

/** The shop's hero slideshow: Admin → Website banners, or a built-in slide when there are none */
const props = defineProps({ banners: { type: Array, default: () => [] } });
const emit = defineEmits(["shop", "design"]);
const router = useRouter();
const { followLink } = useSiteContent();

const FALLBACK = {
  id: "default",
  eyebrow: "Fresh flowers for every moment",
  title: "Bespoke florals, built by you",
  subtitle: "Create your perfect bouquet with fresh flowers, customizable designs, and trusted local shops.",
  image_url: "https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=1800&h=900&fit=crop&q=80",
  button_label: "Shop now",
};
const slides = computed(() => (props.banners.length ? props.banners : [FALLBACK]));

// "Bespoke florals, built by you" → second half in italics, like the landing page headings
const titleParts = (title = "") => {
  const at = title.indexOf(",");
  return at > 0 && at < title.length - 1 ? [title.slice(0, at + 1) + " ", title.slice(at + 1).trim()] : [title];
};

const primary = (slide) => (slide.link_type ? followLink(router, slide) : emit("shop"));

const active = ref(0);
const paused = ref(false);
const go = (i) => {
  const n = slides.value.length;
  active.value = ((i % n) + n) % n;
};

let timer = null;
onMounted(() => {
  const reduced = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  if (!reduced) timer = setInterval(() => !paused.value && slides.value.length > 1 && go(active.value + 1), 6500);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.sh,
.sh * {
  box-sizing: border-box;
}

.sh {
  position: relative;
  overflow: hidden;
  background: var(--ivory-deep);
  isolation: isolate;
}

.sh-track {
  display: flex;
  transition: transform 0.7s var(--ease-out);
}

.sh-slide {
  position: relative;
  flex: 0 0 100%;
  min-width: 0;
  min-height: clamp(380px, 34vw, 520px);
  display: flex;
  align-items: center;
}

.sh-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: 70% center;
}

/* Soft ivory wash on the left so the text reads on any photo */
.sh-fade {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, rgba(242, 245, 242, 0.97) 0%, rgba(242, 245, 242, 0.86) 32%, rgba(242, 245, 242, 0.15) 62%, rgba(242, 245, 242, 0) 100%);
}

.sh-inner {
  position: relative;
  flex: 1;
}

.sh-copy {
  max-width: 520px;
  padding: 48px 0;
}

.sh-eyebrow {
  margin: 0 0 14px;
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--leaf-deep);
}

.sh-title {
  margin: 0 0 18px;
  font-family: var(--font-display);
  font-size: clamp(40px, 4.6vw, 66px);
  font-weight: 400;
  line-height: 1.04;
  letter-spacing: -0.01em;
  color: var(--ink);
}

.sh-title em {
  display: block;
  font-style: italic;
}

.sh-sub {
  max-width: 430px;
  margin: 0 0 28px;
  font-size: 15.5px;
  line-height: 1.7;
  color: var(--ink);
}

.sh-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.sh-btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  height: 48px;
  padding: 0 26px;
  border-radius: 999px;
  font-family: var(--font-sans);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.sh-btn--solid {
  border: 1px solid var(--leaf-deep);
  background: var(--leaf-deep);
  color: var(--paper);
}

.sh-btn--solid:hover {
  border-color: var(--ink);
  background: var(--ink);
}

.sh-btn--ghost {
  border: 1px solid var(--leaf-deep);
  background: rgba(250, 251, 250, 0.6);
  color: var(--leaf-deep);
}

.sh-btn--ghost:hover {
  background: var(--paper);
}

.sh-badge {
  position: absolute;
  right: clamp(16px, 8vw, 140px);
  bottom: 32px;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 12px;
  background: rgba(250, 251, 250, 0.94);
  box-shadow: 0 12px 30px -16px rgba(54, 63, 78, 0.35);
}

.sh-badge svg {
  width: 26px;
  height: 26px;
  fill: none;
  stroke: var(--leaf-deep);
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sh-badge span {
  display: grid;
  gap: 2px;
}

.sh-badge strong {
  font-size: 12.5px;
  font-weight: 500;
  color: var(--ink);
}

.sh-badge small {
  font-size: 11px;
  color: var(--ink-soft);
}

.sh-arrow {
  position: absolute;
  top: 50%;
  z-index: 2;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: rgba(250, 251, 250, 0.92);
  color: var(--ink);
  cursor: pointer;
  transform: translateY(-50%);
  box-shadow: 0 8px 20px -12px rgba(54, 63, 78, 0.4);
}

.sh-arrow svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sh-arrow.prev {
  left: 22px;
}

.sh-arrow.next {
  right: 22px;
}

.sh-dots {
  position: absolute;
  bottom: 20px;
  left: 50%;
  z-index: 2;
  display: flex;
  gap: 7px;
  transform: translateX(-50%);
}

.sh-dots button {
  width: 8px;
  height: 8px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: rgba(250, 251, 250, 0.7);
  cursor: pointer;
  transition: width 0.3s var(--ease-out), background 0.3s;
}

.sh-dots button.on {
  width: 26px;
  background: var(--paper);
}

.sh :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

@media (max-width: 760px) {
  .sh-slide {
    min-height: 0;
    align-items: flex-end;
  }

  .sh-img {
    object-position: center;
  }

  .sh-fade {
    background: linear-gradient(180deg, rgba(242, 245, 242, 0) 0%, rgba(242, 245, 242, 0.55) 35%, rgba(242, 245, 242, 0.97) 70%);
  }

  .sh-copy {
    padding: 200px 0 64px;
  }

  .sh-badge,
  .sh-arrow {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .sh-track {
    transition: none;
  }
}
</style>
