<template>
  <ShopLayout section="shop" title="Photo to 3D" subtitle="Turn a photo of a flower or a bouquet into a 3D model you can attach to a product.">
    <div class="p3-layout">
      <!-- Left: the photo and what it shows -->
      <section class="sp-card p3-input" aria-label="Your photo">
        <div class="sp-card-head"><div><h2>1. Your photo</h2><p>One flower or one bouquet, on a plain background, in good light.</p></div></div>

        <label
          class="p3-drop"
          :class="{ over: dragging, has: photoUrl }"
          @dragover.prevent="dragging = true"
          @dragleave.prevent="dragging = false"
          @drop.prevent="onDrop"
        >
          <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="working" @change="onPick" />
          <img v-if="photoUrl" :src="photoUrl" alt="Your photo" />
          <span v-else class="p3-drop-empty">
            <span class="p3-drop-icon" v-html="ICONS.image"></span>
            <b>Drop a photo here or choose one</b>
            <small>JPG, PNG or WebP · up to 10 MB</small>
          </span>
        </label>
        <p v-if="photoError" class="sp-error">{{ photoError }}</p>

        <div class="p3-field">
          <span class="p3-label">2. What's in it?</span>
          <div class="sp-seg" role="radiogroup" aria-label="What's in the photo">
            <button v-for="k in KINDS" :key="k.key" type="button" role="radio" :aria-checked="kind === k.key" :class="{ on: kind === k.key }" :disabled="working" @click="kind = k.key">{{ k.label }}</button>
          </div>
        </div>

        <div class="p3-actions">
          <button type="button" class="sp-btn sp-btn-primary" :disabled="!photo || working" @click="makeWithAI">
            <span v-html="ICONS.sparkle"></span>{{ working ? "Working…" : "Make 3D with free AI" }}
          </button>
          <button type="button" class="sp-btn sp-btn-ghost" :disabled="!photo || working" @click="useMatched">Build a matching 3D</button>
        </div>

        <ul class="p3-notes">
          <li><b>Free AI</b> rebuilds the real shape from your photo. The most detailed model (TRELLIS) allows about one conversion a day per internet connection; after that an unlimited free model (TripoSR) takes over, with smoother, less detailed results in about 1–2 minutes.</li>
          <li><b>Matching 3D</b> builds BloomCraft's own rose, tulip, peony or daisy in your photo's colors. Instant, works any time.</li>
          <li>Photos sent to the free AI go to Hugging Face's public demo servers.</li>
        </ul>
      </section>

      <!-- Right: progress and the result -->
      <section class="sp-card p3-output" aria-label="3D result" aria-live="polite">
        <div class="sp-card-head">
          <div><h2>3. Your 3D model</h2><p>{{ resultHint }}</p></div>
          <span v-if="result" class="sp-chip" :class="result.source === 'ai' ? 'violet' : 'teal'">{{ result.source === "ai" ? `Made by AI · ${result.provider}` : "Matching build" }}</span>
        </div>

        <!-- Working: skeleton + live status, never a spinner -->
        <div v-if="working" class="p3-stage">
          <div class="bc-skeleton p3-stage-sk"></div>
          <div class="p3-status">
            <b>{{ stageText }}</b>
            <small v-if="switchNote" class="p3-switch">{{ switchNote }}</small>
            <small>{{ elapsedText }} · {{ slowModel ? "usually 1–2 min" : "usually 30 s – 3 min" }}. You can keep this tab open and do other things.</small>
          </div>
        </div>

        <!-- AI couldn't do it: say why, offer the matched build -->
        <div v-else-if="aiError && !result" class="p3-stage p3-message">
          <div class="sp-banner warn">
            <span v-html="ICONS.alert"></span>
            <div><b>{{ aiError.title }}</b><p>{{ aiError.body }}</p></div>
          </div>
          <button type="button" class="sp-btn sp-btn-primary" @click="useMatched">Build a matching 3D instead</button>
        </div>

        <div v-else-if="result" class="p3-result">
          <ModelEditor ref="editor" :model-url="result.url" :reset-key="resultKey" :dark="isDark" :clean="result.source === 'ai'" @cleaned="(n) => (cleaned = n)" />
          <p class="p3-size">{{ fileSize(result.blob.size) }}<template v-if="cleaned"> · removed {{ cleaned }} stray {{ cleaned === 1 ? "fragment triangle" : "fragment triangles" }}</template><template v-if="result.note"> · {{ result.note }}</template><template v-if="tooBig"> · too large to attach (10 MB max on the free plan); download it instead.</template></p>
        </div>

        <div v-else class="sp-empty p3-stage">
          <span class="sp-empty-icon" v-html="ICONS.cube"></span>
          <h3>No 3D model yet</h3>
          <p>Add a photo, then choose free AI or a matching build.</p>
        </div>

        <!-- Matching build controls -->
        <div v-if="mode === 'matched' && result" class="p3-tune">
          <template v-if="kind === 'single'">
            <span class="p3-label">Flower</span>
            <div class="p3-picks">
              <button v-for="h in HEAD_TYPES" :key="h" type="button" class="sp-pick" :class="{ on: head === h }" @click="head = h">{{ HEAD_LABELS[h] }}</button>
            </div>
          </template>
          <template v-else>
            <span class="p3-label">Flowers in the bouquet <small>({{ headTotal }} of {{ MAX_BOUQUET_HEADS }})</small></span>
            <div class="p3-counts">
              <label v-for="h in HEAD_TYPES" :key="h" class="p3-count">
                <span>{{ HEAD_PLURALS[h] }}</span>
                <input v-model.number="counts[h]" type="number" min="0" :max="MAX_BOUQUET_HEADS" step="1" class="sp-input" @change="clampCounts(h)" />
              </label>
            </div>
            <span class="p3-label">Wrapping paper</span>
            <div class="p3-swatches">
              <button v-for="p in PAPER_PRESETS" :key="p" type="button" class="p3-swatch" :class="{ on: paper === p }" :style="{ background: p }" :aria-label="`Paper ${p}`" @click="paper = p"></button>
            </div>
          </template>
          <span class="p3-label">Colors from your photo <small>(tap to change)</small></span>
          <div class="p3-swatches">
            <label v-for="(c, i) in colors" :key="i" class="p3-swatch" :style="{ background: c }" :title="`Flower color ${i + 1}`">
              <input type="color" :value="c" :aria-label="`Flower color ${i + 1}`" @input="colors[i] = $event.target.value" />
            </label>
            <label class="p3-swatch leaf" :style="{ background: leaf }" title="Leaves and stems">
              <input type="color" :value="leaf" aria-label="Leaves and stems" @input="leaf = $event.target.value" />
            </label>
          </div>
        </div>

        <!-- What to do with it -->
        <div v-if="result && !working" class="p3-use">
          <div class="p3-actions">
            <button type="button" class="sp-btn sp-btn-primary" :disabled="exporting" title="Saves your rotation, size and brightness changes in the file" @click="download"><span v-html="ICONS.download"></span>{{ exporting ? "Preparing…" : "Download .glb" }}</button>
            <button type="button" class="sp-btn sp-btn-ghost" @click="reset">Start over</button>
          </div>
          <div class="p3-attach">
            <span class="p3-label">Attach to a product</span>
            <div class="p3-attach-row">
              <select v-model="productId" class="sp-select" aria-label="Product" :disabled="!products || tooBig || attaching">
                <option :value="null" disabled>{{ products ? (products.length ? "Choose a product" : "No products yet") : "Loading your products…" }}</option>
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}{{ p.has_model ? " (has a 3D model)" : "" }}</option>
              </select>
              <button type="button" class="sp-btn sp-btn-dark" :disabled="!productId || tooBig || attaching" @click="attach">{{ attaching ? "Attaching…" : "Attach" }}</button>
            </div>
            <small v-if="selectedProduct?.has_model" class="sp-hint">This replaces {{ selectedProduct.name }}'s current 3D model.</small>
          </div>
        </div>
      </section>
    </div>
  </ShopLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { toast } from "@/utils/snackbar";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import ModelEditor from "../../../components/vendor/photo3d/ModelEditor.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage, useVendorCatalog } from "../../../components/vendor/shop/useVendorCatalog";
