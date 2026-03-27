<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <header class="content-header">
        <div class="header-left">
          <button class="mobile-toggle" @click="toggleMobile" aria-label="Toggle Menu">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
          <h1 class="page-title">Vendor Registration</h1>
        </div>

        <div class="header-actions">
          <div class="search-box">
            <input
              type="text"
              placeholder="Search vendors..."
              v-model="searchQuery"
              @input="onSearch"
            />
            <span class="search-icon">🔍</span>
          </div>
          <button class="icon-btn">🔔</button>
          <button class="icon-btn">⚙️</button>
          <div class="user-profile">
            <div class="admin-avatar">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
            </div>
            <div class="profile-info">
              <span class="profile-name">{{ user?.name ?? "Admin" }}</span>
              <span class="profile-role">Administrator</span>
            </div>
          </div>
        </div>
      </header>

      <div class="tabs">
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'pending' }"
          @click="setTab('pending')"
        >
          Vendor requests ({{ stats.pending || 0 }})
        </button>
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'approved' }"
          @click="setTab('approved')"
        >
          Approved vendors ({{ stats.approved || 0 }})
        </button>
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'rejected' }"
          @click="setTab('rejected')"
        >
          Rejected vendors ({{ stats.rejected || 0 }})
        </button>
      </div>

      <div class="content-body">
        <div class="content-actions">
          <h2 class="section-title">Vendor Registration Requests</h2>
          <div class="action-buttons">
            <button class="btn-filter" @click="toggleFilters">
              <span>⚡</span>
              <span>Filter</span>
            </button>
            <button class="btn-export" @click="exportData">
              <span>📥</span>
              <span>Export</span>
            </button>
          </div>
        </div>

        <div v-if="showFilters" class="filters-panel">
          <div class="filter-group">
            <label>Business Type:</label>
            <select v-model="filters.business_type" @change="fetchApplications">
              <option value="">All</option>
              <option value="individual">Individual</option>
              <option value="partnership">Partnership</option>
              <option value="corporation">Corporation</option>
            </select>
          </div>
          <div class="filter-group">
            <label>Date Range:</label>
            <input
              type="date"
              v-model="filters.start_date"
              @change="fetchApplications"
            />
            <span>to</span>
            <input
              type="date"
              v-model="filters.end_date"
              @change="fetchApplications"
            />
          </div>
          <div class="filter-group">
            <button class="btn-clear-filters" @click="clearFilters">
              Clear Filters
            </button>
          </div>
        </div>

        <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />

        <div v-if="vendorApplications.length === 0" class="empty-state">
          <p>No vendor applications found.</p>
        </div>

        <div v-else class="requests-table">
          <div class="table-header">
            <div class="th" style="flex: 2">Store Name</div>
            <div class="th" style="flex: 1.5">Owner Name</div>
            <div class="th" style="flex: 1">Business Type</div>
            <div class="th" style="flex: 1">Date Submitted</div>
            <div class="th" style="flex: 1">Status</div>
            <div class="th" style="width: 100px">Actions</div>
          </div>

          <div
            v-for="application in vendorApplications"
            :key="application.id"
            class="table-row"
            @click="viewDetails(application)"
          >
            <div class="td" style="flex: 2">
              <div class="store-info">
                <div class="store-avatar">
                  {{ application.store_name?.charAt(0) || "?" }}
                </div>
                <div>
                  <div class="store-name">{{ application.store_name }}</div>
                  <div class="store-location">
                    {{ extractLocation(application.store_address) }}
                  </div>
                </div>
              </div>
            </div>
            <div class="td" style="flex: 1.5">{{ application.owner_name }}</div>
            <div class="td" style="flex: 1">
              {{ formatBusinessType(application.business_type) }}
            </div>
            <div class="td" style="flex: 1">
              {{ formatDate(application.submitted_at) }}
            </div>
            <div class="td" style="flex: 1">
              <span
                class="status-badge"
                :class="application.status.toLowerCase()"
              >
                {{ formatStatus(application.status) }}
              </span>
            </div>
            <div class="td" style="width: 100px">
              <div class="action-buttons-cell">
                <button
                  v-if="application.status === 'pending'"
                  class="btn-action-sm btn-approve-sm"
                  @click.stop="showApproveConfirmation(application)"
                  title="Approve"
                >
                  ✓
                </button>
                <button
                  v-if="application.status === 'pending'"
                  class="btn-action-sm btn-deny-sm"
                  @click.stop="showRejectModal(application)"
                  title="Reject"
                >
                  ✕
                </button>
                <button class="btn-more" @click.stop="viewDetails(application)">
                  ⋯
                </button>
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="vendorApplications.length > 0 && pagination.last_page > 1"
          class="pagination"
        >
          <button
            class="page-btn"
            :disabled="pagination.current_page === 1"
            @click="changePage(pagination.current_page - 1)"
          >
            Prev
          </button>

          <button
            v-for="page in visiblePages"
            :key="page"
            class="page-btn"
            :class="{ active: pagination.current_page === page }"
            @click="changePage(page)"
          >
            {{ page }}
          </button>

          <span v-if="showEndEllipsis" class="page-dots">...</span>

          <button
            v-if="showLastPage"
            class="page-btn"
            :class="{
              active: pagination.current_page === pagination.last_page,
            }"
            @click="changePage(pagination.last_page)"
          >
            {{ pagination.last_page }}
          </button>

          <button
            class="page-btn"
            :disabled="pagination.current_page === pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >
            Next
          </button>

          <span class="pagination-info">
            Showing {{ pagination.from }}-{{ pagination.to }} of
            {{ pagination.total }}
          </span>
        </div>
      </div>
    </main>

    <!-- Detail Modal -->
    <div v-if="showDetailModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>Vendor Registration Details</h2>
          <button class="btn-close" @click="closeModal">✕</button>
        </div>

        <div class="modal-body">
          <div v-if="selectedApplication" class="details-grid">
            <section class="detail-section">
              <h3 class="section-heading">Store Information</h3>
              <div class="detail-group">
                <div class="detail-item">
                  <label>Store Name</label>
                  <p>{{ selectedApplication.store_name }}</p>
                </div>
                <div class="detail-item">
                  <label>Store Description</label>
                  <p>{{ selectedApplication.store_description }}</p>
                </div>
                <div class="detail-item">
                  <label>Business Type</label>
                  <p>
                    {{ formatBusinessType(selectedApplication.business_type) }}
                  </p>
                </div>
                <div class="detail-item">
                  <label>Physical Store Address</label>
                  <p>{{ selectedApplication.store_address }}</p>
                </div>
                <div class="detail-item">
                  <label>Service Areas</label>
                  <p>{{ selectedApplication.service_areas }}</p>
                </div>
                <div class="detail-item">
                  <label>Operating Hours</label>
                  <p>{{ selectedApplication.operating_hours }}</p>
                </div>
              </div>
            </section>

            <section class="detail-section">
              <h3 class="section-heading">
                Owner / Representative Information
              </h3>
              <div class="detail-group">
                <div class="detail-item">
                  <label>Owner's Full Name</label>
                  <p>{{ selectedApplication.owner_name }}</p>
                </div>
                <div class="detail-item">
                  <label>Position / Role</label>
                  <p>{{ selectedApplication.position }}</p>
                </div>
                <div class="detail-item">
                  <label>Contact Number</label>
                  <p>{{ selectedApplication.contact_number }}</p>
                </div>
                <div class="detail-item">
                  <label>Email Address</label>
                  <p>{{ selectedApplication.email }}</p>
                </div>
              </div>
            </section>

            <section class="detail-section">
              <h3 class="section-heading">Identity & Verification</h3>
              <div class="detail-group">
                <div class="detail-item">
                  <label>Government ID Number</label>
                  <p>{{ selectedApplication.government_id_number || "N/A" }}</p>
                </div>
                <div class="detail-item">
                  <label>Government-Issued ID</label>
                  <div
                    v-if="selectedApplication.government_id_url"
                    class="document-preview"
                    @click="viewDocument(selectedApplication.government_id_url)"
                  >
                    <div class="doc-placeholder">📄 View Document</div>
                  </div>
                  <p v-else>Not uploaded</p>
                </div>
                <div class="detail-item">
                  <label>Selfie with Government ID</label>
                  <div
                    v-if="selectedApplication.selfie_with_id_url"
                    class="document-preview"
                    @click="
                      viewDocument(selectedApplication.selfie_with_id_url)
                    "
                  >
                    <div class="doc-placeholder">📷 View Photo</div>
                  </div>
                  <p v-else>Not uploaded</p>
                </div>
                <div class="detail-item">
                  <label>Proof of Address</label>
                  <div
                    v-if="selectedApplication.proof_of_address_url"
                    class="document-preview"
                    @click="
                      viewDocument(selectedApplication.proof_of_address_url)
                    "
                  >
                    <div class="doc-placeholder">📄 View Document</div>
                  </div>
                  <p v-else>Not uploaded</p>
                </div>
              </div>
            </section>

            <section class="detail-section">
              <h3 class="section-heading">Business Registration</h3>
              <div class="detail-group">
                <div class="detail-item">
                  <label>DTI Registration Number</label>
                  <p>{{ selectedApplication.dti_number || "N/A" }}</p>
                </div>
                <div class="detail-item">
                  <label>SEC Registration Number</label>
                  <p>{{ selectedApplication.sec_number || "N/A" }}</p>
                </div>
                <div class="detail-item">
                  <label>Barangay Clearance Number</label>
                  <p>
                    {{ selectedApplication.barangay_clearance_number || "N/A" }}
                  </p>
                </div>
                <div class="detail-item">
                  <label>Barangay Clearance</label>
                  <div
                    v-if="selectedApplication.barangay_clearance_url"
                    class="document-preview"
                    @click="
                      viewDocument(selectedApplication.barangay_clearance_url)
                    "
                  >
                    <div class="doc-placeholder">📄 View Document</div>
                  </div>
                  <p v-else>Not uploaded</p>
                </div>
                <div class="detail-item">
                  <label>Mayor's Permit Number</label>
                  <p>{{ selectedApplication.mayor_permit_number || "N/A" }}</p>
                </div>
                <div class="detail-item">
                  <label>Mayor's Permit</label>
                  <div
                    v-if="selectedApplication.mayor_permit_url"
                    class="document-preview"
                    @click="viewDocument(selectedApplication.mayor_permit_url)"
                  >
                    <div class="doc-placeholder">📄 View Document</div>
                  </div>
                  <p v-else>Not uploaded</p>
                </div>
                <div class="detail-item">
                  <label>BIR Registration / TIN</label>
                  <p>{{ selectedApplication.bir_tin || "N/A" }}</p>
                </div>
              </div>
            </section>

            <section class="detail-section">
              <h3 class="section-heading">Profile Completion Status</h3>
              <div class="detail-group">
                <div class="detail-item full-width">
                  <div class="completion-card">
                    <div class="completion-header">
                      <span class="completion-label">Profile Completion</span>
                      <span class="completion-percentage"
                        >{{
                          selectedApplication.profile_completion_percentage ||
                          0
                        }}%</span
                      >
                    </div>
                    <div class="completion-bar">
                      <div
                        class="completion-fill"
                        :style="{
                          width:
                            (selectedApplication.profile_completion_percentage ||
                              0) + '%',
                        }"
                      ></div>
                    </div>
                    <div class="completion-items">
                      <div class="completion-item">
                        <span
                          class="completion-icon"
                          :class="{
                            completed:
                              selectedApplication.payment_details_completed,
                          }"
                          >{{
                            selectedApplication.payment_details_completed
                              ? "✓"
                              : "○"
                          }}</span
                        >
                        <span>Payment Details</span>
                      </div>
                      <div class="completion-item">
                        <span
                          class="completion-icon"
                          :class="{
                            completed:
                              selectedApplication.product_details_completed,
                          }"
                          >{{
                            selectedApplication.product_details_completed
                              ? "✓"
                              : "○"
                          }}</span
                        >
                        <span>Product Details</span>
                      </div>
                      <div class="completion-item">
                        <span
                          class="completion-icon"
                          :class="{
                            completed:
                              selectedApplication.delivery_details_completed,
                          }"
                          >{{
                            selectedApplication.delivery_details_completed
                              ? "✓"
                              : "○"
                          }}</span
                        >
                        <span>Delivery Details</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <section
              v-if="selectedApplication.payment_details_completed"
              class="detail-section"
            >
              <h3 class="section-heading">Payment & Payout Details</h3>
              <div class="detail-group">
                <div class="detail-item">
                  <label>Preferred Payout Method</label>
                  <p>
                    {{
                      formatPayoutMethod(selectedApplication.payout_method) ||
                      "Not set"
                    }}
                  </p>
                </div>
                <div class="detail-item">
                  <label>Account Holder Name</label>
                  <p>{{ selectedApplication.account_holder_name || "N/A" }}</p>
                </div>
                <div class="detail-item">
                  <label>Account Number</label>
                  <p>
                    {{ selectedApplication.decrypted_account_number || "N/A" }}
                  </p>
                </div>
                <div class="detail-item">
                  <label>Bank Name / E-Wallet Provider</label>
                  <p>{{ selectedApplication.bank_name || "N/A" }}</p>
                </div>
                <div class="detail-item">
                  <label>Billing Address</label>
                  <p>{{ selectedApplication.billing_address || "N/A" }}</p>
                </div>
              </div>
            </section>

            <section
              v-if="selectedApplication.product_details_completed"
              class="detail-section"
            >
              <h3 class="section-heading">Products & Pricing</h3>
              <div class="detail-group">
                <div class="detail-item">
                  <label>Types of Products Offered</label>
                  <p>
                    {{
                      formatProductTypes(selectedApplication.product_types) ||
                      "N/A"
                    }}
                  </p>
                </div>
                <div class="detail-item">
                  <label>Price Range</label>
                  <p>{{ selectedApplication.price_range || "N/A" }}</p>
                </div>
                <div class="detail-item">
                  <label>Same-Day Delivery</label>
                  <p>
                    {{
                      selectedApplication.same_day_delivery !== null
                        ? selectedApplication.same_day_delivery
                          ? "Yes"
                          : "No"
                        : "N/A"
                    }}
                  </p>
                </div>
                <div class="detail-item">
                  <label>Order Cut-Off Time</label>
                  <p>{{ selectedApplication.order_cutoff || "N/A" }}</p>
                </div>
              </div>
            </section>

            <section
              v-if="selectedApplication.delivery_details_completed"
              class="detail-section"
            >
              <h3 class="section-heading">Delivery & Operations</h3>
              <div class="detail-group">
                <div class="detail-item">
                  <label>Delivery Handled By</label>
                  <p>
                    {{
                      formatDeliveryHandler(
                        selectedApplication.delivery_handled_by,
                      ) || "N/A"
                    }}
                  </p>
                </div>
                <div class="detail-item">
                  <label>Delivery Fee</label>
                  <p>{{ selectedApplication.delivery_fee || "N/A" }}</p>
                </div>
                <div class="detail-item">
                  <label>Maximum Orders Per Day</label>
                  <p>{{ selectedApplication.max_orders_per_day || "N/A" }}</p>
                </div>
                <div class="detail-item">
                  <label>Lead Time Required</label>
                  <p>{{ selectedApplication.lead_time || "N/A" }}</p>
                </div>
                <div class="detail-item full-width">
                  <label>Cancellation & Refund Policy</label>
                  <p>{{ selectedApplication.cancellation_policy || "N/A" }}</p>
                </div>
              </div>
            </section>

            <section class="detail-section">
              <h3 class="section-heading">Optional Information</h3>
              <div class="detail-group">
                <div class="detail-item">
                  <label>Store Logo</label>
                  <div
                    v-if="selectedApplication.store_logo_url"
                    class="document-preview"
                    @click="viewDocument(selectedApplication.store_logo_url)"
                  >
                    <div class="doc-placeholder">🖼️ View Logo</div>
                  </div>
                  <p v-else>Not uploaded</p>
                </div>
                <div class="detail-item">
                  <label>Portfolio Photos</label>
                  <div
                    v-if="selectedApplication.portfolio_photos_urls?.length"
                    class="portfolio-grid"
                  >
                    <div
                      v-for="(
                        photo, index
                      ) in selectedApplication.portfolio_photos_urls"
                      :key="index"
                      class="portfolio-item"
                      @click="viewDocument(photo)"
                    >
                      Photo {{ index + 1 }}
                    </div>
                  </div>
                  <p v-else>Not uploaded</p>
                </div>
                <div class="detail-item">
                  <label>Facebook Page</label>
                  <p>{{ selectedApplication.facebook_page || "N/A" }}</p>
                </div>
                <div class="detail-item">
                  <label>Instagram Page</label>
                  <p>{{ selectedApplication.instagram_page || "N/A" }}</p>
                </div>
              </div>
            </section>

            <section
              v-if="selectedApplication.admin_notes"
              class="detail-section"
            >
              <h3 class="section-heading">Admin Notes</h3>
              <div class="detail-group">
                <div class="detail-item full-width">
                  <p>{{ selectedApplication.admin_notes }}</p>
                </div>
              </div>
            </section>

            <section
              v-if="selectedApplication.rejection_reason"
              class="detail-section"
            >
              <h3 class="section-heading">Rejection Reason</h3>
              <div class="detail-group">
                <div class="detail-item full-width">
                  <p>{{ selectedApplication.rejection_reason }}</p>
                </div>
              </div>
            </section>
          </div>
        </div>

        <div
          v-if="selectedApplication && selectedApplication.status === 'pending'"
          class="modal-footer"
        >
          <button
            class="btn-action btn-deny"
            @click="showRejectModal(selectedApplication)"
          >
            Deny Request
          </button>
          <button
            class="btn-action btn-approve"
            @click="showApproveConfirmation(selectedApplication)"
          >
            Approve Request
          </button>
        </div>
      </div>
    </div>

    <!-- Approve Confirmation Modal -->
    <div
      v-if="showApproveModal"
      class="modal-overlay confirmation-modal"
      @click="closeApproveModal"
    >
      <div class="modal-content confirmation-content" @click.stop>
        <div class="modal-header">
          <h2>Approve Vendor Application</h2>
          <button class="btn-close" @click="closeApproveModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="confirmation-icon success">✓</div>
          <p class="confirmation-text">
            Are you sure you want to approve the vendor application for
            <strong>{{ pendingAction?.store_name }}</strong
            >?
          </p>
          <p class="confirmation-subtext">
            The vendor will be notified via email and can proceed to complete
            their profile setup.
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn-action btn-cancel" @click="closeApproveModal" :disabled="isProcessingAction">
            Cancel
          </button>
          <button class="btn-action btn-approve" @click="confirmApprove" :disabled="isProcessingAction">
            <span v-if="isProcessingAction" class="spinner"></span>
            {{ isProcessingAction ? 'Approving...' : 'Yes, Approve' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Modal with Reason -->
    <div
      v-if="showRejectReasonModal"
      class="modal-overlay confirmation-modal"
      @click="closeRejectModal"
    >
      <div class="modal-content confirmation-content" @click.stop>
        <div class="modal-header">
          <h2>Reject Vendor Application</h2>
          <button class="btn-close" @click="closeRejectModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="confirmation-icon danger">✕</div>
          <p class="confirmation-text">
            Are you sure you want to reject the vendor application for
            <strong>{{ pendingAction?.store_name }}</strong
            >?
          </p>
          <div class="input-group">
            <label>Reason for Rejection *</label>
            <textarea
              v-model="rejectionReason"
              placeholder="Please provide a reason for rejecting this application..."
              rows="4"
              class="form-input"
              :class="{ error: rejectionReasonError }"
            ></textarea>
            <div v-if="rejectionReasonError" class="error-message">
              {{ rejectionReasonError }}
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-action btn-cancel" @click="closeRejectModal" :disabled="isProcessingAction">
            Cancel
          </button>
          <button class="btn-action btn-deny" @click="confirmReject" :disabled="isProcessingAction">
            <span v-if="isProcessingAction" class="spinner"></span>
            {{ isProcessingAction ? 'Rejecting...' : 'Reject Application' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Document Viewer Modal -->
    <div
      v-if="showDocumentViewer"
      class="modal-overlay"
      @click="closeDocumentViewer"
    >
      <div class="modal-content document-viewer" @click.stop>
        <div class="modal-header">
          <h2>Document Viewer</h2>
          <button class="btn-close" @click="closeDocumentViewer">✕</button>
        </div>
        <div class="modal-body">
          <img
            v-if="currentDocument"
            :src="currentDocument"
            alt="Document"
            class="document-image"
          />
          <div v-else class="no-document">
            <p>Document not found or could not be loaded</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useAuth } from "../../composables/useAuth";
import { useSidebarState } from "../../composables/useSidebarState";
import { toast } from "vue3-toastify";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import api from "../../plugins/axios";

const { user } = useAuth();
const { toggleMobile } = useSidebarState();

// State
const searchQuery = ref("");
const activeTab = ref("pending");
const showDetailModal = ref(false);
const showDocumentViewer = ref(false);
const showFilters = ref(false);
const showApproveModal = ref(false);
const showRejectReasonModal = ref(false);
const isProcessingAction = ref(false);
const isLoading = ref(false);
const isLoadingMessage = ref("");
const selectedApplication = ref(null);
const currentDocument = ref(null);
const pendingAction = ref(null);
const rejectionReason = ref("");
const rejectionReasonError = ref("");
const vendorApplications = ref([]);
const stats = ref({
  pending: 0,
  approved: 0,
  rejected: 0,
  under_review: 0,
});

const filters = ref({
  business_type: "",
  start_date: "",
  end_date: "",
});

const pagination = ref({
  current_page: 1,
  last_page: 1,
  from: 0,
  to: 0,
  total: 0,
  per_page: 10,
});

// Computed
const visiblePages = computed(() => {
  const pages = [];
  const totalPages = pagination.value.last_page;
  const current = pagination.value.current_page;

  if (totalPages <= 7) {
    for (let i = 1; i <= totalPages; i++) {
      pages.push(i);
    }
  } else {
    if (current <= 4) {
      for (let i = 1; i <= 5; i++) pages.push(i);
    } else if (current >= totalPages - 3) {
      for (let i = totalPages - 4; i <= totalPages; i++) pages.push(i);
    } else {
      for (let i = current - 2; i <= current + 2; i++) pages.push(i);
    }
  }

  return pages;
});

const showEndEllipsis = computed(() => {
  return (
    pagination.value.last_page > 7 &&
    pagination.value.current_page < pagination.value.last_page - 3
  );
});

const showLastPage = computed(() => {
  return (
    pagination.value.last_page > 7 &&
    pagination.value.current_page < pagination.value.last_page - 2
  );
});

// Lifecycle
onMounted(() => {
  fetchApplications();
  fetchStatistics();
});

// Watchers
watch(activeTab, () => {
  pagination.value.current_page = 1;
  fetchApplications();
});

// Methods
const fetchApplications = async () => {
  isLoading.value = true;
  try {
    isLoadingMessage.value = "Loading vendor applications...";
    const response = await api.get('/admin/vendor-applications', {
      params: {
        status: activeTab.value,
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...filters.value,
        ...(searchQuery.value && { search: searchQuery.value })
      }
    });

    const data = response.data;

    if (data.data) {
      vendorApplications.value = data.data;
      pagination.value = data.meta || {
        current_page: 1,
        last_page: 1,
        from: 0,
        to: 0,
        total: 0,
        per_page: 10,
      };
    } else {
      vendorApplications.value = data.applications || data || [];
    }
  } catch (error) {
    console.error("Error fetching applications:", error);
    toast.error("Failed to load vendor applications");
    vendorApplications.value = [];
    pagination.value = {
      current_page: 1,
      last_page: 1,
      from: 0,
      to: 0,
      total: 0,
      per_page: 10,
    };
  } finally {
    isLoading.value = false;
  }
};

const fetchStatistics = async () => {
  try {
    const response = await api.get('/admin/vendor-applications/statistics');
    const data = response.data;
    stats.value = data;
  } catch (error) {
    console.error("Error fetching statistics:", error);
    stats.value = {
      pending: 0,
      approved: 0,
      rejected: 0,
      under_review: 0,
    };
  }
};

const setTab = (tab) => {
  activeTab.value = tab;
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    pagination.value.current_page = page;
    fetchApplications();
  }
};

const toggleFilters = () => {
  showFilters.value = !showFilters.value;
};

const clearFilters = () => {
  filters.value = {
    business_type: "",
    start_date: "",
    end_date: "",
  };
  fetchApplications();
};

const onSearch = debounce(() => {
  pagination.value.current_page = 1;
  fetchApplications();
}, 500);

const exportData = async () => {
  try {
    toast.info("Preparing export...");
    const params = {
      status: activeTab.value,
      ...filters.value,
    };

    const response = await api.get('/admin/vendor-applications/export', {
      params,
      responseType: 'blob' // Important for file downloads
    });

    const blob = new Blob([response.data], { type: response.headers['content-type'] });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `vendor-applications-${activeTab.value}-${
      new Date().toISOString().split("T")[0]
    }.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    toast.success("Export completed successfully!");
  } catch (error) {
    console.error("Error exporting data:", error);
    toast.error("Failed to export data");
  }
};

const viewDetails = (application) => {
  selectedApplication.value = application;
  showDetailModal.value = true;
  document.body.style.overflow = "hidden";
};

const closeModal = () => {
  showDetailModal.value = false;
  selectedApplication.value = null;
  document.body.style.overflow = "auto";
};

const viewDocument = (url) => {
  if (url) {
    currentDocument.value = url;
    showDocumentViewer.value = true;
  } else {
    toast.error("Document URL is not available");
  }
};

const closeDocumentViewer = () => {
  showDocumentViewer.value = false;
  currentDocument.value = null;
};

// Approve confirmation
const showApproveConfirmation = (application) => {
  pendingAction.value = application;
  showApproveModal.value = true;
  document.body.style.overflow = "hidden";
};

const closeApproveModal = () => {
  showApproveModal.value = false;
  pendingAction.value = null;
  document.body.style.overflow = "auto";
};

const confirmApprove = async () => {
  if (!pendingAction.value || isProcessingAction.value) return;

  isProcessingAction.value = true;
  try {
    const response = await api.post(
      `/admin/vendor-applications/${pendingAction.value.id}/approve`
    );

    const data = response.data;
    toast.success(data.message || "Vendor application approved successfully!");

    closeApproveModal();
    if (showDetailModal.value) {
      closeModal();
    }
    fetchApplications();
    fetchStatistics();
  } catch (error) {
    console.error("Error approving application:", error);
    toast.error("Failed to approve application: " + error.message);
  } finally {
    isProcessingAction.value = false;
  }
};

// Reject with reason
const showRejectModal = (application) => {
  pendingAction.value = application;
  rejectionReason.value = "";
  rejectionReasonError.value = "";
  showRejectReasonModal.value = true;
  document.body.style.overflow = "hidden";
};

const closeRejectModal = () => {
  showRejectReasonModal.value = false;
  pendingAction.value = null;
  rejectionReason.value = "";
  rejectionReasonError.value = "";
  document.body.style.overflow = "auto";
};

const confirmReject = async () => {
  if (!pendingAction.value || isProcessingAction.value) return;

  // Validate rejection reason
  if (!rejectionReason.value.trim()) {
    rejectionReasonError.value = "Please provide a reason for rejection";
    return;
  }

  if (rejectionReason.value.trim().length < 10) {
    rejectionReasonError.value =
      "Please provide a more detailed reason (at least 10 characters)";
    return;
  }

  rejectionReasonError.value = "";
  isProcessingAction.value = true;

  try {
    const response = await api.post(`/admin/vendor-applications/${pendingAction.value.id}/reject`, {
      rejection_reason: rejectionReason.value.trim()
    });

    const data = response.data;
    toast.success(data.message || "Vendor application rejected successfully!");

    closeRejectModal();
    if (showDetailModal.value) {
      closeModal();
    }
    fetchApplications();
    fetchStatistics();
  } catch (error) {
    console.error("Error rejecting application:", error);
    toast.error("Failed to reject application: " + error.message);
  } finally {
    isProcessingAction.value = false;
  }
};

// Formatting functions
const formatBusinessType = (type) => {
  const types = {
    individual: "Sole Proprietor",
    partnership: "Partnership",
    corporation: "Corporation",
  };
  return types[type] || type;
};

const formatStatus = (status) => {
  const statuses = {
    pending: "Pending",
    approved: "Approved",
    rejected: "Rejected",
    under_review: "Under Review",
  };
  return statuses[status] || status;
};

const formatPayoutMethod = (method) => {
  const methods = {
    bank: "Bank Transfer",
    gcash: "GCash",
    maya: "Maya",
  };
  return methods[method] || method;
};

const formatDeliveryHandler = (handler) => {
  return handler === "vendor" ? "Vendor" : "Platform";
};

const formatProductTypes = (productTypes) => {
  if (!productTypes) return "N/A";

  if (Array.isArray(productTypes)) {
    return productTypes.join(", ");
  }

  try {
    const parsed = JSON.parse(productTypes);
    if (Array.isArray(parsed)) {
      return parsed.join(", ");
    }
    return parsed;
  } catch (e) {
    return productTypes;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";

  try {
    const date = new Date(dateString);
    return date
      .toLocaleDateString("en-US", {
        month: "2-digit",
        day: "2-digit",
        year: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      })
      .replace(",", " at");
  } catch (e) {
    return dateString;
  }
};

const extractLocation = (address) => {
  if (!address) return "Location not specified";

  const parts = address.split(",");
  if (parts.length >= 2) {
    const city = parts[parts.length - 2]?.trim();
    const country = parts[parts.length - 1]?.trim();
    return `${city}, ${country}`;
  }

  return address;
};

// Utility function
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}
</script>
<style scoped>
* {
  font-family: "Poppins", "Segoe UI", sans-serif;
  box-sizing: border-box;
}

.admin-layout {
  display: flex;
  min-height: 100vh;
  background: #f5f7fa;
}

.admin-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: linear-gradient(135deg, #48bb78, #2f855a);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.admin-avatar svg {
  width: 18px;
  height: 18px;
  color: white;
  stroke: white;
}

.profile-role {
  font-size: 11px;
  color: #48bb78;
  font-weight: 600;
}

/* Main Content */
.main-content {
  margin-left: 240px;
  flex: 1;
  padding: 24px;
  transition: margin-left 0.3s ease;
}

.content-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.2s;
}

.mobile-toggle:hover {
  background: #f1f5f9;
  color: #1e293b;
}

@media (max-width: 968px) {
  .mobile-toggle {
    display: flex;
  }
}

.page-title {
  font-size: 28px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}

.search-box {
  position: relative;
}

.search-box input {
  width: 280px;
  padding: 10px 40px 10px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.3s;
}

.search-box input:focus {
  outline: none;
  border-color: #48bb78;
}

.search-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 16px;
  color: #a0aec0;
}

.icon-btn {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: white;
  cursor: pointer;
  font-size: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.icon-btn:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-left: 16px;
  border-left: 1px solid #e2e8f0;
}

.profile-img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
}

.profile-info {
  display: flex;
  flex-direction: column;
}

.profile-name {
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
}

.profile-email {
  font-size: 12px;
  color: #718096;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 24px;
  border-bottom: 2px solid #e2e8f0;
}

.tab-btn {
  padding: 12px 24px;
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  color: #718096;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  margin-bottom: -2px;
}

.tab-btn:hover {
  color: #2d3748;
}

.tab-btn.active {
  color: #2d3748;
  border-bottom-color: #48bb78;
}

/* Content Body */
.content-body {
  background: white;
  border-radius: 12px;
  padding: 24px;
}

.content-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.section-title {
  font-size: 20px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.action-buttons {
  display: flex;
  gap: 12px;
}

.btn-filter,
.btn-export {
  padding: 10px 20px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s;
}

.btn-filter:hover,
.btn-export:hover {
  background: #f7fafc;
  border-color: #48bb78;
  color: #48bb78;
}

/* Filters Panel */
.filters-panel {
  background: #f7fafc;
  padding: 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.filter-group label {
  font-size: 13px;
  font-weight: 500;
  color: #718096;
}

.filter-group select,
.filter-group input {
  padding: 8px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
}

.filter-group input[type="date"] {
  width: 100%;
}

.btn-clear-filters {
  padding: 8px 16px;
  background: #e53e3e;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.3s;
}

.btn-clear-filters:hover {
  background: #c53030;
}

.empty-state {
  text-align: center;
  padding: 48px;
  color: #718096;
  font-size: 16px;
  border: 2px dashed #e2e8f0;
  border-radius: 8px;
  margin: 20px 0;
}

/* Table */
.requests-table {
  width: 100%;
}

.table-header {
  display: flex;
  padding: 12px 16px;
  background: #f7fafc;
  border-radius: 8px;
  margin-bottom: 8px;
}

.th {
  font-size: 13px;
  font-weight: 600;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.table-row {
  display: flex;
  padding: 16px;
  border-bottom: 1px solid #f7fafc;
  align-items: center;
  cursor: pointer;
  transition: all 0.3s;
}

.table-row:hover {
  background: #f7fafc;
}

.td {
  font-size: 14px;
  color: #2d3748;
  display: flex;
  align-items: center;
}

.store-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.store-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #48bb78;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 16px;
}

.store-name {
  font-weight: 500;
  color: #2d3748;
}

.store-location {
  font-size: 12px;
  color: #718096;
}

.status-badge {
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  display: inline-block;
}

.status-badge.pending {
  background: #fef3c7;
  color: #92400e;
}

.status-badge.approved {
  background: #d1fae5;
  color: #065f46;
}

.status-badge.rejected {
  background: #fee2e2;
  color: #991b1b;
}

.action-buttons-cell {
  display: flex;
  gap: 4px;
  align-items: center;
}

.btn-action-sm {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: none;
  cursor: pointer;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.btn-approve-sm {
  background: #d1fae5;
  color: #065f46;
}

.btn-approve-sm:hover {
  background: #a7f3d0;
}

.btn-deny-sm {
  background: #fee2e2;
  color: #991b1b;
}

.btn-deny-sm:hover {
  background: #fecaca;
}

.btn-more {
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  border-radius: 6px;
  cursor: pointer;
  font-size: 20px;
  font-weight: bold;
  transition: all 0.3s;
}

.btn-more:hover {
  background: #f7fafc;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  margin-top: 24px;
}

.page-btn {
  padding: 8px 12px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s;
  min-width: 36px;
}

.page-btn:hover:not(:disabled) {
  border-color: #48bb78;
  color: #48bb78;
}

.page-btn.active {
  background: #2d3748;
  color: white;
  border-color: #2d3748;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-dots {
  padding: 0 4px;
  color: #718096;
}

.pagination-info {
  margin-left: 16px;
  font-size: 14px;
  color: #718096;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 3000;
  padding: 20px;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 1000px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  animation: slideUp 0.3s ease;
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
  padding: 24px;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
  font-size: 22px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.btn-close {
  width: 36px;
  height: 36px;
  border: none;
  background: #f7fafc;
  border-radius: 50%;
  cursor: pointer;
  font-size: 18px;
  color: #718096;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.btn-close:hover {
  background: #e2e8f0;
  color: #2d3748;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.details-grid {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.detail-section {
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 24px;
}

.detail-section:last-child {
  border-bottom: none;
}

.section-heading {
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 16px 0;
}

.detail-group {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.detail-item.full-width {
  grid-column: 1 / -1;
}

.detail-item label {
  font-size: 13px;
  font-weight: 500;
  color: #718096;
}

.detail-item p {
  font-size: 14px;
  color: #2d3748;
  margin: 0;
}

.document-preview {
  margin-top: 4px;
}

.doc-placeholder {
  padding: 32px;
  background: #f7fafc;
  border: 2px dashed #e2e8f0;
  border-radius: 8px;
  text-align: center;
  color: #718096;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s;
}

.doc-placeholder:hover {
  border-color: #48bb78;
  color: #48bb78;
}

.portfolio-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
  gap: 8px;
  margin-top: 4px;
}

.portfolio-item {
  padding: 8px;
  background: #f7fafc;
  border: 1px dashed #e2e8f0;
  border-radius: 6px;
  text-align: center;
  font-size: 12px;
  color: #718096;
  cursor: pointer;
  transition: all 0.3s;
}

.portfolio-item:hover {
  border-color: #48bb78;
  color: #48bb78;
}

.modal-footer {
  display: flex;
  gap: 12px;
  padding: 24px;
  border-top: 1px solid #e2e8f0;
  justify-content: flex-end;
}

.btn-action {
  padding: 12px 32px;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-action:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: #ffffff;
  animation: spin 1s ease-in-out infinite;
  margin-right: 8px;
}

.btn-deny .spinner {
  border: 2px solid rgba(229, 62, 62, 0.3);
  border-top-color: #e53e3e;
}

.btn-cancel .spinner,
.btn-cancel:disabled {
  opacity: 0.7;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.btn-deny {
  background: white;
  color: #e53e3e;
  border: 1px solid #e53e3e;
}

.btn-deny:hover {
  background: #fff5f5;
}

.btn-approve {
  background: #48bb78;
  color: white;
}

.btn-approve:hover {
  background: #38a169;
  transform: translateY(-1px);
}

.btn-cancel {
  background: white;
  color: #718096;
  border: 1px solid #e2e8f0;
}

.btn-cancel:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

/* Confirmation Modal */
.confirmation-modal .modal-content {
  max-width: 500px;
}
.confirmation-content .modal-body {
  text-align: center;
  padding: 32px 24px;
}
.confirmation-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  margin: 0 auto 20px;
}
.confirmation-icon.success {
  background: #d1fae5;
  color: #065f46;
}
.confirmation-icon.danger {
  background: #fee2e2;
  color: #991b1b;
}
.confirmation-text {
  font-size: 16px;
  color: #2d3748;
  margin-bottom: 12px;
  line-height: 1.5;
}
.confirmation-subtext {
  font-size: 14px;
  color: #718096;
  margin-bottom: 24px;
}
.input-group {
  text-align: left;
  margin-top: 20px;
}
.input-group label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
  margin-bottom: 8px;
}
.form-input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  font-family: inherit;
  transition: all 0.2s;
}
.form-input:focus {
  outline: none;
  border-color: #48bb78;
}
.form-input.error {
  border-color: #e53e3e;
}
.error-message {
  color: #e53e3e;
  font-size: 12px;
  margin-top: 4px;
}
/* Profile Completion */
.completion-card {
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 20px;
}
.completion-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.completion-label {
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
}
.completion-percentage {
  font-size: 18px;
  font-weight: 600;
  color: #48bb78;
}
.completion-bar {
  height: 8px;
  background: #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 16px;
}
.completion-fill {
  height: 100%;
  background: linear-gradient(90deg, #48bb78 0%, #38a169 100%);
  transition: width 0.3s ease;
}
.completion-items {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.completion-item {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: #4a5568;
}
.completion-icon {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  background: #e2e8f0;
  color: #a0aec0;
  flex-shrink: 0;
}
.completion-icon.completed {
  background: #d1fae5;
  color: #065f46;
}
/* Document Viewer */
.document-viewer {
  max-width: 800px;
}
.document-image {
  width: 100%;
  height: auto;
  max-height: 60vh;
  object-fit: contain;
  border-radius: 8px;
}
.no-document {
  text-align: center;
  padding: 48px;
  color: #718096;
}
/* Responsive */
@media (max-width: 1200px) {
  .main-content {
    margin-left: 200px;
  }
  .detail-group {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 968px) {
  .main-content {
    margin-left: 0;
  }
  .table-header,
  .table-row {
    overflow-x: auto;
  }
}
@media (max-width: 640px) {
  .content-header {
    flex-direction: row;
    align-items: center;
    gap: 12px;
  }
  .header-actions {
    width: 100%;
    flex-wrap: wrap;
  }
  .search-box input {
    width: 100%;
  }
  .content-actions {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  .action-buttons {
    width: 100%;
  }
  .btn-filter,
  .btn-export {
    flex: 1;
  }
}
</style>
