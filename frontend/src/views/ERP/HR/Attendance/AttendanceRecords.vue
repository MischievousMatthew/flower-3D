<template>
  <div class="attendance-records">
    

    <div class="records-content">
      <!-- Header -->
      <div class="page-header">
        <h1 class="page-title">Attendance Records</h1>

        <div class="header-actions">
          <button class="scanner-btn" @click="goToScanner">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
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
          <button class="export-btn" @click="exportRecords">
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
            Export
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="filters-section">
        <div class="search-box">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
          </svg>
          <input
            type="text"
            placeholder="Search by employee name or ID..."
            v-model="filters.search"
            @input="debouncedSearch"
          />
        </div>

        <select
          v-model="filters.employee_id"
          @change="loadRecords(1)"
          class="filter-select"
        >
          <option value="">All Employees</option>
          <option v-for="emp in employees" :key="emp.id" :value="emp.id">
            {{ emp.full_name }}
          </option>
        </select>

        <select
          v-model="filters.status"
          @change="loadRecords(1)"
          class="filter-select"
        >
          <option value="">All Status</option>
          <option value="complete">Complete</option>
          <option value="incomplete">Incomplete</option>
        </select>

        <select
          v-model="filters.month"
          @change="loadRecords(1)"
          class="filter-select"
        >
          <option value="">All Months</option>
          <option v-for="m in 12" :key="m" :value="m">
            {{ getMonthName(m) }}
          </option>
        </select>

        <select
          v-model="filters.year"
          @change="loadRecords(1)"
          class="filter-select"
        >
          <option value="">All Years</option>
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>

        <button class="reset-btn" @click="resetFilters">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
            <path d="M3 3v5h5"></path>
          </svg>
          Reset
        </button>
      </div>

      <!-- Summary Cards -->
      <div class="summary-cards">
        <div class="summary-card">
          <div class="card-icon total">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
          </div>
          <div class="card-content">
            <div class="card-value">{{ summary.total_records }}</div>
            <div class="card-label">Total Records</div>
          </div>
        </div>

        <div class="summary-card">
          <div class="card-icon complete">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
          <div class="card-content">
            <div class="card-value">{{ summary.complete_records }}</div>
            <div class="card-label">Complete</div>
          </div>
        </div>

        <div class="summary-card">
          <div class="card-icon incomplete">
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
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
          </div>
          <div class="card-content">
            <div class="card-value">{{ summary.incomplete_records }}</div>
            <div class="card-label">Incomplete</div>
          </div>
        </div>

        <div class="summary-card">
          <div class="card-icon hours">
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
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
          </div>
          <div class="card-content">
            <div class="card-value">{{ summary.total_hours }}</div>
            <div class="card-label">Total Hours</div>
          </div>
        </div>
      </div>

      <!-- Loading Overlay -->
      <LoadingOverlay :visible="isLoading" :message="loadingMessage" />

      <!-- Records Table -->
      <div class="table-container">
        <table class="records-table">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Date</th>
              <th>Day</th>
              <th>Time In</th>
              <th>Time Out</th>
              <th>Total Hours</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="record in records" :key="record.id">
              <td class="employee-cell">
                <div class="employee-info">
                  <div class="employee-avatar">
                    {{ getInitials(record.employee_name) }}
                  </div>
                  <div class="employee-details">
                    <div class="employee-name">{{ record.employee_name }}</div>
                    <div class="employee-id">
                      {{ record.employee?.employee_id }}
                    </div>
                  </div>
                </div>
              </td>
              <td>{{ record.formatted_date }}</td>
              <td>{{ record.day }}</td>
              <td class="time-cell">{{ record.formatted_time_in }}</td>
              <td class="time-cell">
                {{ record.formatted_time_out || "-" }}
              </td>
              <td class="hours-cell">
                {{ record.total_hours ? record.total_hours + " hrs" : "-" }}
              </td>
              <td>
                <span class="status-badge" :class="record.status">
                  {{ record.status }}
                </span>
              </td>
              <td class="action-cell">
                <button class="action-btn" @click="toggleMenu(record.id)">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                  >
                    <circle cx="12" cy="5" r="2"></circle>
                    <circle cx="12" cy="12" r="2"></circle>
                    <circle cx="12" cy="19" r="2"></circle>
                  </svg>
                </button>
                <div v-if="openMenuId === record.id" class="dropdown-menu">
                  <button @click="editRecord(record)">Edit</button>
                  <button class="delete-btn" @click="deleteRecord(record)">
                    Delete
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="records.length === 0 && !isLoading">
              <td colspan="8" class="no-data">
                <div class="no-data-message">
                  <p>No attendance records found</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="pagination" v-if="pagination.last_page > 1">
          <button
            class="page-btn"
            :disabled="pagination.current_page === 1"
            @click="goToPage(pagination.current_page - 1)"
          >
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
          <span class="page-info">
            Showing {{ pagination.from }}-{{ pagination.to }} of
            {{ pagination.total }}
          </span>
          <button
            class="page-btn"
            :disabled="pagination.current_page === pagination.last_page"
            @click="goToPage(pagination.current_page + 1)"
          >
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
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="showEditModal" class="modal-overlay" @click="closeEditModal">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <h2>Edit Attendance</h2>
          <button class="close-btn" @click="closeEditModal">
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
              {{ isSaving ? "Saving..." : "Update" }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";

import LoadingOverlay from "../../../../layouts/components/LoadingOverlay.vue";
import attendanceService from "../../../../services/attendanceService";
import employeeInfoService from "../../../../services/employeeInfoService";

const router = useRouter();

// State
const isLoading = ref(false);
const loadingMessage = ref("Loading records...");
const isSaving = ref(false);
const records = ref([]);
const employees = ref([]);
const openMenuId = ref(null);
const showEditModal = ref(false);

const filters = ref({
  search: "",
  employee_id: "",
  status: "",
  month: "",
  year: new Date().getFullYear(),
  per_page: 15,
});

const pagination = ref({
  total: 0,
  per_page: 15,
  current_page: 1,
  last_page: 1,
  from: 0,
  to: 0,
});

const summary = ref({
  total_records: 0,
  complete_records: 0,
  incomplete_records: 0,
  total_hours: "0.00",
  average_hours: "0.00",
});

const editForm = ref({
  id: null,
  employee_name: "",
  formatted_date: "",
  time_in: "",
  time_out: "",
  notes: "",
});

// Computed
const years = computed(() => {
  const currentYear = new Date().getFullYear();
  return Array.from({ length: 5 }, (_, i) => currentYear - i);
});

// Lifecycle
onMounted(async () => {
  await loadEmployees();
  await loadRecords();
  await loadSummary();
});

// Load records
async function loadRecords(page = 1) {
  try {
    isLoading.value = true;
    loadingMessage.value = "Loading records...";

    const params = {
      page,
      ...filters.value,
    };

    const response = await attendanceService.getAll(params);

    if (response.success) {
      records.value = response.data;
      pagination.value = response.pagination;
    }
  } catch (error) {
    toast.error(error.message || "Failed to load records");
  } finally {
    isLoading.value = false;
  }
}

// Load employees
async function loadEmployees() {
  try {
    const response = await employeeInfoService.getAll({ per_page: 1000 });
    if (response.success) {
      employees.value = response.data;
    }
  } catch (error) {
    console.error("Failed to load employees:", error);
  }
}

// Load summary
async function loadSummary() {
  try {
    const startDate =
      filters.value.month && filters.value.year
        ? `${filters.value.year}-${String(filters.value.month).padStart(2, "0")}-01`
        : `${filters.value.year || new Date().getFullYear()}-01-01`;

    const endDate =
      filters.value.month && filters.value.year
        ? new Date(filters.value.year, filters.value.month, 0)
            .toISOString()
            .split("T")[0]
        : `${filters.value.year || new Date().getFullYear()}-12-31`;

    const response = await attendanceService.getSummary(startDate, endDate);

    if (response.success) {
      summary.value = response.data;
    }
  } catch (error) {
    console.error("Failed to load summary:", error);
  }
}

// Search debounce
let searchTimeout;
function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadRecords(1);
  }, 500);
}

