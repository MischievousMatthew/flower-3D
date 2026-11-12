<template>
  <section class="sh" aria-roledescription="carousel" aria-label="Featured" @mouseenter="hovering = true" @mouseleave="hovering = false">
    <div class="sh-track" :style="{ transform: `translateX(-${active * 100}%)` }">
      <article
        v-for="(slide, i) in slides"
        :key="slide.id"
        class="sh-slide"
        :aria-hidden="i !== active"
        :aria-label="`${i + 1} of ${slides.length}`"
      >
        <!-- The photo (or the admin's banner) fills the whole hero -->
        <img class="sh-img" :src="slide.image_url" :alt="slide.title" :loading="i === 0 ? 'eager' : 'lazy'" />
        <div class="sh-shade" aria-hidden="true"></div>

        <div class="sh-inner">
          <div class="sh-copy">
            <p class="sh-eyebrow">
              <span class="sh-eyebrow-dot" aria-hidden="true"></span>
              {{ slide.eyebrow || "Fresh from local florists" }}
            </p>
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
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 7.5v9L12 21l8-4.5v-9z" /><path d="M4 7.5 12 12l8-4.5M12 12v9" /></svg>
                Explore 3D Designer
              </button>
            </div>
            <ul class="sh-points">
              <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>Made by real local shops</li>
              <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>Design it in 3D first</li>
            </ul>
          </div>
        </div>
      </article>
    </div>

    <div class="sh-float" aria-hidden="true">
      <span class="sh-float-icon">
        <svg viewBox="0 0 24 24"><path d="M4 21V9l8-6 8 6v12" /><path d="M9 21v-6h6v6" /><path d="M9.5 10.5a2.5 2.5 0 0 1 5 0c0 1.8-2.5 3-2.5 3s-2.5-1.2-2.5-3z" /></svg>
      </span>
      <span><strong>Support local florists</strong><small>Real shops. Real people.</small></span>
    </div>

    <div v-if="slides.length > 1" class="sh-controls">
      <button v-if="play.show_arrows" type="button" class="sh-arrow" aria-label="Previous slide" @click="goManual(active - 1)">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
      </button>
      <div v-if="play.show_dots" class="sh-dots">
        <button
          v-for="(s, i) in slides"
          :key="s.id"
          type="button"
          :class="{ on: i === active }"
          :aria-label="`Show slide ${i + 1}`"
          :aria-current="i === active"
          @click="goManual(i)"
        ></button>
      </div>
      <button v-if="play.show_arrows" type="button" class="sh-arrow" aria-label="Next slide" @click="goManual(active + 1)">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
      </button>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useSiteContent } from "../../composables/useSiteContent";

/** The shop's hero slideshow: Admin → Website banners, or a built-in slide when there are none */
const props = defineProps({
  banners: { type: Array, default: () => [] },
  // Admin → Promotion Management → Banners → Slideshow settings
  settings: { type: Object, default: () => ({}) },
  // Admin full-screen preview: the buttons don't go anywhere
  preview: { type: Boolean, default: false },
});
const DEFAULTS = { seconds: 6, autoplay: true, pause_on_hover: true, show_arrows: true, show_dots: true };
const play = computed(() => {
  const s = { ...DEFAULTS, ...(props.settings || {}) };
  const seconds = Number(s.seconds);
  return { ...s, seconds: Number.isFinite(seconds) ? Math.min(30, Math.max(3, seconds)) : DEFAULTS.seconds };
});
const emit = defineEmits(["shop", "design"]);
const router = useRouter();
const { followLink } = useSiteContent();

const FALLBACK = {
  id: "default",
  title: "Bespoke florals, built by you",
  subtitle: "Create your perfect bouquet with fresh flowers, customizable designs, and trusted local shops.",
  image_url: "https://images.unsplash.com/photo-1487070183336-b863922373d4?w=2000&h=1000&fit=crop&q=80",
  button_label: "Shop now",
};
const slides = computed(() => (props.banners.length ? props.banners : [FALLBACK]));

