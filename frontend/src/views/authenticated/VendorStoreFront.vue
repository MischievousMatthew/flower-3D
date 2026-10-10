<template>
  <div class="storefront-page">
    <!-- Shared NavHeader (same as Shop.vue) -->
    <NavHeader
      ref="navHeaderRef"
      :cartCount="cartCount"
      :isAuthenticated="isAuthenticated"
      @scroll-to-section="() => {}"
    />

    <!-- Loading skeleton, shaped like the store header and product grid -->
    <div v-if="loading" class="storefront-skeleton" aria-busy="true">
      <span class="sr-only">Loading store…</span>
      <section class="store-hero">
        <div class="bc-skeleton sk-cover"></div>
        <div class="store-hero-inner">
          <div class="store-hero-content">
            <div class="store-logo-wrap">
              <div class="bc-skeleton sk-logo"></div>
            </div>
            <div class="store-info">
              <div class="bc-skeleton sk-line sk-title"></div>
              <div class="bc-skeleton sk-line"></div>
              <div class="bc-skeleton sk-line sk-short"></div>
            </div>
          </div>
          <div class="store-stats">
            <div v-for="n in 4" :key="n" class="bc-skeleton sk-pill"></div>
          </div>
        </div>
      </section>
      <section class="products-section">
        <div class="sk-products products-grid">
          <div v-for="n in 6" :key="n" class="product-card product-skeleton">
            <div class="bc-skeleton skeleton-img"></div>
            <div class="product-info">
              <div class="bc-skeleton skeleton-line short"></div>
              <div class="bc-skeleton skeleton-line"></div>
              <div class="bc-skeleton skeleton-line medium"></div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-screen">
      <div class="error-icon">🌸</div>
      <h2>Store Not Found</h2>
      <p>{{ error }}</p>
      <button @click="$router.push('/shop')" class="btn-primary">
        ← Back to Shop
      </button>
    </div>

    <template v-else-if="vendor.store_name">
      <!-- ── Store Hero Banner ─────────────────────────────────────── -->
      <section class="store-hero">
        <!-- Cover photo (set by the vendor next to their store logo) -->
        <div class="store-cover">
          <img
            v-if="vendor.store_cover_url"
            :src="vendor.store_cover_url"
            alt=""
            class="store-cover-img"
            @error="vendor.store_cover_url = null"
          />
          <button @click="$router.push('/shop')" class="back-link">
            ← Back to Shop
          </button>
        </div>

        <div class="store-hero-inner">
          <div class="store-hero-content">
            <!-- Logo -->
            <div class="store-logo-wrap">
              <img
                v-if="vendor.store_logo_path"
                :src="vendor.store_logo_path"
                :alt="vendor.store_name"
                class="store-logo"
                @error="handleLogoError"
              />
              <div v-else class="store-logo-fallback">
                {{ vendor.store_name?.charAt(0) || "🌸" }}
              </div>
            </div>

            <!-- Info -->
            <div class="store-info">
              <div class="store-title-row">
                <h1 class="store-name">{{ vendor.store_name }}</h1>
                <span
                  v-if="vendor.verification_level === 'verified'"
                  class="verified-badge"
                  >✓ Verified</span
                >
              </div>
              <p class="store-desc">{{ vendor.store_description }}</p>
              <div class="store-meta-row">
                <span v-if="vendor.business_type" class="meta-chip"
                  >🏪 {{ vendor.business_type }}</span
                >
                <span v-if="storeCity" class="meta-chip"
                  >📍 {{ storeCity }}</span
                >
                <span
                  v-if="vendor.same_day_delivery"
                  class="meta-chip chip-green"
                  >⚡ Same-Day Delivery</span
                >
              </div>
            </div>

            <!-- Actions -->
            <div class="store-actions">
              <button @click="chatVendor" class="btn-chat">💬 Chat</button>
              <button @click="customizeFlowers" class="btn-customize">
                Customize
              </button>
            </div>
          </div>

          <!-- Stat Pills -->
          <div class="store-stats">
            <div class="stat-pill">
              <span class="stat-icon">📦</span>
              <div>
                <p class="stat-label">Products</p>
                <p class="stat-value">
                  {{ (vendor.product_types || []).join(", ") || "Various" }}
                </p>
              </div>
            </div>
            <div class="stat-pill">
              <span class="stat-icon">💰</span>
              <div>
                <p class="stat-label">Price Range</p>
                <p
                  class="stat-value"
                  v-if="vendor.price_min || vendor.price_max"
                >
                  ₱{{ Number(vendor.price_min || 0).toLocaleString() }} – ₱{{
                    Number(vendor.price_max || 0).toLocaleString()
                  }}
                </p>
                <p class="stat-value" v-else>See products</p>
              </div>
            </div>
            <div class="stat-pill">
              <span class="stat-icon">🚚</span>
              <div>
                <p class="stat-label">Base Delivery</p>
                <p class="stat-value">
                  ₱{{ vendor.default_delivery_fee || "—" }}
                </p>
              </div>
            </div>
            <div class="stat-pill">
              <span class="stat-icon">📅</span>
              <div>
                <p class="stat-label">Capacity</p>
                <p class="stat-value">
                  {{ vendor.max_orders_per_day || "—" }} orders/day
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Products Section (matches Shop.vue layout exactly) ──────── -->
      <section class="products-section" id="products">
        <div class="products-container">
          <!-- Sidebar Filter -->
          <aside class="filter-sidebar">
            <div class="filter-header">
              <h2>Filter</h2>
              <button class="btn-clear-all" @click="resetFilters">
                Clear All
              </button>
            </div>
            <p class="filter-subtitle">
              Narrow down your searches with our filter.
            </p>

            <!-- Category -->
            <div class="filter-group">
              <button
                class="filter-toggle"
                @click="openFilters.category = !openFilters.category"
              >
                <div class="filter-label">
                  <span class="filter-icon">🏷️</span>
                  <span>Category</span>
                </div>
                <div class="filter-value">
                  {{ filters.category !== "All" ? filters.category : "All" }}
                  <span class="chevron" :class="{ open: openFilters.category }"
                    >›</span
                  >
                </div>
              </button>
              <div v-if="openFilters.category" class="filter-options">
                <label
                  v-for="cat in categories"
                  :key="cat"
                  class="checkbox-label"
                >
                  <input
                    type="radio"
                    :value="cat"
                    v-model="filters.category"
                    @change="applyFilters"
                  />
                  <span>{{ cat }}</span>
                </label>
              </div>
            </div>

            <!-- Flower Type -->
            <div class="filter-group">
              <button
                class="filter-toggle"
                @click="openFilters.flowerType = !openFilters.flowerType"
              >
                <div class="filter-label">
                  <span class="filter-icon">🌸</span>
                  <span>Flower Type</span>
                </div>
                <div class="filter-value">
                  {{
                    filters.flowerType !== "All" ? filters.flowerType : "All"
                  }}
                  <span
                    class="chevron"
                    :class="{ open: openFilters.flowerType }"
                    >›</span
                  >
                </div>
              </button>
              <div v-if="openFilters.flowerType" class="filter-options">
                <label
                  v-for="ft in flowerTypes"
                  :key="ft"
                  class="checkbox-label"
                >
                  <input
                    type="radio"
                    :value="ft"
                    v-model="filters.flowerType"
                    @change="applyFilters"
                  />
                  <span>{{ ft }}</span>
                </label>
              </div>
            </div>

            <!-- Occasion -->
            <div class="filter-group">
              <button
                class="filter-toggle"
                @click="openFilters.occasion = !openFilters.occasion"
              >
                <div class="filter-label">
                  <span class="filter-icon">🎉</span>
                  <span>Occasion</span>
                </div>
                <div class="filter-value">
                  {{ filters.occasion !== "All" ? filters.occasion : "All" }}
                  <span class="chevron" :class="{ open: openFilters.occasion }"
                    >›</span
                  >
                </div>
              </button>
              <div v-if="openFilters.occasion" class="filter-options">
                <label
                  v-for="occ in occasions"
                  :key="occ"
                  class="checkbox-label"
                >
                  <input
                    type="radio"
                    :value="occ"
                    v-model="filters.occasion"
                    @change="applyFilters"
                  />
                  <span>{{ occ }}</span>
                </label>
              </div>
            </div>
          </aside>

          <!-- Products Main -->
          <div class="products-main">
            <!-- Section header with sort — identical to Shop.vue -->
            <div class="section-header">
              <div>
                <p class="section-subtitle">Products</p>
                <h2>{{ vendor.store_name }}'s Collection</h2>
              </div>
              <div class="sort-controls">
                <span class="results-count"
                  >{{ filteredProducts.length }} Products Found</span
                >
                <select v-model="sortBy" class="sort-select">
                  <option value="newest">Newest First</option>
                  <option value="oldest">Oldest First</option>
                  <option value="price-low">Price: Low to High</option>
                  <option value="price-high">Price: High to Low</option>
                  <option value="name_asc">Name: A to Z</option>
                </select>
              </div>
            </div>

            <!-- No products -->
            <div
              v-if="!loadingProducts && filteredProducts.length === 0"
              class="no-results"
            >
              <div class="no-results-icon">🔍</div>
              <h3>No products found</h3>
              <p>Try adjusting your filters.</p>
              <button class="btn-clear-filters" @click="resetFilters">
                Clear Filters
              </button>
            </div>

            <!-- Loading skeletons — matches Shop.vue skeleton style -->
            <div v-else-if="loadingProducts" class="products-grid">
              <div
                v-for="n in 6"
                :key="n"
                class="product-card product-skeleton"
              >
                <div class="bc-skeleton skeleton-img"></div>
                <div class="product-info">
                  <div class="bc-skeleton skeleton-line short"></div>
                  <div class="bc-skeleton skeleton-line"></div>
                  <div class="bc-skeleton skeleton-line medium"></div>
                </div>
              </div>
            </div>

            <!-- Product grid — pixel-identical to Shop.vue product cards -->
            <div v-else class="products-grid">
              <div
                v-for="product in filteredProducts"
                :key="product.id"
                class="product-card"
              >
                <div class="product-image">
                  <img
                    :src="getProductImage(product)"
                    :alt="product.product_name"
                    @error="handleImageError"
                  />
                  <span v-if="product.has_discount" class="badge-discount">
                    SALE
                  </span>
                  <div class="product-actions-overlay">
                    <button
                      class="icon-btn"
                      @click="openModal(product)"
                      title="Quick view"
                    >
                      👁️
                    </button>
                  </div>
                </div>
                <div class="product-info">
                  <div class="product-rating">⭐ <span>4.8</span></div>
                  <p class="product-category">{{ product.category }}</p>
                  <h3 class="product-name">{{ product.product_name }}</h3>
                  <p class="product-description">
                    {{ truncate(product.description) }}
                  </p>
                  <div class="product-footer">
                    <div class="product-price">
                      <span class="price-current"
                        >₱{{ formatPrice(getDisplayPrice(product)) }}</span
                      >
                      <span
                        v-if="product.has_discount && product.discount_price"
                        class="price-original"
                      >
                        ₱{{ formatPrice(product.selling_price) }}
                      </span>
                    </div>
                    <span :class="['stock-badge', stockClass(product)]">
                      {{ stockText(product) }}
                    </span>
                  </div>
                  <div class="product-actions-bottom">
                    <button
                      class="btn-view-details"
                      @click="openModal(product)"
                    >
                      View Details
                    </button>
                    <button
                      class="btn-add-to-cart"
                      :disabled="product.quantity_in_stock === 0"
                      @click="addToCart(product, 1, $event)"
                    >
                      {{
                        product.quantity_in_stock > 0
                          ? "Add to Cart"
                          : "Out of Stock"
                      }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Delivery & Info Section ──────────────────────────────── -->
      <section class="info-section">
        <div class="info-inner">
          <!-- Pinned shop location -->
          <div class="location-card" :class="{ 'no-pin': !hasStorePin }">
            <div v-if="hasStorePin" ref="locationMapEl" class="location-map"></div>
            <div class="location-details">
              <p class="section-subtitle">Visit Us</p>
              <h3 class="location-title">Find the shop</h3>
              <p class="location-address">
                {{ vendor.store_address || "Address not provided" }}
              </p>
              <p v-if="!hasStorePin" class="location-note">
                This shop hasn't pinned its location on the map yet.
              </p>
              <div v-if="hasStorePin" class="location-actions">
                <a
                  :href="directionsUrl"
                  target="_blank"
                  rel="noopener"
                  class="location-btn location-btn-primary"
                  >Get directions</a
                >
                <a
                  :href="openMapUrl"
                  target="_blank"
                  rel="noopener"
                  class="location-btn"
                  >Open in Maps</a
                >
              </div>
            </div>
          </div>

          <div class="info-cards">
            <!-- Same-day delivery -->
            <div v-if="vendor.same_day_delivery" class="info-card card-green">
              <div class="info-card-icon">⚡</div>
              <div>
                <h4>Same-Day Delivery</h4>
                <p>
                  Order before <strong>{{ parsedCutoffTimes }}</strong>
                </p>
                <p class="info-fine">*Subject to courier availability</p>
              </div>
            </div>
            <!-- Service Areas -->
            <div class="info-card">
              <div class="info-card-icon">📍</div>
              <div>
                <h4>Service Areas</h4>
                <div class="area-tags">
                  <span
                    v-for="area in parsedServiceAreas"
                    :key="area"
                    class="area-tag"
                    >{{ area }}</span
                  >
                  <span v-if="!parsedServiceAreas.length" class="text-muted"
                    >Not specified</span
                  >
                </div>
              </div>
            </div>
            <!-- Operating Hours -->
            <div class="info-card">
              <div class="info-card-icon">🕒</div>
              <div>
                <h4>Operating Hours</h4>
                <p>{{ vendor.operating_hours || "Not specified" }}</p>
              </div>
            </div>
            <!-- Lead Time -->
            <div class="info-card">
              <div class="info-card-icon">📋</div>
              <div>
                <h4>Lead Time</h4>
                <p>{{ vendor.lead_time || "—" }}</p>
                <p v-if="vendor.cancellation_policy" class="info-fine">
                  {{ vendor.cancellation_policy }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Portfolio ─────────────────────────────────────────────── -->
      <section
        v-if="(vendor.portfolio_photos_paths || []).length"
        class="portfolio-section"
      >
        <div class="info-inner">
          <p class="section-subtitle">Our Work</p>
          <h2 class="section-h2">Portfolio</h2>
          <div class="portfolio-grid">
            <div
              v-for="(photo, idx) in vendor.portfolio_photos_paths"
              :key="idx"
              class="portfolio-item"
              @click="openGallery(idx)"
            >
              <img :src="photo" :alt="`Portfolio ${idx + 1}`" />
              <div class="portfolio-overlay">🖼️</div>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Contact ───────────────────────────────────────────────── -->
      <section class="contact-section">
        <div class="info-inner">
          <p class="section-subtitle">Get in Touch</p>
          <h2 class="section-h2">Connect With Us</h2>
          <div class="contact-grid">
            <a
              v-if="vendor.facebook_page"
              :href="vendor.facebook_page"
              target="_blank"
              class="contact-card"
            >
              <div class="contact-icon icon-fb">📘</div>
              <div>
                <p class="contact-label">Facebook</p>
                <p class="contact-val">Visit Page</p>
              </div>
            </a>
            <a
              v-if="vendor.instagram_page"
              :href="vendor.instagram_page"
              target="_blank"
              class="contact-card"
            >
              <div class="contact-icon icon-ig">📸</div>
              <div>
                <p class="contact-label">Instagram</p>
                <p class="contact-val">Follow Us</p>
              </div>
            </a>
            <a
              v-if="vendor.contact_number"
              :href="`tel:${vendor.contact_number}`"
              class="contact-card"
            >
              <div class="contact-icon icon-phone">📞</div>
              <div>
                <p class="contact-label">Phone</p>
                <p class="contact-val">{{ vendor.contact_number }}</p>
              </div>
            </a>
            <a
              v-if="vendor.email"
              :href="`mailto:${vendor.email}`"
              class="contact-card"
            >
              <div class="contact-icon icon-email">✉️</div>
              <div>
                <p class="contact-label">Email</p>
                <p class="contact-val">{{ vendor.email }}</p>
              </div>
            </a>
          </div>
        </div>
      </section>

      <!-- ── Footer ────────────────────────────────────────────────── -->
      <footer class="store-footer">
        <p>
          © {{ new Date().getFullYear() }} {{ vendor.store_name }}. All rights
          reserved.
        </p>
      </footer>
    </template>

    <!-- ── Product Quick View Modal (identical to Shop.vue modal) ── -->
    <div
      v-if="showModal && selectedProduct"
      class="modal-overlay"
      @click="closeModal"
    >
      <div class="modal-content product-modal" @click.stop>
        <button class="modal-close" @click="closeModal">✕</button>
        <div class="modal-body">
          <div class="modal-images">
            <!-- 3D Viewer -->
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
            <!-- Photo View -->
            <div v-else class="image-container">
              <img
                ref="selectedProductImageRef"
                :src="
                  selectedProduct.images?.[selectedImageIndex]?.image_url ||
                  getProductImage(selectedProduct)
                "
                :alt="selectedProduct.product_name"
                class="main-image"
                @error="handleImageError"
              />
              <div
                v-if="(selectedProduct.images || []).length > 1"
                class="image-thumbnails"
              >
                <div
                  v-for="(img, idx) in selectedProduct.images"
                  :key="img.id"
                  :class="['thumbnail', { active: selectedImageIndex === idx }]"
                  @click="selectedImageIndex = idx"
                >
                  <img :src="img.image_url" :alt="`Image ${idx + 1}`" />
                </div>
              </div>
            </div>

            <!-- 3D ↔ Photos toggle — only shown when product has both -->
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
          <div class="modal-details">
            <h2>{{ selectedProduct.product_name }}</h2>
            <div class="modal-category">{{ selectedProduct.category }}</div>
            <div class="modal-rating">
              ⭐ 4.8 <span class="reviews">(128 reviews)</span>
            </div>
            <div class="modal-price">
              <span class="current-price"
                >₱{{ formatPrice(getDisplayPrice(selectedProduct)) }}</span
              >
              <span v-if="selectedProduct.has_discount" class="original-price">
                ₱{{ formatPrice(selectedProduct.selling_price) }}
              </span>
            </div>
            <div class="modal-description">
              <h3>Description</h3>
              <p>{{ selectedProduct.description }}</p>
            </div>
            <div class="modal-specs">
              <div class="spec-item">
                <span class="spec-label">Color:</span
                ><span class="spec-value">{{
                  selectedProduct.color || "Mixed"
                }}</span>
              </div>
              <div class="spec-item">
                <span class="spec-label">Season:</span
                ><span class="spec-value">{{
                  selectedProduct.season || "All Year"
                }}</span>
              </div>
              <div class="spec-item">
                <span class="spec-label">Stock:</span>
                <span :class="['stock-status', stockClass(selectedProduct)]">{{
                  stockText(selectedProduct)
                }}</span>
              </div>
            </div>
            <div class="modal-actions">
              <div class="quantity-selector">
                <button @click="qty > 1 && qty--" class="qty-btn">−</button>
                <span class="qty-value">{{ qty }}</span>
                <button
                  @click="qty < selectedProduct.quantity_in_stock && qty++"
                  class="qty-btn"
                >
                  +
                </button>
              </div>
              <button
                class="btn-add-to-cart-modal"
                :disabled="selectedProduct.quantity_in_stock === 0"
                @click="
                  addToCart(selectedProduct, qty, null, selectedProductImageRef);
                  closeModal();
                "
              >
                {{
                  selectedProduct.quantity_in_stock > 0
                    ? "Add to Cart"
                    : "Out of Stock"
                }}
              </button>
              <button class="btn-buy-now" @click="closeModal()">Buy Now</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Gallery Modal ─────────────────────────────────────────── -->
    <teleport to="body">
      <div
        v-if="galleryIndex !== null"
        class="gallery-modal"
        @click="closeGallery"
      >
        <button class="modal-close gallery-close" @click.stop="closeGallery">
          ✕
        </button>
        <button class="modal-nav nav-prev" @click.stop="prevGalleryImage">
          ‹
        </button>
        <img
          :src="vendor.portfolio_photos_paths[galleryIndex]"
          class="modal-image"
          @click.stop
        />
        <button class="modal-nav nav-next" @click.stop="nextGalleryImage">
          ›
        </button>
        <div class="modal-counter">
          {{ galleryIndex + 1 }} / {{ vendor.portfolio_photos_paths.length }}
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from "vue";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";
import { useRoute, useRouter } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import api from "../../plugins/axios";
import NavHeader from "../../layouts/NavHeader.vue";
import ModelViewer3D from "../../layouts/3D/3DModelViewer.vue";
import { useCart } from "../../composables/useCart";
import { useFlyToCart } from "../../composables/useFlyToCart";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";

const route = useRoute();
const router = useRouter();
const { isAuthenticated } = useAuth();
const cartStore = useCart();
const { flyToCart } = useFlyToCart();
const navHeaderRef = ref(null);

// ── IDs ────────────────────────────────────────────────────────────────────
// Store IDs stay out of the URL. They are carried in the existing browser
// history entry by Shop.vue and Chat.vue, preserving back navigation.
const vendorId = computed(() => window.history.state?.storeId || null);

// ── State ──────────────────────────────────────────────────────────────────
const loading = ref(true);
const loadingProducts = ref(false);
const error = ref(null);

const vendor = ref({
  store_name: "",
  store_description: "",
  store_logo_path: "",
  store_cover_url: null,
  business_type: "",
  store_address: "",
  store_latitude: null,
  store_longitude: null,
  service_areas: [],
  operating_hours: "",
  product_types: [],
  price_min: 0,
  price_max: 0,
  same_day_delivery: false,
  cutoff_times: "",
  max_orders_per_day: 0,
  default_delivery_fee: 0,
  lead_time: "",
  cancellation_policy: "",
  portfolio_photos_paths: [],
  facebook_page: "",
  instagram_page: "",
  contact_number: "",
  email: "",
  status: "",
  verification_level: "",
  owner_id: null,
});

const products = ref([]);

// ── Filters ────────────────────────────────────────────────────────────────
const filters = ref({ category: "All", flowerType: "All", occasion: "All" });
const sortBy = ref("newest");
const openFilters = ref({
  category: false,
  flowerType: false,
  occasion: false,
});

// ── Modal ──────────────────────────────────────────────────────────────────
const showModal = ref(false);
const selectedProduct = ref(null);
const selectedImageIndex = ref(0);
const selectedProductImageRef = ref(null);
const show3D = ref(false);
const qty = ref(1);
const galleryIndex = ref(null);

// ── Computed ───────────────────────────────────────────────────────────────

const cartCount = computed(() => cartStore.count.value);

// store_address = "General Trias" — use it directly as the location chip
const storeCity = computed(() => {
  const addr = vendor.value.store_address || "";
  // If address has commas take the last meaningful part, otherwise use as-is
  const parts = addr
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);
  return parts.length ? parts[parts.length - 1] : "";
});

// ── Pinned location ────────────────────────────────────────────────────────
const storePin = computed(() => {
  const { store_latitude: lat, store_longitude: lng } = vendor.value;
  if (lat === null || lat === undefined || lat === "" || lng === null || lng === undefined || lng === "") return null;
  const latitude = Number(lat);
  const longitude = Number(lng);
  return Number.isFinite(latitude) && Number.isFinite(longitude) ? { latitude, longitude } : null;
});
const hasStorePin = computed(() => !!storePin.value);
const directionsUrl = computed(() =>
  storePin.value
    ? `https://www.google.com/maps/dir/?api=1&destination=${storePin.value.latitude},${storePin.value.longitude}`
    : "",
);
const openMapUrl = computed(() =>
  storePin.value
    ? `https://www.google.com/maps/search/?api=1&query=${storePin.value.latitude},${storePin.value.longitude}`
    : "",
);

const locationMapEl = ref(null);
let locationMap = null;
const storeMarkerIcon = L.icon({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

const renderLocationMap = async () => {
  await nextTick();
  if (!storePin.value || !locationMapEl.value) return;
  locationMap?.remove();
  const position = [storePin.value.latitude, storePin.value.longitude];
  locationMap = L.map(locationMapEl.value, { scrollWheelZoom: false }).setView(position, 16);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: "© OpenStreetMap contributors",
  }).addTo(locationMap);
  L.marker(position, { icon: storeMarkerIcon })
    .addTo(locationMap)
    .bindPopup(vendor.value.store_name || "Shop location");
};

onBeforeUnmount(() => {
  locationMap?.remove();
  locationMap = null;
});

// Safely coerce service_areas — backend may return a plain string "Cavite"
// or a JSON array ["Cavite", "General Trias"]
const parsedServiceAreas = computed(() => {
  const raw = vendor.value.service_areas;
  if (!raw) return [];
  if (Array.isArray(raw)) return raw.filter(Boolean);
  if (typeof raw === "string") {
    // Try JSON
    try {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) return parsed.filter(Boolean);
    } catch {}
    // Comma-separated plain string
    return raw
      .split(",")
      .map((s) => s.trim())
      .filter(Boolean);
  }
  return [];
});

