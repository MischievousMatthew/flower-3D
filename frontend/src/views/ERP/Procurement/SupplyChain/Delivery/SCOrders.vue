<template>
  <div class="sc-orders-page">
    <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />
    <BarcodeModal ref="barcodeModalRef" />

    <!-- ── Header ─────────────────────────────────────────────────────── -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Delivery Orders</h1>
        <p class="page-sub">Real-time delivery tracking across all vendors</p>
      </div>
      <div class="header-right">
        <!-- View toggle — table / calendar / requests -->
        <div class="view-toggle">
          <button
            class="view-btn"
            :class="{ active: currentView === 'table' }"
            @click="currentView = 'table'"
            title="Table view"
          >
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.6"
              width="13"
            >
              <rect x="2" y="2" width="16" height="4" rx="1" />
              <rect x="2" y="8" width="16" height="4" rx="1" />
              <rect x="2" y="14" width="16" height="4" rx="1" />
            </svg>
            Table
          </button>
          <!-- <button
            class="view-btn"
            :class="{ active: currentView === 'calendar' }"
            @click="switchToCalendar"
            title="Calendar view"
          >
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.6"
              width="13"
            >
              <rect x="2" y="3" width="16" height="15" rx="2" />
              <path d="M6 1v4M14 1v4M2 8h16" />
            </svg>
            Calendar
          </button> -->
          <!-- NEW: Requests toggle -->
          <button
            class="view-btn"
            :class="{ active: currentView === 'requests' }"
            @click="switchToRequests"
            title="Return & Refund Requests"
          >
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.6"
              width="13"
            >
              <path d="M3 10a7 7 0 1 0 7-7" stroke-linecap="round" />
              <path
                d="M3 4v6h6"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            Requests
            <span v-if="reqCounts.pending > 0" class="view-btn-badge">{{
              reqCounts.pending
            }}</span>
          </button>
        </div>

        <span class="live-badge"><span class="live-dot"></span> Live</span>

        <button
          class="btn-refresh"
          @click="
            currentView === 'calendar'
              ? fetchCalendarOrders()
              : currentView === 'requests'
                ? fetchRequests()
                : fetchOrders()
          "
          :class="{ spinning: loading || isLoading || reqLoading }"
        >
          <svg
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            width="15"
          >
            <path d="M4 4a8 8 0 0114 4.5M16 16a8 8 0 01-14-4.5" />
            <path d="M18 8.5L18 4 13.5 4M2 11.5L2 16 6.5 16" />
          </svg>
        </button>
      </div>
    </div>

    <!-- ── KPI strip (table/calendar views only) ─────────────────────── -->
    <div class="kpi-strip" v-if="currentView !== 'requests'">
      <div
        v-for="tab in statusTabs"
        :key="tab.value"
        class="kpi-card"
        :class="[tab.color, { active: activeTab === tab.value }]"
        @click="
          activeTab = tab.value;
          fetchOrders();
        "
      >
        <div class="kpi-icon-box" :class="tab.color">
          <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
            <path
              v-if="tab.value === 'all'"
              d="M3 4h14v2H3zm0 5h14v2H3zm0 5h14v2H3z"
            />
            <path
              v-else-if="tab.value === 'pending'"
              d="M10 2a8 8 0 100 16A8 8 0 0010 2zm1 9H9V5h2v6zm0 2H9v2h2v-2z"
            />
            <path
              v-else-if="tab.value === 'to_processed'"
              d="M4 3h12a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V4a1 1 0 011-1zm6 3l-4 4h3v4h2v-4h3l-4-4z"
            />
            <path
              v-else-if="tab.value === 'to_ship'"
              d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm7 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM1 2h2l2.4 9h8.4l1.8-6H6.3L5.7 3H1V2zm5 7h7l-1.1 4H7L6 9z"
            />
            <path
              v-else-if="tab.value === 'to_received'"
              d="M10.707 2.293a1 1 0 00-1.414 0l-7 7A1 1 0 003 11h1v6a1 1 0 001 1h4v-4h2v4h4a1 1 0 001-1v-6h1a1 1 0 00.707-1.707l-7-7z"
            />
            <path
              v-else-if="tab.value === 'completed'"
              d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z"
            />
            <path
              v-else-if="['returned', 'refunded'].includes(tab.value)"
              fill-rule="evenodd"
              d="M7.707 3.293a1 1 0 010 1.414L5.414 7H11a7 7 0 110 14 1 1 0 110-2 5 5 0 100-10H5.414l2.293 2.293a1 1 0 11-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
              clip-rule="evenodd"
            />
          </svg>
        </div>
        <div class="kpi-text">
          <span class="kpi-label">{{ tab.label }}</span>
          <span class="kpi-num">{{
            String(counts[tab.value] ?? 0).padStart(1, "0")
          }}</span>
        </div>
      </div>
    </div>

    <!-- ── Requests KPI strip ─────────────────────────────────────────── -->
    <div class="kpi-strip" v-if="currentView === 'requests'">
      <div
        v-for="rTab in reqTabs"
        :key="rTab.value"
        class="kpi-card"
        :class="[rTab.color, { active: reqActiveFilter === rTab.value }]"
        @click="
          reqActiveFilter = rTab.value;
          fetchRequests();
        "
      >
        <div class="kpi-icon-box" :class="rTab.color">
          <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
            <path
              v-if="rTab.value === 'all'"
              d="M3 4h14v2H3zm0 5h14v2H3zm0 5h14v2H3z"
            />
            <path
              v-else-if="rTab.value === 'pending'"
              d="M10 2a8 8 0 100 16A8 8 0 0010 2zm1 9H9V5h2v6zm0 2H9v2h2v-2z"
            />
            <path
              v-else-if="rTab.value === 'approved'"
              d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z"
            />
            <path
              v-else-if="rTab.value === 'rejected'"
              d="M10 1a9 9 0 100 18A9 9 0 0010 1zm4.3 12.7a1 1 0 01-1.4 0L10 11l-2.9 2.7a1 1 0 01-1.4-1.4L8.6 10 5.7 7.3a1 1 0 011.4-1.4L10 9l2.9-2.7a1 1 0 011.4 1.4L11.4 10l2.9 2.7a1 1 0 010 1.4z"
            />
            <path
              v-else-if="rTab.value === 'return'"
              fill-rule="evenodd"
              d="M7.707 3.293a1 1 0 010 1.414L5.414 7H11a7 7 0 110 14 1 1 0 110-2 5 5 0 100-10H5.414l2.293 2.293a1 1 0 11-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
              clip-rule="evenodd"
            />
            <path
              v-else-if="rTab.value === 'refund'"
              d="M10 2a8 8 0 100 16A8 8 0 0010 2zm1 11H9v-2h2v2zm0-4H9V5h2v4z"
            />
          </svg>
        </div>
        <div class="kpi-text">
          <span class="kpi-label">{{ rTab.label }}</span>
          <span class="kpi-num">{{ reqCounts[rTab.value] ?? 0 }}</span>
        </div>
      </div>
    </div>

    <!-- ── Toolbar ────────────────────────────────────────────────────── -->
    <div class="toolbar">
      <div class="search-box">
        <svg
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          width="14"
        >
          <circle cx="9" cy="9" r="6" />
          <path d="m14 14 3 3" />
        </svg>
        <input
          :value="currentView === 'requests' ? reqSearch : search"
          :placeholder="
            currentView === 'requests'
              ? 'Search order or customer…'
              : 'Search delivery ID or order…'
          "
          @input="
            (e) => {
              if (currentView === 'requests') {
                reqSearch = e.target.value;
                debouncedFetchRequests();
              } else {
                search = e.target.value;
                debouncedFetch();
              }
            }
          "
        />
      </div>
      <!-- Request type filter (requests view only) -->
      <div v-if="currentView === 'requests'" class="req-type-filter">
        <button
          v-for="t in [
            { label: 'All Types', value: '' },
            { label: 'Returns', value: 'return' },
            { label: 'Refunds', value: 'refund' },
          ]"
          :key="t.value"
          class="req-type-btn"
          :class="{ active: reqTypeFilter === t.value }"
          @click="
            reqTypeFilter = t.value;
            fetchRequests();
          "
        >
          {{ t.label }}
        </button>
      </div>
      <div
        class="toolbar-note"
        v-if="lastRefreshed && currentView !== 'requests'"
      >
        Updated {{ lastRefreshed }}
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         TABLE VIEW
    ══════════════════════════════════════════════════════════════════ -->
    <div v-if="currentView === 'table'" class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Delivery ID</th>
              <th>Order</th>
              <th>Items</th>
              <th>Status</th>
              <th>Last Scan</th>
              <th>Scanned By</th>
              <th>Updated</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="8" class="loading-row">
                <div class="spinner"></div>
              </td>
            </tr>
            <tr v-else-if="!orders.length">
              <td colspan="8" class="empty-row">No deliveries found</td>
            </tr>
            <tr v-for="d in orders" :key="d.id" class="data-row">
              <td>
                <div class="delivery-cell">
                  <span class="delivery-id-text">{{ d.delivery_id }}</span>
                  <span class="barcode-text">{{ d.barcode }}</span>
                </div>
              </td>
              <td>
                <div class="order-cell">
                  <span class="order-ref">
                    <template v-if="d.order?.order_number">{{
                      d.order.order_number
                    }}</template>
                    <template v-else-if="d.order?.id"
                      >#{{ d.order.id }}</template
                    >
                    <template v-else>—</template>
                  </span>
                  <span class="order-amount"
                    >₱{{ Number(d.order?.total_amount ?? 0).toFixed(2) }}</span
                  >
                </div>
              </td>
              <td>
                <span class="items-count"
                  >{{
                    d.order?.items_count ?? d.order?.items?.length ?? "—"
                  }}
                  items</span
                >
              </td>
              <td>
                <span class="status-badge" :class="d.status">{{
                  d.status_label
                }}</span>
              </td>
              <td class="muted-cell">{{ formatTime(d.last_scanned_at) }}</td>
              <td class="muted-cell">{{ d.last_scanned_by ?? "—" }}</td>
              <td class="muted-cell">{{ formatTime(d.updated_at) }}</td>
              <td>
                <div class="action-cell">
                  <button
                    class="action-btn detail-btn"
                    title="View order details"
                    @click="openDetail(d)"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                      width="14"
                    >
                      <circle cx="10" cy="10" r="8" />
                      <path d="M10 7v3l2 2" />
                    </svg>
                  </button>
                  <button
                    class="action-btn barcode-btn"
                    title="Show barcode"
                    @click="barcodeModalRef.open(d.order?.id, d.delivery_id)"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                      width="14"
                    >
                      <path
                        d="M3 3h2v14H3zM7 3h1v14H7zM10 3h2v14h-2zM14 3h1v14h-1zM16 3h1v14h-1z"
                      />
                    </svg>
                  </button>
                  <button
                    class="action-btn logs-btn"
                    title="View logs"
                    @click="openLogs(d)"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                      width="14"
                    >
                      <path d="M4 6h12M4 10h12M4 14h8" />
                    </svg>
                  </button>
                  <button
                    v-if="
                      !['completed', 'returned', 'refunded'].includes(d.status)
                    "
                    class="action-btn advance-btn"
                    title="Advance status"
                    @click="openAdvance(d)"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                      width="14"
                    >
                      <path d="M5 10h10M11 5l5 5-5 5" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="pagination" v-if="meta.last_page > 1">
        <button
          class="page-btn"
          :disabled="meta.current_page === 1"
          @click="fetchOrders(meta.current_page - 1)"
        >
          ‹
        </button>
        <span class="page-info"
          >{{ meta.current_page }} / {{ meta.last_page }}</span
        >
        <button
          class="page-btn"
          :disabled="meta.current_page === meta.last_page"
          @click="fetchOrders(meta.current_page + 1)"
        >
          ›
        </button>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         CALENDAR VIEW
    ══════════════════════════════════════════════════════════════════ -->
    <div v-if="currentView === 'calendar'" class="calendar-section">
      <div class="cal-header">
        <div class="cal-month-nav">
          <button class="cal-nav-btn" @click="prevMonth">
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              width="14"
            >
              <path d="M13 5l-5 5 5 5" />
            </svg>
          </button>
          <span class="cal-month-label">{{ calMonthName }} {{ calYear }}</span>
          <button class="cal-nav-btn" @click="nextMonth">
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              width="14"
            >
              <path d="M7 5l5 5-5 5" />
            </svg>
          </button>
        </div>
        <div class="cal-legend">
          <span class="leg-item"
            ><span class="leg-dot leg-reservation"></span>Reservation</span
          >
          <span class="leg-item"
            ><span class="leg-dot leg-created"></span>Created</span
          >
          <span class="leg-item"
            ><span class="leg-dot leg-delivered"></span>Delivered</span
          >
        </div>
        <button class="cal-today-btn" @click="goToday">Today</button>
      </div>
      <div class="cal-grid">
        <div v-for="h in DAY_HEADERS" :key="h" class="cal-day-header">
          {{ h }}
        </div>
        <div
          v-for="cell in calendarCells"
          :key="cell.key"
          class="cal-cell"
          :class="{
            'cal-other-month': !cell.isCurrentMonth,
            'cal-today': cell.isToday,
            'cal-has-orders': cell.orders.length > 0,
            'cal-selected': selectedCalDate === cell.dateStr,
          }"
          @click="selectDate(cell)"
        >
          <span class="cal-date-num">{{ cell.day }}</span>
          <div class="cal-order-pills">
            <div
              v-for="ord in cell.orders.slice(0, 3)"
              :key="ord._uid"
              class="cal-pill"
              :class="'cal-pill--' + ord.dateType"
              :title="'Order ' + (ord.order_number || '#' + ord.id)"
            >
              {{ ord.order_number || "#" + ord.id }}
            </div>
            <div v-if="cell.orders.length > 3" class="cal-pill-more">
              +{{ cell.orders.length - 3 }} more
            </div>
          </div>
        </div>
      </div>
      <transition name="cal-panel-fade">
        <div v-if="selectedCalDate" class="cal-panel">
          <div class="cal-panel-header">
            <h3 class="cal-panel-title">
              {{ formatDateLabel(selectedCalDate) }}
            </h3>
            <span class="cal-panel-count"
              >{{ selectedDateOrders.length }} order{{
                selectedDateOrders.length !== 1 ? "s" : ""
              }}</span
            >
          </div>
          <div v-if="!selectedDateOrders.length" class="cal-panel-empty">
            No orders on this date.
          </div>
          <div v-else class="cal-card-list">
            <div
              v-for="ord in selectedDateOrders"
              :key="ord._uid"
              class="cal-order-card"
            >
              <div class="coc-left">
                <div class="coc-id">{{ ord.order_number || "#" + ord.id }}</div>
                <div class="coc-sub">
                  <span>{{ ord.store_name || "—" }}</span
                  ><span class="coc-sep">·</span>
                  <span>{{ paymentLabel(ord.payment_method) }}</span>
                </div>
              </div>
              <div class="coc-mid">
                <div class="coc-addr">{{ ord.delivery_address || "—" }}</div>
                <div class="coc-contact">
                  {{ ord.delivery_contact_name
                  }}<template v-if="ord.delivery_contact_number">
                    · {{ ord.delivery_contact_number }}</template
                  >
                </div>
              </div>
              <div class="coc-right">
                <span class="status-badge" :class="ord.status">{{
                  statusLabel(ord.status)
                }}</span>
                <span class="coc-total"
                  >₱{{ Number(ord.total_amount || 0).toFixed(2) }}</span
                >
                <span class="date-type-pill" :class="'dtp--' + ord.dateType">{{
                  dateTypeLabel(ord.dateType)
                }}</span>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         REQUESTS VIEW  ← NEW
    ══════════════════════════════════════════════════════════════════ -->
    <div v-if="currentView === 'requests'" class="card">
      <!-- Header bar inside card -->
      <div class="req-card-header">
        <div>
          <h2 class="req-card-title">Return & Refund Requests</h2>
          <p class="req-card-sub">Customer requests awaiting review</p>
        </div>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Customer</th>
              <th>Type</th>
              <th>Reason</th>
              <th>Proof</th>
              <th>Status</th>
              <th>Submitted</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <!-- Loading -->
            <tr v-if="reqLoading">
              <td colspan="8" class="loading-row">
                <div class="spinner"></div>
              </td>
            </tr>
            <!-- Empty -->
            <tr v-else-if="!requests.length">
              <td colspan="8" class="empty-row">
                <div class="req-empty">
                  <svg viewBox="0 0 48 48" fill="none" class="req-empty-ico">
                    <circle
                      cx="24"
                      cy="24"
                      r="20"
                      stroke="#e5e7eb"
                      stroke-width="2"
                    />
                    <path
                      d="M24 16v8M24 28v2"
                      stroke="#9ca3af"
                      stroke-width="2.5"
                      stroke-linecap="round"
                    />
                  </svg>
                  <p>No requests found</p>
                </div>
              </td>
            </tr>
            <!-- Rows -->
            <tr v-for="req in requests" :key="req.id" class="data-row req-row">
              <!-- Order -->
              <td>
                <div class="order-cell">
                  <span class="order-ref">{{
                    req.order?.order_number ?? "—"
                  }}</span>
                  <span class="order-amount"
                    >₱{{
                      Number(req.order?.total_amount ?? 0).toFixed(2)
                    }}</span
                  >
                </div>
              </td>
              <!-- Customer -->
              <td>
                <div class="req-customer">
                  <span class="req-customer-name">{{
                    req.customer?.name ?? "—"
                  }}</span>
                  <span class="req-customer-email">{{
                    req.customer?.email ?? ""
                  }}</span>
                </div>
              </td>
              <!-- Type -->
              <td>
                <span
                  class="req-type-badge"
                  :class="'req-type--' + (req.type ?? 'return')"
                >
                  {{
                    req.type === "return"
                      ? "↩ Return"
                      : req.type === "refund"
                        ? "💰 Refund"
                        : "—"
                  }}
                </span>
              </td>
              <!-- Reason -->
              <td>
                <div class="req-reason" :title="req.reason">
                  {{
                    req.reason?.length > 80
                      ? req.reason.slice(0, 80) + "…"
                      : req.reason
                  }}
                </div>
              </td>
              <!-- Proof media -->
              <td>
                <div
                  v-if="req.media_url"
                  class="req-media-thumb"
                  @click="openMediaModal(req)"
                  title="Click to view proof"
                >
                  <video
                    v-if="req.media_type === 'video'"
                    :src="req.media_url"
                    class="req-media-preview"
                    muted
                  ></video>
                  <img
                    v-else
                    :src="req.media_url"
                    :alt="req.type + ' proof'"
                    class="req-media-preview"
                  />
                  <div class="req-media-overlay">
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="white"
                      stroke-width="1.8"
                      width="18"
                    >
                      <path
                        d="M10 4v12M4 10h12"
                        v-if="req.media_type === 'video'"
                      />
                      <template v-else>
                        <circle cx="10" cy="10" r="7" />
                        <path
                          d="M13 10l-4 3V7l4 3z"
                          fill="white"
                          stroke="none"
                        />
                      </template>
                    </svg>
                  </div>
                </div>
                <span v-else class="req-no-proof">No proof</span>
              </td>
              <!-- Status -->
              <td>
                <span
                  class="req-status-badge"
                  :class="'req-status--' + (req.status ?? 'pending')"
                >
                  {{
                    req.status
                      ? req.status.charAt(0).toUpperCase() + req.status.slice(1)
                      : "Pending"
                  }}
                </span>
                <div
                  v-if="req.admin_notes"
                  class="req-admin-note"
                  :title="req.admin_notes"
                >
                  {{
                    req.admin_notes.length > 40
                      ? req.admin_notes.slice(0, 40) + "…"
                      : req.admin_notes
                  }}
                </div>
              </td>
              <!-- Date -->
              <td class="muted-cell">{{ formatTime(req.created_at) }}</td>
              <!-- Actions -->
              <td>
                <div v-if="req.status === 'pending'" class="req-action-cell">
                  <button
                    class="req-action-btn req-approve-btn"
                    :disabled="reqActionLoading === req.id + '_a'"
                    title="Approve"
                    @click="openApproveModal(req)"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      width="13"
                    >
                      <path
                        d="M4 10l4 4L16 6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                    Approve
                  </button>
                  <button
                    class="req-action-btn req-reject-btn"
                    :disabled="reqActionLoading === req.id + '_r'"
                    title="Reject"
                    @click="openRejectModal(req)"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      width="13"
                    >
                      <path d="M5 5l10 10M15 5l-10 10" stroke-linecap="round" />
                    </svg>
                    Reject
                  </button>
                </div>
                <span v-else class="req-resolved-note">
                  {{ req.status === "approved" ? "✓ Approved" : "✕ Rejected" }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination" v-if="reqMeta.last_page > 1">
        <button
          class="page-btn"
          :disabled="reqMeta.current_page === 1"
          @click="fetchRequests(reqMeta.current_page - 1)"
        >
          ‹
        </button>
        <span class="page-info"
          >{{ reqMeta.current_page }} / {{ reqMeta.last_page }}</span
        >
        <button
          class="page-btn"
          :disabled="reqMeta.current_page === reqMeta.last_page"
          @click="fetchRequests(reqMeta.current_page + 1)"
        >
          ›
        </button>
      </div>
    </div>

    <!-- ── Existing modals (unchanged) ────────────────────────────────── -->

    <!-- Order Detail Modal -->
    <teleport to="body">
      <div
        v-if="detailModal.open"
        class="modal-backdrop"
        @click.self="detailModal.open = false"
      >
        <div class="detail-modal">
          <div class="modal-header">
            <div class="dm-header-left">
              <h2>
                {{
                  detailModal.delivery?.order?.order_number ??
                  detailModal.delivery?.delivery_id
                }}
              </h2>
              <span
                class="status-badge"
                :class="detailModal.delivery?.status"
                >{{ detailModal.delivery?.status_label }}</span
              >
            </div>
            <button class="modal-close" @click="detailModal.open = false">
              ✕
            </button>
          </div>
          <div class="dm-body" v-if="detailModal.delivery">
            <div class="dm-meta-grid">
              <div class="dm-meta-item">
                <span class="dm-meta-label">Delivery ID</span
                ><span class="dm-meta-val mono">{{
                  detailModal.delivery.delivery_id
                }}</span>
              </div>
              <div class="dm-meta-item">
                <span class="dm-meta-label">Barcode</span
                ><span class="dm-meta-val mono">{{
                  detailModal.delivery.barcode
                }}</span>
              </div>
              <div class="dm-meta-item">
                <span class="dm-meta-label">Payment</span
                ><span class="dm-meta-val">{{
                  paymentLabel(detailModal.delivery.order?.payment_method)
                }}</span>
              </div>
              <div class="dm-meta-item">
                <span class="dm-meta-label">Payment Status</span
                ><span class="dm-meta-val">{{
                  detailModal.delivery.order?.payment_status ?? "—"
                }}</span>
              </div>
              <div class="dm-meta-item">
                <span class="dm-meta-label">Reservation Date</span
                ><span class="dm-meta-val">{{
                  safeDate(detailModal.delivery.order?.reservation_date) ||
                  safeDate(detailModal.delivery.order?.created_at) ||
                  "—"
                }}</span>
              </div>
              <div class="dm-meta-item">
                <span class="dm-meta-label">Total</span
                ><span class="dm-meta-val bold"
                  >₱{{
                    Number(
                      detailModal.delivery.order?.total_amount ?? 0,
                    ).toFixed(2)
                  }}</span
                >
              </div>
              <div class="dm-meta-item dm-meta-full">
                <span class="dm-meta-label">Delivery Address</span
                ><span class="dm-meta-val">{{
                  detailModal.delivery.order?.delivery_address ?? "—"
                }}</span>
              </div>
              <div class="dm-meta-item">
                <span class="dm-meta-label">Contact Name</span
                ><span class="dm-meta-val">{{
                  detailModal.delivery.order?.delivery_contact_name ?? "—"
                }}</span>
              </div>
              <div class="dm-meta-item">
                <span class="dm-meta-label">Contact Number</span
                ><span class="dm-meta-val">{{
                  detailModal.delivery.order?.delivery_contact_number ?? "—"
                }}</span>
              </div>
              <div
                class="dm-meta-item dm-meta-full"
                v-if="detailModal.delivery.order?.delivery_notes"
              >
                <span class="dm-meta-label">Delivery Notes</span
                ><span class="dm-meta-val">{{
                  detailModal.delivery.order.delivery_notes
                }}</span>
              </div>
              <div
                class="dm-meta-item dm-meta-full"
                v-if="detailModal.delivery.order?.customer_notes"
              >
                <span class="dm-meta-label">Customer Notes</span
                ><span class="dm-meta-val">{{
                  detailModal.delivery.order.customer_notes
                }}</span>
              </div>
            </div>
            <div class="dm-section-title">
              Order Items
              <span class="dm-item-count">{{
                (detailModal.delivery.order?.items ?? []).length
              }}</span>
            </div>
            <div class="dm-items">
              <div
                v-for="item in detailModal.delivery.order?.items ?? []"
                :key="item.id"
                class="dm-item"
              >
                <div class="dm-item-img-wrap">
                  <img
                    v-if="item.product_image"
                    :src="item.product_image"
                    :alt="item.product_name"
                    class="dm-item-img"
                    @error="$event.target.style.display = 'none'"
                  />
                  <div v-else class="dm-item-img-placeholder">
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="#d1d5db"
                      stroke-width="1.2"
                      width="28"
                    >
                      <rect x="2" y="2" width="16" height="16" rx="2" />
                      <circle cx="7" cy="7" r="2" />
                      <path d="M2 14l5-5 3 3 2-2 6 6" />
                    </svg>
                  </div>
                </div>
                <div class="dm-item-info">
                  <div class="dm-item-name">{{ item.product_name }}</div>
                  <div class="dm-item-desc" v-if="item.product_description">
                    {{ item.product_description }}
                  </div>
                  <div class="dm-item-attrs">
                    <span v-if="item.color" class="dm-attr"
                      >Color: {{ item.color }}</span
                    >
                    <span v-if="item.size" class="dm-attr"
                      >Size: {{ item.size }}</span
                    >
                    <span v-if="item.notes" class="dm-attr"
                      >Note: {{ item.notes }}</span
                    >
                  </div>
                  <button
                    v-if="item.model_3d_url"
                    class="dm-3d-toggle"
                    @click="open3dModal(item.model_3d_url, item.product_name)"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                      width="13"
                    >
                      <path d="M10 2l7 4v8l-7 4-7-4V6z" />
                      <path d="M10 2v14M3 6l7 4 7-4" />
                    </svg>
                    View 3D Model
                  </button>
                </div>
                <div class="dm-item-price">
                  <div class="dm-item-qty">× {{ item.quantity }}</div>
                  <div class="dm-item-unit">
                    ₱{{ Number(item.unit_price).toFixed(2) }} each
                  </div>
                  <div class="dm-item-sub">
                    ₱{{ Number(item.subtotal).toFixed(2) }}
                  </div>
                </div>
              </div>
              <div
                v-if="!(detailModal.delivery.order?.items ?? []).length"
                class="dm-no-items"
              >
                No items found.
              </div>
            </div>
            <div class="dm-totals">
              <div class="dm-total-row">
                <span>Subtotal</span
                ><span
                  >₱{{
                    Number(detailModal.delivery.order?.subtotal ?? 0).toFixed(2)
                  }}</span
                >
              </div>
              <div class="dm-total-row">
                <span>Delivery Fee</span
                ><span
                  >₱{{
                    Number(
                      detailModal.delivery.order?.delivery_fee ?? 0,
                    ).toFixed(2)
                  }}</span
                >
              </div>
              <div class="dm-total-row dm-total-grand">
                <span>Total</span
                ><span
                  >₱{{
                    Number(
                      detailModal.delivery.order?.total_amount ?? 0,
                    ).toFixed(2)
                  }}</span
                >
              </div>
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- 3D Model Modal -->
    <teleport to="body">
      <div
        v-if="model3d.open"
        class="modal-backdrop"
        @click.self="model3d.open = false"
      >
        <div class="model3d-modal">
          <div class="modal-header">
            <h2>{{ model3d.name }}</h2>
            <button class="modal-close" @click="model3d.open = false">✕</button>
          </div>
          <div class="model3d-body">
            <iframe
              :src="model3d.url"
              class="model3d-iframe"
              frameborder="0"
            ></iframe>
          </div>
          <div class="model3d-footer">
            <span>🖱 Drag to rotate · Scroll to zoom</span
            ><a :href="model3d.url" download class="dm-3d-download"
              >↓ Download .glb</a
            >
          </div>
        </div>
      </div>
    </teleport>

    <!-- Logs Modal -->
    <teleport to="body">
      <div
        v-if="logsModal.open"
        class="modal-backdrop"
        @click.self="logsModal.open = false"
      >
        <div class="logs-modal">
          <div class="modal-header">
            <h2>Delivery Logs — {{ logsModal.delivery?.delivery_id }}</h2>
            <button class="modal-close" @click="logsModal.open = false">
              ✕
            </button>
          </div>
          <div class="logs-body">
            <div v-if="logsModal.loading" class="logs-loading">
              <div class="spinner"></div>
            </div>
            <div v-else-if="!logsModal.logs.length" class="logs-empty">
              No scan logs yet
            </div>
            <div v-else class="timeline">
              <div
                v-for="log in logsModal.logs"
                :key="log.id"
                class="timeline-item"
              >
                <div class="tl-dot" :class="log.status"></div>
                <div class="tl-content">
                  <div class="tl-status">
                    <span class="status-badge" :class="log.status">{{
                      log.label
                    }}</span>
                  </div>
                  <div class="tl-meta">
                    <span>{{ log.scanned_by }}</span
                    ><span>{{ formatFull(log.scanned_at) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Advance Status Modal -->
    <teleport to="body">
      <div
        v-if="advanceModal.open"
        class="modal-backdrop"
        @click.self="advanceModal.open = false"
      >
        <div class="advance-modal">
          <div class="modal-header">
            <h2>Advance Delivery Status</h2>
            <button class="modal-close" @click="advanceModal.open = false">
              ✕
            </button>
          </div>
          <div class="advance-body">
            <p class="advance-hint">
              Delivery
              <strong>{{ advanceModal.delivery?.delivery_id }}</strong> is
              currently
              <span
                class="status-badge"
                :class="advanceModal.delivery?.status"
                >{{ advanceModal.delivery?.status_label }}</span
              >
            </p>
            <p class="advance-hint">Move to:</p>
            <div class="advance-options">
              <button
                v-for="opt in advanceOptions(advanceModal.delivery)"
                :key="opt.value"
                class="advance-opt-btn"
                :class="opt.value"
                @click="doAdvance(opt.value)"
                :disabled="advancing"
              >
                {{ opt.label }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- ── NEW: Media Proof Modal ───────────────────────────────────────── -->
    <teleport to="body">
      <div
        v-if="mediaModal.open"
        class="modal-backdrop"
        @click.self="mediaModal.open = false"
      >
        <div class="media-modal">
          <div class="modal-header">
            <h2>
              {{ mediaModal.type === "return" ? "Return" : "Refund" }} Proof —
              {{ mediaModal.orderNumber }}
            </h2>
            <button class="modal-close" @click="mediaModal.open = false">
              ✕
            </button>
          </div>
          <div class="media-modal-body">
            <video
              v-if="mediaModal.mediaType === 'video'"
              :src="mediaModal.url"
              controls
              class="media-modal-content"
            ></video>
            <img
              v-else
              :src="mediaModal.url"
              :alt="mediaModal.type + ' proof'"
              class="media-modal-content"
            />
          </div>
          <div class="media-modal-footer">
            <a
              :href="mediaModal.url"
              target="_blank"
              rel="noopener"
              class="dm-3d-download"
              >↓ Open original</a
            >
          </div>
        </div>
      </div>
    </teleport>

    <!-- ── NEW: Approve / Reject Confirmation Modal ────────────────────── -->
    <teleport to="body">
      <div
        v-if="reqConfirmModal.open"
        class="modal-backdrop"
        @click.self="reqConfirmModal.open = false"
      >
        <div class="req-confirm-modal">
          <div class="modal-header">
            <h2>
              {{
                reqConfirmModal.action === "approve"
                  ? "✅ Approve Request"
                  : "❌ Reject Request"
              }}
            </h2>
            <button class="modal-close" @click="reqConfirmModal.open = false">
              ✕
            </button>
          </div>
          <div class="req-confirm-body">
            <p class="req-confirm-info">
              <span
                class="req-type-badge"
                :class="'req-type--' + reqConfirmModal.request?.type"
              >
                {{
                  reqConfirmModal.request?.type === "return"
                    ? "↩ Return"
                    : "💰 Refund"
                }}
              </span>
              <strong>{{
                reqConfirmModal.request?.order?.order_number
              }}</strong>
              · {{ reqConfirmModal.request?.customer?.name }}
            </p>
            <p class="req-confirm-reason">
              {{ reqConfirmModal.request?.reason }}
            </p>
            <div class="req-notes-field">
              <label class="req-notes-lbl"
                >Admin Notes
                <span style="color: #9ca3af; font-weight: 400"
                  >(optional)</span
                ></label
              >
              <textarea
                v-model="reqConfirmModal.notes"
                class="req-notes-textarea"
                rows="3"
                placeholder="Add any notes for the customer…"
              ></textarea>
            </div>
          </div>
          <div class="req-confirm-footer">
            <button
              class="action-btn-ghost"
              @click="reqConfirmModal.open = false"
              :disabled="reqConfirmModal.loading"
            >
              Cancel
            </button>
            <button
              class="req-confirm-btn"
              :class="
                reqConfirmModal.action === 'approve'
                  ? 'req-confirm-btn--approve'
                  : 'req-confirm-btn--reject'
              "
              :disabled="reqConfirmModal.loading"
              @click="doRequestAction"
            >
              <svg
                v-if="reqConfirmModal.loading"
                class="spin-ico"
                viewBox="0 0 24 24"
                fill="none"
              >
                <circle
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-dasharray="32"
                  stroke-linecap="round"
                />
              </svg>
              {{
                reqConfirmModal.loading
                  ? "Processing…"
                  : reqConfirmModal.action === "approve"
                    ? "Confirm Approve"
                    : "Confirm Reject"
              }}
            </button>
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import { deliveryService } from "../../../../../services/deliveryService";
import LoadingOverlay from "../../../../../layouts/components/LoadingOverlay.vue";
import BarcodeModal from "../../../../../layouts/components/BarcodeModal.vue";

// ─── Constants ────────────────────────────────────────────────────────────────
const DAY_HEADERS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

const TRANSITIONS = {
  pending: [{ value: "to_processed", label: "Mark Processed" }],
  to_processed: [{ value: "to_ship", label: "Mark To Ship" }],
  to_ship: [{ value: "to_received", label: "Mark Received" }],
  to_received: [{ value: "completed", label: "Complete" }],
  completed: [
    { value: "returned", label: "Returned" },
    { value: "refunded", label: "Refunded" },
  ],
};

const statusTabs = [
  { value: "all", label: "All", color: "kpi-gray" },
  { value: "pending", label: "Pending", color: "kpi-amber" },
  { value: "to_processed", label: "To Processed", color: "kpi-blue" },
  { value: "to_ship", label: "To Ship", color: "kpi-indigo" },
  { value: "to_received", label: "To Received", color: "kpi-purple" },
  { value: "completed", label: "Completed", color: "kpi-green" },
  { value: "returned", label: "Returned", color: "kpi-red" },
];

// NEW: request filter tabs
const reqTabs = [
  { value: "all", label: "All", color: "kpi-gray" },
  { value: "pending", label: "Pending", color: "kpi-amber" },
  { value: "approved", label: "Approved", color: "kpi-green" },
  { value: "rejected", label: "Rejected", color: "kpi-red" },
  { value: "return", label: "Returns", color: "kpi-indigo" },
  { value: "refund", label: "Refunds", color: "kpi-blue" },
];

// ─── View ─────────────────────────────────────────────────────────────────────
const currentView = ref("table");

// ─── Loading overlay ──────────────────────────────────────────────────────────
const isLoading = ref(false);
const isLoadingMessage = ref("Loading calendar…");

// ─── Table state ──────────────────────────────────────────────────────────────
const orders = ref([]);
const loading = ref(false);
const search = ref("");
const activeTab = ref("all");
const meta = ref({ current_page: 1, last_page: 1 });
const counts = ref({});
const lastRefreshed = ref("");

// ─── Calendar state ───────────────────────────────────────────────────────────
const calendarOrders = ref([]);
const calendarLoaded = ref(false);
const calendarDate = ref(new Date());
const selectedCalDate = ref(null);

// ─── Requests state (NEW) ─────────────────────────────────────────────────────
const requests = ref([]);
const reqLoading = ref(false);
const reqSearch = ref("");
const reqActiveFilter = ref("all"); // maps to status tab
const reqTypeFilter = ref(""); // '' | 'return' | 'refund'
const reqMeta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 });
const reqCounts = ref({
  all: 0,
  pending: 0,
  approved: 0,
  rejected: 0,
  return: 0,
  refund: 0,
});
const reqActionLoading = ref(null);

// Media preview modal (NEW)
const mediaModal = ref({
  open: false,
  url: null,
  mediaType: null,
  type: null,
  orderNumber: "",
});

// Approve / Reject confirmation modal (NEW)
const reqConfirmModal = ref({
  open: false,
  action: "approve",
  request: null,
  notes: "",
  loading: false,
});

// ─── Existing modals ──────────────────────────────────────────────────────────
const logsModal = ref({
  open: false,
  delivery: null,
  logs: [],
  loading: false,
});
const advanceModal = ref({ open: false, delivery: null });
const detailModal = ref({ open: false, delivery: null });
const model3d = ref({ open: false, url: null, name: "" });
const barcodeModalRef = ref(null);
const advancing = ref(false);

// ─── Calendar computed ────────────────────────────────────────────────────────
const calYear = computed(() => calendarDate.value.getFullYear());
const calMonth = computed(() => calendarDate.value.getMonth());
const calMonthName = computed(() =>
  calendarDate.value.toLocaleString("default", { month: "long" }),
);

const calendarCells = computed(() => {
  const year = calYear.value,
    month = calMonth.value;
  const firstDow = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const todayStr = toDateStr(new Date());
  const cells = [];
  for (let i = firstDow - 1; i >= 0; i--)
    cells.push(buildCell(new Date(year, month, -i), false, todayStr));
  for (let d = 1; d <= daysInMonth; d++)
    cells.push(buildCell(new Date(year, month, d), true, todayStr));
  const pad = 42 - cells.length;
  for (let d = 1; d <= pad; d++)
    cells.push(buildCell(new Date(year, month + 1, d), false, todayStr));
  return cells;
});

function buildCell(date, isCurrentMonth, todayStr) {
  const dateStr = toDateStr(date);
  return {
    key: dateStr + (isCurrentMonth ? "" : "_out"),
    day: date.getDate(),
    dateStr,
    isCurrentMonth,
    isToday: dateStr === todayStr,
    orders: ordersOnDate(dateStr),
  };
}

function ordersOnDate(dateStr) {
  const result = [],
    seen = new Set();
  for (const order of calendarOrders.value) {
    if (seen.has(order.id)) continue;
    let dateType = null;
    if (order.reservation_date && dateStr === safeDate(order.reservation_date))
      dateType = "reservation";
    else if (order.delivered_at && dateStr === safeDate(order.delivered_at))
      dateType = "delivered";
    else if (order.created_at && dateStr === safeDate(order.created_at))
      dateType = "created";
    if (dateType) {
      seen.add(order.id);
      result.push({ ...order, dateType, _uid: `${order.id}-${dateType}` });
    }
  }
  return result;
}

const selectedDateOrders = computed(() =>
  selectedCalDate.value ? ordersOnDate(selectedCalDate.value) : [],
);

// ─── Table fetch ──────────────────────────────────────────────────────────────
let pollTimer = null;
let fetchTimer = null;
let reqFetchTimer = null;

function startPolling() {
  pollTimer = setInterval(() => {
    if (currentView.value === "calendar") {
      calendarLoaded.value = false;
      fetchCalendarOrders();
    } else if (currentView.value === "requests") {
      fetchRequests(reqMeta.value.current_page, true);
    } else {
      fetchOrders(meta.value.current_page, true);
    }
  }, 10_000);
}

function debouncedFetch() {
  clearTimeout(fetchTimer);
  fetchTimer = setTimeout(() => fetchOrders(), 400);
}
function debouncedFetchRequests() {
  clearTimeout(reqFetchTimer);
  reqFetchTimer = setTimeout(() => fetchRequests(), 400);
}

async function fetchOrders(page = 1, silent = false) {
  if (!silent) loading.value = true;
  try {
    const params = { page, per_page: 20 };
    if (activeTab.value !== "all") params.status = activeTab.value;
    if (search.value) params.search = search.value;
    const res = await deliveryService.scOrders(params);
    let raw = [];
    if (Array.isArray(res)) raw = res;
    else if (Array.isArray(res?.data?.data)) raw = res.data.data;
    else if (Array.isArray(res?.data)) raw = res.data;
    orders.value = raw.filter(Boolean);
    meta.value = res?.meta ??
      res?.data?.meta ?? { current_page: 1, last_page: 1 };
    const sc = res?.status_counts ?? res?.data?.status_counts ?? {};
    counts.value = {
      all: sc.all ?? 0,
      pending: sc.pending ?? 0,
      to_processed: sc.to_processed ?? 0,
      to_ship: sc.to_ship ?? 0,
      to_received: sc.to_received ?? 0,
      completed: sc.completed ?? 0,
      returned: sc.returned ?? 0,
      refunded: sc.refunded ?? 0,
    };
    lastRefreshed.value = new Date().toLocaleTimeString();
  } catch (err) {
    if (!silent)
      toast.error(err?.message ?? "Failed to load orders.", {
        position: toast.POSITION.TOP_RIGHT,
        autoClose: 6000,
      });
  } finally {
    loading.value = false;
  }
}

// ─── Requests fetch (NEW) ─────────────────────────────────────────────────────
async function fetchRequests(page = 1, silent = false) {
  if (!silent) reqLoading.value = true;
  try {
    const params = { page, per_page: reqMeta.value.per_page ?? 20 };
    if (
      reqActiveFilter.value !== "all" &&
      !["return", "refund"].includes(reqActiveFilter.value)
    )
      params.status = reqActiveFilter.value;
    if (["return", "refund"].includes(reqActiveFilter.value))
      params.type = reqActiveFilter.value;
    if (reqTypeFilter.value) params.type = reqTypeFilter.value;
    if (reqSearch.value.trim()) params.search = reqSearch.value.trim();

    const res = await deliveryService.getOrderRequests(params);

    // Handle both shapes:
    //   intercepted: res = { success, data: [], meta, counts }
    //   raw axios:   res = { data: { success, data: [], meta, counts } }
    const body =
      res?.success !== undefined
        ? res // interceptor returned JSON body
        : res?.data?.success !== undefined
          ? res.data // raw axios response
          : res;

    const items = Array.isArray(body?.data)
      ? body.data
      : Array.isArray(body)
        ? body
        : [];

    requests.value = items.map((r) => ({
      ...r,
      status: r.status ?? "pending",
      type: r.type ?? "return",
    }));

    if (body?.meta) Object.assign(reqMeta.value, body.meta);
    if (body?.counts) Object.assign(reqCounts.value, body.counts);
  } catch (err) {
    console.error("[fetchRequests] error:", err);
    const msg =
      err?.response?.data?.message ??
      err?.message ??
      "Failed to load requests.";
    if (!silent)
      toast.error(msg, { position: toast.POSITION.TOP_RIGHT, autoClose: 6000 });
  } finally {
    reqLoading.value = false;
  }
}

function switchToRequests() {
  currentView.value = "requests";
  if (!requests.value.length) fetchRequests();
}

// ─── Media modal (NEW) ────────────────────────────────────────────────────────
function openMediaModal(req) {
  mediaModal.value = {
    open: true,
    url: req.media_url,
    mediaType: req.media_type,
    type: req.type,
    orderNumber: req.order?.order_number ?? "—",
  };
}

// ─── Approve / Reject modals (NEW) ────────────────────────────────────────────
function openApproveModal(req) {
  Object.assign(reqConfirmModal.value, {
    open: true,
    action: "approve",
    request: req,
    notes: "",
    loading: false,
  });
}
function openRejectModal(req) {
  Object.assign(reqConfirmModal.value, {
    open: true,
    action: "reject",
    request: req,
    notes: "",
    loading: false,
  });
}

async function doRequestAction() {
  const { action, request, notes } = reqConfirmModal.value;
  reqConfirmModal.value.loading = true;
  reqActionLoading.value = request.id + (action === "approve" ? "_a" : "_r");
  try {
    if (action === "approve") {
      await deliveryService.approveOrderRequest(request.id, {
        admin_notes: notes,
      });
      toast.success("Request approved.", {
        position: toast.POSITION.TOP_RIGHT,
        autoClose: 3000,
      });
    } else {
      await deliveryService.rejectOrderRequest(request.id, {
        admin_notes: notes,
      });
      toast.success("Request rejected.", {
        position: toast.POSITION.TOP_RIGHT,
        autoClose: 3000,
      });
    }
    reqConfirmModal.value.open = false;
    await fetchRequests(reqMeta.value.current_page, true);
  } catch (e) {
    toast.error(e?.response?.data?.message ?? "Action failed.", {
      position: toast.POSITION.TOP_RIGHT,
      autoClose: 4000,
    });
  } finally {
    reqConfirmModal.value.loading = false;
    reqActionLoading.value = null;
  }
}

// ─── Calendar ─────────────────────────────────────────────────────────────────
function switchToCalendar() {
  currentView.value = "calendar";
  if (!calendarLoaded.value) fetchCalendarOrders();
}

async function fetchCalendarOrders() {
  isLoading.value = true;
  isLoadingMessage.value = `Loading orders for ${calMonthName.value} ${calYear.value}…`;
  calendarLoaded.value = false;
  try {
    const res = await deliveryService.scOrders({ per_page: 100 });
    const deliveries = Array.isArray(res) ? res : (res.data ?? []);
    const year = calYear.value,
      month = calMonth.value;
    const result = [],
      seen = new Set();
    for (const delivery of deliveries) {
      const order = delivery?.order;
      if (!order || seen.has(order.id)) continue;
      const dateParts = [
        order.reservation_date,
        order.delivered_at,
        order.created_at,
      ]
        .filter(Boolean)
        .map((d) => safeDate(d));
      const inMonth = dateParts.some((ds) => {
        if (!ds) return false;
        const [y, m] = ds.split("-").map(Number);
        return y === year && m - 1 === month;
      });
      if (inMonth) {
        seen.add(order.id);
        result.push(order);
      }
    }
    calendarOrders.value = result;
    calendarLoaded.value = true;
    if (!result.length)
      toast.info(`No orders for ${calMonthName.value} ${calYear.value}.`, {
        position: toast.POSITION.TOP_RIGHT,
        autoClose: 3000,
      });
  } catch {
    toast.error("Failed to load calendar orders.", {
      position: toast.POSITION.TOP_RIGHT,
      autoClose: 4000,
    });
  } finally {
    isLoading.value = false;
  }
}

function prevMonth() {
  const d = new Date(calendarDate.value);
  d.setMonth(d.getMonth() - 1);
  calendarDate.value = d;
  selectedCalDate.value = null;
  fetchCalendarOrders();
}
function nextMonth() {
  const d = new Date(calendarDate.value);
  d.setMonth(d.getMonth() + 1);
  calendarDate.value = d;
  selectedCalDate.value = null;
  fetchCalendarOrders();
}
function goToday() {
  calendarDate.value = new Date();
  selectedCalDate.value = toDateStr(new Date());
  fetchCalendarOrders();
}
function selectDate(cell) {
  if (!cell.isCurrentMonth) return;
  selectedCalDate.value =
    selectedCalDate.value === cell.dateStr ? null : cell.dateStr;
}

// ─── Detail / log / advance modals ───────────────────────────────────────────
function openDetail(delivery) {
  model3d.value = { open: false, url: null, name: "" };
  detailModal.value = { open: true, delivery };
}
function open3dModal(url, name) {
  model3d.value = { open: true, url, name: name || "3D Model" };
}
async function openLogs(delivery) {
  logsModal.value = { open: true, delivery, logs: [], loading: true };
  try {
    const res = await deliveryService.getLogs(delivery.id);
    logsModal.value.logs = res.logs ?? [];
  } catch {
    toast.error("Failed to load logs.", {
      position: toast.POSITION.TOP_RIGHT,
      autoClose: 4000,
    });
  } finally {
    logsModal.value.loading = false;
  }
}
function openAdvance(delivery) {
  advanceModal.value = { open: true, delivery };
}
function advanceOptions(delivery) {
  return TRANSITIONS[delivery?.status] ?? [];
}
async function doAdvance(targetStatus) {
  advancing.value = true;
  try {
    const res = await deliveryService.updateStatus(
      advanceModal.value.delivery.id,
      targetStatus,
    );
    const idx = orders.value.findIndex(
      (o) => o.id === advanceModal.value.delivery.id,
    );
    if (idx !== -1 && res.delivery) orders.value[idx] = res.delivery;
    advanceModal.value.open = false;
    toast.success("Status updated.", {
      position: toast.POSITION.TOP_RIGHT,
      autoClose: 3000,
    });
    fetchOrders(meta.value.current_page, true);
  } catch (e) {
    toast.error(e?.response?.data?.message ?? "Update failed.", {
      position: toast.POSITION.TOP_RIGHT,
      autoClose: 4000,
    });
  } finally {
    advancing.value = false;
  }
}

// ─── Formatters ───────────────────────────────────────────────────────────────
function safeDate(raw) {
  if (!raw) return null;
  const s = String(raw);
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
  const part = s.split("T")[0];
  if (/^\d{4}-\d{2}-\d{2}$/.test(part)) return part;
  const d = new Date(s);
  if (isNaN(d)) return null;
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}
function toDateStr(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}
function formatDateLabel(dateStr) {
  return new Date(dateStr + "T00:00:00").toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}
function formatTime(iso) {
  if (!iso) return "—";
  const d = new Date(iso),
    diff = Date.now() - d;
  if (diff < 60_000) return "just now";
  if (diff < 3_600_000) return `${Math.floor(diff / 60_000)}m ago`;
  if (diff < 86_400_000) return `${Math.floor(diff / 3_600_000)}h ago`;
  return d.toLocaleDateString("en-US", { month: "short", day: "numeric" });
}
function formatFull(iso) {
  if (!iso) return "—";
  return new Date(iso).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}
function statusLabel(s) {
  return (
    {
      pending: "Pending",
      to_processed: "To Processed",
      to_ship: "To Ship",
      to_received: "To Received",
      completed: "Completed",
      returned: "Returned",
      refunded: "Refunded",
      processing: "Processing",
      shipped: "Shipped",
      out_for_delivery: "Out for Delivery",
      cancelled: "Cancelled",
    }[s] ?? s
  );
}
function paymentLabel(m) {
  return (
    {
      cod: "COD",
      gcash: "GCash",
      maya: "Maya",
      card: "Card",
      bank_transfer: "Bank Transfer",
    }[m] ??
    (m || "—")
  );
}
function dateTypeLabel(t) {
  return (
    { reservation: "Reservation", created: "Created", delivered: "Delivered" }[
      t
    ] ?? t
  );
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
  fetchOrders();
  startPolling();
});
onUnmounted(() => clearInterval(pollTimer));
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════
   ALL ORIGINAL STYLES PRESERVED — only new rules appended at the bottom
═══════════════════════════════════════════════════════════════════════ */
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.sc-orders-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}
.page-title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.page-sub {
  font-size: 13px;
  color: #6b7280;
  margin: 2px 0 0;
}
.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.live-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 20px;
  font-size: 12.5px;
  font-weight: 600;
  color: #16a34a;
}
.live-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #16a34a;
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.4;
  }
}

