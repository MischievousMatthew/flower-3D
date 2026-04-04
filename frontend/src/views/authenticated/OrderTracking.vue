<template>
  <NavHeader
    ref="navHeaderRef"
    :cartCount="cartCount"
    :isAuthenticated="isAuthenticated"
    @scroll-to-section="scrollToSection"
  />
  <div class="ot-page">
    <!-- ── Sub-header ───────────────────────────────────────────────────────── -->
    <div class="ot-header">
      <div class="ot-header__inner">
        <div>
          <h1 class="ot-header__title">My Orders</h1>
          <p class="ot-header__sub">Track and manage your deliveries</p>
        </div>
        <div class="ot-search-wrap">
          <svg class="ot-search-ico" viewBox="0 0 20 20" fill="none">
            <circle
              cx="8.5"
              cy="8.5"
              r="5.5"
              stroke="currentColor"
              stroke-width="1.6"
            />
            <path
              d="M13 13l3.5 3.5"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
            />
          </svg>
          <input
            v-model="search"
            class="ot-search"
            placeholder="Search orders or stores…"
            @input="debounceSearch"
          />
        </div>
      </div>
      <div class="ot-tabs">
        <button
          v-for="tab in statusTabs"
          :key="tab.value"
          class="ot-tab"
          :class="{ active: activeTab === tab.value }"
          @click="setTab(tab.value)"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- ── Content ──────────────────────────────────────────────────────────── -->
    <div class="ot-content">
      <!-- Skeleton -->
      <div v-if="loading" class="ot-skeletons">
        <div v-for="n in 3" :key="n" class="ot-skel">
          <div class="ot-skel__top">
            <div class="ot-skel__line ot-skel__line--title"></div>
            <div class="ot-skel__line ot-skel__line--badge"></div>
          </div>
          <div class="ot-skel__row">
            <div v-for="s in 5" :key="s" class="ot-skel__step"></div>
          </div>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="ot-empty">
        <span class="ot-empty__ico">⚠️</span>
        <p class="ot-empty__title">Could not load orders</p>
        <p class="ot-empty__sub">{{ error }}</p>
        <button class="ot-btn ot-btn--green" @click="fetchOrders()">
          Try Again
        </button>
      </div>

      <!-- Empty -->
      <div v-else-if="!orders.length" class="ot-empty">
        <span class="ot-empty__ico">📦</span>
        <p class="ot-empty__title">No orders yet</p>
        <p class="ot-empty__sub">
          When you place an order it will appear here.
        </p>
      </div>

      <!-- Order list -->
      <template v-else>
        <div class="ot-list">
          <div
            v-for="order in orders"
            :key="order.id"
            class="ot-card"
            :class="{ 'ot-card--open': expandedId === order.id }"
          >
            <!-- ── Card summary ─────────────────────────────────────────────── -->
            <div class="ot-card__top" @click="toggle(order.id)">
              <div class="ot-card__info">
                <div class="ot-card__row">
                  <span class="ot-card__num">{{ order.order_number }}</span>
                  <span class="ot-chip" :class="dlvChipClass(order)">{{
                    dlvLabel(order)
                  }}</span>
                </div>
                <div class="ot-card__row ot-card__row--sm">
                  <svg viewBox="0 0 16 16" fill="none" class="ot-ico-store">
                    <path
                      d="M2 6.5L8 2l6 4.5V14H2V6.5z"
                      stroke="currentColor"
                      stroke-width="1.2"
                      stroke-linejoin="round"
                    />
                  </svg>
                  <span class="ot-card__store">{{
                    order.store_name || "BloomCraft Store"
                  }}</span>
                  <span class="ot-dot">·</span>
                  <span class="ot-card__date">{{
                    fmtDate(order.created_at)
                  }}</span>
                  <span class="ot-dot">·</span>
                  <span class="ot-card__count"
                    >{{ order.items_count }} item{{
                      order.items_count !== 1 ? "s" : ""
                    }}</span
                  >
                </div>
              </div>
              <div class="ot-card__aside">
                <div>
                  <div class="ot-card__total-lbl">Total</div>
                  <div class="ot-card__total">
                    ₱{{ fmt(order.total_amount) }}
                  </div>
                </div>
                <span
                  class="ot-pay-chip"
                  :class="payChip(order.payment_status)"
                  >{{ order.payment_status }}</span
                >
                <svg
                  class="ot-chevron"
                  :class="{ up: expandedId === order.id }"
                  viewBox="0 0 20 20"
                  fill="none"
                >
                  <path
                    d="M5 7.5l5 5 5-5"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </div>
            </div>

            <!-- ── Shipment stepper ─────────────────────────────────────────── -->
            <div class="ot-track">
              <div class="ot-track__head">
                <span class="ot-track__label">Shipment Status</span>
                <span class="ot-chip" :class="dlvChipClass(order)">{{
                  dlvLabel(order)
                }}</span>
              </div>
              <div class="ot-stepper">
                <template v-for="(step, i) in trackingSteps" :key="step.key">
                  <div
                    v-if="i > 0"
                    class="ot-conn"
                    :class="{
                      filled: isStepDone(order, i) || isStepActive(order, i),
                    }"
                  ></div>
                  <div
                    class="ot-step"
                    :class="{
                      done: isStepDone(order, i),
                      active: isStepActive(order, i),
                    }"
                  >
                    <div class="ot-dot-circle">
                      <svg
                        v-if="isStepDone(order, i)"
                        viewBox="0 0 16 16"
                        fill="none"
                      >
                        <path
                          d="M3 8l3.5 3.5L13 5"
                          stroke="white"
                          stroke-width="2.2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        />
                      </svg>
                      <div
                        v-else-if="isStepActive(order, i)"
                        class="ot-pulse"
                      ></div>
                    </div>
                    <span class="ot-step__lbl">{{ step.label }}</span>
                    <span
                      class="ot-step__ts"
                      v-if="
                        isStepActive(order, i) &&
                        order.delivery?.last_scanned_at
                      "
                    >
                      {{ fmtDT(order.delivery.last_scanned_at) }}
                    </span>
                  </div>
                </template>
              </div>

              <!-- Latest note bar -->
              <div
                v-if="
                  order.delivery?.status && order.delivery.status !== 'pending'
                "
                class="ot-note"
              >
                <svg viewBox="0 0 20 20" fill="none" class="ot-note__ico">
                  <rect
                    x="3"
                    y="5"
                    width="14"
                    height="12"
                    rx="2"
                    stroke="currentColor"
                    stroke-width="1.4"
                  />
                  <path
                    d="M7 3v4M13 3v4M3 9h14"
                    stroke="currentColor"
                    stroke-width="1.4"
                    stroke-linecap="round"
                  />
                </svg>
                <div class="ot-note__text">
                  <strong>{{ dlvLabel(order) }}</strong>
                  <span v-if="order.delivery.last_scanned_at">
                    · Last updated
                    {{ fmtDT(order.delivery.last_scanned_at) }}</span
                  >
                </div>
                <code v-if="order.delivery?.barcode" class="ot-barcode">{{
                  order.delivery.barcode
                }}</code>
              </div>
            </div>

            <!-- ── Expanded detail ──────────────────────────────────────────── -->
            <Transition name="expand">
              <div v-if="expandedId === order.id" class="ot-detail">
                <!-- ── Action buttons ───────────────────────────────────────── -->
                <div class="ot-actions" v-if="showActions(order)">
                  <!-- Mark as Received -->
                  <button
                    v-if="canComplete(order)"
                    class="ot-btn ot-btn--green"
                    :disabled="actionLoading === order.id + '_c'"
                    @click.stop="doComplete(order)"
                  >
                    <svg viewBox="0 0 20 20" fill="none" class="ot-btn__ico">
                      <path
                        d="M4 10l4 4L16 6"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                    {{
                      actionLoading === order.id + "_c"
                        ? "Confirming…"
                        : "Mark as Received"
                    }}
                  </button>

                  <!-- Request Return -->
                  <template v-if="canReturn(order)">
                    <div
                      v-if="order.return_request"
                      class="ot-request-status ot-request-status--return"
                    >
                      <svg viewBox="0 0 20 20" fill="none" class="ot-btn__ico">
                        <path
                          d="M3 10a7 7 0 1 0 7-7"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                        />
                        <path
                          d="M3 4v6h6"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        />
                      </svg>
                      Return
                      {{
                        order.return_request.status === "pending"
                          ? "Pending Review"
                          : order.return_request.status
                      }}
                    </div>
                    <button
                      v-else
                      class="ot-btn ot-btn--orange"
                      :disabled="actionLoading === order.id + '_ret'"
                      @click.stop="openRequestModal(order, 'return')"
                    >
                      <svg viewBox="0 0 20 20" fill="none" class="ot-btn__ico">
                        <path
                          d="M3 10a7 7 0 1 0 7-7"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                        />
                        <path
                          d="M3 4v6h6"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        />
                      </svg>
                      Request Return
                    </button>
                  </template>

                  <!-- Request Refund -->
                  <template v-if="canRefund(order)">
                    <div
                      v-if="order.refund_request"
                      class="ot-request-status ot-request-status--refund"
                    >
                      <svg viewBox="0 0 20 20" fill="none" class="ot-btn__ico">
                        <circle
                          cx="10"
                          cy="10"
                          r="7"
                          stroke="currentColor"
                          stroke-width="1.8"
                        />
                        <path
                          d="M10 7v6M7.5 9c0-1 1-1.5 2.5-1.5s2.5.5 2.5 1.5-1 1.5-2.5 1.5-2.5.5-2.5 1.5 1 1.5 2.5 1.5 2.5-.5 2.5-1.5"
                          stroke="currentColor"
                          stroke-width="1.4"
                          stroke-linecap="round"
                        />
                      </svg>
                      Refund
                      {{
                        order.refund_request.status === "pending"
                          ? "Pending Review"
                          : order.refund_request.status
                      }}
                    </div>
                    <button
                      v-else
                      class="ot-btn ot-btn--outline"
                      :disabled="actionLoading === order.id + '_ref'"
                      @click.stop="openRequestModal(order, 'refund')"
                    >
                      <svg viewBox="0 0 20 20" fill="none" class="ot-btn__ico">
                        <circle
                          cx="10"
                          cy="10"
                          r="7"
                          stroke="currentColor"
                          stroke-width="1.8"
                        />
                        <path
                          d="M10 7v6M7.5 9c0-1 1-1.5 2.5-1.5s2.5.5 2.5 1.5-1 1.5-2.5 1.5-2.5.5-2.5 1.5 1 1.5 2.5 1.5 2.5-.5 2.5-1.5"
                          stroke="currentColor"
                          stroke-width="1.4"
                          stroke-linecap="round"
                        />
                      </svg>
                      Request Refund
                    </button>
                  </template>

                  <!-- ① Rate Products button (only for completed orders) -->
                  <button
                    v-if="
                      canReview(order) &&
                      !order.return_request &&
                      !order.refund_request
                    "
                    class="ot-btn ot-btn--star"
                    @click.stop="openReviewModal(order)"
                    :title="
                      hasReviewed(order) ? 'See your review' : 'Rate Products'
                    "
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="currentColor"
                      class="ot-btn__ico"
                      style="color: currentColor"
                    >
                      <path
                        d="M10 1l2.6 5.3 5.9.9-4.3 4.1 1 5.9L10 14.4l-5.2 2.8 1-5.9L1.5 7.2l5.9-.9L10 1z"
                      />
                    </svg>
                    {{ hasReviewed(order) ? "See Review" : "Rate Products" }}
                  </button>
                </div>

                <!-- Delivery info -->
                <div class="ot-sec">
                  <h4 class="ot-sec__title">Delivery Information</h4>
                  <div class="ot-info-grid">
                    <div class="ot-info-cell">
                      <span class="ot-info-lbl">Address</span>
                      <span class="ot-info-val">{{
                        order.delivery_address || "—"
                      }}</span>
                    </div>
                    <div class="ot-info-cell">
                      <span class="ot-info-lbl">Contact</span>
                      <span class="ot-info-val"
                        >{{ order.delivery_contact_name || "—"
                        }}<span v-if="order.delivery_contact_number">
                          · {{ order.delivery_contact_number }}</span
                        ></span
                      >
                    </div>
                    <div class="ot-info-cell" v-if="order.customer_notes">
                      <span class="ot-info-lbl">Notes</span>
                      <span class="ot-info-val" style="font-style: italic">{{
                        order.customer_notes
                      }}</span>
                    </div>
                    <div class="ot-info-cell" v-if="order.reservation_date">
                      <span class="ot-info-lbl">Reservation</span>
                      <span class="ot-info-val">{{
                        order.reservation_date
                      }}</span>
                    </div>
                  </div>
                </div>

                <!-- Return / refund request summary if already submitted -->
                <div
                  class="ot-sec"
                  v-if="order.return_request || order.refund_request"
                >
                  <h4 class="ot-sec__title">Submitted Requests</h4>
                  <div class="ot-request-cards">
                    <div
                      v-if="order.return_request"
                      class="ot-req-card ot-req-card--return"
                    >
                      <div class="ot-req-card__head">
                        <span class="ot-req-card__type">Return Request</span>
                        <span
                          class="ot-req-badge"
                          :class="reqBadge(order.return_request.status)"
                          >{{ order.return_request.status }}</span
                        >
                      </div>
                      <p class="ot-req-card__reason">
                        {{ order.return_request.reason }}
                      </p>
                      <div
                        v-if="order.return_request.media_url"
                        class="ot-req-card__media"
                      >
                        <video
                          v-if="order.return_request.media_type === 'video'"
                          :src="order.return_request.media_url"
                          controls
                          class="ot-media-preview"
                        ></video>
                        <img
                          v-else
                          :src="order.return_request.media_url"
                          alt="Return proof"
                          class="ot-media-preview"
                        />
                      </div>
                    </div>
                    <div
                      v-if="order.refund_request"
                      class="ot-req-card ot-req-card--refund"
                    >
                      <div class="ot-req-card__head">
                        <span class="ot-req-card__type">Refund Request</span>
                        <span
                          class="ot-req-badge"
                          :class="reqBadge(order.refund_request.status)"
                          >{{ order.refund_request.status }}</span
                        >
                      </div>
                      <p class="ot-req-card__reason">
                        {{ order.refund_request.reason }}
                      </p>
                      <div
                        v-if="order.refund_request.media_url"
                        class="ot-req-card__media"
                      >
                        <video
                          v-if="order.refund_request.media_type === 'video'"
                          :src="order.refund_request.media_url"
                          controls
                          class="ot-media-preview"
                        ></video>
                        <img
                          v-else
                          :src="order.refund_request.media_url"
                          alt="Refund proof"
                          class="ot-media-preview"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Timeline -->
                <div class="ot-sec" v-if="order.delivery?.timeline?.length">
                  <h4 class="ot-sec__title">Delivery Updates</h4>
                  <div class="ot-tl">
                    <div
                      v-for="(ev, idx) in order.delivery.timeline"
                      :key="idx"
                      class="ot-tl__item"
                    >
                      <div class="ot-tl__dot"></div>
                      <div
                        v-if="idx < order.delivery.timeline.length - 1"
                        class="ot-tl__line"
                      ></div>
                      <div class="ot-tl__body">
                        <span class="ot-tl__status">{{ ev.label }}</span>
                        <span class="ot-tl__ts">{{
                          fmtDT(ev.scanned_at)
                        }}</span>
                        <span v-if="ev.notes" class="ot-tl__notes">{{
                          ev.notes
                        }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Items -->
                <div class="ot-sec" v-if="order.items?.length">
                  <h4 class="ot-sec__title">
                    Order Items ({{ order.items_count }})
                  </h4>
                  <div class="ot-items">
                    <div
                      v-for="item in order.items"
                      :key="item.id"
                      class="ot-item"
                    >
                      <img
                        v-if="item.product_image"
                        :src="item.product_image"
                        :alt="item.product_name"
                        class="ot-item__img"
                      />
                      <div v-else class="ot-item__img ot-item__img--ph">🌸</div>
                      <div class="ot-item__body">
                        <span class="ot-item__name">{{
                          item.product_name
                        }}</span>
                        <span class="ot-item__meta">
                          <span v-if="item.color">{{ item.color }}</span>
                          <span v-if="item.size"> · {{ item.size }}</span>
                          <span> · Qty {{ item.quantity }}</span>
                        </span>
                      </div>
                      <span class="ot-item__price"
                        >₱{{ fmt(item.subtotal) }}</span
                      >
                    </div>
                  </div>
                </div>

                <!-- Totals -->
                <div class="ot-totals">
                  <div class="ot-totals__row">
                    <span>Subtotal</span><span>₱{{ fmt(order.subtotal) }}</span>
                  </div>
                  <div class="ot-totals__row">
                    <span>Delivery fee</span
                    ><span>₱{{ fmt(order.delivery_fee) }}</span>
                  </div>
                  <div class="ot-totals__row ot-totals__row--bold">
                    <span>Total</span
                    ><span>₱{{ fmt(order.total_amount) }}</span>
                  </div>
                </div>
              </div>
            </Transition>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="ot-pager">
          <button
            class="ot-btn ot-btn--ghost"
            :disabled="meta.current_page === 1"
            @click="goPage(meta.current_page - 1)"
          >
            ← Prev
          </button>
          <span class="ot-pager__info"
            >{{ meta.current_page }} / {{ meta.last_page }}</span
          >
          <button
            class="ot-btn ot-btn--ghost"
            :disabled="meta.current_page === meta.last_page"
            @click="goPage(meta.current_page + 1)"
          >
            Next →
          </button>
        </div>
      </template>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         RETURN / REFUND MODAL
    ═══════════════════════════════════════════════════════════════════════════ -->
    <Transition name="modal">
      <div v-if="reqModal.show" class="ot-overlay" @click.self="closeReqModal">
        <div class="ot-modal">
          <!-- Header -->
          <div
            class="ot-modal__head"
            :class="
              reqModal.type === 'return'
                ? 'ot-modal__head--orange'
                : 'ot-modal__head--green'
            "
          >
            <div class="ot-modal__head-ico">
              <svg
                v-if="reqModal.type === 'return'"
                viewBox="0 0 24 24"
                fill="none"
              >
                <path
                  d="M3 12a9 9 0 1 0 9-9"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                />
                <path
                  d="M3 5v7h7"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
              <svg v-else viewBox="0 0 24 24" fill="none">
                <circle
                  cx="12"
                  cy="12"
                  r="9"
                  stroke="currentColor"
                  stroke-width="2"
                />
                <path
                  d="M12 8v8M9 10c0-1.1 1.3-2 3-2s3 .9 3 2-1.3 2-3 2-3 .9-3 2 1.3 2 3 2 3-.9 3-2"
                  stroke="currentColor"
                  stroke-width="1.6"
                  stroke-linecap="round"
                />
              </svg>
            </div>
            <div>
              <h3 class="ot-modal__title">
                {{
                  reqModal.type === "return"
                    ? "Request Return"
                    : "Request Refund"
                }}
              </h3>
              <p class="ot-modal__sub">{{ reqModal.orderNumber }}</p>
            </div>
            <button class="ot-modal__close" @click="closeReqModal">
              <svg viewBox="0 0 20 20" fill="none">
                <path
                  d="M5 5l10 10M15 5l-10 10"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="ot-modal__body">
            <!-- Reason -->
            <div class="ot-field">
              <label class="ot-field__lbl">
                Reason <span class="ot-field__req">*</span>
              </label>
              <textarea
                v-model="reqModal.reason"
                class="ot-textarea"
                :class="{ 'ot-textarea--err': reqModal.errors.reason }"
                rows="4"
                placeholder="Please describe the reason for your request (min. 10 characters)…"
              ></textarea>
              <span v-if="reqModal.errors.reason" class="ot-field__err">{{
                reqModal.errors.reason
              }}</span>
              <span class="ot-field__hint"
                >{{ reqModal.reason.length }} / 1000 characters</span
              >
            </div>

            <!-- Media upload -->
            <div class="ot-field">
              <label class="ot-field__lbl">
                Upload Proof <span class="ot-field__optional">(optional)</span>
              </label>

              <!-- Drop zone -->
              <div
                class="ot-dropzone"
                :class="{
                  'ot-dropzone--drag': isDragging,
                  'ot-dropzone--err': reqModal.errors.media,
                  'ot-dropzone--filled': reqModal.mediaFile,
                }"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="onDrop"
                @click="$refs.fileInput.click()"
              >
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/jpeg,image/png,video/mp4,video/quicktime"
                  class="ot-dropzone__input"
                  @change="onFileChange"
                />

                <!-- No file yet -->
                <div
                  v-if="!reqModal.mediaFile"
                  class="ot-dropzone__placeholder"
                >
                  <svg viewBox="0 0 48 48" fill="none" class="ot-dropzone__ico">
                    <rect
                      x="4"
                      y="10"
                      width="40"
                      height="30"
                      rx="4"
                      stroke="currentColor"
                      stroke-width="2"
                    />
                    <circle
                      cx="16"
                      cy="22"
                      r="4"
                      stroke="currentColor"
                      stroke-width="2"
                    />
                    <path
                      d="M4 34l10-8 8 6 8-10 14 12"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                    <path
                      d="M24 4v12M20 8l4-4 4 4"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                  </svg>
                  <p class="ot-dropzone__text">
                    <strong>Click or drag</strong> a file here<br />
                    <span class="ot-dropzone__hint"
                      >JPG, PNG, MP4, MOV · max 20 MB</span
                    >
                  </p>
                </div>

                <!-- Preview -->
                <div v-else class="ot-dropzone__preview">
                  <video
                    v-if="reqModal.mediaPreviewType === 'video'"
                    :src="reqModal.mediaPreviewUrl"
                    class="ot-preview-media"
                    controls
                    @click.stop
                  ></video>
                  <img
                    v-else
                    :src="reqModal.mediaPreviewUrl"
                    alt="Preview"
                    class="ot-preview-media"
                  />
                  <div class="ot-dropzone__file-info">
                    <span class="ot-dropzone__file-name">{{
                      reqModal.mediaFile.name
                    }}</span>
                    <span class="ot-dropzone__file-size">{{
                      fmtBytes(reqModal.mediaFile.size)
                    }}</span>
                  </div>
                  <button
                    class="ot-dropzone__remove"
                    @click.stop="removeMedia"
                    title="Remove file"
                  >
                    <svg viewBox="0 0 20 20" fill="none">
                      <path
                        d="M5 5l10 10M15 5l-10 10"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                      />
                    </svg>
                  </button>
                </div>
              </div>
              <span v-if="reqModal.errors.media" class="ot-field__err">{{
                reqModal.errors.media
              }}</span>
            </div>
          </div>

          <!-- Footer -->
          <div class="ot-modal__footer">
            <button
              class="ot-btn ot-btn--ghost"
              @click="closeReqModal"
              :disabled="reqModal.submitting"
            >
              Cancel
            </button>
            <button
              class="ot-btn"
              :class="
                reqModal.type === 'return' ? 'ot-btn--orange' : 'ot-btn--green'
              "
              :disabled="reqModal.submitting"
              @click="submitRequest"
            >
              <svg
                v-if="reqModal.submitting"
                class="ot-spinner"
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
                reqModal.submitting
                  ? "Submitting…"
                  : reqModal.type === "return"
                    ? "Submit Return"
                    : "Submit Refund"
              }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Simple confirm modal (for Mark as Received) -->
    <Transition name="modal">
      <div
        v-if="confirmModal.show"
        class="ot-overlay"
        @click.self="confirmModal.show = false"
      >
        <div class="ot-modal ot-modal--sm">
          <div class="ot-modal__ico-lg">✅</div>
          <h3 class="ot-modal__title">Confirm Delivery</h3>
          <p class="ot-modal__confirm-body">{{ confirmModal.body }}</p>
          <div class="ot-modal__footer">
            <button
              class="ot-btn ot-btn--ghost"
              @click="confirmModal.show = false"
            >
              Cancel
            </button>
            <button
              class="ot-btn ot-btn--green"
              :disabled="confirmModal.loading"
              @click="confirmModal.onConfirm"
            >
              {{ confirmModal.loading ? "Confirming…" : "Yes, I received it" }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ③ Review modal -->
    <ReviewModal
      :show="reviewModal.show"
      :order-id="reviewModal.orderId"
      :order-number="reviewModal.orderNumber"
      :order-items="reviewModal.orderItems"
      @close="reviewModal.show = false"
      @reviewed="(orderId) => onReviewed(orderId)"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../../plugins/axios";
import { useAuth } from "../../composables/useAuth";
import NavHeader from "../../layouts/NavHeader.vue";
import ReviewModal from "../../layouts/components/ReviewModal.vue";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";

const API_BASE = "customer/orders";
const route = useRoute();
const router = useRouter();

// ── State ─────────────────────────────────────────────────────────────────────
const { isAuthenticated } = useAuth();
const orders = ref([]);
const loading = ref(false);
const error = ref(null);
const expandedId = ref(null);
const search = ref("");
const activeTab = ref("all");
const actionLoading = ref(null);
const navHeaderRef = ref(null);
const isDragging = ref(false);
const fileInput = ref(null);
const meta = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
  per_page: 15,
});

