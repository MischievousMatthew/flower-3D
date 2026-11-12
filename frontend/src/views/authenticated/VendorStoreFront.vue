<template>
  <div class="storefront-page bc-theme">
    <NavHeader
      ref="navHeaderRef"
      :cartCount="cartCount"
      :isAuthenticated="isAuthenticated"
      @scroll-to-section="() => {}"
    />

    <!-- Loading: skeletons shaped like the cover, the shop card and the flowers -->
    <div v-if="loading" class="sf-top" aria-busy="true">
      <span class="bc-sr-only">Loading store…</span>
      <div class="bc-skeleton sf-cover sf-cover--skeleton"></div>
      <div class="bc-container">
        <div class="sf-card sf-card--skeleton">
          <span class="bc-skeleton sf-logo sf-logo--skeleton"></span>
          <div class="sf-skel-copy">
            <span class="bc-skeleton sf-skel-title"></span>
            <span class="bc-skeleton sf-skel-line"></span>
          </div>
        </div>
        <div class="sf-grid sf-skel-grid">
          <span v-for="n in 4" :key="n" class="bc-skeleton sf-skel-tile"></span>
        </div>
      </div>
    </div>

    <!-- Store could not be loaded -->
    <section v-else-if="error" class="sf-error bc-container">
      <span class="sf-error-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M4 21V9l8-6 8 6v12" /><path d="M9 21v-6h6v6" /></svg>
      </span>
      <h1>This store isn't available</h1>
      <p>{{ error }} Head back to the shop to browse other florists.</p>
      <button class="bc-btn bc-btn--dark" @click="$router.push('/shop')">Back to the shop</button>
    </section>

    <template v-else-if="vendor.store_name">
      <main>
        <!-- ── Cover, then the shop card overlapping it ───────────────── -->
        <header class="sf-top">
          <div class="sf-cover" :class="{ 'sf-cover--empty': !vendor.store_cover_url }">
            <img
              v-if="vendor.store_cover_url"
              :src="vendor.store_cover_url"
              alt=""
              class="sf-cover-img"
              @error="vendor.store_cover_url = null"
            />
            <span v-else class="sf-cover-mark" aria-hidden="true">{{ storeInitial }}</span>
            <div class="bc-container sf-cover-bar">
              <button type="button" class="sf-back" @click="$router.push('/shop')">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                All shops
              </button>
            </div>
          </div>

          <div class="bc-container">
            <div class="sf-card">
              <div class="sf-logo">
                <img
                  v-if="vendor.store_logo_path && !logoFailed"
                  :src="vendor.store_logo_path"
                  :alt="`${vendor.store_name} logo`"
                  @error="logoFailed = true"
                />
                <span v-else aria-hidden="true">{{ storeInitial }}</span>
              </div>

              <div class="sf-who">
                <div class="sf-name-row">
                  <h1>{{ vendor.store_name }}</h1>
                  <span v-if="vendor.verification_level === 'verified'" class="sf-verified">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                    Verified
                  </span>
                </div>
                <ul class="sf-meta">
                  <li v-if="shopRating.reviews">
                    <span class="sf-star" aria-hidden="true">★</span>
                    <strong>{{ shopRating.average.toFixed(1) }}</strong>
                    <span>({{ shopRating.reviews }} {{ shopRating.reviews === 1 ? "review" : "reviews" }})</span>
                  </li>
                  <li v-if="shopSold">{{ shopSold.toLocaleString() }} sold</li>
                  <li v-if="storeCity">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11z" /><circle cx="12" cy="10" r="2.2" /></svg>
                    {{ storeCity }}
                  </li>
                  <li>
                    <span class="sf-dot" :class="{ off: vendor.taking_orders === false }" aria-hidden="true"></span>
                    {{ vendor.taking_orders === false ? "Not taking orders now" : "Taking orders" }}
                  </li>
                </ul>
              </div>

              <div class="sf-actions">
                <button type="button" class="sf-btn sf-btn--primary" @click="customizeFlowers">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 7.5v9L12 21l8-4.5v-9z" /><path d="M4 7.5 12 12l8-4.5M12 12v9" /></svg>
                  Design a bouquet
                </button>
                <button type="button" class="sf-btn sf-btn--ghost" @click="chatVendor">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H8l-4 4z" /></svg>
                  Message
                </button>
              </div>
            </div>

            <p v-if="vendor.taking_orders === false && vendor.paused_message" class="sf-paused" role="status">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M10 9v6M14 9v6" /></svg>
              {{ vendor.paused_message }}
            </p>

            <!-- Quick facts -->
            <ul class="sf-facts">
              <li v-if="vendor.price_min || vendor.price_max">
                <span class="sf-fact-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 12V4h8l10 10-8 8z" /><circle cx="7.5" cy="8.5" r="1.3" /></svg></span>
                <span><small>Prices</small><strong>{{ peso(vendor.price_min) }} – {{ peso(vendor.price_max) }}</strong></span>
              </li>
              <li>
                <span class="sf-fact-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg></span>
                <span><small>Same-day delivery</small><strong>{{ vendor.same_day_delivery ? "Available" : "Not offered" }}</strong></span>
              </li>
              <li v-if="vendor.operating_hours">
                <span class="sf-fact-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 21V9l8-6 8 6v12" /><path d="M9 21v-6h6v6" /></svg></span>
                <span><small>Open</small><strong>{{ vendor.operating_hours }}</strong></span>
              </li>
            </ul>

            <p v-if="vendor.store_description" class="sf-about">{{ vendor.store_description }}</p>

            <!-- Jump to a part of the page -->
            <nav class="sf-tabs" aria-label="On this page">
              <a href="#sf-flowers" @click.prevent="jump('sf-flowers')">Flowers <small>{{ products.length }}</small></a>
              <a href="#sf-visit" @click.prevent="jump('sf-visit')">Visit &amp; delivery</a>
              <a v-if="portfolio.length" href="#sf-work" @click.prevent="jump('sf-work')">Past work <small>{{ portfolio.length }}</small></a>
            </nav>
          </div>
        </header>

        <!-- ── The shop's flowers ─────────────────────────────────────── -->
        <section id="sf-flowers" class="sf-section" aria-labelledby="sf-flowers-title">
          <div class="bc-container">
            <header class="bc-sec-head">
              <div>
                <p class="bc-sec-eyebrow">The collection</p>
                <h2 id="sf-flowers-title" class="bc-sec-title">Flowers from <em>{{ vendor.store_name }}</em></h2>
              </div>
              <label class="sf-sort">
                <span>Sort by</span>
                <select v-model="sortBy" aria-label="Sort flowers">
                  <option value="popular">Most popular</option>
                  <option value="newest">Newest</option>
                  <option value="rating">Top rated</option>
                  <option value="price-low">Price: low to high</option>
                  <option value="price-high">Price: high to low</option>
                  <option value="name_asc">Name: A to Z</option>
                </select>
              </label>
            </header>

            <!-- Filters as chips: arrangement, then flower type and occasion -->
            <div v-if="products.length" class="sf-filters">
              <div v-for="group in filterGroups" :key="group.key" class="sf-filter-row" role="group" :aria-label="group.label">
                <span class="sf-filter-label">{{ group.label }}</span>
                <div class="sf-chips">
                  <button
                    v-for="option in group.options"
                    :key="option"
                    type="button"
                    class="sf-chip"
                    :class="{ on: filters[group.key] === option }"
                    :aria-pressed="filters[group.key] === option"
                    @click="filters[group.key] = option"
                  >
                    {{ option === "All" ? "All" : words(option) }}
                  </button>
                </div>
              </div>
              <p class="sf-count" aria-live="polite">
                {{ filteredProducts.length }} {{ filteredProducts.length === 1 ? "flower" : "flowers" }}
                <button v-if="filtersOn" type="button" class="sf-clear" @click="resetFilters">Clear filters</button>
              </p>
            </div>

            <div v-if="loadingProducts" class="sf-grid" aria-label="Loading flowers">
              <span v-for="n in 8" :key="n" class="bc-skeleton sf-skel-tile"></span>
            </div>
            <div v-else-if="products.length === 0" class="sf-empty">
              <span class="sf-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M7 10l5 11 5-11" /><circle cx="9" cy="7" r="2.5" /><circle cx="15" cy="7" r="2.5" /><circle cx="12" cy="4.5" r="2.5" /></svg>
              </span>
              <h3>Nothing in the shop yet</h3>
              <p>This florist hasn't listed any flowers. You can still design your own bouquet with them.</p>
              <button type="button" class="sf-btn sf-btn--primary" @click="customizeFlowers">Design a bouquet</button>
            </div>
            <div v-else-if="filteredProducts.length === 0" class="sf-empty">
              <h3>No flowers match these filters</h3>
              <p>Clear the filters to see everything this shop sells.</p>
              <button type="button" class="sf-btn sf-btn--ghost-dark" @click="resetFilters">Clear filters</button>
            </div>
            <div v-else class="sf-grid">
              <ShopProductTile
                v-for="product in filteredProducts"
                :key="product.id"
                :product="product"
                :adding="addingToCartProductId === product.id"
                @open="openProduct"
                @add="addToCartFromCard"
              />
            </div>
          </div>
        </section>

        <!-- ── Visit and delivery ─────────────────────────────────────── -->
        <section id="sf-visit" class="sf-section" aria-labelledby="sf-visit-title">
          <div class="bc-container">
            <div class="sf-visit" :class="{ 'no-map': !hasStorePin }">
              <div v-if="hasStorePin" class="sf-map-frame">
                <div ref="locationMapEl" class="sf-map" role="region" :aria-label="`Map showing ${vendor.store_name}`"></div>
              </div>

              <div class="sf-visit-copy">
                <p class="bc-sec-eyebrow">Visit &amp; delivery</p>
                <h2 id="sf-visit-title" class="sf-visit-title">Find <em>the shop</em></h2>
                <p class="sf-address">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11z" /><circle cx="12" cy="10" r="2.2" /></svg>
                  {{ vendor.store_address || "Address not provided" }}
                </p>
                <p v-if="!hasStorePin" class="sf-muted">This shop hasn't pinned its location on the map yet.</p>
                <div v-if="hasStorePin" class="sf-visit-actions">
                  <a :href="directionsUrl" target="_blank" rel="noopener" class="sf-btn sf-btn--primary">Get directions</a>
                  <a :href="openMapUrl" target="_blank" rel="noopener" class="sf-btn sf-btn--ghost-dark">Open in Google Maps</a>
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
                    <dd>Order by {{ parsedCutoffTimes }} <span class="sf-muted">Subject to courier availability.</span></dd>
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
                    <a :href="link.href" class="sf-contact-link" :target="link.external ? '_blank' : null" :rel="link.external ? 'noopener' : null">
                      <span class="sf-contact-label">{{ link.label }}</span>
                      {{ link.text }}
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Past arrangements ──────────────────────────────────────── -->
        <section v-if="portfolio.length" id="sf-work" class="sf-section" aria-labelledby="sf-work-title">
          <div class="bc-container">
            <header class="bc-sec-head">
              <div>
                <p class="bc-sec-eyebrow">Past work</p>
                <h2 id="sf-work-title" class="bc-sec-title">Arrangements <em>they've made</em></h2>
              </div>
            </header>
            <div class="sf-work">
              <button
                v-for="(photo, idx) in portfolio"
                :key="idx"
                type="button"
                class="sf-work-item"
                :aria-label="`View arrangement ${idx + 1}`"
                @click="openGallery(idx)"
              >
                <img :src="photo" alt="" loading="lazy" decoding="async" />
              </button>
            </div>
          </div>
        </section>
      </main>

      <SiteFooter />
    </template>

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
        <button class="sf-gallery-btn sf-gallery-close" aria-label="Close" @click.stop="closeGallery">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
        </button>
        <button v-if="portfolio.length > 1" class="sf-gallery-btn sf-gallery-prev" aria-label="Previous photo" @click.stop="prevGalleryImage">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
        </button>
        <img :src="portfolio[galleryIndex]" alt="" @click.stop />
        <button v-if="portfolio.length > 1" class="sf-gallery-btn sf-gallery-next" aria-label="Next photo" @click.stop="nextGalleryImage">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
        </button>
        <p class="sf-gallery-count">{{ galleryIndex + 1 }} of {{ portfolio.length }}</p>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from "vue";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";
