<!-- frontend/src/views/authenticated/Checkout.vue -->
<template>
  <div class="checkout-page bc-theme">
    <NavHeader :cartCount="0" />

    <main class="bc-container co-main">
      <!-- Title and the three steps -->
      <header class="co-head">
        <div class="co-title">
          <p class="co-eyebrow">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
            Secure checkout
          </p>
          <h1>Checkout</h1>
        </div>
        <ol class="co-steps" aria-label="Checkout steps">
          <li
            v-for="s in STEPS"
            :key="s.n"
            class="co-step"
            :class="{ on: currentStep === s.n, done: currentStep > s.n }"
            :aria-current="currentStep === s.n ? 'step' : undefined"
          >
            <button type="button" :disabled="s.n >= currentStep || isProcessing" @click="goToStep(s.n)">
              <span class="co-step-num">
                <svg v-if="currentStep > s.n" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                <template v-else>{{ s.n }}</template>
              </span>
              <span class="co-step-text">
                <small>Step {{ s.n }}</small>
                {{ s.label }}
              </span>
            </button>
          </li>
        </ol>
      </header>

      <!-- Loading: the same shapes as the page -->
      <div v-if="isLoading" class="co-grid" aria-label="Loading checkout details">
        <div class="co-card">
          <span class="bc-skeleton sk-line sk-title"></span>
          <span class="bc-skeleton sk-line"></span>
          <div class="sk-fields">
            <span v-for="n in 4" :key="n" class="bc-skeleton sk-field"></span>
          </div>
          <div class="sk-cal"><span v-for="n in 35" :key="n" class="bc-skeleton sk-day"></span></div>
        </div>
        <aside class="co-summary">
          <span class="bc-skeleton sk-line sk-title"></span>
          <div v-for="n in 2" :key="n" class="sk-item">
            <span class="bc-skeleton sk-thumb"></span>
            <span class="bc-skeleton sk-line"></span>
          </div>
          <span class="bc-skeleton sk-line sk-total"></span>
        </aside>
      </div>

      <div v-else class="co-grid">
        <div class="co-flow">
          <!-- ═══ Step 1: Delivery information ═══ -->
          <section v-if="currentStep === 1" class="co-card" aria-labelledby="co-step1">
            <div class="co-card-head">
              <span class="co-card-num" aria-hidden="true">1</span>
              <div>
                <h2 id="co-step1">Delivery information</h2>
                <p>Who it's for, where it goes, and when.</p>
              </div>
            </div>

            <!-- Customer details -->
            <div class="co-block">
              <div class="co-block-head">
                <h3>Your details</h3>
                <router-link :to="{ name: 'Profile' }" class="co-link">Edit in profile</router-link>
              </div>
              <div class="co-person">
                <span class="co-avatar" aria-hidden="true">{{ initials }}</span>
                <div>
                  <strong>{{ checkoutData.user?.name || "Your name" }}</strong>
                  <span>{{ checkoutData.user?.email }}</span>
                </div>
              </div>
            </div>

            <!-- Address and contact number (from the profile; changes here are for this order only) -->
            <div class="co-block">
              <h3>Delivery address</h3>
              <label class="co-field">
                <span class="sr-only">Delivery address</span>
                <textarea
                  v-model="deliveryAddress"
                  rows="2"
                  maxlength="500"
                  placeholder="House number, street, barangay, city"
                  :class="{ invalid: touched.address && !addressOk }"
                  @blur="touched.address = true"
                ></textarea>
              </label>
              <p v-if="touched.address && !addressOk" class="co-error" role="alert">Add the full address so the shop can find it.</p>

              <!-- Outside the shop's delivery areas: the shop approves it first -->
              <div v-else-if="outsideArea" class="co-note co-note--warn" role="status" aria-live="polite">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z" /><path d="M12 7v3.5M12 13h.01" /></svg>
                <div>
                  <strong>This address is outside {{ storeName }}'s delivery areas</strong>
                  <span v-if="areaList">They deliver to {{ areaList }}.</span>
                  <span>
                    You can still order. {{ storeName }} needs to approve delivering it first, and you pay nothing until then.
                    Once they approve, you have 24 hours to pay: the full amount with GCash or Maya, or for cash on delivery,
                    half as a down payment online and the rest in cash.
                    If they can't deliver here, they'll cancel it and you won't be charged.
                  </span>
                </div>
              </div>

              <h3 class="co-mt">Contact number</h3>
              <label class="co-field co-field--icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" /></svg>
                <span class="sr-only">Contact number</span>
                <input
                  v-model="contactNumber"
                  type="tel"
                  inputmode="tel"
                  autocomplete="tel"
                  maxlength="20"
                  placeholder="09XX XXX XXXX"
                  :class="{ invalid: touched.contact && !contactOk }"
                  @blur="touched.contact = true"
                />
              </label>
              <p v-if="touched.contact && !contactOk" class="co-error" role="alert">Enter a mobile number the rider can call, like 0917 123 4567.</p>
              <p class="co-hint">Changes here are for this order only. Your profile stays the same.</p>
            </div>

            <!-- Preferred delivery date -->
            <div class="co-block">
              <div class="co-block-head">
                <h3>Preferred delivery date</h3>
                <span v-if="selectedDate" class="co-chosen">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                  {{ formatSelectedDate }}
                </span>
              </div>
              <ul class="co-facts">
                <li v-for="fact in deliveryFacts" :key="fact">{{ fact }}</li>
              </ul>

              <div class="co-cal">
                <div class="co-cal-head">
                  <button type="button" class="co-icon-btn" :disabled="!canGoPrevious" aria-label="Previous month" @click="previousMonth">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                  </button>
                  <h4 aria-live="polite">{{ currentMonthName }} {{ currentYear }}</h4>
                  <button type="button" class="co-icon-btn" :disabled="!canGoNext" aria-label="Next month" @click="nextMonth">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                  </button>
                </div>
                <div class="co-cal-grid">
                  <span v-for="d in dayHeaders" :key="d" class="co-dow" aria-hidden="true">{{ d.slice(0, 2) }}</span>
                  <button
                    v-for="day in calendarDays"
                    :key="day.dateString"
                    type="button"
                    class="co-day"
                    :class="{
                      out: !day.isCurrentMonth,
                      picked: selectedDate === day.dateString,
                      off: day.isDisabled,
                      [day.colorClass]: true,
                    }"
                    :aria-disabled="day.isDisabled"
                    :aria-pressed="selectedDate === day.dateString"
                    :aria-label="`${day.dateString}${day.label ? ', ' + day.label : ''}`"
                    @click="selectDate(day)"
                  >
                    <span>{{ day.day }}</span>
                    <small v-if="day.label">{{ day.label }}</small>
                  </button>
                </div>
                <ul class="co-legend">
                  <li><i class="lg-available"></i>Available</li>
                  <li><i class="lg-almost"></i>Almost full</li>
                  <li><i class="lg-prep"></i>Preparing</li>
                  <li><i class="lg-full"></i>Fully booked</li>
                  <li><i class="lg-off"></i>Closed</li>
                </ul>
              </div>
            </div>

            <!-- Notes for the shop -->
            <div class="co-block">
              <label class="co-block-head" for="co-notes"><h3>Notes for the shop <small>(optional)</small></h3></label>
              <textarea
                id="co-notes"
                v-model="customerNotes"
                class="co-textarea"
                rows="3"
                maxlength="1000"
                placeholder="Gate code, card message, “please call when outside”…"
              ></textarea>
            </div>

            <div class="co-actions">
              <button type="button" class="co-back" @click="$router.push('/customer/cart')">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                Back to cart
              </button>
              <button type="button" class="co-btn" @click="goToStep(2)">
                Continue to payment
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
              </button>
            </div>
          </section>

          <!-- ═══ Step 2: Payment method ═══ -->
          <section v-if="currentStep === 2" class="co-card" aria-labelledby="co-step2">
            <div class="co-card-head">
              <span class="co-card-num" aria-hidden="true">2</span>
              <div>
                <h2 id="co-step2">Payment method</h2>
                <p>How would you like to pay?</p>
              </div>
            </div>

            <div class="co-pay" role="radiogroup" aria-label="Payment method">
              <label
                v-for="option in payOptions"
                :key="option.key"
                class="co-pay-option"
                :class="{ on: payChoice === option.key, locked: isProcessing }"
              >
                <input v-model="payChoice" type="radio" name="payment" :value="option.key" :disabled="isProcessing" />
                <span class="co-pay-mark" :class="option.key" aria-hidden="true">
                  <svg v-if="option.key === 'cod'" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="11" rx="2" /><circle cx="12" cy="12.5" r="2.5" /></svg>
                  <template v-else>{{ option.mark }}</template>
                </span>
                <span class="co-pay-text">
                  <strong>{{ option.name }}</strong>
                  <small>{{ option.desc }}</small>
                </span>
                <span class="co-radio" aria-hidden="true"></span>
              </label>
            </div>
            <p v-if="!payOptions.length" class="co-error">This shop can't take payments right now. Please try again later or message the shop.</p>

            <div v-if="payChoice === 'cod'" class="co-note">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></svg>
              <div>
                <template v-if="outsideArea">
                  <strong>Half now, half on delivery</strong>
                  <span>
                    Your address is outside {{ storeName }}'s delivery areas. Once they approve your order, pay a ₱{{ formatPrice(depositAmount) }} down payment
                    with GCash or Maya within 24 hours. Then pay the rider the other ₱{{ formatPrice(totalAmount - depositAmount) }} in cash.
                  </span>
                </template>
                <template v-else>
                  <strong>Pay the rider when it arrives</strong>
                  <span>Have ₱{{ formatPrice(totalAmount) }} ready, and keep your phone on so the rider can reach you.</span>
                </template>
              </div>
            </div>
            <div v-else-if="payChoice" class="co-note">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
              <div>
                <template v-if="outsideArea">
                  <strong>You'll pay after {{ storeName }} approves</strong>
                  <span>
                    Your address is outside their delivery areas, so nothing is charged now. When they approve, we'll let you know.
                    Then pay ₱{{ formatPrice(totalAmount) }} with {{ payChoice === "gcash" ? "GCash" : "Maya" }} from My orders within 24 hours.
                  </span>
                </template>
                <template v-else>
                  <strong>You'll pay on PayMongo's secure page</strong>
                  <span>After you place the order, we open {{ payChoice === "gcash" ? "GCash" : "Maya" }} to finish paying. Nothing is charged until you confirm there.</span>
                </template>
              </div>
            </div>

            <!-- Offers: unticked unless they choose it; hidden if they already get offers -->
            <label v-if="showOfferOptIn" class="co-optin">
              <input v-model="offerOptIn" type="checkbox" />
              <span>
                <strong>Send me offers from shops I order from</strong>
                <small>Like birthday treats or discounts. Optional, and you can stop anytime in your profile.</small>
              </span>
            </label>

            <div class="co-actions">
              <button type="button" class="co-back" :disabled="isProcessing" @click="goToStep(1)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                Delivery
              </button>
              <button type="button" class="co-btn" :disabled="!payChoice" @click="goToStep(3)">
                Review order
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
              </button>
            </div>
          </section>

          <!-- ═══ Step 3: Review and place order ═══ -->
          <section v-if="currentStep === 3" class="co-card" aria-labelledby="co-step3">
            <div class="co-card-head">
              <span class="co-card-num" aria-hidden="true">3</span>
              <div>
                <h2 id="co-step3">Review and place order</h2>
                <p>Check everything once more.</p>
              </div>
            </div>

            <div class="co-review">
              <div class="co-review-box">
                <div class="co-block-head">
                  <h3>Delivery</h3>
                  <button type="button" class="co-link" :disabled="isProcessing" @click="goToStep(1)">Edit</button>
                </div>
                <dl class="co-dl">
                  <div><dt>To</dt><dd>{{ checkoutData.user?.name }} · {{ contactNumber }}</dd></div>
                  <div><dt>Address</dt><dd>{{ deliveryAddress }}</dd></div>
                  <div><dt>Date</dt><dd>{{ formatSelectedDate }}</dd></div>
                  <div v-if="customerNotes"><dt>Notes</dt><dd class="co-pre">{{ customerNotes }}</dd></div>
                </dl>
              </div>
              <div class="co-review-box">
                <div class="co-block-head">
                  <h3>Payment</h3>
                  <button type="button" class="co-link" :disabled="isProcessing" @click="goToStep(2)">Edit</button>
                </div>
                <p class="co-paid-with">
                  <span class="co-pay-mark sm" :class="payChoice" aria-hidden="true">
                    <svg v-if="payChoice === 'cod'" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="11" rx="2" /><circle cx="12" cy="12.5" r="2.5" /></svg>
                    <template v-else>{{ payChoice === "gcash" ? "G" : "M" }}</template>
                  </span>
                  {{ chosenPayName }}
                </p>
              </div>
            </div>

            <!-- Ordered products -->
            <div class="co-block">
              <div class="co-block-head">
                <h3>Ordered products</h3>
                <span class="co-soft">From {{ checkoutData.vendor?.store_name || "the shop" }}</span>
              </div>
              <div v-for="entry in shortBouquets" :key="entry.key" class="co-warn" role="status">
                <span class="co-warn-icon" aria-hidden="true">!</span>
                <div>
                  <strong>{{ shortBouquets.length > 1 ? "A custom bouquet isn't full" : "Your custom bouquet isn't full" }}</strong>
                  <span>
                    You chose {{ entry.short.size }} flowers but only placed {{ entry.short.placed }}.
                    {{ entry.short.missing === 1 ? "1 spot is" : `${entry.short.missing} spots are` }} still empty.
                  </span>
                </div>
                <button type="button" class="co-link" @click="fillBouquet(entry)">Fill it</button>
              </div>
              <ul class="co-items">
                <li v-for="(item, index) in checkoutData.items" :key="item.cart_item_id ?? `${item.product_id}-${index}`" class="co-item">
                  <span class="co-item-img">
                    <img :src="item.product_image || FALLBACK_IMAGE" :alt="item.product_name" @error="handleImageError" />
                  </span>
                  <span class="co-item-main">
                    <strong>{{ item.product_name || "Product" }}</strong>
                    <small>
                      Qty {{ item.quantity }}
                      <template v-if="item.color"> · {{ item.color }}</template>
                      · ₱{{ formatPrice(effectivePrice(item)) }} each
                      <span v-if="hasDiscount(item)" class="co-off">−{{ discountPercent(item) }}%</span>
                    </small>
                  </span>
                  <span class="co-item-total">₱{{ formatPrice(lineTotal(item)) }}</span>
                </li>
              </ul>
            </div>

            <!-- Amounts -->
            <dl class="co-totals">
              <div><dt>Subtotal</dt><dd>₱{{ formatPrice(checkoutOriginalTotal) }}</dd></div>
              <div v-if="checkoutTotalSavings > 0" class="save"><dt>Discount</dt><dd>−₱{{ formatPrice(checkoutTotalSavings) }}</dd></div>
              <div><dt>Delivery fee</dt><dd v-if="deliveryFee > 0">₱{{ formatPrice(deliveryFee) }}</dd><dd v-else class="by-shop">Set by the shop</dd></div>
              <div class="grand"><dt>Total amount</dt><dd>₱{{ formatPrice(totalAmount) }}</dd></div>
            </dl>
            <p v-if="!(deliveryFee > 0)" class="co-delivery-note">Each shop books its own third-party courier, so the shop sets the delivery fee. It isn't included in this total.</p>

            <div class="co-actions co-actions--final">
              <button type="button" class="co-back" :disabled="isProcessing" @click="goToStep(2)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                Payment
              </button>
              <button type="button" class="co-btn co-btn--place" :disabled="!selectedPaymentMethod || isProcessing" :aria-busy="isProcessing" @click="placeOrder">
                <template v-if="isProcessing">Placing your order…</template>
                <template v-else>
                  {{ payChoice === "cod" ? "Place order" : "Place order and pay" }} · ₱{{ formatPrice(totalAmount) }}
                </template>
              </button>
            </div>
            <p class="co-terms">By placing your order you agree to the shop's terms and BloomCraft's policies.</p>
          </section>
        </div>

        <!-- ═══ Order summary (every step) ═══ -->
        <aside class="co-summary" aria-labelledby="co-sum-title">
          <div class="co-sum-head">
            <h2 id="co-sum-title">Order summary</h2>
            <span class="co-count">{{ itemCount }} {{ itemCount === 1 ? "item" : "items" }}</span>
          </div>
          <p v-if="checkoutData.vendor?.store_name" class="co-shop">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9l2-5h12l2 5M4 9h16v11H4zM9 20v-5h6v5" /></svg>
            {{ checkoutData.vendor.store_name }}
          </p>
          <ul class="co-sum-items">
            <li v-for="(item, index) in checkoutData.items" :key="item.cart_item_id ?? `${item.product_id}-${index}`">
              <span class="co-sum-img">
                <img :src="item.product_image || FALLBACK_IMAGE" :alt="item.product_name" @error="handleImageError" />
                <b>{{ item.quantity }}</b>
              </span>
              <span class="co-sum-name">{{ item.product_name }}</span>
              <span class="co-sum-price">₱{{ formatPrice(effectivePrice(item) * item.quantity) }}</span>
            </li>
          </ul>
          <dl class="co-totals co-totals--side">
            <div><dt>Subtotal</dt><dd>₱{{ formatPrice(checkoutOriginalTotal) }}</dd></div>
            <div v-if="checkoutTotalSavings > 0" class="save"><dt>Discount</dt><dd>−₱{{ formatPrice(checkoutTotalSavings) }}</dd></div>
            <div><dt>Delivery fee</dt><dd v-if="deliveryFee > 0">₱{{ formatPrice(deliveryFee) }}</dd><dd v-else class="by-shop">Set by the shop</dd></div>
            <div class="grand"><dt>Total</dt><dd>₱{{ formatPrice(totalAmount) }}</dd></div>
          </dl>
          <p v-if="!(deliveryFee > 0)" class="co-delivery-note">Each shop books its own third-party courier, so the shop sets the delivery fee. It isn't included in this total.</p>
          <p v-if="selectedDate" class="co-sum-date">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M4 10h16M9 3v4M15 3v4" /></svg>
            Delivery on <strong>{{ formatSelectedDate }}</strong>
          </p>
          <p class="co-secure">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.4 3 8.3 7 9.5 4-1.2 7-5.1 7-9.5V6z" /><path d="m9 12 2 2 4-4" /></svg>
            Payments are handled securely by PayMongo.
          </p>
        </aside>
      </div>
    </main>

    <!-- Asked before placing an order with a custom bouquet that isn't full -->
    <Teleport to="body">
      <div v-if="shortAsk" class="short-backdrop bc-theme" @click.self="shortAsk = false">
        <div
          class="short-modal"
          role="alertdialog"
          aria-modal="true"
          aria-labelledby="short-title"
          aria-describedby="short-message"
          @keydown.esc="shortAsk = false"
        >
          <p class="co-eyebrow">Before you place the order</p>
          <h2 id="short-title">
            {{ shortBouquets.length === 1 ? "Your bouquet isn't" : `${shortBouquets.length} bouquets aren't` }} full yet
          </h2>
          <p id="short-message">
            <template v-if="shortBouquets.length === 1">
              You chose {{ shortBouquets[0].short.size }} flowers but only placed {{ shortBouquets[0].short.placed }}.
              {{ shortBouquets[0].short.missing === 1 ? "1 spot is" : `${shortBouquets[0].short.missing} spots are` }} still empty.
            </template>
            <template v-else>Some of your custom bouquets still have empty flower spots, so they'll look smaller than you picked.</template>
          </p>
          <div class="short-actions">
            <button ref="shortFillButton" type="button" class="co-btn" @click="fillBouquet(shortBouquets[0])">Fill the rest of the flowers</button>
            <button type="button" class="co-link" @click="placeShortAnyway">Place order as it is</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Back from PayMongo without paying: say why -->
    <PaymentFailedModal
      :show="paymentFailure.show"
      :failure="paymentFailure.failure"
      :order-number="paymentFailure.orderNumber"
      primary-label="Choose how to pay"
      @close="paymentFailure.show = false"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from "vue";
