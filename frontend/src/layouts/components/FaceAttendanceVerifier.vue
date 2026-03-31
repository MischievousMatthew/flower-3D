<template>
  <div
    class="face-verifier-overlay"
    v-if="isOpen"
    @click.self="handleOverlayClick"
  >
    <div class="face-verifier-modal">
      <div class="modal-header">
        <div class="header-left">
          <div class="employee-avatar" v-if="employee?.avatar_url">
            <img :src="employee.avatar_url" :alt="employee.full_name" />
          </div>
          <div class="header-info">
            <h2>{{ employee?.full_name || "Employee" }}</h2>
            <span class="badge" :class="attendanceType">
              {{ attendanceType === "time_in" ? "🟢 Time In" : "🔴 Time Out" }}
            </span>
          </div>
        </div>
        <button class="close-btn" @click="closeVerifier" :disabled="isSending">
          ✕
        </button>
      </div>

      <div class="step-progress">
        <div
          v-for="(step, index) in steps"
          :key="step.key"
          class="step"
          :class="{
            completed: stepIndex > index,
            active: stepIndex === index,
            upcoming: stepIndex < index,
          }"
        >
          <div class="step-circle">
            <span v-if="stepIndex > index">✓</span>
            <span v-else>{{ index + 1 }}</span>
          </div>
          <span class="step-label">{{ step.label }}</span>
        </div>
        <div class="step-line" :style="{ width: stepLineWidth }"></div>
      </div>

      <div
        v-if="phase === 'LOADING_MODELS'"
        class="phase-container loading-phase"
      >
        <div class="loading-animation">
          <div class="pulse-ring"></div>
          <div class="pulse-core">🧠</div>
        </div>
        <h3>Loading AI Models</h3>
        <p>Preparing face recognition engine...</p>
        <div class="progress-bar">
          <div
            class="progress-fill"
            :style="{ width: modelLoadProgress + '%' }"
          ></div>
        </div>
        <div class="model-checklist">
          <div
            v-for="model in modelStatus"
            :key="model.name"
            class="model-item"
            :class="model.status"
          >
            <span class="model-icon">
              {{
                model.status === "loaded"
                  ? "✅"
                  : model.status === "loading"
                    ? "⏳"
                    : "⬜"
              }}
            </span>
            {{ model.name }}
          </div>
        </div>
      </div>

      <div
        v-show="
          ['STARTING_WEBCAM', 'LIVENESS', 'CAPTURING', 'MATCHING'].includes(
            phase,
          )
        "
        class="phase-container webcam-phase"
      >
        <!-- Video + Canvas overlay -->
        <div class="video-wrapper">
          <video
            ref="videoRef"
            autoplay
            muted
            playsinline
            class="webcam-video"
          ></video>
          <canvas ref="overlayCanvasRef" class="overlay-canvas"></canvas>

          <!-- Scanning frame -->
          <div
            class="scan-frame"
            :class="{
              success: faceDetected,
              warning: !faceDetected && phase === 'LIVENESS',
            }"
          >
            <div class="corner tl"></div>
            <div class="corner tr"></div>
            <div class="corner bl"></div>
            <div class="corner br"></div>
          </div>

          <!-- Phase overlay for STARTING_WEBCAM -->
          <div v-if="phase === 'STARTING_WEBCAM'" class="video-overlay">
            <div class="spinner-ring"></div>
            <p>Starting camera...</p>
          </div>
        </div>

        <!-- Liveness instruction panel -->
        <div v-if="phase === 'LIVENESS'" class="liveness-panel">
          <div
            class="challenge-card"
            :class="{ 'challenge-done': livenessStep === 'PASSED' }"
          >
            <div class="challenge-icon">{{ currentChallengeIcon }}</div>
            <h3 class="challenge-title">{{ currentChallengeTitle }}</h3>
            <p class="challenge-description">
              {{ currentChallengeDescription }}
            </p>

            <!-- Blink progress indicators -->
            <div
              v-if="livenessStep === 'BLINK_CHALLENGE'"
              class="blink-indicators"
            >
              <div
                v-for="n in REQUIRED_BLINKS"
                :key="n"
                class="blink-dot"
                :class="{ filled: blinkCount >= n }"
              >
                {{ blinkCount >= n ? "👁️" : "○" }}
              </div>
            </div>

            <!-- Frame stability bar -->
            <div
              v-if="livenessStep === 'DETECT_FACE'"
              class="stability-bar-wrapper"
            >
              <p class="stability-label">Hold still...</p>
              <div class="stability-bar">
                <div
                  class="stability-fill"
                  :style="{
                    width:
                      (stableFrameCount / REQUIRED_STABLE_FRAMES) * 100 + '%',
                  }"
                ></div>
              </div>
            </div>
          </div>

          <!-- Status line -->
          <div class="status-line" :class="faceDetected ? 'ok' : 'warn'">
            <span>{{
              faceDetected
                ? "✅ Face detected"
                : "⚠️ No face detected — look at the camera"
            }}</span>
          </div>
        </div>

        <!-- CAPTURING: brief overlay -->
        <div v-if="phase === 'CAPTURING'" class="video-overlay capture-flash">
          <div class="flash-circle">📸</div>
          <p>Capturing...</p>
        </div>

        <!-- MATCHING: brief overlay -->
        <div v-if="phase === 'MATCHING'" class="video-overlay matching-overlay">
          <div class="matching-animation">
            <div class="face-compare">
              <div class="compare-face">
                <img
                  v-if="employee?.avatar_url"
                  :src="employee.avatar_url"
                  alt="stored"
                />
                <span v-else>👤</span>
                <label>Stored Photo</label>
              </div>
              <div class="compare-arrow">
                <div class="arrow-pulse">↔️</div>
                <span>Comparing...</span>
              </div>
              <div class="compare-face">
                <canvas
                  ref="capturePreviewRef"
                  class="capture-preview"
                ></canvas>
                <label>Live Capture</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="phase === 'SUCCESS'"
        class="phase-container result-phase success-phase"
      >
        <div class="result-icon success-icon">
          <div class="checkmark-circle">
            <svg viewBox="0 0 52 52">
              <circle class="checkmark-bg" cx="26" cy="26" r="25" fill="none" />
              <path
                class="checkmark-tick"
                fill="none"
                stroke-width="3"
                d="M14 27l7 7 17-17"
              />
            </svg>
          </div>
        </div>
        <h2 class="result-title">Identity Verified</h2>
        <div class="result-stats">
          <div class="stat-card">
            <span class="stat-value">{{ blinkCount }}x</span>
            <span class="stat-label">Blinks Detected</span>
          </div>
          <div class="stat-card">
            <span class="stat-value">✅</span>
            <span class="stat-label">Liveness Check</span>
          </div>
        </div>
        <p class="result-subtitle">
          {{ attendanceType === "time_in" ? "Time In" : "Time Out" }} recorded
          for
          <strong>{{ employee?.full_name }}</strong>
        </p>
        <div v-if="isSending" class="sending-indicator">
          <div class="spinner-ring small"></div>
          Submitting attendance...
        </div>
        <div v-if="attendanceResult" class="attendance-confirmation">
          <p>🕐 {{ attendanceResult.time }}</p>
          <p>📅 {{ attendanceResult.date }}</p>
        </div>
      </div>

      <div
        v-if="phase === 'FAILURE'"
        class="phase-container result-phase failure-phase"
      >
        <div class="result-icon failure-icon">
          <div class="x-circle">
            <svg viewBox="0 0 52 52">
              <circle class="x-bg" cx="26" cy="26" r="25" fill="none" />
              <path
                class="x-lines"
                fill="none"
                stroke-width="3"
                d="M16 16 36 36 M36 16 16 36"
              />
            </svg>
          </div>
        </div>
        <h2 class="result-title">Verification Failed</h2>
        <p class="failure-reason">{{ failureReason }}</p>
        <div class="retry-actions">
          <button class="btn-secondary" @click="closeVerifier">Cancel</button>
          <button class="btn-primary" @click="restart">Try Again</button>
        </div>
      </div>

      <div
        v-if="phase === 'PHOTO_ERROR'"
        class="phase-container result-phase failure-phase"
      >
        <div class="result-icon">⚠️</div>
        <h2 class="result-title">Employee Photo Issue</h2>
        <p class="failure-reason">{{ failureReason }}</p>
        <p class="failure-hint">
          Ask admin to update the employee photo and try again.
        </p>
        <button class="btn-secondary" @click="closeVerifier">Close</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted, nextTick } from "vue";
