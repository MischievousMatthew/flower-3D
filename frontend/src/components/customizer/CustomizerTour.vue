<template>
    <div v-if="step" class="ct" :class="{ docked: tip.side.startsWith('dock') }">
      <!-- The part being explained stays bright; the rest dims. Clicks go through, so the customer can do the step -->
      <div v-if="hole" class="ct-hole" :style="holeStyle" aria-hidden="true"></div>
      <div v-else class="ct-dim" aria-hidden="true"></div>

      <span v-if="tip.arrow" class="ct-arrow" :class="`to-${tip.arrow.dir}`" :style="{ left: `${tip.arrow.x}px`, top: `${tip.arrow.y}px` }" aria-hidden="true">
        <BcIcon :name="ARROWS[tip.arrow.dir]" />
      </span>

      <section
        ref="cardEl"
        class="ct-card"
        role="dialog"
        aria-modal="false"
        aria-labelledby="ct-title"
        aria-describedby="ct-body"
        :style="{ left: `${tip.left}px`, top: `${tip.top}px` }"
      >
        <header class="ct-head">
          <p class="ct-count">Step {{ index + 1 }} of {{ total }}</p>
          <button type="button" class="ct-close" aria-label="End the guide" title="End the guide" @click="$emit('close')">
            <BcIcon name="x" />
          </button>
        </header>
        <h2 id="ct-title" class="ct-title">{{ step.title }}</h2>
        <p id="ct-body" class="ct-body" aria-live="polite">{{ step.body }}</p>
        <p v-if="step.waiting" class="ct-wait">
          <span class="ct-wait-dot" aria-hidden="true"></span>
          {{ step.waiting }}
        </p>
        <p v-else-if="step.doneText" class="ct-done">
          <BcIcon name="check" />
          {{ step.doneText }}
        </p>
        <div class="ct-dots" aria-hidden="true">
          <span v-for="n in total" :key="n" :class="{ on: n - 1 === index, past: n - 1 < index }"></span>
        </div>
        <footer class="ct-actions">
          <button v-if="index > 0" type="button" class="ct-btn ghost" @click="$emit('back')">Back</button>
          <button ref="nextEl" type="button" class="ct-btn" :class="{ soft: step.waiting }" @click="$emit('next')">
            {{ index === total - 1 ? "Finish" : step.waiting ? "Skip this step" : "Next" }}
          </button>
        </footer>
      </section>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import BcIcon from "./CustomizerIcon.js";
import { placeTip } from "../../utils/customizerUi.js";

const props = defineProps({
  // { target: data-tour name or "", title, body, waiting?: text while the customer still has to do it, doneText? }
  step: { type: Object, default: null },
  index: { type: Number, default: 0 },
  total: { type: Number, default: 0 },
});
const emit = defineEmits(["next", "back", "close"]);

const ARROWS = { left: "arrowLeft", right: "arrowRight", up: "arrowUp", down: "arrowDown" };
const PAD = 8;

const cardEl = ref(null);
const nextEl = ref(null);
const hole = ref(null);
const tip = reactive({ side: "center", left: 0, top: 0, arrow: null });

const holeStyle = computed(() => ({
  left: `${hole.value.left}px`,
  top: `${hole.value.top}px`,
  width: `${hole.value.width}px`,
  height: `${hole.value.height}px`,
}));

function targetRect() {
  const name = props.step?.target;
  const el = name ? document.querySelector(`[data-tour="${name}"]`) : null;
  if (!el) return null;
  const rect = el.getBoundingClientRect();
  if (!rect.width || !rect.height) return null;
  // Only the part that's on screen
  const top = Math.max(rect.top, 0);
  const bottom = Math.min(rect.bottom, window.innerHeight);
  if (bottom - top < 8) return null;
  return { left: rect.left - PAD, top: top - PAD, width: rect.width + PAD * 2, height: bottom - top + PAD * 2 };
}

function place() {
  if (!props.step) return;
  const rect = targetRect();
  hole.value = rect;
  const card = cardEl.value?.getBoundingClientRect();
  const view = { width: window.innerWidth, height: window.innerHeight };
  const width = card?.width || Math.min(340, view.width - 32);
  Object.assign(tip, placeTip(rect, view, { width, height: card?.height || 220 }));
}

let frame = 0;
const queue = () => {
  cancelAnimationFrame(frame);
  frame = requestAnimationFrame(place);
};

// A new step: bring its part into view, then point at it
watch(
  () => [props.step?.target, props.index],
  async () => {
    if (!props.step) return;
    await nextTick();
    const el = props.step.target ? document.querySelector(`[data-tour="${props.step.target}"]`) : null;
    el?.scrollIntoView?.({ block: "nearest", behavior: "smooth" });
    queue();
    setTimeout(queue, 350); // after the scroll settles
    nextEl.value?.focus({ preventScroll: true });
  },
  { immediate: true },
);
// The text changes size (waiting → done), so the card may need to move
watch(() => [props.step?.title, props.step?.body, props.step?.waiting, props.step?.doneText], () => nextTick(queue));

function onKey(event) {
  if (props.step && event.key === "Escape") emit("close");
}

let poll = 0;
onMounted(() => {
  window.addEventListener("resize", queue);
  window.addEventListener("scroll", queue, true);
  window.addEventListener("keydown", onKey);
  poll = setInterval(() => props.step && queue(), 500); // panels open and close under it
});
onUnmounted(() => {
  window.removeEventListener("resize", queue);
  window.removeEventListener("scroll", queue, true);
  window.removeEventListener("keydown", onKey);
  clearInterval(poll);
  cancelAnimationFrame(frame);
});
</script>