import { useRouter, useRoute } from "vue-router";
import NavHeader from "../../layouts/NavHeader.vue";
import PaymentFailedModal from "../../components/customer/PaymentFailedModal.vue";
import "../../styles/bloomcraft.css";
import api from "../../plugins/axios.js";
import { showCheckoutPaused } from "../../composables/usePlatformStatus";
import { toast } from "@/utils/snackbar";
import { usePrice } from "../../composables/usePrice.js";
import { bouquetShortfall } from "../../utils/customizerUi.js";

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

// The three steps
const STEPS = [
  { n: 1, label: "Delivery" },
  { n: 2, label: "Payment" },
  { n: 3, label: "Review" },
];

// State
const currentStep = ref(1);
const isLoading = ref(false);
const isProcessing = ref(false);
// Shop offers: an unticked checkbox, shown only to customers who don't get offers yet
const offerOptIn = ref(false);
const showOfferOptIn = ref(false);
async function loadOfferConsent() {
  try {
    const { data } = await api.get("/customer/preferences");
    showOfferOptIn.value = !data?.data?.accept_offers;
  } catch {
    showOfferOptIn.value = false;
  }
}
/** Saves a ticked box; never holds up the order */
async function saveOfferOptIn() {
  if (!showOfferOptIn.value || !offerOptIn.value) return;
  try {
    await api.post("/customer/offers/consent", { accept: true, source: "checkout" });
  } catch {
    // They can still switch offers on in their profile
  }
}
const isDirectCheckout = ref(false);
const directCheckoutData = ref(null);
const pendingOnlineOrderId = ref(null);
const paymentFailure = ref({ show: false, failure: null, orderNumber: "" });
const CHECKOUT_DRAFT_KEY = "checkout_payment_draft";

