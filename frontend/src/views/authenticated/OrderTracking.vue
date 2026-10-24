<template>
  <NavHeader
    ref="navHeaderRef"
    :cartCount="cartCount"
    :isAuthenticated="isAuthenticated"
    @scroll-to-section="scrollToSection"
  />
  <div class="ot-page bc-theme">
    <!-- ── Sub-header ───────────────────────────────────────────────────────── -->
    <div class="ot-header">
      <div class="ot-header__inner">
        <div>
          <p class="bc-label">Your orders</p>
          <h1 class="ot-header__title">My <em>orders</em></h1>
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
            aria-label="Search orders or stores"
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
      <!-- Asked once, after their first order: offers from shops they buy from -->
      <section v-if="offerAsk" class="ot-offer-ask" aria-labelledby="ot-offer-title" aria-live="polite">
        <span class="ot-offer-ask__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/></svg>
        </span>
        <div class="ot-offer-ask__text">
          <h2 id="ot-offer-title">Want offers from shops you order from?</h2>
          <p>Birthday treats, welcome-back discounts and early access to seasonal flowers. Only from shops you've bought from, at most one every few days, and you can stop anytime in your profile.</p>
        </div>
        <div class="ot-offer-ask__actions">
          <button type="button" class="ot-offer-btn ot-offer-btn--yes" :disabled="offerBusy" @click="answerOffers(true)">Yes, send offers</button>
          <button type="button" class="ot-offer-btn" :disabled="offerBusy" @click="answerOffers(false)">Not now</button>
        </div>
      </section>
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
        <span class="ot-empty__ico" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M12 4 3 20h18L12 4Z" /><path d="M12 10v4M12 17h.01" /></svg>
        </span>
        <p class="ot-empty__title">Could not load orders</p>
        <p class="ot-empty__sub">{{ error }}</p>
        <button class="ot-btn ot-btn--green" @click="fetchOrders()">
          Try Again
        </button>
      </div>

      <!-- Empty -->
      <div v-else-if="!orders.length" class="ot-empty">
        <span class="ot-empty__ico" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M4 8l8-4 8 4v8l-8 4-8-4V8Z" /><path d="M4 8l8 4 8-4M12 12v8" /></svg>
        </span>
        <p class="ot-empty__title">No orders <em>yet</em></p>
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
            <div
              class="ot-card__top"
              role="button"
              tabindex="0"
              :aria-expanded="expandedId === order.id"
              @click="toggle(order.id)"
              @keydown.enter.prevent="toggle(order.id)"
              @keydown.space.prevent="toggle(order.id)"
            >
              <div class="ot-card__info">
                <div class="ot-card__row">
                  <span class="ot-card__num">{{ order.order_number }}</span>
                  <span class="ot-chip" :class="dlvChipClass(order)">{{
                    dlvLabel(order)
                  }}</span>
                  <span
                    v-if="caseIsOpen(order)"
                    class="ot-chip"
                    :class="order.return_case.next_actor === 'customer' ? 'chip--orange' : 'chip--blue'"
                  >
                    {{ order.return_case.next_actor === "customer" ? "Return/refund · your reply needed" : "Return/refund in progress" }}
                  </span>
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
                  <!-- Cancel is intentionally available only while Ordered. -->
                  <button
                    v-if="canCancel(order)"
                    class="ot-btn ot-btn--cancel"
                    :disabled="actionLoading === order.id + '_cancel'"
                    @click.stop="doCancel(order)"
                  >
                    <svg viewBox="0 0 20 20" fill="none" class="ot-btn__ico">
                      <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    {{ actionLoading === order.id + "_cancel" ? "Cancelling…" : "Cancel Order" }}
                  </button>
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

                  <!-- Return or refund -->
                  <button
                    v-if="order.can_open_return_case"
                    class="ot-btn ot-btn--orange"
                    @click.stop="openRequestModal(order)"
                  >
                    <svg viewBox="0 0 20 20" fill="none" class="ot-btn__ico">
                      <path d="M3 10a7 7 0 1 0 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                      <path d="M3 4v6h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Return or refund
                  </button>
                  <button
                    v-else-if="order.return_case"
                    class="ot-btn ot-btn--outline"
                    @click.stop="scrollToCase(order)"
                  >
                    View return/refund
                  </button>

                  <!-- ① Rate Products button (only for completed orders) -->
                  <button
                    v-if="canReview(order) && !caseIsOpen(order)"
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

                <!-- Return / refund case, settled with the shop -->
                <div v-if="order.return_case" :id="`case-${order.id}`" class="ot-sec">
                  <h4 class="ot-sec__title">Return / refund</h4>
                  <ReturnCasePanel
                    :case-id="order.return_case.id"
                    role="customer"
                    api-base="/customer/return-cases"
                    @changed="(detail) => onCaseChanged(order, detail)"
                  />
                </div>

                <!-- Questions to the shop about this order -->
                <div class="ot-sec">
                  <h4 class="ot-sec__title">Questions for the shop</h4>
                  <ProductQuestions :order-id="order.id" />
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
                      <div v-else class="ot-item__img ot-item__img--ph">
                        {{ item.product_name?.charAt(0) || "B" }}
                      </div>
                      <div class="ot-item__body">
                        <span class="ot-item__name">{{
                          item.product_name
                        }}</span>
                        <span class="ot-item__meta">
                          <span v-if="item.color">{{ item.color }}</span>
                          <span v-if="item.size"> · {{ item.size }}</span>
                          <span
                            ><template v-if="item.color || item.size"> · </template
                            >Qty {{ item.quantity }}</span
                          >
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
              <h3 class="ot-modal__title">Return or refund</h3>
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
            <!-- Keep the item, or send it back -->
            <div class="ot-field">
              <span class="ot-field__lbl">What happens to the item?</span>
              <div class="ot-choice">
                <label class="ot-choice__opt" :class="{ on: reqModal.type === 'refund' }">
                  <input v-model="reqModal.type" type="radio" value="refund" />
                  <span><strong>Keep it</strong><small>The flowers can't be sent back (e.g. wilted)</small></span>
                </label>
                <label class="ot-choice__opt" :class="{ on: reqModal.type === 'return' }">
                  <input v-model="reqModal.type" type="radio" value="return" />
                  <span><strong>Send it back</strong><small>You return the item to the shop</small></span>
                </label>
              </div>
            </div>

            <!-- What would make it right -->
            <div class="ot-field">
              <span class="ot-field__lbl">What would you like?</span>
              <div class="ot-choice ot-choice--3">
                <label
                  v-for="o in RESOLUTION_OPTIONS"
                  :key="o.value"
                  class="ot-choice__opt"
                  :class="{ on: reqModal.resolution === o.value, off: o.value !== 'replacement' && !reqModal.paid }"
                >
                  <input v-model="reqModal.resolution" type="radio" :value="o.value" :disabled="o.value !== 'replacement' && !reqModal.paid" />
                  <span><strong>{{ o.label }}</strong><small>{{ o.hint }}</small></span>
                </label>
              </div>
              <span v-if="!reqModal.paid" class="ot-field__hint">Refunds are available once the order is paid.</span>
              <div v-if="reqModal.resolution === 'partial_refund'" class="ot-amount">
                <label class="ot-field__lbl" for="ot-amount">Amount (order total ₱{{ fmt(reqModal.total) }})</label>
                <input id="ot-amount" v-model.number="reqModal.amount" type="number" min="1" step="0.01" :max="reqModal.total" class="ot-textarea" />
                <span v-if="reqModal.errors.amount" class="ot-field__err">{{ reqModal.errors.amount }}</span>
              </div>
              <span class="ot-field__hint">The shop can accept or suggest something else. You can message each other until you agree.</span>
            </div>

            <!-- What went wrong -->
            <div class="ot-field">
              <label class="ot-field__lbl" for="ot-reason-cat">What went wrong?</label>
              <select id="ot-reason-cat" v-model="reqModal.reasonCategory" class="ot-textarea">
                <option v-for="(label, key) in REASONS" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>

            <!-- Reason -->
            <div class="ot-field">
              <label class="ot-field__lbl">
                Tell the shop what happened <span class="ot-field__req">*</span>
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
              {{ reqModal.submitting ? "Sending…" : "Send request" }}
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
          <div
            class="ot-modal__ico-lg"
            :class="{ 'ot-modal__ico-lg--danger': confirmModal.variant === 'danger' }"
            aria-hidden="true"
          >
            <svg v-if="confirmModal.variant === 'danger'" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="9" />
              <path d="M9 9l6 6M15 9l-6 6" />
            </svg>
            <svg v-else viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
          </div>
          <h3 class="ot-modal__title">{{ confirmModal.title }}</h3>
          <p class="ot-modal__confirm-body">{{ confirmModal.body }}</p>
          <div class="ot-modal__footer">
            <button
              class="ot-btn ot-btn--ghost"
              @click="confirmModal.show = false"
            >
              Cancel
            </button>
            <button
              class="ot-btn"
              :class="confirmModal.variant === 'danger' ? 'ot-btn--cancel' : 'ot-btn--green'"
              :disabled="confirmModal.loading"
              @click="confirmModal.onConfirm"
            >
              {{ confirmModal.loading ? confirmModal.loadingLabel : confirmModal.confirmLabel }}
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
import { ref, reactive, onMounted, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../../plugins/axios";
import { useAuth } from "../../composables/useAuth";
import NavHeader from "../../layouts/NavHeader.vue";
import ReviewModal from "../../layouts/components/ReviewModal.vue";
import ReturnCasePanel from "../../components/returns/ReturnCasePanel.vue";
import ProductQuestions from "../../layouts/components/ProductQuestions.vue";
import "../../styles/bloomcraft.css";
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
  type: "refund",
  resolution: "full_refund",
  amount: null,
  reasonCategory: "damaged",
  paid: false,
  total: 0,
  orderId: null,
  orderNumber: "",
  reason: "",
  mediaFile: null,
  mediaPreviewUrl: null,
  mediaPreviewType: null,
  submitting: false,
  errors: { reason: "", media: "", amount: "" },
});
const RESOLUTION_OPTIONS = [
  { value: "full_refund", label: "Full refund", hint: "All your money back" },
  { value: "partial_refund", label: "Partial refund", hint: "Part of it back" },
  { value: "replacement", label: "Replacement", hint: "Send it again" },
];
const REASONS = {
  damaged: "Arrived damaged or wilted",
  wrong_item: "Wrong item or arrangement",
  missing_item: "Missing items",
  not_as_described: "Not as described or pictured",
  late: "Arrived late",
  quality: "Poor quality",
  other: "Something else",
};

