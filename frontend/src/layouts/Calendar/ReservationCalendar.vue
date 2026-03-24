<!-- ReservationCalendar.vue -->
<template>
  <div class="reservation-calendar">
    <LoadingOverlay :visible="isLoading" message="Loading calendar..." />

    <!-- Calendar Header -->
    <div class="calendar-header">
      <button class="btn-nav" @click="previousMonth" :disabled="isCurrentMonth">
        ←
      </button>
      <div class="month-display">
        <h3>{{ currentMonthName }} {{ currentYear }}</h3>
        <button v-if="!isCurrentMonth" class="btn-today" @click="goToToday">
          Today
        </button>
      </div>
      <button class="btn-nav" @click="nextMonth" :disabled="!canGoNext">
        →
      </button>
    </div>

    <!-- Lead Time Notice (Only show for customers) -->
    <div class="lead-time-notice" v-if="leadTimeDays > 0 && disablePastDates">
      <span class="notice-icon">⏰</span>
      <span class="notice-text">
        Please note: Flowers require {{ leadTimeDays }} days preparation time.
        Dates within this period are unavailable.
      </span>
    </div>

    <!-- Legend -->
    <div class="calendar-legend">
      <div class="legend-item">
        <span class="legend-dot available"></span> Available
      </div>
      <div class="legend-item">
        <span class="legend-dot almost-full"></span> Almost Full
      </div>
      <div class="legend-item">
        <span class="legend-dot fully-booked"></span> Fully Booked
      </div>
      <div class="legend-item">
        <span class="legend-dot closed"></span> Closed
      </div>
      <div v-if="disablePastDates" class="legend-item">
        <span class="legend-dot lead-time"></span> Preparation Time
      </div>
      <div class="legend-item">
        <span class="legend-dot user-order"></span> Your Order
      </div>
      <div v-if="!disablePastDates" class="legend-item">
        <span class="legend-dot past-date"></span> Past Date
      </div>
    </div>

    <!-- Calendar Grid -->
    <div class="calendar-grid">
      <div v-for="day in weekDays" :key="day" class="day-header">{{ day }}</div>

      <div
        v-for="day in calendarDays"
        :key="day.dateString"
        class="calendar-day"
        :class="{
          'other-month': !day.isCurrentMonth,
          'is-today': day.isToday && selectedDate?.date !== day.dateString,
          'is-selected': isSelected(day),
          disabled: day.isDisabled,
          'lead-time-day': day.isWithinLeadTime,
          [day.colorClass]: true,
          'past-date': day.isPastDate && !disablePastDates,
        }"
        @click="selectDate(day)"
      >
        <div class="day-number">{{ day.day }}</div>
        <div v-if="day.isCurrentMonth" class="day-info">
          <div v-if="day.availability?.has_user_order" class="user-order-badge">
            ✓ Ordered
          </div>

          <!-- Customer View Only: Lead Time -->
          <div
            v-else-if="
              day.isWithinLeadTime && disablePastDates && day.isCurrentMonth
            "
            class="lead-time-badge"
          >
            ⏰ Prep
          </div>

          <!-- Customer View Only: Available Slots -->
          <div
            v-else-if="
              disablePastDates &&
              !day.isDisabled &&
              !day.isWithinLeadTime &&
              !day.isPastDate &&
              day.availability?.available_slots > 0
            "
            class="slots-info"
          >
            {{ day.availability.available_slots }} left
          </div>

          <!-- Customer View Only: Fully Booked -->
          <div
            v-else-if="
              disablePastDates &&
              !day.isDisabled &&
              !day.isWithinLeadTime &&
              !day.isPastDate &&
              day.availability?.available_slots <= 0
            "
            class="fully-booked-badge"
          >
            Full
          </div>

          <!-- Past Dates for Customer View -->
          <div
            v-else-if="day.isPastDate && disablePastDates"
            class="past-date-info"
          >
            Unavailable
          </div>

          <!-- Past Dates for Vendor View -->
          <div
            v-else-if="day.isPastDate && !disablePastDates"
            class="past-date-info"
          >
            View Orders
          </div>

          <!-- Vendor View: Future Dates (show order count instead of availability) -->
          <div
            v-else-if="
              !disablePastDates &&
              !day.isPastDate &&
              !day.isDisabled &&
              day.availability
            "
            class="vendor-order-count"
          >
            {{ day.availability.order_count || 0 }} orders
          </div>
        </div>
      </div>
    </div>

    <!-- Selected Date Info -->
    <div v-if="selectedDateInfo" class="selected-date-info">
      <h4>{{ formatSelectedDate }}</h4>
      <div class="date-details">
        <div v-if="selectedDateInfo.has_user_order" class="alert-info">
          <span class="alert-icon">ℹ️</span>
          <p>You already have an order on this date.</p>
        </div>
        <div
          v-else-if="selectedDateInfo.is_within_lead_time && disablePastDates"
          class="alert-warning"
        >
          <span class="alert-icon">⏰</span>
          <p>
            This date requires {{ leadTimeDays }} days preparation time. Please
            select a date at least {{ leadTimeDays }} days from today.
          </p>
        </div>
        <div v-else-if="selectedDateInfo.is_disabled" class="alert-warning">
          <span class="alert-icon">⚠️</span>
          <p>
            {{
              selectedDateInfo.status === "closed"
                ? "This date is closed for orders."
                : selectedDateInfo.status === "fully_booked"
                  ? "This date is fully booked."
                  : "This date is unavailable."
            }}
          </p>
        </div>
        <div
          v-else-if="isPastDate(selectedDate) && !disablePastDates"
          class="past-date-view"
        >
          <p class="view-orders-text">
            <span class="status-icon">📋</span> View orders for this date
          </p>
          <button class="btn-view-orders" @click="viewOrders(selectedDate)">
            View All Orders
          </button>
        </div>
        <div v-else class="date-availability">
          <p class="available-text">
            <span class="status-icon">✓</span> Available for reservation
          </p>
          <p class="slots-remaining">
            {{ selectedDateInfo.available_slots }} of
            {{ selectedDateInfo.max_orders }} slots remaining
          </p>
          <p v-if="leadTimeDays > 0 && disablePastDates" class="lead-time-note">
            ⏰ {{ leadTimeDays }} days preparation time required
          </p>
        </div>
      </div>
    </div>

    <!-- No Selection Info -->
    <div v-else class="no-selection-info">
      <p>Select a date to view availability information</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";
