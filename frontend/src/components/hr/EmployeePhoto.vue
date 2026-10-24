<template>
  <div class="ep" :class="{ editable: canEdit }" :style="{ '--ep-size': `${size}px` }">
    <button
      v-if="canEdit"
      type="button"
      class="ep-face"
      :style="{ background: shown ? 'transparent' : color }"
      :disabled="busy"
      :title="shown ? 'Change photo' : 'Add a photo'"
      :aria-label="shown ? `Change ${name}'s photo` : `Add a photo of ${name}`"
      @click="pick"
    >
      <img v-if="shown" :src="shown" alt="" @error="broken = true" />
      <span v-else>{{ initials }}</span>
      <span class="ep-cam" :class="{ busy }" aria-hidden="true" v-html="busy ? SPIN : ICONS.image"></span>
    </button>
    <span v-if="canEdit && !busy" class="ep-badge" aria-hidden="true" v-html="ICONS.image"></span>
    <span v-else-if="!canEdit" class="ep-face" :style="{ background: shown ? 'transparent' : color }">
      <img v-if="shown" :src="shown" alt="" @error="broken = true" />
      <span v-else>{{ initials }}</span>
    </span>

    <div v-if="canEdit && withActions" class="ep-actions">
      <button type="button" class="ep-link" :disabled="busy" @click="pick">{{ shown ? "Change photo" : "Add photo" }}</button>
      <button v-if="shown && profileId" type="button" class="ep-link danger" :disabled="busy" @click="remove">Remove</button>
    </div>
    <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" class="ep-input" @change="onFile" />
  </div>
</template>

<script setup>
// An employee's profile picture. With canEdit it can be changed or removed:
// right away when the profile exists (profileId), or held as a file for a
// new profile, which the form uploads once it is saved (see uploadPhoto).
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { toast } from "vue3-toastify";
import { SHOP_ICONS as ICONS } from "../vendor/shop/shopIcons";
import { MAX_PHOTO_BYTES, removePhoto, uploadPhoto } from "./employeePhotoApi";

const props = defineProps({
  profileId: { type: [Number, String], default: null },
  src: { type: String, default: "" },
  name: { type: String, default: "" },
  color: { type: String, default: "var(--bc-accent)" },
  size: { type: Number, default: 44 },
  canEdit: { type: Boolean, default: false },
  withActions: { type: Boolean, default: false },
});
const emit = defineEmits(["update:src", "file"]);

const SPIN = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.2-8.56"/></svg>`;

const input = ref(null);
const busy = ref(false);
const broken = ref(false);
const preview = ref("");

const shown = computed(() => preview.value || (broken.value ? "" : props.src));
const initials = computed(() => (props.name || "?").split(/\s+/).filter(Boolean).map((w) => w[0]).slice(0, 2).join("").toUpperCase());

watch(() => props.src, () => (broken.value = false));

const setPreview = (file) => {
  if (preview.value) URL.revokeObjectURL(preview.value);
  preview.value = file ? URL.createObjectURL(file) : "";
};
onBeforeUnmount(() => setPreview(null));

const pick = () => input.value?.click();

async function onFile(e) {
  const file = e.target.files?.[0];
  e.target.value = "";
  if (!file) return;
  if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return toast.error("Choose a JPG, PNG or WebP picture.");
  if (file.size > MAX_PHOTO_BYTES) return toast.error("Choose a photo under 5 MB.");

  setPreview(file);
  // A profile that isn't saved yet: hand the file to the form
  if (!props.profileId) return emit("file", file);

  busy.value = true;
  try {
    const url = await uploadPhoto(props.profileId, file);
    emit("update:src", url || "");
    toast.success("Photo saved.");
  } catch (err) {
    setPreview(null);
    toast.error(err.message);
  } finally {
    busy.value = false;
  }
}

async function remove() {
  busy.value = true;
  try {
    await removePhoto(props.profileId);
    setPreview(null);
    emit("update:src", "");
    toast.success("Photo removed.");
  } catch (err) {
    toast.error(err.message);
  } finally {
    busy.value = false;
  }
}
</script>

<style scoped>
.ep {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
}
.ep-face {
  position: relative;
  flex: none;
  display: grid;
  place-items: center;
  width: var(--ep-size);
  height: var(--ep-size);
  padding: 0;
  overflow: hidden;
  border: 0;
  border-radius: calc(var(--ep-size) * 0.3);
  color: #fff;
  font: inherit;
  font-size: calc(var(--ep-size) * 0.34);
  font-weight: 600;
}
.ep-face img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
button.ep-face {
  cursor: pointer;
}
button.ep-face:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.ep-cam {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(15, 23, 42, 0.55);
  color: #fff;
  opacity: 0;
  transition: opacity 0.15s;
}
.ep-cam :deep(svg) {
  width: 40%;
  max-width: 20px;
  height: auto;
}
button.ep-face:hover .ep-cam,
button.ep-face:focus-visible .ep-cam,
.ep-cam.busy {
  opacity: 1;
}
.ep-cam.busy :deep(svg) {
  animation: ep-spin 0.8s linear infinite;
}
@keyframes ep-spin {
  to {
    transform: rotate(360deg);
  }
}
/* Always-visible hint that the picture can be changed */
.ep-badge {
  position: absolute;
  top: calc(var(--ep-size) - 16px);
  left: calc(var(--ep-size) - 16px);
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border: 2px solid var(--v-surface);
  border-radius: 50%;
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
  pointer-events: none;
}
.ep-badge :deep(svg) {
  width: 11px;
  height: 11px;
}
.ep-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
}
.ep-link {
  padding: 0;
  border: 0;
  background: none;
  color: var(--bc-accent-deep);
  font: inherit;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
}
.ep-link:hover {
  text-decoration: underline;
}
.ep-link.danger {
  color: var(--v-red-text);
}
.ep-link:disabled {
  opacity: 0.5;
  cursor: default;
}
.ep-input {
  display: none;
}
</style>