const parsedCutoffTimes = computed(() => {
  const raw = vendor.value.cutoff_times;
  if (!raw) return "—";
  try {
    const parsed = typeof raw === "string" ? JSON.parse(raw) : raw;
    if (Array.isArray(parsed) && parsed.length)
      return parsed.map((c) => `${c.day} ${c.time}`).join(", ");
    if (parsed?.day) return `${parsed.day} ${parsed.time}`;
  } catch {}
  return String(raw);
});

const categories = computed(() => [
  "All",
  ...new Set(products.value.map((p) => p.category).filter(Boolean)),
]);
const flowerTypes = computed(() => [
  "All",
  ...new Set(products.value.map((p) => p.flower_type).filter(Boolean)),
]);
const occasions = computed(() => [
  "All",
  ...new Set(products.value.flatMap((p) => p.occasion_tags || [])),
]);

const filteredProducts = computed(() => {
  let list = products.value.filter((p) => {
    const okCat =
      filters.value.category === "All" || p.category === filters.value.category;
    const okFt =
      filters.value.flowerType === "All" ||
      p.flower_type === filters.value.flowerType;
    const okOcc =
      filters.value.occasion === "All" ||
      (p.occasion_tags || []).includes(filters.value.occasion);
    return okCat && okFt && okOcc;
  });

  if (sortBy.value === "price-low")
    list = [...list].sort((a, b) => getDisplayPrice(a) - getDisplayPrice(b));
  else if (sortBy.value === "price-high")
    list = [...list].sort((a, b) => getDisplayPrice(b) - getDisplayPrice(a));
  else if (sortBy.value === "oldest")
    list = [...list].sort(
      (a, b) => new Date(a.created_at) - new Date(b.created_at),
    );
  else if (sortBy.value === "name_asc")
    list = [...list].sort((a, b) =>
      a.product_name.localeCompare(b.product_name),
    );
  else
    list = [...list].sort(
      (a, b) => new Date(b.created_at) - new Date(a.created_at),
    );

  return list;
});

