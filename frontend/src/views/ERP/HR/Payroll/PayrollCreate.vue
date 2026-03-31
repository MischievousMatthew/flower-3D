<template>
  <div class="payroll-create-page">
    <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
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
        <div>
          <h1 class="page-title">Generate Payroll</h1>
          <p class="page-subtitle">
            Calculate salary based on attendance records
          </p>
        </div>
      </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
      <!-- Selection Form -->
      <div class="selection-card">
        <h3 class="card-title">Payroll Details</h3>

        <div class="form-group">
          <label>Employee *</label>
          <div class="select-wrapper">
            <select
              v-model="formData.employee_id"
              @change="onEmployeeChange"
              required
            >
              <option value="">Select Employee</option>
              <option
                v-for="emp in employees"
                :key="emp.id"
                :value="emp.id"
                :disabled="!emp.basic_salary || !emp.salary_type"
              >
                {{ emp.full_name }} - {{ emp.employee_id }}
                {{
                  !emp.basic_salary || !emp.salary_type
                    ? "⚠️ Missing Salary Config"
                    : ""
                }}
              </option>
            </select>
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>
          <p class="helper-text">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            Employees with ⚠️ need salary configuration before payroll can be
            generated.
          </p>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Period Start *</label>
            <input
              type="date"
              v-model="formData.period_start"
              @change="clearPreview"
              required
            />
          </div>
          <div class="form-group">
            <label>Period End *</label>
            <input
              type="date"
              v-model="formData.period_end"
              @change="clearPreview"
              required
            />
          </div>
        </div>

        <div class="form-group">
          <label>Notes (Optional)</label>
          <textarea
            v-model="formData.notes"
            rows="3"
            placeholder="Add any additional notes..."
          ></textarea>
        </div>

        <div class="form-group contributions-toggle">
          <div class="toggle-row">
            <label class="toggle-label-main">
              <input
                type="checkbox"
                v-model="formData.include_contributions"
                @change="clearPreview"
                class="toggle-checkbox"
              />
              <span class="toggle-text">
                Include Government Contributions
                <span class="toggle-subtext">(SSS, PhilHealth, Pag-IBIG)</span>
              </span>
            </label>
          </div>
        </div>

        <div class="action-buttons">
          <button
            @click="previewPayroll"
            class="btn-preview"
            :disabled="!canPreview || isPreviewLoading"
          >
            <svg
              v-if="!isPreviewLoading"
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <div v-else class="spinner-small"></div>
            {{ isPreviewLoading ? "Loading..." : "Preview Calculation" }}
          </button>
        </div>
      </div>

      <!-- Employee Info Card -->
      <div v-if="selectedEmployee" class="employee-card">
        <div class="employee-header">
          <div class="employee-avatar">
            {{ getInitials(selectedEmployee.full_name) }}
          </div>
          <div class="employee-info">
            <h3>{{ selectedEmployee.full_name }}</h3>
            <p>{{ selectedEmployee.employee_id }}</p>
            <span class="badge">{{ selectedEmployee.department }}</span>
          </div>
        </div>

        <div
          v-if="!selectedEmployee.basic_salary || !selectedEmployee.salary_type"
          class="salary-config-warning"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path
              d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"
            ></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
          <div class="warning-content">
            <h4>Salary Configuration Missing</h4>
            <p>
              This employee does not have salary configuration set. Please
              configure the following in their employee profile:
            </p>
            <ul>
              <li v-if="!selectedEmployee.basic_salary">Basic Salary</li>
              <li v-if="!selectedEmployee.salary_type">
                Salary Type (Daily/Weekly/Monthly)
              </li>
              <li v-if="!selectedEmployee.standard_work_hours_per_day">
                Standard Work Hours per Day
              </li>
              <li
                v-if="
                  selectedEmployee.salary_type === 'weekly' &&
                  !selectedEmployee.working_days_per_week
                "
              >
                Working Days per Week
              </li>
              <li
                v-if="
                  selectedEmployee.salary_type === 'monthly' &&
                  !selectedEmployee.working_days_per_month
                "
              >
                Working Days per Month
              </li>
            </ul>
            <button @click="goToEmployeeProfile" class="btn-configure">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
                ></path>
                <path
                  d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                ></path>
              </svg>
              Configure Employee Salary
            </button>
          </div>
        </div>

        <div class="employee-details">
          <div class="detail-row">
            <span class="label">Position</span>
            <span class="value">{{ selectedEmployee.position }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Salary Type</span>
            <span class="value">{{
              formatSalaryType(selectedEmployee.salary_type)
            }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Basic Salary</span>
            <span class="value"
              >₱{{
                Number(selectedEmployee.basic_salary).toLocaleString()
              }}</span
            >
          </div>
          <div class="detail-row">
            <span class="label">Standard Hours/Day</span>
            <span class="value"
              >{{ selectedEmployee.standard_work_hours_per_day }} hrs</span
            >
          </div>
          <div
            v-if="selectedEmployee.salary_type === 'weekly'"
            class="detail-row"
          >
            <span class="label">Working Days/Week</span>
            <span class="value"
              >{{ selectedEmployee.working_days_per_week }} days</span
            >
          </div>
          <div
            v-if="selectedEmployee.salary_type === 'monthly'"
            class="detail-row"
          >
            <span class="label">Working Days/Month</span>
            <span class="value"
              >{{ selectedEmployee.working_days_per_month }} days</span
            >
          </div>
        </div>
      </div>

      <!-- Preview/Result Card -->
      <div v-if="previewData" class="preview-card">
        <div class="preview-header">
          <h3>Payroll Calculation</h3>
          <span class="period-badge">
            {{ formatDate(formData.period_start) }} -
            {{ formatDate(formData.period_end) }}
          </span>
        </div>

        <
        <div class="calculation-breakdown">
          <div class="breakdown-item">
            <div class="item-label">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
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
              Expected Work Days
            </div>
            <div class="item-value">
              {{ previewData.expected_work_days }} days
            </div>
          </div>

          <div class="breakdown-item success">
            <div class="item-label">
              <svg
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
              Actual Work Days
            </div>
            <div class="item-value">
              {{ previewData.actual_work_days }} days
            </div>
          </div>

          <div
            v-if="previewData.paid_leave_days > 0"
            class="breakdown-item info"
          >
            <div class="item-label">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              Paid Leave Days (Added)
            </div>
            <div class="item-value success-text">
              +{{ previewData.paid_leave_days }} days
            </div>
          </div>

          <div
            v-if="previewData.unpaid_leave_days > 0"
            class="breakdown-item warning"
          >
            <div class="item-label">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"
                ></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
              </svg>
              Unpaid Leave Days
            </div>
            <div class="item-value warning-text">
              {{ previewData.unpaid_leave_days }} days
            </div>
          </div>

          <div v-if="previewData.absent_days > 0" class="breakdown-item error">
            <div class="item-label">
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
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
              </svg>
              Absent Days
            </div>
            <div class="item-value error-text">
              {{ previewData.absent_days }} days
            </div>
          </div>

          <div class="breakdown-item">
            <div class="item-label">
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
              Total Hours Worked
            </div>
            <div class="item-value">
              {{ previewData.total_hours_worked }} hrs
            </div>
          </div>

          <div class="breakdown-item">
            <div class="item-label">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path
                  d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"
                ></path>
              </svg>
              Hourly Rate
            </div>
            <div class="item-value">₱{{ previewData.hourly_rate }}</div>
          </div>

          <div class="breakdown-item highlight">
            <div class="item-label">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                <line x1="1" y1="10" x2="23" y2="10"></line>
              </svg>
              Gross Salary
            </div>
            <div class="item-value gross">₱{{ previewData.gross_salary }}</div>
          </div>

          <div
            v-if="parseFloat(previewData.deduction_amount) > 0"
            class="breakdown-item error"
          >
            <div class="item-label">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <line x1="5" y1="12" x2="19" y2="12"></line>
              </svg>
              Total Deductions
            </div>
            <div class="item-value error-text">
              -₱{{ previewData.deduction_amount }}
            </div>
          </div>

          <div class="breakdown-item highlight final">
            <div class="item-label">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path
                  d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"
                ></path>
              </svg>
              NET SALARY (Take Home)
            </div>
            <div class="item-value net">₱{{ previewData.net_salary }}</div>
          </div>
        </div>

        <!-- Government Contributions Breakdown -->
        <div
          v-if="previewData.include_contributions"
          class="contributions-breakdown"
        >
          <h4 class="contributions-title">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            </svg>
            Government Contributions
          </h4>

          <div class="contribution-item">
            <span class="contribution-label">SSS (4.5%, max ₱1,900)</span>
            <span class="contribution-value"
              >-₱{{ previewData.sss_contribution }}</span
            >
          </div>
          <div class="contribution-item">
            <span class="contribution-label"
              >PhilHealth (2.5%, ceiling ₱100k)</span
            >
            <span class="contribution-value"
              >-₱{{ previewData.philhealth_contribution }}</span
            >
          </div>
          <div class="contribution-item">
            <span class="contribution-label">Pag-IBIG (1-2%, cap ₱10k)</span>
            <span class="contribution-value"
              >-₱{{ previewData.pagibig_contribution }}</span
            >
          </div>
          <div class="contribution-total">
            <span>Total Contributions</span>
            <span>-₱{{ previewData.total_contributions }}</span>
          </div>
        </div>

        <!-- Salary Breakdown Explanation -->
        <div class="salary-explanation">
          <h4>How Salary is Calculated:</h4>
          <div class="explanation-steps">
            <div class="step">
              <span class="step-number">1</span>
              <span class="step-text"
                >Actual work hours: {{ previewData.actual_work_days }} days ×
                {{ selectedEmployee.standard_work_hours_per_day }} hrs =
                {{
                  (
                    previewData.actual_work_days *
                    selectedEmployee.standard_work_hours_per_day
                  ).toFixed(2)
                }}
                hrs</span
              >
            </div>
            <div v-if="previewData.paid_leave_days > 0" class="step success">
              <span class="step-number">2</span>
              <span class="step-text"
                >✅ Paid leave hours added:
                {{ previewData.paid_leave_days }} days ×
                {{ selectedEmployee.standard_work_hours_per_day }} hrs =
                {{
                  (
                    previewData.paid_leave_days *
                    selectedEmployee.standard_work_hours_per_day
                  ).toFixed(2)
                }}
                hrs</span
              >
            </div>
            <div class="step">
              <span class="step-number">{{
                previewData.paid_leave_days > 0 ? "3" : "2"
              }}</span>
              <span class="step-text"
                >Total paid hours: {{ previewData.total_hours_worked }} hrs ×
                ₱{{ previewData.hourly_rate }}/hr = ₱{{
                  previewData.gross_salary
                }}</span
              >
            </div>
            <div
              v-if="parseFloat(previewData.deduction_amount) > 0"
              class="step error"
            >
              <span class="step-number">{{
                previewData.paid_leave_days > 0 ? "4" : "3"
              }}</span>
              <span class="step-text"
                >❌ Deductions ({{ previewData.unpaid_leave_days }} unpaid leave
                + {{ previewData.absent_days }} absent) = -₱{{
                  previewData.deduction_amount
                }}</span
              >
            </div>
            <div class="step final">
              <span class="step-number">✓</span>
              <span class="step-text"
                ><strong>Final Pay: ₱{{ previewData.net_salary }}</strong></span
              >
            </div>
          </div>
        </div>

        <!-- NEW: Leave Days Notice -->
        <div v-if="previewData.leave_days > 0" class="leave-notice">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <p>
            <strong>{{ previewData.leave_days }} approved leave day(s)</strong>
            during this period. Employee is only paid for
            {{ previewData.attendance_records_count }} day(s) of actual work.
          </p>
        </div>

        <div class="calculation-formula">
          <h4>Calculation Formula:</h4>
          <div class="formula">
            <span class="formula-part"
              >Hourly Rate (₱{{ previewData.hourly_rate }})</span
            >
            <span class="operator">×</span>
            <span class="formula-part"
              >Total Hours ({{ previewData.total_hours_worked }})</span
            >
            <span class="operator">=</span>
            <span class="formula-result">₱{{ previewData.gross_salary }}</span>
          </div>
          <p class="formula-note">
            * Salary calculated based on actual hours worked. Leave days are not
            paid.
          </p>
        </div>

        <button
          @click="generatePayroll"
          class="btn-generate"
          :disabled="isGenerating"
        >
          <svg
            v-if="!isGenerating"
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
          {{ isGenerating ? "Generating..." : "Generate Payroll" }}
        </button>
      </div>

      <!-- No Preview Message -->
      <div v-if="!previewData && selectedEmployee" class="no-preview-card">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="48"
          height="48"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
        >
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="16" x2="12" y2="12"></line>
          <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        <p>Click "Preview Calculation" to see payroll breakdown</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import LoadingOverlay from "../../../../layouts/components/LoadingOverlay.vue";
import employeeInfoService from "../../../../services/employeeInfoService";
import payrollApi from "../../../../services/payrollApi";

const router = useRouter();

// State
const employees = ref([]);
const selectedEmployee = ref(null);
const previewData = ref(null);
const isPreviewLoading = ref(false);
const isGenerating = ref(false);
const isLoading = ref(false);
const isLoadingMessage = ref("Loading...");

const formData = ref({
  employee_id: "",
  period_start: "",
  period_end: "",
  notes: "",
  include_contributions: false,
});

// Computed
const canPreview = computed(() => {
  return (
    formData.value.employee_id &&
    formData.value.period_start &&
    formData.value.period_end &&
    selectedEmployee.value?.basic_salary &&
    selectedEmployee.value?.salary_type
  );
});

// Methods
async function loadEmployees() {
  try {
    isLoading.value = true;
    isLoadingMessage.value = "Loading available employees...";
    const response = await employeeInfoService.getAll({ per_page: 1000 });

    console.log("Employee API Response:", response); // ✅ Debug log

    if (response.success) {
      employees.value = response.data;

      console.log("Loaded employees:", employees.value); // ✅ Debug log
      console.log(
        "Employees with salary config:",
        employees.value.filter((e) => e.basic_salary && e.salary_type),
      ); // ✅ Debug log

      if (employees.value.length === 0) {
        toast.warning("No employees found. Please add employees first.");
      }
    }
  } catch (error) {
    toast.error("Failed to load employees");
    console.error("Error loading employees:", error);
  } finally {
    isLoading.value = false;
  }
}

function goToEmployeeProfile() {
  router.push("/erp/hr/employees/profiles");
}

function onEmployeeChange() {
  selectedEmployee.value = employees.value.find(
    (e) => e.id === parseInt(formData.value.employee_id),
  );
  previewData.value = null;

  if (selectedEmployee.value) {
    if (
      !selectedEmployee.value.basic_salary ||
      !selectedEmployee.value.salary_type
    ) {
      toast.warning(
        `${selectedEmployee.value.full_name} does not have salary configuration. Please configure their salary in the employee profile first.`,
        { autoClose: 5000 },
      );
    }
  }
}

function clearPreview() {
  previewData.value = null;
}

async function previewPayroll() {
  if (!canPreview.value) return;
  try {
    isPreviewLoading.value = true;
    const response = await payrollApi.previewPayroll({
      employee_id: formData.value.employee_id,
      period_start: formData.value.period_start,
      period_end: formData.value.period_end,
      include_contributions: formData.value.include_contributions, // ← add
    });
    if (response.success) {
      previewData.value = response.data;
    } else {
      toast.error(response.message || "Failed to preview payroll");
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to preview payroll");
  } finally {
    isPreviewLoading.value = false;
  }
}

async function generatePayroll() {
  if (!previewData.value) return;
  try {
    isGenerating.value = true;
    const response = await payrollApi.generatePayroll({
      employee_id: formData.value.employee_id,
      period_start: formData.value.period_start,
      period_end: formData.value.period_end,
      notes: formData.value.notes,
      include_contributions: formData.value.include_contributions, // ← add
    });
    if (response.success) {
      toast.success("Payroll generated successfully!");
      await new Promise((resolve) => setTimeout(resolve, 1500));
      await router.push({ name: "PayrollList" });
    } else {
      toast.error(response.message || "Failed to generate payroll");
    }
  } catch (error) {
    toast.error(
      error.response?.data?.message ||
        error.message ||
        "Failed to generate payroll",
    );
  } finally {
    isGenerating.value = false;
  }
}

function goBack() {
  router.back();
}

function getInitials(name) {
  if (!name) return "?";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .substring(0, 2);
}

function formatSalaryType(type) {
  if (!type) return "N/A";
  return type.charAt(0).toUpperCase() + type.slice(1);
}

function formatDate(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

// Lifecycle
onMounted(() => {
  loadEmployees();
});
</script>

<style scoped>
.payroll-create-page {
  padding: 24px;
  background: #f8f9fa;
  min-height: 100vh;
}

/* Page Header */
.page-header {
  margin-bottom: 24px;
}

.header-left {
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
  font-size: 28px;
  font-weight: 700;
  color: #2d3748;
  margin: 0 0 4px 0;
}

.page-subtitle {
  font-size: 14px;
  color: #718096;
  margin: 0;
}

/* Form Container */
.form-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 24px;
  max-width: 1400px;
}

.selection-card,
.employee-card,
.preview-card,
.no-preview-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 24px;
}

.card-title {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
  margin: 0 0 20px 0;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
}

.form-group label {
  font-size: 12px;
  font-weight: 600;
  color: #4a5568;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.select-wrapper {
  position: relative;
}

.select-wrapper select {
  width: 100%;
  padding: 11px 40px 11px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  color: #2d3748;
  background: #ffffff;
  cursor: pointer;
  appearance: none;
  transition: all 0.2s;
}

.select-wrapper select:hover {
  border-color: #cbd5e0;
}

.select-wrapper select:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

.select-wrapper svg {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #718096;
  pointer-events: none;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}

.form-group input,
.form-group textarea {
  padding: 11px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  color: #2d3748;
  transition: all 0.2s;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

.form-group textarea {
  resize: vertical;
  font-family: inherit;
}

.action-buttons {
  display: flex;
  gap: 12px;
}

.btn-preview,
.btn-generate {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-preview {
  background: #ffffff;
  color: #2d3748;
  border: 1px solid #e2e8f0;
}

.btn-preview:hover:not(:disabled) {
  background: #f7fafc;
  border-color: #48bb78;
  color: #48bb78;
}

.btn-generate {
  background: #48bb78;
  color: white;
}

.btn-generate:hover:not(:disabled) {
  background: #38a169;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.35);
}

.btn-preview:disabled,
.btn-generate:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Employee Card */
.employee-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding-bottom: 20px;
  border-bottom: 1px solid #f7fafc;
  margin-bottom: 20px;
}

.employee-avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #48bb78;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
  flex-shrink: 0;
}

.employee-info h3 {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
  margin: 0 0 4px 0;
}

.employee-info p {
  font-size: 13px;
  color: #718096;
  margin: 0 0 8px 0;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  background: #e0f2f7;
  color: #0288d1;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
}

.employee-details {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid #f7fafc;
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
  color: #2d3748;
  font-weight: 600;
}

/* Preview Card */
.preview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 20px;
  border-bottom: 1px solid #f7fafc;
  margin-bottom: 20px;
}

.preview-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
  margin: 0;
}

