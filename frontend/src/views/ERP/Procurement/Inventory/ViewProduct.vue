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
            :class="{ 'btn-disabled-link': !canEditInventoryProducts }"
            :aria-disabled="!canEditInventoryProducts"
            @click.prevent="
              !canEditInventoryProducts &&
              toast.error('You do not have permission to create funding requests.')
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
            :class="{ 'btn-disabled-link': !canEditInventoryProducts }"
            :aria-disabled="!canEditInventoryProducts"
            @click.prevent="
              !canEditInventoryProducts &&
              toast.error('You do not have permission to create inventory products.')
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
              :class="{ 'btn-disabled-link': !canEditInventoryProducts }"
              :aria-disabled="!canEditInventoryProducts"
              @click.prevent="
                !canEditInventoryProducts &&
                toast.error('You do not have permission to create inventory products.')
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
              <div class="td td-product">
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
                    :disabled="!canEditInventoryProducts"
                    :title="
                      canEditInventoryProducts
                        ? 'Open product actions'
                        : 'You do not have permission to edit inventory products.'
                    "
                    @click.stop="toggleMenu(product.id)"
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
                    <div v-if="activeMenu === product.id" class="menu-dropdown">
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
                        v-if="activeTab !== 'approved'"
                        class="menu-item"
                        :disabled="!canEditInventoryProducts"
                        @click="goEdit(product.id)"
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
                        Edit
                      </button>
                      <button
                        v-if="activeTab === 'draft'"
                        class="menu-item"
                        :disabled="!canEditInventoryProducts"
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
                        :disabled="!canEditInventoryProducts"
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
              v-if="activeTab === 'approved'"
              class="btn-primary"
              :disabled="!canEditInventoryProducts"
              @click="openUpdateStockModal(selectedProduct)"
            >
              Update Stock
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
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import api from "../../../../plugins/axios";
import { useAuth } from "../../../../composables/useAuth";
import { useAssignment } from "../../../../composables/useAssignment";
import { toast } from "vue3-toastify";
import { clearStoredAuth } from "../../../../utils/authSession";

const router = useRouter();
const { user } = useAuth();
const { canEdit, isReadOnly } = useAssignment();

const tableSearch = ref("");
const activeTab = ref("approved");
const products = ref([]);
const isLoading = ref(false);
const isLoadingMessage = ref("");
const activeMenu = ref(null);
const selectedProduct = ref(null);
const newStockQuantity = ref(null);

const showViewDetailsModal = ref(false);
const showUpdateStockModal = ref(false);
const showSubmitModal = ref(false);
const showDeleteModal = ref(false);
const canEditInventoryProducts = computed(() => canEdit("inventory_products"));
const isReadOnlyInventoryProducts = computed(() =>
  isReadOnly("inventory_products"),
);

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

const toggleMenu = (id) => {
  if (!canEditInventoryProducts.value) {
    return;
  }

  activeMenu.value = activeMenu.value === id ? null : id;
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
const openDeleteModal = (p) => {
  if (!canEditInventoryProducts.value) {
    toast.error("You do not have permission to delete inventory products.");
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
  if (!canEditInventoryProducts.value) {
    toast.error("You do not have permission to delete inventory products.");
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
  position: absolute;
  right: 0;
  top: calc(100% + 4px);
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
