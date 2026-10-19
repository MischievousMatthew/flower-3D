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

    <main class="bc-container cart-container">
      <header class="cart-header">
        <p class="bc-label">Your selection</p>
        <h1>Shopping <em>cart</em></h1>
        <p v-if="!isLoading" class="cart-count">
          {{ cartItems.length }} {{ cartItems.length === 1 ? "item" : "items" }}
        </p>
        <span v-else class="bc-skeleton cart-count-skeleton"></span>
      </header>

      <!-- Skeleton while the cart loads -->
      <div v-if="isLoading" class="cart-content" aria-label="Loading your cart">
        <div class="cart-items">
          <div v-for="n in 3" :key="n" class="cart-item cart-item--skeleton">
            <span class="bc-skeleton skeleton-check"></span>
            <span class="bc-skeleton skeleton-image"></span>
            <div class="skeleton-lines">
              <span class="bc-skeleton skeleton-line skeleton-line--title"></span>
              <span class="bc-skeleton skeleton-line skeleton-line--short"></span>
              <span class="bc-skeleton skeleton-line skeleton-line--qty"></span>
            </div>
            <span class="bc-skeleton skeleton-line skeleton-price"></span>
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
        <h2>Your cart is <em>empty</em></h2>
        <p>Looks like you haven't added anything to your cart yet</p>
        <button class="bc-btn bc-btn--dark" @click="$router.push('/shop')">
          Continue shopping
          <span class="bc-btn-arrow" aria-hidden="true">→</span>
        </button>
      </div>

      <div v-else class="cart-content">
        <div class="cart-items">
          <div
            v-if="hasLowStockItems || hasOutOfStockItems"
            class="stock-alert-banner"
            :class="{ 'stock-alert-banner--error': hasOutOfStockItems }"
            role="status"
          >
            <p v-if="hasOutOfStockItems">
              Some items in your cart are out of stock. Please remove them to
              continue.
            </p>
            <p v-else-if="hasLowStockItems">
              Some items in your cart have low stock. Order soon!
            </p>
          </div>

          <div class="select-all-section">
            <label class="checkbox-container">
              <input
                type="checkbox"
                v-model="selectAll"
                @change="toggleSelectAll"
              />
              <span class="checkbox-label">Select all</span>
            </label>
            <span class="selected-count"
              >{{ selectedItems.length }} of
              {{ cartItems.length }} selected</span
            >
          </div>

          <section
            v-for="vendor in vendorGroups"
            :key="vendor.id"
            class="vendor-group"
            :aria-label="vendor.name"
          >
            <div class="vendor-header">
              <label class="checkbox-container">
                <input
                  type="checkbox"
                  :checked="isVendorSelected(vendor.id)"
                  :aria-label="`Select all items from ${vendor.name}`"
                  @change="toggleVendorSelect(vendor.id)"
                />
              </label>
              <span class="vendor-name">{{ vendor.name }}</span>
              <span class="vendor-item-count"
                >{{ vendor.items.length }} item{{
                  vendor.items.length > 1 ? "s" : ""
                }}</span
              >
            </div>

            <article
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
                  :aria-label="`Select ${getProductName(item.product, item)}`"
                />
              </label>

              <div class="item-image">
                <img
                  :src="getProductImage(item.product) || FALLBACK_IMAGE"
                  :alt="getProductName(item.product, item)"
                  @error="handleImageError"
                />
              </div>

              <div class="item-details">
                <div class="item-header">
                  <div>
                    <p class="item-category">
                      {{ getProductCategory(item.product, item) }}
                    </p>
                    <h3 class="item-name">
                      {{ getProductName(item.product, item) }}
                    </h3>
                    <p
                      v-if="item.product?.color || item.product?.variety"
                      class="item-specifications"
                    >
                      <span v-if="item.product.color">{{
                        item.product.color
                      }}</span>
                      <span v-if="item.product.variety">{{
                        item.product.variety
                      }}</span>
                    </p>
                  </div>
                  <button
                    class="btn-remove"
                    @click="removeFromCart(item.id)"
                    title="Remove item"
                    :aria-label="`Remove ${getProductName(item.product, item)}`"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12" />
                    </svg>
                  </button>
                </div>

                <div class="item-footer">
                  <div class="quantity-control-wrapper">
                    <div class="quantity-control">
                      <button
                        class="qty-btn"
                        aria-label="Decrease quantity"
                        @click="decreaseQuantity(item.id)"
                        :disabled="
                          isCustomBouquet(item) ||
                          getStockStatus(item.product) === 'Out of stock'
                        "
                      >
                        &#x2212;
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
                        +
                      </button>
                    </div>
                    <span
                      v-if="getStockStatus(item.product) === 'Out of stock'"
                      class="stock-warning"
                      >Out of stock</span
                    >
                    <span
                      v-else-if="getStockStatus(item.product) === 'Low stock'"
                      class="stock-warning stock-warning--low"
                      >Only {{ item.product?.quantity_in_stock }} left</span
                    >
                    <span
                      v-if="pendingUpdates[item.id]"
                      class="sync-indicator"
                      aria-live="polite"
                      >Saving...</span
                    >
                  </div>

                  <div class="item-actions">
                    <button
                      v-if="isCustomBouquet(item)"
                      class="item-action"
                      @click="viewBouquet(item)"
                    >
                      View 3D
                    </button>
                    <button
                      v-if="isCustomBouquet(item)"
                      class="item-action"
                      @click="editBouquet(item)"
                      :disabled="getStockStatus(item.product) === 'Out of stock'"
                    >
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
                      Customize
                    </button>
                  </div>
                </div>
              </div>

              <div class="item-pricing">
                <template v-if="hasDiscount(item)">
                  <span class="price-current"
                    >₱{{ formatPrice(effectivePrice(item)) }}</span
                  >
                  <span class="price-original"
                    >₱{{ formatPrice(originalPrice(item)) }}</span
                  >
                  <span class="price-badge"
                    >{{ discountPercent(item) }}% off</span
                  >
                </template>
                <span v-else class="price-current"
                  >₱{{ formatPrice(effectivePrice(item)) }}</span
                >
                <span class="item-subtotal">
                  Subtotal ₱{{ formatPrice(lineTotal(item)) }}
                  <span
                    v-if="hasDiscount(item) && item.quantity > 1"
                    class="subtotal-saving"
                  >
                    (save ₱{{ formatPrice(lineSaving(item)) }})
                  </span>
                </span>
              </div>
            </article>
          </section>
        </div>

        <!-- Order Summary -->
        <aside class="order-summary" aria-labelledby="summary-title">
          <h2 id="summary-title">Order <em>summary</em></h2>

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

          <p class="payment-methods">
            Pay with an e-wallet via PayMongo, or cash on delivery.
          </p>
        </aside>
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
            class="btn-remove"
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
          background-color="#ebe5dd"
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
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import "../../styles/bloomcraft.css";
import ThreeDModelViewer from "../../layouts/3D/3DModelViewer.vue";

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
  .cart-page :is(p, h1, h2, h3) {
    margin: 0;
  }
}