// Return / refund modal
const reqModal = reactive({
  show: false,
  type: "return",
  orderId: null,
  orderNumber: "",
  reason: "",
  mediaFile: null,
  mediaPreviewUrl: null,
  mediaPreviewType: null,
  submitting: false,
  errors: { reason: "", media: "" },
});

// Simple confirm modal
const confirmModal = reactive({
  show: false,
  body: "",
  loading: false,
  onConfirm: null,
});

// ① Review modal state
const reviewModal = reactive({
  show: false,
  orderId: null,
  orderNumber: "",
  orderItems: [],
});
// Track which order IDs have been reviewed in this session
const reviewedOrderIds = ref(new Set());

let searchTimer = null;

// ── Static data ───────────────────────────────────────────────────────────────
const statusTabs = [
  { label: "All", value: "all" },
  { label: "Processing", value: "processing" },
  { label: "Out for Delivery", value: "out_for_delivery" },
  { label: "Completed", value: "completed" },
  { label: "Returned", value: "returned" },
];

const trackingSteps = [
  { key: "pending", label: "Ordered", deliveryKeys: ["pending"] },
  { key: "to_processed", label: "Packed", deliveryKeys: ["to_processed"] },
  { key: "to_ship", label: "Shipped", deliveryKeys: ["to_ship"] },
  {
    key: "to_received",
    label: "Out for Delivery",
    deliveryKeys: ["to_received"],
  },
  {
    key: "completed",
    label: "Delivered",
    deliveryKeys: ["completed", "returned", "refunded"],
  },
];

