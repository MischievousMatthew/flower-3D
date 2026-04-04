<template>
  <div class="funding-requests">
    

    <div class="requests-content">
      <!-- Header -->
      <div class="page-header">
        <div class="page-title-section">
          <h1 class="page-title">Purchase Funding Requests</h1>
          <p class="page-subtitle">
            Prepare and submit funding requests to Finance
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
          <button
            class="create-btn"
            @click="goToCreate"
            :disabled="!canEditFunding"
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
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            New Request
          </button>
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
              <th>Product Details</th>
              <th>Finance Approver</th>
              <th>Financial Summary</th>
              <th>Urgency</th>
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
                  <span class="requested-by"
                    >By: {{ request.submitted_by_name }}</span
                  >
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
              <td>
                <div class="manager-info">
                  <span class="manager-name">{{
                    request.approver_name || request.accounting_manager_name || "Not assigned"
                  }}</span>
                </div>
              </td>
              <td class="financial-cell">
                <div class="financial-summary">
                  <div class="amount-row">
                    <span class="label">Est. Cost:</span>
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
              <td>
                <span
                  class="urgency-badge"
                  :class="request.urgency_level?.toLowerCase()"
                >
                  <svg
                    v-if="request.urgency_level === 'Critical'"
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                  >
                    <path
                      d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"
                    />
                  </svg>
                  {{ request.urgency_level }}
                </span>
              </td>
              <td>
                <span
                  class="status-badge"
                  :class="getStatusClass(request.request_status)"
                >
                  {{ request.request_status }}
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
                  <button @click="viewRequest(request.id)">View Details</button>
                  <button
                    v-if="request.request_status === 'Draft'"
                    @click="editRequest(request.id)"
                    :disabled="!canEditFunding"
                  >
                    Edit Request
                  </button>
                  <button
                    v-if="request.request_status === 'Draft'"
                    @click="submitRequest(request.id)"
                    :disabled="!canEditFunding"
                  >
                    Submit to Finance
                  </button>
                  <button
                    class="delete-btn"
                    @click="deleteRequest(request.id)"
                    :disabled="!canEditFunding"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="filteredRequests.length === 0">
              <td colspan="7" class="empty-state">
                <p>No funding requests found</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "../../../../plugins/axios";
import { toast } from "vue3-toastify";
import { useAssignment } from "../../../../composables/useAssignment";


const router = useRouter();
const { canEdit } = useAssignment();

const searchQuery = ref("");
const activeStatusTab = ref("all");
const selectedRequestId = ref(null);
const openMenuId = ref(null);
const loading = ref(false);
const error = ref(null);
const requests = ref([]);
const canEditFunding = computed(() => canEdit("inventory_funding"));

const statusTabs = [
  { label: "All Requests", value: "all" },
  { label: "Draft", value: "Draft" },
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

const getMarginClass = (margin) => {
  if (margin >= 30) return "text-success";
  if (margin >= 20) return "text-warning";
  return "text-error";
};

const getStatusClass = (status) => status.toLowerCase().replace(" ", "-");

const formatDate = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const formatNumber = (num) => {
  if (!num) return "0";
  return parseFloat(num).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const fetchRequests = async () => {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await api.get("/procurement/inventory/funding-requests");
    if (data.success) requests.value = data.data;
  } catch (err) {
    console.error("Error fetching requests:", err);
    error.value =
      err.response?.data?.message || "Failed to load funding requests";
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
};

const goToCreate = () =>
  canEditFunding.value
    ? router.push("/erp/procurement/inventory/funding-request/create")
    : toast.error("You do not have permission to create funding requests");

// ID is hidden in history state — never appears in the URL
const viewRequest = (id) => {
  selectedRequestId.value = id;
  router.push({
    name: "FundingRequestDetails",
    params: { id },
  });
};

const editRequest = (id) => {
  if (!canEditFunding.value) {
    toast.error("You do not have permission to edit funding requests");
    return;
  }
  openMenuId.value = null;
  router.push({
    name: "EditFundingRequest",
    params: { id },
  });
};

const submitRequest = async (id) => {
  if (!canEditFunding.value) {
    toast.error("You do not have permission to submit funding requests");
    return;
  }
  openMenuId.value = null;
  if (!confirm("Submit this request to Finance?")) return;
  try {
    const { data } = await api.post(
      `/procurement/inventory/funding-requests/${id}/submit`,
    );
    if (data.success) {
      toast.success(data.message);
      await fetchRequests();
    }
  } catch (err) {
    toast.error(err.response?.data?.message || "Failed to submit request");
  }
};

const deleteRequest = async (id) => {
  if (!canEditFunding.value) {
    toast.error("You do not have permission to delete funding requests");
    return;
  }
  openMenuId.value = null;
  if (!confirm("Are you sure you want to delete this funding request?")) return;
  try {
    const { data } = await api.delete(
      `/procurement/inventory/funding-requests/${id}`,
    );
    if (data.success) {
      toast.success(data.message);
      await fetchRequests();
    }
  } catch (err) {
    toast.error(err.response?.data?.message || "Failed to delete request");
  }
};

const toggleMenu = (id) => {
  openMenuId.value = openMenuId.value === id ? null : id;
};

onMounted(() => fetchRequests());
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");

* {
  font-family: "Poppins", sans-serif;
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.funding-requests {
  display: flex;
  min-height: 100vh;
  background: #f7fafc;
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
  border-color: #4299e1;
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
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
}
.search-box input::placeholder {
  color: #a0aec0;
}

.create-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #4299e1;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.create-btn:hover {
  background: #2b6cb0;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(66, 153, 225, 0.35);
}
.create-btn:disabled,
.dropdown-menu button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
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
}
.status-tabs button.active {
  background: #4299e1;
  border-color: #4299e1;
  color: white;
  box-shadow: 0 4px 12px rgba(66, 153, 225, 0.25);
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
  transition: all 0.2s;
}
.retry-btn:hover {
  background: #2b6cb0;
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
  background: #f0f7ff;
}
.requests-table tbody tr.selected {
  background: #ebf8ff;
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
.requested-by {
  font-size: 12px;
  color: #718096;
}

.product-cell {
  min-width: 200px;
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
  color: #4299e1;
}

.manager-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.manager-name {
  font-size: 13px;
  font-weight: 600;
  color: #2d3748;
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
  color: #2b6cb0 !important;
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
  background: #bee3f8;
  color: #2c5282;
}
.recommendation-badge.approve-partial {
  background: #feebc8;
  color: #7c2d12;
}
.recommendation-badge.reject {
  background: #fed7e2;
  color: #742a2a;
}

.urgency-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.3px;
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

.status-badge {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 16px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.3px;
}
.status-badge.draft {
  background: #e2e8f0;
  color: #4a5568;
}
.status-badge.pending {
  background: #feebc8;
  color: #7c2d12;
}
.status-badge.approved {
  background: #bee3f8;
  color: #2c5282;
}
.status-badge.rejected {
  background: #fed7e2;
  color: #742a2a;
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
  min-width: 200px;
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
  font-weight: 500;
}
.dropdown-menu button:hover {
  background: #f0f7ff;
}
.dropdown-menu button:disabled:hover {
  background: transparent;
}
.dropdown-menu button.delete-btn {
  color: #e53e3e;
}
.dropdown-menu button.delete-btn:hover {
  background: #fff5f5;
}
</style>
