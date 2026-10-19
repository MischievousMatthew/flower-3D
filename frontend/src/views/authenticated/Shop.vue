<template>
  <div class="shop-page bc-theme">
    <NavHeader
      ref="navHeaderRef"
      :cartCount="cartCount"
      :isAuthenticated="isAuthenticated"
      @scroll-to-section="scrollToSection"
    />

    <!-- Login prompt for guests -->
    <div
      v-if="showAuthModal"
      class="auth-modal-overlay"
      @click="closeAuthModal"
    >
      <div
        class="auth-modal-content"
        role="dialog"
        aria-modal="true"
        aria-labelledby="auth-modal-title"
        @click.stop
      >
        <button
          class="auth-modal-close"
          aria-label="Close"
          @click="closeAuthModal"
        >
          &#x2715;
        </button>
        <p class="bc-label">Members only</p>
        <h3 id="auth-modal-title">Login required</h3>
        <p class="auth-modal-text">
          Please login to add items to cart, or view product details.
        </p>
        <div class="auth-modal-actions">
          <button @click="goToLogin" class="bc-btn bc-btn--dark">
            Login now
          </button>
          <button @click="goToRegister" class="bc-btn shop-btn-outline">
            Create account
          </button>
        </div>
        <p class="auth-modal-note">
          Continue browsing as guest?
          <button @click="closeAuthModal" class="btn-continue">
            Continue browsing
          </button>
        </p>
      </div>
    </div>

    <!-- Announcements, then the hero slideshow (Admin → Website) -->
    <div class="shop-top">
      <div v-if="site.announcements.length" class="bc-container shop-announce">
        <AnnouncementBar />
      </div>
      <ShopHero :banners="site.banners" @shop="scrollToSection('products')" @design="openDesigner" />
    </div>

    <ShopTrustStrip variant="top" />

    <ShopOccasions
      id="occasions"
      :occasions="filterOptions.occasions"
      :images="occasionImages"
      :active="selectedFilters.occasion.length === 1 ? selectedFilters.occasion[0] : null"
      :loading="!filterOptionsLoaded"
      :promotions="site.promotions"
      @pick="shopByOccasion"
      @browse="scrollToSection('products')"
    />

    <!-- Top sellers and featured shops, with the bouquet finder beside them -->
    <section class="shop-picks" aria-label="Popular right now">
      <div class="bc-container shop-picks-grid">
        <div class="shop-picks-main">
          <div class="shop-row-head">
            <h2>Top Selling Flowers</h2>
            <button type="button" class="shop-row-all" @click="viewAllPopular">View all <span aria-hidden="true">→</span></button>
          </div>
          <div v-if="loadingPopular" class="top-grid" aria-label="Loading top sellers">
            <span v-for="n in 5" :key="n" class="bc-skeleton top-skel"></span>
          </div>
          <div v-else-if="topSelling.length" class="top-grid">
            <ShopProductTile
              v-for="(product, i) in topSelling"
              :key="`top-${product.id}`"
              :product="product"
              :best-seller="i === 0 && Number(product.sold_count) > 0"
              :adding="addingToCartProductId === product.id"
              @open="openProductModal"
              @add="addToCartDirect"
            />
          </div>
          <p v-else class="shop-empty-line">Top sellers show up here once shops start selling.</p>

          <!-- Everything, newest first; filters from occasions, the finder and admin links land here -->
          <section id="products" class="fresh" aria-labelledby="fresh-title">
            <div class="shop-row-head">
              <h2 id="fresh-title">Fresh Flowers</h2>
              <span class="fresh-count" v-if="!isLoading">{{ pagination.total }} {{ pagination.total === 1 ? "design" : "designs" }}</span>
            </div>
            <ul v-if="activeFilterChips.length" class="fresh-chips" aria-label="Filters">
              <li v-for="chip in activeFilterChips" :key="chip.key">
                <button type="button" class="fresh-chip" :aria-label="`Remove filter ${chip.label}`" @click="removeFilterChip(chip)">
                  {{ chip.label }} <span aria-hidden="true">✕</span>
                </button>
              </li>
              <li><button type="button" class="fresh-clear" @click="clearAllFilters">Clear all</button></li>
            </ul>

            <div v-if="isLoading && !products.length" class="fresh-grid" aria-label="Loading flowers">
              <span v-for="n in 8" :key="n" class="bc-skeleton fresh-skel"></span>
            </div>
            <div v-else-if="products.length" class="fresh-grid">
              <ShopProductTile
                v-for="product in products"
                :key="`fresh-${product.id}`"
                :product="product"
                :adding="addingToCartProductId === product.id"
                @open="openProductModal"
                @add="addToCartDirect"
              />
            </div>
            <p v-else class="shop-empty-line">
              {{ activeFilterChips.length ? "Nothing matches these filters yet. Try removing one." : "Flowers show up here as shops add them." }}
            </p>

            <div v-if="pagination.current_page < pagination.last_page" class="fresh-more">
              <button type="button" class="fresh-more-btn" :disabled="isLoading" @click="loadMoreProducts">
                {{ isLoading ? "Loading…" : "Load more flowers" }}
              </button>
            </div>
          </section>

          <FeaturedShops
            class="shop-picks-shops"
            :shops="vendors"
            :loading="loadingVendors"
            :nearby="nearbyById"
            :by-road="nearbyByRoad"
            @visit="goToStorefront"
          />
        </div>

        <BouquetFinder
          class="shop-picks-finder"
          :occasions="filterOptions.occasions"
          :colors="filterOptions.colors"
          :adding-id="addingToCartProductId"
          @open="openProductModal"
          @add="addToCartDirect"
          @see-all="showFinderMatches"
        />
      </div>
    </section>

    <!-- Featured picks (Admin → Website) -->
    <section v-if="featuredProducts.length" class="shop-featured" id="featured" aria-labelledby="featured-title">
      <div class="bc-container">
        <div class="bc-section-head shop-head">
          <p class="bc-label">Featured picks</p>
          <h2 id="featured-title">Chosen by <em>BloomCraft</em></h2>
        </div>
        <div class="products-grid">
          <ProductCard
            v-for="product in featuredProducts"
            :key="`featured-${product.id}`"
            :product="product"
            :selected-vendor="selectedVendor"
            :adding-to-cart="addingToCartProductId === product.id"
            @open-modal="openProductModal"
            @add-to-cart="addToCartDirect"
            @buy-now="buyNowDirect"
            @select-vendor="selectVendorById"
          />
        </div>
      </div>
    </section>

    <!-- Journal (Admin → Website → Journal) -->
    <JournalTeaser :articles="site.articles" />

    <ShopTrustStrip variant="bottom" />

    <SiteFooter />

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import api from "../../plugins/axios.js";
import NavHeader from "../../layouts/NavHeader.vue";
import productService from "../../services/productService.js";
import { useCart } from "../../composables/useCart";
import { useFlyToCart } from "../../composables/useFlyToCart";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import ProductCard from "../../layouts/components/ProductCard.vue";
import AnnouncementBar from "../../components/site/AnnouncementBar.vue";
import ShopHero from "../../components/shop/ShopHero.vue";
import ShopTrustStrip from "../../components/shop/ShopTrustStrip.vue";
import ShopOccasions from "../../components/shop/ShopOccasions.vue";
import ShopProductTile from "../../components/shop/ShopProductTile.vue";
import BouquetFinder from "../../components/shop/BouquetFinder.vue";
import FeaturedShops from "../../components/shop/FeaturedShops.vue";
import JournalTeaser from "../../components/site/JournalTeaser.vue";
import SiteFooter from "../../components/site/SiteFooter.vue";
import { useSiteContent } from "../../composables/useSiteContent";
import "../../styles/bloomcraft.css";

