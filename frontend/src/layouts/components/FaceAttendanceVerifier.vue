<template>
  <div
    class="face-verifier-overlay"
    v-if="isOpen"
    @click.self="handleOverlayClick"
  >
    <div class="face-verifier-modal">
      <!-- Header -->
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
        <div class="attempt-counter" v-if="attempts > 0">
          <span
            class="attempt-dot"
            v-for="n in 3"
            :key="n"
            :class="{ used: n <= attempts }"
          ></span>
        </div>
        <button class="close-btn" @click="closeVerifier" :disabled="isSending">
          ✕
        </button>
      </div>

      <!-- Step Progress -->
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

      <!-- PHASE: Loading Models -->
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

      <!-- PHASE: Webcam / Positioning / Liveness / Capturing / Matching -->
      <div
        v-show="
          [
            'STARTING_WEBCAM',
            'POSITIONING',
            'LIVENESS',
            'CAPTURING',
            'MATCHING',
          ].includes(phase)
        "
        class="phase-container webcam-phase"
      >
        <!-- Video + canvas -->
        <div class="video-wrapper">
          <video
            ref="videoRef"
            autoplay
            muted
            playsinline
            class="webcam-video"
          ></video>
          <canvas ref="overlayCanvasRef" class="overlay-canvas"></canvas>

          <!-- Depth check flash overlay -->
          <div
            class="env-flash"
            :class="{ active: flashActive }"
            :style="{ background: flashColor }"
          ></div>

          <!-- Scan frame corners -->
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

          <!-- Starting webcam overlay -->
          <div v-if="phase === 'STARTING_WEBCAM'" class="video-overlay">
            <div class="spinner-ring"></div>
            <p>Starting camera...</p>
          </div>

          <!-- Positioning overlay -->
          <div v-if="phase === 'POSITIONING'" class="positioning-overlay">
            <div class="guide-circle" :class="positionGuideClass">
              <div class="guide-ring-inner"></div>
            </div>
            <div class="position-label">{{ positionLabel }}</div>
            <div class="stability-bar-wrapper">
              <div class="stability-bar">
                <div
                  class="stability-fill"
                  :style="{
                    width:
                      (positionFrames / REQUIRED_POSITION_FRAMES) * 100 + '%',
                  }"
                ></div>
              </div>
            </div>
          </div>

          <!-- Capturing overlay -->
          <div v-if="phase === 'CAPTURING'" class="video-overlay capture-flash">
            <div class="flash-circle">📸</div>
            <p>Capturing...</p>
          </div>

          <!-- Matching overlay -->
          <div
            v-if="phase === 'MATCHING'"
            class="video-overlay matching-overlay"
          >
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

        <!-- LIVENESS Challenge Panel -->
        <div v-if="phase === 'LIVENESS'" class="liveness-panel">
          <!-- Challenge timer ring + instruction -->
          <div
            class="challenge-card"
            :class="{ 'challenge-done': currentChallengeComplete }"
          >
            <div class="challenge-header">
              <div class="challenge-timer">
                <svg class="timer-ring" viewBox="0 0 44 44">
                  <circle
                    cx="22"
                    cy="22"
                    r="18"
                    fill="none"
                    stroke="#e2e8f0"
                    stroke-width="3"
                  />
                  <circle
                    cx="22"
                    cy="22"
                    r="18"
                    fill="none"
                    stroke="#48bb78"
                    stroke-width="3"
                    stroke-dasharray="113"
                    :stroke-dashoffset="timerDashOffset"
                    stroke-linecap="round"
                    transform="rotate(-90 22 22)"
                  />
                </svg>
                <span class="timer-text">{{ challengeTimeLeft }}</span>
              </div>
              <div class="challenge-icon-title">
                <span class="challenge-big-icon">{{
                  currentChallenge?.icon
                }}</span>
                <div>
                  <div class="challenge-title">
                    {{ currentChallenge?.label }}
                  </div>
                  <div class="challenge-subtitle">
                    {{ currentChallenge?.hint }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Chain progress dots -->
            <div class="chain-progress">
              <div
                v-for="(ch, i) in activeChallenges"
                :key="i"
                class="chain-dot"
                :class="{
                  done: i < challengeIndex,
                  active: i === challengeIndex,
                  pending: i > challengeIndex,
                }"
              >
                <span>{{ i < challengeIndex ? "✓" : ch.icon }}</span>
              </div>
              <div
                class="chain-connector"
                v-for="i in activeChallenges.length - 1"
                :key="'c' + i"
                :class="{ filled: i <= challengeIndex }"
              ></div>
            </div>

            <!-- Blink indicators -->
            <div
              v-if="currentChallenge?.id === 'blink'"
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
          </div>

          <!-- Face status -->
          <div class="status-line" :class="faceDetected ? 'ok' : 'warn'">
            <span>{{
              faceDetected
                ? "✅ Face detected"
                : "⚠️ No face detected — look at the camera"
            }}</span>
          </div>
        </div>

        <!-- Positioning instruction -->
        <div v-if="phase === 'POSITIONING'" class="instruction-bar">
          <span class="inst-icon">📷</span>
          <span>Center your face in the frame and hold still</span>
        </div>
      </div>

      <!-- PHASE: Success -->
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
            <span class="stat-value">{{ activeChallenges.length }}</span>
            <span class="stat-label">Challenges Passed</span>
          </div>
          <div class="stat-card">
            <span class="stat-value">{{ matchScore }}%</span>
            <span class="stat-label">Match Score</span>
          </div>
          <div class="stat-card">
            <span class="stat-value">✅</span>
            <span class="stat-label">Liveness</span>
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

      <!-- PHASE: Failure -->
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
        <div class="retry-actions" v-if="attempts < 3">
          <button class="btn-secondary" @click="closeVerifier">Cancel</button>
          <button class="btn-primary" @click="restart">
            Try Again ({{ 3 - attempts }} left)
          </button>
        </div>
        <div v-else>
          <p class="no-attempts">
            No attempts remaining. Contact your administrator.
          </p>
          <button class="btn-secondary" @click="closeVerifier">Close</button>
        </div>
      </div>

      <!-- PHASE: Photo Error -->
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

      <!-- PHASE: Suspicious Activity -->
      <div
        v-if="phase === 'SUSPICIOUS'"
        class="phase-container result-phase failure-phase"
      >
        <div class="result-icon">🚫</div>
        <h2 class="result-title">Access Denied</h2>
        <p class="failure-reason">{{ failureReason }}</p>
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
const MATCH_DISTANCE_THRESHOLD = 0.55;
const MATCH_DISPLAY_THRESHOLD = 70;
const REQUIRED_BLINKS = 2;
const REQUIRED_POSITION_FRAMES = 25; // frames before challenge starts
const REQUIRED_STABLE_FRAMES = 20;
const DETECTION_INTERVAL_MS = 80;
const MAX_ATTEMPTS = 3;
const NUM_CHALLENGES = 3; // chain length (was 2)
const CHALLENGE_DURATION = 5; // seconds per challenge
const DEPTH_SCALE_THRESHOLD = 0.08; // 8% size change for depth check

