<template>
  <vendorHeader />
  <div class="inventory-layout">
    <VendorSidebar />

    <main class="main-content">
      <!-- Header -->
      <VendorHeader title="Stocks" v-model="searchQuery">
        <template #actions>
          <button class="icon-btn" title="Add">
            <router-link to="/vendor/add-product">➕</router-link>
          </button>
          <button class="icon-btn" title="Requests">
            <router-link to="/erp/procurement/inventory/funding-request/create"
              >📬</router-link
            >
          </button>
          <button class="icon-btn" title="Activity"><span>📊</span></button>
          <button class="icon-btn" title="Settings"><span>⚙️</span></button>
        </template>
      </VendorHeader>

      <!-- Tabs -->
      <div class="tabs">
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'approved' }"
          @click="switchTab('approved')"
        >
          Products
        </button>
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'draft' }"
          @click="switchTab('draft')"
        >
          Draft Products
        </button>
      </div>

      <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />

      <template v-if="!isLoading">
        <!-- Stats Card -->
        <div class="stats-card">
          <div class="stats-header">
            <div class="stats-icon">📦</div>
            <div class="stats-info">
              <div class="stats-label">TOTAL ASSET VALUE</div>
              <div class="stats-value">
                ₱{{ totalAssetValue.toLocaleString() }}
              </div>
            </div>
          </div>
          <div class="stats-body">
            <div class="product-count">
              {{ productCount }} product{{ productCount !== 1 ? "s" : "" }}
            </div>
            <div class="stock-status">
              <div class="status-item in-stock">
                <span class="status-dot"></span
                ><span>In stock: {{ inStock }}</span>
              </div>
              <div class="status-item low-stock">
                <span class="status-dot"></span
                ><span>Low stock: {{ lowStock }}</span>
              </div>
              <div class="status-item out-stock">
                <span class="status-dot"></span
                ><span>Out of stock: {{ outOfStock }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Content Body -->
        <div class="content-body">
          <div class="content-actions">
            <div class="search-bar">
              <span class="search-icon-sm">🔍</span>
              <input
                type="text"
                placeholder="Search name or SKU..."
                v-model="tableSearch"
              />
            </div>
            <div class="action-buttons">
              <div class="filter-menu">
                <button class="btn-filter" @click.stop="toggleFilterMenu">
                  <span>🎯</span><span>{{ activeFilterLabel }}</span>
                </button>
                <div v-if="showFilterMenu" class="filter-dropdown-menu">
                  <button
                    v-for="option in filterOptions"
                    :key="option.value"
                    class="filter-option"
                    :class="{ active: selectedFilter === option.value }"
                    @click="selectFilter(option.value)"
                  >
                    {{ option.label }}
                  </button>
                </div>
              </div>
              <router-link to="/vendor/add-product" class="btn-new-product">
                <span>➕</span><span>New Product</span>
              </router-link>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="filteredProducts.length === 0" class="empty-state">
            <div class="empty-icon">📦</div>
            <h3>No products found</h3>
            <p v-if="activeTab === 'approved'">
              You don't have any approved products yet.
            </p>
            <p v-else>
              You don't have any draft products. Create a new product to get
              started!
            </p>
            <router-link to="/vendor/add-product" class="btn-create-first"
              >Create Your First Product</router-link
            >
          </div>

          <!-- Inventory Table -->
          <div v-else class="inventory-table">
            <div class="table-header">
              <div class="th th-name">
                NAME <span class="sort-icon">▼</span>
              </div>
              <div class="th th-category">
                CATEGORY <span class="sort-icon">▼</span>
              </div>
              <div class="th th-sku">SKU <span class="sort-icon">▼</span></div>
              <div class="th th-price">
                PRICE <span class="sort-icon">▼</span>
              </div>
              <div class="th th-stock">
                STOCK <span class="sort-icon">▼</span>
              </div>
              <div class="th th-status">
                STATUS <span class="sort-icon">▼</span>
              </div>
              <div class="th th-actions"></div>
            </div>

            <div
              v-for="product in filteredProducts"
              :key="product.id"
              class="table-row"
            >
              <div class="td td-name" @click="openViewDetailsModal(product)">
                <div class="product-info">
                  <div class="product-img-wrap">
                    <img
                      v-if="product.primary_image?.image_url"
                      :src="product.primary_image.image_url"
                      :alt="product.product_name"
                      class="product-image"
                    />
                    <div
                      v-else
                      class="product-icon"
                      :style="{ background: getRandomColor() }"
                    >
                      🌸
                    </div>
                    <!-- Sale badge on table row thumbnail -->
                    <span
                      v-if="product.has_discount || isOnSale(product)"
                      class="sale-dot"
                      >SALE</span
                    >
                  </div>
                  <span>{{ product.product_name }}</span>
                </div>
              </div>
              <div class="td td-category">{{ product.category || "-" }}</div>
              <div class="td td-sku">{{ product.sku || "-" }}</div>
              <div class="td td-price">
                <div class="price-cell">
                  <span v-if="isOnSale(product)" class="price-sale"
                    >₱{{
                      parseFloat(product.discount_price || 0).toFixed(2)
                    }}</span
                  >
                  <span
                    :class="
                      isOnSale(product) ? 'price-original-sm' : 'price-normal'
                    "
                  >
                    ₱{{ parseFloat(product.selling_price || 0).toFixed(2) }}
                  </span>
                </div>
              </div>
              <div class="td td-stock">{{ product.quantity_in_stock }}</div>
              <div class="td td-status">
                <span class="status-badge" :class="getStatusClass(product)">
                  <span class="badge-dot"></span>
                  {{ getStatusText(product) }}
                </span>
              </div>
              <div class="td td-actions">
                <div class="action-menu">
                  <button class="btn-more" @click="toggleMenu(product.id)">
                    ⋯
                  </button>
                  <div v-if="activeMenu === product.id" class="dropdown-menu">
                    <a
                      href="#"
                      @click.prevent="openViewDetailsModal(product)"
                      class="menu-item"
                      >👁️ View Details</a
                    >
                    <a
                      href="#"
                      @click.prevent="openEditModal(product)"
                      class="menu-item"
                      >✏️ Edit Product</a
                    >
                    <a
                      v-if="activeTab === 'draft'"
                      href="#"
                      @click.prevent="openSubmitModal(product)"
                      class="menu-item"
                      >📤 Submit for Approval</a
                    >
                    <a
                      href="#"
                      @click.prevent="openDeleteModal(product)"
                      class="menu-item delete"
                      >🗑️ Delete</a
                    >
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </main>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- VIEW DETAILS MODAL — READ ONLY                         -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <transition name="modal-fade">
      <div
        v-if="showViewDetailsModal"
        class="modal-overlay"
        @click="closeViewDetailsModal"
      >
        <div class="modal-container modal-xl" @click.stop>
          <div class="modal-header">
            <div class="modal-header-left">
              <span class="modal-header-icon">👁️</span>
              <div>
                <h2>Product Details</h2>
                <p class="modal-header-sub">Read-only view</p>
              </div>
            </div>
            <div class="modal-header-right">
              <button class="btn-edit-from-view" @click="switchToEdit">
                ✏️ Edit Product
              </button>
              <button class="btn-close" @click="closeViewDetailsModal">
                ✕
              </button>
            </div>
          </div>

          <div class="modal-body form-modal-body" v-if="selectedProduct">
            <!-- Sale Banner -->
            <div v-if="isOnSale(selectedProduct)" class="sale-banner">
              <span class="sale-banner-icon">🏷️</span>
              <div>
                <strong>{{ calcDiscountPct(selectedProduct) }}% OFF</strong>
                <span> — This product is currently on sale!</span>
              </div>
              <span class="sale-banner-badge">SALE ACTIVE</span>
            </div>

            <!-- Two-column layout mirroring AddProduct -->
            <div class="view-form-grid">
              <!-- ── SECTION: Basic Information ── -->
              <div class="form-section-card full-span">
                <h3 class="fsc-title">📋 Basic Information</h3>
                <div class="form-grid-2">
                  <div class="ro-group full-width">
                    <label class="ro-label">Product Name</label>
                    <div class="ro-value">
                      {{ selectedProduct.product_name || "—" }}
                    </div>
                  </div>
                  <div class="ro-group full-width">
                    <label class="ro-label">Description</label>
                    <div class="ro-value ro-textarea">
                      {{ selectedProduct.description || "—" }}
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">SKU</label>
                    <div class="ro-value mono">
                      {{ selectedProduct.sku || "—" }}
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Category</label>
                    <div class="ro-value">
                      {{ selectedProduct.category || "—" }}
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Flower Type</label>
                    <div class="ro-value">
                      {{ formatFlowerType(selectedProduct.flower_type) }}
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Color</label>
                    <div class="ro-value">
                      <span
                        class="color-dot"
                        :style="{
                          background: colorDotBg(selectedProduct.color),
                        }"
                      ></span>
                      {{ capitalize(selectedProduct.color) || "—" }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- ── SECTION: Pricing ── -->
              <div class="form-section-card">
                <h3 class="fsc-title">💰 Pricing</h3>
                <div class="form-grid-2">
                  <div class="ro-group">
                    <label class="ro-label">Purchase Price</label>
                    <div class="ro-value">
                      ₱{{
                        parseFloat(selectedProduct.purchase_price || 0).toFixed(
                          2,
                        )
                      }}
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Selling Price</label>
                    <div class="ro-value price-highlight">
                      ₱{{
                        parseFloat(selectedProduct.selling_price || 0).toFixed(
                          2,
                        )
                      }}
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Discount Status</label>
                    <div class="ro-value">
                      <span v-if="isOnSale(selectedProduct)" class="pill-on"
                        >✓ Discount Enabled</span
                      >
                      <span v-else class="pill-off">No Discount</span>
                    </div>
                  </div>
                  <div v-if="isOnSale(selectedProduct)" class="ro-group">
                    <label class="ro-label">Discount Price</label>
                    <div class="ro-value sale-price">
                      ₱{{
                        parseFloat(selectedProduct.discount_price || 0).toFixed(
                          2,
                        )
                      }}
                      <span class="sale-pct-tag"
                        >-{{ calcDiscountPct(selectedProduct) }}%</span
                      >
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Profit Margin</label>
                    <div class="ro-value profit">
                      ₱{{ calcProfit(selectedProduct).toFixed(2) }}
                      <span class="profit-pct"
                        >({{ calcProfitPct(selectedProduct) }}%)</span
                      >
                    </div>
                  </div>
                </div>
              </div>

              <!-- ── SECTION: Stock ── -->
              <div class="form-section-card">
                <h3 class="fsc-title">📦 Stock Management</h3>
                <div class="form-grid-2">
                  <div class="ro-group">
                    <label class="ro-label">Current Stock</label>
                    <div class="ro-value">
                      <span class="stock-qty-big">{{
                        selectedProduct.quantity_in_stock
                      }}</span>
                      <span
                        class="status-badge ml-8"
                        :class="getStatusClass(selectedProduct)"
                      >
                        <span class="badge-dot"></span
                        >{{ getStatusText(selectedProduct) }}
                      </span>
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Min Stock Level</label>
                    <div class="ro-value">
                      {{ selectedProduct.min_stock_level || 0 }}
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Max Stock Level</label>
                    <div class="ro-value">
                      {{ selectedProduct.max_stock_level || "—" }}
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Season</label>
                    <div class="ro-value">
                      {{ formatSeason(selectedProduct.season) }}
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Selling Type</label>
                    <div class="ro-value">
                      {{ formatSellingType(selectedProduct.selling_type) }}
                    </div>
                  </div>
                </div>
                <div class="warehouse-note">
                  <span>🏭</span>
                  <span
                    >Storage location, batch dates & freshness are tracked in
                    the <strong>Warehouse module</strong>.</span
                  >
                </div>
              </div>

              <!-- ── SECTION: Supplier ── -->
              <div
                v-if="selectedProduct.supplier_name"
                class="form-section-card"
              >
                <h3 class="fsc-title">🏢 Supplier</h3>
                <div class="form-grid-2">
                  <div class="ro-group">
                    <label class="ro-label">Supplier Name</label>
                    <div class="ro-value">
                      {{ selectedProduct.supplier_name }}
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Contact</label>
                    <div class="ro-value">
                      {{ selectedProduct.supplier_contact || "—" }}
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Supplier SKU</label>
                    <div class="ro-value mono">
                      {{ selectedProduct.supplier_sku || "—" }}
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Preparation Time</label>
                    <div class="ro-value">
                      {{
                        selectedProduct.supplier_lead_time
                          ? selectedProduct.supplier_lead_time + " days"
                          : "—"
                      }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- ── SECTION: Attributes + Images ── -->
              <div class="form-section-card">
                <h3 class="fsc-title">🏷️ Attributes & Notes</h3>
                <div class="form-grid-2">
                  <div class="ro-group">
                    <label class="ro-label">Special Handling</label>
                    <div class="ro-value attr-row">
                      <span
                        v-if="selectedProduct.is_fragile"
                        class="attr-tag fragile"
                        >⚠️ Fragile</span
                      >
                      <span
                        v-if="selectedProduct.requires_refrigeration"
                        class="attr-tag cold"
                        >❄️ Refrigeration</span
                      >
                      <span
                        v-if="
                          !selectedProduct.is_fragile &&
                          !selectedProduct.requires_refrigeration
                        "
                        class="ro-muted"
                        >None</span
                      >
                    </div>
                  </div>
                  <div class="ro-group">
                    <label class="ro-label">Shop Status</label>
                    <div class="ro-value">
                      <span
                        :class="
                          selectedProduct.status === 'active'
                            ? 'pill-on'
                            : 'pill-off'
                        "
                      >
                        {{
                          selectedProduct.status === "active"
                            ? "✓ Visible in Shop"
                            : selectedProduct.status
                        }}
                      </span>
                    </div>
                  </div>
                </div>
                <div v-if="cleanedOccasionTags.length" class="ro-group mt-12">
                  <label class="ro-label">Occasion Tags</label>
                  <div class="tags-row">
                    <span
                      v-for="tag in cleanedOccasionTags"
                      :key="tag"
                      class="occasion-tag"
                      >{{ tag }}</span
                    >
                  </div>
                </div>
                <div
                  v-if="selectedProduct.care_instructions"
                  class="ro-group mt-12"
                >
                  <label class="ro-label">Care Instructions</label>
                  <div class="ro-value ro-textarea">
                    {{ selectedProduct.care_instructions }}
                  </div>
                </div>
                <div v-if="selectedProduct.notes" class="ro-group mt-12">
                  <label class="ro-label">Notes</label>
                  <div class="ro-value ro-textarea">
                    {{ selectedProduct.notes }}
                  </div>
                </div>
              </div>

              <!-- ── SECTION: Images ── -->
              <div
                v-if="selectedProduct.images?.length"
                class="form-section-card"
              >
                <h3 class="fsc-title">📷 Product Images</h3>
                <div class="image-preview-grid">
                  <div
                    v-for="(img, i) in selectedProduct.images"
                    :key="img.id"
                    class="img-thumb-wrap"
                  >
                    <img
                      :src="img.image_url"
                      :alt="selectedProduct.product_name"
                      class="img-thumb"
                    />
                    <span v-if="img.is_primary" class="primary-badge"
                      >Primary</span
                    >
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn-secondary" @click="closeViewDetailsModal">
              Close
            </button>
            <button class="btn-primary" @click="switchToEdit">
              ✏️ Edit This Product
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- EDIT PRODUCT MODAL                                     -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <transition name="modal-fade">
      <div v-if="showEditModal" class="modal-overlay" @click="closeEditModal">
        <div class="modal-container modal-xl" @click.stop>
          <div class="modal-header">
            <div class="modal-header-left">
              <span class="modal-header-icon">✏️</span>
              <div>
                <h2>Edit Product</h2>
                <p class="modal-header-sub">{{ editFormData.product_name }}</p>
              </div>
            </div>
            <button class="btn-close" @click="closeEditModal">✕</button>
          </div>

          <div class="modal-body form-modal-body">
            <form @submit.prevent="submitEditProduct">
              <!-- ── Basic Information ── -->
              <div class="form-section-card full-span">
                <h3 class="fsc-title">📋 Basic Information</h3>
                <div class="form-grid-2">
                  <div class="form-group full-width">
                    <label class="form-label">Product Name *</label>
                    <input
                      v-model="editFormData.product_name"
                      type="text"
                      class="form-input"
                      :class="{ 'is-invalid': editErrors.product_name }"
                      placeholder="e.g., Red Rose Bouquet"
                    />
                    <span v-if="editErrors.product_name" class="error-text">{{
                      editErrors.product_name
                    }}</span>
                  </div>
                  <div class="form-group full-width">
                    <label class="form-label">Description *</label>
                    <textarea
                      v-model="editFormData.description"
                      rows="3"
                      class="form-textarea"
                      :class="{ 'is-invalid': editErrors.description }"
                      placeholder="Describe your product..."
                    ></textarea>
                    <span v-if="editErrors.description" class="error-text">{{
                      editErrors.description
                    }}</span>
                  </div>
                  <div class="form-group">
                    <label class="form-label">SKU *</label>
                    <input
                      v-model="editFormData.sku"
                      type="text"
                      class="form-input"
                      :class="{ 'is-invalid': editErrors.sku }"
                      placeholder="e.g., ROSE-RED-001"
                    />
                    <span v-if="editErrors.sku" class="error-text">{{
                      editErrors.sku
                    }}</span>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select
                      v-model="editFormData.category"
                      class="form-select"
                      :class="{ 'is-invalid': editErrors.category }"
                    >
                      <option value="">Select category</option>
                      <option value="roses">Roses</option>
                      <option value="tulips">Tulips</option>
                      <option value="lilies">Lilies</option>
                      <option value="orchids">Orchids</option>
                      <option value="sunflowers">Sunflowers</option>
                      <option value="mixed-bouquets">Mixed Bouquets</option>
                      <option value="arrangements">Arrangements</option>
                      <option value="plants">Plants</option>
                      <option value="gifts">Gifts & Add-ons</option>
                      <option value="seasonal">Seasonal Flowers</option>
                    </select>
                    <span v-if="editErrors.category" class="error-text">{{
                      editErrors.category
                    }}</span>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Flower Type *</label>
                    <select
                      v-model="editFormData.flower_type"
                      class="form-select"
                    >
                      <option value="">Select flower type</option>
                      <option value="focal">Focal Flowers</option>
                      <option value="secondary">Secondary Flowers</option>
                      <option value="filler">Filler Flowers</option>
                      <option value="line">Line Flowers</option>
                      <option value="greenery">Greenery</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Color *</label>
                    <select v-model="editFormData.color" class="form-select">
                      <option value="">Select color</option>
                      <option value="white">White</option>
                      <option value="yellow">Yellow</option>
                      <option value="red">Red</option>
                      <option value="pink">Pink</option>
                      <option value="purple">Purple</option>
                      <option value="orange">Orange</option>
                      <option value="blue">Blue</option>
                      <option value="green">Green</option>
                      <option value="cream">Cream</option>
                      <option value="other">Other</option>
                    </select>
                  </div>
                  <div v-if="editFormData.color === 'other'" class="form-group">
                    <label class="form-label">Specify Color *</label>
                    <input
                      v-model="editFormData.color_other"
                      type="text"
                      class="form-input"
                      placeholder="e.g., Burgundy"
                    />
                  </div>
                </div>
              </div>

              <!-- ── Pricing ── -->
              <div class="form-section-card">
                <h3 class="fsc-title">💰 Pricing</h3>
                <div class="form-grid-2">
                  <div class="form-group">
                    <label class="form-label">Purchase Price *</label>
                    <div class="input-with-prefix">
                      <span class="prefix">₱</span>
                      <input
                        v-model.number="editFormData.purchase_price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="form-input"
                        :class="{ 'is-invalid': editErrors.purchase_price }"
                        @input="clearEditError('purchase_price')"
                      />
                    </div>
                    <span v-if="editErrors.purchase_price" class="error-text">{{
                      editErrors.purchase_price
                    }}</span>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Selling Price *</label>
                    <div class="input-with-prefix">
                      <span class="prefix">₱</span>
                      <input
                        v-model.number="editFormData.selling_price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="form-input"
                        :class="{ 'is-invalid': editErrors.selling_price }"
                        @input="clearEditError('selling_price')"
                      />
                    </div>
                    <span v-if="editErrors.selling_price" class="error-text">{{
                      editErrors.selling_price
                    }}</span>
                  </div>

                  <!-- Profit display -->
                  <div class="form-group">
                    <label class="form-label">Profit Margin</label>
                    <div class="profit-display">
                      <div class="profit-amount">
                        ₱{{ editProfitAmount.toFixed(2) }}
                      </div>
                      <div class="profit-percentage">
                        {{ editProfitPct.toFixed(1) }}% margin
                      </div>
                    </div>
                  </div>

                  <!-- Discount Toggle -->
                  <div class="form-group full-width">
                    <div class="discount-toggle-row">
                      <label class="toggle-switch">
                        <input
                          type="checkbox"
                          v-model="editFormData.has_discount"
                          @change="onEditDiscountToggle"
                        />
                        <span class="toggle-slider"></span>
                      </label>
                      <div class="toggle-label-group">
                        <span class="toggle-label-main"
                          >Enable Discount Price</span
                        >
                        <span class="toggle-label-sub"
                          >Show a crossed-out original price and a lower sale
                          price</span
                        >
                      </div>
                      <span
                        v-if="editFormData.has_discount"
                        class="discount-active-pill"
                        >🏷️ Sale Active</span
                      >
                    </div>
                  </div>

                  <!-- Discount inputs — shown only when enabled -->
                  <transition name="slide-down">
                    <div v-if="editFormData.has_discount">
                      <div class="form-group">
                        <label class="form-label">Discount Price *</label>
                        <div class="input-with-prefix">
                          <span class="prefix">₱</span>
                          <input
                            v-model.number="editFormData.discount_price"
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-input"
                            :class="{ 'is-invalid': editErrors.discount_price }"
                            @input="clearEditError('discount_price')"
                            placeholder="0.00"
                          />
                        </div>
                        <span
                          v-if="editErrors.discount_price"
                          class="error-text"
                          >{{ editErrors.discount_price }}</span
                        >
                        <span class="hint-text"
                          >Must be less than selling price</span
                        >
                      </div>
                      <div class="form-group">
                        <label class="form-label">Discount Amount</label>
                        <div class="discount-display">
                          <div class="discount-amount">
                            {{ editDiscountPct.toFixed(1) }}%
                          </div>
                          <div class="discount-text">off selling price</div>
                        </div>
                      </div>
                    </div>
                  </transition>
                </div>
              </div>

              <!-- ── Stock ── -->
              <div class="form-section-card">
                <h3 class="fsc-title">📦 Stock Management</h3>
                <div class="form-grid-2">
                  <div class="form-group">
                    <label class="form-label">Quantity in Stock *</label>
                    <input
                      v-model.number="editFormData.quantity_in_stock"
                      type="number"
                      min="0"
                      class="form-input"
                      :class="{ 'is-invalid': editErrors.quantity_in_stock }"
                      @input="clearEditError('quantity_in_stock')"
                    />
                    <span
                      v-if="editErrors.quantity_in_stock"
                      class="error-text"
                      >{{ editErrors.quantity_in_stock }}</span
                    >
                  </div>
                  <div class="form-group">
                    <label class="form-label">Min Stock Level *</label>
                    <input
                      v-model.number="editFormData.min_stock_level"
                      type="number"
                      min="0"
                      class="form-input"
                      @input="clearEditError('min_stock_level')"
                    />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Max Stock Level</label>
                    <input
                      v-model.number="editFormData.max_stock_level"
                      type="number"
                      min="0"
                      class="form-input"
                      placeholder="Optional"
                    />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Season</label>
                    <select v-model="editFormData.season" class="form-select">
                      <option value="all-year">All Year Round</option>
                      <option value="spring">Spring</option>
                      <option value="summer">Summer</option>
                      <option value="autumn">Autumn</option>
                      <option value="winter">Winter</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Selling Type</label>
                    <select
                      v-model="editFormData.selling_type"
                      class="form-select"
                    >
                      <option value="per_piece">Per Piece</option>
                      <option value="per_piece_customizable">
                        Per Piece (Customizable)
                      </option>
                      <option value="bouquet">Bouquet</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Shop Status</label>
                    <select v-model="editFormData.status" class="form-select">
                      <option value="active">Active (Visible in Shop)</option>
                      <option value="inactive">Inactive (Hidden)</option>
                      <option value="discontinued">Discontinued</option>
                    </select>
                  </div>
                </div>
                <div class="info-banner">
                  <span class="info-icon">🏭</span>
                  <div>
                    <strong>Storage & Freshness managed by Warehouse</strong>
                    <p>
                      Storage location, harvest dates, and batch tracking are
                      handled in the Warehouse module.
                    </p>
                  </div>
                </div>
              </div>

              <!-- ── Supplier ── -->
              <div class="form-section-card">
                <h3 class="fsc-title">🏢 Supplier Information</h3>
                <div class="form-grid-2">
                  <div class="form-group">
                    <label class="form-label">Supplier Name</label>
                    <input
                      v-model="editFormData.supplier_name"
                      type="text"
                      class="form-input"
                      placeholder="e.g., Garden Wholesale Inc."
                    />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Supplier Contact</label>
                    <input
                      v-model="editFormData.supplier_contact"
                      type="text"
                      class="form-input"
                      placeholder="Phone or email"
                    />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Supplier SKU</label>
                    <input
                      v-model="editFormData.supplier_sku"
                      type="text"
                      class="form-input"
                      placeholder="Supplier's product code"
                    />
                  </div>
                </div>
              </div>

              <!-- ── Additional Info ── -->
              <div class="form-section-card full-span">
                <h3 class="fsc-title">ℹ️ Additional Information</h3>
                <div class="form-grid-2">
                  <div class="form-group full-width">
                    <label class="form-label">Care Instructions</label>
                    <textarea
                      v-model="editFormData.care_instructions"
                      rows="2"
                      class="form-textarea"
                      placeholder="How to care for these flowers..."
                    ></textarea>
                  </div>
                  <div class="form-group full-width">
                    <label class="form-label"
                      >Occasion Tags (Select up to 2)</label
                    >
                    <div class="tag-selector">
                      <label
                        v-for="tag in occasionTags"
                        :key="tag"
                        class="tag-option"
                        :class="{ disabled: isEditTagDisabled(tag) }"
                      >
                        <input
                          type="checkbox"
                          :value="tag"
                          v-model="editFormData.occasion_tags"
                          :disabled="isEditTagDisabled(tag)"
                          @change="onEditTagChange"
                        />
                        <span>{{ tag }}</span>
                      </label>
                    </div>
                  </div>
                  <div class="form-group full-width">
                    <label class="form-label">Additional Notes</label>
                    <textarea
                      v-model="editFormData.notes"
                      rows="2"
                      class="form-textarea"
                      placeholder="Extra information..."
                    ></textarea>
                  </div>
                  <div class="form-group full-width checkboxes-row">
                    <label class="checkbox-label">
                      <input
                        type="checkbox"
                        v-model="editFormData.is_fragile"
                      />
                      <span>⚠️ Fragile — Handle with Care</span>
                    </label>
                    <label class="checkbox-label">
                      <input
                        type="checkbox"
                        v-model="editFormData.requires_refrigeration"
                      />
                      <span>❄️ Requires Refrigeration</span>
                    </label>
                  </div>
                </div>
              </div>

              <!-- ── Images (optional update) ── -->
              <div class="form-section-card full-span">
                <h3 class="fsc-title">
                  📷 Product Images
                  <span class="optional-tag">Optional — add more</span>
                </h3>
                <div class="image-upload-section">
                  <div class="image-grid">
                    <!-- Existing images -->
                    <div
                      v-for="(img, i) in existingImages"
                      :key="'ex-' + img.id"
                      class="image-preview"
                    >
                      <img :src="img.image_url" alt="Product" />
                      <button
                        type="button"
                        @click="removeExistingImage(i)"
                        class="remove-image-btn"
                      >
                        ✕
                      </button>
                      <div v-if="img.is_primary" class="primary-badge">
                        Primary
                      </div>
                    </div>
                    <!-- New images -->
                    <div
                      v-for="(img, i) in newProductImages"
                      :key="'new-' + i"
                      class="image-preview"
                    >
                      <img :src="img.url" alt="Product" />
                      <button
                        type="button"
                        @click="removeNewImage(i)"
                        class="remove-image-btn"
                      >
                        ✕
                      </button>
                      <div class="new-badge">New</div>
                    </div>
                    <!-- Upload slot -->
                    <div
                      v-if="existingImages.length + newProductImages.length < 5"
                      class="image-upload-placeholder"
                      @click="triggerEditFileInput"
                      @dragover.prevent
                      @drop.prevent="handleEditDrop"
                    >
                      <span class="upload-icon">📷</span>
                      <span class="upload-text">Add Photo</span>
                      <span class="upload-hint">Click or drag here</span>
                    </div>
                  </div>
                  <input
                    ref="editFileInput"
                    type="file"
                    accept="image/*"
                    multiple
                    @change="handleEditFileSelect"
                    style="display: none"
                  />
                  <p class="hint-text">
                    Up to 5 photos total. Removing existing images is permanent.
                  </p>
                </div>
              </div>
            </form>
          </div>

          <div class="modal-footer">
            <button
              class="btn-secondary"
              @click="closeEditModal"
              :disabled="isSubmitting"
            >
              Cancel
            </button>
            <div class="footer-right">
              <span v-if="isSubmitting" class="saving-indicator">
                <span class="loading-spinner-sm"></span> Saving...
              </span>
              <button
                class="btn-primary"
                @click="submitEditProduct"
                :disabled="isSubmitting"
              >
                💾 Save Changes
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- ═══════════════ SUBMIT APPROVAL MODAL ═══════════════ -->
    <transition name="modal-fade">
      <div
        v-if="showSubmitModal"
        class="modal-overlay"
        @click="closeSubmitModal"
      >
        <div class="modal-container modal-sm" @click.stop>
          <div class="modal-header">
            <h2>Submit for Approval</h2>
            <button class="btn-close" @click="closeSubmitModal">✕</button>
          </div>
          <div class="modal-body" v-if="selectedProduct">
            <div class="confirmation-icon">📤</div>
            <p class="modal-description">
              Are you sure you want to submit
              <strong>{{ selectedProduct.product_name }}</strong> for admin
              approval?
            </p>
            <p class="modal-note">
              Once submitted, you won't be able to edit this product until it's
              reviewed.
            </p>
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" @click="closeSubmitModal">
              Cancel
            </button>
            <button class="btn-primary" @click="confirmSubmitForApproval">
              Submit for Approval
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ═══════════════ DELETE MODAL ═══════════════ -->
    <transition name="modal-fade">
      <div
        v-if="showDeleteModal"
        class="modal-overlay"
        @click="closeDeleteModal"
      >
        <div class="modal-container modal-sm" @click.stop>
          <div class="modal-header">
            <h2>Delete Product</h2>
            <button class="btn-close" @click="closeDeleteModal">✕</button>
          </div>
          <div class="modal-body" v-if="selectedProduct">
            <div class="confirmation-icon danger">🗑️</div>
            <p class="modal-description">
              Are you sure you want to delete
              <strong>{{ selectedProduct.product_name }}</strong
              >?
            </p>
            <p class="modal-warning">
              ⚠️ This action cannot be undone. All product data will be
              permanently removed.
            </p>
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" @click="closeDeleteModal">
              Cancel
            </button>
            <button class="btn-danger" @click="confirmDeleteProduct">
              Delete Product
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, reactive } from "vue";
import { useRouter } from "vue-router";
import api from "../../plugins/axios";
import vendorHeader from "../../layouts/vendorHeader.vue";
import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";
import { useAuth } from "../../composables/useAuth";
import { toast } from "vue3-toastify";

const router = useRouter();
const { user } = useAuth();

const searchQuery = ref("");
const tableSearch = ref("");
const activeTab = ref("approved");
const products = ref([]);
const isLoading = ref(false);
const isLoadingMessage = ref("");
const isSubmitting = ref(false);
const activeMenu = ref(null);
const showFilterMenu = ref(false);
const selectedFilter = ref("all");
const selectedProduct = ref(null);
const editFileInput = ref(null);

// Modal states
const showViewDetailsModal = ref(false);
const showEditModal = ref(false);
const showSubmitModal = ref(false);
const showDeleteModal = ref(false);

// Images for edit
const existingImages = ref([]);
const newProductImages = ref([]);
const removedImageIds = ref([]);

// Edit form data
const editFormData = reactive({
  product_name: "",
  description: "",
  sku: "",
  category: "",
  flower_type: "",
  color: "",
  color_other: "",
  purchase_price: 0,
  selling_price: 0,
  has_discount: false,
  discount_price: null,
  quantity_in_stock: 0,
  min_stock_level: 0,
  max_stock_level: null,
  selling_type: "per_piece",
  season: "all-year",
  supplier_name: "",
  supplier_contact: "",
  supplier_sku: "",
  care_instructions: "",
  occasion_tags: [],
  notes: "",
  is_fragile: false,
  requires_refrigeration: false,
  status: "active",
});
const editErrors = reactive({});

const occasionTags = [
  "Wedding",
  "Birthday",
  "Anniversary",
  "Funeral",
  "Get Well",
  "Congratulations",
  "Sympathy",
  "Valentine",
  "Mother's Day",
  "Christmas",
  "Corporate",
  "Just Because",
];

// ── Computed ──────────────────────────────────────────────────────────────

const editProfitAmount = computed(() => {
  const s = parseFloat(editFormData.selling_price) || 0;
  const p = parseFloat(editFormData.purchase_price) || 0;
  return s - p;
});
const editProfitPct = computed(() => {
  const p = parseFloat(editFormData.purchase_price) || 0;
  if (p === 0) return 0;
  return (editProfitAmount.value / p) * 100;
});
const editDiscountPct = computed(() => {
  if (!editFormData.has_discount || !editFormData.discount_price) return 0;
  const s = parseFloat(editFormData.selling_price) || 0;
  const d = parseFloat(editFormData.discount_price) || 0;
  if (s === 0) return 0;
  return ((s - d) / s) * 100;
});

// ── Fetch ─────────────────────────────────────────────────────────────────

const fetchProducts = async () => {
  try {
    isLoading.value = true;
    isLoadingMessage.value = "Preparing product list...";
    const endpoint =
      activeTab.value === "draft"
        ? "/vendor/draft-products"
        : "/vendor/my-products";
    const response = await api.get(endpoint);
    if (response.data.success) {
      products.value = response.data.data;
    } else {
      toast.error("Failed to load products");
    }
  } catch (error) {
    if (error.response?.status === 401) {
      localStorage.removeItem("auth_token");
      localStorage.removeItem("user");
      router.push("/guest/login");
    } else {
      toast.error("Failed to load products. Please try again.");
    }
  } finally {
    isLoading.value = false;
  }
};

// ── Helpers ───────────────────────────────────────────────────────────────

const isOnSale = (p) =>
  p.discount_price &&
  parseFloat(p.discount_price) < parseFloat(p.selling_price);
const calcDiscountPct = (p) => {
  if (!isOnSale(p)) return 0;
  const s = parseFloat(p.selling_price),
    d = parseFloat(p.discount_price);
  return Math.round(((s - d) / s) * 100);
};
const calcProfit = (p) =>
  parseFloat(p.selling_price || 0) - parseFloat(p.purchase_price || 0);
const calcProfitPct = (p) => {
  const pur = parseFloat(p.purchase_price || 0);
  if (pur === 0) return 0;
  return ((calcProfit(p) / pur) * 100).toFixed(1);
};

const knownTags = [
  "Wedding",
  "Birthday",
  "Anniversary",
  "Funeral",
  "Get Well",
  "Congratulations",
  "Sympathy",
  "Valentine",
  "Mother's Day",
  "Christmas",
  "Corporate",
  "Just Because",
];
const cleanedOccasionTags = computed(() => {
  const raw = selectedProduct.value?.occasion_tags;
  if (!raw) return [];
  let joined = Array.isArray(raw) ? raw.join(" ") : raw;
  joined = joined.replace(/[\[\]\\"]/g, "").trim();
  return knownTags.filter((tag) =>
    new RegExp(`\\b${tag}\\b`, "i").test(joined),
  );
});

const totalAssetValue = computed(() =>
  products.value.reduce((sum, p) => {
    const quantity = parseInt(p.quantity_in_stock || 0);

    const price = isOnSale(p)
      ? parseFloat(p.discount_price || 0)
      : parseFloat(p.selling_price || 0);

    return sum + price * quantity;
  }, 0),
);

const productCount = computed(() => products.value.length);
const inStock = computed(
  () =>
    products.value.filter((p) => {
      const s = parseInt(p.quantity_in_stock || 0),
        m = parseInt(p.min_stock_level || 0);
      return s > m && s > 0;
    }).length,
);
const lowStock = computed(
  () =>
    products.value.filter((p) => {
      const s = parseInt(p.quantity_in_stock || 0),
        m = parseInt(p.min_stock_level || 0);
      return s > 0 && s <= m;
    }).length,
);
const outOfStock = computed(
  () =>
    products.value.filter((p) => parseInt(p.quantity_in_stock || 0) === 0)
      .length,
);

const filterOptions = [
  { value: "all", label: "All Products" },
  { value: "on_sale", label: "On Sale" },
  { value: "low_stock", label: "Low Stocks" },
  { value: "high_stock", label: "High Stocks" },
  { value: "name_asc", label: "A-Z" },
  { value: "name_desc", label: "Z-A" },
];

const activeFilterLabel = computed(
  () =>
    filterOptions.find((option) => option.value === selectedFilter.value)
      ?.label || "Filters",
);

const filteredProducts = computed(() => {
  let filtered = [...products.value];

  if (tableSearch.value) {
    const s = tableSearch.value.toLowerCase();
    filtered = filtered.filter(
      (p) =>
        p.product_name?.toLowerCase().includes(s) ||
        p.sku?.toLowerCase().includes(s) ||
        p.category?.toLowerCase().includes(s),
    );
  }

  if (selectedFilter.value === "on_sale") {
    filtered = filtered.filter((product) => isOnSale(product));
  } else if (selectedFilter.value === "low_stock") {
    filtered = filtered.filter((product) => {
      const stock = parseInt(product.quantity_in_stock || 0);
      const minStock = parseInt(product.min_stock_level || 0);
      return stock > 0 && stock <= minStock;
    });
  } else if (selectedFilter.value === "high_stock") {
    filtered = filtered.filter((product) => {
      const stock = parseInt(product.quantity_in_stock || 0);
      const minStock = parseInt(product.min_stock_level || 0);
      return stock > minStock;
    });
  } else if (selectedFilter.value === "name_asc") {
    filtered.sort((a, b) =>
      (a.product_name || "").localeCompare(b.product_name || "", undefined, {
        sensitivity: "base",
      }),
    );
  } else if (selectedFilter.value === "name_desc") {
    filtered.sort((a, b) =>
      (b.product_name || "").localeCompare(a.product_name || "", undefined, {
        sensitivity: "base",
      }),
    );
  }

  return filtered;
});

const getStatusClass = (product) => {
  const s = parseInt(product.quantity_in_stock || 0),
    m = parseInt(product.min_stock_level || 0);
  if (s === 0) return "out-of-stock";
  if (s <= m) return "low-stock";
  return "in-stock";
};
const getStatusText = (product) => {
  const s = parseInt(product.quantity_in_stock || 0),
    m = parseInt(product.min_stock_level || 0);
  if (s === 0) return "OUT OF STOCK";
  if (s <= m) return "LOW STOCK";
  return "IN STOCK";
};
const getRandomColor = () => {
  const colors = ["#fef3c7", "#d1fae5", "#e0e7ff", "#fce7f3", "#fed7aa"];
  return colors[Math.floor(Math.random() * colors.length)];
};
const formatSeason = (s) =>
  ({
    "all-year": "All Year",
    spring: "Spring",
    summer: "Summer",
    autumn: "Autumn",
    winter: "Winter",
  })[s] || s;
const formatSellingType = (t) =>
  ({
    per_piece: "Per Piece",
    per_piece_customizable: "Per Piece (Customizable)",
    bouquet: "Bouquet",
  })[t] || t;
const formatFlowerType = (t) =>
  ({
    focal: "Focal Flowers",
    secondary: "Secondary Flowers",
    filler: "Filler Flowers",
    line: "Line Flowers",
    greenery: "Greenery",
  })[t] || t;
const capitalize = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : "—");
const colorDotBg = (color) => {
  const map = {
    white: "#f5f5f5",
    yellow: "#fcd34d",
    red: "#f87171",
    pink: "#f9a8d4",
    purple: "#c084fc",
    orange: "#fb923c",
    blue: "#60a5fa",
    green: "#4ade80",
    cream: "#fef3c7",
  };
  return map[color] || "#e2e8f0";
};
const toggleMenu = (id) => {
  activeMenu.value = activeMenu.value === id ? null : id;
};
const toggleFilterMenu = () => {
  showFilterMenu.value = !showFilterMenu.value;
};
const selectFilter = (value) => {
  selectedFilter.value = value;
  showFilterMenu.value = false;
};
const switchTab = (tab) => {
  activeTab.value = tab;
  activeMenu.value = null;
  showFilterMenu.value = false;
  fetchProducts();
};

// ── Modal Openers ─────────────────────────────────────────────────────────

const openViewDetailsModal = (p) => {
  selectedProduct.value = p;
  showViewDetailsModal.value = true;
  activeMenu.value = null;
};
const closeViewDetailsModal = () => {
  showViewDetailsModal.value = false;
  selectedProduct.value = null;
};

const openEditModal = (p) => {
  selectedProduct.value = p;
  populateEditForm(p);
  showViewDetailsModal.value = false;
  showEditModal.value = true;
  activeMenu.value = null;
};
const closeEditModal = () => {
  showEditModal.value = false;
  Object.keys(editErrors).forEach((k) => delete editErrors[k]);
  newProductImages.value = [];
  removedImageIds.value = [];
};
const switchToEdit = () => {
  if (selectedProduct.value) openEditModal(selectedProduct.value);
};

const openSubmitModal = (p) => {
  selectedProduct.value = p;
  showSubmitModal.value = true;
  activeMenu.value = null;
};
const closeSubmitModal = () => {
  showSubmitModal.value = false;
  selectedProduct.value = null;
};
const openDeleteModal = (p) => {
  selectedProduct.value = p;
  showDeleteModal.value = true;
  activeMenu.value = null;
};
const closeDeleteModal = () => {
  showDeleteModal.value = false;
  selectedProduct.value = null;
};

// ── Edit Form Population ──────────────────────────────────────────────────

const populateEditForm = (p) => {
  const tags = Array.isArray(p.occasion_tags)
    ? p.occasion_tags.filter((t) => knownTags.includes(t))
    : knownTags.filter((tag) => {
        const joined = (p.occasion_tags || "").replace(/[\[\]\\"]/g, "");
        return new RegExp(`\\b${tag}\\b`, "i").test(joined);
      });

  Object.assign(editFormData, {
    product_name: p.product_name || "",
    description: p.description || "",
    sku: p.sku || "",
    category: p.category || "",
    flower_type: p.flower_type || "",
    color: p.color || "",
    color_other: "",
    purchase_price: parseFloat(p.purchase_price || 0),
    selling_price: parseFloat(p.selling_price || 0),
    has_discount: isOnSale(p),
    discount_price: p.discount_price ? parseFloat(p.discount_price) : null,
    quantity_in_stock: parseInt(p.quantity_in_stock || 0),
    min_stock_level: parseInt(p.min_stock_level || 0),
    max_stock_level: p.max_stock_level ? parseInt(p.max_stock_level) : null,
    selling_type: p.selling_type || "per_piece",
    season: p.season || "all-year",
    supplier_name: p.supplier_name || "",
    supplier_contact: p.supplier_contact || "",
    supplier_sku: p.supplier_sku || "",
    care_instructions: p.care_instructions || "",
    occasion_tags: tags,
    notes: p.notes || "",
    is_fragile: Boolean(p.is_fragile),
    requires_refrigeration: Boolean(p.requires_refrigeration),
    status: p.status || "active",
  });

  // Populate existing images
  existingImages.value = (p.images || []).map((img) => ({ ...img }));
  newProductImages.value = [];
  removedImageIds.value = [];
};

// ── Discount toggle ───────────────────────────────────────────────────────

const onEditDiscountToggle = () => {
  if (!editFormData.has_discount) {
    editFormData.discount_price = null;
    delete editErrors.discount_price;
  }
};

// ── Tag helpers ───────────────────────────────────────────────────────────

const isEditTagDisabled = (tag) =>
  editFormData.occasion_tags.length >= 2 &&
  !editFormData.occasion_tags.includes(tag);
const onEditTagChange = () => {
  if (editFormData.occasion_tags.length > 2)
    editFormData.occasion_tags = editFormData.occasion_tags.slice(0, 2);
};

// ── Image management ──────────────────────────────────────────────────────

const triggerEditFileInput = () => editFileInput.value?.click();
const handleEditFileSelect = (e) => addEditImages(Array.from(e.target.files));
const handleEditDrop = (e) => {
  e.preventDefault();
  addEditImages(Array.from(e.dataTransfer.files));
};
const addEditImages = (files) => {
  const remaining =
    5 - existingImages.value.length - newProductImages.value.length;
  const imageFiles = files
    .filter((f) => f.type.startsWith("image/"))
    .slice(0, remaining);
  imageFiles.forEach((file) => {
    const reader = new FileReader();
    reader.onload = (e) =>
      newProductImages.value.push({ file, url: e.target.result });
    reader.readAsDataURL(file);
  });
  if (editFileInput.value) editFileInput.value.value = "";
};
const removeExistingImage = (i) => {
  const removed = existingImages.value.splice(i, 1);
  if (removed[0]?.id) removedImageIds.value.push(removed[0].id);
};
const removeNewImage = (i) => newProductImages.value.splice(i, 1);

// ── Validation ────────────────────────────────────────────────────────────

const clearEditError = (field) => {
  if (editErrors[field]) delete editErrors[field];
};

const validateEditForm = () => {
  Object.keys(editErrors).forEach((k) => delete editErrors[k]);
  let isValid = true;

  if (!editFormData.product_name?.trim()) {
    editErrors.product_name = "Product Name is required";
    isValid = false;
  }
  if (!editFormData.description?.trim()) {
    editErrors.description = "Description is required";
    isValid = false;
  }
  if (!editFormData.sku?.trim()) {
    editErrors.sku = "SKU is required";
    isValid = false;
  }
  if (!editFormData.category) {
    editErrors.category = "Category is required";
    isValid = false;
  }

  const sell = parseFloat(editFormData.selling_price) || 0;
  const pur = parseFloat(editFormData.purchase_price) || 0;
  if (pur < 0) {
    editErrors.purchase_price = "Purchase price must be 0 or greater";
    isValid = false;
  }
  if (sell <= pur) {
    editErrors.selling_price =
      "Selling price must be greater than purchase price";
    isValid = false;
  }
  if (parseInt(editFormData.quantity_in_stock) < 0) {
    editErrors.quantity_in_stock = "Stock must be 0 or greater";
    isValid = false;
  }

  if (editFormData.has_discount) {
    const disc = parseFloat(editFormData.discount_price) || 0;
    if (!editFormData.discount_price || disc <= 0) {
      editErrors.discount_price =
        "Discount price is required when discount is enabled";
      isValid = false;
    } else if (disc >= sell) {
      editErrors.discount_price =
        "Discount price must be less than selling price";
      isValid = false;
    }
  }

  if (!isValid) {
    const first = Object.keys(editErrors)[0];
    toast.error(editErrors[first]);
  }
  return isValid;
};

// ── Submit Edit ───────────────────────────────────────────────────────────

const submitEditProduct = async () => {
  if (isSubmitting.value) return;
  if (!validateEditForm()) return;

  isSubmitting.value = true;
  try {
    const fd = new FormData();

    // 1. Method Spoofing for Laravel (Crucial for multipart/form-data with PUT)
    fd.append("_method", "PUT");

    // 2. Handle Booleans (Convert to 1 or 0)
    const booleans = ["is_fragile", "requires_refrigeration", "has_discount"];
    booleans.forEach((key) => {
      fd.append(key, editFormData[key] ? "1" : "0");
    });

    // 3. Handle All Other Fields (Including Nullables)
    Object.entries(editFormData).forEach(([key, value]) => {
      // Skip fields we handle specially
      if ([...booleans, "occasion_tags"].includes(key)) return;

      // Handle nullables: Laravel's ConvertEmptyStringsToNull handles this if we send ''
      if (value === null || value === undefined) {
        fd.append(key, "");
      } else {
        fd.append(key, value.toString());
      }
    });

    // 4. Handle Arrays correctly (occasion_tags, images[], removed_image_ids[])
    if (Array.isArray(editFormData.occasion_tags)) {
      editFormData.occasion_tags.forEach((tag) => {
        fd.append("occasion_tags[]", tag);
      });
    }

    // New images
    newProductImages.value.forEach((img) => {
      if (img.file) fd.append("images[]", img.file);
    });

    // Removed image IDs
    removedImageIds.value.forEach((id) => {
      fd.append("removed_image_ids[]", id);
    });

    // 5. Send Request (Axios handles boundary automatically if we DON'T set Content-Type)
    // We use POST + _method: 'PUT' because PHP/Laravel often fails to parse
    // multipart/form-data on native PUT/PATCH requests.
    const res = await api.post(
      `/vendor/products/${selectedProduct.value.id}`,
      fd,
    );

    if (res.data.success) {
      toast.success("Product updated successfully!");
      closeEditModal();
      fetchProducts();
    } else {
      toast.error(res.data.message || "Failed to update product");
    }
  } catch (error) {
    if (error.response?.data?.errors) {
      // Clear previous errors
      Object.keys(editErrors).forEach((k) => delete editErrors[k]);
      // Set new errors
      Object.entries(error.response.data.errors).forEach(([k, v]) => {
        editErrors[k] = Array.isArray(v) ? v[0] : v;
      });
      toast.error(
        Object.values(error.response.data.errors)[0]?.[0] ||
          "Validation failed",
      );
    } else {
      toast.error(error.response?.data?.message || "Failed to update product");
    }
  } finally {
    isSubmitting.value = false;
  }
};

// ── Other actions ─────────────────────────────────────────────────────────

const confirmSubmitForApproval = async () => {
  try {
    const res = await api.post(
      `/vendor/products/${selectedProduct.value.id}/submit-approval`,
    );
    if (res.data.success) {
      closeSubmitModal();
      fetchProducts();
      toast.success("Product submitted for approval!");
    } else toast.error("Failed to submit: " + res.data.message);
  } catch {
    toast.error("Failed to submit product for approval");
  }
};

const confirmDeleteProduct = async () => {
  try {
    const res = await api.delete(
      `/vendor/products/${selectedProduct.value.id}`,
    );
    if (res.data.success) {
      closeDeleteModal();
      fetchProducts();
      toast.success("Product deleted successfully!");
    } else toast.error("Failed to delete: " + res.data.message);
  } catch {
    toast.error("Failed to delete product");
  }
};

const handleClickOutside = (e) => {
  if (!e.target.closest(".action-menu")) activeMenu.value = null;
  if (!e.target.closest(".filter-menu")) showFilterMenu.value = false;
};

onMounted(() => {
  fetchProducts();
  document.addEventListener("click", handleClickOutside);
});
watch(activeTab, () => {
  selectedProduct.value = null;
});
</script>

<style scoped>
*,
*::before,
*::after {
  font-family:
    "Poppins",
    -apple-system,
    sans-serif;
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

.inventory-layout {
  display: flex;
  min-height: 100vh;
  background: #f9fafb;
}
.main-content {
  flex: 1;
  padding: 32px 40px;
  overflow-y: auto;
}

/* ── Header ── */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
}
.breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #9ca3af;
  margin-bottom: 6px;
}
.bc-active {
  color: #6b7280;
  font-weight: 500;
}
.page-title {
  font-size: 26px;
  font-weight: 700;
  color: #111827;
  letter-spacing: -0.4px;
}
.page-sub {
  font-size: 13px;
  color: #6b7280;
  margin-top: 3px;
}
.permission-banner {
  margin-bottom: 18px;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid #f59e0b;
  background: #fffbeb;
  color: #92400e;
  font-size: 13px;
  line-height: 1.5;
}
.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.search-wrap {
  position: relative;
}
.search-icon {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  pointer-events: none;
}
.search-input {
  padding: 9px 14px 9px 34px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  font-family: inherit;
  background: #fff;
  width: 220px;
  outline: none;
  color: #111827;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}
.search-input:focus {
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
}
.search-input::placeholder {
  color: #9ca3af;
}

.btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  font-size: 13px;
  font-weight: 500;
  font-family: inherit;
  color: #6b7280;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s;
}
.btn-ghost:hover {
  border-color: #16a34a;
  color: #16a34a;
  background: #dcfce7;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 16px;
  border: none;
  border-radius: 8px;
  background: #16a34a;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s;
}
.btn-primary:hover {
  background: #15803d;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
}
.btn-primary:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}
.btn-disabled-link {
  opacity: 0.55;
  pointer-events: auto;
  cursor: not-allowed;
  transform: none !important;
  box-shadow: none !important;
}

/* ── Stat Grid ── */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}
.stat-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px 18px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07);
  transition:
    transform 0.2s,
    box-shadow 0.2s;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}
