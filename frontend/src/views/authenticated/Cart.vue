<!-- frontend/src/views/authenticated/Cart.vue -->
<template>
  <div class="cart-page bc-theme">
    <NavHeader :cartCount="cartStore.count.value" />

    <!-- Profile incomplete modal (teleported, so it carries .bc-theme itself) -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="showProfileModal"
          class="modal-backdrop bc-theme"
          @click.self="showProfileModal = false"
        >
          <div
            class="modal-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-title"
          >
            <p class="bc-label">Almost there</p>
            <h2 id="modal-title" class="modal-title">
              Complete your <em>profile</em>
            </h2>
            <p class="modal-message">
              Before you can check out, please add your
              <strong>{{ missingProfileFields }}</strong> to your profile so we
              know where to deliver your order.
            </p>
            <div class="modal-actions">
              <button
                class="bc-btn cart-btn-outline"
                @click="showProfileModal = false"
              >
                Back to cart
              </button>
              <button class="bc-btn bc-btn--dark" @click="goToProfile">
                Complete profile
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Asked before checking out a custom bouquet with fewer flowers than chosen -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="shortBouquets.length" class="modal-backdrop bc-theme" @click.self="shortBouquets = []">
          <div
            class="modal-card"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="short-title"
            aria-describedby="short-message"
            @keydown.esc="shortBouquets = []"
          >
            <p class="bc-label">Before you check out</p>
            <h2 id="short-title" class="modal-title">
              {{ shortBouquets.length === 1 ? "Your bouquet isn't" : `${shortBouquets.length} bouquets aren't` }} <em>full</em> yet
            </h2>
            <p id="short-message" class="modal-message">
              <template v-if="shortBouquets.length === 1">
                You chose {{ shortBouquets[0].short.size }} flowers but only placed {{ shortBouquets[0].short.placed }}.
                {{ shortBouquets[0].short.missing === 1 ? "1 spot is" : `${shortBouquets[0].short.missing} spots are` }} still empty.
              </template>
              <template v-else>Some of your custom bouquets still have empty flower spots, so they'll look smaller than you picked.</template>
            </p>
            <div class="short-actions">
              <button ref="fillRestButton" class="bc-btn bc-btn--dark" @click="editBouquet(shortBouquets[0].item)">
                Fill the rest of the flowers
              </button>
              <button class="bc-link short-anyway" @click="checkoutShortAnyway">Check out as it is</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <main class="bc-container cart-container" :class="{ 'has-mobile-bar': !isLoading && cartItems.length > 0 }">
      <header class="cart-hero">
        <div class="cart-hero-text">
          <p class="bc-label">Your selection</p>
          <div class="cart-title-row">
            <h1>Shopping <em>cart</em></h1>
            <span v-if="!isLoading" class="cart-count-chip">
              {{ cartItems.length }} {{ cartItems.length === 1 ? "item" : "items" }}
            </span>
            <span v-else class="bc-skeleton cart-count-skeleton"></span>
          </div>
        </div>
        <ol class="cart-steps" aria-label="Checkout steps">
          <li class="is-current" aria-current="step"><span class="step-dot">1</span>Cart</li>
          <li><span class="step-dot">2</span>Checkout</li>
          <li><span class="step-dot">3</span>Payment</li>
        </ol>
      </header>

      <!-- Skeleton while the cart loads -->
      <div v-if="isLoading" class="cart-content" aria-label="Loading your cart">
        <div class="cart-items">
          <span class="bc-skeleton skeleton-toolbar"></span>
          <div class="vendor-card">
            <div class="vendor-head">
              <span class="bc-skeleton skeleton-avatar"></span>
              <span class="bc-skeleton skeleton-line skeleton-line--vendor"></span>
            </div>
            <div v-for="n in 3" :key="n" class="cart-item cart-item--skeleton">
              <span class="bc-skeleton skeleton-check"></span>
              <span class="bc-skeleton skeleton-image"></span>
              <div class="skeleton-lines">
                <span class="bc-skeleton skeleton-line skeleton-line--short"></span>
                <span class="bc-skeleton skeleton-line skeleton-line--title"></span>
                <span class="bc-skeleton skeleton-line skeleton-line--qty"></span>
              </div>
              <span class="bc-skeleton skeleton-line skeleton-price"></span>
            </div>
          </div>
        </div>
        <aside class="order-summary">
          <span class="bc-skeleton skeleton-line skeleton-line--title"></span>
          <span class="bc-skeleton skeleton-line"></span>
          <span class="bc-skeleton skeleton-line"></span>
          <span class="bc-skeleton skeleton-line skeleton-total"></span>
          <span class="bc-skeleton skeleton-button"></span>
        </aside>
      </div>

      <div v-else-if="cartItems.length === 0" class="empty-cart">
        <span class="empty-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <path d="M5 8h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8L5 8Z" />
            <path d="M9 8V6.5a3 3 0 0 1 6 0V8" />
          </svg>
        </span>
        <h2>Your cart is <em>empty</em></h2>
        <p>You haven't added any flowers yet. Find a bouquet you love in the shop.</p>
        <button class="bc-btn bc-btn--dark" @click="$router.push('/shop')">
          Browse the shop
          <span class="bc-btn-arrow" aria-hidden="true">→</span>
        </button>
      </div>

      <div v-else class="cart-content">
        <div class="cart-items">
          <div
            v-if="hasLowStockItems || hasOutOfStockItems"
            class="stock-alert"
            :class="{ 'stock-alert--error': hasOutOfStockItems }"
            role="status"
          >
            <svg class="stock-alert-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 3.5 2.8 19.5h18.4L12 3.5Z" />
              <path d="M12 10v4.2M12 17h.01" />
            </svg>
            <p v-if="hasOutOfStockItems">
              Some items in your cart are out of stock. Remove them to continue.
            </p>
            <p v-else>Some items in your cart are running low. Order soon!</p>
          </div>

          <div class="cart-toolbar">
            <label class="check">
              <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" />
              <span class="check-box" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
              </span>
              <span class="check-text">Select all</span>
            </label>
            <span class="selected-count">
              <strong>{{ selectedItems.length }}</strong> of {{ cartItems.length }} selected
            </span>
          </div>

          <section
            v-for="vendor in vendorGroups"
            :key="vendor.id"
            class="vendor-card"
            :aria-label="vendor.name"
          >
            <header class="vendor-head">
              <label class="check">
                <input
                  type="checkbox"
                  :checked="isVendorSelected(vendor.id)"
                  :aria-label="`Select all items from ${vendor.name}`"
                  @change="toggleVendorSelect(vendor.id)"
                />
                <span class="check-box" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                </span>
              </label>
              <span class="vendor-avatar" aria-hidden="true">{{ vendorInitial(vendor.name) }}</span>
              <div class="vendor-meta">
                <span class="vendor-name">{{ vendor.name }}</span>
                <span class="vendor-sub">
                  {{ vendor.items.length }} item{{ vendor.items.length > 1 ? "s" : "" }}
                  · ₱{{ formatPrice(vendor.subtotal) }}
                </span>
              </div>
            </header>

            <TransitionGroup tag="div" name="item" class="vendor-items">
              <article
                v-for="item in vendor.items"
                :key="item.id"
                class="cart-item"
                :class="{
                  'out-of-stock': item.product?.quantity_in_stock === 0,
                  'is-selected': selectedItems.includes(item.id),
                }"
              >
                <label class="check item-check">
                  <input
                    type="checkbox"
                    :value="item.id"
                    v-model="selectedItems"
                    :disabled="item.product?.quantity_in_stock === 0"
                    :aria-label="`Select ${getProductName(item.product, item)}`"
                  />
                  <span class="check-box" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                  </span>
                </label>

                <div class="item-image">
                  <img
                    :src="getProductImage(item.product) || FALLBACK_IMAGE"
                    :alt="getProductName(item.product, item)"
                    loading="lazy"
                    @error="handleImageError"
                  />
                  <span v-if="hasDiscount(item)" class="image-badge">−{{ discountPercent(item) }}%</span>
                </div>

                <div class="item-details">
                  <div class="item-header">
                    <div class="item-heading">
                      <p class="item-category">{{ getProductCategory(item.product, item) }}</p>
                      <h3 class="item-name">{{ getProductName(item.product, item) }}</h3>
                      <p
                        v-if="item.product?.color || item.product?.variety"
                        class="item-tags"
                      >
                        <span v-if="item.product.color" class="item-tag">{{ item.product.color }}</span>
                        <span v-if="item.product.variety" class="item-tag">{{ item.product.variety }}</span>
                      </p>
                      <p v-if="bouquetShortfall(item.customizations)" class="item-short">
                        <span class="item-short-dot" aria-hidden="true"></span>
                        {{ bouquetShortfall(item.customizations).placed }} of {{ bouquetShortfall(item.customizations).size }} flowers ·
                        {{ bouquetShortfall(item.customizations).missing }} {{ bouquetShortfall(item.customizations).missing === 1 ? "spot" : "spots" }} empty
                        <button type="button" class="item-short-fill" @click="editBouquet(item)">Fill it</button>
                      </p>
                    </div>
                    <button
                      class="icon-btn btn-remove"
                      @click="removeFromCart(item.id)"
                      title="Remove item"
                      :aria-label="`Remove ${getProductName(item.product, item)}`"
                    >
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M10.5 11v5M13.5 11v5" />
                      </svg>
                    </button>
                  </div>

                  <div class="item-footer">
                    <div class="quantity-control-wrapper">
                      <div class="quantity-control" :class="{ 'is-locked': isCustomBouquet(item) }">
                        <button
                          class="qty-btn"
                          aria-label="Decrease quantity"
                          @click="decreaseQuantity(item.id)"
                          :disabled="
                            isCustomBouquet(item) ||
                            item.quantity <= 1 ||
                            getStockStatus(item.product) === 'Out of stock'
                          "
                        >
                          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 12h12" /></svg>
                        </button>
                        <input
                          type="text"
                          inputmode="numeric"
                          :value="item.quantity"
                          aria-label="Quantity"
                          @input="handleQuantityInput(item.id, $event)"
                          class="qty-input"
                          :disabled="
                            isCustomBouquet(item) ||
                            getStockStatus(item.product) === 'Out of stock'
                          "
                        />
                        <button
                          class="qty-btn"
                          aria-label="Increase quantity"
                          @click="increaseQuantity(item.id)"
                          :disabled="
                            isCustomBouquet(item) ||
                            getStockStatus(item.product) === 'Out of stock'
                          "
                        >
                          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 12h12M12 6v12" /></svg>
                        </button>
                      </div>
                      <span
                        v-if="getStockStatus(item.product) === 'Out of stock'"
                        class="stock-pill stock-pill--out"
                        >Out of stock</span
                      >
                      <span
                        v-else-if="getStockStatus(item.product) === 'Low stock'"
                        class="stock-pill stock-pill--low"
                        >Only {{ item.product?.quantity_in_stock }} left</span
                      >
                      <span
                        v-if="pendingUpdates[item.id]"
                        class="sync-indicator"
                        aria-live="polite"
                        >Saving…</span
                      >
                    </div>

                    <div class="item-actions">
                      <button
                        v-if="isCustomBouquet(item)"
                        class="item-action"
                        @click="viewBouquet(item)"
                      >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                          <path d="M12 3 4 7.5v9L12 21l8-4.5v-9L12 3Z" />
                          <path d="M4 7.5 12 12l8-4.5M12 12v9" />
                        </svg>
                        View 3D
                      </button>
                      <button
                        v-if="isCustomBouquet(item)"
                        class="item-action"
                        @click="editBouquet(item)"
                        :disabled="getStockStatus(item.product) === 'Out of stock'"
                      >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                          <path d="M4 20h4L19 9l-4-4L4 16v4ZM13.5 6.5l4 4" />
                        </svg>
                        Edit
                      </button>
                      <button
                        v-else
                        class="item-action"
                        @click="customizeItem(item)"
                        :disabled="
                          item.quantity > 1 ||
                          getStockStatus(item.product) === 'Out of stock'
                        "
                        :title="
                          item.quantity > 1
                            ? 'Can only customize one item at a time'
                            : 'Customize this flower'
                        "
                      >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                          <path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1" />
                        </svg>
                        Customize
                      </button>
                    </div>
                  </div>
                </div>

                <div class="item-pricing">
                  <span class="price-current">₱{{ formatPrice(effectivePrice(item)) }}</span>
                  <span v-if="hasDiscount(item)" class="price-original">₱{{ formatPrice(originalPrice(item)) }}</span>
                  <span class="item-subtotal">
                    <span class="item-subtotal-label">Subtotal</span>
                    ₱{{ formatPrice(lineTotal(item)) }}
                  </span>
                  <span
                    v-if="hasDiscount(item) && item.quantity > 1"
                    class="subtotal-saving"
                  >
                    You save ₱{{ formatPrice(lineSaving(item)) }}
                  </span>
                </div>
              </article>
            </TransitionGroup>
          </section>
        </div>

        <!-- Order Summary -->
        <aside class="order-summary" aria-labelledby="summary-title">
          <h2 id="summary-title">Order <em>summary</em></h2>

          <div class="summary-section">
            <div class="summary-row">
              <span>Subtotal ({{ selectedItems.length }} {{ selectedItems.length === 1 ? "item" : "items" }})</span>
              <span>₱{{ formatPrice(selectedOriginalTotal) }}</span>
            </div>
            <div class="summary-row" v-if="totalDiscount > 0">
              <span>Discount</span>
              <span class="discount-amount">−₱{{ formatPrice(totalDiscount) }}</span>
            </div>
            <div class="summary-row">
              <span>Delivery</span>
              <span class="summary-muted">Set by the shop</span>
            </div>
            <p class="delivery-note">
              Each shop books its own third-party courier and bills you the delivery fee separately.
            </p>
          </div>

          <div v-if="discountedSelectedItems.length > 0" class="discount-breakdown">
            <p class="breakdown-title">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 12V4h8l10 10-8 8L3 12Z" />
                <path d="M7.5 7.5h.01" />
              </svg>
              Savings breakdown
            </p>
            <div v-for="entry in discountedSelectedItems" :key="entry.id" class="breakdown-row">
              <span class="breakdown-name">{{ entry.name }}</span>
              <span class="breakdown-saving">−₱{{ formatPrice(entry.saving) }}</span>
            </div>
          </div>

          <div class="summary-total">
            <span>Total</span>
            <span class="total-amount">₱{{ formatPrice(selectedSubtotal) }}</span>
          </div>

          <div class="summary-actions">
            <button
              class="bc-btn bc-btn--dark btn-checkout"
              @click="proceedToCheckout"
              :disabled="selectedItems.length === 0 || hasSelectedOutOfStock"
            >
              Checkout ({{ selectedItems.length }})
              <span class="bc-btn-arrow" aria-hidden="true">→</span>
            </button>
            <button class="bc-link btn-continue-shopping" @click="$router.push('/shop')">
              Continue shopping
            </button>
          </div>

          <ul class="trust-list">
            <li>
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="5" y="10.5" width="14" height="10" rx="2" />
                <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" />
              </svg>
              Secure e-wallet payment with PayMongo
            </li>
            <li>
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="6.5" width="18" height="11" rx="2" />
                <circle cx="12" cy="12" r="2.5" />
              </svg>
              Cash on delivery available
            </li>
          </ul>
        </aside>
      </div>

      <!-- Phones and tablets: total and checkout stay in reach while scrolling -->
      <div v-if="!isLoading && cartItems.length > 0" class="mobile-checkout-bar">
        <div class="mobile-total">
          <span class="mobile-total-label">Total · {{ selectedItems.length }} selected</span>
          <span class="mobile-total-amount">₱{{ formatPrice(selectedSubtotal) }}</span>
        </div>
        <button
          class="bc-btn bc-btn--dark"
          @click="proceedToCheckout"
          :disabled="selectedItems.length === 0 || hasSelectedOutOfStock"
        >
          Checkout
          <span class="bc-btn-arrow" aria-hidden="true">→</span>
        </button>
      </div>
    </main>

    <div
      v-if="show3DModal"
      class="bouquet-modal bc-theme"
      @click.self="close3DModal"
    >
      <div
        class="bouquet-modal-content"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bouquet-modal-title"
      >
        <div class="bouquet-modal-header">
          <h3 id="bouquet-modal-title">Custom <em>bouquet</em></h3>
          <button
            class="icon-btn"
            @click="close3DModal"
            title="Close"
            aria-label="Close"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 6l12 12M18 6 6 18" />
            </svg>
          </button>
        </div>
        <ThreeDModelViewer
          v-if="viewingArrangement"
          model-url="/Boquet.glb"
          model-type="glb"
          :arrangement="viewingArrangement"
          background-color="#e4eae5"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import { usePrice } from "../../composables/usePrice";