// Blink detection
const BLINK_DROP_THRESHOLD = 0.018;
const ROLLING_WINDOW_SIZE = 8;
const MIN_CLOSED_FRAMES = 2;

// ── Challenge Pool ────────────────────────────────────────────────────────────
const CHALLENGE_POOL = [
  {
    id: "blink",
    label: "Blink your eyes",
    hint: "Slowly blink both eyes naturally",
    icon: "👁️",
    detect: "blink",
  },
  {
    id: "smile",
    label: "Smile naturally",
    hint: "Give a genuine smile",
    icon: "😊",
    detect: "smile",
  },
  {
    id: "turn_left",
    label: "Turn head LEFT",
    hint: "Rotate your head slightly to the left",
    icon: "⬅️",
    detect: "turn_left",
  },
  {
    id: "turn_right",
    label: "Turn head RIGHT",
    hint: "Rotate your head slightly to the right",
    icon: "➡️",
    detect: "turn_right",
  },
  {
    id: "nod",
    label: "Nod your head",
    hint: "Move your head up and down once",
    icon: "⬆️⬇️",
    detect: "nod",
  },
  {
    id: "move_closer",
    label: "Move CLOSER",
    hint: "Lean in toward the camera",
    icon: "🔍",
    detect: "move_closer",
  },
  {
    id: "move_back",
    label: "Move BACK",
    hint: "Lean away from the camera",
    icon: "🔎",
    detect: "move_back",
  },
];

// ── Reactive State ────────────────────────────────────────────────────────────
const videoRef = ref(null);
const overlayCanvasRef = ref(null);
const capturePreviewRef = ref(null);