const router = useRouter();
const route = useRoute();
const { site } = useSiteContent();

// ── Website content (Admin → Website) ─────────────────────────────────────
const featuredProducts = ref([]);
const fetchFeatured = async () => {
  try {
    const { data } = await api.get("customer/products/featured");
    if (data.success) featuredProducts.value = data.data || [];
  } catch {
    featuredProducts.value = [];
  }
};
// Most popular products: the Top Selling row, and a photo for each occasion circle
const popularProducts = ref([]);
const loadingPopular = ref(true);
const fetchPopular = async () => {
  try {
    const { data } = await api.get("customer/products", {
      params: { page: 1, per_page: 40, sort_by: "popular_desc", in_stock_only: 1 },
    });
    const list = Array.isArray(data?.data?.data) ? data.data.data : [];
    popularProducts.value = list.filter(isShopVisibleProduct);
  } catch {
    popularProducts.value = [];
  } finally {
    loadingPopular.value = false;
  }
};
const topSelling = computed(() => popularProducts.value.slice(0, 5));
const nearbyById = computed(() => Object.fromEntries(nearbyStores.value.map((store) => [store.id, store.distance_km])));
const productImage = (p) => p.primary_image?.image_url || p.images?.[0]?.image_url || null;
const occasionImages = computed(() => {
  const images = {};
  for (const product of popularProducts.value) {
    const image = productImage(product);
    if (!image) continue;
    for (const tag of product.occasion_tags || []) images[tag] ??= image;
  }
  return images;
});