.period-badge {
  padding: 6px 12px;
  background: #e0f2f7;
  color: #0288d1;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.calculation-breakdown {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

.breakdown-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: #f7fafc;
  border-radius: 10px;
  transition: all 0.2s;
}

.breakdown-item.highlight {
  background: #c6f6d5;
  border: 2px solid #48bb78;
}

.breakdown-item.warning {
  background: #fef5e7;
  border: 1px solid #f39c12;
}

/* Breakdown variants */
.breakdown-item.success {
  background: #c6f6d5;
  border: 1px solid #48bb78;
}

.breakdown-item.info {
  background: #bee3f8;
  border: 1px solid #4299e1;
}

.breakdown-item.error {
  background: #fed7e2;
  border: 1px solid #e53e3e;
}

.breakdown-item.final {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
}

.breakdown-item.final .item-label,
.breakdown-item.final .item-value {
  color: white;
}

.success-text {
  color: #22543d;
}

.error-text {
  color: #9b2c2c;
}

.item-value.net {
  font-size: 28px;
  color: white;
}

/* Salary Explanation */
.salary-explanation {
  background: #f7fafc;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
}

.salary-explanation h4 {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 16px 0;
}

.salary-config-warning {
  display: flex;
  gap: 16px;
  padding: 20px;
  background: #fffbeb;
  border: 2px solid #f59e0b;
  border-radius: 12px;
  margin: 20px 0;
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.salary-config-warning svg {
  color: #d97706;
  flex-shrink: 0;
}

/* Government Contributions Toggle */
.contributions-toggle {
  margin-bottom: 20px;
  padding: 16px;
  background: #f0fff4;
  border: 1px solid #9ae6b4;
  border-radius: 10px;
}

.toggle-row {
  display: flex;
  align-items: center;
}

.toggle-label-main {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
}

.toggle-checkbox {
  width: 18px;
  height: 18px;
  accent-color: #48bb78;
  cursor: pointer;
  flex-shrink: 0;
}

.toggle-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.toggle-subtext {
  font-size: 12px;
  font-weight: 400;
  color: #718096;
}

/* Contributions Breakdown */
.contributions-breakdown {
  background: #fffbeb;
  border: 1px solid #f6e05e;
  border-radius: 10px;
  padding: 16px;
  margin-bottom: 20px;
}

.contributions-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 700;
  color: #744210;
  margin: 0 0 12px 0;
}

