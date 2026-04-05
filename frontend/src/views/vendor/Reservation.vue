<template>
  <vendorHeader />
  <div class="vendor-reservation-page">

    <VendorSidebar />

    <div class="main-content">
      <!-- Header -->
      <div class="page-header">
        <h1 class="page-title">Reservation Management</h1>
        <div class="header-actions">
          <button class="btn-add-closed" @click="showCloseDateModal = true">
            🔴 Mark Date as Closed
          </button>
        </div>
      </div>

      <LoadingOverlay :visible="isLoading" message="Loading reservations..." />

      <div class="dashboard-content">
        <!-- Calendar Section -->
        <div class="calendar-section">
          <div class="section-header">
            <h2>Reservation Calendar</h2>
            <div class="calendar-stats">
              <div class="stat-item">
                <span class="stat-label">Max Orders/Day:</span>
                <span class="stat-value">{{
                  vendorInfo?.max_orders_per_day || 10
                }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Default Delivery Fee:</span>
                <span class="stat-value"
                  >₱{{
                    formatPrice(vendorInfo?.default_delivery_fee || 50)
                  }}</span
                >
              </div>
            </div>
          </div>

          <ReservationCalendar
            :key="calendarRefreshKey"
            :vendor-id="vendorId"
            :closure-mode="true"
            v-model="selectedDate"
            @date-selected="handleDateSelected"
          />
        </div>

        <!-- Orders for Selected Date -->
        <div v-if="selectedDate" class="orders-section">
          <div class="section-header">
            <h3>Orders for {{ formatDate(selectedDate) }}</h3>
            <span class="orders-count"
              >{{ ordersForDate.length }}
              {{ ordersForDate.length === 1 ? "order" : "orders" }}</span
            >
          </div>

          <!-- No Orders State -->
          <div v-if="ordersForDate.length === 0" class="no-orders">
            <div class="no-orders-icon">📋</div>
            <p>No orders for this date yet.</p>
          </div>

          <!-- Orders List -->
          <div v-else class="orders-list">
            <div
              v-for="order in ordersForDate"
              :key="order.id"
              class="order-card"
            >
              <!-- Order Header -->
              <div class="order-header">
                <div class="order-number">
                  <span class="label">Order #</span>
                  <span class="value">{{ order.order_number }}</span>
                </div>
                <div class="order-status-badges">
                  <span class="status-badge" :class="'status-' + order.status">
                    {{ formatStatus(order.status) }}
                  </span>
                  <span
                    class="payment-badge"
                    :class="'payment-' + order.payment_status"
                  >
                    {{ formatPaymentStatus(order.payment_status) }}
                  </span>
                </div>
              </div>

              <!-- Customer Information -->
              <div class="customer-section">
                <h4 class="section-title">
                  <span class="title-icon">👤</span>
                  Customer Details
                </h4>
                <div class="customer-info">
                  <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value">{{ order.customer.name }}</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Contact:</span>
                    <span class="info-value">{{
                      order.customer.contact_number
                    }}</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Delivery Address:</span>
                    <span class="info-value">{{ order.customer.address }}</span>
                  </div>
                </div>
              </div>

              <!-- Order Items -->
              <div class="items-section">
                <h4 class="section-title">
                  <span class="title-icon">💐</span>
                  Order Items
                </h4>
                <div class="items-list">
                  <div
                    v-for="(item, idx) in order.items"
                    :key="idx"
                    class="order-item"
                  >
                    <img
                      :src="item.product_image || '/placeholder.png'"
                      :alt="item.product_name"
                      class="item-image"
                      @error="handleImageError"
                    />
                    <div class="item-details">
                      <h5 class="item-name">{{ item.product_name }}</h5>
                      <p class="item-quantity">Quantity: {{ item.quantity }}</p>
                      <p class="item-price">
                        ₱{{ formatPrice(item.unit_price) }} ×
                        {{ item.quantity }}
                      </p>

                      <!-- 3D Model Button -->
                      <button
                        v-if="item.model_3d_url"
                        class="btn-view-3d"
                        @click="
                          view3DModel(item.model_3d_url, item.product_name)
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
                          <path d="M12 2L2 7l10 5 10-5-10-5z" />
                          <path d="M2 17l10 5 10-5" />
                          <path d="M2 12l10 5 10-5" />
                        </svg>
                        View 3D Model
                      </button>
                    </div>
                    <div class="item-subtotal">
                      ₱{{ formatPrice(item.subtotal) }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Order Notes -->
              <div v-if="order.customer_notes" class="notes-section">
                <h4 class="section-title">
                  <span class="title-icon">📝</span>
                  Special Instructions
                </h4>
                <p class="notes-text">{{ order.customer_notes }}</p>
              </div>

              <!-- Order Total -->
              <div class="order-footer">
                <div class="order-summary">
                  <div class="summary-row">
                    <span>Payment Method:</span>
                    <span class="payment-method">{{
                      formatPaymentMethod(order.payment_method)
                    }}</span>
                  </div>
                  <div class="summary-row total-row">
                    <span>Total Amount:</span>
                    <span class="total-amount"
                      >₱{{ formatPrice(order.total_amount) }}</span
                    >
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Closed Dates Management -->
        <div class="closed-dates-section">
          <div class="section-header">
            <h3>Closed Dates</h3>
            <span class="closed-count"
              >{{ closedDates.length }}
              {{ closedDates.length === 1 ? "date" : "dates" }}</span
            >
          </div>

          <div v-if="closedDates.length === 0" class="no-closed-dates">
            <p>No closed dates scheduled.</p>
          </div>

          <div v-else class="closed-dates-list">
            <div
              v-for="closedDate in closedDates"
              :key="closedDate.id"
              class="closed-date-item"
            >
              <div class="closed-date-info">
                <span class="closed-date-icon">🔴</span>
                <div class="closed-date-details">
                  <span class="closed-date-value">{{
                    formatDate(closedDate.closed_date)
                  }}</span>
                  <span class="closed-date-reason">{{
                    closedDate.reason || "No reason provided"
                  }}</span>
                </div>
              </div>
              <button
                class="btn-remove-closed"
                @click="removeClosedDate(closedDate.id)"
                title="Remove this closed date"
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
                  <path d="M18 6L6 18M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 3D Model Viewer Modal -->
    <div
      v-if="viewing3DModel"
      class="modal-overlay"
      @click="viewing3DModel = null"
    >
      <div class="modal-content model-viewer-modal" @click.stop>
        <div class="modal-header">
          <h3>{{ current3DModelName }}</h3>
          <button class="btn-close-modal" @click="viewing3DModel = null">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M18 6L6 18M6 6l12 12" />
            </svg>
          </button>
        </div>
        <iframe
          :src="viewing3DModel"
          class="model-iframe"
          frameborder="0"
          allow="
            accelerometer;
            autoplay;
            encrypted-media;
            gyroscope;
            picture-in-picture;
          "
        ></iframe>
      </div>
    </div>

    <!-- Mark Date as Closed Modal -->
    <div
      v-if="showCloseDateModal"
      class="modal-overlay"
      @click="showCloseDateModal = false"
    >
      <div class="modal-content close-date-modal" @click.stop>
        <div class="modal-header">
          <h3>Mark Date as Closed</h3>
          <button class="btn-close-modal" @click="showCloseDateModal = false">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M18 6L6 18M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label for="closeDate">Date *</label>
            <input
              type="date"
              id="closeDate"
              v-model="closeDateForm.date"
              :min="today"
              :max="maxCloseDate"
              class="form-input"
            />
            <small class="form-hint">
              You can only mark dates as closed from today up to 3 months ahead.
            </small>
          </div>

          <div class="form-group">
            <label for="closeReason">Reason (Optional)</label>
            <input
              type="text"
              id="closeReason"
              v-model="closeDateForm.reason"
              placeholder="e.g., Holiday, Maintenance"
              class="form-input"
              maxlength="255"
            />
          </div>

          <div class="form-group">
            <label for="closeType">Type</label>
            <select
              id="closeType"
              v-model="closeDateForm.type"
              class="form-select"
            >
              <option value="manual">Manual</option>
              <option value="holiday">Holiday</option>
              <option value="emergency">Emergency</option>
            </select>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" @click="showCloseDateModal = false">
            Cancel
          </button>
          <button
            class="btn-confirm"
            @click="submitCloseDate"
            :disabled="!closeDateForm.date"
          >
            Mark as Closed
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { toast } from "vue3-toastify";
import vendorHeader from "../../layouts/vendorHeader.vue";

import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";
import ReservationCalendar from "../../layouts/Calendar/ReservationCalendar.vue";
import api from "../../plugins/axios";


// State
const isLoading = ref(false);
const vendorId = ref(null);
const vendorInfo = ref(null);
const selectedDate = ref(null);
const ordersForDate = ref([]);
const closedDates = ref([]);
const showCloseDateModal = ref(false);
const viewing3DModel = ref(null);
const current3DModelName = ref("");
const calendarRefreshKey = ref(0);

const today = computed(() => new Date().toISOString().split("T")[0]);
const maxCloseDate = computed(() => {
  const max = new Date();
  max.setMonth(max.getMonth() + 3);
  return max.toISOString().split("T")[0];
});

const closeDateForm = ref({
  date: "",
  reason: "",
  type: "manual",
});

// Lifecycle
onMounted(async () => {
  await loadVendorInfo();
  await loadClosedDates();
});

// Methods
async function loadVendorInfo() {
  try {
    isLoading.value = true;
    const response = await api.get("/vendor/profile");

    if (response.data.success) {
      vendorInfo.value = response.data.data;
      vendorId.value = response.data.data.id;
    }
  } catch (error) {
    console.error("Error loading vendor info:", error);
    toast.error("Failed to load vendor information");
  } finally {
    isLoading.value = false;
  }
}

async function handleDateSelected(data) {
  try {
    isLoading.value = true;
    const response = await api.get("/vendor/reservations/orders-for-date", {
      params: { date: data.date },
    });

    if (response.data.success) {
      ordersForDate.value = response.data.data.orders;
    }
  } catch (error) {
    console.error("Error loading orders:", error);
    toast.error("Failed to load orders for this date");
  } finally {
    isLoading.value = false;
  }
}

async function loadClosedDates() {
  try {
    const response = await api.get("/vendor/reservations/closed-dates");

    if (response.data.success) {
      closedDates.value = response.data.data;
    }
  } catch (error) {
    console.error("Error loading closed dates:", error);
  }
}

async function submitCloseDate() {
  try {
    if (!closeDateForm.value.date) {
      toast.warning("Please select a date");
      return;
    }

    isLoading.value = true;
    const response = await api.post(
      "/vendor/reservations/close-date",
      closeDateForm.value,
    );

    if (response.data.success) {
      toast.success("Date marked as closed successfully");
      showCloseDateModal.value = false;
      closeDateForm.value = { date: "", reason: "", type: "manual" };
      await loadClosedDates();
      calendarRefreshKey.value += 1;
      selectedDate.value = null;
    }
  } catch (error) {
    console.error("Error marking date as closed:", error);
    toast.error(
      error.response?.data?.message || "Failed to mark date as closed",
    );
  } finally {
    isLoading.value = false;
  }
}

async function removeClosedDate(id) {
  try {
    const confirmed = confirm(
      "Are you sure you want to remove this closed date?",
    );
    if (!confirmed) return;

    isLoading.value = true;
    const response = await api.delete(`/vendor/reservations/close-date/${id}`);

    if (response.data.success) {
      toast.success("Closed date removed successfully");
      await loadClosedDates();
      calendarRefreshKey.value += 1;
      selectedDate.value = null;
    }
  } catch (error) {
    console.error("Error removing closed date:", error);
    toast.error("Failed to remove closed date");
  } finally {
    isLoading.value = false;
  }
}

function view3DModel(url, productName) {
  viewing3DModel.value = url;
  current3DModelName.value = productName;
}

function formatDate(dateStr) {
  const date = new Date(dateStr);
  return date.toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

function formatPrice(price) {
  return parseFloat(price || 0).toFixed(2);
}

function formatStatus(status) {
  const statusMap = {
    pending: "Pending",
    processing: "Processing",
    completed: "Completed",
    cancelled: "Cancelled",
    failed: "Failed",
  };
  return statusMap[status] || status;
}

function formatPaymentStatus(status) {
  const statusMap = {
    unpaid: "Unpaid",
    paid: "Paid",
    refunded: "Refunded",
    failed: "Failed",
  };
  return statusMap[status] || status;
}

function formatPaymentMethod(method) {
  const methodMap = {
    cod: "Cash on Delivery",
    gcash: "GCash",
    maya: "Maya",
    card: "Credit/Debit Card",
    bank_transfer: "Bank Transfer",
  };
  return methodMap[method] || method;
}

function handleImageError(event) {
  event.target.src = "/placeholder.png";
}
</script>

<style scoped>
/* Base Layout */
.vendor-reservation-page {
  display: flex;
  min-height: 100vh;
  background: #f5f7fa;
  padding-top: 80px;
}


.main-content {
  flex: 1;
  margin-left: 260px;
  padding: 24px;
  transition: margin-left 0.3s ease;
}

@media (max-width: 968px) {
  .main-content {
    margin-left: 0 !important;
    padding: 16px;
  }
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
}

.page-title {
  font-size: 28px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.btn-add-closed {
  padding: 12px 24px;
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-add-closed:hover {
  background: #dc2626;
  transform: translateY(-2px);
}

/* Dashboard Content */
.dashboard-content {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Section Styles */
.calendar-section,
.orders-section,
.closed-dates-section {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e2e8f0;
}

.section-header h2,
.section-header h3 {
  font-size: 20px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.calendar-stats {
  display: flex;
  gap: 24px;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #f7fafc;
  border-radius: 8px;
}

.stat-label {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
}

.stat-value {
  font-size: 14px;
  color: #2d3748;
  font-weight: 600;
}

.orders-count,
.closed-count {
  padding: 4px 12px;
  background: #e6f7ff;
  color: #0369a1;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}

/* No Data States */
.no-orders,
.no-closed-dates {
  text-align: center;
  padding: 60px 20px;
  color: #718096;
}

.no-orders-icon {
  font-size: 48px;
  margin-bottom: 16px;
  opacity: 0.5;
}

/* Orders List */
.orders-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.order-card {
  background: #fafafa;
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #e2e8f0;
  transition: all 0.3s;
}

.order-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

/* Order Header */
.order-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 16px;
  border-bottom: 1px solid #e2e8f0;
  margin-bottom: 20px;
}

.order-number {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.order-number .label {
  font-size: 12px;
  color: #718096;
  font-weight: 500;
}

.order-number .value {
  font-size: 16px;
  color: #2d3748;
  font-weight: 600;
}

.order-status-badges {
  display: flex;
  gap: 8px;
}

.status-badge,
.payment-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.status-pending {
  background: #fef3c7;
  color: #92400e;
}
.status-processing {
  background: #dbeafe;
  color: #1e40af;
}
.status-completed {
  background: #d1fae5;
  color: #065f46;
}
.status-cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.payment-unpaid {
  background: #fee2e2;
  color: #991b1b;
}
.payment-paid {
  background: #d1fae5;
  color: #065f46;
}

/* Sections within Order Card */
.customer-section,
.items-section,
.notes-section {
  margin-bottom: 20px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 12px 0;
}

.title-icon {
  font-size: 16px;
}

/* Customer Info */
.customer-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  background: white;
  border-radius: 8px;
}

.info-row {
  display: flex;
  gap: 12px;
}

.info-label {
  font-size: 13px;
  font-weight: 500;
  color: #718096;
  min-width: 120px;
}

.info-value {
  font-size: 13px;
  color: #2d3748;
  font-weight: 500;
}

/* Order Items */
.items-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.order-item {
  display: grid;
  grid-template-columns: 80px 1fr auto;
  gap: 16px;
  padding: 12px;
  background: white;
  border-radius: 8px;
  align-items: center;
}

.item-image {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  object-fit: cover;
}

.item-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.item-name {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.item-quantity,
.item-price {
  font-size: 12px;
  color: #718096;
  margin: 0;
}

.btn-view-3d {
  margin-top: 8px;
  padding: 6px 12px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.3s;
}

.btn-view-3d:hover {
  background: #38a169;
  transform: translateY(-1px);
}

.item-subtotal {
  font-size: 16px;
  font-weight: 600;
  color: #48bb78;
}

/* Notes */
.notes-text {
  padding: 12px;
  background: white;
  border-radius: 8px;
  font-size: 13px;
  color: #2d3748;
  line-height: 1.6;
  margin: 0;
}

/* Order Footer */
.order-footer {
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
}

.order-summary {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
  color: #718096;
}

.total-row {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
  padding-top: 8px;
  margin-top: 8px;
  border-top: 1px solid #e2e8f0;
}

.total-amount {
  font-size: 18px;
  color: #48bb78;
}

/* Closed Dates List */
.closed-dates-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.closed-date-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: #fafafa;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.closed-date-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.closed-date-icon {
  font-size: 20px;
}

.closed-date-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.closed-date-value {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
}

.closed-date-reason {
  font-size: 12px;
  color: #718096;
}

.btn-remove-closed {
  width: 36px;
  height: 36px;
  border: none;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-remove-closed:hover {
  background: #fecaca;
  transform: scale(1.05);
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}

.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 500px;
  width: 100%;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.model-viewer-modal {
  max-width: 900px;
  max-height: 80vh;
}

.modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.btn-close-modal {
  width: 32px;
  height: 32px;
  border: none;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.btn-close-modal:hover {
  background: #fecaca;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
  margin-bottom: 8px;
}

.form-input,
.form-select {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 15px;
  color: #2d3748;
  transition: all 0.3s;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

.form-hint {
  display: block;
  margin-top: 8px;
  font-size: 12px;
  color: #718096;
}

.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

.btn-cancel,
.btn-confirm {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-cancel {
  background: white;
  color: #2d3748;
  border: 1px solid #e2e8f0;
}

.btn-cancel:hover {
  background: #f7fafc;
}

.btn-confirm {
  background: #48bb78;
  color: white;
  border: none;
}

.btn-confirm:hover:not(:disabled) {
  background: #38a169;
}

.btn-confirm:disabled {
  background: #cbd5e0;
  cursor: not-allowed;
}

/* 3D Model Viewer */
.model-iframe {
  width: 100%;
  height: 600px;
  border: none;
}

/* Responsive */
@media (max-width: 1200px) {
  .main-content {
    margin-left: 200px;
  }
}

@media (max-width: 968px) {
  .main-content {
    margin-left: 0;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .order-item {
    grid-template-columns: 60px 1fr;
    gap: 12px;
  }

  .item-subtotal {
    grid-column: 2;
    text-align: right;
  }

  .calendar-stats {
    flex-direction: column;
    gap: 8px;
  }
}

@media (max-width: 640px) {
  .main-content {
    padding: 16px;
  }

  .order-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .order-status-badges {
    flex-wrap: wrap;
  }
}
</style>
