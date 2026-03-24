<template>
  <div class="employee-qr-section">
    <!-- Header -->
    <div class="qr-header">
      <h3 class="qr-title">Attendance QR Code</h3>
      <p class="qr-subtitle">Scan this code to clock in and out</p>
    </div>

    <!-- QR Code Display -->
    <div class="qr-display-container">
      <div class="qr-code-wrapper">
        <div v-if="qrCodeDataUrl" class="qr-image-container">
          <img :src="qrCodeDataUrl" alt="QR Code" class="qr-image" />
        </div>
        <div v-else class="qr-loading">
          <p>Generating QR code...</p>
        </div>
      </div>

      <div class="employee-info">
        <p class="employee-name">{{ props.employee_name }}</p>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="qr-actions">
      <button @click="downloadQR" class="btn-download">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
          <polyline points="7 10 12 15 17 10"></polyline>
          <line x1="12" y1="15" x2="12" y2="3"></line>
        </svg>
        Download PNG
      </button>

      <button @click="printQR" class="btn-print">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <polyline points="6 9 6 2 18 2 18 9"></polyline>
          <path
            d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"
          ></path>
          <rect x="6" y="14" width="12" height="8"></rect>
        </svg>
        Print
      </button>
    </div>

    <!-- Instructions -->
    <div class="qr-instructions">
      <h4>How to use:</h4>
      <ol>
        <li>Open the Attendance Scanner</li>
        <li>Scan this QR code with your camera</li>
        <li>First scan = Clock In | Second scan = Clock Out</li>
      </ol>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import QRCode from "qrcode";

const props = defineProps({
  employee_id: {
    type: [String, Number],
    required: true,
  },
  owner_id: {
    type: [String, Number],
    required: true,
  },
  employee_name: {
    type: String,
    required: true,
  },
});

const qrCodeDataUrl = ref("");

// Generate QR code data
const generateQRData = () => {
  return JSON.stringify({
    owner_id: props.owner_id,
    employee_id: props.employee_id,
    type: "employee_attendance",
    timestamp: Date.now(),
  });
};

// Render QR code as Data URL
const renderQRCode = async () => {
  try {
    const qrData = generateQRData();

    console.log("Generating QR Code with data:", qrData); // Debug

    const dataUrl = await QRCode.toDataURL(qrData, {
      width: 280,
      margin: 2,
      color: {
        dark: "#1a202c",
        light: "#ffffff",
      },
      errorCorrectionLevel: "H",
    });

    qrCodeDataUrl.value = dataUrl;
    console.log("QR Code generated successfully"); // Debug
  } catch (error) {
    console.error("Error generating QR code:", error);
  }
};

// Download QR as PNG
const downloadQR = () => {
  if (!qrCodeDataUrl.value) return;

  const link = document.createElement("a");
  link.download = `QR-${props.employee_id}-${props.employee_name.replace(/\s+/g, "-")}.png`;
  link.href = qrCodeDataUrl.value;
  link.click();
};

// Print QR code
const printQR = () => {
  if (!qrCodeDataUrl.value) return;

  const printWindow = window.open("", "_blank");

  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>Employee QR Code - ${props.employee_name}</title>
        <style>
          body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 40px;
          }
          .print-container {
            text-align: center;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 40px;
            max-width: 400px;
          }
          h1 {
            font-size: 24px;
            margin-bottom: 8px;
            color: #1a202c;
          }
          .employee-id {
            font-size: 16px;
            color: #48bb78;
            font-weight: 600;
            margin-bottom: 32px;
          }
          .qr-code {
            margin: 0 auto 24px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
          }
          .instructions {
            text-align: left;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 2px solid #e2e8f0;
          }
          .instructions h3 {
            font-size: 14px;
            margin-bottom: 12px;
            color: #4a5568;
          }
          .instructions ol {
            font-size: 13px;
            color: #718096;
            line-height: 1.8;
          }
          @media print {
            body { padding: 0; }
            .print-container { border: none; }
          }
        </style>
      </head>
      <body>
        <div class="print-container">
          <h1>${props.employee_name}</h1>
          <p class="employee-id">Employee ID: ${props.employee_id}</p>
          <img src="${qrCodeDataUrl.value}" alt="QR Code" class="qr-code" />
          <div class="instructions">
            <h3>Instructions:</h3>
            <ol>
              <li>Scan this QR code at the Attendance Scanner</li>
              <li>First scan of the day = Clock In</li>
              <li>Second scan of the day = Clock Out</li>
            </ol>
          </div>
        </div>
      </body>
    </html>
  `);

  printWindow.document.close();
  printWindow.focus();

  setTimeout(() => {
    printWindow.print();
  }, 250);
};

// Lifecycle
onMounted(() => {
  console.log("EmployeeQRCode mounted with props:", props); // Debug
  renderQRCode();
});

// Watch for prop changes
watch(
  () => [props.employee_id, props.owner_id],
  () => {
    console.log("Props changed, regenerating QR code"); // Debug
    renderQRCode();
  },
);
</script>

<style scoped>
.employee-qr-section {
  background: #ffffff;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.qr-header {
  margin-bottom: 24px;
}

.qr-title {
  font-size: 18px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 6px 0;
}

.qr-subtitle {
  font-size: 14px;
  color: #718096;
  margin: 0;
}

.qr-display-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px;
  background: #f7fafc;
  border-radius: 12px;
  margin-bottom: 20px;
}

.qr-code-wrapper {
  background: #ffffff;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  margin-bottom: 16px;
  min-width: 150px;
  min-height: 150px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.qr-image-container {
  width: 150px;
  height: 150px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.qr-image {
  width: 100%;
  height: 100%;
  display: block;
}

.qr-loading {
  width: 280px;
  height: 280px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #718096;
  font-size: 14px;
}

.employee-info {
  text-align: center;
}

.employee-name {
  font-size: 16px;
  font-weight: 600;
  color: #1a202c;
  margin: 0 0 4px 0;
}

.employee-id {
  font-size: 14px;
  color: #48bb78;
  font-weight: 600;
  margin: 0;
}

.qr-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 20px;
}

.btn-download,
.btn-print {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-download {
  background: #48bb78;
  color: white;
}

.btn-download:hover {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.35);
}

.btn-print {
  background: #ffffff;
  color: #4a5568;
  border: 1px solid #e2e8f0;
}

.btn-print:hover {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}

.qr-instructions {
  padding: 16px;
  background: #f0fff4;
  border-radius: 10px;
  border-left: 4px solid #48bb78;
}

.qr-instructions h4 {
  font-size: 14px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 10px 0;
}

.qr-instructions ol {
  margin: 0;
  padding-left: 20px;
}

.qr-instructions li {
  font-size: 13px;
  color: #4a5568;
  line-height: 1.6;
  margin-bottom: 4px;
}

@media (max-width: 768px) {
  .employee-qr-section {
    padding: 16px;
  }

  .qr-actions {
    grid-template-columns: 1fr;
  }
}
</style>