import { useRoute, useRouter } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import api from "../../plugins/axios";
import { productRoute } from "../../utils/productLink.js";
import { rememberProduct } from "../../utils/productPreview.js";
import NavHeader from "../../layouts/NavHeader.vue";
import ShopProductTile from "../../components/shop/ShopProductTile.vue";
import SiteFooter from "../../components/site/SiteFooter.vue";
import { useCart } from "../../composables/useCart";
import { useFlyToCart } from "../../composables/useFlyToCart";
import { toast } from "@/utils/snackbar";
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
  store_address: "",
  store_latitude: null,
  store_longitude: null,
  service_areas: [],
  operating_hours: "",
  price_min: 0,
  price_max: 0,
  same_day_delivery: false,
  cutoff_times: "",
  default_delivery_fee: 0,
  lead_time: "",
  cancellation_policy: "",
  portfolio_photos_paths: [],
  facebook_page: "",
  instagram_page: "",
  contact_number: "",
  email: "",
  verification_level: "",
  owner_id: null,
});

const products = ref([]);
const addingToCartProductId = ref(null);
const galleryIndex = ref(null);

// ── Filters and sorting ────────────────────────────────────────────────────
const ALL = { category: "All", flowerType: "All", occasion: "All" };
const filters = ref({ ...ALL });
const sortBy = ref("popular");
const filtersOn = computed(() => Object.keys(ALL).some((k) => filters.value[k] !== "All"));

