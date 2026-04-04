<!-- frontend/src/views/authenticated/Cart.vue -->
<template>
  <div class="cart-page">
    <NavHeader :cartCount="cartStore.count.value" />

    <!-- ✅ Profile Incomplete Modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="showProfileModal"
          class="modal-backdrop"
          @click.self="showProfileModal = false"
        >
          <div
            class="modal-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-title"
          >
            <div class="modal-icon">
              <svg
                width="48"
                height="48"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
              >
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
            </div>
            <h2 id="modal-title" class="modal-title">Complete Your Profile</h2>
            <p class="modal-message">
              Before you can check out, please add your
              <strong>{{ missingProfileFields }}</strong> to your profile so we
              know where to deliver your order.
            </p>
            <div class="modal-actions">
              <button
                class="modal-btn-secondary"
                @click="showProfileModal = false"
              >
                Back to Cart
              </button>
              <button class="modal-btn-primary" @click="goToProfile">
                Complete Profile
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
    <!-- ✅ End Modal -->

    <div class="cart-container">
      <div class="cart-header">
        <h1>Shopping Cart</h1>
        <p class="cart-count">
          {{ cartItems.length }} {{ cartItems.length === 1 ? "item" : "items" }}
        </p>
      </div>

      <div v-if="cartItems.length === 0 && !isLoading" class="empty-cart">
        <div class="empty-icon">🛒</div>
        <h2>Your cart is empty</h2>
        <p>Looks like you haven't added anything to your cart yet</p>
        <button class="btn-shop" @click="$router.push('/shop')">
          Continue Shopping
        </button>
      </div>

      <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />

      <div v-if="cartItems.length > 0" class="cart-content">
        <div class="cart-items">
          <div
            v-if="hasLowStockItems || hasOutOfStockItems"
            class="stock-alert-banner"
          >
            <div class="alert-icon">⚠️</div>
            <div class="alert-content">
              <p v-if="hasOutOfStockItems">
                Some items in your cart are out of stock. Please remove them to
                continue.
              </p>
              <p v-else-if="hasLowStockItems">
                Some items in your cart have low stock. Order soon!
              </p>
            </div>
          </div>

          <div class="select-all-section">
            <label class="checkbox-container">
              <input
                type="checkbox"
                v-model="selectAll"
                @change="toggleSelectAll"
              />
              <span class="checkmark"></span>
              <span class="checkbox-label">Select All</span>
            </label>
            <span class="selected-count"
              >{{ selectedItems.length }} of
              {{ cartItems.length }} selected</span
            >
          </div>

          <div
            v-for="vendor in vendorGroups"
            :key="vendor.id"
            class="vendor-group"
          >
            <div class="vendor-header">
              <label class="checkbox-container">
                <input
                  type="checkbox"
                  :checked="isVendorSelected(vendor.id)"
                  @change="toggleVendorSelect(vendor.id)"
                />
                <span class="checkmark"></span>
              </label>
              <div class="vendor-info">
                <span class="vendor-icon">🏪</span>
                <span class="vendor-name">{{ vendor.name }}</span>
              </div>
              <span class="vendor-item-count"
                >{{ vendor.items.length }} item{{
                  vendor.items.length > 1 ? "s" : ""
                }}</span
              >
            </div>

            <div class="vendor-items">
              <div
                v-for="item in vendor.items"
                :key="item.id"
                class="cart-item"
                :class="{
                  'out-of-stock': item.product?.quantity_in_stock === 0,
                }"
              >
                <label class="checkbox-container item-checkbox">
                  <input
                    type="checkbox"
                    :value="item.id"
                    v-model="selectedItems"
                    :disabled="item.product?.quantity_in_stock === 0"
                  />
                  <span class="checkmark"></span>
                </label>

                <div class="item-image">
                  <img
                    :src="
                      getProductImage(item.product) ||
                      'https://via.placeholder.com/150'
                    "
                    :alt="getProductName(item.product)"
                    @error="handleImageError"
                  />
                </div>

                <div class="item-details">
                  <div class="item-header">
                    <div>
                      <h3 class="item-name">
                        {{ getProductName(item.product) }}
                      </h3>
                      <p class="item-category">
                        {{ getProductCategory(item.product) }}
                      </p>
                    </div>
                    <button
                      class="btn-remove"
                      @click="removeFromCart(item.id)"
                      title="Remove item"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                      >
                        <path
                          fill="currentColor"
                          d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6zM8 9h8v10H8zm7.5-5l-1-1h-5l-1 1H5v2h14V4z"
                        />
                      </svg>
                    </button>
                  </div>

                  <div class="item-specifications" v-if="item.product">
                    <div class="spec-item" v-if="item.product.color">
                      <span class="spec-icon">🎨</span>
                      <span class="spec-value">{{ item.product.color }}</span>
                    </div>
                    <div class="spec-item" v-if="item.product.variety">
                      <span class="spec-icon">🌱</span>
                      <span class="spec-value">{{ item.product.variety }}</span>
                    </div>
                  </div>

                  <div class="item-footer">
                    <div class="quantity-control-wrapper">
                      <div
                        class="stock-warning"
                        v-if="getStockStatus(item.product) === 'Out of stock'"
                      >
                        <span class="warning-icon">⛔</span>
                        <span class="warning-text">Out of stock</span>
                      </div>
                      <div
                        class="stock-warning low-stock"
                        v-else-if="getStockStatus(item.product) === 'Low stock'"
                      >
                        <span class="warning-icon">⚠️</span>
                        <span class="warning-text"
                          >Only {{ item.product?.quantity_in_stock }} left</span
                        >
                      </div>

                      <div class="quantity-control">
                        <button
                          class="qty-btn"
                          @click="decreaseQuantity(item.id)"
                          :disabled="
                            getStockStatus(item.product) === 'Out of stock'
                          "
                        >
                          <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                          >
                            <path fill="currentColor" d="M19 13H5v-2h14z" />
                          </svg>
                        </button>
                        <input
                          type="text"
                          :value="item.quantity"
                          @input="handleQuantityInput(item.id, $event)"
                          class="qty-input"
                          :disabled="
                            getStockStatus(item.product) === 'Out of stock'
                          "
                        />
                        <button
                          class="qty-btn"
                          @click="increaseQuantity(item.id)"
                          :disabled="
                            getStockStatus(item.product) === 'Out of stock'
                          "
                        >
                          <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                          >
                            <path
                              fill="currentColor"
                              d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z"
                            />
                          </svg>
                        </button>
                      </div>
                      <div
                        v-if="pendingUpdates[item.id]"
                        class="sync-indicator"
                      >
                        <span class="sync-dot"></span>
                        <span class="sync-text">Syncing...</span>
                      </div>
                    </div>

                    <div class="item-actions">
                      <button
                        class="btn-customize"
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
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          width="18"
                          height="18"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        >
                          <path d="M12 20h9" />
                          <path
                            d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"
                          />
                        </svg>
                        <span>Customize</span>
                      </button>

                      <div class="item-pricing">
                        <!-- With discount -->
                        <div v-if="hasDiscount(item)" class="price-discount">
                          <span class="price-original"
                            >₱{{ formatPrice(originalPrice(item)) }}</span
                          >
                          <span class="price-current"
                            >₱{{ formatPrice(effectivePrice(item)) }}</span
                          >
                          <span class="price-badge"
                            >{{ discountPercent(item) }}% OFF</span
                          >
                        </div>
                        <!-- No discount -->
                        <div v-else class="price-regular">
                          <span class="price-current"
                            >₱{{ formatPrice(effectivePrice(item)) }}</span
                          >
                        </div>

                        <div class="item-subtotal">
                          Subtotal: ₱{{ formatPrice(lineTotal(item)) }}
                          <span
                            v-if="hasDiscount(item) && item.quantity > 1"
                            class="subtotal-saving"
                          >
                            (save ₱{{ formatPrice(lineSaving(item)) }})
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Order Summary -->
        <div class="order-summary">
          <h2>Order Summary</h2>

          <div class="summary-section">
            <div class="summary-row">
              <span>Subtotal ({{ selectedItems.length }} items)</span>
              <span>₱{{ formatPrice(selectedOriginalTotal) }}</span>
            </div>
            <div class="summary-row discount-row" v-if="totalDiscount > 0">
              <span>Discount</span>
              <span class="discount-amount"
                >-₱{{ formatPrice(totalDiscount) }}</span
              >
            </div>
          </div>

          <!-- Per-item discount breakdown -->
          <div
            v-if="discountedSelectedItems.length > 0"
            class="discount-breakdown"
          >
            <p class="breakdown-title">Savings breakdown</p>
            <div
              v-for="entry in discountedSelectedItems"
              :key="entry.id"
              class="breakdown-row"
            >
              <span class="breakdown-name">{{ entry.name }}</span>
              <span class="breakdown-saving"
                >-₱{{ formatPrice(entry.saving) }}</span
              >
            </div>
          </div>

          <div class="summary-total">
            <span>Total</span>
            <span class="total-amount"
              >₱{{ formatPrice(selectedSubtotal) }}</span
            >
          </div>

          <div class="promo-section">
            <input
              type="text"
              v-model="promoCode"
              placeholder="Enter promo code"
              class="promo-input"
            />
            <button class="btn-apply-promo" @click="applyPromoCode">
              Apply
            </button>
          </div>

          <div class="summary-actions">
            <button
              class="btn-continue-shopping"
              @click="$router.push('/shop')"
            >
              Continue Shopping
            </button>
            <button
              class="btn-checkout"
              @click="proceedToCheckout"
              :disabled="selectedItems.length === 0 || hasSelectedOutOfStock"
            >
              Checkout ({{ selectedItems.length }})
            </button>
          </div>

          <div class="payment-methods">
            <p>We accept:</p>
            <div class="payment-icons">
              <span class="payment-icon">💳</span>
              <span class="payment-icon">🏦</span>
              <span class="payment-icon">📱</span>
            </div>
          </div>
        </div>
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
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";