/* Same body reset the landing and shop pages apply (UA 8px margin). */
:global(body) {
  margin: 0;
}

.cart-page {
  min-height: 100vh;
  background: var(--ivory);
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
  padding-top: 150px;
  padding-bottom: var(--section-space);
}

/* ---- Header ---- */
.cart-header {
  margin-bottom: 56px;
}

.cart-header h1 {
  margin-top: 22px;
  font-size: clamp(48px, 5.6vw, 84px);
}

.cart-count {
  margin-top: 10px;
  color: var(--ink-soft);
  font-size: 15px;
}

.cart-count-skeleton {
  display: block;
  width: 80px;
  height: 14px;
  margin-top: 16px;
}

/* ---- Empty ---- */
.empty-cart {
  padding: 80px 0;
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
  text-align: center;
}

.empty-cart h2 {
  font-size: clamp(36px, 4vw, 56px);
}

.empty-cart p {
  margin: 14px 0 32px;
  color: var(--ink-soft);
}

/* ---- Layout ---- */
.cart-content {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 380px;
  gap: clamp(32px, 4vw, 64px);
  align-items: start;
}

.stock-alert-banner {
  margin-bottom: 20px;
  padding: 14px 18px;
  border: 1px solid rgba(212, 178, 122, 0.6);
  border-radius: 14px;
  background: rgba(212, 178, 122, 0.12);
  color: #7a5a1e;
  font-size: 14px;
}

