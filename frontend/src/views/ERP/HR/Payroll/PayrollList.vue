<template>
  <div class="payroll-list-page">
    <!-- ───── Page Header ───── -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Payroll Records</h1>
        <p class="page-subtitle">Review and process approved payrolls</p>
      </div>
      <div class="header-right">
        <button
          v-if="selectedIds.length > 0"
          @click="openPayConfirm"
          class="btn-pay"
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
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
          </svg>
          Process Payroll ({{ selectedIds.length }})
        </button>
        <button @click="goToCreate" class="btn-create">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Generate Payroll
        </button>
      </div>
    </div>

    <!-- ───── Filters ───── -->
    <div class="filters-section">
      <!-- Employee Search -->
      <div class="filter-group search-group">
        <label>Search Employee</label>
        <div class="input-icon-wrapper">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            class="search-icon"
          >
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input
            v-model="filters.search"
            @input="onSearchInput"
            type="text"
            placeholder="Type employee name…"
            class="filter-input"
          />
          <button
            v-if="filters.search"
            @click="clearSearch"
            class="btn-clear-input"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="14"
              height="14"
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
      </div>

      <!-- Status -->
      <div class="filter-group">
        <label>Status</label>
        <div class="select-wrapper">
          <select v-model="filters.status" @change="loadPayrolls">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="paid">Paid</option>
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

      <!-- Month -->
      <div class="filter-group">
        <label>Month</label>
        <div class="select-wrapper">
          <select v-model="filters.month" @change="loadPayrolls">
            <option value="">All Months</option>
            <option v-for="(m, i) in months" :key="i + 1" :value="i + 1">
              {{ m }}
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

      <!-- Year -->
      <div class="filter-group">
        <label>Year</label>
        <div class="select-wrapper">
          <select v-model="filters.year" @change="loadPayrolls">
            <option value="">All Years</option>
            <option v-for="y in yearOptions" :key="y" :value="y">
              {{ y }}
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

      <!-- Rows per page -->
      <div class="filter-group" style="min-width: 110px; max-width: 130px">
        <label>Show</label>
        <div class="select-wrapper">
          <select v-model.number="filters.per_page" @change="loadPayrolls(1)">
            <option :value="25">25 rows</option>
            <option :value="50">50 rows</option>
            <option :value="100">100 rows</option>
            <option :value="200">200 rows</option>
            <option :value="99999">All</option>
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
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
          <path d="M3 3v5h5"></path>
        </svg>
        Reset
      </button>
    </div>

    <!-- ───── Summary Cards ───── -->
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
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
            <line x1="1" y1="10" x2="23" y2="10"></line>
          </svg>
        </div>
        <div class="card-content">
          <div class="card-value">{{ summary.total_payrolls }}</div>
          <div class="card-label">TOTAL PAYROLLS</div>
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
            stroke-width="2"
          >
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
          </svg>
        </div>
        <div class="card-content">
          <div class="card-value">{{ summary.unique_employees }}</div>
          <div class="card-label">EMPLOYEES</div>
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
          <div class="card-value">{{ summary.total_hours_worked }}</div>
          <div class="card-label">TOTAL HOURS</div>
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
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
          </svg>
        </div>
        <div class="card-content">
          <div class="card-value">₱{{ summary.total_net_salary || '0.00' }}</div>
          <div class="card-label">TOTAL AMOUNT</div>
        </div>
      </div>
    </div>

    <!-- ───── Loading ───── -->
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading payroll records...</p>
    </div>

    <!-- ───── Table ───── -->
    <div v-else class="table-container">
      <div v-if="selectedIds.length > 0" class="bulk-bar">
        <span>{{ selectedIds.length }} record(s) selected</span>
        <button @click="clearSelection" class="btn-clear-sel">
          Clear selection
        </button>
      </div>

      <table class="payroll-table">
        <thead>
          <tr>
            <th class="th-check">
              <!--
                Header checkbox:
                - Only selects ENABLED (approved) rows.
                - Checked when all approved rows on this page are selected.
                - Indeterminate when some (but not all) approved rows are selected.
              -->
              <input
                type="checkbox"
                class="custom-checkbox"
                :checked="isAllApprovedSelected"
                :indeterminate.prop="isSomeApprovedSelected"
                :disabled="approvedPayrolls.length === 0"
                @change="toggleSelectAllApproved"
              />
            </th>
            <th>EMPLOYEE</th>
            <th>PERIOD</th>
            <th>SALARY TYPE</th>
            <th>TOTAL HOURS</th>
            <th>NET SALARY</th>
            <th>STATUS</th>
            <th>DATE GENERATED</th>
            <th>ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="payroll in payrolls"
            :key="payroll.id"
            :class="{ 'row-selected': selectedIds.includes(payroll.id) }"
          >
            <td class="td-check">
              <!--
                Row checkbox:
                - DISABLED when status is pending / rejected / paid.
                - ENABLED only when status is approved.
              -->
              <input
                type="checkbox"
                class="custom-checkbox"
                :disabled="payroll.status !== 'approved'"
                :checked="selectedIds.includes(payroll.id)"
                @change="toggleSelect(payroll)"
              />
            </td>
            <td>
              <div class="employee-cell">
                <div class="employee-avatar">
                  {{ getInitials(payroll.employee?.full_name) }}
                </div>
                <div class="employee-info">
                  <div class="employee-name">
                    {{ payroll.employee?.full_name }}
                  </div>
                  <div class="employee-id">
                    {{ payroll.employee?.employee_id }}
                  </div>
                </div>
              </div>
            </td>
            <td>
              <div class="period-cell">
                {{ formatDate(payroll.period_start) }} –
                {{ formatDate(payroll.period_end) }}
              </div>
            </td>
            <td>
              <span class="salary-type-badge" :class="payroll.salary_type">
                {{ formatSalaryType(payroll.salary_type) }}
              </span>
            </td>
            <td class="hours-cell">{{ payroll.total_hours_worked }} hrs</td>
            <td class="amount-cell">
              ₱{{
                Number(
                  payroll.net_salary || payroll.gross_salary,
                ).toLocaleString()
              }}
            </td>
            <td>
              <span class="status-badge" :class="payroll.status">{{
                statusLabel(payroll.status)
              }}</span>
            </td>
            <td>{{ formatDateTime(payroll.created_at) }}</td>
            <td class="action-cell">
              <button
                @click="viewDetails(payroll)"
                class="btn-action view"
                title="View details"
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
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
              </button>
              <!-- Quick pay button for approved rows -->
              <button
                v-if="payroll.status === 'approved'"
                @click="openPayConfirmSingle(payroll)"
                class="btn-action pay"
                title="Mark as Paid"
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
                  <line x1="12" y1="1" x2="12" y2="23"></line>
                  <path
                    d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"
                  ></path>
                </svg>
              </button>
              <button
                v-if="payroll.status === 'pending'"
                @click="deletePayroll(payroll)"
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
          <tr v-if="payrolls.length === 0">
            <td colspan="9" class="no-data">
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
              <p>No payroll records found</p>
              <button @click="goToCreate" class="btn-create-first">
                Generate First Payroll
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Record count + Pagination -->
      <div class="pagination-bar">
        <span class="record-count">
          Showing {{ payrolls.length }} of {{ pagination.total }} record{{
            pagination.total !== 1 ? "s" : ""
          }}
        </span>
        <div v-if="pagination.last_page > 1" class="pagination">
          <button
            class="page-btn"
            :disabled="pagination.current_page === 1"
            @click="goToPage(pagination.current_page - 1)"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
          </button>
          <span class="page-info"
            >Page {{ pagination.current_page }} of
            {{ pagination.last_page }}</span
          >
          <button
            class="page-btn"
            :disabled="pagination.current_page === pagination.last_page"
            @click="goToPage(pagination.current_page + 1)"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="14"
              height="14"
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

    <!-- ───── View Details Modal ───── -->
    <div
      v-if="showDetailsModal"
      class="modal-overlay"
      @click="closeDetailsModal"
    >
      <div class="modal" @click.stop>
        <div class="modal-header">
          <h2>Payroll Details</h2>
          <div style="display: flex; align-items: center; gap: 12px">
            <span
              v-if="selectedPayroll"
              class="status-badge"
              :class="selectedPayroll.status"
            >
              {{ statusLabel(selectedPayroll.status) }}
            </span>
            <button class="btn-close" @click="closeDetailsModal">
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
        </div>
        <div v-if="selectedPayroll" class="modal-body">
          <div class="detail-section">
            <h3>Employee Information</h3>
            <div class="detail-row">
              <span class="label">Name</span>
              <span class="value">{{
                selectedPayroll.employee?.full_name
              }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Employee ID</span>
              <span class="value">{{
                selectedPayroll.employee?.employee_id
              }}</span>
            </div>
          </div>
          <div class="detail-section">
            <h3>Payroll Period</h3>
            <div class="detail-row">
              <span class="label">Start Date</span>
              <span class="value">{{
                formatDate(selectedPayroll.period_start)
              }}</span>
            </div>
            <div class="detail-row">
              <span class="label">End Date</span>
              <span class="value">{{
                formatDate(selectedPayroll.period_end)
              }}</span>
            </div>
          </div>
          <div class="detail-section">
            <h3>Salary Breakdown</h3>
            <div class="detail-row">
              <span class="label">Expected Work Days</span>
              <span class="value"
                >{{ selectedPayroll.expected_work_days }} days</span
              >
            </div>
            <div class="detail-row">
              <span class="label">Actual Work Days</span>
              <span class="value"
                >{{ selectedPayroll.actual_work_days }} days</span
              >
            </div>
            <div
              v-if="selectedPayroll.paid_leave_days > 0"
              class="detail-row success"
            >
              <span class="label">Paid Leave Days</span>
              <span class="value success-text"
                >+{{ selectedPayroll.paid_leave_days }} days</span
              >
            </div>
            <div
              v-if="selectedPayroll.unpaid_leave_days > 0"
              class="detail-row warning"
            >
              <span class="label">Unpaid Leave Days</span>
              <span class="value warning-text"
                >{{ selectedPayroll.unpaid_leave_days }} days</span
              >
            </div>
            <div
              v-if="selectedPayroll.absent_days > 0"
              class="detail-row error"
            >
              <span class="label">Absent Days</span>
              <span class="value error-text"
                >{{ selectedPayroll.absent_days }} days</span
              >
            </div>
            <div class="detail-row">
              <span class="label">Total Hours Worked</span>
              <span class="value"
                >{{ selectedPayroll.total_hours_worked }} hrs</span
              >
            </div>
            <div class="detail-row">
              <span class="label">Hourly Rate</span>
              <span class="value"
                >₱{{
                  Number(selectedPayroll.hourly_rate).toLocaleString()
                }}</span
              >
            </div>
            <div class="detail-row highlight">
              <span class="label">Gross Salary</span>
              <span class="value gross"
                >₱{{
                  Number(selectedPayroll.gross_salary).toLocaleString()
                }}</span
              >
            </div>
            <div
              v-if="selectedPayroll.deduction_amount > 0"
              class="detail-row error"
            >
              <span class="label">Total Deductions</span>
              <span class="value error-text"
                >-₱{{
                  Number(selectedPayroll.deduction_amount).toLocaleString()
                }}</span
              >
            </div>
            <div class="detail-row highlight-net">
              <span class="label">NET SALARY (Take Home)</span>
              <span class="value net">
                ₱{{
                  Number(
                    selectedPayroll.net_salary || selectedPayroll.gross_salary,
                  ).toLocaleString()
                }}
              </span>
            </div>
          </div>
          <div v-if="selectedPayroll.notes" class="detail-section">
            <h3>Notes</h3>
            <p class="notes">{{ selectedPayroll.notes }}</p>
          </div>
          <!-- Finance Rejection Notes -->
          <div v-if="selectedPayroll.finance_notes" class="detail-section rejection-notes-section">
            <h3>
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
              Rejection Reason
            </h3>
            <p class="rejection-notes">{{ selectedPayroll.finance_notes }}</p>
          </div>
          <!-- Pay action from modal -->
          <div
            v-if="selectedPayroll.status === 'approved'"
            class="modal-actions"
          >
            <button
              @click="
                openPayConfirmSingle(selectedPayroll);
                closeDetailsModal();
              "
              class="btn-modal-pay"
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
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path
                  d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"
                ></path>
              </svg>
              Process Payroll
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ───── Pay Confirmation Modal ───── -->
    <div v-if="showPayConfirm" class="modal-overlay" @click="closePayConfirm">
      <div class="modal modal-confirm" @click.stop>
        <div class="confirm-icon-wrap">
          <div class="confirm-icon">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="32"
              height="32"
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
          </div>
        </div>
        <div class="confirm-body">
          <h3 class="confirm-title">Confirm Payment</h3>

          <!-- Single payroll -->
          <p v-if="payConfirmTargets.length === 1" class="confirm-message">
            Are you sure you want to mark the payroll as
            <strong>Paid</strong> for
            <strong>{{ payConfirmTargets[0].employee?.full_name }}</strong>
            with a Net Salary of
            <strong class="amount-highlight">
              ₱{{
                Number(
                  payConfirmTargets[0].net_salary ||
                    payConfirmTargets[0].gross_salary,
                ).toLocaleString()
              }} </strong
            >?
          </p>

          <!-- Bulk payroll -->
          <div v-else>
            <p class="confirm-message">
              Are you sure you want to mark
              <strong>{{ payConfirmTargets.length }} payrolls</strong> as
              <strong>Paid</strong>?
            </p>
            <div class="confirm-list">
              <div
                v-for="p in payConfirmTargets"
                :key="p.id"
                class="confirm-list-item"
              >
                <div class="confirm-list-name">{{ p.employee?.full_name }}</div>
                <div class="confirm-list-amount">
                  ₱{{ Number(p.net_salary || p.gross_salary).toLocaleString() }}
                </div>
              </div>
              <div class="confirm-list-total">
                <span>Total</span>
                <span>₱{{ bulkTotalNet.toLocaleString() }}</span>
              </div>
            </div>
          </div>

          <p class="confirm-sub">This action cannot be undone.</p>

          <div class="confirm-actions">
            <button @click="closePayConfirm" class="btn-cancel">Cancel</button>
            <button
              @click="confirmPay"
              class="btn-confirm-pay"
              :disabled="isProcessing"
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
              {{ isProcessing ? "Processing…" : "Confirm & Pay" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import payrollApi from "../../../../services/payrollApi";
import employeeInfoService from "../../../../services/employeeInfoService";

const router = useRouter();

// ── State ─────────────────────────────────────────────────────────────────────
const isLoading = ref(false);
const isProcessing = ref(false);
const payrolls = ref([]);
const employees = ref([]);
const selectedIds = ref([]);
// Store selected payroll objects (for display in confirm modal)
const selectedPayrolls = ref([]);

const showDetailsModal = ref(false);
const selectedPayroll = ref(null);

// Pay confirmation modal
const showPayConfirm = ref(false);
const payConfirmTargets = ref([]); // payroll objects to pay

let searchTimer = null;

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

const filters = ref({
  search: "",
  status: "",
  month: "",
  year: "",
  per_page: 50,
});

const pagination = ref({
  total: 0,
  per_page: 50,
  current_page: 1,
  last_page: 1,
});

const summary = ref({
  total_payrolls: 0,
  unique_employees: 0,
  total_hours_worked: "0.00",
  total_net_salary: "0.00",
});

// ── Computed ──────────────────────────────────────────────────────────────────
const yearOptions = computed(() => {
  const y = new Date().getFullYear();
  return Array.from({ length: 6 }, (_, i) => y - i);
});

// Only approved rows are selectable
const approvedPayrolls = computed(() =>
  payrolls.value.filter((p) => p.status === "approved"),
);

const isAllApprovedSelected = computed(
  () =>
    approvedPayrolls.value.length > 0 &&
    approvedPayrolls.value.every((p) => selectedIds.value.includes(p.id)),
);

// True only when SOME (not all) approved rows are checked
const isSomeApprovedSelected = computed(
  () => selectedIds.value.length > 0 && !isAllApprovedSelected.value,
);

const bulkTotalNet = computed(() =>
  payConfirmTargets.value.reduce(
    (sum, p) => sum + Number(p.net_salary || p.gross_salary),
    0,
  ),
);

// ── Methods ───────────────────────────────────────────────────────────────────
async function loadPayrolls(page = 1) {
  try {
    isLoading.value = true;
    selectedIds.value = [];
    selectedPayrolls.value = [];

    const response = await payrollApi.getPayrolls({
      page,
      search: filters.value.search,
      status: filters.value.status,
      month: filters.value.month,
      year: filters.value.year,
      per_page: filters.value.per_page,
    });

    if (response.success) {
      payrolls.value = response.data;
      pagination.value = response.pagination;
    }
    await loadSummary();
  } catch {
    toast.error("Failed to load payroll records");
  } finally {
    isLoading.value = false;
  }
}

async function loadSummary() {
  try {
    const params = {
      search: filters.value.search,
      month: filters.value.month,
      year: filters.value.year || new Date().getFullYear(),
    };
    if (filters.value.status) {
      params.status = filters.value.status;
    }
    const response = await payrollApi.getSummary(params);
    if (response.success) summary.value = response.data;
  } catch {
    /* silent */
  }
}

async function loadEmployees() {
  try {
    const response = await employeeInfoService.getAll({ per_page: 1000 });
    if (response.success) employees.value = response.data;
  } catch {
    /* silent */
  }
}

// ── Search (debounced) ────────────────────────────────────────────────────────
function onSearchInput() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadPayrolls(1), 350);
}

function clearSearch() {
  filters.value.search = "";
  loadPayrolls(1);
}

// ── Selection (approved only) ─────────────────────────────────────────────────
function toggleSelect(payroll) {
  if (payroll.status !== "approved") return; // guard: never select disabled rows
  if (selectedIds.value.includes(payroll.id)) {
    selectedIds.value = selectedIds.value.filter((i) => i !== payroll.id);
    selectedPayrolls.value = selectedPayrolls.value.filter(
      (p) => p.id !== payroll.id,
    );
  } else {
    selectedIds.value.push(payroll.id);
    selectedPayrolls.value.push(payroll);
  }
}

function toggleSelectAllApproved() {
  if (isAllApprovedSelected.value) {
    // Deselect all approved
    const approvedIds = approvedPayrolls.value.map((p) => p.id);
    selectedIds.value = selectedIds.value.filter(
      (id) => !approvedIds.includes(id),
    );
    selectedPayrolls.value = selectedPayrolls.value.filter(
      (p) => !approvedIds.includes(p.id),
    );
  } else {
    // Select only approved rows — never touch disabled rows
    approvedPayrolls.value.forEach((p) => {
      if (!selectedIds.value.includes(p.id)) {
        selectedIds.value.push(p.id);
        selectedPayrolls.value.push(p);
      }
    });
  }
}

function clearSelection() {
  selectedIds.value = [];
  selectedPayrolls.value = [];
}

// ── Pay Confirmation ──────────────────────────────────────────────────────────
function openPayConfirm() {
  // Bulk: use currently selected payroll objects
  payConfirmTargets.value = [...selectedPayrolls.value];
  showPayConfirm.value = true;
}

function openPayConfirmSingle(payroll) {
  payConfirmTargets.value = [payroll];
  showPayConfirm.value = true;
}

function closePayConfirm() {
  showPayConfirm.value = false;
  payConfirmTargets.value = [];
}

async function confirmPay() {
  const ids = payConfirmTargets.value.map((p) => p.id);
  try {
    isProcessing.value = true;
    const response = await payrollApi.markAsPaid(ids);
    if (response.success) {
      toast.success(response.message || "Payroll(s) marked as Paid.");
      clearSelection();
      closePayConfirm();
      loadPayrolls(pagination.value.current_page);
    } else {
      toast.error(response.message || "Failed to process payment.");
    }
  } catch {
    toast.error("Failed to process payroll payment.");
  } finally {
    isProcessing.value = false;
  }
}

// ── Delete ─────────────────────────────────────────────────────────────────────
async function deletePayroll(payroll) {
  // Using custom confirm modal would be ideal; for delete we keep it simple
  if (!confirm(`Delete payroll for ${payroll.employee?.full_name}?`)) return;
  try {
    const response = await payrollApi.deletePayroll(payroll.id);
    if (response.success) {
      toast.success("Payroll deleted.");
      loadPayrolls(pagination.value.current_page);
    }
  } catch {
    toast.error("Failed to delete payroll.");
  }
}

// ── Misc ──────────────────────────────────────────────────────────────────────
function resetFilters() {
  filters.value = { search: "", status: "", month: "", year: "", per_page: 50 };
  loadPayrolls(1);
}

function goToPage(page) {
  loadPayrolls(page);
}

function viewDetails(payroll) {
  selectedPayroll.value = payroll;
  showDetailsModal.value = true;
}

function closeDetailsModal() {
  showDetailsModal.value = false;
  selectedPayroll.value = null;
}

function goToCreate() {
  router.push("/erp/hr/payroll/create");
}

// ── Helpers ───────────────────────────────────────────────────────────────────
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

function formatDateTime(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function statusLabel(status) {
  const map = {
    pending: "Pending",
    approved: "Approved",
    rejected: "Rejected",
    paid: "Paid",
  };
  return map[status] ?? status;
}

onMounted(() => {
  loadPayrolls();
  loadEmployees();
});
</script>

<style scoped>
.payroll-list-page {
  padding: 24px;
  background: #f8f9fa;
  min-height: 100vh;
}

/* ── Header ── */
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
  align-items: center;
}

.btn-create {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-create:hover {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.35);
}

.btn-pay {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-pay:hover {
  background: #469a69;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(66, 153, 225, 0.35);
}

/* ── Filters ── */
.filters-section {
  display: flex;
  gap: 14px;
  margin-bottom: 24px;
  align-items: flex-end;
  flex-wrap: wrap;
}
.filter-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
  min-width: 130px;
}
.search-group {
  flex: 2;
  min-width: 200px;
}
.filter-group label {
  font-size: 12px;
  font-weight: 600;
  color: #4a5568;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.input-icon-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}
.search-icon {
  position: absolute;
  left: 12px;
  color: #a0aec0;
  pointer-events: none;
}
.filter-input {
  width: 100%;
  padding: 11px 36px 11px 38px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  color: #2d3748;
  background: #fff;
  transition: all 0.2s;
}
.filter-input:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}
.btn-clear-input {
  position: absolute;
  right: 10px;
  background: none;
  border: none;
  color: #a0aec0;
  cursor: pointer;
  padding: 2px;
  display: flex;
  align-items: center;
}
.btn-clear-input:hover {
  color: #4a5568;
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
  background: #fff;
  cursor: pointer;
  appearance: none;
  transition: all 0.2s;
}
.select-wrapper select:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
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
  background: #fff;
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

/* ── Summary Cards ── */
.summary-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}
.summary-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px;
  background: #fff;
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
.card-icon.blue {
  background: #dbeafe;
  color: #1e40af;
}
.card-icon.yellow {
  background: #fef5e7;
  color: #c27803;
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

/* ── Loading ── */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  background: #fff;
  border-radius: 12px;
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

/* ── Bulk bar ── */
.bulk-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 18px;
  background: #ebf8ff;
  border-bottom: 1px solid #bee3f8;
  font-size: 13px;
  color: #2b6cb0;
  font-weight: 600;
}
.btn-clear-sel {
  background: none;
  border: none;
  color: #48bb78;
  font-size: 13px;
  cursor: pointer;
  font-weight: 600;
  text-decoration: underline;
}

