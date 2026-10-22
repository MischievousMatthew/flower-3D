<template>
  <section class="sp-card lq">
    <div class="lq-preview">
      <div class="lq-sheet">
        <div class="lq-brand">
          <img v-if="info?.logo && logoOk" :src="info.logo" alt="" crossorigin="anonymous" />
          <span v-else class="lq-initials">{{ initialsOf(info?.name ?? "") }}</span>
          <b>{{ info?.name ?? "" }}</b>
        </div>
        <h3>Leave &amp; overtime</h3>
        <p>Scan to ask for leave or overtime</p>
        <div class="lq-code">
          <canvas v-show="ready" ref="canvas" aria-label="Leave QR code"></canvas>
          <div v-if="!ready" class="bc-skeleton lq-code-sk"></div>
        </div>
        <ol>
          <li>Scan this code with your phone camera</li>
          <li>Scan your ID badge</li>
          <li>Send your request — HR reviews it</li>
        </ol>
      </div>
    </div>

    <div class="lq-side">
      <h2>The shop's leave QR code</h2>
      <p>Post it where staff can see it, like the break room. It opens your shop's leave page; each person then scans their own ID badge, so nobody can ask for leave as someone else.</p>
      <p v-if="error" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ error }}</p>
      <p v-else-if="ready && !logoUsed" class="sp-hint">{{ info?.logo ? "Your logo couldn't be loaded into the code, so your initials are used." : "Add a shop logo in Shop Profile to show it in the middle." }}</p>
      <div class="lq-actions">
        <button type="button" class="sp-btn sp-btn-primary" :disabled="!ready" @click="download"><span v-html="ICONS.download"></span>Download QR (PNG)</button>
        <button type="button" class="sp-btn sp-btn-ghost" :disabled="!ready" @click="print"><span v-html="ICONS.print"></span>Print poster</button>
        <button type="button" class="sp-btn sp-btn-ghost" :disabled="!url" @click="copy"><span v-html="ICONS.clip"></span>{{ copied ? "Copied" : "Copy link" }}</button>
      </div>
      <small class="sp-hint lq-url">{{ url }}</small>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from "vue";
import QRCode from "qrcode";
import { SHOP_ICONS as ICONS } from "../../vendor/shop/shopIcons";
import { initialsOf } from "../hrUi";

const props = defineProps({
  // () => { code, name, logo }
  load: { type: Function, required: true },
});

const SIZE = 1024;
const canvas = ref(null);
const info = ref(null);
const url = ref("");
const ready = ref(false);
const error = ref("");
const logoOk = ref(true);
const logoUsed = ref(false);
const copied = ref(false);

function loadImage(src) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = "anonymous"; // so the canvas can still be downloaded
    img.onload = () => resolve(img);
    img.onerror = reject;
    img.src = src;
  });
}

function roundRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

/** A QR code with high error correction, and the logo on a white tile in the middle (about 22% wide). */
async function draw() {
  const c = canvas.value;
  await QRCode.toCanvas(c, url.value, { errorCorrectionLevel: "H", width: SIZE, margin: 2, color: { dark: "#111827", light: "#ffffff" } });
  const ctx = c.getContext("2d");
  const tile = SIZE * 0.24;
  const x = (SIZE - tile) / 2;
  ctx.fillStyle = "#ffffff";
  roundRect(ctx, x, x, tile, tile, tile * 0.22);
  ctx.fill();

  const inner = tile * 0.8;
  const ix = (SIZE - inner) / 2;
  logoUsed.value = false;
  if (info.value.logo) {
    try {
      const img = await loadImage(info.value.logo);
      ctx.save();
      roundRect(ctx, ix, ix, inner, inner, inner * 0.2);
      ctx.clip();
      // Cover the square, whatever the logo's shape
      const scale = Math.max(inner / img.width, inner / img.height);
      ctx.drawImage(img, SIZE / 2 - (img.width * scale) / 2, SIZE / 2 - (img.height * scale) / 2, img.width * scale, img.height * scale);
      ctx.restore();
      c.toDataURL(); // throws if the logo made the canvas unreadable
      logoUsed.value = true;
    } catch (_) {
      logoOk.value = false;
      await QRCode.toCanvas(c, url.value, { errorCorrectionLevel: "H", width: SIZE, margin: 2, color: { dark: "#111827", light: "#ffffff" } });
      ctx.fillStyle = "#ffffff";
      roundRect(ctx, x, x, tile, tile, tile * 0.22);
      ctx.fill();
    }
  }
  if (!logoUsed.value) {
    const accent = getComputedStyle(document.documentElement).getPropertyValue("--bc-accent").trim() || "#48bb78";
    ctx.fillStyle = accent;
    roundRect(ctx, ix, ix, inner, inner, inner * 0.2);
    ctx.fill();
    ctx.fillStyle = "#ffffff";
    ctx.font = `700 ${inner * 0.42}px Poppins, sans-serif`;
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(initialsOf(info.value.name) || "BC", SIZE / 2, SIZE / 2 + inner * 0.03);
  }
  // The QR library pins a pixel size on the element; let CSS size it (square) instead
  c.style.width = "";
  c.style.height = "";
  ready.value = true;
}

