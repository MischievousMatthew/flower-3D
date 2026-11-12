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

    <main>
    <!-- 1. Search (the navbar above has the logo, links, cart and account) -->
    <div class="shop-top">
      <div class="bc-container shop-searchbar">
        <ShopSearch
          v-model="searchInput"
          class="shop-searchbar-field"
          :flowers="searchFlowers"
          :occasions="searchOccasions"
          :colors="searchColors"
          @submit="searchAndShow"
          @clear="clearSearch"
          @pick="onSearchPick"
        />
      </div>

      <!-- 2. Hero and promotions (Admin → Website, Promotion Management) -->
      <div v-if="site.announcements.length" class="bc-container shop-announce">
        <AnnouncementBar />
      </div>
      <ShopHero :banners="site.banners" :settings="site.slideshow" @shop="scrollToSection('products')" @design="openDesigner" />
      <DesignerShopPicker :open="designerPickerOpen" @close="designerPickerOpen = false" @choose="designWithShop" @browse="browseFlowerShops" />
    </div>
    <ShopPromotions :promotions="site.promotions" @sale="showOnSale" @design="openDesigner" />
    <ShopTrustStrip variant="top" />

    <!-- 3. Shop by category: flower types, occasions, colors, arrangements -->
    <ShopCategories
      id="occasions"
      :options="filterOptions"
      :images="occasionImages"
      :type-images="typeImages"
      :active="selectedFilters"
      :loading="!filterOptionsLoaded"
      @pick="onCategoryPick"
    />

    <!-- 4. Featured collections (tabs) -->
    <FeaturedCollections
      id="collections"
      :featured="featuredProducts"
      :owner-ids="nearMe ? nearMe.ownerIds : null"
      :adding-id="addingToCartProductId"
      @open="openProductModal"
      @add="addToCartDirect"
      @view-all="onViewAll"
    />

    <!-- 5. Every flower: filters, sorting and pages -->
    <section id="products" class="cat" aria-labelledby="cat-title">
      <div class="bc-container">
        <header class="bc-sec-head">
          <div>
            <p class="bc-sec-eyebrow">Shop all flowers</p>
            <h2 id="cat-title" class="bc-sec-title">{{ catalogTitle }}</h2>
          </div>
          <p class="cat-count" aria-live="polite">{{ !isLoading || products.length ? rangeText : "" }}</p>
        </header>

        <div class="cat-layout">
          <ShopFilters
            class="cat-filters"
            :options="filterOptions"
            :filters="selectedFilters"
            :active-count="activeFilterChips.length"
            :total="pagination.total || 0"
            :open="filtersOpen"
            @toggle="toggleFilter"
            @flag="setFlag"
            @price="setPrice"
            @clear="clearAllFilters"
            @close="filtersOpen = false"
          />

          <div class="cat-main">
            <div class="cat-tools">
              <button type="button" class="cat-filter-btn" :aria-expanded="filtersOpen" @click="filtersOpen = true">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
                Filters<span v-if="activeFilterChips.length" class="cat-filter-n">{{ activeFilterChips.length }}</span>
              </button>
              <ul v-if="activeFilterChips.length" class="fresh-chips" aria-label="Filters in use">
                <li v-for="chip in activeFilterChips" :key="chip.key">
                  <button type="button" class="fresh-chip" :aria-label="`Remove filter ${chip.label}`" @click="removeFilterChip(chip)">
                    {{ chip.label }} <span aria-hidden="true">✕</span>
                  </button>
                </li>
                <li><button type="button" class="fresh-clear" @click="clearAllFilters">Clear all</button></li>
              </ul>
              <label class="fresh-sort">
                <span>Sort by</span>
                <select v-model="sortBy" aria-label="Sort flowers" @change="onSortChange">
                  <option v-for="option in SORT_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
              </label>
            </div>

            <div v-if="isLoading && !products.length" class="fresh-grid" aria-label="Loading flowers">
              <span v-for="n in 9" :key="n" class="bc-skeleton fresh-skel"></span>
            </div>
            <div v-else-if="products.length" class="fresh-grid" :class="{ dim: isLoading }" :aria-busy="isLoading">
              <ShopProductTile
                v-for="product in products"
                :key="`fresh-${product.id}`"
                :product="product"
                :adding="addingToCartProductId === product.id"
                @open="openProductModal"
                @add="addToCartDirect"
              />
            </div>
            <div v-else class="cat-empty">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 1 1 0 5zM16.5 12a2.5 2.5 0 1 1 5 0 2.5 2.5 0 1 1-5 0zM12 16.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 1 1 0-5zM7.5 12a2.5 2.5 0 1 1-5 0 2.5 2.5 0 1 1 5 0z" /><circle cx="12" cy="12" r="2" /></svg>
              <p>{{ emptyText }}</p>
              <button v-if="activeFilterChips.length" type="button" class="bc-btn bc-btn--dark" @click="clearAllFilters">Clear filters</button>
            </div>

            <ShopPagination :page="pagination.current_page" :last="pagination.last_page" @go="goToPage" />
          </div>
        </div>
      </div>
    </section>

    <!-- 6. Build your own bouquet (3D designer), with the bouquet finder beside it -->
    <section class="build" aria-label="Build your own bouquet">
      <div class="bc-container build-grid">
        <DesignerBanner @design="openDesigner" @shops="scrollToSection('flower-shops')" />
        <BouquetFinder
          class="build-finder"
          :occasions="filterOptions.occasions"
          :colors="filterOptions.colors"
          :adding-id="addingToCartProductId"
          @open="openProductModal"
          @add="addToCartDirect"
          @see-all="showFinderMatches"
        />
      </div>
    </section>

    <!-- 7. Flower shops around you: the map, then the shops -->
    <ShopMap v-model:page-filter="nearMeOnly" @visit="goToStorefront" @range="onShopRange" />
    <section class="shop-stores" aria-label="Flower shops list">
      <div class="bc-container">
        <FeaturedShops
          id="flower-shops"
          :shops="vendors"
          :loading="loadingVendors"
          :nearby="nearInCircle"
          :distances="shopDistances"
          :by-road="false"
          :failed="vendorsFailed"
          :total="vendorPagination.total"
          :loading-more="loadingMoreVendors"
          @visit="goToStorefront"
          @more="fetchVendors({ append: true })"
          @retry="fetchVendors()"
        />
      </div>
    </section>

    <!-- Journal (Admin → Website → Journal) -->
    <JournalTeaser :articles="site.articles" />
    </main>

    <!-- 8. Footer: about, support, policies, contact -->
    <SiteFooter />

  </div>