import api from "../../plugins/axios";
import { toast } from "vue3-toastify";

const props = defineProps({
  vendorId: { type: Number, required: true },
  modelValue: { type: Object, default: null },
  // Add new prop to control past date behavior
  disablePastDates: {
    type: Boolean,
    default: true, // Default to true (customer view - past dates disabled)
  },
});

const emit = defineEmits(["update:modelValue", "dateSelected", "viewOrders"]);

// State
const isLoading = ref(false);
const currentDate = ref(new Date());
const selectedDate = ref(props.modelValue);
const calendarData = ref({});
const vendorInfo = ref({});
const leadTimeDays = ref(3); // Default to 3 days

const weekDays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

// Computed
const currentMonth = computed(() => currentDate.value.getMonth());
const currentYear = computed(() => currentDate.value.getFullYear());
const currentMonthName = computed(() =>
  currentDate.value.toLocaleString("default", { month: "long" }),
);

const isCurrentMonth = computed(() => {
  const now = new Date();
  return (
    currentMonth.value === now.getMonth() &&
    currentYear.value === now.getFullYear()
  );
});

const canGoNext = computed(() => {
  const maxDate = new Date();
  maxDate.setMonth(maxDate.getMonth() + 3);
  const nextMonth = new Date(currentDate.value);
  nextMonth.setMonth(nextMonth.getMonth() + 1);
  return nextMonth <= maxDate;
});

const calendarDays = computed(() => {
  const year = currentYear.value;
  const month = currentMonth.value;
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);

  const days = [];
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const firstDayOfWeek = firstDay.getDay();
  for (let i = firstDayOfWeek - 1; i >= 0; i--)
    days.push(createDayObject(new Date(year, month, -i), false));
  for (let day = 1; day <= lastDay.getDate(); day++)
    days.push(createDayObject(new Date(year, month, day), true));
  const remaining = 42 - days.length;
  for (let day = 1; day <= remaining; day++)
    days.push(createDayObject(new Date(year, month + 1, day), false));

  return days;
});

const selectedDateInfo = computed(() =>
  selectedDate.value ? calendarData.value[selectedDate.value] : null,
);

