<template>
  <!--
    Full-screen photo viewer, shared by admin, vendor and ERP pages.
    <PhotoViewer v-model:index="i" :images="urls" :open="show" :alt="name" @close="show = false" />
    Always dark so photos read true in light and dark mode.
  -->
  <Teleport to="body">
    <Transition name="pv">
      <div v-if="open && current" class="photo-viewer" role="dialog" aria-modal="true" :aria-label="`${alt} photos`" @click.self="close">
        <button ref="closeBtn" type="button" class="pv-btn pv-close" aria-label="Close photo" @click="close">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>
        <button v-if="count > 1" type="button" class="pv-btn pv-prev" aria-label="Previous photo" @click="step(-1)">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6" /></svg>
        </button>
        <img :src="current" :alt="alt" class="pv-img" />
        <button v-if="count > 1" type="button" class="pv-btn pv-next" aria-label="Next photo" @click="step(1)">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6" /></svg>
        </button>
        <span v-if="count > 1" class="pv-count">{{ safeIndex + 1 }} / {{ count }}</span>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";

const props = defineProps({
  images: { type: Array, default: () => [] },
  index: { type: Number, default: 0 },
  open: { type: Boolean, default: false },
  alt: { type: String, default: "Product" },
});
const emit = defineEmits(["update:index", "close"]);

const closeBtn = ref(null);
const count = computed(() => props.images.length);
const safeIndex = computed(() => Math.min(Math.max(props.index, 0), Math.max(count.value - 1, 0)));
const current = computed(() => props.images[safeIndex.value] ?? null);

const close = () => emit("close");
const step = (dir) => {
  if (count.value < 2) return;
  emit("update:index", (safeIndex.value + dir + count.value) % count.value);
};

// Runs before the page's own Escape handlers, so Esc closes only the photo
const onKey = (e) => {
  if (e.key === "Escape") close();
  else if (e.key === "ArrowLeft") step(-1);
  else if (e.key === "ArrowRight") step(1);
  else return;
  e.preventDefault();
  e.stopImmediatePropagation();
};

let returnFocus = null;
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      returnFocus = document.activeElement;
      window.addEventListener("keydown", onKey, true);
      nextTick(() => closeBtn.value?.focus());
    } else {
      window.removeEventListener("keydown", onKey, true);
      returnFocus?.focus?.();
      returnFocus = null;
    }
  },
  { immediate: true },
);
onBeforeUnmount(() => window.removeEventListener("keydown", onKey, true));
</script>

<style scoped>
.photo-viewer {
  position: fixed;
  inset: 0;
  z-index: 5000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 56px 72px;
  background: rgba(2, 6, 23, 0.92);
}
.pv-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  border-radius: 8px;
  user-select: none;
}
.pv-btn {
  position: absolute;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  cursor: pointer;
  transition: background 0.2s;
}
.pv-btn:hover {
  background: rgba(255, 255, 255, 0.22);
}
.pv-btn:focus-visible {
  outline: 2px solid #fff;
  outline-offset: 2px;
}
.pv-close {
  top: 16px;
  right: 16px;
}
.pv-prev {
  left: 16px;
}
.pv-next {
  right: 16px;
}
.pv-count {
  position: absolute;
  bottom: 18px;
  left: 50%;
  transform: translateX(-50%);
  padding: 4px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  font-size: 13px;
  font-variant-numeric: tabular-nums;
}
.pv-enter-active,
.pv-leave-active {
  transition: opacity 0.2s ease;
}
.pv-enter-from,
.pv-leave-to {
  opacity: 0;
}
@media (max-width: 640px) {
  .photo-viewer {
    padding: 64px 12px;
  }
  .pv-prev,
  .pv-next {
    top: auto;
    bottom: 12px;
  }
  .pv-prev {
    left: 12px;
  }
  .pv-next {
    right: 12px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .pv-enter-active,
  .pv-leave-active {
    transition: none;
  }
}
</style>
