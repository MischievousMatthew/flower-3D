<template>
  <div class="purchase-requests">
    

    <div class="requests-content">
      <!-- Header -->
      <div class="page-header">
        <h1 class="page-title">Purchase Requests</h1>

        <div class="header-actions">
          <div class="tabs">
            <button
              :class="{ active: activeTab === 'all' }"
              @click="activeTab = 'all'"
            >
              All requests
            </button>
            <button
              :class="{ active: activeTab === 'flagged' }"
              @click="activeTab = 'flagged'"
            >
              Flagged requests
            </button>
          </div>

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
              placeholder="Search content"
              v-model="searchQuery"
            />
          </div>
        </div>
      </div>

      <!-- Filters Bar -->
      <div class="filters-bar">
        <button class="filter-chip active" @click="toggleFilter('today')">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
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
          Today
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

        <button class="filter-chip active" @click="toggleFilter('project')">
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
              d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"
            ></path>
          </svg>
          Project
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

        <button class="filter-btn">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <polygon
              points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"
            ></polygon>
          </svg>
        </button>

        <button class="filter-btn">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <line x1="4" y1="21" x2="4" y2="14"></line>
            <line x1="4" y1="10" x2="4" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12" y2="3"></line>
            <line x1="20" y1="21" x2="20" y2="16"></line>
            <line x1="20" y1="12" x2="20" y2="3"></line>
            <line x1="1" y1="14" x2="7" y2="14"></line>
            <line x1="9" y1="8" x2="15" y2="8"></line>
            <line x1="17" y1="16" x2="23" y2="16"></line>
          </svg>
        </button>

        <div class="results-info">
          <span class="results-count"
            >{{ filteredRequests.length }} requests</span
          >
          <span class="results-filter">gandalf | ...</span>
        </div>
      </div>

      <!-- Requests Table -->
      <div class="table-container">
        <table class="requests-table">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Request Info</th>
              <th>Direction</th>
              <th>Financial Check</th>
              <th>Stock Status</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="request in filteredRequests"
              :key="request.id"
              @click="selectRequest(request)"
              :class="{ selected: selectedRequest?.id === request.id }"
            >
              <td class="timestamp-cell">{{ request.timestamp }}</td>
              <td class="info-cell">
                <div class="request-info">
                  <div
                    class="product-icon"
                    :style="{ background: request.iconColor }"
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
                      <path
                        d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z"
                      ></path>
                      <path d="M12 6v6l4 2"></path>
                    </svg>
                  </div>
                  <div class="info-text">
                    <span class="product-name">{{ request.productName }}</span>
                    <span class="request-id">{{ request.requestId }}</span>
                  </div>
                </div>
              </td>
              <td>
                <span
                  class="direction-badge"
                  :class="request.direction.toLowerCase()"
                >
                  <svg
                    v-if="request.direction === 'Input'"
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <line x1="12" y1="19" x2="12" y2="5"></line>
                    <polyline points="5 12 12 5 19 12"></polyline>
                  </svg>
                  <svg
                    v-else-if="request.direction === 'Output'"
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <polyline points="19 12 12 19 5 12"></polyline>
                  </svg>
                  <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                  </svg>
                  {{ request.direction }}
                </span>
              </td>
              <td>
                <div class="financial-indicators">
                  <span
                    v-for="indicator in request.financialFlags"
                    :key="indicator.type"
                    class="indicator-badge"
                    :class="indicator.type"
                  >
                    {{ indicator.label }}
                  </span>
                </div>
              </td>
              <td>
                <div class="stock-status">
                  <span
                    v-if="request.stockStatus === 'sufficient'"
                    class="status-icon success"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="14"
                      height="14"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2.5"
                    >
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Sufficient
                  </span>
                  <span
                    v-else-if="request.stockStatus === 'low'"
                    class="status-icon warning"
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
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="8" x2="12" y2="12"></line>
                      <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    Low Stock
                  </span>
                  <span v-else class="status-icon error">
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
                      <line x1="15" y1="9" x2="9" y2="15"></line>
                      <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    Out of Stock
                  </span>
                </div>
              </td>
              <td>
                <span
                  class="status-badge"
                  :class="
                    request.approvalStatus.toLowerCase().replace(' ', '-')
                  "
                >
                  {{ request.approvalStatus }}
                </span>
              </td>
              <td class="action-cell" @click.stop>
                <button class="action-btn" @click="toggleMenu(request.id)">
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
                <div v-if="openMenuId === request.id" class="dropdown-menu">
                  <button @click="selectRequest(request)">View Details</button>
                  <button
                    v-if="request.approvalStatus === 'Pending'"
                    @click="openApprovalModal(request)"
                  >
                    Review Request
                  </button>
                  <button @click="editRequest(request)">Edit</button>
                  <button class="delete-btn" @click="deleteRequest(request)">
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Right Sidebar - Request Details -->
    <transition name="slide">
      <div v-if="selectedRequest" class="request-sidebar">
        <button class="close-sidebar" @click="selectedRequest = null">
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

        <div class="sidebar-content">
          <div class="request-header">
            <div
              class="product-icon-large"
              :style="{ background: selectedRequest.iconColor }"
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
                  d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z"
                ></path>
              </svg>
            </div>
            <h2>{{ selectedRequest.productName }}</h2>
            <p class="request-id-large">{{ selectedRequest.requestId }}</p>
            <span
              class="status-badge large"
              :class="
                selectedRequest.approvalStatus.toLowerCase().replace(' ', '-')
              "
            >
              {{ selectedRequest.approvalStatus }}
            </span>
          </div>

          <div
            class="action-buttons"
            v-if="selectedRequest.approvalStatus === 'Pending'"
          >
            <button
              class="approve-btn"
              @click="openApprovalModal(selectedRequest)"
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
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              Review Request
            </button>
          </div>

          <div class="tabs">
            <button
              :class="{ active: detailTab === 'basic' }"
              @click="detailTab = 'basic'"
            >
              Basic Info
            </button>
            <button
              :class="{ active: detailTab === 'financial' }"
              @click="detailTab = 'financial'"
            >
              Financial
            </button>
            <button
              :class="{ active: detailTab === 'stock' }"
              @click="detailTab = 'stock'"
            >
              Stock
            </button>
          </div>

          <!-- Basic Info Tab -->
          <div v-if="detailTab === 'basic'" class="tab-content">
            <div class="info-section">
              <h3>Core Identification</h3>
              <div class="info-row">
                <span class="label">Request ID</span>
                <span class="value">{{ selectedRequest.requestId }}</span>
              </div>
              <div class="info-row">
                <span class="label">Source Order ID</span>
                <span class="value">{{ selectedRequest.sourceOrderId }}</span>
              </div>
              <div class="info-row">
                <span class="label">Request Date</span>
                <span class="value">{{ selectedRequest.requestDate }}</span>
              </div>
              <div class="info-row">
                <span class="label">Requested By</span>
                <span class="value">{{ selectedRequest.requestedBy }}</span>
              </div>
              <div class="info-row">
                <span class="label">Department</span>
                <span class="value">{{ selectedRequest.department }}</span>
              </div>
              <div class="info-row">
                <span class="label">Priority Level</span>
                <span class="value">
                  <span
                    class="priority-badge"
                    :class="selectedRequest.priority.toLowerCase()"
                  >
                    {{ selectedRequest.priority }}
                  </span>
                </span>
              </div>
            </div>

            <div class="info-section">
              <h3>Product & Quantity</h3>
              <div class="info-row">
                <span class="label">Product Name</span>
                <span class="value">{{ selectedRequest.productName }}</span>
              </div>
              <div class="info-row">
                <span class="label">Flower Type</span>
                <span class="value">{{ selectedRequest.flowerType }}</span>
              </div>
              <div class="info-row">
                <span class="label">Variant</span>
                <span class="value">{{ selectedRequest.variant }}</span>
              </div>
              <div class="info-row">
                <span class="label">Requested Quantity</span>
                <span class="value font-bold"
                  >{{ selectedRequest.requestedQty }}
                  {{ selectedRequest.uom }}</span
                >
              </div>
              <div class="info-row">
                <span class="label">Shelf Life</span>
                <span class="value">{{ selectedRequest.shelfLife }} days</span>
              </div>
              <div class="info-row">
                <span class="label">Expected Waste</span>
                <span class="value">{{ selectedRequest.expectedWaste }}%</span>
              </div>
              <div class="info-row">
                <span class="label">Delivery Date</span>
                <span class="value">{{ selectedRequest.deliveryDate }}</span>
              </div>
            </div>
          </div>

          <!-- Financial Tab -->
          <div v-if="detailTab === 'financial'" class="tab-content">
            <div class="info-section">
              <h3>Cost Analysis</h3>
              <div class="info-row">
                <span class="label">Estimated Unit Cost</span>
                <span class="value"
                  >₱{{ selectedRequest.unitCost?.toLocaleString() }}</span
                >
              </div>
              <div class="info-row">
                <span class="label">Total Estimated Cost</span>
                <span class="value font-bold"
                  >₱{{ selectedRequest.totalCost?.toLocaleString() }}</span
                >
              </div>
              <div class="info-row">
                <span class="label">Expected Selling Price</span>
                <span class="value"
                  >₱{{ selectedRequest.sellingPrice?.toLocaleString() }}</span
                >
              </div>
              <div class="info-row">
                <span class="label">Expected Margin</span>
                <span
                  class="value"
                  :class="
                    selectedRequest.margin < 20
                      ? 'text-warning'
                      : 'text-success'
                  "
                >
                  {{ selectedRequest.margin }}%
                </span>
              </div>
              <div class="info-row">
                <span class="label">Break-Even Quantity</span>
                <span class="value">{{ selectedRequest.breakEven }} stems</span>
              </div>
            </div>

            <div class="info-section">
              <h3>Budget Status</h3>
              <div class="info-row">
                <span class="label">Available Cash Balance</span>
                <span class="value"
                  >₱{{ selectedRequest.cashBalance?.toLocaleString() }}</span
                >
              </div>
              <div class="info-row">
                <span class="label">Monthly Budget (Flowers)</span>
                <span class="value"
                  >₱{{ selectedRequest.monthlyBudget?.toLocaleString() }}</span
                >
              </div>
              <div class="info-row">
                <span class="label">Remaining Budget</span>
                <span
                  class="value"
                  :class="
                    selectedRequest.remainingBudget < selectedRequest.totalCost
                      ? 'text-error'
                      : 'text-success'
                  "
                >
                  ₱{{ selectedRequest.remainingBudget?.toLocaleString() }}
                </span>
              </div>
            </div>

            <div
              class="warning-box"
              v-if="
                selectedRequest.financialFlags &&
                selectedRequest.financialFlags.length > 0
              "
            >
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
                  d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"
                ></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
              </svg>
              <div class="warning-content">
                <h4>Financial Warnings</h4>
                <ul>
                  <li
                    v-for="flag in selectedRequest.financialFlags"
                    :key="flag.type"
                  >
                    {{ flag.label }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Stock Tab -->
          <div v-if="detailTab === 'stock'" class="tab-content">
            <div class="info-section">
              <h3>Stock Context</h3>
              <div class="info-row">
                <span class="label">Current Stock Level</span>
                <span class="value"
                  >{{ selectedRequest.currentStock }} stems</span
                >
              </div>
              <div class="info-row">
                <span class="label">Incoming Stock (POs)</span>
                <span class="value"
                  >{{ selectedRequest.incomingStock }} stems</span
                >
              </div>
              <div class="info-row">
                <span class="label">Reserved Quantity</span>
                <span class="value"
                  >{{ selectedRequest.reservedQty }} stems</span
                >
              </div>
              <div class="info-row">
                <span class="label">Stock Shortage</span>
                <span class="value font-bold text-error"
                  >{{ selectedRequest.stockShortage }} stems</span
                >
              </div>
            </div>

            <div
              class="info-section"
              v-if="selectedRequest.approvalStatus !== 'Pending'"
            >
              <h3>Approval Details</h3>
              <div class="info-row">
                <span class="label">Approved Quantity</span>
                <span class="value font-bold"
                  >{{ selectedRequest.approvedQty || "-" }}
                  {{ selectedRequest.uom }}</span
                >
              </div>
              <div class="info-row">
                <span class="label">Approved Budget</span>
                <span class="value"
                  >₱{{
                    selectedRequest.approvedBudget?.toLocaleString() || "-"
                  }}</span
                >
              </div>
              <div class="info-row">
                <span class="label">Approved By</span>
                <span class="value">{{
                  selectedRequest.approvedBy || "-"
                }}</span>
              </div>
              <div class="info-row">
                <span class="label">Approval Date</span>
                <span class="value">{{
                  selectedRequest.approvalDate || "-"
                }}</span>
              </div>
              <div class="info-row" v-if="selectedRequest.disapprovalReason">
                <span class="label">Reason</span>
                <span class="value">{{
                  selectedRequest.disapprovalReason
                }}</span>
              </div>
              <div class="info-row" v-if="selectedRequest.disapprovalNotes">
                <span class="label">Notes</span>
                <span class="value">{{
                  selectedRequest.disapprovalNotes
                }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Approval/Rejection Modal -->
    <div
      class="modal-overlay"
      v-if="showApprovalModal"
      @click="closeApprovalModal"
    >
      <div class="modal approval-modal" @click.stop>
        <div class="modal-header">
          <h2>Review Purchase Request</h2>
          <button class="close-btn" @click="closeApprovalModal">
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

        <div class="modal-body">
          <div class="request-summary">
            <h3>{{ approvalForm.productName }}</h3>
            <p>{{ approvalForm.requestId }}</p>
            <div class="summary-grid">
              <div class="summary-item">
                <span class="summary-label">Requested</span>
                <span class="summary-value"
                  >{{ approvalForm.requestedQty }} {{ approvalForm.uom }}</span
                >
              </div>
              <div class="summary-item">
                <span class="summary-label">Total Cost</span>
                <span class="summary-value"
                  >₱{{ approvalForm.totalCost?.toLocaleString() }}</span
                >
              </div>
              <div class="summary-item">
                <span class="summary-label">Margin</span>
                <span
                  class="summary-value"
                  :class="
                    approvalForm.margin < 20 ? 'text-warning' : 'text-success'
                  "
                >
                  {{ approvalForm.margin }}%
                </span>
              </div>
            </div>
          </div>

          <div class="decision-section">
            <h4>Approval Decision</h4>
            <div class="decision-buttons">
              <button
                class="decision-btn approve"
                :class="{ active: approvalForm.decision === 'approve' }"
                @click="approvalForm.decision = 'approve'"
              >
                <svg
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
                Approve
              </button>
              <button
                class="decision-btn reject"
                :class="{ active: approvalForm.decision === 'reject' }"
                @click="approvalForm.decision = 'reject'"
              >
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
                Reject
              </button>
            </div>
          </div>

          <div class="form-section">
            <div class="form-row">
              <div class="form-group">
                <label>Approved Quantity *</label>
                <div class="input-with-unit">
                  <input
                    type="number"
                    v-model="approvalForm.approvedQty"
                    :max="approvalForm.requestedQty"
                    :disabled="approvalForm.decision === 'reject'"
                  />
                  <span class="unit">{{ approvalForm.uom }}</span>
                </div>
              </div>
              <div class="form-group">
                <label>Approved Budget *</label>
                <div class="input-with-unit">
                  <span class="currency">₱</span>
                  <input
                    type="number"
                    v-model="approvalForm.approvedBudget"
                    :disabled="approvalForm.decision === 'reject'"
                  />
                </div>
              </div>
            </div>

            <div class="form-group" v-if="approvalForm.decision === 'reject'">
              <label>Disapproval Reason *</label>
              <select v-model="approvalForm.disapprovalReason" required>
                <option value="">Select reason</option>
                <option value="Insufficient Cash">Insufficient Cash</option>
                <option value="Budget Exceeded">Budget Exceeded</option>
                <option value="Low Profit Margin">Low Profit Margin</option>
                <option value="Overstock Risk">Overstock Risk</option>
                <option value="High Spoilage Risk">High Spoilage Risk</option>
                <option value="Supplier Price Too High">
                  Supplier Price Too High
                </option>
                <option value="Seasonal Demand Drop">
                  Seasonal Demand Drop
                </option>
                <option value="Duplicate Request">Duplicate Request</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="form-group">
              <label>{{
                approvalForm.decision === "reject"
                  ? "Disapproval Notes *"
                  : "Approval Notes (Optional)"
              }}</label>
              <textarea
                v-model="approvalForm.notes"
                rows="4"
                :placeholder="
                  approvalForm.decision === 'reject'
                    ? 'Please provide detailed reason for rejection...'
                    : 'Add any additional notes or conditions...'
                "
                :required="approvalForm.decision === 'reject'"
              ></textarea>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Required Supplier (Optional)</label>
                <input
                  type="text"
                  v-model="approvalForm.requiredSupplier"
                  placeholder="e.g., ABC Flowers Inc."
                />
              </div>
              <div class="form-group">
                <label>Price Ceiling (Optional)</label>
                <div class="input-with-unit">
                  <span class="currency">₱</span>
                  <input
                    type="number"
                    v-model="approvalForm.priceCeiling"
                    placeholder="0.00"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" @click="closeApprovalModal">Cancel</button>
          <button
            class="btn-submit"
            @click="submitApproval"
            :disabled="!approvalForm.decision"
          >
            {{
              approvalForm.decision === "approve"
                ? "Approve Request"
                : "Reject Request"
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";


const activeTab = ref("all");
const searchQuery = ref("");
const selectedRequest = ref(null);
const detailTab = ref("basic");
const openMenuId = ref(null);
const showApprovalModal = ref(false);

const approvalForm = ref({
  decision: null,
  approvedQty: 0,
  approvedBudget: 0,
  disapprovalReason: "",
  notes: "",
  requiredSupplier: "",
  priceCeiling: null,
});

const requests = ref([
  {
    id: 1,
    timestamp: "2024-01-06 09:20:00",
    productName: "Red Roses",
    requestId: "PR-2024-001",
    iconColor: "#fc8181",
    direction: "Input",
    financialFlags: [
      { type: "warning", label: "Low Margin 18%" },
      { type: "info", label: "Budget OK" },
    ],
    stockStatus: "low",
    approvalStatus: "Pending",
    sourceOrderId: "SO-2024-156",
    requestDate: "2024-01-06",
    requestedBy: "John Doe",
    department: "Procurement",
    priority: "Urgent",
    flowerType: "Fresh",
    variant: "Long Stem",
    requestedQty: 500,
    uom: "Stems",
    shelfLife: 5,
    expectedWaste: 10,
    deliveryDate: "2024-01-15",
    unitCost: 25,
    totalCost: 12500,
    sellingPrice: 35,
    margin: 18,
    breakEven: 360,
    cashBalance: 250000,
    monthlyBudget: 100000,
    remainingBudget: 45000,
    currentStock: 50,
    incomingStock: 200,
    reservedQty: 100,
    stockShortage: 250,
  },
  {
    id: 2,
    timestamp: "2024-01-06 09:19:00",
    productName: "White Lilies",
    requestId: "PR-2024-002",
    iconColor: "#48bb78",
    direction: "Input",
    financialFlags: [{ type: "success", label: "Good Margin 32%" }],
    stockStatus: "sufficient",
    approvalStatus: "Approved",
    sourceOrderId: "SO-2024-157",
    requestDate: "2024-01-06",
    requestedBy: "Jane Smith",
    department: "Procurement",
    priority: "Normal",
    flowerType: "Fresh",
    variant: "Standard",
    requestedQty: 300,
    uom: "Stems",
    shelfLife: 7,
    expectedWaste: 8,
    deliveryDate: "2024-01-18",
    unitCost: 30,
    totalCost: 9000,
    sellingPrice: 45,
    margin: 32,
    breakEven: 200,
    cashBalance: 250000,
    monthlyBudget: 100000,
    remainingBudget: 45000,
    currentStock: 150,
    incomingStock: 100,
    reservedQty: 50,
    stockShortage: 100,
    approvedQty: 300,
    approvedBudget: 9000,
    approvedBy: "Sarah Johnson",
    approvalDate: "2024-01-06 10:30:00",
  },
  {
    id: 3,
    timestamp: "2024-01-06 09:18:00",
    productName: "Pink Carnations",
    requestId: "PR-2024-003",
    iconColor: "#f687b3",
    direction: "Output",
    financialFlags: [
      { type: "error", label: "Margin < 15%" },
      { type: "warning", label: "High Waste Risk" },
    ],
    stockStatus: "out",
    approvalStatus: "Rejected",
    sourceOrderId: "SO-2024-158",
    requestDate: "2024-01-06",
    requestedBy: "Mike Wilson",
    department: "Procurement",
    priority: "Perishable",
    flowerType: "Fresh",
    variant: "Short Stem",
    requestedQty: 800,
    uom: "Stems",
    shelfLife: 3,
    expectedWaste: 15,
    deliveryDate: "2024-01-10",
    unitCost: 18,
    totalCost: 14400,
    sellingPrice: 22,
    margin: 14,
    breakEven: 655,
    cashBalance: 250000,
    monthlyBudget: 100000,
    remainingBudget: 45000,
    currentStock: 0,
    incomingStock: 0,
    reservedQty: 0,
    stockShortage: 800,
    approvedQty: 0,
    approvedBudget: 0,
    approvedBy: "Sarah Johnson",
    approvalDate: "2024-01-06 11:00:00",
    disapprovalReason: "Low Profit Margin",
    disapprovalNotes:
      "Margin below 15% threshold. High spoilage risk due to 3-day shelf life. Recommend sourcing alternative suppliers with better pricing.",
  },
]);

const filteredRequests = computed(() => {
  let filtered = requests.value;

  if (activeTab.value === "flagged") {
    filtered = filtered.filter((r) =>
      r.financialFlags.some((f) => f.type === "warning" || f.type === "error"),
    );
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(
      (r) =>
        r.productName.toLowerCase().includes(query) ||
        r.requestId.toLowerCase().includes(query) ||
        r.requestedBy.toLowerCase().includes(query),
    );
  }

  return filtered;
});

const selectRequest = (request) => {
  selectedRequest.value = request;
  detailTab.value = "basic";
  openMenuId.value = null;
};

const toggleMenu = (id) => {
  openMenuId.value = openMenuId.value === id ? null : id;
};

const toggleFilter = (filter) => {
  console.log("Toggle filter:", filter);
};

const openApprovalModal = (request) => {
  approvalForm.value = {
    ...request,
    decision: null,
    approvedQty: request.requestedQty,
    approvedBudget: request.totalCost,
    disapprovalReason: "",
    notes: "",
    requiredSupplier: "",
    priceCeiling: null,
  };
  showApprovalModal.value = true;
  openMenuId.value = null;
};

const closeApprovalModal = () => {
  showApprovalModal.value = false;
  approvalForm.value = {
    decision: null,
    approvedQty: 0,
    approvedBudget: 0,
    disapprovalReason: "",
    notes: "",
    requiredSupplier: "",
    priceCeiling: null,
  };
};

const submitApproval = () => {
  const request = requests.value.find((r) => r.id === approvalForm.value.id);
  if (request) {
    if (approvalForm.value.decision === "approve") {
      request.approvalStatus = "Approved";
      request.approvedQty = approvalForm.value.approvedQty;
      request.approvedBudget = approvalForm.value.approvedBudget;
    } else {
      request.approvalStatus = "Rejected";
      request.approvedQty = 0;
      request.approvedBudget = 0;
      request.disapprovalReason = approvalForm.value.disapprovalReason;
      request.disapprovalNotes = approvalForm.value.notes;
    }
    request.approvedBy = "Current User";
    request.approvalDate = new Date()
      .toISOString()
      .slice(0, 19)
      .replace("T", " ");

    if (selectedRequest.value?.id === request.id) {
      selectedRequest.value = { ...request };
    }
  }
  closeApprovalModal();
};

const editRequest = (request) => {
  console.log("Edit request:", request);
  openMenuId.value = null;
};

const deleteRequest = (request) => {
  if (confirm(`Are you sure you want to delete ${request.requestId}?`)) {
    const index = requests.value.findIndex((r) => r.id === request.id);
    if (index !== -1) {
      requests.value.splice(index, 1);
      if (selectedRequest.value?.id === request.id) {
        selectedRequest.value = null;
      }
    }
  }
  openMenuId.value = null;
};
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.purchase-requests {
  display: flex;
  min-height: 100vh;
  background: #f7fafc;
  font-family: "Poppins", sans-serif;
}

.requests-content {
  flex: 1;
  padding: 32px 40px;
  transition: margin-right 0.3s ease;
}

.requests-content:has(~ .request-sidebar) {
  margin-right: 420px;
}

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.page-title {
  font-size: 32px;
  font-weight: 700;
  color: #2d3748;
  letter-spacing: -0.5px;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}

.tabs {
  display: flex;
  gap: 4px;
  padding: 4px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.tabs button {
  padding: 8px 20px;
  background: transparent;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #718096;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}

.tabs button.active {
  background: #48bb78;
  color: white;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.25);
}

.search-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  min-width: 300px;
  transition: all 0.2s;
}

.search-box:focus-within {
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

.search-box svg {
  color: #a0aec0;
  flex-shrink: 0;
}

.search-box input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  color: #2d3748;
  font-family: "Poppins", sans-serif;
}

.search-box input::placeholder {
  color: #a0aec0;
}

/* Filters Bar */
.filters-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding: 16px 20px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}

.filter-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}

.filter-chip.active {
  background: #48bb78;
  border-color: #48bb78;
  color: white;
}

.filter-chip svg {
  flex-shrink: 0;
}

.filter-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
}

.filter-btn:hover {
  background: #e2e8f0;
  border-color: #cbd5e0;
}

.results-info {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
}

.results-count {
  color: #2d3748;
  font-weight: 600;
}

.results-filter {
  color: #718096;
}

/* Table */
.table-container {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}

.requests-table {
  width: 100%;
  border-collapse: collapse;
}

.requests-table thead {
  background: #f7fafc;
  border-bottom: 2px solid #e2e8f0;
}

.requests-table th {
  padding: 14px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 700;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.requests-table tbody tr {
  border-bottom: 1px solid #f7fafc;
  cursor: pointer;
  transition: all 0.2s;
}

.requests-table tbody tr:hover {
  background: #f7fafc;
}

.requests-table tbody tr.selected {
  background: #f0fff4;
}

.requests-table td {
  padding: 16px;
  font-size: 14px;
  color: #2d3748;
}

.timestamp-cell {
  font-family: "Poppins", monospace;
  font-size: 13px;
  color: #718096;
}

.info-cell {
  min-width: 220px;
}

.request-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.product-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
}

.info-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.product-name {
  font-weight: 600;
  color: #2d3748;
}

.request-id {
  font-size: 12px;
  color: #718096;
  font-family: "Poppins", monospace;
}

.direction-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}

