<template>
  <div class="storefront-page bc-theme">
    <NavHeader
      ref="navHeaderRef"
      :cartCount="cartCount"
      :isAuthenticated="isAuthenticated"
      @scroll-to-section="() => {}"
    />

    <!-- Loading skeleton, shaped like the store header and collection -->
    <div v-if="loading" aria-busy="true">
      <span class="bc-sr-only">Loading store…</span>
      <div class="bc-skeleton sf-cover sf-cover--skeleton"></div>
      <div class="bc-container">
        <div class="sf-identity">
          <div class="bc-skeleton sf-seal sf-seal--skeleton"></div>
          <div class="sf-skeleton-copy">
            <span class="bc-skeleton sf-skeleton-title"></span>
            <span class="bc-skeleton sf-skeleton-line"></span>
            <span class="bc-skeleton sf-skeleton-line sf-skeleton-line--short"></span>
          </div>
        </div>
        <div class="products-grid sf-skeleton-grid">
          <div v-for="n in 4" :key="n" class="product-skeleton">
            <span class="bc-skeleton product-skeleton-image"></span>
            <span class="bc-skeleton product-skeleton-line product-skeleton-line--short"></span>
            <span class="bc-skeleton product-skeleton-line"></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Store could not be loaded -->
    <section v-else-if="error" class="sf-error bc-container">
      <h1>This store isn't available</h1>
      <p>{{ error }} Head back to the shop to browse other florists.</p>
      <button class="bc-btn bc-btn--dark" @click="$router.push('/shop')">
        Back to the shop
      </button>
    </section>

    <template v-else-if="vendor.store_name">
      <!-- ── Cover + identity ─────────────────────────────────────── -->
      <header class="sf-header">
        <div class="sf-cover" :class="{ 'sf-cover--empty': !vendor.store_cover_url }">
          <img
            v-if="vendor.store_cover_url"
            :src="vendor.store_cover_url"
            alt=""
            class="sf-cover-img"
            @error="vendor.store_cover_url = null"
          />
          <span v-else class="sf-cover-monogram" aria-hidden="true">{{
            storeInitial
          }}</span>
          <div class="bc-container sf-cover-bar">
            <button class="sf-back" @click="$router.push('/shop')">
              <span aria-hidden="true">←</span> All shops
            </button>
          </div>
        </div>

        <div class="bc-container">
          <div class="sf-identity">
            <div class="sf-seal">
              <img
                v-if="vendor.store_logo_path && !logoFailed"
                :src="vendor.store_logo_path"
                :alt="`${vendor.store_name} logo`"
                @error="logoFailed = true"
              />
              <span v-else aria-hidden="true">{{ storeInitial }}</span>
            </div>

            <div class="sf-title">
              <h1>{{ vendor.store_name }}</h1>
              <p v-if="vendor.verification_level === 'verified'" class="sf-verified">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m5 12.5 4.5 4.5L19 7.5" />
                </svg>
                Verified florist
              </p>
            </div>

            <div class="sf-actions">
              <button class="bc-btn bc-btn--dark" @click="customizeFlowers">
                Design a bouquet
              </button>
              <button class="bc-btn sf-btn-outline" @click="chatVendor">
                Message the shop
              </button>
            </div>
          </div>

          <p v-if="vendor.taking_orders === false" class="sf-paused" role="status">
            {{ vendor.paused_message }}
          </p>

          <p v-if="vendor.store_description" class="sf-desc">
            {{ vendor.store_description }}
          </p>

          <dl class="sf-facts">
            <div v-if="storeCity">
              <dt>Based in</dt>
              <dd>{{ storeCity }}</dd>
            </div>
            <div v-if="vendor.price_min || vendor.price_max">
              <dt>Prices</dt>
              <dd>
                ₱{{ Number(vendor.price_min || 0).toLocaleString() }} – ₱{{
                  Number(vendor.price_max || 0).toLocaleString()
                }}
              </dd>
            </div>
            <div v-if="vendor.default_delivery_fee">
              <dt>Delivery from</dt>
              <dd>₱{{ Number(vendor.default_delivery_fee).toLocaleString() }}</dd>
            </div>
            <div>
              <dt>Same-day delivery</dt>
              <dd>{{ vendor.same_day_delivery ? "Available" : "Not offered" }}</dd>
            </div>
          </dl>
        </div>
      </header>

      <!-- ── Collection ───────────────────────────────────────────── -->
      <section id="products" class="sf-collection">
        <div class="bc-container products-layout">
          <aside class="filter-sidebar" aria-label="Filters">
            <div class="filter-header">
              <h2>Filter</h2>
              <button class="btn-clear-all" @click="resetFilters">Clear all</button>
            </div>
            <p class="filter-subtitle">Narrow down this shop's flowers.</p>

            <div v-for="group in filterGroups" :key="group.key" class="filter-group">
              <button
                class="filter-toggle"
                :aria-expanded="openFilters[group.key]"
                @click="openFilters[group.key] = !openFilters[group.key]"
              >
                <span class="filter-label">{{ group.label }}</span>
                <span class="filter-value">
                  {{ filters[group.key] }}
                  <span class="chevron" :class="{ open: openFilters[group.key] }">›</span>
                </span>
              </button>
              <div v-if="openFilters[group.key]" class="filter-options">
                <label v-for="option in group.options" :key="option" class="checkbox-label">
                  <input
                    type="radio"
                    :name="group.key"
                    :value="option"
                    v-model="filters[group.key]"
                  />
                  <span>{{ option }}</span>
                </label>
              </div>
            </div>
          </aside>

          <div class="products-main">
            <div class="products-head">
              <h2>The collection</h2>
              <div class="sort-controls">
                <span class="results-count">
                  {{ filteredProducts.length }}
                  {{ filteredProducts.length === 1 ? "flower" : "flowers" }}
                </span>
                <label>
                  <span class="bc-sr-only">Sort products</span>
                  <select v-model="sortBy" class="sort-select">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="price-low">Price: low to high</option>
                    <option value="price-high">Price: high to low</option>
                    <option value="name_asc">Name: A to Z</option>
                  </select>
                </label>
              </div>
            </div>

            <div v-if="loadingProducts" class="products-grid" aria-label="Loading products">
              <div v-for="n in 6" :key="n" class="product-skeleton">
                <span class="bc-skeleton product-skeleton-image"></span>
                <span class="bc-skeleton product-skeleton-line product-skeleton-line--short"></span>
                <span class="bc-skeleton product-skeleton-line"></span>
                <span class="bc-skeleton product-skeleton-line product-skeleton-line--price"></span>
              </div>
            </div>
            <div v-else-if="products.length === 0" class="no-results">
              <h3>Nothing in the shop yet</h3>
              <p>This florist hasn't listed any flowers. You can still design your own bouquet with them.</p>
              <button class="bc-btn bc-btn--dark" @click="customizeFlowers">
                Design a bouquet
              </button>
            </div>
            <div v-else-if="filteredProducts.length === 0" class="no-results">
              <h3>No flowers match these filters</h3>
              <p>Clear the filters to see everything this shop sells.</p>
              <button class="bc-btn bc-btn--dark" @click="resetFilters">
                Clear all filters
              </button>
            </div>
            <div v-else class="products-grid">
              <ProductCard
                v-for="product in filteredProducts"
                :key="product.id"
                :product="product"
                :selected-vendor="vendor"
                :adding-to-cart="addingToCartProductId === product.id"
                @open-modal="openModal"
                @add-to-cart="addToCartFromCard"
                @buy-now="buyNowDirect"
              />
            </div>
          </div>
        </div>
      </section>

      <!-- ── Visit the shop ───────────────────────────────────────── -->
      <section class="sf-visit">
        <div class="bc-container sf-visit-grid" :class="{ 'sf-visit-grid--no-map': !hasStorePin }">
          <div v-if="hasStorePin" class="sf-map-frame">
            <div ref="locationMapEl" class="sf-map" role="region" :aria-label="`Map showing ${vendor.store_name}`"></div>
          </div>

          <div class="sf-visit-copy">
            <h2>Visit the shop</h2>
            <p class="sf-address">{{ vendor.store_address || "Address not provided" }}</p>
            <p v-if="!hasStorePin" class="sf-muted">
              This shop hasn't pinned its location on the map yet.
            </p>
            <div v-if="hasStorePin" class="sf-visit-actions">
              <a :href="directionsUrl" target="_blank" rel="noopener" class="bc-btn bc-btn--dark">
                Get directions
              </a>
              <a :href="openMapUrl" target="_blank" rel="noopener" class="bc-link">
                Open in Google Maps
              </a>
            </div>

            <dl class="sf-details">
              <div>
                <dt>Opening hours</dt>
                <dd>{{ vendor.operating_hours || "Not listed" }}</dd>
              </div>
              <div>
                <dt>Delivers to</dt>
                <dd>{{ parsedServiceAreas.length ? parsedServiceAreas.join(", ") : "Not listed" }}</dd>
              </div>
              <div v-if="vendor.same_day_delivery">
                <dt>Same-day cutoff</dt>
                <dd>
                  Order by {{ parsedCutoffTimes }}
                  <span class="sf-muted">Subject to courier availability.</span>
                </dd>
              </div>
              <div v-if="vendor.lead_time">
                <dt>Lead time</dt>
                <dd>{{ vendor.lead_time }}</dd>
              </div>
              <div v-if="vendor.cancellation_policy">
                <dt>Cancellations</dt>
                <dd>{{ vendor.cancellation_policy }}</dd>
              </div>
            </dl>

            <ul v-if="contactLinks.length" class="sf-contact">
              <li v-for="link in contactLinks" :key="link.label">
                <span class="sf-contact-label">{{ link.label }}</span>
                <a
                  :href="link.href"
                  class="bc-link"
                  :target="link.external ? '_blank' : null"
                  :rel="link.external ? 'noopener' : null"
                  >{{ link.text }}</a
                >
              </li>
            </ul>
          </div>
        </div>
      </section>

      <!-- ── Portfolio ────────────────────────────────────────────── -->
      <section v-if="(vendor.portfolio_photos_paths || []).length" class="sf-portfolio">
        <div class="bc-container">
          <h2>Past arrangements</h2>
          <div class="sf-portfolio-grid">
            <button
              v-for="(photo, idx) in vendor.portfolio_photos_paths"
              :key="idx"
              type="button"
              class="sf-portfolio-item"
              :aria-label="`View arrangement ${idx + 1}`"
              @click="openGallery(idx)"
            >
              <img :src="photo" alt="" loading="lazy" />
            </button>
          </div>
        </div>
      </section>

      <footer class="sf-footer">
        <div class="bc-container">
          © {{ new Date().getFullYear() }} {{ vendor.store_name }} on BloomCraft
        </div>
      </footer>
    </template>

    <!-- ── Product details ──────────────────────────────────────── -->
    <div v-if="showModal && selectedProduct" class="modal-overlay" @click="closeModal">
      <div
        class="modal-content"
        role="dialog"
        aria-modal="true"
        :aria-label="selectedProduct.product_name"
        @click.stop
      >
        <button class="modal-close" aria-label="Close" @click="closeModal">&#x2715;</button>
        <div class="modal-inner">
          <div class="modal-left">
            <ModelViewer3D
              v-if="show3D && selectedProduct.models?.length > 0"
              :modelUrl="selectedProduct.models[0].model_url"
              :modelType="selectedProduct.models[0].model_type"
              class="model-viewer"
            />
            <div v-else class="image-container">
              <img
                ref="selectedProductImageRef"
                :src="selectedProduct.images?.[selectedImageIndex]?.image_url || getProductImage(selectedProduct)"
                :alt="selectedProduct.product_name"
                class="main-image"
                @error="handleImageError"
              />
              <div v-if="(selectedProduct.images || []).length > 1" class="image-thumbnails">
                <button
                  v-for="(img, idx) in selectedProduct.images"
                  :key="img.id"
                  type="button"
                  class="thumbnail"
                  :class="{ active: selectedImageIndex === idx }"
                  :aria-label="`Show image ${idx + 1}`"
                  @click="selectedImageIndex = idx"
                >
                  <img :src="img.image_url" alt="" />
                </button>
              </div>
            </div>
            <div
              v-if="selectedProduct.models?.length > 0 && selectedProduct.images?.length > 0"
              class="view-toggle"
            >
              <button @click="show3D = true" :class="{ active: show3D }">3D view</button>
              <button @click="show3D = false" :class="{ active: !show3D }">Photos</button>
            </div>
          </div>

          <div class="modal-right">
            <p class="bc-label">{{ selectedProduct.category }}</p>
            <h2 class="modal-product-name">{{ selectedProduct.product_name }}</h2>

            <div v-if="!productStatsLoading" class="modal-stats-row">
              <span class="modal-stars" aria-hidden="true">
                <span
                  v-for="n in 5"
                  :key="n"
                  class="modal-star"
                  :class="getStarClass(n, productStats.average_rating)"
                  >&#x2605;</span
                >
              </span>
              <span class="modal-stats-avg">{{ (productStats.average_rating || 0).toFixed(1) }}</span>
              <span>
                {{ productStats.total_reviews }}
                review{{ productStats.total_reviews !== 1 ? "s" : "" }}, {{ productStats.sold_count }} sold
              </span>
            </div>
            <div v-else class="modal-stats-skeleton"><span class="bc-skeleton"></span></div>

            <div class="modal-price-row">
              <span class="modal-price-current">₱{{ formatPrice(getDisplayPrice(selectedProduct)) }}</span>
              <span
                v-if="selectedProduct.has_discount && selectedProduct.discount_price"
                class="modal-price-original"
                >₱{{ formatPrice(selectedProduct.selling_price) }}</span
              >
            </div>

            <p class="modal-desc-text">{{ selectedProduct.description }}</p>

            <dl class="modal-specs">
              <div class="modal-spec">
                <dt>Color</dt>
                <dd>{{ selectedProduct.color || "Mixed" }}</dd>
              </div>
              <div class="modal-spec">
                <dt>Stock</dt>
                <dd class="stock-pill" :class="stockClass(selectedProduct)">
                  {{ stockText(selectedProduct) }}
                </dd>
              </div>
            </dl>

            <div class="modal-cart-row">
              <div class="quantity-selector" role="group" aria-label="Quantity">
                <button class="qty-btn" aria-label="Decrease quantity" @click="qty > 1 && qty--">&#x2212;</button>
                <span class="qty-value" aria-live="polite">{{ qty }}</span>
                <button
                  class="qty-btn"
                  aria-label="Increase quantity"
                  @click="qty < selectedProduct.quantity_in_stock && qty++"
                >
                  +
                </button>
              </div>
              <button
                class="bc-btn sf-btn-outline modal-btn"
                :disabled="selectedProduct.quantity_in_stock === 0 || addingToCartModal"
                @click="addToCartFromModal"
              >
                {{
                  addingToCartModal
                    ? "Adding..."
                    : selectedProduct.quantity_in_stock > 0
                      ? "Add to cart"
                      : "Out of stock"
                }}
              </button>
              <button
                class="bc-btn bc-btn--dark modal-btn"
                :disabled="selectedProduct.quantity_in_stock === 0 || addingToCartModal"
                @click="buyNow(selectedProduct, qty)"
              >
                Buy now
              </button>
            </div>

            <div class="modal-reviews-embed">
              <p class="bc-label">Customer reviews</p>
              <ProductReviews
                :product-id="selectedProduct.id"
                class="modal-reviews-component"
                @updated="fetchProductStats(selectedProduct.id)"
              />
            </div>

            <div v-if="selectedProduct" class="modal-reviews-embed">
              <p class="bc-label">Questions &amp; answers</p>
              <ProductQuestions :product-id="selectedProduct.id" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Portfolio viewer ─────────────────────────────────────── -->
    <teleport to="body">
      <div
        v-if="galleryIndex !== null"
        class="sf-gallery"
        role="dialog"
        aria-modal="true"
        aria-label="Past arrangements"
        @click="closeGallery"
      >
        <button class="sf-gallery-btn sf-gallery-close" aria-label="Close" @click.stop="closeGallery">&#x2715;</button>
        <button class="sf-gallery-btn sf-gallery-prev" aria-label="Previous photo" @click.stop="prevGalleryImage">‹</button>
        <img :src="vendor.portfolio_photos_paths[galleryIndex]" alt="" @click.stop />
        <button class="sf-gallery-btn sf-gallery-next" aria-label="Next photo" @click.stop="nextGalleryImage">›</button>
        <p class="sf-gallery-count">
          {{ galleryIndex + 1 }} of {{ vendor.portfolio_photos_paths.length }}
        </p>
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
import ProductCard from "../../layouts/components/ProductCard.vue";
import ProductReviews from "../../layouts/components/ProductReviews.vue";
import ProductQuestions from "../../layouts/components/ProductQuestions.vue";
import { useCart } from "../../composables/useCart";
import { useFlyToCart } from "../../composables/useFlyToCart";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import "../../styles/bloomcraft.css";

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
const logoFailed = ref(false);

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
const openFilters = ref({ category: true, flowerType: false, occasion: false });