import { useCart } from "../../composables/useCart";
import NavHeader from "../../layouts/NavHeader.vue";
import api from "../../plugins/axios"; // ✅ needed to fetch profile
import { showCheckoutPaused, usePlatformStatus } from "../../composables/usePlatformStatus";
import { toast } from "@/utils/snackbar";
import "../../styles/bloomcraft.css";
import ThreeDModelViewer from "../../layouts/3D/3DModelViewer.vue";
import { bouquetShortfall } from "../../utils/customizerUi.js";

const router = useRouter();
const { refresh: refreshPlatform } = usePlatformStatus();
const { isAuthenticated } = useAuth();
const cartStore = useCart();
const {
  effectivePrice,
  originalPrice,
  hasDiscount,
  discountPercent,
  lineTotal,
  lineSaving,
  formatPrice,
} = usePrice();

const cartItems = ref([]);
const selectedItems = ref([]);
const selectAll = ref(false);
const isLoading = ref(true);
const pendingUpdates = ref({});
const updateTimeouts = ref({});
const show3DModal = ref(false);
const viewingArrangement = ref(null);

const showProfileModal = ref(false);
// Selected custom bouquets with empty flower spots, while that question is open: [{ item, short }]
const shortBouquets = ref([]);
const fillRestButton = ref(null);
let shortAccepted = false;