const phase = ref("LOADING_MODELS");
const faceDetected = ref(false);
const blinkCount = ref(0);
const matchScore = ref(0);
const failureReason = ref("");
const isSending = ref(false);
const attendanceResult = ref(null);
const attempts = ref(0);
const currentChallengeComplete = ref(false);

// Positioning
const positionFrames = ref(0);
const positionGuideClass = ref("neutral");
const positionLabel = ref("Looking for face...");

// Liveness challenges
const activeChallenges = ref([]);
const challengeIndex = ref(0);
const challengeTimeLeft = ref(CHALLENGE_DURATION);
const timerDashOffset = ref(0);

// Environmental flash (anti-replay)
const flashActive = ref(false);
const flashColor = ref("rgba(255,0,0,0.15)");

// Model loading
const modelStatus = ref([
  { name: "Face Detector", status: "pending" },
  { name: "Face Landmarks", status: "pending" },
  { name: "Face Recognition", status: "pending" },
  { name: "Face Expressions", status: "pending" },
]);

const modelLoadProgress = computed(() => {
  const loaded = modelStatus.value.filter((m) => m.status === "loaded").length;
  return Math.round((loaded / modelStatus.value.length) * 100);
});

// ── Non-reactive internals ────────────────────────────────────────────────────
let stream = null;
let detectionLoop = null;
let challengeTimerLoop = null;
let storedDescriptor = null;
let capturedDescriptor = null;
let modelsLoaded = false;

// Blink
let waitingForReopen = false;
let closedFrameCount = 0;
let rollingEARWindow = [];

// Calibration
let baselineEAR = null;
let calibrationFrames = [];
let stableFrameCount = 0;

// Depth tracking
let baselineFaceWidth = null;
let lastNoseY = null;
let nodFrames = 0;

// Challenge state machine
let challengePassed = false;
let challengeStartTime = 0;

// Screen replay / texture
let lastTextureVar = null;

// ── Step Progress ─────────────────────────────────────────────────────────────
const steps = [
  { key: "models", label: "Load AI" },
  { key: "position", label: "Position" },
  { key: "liveness", label: "Liveness" },
  { key: "verify", label: "Verify" },
  { key: "done", label: "Done" },
];

const PHASE_TO_STEP = {
  LOADING_MODELS: 0,
  STARTING_WEBCAM: 1,
  POSITIONING: 1,
  LIVENESS: 2,
  CAPTURING: 3,
  MATCHING: 3,
  SUCCESS: 4,
  FAILURE: 4,
  PHOTO_ERROR: 4,
  SUSPICIOUS: 4,
};

const stepIndex = computed(() => PHASE_TO_STEP[phase.value] ?? 0);
const stepLineWidth = computed(
  () => `${(stepIndex.value / (steps.length - 1)) * 100}%`,
);

const currentChallenge = computed(
  () => activeChallenges.value[challengeIndex.value] ?? null,
);

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

// ── Main Flow ─────────────────────────────────────────────────────────────────
async function startVerification() {
  try {
    // 1. Virtual camera check
    await detectVirtualCamera();

    // 2. Load models
    phase.value = "LOADING_MODELS";
    await loadModels();

    // 3. Extract stored descriptor (parallel with webcam start)
    const photoPromise = extractStoredPhotoDescriptor();

    // 4. Start webcam
    phase.value = "STARTING_WEBCAM";
    await startWebcam();
    storedDescriptor = await photoPromise;

    // 5. Positioning gate
    phase.value = "POSITIONING";
    await runPositioningGate();

    // 6. Environmental flash (anti-replay)
    triggerEnvFlash();

    // 7. Pick challenges
    pickChallenges();

    // 8. Run liveness challenges
    phase.value = "LIVENESS";
    await runChallengeChain();

    // 9. Capture & match
    await captureAndMatch();
  } catch (err) {
    console.error("[FaceVerifier]", err);
    if (err.code === "PHOTO_ERROR") {
      phase.value = "PHOTO_ERROR";
      failureReason.value = err.message;
    } else if (err.code === "SUSPICIOUS") {
      phase.value = "SUSPICIOUS";
      failureReason.value = err.message;
    } else {
      setFailure(err.message || "An unexpected error occurred.");
    }
  }
}