// ── Auth ──────────────────────────────────────────────────────────────────────
function authHeaders() {
  const token =
    localStorage.getItem("token") || localStorage.getItem("auth_token");
  return token ? { Authorization: `Bearer ${token}` } : {};
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
async function fetchOrders(page = 1) {
  loading.value = true;
  error.value = null;
  try {
    const params = { page, per_page: meta.per_page };
    if (activeTab.value !== "all") params.delivery_status = activeTab.value;
    if (search.value.trim()) params.search = search.value.trim();

    const res = await api.get(API_BASE, { params, headers: authHeaders() });
    orders.value = res.data.data ?? [];
    Object.assign(meta, res.data.meta ?? {});
  } catch (e) {
    error.value =
      e.response?.status === 401
        ? "Session expired — please log in again."
        : (e.response?.data?.message ??
          "Could not load orders. Please try again.");
  } finally {
    loading.value = false;
  }
}

// ── Action guards ─────────────────────────────────────────────────────────────
const canComplete = (o) => o.delivery?.status === "to_received";
const canReturn = (o) => o.delivery?.status === "completed";
const canRefund = (o) =>
  o.delivery?.status === "completed" && o.payment_status === "paid";
const showActions = (o) =>
  canComplete(o) || canReturn(o) || canRefund(o) || canReview(o);

// ① canReview computed helper — true if order is completed
const canReview = (order) =>
  order.delivery?.status === "completed" || order.status === "completed";

// True if already reviewed in this session (disables the button)
const hasReviewed = (order) => reviewedOrderIds.value.has(order.id);

// ── Mark as received ──────────────────────────────────────────────────────────
function doComplete(order) {
  confirmModal.show = true;
  confirmModal.loading = false;
  confirmModal.body = `Have you received order ${order.order_number}? This will mark it as delivered.`;
  confirmModal.onConfirm = async () => {
    confirmModal.loading = true;
    actionLoading.value = order.id + "_c";
    try {
      await api.post(
        `${API_BASE}/${order.id}/complete`,
        {},
        { headers: authHeaders() },
      );
      confirmModal.show = false;
      await fetchOrders(meta.current_page);
    } catch (e) {
      alert(e.response?.data?.message ?? "Action failed.");
    } finally {
      confirmModal.loading = false;
      actionLoading.value = null;
    }
  };
}

// ── Return / Refund modal ─────────────────────────────────────────────────────
function openRequestModal(order, type) {
  Object.assign(reqModal, {
    show: true,
    type,
    orderId: order.id,
    orderNumber: order.order_number,
    reason: "",
    mediaFile: null,
    mediaPreviewUrl: null,
    mediaPreviewType: null,
    submitting: false,
    errors: { reason: "", media: "" },
  });
}

function closeReqModal() {
  if (reqModal.submitting) return;
  reqModal.show = false;
  removeMedia();
}

function onFileChange(e) {
  const file = e.target.files?.[0];
  if (file) setMediaFile(file);
}

function onDrop(e) {
  isDragging.value = false;
  const file = e.dataTransfer.files?.[0];
  if (file) setMediaFile(file);
}

function setMediaFile(file) {
  reqModal.errors.media = "";

  const allowed = ["image/jpeg", "image/png", "video/mp4", "video/quicktime"];
  if (!allowed.includes(file.type)) {
    reqModal.errors.media = "Only JPG, PNG, MP4, or MOV files are allowed.";
    return;
  }
  if (file.size > 20 * 1024 * 1024) {
    reqModal.errors.media = "File size must not exceed 20 MB.";
    return;
  }

  reqModal.mediaFile = file;
  reqModal.mediaPreviewType = file.type.startsWith("video/")
    ? "video"
    : "image";
  reqModal.mediaPreviewUrl = URL.createObjectURL(file);
}

function removeMedia() {
  if (reqModal.mediaPreviewUrl) URL.revokeObjectURL(reqModal.mediaPreviewUrl);
  reqModal.mediaFile = null;
  reqModal.mediaPreviewUrl = null;
  reqModal.mediaPreviewType = null;
  if (fileInput.value) fileInput.value.value = "";
}

function validateReqModal() {
  let ok = true;
  reqModal.errors.reason = "";
  reqModal.errors.media = "";

  if (!reqModal.reason.trim() || reqModal.reason.trim().length < 10) {
    reqModal.errors.reason = "Please provide at least 10 characters.";
    ok = false;
  }
  if (reqModal.reason.length > 1000) {
    reqModal.errors.reason = "Reason must not exceed 1000 characters.";
    ok = false;
  }
  return ok;
}

async function submitRequest() {
  if (!validateReqModal()) return;

  reqModal.submitting = true;
  actionLoading.value =
    reqModal.orderId + (reqModal.type === "return" ? "_ret" : "_ref");

  try {
    const fd = new FormData();
    fd.append("reason", reqModal.reason);
    if (reqModal.mediaFile) fd.append("media", reqModal.mediaFile);

    const endpoint =
      reqModal.type === "return" ? "request-return" : "request-refund";

    await api.post(`${API_BASE}/${reqModal.orderId}/${endpoint}`, fd, {
      headers: {
        ...authHeaders(),
        "Content-Type": "multipart/form-data",
      },
    });

    closeReqModal();
    await fetchOrders(meta.current_page);
  } catch (e) {
    const msg =
      e.response?.data?.message ?? "Submission failed. Please try again.";
    if (e.response?.status === 422 && e.response.data?.errors) {
      const errs = e.response.data.errors;
      reqModal.errors.reason = errs.reason?.[0] ?? "";
      reqModal.errors.media = errs.media?.[0] ?? "";
    } else {
      alert(msg);
    }
  } finally {
    reqModal.submitting = false;
    actionLoading.value = null;
  }
}

// ① openReviewModal function
function openReviewModal(order) {
  reviewModal.orderId = order.id;
  reviewModal.orderNumber = order.order_number ?? "";
  // Pass items if already loaded (card expanded); otherwise ReviewModal fetches them itself
  reviewModal.orderItems = order.items ?? [];
  reviewModal.show = true;
}

// Called when ReviewModal emits "reviewed"
function onReviewed(orderId) {
  // Mark this order as reviewed so the button disables immediately
  if (orderId) reviewedOrderIds.value.add(orderId);
  // Show success toast
  toast.success("Review submitted successfully! Thank you for your feedback.", {
    autoClose: 3500,
    position: toast.POSITION.TOP_RIGHT,
  });
  // Refresh orders list in background
  fetchOrders(meta.current_page);
}

// ── UI helpers ────────────────────────────────────────────────────────────────
const toggle = (id) => {
  expandedId.value = expandedId.value === id ? null : id;
};
const setTab = (v) => {
  activeTab.value = v;
  expandedId.value = null;
  fetchOrders(1);
};
const goPage = (p) => {
  fetchOrders(p);
  window.scrollTo({ top: 0, behavior: "smooth" });
};
const debounceSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => fetchOrders(1), 400);
};

