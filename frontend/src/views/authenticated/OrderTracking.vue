<template>
  <NavHeader
    ref="navHeaderRef"
    :cartCount="cartCount"
    :isAuthenticated="isAuthenticated"
    @scroll-to-section="scrollToSection"
  />
  <div class="ot-page bc-theme">
    <!-- ── Header: title, search, status tabs ───────────────────────────── -->
    <header class="ot-head">
      <div class="ot-wrap">
        <div class="ot-head__top">
          <div>
            <p class="bc-label">Your orders</p>
            <h1 class="ot-title">My <em>orders</em></h1>
            <p class="ot-lede">Follow each bouquet from the florist's bench to your door.</p>
          </div>
          <label class="ot-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" /></svg>
            <input
              v-model="search"
              type="search"
              aria-label="Search orders or shops"
              placeholder="Search by order number or shop"
              @input="debounceSearch"
            />
          </label>
        </div>

        <nav class="ot-tabs" aria-label="Filter orders by status">
          <button
            v-for="tab in statusTabs"
            :key="tab.value"
            type="button"
            class="ot-tab"
            :class="{ on: !focusOrder && activeTab === tab.value }"
            :aria-pressed="!focusOrder && activeTab === tab.value"
            @click="setTab(tab.value)"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>
    </header>

    <main class="ot-wrap ot-body">
      <!-- Asked once, after their first order: offers from shops they buy from -->
      <section v-if="offerAsk" class="ot-offer" aria-labelledby="ot-offer-title" aria-live="polite">
        <span class="ot-offer__ico" aria-hidden="true">
          <svg viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="4" rx="1" /><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5" /></svg>
        </span>
        <div class="ot-offer__text">
          <h2 id="ot-offer-title">Want offers from shops you order from?</h2>
          <p>Birthday treats, welcome-back discounts and early access to seasonal flowers. Only from shops you've bought from, at most one every few days, and you can stop anytime in your profile.</p>
        </div>
        <div class="ot-offer__actions">
          <button type="button" class="ot-btn ot-btn--primary" :disabled="offerBusy" @click="answerOffers(true)">Yes, send offers</button>
          <button type="button" class="ot-btn ot-btn--quiet" :disabled="offerBusy" @click="answerOffers(false)">Not now</button>
        </div>
      </section>

      <!-- Loading: cards in the same shape -->
      <div v-if="loading" class="ot-list" aria-label="Loading orders">
        <div v-for="n in 3" :key="n" class="ot-card ot-card--skel">
          <div class="ot-card__head">
            <span class="bc-skeleton ot-skel-thumb"></span>
            <span class="ot-skel-lines">
              <span class="bc-skeleton ot-skel-line w40"></span>
              <span class="bc-skeleton ot-skel-line w60"></span>
            </span>
            <span class="bc-skeleton ot-skel-line w20"></span>
          </div>
          <span class="bc-skeleton ot-skel-bar"></span>
        </div>
      </div>

      <!-- Couldn't load -->
      <div v-else-if="error" class="ot-empty">
        <span class="ot-empty__ico red" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M12 4 3 20h18L12 4Z" /><path d="M12 10v4M12 17h.01" /></svg>
        </span>
        <h2>We couldn't load your orders</h2>
        <p>{{ error }}</p>
        <button type="button" class="ot-btn ot-btn--primary" @click="fetchOrders()">Try again</button>
      </div>

      <!-- Nothing here -->
      <div v-else-if="!orders.length && !focusOrder" class="ot-empty">
        <span class="ot-empty__ico" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M4 8l8-4 8 4v8l-8 4-8-4V8Z" /><path d="M4 8l8 4 8-4M12 12v8" /></svg>
        </span>
        <h2>{{ search.trim() || activeTab !== "all" ? "No orders match" : "No orders yet" }}</h2>
        <p>{{ search.trim() || activeTab !== "all" ? "Try another tab or a different search." : "When you order flowers, you can follow every step here." }}</p>
        <router-link v-if="!search.trim() && activeTab === 'all'" to="/shop" class="ot-btn ot-btn--primary">Browse flowers</router-link>
      </div>

      <!-- Opened from a notification, but that order is gone -->
      <div v-else-if="focusOrder && !orders.length" class="ot-empty">
        <span class="ot-empty__ico" aria-hidden="true">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" /></svg>
        </span>
        <h2>We couldn't find order {{ focusOrder }}</h2>
        <p>It may have been removed. Your other orders are still here.</p>
        <button type="button" class="ot-btn ot-btn--primary" @click="showAllOrders">View all orders</button>
      </div>

      <template v-else>
        <div v-if="focusOrder" class="ot-focus" role="status">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15L6 16Z" /><path d="M10 20a2 2 0 0 0 4 0" /></svg>
          <span>Showing order <strong>{{ focusOrder }}</strong> from your notifications.</span>
        </div>

        <div class="ot-list">
          <article
            v-for="order in orders"
            :id="`order-card-${order.id}`"
            :key="order.id"
            class="ot-card"
            :class="[`tone-${statusTone(order)}`, { open: isOpen(order.id) }]"
          >
            <!-- ── Summary ───────────────────────────────────────────── -->
            <div class="ot-card__head">
              <div class="ot-thumbs" aria-hidden="true">
                <template v-if="thumbs(order).length">
                  <img v-for="(src, t) in thumbs(order)" :key="t" :src="src" alt="" loading="lazy" />
                </template>
                <span v-else class="ot-thumbs__ph">{{ (order.store_name || "B").charAt(0) }}</span>
                <span v-if="moreItems(order)" class="ot-thumbs__more">+{{ moreItems(order) }}</span>
              </div>

              <div class="ot-card__id">
                <p class="ot-card__num">{{ order.order_number }}</p>
                <p class="ot-card__meta">
                  <span class="ot-card__store">{{ order.store_name || "BloomCraft shop" }}</span>
                  <span aria-hidden="true">·</span>
                  <span>{{ fmtDate(order.created_at) }}</span>
                  <span aria-hidden="true">·</span>
                  <span>{{ order.items_count }} {{ order.items_count === 1 ? "item" : "items" }}</span>
                </p>
              </div>

              <div class="ot-card__end">
                <span class="ot-status" :class="`tone-${statusTone(order)}`"><i aria-hidden="true"></i>{{ dlvLabel(order) }}</span>
                <p class="ot-card__total">₱{{ fmt(order.total_amount) }}</p>
                <button
                  v-if="order.payment_failure"
                  type="button"
                  class="ot-pay"
                  :class="payTone(order.payment_status)"
                  :aria-label="`Payment ${order.payment_status}. See why order ${order.order_number} didn't go through`"
                  @click="openPaymentFailure(order)"
                >
                  {{ payLabel(order.payment_status) }}
                  <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.25" /><path d="M8 7.25v3.5M8 5.1v.05" /></svg>
                </button>
                <span v-else class="ot-pay" :class="payTone(order.payment_status)">{{ payLabel(order.payment_status) }}</span>
              </div>
            </div>

            <!-- Outside the shop's delivery areas: the shop approves it first -->
            <p v-if="order.outside_area && order.status === 'pending'" class="ot-case-note" :class="order.outside_area.approved ? 'blue' : 'amber'" role="status">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z" /><circle cx="12" cy="9.5" r="2.5" /></svg>
              <template v-if="order.outside_area.approved">{{ order.store_name || "The shop" }} approved delivering to your address. Your order goes ahead as usual.</template>
              <template v-else>
                Your address is outside {{ order.store_name || "the shop" }}'s delivery areas. They need to approve delivering it before your order goes out. If they can't, they'll cancel it and you won't be charged.
              </template>
            </p>

            <!-- Return / refund in progress -->
            <p v-if="caseIsOpen(order)" class="ot-case-note" :class="order.return_case.next_actor === 'customer' ? 'amber' : 'blue'">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9" /><path d="M3 5v7h7" /></svg>
              {{ order.return_case.next_actor === "customer" ? "Your return or refund needs your reply." : "Your return or refund is being handled." }}
              <button type="button" class="ot-link" @click="scrollToCase(order)">Open it</button>
            </p>

            <!-- ── Progress ──────────────────────────────────────────── -->
            <div v-if="order.status === 'cancelled'" class="ot-cancelled">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M9 9l6 6M15 9l-6 6" /></svg>
              This order was cancelled.
            </div>
            <div v-else class="ot-progress">
              <ol class="ot-steps" :aria-label="`Order progress: ${dlvLabel(order)}`">
                <li
                  v-for="(step, i) in trackingSteps"
                  :key="step.key"
                  class="ot-step"
                  :class="{ done: isStepDone(order, i), now: isStepActive(order, i) }"
                  :aria-current="isStepActive(order, i) ? 'step' : undefined"
                >
                  <span class="ot-step__dot" aria-hidden="true">
                    <svg v-if="isStepDone(order, i)" viewBox="0 0 16 16"><path d="M3.5 8.5l3 3 6-7" /></svg>
                  </span>
                  <span class="ot-step__label">{{ step.label }}</span>
                </li>
              </ol>
              <p v-if="order.delivery?.last_scanned_at" class="ot-progress__when">
                Updated {{ fmtDT(order.delivery.last_scanned_at) }}
                <code v-if="order.delivery?.barcode">{{ order.delivery.barcode }}</code>
              </p>
            </div>

            <!-- Out for delivery: the customer completes the order -->
            <div v-if="canComplete(order)" class="ot-receive">
              <button v-if="order.delivery?.photo_url" type="button" class="ot-receive__photo" aria-label="View proof of order" @click="openProof(order)">
                <img :src="order.delivery.photo_url" alt="" loading="lazy" />
              </button>
              <div class="ot-receive__text">
                <strong>Your order is on its way</strong>
                <span>Once it's in your hands, mark it received. That completes the order.</span>
                <small v-if="order.delivery?.auto_complete_at">
                  If you don't, it completes by itself on {{ fmtDay(order.delivery.auto_complete_at) }}. Something wrong? Tell the shop or ask for a return or refund before then.
                </small>
              </div>
              <button type="button" class="ot-btn ot-btn--primary" :disabled="actionLoading === order.id + '_c'" @click="doComplete(order)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                {{ actionLoading === order.id + "_c" ? "Confirming…" : "Mark as received" }}
              </button>
            </div>

            <!-- ── Actions ───────────────────────────────────────────── -->
            <footer class="ot-card__foot">
              <div class="ot-actions">
                <button v-if="order.vendor_id" type="button" class="ot-btn ot-btn--ghost" @click="askShop(order)">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12a8 8 0 0 1-11.6 7.1L4 20l1-4.3A8 8 0 1 1 20 12Z" /></svg>
                  Ask the shop
                </button>
                <button v-if="canReview(order) && !caseIsOpen(order)" type="button" class="ot-btn ot-btn--ghost" @click="openReviewModal(order)">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.9L12 16.9l-5.2 2.8 1-5.9-4.3-4.1 5.9-.9z" /></svg>
                  {{ hasReviewed(order) ? "See your review" : "Rate your flowers" }}
                </button>
                <button v-if="order.can_open_return_case" type="button" class="ot-btn ot-btn--ghost" @click="openRequestModal(order)">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9" /><path d="M3 5v7h7" /></svg>
                  Return or refund
                </button>
                <button v-else-if="order.return_case && !caseIsOpen(order)" type="button" class="ot-btn ot-btn--ghost" @click="scrollToCase(order)">
                  View return or refund
                </button>
                <button
                  v-if="canCancel(order)"
                  type="button"
                  class="ot-btn ot-btn--ghost danger"
                  :disabled="actionLoading === order.id + '_cancel'"
                  @click="doCancel(order)"
                >
                  {{ actionLoading === order.id + "_cancel" ? "Cancelling…" : "Cancel order" }}
                </button>
              </div>
              <button
                type="button"
                class="ot-toggle"
                :aria-expanded="isOpen(order.id)"
                :aria-controls="`order-detail-${order.id}`"
                @click="toggle(order.id)"
              >
                {{ isOpen(order.id) ? "Hide details" : "Details" }}
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
              </button>
            </footer>

            <!-- ── Details ───────────────────────────────────────────── -->
            <!-- Opening fades in; closing is instant (the leave hook finishes at once),
                 so the page is already its new height when toggle() measures and scrolls -->
            <Transition name="expand" @leave="(el, done) => done()">
              <div v-if="isOpen(order.id)" :id="`order-detail-${order.id}`" class="ot-detail">
                <div class="ot-detail__grid">
                  <!-- Items and what it cost -->
                  <section class="ot-panel" aria-label="Items">
                    <h3 class="ot-panel__title">Items <span>{{ order.items_count }}</span></h3>
                    <ul class="ot-items">
                      <li v-for="item in order.items" :key="item.id" class="ot-item">
                        <img v-if="item.product_image" :src="item.product_image" :alt="item.product_name" loading="lazy" />
                        <span v-else class="ot-item__ph" aria-hidden="true">{{ item.product_name?.charAt(0) || "B" }}</span>
                        <span class="ot-item__body">
                          <strong>{{ item.product_name }}</strong>
                          <small>{{ [item.color, item.size, `Qty ${item.quantity}`].filter(Boolean).join(" · ") }}</small>
                        </span>
                        <span class="ot-item__price">₱{{ fmt(item.subtotal) }}</span>
                      </li>
                    </ul>
                    <dl class="ot-totals">
                      <div><dt>Subtotal</dt><dd>₱{{ fmt(order.subtotal) }}</dd></div>
                      <div><dt>Delivery</dt><dd>₱{{ fmt(order.delivery_fee) }}</dd></div>
                      <div class="sum"><dt>Total</dt><dd>₱{{ fmt(order.total_amount) }}</dd></div>
                    </dl>
                  </section>

                  <!-- Where it's going and what happened so far -->
                  <section class="ot-panel" aria-label="Delivery">
                    <h3 class="ot-panel__title">Delivery</h3>
                    <dl class="ot-info">
                      <div>
                        <dt><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z" /><circle cx="12" cy="9.5" r="2.5" /></svg>Address</dt>
                        <dd>{{ order.delivery_address || "Not given" }}</dd>
                      </div>
                      <div>
                        <dt><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 20a8 8 0 0 1 16 0" /></svg>Receiver</dt>
                        <dd>{{ order.delivery_contact_name || "Not given" }}<template v-if="order.delivery_contact_number"> · {{ order.delivery_contact_number }}</template></dd>
                      </div>
                      <div v-if="order.reservation_date">
                        <dt><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M8 3v4M16 3v4M4 10h16" /></svg>Delivery date</dt>
                        <dd>{{ order.reservation_date }}</dd>
                      </div>
                      <div v-if="order.customer_notes">
                        <dt><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5z" /><path d="M8 9h8M8 13h8M8 17h5" /></svg>Your note</dt>
                        <dd class="note">{{ order.customer_notes }}</dd>
                      </div>
                    </dl>

                    <button
                      v-if="order.delivery?.photo_url && !order.delivery?.timeline?.length"
                      type="button"
                      class="ot-proof ot-proof--wide"
                      @click="openProof(order)"
                    >
                      <img :src="order.delivery.photo_url" alt="" loading="lazy" />
                      <span><strong>View proof of order</strong><small>{{ order.store_name || "The shop" }}'s photo before sending it out</small></span>
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h6v6M10 14 21 3M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5" /></svg>
                    </button>

                    <template v-if="order.delivery?.timeline?.length">
                      <h4 class="ot-panel__sub">Updates</h4>
                      <ol class="ot-timeline">
                        <li v-for="(ev, idx) in order.delivery.timeline" :key="idx">
                          <strong>{{ ev.label }}</strong>
                          <time>{{ fmtDT(ev.scanned_at) }}</time>
                          <p v-if="ev.notes">{{ ev.notes }}</p>
                          <!-- The shop's photo, on the update when it went out -->
                          <button v-if="idx === proofIndex(order)" type="button" class="ot-proof" @click="openProof(order)">
                            <img :src="order.delivery.photo_url" alt="" loading="lazy" />
                            <span><strong>View proof of order</strong><small>Photo by {{ order.store_name || "the shop" }}</small></span>
                          </button>
                          <p v-else-if="!order.delivery?.photo_url && ev.status === 'to_received'" class="ot-noproof">No photo from the shop for this order.</p>
                        </li>
                      </ol>
                    </template>
                  </section>
                </div>

                <!-- Return / refund case, settled with the shop -->
                <section v-if="order.return_case" :id="`case-${order.id}`" class="ot-panel">
                  <h3 class="ot-panel__title">Return or refund</h3>
                  <ReturnCasePanel
                    :case-id="order.return_case.id"
                    role="customer"
                    api-base="/customer/return-cases"
                    @changed="(detail) => onCaseChanged(order, detail)"
                  />
                </section>

                <!-- Questions to the shop about this order -->
                <section class="ot-panel">
                  <h3 class="ot-panel__title">Questions for the shop</h3>
                  <ProductQuestions :order-id="order.id" />
                </section>
              </div>
            </Transition>
          </article>
        </div>

        <!-- One order from a notification: the way back to all of them -->
        <div v-if="focusOrder" class="ot-focus-all">
          <button type="button" class="ot-btn ot-btn--primary" @click="showAllOrders">
            View all orders
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </button>
          <span>Or pick a tab above, like Processing or Completed.</span>
        </div>
        <!-- 10 orders, then 10 more, then 20 at a time -->
        <ShowMore v-else tone="customer" :has-more="pager.hasMore.value" :loading="pager.loadingMore.value" :shown="orders.length" :total="pager.total.value" @more="pager.showMore()" />
      </template>
    </main>

    <!-- ── Return or refund request ──────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="reqModal.show" class="ot-overlay" @click.self="closeReqModal">
        <div class="ot-modal" role="dialog" aria-modal="true" aria-labelledby="ot-req-title" @keydown.esc="closeReqModal">
          <header class="ot-modal__head">
            <span class="ot-modal__badge" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9" /><path d="M3 5v7h7" /></svg>
            </span>
            <div>
              <h3 id="ot-req-title" class="ot-modal__title">Return or refund</h3>
              <p class="ot-modal__sub">{{ reqModal.orderNumber }}</p>
            </div>
            <button type="button" class="ot-modal__close" aria-label="Close" @click="closeReqModal">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
            </button>
          </header>

          <div class="ot-modal__body">
            <fieldset class="ot-field">
              <legend class="ot-field__lbl">What happens to the item?</legend>
              <div class="ot-choice">
                <label class="ot-choice__opt" :class="{ on: reqModal.type === 'refund' }">
                  <input v-model="reqModal.type" type="radio" value="refund" />
                  <span><strong>Keep it</strong><small>It can't be sent back (e.g. wilted)</small></span>
                </label>
                <label class="ot-choice__opt" :class="{ on: reqModal.type === 'return' }">
                  <input v-model="reqModal.type" type="radio" value="return" />
                  <span><strong>Send it back</strong><small>You return the item to the shop</small></span>
                </label>
              </div>
            </fieldset>

            <fieldset class="ot-field">
              <legend class="ot-field__lbl">What would you like?</legend>
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
              <div v-if="reqModal.resolution === 'partial_refund'" class="ot-field">
                <label class="ot-field__lbl" for="ot-amount">Amount (order total ₱{{ fmt(reqModal.total) }})</label>
                <input id="ot-amount" v-model.number="reqModal.amount" type="number" min="1" step="0.01" :max="reqModal.total" class="ot-input" />
                <span v-if="reqModal.errors.amount" class="ot-field__err">{{ reqModal.errors.amount }}</span>
              </div>
              <span class="ot-field__hint">The shop can accept or suggest something else. You can message each other until you agree.</span>
            </fieldset>

            <div class="ot-field">
              <label class="ot-field__lbl" for="ot-reason-cat">What went wrong?</label>
              <select id="ot-reason-cat" v-model="reqModal.reasonCategory" class="ot-input">
                <option v-for="(label, key) in REASONS" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>

            <div class="ot-field">
              <label class="ot-field__lbl" for="ot-reason">Tell the shop what happened <span class="ot-field__req">*</span></label>
              <textarea
                id="ot-reason"
                v-model="reqModal.reason"
                class="ot-input"
                :class="{ bad: reqModal.errors.reason }"
                rows="4"
                placeholder="What went wrong? At least 10 characters."
              ></textarea>
              <span v-if="reqModal.errors.reason" class="ot-field__err">{{ reqModal.errors.reason }}</span>
              <span class="ot-field__hint">{{ reqModal.reason.length }} / 1000 characters</span>
            </div>

            <div class="ot-field">
              <span class="ot-field__lbl">Photo or video <span class="ot-field__opt">(optional)</span></span>
              <div
                class="ot-drop"
                :class="{ drag: isDragging, bad: reqModal.errors.media, filled: reqModal.mediaFile }"
                role="button"
                tabindex="0"
                aria-label="Add a photo or video"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="onDrop"
                @click="$refs.fileInput.click()"
                @keydown.enter.prevent="$refs.fileInput.click()"
              >
                <input ref="fileInput" type="file" accept="image/jpeg,image/png,video/mp4,video/quicktime" class="ot-drop__input" @change="onFileChange" />
                <div v-if="!reqModal.mediaFile" class="ot-drop__empty">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5" /><circle cx="9" cy="10" r="2" /><path d="m3 17 5-4 4 3 4-5 5 5" /></svg>
                  <p><strong>Click or drag</strong> a file here<br /><small>JPG, PNG, MP4 or MOV · up to 20 MB</small></p>
                </div>
                <div v-else class="ot-drop__file">
                  <video v-if="reqModal.mediaPreviewType === 'video'" :src="reqModal.mediaPreviewUrl" controls @click.stop></video>
                  <img v-else :src="reqModal.mediaPreviewUrl" alt="Preview" />
                  <span class="ot-drop__name">
                    <strong>{{ reqModal.mediaFile.name }}</strong>
                    <small>{{ fmtBytes(reqModal.mediaFile.size) }}</small>
                  </span>
                  <button type="button" class="ot-modal__close" aria-label="Remove file" @click.stop="removeMedia">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                  </button>
                </div>
              </div>
              <span v-if="reqModal.errors.media" class="ot-field__err">{{ reqModal.errors.media }}</span>
            </div>
          </div>

          <footer class="ot-modal__foot">
            <button type="button" class="ot-btn ot-btn--quiet" :disabled="reqModal.submitting" @click="closeReqModal">Cancel</button>
            <button type="button" class="ot-btn ot-btn--primary" :disabled="reqModal.submitting" @click="submitRequest">
              {{ reqModal.submitting ? "Sending…" : "Send request" }}
            </button>
          </footer>
        </div>
      </div>
    </Transition>

    <!-- ── Confirm: received / cancel ────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="confirmModal.show" class="ot-overlay" @click.self="confirmModal.show = false">
        <div class="ot-modal ot-modal--sm" role="alertdialog" aria-modal="true" aria-labelledby="ot-confirm-title" @keydown.esc="confirmModal.show = false">
          <span class="ot-modal__badge big" :class="{ danger: confirmModal.variant === 'danger' }" aria-hidden="true">
            <svg v-if="confirmModal.variant === 'danger'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M9 9l6 6M15 9l-6 6" /></svg>
            <svg v-else viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
          </span>
          <h3 id="ot-confirm-title" class="ot-modal__title">{{ confirmModal.title }}</h3>
          <p class="ot-modal__text">{{ confirmModal.body }}</p>
          <footer class="ot-modal__foot">
            <button type="button" class="ot-btn ot-btn--quiet" @click="confirmModal.show = false">Not now</button>
            <button
              type="button"
              class="ot-btn"
              :class="confirmModal.variant === 'danger' ? 'ot-btn--danger' : 'ot-btn--primary'"
              :disabled="confirmModal.loading"
              @click="confirmModal.onConfirm"
            >
              {{ confirmModal.loading ? confirmModal.loadingLabel : confirmModal.confirmLabel }}
            </button>
          </footer>
        </div>
      </div>
    </Transition>

    <!-- Why an online payment didn't go through -->
    <PaymentFailedModal
      :show="paymentFailure.show"
      :failure="paymentFailure.order?.payment_failure"
      :order-number="paymentFailure.order?.order_number || ''"
      :secondary-label="paymentFailure.order?.vendor_id ? 'Ask the shop' : ''"
      @close="paymentFailure.show = false"
      @secondary="askShop(paymentFailure.order)"
    />

    <!-- Proof of order: the shop's photo, without leaving the page -->
    <Transition name="modal">
      <div v-if="proof.show" class="ot-overlay ot-overlay--dark" @click.self="closeProof">
        <figure class="ot-proof-modal" role="dialog" aria-modal="true" aria-labelledby="ot-proof-title" @keydown.esc="closeProof">
          <header class="ot-proof-modal__head">
            <div>
              <h3 id="ot-proof-title">Proof of order</h3>
              <p>{{ proof.orderNumber }}</p>
            </div>
            <button ref="proofClose" type="button" class="ot-modal__close" aria-label="Close" @click="closeProof">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
            </button>
          </header>
          <div class="ot-proof-modal__img">
            <img :src="proof.url" :alt="`Photo of order ${proof.orderNumber} before delivery`" />
          </div>
          <figcaption>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z" /><circle cx="12" cy="13" r="3.5" /></svg>
            {{ proof.store }} took this photo before sending your order out<template v-if="proof.takenAt"> · {{ fmtDT(proof.takenAt) }}</template>.
          </figcaption>
        </figure>
      </div>
    </Transition>

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
import { watch, ref, reactive, onMounted, onUnmounted, nextTick } from "vue";
import ShowMore from "../../components/common/ShowMore.vue";
import { useShowMorePaged } from "../../composables/useShowMore";
import { useRoute, useRouter } from "vue-router";
import api from "../../plugins/axios";
import { useAuth } from "../../composables/useAuth";
import NavHeader from "../../layouts/NavHeader.vue";
import ReviewModal from "../../layouts/components/ReviewModal.vue";
import PaymentFailedModal from "../../components/customer/PaymentFailedModal.vue";
import ReturnCasePanel from "../../components/returns/ReturnCasePanel.vue";
import ProductQuestions from "../../layouts/components/ProductQuestions.vue";
import "../../styles/bloomcraft.css";
import { toast } from "@/utils/snackbar";

