<template>
  <!-- Small tag for product lists -->
  <span v-if="state && variant === 'chip'" class="mod-chip" :class="state.tone" :title="product.moderation_reason || state.title">
    {{ state.chip }}
  </span>

  <!-- Explanation in the product details -->
  <div v-else-if="state && variant === 'banner'" class="mod-banner" :class="state.tone" role="status">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg>
    <div>
      <strong>{{ state.title }}</strong>
      <span v-if="product.moderation_reason">Reason: {{ product.moderation_reason }}</span>
      <span>{{ state.help }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";

/**
 * Shows vendors when BloomCraft has taken one of their listings down.
 * Nothing shows for live listings.
 */
const props = defineProps({
  product: { type: Object, required: true },
  variant: { type: String, default: "chip" },
});

const STATES = {
  hidden: {
    tone: "warn",
    chip: "Hidden by BloomCraft",
    title: "BloomCraft has hidden this listing from customers",
    help: "Fix the details and save the product. It goes back to BloomCraft for review and returns to the shop once approved.",
  },
  resubmitted: {
    tone: "info",
    chip: "Awaiting review",
    title: "Your changes are waiting for BloomCraft's review",
    help: "The listing stays hidden from customers until it's approved.",
  },
  removed: {
    tone: "bad",
    chip: "Removed by BloomCraft",
    title: "BloomCraft has removed this listing from the shop",
    help: "Customers can't see or buy it. Contact BloomCraft support if you think this is a mistake.",
  },
};

const state = computed(() => STATES[props.product?.moderation_status] ?? null);
</script>

<style scoped>
.mod-chip {
  display: inline-block;
  margin-left: 6px;
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 10.5px;
  font-weight: 600;
  white-space: nowrap;
  vertical-align: middle;
}
.mod-banner {
  display: flex;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 10px;
  margin-bottom: 14px;
  font-size: 13px;
  line-height: 1.45;
}
.mod-banner svg {
  flex-shrink: 0;
  margin-top: 1px;
}
.mod-banner div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.warn { background: #fffbeb; color: #92400e; }
.mod-banner.warn { border: 1px solid #fde68a; }
.info { background: #eff6ff; color: #1e40af; }
.mod-banner.info { border: 1px solid #bfdbfe; }
.bad { background: #fef2f2; color: #991b1b; }
.mod-banner.bad { border: 1px solid #fecaca; }
</style>
