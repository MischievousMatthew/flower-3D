<!-- frontend/src/views/authenticated/Checkout.vue -->
<template>
  <div class="checkout-page">
    <NavHeader :cartCount="0" />

    <div class="checkout-container">
      <div class="checkout-header">
        <h1>Checkout</h1>
        <div class="step-indicator">
          <div
            class="step"
            :class="{ active: currentStep >= 1, completed: currentStep > 1 }"
          >
            <div class="step-number">1</div>
            <div class="step-label">Delivery Date</div>
          </div>
          <div class="step-line" :class="{ active: currentStep > 1 }"></div>
          <div
            class="step"
            :class="{ active: currentStep >= 2, completed: currentStep > 2 }"
          >
            <div class="step-number">2</div>
            <div class="step-label">Review Order</div>
          </div>
          <div class="step-line" :class="{ active: currentStep > 2 }"></div>
          <div class="step" :class="{ active: currentStep >= 3 }">
            <div class="step-number">3</div>
            <div class="step-label">Payment</div>
          </div>
        </div>
      </div>

      <LoadingOverlay :visible="isLoading" :message="loadingMessage" />

      <div class="checkout-content">
        <!-- Left Side: Steps Content -->
        <div class="checkout-steps">
          <!-- Step 1: Choose Delivery Date -->
          <div v-if="currentStep === 1" class="step-content">
            <div class="section-card">
              <h2>Choose Your Delivery Date</h2>
              <p class="section-description">
                Select a date for your order delivery. Available dates are shown
                in the calendar.
              </p>

              <!-- Calendar Legend -->
                <div class="calendar-legend">
                  <div class="legend-item">
                    <span class="legend-dot dot-today"></span>
                    <span class="legend-text">{{ todayLegendText }}</span>
                  </div>
                <div class="legend-item">
                  <span class="legend-dot dot-prep"></span>
                  <span class="legend-text">Preparation Time</span>
                </div>
                <div class="legend-item">
                  <span class="legend-dot dot-available"></span>
                  <span class="legend-text">Available</span>
                </div>
                <div class="legend-item">
                  <span class="legend-dot dot-almost"></span>
                  <span class="legend-text">Almost Full</span>
                </div>
                <div class="legend-item">
                  <span class="legend-dot dot-full"></span>
                  <span class="legend-text">Fully Booked</span>
                </div>
                <div class="legend-item">
                  <span class="legend-dot dot-disabled"></span>
                  <span class="legend-text">Not Available</span>
                </div>
              </div>

              <!-- Inline Calendar -->
              <div class="inline-calendar">
                <div v-if="vendorReservationNotice" class="vendor-calendar-note">
                  {{ vendorReservationNotice }}
                </div>

                <div class="calendar-header">
                  <button
                    @click="previousMonth"
                    class="btn-nav"
                    :disabled="!canGoPrevious"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="20"
                      height="20"
                      viewBox="0 0 24 24"
                    >
                      <path
                        fill="currentColor"
                        d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"
                      />
                    </svg>
                  </button>
                  <div class="calendar-title">
                    <h3>{{ currentMonthName }} {{ currentYear }}</h3>
                  </div>
                  <button
                    @click="nextMonth"
                    class="btn-nav"
                    :disabled="!canGoNext"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="20"
                      height="20"
                      viewBox="0 0 24 24"
                    >
                      <path
                        fill="currentColor"
                        d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"
                      />
                    </svg>
                  </button>
                </div>

                <div class="calendar-grid">
                  <div v-for="day in dayHeaders" :key="day" class="day-header">
                    {{ day }}
                  </div>

                  <div
                    v-for="day in calendarDays"
                    :key="day.dateString"
                    class="calendar-day"
                    :class="{
                      'other-month': !day.isCurrentMonth,
                      'is-selected': selectedDate === day.dateString,
                      'is-disabled': day.isDisabled,
                      [day.colorClass]: true,
                    }"
                    @click="selectDate(day)"
                  >
                    <div class="day-number">{{ day.day }}</div>
                    <div v-if="day.label" class="day-label">
                      {{ day.label }}
                    </div>
                  </div>
                </div>

                <div v-if="selectedDate" class="selected-date-display">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"
                    />
                  </svg>
                  <span
                    >Selected Date:
                    <strong>{{ formatSelectedDate }}</strong></span
                  >
                </div>
              </div>

              <div class="step-actions">
                <button
                  class="btn-secondary"
                  @click="$router.push('/customer/cart')"
                >
                  Back to Cart
                </button>
                <button
                  class="btn-primary"
                  @click="goToStep(2)"
                  :disabled="!selectedDate"
                >
                  Continue to Review
                </button>
              </div>
            </div>
          </div>

          <!-- Step 2: Review Order -->
          <div v-if="currentStep === 2" class="step-content">
            <div class="section-card">
              <h2>Review Your Order</h2>

              <!-- User / Delivery Information -->
              <div class="info-section">
                <h3>Delivery Information</h3>
                <div class="info-grid">
                  <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value">{{
                      checkoutData.user?.name || "N/A"
                    }}</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value">{{
                      checkoutData.user?.email || "N/A"
                    }}</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value">{{
                      checkoutData.user?.contact_number || "N/A"
                    }}</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value">{{
                      checkoutData.user?.address || "N/A"
                    }}</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Delivery Date:</span>
                    <span class="info-value">{{ formatSelectedDate }}</span>
                  </div>
                </div>
              </div>

              <!-- Store Information -->
              <div class="info-section">
                <h3>Store Information</h3>
                <div class="info-grid">
                  <div class="info-row">
                    <span class="info-label">Store Name:</span>
                    <span class="info-value">{{
                      checkoutData.vendor?.store_name ||
                      checkoutData.vendor?.display_name ||
                      "N/A"
                    }}</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value">{{
                      checkoutData.vendor?.email || "N/A"
                    }}</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Contact:</span>
                    <span class="info-value">{{
                      checkoutData.vendor?.contact_number || "N/A"
                    }}</span>
                  </div>
                </div>
              </div>

              <!-- Order Items -->
              <div class="info-section">
                <h3>Order Items</h3>
                <div class="order-items-list">
                  <div
                    v-for="item in checkoutData.items"
                    :key="item.id"
                    class="order-item"
                  >
                    <div class="item-image-small">
                      <img
                        :src="
                          item.product_image || 'https://via.placeholder.com/80'
                        "
                        :alt="item.product_name"
                        @error="handleImageError"
                      />
                    </div>

                    <div class="item-info">
                      <h4>{{ item.product_name || "Unknown Product" }}</h4>
                      <p class="item-meta">Quantity: {{ item.quantity }}</p>

                      <!-- Price display with discount awareness -->
                      <div v-if="hasDiscount(item)" class="item-price-group">
                        <span class="price-original"
                          >₱{{ formatPrice(originalPrice(item)) }}</span
                        >
                        <span class="price-discounted"
                          >₱{{ formatPrice(effectivePrice(item)) }}</span
                        >
                        <span class="price-badge"
                          >{{ discountPercent(item) }}% OFF</span
                        >
                      </div>
                      <p v-else class="item-price">
                        ₱{{ formatPrice(effectivePrice(item)) }} ×
                        {{ item.quantity }}
                      </p>
                    </div>

                    <div class="item-total">
                      ₱{{ formatPrice(lineTotal(item)) }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Customer Notes -->
              <div class="info-section">
                <h3>Special Instructions (Optional)</h3>
                <textarea
                  v-model="customerNotes"
                  class="notes-textarea"
                  placeholder="Add any special instructions for your order..."
                  rows="4"
                ></textarea>
              </div>

              <div class="step-actions">
                <button class="btn-secondary" @click="goToStep(1)">
                  Back to Calendar
                </button>
                <button class="btn-primary" @click="goToStep(3)">
                  Continue to Payment
                </button>
              </div>
            </div>
          </div>

          <!-- Step 3: Payment -->
          <div v-if="currentStep === 3" class="step-content">
            <div class="section-card">
              <h2>Payment Method</h2>
              <p class="section-description">
                Choose your preferred payment method
              </p>

              <!-- Available Payment Methods from Vendor -->
              <div class="payment-methods-grid">
                <!-- Online Payment Methods (based on vendor's payout method) -->
                <label
                  v-for="method in availablePaymentMethods"
                  :key="method.type"
                  class="payment-method-card"
                  :class="{ selected: selectedPaymentMethod === method.type }"
                >
                  <input
                    type="radio"
                    name="payment"
                    :value="method.type"
                    v-model="selectedPaymentMethod"
                  />
                  <div class="payment-icon">{{ method.icon }}</div>
                  <div class="payment-info">
                    <h4>{{ method.name }}</h4>
                    <p>{{ method.description }}</p>
                  </div>
                </label>
              </div>

              <!-- Online Payment Card Details (only for gcash/maya/card) -->
              <div
                v-if="['gcash', 'maya', 'card'].includes(selectedPaymentMethod)"
                class="card-details-section"
              >
                <h3>Payment Details</h3>
                <div class="payment-info-message">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="#48bb78"
                      d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
                    />
                  </svg>
                  <span>
                    You will be redirected to a secure payment page to complete
                    your transaction.
                  </span>
                </div>
              </div>

              <!-- COD Instructions -->
              <div
                v-if="selectedPaymentMethod === 'cod'"
                class="cod-instructions"
              >
                <h3>Cash on Delivery Instructions</h3>
                <div class="instructions-list">
                  <div class="instruction-item">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                    >
                      <path
                        fill="#48bb78"
                        d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"
                      />
                    </svg>
                    <span>Pay when you receive your order</span>
                  </div>
                  <div class="instruction-item">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                    >
                      <path
                        fill="#48bb78"
                        d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"
                      />
                    </svg>
                    <span>Have exact amount ready for delivery</span>
                  </div>
                  <div class="instruction-item">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                    >
                      <path
                        fill="#48bb78"
                        d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"
                      />
                    </svg>
                    <span>Ensure contact number is correct</span>
                  </div>
                </div>
              </div>

              <!-- Bank Transfer Instructions -->
              <div
                v-if="selectedPaymentMethod === 'bank_transfer'"
                class="bank-instructions"
              >
                <h3>Bank Transfer Instructions</h3>
                <div class="instructions-list">
                  <div class="instruction-item">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                    >
                      <path
                        fill="#48bb78"
                        d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"
                      />
                    </svg>
                    <span>Complete payment within 24 hours</span>
                  </div>
                  <div class="instruction-item">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                    >
                      <path
                        fill="#48bb78"
                        d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"
                      />
                    </svg>
                    <span>Send proof of payment to vendor</span>
                  </div>
                  <div class="instruction-item">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                    >
                      <path
                        fill="#48bb78"
                        d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"
                      />
                    </svg>
                    <span
                      >Order will be processed after payment confirmation</span
                    >
                  </div>
                </div>
              </div>

              <div class="step-actions">
                <button class="btn-secondary" @click="goToStep(2)">
                  Back to Review
                </button>
                <button
                  class="btn-primary btn-place-order"
                  @click="placeOrder"
                  :disabled="!selectedPaymentMethod || isProcessing"
                >
                  <span v-if="isProcessing">Processing...</span>
                  <span v-else>Place Order</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Side: Order Summary -->
        <!-- Right Side: Order Summary -->
        <div class="order-summary-sidebar">
          <div class="summary-card">
            <!-- Header -->
            <div class="summary-header">
              <h3>Order Summary</h3>
              <span class="item-count-badge">
                {{ checkoutData.items?.length || 0 }} item{{
                  checkoutData.items?.length !== 1 ? "s" : ""
                }}
              </span>
            </div>

            <!-- Item List -->
            <div class="summary-items-list">
              <div
                v-for="item in checkoutData.items"
                :key="item.id"
                class="summary-item"
              >
                <div class="summary-item-img">
                  <img
                    :src="
                      item.product_image || 'https://via.placeholder.com/48'
                    "
                    :alt="item.product_name"
                    @error="handleImageError"
                  />
                  <span class="summary-item-qty">{{ item.quantity }}</span>
                </div>
                <div class="summary-item-info">
                  <p class="summary-item-name">{{ item.product_name }}</p>
                  <div class="summary-item-pricing">
                    <template v-if="item.discount_price">
                      <span class="summary-item-original"
                        >₱{{ formatPrice(item.original_price) }}</span
                      >
                      <span class="summary-item-price"
                        >₱{{ formatPrice(item.discount_price) }}</span
                      >
                    </template>
                    <span v-else class="summary-item-price"
                      >₱{{ formatPrice(item.unit_price) }}</span
                    >
                  </div>
                </div>
                <div class="summary-item-total">
                  ₱{{ formatPrice(effectivePrice(item) * item.quantity) }}
                </div>
              </div>
            </div>

            <!-- Divider -->
            <div class="summary-divider"></div>

            <!-- Price Breakdown -->
            <div class="summary-breakdown">
              <div class="breakdown-row">
                <span>Original price</span>
                <span>₱{{ formatPrice(checkoutOriginalTotal) }}</span>
              </div>

              <div
                class="breakdown-row savings-row"
                v-if="checkoutTotalSavings > 0"
              >
                <span>
                  <span class="savings-label">Discount savings</span>
                </span>
                <span class="savings-amount"
                  >−₱{{ formatPrice(checkoutTotalSavings) }}</span
                >
              </div>
            </div>

            <!-- Savings Banner -->
            <div v-if="checkoutTotalSavings > 0" class="savings-banner">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"
                />
              </svg>
              You save ₱{{ formatPrice(checkoutTotalSavings) }} on this order!
            </div>

            <div class="summary-divider"></div>

            <!-- Total -->
            <div class="summary-total-row">
              <div class="total-label">
                <span>Total</span>
                <span class="total-note">Final order total</span>
              </div>
              <span class="total-amount">₱{{ formatPrice(totalAmount) }}</span>
            </div>

            <!-- Notes -->
            <div class="summary-notes">
              <div class="summary-note">
                <div class="note-icon note-icon-info">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"
                    />
                  </svg>
                </div>
                <span>Your order will be thoughtfully prepared.</span>
              </div>
              <div class="summary-note">
                <div class="note-icon note-icon-truck">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"
                    />
                  </svg>
                </div>
                <span>No delivery fee is included in this checkout.</span>
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
import NavHeader from "../../layouts/NavHeader.vue";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";
import api from "../../plugins/axios.js";
import { toast } from "vue3-toastify";
import { usePrice } from "../../composables/usePrice.js";

const PH_TIMEZONE = "Asia/Manila";

const {
  effectivePrice,
  originalPrice,
  hasDiscount,
  discountPercent,
  lineTotal,
  formatPrice,
} = usePrice();
const router = useRouter();
const route = useRoute();

// State
const currentStep = ref(1);
const isLoading = ref(false);
const loadingMessage = ref("");
const isProcessing = ref(false);
const isDirectCheckout = ref(false);
const directCheckoutData = ref(null);

// Reservation state
const selectedDate = ref(null);
const calendarDate = ref(new Date());
const calendarData = ref({});
const leadTimeDays = ref(3);
const vendorReservationSettings = ref({
  timezone: PH_TIMEZONE,
  sameDayDelivery: false,
  sameDayAvailableToday: false,
  cutoffTimeToday: null,
  maxOrdersPerDay: 10,
});

// Checkout data
const checkoutData = ref({
  user: null,
  vendor: null,
  items: [],
  summary: { subtotal: 0, total_amount: 0 },
  payment_methods: {
    available_methods: [],
    default_method: null,
  },
});

const checkoutOriginalTotal = computed(() => {
  return (checkoutData.value.items || []).reduce((sum, item) => {
    const orig = parseFloat(item.original_price || item.unit_price || 0);
    return sum + orig * item.quantity;
  }, 0);
});

const checkoutTotalSavings = computed(() => {
  return (checkoutData.value.items || []).reduce((sum, item) => {
    if (!item.discount_price) return sum;
    const orig = parseFloat(item.original_price || item.unit_price || 0);
    const disc = parseFloat(item.discount_price);
    return sum + (orig - disc) * item.quantity;
  }, 0);
});

const customerNotes = ref("");
const selectedPaymentMethod = ref(null);
const availablePaymentMethods = ref([]);

// Day headers
const dayHeaders = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

// Computed
const totalAmount = computed(() => {
  return parseFloat(checkoutData.value.summary?.total_amount || 0);
});

const currentMonth = computed(() => calendarDate.value.getMonth());
const currentYear = computed(() => calendarDate.value.getFullYear());
const currentMonthName = computed(() =>
  calendarDate.value.toLocaleString("default", { month: "long" }),
);

const todayLegendText = computed(() =>
  vendorReservationSettings.value.sameDayAvailableToday
    ? "Today (Available)"
    : "Today",
);

const vendorReservationNotice = computed(() => {
  const parts = [];

  parts.push(
    `Vendor capacity: ${vendorReservationSettings.value.maxOrdersPerDay} orders per day.`,
  );
  parts.push(`Lead time: ${leadTimeDays.value} day(s).`);

  if (vendorReservationSettings.value.sameDayDelivery) {
    if (vendorReservationSettings.value.cutoffTimeToday) {
      parts.push(
        `Same-day delivery is available until ${formatCutoffTime(vendorReservationSettings.value.cutoffTimeToday)} PH time.`,
      );
    } else {
      parts.push("Same-day delivery is available.");
    }
  } else {
    parts.push("Same-day delivery is not available.");
  }

  return parts.join(" ");
});

const formatSelectedDate = computed(() => {
  if (!selectedDate.value) return "";
  const date = new Date(selectedDate.value);
  return date.toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
});

const canGoPrevious = computed(() => {
  const today = getPhilippinesToday();
  const firstOfCurrentMonth = new Date(
    today.getFullYear(),
    today.getMonth(),
    1,
  );
  const firstOfCalendarMonth = new Date(
    currentYear.value,
    currentMonth.value,
    1,
  );
  return firstOfCalendarMonth > firstOfCurrentMonth;
});

const canGoNext = computed(() => {
  const today = getPhilippinesToday();
  const maxDate = new Date(today);
  maxDate.setMonth(maxDate.getMonth() + 3);
  const lastOfCalendarMonth = new Date(
    currentYear.value,
    currentMonth.value + 1,
    0,
  );
  return lastOfCalendarMonth < maxDate;
});

// Calendar days computation
const calendarDays = computed(() => {
  const year = currentYear.value;
  const month = currentMonth.value;
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);

  const days = [];
  const today = getPhilippinesToday();

  const minDate = new Date(today);
  if (!vendorReservationSettings.value.sameDayAvailableToday) {
    minDate.setDate(minDate.getDate() + leadTimeDays.value);
  }

  // Calculate max date (3 months from today)
  const maxDate = new Date(today);
  maxDate.setMonth(maxDate.getMonth() + 3);

  // Add previous month days
  const firstDayOfWeek = firstDay.getDay();
  for (let i = firstDayOfWeek - 1; i >= 0; i--) {
    days.push(
      createDayObject(
        new Date(year, month, -i),
        false,
        today,
        minDate,
        maxDate,
      ),
    );
  }

  // Add current month days
  for (let day = 1; day <= lastDay.getDate(); day++) {
    days.push(
      createDayObject(
        new Date(year, month, day),
        true,
        today,
        minDate,
        maxDate,
      ),
    );
  }

  // Add next month days to fill calendar
  const remaining = 42 - days.length;
  for (let day = 1; day <= remaining; day++) {
    days.push(
      createDayObject(
        new Date(year, month + 1, day),
        false,
        today,
        minDate,
        maxDate,
      ),
    );
  }

  return days;
});

function createDayObject(date, isCurrentMonth, today, minDate, maxDate) {
  const dateString = formatDateString(date);
  const availability = calendarData.value[dateString] || null;

  const cleanDate = new Date(
    date.getFullYear(),
    date.getMonth(),
    date.getDate(),
  );
  const isPastDate = cleanDate < today;
  const isToday = cleanDate.getTime() === today.getTime();

  // Calculate tomorrow and day after tomorrow
  const tomorrow = new Date(today);
  tomorrow.setDate(tomorrow.getDate() + 1);
  const dayAfterTomorrow = new Date(today);
  dayAfterTomorrow.setDate(dayAfterTomorrow.getDate() + 2);

  const isTomorrow = cleanDate.getTime() === tomorrow.getTime();
  const isDayAfterTomorrow = cleanDate.getTime() === dayAfterTomorrow.getTime();
  const isWithinLeadTime = Boolean(availability?.is_within_lead_time) || cleanDate < minDate;
  const isBeyondLimit = cleanDate > maxDate;
  const isSameDayCutoffBlocked = Boolean(
    availability?.is_same_day_cutoff_blocked,
  );

  // Determine color class
  let colorClass = "day-white";
  let label = null;

  if (isPastDate || isBeyondLimit) {
    colorClass = "day-disabled";
  } else if (isToday && !vendorReservationSettings.value.sameDayAvailableToday) {
    colorClass = "day-today";
    label = "Today";
  } else if (isToday && vendorReservationSettings.value.sameDayAvailableToday) {
    colorClass = "day-available";
    label = "Today";
  } else if (isTomorrow || isDayAfterTomorrow) {
    colorClass = "day-prep-time";
    label = "Prep Time";
  } else if (isWithinLeadTime || isSameDayCutoffBlocked) {
    colorClass = "day-prep-time";
    label = isSameDayCutoffBlocked ? "Cutoff" : "Prep Time";
  } else if (availability) {
    if (availability.is_disabled || availability.status === "closed") {
      colorClass = "day-disabled";
    } else if (availability.status === "fully_booked") {
      colorClass = "day-fully-booked";
    } else if (availability.status === "almost_full") {
      colorClass = "day-almost-full";
    } else {
      colorClass = "day-available";
    }
  } else {
    colorClass = "day-available";
  }

  const isDisabled =
    isPastDate ||
    isBeyondLimit ||
    isWithinLeadTime ||
    isSameDayCutoffBlocked ||
    availability?.is_disabled ||
    availability?.status === "fully_booked" ||
    false;

  return {
    day: date.getDate(),
    dateString,
    isCurrentMonth,
    isToday,
    isPastDate,
    isWithinLeadTime,
    isSameDayCutoffBlocked,
    isDisabled,
    availability,
    colorClass,
    label,
  };
}

function formatDateString(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

function selectDate(day) {
  if (!day.isCurrentMonth) return;

  if (day.isDisabled) {
    if (day.isSameDayCutoffBlocked) {
      const cutoffTime = vendorReservationSettings.value.cutoffTimeToday
        ? formatCutoffTime(vendorReservationSettings.value.cutoffTimeToday)
        : null;
      toast.error(
        cutoffTime
          ? `Same-day delivery is only available until ${cutoffTime} PH time.`
          : "Same-day delivery cutoff has already been reached for today.",
      );
    } else if (day.isToday || day.isWithinLeadTime) {
      toast.error(
        `Flowers require ${leadTimeDays.value} day(s) preparation time. Please select a date at least ${leadTimeDays.value} day(s) from today.`,
      );
    } else if (day.isPastDate) {
      toast.error("Cannot select past dates.");
    } else {
      toast.error("This date is not available for reservation.");
    }
    return;
  }

  selectedDate.value = day.dateString;
}

async function loadCalendarData() {
  try {
    const startDate = formatDateString(
      new Date(currentYear.value, currentMonth.value, 1),
    );
    const endDate = formatDateString(
      new Date(currentYear.value, currentMonth.value + 1, 0),
    );

    const res = await api.get("/customer/availability", {
      params: {
        vendor_id: checkoutData.value.vendor?.id,
        start_date: startDate,
        end_date: endDate,
      },
    });

    if (res.data.success) {
      calendarData.value = res.data.data.calendar || {};
      const vendor = res.data.data.vendor || {};
      leadTimeDays.value = Number(vendor.lead_time_days ?? 3);
      vendorReservationSettings.value = {
        timezone: vendor.timezone || PH_TIMEZONE,
        sameDayDelivery: Boolean(vendor.same_day_delivery),
        sameDayAvailableToday: Boolean(vendor.same_day_available_today),
        cutoffTimeToday: vendor.cutoff_time_today || null,
        maxOrdersPerDay: Number(vendor.max_orders_per_day ?? 10),
      };
    }
  } catch (e) {
    console.error("Error loading calendar:", e);
  }
}

function previousMonth() {
  if (!canGoPrevious.value) return;
  const d = new Date(calendarDate.value);
  d.setMonth(d.getMonth() - 1);
  calendarDate.value = d;
  loadCalendarData();
}

function nextMonth() {
  if (!canGoNext.value) return;
  const d = new Date(calendarDate.value);
  d.setMonth(d.getMonth() + 1);
  calendarDate.value = d;
  loadCalendarData();
}

function goToStep(step) {
  if (step === 2 && !selectedDate.value) {
    toast.warning("Please select a delivery date first");
    return;
  }
  currentStep.value = step;
  window.scrollTo({ top: 0, behavior: "smooth" });
}

async function loadCheckoutData() {
  try {
    isLoading.value = true;
    loadingMessage.value = "Loading checkout details...";

    const savedData = localStorage.getItem("checkout_data");
    const directData = sessionStorage.getItem("directCheckout");
    const isDirect = route.query.direct === "true";

    if (isDirect && directData) {
      isDirectCheckout.value = true;
      directCheckoutData.value = JSON.parse(directData);

      const response = await api.post("/checkout/initialize", {
        product_id: directCheckoutData.value.items[0].product_id,
        quantity: directCheckoutData.value.items[0].quantity,
      });

      if (response.data.success) {
        checkoutData.value = response.data.data;
      } else {
        toast.error("Failed to load direct checkout data");
        router.push("/shop");
        return;
      }
    } else {
      if (!savedData) {
        toast.error("No checkout data found");
        router.push("/customer/cart");
        return;
      }

      const parsed = JSON.parse(savedData);
      const response = await api.post("/checkout/initialize", {
        cart_item_ids: parsed.cart_item_ids,
      });

      if (response.data.success) {
        checkoutData.value = response.data.data;
      } else {
        toast.error("Failed to load checkout data");
        router.push("/customer/cart");
        return;
      }
    }

    // Set available payment methods from backend
    if (checkoutData.value.payment_methods?.available_methods) {
      availablePaymentMethods.value =
        checkoutData.value.payment_methods.available_methods;
      // Set default payment method
      if (checkoutData.value.payment_methods.default_method) {
        selectedPaymentMethod.value =
          checkoutData.value.payment_methods.default_method;
      } else if (availablePaymentMethods.value.length > 0) {
        selectedPaymentMethod.value = availablePaymentMethods.value[0].type;
      }
    }
  } catch (error) {
    console.error("Error loading checkout:", error);
    toast.error("Failed to initialize checkout");
    router.push("/customer/cart");
  } finally {
    isLoading.value = false;
  }
}

async function placeOrder() {
  if (!selectedPaymentMethod.value) {
    toast.error("Please select a payment method");
    return;
  }

  try {
    isProcessing.value = true;
    loadingMessage.value = "Processing your order...";
    isLoading.value = true;

    const orderData = {
      reservation_date: selectedDate.value,
      payment_method: selectedPaymentMethod.value,
      delivery_address: checkoutData.value.user?.address || "",
      contact_number: checkoutData.value.user?.contact_number || "",
      customer_notes: customerNotes.value,

      ...(isDirectCheckout.value
        ? {
            product_id: directCheckoutData.value.items[0].product_id,
            quantity: directCheckoutData.value.items[0].quantity,
          }
        : {
            cart_item_ids:
              JSON.parse(localStorage.getItem("checkout_data"))
                ?.cart_item_ids || [],
          }),
    };

    console.log("Sending order data:", orderData);

    const response = await api.post("/checkout/create-order", orderData);

    console.log("Order response:", response.data);

    if (response.data.success) {
      toast.success("Order created successfully!");

      if (isDirectCheckout.value) {
        sessionStorage.removeItem("directCheckout");
      } else {
        localStorage.removeItem("checkout_data");
      }

      if (response.data.data?.payment?.requires_redirect) {
        window.location.href = response.data.data.payment.redirect_url;
      } else {
        router.push("/customer/orders");
      }
    } else {
      toast.error(response.data.message || "Failed to place order");
    }
  } catch (error) {
    console.log("Validation errors:", error.response?.data?.errors);
    console.error("Error placing order:", error);
    toast.error(error.response?.data?.message || "Failed to place order");
  } finally {
    isProcessing.value = false;
    isLoading.value = false;
  }
}

function handleImageError(event) {
  event.target.src = "https://via.placeholder.com/80";
}

function getPhilippinesNowParts() {
  const formatter = new Intl.DateTimeFormat("en-CA", {
    timeZone: PH_TIMEZONE,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });

  const parts = formatter.formatToParts(new Date());
  const get = (type) => parts.find((part) => part.type === type)?.value;

  return {
    year: Number(get("year")),
    month: Number(get("month")),
    day: Number(get("day")),
  };
}

function getPhilippinesToday() {
  const { year, month, day } = getPhilippinesNowParts();
  return new Date(year, month - 1, day);
}

function formatCutoffTime(value) {
  if (!value) return null;
  const [hours, minutes] = value.split(":").map(Number);
  const date = new Date(2000, 0, 1, hours, minutes);
  return date.toLocaleTimeString("en-PH", {
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
  });
}

onMounted(async () => {
  await loadCheckoutData();
  await loadCalendarData();
});
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");

* {
  font-family: "Poppins", sans-serif;
  box-sizing: border-box;
}

.checkout-page {
  min-height: 100vh;
  background: #f5f7fa;
  padding-top: 80px;
}

.checkout-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
}

.checkout-header {
  margin-bottom: 40px;
}

.checkout-header h1 {
  font-size: 32px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 24px 0;
}

.step-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0;
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.step-number {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #e2e8f0;
  color: #718096;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 16px;
  transition: all 0.3s;
}

.step.active .step-number {
  background: #48bb78;
  color: white;
}

.step.completed .step-number {
  background: #38a169;
  color: white;
}

.step-label {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
}

.step.active .step-label {
  color: #2d3748;
  font-weight: 600;
}

.step-line {
  width: 100px;
  height: 2px;
  background: #e2e8f0;
  transition: all 0.3s;
}

.step-line.active {
  background: #48bb78;
}

.checkout-content {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 32px;
  align-items: start;
}

.checkout-steps {
  background: white;
  border-radius: 12px;
  overflow: hidden;
}

.step-content {
  animation: fadeIn 0.3s ease;
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

.section-card {
  padding: 32px;
}

.section-card h2 {
  font-size: 24px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 8px 0;
}

.section-description {
  font-size: 14px;
  color: #718096;
  margin: 0 0 24px 0;
}

/* Calendar Legend */
.calendar-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  padding: 16px;
  background: #f7fafc;
  border-radius: 8px;
  margin-bottom: 24px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.legend-dot {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  border: 2px solid #cbd5e0;
}

.dot-today {
  background: #2d3748;
  border-color: #2d3748;
}

.dot-prep {
  background: white;
  border-color: #fbbf24;
  border-width: 2px;
}

.dot-available {
  background: white;
  border-color: #48bb78;
  border-width: 2px;
}

.dot-almost {
  background: white;
  border-color: #fb923c;
  border-width: 2px;
}

.dot-full {
  background: #dc2626;
  border-color: #dc2626;
}

.dot-disabled {
  background: #e5e7eb;
  border-color: #9ca3af;
}

.legend-text {
  font-size: 13px;
  color: #4b5563;
  font-weight: 500;
}

/* Inline Calendar */
.inline-calendar {
  max-width: 500px;
  margin: 0 auto;
}

.vendor-calendar-note {
  margin-bottom: 16px;
  padding: 12px 14px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  font-size: 13px;
  line-height: 1.5;
  color: #166534;
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.calendar-title h3 {
  font-size: 20px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.btn-nav {
  width: 40px;
  height: 40px;
  border: 1px solid #e2e8f0;
  background: white;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.btn-nav:hover:not(:disabled) {
  background: #f7fafc;
  border-color: #48bb78;
}

.btn-nav:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
}

.day-header {
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  color: #718096;
  padding: 12px 0;
}

.calendar-day {
  aspect-ratio: 1;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  padding: 8px;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60px;
  background: white;
}

.day-number {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
}

.day-label {
  font-size: 9px;
  color: #718096;
  margin-top: 4px;
  font-weight: 500;
}

.calendar-day.other-month {
  opacity: 0.3;
  cursor: default;
}

.calendar-day.day-today {
  background: #2d3748;
  border-color: #2d3748;
}

.calendar-day.day-today .day-number,
.calendar-day.day-today .day-label {
  color: white;
}

.calendar-day.day-prep-time {
  border-color: #fbbf24;
  border-width: 2px;
}

.calendar-day.day-available {
  border-color: #48bb78;
  border-width: 2px;
}

.calendar-day.day-almost-full {
  border-color: #fb923c;
  border-width: 2px;
}

.calendar-day.day-fully-booked {
  background: #fee2e2;
  border-color: #dc2626;
}

.calendar-day.day-disabled {
  background: #f9fafb;
  opacity: 0.5;
  cursor: not-allowed;
}

.calendar-day.is-selected {
  border-width: 3px;
  border-color: #48bb78;
  background: #f0fdf4;
  transform: scale(1.05);
}

.calendar-day:not(.is-disabled):not(.other-month):hover {
  transform: scale(1.08);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.selected-date-display {
  margin-top: 24px;
  padding: 16px;
  background: #f0fdf4;
  border: 2px solid #48bb78;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 12px;
  color: #2d3748;
  font-size: 14px;
}

.selected-date-display svg {
  color: #48bb78;
}

/* Info Sections */
.info-section {
  margin-bottom: 32px;
}

.info-section h3 {
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 16px 0;
  padding-bottom: 8px;
  border-bottom: 2px solid #e2e8f0;
}

.info-grid {
  display: grid;
  gap: 12px;
}

.info-row {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 16px;
  padding: 12px 0;
  border-bottom: 1px solid #f7fafc;
}

.info-label {
  font-size: 14px;
  font-weight: 600;
  color: #6b7280;
}

.info-value {
  font-size: 14px;
  color: #1f2937;
}

/* Order Items */
.order-items-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.order-item {
  display: grid;
  grid-template-columns: 80px 1fr auto;
  gap: 16px;
  padding: 16px;
  background: #f9fafb;
  border-radius: 8px;
  align-items: center;
}

.item-image-small {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  overflow: hidden;
  background: white;
}

.item-image-small img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.item-info h4 {
  font-size: 15px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 4px 0;
}

.item-meta {
  font-size: 13px;
  color: #718096;
  margin: 0 0 4px 0;
}

.item-price {
  font-size: 13px;
  color: #4b5563;
  margin: 0;
}

.item-total {
  font-size: 16px;
  font-weight: 700;
  color: #48bb78;
}

.item-price-group {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.price-original {
  font-size: 12px;
  color: #a0aec0;
  text-decoration: line-through;
}

.price-discounted {
  font-size: 14px;
  font-weight: 600;
  color: #48bb78;
}

.price-badge {
  padding: 2px 6px;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}

/* Notes */
.notes-textarea {
  width: 100%;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  resize: vertical;
  transition: all 0.3s;
}

.notes-textarea:focus {
  outline: none;
  border-color: #48bb78;
}

/* Payment Methods */
.payment-methods-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.payment-method-card {
  padding: 20px;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  background: white;
}

.payment-method-card input {
  display: none;
}

.payment-method-card.selected {
  border-color: #48bb78;
  background: #f0fdf4;
}

.payment-method-card:hover {
  border-color: #48bb78;
}

.payment-icon {
  font-size: 48px;
}

.payment-info h4 {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 4px 0;
  text-align: center;
}

.payment-info p {
  font-size: 13px;
  color: #718096;
  margin: 0;
  text-align: center;
}

/* Payment Instructions */
.payment-info-message {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: #f0fdf4;
  border-radius: 8px;
  border: 1px solid #48bb78;
  margin-top: 16px;
}

.payment-info-message svg {
  flex-shrink: 0;
}

.payment-info-message span {
  font-size: 14px;
  color: #2d3748;
  line-height: 1.5;
}

/* Instructions Sections */
.cod-instructions,
.bank-instructions {
  margin-top: 24px;
  padding: 20px;
  background: #f9fafb;
  border-radius: 8px;
  border-left: 4px solid #48bb78;
}

.cod-instructions h3,
.bank-instructions h3 {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 16px 0;
}

.instructions-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.instruction-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.instruction-item svg {
  flex-shrink: 0;
  margin-top: 2px;
}

.instruction-item span {
  font-size: 14px;
  color: #4b5563;
  line-height: 1.5;
}

/* Step Actions */
.step-actions {
  display: flex;
  gap: 12px;
  margin-top: 32px;
  padding-top: 24px;
  border-top: 1px solid #e2e8f0;
}

.btn-secondary {
  flex: 1;
  padding: 14px 24px;
  background: white;
  color: #2d3748;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-secondary:hover {
  background: #f7fafc;
  border-color: #48bb78;
}

.btn-primary {
  flex: 1;
  padding: 14px 24px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-primary:hover:not(:disabled) {
  background: #38a169;
  transform: translateY(-2px);
}

.btn-primary:disabled {
  background: #cbd5e0;
  cursor: not-allowed;
  transform: none;
}

.btn-place-order {
  background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}

/* ── Order Summary Sidebar ─────────────────────────────── */
.order-summary-sidebar {
  position: sticky;
  top: 100px;
}

.summary-card {
  background: white;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
  border: 1px solid #f0f0f0;
}

.summary-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.summary-header h3 {
  font-size: 18px;
  font-weight: 600;
  color: #1a202c;
  margin: 0;
}

.item-count-badge {
  background: #ebf8f0;
  color: #276749;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 20px;
}

/* Item list */
.summary-items-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-bottom: 4px;
}

.summary-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.summary-item-img {
  position: relative;
  flex-shrink: 0;
}

.summary-item-img img {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid #e2e8f0;
}

.summary-item-qty {
  position: absolute;
  top: -6px;
  right: -6px;
  background: #2d3748;
  color: white;
  font-size: 10px;
  font-weight: 700;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.summary-item-info {
  flex: 1;
  min-width: 0;
}

.summary-item-name {
  font-size: 13px;
  font-weight: 500;
  color: #2d3748;
  margin: 0 0 3px 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.summary-item-pricing {
  display: flex;
  align-items: center;
  gap: 6px;
}

.summary-item-original {
  font-size: 11px;
  color: #a0aec0;
  text-decoration: line-through;
}

.summary-item-price {
  font-size: 12px;
  font-weight: 600;
  color: #48bb78;
}

.summary-item-total {
  font-size: 13px;
  font-weight: 600;
  color: #2d3748;
  white-space: nowrap;
}

/* Divider */
.summary-divider {
  height: 1px;
  background: #f0f0f0;
  margin: 16px 0;
}

/* Price breakdown */
.summary-breakdown {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 4px;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
  color: #718096;
}

.savings-row {
  color: #2f855a;
}

.savings-label {
  font-weight: 500;
}

.savings-amount {
  font-weight: 600;
  color: #2f855a;
}

/* Savings banner */
.savings-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  font-weight: 600;
  color: #166534;
  margin: 12px 0;
}

.savings-banner svg {
  flex-shrink: 0;
  color: #16a34a;
}

/* Total row */
.summary-total-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 4px 0 8px;
}

.total-label {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.total-label span:first-child {
  font-size: 17px;
  font-weight: 700;
  color: #1a202c;
}

.total-note {
  font-size: 11px !important;
  color: #a0aec0 !important;
  font-weight: 400 !important;
}

.total-amount {
  font-size: 26px;
  font-weight: 700;
  color: #48bb78;
}

/* Notes */
.summary-notes {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 4px;
}

.summary-note {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 12px;
  color: #718096;
  line-height: 1.5;
}

.note-icon {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 1px;
}

.note-icon-info {
  background: #ebf8ff;
  color: #2b6cb0;
}

.note-icon-truck {
  background: #f0fdf4;
  color: #276749;
}

@media (max-width: 1200px) {
  .checkout-content {
    grid-template-columns: 1fr;
  }

  .order-summary-sidebar {
    position: static;
  }
}

@media (max-width: 768px) {
  .step-indicator {
    gap: 8px;
  }

  .step-line {
    width: 60px;
  }

  .step-label {
    display: none;
  }

  .payment-methods-grid {
    grid-template-columns: 1fr;
  }

  .info-row {
    grid-template-columns: 1fr;
    gap: 4px;
  }

  .order-item {
    grid-template-columns: 60px 1fr;
    gap: 12px;
  }

  .item-total {
    grid-column: 2;
    text-align: right;
  }
}
</style>