function download() {
  const a = document.createElement("a");
  a.href = canvas.value.toDataURL("image/png");
  a.download = `leave-qr-${(info.value.name || "shop").toLowerCase().replace(/[^a-z0-9]+/g, "-")}.png`;
  a.click();
}

function print() {
  const image = canvas.value.toDataURL("image/png");
  const name = (info.value.name || "").replace(/[<>&"]/g, "");
  const win = window.open("", "_blank", "width=820,height=1000");
  if (!win) return;
  win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Leave QR — ${name}</title>
<style>@page{size:A4;margin:18mm}body{margin:0;font-family:Poppins,Arial,sans-serif;color:#111827;text-align:center}
h1{font-size:30px;margin:8px 0 2px}h2{font-size:20px;font-weight:500;color:#4b5563;margin:0 0 22px}
img{width:118mm;height:118mm}ol{display:inline-block;text-align:left;font-size:16px;line-height:1.9;margin-top:18px}
.shop{font-size:18px;font-weight:600;letter-spacing:.02em;color:#374151}</style></head>
<body><div class="shop">${name}</div><h1>Leave &amp; overtime</h1><h2>Scan to ask for leave or overtime</h2>
<img src="${image}" alt="Leave QR code"><ol><li>Scan this code with your phone camera</li><li>Scan your ID badge</li><li>Send your request — HR reviews it</li></ol>
<script>window.onload=()=>{window.print()}<\/script></body></html>`);
  win.document.close();
}

async function copy() {
  try {
    await navigator.clipboard.writeText(url.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
  } catch (_) {
    copied.value = false;
  }
}

onMounted(async () => {
  try {
    info.value = (await props.load()).data;
    url.value = `${window.location.origin}/leaves?s=${encodeURIComponent(info.value.code)}`;
    await draw();
  } catch (e) {
    error.value = e?.response?.data?.message || "Couldn't make the QR code.";
  }
});
</script>

<style scoped>
.lq {
  display: grid;
  grid-template-columns: minmax(0, 360px) minmax(0, 1fr);
  gap: 24px;
  align-items: center;
}
.lq-preview {
  padding: 18px;
  border-radius: 18px;
  background: var(--v-subtle);
}
.lq-sheet {
  padding: 22px 18px;
  border-radius: 16px;
  background: #ffffff;
  color: #111827;
  text-align: center;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
}
.lq-brand {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.lq-brand img,
.lq-initials {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  object-fit: cover;
}
.lq-initials {
  display: grid;
  place-items: center;
  background: var(--bc-accent);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
}
.lq-brand b {
  font-size: 13px;
}
.lq-sheet h3 {
  margin: 10px 0 0;
  font-size: 19px;
  font-weight: 700;
}
.lq-sheet p {
  margin: 2px 0 12px;
  font-size: 12.5px;
  color: #4b5563;
}
.lq-code canvas {
  display: block;
  width: 100%;
  max-width: 250px;
  height: auto;
  aspect-ratio: 1;
  margin: 0 auto;
}
.lq-code-sk {
  width: 250px;
  max-width: 100%;
  aspect-ratio: 1;
  margin: 0 auto;
  border-radius: 12px;
}
.lq-sheet ol {
  display: inline-block;
  margin: 10px 0 0;
  padding-left: 18px;
  font-size: 11.5px;
  line-height: 1.7;
  text-align: left;
  color: #374151;
}
.lq-side h2 {
  margin: 0 0 6px;
  font-size: 17px;
  font-weight: 600;
  color: var(--v-text);
}
.lq-side p {
  margin: 0 0 12px;
  font-size: 13.5px;
  color: var(--v-muted);
}
.lq-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 6px 0 10px;
}
.lq-url {
  display: block;
  overflow-wrap: anywhere;
}
@media (max-width: 860px) {
  .lq {
    grid-template-columns: 1fr;
  }
}
</style>