// stepper
function stepIdx(order) {
  const ds = order.delivery?.status;
  if (!ds) return 0;
  return trackingSteps.findIndex((s) => s.deliveryKeys.includes(ds));
}
const isStepDone = (o, i) => i < stepIdx(o);
const isStepActive = (o, i) => i === stepIdx(o);

// labels & chips
function dlvLabel(order) {
  const m = {
    pending: "Pending",
    to_processed: "Packed",
    to_ship: "Shipped",
    to_received: "Out for Delivery",
    completed: "Delivered",
    returned: "Returned",
    refunded: "Refunded",
  };
  return m[order.delivery?.status] ?? "Pending";
}
function dlvChipClass(order) {
  const ds = order.delivery?.status ?? "pending";
  if (["to_ship", "to_received"].includes(ds)) return "chip--blue";
  if (ds === "completed") return "chip--green";
  if (["pending", "to_processed"].includes(ds)) return "chip--orange";
  return "chip--red";
}
function payChip(s) {
  if (s === "paid") return "pay--green";
  if (s === "unpaid") return "pay--yellow";
  if (["failed", "refunded"].includes(s)) return "pay--red";
  return "";
}
function reqBadge(s) {
  if (s === "approved") return "req-badge--green";
  if (s === "rejected") return "req-badge--red";
  return "req-badge--yellow";
}