// The unpaid e-wallet order this tab sent to PayMongo. It belongs to one set of
// items: a later checkout with different items must never reuse it (that
// charged for an old cart). `atPayMongo` is set while the customer is on
// PayMongo, so coming back (Cancel or the Back button) always explains it.
const PENDING_ORDER_KEY = "pending_online_order";
const PAYMENT_FALLBACK = {
  reason: "cancelled",
  title: "Payment wasn't finished",
  message: "You came back from the PayMongo page before the payment went through.",
  next: "Nothing was charged. Choose how to pay to try again.",
  code: null,
};

function checkoutItemsKey() {
  try {
    const direct = sessionStorage.getItem("directCheckout");
    if (isDirectCheckout.value && direct) {
      const d = JSON.parse(direct);
      return "direct:" + JSON.stringify(d.custom_items ?? d.items?.map((i) => [i.product_id, i.quantity]) ?? []);
    }
    const ids = JSON.parse(localStorage.getItem("checkout_data") || "null")?.cart_item_ids ?? [];
    return "cart:" + [...ids].map(Number).sort((a, b) => a - b).join(",");
  } catch {
    return "";
  }
}

// One key per checkout attempt, shared by tabs with the same items: sending
// the same checkout twice (double click, a second tab) returns the order
// already made instead of making another. A new key after each result.
const CHECKOUT_KEYS = "bc_checkout_keys";