// ── Virtual Camera Detection ──────────────────────────────────────────────────
async function detectVirtualCamera() {
  try {
    const devices = await navigator.mediaDevices.enumerateDevices();
    const videoInputs = devices.filter((d) => d.kind === "videoinput");
    const suspicious = [
      "obs",
      "manycam",
      "xsplit",
      "snap camera",
      "virtual",
      "fake",
      "droid",
      "iriun",
      "epoccam",
    ];

    for (const device of videoInputs) {
      const label = (device.label || "").toLowerCase();
      if (suspicious.some((s) => label.includes(s))) {
        throw Object.assign(
          new Error(
            `Virtual camera detected (${device.label}). Use your real webcam.`,
          ),
          { code: "SUSPICIOUS" },
        );
      }
    }
  } catch (err) {
    if (err.code === "SUSPICIOUS") throw err;
    // If enumeration fails (no permission yet), continue — real cameras don't need pre-permission labels
  }
}

// ── Model Loading ─────────────────────────────────────────────────────────────
async function loadModels() {
  if (modelsLoaded) return;

  const defs = [
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
    {
      loader: () =>
        faceapi.nets.faceExpressionNet.loadFromUri(props.modelsPath),
      index: 3,
    },
  ];

  for (const def of defs) {
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

  const url = props.employee.avatar_url.startsWith("http")
    ? props.employee.avatar_url
    : props.employee.avatar_url.replace("/storage/", "/api/storage/");

  const img = await loadImageFromUrl(url);
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
    if (detection) break;
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
          Authorization: `Bearer ${localStorage.getItem("auth_token") || ""}`,
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
      img.onerror = () => reject(new Error(`Failed to render image`));
      img.src = objectUrl;
    } catch (err) {
      reject(new Error(`Failed to load image: ${err.message}`));
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

// ── Positioning Gate ──────────────────────────────────────────────────────────
// Requires face to be centered and large enough for REQUIRED_POSITION_FRAMES
async function runPositioningGate() {
  return new Promise((resolve, reject) => {
    positionFrames.value = 0;
    baselineFaceWidth = null;

    const loop = setInterval(async () => {
      if (!videoRef.value || videoRef.value.readyState < 2) return;

      const det = await faceapi
        .detectSingleFace(
          videoRef.value,
          new faceapi.TinyFaceDetectorOptions({
            inputSize: 224,
            scoreThreshold: 0.5,
          }),
        )
        .withFaceLandmarks();

      if (!det) {
        positionFrames.value = 0;
        positionGuideClass.value = "neutral";
        positionLabel.value = "No face detected — move closer";
        return;
      }

      const box = det.detection.box;
      const vidW = videoRef.value.videoWidth || 640;
      const vidH = videoRef.value.videoHeight || 480;

      const cx = box.x + box.width / 2;
      const cy = box.y + box.height / 2;
      const centered =
        Math.abs(cx - vidW / 2) < vidW * 0.25 &&
        Math.abs(cy - vidH / 2) < vidH * 0.25;
      const bigEnough = box.width > vidW * 0.18;

      if (!centered || !bigEnough) {
        positionFrames.value = 0;
        positionGuideClass.value = "warning";
        positionLabel.value = !bigEnough ? "Move closer" : "Center your face";
        return;
      }

      // Check for screen replay via texture variance
      const textureOk = checkTextureVariance(det);
      if (!textureOk) {
        clearInterval(loop);
        reject(
          Object.assign(
            new Error("Screen or photo detected. Please use your real face."),
            { code: "SUSPICIOUS" },
          ),
        );
        return;
      }

      positionGuideClass.value = "good";
      positionLabel.value = "Hold still...";
      positionFrames.value++;

      // Save baseline face width for depth checks
      if (!baselineFaceWidth) baselineFaceWidth = box.width;

      if (positionFrames.value >= REQUIRED_POSITION_FRAMES) {
        clearInterval(loop);
        resolve();
      }
    }, DETECTION_INTERVAL_MS);
  });
}

// ── Screen Replay Detection (Texture Variance) ────────────────────────────────
// Real faces have chaotic high-frequency texture; screens are smoother
function checkTextureVariance(detection) {
  try {
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");
    const box = detection.detection.box;

    // Crop just the face region
    const size = 32;
    canvas.width = canvas.height = size;
    ctx.drawImage(
      videoRef.value,
      box.x,
      box.y,
      box.width,
      box.height,
      0,
      0,
      size,
      size,
    );

    const pixels = ctx.getImageData(0, 0, size, size).data;
    const grays = [];
    for (let i = 0; i < pixels.length; i += 4) {
      grays.push(
        0.299 * pixels[i] + 0.587 * pixels[i + 1] + 0.114 * pixels[i + 2],
      );
    }

    const mean = grays.reduce((a, b) => a + b, 0) / grays.length;
    const variance =
      grays.reduce((s, g) => s + Math.pow(g - mean, 2), 0) / grays.length;

    // Screens typically show variance < 80 (very uniform); real skin > 120
    if (lastTextureVar !== null && variance < 60) {
      console.warn(
        "[FaceVerifier] Low texture variance — possible replay:",
        variance.toFixed(1),
      );
      return false;
    }
    lastTextureVar = variance;
    return true;
  } catch (_) {
    return true; // If we can't check, don't block
  }
}

// ── Environmental Flash ───────────────────────────────────────────────────────
function triggerEnvFlash() {
  const colors = [
    "rgba(255,50,50,0.18)",
    "rgba(50,255,50,0.18)",
    "rgba(50,50,255,0.18)",
    "rgba(255,255,50,0.18)",
  ];
  flashColor.value = colors[Math.floor(Math.random() * colors.length)];
  flashActive.value = true;
  setTimeout(() => {
    flashActive.value = false;
  }, 700);
}

// ── Challenge Setup ───────────────────────────────────────────────────────────
function pickChallenges() {
  // Always include depth (move_closer → move_back) as first two for Z-axis verification
  const depthPair = [
    CHALLENGE_POOL.find((c) => c.id === "move_closer"),
    CHALLENGE_POOL.find((c) => c.id === "move_back"),
  ];

  // Shuffle behavioral challenges
  const behavioral = CHALLENGE_POOL.filter(
    (c) => !["move_closer", "move_back"].includes(c.id),
  )
    .sort(() => Math.random() - 0.5)
    .slice(0, NUM_CHALLENGES - 2); // fill remaining slots

  // Chain: depth check first → behavioral
  activeChallenges.value = [...depthPair, ...behavioral];
  challengeIndex.value = 0;
}

// ── Challenge Chain ───────────────────────────────────────────────────────────
async function runChallengeChain() {
  for (let i = 0; i < activeChallenges.value.length; i++) {
    challengeIndex.value = i;
    currentChallengeComplete.value = false;

    const passed = await runSingleChallenge(activeChallenges.value[i]);

    if (!passed) {
      attempts.value++;
      const remaining = MAX_ATTEMPTS - attempts.value;
      if (remaining <= 0) {
        throw new Error("Maximum attempts reached. Verification blocked.");
      }
      throw new Error(
        `Challenge failed: "${activeChallenges.value[i].label}". ` +
          `${remaining} attempt(s) remaining.`,
      );
    }

    currentChallengeComplete.value = true;
    await delay(400);

    // Flash again between challenges (anti-replay)
    triggerEnvFlash();
  }
}

function runSingleChallenge(challenge) {
  return new Promise((resolve) => {
    const totalMs = challenge.duration
      ? challenge.duration * 1000
      : CHALLENGE_DURATION * 1000;
    challengeTimeLeft.value = challenge.duration || CHALLENGE_DURATION;
    timerDashOffset.value = 0;
    challengePassed = false;
    challengeStartTime = Date.now();
    blinkCount.value = 0;
    waitingForReopen = false;
    closedFrameCount = 0;
    rollingEARWindow = [];
    lastNoseY = null;
    nodFrames = 0;

    const startWidth = baselineFaceWidth;

    // Timer countdown
    challengeTimerLoop = setInterval(() => {
      const elapsed = Date.now() - challengeStartTime;
      const remaining = Math.max(0, (totalMs - elapsed) / 1000);
      challengeTimeLeft.value = Math.ceil(remaining);
      timerDashOffset.value = 113 * (1 - elapsed / totalMs);

      if (remaining <= 0 && !challengePassed) {
        clearInterval(challengeTimerLoop);
        clearInterval(detectionLoop);
        resolve(false);
      }
    }, 100);

    // Detection loop
    detectionLoop = setInterval(async () => {
      if (challengePassed) return;
      if (!videoRef.value || videoRef.value.readyState < 2) return;

      const det = await faceapi
        .detectSingleFace(
          videoRef.value,
          new faceapi.TinyFaceDetectorOptions({
            inputSize: 224,
            scoreThreshold: 0.45,
          }),
        )
        .withFaceLandmarks()
        .withFaceExpressions();

      drawOverlay(det);
      faceDetected.value = !!det;

      if (!det) return;

      const passed = evaluateChallenge(challenge.id, det, startWidth);
      if (passed) {
        challengePassed = true;
        clearInterval(challengeTimerLoop);
        clearInterval(detectionLoop);
        resolve(true);
      }
    }, DETECTION_INTERVAL_MS);
  });
}

// ── Challenge Evaluators ──────────────────────────────────────────────────────
function evaluateChallenge(type, det, startWidth) {
  const landmarks = det.landmarks;
  const expr = det.expressions;
  const box = det.detection.box;
  const vidW = videoRef.value?.videoWidth || 640;

  switch (type) {
    case "blink": {
      const ear = computeEAR(landmarks);
      detectBlink(ear);
      return blinkCount.value >= REQUIRED_BLINKS;
    }

    case "smile":
      return (expr.happy ?? 0) > 0.65;

    case "turn_left": {
      const nose = landmarks.getNose?.() ?? [];
      const noseX = nose[3]?.x ?? 0;
      const centerX = box.x + box.width / 2;
      return noseX < centerX - box.width * 0.12;
    }

    case "turn_right": {
      const nose = landmarks.getNose?.() ?? [];
      const noseX = nose[3]?.x ?? 0;
      const centerX = box.x + box.width / 2;
      return noseX > centerX + box.width * 0.12;
    }

    case "nod": {
      const nose = landmarks.getNose?.() ?? [];
      const noseY = nose[3]?.y ?? 0;
      if (lastNoseY === null) {
        lastNoseY = noseY;
        return false;
      }
      const delta = Math.abs(noseY - lastNoseY);
      if (delta > 8) nodFrames++;
      lastNoseY = noseY;
      return nodFrames >= 3;
    }

    case "move_closer": {
      if (!startWidth) return false;
      const ratio = box.width / startWidth;
      if (ratio > 1 + DEPTH_SCALE_THRESHOLD) {
        baselineFaceWidth = box.width; // update baseline after closer
        return true;
      }
      return false;
    }

    case "move_back": {
      if (!baselineFaceWidth) return false;
      const ratio = box.width / baselineFaceWidth;
      return ratio < 1 - DEPTH_SCALE_THRESHOLD;
    }

    default:
      return false;
  }
}

// ── EAR & Blink ──────────────────────────────────────────────────────────────
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
    if (!positions) return 0.3;
    return (
      (earForEye([36, 37, 38, 39, 40, 41].map((i) => positions[i])) +
        earForEye([42, 43, 44, 45, 46, 47].map((i) => positions[i]))) /
      2
    );
  } catch {
    return 0.3;
  }
}