// formatters
const fmt = (v) =>
  Number(v ?? 0).toLocaleString("en-PH", { minimumFractionDigits: 2 });
const fmtDate = (iso) =>
  iso
    ? new Date(iso).toLocaleDateString("en-PH", {
        year: "numeric",
        month: "short",
        day: "numeric",
      })
    : "—";
const fmtDT = (iso) =>
  iso
    ? new Date(iso).toLocaleString("en-PH", {
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      })
    : "—";
const fmtBytes = (b) =>
  b > 1048576
    ? (b / 1048576).toFixed(1) + " MB"
    : (b / 1024).toFixed(0) + " KB";

// NavHeader scroll helper (kept for compatibility)
const scrollToSection = (id) => {
  const el = document.getElementById(id);
  if (el) el.scrollIntoView({ behavior: "smooth" });
};

const cartCount = ref(0);

onMounted(async () => {
  await fetchOrders();

  if (route.query.payment === "success") {
    toast.success("Payment confirmed. Your order has been updated.", {
      position: toast.POSITION.TOP_RIGHT,
    });

    const nextQuery = { ...route.query };
    delete nextQuery.payment;
    delete nextQuery.reference;

    router.replace({ query: nextQuery });
  }
});
</script>

<style scoped>
/* ── Tokens ──────────────────────────────────────────────────────────────── */
.ot-page {
  --green: #48bb78;
  --green-dk: #38a169;
  --green-lt: #f0fff4;
  --orange: #ed8936;
  --orange-lt: #fffaf0;
  --dark: #2d3748;
  --dark2: #4a5568;
  --muted: #718096;
  --pale: #a0aec0;
  --border: #e2e8f0;
  --surface: #fff;
  --bg: #f7f8fa;
  --radius: 12px;
  --r-sm: 8px;
  --shadow-sm: 0 1px 4px rgba(0, 0, 0, 0.05);
  --shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  --font: "Poppins", sans-serif;

  min-height: 100vh;
  background: var(--bg);
  padding-top: 80px;
  font-family: var(--font);
  color: var(--dark);
}

