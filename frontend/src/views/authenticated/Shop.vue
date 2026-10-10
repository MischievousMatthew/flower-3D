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

    <!-- Hero -->
    <section class="shop-hero">
      <div class="bc-container shop-hero-grid">
        <div class="shop-hero-copy">
          <p class="bc-label">Fresh flowers</p>
          <h1>The ultimate <em>flower</em> shopping destination</h1>
          <p class="shop-hero-desc">
            Discover beautiful, fresh flowers from local vendors. Perfect for
            every occasion.
          </p>
          <div class="shop-hero-actions">
            <button
              class="bc-btn bc-btn--dark"
              @click="scrollToSection('products')"
            >
              Shop now <span class="bc-btn-arrow" aria-hidden="true">→</span>
            </button>
            <button
              v-if="!isAuthenticated"
              class="bc-link shop-link-btn"
              @click="goToRegister"
            >
              Create free account
            </button>
          </div>
          <ul class="shop-hero-notes">
            <li>Fast delivery</li>
            <li>Secure payment</li>
            <li>Fresh &amp; quality</li>
          </ul>
        </div>
        <figure class="shop-hero-figure">
          <img
            src="https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=1100&h=1300&fit=crop&q=80"
            alt="Hand-tied bouquet of soft pink and peach roses"
          />
        </figure>
      </div>
    </section>

    <!-- Stores -->
    <section class="shop-stores" id="vendors">
      <div class="bc-container">
        <div class="bc-section-head bc-section-head--split shop-head">
          <div>
            <p class="bc-label">Our stores</p>
            <h2>Shop from trusted <em>local florists</em></h2>
          </div>
          <label class="store-search">
            <span class="bc-sr-only">Search flower shops</span>
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="11" cy="11" r="6.5" />
              <path d="m16 16 4 4" />
            </svg>
            <input
              v-model="vendorSearch"
              @input="debouncedFetchVendors"
              placeholder="Search flower shops"
              class="store-search-input"
            />
            <button
              v-if="vendorSearch"
              type="button"
              @click="clearVendorSearch"
              class="store-search-clear"
              aria-label="Clear search"
            >
              &#x2715;
            </button>
          </label>
        </div>

        <!-- Stores near the customer's pinned location (within 5 km) -->
        <section
          v-if="nearbyStores.length"
          class="near-you"
          aria-labelledby="near-you-title"
        >
          <div class="near-you-copy">
            <p class="bc-label">Near you</p>
            <h3 id="near-you-title">
              You're {{ formatDistance(nearbyStores[0].distance_km) }} {{ nearbyByRoad ? "by road " : "" }}from
              {{ nearbyStores[0].store_name }}
            </h3>
            <p>
              {{
                nearbyStores.length > 1
                  ? `${nearbyStores.length} shops are`
                  : "This shop is"
              }}
              within {{ nearbyRadiusKm }} km {{ nearbyByRoad ? "by road" : "" }} of the location pinned in your profile.
            </p>
          </div>
          <ul class="near-you-list">
            <li v-for="store in nearbyStores" :key="store.id">
              <button
                type="button"
                class="near-you-store"
                @click="goToStorefront(store.id)"
              >
                <span class="store-logo store-logo--small">
                  <img
                    v-if="store.store_logo_path"
                    :src="store.store_logo_path"
                    alt=""
                    @error="handleVendorLogoError"
                  />
                  <span v-else class="store-logo-fallback">{{
                    store.store_name?.charAt(0) || "B"
                  }}</span>
                </span>
                <span class="near-you-store-text">
                  <span class="near-you-name">{{ store.store_name }}</span>
                  <span class="near-you-distance"
                    >{{ formatDistance(store.distance_km) }}{{ nearbyByRoad ? "" : " away" }}<template v-if="nearbyByRoad && store.drive_minutes"> · {{ store.drive_minutes }} min drive</template></span
                  >
                </span>
                <span class="near-you-arrow" aria-hidden="true">→</span>
              </button>
            </li>
          </ul>
        </section>
        <p
          v-else-if="isAuthenticated && nearbyLoaded && !nearbyHasPin"
          class="near-you-prompt"
        >
          Pin your location in your profile to see shops near you.
          <router-link to="/customer/profile" class="bc-link"
            >Pin my location</router-link
          >
        </p>
        <p
          v-else-if="isAuthenticated && nearbyLoaded && nearbyHasPin"
          class="near-you-prompt"
        >
          No shops within {{ nearbyRadiusKm }} km {{ nearbyByRoad ? "by road " : "" }}of your pinned location yet.
        </p>

        <div v-if="loadingVendors" class="stores-grid" aria-label="Loading stores">
          <div v-for="n in 4" :key="n" class="store-card store-card--skeleton">
            <span class="bc-skeleton store-skeleton-logo"></span>
            <span class="bc-skeleton store-skeleton-line"></span>
            <span
              class="bc-skeleton store-skeleton-line store-skeleton-line--short"
            ></span>
          </div>
        </div>
        <div v-else-if="vendors.length > 0" class="stores-grid">
          <article
            v-for="vendor in vendors"
            :key="vendor.id"
            :class="[
              'store-card',
              { 'store-card--active': selectedVendor?.id === vendor.id },
            ]"
            @click="selectVendor(vendor)"
          >
            <div class="store-card-top">
              <div class="store-logo">
                <img
                  v-if="vendor.store_logo_path"
                  :src="vendor.store_logo_path"
                  :alt="vendor.store_name"
                  @error="handleVendorLogoError"
                />
                <span v-else class="store-logo-fallback">{{
                  vendor.store_name?.charAt(0) || "B"
                }}</span>
              </div>
              <span
                v-if="vendor.verification_level === 'verified'"
                class="store-verified"
                title="Verified Vendor"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m5 12.5 4.5 4.5L19 7.5" />
                </svg>
                Verified
              </span>
            </div>
            <h3 class="store-name">{{ vendor.store_name }}</h3>
            <p class="store-desc">
              {{ truncateDescription(vendor.store_description, 55) }}
            </p>
            <ul class="store-meta">
              <li v-if="nearbyDistance(vendor) !== null">
                {{ formatDistance(nearbyDistance(vendor)) }} {{ nearbyByRoad ? "by road" : "away" }}
              </li>
              <li
                v-else-if="!nearbyHasPin && isVendorCloseToCustomer(vendor)"
                title="Approximate distance from your selected city"
              >
                Close to you
              </li>
              <li v-if="vendor.same_day_delivery">Same-day</li>
              <li>From &#x20B1;{{ Math.round(vendor.price_min).toLocaleString() }}</li>
            </ul>
            <button
              type="button"
              @click.stop="goToStorefront(vendor.id)"
              class="bc-link store-visit"
            >
              Visit store
            </button>
          </article>
        </div>
        <p v-else class="shop-empty-line">
          No stores found{{ vendorSearch ? ` for "${vendorSearch}"` : "" }}
        </p>

        <div v-if="vendorPagination.last_page > 1" class="shop-pagination">
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

    <!-- Shop by occasion (from the real occasion filter values) -->
    <section class="shop-occasions" id="occasions">
      <div class="bc-container">
        <div class="bc-section-head shop-head">
          <p class="bc-label">Shop by occasion</p>
          <h2>Flowers for every <em>moment</em></h2>
        </div>
        <div
          v-if="!filterOptionsLoaded"
          class="occasion-list"
          aria-label="Loading occasions"
        >
          <span
            v-for="n in 5"
            :key="n"
            class="bc-skeleton occasion-skeleton"
          ></span>
        </div>
        <ul v-else-if="filterOptions.occasions.length" class="occasion-list">
          <li v-for="(occasion, i) in filterOptions.occasions" :key="occasion">
            <button
              type="button"
              class="occasion-item"
              :class="{
                'occasion-item--active':
                  selectedFilters.occasion.length === 1 &&
                  selectedFilters.occasion[0] === occasion,
              }"
              :aria-pressed="
                selectedFilters.occasion.length === 1 &&
                selectedFilters.occasion[0] === occasion
              "
              @click="shopByOccasion(occasion)"
            >
              <span class="occasion-index">{{
                String(i + 1).padStart(2, "0")
              }}</span>
              <span class="occasion-name">{{ occasion }}</span>
              <span class="occasion-arrow" aria-hidden="true">→</span>
            </button>
          </li>
        </ul>
        <p v-else class="shop-empty-line">
          Occasions appear here as vendors tag their flowers.
        </p>
      </div>
    </section>

    <!-- Products -->
    <section id="products" class="shop-products">
      <div class="bc-container products-layout">
        <aside class="filter-sidebar" aria-label="Filters">
          <div v-if="selectedVendor" class="active-vendor">
            <div class="store-logo store-logo--small">
              <img
                v-if="selectedVendor.store_logo_path"
                :src="selectedVendor.store_logo_path"
                :alt="selectedVendor.store_name"
              />
              <span v-else class="store-logo-fallback">{{
                selectedVendor.store_name?.charAt(0) || "B"
              }}</span>
            </div>
            <div class="active-vendor-info">
              <p class="active-vendor-label">Viewing store</p>
              <p class="active-vendor-name">{{ selectedVendor.store_name }}</p>
            </div>
            <button
              @click="clearVendorFilter"
              class="btn-clear-vendor"
              title="Show all stores"
              aria-label="Show all stores"
            >
              &#x2715;
            </button>
          </div>

          <div class="filter-header">
            <h2>Filter</h2>
            <button class="btn-clear-all" @click="clearAllFilters">
              Clear all
            </button>
          </div>
          <p class="filter-subtitle">
            Narrow down your searches with our filter.
          </p>

          <div class="filter-group">
            <button
              class="filter-toggle"
              :aria-expanded="openFilters.category"
              @click="toggleFilter('category')"
            >
              <span class="filter-label">Category</span>
              <span class="filter-value">
                {{
                  selectedFilters.category.length > 0
                    ? selectedFilters.category.join(", ")
                    : "All"
                }}
                <span class="chevron" :class="{ open: openFilters.category }"
                  >&#x203A;</span
                >
              </span>
            </button>
            <div v-if="openFilters.category" class="filter-options">
              <div
                v-if="filterOptions.categories.length === 0"
                class="filter-option-skeletons"
              >
                <span class="bc-skeleton"></span><span class="bc-skeleton"></span>
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
            <button
              class="filter-toggle"
              :aria-expanded="openFilters.color"
              @click="toggleFilter('color')"
            >
              <span class="filter-label">Color</span>
              <span class="filter-value">
                {{
                  selectedFilters.color.length > 0
                    ? selectedFilters.color.join(", ")
                    : "All"
                }}
                <span class="chevron" :class="{ open: openFilters.color }"
                  >&#x203A;</span
                >
              </span>
            </button>
            <div v-if="openFilters.color" class="filter-options">
              <div
                v-if="filterOptions.colors.length === 0"
                class="filter-option-skeletons"
              >
                <span class="bc-skeleton"></span><span class="bc-skeleton"></span>
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
            <button
              class="filter-toggle"
              :aria-expanded="openFilters.price"
              @click="toggleFilter('price')"
            >
              <span class="filter-label">Price</span>
              <span class="filter-value">
                {{ priceRangeText }}
                <span class="chevron" :class="{ open: openFilters.price }"
                  >&#x203A;</span
                >
              </span>
            </button>
            <div v-if="openFilters.price" class="filter-options">
              <div class="price-range">
                <div class="price-inputs">
                  <input
                    type="number"
                    v-model.number="selectedFilters.priceMin"
                    :placeholder="`Min ₱${filterOptions.price_range?.min || 0}`"
                    aria-label="Minimum price"
                    @input="onPriceChange"
                    class="price-input"
                  />
                  <span class="price-separator">–</span>
                  <input
                    type="number"
                    v-model.number="selectedFilters.priceMax"
                    :placeholder="`Max ₱${filterOptions.price_range?.max || 100}`"
                    aria-label="Maximum price"
                    @input="onPriceChange"
                    class="price-input"
                  />
                </div>
                <input
                  type="range"
                  :min="filterOptions.price_range?.min || 0"
                  :max="filterOptions.price_range?.max || 100"
                  v-model.number="priceSliderValue"
                  aria-label="Price"
                  @input="onSliderChange"
                  class="slider"
                />
              </div>
            </div>
          </div>

          <div class="filter-group">
            <button
              class="filter-toggle"
              :aria-expanded="openFilters.occasion"
              @click="toggleFilter('occasion')"
            >
              <span class="filter-label">Occasion</span>
              <span class="filter-value">
                {{
                  selectedFilters.occasion.length > 0
                    ? selectedFilters.occasion.join(", ")
                    : "All"
                }}
                <span class="chevron" :class="{ open: openFilters.occasion }"
                  >&#x203A;</span
                >
              </span>
            </button>
            <div v-if="openFilters.occasion" class="filter-options">
              <div
                v-if="filterOptions.occasions.length === 0"
                class="filter-option-skeletons"
              >
                <span class="bc-skeleton"></span><span class="bc-skeleton"></span>
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
            <button
              class="filter-toggle"
              :aria-expanded="openFilters.season"
              @click="toggleFilter('season')"
            >
              <span class="filter-label">Season</span>
              <span class="filter-value">
                {{
                  selectedFilters.season.length > 0
                    ? selectedFilters.season.join(", ")
                    : "All"
                }}
                <span class="chevron" :class="{ open: openFilters.season }"
                  >&#x203A;</span
                >
              </span>
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
            <button
              class="filter-toggle"
              :aria-expanded="openFilters.availability"
              @click="toggleFilter('availability')"
            >
              <span class="filter-label">Availability</span>
              <span class="filter-value">
                {{ availabilityText }}
                <span
                  class="chevron"
                  :class="{ open: openFilters.availability }"
                  >&#x203A;</span
                >
              </span>
            </button>
            <div v-if="openFilters.availability" class="filter-options">
              <label class="checkbox-label"
                ><input
                  type="checkbox"
                  v-model="selectedFilters.inStockOnly"
                  @change="fetchProducts"
                /><span>In stock only</span></label
              >
            </div>
          </div>
        </aside>

        <div class="products-main">
          <div class="products-head">
            <div>
              <p class="bc-label">Products</p>
              <h2 v-if="selectedVendor">
                {{ selectedVendor.store_name }}'s <em>collection</em>
              </h2>
              <h2 v-else>Fresh flowers <em>collection</em></h2>
              <button
                v-if="selectedVendor"
                @click="clearVendorFilter"
                class="bc-link btn-clear-store-inline"
              >
                Back to all stores
              </button>
            </div>
            <div class="sort-controls">
              <span class="results-count"
                >{{ pagination.total }} products found</span
              >
              <label class="sort-wrap">
                <span class="bc-sr-only">Sort products</span>
                <select
                  v-model="sortBy"
                  @change="onSortChange"
                  class="sort-select"
                >
                  <option value="popular_desc">Most Popular</option>
                  <option value="created_at_desc">Newest First</option>
                  <option value="price_low">Price: Low to High</option>
                  <option value="price_high">Price: High to Low</option>
                  <option value="name_asc">Name: A to Z</option>
                  <option value="name_desc">Name: Z to A</option>
                </select>
              </label>
            </div>
          </div>

          <div
            v-if="isLoading"
            class="products-grid"
            aria-label="Loading products"
          >
            <div v-for="n in 8" :key="n" class="product-skeleton">
              <span class="bc-skeleton product-skeleton-image"></span>
              <span class="bc-skeleton product-skeleton-line product-skeleton-line--short"></span>
              <span class="bc-skeleton product-skeleton-line"></span>
              <span class="bc-skeleton product-skeleton-line product-skeleton-line--price"></span>
            </div>
          </div>
          <div v-else-if="products.length === 0" class="no-results">
            <h3>No products <em>found</em></h3>
            <p>Try adjusting your filters to find what you're looking for.</p>
            <button class="bc-btn bc-btn--dark" @click="clearAllFilters">
              Clear all filters
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
              @buy-now="buyNowDirect"
              @select-vendor="selectVendorById"
            />
          </div>

          <div v-if="pagination.total > 0" class="shop-pagination">
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

    <!-- Product details -->
    <div v-if="showProductModal" class="modal-overlay" @click="closeModal">
      <div
        class="modal-content"
        role="dialog"
        aria-modal="true"
        :aria-label="selectedProduct?.product_name || 'Product details'"
        @click.stop
      >
        <button class="modal-close" aria-label="Close" @click="closeModal">
          &#x2715;
        </button>
        <div v-if="selectedProduct" class="modal-inner">
          <!-- Images / 3D -->
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
                ref="selectedProductImageRef"
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
                <button
                  v-for="(image, index) in selectedProduct.images"
                  :key="image.id"
                  type="button"
                  class="thumbnail"
                  :class="{ active: selectedImageIndex === index }"
                  :aria-label="`Show image ${index + 1}`"
                  @click="selectImage(index)"
                >
                  <img
                    :src="image.image_url"
                    :alt="`${selectedProduct.product_name} - Image ${index + 1}`"
                  />
                </button>
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
                3D view
              </button>
              <button @click="show3D = false" :class="{ active: !show3D }">
                Photos
              </button>
            </div>
          </div>

          <!-- Details + reviews -->
          <div class="modal-right">
            <p class="bc-label modal-category-tag">{{ selectedProduct.category }}</p>
            <h2 class="modal-product-name">
              {{ selectedProduct.product_name }}
            </h2>

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
              <span class="modal-stats-avg">{{
                (productStats.average_rating || 0).toFixed(1)
              }}</span>
              <span class="modal-stats-sep">&#x00B7;</span>
              <span
                >{{ productStats.total_reviews }} review{{
                  productStats.total_reviews !== 1 ? "s" : ""
                }}</span
              >
              <span class="modal-stats-sep">&#x00B7;</span>
              <span>{{ productStats.sold_count }} sold</span>
            </div>
            <div v-else class="modal-stats-skeleton">
              <span class="bc-skeleton"></span>
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
                >{{ calculateDiscountPercentage(selectedProduct) }}% off</span
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
                <dd
                  class="stock-pill"
                  :class="getStockStatusClass(selectedProduct)"
                >
                  {{ getStockStatusText(selectedProduct) }}
                </dd>
              </div>
            </dl>

            <div class="modal-cart-row">
              <div class="quantity-selector" role="group" aria-label="Quantity">
                <button
                  @click="decreaseQuantity"
                  class="qty-btn"
                  aria-label="Decrease quantity"
                >
                  &#x2212;
                </button>
                <span class="qty-value" aria-live="polite">{{ quantity }}</span>
                <button
                  @click="increaseQuantity"
                  class="qty-btn"
                  aria-label="Increase quantity"
                >
                  +
                </button>
              </div>
              <button
                @click="addToCartModal"
                :disabled="
                  selectedProduct.quantity_in_stock === 0 || addingToCartModal
                "
                class="bc-btn shop-btn-outline modal-btn"
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
                @click="buyNow"
                :disabled="
                  selectedProduct.quantity_in_stock === 0 || addingToCartModal
                "
                class="bc-btn bc-btn--dark modal-btn"
              >
                {{ addingToCartModal ? "Please wait..." : "Buy now" }}
              </button>
            </div>

            <div v-if="!isAuthenticated" class="guest-notice">
              <p>
                <strong>Create an account</strong> to save items to add to
                cart, track orders, and get personalized recommendations.
              </p>
              <div class="guest-actions">
                <button @click="goToLogin" class="bc-link">Login</button>
                <button @click="goToRegister" class="bc-link">Sign up</button>
              </div>
            </div>

            <div class="modal-reviews-embed">
              <p class="bc-label">Customer reviews</p>
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
import ModelViewer3D from "../../layouts/3D/3DModelViewer.vue";
import productService from "../../services/productService.js";
import { useCart } from "../../composables/useCart";
import { useFlyToCart } from "../../composables/useFlyToCart";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import ProductReviews from "../../layouts/components/ProductReviews.vue";
import ProductCard from "../../layouts/components/ProductCard.vue";
import "../../styles/bloomcraft.css";

