<template>
  <div class="leave-management">
    

    <div class="content">
      <!-- Header -->
      <div class="page-header">
        <h1 class="page-title">Leave Management</h1>
        <div class="header-actions">
          <button class="btn-refresh" @click="loadLeaves" :disabled="isLoading">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              :class="{ spinning: isLoading }"
            >
              <polyline points="23 4 23 10 17 10"></polyline>
              <polyline points="1 20 1 14 7 14"></polyline>
              <path
                d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"
              ></path>
            </svg>
            Refresh
          </button>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="stats-grid">
        <div class="stat-card pending">
          <div class="stat-icon">
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
          <div class="stat-content">
            <h3>Pending Requests</h3>
            <p class="stat-value">{{ statistics.pending || 0 }}</p>
          </div>
        </div>

        <div class="stat-card approved">
          <div class="stat-icon">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
          </div>
          <div class="stat-content">
            <h3>Approved This Month</h3>
            <p class="stat-value">{{ statistics.approved_this_month || 0 }}</p>
          </div>
        </div>

        <div class="stat-card days">
          <div class="stat-icon">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
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
          </div>
          <div class="stat-content">
            <h3>Total Days This Month</h3>
            <p class="stat-value">
              {{ statistics.total_days_this_month || 0 }}
            </p>
          </div>
        </div>

        <div class="stat-card rejected">
          <div class="stat-icon">
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
              <line x1="15" y1="9" x2="9" y2="15"></line>
              <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
          </div>
          <div class="stat-content">
            <h3>Rejected</h3>
            <p class="stat-value">{{ statistics.rejected || 0 }}</p>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="filters-bar">
        <div class="filter-group">
          <select v-model="filters.status" @change="loadLeaves">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>

        <div class="filter-group">
          <select v-model="filters.leave_type" @change="loadLeaves">
            <option value="">All Leave Types</option>
            <option value="sick_leave">Sick Leave</option>
            <option value="vacation_leave">Vacation Leave</option>
            <option value="emergency_leave">Emergency Leave</option>
            <option value="unpaid_leave">Unpaid Leave</option>
            <option value="maternity_leave">Maternity Leave</option>
            <option value="paternity_leave">Paternity Leave</option>
            <option value="bereavement_leave">Bereavement Leave</option>
            <option value="other">Other</option>
          </select>
        </div>

        <button class="btn-reset" @click="resetFilters">Reset Filters</button>
      </div>

      <!-- Loading Overlay -->
      <LoadingOverlay
        :visible="isLoading"
        message="Loading leave requests..."
      />

      <!-- Leave Requests Table -->
      <div class="table-container">
        <table class="leave-table">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Leave Type</th>
              <th>Period</th>
              <th>Days</th>
              <th>Status</th>
              <th>Submitted</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="leave in leaves"
              :key="leave.id"
              @click="viewDetails(leave)"
              class="clickable"
            >
              <td class="employee-cell">
                <div class="employee-info">
                  <div class="avatar">
                    {{ leave.employee.first_name.charAt(0) }}
                  </div>
                  <div class="details">
                    <span class="name">{{ leave.employee.full_name }}</span>
                    <span class="id">{{ leave.employee.employee_id }}</span>
                  </div>
                </div>
              </td>
              <td>
                <span class="leave-type-badge" :class="leave.leave_type">
                  {{ formatLeaveType(leave.leave_type) }}
                </span>
              </td>
              <td>
                <div class="date-range">
                  <span>{{ formatDate(leave.start_date) }}</span>
                  <span class="separator">→</span>
                  <span>{{ formatDate(leave.end_date) }}</span>
                </div>
              </td>
              <td>
                <span class="days-badge">{{ leave.total_days }} day(s)</span>
              </td>
              <td>
                <span class="status-badge" :class="leave.status">
                  {{ leave.status }}
                </span>
              </td>
              <td>{{ formatDateTime(leave.submitted_at) }}</td>
              <td class="action-cell" @click.stop>
                <button
                  v-if="leave.status === 'pending'"
                  @click="approveLeave(leave)"
                  class="btn-action approve"
                  title="Approve"
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
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </button>
                <button
                  v-if="leave.status === 'pending'"
                  @click="rejectLeave(leave)"
                  class="btn-action reject"
                  title="Reject"
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
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
                </button>
                <button
                  @click="deleteLeave(leave)"
                  class="btn-action delete"
                  title="Delete"
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
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path
                      d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
                    ></path>
                  </svg>
                </button>
              </td>
            </tr>
            <tr v-if="leaves.length === 0 && !isLoading">
              <td colspan="7" class="no-data">
                <div class="no-data-message">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="48"
                    height="48"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <rect
                      x="3"
                      y="4"
                      width="18"
                      height="18"
                      rx="2"
                      ry="2"
                    ></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                  </svg>
                  <p>No leave requests found</p>
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
            Page {{ pagination.current_page }} of {{ pagination.last_page }}
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

    <!-- Details Modal -->
    <div v-if="selectedLeave" class="modal-overlay" @click="closeModal">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <h2>Leave Request Details</h2>
          <button class="close-btn" @click="closeModal">
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

        <div class="modal-body">
          <!-- Employee Info -->
          <div class="section">
            <h3>Employee Information</h3>
            <div class="info-grid">
              <div class="info-item">
                <span class="label">Name:</span>
                <span class="value">{{
                  selectedLeave.employee.full_name
                }}</span>
              </div>
              <div class="info-item">
                <span class="label">Employee ID:</span>
                <span class="value">{{
                  selectedLeave.employee.employee_id
                }}</span>
              </div>
              <div class="info-item">
                <span class="label">Position:</span>
                <span class="value">{{ selectedLeave.employee.position }}</span>
              </div>
              <div class="info-item">
                <span class="label">Department:</span>
                <span class="value">{{
                  selectedLeave.employee.department
                }}</span>
              </div>
            </div>
          </div>

          <!-- Leave Details -->
          <div class="section">
            <h3>Leave Details</h3>
            <div class="info-grid">
              <div class="info-item">
                <span class="label">Leave Type:</span>
                <span class="value">{{
                  formatLeaveType(selectedLeave.leave_type)
                }}</span>
              </div>
              <div class="info-item">
                <span class="label">Status:</span>
                <span class="status-badge" :class="selectedLeave.status">
                  {{ selectedLeave.status }}
                </span>
              </div>
              <div class="info-item">
                <span class="label">Start Date:</span>
                <span class="value">{{
                  formatDate(selectedLeave.start_date)
                }}</span>
              </div>
              <div class="info-item">
                <span class="label">End Date:</span>
                <span class="value">{{
                  formatDate(selectedLeave.end_date)
                }}</span>
              </div>
              <div class="info-item">
                <span class="label">Total Days:</span>
                <span class="value">{{ selectedLeave.total_days }} day(s)</span>
              </div>
              <div class="info-item">
                <span class="label">Submitted:</span>
                <span class="value">{{
                  formatDateTime(selectedLeave.submitted_at)
                }}</span>
              </div>
            </div>
          </div>

          <!-- Reason -->
          <div class="section">
            <h3>Reason</h3>
            <div class="reason-box">
              {{ selectedLeave.reason }}
            </div>
          </div>

          <!-- Review Info (if reviewed) -->
          <div v-if="selectedLeave.reviewed_at" class="section">
            <h3>Review Information</h3>
            <div class="info-grid">
              <div class="info-item">
                <span class="label">Reviewed By:</span>
                <span class="value">{{
                  selectedLeave.reviewer?.name || "N/A"
                }}</span>
              </div>
              <div class="info-item">
                <span class="label">Reviewed At:</span>
                <span class="value">{{
                  formatDateTime(selectedLeave.reviewed_at)
                }}</span>
              </div>
            </div>
            <div v-if="selectedLeave.admin_notes" class="admin-notes">
              <strong>Admin Notes:</strong>
              <p>{{ selectedLeave.admin_notes }}</p>
            </div>
          </div>

          <!-- Actions (if pending) -->
          <div v-if="selectedLeave.status === 'pending'" class="modal-actions">
            <button @click="approveLeave(selectedLeave)" class="btn-approve">
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
              Approve Leave
            </button>
            <button
              @click="rejectLeave(selectedLeave)"
              class="btn-reject-modal"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
              Reject Leave
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { toast } from "vue3-toastify";