<style scoped>
.ct {
  position: fixed;
  inset: 0;
  z-index: 1150;
  pointer-events: none;
  font-family: var(--font-sans);
}

.ct-dim {
  position: absolute;
  inset: 0;
  background: rgba(38, 45, 57, 0.4);
}

/* The bright window around the part, with a soft green ring that breathes */
.ct-hole {
  position: absolute;
  border-radius: 14px;
  box-shadow: 0 0 0 9999px rgba(38, 45, 57, 0.4);
  outline: 3px solid var(--leaf);
  outline-offset: 0;
  transition: left 0.3s var(--ease-out, ease), top 0.3s var(--ease-out, ease), width 0.3s var(--ease-out, ease), height 0.3s var(--ease-out, ease);
  animation: ct-ring 1.8s ease-in-out infinite;
}

@keyframes ct-ring {
  0%, 100% { outline-color: rgba(77, 177, 115, 1); }
  50% { outline-color: rgba(77, 177, 115, 0.45); }
}

.ct-arrow {
  position: absolute;
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  margin: -17px 0 0 -17px;
  border-radius: 50%;
  background: var(--leaf);
  color: #ffffff;
  box-shadow: 0 6px 16px rgba(46, 122, 79, 0.35);
  transition: left 0.3s ease, top 0.3s ease;
}

.ct-arrow :deep(svg) {
  width: 20px;
  height: 20px;
  stroke-width: 2.4;
}

.ct-arrow.to-left { animation: ct-left 1s ease-in-out infinite; }
.ct-arrow.to-right { animation: ct-right 1s ease-in-out infinite; }
.ct-arrow.to-up { animation: ct-up 1s ease-in-out infinite; }
.ct-arrow.to-down { animation: ct-down 1s ease-in-out infinite; }

@keyframes ct-left { 50% { transform: translateX(-6px); } }
@keyframes ct-right { 50% { transform: translateX(6px); } }
@keyframes ct-up { 50% { transform: translateY(-6px); } }
@keyframes ct-down { 50% { transform: translateY(6px); } }

.ct-card {
  position: absolute;
  display: grid;
  gap: 10px;
  width: min(340px, calc(100vw - 32px));
  padding: 18px 18px 16px;
  border-radius: 18px;
  background: var(--paper);
  color: var(--ink);
  box-shadow: 0 24px 60px rgba(38, 45, 57, 0.28);
  pointer-events: auto;
  transition: left 0.3s var(--ease-out, ease), top 0.3s var(--ease-out, ease);
}

.ct-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.ct-count {
  margin: 0;
  color: var(--leaf-deep);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.ct-close {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  margin: -6px -6px -6px 0;
  border: 0;
  border-radius: 50%;
  background: none;
  color: var(--ink-soft);
  cursor: pointer;
}

.ct-close:hover {
  background: var(--ivory-deep, #eef1ee);
  color: var(--ink);
}

.ct-title {
  margin: 0;
  font-family: var(--font-display);
  font-size: 26px;
  font-weight: 600;
  line-height: 1.1;
}

.ct-body {
  margin: 0;
  color: var(--ink-soft);
  font-size: 15px;
  line-height: 1.55;
}

.ct-wait,
.ct-done {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 8px 12px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 500;
}

.ct-wait {
  background: rgba(77, 177, 115, 0.1);
  color: var(--leaf-deep);
}

.ct-wait-dot {
  width: 8px;
  height: 8px;
  flex-shrink: 0;
  border-radius: 50%;
  background: var(--leaf);
  animation: ct-pulse 1.2s ease-in-out infinite;
}

@keyframes ct-pulse {
  50% { opacity: 0.3; }
}

.ct-done {
  background: var(--leaf);
  color: #ffffff;
}

.ct-done :deep(svg) {
  width: 16px;
  height: 16px;
}

.ct-dots {
  display: flex;
  gap: 5px;
}

.ct-dots span {
  width: 6px;
  height: 6px;
  border-radius: 999px;
  background: var(--line);
  transition: width 0.25s ease, background 0.25s ease;
}

.ct-dots span.past {
  background: var(--leaf-light);
}

.ct-dots span.on {
  width: 18px;
  background: var(--leaf);
}

.ct-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.ct-btn {
  min-height: 44px;
  padding: 0 20px;
  border: 0;
  border-radius: 999px;
  background: var(--ink);
  color: var(--paper);
  font: 600 14px var(--font-sans);
  cursor: pointer;
  transition: background 0.2s ease;
}

.ct-btn:hover {
  background: var(--leaf-deep);
}

.ct-btn.soft {
  background: var(--ivory-deep, #eef1ee);
  color: var(--ink);
}

.ct-btn.soft:hover {
  background: #e2e7e3;
}

.ct-btn.ghost {
  margin-right: auto;
  background: none;
  color: var(--ink-soft);
}

.ct-btn.ghost:hover {
  color: var(--ink);
}

.ct-btn:focus-visible,
.ct-close:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
  .ct-hole,
  .ct-arrow,
  .ct-wait-dot {
    animation: none;
  }
  .ct-hole,
  .ct-card,
  .ct-arrow {
    transition: none;
  }
}
</style>
