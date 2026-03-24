<template>
  <div class="scanner-wrap">
    <!-- Manual / hardware input -->
    <div
      class="scanner-field"
      :class="{
        active: focused,
        'state-ok': state === 'ok',
        'state-err': state === 'err',
      }"
    >
      <svg
        viewBox="0 0 24 24"
        width="16"
        fill="currentColor"
        style="color: #475569; flex-shrink: 0"
      >
        <path
          fill-rule="evenodd"
          d="M2 6h1v12H2zm2 0h2v12H4zm4 0h1v12H8zm2 0h3v12h-3zm4 0h1v12h-1zm3 0h1v12h-1zm2 0h1v12h-1zm2 0h1v12h-1z"
        />
      </svg>
      <input
        ref="inputRef"
        v-model="buffer"
        :placeholder="placeholder"
        @keydown.enter.prevent="submitManual"
        @focus="focused = true"
        @blur="focused = false"
        autocomplete="off"
        spellcheck="false"
      />
      <span v-if="scanning" class="spin"></span>
      <span v-else-if="state === 'ok'" class="state-icon ok">✓</span>
      <span v-else-if="state === 'err'" class="state-icon err">✕</span>
    </div>

    <!-- Big camera open button -->
    <button
      class="open-camera-btn"
      :class="{ 'is-open': cameraOpen }"
      @click="toggleCamera"
      :disabled="camLoading"
    >
      <span class="ocb-icon">
        <svg
          v-if="camLoading"
          viewBox="0 0 24 24"
          width="20"
          height="20"
          fill="none"
        >
          <circle
            cx="12"
            cy="12"
            r="9"
            stroke="#10b981"
            stroke-width="2"
            stroke-dasharray="40"
            stroke-dashoffset="15"
            style="
              animation: spin 0.8s linear infinite;
              transform-origin: center;
            "
          />
        </svg>
        <svg
          v-else-if="!cameraOpen"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
          width="20"
          height="20"
        >
          <path
            d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"
          />
          <circle cx="12" cy="13" r="4" />
        </svg>
        <svg
          v-else
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          width="20"
          height="20"
        >
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </span>
      <span class="ocb-label">
        {{
          camLoading
            ? "Starting camera…"
            : cameraOpen
              ? "Close Camera"
              : "Open Camera Scanner"
        }}
      </span>
      <span class="ocb-badge" v-if="!cameraOpen && !camLoading"
        >TAP TO SCAN</span
      >
    </button>

    <!-- Camera viewfinder — Quagga2 draws INTO this div -->
    <transition name="cam-slide">
      <div v-if="cameraOpen" class="camera-container">
        <div class="camera-header">
          <span class="cam-label">
            <span class="cam-dot"></span>
            Live — Code128 scanner active
          </span>
          <select
            v-if="cameras.length > 1"
            v-model="selectedCamera"
            @change="switchCamera"
            class="cam-select"
          >
            <option v-for="c in cameras" :key="c.deviceId" :value="c.deviceId">
              {{ c.label || "Camera " + (cameras.indexOf(c) + 1) }}
            </option>
          </select>
        </div>

        <!-- Quagga2 renders <video> + <canvas> inside this div -->
        <div class="viewfinder-wrap">
          <div ref="quaggaRef" class="quagga-viewport"></div>

          <!-- Overlay UI -->
          <div class="scan-line" v-if="!camError"></div>
          <div class="corner tl"></div>
          <div class="corner tr"></div>
          <div class="corner bl"></div>
          <div class="corner br"></div>

          <transition name="flash">
            <div v-if="flashVisible" class="scan-flash"></div>
          </transition>

          <div v-if="camError" class="cam-error-overlay">
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="36"
            >
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <p>{{ camError }}</p>
            <button @click="retryCamera" class="retry-btn">Try Again</button>
          </div>
        </div>

        <p class="cam-hint">
          Hold the barcode steady — scanning automatically every frame
        </p>
      </div>
    </transition>

    <!-- Status message -->
    <transition name="msg">
      <div v-if="message" class="scan-msg" :class="state">{{ message }}</div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from "vue";
import Quagga from "@ericblade/quagga2";

const props = defineProps({
  placeholder: { type: String, default: "Scan barcode or type manually…" },
  autoFocus: { type: Boolean, default: true },
});
const emit = defineEmits(["scan"]);