const isStorefrontVisibleProduct = (product) =>
  ["per_piece", "bouquet", "", null, undefined].includes(product?.selling_type);

// ── API ────────────────────────────────────────────────────────────────────

const fetchVendor = async () => {
  loading.value = true;
  error.value = null;
  if (!vendorId.value) {
    error.value = "This store could not be identified.";
    loading.value = false;
    return;
  }
  try {
    const { data } = await api.get(`vendors/${vendorId.value}`);
    if (data.success) {
      vendor.value = data.data;
    } else {
      error.value = data.message || "Store not found.";
    }
  } catch (err) {
    console.error("Vendor fetch error:", err);
    error.value = "This store could not be loaded.";
  } finally {
    loading.value = false;
  }
  if (!error.value) {
    fetchProducts();
    renderLocationMap();
  }
};

const fetchProducts = async () => {
  loadingProducts.value = true;
  try {
    // Use the vendor's owner_id (user id) to fetch products via our storefront endpoint
    const { data } = await api.get(`vendors/${vendorId.value}/products`);
    if (data.success) {
      products.value = (Array.isArray(data.data) ? data.data : []).filter(
        isStorefrontVisibleProduct,
      );
    } else {
      products.value = [];
    }
  } catch (err) {
    console.error("Products fetch error:", err);
    products.value = [];
  } finally {
    loadingProducts.value = false;
  }
};

