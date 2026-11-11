<template>
  <Teleport to="body">
    <div class="lb" role="dialog" aria-modal="true" :aria-label="`${alt}: photo ${index + 1} of ${images.length}`" @click.self="close">
      <header class="lb-top">
        <span class="lb-count" aria-live="polite">{{ index + 1 }} / {{ images.length }}</span>
        <div class="lb-tools">
          <button type="button" class="lb-btn" :aria-pressed="zoomed" :aria-label="zoomed ? 'Zoom out' : 'Zoom in'" :title="zoomed ? 'Zoom out' : 'Zoom in'" @click="toggleZoom()">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" /><path v-if="!zoomed" d="M11 8.5v5M8.5 11h5" /><path v-else d="M8.5 11h5" /></svg>
          </button>
          <button ref="closeEl" type="button" class="lb-btn" aria-label="Close" title="Close (Esc)" @click="close">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
          </button>
        </div>
      </header>

      <div class="lb-stage" @click.self="close">
        <button v-if="images.length > 1" type="button" class="lb-arrow prev" aria-label="Previous photo" @click="go(-1)">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
        </button>
        <div
          class="lb-frame"
          :class="{ zoomed }"
          @click="toggleZoom($event)"
          @mousemove="pan"
        >
          <img
            :key="images[index]"
            :src="sizedImage(images[index], 1600)"
            :alt="`${alt}, photo ${index + 1}`"
            :style="zoomed ? { transformOrigin: `${origin.x}% ${origin.y}%` } : null"
            draggable="false"
          />
        </div>
        <button v-if="images.length > 1" type="button" class="lb-arrow next" aria-label="Next photo" @click="go(1)">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
        </button>
      </div>

      <div v-if="images.length > 1" class="lb-thumbs" role="tablist" aria-label="Photos">
        <button
          v-for="(url, i) in images"
          :key="url + i"
          type="button"
          role="tab"
          class="lb-thumb"
          :class="{ on: i === index }"
          :aria-selected="i === index"
          :aria-label="`Photo ${i + 1}`"
          @click="show(i)"
        >
          <img :src="sizedImage(url, 120)" alt="" loading="lazy" />
        </button>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { sizedImage } from "../../utils/cloudinaryImage.js";

const props = defineProps({
  images: { type: Array, required: true }, // image urls
  start: { type: Number, default: 0 },
  alt: { type: String, default: "Product" },
});
const emit = defineEmits(["close", "change"]);

const index = ref(Math.min(Math.max(props.start, 0), props.images.length - 1));
const zoomed = ref(false);
const origin = reactive({ x: 50, y: 50 });
const closeEl = ref(null);
let returnFocus = null;
let bodyOverflow = "";

function show(i) {
  const n = props.images.length;
  index.value = ((i % n) + n) % n;
  zoomed.value = false;
  emit("change", index.value);
}
const go = (step) => show(index.value + step);

/** Zoom follows the pointer: that point of the photo stays under it */
function aimAt(event) {
  const r = event.currentTarget.getBoundingClientRect();
  origin.x = Math.min(100, Math.max(0, ((event.clientX - r.left) / r.width) * 100));
  origin.y = Math.min(100, Math.max(0, ((event.clientY - r.top) / r.height) * 100));
}

function pan(event) {
  if (zoomed.value) aimAt(event);
}

/** From a click on the photo: zoom in on that spot. From the button: the middle. */
function toggleZoom(event) {
  if (event?.currentTarget && event.clientX != null) aimAt(event);
  else Object.assign(origin, { x: 50, y: 50 });
  zoomed.value = !zoomed.value;
}

const close = () => emit("close");

function onKey(event) {
  if (event.key === "Escape") close();
  else if (event.key === "ArrowLeft") go(-1);
  else if (event.key === "ArrowRight") go(1);
}

onMounted(() => {
  returnFocus = document.activeElement;
  bodyOverflow = document.body.style.overflow;
  document.body.style.overflow = "hidden"; // the page doesn't scroll behind it
  window.addEventListener("keydown", onKey);
  nextTick(() => closeEl.value?.focus());
});
onBeforeUnmount(() => {
  document.body.style.overflow = bodyOverflow;
  window.removeEventListener("keydown", onKey);
  returnFocus?.focus?.({ preventScroll: true });
});
</script>

<style scoped>
.lb {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  gap: 12px;
  padding: 16px;
  background: rgba(17, 21, 28, 0.94);
  color: #ffffff;
  font-family: var(--font-sans, "Poppins", system-ui, sans-serif);
  animation: lb-in 0.18s ease-out;
}

@keyframes lb-in {
  from { opacity: 0; }
}

.lb-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.lb-count {
  padding-left: 6px;
  color: rgba(255, 255, 255, 0.72);
  font-size: 13px;
  font-variant-numeric: tabular-nums;
}

.lb-tools {
  display: flex;
  gap: 6px;
}

.lb-btn,
.lb-arrow {
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  cursor: pointer;
  transition: background 0.15s ease;
}

.lb-btn {
  width: 44px;
  height: 44px;
}

.lb-btn:hover,
.lb-arrow:hover {
  background: rgba(255, 255, 255, 0.2);
}

.lb svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.lb-stage {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  min-height: 0;
}

.lb-frame {
  display: grid;
  place-items: center;
  width: min(100%, 1100px);
  height: 100%;
  overflow: hidden;
  border-radius: 12px;
  cursor: zoom-in;
}

.lb-frame.zoomed {
  cursor: zoom-out;
}

.lb-frame img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  user-select: none;
  transition: transform 0.25s ease;
}

.lb-frame.zoomed img {
  transform: scale(2.2);
  transition: transform 0.25s ease, transform-origin 0s;
}

.lb-arrow {
  flex: none;
  width: 48px;
  height: 48px;
}

.lb-thumbs {
  display: flex;
  justify-content: center;
  gap: 8px;
  overflow-x: auto;
  padding: 2px;
}

.lb-thumb {
  flex: none;
  width: 56px;
  height: 56px;
  padding: 0;
  overflow: hidden;
  border: 2px solid transparent;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.08);
  opacity: 0.55;
  cursor: pointer;
  transition: opacity 0.15s ease, border-color 0.15s ease;
}

.lb-thumb:hover {
  opacity: 0.85;
}

.lb-thumb.on {
  border-color: #ffffff;
  opacity: 1;
}

.lb-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.lb :is(button):focus-visible {
  outline: 2px solid #4db173;
  outline-offset: 2px;
}

@media (max-width: 640px) {
  .lb {
    padding: 10px;
  }

  .lb-arrow {
    position: absolute;
    z-index: 1;
    width: 40px;
    height: 40px;
  }

  .lb-arrow.prev {
    left: 4px;
  }

  .lb-arrow.next {
    right: 4px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .lb,
  .lb-frame img {
    animation: none;
    transition: none;
  }
}
</style>
