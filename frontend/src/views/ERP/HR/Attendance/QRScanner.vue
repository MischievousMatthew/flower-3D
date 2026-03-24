<template>
  <div class="qr-scanner-page">
    <!-- Header -->
    <div class="scanner-header">
      <button @click="goBack" class="btn-back">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
        Back
      </button>
      <h1 class="page-title">Attendance Scanner</h1>
    </div>

    <!-- Scanner Section -->
    <div class="scanner-container">
      <!-- Camera Permission -->
      <div v-if="!cameraReady" class="permission-screen">
        <div class="permission-icon">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="64"
            height="64"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path
              d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"
            ></path>
            <circle cx="12" cy="13" r="4"></circle>
          </svg>
        </div>
        <h3>Camera Access Required</h3>
        <p>Please allow camera access to scan QR codes</p>
        <button @click="initCamera" class="btn-allow">Enable Camera</button>
      </div>

      <!-- Active Scanner -->
      <div v-else-if="!scanResult && !showVerifier" class="scanner-view">
        <div class="camera-container">
          <video
            ref="videoElement"
            autoplay
            playsinline
            class="camera-feed"
          ></video>
          <canvas ref="canvasElement" class="scanner-canvas"></canvas>

          <!-- Scanning Frame -->
          <div class="scan-frame">
            <div class="frame-corner tl"></div>
            <div class="frame-corner tr"></div>
            <div class="frame-corner bl"></div>
            <div class="frame-corner br"></div>
            <div v-if="isScanning" class="scan-line"></div>
          </div>
        </div>

        <p class="scan-instruction">Position QR code within the frame</p>
      </div>

      <!-- Loading Employee (between QR scan and face verifier opening) -->
      <div v-else-if="isLoadingEmployee" class="loading-employee">
        <div class="spinner"></div>
        <p>Loading employee data...</p>
      </div>

      <!-- Scan Result -->
      <div
        v-else-if="scanResult"
        class="result-screen"
        :class="scanResult.success ? 'success' : 'error'"
      >
        <div class="result-icon">
          <svg
            v-if="scanResult.success"
            xmlns="http://www.w3.org/2000/svg"
            width="64"
            height="64"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="3"
          >
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <svg
            v-else
            xmlns="http://www.w3.org/2000/svg"
            width="64"
            height="64"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="3"
          >
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="15" y1="9" x2="9" y2="15"></line>
            <line x1="9" y1="9" x2="15" y2="15"></line>
          </svg>
        </div>

        <h2 class="result-title">{{ scanResult.message }}</h2>

        <div
          v-if="scanResult.success && scanResult.data"
          class="result-details"
        >
          <div class="employee-card">
            <div class="employee-avatar">
              {{ getInitials(scanResult.data.employee_name) }}
            </div>
            <div class="employee-data">
              <h3>{{ scanResult.data.employee_name }}</h3>
              <p>{{ scanResult.data.employee_id }}</p>
            </div>
          </div>

          <div class="attendance-info">
            <div class="info-row">
              <span class="label">Date</span>
              <span class="value">{{ scanResult.data.date }}</span>
            </div>
            <div class="info-row">
              <span class="label">Day</span>
              <span class="value">{{ scanResult.data.day }}</span>
            </div>

            <div
              v-if="scanResult.type === 'time_in'"
              class="info-row highlight"
            >
              <span class="label">Time In</span>
              <span class="value time">{{ scanResult.data.time_in }}</span>
            </div>

            <template v-if="scanResult.type === 'time_out'">
              <div class="info-row">
                <span class="label">Time In</span>
                <span class="value">{{ scanResult.data.time_in }}</span>
              </div>
              <div class="info-row highlight">
                <span class="label">Time Out</span>
                <span class="value time">{{ scanResult.data.time_out }}</span>
              </div>
              <div class="info-row">
                <span class="label">Total Hours</span>
                <span class="value hours"
                  >{{ scanResult.data.total_hours }} hrs</span
                >
              </div>
            </template>
          </div>
        </div>

        <button @click="resetScanner" class="btn-scan-again">
          Scan Next Employee
        </button>
      </div>
    </div>

    <FaceAttendanceVerifier
      v-model:isOpen="showVerifier"
      :employee="scannedEmployee"
      :attendance-type="attendanceType"
      models-path="/models"
      @success="onVerifySuccess"
      @failure="onVerifyFailure"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from "vue";
import { useRouter } from "vue-router";
import jsQR from "jsqr";
import attendanceApi from "../../../../services/attendanceApi";
import api from "../../../../plugins/axios";
import FaceAttendanceVerifier from "../../../../layouts/components/FaceAttendanceVerifier.vue";

const router = useRouter();

// ── QR Scanner Refs ───────────────────────────────────────────────────────────
const videoElement = ref(null);
const canvasElement = ref(null);
const cameraReady = ref(false);
const isScanning = ref(false);
const scanResult = ref(null);
let stream = null;
let animationFrame = null;

// ── Face Verifier State ───────────────────────────────────────────────────────
const showVerifier = ref(false);
const scannedEmployee = ref(null);
const attendanceType = ref("time_in");
const isLoadingEmployee = ref(false);