const loadCart = async () => {
  if (!isAuthenticated.value) return;
  await cartStore.initialize();
};

// ── Helpers ────────────────────────────────────────────────────────────────

const getDisplayPrice = (p) =>
  p.has_discount && p.discount_price
    ? Number(p.discount_price)
    : Number(p.selling_price || 0);

const formatPrice = (n) => Number(n || 0).toFixed(2);

const getProductImage = (p) => {
  if (p?.primary_image?.image_url) return p.primary_image.image_url;
  if (p?.images?.length)
    return (
      p.images.find((i) => i.is_primary)?.image_url || p.images[0].image_url
    );
  const fallbacks = [
    "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=300&h=300&fit=crop",
    "https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=300&h=300&fit=crop",
  ];
  return fallbacks[p.id % fallbacks.length] || fallbacks[0];
};

const handleImageError = (e) => {
  e.target.src =
    "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=300&h=300&fit=crop";
};
const handleLogoError = (e) => {
  e.target.style.display = "none";
};

const truncate = (text, len = 60) =>
  !text ? "" : text.length > len ? text.slice(0, len) + "..." : text;

const stockClass = (p) =>
  p.quantity_in_stock <= 0
    ? "out-of-stock"
    : p.quantity_in_stock <= 10
      ? "low-stock"
      : "in-stock";