const router = useRouter();
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
const promoCode = ref("");
const isLoading = ref(true);
const isLoadingMessage = ref("");
const pendingUpdates = ref({});
const updateTimeouts = ref({});

const showProfileModal = ref(false);
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
    if (showLoading) {
      isLoading.value = true;
      isLoadingMessage.value = "Preparing your cart...";
    }
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

const getProductName = (p) => p?.product_name || "Unknown Product";
const getProductCategory = (p) => p?.category || "Uncategorized";

const getProductImage = (product) => {
  if (!product) return null;
  if (product.primary_image?.image_url) return product.primary_image.image_url;
  if (product.images?.length > 0) {
    const primary = product.images.find((img) => img.is_primary);
    return primary?.image_url || product.images[0].image_url;
  }
  return null;
};

const applyPromoCode = () => {
  if (promoCode.value.trim()) {
    toast.info(`Promo code "${promoCode.value}" applied!`);
    promoCode.value = "";
  }
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

const handleImageError = (e) => {
  e.target.src = "https://via.placeholder.com/150";
};
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");

* {
  font-family: "Poppins", sans-serif;
  box-sizing: border-box;
}

.cart-page {
  min-height: 100vh;
  background: #f5f7fa;
  padding-top: 80px;
}
.cart-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
}
.cart-header {
  margin-bottom: 32px;
}
.cart-header h1 {
  font-size: 32px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 8px 0;
}
.cart-count {
  font-size: 16px;
  color: #718096;
  margin: 0;
}