const router = useRouter();
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
const selectedImageIndex = ref(0);
const selectedProductImageRef = ref(null);
const quantity = ref(1);
const showAuthModal = ref(false);
const pendingAction = ref(null);
const sortBy = ref("created_at_desc");
const isLoading = ref(false);
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

// Customer locations are intentionally city reference points, not customer pins.
// Vendor coordinates always come from the vendor's saved registration map pin.
const customerCityCoordinates = {
  Manila: [14.5995, 120.9842],
  "Quezon City": [14.676, 121.0437],
  Caloocan: [14.7566, 121.0456],
  "Las Piñas": [14.4445, 120.9939],
  Makati: [14.5547, 121.0244],
  Malabon: [14.6681, 120.9658],
  Mandaluyong: [14.5794, 121.0359],
  Marikina: [14.6507, 121.1029],
  Muntinlupa: [14.4081, 121.0415],
  Navotas: [14.6667, 120.9417],
  Parañaque: [14.4793, 121.0198],
  Pasay: [14.5378, 121.0014],
  Pasig: [14.5764, 121.0851],
  "San Juan": [14.6019, 121.0355],
  Taguig: [14.5176, 121.0509],
  Valenzuela: [14.7006, 120.983],
  "Bacoor, Cavite": [14.459, 120.9486],
  "Imus, Cavite": [14.4297, 120.9367],
  "Dasmariñas, Cavite": [14.3294, 120.9367],
  "General Trias, Cavite": [14.296, 120.9045],
  "Trece Martires, Cavite": [14.2814, 120.8689],
  "Tagaytay, Cavite": [14.1153, 120.9621],
  "Carmona, Cavite": [14.3132, 121.0576],
  "Cavite City, Cavite": [14.4791, 120.8969],
  "Silang, Cavite": [14.2157, 120.9714],
  "General Mariano Alvarez, Cavite": [14.305, 121.0005],
  "Kawit, Cavite": [14.4443, 120.901],
  "Noveleta, Cavite": [14.4292, 120.8799],
  "Rosario, Cavite": [14.4148, 120.8532],
  "Tanza, Cavite": [14.3944, 120.8554],
  "Naic, Cavite": [14.3181, 120.7669],
  "Maragondon, Cavite": [14.2733, 120.7372],
  "Ternate, Cavite": [14.2897, 120.715],
  "Indang, Cavite": [14.1953, 120.8769],
  "Amadeo, Cavite": [14.1706, 120.9234],
  "Mendez, Cavite": [14.1287, 120.9051],
  "Alfonso, Cavite": [14.1406, 120.8532],
  "Magallanes, Cavite": [14.1874, 120.7587],
  "General Emilio Aguinaldo, Cavite": [14.1842, 120.7947],
};

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
// True once the filter options request has finished (drives the occasion skeletons).
const filterOptionsLoaded = ref(false);
let vendorSearchTimeout = null;