// Simple confirm modal
const confirmModal = reactive({
  show: false,
  title: "Confirm Delivery",
  icon: "✅",
  body: "",
  confirmLabel: "Yes, I received it",
  loadingLabel: "Confirming…",
  variant: "success",
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
  { label: "Returns & refunds", value: "returns" },
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
// A return/refund case still being settled
const caseIsOpen = (o) => ["pending", "approved"].includes(o.return_case?.status);
const isPaidEWallet = (o) =>
  ["ewallet", "gcash", "maya", "paymaya"].includes(
    String(o.payment_method || "").toLowerCase(),
  ) && o.payment_status === "paid";
const canCancel = (o) =>
  ["pending", "processing"].includes(o.status) &&
  (!o.delivery || o.delivery.status === "pending") &&
  !isPaidEWallet(o);
const showActions = (o) =>
  canCancel(o) || canComplete(o) || o.can_open_return_case || o.return_case || canReview(o);

// ① canReview computed helper — true if order is completed
const canReview = (order) =>
  order.delivery?.status === "completed" || order.status === "completed";

// True if already reviewed in this session (disables the button)
const hasReviewed = (order) => reviewedOrderIds.value.has(order.id);

// ── Mark as received ──────────────────────────────────────────────────────────
function doComplete(order) {
  confirmModal.show = true;
  confirmModal.loading = false;
  confirmModal.title = "Confirm Delivery";
  confirmModal.icon = "✅";
  confirmModal.body = `Have you received order ${order.order_number}? This will mark it as delivered.`;
  confirmModal.confirmLabel = "Yes, I received it";
  confirmModal.loadingLabel = "Confirming…";
  confirmModal.variant = "success";
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
      toast.error(e.response?.data?.message ?? "That didn't go through. Please try again.");
    } finally {
      confirmModal.loading = false;
      actionLoading.value = null;
    }
  };
}

