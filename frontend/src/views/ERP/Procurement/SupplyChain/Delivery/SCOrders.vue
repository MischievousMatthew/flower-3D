<template>
  <div class="sc-orders-page">
    <BarcodeModal ref="barcodeModalRef" />

    <!-- ── Header ─────────────────────────────────────────────────────── -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Deliveries</h1>
        <p class="page-sub">Prepare each order, photograph it, send it out and track it until the customer has it.</p>
      </div>
      <div class="header-right">
        <!-- View toggle — table / calendar / requests -->
        <div class="view-toggle">
          <button
            class="view-btn"
            :class="{ active: currentView === 'table' }"
            @click="currentView = 'table'"
            title="Orders"
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
            Orders
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
            title="Returns and refunds"
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
            Returns & refunds
            <span v-if="reqCounts.needs_shop > 0" class="view-btn-badge">{{
              reqCounts.needs_shop
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

    <!-- ── Order stages, the same as the vendor's Orders page ────────── -->
    <template v-if="currentView !== 'requests'">
      <section class="dl-due" aria-label="Due today and overdue">
        <button
          v-for="t in DUE_TILES"
          :key="t.key"
          type="button"
          class="dl-due-tile"
          :class="[`tone-${t.tone}`, { on: activeTab === t.key, hot: t.key === 'overdue' && counts.overdue, zero: !counts[t.key] }]"
          @click="setStatusTab(t.key)"
        >
          <span class="dl-due-icon" v-html="ICONS[t.icon]"></span>
          <span class="dl-due-text">
            <span class="dl-due-label">{{ t.label }}</span>
            <span class="dl-due-hint">{{ counts[t.key] ? t.hint : t.empty }}</span>
          </span>
          <strong>{{ counts[t.key] ?? 0 }}</strong>
        </button>
      </section>
      <section class="dl-pipe" aria-label="Orders by stage">
        <button
          v-for="(st, i) in PIPELINE"
          :key="st.key"
          type="button"
          class="dl-pipe-step"
          :class="[`tone-${st.tone}`, { on: activeTab === st.key }]"
          @click="setStatusTab(st.key)"
        >
          <span class="dl-pipe-label"><span class="dl-pipe-dot"></span>{{ st.label }}</span>
          <strong>{{ counts[st.key] ?? 0 }}</strong>
          <span class="dl-pipe-hint">{{ st.hint }}</span>
          <span v-if="i < PIPELINE.length - 1" class="dl-pipe-arrow" aria-hidden="true" v-html="ICONS.chevronRight"></span>
        </button>
      </section>
      <div class="dl-tabs">
        <div class="sp-seg" role="tablist" aria-label="Filter deliveries">
          <button v-for="t in statusTabs" :key="t.value" type="button" role="tab" :aria-selected="activeTab === t.value" :class="{ on: activeTab === t.value }" @click="setStatusTab(t.value)">
            {{ t.label }}<span class="count">{{ counts[t.value] ?? 0 }}</span>
          </button>
        </div>
      </div>
    </template>

    <!-- ── Returns and refunds: where each one stands ─────────────────── -->
    <template v-if="currentView === 'requests'">
      <section class="dl-pipe dl-pipe--req" aria-label="Returns and refunds by stage">
        <button
          v-for="st in REQ_STAGES"
          :key="st.key"
          type="button"
          class="dl-pipe-step"
          :class="[`tone-${st.tone}`, { on: reqActiveFilter === st.filter }]"
          @click="setReqFilter(st.filter)"
        >
          <span class="dl-pipe-label"><span class="dl-pipe-dot"></span>{{ st.label }}</span>
          <strong>{{ reqCounts[st.key] ?? 0 }}</strong>
          <span class="dl-pipe-hint">{{ st.hint }}</span>
        </button>
      </section>
      <div class="dl-tabs">
        <div class="sp-seg" role="tablist" aria-label="Filter returns and refunds">
          <button v-for="t in reqTabs" :key="t.value" type="button" role="tab" :aria-selected="reqActiveFilter === t.value" :class="{ on: reqActiveFilter === t.value }" @click="setReqFilter(t.value)">
            {{ t.label }}<span class="count">{{ reqCountFor(t.value) }}</span>
          </button>
        </div>
      </div>
    </template>

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
      <div
        class="toolbar-note"
        v-if="lastRefreshed && currentView !== 'requests'"
      >
        Updated {{ lastRefreshed }}
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         LIST VIEW
    ══════════════════════════════════════════════════════════════════ -->
    <section v-if="currentView === 'table'" class="sp-card dl-list-card" aria-label="Deliveries">
      <ul v-if="loading && !orders.length" class="dl-list">
        <li v-for="n in 5" :key="n" class="dl-row">
          <div class="bc-skeleton dl-thumb"></div>
          <div class="dl-grow"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-line w70"></div></div>
          <div class="bc-skeleton dl-sk-btn"></div>
        </li>
      </ul>
      <div v-else-if="!orders.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.truck"></span>
        <h3>{{ search ? "No deliveries match" : activeTab === "all" ? "No deliveries yet" : `Nothing ${TAB_EMPTY[activeTab] ?? "here"}` }}</h3>
        <p>{{ search ? "Try another order number or customer." : "Orders show up here once customers check out." }}</p>
      </div>
      <ul v-else class="dl-list" :class="{ dim: loading }">
        <li v-for="d in orders" :key="d.id" class="dl-row">
          <button type="button" class="dl-main" @click="openDetail(d)">
            <span class="dl-thumb-wrap">
              <img v-if="d.photo_url || firstImage(d)" :src="d.photo_url || firstImage(d)" :alt="d.photo_url ? 'Photo of the order' : itemNames(d)" class="dl-thumb" loading="lazy" />
              <span v-else class="dl-thumb blank" v-html="ICONS.flower"></span>
              <span v-if="(d.order?.items_count ?? 0) > 1" class="dl-thumb-count">×{{ d.order.items_count }}</span>
            </span>
            <span class="dl-text">
              <span class="dl-top">
                <strong>{{ d.order?.delivery_contact_name || "Customer" }}</strong>
                <span class="dl-num">{{ d.order?.order_number ?? d.delivery_id }}</span>
                <span v-if="d.barcode" class="dl-code">{{ d.barcode }}</span>
              </span>
              <span class="dl-items">{{ itemNames(d) || "—" }}</span>
              <span class="dl-meta">
                <span v-if="d.order?.reservation_date" class="dl-date"><span v-html="ICONS.truck"></span>{{ dayText(d.order.reservation_date) }}</span>
                <span v-if="lateDays(d) > 0" class="sp-chip red">{{ lateDays(d) }} {{ lateDays(d) === 1 ? "day" : "days" }} late</span>
                <span v-else-if="lateDays(d) === 0" class="sp-chip green">Due today</span>
                <span v-if="d.order?.store_name">{{ d.order.store_name }}</span>
                <span v-if="d.photo_url" class="sp-chip teal"><span v-html="ICONS.image"></span>Photo taken</span>
                <span v-else-if="d.status === 'to_ship'" class="sp-chip amber">Needs a photo</span>
                <span class="dl-updated">Updated {{ formatTime(d.updated_at) }}</span>
              </span>
            </span>
          </button>
          <div class="dl-side">
            <div class="dl-money">
              <strong>₱{{ Number(d.order?.total_amount ?? 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</strong>
              <span>{{ paymentLabel(d.order?.payment_method) }}<template v-if="d.order?.payment_status === 'paid'"> · paid</template></span>
            </div>
            <span class="sp-chip" :class="STATUS_TONE[d.status] ?? 'slate'">{{ STATUS_LABEL[d.status] ?? d.status_label }}</span>
            <div class="dl-actions">
              <button v-if="NEXT[d.status]" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="openAdvance(d, NEXT[d.status].value)">{{ NEXT[d.status].label }}</button>
              <span v-else-if="d.status === 'to_received'" class="dl-wait" title="The customer marks it received, or it completes by itself 3 days after it went out">Waiting for the customer</span>
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="openDetail(d)">Details</button>
              <button type="button" class="sp-icon-btn" title="Barcode" :aria-label="`Barcode for ${d.order?.order_number ?? d.delivery_id}`" @click="barcodeModalRef.open(d.order?.id, d.delivery_id)"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" width="16" height="16" aria-hidden="true"><path d="M3 4v12M6 4v12M8.5 4v12M11 4v12M14 4v12M17 4v12" /></svg></button>
              <button type="button" class="sp-icon-btn" title="History" :aria-label="`History of ${d.order?.order_number ?? d.delivery_id}`" @click="openLogs(d)"><span v-html="ICONS.history"></span></button>
            </div>
          </div>
        </li>
      </ul>
      <ShowMore v-if="orders.length" tone="vendor" :has-more="orderPager.hasMore.value" :loading="orderPager.loadingMore.value" :shown="orders.length" :total="orderPager.total.value" @more="orderPager.showMore()" />
    </section>

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
         RETURNS AND REFUNDS
    ══════════════════════════════════════════════════════════════════ -->
    <section v-if="currentView === 'requests'" class="sp-card dl-list-card" aria-label="Returns and refunds">
      <ul v-if="reqLoading && !requests.length" class="dl-list">
        <li v-for="n in 4" :key="n" class="dl-row">
          <div class="bc-skeleton dl-thumb"></div>
          <div class="dl-grow"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-line w70"></div></div>
          <div class="bc-skeleton dl-sk-btn"></div>
        </li>
      </ul>
      <div v-else-if="!requests.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.restore"></span>
        <h3>{{ reqSearch ? "No returns or refunds match" : "No returns or refunds here" }}</h3>
        <p>{{ reqSearch ? "Try another order number or customer." : "When a customer asks to return an order or get money back, it shows up here." }}</p>
      </div>
      <ul v-else class="dl-list" :class="{ dim: reqLoading }">
        <li v-for="req in requests" :key="req.id" class="dl-row" :class="{ 'dl-row--turn': req.next_actor === 'vendor' }">
          <button type="button" class="dl-main" @click="openCase(req)">
            <span class="dl-thumb-wrap">
              <span v-if="req.media_url" class="dl-proof" role="button" tabindex="0" :aria-label="`See the customer's proof for ${req.order?.order_number ?? 'this order'}`" @click.stop="openMediaModal(req)" @keydown.enter.stop.prevent="openMediaModal(req)">
                <video v-if="req.media_type === 'video'" :src="req.media_url" class="dl-thumb" muted></video>
                <img v-else :src="req.media_url" :alt="`${req.type} proof`" class="dl-thumb" loading="lazy" />
                <span v-if="req.media_type === 'video'" class="dl-proof-play" v-html="ICONS.play"></span>
              </span>
              <span v-else class="dl-thumb blank" v-html="ICONS.restore"></span>
            </span>
            <span class="dl-text">
              <span class="dl-top">
                <strong>{{ req.customer?.name ?? "Customer" }}</strong>
                <span class="dl-num">{{ req.order?.order_number ?? "—" }}</span>
                <span class="sp-chip" :class="req.type === 'refund' ? 'blue' : 'violet'">{{ req.type === "refund" ? "Refund" : "Return" }}</span>
              </span>
              <span class="dl-items" :title="req.reason">“{{ req.reason || "No reason given" }}”</span>
              <span class="dl-meta">
                <span>Asked {{ formatTime(req.created_at) }}</span>
                <span v-if="req.settled" class="dl-agreed">Agreed: {{ describeResolution(req.settled.resolution, req.settled.amount) }}</span>
                <span v-else-if="req.offer">{{ req.offer.by === "vendor" ? "You offered" : "They asked for" }} {{ describeResolution(req.offer.resolution, req.offer.amount) }}</span>
                <span v-if="!req.media_url">No proof attached</span>
              </span>
            </span>
          </button>
          <div class="dl-side">
            <div class="dl-money">
              <strong>₱{{ Number(req.order?.total_amount ?? 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</strong>
              <span>order total</span>
            </div>
            <div class="dl-req-state">
              <span class="sp-chip" :class="REQ_TONE[req.status] ?? 'slate'">{{ CASE_STAGE_LABEL[req.stage] ?? req.status }}</span>
              <small v-if="req.next_actor === 'vendor'" class="dl-turn">Your turn</small>
              <small v-else-if="req.next_actor === 'customer'" class="dl-turn wait">Waiting for the customer</small>
            </div>
            <div class="dl-actions">
              <button type="button" class="sp-btn sp-btn-sm" :class="req.next_actor === 'vendor' ? 'sp-btn-primary' : 'sp-btn-ghost'" @click="openCase(req)">{{ req.next_actor === "vendor" ? "Respond" : "Open" }}</button>
            </div>
          </div>
        </li>
      </ul>
      <ShowMore v-if="requests.length" tone="vendor" :has-more="reqPager.hasMore.value" :loading="reqPager.loadingMore.value" :shown="requests.length" :total="reqPager.total.value" @more="reqPager.showMore()" />
    </section>

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
            <figure v-if="detailModal.delivery.photo_url" class="dm-photo">
              <img :src="detailModal.delivery.photo_url" alt="Photo of the order before delivery" />
              <figcaption>Photo before delivery<template v-if="detailModal.delivery.photo_taken_by"> · {{ detailModal.delivery.photo_taken_by }}</template><template v-if="detailModal.delivery.photo_taken_at"> · {{ formatTime(detailModal.delivery.photo_taken_at) }}</template>. The customer sees this on their order.</figcaption>
            </figure>
            <div class="dm-meta-grid">
              <div class="dm-meta-item">
                <span class="dm-meta-label">Delivery ID</span
                ><span class="dm-meta-val mono">{{
                  detailModal.delivery.delivery_id
                }}</span>
              </div>
              <div class="dm-meta-item">
                <span class="dm-meta-label">Barcode</span>
                <button type="button" class="dm-barcode-btn" @click="barcodeModalRef.open(detailModal.delivery.order?.id, detailModal.delivery.delivery_id)">
                  <span class="mono">{{ detailModal.delivery.barcode || detailModal.delivery.delivery_id }}</span> · Show barcode
                </button>
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
            <!-- A custom 3D bouquet: what to make, and the design in 3D -->
            <section v-if="detailBouquet" class="dm-bouquet" aria-labelledby="dm-bouquet-title">
              <div class="dm-bouquet-head">
                <div>
                  <span class="dm-bouquet-tag">Custom 3D bouquet</span>
                  <h3 id="dm-bouquet-title">
                    {{ detailRecipe.flowerTotal }} flower{{ detailRecipe.flowerTotal === 1 ? "" : "s" }}<template v-if="detailRecipe.occasion"> · {{ detailRecipe.occasion }}</template>
                  </h3>
                </div>
                <button type="button" class="dm-bouquet-btn" @click="openBouquet3d">
                  <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="15" aria-hidden="true"><path d="M10 2l7 4v8l-7 4-7-4V6z" /><path d="M10 2v14M3 6l7 4 7-4" /></svg>
                  View 3D bouquet
                </button>
              </div>
              <dl class="dm-recipe">
                <div>
                  <dt>Flowers</dt>
                  <dd>
                    <span v-for="f in detailRecipe.flowers" :key="f.name" class="dm-recipe-chip">{{ f.count }} × {{ f.name }}</span>
                  </dd>
                </div>
                <div v-if="detailRecipe.wrapper">
                  <dt>Wrapper</dt>
                  <dd><span class="dm-swatch"><i :style="{ background: detailRecipe.wrapper.color }"></i>{{ detailRecipe.wrapper.name || "Wrapper" }}</span></dd>
                </div>
                <div v-if="detailRecipe.pattern">
                  <dt>Wrapper pattern</dt>
                  <dd>{{ detailRecipe.pattern }}</dd>
                </div>
                <div v-if="detailRecipe.innerWrapper">
                  <dt>Inner wrapper</dt>
                  <dd><span class="dm-swatch"><i :style="{ background: detailRecipe.innerWrapper.color }"></i>{{ detailRecipe.innerWrapper.name || "Inner wrapper" }}</span></dd>
                </div>
                <div v-if="detailRecipe.innerPattern">
                  <dt>Inner wrapper pattern</dt>
                  <dd>{{ detailRecipe.innerPattern }}</dd>
                </div>
                <div v-if="detailRecipe.ribbon">
                  <dt>Ribbon</dt>
                  <dd><span class="dm-swatch"><i :style="{ background: detailRecipe.ribbon.color }"></i>{{ detailRecipe.ribbon.name || "Ribbon" }}</span></dd>
                </div>
                <div v-if="detailRecipe.fillers.length">
                  <dt>Fillers</dt>
                  <dd>{{ detailRecipe.fillers.join(", ") }}</dd>
                </div>
              </dl>
            </section>

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
                  <div class="dm-item-name">
                    {{ item.product_name }}
                    <span v-if="isBouquetItem(item)" class="dm-recipe-chip">Custom 3D bouquet</span>
                  </div>
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
                    v-if="item.model_3d_url && !isBouquetItem(item)"
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
      <!-- A real 3D viewer (an iframe can't show a .glb file) -->
      <Order3DModal
        :model="model3d.open ? { title: model3d.name, url: model3d.url, type: 'glb', arrangement: model3d.arrangement || null } : null"
        @close="model3d.open = false"
      />
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
              <div class="bc-skeleton sk-line"></div><div class="bc-skeleton sk-line sk-short"></div>
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

    <!-- Next step -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="advanceModal.open" class="sp-backdrop" @click="closeAdvance"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="advanceModal.open" class="sp-modal-wrap">
          <form class="sp-modal dl-adv" role="dialog" aria-modal="true" aria-labelledby="dl-adv-title" novalidate @submit.prevent="confirmAdvance">
            <div class="sp-modal-head">
              <div>
                <h3 id="dl-adv-title">{{ advanceTitle }}</h3>
                <p>{{ advanceModal.delivery?.order?.order_number ?? advanceModal.delivery?.delivery_id }} · {{ advanceModal.delivery?.order?.delivery_contact_name || "Customer" }} · now <b>{{ STATUS_LABEL[advanceModal.delivery?.status] }}</b></p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeAdvance"><span v-html="ICONS.close"></span></button>
            </div>

            <!-- More than one way to go (after completion): pick one -->
            <div v-if="advanceOptions(advanceModal.delivery).length > 1" class="dl-adv-pick" role="radiogroup" aria-label="Move to">
              <label v-for="opt in advanceOptions(advanceModal.delivery)" :key="opt.value" class="dl-adv-opt" :class="{ on: advanceModal.target === opt.value }">
                <input v-model="advanceModal.target" type="radio" :value="opt.value" />{{ opt.label }}
              </label>
            </div>
            <p v-else class="dl-adv-text">{{ ADVANCE_TEXT[advanceModal.target] }}</p>

            <DeliveryPhotoPicker v-if="advanceModal.target === 'to_received'" v-model:file="advanceModal.photo" :existing-url="advanceModal.delivery?.photo_url || ''" :disabled="advancing" />

            <p v-if="advanceModal.error" class="sp-error" role="alert">{{ advanceModal.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" :disabled="advancing" @click="closeAdvance">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="advancing || !advanceModal.target">
                {{ advancing ? (advanceModal.photo ? "Uploading photo…" : "Saving…") : (NEXT_LABEL[advanceModal.target] ?? "Save") }}
              </button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

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

    <!-- ── Return / refund case ───────────────────────────────────────── -->
    <teleport to="body">
      <div v-if="caseModal.id" class="modal-backdrop" @click.self="closeCase">
        <div class="req-case-modal" role="dialog" aria-modal="true">
          <div class="req-case-head">
            <div>
              <h3>Return / refund · {{ caseModal.orderNumber }}</h3>
              <p v-if="caseModal.customer">{{ caseModal.customer }}</p>
            </div>
            <button class="modal-close" aria-label="Close" @click="closeCase">✕</button>
          </div>
          <ReturnCasePanel
            :case-id="caseModal.id"
            role="vendor"
            api-base="/sc/order-requests"
            @changed="fetchRequests(reqMeta.current_page, true)"
          />
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import ShowMore from "../../../../../components/common/ShowMore.vue";
import { useShowMorePaged } from "../../../../../composables/useShowMore";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { toast } from "@/utils/snackbar";
import { deliveryService } from "../../../../../services/deliveryService";
import ReturnCasePanel from "../../../../../components/returns/ReturnCasePanel.vue";
import DeliveryPhotoPicker from "../../../../../components/vendor/orders/DeliveryPhotoPicker.vue";
import BarcodeModal from "../../../../../layouts/components/BarcodeModal.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import Order3DModal from "../../../../../components/vendor/orders/Order3DModal.vue";
import { bouquetFromItems, bouquetRecipe, isBouquetItem } from "../../../../../components/vendor/orders/orderUi";
import "../../../../../components/vendor/shop/shop.css";

// ─── Constants ────────────────────────────────────────────────────────────────
const DAY_HEADERS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

// The same stages and words as the vendor's Orders page
const STATUS_LABEL = {
  pending: "Pending",
  to_processed: "Preparing",
  to_ship: "Ready",
  to_received: "Out for delivery",
  completed: "Completed",
  returned: "Returned",
  refunded: "Refunded",
};
const STATUS_TONE = { pending: "amber", to_processed: "blue", to_ship: "violet", to_received: "teal", completed: "green", returned: "red", refunded: "red" };
const PIPELINE = [
  { key: "pending", label: "Pending", hint: "Confirm them", tone: "amber" },
  { key: "to_processed", label: "Preparing", hint: "Being arranged", tone: "blue" },
  { key: "to_ship", label: "Ready", hint: "Photo, then send out", tone: "violet" },
  { key: "to_received", label: "Out for delivery", hint: "Customer confirms (or 3 days)", tone: "teal" },
  { key: "completed", label: "Completed", hint: "Customer has it", tone: "green" },
];
// The one next step from each stage
const NEXT = {
  pending: { value: "to_processed", label: "Start preparing" },
  to_processed: { value: "to_ship", label: "Mark ready" },
  to_ship: { value: "to_received", label: "Send out for delivery" },
  // Out for delivery: only the customer completes it, by marking it received
};
const NEXT_LABEL = { to_processed: "Start preparing", to_ship: "Mark ready", to_received: "Send out for delivery", completed: "Mark delivered", returned: "Mark returned", refunded: "Mark refunded" };
const ADVANCE_TEXT = {
  to_processed: "The order is confirmed and the shop starts arranging it.",
  to_ship: "The order is arranged and waiting for the rider.",
  to_received: "The order leaves the shop. Take a photo of it first: the customer sees it on their order.",
  completed: "The customer has the order.",
};
const TRANSITIONS = {
  pending: [{ value: "to_processed", label: "Preparing" }],
  to_processed: [{ value: "to_ship", label: "Ready" }],
  to_ship: [{ value: "to_received", label: "Out for delivery" }],
  to_received: [],
  completed: [
    { value: "returned", label: "Returned" },
    { value: "refunded", label: "Refunded" },
  ],
};
const TAB_EMPTY = { pending: "pending", to_processed: "being prepared", to_ship: "ready", to_received: "out for delivery", completed: "completed yet", returned: "returned", due_today: "due today", overdue: "late" };

const DUE_TILES = [
  { key: "due_today", label: "Due today", hint: "Going out today", empty: "Nothing due today", icon: "calendar", tone: "green" },
  { key: "overdue", label: "Overdue", hint: "Past their delivery date", empty: "Nothing late", icon: "alert", tone: "red" },
];
const DUE_FILTER = { due_today: "today", overdue: "overdue" };

const statusTabs = [
  { value: "all", label: "All" },
  { value: "due_today", label: "Due today" },
  { value: "overdue", label: "Overdue" },
  { value: "pending", label: "Pending" },
  { value: "to_processed", label: "Preparing" },
  { value: "to_ship", label: "Ready" },
  { value: "to_received", label: "Out for delivery" },
  { value: "completed", label: "Completed" },
  { value: "returned", label: "Returned" },
];

// NEW: request filter tabs
const reqTabs = [
  { value: "all", label: "All" },
  { value: "pending", label: "Agreeing" },
  { value: "approved", label: "In progress" },
  { value: "completed", label: "Settled" },
  { value: "rejected", label: "Declined or withdrawn" },
  { value: "return", label: "Returns" },
  { value: "refund", label: "Refunds" },
];
// Where returns and refunds stand, as cards like the order stages
const REQ_STAGES = [
  { key: "needs_shop", filter: "all", label: "Your turn", hint: "Waiting on the shop", tone: "amber" },
  { key: "pending", filter: "pending", label: "Agreeing", hint: "Settling what to do", tone: "blue" },
  { key: "approved", filter: "approved", label: "In progress", hint: "Refund or item on its way", tone: "violet" },
  { key: "completed", filter: "completed", label: "Settled", hint: "Customer confirmed", tone: "green" },
];
const REQ_TONE = { pending: "amber", approved: "blue", completed: "green", rejected: "red", withdrawn: "slate" };
function setReqFilter(value) {
  reqActiveFilter.value = value;
  reqTypeFilter.value = "";
  fetchRequests();
}
const CASE_STAGE_LABEL = {
  awaiting_vendor: "New request",
  awaiting_customer: "Offer sent",
  awaiting_return: "Item coming back",
  awaiting_refund: "Send refund",
  confirm_refund: "Refund sent",
  awaiting_replacement: "Send replacement",
  confirm_replacement: "Replacement sent",
  completed: "Settled",
  rejected: "Declined",
  withdrawn: "Withdrawn",
};
const reqCountFor = (value) =>
  ({ return: reqCounts.value.returns, refund: reqCounts.value.refunds })[value] ?? reqCounts.value[value] ?? 0;
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
const describeResolution = (resolution, amount) =>
  resolution === "replacement" ? "a replacement" : `${resolution === "full_refund" ? "full" : "partial"} refund ${peso.format(Number(amount || 0))}`;

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
  completed: 0,
  rejected: 0,
  needs_shop: 0,
  returns: 0,
  refunds: 0,
});

// Media preview modal (NEW)
const mediaModal = ref({
  open: false,
  url: null,
  mediaType: null,
  type: null,
  orderNumber: "",
});

// Return / refund case being viewed
const caseModal = ref({ id: null, orderNumber: "", customer: "" });
function openCase(req) {
  caseModal.value = { id: req.id, orderNumber: req.order?.order_number ?? "—", customer: req.customer?.name ?? "" };
}
function closeCase() {
  caseModal.value = { id: null, orderNumber: "", customer: "" };
}

// ─── Existing modals ──────────────────────────────────────────────────────────
const logsModal = ref({
  open: false,
  delivery: null,
  logs: [],
  loading: false,
});
const advanceModal = ref({ open: false, delivery: null, target: "", photo: null, error: "" });
const detailModal = ref({ open: false, delivery: null });
const model3d = ref({ open: false, url: null, name: "" });
const advancing = ref(false);
const barcodeModalRef = ref(null);

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
      // Not while the next rows are still loading as they scroll
      if (!reqPager.loadingMore.value) fetchRequests(reqMeta.value.current_page, true);
    } else {
      if (!orderPager.loadingMore.value) fetchOrders(meta.value.current_page, true);
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

/** One page of deliveries from the server, for the scrolling list */
async function fetchOrderPage(page) {
  {
    const params = { page, per_page: 20 };
    if (DUE_FILTER[activeTab.value]) params.due = DUE_FILTER[activeTab.value];
    else if (activeTab.value !== "all") params.status = activeTab.value;
    if (search.value) params.search = search.value;
    const res = await deliveryService.scOrders(params);
    let raw = [];
    if (Array.isArray(res)) raw = res;
    else if (Array.isArray(res?.data?.data)) raw = res.data.data;
    else if (Array.isArray(res?.data)) raw = res.data;
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
      due_today: sc.due_today ?? 0,
      overdue: sc.overdue ?? 0,
    };
    lastRefreshed.value = new Date().toLocaleTimeString();
    return { items: raw.filter(Boolean), lastPage: meta.value.last_page ?? 1, total: meta.value.total };
  }
}
const orderPager = useShowMorePaged(fetchOrderPage);
watch(() => orderPager.visible.value, (rows) => (orders.value = rows));

// The first rows again (a new tab or search), or a quiet refresh that keeps
// as many rows showing as before (polling, after a change)
async function fetchOrders(_page = 1, silent = false) {
  if (!silent) loading.value = true;
  try {
    if (silent) await orderPager.refresh();
    else await orderPager.load();
    if (!silent && orderPager.failure.value) throw orderPager.failure.value;
  } catch (err) {
    if (!silent)
      toast.error(apiMessage(err, "Couldn't load the deliveries."), { autoClose: 6000 });
  } finally {
    loading.value = false;
  }
}

// ─── Requests fetch (NEW) ─────────────────────────────────────────────────────
/** One page of return and refund requests, for the scrolling list */
async function fetchRequestPage(page) {
  {
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

    if (body?.meta) Object.assign(reqMeta.value, body.meta);
    if (body?.counts) Object.assign(reqCounts.value, body.counts);
    return {
      items: items.map((r) => ({ ...r, status: r.status ?? "pending", type: r.type ?? "return" })),
      lastPage: reqMeta.value.last_page ?? 1,
      total: reqMeta.value.total,
    };
  }
}
const reqPager = useShowMorePaged(fetchRequestPage);
watch(() => reqPager.visible.value, (rows) => (requests.value = rows));

async function fetchRequests(_page = 1, silent = false) {
  if (!silent) reqLoading.value = true;
  try {
    if (silent) await reqPager.refresh();
    else await reqPager.load();
    if (!silent && reqPager.failure.value) throw reqPager.failure.value;
  } catch (err) {
    if (!silent)
      toast.error(apiMessage(err, "Couldn't load the requests."), { autoClose: 6000 });
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
  model3d.value = { open: true, url: new URL(url, window.location.origin).href, name: name || "3D Model", arrangement: null };
}

// The custom 3D bouquet in the open order, and what staff need to make it
const detailBouquet = computed(() => bouquetFromItems(detailModal.value.delivery?.order?.items ?? []));
const detailRecipe = computed(() => bouquetRecipe(detailBouquet.value));
function openBouquet3d() {
  model3d.value = { open: true, url: "/Boquet.glb", name: "Custom bouquet", arrangement: detailBouquet.value };
}
async function openLogs(delivery) {
  logsModal.value = { open: true, delivery, logs: [], loading: true };
  try {
    const res = await deliveryService.getLogs(delivery.id);
    logsModal.value.logs = res?.data?.logs ?? [];
  } catch {
    toast.error("Failed to load logs.", {
      position: toast.POSITION.TOP_RIGHT,
      autoClose: 4000,
    });
  } finally {
    logsModal.value.loading = false;
  }
}
function openAdvance(delivery, target = "") {
  const options = TRANSITIONS[delivery?.status] ?? [];
  advanceModal.value = { open: true, delivery, target: target || (options.length === 1 ? options[0].value : ""), photo: null, error: "" };
}
function closeAdvance() {
  if (!advancing.value) advanceModal.value.open = false;
}
const advanceTitle = computed(() => {
  const t = advanceModal.value.target;
  return t ? `${NEXT_LABEL[t] ?? "Move on"}?` : "Move the order on";
});
// "Out for delivery" needs a photo of the order: uploaded first, then the step
async function confirmAdvance() {
  const m = advanceModal.value;
  m.error = "";
  if (!m.target) return;
  if (m.target === "to_received" && !m.photo && !m.delivery?.photo_url) {
    m.error = "Take or choose a photo of the order first. The customer sees it on their order.";
    return;
  }
  if (m.photo) {
    advancing.value = true;
    try {
      const res = await deliveryService.uploadPhoto(m.delivery.id, m.photo);
      m.delivery = res.data.delivery ?? m.delivery;
      m.photo = null;
    } catch (e) {
      m.error = e?.response?.data?.errors?.photo?.[0] ?? e?.response?.data?.message ?? "The photo didn't upload. Try again.";
      advancing.value = false;
      return;
    }
    advancing.value = false;
  }
  await doAdvance(m.target);
}
function setStatusTab(key) {
  activeTab.value = key;
  fetchOrders();
}
// Days past the delivery date for an order still on its way (0 = due today, -1 = not due yet)
function lateDays(d) {
  if (["completed", "returned", "refunded"].includes(d.status)) return -1;
  const date = safeDate(d.order?.reservation_date);
  if (!date) return -1;
  const days = Math.round((new Date(new Date().toDateString()) - new Date(`${date}T00:00:00`)) / 86400000);
  return days >= 0 ? days : -1;
}
const itemNames = (d) => (d.order?.items ?? []).map((i) => i.product_name).filter(Boolean).join(", ");
const firstImage = (d) => (d.order?.items ?? []).find((i) => i.product_image)?.product_image ?? "";
function dayText(ymd) {
  const date = safeDate(ymd);
  if (!date) return "";
  const days = Math.round((new Date(`${date}T00:00:00`) - new Date(new Date().toDateString())) / 86400000);
  if (days === 0) return "Today";
  if (days === 1) return "Tomorrow";
  if (days === -1) return "Yesterday";
  return new Date(`${date}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric" });
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
    const body = res?.data ?? res;
    if (body?.success === false || !body?.delivery) {
      throw new Error(body?.message ?? "Update failed.");
    }

    const updatedDelivery = body.delivery;
    const previousStatus = advanceModal.value.delivery.status;
    const idx = orders.value.findIndex(
      (o) => o.id === advanceModal.value.delivery.id,
    );
    if (idx !== -1) {
      if (
        activeTab.value === "all" ||
        activeTab.value === updatedDelivery.status
      ) {
        orders.value.splice(idx, 1, updatedDelivery);
      } else {
        orders.value.splice(idx, 1);
      }
    }

    if (previousStatus !== updatedDelivery.status) {
      counts.value = {
        ...counts.value,
        [previousStatus]: Math.max(0, (counts.value[previousStatus] ?? 0) - 1),
        [updatedDelivery.status]: (counts.value[updatedDelivery.status] ?? 0) + 1,
      };
    }

    if (detailModal.value.delivery?.id === updatedDelivery.id) {
      detailModal.value.delivery = updatedDelivery;
    }
    advanceModal.value.open = false;
    toast.success("Status updated.", {
      position: toast.POSITION.TOP_RIGHT,
      autoClose: 3000,
    });
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
.sk-line {
  height: 12px;
  margin: 6px auto;
  border-radius: 6px;
  width: 60%;
}
.sk-short {
  width: 35%;
}
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

/* ── Stages, like the vendor's Orders page ─────────────────────── */
/* Due today and Overdue */
.dl-due {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.dl-due-tile {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
  background: var(--v-surface);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.dl-due-tile:hover,
.dl-due-tile.on {
  border-color: var(--tone);
  box-shadow: 0 4px 14px rgba(16, 24, 40, 0.06);
}
.dl-due-tile.on,
.dl-due-tile.hot {
  background: var(--tone-soft);
}
.dl-due-tile.hot {
  border-color: var(--tone);
}
.dl-due-tile:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.dl-due-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--tone-soft);
  color: var(--tone-ink);
}
.dl-due-tile.hot .dl-due-icon,
.dl-due-tile.on .dl-due-icon {
  background: var(--v-surface);
}
.dl-due-icon :deep(svg) {
  width: 20px;
  height: 20px;
}
.dl-due-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.dl-due-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--tone-ink);
}
.dl-due-hint {
  font-size: 12px;
  color: var(--v-muted);
}
.dl-due-tile strong {
  font-size: 30px;
  font-weight: 600;
  line-height: 1;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.dl-due-tile.zero strong {
  color: var(--v-faint);
}
.tone-red { --tone: #fc8181; --tone-soft: var(--v-red-soft); --tone-ink: var(--v-red-text); }
.dl-pipe {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
}
.dl-pipe-step {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  padding: 14px 16px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
  background: var(--v-surface);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.dl-pipe-step:hover,
.dl-pipe-step.on {
  border-color: var(--tone);
  box-shadow: 0 4px 14px rgba(16, 24, 40, 0.06);
}
.dl-pipe-step.on {
  background: var(--tone-soft);
}
.dl-pipe-step:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.tone-amber { --tone: #f6ad55; --tone-soft: var(--v-amber-soft); --tone-ink: var(--v-amber-text); }
.tone-blue { --tone: #63b3ed; --tone-soft: var(--v-blue-soft); --tone-ink: var(--v-blue-text); }
.tone-violet { --tone: #b794f4; --tone-soft: var(--v-purple-soft); --tone-ink: var(--v-purple-text); }
.tone-teal { --tone: #4fd1c5; --tone-soft: var(--v-teal-soft); --tone-ink: var(--v-teal-text); }
.tone-green { --tone: #68d391; --tone-soft: var(--v-green-soft); --tone-ink: var(--v-green-text); }
.dl-pipe--req {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.dl-row--turn {
  box-shadow: inset 3px 0 0 var(--v-amber-text);
}
.dl-proof {
  position: relative;
  display: block;
  cursor: zoom-in;
  border-radius: 12px;
}
.dl-proof:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.dl-proof-play {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  color: #fff;
}
.dl-proof-play :deep(svg) {
  width: 20px;
  height: 20px;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.5));
}
.dl-agreed {
  color: var(--v-green-text);
  font-weight: 500;
}
.dl-req-state {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
}
.dl-turn {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-amber-text);
}
.dl-turn.wait {
  font-weight: 400;
  color: var(--v-muted);
}
.dl-wait {
  padding: 6px 10px;
  border-radius: 8px;
  background: var(--v-subtle);
  font-size: 12px;
  color: var(--v-muted);
  white-space: nowrap;
}
.dl-pipe-label {
  display: flex;
  align-items: center;
  gap: 7px;
  overflow: hidden;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--tone-ink);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.dl-pipe-dot {
  flex: none;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--tone);
}
.dl-pipe-step strong {
  font-size: 24px;
  font-weight: 600;
  line-height: 1.2;
  color: var(--v-text);
}
.dl-pipe-hint {
  font-size: 11.5px;
  color: var(--v-faint);
}
.dl-pipe-arrow {
  position: absolute;
  right: -15px;
  top: 50%;
  z-index: 1;
  display: grid;
  place-items: center;
  width: 18px;
  height: 18px;
  transform: translateY(-50%);
  border-radius: 50%;
  background: var(--v-subtle);
  color: var(--v-faint);
}
.dl-pipe-arrow :deep(svg) {
  width: 14px;
  height: 14px;
}
.dl-tabs {
  max-width: 100%;
  overflow-x: auto;
  scrollbar-width: none;
}
.dl-tabs .sp-seg {
  flex-wrap: nowrap;
}
.dl-tabs .sp-seg button {
  white-space: nowrap;
}

/* ── Delivery list ─────────────────────────────────────────────── */
.dl-list-card {
  padding: 6px;
}
.dl-list {
  margin: 0;
  padding: 0;
  list-style: none;
  transition: opacity 0.2s;
}
.dl-list.dim {
  opacity: 0.55;
}
.dl-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px;
  border-radius: 14px;
}
.dl-row + .dl-row {
  border-top: 1px solid var(--v-border);
}
.dl-row:hover {
  background: var(--v-subtle);
}
.dl-main {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.dl-main:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 4px;
  border-radius: 10px;
}
.dl-thumb-wrap {
  position: relative;
  flex: none;
}
.dl-thumb {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 12px;
  object-fit: cover;
  background: var(--v-subtle);
}
.dl-thumb.blank {
  color: var(--v-faint);
}
.dl-thumb.blank :deep(svg) {
  width: 24px;
  height: 24px;
}
.dl-thumb-count {
  position: absolute;
  right: -6px;
  bottom: -6px;
  padding: 1px 6px;
  border: 2px solid var(--v-surface);
  border-radius: 999px;
  background: var(--v-text);
  color: var(--v-surface);
  font-size: 10.5px;
  font-weight: 600;
}
.dl-grow {
  flex: 1;
  min-width: 0;
}
.dl-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.dl-top {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px;
}
.dl-top strong {
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
}
.dl-num {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12px;
  color: var(--v-muted);
}
.dl-code {
  padding: 1px 7px;
  border-radius: 6px;
  background: var(--v-subtle);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 11px;
  color: var(--v-muted);
}
.dm-barcode-btn {
  padding: 0;
  border: 0;
  background: none;
  color: var(--bc-accent-deep);
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}
.dm-barcode-btn:hover {
  text-decoration: underline;
}
.dl-items {
  overflow: hidden;
  font-size: 13px;
  color: var(--v-text-2);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.dl-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 12px;
  font-size: 12px;
  color: var(--v-muted);
}
.dl-meta .sp-chip :deep(svg),
.dl-date :deep(svg) {
  width: 13px;
  height: 13px;
}
.dl-meta .sp-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.dl-date {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--v-text-2);
  font-weight: 500;
}
.dl-updated {
  color: var(--v-faint);
}
.dl-side {
  flex: none;
  display: flex;
  align-items: center;
  gap: 14px;
}
.dl-money {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 110px;
  text-align: right;
}
.dl-money strong {
  font-size: 14px;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.dl-money span {
  font-size: 11.5px;
  color: var(--v-muted);
}
.dl-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.dl-sk-btn {
  width: 110px;
  height: 34px;
  border-radius: 10px;
}

/* ── Photo in the order details ────────────────────────────────── */
.dm-photo {
  margin: 0 0 16px;
  overflow: hidden;
  border: 1px solid var(--v-border);
  border-radius: 14px;
}
.dm-photo img {
  display: block;
  width: 100%;
  max-height: 300px;
  object-fit: cover;
}
.dm-photo figcaption {
  padding: 8px 12px;
  font-size: 12px;
  color: var(--v-muted);
}

/* ── Next step window ──────────────────────────────────────────── */
.dl-adv {
  max-width: 480px;
}
.dl-adv-text {
  margin: 0 0 14px;
  font-size: 13.5px;
  line-height: 1.6;
  color: var(--v-text-2);
}
.dl-adv-pick {
  display: flex;
  gap: 8px;
  margin-bottom: 14px;
}
.dl-adv-opt {
  position: relative;
  display: inline-flex;
  align-items: center;
  min-height: 42px;
  padding: 0 16px;
  border: 1px solid var(--v-border-strong);
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  color: var(--v-text);
  cursor: pointer;
}
.dl-adv-opt input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.dl-adv-opt.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-weight: 600;
}
.dl-adv-opt:has(input:focus-visible) {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.dl-adv :deep(.dpp) {
  margin-bottom: 12px;
}

@media (max-width: 1280px) {
  .dl-pipe {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  .dl-pipe-arrow {
    display: none;
  }
}
@media (max-width: 1100px) {
  .dl-pipe {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .dl-pipe-arrow {
    display: none;
  }
}
@media (max-width: 560px) {
  .dl-due {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 760px) {
  .dl-row {
    flex-wrap: wrap;
  }
  .dl-side {
    width: 100%;
    flex-wrap: wrap;
    justify-content: space-between;
    padding-left: 70px;
  }
  .dl-money {
    align-items: flex-start;
    text-align: left;
  }
}
@media (prefers-reduced-motion: reduce) {
  .dl-pipe-step,
  .dl-list {
    transition: none;
  }
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}
.page-title {
  font-size: 22px;
  font-weight: 700;
  color: var(--v-text);
  margin: 0;
}
.page-sub {
  font-size: 13px;
  color: var(--v-muted);
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
  background: var(--v-green-soft);
  border: 1px solid #bbf7d0;
  border-radius: 20px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--v-green-text);
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
  border: 1px solid var(--v-border);
  background: var(--v-surface);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--v-muted);
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
  background: var(--v-surface);
  border: 1.5px solid var(--v-border);
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
  background: var(--v-ink);
  color: var(--v-ink-text);
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
  color: var(--v-faint);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.kpi-num {
  font-size: 20px;
  font-weight: 800;
  color: var(--v-text);
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
  background: var(--v-surface);
  border: 1px solid var(--v-border);
  border-radius: 9px;
  min-width: 280px;
}
.search-box input {
  border: none;
  background: none;
  outline: none;
  font-size: 13px;
  color: var(--v-text-2);
  flex: 1;
  font-family: "Poppins", sans-serif;
}
.toolbar-note {
  font-size: 12px;
  color: var(--v-faint);
}

.card {
  background: var(--v-surface);
  border: 1px solid var(--v-border);
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
  background: var(--v-subtle);
}
.data-table th {
  padding: 10px 16px;
  text-align: left;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-bottom: 1px solid var(--v-border);
  white-space: nowrap;
}
.data-row td {
  padding: 13px 16px;
  border-bottom: 1px solid var(--v-border);
  vertical-align: middle;
}
.data-row:last-child td {
  border-bottom: none;
}
.data-row:hover td {
  background: var(--v-subtle);
}
.loading-row,
.empty-row {
  text-align: center;
  padding: 48px 16px !important;
  color: var(--v-faint);
}
.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid var(--v-border);
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
  color: var(--v-text);
  font-size: 13px;
}
.barcode-text {
  font-family: monospace;
  font-size: 11px;
  color: var(--v-faint);
}
.order-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.order-ref {
  font-weight: 600;
  color: var(--v-text-2);
}
.order-amount {
  font-size: 12px;
  color: var(--v-muted);
}
.items-count {
  font-size: 12.5px;
  color: var(--v-muted);
}
.muted-cell {
  color: var(--v-muted);
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
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.status-badge.to_processed {
  background: var(--v-blue-soft);
  color: var(--v-blue-text);
}
.status-badge.to_ship {
  background: var(--v-blue-soft);
  color: var(--v-blue-text);
}
.status-badge.to_received {
  background: var(--v-purple-soft);
  color: var(--v-purple-text);
}
.status-badge.completed {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.status-badge.returned {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.status-badge.refunded {
  background: var(--v-subtle);
  color: var(--v-text-2);
}
.status-badge.processing {
  background: var(--v-blue-soft);
  color: var(--v-blue-text);
}
.status-badge.shipped {
  background: var(--v-purple-soft);
  color: var(--v-purple-text);
}
.status-badge.cancelled {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.status-badge.out_for_delivery {
  background: var(--v-pink-soft);
  color: var(--v-pink-text);
}

.action-cell {
  display: flex;
  gap: 6px;
}
.action-btn {
  width: 30px;
  height: 30px;
  border-radius: 7px;
  border: 1px solid var(--v-border);
  background: var(--v-surface);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--v-muted);
  transition: all 0.18s;
}
.detail-btn:hover {
  border-color: #93c5fd;
  color: var(--v-blue-text);
  background: var(--v-blue-soft);
}
.barcode-btn:hover {
  border-color: #c4b5fd;
  color: var(--v-purple-text);
  background: var(--v-purple-soft);
}
.logs-btn:hover {
  border-color: #fcd34d;
  color: var(--v-amber-text);
  background: var(--v-amber-soft);
}
.advance-btn:hover {
  border-color: #6ee7b7;
  color: var(--v-green-text);
  background: var(--v-green-soft);
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 12px 20px;
  border-top: 1px solid var(--v-border);
}
.page-btn {
  width: 30px;
  height: 30px;
  border-radius: 7px;
  border: 1px solid var(--v-border);
  background: var(--v-surface);
  cursor: pointer;
  font-size: 16px;
  color: var(--v-text-2);
}
.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.page-info {
  font-size: 13px;
  color: var(--v-muted);
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
  background: var(--v-surface);
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
  border-bottom: 1px solid var(--v-border);
  flex-shrink: 0;
}
.modal-header h2 {
  font-size: 15px;
  font-weight: 700;
  color: var(--v-text);
  margin: 0;
}
.modal-close {
  background: none;
  border: none;
  font-size: 18px;
  color: var(--v-faint);
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
  color: var(--v-faint);
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
  background: var(--v-subtle);
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
  background: var(--v-amber-soft);
  border: 2px solid #f59e0b;
}
.tl-dot.to_processed {
  background: var(--v-blue-soft);
  border: 2px solid #3b82f6;
}
.tl-dot.to_ship {
  background: var(--v-blue-soft);
  border: 2px solid #6366f1;
}
.tl-dot.to_received {
  background: var(--v-purple-soft);
  border: 2px solid #8b5cf6;
}
.tl-dot.completed {
  background: var(--v-green-soft);
  border: 2px solid #10b981;
}
.tl-dot.returned {
  background: var(--v-red-soft);
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
  color: var(--v-faint);
}
.advance-body {
  padding: 22px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.advance-hint {
  font-size: 13.5px;
  color: var(--v-text-2);
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
  background: var(--v-blue-soft);
  color: var(--v-blue-text);
}
.advance-opt-btn.to_ship {
  background: var(--v-blue-soft);
  color: var(--v-blue-text);
}
.advance-opt-btn.to_received {
  background: var(--v-purple-soft);
  color: var(--v-purple-text);
}
.advance-opt-btn.completed {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.advance-opt-btn.returned {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.advance-opt-btn.refunded {
  background: var(--v-subtle);
  color: var(--v-text-2);
}

/* View toggle */
.view-toggle {
  display: flex;
  border: 1px solid var(--v-border);
  border-radius: 9px;
  overflow: hidden;
}
.view-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 13px;
  background: var(--v-surface);
  border: none;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-muted);
  cursor: pointer;
  transition: all 0.14s;
  font-family: "Poppins", sans-serif;
  position: relative;
}
.view-btn + .view-btn {
  border-left: 1px solid var(--v-border);
}
.view-btn.active {
  background: #10b981;
  color: #fff;
}
.view-btn:hover:not(.active) {
  background: var(--v-subtle);
  color: var(--v-text-2);
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
  background: var(--v-surface);
  border: 1px solid var(--v-border);
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
  border: 1px solid var(--v-border);
  background: var(--v-surface);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--v-text-2);
  transition: all 0.14s;
}
.cal-nav-btn:hover {
  border-color: #10b981;
  color: #10b981;
}
.cal-month-label {
  font-size: 16px;
  font-weight: 700;
  color: var(--v-text);
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
  color: var(--v-muted);
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
  border: 1px solid var(--v-border);
  background: var(--v-surface);
  font-size: 12.5px;
  font-weight: 600;
  color: var(--v-text-2);
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
  color: var(--v-faint);
  padding: 6px 0;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.cal-cell {
  min-height: 88px;
  border: 1.5px solid var(--v-border);
  border-radius: 9px;
  padding: 6px 5px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  background: var(--v-surface);
  transition:
    border-color 0.12s,
    background 0.12s;
  cursor: pointer;
}
.cal-cell:hover:not(.cal-other-month) {
  border-color: #a7f3d0;
  background: var(--v-green-soft);
}
.cal-cell.cal-other-month {
  opacity: 0.28;
  cursor: default;
  pointer-events: none;
}
.cal-cell.cal-today {
  border-color: #10b981;
  background: var(--v-green-soft);
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
  color: var(--v-text-2);
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
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.cal-pill--created {
  background: var(--v-blue-soft);
  color: var(--v-blue-text);
}
.cal-pill--delivered {
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.cal-pill-more {
  font-size: 10px;
  color: var(--v-faint);
  padding: 1px 4px;
}
.cal-panel {
  border-top: 1px solid var(--v-border);
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
  color: var(--v-text);
  margin: 0;
}
.cal-panel-count {
  padding: 3px 10px;
  border-radius: 20px;
  background: var(--v-green-soft);
  border: 1px solid #bbf7d0;
  font-size: 12px;
  font-weight: 600;
  color: var(--v-green-text);
}
.cal-panel-empty {
  text-align: center;
  color: var(--v-faint);
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
  border: 1px solid var(--v-border);
  border-radius: 10px;
  background: var(--v-subtle);
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
  color: var(--v-text);
}
.coc-sub {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-top: 4px;
  font-size: 12px;
  color: var(--v-muted);
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
  color: var(--v-text-2);
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.coc-contact {
  font-size: 12px;
  color: var(--v-faint);
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
  color: var(--v-text);
}
.date-type-pill {
  font-size: 10.5px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
}
.dtp--reservation {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.dtp--created {
  background: var(--v-blue-soft);
  color: var(--v-blue-text);
}
.dtp--delivered {
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
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
  background: var(--v-surface);
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
  background: var(--v-subtle);
  border: 1px solid var(--v-border);
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
  color: var(--v-faint);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.dm-meta-val {
  font-size: 13px;
  color: var(--v-text);
  font-weight: 500;
}
.dm-meta-val.mono {
  font-family: monospace;
  font-size: 12px;
}
.dm-meta-val.bold {
  font-weight: 700;
}
/* Custom 3D bouquet: what to make */
.dm-bouquet {
  display: grid;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-purple-soft);
}
.dm-bouquet-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}
.dm-bouquet-tag {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--v-purple-text);
}
.dm-bouquet h3 {
  margin: 2px 0 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--v-text);
}
.dm-bouquet-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 36px;
  padding: 0 14px;
  border: 0;
  border-radius: 10px;
  background: var(--v-ink);
  color: var(--v-ink-text);
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.dm-bouquet-btn:focus-visible {
  outline: 2px solid var(--v-purple-text);
  outline-offset: 2px;
}
.dm-recipe {
  display: grid;
  gap: 8px;
  margin: 0;
}
.dm-recipe > div {
  display: grid;
  grid-template-columns: 110px minmax(0, 1fr);
  gap: 10px;
  align-items: start;
}
.dm-recipe dt {
  font-size: 12px;
  color: var(--v-muted);
}
.dm-recipe dd {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0;
  font-size: 13px;
  color: var(--v-text);
}
.dm-recipe-chip {
  display: inline-flex;
  align-items: center;
  padding: 2px 9px;
  border-radius: 999px;
  background: var(--v-surface);
  border: 1px solid var(--v-border);
  font-size: 12px;
  font-weight: 500;
  color: var(--v-text);
}
.dm-item-name .dm-recipe-chip {
  margin-left: 6px;
  color: var(--v-purple-text);
}
.dm-swatch {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.dm-swatch i {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  border: 1px solid var(--v-border-strong);
}
@media (max-width: 560px) {
  .dm-recipe > div {
    grid-template-columns: 1fr;
    gap: 2px;
  }
}

.dm-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: var(--v-text-2);
}
.dm-item-count {
  background: var(--v-green-soft);
  border: 1px solid #bbf7d0;
  color: var(--v-green-text);
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
  color: var(--v-faint);
  font-size: 13px;
  padding: 20px;
}
.dm-item {
  display: flex;
  gap: 14px;
  align-items: flex-start;
  padding: 14px;
  border: 1px solid var(--v-border);
  border-radius: 10px;
  background: var(--v-subtle);
}
.dm-item-img-wrap {
  width: 72px;
  height: 72px;
  border-radius: 8px;
  overflow: hidden;
  flex-shrink: 0;
  border: 1px solid var(--v-border);
  background: var(--v-subtle);
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
  color: var(--v-text);
}
.dm-item-desc {
  font-size: 12px;
  color: var(--v-muted);
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
  background: var(--v-subtle);
  color: var(--v-text-2);
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
  color: var(--v-muted);
}
.dm-item-unit {
  font-size: 11.5px;
  color: var(--v-faint);
}
.dm-item-sub {
  font-size: 14px;
  font-weight: 700;
  color: var(--v-text);
}
.dm-totals {
  border-top: 1px solid var(--v-border);
  padding-top: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.dm-total-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: var(--v-muted);
}
.dm-total-grand {
  font-size: 15px;
  font-weight: 700;
  color: var(--v-text);
  border-top: 1px solid var(--v-border);
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
  background: var(--v-surface);
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
  background: var(--v-subtle);
  border-top: 1px solid var(--v-border);
  border-radius: 0 0 14px 14px;
  font-size: 12px;
  color: var(--v-faint);
}
.dm-3d-download {
  font-size: 12px;
  font-weight: 600;
  color: var(--v-purple-text);
  text-decoration: none;
  padding: 3px 10px;
  border-radius: 8px;
  background: var(--v-purple-soft);
  border: 1px solid #ddd6fe;
  transition: background 0.12s;
}
.dm-3d-download:hover {
  background: var(--v-purple-soft);
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
  border-bottom: 1px solid var(--v-border);
}
.req-card-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--v-text);
  margin: 0;
}
.req-card-sub {
  font-size: 12.5px;
  color: var(--v-faint);
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
  border: 1px solid var(--v-border);
  background: var(--v-surface);
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-muted);
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
  color: var(--v-text);
}
.req-customer-email {
  font-size: 11.5px;
  color: var(--v-faint);
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
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.req-type--refund {
  background: var(--v-blue-soft);
  color: var(--v-blue-text);
}

/* Reason */
.req-reason {
  font-size: 12.5px;
  color: var(--v-text-2);
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
  border: 1px solid var(--v-border);
  background: var(--v-subtle);
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
  color: var(--v-faint);
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
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.req-status--approved {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.req-status--rejected {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}

.req-admin-note {
  font-size: 11px;
  color: var(--v-faint);
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
  background: var(--v-green-soft);
  color: var(--v-green-text);
  border: 1px solid #bbf7d0;
}
.req-approve-btn:hover:not(:disabled) {
  background: #10b981;
  color: #fff;
  border-color: #10b981;
}
.req-reject-btn {
  background: var(--v-red-soft);
  color: var(--v-red-text);
  border: 1px solid #fecaca;
}
.req-reject-btn:hover:not(:disabled) {
  background: #ef4444;
  color: #fff;
  border-color: #ef4444;
}

.req-resolved-note {
  font-size: 12.5px;
  color: var(--v-faint);
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
  color: var(--v-faint);
}

/* Media proof modal */
.media-modal {
  background: var(--v-surface);
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
  background: var(--v-subtle);
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
  border-top: 1px solid var(--v-border);
}

/* Approve / Reject confirm modal */
.req-confirm-modal {
  background: var(--v-surface);
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
  color: var(--v-text-2);
  margin: 0;
  flex-wrap: wrap;
}
.req-confirm-reason {
  font-size: 13px;
  color: var(--v-muted);
  background: var(--v-subtle);
  border: 1px solid var(--v-border);
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
  color: var(--v-text-2);
}
.req-notes-textarea {
  width: 100%;
  padding: 9px 12px;
  background: var(--v-subtle);
  border: 1.5px solid var(--v-border);
  border-radius: 8px;
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  color: var(--v-text-2);
  resize: vertical;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}
.req-notes-textarea:focus {
  border-color: #10b981;
  background: var(--v-input);
}
.req-confirm-footer {
  display: flex;
  gap: 10px;
  padding: 16px 22px;
  border-top: 1px solid var(--v-border);
  justify-content: flex-end;
}
.action-btn-ghost {
  padding: 9px 18px;
  border-radius: 8px;
  border: 1px solid var(--v-border);
  background: var(--v-surface);
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  font-weight: 600;
  color: var(--v-muted);
  cursor: pointer;
  transition: background 0.15s;
}
.action-btn-ghost:hover:not(:disabled) {
  background: var(--v-subtle);
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

/* Return / refund case */
.req-case-modal {
  width: min(760px, calc(100vw - 32px));
  max-height: calc(100vh - 48px);
  overflow-y: auto;
  padding: 22px;
  border-radius: 16px;
  background: var(--v-surface);
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
  --return-accent: #4f46e5;
  --return-accent-soft: #eef2ff;
}
.req-case-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 14px;
}
.req-case-head h3 {
  margin: 0;
  font-size: 16px;
}
.req-case-head p {
  margin: 2px 0 0;
  font-size: 12.5px;
  color: var(--v-muted);
}
.req-turn {
  margin-top: 4px;
  font-size: 11px;
  font-weight: 700;
  color: var(--v-amber-text);
}
.req-turn--wait {
  color: var(--v-muted);
  font-weight: 500;
}
.req-status--completed {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.req-status--withdrawn {
  background: var(--v-subtle);
  color: var(--v-muted);
}
</style>