function earForEye(pts) {
  if (!pts || pts.length < 6) return 0.3;
  const h = eucDist(pts[0], pts[3]);
  return h === 0
    ? 0
    : (eucDist(pts[1], pts[5]) + eucDist(pts[2], pts[4])) / (2 * h);
}

function eucDist(a, b) {
  return Math.sqrt(Math.pow(a.x - b.x, 2) + Math.pow(a.y - b.y, 2));
}

function detectBlink(ear) {
  if (!waitingForReopen) {
    rollingEARWindow.push(ear);
    if (rollingEARWindow.length > ROLLING_WINDOW_SIZE) rollingEARWindow.shift();
  }
  if (rollingEARWindow.length < 4) return;

  const avg =
    rollingEARWindow.reduce((a, b) => a + b, 0) / rollingEARWindow.length;
  const drop = avg - ear;

  if (!waitingForReopen && drop > BLINK_DROP_THRESHOLD) {
    closedFrameCount++;
    if (closedFrameCount >= MIN_CLOSED_FRAMES) waitingForReopen = true;
  } else if (waitingForReopen && drop < BLINK_DROP_THRESHOLD * 0.5) {
    blinkCount.value++;
    waitingForReopen = false;
    closedFrameCount = 0;
    rollingEARWindow = [];
  } else if (!waitingForReopen) {
    closedFrameCount = 0;
  }
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
    ctx.fillStyle = "rgba(99,179,237,0.7)";
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
  stopChallengeLoops();
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
    }
  }

  if (results.length === 0) {
    attempts.value++;
    setFailure("Could not detect face at capture time. Please try again.");
    return;
  }

  results.sort((a, b) => a.distance - b.distance);
  const topN = results.slice(0, Math.min(3, results.length));
  capturedDescriptor = averageDescriptors(topN.map((r) => r.descriptor));

  // Draw preview
  await nextTick();
  if (capturePreviewRef.value && videoRef.value) {
    const ctx = capturePreviewRef.value.getContext("2d");
    capturePreviewRef.value.width = videoRef.value.videoWidth;
    capturePreviewRef.value.height = videoRef.value.videoHeight;
    ctx.drawImage(videoRef.value, 0, 0);
  }

  phase.value = "MATCHING";
  await delay(200);

  const distance = faceapi.euclideanDistance(
    storedDescriptor,
    capturedDescriptor,
  );
  matchScore.value = distanceToScore(distance);

  console.log(
    `[FaceVerifier] distance=${distance.toFixed(4)} score=${matchScore.value}%`,
  );

  await delay(1500);

  if (
    distance <= MATCH_DISTANCE_THRESHOLD &&
    matchScore.value >= MATCH_DISPLAY_THRESHOLD
  ) {
    stopWebcam();
    phase.value = "SUCCESS";
    await sendAttendance();
  } else {
    attempts.value++;
    stopWebcam();
    setFailure(
      `Face does not match the registered photo. ` +
        `Match score: ${matchScore.value}% (minimum ${MATCH_DISPLAY_THRESHOLD}% required).`,
    );
    emit("failure", { reason: "face_mismatch", score: matchScore.value });
  }
}