/* ── Sub-header ──────────────────────────────────────────────────────────── */
.ot-header {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  position: sticky;
  top: 80px;
  z-index: 30;
}
.ot-header__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 48px 12px;
  flex-wrap: wrap;
}
.ot-header__title {
  font-size: 1.2rem;
  font-weight: 700;
  color: #1a202c;
  margin: 0;
}
.ot-header__sub {
  font-size: 0.78rem;
  color: var(--pale);
  margin: 2px 0 0;
}

/* search */
.ot-search-wrap {
  position: relative;
  flex: 1;
  max-width: 320px;
}
.ot-search-ico {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  width: 15px;
  height: 15px;
  color: var(--pale);
  pointer-events: none;
}
.ot-search {
  width: 100%;
  padding: 9px 12px 9px 34px;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
  font-family: inherit;
  font-size: 0.875rem;
  color: var(--dark);
  outline: none;
  transition: border-color 0.2s;
}
.ot-search::placeholder {
  color: var(--pale);
}
.ot-search:focus {
  border-color: var(--green);
  background: var(--surface);
}

/* tabs */
.ot-tabs {
  display: flex;
  gap: 2px;
  padding: 0 48px;
  overflow-x: auto;
  scrollbar-width: none;
}
.ot-tabs::-webkit-scrollbar {
  display: none;
}
.ot-tab {
  padding: 10px 18px;
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--muted);
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  white-space: nowrap;
  transition:
    color 0.2s,
    border-color 0.2s;
}
.ot-tab:hover {
  color: var(--dark);
}
.ot-tab.active {
  color: var(--dark);
  border-bottom-color: var(--green);
  font-weight: 600;
}

/* ── Content ─────────────────────────────────────────────────────────────── */
.ot-content {
  padding: 24px 48px 60px;
}

/* ── Skeleton ────────────────────────────────────────────────────────────── */
.ot-skeletons {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.ot-skel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 20px 22px;
}
.ot-skel__top {
  display: flex;
  justify-content: space-between;
  margin-bottom: 16px;
}
.ot-skel__line {
  height: 12px;
  border-radius: 6px;
  background: linear-gradient(90deg, #f0f2f5 25%, #e2e8f0 50%, #f0f2f5 75%);
  background-size: 200%;
  animation: shimmer 1.4s infinite;
}
.ot-skel__line--title {
  width: 42%;
  height: 16px;
}
.ot-skel__line--badge {
  width: 70px;
}
.ot-skel__row {
  display: flex;
  gap: 8px;
}
.ot-skel__step {
  flex: 1;
  height: 40px;
  border-radius: 6px;
  background: linear-gradient(90deg, #f0f2f5 25%, #e2e8f0 50%, #f0f2f5 75%);
  background-size: 200%;
  animation: shimmer 1.4s infinite;
}
@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

/* ── Empty / Error ───────────────────────────────────────────────────────── */
.ot-empty {
  text-align: center;
  padding: 64px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}
.ot-empty__ico {
  font-size: 2.8rem;
}
.ot-empty__title {
  font-size: 1.05rem;
  font-weight: 700;
  margin: 0;
}
.ot-empty__sub {
  font-size: 0.875rem;
  color: var(--muted);
  margin: 0;
}

/* ── Cards ───────────────────────────────────────────────────────────────── */
.ot-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.ot-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
  transition:
    box-shadow 0.22s,
    border-color 0.22s;
}
.ot-card:hover {
  box-shadow: var(--shadow);
}
.ot-card--open {
  border-color: var(--green);
}

/* card top */
.ot-card__top {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 18px 22px;
  cursor: pointer;
  user-select: none;
}
.ot-card__info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ot-card__row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.ot-card__row--sm {
  gap: 5px;
}
.ot-card__num {
  font-size: 0.95rem;
  font-weight: 700;
  color: #1a202c;
}
.ot-ico-store {
  width: 12px;
  height: 12px;
  color: var(--pale);
  flex-shrink: 0;
}
.ot-card__store {
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--dark2);
}
.ot-dot {
  color: var(--border);
  font-size: 0.75rem;
}
.ot-card__date,
.ot-card__count {
  font-size: 0.78rem;
  color: var(--pale);
}

.ot-card__aside {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 7px;
  flex-shrink: 0;
}
.ot-card__total-lbl {
  font-size: 0.68rem;
  color: var(--pale);
  font-weight: 500;
}
.ot-card__total {
  font-size: 1rem;
  font-weight: 700;
  color: #1a202c;
}
.ot-chevron {
  width: 18px;
  height: 18px;
  color: var(--pale);
  transition: transform 0.22s;
}
.ot-chevron.up {
  transform: rotate(180deg);
}

/* chips */
.ot-chip {
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  flex-shrink: 0;
}
.chip--blue {
  background: #ebf8ff;
  color: #2b6cb0;
}
.chip--green {
  background: var(--green-lt);
  color: #276749;
}
.chip--orange {
  background: var(--orange-lt);
  color: #c05621;
}
.chip--red {
  background: #fff5f5;
  color: #9b2c2c;
}

.ot-pay-chip {
  padding: 2px 9px;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.pay--green {
  background: var(--green-lt);
  color: #276749;
}
.pay--yellow {
  background: #fefcbf;
  color: #744210;
}
.pay--red {
  background: #fff5f5;
  color: #9b2c2c;
}

/* ── Track section ───────────────────────────────────────────────────────── */
.ot-track {
  border-top: 1px solid #f0f2f5;
  padding: 16px 22px 18px;
}
.ot-track__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.ot-track__label {
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--muted);
}