.direction-badge.input {
  background: #bee3f8;
  color: #2c5282;
}

.direction-badge.output {
  background: #fed7e2;
  color: #702459;
}

.direction-badge.unknown {
  background: #e2e8f0;
  color: #4a5568;
}

.financial-indicators {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.indicator-badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}

.indicator-badge.success {
  background: #c6f6d5;
  color: #22543d;
}

.indicator-badge.warning {
  background: #feebc8;
  color: #7c2d12;
}

.indicator-badge.error {
  background: #fed7e2;
  color: #742a2a;
}

.indicator-badge.info {
  background: #bee3f8;
  color: #2c5282;
}

.stock-status {
  display: flex;
  align-items: center;
}

.status-icon {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
}

.status-icon.success {
  background: #c6f6d5;
  color: #22543d;
}

.status-icon.warning {
  background: #feebc8;
  color: #7c2d12;
}

.status-icon.error {
  background: #fed7e2;
  color: #742a2a;
}

.status-badge {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 16px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.3px;
}

.status-badge.pending {
  background: #feebc8;
  color: #7c2d12;
}

.status-badge.approved {
  background: #c6f6d5;
  color: #22543d;
}

.status-badge.rejected {
  background: #fed7e2;
  color: #742a2a;
}

.status-badge.large {
  padding: 8px 18px;
  font-size: 13px;
}

