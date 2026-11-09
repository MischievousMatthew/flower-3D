<template>
  <Teleport to="body">
    <Transition name="bm-fade">
      <div v-if="isOpen" class="bm-overlay" @click.self="close" @keydown.esc="close">
        <div ref="panel" class="bm-panel" role="dialog" aria-modal="true" aria-labelledby="bm-title" tabindex="-1">
          <!-- Header -->
          <header class="bm-head">
            <span class="bm-head-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 5v14M7.5 5v14M10 5v14M13.5 5v14M16 5v14M20 5v14" /></svg>
            </span>
            <div class="bm-head-text">
              <h2 id="bm-title">Delivery label</h2>
              <p>Stick it on the packaging. Scanning it moves the order to its next step.</p>
            </div>
            <button type="button" class="bm-icon-btn" aria-label="Close" @click="close">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
            </button>
          </header>

          <!-- Loading: a skeleton of the label -->
          <div v-if="loading" class="bm-ticket bm-ticket--sk" aria-busy="true" aria-label="Making the barcode">
            <div class="bc-skeleton bm-sk-line w40"></div>
            <div class="bc-skeleton bm-sk-bars"></div>
            <div class="bc-skeleton bm-sk-line w60"></div>
          </div>

          <!-- Couldn't make it -->
          <div v-else-if="error" class="bm-error" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16h.01" /></svg>
            <p>The barcode couldn't be made. Check your connection and try again.</p>
            <button type="button" class="bm-btn bm-btn--ghost" @click="loadBarcode">Try again</button>
          </div>

          <template v-else>
            <!-- The label itself (always white: it's what gets printed) -->
            <div class="bm-ticket">
              <div class="bm-ticket-top">
                <span class="bm-brand">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4DB173" d="M12 3c1.8 2.2 2.6 4.3 2.4 6.3 2-.9 4.1-.9 6.3 0-1 2.6-2.8 4.3-5.4 4.9 1.2 1.8 1.4 3.9.6 6.3-2.3-.6-3.9-1.9-4.6-4-.7 2.1-2.3 3.4-4.6 4-.8-2.4-.6-4.5.6-6.3-2.6-.6-4.4-2.3-5.4-4.9 2.2-.9 4.3-.9 6.3 0C10 7.3 10.2 5.2 12 3z" /></svg>
                  BloomCraft
                </span>
                <span class="bm-tag">Delivery</span>
              </div>

              <div ref="svgWrap" class="bm-bars" v-html="barcodeSvg"></div>

              <div class="bm-code-row">
                <span class="bm-code">{{ barcodeValue }}</span>
                <button type="button" class="bm-copy" :aria-label="copied ? 'Copied' : 'Copy the code'" @click="copyCode">
                  <svg v-if="!copied" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2" /><path d="M5 15V5a2 2 0 0 1 2-2h10" /></svg>
                  <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                  {{ copied ? "Copied" : "Copy" }}
                </button>
              </div>

              <div class="bm-perf" aria-hidden="true"></div>

              <div class="bm-ticket-foot">
                <span><small>Delivery</small><b>{{ label }}</b></span>
                <span class="bm-foot-right"><small>Scan with</small><b>Order Scan</b></span>
              </div>
            </div>

            <!-- Actions -->
            <div class="bm-actions">
              <button type="button" class="bm-btn bm-btn--primary" @click="printBarcode">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6" /><rect x="3" y="9" width="18" height="8" rx="2" /><path d="M7 14h10v7H7z" /></svg>
                Print label
              </button>
              <div class="bm-dl" role="group" aria-label="Download">
                <button type="button" class="bm-btn bm-btn--ghost" @click="downloadPng">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11M7 10l5 5 5-5M5 20h14" /></svg>
                  PNG
                </button>
                <button type="button" class="bm-btn bm-btn--ghost" @click="downloadSvg">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11M7 10l5 5 5-5M5 20h14" /></svg>
                  SVG
                </button>
              </div>
            </div>
            <p class="bm-tip">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></svg>
              Print at 100% scale so scanners read it. PNG works for most label printers.
            </p>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { nextTick, ref } from "vue";
import { deliveryService } from "../../services/deliveryService";

const isOpen = ref(false);
const loading = ref(false);
const error = ref(null);
const barcodeSvg = ref("");
const barcodeValue = ref("");
const label = ref("");
const svgWrap = ref(null);
const panel = ref(null);
const copied = ref(false);
let currentOrderId = null;
let lastFocus = null;

async function open(orderId, deliveryLabel = "") {
  if (!orderId) return;
  lastFocus = document.activeElement;
  currentOrderId = orderId;
  label.value = deliveryLabel || `Order #${orderId}`;
  isOpen.value = true;
  error.value = null;
  copied.value = false;
  barcodeSvg.value = "";
  barcodeValue.value = "";
  await nextTick();
  panel.value?.focus();
  await loadBarcode();
}

function close() {
  isOpen.value = false;
  lastFocus?.focus?.();
}

defineExpose({ open, close });

async function loadBarcode() {
  loading.value = true;
  error.value = null;
  try {
    const res = await deliveryService.getBarcode(currentOrderId);
    const data = res?.data ?? res;
    barcodeValue.value = data?.barcode ?? data?.barcode_value ?? "";
    barcodeSvg.value = data?.svg ?? data?.barcode_svg ?? "";
    if (!barcodeSvg.value) throw new Error("no barcode");
  } catch {
    error.value = true;
  } finally {
    loading.value = false;
  }
}

async function copyCode() {
  try {
    await navigator.clipboard.writeText(barcodeValue.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1600);
  } catch {
    // Clipboard blocked: the code is on screen to type
  }
}

const fileName = (ext) => `bloomcraft-label-${barcodeValue.value || label.value || "delivery"}.${ext}`;

function save(href, ext) {
  const a = Object.assign(document.createElement("a"), { href, download: fileName(ext) });
  document.body.appendChild(a);
  a.click();
  a.remove();
}

function downloadSvg() {
  const svgEl = svgWrap.value?.querySelector("svg");
  if (!svgEl) return;
  const url = URL.createObjectURL(new Blob([svgEl.outerHTML], { type: "image/svg+xml" }));
  save(url, "svg");
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/** The whole label as a PNG: barcode, code and delivery number, ready for a label printer */
async function downloadPng() {
  const svgEl = svgWrap.value?.querySelector("svg");
  if (!svgEl) return;
  const scale = 3;
  const bw = svgEl.viewBox.baseVal.width || svgEl.clientWidth;
  const bh = svgEl.viewBox.baseVal.height || svgEl.clientHeight;
  const pad = 24;
  const W = Math.max(bw, 260) + pad * 2;
  const H = bh + pad * 2 + 54;
  const canvas = document.createElement("canvas");
  canvas.width = W * scale;
  canvas.height = H * scale;
  const ctx = canvas.getContext("2d");
  ctx.scale(scale, scale);
  ctx.fillStyle = "#ffffff";
  ctx.fillRect(0, 0, W, H);

  const url = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(svgEl)], { type: "image/svg+xml" }));
  const img = new Image();
  await new Promise((res, rej) => {
    img.onload = res;
    img.onerror = rej;
    img.src = url;
  });
  ctx.drawImage(img, (W - bw) / 2, pad, bw, bh);
  URL.revokeObjectURL(url);

  ctx.fillStyle = "#1f2733";
  ctx.textAlign = "center";
  ctx.font = "700 16px ui-monospace, Menlo, Consolas, monospace";
  ctx.fillText(barcodeValue.value.split("").join(" "), W / 2, pad + bh + 24);
  ctx.fillStyle = "#6b7380";
  ctx.font = "500 11px Poppins, Arial, sans-serif";
  ctx.fillText(`BloomCraft · ${label.value}`, W / 2, pad + bh + 44);

  save(canvas.toDataURL("image/png"), "png");
}

