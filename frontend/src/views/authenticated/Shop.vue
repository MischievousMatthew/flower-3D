<template>
  <div class="shop-page">
    <NavHeader
      ref="navHeaderRef"
      :cartCount="cartCount"
      :isAuthenticated="isAuthenticated"
      @scroll-to-section="scrollToSection"
    />
    <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />

    <div
      v-if="showAuthModal"
      class="auth-modal-overlay"
      @click="closeAuthModal"
    >
      <div class="auth-modal-content" @click.stop>
        <button class="auth-modal-close" @click="closeAuthModal">
          &#x2715;
        </button>
        <div class="auth-modal-body">
          <div class="auth-modal-icon">&#x1F512;</div>
          <h3>Login Required</h3>
          <p>Please login to add items to cart, or view product details.</p>
          <div class="auth-modal-actions">
            <button @click="goToLogin" class="btn-login-modal">
              Login Now
            </button>
            <button @click="goToRegister" class="btn-register-modal">
              Create Account
            </button>
          </div>
          <p class="auth-modal-note">
            Continue browsing as guest?
            <button @click="closeAuthModal" class="btn-continue">
              Continue Browsing
            </button>
          </p>
        </div>
      </div>
    </div>

    <section class="hero">
      <div class="hero-content">
        <h1>
          The Ultimate <span class="highlight">Flower</span><br />Shopping
          Destination
        </h1>
        <p>
          Discover beautiful, fresh flowers from local vendors. Perfect for
          every occasion.
        </p>
        <div class="hero-buttons">
          <button class="btn-primary" @click="scrollToSection('products')">
            Browse Flowers &#x2192;
          </button>
          <button
            v-if="!isAuthenticated"
            class="btn-secondary"
            @click="goToRegister"
          >
            Create Free Account
          </button>
        </div>
        <div class="ratings">
          <div class="customer-avatars">
            <img
              v-for="n in 4"
              :key="n"
              :src="`https://i.pravatar.cc/40?img=${n}`"
              alt="Customer"
              class="avatar"
            />
            <div class="avatar-more">+</div>
          </div>
          <div class="rating-text">
            <div class="stars">&#x2B50; 4.9+ Ratings</div>
            <p>Trusted by {{ totalCustomers }}+ Customers</p>
          </div>
        </div>
      </div>
      <div class="hero-image">
        <img
          src="https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=600&h=600&fit=crop"
          alt="Flower bouquet"
        />
        <div class="badge badge-secure">&#x1F512; Secure Payment</div>
        <div class="badge badge-delivery">&#x1F69A; Fast Delivery</div>
      </div>
    </section>

    <section class="vendors-section" id="vendors">
      <div class="vendors-container">
        <div class="vendors-header">
          <div>
            <p class="section-subtitle">Our Sellers</p>
            <h2>Browse by Store</h2>
          </div>
          <div class="vendor-search-bar">
            <span class="vendor-search-icon">&#x1F50D;</span>
            <input
              v-model="vendorSearch"
              @input="debouncedFetchVendors"
              placeholder="Search flower shops..."
              class="vendor-search-input"
            />
            <button
              v-if="vendorSearch"
              @click="clearVendorSearch"
              class="vendor-search-clear"
            >
              &#x2715;
            </button>
          </div>
        </div>
        <div v-if="loadingVendors" class="vendors-grid">
          <div v-for="n in 4" :key="n" class="vendor-card vendor-card-skeleton">
            <div class="skeleton skeleton-logo"></div>
            <div class="vendor-card-info">
              <div class="skeleton skeleton-title"></div>
              <div class="skeleton skeleton-desc"></div>
            </div>
          </div>
        </div>
        <div v-else-if="vendors.length > 0" class="vendors-grid">
          <div
            v-for="vendor in vendors"
            :key="vendor.id"
            :class="[
              'vendor-card',
              { 'vendor-card-active': selectedVendor?.id === vendor.id },
            ]"
            @click="selectVendor(vendor)"
          >
            <div class="vendor-card-logo">
              <img
                v-if="vendor.store_logo_path"
                :src="vendor.store_logo_path"
                :alt="vendor.store_name"
                @error="handleVendorLogoError"
              />
              <span v-else class="vendor-logo-fallback">&#x1F338;</span>
            </div>
            <div class="vendor-card-info">
              <div class="vendor-card-title-row">
                <h4 class="vendor-card-name">{{ vendor.store_name }}</h4>
                <span
                  v-if="vendor.verification_level === 'verified'"
                  class="vendor-verified-badge"
                  title="Verified Vendor"
                  >&#x2713;</span
                >
              </div>
              <p class="vendor-card-desc">
                {{ truncateDescription(vendor.store_description, 55) }}
              </p>
              <div class="vendor-card-meta">
                <span
                  v-if="vendor.same_day_delivery"
                  class="vendor-meta-tag tag-sameday"
                  >&#x26A1; Same-Day</span
                >
                <span class="vendor-meta-tag tag-price"
                  >&#x20B1;{{
                    Math.round(vendor.price_min).toLocaleString()
                  }}+</span
                >
              </div>
            </div>
            <button
              @click.stop="goToStorefront(vendor.id)"
              class="btn-view-store"
            >
              Visit Store &#x2192;
            </button>
          </div>
        </div>
        <div v-else class="no-vendors">
          <span class="no-vendors-icon">&#x1F3EA;</span>
          <p>
            No stores found{{ vendorSearch ? ` for "${vendorSearch}"` : "" }}
          </p>
        </div>
        <div v-if="vendorPagination.last_page > 1" class="vendors-pagination">
          <button
            @click="prevVendorPage"
            :disabled="vendorPagination.current_page === 1"
            class="pagination-btn"
          >
            &#x2190; Prev
          </button>
          <span class="pagination-info"
            >{{ vendorPagination.current_page }} /
            {{ vendorPagination.last_page }}</span
          >
          <button
            @click="nextVendorPage"
            :disabled="
              vendorPagination.current_page === vendorPagination.last_page
            "
            class="pagination-btn"
          >
            Next &#x2192;
          </button>
        </div>
      </div>
    </section>

    <section id="products" class="products-section">
      <div class="products-container">
        <aside class="filter-sidebar">
          <div v-if="selectedVendor" class="active-vendor-filter">
            <div class="active-vendor-logo">
              <img
                v-if="selectedVendor.store_logo_path"
                :src="selectedVendor.store_logo_path"
                :alt="selectedVendor.store_name"
              />
              <span v-else class="vendor-logo-placeholder">&#x1F338;</span>
            </div>
            <div class="active-vendor-info">
              <p class="active-vendor-label">Viewing store</p>
              <p class="active-vendor-name">{{ selectedVendor.store_name }}</p>
            </div>
            <button
              @click="clearVendorFilter"
              class="btn-clear-vendor"
              title="Show all stores"
            >
              &#x2715;
            </button>
          </div>
          <div class="filter-header">
            <h2>Filter</h2>
            <button class="btn-clear-all" @click="clearAllFilters">
              Clear All
            </button>
          </div>
          <p class="filter-subtitle">
            Narrow down your searches with our filter.
          </p>
          <div class="filter-group">
            <button class="filter-toggle" @click="toggleFilter('category')">
              <div class="filter-label">
                <span class="filter-icon">&#x1F3F7;&#xFE0F;</span
                ><span>Category</span>
              </div>
              <div class="filter-value">
                {{
                  selectedFilters.category.length > 0
                    ? selectedFilters.category.join(", ")
                    : "All"
                }}<span class="chevron" :class="{ open: openFilters.category }"
                  >&#x203A;</span
                >
              </div>
            </button>
            <div v-if="openFilters.category" class="filter-options">
              <div
                v-if="filterOptions.categories.length === 0"
                class="no-options"
              >
                Loading...
              </div>
              <label
                v-for="category in filterOptions.categories"
                :key="category"
                class="checkbox-label"
              >
                <input
                  type="checkbox"
                  :value="category"
                  v-model="selectedFilters.category"
                  @change="fetchProducts"
                /><span>{{ category }}</span>
              </label>
            </div>
          </div>
          <div class="filter-group">
            <button class="filter-toggle" @click="toggleFilter('color')">
              <div class="filter-label">
                <span class="filter-icon">&#x1F3A8;</span><span>Color</span>
              </div>
              <div class="filter-value">
                {{
                  selectedFilters.color.length > 0
                    ? selectedFilters.color.join(", ")
                    : "All"
                }}<span class="chevron" :class="{ open: openFilters.color }"
                  >&#x203A;</span
                >
              </div>
            </button>
            <div v-if="openFilters.color" class="filter-options">
              <div v-if="filterOptions.colors.length === 0" class="no-options">
                Loading...
              </div>
              <label
                v-for="color in filterOptions.colors"
                :key="color"
                class="checkbox-label"
              >
                <input
                  type="checkbox"
                  :value="color"
                  v-model="selectedFilters.color"
                  @change="fetchProducts"
                /><span>{{ color }}</span>
              </label>
            </div>
          </div>
          <div class="filter-group">
            <button class="filter-toggle" @click="toggleFilter('price')">
              <div class="filter-label">
                <span class="filter-icon">&#x1F4B0;</span><span>Price</span>
              </div>
              <div class="filter-value">
                {{ priceRangeText
                }}<span class="chevron" :class="{ open: openFilters.price }"
                  >&#x203A;</span
                >
              </div>
            </button>
            <div v-if="openFilters.price" class="filter-options">
              <div class="price-range">
                <div class="price-inputs">
                  <input
                    type="number"
                    v-model.number="selectedFilters.priceMin"
                    :placeholder="`Min &#x20B1;${filterOptions.price_range?.min || 0}`"
                    @input="onPriceChange"
                    class="price-input"
                  />
                  <span class="price-separator">-</span>
                  <input
                    type="number"
                    v-model.number="selectedFilters.priceMax"
                    :placeholder="`Max &#x20B1;${filterOptions.price_range?.max || 100}`"
                    @input="onPriceChange"
                    class="price-input"
                  />
                </div>
                <div class="price-slider">
                  <input
                    type="range"
                    :min="filterOptions.price_range?.min || 0"
                    :max="filterOptions.price_range?.max || 100"
                    v-model.number="priceSliderValue"
                    @input="onSliderChange"
                    class="slider"
                  />
                </div>
              </div>
            </div>
          </div>
          <div class="filter-group">
            <button class="filter-toggle" @click="toggleFilter('occasion')">
              <div class="filter-label">
                <span class="filter-icon">&#x1F389;</span><span>Occasion</span>
              </div>
              <div class="filter-value">
                {{
                  selectedFilters.occasion.length > 0
                    ? selectedFilters.occasion.join(", ")
                    : "All"
                }}<span class="chevron" :class="{ open: openFilters.occasion }"
                  >&#x203A;</span
                >
              </div>
            </button>
            <div v-if="openFilters.occasion" class="filter-options">
              <div
                v-if="filterOptions.occasions.length === 0"
                class="no-options"
              >
                Loading...
              </div>
              <label
                v-for="occasion in filterOptions.occasions"
                :key="occasion"
                class="checkbox-label"
              >
                <input
                  type="checkbox"
                  :value="occasion"
                  v-model="selectedFilters.occasion"
                  @change="fetchProducts"
                /><span>{{ occasion }}</span>
              </label>
            </div>
          </div>
          <div class="filter-group">
            <button class="filter-toggle" @click="toggleFilter('season')">
              <div class="filter-label">
                <span class="filter-icon">&#x1F338;</span><span>Season</span>
              </div>
              <div class="filter-value">
                {{
                  selectedFilters.season.length > 0
                    ? selectedFilters.season.join(", ")
                    : "All"
                }}<span class="chevron" :class="{ open: openFilters.season }"
                  >&#x203A;</span
                >
              </div>
            </button>
            <div v-if="openFilters.season" class="filter-options">
              <label
                v-for="season in filterOptions.seasons"
                :key="season"
                class="checkbox-label"
              >
                <input
                  type="checkbox"
                  :value="season"
                  v-model="selectedFilters.season"
                  @change="fetchProducts"
                /><span>{{
                  season.charAt(0).toUpperCase() + season.slice(1)
                }}</span>
              </label>
            </div>
          </div>
          <div class="filter-group">
            <button class="filter-toggle" @click="toggleFilter('availability')">
              <div class="filter-label">
                <span class="filter-icon">&#x2705;</span
                ><span>Availability</span>
              </div>
              <div class="filter-value">
                {{ availabilityText
                }}<span
                  class="chevron"
                  :class="{ open: openFilters.availability }"
                  >&#x203A;</span
                >
              </div>
            </button>
            <div v-if="openFilters.availability" class="filter-options">
              <label class="checkbox-label"
                ><input
                  type="checkbox"
                  v-model="selectedFilters.inStockOnly"
                  @change="fetchProducts"
                /><span>In Stock Only</span></label
              >
            </div>
          </div>
        </aside>

        <div class="products-main">
          <div class="section-header">
            <div>
              <p class="section-subtitle">Products</p>
              <h2 v-if="selectedVendor">
                {{ selectedVendor.store_name }}'s Collection
                <button
                  @click="clearVendorFilter"
                  class="btn-clear-store-inline"
                >
                  &#x00D7; All Stores
                </button>
              </h2>
              <h2 v-else>Fresh Flowers Collection</h2>
            </div>
            <div class="sort-controls">
              <span class="results-count"
                >{{ pagination.total }} Products Found</span
              >
              <select
                v-model="sortBy"
                @change="onSortChange"
                class="sort-select"
              >
                <option value="created_at_desc">Newest First</option>
                <option value="price_low">Price: Low to High</option>
                <option value="price_high">Price: High to Low</option>
                <option value="name_asc">Name: A to Z</option>
                <option value="name_desc">Name: Z to A</option>
              </select>
            </div>
          </div>
          <div v-if="products.length === 0 && !isLoading" class="no-results">
            <div class="no-results-icon">&#x1F50D;</div>
            <h3>No products found</h3>
            <p>Try adjusting your filters to find what you're looking for.</p>
            <button class="btn-clear-filters" @click="clearAllFilters">
              Clear All Filters
            </button>
          </div>
          <div v-else class="products-grid">
            <ProductCard
              v-for="product in products"
              :key="product.id"
              :product="product"
              :selected-vendor="selectedVendor"
              :adding-to-cart="addingToCartProductId === product.id"
              @open-modal="openProductModal"
              @add-to-cart="addToCartDirect"
              @select-vendor="selectVendorById"
            />
          </div>
          <div v-if="pagination.total > 0" class="pagination">
            <button
              @click="prevPage"
              :disabled="pagination.current_page === 1"
              class="pagination-btn"
            >
              &#x2190; Previous
            </button>
            <span class="pagination-info"
              >Page {{ pagination.current_page }} of
              {{ pagination.last_page }}</span
            >
            <button
              @click="nextPage"
              :disabled="pagination.current_page === pagination.last_page"
              class="pagination-btn"
            >
              Next &#x2192;
            </button>
          </div>
        </div>
      </div>
    </section>

    <section class="promo-section">
      <div class="promo-card promo-primary">
        <div class="promo-content">
          <span class="promo-badge">Flat 20% Discount</span>
          <h3>Lovely Fresh<br />Bouquets</h3>
          <p>Special discount on all bouquet collections this week.</p>
          <button class="btn-promo" @click="scrollToSection('products')">
            Shop Now &#x2192;
          </button>
        </div>
        <div class="promo-image">
          <img
            src="https://images.unsplash.com/photo-1525310072745-f49212b5ac6d?w=400&h=400&fit=crop"
            alt="Fresh Bouquets"
          />
        </div>
      </div>
      <div class="promo-card promo-secondary">
        <div class="promo-content">
          <span class="promo-badge">Flat 25% Discount</span>
          <h3>Pure Bloom<br />Collection</h3>
          <p>Exclusive seasonal flowers available for limited time.</p>
          <button class="btn-promo" @click="scrollToSection('products')">
            Shop Now &#x2192;
          </button>
        </div>
        <div class="promo-image">
          <img
            src="https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=400&h=400&fit=crop"
            alt="Bloom Collection"
          />
        </div>
      </div>
    </section>

    <section class="holiday-sales" id="sale">
      <h2>Holiday Sales</h2>
      <p>Get up to 50% off - Limited Time Offer!</p>
      <div class="countdown">
        <div class="countdown-item">
          <span class="countdown-number">{{ countdown.days }}</span
          ><span class="countdown-label">Days</span>
        </div>
        <span class="countdown-separator">:</span>
        <div class="countdown-item">
          <span class="countdown-number">{{ countdown.hours }}</span
          ><span class="countdown-label">Hours</span>
        </div>
        <span class="countdown-separator">:</span>
        <div class="countdown-item">
          <span class="countdown-number">{{ countdown.minutes }}</span
          ><span class="countdown-label">Minutes</span>
        </div>
        <span class="countdown-separator">:</span>
        <div class="countdown-item">
          <span class="countdown-number">{{ countdown.seconds }}</span
          ><span class="countdown-label">Seconds</span>
        </div>
      </div>
      <button class="btn-shop-now" @click="scrollToSection('products')">
        Shop Now &#x2192;
      </button>
    </section>

    <!-- ENHANCED PRODUCT MODAL -->
    <div v-if="showProductModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <button class="modal-close" @click="closeModal">&#x2715;</button>
        <div v-if="selectedProduct" class="modal-inner">
          <!-- LEFT: Images -->
          <div class="modal-left">
            <ModelViewer3D
              v-if="
                show3D &&
                selectedProduct.models &&
                selectedProduct.models.length > 0
              "
              :modelUrl="selectedProduct.models[0].model_url"
              :modelType="selectedProduct.models[0].model_type"
              class="model-viewer"
            />
            <div v-else class="image-container">
              <img
                :src="
                  selectedProduct.images?.[selectedImageIndex]?.image_url ||
                  getProductImage(selectedProduct, true)
                "
                :alt="selectedProduct.product_name"
                class="main-image"
                @error="handleImageError"
              />
              <div
                v-if="
                  selectedProduct.images && selectedProduct.images.length > 1
                "
                class="image-thumbnails"
              >
                <div
                  v-for="(image, index) in selectedProduct.images"
                  :key="image.id"
                  class="thumbnail"
                  :class="{ active: selectedImageIndex === index }"
                  @click="selectImage(index)"
                >
                  <img
                    :src="image.image_url"
                    :alt="`${selectedProduct.product_name} - Image ${index + 1}`"
                  />
                </div>
              </div>
            </div>
            <div
              v-if="
                selectedProduct.models?.length > 0 &&
                selectedProduct.images?.length > 0
              "
              class="view-toggle"
            >
              <button @click="show3D = true" :class="{ active: show3D }">
                3D View
              </button>
              <button @click="show3D = false" :class="{ active: !show3D }">
                Photos
              </button>
            </div>
          </div>

          <!-- RIGHT: Details + Reviews (scrollable) -->
          <div class="modal-right">
            <div class="modal-top-info">
              <p class="modal-category-tag">{{ selectedProduct.category }}</p>
              <h2 class="modal-product-name">
                {{ selectedProduct.product_name }}
              </h2>

              <!-- Live stats -->
              <div v-if="!productStatsLoading" class="modal-stats-row">
                <div class="modal-stars">
                  <span
                    v-for="n in 5"
                    :key="n"
                    class="modal-star"
                    :class="getStarClass(n, productStats.average_rating)"
                    >&#x2605;</span
                  >
                </div>
                <span class="modal-stats-avg">{{
                  (productStats.average_rating || 0).toFixed(1)
                }}</span>
                <span class="modal-stats-sep">&#x00B7;</span>
                <span class="modal-stats-reviews"
                  >{{ productStats.total_reviews }} review{{
                    productStats.total_reviews !== 1 ? "s" : ""
                  }}</span
                >
                <span class="modal-stats-sep">&#x00B7;</span>
                <span class="modal-stats-sold"
                  >{{ productStats.sold_count }} sold</span
                >
              </div>
              <div v-else class="modal-stats-skeleton">
                <div class="msk msk--wide"></div>
              </div>

              <div class="modal-price-row">
                <template v-if="selectedProduct.discount_price">
                  <span class="modal-price-current"
                    >₱{{ formatPrice(selectedProduct.discount_price) }}</span
                  >
                  <span class="modal-price-original"
                    >₱{{ formatPrice(selectedProduct.selling_price) }}</span
                  >
                </template>
                <template v-else>
                  <span class="modal-price-current"
                    >₱{{ formatPrice(selectedProduct.selling_price) }}</span
                  >
                </template>
                <span
                  v-if="calculateDiscountPercentage(selectedProduct) > 0"
                  class="modal-price-badge"
                  >{{ calculateDiscountPercentage(selectedProduct) }}% OFF</span
                >
              </div>
            </div>

            <div class="modal-desc-block">
              <p class="modal-desc-text">{{ selectedProduct.description }}</p>
            </div>

            <div class="modal-specs-row">
              <div class="modal-spec">
                <span class="modal-spec__label">Color</span>
                <span class="modal-spec__val">{{
                  selectedProduct.color || "Mixed"
                }}</span>
              </div>
              <div class="modal-spec">
                <span class="modal-spec__label">Stock</span>
                <span
                  class="modal-spec__val stock-pill"
                  :class="getStockStatusClass(selectedProduct)"
                  >{{ getStockStatusText(selectedProduct) }}</span
                >
              </div>
            </div>

            <div class="modal-cart-row">
              <div class="quantity-selector">
                <button @click="decreaseQuantity" class="qty-btn">
                  &#x2212;
                </button>
                <span class="qty-value">{{ quantity }}</span>
                <button @click="increaseQuantity" class="qty-btn">+</button>
              </div>
              <button
                @click="addToCartModal"
                :disabled="
                  selectedProduct.quantity_in_stock === 0 || addingToCartModal
                "
                class="btn-add-to-cart-modal"
              >
                <span
                  v-if="addingToCartModal"
                  class="loading-spinner-small"
                ></span>
                <span v-else>{{
                  selectedProduct.quantity_in_stock > 0
                    ? "Add to Cart"
                    : "Out of Stock"
                }}</span>
              </button>
              <button
                @click="buyNow"
                :disabled="
                  selectedProduct.quantity_in_stock === 0 || addingToCartModal
                "
                class="btn-buy-now"
              >
                <span
                  v-if="addingToCartModal"
                  class="loading-spinner-small"
                ></span>
                <span v-else>Buy Now</span>
              </button>
            </div>

            <div v-if="!isAuthenticated" class="guest-notice">
              <p>
                &#x1F4A1; <strong>Create an account</strong> to save items to
                add to cart, track orders, and get personalized recommendations!
              </p>
              <div class="guest-actions">
                <button @click="goToLogin" class="btn-guest-login">
                  Login
                </button>
                <button @click="goToRegister" class="btn-guest-register">
                  Sign Up
                </button>
              </div>
            </div>

            <div class="modal-reviews-embed">
              <div class="modal-reviews-embed__head">
                <h4 class="modal-reviews-embed__title">Customer Reviews</h4>
              </div>
              <ProductReviews
                v-if="selectedProduct"
                :product-id="selectedProduct.id"
                class="modal-reviews-component"
                @updated="refreshProductStats"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import api from "../../plugins/axios.js";