const stockText = (p) =>
  p.quantity_in_stock <= 0
    ? "Out of Stock"
    : p.quantity_in_stock <= 10
      ? "Low Stock"
      : "In Stock";

const applyFilters = () => {}; // filteredProducts is reactive computed

const resetFilters = () => {
  filters.value = { category: "All", flowerType: "All", occasion: "All" };
};

// ── Cart / Wishlist ────────────────────────────────────────────────────────

const getCardImageElement = (event) =>
  event?.currentTarget
    ?.closest(".product-card")
    ?.querySelector(".product-image img");

const addToCart = async (
  product,
  quantity = 1,
  event = null,
  sourceImageEl = null,
) => {
  if (!isAuthenticated.value) {
    router.push({ path: "/guest/login", query: { redirect: route.fullPath } });
    return;
  }
  try {
    const r = await cartStore.addToCart({
      product_id: product.id,
      quantity,
    });
    if (r.success) {
      await flyToCart(sourceImageEl || getCardImageElement(event));
      if (navHeaderRef.value?.triggerCartPulse)
        navHeaderRef.value.triggerCartPulse();
      toast.success(`${product.product_name} added to cart!`, {
        autoClose: 2000,
        position: "top-right",
      });
    }
  } catch {
    toast.error("Failed to add item to cart", {
      autoClose: 3000,
      position: "top-right",
    });
  }
};



