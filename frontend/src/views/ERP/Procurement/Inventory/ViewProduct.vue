<template>
  <div class="inventory-layout">
    <main class="main-content">
      <!-- ── Page Header ── -->
      <div class="page-header">
        <div class="header-left">
          <div class="breadcrumb">
            <span>Inventory</span>
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polyline points="9 18 15 12 9 6" />
            </svg>
            <span class="bc-active">Stock Management</span>
          </div>
          <h1 class="page-title">Stocks</h1>
          <p class="page-sub">Monitor and manage your product inventory</p>
        </div>
        <div class="header-right">
          <div class="search-wrap">
            <svg
              class="search-icon"
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
            >
              <circle cx="11" cy="11" r="8" />
              <path d="m21 21-4.35-4.35" />
            </svg>
            <input
              type="text"
              placeholder="Search products…"
              v-model="tableSearch"
              class="search-input"
            />
          </div>
          <router-link
            to="/erp/procurement/inventory/funding-request/create"
            class="btn-ghost"
            :class="{ 'btn-disabled-link': !canCreateFunding }"
            :aria-disabled="!canCreateFunding"
            :title="canCreateFunding ? '' : permissionMessages.create"
            @click.prevent="
              !canCreateFunding && toast.error(permissionMessages.create)
            "
          >
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
              />
              <polyline points="14 2 14 8 20 8" />
              <line x1="12" y1="11" x2="12" y2="17" />
              <line x1="9" y1="14" x2="15" y2="14" />
            </svg>
            Funding Request
          </router-link>
          <router-link
            to="/erp/procurement/inventory/add-product"
            class="btn-primary"
            :class="{ 'btn-disabled-link': !canCreateInventoryProducts }"
            :aria-disabled="!canCreateInventoryProducts"
            :title="canCreateInventoryProducts ? '' : permissionMessages.create"
            @click.prevent="
              !canCreateInventoryProducts &&
              toast.error(permissionMessages.create)
            "
          >
            <svg
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
            >
              <line x1="12" y1="5" x2="12" y2="19" />
              <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            New Product
          </router-link>
        </div>
      </div>

      <!-- ── Stat Cards ── -->
      <div v-if="isReadOnlyInventoryProducts" class="permission-banner">
        Read-only mode. You can review stock levels, but only employees with
        edit access can update inventory products.
      </div>

      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-icon si-purple">
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
              <polyline points="9 22 9 12 15 12 15 22" />
            </svg>
          </div>
          <div class="stat-body">
            <span class="stat-val"
              >₱{{
                totalAssetValue.toLocaleString("en-US", {
                  minimumFractionDigits: 0,
                })
              }}</span
            >
            <span class="stat-lbl">Total Asset Value</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon si-blue">
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <rect x="2" y="3" width="20" height="14" rx="2" />
              <path d="M8 21h8M12 17v4" />
            </svg>
          </div>
          <div class="stat-body">
            <span class="stat-val">{{ productCount }}</span>
            <span class="stat-lbl">Total Products</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon si-green">
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
              <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <div class="stat-body">
            <span class="stat-val">{{ inStock }}</span>
            <span class="stat-lbl">In Stock</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon si-amber">
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"
              />
              <line x1="12" y1="9" x2="12" y2="13" />
              <line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
          </div>
          <div class="stat-body">
            <span class="stat-val">{{ lowStock }}</span>
            <span class="stat-lbl">Low Stock</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon si-red">
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <circle cx="12" cy="12" r="10" />
              <line x1="15" y1="9" x2="9" y2="15" />
              <line x1="9" y1="9" x2="15" y2="15" />
            </svg>
          </div>
          <div class="stat-body">
            <span class="stat-val">{{ outOfStock }}</span>
            <span class="stat-lbl">Out of Stock</span>
          </div>
        </div>
      </div>

      <!-- ── Table Card ── -->
      <div class="table-card">
        <div class="tab-bar">
          <button
            class="tab"
            :class="{ active: activeTab === 'approved' }"
            @click="switchTab('approved')"
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
              <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
            Approved Products
            <span class="tab-pill tp-approved">{{
              getTabCount("approved")
            }}</span>
          </button>
          <button
            class="tab"
            :class="{ active: activeTab === 'draft' }"
            @click="switchTab('draft')"
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
              />
              <path
                d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
              />
            </svg>
            Draft Products
            <span class="tab-pill tp-draft">{{ getTabCount("draft") }}</span>
          </button>
        </div>

        <div v-if="isLoading" class="state-center">
          <div class="spinner"></div>
          <p>{{ isLoadingMessage }}</p>
        </div>

        <template v-else>
          <div class="table-toolbar">
            <span class="results-txt"
              ><strong>{{ filteredProducts.length }}</strong> product{{
                filteredProducts.length !== 1 ? "s" : ""
              }}</span
            >
            <button class="btn-filter">
              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <line x1="4" y1="6" x2="20" y2="6" />
                <line x1="8" y1="12" x2="16" y2="12" />
                <line x1="11" y1="18" x2="13" y2="18" />
              </svg>
              Filter
            </button>
          </div>

          <div v-if="filteredProducts.length === 0" class="state-center empty">
            <div class="empty-icon">
              <svg
                width="48"
                height="48"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1"
              >
                <rect x="2" y="7" width="20" height="14" rx="2" />
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
              </svg>
            </div>
            <h3>No products found</h3>
            <p>
              {{
                activeTab === "approved"
                  ? "No approved products yet."
                  : "No draft products. Create one to get started!"
              }}
            </p>
            <router-link
              to="/erp/procurement/inventory/add-product"
              class="btn-primary"
              :class="{ 'btn-disabled-link': !canCreateInventoryProducts }"
              :aria-disabled="!canCreateInventoryProducts"
              :title="
                canCreateInventoryProducts ? '' : permissionMessages.create
              "
              @click.prevent="
                !canCreateInventoryProducts &&
                toast.error(permissionMessages.create)
              "
            >
              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
              >
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
              </svg>
              Create First Product
            </router-link>
          </div>

          <div v-else class="data-table">
            <div class="t-head">
              <div>Product</div>
              <div>Category</div>
              <div>SKU</div>
              <div>Price</div>
              <div>Stock</div>
              <div>Status</div>
              <div></div>
            </div>

            <div
              v-for="product in filteredProducts"
              :key="product.id"
              class="t-row"
            >
              <div
                class="td td-product"
                @click="
                  canViewInventoryProducts && openViewDetailsModal(product)
                "
              >
                <div class="p-thumb-wrap">
                  <div
                    class="p-thumb"
                    :style="
                      !product.primary_image?.image_url
                        ? { background: getRandomColor(product.id) }
                        : {}
                    "
                  >
                    <img
                      v-if="product.primary_image?.image_url"
                      :src="product.primary_image.image_url"
                      :alt="product.product_name"
                    />
                    <svg
                      v-else
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="#9ca3af"
                      stroke-width="1.5"
                    >
                      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                  </div>
                  <span v-if="isOnSale(product)" class="sale-dot">SALE</span>
                </div>
                <div class="p-meta">
                  <span class="p-name">{{ product.product_name }}</span>
                  <span class="p-season">{{
                    formatSeason(product.season)
                  }}</span>
                </div>
              </div>

              <div class="td">
                <span class="chip">{{ product.category || "—" }}</span>
              </div>
              <div class="td mono">{{ product.sku || "—" }}</div>

              <div class="td td-price">
                <span v-if="isOnSale(product)" class="price-sale"
                  >₱{{
                    parseFloat(product.discount_price || 0).toFixed(2)
                  }}</span
                >
                <span :class="isOnSale(product) ? 'price-struck' : 'price-main'"
                  >₱{{
                    parseFloat(product.selling_price || 0).toFixed(2)
                  }}</span
                >
                <span class="price-cost"
                  >cost ₱{{
                    parseFloat(product.purchase_price || 0).toFixed(2)
                  }}</span
                >
              </div>

              <div class="td td-stock">
                <span class="stock-num">{{ product.quantity_in_stock }}</span>
                <div class="stock-track">
                  <div
                    class="stock-fill"
                    :class="'sf-' + getStatusKey(product)"
                    :style="{ width: getStockBarWidth(product) + '%' }"
                  ></div>
                </div>
              </div>

              <div class="td">
                <span
                  class="status-badge"
                  :class="'sb-' + getStatusKey(product)"
                >
                  <span class="badge-dot"></span>{{ getStatusText(product) }}
                </span>
              </div>

              <div class="td td-action">
                <div class="menu-wrap">
                  <button
                    class="menu-btn"
                    :disabled="!canViewInventoryProducts"
                    :title="
                      canViewInventoryProducts
                        ? 'Open product actions'
                        : permissionMessages.view
                    "
                    @click.stop="toggleMenu(product.id, $event)"
                  >
                    <svg
                      width="15"
                      height="15"
                      viewBox="0 0 24 24"
                      fill="currentColor"
                    >
                      <circle cx="12" cy="5" r="2" />
                      <circle cx="12" cy="12" r="2" />
                      <circle cx="12" cy="19" r="2" />
                    </svg>
                  </button>
                  <transition name="pop">
                    <div
                      v-if="activeMenu === product.id"
                      :ref="setDropdownRef"
                      class="menu-dropdown"
                      :style="{
                        top: menuStyle.top,
                        left: menuStyle.left,
                        visibility: menuStyle.visibility,
                      }"
                    >
                      <button
                        class="menu-item"
                        @click="openViewDetailsModal(product)"
                      >
                        <svg
                          width="13"
                          height="13"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path
                            d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                          />
                          <circle cx="12" cy="12" r="3" />
                        </svg>
                        View Details
                      </button>
                      <button
                        class="menu-item"
                        :disabled="!canEditInventoryProducts"
                        :title="
                          canEditInventoryProducts
                            ? ''
                            : permissionMessages.edit
                        "
                        @click="openEditModal(product)"
                      >
                        <svg
                          width="13"
                          height="13"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path
                            d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
                          />
                          <path
                            d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                          />
                        </svg>
                        Edit Product Details
                      </button>
                      <button
                        v-if="activeTab === 'draft'"
                        class="menu-item"
                        :disabled="!canEditInventoryProducts"
                        :title="
                          canEditInventoryProducts
                            ? ''
                            : permissionMessages.edit
                        "
                        @click="openSubmitModal(product)"
                      >
                        <svg
                          width="13"
                          height="13"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <line x1="22" y1="2" x2="11" y2="13" />
                          <polygon points="22 2 15 22 11 13 2 9 22 2" />
                        </svg>
                        Submit for Approval
                      </button>
                      <button
                        v-if="activeTab === 'approved'"
                        class="menu-item"
                        :disabled="!canEditInventoryProducts"
                        :title="
                          canEditInventoryProducts
                            ? ''
                            : permissionMessages.edit
                        "
                        @click="openUpdateStockModal(product)"
                      >
                        <svg
                          width="13"
                          height="13"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <polyline points="1 4 1 10 7 10" />
                          <path d="M3.51 15a9 9 0 1 0 .49-3.5" />
                        </svg>
                        Update Stock
                      </button>
                      <div class="menu-sep"></div>
                      <button
                        class="menu-item danger"
                        :disabled="!canDeleteInventoryProducts"
                        :title="
                          canDeleteInventoryProducts
                            ? ''
                            : permissionMessages.delete
                        "
                        @click="openDeleteModal(product)"
                      >
                        <svg
                          width="13"
                          height="13"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <polyline points="3 6 5 6 21 6" />
                          <path
                            d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"
                          />
                        </svg>
                        Delete
                      </button>
                    </div>
                  </transition>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </main>

    <!-- ══ VIEW DETAILS MODAL ══ -->
    <transition name="mfade">
      <div
        v-if="showViewDetailsModal"
        class="modal-overlay"
        @click="closeViewDetailsModal"
      >
        <div class="modal-box modal-xl" @click.stop>
          <div class="modal-hd">
            <div class="mhd-left">
              <span class="mhd-ico">👁️</span>
              <div>
                <h2 class="mhd-title">Product Details</h2>
                <p class="mhd-sub">Full specifications and inventory</p>
              </div>
            </div>
            <button class="btn-close" @click="closeViewDetailsModal">✕</button>
          </div>
          <div class="modal-bd scroll" v-if="selectedProduct">
            <div v-if="isOnSale(selectedProduct)" class="sale-banner">
              <span>🏷️</span>
              <div>
                <strong>{{ calcDiscountPct(selectedProduct) }}% OFF</strong
                ><span> — This product is currently on sale!</span>
              </div>
              <span class="sale-banner-badge">SALE ACTIVE</span>
            </div>
            <div class="vd-grid">
              <div class="vd-card span2">
                <h3 class="vdc-title">📋 Basic Information</h3>
                <div class="ro-grid">
                  <div class="ro-g span2">
                    <label>Product Name</label>
                    <div class="ro-val">
                      {{ selectedProduct.product_name || "—" }}
                    </div>
                  </div>
                  <div class="ro-g span2">
                    <label>Description</label>
                    <div class="ro-val ro-tall">
                      {{ selectedProduct.description || "—" }}
                    </div>
                  </div>
                  <div class="ro-g">
                    <label>SKU</label>
                    <div class="ro-val mono">
                      {{ selectedProduct.sku || "—" }}
                    </div>
                  </div>
                  <div class="ro-g">
                    <label>Category</label>
                    <div class="ro-val">
                      {{ selectedProduct.category || "—" }}
                    </div>
                  </div>
                  <div class="ro-g">
                    <label>Color</label>
                    <div class="ro-val">{{ selectedProduct.color || "—" }}</div>
                  </div>
                  <div class="ro-g">
                    <label>Season</label>
                    <div class="ro-val">
                      {{ formatSeason(selectedProduct.season) }}
                    </div>
                  </div>
                </div>
              </div>
              <div class="vd-card">
                <h3 class="vdc-title">💰 Pricing</h3>
                <div class="ro-grid">
                  <div class="ro-g">
                    <label>Purchase Price</label>
                    <div class="ro-val">
                      ₱{{
                        parseFloat(selectedProduct.purchase_price || 0).toFixed(
                          2,
                        )
                      }}
                    </div>
                  </div>
                  <div class="ro-g">
                    <label>Selling Price</label>
                    <div class="ro-val val-green">
                      ₱{{
                        parseFloat(selectedProduct.selling_price || 0).toFixed(
                          2,
                        )
                      }}
                    </div>
                  </div>
                  <div class="ro-g">
                    <label>Profit</label>
                    <div class="ro-val val-green">
                      ₱{{ calcProfit(selectedProduct).toFixed(2) }}
                      <span class="pct-badge"
                        >({{ calcProfitPct(selectedProduct) }}%)</span
                      >
                    </div>
                  </div>
                  <div class="ro-g">
                    <label>Discount</label>
                    <div class="ro-val">
                      <span v-if="isOnSale(selectedProduct)" class="pill-on"
                        >✓ Active</span
                      ><span v-else class="pill-off">None</span>
                    </div>
                  </div>
                  <div v-if="isOnSale(selectedProduct)" class="ro-g span2">
                    <label>Discount Price</label>
                    <div class="ro-val val-red">
                      ₱{{
                        parseFloat(selectedProduct.discount_price || 0).toFixed(
                          2,
                        )
                      }}
                      <span class="pct-badge red"
                        >-{{ calcDiscountPct(selectedProduct) }}%</span
                      >
                    </div>
                  </div>
                </div>
              </div>
              <div class="vd-card">
                <h3 class="vdc-title">📦 Stock Management</h3>
                <div class="ro-grid">
                  <div class="ro-g">
                    <label>Current Stock</label>
                    <div class="ro-val">
                      <span class="big-num">{{
                        selectedProduct.quantity_in_stock
                      }}</span>
                      <span
                        class="status-badge"
                        :class="'sb-' + getStatusKey(selectedProduct)"
                        style="margin-left: 8px"
                        ><span class="badge-dot"></span
                        >{{ getStatusText(selectedProduct) }}</span
                      >
                    </div>
                  </div>
                  <div class="ro-g">
                    <label>Min Level</label>
                    <div class="ro-val">
                      {{ selectedProduct.min_stock_level || 0 }}
                    </div>
                  </div>
                  <div class="ro-g">
                    <label>Max Level</label>
                    <div class="ro-val">
                      {{ selectedProduct.max_stock_level || "—" }}
                    </div>
                  </div>
                  <div class="ro-g" v-if="selectedProduct.storage_location">
                    <label>Location</label>
                    <div class="ro-val">
                      {{ selectedProduct.storage_location }}
                    </div>
                  </div>
                  <div class="ro-g" v-if="selectedProduct.harvest_date">
                    <label>Harvest</label>
                    <div class="ro-val">
                      {{ formatDate(selectedProduct.harvest_date) }}
                    </div>
                  </div>
                  <div class="ro-g" v-if="selectedProduct.expiration_date">
                    <label>Expires</label>
                    <div class="ro-val">
                      {{ formatDate(selectedProduct.expiration_date) }}
                    </div>
                  </div>
                </div>
              </div>
              <div class="vd-card" v-if="selectedProduct.supplier_name">
                <h3 class="vdc-title">🚚 Supplier</h3>
                <div class="ro-grid">
                  <div class="ro-g">
                    <label>Name</label>
                    <div class="ro-val">
                      {{ selectedProduct.supplier_name }}
                    </div>
                  </div>
                  <div class="ro-g">
                    <label>Contact</label>
                    <div class="ro-val">
                      {{ selectedProduct.supplier_contact || "—" }}
                    </div>
                  </div>
                  <div class="ro-g" v-if="selectedProduct.supplier_lead_time">
                    <label>Lead Time</label>
                    <div class="ro-val">
                      {{ selectedProduct.supplier_lead_time }} days
                    </div>
                  </div>
                </div>
              </div>
              <div class="vd-card">
                <h3 class="vdc-title">🏷️ Attributes</h3>
                <div class="ro-grid">
                  <div class="ro-g">
                    <label>Handling</label>
                    <div class="ro-val attr-row">
                      <span
                        v-if="selectedProduct.is_fragile"
                        class="attr-tag amber"
                        >⚠️ Fragile</span
                      >
                      <span
                        v-if="selectedProduct.requires_refrigeration"
                        class="attr-tag blue"
                        >❄️ Cold</span
                      >
                      <span
                        v-if="
                          !selectedProduct.is_fragile &&
                          !selectedProduct.requires_refrigeration
                        "
                        class="muted-txt"
                        >None</span
                      >
                    </div>
                  </div>
                  <div class="ro-g">
                    <label>Shop Status</label>
                    <div class="ro-val">
                      <span
                        :class="
                          selectedProduct.status === 'active'
                            ? 'pill-on'
                            : 'pill-off'
                        "
                        >{{
                          selectedProduct.status === "active"
                            ? "✓ Visible"
                            : selectedProduct.status
                        }}</span
                      >
                    </div>
                  </div>
                </div>
                <div v-if="cleanedOccasionTags.length" style="margin-top: 12px">
                  <label class="ro-label-sm">Occasion Tags</label>
                  <div class="tags-row">
                    <span
                      v-for="t in cleanedOccasionTags"
                      :key="t"
                      class="occ-tag"
                      >{{ t }}</span
                    >
                  </div>
                </div>
              </div>
              <div class="vd-card" v-if="selectedProduct.images?.length">
                <h3 class="vdc-title">📷 Images</h3>
                <div class="img-strip">
                  <div
                    v-for="img in selectedProduct.images"
                    :key="img.id"
                    class="img-tw"
                  >
                    <img :src="img.image_url" />
                    <span v-if="img.is_primary" class="pri-badge">Primary</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-ft">
            <button class="btn-ghost-sm" @click="closeViewDetailsModal">
              Close
            </button>
            <button
              class="btn-primary"
              :disabled="!canEditInventoryProducts"
              :title="canEditInventoryProducts ? '' : permissionMessages.edit"
              @click="switchToEdit"
            >
              ✏️ Edit Product Details
            </button>
            <button
              v-if="activeTab === 'approved'"
              class="btn-ghost-sm"
              :disabled="!canEditInventoryProducts"
              :title="canEditInventoryProducts ? '' : permissionMessages.edit"
              @click="openUpdateStockModal(selectedProduct)"
            >
              Update Stock
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ══ EDIT PRODUCT DETAILS MODAL ══ -->
    <transition name="mfade">
      <div v-if="showEditModal" class="modal-overlay" @click="closeEditModal">
        <div class="modal-box modal-xl" @click.stop>
          <div class="modal-hd">
            <div class="mhd-left">
              <span class="mhd-ico">✏️</span>
              <div>
                <h2 class="mhd-title">Edit Product Details</h2>
                <p class="mhd-sub">{{ editFormData.product_name }}</p>
              </div>
            </div>
            <button class="btn-close" @click="closeEditModal">✕</button>
          </div>

          <div class="modal-bd scroll">
            <form @submit.prevent="submitEditProduct">
              <div class="vd-grid">
                <!-- ── Basic Information ── -->
                <div class="vd-card span2">
                  <h3 class="vdc-title">📋 Basic Information</h3>
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
                    <div
                      v-if="editFormData.color === 'other'"
                      class="form-group"
                    >
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
                <div class="vd-card">
                  <h3 class="vdc-title">💰 Pricing</h3>
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
                      <span
                        v-if="editErrors.purchase_price"
                        class="error-text"
                        >{{ editErrors.purchase_price }}</span
                      >
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
                      <span
                        v-if="editErrors.selling_price"
                        class="error-text"
                        >{{ editErrors.selling_price }}</span
                      >
                    </div>

                    <!-- Profit display -->
                    <div class="form-group span2">
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
                    <div class="form-group span2">
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
                            >Show regular & sale price</span
                          >
                        </div>
                        <span
                          v-if="editFormData.has_discount"
                          class="discount-active-pill"
                          >🏷️ Sale Active</span
                        >
                      </div>
                    </div>

                    <!-- Discount inputs -->
                    <div
                      v-if="editFormData.has_discount"
                      class="form-group span2"
                    >
                      <div class="form-grid-2">
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
                              :class="{
                                'is-invalid': editErrors.discount_price,
                              }"
                              @input="clearEditError('discount_price')"
                              placeholder="0.00"
                            />
                          </div>
                          <span
                            v-if="editErrors.discount_price"
                            class="error-text"
                            >{{ editErrors.discount_price }}</span
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
                    </div>
                  </div>
                </div>

                <!-- ── Stock ── -->
                <div class="vd-card">
                  <h3 class="vdc-title">📦 Stock Management</h3>
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
                </div>

                <!-- ── Supplier Information ── -->
                <div class="vd-card span2">
                  <h3 class="vdc-title">🏢 Supplier Information</h3>
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
                    <div class="form-group full-width">
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

                <!-- ── Additional Information ── -->
                <div class="vd-card span2">
                  <h3 class="vdc-title">ℹ️ Additional Information</h3>
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

                <!-- ── Product Images ── -->
                <div class="vd-card span2">
                  <h3 class="vdc-title">📷 Product Images</h3>
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
                        <div v-if="img.is_primary" class="pri-badge">
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
                        v-if="
                          existingImages.length + newProductImages.length < 5
                        "
                        class="image-upload-placeholder"
                        @click="triggerEditFileInput"
                        @dragover.prevent
                        @drop.prevent="handleEditDrop"
                      >
                        <span class="upload-icon">📷</span>
                        <span class="upload-text">Add Photo</span>
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
                      Up to 5 photos total. Removing existing images is
                      permanent.
                    </p>
                  </div>
                </div>
              </div>
            </form>
          </div>

          <div class="modal-ft">
            <button
              class="btn-ghost-sm"
              @click="closeEditModal"
              :disabled="isSubmitting"
            >
              Cancel
            </button>
            <button
              class="btn-primary"
              @click="submitEditProduct"
              :disabled="isSubmitting"
            >
              <span v-if="isSubmitting">Saving...</span>
              <span v-else>💾 Save Changes</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ══ UPDATE STOCK MODAL ══ -->
    <transition name="mfade">
      <div
        v-if="showUpdateStockModal"
        class="modal-overlay"
        @click="closeUpdateStockModal"
      >
        <div class="modal-box modal-sm" @click.stop>
          <div class="modal-hd">
            <div class="mhd-left">
              <span class="mhd-ico" style="color: #16a34a">🔄</span>
              <div>
                <h2 class="mhd-title">Update Stock</h2>
                <p class="mhd-sub">Adjust inventory quantity</p>
              </div>
            </div>
            <button class="btn-close" @click="closeUpdateStockModal">✕</button>
          </div>
          <div class="modal-bd" style="padding: 24px" v-if="selectedProduct">
            <p class="modal-desc">
              Updating stock for
              <strong>{{ selectedProduct.product_name }}</strong>
            </p>
            <div class="mf-grp">
              <label>Current Stock</label
              ><input
                type="number"
                class="mf-inp"
                :value="selectedProduct.quantity_in_stock"
                disabled
              />
            </div>
            <div class="mf-grp">
              <label>New Quantity <span style="color: #dc2626">*</span></label
              ><input
                type="number"
                class="mf-inp"
                v-model.number="newStockQuantity"
                placeholder="Enter new quantity"
                min="0"
                :disabled="!canEditInventoryProducts"
              />
            </div>
          </div>
          <div class="modal-ft">
            <button class="btn-ghost-sm" @click="closeUpdateStockModal">
              Cancel
            </button>
            <button
              class="btn-primary"
              @click="confirmUpdateStock"
              :disabled="
                !canEditInventoryProducts ||
                newStockQuantity === null ||
                newStockQuantity < 0
              "
            >
              Update Stock
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ══ SUBMIT MODAL ══ -->
    <transition name="mfade">
      <div
        v-if="showSubmitModal"
        class="modal-overlay"
        @click="closeSubmitModal"
      >
        <div class="modal-box modal-sm" @click.stop>
          <div class="modal-hd">
            <div class="mhd-left">
              <span class="mhd-ico">📤</span>
              <div>
                <h2 class="mhd-title">Submit for Approval</h2>
                <p class="mhd-sub">Send to admin for review</p>
              </div>
            </div>
            <button class="btn-close" @click="closeSubmitModal">✕</button>
          </div>
          <div class="modal-bd" style="padding: 24px" v-if="selectedProduct">
            <div class="confirm-ico">📤</div>
            <p class="modal-desc">
              Submit <strong>{{ selectedProduct.product_name }}</strong> for
              admin approval?
            </p>
            <p class="modal-note">
              Once submitted, you won't be able to edit until it's reviewed.
            </p>
          </div>
          <div class="modal-ft">
            <button class="btn-ghost-sm" @click="closeSubmitModal">
              Cancel
            </button>
            <button class="btn-primary" @click="confirmSubmitForApproval">
              Submit for Approval
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ══ DELETE MODAL ══ -->
    <transition name="mfade">
      <div
        v-if="showDeleteModal"
        class="modal-overlay"
        @click="closeDeleteModal"
      >
        <div class="modal-box modal-sm" @click.stop>
          <div class="modal-hd">
            <div class="mhd-left">
              <span class="mhd-ico">🗑️</span>
              <div>
                <h2 class="mhd-title">Delete Product</h2>
                <p class="mhd-sub">This action is permanent</p>
              </div>
            </div>
            <button class="btn-close" @click="closeDeleteModal">✕</button>
          </div>
          <div class="modal-bd" style="padding: 24px" v-if="selectedProduct">
            <div class="confirm-ico">🗑️</div>
            <p class="modal-desc">
              Delete <strong>{{ selectedProduct.product_name }}</strong
              >?
            </p>
            <p class="modal-warn">
              ⚠️ This cannot be undone. All product data will be permanently
              removed.
            </p>
          </div>
          <div class="modal-ft">
            <button class="btn-ghost-sm" @click="closeDeleteModal">
              Cancel
            </button>
            <button
              class="btn-danger"
              @click="confirmDeleteProduct"
              :disabled="!canDeleteInventoryProducts"
              :title="
                canDeleteInventoryProducts ? '' : permissionMessages.delete
              "
            >
              Delete Product
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import {
  ref,
  computed,
  onMounted,
  onUnmounted,
  nextTick,
  watch,
  reactive,
} from "vue";
import { useRouter } from "vue-router";
import api from "../../../../plugins/axios";
import { useAuth } from "../../../../composables/useAuth";
import {
  PERMISSION_TOOLTIPS,
  useAssignment,
} from "../../../../composables/useAssignment";
import { toast } from "vue3-toastify";
import { clearStoredAuth } from "../../../../utils/authSession";