import NavHeader from "../../layouts/NavHeader.vue";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";
import ModelViewer3D from "../../layouts/3D/3DModelViewer.vue";
import productService from "../../services/productService.js";
import cartService from "../../services/cartService.js";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import ProductReviews from "../../layouts/components/ProductReviews.vue";
import ProductCard from "../../layouts/components/ProductCard.vue";

const router = useRouter();
const { isAuthenticated } = useAuth();

const navHeaderRef = ref(null);
const cartItems = ref([]);
const addingToCart = ref(false);
const addingToCartProductId = ref(null);
const addingToCartModal = ref(false);
const show3D = ref(false);
const showProductModal = ref(false);
const selectedProduct = ref(null);
const selectedImageIndex = ref(0);
const quantity = ref(1);
const showAuthModal = ref(false);
const pendingAction = ref(null);
const sortBy = ref("created_at_desc");
const isLoading = ref(false);
const isLoadingMessage = ref("");
const products = ref([]);

// Live stats for the open product modal
const productStats = ref({
  average_rating: 0,
  total_reviews: 0,
  sold_count: 0,
});
const productStatsLoading = ref(false);

const vendors = ref([]);
const selectedVendor = ref(null);
const vendorSearch = ref("");
const loadingVendors = ref(false);
const vendorPagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 8,
  total: 0,
});

