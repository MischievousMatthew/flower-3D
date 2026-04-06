<template>
  <div class="finance-funding-requests">
    <div class="requests-content">
      <!-- Header -->
      <div class="page-header">
        <div class="page-title-section">
          <h1 class="page-title">Funding Requests Review</h1>
          <p class="page-subtitle">
            Review and approve/reject funding requests from Inventory
          </p>
        </div>

        <div class="header-actions">
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
              placeholder="Search requests"
              v-model="searchQuery"
            />
          </div>
        </div>
      </div>

      <!-- Status Tabs -->
      <div class="status-tabs">
        <button
          v-for="tab in statusTabs"
          :key="tab.value"
          :class="{ active: activeStatusTab === tab.value }"
          @click="activeStatusTab = tab.value"
        >
          <span class="tab-label">{{ tab.label }}</span>
          <span class="tab-count">{{ getStatusCount(tab.value) }}</span>
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <div class="spinner"></div>
        <p>Loading requests...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-container">
        <p>{{ error }}</p>
        <button @click="fetchRequests" class="retry-btn">Retry</button>
      </div>

      <!-- Requests Table -->
      <div v-else class="table-container">
        <table class="requests-table">
          <thead>
            <tr>
              <th>Request Info</th>
              <th>Submitted By</th>
              <th>Approver</th>
              <th>Product Details</th>
              <th>Financial Summary</th>
              <th>Decision Brief</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="request in filteredRequests"
              :key="request.id"
              @click="viewRequest(request.id)"
              :class="{ selected: selectedRequestId === request.id }"
            >
              <td class="info-cell">
                <div class="request-info">
                  <span class="request-id">{{
                    request.finance_request_id
                  }}</span>
                  <span class="request-date">{{
                    formatDate(request.request_date)
                  }}</span>
                </div>
              </td>
              <td class="submitter-cell">
                <div class="submitter-info">
                  <span class="submitter-name">{{
                    request.submitted_by_name
                  }}</span>
                  <span class="submitter-email">{{
                    request.submitted_by_email
                  }}</span>
                </div>
              </td>
              <td class="approver-cell">
                <div class="approver-info">
                  <span class="approver-name">{{
                    request.approver_name || "Unassigned"
                  }}</span>
                  <span class="approver-meta">
                    {{
                      isAssignedApprover(request)
                        ? "You are the approver"
                        : "Assigned finance approver"
                    }}
                  </span>
                </div>
              </td>
              <td class="product-cell">
                <div class="product-info">
                  <span class="product-name">{{ request.product_name }}</span>
                  <span class="product-details"
                    >{{ request.flower_category }} • {{ request.variant }}</span
                  >
                  <span class="quantity"
                    >{{ request.requested_qty }} {{ request.uom }}</span
                  >
                </div>
              </td>
              <td class="financial-cell">
                <div class="financial-summary">
                  <div class="amount-row">
                    <span class="label">Total Cost:</span>
                    <span class="value"
                      >₱{{ formatNumber(request.estimated_total_cost) }}</span
                    >
                  </div>
                  <div class="amount-row">
                    <span class="label">Margin:</span>
                    <span
                      class="value"
                      :class="getMarginClass(request.estimated_gross_margin)"
                    >
                      {{ request.estimated_gross_margin }}%
                    </span>
                  </div>
                  <div
                    class="recommendation-badge"
                    :class="
                      request.finance_recommendation
                        ?.toLowerCase()
                        .replace(' ', '-')
                    "
                  >
                    {{ request.finance_recommendation }}
                  </div>
                </div>
              </td>
              <td class="brief-cell">
                <div class="brief-summary">
                  <div class="brief-row">
                    <span class="brief-icon">📦</span>
                    <span class="brief-text">{{
                      truncateText(request.business_justification, 40)
                    }}</span>
                  </div>
                  <div class="brief-row">
                    <span class="brief-icon">✅</span>
                    <span class="brief-text">{{
                      truncateText(request.approval_impact, 40)
                    }}</span>
                  </div>
                </div>
              </td>
              <td>
                <span
                  class="status-badge"
                  :class="getStatusClass(request)"
                >
                  {{ displayRequestStatus(request) }}
                </span>
              </td>
              <td class="action-cell" @click.stop>
                <button
                  v-if="request.request_status === 'Pending'"
                  class="review-btn"
                  @click="openReviewModal(request)"
                  :disabled="!canReviewRequest(request)"
                  :title="
                    canReviewRequest(request)
                      ? 'Review request'
                      : 'Only the assigned approver can review this request'
                  "
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
                  Review
                </button>
                <button
                  v-else
                  class="view-btn"
                  @click="viewRequest(request.id)"
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
                      d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                    ></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  View
                </button>
              </td>
            </tr>
            <tr v-if="filteredRequests.length === 0">
              <td colspan="8" class="empty-state">
                <p>No funding requests found</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Review Modal -->
    <div class="modal-overlay" v-if="showReviewModal" @click="closeReviewModal">
      <div class="modal review-modal" @click.stop>
        <div class="modal-header">
          <h2>Review Funding Request</h2>
          <button class="close-btn" @click="closeReviewModal">
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
          <!-- Request Summary -->
          <div class="request-summary">
            <h3>{{ reviewForm.product_name }}</h3>
            <p class="request-id">{{ reviewForm.finance_request_id }}</p>
            <div class="summary-grid">
              <div class="summary-item">
                <span class="summary-label">Requested By</span>
                <span class="summary-value">{{
                  reviewForm.submitted_by_name
                }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Approver</span>
                <span class="summary-value">{{
                  reviewForm.approver_name || "Unassigned"
                }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Requested Qty</span>
                <span class="summary-value"
                  >{{ reviewForm.requested_qty }} {{ reviewForm.uom }}</span
                >
              </div>
              <div class="summary-item">
                <span class="summary-label">Total Cost</span>
                <span class="summary-value"
                  >₱{{ formatNumber(reviewForm.estimated_total_cost) }}</span
                >
              </div>
              <div class="summary-item">
                <span class="summary-label">Margin</span>
                <span
                  class="summary-value"
                  :class="getMarginClass(reviewForm.estimated_gross_margin)"
                >
                  {{ reviewForm.estimated_gross_margin }}%
                </span>
              </div>
            </div>
          </div>

          <!-- Decision Brief -->
          <div class="decision-brief-section">
            <h4>Decision Brief</h4>
            <div class="brief-item">
              <div class="brief-icon">📦</div>
              <div class="brief-content">
                <span class="brief-label">What & Why</span>
                <p>{{ reviewForm.business_justification }}</p>
              </div>
            </div>
            <div class="brief-item success">
              <div class="brief-icon">✅</div>
              <div class="brief-content">
                <span class="brief-label">If Approved</span>
                <p>{{ reviewForm.approval_impact }}</p>
              </div>
            </div>
            <div class="brief-item warning">
              <div class="brief-icon">⚠️</div>
              <div class="brief-content">
                <span class="brief-label">If Rejected/Delayed</span>
                <p>{{ reviewForm.rejection_risk }}</p>
              </div>
            </div>
            <div class="brief-item" v-if="reviewForm.additional_notes">
              <div class="brief-icon">💡</div>
              <div class="brief-content">
                <span class="brief-label">Additional Context</span>
                <p>{{ reviewForm.additional_notes }}</p>
              </div>
            </div>
          </div>

          <!-- Finance Recommendation -->
          <div class="finance-recommendation">
            <h4>Finance Recommendation</h4>
            <div
              class="recommendation-badge"
              :class="
                reviewForm.finance_recommendation
                  ?.toLowerCase()
                  .replace(' ', '-')
              "
            >
              {{ reviewForm.finance_recommendation }}
            </div>
            <div class="recommendation-details">
              <div class="detail-item">
                <span class="label">Recommended Qty:</span>
                <span class="value"
                  >{{ reviewForm.recommended_qty }} {{ reviewForm.uom }}</span
                >
              </div>
              <div class="detail-item">
                <span class="label">Recommended Budget:</span>
                <span class="value"
                  >₱{{ formatNumber(reviewForm.recommended_budget) }}</span
                >
              </div>
              <div class="detail-item" v-if="reviewForm.price_ceiling">
                <span class="label">Price Ceiling:</span>
                <span class="value"
                  >₱{{ formatNumber(reviewForm.price_ceiling) }}</span
                >
              </div>
            </div>
          </div>

          <!-- Decision Section -->
          <div class="decision-section">
            <h4>Your Decision</h4>
            <div class="decision-buttons">
              <button
                class="decision-btn approve"
                :class="{ active: reviewForm.decision === 'approve' }"
                @click="reviewForm.decision = 'approve'"
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
                :class="{ active: reviewForm.decision === 'reject' }"
                @click="reviewForm.decision = 'reject'"
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

          <!-- Approval Form -->
          <div v-if="reviewForm.decision === 'approve'" class="form-section">
            <div class="form-row">
              <div class="form-group">
                <label>Approved Quantity *</label>
                <div class="input-with-unit">
                  <input
                    type="number"
                    v-model.number="reviewForm.approved_quantity"
                    :max="reviewForm.requested_qty"
                    required
                    min="0"
                  />
                  <span class="unit">{{ reviewForm.uom }}</span>
                </div>
              </div>
              <div class="form-group">
                <label>Approved Amount *</label>
                <div class="input-with-unit">
                  <span class="currency">₱</span>
                  <input
                    type="number"
                    v-model.number="reviewForm.approved_amount"
                    required
                    min="0"
                    step="0.01"
                  />
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Remarks (Optional)</label>
              <textarea
                v-model="reviewForm.finance_remarks"
                rows="3"
                placeholder="Add any comments or conditions..."
              ></textarea>
            </div>
          </div>

          <!-- Rejection Form -->
          <div v-if="reviewForm.decision === 'reject'" class="form-section">
            <div class="form-group">
              <label>Rejection Reason *</label>
              <select v-model="reviewForm.rejection_reason" required>
                <option value="">Select reason</option>
                <option value="Insufficient Cash">Insufficient Cash</option>
                <option value="Budget Exceeded">Budget Exceeded</option>
                <option value="Low Profit Margin">Low Profit Margin</option>
                <option value="Overstock Risk">Overstock Risk</option>
                <option value="High Spoilage Risk">High Spoilage Risk</option>
                <option value="Supplier Price Too High">
                  Supplier Price Too High
                </option>
                <option value="Duplicate Request">Duplicate Request</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="form-group">
              <label>Rejection Notes *</label>
              <textarea
                v-model="reviewForm.rejection_notes"
                rows="4"
                placeholder="Explain the reason for rejection..."
                required
              ></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" @click="closeReviewModal">Cancel</button>
          <button
            class="btn-submit"
            @click="submitDecision"
            :disabled="!reviewForm.decision || submitting || !canSubmitDecision"
          >
            {{
              submitting
                ? "Submitting..."
                : reviewForm.decision === "approve"
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
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "../../../plugins/axios";
import { toast } from "vue3-toastify";
import { useAssignment } from "../../../composables/useAssignment";
import { useAuth } from "../../../composables/useAuth";

const router = useRouter();
const { canEdit } = useAssignment();
const { user } = useAuth();

const searchQuery = ref("");
const activeStatusTab = ref("all");
const selectedRequestId = ref(null);
const loading = ref(false);
const error = ref(null);
const requests = ref([]);
const showReviewModal = ref(false);
const submitting = ref(false);
const canEditFunding = computed(() => canEdit("funding_requests"));
const currentEmployeeId = computed(() => Number(user.value?.id || 0));
const canSubmitDecision = computed(
  () =>
    canEditFunding.value &&
    Number(reviewForm.value.approver_id || 0) === currentEmployeeId.value,
);

const reviewForm = ref({
  id: null,
  decision: null,
  approved_quantity: 0,
  approved_amount: 0,
  finance_remarks: "",
  rejection_reason: "",
  rejection_notes: "",
});

const statusTabs = [
  { label: "All Requests", value: "all" },
  { label: "Pending", value: "Pending" },
  { label: "Approved", value: "Approved" },
  { label: "Rejected", value: "Rejected" },
];

const filteredRequests = computed(() => {
  let filtered = requests.value;

  if (activeStatusTab.value !== "all") {
    filtered = filtered.filter(
      (r) => r.request_status === activeStatusTab.value,
    );
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(
      (r) =>
        r.finance_request_id.toLowerCase().includes(query) ||
        r.product_name.toLowerCase().includes(query) ||
        r.submitted_by_name.toLowerCase().includes(query),
    );
  }

  return filtered;
});

const getStatusCount = (status) => {
  if (status === "all") return requests.value.length;
  return requests.value.filter((r) => r.request_status === status).length;
};

const isAssignedApprover = (request) =>
  Number(request?.approver_id || 0) === currentEmployeeId.value;

const canReviewRequest = (request) =>
  canEditFunding.value && isAssignedApprover(request);

const getMarginClass = (margin) => {
  if (margin >= 30) return "text-success";
  if (margin >= 20) return "text-warning";
  return "text-error";
};

const getStatusClass = (status) => {
  if (typeof status === "object" && status !== null) {
    return displayRequestStatus(status).toLowerCase().replace(" ", "-");
  }
  return status.toLowerCase().replace(" ", "-");
};

const displayRequestStatus = (request) => {
  if (request?.request_status === "Approved" && request?.payment_status === "paid") {
    return "Paid";
  }

  return request?.request_status || "Unknown";
};

const formatDate = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const formatNumber = (num) => {
  if (!num) return "0.00";
  return parseFloat(num).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const truncateText = (text, maxLength) => {
  if (!text) return "";
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + "...";
};

const fetchRequests = async () => {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await api.get("/finance/funding-requests");
    if (data.success) {
      requests.value = data.data;
    }
  } catch (err) {
    console.error("Error fetching requests:", err);
    error.value =
      err.response?.data?.message || "Failed to load funding requests";
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
};

const viewRequest = (id) => {
  selectedRequestId.value = id;
  router.push({
    name: "FinanceFundingRequestDetails",
    params: { id },
  });
};

const openReviewModal = (request) => {
  if (!canEditFunding.value) {
    toast.error("You do not have permission to review funding requests");
    return;
  }
  if (!isAssignedApprover(request)) {
    toast.error("Only the assigned approver can review this request");
    return;
  }
  reviewForm.value = {
    id: request.id,
    finance_request_id: request.finance_request_id,
    approver_id: request.approver_id,
    approver_name: request.approver_name,
    product_name: request.product_name,
    submitted_by_name: request.submitted_by_name,
    requested_qty: request.requested_qty,
    uom: request.uom,
    estimated_total_cost: request.estimated_total_cost,
    estimated_gross_margin: request.estimated_gross_margin,
    business_justification: request.business_justification,
    approval_impact: request.approval_impact,
    rejection_risk: request.rejection_risk,
    additional_notes: request.additional_notes,
    finance_recommendation: request.finance_recommendation,
    recommended_qty: request.recommended_qty,
    recommended_budget: request.recommended_budget,
    price_ceiling: request.price_ceiling,
    decision: null,
    approved_quantity: request.requested_qty,
    approved_amount: request.estimated_total_cost,
    finance_remarks: "",
    rejection_reason: "",
    rejection_notes: "",
  };
  showReviewModal.value = true;
};

const closeReviewModal = () => {
  showReviewModal.value = false;
  reviewForm.value = {
    id: null,
    decision: null,
    approver_id: null,
    approver_name: "",
    approved_quantity: 0,
    approved_amount: 0,
    finance_remarks: "",
    rejection_reason: "",
    rejection_notes: "",
  };
};

const submitDecision = async () => {
  if (!canEditFunding.value) {
    toast.error("You do not have permission to review funding requests");
    return;
  }
  if (!canSubmitDecision.value) {
    toast.error("Only the assigned approver can submit a decision");
    return;
  }
  if (!reviewForm.value.decision) {
    toast.error("Please select a decision");
    return;
  }

  if (reviewForm.value.decision === "approve") {
    if (
      !reviewForm.value.approved_quantity ||
      !reviewForm.value.approved_amount
    ) {
      toast.error("Please fill in all required fields");
      return;
    }
  }

  if (reviewForm.value.decision === "reject") {
    if (
      !reviewForm.value.rejection_reason ||
      !reviewForm.value.rejection_notes
    ) {
      toast.error("Please fill in all required fields");
      return;
    }
  }

  submitting.value = true;

  try {
    if (reviewForm.value.decision === "approve") {
      const payload = {
        approved_quantity: reviewForm.value.approved_quantity,
        approved_amount: reviewForm.value.approved_amount,
        finance_remarks: reviewForm.value.finance_remarks,
      };

      const { data } = await api.post(
        `finance/funding-requests/${reviewForm.value.id}/approve`,
        payload,
      );

      if (data.success) {
        toast.success(data.message);
        closeReviewModal();
        await fetchRequests();
      }
    } else {
      const payload = {
        rejection_reason: reviewForm.value.rejection_reason,
        rejection_notes: reviewForm.value.rejection_notes,
      };

      const { data } = await api.post(
        `finance/funding-requests/${reviewForm.value.id}/reject`,
        payload,
      );

      if (data.success) {
        toast.success(data.message);
        closeReviewModal();
        await fetchRequests();
      }
    }
  } catch (err) {
    console.error("Error submitting decision:", err);
    const errorMessage =
      err.response?.data?.message || "Failed to submit decision";
    toast.error(errorMessage);

    if (err.response?.data?.errors) {
      const errors = err.response.data.errors;
      const firstError = Object.values(errors)[0];
      toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
    }
  } finally {
    submitting.value = false;
  }
};

onMounted(() => {
  fetchRequests();
});
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.finance-funding-requests {
  display: flex;
  min-height: 100vh;
  background: #f7fafc;
  font-family: "Poppins", sans-serif;
}

.requests-content {
  flex: 1;
  padding: 32px 40px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 28px;
}

.page-title-section {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-title {
  font-size: 32px;
  font-weight: 700;
  color: #2d3748;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 14px;
  color: #718096;
  font-weight: 500;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 16px;
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

.status-tabs {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  padding: 20px 24px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}

.status-tabs button {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 14px 20px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}

.status-tabs button.active {
  background: #48bb78;
  border-color: #48bb78;
  color: white;
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.25);
}

.tab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 24px;
  padding: 0 8px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
}

.status-tabs button.active .tab-count {
  background: rgba(255, 255, 255, 0.25);
}

.loading-container,
.error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e2e8f0;
  border-top-color: #48bb78;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.loading-container p,
.error-container p {
  margin-top: 16px;
  font-size: 14px;
  color: #718096;
}

.retry-btn {
  margin-top: 12px;
  padding: 8px 20px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.retry-btn:hover {
  background: #38a169;
}

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
  vertical-align: top;
}

.empty-state {
  text-align: center;
  padding: 60px 20px !important;
  color: #a0aec0;
}

.info-cell {
  min-width: 180px;
}

.request-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.request-id {
  font-weight: 700;
  color: #2d3748;
  font-family: "Poppins", monospace;
  font-size: 14px;
}

.request-date,
.submitted-date {
  font-size: 12px;
  color: #718096;
}

.submitter-cell {
  min-width: 160px;
}

.submitter-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.submitter-name {
  font-weight: 600;
  color: #2d3748;
  font-size: 14px;
}

.submitter-email {
  font-size: 12px;
  color: #718096;
}

.approver-cell {
  min-width: 170px;
}

.approver-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.approver-name {
  font-weight: 600;
  color: #2d3748;
  font-size: 14px;
}

.approver-meta {
  font-size: 12px;
  color: #718096;
}

.product-cell {
  min-width: 180px;
}

.product-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.product-name {
  font-weight: 700;
  color: #2d3748;
  font-size: 14px;
}

.product-details {
  font-size: 12px;
  color: #718096;
}

.quantity {
  font-size: 13px;
  font-weight: 600;
  color: #48bb78;
}

.financial-cell {
  min-width: 180px;
}

.financial-summary {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.amount-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
}

.amount-row .label {
  color: #718096;
  font-weight: 500;
}

.amount-row .value {
  font-weight: 700;
  color: #2d3748;
  font-family: "Poppins", monospace;
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

.recommendation-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.3px;
  text-transform: uppercase;
  margin-top: 4px;
  text-align: center;
}

.recommendation-badge.approve {
  background: #c6f6d5;
  color: #22543d;
}

.recommendation-badge.approve-partial {
  background: #feebc8;
  color: #7c2d12;
}

.recommendation-badge.reject {
  background: #fed7e2;
  color: #742a2a;
}

.brief-cell {
  min-width: 220px;
}

.brief-summary {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.brief-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.brief-icon {
  font-size: 14px;
  flex-shrink: 0;
}

.brief-text {
  font-size: 12px;
  color: #4a5568;
  line-height: 1.4;
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

.action-cell {
  min-width: 100px;
}

.review-btn,
.view-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
  border: none;
}

.review-btn {
  background: #48bb78;
  color: white;
}

.review-btn:hover {
  background: #38a169;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
}
.review-btn:disabled,
.btn-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.view-btn {
  background: #f7fafc;
  color: #4a5568;
  border: 1px solid #e2e8f0;
}

.view-btn:hover {
  background: #e2e8f0;
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
  max-width: 800px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
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
  font-size: 20px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 4px;
}

.request-summary .request-id {
  font-size: 13px;
  color: #718096;
  font-family: "Poppins", monospace;
  font-weight: 600;
  margin-bottom: 16px;
  display: block;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
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
  font-size: 15px;
  font-weight: 700;
  color: #2d3748;
}

.decision-brief-section h4,
.finance-recommendation h4,
.decision-section h4 {
  font-size: 16px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 16px;
}

.brief-item {
  display: flex;
  gap: 12px;
  padding: 12px;
  background: #f7fafc;
  border-radius: 8px;
  margin-bottom: 12px;
  border-left: 3px solid #cbd5e0;
}

.brief-item:last-child {
  margin-bottom: 0;
}

.brief-item.success {
  border-left-color: #48bb78;
  background: #f0fff4;
}

.brief-item.warning {
  border-left-color: #ed8936;
  background: #fffaf0;
}

.brief-icon {
  font-size: 18px;
  flex-shrink: 0;
}

.brief-content {
  flex: 1;
}

.brief-label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.brief-content p {
  font-size: 13px;
  color: #2d3748;
  line-height: 1.5;
  margin: 0;
}

.finance-recommendation .recommendation-badge {
  margin-bottom: 12px;
}

.recommendation-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.detail-item .label {
  color: #718096;
  font-weight: 500;
}

.detail-item .value {
  font-weight: 700;
  color: #2d3748;
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

.input-with-unit {
  position: relative;
  display: flex;
  align-items: center;
}

.input-with-unit input {
  flex: 1;
}

.input-with-unit .unit {
  position: absolute;
  right: 14px;
  font-size: 14px;
  color: #718096;
  font-weight: 600;
  pointer-events: none;
}

.input-with-unit .currency {
  position: absolute;
  left: 14px;
  font-size: 14px;
  color: #718096;
  font-weight: 600;
  pointer-events: none;
}

.input-with-unit input:has(~ .currency) {
  padding-left: 36px;
}

.input-with-unit input:has(~ .unit) {
  padding-right: 60px;
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

@media (max-width: 768px) {
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