// ── State ─────────────────────────────────────────────────────────────────────
const inputRef = ref(null);
const quaggaRef = ref(null);
const buffer = ref("");
const focused = ref(false);
const scanning = ref(false);
const state = ref("");
const message = ref("");
const cameraOpen = ref(false);
const camLoading = ref(false);
const cameras = ref([]);
const selectedCamera = ref("");
const camError = ref("");
const flashVisible = ref(false);

let quaggaRunning = false;
let msgTimer = null;
let resetTimer = null;

// ── Manual / hardware input ───────────────────────────────────────────────────
function submitManual() {
  const val = buffer.value.trim().toUpperCase();

  console.log("[Scanner] Manual submit triggered");
  console.log("[Scanner] Buffer value:", buffer.value);
  console.log("[Scanner] Trimmed value:", val);
  console.log("[Scanner] Type:", typeof val);

  if (!val || scanning.value) {
    console.warn("[Scanner] Ignored manual scan - empty or scanning");
    return;
  }

  buffer.value = "";

  console.log("[Scanner] Emitting scan event with:", val);

  emit("scan", val);
}

// ── Camera toggle ─────────────────────────────────────────────────────────────
async function toggleCamera() {
  if (cameraOpen.value) {
    await stopQuagga();
    cameraOpen.value = false;
  } else {
    cameraOpen.value = true;
    await nextTick();
    await startQuagga();
  }
}

async function retryCamera() {
  camError.value = "";
  await stopQuagga();
  await nextTick();
  await startQuagga();
}

// ── Quagga2 start ─────────────────────────────────────────────────────────────
async function startQuagga() {
  camLoading.value = true;
  camError.value = "";

  // Enumerate cameras to find back camera
  try {
    const devices = await navigator.mediaDevices.enumerateDevices();
    const videoDevices = devices.filter((d) => d.kind === "videoinput");
    cameras.value = videoDevices;
    const backCam = videoDevices.find((d) =>
      /back|rear|environment/i.test(d.label),
    );
    if (!selectedCamera.value && backCam) {
      selectedCamera.value = backCam.deviceId;
    }
  } catch {
    /* ignore — will use default */
  }

  const constraints = selectedCamera.value
    ? { deviceId: { exact: selectedCamera.value } }
    : { facingMode: "environment" };

  Quagga.init(
    {
      inputStream: {
        name: "Live",
        type: "LiveStream",
        target: quaggaRef.value, // renders into our div
        constraints: {
          ...constraints,
          width: { min: 640, ideal: 1280 },
          height: { min: 480, ideal: 720 },
        },
        area: {
          // Only scan the middle horizontal strip — faster + more accurate
          top: "20%",
          right: "5%",
          left: "5%",
          bottom: "20%",
        },
      },
      locator: {
        patchSize: "medium", // 'x-small' | 'small' | 'medium' | 'large' | 'x-large'
        halfSample: true,
      },
      numOfWorkers: navigator.hardwareConcurrency
        ? Math.min(navigator.hardwareConcurrency, 4)
        : 2,
      frequency: 10, // decode attempts per second
      decoder: {
        readers: ["code_128_reader"],
        multiple: false,
      },
      locate: true,
    },
    (err) => {
      camLoading.value = false;
      if (err) {
        console.error("[Quagga] init error:", err);
        if (
          String(err).includes("Permission") ||
          String(err).includes("allowed")
        ) {
          camError.value =
            "Camera permission denied. Please allow camera access.";
        } else if (String(err).includes("device")) {
          camError.value = "No camera found on this device.";
        } else {
          camError.value =
            "Could not start camera. " + (err?.message ?? String(err));
        }
        return;
      }
      Quagga.start();
      quaggaRunning = true;
    },
  );

  // Listen for successful decodes
  Quagga.onDetected(handleDetected);
}

async function stopQuagga() {
  if (quaggaRunning) {
    Quagga.offDetected(handleDetected);
    try {
      Quagga.stop();
    } catch {}
    quaggaRunning = false;
  }
  camLoading.value = false;
}

async function switchCamera() {
  await stopQuagga();
  await nextTick();
  await startQuagga();
}

// ── Barcode detected ──────────────────────────────────────────────────────────
let lastCode = "";
let lastCodeTime = 0;