.contributions-title svg {
  color: #d69e2e;
}

.contribution-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px dashed #fef08a;
  font-size: 13px;
  color: #92400e;
}

.contribution-label {
  font-weight: 500;
}

.contribution-value {
  font-weight: 600;
  color: #b45309;
}

.contribution-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 10px;
  font-size: 14px;
  font-weight: 700;
  color: #78350f;
}

.warning-content {
  flex: 1;
}

.warning-content h4 {
  font-size: 16px;
  font-weight: 700;
  color: #92400e;
  margin: 0 0 8px 0;
}

.warning-content p {
  font-size: 14px;
  color: #78350f;
  margin: 0 0 12px 0;
  line-height: 1.5;
}

.warning-content ul {
  margin: 0 0 16px 0;
  padding-left: 20px;
}

.warning-content ul li {
  font-size: 14px;
  color: #92400e;
  margin: 4px 0;
}

.btn-configure {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #f59e0b;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-configure:hover {
  background: #d97706;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.explanation-steps {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.helper-text {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 6px;
  font-size: 12px;
  color: #718096;
}

.helper-text svg {
  color: #4299e1;
  flex-shrink: 0;
}

.select-wrapper select option:disabled {
  color: #cbd5e0;
  background: #f7fafc;
}

.step {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: white;
  border-radius: 8px;
  border-left: 3px solid #cbd5e0;
}