</template>

<script setup>
import { productRoute } from "../../utils/productLink.js";
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import api from "../../plugins/axios.js";
import NavHeader from "../../layouts/NavHeader.vue";
import productService from "../../services/productService.js";
import { useCart } from "../../composables/useCart";
import { useFlyToCart } from "../../composables/useFlyToCart";
import { toast } from "@/utils/snackbar";
import AnnouncementBar from "../../components/site/AnnouncementBar.vue";
import ShopHero from "../../components/shop/ShopHero.vue";
import ShopTrustStrip from "../../components/shop/ShopTrustStrip.vue";
import ShopPromotions from "../../components/shop/ShopPromotions.vue";
import ShopCategories from "../../components/shop/ShopCategories.vue";
import FeaturedCollections from "../../components/shop/FeaturedCollections.vue";
import ShopFilters from "../../components/shop/ShopFilters.vue";
import ShopPagination from "../../components/shop/ShopPagination.vue";
import DesignerBanner from "../../components/shop/DesignerBanner.vue";
import ShopProductTile from "../../components/shop/ShopProductTile.vue";
import ShopSearch from "../../components/shop/ShopSearch.vue";
import BouquetFinder from "../../components/shop/BouquetFinder.vue";
import FeaturedShops from "../../components/shop/FeaturedShops.vue";
import DesignerShopPicker from "../../components/shop/DesignerShopPicker.vue";
import { rememberProduct } from "../../utils/productPreview.js";
import ShopMap from "../../components/shop/ShopMap.vue";
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
      params: {
        page: 1,
        per_page: 40,
        sort_by: "popular_desc",
        in_stock_only: 1,
        ...(nearMe.value ? { owner_ids: idsParam(nearMe.value.ownerIds) } : {}),
      },
    });
    const list = Array.isArray(data?.data?.data) ? data.data.data : [];
    popularProducts.value = list.filter(isShopVisibleProduct);
  } catch {
    popularProducts.value = [];
  } finally {
    loadingPopular.value = false;
  }
};
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
// A photo for each flower type and arrangement tile, a different one per tile where possible
const typeImages = computed(() => {
  const images = {};
  const used = new Set();
  for (const unique of [true, false]) {
    for (const product of popularProducts.value) {
      const image = productImage(product);
      if (!image) continue;
      for (const [kind, value] of [["flowerType", product.flower_type], ["category", product.category]]) {
        const key = `${kind}:${value}`;
        if (!value || images[key] || (unique && used.has(image))) continue;
        images[key] = image;
        used.add(image);
      }
    }
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
const SORT_OPTIONS = [
  { value: "created_at_desc", label: "Newest" },
  { value: "popular_desc", label: "Most popular" },
  { value: "rating_desc", label: "Top rated" },
  { value: "trending_desc", label: "Trending this week" },
  { value: "price_low", label: "Price: low to high" },
  { value: "price_high", label: "Price: high to low" },
  { value: "name_asc", label: "Name: A to Z" },
];
// What's typed in the search box, and the search the list is showing
const searchInput = ref("");
const activeSearch = ref("");
let searchTimer = 0;
const isLoading = ref(false);
const products = ref([]);

// Live stats for the open product modal
const vendors = ref([]);

// ── Near me (the shop map's circle) ────────────────────────────────────────
// With a pinned address, the page shows only the shops inside the circle,
// until the customer switches it off (or removes the chip).
const shopRange = ref(null); // { km, shopIds, ownerIds } from the map, null without a pin
const nearMeOnly = ref(true);
const nearMe = computed(() => (nearMeOnly.value && shopRange.value ? shopRange.value : null));
const nearMeKey = computed(() => (nearMe.value ? `${nearMe.value.km}|${nearMe.value.ownerIds.join()}|${nearMe.value.shopIds.join()}` : ""));
const onShopRange = (range) => {
  shopRange.value = range;
};
// "0" means none: the server then lists nothing instead of everything
const idsParam = (ids) => (ids.length ? ids.join(",") : "0");
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
  flowerType: [],
  inStockOnly: true,
  onSale: false, // "Sales" in the navbar: only flowers selling below their price
  bestSellers: false, // "View all" on Top Selling Flowers
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
// Only the newest request may change the list: an older, slower reply (the
// list before the map's circle was known) must not overwrite it. A failed load
// tries again twice (the server may be waking up), then says so with a retry,
// instead of looking like there are no shops at all.
let vendorRequest = 0;
const vendorsFailed = ref(false);
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
// 8 shops at a time; "Explore more shops" adds the next 8 below (append)
const SHOPS_PER_PAGE = 8;
const loadingMoreVendors = ref(false);
const fetchVendors = async ({ append = false } = {}) => {
  const mine = ++vendorRequest;
  if (append) loadingMoreVendors.value = true;
  else loadingVendors.value = !vendors.value.length;
  vendorsFailed.value = false;
  const params = {
    page: append ? vendorPagination.value.current_page + 1 : 1,
    per_page: SHOPS_PER_PAGE,
  };
  if (vendorSearch.value) params.search = vendorSearch.value;
  // Every shop is listed; with a pin, nearest first (across all pages)
  const nearestFirst = Object.entries(shopDistances.value).sort((a, b) => a[1] - b[1]).map(([id]) => id);
  if (nearestFirst.length) params.order_ids = nearestFirst.slice(0, 200).join(",");
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      const r = await api.get("vendors", { params });
      if (mine !== vendorRequest) return;
      if (!r.data.success) throw new Error("vendors not loaded");
      const vendorData = Array.isArray(r.data.data?.data) ? r.data.data.data : [];
      if (append) {
        const seen = new Set(vendors.value.map((v) => v.id));
        vendors.value = [...vendors.value, ...vendorData.filter((v) => !seen.has(v.id))];
      } else {
        vendors.value = vendorData;
      }
      vendorPagination.value = {
        current_page: r.data.data?.current_page ?? params.page,
        last_page: r.data.data?.last_page ?? 1,
        per_page: SHOPS_PER_PAGE,
        total: r.data.data?.total ?? vendors.value.length,
      };
      loadingVendors.value = false;
      loadingMoreVendors.value = false;
      return;
    } catch (e) {
      if (mine !== vendorRequest) return;
      if (attempt === 2) {
        console.error(e);
        // A failed "more" keeps the shops already shown
        if (append) toast.error("Couldn't load more shops. Please try again.");
        else vendorsFailed.value = true;
        loadingVendors.value = false;
        loadingMoreVendors.value = false;
        return;
      }
      await wait(attempt ? 4000 : 1500);
      if (mine !== vendorRequest) return;
    }
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
// ── Shops near the customer ──────────────────────────────────────────────
// The map measures every shop's distance from the customer's pin (in a
// straight line, like its circle). The shop cards use those same numbers, so a
// shop never shows two different distances. "Near you" is for the shops
// inside the circle; every shop is listed, nearest first.
const shopDistances = computed(() => shopRange.value?.distances ?? {});
const nearInCircle = computed(() => {
  const inside = new Set(shopRange.value?.shopIds ?? []);
  return Object.fromEntries(Object.entries(shopDistances.value).filter(([id]) => inside.has(Number(id))));
});

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
  if (selectedFilters.value.flowerType.length)
    p.flower_type = selectedFilters.value.flowerType.join(",");
  if (selectedFilters.value.season.length)
    p.season = selectedFilters.value.season.join(",");
  if (selectedFilters.value.occasion.length)
    p.occasion = selectedFilters.value.occasion.join(",");
  if (selectedFilters.value.priceMin !== null)
    p.min_price = selectedFilters.value.priceMin;
  if (selectedFilters.value.priceMax !== null)
    p.max_price = selectedFilters.value.priceMax;
  p.in_stock_only = selectedFilters.value.inStockOnly ? 1 : 0;
  if (selectedFilters.value.onSale) p.on_sale = 1;
  if (selectedFilters.value.bestSellers) p.best_sellers = 1;
  p.sort_by = sortBy.value;
  if (nearMe.value) p.owner_ids = idsParam(nearMe.value.ownerIds);
  if (activeSearch.value) p.search = activeSearch.value;
  return p;
};

// ── Products ──────────────────────────────────────────────────────────────
// Numbered pages: each page replaces the grid, then the grid's top comes into view
const goToPage = async (page) => {
  pagination.value.current_page = page;
  document.getElementById("products")?.scrollIntoView({ behavior: "smooth", block: "start" });
  await fetchProducts();
};

// The filters in use, as removable chips above Fresh Flowers
const activeFilterChips = computed(() => {
  const f = selectedFilters.value;
  const label = (s) => String(s).replace(/[-_]/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
  const chips = [
    ...f.occasion.map((v) => ({ key: `occasion-${v}`, type: "occasion", value: v, label: v })),
    ...f.category.map((v) => ({ key: `category-${v}`, type: "category", value: v, label: site.categories.find((c) => c.value === v)?.label || label(v) })),
    ...f.flowerType.map((v) => ({ key: `flowerType-${v}`, type: "flowerType", value: v, label: filterOptions.value.labels?.flower_types?.[v] || label(v) })),
    ...f.color.map((v) => ({ key: `color-${v}`, type: "color", value: v, label: label(v) })),
  ];
  if (nearMe.value) chips.unshift({ key: "near-me", type: "near-me", label: `Within ${nearMe.value.km} km of you` });
  if (activeSearch.value) chips.unshift({ key: "search", type: "search", label: `"${activeSearch.value}"` });
  if (f.onSale) chips.unshift({ key: "sale", type: "sale", label: "On sale" });
  if (f.bestSellers) chips.unshift({ key: "best", type: "best", label: "Best sellers" });
  if (f.priceMin !== null || f.priceMax !== null) {
    const min = f.priceMin !== null ? `₱${f.priceMin}` : "";
    const max = f.priceMax !== null ? `₱${f.priceMax}` : "";
    chips.push({ key: "price", type: "price", label: min && max ? `${min} – ${max}` : min ? `From ${min}` : `Up to ${max}` });
  }
  return chips;
});
const removeFilterChip = (chip) => {
  const f = selectedFilters.value;
  if (chip.type === "near-me") {
    nearMeOnly.value = false; // the watcher reloads the page's lists
    return;
  }
  if (chip.type === "search") {
    clearSearch();
    return;
  }
  if (chip.type === "best") {
    f.bestSellers = false;
  } else if (chip.type === "sale") {
    f.onSale = false;
  } else if (chip.type === "price") {
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
        products.value = (Array.isArray(r.data.data) ? r.data.data : [])
          .filter(isShopVisibleProduct)
          .filter((p) => !selectedFilters.value.onSale || isOnSale(p));
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
        products.value = page;
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
    flowerType: [],
    inStockOnly: true,
    onSale: false,
    bestSellers: false,
  };
  pagination.value.current_page = 1;
  clearTimeout(searchTimer);
  searchInput.value = "";
  activeSearch.value = "";
  if (nearMe.value) {
    nearMeOnly.value = false; // the watcher reloads everything
    return;
  }
  fetchProducts();
};

// Search runs a moment after typing stops, or straight away on Enter
function applySearch() {
  clearTimeout(searchTimer);
  const next = searchInput.value.trim().slice(0, 100);
  if (next === activeSearch.value) return;
  activeSearch.value = next;
  pagination.value.current_page = 1;
  fetchProducts();
}
function clearSearch() {
  searchInput.value = "";
  applySearch();
}

// ── Search suggestions ───────────────────────────────────────────────────
const titleWords = (s) => String(s).replace(/[-_]/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
const searchFlowers = computed(() =>
  (filterOptions.value.categories || []).map((v) => ({
    value: v,
    label: filterOptions.value.labels?.categories?.[v] || site.categories.find((c) => c.value === v)?.label || titleWords(v),
  })),
);
const searchOccasions = computed(() =>
  (filterOptions.value.occasions || []).map((v) => ({ value: v, label: filterOptions.value.labels?.occasions?.[v] || titleWords(v) })),
);
const searchColors = computed(() => (filterOptions.value.colors || []).map((v) => ({ value: v, label: titleWords(v) })));

/** A suggestion was picked: open the flower, or show the shop filtered by it */
function onSearchPick(item) {
  if (item.type === "product") return openProductModal(item.product);
  if (item.type === "term") {
    searchInput.value = item.value;
    applySearch();
    return;
  }
  const key = { flower: "category", occasion: "occasion", color: "color" }[item.type];
  if (!key) return;
  clearTimeout(searchTimer);
  searchInput.value = "";
  activeSearch.value = "";
  selectedFilters.value[key] = [item.value];
  pagination.value.current_page = 1;
  fetchProducts();
  document.getElementById("products")?.scrollIntoView({ behavior: "smooth", block: "start" });
}

// Searches that found flowers are counted (once per visit) for "Popular searches"
const countedSearches = new Set();
watch(isLoading, (loading) => {
  const term = activeSearch.value.trim().toLowerCase();
  if (loading || !term || term.length < 2 || countedSearches.has(term) || !(pagination.value.total > 0)) return;
  countedSearches.add(term);
  api.post("customer/search/log", { term }).catch(() => {});
});
watch(searchInput, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(applySearch, 350);
});
function onSortChange() {
  pagination.value.current_page = 1;
  fetchProducts();
}

// The circle changed (distance, pin, or switched on/off): reload what the page lists
watch(nearMeKey, (now, before) => {
  if (now === before) return;
  pagination.value.current_page = 1;
  fetchProducts();
  fetchPopular();
});
// The shops list doesn't narrow to the circle; it only re-sorts once the distances are known
const shopOrderKey = computed(() => Object.entries(shopDistances.value).sort((a, b) => a[1] - b[1]).map(([id]) => id).join());
watch(shopOrderKey, (now, before) => {
  if (now !== before) fetchVendors();
});

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
      query: { direct: "true" },
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
const SECTION_ALIASES = { sale: "products", categories: "occasions" };
const scrollToSection = (id) => {
  // "Sales": the flower grid, showing only what's on sale
  if (id === "sale") return showOnSale();
  const el = document.getElementById(SECTION_ALIASES[id] || id);
  if (el) el.scrollIntoView({ behavior: "smooth" });
};

/** Selling below its price right now (the sale price the card shows) */
const isOnSale = (p) => {
  const sale = Number(p?.discount_price);
  const price = Number(p?.selling_price ?? p?.price);
  return Boolean(p?.has_discount) || (sale > 0 && price > 0 && sale < price);
};

function showOnSale() {
  selectedFilters.value.onSale = true;
  pagination.value.current_page = 1;
  fetchProducts();
  const el = document.getElementById("products");
  if (el) el.scrollIntoView({ behavior: "smooth" });
}

// Shop by category: filter the grid to one category and jump to it.
// Links from banners, announcements and promotions arrive as
// ?category=…, ?occasion=… or ?section=products
const applyContentLink = (query) => {
  const category = typeof query.category === "string" ? query.category : null;
  const occasion = typeof query.occasion === "string" ? query.occasion : null;
  if (category) selectedFilters.value.category = [category];
  if (occasion) selectedFilters.value.occasion = [occasion];
  // ?section=sale: "Sales" clicked on another page
  if (query.section === "sale") selectedFilters.value.onSale = true;
  return Boolean(category || occasion || query.section === "products" || query.section === "sale");
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

// "View all" on a featured collection: the full catalog in that order
const onViewAll = (view) => {
  selectedFilters.value.bestSellers = view === "best";
  sortBy.value = { best: "popular_desc", new: "created_at_desc", trending: "trending_desc", top: "rating_desc" }[view] || sortBy.value;
  pagination.value.current_page = 1;
  fetchProducts();
  scrollToSection("products");
};

// ── Catalog filters ─────────────────────────────────────────────────────
const filtersOpen = ref(false);
watch(filtersOpen, (open) => {
  document.body.style.overflow = open ? "hidden" : "";
});
const refetchFromFirstPage = () => {
  pagination.value.current_page = 1;
  fetchProducts();
};
const toggleFilter = (type, value) => {
  const list = selectedFilters.value[type] || [];
  selectedFilters.value[type] = list.includes(value) ? list.filter((v) => v !== value) : [...list, value];
  refetchFromFirstPage();
};
const setFlag = (name, on) => {
  selectedFilters.value[name] = on;
  refetchFromFirstPage();
};
const setPrice = (min, max) => {
  const f = selectedFilters.value;
  // The same budget again switches it off
  const same = f.priceMin === min && f.priceMax === max;
  f.priceMin = same ? null : min;
  f.priceMax = same ? null : max;
  refetchFromFirstPage();
};
// A category tile: show only that one (again to show everything)
const onCategoryPick = ({ type, value }) => {
  const f = selectedFilters.value;
  const only = f[type].length === 1 && f[type][0] === value;
  f[type] = only ? [] : [value];
  refetchFromFirstPage();
  if (!only) scrollToSection("products");
};
// The search bar at the top: Enter shows the results
const searchAndShow = () => {
  applySearch();
  scrollToSection("products");
};
const catalogTitle = computed(() => {
  const f = selectedFilters.value;
  if (activeSearch.value) return `Results for "${activeSearch.value}"`;
  if (f.bestSellers) return "Best sellers";
  if (f.onSale) return "Flowers on sale";
  if (sortBy.value === "trending_desc") return "Trending this week";
  return "Every flower, one place";
});
const rangeText = computed(() => {
  const { current_page: page, per_page: per, total } = pagination.value;
  if (!total) return "No flowers";
  const from = (page - 1) * per + 1;
  const to = Math.min(total, from + products.value.length - 1);
  return `Showing ${from}–${to} of ${total} ${total === 1 ? "flower" : "flowers"}`;
});
const emptyText = computed(() => {
  if (activeSearch.value) return `No flowers match "${activeSearch.value}". Try another word or clear the search.`;
  if (selectedFilters.value.onSale && activeFilterChips.value.length === 1) return 'No flowers are on sale right now. Check back soon, or remove "On sale" to see every flower.';
  if (sortBy.value === "trending_desc" && !activeFilterChips.value.length) return "Nothing has been ordered in the last 7 days yet. Check back soon.";
  if (activeFilterChips.value.length) return "Nothing matches these filters yet. Try removing one.";
  return "Flowers show up here as shops add them.";
});

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

// "Explore 3D Designer": the customer chooses a shop that has everything a
// bouquet needs (flowers, fillers, wraps and ribbons), over a blurred page
const designerPickerOpen = ref(false);
const openDesigner = () => {
  designerPickerOpen.value = true;
};
const designWithShop = (shop) => {
  designerPickerOpen.value = false;
  router.push({
    name: "FlowerCustomizer",
    params: { id: String(shop.id) },
    query: { store_name: shop.store_name, ...(shop.owner_id ? { owner_id: String(shop.owner_id) } : {}) },
  });
};
const browseFlowerShops = () => {
  designerPickerOpen.value = false;
  scrollToSection("flower-shops");
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
  rememberProduct(product);
  router.push(productRoute(product.id));
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
  // Sent here to pick a shop for the 3D designer (landing page, after signing in)
  if (route.query.design === "1") {
    openDesigner();
    const { design, ...rest } = route.query;
    router.replace({ query: rest });
  }
  const fromContentLink = applyContentLink(route.query);
  await Promise.all([fetchFilterOptions(), fetchProducts(), fetchVendors(), fetchFeatured(), fetchPopular()]);
  if (fromContentLink) setTimeout(() => document.getElementById("products")?.scrollIntoView({ behavior: "smooth" }), 50);
  else if (typeof route.query.section === "string") setTimeout(() => scrollToSection(route.query.section), 50);
  if (isAuthenticated.value) {
    await loadCart();
  } else {
    await loadPendingActionFromStorage();
  }
});
onUnmounted(() => {
  document.body.style.overflow = "";
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


/* Anchored sections clear the fixed header */
#vendors,
#occasions,
#products {
  scroll-margin-top: 110px;
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

.auth-modal-content {
  position: relative;
  width: 100%;
  max-width: 440px;
  padding: 40px 34px 30px;
  border-radius: 26px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(38, 45, 57, 0.25);
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
  background: rgba(250, 251, 250, 0.9);
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
  outline: 2px solid var(--leaf);
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
  box-shadow: 0 30px 80px rgba(38, 45, 57, 0.25);
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

/* ── 1. Search bar under the navbar ─────────────────────────────────── */
.shop-top {
  padding-top: var(--shop-nav-h, 96px);
}

.shop-searchbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px 20px;
  padding-top: 14px;
  padding-bottom: 14px;
}

/* The search bar on its own, centred */
.shop-searchbar {
  justify-content: center;
}

.shop-searchbar :deep(.ss) {
  flex: 1 1 420px;
  max-width: 720px;
}

.shop-announce {
  padding-bottom: 12px;
}

/* Anchored sections clear the fixed header */
#collections,
#flower-shops {
  scroll-margin-top: 110px;
}

/* ── 5. Catalog ─────────────────────────────────────────────────────── */
.cat {
  margin-top: clamp(56px, 6vw, 88px);
}

.cat-count {
  margin: 0 0 6px;
  font-size: 13px;
  color: var(--ink-soft);
  font-variant-numeric: tabular-nums;
}

.cat-layout {
  display: grid;
  grid-template-columns: 260px minmax(0, 1fr);
  gap: 28px;
  align-items: start;
}

/* Wide screens: the filter column stays in view while the flowers scroll */
@media (min-width: 1025px) {
  .cat-filters {
    position: sticky;
    top: 110px;
  }

  .cat-filters :deep(.sf) {
    max-height: calc(100vh - 130px);
    overflow-y: auto;
    scrollbar-width: thin;
  }
}

.cat-tools {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px 14px;
  margin-bottom: 18px;
}

.cat-filter-btn {
  display: none;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 16px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--paper);
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
}

.cat-filter-btn svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
}

.cat-filter-n {
  display: grid;
  place-items: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--leaf-deep);
  color: var(--paper);
  font-size: 11px;
}

.cat-filter-btn:focus-visible,
.fresh-chip:focus-visible,
.fresh-clear:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

.fresh-sort {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
  font-size: 13px;
  color: var(--ink-soft);
}

.fresh-sort select {
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

.fresh-sort select:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

.fresh-chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.fresh-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 34px;
  padding: 0 12px;
  border: 1px solid rgba(77, 177, 115, 0.35);
  border-radius: 999px;
  background: #e9f5ed;
  font-size: 12.5px;
  color: var(--leaf-deep);
  cursor: pointer;
}

.fresh-chip:hover {
  border-color: var(--leaf-deep);
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
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 20px;
  transition: opacity 0.2s ease;
}

/* A new page on its way: the current one fades a little */
.fresh-grid.dim {
  opacity: 0.55;
}

.fresh-skel {
  height: 340px;
  border-radius: 14px;
}

.cat-empty {
  display: grid;
  justify-items: center;
  gap: 14px;
  padding: 56px 20px;
  border: 1px dashed var(--line);
  border-radius: 20px;
  text-align: center;
}

.cat-empty svg {
  width: 40px;
  height: 40px;
  fill: none;
  stroke: var(--leaf);
  stroke-width: 1.3;
}

.cat-empty p {
  max-width: 44ch;
  font-size: 14.5px;
  color: var(--ink-soft);
}

/* ── 6. Build your own bouquet ──────────────────────────────────────── */
.build {
  padding: clamp(56px, 6vw, 88px) 0 0;
}

.build-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 22px;
  align-items: stretch;
}

/* ── 7. Shops ───────────────────────────────────────────────────────── */
.build + * {
  margin-top: clamp(56px, 6vw, 88px);
}

.shop-stores {
  padding: 8px 0 clamp(28px, 4vw, 48px);
}

@media (max-width: 1180px) {
  .build-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 1024px) {
  .cat-layout {
    grid-template-columns: minmax(0, 1fr);
  }


  .cat-filter-btn {
    display: inline-flex;
  }
}

@media (max-width: 860px) {
  .fresh-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

}

@media (max-width: 640px) {
  .fresh-sort {
    margin-left: auto;
  }

  .fresh-sort span {
    display: none;
  }

  .fresh-chips {
    order: 3;
    width: 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .fresh-grid {
    transition: none;
  }
}
</style>