function distanceToScore(distance) {
  if (distance <= 0.2) return Math.round(90 + ((0.2 - distance) / 0.2) * 10);
  if (distance <= 0.35) return Math.round(80 + ((0.35 - distance) / 0.15) * 10);
  if (distance <= 0.45) return Math.round(75 + ((0.45 - distance) / 0.1) * 5);
  if (distance <= 0.6) return Math.round(50 + ((0.6 - distance) / 0.15) * 25);
  return Math.round(Math.max(0, 50 - (distance - 0.6) * 100));
}

function averageDescriptors(descriptors) {
  const len = descriptors[0].length;
  const avg = new Float32Array(len);
  for (let i = 0; i < len; i++) {
    avg[i] = descriptors.reduce((sum, d) => sum + d[i], 0) / descriptors.length;
  }
  return avg;
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
      challenges: activeChallenges.value.map((c) => c.id),
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
  stopChallengeLoops();
  stopWebcam();
  failureReason.value = reason;
  phase.value = "FAILURE";
}

// ── Reset & Cleanup ───────────────────────────────────────────────────────────
function resetState() {
  phase.value = "LOADING_MODELS";
  matchScore.value = 0;
  failureReason.value = "";
  isSending.value = false;
  attendanceResult.value = null;
  faceDetected.value = false;
  blinkCount.value = 0;
  positionFrames.value = 0;
  positionGuideClass.value = "neutral";
  positionLabel.value = "Looking for face...";
  currentChallengeComplete.value = false;
  challengeIndex.value = 0;
  challengeTimeLeft.value = CHALLENGE_DURATION;
  timerDashOffset.value = 0;
  flashActive.value = false;
  activeChallenges.value = [];

  storedDescriptor = null;
  capturedDescriptor = null;
  baselineFaceWidth = null;
  baselineEAR = null;
  calibrationFrames = [];
  stableFrameCount = 0;
  lastNoseY = null;
  nodFrames = 0;
  waitingForReopen = false;
  closedFrameCount = 0;
  rollingEARWindow = [];
  lastTextureVar = null;

  const ctx = overlayCanvasRef.value?.getContext("2d");
  ctx?.clearRect(
    0,
    0,
    overlayCanvasRef.value.width,
    overlayCanvasRef.value.height,
  );
}