function doCancel(order) {
  confirmModal.show = true;
  confirmModal.loading = false;
  confirmModal.title = "Cancel Delivery";
  confirmModal.icon = "?";
  confirmModal.body = `Are you sure you want to cancel order ${order.order_number}?`;
  confirmModal.confirmLabel = "Yes, cancel my order";
  confirmModal.loadingLabel = "Cancelling…";
  confirmModal.variant = "danger";
  confirmModal.onConfirm = async () => {
    confirmModal.loading = true;
    actionLoading.value = order.id + "_cancel";
    try {
      const response = await api.post(
        `${API_BASE}/${order.id}/cancel`,
        {},
        { headers: authHeaders() },
      );
      confirmModal.show = false;
      toast.success(response.data?.message || "The order was cancelled.", {
        autoClose: 3500,
        position: toast.POSITION.TOP_RIGHT,
      });
      await fetchOrders(meta.current_page);
    } catch (e) {
      toast.error(e.response?.data?.message || "Unable to cancel this order.", {
        autoClose: 3500,
        position: toast.POSITION.TOP_RIGHT,
      });
    } finally {
      confirmModal.loading = false;
      actionLoading.value = null;
    }
  };
}

// ── Return / Refund modal ─────────────────────────────────────────────────────
function openRequestModal(order) {
  const paid = order.payment_status === "paid";
  Object.assign(reqModal, {
    show: true,
    type: "refund",
    resolution: paid ? "full_refund" : "replacement",
    amount: null,
    reasonCategory: "damaged",
    paid,
    total: Number(order.total_amount || 0),
    orderId: order.id,
    orderNumber: order.order_number,
    reason: "",
    mediaFile: null,
    mediaPreviewUrl: null,
    mediaPreviewType: null,
    submitting: false,
    errors: { reason: "", media: "", amount: "" },
  });
}

// Keeps the order card in step with the case as it moves along
async function onCaseChanged(order, detail) {
  order.return_case = { ...order.return_case, ...detail };
  if (["completed", "rejected", "withdrawn"].includes(detail.status) || detail.item_received_at) {
    try {
      const { data } = await api.get(`${API_BASE}/${order.id}`, { headers: authHeaders() });
      Object.assign(order, data.data ?? {});
    } catch {
      // the next page load picks it up
    }
  }
}