function printBarcode() {
  const svgEl = svgWrap.value?.querySelector("svg");
  if (!svgEl) return;
  const esc = (t) => String(t).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]);
  const win = window.open("", "_blank", "width=520,height=420");
  if (!win) return;
  win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8">
    <title>Delivery label · ${esc(label.value)}</title>
    <style>
      @page{margin:10mm}
      body{margin:0;display:grid;place-items:center;min-height:100vh;font-family:Poppins,Arial,sans-serif;background:#fff}
      .label{width:320px;padding:16px 18px;border:1.5px solid #1f2733;border-radius:12px}
      .top{display:flex;justify-content:space-between;align-items:center;font-size:12px;font-weight:700;color:#1f2733}
      .tag{padding:2px 8px;border:1px solid #1f2733;border-radius:999px;font-size:10px;letter-spacing:.08em;text-transform:uppercase}
      svg{display:block;width:100%;height:auto;margin:12px 0 6px}
      svg text{display:none}
      .code{text-align:center;font:700 16px ui-monospace,Menlo,Consolas,monospace;letter-spacing:.18em;color:#1f2733}
      .foot{display:flex;justify-content:space-between;margin-top:10px;padding-top:8px;border-top:1px dashed #9aa3ad;font-size:10.5px;color:#4b5563}
    </style></head><body>
    <div class="label">
      <div class="top"><span>BloomCraft</span><span class="tag">Delivery</span></div>
      ${svgEl.outerHTML}
      <div class="code">${esc(barcodeValue.value)}</div>
      <div class="foot"><span>${esc(label.value)}</span><span>Scan with Order Scan</span></div>
    </div>
    <script>window.onload=()=>{window.print();}<\/script>
    </body></html>`);
  win.document.close();
}
</script>

<style scoped>
.bm-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(15, 20, 28, 0.55);
  backdrop-filter: blur(6px);
}
.bm-panel {
  width: 440px;
  max-width: 100%;
  max-height: calc(100vh - 32px);
  overflow-y: auto;
  padding: 20px;
  border: 1px solid var(--v-border, #e5e7eb);
  border-radius: 22px;
  background: var(--v-surface, #fff);
  box-shadow: 0 30px 80px rgba(0, 0, 0, 0.28);
  font-family: "Poppins", "Segoe UI", sans-serif;
}
.bm-panel:focus {
  outline: none;
}

/* Header */
.bm-head {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 16px;
}
.bm-head-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--bc-accent-soft, #e8f6ee);
  color: var(--bc-accent-deep, #2f8a52);
}
.bm-head-icon svg {
  width: 20px;
  height: 20px;
}
.bm-head-text {
  flex: 1;
  min-width: 0;
}
.bm-head-text h2 {
  margin: 0;
  font-size: 17px;
  font-weight: 600;
  color: var(--v-text, #1f2733);
}
.bm-head-text p {
  margin: 2px 0 0;
  font-size: 12.5px;
  line-height: 1.45;
  color: var(--v-muted, #6b7380);
}
.bm-icon-btn {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border: 1px solid var(--v-border, #e5e7eb);
  border-radius: 10px;
  background: transparent;
  color: var(--v-muted, #6b7380);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.bm-icon-btn:hover {
  background: var(--v-subtle, #f3f4f6);
  color: var(--v-text, #1f2733);
}
.bm-icon-btn svg {
  width: 16px;
  height: 16px;
}

/* The label: white in both themes, since it's what gets printed */
.bm-ticket {
  position: relative;
  padding: 16px 18px 14px;
  border-radius: 16px;
  background: #ffffff;
  color: #1f2733;
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.04), 0 8px 24px rgba(16, 24, 40, 0.1);
  border: 1px solid #e6e9ee;
}
.bm-ticket-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.bm-brand {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  color: #363f4e;
}
.bm-brand svg {
  width: 18px;
  height: 18px;
}
.bm-tag {
  padding: 3px 10px;
  border-radius: 999px;
  background: #eef8f2;
  color: #2f8a52;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.bm-bars {
  display: flex;
  justify-content: center;
  margin: 14px 0 8px;
}
.bm-bars :deep(svg) {
  display: block;
  width: 100%;
  max-width: 360px;
  height: auto;
}
.bm-bars :deep(svg text) {
  display: none;
}
.bm-code-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.bm-code {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 17px;
  font-weight: 700;
  letter-spacing: 0.16em;
  color: #1f2733;
}
.bm-copy {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-height: 30px;
  padding: 0 10px;
  border: 1px solid #e2e6eb;
  border-radius: 999px;
  background: #f7f8fa;
  color: #4b5563;
  font: inherit;
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
}
.bm-copy:hover {
  border-color: #4db173;
  color: #2f8a52;
}
.bm-copy svg {
  width: 13px;
  height: 13px;
}
/* Perforation with a notch each side, like a tear-off ticket */
.bm-perf {
  position: relative;
  margin: 14px -18px 10px;
  border-top: 1.5px dashed #d5dae1;
}
.bm-perf::before,
.bm-perf::after {
  content: "";
  position: absolute;
  top: -9px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--v-surface, #fff);
  border: 1px solid #e6e9ee;
}
.bm-perf::before {
  left: -9px;
  clip-path: inset(0 0 0 50%);
}
.bm-perf::after {
  right: -9px;
  clip-path: inset(0 50% 0 0);
}
.bm-ticket-foot {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
.bm-ticket-foot span {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.bm-ticket-foot small {
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #8a93a0;
}
.bm-ticket-foot b {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12.5px;
  color: #1f2733;
}
.bm-foot-right {
  align-items: flex-end;
  text-align: right;
}
.bm-foot-right b {
  font-family: inherit;
}

/* Skeleton while it's made */
.bm-ticket--sk {
  display: grid;
  gap: 12px;
}
.bm-sk-line {
  height: 12px;
  border-radius: 6px;
}
.bm-sk-bars {
  height: 92px;
  border-radius: 10px;
}
.w40 {
  width: 40%;
}
.w60 {
  width: 60%;
  justify-self: center;
}

/* Error */
.bm-error {
  display: grid;
  justify-items: center;
  gap: 10px;
  padding: 24px 12px;
  text-align: center;
  color: var(--v-muted, #6b7380);
}
.bm-error svg {
  width: 28px;
  height: 28px;
  color: var(--v-red-text, #c53030);
}
.bm-error p {
  margin: 0;
  font-size: 13px;
}

/* Actions */
.bm-actions {
  display: flex;
  gap: 8px;
  margin-top: 16px;
}
.bm-dl {
  display: flex;
  gap: 8px;
}
.bm-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 44px;
  padding: 0 16px;
  border-radius: 12px;
  font: inherit;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, transform 0.1s;
}
.bm-btn svg {
  width: 16px;
  height: 16px;
}
.bm-btn:active {
  transform: scale(0.98);
}
.bm-btn--primary {
  flex: 1;
  border: 0;
  background: var(--bc-accent, #4db173);
  color: var(--bc-accent-ink, #fff);
}
.bm-btn--primary:hover {
  background: var(--bc-accent-deep, #2f8a52);
}
.bm-btn--ghost {
  border: 1px solid var(--v-border-strong, #d5dae1);
  background: var(--v-surface, #fff);
  color: var(--v-text, #1f2733);
}
.bm-btn--ghost:hover {
  border-color: var(--bc-accent, #4db173);
  color: var(--bc-accent-deep, #2f8a52);
}
.bm-btn:focus-visible,
.bm-copy:focus-visible,
.bm-icon-btn:focus-visible {
  outline: 2px solid var(--bc-accent, #4db173);
  outline-offset: 2px;
}
.bm-tip {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  margin: 12px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--v-muted, #6b7380);
}
.bm-tip svg {
  flex: none;
  width: 14px;
  height: 14px;
  margin-top: 2px;
}

/* Open and close */
.bm-fade-enter-active,
.bm-fade-leave-active {
  transition: opacity 0.2s ease;
}
.bm-fade-enter-active .bm-panel,
.bm-fade-leave-active .bm-panel {
  transition: transform 0.22s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.bm-fade-enter-from,
.bm-fade-leave-to {
  opacity: 0;
}
.bm-fade-enter-from .bm-panel,
.bm-fade-leave-to .bm-panel {
  transform: translateY(8px) scale(0.98);
}
@media (prefers-reduced-motion: reduce) {
  .bm-fade-enter-active,
  .bm-fade-leave-active,
  .bm-fade-enter-active .bm-panel,
  .bm-fade-leave-active .bm-panel,
  .bm-btn {
    transition: none;
  }
}
@media (max-width: 420px) {
  .bm-actions {
    flex-direction: column;
  }
  .bm-dl .bm-btn {
    flex: 1;
  }
}
</style>