// ── Computed ───────────────────────────────────────────────────────────────
const cartCount = computed(() => cartStore.count.value);
const storeInitial = computed(() => vendor.value.store_name?.trim().charAt(0).toUpperCase() || "B");
const portfolio = computed(() => (vendor.value.portfolio_photos_paths || []).filter(Boolean));

// store_address is usually "Town, Province"; show the town
const storeCity = computed(() => (vendor.value.store_address || "").split(",").map((s) => s.trim()).filter(Boolean)[0] || "");

// The shop's rating and sales, from its flowers
const shopRating = computed(() => {
  let reviews = 0;
  let total = 0;
  for (const p of products.value) {
    const n = Number(p.total_reviews || 0);
    reviews += n;
    total += n * Number(p.average_rating || 0);
  }
  return { reviews, average: reviews ? total / reviews : 0 };
});
const shopSold = computed(() => products.value.reduce((sum, p) => sum + Number(p.sold_count || 0), 0));

const contactLinks = computed(() => {
  const v = vendor.value;
  return [
    v.contact_number && { label: "Phone", text: v.contact_number, href: `tel:${String(v.contact_number).replace(/\s+/g, "")}` },
    v.email && { label: "Email", text: v.email, href: `mailto:${v.email}` },
    v.facebook_page && { label: "Facebook", text: "Visit page", href: v.facebook_page, external: true },
    v.instagram_page && { label: "Instagram", text: "Follow", href: v.instagram_page, external: true },
  ].filter(Boolean);
});