const router = useRouter();
const { user } = useAuth();
const { can } = useAssignment();

const tableSearch = ref("");
const activeTab = ref("approved");
const products = ref([]);
const isLoading = ref(false);
const isLoadingMessage = ref("");
const activeMenu = ref(null);
const selectedProduct = ref(null);
const newStockQuantity = ref(null);

const dropdownRef = ref(null);
const setDropdownRef = (el) => {
  if (el) dropdownRef.value = el;
};
const activeTriggerEl = ref(null);
const menuStyle = reactive({
  top: "0px",
  left: "0px",
  visibility: "hidden",
});

const showViewDetailsModal = ref(false);
const showUpdateStockModal = ref(false);
const showSubmitModal = ref(false);
const showDeleteModal = ref(false);
const showEditModal = ref(false);
const isSubmitting = ref(false);
const editFileInput = ref(null);
const existingImages = ref([]);
const newProductImages = ref([]);
const removedImageIds = ref([]);

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

const canViewInventoryProducts = computed(() =>
  can("inventory_products", "view"),
);
const canCreateInventoryProducts = computed(() =>
  can("inventory_products", "create"),
);
const canEditInventoryProducts = computed(() =>
  can("inventory_products", "edit"),
);
const canDeleteInventoryProducts = computed(() =>
  can("inventory_products", "delete"),
);
const canCreateFunding = computed(() => can("inventory_funding", "create"));
const isReadOnlyInventoryProducts = computed(
  () => canViewInventoryProducts.value && !canEditInventoryProducts.value,
);
const permissionMessages = PERMISSION_TOOLTIPS;

