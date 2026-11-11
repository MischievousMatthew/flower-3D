<template>
    <div v-if="step" class="ct" :class="{ docked: tip.side.startsWith('dock') }">
      <!-- The part being explained stays bright; the rest dims. Clicks go through, so the customer can do the step -->
      <div v-if="hole" class="ct-hole" :style="holeStyle" aria-hidden="true"></div>
      <div v-else class="ct-dim" aria-hidden="true"></div>

      <span v-if="tip.arrow" class="ct-arrow" :class="`to-${tip.arrow.dir}`" :style="{ left: `${tip.arrow.x}px`, top: `${tip.arrow.y}px` }" aria-hidden="true">
        <BcIcon :name="ARROWS[tip.arrow.dir]" />
      </span>

      <!-- Placing: an arrow and a ring on the exact spot the flower will land -->
      <template v-if="spotMark">
        <span class="ct-drop" :style="{ left: `${spotMark.x}px`, top: `${spotMark.y}px` }" aria-hidden="true"></span>
        <span class="ct-arrow to-down ct-spot-arrow" :style="{ left: `${spotMark.x}px`, top: `${spotMark.y - 46}px` }" aria-hidden="true">
          <BcIcon name="arrowDown" />
        </span>
      </template>

      <!-- Drag demo: a pointer carries the item from the list onto the bouquet, back and forth -->
      <template v-if="drag">
        <svg class="ct-trail" aria-hidden="true">
          <defs>
            <marker id="ct-trail-head" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse">
              <path d="M0 0l10 5-10 5z" />
            </marker>
          </defs>
          <line :x1="drag.x1" :y1="drag.y1" :x2="drag.x2" :y2="drag.y2" marker-end="url(#ct-trail-head)" />
        </svg>
        <span class="ct-drop" :style="{ left: `${drag.x2}px`, top: `${drag.y2}px` }" aria-hidden="true"></span>
        <span ref="dragEl" class="ct-drag" aria-hidden="true">
          <span class="ct-drag-chip"><BcIcon :name="step.drag.icon || 'leaf'" /></span>
          <span class="ct-drag-pointer"><BcIcon name="pointer" /></span>
        </span>
      </template>

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
        <!-- Just did it: the win is the message, then on to the next step by itself -->
        <template v-if="step.celebrate">
          <div class="ct-win" role="status">
            <span class="ct-win-badge" aria-hidden="true"><BcIcon name="check" /></span>
            <div>
              <h2 id="ct-title" class="ct-title">{{ step.celebrate.title }}</h2>
              <p id="ct-body" class="ct-win-text">{{ step.celebrate.text }}</p>
            </div>
          </div>
          <div class="ct-timer" aria-hidden="true">
            <span :key="`${index}-${step.celebrate.text}`" :style="{ animationDuration: `${step.celebrate.ms}ms` }"></span>
          </div>
          <p class="ct-timer-label">{{ index === total - 1 ? "Finishing" : "Next step" }} in a moment…</p>
        </template>
        <template v-else>
          <h2 id="ct-title" class="ct-title">{{ step.title }}</h2>
          <p id="ct-body" class="ct-body" aria-live="polite">{{ step.body }}</p>
        </template>
        <p v-if="step.waiting && !step.celebrate" class="ct-wait">
          <span class="ct-wait-dot" aria-hidden="true"></span>
          {{ step.waiting }}
        </p>
        <p v-else-if="step.doneText && !step.celebrate" class="ct-done">
          <BcIcon name="check" />
          {{ step.doneText }}
        </p>
        <div class="ct-dots" aria-hidden="true">
          <span v-for="n in total" :key="n" :class="{ on: n - 1 === index, past: n - 1 < index }"></span>
        </div>
        <footer class="ct-actions">
          <button v-if="index > 0" type="button" class="ct-btn ghost" @click="$emit('back')">Back</button>
          <button v-if="!(step.waiting && step.canSkip === false)" ref="nextEl" type="button" class="ct-btn" :class="{ soft: step.waiting }" @click="$emit('next')">
            {{ index === total - 1 ? "Finish" : step.waiting ? "Skip this step" : "Next" }}
          </button>
        </footer>
      </section>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import BcIcon from "./CustomizerIcon.js";
import { cornerTip, placeTip } from "../../utils/customizerUi.js";

const props = defineProps({
  // { target: data-tour name or "", title, body, waiting?: text while the customer still has to do it, doneText?,
  //   celebrate?: { title, text, ms } right after they did it; the guide moves on after `ms` }
  step: { type: Object, default: null },
  index: { type: Number, default: 0 },
  total: { type: Number, default: 0 },
  // () => [{ left, top, width, height, weight }]: what the card must not cover when it can't sit beside its part
  keepClear: { type: Function, default: null },
});
const emit = defineEmits(["next", "back", "close"]);