function checkoutShortAnyway() {
  shortBouquets.value = [];
  shortAccepted = true;
  proceedToCheckout();
}
const userProfile = ref({ address: null, contact_number: null, city: null });

// ✅ Which required fields are missing — used in the modal message
const missingProfileFields = computed(() => {
  const missing = [];

  if (!userProfile.value.address) missing.push("Address");
  if (!userProfile.value.contact_number) missing.push("Contact Number");
  if (!userProfile.value.city) missing.push("City");

  if (missing.length === 0) return "";
  if (missing.length === 1) return missing[0];
  if (missing.length === 2) return missing.join(" and ");

  return `${missing.slice(0, -1).join(", ")}, and ${missing.slice(-1)}`;
});

const isProfileIncomplete = computed(
  () =>
    !userProfile.value.address ||
    !userProfile.value.contact_number ||
    !userProfile.value.city,
);

// ✅ Fetch only the fields we need; silently ignore failures so cart still loads
const fetchUserProfile = async () => {
  try {
    const token = localStorage.getItem("auth_token");
    const response = await api.get("profile/user-profile", {
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    });
    if (response.data.success) {
      userProfile.value.address = response.data.user.address || null;
      userProfile.value.contact_number =
        response.data.user.contact_number || null;
      userProfile.value.city = response.data.user.city || null;
    }
  } catch {}
};