// ── Computed ──────────────────────────────────────────────────────────────
const cartCount = computed(() => cartStore.count.value);
const priceRangeText = computed(() =>
  selectedFilters.value.priceMin !== null &&
  selectedFilters.value.priceMax !== null
    ? `₱${selectedFilters.value.priceMin} - ₱${selectedFilters.value.priceMax}`
    : "All",
);
const availabilityText = computed(() =>
  selectedFilters.value.inStockOnly ? "In Stock Only" : "All",
);

function getCustomerCityCoordinates(city) {
  return customerCityCoordinates[city?.trim()] ?? null;
}

function calculateDistanceInKm([latitudeA, longitudeA], [latitudeB, longitudeB]) {
  const degreesToRadians = (degrees) => (degrees * Math.PI) / 180;
  const earthRadiusInKm = 6371;
  const latitudeDifference = degreesToRadians(latitudeB - latitudeA);
  const longitudeDifference = degreesToRadians(longitudeB - longitudeA);
  const haversine =
    Math.sin(latitudeDifference / 2) ** 2 +
    Math.cos(degreesToRadians(latitudeA)) *
      Math.cos(degreesToRadians(latitudeB)) *
      Math.sin(longitudeDifference / 2) ** 2;

  return earthRadiusInKm * 2 * Math.atan2(Math.sqrt(haversine), Math.sqrt(1 - haversine));
}