// Categories that currently have something to buy, in the admin's order
const { isAuthenticated, user } = useAuth();
const cartStore = useCart();
const { flyToCart } = useFlyToCart();

const navHeaderRef = ref(null);
const addingToCart = ref(false);
const addingToCartProductId = ref(null);
const addingToCartModal = ref(false);
const show3D = ref(false);
const showProductModal = ref(false);
const selectedProduct = ref(null);
const selectedProductImageRef = ref(null);
const quantity = ref(1);
const showAuthModal = ref(false);
const pendingAction = ref(null);
const sortBy = ref("created_at_desc");
const isLoading = ref(false);
const products = ref([]);

// Live stats for the open product modal
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

// Customer locations are intentionally city reference points, not customer pins.
// Vendor coordinates always come from the vendor's saved registration map pin.
// How many live products are tagged with an occasion (0 shows as "Coming soon")
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
// True once the filter options request has finished (drives the occasion skeletons).
const filterOptionsLoaded = ref(false);

// ── Computed ──────────────────────────────────────────────────────────────
const cartCount = computed(() => cartStore.count.value);
// The customer's pinned location from their profile; older accounts without
// a pin fall back to the centre of their city.
// ── Star helper ───────────────────────────────────────────────────────────
// ── Live stats (fetched when modal opens) ─────────────────────────────────
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
    cartStore.resetCart();
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
      const vendorData = Array.isArray(r.data.data?.data)
        ? r.data.data.data
        : [];
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
// ── Stores near the customer's pinned location ────────────────────────────
const nearbyStores = ref([]);
const nearbyHasPin = ref(false);
const nearbyLoaded = ref(false);
const nearbyRadiusKm = ref(5);
const nearbyByRoad = ref(true); // false only if the routing service was unavailable
const fetchNearbyStores = async () => {
  if (!isAuthenticated.value) return;
  try {
    const { data } = await api.get("/stores/nearby");
    if (data.success) {
      nearbyStores.value = data.data || [];
      nearbyHasPin.value = !!data.has_pin;
      nearbyRadiusKm.value = data.radius_km || 5;
      nearbyByRoad.value = data.distance_type !== "straight";
    }
  } catch (error) {
    console.error("fetchNearbyStores:", error);
  } finally {
    nearbyLoaded.value = true;
  }
};

const goToStorefront = (storeId) =>
  router.push({
    name: "VendorStorefront",
    state: { storeId: String(storeId) },
  });
// ── Filters ───────────────────────────────────────────────────────────────
const isShopVisibleProduct = (product) =>
  ["per_piece", "bouquet", "", null, undefined].includes(product?.selling_type);

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
// Set by loadMoreProducts so the next page adds to the list instead of replacing it
let appendNextPage = false;
const loadMoreProducts = async () => {
  if (pagination.value.current_page >= pagination.value.last_page) return;
  pagination.value.current_page++;
  appendNextPage = true;
  try {
    await fetchProducts();
  } finally {
    appendNextPage = false;
  }
};