const filterOptions = ref({
  categories: [],
  varieties: [],
  colors: [],
  occasions: [],
  seasons: ["all-year", "spring", "summer", "autumn", "winter"],
  price_range: { min: 0, max: 100 },
});

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 12,
  total: 0,
});
const totalCustomers = ref(930);
const openFilters = ref({
  category: false,
  color: false,
  price: false,
  occasion: false,
  season: false,
  variety: false,
  availability: false,
});
const selectedFilters = ref({
  category: [],
  color: [],
  priceMin: null,
  priceMax: null,
  occasion: [],
  season: [],
  variety: [],
  inStockOnly: true,
});
const priceSliderValue = ref(50);
const countdown = ref({ days: 4, hours: 14, minutes: 48, seconds: 18 });
let countdownInterval = null;
let vendorSearchTimeout = null;

// ── Computed ──────────────────────────────────────────────────────────────
const cartCount = computed(() => cartItems.value.length);
const priceRangeText = computed(() =>
  selectedFilters.value.priceMin !== null &&
  selectedFilters.value.priceMax !== null
    ? `₱${selectedFilters.value.priceMin} - ₱${selectedFilters.value.priceMax}`
    : "All",
);
const availabilityText = computed(() =>
  selectedFilters.value.inStockOnly ? "In Stock Only" : "All",
);