function readCheckoutKeys() {
  try {
    return JSON.parse(localStorage.getItem(CHECKOUT_KEYS) || "{}") || {};
  } catch {
    return {};
  }
}

function checkoutKeyFor(itemsKey) {
  const keys = readCheckoutKeys();
  if (!keys[itemsKey]) {
    keys[itemsKey] = crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
    try {
      localStorage.setItem(CHECKOUT_KEYS, JSON.stringify(keys));
    } catch {
      // storage unavailable: the key still covers a double click on this page
    }
  }
  return keys[itemsKey];
}

function finishCheckoutKey(itemsKey) {
  const keys = readCheckoutKeys();
  delete keys[itemsKey];
  try {
    localStorage.setItem(CHECKOUT_KEYS, JSON.stringify(keys));
  } catch {
    // nothing to clear
  }
}

function readPendingOrder() {
  try {
    return JSON.parse(sessionStorage.getItem(PENDING_ORDER_KEY) || "null");
  } catch {
    return null;
  }
}

function savePendingOrder(id, atPayMongo) {
  try {
    sessionStorage.setItem(PENDING_ORDER_KEY, JSON.stringify({ id: Number(id), key: checkoutItemsKey(), atPayMongo }));
  } catch {
    // storage unavailable: a retry simply starts a new order
  }
}

function forgetPendingOrder() {
  pendingOnlineOrderId.value = null;
  try {
    sessionStorage.removeItem(PENDING_ORDER_KEY);
    sessionStorage.removeItem("pending_online_order_id"); // older pages saved it here
  } catch {
    // nothing to clear
  }
}

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

// ── Delivery details: from the profile, changeable for this order only ──
const deliveryAddress = ref("");
const contactNumber = ref("");
const touched = reactive({ address: false, contact: false });
const addressOk = computed(() => deliveryAddress.value.trim().length >= 8);
// A Philippine mobile (09…, +63…) or any 10 to 13 digit number
const contactOk = computed(() => {
  const digits = contactNumber.value.replace(/\D/g, "");
  return digits.length >= 10 && digits.length <= 13;
});
const initials = computed(() =>
  String(checkoutData.value.user?.name || "?")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join(""),
);
const itemCount = computed(() => (checkoutData.value.items || []).reduce((n, item) => n + Number(item.quantity || 0), 0));
const deliveryFee = computed(() => Number(checkoutData.value.summary?.delivery_fee || 0));

// ── Delivery areas: is this address one the shop delivers to? ──
// Checked by the server (it reads the places on the shop's list from the
// address); checked again as the address is edited.
const area = ref(null); // { covered, restricted, areas, found }
let areaFor = null; // the address `area` is for
let areaTimer = 0;
let areaSeq = 0;
const outsideArea = computed(() => !!area.value && !area.value.covered);
const storeName = computed(() => checkoutData.value.vendor?.store_name || "The shop");
const areaList = computed(() => {
  const list = area.value?.areas ?? [];
  return list.length > 1 ? `${list.slice(0, -1).join(", ")} and ${list[list.length - 1]}` : list[0] || "";
});
// Cash on delivery outside the areas: half online once the shop approves (matches the server's 50%)
const depositAmount = computed(() => Math.round(Number(totalAmount.value || 0) * 50) / 100);
async function checkArea(address) {
  const seq = ++areaSeq;
  try {
    const { data } = await api.post("checkout/delivery-area", { vendor_id: checkoutData.value.vendor?.id, address });
    if (seq !== areaSeq) return;
    area.value = data.data;
    areaFor = address;
  } catch {
    // Keep the last answer; the order is checked again when it's placed
  }
}
watch(deliveryAddress, (address) => {
  clearTimeout(areaTimer);
  const text = address.trim();
  if (!checkoutData.value.vendor?.id || text === areaFor || text.length < 8) return;
  areaTimer = setTimeout(() => checkArea(text), 450);
});
onBeforeUnmount(() => clearTimeout(areaTimer));

// ── Payment: GCash and Maya (both through PayMongo) or cash on delivery ──
const payChoice = ref(null); // "gcash" | "paymaya" | "cod"
const payOptions = computed(() => {
  const types = availablePaymentMethods.value.map((m) => m.type);
  const list = [];
  if (types.includes("ewallet")) {
    list.push({ key: "gcash", mark: "G", name: "GCash", desc: "Pay with your GCash wallet on PayMongo's secure page" });
    list.push({ key: "paymaya", mark: "M", name: "Maya", desc: "Pay with your Maya wallet on PayMongo's secure page" });
  }
  if (types.includes("cod")) {
    list.push({ key: "cod", name: "Cash on Delivery", desc: "Pay the rider in cash when your flowers arrive" });
  }
  return list;
});
const chosenPayName = computed(() => payOptions.value.find((o) => o.key === payChoice.value)?.name || "");
// The order still says "ewallet" or "cod"; the wallet goes along as ewallet_type
watch(payChoice, (choice) => {
  if (choice) selectedPaymentMethod.value = choice === "cod" ? "cod" : "ewallet";
});
// The address moved outside the areas: a wallet picked before isn't on offer now
watch(payOptions, (list) => {
  if (payChoice.value && !list.some((o) => o.key === payChoice.value)) syncPayChoice();
});
function syncPayChoice(saved = null) {
  const keys = payOptions.value.map((o) => o.key);
  if (saved && keys.includes(saved)) payChoice.value = saved;
  else if (selectedPaymentMethod.value === "cod" && keys.includes("cod")) payChoice.value = "cod";
  else payChoice.value = keys[0] || null;
}

// ── Delivery date hints, in place of the long notice ──
const deliveryFacts = computed(() => {
  const facts = [];
  const lead = leadTimeDays.value;
  const earliest = getPhilippinesToday();
  earliest.setDate(earliest.getDate() + lead + 1);
  facts.push(`Earliest: ${earliest.toLocaleDateString("en-PH", { weekday: "short", month: "short", day: "numeric" })}`);
  if (lead > 0) facts.push(`${lead} ${lead === 1 ? "day" : "days"} to prepare`);
  const s = vendorReservationSettings.value;
  if (s.sameDayDelivery && s.sameDayAvailableToday) facts.push(`Same day until ${formatCutoffTime(s.cutoffTimeToday)}`);
  else facts.push("No same-day delivery today");
  if (selectedDate.value) facts.push(`${selectedDateRemainingCapacity.value} slots left that day`);
  return facts;
});

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

// How many more orders the shop can take on the chosen day ("3 slots left that day")
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
  // The calendar belongs to the shop; there is nothing to ask for without one
  if (!checkoutData.value.vendor?.id) return;

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

/** Step 1 is done when the address, number and date are there */
function deliveryReady() {
  touched.address = true;
  touched.contact = true;
  if (!addressOk.value) return toast.warning("Add your delivery address first."), false;
  if (!contactOk.value) return toast.warning("Add a contact number the rider can call."), false;
  if (!selectedDate.value) return toast.warning("Pick a delivery date first."), false;
  return true;
}