.action-cell {
  position: relative;
}

.action-btn {
  background: transparent;
  border: none;
  color: #a0aec0;
  cursor: pointer;
  padding: 6px;
  display: flex;
  align-items: center;
  border-radius: 6px;
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
  min-width: 180px;
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

/* Sidebar */
.request-sidebar {
  position: fixed;
  right: 0;
  top: 0;
  width: 420px;
  height: 100vh;
  background: #ffffff;
  border-left: 1px solid #e2e8f0;
  overflow-y: auto;
  z-index: 50;
  box-shadow: -4px 0 20px rgba(0, 0, 0, 0.06);
}

.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
}

.close-sidebar {
  position: absolute;
  top: 20px;
  right: 20px;
  background: #f7fafc;
  border: none;
  border-radius: 8px;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  color: #718096;
  z-index: 10;
}

.close-sidebar:hover {
  background: #e2e8f0;
  color: #2d3748;
}

.sidebar-content {
  padding: 32px 24px;
}

.request-header {
  text-align: center;
  margin-bottom: 24px;
  padding-bottom: 24px;
  border-bottom: 1px solid #f7fafc;
}

.product-icon-large {
  width: 80px;
  height: 80px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
  color: white;
}

.request-header h2 {
  font-size: 24px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 8px;
}