const colorPalette = [
  "#dcfce7",
  "#dbeafe",
  "#fef9c3",
  "#fce7f3",
  "#fed7aa",
  "#e0e7ff",
];
const getRandomColor = (id) => colorPalette[id % colorPalette.length];

const fetchProducts = async () => {
  try {
    isLoading.value = true;
    isLoadingMessage.value = "Loading products…";
    const endpoint =
      activeTab.value === "draft"
        ? "/procurement/inventory/draft-products"
        : "/procurement/inventory/my-products";
    const res = await api.get(endpoint);
    if (res.data.success) products.value = res.data.data;
    else toast.error("Failed to load products");
  } catch (e) {
    if (e.response?.status === 401) {
      clearStoredAuth();
      router.push("/guest/login");
    } else toast.error("Failed to load products");
  } finally {
    isLoading.value = false;
  }
};

const isOnSale = (p) =>
  p.discount_price &&
  parseFloat(p.discount_price) < parseFloat(p.selling_price);
const calcDiscountPct = (p) => {
  if (!isOnSale(p)) return 0;
  return Math.round(
    ((parseFloat(p.selling_price) - parseFloat(p.discount_price)) /
      parseFloat(p.selling_price)) *
      100,
  );
};
const calcProfit = (p) =>
  parseFloat(p.selling_price || 0) - parseFloat(p.purchase_price || 0);