// The customer's pinned location from their profile; older accounts without
// a pin fall back to the centre of their city.
function getCustomerCoordinates() {
  const latitude = Number(user.value?.latitude);
  const longitude = Number(user.value?.longitude);
  if (user.value?.latitude != null && Number.isFinite(latitude) && Number.isFinite(longitude)) {
    return [latitude, longitude];
  }
  return getCustomerCityCoordinates(user.value?.city);
}

function isVendorCloseToCustomer(vendor) {
  const customerCoordinates = getCustomerCoordinates();
  const vendorLatitude = Number(vendor.store_latitude);
  const vendorLongitude = Number(vendor.store_longitude);

  if (
    !customerCoordinates ||
    !Number.isFinite(vendorLatitude) ||
    !Number.isFinite(vendorLongitude) ||
    vendorLatitude < -90 || vendorLatitude > 90 ||
    vendorLongitude < -180 || vendorLongitude > 180
  ) {
    return false;
  }

  return calculateDistanceInKm(customerCoordinates, [vendorLatitude, vendorLongitude]) <= 3;
}

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
// ── Stores near the customer's pinned location ────────────────────────────
const nearbyStores = ref([]);
const nearbyHasPin = ref(false);
const nearbyLoaded = ref(false);
const nearbyRadiusKm = ref(5);
const nearbyByRoad = ref(true); // false only if the routing service was unavailable
const nearbyDistanceById = computed(
  () => new Map(nearbyStores.value.map((store) => [store.id, store.distance_km])),
);
const nearbyDistance = (vendor) => nearbyDistanceById.value.get(vendor.id) ?? null;
const formatDistance = (km) =>
  km < 1 ? `${Math.max(100, Math.round((km * 1000) / 100) * 100)} m` : `${km} km`;

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
const applyPopularSort = () => {
  sortBy.value = "popular_desc";
  pagination.value.current_page = 1;
  fetchProducts();
  document.getElementById("products")?.scrollIntoView({ behavior: "smooth" });
};

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
        products.value = (
          Array.isArray(r.data.data.data) ? r.data.data.data : []
        ).filter(isShopVisibleProduct);
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