const goToProfile = () => {
  showProfileModal.value = false;
  router.push("/customer/profile");
};

const hydrateCartItems = (sourceItems = []) => {
  cartItems.value = sourceItems.map((item) => ({
    id: item.id,
    quantity: item.quantity,
    price: parseFloat(item.price || 0),
    color: item.color,
    size: item.size,
    customizations: item.customizations || null,
    product: item.product
      ? {
          ...item.product,
          selling_price: parseFloat(item.product.selling_price || 0),
          discount_price: item.product.discount_price
            ? parseFloat(item.product.discount_price)
            : null,
          quantity_in_stock: parseInt(item.product.quantity_in_stock || 0),
          owner: item.product.owner || null,
          images: item.product.images || [],
        }
      : null,
  }));
};

// ── Vendor grouping ───────────────────────────────────────────
const vendorGroups = computed(() => {
  const vendors = {};
  cartItems.value.forEach((item) => {
    const vendor = item.product?.owner || null;
    const vendorId = vendor?.id || item.product?.owner_id || "unknown";
    const vendorName =
      vendor?.display_name ||
      vendor?.store_name ||
      vendor?.name ||
      "Local Vendor";

    if (!vendors[vendorId]) {
      vendors[vendorId] = {
        id: vendorId,
        name: vendorName,
        items: [],
        subtotal: 0,
      };
    }
    vendors[vendorId].items.push(item);
    vendors[vendorId].subtotal += lineTotal(item);
  });
  return Object.values(vendors);
});

// ── Totals ────────────────────────────────────────────────────
const selectedOriginalTotal = computed(() =>
  selectedItems.value.reduce((sum, id) => {
    const item = cartItems.value.find((i) => i.id === id);
    return item ? sum + originalPrice(item) * item.quantity : sum;
  }, 0),
);

const selectedSubtotal = computed(() =>
  selectedItems.value.reduce((sum, id) => {
    const item = cartItems.value.find((i) => i.id === id);
    return item ? sum + lineTotal(item) : sum;
  }, 0),
);

const totalDiscount = computed(
  () => selectedOriginalTotal.value - selectedSubtotal.value,
);

const discountedSelectedItems = computed(() =>
  selectedItems.value
    .map((id) => cartItems.value.find((i) => i.id === id))
    .filter((item) => item && hasDiscount(item))
    .map((item) => ({
      id: item.id,
      name: item.product?.product_name || "Item",
      saving: lineSaving(item),
    })),
);

// ── Stock helpers ─────────────────────────────────────────────
const hasLowStockItems = computed(() =>
  cartItems.value.some(
    (i) => i.product?.quantity_in_stock > 0 && i.product.quantity_in_stock <= 5,
  ),
);

const hasOutOfStockItems = computed(() =>
  cartItems.value.some((i) => !i.product || i.product.quantity_in_stock <= 0),
);

const hasSelectedOutOfStock = computed(() =>
  selectedItems.value.some((id) => {
    const item = cartItems.value.find((i) => i.id === id);
    return !item?.product || item.product.quantity_in_stock <= 0;
  }),
);

// ── Select helpers ────────────────────────────────────────────
const toggleSelectAll = () => {
  selectedItems.value = selectAll.value
    ? cartItems.value
        .filter((i) => i.product?.quantity_in_stock > 0)
        .map((i) => i.id)
    : [];
};

const toggleVendorSelect = (vendorId) => {
  const vendor = vendorGroups.value.find((v) => v.id === vendorId);
  if (!vendor) return;
  const ids = vendor.items
    .filter((i) => i.product?.quantity_in_stock > 0)
    .map((i) => i.id);
  const allSelected = ids.every((id) => selectedItems.value.includes(id));
  selectedItems.value = allSelected
    ? selectedItems.value.filter((id) => !ids.includes(id))
    : Array.from(new Set([...selectedItems.value, ...ids]));
};

const isVendorSelected = (vendorId) => {
  const vendor = vendorGroups.value.find((v) => v.id === vendorId);
  if (!vendor) return false;
  const ids = vendor.items
    .filter((i) => i.product?.quantity_in_stock > 0)
    .map((i) => i.id);
  return ids.length > 0 && ids.every((id) => selectedItems.value.includes(id));
};

// ── Load cart ─────────────────────────────────────────────────
onMounted(async () => {
  if (!isAuthenticated.value) {
    router.push("/guest/login");
    return;
  }
  // ✅ Load cart and profile in parallel; profile failure won't block cart
  await Promise.all([loadCart(), fetchUserProfile()]);
});

const loadCart = async (showLoading = true) => {
  try {
    if (showLoading) isLoading.value = true;
    await cartStore.refreshCart();
    hydrateCartItems(cartStore.items.value);
    if (cartItems.value.length > 0) {
      selectedItems.value = cartItems.value
        .filter((i) => i.product?.quantity_in_stock > 0)
        .map((i) => i.id);
      selectAll.value =
        selectedItems.value.length ===
        cartItems.value.filter((i) => i.product?.quantity_in_stock > 0).length;
    } else {
      selectedItems.value = [];
      selectAll.value = false;
    }
  } catch (error) {
    console.error("Error loading cart:", error);
    if (showLoading) toast.error("Failed to load cart");
    cartItems.value = [];
  } finally {
    if (showLoading) isLoading.value = false;
  }
};

watch(
  () => cartStore.items.value,
  (value) => {
    hydrateCartItems(value);
    const validIds = new Set(cartItems.value.map((item) => item.id));
    selectedItems.value = selectedItems.value.filter((id) => validIds.has(id));
    const selectableItems = cartItems.value.filter(
      (item) => item.product?.quantity_in_stock > 0,
    );
    selectAll.value =
      selectableItems.length > 0 &&
      selectableItems.every((item) => selectedItems.value.includes(item.id));
  },
  { deep: true },
);

// ── Quantity controls ─────────────────────────────────────────
const increaseQuantity = (itemId) => {
  const item = cartItems.value.find((i) => i.id === itemId);
  if (!item) return;
  const max = Math.max(1, item.product?.quantity_in_stock || 1);
  if (item.quantity >= max) {
    toast.warning(`Maximum quantity is ${max}`);
    return;
  }
  item.quantity++;
  scheduleQuantityUpdate(itemId, item.quantity);
};