// Reset filters
function resetFilters() {
  filters.value = {
    search: "",
    employee_id: "",
    status: "",
    month: "",
    year: new Date().getFullYear(),
    per_page: 15,
  };
  loadRecords(1);
  loadSummary();
}

// Pagination
function goToPage(page) {
  loadRecords(page);
}

// Menu toggle
function toggleMenu(id) {
  openMenuId.value = openMenuId.value === id ? null : id;
}

// Edit record
function editRecord(record) {
  editForm.value = {
    id: record.id,
    employee_name: record.employee_name,
    formatted_date: record.formatted_date,
    time_in: record.time_in ? record.time_in.substring(0, 5) : "",
    time_out: record.time_out ? record.time_out.substring(0, 5) : "",
    notes: record.notes || "",
  };
  showEditModal.value = true;
  openMenuId.value = null;
}

// Save record
async function saveRecord() {
  try {
    isSaving.value = true;

    const response = await attendanceService.update(editForm.value.id, {
      time_in: editForm.value.time_in,
      time_out: editForm.value.time_out,
      notes: editForm.value.notes,
    });

    if (response.success) {
      toast.success("Attendance updated successfully");
      closeEditModal();
      await loadRecords(pagination.value.current_page);
      await loadSummary();
    }
  } catch (error) {
    toast.error(error.message || "Failed to update attendance");
  } finally {
    isSaving.value = false;
  }
}