const API_BASE = "customer/orders";
const route = useRoute();
const router = useRouter();

// ── State ─────────────────────────────────────────────────────────────────────
const { isAuthenticated } = useAuth();
const orders = ref([]);
const loading = ref(false);
const error = ref(null);
// One order open at a time. Opening one closes the other, and the page then
// scrolls by however much that moved things, so the order that was clicked
// stays exactly where it was on screen (see toggle).
const openId = ref(null);
const isOpen = (id) => openId.value === id;
const openOrder = (id) => (openId.value = id);
const closeAll = () => (openId.value = null);
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
  title: "Got your order?",
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
/** One page of orders from the server, for the "Show more" list */
async function fetchOrdersPage(page) {
  const params = { page, per_page: meta.per_page };
  if (focusOrder.value) {
    // Opened from a notification: just that order (search matches part of a
    // number, so keep only the exact one)
    params.search = focusOrder.value;
    const res = await api.get(API_BASE, { params, headers: authHeaders() });
    const want = focusOrder.value.toUpperCase();
    const items = (res.data.data ?? []).filter((o) => String(o.order_number || "").replace(/^#/, "").toUpperCase() === want);
    return { items, lastPage: 1, total: items.length };
  }
  if (activeTab.value !== "all") params.delivery_status = activeTab.value;
  if (search.value.trim()) params.search = search.value.trim();

  const res = await api.get(API_BASE, { params, headers: authHeaders() });
  Object.assign(meta, res.data.meta ?? {});
  return { items: res.data.data ?? [], lastPage: meta.last_page, total: meta.total };
}
// Orders show 10 at first, then 10 more, then 20 at a time
const pager = useShowMorePaged(fetchOrdersPage);
watch(() => pager.visible.value, (rows) => (orders.value = rows), { immediate: true });

/** Starts the list over (opening the page, a new tab or search) */
async function fetchOrders() {
  loading.value = true;
  error.value = null;
  await pager.load();
  if (pager.error.value) error.value = pager.error.value || "Could not load orders. Please try again.";
  loading.value = false;
  // The order from a notification opens up, showing its details
  if (focusOrder.value && orders.value.length === 1) openOrder(orders.value[0].id);
}

// ── One order, from a notification ──────────────────────────────────────────
// The notification passes the order number in history state (focusOrder);
// a tab, a search or "View all orders" goes back to the full list
const readFocus = () => {
  try {
    const value = window.history.state?.focusOrder;
    return typeof value === "string" ? value.replace(/^#/, "").trim() : "";
  } catch {
    return "";
  }
};
const focusOrder = ref(readFocus());

function clearFocus() {
  if (!focusOrder.value) return;
  focusOrder.value = "";
  // Forget it in this history entry too, so a refresh shows every order
  try {
    window.history.replaceState({ ...window.history.state, focusOrder: null }, "");
  } catch {
    // Nothing to forget
  }
}

function showAllOrders() {
  clearFocus();
  activeTab.value = "all";
  closeAll();
  fetchOrders();
}

// Another notification clicked while already on this page
const stopFocusWatch = router.afterEach((to) => {
  if (to.name !== route.name) return;
  const next = readFocus();
  if (!next || next === focusOrder.value) return;
  focusOrder.value = next;
  closeAll();
  fetchOrders();
});
onUnmounted(stopFocusWatch);
/** After an action (cancel, received…): reload, keeping as many orders showing */
const refreshOrders = () => pager.refresh();

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
  !!o.vendor_id || canCancel(o) || canComplete(o) || o.can_open_return_case || o.return_case || canReview(o);

const paymentFailure = reactive({ show: false, order: null });
const openPaymentFailure = (order) => {
  paymentFailure.order = order;
  paymentFailure.show = true;
};

// Opens the shop's chat; the question is sent with a card for this order
const askShop = (order) =>
  router.push({
    path: "/customer/chat",
    query: { vendor_id: String(order.vendor_id), vendor_name: order.store_name || "" },
    state: {
      chatAbout: JSON.stringify({
        type: "order",
        id: order.id,
        order_number: order.order_number,
        // Shown on the order card above the message box
        image: thumbs(order)[0] || "",
        status: dlvLabel(order),
        items_count: Number(order.items_count || 0),
        total: Number(order.total_amount || 0),
      }),
    },
  });

// ① canReview computed helper — true if order is completed
const canReview = (order) =>
  order.delivery?.status === "completed" || order.status === "completed";

// True if already reviewed in this session (disables the button)
const hasReviewed = (order) => reviewedOrderIds.value.has(order.id);

// ── Mark as received ──────────────────────────────────────────────────────────
// "Friday, Nov 14" for the day an order completes by itself
const fmtDay = (iso) => (iso ? new Date(iso).toLocaleDateString("en-PH", { weekday: "long", month: "short", day: "numeric" }) : "");

function doComplete(order) {
  confirmModal.show = true;
  confirmModal.loading = false;
  confirmModal.title = "Got your order?";
  confirmModal.body = `Mark order ${order.order_number} as received? This completes the order. If something is wrong with it, you can still ask for a return or refund afterwards.`;
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
      await refreshOrders();
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
  confirmModal.title = "Cancel this order?";
  confirmModal.body = `Order ${order.order_number} will be cancelled and the shop won't prepare it.`;
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
      await refreshOrders();
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
  openOrder(order.id);
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
    await refreshOrders();
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
  refreshOrders();
}

// ── Proof of order (the shop's photo) ───────────────────────────────────────
/**
 * The update the photo belongs to: the one when the order went out for
 * delivery, else the first one at or after the photo was taken, else the newest.
 */
function proofIndex(order) {
  const timeline = order.delivery?.timeline || [];
  if (!order.delivery?.photo_url || !timeline.length) return -1;
  const out = timeline.findIndex((ev) => ev.status === "to_received");
  if (out >= 0) return out;
  const taken = Date.parse(order.delivery.photo_taken_at || "");
  if (!Number.isNaN(taken)) {
    const after = timeline.findIndex((ev) => Date.parse(ev.scanned_at || "") >= taken - 60000);
    if (after >= 0) return after;
  }
  return timeline.length - 1;
}

const proof = reactive({ show: false, url: "", orderNumber: "", store: "", takenAt: "" });
const proofClose = ref(null);
let proofReturnFocus = null;
function openProof(order) {
  proofReturnFocus = document.activeElement;
  Object.assign(proof, {
    show: true,
    url: order.delivery.photo_url,
    orderNumber: order.order_number,
    store: order.store_name || "The shop",
    takenAt: order.delivery.photo_taken_at || "",
  });
  nextTick(() => proofClose.value?.focus());
}
function closeProof() {
  proof.show = false;
  proofReturnFocus?.focus?.();
}

// ── UI helpers ────────────────────────────────────────────────────────────────
// Just under the floating navbar, measured each time (its height changes with the screen)
const navClearance = () => {
  const nav = document.querySelector(".navbar, nav.bc-navbar, header .navbar");
  const bottom = nav ? nav.getBoundingClientRect().bottom : 96;
  return Math.max(16, bottom) + 12;
};
const cardTop = (id) => document.getElementById(`order-card-${id}`)?.getBoundingClientRect().top;

async function toggle(id) {
  const before = cardTop(id);
  const closing = isOpen(id);
  openId.value = closing ? null : id;
  await nextTick();
  const after = cardTop(id);
  if (before == null || after == null) return;
  const clearance = navClearance();
  if (closing && before < clearance) {
    // Closed from far down a long order: bring its top back under the navbar
    window.scrollBy({ top: after - clearance, behavior: "instant" });
  } else if (Math.abs(after - before) > 1) {
    // The order above closed: keep this one where it was
    window.scrollBy({ top: after - before, behavior: "instant" });
  }
  // Opened: glide it up so its top sits just under the navbar
  if (!closing) {
    const top = cardTop(id);
    if (top != null && Math.abs(top - clearance) > 4) {
      const still = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
      window.scrollBy({ top: top - clearance, behavior: still ? "instant" : "smooth" });
    }
  }
}
const setTab = (v) => {
  clearFocus();
  activeTab.value = v;
  closeAll();
  fetchOrders();
};
const debounceSearch = () => {
  clearFocus();
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => fetchOrders(), 400);
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
/** The colour of an order's status: amber (with the shop), blue (on the way), green (done) */
function statusTone(order) {
  if (order.status === "cancelled") return "red";
  const ds = order.delivery?.status ?? "pending";
  if (["to_ship", "to_received"].includes(ds)) return "blue";
  if (ds === "completed") return "green";
  if (["pending", "to_processed"].includes(ds)) return "amber";
  return "slate";
}
const PAY_LABELS = { paid: "Paid", unpaid: "Not paid yet", failed: "Payment failed", refunded: "Refunded", pending: "Payment pending" };
const payLabel = (s) => PAY_LABELS[s] || (s ? s.charAt(0).toUpperCase() + s.slice(1) : "");
function payTone(s) {
  if (s === "paid") return "paid";
  if (s === "unpaid" || s === "pending") return "unpaid";
  if (s === "failed") return "failed";
  return "muted";
}
// Up to three item photos for the card, and how many more items there are
const thumbs = (order) => (order.items || []).map((item) => item.product_image).filter(Boolean).slice(0, 3);
const moreItems = (order) => Math.max(0, Number(order.items_count || 0) - Math.max(thumbs(order).length, 1));

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
/* My orders. Tokens, type and the navbar come from styles/bloomcraft.css
   (the logo's navy --ink and green --leaf, Cormorant headings, Poppins text). */

@layer bc-reset {
  .ot-page :is(p, h1, h2, h3, h4, dl, dd, ol, ul, figure, fieldset) {
    margin: 0;
  }
}

.ot-page {
  /* Status colours, each a text colour on a soft fill */
  --amber: #9a5b0b;
  --amber-bg: #fdf1df;
  --blue: #2c5f9e;
  --blue-bg: #e7effa;
  --green: #2e7a4f;
  --green-bg: #e4f3ea;
  --red: #b4332a;
  --red-bg: #fbe9e7;
  --slate: #4f5866;
  --slate-bg: #edf0f2;
  --card: #ffffff;
  --radius: 18px;
  min-height: 100vh;
  padding-bottom: 96px;
  background: var(--ivory);
  color: var(--ink);
  font-family: var(--font-sans);
}

.ot-wrap {
  width: min(1040px, 100% - 32px);
  margin-inline: auto;
}

/* ── Header ───────────────────────────────────────────────── */
.ot-head {
  padding-top: clamp(112px, 14vw, 136px);
  border-bottom: 1px solid var(--line);
  background: linear-gradient(180deg, var(--paper) 0%, var(--ivory) 100%);
}

.ot-head__top {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px 32px;
}

.ot-title {
  margin-top: 10px !important;
  font-family: var(--font-display);
  font-size: clamp(40px, 5vw, 60px);
  font-weight: 500;
  line-height: 1;
  letter-spacing: -0.01em;
}

.ot-title em {
  color: var(--leaf-deep);
  font-style: italic;
}

.ot-lede {
  margin-top: 12px !important;
  color: var(--ink-soft);
  font-size: 15px;
}

.ot-search {
  display: flex;
  align-items: center;
  gap: 10px;
  width: min(340px, 100%);
  height: 48px;
  padding: 0 16px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--card);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.ot-search:focus-within {
  border-color: var(--leaf);
  box-shadow: 0 0 0 4px rgba(77, 177, 115, 0.14);
}

.ot-search svg {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  fill: none;
  stroke: var(--ink-soft);
  stroke-width: 1.8;
  stroke-linecap: round;
}

.ot-search input {
  flex: 1;
  min-width: 0;
  border: 0;
  background: none;
  color: var(--ink);
  font: 400 14.5px var(--font-sans);
  outline: none;
}

.ot-search input::placeholder {
  color: #8b929c;
}

.ot-tabs {
  display: flex;
  gap: 6px;
  margin-top: 28px;
  padding-bottom: 14px;
  overflow-x: auto;
  scrollbar-width: none;
}

.ot-tabs::-webkit-scrollbar {
  display: none;
}

.ot-tab {
  flex-shrink: 0;
  min-height: 40px;
  padding: 0 16px;
  border: 1px solid transparent;
  border-radius: 999px;
  background: none;
  color: var(--ink-soft);
  font: 500 13.5px var(--font-sans);
  white-space: nowrap;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.ot-tab:hover {
  border-color: var(--line);
  color: var(--ink);
}

.ot-tab.on {
  background: var(--ink);
  color: var(--paper);
}

/* ── Body ─────────────────────────────────────────────────── */
.ot-body {
  padding-top: 28px;
}

.ot-list {
  display: grid;
  gap: 16px;
  overflow-anchor: none;
}

/* Buttons */
.ot-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  padding: 0 18px;
  border: 1px solid transparent;
  border-radius: 999px;
  font: 500 13.5px var(--font-sans);
  text-decoration: none;
  white-space: nowrap;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.ot-btn svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ot-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.ot-btn--primary {
  background: var(--ink);
  color: var(--paper);
}

.ot-btn--primary:hover:not(:disabled) {
  background: var(--leaf-deep);
}

.ot-btn--danger {
  background: var(--red);
  color: #ffffff;
}

.ot-btn--danger:hover:not(:disabled) {
  background: #952a22;
}

.ot-btn--ghost {
  border-color: var(--line);
  background: var(--card);
  color: var(--ink);
}

.ot-btn--ghost:hover:not(:disabled) {
  border-color: var(--leaf);
  color: var(--leaf-deep);
}

.ot-btn--ghost.danger {
  color: var(--red);
}

.ot-btn--ghost.danger:hover:not(:disabled) {
  border-color: var(--red);
  color: var(--red);
  background: var(--red-bg);
}

.ot-btn--quiet {
  background: none;
  color: var(--ink-soft);
}

.ot-btn--quiet:hover:not(:disabled) {
  background: var(--ivory-deep);
  color: var(--ink);
}

.ot-link {
  margin-left: auto;
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: 600 13px var(--font-sans);
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}

:is(.ot-btn, .ot-tab, .ot-toggle, .ot-link, .ot-pay, .ot-drop, .ot-modal__close):focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

/* Offer question */
.ot-offer {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px 20px;
  margin-bottom: 20px;
  padding: 18px 20px;
  border: 1px solid rgba(77, 177, 115, 0.3);
  border-radius: var(--radius);
  background: linear-gradient(120deg, #f1f9f4 0%, var(--card) 70%);
}

.ot-offer__ico {
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border-radius: 14px;
  background: var(--green-bg);
  color: var(--leaf-deep);
}

.ot-offer__ico svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ot-offer__text h2 {
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 500;
}

.ot-offer__text p {
  margin-top: 4px !important;
  color: var(--ink-soft);
  font-size: 13.5px;
  line-height: 1.55;
}

.ot-offer__actions {
  display: flex;
  gap: 8px;
}

/* From a notification */
.ot-focus {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
  padding: 12px 16px;
  border-radius: 14px;
  background: var(--green-bg);
  color: var(--ink);
  font-size: 13.5px;
}

.ot-focus svg,
.ot-focus-all svg {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ot-focus svg {
  color: var(--leaf-deep);
}

.ot-focus-all {
  display: grid;
  justify-items: center;
  gap: 10px;
  margin-top: 28px;
  color: var(--ink-soft);
  font-size: 13px;
  text-align: center;
}

/* Empty, error */
.ot-empty {
  display: grid;
  justify-items: center;
  gap: 10px;
  max-width: 420px;
  margin: 48px auto 0;
  padding: 40px 24px;
  border: 1px dashed var(--line);
  border-radius: var(--radius);
  background: var(--card);
  text-align: center;
}

.ot-empty__ico {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  margin-bottom: 6px;
  border-radius: 50%;
  background: var(--green-bg);
  color: var(--leaf-deep);
}

.ot-empty__ico.red {
  background: var(--red-bg);
  color: var(--red);
}

.ot-empty__ico svg {
  width: 26px;
  height: 26px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ot-empty h2 {
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 500;
}

.ot-empty p {
  margin-bottom: 8px !important;
  color: var(--ink-soft);
  font-size: 14px;
  line-height: 1.6;
}

/* ── Order card ───────────────────────────────────────────── */
.ot-card {
  --tone: var(--slate);
  --tone-bg: var(--slate-bg);
  position: relative;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: var(--radius);
  background: var(--card);
  box-shadow: 0 1px 2px rgba(54, 63, 78, 0.04);
  transition: box-shadow 0.25s ease, border-color 0.25s ease;
}

.ot-card:hover,
.ot-card.open {
  border-color: rgba(54, 63, 78, 0.18);
  box-shadow: 0 18px 40px -24px rgba(54, 63, 78, 0.35);
}

.tone-amber { --tone: var(--amber); --tone-bg: var(--amber-bg); }
.tone-blue { --tone: var(--blue); --tone-bg: var(--blue-bg); }
.tone-green { --tone: var(--green); --tone-bg: var(--green-bg); }
.tone-red { --tone: var(--red); --tone-bg: var(--red-bg); }
.tone-slate { --tone: var(--slate); --tone-bg: var(--slate-bg); }

.ot-card__head {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px;
  padding: 20px 22px 0;
}

/* Item photos, overlapping */
.ot-thumbs {
  position: relative;
  display: flex;
  align-items: center;
}

.ot-thumbs img,
.ot-thumbs__ph {
  width: 56px;
  height: 56px;
  border: 3px solid var(--card);
  border-radius: 14px;
  object-fit: cover;
  background: var(--ivory-deep);
}

.ot-thumbs img + img {
  margin-left: -22px;
}

.ot-thumbs__ph {
  display: grid;
  place-items: center;
  color: var(--leaf-deep);
  font-family: var(--font-display);
  font-size: 24px;
}

.ot-thumbs__more {
  position: absolute;
  right: -6px;
  bottom: -4px;
  padding: 2px 7px;
  border: 2px solid var(--card);
  border-radius: 999px;
  background: var(--ink);
  color: var(--paper);
  font-size: 10.5px;
  font-weight: 600;
}

.ot-card__id {
  min-width: 0;
}

.ot-card__num {
  color: var(--ink);
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.01em;
  font-variant-numeric: tabular-nums;
}

.ot-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 2px 8px;
  margin-top: 4px !important;
  color: var(--ink-soft);
  font-size: 13px;
}

.ot-card__store {
  color: var(--ink);
  font-weight: 500;
}

.ot-card__end {
  display: grid;
  justify-items: end;
  gap: 6px;
}

.ot-status {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 5px 12px 5px 10px;
  border-radius: 999px;
  background: var(--tone-bg);
  color: var(--tone);
  font-size: 12.5px;
  font-weight: 600;
  white-space: nowrap;
}

.ot-status i {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
}

.ot-card__total {
  font-family: var(--font-display);
  font-size: 24px;
  font-weight: 600;
  line-height: 1;
  font-variant-numeric: lining-nums tabular-nums;
}

.ot-pay {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0;
  border: 0;
  background: none;
  font: 500 12px var(--font-sans);
}

.ot-pay svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.5;
  stroke-linecap: round;
}

button.ot-pay {
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 3px;
}

.ot-pay.paid { color: var(--green); }
.ot-pay.unpaid { color: var(--amber); }
.ot-pay.failed { color: var(--red); }
.ot-pay.muted { color: var(--ink-soft); }

.ot-case-note {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 16px 22px 0 !important;
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 500;
}

.ot-case-note.amber { background: var(--amber-bg); color: var(--amber); }
.ot-case-note.blue { background: var(--blue-bg); color: var(--blue); }

.ot-case-note svg,
.ot-cancelled svg {
  flex-shrink: 0;
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

/* ── Progress ─────────────────────────────────────────────── */
.ot-progress {
  margin: 20px 22px 0;
  padding: 18px 18px 14px;
  border-radius: 14px;
  background: var(--ivory);
}

.ot-steps {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  padding: 0;
  list-style: none;
}

.ot-step {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 8px;
  text-align: center;
}

/* The line to the next step */
.ot-step:not(:last-child)::after {
  content: "";
  position: absolute;
  top: 11px;
  left: calc(50% + 14px);
  right: calc(-50% + 14px);
  height: 2px;
  border-radius: 2px;
  background: #d9dfdb;
}

.ot-step.done:not(:last-child)::after {
  background: var(--leaf);
}

.ot-step__dot {
  position: relative;
  z-index: 1;
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border: 2px solid #d9dfdb;
  border-radius: 50%;
  background: var(--card);
}

.ot-step.done .ot-step__dot {
  border-color: var(--leaf);
  background: var(--leaf);
}

.ot-step__dot svg {
  width: 13px;
  height: 13px;
  fill: none;
  stroke: #ffffff;
  stroke-width: 2.4;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ot-step.now .ot-step__dot {
  border-color: var(--leaf);
  box-shadow: 0 0 0 5px rgba(77, 177, 115, 0.18);
}

.ot-step.now .ot-step__dot::after {
  content: "";
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--leaf);
}

.ot-step__label {
  color: #8b929c;
  font-size: 12px;
  font-weight: 500;
  line-height: 1.3;
}

.ot-step.done .ot-step__label {
  color: var(--ink-soft);
}

.ot-step.now .ot-step__label {
  color: var(--ink);
  font-weight: 600;
}

.ot-progress__when {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 14px !important;
  color: var(--ink-soft);
  font-size: 12px;
}

.ot-progress__when code {
  padding: 2px 8px;
  border-radius: 6px;
  background: var(--card);
  color: var(--ink);
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  font-size: 11.5px;
}

.ot-cancelled {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 20px 22px 0;
  padding: 14px 16px;
  border-radius: 14px;
  background: var(--red-bg);
  color: var(--red);
  font-size: 13.5px;
  font-weight: 500;
}

/* Out for delivery */
.ot-receive {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px;
  margin: 14px 22px 0;
  padding: 14px 16px;
  border: 1px solid rgba(77, 177, 115, 0.35);
  border-radius: 14px;
  background: #f1f9f4;
}

.ot-receive img {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  object-fit: cover;
}

.ot-receive__text {
  display: grid;
  gap: 3px;
  font-size: 13px;
  line-height: 1.5;
}

.ot-receive__text strong {
  font-size: 14.5px;
}

.ot-receive__text span {
  color: var(--ink-soft);
}

.ot-receive__text small {
  color: var(--ink-soft);
  font-size: 12px;
}

/* ── Card footer ──────────────────────────────────────────── */
.ot-card__foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 18px;
  padding: 14px 22px;
  border-top: 1px solid var(--line-soft, var(--line));
}

.ot-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.ot-actions .ot-btn {
  min-height: 38px;
  padding: 0 14px;
  font-size: 13px;
}

.ot-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 40px;
  margin-left: auto;
  padding: 0 6px 0 12px;
  border: 0;
  border-radius: 999px;
  background: none;
  color: var(--ink);
  font: 600 13px var(--font-sans);
  cursor: pointer;
}

.ot-toggle:hover {
  background: var(--ivory);
}

.ot-toggle svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform 0.25s ease;
}

.ot-card.open .ot-toggle svg {
  transform: rotate(180deg);
}

/* ── Details ──────────────────────────────────────────────── */
.ot-detail {
  display: grid;
  gap: 16px;
  padding: 4px 22px 22px;
}

.ot-detail__grid {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
  gap: 16px;
}

.ot-panel {
  padding: 18px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--card);
}

.ot-panel__title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px !important;
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 500;
}

.ot-panel__title span {
  padding: 1px 8px;
  border-radius: 999px;
  background: var(--ivory-deep);
  color: var(--ink-soft);
  font-family: var(--font-sans);
  font-size: 12px;
  font-weight: 600;
}

.ot-panel__sub {
  margin: 18px 0 10px !important;
  color: var(--ink-soft);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.ot-items {
  display: grid;
  gap: 12px;
  padding: 0;
  list-style: none;
}

.ot-item {
  display: grid;
  grid-template-columns: 48px minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
}

.ot-item img,
.ot-item__ph {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  object-fit: cover;
  background: var(--ivory-deep);
}

.ot-item__ph {
  display: grid;
  place-items: center;
  color: var(--leaf-deep);
  font-family: var(--font-display);
  font-size: 20px;
}

.ot-item__body {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.ot-item__body strong {
  overflow: hidden;
  font-size: 14px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ot-item__body small {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.ot-item__price {
  font-size: 14px;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.ot-totals {
  display: grid;
  gap: 8px;
  margin-top: 16px !important;
  padding-top: 14px;
  border-top: 1px dashed var(--line);
}

.ot-totals div {
  display: flex;
  justify-content: space-between;
  color: var(--ink-soft);
  font-size: 13.5px;
}

.ot-totals dd {
  font-variant-numeric: tabular-nums;
}

.ot-totals .sum {
  color: var(--ink);
  font-size: 15px;
  font-weight: 600;
}

.ot-info {
  display: grid;
  gap: 14px;
}

.ot-info dt {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--ink-soft);
  font-size: 12px;
  font-weight: 500;
}

.ot-info dt svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ot-info dd {
  margin-top: 3px;
  font-size: 14px;
  line-height: 1.5;
}

.ot-info dd.note {
  font-style: italic;
}

/* "View proof of order" on a delivery update */
.ot-proof {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  justify-self: start;
  margin-top: 8px;
  padding: 6px 14px 6px 6px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--card);
  color: var(--ink);
  font-family: var(--font-sans);
  text-align: left;
  cursor: pointer;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.ot-proof:hover {
  border-color: var(--leaf);
  box-shadow: 0 8px 18px -12px rgba(54, 63, 78, 0.4);
}

.ot-proof:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

.ot-proof img {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  object-fit: cover;
}

.ot-proof span {
  display: grid;
  gap: 1px;
}

.ot-proof strong {
  color: var(--leaf-deep);
  font-size: 13px;
  font-weight: 600;
}

.ot-proof small {
  color: var(--ink-soft);
  font-size: 11.5px;
}

.ot-proof--wide {
  display: flex;
  width: 100%;
  margin-top: 16px;
}

.ot-proof--wide img {
  width: 56px;
  height: 56px;
}

.ot-proof--wide svg {
  width: 18px;
  height: 18px;
  margin-left: auto;
  fill: none;
  stroke: var(--ink-soft);
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ot-noproof {
  color: var(--ink-soft);
  font-size: 12px;
  font-style: italic;
}

.ot-receive__photo {
  padding: 0;
  border: 0;
  border-radius: 12px;
  background: none;
  cursor: zoom-in;
}

.ot-receive__photo:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

/* The photo pop-up */
.ot-overlay--dark {
  background: rgba(24, 28, 36, 0.72);
}

.ot-proof-modal {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  width: min(720px, 100%);
  max-height: min(92vh, 900px);
  overflow: hidden;
  border-radius: 22px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(0, 0, 0, 0.35);
}

.ot-proof-modal__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px 14px 22px;
}

.ot-proof-modal__head h3 {
  font-family: var(--font-display);
  font-size: 24px;
  font-weight: 500;
  line-height: 1.1;
}

.ot-proof-modal__head p {
  margin-top: 2px !important;
  color: var(--ink-soft);
  font-size: 13px;
}

.ot-proof-modal__img {
  display: grid;
  place-items: center;
  min-height: 0;
  padding: 0 18px;
}

.ot-proof-modal__img img {
  display: block;
  max-width: 100%;
  max-height: min(68vh, 640px);
  border-radius: 14px;
  object-fit: contain;
  background: var(--ivory-deep);
}

.ot-proof-modal figcaption {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 22px 18px;
  color: var(--ink-soft);
  font-size: 13px;
  line-height: 1.5;
}

.ot-proof-modal figcaption svg {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  fill: none;
  stroke: var(--leaf-deep);
  stroke-width: 1.7;
  stroke-linejoin: round;
}

.ot-timeline {
  position: relative;
  display: grid;
  gap: 14px;
  padding: 0 0 0 20px;
  list-style: none;
}

.ot-timeline::before {
  content: "";
  position: absolute;
  top: 6px;
  bottom: 6px;
  left: 5px;
  width: 2px;
  border-radius: 2px;
  background: var(--line);
}

.ot-timeline li {
  position: relative;
  display: grid;
  gap: 2px;
  font-size: 13px;
}

.ot-timeline li::before {
  content: "";
  position: absolute;
  top: 4px;
  left: -20px;
  width: 12px;
  height: 12px;
  border: 2px solid var(--card);
  border-radius: 50%;
  background: var(--leaf-light);
  box-sizing: border-box;
}

/* The newest update (the list runs oldest to newest) is where the order is now */
.ot-timeline li:last-child::before {
  background: var(--leaf);
  box-shadow: 0 0 0 3px rgba(77, 177, 115, 0.2);
}

.ot-timeline li:last-child strong {
  color: var(--leaf-deep);
}

.ot-timeline time {
  color: var(--ink-soft);
  font-size: 12px;
}

.ot-timeline p {
  color: var(--ink-soft);
  font-size: 12.5px;
}

/* ── Loading ──────────────────────────────────────────────── */
.ot-card--skel {
  padding-bottom: 20px;
}

.ot-skel-thumb {
  width: 56px;
  height: 56px;
  border-radius: 14px;
}

.ot-skel-lines {
  display: grid;
  gap: 8px;
}

.ot-skel-line {
  display: block;
  height: 12px;
  border-radius: 6px;
}

.ot-skel-line.w20 { width: 80px; height: 22px; }
.ot-skel-line.w40 { width: 40%; }
.ot-skel-line.w60 { width: 60%; }

.ot-skel-bar {
  display: block;
  height: 64px;
  margin: 20px 22px 0;
  border-radius: 14px;
}

/* ── Modals ───────────────────────────────────────────────── */
.ot-overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(38, 45, 57, 0.45);
  backdrop-filter: blur(4px);
}

.ot-modal {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  width: min(560px, 100%);
  max-height: min(88vh, 860px);
  overflow: hidden;
  border-radius: 22px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(38, 45, 57, 0.25);
}

.ot-modal--sm {
  display: grid;
  grid-template-rows: none;
  justify-items: center;
  gap: 12px;
  width: min(420px, 100%);
  padding: 28px 24px 20px;
  text-align: center;
}

.ot-modal__head {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 14px;
  padding: 20px 22px;
  border-bottom: 1px solid var(--line);
}

.ot-modal__badge {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: var(--green-bg);
  color: var(--leaf-deep);
}

.ot-modal__badge.big {
  width: 56px;
  height: 56px;
  border-radius: 50%;
}

.ot-modal__badge.danger {
  background: var(--red-bg);
  color: var(--red);
}

.ot-modal__badge svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ot-modal__title {
  font-family: var(--font-display);
  font-size: 26px;
  font-weight: 500;
  line-height: 1.1;
}

.ot-modal__sub {
  margin-top: 2px !important;
  color: var(--ink-soft);
  font-size: 13px;
}

.ot-modal__text {
  color: var(--ink-soft);
  font-size: 14px;
  line-height: 1.6;
}

.ot-modal__close {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 50%;
  background: var(--ivory-deep);
  color: var(--ink);
  cursor: pointer;
}

.ot-modal__close svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
}

.ot-modal__body {
  display: grid;
  gap: 20px;
  padding: 20px 22px;
  overflow-y: auto;
}

.ot-modal__foot {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 14px 22px;
  border-top: 1px solid var(--line);
}

.ot-modal--sm .ot-modal__foot {
  width: 100%;
  justify-content: center;
  margin-top: 8px;
  padding: 14px 0 0;
}

.ot-field {
  display: grid;
  gap: 8px;
  min-width: 0;
  padding: 0;
  border: 0;
}

.ot-field__lbl {
  padding: 0;
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 600;
}

.ot-field__req {
  color: var(--red);
}

.ot-field__opt,
.ot-field__hint {
  color: var(--ink-soft);
  font-size: 12px;
  font-weight: 400;
}

.ot-field__err {
  color: var(--red);
  font-size: 12.5px;
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
  gap: 10px;
  padding: 12px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--card);
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease;
}

.ot-choice__opt input {
  margin-top: 2px;
  accent-color: var(--leaf-deep);
}

.ot-choice__opt span {
  display: grid;
  gap: 2px;
}

.ot-choice__opt strong {
  font-size: 13.5px;
  font-weight: 600;
}

.ot-choice__opt small {
  color: var(--ink-soft);
  font-size: 12px;
  line-height: 1.4;
}

.ot-choice__opt.on {
  border-color: var(--leaf);
  background: #f1f9f4;
}

.ot-choice__opt.off {
  opacity: 0.5;
  cursor: not-allowed;
}

.ot-input {
  width: 100%;
  padding: 11px 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--card);
  color: var(--ink);
  font: 400 14px var(--font-sans);
  resize: vertical;
}

.ot-input:focus {
  border-color: var(--leaf);
  outline: none;
  box-shadow: 0 0 0 4px rgba(77, 177, 115, 0.14);
}

.ot-input.bad {
  border-color: var(--red);
}

.ot-drop {
  position: relative;
  padding: 18px;
  border: 1.5px dashed #c9d1cc;
  border-radius: 14px;
  background: var(--card);
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease;
}

.ot-drop:hover,
.ot-drop.drag {
  border-color: var(--leaf);
  background: #f1f9f4;
}

.ot-drop.bad {
  border-color: var(--red);
}

.ot-drop__input {
  display: none;
}

.ot-drop__empty {
  display: flex;
  align-items: center;
  gap: 14px;
  color: var(--ink-soft);
  font-size: 13px;
  line-height: 1.5;
}

.ot-drop__empty svg {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  fill: none;
  stroke: var(--leaf-deep);
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ot-drop__empty strong {
  color: var(--ink);
}

.ot-drop__file {
  display: grid;
  grid-template-columns: 64px minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
}

.ot-drop__file :is(img, video) {
  width: 64px;
  height: 64px;
  border-radius: 10px;
  object-fit: cover;
}

.ot-drop__name {
  display: grid;
  min-width: 0;
}

.ot-drop__name strong {
  overflow: hidden;
  font-size: 13px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ot-drop__name small {
  color: var(--ink-soft);
  font-size: 12px;
}

/* ── Motion ───────────────────────────────────────────────── */
.expand-enter-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}

.expand-enter-from {
  opacity: 0;
  transform: translateY(-6px);
}

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-active :is(.ot-modal, .ot-proof-modal),
.modal-leave-active :is(.ot-modal, .ot-proof-modal) {
  transition: transform 0.22s var(--ease-out, ease);
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from :is(.ot-modal, .ot-proof-modal),
.modal-leave-to :is(.ot-modal, .ot-proof-modal) {
  transform: translateY(12px) scale(0.98);
}

/* ── Small screens ────────────────────────────────────────── */
@media (max-width: 760px) {
  .ot-search {
    width: 100%;
  }

  .ot-offer {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .ot-offer__actions {
    grid-column: 1 / -1;
  }

  .ot-card__head {
    grid-template-columns: auto minmax(0, 1fr);
    padding: 16px 16px 0;
  }

  .ot-card__end {
    grid-column: 1 / -1;
    grid-template-columns: auto auto 1fr;
    align-items: center;
    justify-items: start;
    gap: 10px;
  }

  .ot-card__total {
    font-size: 20px;
  }

  .ot-card__end .ot-pay {
    justify-self: end;
  }

  .ot-progress,
  .ot-cancelled,
  .ot-receive {
    margin-inline: 16px;
  }

  .ot-case-note {
    margin-inline: 16px !important;
  }

  .ot-progress {
    padding: 14px 10px 12px;
  }

  .ot-step__label {
    font-size: 10.5px;
  }

  .ot-receive {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .ot-receive .ot-btn {
    grid-column: 1 / -1;
  }

  .ot-card__foot {
    padding: 12px 16px;
  }

  .ot-detail {
    padding: 4px 16px 16px;
  }

  .ot-detail__grid,
  .ot-choice--3 {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 420px) {
  .ot-thumbs img,
  .ot-thumbs__ph {
    width: 48px;
    height: 48px;
  }

  .ot-choice {
    grid-template-columns: 1fr;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ot-card,
  .ot-toggle svg,
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