const peso = (n) => `₱${Number(n || 0).toLocaleString("en-PH", { maximumFractionDigits: 2 })}`;
const words = (s) => String(s).replace(/[-_]/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());

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
  storePin.value ? `https://www.google.com/maps/dir/?api=1&destination=${storePin.value.latitude},${storePin.value.longitude}` : "",
);
const openMapUrl = computed(() =>
  storePin.value ? `https://www.google.com/maps/search/?api=1&query=${storePin.value.latitude},${storePin.value.longitude}` : "",
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
  L.marker(position, { icon: storeMarkerIcon }).addTo(locationMap).bindPopup(vendor.value.store_name || "Shop location");
};

onBeforeUnmount(() => {
  locationMap?.remove();
  locationMap = null;
});

// Safely coerce service_areas: a plain string "Cavite" or a JSON array
const parsedServiceAreas = computed(() => {
  const raw = vendor.value.service_areas;
  if (!raw) return [];
  if (Array.isArray(raw)) return raw.filter(Boolean);
  if (typeof raw === "string") {
    try {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) return parsed.filter(Boolean);
    } catch {}
    return raw.split(",").map((s) => s.trim()).filter(Boolean);
  }
  return [];
});

const parsedCutoffTimes = computed(() => {
  const raw = vendor.value.cutoff_times;
  if (!raw) return "—";
  try {
    const parsed = typeof raw === "string" ? JSON.parse(raw) : raw;
    if (Array.isArray(parsed) && parsed.length) return parsed.map((c) => `${c.day} ${c.time}`).join(", ");
    if (parsed?.day) return `${parsed.day} ${parsed.time}`;
  } catch {}
  return String(raw);
});

// Only groups with a real choice (more than one option) show
const uniqueOptions = (values) => ["All", ...new Set(values.filter(Boolean))];
const filterGroups = computed(() =>
  [
    { key: "category", label: "Arrangement", options: uniqueOptions(products.value.map((p) => p.category)) },
    { key: "flowerType", label: "Flower type", options: uniqueOptions(products.value.map((p) => p.flower_type)) },
    { key: "occasion", label: "Occasion", options: uniqueOptions(products.value.flatMap((p) => p.occasion_tags || [])) },
  ].filter((g) => g.options.length > 2 || filters.value[g.key] !== "All"),
);

const getDisplayPrice = (p) => (p.has_discount && p.discount_price ? Number(p.discount_price) : Number(p.selling_price || 0));

const filteredProducts = computed(() => {
  const f = filters.value;
  const list = products.value.filter(
    (p) =>
      (f.category === "All" || p.category === f.category) &&
      (f.flowerType === "All" || p.flower_type === f.flowerType) &&
      (f.occasion === "All" || (p.occasion_tags || []).includes(f.occasion)),
  );
  const sorters = {
    popular: (a, b) => Number(b.sold_count || 0) - Number(a.sold_count || 0) || new Date(b.created_at) - new Date(a.created_at),
    rating: (a, b) => Number(b.average_rating || 0) - Number(a.average_rating || 0) || Number(b.total_reviews || 0) - Number(a.total_reviews || 0),
    "price-low": (a, b) => getDisplayPrice(a) - getDisplayPrice(b),
    "price-high": (a, b) => getDisplayPrice(b) - getDisplayPrice(a),
    name_asc: (a, b) => a.product_name.localeCompare(b.product_name),
    newest: (a, b) => new Date(b.created_at) - new Date(a.created_at),
  };
  return [...list].sort(sorters[sortBy.value] || sorters.popular);
});

const resetFilters = () => {
  filters.value = { ...ALL };
};

const isStorefrontVisibleProduct = (product) => ["per_piece", "bouquet", "", null, undefined].includes(product?.selling_type);

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
    if (data.success) vendor.value = data.data;
    else error.value = data.message || "Store not found.";
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