const decreaseQuantity = (itemId) => {
  const item = cartItems.value.find((i) => i.id === itemId);
  if (!item || item.quantity <= 1) return;
  item.quantity--;
  scheduleQuantityUpdate(itemId, item.quantity);
};

const handleQuantityInput = (itemId, event) => {
  const item = cartItems.value.find((i) => i.id === itemId);
  if (!item) return;
  const max = Math.max(1, item.product?.quantity_in_stock || 1);
  let qty = parseInt(event.target.value) || 1;
  if (qty < 1) qty = 1;
  if (qty > max) {
    qty = max;
    toast.warning(`Maximum quantity is ${max}`);
  }
  item.quantity = qty;
  scheduleQuantityUpdate(itemId, qty);
};

const scheduleQuantityUpdate = (itemId, quantity) => {
  clearTimeout(updateTimeouts.value[itemId]);
  pendingUpdates.value[itemId] = true;
  updateTimeouts.value[itemId] = setTimeout(async () => {
    try {
      await cartStore.updateCartItem(itemId, quantity);
      await loadCart(false);
    } catch {
      await loadCart(false);
      toast.error("Failed to update quantity");
    } finally {
      delete pendingUpdates.value[itemId];
    }
  }, 500);
};

// ── Other actions ─────────────────────────────────────────────
const customizeItem = (item) => {
  if (item.quantity > 1) {
    toast.warning("Please set quantity to 1 to customize this item");
    return;
  }
  localStorage.setItem(
    "customize_item",
    JSON.stringify({
      cartItemId: item.id,
      productId: item.product.id,
      productName: item.product.product_name,
      productImage: getProductImage(item.product),
    }),
  );
  router.push(`/customize/${item.product.id}`);
};

const isCustomBouquet = (item) => item?.customizations?.type === "custom_flower_bouquet";

const viewBouquet = (item) => {
  viewingArrangement.value = item.customizations;
  show3DModal.value = true;
};

const close3DModal = () => {
  show3DModal.value = false;
  viewingArrangement.value = null;
};

const editBouquet = (item) => {
  const customization = item.customizations;
  router.push({
    path: "/customize/flower",
    query: {
      cart_item_id: item.id,
      store_id: customization.store_id,
      owner_id: customization.vendor_id,
    },
  });
};

const removeFromCart = async (itemId) => {
  const idx = cartItems.value.findIndex((i) => i.id === itemId);
  if (idx === -1) return;
  const removed = {
    ...cartItems.value[idx],
    index: idx,
    selected: selectedItems.value.includes(itemId),
  };
  cartItems.value.splice(idx, 1);
  selectedItems.value = selectedItems.value.filter((id) => id !== itemId);
  clearTimeout(updateTimeouts.value[itemId]);
  delete updateTimeouts.value[itemId];
  delete pendingUpdates.value[itemId];
  try {
    await cartStore.removeFromCart(itemId);
    await loadCart(false);
    toast.success("Item removed from cart");
  } catch {
    cartItems.value.splice(removed.index, 0, removed);
    if (removed.selected) selectedItems.value.push(itemId);
    toast.error("Failed to remove item");
  }
};

const getStockStatus = (product) => {
  if (!product) return "Unknown";
  const stock = parseInt(product.quantity_in_stock || 0);
  if (stock <= 0) return "Out of stock";
  if (stock <= 5) return "Low stock";
  return "In stock";
};

const getProductName = (p, item = null) => isCustomBouquet(item) ? "Custom Bouquet" : (p?.product_name || "Unknown Product");
const getProductCategory = (p, item = null) => isCustomBouquet(item) ? "Custom flower arrangement" : (p?.category || "Uncategorized");

// First letter of the shop's name, for its badge in the cart
const vendorInitial = (name) => (name || "S").trim().charAt(0).toUpperCase() || "S";

const getProductImage = (product) => {
  if (!product) return null;
  if (product.primary_image?.image_url) return product.primary_image.image_url;
  if (product.images?.length > 0) {
    const primary = product.images.find((img) => img.is_primary);
    return primary?.image_url || product.images[0].image_url;
  }
  return null;
};

// ✅ Modified: profile check runs first; no checkout API is called if incomplete
const proceedToCheckout = async () => {
  if (selectedItems.value.length === 0) {
    toast.warning("Please select items to checkout");
    return;
  }
  if (hasSelectedOutOfStock.value) {
    toast.error("Please remove out of stock items");
    return;
  }

  // A custom bouquet with fewer flowers than chosen asks first
  if (!shortAccepted) {
    const short = cartItems.value
      .filter((item) => selectedItems.value.includes(item.id))
      .map((item) => ({ item, short: bouquetShortfall(item.customizations) }))
      .filter((entry) => entry.short);
    if (short.length) {
      shortBouquets.value = short;
      setTimeout(() => fillRestButton.value?.focus(), 50);
      return;
    }
  }
  shortAccepted = false;

  // An admin may have paused checkout for every store
  const platform = await refreshPlatform();
  if (platform.checkout_paused) {
    showCheckoutPaused(platform.checkout_pause_message);
    return;
  }

  // ✅ Re-fetch profile at click time to catch updates made since page load
  await fetchUserProfile();

  if (isProfileIncomplete.value) {
    showProfileModal.value = true;
    return; // ← checkout flow stops here; nothing is saved to localStorage
  }

  localStorage.setItem(
    "checkout_data",
    JSON.stringify({ cart_item_ids: selectedItems.value }),
  );
  router.push("/customer/checkout");
};

const FALLBACK_IMAGE =
  "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=300&h=375&fit=crop";
const handleImageError = (e) => {
  if (e.target.src !== FALLBACK_IMAGE) e.target.src = FALLBACK_IMAGE;
};
</script>

<style scoped>
/* Tokens, type, labels, buttons, links, skeletons and the navbar come from
   src/styles/bloomcraft.css (shared with the landing page and Shop). */

/* Resets live in a cascade layer so they only replace browser defaults. */
@layer bc-reset {
  .cart-page :is(p, h1, h2, h3, ul, ol) {
    margin: 0;
  }
  .cart-page :is(ul, ol) {
    padding: 0;
    list-style: none;
  }
}

/* Same body reset the landing and shop pages apply (UA 8px margin). */
:global(body) {
  margin: 0;
}