/* stepper */
.ot-stepper {
  display: flex;
  align-items: flex-start;
}
.ot-conn {
  flex: 1;
  height: 2px;
  background: var(--border);
  margin-top: 17px;
  transition: background 0.3s;
}
.ot-conn.filled {
  background: var(--green);
}
.ot-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
  min-width: 64px;
}
.ot-dot-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #f0f2f5;
  border: 2px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.25s;
  position: relative;
  z-index: 1;
}
.ot-dot-circle svg {
  width: 16px;
  height: 16px;
}
.ot-step.done .ot-dot-circle {
  background: var(--green);
  border-color: var(--green);
}
.ot-step.active .ot-dot-circle {
  background: var(--surface);
  border-color: var(--green);
  border-width: 2.5px;
  box-shadow: 0 0 0 5px rgba(72, 187, 120, 0.12);
}
.ot-pulse {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--green);
  animation: pulse 1.6s infinite;
}
@keyframes pulse {
  0%,
  100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.4);
    opacity: 0.6;
  }
}
.ot-step__lbl {
  font-size: 0.7rem;
  font-weight: 500;
  color: var(--pale);
  margin-top: 7px;
  text-align: center;
  line-height: 1.3;
}
.ot-step.done .ot-step__lbl,
.ot-step.active .ot-step__lbl {
  color: var(--dark);
  font-weight: 600;
}
.ot-step__ts {
  font-size: 0.64rem;
  color: var(--green);
  font-weight: 600;
  margin-top: 3px;
  text-align: center;
  min-height: 14px;
}

/* note bar */
.ot-note {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: #f0f9ff;
  border: 1px solid #bfdbfe;
  border-radius: var(--r-sm);
  padding: 12px 14px;
  margin-top: 16px;
}
.ot-note__ico {
  width: 17px;
  height: 17px;
  color: #3b82f6;
  flex-shrink: 0;
  margin-top: 1px;
}
.ot-note__text {
  flex: 1;
  font-size: 0.8rem;
  color: #1e40af;
}
.ot-note__text strong {
  font-weight: 600;
}
.ot-barcode {
  font-family: monospace;
  font-size: 0.7rem;
  color: var(--muted);
  background: var(--surface);
  padding: 2px 7px;
  border-radius: 4px;
  border: 1px solid var(--border);
  flex-shrink: 0;
}

/* ── Expand ───────────────────────────────────────────────────────────────── */
.expand-enter-active,
.expand-leave-active {
  transition:
    max-height 0.28s ease,
    opacity 0.22s ease;
  overflow: hidden;
  max-height: 2000px;
}
.expand-enter-from,
.expand-leave-to {
  max-height: 0;
  opacity: 0;
}

/* ── Detail panel ────────────────────────────────────────────────────────── */
.ot-detail {
  border-top: 1px solid #f0f2f5;
  background: #fafbfc;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* actions row */
.ot-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

/* request status pill */
.ot-request-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  border-radius: var(--r-sm);
  font-size: 0.875rem;
  font-weight: 600;
}
.ot-request-status--return {
  background: var(--orange-lt);
  color: #c05621;
}
.ot-request-status--refund {
  background: #ebf8ff;
  color: #2b6cb0;
}

/* submitted request cards */
.ot-request-cards {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ot-req-card {
  background: var(--surface);
  border-radius: var(--r-sm);
  border: 1px solid var(--border);
  padding: 14px 16px;
}
.ot-req-card--return {
  border-left: 3px solid var(--orange);
}
.ot-req-card--refund {
  border-left: 3px solid var(--green);
}
.ot-req-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.ot-req-card__type {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--dark);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.ot-req-badge {
  padding: 2px 9px;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
}
.req-badge--yellow {
  background: #fefcbf;
  color: #744210;
}
.req-badge--green {
  background: var(--green-lt);
  color: #276749;
}
.req-badge--red {
  background: #fff5f5;
  color: #9b2c2c;
}
.ot-req-card__reason {
  font-size: 0.875rem;
  color: var(--dark2);
  margin: 0 0 10px;
  line-height: 1.5;
}
.ot-req-card__media {
  margin-top: 8px;
}
.ot-media-preview {
  max-width: 100%;
  max-height: 200px;
  border-radius: var(--r-sm);
  object-fit: cover;
  border: 1px solid var(--border);
}

/* detail section */
.ot-sec {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ot-sec__title {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--pale);
  margin: 0;
}

.ot-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 10px;
}
.ot-info-cell {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.ot-info-lbl {
  font-size: 0.7rem;
  color: var(--pale);
  font-weight: 500;
}
.ot-info-val {
  font-size: 0.875rem;
  color: var(--dark);
  line-height: 1.4;
}

/* timeline */
.ot-tl {
  display: flex;
  flex-direction: column;
}
.ot-tl__item {
  display: flex;
  gap: 12px;
  position: relative;
  padding-bottom: 14px;
}
.ot-tl__item:last-child {
  padding-bottom: 0;
}
.ot-tl__dot {
  width: 13px;
  height: 13px;
  border-radius: 50%;
  background: var(--green);
  flex-shrink: 0;
  margin-top: 4px;
  position: relative;
  z-index: 1;
}
.ot-tl__line {
  position: absolute;
  left: 6px;
  top: 17px;
  bottom: 0;
  width: 2px;
  background: var(--border);
}
.ot-tl__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.ot-tl__status {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--dark);
}
.ot-tl__ts {
  font-size: 0.78rem;
  color: var(--pale);
}
.ot-tl__notes {
  font-size: 0.8rem;
  color: var(--muted);
  font-style: italic;
}

/* items */
.ot-items {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ot-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  background: var(--surface);
  border: 1px solid #f0f2f5;
  border-radius: var(--r-sm);
}
.ot-item__img {
  width: 46px;
  height: 46px;
  object-fit: cover;
  border-radius: 7px;
  background: #f0f2f5;
  flex-shrink: 0;
}
.ot-item__img--ph {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
}
.ot-item__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.ot-item__name {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--dark);
}
.ot-item__meta {
  font-size: 0.76rem;
  color: var(--pale);
}
.ot-item__price {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--dark);
  flex-shrink: 0;
}

/* totals */
.ot-totals {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
  padding: 14px 18px;
}
.ot-totals__row {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  color: var(--muted);
  padding: 4px 0;
}
.ot-totals__row--bold {
  font-size: 1rem;
  font-weight: 700;
  color: #1a202c;
  border-top: 1px solid var(--border);
  margin-top: 8px;
  padding-top: 12px;
}

