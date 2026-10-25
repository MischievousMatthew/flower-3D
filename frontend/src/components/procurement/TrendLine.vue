<template>
  <figure class="tl" :aria-label="summary">
    <div ref="box" class="tl-plot" @pointermove="move" @pointerleave="hover = -1">
      <svg :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none" aria-hidden="true">
        <line v-for="g in grid" :key="g" class="tl-grid" x1="0" :x2="W" :y1="g" :y2="g" />
        <path class="tl-area" :d="area" />
        <path class="tl-line" :d="line" vector-effect="non-scaling-stroke" />
        <line v-if="hover >= 0" class="tl-cross" :x1="xs[hover]" :x2="xs[hover]" y1="0" :y2="H" vector-effect="non-scaling-stroke" />
      </svg>
      <span v-if="hover >= 0" class="tl-dot" :style="{ left: `${(xs[hover] / W) * 100}%`, top: `${(ys[hover] / H) * 100}%` }"></span>
      <div v-if="hover >= 0" class="tl-tip" :class="{ flip: hover > points.length / 2 }" :style="{ left: `${(xs[hover] / W) * 100}%` }">
        <strong>{{ format(points[hover].value) }}</strong>
        <span>{{ day(points[hover].date) }}</span>
      </div>
      <span class="tl-max">{{ format(max) }}</span>
    </div>
    <div class="tl-axis" aria-hidden="true">
      <span>{{ day(points[0]?.date) }}</span>
      <span>{{ day(points[points.length - 1]?.date) }}</span>
    </div>
  </figure>
</template>

<script setup>
// A single-series line over time, with a crosshair and a tooltip on hover.
// Colors come from the --v-* / --bc-accent tokens, so it follows light and dark.
import { computed, ref } from "vue";

const props = defineProps({
  points: { type: Array, default: () => [] }, // [{ date: "2026-10-01", value: 3 }]
  unit: { type: String, default: "" },
});

const W = 600;
const H = 160;
const box = ref(null);
const hover = ref(-1);

const max = computed(() => Math.max(1, ...props.points.map((p) => p.value)));
const xs = computed(() => props.points.map((_, i) => (props.points.length < 2 ? W / 2 : (i / (props.points.length - 1)) * W)));
const ys = computed(() => props.points.map((p) => H - 6 - (p.value / max.value) * (H - 16)));
const line = computed(() => xs.value.map((x, i) => `${i ? "L" : "M"}${x.toFixed(1)},${ys.value[i].toFixed(1)}`).join(""));
const area = computed(() => (xs.value.length ? `${line.value}L${W},${H}L0,${H}Z` : ""));
const grid = [H * 0.25, H * 0.5, H * 0.75];

const format = (v) => `${Number(v || 0).toLocaleString("en-PH")}${props.unit ? ` ${props.unit}` : ""}`;
const day = (d) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric" }) : "");
const summary = computed(() => {
  const total = props.points.reduce((s, p) => s + p.value, 0);
  const peak = props.points.reduce((a, p) => (p.value > (a?.value ?? -1) ? p : a), null);
  return `${format(total)} from ${day(props.points[0]?.date)} to ${day(props.points.at(-1)?.date)}${peak?.value ? `, most on ${day(peak.date)} (${format(peak.value)})` : ""}`;
});

function move(e) {
  const r = box.value.getBoundingClientRect();
  const n = props.points.length;
  if (!n || !r.width) return;
  hover.value = Math.min(n - 1, Math.max(0, Math.round(((e.clientX - r.left) / r.width) * (n - 1))));
}
</script>

<style scoped>
.tl {
  margin: 0;
}
.tl-plot {
  position: relative;
  height: 160px;
  cursor: crosshair;
}
.tl-plot svg {
  display: block;
  width: 100%;
  height: 100%;
  overflow: visible;
}
.tl-grid {
  stroke: var(--v-border);
  stroke-dasharray: 3 4;
  vector-effect: non-scaling-stroke;
}
.tl-area {
  fill: color-mix(in srgb, var(--bc-accent) 14%, transparent);
}
.tl-line {
  fill: none;
  stroke: var(--bc-accent);
  stroke-width: 2;
  stroke-linejoin: round;
  stroke-linecap: round;
}
.tl-cross {
  stroke: var(--v-border-strong);
  stroke-width: 1;
}
.tl-dot {
  position: absolute;
  width: 10px;
  height: 10px;
  border: 2px solid var(--v-surface);
  border-radius: 50%;
  background: var(--bc-accent);
  transform: translate(-50%, -50%);
  pointer-events: none;
}
.tl-tip {
  position: absolute;
  top: 0;
  display: flex;
  flex-direction: column;
  padding: 6px 10px;
  border: 1px solid var(--v-border-strong);
  border-radius: 10px;
  background: var(--v-raised);
  box-shadow: 0 8px 20px -10px rgba(15, 23, 42, 0.4);
  transform: translateX(10px);
  pointer-events: none;
  white-space: nowrap;
}
.tl-tip.flip {
  transform: translateX(calc(-100% - 10px));
}
.tl-tip strong {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.tl-tip span {
  font-size: 11.5px;
  color: var(--v-muted);
}
.tl-max {
  position: absolute;
  top: -2px;
  left: 0;
  font-size: 11px;
  color: var(--v-faint);
  pointer-events: none;
}
.tl-axis {
  display: flex;
  justify-content: space-between;
  margin-top: 6px;
  font-size: 11.5px;
  color: var(--v-faint);
}
</style>
