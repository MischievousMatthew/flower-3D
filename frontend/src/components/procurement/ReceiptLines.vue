<template>
  <div class="rl">
    <ul class="rl-lines">
      <li v-for="l in receipt.lines ?? []" :key="l.id">
        <div class="rl-top">
          <b>{{ l.product_name }}</b>
          <span v-if="conditionOf(l.condition)" class="sp-chip" :class="conditionOf(l.condition).tone">{{ conditionOf(l.condition).label }}</span>
        </div>
        <div class="rl-nums">
          <span>{{ l.delivered_qty }} delivered</span>
          <span class="good">{{ l.accepted_qty }} into stock</span>
          <span v-if="l.rejected_qty" class="bad">{{ l.rejected_qty }} rejected{{ l.reject_reason ? ` · ${reasonText(l.reject_reason).toLowerCase()}` : "" }}</span>
          <span v-if="l.missing_qty" class="warn">{{ l.missing_qty }} missing{{ l.close_short ? " · not coming" : "" }}</span>
          <span v-else-if="l.close_short" class="warn">rest closed</span>
        </div>
        <div v-if="(l.checks ?? []).length" class="rl-checks">
          <span v-for="c in l.checks" :key="c"><span v-html="ICONS.check"></span>{{ checkText(c) }}</span>
        </div>
        <p v-if="l.notes" class="rl-note">{{ l.notes }}</p>
      </li>
    </ul>
    <p v-if="receipt.notes" class="rl-note">{{ receipt.notes }}</p>
    <div v-if="(receipt.files ?? []).length" class="rl-files">
      <a v-for="f in receipt.files" :key="f.id" :href="f.url" target="_blank" rel="noopener" class="rl-file" :class="f.kind" :title="f.original_name || (f.kind === 'receipt' ? 'Delivery receipt' : 'Photo')">
        <img v-if="isImage(f)" :src="f.url" :alt="f.kind === 'receipt' ? 'Delivery receipt' : 'Inspection photo'" loading="lazy" />
        <span v-else class="rl-doc" v-html="ICONS.note"></span>
        <small v-if="f.kind === 'receipt'">Receipt</small>
      </a>
    </div>
  </div>
</template>

<script setup>
// One delivery's inspection, line by line, with its receipt and photos
import { SHOP_ICONS as ICONS } from "../vendor/shop/shopIcons";
import { conditionOf, reasonText, checkText } from "./receivingUi";

defineProps({ receipt: { type: Object, required: true } });
const isImage = (f) => (f.mime ?? "").startsWith("image/") || /\.(jpe?g|png|webp)(\?|$)/i.test(f.url);
</script>

<style scoped>
.rl-lines {
  list-style: none;
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
}
.rl-lines li {
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--v-subtle);
}
.rl-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.rl-top b {
  font-size: 13px;
  color: var(--v-text);
}
.rl-nums {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  margin-top: 4px;
  font-size: 12px;
  color: var(--v-text-2);
}
.rl-nums .good { color: var(--v-green-text); font-weight: 600; }
.rl-nums .bad { color: var(--v-red-text); font-weight: 600; }
.rl-nums .warn { color: var(--v-amber-text); font-weight: 600; }
.rl-checks {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 10px;
  margin-top: 6px;
  font-size: 11.5px;
  color: var(--v-muted);
}
.rl-checks > span {
  display: inline-flex;
  align-items: center;
  gap: 3px;
}
.rl-checks :deep(svg) {
  width: 12px;
  height: 12px;
  color: var(--v-green-text);
}
.rl-note {
  margin: 6px 0 0;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.rl-files {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}
.rl-file {
  position: relative;
  display: grid;
  place-items: center;
  width: 72px;
  height: 72px;
  overflow: hidden;
  border: 1px solid var(--v-border);
  border-radius: 10px;
  background: var(--v-surface);
}
.rl-file:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.rl-file img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.rl-doc {
  color: var(--v-muted);
}
.rl-doc :deep(svg) {
  width: 24px;
  height: 24px;
}
.rl-file small {
  position: absolute;
  inset: auto 0 0;
  padding: 2px;
  background: rgba(15, 23, 42, 0.7);
  color: #fff;
  font-size: 10px;
  text-align: center;
}
</style>