const chatVendor = () => {
  if (!isAuthenticated.value) {
    router.push({ path: "/guest/login", query: { redirect: route.fullPath } });
    return;
  }

  router.push({
    path: "/customer/chat",
    query: {
      vendor_id: String(vendorId.value),
      vendor_name: vendor.value?.store_name || "",
    },
  });
};

const customizeFlowers = () => {
  router.push({
    path: "/customize/flower",
    query: {
      store_id: String(vendorId.value),
      owner_id: vendor.value?.owner_id ? String(vendor.value.owner_id) : "",
      store_name: vendor.value?.store_name || "",
    },
  });
};

// ── Modal ──────────────────────────────────────────────────────────────────

const openModal = (p) => {
  selectedProduct.value = p;
  selectedImageIndex.value = 0;
  show3D.value = !!(p.models && p.models.length > 0);
  qty.value = 1;
  showModal.value = true;
  document.body.style.overflow = "hidden";
};

const closeModal = () => {
  showModal.value = false;
  selectedProduct.value = null;
  show3D.value = false;
  document.body.style.overflow = "auto";
};

// ── Gallery ────────────────────────────────────────────────────────────────

const openGallery = (i) => (galleryIndex.value = i);
const closeGallery = () => (galleryIndex.value = null);
const nextGalleryImage = () => {
  galleryIndex.value =
    (galleryIndex.value + 1) % vendor.value.portfolio_photos_paths.length;
};
const prevGalleryImage = () => {
  const l = vendor.value.portfolio_photos_paths.length;
  galleryIndex.value = (galleryIndex.value - 1 + l) % l;
};

// ── Lifecycle ──────────────────────────────────────────────────────────────

onMounted(async () => {
  await fetchVendor();
  await loadCart();
});
</script>

<style scoped>
/* ── Base ──────────────────────────────────────────────────────────────── */
* {
  font-family: "Poppins", sans-serif;
  box-sizing: border-box;
}

.storefront-page {
  width: 100%;
  overflow-x: hidden;
  background: #f7fafc;
}

/* ── Error Screen ─────────────────────────────────────────────────────── */
.error-screen {
  min-height: 60vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  text-align: center;
  padding: 40px;
}
.error-icon {
  font-size: 64px;
}
.error-screen h2 {
  font-size: 28px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}
.error-screen p {
  color: #718096;
  margin: 0;
}

/* ── Buttons (same as Shop.vue) ───────────────────────────────────────── */
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
  background: #38a169;
}

/* ── Store Hero ───────────────────────────────────────────────────────── */
.store-hero {
  margin-top: 80px;
  background: linear-gradient(135deg, #f0f4ff 0%, #fef5ff 100%);
  padding: 0 0 36px;
  border-bottom: 1px solid #e2e8f0;
}
.store-hero-inner {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 5%;
}

/* Cover photo; falls back to a soft gradient when the vendor has none */
.store-cover {
  position: relative;
  height: 300px;
  background: linear-gradient(135deg, #c6f6d5 0%, #e9d8fd 55%, #fed7e2 100%);
  overflow: hidden;
}
.store-cover-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.store-cover::after {
  content: "";
  position: absolute;
  inset: auto 0 0;
  height: 40%;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.18), transparent);
  pointer-events: none;
}

.back-link {
  position: absolute;
  top: 20px;
  left: 5%;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #276749;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  background: rgba(255, 255, 255, 0.92);
  border: none;
  border-radius: 999px;
  padding: 8px 14px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
  transition: background 0.2s;
}
.back-link:hover {
  background: #ffffff;
}

.store-hero-content {
  display: flex;
  align-items: flex-start;
  gap: 24px;
  margin-bottom: 32px;
  flex-wrap: wrap;
}

.store-logo-wrap {
  flex-shrink: 0;
  margin-top: -56px; /* sits over the bottom of the cover */
  position: relative;
  z-index: 1;
}
.store-info {
  padding-top: 20px;
}
.store-actions {
  padding-top: 20px;
}
.store-logo {
  width: 120px;
  height: 120px;
  border-radius: 18px;
  object-fit: cover;
  background: white;
  border: 4px solid white;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}
.store-logo-fallback {
  width: 120px;
  height: 120px;
  border: 4px solid white;
  border-radius: 18px;
  background: linear-gradient(135deg, #48bb78, #38a169);
  color: white;
  font-size: 36px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 16px rgba(72, 187, 120, 0.3);
}

.store-info {
  flex: 1;
  min-width: 0;
}
.store-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}
.store-name {
  font-size: 30px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}
.verified-badge {
  background: #ebf8ff;
  color: #2b6cb0;
  border-radius: 20px;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 600;
}
.store-desc {
  color: #718096;
  font-size: 15px;
  margin: 0 0 12px;
  max-width: 600px;
}
.store-meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.meta-chip {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  padding: 4px 12px;
  font-size: 12px;
  color: #4a5568;
  font-weight: 500;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}
.chip-green {
  background: #f0fff4;
  border-color: #68d391;
  color: #276749;
}

.store-actions {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  flex-shrink: 0;
}
.btn-chat {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  background: #48bb78;
  color: white;
  border: none;
  transition: background 0.2s;
}
.btn-chat:hover {
  background: #38a169;
}

/* Stat pills — same style as Shop.vue hero ratings row */
.btn-customize {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  background: white;
  color: #2f855a;
  border: 1px solid #9ae6b4;
  transition:
    background 0.2s,
    color 0.2s,
    border-color 0.2s;
}
.btn-customize:hover {
  background: #f0fff4;
  border-color: #68d391;
}

.store-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.stat-pill {
  background: white;
  border-radius: 12px;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  border: 1px solid #f0f0f0;
}
.stat-icon {
  font-size: 24px;
  flex-shrink: 0;
}
.stat-label {
  font-size: 11px;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin: 0 0 2px;
  font-weight: 600;
}
.stat-value {
  font-size: 14px;
  color: #2d3748;
  font-weight: 600;
  margin: 0;
}