.stock-alert-banner--error {
  border-color: rgba(142, 68, 58, 0.35);
  background: rgba(184, 101, 88, 0.08);
  color: var(--rosewood-dark);
}

.select-all-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 0 18px;
  border-bottom: 1px solid var(--line);
}

.checkbox-container {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
}

.checkbox-container input {
  width: 18px;
  height: 18px;
  margin: 0;
  accent-color: var(--ink);
  cursor: pointer;
}

.checkbox-container input:disabled {
  cursor: not-allowed;
}

.checkbox-label {
  font-size: 14px;
  font-weight: 500;
}

.selected-count {
  color: var(--ink-soft);
  font-size: 13px;
}

/* ---- Vendor groups ---- */
.vendor-group {
  padding-top: 26px;
}

.vendor-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 6px;
}

.vendor-name {
  font-family: var(--font-display);
  font-size: 24px;
  font-weight: 500;
}

.vendor-item-count {
  margin-left: auto;
  color: var(--ink-soft);
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

/* ---- Item row ---- */
.cart-item {
  display: grid;
  grid-template-columns: 18px 104px minmax(0, 1fr) auto;
  gap: 22px;
  align-items: start;
  padding: 24px 0;
  border-bottom: 1px solid var(--line);
}

.cart-item.out-of-stock .item-image,
.cart-item.out-of-stock .item-details {
  opacity: 0.55;
}

.item-checkbox {
  margin-top: 4px;
}

.item-image {
  width: 104px;
  aspect-ratio: 4 / 5;
  overflow: hidden;
  border-radius: 12px;
  background: var(--ivory-deep);
}

.item-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.item-details {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

.item-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.item-category {
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.18em;
  text-transform: uppercase;
}

.cart-page .item-name {
  margin-top: 4px;
  font-size: 26px;
  line-height: 1.1;
}

.item-specifications {
  margin-top: 6px;
  color: var(--ink-soft);
  font-size: 13px;
  text-transform: capitalize;
}

.item-specifications span + span::before {
  content: "·";
  margin: 0 8px;
  color: var(--rosewood);
}

.btn-remove {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 50%;
  background: none;
  color: var(--ink-soft);
  cursor: pointer;
  transition: background 0.25s, color 0.25s;
}

.btn-remove:hover {
  background: var(--ivory-deep);
  color: var(--rosewood-dark);
}

.btn-remove svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.item-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
}

.quantity-control-wrapper {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
}

.quantity-control {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--line);
  border-radius: 999px;
}

.qty-btn {
  width: 36px;
  height: 38px;
  border: none;
  background: none;
  color: var(--ink);
  font-size: 16px;
  cursor: pointer;
}

.qty-btn:disabled {
  color: var(--line);
  cursor: not-allowed;
}

.qty-input {
  width: 34px;
  border: none;
  background: none;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 14px;
  font-weight: 500;
  text-align: center;
}

.qty-input:focus {
  outline: none;
}

.qty-input:disabled {
  color: var(--ink-soft);
}