const calcProfitPct = (p) => {
  const pur = parseFloat(p.purchase_price || 0);
  return pur === 0 ? 0 : ((calcProfit(p) / pur) * 100).toFixed(1);
};

const switchTab = (tab) => {
  activeTab.value = tab;
  activeMenu.value = null;
  fetchProducts();
};
const getTabCount = (tab) =>
  tab === "approved"
    ? products.value.filter((p) => p.status !== "draft").length
    : products.value.filter((p) => p.status === "draft").length;
const goEdit = (id) => {
  if (!canEditInventoryProducts.value) {
    toast.error("You do not have permission to edit inventory products.");
    activeMenu.value = null;
    return;
  }

  activeMenu.value = null;
  router.push({
    path: "/erp/procurement/inventory/add-product",
    query: { edit: id },
  });
};

const totalAssetValue = computed(() =>
  products.value.reduce(
    (s, p) =>
      s + parseFloat(p.selling_price || 0) * parseInt(p.quantity_in_stock || 0),
    0,
  ),
);
const productCount = computed(() => products.value.length);
const inStock = computed(
  () =>
    products.value.filter(
      (p) =>
        parseInt(p.quantity_in_stock || 0) > parseInt(p.min_stock_level || 0) &&
        parseInt(p.quantity_in_stock || 0) > 0,
    ).length,
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
const filteredProducts = computed(() => {
  if (!tableSearch.value) return products.value;
  const q = tableSearch.value.toLowerCase();
  return products.value.filter(
    (p) =>
      p.product_name?.toLowerCase().includes(q) ||
      p.sku?.toLowerCase().includes(q) ||
      p.category?.toLowerCase().includes(q),
  );
});

const getStatusKey = (p) => {
  const s = parseInt(p.quantity_in_stock || 0),
    m = parseInt(p.min_stock_level || 0);
  if (s === 0) return "out";
  if (s <= m) return "low";
  return "in";
};
const getStatusText = (p) =>
  ({ in: "In Stock", low: "Low Stock", out: "Out of Stock" })[getStatusKey(p)];
const getStockBarWidth = (p) =>
  Math.min(
    100,
    (parseInt(p.quantity_in_stock || 0) / parseInt(p.max_stock_level || 100)) *
      100,
  );
const formatDate = (d) =>
  d
    ? new Date(d).toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
      })
    : "—";