const ARROWS = { left: "arrowLeft", right: "arrowRight", up: "arrowUp", down: "arrowDown" };
const PAD = 8;

const cardEl = ref(null);
const nextEl = ref(null);
const hole = ref(null);
const tip = reactive({ side: "center", left: 0, top: 0, arrow: null });
const drag = ref(null); // { x1, y1, x2, y2 } on screen
const spotMark = ref(null); // { x, y }: where the flower will land, while placing
const dragEl = ref(null);
let dragRun = null; // the running animation, and the path it follows
let dragKey = "";

/**
 * Moves the item along the dotted line into the circle and back, on the very
 * points the line is drawn between. Started again only when the path moves.
 */
function runDrag() {
  const path = drag.value;
  const el = dragEl.value;
  if (!path || !el) {
    dragRun?.cancel();
    dragRun = null;
    dragKey = "";
    return;
  }
  const key = [path.x1, path.y1, path.x2, path.y2].map(Math.round).join(",");
  if (key === dragKey && dragRun) return;
  dragKey = key;
  dragRun?.cancel();
  const at = (x, y, size = 1) => `translate(${x}px, ${y}px) scale(${size})`;
  const start = at(path.x1, path.y1);
  const end = at(path.x2, path.y2);
  if (window.matchMedia?.("(prefers-reduced-motion: reduce)").matches) {
    el.style.transform = end; // still shows where it goes
    dragRun = null;
    return;
  }
  el.style.transform = start;
  dragRun = el.animate(
    [
      { offset: 0, transform: start },
      { offset: 0.12, transform: start },
      { offset: 0.18, transform: at(path.x1, path.y1, 1.12), easing: "cubic-bezier(0.45, 0, 0.25, 1)" },
      { offset: 0.6, transform: at(path.x2, path.y2, 1.12) },
      { offset: 0.68, transform: end },
      { offset: 0.8, transform: end, easing: "ease-in-out" },
      { offset: 1, transform: start },
    ],
    { duration: 2800, iterations: Infinity },
  );
}
watch(drag, () => nextTick(runDrag));

const centerOf = (el, down = 0.5) => {
  const rect = el?.getBoundingClientRect();
  if (!rect || !rect.width || !rect.height) return null;
  return { x: rect.left + rect.width / 2, y: rect.top + rect.height * down };
};

/** Where the drag demo starts (the first item in the list) and ends (the bouquet) */
function dragPath() {
  const spec = props.step?.drag;
  if (!spec) return null;
  const from = centerOf(document.querySelector(spec.from));
  // The drop goes to the exact spot the item will land, when the step knows it
  const to = props.step.spot?.() || centerOf(document.querySelector(`[data-tour="${spec.to}"]`), 0.45);
  if (!from || !to || from.y < 0 || from.y > window.innerHeight) return null;
  return { x1: from.x, y1: from.y, x2: to.x, y2: to.y };
}

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
  const height = card?.height || 220;
  Object.assign(tip, placeTip(rect, view, { width, height }));
  // No room beside the part (the bouquet stage), or no part on screen: a corner that keeps the bouquet in sight
  if ((tip.side.startsWith("dock") || tip.side === "center") && props.keepClear) {
    const corner = cornerTip(rect, view, { width, height }, props.keepClear() || []);
    if (corner) Object.assign(tip, corner);
  }
  drag.value = dragPath();
  // Placing (the bouquet is the part): mark the landing spot itself
  const spot = !drag.value && props.step.target === "stage" && !props.step.celebrate ? props.step.spot?.() : null;
  spotMark.value = spot && spot.y > 0 && spot.y < view.height ? spot : null;
  // Keep the card off the drag path: beside the list it drops to the bottom of the screen
  if (drag.value && rect && (tip.side === "right" || tip.side === "left")) {
    tip.top = Math.max(16, view.height - height - 16);
    const cy = rect.top + rect.height / 2;
    tip.arrow = { ...tip.arrow, y: Math.max(tip.top + 18, Math.min(Math.max(cy, rect.top + 30), tip.top + height - 18)) };
  }
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
watch(() => [props.step?.title, props.step?.body, props.step?.waiting, props.step?.doneText, props.step?.celebrate?.text], () => nextTick(queue));
// The win takes the focus to Next, so Enter goes on without waiting
watch(
  () => props.step?.celebrate?.text,
  (text) => text && nextTick(() => nextEl.value?.focus({ preventScroll: true })),
);

function onKey(event) {
  if (props.step && event.key === "Escape") emit("close");
}