function goToStep(step) {
  if (step > currentStep.value) {
    if (step >= 2 && !deliveryReady()) {
      currentStep.value = 1;
      return;
    }
    if (step === 3 && !payChoice.value) {
      toast.warning("Choose how you'll pay first.");
      return;
    }
  }
  currentStep.value = step;
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function saveCheckoutDraft() {
  sessionStorage.setItem(CHECKOUT_DRAFT_KEY, JSON.stringify({
    selectedDate: selectedDate.value,
    customerNotes: customerNotes.value,
    selectedPaymentMethod: selectedPaymentMethod.value,
    payChoice: payChoice.value,
    deliveryAddress: deliveryAddress.value,
    contactNumber: contactNumber.value,
  }));
}

function restoreCheckoutDraft() {
  try {
    const draft = JSON.parse(sessionStorage.getItem(CHECKOUT_DRAFT_KEY) || "null");
    if (!draft) return;
    selectedDate.value = draft.selectedDate || null;
    // A date picked on the product page may be next month: open the calendar there
    if (/^\d{4}-\d{2}-\d{2}$/.test(selectedDate.value || "")) {
      const [year, month] = selectedDate.value.split("-").map(Number);
      calendarDate.value = new Date(year, month - 1, 1);
    }
    customerNotes.value = draft.customerNotes || "";
    selectedPaymentMethod.value = draft.selectedPaymentMethod || selectedPaymentMethod.value;
    if (draft.deliveryAddress) deliveryAddress.value = draft.deliveryAddress;
    if (draft.contactNumber) contactNumber.value = draft.contactNumber;
    syncPayChoice(draft.payChoice);
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
    // Paid after all (PayMongo confirmed it before the customer came back)
    if (order?.payment_status === "paid") {
      forgetPendingOrder();
      router.replace({ path: "/customer/orders", query: { payment: "success" } });
      return "paid";
    }
    paymentFailure.value = {
      show: false,
      failure: order?.payment_failure || null,
      orderNumber: order?.order_number || "",
    };
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
    if (error.response?.status === 404) {
      forgetPendingOrder();
    }
    console.warn("Unable to restore cancelled checkout order", error);
  }
}

async function abandonPendingOnlinePayment(orderId = pendingOnlineOrderId.value) {
  if (!orderId) return;

  try {
    await api.post(
      `/checkout/orders/${orderId}/abandon-payment`,
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
            color: directCheckoutData.value.items[0].color || null, // picked on the product page
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
    // The profile address was already checked against the shop's delivery areas
    area.value = checkoutData.value.delivery_area ?? null;
    areaFor = String(checkoutData.value.user?.address || "").trim();
    deliveryAddress.value = checkoutData.value.user?.address || "";
    contactNumber.value = checkoutData.value.user?.contact_number || "";
    syncPayChoice();
    // PayMongo replaces this page. Restore the draft only after payment
    // options are loaded, so its selected method can be retained safely.
    restoreCheckoutDraft();
  } catch (error) {
    console.error("Error loading checkout:", error);
    // 503 = an admin paused checkout; explain it in the maintenance modal
    if (error.response?.status === 503) showCheckoutPaused(error.response.data?.message);
    else toast.error("Failed to initialize checkout");
    router.push("/customer/cart");
  } finally {
    isLoading.value = false;
  }
}

// Custom bouquets with fewer flowers than the size chosen: [{ key, item, short }].
// A bouquet bought straight from the customizer comes as one item per flower,
// all with the same design, so it counts once.
const shortBouquets = computed(() => {
  const seen = new Set();
  const list = [];
  for (const item of checkoutData.value?.items || []) {
    const short = bouquetShortfall(item.customizations);
    const key = item.cart_item_id ? `cart-${item.cart_item_id}` : "direct";
    if (!short || seen.has(key)) continue;
    seen.add(key);
    list.push({ key, item, short });
  }
  return list;
});
const shortAsk = ref(false);
const shortFillButton = ref(null);
let shortAccepted = false;

/** Opens the bouquet in the customizer to fill its empty spots */
function fillBouquet(entry) {
  shortAsk.value = false;
  const design = entry?.item?.customizations || {};
  const query = { store_id: design.store_id, owner_id: design.vendor_id };
  // From the cart: edit that cart item. Bought straight from the customizer: pick up where it left off
  if (entry?.item?.cart_item_id) query.cart_item_id = entry.item.cart_item_id;
  else query.resume = "checkout";
  router.push({ path: "/customize/flower", query });
}

function placeShortAnyway() {
  shortAsk.value = false;
  shortAccepted = true;
  placeOrder();
}

async function placeOrder() {
  if (!selectedPaymentMethod.value) {
    toast.error("Please select a payment method");
    return;
  }
  if (!addressOk.value || !contactOk.value || !selectedDate.value) {
    goToStep(1);
    deliveryReady();
    return;
  }
  if (shortBouquets.value.length && !shortAccepted && !isProcessing.value) {
    shortAsk.value = true;
    setTimeout(() => shortFillButton.value?.focus(), 50);
    return;
  }
  shortAccepted = false;

  if (isProcessing.value) return;
  const itemsKey = checkoutItemsKey();
  try {
    isProcessing.value = true;

    // A payment that was cancelled or failed is finished; paying again is
    // always a new order (never the old one)
    forgetPendingOrder();

    const orderData = {
      reservation_date: selectedDate.value,
      payment_method: selectedPaymentMethod.value,
      delivery_address: deliveryAddress.value.trim(),
      contact_number: contactNumber.value.trim(),
      // GCash or Maya: PayMongo opens only that one
      ewallet_type: payChoice.value === "cod" ? null : payChoice.value,
      customer_notes: customerNotes.value,
      checkout_key: checkoutKeyFor(itemsKey),

      ...(isDirectCheckout.value
        ? directCheckoutData.value.custom_items
          ? { custom_items: directCheckoutData.value.custom_items }
          : {
              product_id: directCheckoutData.value.items[0].product_id,
              quantity: directCheckoutData.value.items[0].quantity,
              color: directCheckoutData.value.items[0].color || null,
            }
        : {
            cart_item_ids:
              JSON.parse(localStorage.getItem("checkout_data"))
                ?.cart_item_ids || [],
          }),
    };

    console.log("Sending order data:", orderData);

    const response = await api.post("/checkout/create-order", orderData);
    if (response.data?.success || response.data?.checkout_url) await saveOfferOptIn();

    console.log("Order response:", response.data);

    const checkoutUrl = response.data.checkout_url;

    if (checkoutUrl) {
      saveCheckoutDraft();
      pendingOnlineOrderId.value = response.data.order_id;
      savePendingOrder(response.data.order_id, true);

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
        forgetPendingOrder();
        finishCheckoutKey(itemsKey);
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
    const data = error.response?.data ?? {};
    // The order this checkout made has ended (its payment failed, or the
    // payment page couldn't open): it is never paid again, the next try is new
    if (data.data?.order?.id) {
      finishCheckoutKey(itemsKey);
    }
    // Checkout was paused while the customer was on this page
    if (error.response?.status === 503) showCheckoutPaused(data.message);
    else if (data.code === "payment_failed" || error.response?.status === 502) {
      paymentFailure.value = {
        show: true,
        failure: data.data?.payment_failure || {
          reason: "not_started",
          title: "We couldn't open the payment page",
          message: data.message || "PayMongo didn't respond when we tried to start your payment.",
          next: "Nothing was charged. Please try again in a few minutes.",
          code: null,
        },
        orderNumber: data.data?.order?.order_number || "",
      };
    }
    // Someone else bought the last ones first: nothing was ordered
    else if (data.code === "out_of_stock") toast.error(data.message);
    else toast.error(data.message || "Failed to place order");
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

// The browser's Back button can bring this page back from its cache without
// running it again, so nothing would be checked or explained: load it fresh
function onPageShow(event) {
  if (event.persisted && readPendingOrder()?.atPayMongo) window.location.reload();
}
onMounted(() => window.addEventListener("pageshow", onPageShow));
onBeforeUnmount(() => window.removeEventListener("pageshow", onPageShow));

onMounted(async () => {
  loadOfferConsent();
  // PayMongo's return link names the order; otherwise this tab's saved one
  const stored = readPendingOrder();
  const fromPayMongo = Number(route.query.order_id) || null;
  try {
    sessionStorage.removeItem("pending_online_order_id"); // older pages: not tied to any items, never reused
  } catch {
    // storage unavailable
  }
  pendingOnlineOrderId.value = fromPayMongo || Number(stored?.id) || null;
  await loadCheckoutData();
  // A failed load already sent the customer back to the cart
  if (!checkoutData.value.vendor?.id) return;

  // A saved order for different items (an older checkout) is closed, not reused
  if (!fromPayMongo && stored && stored.key !== checkoutItemsKey()) {
    abandonPendingOnlinePayment(stored.id);
    forgetPendingOrder();
  }

  if (pendingOnlineOrderId.value) {
    const cameBack = Boolean(route.query.payment) || stored?.atPayMongo === true;

    // PayMongo said "paid" but hadn't confirmed yet: give it a moment before
    // treating the payment as unfinished, so a real payment is never closed
    if (route.query.payment === "pending") {
      for (let i = 0; i < 4; i++) {
        if ((await restorePendingOnlineOrderState()) === "paid") return;
        await new Promise((r) => setTimeout(r, 2500));
      }
    }
    if ((await restorePendingOnlineOrderState()) === "paid") return;

    // Cancel and the browser Back button both land here: close the unfinished
    // payment so nothing stays "pending", then say what happened
    await abandonPendingOnlinePayment();
    if ((await restorePendingOnlineOrderState()) === "paid") return;
    if (pendingOnlineOrderId.value) {
      // That order is finished for good: paying again places a new order
      forgetPendingOrder();
      finishCheckoutKey(checkoutItemsKey());
      currentStep.value = 2;
      if (cameBack) {
        paymentFailure.value.failure = paymentFailure.value.failure || PAYMENT_FALLBACK;
        paymentFailure.value.show = true;
      }
    }
  }
  await loadCalendarData();
});
</script>

<style scoped>
/* Checkout: three steps on the left, the order summary on the right.
   Tokens come from styles/bloomcraft.css (navy --ink, green --leaf). */
.checkout-page,
.checkout-page * {
  box-sizing: border-box;
}

.checkout-page {
  --co-surface: #ffffff;
  --co-line: rgba(54, 63, 78, 0.1);
  --co-line-strong: rgba(54, 63, 78, 0.2);
  --co-soft-bg: rgba(54, 63, 78, 0.04);
  --co-red: #b4332a;
  min-height: 100vh;
  background: var(--ivory);
  color: var(--ink);
  font-family: var(--font-sans);
}

.checkout-page :is(h1, h2, h3, h4, p, dl, dd, ul, ol) {
  margin: 0;
}

.checkout-page :is(h2, h3, h4) {
  font-family: var(--font-sans);
  letter-spacing: 0;
  line-height: 1.3;
}

.checkout-page button {
  font-family: var(--font-sans);
}

.checkout-page svg {
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

.co-main {
  padding-top: 104px;
  padding-bottom: 72px;
}

/* ── Header + steps ───────────────────────────────────────────── */
.co-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 24px;
}

.co-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--leaf-deep);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.co-eyebrow svg {
  width: 14px;
  height: 14px;
}

.co-title h1 {
  margin-top: 4px;
  font-family: var(--font-display);
  font-size: clamp(32px, 4vw, 42px);
  font-weight: 500;
  line-height: 1.05;
}

.co-steps {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0;
  list-style: none;
}

.co-step {
  display: flex;
  align-items: center;
  gap: 8px;
}

.co-step + .co-step::before {
  content: "";
  width: 28px;
  height: 2px;
  border-radius: 2px;
  background: var(--co-line-strong);
}

.co-step.done + .co-step::before,
.co-step.on::before {
  background: var(--leaf);
}

.co-step button {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 6px 12px 6px 6px;
  border: 1px solid var(--co-line);
  border-radius: 999px;
  background: var(--co-surface);
  color: var(--ink-soft);
  font-size: 13px;
  font-weight: 500;
  text-align: left;
  cursor: default;
}

.co-step.done button {
  cursor: pointer;
}

.co-step.done button:hover {
  border-color: var(--leaf);
}

.co-step.on button {
  border-color: var(--ink);
  color: var(--ink);
  box-shadow: 0 4px 14px rgba(54, 63, 78, 0.1);
}

.co-step-num {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--co-soft-bg);
  color: var(--ink-soft);
  font-size: 13px;
  font-weight: 600;
}

.co-step.on .co-step-num {
  background: var(--ink);
  color: #ffffff;
}

.co-step.done .co-step-num {
  background: var(--leaf);
  color: #ffffff;
}

.co-step-num svg {
  width: 15px;
  height: 15px;
  stroke-width: 2.6;
}

.co-step-text {
  display: grid;
  line-height: 1.2;
}

.co-step-text small {
  color: var(--ink-soft);
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

/* ── Layout ───────────────────────────────────────────────────── */
.co-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 24px;
  align-items: start;
}

.co-card,
.co-summary {
  border: 1px solid var(--co-line);
  border-radius: 20px;
  background: var(--co-surface);
  box-shadow: 0 1px 2px rgba(54, 63, 78, 0.04);
}

.co-card {
  display: grid;
  gap: 0;
  padding: clamp(18px, 3vw, 28px);
}

.co-card-head {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding-bottom: 18px;
  border-bottom: 1px solid var(--co-line);
}

.co-card-num {
  display: grid;
  flex: none;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 12px;
  background: var(--ink);
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
}

.co-card-head h2 {
  font-size: 19px;
  font-weight: 600;
}

.co-card-head p {
  margin-top: 2px;
  color: var(--ink-soft);
  font-size: 13.5px;
}

.co-block {
  display: grid;
  gap: 10px;
  padding: 20px 0;
  border-bottom: 1px solid var(--co-line);
}

.co-block h3 {
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.co-block h3 small {
  color: var(--ink-soft);
  font-weight: 400;
  letter-spacing: 0;
  text-transform: none;
}

.co-block-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.co-mt {
  margin-top: 8px !important;
}

.co-link {
  padding: 0;
  border: 0;
  background: none;
  color: var(--leaf-deep);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
}

.co-link:hover {
  text-decoration: underline;
}

.co-link:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.co-soft {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.co-hint {
  color: var(--ink-soft);
  font-size: 12px;
}

.co-error {
  color: var(--co-red);
  font-size: 12.5px;
}

/* Customer details */
.co-person {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--co-soft-bg);
}

.co-avatar {
  display: grid;
  flex: none;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--ink);
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
}

.co-person div {
  display: grid;
  min-width: 0;
}

.co-person strong {
  font-size: 14.5px;
  font-weight: 600;
}

.co-person span {
  overflow: hidden;
  color: var(--ink-soft);
  font-size: 13px;
  text-overflow: ellipsis;
}

/* Fields */
.co-field {
  position: relative;
  display: block;
}

.co-field textarea,
.co-field input,
.co-textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--co-line-strong);
  border-radius: 12px;
  background: var(--co-surface);
  color: var(--ink);
  font: 400 14px/1.5 var(--font-sans);
  resize: vertical;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.co-field--icon input {
  padding-left: 42px;
}

.co-field--icon svg {
  position: absolute;
  top: 50%;
  left: 14px;
  width: 17px;
  height: 17px;
  color: var(--ink-soft);
  transform: translateY(-50%);
  pointer-events: none;
}

.co-field :is(textarea, input):focus,
.co-textarea:focus {
  border-color: var(--leaf);
  outline: none;
  box-shadow: 0 0 0 4px rgba(77, 177, 115, 0.14);
}

.co-field .invalid {
  border-color: var(--co-red);
}

/* Delivery date */
.co-chosen {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(77, 177, 115, 0.12);
  color: var(--leaf-deep);
  font-size: 12.5px;
  font-weight: 600;
}

.co-chosen svg {
  width: 14px;
  height: 14px;
  stroke-width: 2.4;
}

.co-facts {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 0;
  list-style: none;
}

.co-facts li {
  padding: 4px 10px;
  border: 1px solid var(--co-line);
  border-radius: 999px;
  color: var(--ink-soft);
  font-size: 12px;
}

.co-cal {
  display: grid;
  gap: 10px;
  padding: 14px;
  border: 1px solid var(--co-line);
  border-radius: 16px;
}

.co-cal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.co-cal-head h4 {
  font-size: 15px;
  font-weight: 600;
}

.co-icon-btn {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border: 1px solid var(--co-line);
  border-radius: 10px;
  background: var(--co-surface);
  color: var(--ink);
  cursor: pointer;
}

.co-icon-btn svg {
  width: 16px;
  height: 16px;
}

.co-icon-btn:hover:not(:disabled) {
  border-color: var(--ink);
}

.co-icon-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.co-cal-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 4px;
}

.co-dow {
  padding: 4px 0;
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 600;
  text-align: center;
  text-transform: uppercase;
}

.co-day {
  position: relative;
  display: grid;
  place-content: center;
  gap: 0;
  min-height: 48px;
  padding: 4px 2px;
  border: 1px solid transparent;
  border-radius: 10px;
  background: transparent;
  color: var(--ink);
  font-size: 14px;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  cursor: pointer;
  transition: background 0.12s ease, border-color 0.12s ease;
}

.co-day small {
  font-size: 9px;
  font-weight: 600;
  line-height: 1.1;
  text-transform: uppercase;
}

.co-day.day-available {
  background: rgba(77, 177, 115, 0.1);
}

.co-day.day-available:hover {
  border-color: var(--leaf);
}

.co-day.day-almost-full {
  background: rgba(209, 138, 28, 0.13);
  color: #8a520f;
}

.co-day.day-prep-time {
  background: rgba(54, 63, 78, 0.06);
  color: var(--ink-soft);
}

.co-day.day-fully-booked {
  background: rgba(180, 51, 42, 0.1);
  color: var(--co-red);
  text-decoration: line-through;
}

.co-day.day-today {
  border-color: var(--co-line-strong);
}

.co-day.day-disabled,
.co-day.off {
  color: rgba(54, 63, 78, 0.35);
  cursor: not-allowed;
}

.co-day.day-disabled {
  background: transparent;
}

.co-day.out {
  visibility: hidden;
}

.co-day.picked {
  border-color: var(--ink);
  background: var(--ink);
  color: #ffffff;
}

.co-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 14px;
  padding: 0;
  list-style: none;
  color: var(--ink-soft);
  font-size: 11.5px;
}

