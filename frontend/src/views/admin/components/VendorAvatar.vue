<template>
  <span class="v-avatar" :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${Math.round(size * 0.4)}px` }">
    <img v-if="vendor.logo_url && !broken" :src="vendor.logo_url" alt="" @error="broken = true" />
    <template v-else>{{ initial }}</template>
  </span>
</template>

<script setup>
import { computed, ref, watch } from "vue";

/** A vendor's store logo, or the store's first letter when there is none */
const props = defineProps({
  vendor: { type: Object, required: true },
  size: { type: Number, default: 30 },
});

const broken = ref(false);
const initial = computed(() => (props.vendor.store_name || "?").charAt(0).toUpperCase());
watch(() => props.vendor.logo_url, () => (broken.value = false));
</script>

<style scoped>
.v-avatar {
  border-radius: 50%;
  background: linear-gradient(135deg, #7c6ff7, #5b4fc9);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  flex-shrink: 0;
  overflow: hidden;
  font-family: "Poppins", "Segoe UI", sans-serif;
}
.v-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
