<!-- frontend/src/views/authenticated/Checkout.vue -->
<template>
  <div class="checkout-page bc-theme">
    <NavHeader :cartCount="0" />

    <main class="bc-container checkout-container">
      <header class="checkout-header">
        <div>
          <p class="bc-label">Secure checkout</p>
          <h1>Complete your <em>order</em></h1>
        </div>
        <ol class="step-indicator" aria-label="Checkout steps">
          <li
            class="step"
            :class="{ active: currentStep >= 1, completed: currentStep > 1 }"
            :aria-current="currentStep === 1 ? 'step' : undefined"
          >
            <span class="step-number">i</span>
            <span class="step-label">Delivery date</span>
          </li>
          <li class="step-line" :class="{ active: currentStep > 1 }" aria-hidden="true"></li>
          <li
            class="step"
            :class="{ active: currentStep >= 2, completed: currentStep > 2 }"
            :aria-current="currentStep === 2 ? 'step' : undefined"
          >
            <span class="step-number">ii</span>
            <span class="step-label">Review order</span>
          </li>
          <li class="step-line" :class="{ active: currentStep > 2 }" aria-hidden="true"></li>
          <li
            class="step"
            :class="{ active: currentStep >= 3 }"
            :aria-current="currentStep === 3 ? 'step' : undefined"
          >
            <span class="step-number">iii</span>
            <span class="step-label">Payment</span>
          </li>
        </ol>
      </header>

      <!-- Skeleton while checkout details load -->
      <div
        v-if="isLoading"
        class="checkout-content"
        aria-label="Loading checkout details"
      >
        <div class="section-card">
          <span class="bc-skeleton skeleton-line skeleton-title"></span>
          <span class="bc-skeleton skeleton-line skeleton-text"></span>
          <div class="skeleton-calendar">
            <span v-for="n in 35" :key="n" class="bc-skeleton skeleton-day"></span>
          </div>
        </div>
        <aside class="summary-card">
          <span class="bc-skeleton skeleton-line skeleton-title"></span>
          <div v-for="n in 2" :key="n" class="skeleton-summary-item">
            <span class="bc-skeleton skeleton-thumb"></span>
            <span class="bc-skeleton skeleton-line"></span>
          </div>
          <span class="bc-skeleton skeleton-line skeleton-total"></span>
        </aside>
      </div>

      <div v-else class="checkout-content">
        <!-- Left: steps -->
        <div class="checkout-steps">
          <!-- Step 1: Choose Delivery Date -->
          <section v-if="currentStep === 1" class="section-card">
            <h2>Choose your <em>delivery date</em></h2>
            <p class="section-description">
              Select a date for your order delivery. Available dates are shown
              in the calendar.
            </p>

            <ul class="calendar-legend">
              <li class="legend-item">
                <span class="legend-dot dot-today"></span>
                <span class="legend-text">{{ todayLegendText }}</span>
              </li>
              <li class="legend-item">
                <span class="legend-dot dot-prep"></span>
                <span class="legend-text">Preparation time</span>
              </li>
              <li class="legend-item">
                <span class="legend-dot dot-available"></span>
                <span class="legend-text">Available</span>
              </li>
              <li class="legend-item">
                <span class="legend-dot dot-almost"></span>
                <span class="legend-text">Almost full</span>
              </li>
              <li class="legend-item">
                <span class="legend-dot dot-full"></span>
                <span class="legend-text">Fully booked</span>
              </li>
              <li class="legend-item">
                <span class="legend-dot dot-disabled"></span>
                <span class="legend-text">Not available</span>
              </li>
            </ul>

            <div class="inline-calendar">
              <p v-if="vendorReservationNotice" class="vendor-calendar-note">
                {{ vendorReservationNotice }}
              </p>

              <div class="calendar-header">
                <button
                  @click="previousMonth"
                  class="btn-nav"
                  :disabled="!canGoPrevious"
                  aria-label="Previous month"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" />
                  </svg>
                </button>
                <h3 class="calendar-title" aria-live="polite">
                  {{ currentMonthName }} {{ currentYear }}
                </h3>
                <button
                  @click="nextMonth"
                  class="btn-nav"
                  :disabled="!canGoNext"
                  aria-label="Next month"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 6l6 6-6 6" />
                  </svg>
                </button>
              </div>

              <div class="calendar-grid">
                <div
                  v-for="day in dayHeaders"
                  :key="day"
                  class="day-header"
                  aria-hidden="true"
                >
                  {{ day }}
                </div>

                <button
                  v-for="day in calendarDays"
                  :key="day.dateString"
                  type="button"
                  class="calendar-day"
                  :class="{
                    'other-month': !day.isCurrentMonth,
                    'is-selected': selectedDate === day.dateString,
                    'is-disabled': day.isDisabled,
                    [day.colorClass]: true,
                  }"
                  :aria-disabled="day.isDisabled"
                  :aria-pressed="selectedDate === day.dateString"
                  :aria-label="`${day.dateString}${day.label ? ', ' + day.label : ''}`"
                  @click="selectDate(day)"
                >
                  <span class="day-number">{{ day.day }}</span>
                  <span v-if="day.label" class="day-label">{{ day.label }}</span>
                </button>
              </div>

              <p v-if="selectedDate" class="selected-date-display">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="4" y="5" width="16" height="15" rx="2" />
                  <path d="M4 10h16M9 3v4M15 3v4" />
                </svg>
                <span
                  >Selected date: <strong>{{ formatSelectedDate }}</strong></span
                >
              </p>
            </div>

            <div class="step-actions">
              <button
                class="bc-link step-back"
                @click="$router.push('/customer/cart')"
              >
                Back to cart
              </button>
              <button
                class="bc-btn bc-btn--dark"
                @click="goToStep(2)"
                :disabled="!selectedDate"
              >
                Continue to review
                <span class="bc-btn-arrow" aria-hidden="true">→</span>
              </button>
            </div>
          </section>

          <!-- Step 2: Review Order -->
          <section v-if="currentStep === 2" class="section-card">
            <h2>Review your <em>order</em></h2>

            <div class="info-section">
              <h3 class="bc-label">Delivery information</h3>
              <dl class="info-grid">
                <div class="info-row">
                  <dt class="info-label">Name</dt>
                  <dd class="info-value">{{ checkoutData.user?.name || "N/A" }}</dd>
                </div>
                <div class="info-row">
                  <dt class="info-label">Email</dt>
                  <dd class="info-value">{{ checkoutData.user?.email || "N/A" }}</dd>
                </div>
                <div class="info-row">
                  <dt class="info-label">Phone</dt>
                  <dd class="info-value">
                    {{ checkoutData.user?.contact_number || "N/A" }}
                  </dd>
                </div>
                <div class="info-row">
                  <dt class="info-label">Address</dt>
                  <dd class="info-value">{{ checkoutData.user?.address || "N/A" }}</dd>
                </div>
                <div class="info-row">
                  <dt class="info-label">Delivery date</dt>
                  <dd class="info-value">{{ formatSelectedDate }}</dd>
                </div>
              </dl>
            </div>

            <div class="info-section">
              <h3 class="bc-label">Store information</h3>
              <dl class="info-grid">
                <div class="info-row">
                  <dt class="info-label">Store name</dt>
                  <dd class="info-value">
                    {{
                      checkoutData.vendor?.store_name ||
                      checkoutData.vendor?.display_name ||
                      "N/A"
                    }}
                  </dd>
                </div>
                <div class="info-row">
                  <dt class="info-label">Email</dt>
                  <dd class="info-value">{{ checkoutData.vendor?.email || "N/A" }}</dd>
                </div>
                <div class="info-row">
                  <dt class="info-label">Contact</dt>
                  <dd class="info-value">
                    {{ checkoutData.vendor?.contact_number || "N/A" }}
                  </dd>
                </div>
              </dl>
            </div>

            <div class="info-section">
              <h3 class="bc-label">Order items</h3>
              <ul class="order-items-list">
                <li
                  v-for="(item, index) in checkoutData.items"
                  :key="item.cart_item_id ?? `${item.product_id}-${index}`"
                  class="order-item"
                >
                  <div class="item-image-small">
                    <img
                      :src="item.product_image || FALLBACK_IMAGE"
                      :alt="item.product_name"
                      @error="handleImageError"
                    />
                  </div>

                  <div class="item-info">
                    <h4>{{ item.product_name || "Unknown Product" }}</h4>
                    <p class="item-meta">Quantity {{ item.quantity }}</p>
                    <p v-if="hasDiscount(item)" class="item-price-group">
                      <span class="price-discounted"
                        >₱{{ formatPrice(effectivePrice(item)) }}</span
                      >
                      <span class="price-original"
                        >₱{{ formatPrice(originalPrice(item)) }}</span
                      >
                      <span class="price-badge"
                        >{{ discountPercent(item) }}% off</span
                      >
                    </p>
                    <p v-else class="item-price">
                      ₱{{ formatPrice(effectivePrice(item)) }} ×
                      {{ item.quantity }}
                    </p>
                  </div>

                  <div class="item-total">
                    ₱{{ formatPrice(lineTotal(item)) }}
                  </div>
                </li>
              </ul>
            </div>

            <div class="info-section">
              <label class="bc-label" for="customer-notes"
                >Special instructions (optional)</label
              >
              <textarea
                id="customer-notes"
                v-model="customerNotes"
                class="notes-textarea"
                placeholder="Add any special instructions for your order..."
                rows="4"
              ></textarea>
            </div>

            <div class="step-actions">
              <button class="bc-link step-back" @click="goToStep(1)">
                Back to calendar
              </button>
              <button class="bc-btn bc-btn--dark" @click="goToStep(3)">
                Continue to payment
                <span class="bc-btn-arrow" aria-hidden="true">→</span>
              </button>
            </div>
          </section>

          <!-- Step 3: Payment -->
          <section v-if="currentStep === 3" class="section-card">
            <h2>Payment <em>method</em></h2>
            <p class="section-description">Choose your preferred payment method</p>

            <div
              class="payment-methods-grid"
              role="radiogroup"
              aria-label="Payment method"
            >
              <label
                v-for="method in availablePaymentMethods"
                :key="method.type"
                class="payment-method-card"
                :class="{
                  selected: selectedPaymentMethod === method.type,
                  'is-locked': isProcessing,
                }"
              >
                <input
                  type="radio"
                  name="payment"
                  :value="method.type"
                  v-model="selectedPaymentMethod"
                  :disabled="isProcessing"
                />
                <span class="payment-icon" aria-hidden="true">
                  <svg v-if="method.type === 'ewallet'" viewBox="0 0 24 24">
                    <rect x="6" y="3" width="12" height="18" rx="2.5" />
                    <path d="M11 18h2" />
                  </svg>
                  <svg v-else viewBox="0 0 24 24">
                    <rect x="3" y="7" width="18" height="11" rx="2" />
                    <circle cx="12" cy="12.5" r="2.5" />
                  </svg>
                </span>
                <span class="payment-info">
                  <span class="payment-name">{{ method.name }}</span>
                  <span class="payment-description">{{ method.description }}</span>
                </span>
                <span class="payment-radio" aria-hidden="true"></span>
              </label>
            </div>

            <div
              v-if="selectedPaymentMethod === 'ewallet'"
              class="payment-note"
            >
              <h3 class="bc-label">Payment details</h3>
              <p>
                You will be redirected to a secure payment page to complete your
                transaction.
              </p>
            </div>

            <div v-if="selectedPaymentMethod === 'cod'" class="payment-note">
              <h3 class="bc-label">Cash on delivery instructions</h3>
              <ul class="instructions-list">
                <li>Pay when you receive your order</li>
                <li>Have exact amount ready for delivery</li>
                <li>Ensure contact number is correct</li>
              </ul>
            </div>

            <div class="step-actions">
              <button
                class="bc-link step-back"
                :disabled="isProcessing"
                @click="goToStep(2)"
              >
                Back to review
              </button>
              <button
                class="bc-btn bc-btn--dark btn-place-order"
                @click="placeOrder"
                :disabled="!selectedPaymentMethod || isProcessing"
                :aria-busy="isProcessing"
              >
                <span v-if="isProcessing">Processing...</span>
                <span v-else>Place order</span>
              </button>
            </div>
          </section>
        </div>

        <!-- Right: order summary -->
        <aside class="summary-card" aria-labelledby="summary-title">
          <div class="summary-header">
            <h2 id="summary-title">Order <em>summary</em></h2>
            <span class="item-count-badge">
              {{ checkoutData.items?.length || 0 }} item{{
                checkoutData.items?.length !== 1 ? "s" : ""
              }}
            </span>
          </div>

          <ul class="summary-items-list">
            <li
              v-for="(item, index) in checkoutData.items"
              :key="item.cart_item_id ?? `${item.product_id}-${index}`"
              class="summary-item"
            >
              <div class="summary-item-img">
                <img
                  :src="item.product_image || FALLBACK_IMAGE"
                  :alt="item.product_name"
                  @error="handleImageError"
                />
                <span class="summary-item-qty">{{ item.quantity }}</span>
              </div>
              <div class="summary-item-info">
                <p class="summary-item-name">{{ item.product_name }}</p>
                <p class="summary-item-pricing">
                  <template v-if="item.discount_price">
                    <span class="summary-item-price"
                      >₱{{ formatPrice(item.discount_price) }}</span
                    >
                    <span class="summary-item-original"
                      >₱{{ formatPrice(item.original_price) }}</span
                    >
                  </template>
                  <span v-else class="summary-item-price"
                    >₱{{ formatPrice(item.unit_price) }}</span
                  >
                </p>
              </div>
              <div class="summary-item-total">
                ₱{{ formatPrice(effectivePrice(item) * item.quantity) }}
              </div>
            </li>
          </ul>

          <div class="summary-breakdown">
            <div class="breakdown-row">
              <span>Original price</span>
              <span>₱{{ formatPrice(checkoutOriginalTotal) }}</span>
            </div>
            <div class="breakdown-row savings-row" v-if="checkoutTotalSavings > 0">
              <span>Discount savings</span>
              <span class="savings-amount"
                >−₱{{ formatPrice(checkoutTotalSavings) }}</span
              >
            </div>
          </div>

          <p v-if="checkoutTotalSavings > 0" class="savings-banner">
            You save ₱{{ formatPrice(checkoutTotalSavings) }} on this order.
          </p>

          <div class="summary-total-row">
            <div class="total-label">
              <span>Total</span>
              <span class="total-note">Final order total</span>
            </div>
            <span class="total-amount">₱{{ formatPrice(totalAmount) }}</span>
          </div>

          <ul class="summary-notes">
            <li>Your order will be thoughtfully prepared.</li>
            <li>No delivery fee is included in this checkout.</li>
          </ul>
        </aside>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import NavHeader from "../../layouts/NavHeader.vue";
