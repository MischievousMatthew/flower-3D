<template>
  <vendorHeader />
  <div class="all-orders-page">
    <VendorSidebar />

    <div class="orders-container">
      <!-- Page Header -->
      <div class="orders-header">
        <h1>All Orders</h1>
        <div class="header-actions">
          <div class="search-box">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"
              />
            </svg>
            <input
              type="text"
              v-model="searchQuery"
              placeholder="Search orders..."
              @input="handleSearch"
            />
          </div>

          <!-- View Toggle -->
          <div class="view-toggle">
            <button
              class="toggle-btn"
              :class="{ active: currentView === 'tabs' }"
              @click="currentView = 'tabs'"
              title="Status Tabs View"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"
                />
              </svg>
              <span>Tabs</span>
            </button>
            <button
              class="toggle-btn"
              :class="{ active: currentView === 'calendar' }"
              @click="currentView = 'calendar'"
              title="Calendar View"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zM9 14H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2zm-8 4H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2z"
                />
              </svg>
              <span>Calendar</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Notifications Section -->
      <div v-if="notifications.length > 0" class="notifications-section">
        <div class="notifications-header">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
          >
            <path
              fill="currentColor"
              d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"
            />
          </svg>
          <h3>Notifications</h3>
          <span class="notification-count">{{ notifications.length }}</span>
        </div>
        <div class="notifications-list">
          <div
            v-for="(notification, index) in notifications"
            :key="index"
            class="notification-item"
            :class="`notification-${notification.type}`"
          >
            <div class="notification-icon">
              <svg
                v-if="notification.type === 'warning'"
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"
                />
              </svg>
              <svg
                v-else-if="notification.type === 'urgent'"
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"
                />
              </svg>
              <svg
                v-else
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
                />
              </svg>
            </div>
            <div class="notification-content">
              <p class="notification-title">{{ notification.title }}</p>
              <p class="notification-message">{{ notification.message }}</p>
            </div>
            <button @click="dismissNotification(index)" class="btn-dismiss">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <LoadingOverlay :visible="isLoading" :message="loadingMessage" />

      <!-- Status Tabs View -->
      <div v-if="currentView === 'tabs'" class="tabs-view">
        <div class="status-tabs">
          <button
            v-for="status in statusList"
            :key="status.value"
            class="status-tab"
            :class="{ active: activeStatusTab === status.value }"
            @click="activeStatusTab = status.value"
          >
            <span class="tab-label">{{ status.label }}</span>
            <span class="tab-count">{{
              getOrderCountByStatus(status.value)
            }}</span>
          </button>
        </div>

        <!-- Orders List for Selected Tab -->
        <div class="orders-list">
          <div
            v-if="filteredOrdersByTab.length === 0 && !isLoading"
            class="empty-state"
          >
            <div class="empty-icon">📦</div>
            <h3>No {{ activeStatusTab }} orders</h3>
            <p>Orders with this status will appear here</p>
          </div>

          <div
            v-for="order in filteredOrdersByTab"
            :key="order.id"
            class="order-card"
            :data-order-date="order.reservation_date"
            :id="`order-${order.id}`"
          >
            <div class="order-card-header">
              <div class="order-info-left">
                <h3>Order #{{ order.order_number }}</h3>
                <span class="order-date">
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
                  Reservation:
                  {{ formatDate(order.reservation_date) || "Date not set" }}
                </span>
              </div>
              <div class="order-info-right">
                <span class="order-status" :class="`status-${order.status}`">
                  {{ formatStatus(order.status) }}
                </span>
                <span class="order-total"
                  >₱{{ formatPrice(order.total_amount) }}</span
                >
              </div>
            </div>

            <div class="order-card-body">
              <!-- Customer Information -->
              <div class="info-section-compact">
                <h4>Customer Information</h4>
                <div class="info-grid-compact">
                  <div class="info-item">
                    <span class="info-icon">👤</span>
                    <div class="info-content">
                      <span class="info-label">Name</span>
                      <span class="info-value">{{
                        order.customer?.name || order.user?.name || "N/A"
                      }}</span>
                    </div>
                  </div>
                  <div class="info-item">
                    <span class="info-icon">📧</span>
                    <div class="info-content">
                      <span class="info-label">Email</span>
                      <span class="info-value">{{
                        order.customer?.email || order.user?.email || "N/A"
                      }}</span>
                    </div>
                  </div>
                  <div class="info-item">
                    <span class="info-icon">📱</span>
                    <div class="info-content">
                      <span class="info-label">Phone</span>
                      <span class="info-value">{{
                        order.customer?.contact_number ||
                        order.user?.contact_number ||
                        "N/A"
                      }}</span>
                    </div>
                  </div>
                  <div class="info-item">
                    <span class="info-icon">📍</span>
                    <div class="info-content">
                      <span class="info-label">Address</span>
                      <span class="info-value">{{
                        order.customer?.address || order.user?.address || "N/A"
                      }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Order Items with Images and 3D Models -->
              <div class="info-section-compact">
                <h4>Order Items</h4>
                <div class="order-items-grid">
                  <div
                    v-for="item in order.items"
                    :key="item.id"
                    class="order-item-card"
                  >
                    <div class="item-image-container">
                      <img
                        :src="
                          item.product_image ||
                          getProductImage(item.product) ||
                          'https://via.placeholder.com/100'
                        "
                        :alt="item.product_name || item.product?.product_name"
                        @error="handleImageError"
                        class="item-image"
                      />
                      <button
                        v-if="
                          item.model_3d_url || item.product?.models?.length > 0
                        "
                        @click="show3DModel(item)"
                        class="btn-3d-viewer"
                        title="View 3D Model"
                      >
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          width="16"
                          height="16"
                          viewBox="0 0 24 24"
                        >
                          <path
                            fill="currentColor"
                            d="M21 16.5c0 .38-.21.71-.53.88l-7.9 4.44c-.16.12-.36.18-.57.18-.21 0-.41-.06-.57-.18l-7.9-4.44A.991.991 0 0 1 3 16.5v-9c0-.38.21-.71.53-.88l7.9-4.44c.16-.12.36-.18.57-.18.21 0 .41.06.57.18l7.9 4.44c.32.17.53.5.53.88v9M12 4.15L5 8.09v7.82l7 3.94 7-3.94V8.09l-7-3.94Z"
                          />
                        </svg>
                      </button>
                    </div>
                    <div class="item-details-compact">
                      <h5>
                        {{
                          item.product_name ||
                          item.product?.product_name ||
                          "Unknown Product"
                        }}
                      </h5>
                      <p class="item-quantity">Qty: {{ item.quantity }}</p>
                      <p class="item-price">
                        ₱{{ formatPrice(item.unit_price || item.price) }} ×
                        {{ item.quantity }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Employee Assignment -->
              <div class="info-section-compact">
                <h4>Employee Assignment</h4>
                <div class="employee-assignment" @click.stop>
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"
                    />
                  </svg>
                  <select
                    class="employee-select"
                    :value="order.assigned_employee_id || ''"
                    @change="assignEmployee(order.id, $event.target.value)"
                  >
                    <option value="">-- Select Employee --</option>
                    <option
                      v-for="emp in employees"
                      :key="emp.id"
                      :value="emp.id"
                    >
                      {{ emp.name }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- Customer Notes -->
              <div v-if="order.customer_notes" class="info-section-compact">
                <h4>Customer Notes</h4>
                <div class="customer-notes">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"
                    />
                  </svg>
                  <p>{{ order.customer_notes }}</p>
                </div>
              </div>
            </div>

            <div class="order-card-footer">
              <div class="order-actions">
                <button
                  class="btn-action btn-view"
                  @click="viewOrderDetails(order.id)"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"
                    />
                  </svg>
                  View Details
                </button>
                <button
                  class="btn-action btn-update"
                  @click="updateOrderStatus(order.id, order.status)"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"
                    />
                  </svg>
                  Update Status
                </button>
              </div>
              <div class="order-meta">
                <span class="payment-method">
                  {{
                    order.payment_method === "online"
                      ? "💳 Online Payment"
                      : "💵 Cash on Delivery"
                  }}
                </span>
                <span
                  class="payment-status"
                  :class="`payment-${order.payment_status}`"
                >
                  {{
                    order.payment_status === "paid" ? "✓ Paid" : "⏳ Pending"
                  }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Calendar View -->
      <div v-if="currentView === 'calendar'" class="calendar-view">
        <div class="vendor-calendar-section">
          <div class="calendar-section-header">
            <h3>Order Calendar</h3>
            <button @click="goToToday" class="btn-today">Today</button>
          </div>

          <div class="calendar-controls-compact">
            <button @click="previousMonth" class="btn-nav-small">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"
                />
              </svg>
            </button>
            <span class="month-year-small"
              >{{ currentMonthName }} {{ currentYear }}</span
            >
            <button @click="nextMonth" class="btn-nav-small">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"
                />
              </svg>
            </button>
          </div>

          <div class="vendor-calendar-grid">
            <div
              v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
              :key="day"
              class="day-header-tiny"
            >
              {{ day }}
            </div>

            <div
              v-for="day in calendarDays"
              :key="day.dateString"
              class="vendor-calendar-day"
              :class="{
                'other-month': !day.isCurrentMonth,
                'is-selected': day.isSelected,
                'has-orders': day.orderCount > 0,
                'is-today': day.isToday,
              }"
              @click="selectCalendarDate(day)"
            >
              <div class="day-num-tiny">{{ day.day }}</div>
              <div v-if="day.orderCount > 0" class="order-count-badge">
                {{ day.orderCount }}
              </div>
            </div>
          </div>

          <div v-if="selectedCalendarDate" class="calendar-selection-info">
            Selected: {{ formatCalendarDate(selectedCalendarDate) }}
            <span v-if="getOrdersForDate(selectedCalendarDate).length > 0">
              ({{ getOrdersForDate(selectedCalendarDate).length }} orders)
            </span>
          </div>
        </div>

        <!-- Orders for Selected Date -->
        <div class="orders-list">
          <div
            v-if="filteredOrdersByDate.length === 0 && !isLoading"
            class="empty-state"
          >
            <div class="empty-icon">📦</div>
            <h3>No orders for this date</h3>
            <p>Select a date with orders from the calendar above</p>
          </div>

          <div
            v-for="order in filteredOrdersByDate"
            :key="order.id"
            class="order-card"
            :data-order-date="order.reservation_date"
            :id="`order-${order.id}`"
          >
            <div class="order-card-header">
              <div class="order-info-left">
                <h3>Order #{{ order.order_number }}</h3>
                <span class="order-date">
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
                  Reservation: {{ formatDate(order.reservation_date) }}
                </span>
              </div>
              <div class="order-info-right">
                <span class="order-status" :class="`status-${order.status}`">
                  {{ formatStatus(order.status) }}
                </span>
                <span class="order-total"
                  >₱{{ formatPrice(order.total_amount) }}</span
                >
              </div>
            </div>

            <div class="order-card-body">
              <!-- Customer Information -->
              <div class="info-section-compact">
                <h4>Customer Information</h4>
                <div class="info-grid-compact">
                  <div class="info-item">
                    <span class="info-icon">👤</span>
                    <div class="info-content">
                      <span class="info-label">Name</span>
                      <span class="info-value">{{
                        order.user?.name || "N/A"
                      }}</span>
                    </div>
                  </div>
                  <div class="info-item">
                    <span class="info-icon">📧</span>
                    <div class="info-content">
                      <span class="info-label">Email</span>
                      <span class="info-value">{{
                        order.user?.email || "N/A"
                      }}</span>
                    </div>
                  </div>
                  <div class="info-item">
                    <span class="info-icon">📱</span>
                    <div class="info-content">
                      <span class="info-label">Phone</span>
                      <span class="info-value">{{
                        order.user?.contact_number || "N/A"
                      }}</span>
                    </div>
                  </div>
                  <div class="info-item">
                    <span class="info-icon">📍</span>
                    <div class="info-content">
                      <span class="info-label">Address</span>
                      <span class="info-value">{{
                        order.user?.address || "N/A"
                      }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Order Items -->
              <div class="info-section-compact">
                <h4>Order Items</h4>
                <div class="order-items-grid">
                  <div
                    v-for="item in order.items"
                    :key="item.id"
                    class="order-item-card"
                  >
                    <div class="item-image-container">
                      <img
                        :src="
                          getProductImage(item.product) ||
                          'https://via.placeholder.com/100'
                        "
                        :alt="item.product?.product_name"
                        @error="handleImageError"
                        class="item-image"
                      />
                      <button
                        v-if="
                          item.model_3d_url ||
                          item.product?.models?.length > 0 ||
                          item.has_3d_model
                        "
                        @click="show3DModel(item)"
                        class="btn-3d-viewer"
                        title="View 3D Model"
                      >
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          width="16"
                          height="16"
                          viewBox="0 0 24 24"
                        >
                          <path
                            fill="currentColor"
                            d="M21 16.5c0 .38-.21.71-.53.88l-7.9 4.44c-.16.12-.36.18-.57.18-.21 0-.41-.06-.57-.18l-7.9-4.44A.991.991 0 0 1 3 16.5v-9c0-.38.21-.71.53-.88l7.9-4.44c.16-.12.36-.18.57-.18.21 0 .41.06.57.18l7.9 4.44c.32.17.53.5.53.88v9M12 4.15L5 8.09v7.82l7 3.94 7-3.94V8.09l-7-3.94Z"
                          />
                        </svg>
                      </button>
                    </div>
                    <div class="item-details-compact">
                      <h5>
                        {{ item.product?.product_name || "Unknown Product" }}
                      </h5>
                      <p class="item-quantity">Qty: {{ item.quantity }}</p>
                      <p class="item-price">
                        ₱{{ formatPrice(item.price) }} × {{ item.quantity }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Employee Assignment -->
              <div class="info-section-compact">
                <h4>Employee Assignment</h4>
                <div class="employee-assignment" @click.stop>
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"
                    />
                  </svg>
                  <select
                    class="employee-select"
                    :value="order.assigned_employee_id || ''"
                    @change="assignEmployee(order.id, $event.target.value)"
                  >
                    <option value="">-- Select Employee --</option>
                    <option
                      v-for="emp in employees"
                      :key="emp.id"
                      :value="emp.id"
                    >
                      {{ emp.name }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- Customer Notes -->
              <div v-if="order.customer_notes" class="info-section-compact">
                <h4>Customer Notes</h4>
                <div class="customer-notes">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"
                    />
                  </svg>
                  <p>{{ order.customer_notes }}</p>
                </div>
              </div>
            </div>

            <div class="order-card-footer">
              <div class="order-actions">
                <button
                  class="btn-action btn-view"
                  @click="viewOrderDetails(order.id)"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"
                    />
                  </svg>
                  View Details
                </button>
                <button
                  class="btn-action btn-update"
                  @click="updateOrderStatus(order.id, order.status)"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"
                    />
                  </svg>
                  Update Status
                </button>
              </div>
              <div class="order-meta">
                <span class="payment-method">
                  {{
                    order.payment_method === "online"
                      ? "💳 Online Payment"
                      : order.payment_method === "cod"
                        ? "💵 Cash on Delivery"
                        : order.payment_method === "bank_transfer"
                          ? "🏦 Bank Transfer"
                          : "📱 " + (order.payment_method || "Unknown")
                  }}
                </span>
                <span
                  class="payment-status"
                  :class="`payment-${order.payment_status}`"
                >
                  {{ formatPaymentStatus(order.payment_status) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div
        v-if="pagination.total > pagination.per_page && currentView === 'tabs'"
        class="pagination"
      >
        <button
          @click="changePage(pagination.current_page - 1)"
          :disabled="pagination.current_page === 1"
          class="pagination-btn"
        >
          Previous
        </button>
        <span class="pagination-info">
          Page {{ pagination.current_page }} of {{ pagination.last_page }}
        </span>
        <button
          @click="changePage(pagination.current_page + 1)"
          :disabled="pagination.current_page === pagination.last_page"
          class="pagination-btn"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Order Details Modal -->
    <div
      v-if="showOrderDetailsModal"
      class="modal-overlay"
      @click="closeOrderDetailsModal"
    >
      <div class="modal-content order-details-modal" @click.stop>
        <div class="modal-header">
          <div class="modal-title-section">
            <h3>Order #{{ selectedOrderDetails?.order_number }}</h3>
            <span
              class="order-status-badge"
              :class="`status-${selectedOrderDetails?.status}`"
            >
              {{ formatStatus(selectedOrderDetails?.status) }}
            </span>
          </div>
          <button @click="closeOrderDetailsModal" class="btn-close">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"
              />
            </svg>
          </button>
        </div>

        <div class="modal-body-scrollable">
          <div v-if="selectedOrderDetails" class="order-details-content">
            <!-- Order Summary -->
            <div class="details-section">
              <h4 class="section-title">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                >
                  <path
                    fill="currentColor"
                    d="M13 2.05v3.03c3.39.49 6 3.39 6 6.92 0 .9-.18 1.75-.48 2.54l2.6 1.53c.56-1.24.88-2.62.88-4.07 0-5.18-3.95-9.45-9-9.95zM12 19c-3.87 0-7-3.13-7-7 0-3.53 2.61-6.43 6-6.92V2.05c-5.06.5-9 4.76-9 9.95 0 5.52 4.47 10 9.99 10 3.31 0 6.24-1.61 8.06-4.09l-2.6-1.53C16.17 17.98 14.21 19 12 19z"
                  />
                </svg>
                Order Summary
              </h4>
              <div class="summary-grid">
                <div class="summary-item">
                  <span class="label">Order Number:</span>
                  <span class="value">{{
                    selectedOrderDetails.order_number
                  }}</span>
                </div>
                <div class="summary-item">
                  <span class="label">Reservation Date:</span>
                  <span class="value">{{
                    formatDate(selectedOrderDetails.reservation_date)
                  }}</span>
                </div>
                <div class="summary-item">
                  <span class="label">Created:</span>
                  <span class="value">{{
                    formatDate(selectedOrderDetails.created_at)
                  }}</span>
                </div>
                <div class="summary-item">
                  <span class="label">Payment Method:</span>
                  <span class="value">
                    {{
                      selectedOrderDetails.payment_method === "online"
                        ? "💳 Online Payment"
                        : "💵 Cash on Delivery"
                    }}
                  </span>
                </div>
                <div class="summary-item">
                  <span class="label">Payment Status:</span>
                  <span
                    class="value"
                    :class="`payment-${selectedOrderDetails.payment_status}`"
                  >
                    {{
                      formatPaymentStatus(selectedOrderDetails.payment_status)
                    }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Customer Information -->
            <div class="details-section">
              <h4 class="section-title">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                >
                  <path
                    fill="currentColor"
                    d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"
                  />
                </svg>
                Customer Information
              </h4>
              <div class="customer-info-grid">
                <div class="info-card">
                  <div class="info-icon">👤</div>
                  <div class="info-details">
                    <span class="info-label">Name</span>
                    <span class="info-text">{{
                      selectedOrderDetails.customer?.name ||
                      selectedOrderDetails.user?.name
                    }}</span>
                  </div>
                </div>
                <div class="info-card">
                  <div class="info-icon">📧</div>
                  <div class="info-details">
                    <span class="info-label">Email</span>
                    <span class="info-text">{{
                      selectedOrderDetails.customer?.email ||
                      selectedOrderDetails.user?.email
                    }}</span>
                  </div>
                </div>
                <div class="info-card">
                  <div class="info-icon">📱</div>
                  <div class="info-details">
                    <span class="info-label">Phone</span>
                    <span class="info-text">{{
                      selectedOrderDetails.customer?.contact_number ||
                      selectedOrderDetails.user?.contact_number
                    }}</span>
                  </div>
                </div>
                <div class="info-card full-width">
                  <div class="info-icon">📍</div>
                  <div class="info-details">
                    <span class="info-label">Address</span>
                    <span class="info-text">{{
                      selectedOrderDetails.customer?.address ||
                      selectedOrderDetails.user?.address
                    }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Order Items -->
            <div class="details-section">
              <h4 class="section-title">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                >
                  <path
                    fill="currentColor"
                    d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"
                  />
                </svg>
                Order Items ({{ selectedOrderDetails.items?.length || 0 }})
              </h4>
              <div class="items-list">
                <div
                  v-for="item in selectedOrderDetails.items"
                  :key="item.id"
                  class="item-row"
                >
                  <div class="item-image-wrapper">
                    <img
                      :src="item.product_image || getProductImage(item.product)"
                      :alt="item.product_name"
                      @error="handleImageError"
                    />
                    <button
                      v-if="
                        item.model_3d_url || item.product?.models?.length > 0
                      "
                      @click="show3DModel(item)"
                      class="btn-3d-mini"
                      title="View 3D Model"
                    >
                      🎲
                    </button>
                  </div>
                  <div class="item-info">
                    <h5>{{ item.product_name }}</h5>
                    <p class="item-meta">
                      Qty: {{ item.quantity }} × ₱{{
                        formatPrice(item.unit_price || item.price)
                      }}
                    </p>
                  </div>
                  <div class="item-total">
                    ₱{{
                      formatPrice(
                        (item.unit_price || item.price) * item.quantity,
                      )
                    }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Customer Notes -->
            <div
              v-if="selectedOrderDetails.customer_notes"
              class="details-section"
            >
              <h4 class="section-title">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                >
                  <path
                    fill="currentColor"
                    d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"
                  />
                </svg>
                Customer Notes
              </h4>
              <div class="notes-box">
                <p>{{ selectedOrderDetails.customer_notes }}</p>
              </div>
            </div>

            <!-- Pricing Summary -->
            <div class="details-section">
              <h4 class="section-title">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                >
                  <path
                    fill="currentColor"
                    d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"
                  />
                </svg>
                Pricing
              </h4>
              <div class="pricing-summary">
                <div class="pricing-row">
                  <span>Subtotal:</span>
                  <span>₱{{ formatPrice(selectedOrderDetails.subtotal) }}</span>
                </div>
                <div class="pricing-row">
                  <span>Delivery Fee:</span>
                  <span
                    >₱{{ formatPrice(selectedOrderDetails.delivery_fee) }}</span
                  >
                </div>
                <div class="pricing-row total-row">
                  <span>Total Amount:</span>
                  <span
                    >₱{{ formatPrice(selectedOrderDetails.total_amount) }}</span
                  >
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button @click="closeOrderDetailsModal" class="btn-secondary">
            Close
          </button>
          <button
            @click="
              updateOrderStatus(
                selectedOrderDetails.id,
                selectedOrderDetails.status,
              )
            "
            class="btn-primary"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"
              />
            </svg>
            Update Status
          </button>
        </div>
      </div>
    </div>

    <!-- Enhanced 3D Model Viewer Modal -->
    <div v-if="show3DModal" class="modal-overlay-3d" @click="close3DModal">
      <div class="modal-content-3d" @click.stop>
        <div class="modal-header-3d">
          <div class="model-info">
            <h3>🎲 3D Model Preview</h3>
            <span class="model-name">{{
              current3DModel?.productName || "Flower Model"
            }}</span>
          </div>
          <button @click="close3DModal" class="btn-close-3d">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"
              />
            </svg>
          </button>
        </div>
        <div class="modal-body-3d">
          <ThreeDModelViewer
            v-if="current3DModel"
            :modelUrl="current3DModel.url"
            :modelType="current3DModel.type"
            :backgroundColor="'#1a1a2e'"
          />
        </div>
        <div class="modal-footer-3d">
          <div class="model-controls-info">
            <div class="control-tip">
              <span class="icon">🖱️</span>
              <span>Drag to rotate</span>
            </div>
            <div class="control-tip">
              <span class="icon">🔍</span>
              <span>Scroll to zoom</span>
            </div>
            <div class="control-tip">
              <span class="icon">⚡</span>
              <span>Double-click to reset</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 3D Model Viewer Modal -->
    <div v-if="show3DModal" class="modal-overlay" @click="close3DModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>3D Model Preview</h3>
          <button @click="close3DModal" class="btn-close">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"
              />
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <ThreeDModelViewer
            v-if="current3DModel"
            :modelUrl="current3DModel.url"
            :modelType="current3DModel.type"
            :backgroundColor="'#f5f7fa'"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from "vue";
import { useRouter } from "vue-router";
import vendorHeader from "../../layouts/vendorHeader.vue";

import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";

import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";
import ThreeDModelViewer from "../../layouts/3D/3DModelViewer.vue";
import api from "../../plugins/axios.js";
import { toast } from "vue3-toastify";

const router = useRouter();

// State
const orders = ref([]);
const isLoading = ref(false);
const loadingMessage = ref("");
const searchQuery = ref("");
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
});

// View State
const currentView = ref("tabs"); // 'tabs' or 'calendar'
const activeStatusTab = ref("pending");

// Status List
const statusList = ref([
  { value: "pending", label: "Pending" },
  { value: "processing", label: "Processing" },
  { value: "delivered", label: "Delivered" },
  { value: "completed", label: "Completed" },
  { value: "refunded", label: "Refunded" },
  { value: "cancelled", label: "Cancelled" },
]);

// Calendar state
const calendarDate = ref(new Date());
const calendarData = ref({});
const selectedCalendarDate = ref(null);

// 3D Model state
const show3DModal = ref(false);
const current3DModel = ref(null);

// Order Details Modal
const showOrderDetailsModal = ref(false);
const selectedOrderDetails = ref(null);
const loadingOrderDetails = ref(false);

// Employee assignment
const employees = ref([
  { id: 1, name: "John Doe" },
  { id: 2, name: "Jane Smith" },
  { id: 3, name: "Mike Johnson" },
  { id: 4, name: "Sarah Williams" },
  { id: 5, name: "Robert Brown" },
]);

// Notifications
const notifications = ref([]);

// Computed - Notifications
const generateNotifications = () => {
  const newNotifications = [];

  // Check for low stock items (simulated - in real app, check inventory)
  const lowStockCount = Math.floor(Math.random() * 3); // Simulate 0-2 low stock items
  if (lowStockCount > 0) {
    newNotifications.push({
      type: "warning",
      title: "Low Stock Alert",
      message: `${lowStockCount} product${lowStockCount > 1 ? "s are" : " is"} running low on stock. Restock soon to avoid order delays.`,
    });
  }

  // Check for urgent orders
  const urgentOrders = orders.value.filter((order) => {
    if (!order.reservation_date) return false;
    const reservationDate = new Date(order.reservation_date);
    const today = new Date();
    const diffTime = reservationDate - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays <= 2 && diffDays >= 0 && order.status === "pending";
  });

  if (urgentOrders.length > 0) {
    newNotifications.push({
      type: "urgent",
      title: "Urgent Orders",
      message: `${urgentOrders.length} order${urgentOrders.length > 1 ? "s" : ""} need${urgentOrders.length === 1 ? "s" : ""} attention. Reservation date${urgentOrders.length > 1 ? "s are" : " is"} within 2 days.`,
    });
  }

  // Check for pending orders that need processing
  const pendingCount = orders.value.filter(
    (o) => o.status === "pending",
  ).length;
  if (pendingCount > 5) {
    newNotifications.push({
      type: "info",
      title: "Pending Orders",
      message: `You have ${pendingCount} pending orders waiting to be processed.`,
    });
  }

  notifications.value = newNotifications;
};

// Computed - Filtered Orders by Tab
const filteredOrdersByTab = computed(() => {
  let filtered = orders.value;

  // Filter by active status tab
  filtered = filtered.filter((order) => order.status === activeStatusTab.value);

  // Apply search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(
      (order) =>
        order.order_number?.toLowerCase().includes(query) ||
        (order.customer?.name || order.user?.name)
          ?.toLowerCase()
          .includes(query) ||
        (order.customer?.email || order.user?.email)
          ?.toLowerCase()
          .includes(query),
    );
  }

  return filtered;
});

// Computed - Filtered Orders by Date (Calendar View)
const filteredOrdersByDate = computed(() => {
  if (!selectedCalendarDate.value) return [];
  return getOrdersForDate(selectedCalendarDate.value);
});

// Computed - Order Count by Status
const getOrderCountByStatus = (status) => {
  return orders.value.filter((order) => order.status === status).length;
};

// Calendar computed
const currentMonth = computed(() => calendarDate.value.getMonth());
const currentYear = computed(() => calendarDate.value.getFullYear());
const currentMonthName = computed(() =>
  calendarDate.value.toLocaleString("default", { month: "long" }),
);

const calendarDays = computed(() => {
  const year = currentYear.value;
  const month = currentMonth.value;
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);

  const days = [];
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  // Add previous month days
  const firstDayOfWeek = firstDay.getDay();
  for (let i = firstDayOfWeek - 1; i >= 0; i--) {
    const date = new Date(year, month, -i);
    days.push({
      day: date.getDate(),
      dateString: formatDateString(date),
      isCurrentMonth: false,
      orderCount: 0,
      isToday: false,
      isSelected: false,
    });
  }

  // Add current month days
  for (let day = 1; day <= lastDay.getDate(); day++) {
    const date = new Date(year, month, day);
    const dateString = formatDateString(date);
    const orderCount = calendarData.value[dateString] || 0;
    const isToday = date.getTime() === today.getTime();
    const isSelected = selectedCalendarDate.value === dateString;

    // Debug: Log days with orders
    if (orderCount > 0) {
      console.log(`Calendar day ${dateString}: ${orderCount} orders`);
      const dateOrders = getOrdersForDate(dateString);
      console.log(
        `Orders for ${dateString}:`,
        dateOrders.map((o) => o.order_number),
      );
    }

    days.push({
      day,
      dateString,
      isCurrentMonth: true,
      orderCount,
      isToday,
      isSelected,
    });
  }

  // Add next month days to fill calendar
  const remaining = 42 - days.length;
  for (let day = 1; day <= remaining; day++) {
    const date = new Date(year, month + 1, day);
    days.push({
      day,
      dateString: formatDateString(date),
      isCurrentMonth: false,
      orderCount: 0,
      isToday: false,
      isSelected: false,
    });
  }

  return days;
});

function formatDateString(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

function formatPaymentStatus(status) {
  const statusMap = {
    paid: "✓ Paid",
    unpaid: "⏳ Unpaid",
    refunded: "↩️ Refunded",
    failed: "❌ Failed",
  };
  return statusMap[status] || status;
}

function formatCalendarDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

function getOrdersForDate(dateString) {
  return orders.value.filter((order) => {
    if (!order.reservation_date) {
      console.log(`Order ${order.id} has no reservation_date`);
      return false;
    }

    console.log(`Checking order ${order.id}:`, {
      reservation_date: order.reservation_date,
      dateString: dateString,
    });

    try {
      // Handle different date formats
      let orderDateStr = order.reservation_date;

      // If it's already in YYYY-MM-DD format
      if (/^\d{4}-\d{2}-\d{2}$/.test(orderDateStr)) {
        return orderDateStr === dateString;
      }

      // Try parsing as Date
      const orderDate = new Date(orderDateStr);
      if (isNaN(orderDate.getTime())) {
        console.log(`Invalid date for order ${order.id}:`, orderDateStr);
        return false;
      }

      const orderDateFormatted = `${orderDate.getFullYear()}-${String(orderDate.getMonth() + 1).padStart(2, "0")}-${String(orderDate.getDate()).padStart(2, "0")}`;

      console.log(
        `Formatted order date: ${orderDateFormatted}, Comparing to: ${dateString}`,
      );

      return orderDateFormatted === dateString;
    } catch (error) {
      console.error("Error comparing dates:", error);
      return false;
    }
  });
}

function dismissNotification(index) {
  notifications.value.splice(index, 1);
}

async function loadCalendarOrders() {
  try {
    const startDate = formatDateString(
      new Date(currentYear.value, currentMonth.value, 1),
    );
    const endDate = formatDateString(
      new Date(currentYear.value, currentMonth.value + 1, 0),
    );

    console.log("Loading calendar data:", { startDate, endDate });

    const response = await api.get("/vendor/orders/calendar-data", {
      params: { start_date: startDate, end_date: endDate },
    });

    console.log("Calendar API response:", response.data);

    if (response.data.success) {
      calendarData.value = response.data.data.reduce((acc, item) => {
        acc[item.date] = item.count;
        console.log(`Date ${item.date}: ${item.count} orders`);
        return acc;
      }, {});

      // Debug: Show what dates have orders
      console.log("Calendar data loaded:", calendarData.value);
    } else {
      console.error("Failed to load calendar data:", response.data);
    }
  } catch (error) {
    console.error("Error loading calendar:", error);
  }
}

function selectCalendarDate(day) {
  if (!day.isCurrentMonth) return;

  selectedCalendarDate.value = day.dateString;

  const dateOrders = getOrdersForDate(day.dateString);

  console.log(`Selected date: ${day.dateString}`);
  console.log(`Found ${dateOrders.length} orders for this date:`);
  dateOrders.forEach((order) => {
    console.log(`- Order #${order.order_number}: ${order.reservation_date}`);
  });

  if (dateOrders.length > 0) {
    nextTick(() => {
      const element = document.querySelector(
        `[data-order-date="${day.dateString}"]`,
      );
      if (element) {
        console.log("Found element to scroll to:", element.id);
        element.scrollIntoView({ behavior: "smooth", block: "start" });
        element.style.animation = "highlight 1s ease";
        setTimeout(() => {
          element.style.animation = "";
        }, 1000);
      } else {
        console.log("No element found with data-order-date:", day.dateString);
        // Debug: List all elements with data-order-date
        const allElements = document.querySelectorAll("[data-order-date]");
        console.log(
          "All elements with data-order-date:",
          Array.from(allElements).map((el) => ({
            id: el.id,
            date: el.getAttribute("data-order-date"),
          })),
        );
      }
    });
  } else {
    toast.info(`No orders for ${formatCalendarDate(day.dateString)}`);
  }
}

function previousMonth() {
  const d = new Date(calendarDate.value);
  d.setMonth(d.getMonth() - 1);
  calendarDate.value = d;
  loadCalendarOrders();
}

function nextMonth() {
  const d = new Date(calendarDate.value);
  d.setMonth(d.getMonth() + 1);
  calendarDate.value = d;
  loadCalendarOrders();
}

function goToToday() {
  calendarDate.value = new Date();
  selectedCalendarDate.value = formatDateString(new Date());
  loadCalendarOrders();
}

function assignEmployee(orderId, employeeId) {
  const order = orders.value.find((o) => o.id === orderId);
  if (order) {
    order.assigned_employee_id = employeeId ? parseInt(employeeId) : null;
    const employee = employees.value.find((e) => e.id === parseInt(employeeId));
    if (employee) {
      toast.success(`Order assigned to ${employee.name}`);
    } else {
      toast.info("Employee assignment cleared");
    }
  }
}

function show3DModel(item) {
  console.log("=== 3D MODEL DEBUG ===");
  console.log("Full item:", item);

  let modelUrl = null;
  let modelType = "glb";
  let productName = item.product_name || item.product?.product_name || "Flower";

  // Priority 1: Check item's direct model_3d_url
  if (item.model_3d_url) {
    modelUrl = item.model_3d_url;
    console.log("✓ Found model_3d_url on item:", modelUrl);
  }
  // Priority 2: Check product's models array
  else if (item.product?.models && item.product.models.length > 0) {
    const model = item.product.models[0];
    modelUrl = model.model_url;
    modelType = model.model_type || "glb";
    console.log("✓ Found model in product.models:", {
      url: modelUrl,
      type: modelType,
    });
  }

  console.log("Final modelUrl:", modelUrl);
  console.log("Final modelType:", modelType);

  if (modelUrl) {
    try {
      const url = new URL(modelUrl, window.location.origin);
      console.log("✓ Valid URL constructed:", url.href);

      current3DModel.value = {
        url: url.href,
        type: modelType,
        productName: productName,
      };
      show3DModal.value = true;

      console.log("✓ 3D Modal opened with:", current3DModel.value);
    } catch (error) {
      console.error("❌ Invalid URL:", modelUrl, error);
      toast.error(`Invalid 3D model URL: ${modelUrl}`);
    }
  } else {
    console.error("❌ No 3D model URL found");
    toast.error("No 3D model available for this product");
  }

  console.log("=== END DEBUG ===");
}

// View Order Details Function
async function viewOrderDetails(orderId) {
  try {
    loadingOrderDetails.value = true;
    showOrderDetailsModal.value = true;

    const response = await api.get(`/vendor/orders/${orderId}`);

    if (response.data.success) {
      selectedOrderDetails.value = response.data.data;
    } else {
      toast.error("Failed to load order details");
      closeOrderDetailsModal();
    }
  } catch (error) {
    console.error("Error loading order details:", error);
    toast.error("Failed to load order details");
    closeOrderDetailsModal();
  } finally {
    loadingOrderDetails.value = false;
  }
}

function close3DModal() {
  show3DModal.value = false;
  current3DModel.value = null;
}

function closeOrderDetailsModal() {
  showOrderDetailsModal.value = false;
  selectedOrderDetails.value = null;
}

async function loadOrders(page = 1) {
  try {
    isLoading.value = true;
    loadingMessage.value = "Loading orders...";

    const response = await api.get("/vendor/orders", {
      params: {
        page,
        search: searchQuery.value,
      },
    });

    if (response.data.success) {
      orders.value = response.data.data.orders;

      // Debug: Check reservation dates
      console.log("=== ORDER RESERVATION DATE DEBUG ===");
      orders.value.forEach((order, index) => {
        console.log(`Order ${index + 1}:`, {
          id: order.id,
          order_number: order.order_number,
          reservation_date: order.reservation_date,
          type: typeof order.reservation_date,
          is_null: order.reservation_date === null,
          is_undefined: order.reservation_date === undefined,
          formatted_date: formatDate(order.reservation_date),
        });
      });
      console.log("=== END DEBUG ===");

      pagination.value = response.data.data.pagination;

      // Generate notifications after loading orders
      generateNotifications();

      // Load calendar data after orders are loaded
      await loadCalendarOrders();
    } else {
      toast.error("Failed to load orders");
    }
  } catch (error) {
    console.error("Error loading orders:", error);
    toast.error("Failed to load orders");
  } finally {
    isLoading.value = false;
  }
}

function handleSearch() {
  // Search is handled by computed properties in real-time
  // This function can be used for debouncing if needed
}

function changePage(page) {
  if (page >= 1 && page <= pagination.value.last_page) {
    loadOrders(page);
  }
}

function formatDate(dateString) {
  if (!dateString) {
    console.warn("No date string provided to formatDate");
    return "Date not set";
  }

  try {
    let date;

    // If it's already a Date object
    if (dateString instanceof Date) {
      date = dateString;
    }
    // If it's a string
    else if (typeof dateString === "string") {
      console.log("Formatting date string:", dateString);

      // Try different parsing strategies
      // 1. Try as-is (might be ISO format)
      date = new Date(dateString);

      // 2. If that fails and it's YYYY-MM-DD format
      if (isNaN(date.getTime()) && /^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
        console.log("Detected YYYY-MM-DD format, adding time");
        date = new Date(dateString + "T00:00:00");
      }

      // 3. If it's YYYY-MM-DD HH:MM:SS format
      if (
        isNaN(date.getTime()) &&
        /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(dateString)
      ) {
        console.log("Detected MySQL datetime format");
        date = new Date(dateString.replace(" ", "T"));
      }
    } else {
      console.warn("Unknown date format:", typeof dateString, dateString);
      return "Invalid date format";
    }

    // Check if date is valid
    if (isNaN(date.getTime())) {
      console.warn("Invalid date after parsing:", dateString);
      return "Invalid date";
    }

    // Format nicely
    return date.toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch (error) {
    console.error("Error formatting date:", error, "Date string:", dateString);
    return "Date error";
  }
}

function formatStatus(status) {
  const statusMap = {
    pending: "Pending",
    processing: "Processing",
    delivered: "Delivered",
    completed: "Completed",
    refunded: "Refunded",
    cancelled: "Cancelled",
  };
  return statusMap[status] || status;
}

function formatPrice(price) {
  return parseFloat(price || 0).toFixed(2);
}

function getProductImage(product) {
  if (!product) return null;
  if (product.primary_image?.image_url) return product.primary_image.image_url;
  if (product.images?.length > 0) {
    const primaryImage = product.images.find((img) => img.is_primary);
    if (primaryImage?.image_url) return primaryImage.image_url;
    return product.images[0].image_url;
  }
  return null;
}

function handleImageError(event) {
  // Use a local fallback or data URL instead of external site
  event.target.src =
    "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCIgaGVpZ2h0PSIxMDAiIGZpbGw9IiNFNUU1RTUiLz48cGF0aCBkPSJNNjUgMzVINTVWNTVIMzVWNjVINTVWODVINjVWNjVIODVWNTVINjVWMzVaIiBmaWxsPSIjQ0NDIi8+PC9zdmc+";
}

function updateOrderStatus(orderId, currentStatus) {
  toast.info("Status update feature - implement modal here");
}

onMounted(async () => {
  await loadOrders();
  await loadCalendarOrders();
});
</script>

<style scoped>
* {
  font-family: "Poppins", sans-serif;
  box-sizing: border-box;
}

/* ==================== PAGE LAYOUT ==================== */
.all-orders-page {
  margin-left: 260px;
  min-height: 100vh;
  background: #f5f7fa;
}

.orders-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
}

/* ==================== HEADER SECTION ==================== */
.orders-header {
  margin-bottom: 24px;
}

.orders-header h1 {
  font-size: 32px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 20px 0;
}

.header-actions {
  display: flex;
  gap: 16px;
  align-items: center;
  flex-wrap: wrap;
}

.search-box {
  flex: 1;
  min-width: 300px;
  max-width: 400px;
  position: relative;
  display: flex;
  align-items: center;
}

.search-box svg {
  position: absolute;
  left: 12px;
  color: #718096;
}

.search-box input {
  width: 100%;
  padding: 12px 12px 12px 44px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  background: white;
}

.search-box input:focus {
  outline: none;
  border-color: #48bb78;
}

/* View Toggle */
.view-toggle {
  display: flex;
  gap: 0;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
}

.toggle-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border: none;
  background: white;
  color: #718096;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  border-right: 1px solid #e2e8f0;
}

.toggle-btn:last-child {
  border-right: none;
}

.toggle-btn:hover {
  background: #f7fafc;
}

.toggle-btn.active {
  background: #48bb78;
  color: white;
}

.toggle-btn svg {
  width: 18px;
  height: 18px;
}

/* ==================== NOTIFICATIONS SECTION ==================== */
.notifications-section {
  background: white;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.notifications-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.notifications-header svg {
  color: #48bb78;
}

.notifications-header h3 {
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
  flex: 1;
}

.notification-count {
  background: #48bb78;
  color: white;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
}

.notifications-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  border-radius: 8px;
  border-left: 4px solid;
  transition: all 0.3s;
}

.notification-warning {
  background: #fef3c7;
  border-left-color: #fbbf24;
}

.notification-urgent {
  background: #fee2e2;
  border-left-color: #dc2626;
}

.notification-info {
  background: #dbeafe;
  border-left-color: #3b82f6;
}

.notification-icon {
  flex-shrink: 0;
}

.notification-warning .notification-icon svg {
  color: #d97706;
}

.notification-urgent .notification-icon svg {
  color: #dc2626;
}

.notification-info .notification-icon svg {
  color: #3b82f6;
}

.notification-content {
  flex: 1;
}

.notification-title {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 4px 0;
}

.notification-message {
  font-size: 13px;
  color: #4b5563;
  margin: 0;
  line-height: 1.5;
}

.btn-dismiss {
  width: 28px;
  height: 28px;
  border: none;
  background: rgba(0, 0, 0, 0.05);
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
  flex-shrink: 0;
}

.btn-dismiss:hover {
  background: rgba(0, 0, 0, 0.1);
}

.btn-dismiss svg {
  color: #718096;
}

/* ==================== TABS VIEW ==================== */
.tabs-view {
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

.status-tabs {
  display: flex;
  gap: 8px;
  background: white;
  padding: 12px;
  border-radius: 12px;
  margin-bottom: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  overflow-x: auto;
  scrollbar-width: thin;
}

.status-tab {
  flex: 1;
  min-width: 120px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 14px 20px;
  border: 2px solid #e2e8f0;
  background: white;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s;
}

.status-tab:hover {
  border-color: #48bb78;
  background: #f0fdf4;
}

.status-tab.active {
  border-color: #48bb78;
  background: #48bb78;
}

.tab-label {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
}

.status-tab.active .tab-label {
  color: white;
}

.tab-count {
  font-size: 20px;
  font-weight: 700;
  color: #48bb78;
}

.status-tab.active .tab-count {
  color: white;
}

/* ==================== CALENDAR VIEW ==================== */
.calendar-view {
  animation: fadeIn 0.3s ease;
}

.vendor-calendar-section {
  background: white;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 32px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.calendar-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.calendar-section-header h3 {
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.btn-today {
  padding: 8px 16px;
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

.calendar-controls-compact {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.btn-nav-small {
  width: 32px;
  height: 32px;
  border: 1px solid #e2e8f0;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.btn-nav-small:hover {
  background: #f7fafc;
  border-color: #48bb78;
}

.month-year-small {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
}

.vendor-calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  max-width: 500px;
  margin: 0 auto;
}

.day-header-tiny {
  text-align: center;
  font-size: 11px;
  font-weight: 600;
  color: #718096;
  padding: 8px 0;
}

.vendor-calendar-day {
  aspect-ratio: 1;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 4px;
  cursor: pointer;
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 50px;
  background: white;
  transition: all 0.3s;
}

.day-num-tiny {
  font-size: 13px;
  font-weight: 500;
  color: #2d3748;
}

.order-count-badge {
  position: absolute;
  top: 4px;
  right: 4px;
  background: #48bb78;
  color: white;
  font-size: 9px;
  padding: 2px 5px;
  border-radius: 10px;
  font-weight: 700;
  min-width: 18px;
  text-align: center;
}

.vendor-calendar-day.has-orders {
  background: #f0fdf4;
  border-color: #48bb78;
  border-width: 2px;
}

.vendor-calendar-day.is-today {
  background: #e0e7ff;
  border-color: #6366f1;
  border-width: 2px;
}

.vendor-calendar-day.is-selected {
  border-width: 2px;
  border-color: #2d3748;
  background: #fef3c7;
  transform: scale(1.05);
}

.vendor-calendar-day.other-month {
  opacity: 0.3;
  cursor: default;
}

.vendor-calendar-day:not(.other-month):hover {
  background: #f7fafc;
  transform: scale(1.05);
}

.calendar-selection-info {
  margin-top: 16px;
  padding: 12px;
  background: #f0fdf4;
  border: 1px solid #48bb78;
  border-radius: 8px;
  text-align: center;
  font-size: 14px;
  color: #2d3748;
  font-weight: 500;
}

/* ==================== ORDERS LIST ==================== */
.orders-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.empty-state {
  background: white;
  border-radius: 12px;
  padding: 80px 40px;
  text-align: center;
}

.empty-icon {
  font-size: 80px;
  margin-bottom: 20px;
  opacity: 0.5;
}

.empty-state h3 {
  font-size: 24px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 8px 0;
}

.empty-state p {
  font-size: 14px;
  color: #718096;
  margin: 0;
}

.order-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  transition: all 0.3s;
}

.order-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

@keyframes highlight {
  0%,
  100% {
    background: white;
  }
  50% {
    background: #fef3c7;
  }
}

.order-card-header {
  padding: 20px;
  background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.order-info-left h3 {
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 8px 0;
}

.order-date {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #718096;
}

.order-date svg {
  color: #48bb78;
}

.order-info-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.order-status {
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

.status-delivered {
  background: #e0e7ff;
  color: #4338ca;
}

.status-completed {
  background: #d1fae5;
  color: #065f46;
}

.status-refunded {
  background: #fce7f3;
  color: #9f1239;
}

.status-cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.order-total {
  font-size: 20px;
  font-weight: 700;
  color: #48bb78;
}

.order-card-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.info-section-compact h4 {
  font-size: 15px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 12px 0;
  padding-bottom: 8px;
  border-bottom: 2px solid #e2e8f0;
}

.info-grid-compact {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.info-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  background: #f9fafb;
  border-radius: 6px;
}

.info-icon {
  font-size: 20px;
  flex-shrink: 0;
}

.info-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.info-label {
  font-size: 11px;
  color: #718096;
  font-weight: 500;
  text-transform: uppercase;
}

.info-value {
  font-size: 13px;
  color: #2d3748;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.order-items-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}

.order-item-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.item-image-container {
  position: relative;
  width: 100%;
  aspect-ratio: 1;
  border-radius: 6px;
  overflow: hidden;
  background: white;
}

.item-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.btn-3d-viewer {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 32px;
  height: 32px;
  background: rgba(72, 187, 120, 0.9);
  color: white;
  border: none;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.btn-3d-viewer:hover {
  background: #38a169;
  transform: scale(1.1);
}

.item-details-compact h5 {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.item-quantity {
  font-size: 12px;
  color: #718096;
  margin: 0;
}

.item-price {
  font-size: 13px;
  color: #48bb78;
  font-weight: 600;
  margin: 0;
}

.employee-assignment {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f9fafb;
  border-radius: 8px;
}

.employee-assignment svg {
  color: #48bb78;
  flex-shrink: 0;
}

.employee-select {
  flex: 1;
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  background: white;
}

.employee-select:focus {
  outline: none;
  border-color: #48bb78;
}

.customer-notes {
  display: flex;
  gap: 10px;
  padding: 12px;
  background: #fffbeb;
  border-left: 3px solid #fbbf24;
  border-radius: 6px;
}

.customer-notes svg {
  color: #fbbf24;
  flex-shrink: 0;
  margin-top: 2px;
}

.customer-notes p {
  margin: 0;
  font-size: 13px;
  color: #2d3748;
  line-height: 1.5;
}

.order-card-footer {
  padding: 16px 20px;
  background: #f9fafb;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.order-actions {
  display: flex;
  gap: 8px;
}

.btn-action {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-view {
  background: #dbeafe;
  color: #1e40af;
}

.btn-view:hover {
  background: #bfdbfe;
}

.btn-update {
  background: #fef3c7;
  color: #92400e;
}

.btn-update:hover {
  background: #fde68a;
}

.order-meta {
  display: flex;
  gap: 12px;
  align-items: center;
}

.payment-method,
.payment-status {
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  background: white;
  border: 1px solid #e2e8f0;
}

.payment-paid {
  background: #d1fae5;
  color: #065f46;
  border-color: #6ee7b7;
}

/* ==================== PAGINATION ==================== */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 32px;
}

.pagination-btn {
  padding: 10px 20px;
  background: white;
  color: #2d3748;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.pagination-btn:hover:not(:disabled) {
  background: #f7fafc;
  border-color: #48bb78;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-info {
  font-size: 14px;
  color: #718096;
}

/* ==================== ORDER DETAILS MODAL ==================== */
.order-details-modal {
  max-width: 900px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}

.modal-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.order-status-badge {
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.modal-body-scrollable {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
  max-height: calc(90vh - 180px);
}

.modal-body-scrollable::-webkit-scrollbar {
  width: 8px;
}

.modal-body-scrollable::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.modal-body-scrollable::-webkit-scrollbar-thumb {
  background: #cbd5e0;
  border-radius: 10px;
}

.modal-body-scrollable::-webkit-scrollbar-thumb:hover {
  background: #a0aec0;
}

.order-details-content {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.details-section {
  background: #f9fafb;
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #e2e8f0;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 16px 0;
  padding-bottom: 12px;
  border-bottom: 2px solid #e2e8f0;
}

.section-title svg {
  color: #48bb78;
}

/* Summary Grid */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.summary-item .label {
  font-size: 12px;
  color: #718096;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.summary-item .value {
  font-size: 14px;
  color: #2d3748;
  font-weight: 600;
}

/* Customer Info Grid */
.customer-info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.info-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  background: white;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  transition: all 0.3s;
}

.info-card:hover {
  border-color: #48bb78;
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.1);
  transform: translateY(-2px);
}

.info-card.full-width {
  grid-column: 1 / -1;
}

.info-icon {
  font-size: 24px;
  flex-shrink: 0;
}

.info-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 0;
}

.info-label {
  font-size: 11px;
  color: #718096;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-text {
  font-size: 14px;
  color: #2d3748;
  font-weight: 500;
  word-break: break-word;
}

/* Items List */
.items-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.item-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: white;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  transition: all 0.3s;
}

.item-row:hover {
  border-color: #48bb78;
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.1);
}

.item-image-wrapper {
  position: relative;
  width: 80px;
  height: 80px;
  border-radius: 8px;
  overflow: hidden;
  flex-shrink: 0;
  background: #f7fafc;
}

.item-image-wrapper img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.btn-3d-mini {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 28px;
  height: 28px;
  background: rgba(72, 187, 120, 0.95);
  border: none;
  border-radius: 50%;
  cursor: pointer;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.btn-3d-mini:hover {
  transform: scale(1.15) rotate(360deg);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
}

.item-info {
  flex: 1;
  min-width: 0;
}

.item-info h5 {
  font-size: 15px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 6px 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.item-meta {
  font-size: 13px;
  color: #718096;
  margin: 0;
}

.item-total {
  font-size: 18px;
  font-weight: 700;
  color: #48bb78;
  flex-shrink: 0;
}

/* Notes Box */
.notes-box {
  padding: 16px;
  background: white;
  border-radius: 10px;
  border-left: 4px solid #fbbf24;
}

.notes-box p {
  margin: 0;
  font-size: 14px;
  color: #2d3748;
  line-height: 1.6;
}

/* Pricing Summary */
.pricing-summary {
  background: white;
  border-radius: 10px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pricing-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
  color: #2d3748;
}

.pricing-row.total-row {
  padding-top: 12px;
  border-top: 2px solid #e2e8f0;
  font-size: 18px;
  font-weight: 700;
  color: #48bb78;
}

/* Modal Footer */
.modal-footer {
  padding: 20px 24px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  background: #f9fafb;
}

.btn-secondary,
.btn-primary {
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-secondary {
  background: white;
  color: #2d3748;
  border: 1px solid #e2e8f0;
}

.btn-secondary:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.btn-primary {
  background: #48bb78;
  color: white;
}

.btn-primary:hover {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
}

/* ==================== 3D MODEL MODAL (Legacy) ==================== */
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
  z-index: 1000;
  animation: fadeIn 0.3s ease;
}

.modal-content {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  overflow: hidden;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    transform: translateY(50px);
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
  padding: 20px;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
  font-size: 20px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.btn-close {
  width: 36px;
  height: 36px;
  border: none;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.btn-close:hover {
  background: #fecaca;
  transform: scale(1.1);
}

.modal-body {
  padding: 20px;
  height: 500px;
}

/* ==================== ENHANCED 3D MODEL VIEWER ==================== */
.modal-overlay-3d {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  animation: fadeIn 0.4s ease;
  backdrop-filter: blur(8px);
}

.modal-content-3d {
  background: linear-gradient(135deg, #1e1e2e 0%, #2d2d44 100%);
  border-radius: 20px;
  width: 95%;
  max-width: 1000px;
  max-height: 95vh;
  overflow: hidden;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
  border: 1px solid rgba(72, 187, 120, 0.2);
  animation: slideUpScale 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes slideUpScale {
  from {
    transform: translateY(50px) scale(0.9);
    opacity: 0;
  }
  to {
    transform: translateY(0) scale(1);
    opacity: 1;
  }
}

.modal-header-3d {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 28px;
  background: linear-gradient(
    135deg,
    rgba(72, 187, 120, 0.1) 0%,
    rgba(72, 187, 120, 0.05) 100%
  );
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.model-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.model-info h3 {
  font-size: 22px;
  font-weight: 700;
  color: #ffffff;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 12px;
}

.model-name {
  font-size: 14px;
  color: #48bb78;
  font-weight: 500;
  letter-spacing: 0.5px;
}

.btn-close-3d {
  width: 44px;
  height: 44px;
  border: none;
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border-radius: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.btn-close-3d:hover {
  background: rgba(239, 68, 68, 0.25);
  transform: scale(1.1) rotate(90deg);
  box-shadow: 0 4px 16px rgba(239, 68, 68, 0.3);
}

.modal-body-3d {
  padding: 0;
  height: 600px;
  background: #1a1a2e;
  position: relative;
}

.modal-footer-3d {
  padding: 20px 28px;
  background: linear-gradient(
    135deg,
    rgba(72, 187, 120, 0.1) 0%,
    rgba(72, 187, 120, 0.05) 100%
  );
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.model-controls-info {
  display: flex;
  justify-content: center;
  gap: 32px;
  flex-wrap: wrap;
}

.control-tip {
  display: flex;
  align-items: center;
  gap: 10px;
  color: rgba(255, 255, 255, 0.8);
  font-size: 13px;
  font-weight: 500;
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  transition: all 0.3s;
}

.control-tip:hover {
  background: rgba(72, 187, 120, 0.15);
  border-color: rgba(72, 187, 120, 0.3);
  transform: translateY(-2px);
}

.control-tip .icon {
  font-size: 18px;
}

/* ==================== LOADING STATES ==================== */
.loading-order-details {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  gap: 20px;
}

.loading-spinner-large {
  width: 60px;
  height: 60px;
  border: 4px solid rgba(72, 187, 120, 0.2);
  border-top: 4px solid #48bb78;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

/* ==================== RESPONSIVE DESIGN ==================== */
@media (max-width: 768px) {
  .header-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .search-box {
    max-width: 100%;
  }

  .view-toggle {
    width: 100%;
  }

  .toggle-btn {
    flex: 1;
    justify-content: center;
  }

  .status-tabs {
    overflow-x: auto;
    flex-wrap: nowrap;
  }

  .status-tab {
    min-width: 100px;
  }

  .info-grid-compact {
    grid-template-columns: 1fr;
  }

  .order-items-grid {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  }

  .order-card-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .order-info-right {
    align-items: flex-start;
  }

  .order-card-footer {
    flex-direction: column;
    gap: 12px;
    align-items: stretch;
  }

  .order-actions {
    flex-direction: column;
  }

  .order-meta {
    flex-direction: column;
    align-items: stretch;
  }

  /* Order Details Modal Responsive */
  .summary-grid,
  .customer-info-grid {
    grid-template-columns: 1fr;
  }

  .info-card.full-width {
    grid-column: 1;
  }

  .item-row {
    flex-direction: column;
    align-items: flex-start;
  }

  .item-image-wrapper {
    width: 100%;
    height: 200px;
  }

  .item-total {
    align-self: flex-end;
  }

  .modal-footer {
    flex-direction: column;
  }

  .btn-secondary,
  .btn-primary {
    width: 100%;
    justify-content: center;
  }

  /* 3D Model Modal Responsive */
  .modal-body-3d {
    height: 400px;
  }

  .model-controls-info {
    gap: 12px;
  }

  .control-tip {
    flex: 1;
    min-width: calc(50% - 6px);
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .modal-content-3d {
    width: 100%;
    max-width: 100%;
    border-radius: 0;
    max-height: 100vh;
  }

  .modal-body-3d {
    height: calc(100vh - 200px);
  }
}
</style>