.stat-icon {
  width: 40px;
  height: 40px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.si-purple {
  background: #ede9fe;
  color: #7c3aed;
}
.si-blue {
  background: #dbeafe;
  color: #2563eb;
}
.si-green {
  background: #dcfce7;
  color: #16a34a;
}
.si-amber {
  background: #fef3c7;
  color: #d97706;
}
.si-red {
  background: #fee2e2;
  color: #dc2626;
}
.stat-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.stat-val {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  letter-spacing: -0.3px;
}
.stat-lbl {
  font-size: 11px;
  color: #9ca3af;
  font-weight: 500;
}

/* ── Table Card ── */
.table-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07);
  overflow: hidden;
}

/* ── Tabs ── */
.tab-bar {
  display: flex;
  gap: 4px;
  padding: 12px 16px 0;
  border-bottom: 1px solid #e5e7eb;
  background: #fafafa;
}
.tab {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 10px 16px 11px;
  border: none;
  border-bottom: 2px solid transparent;
  background: transparent;
  font-size: 13px;
  font-weight: 500;
  font-family: inherit;
  color: #6b7280;
  cursor: pointer;
  margin-bottom: -1px;
  transition: all 0.2s;
}
.tab:hover {
  color: #111827;
}
.tab.active {
  color: #16a34a;
  border-bottom-color: #16a34a;
  font-weight: 600;
}
.tab-pill {
  padding: 2px 7px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
}
.tp-approved {
  background: #dcfce7;
  color: #16a34a;
}
.tp-draft {
  background: #f3f4f6;
  color: #6b7280;
}