// ── Star helper ───────────────────────────────────────────────────────────
function getStarClass(n, rating) {
  if (n <= Math.floor(rating)) return "star-full";
  if (n - rating < 1 && rating % 1 >= 0.5) return "star-half";
  return "star-empty";
}

// ── Live stats (fetched when modal opens) ─────────────────────────────────
async function fetchProductStats(productId) {
  productStatsLoading.value = true;
  productStats.value = { average_rating: 0, total_reviews: 0, sold_count: 0 };
  try {
    // Reuse the reviews endpoint — it returns summary with average_rating,
    // total_reviews, sold_count and breakdown
    const res = await api.get(`products/${productId}/reviews`, {
      params: { per_page: 1 },
    });
    if (res.data.success !== false) {
      const s = res.data.summary ?? res.data.data?.summary ?? {};
      productStats.value = {
        average_rating: s.average_rating ?? 0,
        total_reviews: s.total_reviews ?? 0,
        sold_count: s.sold_count ?? 0,
      };
    }
  } catch (e) {
    console.error("fetchProductStats:", e);
  } finally {
    productStatsLoading.value = false;
  }
}

function refreshProductStats() {
  if (selectedProduct.value) fetchProductStats(selectedProduct.value.id);
}

// ── Auth watcher ──────────────────────────────────────────────────────────
watch(isAuthenticated, async (val) => {
  if (val) {
    await loadCart();
    if (pendingAction.value) {
      const { action, product, qty, callback } = pendingAction.value;
      pendingAction.value = null;
      clearPendingActionFromStorage();
      if (callback) callback();
      else await executePendingAction(action, product, qty);
    } else {
      await loadPendingActionFromStorage();
    }
  } else {
    cartItems.value = [];
    clearPendingActionFromStorage();
  }
});

// ── Vendors ───────────────────────────────────────────────────────────────
const fetchVendors = async () => {
  try {
    loadingVendors.value = true;
    const params = {
      page: vendorPagination.value.current_page,
      per_page: vendorPagination.value.per_page,
    };
    if (vendorSearch.value) params.search = vendorSearch.value;
    const r = await api.get("vendors", { params });
    if (r.data.success) {
      const vendorData = Array.isArray(r.data.data?.data) ? r.data.data.data : [];
      vendors.value = vendorData;
      vendorPagination.value = {
        current_page: r.data.data?.current_page ?? 1,
        last_page: r.data.data?.last_page ?? 1,
        per_page: r.data.data?.per_page ?? vendorData.length,
        total: r.data.data?.total ?? vendorData.length,
      };
    }
  } catch (e) {
    console.error(e);
    vendors.value = [];
  } finally {
    loadingVendors.value = false;
  }
};

const debouncedFetchVendors = () => {
  clearTimeout(vendorSearchTimeout);
  vendorSearchTimeout = setTimeout(() => {
    vendorPagination.value.current_page = 1;
    fetchVendors();
  }, 350);
};
const clearVendorSearch = () => {
  vendorSearch.value = "";
  vendorPagination.value.current_page = 1;
  fetchVendors();
};
const prevVendorPage = () => {
  if (vendorPagination.value.current_page > 1) {
    vendorPagination.value.current_page--;
    fetchVendors();
  }
};
const nextVendorPage = () => {
  if (vendorPagination.value.current_page < vendorPagination.value.last_page) {
    vendorPagination.value.current_page++;
    fetchVendors();
  }
};
const selectVendor = (vendor) => {
  if (selectedVendor.value?.id === vendor.id) {
    clearVendorFilter();
    return;
  }
  selectedVendor.value = vendor;
  pagination.value.current_page = 1;
  fetchProducts();
  document.getElementById("products")?.scrollIntoView({ behavior: "smooth" });
};
const selectVendorById = async (vendorId) => {
  if (!vendorId) return;
  try {
    const r = await api.get(`vendors/${vendorId}`);
    if (r.data?.success && r.data.data) {
      selectVendor(r.data.data);
    }
  } catch (e) {
    console.error(e);
  }
};
const clearVendorFilter = () => {
  selectedVendor.value = null;
  pagination.value.current_page = 1;
  fetchProducts();
};
const goToStorefront = (id) => router.push(`/store/${id}`);
const handleVendorLogoError = (e) => {
  e.target.style.display = "none";
};

// ── Filters ───────────────────────────────────────────────────────────────
const toggleFilter = (f) => {
  openFilters.value[f] = !openFilters.value[f];
};
const onPriceChange = () => {
  clearTimeout(window.priceTimeout);
  window.priceTimeout = setTimeout(() => fetchProducts(), 500);
};
const onSliderChange = () => {
  const r = filterOptions.value.price_range;
  if (!r) return;
  selectedFilters.value.priceMax = Math.round(
    (priceSliderValue.value / 100) * r.max,
  );
  selectedFilters.value.priceMin = Math.round(
    (priceSliderValue.value / 50) * r.max,
  );
  if (selectedFilters.value.priceMin > selectedFilters.value.priceMax)
    selectedFilters.value.priceMin = selectedFilters.value.priceMax - 10;
  fetchProducts();
};
const onSortChange = () => fetchProducts();

const buildQueryParams = () => {
  const p = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page,
  };
  if (selectedFilters.value.category.length)
    p.category = selectedFilters.value.category.join(",");
  if (selectedFilters.value.color.length)
    p.color = selectedFilters.value.color.join(",");
  if (selectedFilters.value.variety.length)
    p.variety = selectedFilters.value.variety.join(",");
  if (selectedFilters.value.season.length)
    p.season = selectedFilters.value.season.join(",");
  if (selectedFilters.value.occasion.length)
    p.occasion = selectedFilters.value.occasion.join(",");
  if (selectedFilters.value.priceMin !== null)
    p.min_price = selectedFilters.value.priceMin;
  if (selectedFilters.value.priceMax !== null)
    p.max_price = selectedFilters.value.priceMax;
  p.in_stock_only = selectedFilters.value.inStockOnly ? 1 : 0;
  p.sort_by = sortBy.value;
  return p;
};

// ── Products ──────────────────────────────────────────────────────────────
const fetchProducts = async () => {
  try {
    isLoading.value = true;
    isLoadingMessage.value = "Preparing our products...";
    const params = buildQueryParams();
    let endpoint = "customer/products";
    if (selectedVendor.value) {
      endpoint = `vendors/${selectedVendor.value.id}/products`;
      const m = {
        created_at_desc: "newest",
        price_low: "price-low",
        price_high: "price-high",
        name_asc: "newest",
        name_desc: "newest",
      };
      params.sort_by = m[sortBy.value] ?? "newest";
      if (selectedFilters.value.category.length)
        params.category = selectedFilters.value.category[0];
      if (selectedFilters.value.occasion.length)
        params.occasion = selectedFilters.value.occasion[0];
    }
    const r = await api.get(endpoint, { params });
    if (r.data.success) {
      if (selectedVendor.value) {
        products.value = r.data.data;
        pagination.value = {
          current_page: 1,
          last_page: 1,
          per_page: r.data.data.length,
          total: r.data.data.length,
        };
      } else {
        products.value = r.data.data.data;
        pagination.value = {
          current_page: r.data.data.current_page,
          last_page: r.data.data.last_page,
          per_page: r.data.data.per_page,
          total: r.data.data.total,
        };
      }
      totalCustomers.value = Math.max(930, pagination.value.total * 3);
    }
  } catch (e) {
    console.error(e);
    pagination.value.total = products.value.length;
  } finally {
    isLoading.value = false;
  }
};

const fetchFilterOptions = async () => {
  try {
    const r = await productService.getFilterOptions();
    if (r.success) {
      filterOptions.value = r.data;
      if (r.data.price_range) {
        selectedFilters.value.priceMax = Math.round(r.data.price_range.max);
        priceSliderValue.value = 50;
      }
    }
  } catch (e) {}
};
const loadCart = async () => {
  if (!isAuthenticated.value) return;
  try {
    const r = await cartService.getCart();
    if (r.success) cartItems.value = r.data.items || [];
  } catch (e) {}
};
const clearAllFilters = () => {
  selectedFilters.value = {
    category: [],
    color: [],
    priceMin: null,
    priceMax: null,
    occasion: [],
    season: [],
    variety: [],
    inStockOnly: true,
  };
  priceSliderValue.value = 50;
  fetchProducts();
};