import * as faceapi from "@vladmandic/face-api";
import api from "../../plugins/axios";

// ── Props & Emits ─────────────────────────────────────────────────────────────
const props = defineProps({
  employee: { type: Object, required: true },
  attendanceType: { type: String, default: "time_in" },
  isOpen: { type: Boolean, default: false },
  modelsPath: { type: String, default: "/models" },
});

const emit = defineEmits(["update:isOpen", "success", "failure"]);

// ── Constants ─────────────────────────────────────────────────────────────────
const MATCH_DISTANCE_THRESHOLD = 0.45; // up to 0.45 euclidean distance is a valid match
const MATCH_DISPLAY_THRESHOLD = 75; // minimum % score required to pass

const REQUIRED_BLINKS = 2;
const REQUIRED_STABLE_FRAMES = 20;
const CALIBRATION_FRAMES_NEEDED = 15;
const DETECTION_INTERVAL_MS = 50;
const MAX_RETRY_COUNT = 3;

// Blink detection — rolling delta method
const BLINK_DROP_THRESHOLD = 0.018;
const ROLLING_WINDOW_SIZE = 8;
const MIN_CLOSED_FRAMES = 2;

// ── Reactive State ────────────────────────────────────────────────────────────
const videoRef = ref(null);
const overlayCanvasRef = ref(null);
const capturePreviewRef = ref(null);