import LoadingOverlay from "../../../../layouts/components/LoadingOverlay.vue";
import leaveApi from "../../../../services/leaveApi";

// State
const isLoading = ref(false);
const leaves = ref([]);
const statistics = ref({});
const selectedLeave = ref(null);

const filters = ref({
  status: "",
  leave_type: "",
});

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});

// Lifecycle
onMounted(() => {
  loadStatistics();
  loadLeaves();
});

// Load statistics
async function loadStatistics() {
  try {
    const response = await leaveApi.getStatistics();
    if (response.success) {
      statistics.value = response.data;
    }
  } catch (error) {
    console.error("Failed to load statistics:", error);
  }
}

// Load leaves
async function loadLeaves(page = 1) {
  try {
    isLoading.value = true;

    const params = {
      page,
      per_page: pagination.value.per_page,
      ...filters.value,
    };

    const response = await leaveApi.getLeaves(params);

    if (response.success) {
      leaves.value = response.data;
      pagination.value = response.pagination;
    }
  } catch (error) {
    toast.error(error.message || "Failed to load leave requests");
    console.error("Error loading leaves:", error);
  } finally {
    isLoading.value = false;
  }
}

// Reset filters
function resetFilters() {
  filters.value = {
    status: "",
    leave_type: "",
  };
  loadLeaves(1);
}