// ── Product helpers ───────────────────────────────────────────────────────
const getProductImage = (product) => {
  if (product?.primary_image?.image_url) return product.primary_image.image_url;
  if (product?.images?.length > 0) {
    const p = product.images.find((i) => i.is_primary);
    return p?.image_url ?? product.images[0].image_url;
  }
  const imgs = [
    "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=300&h=300&fit=crop",
    "https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=300&h=300&fit=crop",
    "https://images.unsplash.com/photo-1588423865281-316e8eab60ee?w=300&h=300&fit=crop",
    "https://images.unsplash.com/photo-1518709594023-6eab9bab7b23?w=300&h=300&fit=crop",
  ];
  return imgs[(product.id ?? 0) % imgs.length];
};
const handleImageError = (e) => {
  e.target.src =
    "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=300&h=300&fit=crop";
};
const formatPrice = (p) => (!p ? "0.00" : parseFloat(p).toFixed(2));
const calculateDiscountPercentage = (product) => {
  if (!product.discount_price || !product.selling_price) return 0;
  const original = parseFloat(product.selling_price);
  const sale = parseFloat(product.discount_price);
  if (sale >= original) return 0;
  return Math.round(((original - sale) / original) * 100);
};
const getStockStatusClass = (p) =>
  p.quantity_in_stock <= 0
    ? "out-of-stock"
    : p.quantity_in_stock <= 10
      ? "low-stock"
      : "in-stock";
const getStockStatusText = (p) =>
  p.quantity_in_stock <= 0
    ? "Out of Stock"
    : p.quantity_in_stock <= 10
      ? "Low Stock"
      : "In Stock";
const truncateDescription = (text, length = 60) =>
  !text ? "" : text.length > length ? text.substring(0, length) + "..." : text;

// ── Pagination ────────────────────────────────────────────────────────────
const prevPage = () => {
  if (pagination.value.current_page > 1) {
    pagination.value.current_page--;
    fetchProducts();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }
};
const nextPage = () => {
  if (pagination.value.current_page < pagination.value.last_page) {
    pagination.value.current_page++;
    fetchProducts();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }
};

// ── Auth helpers ──────────────────────────────────────────────────────────
const requireAuth = (action, product = null, qty = 1, callback = null) => {
  if (isAuthenticated.value) return true;
  pendingAction.value = {
    action,
    product,
    qty,
    callback: callback || (() => executePendingAction(action, product, qty)),
  };
  savePendingActionToStorage();
  showAuthModal.value = true;
  return false;
};
const executePendingAction = async (action, product, qty) => {
  if (action === "addToCart") await addToCartDirect(product, qty);
  if (action === "addToCartModal") await addToCartModalAction(product, qty);
  if (action === "buyNow") await buyNowAction(product, qty);
};
const addToCartModalAction = async (product, qty) => {
  if (!product) return;
  addingToCartModal.value = true;
  const s = await addToCartDirect(product, qty);
  if (s) setTimeout(() => closeModal(), 800);
  addingToCartModal.value = false;
};
const buyNowAction = async (product, qty) => {
  addingToCartModal.value = true;
  try {
    sessionStorage.setItem(
      "directCheckout",
      JSON.stringify({
        items: [
          {
            product_id: product.id,
            quantity: qty,
            product_name: product.product_name,
            price: product.selling_price,
            image: getProductImage(product),
          },
        ],
        total: parseFloat(product.selling_price) * qty,
        isDirectCheckout: true,
      }),
    );
    closeModal();
    router.push({
      path: "/customer/checkout",
      query: { direct: "true", product_id: product.id, quantity: qty },
    });
  } catch (e) {
    toast.error("Failed to process order.", {
      autoClose: 3000,
      position: toast.POSITION.TOP_RIGHT,
    });
  } finally {
    addingToCartModal.value = false;
  }
};
const savePendingActionToStorage = () => {
  if (pendingAction.value?.product)
    localStorage.setItem(
      "pendingAction",
      JSON.stringify({
        action: pendingAction.value.action,
        productId: pendingAction.value.product.id,
        qty: pendingAction.value.qty,
        timestamp: Date.now(),
      }),
    );
};
const loadPendingActionFromStorage = async () => {
  const s = localStorage.getItem("pendingAction");
  if (!s) return;
  try {
    const { action, productId, qty, timestamp } = JSON.parse(s);
    if (Date.now() - timestamp > 86400000) {
      localStorage.removeItem("pendingAction");
      return;
    }
    if (productId) {
      const r = await productService.getProductById(productId);
      if (r.success) {
        pendingAction.value = {
          action,
          product: r.data,
          qty,
          callback: () => executePendingAction(action, r.data, qty),
        };
        await executePendingAction(action, r.data, qty);
      }
    }
  } catch (e) {}
};
const clearPendingActionFromStorage = () =>
  localStorage.removeItem("pendingAction");
const goToLogin = () => {
  const p = router.currentRoute.value.fullPath;
  showAuthModal.value = false;
  router.push({ path: "/guest/login", query: { redirect: p } });
};
const goToRegister = () => {
  const p = router.currentRoute.value.fullPath;
  showAuthModal.value = false;
  router.push({ path: "/guest/register", query: { redirect: p } });
};
const closeAuthModal = () => {
  showAuthModal.value = false;
  pendingAction.value = null;
  clearPendingActionFromStorage();
};
const scrollToSection = (id) => {
  const el = document.getElementById(id);
  if (el) el.scrollIntoView({ behavior: "smooth" });
};

// ── Modal ─────────────────────────────────────────────────────────────────
const selectImage = (i) => {
  selectedImageIndex.value = i;
};

const openProductModal = async (product) => {
  selectedProduct.value = product;
  quantity.value = 1;
  selectedImageIndex.value = 0;
  show3D.value = !!(product.models?.length > 0);
  showProductModal.value = true;
  document.body.style.overflow = "hidden";
  // Fetch live stats in the background
  fetchProductStats(product.id);
};
const closeModal = () => {
  showProductModal.value = false;
  selectedProduct.value = null;
  show3D.value = false;
  document.body.style.overflow = "auto";
};
const increaseQuantity = () => {
  if (
    selectedProduct.value &&
    selectedProduct.value.quantity_in_stock > quantity.value
  )
    quantity.value++;
};
const decreaseQuantity = () => {
  if (quantity.value > 1) quantity.value--;
};
const addToCartModal = async () => {
  if (!selectedProduct.value) return;
  if (!requireAuth("addToCartModal", selectedProduct.value, quantity.value))
    return;
  await addToCartModalAction(selectedProduct.value, quantity.value);
};
const addToCartDirect = async (product, qty = 1) => {
  if (!isAuthenticated.value) {
    requireAuth("addToCart", product, qty);
    return false;
  }
  addingToCartProductId.value = product.id;
  addingToCart.value = true;
  try {
    const r = await cartService.addToCart({
      product_id: product.id,
      quantity: qty,
      color: product.color || null,
      size: product.size || null,
      customizations: {},
    });
    if (r.success) {
      await loadCart();
      if (navHeaderRef.value) navHeaderRef.value.triggerCartPulse();
      toast.success(`${product.product_name} added to cart!`, {
        autoClose: 2000,
        position: toast.POSITION.TOP_RIGHT,
      });
      return true;
    }
  } catch (e) {
    toast.error("Failed to add item to cart", {
      autoClose: 3000,
      position: toast.POSITION.TOP_RIGHT,
    });
    return false;
  } finally {
    addingToCart.value = false;
    addingToCartProductId.value = null;
  }
};
const buyNow = async () => {
  if (!selectedProduct.value) return;
  if (!requireAuth("buyNow", selectedProduct.value, quantity.value)) return;
  await buyNowAction(selectedProduct.value, quantity.value);
};

// ── Countdown ─────────────────────────────────────────────────────────────
const updateCountdown = () => {
  if (countdown.value.seconds > 0) countdown.value.seconds--;
  else if (countdown.value.minutes > 0) {
    countdown.value.minutes--;
    countdown.value.seconds = 59;
  } else if (countdown.value.hours > 0) {
    countdown.value.hours--;
    countdown.value.minutes = 59;
    countdown.value.seconds = 59;
  } else if (countdown.value.days > 0) {
    countdown.value.days--;
    countdown.value.hours = 23;
    countdown.value.minutes = 59;
    countdown.value.seconds = 59;
  }
};