.cart-page {
  /* Page-only status colours, on top of the shared logo tokens */
  --amber-bg: #fdf3e3;
  --amber-ink: #8a5a12;
  --amber-dot: #d68a1c;
  --rose-bg: #fbeceb;
  --rose-ink: #a23b3b;
  --leaf-wash: rgba(77, 177, 115, 0.1);
  --card-shadow: 0 1px 2px rgba(38, 45, 57, 0.04), 0 12px 32px -18px rgba(38, 45, 57, 0.18);
  --card-shadow-hover: 0 1px 2px rgba(38, 45, 57, 0.05), 0 22px 44px -22px rgba(38, 45, 57, 0.28);

  min-height: 100vh;
  background:
    radial-gradient(1200px 520px at 85% -10%, rgba(77, 177, 115, 0.1), transparent 60%),
    var(--ivory);
}

.cart-page button {
  font-family: var(--font-sans);
}

.cart-btn-outline {
  background: transparent;
  color: var(--ink);
  border-color: var(--line);
}

.cart-btn-outline:hover {
  border-color: var(--ink);
}

.cart-container {
  padding-top: 140px;
  padding-bottom: clamp(80px, 9vw, 140px);
}

/* ---- Hero ---- */
.cart-hero {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-end;
  gap: 28px;
  margin-bottom: 40px;
}

.cart-title-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 18px;
  margin-top: 18px;
}

.cart-hero h1 {
  font-size: clamp(44px, 5.4vw, 76px);
}

.cart-count-chip {
  padding: 6px 14px;
  border-radius: 999px;
  background: var(--leaf-wash);
  color: var(--leaf-deep);
  font-size: 13px;
  font-weight: 500;
}

.cart-count-skeleton {
  display: block;
  width: 76px;
  height: 30px;
  border-radius: 999px;
}

.cart-steps {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--ink-soft);
  font-size: 13px;
  font-weight: 500;
}

.cart-steps li {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.cart-steps li + li::before {
  content: "";
  width: 28px;
  height: 1px;
  margin-right: 4px;
  background: var(--line);
}

.step-dot {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: var(--paper);
  font-size: 12px;
}

.cart-steps .is-current {
  color: var(--ink);
}

.cart-steps .is-current .step-dot {
  border-color: var(--ink);
  background: var(--ink);
  color: var(--paper);
}

/* ---- Empty ---- */
.empty-cart {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: clamp(56px, 8vw, 96px) 24px;
  border: 1px solid var(--line-soft);
  border-radius: 28px;
  background: var(--paper);
  box-shadow: var(--card-shadow);
  text-align: center;
}

.empty-icon {
  display: grid;
  place-items: center;
  width: 88px;
  height: 88px;
  margin-bottom: 24px;
  border-radius: 50%;
  background: var(--leaf-wash);
  color: var(--leaf-deep);
}

.empty-icon svg {
  width: 38px;
  height: 38px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.4;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.empty-cart h2 {
  font-size: clamp(34px, 4vw, 52px);
}

.empty-cart p {
  max-width: 380px;
  margin: 14px 0 30px;
  color: var(--ink-soft);
}

/* ---- Layout ---- */
.cart-content {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 380px;
  gap: clamp(24px, 3vw, 40px);
  align-items: start;
}

.cart-items {
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-width: 0;
}

/* ---- Stock alert ---- */
.stock-alert {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 16px;
  background: var(--amber-bg);
  color: var(--amber-ink);
  font-size: 14px;
  font-weight: 400;
}

.stock-alert--error {
  background: var(--rose-bg);
  color: var(--rose-ink);
}

.stock-alert-icon {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

/* ---- Custom checkbox (native input kept for keyboard + screen readers) ---- */
.check {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 12px;
  min-height: 44px;
  cursor: pointer;
}

.check input {
  position: absolute;
  width: 22px;
  height: 22px;
  margin: 0;
  opacity: 0;
  cursor: pointer;
}

.check-box {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  border: 1.5px solid rgba(54, 63, 78, 0.3);
  border-radius: 7px;
  background: var(--paper);
  color: transparent;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.check-box svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.check:hover .check-box {
  border-color: var(--leaf-deep);
}

.check input:checked + .check-box {
  border-color: var(--leaf-deep);
  background: var(--leaf-deep);
  color: #fff;
}

.check input:focus-visible + .check-box {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

.check input:disabled,
.check input:disabled + .check-box {
  cursor: not-allowed;
}

.check input:disabled + .check-box {
  border-color: var(--line);
  background: var(--ivory-deep);
}

.check-text {
  font-size: 14px;
  font-weight: 500;
}

/* ---- Toolbar ---- */
.cart-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 4px 20px;
  border: 1px solid var(--line-soft);
  border-radius: 18px;
  background: var(--paper);
  box-shadow: var(--card-shadow);
}

.selected-count {
  color: var(--ink-soft);
  font-size: 13px;
}

.selected-count strong {
  color: var(--ink);
  font-weight: 600;
}

/* ---- Vendor card ---- */
.vendor-card {
  overflow: hidden;
  border: 1px solid var(--line-soft);
  border-radius: 24px;
  background: var(--paper);
  box-shadow: var(--card-shadow);
}

.vendor-head {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 10px 20px;
  border-bottom: 1px solid var(--line-soft);
  background: linear-gradient(180deg, rgba(228, 234, 229, 0.55), rgba(228, 234, 229, 0));
}

.vendor-avatar {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--ink);
  color: var(--paper);
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 500;
  line-height: 1;
}

.vendor-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.3;
}

.vendor-name {
  overflow: hidden;
  font-size: 15px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.vendor-sub {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.vendor-items {
  position: relative;
}

/* ---- Item row ---- */
.cart-item {
  position: relative;
  display: grid;
  grid-template-columns: 22px 116px minmax(0, 1fr) auto;
  gap: 20px;
  align-items: start;
  padding: 22px 20px;
  transition: background 0.25s ease;
}

.cart-item + .cart-item {
  border-top: 1px solid var(--line-soft);
}

.cart-item::before {
  content: "";
  position: absolute;
  left: 0;
  top: 22px;
  bottom: 22px;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: var(--leaf);
  opacity: 0;
  transition: opacity 0.25s ease;
}

.cart-item.is-selected::before {
  opacity: 1;
}

.cart-item:hover {
  background: rgba(242, 245, 242, 0.6);
}

.cart-item.out-of-stock .item-image,
.cart-item.out-of-stock .item-heading,
.cart-item.out-of-stock .item-pricing {
  opacity: 0.55;
}

.item-check {
  min-height: 0;
  margin-top: 2px;
}

.item-image {
  position: relative;
  width: 116px;
  aspect-ratio: 4 / 5;
  overflow: hidden;
  border-radius: 16px;
  background: var(--ivory-deep);
}

.item-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s var(--ease-out);
}

.cart-item:hover .item-image img {
  transform: scale(1.05);
}

.image-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  padding: 3px 8px;
  border-radius: 999px;
  background: var(--leaf-deep);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
}

.item-details {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 16px;
  align-self: stretch;
  min-width: 0;
}

.item-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.item-heading {
  min-width: 0;
}

.item-category {
  color: var(--leaf-deep);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.cart-page .item-name {
  margin-top: 4px;
  font-size: 26px;
  line-height: 1.1;
}

.item-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 10px;
}

.item-tag {
  padding: 3px 10px;
  border: 1px solid var(--line);
  border-radius: 999px;
  color: var(--ink-soft);
  font-size: 12px;
  text-transform: capitalize;
}

.item-short {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  width: fit-content;
  margin-top: 10px;
  padding: 6px 6px 6px 12px;
  border-radius: 999px;
  background: var(--amber-bg);
  color: var(--amber-ink);
  font-size: 13px;
  font-weight: 500;
}

.item-short-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--amber-dot);
}