// View details
function viewDetails(leave) {
  selectedLeave.value = leave;
}

// Close modal
function closeModal() {
  selectedLeave.value = null;
}

// Approve leave
async function approveLeave(leave) {
  const notes = prompt("Add notes (optional):");

  try {
    const response = await leaveApi.updateLeaveStatus(
      leave.id,
      "approved",
      notes,
    );

    if (response.success) {
      toast.success("Leave request approved");
      await loadLeaves(pagination.value.current_page);
      await loadStatistics();
      if (selectedLeave.value?.id === leave.id) {
        selectedLeave.value = null;
      }
    }
  } catch (error) {
    toast.error(error.message || "Failed to approve leave");
  }
}

// Reject leave
async function rejectLeave(leave) {
  const notes = prompt("Reason for rejection:");

  if (!notes) {
    toast.warning("Please provide a reason for rejection");
    return;
  }

  try {
    const response = await leaveApi.updateLeaveStatus(
      leave.id,
      "rejected",
      notes,
    );

    if (response.success) {
      toast.success("Leave request rejected");
      await loadLeaves(pagination.value.current_page);
      await loadStatistics();
      if (selectedLeave.value?.id === leave.id) {
        selectedLeave.value = null;
      }
    }
  } catch (error) {
    toast.error(error.message || "Failed to reject leave");
  }
}

// Delete leave
async function deleteLeave(leave) {
  if (!confirm(`Are you sure you want to delete this leave request?`)) {
    return;
  }

  try {
    const response = await leaveApi.deleteLeave(leave.id);

    if (response.success) {
      toast.success("Leave request deleted");
      await loadLeaves(pagination.value.current_page);
      await loadStatistics();
      if (selectedLeave.value?.id === leave.id) {
        selectedLeave.value = null;
      }
    }
  } catch (error) {
    toast.error(error.message || "Failed to delete leave");
  }
}

// Pagination
function goToPage(page) {
  loadLeaves(page);
}