const formatSeason = (s) =>
  ({
    "all-year": "All Year",
    spring: "Spring",
    summer: "Summer",
    autumn: "Autumn",
    winter: "Winter",
  })[s] || s;

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
  const j = (Array.isArray(raw) ? raw.join(" ") : raw)
    .replace(/[\[\]\\"]/g, "")
    .trim();
  return knownTags.filter((t) => new RegExp(`\\b${t}\\b`, "i").test(j));
});

const toggleMenu = (id, event) => {
  if (!canEditInventoryProducts.value) {
    return;
  }

  if (activeMenu.value === id) {
    activeMenu.value = null;
    return;
  }

  activeTriggerEl.value = event.currentTarget;
  menuStyle.visibility = "hidden";
  activeMenu.value = id;

  nextTick(() => {
    positionMenu();
  });
};

const positionMenu = () => {
  const btn = activeTriggerEl.value;
  const menu = dropdownRef.value;
  if (!btn || !menu) return;

  const margin = 8;
  const gap = 6;
  const btnRect = btn.getBoundingClientRect();
  const menuRect = menu.getBoundingClientRect();
  const vw = window.innerWidth;
  const vh = window.innerHeight;

  let left = btnRect.right - menuRect.width;
  if (left < margin) {
    left = btnRect.left;
  }
  if (left + menuRect.width > vw - margin) {
    left = vw - menuRect.width - margin;
  }
  if (left < margin) left = margin;

  let top = btnRect.bottom + gap;
  const spaceBelow = vh - btnRect.bottom;
  const spaceAbove = btnRect.top;
  if (spaceBelow < menuRect.height + margin && spaceAbove > spaceBelow) {
    top = btnRect.top - menuRect.height - gap;
  }
  if (top < margin) top = margin;

  menuStyle.top = `${top}px`;
  menuStyle.left = `${left}px`;
  menuStyle.visibility = "visible";
};

