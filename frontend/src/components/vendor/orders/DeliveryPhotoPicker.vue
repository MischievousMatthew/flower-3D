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
        <input type="file" accept="image/*" capture="environment" :disabled="disabled || stamping" @change="pick" />
        {{ preview ? "Retake photo" : "Take a photo" }}
      </label>
      <label class="dpp-btn">
        <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="disabled || stamping" @change="pick" />
        Choose a photo
      </label>
    </div>
    <p v-if="stamping" class="dpp-note" role="status">Adding the order number, date and time…</p>
    <p v-else-if="!error" class="dpp-note">The order number, date and time are stamped in the corner.</p>
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
  // Stamped in the photo's bottom-right corner with the date and time it was taken
  orderNumber: { type: String, default: "" },
});
const emit = defineEmits(["update:file"]);

// Cloudinary's free plan takes images up to 10 MB; the stamped copy is made smaller
const MAX_BYTES = 10 * 1024 * 1024;
// A phone photo can be bigger than that before it's stamped and shrunk
const MAX_PICK_BYTES = 30 * 1024 * 1024;
// The longest side of the uploaded photo
const MAX_SIDE = 2000;
const TYPES = ["image/jpeg", "image/png", "image/webp"];

const error = ref("");
const stamping = ref(false);
const localUrl = ref("");
const preview = computed(() => localUrl.value || props.existingUrl || "");

watch(
  () => props.file,
  (f) => {
    if (localUrl.value) URL.revokeObjectURL(localUrl.value);
    localUrl.value = f ? URL.createObjectURL(f) : "";
  },
);

async function pick(e) {
  const f = e.target.files?.[0];
  e.target.value = "";
  if (!f) return;
  error.value = "";
  if (!TYPES.includes(f.type)) {
    error.value = "Use a JPG, PNG or WebP photo.";
    return;
  }
  if (f.size > MAX_PICK_BYTES) {
    error.value = "That photo is too big. Use one under 30 MB.";
    return;
  }
  stamping.value = true;
  try {
    const stamped = await stamp(f);
    if (stamped.size > MAX_BYTES) {
      error.value = "The photo must be 10 MB or smaller.";
      return;
    }
    emit("update:file", stamped);
  } catch {
    // Couldn't draw it here (an unusual format): upload it as it is when it fits
    if (f.size > MAX_BYTES) error.value = "The photo must be 10 MB or smaller.";
    else emit("update:file", f);
  } finally {
    stamping.value = false;
  }
}

async function loadImage(file) {
  if (window.createImageBitmap) {
    try {
      // Turned the right way up, as the camera held it
      return await createImageBitmap(file, { imageOrientation: "from-image" });
    } catch {
      // Fall back to an <img> below
    }
  }
  const url = URL.createObjectURL(file);
  try {
    const img = new Image();
    img.src = url;
    await img.decode();
    return img;
  } finally {
    URL.revokeObjectURL(url);
  }
}

/**
 * The photo with the order number, date and time in its bottom-right corner
 * (a dark rounded box, like a timestamp camera), at most 2000px on its
 * longest side, as a JPEG.
 */
async function stamp(file) {
  const img = await loadImage(file);
  const w0 = img.width;
  const h0 = img.height;
  const scale = Math.min(1, MAX_SIDE / Math.max(w0, h0));
  const w = Math.round(w0 * scale);
  const h = Math.round(h0 * scale);
  const canvas = document.createElement("canvas");
  canvas.width = w;
  canvas.height = h;
  const ctx = canvas.getContext("2d");
  ctx.drawImage(img, 0, 0, w, h);
  img.close?.();

  const now = new Date();
  const lines = [
    props.orderNumber || "Order",
    now.toLocaleDateString("en-PH", { month: "long", day: "numeric", year: "numeric" }),
    now.toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" }),
  ];
  // Sized to the photo, so it reads the same on any camera
  const size = Math.max(14, Math.round(Math.min(w, h) * 0.032));
  const lineH = Math.round(size * 1.3);
  const pad = Math.round(size * 0.7);
  const margin = Math.round(size * 0.9);
  const fonts = [`700 ${size}px ui-monospace, Menlo, Consolas, monospace`, `500 ${size}px Poppins, Arial, sans-serif`, `500 ${size}px Poppins, Arial, sans-serif`];
  const widths = lines.map((t, i) => {
    ctx.font = fonts[i];
    return ctx.measureText(t).width;
  });
  const boxW = Math.ceil(Math.max(...widths) + pad * 2);
  const boxH = lineH * lines.length + pad * 2 - Math.round(lineH - size);
  const x = w - boxW - margin;
  const y = h - boxH - margin;

  ctx.fillStyle = "rgba(15, 20, 28, 0.62)";
  const radius = Math.round(size * 0.6);
  ctx.beginPath();
  if (ctx.roundRect) ctx.roundRect(x, y, boxW, boxH, radius);
  else ctx.rect(x, y, boxW, boxH);
  ctx.fill();

  ctx.textAlign = "right";
  ctx.textBaseline = "top";
  lines.forEach((t, i) => {
    ctx.font = fonts[i];
    ctx.fillStyle = i === 0 ? "#ffffff" : "rgba(255, 255, 255, 0.86)";
    ctx.fillText(t, x + boxW - pad, y + pad + i * lineH);
  });

  const blob = await new Promise((res) => canvas.toBlob(res, "image/jpeg", 0.88));
  if (!blob) throw new Error("stamp failed");
  const base = (props.orderNumber || "order").replace(/[^\w-]+/g, "");
  return new File([blob], `${base}-delivery.jpg`, { type: "image/jpeg", lastModified: now.getTime() });
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
.dpp-note {
  margin: 0;
  font-size: 11.5px;
  color: var(--v-faint);
}
.dpp-error {
  margin: 0;
  font-size: 12px;
  color: var(--v-red-text);
}
</style>
