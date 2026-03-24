<template>
  <div class="qr-scanner-wrapper">
    <div class="scanner-overlay">
      <!-- Scanner Container -->
      <div class="scanner-container">
        <!-- Header -->
        <div class="scanner-header">
          <h2>Scan QR Code</h2>
          <button @click="closeScanner" class="btn-close">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>

        <!-- Video Preview -->
        <div class="video-container">
          <video ref="videoElement" autoplay playsinline></video>

          <!-- Scanning Frame -->
          <div class="scan-frame">
            <div class="corner top-left"></div>
            <div class="corner top-right"></div>
            <div class="corner bottom-left"></div>
            <div class="corner bottom-right"></div>
            <div v-if="isScanning" class="scan-line"></div>
          </div>

          <!-- Status Messages -->
          <div v-if="statusMessage" class="status-message" :class="statusType">
            <svg
              v-if="statusType === 'error'"
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="15" y1="9" x2="9" y2="15"></line>
              <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
            <svg
              v-else-if="statusType === 'success'"
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <span>{{ statusMessage }}</span>
          </div>
        </div>

        <!-- Instructions -->
        <div class="scanner-instructions">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
          </svg>
          <p>Position the QR code within the frame</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import jsQR from "jsqr";

// Emits
const emit = defineEmits(["scan-success", "scan-error", "close"]);

// State
const videoElement = ref(null);
const stream = ref(null);
const isScanning = ref(false);
const statusMessage = ref("");
const statusType = ref("info"); // 'info', 'error', 'success'

let animationFrameId = null;

// Lifecycle
onMounted(async () => {
  await startCamera();
});

onUnmounted(() => {
  stopCamera();
});

// Start camera
async function startCamera() {
  try {
    statusMessage.value = "Starting camera...";
    statusType.value = "info";

    const constraints = {
      video: {
        facingMode: { ideal: "environment" }, // Prefer back camera on mobile
        width: { ideal: 1280 },
        height: { ideal: 720 },
      },
    };

    stream.value = await navigator.mediaDevices.getUserMedia(constraints);

    if (videoElement.value) {
      videoElement.value.srcObject = stream.value;
      videoElement.value.onloadedmetadata = () => {
        videoElement.value.play();
        isScanning.value = true;
        statusMessage.value = "";
        startScanning();
      };
    }
  } catch (error) {
    console.error("Camera access error:", error);
    handleCameraError(error);
  }
}

// Stop camera
function stopCamera() {
  isScanning.value = false;

  if (animationFrameId) {
    cancelAnimationFrame(animationFrameId);
  }

  if (stream.value) {
    stream.value.getTracks().forEach((track) => track.stop());
    stream.value = null;
  }

  if (videoElement.value) {
    videoElement.value.srcObject = null;
  }
}

// Start scanning loop
function startScanning() {
  const canvas = document.createElement("canvas");
  const context = canvas.getContext("2d");

  function scan() {
    if (!isScanning.value || !videoElement.value) {
      return;
    }

    const video = videoElement.value;

    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      context.drawImage(video, 0, 0, canvas.width, canvas.height);

      const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(imageData.data, imageData.width, imageData.height, {
        inversionAttempts: "dontInvert",
      });

      if (code && code.data) {
        handleQRCodeDetected(code.data);
        return; // Stop scanning after successful read
      }
    }

    animationFrameId = requestAnimationFrame(scan);
  }

  scan();
}

// Handle QR code detected
function handleQRCodeDetected(data) {
  console.log("QR Code detected:", data);

  isScanning.value = false;
  statusMessage.value = "QR Code detected!";
  statusType.value = "success";

  // Emit success event
  setTimeout(() => {
    emit("scan-success", data);
    stopCamera();
  }, 500);
}

// Handle camera errors
function handleCameraError(error) {
  let message = "Failed to access camera";

  if (
    error.name === "NotAllowedError" ||
    error.name === "PermissionDeniedError"
  ) {
    message = "Camera access denied. Please allow camera permissions.";
  } else if (error.name === "NotFoundError") {
    message = "No camera found on this device.";
  } else if (error.name === "NotReadableError") {
    message = "Camera is already in use by another application.";
  } else if (error.name === "OverconstrainedError") {
    message = "Camera does not meet requirements.";
  }

  statusMessage.value = message;
  statusType.value = "error";
  emit("scan-error", message);
}

// Close scanner
function closeScanner() {
  stopCamera();
  emit("close");
}
</script>

<style scoped>
.qr-scanner-wrapper {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.scanner-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.scanner-container {
  background: white;
  border-radius: 20px;
  overflow: hidden;
  max-width: 600px;
  width: 100%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    transform: translateY(30px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

/* Header */
.scanner-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.scanner-header h2 {
  font-size: 20px;
  font-weight: 700;
  margin: 0;
}

.btn-close {
  width: 36px;
  height: 36px;
  border: none;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: white;
  transition: all 0.2s;
}

.btn-close:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: scale(1.05);
}

/* Video Container */
.video-container {
  position: relative;
  aspect-ratio: 4/3;
  background: #000;
  overflow: hidden;
}

.video-container video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Scanning Frame */
.scan-frame {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 250px;
  height: 250px;
  pointer-events: none;
}

.corner {
  position: absolute;
  width: 40px;
  height: 40px;
  border: 3px solid #48bb78;
}

.corner.top-left {
  top: 0;
  left: 0;
  border-right: none;
  border-bottom: none;
  border-radius: 8px 0 0 0;
}

.corner.top-right {
  top: 0;
  right: 0;
  border-left: none;
  border-bottom: none;
  border-radius: 0 8px 0 0;
}

.corner.bottom-left {
  bottom: 0;
  left: 0;
  border-right: none;
  border-top: none;
  border-radius: 0 0 0 8px;
}

.corner.bottom-right {
  bottom: 0;
  right: 0;
  border-left: none;
  border-top: none;
  border-radius: 0 0 8px 0;
}

/* Scanning Animation */
.scan-line {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, #48bb78, transparent);
  box-shadow: 0 0 10px #48bb78;
  animation: scan 2s linear infinite;
}

@keyframes scan {
  0% {
    transform: translateY(0);
  }
  100% {
    transform: translateY(250px);
  }
}

/* Status Message */
.status-message {
  position: absolute;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  backdrop-filter: blur(10px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  animation: slideUp 0.3s ease;
  max-width: 90%;
  text-align: center;
}

.status-message.info {
  background: rgba(66, 153, 225, 0.9);
  color: white;
}

.status-message.error {
  background: rgba(229, 62, 62, 0.9);
  color: white;
}

.status-message.success {
  background: rgba(72, 187, 120, 0.9);
  color: white;
}

/* Instructions */
.scanner-instructions {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px 24px;
  background: #f7fafc;
  color: #4a5568;
}

.scanner-instructions svg {
  flex-shrink: 0;
  color: #667eea;
}

.scanner-instructions p {
  margin: 0;
  font-size: 14px;
  font-weight: 500;
}

/* Responsive */
@media (max-width: 768px) {
  .scanner-overlay {
    padding: 0;
  }

  .scanner-container {
    border-radius: 0;
    max-width: 100%;
    height: 100vh;
    display: flex;
    flex-direction: column;
  }

  .video-container {
    flex: 1;
  }

  .scan-frame {
    width: 200px;
    height: 200px;
  }
}
</style>