// Shop by occasion: filter the grid to one occasion and jump to it.
const shopByOccasion = (occasion) => {
  selectedFilters.value.occasion = [occasion];
  pagination.value.current_page = 1;
  fetchProducts();
  scrollToSection("products");
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
  await Promise.all([fetchFilterOptions(), fetchProducts(), fetchVendors()]);
  if (isAuthenticated.value) {
    await Promise.all([loadCart(), fetchNearbyStores()]);
  } else {
    await loadPendingActionFromStorage();
  }
});
onUnmounted(() => {
  if (vendorSearchTimeout) clearTimeout(vendorSearchTimeout);
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

.shop-link-btn {
  border: none;
  background: none;
  cursor: pointer;
}

.shop-head h2 {
  font-size: clamp(40px, 4.6vw, 68px);
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

/* ============================================================
   HERO
============================================================= */
.shop-hero {
  padding: 150px 0 clamp(80px, 9vw, 130px);
}

.shop-hero-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
  gap: clamp(40px, 6vw, 96px);
  align-items: center;
}

.shop-hero-copy h1 {
  margin: 26px 0 26px;
  font-size: clamp(54px, 6.4vw, 104px);
}

.shop-hero-desc {
  max-width: 440px;
  margin-bottom: 40px;
  color: var(--ink-soft);
  font-size: 16.5px;
  line-height: 1.8;
}

.shop-hero-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 32px;
}

.shop-hero-notes {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 0;
  margin-top: 48px;
  padding-top: 24px;
  border-top: 1px solid var(--line);
  color: var(--ink-soft);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.shop-hero-notes li + li::before {
  content: "·";
  margin: 0 14px;
  color: var(--rosewood);
}

.shop-hero-figure {
  position: relative;
  aspect-ratio: 11 / 13;
  max-height: 640px;
  justify-self: end;
  width: 100%;
  overflow: hidden;
  border-radius: 999px 999px 18px 18px;
  background: var(--ivory-deep);
}

.shop-hero-figure img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* ============================================================
   STORES
============================================================= */
.shop-stores,
.shop-occasions {
  padding-bottom: clamp(90px, 10vw, 140px);
}

.store-search {
  display: flex;
  align-items: center;
  gap: 10px;
  justify-self: end;
  width: min(100%, 340px);
  padding: 0 4px 10px;
  border-bottom: 1px solid var(--line);
  transition: border-color 0.3s;
}

.store-search:focus-within {
  border-color: var(--ink);
}

.store-search svg {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  fill: none;
  stroke: var(--ink-soft);
  stroke-width: 1.5;
  stroke-linecap: round;
}

.store-search-input {
  flex: 1;
  min-width: 0;
  border: none;
  outline: none;
  background: none;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 15px;
}

.store-search-input::placeholder {
  color: var(--ink-soft);
}

.store-search-clear {
  border: none;
  background: none;
  color: var(--ink-soft);
  cursor: pointer;
}

.stores-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
  margin-top: 64px;
}

/* ── Near you ──────────────────────────────────────────────────────── */
.near-you {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr);
  gap: clamp(24px, 4vw, 56px);
  align-items: center;
  margin-top: 48px;
  padding: clamp(24px, 3vw, 36px);
  border-radius: 22px;
  background: var(--ivory-deep);
}