/* ── Toolbar ── */
.table-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 18px;
  border-bottom: 1px solid #e5e7eb;
}
.results-txt {
  font-size: 13px;
  color: #6b7280;
}
.results-txt strong {
  color: #111827;
}
.btn-filter {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  font-size: 12px;
  font-weight: 500;
  font-family: inherit;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-filter:hover {
  border-color: #16a34a;
  color: #16a34a;
}

/* ── States ── */
.state-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  gap: 12px;
}
.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top-color: #16a34a;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
.state-center p {
  font-size: 13px;
  color: #9ca3af;
}
.empty .empty-icon {
  width: 72px;
  height: 72px;
  border-radius: 16px;
  background: #f9fafb;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  margin-bottom: 4px;
}
.empty h3 {
  font-size: 17px;
  font-weight: 600;
  color: #111827;
}
.empty p {
  font-size: 13px;
  color: #6b7280;
}

/* ── Data Table ── */
.data-table {
  width: 100%;
}
.t-head {
  display: grid;
  grid-template-columns: 2.4fr 1fr 1fr 1.2fr 1.3fr 1.1fr 52px;
  gap: 10px;
  padding: 11px 18px;
  background: #fafafa;
  border-bottom: 1px solid #e5e7eb;
}
.t-head > div {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #9ca3af;
}
.t-row {
  display: grid;
  grid-template-columns: 2.4fr 1fr 1fr 1.2fr 1.3fr 1.1fr 52px;
  gap: 10px;
  padding: 13px 18px;
  border-bottom: 1px solid #f3f4f6;
  align-items: center;
  transition: background 0.15s;
}
.t-row:last-child {
  border-bottom: none;
}
.t-row:hover {
  background: #fafafa;
}
.td {
  font-size: 13px;
  color: #111827;
}