/* ── Table ── */
.table-container {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
}
.payroll-table {
  width: 100%;
  border-collapse: collapse;
}
.payroll-table th {
  padding: 14px 18px;
  text-align: left;
  font-size: 10px;
  font-weight: 700;
  color: #a0aec0;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  border-bottom: 1px solid #e2e8f0;
}
.th-check,
.td-check {
  width: 44px;
  text-align: center !important;
  padding: 0 8px !important;
}
.payroll-table tbody tr {
  border-bottom: 1px solid #f7fafc;
  transition: background 0.15s;
}
.payroll-table tbody tr:hover {
  background: #f7fafc;
}
.payroll-table tbody tr.row-selected {
  background: #ebf8ff !important;
}
.payroll-table td {
  padding: 14px 18px;
  font-size: 14px;
  color: #2d3748;
}

.custom-checkbox {
  border: 1px solid #48bb78;
  width: 16px;
  height: 16px;
  accent-color: #48bb78;
  cursor: pointer;
}

.custom-checkbox:disabled {
  border: 1px solid #48bb78;
  cursor: not-allowed;
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

.employee-name {
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 2px;
}

.employee-id {
  font-size: 12px;
  color: #a0aec0;
}

.period-cell {
  font-size: 13px;
  color: #4a5568;
}

.salary-type-badge {
  display: inline-block;
  padding: 5px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}
.salary-type-badge.daily {
  background: #e0f2f7;
  color: #0288d1;
}
.salary-type-badge.weekly {
  background: #f3e5f5;
  color: #7b1fa2;
}
.salary-type-badge.monthly {
  background: #e8f5e9;
  color: #2e7d32;
}

.status-badge {
  display: inline-block;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.3px;
}
.status-badge.pending {
  background: #fef3c7;
  color: #92400e;
}
.status-badge.approved {
  background: #c6f6d5;
  color: #22543d;
}
.status-badge.rejected {
  background: #fed7e2;
  color: #9b2c2c;
}
.status-badge.paid {
  background: #d1fae5;
  color: #065f46;
}

.hours-cell {
  font-weight: 600;
  color: #2d3748;
}
.amount-cell {
  font-weight: 700;
  color: #48bb78;
  font-size: 15px;
}

.action-cell {
  display: flex;
  gap: 6px;
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
.btn-action.view:hover {
  color: #48bb78;
  background: #ebf8ff;
}
.btn-action.pay:hover {
  color: #48bb78;
  background: #f0fff4;
}
.btn-action.delete:hover {
  color: #e53e3e;
  background: #fff5f5;
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
  margin: 0 0 16px 0;
  font-size: 14px;
  color: #a0aec0;
}
.btn-create-first {
  padding: 10px 20px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
.btn-create-first:hover {
  background: #38a169;
}

/* ── Pagination bar ── */
.pagination-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-top: 1px solid #e2e8f0;
  gap: 12px;
  flex-wrap: wrap;
}
.record-count {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
}
.pagination {
  display: flex;
  align-items: center;
  gap: 12px;
}
.page-btn {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  width: 32px;
  height: 32px;
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
}
.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.page-info {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
}

/* ── Modals shared ── */
.modal-overlay {
  position: fixed;
  inset: 0;
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
  background: #fff;
  border-radius: 14px;
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  animation: slideUp 0.25s;
}
@keyframes slideUp {
  from {
    transform: translateY(24px);
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
  padding: 28px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 28px;
}

/* ── Details modal content ── */
.detail-section {
  padding-bottom: 24px;
  border-bottom: 1px solid #f0f4f8;
}
.detail-section:last-child {
  border-bottom: none;
  padding-bottom: 0;
}
.detail-section h3 {
  font-size: 14px;
  font-weight: 700;
  color: #2d3748;
  margin: 0 0 16px 0;
}
.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f0f4f8;
}
.detail-row:last-child {
  border-bottom: none;
}
.detail-row.highlight {
  background: #ebf8ff;
  padding: 12px 16px;
  border-radius: 8px;
  margin-top: 6px;
}
.detail-row.highlight-net {
  background: #c6f6d5;
  padding: 12px 16px;
  border-radius: 8px;
  margin-top: 6px;
}
.detail-row.success {
  background: #f0fff4;
  padding: 10px 14px;
  border-radius: 8px;
  margin: 6px 0;
  border-left: 3px solid #48bb78;
}
.detail-row.warning {
  background: #fffaf0;
  padding: 10px 14px;
  border-radius: 8px;
  margin: 6px 0;
  border-left: 3px solid #ed8936;
}
.detail-row.error {
  background: #fff5f5;
  padding: 10px 14px;
  border-radius: 8px;
  margin: 6px 0;
  border-left: 3px solid #e53e3e;
}
.label {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
}
.value {
  font-size: 14px;
  color: #2d3748;
  font-weight: 600;
}
.value.gross {
  font-size: 17px;
  color: #48bb78;
}
.value.net {
  font-size: 20px;
  color: #48bb78;
  font-weight: 700;
}
.success-text {
  color: #22543d;
  font-weight: 700;
}
.warning-text {
  color: #c27803;
  font-weight: 700;
}
.error-text {
  color: #9b2c2c;
  font-weight: 700;
}
.notes {
  font-size: 14px;
  color: #4a5568;
  line-height: 1.6;
  margin: 0;
}

.modal-actions {
  display: flex;
  gap: 12px;
}
.btn-modal-pay {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 20px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-modal-pay:hover {
  background: #469a69;
}

/* ── Pay Confirm Modal ── */
.modal-confirm {
  max-width: 460px;
  border-radius: 16px;
}
.confirm-icon-wrap {
  display: flex;
  justify-content: center;
  padding: 28px 24px 0;
}
.confirm-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, #48bb78, #469a69);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 8px 24px rgba(66, 153, 225, 0.35);
}
.confirm-body {
  padding: 20px 28px 28px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.confirm-title {
  font-size: 20px;
  font-weight: 700;
  color: #2d3748;
  margin: 0;
  text-align: center;
}
.confirm-message {
  font-size: 15px;
  color: #4a5568;
  line-height: 1.7;
  margin: 0;
  text-align: center;
}
.amount-highlight {
  color: #48bb78;
  font-size: 17px;
}
.confirm-sub {
  font-size: 12px;
  color: #a0aec0;
  text-align: center;
  margin: 0;
}

.confirm-list {
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  font-size: 13px;
}
.confirm-list-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-bottom: 1px solid #e2e8f0;
}
.confirm-list-name {
  font-weight: 500;
  color: #2d3748;
}
.confirm-list-amount {
  font-weight: 700;
  color: #48bb78;
}
.confirm-list-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  background: #edf2f7;
  font-size: 13px;
  font-weight: 700;
  color: #2d3748;
}

.confirm-actions {
  display: flex;
  gap: 12px;
  margin-top: 4px;
}
.btn-cancel {
  flex: 1;
  padding: 12px;
  background: #f7fafc;
  color: #4a5568;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-cancel:hover {
  background: #e2e8f0;
}
.btn-confirm-pay {
  flex: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-confirm-pay:hover:not(:disabled) {
  background: #469a69;
  box-shadow: 0 4px 16px rgba(66, 153, 225, 0.4);
}
.btn-confirm-pay:disabled {
  background: #469a69;
  opacity: 0.6;
  cursor: not-allowed;
}


/* ── Rejection Notes ── */
.rejection-notes-section {
  background: #fff5f5;
  border: 1px solid #fed7d7;
  border-radius: 10px;
  padding: 16px;
}
.rejection-notes-section h3 {
  color: #c53030;
  display: flex;
  align-items: center;
}
.rejection-notes {
  margin: 8px 0 0;
  font-size: 14px;
  color: #742a2a;
  line-height: 1.6;
  white-space: pre-wrap;
}

/* ── Responsive ── */
@media (max-width: 1200px) {
  .summary-cards {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 768px) {
  .payroll-list-page {
    padding: 16px;
  }
  .page-header {
    flex-direction: column;
    gap: 16px;
  }
  .filters-section {
    flex-direction: column;
  }
  .summary-cards {
    grid-template-columns: 1fr;
  }
}
</style>