.near-you + .stores-grid {
  margin-top: 40px;
}

.shop-page .near-you-copy h3 {
  margin-top: 14px;
  font-size: clamp(28px, 3vw, 40px);
}

.near-you-copy p:last-child {
  margin-top: 10px;
  color: var(--ink-soft);
  font-size: 14px;
}

.near-you-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 10px;
}

.near-you-store {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 12px 16px 12px 12px;
  border: 1px solid transparent;
  border-radius: 16px;
  background: var(--paper);
  color: var(--ink);
  text-align: left;
  cursor: pointer;
  transition: border-color 0.3s;
}

.near-you-store:hover {
  border-color: var(--ink);
}

.near-you-store:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 3px;
}

.near-you-store-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.near-you-name {
  overflow: hidden;
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 500;
  line-height: 1.15;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.near-you-distance {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.near-you-arrow {
  color: var(--ink-soft);
  transition: transform 0.3s var(--ease-out);
}

.near-you-store:hover .near-you-arrow {
  transform: translateX(3px);
}

.near-you-prompt {
  margin-top: 40px;
  color: var(--ink-soft);
  font-size: 14px;
}

.near-you-prompt .bc-link {
  margin-left: 8px;
}

.near-you-prompt + .stores-grid {
  margin-top: 28px;
}

@media (max-width: 968px) {
  .near-you {
    grid-template-columns: 1fr;
  }
}

.store-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 28px 26px 26px;
  border: 1px solid rgba(30, 27, 24, 0.1);
  border-radius: 18px;
  background: var(--paper);
  cursor: pointer;
  transition:
    border-color 0.4s var(--ease-out),
    transform 0.4s var(--ease-out);
}

.store-card:hover {
  border-color: var(--line);
  transform: translateY(-3px);
}

.store-card--active {
  border-color: var(--ink);
}

.store-card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 8px;
}

