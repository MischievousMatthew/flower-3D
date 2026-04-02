<template>
  <div class="funding-request-details">
    <div class="details-content">
      <div class="details-header">
        <div>
          <h1 class="details-title">Funding Request Details</h1>
          <p class="details-subtitle">{{ request?.finance_request_id }}</p>
        </div>
        <button class="back-btn" @click="goBack">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          Back to List
        </button>
      </div>

      <div v-if="loading" class="loading-container">
        <div class="spinner"></div>
        <p>Loading request...</p>
      </div>

      <div v-else-if="error" class="error-container">
        <p>{{ error }}</p>
        <button @click="fetchRequest" class="retry-btn">Retry</button>
      </div>

      <div v-else-if="request" class="details-card">
        <!-- Status Banner -->
        <div
          class="status-banner"
          :class="getStatusClass(request.request_status)"
        >
          <div class="banner-content">
            <div>
              <h3>{{ request?.request_status }}</h3>
              <p v-if="request.request_status === 'Pending'">
                Waiting for Finance review
              </p>
              <p v-else-if="request.request_status === 'Approved'">
                Approved by {{ request.reviewed_by_name }} on
                {{ formatDate(request.accounting_decision_at) }}
              </p>
              <p v-else-if="request.request_status === 'Rejected'">
                Rejected by {{ request.reviewed_by_name }} on
                {{ formatDate(request.accounting_decision_at) }}
              </p>
              <p v-else-if="canEditFunding">You can edit and submit this request</p>
              <p v-else>You have view-only access to this request</p>
            </div>
            <!-- Edit/Submit actions only visible from Inventory context -->
            <div
              class="banner-actions"
              v-if="request.request_status === 'Draft' && isInventoryContext"
            >
              <button
                class="edit-btn"
                @click="editRequest"
                :disabled="!canEditFunding"
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
                  <path
                    d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
                  ></path>
                  <path
                    d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                  ></path>
                </svg>
                Edit Request
              </button>
              <button
                class="submit-btn"
                @click="submitRequest"
                :disabled="!canEditFunding"
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
                  <line x1="22" y1="2" x2="11" y2="13"></line>
                  <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
                Submit to Finance
              </button>
            </div>
          </div>
        </div>

        <!-- Request Information -->
        <div class="info-section">
          <h3>Request Identity & Control</h3>
          <div class="info-grid">
            <div class="info-item">
              <span class="label">Finance Request ID</span>
              <span class="value">{{ request.finance_request_id }}</span>
            </div>
            <div class="info-item">
              <span class="label">Related Sales Order ID</span>
              <span class="value">{{
                request.related_sales_order_id || "N/A"
              }}</span>
            </div>
            <div class="info-item">
              <span class="label">Request Date</span>
              <span class="value">{{ formatDate(request.request_date) }}</span>
            </div>
            <div class="info-item">
              <span class="label">Requested By</span>
              <span class="value">{{ request.submitted_by_name }}</span>
            </div>
            <div class="info-item">
              <span class="label">Finance Approver</span>
              <span class="value">{{
                request.approver_name || request.accounting_manager_name
              }}</span>
            </div>
            <div class="info-item">
              <span class="label">Submitted Date</span>
              <span class="value">{{
                request.submitted_to_accounting_at
                  ? formatDateTime(request.submitted_to_accounting_at)
                  : "Not submitted"
              }}</span>
            </div>
          </div>
        </div>

        <!-- Product Details -->
        <div class="info-section">
          <h3>Product & Procurement Details</h3>
          <div class="info-grid">
            <div class="info-item">
              <span class="label">Product Name</span>
              <span class="value font-bold">{{ request.product_name }}</span>
            </div>
            <div class="info-item">
              <span class="label">Flower Category</span>
              <span class="value">{{ request.flower_category }}</span>
            </div>
            <div class="info-item">
              <span class="label">Variant/Grade</span>
              <span class="value">{{ request.variant }}</span>
            </div>
            <div class="info-item">
              <span class="label">Requested Quantity</span>
              <span class="value font-bold"
                >{{ request.requested_qty }} {{ request.uom }}</span
              >
            </div>
            <div class="info-item">
              <span class="label">Minimum Order Qty</span>
              <span class="value"
                >{{ request.moq || "N/A" }} {{ request.uom }}</span
              >
            </div>
            <div class="info-item">
              <span class="label">Required Delivery Date</span>
              <span class="value">{{
                formatDate(request.required_delivery_date)
              }}</span>
            </div>
            <div class="info-item">
              <span class="label">Preferred Supplier</span>
              <span class="value">{{
                request.preferred_supplier || "N/A"
              }}</span>
            </div>
            <div class="info-item">
              <span class="label">Alternative Suppliers</span>
              <span class="value">{{
                request.alternative_suppliers || "N/A"
              }}</span>
            </div>
          </div>
        </div>

        <!-- Inventory Context -->
        <div class="info-section">
          <h3>Inventory Justification</h3>
          <div class="info-grid">
            <div class="info-item">
              <span class="label">Current Stock Level</span>
              <span class="value"
                >{{ request.current_stock }} {{ request.uom }}</span
              >
            </div>
            <div class="info-item">
              <span class="label">Reserved Stock</span>
              <span class="value"
                >{{ request.reserved_stock }} {{ request.uom }}</span
              >
            </div>
            <div class="info-item">
              <span class="label">Net Available Stock</span>
              <span class="value"
                >{{ request.net_available_stock }} {{ request.uom }}</span
              >
            </div>
            <div class="info-item">
              <span class="label">Required Quantity</span>
              <span class="value font-bold"
                >{{ request.required_quantity }} {{ request.uom }}</span
              >
            </div>
            <div class="info-item">
              <span class="label">Stock Shortage Qty</span>
              <span class="value font-bold text-error"
                >{{ request.stock_shortage_qty }} {{ request.uom }}</span
              >
            </div>
            <div class="info-item">
              <span class="label">Incoming Stock (POs)</span>
              <span class="value"
                >{{ request.incoming_stock || 0 }} {{ request.uom }}</span
              >
            </div>
            <div class="info-item">
              <span class="label">Reason for Shortage</span>
              <span class="value">{{ request.reason_for_shortage }}</span>
            </div>
          </div>
        </div>

        <!-- Financial Breakdown -->
        <div class="info-section">
          <h3>Financial Breakdown</h3>
          <div class="info-grid">
            <div class="info-item">
              <span class="label">Estimated Unit Cost</span>
              <span class="value"
                >₱{{ formatNumber(request.estimated_unit_cost) }}</span
              >
            </div>
            <div class="info-item">
              <span class="label">Estimated Total Cost</span>
              <span class="value font-bold"
                >₱{{ formatNumber(request.estimated_total_cost) }}</span
              >
            </div>
            <div class="info-item">
              <span class="label">Payment Terms</span>
              <span class="value">{{ request.payment_terms }}</span>
            </div>
            <div class="info-item">
              <span class="label">Expected Selling Price</span>
              <span class="value"
                >₱{{ formatNumber(request.expected_selling_price) }}</span
              >
            </div>
            <div class="info-item">
              <span class="label">Expected Revenue</span>
              <span class="value"
                >₱{{ formatNumber(request.expected_revenue) }}</span
              >
            </div>
            <div class="info-item">
              <span class="label">Estimated Gross Margin</span>
              <span
                class="value"
                :class="getMarginClass(request.estimated_gross_margin)"
              >
                {{ request.estimated_gross_margin }}%
              </span>
            </div>
            <div class="info-item" v-if="request.tax_vat_estimate">
              <span class="label">Tax/VAT Estimate</span>
              <span class="value"
                >₱{{ formatNumber(request.tax_vat_estimate) }}</span
              >
            </div>
            <div class="info-item" v-if="request.logistics_cost">
              <span class="label">Logistics Cost</span>
              <span class="value"
                >₱{{ formatNumber(request.logistics_cost) }}</span
              >
            </div>
          </div>
        </div>

        <!-- Urgency & Risk -->
        <div class="info-section">
          <h3>Urgency & Risk</h3>
          <div class="info-grid">
            <div class="info-item">
              <span class="label">Urgency Level</span>
              <span class="value">
                <span
                  class="urgency-badge"
                  :class="request.urgency_level?.toLowerCase()"
                >
                  {{ request.urgency_level }}
                </span>
              </span>
            </div>
            <div class="info-item">
              <span class="label">Shelf Life</span>
              <span class="value">{{ request.shelf_life }} days</span>
            </div>
            <div class="info-item">
              <span class="label">Expected Spoilage</span>
              <span class="value">{{ request.expected_spoilage }}%</span>
            </div>
            <div class="info-item" v-if="request.missed_sales_impact">
              <span class="label">Missed Sales Impact</span>
              <span class="value text-error"
                >₱{{ formatNumber(request.missed_sales_impact) }}</span
              >
            </div>
            <div class="info-item" v-if="request.seasonal_tag">
              <span class="label">Seasonal Tag</span>
              <span class="value">{{ request.seasonal_tag }}</span>
            </div>
            <div class="info-item">
              <span class="label">Demand Confidence</span>
              <span class="value">{{ request.demand_confidence }}</span>
            </div>
          </div>
        </div>

        <!-- Finance Recommendation -->
        <div class="info-section">
          <h3>Finance Recommendation</h3>
          <div
            class="recommendation-card"
            :class="
              request.finance_recommendation?.toLowerCase().replace(' ', '-')
            "
          >
            <div class="recommendation-header">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
              </svg>
              <span>{{ request.finance_recommendation }}</span>
            </div>
            <div class="info-grid">
              <div class="info-item">
                <span class="label">Recommended Quantity</span>
                <span class="value font-bold"
                  >{{ request.recommended_qty }} {{ request.uom }}</span
                >
              </div>
              <div class="info-item">
                <span class="label">Recommended Budget</span>
                <span class="value font-bold"
                  >₱{{ formatNumber(request.recommended_budget) }}</span
                >
              </div>
              <div class="info-item" v-if="request.price_ceiling">
                <span class="label">Price Ceiling</span>
                <span class="value"
                  >₱{{ formatNumber(request.price_ceiling) }}</span
                >
              </div>
              <div class="info-item" v-if="request.suggested_supplier">
                <span class="label">Suggested Supplier</span>
                <span class="value">{{ request.suggested_supplier }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Decision Brief -->
        <div class="info-section">
          <h3>Decision Brief</h3>
          <div class="decision-brief">
            <div class="brief-item">
              <div class="brief-icon">📦</div>
              <div class="brief-content">
                <span class="brief-label">What & Why</span>
                <p>{{ request.business_justification }}</p>
              </div>
            </div>
            <div class="brief-item success">
              <div class="brief-icon">✅</div>
              <div class="brief-content">
                <span class="brief-label">If Approved</span>
                <p>{{ request.approval_impact }}</p>
              </div>
            </div>
            <div class="brief-item warning">
              <div class="brief-icon">⚠️</div>
              <div class="brief-content">
                <span class="brief-label">If Rejected/Delayed</span>
                <p>{{ request.rejection_risk }}</p>
              </div>
            </div>
            <div class="brief-item" v-if="request.additional_notes">
              <div class="brief-icon">💡</div>
              <div class="brief-content">
                <span class="brief-label">Additional Context</span>
                <p>{{ request.additional_notes }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Finance Decision -->
        <div
          class="info-section"
          v-if="
            request.request_status === 'Approved' ||
            request.request_status === 'Rejected'
          "
        >
          <h3>Finance Decision</h3>
          <div
            class="decision-card"
            :class="request.request_status.toLowerCase()"
          >
            <div class="decision-header">
              <svg
                v-if="request.request_status === 'Approved'"
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
              <svg
                v-else
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
              <span
                >{{ request.request_status }} by
                {{ request.reviewed_by_name }}</span
              >
            </div>
            <div class="info-grid">
              <div
                class="info-item"
                v-if="request.request_status === 'Approved'"
              >
                <span class="label">Approved Quantity</span>
                <span class="value font-bold"
                  >{{ request.approved_quantity }} {{ request.uom }}</span
                >
              </div>
              <div
                class="info-item"
                v-if="request.request_status === 'Approved'"
              >
                <span class="label">Approved Amount</span>
                <span class="value font-bold"
                  >₱{{ formatNumber(request.approved_amount) }}</span
                >
              </div>
              <div class="info-item">
                <span class="label">Decision Date</span>
                <span class="value">{{
                  formatDateTime(request.accounting_decision_at)
                }}</span>
              </div>
              <div class="info-item" v-if="request.accounting_remarks">
                <span class="label">Remarks</span>
                <span class="value">{{ request.accounting_remarks }}</span>
              </div>
            </div>
            <div
              v-if="request.request_status === 'Rejected'"
              class="rejection-details"
            >
              <div class="rejection-item">
                <span class="label">Rejection Reason</span>
                <span class="value text-error">{{
                  request.rejection_reason
                }}</span>
              </div>
              <div class="rejection-item">
                <span class="label">Rejection Notes</span>
                <p class="rejection-notes">{{ request.rejection_notes }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import api from "../../../../plugins/axios";
import { toast } from "vue3-toastify";
import { useAssignment } from "../../../../composables/useAssignment";

// Sidebar components



const props = defineProps({
  /**
   * 'finance'   → renders Finance sidebar, hides Inventory-only actions
   * 'inventory' → renders Inventory sidebar, shows edit/submit actions
   * Defaults to 'inventory' for backwards compatibility.
   */
  context: {
    type: String,
    default: "inventory",
    validator: (v) => ["finance", "inventory"].includes(v),
  },
});

const router = useRouter();
const route = useRoute();
const { canEdit } = useAssignment();

const request = ref(null);
const loading = ref(false);
const error = ref(null);

// ── Computed helpers ──────────────────────────────────────────────────────────

/** Convenience flag used in the template */
const isInventoryContext = computed(() => props.context === "inventory");
const canEditFunding = computed(() => canEdit("inventory_funding"));

// ── Data fetching ─────────────────────────────────────────────────────────────

const fetchRequest = async () => {
  loading.value = true;
  error.value = null;

  const requestId = window.history.state?.requestId;

  if (!requestId) {
    error.value = "No request selected. Please go back and try again.";
    loading.value = false;
    return;
  }

  try {
    const { data } = await api.get(
      `/procurement/inventory/funding-requests/${requestId}`,
    );
    if (data.success) request.value = data.data;
  } catch (err) {
    error.value =
      err.response?.data?.message || "Failed to load funding request";
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
};

// ── Navigation ────────────────────────────────────────────────────────────────

/** Go back to the correct list depending on context */
const goBack = () => {
  if (props.context === "finance") {
    router.push("/erp/finance/funding-requests");
  } else {
    router.push("/erp/procurement/inventory/funding-request");
  }
};

const editRequest = () => {
  if (!canEditFunding.value) {
    toast.error("You do not have permission to edit funding requests");
    return;
  }
  const requestId = window.history.state?.requestId;
  router.push({
    path: "/erp/procurement/inventory/funding-request/edit",
    state: { requestId },
  });
};

const submitRequest = async () => {
  if (!canEditFunding.value) {
    toast.error("You do not have permission to submit funding requests");
    return;
  }
  const requestId = window.history.state?.requestId;
  if (!confirm("Submit this request to Finance?")) return;
  try {
    const { data } = await api.post(
      `/procurement/inventory/funding-requests/${requestId}/submit`,
    );
    if (data.success) {
      toast.success(data.message);
      await fetchRequest();
    }
  } catch (err) {
    toast.error(err.response?.data?.message || "Failed to submit request");
  }
};

// ── Formatters / helpers ──────────────────────────────────────────────────────

const formatDate = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const formatDateTime = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const formatNumber = (num) => {
  if (!num) return "0.00";
  return parseFloat(num).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const getStatusClass = (status) =>
  status?.toLowerCase().replace(" ", "-") || "";

const getMarginClass = (margin) => {
  if (margin >= 30) return "text-success";
  if (margin >= 20) return "text-warning";
  return "text-error";
};

onMounted(() => fetchRequest());
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.funding-request-details {
  min-height: 100vh;
  background: #f7fafc;
  font-family: "Poppins", sans-serif;
}
.details-content {
  flex: 1;
  padding: 32px 40px;
  max-width: 1400px;
  margin: 0 auto;
}
.details-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
}
.details-title {
  font-size: 32px;
  font-weight: 700;
  color: #2d3748;
  letter-spacing: -0.5px;
  margin-bottom: 4px;
}
.details-subtitle {
  font-size: 14px;
  color: #718096;
  font-family: "Poppins", monospace;
  font-weight: 600;
}
.back-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
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
.back-btn:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
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
  border-top-color: #4299e1;
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
  background: #4299e1;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
.retry-btn:hover {
  background: #2b6cb0;
}

.details-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  overflow: hidden;
}

/* Status Banner */
.status-banner {
  padding: 24px 32px;
  border-bottom: 2px solid #e2e8f0;
}
.status-banner.draft {
  background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
}
.status-banner.pending {
  background: linear-gradient(135deg, #fffaf0 0%, #fef5e7 100%);
}
.status-banner.approved {
  background: linear-gradient(135deg, #ebf8ff 0%, #e8f4fd 100%);
}
.status-banner.rejected {
  background: linear-gradient(135deg, #fff5f5 0%, #fed7e2 100%);
}
.banner-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.status-banner h3 {
  font-size: 20px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 4px;
}
.status-banner p {
  font-size: 14px;
  color: #718096;
}
.banner-actions {
  display: flex;
  gap: 12px;
}
.edit-btn,
.submit-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
  border: none;
}
.edit-btn {
  background: #ffffff;
  color: #4a5568;
  border: 1px solid #e2e8f0;
}
.edit-btn:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}
.submit-btn {
  background: #4299e1;
  color: white;
}
.submit-btn:hover {
  background: #2b6cb0;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(66, 153, 225, 0.35);
}
.edit-btn:disabled,
.submit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

/* Info Sections */
.info-section {
  padding: 32px;
  border-bottom: 2px solid #f7fafc;
}
.info-section:last-child {
  border-bottom: none;
}
.info-section h3 {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 20px;
}
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}
.info-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.info-item .label {
  font-size: 12px;
  font-weight: 600;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.info-item .value {
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
}
.font-bold {
  font-weight: 700 !important;
}
.text-success {
  color: #2b6cb0 !important;
}
.text-warning {
  color: #dd6b20 !important;
}
.text-error {
  color: #e53e3e !important;
}

.urgency-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
}
.urgency-badge.normal {
  background: #bee3f8;
  color: #2c5282;
}
.urgency-badge.urgent {
  background: #feebc8;
  color: #7c2d12;
}
.urgency-badge.critical {
  background: #fed7e2;
  color: #742a2a;
}

/* Recommendation Card */
.recommendation-card {
  padding: 20px;
  border-radius: 12px;
  border: 2px solid;
}
.recommendation-card.approve {
  background: #ebf8ff;
  border-color: #4299e1;
}
.recommendation-card.approve-partial {
  background: #fffaf0;
  border-color: #ed8936;
}
.recommendation-card.reject {
  background: #fff5f5;
  border-color: #e53e3e;
}
.recommendation-header {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}
.recommendation-card.approve .recommendation-header {
  color: #2c5282;
}
.recommendation-card.approve-partial .recommendation-header {
  color: #7c2d12;
}
.recommendation-card.reject .recommendation-header {
  color: #742a2a;
}

/* Decision Brief */
.decision-brief {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.brief-item {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #f7fafc;
  border-radius: 8px;
  border-left: 3px solid #cbd5e0;
}
.brief-item.success {
  border-left-color: #4299e1;
  background: #ebf8ff;
}
.brief-item.warning {
  border-left-color: #ed8936;
  background: #fffaf0;
}
.brief-icon {
  font-size: 20px;
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
  margin-bottom: 6px;
}
.brief-content p {
  font-size: 14px;
  color: #2d3748;
  line-height: 1.6;
  margin: 0;
}

/* Decision Card */
.decision-card {
  padding: 20px;
  border-radius: 12px;
  border: 2px solid;
}
.decision-card.approved {
  background: #ebf8ff;
  border-color: #4299e1;
}
.decision-card.rejected {
  background: #fff5f5;
  border-color: #e53e3e;
}
.decision-header {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}
.decision-card.approved .decision-header {
  color: #2c5282;
}
.decision-card.rejected .decision-header {
  color: #742a2a;
}
.rejection-details {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid rgba(0, 0, 0, 0.1);
}
.rejection-item {
  margin-bottom: 12px;
}
.rejection-item:last-child {
  margin-bottom: 0;
}
.rejection-item .label {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}
.rejection-notes {
  font-size: 14px;
  color: #2d3748;
  line-height: 1.6;
  margin: 0;
}

@media (max-width: 768px) {
  .details-header,
  .banner-content {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  .banner-actions {
    width: 100%;
    flex-direction: column;
  }
  .edit-btn,
  .submit-btn {
    width: 100%;
    justify-content: center;
  }
  .info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