const formatSelectedDate = computed(() => {
  if (!selectedDate.value) return "";

  try {
    const date = new Date(selectedDate.value + "T00:00:00");

    if (isNaN(date.getTime())) {
      return "Invalid Date";
    }

    return date.toLocaleDateString("en-US", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  } catch (error) {
    console.error("Error formatting date:", error);
    return "Invalid Date";
  }
});

// Methods
function createDayObject(date, isCurrentMonth) {
  const dateString = formatDateString(date);
  const availability = calendarData.value[dateString] || null;

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const cleanDate = new Date(
    date.getFullYear(),
    date.getMonth(),
    date.getDate(),
  );

  const isPastDate = cleanDate < today;
  const isToday = cleanDate.getTime() === today.getTime();

  // Calculate lead time days - FIXED: Today should always be disabled for customers
  let isWithinLeadTime = false;
  if (props.disablePastDates) {
    // Today should ALWAYS be disabled for customers (no same-day orders for flowers)
    if (isToday) {
      isWithinLeadTime = true;
    } else if (leadTimeDays.value > 0) {
      // For future dates, check lead time
      const minAvailableDate = new Date(today);
      minAvailableDate.setDate(minAvailableDate.getDate() + leadTimeDays.value);

      const cleanMinAvailableDate = new Date(
        minAvailableDate.getFullYear(),
        minAvailableDate.getMonth(),
        minAvailableDate.getDate(),
      );

      // Date is within lead time if it's BEFORE minAvailableDate
      isWithinLeadTime = cleanDate < cleanMinAvailableDate;
    }
  }

  const maxDate = new Date();
  maxDate.setMonth(maxDate.getMonth() + 3);
  maxDate.setHours(0, 0, 0, 0);
  const isBeyondLimit = cleanDate > maxDate;

  // Different disabled logic for customer vs vendor
  let isDisabled = false;

  if (props.disablePastDates) {
    // Customer view: disable past dates, beyond limit, lead time, or API-disabled
    isDisabled =
      isPastDate ||
      isBeyondLimit ||
      isWithinLeadTime || // This includes today
      availability?.is_disabled ||
      false;
  } else {
    // Vendor view: only disable beyond limit or API-disabled (allow past dates)
    isDisabled = isBeyondLimit || availability?.is_disabled || false;
  }

  // Color class logic
  let colorClass = "day-white";

  if (availability && isCurrentMonth) {
    if (isDisabled) {
      colorClass = "day-disabled";
    } else {
      colorClass = getColorClass(availability.color);
    }
  } else if (isPastDate && props.disablePastDates) {
    colorClass = "day-disabled";
  } else if (isBeyondLimit) {
    colorClass = "day-disabled";
  } else if (isPastDate && !props.disablePastDates) {
    colorClass = "day-gray";
  }

  // Special override for lead time days (including today)
  if (isWithinLeadTime && isCurrentMonth) {
    colorClass = "lead-time-day";
  }

  return {
    day: date.getDate(),
    dateString,
    isCurrentMonth,
    isToday,
    isDisabled,
    isWithinLeadTime,
    isPastDate: isPastDate,
    availability,
    colorClass,
  };
}

function getColorClass(color) {
  const colorMap = {
    red: "day-red",
    orange: "day-orange",
    yellow: "day-yellow",
    white: "day-white",
    green: "day-green",
    gray: "day-gray",
  };
  return colorMap[color] || "day-white";
}

function formatDateString(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

function isSelected(day) {
  return selectedDate.value === day.dateString;
}

function selectDate(day) {
  // Different behavior for customer vs vendor view
  if (props.disablePastDates && day.isDisabled) {
    // Customer view - show error for disabled dates
    if (day.isToday) {
      toast.error(
        "Same-day orders are not available. Please select a future date.",
      );
    } else if (day.isWithinLeadTime) {
      toast.error(
        `This date requires ${leadTimeDays.value} days preparation time. Please select a date at least ${leadTimeDays.value} days from today.`,
      );
    } else if (day.isPastDate) {
      toast.error("Cannot select past dates.");
    } else {
      toast.error("This date is not available for reservation.");
    }
    return;
  }

  if (!props.disablePastDates && day.isDisabled && !day.isPastDate) {
    // Vendor view - only show error for non-past disabled dates
    toast.error("This date is not available.");
    return;
  }

  selectedDate.value = day.dateString;
  emit("update:modelValue", day.dateString);

  emit("dateSelected", {
    date: day.dateString,
    availability: day.availability,
    is_disabled: day.isDisabled,
    is_within_lead_time: day.isWithinLeadTime,
    is_today: day.isToday, // Add this
  });
}

function previousMonth() {
  if (!isCurrentMonth.value) {
    const d = new Date(currentDate.value);
    d.setMonth(d.getMonth() - 1);
    currentDate.value = d;
  }
}

function nextMonth() {
  if (canGoNext.value) {
    const d = new Date(currentDate.value);
    d.setMonth(d.getMonth() + 1);
    currentDate.value = d;
  }
}

function goToToday() {
  currentDate.value = new Date();
}

async function loadCalendarData() {
  try {
    isLoading.value = true;

    // Format dates for API
    const startDate = formatDateString(
      new Date(currentYear.value, currentMonth.value, 1),
    );
    const endDate = formatDateString(
      new Date(currentYear.value, currentMonth.value + 1, 0),
    );

    const res = await api.get("/customer/availability", {
      params: {
        vendor_id: props.vendorId,
        start_date: startDate,
        end_date: endDate,
      },
    });

    if (res.data.success) {
      calendarData.value = res.data.data.calendar || {};
      vendorInfo.value = res.data.data.vendor || {};
      leadTimeDays.value = res.data.data.vendor?.lead_time_days || 3;

      console.log(
        "Loaded calendar data with lead time:",
        leadTimeDays.value,
        "days",
      );
    }
  } catch (e) {
    console.error("Error loading calendar:", e);
    // Fallback to default lead time if API fails
    leadTimeDays.value = 3;
  } finally {
    isLoading.value = false;
  }
}

function viewOrders(date) {
  emit("viewOrders", date);
}

function isPastDate(dateString) {
  if (!dateString) return false;
  const date = new Date(dateString + "T00:00:00");
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return date < today;
}

onMounted(() => {
  loadCalendarData();
});

watch([currentMonth, currentYear], loadCalendarData);

watch(
  () => props.modelValue,
  (val) => {
    if (val && val !== selectedDate.value) {
      selectedDate.value = val;
    }
  },
);
</script>

<style scoped>
.reservation-calendar {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.month-display {
  display: flex;
  align-items: center;
  gap: 12px;
}

.month-display h3 {
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.btn-nav {
  width: 36px;
  height: 36px;
  border: 1px solid #e2e8f0;
  background: white;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s;
  color: #4a5568;
}

.btn-nav:hover:not(:disabled) {
  background: #f7fafc;
  border-color: #48bb78;
  color: #48bb78;
}

.btn-nav:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.btn-today {
  padding: 6px 12px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-today:hover {
  background: #38a169;
}

/* Lead Time Notice */
.lead-time-notice {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #fef3c7;
  border: 1px solid #fbbf24;
  border-radius: 8px;
  margin-bottom: 20px;
  font-size: 14px;
  color: #92400e;
}

.notice-icon {
  font-size: 18px;
}

.notice-text {
  flex: 1;
}

/* Legend */
.calendar-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 20px;
  padding: 12px;
  background: #f7fafc;
  border-radius: 8px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #4a5568;
}

.legend-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 1px solid #cbd5e0;
}

.legend-dot.available {
  background: white;
}

.legend-dot.almost-full {
  background: #fbbf24;
}

.legend-dot.fully-booked {
  background: #f97316;
}

.legend-dot.closed {
  background: #ef4444;
}

.legend-dot.lead-time {
  background: #9ca3af;
}

.legend-dot.user-order {
  background: #10b981;
}

.legend-dot.past-date {
  background: #9ca3af;
  border-color: #6b7280;
}

.calendar-day.past-date {
  background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
  border-color: #d1d5db;
  color: #6b7280;
}

.calendar-day.past-date:hover:not(.disabled) {
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  z-index: 1;
}

.calendar-day.past-date .day-number {
  color: #6b7280;
}

.past-date-info {
  font-size: 9px;
  font-weight: 600;
  color: #6b7280;
  background: white;
  padding: 2px 6px;
  border-radius: 10px;
}

.past-date-view {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  background: #f3f4f6;
  border-radius: 8px;
}

.view-orders-text {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #4b5563;
  margin: 0;
}

.btn-view-orders {
  padding: 10px 16px;
  background: #4f46e5;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  align-self: flex-start;
}

.btn-view-orders:hover {
  background: #4338ca;
  transform: translateY(-2px);
}

/* Calendar Grid */
.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 8px;
}

.day-header {
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  color: #718096;
  padding: 8px 0;
}

.calendar-day {
  aspect-ratio: 1;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  padding: 8px;
  cursor: pointer;
  transition: all 0.3s;
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.calendar-day:hover:not(.disabled):not(.other-month):not(.lead-time-day) {
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  z-index: 1;
}

.calendar-day.other-month {
  opacity: 0.3;
  cursor: default;
}

.calendar-day.disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.calendar-day.lead-time-day {
  background: #fef3c7;
  border-color: #fbbf24;
  color: #92400e;
}

.calendar-day.lead-time-day .day-number {
  color: #92400e;
}

/* FIX: Today's date styling - only when NOT selected */
.calendar-day.is-today {
  border-color: #2d3748;
  font-weight: 600;
  background: rgba(72, 187, 120, 0.1);
}

/* FIX: Selected date styling - overrides today's styling */
.calendar-day.is-selected {
  border-color: #48bb78;
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  font-weight: 600;
}

/* Color classes */
.calendar-day.day-white {
  background: white;
}

.calendar-day.day-green {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  border-color: #10b981;
}

.calendar-day.day-yellow {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border-color: #fbbf24;
}

.calendar-day.day-orange {
  background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
  border-color: #f97316;
}

.calendar-day.day-red {
  background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
  border-color: #ef4444;
}

.calendar-day.day-gray {
  background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
  border-color: #d1d5db;
  color: #6b7280;
}

.calendar-day.day-disabled {
  background: #f7fafc;
}

.day-number {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
}

.calendar-day.day-gray .day-number {
  color: #6b7280;
}

.day-info {
  margin-top: 4px;
  text-align: center;
}

.user-order-badge {
  font-size: 9px;
  font-weight: 600;
  color: #059669;
  background: white;
  padding: 2px 6px;
  border-radius: 10px;
}

.lead-time-badge {
  font-size: 9px;
  font-weight: 600;
  color: #92400e;
  background: #fef3c7;
  padding: 2px 6px;
  border-radius: 10px;
}

.slots-info {
  font-size: 9px;
  color: #718096;
  font-weight: 500;
}

/* Selected Date Info */
.selected-date-info {
  margin-top: 24px;
  padding: 20px;
  background: #f7fafc;
  border-radius: 12px;
  border-left: 4px solid #48bb78;
}

.selected-date-info h4 {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 12px 0;
}

.date-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.alert-info,
.alert-warning {
  display: flex;
  align-items: start;
  gap: 10px;
  padding: 12px;
  border-radius: 8px;
}

.alert-info {
  background: #dbeafe;
  border: 1px solid #60a5fa;
}

.alert-warning {
  background: #fef3c7;
  border: 1px solid #fbbf24;
}

.alert-warning .alert-icon {
  font-size: 18px;
}

.alert-info p,
.alert-warning p {
  margin: 0;
  font-size: 14px;
  color: #2d3748;
}

.date-availability {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.available-text {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #059669;
  margin: 0;
}

.status-icon {
  font-size: 16px;
}

.slots-remaining {
  font-size: 13px;
  color: #4a5568;
  margin: 0;
}

.lead-time-note {
  font-size: 12px;
  color: #6b7280;
  margin: 4px 0 0 0;
  display: flex;
  align-items: center;
  gap: 6px;
}

/* No Selection Info */
.no-selection-info {
  margin-top: 24px;
  padding: 20px;
  background: #f7fafc;
  border-radius: 12px;
  text-align: center;
  color: #718096;
  font-size: 14px;
}

/* Responsive */
@media (max-width: 768px) {
  .calendar-grid {
    gap: 4px;
  }

  .calendar-day {
    padding: 4px;
  }

  .day-number {
    font-size: 12px;
  }

  .day-info {
    display: none;
  }

  .calendar-legend {
    gap: 8px;
  }

  .legend-item {
    font-size: 11px;
  }

  .lead-time-notice {
    font-size: 13px;
    padding: 10px 12px;
  }
}
</style>
