<template>
  <div class="dd">
    <div
      class="dd-zone"
      :class="{ over, has: !!info, bad: !!error }"
      role="button"
      tabindex="0"
      :aria-label="info ? `Replace ${label}` : `Upload ${label}`"
      @click="input.click()"
      @keydown.enter.prevent="input.click()"
      @keydown.space.prevent="input.click()"
      @dragenter.prevent="$emit('dragenter', $event)"
      @dragover.prevent="$emit('dragover', $event)"
      @dragleave.prevent="$emit('dragleave', $event)"
      @drop.prevent="$emit('drop', $event)"
    >
      <template v-if="!info">
        <svg class="dd-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0-4 4m4-4 4 4" /><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" /></svg>
        <span class="dd-text"><b>Upload {{ label }}</b><small>{{ hint || "Photo or PDF of the original · up to 10 MB" }}</small></span>
      </template>
      <template v-else>
        <img v-if="info.url && /^image\//.test(info.type || '')" :src="info.url" alt="" class="dd-thumb" />
        <span v-else class="dd-file" aria-hidden="true">PDF</span>
        <span class="dd-text"><b>{{ info.name }}</b><small>{{ size(info.size) }} · click to replace</small></span>
        <button type="button" class="dd-remove" :aria-label="`Remove ${label}`" @click.stop="$emit('remove')">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
        </button>
      </template>
    </div>
    <input ref="input" type="file" class="dd-input" :name="field" :accept="accept" @change="$emit('change', $event)" />
    <div v-if="error" class="error-message">{{ error }}</div>
  </div>
</template>

<script setup>
// One document upload for the vendor application. The page keeps the files
// (validation, previews, saved progress); this is only the drop zone.
import { ref } from "vue";

defineProps({
  field: { type: String, required: true },
  label: { type: String, required: true },
  info: { type: Object, default: null },
  over: { type: Boolean, default: false },
  error: { type: String, default: "" },
  hint: { type: String, default: "" },
  accept: { type: String, default: ".jpg,.jpeg,.png,.pdf,image/*,application/pdf" },
});
defineEmits(["change", "drop", "dragenter", "dragover", "dragleave", "remove"]);

const input = ref(null);
const size = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round((b || 0) / 1024))} KB`);
</script>

<style scoped>
.dd-zone {
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 60px;
  padding: 10px 14px;
  border: 1.5px dashed rgba(30, 27, 24, 0.2);
  border-radius: 16px;
  background: var(--paper, #faf8f4);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.dd-zone:hover,
.dd-zone.over,
.dd-zone:focus-visible {
  border-color: var(--rosewood-dark, #8e443a);
  background: #fff;
  outline: none;
}
.dd-zone.has {
  border-style: solid;
  border-color: var(--line, rgba(30, 27, 24, 0.12));
  background: #fff;
}
.dd-zone.bad {
  border-color: rgba(142, 68, 58, 0.55);
}
.dd-icon {
  flex: none;
  width: 26px;
  height: 26px;
  fill: none;
  stroke: var(--rosewood-dark, #8e443a);
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.dd-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}
.dd-text b {
  overflow: hidden;
  font-size: 14px;
  font-weight: 500;
  color: var(--ink, #1e1b18);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.dd-text small {
  font-size: 12.5px;
  color: var(--ink-soft, #6e6761);
}
.dd-thumb,
.dd-file {
  flex: none;
  width: 46px;
  height: 46px;
  border-radius: 10px;
  object-fit: cover;
}
.dd-file {
  display: grid;
  place-items: center;
  background: var(--ivory, #f4f1ec);
  font-size: 11px;
  font-weight: 600;
  color: var(--rosewood-dark, #8e443a);
}
.dd-remove {
  display: grid;
  place-items: center;
  flex: none;
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: var(--ink-soft, #6e6761);
  cursor: pointer;
}
.dd-remove:hover {
  background: var(--ivory, #f4f1ec);
  color: var(--ink, #1e1b18);
}
.dd-remove svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
}
.dd-input {
  display: none;
}
.error-message {
  margin-top: 6px;
  font-size: 13px;
  color: var(--rosewood-dark, #8e443a);
}
</style>