.btn-refresh {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #6b7280;
  transition: all 0.15s;
}
.btn-refresh:hover {
  border-color: #10b981;
  color: #10b981;
}
.btn-refresh.spinning svg {
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.kpi-strip {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 10px;
}
.kpi-card {
  background: #fff;
  border: 1.5px solid #f0f2f5;
  border-radius: 14px;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: all 0.15s;
}
.kpi-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07);
}
.kpi-card.active {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
}
.kpi-icon-box {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.kpi-icon-box svg {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}
.kpi-icon-box.kpi-gray {
  background: #374151;
  color: #fff;
}
.kpi-icon-box.kpi-amber {
  background: #f59e0b;
  color: #fff;
}
.kpi-icon-box.kpi-blue {
  background: #3b82f6;
  color: #fff;
}
.kpi-icon-box.kpi-indigo {
  background: #6366f1;
  color: #fff;
}
.kpi-icon-box.kpi-purple {
  background: #8b5cf6;
  color: #fff;
}
.kpi-icon-box.kpi-green {
  background: #10b981;
  color: #fff;
}
.kpi-icon-box.kpi-red {
  background: #ef4444;
  color: #fff;
}
.kpi-text {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}
.kpi-label {
  font-size: 10.5px;
  font-weight: 600;
  color: #9ca3af;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.kpi-num {
  font-size: 20px;
  font-weight: 800;
  color: #111827;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.5px;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 9px;
  min-width: 280px;
}
.search-box input {
  border: none;
  background: none;
  outline: none;
  font-size: 13px;
  color: #374151;
  flex: 1;
  font-family: "Poppins", sans-serif;
}
.toolbar-note {
  font-size: 12px;
  color: #9ca3af;
}

.card {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 14px;
  overflow: hidden;
}
.table-wrap {
  overflow-x: auto;
}
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.data-table thead tr {
  background: #f9fafb;
}
.data-table th {
  padding: 10px 16px;
  text-align: left;
  font-size: 11.5px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-bottom: 1px solid #e8ecf0;
  white-space: nowrap;
}
.data-row td {
  padding: 13px 16px;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}
.data-row:last-child td {
  border-bottom: none;
}
.data-row:hover td {
  background: #fafafa;
}
.loading-row,
.empty-row {
  text-align: center;
  padding: 48px 16px !important;
  color: #9ca3af;
}
.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #e5e7eb;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  margin: 0 auto;
}