import { convertWithAI } from "../../../components/vendor/photo3d/aiProviders";
import { extractPalette } from "../../../components/vendor/photo3d/photoPalette";
import { HEAD_LABELS, HEAD_PLURALS, HEAD_TYPES, MAX_BOUQUET_HEADS, PAPER_PRESETS, buildMatchedModel, exportGlb } from "../../../components/vendor/photo3d/matchedModel";
import { useVendorTheme } from "../../../composables/useVendorTheme";
import "../../../components/vendor/shop/shop.css";

const KINDS = [
  { key: "single", label: "Single flower" },
  { key: "bouquet", label: "Bouquet" },
];
const MAX_PHOTO_BYTES = 10 * 1024 * 1024;
const MAX_ATTACH_BYTES = 10 * 1024 * 1024; // Cloudinary free tier
const MAX_SIDE = 1024;
const STAGES = {
  connect: "Connecting to the free AI…",
  background: "Removing the background…",
  shape: "Building the 3D shape…",
  download: "Downloading your 3D model…",
};

const { isDark } = useVendorTheme();
const { products, load: loadProducts } = useVendorCatalog();

const photo = ref(null); // resized Blob sent to the AI
const photoUrl = ref("");
const photoName = ref("flower");
const photoError = ref("");
const dragging = ref(false);
const kind = ref("single");

