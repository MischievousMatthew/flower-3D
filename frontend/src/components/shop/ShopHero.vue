<template>
  <section class="sh" aria-roledescription="carousel" aria-label="Featured" @mouseenter="hovering = true" @mouseleave="hovering = false">
    <div class="sh-card">
        <!-- Soft petals in the background of the card -->
        <svg class="sh-deco" viewBox="0 0 400 400" aria-hidden="true">
          <circle cx="330" cy="70" r="120" />
          <circle cx="40" cy="360" r="90" />
        </svg>

        <div class="sh-track" :style="{ transform: `translateX(-${active * 100}%)` }">
          <article
            v-for="(slide, i) in slides"
            :key="slide.id"
            class="sh-slide"
            :aria-hidden="i !== active"
            :aria-label="`${i + 1} of ${slides.length}`"
          >
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

            <div class="sh-media">
              <div class="sh-frame">
                <img class="sh-img" :src="slide.image_url" :alt="slide.title" :loading="i === 0 ? 'eager' : 'lazy'" />
              </div>
              <div class="sh-float sh-float--top" aria-hidden="true">
                <span class="sh-float-icon sh-float-icon--leaf">
                  <svg viewBox="0 0 24 24"><path d="M12 3 4 7.5v9L12 21l8-4.5v-9z" /><path d="M4 7.5 12 12l8-4.5M12 12v9" /></svg>
                </span>
                <span><strong>Preview in 3D</strong><small>See it before you order</small></span>
              </div>
              <div class="sh-float sh-float--bottom" aria-hidden="true">
                <span class="sh-float-icon">
                  <svg viewBox="0 0 24 24"><path d="M4 21V9l8-6 8 6v12" /><path d="M9 21v-6h6v6" /><path d="M9.5 10.5a2.5 2.5 0 0 1 5 0c0 1.8-2.5 3-2.5 3s-2.5-1.2-2.5-3z" /></svg>
                </span>
                <span><strong>Support local florists</strong><small>Real shops. Real people.</small></span>
              </div>
            </div>
          </article>
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
  image_url: "https://images.unsplash.com/photo-1487070183336-b863922373d4?w=1600&h=1280&fit=crop&q=80",
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

/* Full width: the card runs edge to edge, its content lines up with the page */
.sh {
  --sh-inline: max(var(--gutter), calc((100% - 1240px) / 2));
}

/* One rounded card: copy on a soft mint panel, the photo framed on the right */
.sh-card {
  position: relative;
  overflow: hidden;
  border-block: 1px solid var(--line-soft);
  background: linear-gradient(120deg, #e6f2ea 0%, var(--paper) 55%, #f6ece6 100%);
  isolation: isolate;
}

.sh-deco {
  position: absolute;
  inset: 0;
  z-index: -1;
  width: 100%;
  height: 100%;
  pointer-events: none;
}

.sh-deco circle {
  fill: rgba(77, 177, 115, 0.07);
}

.sh-track {
  display: flex;
  transition: transform 0.7s var(--ease-out);
}

.sh-slide {
  display: grid;
  flex: 0 0 100%;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr);
  align-items: center;
  gap: clamp(24px, 4vw, 56px);
  min-width: 0;
  padding: clamp(32px, 4vw, 64px) var(--sh-inline) clamp(64px, 6vw, 84px);
}

.sh-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 18px;
  padding: 7px 14px 7px 10px;
  border: 1px solid rgba(46, 122, 79, 0.18);
  border-radius: 999px;
  background: rgba(250, 251, 250, 0.75);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.04em;
  color: var(--leaf-deep);
}

.sh-eyebrow-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--leaf);
  box-shadow: 0 0 0 4px rgba(77, 177, 115, 0.2);
}

.sh-title {
  margin: 0 0 18px;
  font-family: var(--font-display);
  font-size: clamp(42px, 5vw, 72px);
  font-weight: 500;
  line-height: 0.98;
  letter-spacing: -0.015em;
  color: var(--ink);
  text-wrap: balance;
}

.sh-title em {
  display: block;
  font-style: italic;
  color: var(--leaf-deep);
}

.sh-sub {
  max-width: 440px;
  margin: 0 0 28px;
  font-size: 15.5px;
  line-height: 1.7;
  color: var(--ink-soft);
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
  min-height: 52px;
  padding: 0 26px;
  border-radius: 999px;
  font-family: var(--font-sans);
  font-size: 14.5px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.sh-btn svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linejoin: round;
}

.sh-btn--solid {
  border: 1px solid var(--ink);
  background: var(--ink);
  color: var(--paper);
  box-shadow: 0 14px 28px -16px rgba(38, 45, 57, 0.6);
}

.sh-btn--solid:hover {
  border-color: var(--leaf-deep);
  background: var(--leaf-deep);
}

.sh-btn--ghost {
  border: 1px solid var(--line);
  background: var(--paper);
  color: var(--ink);
}

.sh-btn--ghost:hover {
  border-color: var(--ink);
}

.sh-points {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 20px;
  margin: 26px 0 0;
  padding: 0;
  list-style: none;
  font-size: 13px;
  color: var(--ink-soft);
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
  stroke: var(--leaf);
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

/* The photo, framed with an arched top */
.sh-media {
  position: relative;
  padding: 12px 0;
}

.sh-frame {
  overflow: hidden;
  aspect-ratio: 5 / 4;
  border-radius: 220px 220px 28px 28px;
  background: var(--ivory-deep);
  box-shadow: 0 30px 60px -36px rgba(38, 45, 57, 0.55);
}

.sh-img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sh-float {
  position: absolute;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px 12px 12px;
  border: 1px solid var(--line-soft);
  border-radius: 16px;
  background: rgba(250, 251, 250, 0.96);
  box-shadow: 0 18px 36px -20px rgba(38, 45, 57, 0.45);
  backdrop-filter: blur(6px);
}

.sh-float--top {
  top: 18%;
  left: -28px;
}

.sh-float--bottom {
  right: 0;
  bottom: 4px;
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

.sh-float-icon--leaf {
  background: var(--ink);
  color: var(--leaf-light);
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

/* Slide controls, bottom left under the copy */
.sh-controls {
  position: absolute;
  bottom: clamp(16px, 2vw, 24px);
  left: var(--sh-inline);
  display: flex;
  align-items: center;
  gap: 12px;
}

.sh-arrow {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: var(--paper);
  color: var(--ink);
  cursor: pointer;
  transition: border-color 0.2s ease;
}

.sh-arrow:hover {
  border-color: var(--ink);
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
  gap: 4px;
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

@media (max-width: 900px) {
  .sh-slide {
    grid-template-columns: minmax(0, 1fr);
    gap: 8px;
  }

  .sh-media {
    order: -1;
    padding: 0;
  }

  .sh-frame {
    aspect-ratio: 16 / 10;
    border-radius: 24px;
  }

  .sh-float--top {
    display: none;
  }

  .sh-float--bottom {
    right: 12px;
    bottom: 12px;
  }

  .sh-title {
    margin-top: 16px;
  }
}

@media (max-width: 560px) {
  .sh-float,
  .sh-points {
    display: none;
  }

  .sh-btn {
    flex: 1 1 auto;
    justify-content: center;
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