// ── Camera ────────────────────────────────────────────────────────────────────

const initCamera = async () => {
  try {
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

    stream = await navigator.mediaDevices.getUserMedia({
      video: isMobile ? { facingMode: { ideal: "environment" } } : true,
      audio: false,
    });

    cameraReady.value = true;
    await nextTick();

    if (videoElement.value) {
      videoElement.value.srcObject = stream;
      await videoElement.value.play();
      isScanning.value = true;
      startScanning();
    }
  } catch (error) {
    console.error("Camera error:", error);
    alert("Unable to access camera.");
  }
};

const startScanning = () => {
  const canvas = canvasElement.value;
  const video = videoElement.value;
  if (!canvas || !video) return;

  const ctx = canvas.getContext("2d", { willReadFrequently: true });

  const scan = () => {
    if (video.readyState === video.HAVE_ENOUGH_DATA && isScanning.value) {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

      const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(imageData.data, imageData.width, imageData.height);

      if (code && code.data) {
        processQRCode(code.data);
      }
    }

    if (isScanning.value) {
      animationFrame = requestAnimationFrame(scan);
    }
  };

  scan();
};

// ── QR Processing (now opens face verifier instead of directly recording) ─────

const processQRCode = async (qrData) => {
  if (scanResult.value || showVerifier.value || isLoadingEmployee.value) return;

  // Stop QR scanning immediately
  isScanning.value = false;
  stopCamera();

  isLoadingEmployee.value = true;

  try {
    // Step 1: Parse and validate the QR payload
    let payload;
    try {
      payload = JSON.parse(qrData);
    } catch {
      scanResult.value = {
        success: false,
        message: "Invalid QR code format",
        type: "error",
      };
      isLoadingEmployee.value = false;
      return;
    }

    if (payload.type !== "employee_attendance") {
      scanResult.value = {
        success: false,
        message: "This QR code is not for attendance",
        type: "error",
      };
      isLoadingEmployee.value = false;
      return;
    }

    // Step 2: Fetch full employee details from backend
    // We need avatar_url for the face recognition comparison
    const { data } = await api.get(`/employees-info/${payload.employee_id}`);

    if (!data.success || !data.data) {
      scanResult.value = {
        success: false,
        message: "Employee not found",
        type: "error",
      };
      isLoadingEmployee.value = false;
      return;
    }

    const employee = data.data;

    // Step 3: Check if employee has a photo stored
    if (!employee.avatar_url) {
      scanResult.value = {
        success: false,
        message: `No photo on file for ${employee.full_name}. Ask admin to upload a photo first.`,
        type: "error",
      };
      isLoadingEmployee.value = false;
      return;
    }

    // Step 4: Determine time_in or time_out based on today's attendance
    // Check if they already have a time_in for today
    const today = new Date().toISOString().split("T")[0];
    const attendanceResponse = await attendanceApi.getRecords({
      date: today,
      employee_id: employee.id,
    });
    const todayRecord = attendanceResponse?.data?.find(
      (r) => r.employee_id === employee.id,
    );

    if (todayRecord?.status === "complete") {
      scanResult.value = {
        success: false,
        message: `${employee.full_name} has already completed attendance for today`,
        type: "error",
      };
      isLoadingEmployee.value = false;
      return;
    }

    attendanceType.value =
      todayRecord?.time_in && !todayRecord?.time_out ? "time_out" : "time_in";
    scannedEmployee.value = employee;
    isLoadingEmployee.value = false;

    // Step 5: Open face verifier modal
    showVerifier.value = true;
  } catch (error) {
    console.error("QR processing error:", error);
    scanResult.value = {
      success: false,
      message: error.response?.data?.message || "Failed to process QR code",
      type: "error",
    };
    isLoadingEmployee.value = false;
  }
};

// ── Face Verifier Callbacks ───────────────────────────────────────────────────

const onVerifySuccess = ({ employee, type, result }) => {
  showVerifier.value = false;

  // Display the same result screen that was there before
  const now = new Date();
  const time = now.toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
  });
  const date = now.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
  const day = now.toLocaleDateString("en-US", { weekday: "long" });

  scanResult.value = {
    success: true,
    message: type === "time_in" ? "Time In Recorded" : "Time Out Recorded",
    type: type,
    data: {
      employee_name: employee.full_name,
      employee_id: employee.employee_id,
      time_in: result?.time_in || time,
      time_out: result?.time_out || time,
      total_hours: result?.total_hours || "0.00",
      date: result?.date || date,
      day: result?.day || day,
    },
  };
};

const onVerifyFailure = ({ reason, score }) => {
  // The verifier modal shows its own failure screen with retry button.
  // This callback fires only if the user clicks Cancel on the failure screen.
  showVerifier.value = false;

  scanResult.value = {
    success: false,
    message:
      reason === "face_mismatch"
        ? `Face verification failed (${score}% match). Please try again or contact admin.`
        : "Face verification was cancelled.",
    type: "error",
  };
};

// ── Reset / Navigation ────────────────────────────────────────────────────────