.co-legend li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.co-legend i {
  width: 10px;
  height: 10px;
  border-radius: 3px;
}

.lg-available { background: rgba(77, 177, 115, 0.35); }
.lg-almost { background: rgba(209, 138, 28, 0.4); }
.lg-prep { background: rgba(54, 63, 78, 0.15); }
.lg-full { background: rgba(180, 51, 42, 0.3); }
.lg-off { background: transparent; box-shadow: inset 0 0 0 1px var(--co-line-strong); }

/* Actions */
.co-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 20px;
}

.co-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 44px;
  padding: 0 4px;
  border: 0;
  background: none;
  color: var(--ink-soft);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.co-back:hover:not(:disabled) {
  color: var(--ink);
}

.co-back svg {
  width: 16px;
  height: 16px;
}

.co-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 48px;
  padding: 0 24px;
  border: 0;
  border-radius: 12px;
  background: var(--ink);
  color: #ffffff;
  font-size: 14.5px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.1s ease;
}

.co-btn:hover:not(:disabled) {
  background: var(--leaf-deep);
}

.co-btn:active:not(:disabled) {
  transform: scale(0.98);
}

.co-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.co-btn svg {
  width: 17px;
  height: 17px;
}

.co-btn--place {
  min-width: 260px;
  background: var(--leaf-deep);
}