onMounted(async () => {
  countdownInterval = setInterval(updateCountdown, 1000);
  await Promise.all([fetchFilterOptions(), fetchProducts(), fetchVendors()]);
  if (isAuthenticated.value) {
    await loadCart();
  } else {
    await loadPendingActionFromStorage();
  }
});
onUnmounted(() => {
  if (countdownInterval) clearInterval(countdownInterval);
  if (vendorSearchTimeout) clearTimeout(vendorSearchTimeout);
  document.body.style.overflow = "auto";
});
</script>

<style scoped>
* {
  font-family: "Poppins", sans-serif;
}
.shop-page {
  width: 100%;
  overflow-x: hidden;
}

/* Auth modal */
.auth-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 20px;
}
.auth-modal-content {
  background: white;
  border-radius: 12px;
  max-width: 400px;
  width: 100%;
  padding: 30px;
  position: relative;
  animation: modalSlideIn 0.3s ease;
}
@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.auth-modal-close {
  position: absolute;
  top: 15px;
  right: 15px;
  background: none;
  border: none;
  font-size: 20px;
  color: #718096;
  cursor: pointer;
}
.auth-modal-body {
  text-align: center;
  padding: 20px 0;
}
.auth-modal-icon {
  font-size: 48px;
  margin-bottom: 20px;
}
.auth-modal-body h3 {
  font-size: 24px;
  font-weight: 600;
  margin-bottom: 12px;
  color: #2d3748;
}
.auth-modal-body p {
  color: #718096;
  margin-bottom: 24px;
  line-height: 1.6;
}
.auth-modal-actions {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}
.btn-login-modal {
  flex: 1;
  padding: 12px 24px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
}
.btn-login-modal:hover {
  background: #38a169;
}
.btn-register-modal {
  flex: 1;
  padding: 12px 24px;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
}
.auth-modal-note {
  font-size: 14px;
  color: #a0aec0;
}
.btn-continue {
  background: none;
  border: none;
  color: #48bb78;
  font-weight: 500;
  cursor: pointer;
  padding: 4px 8px;
}
.guest-notice {
  margin-top: 16px;
  padding: 14px;
  background: #f0fff4;
  border-radius: 8px;
  border: 1px solid #c6f6d5;
}
.guest-notice p {
  color: #276749;
  font-size: 13px;
  margin-bottom: 10px;
}
.guest-actions {
  display: flex;
  gap: 10px;
}
.btn-guest-login {
  flex: 1;
  padding: 7px 14px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}
.btn-guest-register {
  flex: 1;
  padding: 7px 14px;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

/* Hero */
.hero {
  margin-top: 80px;
  padding: 60px 5%;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 60px;
  align-items: center;
  background: linear-gradient(135deg, #f0f4ff 0%, #fef5ff 100%);
}
.hero-content h1 {
  font-size: 48px;
  font-weight: 400;
  line-height: 1.2;
  margin-bottom: 20px;
  color: #2d3748;
}
.hero-content .highlight {
  color: #48bb78;
  font-weight: 600;
}
.hero-content p {
  font-size: 16px;
  color: #718096;
  margin-bottom: 32px;
  max-width: 500px;
}
.hero-buttons {
  display: flex;
  gap: 16px;
  margin-bottom: 32px;
}
.btn-primary {
  padding: 14px 32px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}
.btn-primary:hover {
  background: #553c9a;
  transform: translateY(-2px);
}
.btn-secondary {
  padding: 14px 32px;
  background: transparent;
  color: #2d3748;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}
.ratings {
  display: flex;
  align-items: center;
  gap: 16px;
}
.customer-avatars {
  display: flex;
  align-items: center;
}
.avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 2px solid white;
  margin-left: -12px;
}
.avatar:first-child {
  margin-left: 0;
}
.avatar-more {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #48bb78;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  border: 2px solid white;
  margin-left: -12px;
}
.rating-text {
  font-size: 14px;
}
.stars {
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 4px;
}
.rating-text p {
  color: #718096;
  font-size: 13px;
  margin: 0;
}
.hero-image {
  position: relative;
  height: 500px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.hero-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 16px;
}
.badge {
  position: absolute;
  padding: 12px 20px;
  background: white;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.badge-secure {
  top: 20px;
  right: 20px;
  color: #48bb78;
}
.badge-delivery {
  bottom: 20px;
  left: 20px;
  color: #2d3748;
}

/* Vendors */
.vendors-section {
  padding: 60px 5% 40px;
  background: #fff;
  border-bottom: 1px solid #f0f0f0;
}
.vendors-container {
  max-width: 1400px;
  margin: 0 auto;
}
.vendors-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 28px;
  gap: 20px;
  flex-wrap: wrap;
}
.vendors-header h2 {
  font-size: 28px;
  font-weight: 500;
  color: #2d3748;
  margin: 0;
}
.vendor-search-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #f7fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 16px;
  min-width: 260px;
  transition: border-color 0.2s;
}
.vendor-search-bar:focus-within {
  border-color: #48bb78;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}
.vendor-search-icon {
  font-size: 16px;
  flex-shrink: 0;
}
.vendor-search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  color: #2d3748;
  flex: 1;
}
.vendor-search-input::placeholder {
  color: #a0aec0;
}
.vendor-search-clear {
  background: none;
  border: none;
  cursor: pointer;
  color: #a0aec0;
  font-size: 14px;
  padding: 0;
  line-height: 1;
}
.vendors-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}
.vendor-card {
  background: #fff;
  border: 1.5px solid #e2e8f0;
  border-radius: 14px;
  padding: 18px;
  cursor: pointer;
  transition: all 0.25s;
  display: flex;
  flex-direction: column;
  gap: 12px;
  position: relative;
  overflow: hidden;
}
.vendor-card::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(72, 187, 120, 0.04), transparent);
  opacity: 0;
  transition: opacity 0.25s;
  pointer-events: none;
}
.vendor-card:hover {
  border-color: #48bb78;
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.12);
  transform: translateY(-2px);
}
.vendor-card:hover::before {
  opacity: 1;
}
.vendor-card-active {
  border-color: #48bb78;
  background: #f0fff4;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.2);
}
.vendor-card-skeleton {
  cursor: default;
  pointer-events: none;
}
.skeleton {
  background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  border-radius: 6px;
}
@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
.skeleton-logo {
  width: 52px;
  height: 52px;
  border-radius: 10px;
}
.skeleton-title {
  height: 14px;
  width: 70%;
  margin-bottom: 8px;
}
.skeleton-desc {
  height: 12px;
  width: 90%;
}
.vendor-card-logo {
  width: 52px;
  height: 52px;
  border-radius: 10px;
  overflow: hidden;
  background: #f7fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid #e2e8f0;
}
.vendor-card-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.vendor-logo-fallback {
  font-size: 24px;
}
.vendor-card-info {
  flex: 1;
}
.vendor-card-title-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}
.vendor-card-name {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
  line-height: 1.3;
}
.vendor-verified-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  background: #3182ce;
  color: white;
  border-radius: 50%;
  font-size: 9px;
  font-weight: 700;
  flex-shrink: 0;
}
.vendor-card-desc {
  font-size: 12px;
  color: #718096;
  margin: 0 0 8px;
  line-height: 1.4;
}
.vendor-card-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.vendor-meta-tag {
  font-size: 11px;
  font-weight: 500;
  padding: 3px 8px;
  border-radius: 20px;
}
.tag-sameday {
  background: #ebf8ff;
  color: #2b6cb0;
}
.tag-price {
  background: #f0fff4;
  color: #276749;
}
.btn-view-store {
  background: none;
  border: 1.5px solid #48bb78;
  color: #276749;
  border-radius: 8px;
  padding: 7px 12px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  text-align: center;
}
.btn-view-store:hover {
  background: #48bb78;
  color: white;
}
.no-vendors {
  text-align: center;
  padding: 40px;
  color: #a0aec0;
}
.no-vendors-icon {
  font-size: 40px;
  display: block;
  margin-bottom: 12px;
}
.vendors-pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 20px;
  margin-top: 8px;
}
.active-vendor-filter {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #f0fff4;
  border: 1.5px solid #48bb78;
  border-radius: 10px;
  padding: 10px 12px;
  margin-bottom: 16px;
}
.active-vendor-logo {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  overflow: hidden;
  background: #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.active-vendor-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.vendor-logo-placeholder {
  font-size: 18px;
}
.active-vendor-info {
  flex: 1;
  min-width: 0;
}
.active-vendor-label {
  font-size: 10px;
  color: #68d391;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0;
  font-weight: 600;
}
.active-vendor-name {
  font-size: 13px;
  font-weight: 600;
  color: #276749;
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.btn-clear-vendor {
  background: none;
  border: none;
  cursor: pointer;
  color: #48bb78;
  font-size: 16px;
  padding: 2px;
  flex-shrink: 0;
}

/* Products section */
.products-section {
  padding: 80px 5%;
  background: #f7fafc;
}
.products-container {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 32px;
  max-width: 1400px;
  margin: 0 auto;
}
.filter-sidebar {
  background: white;
  border-radius: 12px;
  padding: 24px;
  height: fit-content;
  position: sticky;
  top: 100px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}
.filter-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.filter-header h2 {
  font-size: 24px;
  font-weight: 500;
  color: #2d3748;
  margin: 0;
}
.btn-clear-all {
  background: none;
  border: none;
  color: #7c3aed;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
}
.filter-subtitle {
  font-size: 13px;
  color: #9ca3af;
  margin-bottom: 24px;
}
.filter-group {
  border-bottom: 1px solid #f3f4f6;
  padding: 16px 0;
}
.filter-group:last-child {
  border-bottom: none;
}
.filter-toggle {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  text-align: left;
}
.filter-label {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 15px;
  color: #2d3748;
  font-weight: 500;
}
.filter-icon {
  font-size: 18px;
  width: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.filter-value {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #6b7280;
  max-width: 120px;
  text-overflow: ellipsis;
  overflow: hidden;
  white-space: nowrap;
}
.chevron {
  font-size: 18px;
  color: #9ca3af;
  transition: transform 0.3s;
}
.chevron.open {
  transform: rotate(90deg);
}
.filter-options {
  padding: 16px 0 0 36px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  animation: slideDown 0.3s ease;
}
@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: #4b5563;
  cursor: pointer;
  padding: 4px 0;
}
.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: #48bb78;
}
.price-range {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.price-inputs {
  display: flex;
  align-items: center;
  gap: 8px;
}
.price-input {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font-size: 14px;
  color: #2d3748;
}
.price-separator {
  color: #9ca3af;
}
.no-options {
  color: #9ca3af;
  font-size: 14px;
  padding: 8px 0;
}
.price-slider {
  margin-top: 16px;
}
.slider {
  width: 100%;
  height: 6px;
  border-radius: 3px;
  background: #e5e7eb;
  outline: none;
  opacity: 0.7;
  transition: opacity 0.2s;
}
.slider:hover {
  opacity: 1;
}
.slider::-webkit-slider-thumb {
  appearance: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #48bb78;
  cursor: pointer;
}
.slider::-moz-range-thumb {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #48bb78;
  cursor: pointer;
}
.products-main {
  display: flex;
  flex-direction: column;
  gap: 32px;
}
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.section-subtitle {
  color: #48bb78;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 8px;
}
.section-header h2 {
  font-size: 32px;
  font-weight: 500;
  color: #2d3748;
  margin: 0;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.sort-controls {
  display: flex;
  align-items: center;
  gap: 16px;
}
.results-count {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}
.sort-select {
  padding: 10px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  color: #2d3748;
  background: white;
  cursor: pointer;
}
.btn-clear-store-inline {
  background: none;
  border: 1.5px solid #48bb78;
  color: #48bb78;
  border-radius: 20px;
  padding: 4px 14px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}
.btn-clear-store-inline:hover {
  background: #48bb78;
  color: white;
}
.product-vendor-tag {
  font-size: 12px;
  color: #48bb78;
  font-weight: 500;
  cursor: pointer;
  margin: 0 0 4px;
  transition: color 0.2s;
}
.product-vendor-tag:hover {
  color: #276749;
  text-decoration: underline;
}
.no-results {
  text-align: center;
  padding: 80px 20px;
  background: white;
  border-radius: 12px;
}
.no-results-icon {
  font-size: 64px;
  margin-bottom: 16px;
}
.no-results h3 {
  font-size: 24px;
  font-weight: 500;
  color: #2d3748;
  margin-bottom: 8px;
}
.no-results p {
  font-size: 16px;
  color: #9ca3af;
  margin-bottom: 24px;
}
.btn-clear-filters {
  padding: 12px 24px;
  background: #7c3aed;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}
.products-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}
.product-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  transition: all 0.3s;
}
.product-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}
.product-image {
  position: relative;
  width: 100%;
  height: 240px;
  overflow: hidden;
}
.product-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.badge-discount {
  position: absolute;
  top: 12px;
  right: 12px;
  padding: 6px 12px;
  background: #e53e3e;
  color: white;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}