.delivery-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.delivery-id-text {
  font-weight: 600;
  color: #111827;
  font-size: 13px;
}
.barcode-text {
  font-family: monospace;
  font-size: 11px;
  color: #9ca3af;
}
.order-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.order-ref {
  font-weight: 600;
  color: #374151;
}
.order-amount {
  font-size: 12px;
  color: #6b7280;
}
.items-count {
  font-size: 12.5px;
  color: #6b7280;
}
.muted-cell {
  color: #6b7280;
  font-size: 12.5px;
  white-space: nowrap;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 600;
  white-space: nowrap;
}
.status-badge.pending {
  background: #fef3c7;
  color: #92400e;
}
.status-badge.to_processed {
  background: #dbeafe;
  color: #1d4ed8;
}
.status-badge.to_ship {
  background: #e0e7ff;
  color: #3730a3;
}
.status-badge.to_received {
  background: #ede9fe;
  color: #6d28d9;
}
.status-badge.completed {
  background: #dcfce7;
  color: #166534;
}
.status-badge.returned {
  background: #fee2e2;
  color: #991b1b;
}
.status-badge.refunded {
  background: #f3f4f6;
  color: #374151;
}
.status-badge.processing {
  background: #dbeafe;
  color: #1d4ed8;
}
.status-badge.shipped {
  background: #ede9fe;
  color: #6d28d9;
}
.status-badge.cancelled {
  background: #fee2e2;
  color: #991b1b;
}
.status-badge.out_for_delivery {
  background: #fce7f3;
  color: #9d174d;
}

