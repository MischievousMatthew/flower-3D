<template>
  <div class="leave-request-scanner">
    <div class="content">
      <!-- Header -->
      <div class="page-header">
        <div class="header-left">
          <router-link to="/" class="logo"
            ><img
              src="../../../../../public/bloomcraft-blankBg.png"
              alt="Bloomcraft Logo"
              width="60"
              height="60"
          /></router-link>
          <div>
            <h1 class="page-title">Request Leave (QR Scan)</h1>
            <p class="page-subtitle">
              Scan employee QR code to submit leave request
            </p>
          </div>
        </div>
      </div>

      <!-- Scanner Section -->
      <div v-if="!employeeData && !isScanning" class="scanner-prompt">
        <div class="prompt-card">
          <div class="qr-illustration">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="120"
              height="120"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
            >
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
          </div>
          <h2>Ready to Scan</h2>
          <p>Click the button below to start scanning employee QR codes</p>
          <button @click="startScanning" class="btn-start-scan">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
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
            Start QR Scanner
          </button>
        </div>
      </div>

      <!-- QR Scanner Component -->
      <div v-if="isScanning" class="scanner-container">
        <QRScanner
          @scan-success="handleScanSuccess"
          @scan-error="handleScanError"
          @close="stopScanning"
        />
      </div>

      <!-- Leave Request Form (After Scan) -->
      <div v-if="employeeData && !isScanning" class="form-section">
        <!-- Employee Card -->
        <div class="employee-card">
          <div class="employee-header">
            <div class="employee-avatar">
              <img
                v-if="employeeData.avatar_url"
                :src="employeeData.avatar_url"
                :alt="employeeData.full_name"
              />
              <span v-else>{{ employeeData.full_name.charAt(0) }}</span>
            </div>
            <div class="employee-details">
              <h3>{{ employeeData.full_name }}</h3>
              <p class="employee-id">{{ employeeData.employee_id }}</p>
              <div class="employee-meta">
                <span class="meta-item">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                  {{ employeeData.position }}
                </span>
                <span class="meta-item">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path
                      d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"
                    ></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                  </svg>
                  {{ employeeData.department }}
                </span>
              </div>
            </div>
            <button @click="resetScanner" class="btn-change">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <polyline points="1 4 1 10 7 10"></polyline>
                <polyline points="23 20 23 14 17 14"></polyline>
                <path
                  d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"
                ></path>
              </svg>
              Change Employee
            </button>
          </div>
        </div>

        <!-- Leave Form -->
        <div class="form-container">
          <form @submit.prevent="submitLeaveRequest">
            <div class="form-grid">
              <!-- Leave Type -->
              <div class="form-group full-width">
                <label>Leave Type *</label>
                <select v-model="formData.leave_type" required>
                  <option value="">Select leave type</option>
                  <option value="sick_leave">Sick Leave</option>
                  <option value="vacation_leave">Vacation Leave</option>
                  <option value="emergency_leave">Emergency Leave</option>
                  <option value="unpaid_leave">Unpaid Leave</option>
                  <option value="maternity_leave">Maternity Leave</option>
                  <option value="paternity_leave">Paternity Leave</option>
                  <option value="bereavement_leave">Bereavement Leave</option>
                  <option value="other">Other</option>
                </select>
                <span v-if="errors.leave_type" class="error-message">
                  {{ errors.leave_type[0] }}
                </span>
              </div>

              <!-- Date Range -->
              <div class="form-group">
                <label>Start Date *</label>
                <input
                  type="date"
                  v-model="formData.start_date"
                  :min="minDate"
                  required
                  @change="calculateDays"
                />
                <span v-if="errors.start_date" class="error-message">
                  {{ errors.start_date[0] }}
                </span>
              </div>

              <div class="form-group">
                <label>End Date *</label>
                <input
                  type="date"
                  v-model="formData.end_date"
                  :min="formData.start_date || minDate"
                  required
                  @change="calculateDays"
                />
                <span v-if="errors.end_date" class="error-message">
                  {{ errors.end_date[0] }}
                </span>
              </div>

              <!-- Days Calculation -->
              <div v-if="calculatedDays > 0" class="days-info full-width">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>Total Working Days: {{ calculatedDays }} day(s)</span>
              </div>

              <!-- Reason -->
              <div class="form-group full-width">
                <label>Reason for Leave *</label>
                <textarea
                  v-model="formData.reason"
                  placeholder="Enter reason for leave request..."
                  rows="4"
                  required
                  minlength="10"
                  maxlength="1000"
                ></textarea>
                <div class="character-count">
                  {{ formData.reason.length }}/1000 characters
                </div>
                <span v-if="errors.reason" class="error-message">
                  {{ errors.reason[0] }}
                </span>
              </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
              <button type="button" @click="resetScanner" class="btn-cancel">
                Cancel
              </button>
              <button type="submit" class="btn-submit" :disabled="isSubmitting">
                <svg
                  v-if="!isSubmitting"
                  xmlns="http://www.w3.org/2000/svg"
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <div v-else class="spinner-small"></div>
                {{ isSubmitting ? "Submitting..." : "Submit Leave Request" }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div v-if="submitSuccess && successData" class="success-modal-overlay">
      <div class="success-modal">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="80"
          height="80"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          class="success-icon"
        >
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="16 8 10 14 8 12"></polyline>
        </svg>
        <h2>Request Submitted!</h2>
        <p>The leave request has been submitted successfully.</p>
        <div class="success-details">
          <div class="detail-row">
            <span class="label">Reference Number:</span>
            <span class="value">#{{ successData.reference_number }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Total Days:</span>
            <span class="value">{{ successData.total_days }} day(s)</span>
          </div>
          <div class="detail-row">
            <span class="label">Status:</span>
            <span class="status-badge pending">Pending Approval</span>
          </div>
        </div>
        <button @click="resetScanner" class="btn-submit">
          Submit Another Request
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";

import QRScanner from "../../../../layouts/components/QRScanner.vue";
import employeeInfoService from "../../../../services/employeeInfoService";
import leaveApi from "../../../../services/leaveApi";

const router = useRouter();

// State
const isScanning = ref(false);
const employeeData = ref(null);
const isSubmitting = ref(false);
const submitSuccess = ref(false);
const successData = ref(null);
const errors = ref({});
const calculatedDays = ref(0);

// Form Data
const formData = ref({
  leave_type: "",
  start_date: "",
  end_date: "",
  reason: "",
});

// Min date (today)
const minDate = computed(() => {
  const today = new Date();
  return today.toISOString().split("T")[0];
});

// Scanner Controls
function startScanning() {
  isScanning.value = true;
}

function stopScanning() {
  isScanning.value = false;
}

function resetScanner() {
  employeeData.value = null;
  submitSuccess.value = false;
  successData.value = null;
  formData.value = {
    leave_type: "",
    start_date: "",
    end_date: "",
    reason: "",
  };
  errors.value = {};
  calculatedDays.value = 0;
  isScanning.value = false;
}

// Handle QR Scan Success
async function handleScanSuccess(qrData) {
  try {
    console.log("QR Scanned:", qrData);

    // Parse QR data (handle both JSON and base64 formats)
    let parsedData;
    try {
      // Check if it's already JSON (starts with '{')
      if (qrData.trim().startsWith("{")) {
        parsedData = JSON.parse(qrData);
      } else {
        // Try base64 decode first
        const decoded = atob(qrData);
        parsedData = JSON.parse(decoded);
      }
    } catch (e) {
      console.error("Failed to parse QR data:", e);
      toast.error("Invalid QR code format");
      return;
    }

    // Validate QR code has required fields
    if (!parsedData.employee_id || !parsedData.owner_id) {
      toast.error("Invalid employee QR code");
      return;
    }

    // Check if it's an attendance QR (we accept it for leave too)
    if (parsedData.type && parsedData.type !== "employee_attendance") {
      toast.error("This QR code is not valid for leave requests");
      return;
    }

    // Fetch employee details
    const response = await employeeInfoService.getById(parsedData.employee_id);

    if (response.success) {
      employeeData.value = response.data;
      stopScanning();
      toast.success(`Employee loaded: ${response.data.full_name}`);
    } else {
      toast.error("Employee not found");
    }
  } catch (error) {
    console.error("Error processing QR scan:", error);
    toast.error(error.message || "Failed to load employee data");
  }
}

// Handle QR Scan Error
function handleScanError(error) {
  console.error("QR Scan Error:", error);
  toast.error("Failed to scan QR code. Please try again.");
}

// Calculate working days
function calculateDays() {
  if (!formData.value.start_date || !formData.value.end_date) {
    calculatedDays.value = 0;
    return;
  }

  const start = new Date(formData.value.start_date);
  const end = new Date(formData.value.end_date);

  if (end < start) {
    calculatedDays.value = 0;
    return;
  }

  let days = 0;
  let current = new Date(start);

  while (current <= end) {
    const dayOfWeek = current.getDay();
    // Count weekdays only (exclude Saturday=6, Sunday=0)
    if (dayOfWeek !== 0 && dayOfWeek !== 6) {
      days++;
    }
    current.setDate(current.getDate() + 1);
  }

  calculatedDays.value = days;
}

// Submit Leave Request
async function submitLeaveRequest() {
  try {
    isSubmitting.value = true;
    errors.value = {};

    const payload = {
      employee_id: employeeData.value.id,
      owner_id: employeeData.value.owner_id,
      start_date: formData.value.start_date,
      end_date: formData.value.end_date,
      leave_type: formData.value.leave_type,
      reason: formData.value.reason,
    };

    const response = await leaveApi.submitLeaveRequest(payload);

    if (response.success) {
      toast.success("Leave request submitted successfully");
      successData.value = response.data;
      submitSuccess.value = true;
    }
  } catch (error) {
    console.error("Submission error:", error);

    if (error.errors) {
      errors.value = error.errors;
      toast.error("Please fix the validation errors");
    } else {
      toast.error(error.message || "Failed to submit leave request");
    }
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<style scoped>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.logo {
  text-decoration: none;
  position: absolute;
  top: 32px;
  left: 32px;
  font-size: 32px;
  animation: bounce 2s ease-in-out infinite;
}

@keyframes bounce {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-6px);
  }
}

.leave-request-scanner {
  display: flex;
  min-height: 100vh;
  background: #f7fafc;
}

.content {
  flex: 1;
  padding: 32px 40px;
}

/* Header */
.page-header {
  margin-bottom: 32px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #1a202c;
  margin-bottom: 4px;
}

.page-subtitle {
  font-size: 14px;
  color: #718096;
}

/* Scanner Prompt */
.scanner-prompt {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
}

.prompt-card {
  background: white;
  border-radius: 20px;
  padding: 60px 40px;
  text-align: center;
  max-width: 500px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.qr-illustration {
  margin-bottom: 24px;
}

.qr-illustration svg {
  color: #48bb78;
}

.prompt-card h2 {
  font-size: 24px;
  font-weight: 700;
  color: #1a202c;
  margin-bottom: 12px;
}

.prompt-card p {
  font-size: 14px;
  color: #718096;
  margin-bottom: 32px;
}

.btn-start-scan {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 14px 32px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-start-scan:hover {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(72, 187, 120, 0.4);
}

/* Scanner Container */
.scanner-container {
  max-width: 600px;
  margin: 0 auto;
}

/* Employee Card */
.employee-card {
  background: white;
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.employee-header {
  display: flex;
  align-items: center;
  gap: 20px;
}

.employee-avatar {
  width: 70px;
  height: 70px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 28px;
  font-weight: 700;
  flex-shrink: 0;
  overflow: hidden;
}

.employee-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.employee-details {
  flex: 1;
}

.employee-details h3 {
  font-size: 20px;
  font-weight: 700;
  color: #1a202c;
  margin-bottom: 4px;
}

.employee-id {
  font-size: 13px;
  color: #a0aec0;
  margin-bottom: 12px;
}

.employee-meta {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #718096;
}

.btn-change {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-change:hover {
  background: #e2e8f0;
  border-color: #cbd5e0;
}

/* Form Container */
.form-container {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 24px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
}

.form-group input,
.form-group select,
.form-group textarea {
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  color: #2d3748;
  transition: all 0.2s;
  font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

.form-group select {
  cursor: pointer;
  background: white;
}

.form-group textarea {
  resize: vertical;
}

.character-count {
  text-align: right;
  font-size: 12px;
  color: #a0aec0;
}

.error-message {
  color: #e53e3e;
  font-size: 12px;
}

.days-info {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #f0fff4;
  border: 1px solid #9ae6b4;
  border-radius: 10px;
  color: #22543d;
  font-size: 14px;
  font-weight: 600;
}

/* Form Actions */
.form-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding-top: 24px;
  border-top: 2px solid #f7fafc;
}

.btn-cancel,
.btn-submit {
  padding: 12px 28px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-cancel {
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  color: #4a5568;
}

.btn-cancel:hover {
  background: #e2e8f0;
}

.btn-submit {
  background: #48bb78;
  border: none;
  color: white;
}

.btn-submit:hover:not(:disabled) {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.35);
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spinner-small {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.success-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  z-index: 1000;
}

.success-modal {
  width: min(100%, 520px);
  background: white;
  border-radius: 20px;
  padding: 40px 32px;
  text-align: center;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
}

.success-icon {
  color: #48bb78;
  margin-bottom: 20px;
}

.success-modal h2 {
  font-size: 28px;
  color: #1a202c;
  margin-bottom: 12px;
}

.success-modal > p {
  font-size: 14px;
  color: #718096;
  margin-bottom: 24px;
}

.success-details {
  background: #f7fafc;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid #e2e8f0;
}

.detail-row:last-child {
  border-bottom: none;
}

.label {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
}

.value {
  font-size: 14px;
  color: #1a202c;
  font-weight: 600;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.status-badge.pending {
  background: #fef3c7;
  color: #92400e;
}

/* Responsive */
@media (max-width: 768px) {
  .content {
    padding: 20px;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .employee-header {
    flex-direction: column;
    text-align: center;
  }

  .btn-change {
    width: 100%;
    justify-content: center;
  }
}
</style>