/* ── Products Section (pixel-identical to Shop.vue) ───────────────────── */
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

/* Sidebar — exact Shop.vue clone */
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
.btn-clear-all:hover {
  text-decoration: underline;
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
    transform: translateY(-8px);
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
.checkbox-label input {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: #48bb78;
}
.checkbox-label:hover {
  color: #2d3748;
}

/* Products main */
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
  font-family: "Poppins", sans-serif;
}
.sort-select:focus {
  outline: none;
  border-color: #48bb78;
}

/* No results */
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

/* Skeletons */
.product-skeleton {
  pointer-events: none;
}
.skeleton-img {
  height: 240px;
  border-radius: 12px 12px 0 0;
}
.skeleton-line {
  height: 14px;
  margin-bottom: 10px;
}
.skeleton-line.short {
  width: 50%;
}
.skeleton-line.medium {
  width: 70%;
}

/* ── Page loading skeleton ───────────────────────────────────────────── */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
.sk-cover {
  height: 300px;
  border-radius: 0;
}
.sk-logo {
  width: 120px;
  height: 120px;
  border-radius: 18px;
  border: 4px solid white;
}
.sk-line {
  height: 14px;
  margin-bottom: 12px;
  max-width: 520px;
}
.sk-title {
  height: 30px;
  width: 280px;
  max-width: 70%;
}
.sk-short {
  width: 40%;
}
.sk-pill {
  height: 64px;
  border-radius: 12px;
}
.sk-products {
  max-width: 1400px;
  margin: 0 auto;
}

/* Product grid — identical to Shop.vue */
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
  transition: transform 0.3s;
}
.product-card:hover .product-image img {
  transform: scale(1.05);
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
.product-actions-overlay {
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
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}
.icon-btn:hover {
  background: #f7fafc;
  transform: scale(1.1);
}
.product-info {
  padding: 16px;
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
  margin-bottom: 0;
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
  font-family: "Poppins", sans-serif;
}
.btn-view-details:hover {
  background: #f7fafc;
}
.btn-add-to-cart {
  height: 40px;
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
  font-family: "Poppins", sans-serif;
}
.btn-add-to-cart:hover:not(:disabled) {
  background: #38a169;
}
.btn-add-to-cart:disabled {
  background: #cbd5e0;
  cursor: not-allowed;
}

/* ── Info Section ─────────────────────────────────────────────────────── */
.info-section {
  background: white;
  padding: 60px 5%;
  border-top: 1px solid #e2e8f0;
}
.info-inner {
  max-width: 1400px;
  margin: 0 auto;
}
.info-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}

/* ── Location card ────────────────────────────────────────────────────── */
.location-card {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
  margin-bottom: 24px;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  overflow: hidden;
  background: #f7fafc;
}
.location-card.no-pin {
  grid-template-columns: 1fr;
}
.location-map {
  min-height: 300px;
  z-index: 0; /* keep Leaflet panes under the sticky header */
}
.location-details {
  padding: 28px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 8px;
}
.location-details .section-subtitle {
  margin: 0;
}
.location-title {
  font-size: 22px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}
.location-address {
  font-size: 15px;
  color: #4a5568;
  line-height: 1.6;
  margin: 0;
}
.location-note {
  font-size: 13px;
  color: #a0aec0;
  margin: 0;
}
.location-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 12px;
}
.location-btn {
  padding: 10px 18px;
  border-radius: 8px;
  border: 1px solid #cbd5e0;
  background: white;
  color: #2d3748;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}
.location-btn:hover {
  border-color: #48bb78;
  color: #276749;
}
.location-btn-primary {
  background: #48bb78;
  border-color: #48bb78;
  color: white;
}
.location-btn-primary:hover {
  background: #38a169;
  color: white;
}
.info-card {
  background: #f7fafc;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  gap: 16px;
  align-items: flex-start;
  border: 1px solid #e2e8f0;
}
.info-card.card-green {
  background: #f0fff4;
  border-color: #68d391;
}
.info-card-icon {
  font-size: 24px;
  flex-shrink: 0;
}
.info-card h4 {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 6px;
}
.info-card p {
  font-size: 13px;
  color: #718096;
  margin: 0;
}
.info-fine {
  font-size: 11px;
  color: #a0aec0;
  margin-top: 4px !important;
}
.area-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.area-tag {
  padding: 2px 10px;
  border-radius: 20px;
  font-size: 12px;
  background: white;
  border: 1px solid #e2e8f0;
  color: #4a5568;
}
.text-muted {
  font-size: 13px;
  color: #a0aec0;
}

/* ── Portfolio ────────────────────────────────────────────────────────── */
.portfolio-section {
  padding: 60px 5%;
  background: #f7fafc;
}
.section-h2 {
  font-size: 32px;
  font-weight: 500;
  color: #2d3748;
  margin: 0 0 28px;
}
.portfolio-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.portfolio-item {
  position: relative;
  padding-top: 100%;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
}
.portfolio-item img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s;
}
.portfolio-item:hover img {
  transform: scale(1.08);
}
.portfolio-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.3s;
  font-size: 2rem;
  opacity: 0;
}
.portfolio-item:hover .portfolio-overlay {
  background: rgba(0, 0, 0, 0.3);
  opacity: 1;
}

/* ── Contact ──────────────────────────────────────────────────────────── */
.contact-section {
  padding: 60px 5%;
  background: white;
  border-top: 1px solid #e2e8f0;
}
.contact-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.contact-card {
  background: #f7fafc;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  text-decoration: none;
  color: inherit;
  border: 1px solid #e2e8f0;
  transition: box-shadow 0.3s;
}
.contact-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}
.contact-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
}
.icon-fb {
  background: #ebf8ff;
}
.icon-ig {
  background: #fdf2f8;
}
.icon-phone {
  background: #f0fff4;
}
.icon-email {
  background: #faf5ff;
}
.contact-label {
  font-size: 12px;
  color: #9ca3af;
  margin: 0 0 2px;
}
.contact-val {
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
  margin: 0;
  word-break: break-all;
}

/* ── Footer ───────────────────────────────────────────────────────────── */
.store-footer {
  background: #2d3748;
  color: #cbd5e0;
  padding: 28px 5%;
  text-align: center;
  font-size: 14px;
}
.store-footer p {
  margin: 0;
}