.request-id-large {
  font-size: 14px;
  color: #718096;
  font-family: "Poppins", monospace;
  font-weight: 500;
  margin-bottom: 12px;
  display: block;
}

.action-buttons {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
}

.approve-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 20px;
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

.approve-btn:hover {
  background: #38a169;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.3);
}

.tab-content {
  animation: fadeIn 0.3s;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.info-section {
  margin-bottom: 28px;
}

.info-section h3 {
  font-size: 13px;
  font-weight: 700;
  color: #718096;
  margin-bottom: 16px;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 12px 0;
  border-bottom: 1px solid #f7fafc;
}

.info-row:last-child {
  border-bottom: none;
}

.info-row .label {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
  flex: 0 0 45%;
}

.info-row .value {
  font-size: 14px;
  color: #2d3748;
  font-weight: 500;
  text-align: right;
  flex: 1;
}

.font-bold {
  font-weight: 700 !important;
}

.text-success {
  color: #38a169 !important;
}

.text-warning {
  color: #dd6b20 !important;
}

.text-error {
  color: #e53e3e !important;
}

.priority-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}

.priority-badge.urgent {
  background: #fed7e2;
  color: #742a2a;
}

.priority-badge.normal {
  background: #bee3f8;
  color: #2c5282;
}

.priority-badge.perishable {
  background: #feebc8;
  color: #7c2d12;
}