.empty-cart {
  background: white;
  border-radius: 16px;
  padding: 80px 40px;
  text-align: center;
}
.empty-icon {
  font-size: 80px;
  margin-bottom: 24px;
  opacity: 0.5;
}
.empty-cart h2 {
  font-size: 28px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 12px 0;
}
.empty-cart p {
  font-size: 16px;
  color: #718096;
  margin: 0 0 32px 0;
}
.btn-shop {
  padding: 14px 32px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}
.btn-shop:hover {
  background: #38a169;
  transform: translateY(-2px);
}

.cart-content {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 32px;
  align-items: start;
}
.cart-items {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.stock-alert-banner {
  background: #fef3c7;
  border: 1px solid #fbbf24;
  border-radius: 12px;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.alert-icon {
  font-size: 24px;
}
.alert-content p {
  margin: 0;
  color: #92400e;
  font-size: 14px;
  font-weight: 500;
}

.select-all-section {
  background: white;
  border-radius: 12px;
  padding: 16px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}
.selected-count {
  font-size: 14px;
  color: #718096;
  font-weight: 500;
}

.checkbox-container {
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
  cursor: pointer;
  user-select: none;
}
.checkbox-container input {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  height: 0;
  width: 0;
}
.checkmark {
  height: 20px;
  width: 20px;
  background-color: white;
  border: 2px solid #cbd5e0;
  border-radius: 4px;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.checkbox-container:hover .checkmark {
  border-color: #48bb78;
}
.checkbox-container input:checked ~ .checkmark {
  background-color: #48bb78;
  border-color: #48bb78;
}
.checkmark:after {
  content: "";
  display: none;
  width: 5px;
  height: 10px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}
.checkbox-container input:checked ~ .checkmark:after {
  display: block;
}
.checkbox-container input:disabled ~ .checkmark {
  background-color: #f7fafc;
  border-color: #e2e8f0;
  cursor: not-allowed;
}
.checkbox-label {
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
}

.vendor-group {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}
.vendor-header {
  background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid #e2e8f0;
}
.vendor-info {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
}
.vendor-icon {
  font-size: 20px;
}
.vendor-name {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
}
.vendor-item-count {
  font-size: 13px;
  color: #718096;
  background: white;
  padding: 4px 12px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.vendor-items {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cart-item {
  padding: 16px;
  display: grid;
  grid-template-columns: auto 120px 1fr;
  gap: 16px;
  border-radius: 8px;
  transition: all 0.3s;
  background: #fafafa;
}
.cart-item:hover {
  background: #f7fafc;
}
.cart-item.out-of-stock {
  opacity: 0.6;
}

.item-checkbox {
  display: flex;
  align-items: center;
  justify-content: center;
  padding-top: 40px;
}
.item-image {
  width: 120px;
  height: 120px;
  border-radius: 8px;
  overflow: hidden;
  background: white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
.item-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.item-details {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.item-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.item-name {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 4px 0;
}
.item-category {
  font-size: 12px;
  color: #48bb78;
  font-weight: 600;
  text-transform: uppercase;
  margin: 0;
}

.btn-remove {
  width: 36px;
  height: 36px;
  border: none;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-remove:hover {
  background: #fecaca;
  transform: scale(1.05);
}

.item-specifications {
  display: flex;
  gap: 16px;
  padding: 8px 0;
}
.spec-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
}
.spec-icon {
  font-size: 14px;
}
.spec-value {
  color: #4a5568;
  font-weight: 500;
}

.item-footer {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.quantity-control-wrapper {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.stock-warning {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  background: #fee2e2;
  border-radius: 6px;
  font-size: 12px;
  color: #991b1b;
  font-weight: 500;
  width: fit-content;
}
.stock-warning.low-stock {
  background: #fef3c7;
  color: #92400e;
}
.warning-icon {
  font-size: 14px;
}

.sync-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: #718096;
}
.sync-dot {
  width: 6px;
  height: 6px;
  background-color: #48bb78;
  border-radius: 50%;
  animation: pulse 1.5s infinite;
}
.sync-text {
  font-size: 11px;
  font-weight: 500;
}

@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.quantity-control {
  display: flex;
  align-items: center;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  width: fit-content;
  background: white;
}
.qty-btn {
  width: 40px;
  height: 40px;
  background: white;
  border: none;
  color: #2d3748;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.qty-btn:hover:not(:disabled) {
  background: #f7fafc;
}
.qty-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.qty-input {
  width: 60px;
  height: 40px;
  border: none;
  border-left: 1px solid #e2e8f0;
  border-right: 1px solid #e2e8f0;
  text-align: center;
  font-size: 15px;
  font-weight: 600;
  color: #2d3748;
  background: white;
}
.qty-input:focus {
  outline: none;
  background: #f7fafc;
}
.qty-input:disabled {
  background: #f7fafc;
  color: #a0aec0;
}

.item-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.btn-customize {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}
.btn-customize:hover:not(:disabled) {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
}
.btn-customize:disabled {
  background: #cbd5e0;
  color: #718096;
  cursor: not-allowed;
  transform: none;
}

.item-pricing {
  text-align: right;
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: flex-end;
}
.price-discount {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
  justify-content: flex-end;
}
.price-original {
  font-size: 13px;
  color: #a0aec0;
  text-decoration: line-through;
}
.price-current {
  font-size: 18px;
  font-weight: 700;
  color: #48bb78;
}
.price-badge {
  padding: 2px 8px;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}
.price-regular .price-current {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
}
.item-subtotal {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
}
.subtotal-saving {
  color: #38a169;
  font-weight: 600;
  margin-left: 4px;
}

/* Order Summary */
.order-summary {
  background: white;
  border-radius: 12px;
  padding: 24px;
  position: sticky;
  top: 100px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}
.order-summary h2 {
  font-size: 20px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 20px 0;
}

.summary-section {
  padding: 16px 0;
  border-bottom: 1px solid #e2e8f0;
}
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  font-size: 14px;
  color: #718096;
}
.summary-row:last-child {
  margin-bottom: 0;
}
.discount-row {
  color: #e53e3e;
}
.discount-amount {
  font-weight: 600;
}

.discount-breakdown {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  padding: 12px 14px;
  margin: 12px 0;
}
.breakdown-title {
  font-size: 12px;
  font-weight: 600;
  color: #166534;
  margin: 0 0 8px 0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.breakdown-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  color: #4b5563;
  padding: 3px 0;
}
.breakdown-name {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-right: 12px;
}
.breakdown-saving {
  color: #16a34a;
  font-weight: 600;
  white-space: nowrap;
}

.summary-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 0;
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
}
.total-amount {
  font-size: 24px;
  color: #48bb78;
}

.promo-section {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
}
.promo-input {
  flex: 1;
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
}
.promo-input:focus {
  outline: none;
  border-color: #48bb78;
}
.btn-apply-promo {
  padding: 10px 20px;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}
.btn-apply-promo:hover {
  background: #1a202c;
}

.summary-actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}
.btn-continue-shopping {
  padding: 12px 24px;
  background: white;
  color: #2d3748;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}
.btn-continue-shopping:hover {
  background: #f7fafc;
  border-color: #48bb78;
}
.btn-checkout {
  padding: 14px 24px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}
.btn-checkout:hover:not(:disabled) {
  background: #38a169;
  transform: translateY(-2px);
}
.btn-checkout:disabled {
  background: #cbd5e0;
  cursor: not-allowed;
  transform: none;
}

.payment-methods {
  text-align: center;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
}
.payment-methods p {
  font-size: 12px;
  color: #718096;
  margin: 0 0 8px 0;
}
.payment-icons {
  display: flex;
  justify-content: center;
  gap: 12px;
  font-size: 24px;
}

/* ✅ Profile Incomplete Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-card {
  background: white;
  border-radius: 20px;
  padding: 40px 36px;
  max-width: 440px;
  width: 100%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
  text-align: center;
}

.modal-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(
    135deg,
    rgba(72, 187, 120, 0.12) 0%,
    rgba(45, 55, 72, 0.08) 100%
  );
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
  color: #2d3748;
}

.modal-title {
  font-size: 22px;
  font-weight: 700;
  color: #2d3748;
  margin: 0 0 12px;
}

.modal-message {
  font-size: 15px;
  color: #718096;
  line-height: 1.6;
  margin: 0 0 28px;
}

.modal-message strong {
  color: #2d3748;
  font-weight: 600;
}

.modal-actions {
  display: flex;
  gap: 12px;
}

.modal-btn-secondary {
  flex: 1;
  padding: 12px 20px;
  background: white;
  color: #2d3748;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}
.modal-btn-secondary:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.modal-btn-primary {
  flex: 1;
  padding: 12px 20px;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}
.modal-btn-primary:hover {
  background: #38a169;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(56, 161, 105, 0.3);
}

/* Transition */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.25s ease;
}
.modal-fade-enter-active .modal-card,
.modal-fade-leave-active .modal-card {
  transition:
    transform 0.25s ease,
    opacity 0.25s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
.modal-fade-enter-from .modal-card,
.modal-fade-leave-to .modal-card {
  transform: translateY(16px);
  opacity: 0;
}

@media (max-width: 1200px) {
  .cart-content {
    grid-template-columns: 1fr;
  }
  .order-summary {
    position: static;
  }
}
@media (max-width: 768px) {
  .cart-item {
    grid-template-columns: auto 100px 1fr;
    gap: 12px;
    padding: 12px;
  }
  .item-image {
    width: 100px;
    height: 100px;
  }
  .item-actions {
    flex-direction: column;
    align-items: stretch;
  }
  .btn-customize {
    justify-content: center;
  }
  .item-pricing {
    text-align: left;
    align-items: flex-start;
  }
  .price-discount {
    justify-content: flex-start;
  }
  .vendor-header {
    flex-wrap: wrap;
  }
  .modal-card {
    padding: 28px 20px;
  }
  .modal-actions {
    flex-direction: column;
  }
}
@media (max-width: 640px) {
  .cart-container {
    padding: 24px 16px;
  }
  .cart-header h1 {
    font-size: 24px;
  }
  .item-specifications {
    flex-direction: column;
    gap: 8px;
  }
  .select-all-section {
    flex-direction: column;
    gap: 12px;
    align-items: flex-start;
  }
}
</style>