function handleDetected(result) {
  const code = result?.codeResult?.code;
  if (!code) return;

  const cleanCode = String(code).trim().toUpperCase();

  // Debounce — same code within 2s is ignored
  const now = Date.now();
  if (cleanCode === lastCode && now - lastCodeTime < 2000) return;
  lastCode = cleanCode;
  lastCodeTime = now;

  console.log("[Scanner] Detected:", cleanCode);

  // Green flash
  flashVisible.value = true;
  setTimeout(() => {
    flashVisible.value = false;
  }, 300);

  emit("scan", cleanCode);
}

// ── Exposed to parent ─────────────────────────────────────────────────────────
function showResult(type, msg) {
  scanning.value = false;
  state.value = type;
  message.value = msg;
  clearTimeout(msgTimer);
  clearTimeout(resetTimer);
  msgTimer = setTimeout(() => {
    message.value = "";
  }, 4000);
  resetTimer = setTimeout(() => {
    state.value = "";
  }, 2500);
  inputRef.value?.focus();
}

function setScanning(val) {
  scanning.value = val;
}

function handlePageClick() {
  if (!cameraOpen.value) inputRef.value?.focus();
}

onMounted(() => {
  if (props.autoFocus) {
    inputRef.value?.focus();
    document.addEventListener("click", handlePageClick);
  }
});

onUnmounted(() => {
  stopQuagga();
  document.removeEventListener("click", handlePageClick);
  clearTimeout(msgTimer);
  clearTimeout(resetTimer);
});

defineExpose({ showResult, setScanning });
</script>

<style scoped>
.scanner-wrap {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
}

/* ── Text input ── */
.scanner-field {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 14px;
  background: #0c1118;
  border: 2px solid #1e2a35;
  border-radius: 11px;
  transition:
    border-color 0.15s,
    box-shadow 0.15s;
}
.scanner-field.active {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.scanner-field.state-ok {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}
.scanner-field.state-err {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
}

.scanner-field input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  font-family: "Poppins", monospace;
  color: #e2e8f0;
  letter-spacing: 0.03em;
}
.scanner-field input::placeholder {
  color: #334155;
}

.spin {
  width: 15px;
  height: 15px;
  flex-shrink: 0;
  border: 2px solid #1e2a35;
  border-top-color: #f59e0b;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.state-icon {
  font-size: 15px;
  font-weight: 700;
  flex-shrink: 0;
}
.state-icon.ok {
  color: #10b981;
}
.state-icon.err {
  color: #ef4444;
}

/* ── Camera open button ── */
.open-camera-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 13px 18px;
  background: linear-gradient(135deg, #0f2a1e 0%, #0c1f2e 100%);
  border: 1.5px dashed #1e4a35;
  border-radius: 11px;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}
.open-camera-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.open-camera-btn:not(:disabled):hover {
  border-color: #10b981;
  box-shadow: 0 0 16px rgba(16, 185, 129, 0.15);
}
.open-camera-btn.is-open {
  border-style: solid;
  border-color: #ef4444;
  background: linear-gradient(135deg, #1f0c0c 0%, #1a0c18 100%);
}

.ocb-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: rgba(16, 185, 129, 0.12);
  border: 1px solid rgba(16, 185, 129, 0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #10b981;
  flex-shrink: 0;
  transition: all 0.2s;
}
.open-camera-btn.is-open .ocb-icon {
  background: rgba(239, 68, 68, 0.12);
  border-color: rgba(239, 68, 68, 0.25);
  color: #ef4444;
}

.ocb-label {
  flex: 1;
  font-size: 13.5px;
  font-weight: 600;
  color: #94a3b8;
}
.open-camera-btn.is-open .ocb-label {
  color: #f87171;
}

.ocb-badge {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  padding: 3px 8px;
  border-radius: 6px;
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.2);
}

/* ── Camera container ── */
.camera-container {
  border: 1px solid #1e2a35;
  border-radius: 12px;
  overflow: hidden;
  background: #000;
  display: flex;
  flex-direction: column;
}
.camera-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-bottom: 1px solid #1e2a35;
  background: #0c1118;
}
.cam-label {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 11.5px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.cam-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #ef4444;
  box-shadow: 0 0 6px #ef4444;
  animation: live-pulse 1.5s infinite;
}
@keyframes live-pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.3;
  }
}

.cam-select {
  background: #0f1923;
  border: 1px solid #1e2a35;
  color: #94a3b8;
  font-size: 12px;
  padding: 4px 8px;
  border-radius: 6px;
  outline: none;
  cursor: pointer;
}