// The filters in use, as removable chips above Fresh Flowers
const activeFilterChips = computed(() => {
  const f = selectedFilters.value;
  const label = (s) => String(s).replace(/[-_]/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
  const chips = [
    ...f.occasion.map((v) => ({ key: `occasion-${v}`, type: "occasion", value: v, label: v })),
    ...f.category.map((v) => ({ key: `category-${v}`, type: "category", value: v, label: site.categories.find((c) => c.value === v)?.label || label(v) })),
    ...f.color.map((v) => ({ key: `color-${v}`, type: "color", value: v, label: label(v) })),
  ];
  if (f.priceMin !== null || (f.priceMax !== null && f.priceMax !== Math.round(filterOptions.value.price_range?.max ?? -1))) {
    const min = f.priceMin !== null ? `₱${f.priceMin}` : "";
    const max = f.priceMax !== null ? `₱${f.priceMax}` : "";
    chips.push({ key: "price", type: "price", label: min && max ? `${min} – ${max}` : min ? `From ${min}` : `Up to ${max}` });
  }
  return chips;
});
const removeFilterChip = (chip) => {
  const f = selectedFilters.value;
  if (chip.type === "price") {
    f.priceMin = null;
    f.priceMax = null;
  } else {
    f[chip.type] = f[chip.type].filter((v) => v !== chip.value);
  }
  pagination.value.current_page = 1;
  fetchProducts();
};

const fetchProducts = async () => {
  try {
    isLoading.value = true;
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
        products.value = (Array.isArray(r.data.data) ? r.data.data : []).filter(
          isShopVisibleProduct,
        );
        pagination.value = {
          current_page: 1,
          last_page: 1,
          per_page: products.value.length,
          total: products.value.length,
        };
      } else {
        const page = (
          Array.isArray(r.data.data.data) ? r.data.data.data : []
        ).filter(isShopVisibleProduct);
        products.value = appendNextPage ? [...products.value, ...page.filter((p) => !products.value.some((q) => q.id === p.id))] : page;
        pagination.value = {
          current_page: r.data.data.current_page,
          last_page: r.data.data.last_page,
          per_page: r.data.data.per_page,
          total: r.data.data.total,
        };
      }
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
      }
    }
  } catch (e) {
  } finally {
    filterOptionsLoaded.value = true;
  }
};
const loadCart = async () => {
  if (!isAuthenticated.value) return;
  await cartStore.initialize();
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
  pagination.value.current_page = 1;
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
// ── Pagination ────────────────────────────────────────────────────────────
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
const addToCartModalAction = async (product, qty, animationContext = {}) => {
  if (!product) return;
  addingToCartModal.value = true;
  const s = await addToCartDirect(product, qty, animationContext);
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
const SECTION_ALIASES = { sale: "products" };
const scrollToSection = (id) => {
  const el = document.getElementById(SECTION_ALIASES[id] || id);
  if (el) el.scrollIntoView({ behavior: "smooth" });
};

// Shop by category: filter the grid to one category and jump to it.
// Links from banners, announcements and promotions arrive as
// ?category=…, ?occasion=… or ?section=products
const applyContentLink = (query) => {
  const category = typeof query.category === "string" ? query.category : null;
  const occasion = typeof query.occasion === "string" ? query.occasion : null;
  if (category) selectedFilters.value.category = [category];
  if (occasion) selectedFilters.value.occasion = [occasion];
  return Boolean(category || occasion || query.section === "products");
};
watch(
  () => route.query,
  (query) => {
    if (!applyContentLink(query)) return;
    pagination.value.current_page = 1;
    fetchProducts();
    setTimeout(() => scrollToSection("products"), 50);
  },
);

// "View all" on Top Selling: the full list, most popular first
const viewAllPopular = () => {
  sortBy.value = "popular_desc";
  pagination.value.current_page = 1;
  fetchProducts();
  scrollToSection("products");
};

// "See all matches" from the bouquet finder: same filters on the full list
const showFinderMatches = ({ occasion, color, priceMin, priceMax }) => {
  selectedFilters.value.occasion = occasion ? [occasion] : [];
  selectedFilters.value.color = color ? [color] : [];
  selectedFilters.value.priceMin = priceMin;
  selectedFilters.value.priceMax = priceMax;
  sortBy.value = "popular_desc";
  pagination.value.current_page = 1;
  fetchProducts();
  scrollToSection("products");
};

// "Explore 3D Designer": the bouquet customizer of the first shop that offers it
const openDesigner = () => {
  const shop = vendors.value[0];
  if (!shop) {
    scrollToSection("vendors");
    return;
  }
  router.push({ name: "FlowerCustomizer", params: { id: String(shop.id) }, query: { store_name: shop.store_name } });
};

// Shop by occasion: filter the grid to one occasion and jump to it.
const shopByOccasion = (occasion) => {
  selectedFilters.value.occasion = [occasion];
  pagination.value.current_page = 1;
  fetchProducts();
  scrollToSection("products");
};

// ── Modal ─────────────────────────────────────────────────────────────────
const openProductModal = (product) => {
  router.push({ name: "ProductDetails", params: { id: String(product.id) } });
};
const closeModal = () => {
  showProductModal.value = false;
  selectedProduct.value = null;
  show3D.value = false;
  document.body.style.overflow = "auto";
};
const addToCartModal = async () => {
  if (!selectedProduct.value) return;
  if (!requireAuth("addToCartModal", selectedProduct.value, quantity.value))
    return;
  await addToCartModalAction(selectedProduct.value, quantity.value, {
    imageEl: selectedProductImageRef.value,
  });
};
const addToCartDirect = async (
  product,
  qtyOrAnimationContext = 1,
  animationContext = {},
) => {
  const qty =
    typeof qtyOrAnimationContext === "number" ? qtyOrAnimationContext : 1;
  const resolvedAnimationContext =
    typeof qtyOrAnimationContext === "number"
      ? animationContext
      : (qtyOrAnimationContext ?? {});

  if (!isAuthenticated.value) {
    requireAuth("addToCart", product, qty);
    return false;
  }
  addingToCartProductId.value = product.id;
  addingToCart.value = true;
  try {
    const r = await cartStore.addToCart({
      product_id: product.id,
      quantity: qty,
      color: product.color || null,
      size: product.size || null,
      customizations: {},
    });
    if (r.success) {
      await flyToCart(resolvedAnimationContext.imageEl);
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
const buyNowDirect = async (product) => {
  if (!product || product.quantity_in_stock === 0) return;
  if (!requireAuth("buyNow", product, 1)) return;
  await buyNowAction(product, 1);
};

onMounted(async () => {
  const fromContentLink = applyContentLink(route.query);
  await Promise.all([fetchFilterOptions(), fetchProducts(), fetchVendors(), fetchFeatured(), fetchPopular()]);
  if (fromContentLink) setTimeout(() => scrollToSection("products"), 50);
  if (isAuthenticated.value) {
    await Promise.all([loadCart(), fetchNearbyStores()]);
  } else {
    await loadPendingActionFromStorage();
  }
});
onUnmounted(() => {
  document.body.style.overflow = "auto";
});
</script>

<style scoped>
/* Tokens, type, labels, buttons, links, skeletons and the navbar come from
   src/styles/bloomcraft.css (shared with the landing page). */

/* Resets live in a cascade layer so they only replace browser defaults and
   never override real styles (incl. the shared bloomcraft.css rules). */
@layer bc-reset {
  * {
    box-sizing: border-box;
  }

  .shop-page :is(p, h1, h2, h3, h4, ul, dl, dd, figure) {
    margin: 0;
  }

  .shop-page ul {
    padding: 0;
    list-style: none;
  }
}

/* Same body reset the landing and pricing pages apply (UA 8px margin). */
:global(body) {
  margin: 0;
}

.shop-page {
  width: 100%;
  min-height: 100vh;
  overflow-x: hidden;
  background: var(--ivory);
}

.shop-page button {
  font-family: var(--font-sans);
}

/* Secondary button: outline version of .bc-btn */
.shop-btn-outline {
  background: transparent;
  color: var(--ink);
  border-color: var(--line);
}

.shop-btn-outline:hover {
  border-color: var(--ink);
}

.shop-head h2 {
  font-size: clamp(32px, 3.4vw, 46px);
}

/* Anchored sections clear the fixed header */
#vendors,
#occasions,
#products {
  scroll-margin-top: 110px;
}

.shop-empty-line {
  padding: 32px 0;
  color: var(--ink-soft);
  font-size: 15px;
}

.shop-featured,
.shop-categories {
  padding: clamp(24px, 3vw, 40px) 0;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
  gap: 36px 24px;
}

/* ============================================================
   MODALS
============================================================= */
.auth-modal-overlay,
.modal-overlay {
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

.auth-modal-content {
  position: relative;
  width: 100%;
  max-width: 440px;
  padding: 40px 34px 30px;
  border-radius: 26px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(27, 22, 18, 0.25);
  animation: riseIn 0.4s var(--ease-out);
}

.auth-modal-content h3 {
  margin: 14px 0 10px;
  font-size: 34px;
}

.auth-modal-text {
  margin-bottom: 26px;
  color: var(--ink-soft);
  font-size: 14.5px;
}

.auth-modal-actions {
  display: grid;
  gap: 10px;
}

.auth-modal-actions .bc-btn {
  justify-content: center;
}

.auth-modal-note {
  margin-top: 20px;
  color: var(--ink-soft);
  font-size: 13px;
  text-align: center;
}

.btn-continue {
  border: none;
  background: none;
  color: var(--ink);
  font-size: 13px;
  font-weight: 500;
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}

.auth-modal-close,
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
  background: rgba(250, 248, 244, 0.9);
  color: var(--ink);
  font-size: 15px;
  cursor: pointer;
  transition: background 0.3s;
}

.auth-modal-close:hover,
.modal-close:hover {
  background: var(--ivory-deep);
}

.auth-modal-close:focus-visible,
.modal-close:focus-visible,
.btn-continue:focus-visible,
.thumbnail:focus-visible,
.view-toggle button:focus-visible,
.qty-btn:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 3px;
}

.modal-content {
  position: relative;
  width: 100%;
  max-width: 1080px;
  max-height: calc(100vh - 40px);
  overflow: hidden;
  border-radius: 24px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(27, 22, 18, 0.25);
  animation: riseIn 0.45s var(--ease-out);
}

@media (max-width: 968px) {

  .modal-content {
    overflow-y: auto;
  }
}

@media (max-width: 640px) {

  .products-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 28px 14px;
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
  .auth-modal-overlay,
  .modal-overlay,
  .auth-modal-content,
  .modal-content {
    animation: none;
  }
}

/* ── Redesigned top of the shop ─────────────────────────────────────── */
.shop-top {
  padding-top: var(--shop-nav-h, 96px);
}

.shop-announce {
  padding-top: 12px;
  padding-bottom: 12px;
}

.shop-picks {
  margin-top: 30px;
}

.shop-picks-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 26px;
  align-items: start;
}

.shop-row-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.shop-row-head h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 500;
  color: var(--ink);
}

.shop-row-all {
  padding: 0;
  border: 0;
  background: none;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--rosewood-dark);
  cursor: pointer;
}