const phase = ref("LOADING_MODELS");
const faceDetected = ref(false);
const blinkCount = ref(0);
const stableFrameCount = ref(0);
const livenessStep = ref("DETECT_FACE");
const matchScore = ref(null);
const failureReason = ref("");
const isSending = ref(false);
const attendanceResult = ref(null);
const retryCount = ref(0);

const modelStatus = ref([
  { name: "Face Detector", status: "pending" },
  { name: "Face Landmarks", status: "pending" },
  { name: "Face Recognition", status: "pending" },
]);

const modelLoadProgress = computed(() => {
  const loaded = modelStatus.value.filter((m) => m.status === "loaded").length;
  return Math.round((loaded / modelStatus.value.length) * 100);
});

// ── Non-reactive variables ────────────────────────────────────────────────────
let stream = null;
let detectionLoop = null;
let storedDescriptor = null;
let capturedDescriptor = null;
let modelsLoaded = false;

// Blink tracking
let waitingForReopen = false;
let closedFrameCount = 0;
let rollingEARWindow = [];

// Calibration
let baselineEAR = null;
let calibrationFrames = [];

// ── Step Progress ─────────────────────────────────────────────────────────────
const steps = [
  { key: "models", label: "Load AI" },
  { key: "webcam", label: "Camera" },
  { key: "liveness", label: "Liveness" },
  { key: "verify", label: "Verify" },
  { key: "done", label: "Done" },
];

const PHASE_TO_STEP = {
  LOADING_MODELS: 0,
  STARTING_WEBCAM: 1,
  LIVENESS: 2,
  CAPTURING: 3,
  MATCHING: 3,
  SUCCESS: 4,
  FAILURE: 4,
  PHOTO_ERROR: 4,
};

const stepIndex = computed(() => PHASE_TO_STEP[phase.value] ?? 0);
const stepLineWidth = computed(
  () => `${(stepIndex.value / (steps.length - 1)) * 100}%`,
);

// ── Liveness UI Computed ──────────────────────────────────────────────────────
const currentChallengeIcon = computed(() => {
  if (livenessStep.value === "DETECT_FACE") return "👁️";
  if (livenessStep.value === "BLINK_CHALLENGE") return "😉";
  if (livenessStep.value === "PASSED") return "✅";
  return "⏳";
});

const currentChallengeTitle = computed(() => {
  if (livenessStep.value === "DETECT_FACE") return "Look at the Camera";
  if (livenessStep.value === "BLINK_CHALLENGE")
    return `Blink ${REQUIRED_BLINKS} Times`;
  if (livenessStep.value === "PASSED") return "Liveness Confirmed!";
  return "Preparing...";
});

const currentChallengeDescription = computed(() => {
  if (livenessStep.value === "DETECT_FACE")
    return "Position your face within the frame and hold still.";
  if (livenessStep.value === "BLINK_CHALLENGE")
    return "Slowly blink your eyes naturally. Both eyes must close and reopen.";
  if (livenessStep.value === "PASSED")
    return "You're a real person! Capturing your photo now...";
  return "";
});

// ── Watcher ───────────────────────────────────────────────────────────────────
watch(
  () => props.isOpen,
  async (open) => {
    if (open) {
      resetState();
      await startVerification();
    } else {
      cleanup();
    }
  },
);

// ── Verification Flow ─────────────────────────────────────────────────────────
async function startVerification() {
  try {
    phase.value = "LOADING_MODELS";
    await loadModels();

    const photoPromise = extractStoredPhotoDescriptor();

    phase.value = "STARTING_WEBCAM";
    await startWebcam();

    storedDescriptor = await photoPromise;

    phase.value = "LIVENESS";
    livenessStep.value = "DETECT_FACE";
    startDetectionLoop();
  } catch (err) {
    console.error("[FaceVerifier]", err);
    if (err.code === "PHOTO_ERROR") {
      phase.value = "PHOTO_ERROR";
      failureReason.value = err.message;
    } else {
      setFailure("Camera or model error: " + (err.message || "Unknown error"));
    }
  }
}

// ── Model Loading ─────────────────────────────────────────────────────────────
async function loadModels() {
  if (modelsLoaded) return;

  const modelDefs = [
    {
      loader: () => faceapi.nets.tinyFaceDetector.loadFromUri(props.modelsPath),
      index: 0,
    },
    {
      loader: () =>
        faceapi.nets.faceLandmark68Net.loadFromUri(props.modelsPath),
      index: 1,
    },
    {
      loader: () =>
        faceapi.nets.faceRecognitionNet.loadFromUri(props.modelsPath),
      index: 2,
    },
  ];

  for (const def of modelDefs) {
    modelStatus.value[def.index].status = "loading";
    await def.loader();
    modelStatus.value[def.index].status = "loaded";
  }

  modelsLoaded = true;
}