function stopChallengeLoops() {
  if (detectionLoop) {
    clearInterval(detectionLoop);
    detectionLoop = null;
  }
  if (challengeTimerLoop) {
    clearInterval(challengeTimerLoop);
    challengeTimerLoop = null;
  }
}

function cleanup() {
  stopChallengeLoops();
  stopWebcam();
}

async function restart() {
  cleanup();
  resetState();
  await nextTick();
  await startVerification();
}

function closeVerifier() {
  if (isSending.value) return;
  cleanup();
  emit("update:isOpen", false);
}

function handleOverlayClick() {
  if (
    ["SUCCESS", "FAILURE", "PHOTO_ERROR", "SUSPICIOUS"].includes(phase.value)
  ) {
    closeVerifier();
  }
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

onUnmounted(() => cleanup());
</script>

<style scoped>
/* ── Base ───────────────────────────────────────────────────────────────────── */
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
  background: #fff;
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

/* ── Header ──────────────────────────────────────────────────────────────────── */
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #e5e7eb;
  gap: 12px;
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

/* Attempt dots */
.attempt-counter {
  display: flex;
  gap: 6px;
}
.attempt-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #e5e7eb;
  transition: background 0.3s;
}
.attempt-dot.used {
  background: #ef4444;
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

/* ── Step Progress ───────────────────────────────────────────────────────────── */
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

/* ── Phase Container ─────────────────────────────────────────────────────────── */
.phase-container {
  padding: 28px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
  min-height: 340px;
  justify-content: center;
}

/* ── Loading Phase ───────────────────────────────────────────────────────────── */
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

/* ── Webcam Phase ────────────────────────────────────────────────────────────── */
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
  transform: scaleX(-1);
  display: block;
}
.overlay-canvas {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  transform: scaleX(-1);
  pointer-events: none;
}