// Delete record
async function deleteRecord(record) {
  if (!confirm(`Delete attendance record for ${record.employee_name}?`)) {
    return;
  }

  try {
    isLoading.value = true;
    loadingMessage.value = "Deleting record...";

    const response = await attendanceService.delete(record.id);

    if (response.success) {
      toast.success("Record deleted successfully");
      await loadRecords(pagination.value.current_page);
      await loadSummary();
    }
  } catch (error) {
    toast.error(error.message || "Failed to delete record");
  } finally {
    isLoading.value = false;
    openMenuId.value = null;
  }
}

// Close edit modal
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

// Export records
function exportRecords() {
  toast.info("Export feature coming soon!");
}

// Navigation
function goToScanner() {
  router.push("/hr/attendance/scanner");
}

// Helpers
function getInitials(name) {
  if (!name) return "?";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .substring(0, 2);
}

function getMonthName(month) {
  const months = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];
  return months[month - 1];
}

// Close menu when clicking outside
if (typeof document !== "undefined") {
  document.addEventListener("click", () => {
    openMenuId.value = null;
  });
}
</script>

<style scoped>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.attendance-records {
  display: flex;
  min-height: 100vh;
  background: #f7fafc;
}

.records-content {
  flex: 1;
  padding: 32px 40px;
}

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #1a202c;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.scanner-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}

.scanner-btn:hover {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.35);
}

.export-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: #ffffff;
  color: #4a5568;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}

.export-btn:hover {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}

/* Filters */
.filters-section {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  flex: 1;
  min-width: 280px;
  transition: all 0.2s;
}

.search-box:focus-within {
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

.search-box svg {
  color: #718096;
  flex-shrink: 0;
}

.search-box input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  color: #1a202c;
  font-family: "Poppins", sans-serif;
}

.filter-select {
  padding: 10px 16px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
  font-weight: 500;
}

.filter-select:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

.reset-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}

.reset-btn:hover {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}

/* Summary Cards */
.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  transition: all 0.2s;
}

.summary-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
}

.card-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.card-icon.total {
  background: #e6fffa;
  color: #234e52;
}

.card-icon.complete {
  background: #c6f6d5;
  color: #22543d;
}

.card-icon.incomplete {
  background: #feebc8;
  color: #7c2d12;
}

.card-icon.hours {
  background: #e0e7ff;
  color: #3730a3;
}

.card-content {
  flex: 1;
}

.card-value {
  font-size: 28px;
  font-weight: 700;
  color: #1a202c;
  margin-bottom: 4px;
}