/* Product cell */
.td-product {
  display: flex;
  align-items: center;
  gap: 10px;
}
.p-thumb-wrap {
  position: relative;
  flex-shrink: 0;
}
.p-thumb {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}
.p-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.sale-dot {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #dc2626;
  color: #fff;
  font-size: 7px;
  font-weight: 700;
  padding: 1px 3px;
  border-radius: 3px;
  line-height: 1.2;
}
.p-meta {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}
.p-name {
  font-weight: 600;
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.p-season {
  font-size: 11px;
  color: #9ca3af;
}
.chip {
  display: inline-block;
  padding: 3px 9px;
  background: #f3f4f6;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
  color: #6b7280;
}
.mono {
  font-family: monospace;
  font-size: 11px;
  color: #6b7280;
}

/* Price cell */
.td-price {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.price-sale {
  font-weight: 700;
  color: #dc2626;
  font-size: 13px;
}
.price-struck {
  font-size: 11px;
  color: #9ca3af;
  text-decoration: line-through;
}
.price-main {
  font-weight: 600;
  font-size: 13px;
  color: #111827;
}
.price-cost {
  font-size: 11px;
  color: #9ca3af;
}

/* Stock cell */
.td-stock {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.stock-num {
  font-weight: 600;
  font-size: 14px;
  color: #111827;
}
.stock-track {
  height: 3px;
  background: #f3f4f6;
  border-radius: 2px;
  overflow: hidden;
}
.stock-fill {
  height: 100%;
  border-radius: 2px;
  transition: width 0.3s;
}
.sf-in {
  background: #16a34a;
}
.sf-low {
  background: #d97706;
}
.sf-out {
  background: #dc2626;
  width: 0 !important;
}

/* Status badge */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
}
.sb-in {
  background: #dcfce7;
  color: #16a34a;
}
.sb-low {
  background: #fef3c7;
  color: #d97706;
}
.sb-out {
  background: #fee2e2;
  color: #dc2626;
}
.badge-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: currentColor;
}

/* Action menu */
.td-action {
  display: flex;
  justify-content: flex-end;
}
.menu-wrap {
  position: relative;
}
.menu-btn {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #9ca3af;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.menu-btn:hover {
  border-color: #16a34a;
  color: #16a34a;
  background: #dcfce7;
}
.menu-btn:disabled {
  cursor: not-allowed;
  color: #d1d5db;
  background: #f9fafb;
  border-color: #e5e7eb;
}
.menu-dropdown {
  position: fixed;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.14);
  min-width: 180px;
  z-index: 200;
  overflow: hidden;
}
.menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 9px 13px;
  background: transparent;
  border: none;
  font-size: 13px;
  font-weight: 500;
  font-family: inherit;
  color: #111827;
  cursor: pointer;
  transition: background 0.15s;
}
.menu-item:hover {
  background: #f9fafb;
}
.menu-item:disabled {
  color: #9ca3af;
  cursor: not-allowed;
  background: transparent;
}
.menu-item.danger {
  color: #dc2626;
}
.menu-item.danger:hover {
  background: #fee2e2;
}
.menu-sep {
  height: 1px;
  background: #e5e7eb;
  margin: 3px 0;
}
.pop-enter-active,
.pop-leave-active {
  transition:
    opacity 0.15s,
    transform 0.15s;
}
.pop-enter-from,
.pop-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.97);
}

