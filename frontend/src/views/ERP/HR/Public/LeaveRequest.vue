<template>
  <div class="leave-request-page">
    <!-- Header -->
    <div class="page-header">
      <div class="company-logo">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="40"
          height="40"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        <h1>Leave Request</h1>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isVerifying" class="loading-container">
      <div class="spinner"></div>
      <p>Verifying your QR code...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="verifyError" class="error-container">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="60"
        height="60"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
      >
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="15" y1="9" x2="9" y2="15"></line>
        <line x1="9" y1="9" x2="15" y2="15"></line>
      </svg>
      <h2>Verification Failed</h2>
      <p>{{ verifyError }}</p>
      <button @click="retryVerification" class="btn-retry">Try Again</button>
    </div>

    <!-- Success State -->
    <div v-else-if="submitSuccess" class="success-container">
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
      <p>Your leave request has been submitted successfully.</p>
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
      <p class="info-text">
        Your request will be reviewed by HR. You'll be notified once it's
        approved or rejected.
      </p>
      <button @click="resetForm" class="btn-another">
        Submit Another Request
      </button>
    </div>

    <!-- Leave Request Form -->
    <div v-else-if="employeeData" class="form-container">
      <!-- Employee Info Card -->
      <div class="employee-card">
        <div class="employee-avatar">
          {{ employeeData.employee_name.charAt(0).toUpperCase() }}
        </div>
        <div class="employee-info">
          <h3>{{ employeeData.employee_name }}</h3>
          <p class="employee-id">{{ employeeData.employee_number }}</p>
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
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
              </svg>
              {{ employeeData.department }}
            </span>
          </div>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submitRequest" class="leave-form">
        <h2>Request Leave</h2>

        <!-- Leave Type -->
        <div class="form-group">
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
        <div class="form-row">
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
        </div>

        <!-- Days Calculation -->
        <div v-if="calculatedDays > 0" class="days-info">
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
          <span>Total: {{ calculatedDays }} working day(s)</span>
        </div>

        <!-- Reason -->
        <div class="form-group">
          <label>Reason for Leave *</label>
          <textarea
            v-model="formData.reason"
            placeholder="Please provide a reason for your leave request..."
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

        <!-- Submit Button -->
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
            <path d="M22 2L11 13"></path>
            <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
          </svg>
          <div v-else class="spinner-small"></div>
          {{ isSubmitting ? "Submitting..." : "Submit Leave Request" }}
        </button>
      </form>

      <!-- Info Notice -->
      <div class="info-notice">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="16" x2="12" y2="12"></line>
          <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        <p>
          Your leave request will be reviewed by HR. Please ensure all details
          are correct before submitting.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import leaveApi from "../../../../services/leaveApi";

const route = useRoute();

// State
const isVerifying = ref(true);
const verifyError = ref(null);
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

// Verify QR Token on mount
onMounted(async () => {
  const token = route.query.token || route.query.qr;

  if (!token) {
    verifyError.value = "No QR token provided. Please scan a valid QR code.";
    isVerifying.value = false;
    return;
  }

  await verifyQRToken(token);
});

// Verify QR Token
async function verifyQRToken(token) {
  try {
    isVerifying.value = true;
    verifyError.value = null;

    const response = await leaveApi.verifyQRToken(token);

    if (response.success) {
      employeeData.value = response.data;
    } else {
      verifyError.value = response.message || "Failed to verify QR code";
    }
  } catch (error) {
    console.error("Verification error:", error);
    verifyError.value =
      error.message || "An error occurred while verifying your QR code";
  } finally {
    isVerifying.value = false;
  }
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
    // Count weekdays only (Monday = 1 to Friday = 5)
    if (dayOfWeek !== 0 && dayOfWeek !== 6) {
      days++;
    }
    current.setDate(current.getDate() + 1);
  }

  calculatedDays.value = days;
}

// Submit Leave Request
async function submitRequest() {
  try {
    isSubmitting.value = true;
    errors.value = {};

    const payload = {
      employee_id: employeeData.value.employee_id,
      owner_id: employeeData.value.owner_id,
      start_date: formData.value.start_date,
      end_date: formData.value.end_date,
      leave_type: formData.value.leave_type,
      reason: formData.value.reason,
    };

    const response = await leaveApi.submitLeaveRequest(payload);

    if (response.success) {
      successData.value = response.data;
      submitSuccess.value = true;
    }
  } catch (error) {
    console.error("Submission error:", error);

    if (error.errors) {
      errors.value = error.errors;
    } else {
      alert(error.message || "Failed to submit leave request");
    }
  } finally {
    isSubmitting.value = false;
  }
}