.store-logo {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  overflow: hidden;
  border-radius: 50%;
  background: var(--ivory-deep);
}

.store-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.store-logo--small {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
}

.store-logo-fallback {
  font-family: var(--font-display);
  font-size: 24px;
  font-weight: 500;
  color: var(--rosewood-dark);
}

.store-verified {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--rosewood-dark);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.store-verified svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.shop-page .store-name {
  font-size: 26px;
  line-height: 1.1;
}

.store-desc {
  min-height: 44px;
  color: var(--ink-soft);
  font-size: 13.5px;
  line-height: 1.65;
}

.store-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 0;
  color: var(--ink-soft);
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.store-meta li + li::before {
  content: "·";
  margin: 0 10px;
  color: var(--rosewood);
}

.store-visit {
  align-self: flex-start;
  margin-top: 10px;
  border: none;
  background: none;
  cursor: pointer;
}

.store-card--skeleton {
  cursor: default;
}

.store-card--skeleton:hover {
  transform: none;
}

.store-skeleton-logo {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  margin-bottom: 12px;
}

.store-skeleton-line {
  height: 14px;
}

.store-skeleton-line--short {
  width: 60%;
}

/* ============================================================
   SHOP BY OCCASION
============================================================= */
.occasion-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  column-gap: 64px;
  margin-top: 64px;
  border-top: 1px solid var(--line);
}

.occasion-item {
  display: grid;
  grid-template-columns: 52px minmax(0, 1fr) 24px;
  align-items: center;
  width: 100%;
  padding: 26px 0;
  border: none;
  border-bottom: 1px solid var(--line);
  background: none;
  color: var(--ink);
  text-align: left;
  cursor: pointer;
}

.occasion-index {
  font-family: var(--font-display);
  font-size: 19px;
  font-style: italic;
  color: var(--ink-soft);
}

.occasion-name {
  font-family: var(--font-display);
  font-size: clamp(26px, 2.6vw, 38px);
  font-weight: 500;
  line-height: 1.1;
  text-transform: capitalize;
  transition:
    transform 0.5s var(--ease-out),
    color 0.3s;
}

.occasion-arrow {
  color: var(--ink-soft);
  opacity: 0;
  transform: translateX(-6px);
  transition:
    opacity 0.4s,
    transform 0.4s var(--ease-out);
}

.occasion-item:hover .occasion-name,
.occasion-item:focus-visible .occasion-name {
  transform: translateX(10px);
  color: var(--rosewood-dark);
}