.item-short-fill {
  min-height: 28px;
  padding: 0 12px;
  border: 0;
  border-radius: 999px;
  background: var(--ink);
  color: var(--paper);
  font: 600 12px var(--font-sans);
  cursor: pointer;
  transition: background 0.2s ease;
}

.item-short-fill:hover {
  background: var(--leaf-deep);
}

.item-short-fill:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

.short-actions {
  display: grid;
  gap: 8px;
  margin-top: 8px;
}

.short-actions .bc-btn {
  justify-content: center;
  width: 100%;
}

.short-anyway {
  justify-self: center;
  min-height: 44px;
  padding: 10px 12px;
  border: 0;
  border-radius: 8px;
  background: none;
  color: var(--ink-soft);
  font-family: var(--font-sans);
  font-size: 13.5px;
  font-weight: 500;
  text-decoration: underline;
  text-underline-offset: 4px;
  text-decoration-color: rgba(54, 63, 78, 0.35);
  cursor: pointer;
  transition: color 0.2s ease, text-decoration-color 0.2s ease;
}

.short-anyway::after {
  display: none;
}

.short-anyway:hover {
  color: var(--ink);
  text-decoration-color: currentColor;
}

/* Icon buttons (remove, close) */
.icon-btn {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border: 1px solid transparent;
  border-radius: 12px;
  background: none;
  color: var(--ink-soft);
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.icon-btn svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.icon-btn:hover {
  border-color: var(--line);
  background: var(--paper);
  color: var(--ink);
}

.btn-remove:hover {
  border-color: rgba(162, 59, 59, 0.2);
  background: var(--rose-bg);
  color: var(--rose-ink);
}

.item-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.quantity-control-wrapper {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.quantity-control {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 3px;
  border-radius: 999px;
  background: var(--ivory);
  box-shadow: inset 0 0 0 1px var(--line-soft);
}

.qty-btn {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 50%;
  background: var(--paper);
  color: var(--ink);
  box-shadow: 0 1px 2px rgba(38, 45, 57, 0.1);
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease, transform 0.15s ease;
}

.qty-btn svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
}

.qty-btn:hover:not(:disabled) {
  background: var(--ink);
  color: var(--paper);
}

.qty-btn:active:not(:disabled) {
  transform: scale(0.92);
}

.qty-btn:disabled {
  background: transparent;
  box-shadow: none;
  color: rgba(54, 63, 78, 0.3);
  cursor: not-allowed;
}

.qty-input {
  width: 38px;
  border: none;
  background: none;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 15px;
  font-weight: 600;
  text-align: center;
}

.qty-input:focus {
  outline: none;
}

.quantity-control:focus-within:not(.is-locked) {
  box-shadow: inset 0 0 0 1.5px var(--leaf);
}

.qty-input:disabled {
  color: var(--ink-soft);
}

.stock-pill {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 500;
}

.stock-pill--out {
  background: var(--rose-bg);
  color: var(--rose-ink);
}

.stock-pill--low {
  background: var(--amber-bg);
  color: var(--amber-ink);
}

.sync-indicator {
  color: var(--ink-soft);
  font-size: 12px;
}

.item-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.item-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 36px;
  padding: 0 14px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--paper);
  color: var(--ink);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.2s ease, color 0.2s ease, background 0.2s ease;
}

.item-action svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.item-action:hover:not(:disabled) {
  border-color: var(--leaf-deep);
  background: var(--leaf-wash);
  color: var(--leaf-deep);
}

.item-action:disabled {
  color: var(--ink-soft);
  opacity: 0.5;
  cursor: not-allowed;
}

.item-pricing {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  /* Fixed width keeps every row's actions aligned */
  width: 140px;
  text-align: right;
}

.price-current {
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 500;
  line-height: 1.1;
}

.price-original {
  color: var(--ink-soft);
  font-size: 13px;
  text-decoration: line-through;
}

.item-subtotal {
  margin-top: 10px;
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 500;
}

.item-subtotal-label {
  display: block;
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.subtotal-saving {
  color: var(--leaf-deep);
  font-size: 12px;
  font-weight: 500;
}

/* Item leaves the list */
.item-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.item-leave-to {
  opacity: 0;
  transform: translateX(16px);
}

.item-move {
  transition: transform 0.3s var(--ease-out);
}

/* ---- Summary ---- */
.order-summary {
  position: sticky;
  top: 110px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 30px 28px;
  border: 1px solid var(--line-soft);
  border-radius: 26px;
  background: var(--paper);
  box-shadow: var(--card-shadow);
}

.order-summary h2 {
  font-size: 34px;
}

.summary-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 18px;
  border-top: 1px solid var(--line);
}

.summary-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  color: var(--ink-soft);
  font-size: 14px;
}

.summary-row span:last-child {
  color: var(--ink);
  font-weight: 500;
}

.delivery-note {
  margin-top: -4px;
  color: var(--ink-soft);
  font-size: 12.5px;
  line-height: 1.55;
}

.summary-row .summary-muted {
  color: var(--ink-soft);
  font-weight: 400;
}

.summary-row .discount-amount,
.breakdown-saving {
  color: var(--leaf-deep);
}

.discount-breakdown {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px 16px;
  border-radius: 14px;
  background: var(--leaf-wash);
}

