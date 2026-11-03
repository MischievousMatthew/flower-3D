<template>
  <div v-if="photos?.length" class="mp" :class="{ end: alignEnd }">
    <a v-for="p in photos" :key="p.url" :href="p.url" target="_blank" rel="noopener" class="mp-photo" :title="`Open ${p.name} full size`">
      <img :src="p.url" :alt="p.name" loading="lazy" />
    </a>
  </div>
</template>

<script setup>
/** Photos sent with a Help & support message; each opens full size */
defineProps({
  photos: { type: Array, default: () => [] },
  alignEnd: { type: Boolean, default: false },
});
</script>

<style scoped>
.mp {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 6px;
}
.mp.end {
  justify-content: flex-end;
}
.mp-photo {
  display: block;
  width: 120px;
  height: 120px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--line, var(--v-border, #e5e7eb));
  background: var(--v-subtle, #f1f5f9);
}
.mp-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.2s ease;
}
.mp-photo:hover img {
  transform: scale(1.04);
}
.mp-photo:focus-visible {
  outline: 2px solid var(--bc-accent, #4db173);
  outline-offset: 2px;
}
@media (max-width: 480px) {
  .mp-photo {
    width: 96px;
    height: 96px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .mp-photo img {
    transition: none;
  }
}
</style>
