<template>
  <div class="bc-chart" :style="{ '--h': `${height}px` }">
    <div class="bc-y" aria-hidden="true">
      <span v-for="t in ticks" :key="t">{{ shortMoney(t) }}</span>
    </div>
    <div class="bc-plot">
      <div class="bc-grid" aria-hidden="true"><i v-for="t in ticks" :key="t"></i></div>
      <div class="bc-cols" :class="{ dense: rows.length > 14 }">
        <div
          v-for="(r, i) in rows"
          :key="r.key ?? i"
          class="bc-col"
          tabindex="0"
          :aria-label="aria(r)"
          @mouseenter="hover = i"
          @mouseleave="hover = null"
          @focus="hover = i"
          @blur="hover = null"
        >
          <div class="bc-bars">
            <span
              v-for="s in series"
              :key="s.key"
              class="bc-bar"
              :style="{ height: `${pct(r[s.key])}%`, background: s.color }"
            ></span>
          </div>
          <span class="bc-x" :class="{ hide: !showLabel(i) }">{{ r.short ?? r.label }}</span>
          <div v-if="hover === i" class="bc-tip" :class="tipSide(i)">
            <strong>{{ r.label }}</strong>
            <span v-for="s in series" :key="s.key"><i :style="{ background: s.color }"></i>{{ s.label }} {{ money(r[s.key]) }}</span>
            <span v-if="extra" class="bc-extra">{{ extra.label }} <b :class="{ neg: r[extra.key] < 0 }">{{ money(r[extra.key]) }}</b></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="bc-legend">
    <span v-for="s in series" :key="s.key"><i :style="{ background: s.color }"></i>{{ s.label }}</span>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { money, shortMoney } from "./financeUi";

// rows: [{ key, label, short?, [series.key]: number }], series: [{ key, label, color }]
const props = defineProps({
  rows: { type: Array, default: () => [] },
  series: { type: Array, default: () => [] },
  extra: { type: Object, default: null }, // { key, label } shown in the tooltip only
  height: { type: Number, default: 220 },
});
const hover = ref(null);

const max = computed(() => {
  const top = Math.max(0, ...props.rows.flatMap((r) => props.series.map((s) => Number(r[s.key]) || 0)));
  if (!top) return 100;
  const mag = 10 ** Math.floor(Math.log10(top));
  return Math.ceil(top / mag) * mag;
});
const ticks = computed(() => [max.value, max.value * 0.75, max.value * 0.5, max.value * 0.25, 0]);
const pct = (v) => Math.max(0, Math.min(100, ((Number(v) || 0) / max.value) * 100));
const showLabel = (i) => {
  const n = props.rows.length;
  const every = n > 24 ? Math.ceil(n / 8) : n > 12 ? 2 : 1;
  return i % every === 0 || i === n - 1;
};
const tipSide = (i) => (i < props.rows.length / 3 ? "left" : i > (props.rows.length * 2) / 3 ? "right" : "");
const aria = (r) => `${r.label}: ${props.series.map((s) => `${s.label} ${money(r[s.key])}`).join(", ")}`;
</script>

<style scoped>
.bc-chart {
  display: flex;
  gap: 8px;
  height: var(--h);
  padding-bottom: 22px;
}
.bc-y {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  width: 44px;
  flex: none;
  font-size: 10.5px;
  color: #a0aec0;
  text-align: right;
  transform: translateY(-6px);
}
.bc-plot {
  position: relative;
  flex: 1;
  min-width: 0;
}
.bc-grid {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.bc-grid i {
  display: block;
  height: 1px;
  background: #edf2f7;
}
.bc-cols {
  position: relative;
  display: flex;
  align-items: flex-end;
  gap: 6px;
  height: 100%;
}
.bc-cols.dense {
  gap: 2px;
}
.bc-col {
  position: relative;
  flex: 1;
  min-width: 0;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  outline: none;
  border-radius: 6px;
}
.bc-col:hover,
.bc-col:focus-visible {
  background: rgba(72, 187, 120, 0.06);
}
.bc-bars {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 2px;
  height: 100%;
}
.bc-bar {
  flex: 1;
  max-width: 22px;
  min-height: 2px;
  border-radius: 5px 5px 2px 2px;
  transition: height 0.4s ease;
}
.bc-x {
  position: absolute;
  left: 50%;
  bottom: -20px;
  transform: translateX(-50%);
  font-size: 10.5px;
  color: #a0aec0;
  white-space: nowrap;
}
.bc-x.hide {
  visibility: hidden;
}
.bc-tip {
  position: absolute;
  bottom: calc(100% - 8px);
  left: 50%;
  z-index: 5;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 170px;
  padding: 9px 11px;
  border-radius: 11px;
  background: #1a202c;
  color: #e2e8f0;
  font-size: 11.5px;
  white-space: nowrap;
  pointer-events: none;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
}
.bc-tip.left {
  left: 0;
  transform: none;
}
.bc-tip.right {
  left: auto;
  right: 0;
  transform: none;
}
.bc-tip strong {
  color: #fff;
  font-size: 12px;
}
.bc-tip span {
  display: flex;
  align-items: center;
  gap: 6px;
}
.bc-tip i,
.bc-legend i {
  width: 9px;
  height: 9px;
  border-radius: 3px;
}
.bc-extra {
  padding-top: 4px;
  border-top: 1px solid #2d3748;
}
.bc-extra b {
  color: #9ae6b4;
}
.bc-extra b.neg {
  color: #feb2b2;
}
.bc-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  margin-top: 6px;
  font-size: 12px;
  color: #4a5568;
}
.bc-legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
</style>