// Format functions
function formatDate(date) {
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatDateTime(datetime) {
  return new Date(datetime).toLocaleString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function formatLeaveType(type) {
  const types = {
    sick_leave: "Sick Leave",
    vacation_leave: "Vacation Leave",
    emergency_leave: "Emergency Leave",
    unpaid_leave: "Unpaid Leave",
    maternity_leave: "Maternity Leave",
    paternity_leave: "Paternity Leave",
    bereavement_leave: "Bereavement Leave",
    other: "Other",
  };
  return types[type] || type;
}
</script>

<style scoped>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.leave-management {
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

.btn-refresh {
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
}

.btn-refresh:hover:not(:disabled) {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}

.btn-refresh:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Statistics */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  border-left: 4px solid;
}

.stat-card.pending {
  border-left-color: #f6ad55;
}

.stat-card.approved {
  border-left-color: #48bb78;
}

.stat-card.days {
  border-left-color: #4299e1;
}

.stat-card.rejected {
  border-left-color: #fc8181;
}

.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-card.pending .stat-icon {
  background: #fef5e7;
  color: #d68910;
}

.stat-card.approved .stat-icon {
  background: #f0fff4;
  color: #38a169;
}

.stat-card.days .stat-icon {
  background: #ebf8ff;
  color: #3182ce;
}

.stat-card.rejected .stat-icon {
  background: #fff5f5;
  color: #e53e3e;
}

.stat-content h3 {
  font-size: 13px;
  color: #718096;
  font-weight: 600;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #1a202c;
}

/* Filters */
.filters-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  align-items: center;
}

.filter-group select {
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  color: #2d3748;
  background: white;
  cursor: pointer;
  transition: all 0.2s;
}

.filter-group select:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

.btn-reset {
  padding: 10px 20px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-reset:hover {
  background: #e2e8f0;
}

/* Table */
.table-container {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.leave-table {
  width: 100%;
  border-collapse: collapse;
}

.leave-table thead {
  background: #f7fafc;
  border-bottom: 2px solid #e2e8f0;
}

.leave-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 12px;
  font-weight: 700;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.leave-table tbody tr {
  border-bottom: 1px solid #f7fafc;
  transition: all 0.2s;
}

.leave-table tbody tr.clickable {
  cursor: pointer;
}

.leave-table tbody tr.clickable:hover {
  background: #f7fafc;
}

.leave-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: #2d3748;
}

/* Employee Cell */
.employee-cell {
  min-width: 200px;
}

.employee-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 16px;
  flex-shrink: 0;
}

.employee-info .details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.employee-info .name {
  font-weight: 600;
  color: #1a202c;
}

.employee-info .id {
  font-size: 12px;
  color: #a0aec0;
}

/* Leave Type Badge */
.leave-type-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.leave-type-badge.sick_leave {
  background: #fed7e2;
  color: #97266d;
}

.leave-type-badge.vacation_leave {
  background: #d6bcfa;
  color: #553c9a;
}

.leave-type-badge.emergency_leave {
  background: #feb2b2;
  color: #9b2c2c;
}

.leave-type-badge.unpaid_leave {
  background: #e2e8f0;
  color: #4a5568;
}

.leave-type-badge.maternity_leave,
.leave-type-badge.paternity_leave {
  background: #bee3f8;
  color: #2c5282;
}

.leave-type-badge.bereavement_leave {
  background: #cbd5e0;
  color: #2d3748;
}

.leave-type-badge.other {
  background: #faf089;
  color: #744210;
}

/* Date Range */
.date-range {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.date-range .separator {
  color: #a0aec0;
}

/* Days Badge */
.days-badge {
  display: inline-block;
  padding: 4px 12px;
  background: #f0fff4;
  color: #22543d;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

/* Status Badge */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}

.status-badge.pending {
  background: #fef5e7;
  color: #d68910;
}

.status-badge.approved {
  background: #c6f6d5;
  color: #22543d;
}

.status-badge.rejected {
  background: #fed7d7;
  color: #742a2a;
}

/* Action Buttons */
.action-cell {
  display: flex;
  gap: 8px;
}

.btn-action {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-action.approve {
  background: #f0fff4;
  color: #38a169;
}

.btn-action.approve:hover {
  background: #c6f6d5;
  transform: scale(1.1);
}

.btn-action.reject {
  background: #fff5f5;
  color: #e53e3e;
}

.btn-action.reject:hover {
  background: #fed7d7;
  transform: scale(1.1);
}

.btn-action.delete {
  background: #f7fafc;
  color: #718096;
}

.btn-action.delete:hover {
  background: #e2e8f0;
  transform: scale(1.1);
}

/* No Data */
.no-data {
  padding: 60px 20px;
  text-align: center;
}

.no-data-message {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.no-data-message svg {
  color: #cbd5e0;
}

.no-data-message p {
  font-size: 16px;
  color: #a0aec0;
  font-weight: 500;
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
  background: white;
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
  opacity: 0.4;
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

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal {
  background: white;
  border-radius: 20px;
  width: 100%;
  max-width: 700px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
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
  padding: 24px 32px;
  border-bottom: 2px solid #f7fafc;
}

.modal-header h2 {
  font-size: 20px;
  font-weight: 700;
  color: #1a202c;
}

.close-btn {
  background: #f7fafc;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #718096;
  transition: all 0.2s;
}

.close-btn:hover {
  background: #e2e8f0;
  color: #2d3748;
}

.modal-body {
  padding: 32px;
  overflow-y: auto;
}

.section {
  margin-bottom: 28px;
}

.section h3 {
  font-size: 14px;
  font-weight: 700;
  color: #718096;
  margin-bottom: 16px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  background: #f7fafc;
  border-radius: 8px;
}

.info-item .label {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
}

.info-item .value {
  font-size: 14px;
  color: #1a202c;
  font-weight: 600;
}

.reason-box {
  padding: 16px;
  background: #f7fafc;
  border-radius: 10px;
  font-size: 14px;
  color: #2d3748;
  line-height: 1.6;
  white-space: pre-wrap;
}

.admin-notes {
  margin-top: 16px;
  padding: 16px;
  background: #ebf8ff;
  border-left: 4px solid #4299e1;
  border-radius: 8px;
}

.admin-notes strong {
  color: #2c5282;
  margin-bottom: 8px;
  display: block;
}

.admin-notes p {
  font-size: 14px;
  color: #2d3748;
  line-height: 1.6;
  margin: 0;
}

.modal-actions {
  display: flex;
  gap: 12px;
  padding-top: 24px;
  border-top: 2px solid #f7fafc;
}

.btn-approve,
.btn-reject-modal {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 24px;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-approve {
  background: #48bb78;
  color: white;
}

.btn-approve:hover {
  background: #38a169;
  transform: translateY(-2px);
}

.btn-reject-modal {
  background: #fc8181;
  color: white;
}

.btn-reject-modal:hover {
  background: #f56565;
  transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 768px) {
  .content {
    padding: 20px;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .filters-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .modal {
    max-width: 100%;
  }
}
</style>