/* Viewfinder wrapper */
.viewfinder-wrap {
  position: relative;
  width: 100%;
  aspect-ratio: 16/9;
  overflow: hidden;
  background: #000;
}

/* Quagga renders <video> + <canvas> inside .quagga-viewport */
.quagga-viewport {
  width: 100%;
  height: 100%;
}

/* Make Quagga's internal video + canvas fill the container */
.quagga-viewport :deep(video),
.quagga-viewport :deep(canvas) {
  width: 100% !important;
  height: 100% !important;
  object-fit: cover;
  background: #000;
  position: absolute;
  top: 0;
  left: 0;
}

/* Hide Quagga's debug canvas in production (shows red detection boxes) */
.quagga-viewport :deep(canvas.drawingBuffer) {
  display: none;
}

/* Scan sweep line */
.scan-line {
  position: absolute;
  left: 5%;
  right: 5%;
  height: 2px;
  background: linear-gradient(
    90deg,
    transparent,
    #10b981 30%,
    #10b981 70%,
    transparent
  );
  box-shadow:
    0 0 10px #10b981,
    0 0 20px rgba(16, 185, 129, 0.4);
  animation: scan-sweep 2s ease-in-out infinite;
  z-index: 4;
  pointer-events: none;
}
@keyframes scan-sweep {
  0% {
    top: 20%;
    opacity: 0;
  }
  8% {
    opacity: 1;
  }
  92% {
    opacity: 1;
  }
  100% {
    top: 80%;
    opacity: 0;
  }
}

/* Corners */
.corner {
  position: absolute;
  width: 28px;
  height: 28px;
  border-color: #10b981;
  border-style: solid;
  z-index: 5;
  pointer-events: none;
}
.corner.tl {
  top: 8%;
  left: 5%;
  border-width: 3px 0 0 3px;
  border-radius: 3px 0 0 0;
}
.corner.tr {
  top: 8%;
  right: 5%;
  border-width: 3px 3px 0 0;
  border-radius: 0 3px 0 0;
}
.corner.bl {
  bottom: 8%;
  left: 5%;
  border-width: 0 0 3px 3px;
  border-radius: 0 0 0 3px;
}
.corner.br {
  bottom: 8%;
  right: 5%;
  border-width: 0 3px 3px 0;
  border-radius: 0 0 3px 0;
}

/* Green flash on detection */
.scan-flash {
  position: absolute;
  inset: 0;
  background: rgba(16, 185, 129, 0.3);
  z-index: 6;
  pointer-events: none;
}
.flash-enter-active,
.flash-leave-active {
  transition: opacity 0.25s;
}
.flash-enter-from,
.flash-leave-to {
  opacity: 0;
}

/* Error overlay */
.cam-error-overlay {
  position: absolute;
  inset: 0;
  background: rgba(10, 18, 28, 0.93);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  z-index: 10;
  padding: 24px;
  text-align: center;
}
.cam-error-overlay svg {
  color: #475569;
}
.cam-error-overlay p {
  font-size: 13px;
  color: #64748b;
  line-height: 1.6;
  margin: 0;
}
.retry-btn {
  padding: 8px 22px;
  border-radius: 8px;
  border: 1px solid #334155;
  background: #0f1923;
  color: #94a3b8;
  font-size: 12.5px;
  font-family: "Poppins", sans-serif;
  cursor: pointer;
  transition: all 0.15s;
}
.retry-btn:hover {
  border-color: #3b82f6;
  color: #3b82f6;
}

.cam-hint {
  padding: 8px 14px;
  margin: 0;
  font-size: 11.5px;
  color: #1e3a2a;
  text-align: center;
  background: #0c1118;
}

/* ── Status message ── */
.scan-msg {
  font-size: 12.5px;
  font-weight: 500;
  padding: 8px 14px;
  border-radius: 8px;
}
.scan-msg.ok {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.2);
}
.scan-msg.err {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

/* ── Transitions ── */
.cam-slide-enter-active,
.cam-slide-leave-active {
  transition: all 0.3s ease;
}
.cam-slide-enter-from,
.cam-slide-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.msg-enter-active,
.msg-leave-active {
  transition:
    opacity 0.2s,
    transform 0.2s;
}
.msg-enter-from,
.msg-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