.breakdown-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--leaf-deep);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.breakdown-title svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 13px;
}

.breakdown-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.breakdown-saving {
  font-weight: 500;
}

.summary-total {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding: 18px 0 2px;
  border-top: 1px dashed rgba(54, 63, 78, 0.2);
  font-size: 14px;
  font-weight: 500;
}

.total-amount {
  font-family: var(--font-display);
  font-size: 42px;
  font-weight: 500;
  line-height: 1;
}

.summary-actions {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

.btn-checkout {
  justify-content: center;
  width: 100%;
  box-shadow: 0 14px 28px -14px rgba(38, 45, 57, 0.55);
}

.cart-page .bc-btn:disabled {
  opacity: 0.45;
  box-shadow: none;
  cursor: not-allowed;
}

.cart-page .bc-btn--dark:disabled:hover {
  background: var(--ink);
}

.btn-continue-shopping {
  border: none;
  background: none;
  cursor: pointer;
}

.trust-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 16px;
  border-top: 1px solid var(--line-soft);
  color: var(--ink-soft);
  font-size: 12.5px;
}

.trust-list li {
  display: flex;
  align-items: center;
  gap: 10px;
}

.trust-list svg {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  fill: none;
  stroke: var(--leaf-deep);
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.qty-btn:focus-visible,
.icon-btn:focus-visible,
.item-action:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

/* ---- Mobile checkout bar (hidden on desktop, where the summary is sticky) ---- */
.mobile-checkout-bar {
  display: none;
}

/* ---- Skeleton ---- */
.skeleton-toolbar {
  display: block;
  height: 54px;
  border-radius: 18px;
}

.skeleton-avatar {
  width: 40px;
  height: 40px;
  border-radius: 12px;
}

.cart-item--skeleton {
  align-items: center;
}

.cart-item--skeleton:hover {
  background: none;
}

.skeleton-check {
  width: 22px;
  height: 22px;
  border-radius: 7px;
}

.skeleton-image {
  width: 116px;
  aspect-ratio: 4 / 5;
  border-radius: 16px;
}

.skeleton-lines {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skeleton-line {
  display: block;
  height: 13px;
}

.skeleton-line--vendor {
  width: 160px;
  height: 16px;
}

.skeleton-line--title {
  width: 70%;
  height: 22px;
}

.skeleton-line--short {
  width: 40%;
}

.skeleton-line--qty {
  width: 124px;
  height: 42px;
  border-radius: 999px;
}

.skeleton-price {
  width: 80px;
  height: 24px;
}

.skeleton-total {
  height: 34px;
  margin-top: 8px;
}

.skeleton-button {
  display: block;
  height: 54px;
  border-radius: 999px;
}

/* ---- Modals ---- */
.modal-backdrop,
.bouquet-modal {
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

.modal-card {
  width: 100%;
  max-width: 460px;
  padding: 40px 34px 32px;
  border-radius: 26px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(38, 45, 57, 0.25);
}

.modal-title {
  margin: 14px 0 12px;
  font-size: 36px;
}

.modal-message {
  margin-bottom: 26px;
  color: var(--ink-soft);
  font-size: 14.5px;
  line-height: 1.7;
}

.modal-message strong {
  color: var(--ink);
  font-weight: 500;
}

.modal-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.modal-actions .bc-btn {
  justify-content: center;
  padding-inline: 18px;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.bouquet-modal-content {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 880px;
  height: min(80vh, 680px);
  overflow: hidden;
  border-radius: 24px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(38, 45, 57, 0.25);
}

.bouquet-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--line-soft);
}

.bouquet-modal-header h3 {
  font-size: 30px;
}

.bouquet-modal-content > :last-child {
  flex: 1;
  min-height: 0;
}

/* ---- Responsive ---- */
@media (max-width: 1024px) {
  .cart-content {
    grid-template-columns: 1fr;
  }

  .order-summary {
    position: static;
  }

  .cart-container.has-mobile-bar {
    padding-bottom: 140px;
  }

  .mobile-checkout-bar {
    position: fixed;
    left: 12px;
    right: 12px;
    bottom: calc(12px + env(safe-area-inset-bottom, 0px));
    z-index: 900;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 10px 10px 10px 22px;
    border: 1px solid var(--line-soft);
    border-radius: 22px;
    background: rgba(250, 251, 250, 0.92);
    box-shadow: 0 18px 40px -16px rgba(38, 45, 57, 0.35);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
  }

  .mobile-total {
    display: flex;
    flex-direction: column;
    min-width: 0;
    line-height: 1.2;
  }

  .mobile-total-label {
    color: var(--ink-soft);
    font-size: 12px;
  }

  .mobile-total-amount {
    font-family: var(--font-display);
    font-size: 26px;
    font-weight: 500;
  }

  .mobile-checkout-bar .bc-btn {
    flex-shrink: 0;
    padding: 14px 24px;
  }
}

@media (max-width: 640px) {
  .cart-container {
    padding-top: 112px;
  }

  .cart-hero {
    margin-bottom: 28px;
  }

  .cart-steps li + li::before {
    width: 14px;
  }

  .cart-toolbar,
  .vendor-head {
    padding-inline: 14px;
  }

  .cart-item {
    grid-template-columns: 22px 84px minmax(0, 1fr);
    gap: 12px 14px;
    padding: 18px 14px;
  }

  .item-image,
  .skeleton-image {
    width: 84px;
    border-radius: 12px;
  }

  .image-badge {
    top: 6px;
    left: 6px;
    font-size: 10px;
  }

  .item-pricing {
    grid-column: 2 / -1;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 4px 10px;
    width: auto;
    text-align: left;
  }

  .price-current {
    font-size: 24px;
  }

  .item-subtotal {
    margin-top: 0;
    margin-left: auto;
  }

  .item-subtotal-label {
    display: inline;
    margin-right: 6px;
  }

  .subtotal-saving {
    width: 100%;
    text-align: right;
  }

  .cart-page .item-name {
    font-size: 22px;
  }

  .item-footer {
    align-items: flex-start;
  }

  .order-summary {
    padding: 24px 20px;
  }

  .modal-actions {
    grid-template-columns: 1fr;
  }

  .skeleton-price {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .modal-fade-enter-active,
  .modal-fade-leave-active,
  .item-leave-active,
  .item-move {
    transition: none;
  }

  .item-image img,
  .cart-item:hover .item-image img {
    transition: none;
    transform: none;
  }
}
</style>