const closeMenuOnScrollOrResize = () => {
  if (activeMenu.value !== null) {
    activeMenu.value = null;
  }
};

const openViewDetailsModal = (p) => {
  selectedProduct.value = p;
  showViewDetailsModal.value = true;
  activeMenu.value = null;
};
const closeViewDetailsModal = () => {
  showViewDetailsModal.value = false;
  selectedProduct.value = null;
};
const openUpdateStockModal = (p) => {
  if (!canEditInventoryProducts.value) {
    toast.error("You do not have permission to update stock.");
    activeMenu.value = null;
    return;
  }

  selectedProduct.value = p;
  newStockQuantity.value = p.quantity_in_stock;
  showUpdateStockModal.value = true;
  activeMenu.value = null;
  showViewDetailsModal.value = false;
};
const closeUpdateStockModal = () => {
  showUpdateStockModal.value = false;
  selectedProduct.value = null;
  newStockQuantity.value = null;
};
const openSubmitModal = (p) => {
  if (!canEditInventoryProducts.value) {
    toast.error("You do not have permission to submit products for approval.");
    activeMenu.value = null;
    return;
  }

  selectedProduct.value = p;
  showSubmitModal.value = true;
  activeMenu.value = null;
};
const closeSubmitModal = () => {
  showSubmitModal.value = false;
  selectedProduct.value = null;
};
const occasionTags = knownTags;

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