// The shop's flowers from the same list as the Shop page, so the cards show
// ratings, sold counts and tags; the storefront list is the fallback
const fetchProducts = async () => {
  loadingProducts.value = true;
  try {
    let list = null;
    if (vendor.value.owner_id) {
      try {
        const { data } = await api.get("customer/products", {
          params: { owner_ids: String(vendor.value.owner_id), per_page: 100, in_stock_only: 0, sort_by: "popular_desc" },
        });
        if (data?.success) list = Array.isArray(data.data?.data) ? data.data.data : [];
      } catch {
        list = null;
      }
    }
    if (list === null) {
      const { data } = await api.get(`vendors/${vendorId.value}/products`);
      list = data.success && Array.isArray(data.data) ? data.data : [];
    }
    products.value = list.filter(isStorefrontVisibleProduct);
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

// ── Actions ────────────────────────────────────────────────────────────────
const goToLogin = () => {
  router.push({ path: "/guest/login", query: { redirect: route.fullPath } });
};

const openProduct = (product) => {
  rememberProduct(product);
  router.push(productRoute(product.id));
};

const addToCartFromCard = async (product, context = {}) => {
  if (!isAuthenticated.value) {
    goToLogin();
    return;
  }
  addingToCartProductId.value = product.id;
  try {
    const r = await cartStore.addToCart({ product_id: product.id, quantity: 1 });
    if (r.success) {
      await flyToCart(context.imageEl);
      navHeaderRef.value?.triggerCartPulse?.();
      toast.success(`${product.product_name} added to cart!`, { autoClose: 2000 });
    }
  } catch {
    toast.error("Couldn't add that to your cart. Please try again.", { autoClose: 3000 });
  } finally {
    addingToCartProductId.value = null;
  }
};

const chatVendor = () => {
  if (!isAuthenticated.value) {
    goToLogin();
    return;
  }
  // Chat conversations are between user accounts, so pass the vendor's
  // user id (owner_id), not the store id.
  if (!vendor.value?.owner_id) {
    toast.error("This shop can't receive messages yet.");
    return;
  }
  router.push({
    path: "/customer/chat",
    query: { vendor_id: String(vendor.value.owner_id), vendor_name: vendor.value?.store_name || "" },
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

const jump = (id) => {
  const reduce = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  document.getElementById(id)?.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
};

// ── Portfolio viewer ───────────────────────────────────────────────────────
const openGallery = (i) => (galleryIndex.value = i);
const closeGallery = () => (galleryIndex.value = null);
const nextGalleryImage = () => {
  galleryIndex.value = (galleryIndex.value + 1) % portfolio.value.length;
};
const prevGalleryImage = () => {
  const l = portfolio.value.length;
  galleryIndex.value = (galleryIndex.value - 1 + l) % l;
};
// Arrow keys and Escape while the viewer is open; the page doesn't scroll behind it
const onGalleryKey = (e) => {
  if (galleryIndex.value === null) return;
  if (e.key === "Escape") closeGallery();
  else if (e.key === "ArrowRight") nextGalleryImage();
  else if (e.key === "ArrowLeft") prevGalleryImage();
};
watch(galleryIndex, (i) => {
  document.body.style.overflow = i === null ? "" : "hidden";
});
onMounted(() => window.addEventListener("keydown", onGalleryKey));
onBeforeUnmount(() => {
  window.removeEventListener("keydown", onGalleryKey);
  document.body.style.overflow = "";
});

// ── Lifecycle ──────────────────────────────────────────────────────────────
onMounted(async () => {
  await fetchVendor();
  await loadCart();
});
</script>

<style scoped>
/* Tokens, type, buttons, section headings and skeletons come from
   src/styles/bloomcraft.css (shared with the shop). */
@layer bc-reset {
  .storefront-page *,
  .storefront-page *::before,
  .storefront-page *::after {
    box-sizing: border-box;
  }

  .storefront-page :is(p, h1, h2, h3, ul, dl, dd) {
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
  min-height: 100vh;
  overflow-x: hidden;
  background: var(--ivory);
  color: var(--ink);
}

.storefront-page button {
  font-family: var(--font-sans);
}

.storefront-page svg {
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

/* ── Cover ─────────────────────────────────────────────────────────── */
.sf-top {
  padding-top: var(--shop-nav-h, 96px);
}

.sf-cover {
  position: relative;
  height: clamp(220px, 28vw, 360px);
  overflow: hidden;
  background: linear-gradient(120deg, #dcefe2, #f6ece6);
}

.sf-cover::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(38, 45, 57, 0.35) 0%, rgba(38, 45, 57, 0) 40%, rgba(38, 45, 57, 0.2) 100%);
  pointer-events: none;
}

.sf-cover--empty::after {
  background: none;
}

.sf-cover-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sf-cover-mark {
  position: absolute;
  right: 6%;
  bottom: -40px;
  font-family: var(--font-display);
  font-size: clamp(180px, 22vw, 320px);
  font-style: italic;
  line-height: 1;
  color: rgba(46, 122, 79, 0.12);
}

.sf-cover-bar {
  position: absolute;
  top: 18px;
  right: 0;
  left: 0;
  z-index: 1;
}

.sf-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 40px;
  padding: 0 16px 0 10px;
  border: 0;
  border-radius: 999px;
  background: rgba(250, 251, 250, 0.92);
  color: var(--ink);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  backdrop-filter: blur(6px);
  transition: background 0.2s ease;
}

.sf-back:hover {
  background: var(--paper);
}

.sf-back svg {
  width: 16px;
  height: 16px;
  stroke-width: 2;
}

.sf-cover--skeleton {
  display: block;
  border-radius: 0;
}

/* ── The shop card, overlapping the cover ─────────────────────────── */
.sf-card {
  position: relative;
  z-index: 2;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 22px;
  margin-top: -64px;
  padding: 22px 26px;
  border: 1px solid var(--line-soft);
  border-radius: 24px;
  background: var(--paper);
  box-shadow: 0 24px 50px -32px rgba(38, 45, 57, 0.45);
}

.sf-logo {
  display: grid;
  place-items: center;
  width: 96px;
  height: 96px;
  overflow: hidden;
  border: 4px solid var(--paper);
  border-radius: 24px;
  background: #e9f5ed;
  box-shadow: 0 10px 24px -14px rgba(38, 45, 57, 0.5);
  font-family: var(--font-display);
  font-size: 44px;
  font-style: italic;
  color: var(--leaf-deep);
}

.sf-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sf-name-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
}

.sf-name-row h1 {
  font-family: var(--font-display);
  font-size: clamp(32px, 3.4vw, 46px);
  font-weight: 500;
  line-height: 1.05;
  color: var(--ink);
}

.sf-verified {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px 4px 7px;
  border-radius: 999px;
  background: #e9f5ed;
  color: var(--leaf-deep);
  font-size: 12px;
  font-weight: 600;
}

.sf-verified svg {
  width: 14px;
  height: 14px;
  stroke-width: 2.4;
}

.sf-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 18px;
  margin-top: 8px;
  font-size: 13.5px;
  color: var(--ink-soft);
}

.sf-meta li {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.sf-meta strong {
  color: var(--ink);
  font-weight: 600;
}

.sf-meta svg {
  width: 15px;
  height: 15px;
}

.sf-star {
  color: #e2a23b;
}

.sf-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--leaf);
  box-shadow: 0 0 0 3px rgba(77, 177, 115, 0.2);
}

.sf-dot.off {
  background: #d18a1c;
  box-shadow: 0 0 0 3px rgba(209, 138, 28, 0.2);
}

.sf-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

/* Buttons */
.sf-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 48px;
  padding: 0 22px;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.sf-btn svg {
  width: 18px;
  height: 18px;
}

.sf-btn--primary {
  background: var(--ink);
  color: var(--paper);
}

.sf-btn--primary:hover {
  background: var(--leaf-deep);
}

.sf-btn--ghost,
.sf-btn--ghost-dark {
  border-color: var(--line);
  background: var(--paper);
  color: var(--ink);
}

.sf-btn--ghost:hover,
.sf-btn--ghost-dark:hover {
  border-color: var(--ink);
}

.sf-paused {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 14px;
  padding: 12px 16px;
  border: 1px solid rgba(209, 138, 28, 0.3);
  border-radius: 14px;
  background: #fdf4e4;
  color: #8a5a10;
  font-size: 14px;
}

.sf-paused svg {
  flex: none;
  width: 18px;
  height: 18px;
}

/* Quick facts */
.sf-facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  margin-top: 16px;
}

.sf-facts li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid var(--line-soft);
  border-radius: 16px;
  background: var(--paper);
}