// ── Stored Photo Descriptor ───────────────────────────────────────────────────
async function extractStoredPhotoDescriptor() {
  if (!props.employee?.avatar_url) {
    throw Object.assign(
      new Error("No employee photo stored. Upload a photo first."),
      { code: "PHOTO_ERROR" },
    );
  }

  const apiUrl = props.employee.avatar_url.replace(
    "/storage/",
    "/api/storage/",
  );
  const img = await loadImageFromUrl(apiUrl);

  // Try multiple input sizes — use whichever gives a detection
  const inputSizes = [416, 320, 608];
  let detection = null;

  for (const size of inputSizes) {
    detection = await faceapi
      .detectSingleFace(
        img,
        new faceapi.TinyFaceDetectorOptions({
          inputSize: size,
          scoreThreshold: 0.3,
        }),
      )
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (detection) {
      console.log(`[FaceVerifier] Stored photo detected at inputSize=${size}`);
      break;
    }
  }

  if (!detection) {
    throw Object.assign(
      new Error(
        "Could not detect a face in the stored employee photo. Please update the photo.",
      ),
      { code: "PHOTO_ERROR" },
    );
  }

  return detection.descriptor;
}

function loadImageFromUrl(url) {
  return new Promise(async (resolve, reject) => {
    try {
      const response = await fetch(url, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("token") || sessionStorage.getItem("token") || ""}`,
        },
        mode: "cors",
      });

      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const blob = await response.blob();
      const objectUrl = URL.createObjectURL(blob);
      const img = new Image();

      img.onload = () => {
        URL.revokeObjectURL(objectUrl);
        resolve(img);
      };
      img.onerror = () => reject(new Error(`Failed to render image: ${url}`));
      img.src = objectUrl;
    } catch (err) {
      reject(new Error(`Failed to load image: ${url}`));
    }
  });
}

// ── Webcam ────────────────────────────────────────────────────────────────────
async function startWebcam() {
  stream = await navigator.mediaDevices.getUserMedia({
    video: {
      width: { ideal: 640 },
      height: { ideal: 480 },
      facingMode: "user",
    },
    audio: false,
  });

  await nextTick();
  videoRef.value.srcObject = stream;

  await new Promise((resolve) => {
    videoRef.value.onloadedmetadata = () => resolve();
  });

  overlayCanvasRef.value.width = videoRef.value.videoWidth;
  overlayCanvasRef.value.height = videoRef.value.videoHeight;
}

function stopWebcam() {
  if (stream) {
    stream.getTracks().forEach((t) => t.stop());
    stream = null;
  }
}

// ── Detection Loop ────────────────────────────────────────────────────────────
function startDetectionLoop() {
  detectionLoop = setInterval(runDetectionFrame, DETECTION_INTERVAL_MS);
}

function stopDetectionLoop() {
  if (detectionLoop) {
    clearInterval(detectionLoop);
    detectionLoop = null;
  }
}

async function runDetectionFrame() {
  if (phase.value !== "LIVENESS") return;
  if (!videoRef.value || videoRef.value.readyState < 2) return;

  const detection = await faceapi
    .detectSingleFace(
      videoRef.value,
      new faceapi.TinyFaceDetectorOptions({
        inputSize: 224,
        scoreThreshold: 0.5,
      }),
    )
    .withFaceLandmarks();

  drawOverlay(detection);

  if (!detection) {
    faceDetected.value = false;
    if (livenessStep.value === "DETECT_FACE") {
      stableFrameCount.value = 0;
      calibrationFrames = [];
    }
    return;
  }

  faceDetected.value = true;
  const ear = computeEAR(detection.landmarks);

  // ── Phase 1: Stable face + calibrate baseline EAR ─────────────────────────
  if (livenessStep.value === "DETECT_FACE") {
    stableFrameCount.value++;

    if (calibrationFrames.length < CALIBRATION_FRAMES_NEEDED) {
      calibrationFrames.push(ear);
    }

    if (stableFrameCount.value >= REQUIRED_STABLE_FRAMES) {
      baselineEAR =
        calibrationFrames.reduce((a, b) => a + b, 0) / calibrationFrames.length;
      console.log("[Blink] Baseline EAR calibrated:", baselineEAR.toFixed(4));

      livenessStep.value = "BLINK_CHALLENGE";
      blinkCount.value = 0;
      waitingForReopen = false;
    }
    return;
  }

  // ── Phase 2: Detect blinks ─────────────────────────────────────────────────
  if (livenessStep.value === "BLINK_CHALLENGE") {
    detectBlink(ear);

    if (blinkCount.value >= REQUIRED_BLINKS) {
      livenessStep.value = "PASSED";
      stopDetectionLoop();
      await delay(800);
      await captureAndMatch();
    }
  }
}

// ── EAR Computation ───────────────────────────────────────────────────────────
function computeEAR(landmarks) {
  try {
    if (typeof landmarks.getLeftEye === "function") {
      return (
        (earForEye(landmarks.getLeftEye()) +
          earForEye(landmarks.getRightEye())) /
        2
      );
    }

    const positions =
      landmarks.positions ?? (Array.isArray(landmarks) ? landmarks : null);
    if (!positions) {
      console.warn("[EAR] Cannot read landmarks:", landmarks);
      return 0.3;
    }

    const leftEye = [36, 37, 38, 39, 40, 41].map((i) => positions[i]);
    const rightEye = [42, 43, 44, 45, 46, 47].map((i) => positions[i]);
    return (earForEye(leftEye) + earForEye(rightEye)) / 2;
  } catch (e) {
    console.error("[EAR] Error:", e);
    return 0.3;
  }
}

function earForEye(pts) {
  if (!pts || pts.length < 6) return 0.3;
  const v1 = eucDist(pts[1], pts[5]);
  const v2 = eucDist(pts[2], pts[4]);
  const h = eucDist(pts[0], pts[3]);
  return h === 0 ? 0 : (v1 + v2) / (2 * h);
}

function eucDist(a, b) {
  return Math.sqrt(Math.pow(a.x - b.x, 2) + Math.pow(a.y - b.y, 2));
}

// ── Blink Detection (rolling delta method) ────────────────────────────────────
function detectBlink(ear) {
  if (!waitingForReopen) {
    rollingEARWindow.push(ear);
    if (rollingEARWindow.length > ROLLING_WINDOW_SIZE) rollingEARWindow.shift();
  }

  if (rollingEARWindow.length < 4) return;

  const rollingAvg =
    rollingEARWindow.reduce((a, b) => a + b, 0) / rollingEARWindow.length;
  const drop = rollingAvg - ear;

  if (!waitingForReopen && drop > BLINK_DROP_THRESHOLD) {
    closedFrameCount++;
    if (closedFrameCount >= MIN_CLOSED_FRAMES) {
      waitingForReopen = true;
      console.log(
        `[Blink] Eye closing — drop: ${drop.toFixed(4)} avg: ${rollingAvg.toFixed(4)}`,
      );
    }
  } else if (waitingForReopen && drop < BLINK_DROP_THRESHOLD * 0.5) {
    blinkCount.value++;
    waitingForReopen = false;
    closedFrameCount = 0;
    rollingEARWindow = [];
    console.log(`[Blink] ✅ Blink #${blinkCount.value} detected`);
  } else if (!waitingForReopen) {
    closedFrameCount = 0;
  }
}

// ── Score Formula ─────────────────────────────────────────────────────────────
// face-api euclidean distance scale:
//   0.00 – 0.20 = near perfect  → 90–100%
//   0.20 – 0.35 = excellent     → 80–90%
//   0.35 – 0.45 = good match    → 75–80%
//   0.45 – 0.60 = borderline    → 50–75%
//   0.60+       = likely diff   → 0–50%
function distanceToScore(distance) {
  if (distance <= 0.2) return Math.round(90 + ((0.2 - distance) / 0.2) * 10);
  if (distance <= 0.35) return Math.round(80 + ((0.35 - distance) / 0.15) * 10);
  if (distance <= 0.45) return Math.round(75 + ((0.45 - distance) / 0.1) * 5);
  if (distance <= 0.6) return Math.round(50 + ((0.6 - distance) / 0.15) * 25);
  return Math.round(Math.max(0, 50 - (distance - 0.6) * 100));
}

// ── Descriptor Averaging ──────────────────────────────────────────────────────
function averageDescriptors(descriptors) {
  const length = descriptors[0].length;
  const avg = new Float32Array(length);
  for (let i = 0; i < length; i++) {
    avg[i] = descriptors.reduce((sum, d) => sum + d[i], 0) / descriptors.length;
  }
  return avg;
}

// ── Canvas Overlay ────────────────────────────────────────────────────────────
function drawOverlay(detection) {
  const canvas = overlayCanvasRef.value;
  if (!canvas) return;

  const ctx = canvas.getContext("2d");
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  if (!detection) return;

  const box = detection.detection.box;
  ctx.strokeStyle = faceDetected.value ? "#4ade80" : "#facc15";
  ctx.lineWidth = 2;
  ctx.strokeRect(box.x, box.y, box.width, box.height);

  if (detection.landmarks?.positions) {
    ctx.fillStyle = "rgba(99, 179, 237, 0.8)";
    for (const pt of detection.landmarks.positions) {
      ctx.beginPath();
      ctx.arc(pt.x, pt.y, 2, 0, Math.PI * 2);
      ctx.fill();
    }
  }
}

// ── Capture & Match ───────────────────────────────────────────────────────────
async function captureAndMatch() {
  phase.value = "CAPTURING";
  await delay(300);

  const CAPTURE_ATTEMPTS = 10;
  const results = [];

  for (let i = 0; i < CAPTURE_ATTEMPTS; i++) {
    await delay(60);
    if (!videoRef.value || videoRef.value.readyState < 2) continue;

    const det = await faceapi
      .detectSingleFace(
        videoRef.value,
        new faceapi.TinyFaceDetectorOptions({
          inputSize: 416,
          scoreThreshold: 0.5,
        }),
      )
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (det) {
      const dist = faceapi.euclideanDistance(storedDescriptor, det.descriptor);
      results.push({ descriptor: det.descriptor, distance: dist });
      console.log(
        `[FaceVerifier] Frame ${results.length}: distance=${dist.toFixed(4)}`,
      );
    }
  }

  if (results.length === 0) {
    if (retryCount.value < MAX_RETRY_COUNT) {
      retryCount.value++;
      resetLivenessState();
      phase.value = "LIVENESS";
      startDetectionLoop();
      return;
    }
    setFailure("Could not detect face at capture time. Please try again.");
    return;
  }

  // Sort by best match, average top 3
  results.sort((a, b) => a.distance - b.distance);
  const topN = results.slice(0, Math.min(3, results.length));
  capturedDescriptor = averageDescriptors(topN.map((r) => r.descriptor));

  console.log(
    `[FaceVerifier] Captured ${results.length}/${CAPTURE_ATTEMPTS} frames, using top ${topN.length}`,
  );

  // Draw preview
  nextTick(() => {
    if (capturePreviewRef.value && videoRef.value) {
      const ctx = capturePreviewRef.value.getContext("2d");
      capturePreviewRef.value.width = videoRef.value.videoWidth;
      capturePreviewRef.value.height = videoRef.value.videoHeight;
      ctx.drawImage(videoRef.value, 0, 0);
    }
  });

  phase.value = "MATCHING";
  await delay(200);

  const distance = faceapi.euclideanDistance(
    storedDescriptor,
    capturedDescriptor,
  );
  matchScore.value = distanceToScore(distance);

  console.log(
    `[FaceVerifier] Final distance=${distance.toFixed(4)} score=${matchScore.value}%`,
  );

  await delay(1500);

  if (matchScore.value >= MATCH_DISPLAY_THRESHOLD) {
    stopWebcam();
    phase.value = "SUCCESS";
    await sendAttendance();
  } else {
    stopWebcam();
    setFailure(
      `Face does not match the registered photo. ` +
        `Match score: ${matchScore.value}% (minimum ${MATCH_DISPLAY_THRESHOLD}% required).`,
    );
    emit("failure", { reason: "face_mismatch", score: matchScore.value });
  }
}

// ── Send Attendance ───────────────────────────────────────────────────────────
async function sendAttendance() {
  isSending.value = true;
  try {
    const endpoint =
      props.attendanceType === "time_in"
        ? "/attendance/time-in"
        : "/attendance/time-out";

    const { data } = await api.post(endpoint, {
      employee_id: props.employee.id,
      face_verified: true,
      match_score: matchScore.value,
      liveness_passed: true,
      blink_count: blinkCount.value,
    });

    if (data.success) {
      attendanceResult.value = {
        time:
          data.data?.formatted_time_in || data.data?.formatted_time_out || "",
        date: data.data?.formatted_date || "",
      };
      emit("success", {
        employee: props.employee,
        type: props.attendanceType,
        result: data.data,
      });
    }
  } catch (err) {
    console.error("[FaceVerifier] API error:", err);
    attendanceResult.value = {
      time: new Date().toLocaleTimeString(),
      date: new Date().toLocaleDateString(),
    };
    emit("success", {
      employee: props.employee,
      type: props.attendanceType,
      result: null,
    });
  } finally {
    isSending.value = false;
  }
}

// ── Failure ───────────────────────────────────────────────────────────────────
function setFailure(reason) {
  stopDetectionLoop();
  stopWebcam();
  failureReason.value = reason;
  phase.value = "FAILURE";
}

// ── Reset ─────────────────────────────────────────────────────────────────────
function resetLivenessState() {
  stableFrameCount.value = 0;
  blinkCount.value = 0;
  faceDetected.value = false;
  livenessStep.value = "DETECT_FACE";
  waitingForReopen = false;
  closedFrameCount = 0;
  rollingEARWindow = [];
  baselineEAR = null;
  calibrationFrames = [];

  const ctx = overlayCanvasRef.value?.getContext("2d");
  ctx?.clearRect(
    0,
    0,
    overlayCanvasRef.value.width,
    overlayCanvasRef.value.height,
  );
}

function resetState() {
  phase.value = "LOADING_MODELS";
  matchScore.value = null;
  failureReason.value = "";
  isSending.value = false;
  attendanceResult.value = null;
  retryCount.value = 0;
  storedDescriptor = null;
  capturedDescriptor = null;
  resetLivenessState();
}

async function restart() {
  cleanup();
  resetState();
  await nextTick();
  await startVerification();
}

// ── Modal / Cleanup ───────────────────────────────────────────────────────────
function cleanup() {
  stopDetectionLoop();
  stopWebcam();
}

function closeVerifier() {
  if (isSending.value) return;
  cleanup();
  emit("update:isOpen", false);
}

function handleOverlayClick() {
  if (["SUCCESS", "FAILURE", "PHOTO_ERROR"].includes(phase.value)) {
    closeVerifier();
  }
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

onUnmounted(() => cleanup());
</script>

<style scoped>
.face-verifier-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
  animation: fadeIn 0.25s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.face-verifier-modal {
  background: #ffffff;
  border-radius: 20px;
  width: min(680px, 95vw);
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
  display: flex;
  flex-direction: column;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #e5e7eb;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.employee-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid #3b82f6;
  flex-shrink: 0;
}

.employee-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.header-info h2 {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 4px;
}

.badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.badge.time_in {
  background: #dcfce7;
  color: #166534;
}
.badge.time_out {
  background: #fee2e2;
  color: #991b1b;
}

.close-btn {
  background: #f3f4f6;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  font-size: 16px;
  color: #6b7280;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.close-btn:hover:not(:disabled) {
  background: #e5e7eb;
  color: #111827;
}
.close-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.step-progress {
  position: relative;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 20px 32px 16px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.step-line {
  position: absolute;
  top: 34px;
  left: 42px;
  right: 42px;
  height: 2px;
  background: #3b82f6;
  transition: width 0.5s ease;
  transform-origin: left;
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  position: relative;
  z-index: 1;
}

.step-circle {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  transition: all 0.3s;
}

.step.completed .step-circle {
  background: #3b82f6;
  color: white;
}
.step.active .step-circle {
  background: #3b82f6;
  color: white;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
}
.step.upcoming .step-circle {
  background: #e5e7eb;
  color: #6b7280;
}

.step-label {
  font-size: 11px;
  font-weight: 500;
  color: #6b7280;
  white-space: nowrap;
}

.step.active .step-label {
  color: #3b82f6;
  font-weight: 700;
}
.step.completed .step-label {
  color: #374151;
}

.phase-container {
  padding: 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
  min-height: 340px;
  justify-content: center;
}

.loading-phase {
  text-align: center;
}
.loading-phase h3 {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.loading-phase p {
  color: #6b7280;
  margin: 0;
  font-size: 14px;
}

.loading-animation {
  position: relative;
  width: 80px;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pulse-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 3px solid #3b82f6;
  animation: pulseRing 1.5s ease-in-out infinite;
}

@keyframes pulseRing {
  0% {
    transform: scale(0.8);
    opacity: 1;
  }
  100% {
    transform: scale(1.3);
    opacity: 0;
  }
}

.pulse-core {
  font-size: 32px;
  z-index: 1;
}

.progress-bar {
  width: 100%;
  max-width: 320px;
  height: 6px;
  background: #e5e7eb;
  border-radius: 999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #3b82f6, #8b5cf6);
  border-radius: 999px;
  transition: width 0.4s ease;
}

.model-checklist {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 100%;
  max-width: 280px;
}

.model-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #374151;
  padding: 8px 12px;
  border-radius: 8px;
  background: #f3f4f6;
  transition: all 0.3s;
}

.model-item.loaded {
  background: #eff6ff;
  color: #1d4ed8;
}
.model-item.loading {
  background: #fefce8;
  color: #92400e;
  animation: blink 1s infinite;
}

@keyframes blink {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.6;
  }
}

.model-icon {
  font-size: 16px;
}

.webcam-phase {
  padding: 20px;
  gap: 16px;
  min-height: auto;
}

.video-wrapper {
  position: relative;
  width: 100%;
  max-width: 480px;
  border-radius: 14px;
  overflow: hidden;
  background: #111;
  aspect-ratio: 4/3;
}

.webcam-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform: scaleX(-1); /* mirror for selfie feel */
  display: block;
}

.overlay-canvas {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  transform: scaleX(-1); /* mirror matches video */
  pointer-events: none;
}

/* Animated scan frame */
.scan-frame {
  position: absolute;
  inset: 16px;
  pointer-events: none;
  transition: all 0.3s;
}

.corner {
  position: absolute;
  width: 20px;
  height: 20px;
  border-color: rgba(255, 255, 255, 0.8);
  border-style: solid;
}

.corner.tl {
  top: 0;
  left: 0;
  border-width: 3px 0 0 3px;
  border-radius: 4px 0 0 0;
}
.corner.tr {
  top: 0;
  right: 0;
  border-width: 3px 3px 0 0;
  border-radius: 0 4px 0 0;
}
.corner.bl {
  bottom: 0;
  left: 0;
  border-width: 0 0 3px 3px;
  border-radius: 0 0 0 4px;
}
.corner.br {
  bottom: 0;
  right: 0;
  border-width: 0 3px 3px 0;
  border-radius: 0 0 4px 0;
}

.scan-frame.success .corner {
  border-color: #4ade80;
}
.scan-frame.warning .corner {
  border-color: #facc15;
  animation: cornerPulse 1s ease-in-out infinite;
}

@keyframes cornerPulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.4;
  }
}