.product-actions {
  position: absolute;
  top: 12px;
  left: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.icon-btn {
  width: 36px;
  height: 36px;
  background: white;
  border: none;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.3s;
}
.icon-btn:hover {
  background: #f7fafc;
  transform: scale(1.1);
}
.product-rating {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 14px;
  color: #2d3748;
  font-weight: 500;
  margin-bottom: 8px;
}
.product-review-count {
  font-size: 12px;
  color: #a0aec0;
  font-weight: 400;
}
.product-category {
  font-size: 13px;
  color: #718096;
  margin-bottom: 4px;
}
.product-name {
  font-size: 16px;
  font-weight: 500;
  color: #2d3748;
  margin-bottom: 12px;
}
.product-description {
  font-size: 14px;
  color: #718096;
  margin-bottom: 12px;
  line-height: 1.4;
}
.product-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.product-price {
  display: flex;
  align-items: center;
  gap: 8px;
}
.price-current {
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
}
.price-original {
  font-size: 14px;
  color: #a0aec0;
  text-decoration: line-through;
}
.stock-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
}
.stock-badge.in-stock {
  background: #d1fae5;
  color: #065f46;
}
.stock-badge.low-stock {
  background: #fef3c7;
  color: #92400e;
}
.stock-badge.out-of-stock {
  background: #fee2e2;
  color: #991b1b;
}
.product-actions-bottom {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-top: 12px;
}
.btn-view-details {
  height: 40px;
  padding: 0 16px;
  background: white;
  color: #2d3748;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-view-details:hover {
  background: #f7fafc;
}
.btn-add-to-cart {
  height: 40px;
  padding: 0 16px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-add-to-cart:hover:not(:disabled) {
  background: #38a169;
  transform: translateY(-2px);
}
.btn-add-to-cart:disabled {
  background: #cbd5e0;
  cursor: not-allowed;
}
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 24px;
  margin-top: 40px;
  padding: 20px;
}
.pagination-btn {
  padding: 10px 20px;
  background: white;
  color: #2d3748;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}
.pagination-btn:hover:not(:disabled) {
  background: #f7fafc;
}
.pagination-btn:disabled {
  color: #cbd5e0;
  cursor: not-allowed;
}
.pagination-info {
  font-size: 14px;
  color: #718096;
}
.promo-section {
  padding: 80px 5%;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 32px;
  max-width: 1400px;
  margin: 0 auto;
}
.promo-card {
  border-radius: 16px;
  padding: 40px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 32px;
  align-items: center;
}
.promo-primary {
  background: linear-gradient(135deg, #f0f4ff 0%, #e6e6fa 100%);
}
.promo-secondary {
  background: linear-gradient(135deg, #48bb78 0%, #553c9a 100%);
  color: white;
}
.promo-badge {
  display: inline-block;
  padding: 6px 16px;
  background: rgba(255, 255, 255, 0.9);
  color: #48bb78;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 16px;
}
.promo-secondary .promo-badge {
  background: rgba(255, 255, 255, 0.2);
  color: white;
}
.promo-content h3 {
  font-size: 32px;
  font-weight: 500;
  margin-bottom: 12px;
}
.promo-content p {
  font-size: 14px;
  margin-bottom: 24px;
  opacity: 0.8;
}
.btn-promo {
  padding: 12px 28px;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}
.promo-secondary .btn-promo {
  background: white;
  color: #48bb78;
}
.promo-image {
  height: 250px;
  border-radius: 12px;
  overflow: hidden;
}
.promo-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.holiday-sales {
  padding: 80px 5%;
  text-align: center;
  background: linear-gradient(135deg, #f0f4ff 0%, #fef5ff 100%);
}
.holiday-sales h2 {
  font-size: 36px;
  font-weight: 500;
  color: #2d3748;
  margin-bottom: 12px;
}
.holiday-sales > p {
  font-size: 16px;
  color: #718096;
  margin-bottom: 40px;
}
.countdown {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-bottom: 40px;
}
.countdown-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.countdown-number {
  font-size: 48px;
  font-weight: 600;
  color: #2d3748;
  line-height: 1;
  margin-bottom: 8px;
}
.countdown-label {
  font-size: 14px;
  color: #718096;
}
.countdown-separator {
  font-size: 36px;
  color: #cbd5e0;
  margin: 0 8px;
}
.btn-shop-now {
  padding: 16px 48px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 500;
  cursor: pointer;
}
.loading-spinner-small {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: white;
  animation: spin-small 0.8s linear infinite;
}
@keyframes spin-small {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

/* ══ ENHANCED PRODUCT MODAL ══ */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}
.modal-content {
  background: white;
  border-radius: 16px;
  width: 100%;
  max-width: 1060px;
  max-height: 92vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.2);
  position: relative;
}
.modal-close {
  position: absolute;
  top: 14px;
  right: 14px;
  width: 32px;
  height: 32px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 50%;
  font-size: 16px;
  cursor: pointer;
  color: #718096;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
  transition: all 0.2s;
}
.modal-close:hover {
  background: #fee2e2;
  color: #e53e3e;
}
.modal-inner {
  display: flex;
  height: 92vh;
  max-height: 92vh;
  overflow: hidden;
}

/* Left image pane */
.modal-left {
  width: 400px;
  flex-shrink: 0;
  background: #f8fafc;
  border-right: 1px solid #f0f2f5;
  display: flex;
  flex-direction: column;
  padding: 20px;
  gap: 12px;
  overflow: hidden;
}
.model-viewer {
  width: 100%;
  height: 340px;
  border-radius: 12px;
}
.image-container {
  display: flex;
  flex-direction: column;
  gap: 10px;
  flex: 1;
  min-height: 0;
}
.main-image {
  width: 100%;
  height: 320px;
  object-fit: contain;
  border-radius: 12px;
  background: #fff;
  border: 2px solid #e2e8f0;
}
.image-thumbnails {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.thumbnail {
  width: 58px;
  height: 58px;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.2s;
  background: #fff;
  flex-shrink: 0;
}
.thumbnail:hover {
  border-color: #cbd5e0;
}
.thumbnail.active {
  border-color: #48bb78;
  box-shadow: 0 0 0 2px rgba(72, 187, 120, 0.2);
}
.thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.view-toggle {
  display: flex;
  gap: 8px;
}
.view-toggle button {
  flex: 1;
  padding: 8px;
  background: white;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  color: #2d3748;
  transition: all 0.2s;
}
.view-toggle button.active {
  background: #48bb78;
  color: white;
  border-color: #48bb78;
}

/* Right scrollable pane */
.modal-right {
  flex: 1;
  overflow-y: auto;
  padding: 24px 24px 24px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  scrollbar-width: thin;
  scrollbar-color: #e2e8f0 transparent;
}
.modal-right::-webkit-scrollbar {
  width: 4px;
}
.modal-right::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 4px;
}
.modal-top-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.modal-category-tag {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #48bb78;
  margin: 0;
}
.modal-product-name {
  font-size: 1.45rem;
  font-weight: 700;
  color: #1a202c;
  margin: 0;
  line-height: 1.25;
}

/* Live stats row */
.modal-stats-row {
  display: flex;
  align-items: center;
  gap: 7px;
  flex-wrap: wrap;
}
.modal-stars {
  display: flex;
  gap: 1px;
}
.modal-star {
  font-size: 15px;
}
.star-full {
  color: #f6ad55;
}
.star-half {
  color: #f6ad55;
  opacity: 0.55;
}
.star-empty {
  color: #d1d5db;
}
.modal-stats-avg {
  font-size: 14px;
  font-weight: 700;
  color: #2d3748;
}
.modal-stats-sep {
  color: #cbd5e0;
  font-size: 13px;
}
.modal-stats-reviews {
  font-size: 13px;
  color: #718096;
}
.modal-stats-sold {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  color: #276749;
  background: #f0fff4;
  border: 1px solid #c6f6d5;
  border-radius: 20px;
  padding: 2px 9px;
}
.modal-stats-skeleton {
  height: 20px;
}
.msk {
  height: 16px;
  border-radius: 6px;
  background: linear-gradient(90deg, #f0f2f5 25%, #e8ecf0 50%, #f0f2f5 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}
.msk--wide {
  width: 220px;
}

/* Price */
.modal-price-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.modal-price-current {
  font-size: 1.7rem;
  font-weight: 700;
  color: #1a202c;
}
.modal-price-original {
  font-size: 1rem;
  color: #a0aec0;
  text-decoration: line-through;
}
.modal-price-badge {
  padding: 4px 10px;
  background: #fef3c7;
  color: #92400e;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
}

/* Description */
.modal-desc-block {
  background: #f8fafc;
  border-radius: 10px;
  padding: 14px 16px;
}
.modal-desc-text {
  font-size: 13px;
  color: #4a5568;
  line-height: 1.65;
  margin: 0;
}

/* Specs (Color + Stock only) */
.modal-specs-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.modal-spec {
  display: flex;
  flex-direction: column;
  gap: 3px;
  background: #f8fafc;
  border: 1px solid #e8ecf2;
  border-radius: 8px;
  padding: 10px 14px;
  min-width: 90px;
}
.modal-spec__label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #a0aec0;
}
.modal-spec__val {
  font-size: 13px;
  font-weight: 600;
  color: #2d3748;
}
.stock-pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}
.stock-pill.in-stock {
  background: #d1fae5;
  color: #065f46;
}
.stock-pill.low-stock {
  background: #fef3c7;
  color: #92400e;
}
.stock-pill.out-of-stock {
  background: #fee2e2;
  color: #991b1b;
}

/* Cart actions */
.modal-cart-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.quantity-selector {
  display: flex;
  align-items: center;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  height: 44px;
  flex-shrink: 0;
}
.qty-btn {
  width: 40px;
  height: 44px;
  background: white;
  border: none;
  color: #2d3748;
  font-size: 18px;
  cursor: pointer;
  transition: background 0.2s;
}
.qty-btn:hover {
  background: #f0fff4;
  color: #48bb78;
}
.qty-value {
  font-size: 15px;
  font-weight: 700;
  color: #2d3748;
  min-width: 32px;
  text-align: center;
}
.btn-add-to-cart-modal {
  flex: 1;
  height: 44px;
  padding: 0 18px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}
.btn-add-to-cart-modal:hover:not(:disabled) {
  background: #38a169;
  transform: translateY(-1px);
}
.btn-add-to-cart-modal:disabled {
  background: #cbd5e0;
  cursor: not-allowed;
}
.btn-buy-now {
  height: 44px;
  padding: 0 18px;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}
.btn-buy-now:hover:not(:disabled) {
  background: #1a202c;
  transform: translateY(-1px);
}
.btn-buy-now:disabled {
  background: #cbd5e0;
  cursor: not-allowed;
}

/* Embedded reviews — your ProductReviews component sits here */
.modal-reviews-embed {
  border-top: 1.5px solid #f0f2f5;
  padding-top: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.modal-reviews-embed__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.modal-reviews-embed__title {
  font-size: 15px;
  font-weight: 700;
  color: #1a202c;
  margin: 0;
}

/* Constrain the pr-wrap list height so it scrolls inside the pane */
.modal-reviews-component :deep(.pr-list) {
  max-height: 320px;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: #e2e8f0 transparent;
}
.modal-reviews-component :deep(.pr-list::-webkit-scrollbar) {
  width: 4px;
}
.modal-reviews-component :deep(.pr-list::-webkit-scrollbar-thumb) {
  background: #e2e8f0;
  border-radius: 4px;
}

/* Responsive */
@media (max-width: 1100px) {
  .modal-left {
    width: 340px;
  }
}
@media (max-width: 860px) {
  .modal-inner {
    flex-direction: column;
    height: auto;
    max-height: none;
    overflow: visible;
  }
  .modal-content {
    max-height: 94vh;
    overflow-y: auto;
  }
  .modal-left {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid #f0f2f5;
  }
  .main-image {
    height: 240px;
  }
  .modal-right {
    overflow: visible;
  }
}
@media (max-width: 768px) {
  .hero {
    grid-template-columns: 1fr;
  }
  .hero-image {
    height: 300px;
  }
  .products-container {
    grid-template-columns: 1fr;
  }
  .filter-sidebar {
    position: static;
  }
  .promo-section {
    grid-template-columns: 1fr;
  }
  .promo-card {
    grid-template-columns: 1fr;
  }
  .vendors-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .vendors-header {
    flex-direction: column;
    align-items: flex-start;
  }
}
@media (max-width: 640px) {
  .hero-content h1 {
    font-size: 32px;
  }
  .products-grid {
    grid-template-columns: 1fr;
  }
  .vendors-grid {
    grid-template-columns: 1fr;
  }
  .modal-cart-row {
    flex-direction: column;
  }
  .btn-add-to-cart-modal,
  .btn-buy-now {
    width: 100%;
  }
}
</style>
