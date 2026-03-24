<template>
  <teleport to="body">
    <transition name="bm-fade">
      <div v-if="isOpen" class="bm-overlay" @click.self="close">
        <div class="bm-panel">
          <!-- Header -->
          <div class="bm-header">
            <h2 class="bm-title">Order Barcode</h2>
            <button class="bm-close" @click="close">✕</button>
          </div>

          <!-- Body -->
          <div class="bm-body">
            <!-- Loading -->
            <div v-if="loading" class="bm-loading">
              <div class="bm-spinner"></div>
              <span>Generating barcode…</span>
            </div>

            <!-- Error -->
            <div v-else-if="error" class="bm-error">
              <svg
                viewBox="0 0 20 20"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                width="22"
              >
                <circle cx="10" cy="10" r="8" />
                <path d="M10 6v4M10 13h.01" />
              </svg>
              <p>{{ error }}</p>
              <button class="bm-retry" @click="loadBarcode">Retry</button>
            </div>

            <!-- Barcode display -->
            <template v-else>
              <!-- Delivery ID label -->
              <div class="bm-delivery-label">{{ label }}</div>

              <!-- Barcode card -->
              <div class="bm-card">
                <!-- SVG barcode -->
                <div class="bm-svg-wrap" ref="svgWrap">
                  <div v-html="barcodeSvg" class="bm-svg-inner"></div>
                </div>

                <!-- Code large bold -->
                <div class="bm-code-large">{{ barcodeValue }}</div>

                <!-- Hint -->
                <div class="bm-hint">
                  Scan with any standard barcode scanner
                </div>
              </div>

              <!-- Action buttons -->
              <div class="bm-actions">
                <button class="bm-btn bm-btn-svg" @click="downloadSvg">
                  <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    width="14"
                  >
                    <path d="M10 3v10M6 9l4 4 4-4M3 17h14" />
                  </svg>
                  Download SVG
                </button>
                <button class="bm-btn bm-btn-png" @click="downloadPng">
                  <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    width="14"
                  >
                    <path d="M10 3v10M6 9l4 4 4-4M3 17h14" />
                  </svg>
                  Download PNG
                </button>
                <button class="bm-btn bm-btn-print" @click="printBarcode">
                  <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="14"
                  >
                    <rect x="4" y="7" width="12" height="8" rx="1" />
                    <path d="M7 7V4h6v3M7 13h6" />
                  </svg>
                  Print
                </button>
              </div>

              <!-- Tip -->
              <p class="bm-tip">
                💡 Print at <strong>100% scale</strong> and cut to size for
                flower packaging.
              </p>
            </template>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script setup>
import { ref } from "vue";
import { deliveryService } from "../../services/deliveryService";

const isOpen = ref(false);
const loading = ref(false);
const error = ref(null);
const barcodeSvg = ref("");
const barcodeValue = ref("");
const label = ref("");
const svgWrap = ref(null);
let currentOrderId = null;

async function open(orderId, deliveryLabel = "") {
  if (!orderId) return;
  currentOrderId = orderId;
  label.value = deliveryLabel || `Order #${orderId}`;
  isOpen.value = true;
  error.value = null;
  barcodeSvg.value = "";
  barcodeValue.value = "";
  await loadBarcode();
}

function close() {
  isOpen.value = false;
}

defineExpose({ open, close });

async function loadBarcode() {
  loading.value = true;
  error.value = null;
  try {
    const res = await deliveryService.getBarcode(currentOrderId);
    const data = res?.data ?? res;
    barcodeValue.value = data?.barcode ?? data?.barcode_value ?? "";
    barcodeSvg.value =
      data?.svg ?? data?.barcode_svg ?? generateFallbackSvg(barcodeValue.value);
  } catch (e) {
    error.value = e?.message ?? "Failed to load barcode";
  } finally {
    loading.value = false;
  }
}