.sf-fact-icon {
  display: grid;
  flex: none;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #e9f5ed;
  color: var(--leaf-deep);
}

.sf-fact-icon svg {
  width: 20px;
  height: 20px;
}

.sf-facts li > span:last-child {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.sf-facts small {
  font-size: 12px;
  color: var(--ink-soft);
}

.sf-facts strong {
  overflow: hidden;
  font-size: 14px;
  font-weight: 600;
  color: var(--ink);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sf-about {
  max-width: 72ch;
  margin-top: 22px;
  font-size: 15px;
  line-height: 1.75;
  color: var(--ink-soft);
}

/* Jump links */
.sf-tabs {
  position: sticky;
  top: 84px;
  z-index: 5;
  display: flex;
  gap: 4px;
  width: max-content;
  max-width: 100%;
  margin-top: 26px;
  padding: 4px;
  overflow-x: auto;
  border: 1px solid var(--line-soft);
  border-radius: 999px;
  background: rgba(250, 251, 250, 0.92);
  box-shadow: 0 10px 24px -18px rgba(38, 45, 57, 0.4);
  backdrop-filter: blur(8px);
  scrollbar-width: none;
}

.sf-tabs a {
  display: inline-flex;
  flex: none;
  align-items: center;
  gap: 6px;
  min-height: 40px;
  padding: 0 16px;
  border-radius: 999px;
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.2s ease;
}

.sf-tabs a:hover {
  background: var(--ivory);
}

.sf-tabs small {
  padding: 1px 7px;
  border-radius: 999px;
  background: var(--ivory-deep);
  font-size: 11px;
  font-weight: 600;
}

/* ── Sections ──────────────────────────────────────────────────────── */
.sf-section {
  padding-top: clamp(48px, 6vw, 80px);
  scroll-margin-top: 90px;
}

.sf-sort {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--ink-soft);
}

.sf-sort select {
  min-height: 44px;
  padding: 0 36px 0 14px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--paper)
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23363f4e' stroke-width='1.6' stroke-linecap='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E")
    no-repeat right 12px center / 16px;
  color: var(--ink);
  font: inherit;
  font-size: 13.5px;
  font-weight: 500;
  appearance: none;
  cursor: pointer;
}