.occasion-item:hover .occasion-arrow,
.occasion-item:focus-visible .occasion-arrow,
.occasion-item--active .occasion-arrow {
  opacity: 1;
  transform: none;
}

.occasion-item--active .occasion-name {
  color: var(--rosewood-dark);
  font-style: italic;
}

.occasion-item:focus-visible {
  outline: none;
}

.occasion-skeleton {
  height: 30px;
  margin: 28px 0;
}

/* ============================================================
   PRODUCTS + FILTERS
============================================================= */
.shop-products {
  padding-bottom: var(--section-space);
}

.products-layout {
  display: grid;
  grid-template-columns: 260px minmax(0, 1fr);
  gap: clamp(32px, 4vw, 64px);
  align-items: start;
}

.filter-sidebar {
  position: sticky;
  top: 110px;
}

.active-vendor {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 28px;
  padding: 14px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--paper);
}

.active-vendor-info {
  flex: 1;
  min-width: 0;
}

.active-vendor-label {
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.active-vendor-name {
  overflow: hidden;
  font-family: var(--font-display);
  font-size: 19px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.btn-clear-vendor {
  border: none;
  background: none;
  color: var(--ink-soft);
  cursor: pointer;
}

.filter-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.shop-page .filter-header h2 {
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
  margin: 6px 0 22px;
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

.filter-option-skeletons {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.filter-option-skeletons .bc-skeleton {
  height: 12px;
}

.filter-option-skeletons .bc-skeleton:last-child {
  width: 60%;
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

.price-range {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.price-inputs {
  display: flex;
  align-items: center;
  gap: 8px;
}

.price-input {
  width: 100%;
  min-width: 0;
  padding: 9px 12px;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: var(--paper);
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 13px;
}

.price-input:focus {
  outline: none;
  border-color: var(--ink);
}

.price-separator {
  color: var(--ink-soft);
}

.slider {
  width: 100%;
  accent-color: var(--ink);
}

.filter-toggle:focus-visible,
.btn-clear-all:focus-visible,
.btn-clear-vendor:focus-visible,
.checkbox-label input:focus-visible,
.store-card:focus-visible,
.pagination-btn:focus-visible,
.sort-select:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 3px;
}

.products-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 20px;
  margin-bottom: 40px;
}

.products-head h2 {
  margin-top: 18px;
  font-size: clamp(36px, 3.6vw, 56px);
}

.btn-clear-store-inline {
  margin-top: 12px;
  border: none;
  background: none;
  cursor: pointer;
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
  padding: 72px 0;
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
  text-align: center;
}

.no-results h3 {
  font-size: 40px;
}

.no-results p {
  margin: 14px 0 28px;
  color: var(--ink-soft);
}

.shop-pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 20px;
  margin-top: 56px;
}

.pagination-btn {
  padding: 10px 20px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: transparent;
  color: var(--ink);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.3s;
}

.pagination-btn:hover:not(:disabled) {
  border-color: var(--ink);
}

.pagination-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pagination-info {
  color: var(--ink-soft);
  font-size: 13px;
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
  background: rgba(250, 248, 244, 0.92);
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

.modal-product-name {
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

.modal-price-badge {
  color: var(--rosewood-dark);
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
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
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
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
  color: var(--rosewood-dark);
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

.guest-notice {
  padding: 18px 20px;
  border-radius: 14px;
  background: var(--ivory);
  color: var(--ink-soft);
  font-size: 13.5px;
}

.guest-notice strong {
  color: var(--ink);
  font-weight: 500;
}

.guest-actions {
  display: flex;
  gap: 24px;
  margin-top: 10px;
}

.guest-actions .bc-link {
  border: none;
  background: none;
  cursor: pointer;
}

.modal-reviews-embed {
  padding-top: 22px;
  border-top: 1px solid var(--line);
}

.modal-reviews-component {
  margin-top: 16px;
}

/* ============================================================
   RESPONSIVE
============================================================= */
@media (max-width: 1100px) {
  .stores-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 968px) {
  .shop-hero {
    padding-top: 120px;
  }

  .shop-hero-grid {
    grid-template-columns: 1fr;
  }

  .shop-hero-figure {
    justify-self: stretch;
    max-height: 460px;
    aspect-ratio: 4 / 3;
    border-radius: 18px;
  }

  .store-search {
    justify-self: stretch;
    width: 100%;
  }

  .stores-grid,
  .occasion-list {
    margin-top: 40px;
  }

  .occasion-list {
    grid-template-columns: 1fr;
  }

  .products-layout {
    grid-template-columns: 1fr;
  }

  .filter-sidebar {
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
  .stores-grid {
    grid-template-columns: 1fr;
  }

  .products-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 28px 14px;
  }

  .sort-controls {
    width: 100%;
    justify-content: space-between;
  }

  .modal-overlay {
    padding: 0;
    align-items: flex-end;
  }

  .modal-content {
    max-height: 94vh;
    border-radius: 22px 22px 0 0;
  }

  .shop-pagination {
    gap: 10px;
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
</style>