/* Video overlays */
.video-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: white;
  font-size: 14px;
  font-weight: 600;
}

.capture-flash {
  animation: flashEffect 0.3s ease;
}
@keyframes flashEffect {
  0% {
    background: rgba(255, 255, 255, 0.8);
  }
  100% {
    background: rgba(0, 0, 0, 0);
  }
}

/* Matching overlay */
.matching-overlay {
  background: rgba(0, 0, 0, 0.75);
}
.matching-animation {
  width: 100%;
  padding: 0 24px;
}
.face-compare {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 20px;
}

.compare-face {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.compare-face img,
.compare-face canvas.capture-preview {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #60a5fa;
  background: #1f2937;
}

.compare-face label {
  font-size: 11px;
  color: #d1d5db;
}

.compare-arrow {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: #9ca3af;
}

.arrow-pulse {
  font-size: 22px;
  animation: arrowPulse 0.8s ease-in-out infinite;
}

@keyframes arrowPulse {
  0%,
  100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.2);
  }
}

.liveness-panel {
  width: 100%;
  max-width: 480px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.challenge-card {
  background: #f0f9ff;
  border: 2px solid #bfdbfe;
  border-radius: 14px;
  padding: 20px;
  text-align: center;
  transition: all 0.3s;
}

.challenge-card.challenge-done {
  background: #f0fdf4;
  border-color: #86efac;
}

.challenge-icon {
  font-size: 36px;
  margin-bottom: 8px;
}
.challenge-title {
  font-size: 17px;
  font-weight: 700;
  color: #1e3a5f;
  margin: 0 0 6px;
}
.challenge-description {
  font-size: 13px;
  color: #4b5563;
  margin: 0;
}

.blink-indicators {
  display: flex;
  gap: 12px;
  justify-content: center;
  margin-top: 14px;
  font-size: 24px;
}

.blink-dot {
  transition: transform 0.3s;
}
.blink-dot.filled {
  transform: scale(1.3);
}

.stability-bar-wrapper {
  margin-top: 14px;
}
.stability-label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
}