.sf-filters {
  display: grid;
  gap: 10px;
  margin-bottom: 22px;
  padding: 16px 18px;
  border: 1px solid var(--line-soft);
  border-radius: 20px;
  background: var(--paper);
}

.sf-filter-row {
  display: grid;
  grid-template-columns: 110px minmax(0, 1fr);
  align-items: center;
  gap: 12px;
}

.sf-filter-label {
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ink-soft);
}

.sf-chips {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  scrollbar-width: none;
}

.sf-chip {
  flex: none;
  min-height: 36px;
  padding: 0 14px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--paper);
  color: var(--ink);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.sf-chip:hover {
  border-color: var(--ink);
}

.sf-chip.on {
  border-color: var(--leaf-deep);
  background: var(--leaf-deep);
  color: var(--paper);
}

.sf-count {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-top: 6px;
  border-top: 1px solid var(--line-soft);
  font-size: 13px;
  color: var(--ink-soft);
}

.sf-clear {
  padding: 0;
  border: 0;
  background: none;
  color: var(--leaf-deep);
  font-size: 13px;
  font-weight: 500;
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}

.sf-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
}

.sf-skel-tile {
  height: 340px;
  border-radius: 14px;
}

.sf-empty {
  display: grid;
  justify-items: center;
  gap: 12px;
  padding: 56px 20px;
  border: 1px dashed var(--line);
  border-radius: 24px;
  text-align: center;
}

.sf-empty-icon {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 18px;
  background: #e9f5ed;
  color: var(--leaf-deep);
}

.sf-empty-icon svg {
  width: 28px;
  height: 28px;
}

.sf-empty h3 {
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 500;
}

.sf-empty p {
  max-width: 44ch;
  font-size: 14.5px;
  color: var(--ink-soft);
}

/* ── Visit ─────────────────────────────────────────────────────────── */
.sf-visit {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
  overflow: hidden;
  border: 1px solid var(--line-soft);
  border-radius: 28px;
  background: var(--paper);
}

.sf-visit.no-map {
  grid-template-columns: minmax(0, 1fr);
}

.sf-map-frame {
  min-height: 420px;
  background: var(--ivory-deep);
}

.sf-map {
  width: 100%;
  height: 100%;
  min-height: 420px;
}

.sf-visit-copy {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: clamp(24px, 3.5vw, 44px);
}

.sf-visit-copy .bc-sec-eyebrow {
  margin: 0;
}

.sf-visit-title {
  font-family: var(--font-display);
  font-size: clamp(30px, 3vw, 40px);
  font-weight: 500;
  line-height: 1.05;
}

.sf-visit-title em {
  color: var(--leaf-deep);
}

.sf-address {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 15px;
  line-height: 1.55;
  color: var(--ink);
}

.sf-address svg {
  flex: none;
  width: 18px;
  height: 18px;
  margin-top: 2px;
  color: var(--leaf-deep);
}

.sf-visit-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.sf-details {
  display: grid;
  gap: 0;
  margin-top: 6px;
  border-top: 1px solid var(--line-soft);
}

.sf-details > div {
  display: grid;
  grid-template-columns: 140px minmax(0, 1fr);
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid var(--line-soft);
}

.sf-details dt {
  font-size: 13px;
  color: var(--ink-soft);
}

.sf-details dd {
  font-size: 14px;
  line-height: 1.55;
  color: var(--ink);
}

.sf-details dd .sf-muted {
  display: block;
}

.sf-muted {
  font-size: 13px;
  color: var(--ink-soft);
}

