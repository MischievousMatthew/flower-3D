<!-- frontend/src/views/vendor/OrderDetails.vue -->
<template>
  <div class="order-details-page">
    <VendorSidebar />

    <div class="order-details-container">
      <!-- Header -->
      <div class="page-header">
        <div class="header-left">
          <button @click="goBack" class="btn-back">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"
              />
            </svg>
            Back
          </button>
          <div class="header-title">
            <h1>Order Details</h1>
            <p>Track your shipment and manage your order</p>
          </div>
        </div>
        <div class="header-actions">
          <button @click="printOrder" class="btn-print">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"
              />
            </svg>
            Print
          </button>
          <button @click="contactSupport" class="btn-support">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"
              />
            </svg>
            Contact Support
          </button>
        </div>
      </div>

      <LoadingOverlay :visible="isLoading" :message="loadingMessage" />

      <div v-if="!isLoading && order" class="content-grid">
        <!-- Left Column -->
        <div class="left-column">
          <!-- Order Info Card -->
          <div class="info-card">
            <div class="card-header">
              <h2>Order #{{ order.order_number }}</h2>
              <span
                class="order-status-badge"
                :class="`status-${order.status}`"
              >
                {{ formatStatus(order.status) }}
              </span>
            </div>
            <div class="order-meta">
              <div class="meta-item">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                >
                  <path
                    fill="currentColor"
                    d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"
                  />
                </svg>
                <span>Placed on: {{ formatDate(order.created_at) }}</span>
              </div>
              <div class="meta-item">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                >
                  <path
                    fill="currentColor"
                    d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"
                  />
                </svg>
                <span
                  >Reservation: {{ formatDate(order.reservation_date) }}</span
                >
              </div>
            </div>
          </div>

          <!-- Order Items -->
          <div class="items-card">
            <h2>Order Items</h2>
            <div class="items-list">
              <div
                v-for="item in order.items"
                :key="item.id"
                class="order-item"
              >
                <div class="item-image">
                  <img
                    :src="
                      item.product_image || 'https://via.placeholder.com/80'
                    "
                    :alt="item.product_name"
                    @error="handleImageError"
                  />
                </div>
                <div class="item-details">
                  <h3>{{ item.product_name }}</h3>
                  <p class="item-meta">Qty: {{ item.quantity }}</p>
                  <p class="item-price">₱{{ formatPrice(item.unit_price) }}</p>
                </div>
                <div class="item-total">₱{{ formatPrice(item.subtotal) }}</div>
              </div>
            </div>

            <!-- Return Policy -->
            <div class="return-policy">
              <h4>Return Policy</h4>
              <p>
                This item can be returned within 30 days of delivery. Please
                keep the original packaging.
              </p>
              <p class="policy-date">Eligible until {{ getReturnDate() }}</p>
            </div>
          </div>

          <!-- Shipment Status */
          <div class="shipment-card">
            <div class="card-header">
              <h2>Shipment Status</h2>
              <span class="shipment-badge" :class="`shipment-${order.status}`">
                {{ getShipmentLabel(order.status) }}
              </span>
            </div>

            <!-- Status Timeline -->
          <div class="status-timeline">
            <div
              v-for="(status, index) in statusSteps"
              :key="status.key"
              class="timeline-step"
              :class="{
                active: isStepActive(status.key),
                completed: isStepCompleted(status.key),
                current: isCurrentStep(status.key),
              }"
            >
              <div class="step-indicator">
                <div class="step-circle">
                  <svg
                    v-if="isStepCompleted(status.key)"
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"
                    />
                  </svg>
                </div>
                <div
                  v-if="index < statusSteps.length - 1"
                  class="step-line"
                ></div>
              </div>
              <div class="step-content">
                <h3>{{ status.label }}</h3>
                <p class="step-date" v-if="getStepDate(status.key)">
                  {{ getStepDate(status.key) }}
                </p>
                <p class="step-date pending" v-else>
                  {{ status.pending }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Tracking Updates -->
        <div class="tracking-card">
          <h2>
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"
              />
            </svg>
            Package in transit to distribution center
          </h2>
          <p class="tracking-description">
            Your package has left our warehouse and is on its way to the local
            distribution center.
          </p>
          <div class="tracking-info">
            <div class="tracking-item">
              <span class="tracking-label">Carrier:</span>
              <span class="tracking-value">{{
                order.carrier || "Standard Delivery"
              }}</span>
            </div>
            <div class="tracking-item">
              <span class="tracking-label">Tracking:</span>
              <span class="tracking-value">{{
                order.tracking_number || "FDX" + order.id + "89934590"
              }}</span>
            </div>
          </div>

          <button
            @click="showAllUpdates = !showAllUpdates"
            class="btn-view-updates"
          >
            {{ showAllUpdates ? "Hide" : "View All" }} Updates
          </button>
        </div>

        <!-- Payment Info -->
        <div class="payment-card">
          <h2>Payment Information</h2>
          <div class="payment-method">
            <div class="payment-icon">
              <svg
                v-if="order.payment_method === 'online'"
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"
                />
              </svg>
              <svg
                v-else
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"
                />
              </svg>
            </div>
            <div class="payment-details">
              <h3>
                {{
                  order.payment_method === "online"
                    ? "Card Payment"
                    : "Cash on Delivery"
                }}
              </h3>
              <p v-if="order.payment_method === 'online'">
                •••• •••• •••• 4562
              </p>
              <p v-else>Pay when you receive</p>
            </div>
            <div class="payment-amount">
              ₱{{ formatPrice(order.total_amount) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column - Live Tracking -->
      <div class="right-column">
        <div class="tracking-map-card">
          <div class="map-header">
            <h3>Live Tracking</h3>
            <button @click="toggleFullscreen" class="btn-fullscreen">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"
                />
              </svg>
            </button>
          </div>
          <p class="tracking-subtitle">
            Estimated arrival: {{ getEstimatedArrival() }}
          </p>

          <div class="map-status">
            <div class="status-item">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"
                />
              </svg>
              <span>Current Location</span>
            </div>
            <div class="status-item destination">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"
                />
              </svg>
              <span>Destination</span>
            </div>
            <div class="status-item route">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"
                />
              </svg>
              <span>Route</span>
            </div>
          </div>

          <div class="map-placeholder">
            <svg viewBox="0 0 400 300" class="route-map">
              <defs>
                <linearGradient
                  id="routeGradient"
                  x1="0%"
                  y1="0%"
                  x2="100%"
                  y2="100%"
                >
                  <stop
                    offset="0%"
                    style="stop-color: #48bb78; stop-opacity: 1"
                  />
                  <stop
                    offset="100%"
                    style="stop-color: #f59e0b; stop-opacity: 1"
                  />
                </linearGradient>
              </defs>

              <path
                d="M 50 250 Q 100 200, 150 180 T 250 120 T 350 50"
                fill="none"
                stroke="url(#routeGradient)"
                stroke-width="4"
                stroke-linecap="round"
              />

              <circle cx="50" cy="250" r="12" fill="#48bb78" />
              <text
                x="50"
                y="275"
                text-anchor="middle"
                fill="#2d3748"
                font-size="12"
                font-weight="600"
              >
                Start
              </text>

              <g class="current-marker">
                <circle cx="250" cy="120" r="20" fill="#fbbf24" opacity="0.3">
                  <animate
                    attributeName="r"
                    values="20;25;20"
                    dur="2s"
                    repeatCount="indefinite"
                  />
                </circle>
                <circle cx="250" cy="120" r="10" fill="#fbbf24" />
              </g>

              <circle cx="350" cy="50" r="12" fill="#dc2626" />
              <text
                x="350"
                y="35"
                text-anchor="middle"
                fill="#2d3748"
                font-size="12"
                font-weight="600"
              >
                Destination
              </text>
            </svg>
          </div>

          <!-- Delivery Updates -->
          <div class="delivery-updates">
            <h4>Delivery Updates</h4>
            <button @click="toggleUpdates" class="btn-close-updates">
              Close
            </button>
          </div>

          <div class="updates-timeline">
            <div class="update-notification">
              <div class="notification-icon">✓</div>
              <div class="notification-content">
                <h4>Package has left the warehouse</h4>
                <p>Aug 4, 9:20 AM • Chicago, IL</p>
              </div>
            </div>
            <div class="update-notification">
              <div class="notification-icon">✓</div>
              <div class="notification-content">
                <h4>In transit to distribution center</h4>
                <p>Aug 4, 2:30 PM • Cleveland, OH</p>
              </div>
            </div>
          </div>

          <button @click="viewAllUpdates" class="btn-view-all">
            View All Updates
          </button>
        </div>

        <!-- Customer Information -->
        <div class="customer-card">
          <h3>Customer Information</h3>
          <div class="customer-info">
            <div class="info-row">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"
                />
              </svg>
              <div>
                <span class="label">Name</span>
                <span class="value">{{ order.customer?.name || "N/A" }}</span>
              </div>
            </div>
            <div class="info-row">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"
                />
              </svg>
              <div>
                <span class="label">Address</span>
                <span class="value">{{
                  order.customer?.address || "N/A"
                }}</span>
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
import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";
import api from "../../plugins/axios.js";
import { toast } from "vue3-toastify";

const router = useRouter();
const route = useRoute();

const order = ref(null);
const isLoading = ref(true);
const loadingMessage = ref("Loading order details...");
const showAllUpdates = ref(false);

const statusSteps = ref([
  { key: "pending", label: "Ordered", pending: "(Expected)" },
  { key: "processing", label: "Processing", pending: "(Expected)" },
  { key: "shipped", label: "Shipped", pending: "(Expected)" },
  { key: "delivered", label: "Out for Delivery", pending: "(Expected)" },
  { key: "completed", label: "Delivered", pending: "(Expected)" },
]);

function getCurrentStepIndex() {
  const currentStatus = order.value?.status || "pending";
  const statusMap = {
    pending: 0,
    processing: 1,
    shipped: 2,
    delivered: 3,
    completed: 4,
  };
  return statusMap[currentStatus] || 0;
}

function isStepActive(stepKey) {
  return (
    getCurrentStepIndex() >=
    statusSteps.value.findIndex((s) => s.key === stepKey)
  );
}

function isStepCompleted(stepKey) {
  return (
    getCurrentStepIndex() >
    statusSteps.value.findIndex((s) => s.key === stepKey)
  );
}

function isCurrentStep(stepKey) {
  return (
    getCurrentStepIndex() ===
    statusSteps.value.findIndex((s) => s.key === stepKey)
  );
}

function getStepDate(stepKey) {
  if (!order.value) return null;
  const currentIndex = getCurrentStepIndex();
  const stepIndex = statusSteps.value.findIndex((s) => s.key === stepKey);

  if (stepIndex <= currentIndex) {
    if (stepKey === "pending") {
      return formatDateTime(order.value.created_at);
    }
  }
  return null;
}

function getShipmentLabel(status) {
  const labels = {
    pending: "Pending",
    processing: "Processing",
    shipped: "Shipped",
    delivered: "Delivered",
    completed: "Delivered",
  };
  return labels[status] || "Pending";
}

async function loadOrderDetails() {
  try {
    const orderId = route.params.id;
    const response = await api.get(`/vendor/orders/${orderId}`);

    if (response.data.success) {
      order.value = response.data.data;
    } else {
      toast.error("Failed to load order");
      router.push("/vendor/orders");
    }
  } catch (error) {
    console.error("Error:", error);
    toast.error("Failed to load order");
    router.push("/vendor/orders");
  } finally {
    isLoading.value = false;
  }
}

function goBack() {
  router.push("/vendor/orders");
}

function printOrder() {
  window.print();
}

function contactSupport() {
  toast.info("Contact support");
}

function toggleFullscreen() {
  toast.info("Fullscreen");
}

function toggleUpdates() {
  toast.info("Updates toggled");
}

function viewAllUpdates() {
  toast.info("View all updates");
}

function getReturnDate() {
  if (!order.value?.reservation_date) return "N/A";
  const date = new Date(order.value.reservation_date);
  date.setDate(date.getDate() + 30);
  return formatDate(date);
}

function getEstimatedArrival() {
  return formatDate(order.value?.reservation_date);
}

function formatDate(dateString) {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatDateTime(dateString) {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function formatStatus(status) {
  const map = {
    pending: "Pending",
    processing: "Processing",
    shipped: "Shipped",
    delivered: "Delivered",
    completed: "Completed",
  };
  return map[status] || status;
}

function formatPrice(price) {
  return parseFloat(price || 0).toFixed(2);
}

function handleImageError(event) {
  event.target.src = "https://via.placeholder.com/80";
}

onMounted(() => {
  loadOrderDetails();
});
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");

* {
  font-family: "Poppins", sans-serif;
  box-sizing: border-box;
}

.order-details-page {
  margin-left: 260px;
  min-height: 100vh;
  background: #f5f7fa;
}

.order-details-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 32px;
  flex-wrap: wrap;
  gap: 20px;
}

.header-left {
  display: flex;
  gap: 20px;
  align-items: center;
}

.btn-back {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-back:hover {
  background: #f7fafc;
  border-color: #48bb78;
}

.header-title h1 {
  font-size: 28px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 4px 0;
}

.header-title p {
  font-size: 14px;
  color: #718096;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.btn-print,
.btn-support {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-support {
  background: #48bb78;
  color: white;
  border-color: #48bb78;
}

.btn-print:hover {
  background: #f7fafc;
}

.btn-support:hover {
  background: #38a169;
}

.content-grid {
  display: grid;
  grid-template-columns: 1fr 450px;
  gap: 24px;
}

.left-column,
.right-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.info-card,
.items-card,
.shipment-card,
.tracking-card,
.payment-card,
.tracking-map-card,
.customer-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

h2,
h3,
h4 {
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 16px 0;
}

.tracking-map-card h3,
.customer-card h3 {
  font-size: 16px;
}

.order-status-badge {
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.status-processing {
  background: #dbeafe;
  color: #1e40af;
}

.status-shipped,
.status-delivered {
  background: #e0e7ff;
  color: #4338ca;
}

.status-completed {
  background: #d1fae5;
  color: #065f46;
}

.order-meta {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #718096;
  font-size: 14px;
}

.meta-item svg {
  color: #48bb78;
}

.items-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

.order-item {
  display: flex;
  gap: 16px;
  padding: 16px;
  background: #f9fafb;
  border-radius: 8px;
}

.item-image {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  overflow: hidden;
}

.item-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.item-details {
  flex: 1;
}

.item-details h3 {
  font-size: 15px;
  margin: 0 0 4px 0;
}

.item-meta {
  font-size: 13px;
  color: #718096;
  margin: 0 0 4px 0;
}

.item-price {
  font-size: 13px;
  color: #48bb78;
  font-weight: 500;
  margin: 0;
}

.item-total {
  font-size: 16px;
  font-weight: 700;
  color: #2d3748;
}

.return-policy {
  padding: 16px;
  background: #f0fdf4;
  border-left: 3px solid #48bb78;
  border-radius: 6px;
}

.return-policy h4 {
  font-size: 14px;
  margin: 0 0 8px 0;
}

.return-policy p {
  font-size: 13px;
  color: #4b5563;
  margin: 0 0 4px 0;
}

.policy-date {
  color: #48bb78;
  font-weight: 500;
}

.shipment-badge {
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
}

.shipment-pending {
  background: #fef3c7;
  color: #92400e;
}

.shipment-processing {
  background: #dbeafe;
  color: #1e40af;
}

.shipment-shipped,
.shipment-delivered {
  background: #e0e7ff;
  color: #4338ca;
}

.shipment-completed {
  background: #d1fae5;
  color: #065f46;
}

.status-timeline {
  display: flex;
  flex-direction: column;
  gap: 24px;
  margin-top: 24px;
}

.timeline-step {
  display: grid;
  grid-template-columns: 40px 1fr;
  gap: 16px;
}

.step-indicator {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.step-circle {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #f7fafc;
  border: 2px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2;
  transition: all 0.3s;
}

.timeline-step.active .step-circle {
  border-color: #48bb78;
}

.timeline-step.completed .step-circle {
  background: #48bb78;
  border-color: #48bb78;
}

.timeline-step.completed .step-circle svg {
  color: white;
}

.timeline-step.current .step-circle {
  background: #48bb78;
  box-shadow: 0 0 0 4px rgba(72, 187, 120, 0.2);
}

.step-line {
  width: 2px;
  flex: 1;
  background: #e2e8f0;
  margin-top: 4px;
}

.timeline-step.active .step-line {
  background: #48bb78;
}

.step-content h3 {
  font-size: 15px;
  margin: 0 0 4px 0;
}

.step-date {
  font-size: 13px;
  color: #48bb78;
  margin: 0;
  font-weight: 500;
}

.step-date.pending {
  color: #a0aec0;
}

.tracking-card h2 {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tracking-description {
  font-size: 14px;
  color: #718096;
  margin: 0 0 16px 0;
}

.tracking-info {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}

.tracking-item {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
}

.tracking-label {
  color: #718096;
  font-weight: 500;
}

.tracking-value {
  color: #2d3748;
  font-weight: 600;
}

.btn-view-updates {
  width: 100%;
  padding: 10px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.btn-view-updates:hover {
  background: #edf2f7;
}

.payment-method {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: #f9fafb;
  border-radius: 8px;
}

.payment-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  background: #48bb78;
  display: flex;
  align-items: center;
  justify-content: center;
}

.payment-icon svg {
  color: white;
}

.payment-details {
  flex: 1;
}

.payment-details h3 {
  font-size: 15px;
  margin: 0 0 4px 0;
}

.payment-details p {
  font-size: 13px;
  color: #718096;
  margin: 0;
}

.payment-amount {
  font-size: 20px;
  font-weight: 700;
  color: #2d3748;
}

.map-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.btn-fullscreen {
  width: 32px;
  height: 32px;
  border: 1px solid #e2e8f0;
  background: white;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.tracking-subtitle {
  font-size: 13px;
  color: #718096;
  margin: 0 0 16px 0;
}

.map-status {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.status-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #48bb78;
  font-weight: 500;
}

.status-item.destination {
  color: #dc2626;
}

.status-item.route {
  color: #718096;
}

.map-placeholder {
  width: 100%;
  height: 300px;
  background: #f9fafb;
  border-radius: 8px;
  margin-bottom: 16px;
}

.route-map {
  width: 100%;
  height: 100%;
}

.delivery-updates {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
}

.delivery-updates h4 {
  font-size: 14px;
  font-weight: 600;
  margin: 0;
}

.btn-close-updates {
  font-size: 12px;
  color: #718096;
  background: none;
  border: none;
  cursor: pointer;
}

.updates-timeline {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 16px;
}

.update-notification {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #2d3748;
  border-radius: 8px;
  color: white;
}

.notification-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
}

.notification-content h4 {
  font-size: 14px;
  color: white;
  margin: 0 0 4px 0;
}

.notification-content p {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
  margin: 0;
}

.btn-view-all {
  width: 100%;
  padding: 10px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  cursor: pointer;
}

.customer-info {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.info-row {
  display: flex;
  gap: 12px;
}

.info-row svg {
  color: #48bb78;
}

.info-row > div {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-row .label {
  font-size: 11px;
  color: #718096;
  text-transform: uppercase;
}

.info-row .value {
  font-size: 14px;
  color: #2d3748;
  font-weight: 500;
}

@media (max-width: 1200px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .order-details-page {
    margin-left: 0;
  }
}
</style>
