<template>
  <div class="time-in-out-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Time In / Time Out</h1>
        <p class="page-subtitle">
          View employee attendance for {{ formattedSelectedDate }}
        </p>
      </div>
      <div class="header-right">
        <button @click="goToScanner" class="btn-scanner">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <rect x="2" y="2" width="20" height="20" rx="2" ry="2"></rect>
            <path d="M8 2v20M16 2v20M2 8h20M2 16h20"></path>
          </svg>
          Open Scanner
        </button>
      </div>
    </div>

    <div class="content-wrapper">
      <!-- Main Content -->
      <div class="main-content">
        <!-- Filters -->
        <div class="filters-section">
          <div class="filter-group">
            <label>Employee</label>
            <div class="select-wrapper">
              <select v-model="filters.employee_id" @change="loadRecords">
                <option value="">All Employees</option>
                <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                  {{ emp.full_name }}
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
          </div>

          <div class="filter-group">
            <label>Status</label>
            <div class="select-wrapper">
              <select v-model="filters.status" @change="loadRecords">
                <option value="">All Status</option>
                <option value="complete">Complete</option>
                <option value="incomplete">Incomplete</option>
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
          </div>

          <button @click="resetFilters" class="btn-reset">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"
              ></path>
              <path d="M3 3v5h5"></path>
            </svg>
            Reset
          </button>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
          <div class="summary-card">
            <div class="card-icon gray">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
              </svg>
            </div>
            <div class="card-content">
              <div class="card-value">{{ summary.total_records }}</div>
              <div class="card-label">TOTAL RECORDS</div>
            </div>
          </div>

          <div class="summary-card">
            <div class="card-icon green">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
              >
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
            </div>
            <div class="card-content">
              <div class="card-value">{{ summary.complete_records }}</div>
              <div class="card-label">COMPLETE</div>
            </div>
          </div>

          <div class="summary-card">
            <div class="card-icon yellow">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            </div>
            <div class="card-content">
              <div class="card-value">{{ summary.incomplete_records }}</div>
              <div class="card-label">INCOMPLETE</div>
            </div>
          </div>

          <div class="summary-card">
            <div class="card-icon blue">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            </div>
            <div class="card-content">
              <div class="card-value">{{ summary.total_hours }}</div>
              <div class="card-label">TOTAL HOURS</div>
            </div>
          </div>
        </div>

        <!-- Attendance Table -->
        <div class="table-container">
          <div v-if="isLoading" class="loading-state">
            <div class="spinner"></div>
            <p>Loading records...</p>
          </div>

          <table v-else class="attendance-table">
            <thead>
              <tr>
                <th>EMPLOYEE</th>
                <th>TIME IN</th>
                <th>TIME OUT</th>
                <th>TOTAL HOURS</th>
                <th>STATUS</th>
                <th>ACTIONS</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="record in records" :key="record.id">
                <td>
                  <div class="employee-cell">
                    <div class="employee-avatar">
                      {{ getInitials(record.employee?.full_name) }}
                    </div>
                    <div class="employee-info">
                      <div class="employee-name">
                        {{ record.employee?.full_name }}
                      </div>
                      <div class="employee-id">
                        {{ record.employee?.employee_id }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="time-cell">{{ formatTime(record.time_in) }}</td>
                <td class="time-cell">{{ formatTime(record.time_out) }}</td>
                <td class="hours-cell">
                  {{ formatHours(record.total_hours) }}
                </td>
                <td>
                  <span class="status-badge" :class="record.status">
                    {{
                      record.status === "complete" ? "Complete" : "Incomplete"
                    }}
                  </span>
                </td>
                <td class="action-cell">
                  <button @click="openEditModal(record)" class="btn-action">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
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
                  </button>
                </td>
              </tr>
              <tr v-if="records.length === 0">
                <td colspan="6" class="no-data">
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
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                  </svg>
                  <p>No records found for {{ formattedSelectedDate }}</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Calendar Sidebar -->
      <div class="calendar-sidebar">
        <div class="calendar-container">
          <div class="calendar-header">
            <button @click="previousMonth" class="month-nav">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <polyline points="15 18 9 12 15 6"></polyline>
              </svg>
            </button>
            <h3 class="calendar-title">{{ calendarTitle }}</h3>
            <button @click="nextMonth" class="month-nav">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </button>
          </div>

          <div class="calendar-grid">
            <div class="calendar-weekdays">
              <div v-for="day in weekdays" :key="day" class="weekday">
                {{ day }}
              </div>
            </div>
            <div class="calendar-dates">
              <button
                v-for="date in calendarDates"
                :key="date.key"
                @click="selectDate(date)"
                class="calendar-date"
                :class="{
                  'other-month': !date.isCurrentMonth,
                  selected: isSelectedDate(date),
                  today: isToday(date),
                }"
                :disabled="!date.isCurrentMonth"
              >
                {{ date.day }}
              </button>
            </div>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
          <h4>Selected Date Stats</h4>
          <div class="stat-item">
            <span class="stat-label">Total Employees:</span>
            <span class="stat-value">{{ summary.total_records }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Completed:</span>
            <span class="stat-value success">{{
              summary.complete_records
            }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Incomplete:</span>
            <span class="stat-value warning">{{
              summary.incomplete_records
            }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div v-if="showEditModal" class="modal-overlay" @click="closeEditModal">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h2>Edit Attendance</h2>
        <button class="btn-close" @click="closeEditModal">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
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

      <form @submit.prevent="saveRecord" class="modal-body">
        <div class="form-group">
          <label>Employee</label>
          <input type="text" :value="editForm.employee_name" disabled />
        </div>

        <div class="form-group">
          <label>Date</label>
          <input type="text" :value="editForm.formatted_date" disabled />
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Time In *</label>
            <input type="time" v-model="editForm.time_in" required />
          </div>

          <div class="form-group">
            <label>Time Out</label>
            <input type="time" v-model="editForm.time_out" />
          </div>
        </div>

        <div class="form-group">
          <label>Notes</label>
          <textarea
            v-model="editForm.notes"
            rows="3"
            placeholder="Optional notes..."
          ></textarea>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" @click="closeEditModal">
            Cancel
          </button>
          <button type="submit" class="btn-submit" :disabled="isSaving">
            {{ isSaving ? "Saving..." : "Update Attendance" }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import attendanceApi from "../../../../services/attendanceApi";

const router = useRouter();

// State
const isLoading = ref(false);
const isSaving = ref(false);
const showEditModal = ref(false);
const records = ref([]);
const employees = ref([]);

// Initialize with today's date at midnight (local time)
const today = new Date();
today.setHours(0, 0, 0, 0);
const selectedDate = ref(today);
const currentMonth = ref(new Date(today.getFullYear(), today.getMonth(), 1));

const filters = ref({
  employee_id: "",
  status: "",
});

const summary = ref({
  total_records: 0,
  complete_records: 0,
  incomplete_records: 0,
  total_hours: "0.00",
});

const editForm = ref({
  id: null,
  employee_name: "",
  formatted_date: "",
  time_in: "",
  time_out: "",
  notes: "",
});

const weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

// Computed
const formattedSelectedDate = computed(() => {
  return selectedDate.value.toLocaleDateString("en-US", {
    weekday: "long",
    month: "long",
    day: "numeric",
    year: "numeric",
  });
});

const calendarTitle = computed(() => {
  return currentMonth.value.toLocaleDateString("en-US", {
    month: "long",
    year: "numeric",
  });
});

const calendarDates = computed(() => {
  const year = currentMonth.value.getFullYear();
  const month = currentMonth.value.getMonth();

  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const prevLastDay = new Date(year, month, 0);

  const firstDayOfWeek = firstDay.getDay();
  const lastDate = lastDay.getDate();
  const prevLastDate = prevLastDay.getDate();

  const dates = [];

  // Previous month dates
  for (let i = firstDayOfWeek - 1; i >= 0; i--) {
    dates.push({
      day: prevLastDate - i,
      month: month - 1,
      year: month === 0 ? year - 1 : year,
      isCurrentMonth: false,
      key: `prev-${prevLastDate - i}`,
    });
  }

  // Current month dates
  for (let i = 1; i <= lastDate; i++) {
    dates.push({
      day: i,
      month: month,
      year: year,
      isCurrentMonth: true,
      key: `current-${i}`,
    });
  }

  // Next month dates
  const remainingDays = 42 - dates.length;
  for (let i = 1; i <= remainingDays; i++) {
    dates.push({
      day: i,
      month: month + 1,
      year: month === 11 ? year + 1 : year,
      isCurrentMonth: false,
      key: `next-${i}`,
    });
  }

  return dates;
});

// Helper function to format date for API (YYYY-MM-DD)
function formatDateForAPI(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

// Calendar Methods
function previousMonth() {
  currentMonth.value = new Date(
    currentMonth.value.getFullYear(),
    currentMonth.value.getMonth() - 1,
    1,
  );
}

function nextMonth() {
  currentMonth.value = new Date(
    currentMonth.value.getFullYear(),
    currentMonth.value.getMonth() + 1,
    1,
  );
}

function selectDate(date) {
  if (!date.isCurrentMonth) return;

  // Create new date at midnight local time
  const newDate = new Date(date.year, date.month, date.day);
  newDate.setHours(0, 0, 0, 0);

  selectedDate.value = newDate;
  loadRecords();
}

function isSelectedDate(date) {
  if (!date.isCurrentMonth) return false;
  return (
    date.day === selectedDate.value.getDate() &&
    date.month === selectedDate.value.getMonth() &&
    date.year === selectedDate.value.getFullYear()
  );
}

function isToday(date) {
  const today = new Date();
  return (
    date.day === today.getDate() &&
    date.month === today.getMonth() &&
    date.year === today.getFullYear()
  );
}

// Data Loading
const loadRecords = async () => {
  try {
    isLoading.value = true;

    const formattedDate = formatDateForAPI(selectedDate.value);

    console.log("Loading records for date:", formattedDate);

    const data = await attendanceApi.getRecords({
      date: formattedDate,
      ...filters.value,
    });

    console.log("API Raw Response:", data);

    // Backend returns: { success: true, data: [], pagination: {} }
    records.value = Array.isArray(data?.data) ? data.data : [];

    console.log("Processed records:", records.value);

    summary.value = {
      total_records: records.value.length,
      complete_records: records.value.filter((r) => r.status === "complete")
        .length,
      incomplete_records: records.value.filter((r) => r.status === "incomplete")
        .length,
      total_hours: records.value
        .reduce((sum, r) => sum + (parseFloat(r.total_hours) || 0), 0)
        .toFixed(2),
    };
  } catch (error) {
    console.error("Error loading records:", error);
    records.value = [];
  } finally {
    isLoading.value = false;
  }
};

function resetFilters() {
  filters.value = {
    employee_id: "",
    status: "",
  };
  loadRecords();
}

// Edit Modal Functions
function openEditModal(record) {
  editForm.value = {
    id: record.id,
    employee_name: record.employee?.full_name,
    formatted_date: formatDate(record.attendance_date),
    time_in: record.time_in ? record.time_in.substring(0, 5) : "",
    time_out: record.time_out ? record.time_out.substring(0, 5) : "",
    notes: record.notes || "",
  };
  showEditModal.value = true;
}

function closeEditModal() {
  showEditModal.value = false;
  editForm.value = {
    id: null,
    employee_name: "",
    formatted_date: "",
    time_in: "",
    time_out: "",
    notes: "",
  };
}

async function saveRecord() {
  try {
    isSaving.value = true;

    // Make API call
    const response = await attendanceApi.update(editForm.value.id, {
      time_in: editForm.value.time_in,
      time_out: editForm.value.time_out,
      notes: editForm.value.notes,
    });

    if (response.success) {
      alert("Attendance updated successfully!");
      closeEditModal();
      loadRecords();
    } else {
      alert("Failed to save record: " + (response.message || "Unknown error"));
    }
  } catch (error) {
    console.error("Error saving record:", error);
    alert(
      "Failed to save record: " +
        (error.response?.data?.message || error.message || "Unknown error"),
    );
  } finally {
    isSaving.value = false;
  }
}

// Navigation
function goToScanner() {
  router.push("/erp/hr/attendance/qrscanner");
}

// Helper Functions
function getInitials(name) {
  if (!name) return "?";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .substring(0, 2);
}

function formatDate(date) {
  if (!date) return "---";
  return new Date(date).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function formatTime(time) {
  if (!time) return "---";
  const [hours, minutes] = time.split(":");
  const hour = parseInt(hours);
  const ampm = hour >= 12 ? "PM" : "AM";
  const displayHour = hour % 12 || 12;
  return `${displayHour}:${minutes} ${ampm}`;
}

function formatHours(hours) {
  if (!hours) return "0.00 hrs";
  return `${parseFloat(hours).toFixed(2)} hrs`;
}

// Lifecycle
onMounted(() => {
  console.log(
    "Component mounted, loading records for:",
    formattedSelectedDate.value,
  ); // Debug log
  loadRecords();
});
</script>

<style scoped>
.time-in-out-page {
  padding: 24px;
  background: #f8f9fa;
  min-height: 100vh;
}

/* Page Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.header-left {
  flex: 1;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #2d3748;
  margin: 0 0 6px 0;
}

.page-subtitle {
  font-size: 14px;
  color: #718096;
  margin: 0;
}

.header-right {
  display: flex;
  gap: 12px;
}

.btn-scanner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  background: #48bb78;
  color: white;
}

.btn-scanner:hover {
  background: #38a169;
}

/* Content Wrapper */
.content-wrapper {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 24px;
}

/* Main Content */
.main-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Filters */
.filters-section {
  display: flex;
  gap: 16px;
  align-items: flex-end;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.filter-group label {
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

.btn-reset {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 11px 16px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-reset:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

/* Summary Cards */
.summary-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.card-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.card-icon.gray {
  background: #f7fafc;
  color: #4a5568;
}

.card-icon.green {
  background: #c6f6d5;
  color: #22543d;
}

.card-icon.yellow {
  background: #fef5e7;
  color: #c27803;
}

.card-icon.blue {
  background: #dbeafe;
  color: #1e40af;
}

.card-content {
  flex: 1;
}

.card-value {
  font-size: 26px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 2px;
  line-height: 1;
}

.card-label {
  font-size: 10px;
  color: #a0aec0;
  font-weight: 600;
  letter-spacing: 0.8px;
}

/* Table */
.table-container {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #48bb78;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.loading-state p {
  font-size: 14px;
  color: #718096;
}

.attendance-table {
  width: 100%;
  border-collapse: collapse;
}

.attendance-table thead {
  background: #ffffff;
}

.attendance-table th {
  padding: 14px 18px;
  text-align: left;
  font-size: 10px;
  font-weight: 700;
  color: #a0aec0;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  border-bottom: 1px solid #e2e8f0;
}

.attendance-table tbody tr {
  border-bottom: 1px solid #f7fafc;
  transition: all 0.2s;
}

.attendance-table tbody tr:hover {
  background: #f7fafc;
}

.attendance-table td {
  padding: 14px 18px;
  font-size: 14px;
  color: #2d3748;
}

.employee-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.employee-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: #48bb78;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
}

.employee-info {
  flex: 1;
}

.employee-name {
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 2px;
}

.employee-id {
  font-size: 12px;
  color: #a0aec0;
}

.time-cell {
  font-weight: 600;
  color: #2d3748;
}

.hours-cell {
  font-weight: 600;
  color: #2d3748;
}

.status-badge {
  display: inline-block;
  padding: 5px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.status-badge.complete {
  background: #c6f6d5;
  color: #22543d;
}

.status-badge.incomplete {
  background: #fef5e7;
  color: #c27803;
}

.action-cell {
  text-align: center;
}

.btn-action {
  padding: 6px;
  background: transparent;
  border: none;
  color: #a0aec0;
  cursor: pointer;
  transition: all 0.2s;
  border-radius: 6px;
}

.btn-action:hover {
  color: #48bb78;
  background: #f0fff4;
}

.no-data {
  padding: 60px 20px;
  text-align: center;
}

.no-data svg {
  color: #cbd5e0;
  margin-bottom: 16px;
}

.no-data p {
  margin: 0;
  font-size: 14px;
  color: #a0aec0;
}

/* Calendar Sidebar */
.calendar-sidebar {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.calendar-container {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 20px;
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.calendar-title {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.month-nav {
  background: transparent;
  border: none;
  color: #718096;
  cursor: pointer;
  padding: 6px;
  border-radius: 6px;
  transition: all 0.2s;
}

.month-nav:hover {
  background: #f7fafc;
  color: #48bb78;
}

.calendar-grid {
  width: 100%;
}

.calendar-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  margin-bottom: 8px;
}

.weekday {
  text-align: center;
  font-size: 11px;
  font-weight: 600;
  color: #a0aec0;
  padding: 6px 0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.calendar-dates {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.calendar-date {
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 500;
  color: #2d3748;
  background: transparent;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.calendar-date:hover:not(:disabled) {
  background: #f7fafc;
  border: 1px solid #48bb78;
  color: #48bb78;
  font-weight: bolder;
}

.calendar-date.other-month {
  color: #cbd5e0;
  cursor: default;
}

.calendar-date.selected {
  background: #48bb78;
  color: white;
  font-weight: 700;
}

.calendar-date.today {
  background: #e0f2f7;
  color: #48bb78;
  font-weight: 700;
}

.calendar-date.selected.today {
  background: #48bb78;
  color: white;
}

/* Quick Stats */
.quick-stats {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 20px;
}

.quick-stats h4 {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 16px 0;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid #f7fafc;
}

.stat-item:last-child {
  border-bottom: none;
}

.stat-label {
  font-size: 13px;
  color: #718096;
}

.stat-value {
  font-size: 16px;
  font-weight: 700;
  color: #2d3748;
}

.stat-value.success {
  color: #48bb78;
}

.stat-value.warning {
  color: #ed8936;
}

/* Edit Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
  animation: fadeIn 0.2s;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 500px;
  overflow: hidden;
  animation: slideUp 0.3s;
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

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
  margin: 0;
}

.btn-close {
  background: #f7fafc;
  border: none;
  color: #718096;
  cursor: pointer;
  padding: 6px;
  display: flex;
  align-items: center;
  border-radius: 6px;
  transition: all 0.2s;
}

.btn-close:hover {
  background: #e2e8f0;
  color: #2d3748;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-group label {
  font-size: 12px;
  font-weight: 600;
  color: #4a5568;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.form-group input,
.form-group textarea {
  padding: 10px 12px;
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

.form-group input:disabled {
  background: #f7fafc;
  cursor: not-allowed;
  color: #718096;
}

.form-group textarea {
  resize: vertical;
  font-family: inherit;
}

.modal-footer {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
}

.btn-cancel,
.btn-submit {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-cancel {
  background: #f7fafc;
  color: #2d3748;
  border: 1px solid #e2e8f0;
}

.btn-cancel:hover {
  background: #e2e8f0;
}

.btn-submit {
  background: #48bb78;
  color: white;
}

.btn-submit:hover:not(:disabled) {
  background: #38a169;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Responsive */
@media (max-width: 1200px) {
  .content-wrapper {
    grid-template-columns: 1fr;
  }

  .calendar-sidebar {
    order: -1;
  }

  .summary-cards {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .time-in-out-page {
    padding: 16px;
  }

  .page-header {
    flex-direction: column;
    gap: 16px;
  }

  .filters-section {
    flex-direction: column;
    align-items: stretch;
  }

  .summary-cards {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 600px) {
  .modal {
    max-width: 100%;
    margin: 0 10px;
  }

  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>