// Reset form for another request
function resetForm() {
  formData.value = {
    leave_type: "",
    start_date: "",
    end_date: "",
    reason: "",
  };
  errors.value = {};
  calculatedDays.value = 0;
  submitSuccess.value = false;
  successData.value = null;
}

// Retry verification
function retryVerification() {
  window.location.reload();
}
</script>

<style scoped>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family:
    "Poppins",
    -apple-system,
    BlinkMacSystemFont,
    sans-serif;
}

.leave-request-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.page-header {
  width: 100%;
  max-width: 600px;
  margin-bottom: 30px;
}

.company-logo {
  display: flex;
  align-items: center;
  gap: 12px;
  color: white;
}

.company-logo svg {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  padding: 8px;
}

.company-logo h1 {
  font-size: 28px;
  font-weight: 700;
}

/* Loading State */
.loading-container {
  background: white;
  border-radius: 20px;
  padding: 60px 40px;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  max-width: 400px;
  width: 100%;
}

.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #e2e8f0;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 20px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.loading-container p {
  font-size: 16px;
  color: #718096;
}

/* Error State */
.error-container {
  background: white;
  border-radius: 20px;
  padding: 60px 40px;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  max-width: 400px;
  width: 100%;
}

.error-container svg {
  color: #e53e3e;
  margin-bottom: 20px;
}

.error-container h2 {
  font-size: 24px;
  color: #1a202c;
  margin-bottom: 12px;
}

.error-container p {
  font-size: 14px;
  color: #718096;
  margin-bottom: 24px;
}

.btn-retry {
  background: #667eea;
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-retry:hover {
  background: #5a67d8;
  transform: translateY(-2px);
}

/* Success State */
.success-container {
  background: white;
  border-radius: 20px;
  padding: 60px 40px;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  max-width: 500px;
  width: 100%;
}

.success-icon {
  color: #48bb78;
  margin-bottom: 20px;
}

.success-container h2 {
  font-size: 28px;
  color: #1a202c;
  margin-bottom: 12px;
}

.success-container > p {
  font-size: 14px;
  color: #718096;
  margin-bottom: 30px;
}

.success-details {
  background: #f7fafc;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #e2e8f0;
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-row .label {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
}

.detail-row .value {
  font-size: 14px;
  color: #1a202c;
  font-weight: 600;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.status-badge.pending {
  background: #fef5e7;
  color: #d68910;
}

.info-text {
  font-size: 13px;
  color: #718096;
  margin-bottom: 24px;
  line-height: 1.6;
}

.btn-another {
  background: #667eea;
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-another:hover {
  background: #5a67d8;
  transform: translateY(-2px);
}

/* Form Container */
.form-container {
  background: white;
  border-radius: 20px;
  padding: 40px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  max-width: 600px;
  width: 100%;
}

/* Employee Card */
.employee-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 16px;
  margin-bottom: 30px;
  color: white;
}

.employee-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 700;
  flex-shrink: 0;
}

.employee-info {
  flex: 1;
}

.employee-info h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 4px;
}

.employee-id {
  font-size: 13px;
  opacity: 0.9;
  margin-bottom: 8px;
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
  font-size: 12px;
  opacity: 0.9;
}

/* Form */
.leave-form h2 {
  font-size: 22px;
  color: #1a202c;
  margin-bottom: 24px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 8px;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
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
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group select {
  cursor: pointer;
  background: white;
}

.form-group textarea {
  resize: vertical;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.character-count {
  text-align: right;
  font-size: 12px;
  color: #a0aec0;
  margin-top: 4px;
}

.error-message {
  display: block;
  color: #e53e3e;
  font-size: 12px;
  margin-top: 4px;
}

.days-info {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #f0fff4;
  border: 1px solid #9ae6b4;
  border-radius: 8px;
  color: #22543d;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 20px;
}

.btn-submit {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 24px;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 10px;
}

.btn-submit:hover:not(:disabled) {
  background: #5a67d8;
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
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

/* Info Notice */
.info-notice {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #ebf4ff;
  border: 1px solid #90cdf4;
  border-radius: 10px;
  margin-top: 24px;
}

.info-notice svg {
  color: #3182ce;
  flex-shrink: 0;
  margin-top: 2px;
}

.info-notice p {
  font-size: 13px;
  color: #2c5282;
  line-height: 1.6;
  margin: 0;
}

/* Responsive */
@media (max-width: 640px) {
  .leave-request-page {
    padding: 10px;
  }

  .form-container {
    padding: 24px;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .employee-meta {
    flex-direction: column;
    gap: 8px;
  }
}
</style>