.warning-box {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #fffaf0;
  border: 1px solid #feebc8;
  border-left: 4px solid #dd6b20;
  border-radius: 10px;
  margin-top: 20px;
}

.warning-box svg {
  flex-shrink: 0;
  color: #dd6b20;
}

.warning-content h4 {
  font-size: 14px;
  font-weight: 700;
  color: #7c2d12;
  margin-bottom: 8px;
}

.warning-content ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.warning-content li {
  font-size: 13px;
  color: #7c2d12;
  padding: 4px 0;
  position: relative;
  padding-left: 16px;
}

.warning-content li::before {
  content: "•";
  position: absolute;
  left: 0;
  color: #dd6b20;
  font-weight: bold;
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
  font-size: 24px;
  font-weight: 700;
  color: #2d3748;
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
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.request-summary {
  padding: 20px;
  background: #f7fafc;
  border-radius: 12px;
}

.request-summary h3 {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 4px;
}

.request-summary p {
  font-size: 13px;
  color: #718096;
  font-family: "Poppins", monospace;
  margin-bottom: 16px;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.summary-label {
  font-size: 11px;
  color: #718096;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.summary-value {
  font-size: 16px;
  font-weight: 700;
  color: #2d3748;
}

.decision-section h4 {
  font-size: 14px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 12px;
}

.decision-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.decision-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px 24px;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  background: #ffffff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}

.decision-btn.approve {
  color: #38a169;
}

.decision-btn.approve.active {
  background: #48bb78;
  border-color: #48bb78;
  color: white;
}

.decision-btn.reject {
  color: #e53e3e;
}

.decision-btn.reject.active {
  background: #e53e3e;
  border-color: #e53e3e;
  color: white;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
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
  font-size: 13px;
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
  font-family: "Poppins", sans-serif;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

.form-group input:disabled {
  background: #f7fafc;
  color: #a0aec0;
  cursor: not-allowed;
}

.input-with-unit {
  position: relative;
  display: flex;
  align-items: center;
}

.input-with-unit input {
  flex: 1;
  padding-right: 60px;
}

.input-with-unit .unit,
.input-with-unit .currency {
  position: absolute;
  right: 14px;
  font-size: 14px;
  color: #718096;
  font-weight: 600;
  pointer-events: none;
}

.input-with-unit .currency {
  left: 14px;
  right: auto;
}

.input-with-unit input:has(~ .currency) {
  padding-left: 36px;
}

.modal-footer {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding: 24px 32px;
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
  opacity: 0.5;
  cursor: not-allowed;
}

/* Responsive */
@media (max-width: 1400px) {
  .request-sidebar {
    width: 380px;
  }

  .requests-content:has(~ .request-sidebar) {
    margin-right: 380px;
  }
}

@media (max-width: 1200px) {
  .request-sidebar {
    width: 100%;
    max-width: 420px;
  }

  .requests-content:has(~ .request-sidebar) {
    margin-right: 0;
  }
}

@media (max-width: 768px) {
  .requests-content {
    margin-left: 0;
    padding: 20px;
  }

  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    flex-direction: column;
  }

  .search-box {
    min-width: 100%;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }

  .decision-buttons {
    grid-template-columns: 1fr;
  }

  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>