// The spot moves on screen when the view is turned: keep the arrow on it every frame
let spotFrame = 0;
function followSpot() {
  spotFrame = requestAnimationFrame(followSpot);
  if (!spotMark.value || !props.step?.spot) return;
  const spot = props.step.spot();
  if (spot && (Math.abs(spot.x - spotMark.value.x) > 0.5 || Math.abs(spot.y - spotMark.value.y) > 0.5)) spotMark.value = spot;
}

let poll = 0;
onMounted(() => {
  window.addEventListener("resize", queue);
  window.addEventListener("scroll", queue, true);
  window.addEventListener("keydown", onKey);
  poll = setInterval(() => props.step && queue(), 500); // panels open and close under it
  followSpot();
});
onUnmounted(() => {
  window.removeEventListener("resize", queue);
  window.removeEventListener("scroll", queue, true);
  window.removeEventListener("keydown", onKey);
  clearInterval(poll);
  cancelAnimationFrame(frame);
  cancelAnimationFrame(spotFrame);
  dragRun?.cancel();
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

/* Follows the spot every frame, so no easing behind it */
.ct-spot-arrow {
  transition: none;
}

@keyframes ct-left { 50% { transform: translateX(-6px); } }
@keyframes ct-right { 50% { transform: translateX(6px); } }
@keyframes ct-up { 50% { transform: translateY(-6px); } }
@keyframes ct-down { 50% { transform: translateY(6px); } }

/* Drag demo */
.ct-trail {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  overflow: visible;
}

.ct-trail line {
  stroke: #ffffff;
  stroke-width: 3;
  stroke-linecap: round;
  stroke-dasharray: 2 9;
  filter: drop-shadow(0 1px 2px rgba(38, 45, 57, 0.45));
  animation: ct-march 0.8s linear infinite;
}

.ct-trail marker path {
  fill: #ffffff;
}

@keyframes ct-march {
  to { stroke-dashoffset: -11; }
}

.ct-drop {
  position: absolute;
  width: 56px;
  height: 56px;
  margin: -28px 0 0 -28px;
  border: 3px dashed #ffffff;
  border-radius: 50%;
  background: rgba(77, 177, 115, 0.35);
  animation: ct-drop 2.8s ease-in-out infinite;
}

@keyframes ct-drop {
  0%, 45% { transform: scale(0.85); opacity: 0.6; }
  60%, 75% { transform: scale(1.1); opacity: 1; }
  100% { transform: scale(0.85); opacity: 0.6; }
}

/* Placed by transform alone (see runDrag): its point is the item's centre */
.ct-drag {
  position: absolute;
  left: 0;
  top: 0;
  width: 0;
  height: 0;
  will-change: transform;
}

.ct-drag-chip {
  position: absolute;
  left: -22px;
  top: -22px;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 2px solid #ffffff;
  border-radius: 50%;
  background: var(--leaf);
  color: #ffffff;
  box-shadow: 0 8px 20px rgba(38, 45, 57, 0.35);
}

.ct-drag-chip :deep(svg) {
  width: 22px;
  height: 22px;
}

.ct-drag-pointer {
  position: absolute;
  left: 6px;
  top: 4px;
  color: var(--ink);
}

.ct-drag-pointer :deep(svg) {
  width: 30px;
  height: 30px;
  fill: #ffffff;
  stroke: var(--ink);
  stroke-width: 1.6;
  filter: drop-shadow(0 2px 3px rgba(38, 45, 57, 0.35));
}

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

/* The win: a green badge that pops in, the message large, a bar running out */
.ct-win {
  display: flex;
  align-items: center;
  gap: 14px;
}

.ct-win-badge {
  flex: none;
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--leaf);
  color: #ffffff;
  box-shadow: 0 0 0 6px rgba(77, 177, 115, 0.16);
  animation: ct-pop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

.ct-win-badge :deep(svg) {
  width: 24px;
  height: 24px;
  stroke-width: 2.6;
}

@keyframes ct-pop {
  from { transform: scale(0.4); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.ct-win .ct-title {
  color: var(--leaf-deep);
}

.ct-win-text {
  margin: 4px 0 0;
  color: var(--ink);
  font-size: 15px;
  font-weight: 500;
  line-height: 1.45;
}

.ct-timer {
  height: 4px;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(77, 177, 115, 0.15);
}

.ct-timer span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--leaf);
  transform-origin: left center;
  animation: ct-timer linear both;
}

@keyframes ct-timer {
  from { transform: scaleX(1); }
  to { transform: scaleX(0); }
}

.ct-timer-label {
  margin: -4px 0 0;
  color: var(--ink-soft);
  font-size: 12.5px;
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
  .ct-wait-dot,
  .ct-trail line,
  .ct-drop,
  .ct-win-badge {
    animation: none;
  }
  .ct-hole,
  .ct-card,
  .ct-arrow {
    transition: none;
  }
}
</style>