function scrollToCase(order) {
  expandedId.value = order.id;
  nextTick(() => document.getElementById(`case-${order.id}`)?.scrollIntoView({ behavior: "smooth", block: "start" }));
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
  reqModal.errors.amount = "";

  if (reqModal.resolution === "partial_refund") {
    const amount = Number(reqModal.amount);
    if (!amount || amount <= 0 || amount >= reqModal.total) {
      reqModal.errors.amount = `Enter an amount above ₱0 and below ₱${fmt(reqModal.total)}.`;
      ok = false;
    }
  }

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
  const orderId = reqModal.orderId;

  try {
    const fd = new FormData();
    fd.append("type", reqModal.type);
    fd.append("resolution", reqModal.resolution);
    if (reqModal.resolution === "partial_refund") fd.append("amount", String(reqModal.amount));
    fd.append("reason_category", reqModal.reasonCategory);
    fd.append("reason", reqModal.reason);
    if (reqModal.mediaFile) fd.append("media", reqModal.mediaFile);

    const { data } = await api.post(`${API_BASE}/${orderId}/return-case`, fd, {
      headers: {
        ...authHeaders(),
        "Content-Type": "multipart/form-data",
      },
    });

    reqModal.submitting = false;
    closeReqModal();
    toast.success(data.message || "Request sent to the shop.");
    await fetchOrders(meta.current_page);
    scrollToCase({ id: orderId });
  } catch (e) {
    const msg =
      e.response?.data?.message ?? "Submission failed. Please try again.";
    if (e.response?.status === 422 && e.response.data?.errors) {
      const errs = e.response.data.errors;
      reqModal.errors.reason = errs.reason?.[0] ?? "";
      reqModal.errors.media = errs.media?.[0] ?? "";
      reqModal.errors.amount = errs.amount?.[0] ?? "";
    } else {
      toast.error(msg);
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
  if (order.status === "cancelled") return "Cancelled";
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
  if (order.status === "cancelled") return "chip--red";
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

// The one-time question about shop offers
const offerAsk = ref(false);
const offerBusy = ref(false);
async function loadOfferAsk() {
  try {
    const { data } = await api.get("/customer/preferences");
    offerAsk.value = !!data?.data?.offers_prompt;
  } catch {
    offerAsk.value = false;
  }
}
async function answerOffers(accept) {
  offerBusy.value = true;
  try {
    const { data } = await api.post("/customer/offers/consent", { accept, source: "prompt" });
    offerAsk.value = false;
    toast.success(data.message);
  } catch {
    toast.error("Couldn't save that. You can choose in your profile anytime.");
  } finally {
    offerBusy.value = false;
  }
}

onMounted(async () => {
  await fetchOrders();
  loadOfferAsk();

  if (route.query.payment === "success") {
    toast.success("Payment confirmed. Your order has been updated.", {
      position: toast.POSITION.TOP_RIGHT,
    });

    const nextQuery = { ...route.query };
    delete nextQuery.payment;
    delete nextQuery.reference;

    router.replace({ query: nextQuery });
  } else if (route.query.ordered === "success") {
    toast.success("Order placed successfully! You can track it here.", {
      position: toast.POSITION.TOP_RIGHT,
    });

    const nextQuery = { ...route.query };
    delete nextQuery.ordered;
    delete nextQuery.order_id;

    router.replace({ query: nextQuery });
  }
});
</script>

<style scoped>
/* Tokens, type, labels, buttons, skeletons and the navbar come from
   src/styles/bloomcraft.css (shared with the landing page and shop pages). */

@layer bc-reset {
  .ot-page :is(p, h1, h2, h3, h4) {
    margin: 0;
  }
}

.ot-choice {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.ot-choice--3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
.ot-choice__opt {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  padding: 10px;
  border: 1px solid var(--line, #e2ddd5);
  border-radius: 12px;
  cursor: pointer;
  font-size: 13px;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.ot-choice__opt span {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.ot-choice__opt small {
  color: var(--ink-soft, #6b6560);
  font-size: 11.5px;
}
.ot-choice__opt.on {
  border-color: var(--return-accent, #3f6f5a);
  box-shadow: 0 0 0 2px var(--return-accent-soft, #e8f1ec);
}
.ot-choice__opt.off {
  opacity: 0.45;
  cursor: not-allowed;
}
.ot-choice__opt input {
  margin-top: 2px;
  accent-color: var(--return-accent, #3f6f5a);
}
.ot-amount {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 8px;
}
@media (max-width: 560px) {
  .ot-choice,
  .ot-choice--3 {
    grid-template-columns: minmax(0, 1fr);
  }
}

:global(body) {
  margin: 0;
}

.ot-page {
  /* Status colours (muted to sit with the ivory palette) */
  --ok: #5d7150;
  --ok-bg: rgba(125, 143, 105, 0.14);
  --warn: #8a6420;
  --warn-bg: rgba(212, 178, 122, 0.2);
  --info: #4b6075;
  --info-bg: rgba(75, 96, 117, 0.1);
  --bad: var(--leaf-deep);
  --bad-bg: rgba(77, 177, 115, 0.1);

  min-height: 100vh;
  padding: 150px var(--gutter) var(--section-space);
  background: var(--ivory);
}

.ot-page button {
  font-family: var(--font-sans);
}

/* ── Header ──────────────────────────────────────────────────────────────── */
.ot-header {
  max-width: 1240px;
  margin: 0 auto 40px;
}

.ot-header__inner {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 24px;
  margin-bottom: 36px;
}

.ot-header__title {
  margin-top: 22px;
  font-size: clamp(48px, 5.6vw, 84px);
}

.ot-header__sub {
  margin-top: 10px;
  color: var(--ink-soft);
  font-size: 15px;
}

.ot-search-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  width: min(100%, 340px);
  padding: 0 4px 10px;
  border-bottom: 1px solid var(--line);
  transition: border-color 0.3s;
}

.ot-search-wrap:focus-within {
  border-color: var(--ink);
}

.ot-search-ico {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  color: var(--ink-soft);
}

.ot-search {
  flex: 1;
  min-width: 0;
  border: none;
  outline: none;
  background: none;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 15px;
}

.ot-search::placeholder {
  color: var(--ink-soft);
}

.ot-tabs {
  display: flex;
  gap: 28px;
  overflow-x: auto;
  border-bottom: 1px solid var(--line);
  scrollbar-width: none;
}

.ot-tabs::-webkit-scrollbar {
  display: none;
}

.ot-tab {
  flex-shrink: 0;
  padding: 0 0 14px;
  border: none;
  border-bottom: 1px solid transparent;
  margin-bottom: -1px;
  background: none;
  color: var(--ink-soft);
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition:
    color 0.3s,
    border-color 0.3s;
}

.ot-tab:hover {
  color: var(--ink);
}

.ot-tab.active {
  color: var(--ink);
  border-bottom-color: var(--ink);
}

/* ── Content ─────────────────────────────────────────────────────────────── */
.ot-content {
  max-width: 1240px;
  margin: 0 auto;
}

.ot-offer-ask {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
  padding: 18px 20px;
  border: 1px solid color-mix(in srgb, var(--leaf) 45%, #fff);
  border-radius: 18px;
  background: color-mix(in srgb, var(--leaf) 8%, #fff);
}
.ot-offer-ask__icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border-radius: 14px;
  background: #fff;
  color: var(--leaf-deep);
}
.ot-offer-ask__text {
  flex: 1;
  min-width: 0;
}
.ot-offer-ask__text h2 {
  margin: 0 0 4px;
  font-family: var(--font-sans);
  font-size: 16px;
  font-weight: 600;
  color: var(--ink);
}
.ot-offer-ask__text p {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.55;
  color: var(--ink-soft);
}
.ot-offer-ask__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
/* Both answers look like real buttons, the same size */
.ot-offer-btn {
  min-width: 140px;
  min-height: 44px;
  padding: 0 18px;
  border: 1px solid var(--ink);
  border-radius: 999px;
  background: #fff;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease;
}
.ot-offer-btn:hover:not(:disabled) {
  background: var(--ivory-deep);
}
.ot-offer-btn--yes {
  background: var(--ink);
  color: var(--ivory);
}
.ot-offer-btn--yes:hover:not(:disabled) {
  background: var(--leaf-deep);
}
.ot-offer-btn:disabled {
  opacity: 0.6;
  cursor: default;
}
.ot-offer-btn:focus-visible {
  outline: 2px solid var(--leaf-deep);
  outline-offset: 2px;
}
@media (max-width: 760px) {
  .ot-offer-ask {
    flex-wrap: wrap;
  }
  .ot-offer-ask__actions {
    width: 100%;
  }
  .ot-offer-btn {
    flex: 1;
  }
}

.ot-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Skeleton */
.ot-skeletons {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ot-skel {
  padding: 28px 30px;
  border: 1px solid var(--line-soft);
  border-radius: 20px;
  background: var(--paper);
}

.ot-skel__top {
  display: flex;
  justify-content: space-between;
  margin-bottom: 26px;
}

.ot-skel__line,
.ot-skel__step {
  border-radius: 10px;
  background: linear-gradient(100deg, var(--ivory-deep) 30%, var(--paper) 50%, var(--ivory-deep) 70%);
  background-size: 300% 100%;
  animation: otShimmer 1.6s ease-in-out infinite;
}

.ot-skel__line--title {
  width: 36%;
  height: 22px;
}

.ot-skel__line--badge {
  width: 90px;
  height: 22px;
  border-radius: 999px;
}

.ot-skel__row {
  display: flex;
  gap: 12px;
}

.ot-skel__step {
  flex: 1;
  height: 10px;
}

@keyframes otShimmer {
  from {
    background-position: 100% 0;
  }
  to {
    background-position: 0 0;
  }
}

/* Empty / error */
.ot-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 80px 20px;
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
  text-align: center;
}

.ot-empty__ico {
  width: 40px;
  height: 40px;
  margin-bottom: 6px;
  color: var(--ink-soft);
}

.ot-empty__ico svg {
  width: 100%;
  height: 100%;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.3;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ot-empty__title {
  font-family: var(--font-display);
  font-size: 40px;
  font-weight: 500;
  line-height: 1.1;
}

.ot-empty__sub {
  margin-bottom: 14px;
  color: var(--ink-soft);
}

/* ── Card ────────────────────────────────────────────────────────────────── */
.ot-card {
  overflow: hidden;
  border: 1px solid var(--line-soft);
  border-radius: 20px;
  background: var(--paper);
  transition: border-color 0.3s;
}

.ot-card--open {
  border-color: var(--line);
}

.ot-card__top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  padding: 26px 30px 20px;
  cursor: pointer;
}

.ot-card__info {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

.ot-card__row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.ot-card__row--sm {
  gap: 8px;
  color: var(--ink-soft);
  font-size: 13px;
}

.ot-card__num {
  font-family: var(--font-display);
  font-size: 26px;
  font-weight: 500;
  line-height: 1.1;
}

.ot-ico-store {
  width: 14px;
  height: 14px;
}

.ot-card__store {
  color: var(--ink);
  font-weight: 500;
}

.ot-dot {
  color: var(--leaf);
}

.ot-card__aside {
  display: flex;
  align-items: center;
  gap: 18px;
  flex-shrink: 0;
  text-align: right;
}

.ot-card__total-lbl {
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.ot-card__total {
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 500;
  line-height: 1.1;
}

.ot-chevron {
  width: 20px;
  height: 20px;
  color: var(--ink-soft);
  transition: transform 0.35s var(--ease-out);
}

.ot-chevron.up {
  transform: rotate(180deg);
}

/* Status chips */
.ot-chip,
.ot-pay-chip,
.ot-req-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 11px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  white-space: nowrap;
}

.chip--green,
.pay--green,
.req-badge--green {
  background: var(--ok-bg);
  color: var(--ok);
}

.chip--orange,
.pay--yellow,
.req-badge--yellow {
  background: var(--warn-bg);
  color: var(--warn);
}

.chip--blue {
  background: var(--info-bg);
  color: var(--info);
}

.chip--red,
.pay--red,
.req-badge--red {
  background: var(--bad-bg);
  color: var(--bad);
}

/* ── Tracking stepper ────────────────────────────────────────────────────── */
.ot-track {
  padding: 20px 30px 26px;
  border-top: 1px solid var(--line-soft);
}

.ot-track__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 22px;
}

.ot-track__label {
  color: var(--ink-soft);
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.2em;
  text-transform: uppercase;
}

.ot-stepper {
  display: flex;
  align-items: flex-start;
}

.ot-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  width: 92px;
  flex-shrink: 0;
  text-align: center;
}

.ot-dot-circle {
  position: relative;
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: var(--paper);
}

.ot-dot-circle svg {
  width: 12px;
  height: 12px;
}

.ot-step.done .ot-dot-circle {
  background: var(--ink);
  border-color: var(--ink);
}

.ot-step.active .ot-dot-circle {
  border-color: var(--ink);
}

.ot-pulse {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--leaf);
  animation: otPulse 1.8s ease-out infinite;
}

@keyframes otPulse {
  0% {
    box-shadow: 0 0 0 0 rgba(77, 177, 115, 0.45);
  }
  70% {
    box-shadow: 0 0 0 8px rgba(77, 177, 115, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(77, 177, 115, 0);
  }
}

.ot-conn {
  flex: 1;
  height: 1px;
  margin-top: 12px;
  background: var(--line);
}

.ot-conn.filled {
  background: var(--ink);
}

.ot-step__lbl {
  color: var(--ink-soft);
  font-size: 12px;
  line-height: 1.3;
}

.ot-step.done .ot-step__lbl,
.ot-step.active .ot-step__lbl {
  color: var(--ink);
  font-weight: 500;
}

.ot-step__ts {
  color: var(--ink-soft);
  font-size: 10.5px;
}

.ot-note {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 22px;
  padding: 12px 16px;
  border-radius: 12px;
  background: var(--ivory);
  color: var(--ink-soft);
  font-size: 13px;
}

.ot-note__ico {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.ot-note__text strong {
  color: var(--ink);
  font-weight: 500;
}

.ot-barcode {
  margin-left: auto;
  padding: 3px 8px;
  border-radius: 6px;
  background: var(--paper);
  color: var(--ink);
  font-size: 12px;
}

/* ── Expanded detail ─────────────────────────────────────────────────────── */
.ot-detail {
  display: flex;
  flex-direction: column;
  gap: 28px;
  padding: 26px 30px 30px;
  border-top: 1px solid var(--line-soft);
}

.ot-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.ot-sec {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.ot-sec__title {
  color: var(--ink-soft);
  font-family: var(--font-sans);
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.2em;
  text-transform: uppercase;
}

.ot-info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  border-top: 1px solid var(--line);
}

.ot-info-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px 20px 12px 0;
  border-bottom: 1px solid var(--line-soft);
}

.ot-info-lbl {
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.ot-info-val {
  font-size: 14.5px;
  word-break: break-word;
}

/* Requests */
.ot-request-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 12px;
}

.ot-req-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 18px 20px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #fff;
}

.ot-req-card__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}

.ot-req-card__type {
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 500;
}

.ot-req-card__reason {
  color: var(--ink-soft);
  font-size: 13.5px;
  line-height: 1.6;
}

.ot-media-preview {
  max-width: 100%;
  max-height: 200px;
  border-radius: 10px;
}

.ot-request-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 999px;
  background: var(--warn-bg);
  color: var(--warn);
  font-size: 13px;
  font-weight: 500;
  text-transform: capitalize;
}

/* Timeline */
.ot-tl {
  display: flex;
  flex-direction: column;
}

.ot-tl__item {
  position: relative;
  display: flex;
  gap: 16px;
  padding-bottom: 18px;
}

.ot-tl__dot {
  flex-shrink: 0;
  width: 9px;
  height: 9px;
  margin-top: 6px;
  border-radius: 50%;
  background: var(--ink);
}

.ot-tl__item:not(:first-child) .ot-tl__dot {
  background: var(--paper);
  border: 1px solid var(--ink-soft);
}

.ot-tl__line {
  position: absolute;
  top: 18px;
  bottom: 0;
  left: 4px;
  width: 1px;
  background: var(--line);
}

.ot-tl__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ot-tl__status {
  font-size: 14px;
  font-weight: 500;
}

.ot-tl__ts,
.ot-tl__notes {
  color: var(--ink-soft);
  font-size: 12.5px;
}

/* Items */
.ot-items {
  border-top: 1px solid var(--line);
}

.ot-item {
  display: grid;
  grid-template-columns: 56px minmax(0, 1fr) auto;
  gap: 16px;
  align-items: center;
  padding: 14px 0;
  border-bottom: 1px solid var(--line-soft);
}

.ot-item__img {
  width: 56px;
  height: 70px;
  border-radius: 10px;
  object-fit: cover;
  background: var(--ivory-deep);
}

.ot-item__img--ph {
  display: grid;
  place-items: center;
  color: var(--leaf-deep);
  font-family: var(--font-display);
  font-size: 24px;
}

.ot-item__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.ot-item__name {
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 500;
  line-height: 1.15;
}

.ot-item__meta {
  color: var(--ink-soft);
  font-size: 12.5px;
  text-transform: capitalize;
}

.ot-item__price {
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 500;
}

/* Totals */
.ot-totals {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-self: flex-end;
  width: min(100%, 320px);
}

.ot-totals__row {
  display: flex;
  justify-content: space-between;
  color: var(--ink-soft);
  font-size: 14px;
}

.ot-totals__row--bold {
  align-items: baseline;
  padding-top: 12px;
  border-top: 1px solid var(--line);
  color: var(--ink);
  font-weight: 500;
}

.ot-totals__row--bold span:last-child {
  font-family: var(--font-display);
  font-size: 30px;
}

/* Pager */
.ot-pager {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 20px;
  margin-top: 48px;
}

.ot-pager__info {
  color: var(--ink-soft);
  font-size: 13px;
}

/* ── Buttons (same shape and motion as .bc-btn) ──────────────────────────── */
.ot-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 22px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: transparent;
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition:
    background 0.45s var(--ease-out),
    color 0.45s var(--ease-out),
    border-color 0.45s var(--ease-out);
}

.ot-btn:hover:not(:disabled) {
  border-color: var(--ink);
}

.ot-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.ot-btn__ico {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

/* Primary action */
.ot-btn--green {
  background: var(--ink);
  border-color: var(--ink);
  color: var(--ivory);
}

.ot-btn--green:hover:not(:disabled) {
  background: var(--leaf-deep);
  border-color: var(--leaf-deep);
}

/* Destructive */
.ot-btn--cancel {
  border-color: rgba(46, 122, 79, 0.35);
  color: var(--leaf-deep);
}

.ot-btn--cancel:hover:not(:disabled) {
  background: var(--leaf-deep);
  border-color: var(--leaf-deep);
  color: var(--ivory);
}

/* Secondary (return) and tertiary (refund / review / ghost) */
.ot-btn--orange {
  border-color: rgba(212, 178, 122, 0.8);
  color: var(--warn);
}

.ot-btn--orange:hover:not(:disabled) {
  border-color: var(--warn);
}

.ot-btn--star .ot-btn__ico {
  color: var(--gold) !important;
}

.ot-btn:focus-visible,
.ot-tab:focus-visible,
.ot-card__top:focus-visible,
.ot-modal__close:focus-visible,
.ot-dropzone__remove:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

/* ── Modals ──────────────────────────────────────────────────────────────── */
.ot-overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(38, 45, 57, 0.45);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

.ot-modal {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 540px;
  max-height: calc(100vh - 40px);
  overflow: hidden;
  border-radius: 24px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(38, 45, 57, 0.25);
}

.ot-modal--sm {
  max-width: 420px;
  align-items: center;
  gap: 10px;
  padding: 36px 32px 28px;
  text-align: center;
}

.ot-modal__head {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 24px 26px 18px;
  border-bottom: 1px solid var(--line-soft);
}

.ot-modal__head-ico {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: var(--ivory);
  color: var(--ink);
}

.ot-modal__head--orange .ot-modal__head-ico {
  color: var(--warn);
}

.ot-modal__head-ico svg {
  width: 20px;
  height: 20px;
}

.ot-modal__title {
  font-family: var(--font-display);
  font-size: 30px;
  font-weight: 500;
  line-height: 1.1;
}

.ot-modal__sub {
  color: var(--ink-soft);
  font-size: 13px;
}

.ot-modal__close {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  margin-left: auto;
  border: none;
  border-radius: 50%;
  background: var(--ivory);
  color: var(--ink);
  cursor: pointer;
}

.ot-modal__close svg {
  width: 16px;
  height: 16px;
}

.ot-modal__body {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 22px 26px;
  overflow-y: auto;
}

.ot-modal__ico-lg {
  display: grid;
  place-items: center;
  width: 54px;
  height: 54px;
  margin-bottom: 6px;
  border-radius: 50%;
  background: var(--ivory);
  color: var(--ink);
}

.ot-modal__ico-lg--danger {
  color: var(--leaf-deep);
}

.ot-modal__ico-lg svg {
  width: 24px;
  height: 24px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ot-modal__confirm-body {
  color: var(--ink-soft);
  font-size: 14.5px;
  line-height: 1.7;
}

.ot-modal__footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 18px 26px 24px;
  border-top: 1px solid var(--line-soft);
}

.ot-modal--sm .ot-modal__footer {
  justify-content: center;
  width: 100%;
  margin-top: 14px;
  padding: 18px 0 0;
}

/* Form */
.ot-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ot-field__lbl {
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.ot-field__req {
  color: var(--leaf-deep);
}

.ot-field__optional {
  letter-spacing: 0;
  text-transform: none;
}

.ot-textarea {
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

.ot-textarea:focus {
  outline: none;
  border-color: var(--ink);
}

.ot-textarea--err {
  border-color: var(--leaf-deep);
}

.ot-field__err {
  color: var(--leaf-deep);
  font-size: 12.5px;
}

.ot-field__hint {
  align-self: flex-end;
  color: var(--ink-soft);
  font-size: 11.5px;
}

.ot-dropzone {
  position: relative;
  padding: 22px;
  border: 1px dashed var(--line);
  border-radius: 14px;
  background: #fff;
  cursor: pointer;
  transition:
    border-color 0.3s,
    background 0.3s;
}

.ot-dropzone:hover,
.ot-dropzone--drag {
  border-color: var(--ink);
  background: var(--ivory);
}

.ot-dropzone--err {
  border-color: var(--leaf-deep);
}

.ot-dropzone--filled {
  border-style: solid;
}

.ot-dropzone__input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.ot-dropzone__placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  color: var(--ink-soft);
  text-align: center;
}

.ot-dropzone__ico {
  width: 40px;
  height: 40px;
}

.ot-dropzone__text {
  font-size: 13.5px;
  line-height: 1.6;
}

.ot-dropzone__text strong {
  color: var(--ink);
  font-weight: 500;
}

.ot-dropzone__hint {
  font-size: 12px;
}

.ot-dropzone__preview {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
}

.ot-preview-media {
  width: 84px;
  height: 84px;
  border-radius: 10px;
  object-fit: cover;
}

.ot-dropzone__file-info {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.ot-dropzone__file-name {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ot-dropzone__file-size {
  color: var(--ink-soft);
  font-size: 12px;
}

.ot-dropzone__remove {
  position: relative;
  z-index: 1;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 50%;
  background: var(--ivory);
  color: var(--ink);
  cursor: pointer;
}

.ot-dropzone__remove svg {
  width: 14px;
  height: 14px;
}

/* ── Transitions ─────────────────────────────────────────────────────────── */
.expand-enter-active,
.expand-leave-active {
  transition:
    opacity 0.35s ease,
    transform 0.35s var(--ease-out);
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-active .ot-modal,
.modal-leave-active .ot-modal {
  transition: transform 0.4s var(--ease-out);
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .ot-modal,
.modal-leave-to .ot-modal {
  transform: translateY(16px);
}

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .ot-page {
    padding-top: 120px;
  }

  .ot-search-wrap {
    width: 100%;
  }

  .ot-card__top {
    flex-direction: column;
    align-items: stretch;
    padding: 22px 20px 16px;
  }

  .ot-card__aside {
    justify-content: space-between;
    text-align: left;
  }

  .ot-track,
  .ot-detail {
    padding-inline: 20px;
  }

  /* Five steps fit on a phone: narrower columns, smaller labels */
  .ot-step {
    width: 52px;
    gap: 6px;
  }

  .ot-step__lbl {
    font-size: 10.5px;
  }

  .ot-step__ts {
    display: none;
  }

  .ot-conn {
    min-width: 6px;
  }

  .ot-info-grid {
    grid-template-columns: 1fr;
  }

  .ot-actions .ot-btn {
    flex: 1 1 auto;
  }

  .ot-totals {
    width: 100%;
  }

  .ot-overlay {
    align-items: flex-end;
    padding: 0;
  }

  .ot-modal {
    max-height: 94vh;
    border-radius: 22px 22px 0 0;
  }

  .ot-modal--sm {
    border-radius: 22px 22px 0 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ot-skel__line,
  .ot-skel__step,
  .ot-pulse {
    animation: none;
  }

  .expand-enter-active,
  .expand-leave-active,
  .modal-enter-active,
  .modal-leave-active,
  .modal-enter-active .ot-modal,
  .modal-leave-active .ot-modal {
    transition: none;
  }
}
</style>