.co-btn--place:hover:not(:disabled) {
  background: var(--ink);
}

.co-terms {
  margin-top: 10px !important;
  color: var(--ink-soft);
  font-size: 11.5px;
  text-align: right;
}

/* ── Payment ─────────────────────────────────────────────────── */
.co-pay {
  display: grid;
  gap: 10px;
  padding: 20px 0 14px;
}

.co-pay-option {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border: 1px solid var(--co-line-strong);
  border-radius: 14px;
  background: var(--co-surface);
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.co-pay-option input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.co-pay-option:hover {
  border-color: var(--ink);
}

.co-pay-option.on {
  border-color: var(--ink);
  background: var(--co-soft-bg);
  box-shadow: 0 0 0 1px var(--ink);
}

.co-pay-option.locked {
  cursor: not-allowed;
  opacity: 0.7;
}

.co-pay-option:has(input:focus-visible) {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

/* Simple wallet marks: a letter on the wallet's colour */
.co-pay-mark {
  display: grid;
  flex: none;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  color: #ffffff;
  font-size: 18px;
  font-weight: 700;
}

.co-pay-mark.gcash { background: #0a6cff; }
.co-pay-mark.paymaya { background: #0c8f5a; }
.co-pay-mark.cod { background: var(--ink); }

.co-pay-mark svg {
  width: 20px;
  height: 20px;
}

.co-pay-mark.sm {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  font-size: 13px;
}

.co-pay-mark.sm svg {
  width: 15px;
  height: 15px;
}

.co-pay-text {
  display: grid;
  flex: 1;
  min-width: 0;
}

.co-pay-text strong {
  font-size: 15px;
  font-weight: 600;
}

.co-pay-text small {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.co-radio {
  flex: none;
  width: 20px;
  height: 20px;
  border: 2px solid var(--co-line-strong);
  border-radius: 50%;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.co-pay-option.on .co-radio {
  border-color: var(--ink);
  box-shadow: inset 0 0 0 4px var(--co-surface);
  background: var(--ink);
}

.co-note {
  display: flex;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 14px;
  background: rgba(77, 177, 115, 0.08);
}

.co-note svg {
  flex: none;
  width: 20px;
  height: 20px;
  color: var(--leaf-deep);
}

.co-note div {
  display: grid;
  gap: 2px;
  font-size: 13px;
}

.co-note span {
  color: var(--ink-soft);
}

/* Outside the shop's delivery areas */
.co-note--warn {
  margin-top: 12px;
  border: 1px solid rgba(209, 138, 28, 0.28);
  background: rgba(209, 138, 28, 0.08);
}

.co-note--warn svg {
  color: #b7791f;
}

.co-note--warn div {
  gap: 4px;
}

.co-optin {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-top: 14px;
  padding: 14px 16px;
  border: 1px dashed var(--co-line-strong);
  border-radius: 14px;
  cursor: pointer;
}

.co-optin input {
  width: 18px;
  height: 18px;
  margin-top: 2px;
  accent-color: var(--leaf-deep);
}

.co-optin span {
  display: grid;
  gap: 2px;
  font-size: 13.5px;
}

.co-optin small {
  color: var(--ink-soft);
  font-size: 12px;
}

/* ── Review ──────────────────────────────────────────────────── */
.co-review {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 12px;
  padding-top: 20px;
}

.co-review-box {
  display: grid;
  align-content: start;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid var(--co-line);
  border-radius: 14px;
}

.co-dl {
  display: grid;
  gap: 6px;
}

.co-dl div {
  display: grid;
  grid-template-columns: 64px minmax(0, 1fr);
  gap: 8px;
  font-size: 13px;
}

.co-dl dt {
  color: var(--ink-soft);
}

.co-dl dd {
  overflow-wrap: anywhere;
}

.co-pre {
  white-space: pre-line;
}

.co-paid-with {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  font-weight: 600;
}

.co-warn {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 12px;
  background: #fdf0e3;
}

.co-warn-icon {
  display: grid;
  flex: none;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #d18a1c;
  color: #ffffff;
  font-weight: 700;
}

.co-warn div {
  display: grid;
  flex: 1;
  font-size: 13px;
}

.co-warn span {
  color: var(--ink-soft);
}

.co-items {
  display: grid;
  gap: 2px;
  padding: 0;
  list-style: none;
}

.co-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
}

.co-item + .co-item {
  border-top: 1px dashed var(--co-line);
}

.co-item-img {
  flex: none;
  width: 56px;
  height: 56px;
  overflow: hidden;
  border-radius: 12px;
  background: var(--ivory-deep);
}

.co-item-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.co-item-main {
  display: grid;
  flex: 1;
  min-width: 0;
}

.co-item-main strong {
  overflow: hidden;
  font-size: 14px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.co-item-main small {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.co-off {
  margin-left: 4px;
  padding: 1px 6px;
  border-radius: 5px;
  background: #fdecea;
  color: var(--co-red);
  font-size: 11px;
  font-weight: 600;
}

.co-item-total {
  font-size: 14px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.co-totals {
  display: grid;
  gap: 8px;
  padding: 18px 0 0;
}

.co-totals div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 14px;
}

.co-totals dt {
  color: var(--ink-soft);
}

.co-totals dd {
  font-variant-numeric: tabular-nums;
}

.co-totals .save dd {
  color: var(--co-red);
}

.co-totals .by-shop {
  color: var(--ink-soft);
  font-weight: 500;
}

.co-delivery-note {
  margin: 8px 0 0;
  color: var(--ink-soft);
  font-size: 12.5px;
  line-height: 1.55;
}

.co-totals .grand {
  margin-top: 6px;
  padding-top: 12px;
  border-top: 1px solid var(--co-line);
  font-size: 17px;
}

.co-totals .grand dt {
  color: var(--ink);
  font-weight: 600;
}

.co-totals .grand dd {
  font-weight: 700;
}

.co-actions--final {
  border-top: 0;
}

/* ── Summary ─────────────────────────────────────────────────── */
.co-summary {
  position: sticky;
  top: 96px;
  display: grid;
  gap: 14px;
  padding: 20px;
}

.co-sum-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.co-sum-head h2 {
  font-size: 16px;
  font-weight: 600;
}

.co-count {
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--co-soft-bg);
  color: var(--ink-soft);
  font-size: 12px;
  font-weight: 500;
}

.co-shop {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--leaf-deep);
  font-size: 13px;
  font-weight: 600;
}

.co-shop svg {
  width: 15px;
  height: 15px;
}

.co-sum-items {
  display: grid;
  gap: 12px;
  max-height: 280px;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}

.co-sum-items li {
  display: flex;
  align-items: center;
  gap: 12px;
}

.co-sum-img {
  position: relative;
  flex: none;
  width: 48px;
  height: 48px;
  border-radius: 10px;
  background: var(--ivory-deep);
}

.co-sum-img img {
  width: 100%;
  height: 100%;
  border-radius: inherit;
  object-fit: cover;
}

.co-sum-img b {
  position: absolute;
  top: -6px;
  right: -6px;
  display: grid;
  place-items: center;
  min-width: 20px;
  height: 20px;
  padding: 0 5px;
  border: 2px solid var(--co-surface);
  border-radius: 999px;
  background: var(--ink);
  color: #ffffff;
  font-size: 10.5px;
  font-weight: 600;
}

.co-sum-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  font-size: 13px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.co-sum-price {
  font-size: 13px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.co-totals--side {
  padding-top: 14px;
  border-top: 1px solid var(--co-line);
}

.co-totals--side div {
  font-size: 13.5px;
}

.co-totals--side .grand {
  font-size: 16px;
}

.co-sum-date,
.co-secure {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  color: var(--ink-soft);
  font-size: 12.5px;
}

.co-sum-date {
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--co-soft-bg);
  color: var(--ink);
}

.co-sum-date svg,
.co-secure svg {
  flex: none;
  width: 16px;
  height: 16px;
  color: var(--leaf-deep);
}

/* ── Loading ─────────────────────────────────────────────────── */
.sk-line {
  display: block;
  height: 14px;
  margin-bottom: 10px;
  border-radius: 7px;
}

.sk-title {
  width: 40%;
  height: 22px;
}

.sk-fields {
  display: grid;
  gap: 10px;
  margin: 16px 0;
}

.sk-field {
  height: 46px;
  border-radius: 12px;
}

.sk-cal {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.sk-day {
  height: 44px;
  border-radius: 10px;
}

.sk-item {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 12px 0;
}

.sk-thumb {
  width: 48px;
  height: 48px;
  border-radius: 10px;
}

.sk-item .sk-line {
  flex: 1;
  margin: 0;
}

.sk-total {
  height: 22px;
  margin-top: 16px;
}

/* ── Unfilled bouquet modal ──────────────────────────────────── */
.short-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(30, 36, 46, 0.45);
}

.short-modal {
  display: grid;
  gap: 10px;
  width: min(440px, 100%);
  padding: 24px;
  border-radius: 20px;
  background: var(--co-surface);
  box-shadow: 0 30px 80px rgba(30, 36, 46, 0.25);
}

.short-modal h2 {
  font-size: 20px;
  font-weight: 600;
}

.short-modal p:not(.co-eyebrow) {
  color: var(--ink-soft);
  font-size: 14px;
  line-height: 1.55;
}

.short-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 14px;
  margin-top: 8px;
}

/* Focus */
.checkout-page :is(button, a, input, textarea):focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

.co-field :is(textarea, input):focus-visible,
.co-textarea:focus-visible {
  outline: none;
}

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 980px) {
  .co-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .co-summary {
    position: static;
    order: -1;
  }

  .co-sum-items {
    max-height: none;
  }
}

@media (max-width: 640px) {
  .co-main {
    padding-top: 88px;
  }

  .co-steps {
    width: 100%;
  }

  .co-step {
    flex: 1;
  }

  .co-step + .co-step::before {
    width: 12px;
  }

  .co-step button {
    width: 100%;
    padding-right: 6px;
  }

  .co-step-text small {
    display: none;
  }

  .co-step:not(.on) .co-step-text {
    display: none;
  }

  .co-review {
    grid-template-columns: minmax(0, 1fr);
  }

  .co-day {
    min-height: 42px;
  }

  .co-day small {
    display: none;
  }

  .co-actions {
    flex-direction: column-reverse;
    align-items: stretch;
  }

  .co-btn,
  .co-btn--place {
    width: 100%;
    min-width: 0;
  }

  .co-terms {
    text-align: center;
  }
}

@media (prefers-reduced-motion: reduce) {
  .co-btn,
  .co-day,
  .co-pay-option {
    transition: none;
  }
}
</style>