// ── Product modal ──────────────────────────────────────────────────────────
const showModal = ref(false);
const selectedProduct = ref(null);
const selectedImageIndex = ref(0);
const selectedProductImageRef = ref(null);
const show3D = ref(false);
const qty = ref(1);
const addingToCartProductId = ref(null);
const addingToCartModal = ref(false);
const productStats = ref({ average_rating: 0, total_reviews: 0, sold_count: 0 });
const productStatsLoading = ref(false);
const galleryIndex = ref(null);

// ── Computed ───────────────────────────────────────────────────────────────

const cartCount = computed(() => cartStore.count.value);

const storeInitial = computed(() => vendor.value.store_name?.trim().charAt(0).toUpperCase() || "B");

// store_address is usually "Town, Province"; show the town
const storeCity = computed(() => {
  const parts = (vendor.value.store_address || "")
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);
  return parts[0] || "";
});

const contactLinks = computed(() => {
  const v = vendor.value;
  return [
    v.contact_number && { label: "Phone", text: v.contact_number, href: `tel:${v.contact_number}` },
    v.email && { label: "Email", text: v.email, href: `mailto:${v.email}` },
    v.facebook_page && { label: "Facebook", text: "Visit page", href: v.facebook_page, external: true },
    v.instagram_page && { label: "Instagram", text: "Follow", href: v.instagram_page, external: true },
  ].filter(Boolean);
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
  locationMap = L.map(locationMapEl.value, { scrollWheelZoom: false }).setView(position, 15);
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
  document.body.style.overflow = "auto";
});