.action-cell {
  display: flex;
  gap: 6px;
}
.action-btn {
  width: 30px;
  height: 30px;
  border-radius: 7px;
  border: 1px solid #e5e7eb;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #6b7280;
  transition: all 0.18s;
}
.detail-btn:hover {
  border-color: #93c5fd;
  color: #2563eb;
  background: #eff6ff;
}
.barcode-btn:hover {
  border-color: #c4b5fd;
  color: #7c3aed;
  background: #f5f3ff;
}
.logs-btn:hover {
  border-color: #fcd34d;
  color: #d97706;
  background: #fffbeb;
}
.advance-btn:hover {
  border-color: #6ee7b7;
  color: #059669;
  background: #ecfdf5;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 12px 20px;
  border-top: 1px solid #f0f2f5;
}
.page-btn {
  width: 30px;
  height: 30px;
  border-radius: 7px;
  border: 1px solid #e5e7eb;
  background: #fff;
  cursor: pointer;
  font-size: 16px;
  color: #374151;
}
.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.page-info {
  font-size: 13px;
  color: #6b7280;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.logs-modal,
.advance-modal {
  background: #fff;
  border-radius: 16px;
  width: 480px;
  max-width: 95vw;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  max-height: 80vh;
  display: flex;
  flex-direction: column;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px;
  border-bottom: 1px solid #f0f2f5;
  flex-shrink: 0;
}
.modal-header h2 {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.modal-close {
  background: none;
  border: none;
  font-size: 18px;
  color: #9ca3af;
  cursor: pointer;
}
.logs-body {
  padding: 20px 22px;
  overflow-y: auto;
  flex: 1;
}
.logs-loading {
  display: flex;
  justify-content: center;
  padding: 40px;
}
.logs-empty {
  text-align: center;
  color: #9ca3af;
  padding: 40px;
}
.timeline {
  display: flex;
  flex-direction: column;
}
.timeline-item {
  display: flex;
  gap: 14px;
  position: relative;
  padding-bottom: 20px;
}
.timeline-item:last-child {
  padding-bottom: 0;
}
.timeline-item::before {
  content: "";
  position: absolute;
  left: 7px;
  top: 18px;
  bottom: 0;
  width: 2px;
  background: #f3f4f6;
}
.timeline-item:last-child::before {
  display: none;
}
.tl-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 2px;
}
.tl-dot.pending {
  background: #fef3c7;
  border: 2px solid #f59e0b;
}
.tl-dot.to_processed {
  background: #dbeafe;
  border: 2px solid #3b82f6;
}
.tl-dot.to_ship {
  background: #e0e7ff;
  border: 2px solid #6366f1;
}
.tl-dot.to_received {
  background: #ede9fe;
  border: 2px solid #8b5cf6;
}
.tl-dot.completed {
  background: #dcfce7;
  border: 2px solid #10b981;
}
.tl-dot.returned {
  background: #fee2e2;
  border: 2px solid #ef4444;
}
.tl-content {
  flex: 1;
}
.tl-status {
  margin-bottom: 4px;
}
.tl-meta {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #9ca3af;
}
.advance-body {
  padding: 22px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.advance-hint {
  font-size: 13.5px;
  color: #374151;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.advance-options {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.advance-opt-btn {
  padding: 10px 22px;
  border: none;
  border-radius: 9px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.15s;
  font-family: "Poppins", sans-serif;
}
.advance-opt-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.advance-opt-btn.to_processed {
  background: #dbeafe;
  color: #1d4ed8;
}
.advance-opt-btn.to_ship {
  background: #e0e7ff;
  color: #3730a3;
}
.advance-opt-btn.to_received {
  background: #ede9fe;
  color: #6d28d9;
}
.advance-opt-btn.completed {
  background: #dcfce7;
  color: #166534;
}
.advance-opt-btn.returned {
  background: #fee2e2;
  color: #991b1b;
}
.advance-opt-btn.refunded {
  background: #f3f4f6;
  color: #374151;
}

/* View toggle */
.view-toggle {
  display: flex;
  border: 1px solid #e5e7eb;
  border-radius: 9px;
  overflow: hidden;
}
.view-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 13px;
  background: #fff;
  border: none;
  font-size: 12.5px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.14s;
  font-family: "Poppins", sans-serif;
  position: relative;
}
.view-btn + .view-btn {
  border-left: 1px solid #e5e7eb;
}
.view-btn.active {
  background: #10b981;
  color: #fff;
}
.view-btn:hover:not(.active) {
  background: #f9fafb;
  color: #374151;
}
.view-btn-badge {
  position: absolute;
  top: 3px;
  right: 4px;
  background: #ef4444;
  color: #fff;
  font-size: 9px;
  font-weight: 700;
  width: 15px;
  height: 15px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Calendar */
.calendar-section {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 14px;
  padding: 22px 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.cal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
.cal-month-nav {
  display: flex;
  align-items: center;
  gap: 12px;
}
.cal-nav-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #374151;
  transition: all 0.14s;
}
.cal-nav-btn:hover {
  border-color: #10b981;
  color: #10b981;
}
.cal-month-label {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
  min-width: 160px;
  text-align: center;
}
.cal-legend {
  display: flex;
  align-items: center;
  gap: 14px;
}
.leg-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: #6b7280;
}
.leg-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.leg-reservation {
  background: #10b981;
}
.leg-created {
  background: #6366f1;
}
.leg-delivered {
  background: #f59e0b;
}
.cal-today-btn {
  padding: 7px 16px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  font-size: 12.5px;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  transition: all 0.14s;
  font-family: "Poppins", sans-serif;
}
.cal-today-btn:hover {
  border-color: #10b981;
  color: #10b981;
}
.cal-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}
.cal-day-header {
  text-align: center;
  font-size: 11.5px;
  font-weight: 600;
  color: #9ca3af;
  padding: 6px 0;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.cal-cell {
  min-height: 88px;
  border: 1.5px solid #f0f2f5;
  border-radius: 9px;
  padding: 6px 5px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  background: #fff;
  transition:
    border-color 0.12s,
    background 0.12s;
  cursor: pointer;
}
.cal-cell:hover:not(.cal-other-month) {
  border-color: #a7f3d0;
  background: #f0fdf4;
}
.cal-cell.cal-other-month {
  opacity: 0.28;
  cursor: default;
  pointer-events: none;
}
.cal-cell.cal-today {
  border-color: #10b981;
  background: #f0fdf4;
}
.cal-cell.cal-has-orders {
  border-color: #6ee7b7;
}
.cal-cell.cal-selected {
  border-color: #111827;
  border-width: 2px;
}
.cal-cell.cal-today .cal-date-num {
  background: #10b981;
  color: #fff;
}
.cal-date-num {
  font-size: 12.5px;
  font-weight: 600;
  color: #374151;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.cal-order-pills {
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow: hidden;
}
.cal-pill {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 5px;
  border-radius: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  line-height: 1.4;
}
.cal-pill--reservation {
  background: #d1fae5;
  color: #065f46;
}
.cal-pill--created {
  background: #e0e7ff;
  color: #3730a3;
}
.cal-pill--delivered {
  background: #fef3c7;
  color: #92400e;
}
.cal-pill-more {
  font-size: 10px;
  color: #9ca3af;
  padding: 1px 4px;
}
.cal-panel {
  border-top: 1px solid #f0f2f5;
  padding-top: 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.cal-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.cal-panel-title {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.cal-panel-count {
  padding: 3px 10px;
  border-radius: 20px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  font-size: 12px;
  font-weight: 600;
  color: #16a34a;
}
.cal-panel-empty {
  text-align: center;
  color: #9ca3af;
  font-size: 13px;
  padding: 20px 0;
}
.cal-card-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.cal-order-card {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 14px 16px;
  border: 1px solid #e8ecf0;
  border-radius: 10px;
  background: #fafafa;
  transition: box-shadow 0.12s;
}
.cal-order-card:hover {
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
}
.coc-left {
  min-width: 130px;
  flex-shrink: 0;
}
.coc-id {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}
.coc-sub {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-top: 4px;
  font-size: 12px;
  color: #6b7280;
}
.coc-sep {
  color: #d1d5db;
}
.coc-mid {
  flex: 1;
  min-width: 0;
}
.coc-addr {
  font-size: 12.5px;
  color: #374151;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.coc-contact {
  font-size: 12px;
  color: #9ca3af;
  margin-top: 3px;
}
.coc-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  flex-shrink: 0;
}
.coc-total {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}
.date-type-pill {
  font-size: 10.5px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
}
.dtp--reservation {
  background: #d1fae5;
  color: #065f46;
}
.dtp--created {
  background: #e0e7ff;
  color: #3730a3;
}
.dtp--delivered {
  background: #fef3c7;
  color: #92400e;
}
.cal-panel-fade-enter-active {
  transition:
    opacity 0.2s,
    transform 0.2s;
}
.cal-panel-fade-enter-from {
  opacity: 0;
  transform: translateY(-6px);
}

/* Order Detail Modal */
.detail-modal {
  background: #fff;
  border-radius: 16px;
  width: 680px;
  max-width: 96vw;
  max-height: 86vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.18);
}
.dm-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.dm-body {
  padding: 20px 24px;
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.dm-meta-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px 16px;
  background: #f9fafb;
  border: 1px solid #f0f2f5;
  border-radius: 10px;
  padding: 16px;
}
.dm-meta-full {
  grid-column: 1 / -1;
}
.dm-meta-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.dm-meta-label {
  font-size: 10.5px;
  font-weight: 600;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.dm-meta-val {
  font-size: 13px;
  color: #111827;
  font-weight: 500;
}
.dm-meta-val.mono {
  font-family: monospace;
  font-size: 12px;
}
.dm-meta-val.bold {
  font-weight: 700;
}
.dm-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: #374151;
}
.dm-item-count {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #16a34a;
  font-size: 11px;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 20px;
}
.dm-items {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.dm-no-items {
  text-align: center;
  color: #9ca3af;
  font-size: 13px;
  padding: 20px;
}
.dm-item {
  display: flex;
  gap: 14px;
  align-items: flex-start;
  padding: 14px;
  border: 1px solid #e8ecf0;
  border-radius: 10px;
  background: #fafafa;
}
.dm-item-img-wrap {
  width: 72px;
  height: 72px;
  border-radius: 8px;
  overflow: hidden;
  flex-shrink: 0;
  border: 1px solid #e8ecf0;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
}
.dm-item-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.dm-item-img-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
}
.dm-item-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.dm-item-name {
  font-size: 13.5px;
  font-weight: 700;
  color: #111827;
}
.dm-item-desc {
  font-size: 12px;
  color: #6b7280;
  line-height: 1.4;
}
.dm-item-attrs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.dm-attr {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 20px;
  background: #f3f4f6;
  color: #374151;
  font-weight: 500;
}
.dm-item-price {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
  flex-shrink: 0;
}
.dm-item-qty {
  font-size: 13px;
  color: #6b7280;
}
.dm-item-unit {
  font-size: 11.5px;
  color: #9ca3af;
}
.dm-item-sub {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}
.dm-totals {
  border-top: 1px solid #f0f2f5;
  padding-top: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.dm-total-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: #6b7280;
}
.dm-total-grand {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
  border-top: 1px solid #e5e7eb;
  padding-top: 8px;
  margin-top: 4px;
}
.dm-3d-toggle {
  margin-top: 6px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #fff;
  padding: 5px 12px;
  border-radius: 20px;
  background: #48bb78;
  border: none;
  cursor: pointer;
  transition: background 0.14s;
}
.dm-3d-toggle:hover {
  background: #38a169;
}
.model3d-modal {
  background: #fff;
  border-radius: 14px;
  width: 900px;
  max-width: 96vw;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.2);
}
.model3d-body {
  flex: 1;
  overflow: hidden;
}
.model3d-iframe {
  width: 100%;
  height: 560px;
  border: none;
  display: block;
}
.model3d-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 20px;
  background: #f9fafb;
  border-top: 1px solid #e5e7eb;
  border-radius: 0 0 14px 14px;
  font-size: 12px;
  color: #9ca3af;
}
.dm-3d-download {
  font-size: 12px;
  font-weight: 600;
  color: #7c3aed;
  text-decoration: none;
  padding: 3px 10px;
  border-radius: 8px;
  background: #f5f3ff;
  border: 1px solid #ddd6fe;
  transition: background 0.12s;
}
.dm-3d-download:hover {
  background: #ede9fe;
}