const working = ref(false);
const stage = ref("connect");
const providerLabel = ref("");
const switchNote = ref(""); // why we moved on to the unlimited model
const startedAt = ref(0);
const now = ref(Date.now());
let ticker = null;
let alive = true;

const mode = ref(null); // "ai" | "matched"
const result = ref(null); // { blob, url, source, provider }
const aiError = ref(null);

// Matching build options
const colors = ref(["#c81e2d"]);
const leaf = ref("#4c8a3c");
const head = ref("rose");
const counts = reactive({ rose: 7, tulip: 0, peony: 0, daisy: 0 });
const paper = ref(PAPER_PRESETS[0]);

const productId = ref(null);
const attaching = ref(false);

const editor = ref(null);
const resultKey = ref(0); // a new key resets the editor's changes
const cleaned = ref(0);
const exporting = ref(false);
const tooBig = computed(() => (result.value?.blob.size ?? 0) > MAX_ATTACH_BYTES);
const selectedProduct = computed(() => products.value?.find((p) => p.id === productId.value) ?? null);
const headTotal = computed(() => HEAD_TYPES.reduce((n, h) => n + (Number(counts[h]) || 0), 0));
const stageText = computed(() => `${STAGES[stage.value] || "Working…"}${providerLabel.value ? ` (${providerLabel.value})` : ""}`);
// TripoSR runs on a free CPU, so it is slower than the GPU models
const slowModel = computed(() => providerLabel.value === "TripoSR");
const elapsedText = computed(() => {
  const s = Math.max(0, Math.round((now.value - startedAt.value) / 1000));
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, "0")}`;
});
const resultHint = computed(() => {
  if (working.value) return "Hang tight — the free AI is working on it.";
  if (result.value) return "Drag to spin it around, scroll to zoom.";
  return "It will show up here.";
});

const fileSize = (bytes) => (bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

// ── Photo ──────────────────────────────────────────────────────────────
function onPick(e) {
  const file = e.target.files?.[0];
  e.target.value = "";
  if (file) setPhoto(file);
}
function onDrop(e) {
  dragging.value = false;
  const file = e.dataTransfer?.files?.[0];
  if (file) setPhoto(file);
}

async function setPhoto(file) {
  photoError.value = "";
  if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
    photoError.value = "Use a JPG, PNG or WebP photo. (iPhone HEIC photos: share or export as JPG first.)";
    return;
  }
  if (file.size > MAX_PHOTO_BYTES) {
    photoError.value = "That photo is over 10 MB. Try a smaller one.";
    return;
  }
  try {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement("canvas");
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const ctx = canvas.getContext("2d", { willReadFrequently: true });
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();
    const type = file.type === "image/png" ? "image/png" : "image/jpeg";
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, 0.92));
    if (!blob) throw new Error("resize failed");

    // Colors for the matching build, read from a small copy
    const small = document.createElement("canvas");
    const k = Math.min(1, 160 / Math.max(canvas.width, canvas.height));
    small.width = Math.max(1, Math.round(canvas.width * k));
    small.height = Math.max(1, Math.round(canvas.height * k));
    const sctx = small.getContext("2d", { willReadFrequently: true });
    sctx.drawImage(canvas, 0, 0, small.width, small.height);
    const palette = extractPalette(sctx.getImageData(0, 0, small.width, small.height));

    reset();
    photo.value = blob;
    if (photoUrl.value) URL.revokeObjectURL(photoUrl.value);
    photoUrl.value = URL.createObjectURL(blob);
    photoName.value = file.name.replace(/\.[^.]+$/, "").replace(/[^a-z0-9-]+/gi, "-").replace(/^-|-$/g, "").toLowerCase() || "flower";
    colors.value = palette.flowers;
    leaf.value = palette.leaf;
  } catch (_) {
    photoError.value = "Couldn't read that photo. Try another one.";
  }
}

// ── Results ────────────────────────────────────────────────────────────
const RESULT_NOTES = {
  TripoSR: "Made by the unlimited free model: smoother and less detailed than TRELLIS. Try TRELLIS again after its daily limit resets for more detail.",
};

function showResult(blob, source, provider = "") {
  const fresh = !result.value || result.value.source !== source || source === "ai";
  if (result.value?.url) URL.revokeObjectURL(result.value.url);
  if (fresh) {
    resultKey.value++;
    cleaned.value = 0;
  }
  result.value = { blob, url: URL.createObjectURL(blob), source, provider, note: RESULT_NOTES[provider] || "" };
}

function reset() {
  if (result.value?.url) URL.revokeObjectURL(result.value.url);
  result.value = null;
  aiError.value = null;
  mode.value = null;
}

const retryText = (seconds) => {
  if (!seconds) return "later today";
  const h = Math.floor(seconds / 3600), m = Math.round((seconds % 3600) / 60);
  return h ? `in about ${h} h ${m} min` : `in about ${Math.max(1, m)} min`;
};

function explain(err) {
  switch (err?.code) {
    case "quota":
      return { title: "The free AI can't take more photos right now", body: `Today's detailed-model limit is used up (it resets ${retryText(err.retryInSeconds)}) and the unlimited free model isn't answering. Try again in a few minutes, or build a matching 3D now.` };
    case "busy":
      return { title: "The free AI is busy", body: "Lots of people are using it right now. Try again in a few minutes, or build a matching 3D now." };
    case "bad-photo":
      return { title: "The AI couldn't find a flower in this photo", body: "Try a photo with one flower or bouquet in the middle and a plain background." };
    default:
      return { title: "The free AI isn't available right now", body: "The free demo servers may be restarting or offline. Try again later, or build a matching 3D now." };
  }
}

async function makeWithAI() {
  if (!photo.value || working.value) return;
  reset();
  mode.value = "ai";
  working.value = true;
  stage.value = "connect";
  providerLabel.value = "";
  switchNote.value = "";
  startedAt.value = Date.now();
  now.value = startedAt.value;
  ticker = window.setInterval(() => (now.value = Date.now()), 1000);
  try {
    const { blob, provider } = await convertWithAI(photo.value, {
      onStage: (s) => alive && (stage.value = s),
      onProvider: (p) => {
        if (!alive) return;
        providerLabel.value = p.label;
        // The CPU model only runs after the GPU ones were skipped or failed
        if (p.pool === "cpu" && !switchNote.value) switchNote.value = "The detailed model isn't available right now, so the unlimited free model is making it.";
      },
      onSkip: () => alive && (switchNote.value = "Today's detailed-model limit is used up, so the unlimited free model is making it."),
    });
    if (!alive) return;
    showResult(blob, "ai", provider.label);
  } catch (err) {
    if (!alive) return;
    aiError.value = explain(err);
  } finally {
    window.clearInterval(ticker);
    if (alive) working.value = false;
  }
}

let building = 0;
async function buildMatched() {
  const run = ++building;
  const model = buildMatchedModel({
    kind: kind.value,
    head: head.value,
    counts: { ...counts },
    colors: colors.value,
    leaf: leaf.value,
    paper: paper.value,
  });
  const blob = await exportGlb(model);
  if (alive && run === building) showResult(blob, "matched");
}

async function useMatched() {
  if (!photo.value) return;
  aiError.value = null;
  mode.value = "matched";
  if (kind.value === "bouquet" && headTotal.value === 0) counts.rose = 7;
  try {
    await buildMatched();
  } catch (_) {
    toast.error("Couldn't build the 3D model. Try again.");
  }
}

// Rebuild the matching 3D as its options change (lightly debounced)
let rebuildTimer = null;
watch([kind, head, paper, leaf, colors, () => ({ ...counts })], () => {
  if (mode.value !== "matched" || !result.value) return;
  window.clearTimeout(rebuildTimer);
  rebuildTimer = window.setTimeout(() => buildMatched().catch(() => {}), 250);
}, { deep: true });

function clampCounts(changed) {
  counts[changed] = Math.max(0, Math.min(MAX_BOUQUET_HEADS, Math.round(Number(counts[changed]) || 0)));
  const over = headTotal.value - MAX_BOUQUET_HEADS;
  if (over > 0) counts[changed] -= over;
}

// ── Download / attach ──────────────────────────────────────────────────
// The model with the editor's changes (rotation, size, brightness) saved in
async function editedBlob() {
  try {
    return (await editor.value?.exportEdited()) ?? result.value.blob;
  } catch (_) {
    return result.value.blob;
  }
}

async function download() {
  if (exporting.value) return;
  exporting.value = true;
  try {
    const blob = await editedBlob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${photoName.value}-3d.glb`;
    a.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 10000);
  } finally {
    exporting.value = false;
  }
}