// Safely coerce service_areas — backend may return a plain string "Cavite"
// or a JSON array ["Cavite", "General Trias"]
const parsedServiceAreas = computed(() => {
  const raw = vendor.value.service_areas;
  if (!raw) return [];
  if (Array.isArray(raw)) return raw.filter(Boolean);
  if (typeof raw === "string") {
    try {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) return parsed.filter(Boolean);
    } catch {}
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

const uniqueOptions = (values) => ["All", ...new Set(values.filter(Boolean))];
const filterGroups = computed(() => [
  { key: "category", label: "Category", options: uniqueOptions(products.value.map((p) => p.category)) },
  { key: "flowerType", label: "Flower type", options: uniqueOptions(products.value.map((p) => p.flower_type)) },
  { key: "occasion", label: "Occasion", options: uniqueOptions(products.value.flatMap((p) => p.occasion_tags || [])) },
]);

const filteredProducts = computed(() => {
  const list = products.value.filter((p) => {
    const okCat = filters.value.category === "All" || p.category === filters.value.category;
    const okFt = filters.value.flowerType === "All" || p.flower_type === filters.value.flowerType;
    const okOcc =
      filters.value.occasion === "All" || (p.occasion_tags || []).includes(filters.value.occasion);
    return okCat && okFt && okOcc;
  });

  const sorters = {
    "price-low": (a, b) => getDisplayPrice(a) - getDisplayPrice(b),
    "price-high": (a, b) => getDisplayPrice(b) - getDisplayPrice(a),
    oldest: (a, b) => new Date(a.created_at) - new Date(b.created_at),
    name_asc: (a, b) => a.product_name.localeCompare(b.product_name),
    newest: (a, b) => new Date(b.created_at) - new Date(a.created_at),
  };
  return [...list].sort(sorters[sortBy.value] || sorters.newest);
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
    const { data } = await api.get(`vendors/${vendorId.value}/products`);
    products.value = data.success
      ? (Array.isArray(data.data) ? data.data : []).filter(isStorefrontVisibleProduct)
      : [];
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

// Live rating, review count and sold count for the open product
const fetchProductStats = async (productId) => {
  productStatsLoading.value = true;
  productStats.value = { average_rating: 0, total_reviews: 0, sold_count: 0 };
  try {
    const res = await api.get(`products/${productId}/reviews`, { params: { per_page: 1 } });
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
};

// ── Helpers ────────────────────────────────────────────────────────────────

const getDisplayPrice = (p) =>
  p.has_discount && p.discount_price ? Number(p.discount_price) : Number(p.selling_price || 0);

const formatPrice = (n) => Number(n || 0).toFixed(2);

const getProductImage = (p) => {
  if (p?.primary_image?.image_url) return p.primary_image.image_url;
  if (p?.images?.length) return p.images.find((i) => i.is_primary)?.image_url || p.images[0].image_url;
  const fallbacks = [
    "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=600&h=600&fit=crop",
    "https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=600&h=600&fit=crop",
  ];
  return fallbacks[p.id % fallbacks.length] || fallbacks[0];
};

const handleImageError = (e) => {
  e.target.src = "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=600&h=600&fit=crop";
};

const getStarClass = (n, rating) => {
  if (n <= Math.floor(rating)) return "star-full";
  if (n - rating < 1 && rating % 1 >= 0.5) return "star-half";
  return "star-empty";
};

const stockClass = (p) =>
  p.quantity_in_stock <= 0 ? "out-of-stock" : p.quantity_in_stock <= 10 ? "low-stock" : "in-stock";
const stockText = (p) =>
  p.quantity_in_stock <= 0 ? "Out of stock" : p.quantity_in_stock <= 10 ? "Low stock" : "In stock";

const resetFilters = () => {
  filters.value = { category: "All", flowerType: "All", occasion: "All" };
};

// ── Cart / checkout ────────────────────────────────────────────────────────

const goToLogin = () => {
  router.push({ path: "/guest/login", query: { redirect: route.fullPath } });
};

const addToCart = async (product, quantity, imageEl) => {
  if (!isAuthenticated.value) {
    goToLogin();
    return false;
  }
  try {
    const r = await cartStore.addToCart({ product_id: product.id, quantity });
    if (r.success) {
      await flyToCart(imageEl);
      navHeaderRef.value?.triggerCartPulse?.();
      toast.success(`${product.product_name} added to cart!`, { autoClose: 2000, position: "top-right" });
      return true;
    }
  } catch {
    toast.error("Failed to add item to cart", { autoClose: 3000, position: "top-right" });
  }
  return false;
};

const addToCartFromCard = async (product, context = {}) => {
  addingToCartProductId.value = product.id;
  await addToCart(product, 1, context.imageEl);
  addingToCartProductId.value = null;
};

const addToCartFromModal = async () => {
  addingToCartModal.value = true;
  const added = await addToCart(selectedProduct.value, qty.value, selectedProductImageRef.value);
  addingToCartModal.value = false;
  if (added) closeModal();
};

const buyNow = (product, quantity) => {
  if (!product || product.quantity_in_stock === 0) return;
  if (!isAuthenticated.value) {
    goToLogin();
    return;
  }
  sessionStorage.setItem(
    "directCheckout",
    JSON.stringify({
      items: [
        {
          product_id: product.id,
          quantity,
          product_name: product.product_name,
          price: product.selling_price,
          image: getProductImage(product),
        },
      ],
      total: parseFloat(product.selling_price) * quantity,
      isDirectCheckout: true,
    }),
  );
  closeModal();
  router.push({
    path: "/customer/checkout",
    query: { direct: "true", product_id: product.id, quantity },
  });
};

const buyNowDirect = (product) => buyNow(product, 1);

const chatVendor = () => {
  if (!isAuthenticated.value) {
    goToLogin();
    return;
  }
  // Chat conversations are between user accounts, so pass the vendor's
  // user id (owner_id), not the store id.
  if (!vendor.value?.owner_id) {
    toast.error("This shop can't receive messages yet.", { position: "top-right" });
    return;
  }
  router.push({
    path: "/customer/chat",
    query: {
      vendor_id: String(vendor.value.owner_id),
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
  fetchProductStats(p.id);
};

const closeModal = () => {
  showModal.value = false;
  selectedProduct.value = null;
  show3D.value = false;
  document.body.style.overflow = "auto";
};

// ── Portfolio viewer ───────────────────────────────────────────────────────

const openGallery = (i) => (galleryIndex.value = i);
const closeGallery = () => (galleryIndex.value = null);
const nextGalleryImage = () => {
  galleryIndex.value = (galleryIndex.value + 1) % vendor.value.portfolio_photos_paths.length;
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
/* Tokens, type, labels, buttons, links, skeletons and the navbar come from
   src/styles/bloomcraft.css. Product, filter and modal styles match Shop.vue. */

@layer bc-reset {
  .storefront-page :is(p, h1, h2, h3, h4, ul, dl, dd, figure) {
    margin: 0;
  }

  .storefront-page ul {
    padding: 0;
    list-style: none;
  }
}

:global(body) {
  margin: 0;
}

.storefront-page {
  width: 100%;
  min-height: 100vh;
  overflow-x: hidden;
  background: var(--ivory);
}

.storefront-page button {
  font-family: var(--font-sans);
}

.sf-btn-outline {
  background: transparent;
  color: var(--ink);
  border-color: var(--line);
}

.sf-btn-outline:hover {
  border-color: var(--ink);
}

.sf-muted {
  display: block;
  color: var(--ink-soft);
  font-size: 13.5px;
}

/* ============================================================
   COVER + IDENTITY
============================================================= */
.sf-cover {
  position: relative;
  height: clamp(240px, 34vw, 440px);
  margin-top: 80px;
  overflow: hidden;
  background: var(--ivory-deep);
}

.sf-cover-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* No cover photo: the store's initial, very large and quiet */
.sf-cover--empty {
  background: linear-gradient(160deg, var(--ivory-deep) 0%, #e2d6cb 100%);
}

.sf-cover-monogram {
  position: absolute;
  right: 6%;
  bottom: -0.28em;
  font-family: var(--font-display);
  font-size: clamp(260px, 38vw, 560px);
  font-style: italic;
  line-height: 1;
  color: var(--leaf);
  opacity: 0.14;
  user-select: none;
}

.sf-cover-bar {
  position: relative;
  padding-top: 22px;
}

.sf-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 16px;
  border: none;
  border-radius: 999px;
  background: rgba(250, 251, 250, 0.92);
  color: var(--ink);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.3s;
}

.sf-back:hover {
  background: var(--paper);
}

.sf-identity {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: end;
  gap: 28px;
}

.sf-seal {
  display: grid;
  place-items: center;
  width: 136px;
  height: 136px;
  margin-top: -68px;
  position: relative;
  overflow: hidden;
  border: 6px solid var(--ivory);
  border-radius: 32px;
  background: var(--paper);
  font-family: var(--font-display);
  font-size: 64px;
  font-style: italic;
  color: var(--leaf-deep);
}

.sf-seal img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sf-title {
  padding-bottom: 6px;
  min-width: 0;
}

.storefront-page .sf-title h1 {
  font-size: clamp(42px, 5.4vw, 80px);
  overflow-wrap: anywhere;
}

.sf-verified {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 10px;
  color: #5d7150;
  font-size: 13px;
  font-weight: 500;
}

.sf-verified svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
}

.sf-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  padding-bottom: 10px;
}

.sf-paused {
  max-width: 62ch;
  margin-top: 24px !important;
  padding: 12px 16px;
  border: 1px solid #f6d58e;
  border-radius: 12px;
  background: #fffaf0;
  color: #8a4b0f;
  font-size: 14px;
  line-height: 1.6;
}

.sf-desc {
  max-width: 62ch;
  margin-top: 28px !important;
  color: var(--ink-soft);
  font-size: 16px;
  line-height: 1.8;
}

.sf-facts {
  display: flex;
  flex-wrap: wrap;
  margin: 40px 0 0;
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
}

.sf-facts > div {
  flex: 1 1 180px;
  padding: 18px 24px 18px 0;
}

.sf-facts > div + div {
  padding-left: 24px;
  border-left: 1px solid var(--line);
}

.sf-facts dt,
.sf-details dt {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.sf-facts dd {
  margin-top: 2px;
  font-family: var(--font-display);
  font-size: 24px;
  font-weight: 500;
  line-height: 1.2;
}

/* ============================================================
   COLLECTION (matches Shop.vue)
============================================================= */
.sf-collection {
  padding: clamp(64px, 8vw, 110px) 0;
  scroll-margin-top: 110px;
}

.products-layout {
  display: grid;
  grid-template-columns: 240px minmax(0, 1fr);
  gap: clamp(32px, 4vw, 64px);
  align-items: start;
}

.filter-sidebar {
  position: sticky;
  top: 110px;
}

.filter-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.storefront-page .filter-header h2 {
  font-size: 30px;
}

.btn-clear-all {
  border: none;
  background: none;
  color: var(--ink);
  font-size: 13px;
  font-weight: 500;
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}

.filter-subtitle {
  margin: 6px 0 22px !important;
  color: var(--ink-soft);
  font-size: 13.5px;
}

.filter-group {
  border-top: 1px solid var(--line);
}

.filter-group:last-child {
  border-bottom: 1px solid var(--line);
}

.filter-toggle {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 17px 0;
  border: none;
  background: none;
  color: var(--ink);
  text-align: left;
  cursor: pointer;
}

.filter-label {
  font-size: 14px;
  font-weight: 500;
}

.filter-value {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  max-width: 55%;
  overflow: hidden;
  color: var(--ink-soft);
  font-size: 12.5px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chevron {
  display: inline-block;
  font-size: 18px;
  line-height: 1;
  transition: transform 0.3s var(--ease-out);
}

.chevron.open {
  transform: rotate(90deg);
}

.filter-options {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 0 0 18px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--ink);
  font-size: 14px;
  text-transform: capitalize;
  cursor: pointer;
}

.checkbox-label input {
  width: 16px;
  height: 16px;
  accent-color: var(--ink);
}

.products-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 20px;
  margin-bottom: 40px;
}

.storefront-page .products-head h2 {
  font-size: clamp(36px, 3.6vw, 56px);
}

.sort-controls {
  display: flex;
  align-items: center;
  gap: 20px;
}

.results-count {
  color: var(--ink-soft);
  font-size: 13px;
}

.sort-select {
  padding: 10px 34px 10px 16px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--paper)
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231e1b18' stroke-width='1.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E")
    no-repeat right 12px center / 14px;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 13px;
  appearance: none;
  cursor: pointer;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
  gap: 36px 24px;
}

.product-skeleton {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.product-skeleton-image {
  aspect-ratio: 4 / 5;
  border-radius: 16px;
  margin-bottom: 6px;
}

.product-skeleton-line {
  height: 14px;
}

.product-skeleton-line--short {
  width: 40%;
  height: 10px;
}

.product-skeleton-line--price {
  width: 30%;
}

.no-results {
  padding: 72px 24px;
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
  text-align: center;
}

.storefront-page .no-results h3 {
  font-size: 38px;
}

.no-results p {
  max-width: 46ch;
  margin: 14px auto 28px !important;
  color: var(--ink-soft);
}

/* ============================================================
   VISIT THE SHOP
============================================================= */
.sf-visit {
  padding: clamp(64px, 8vw, 110px) 0;
  background: var(--paper);
  border-top: 1px solid var(--line-soft);
}

.sf-visit-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
  gap: clamp(32px, 5vw, 80px);
  align-items: start;
}

.sf-visit-grid--no-map {
  grid-template-columns: minmax(0, 720px);
}

.sf-map-frame {
  position: sticky;
  top: 110px;
  overflow: hidden;
  border-radius: 24px;
  border: 1px solid var(--line);
}

.sf-map {
  height: clamp(320px, 42vw, 520px);
  z-index: 0; /* keep Leaflet's panes under the fixed header */
}

.storefront-page .sf-visit-copy h2 {
  font-size: clamp(38px, 4vw, 60px);
}

.sf-address {
  margin-top: 18px !important;
  font-size: 17px;
  line-height: 1.6;
}

.sf-visit-copy > .sf-muted {
  margin-top: 8px;
}

.sf-visit-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 24px;
  margin-top: 26px;
}

.sf-details {
  margin: 40px 0 0;
  border-top: 1px solid var(--line);
}

.sf-details > div {
  display: grid;
  grid-template-columns: 140px minmax(0, 1fr);
  gap: 16px;
  padding: 16px 0;
  border-bottom: 1px solid var(--line);
}

.sf-details dd {
  font-size: 14.5px;
  line-height: 1.6;
}

.sf-contact {
  display: grid;
  gap: 12px;
  margin-top: 32px !important;
}

.sf-contact li {
  display: grid;
  grid-template-columns: 140px minmax(0, 1fr);
  gap: 16px;
  align-items: baseline;
}

.sf-contact-label {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.sf-contact .bc-link {
  justify-self: start;
  overflow-wrap: anywhere;
}

/* ============================================================
   PORTFOLIO
============================================================= */
.sf-portfolio {
  padding: clamp(64px, 8vw, 110px) 0;
}

.storefront-page .sf-portfolio h2 {
  margin-bottom: 36px;
  font-size: clamp(36px, 3.6vw, 56px);
}

.sf-portfolio-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
}

.sf-portfolio-item {
  aspect-ratio: 4 / 5;
  padding: 0;
  overflow: hidden;
  border: none;
  border-radius: 18px;
  background: var(--ivory-deep);
  cursor: zoom-in;
}

.sf-portfolio-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s var(--ease-out);
}

.sf-portfolio-item:hover img {
  transform: scale(1.03);
}

.sf-footer {
  padding: 32px 0;
  border-top: 1px solid var(--line);
  color: var(--ink-soft);
  font-size: 13px;
}

/* ============================================================
   ERROR + SKELETON
============================================================= */
.sf-error {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 18px;
  padding-top: 200px;
  padding-bottom: 160px;
}

.storefront-page .sf-error h1 {
  font-size: clamp(40px, 5vw, 72px);
}

.sf-error p {
  max-width: 52ch;
  color: var(--ink-soft);
}

/* .sf-cover / .sf-seal set their own background, so restate the shimmer */
.sf-cover--skeleton,
.sf-seal--skeleton {
  background: linear-gradient(100deg, var(--ivory-deep) 30%, var(--paper) 50%, var(--ivory-deep) 70%);
  background-size: 300% 100%;
}

.sf-cover--skeleton {
  border-radius: 0;
}

.sf-skeleton-copy {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-bottom: 6px;
}

.sf-skeleton-title {
  width: min(420px, 80%);
  height: 48px;
}

.sf-skeleton-line {
  width: min(560px, 100%);
  height: 14px;
}

.sf-skeleton-line--short {
  width: min(320px, 60%);
}

.sf-skeleton-grid {
  margin: 72px 0 110px;
}

/* ============================================================
   MODALS (match Shop.vue)
============================================================= */
.modal-overlay {
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
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
}

@keyframes riseIn {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
}

.modal-close {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 2;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border: none;
  border-radius: 50%;
  background: rgba(250, 251, 250, 0.9);
  color: var(--ink);
  font-size: 15px;
  cursor: pointer;
  transition: background 0.3s;
}

.modal-close:hover {
  background: var(--ivory-deep);
}

.modal-content {
  position: relative;
  width: 100%;
  max-width: 1080px;
  max-height: calc(100vh - 40px);
  overflow: hidden;
  border-radius: 24px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(38, 45, 57, 0.25);
  animation: riseIn 0.45s var(--ease-out);
}

.modal-inner {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  max-height: calc(100vh - 40px);
}

.modal-left {
  position: relative;
  display: flex;
  flex-direction: column;
  min-height: 0;
  background: var(--ivory-deep);
}

.image-container {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-height: 0;
}

.main-image {
  flex: 1;
  width: 100%;
  min-height: 0;
  object-fit: cover;
}

.model-viewer {
  flex: 1;
  min-height: 420px;
}

.image-thumbnails {
  display: flex;
  gap: 8px;
  padding: 12px;
  overflow-x: auto;
}

.thumbnail {
  flex-shrink: 0;
  width: 60px;
  height: 60px;
  padding: 0;
  overflow: hidden;
  border: 1px solid transparent;
  border-radius: 10px;
  background: none;
  cursor: pointer;
  opacity: 0.65;
  transition:
    opacity 0.3s,
    border-color 0.3s;
}

.thumbnail.active,
.thumbnail:hover {
  border-color: var(--ink);
  opacity: 1;
}

.thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.view-toggle {
  position: absolute;
  left: 16px;
  bottom: 16px;
  display: flex;
  padding: 4px;
  border-radius: 999px;
  background: rgba(250, 251, 250, 0.92);
}

.view-toggle button {
  padding: 8px 16px;
  border: none;
  border-radius: 999px;
  background: none;
  color: var(--ink-soft);
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
}

.view-toggle button.active {
  background: var(--ink);
  color: var(--ivory);
}

.modal-right {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 48px 44px 40px;
  overflow-y: auto;
}

.storefront-page .modal-product-name {
  margin-top: -4px;
  font-size: clamp(36px, 3.4vw, 50px);
}

.modal-stats-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  color: var(--ink-soft);
  font-size: 13px;
}

.modal-stars {
  display: inline-flex;
  gap: 1px;
}

.modal-star {
  color: var(--line);
  font-size: 14px;
}

.modal-star.star-full {
  color: var(--gold);
}

.modal-star.star-half {
  background: linear-gradient(90deg, var(--gold) 50%, var(--line) 50%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.modal-stats-avg {
  color: var(--ink);
  font-weight: 500;
}

.modal-stats-skeleton .bc-skeleton {
  display: block;
  width: 60%;
  height: 14px;
}

.modal-price-row {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 12px;
}

.modal-price-current {
  font-family: var(--font-display);
  font-size: 40px;
  font-weight: 500;
  line-height: 1;
}

.modal-price-original {
  color: var(--ink-soft);
  font-size: 15px;
  text-decoration: line-through;
}

.modal-desc-text {
  color: var(--ink-soft);
  font-size: 15px;
  line-height: 1.8;
}

.modal-specs {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
}

.modal-spec {
  padding: 14px 0;
}

.modal-spec + .modal-spec {
  padding-left: 20px;
  border-left: 1px solid var(--line);
}

.modal-spec dt {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.modal-spec dd {
  margin-top: 4px;
  font-size: 14.5px;
  font-weight: 500;
  text-transform: capitalize;
}

.stock-pill.in-stock {
  color: #5d7150;
}

.stock-pill.low-stock {
  color: #9a6a1f;
}

.stock-pill.out-of-stock {
  color: var(--leaf-deep);
}

.modal-cart-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.quantity-selector {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--line);
  border-radius: 999px;
}

.qty-btn {
  width: 42px;
  height: 50px;
  border: none;
  background: none;
  color: var(--ink);
  font-size: 17px;
  cursor: pointer;
}

.qty-value {
  min-width: 24px;
  font-size: 15px;
  font-weight: 500;
  text-align: center;
}

.modal-btn {
  flex: 1;
  justify-content: center;
  min-width: 140px;
}

.modal-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.modal-reviews-embed {
  padding-top: 22px;
  border-top: 1px solid var(--line);
}

.modal-reviews-component {
  margin-top: 16px;
}

/* Portfolio viewer (teleported to body, so it can't use the page's tokens) */
.sf-gallery {
  position: fixed;
  inset: 0;
  z-index: 2100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(38, 45, 57, 0.9);
}

.sf-gallery img {
  max-width: min(92vw, 1100px);
  max-height: 84vh;
  border-radius: 14px;
  object-fit: contain;
}

.sf-gallery-btn {
  position: absolute;
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border: none;
  border-radius: 50%;
  background: rgba(250, 251, 250, 0.14);
  color: #fafbfa;
  font-size: 24px;
  cursor: pointer;
}

.sf-gallery-btn:hover {
  background: rgba(250, 251, 250, 0.26);
}

.sf-gallery-close {
  top: 20px;
  right: 20px;
  font-size: 16px;
}

.sf-gallery-prev {
  left: 20px;
}

.sf-gallery-next {
  right: 20px;
}

.sf-gallery-count {
  position: absolute;
  bottom: 20px;
  margin: 0;
  color: rgba(250, 251, 250, 0.75);
  font-family: "Poppins", sans-serif;
  font-size: 13px;
}

/* Focus */
.sf-back:focus-visible,
.btn-clear-all:focus-visible,
.filter-toggle:focus-visible,
.checkbox-label input:focus-visible,
.sort-select:focus-visible,
.sf-portfolio-item:focus-visible,
.modal-close:focus-visible,
.thumbnail:focus-visible,
.view-toggle button:focus-visible,
.qty-btn:focus-visible,
.sf-gallery-btn:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

/* ============================================================
   RESPONSIVE
============================================================= */
@media (max-width: 968px) {
  .sf-identity {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .sf-actions {
    grid-column: 1 / -1;
    padding-bottom: 0;
  }

  .products-layout,
  .sf-visit-grid {
    grid-template-columns: 1fr;
  }

  .filter-sidebar,
  .sf-map-frame {
    position: static;
  }

  .modal-inner {
    grid-template-columns: 1fr;
    overflow-y: auto;
  }

  .modal-left {
    min-height: 320px;
  }

  .main-image {
    max-height: 420px;
  }

  .modal-right {
    overflow: visible;
    padding: 32px 24px 28px;
  }

  .modal-content {
    overflow-y: auto;
  }
}

@media (max-width: 640px) {
  .sf-identity {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .sf-seal {
    width: 104px;
    height: 104px;
    margin-top: -52px;
    border-radius: 26px;
    font-size: 48px;
  }

  .sf-actions .bc-btn {
    flex: 1 1 auto;
    justify-content: center;
  }

  .sf-facts > div {
    flex-basis: 50%;
    padding: 14px 12px 14px 0;
  }

  .sf-facts > div + div {
    padding-left: 0;
    border-left: none;
  }

  .sf-facts dd {
    font-size: 20px;
  }

  .sf-details > div,
  .sf-contact li {
    grid-template-columns: 1fr;
    gap: 2px;
  }

  .products-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 28px 14px;
  }

  .sort-controls {
    width: 100%;
    justify-content: space-between;
  }

  .sf-portfolio-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }

  .modal-overlay {
    padding: 0;
    align-items: flex-end;
  }

  .modal-content {
    max-height: 94vh;
    border-radius: 22px 22px 0 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .modal-overlay,
  .modal-content {
    animation: none;
  }

  .sf-portfolio-item img,
  .chevron {
    transition: none;
  }
}
</style>