const resetScanner = () => {
  scanResult.value = null;
  scannedEmployee.value = null;
  showVerifier.value = false;
  isLoadingEmployee.value = false;
  initCamera();
};

const stopCamera = () => {
  if (stream) {
    stream.getTracks().forEach((track) => track.stop());
    stream = null;
  }
  if (animationFrame) {
    cancelAnimationFrame(animationFrame);
    animationFrame = null;
  }
  isScanning.value = false;
};

const getInitials = (name) => {
  if (!name) return "?";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .substring(0, 2);
};

const goBack = () => {
  stopCamera();
  router.back();
};

onMounted(() => {
  initCamera();
});

onUnmounted(() => {
  stopCamera();
});
</script>

<style scoped>
.qr-scanner-page {
  min-height: 100vh;
  background: #f7fafc;
  padding: 20px;
}

.scanner-header {
  max-width: 600px;
  margin: 0 auto 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.btn-back {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-back:hover {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #1a202c;
  margin: 0;
}

.scanner-container {
  max-width: 600px;
  margin: 0 auto;
  background: #ffffff;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
}

/* Permission Screen */
.permission-screen {
  padding: 80px 40px;
  text-align: center;
}

.permission-icon {
  color: #48bb78;
  margin-bottom: 24px;
}

.permission-screen h3 {
  font-size: 22px;
  font-weight: 700;
  color: #1a202c;
  margin-bottom: 12px;
}

.permission-screen p {
  font-size: 16px;
  color: #718096;
  margin-bottom: 32px;
}

.btn-allow {
  padding: 14px 32px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-allow:hover {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.35);
}

/* Loading Employee */
.loading-employee {
  padding: 80px 40px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e2e8f0;
  border-top-color: #48bb78;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.loading-employee p {
  font-size: 14px;
  color: #718096;
  margin: 0;
}

/* Scanner View */
.scanner-view {
  position: relative;
}

.camera-container {
  position: relative;
  width: 100%;
  height: 400px;
  background: #000;
}

.camera-feed {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.scanner-canvas {
  display: none;
}

.scan-frame {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 280px;
  height: 280px;
  border: 2px solid #48bb78;
  border-radius: 20px;
}

.frame-corner {
  position: absolute;
  width: 30px;
  height: 30px;
  border: 4px solid #48bb78;
}

.frame-corner.tl {
  top: -2px;
  left: -2px;
  border-right: none;
  border-bottom: none;
  border-top-left-radius: 20px;
}

.frame-corner.tr {
  top: -2px;
  right: -2px;
  border-left: none;
  border-bottom: none;
  border-top-right-radius: 20px;
}

.frame-corner.bl {
  bottom: -2px;
  left: -2px;
  border-right: none;
  border-top: none;
  border-bottom-left-radius: 20px;
}

.frame-corner.br {
  bottom: -2px;
  right: -2px;
  border-left: none;
  border-top: none;
  border-bottom-right-radius: 20px;
}

.scan-line {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, #48bb78, transparent);
  animation: scan 2s linear infinite;
}

@keyframes scan {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(280px);
  }
}

.scan-instruction {
  padding: 20px;
  text-align: center;
  background: #f7fafc;
  font-size: 14px;
  color: #718096;
  font-weight: 500;
  margin: 0;
}

/* Result Screen */
.result-screen {
  padding: 60px 40px;
  text-align: center;
  animation: fadeIn 0.3s;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.result-icon {
  margin-bottom: 24px;
  display: inline-flex;
  padding: 20px;
  border-radius: 50%;
}

.result-screen.success .result-icon {
  background: #c6f6d5;
  color: #22543d;
}

.result-screen.error .result-icon {
  background: #fed7e2;
  color: #9b2c2c;
}

.result-title {
  font-size: 22px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 32px 0;
}

.result-details {
  margin-bottom: 32px;
}

.employee-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: #f7fafc;
  border-radius: 12px;
  margin-bottom: 20px;
}

.employee-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: #48bb78;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 700;
  flex-shrink: 0;
}

.employee-data {
  flex: 1;
  text-align: left;
}

.employee-data h3 {
  font-size: 18px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 4px 0;
}

.employee-data p {
  font-size: 14px;
  color: #718096;
  margin: 0;
}

.attendance-info {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
  background: #f7fafc;
  border-radius: 10px;
}

.info-row.highlight {
  background: #c6f6d5;
  border: 2px solid #48bb78;
}

.info-row .label {
  font-size: 14px;
  color: #718096;
  font-weight: 500;
}

.info-row .value {
  font-size: 16px;
  color: #1a202c;
  font-weight: 600;
}

.info-row .value.time {
  font-size: 18px;
  color: #48bb78;
}

.info-row .value.hours {
  color: #48bb78;
}

.btn-scan-again {
  width: 100%;
  padding: 14px 32px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-scan-again:hover {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.35);
}

@media (max-width: 768px) {
  .qr-scanner-page {
    padding: 12px;
  }

  .scanner-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .scan-frame {
    width: 220px;
    height: 220px;
  }

  .result-screen {
    padding: 40px 20px;
  }
}
</style>