/* Environmental flash */
.env-flash {
  position: absolute;
  inset: 0;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.08s;
  z-index: 5;
}
.env-flash.active {
  opacity: 1;
}

/* Scan frame corners */
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

/* Positioning overlay */
.positioning-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  pointer-events: none;
}
.guide-circle {
  width: 180px;
  height: 220px;
  border-radius: 50%;
  border: 3px solid;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: border-color 0.3s;
}
.guide-circle.neutral .guide-ring-inner {
  border-color: rgba(255, 255, 255, 0.4);
}
.guide-circle.warning {
  border-color: #f6ad55;
}
.guide-circle.good {
  border-color: #48bb78;
}
.guide-ring-inner {
  width: 160px;
  height: 200px;
  border-radius: 50%;
  border: 2px dashed rgba(255, 255, 255, 0.3);
}

.position-label {
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  padding: 4px 14px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
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
.flash-circle {
  font-size: 40px;
}

/* Matching overlay */
.matching-overlay {
  background: rgba(0, 0, 0, 0.75);
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

/* Instruction bar */
.instruction-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #f7fafc;
  font-size: 13px;
  color: #4a5568;
  font-weight: 500;
  width: 100%;
}
.inst-icon {
  font-size: 18px;
}

/* Stability bar */
.stability-bar-wrapper {
  width: 100%;
  max-width: 200px;
}
.stability-bar {
  height: 6px;
  background: #e5e7eb;
  border-radius: 999px;
  overflow: hidden;
}
.stability-fill {
  height: 100%;
  background: linear-gradient(90deg, #48bb78, #3b82f6);
  border-radius: 999px;
  transition: width 0.2s ease;
}

/* ── Liveness Panel ──────────────────────────────────────────────────────────── */
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
  padding: 18px;
  transition: all 0.3s;
}
.challenge-card.challenge-done {
  background: #f0fdf4;
  border-color: #86efac;
}

.challenge-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 14px;
}

.challenge-timer {
  position: relative;
  width: 44px;
  height: 44px;
  flex-shrink: 0;
}
.timer-ring {
  width: 44px;
  height: 44px;
}
.timer-text {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  color: #1a202c;
}

.challenge-icon-title {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
}
.challenge-big-icon {
  font-size: 28px;
}
.challenge-title {
  font-size: 16px;
  font-weight: 700;
  color: #1e3a5f;
}
.challenge-subtitle {
  font-size: 12px;
  color: #64748b;
  margin-top: 2px;
}

/* Chain progress */
.chain-progress {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0;
  flex-wrap: wrap;
}
.chain-dot {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  transition: all 0.3s;
  background: #e5e7eb;
  color: #6b7280;
  z-index: 1;
}
.chain-dot.done {
  background: #3b82f6;
  color: white;
}
.chain-dot.active {
  background: #3b82f6;
  color: white;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.25);
  transform: scale(1.15);
}
.chain-connector {
  height: 3px;
  width: 24px;
  background: #e5e7eb;
  transition: background 0.3s;
}
.chain-connector.filled {
  background: #3b82f6;
}

/* Blink indicators */
.blink-indicators {
  display: flex;
  gap: 14px;
  justify-content: center;
  margin-top: 10px;
  font-size: 26px;
}
.blink-dot.filled {
  transform: scale(1.3);
}

/* Status line */
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

/* ── Result Phases ───────────────────────────────────────────────────────────── */
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
.no-attempts {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 12px;
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

/* SVG animations */
.result-icon {
  display: flex;
  align-items: center;
  justify-content: center;
}
.checkmark-circle,
.x-circle {
  width: 80px;
  height: 80px;
}
.checkmark-circle svg,
.x-circle svg {
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

/* Retry actions */
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

/* Spinner */
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
  .chain-progress {
    gap: 4px;
  }
}
</style>