/* ── Product Modal (same as Shop.vue) ────────────────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}
.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 1000px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  padding: 30px;
  position: relative;
}
.modal-close {
  position: absolute;
  top: 20px;
  right: 20px;
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #718096;
  z-index: 1;
}
.modal-close:hover {
  color: #2d3748;
}
.modal-body {
  display: flex;
  gap: 32px;
  align-items: flex-start;
}
.modal-images {
  flex: 0 0 460px;
  background: #f7fafc;
  border-radius: 12px;
  padding: 16px;
  min-height: 460px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.model-viewer {
  width: 100%;
  height: 420px;
  border-radius: 12px;
}
.image-container {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.main-image {
  width: 100%;
  height: 380px;
  object-fit: contain;
  border-radius: 10px;
  background: white;
  border: 2px solid #48bb78;
}
.image-thumbnails {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.thumbnail {
  width: 60px;
  height: 60px;
  border-radius: 6px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.2s;
}
.thumbnail:hover {
  border-color: #cbd5e0;
}
.thumbnail.active {
  border-color: #48bb78;
}
.thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.view-toggle {
  display: flex;
  gap: 10px;
  padding: 10px 14px;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  justify-content: center;
}
.view-toggle button {
  padding: 10px 20px;
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
  min-width: 100px;
  font-family: "Poppins", sans-serif;
}
.view-toggle button:hover {
  border-color: #cbd5e0;
  background: #f7fafc;
}
.view-toggle button.active {
  background: #48bb78;
  color: white;
  border-color: #48bb78;
}
.modal-details {
  flex: 1;
}
.modal-details h2 {
  font-size: 26px;
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 6px;
}
.modal-category {
  font-size: 14px;
  color: #718096;
  margin-bottom: 10px;
}
.modal-rating {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 16px;
  font-size: 14px;
}
.reviews {
  color: #718096;
}
.modal-price {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}
.current-price {
  font-size: 30px;
  font-weight: 600;
  color: #2d3748;
}
.original-price {
  font-size: 18px;
  color: #a0aec0;
  text-decoration: line-through;
}
.modal-description h3 {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 6px;
  color: #2d3748;
}
.modal-description p {
  color: #718096;
  line-height: 1.6;
  margin-bottom: 20px;
}
.modal-specs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 20px;
}
.spec-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.spec-label {
  font-size: 13px;
  color: #718096;
}
.spec-value {
  font-size: 14px;
  color: #2d3748;
  font-weight: 500;
}
.stock-status {
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 13px;
  font-weight: 500;
}
.stock-status.in-stock {
  background: #d1fae5;
  color: #065f46;
}
.stock-status.low-stock {
  background: #fef3c7;
  color: #92400e;
}
.stock-status.out-of-stock {
  background: #fee2e2;
  color: #991b1b;
}
.modal-actions {
  display: grid;
  grid-template-columns: auto 1fr 1fr;
  gap: 12px;
  align-items: center;
}
.quantity-selector {
  display: flex;
  align-items: center;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  height: 44px;
}
.qty-btn {
  width: 44px;
  height: 44px;
  background: white;
  border: none;
  font-size: 18px;
  cursor: pointer;
  color: #2d3748;
  transition: background 0.2s;
}
.qty-btn:hover {
  background: #f7fafc;
}
.qty-value {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
  min-width: 32px;
  text-align: center;
}
.btn-add-to-cart-modal {
  height: 44px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}
.btn-add-to-cart-modal:hover:not(:disabled) {
  background: #38a169;
}
.btn-add-to-cart-modal:disabled {
  background: #cbd5e0;
  cursor: not-allowed;
}
.btn-buy-now {
  height: 44px;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
  font-family: "Poppins", sans-serif;
}
.btn-buy-now:hover {
  background: #1a202c;
}

/* ── Gallery Modal ────────────────────────────────────────────────────── */
.gallery-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.92);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.gallery-close {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 40px;
  height: 40px;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  border-radius: 50%;
  color: white;
  cursor: pointer;
  font-size: 20px;
}
.modal-nav {
  position: absolute;
  width: 48px;
  height: 48px;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  border-radius: 50%;
  color: white;
  cursor: pointer;
  font-size: 28px;
  transition: background 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal-nav:hover {
  background: rgba(255, 255, 255, 0.2);
}
.nav-prev {
  left: 20px;
}
.nav-next {
  right: 20px;
}
.modal-image {
  max-width: 90%;
  max-height: 85vh;
  object-fit: contain;
  border-radius: 8px;
}
.modal-counter {
  position: absolute;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  color: white;
  font-size: 14px;
}

/* ── Responsive ───────────────────────────────────────────────────────── */
@media (max-width: 1200px) {
  .products-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .store-stats {
    grid-template-columns: repeat(2, 1fr);
  }
  .info-cards {
    grid-template-columns: repeat(2, 1fr);
  }
  .contact-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .portfolio-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
@media (max-width: 968px) {
  .products-container {
    grid-template-columns: 1fr;
  }
  .filter-sidebar {
    position: static;
  }
  .store-hero-content {
    flex-wrap: wrap;
  }
  .modal-body {
    flex-direction: column;
  }
  .modal-images {
    flex: none;
    max-width: 100%;
  }
  .main-image {
    height: 280px;
  }
}
@media (max-width: 768px) {
  .store-stats {
    grid-template-columns: repeat(2, 1fr);
  }
  .products-grid {
    grid-template-columns: 1fr;
  }
  .section-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  .info-cards {
    grid-template-columns: 1fr;
  }
  .location-card {
    grid-template-columns: 1fr;
  }
  .location-map {
    min-height: 240px;
  }
  .store-cover,
  .sk-cover {
    height: 200px;
  }
  .sk-logo {
    width: 96px;
    height: 96px;
  }
  .store-logo-wrap {
    margin-top: -44px;
  }
  .store-logo,
  .store-logo-fallback {
    width: 96px;
    height: 96px;
  }
  .store-info,
  .store-actions {
    padding-top: 0;
  }
  .contact-grid {
    grid-template-columns: 1fr;
  }
  .portfolio-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 480px) {
  .store-stats {
    grid-template-columns: 1fr 1fr;
  }
  .modal-actions {
    grid-template-columns: 1fr;
  }
  .store-name {
    font-size: 22px;
  }
}
</style>