/* ═══════════════════════════════════════════════════════════════════════
   NEW: REQUESTS VIEW STYLES
═══════════════════════════════════════════════════════════════════════ */

/* Requests KPI strip — 6 cols */
.kpi-strip.req-strip {
  grid-template-columns: repeat(6, 1fr);
}

/* Requests card header */
.req-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px 14px;
  border-bottom: 1px solid #f0f2f5;
}
.req-card-title {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.req-card-sub {
  font-size: 12.5px;
  color: #9ca3af;
  margin: 2px 0 0;
}

/* Type filter bar in toolbar */
.req-type-filter {
  display: flex;
  gap: 4px;
}
.req-type-btn {
  padding: 6px 14px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  font-size: 12.5px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.14s;
  font-family: "Poppins", sans-serif;
}
.req-type-btn:hover {
  border-color: #10b981;
  color: #10b981;
}
.req-type-btn.active {
  background: #10b981;
  border-color: #10b981;
  color: #fff;
}

/* Row data cells */
.req-row td {
  vertical-align: top;
}

/* Customer */
.req-customer {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.req-customer-name {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}
.req-customer-email {
  font-size: 11.5px;
  color: #9ca3af;
}

/* Type badge */
.req-type-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 600;
  white-space: nowrap;
}
.req-type--return {
  background: #fff7ed;
  color: #c2410c;
}
.req-type--refund {
  background: #eff6ff;
  color: #1d4ed8;
}

/* Reason */
.req-reason {
  font-size: 12.5px;
  color: #374151;
  line-height: 1.5;
  max-width: 240px;
}

/* Media thumbnail */
.req-media-thumb {
  width: 60px;
  height: 48px;
  border-radius: 6px;
  overflow: hidden;
  cursor: pointer;
  position: relative;
  border: 1px solid #e5e7eb;
  background: #f3f4f6;
  flex-shrink: 0;
}
.req-media-thumb:hover .req-media-overlay {
  opacity: 1;
}
.req-media-preview {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.req-media-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.18s;
}
.req-no-proof {
  font-size: 11.5px;
  color: #9ca3af;
  font-style: italic;
}

/* Status badge for requests */
.req-status-badge {
  display: inline-flex;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 600;
}
.req-status--pending {
  background: #fef3c7;
  color: #92400e;
}
.req-status--approved {
  background: #dcfce7;
  color: #166534;
}
.req-status--rejected {
  background: #fee2e2;
  color: #991b1b;
}

.req-admin-note {
  font-size: 11px;
  color: #9ca3af;
  margin-top: 4px;
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-style: italic;
}

/* Action buttons in requests table */
.req-action-cell {
  display: flex;
  gap: 6px;
}
.req-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 7px;
  border: none;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  font-family: "Poppins", sans-serif;
}
.req-action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.req-approve-btn {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #bbf7d0;
}
.req-approve-btn:hover:not(:disabled) {
  background: #10b981;
  color: #fff;
  border-color: #10b981;
}
.req-reject-btn {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
}
.req-reject-btn:hover:not(:disabled) {
  background: #ef4444;
  color: #fff;
  border-color: #ef4444;
}