import "../../styles/bloomcraft.css";
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
const isProcessing = ref(false);
const isDirectCheckout = ref(false);
const directCheckoutData = ref(null);
const pendingOnlineOrderId = ref(null);
const CHECKOUT_DRAFT_KEY = "checkout_payment_draft";

// Reservation state
const selectedDate = ref(null);
const calendarDate = ref(new Date());
const calendarData = ref({});
const leadTimeDays = ref(3);
const preparationDays = ref(0);
const vendorReservationSettings = ref({
  timezone: PH_TIMEZONE,
  sameDayDelivery: false,
  sameDayAvailableToday: false,
  operatingSchedules: [],
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

const selectedDateRemainingCapacity = computed(() => {
  const availability = selectedDate.value
    ? calendarData.value[selectedDate.value]
    : null;
  const maxOrders = Number(
    availability?.max_orders ?? vendorReservationSettings.value.maxOrdersPerDay,
  );
  const ordersCount = Number(availability?.orders_count ?? 0);

  return Math.max(
    0,
    (Number.isFinite(maxOrders) ? maxOrders : 0) -
      (Number.isFinite(ordersCount) ? ordersCount : 0),
  );
});

const vendorReservationNotice = computed(() => {
  const parts = [];
  const leadDays = leadTimeDays.value;

  parts.push(
    `Vendor capacity: ${selectedDateRemainingCapacity.value} orders remaining.`,
  );
  if (leadDays === 0) {
    parts.push("No lead time required for future dates.");
  } else if (leadDays === 1) {
    parts.push("1 day lead time (starts tomorrow).");
  } else {
    parts.push(`${leadDays} days lead time (starts tomorrow).`);
  }
  parts.push(
    `Earliest future delivery date is after ${leadDays} ${leadDays === 1 ? "day" : "days"} from tomorrow.`,
  );

  if (vendorReservationSettings.value.sameDayDelivery) {
    if (vendorReservationSettings.value.sameDayAvailableToday) {
      parts.push(
        `Same-day delivery is available until ${formatCutoffTime(vendorReservationSettings.value.cutoffTimeToday)} PH time.`,
      );
    } else {
      parts.push("Same-day delivery is not available for today.");
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
  minDate.setDate(minDate.getDate() + leadTimeDays.value + 1);

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
  } else if (isWithinLeadTime || isSameDayCutoffBlocked) {
    colorClass = "day-prep-time";
    label = isSameDayCutoffBlocked
      ? "Cutoff"
      : "Prep Time";
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
        `Preparation starts tomorrow. Please select a date at least ${leadTimeDays.value} ${leadTimeDays.value === 1 ? "day" : "days"} from today.`,
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
      leadTimeDays.value = Number(vendor.lead_time_days ?? 0);
      preparationDays.value = leadTimeDays.value;
      vendorReservationSettings.value = {
        timezone: vendor.timezone || PH_TIMEZONE,
        sameDayDelivery: Boolean(vendor.same_day_delivery),
        sameDayAvailableToday: Boolean(vendor.same_day_available_today),
        operatingSchedules: Array.isArray(vendor.operating_schedules)
          ? vendor.operating_schedules
          : [],
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

function saveCheckoutDraft() {
  sessionStorage.setItem(CHECKOUT_DRAFT_KEY, JSON.stringify({
    selectedDate: selectedDate.value,
    customerNotes: customerNotes.value,
    selectedPaymentMethod: selectedPaymentMethod.value,
  }));
}

function restoreCheckoutDraft() {
  try {
    const draft = JSON.parse(sessionStorage.getItem(CHECKOUT_DRAFT_KEY) || "null");
    if (!draft) return;
    selectedDate.value = draft.selectedDate || null;
    customerNotes.value = draft.customerNotes || "";
    selectedPaymentMethod.value = draft.selectedPaymentMethod || selectedPaymentMethod.value;
  } catch (error) {
    console.warn("Unable to restore checkout draft", error);
  }
}

async function restorePendingOnlineOrderState() {
  if (!pendingOnlineOrderId.value) return;

  try {
    const response = await api.get(
      `/checkout/orders/${pendingOnlineOrderId.value}`,
    );
    const order = response.data?.data;
    const reservationDate = String(order?.reservation_date || "").slice(0, 10);

    // Session storage is a convenience, not the source of truth. Returning
    // from PayMongo must still restore the original reservation if storage was
    // cleared, the browser was restarted, or the redirect used another tab.
    if (!selectedDate.value && reservationDate) {
      selectedDate.value = reservationDate;
      const [year, month] = reservationDate.split("-").map(Number);
      calendarDate.value = new Date(year, month - 1, 1);
    }
    if (!customerNotes.value && order?.customer_notes) {
      customerNotes.value = order.customer_notes;
    }
  } catch (error) {
    // The draft is still usable if the pending order no longer exists.
    console.warn("Unable to restore cancelled checkout order", error);
  }
}

async function abandonPendingOnlinePayment() {
  if (!pendingOnlineOrderId.value) return;

  try {
    await api.post(
      `/checkout/orders/${pendingOnlineOrderId.value}/abandon-payment`,
    );
  } catch (error) {
    console.warn("Unable to retire unfinished PayMongo checkout", error);
  }
}

async function loadCheckoutData() {
  try {
    isLoading.value = true;

    const savedData = localStorage.getItem("checkout_data");
    const directData = sessionStorage.getItem("directCheckout");
    const isDirect = route.query.direct === "true" || (
      Boolean(pendingOnlineOrderId.value) && Boolean(directData)
    );

    if (isDirect && directData) {
      isDirectCheckout.value = true;
      directCheckoutData.value = JSON.parse(directData);

      const directCheckoutPayload = directCheckoutData.value.custom_items
        ? { custom_items: directCheckoutData.value.custom_items }
        : {
            product_id: directCheckoutData.value.items[0].product_id,
            quantity: directCheckoutData.value.items[0].quantity,
          };
      const response = await api.post("/checkout/initialize", directCheckoutPayload);

      if (response.data.success) {
        checkoutData.value = response.data.data;
        preparationDays.value = Number(
          checkoutData.value.summary?.lead_time_days ??
            checkoutData.value.vendor?.lead_time_days ??
            0,
        );
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
        preparationDays.value = Number(
          checkoutData.value.summary?.lead_time_days ??
            checkoutData.value.vendor?.lead_time_days ??
            0,
        );
      } else {
        toast.error("Failed to load checkout data");
        router.push("/customer/cart");
        return;
      }
    }

    // Set available payment methods from backend
    if (checkoutData.value.payment_methods?.available_methods) {
      availablePaymentMethods.value = checkoutData.value.payment_methods.available_methods.filter(
        (method) => {
          const paymentMethod = String(method?.type || "").toLowerCase();
          return ["ewallet", "cod"].includes(paymentMethod);
        },
      );
      // Set default payment method
      if (
        availablePaymentMethods.value.some(
          (method) =>
            method.type === checkoutData.value.payment_methods.default_method,
        )
      ) {
        selectedPaymentMethod.value =
          checkoutData.value.payment_methods.default_method;
      } else if (availablePaymentMethods.value.length > 0) {
        selectedPaymentMethod.value = availablePaymentMethods.value[0].type;
      }
    }
    // PayMongo replaces this page. Restore the draft only after payment
    // options are loaded, so its selected method can be retained safely.
    restoreCheckoutDraft();
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

    // A cancelled online order can be retried only through E-Wallet. Selecting
    // COD deliberately starts a fresh order instead of submitting the old one.
    const isOnlineRetry = Boolean(pendingOnlineOrderId.value)
      && selectedPaymentMethod.value === "ewallet";
    if (!isOnlineRetry) {
      pendingOnlineOrderId.value = null;
      sessionStorage.removeItem("pending_online_order_id");
    }

    const orderData = {
      reservation_date: selectedDate.value,
      payment_method: selectedPaymentMethod.value,
      delivery_address: checkoutData.value.user?.address || "",
      contact_number: checkoutData.value.user?.contact_number || "",
      customer_notes: customerNotes.value,
      ...(isOnlineRetry
        ? { retry_order_id: pendingOnlineOrderId.value }
        : {}),

      ...(isDirectCheckout.value
        ? directCheckoutData.value.custom_items
          ? { custom_items: directCheckoutData.value.custom_items }
          : {
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

    const checkoutUrl = response.data.checkout_url;

    if (checkoutUrl) {
      saveCheckoutDraft();
      pendingOnlineOrderId.value = response.data.order_id;
      sessionStorage.setItem(
        "pending_online_order_id",
        String(response.data.order_id),
      );

      window.location.href = checkoutUrl;
      return;
    }

    if (response.data.success) {
      if (response.data.data?.payment?.requires_redirect) {
        console.error("Checkout redirect URL missing:", response.data);
        toast.error(
          "Payment session was created but no checkout URL was returned.",
        );
        return;
      } else {
        if (isDirectCheckout.value) {
          sessionStorage.removeItem("directCheckout");
        } else {
          localStorage.removeItem("checkout_data");
        }
        sessionStorage.removeItem("pending_online_order_id");
        sessionStorage.removeItem(CHECKOUT_DRAFT_KEY);

        // Show confirmation after the destination has rendered, instead of
        // briefly flashing a Checkout toast during navigation.
        router.push({
          path: "/customer/orders",
          query: {
            ordered: "success",
            order_id: response.data.data?.order?.id,
          },
        });
      }
    } else {
      toast.error(response.data.message || "Failed to place order");
    }
  } catch (error) {
    console.log("Validation errors:", error.response?.data?.errors);
    console.error("Checkout error:", error.response?.data || error);
    const retryOrderId = error.response?.data?.data?.order?.id;
    if (retryOrderId) {
      pendingOnlineOrderId.value = retryOrderId;
      sessionStorage.setItem("pending_online_order_id", String(retryOrderId));
    }
    toast.error(error.response?.data?.message || "Failed to place order");
  } finally {
    isProcessing.value = false;
  }
}

const FALLBACK_IMAGE =
  "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=160&h=200&fit=crop";
function handleImageError(event) {
  if (event.target.src !== FALLBACK_IMAGE) event.target.src = FALLBACK_IMAGE;
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
  pendingOnlineOrderId.value = Number(
    route.query.order_id || sessionStorage.getItem("pending_online_order_id"),
  ) || null;
  await loadCheckoutData();
  // This covers both PayMongo's Cancel action and the browser Back action.
  // Neither path may leave an online order active while payment is unfinished.
  if (pendingOnlineOrderId.value) {
    await abandonPendingOnlinePayment();
  }
  await restorePendingOnlineOrderState();
  await loadCalendarData();

  if (route.query.payment === "cancelled" || pendingOnlineOrderId.value) {
    currentStep.value = 3;
    if (route.query.payment === "cancelled") {
      toast.info("Payment was not completed. Choose E-Wallet to try again.");
    }
  }
});
</script>

<style scoped>
/* Tokens, type, labels, buttons, links, skeletons and the navbar come from
   src/styles/bloomcraft.css (shared with the landing page, Shop and Cart). */

@layer bc-reset {
  .checkout-page :is(p, h1, h2, h3, h4, ul, ol, dl, dd) {
    margin: 0;
  }

  .checkout-page :is(ul, ol) {
    padding: 0;
    list-style: none;
  }
}

:global(body) {
  margin: 0;
}

.checkout-page {
  min-height: 100vh;
  background: var(--ivory);
}

.checkout-page button {
  font-family: var(--font-sans);
}

.checkout-container {
  padding-top: 150px;
  padding-bottom: var(--section-space);
}

/* ---- Header + steps ---- */
.checkout-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 32px;
  margin-bottom: 56px;
}

.checkout-header h1 {
  margin-top: 22px;
  font-size: clamp(48px, 5.6vw, 84px);
}

.step-indicator {
  display: flex;
  align-items: center;
  gap: 14px;
}

.step {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--ink-soft);
  opacity: 0.55;
  transition: opacity 0.4s ease;
}

.step.active {
  color: var(--ink);
  opacity: 1;
}

.step-number {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border: 1px solid currentColor;
  border-radius: 50%;
  font-family: var(--font-display);
  font-size: 16px;
  font-style: italic;
}

.step.active .step-number {
  background: var(--ink);
  border-color: var(--ink);
  color: var(--ivory);
}

.step.completed .step-number {
  background: transparent;
  color: var(--ink);
}

.step-label {
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.step-line {
  width: 36px;
  height: 1px;
  background: var(--line);
}

.step-line.active {
  background: var(--ink);
}

/* ---- Layout ---- */
.checkout-content {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 380px;
  gap: clamp(32px, 4vw, 64px);
  align-items: start;
}

.section-card {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: clamp(28px, 3vw, 44px);
  border: 1px solid var(--line-soft);
  border-radius: 22px;
  background: var(--paper);
}

.section-card h2 {
  font-size: clamp(34px, 3.2vw, 46px);
}

.section-description {
  margin-top: -12px;
  color: var(--ink-soft);
  font-size: 15px;
}

/* ---- Calendar ---- */
.calendar-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 20px;
}

.legend-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--ink-soft);
  font-size: 12.5px;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

/* Status colours, shared by the legend and the calendar days */
.checkout-page {
  --day-available: #7d8f69;
  --day-almost: #c99a4a;
  --day-full: var(--rosewood);
}

.dot-today {
  border: 1.5px solid var(--ink);
}

.dot-prep {
  background: var(--ivory-deep);
  border: 1px solid var(--line);
}

.dot-available {
  background: var(--day-available);
}

.dot-almost {
  background: var(--day-almost);
}

.dot-full {
  background: var(--day-full);
}

.dot-disabled {
  background: var(--line);
}

.vendor-calendar-note {
  margin-bottom: 16px;
  padding: 12px 16px;
  border-radius: 12px;
  background: var(--ivory);
  color: var(--ink-soft);
  font-size: 13.5px;
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
}

.checkout-page .calendar-title {
  font-family: var(--font-display);
  font-size: 28px;
}

.btn-nav {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: none;
  color: var(--ink);
  cursor: pointer;
  transition: border-color 0.3s;
}

.btn-nav:hover:not(:disabled) {
  border-color: var(--ink);
}

.btn-nav:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.btn-nav svg,
.selected-date-display svg,
.payment-icon svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 6px;
}

.day-header {
  padding-bottom: 8px;
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-align: center;
  text-transform: uppercase;
}

.calendar-day {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  min-height: 62px;
  padding: 6px 2px;
  border: 1px solid transparent;
  border-radius: 12px;
  background: #fff;
  color: var(--ink);
  cursor: pointer;
  transition:
    border-color 0.25s,
    background 0.25s,
    transform 0.25s var(--ease-out);
}

.calendar-day:hover:not(.is-disabled) {
  border-color: var(--ink);
}

.day-number {
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 500;
  line-height: 1;
}

.day-label {
  font-size: 9.5px;
  font-weight: 500;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

/* Status: a coloured bottom bar keeps the grid calm but readable */
.calendar-day::after {
  content: "";
  position: absolute;
  left: 30%;
  right: 30%;
  bottom: 6px;
  height: 2px;
  border-radius: 2px;
  background: transparent;
}

.calendar-day.day-available::after {
  background: var(--day-available);
}

.calendar-day.day-almost-full::after {
  background: var(--day-almost);
}

.calendar-day.day-fully-booked {
  background: rgba(184, 101, 88, 0.07);
  color: var(--ink-soft);
}

.calendar-day.day-fully-booked::after {
  background: var(--day-full);
}

.calendar-day.day-prep-time {
  background: var(--ivory-deep);
  color: var(--ink-soft);
}

.calendar-day.day-today {
  border-color: var(--ink);
}

/* Unavailable days stay clickable: selectDate() explains why they can't be chosen. */
.calendar-day.day-disabled,
.calendar-day.is-disabled {
  cursor: not-allowed;
}

.calendar-day.day-disabled {
  background: transparent;
  color: rgba(30, 27, 24, 0.3);
}

.calendar-day.other-month {
  opacity: 0.45;
}

.calendar-day.is-selected {
  background: var(--ink);
  border-color: var(--ink);
  color: var(--ivory);
}

.calendar-day.is-selected::after {
  background: var(--ivory);
}

.calendar-day:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 2px;
}

.selected-date-display {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 18px;
  padding: 14px 16px;
  border-radius: 12px;
  background: var(--ivory);
  color: var(--ink-soft);
  font-size: 14px;
}

.selected-date-display strong {
  color: var(--ink);
  font-weight: 500;
}

/* ---- Step actions ---- */
.step-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  padding-top: 24px;
  border-top: 1px solid var(--line);
}

.step-back {
  border: none;
  background: none;
  cursor: pointer;
}

.step-back:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.step-actions .bc-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

/* ---- Review ---- */
.info-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  border-top: 1px solid var(--line);
}

.info-row {
  padding: 12px 0;
  border-bottom: 1px solid var(--line-soft);
}

.info-row:nth-child(odd) {
  padding-right: 20px;
}

.info-label {
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.info-value {
  margin-top: 2px;
  font-size: 14.5px;
  word-break: break-word;
}

.order-items-list {
  border-top: 1px solid var(--line);
}

.order-item {
  display: grid;
  grid-template-columns: 64px minmax(0, 1fr) auto;
  gap: 16px;
  align-items: center;
  padding: 14px 0;
  border-bottom: 1px solid var(--line-soft);
}

.item-image-small {
  width: 64px;
  aspect-ratio: 4 / 5;
  overflow: hidden;
  border-radius: 10px;
  background: var(--ivory-deep);
}

.item-image-small img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.item-info h4 {
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 500;
  line-height: 1.15;
}

.item-meta,
.item-price {
  color: var(--ink-soft);
  font-size: 13px;
}

.item-price-group {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px;
  font-size: 13px;
}

.price-original {
  color: var(--ink-soft);
  text-decoration: line-through;
}

.price-badge {
  color: var(--rosewood-dark);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.item-total {
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 500;
}

.notes-textarea {
  width: 100%;
  padding: 14px 16px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #fff;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 14px;
  resize: vertical;
}

.notes-textarea:focus {
  outline: none;
  border-color: var(--ink);
}

/* ---- Payment ---- */
.payment-methods-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}

.payment-method-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px;
  border: 1px solid var(--line);
  border-radius: 16px;
  background: #fff;
  cursor: pointer;
  transition: border-color 0.3s;
}

.payment-method-card:hover {
  border-color: rgba(30, 27, 24, 0.35);
}

.payment-method-card.selected {
  border-color: var(--ink);
}

.payment-method-card.is-locked {
  cursor: not-allowed;
}

.payment-method-card input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.payment-method-card:focus-within {
  outline: 2px solid var(--rosewood);
  outline-offset: 3px;
}

.payment-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: var(--ivory);
  color: var(--ink);
}

.payment-info {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.payment-name {
  font-family: var(--font-display);
  font-size: 21px;
  font-weight: 500;
  line-height: 1.15;
}

.payment-description {
  color: var(--ink-soft);
  font-size: 12.5px;
  line-height: 1.5;
}

.payment-radio {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  border: 1px solid var(--line);
  border-radius: 50%;
  transition: border 0.25s;
}

.payment-method-card.selected .payment-radio {
  border: 5px solid var(--ink);
}

.payment-note {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 18px 20px;
  border-radius: 14px;
  background: var(--ivory);
  color: var(--ink-soft);
  font-size: 14px;
}

.instructions-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.instructions-list li::before {
  content: "—";
  margin-right: 10px;
  color: var(--rosewood);
}

/* ---- Summary ---- */
.summary-card {
  position: sticky;
  top: 110px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 32px 30px;
  border: 1px solid var(--line-soft);
  border-radius: 22px;
  background: var(--paper);
}

.summary-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.summary-header h2 {
  font-size: 32px;
}

.item-count-badge {
  color: var(--ink-soft);
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.summary-items-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
  max-height: 300px;
  padding-top: 16px;
  overflow-y: auto;
  border-top: 1px solid var(--line);
}

.summary-item {
  display: grid;
  grid-template-columns: 48px minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
}

.summary-item-img {
  position: relative;
  width: 48px;
  height: 56px;
}

.summary-item-img img {
  display: block;
  width: 100%;
  height: 100%;
  border-radius: 8px;
  object-fit: cover;
  background: var(--ivory-deep);
}

.summary-item-qty {
  position: absolute;
  top: -6px;
  right: -6px;
  display: grid;
  place-items: center;
  min-width: 18px;
  height: 18px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--ink);
  color: var(--ivory);
  font-size: 10px;
  font-weight: 600;
}

.summary-item-name {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.summary-item-pricing {
  display: flex;
  gap: 6px;
  color: var(--ink-soft);
  font-size: 12px;
}

.summary-item-original {
  text-decoration: line-through;
}

.summary-item-total {
  font-size: 13.5px;
  font-weight: 500;
}

.summary-breakdown {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 16px;
  border-top: 1px solid var(--line);
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  color: var(--ink-soft);
  font-size: 14px;
}

.breakdown-row span:last-child {
  color: var(--ink);
}

.savings-row .savings-amount {
  color: var(--rosewood-dark);
}

.savings-banner {
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(184, 101, 88, 0.08);
  color: var(--rosewood-dark);
  font-size: 13px;
}

.summary-total-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding-top: 16px;
  border-top: 1px solid var(--line);
}

.total-label {
  display: flex;
  flex-direction: column;
  font-size: 14px;
  font-weight: 500;
}

.total-note {
  color: var(--ink-soft);
  font-size: 12px;
  font-weight: 300;
}

.total-amount {
  font-family: var(--font-display);
  font-size: 40px;
  font-weight: 500;
  line-height: 1;
}

.summary-notes {
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: var(--ink-soft);
  font-size: 12.5px;
}

.summary-notes li::before {
  content: "—";
  margin-right: 8px;
  color: var(--rosewood);
}

.btn-nav:focus-visible,
.step-back:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 3px;
}

/* ---- Skeleton ---- */
.skeleton-line {
  display: block;
  height: 13px;
}

.skeleton-title {
  width: 55%;
  height: 32px;
}

.skeleton-text {
  width: 75%;
}

.skeleton-calendar {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 6px;
}

.skeleton-day {
  height: 62px;
  border-radius: 12px;
}

.skeleton-summary-item {
  display: grid;
  grid-template-columns: 48px 1fr;
  gap: 12px;
  align-items: center;
}

.skeleton-thumb {
  height: 56px;
  border-radius: 8px;
}

.skeleton-total {
  height: 36px;
}

/* ---- Responsive ---- */
@media (max-width: 1024px) {
  .checkout-content {
    grid-template-columns: 1fr;
  }

  .summary-card {
    position: static;
  }
}

@media (max-width: 640px) {
  .checkout-container {
    padding-top: 120px;
  }

  .step-label {
    display: none;
  }

  .step-line {
    width: 20px;
  }

  .calendar-grid,
  .skeleton-calendar {
    gap: 4px;
  }

  .calendar-day,
  .skeleton-day {
    min-height: 48px;
    height: auto;
    border-radius: 10px;
  }

  .day-number {
    font-size: 17px;
  }

  .day-label {
    font-size: 8px;
    letter-spacing: 0;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .info-row:nth-child(odd) {
    padding-right: 0;
  }

  .step-actions {
    flex-direction: column-reverse;
    align-items: stretch;
    text-align: center;
  }

  .step-actions .bc-btn {
    justify-content: center;
  }

  .step-back {
    align-self: center;
  }
}
</style>