.top-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 16px;
}

.top-skel {
  height: 250px;
  border-radius: 12px;
}

.shop-picks-shops {
  margin-top: 32px;
}

/* The full store list and everything after it sit lower on the page */
.shop-picks + * {
  margin-top: clamp(40px, 5vw, 64px);
}

.shop-picks-finder {
  position: sticky;
  top: 110px;
}

@media (max-width: 1180px) {
  .shop-picks-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .shop-picks-finder {
    position: static;
  }
}

@media (max-width: 960px) {
  .top-grid {
    display: flex;
    overflow-x: auto;
    scrollbar-width: thin;
  }

  .top-grid > * {
    flex: 0 0 190px;
  }
}

/* Fresh Flowers: the browse area, cards a size up from Top Selling */
.fresh {
  margin-top: 36px;
  scroll-margin-top: 110px;
}

.fresh-count {
  font-size: 12.5px;
  color: var(--ink-soft);
}

.fresh-chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: -4px 0 16px;
}

.fresh-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 32px;
  padding: 0 12px;
  border: 1px solid rgba(184, 101, 88, 0.35);
  border-radius: 999px;
  background: #f8ece7;
  font-size: 12.5px;
  color: var(--rosewood-dark);
  cursor: pointer;
}

.fresh-chip:hover {
  border-color: var(--rosewood-dark);
}

.fresh-chip span {
  font-size: 10px;
}

.fresh-clear {
  padding: 0;
  border: 0;
  border-bottom: 1px solid currentColor;
  background: none;
  font-size: 12.5px;
  color: var(--ink-soft);
  cursor: pointer;
}

.fresh-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
}

.fresh-skel {
  height: 300px;
  border-radius: 12px;
}

.fresh-more {
  display: flex;
  justify-content: center;
  margin-top: 24px;
}

.fresh-more-btn {
  height: 44px;
  padding: 0 26px;
  border: 1px solid var(--rosewood-dark);
  border-radius: 999px;
  background: var(--paper);
  font-size: 13.5px;
  font-weight: 500;
  color: var(--rosewood-dark);
  cursor: pointer;
}

.fresh-more-btn:hover:not(:disabled) {
  background: #f8ece7;
}

@media (max-width: 960px) {
  .fresh-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .fresh-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
}
</style>