function generateFallbackSvg(code) {
  if (!code) return "";
  const w = 300,
    h = 80;
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">
    <rect width="100%" height="100%" fill="#fee2e2"/>
    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#ef4444" font-size="12">Barcode Pending...</text>
  </svg>`;
}

function downloadSvg() {
  const svgEl = svgWrap.value?.querySelector("svg");
  if (!svgEl) return;
  const blob = new Blob([svgEl.outerHTML], { type: "image/svg+xml" });
  const url = URL.createObjectURL(blob);
  const a = Object.assign(document.createElement("a"), {
    href: url,
    download: `barcode-${barcodeValue.value || "delivery"}.svg`,
  });
  a.click();
  URL.revokeObjectURL(url);
}

async function downloadPng() {
  const svgEl = svgWrap.value?.querySelector("svg");
  if (!svgEl) return;
  const scale = 3;
  const w = svgEl.viewBox.baseVal.width || svgEl.clientWidth;
  const h = svgEl.viewBox.baseVal.height || svgEl.clientHeight;
  const canvas = document.createElement("canvas");
  canvas.width = w * scale;
  canvas.height = h * scale;
  const ctx = canvas.getContext("2d");
  const blob = new Blob([new XMLSerializer().serializeToString(svgEl)], {
    type: "image/svg+xml",
  });
  const url = URL.createObjectURL(blob);
  const img = new Image();
  await new Promise((res, rej) => {
    img.onload = res;
    img.onerror = rej;
    img.src = url;
  });
  ctx.fillStyle = "#ffffff";
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
  URL.revokeObjectURL(url);
  const a = Object.assign(document.createElement("a"), {
    download: `barcode-${barcodeValue.value || "delivery"}.png`,
    href: canvas.toDataURL("image/png"),
  });
  a.click();
}

function printBarcode() {
  const svgEl = svgWrap.value?.querySelector("svg");
  if (!svgEl) return;
  const win = window.open("", "_blank", "width=460,height=360");
  win.document.write(`<!DOCTYPE html><html><head>
    <title>Barcode — ${label.value}</title>
    <style>
      body{margin:0;display:flex;flex-direction:column;align-items:center;
           justify-content:center;height:100vh;font-family:monospace;gap:10px;background:#fff}
      svg{max-width:340px}
      .code{font-size:16px;font-weight:700;letter-spacing:.12em;color:#111827}
      .lbl{font-size:11px;color:#9ca3af;letter-spacing:.05em}
    </style></head><body>
    ${svgEl.outerHTML}
    <p class="code">${barcodeValue.value}</p>
    <p class="lbl">${label.value}</p>
    <script>window.onload=()=>window.print()<\/script>
    </body></html>`);
  win.document.close();
}
</script>

<style scoped>
.bm-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(0, 0, 0, 0.42);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
}

.bm-panel {
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 28px 80px rgba(0, 0, 0, 0.18);
  width: 460px;
  max-width: 94vw;
  overflow: hidden;
}

/* ── Header ── */
.bm-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 16px;
}
.bm-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.bm-close {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  cursor: pointer;
  color: #9ca3af;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
}
.bm-close:hover {
  background: #f3f4f6;
  color: #374151;
}

/* ── Body ── */
.bm-body {
  padding: 0 24px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* Delivery ID label */
.bm-delivery-label {
  font-size: 12px;
  font-weight: 600;
  color: #9ca3af;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

/* ── Barcode card ── */
.bm-card {
  background: #f8fafc;
  border: 1.5px solid #e8ecf0;
  border-radius: 14px;
  padding: 20px 20px 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.bm-svg-wrap {
  width: 100%;
  display: flex;
  justify-content: center;
}
.bm-svg-inner :deep(svg) {
  max-width: 100%;
  height: auto;
  display: block;
}

.bm-code-small {
  font-family: monospace;
  font-size: 11px;
  color: #9ca3af;
  letter-spacing: 0.06em;
}
.bm-code-large {
  font-family: monospace;
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  letter-spacing: 0.12em;
}
.bm-hint {
  font-size: 12px;
  color: #9ca3af;
  margin-top: 2px;
}

.bm-svg-inner :deep(svg text) {
  display: none;
}

/* ── Loading / Error ── */
.bm-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 32px;
  color: #9ca3af;
  font-size: 13px;
}
.bm-spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #f3f4f6;
  border-top-color: #22c55e;
  border-radius: 50%;
  animation: bm-spin 0.7s linear infinite;
}
@keyframes bm-spin {
  to {
    transform: rotate(360deg);
  }
}

.bm-error {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 24px;
  color: #6b7280;
  text-align: center;
}
.bm-error p {
  font-size: 13px;
  margin: 0;
}
.bm-retry {
  padding: 7px 18px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  font-size: 13px;
  cursor: pointer;
  color: #374151;
  transition: all 0.15s;
}
.bm-retry:hover {
  background: #eff6ff;
  border-color: #93c5fd;
  color: #2563eb;
}

/* ── Action buttons ── */
.bm-actions {
  display: flex;
  gap: 10px;
  width: 100%;
}
.bm-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 11px 0;
  border-radius: 10px;
  border: none;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition:
    filter 0.15s,
    transform 0.1s;
}
.bm-btn:active {
  transform: scale(0.97);
}

/* Green — Download SVG */
.bm-btn-svg {
  background: #22c55e;
  color: #fff;
}
.bm-btn-svg:hover {
  filter: brightness(1.08);
}

/* Blue — Download PNG */
.bm-btn-png {
  background: #3b82f6;
  color: #fff;
}
.bm-btn-png:hover {
  filter: brightness(1.08);
}

/* Gray — Print */
.bm-btn-print {
  background: #f3f4f6;
  color: #374151;
  border: 1.5px solid #e5e7eb;
  flex: 0 0 auto;
  padding: 11px 20px;
}
.bm-btn-print:hover {
  background: #e5e7eb;
}

/* ── Tip ── */
.bm-tip {
  font-size: 12.5px;
  color: #6b7280;
  text-align: center;
  margin: 0;
  line-height: 1.5;
}
.bm-tip strong {
  color: #111827;
}

/* ── Transition ── */
.bm-fade-enter-active,
.bm-fade-leave-active {
  transition:
    opacity 0.2s,
    transform 0.2s;
}
.bm-fade-enter-from,
.bm-fade-leave-to {
  opacity: 0;
  transform: scale(0.96);
}
</style>
