<template>
  <div class="dpp">
    <div class="dpp-head">
      <b>Photo of the order</b>
      <small>Take or attach a photo of the flowers before they go out. The customer sees it on their order.</small>
    </div>

    <div v-if="preview" class="dpp-preview">
      <img :src="preview" alt="Photo of the order before delivery" />
      <span v-if="!file && existingUrl" class="dpp-tag">Already taken</span>
    </div>
    <div v-else class="dpp-empty" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" /><circle cx="12" cy="13" r="3.5" /></svg>
      <span>No photo yet</span>
    </div>

    <div class="dpp-actions">
      <!-- On a phone this opens the camera straight away -->
      <label class="dpp-btn primary">
        <input type="file" accept="image/*" capture="environment" :disabled="disabled" @change="pick" />
        {{ preview ? "Retake photo" : "Take a photo" }}
      </label>
      <label class="dpp-btn">
        <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="disabled" @change="pick" />
        Choose a photo
      </label>
    </div>
    <p v-if="error" class="dpp-error" role="alert">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";

const props = defineProps({
  // The photo picked now (a File), v-model
  file: { type: [Object, null], default: null },
  // A photo already saved for this order
  existingUrl: { type: String, default: "" },
  disabled: { type: Boolean, default: false },
});
const emit = defineEmits(["update:file"]);

// Cloudinary's free plan takes images up to 10 MB
const MAX_BYTES = 10 * 1024 * 1024;
const TYPES = ["image/jpeg", "image/png", "image/webp"];

const error = ref("");
const localUrl = ref("");
const preview = computed(() => localUrl.value || props.existingUrl || "");

watch(
  () => props.file,
  (f) => {
    if (localUrl.value) URL.revokeObjectURL(localUrl.value);
    localUrl.value = f ? URL.createObjectURL(f) : "";
  },
);

function pick(e) {
  const f = e.target.files?.[0];
  e.target.value = "";
  if (!f) return;
  error.value = "";
  if (!TYPES.includes(f.type)) {
    error.value = "Use a JPG, PNG or WebP photo.";
    return;
  }
  if (f.size > MAX_BYTES) {
    error.value = "The photo must be 10 MB or smaller.";
    return;
  }
  emit("update:file", f);
}

onBeforeUnmount(() => {
  if (localUrl.value) URL.revokeObjectURL(localUrl.value);
});
</script>

<style scoped>
.dpp {
  display: grid;
  gap: 10px;
}
.dpp-head {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.dpp-head b {
  font-size: 13.5px;
  color: var(--v-text);
}
.dpp-head small {
  font-size: 12px;
  line-height: 1.5;
  color: var(--v-muted);
}
.dpp-preview {
  position: relative;
  overflow: hidden;
  border-radius: 14px;
  background: var(--v-subtle);
}
.dpp-preview img {
  display: block;
  width: 100%;
  max-height: 260px;
  object-fit: cover;
}
.dpp-tag {
  position: absolute;
  left: 10px;
  top: 10px;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--v-green-soft);
  color: var(--v-green-text);
  font-size: 11.5px;
  font-weight: 600;
}
.dpp-empty {
  display: grid;
  place-items: center;
  gap: 6px;
  padding: 26px 12px;
  border: 1.5px dashed var(--v-border-strong);
  border-radius: 14px;
  color: var(--v-muted);
  font-size: 12.5px;
}
.dpp-empty svg {
  width: 30px;
  height: 30px;
}
.dpp-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.dpp-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
  padding: 0 16px;
  border: 1px solid var(--v-border-strong);
  border-radius: 10px;
  background: var(--v-surface);
  color: var(--v-text);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.dpp-btn.primary {
  border-color: var(--bc-accent);
  background: var(--bc-accent);
  color: var(--bc-accent-ink, #fff);
}
.dpp-btn input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.dpp-btn:has(input:focus-visible) {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.dpp-error {
  margin: 0;
  font-size: 12px;
  color: var(--v-red-text);
}
</style>