async function attach() {
  if (!result.value || !productId.value || attaching.value) return;
  attaching.value = true;
  try {
    const blob = await editedBlob();
    if (blob.size > MAX_ATTACH_BYTES) {
      toast.error("The edited model is over 10 MB, too large to attach on the free plan. Download it instead.");
      return;
    }
    const fd = new FormData();
    fd.append("_method", "PUT");
    fd.append("model_file", new File([blob], `${photoName.value}-3d.glb`, { type: "model/gltf-binary" }));
    await api.post(`vendor/products/${productId.value}`, fd);
    toast.success(`3D model attached to ${selectedProduct.value?.name ?? "the product"}.`);
    loadProducts();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't attach the 3D model."));
  } finally {
    attaching.value = false;
  }
}

onMounted(() => {
  if (!products.value) loadProducts();
});

onBeforeUnmount(() => {
  alive = false;
  window.clearInterval(ticker);
  window.clearTimeout(rebuildTimer);
  if (photoUrl.value) URL.revokeObjectURL(photoUrl.value);
  if (result.value?.url) URL.revokeObjectURL(result.value.url);
});
</script>

<style scoped>
.p3-layout {
  display: grid;
  grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
  gap: 18px;
  align-items: start;
}
.p3-input,
.p3-output {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.p3-drop {
  position: relative;
  display: grid;
  place-items: center;
  min-height: 240px;
  overflow: hidden;
  border: 1.5px dashed var(--v-border-strong);
  border-radius: 14px;
  background: var(--v-subtle);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.p3-drop:hover,
.p3-drop.over {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.p3-drop.has {
  border-style: solid;
}
.p3-drop input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.p3-drop img {
  max-width: 100%;
  max-height: 320px;
  object-fit: contain;
}
.p3-drop-empty {
  display: grid;
  justify-items: center;
  gap: 4px;
  padding: 24px;
  text-align: center;
  color: var(--v-muted);
}
.p3-drop-empty b {
  color: var(--v-text);
  font-size: 14px;
}
.p3-drop-empty small {
  font-size: 12px;
}
.p3-drop-icon {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  margin-bottom: 4px;
  border-radius: 12px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
}
.p3-drop-icon :deep(svg) {
  width: 22px;
  height: 22px;
}
.p3-field {
  display: grid;
  gap: 8px;
}
.p3-label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--v-text-2);
}
.p3-label small {
  font-weight: 400;
  color: var(--v-muted);
}
.p3-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.p3-notes {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 12px 14px 12px 28px;
  border-radius: 12px;
  background: var(--v-subtle);
  color: var(--v-muted);
  font-size: 12px;
  line-height: 1.5;
}
.p3-notes b {
  color: var(--v-text-2);
}
.p3-stage {
  display: grid;
  place-items: center;
  gap: 14px;
  min-height: 380px;
}
.p3-stage-sk {
  width: 100%;
  height: 300px;
  border-radius: 14px;
}
.p3-status {
  display: grid;
  gap: 2px;
  justify-items: center;
  text-align: center;
}
.p3-status b {
  font-size: 14px;
  color: var(--v-text);
}
.p3-status .p3-switch {
  color: var(--bc-accent-deep);
  font-weight: 500;
}
.p3-status small {
  font-size: 12px;
  color: var(--v-muted);
}
.p3-message {
  align-content: center;
}
.p3-message .sp-banner {
  max-width: 460px;
}
.p3-message .sp-banner p {
  margin: 2px 0 0;
}
.p3-size {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--v-muted);
}
.p3-tune,
.p3-use {
  display: grid;
  gap: 10px;
  padding-top: 14px;
  border-top: 1px solid var(--v-border);
}
.p3-picks {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.p3-counts {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}
.p3-count {
  display: grid;
  gap: 4px;
  font-size: 12px;
  color: var(--v-muted);
}
.p3-swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.p3-swatch {
  position: relative;
  width: 30px;
  height: 30px;
  padding: 0;
  overflow: hidden;
  border: 2px solid var(--v-surface);
  border-radius: 50%;
  box-shadow: 0 0 0 1px var(--v-border-strong);
  cursor: pointer;
}
.p3-swatch.on {
  box-shadow: 0 0 0 2px var(--v-text);
}
.p3-swatch.leaf {
  border-radius: 8px;
}
.p3-swatch input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.p3-attach {
  display: grid;
  gap: 6px;
}
.p3-attach-row {
  display: flex;
  gap: 8px;
}
.p3-attach-row .sp-select {
  flex: 1;
  min-width: 0;
}

@media (max-width: 900px) {
  .p3-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .p3-counts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