.sf-contact {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.sf-contact-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 16px;
  border: 1px solid var(--line);
  border-radius: 999px;
  color: var(--ink);
  font-size: 13.5px;
  text-decoration: none;
  transition: border-color 0.2s ease;
}

.sf-contact-link:hover {
  border-color: var(--ink);
}

.sf-contact-label {
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--leaf-deep);
}

/* ── Past work ─────────────────────────────────────────────────────── */
.sf-work {
  columns: 4 220px;
  column-gap: 14px;
}

.sf-work-item {
  display: block;
  width: 100%;
  margin: 0 0 14px;
  padding: 0;
  overflow: hidden;
  border: 0;
  border-radius: 18px;
  background: var(--ivory-deep);
  cursor: zoom-in;
  break-inside: avoid;
}

.sf-work-item img {
  display: block;
  width: 100%;
  height: auto;
  transition: transform 0.5s var(--ease-out);
}

.sf-work-item:hover img {
  transform: scale(1.04);
}

/* ── Viewer ────────────────────────────────────────────────────────── */
.sf-gallery {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: grid;
  place-items: center;
  padding: 60px 72px;
  background: rgba(20, 24, 31, 0.92);
}

.sf-gallery img {
  max-width: 100%;
  max-height: calc(100vh - 140px);
  border-radius: 16px;
  object-fit: contain;
}

.sf-gallery-btn {
  position: absolute;
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border: 0;
  border-radius: 50%;
  background: rgba(250, 251, 250, 0.12);
  color: #fafbfa;
  cursor: pointer;
  transition: background 0.2s ease;
}

.sf-gallery-btn:hover {
  background: rgba(250, 251, 250, 0.24);
}

.sf-gallery-btn svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sf-gallery-close {
  top: 18px;
  right: 18px;
}

.sf-gallery-prev {
  left: 18px;
}

.sf-gallery-next {
  right: 18px;
}

.sf-gallery-count {
  position: absolute;
  bottom: 20px;
  margin: 0;
  color: rgba(250, 251, 250, 0.75);
  font-size: 13px;
}

/* ── Error and skeletons ───────────────────────────────────────────── */
.sf-error {
  display: grid;
  justify-items: center;
  gap: 14px;
  padding: calc(var(--shop-nav-h, 96px) + 80px) 0 120px;
  text-align: center;
}

.sf-error-icon {
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  border-radius: 20px;
  background: #e9f5ed;
  color: var(--leaf-deep);
}

.sf-error-icon svg {
  width: 30px;
  height: 30px;
}

.sf-error h1 {
  font-family: var(--font-display);
  font-size: 40px;
  font-weight: 500;
}

.sf-error p {
  max-width: 46ch;
  color: var(--ink-soft);
}

.sf-card--skeleton {
  grid-template-columns: auto minmax(0, 1fr);
}

.sf-logo--skeleton {
  border: 0;
}

.sf-skel-copy {
  display: grid;
  gap: 10px;
}

.sf-skel-title {
  width: min(320px, 70%);
  height: 34px;
  border-radius: 8px;
}

.sf-skel-line {
  width: min(240px, 50%);
  height: 14px;
  border-radius: 6px;
}

.sf-skel-grid {
  margin-top: 40px;
}

/* Focus */
.storefront-page :is(button, a, select):focus-visible,
.sf-gallery :is(button):focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

/* ── Smaller screens ───────────────────────────────────────────────── */
@media (max-width: 1080px) {
  .sf-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .sf-card {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .sf-actions {
    grid-column: 1 / -1;
  }

  .sf-actions .sf-btn {
    flex: 1 1 auto;
  }

  .sf-visit {
    grid-template-columns: minmax(0, 1fr);
  }

  .sf-map-frame,
  .sf-map {
    min-height: 280px;
  }
}

@media (max-width: 640px) {
  .sf-card {
    gap: 14px;
    margin-top: -48px;
    padding: 18px;
  }

  .sf-logo {
    width: 72px;
    height: 72px;
    border-radius: 18px;
    font-size: 34px;
  }

  .sf-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .sf-filter-row {
    grid-template-columns: minmax(0, 1fr);
    gap: 6px;
  }

  .sf-details > div {
    grid-template-columns: minmax(0, 1fr);
    gap: 2px;
  }

  .sf-sort span {
    display: none;
  }

  .sf-tabs {
    top: 72px;
  }

  .sf-gallery {
    padding: 64px 12px;
  }

  .sf-gallery-prev,
  .sf-gallery-next {
    top: auto;
    bottom: 12px;
  }

  .sf-gallery-prev {
    left: calc(50% - 96px);
  }

  .sf-gallery-next {
    right: calc(50% - 96px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .sf-work-item img,
  .sf-btn,
  .sf-chip {
    transition: none;
  }
}
</style>