// "Bespoke florals, built by you" → second half in italics, like the landing page headings
const titleParts = (title = "") => {
  const at = title.indexOf(",");
  return at > 0 && at < title.length - 1 ? [title.slice(0, at + 1) + " ", title.slice(at + 1).trim()] : [title];
};

const primary = (slide) => {
  if (props.preview) return;
  return slide.link_type ? followLink(router, slide) : emit("shop");
};

const active = ref(0);
const hovering = ref(false);
const paused = computed(() => play.value.pause_on_hover && hovering.value);
const go = (i) => {
  const n = slides.value.length;
  active.value = ((i % n) + n) % n;
};

let timer = null;
let reduced = false;
const restart = () => {
  clearInterval(timer);
  timer = null;
  if (reduced || !play.value.autoplay) return;
  timer = setInterval(() => !paused.value && slides.value.length > 1 && go(active.value + 1), play.value.seconds * 1000);
};
// a click on an arrow or dot gives the new slide its full time
const goManual = (i) => {
  go(i);
  restart();
};
watch(() => [play.value.autoplay, play.value.seconds], restart);
watch(() => slides.value.length, () => active.value >= slides.value.length && (active.value = 0));
onMounted(() => {
  reduced = !!window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  restart();
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.sh,
.sh * {
  box-sizing: border-box;
}

/* Full width, the photo fills it; the copy lines up with the page */
.sh {
  --sh-inline: max(var(--gutter), calc((100% - 1240px) / 2));
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
  display: flex;
  flex: 0 0 100%;
  align-items: center;
  min-width: 0;
  min-height: clamp(460px, 42vw, 640px);
}

.sh-img {
  position: absolute;
  inset: 0;
  z-index: -2;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* A navy shade from the left so the white text reads; the right stays bright */
.sh-shade {
  position: absolute;
  inset: 0;
  z-index: -1;
  background: linear-gradient(90deg, rgba(38, 45, 57, 0.78) 0%, rgba(38, 45, 57, 0.55) 34%, rgba(38, 45, 57, 0.12) 62%, rgba(38, 45, 57, 0) 78%);
}

.sh-inner {
  width: 100%;
  padding: clamp(32px, 4vw, 56px) var(--sh-inline) clamp(72px, 7vw, 96px);
}

/* The copy straight on the photo, in white */
.sh-copy {
  max-width: 540px;
  color: var(--paper);
  text-shadow: 0 1px 18px rgba(38, 45, 57, 0.35);
}

.sh-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 16px;
  padding: 6px 14px 6px 10px;
  border: 1px solid rgba(250, 251, 250, 0.3);
  border-radius: 999px;
  background: rgba(250, 251, 250, 0.14);
  backdrop-filter: blur(6px);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.04em;
  color: var(--paper);
  text-shadow: none;
}

.sh-eyebrow-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--leaf);
  box-shadow: 0 0 0 4px rgba(77, 177, 115, 0.2);
}

.sh-title {
  margin: 0 0 16px;
  font-family: var(--font-display);
  font-size: clamp(40px, 4.4vw, 64px);
  font-weight: 500;
  line-height: 0.98;
  letter-spacing: -0.015em;
  color: var(--paper);
  text-wrap: balance;
}

.sh-title em {
  display: block;
  font-style: italic;
  color: var(--leaf-light);
}

.sh-sub {
  max-width: 420px;
  margin: 0 0 24px;
  font-size: 15px;
  line-height: 1.65;
  color: rgba(250, 251, 250, 0.9);
}

.sh-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.sh-btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 50px;
  padding: 0 24px;
  border-radius: 999px;
  font-family: var(--font-sans);
  font-size: 14.5px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.sh-btn svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linejoin: round;
}

.sh-btn {
  text-shadow: none;
}

.sh-btn--solid {
  border: 1px solid var(--leaf);
  background: var(--leaf);
  color: var(--espresso);
}

.sh-btn--solid:hover {
  border-color: var(--leaf-light);
  background: var(--leaf-light);
}