.card-label {
  font-size: 13px;
  color: #718096;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Table */
.table-container {
  background: #ffffff;
  border-radius: 16px;
  overflow: visible;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.records-table {
  width: 100%;
  border-collapse: collapse;
}

.records-table thead {
  background: #f7fafc;
  border-bottom: 2px solid #e2e8f0;
}

.records-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}

.records-table tbody tr {
  border-bottom: 1px solid #f7fafc;
  transition: all 0.2s;
}

.records-table tbody tr:hover {
  background: #f7fafc;
}

.records-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: #2d3748;
}

.employee-cell {
  min-width: 220px;
}

.employee-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.employee-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #48bb78;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
}

.employee-details {
  flex: 1;
}

.employee-name {
  font-weight: 600;
  color: #1a202c;
  margin-bottom: 2px;
}

.employee-id {
  font-size: 12px;
  color: #718096;
}

.time-cell {
  font-weight: 600;
  color: #48bb78;
}

.hours-cell {
  font-weight: 600;
  color: #3730a3;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
  text-transform: capitalize;
}

.status-badge.complete {
  background: #c6f6d5;
  color: #22543d;
}

.status-badge.incomplete {
  background: #feebc8;
  color: #7c2d12;
}

.action-cell {
  position: relative;
}

.action-btn {
  background: transparent;
  border: none;
  color: #a0aec0;
  cursor: pointer;
  padding: 8px;
  display: flex;
  align-items: center;
  border-radius: 8px;
  transition: all 0.2s;
}

.action-btn:hover {
  background: #f7fafc;
  color: #4a5568;
}

.dropdown-menu {
  position: absolute;
  right: 0;
  top: 100%;
  margin-top: 4px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  z-index: 100;
  min-width: 140px;
  overflow: hidden;
}

.dropdown-menu button {
  display: block;
  width: 100%;
  padding: 12px 16px;
  text-align: left;
  background: transparent;
  border: none;
  font-size: 14px;
  color: #2d3748;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
  font-weight: 500;
}

.dropdown-menu button:hover {
  background: #f7fafc;
}

.dropdown-menu button.delete-btn {
  color: #e53e3e;
}

.dropdown-menu button.delete-btn:hover {
  background: #fff5f5;
}

.no-data {
  padding: 60px 20px;
  text-align: center;
}

.no-data-message p {
  font-size: 16px;
  color: #718096;
}

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 20px;
  border-top: 1px solid #f7fafc;
}

.page-btn {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  color: #718096;
}

.page-btn:hover:not(:disabled) {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-info {
  font-size: 14px;
  color: #718096;
  font-weight: 500;
}

/* Modal */
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

.modal {
  background: #ffffff;
  border-radius: 20px;
  width: 100%;
  max-width: 500px;
  overflow: hidden;
  animation: slideUp 0.3s;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
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
  padding: 24px 32px;
  border-bottom: 2px solid #f7fafc;
}

.modal-header h2 {
  font-size: 24px;
  font-weight: 700;
  color: #1a202c;
}

.close-btn {
  background: #f7fafc;
  border: none;
  color: #718096;
  cursor: pointer;
  padding: 8px;
  display: flex;
  align-items: center;
  border-radius: 8px;
  transition: all 0.2s;
}

.close-btn:hover {
  background: #e2e8f0;
  color: #2d3748;
}

.modal-body {
  padding: 32px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
}

.form-group input,
.form-group textarea {
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  color: #2d3748;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
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
}

.modal-footer {
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
  font-family: "Poppins", sans-serif;
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
  border: 1px solid #48bb78;
  color: white;
}

.btn-submit:hover:not(:disabled) {
  background: #38a169;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.35);
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Responsive */
@media (max-width: 768px) {
  .records-content {
    padding: 20px;
  }

  .page-header {
    flex-direction: column;
    align-items: stretch;
    gap: 16px;
  }

  .filters-section {
    flex-direction: column;
  }

  .search-box {
    min-width: 100%;
  }

  .summary-cards {
    grid-template-columns: 1fr;
  }

  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>