.stability-bar {
  height: 6px;
  background: #dbeafe;
  border-radius: 999px;
  overflow: hidden;
}

.stability-fill {
  height: 100%;
  background: linear-gradient(90deg, #60a5fa, #34d399);
  border-radius: 999px;
  transition: width 0.2s ease;
}

.status-line {
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  text-align: center;
}

.status-line.ok {
  background: #f0fdf4;
  color: #166534;
}
.status-line.warn {
  background: #fef9c3;
  color: #713f12;
}

.result-phase {
  text-align: center;
}
.result-title {
  font-size: 24px;
  font-weight: 800;
  margin: 0;
}
.result-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}
.failure-reason {
  font-size: 14px;
  color: #dc2626;
  max-width: 340px;
  line-height: 1.5;
  margin: 0;
}
.failure-hint {
  font-size: 12px;
  color: #9ca3af;
  margin: 0;
}

.result-stats {
  display: flex;
  gap: 16px;
  justify-content: center;
  flex-wrap: wrap;
}

.stat-card {
  background: #f0f9ff;
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  padding: 12px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 80px;
}

.stat-value {
  font-size: 22px;
  font-weight: 800;
  color: #1d4ed8;
}
.stat-label {
  font-size: 11px;
  color: #6b7280;
  font-weight: 600;
}