const openEditModal = (p) => {
  if (!canEditInventoryProducts.value) {
    toast.error("You do not have permission to edit inventory products.");
    activeMenu.value = null;
    return;
  }
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

  existingImages.value = (p.images || []).map((img) => ({ ...img }));
  newProductImages.value = [];
  removedImageIds.value = [];
};
const onEditDiscountToggle = () => {
  if (!editFormData.has_discount) {
    editFormData.discount_price = null;
    delete editErrors.discount_price;
  }
};
const isEditTagDisabled = (tag) =>
  editFormData.occasion_tags.length >= 2 &&
  !editFormData.occasion_tags.includes(tag);
const onEditTagChange = () => {
  if (editFormData.occasion_tags.length > 2)
    editFormData.occasion_tags = editFormData.occasion_tags.slice(0, 2);
};
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
const submitEditProduct = async () => {
  if (isSubmitting.value) return;
  if (!canEditInventoryProducts.value) {
    toast.error("You do not have permission to edit inventory products.");
    return;
  }
  if (!validateEditForm()) return;

  isSubmitting.value = true;
  try {
    const fd = new FormData();
    fd.append("_method", "PUT");

    const booleans = ["is_fragile", "requires_refrigeration", "has_discount"];
    booleans.forEach((key) => {
      fd.append(key, editFormData[key] ? "1" : "0");
    });

    Object.entries(editFormData).forEach(([key, value]) => {
      if ([...booleans, "occasion_tags"].includes(key)) return;
      if (value === null || value === undefined) {
        fd.append(key, "");
      } else {
        fd.append(key, value.toString());
      }
    });

    if (Array.isArray(editFormData.occasion_tags)) {
      editFormData.occasion_tags.forEach((tag) => {
        fd.append("occasion_tags[]", tag);
      });
    }

    newProductImages.value.forEach((img) => {
      if (img.file) fd.append("images[]", img.file);
    });

    removedImageIds.value.forEach((id) => {
      fd.append("removed_image_ids[]", id);
    });

    const res = await api.post(
      `/procurement/inventory/products/${selectedProduct.value.id}`,
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
      Object.keys(editErrors).forEach((k) => delete editErrors[k]);
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

const openDeleteModal = (p) => {
  if (!canDeleteInventoryProducts.value) {
    toast.error(permissionMessages.delete);
    activeMenu.value = null;
    return;
  }

  selectedProduct.value = p;
  showDeleteModal.value = true;
  activeMenu.value = null;
};
const closeDeleteModal = () => {
  showDeleteModal.value = false;
  selectedProduct.value = null;
};

const confirmUpdateStock = async () => {
  if (!canEditInventoryProducts.value) {
    toast.error("You do not have permission to update stock.");
    return;
  }

  if (newStockQuantity.value === null || newStockQuantity.value < 0) {
    toast.error("Enter a valid quantity");
    return;
  }
  try {
    const r = await api.patch(
      `/procurement/inventory/products/${selectedProduct.value.id}/stock`,
      { quantity_in_stock: newStockQuantity.value },
    );
    if (r.data.success) {
      closeUpdateStockModal();
      fetchProducts();
      toast.success("Stock updated!");
    } else toast.error("Failed to update stock");
  } catch {
    toast.error("Failed to update stock");
  }
};
const confirmSubmitForApproval = async () => {
  if (!canEditInventoryProducts.value) {
    toast.error("You do not have permission to submit products for approval.");
    return;
  }

  try {
    const r = await api.post(
      `/vendor/products/${selectedProduct.value.id}/submit-approval`,
    );
    if (r.data.success) {
      closeSubmitModal();
      fetchProducts();
      toast.success("Submitted for approval!");
    } else toast.error("Failed to submit");
  } catch {
    toast.error("Failed to submit for approval");
  }
};
const confirmDeleteProduct = async () => {
  if (!canDeleteInventoryProducts.value) {
    toast.error(permissionMessages.delete);
    return;
  }

  try {
    const r = await api.delete(
      `/procurement/inventory/products/${selectedProduct.value.id}`,
    );
    if (r.data.success) {
      closeDeleteModal();
      fetchProducts();
      toast.success("Product deleted");
    } else toast.error("Failed to delete");
  } catch {
    toast.error("Failed to delete product");
  }
};

onMounted(() => {
  fetchProducts();
  document.addEventListener("click", (e) => {
    if (!e.target.closest(".menu-wrap")) activeMenu.value = null;
  });
  window.addEventListener("scroll", closeMenuOnScrollOrResize, true);
  window.addEventListener("resize", closeMenuOnScrollOrResize);
});

onUnmounted(() => {
  window.removeEventListener("scroll", closeMenuOnScrollOrResize, true);
  window.removeEventListener("resize", closeMenuOnScrollOrResize);
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
}
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .stat-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