.req-resolved-note {
  font-size: 12.5px;
  color: #9ca3af;
  white-space: nowrap;
}

/* Empty state inside table */
.req-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 20px;
}
.req-empty-ico {
  width: 48px;
  height: 48px;
  opacity: 0.5;
}
.req-empty p {
  margin: 0;
  font-size: 13px;
  color: #9ca3af;
}

/* Media proof modal */
.media-modal {
  background: #fff;
  border-radius: 14px;
  width: 640px;
  max-width: 96vw;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.2);
}
.media-modal-body {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: #f9fafb;
  overflow: hidden;
}
.media-modal-content {
  max-width: 100%;
  max-height: 480px;
  border-radius: 8px;
  object-fit: contain;
}
.media-modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding: 12px 20px;
  border-top: 1px solid #e5e7eb;
}

/* Approve / Reject confirm modal */
.req-confirm-modal {
  background: #fff;
  border-radius: 14px;
  width: 480px;
  max-width: 96vw;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.18);
  display: flex;
  flex-direction: column;
}
.req-confirm-body {
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.req-confirm-info {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13.5px;
  color: #374151;
  margin: 0;
  flex-wrap: wrap;
}
.req-confirm-reason {
  font-size: 13px;
  color: #6b7280;
  background: #f9fafb;
  border: 1px solid #f0f2f5;
  border-radius: 8px;
  padding: 12px 14px;
  margin: 0;
  line-height: 1.5;
  max-height: 100px;
  overflow-y: auto;
}
.req-notes-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.req-notes-lbl {
  font-size: 12.5px;
  font-weight: 600;
  color: #374151;
}
.req-notes-textarea {
  width: 100%;
  padding: 9px 12px;
  background: #f9fafb;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  color: #374151;
  resize: vertical;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}
.req-notes-textarea:focus {
  border-color: #10b981;
  background: #fff;
}
.req-confirm-footer {
  display: flex;
  gap: 10px;
  padding: 16px 22px;
  border-top: 1px solid #f0f2f5;
  justify-content: flex-end;
}
.action-btn-ghost {
  padding: 9px 18px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
  transition: background 0.15s;
}
.action-btn-ghost:hover:not(:disabled) {
  background: #f9fafb;
}
.action-btn-ghost:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.req-confirm-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 20px;
  border-radius: 8px;
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.18s;
}
.req-confirm-btn--approve {
  background: #10b981;
  color: #fff;
}
.req-confirm-btn--approve:hover:not(:disabled) {
  background: #059669;
}
.req-confirm-btn--reject {
  background: #ef4444;
  color: #fff;
}
.req-confirm-btn--reject:hover:not(:disabled) {
  background: #dc2626;
}
.req-confirm-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.spin-ico {
  width: 15px;
  height: 15px;
  animation: spin 0.8s linear infinite;
  flex-shrink: 0;
}
</style>