/* ── Modals ── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}
.modal-box {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.2);
  width: 100%;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.modal-box.modal-sm {
  max-width: 460px;
}
.modal-box.modal-xl {
  max-width: 920px;
}

.modal-hd {
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-shrink: 0;
}
.mhd-left {
  display: flex;
  align-items: center;
  gap: 14px;
}
.mhd-ico {
  font-size: 28px;
  line-height: 1;
}
.mhd-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.mhd-sub {
  font-size: 12px;
  color: #9ca3af;
  margin: 2px 0 0;
}
.btn-close {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  color: #6b7280;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.btn-close:hover {
  background: #e5e7eb;
  color: #111827;
}

.modal-bd {
  overflow-y: auto;
  flex: 1;
}
.modal-bd.scroll {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.modal-ft {
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  flex-shrink: 0;
}

.btn-ghost-sm {
  padding: 9px 20px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
  font-size: 13px;
  font-weight: 600;
  font-family: inherit;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-ghost-sm:hover {
  background: #e5e7eb;
  color: #111827;
}
.btn-danger {
  padding: 9px 20px;
  border: none;
  border-radius: 8px;
  background: #dc2626;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-danger:hover {
  background: #b91c1c;
}

/* ── View modal detail cards ── */
.sale-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  background: linear-gradient(135deg, #fef2f2, #fff5f5);
  border: 1.5px solid #fca5a5;
  border-radius: 10px;
  border-left: 4px solid #dc2626;
}
.sale-banner strong {
  color: #dc2626;
  font-size: 15px;
}
.sale-banner span {
  font-size: 14px;
  color: #6b7280;
}
.sale-banner-badge {
  margin-left: auto;
  padding: 5px 12px;
  background: #dc2626;
  color: #fff;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}

.vd-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.vd-card {
  background: #fafbfd;
  border: 1px solid #edf0f5;
  border-radius: 12px;
  padding: 18px;
}
.vd-card.span2 {
  grid-column: 1 / -1;
}
.vdc-title {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 14px;
  padding-bottom: 10px;
  border-bottom: 1px solid #e8edf5;
}

.ro-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.ro-g {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.ro-g.span2 {
  grid-column: 1 / -1;
}
.ro-g label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
}
.ro-val {
  font-size: 13.5px;
  color: #111827;
  font-weight: 500;
  padding: 8px 12px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  min-height: 38px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.ro-val.ro-tall {
  align-items: flex-start;
  min-height: 60px;
  line-height: 1.6;
}
.ro-val.mono {
  font-family: monospace;
  font-size: 12px;
}
.ro-val.val-green {
  color: #16a34a;
  font-weight: 700;
  font-size: 15px;
}
.ro-val.val-red {
  color: #dc2626;
  font-weight: 700;
  font-size: 15px;
}
.pct-badge {
  font-size: 12px;
  opacity: 0.75;
}
.pct-badge.red {
  padding: 2px 8px;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  opacity: 1;
}
.big-num {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
}
.pill-on {
  display: inline-block;
  padding: 4px 10px;
  background: #dcfce7;
  color: #065f46;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}
.pill-off {
  display: inline-block;
  padding: 4px 10px;
  background: #f3f4f6;
  color: #6b7280;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}
.attr-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.attr-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}
.attr-tag.amber {
  background: #fef3c7;
  color: #92400e;
}
.attr-tag.blue {
  background: #dbeafe;
  color: #1e40af;
}
.muted-txt {
  color: #9ca3af;
  font-size: 13px;
  font-style: italic;
}
.tags-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.occ-tag {
  display: inline-block;
  padding: 5px 12px;
  background: #ede9fe;
  color: #7c3aed;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}
.img-strip {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.img-tw {
  position: relative;
}
.img-tw img {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  object-fit: cover;
  border: 2px solid #e5e7eb;
}
.pri-badge {
  position: absolute;
  bottom: 4px;
  left: 4px;
  padding: 2px 6px;
  background: #16a34a;
  color: #fff;
  font-size: 9px;
  font-weight: 700;
  border-radius: 3px;
}
.ro-label-sm {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
  display: block;
  margin-bottom: 8px;
}

/* ── Stock modal fields ── */
.mf-grp {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 14px;
}
.mf-grp label {
  font-size: 12px;
  font-weight: 600;
  color: #111827;
}
.mf-inp {
  padding: 9px 13px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  font-family: inherit;
  color: #111827;
  outline: none;
  transition: border-color 0.2s;
  width: 100%;
}
.mf-inp:focus {
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
}
.mf-inp:disabled {
  background: #f9fafb;
  color: #9ca3af;
  cursor: not-allowed;
}

/* Confirm modal */
.modal-desc {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 12px;
  line-height: 1.6;
}
.modal-note {
  font-size: 13px;
  color: #6b7280;
  background: #f9fafb;
  padding: 12px;
  border-radius: 8px;
}
.modal-warn {
  font-size: 13px;
  color: #c53030;
  background: #fff5f5;
  padding: 12px;
  border-radius: 8px;
  margin-top: 8px;
  border-left: 3px solid #fc8181;
}
.confirm-ico {
  font-size: 48px;
  text-align: center;
  margin-bottom: 16px;
}

/* Modal transition */
.mfade-enter-active,
.mfade-leave-active {
  transition: opacity 0.2s ease;
}
.mfade-enter-from,
.mfade-leave-to {
  opacity: 0;
}
.mfade-enter-active .modal-box,
.mfade-leave-active .modal-box {
  transition: transform 0.25s ease;
}
.mfade-enter-from .modal-box,
.mfade-leave-to .modal-box {
  transform: scale(0.95) translateY(8px);
}

/* ── Edit Modal Form Controls ── */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-group.full-width,
.form-group.span2 {
  grid-column: 1 / -1;
}
.form-label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}
.form-input,
.form-select,
.form-textarea {
  padding: 9px 13px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  color: #111827;
  transition: all 0.2s;
  background: #fff;
  font-family: inherit;
  width: 100%;
}
.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
}
.form-textarea {
  resize: vertical;
  min-height: 64px;
}
.form-input.is-invalid,
.form-select.is-invalid,
.form-textarea.is-invalid {
  border-color: #dc2626;
}
.error-text {
  color: #dc2626;
  font-size: 11px;
}
.hint-text {
  font-size: 11px;
  color: #9ca3af;
}
.input-with-prefix {
  position: relative;
  display: flex;
  align-items: center;
}
.prefix {
  position: absolute;
  left: 12px;
  color: #9ca3af;
  font-weight: 500;
  z-index: 1;
  pointer-events: none;
}
.input-with-prefix .form-input {
  padding-left: 28px;
}
.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.profit-display {
  padding: 12px;
  background: #dcfce7;
  border-radius: 8px;
  border: 1px solid #86efac;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.profit-amount {
  font-size: 18px;
  font-weight: 700;
  color: #15803d;
}
.profit-percentage {
  font-size: 11px;
  color: #16a34a;
}
.discount-display {
  padding: 12px;
  background: #fef3c7;
  border-radius: 8px;
  border: 1px solid #fde68a;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.discount-amount {
  font-size: 18px;
  font-weight: 700;
  color: #b45309;
}
.discount-text {
  font-size: 11px;
  color: #d97706;
}
.discount-toggle-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 40px;
  height: 22px;
  flex-shrink: 0;
}
.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}
.toggle-slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background: #d1d5db;
  border-radius: 22px;
  transition: 0.3s;
}
.toggle-slider::before {
  content: "";
  position: absolute;
  height: 16px;
  width: 16px;
  left: 3px;
  bottom: 3px;
  background: #fff;
  border-radius: 50%;
  transition: 0.3s;
}
.toggle-switch input:checked + .toggle-slider {
  background: #16a34a;
}
.toggle-switch input:checked + .toggle-slider::before {
  transform: translateX(18px);
}
.toggle-label-group {
  flex: 1;
}
.toggle-label-main {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}
.toggle-label-sub {
  display: block;
  font-size: 11px;
  color: #9ca3af;
}
.discount-active-pill {
  padding: 4px 10px;
  background: #dc2626;
  color: #fff;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
}
.tag-selector {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.tag-option {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  background: #fff;
}
.tag-option:hover:not(.disabled) {
  border-color: #16a34a;
  background: #f0fdf4;
}
.tag-option.disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.tag-option input[type="checkbox"] {
  accent-color: #16a34a;
}
.checkboxes-row {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}
.checkbox-label {
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  font-size: 13px;
  color: #111827;
}
.checkbox-label input[type="checkbox"] {
  accent-color: #16a34a;
}
.image-upload-section {
  width: 100%;
}
.image-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 8px;
}
.image-preview {
  position: relative;
  width: 72px;
  height: 72px;
  border-radius: 8px;
  overflow: hidden;
  border: 2px solid #e5e7eb;
}
.image-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.remove-image-btn {
  position: absolute;
  top: 3px;
  right: 3px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: none;
  background: rgba(220, 38, 38, 0.9);
  color: #fff;
  cursor: pointer;
  font-size: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.image-upload-placeholder {
  width: 72px;
  height: 72px;
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  background: #f9fafb;
  gap: 2px;
}
.image-upload-placeholder:hover {
  border-color: #16a34a;
  background: #dcfce7;
}
.upload-icon {
  font-size: 18px;
}
.upload-text {
  font-size: 9px;
  font-weight: 600;
  color: #6b7280;
}
.new-badge {
  position: absolute;
  bottom: 3px;
  left: 3px;
  padding: 1px 5px;
  background: #2563eb;
  color: #fff;
  font-size: 8px;
  font-weight: 700;
  border-radius: 3px;
}

/* Responsive */
@media (max-width: 1400px) {
  .stat-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
@media (max-width: 1024px) {
  .main-content {
    padding: 20px;
  }
  .stat-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .t-head,
  .t-row {
    grid-template-columns: 2fr 1fr 1fr 1fr 48px;
  }
  .t-head > div:nth-child(3),
  .t-row .td:nth-child(3),
  .t-head > div:nth-child(5),
  .t-row .td:nth-child(5) {
    display: none;
  }
  .vd-grid {
    grid-template-columns: 1fr;
  }
  .ro-grid {
    grid-template-columns: 1fr;
  }
  .form-grid-2 {
    grid-template-columns: 1fr;
  }
  .tag-selector {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .stat-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .tag-selector {
    grid-template-columns: 1fr;
  }
}
</style>