.step.success {
  border-left-color: #48bb78;
  background: #f0fff4;
}

.step.error {
  border-left-color: #e53e3e;
  background: #fff5f5;
}

.step.final {
  border-left-color: #667eea;
  background: #edf2f7;
}

.step-number {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #cbd5e0;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  flex-shrink: 0;
}

.step.success .step-number {
  background: #48bb78;
}

.step.error .step-number {
  background: #e53e3e;
}

.step.final .step-number {
  background: #667eea;
}

.step-text {
  font-size: 13px;
  color: #4a5568;
  line-height: 1.5;
}

.warning-text {
  color: #c27803;
}

.leave-notice {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: 8px;
  margin-bottom: 20px;
}

.leave-notice svg {
  color: #c27803;
  flex-shrink: 0;
  margin-top: 2px;
}

.leave-notice p {
  margin: 0;
  font-size: 13px;
  color: #856404;
  line-height: 1.5;
}

.leave-notice strong {
  color: #c27803;
}

.formula-note {
  font-size: 12px;
  color: #718096;
  font-style: italic;
  margin: 12px 0 0 0;
}

.item-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: #4a5568;
  font-weight: 500;
}

.item-label svg {
  color: #718096;
}

.breakdown-item.highlight .item-label svg {
  color: #48bb78;
}

.item-value {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
}

.item-value.gross {
  font-size: 24px;
  color: #48bb78;
}

.calculation-formula {
  padding: 20px;
  background: #f0fff4;
  border-radius: 10px;
  margin-bottom: 24px;
}

.calculation-formula h4 {
  font-size: 13px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 12px 0;
}

.formula {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.formula-part {
  padding: 8px 14px;
  background: #ffffff;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
}

.operator {
  font-size: 16px;
  font-weight: 700;
  color: #718096;
}

.formula-result {
  padding: 8px 14px;
  background: #48bb78;
  color: white;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 700;
}

/* No Preview Card */
.no-preview-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
}

.no-preview-card svg {
  color: #cbd5e0;
  margin-bottom: 16px;
}

.no-preview-card p {
  font-size: 14px;
  color: #a0aec0;
  margin: 0;
}

/* Spinner */
.spinner-small {
  width: 16px;
  height: 16px;
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

/* Responsive */
@media (max-width: 1200px) {
  .form-container {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .payroll-create-page {
    padding: 16px;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .formula {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