.sending-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #6b7280;
}

.attendance-confirmation {
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 10px;
  padding: 12px 20px;
  font-size: 14px;
  color: #166534;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

/* Animated success checkmark */
.result-icon {
  display: flex;
  align-items: center;
  justify-content: center;
}

.checkmark-circle {
  width: 80px;
  height: 80px;
}
.checkmark-circle svg {
  width: 100%;
  height: 100%;
}
.checkmark-bg {
  stroke: #22c55e;
  stroke-width: 2;
  animation: circleDraw 0.4s ease forwards;
}
.checkmark-tick {
  stroke: #22c55e;
  stroke-dasharray: 30;
  stroke-dashoffset: 30;
  animation: tickDraw 0.4s 0.3s ease forwards;
}

@keyframes circleDraw {
  from {
    stroke-dasharray: 0 158;
  }
  to {
    stroke-dasharray: 158 0;
  }
}

@keyframes tickDraw {
  to {
    stroke-dashoffset: 0;
  }
}

/* Animated X */
.x-circle {
  width: 80px;
  height: 80px;
}
.x-circle svg {
  width: 100%;
  height: 100%;
}
.x-bg {
  stroke: #ef4444;
  stroke-width: 2;
  animation: circleDraw 0.4s ease forwards;
}
.x-lines {
  stroke: #ef4444;
  stroke-dasharray: 30;
  stroke-dashoffset: 30;
  animation: tickDraw 0.4s 0.3s ease forwards;
}

.failure-details {
  display: flex;
  gap: 16px;
  font-size: 13px;
  color: #374151;
  background: #f9fafb;
  padding: 10px 16px;
  border-radius: 8px;
}

.retry-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.btn-primary,
.btn-secondary {
  padding: 10px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}
.btn-primary:hover {
  background: #2563eb;
  transform: translateY(-1px);
}
.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}
.btn-secondary:hover {
  background: #e5e7eb;
}

.spinner-ring {
  width: 48px;
  height: 48px;
  border: 4px solid #e5e7eb;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.spinner-ring.small {
  width: 20px;
  height: 20px;
  border-width: 3px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 480px) {
  .face-verifier-modal {
    border-radius: 16px 16px 0 0;
    position: fixed;
    bottom: 0;
  }
  .step-label {
    display: none;
  }
  .result-stats {
    gap: 10px;
  }
  .stat-card {
    padding: 10px 14px;
    min-width: 70px;
  }
}
</style>