.sh-btn--ghost {
  border: 1px solid rgba(250, 251, 250, 0.6);
  background: rgba(250, 251, 250, 0.1);
  color: var(--paper);
  backdrop-filter: blur(6px);
}

.sh-btn--ghost:hover {
  border-color: var(--paper);
  background: rgba(250, 251, 250, 0.2);
}

.sh-points {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 20px;
  margin: 22px 0 0;
  padding: 0;
  list-style: none;
  font-size: 13px;
  color: rgba(250, 251, 250, 0.88);
}

.sh-points li {
  display: inline-flex;
  align-items: center;
  gap: 7px;
}

.sh-points svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: var(--leaf-light);
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sh-float {
  position: absolute;
  right: var(--sh-inline);
  bottom: 28px;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px 12px 12px;
  border-radius: 16px;
  background: rgba(250, 251, 250, 0.95);
  box-shadow: 0 18px 36px -20px rgba(38, 45, 57, 0.5);
}

.sh-float-icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #e9f5ed;
  color: var(--leaf-deep);
}

.sh-float-icon svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sh-float span:last-child {
  display: grid;
  gap: 2px;
}

.sh-float strong {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}

.sh-float small {
  font-size: 11.5px;
  color: var(--ink-soft);
}

/* Slide controls on a small pill at the bottom centre */
.sh-controls {
  position: absolute;
  bottom: 24px;
  left: 50%;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px;
  border-radius: 999px;
  background: rgba(250, 251, 250, 0.92);
  box-shadow: 0 12px 28px -16px rgba(38, 45, 57, 0.5);
  transform: translateX(-50%);
}

.sh-arrow {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--ink);
  cursor: pointer;
  transition: background 0.2s ease;
}

.sh-arrow:hover {
  background: var(--ivory);
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

.sh-dots {
  display: flex;
  gap: 2px;
}

/* 24px tap target around each dot */
.sh-dots button {
  position: relative;
  width: 24px;
  height: 24px;
  padding: 0;
  border: 0;
  background: none;
  cursor: pointer;
  transition: width 0.3s var(--ease-out);
}

.sh-dots button::after {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: rgba(54, 63, 78, 0.25);
  transform: translate(-50%, -50%);
  transition: width 0.3s var(--ease-out), background 0.3s;
}

.sh-dots button.on {
  width: 34px;
}

.sh-dots button.on::after {
  width: 26px;
  background: var(--leaf-deep);
}

.sh :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

/* Phones: the photo on top at full width, the card overlapping its bottom */
@media (max-width: 760px) {
  .sh-slide {
    flex-direction: column;
    align-items: stretch;
    min-height: 0;
  }

  .sh-img {
    position: relative;
    z-index: 0;
    height: 300px;
  }

  .sh-shade,
  .sh-float,
  .sh-points {
    display: none;
  }

  .sh-inner {
    margin-top: -48px;
    padding: 0 var(--gutter) 72px;
  }

  /* The copy sits under the photo on phones: a white card with dark text */
  .sh-copy {
    position: relative;
    max-width: none;
    padding: 24px;
    border-radius: 24px;
    background: var(--paper);
    box-shadow: 0 24px 50px -30px rgba(38, 45, 57, 0.5);
    color: var(--ink);
    text-shadow: none;
  }

  .sh-eyebrow {
    border-color: transparent;
    background: #e9f5ed;
    color: var(--leaf-deep);
  }

  .sh-title {
    color: var(--ink);
  }

  .sh-title em {
    color: var(--leaf-deep);
  }

  .sh-sub {
    color: var(--ink-soft);
  }

  .sh-btn--solid {
    border-color: var(--ink);
    background: var(--ink);
    color: var(--paper);
  }

  .sh-btn--ghost {
    border-color: var(--line);
    background: var(--paper);
    color: var(--ink);
  }

  .sh-btn {
    flex: 1 1 auto;
    justify-content: center;
  }

  .sh-controls {
    bottom: 16px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .sh-track,
  .sh-dots button,
  .sh-dots button::after {
    transition: none;
  }
}
</style>