.stock-warning {
  color: var(--rosewood-dark);
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.stock-warning--low {
  color: #9a6a1f;
}

.sync-indicator {
  color: var(--ink-soft);
  font-size: 12px;
}

.item-actions {
  display: flex;
  gap: 18px;
}

.item-action {
  padding: 0 0 3px;
  border: none;
  border-bottom: 1px solid currentColor;
  background: none;
  color: var(--ink);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: color 0.3s;
}

.item-action:hover:not(:disabled) {
  color: var(--rosewood-dark);
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
  gap: 4px;
  /* Fixed width keeps every row's remove button and actions aligned */
  width: 150px;
  text-align: right;
}

.price-current {
  font-family: var(--font-display);
  font-size: 26px;
  font-weight: 500;
  line-height: 1;
}

.price-original {
  color: var(--ink-soft);
  font-size: 13px;
  text-decoration: line-through;
}

.price-badge {
  color: var(--rosewood-dark);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.item-subtotal {
  margin-top: 6px;
  color: var(--ink-soft);
  font-size: 12.5px;
}

.subtotal-saving {
  color: var(--rosewood-dark);
}

/* ---- Summary ---- */
.order-summary {
  position: sticky;
  top: 110px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 32px 30px;
  border: 1px solid var(--line-soft);
  border-radius: 20px;
  background: var(--paper);
}

.order-summary h2 {
  font-size: 34px;
}

.summary-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
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
}

.discount-amount,
.breakdown-saving {
  color: var(--rosewood-dark) !important;
}

.discount-breakdown {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px 16px;
  border-radius: 12px;
  background: var(--ivory);
}

.breakdown-title {
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
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

.summary-total {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-top: 18px;
  border-top: 1px solid var(--line);
  font-size: 14px;
  font-weight: 500;
}

.total-amount {
  font-family: var(--font-display);
  font-size: 40px;
  font-weight: 500;
  line-height: 1;
}

.summary-actions {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.btn-checkout {
  justify-content: center;
  width: 100%;
}

.btn-checkout:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.btn-continue-shopping {
  border: none;
  background: none;
  cursor: pointer;
}

.payment-methods {
  color: var(--ink-soft);
  font-size: 12.5px;
  line-height: 1.6;
  text-align: center;
}

.qty-btn:focus-visible,
.btn-remove:focus-visible,
.item-action:focus-visible,
.checkbox-container input:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 3px;
}

/* ---- Skeleton ---- */
.cart-item--skeleton {
  align-items: center;
}

.skeleton-check {
  width: 18px;
  height: 18px;
  border-radius: 5px;
}

.skeleton-image {
  width: 104px;
  aspect-ratio: 4 / 5;
  border-radius: 12px;
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

.skeleton-line--title {
  width: 70%;
  height: 22px;
}

.skeleton-line--short {
  width: 40%;
}

.skeleton-line--qty {
  width: 120px;
  height: 36px;
  border-radius: 999px;
}

.skeleton-price {
  width: 80px;
  height: 22px;
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
  background: rgba(27, 22, 18, 0.45);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

.modal-card {
  width: 100%;
  max-width: 460px;
  padding: 40px 34px 32px;
  border-radius: 26px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(27, 22, 18, 0.25);
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
  box-shadow: 0 30px 80px rgba(27, 22, 18, 0.25);
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
}

@media (max-width: 640px) {
  .cart-container {
    padding-top: 120px;
  }

  .cart-item {
    grid-template-columns: 18px 84px minmax(0, 1fr);
    gap: 14px;
  }

  .item-image,
  .skeleton-image {
    width: 84px;
  }

  .item-pricing {
    width: auto;
    grid-column: 2 / -1;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 6px 10px;
    text-align: left;
  }

  .item-subtotal {
    width: 100%;
    margin-top: 0;
  }

  .cart-page .item-name {
    font-size: 22px;
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
  .modal-fade-leave-active {
    transition: none;
  }
}
</style>