/* ── Buttons ─────────────────────────────────────────────────────────────── */
.ot-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 10px 18px;
  border-radius: var(--r-sm);
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}
.ot-btn--green {
  background: var(--green);
  color: #fff;
}
.ot-btn--green:hover:not(:disabled) {
  background: var(--green-dk);
}
.ot-btn--orange {
  background: var(--orange);
  color: #fff;
}
.ot-btn--orange:hover:not(:disabled) {
  background: #dd6b20;
}
.ot-btn--outline {
  background: var(--surface);
  color: var(--dark);
  border: 1.5px solid var(--border);
}
.ot-btn--outline:hover:not(:disabled) {
  border-color: var(--green);
  color: var(--green);
}
.ot-btn--ghost {
  background: transparent;
  color: var(--dark2);
  border: 1.5px solid var(--border);
}
.ot-btn--ghost:hover:not(:disabled) {
  background: var(--bg);
}
/* ④ Rate Products button style */
.ot-btn--star {
  background: linear-gradient(135deg, #f6ad55, #ed8936);
  color: #fff;
}
.ot-btn--star:hover:not(:disabled) {
  background: linear-gradient(135deg, #ed8936, #dd6b20);
}
/* Rated/disabled state */
.ot-btn--star-done,
.ot-btn--star:disabled {
  background: #e2e8f0 !important;
  color: #718096 !important;
  cursor: not-allowed !important;
  opacity: 1 !important;
}
.ot-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.ot-btn__ico {
  width: 15px;
  height: 15px;
  flex-shrink: 0;
}

/* ── Pagination ──────────────────────────────────────────────────────────── */
.ot-pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 24px;
}
.ot-pager__info {
  font-size: 0.875rem;
  color: var(--muted);
}

/* ── Overlay & Modals ────────────────────────────────────────────────────── */
.ot-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
  padding: 20px;
  overflow-y: auto;
}

/* Return / refund modal */
.ot-modal {
  background: var(--surface);
  border-radius: 16px;
  width: 100%;
  max-width: 500px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  max-height: 90vh;
}
.ot-modal--sm {
  max-width: 400px;
  padding: 32px 28px;
  align-items: center;
  text-align: center;
  gap: 14px;
}
.ot-modal__ico-lg {
  font-size: 2.4rem;
}

/* modal header */
.ot-modal__head {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 20px 24px;
  flex-shrink: 0;
}
.ot-modal__head--orange {
  background: linear-gradient(135deg, #fffaf0, #feebc8);
}
.ot-modal__head--green {
  background: linear-gradient(135deg, #f0fff4, #c6f6d5);
}
.ot-modal__head-ico {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.ot-modal__head-ico svg {
  width: 22px;
  height: 22px;
  color: var(--dark);
}
.ot-modal__title {
  font-size: 1.05rem;
  font-weight: 700;
  color: #1a202c;
  margin: 0;
}
.ot-modal__sub {
  font-size: 0.78rem;
  color: var(--muted);
  margin: 2px 0 0;
}
.ot-modal__close {
  margin-left: auto;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: rgba(255, 255, 255, 0.6);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
  flex-shrink: 0;
}
.ot-modal__close:hover {
  background: rgba(255, 255, 255, 0.9);
}
.ot-modal__close svg {
  width: 14px;
  height: 14px;
  color: var(--dark);
}

/* modal body */
.ot-modal__body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  overflow-y: auto;
  flex: 1;
}
.ot-modal__confirm-body {
  font-size: 0.875rem;
  color: var(--muted);
  margin: 0;
  line-height: 1.5;
  text-align: center;
}

/* modal footer */
.ot-modal__footer {
  display: flex;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--border);
  justify-content: flex-end;
  flex-shrink: 0;
}

/* ── Form fields ─────────────────────────────────────────────────────────── */
.ot-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ot-field__lbl {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--dark);
}
.ot-field__req {
  color: #e53e3e;
}
.ot-field__optional {
  font-size: 0.75rem;
  color: var(--pale);
  font-weight: 400;
}
.ot-field__err {
  font-size: 0.75rem;
  color: #e53e3e;
}
.ot-field__hint {
  font-size: 0.72rem;
  color: var(--pale);
  text-align: right;
}

.ot-textarea {
  width: 100%;
  padding: 10px 12px;
  background: var(--bg);
  border: 1.5px solid var(--border);
  border-radius: var(--r-sm);
  font-family: inherit;
  font-size: 0.875rem;
  color: var(--dark);
  resize: vertical;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}
.ot-textarea:focus {
  border-color: var(--green);
  background: var(--surface);
}
.ot-textarea--err {
  border-color: #e53e3e;
}

/* ── Drop zone ───────────────────────────────────────────────────────────── */
.ot-dropzone {
  border: 2px dashed var(--border);
  border-radius: var(--r-sm);
  background: var(--bg);
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  min-height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.ot-dropzone:hover {
  border-color: var(--green);
  background: #f0fff4;
}
.ot-dropzone--drag {
  border-color: var(--green);
  background: #f0fff4;
  box-shadow: 0 0 0 4px rgba(72, 187, 120, 0.12);
}
.ot-dropzone--err {
  border-color: #e53e3e;
}
.ot-dropzone__input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
  width: 100%;
  height: 100%;
}
.ot-dropzone__placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 24px;
  pointer-events: none;
}
.ot-dropzone__ico {
  width: 48px;
  height: 48px;
  color: var(--pale);
}
.ot-dropzone__text {
  font-size: 0.875rem;
  color: var(--muted);
  text-align: center;
  margin: 0;
  line-height: 1.5;
}
.ot-dropzone__text strong {
  color: var(--dark);
}
.ot-dropzone__hint {
  font-size: 0.75rem;
  color: var(--pale);
}

/* file preview */
.ot-dropzone__preview {
  width: 100%;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  position: relative;
}
.ot-preview-media {
  width: 100%;
  max-height: 200px;
  object-fit: cover;
  border-radius: var(--r-sm);
  border: 1px solid var(--border);
}
.ot-dropzone__file-info {
  display: flex;
  align-items: center;
  gap: 8px;
}
.ot-dropzone__file-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--dark);
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ot-dropzone__file-size {
  font-size: 0.75rem;
  color: var(--pale);
  flex-shrink: 0;
}
.ot-dropzone__remove {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.5);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}
.ot-dropzone__remove:hover {
  background: rgba(0, 0, 0, 0.7);
}
.ot-dropzone__remove svg {
  width: 12px;
  height: 12px;
  color: #fff;
}

/* spinner */
.ot-spinner {
  width: 16px;
  height: 16px;
  animation: spin 0.8s linear infinite;
  flex-shrink: 0;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* ── Modal transitions ───────────────────────────────────────────────────── */
.modal-enter-active,
.modal-leave-active {
  transition:
    opacity 0.2s,
    transform 0.2s;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
  transform: scale(0.97);
}

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 640px) {
  .ot-page {
    padding-top: 72px;
  }
  .ot-header {
    top: 72px;
  }
  .ot-header__inner {
    padding: 14px 16px 10px;
  }
  .ot-tabs {
    padding: 0 16px;
  }
  .ot-content {
    padding: 16px 16px 48px;
  }
  .ot-card__top {
    padding: 14px 16px;
  }
  .ot-track {
    padding: 14px 16px 16px;
  }
  .ot-detail {
    padding: 16px;
  }
  .ot-search-wrap {
    max-width: none;
  }
  .ot-step {
    min-width: 48px;
  }
  .ot-step__lbl {
    font-size: 0.62rem;
  }
  .ot-dot-circle {
    width: 28px;
    height: 28px;
  }
  .ot-conn {
    margin-top: 14px;
  }
  .ot-modal__head,
  .ot-modal__body,
  .ot-modal__footer {
    padding-left: 16px;
    padding-right: 16px;
  }
  .ot-actions {
    flex-direction: column;
  }
}
</style>
