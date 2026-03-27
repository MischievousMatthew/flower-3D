<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <!-- Header -->
      <header class="content-header">
        <h1 class="page-title">Product Management</h1>

        <div class="header-actions">
          <div class="search-box">
            <input
              type="text"
              placeholder="Search products..."
              v-model="searchQuery"
              @input="onSearch"
            />
            <span class="search-icon">🔍</span>
          </div>
          <button class="icon-btn">🔔</button>
          <button class="icon-btn">⚙️</button>
          <div class="user-profile">
            <img
              src="https://i.pravatar.cc/40?img=5"
              alt="Admin"
              class="profile-img"
            />
            <div class="profile-info">
              <span class="profile-name">Admin User</span>
              <span class="profile-email">admin@bloomcraft.com</span>
            </div>
          </div>
        </div>
      </header>

      <!-- Tabs -->
      <div class="tabs">
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'active' }"
          @click="setTab('active')"
        >
          Active Products ({{ stats.active_products || 0 }})
        </button>
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'draft' }"
          @click="setTab('draft')"
        >
          Draft ({{ stats.draft_products || 0 }})
        </button>
        <button
          class="tab-btn"
          :class="{ active: activeTab === 'inactive' }"
          @click="setTab('inactive')"
        >
          Inactive ({{ stats.inactive_products || 0 }})
        </button>
      </div>

      <!-- Content Body -->
      <div class="content-body">
        <div class="content-actions">
          <h2 class="section-title">Product Management</h2>
          <div class="action-buttons">
            <button class="btn-filter" @click="toggleFilters">
              <span>⚡</span>
              <span>Filter</span>
            </button>
            <button class="btn-export" @click="exportData">
              <span>📥</span>
              <span>Export</span>
            </button>
          </div>
        </div>

        <!-- Filters Panel -->
        <div v-if="showFilters" class="filters-panel">
          <div class="filter-group">
            <label>Category:</label>
            <select v-model="filters.category" @change="fetchProducts">
              <option value="">All Categories</option>
              <option value="roses">Roses</option>
              <option value="tulips">Tulips</option>
              <option value="lilies">Lilies</option>
              <option value="orchids">Orchids</option>
              <option value="sunflowers">Sunflowers</option>
              <option value="mixed-bouquets">Mixed Bouquets</option>
            </select>
          </div>
          <div class="filter-group">
            <label>Flower Type:</label>
            <select v-model="filters.flower_type" @change="fetchProducts">
              <option value="">All Types</option>
              <option value="focal">Focal Flowers</option>
              <option value="secondary">Secondary Flowers</option>
              <option value="filler">Filler Flowers</option>
              <option value="line">Line Flowers</option>
              <option value="greenery">Greenery</option>
            </select>
          </div>
          <div class="filter-group">
            <label>Price Range:</label>
            <input
              type="number"
              v-model="filters.min_price"
              placeholder="Min"
              @change="fetchProducts"
            />
            <span>to</span>
            <input
              type="number"
              v-model="filters.max_price"
              placeholder="Max"
              @change="fetchProducts"
            />
          </div>
          <div class="filter-group">
            <button class="btn-clear-filters" @click="clearFilters">
              Clear Filters
            </button>
          </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #92400e">
              📦
            </div>
            <div class="stat-info">
              <h3>{{ stats.total_products || 0 }}</h3>
              <p>Total Products</p>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background: #d1fae5; color: #065f46">
              ✓
            </div>
            <div class="stat-info">
              <h3>{{ stats.active_products || 0 }}</h3>
              <p>Active Products</p>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background: #fee2e2; color: #991b1b">
              ⏸️
            </div>
            <div class="stat-info">
              <h3>{{ stats.inactive_products || 0 }}</h3>
              <p>Inactive Products</p>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background: #fecaca; color: #991b1b">
              ⚠️
            </div>
            <div class="stat-info">
              <h3>{{ stats.low_stock || 0 }}</h3>
              <p>Low Stock Items</p>
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
          <div class="loading-spinner"></div>
          <p>Loading products...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="products.length === 0" class="empty-state">
          <p>No products found.</p>
        </div>

        <!-- Products Grid -->
        <div v-else class="products-grid">
          <div
            v-for="product in products"
            :key="product.id"
            class="product-card"
            @click="viewDetails(product)"
          >
            <div class="product-image">
              <img
                :src="
                  product.images?.[0]?.image_url ||
                  'https://via.placeholder.com/300'
                "
                :alt="product.product_name"
              />
              <span class="status-badge" :class="`status-${product.status}`">
                {{ formatStatus(product.status) }}
              </span>
              <div v-if="product.model" class="model-badge">🎨 3D Model</div>
            </div>
            <div class="product-info">
              <div class="product-header">
                <span class="product-category">{{ product.category }}</span>
                <span class="product-sku">{{ product.sku }}</span>
              </div>
              <h3 class="product-name">{{ product.product_name }}</h3>
              <p class="product-vendor">
                by {{ product.owner?.store_name || product.owner?.name }}
              </p>

              <div class="product-details">
                <div class="detail-item">
                  <span class="detail-label">Price:</span>
                  <span class="detail-value">{{
                    formatCurrency(product.selling_price)
                  }}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Stock:</span>
                  <span
                    class="detail-value"
                    :class="{ 'low-stock': product.is_low_stock }"
                  >
                    {{ product.quantity_in_stock }}
                  </span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Type:</span>
                  <span class="detail-value">{{
                    formatFlowerType(product.flower_type)
                  }}</span>
                </div>
              </div>

              <div class="product-actions">
                <button
                  class="btn-action btn-toggle"
                  :class="{
                    'btn-activate': product.status === 'inactive',
                    'btn-deactivate': product.status === 'active',
                  }"
                  @click.stop="toggleProductStatus(product.id)"
                >
                  {{
                    product.status === "active"
                      ? "⏸️ Deactivate"
                      : "▶️ Activate"
                  }}
                </button>
                <button
                  class="btn-action btn-view"
                  @click.stop="viewDetails(product)"
                >
                  👁️ View
                </button>
                <button
                  class="btn-action btn-delete"
                  @click.stop="deleteProduct(product.id)"
                >
                  🗑️ Delete
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div
          v-if="products.length > 0 && pagination.last_page > 1"
          class="pagination"
        >
          <button
            class="page-btn"
            :disabled="pagination.current_page === 1"
            @click="changePage(pagination.current_page - 1)"
          >
            Prev
          </button>

          <button
            v-for="page in visiblePages"
            :key="page"
            class="page-btn"
            :class="{ active: pagination.current_page === page }"
            @click="changePage(page)"
          >
            {{ page }}
          </button>

          <button
            class="page-btn"
            :disabled="pagination.current_page === pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </main>

    <!-- Product Detail Modal -->
    <div v-if="showDetailModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content product-detail-modal" @click.stop>
        <div class="modal-header">
          <h2>Product Details</h2>
          <button class="btn-close" @click="closeModal">✕</button>
        </div>

        <div class="modal-body" v-if="selectedProduct">
          <div class="modal-grid">
            <!-- Left: Images -->
            <div class="modal-images">
              <div class="main-image">
                <img
                  :src="
                    selectedProduct.images?.[currentImageIndex]?.image_url ||
                    'https://via.placeholder.com/500'
                  "
                  :alt="selectedProduct.product_name"
                />
              </div>
              <div
                v-if="selectedProduct.images?.length > 1"
                class="thumbnail-grid"
              >
                <div
                  v-for="(image, index) in selectedProduct.images"
                  :key="image.id"
                  class="thumbnail"
                  :class="{ active: index === currentImageIndex }"
                  @click="currentImageIndex = index"
                >
                  <img :src="image.image_url" :alt="`Image ${index + 1}`" />
                </div>
              </div>
            </div>

            <!-- Right: Details -->
            <div class="modal-details">
              <!-- Basic Info -->
              <div class="detail-section">
                <h3>{{ selectedProduct.product_name }}</h3>
                <div class="meta-info">
                  <span class="badge-category">{{
                    selectedProduct.category
                  }}</span>
                  <span class="badge-sku">SKU: {{ selectedProduct.sku }}</span>
                  <span
                    class="badge-status"
                    :class="`status-${selectedProduct.status}`"
                  >
                    {{ formatStatus(selectedProduct.status) }}
                  </span>
                  <span class="badge-flower-type">
                    {{ formatFlowerType(selectedProduct.flower_type) }}
                  </span>
                </div>
                <p class="product-description">
                  {{ selectedProduct.description }}
                </p>
              </div>

              <!-- Vendor Info -->
              <div class="detail-section">
                <h4>Vendor Information</h4>
                <div class="info-grid">
                  <div class="info-item">
                    <label>Store Name:</label>
                    <span>{{
                      selectedProduct.owner?.store_name || "N/A"
                    }}</span>
                  </div>
                  <div class="info-item">
                    <label>Owner:</label>
                    <span>{{ selectedProduct.owner?.name || "N/A" }}</span>
                  </div>
                </div>
              </div>

              <!-- Pricing -->
              <div class="detail-section">
                <h4>Pricing Information</h4>
                <div class="info-grid">
                  <div class="info-item">
                    <label>Purchase Price:</label>
                    <span>{{
                      formatCurrency(selectedProduct.purchase_price)
                    }}</span>
                  </div>
                  <div class="info-item">
                    <label>Selling Price:</label>
                    <span class="highlight-price">{{
                      formatCurrency(selectedProduct.selling_price)
                    }}</span>
                  </div>
                  <div
                    class="info-item"
                    v-if="
                      selectedProduct.has_discount &&
                      selectedProduct.discount_price
                    "
                  >
                    <label>Discount Price:</label>
                    <span class="discount-price">{{
                      formatCurrency(selectedProduct.discount_price)
                    }}</span>
                  </div>
                  <div class="info-item">
                    <label>Profit Margin:</label>
                    <span class="profit-margin">
                      {{ formatCurrency(selectedProduct.profit_amount) }}
                      ({{ selectedProduct.profit_percentage?.toFixed(1) }}%)
                    </span>
                  </div>
                </div>
              </div>

              <!-- Stock -->
              <div class="detail-section">
                <h4>Stock Management</h4>
                <div class="info-grid">
                  <div class="info-item">
                    <label>Current Stock:</label>
                    <span
                      :class="{ 'low-stock': selectedProduct.is_low_stock }"
                    >
                      {{ selectedProduct.quantity_in_stock }}
                    </span>
                  </div>
                  <div class="info-item">
                    <label>Min Level:</label>
                    <span>{{ selectedProduct.min_stock_level }}</span>
                  </div>
                  <div class="info-item" v-if="selectedProduct.max_stock_level">
                    <label>Max Level:</label>
                    <span>{{ selectedProduct.max_stock_level }}</span>
                  </div>
                  <div
                    class="info-item"
                    v-if="selectedProduct.storage_location"
                  >
                    <label>Location:</label>
                    <span>{{ selectedProduct.storage_location }}</span>
                  </div>
                </div>
              </div>

              <!-- Product Details -->
              <div class="detail-section">
                <h4>Product Specifications</h4>
                <div class="info-grid">
                  <div class="info-item">
                    <label>Color:</label>
                    <span>{{
                      selectedProduct.color === "other"
                        ? selectedProduct.color_other
                        : selectedProduct.color
                    }}</span>
                  </div>
                  <div class="info-item">
                    <label>Flower Type:</label>
                    <span>{{
                      formatFlowerType(selectedProduct.flower_type)
                    }}</span>
                  </div>
                  <div class="info-item">
                    <label>Selling Type:</label>
                    <span>{{
                      formatSellingType(selectedProduct.selling_type)
                    }}</span>
                  </div>
                  <div class="info-item" v-if="selectedProduct.freshness_days">
                    <label>Freshness:</label>
                    <span>{{ selectedProduct.freshness_days }} days</span>
                  </div>
                </div>
              </div>

              <!-- 3D Model -->
              <div class="detail-section" v-if="selectedProduct.model">
                <h4>3D Model</h4>
                <div class="model-info-box">
                  <span class="model-icon">🎨</span>
                  <div>
                    <p class="model-file-name">
                      {{ selectedProduct.model.model_type.toUpperCase() }} Model
                    </p>
                    <p class="model-file-size">
                      Size:
                      {{ formatFileSize(selectedProduct.model.file_size) }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Care Instructions -->
              <div
                class="detail-section"
                v-if="selectedProduct.care_instructions"
              >
                <h4>Care Instructions</h4>
                <p>{{ selectedProduct.care_instructions }}</p>
              </div>

              <!-- Occasion Tags -->
              <div
                class="detail-section"
                v-if="
                  selectedProduct.occasion_tags &&
                  selectedProduct.occasion_tags.length > 0
                "
              >
                <h4>Occasion Tags</h4>
                <div class="occasion-tags">
                  <span
                    v-for="tag in selectedProduct.occasion_tags"
                    :key="tag"
                    class="occasion-tag"
                  >
                    {{ tag }}
                  </span>
                </div>
              </div>

              <!-- Supplier Info -->
              <div class="detail-section" v-if="selectedProduct.supplier_name">
                <h4>Supplier Information</h4>
                <div class="info-grid">
                  <div class="info-item">
                    <label>Supplier:</label>
                    <span>{{ selectedProduct.supplier_name }}</span>
                  </div>
                  <div
                    class="info-item"
                    v-if="selectedProduct.supplier_contact"
                  >
                    <label>Contact:</label>
                    <span>{{ selectedProduct.supplier_contact }}</span>
                  </div>
                  <div
                    class="info-item"
                    v-if="selectedProduct.supplier_lead_time"
                  >
                    <label>Lead Time:</label>
                    <span>{{ selectedProduct.supplier_lead_time }} days</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
          <button
            class="btn-action-large btn-delete-large"
            @click="deleteProduct(selectedProduct.id, true)"
          >
            Delete Product
          </button>
          <button
            class="btn-action-large"
            :class="
              selectedProduct.status === 'active'
                ? 'btn-deactivate-large'
                : 'btn-activate-large'
            "
            @click="toggleProductStatus(selectedProduct.id, true)"
          >
            {{
              selectedProduct.status === "active"
                ? "Deactivate Product"
                : "Activate Product"
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import api from "../../plugins/axios";

// const API_BASE_URL = "http://localhost:8000/api";

// State
const searchQuery = ref("");
const activeTab = ref("active");
const showDetailModal = ref(false);
const showFilters = ref(false);
const loading = ref(false);
const selectedProduct = ref(null);
const currentImageIndex = ref(0);
const products = ref([]);

const stats = ref({
  total_products: 0,
  active_products: 0,
  draft_products: 0,
  inactive_products: 0,
  low_stock: 0,
});

const filters = ref({
  category: "",
  flower_type: "",
  min_price: "",
  max_price: "",
});

const pagination = ref({
  current_page: 1,
  last_page: 1,
  from: 0,
  to: 0,
  total: 0,
  per_page: 15,
});

// Computed
const visiblePages = computed(() => {
  const pages = [];
  const totalPages = pagination.value.last_page;
  const current = pagination.value.current_page;

  if (totalPages <= 7) {
    for (let i = 1; i <= totalPages; i++) {
      pages.push(i);
    }
  } else {
    if (current <= 4) {
      for (let i = 1; i <= 5; i++) pages.push(i);
    } else if (current >= totalPages - 3) {
      for (let i = totalPages - 4; i <= totalPages; i++) pages.push(i);
    } else {
      for (let i = current - 2; i <= current + 2; i++) pages.push(i);
    }
  }

  return pages;
});

// Lifecycle
onMounted(() => {
  fetchProducts();
  fetchStatistics();
});

// Watchers
watch(activeTab, () => {
  pagination.value.current_page = 1;
  fetchProducts();
});

// Methods
const getAuthToken = () => {
  return (
    localStorage.getItem("auth_token") || sessionStorage.getItem("auth_token")
  );
};

const fetchProducts = async () => {
  loading.value = true;
  try {
    const token = getAuthToken();
    const response = await api.get('/admin/products', {
      params: {
        status: activeTab.value,
        per_page: pagination.value.per_page,
        page: pagination.value.current_page,
        ...filters.value,
        ...(searchQuery.value && { search: searchQuery.value })
      }
    });

    const data = response.data;

    if (data.success) {
      products.value = data.data.data || data.data;
      if (data.data.meta) {
        pagination.value = data.data.meta;
      }
    } else {
      products.value = [];
    }
  } catch (error) {
    console.error("Error fetching products:", error);
    products.value = [];
  } finally {
    loading.value = false;
  }
};

const fetchStatistics = async () => {
  try {
    const token = getAuthToken();
    const response = await api.get('/admin/products/statistics');
    const data = response.data;
    if (data.success) {
      stats.value = data.data;
    }
  } catch (error) {
    console.error("Error fetching statistics:", error);
  }
};

const setTab = (tab) => {
  activeTab.value = tab;
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    pagination.value.current_page = page;
    fetchProducts();
  }
};

const toggleFilters = () => {
  showFilters.value = !showFilters.value;
};

const clearFilters = () => {
  filters.value = {
    category: "",
    flower_type: "",
    min_price: "",
    max_price: "",
  };
  fetchProducts();
};

const onSearch = debounce(() => {
  pagination.value.current_page = 1;
  fetchProducts();
}, 500);

const exportData = () => {
  toast.info("Export functionality - Coming soon!");
};

const viewDetails = (product) => {
  selectedProduct.value = product;
  currentImageIndex.value = 0;
  showDetailModal.value = true;
  document.body.style.overflow = "hidden";
};

const closeModal = () => {
  showDetailModal.value = false;
  selectedProduct.value = null;
  document.body.style.overflow = "auto";
};

const toggleProductStatus = async (productId, fromModal = false) => {
  if (!confirm("Are you sure you want to change this product's status?"))
    return;

  try {
    const token = getAuthToken();
    const response = await api.post(`/admin/products/${productId}/toggle-status`);
    const data = response.data;

    if (data.success) {
      toast.success(data.message);
      fetchProducts();
      fetchStatistics();
      if (fromModal) {
        selectedProduct.value = data.data;
      }
    } else {
      toast.error(
        "Failed to update product status: " + (data.message || "Unknown error"),
      );
    }
  } catch (error) {
    console.error("Error toggling product status:", error);
    toast.error("Failed to update product status");
  }
};

const deleteProduct = async (productId, fromModal = false) => {
  if (
    !confirm(
      "Are you sure you want to delete this product? This action cannot be undone.",
    )
  )
    return;

  try {
    const token = getAuthToken();
    const response = await fetch(
      `${API_BASE_URL}/admin/products/${productId}`,
      {
        method: "DELETE",
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
        },
      },
    );

    const data = await response.json();

    if (data.success) {
      toast.success("Product deleted successfully");
      fetchProducts();
      fetchStatistics();
      if (fromModal) {
        closeModal();
      }
    } else {
      toast.error(
        "Failed to delete product: " + (data.message || "Unknown error"),
      );
    }
  } catch (error) {
    console.error("Error deleting product:", error);
    toast.error("Failed to delete product");
  }
};

// Formatting functions
const formatStatus = (status) => {
  const statuses = {
    active: "Active",
    draft: "Draft",
    inactive: "Inactive",
  };
  return statuses[status] || status;
};

const formatFlowerType = (type) => {
  const types = {
    focal: "Focal",
    secondary: "Secondary",
    filler: "Filler",
    line: "Line",
    greenery: "Greenery",
  };
  return types[type] || type;
};

const formatSellingType = (type) => {
  const types = {
    per_piece: "Per Piece",
    per_piece_customizable: "Per Piece (Customizable)",
    bouquet: "Bouquet",
  };
  return types[type] || type;
};

const formatCurrency = (amount) => {
  return `₱${parseFloat(amount || 0).toFixed(2)}`;
};

const formatFileSize = (bytes) => {
  if (!bytes) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
};

// Utility function
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}
</script>
<style scoped>
* {
  font-family:
    "Poppins",
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    sans-serif;
  box-sizing: border-box;
}

.admin-layout {
  display: flex;
  min-height: 100vh;
  background: #f5f7fa;
}

.main-content {
  margin-left: 260px;
  flex: 1;
  padding: 24px;
  transition: margin-left 0.3s ease;
}

/* Header */
.content-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.page-title {
  font-size: 28px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}

.search-box {
  position: relative;
}

.search-box input {
  width: 280px;
  padding: 10px 40px 10px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.3s;
}

.search-box input:focus {
  outline: none;
  border-color: #48bb78;
}

.search-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 16px;
  color: #a0aec0;
}

.icon-btn {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: white;
  cursor: pointer;
  font-size: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.icon-btn:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-left: 16px;
  border-left: 1px solid #e2e8f0;
}

.profile-img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
}

.profile-info {
  display: flex;
  flex-direction: column;
}

.profile-name {
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
}

.profile-email {
  font-size: 12px;
  color: #718096;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 24px;
  border-bottom: 2px solid #e2e8f0;
}

.tab-btn {
  padding: 12px 24px;
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  color: #718096;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  margin-bottom: -2px;
}

.tab-btn:hover {
  color: #2d3748;
}

.tab-btn.active {
  color: #48bb78;
  border-bottom-color: #48bb78;
}

/* Content Body */
.content-body {
  background: white;
  border-radius: 12px;
  padding: 24px;
}

.content-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.section-title {
  font-size: 20px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.action-buttons {
  display: flex;
  gap: 12px;
}

.btn-filter,
.btn-export {
  padding: 10px 20px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s;
}

.btn-filter:hover,
.btn-export:hover {
  background: #f7fafc;
  border-color: #48bb78;
  color: #48bb78;
}

/* Filters Panel */
.filters-panel {
  background: #f7fafc;
  padding: 16px;
  border-radius: 8px;
  margin-bottom: 24px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  align-items: end;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.filter-group label {
  font-size: 13px;
  font-weight: 500;
  color: #718096;
}

.filter-group select,
.filter-group input {
  padding: 8px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
}

.btn-clear-filters {
  padding: 8px 16px;
  background: #e53e3e;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.3s;
}

.btn-clear-filters:hover {
  background: #c53030;
}

/* Statistics Cards */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 24px;
}

.stat-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  transition: all 0.3s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.stat-info h3 {
  font-size: 28px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 4px 0;
}

.stat-info p {
  font-size: 14px;
  color: #718096;
  margin: 0;
}

/* Products Grid */
.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 24px;
  margin-bottom: 24px;
}

.product-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s;
}

.product-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  border-color: #48bb78;
}

.product-image {
  position: relative;
  width: 100%;
  height: 240px;
  overflow: hidden;
  background: #f7fafc;
}

.product-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.status-badge {
  position: absolute;
  top: 12px;
  right: 12px;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.status-badge.status-active {
  background: #d1fae5;
  color: #065f46;
}

.status-badge.status-draft {
  background: #fef3c7;
  color: #92400e;
}

.status-badge.status-inactive {
  background: #fee2e2;
  color: #991b1b;
}

.model-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  padding: 6px 12px;
  background: #7c3aed;
  color: white;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
}

.product-info {
  padding: 16px;
}

.product-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.product-category {
  font-size: 12px;
  color: #48bb78;
  font-weight: 600;
  text-transform: uppercase;
}

.product-sku {
  font-size: 11px;
  color: #a0aec0;
  font-weight: 500;
}

.product-name {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 4px 0;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.product-vendor {
  font-size: 13px;
  color: #718096;
  margin: 0 0 12px 0;
}

.product-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 0;
  border-top: 1px solid #f7fafc;
  border-bottom: 1px solid #f7fafc;
  margin-bottom: 12px;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
}

.detail-label {
  color: #718096;
  font-weight: 500;
}

.detail-value {
  color: #2d3748;
  font-weight: 600;
}

.detail-value.low-stock {
  color: #e53e3e;
}

.product-actions {
  display: flex;
  gap: 8px;
}

.btn-action {
  flex: 1;
  padding: 8px 12px;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.btn-toggle {
  background: #e2e8f0;
  color: #2d3748;
}

.btn-activate {
  background: #d1fae5;
  color: #065f46;
}

.btn-activate:hover {
  background: #a7f3d0;
}

.btn-deactivate {
  background: #fef3c7;
  color: #92400e;
}

.btn-deactivate:hover {
  background: #fde68a;
}

.btn-view {
  background: #dbeafe;
  color: #1e3a8a;
}

.btn-view:hover {
  background: #bfdbfe;
}

.btn-delete {
  background: #fee2e2;
  color: #991b1b;
}

.btn-delete:hover {
  background: #fecaca;
}

/* Loading & Empty States */
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px;
  color: #718096;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #f3f3f3;
  border-top: 3px solid #48bb78;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  margin-top: 24px;
}

.page-btn {
  padding: 8px 12px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s;
  min-width: 36px;
}

.page-btn:hover:not(:disabled) {
  border-color: #48bb78;
  color: #48bb78;
}

.page-btn.active {
  background: #48bb78;
  color: white;
  border-color: #48bb78;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 3000;
  padding: 20px;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 1200px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    transform: translateY(30px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
  font-size: 22px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.btn-close {
  width: 36px;
  height: 36px;
  border: none;
  background: #f7fafc;
  border-radius: 50%;
  cursor: pointer;
  font-size: 18px;
  color: #718096;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.btn-close:hover {
  background: #e2e8f0;
  color: #2d3748;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.modal-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 32px;
}

.modal-images {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.main-image {
  width: 100%;
  height: 400px;
  background: #f7fafc;
  border-radius: 12px;
  overflow: hidden;
}

.main-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.thumbnail-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.thumbnail {
  width: 100%;
  height: 80px;
  background: #f7fafc;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.3s;
}

.thumbnail.active {
  border-color: #48bb78;
}

.thumbnail:hover {
  border-color: #cbd5e0;
}

.thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.modal-details {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.detail-section h3 {
  font-size: 24px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 12px 0;
}

.meta-info {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}

.badge-category,
.badge-sku,
.badge-status,
.badge-flower-type {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.badge-category {
  background: #d1fae5;
  color: #065f46;
}

.badge-sku {
  background: #e2e8f0;
  color: #718096;
}

.badge-flower-type {
  background: #dbeafe;
  color: #1e3a8a;
}

.product-description {
  font-size: 14px;
  color: #718096;
  line-height: 1.6;
}

.detail-section h4 {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 12px 0;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-item label {
  font-size: 13px;
  font-weight: 500;
  color: #718096;
}

.info-item span {
  font-size: 14px;
  color: #2d3748;
  font-weight: 500;
}

.highlight-price {
  color: #48bb78 !important;
  font-size: 18px !important;
  font-weight: 700 !important;
}

.discount-price {
  color: #e53e3e !important;
}

.profit-margin {
  color: #48bb78 !important;
  font-weight: 700 !important;
}

.model-info-box {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: #f7fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.model-icon {
  font-size: 32px;
}

.model-file-name {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 4px 0;
}

.model-file-size {
  font-size: 12px;
  color: #718096;
  margin: 0;
}

.occasion-tags {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.occasion-tag {
  padding: 6px 12px;
  background: #f0fdf4;
  color: #166534;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
}

.modal-footer {
  display: flex;
  gap: 12px;
  padding: 24px;
  border-top: 1px solid #e2e8f0;
  justify-content: flex-end;
}

.btn-action-large {
  padding: 12px 32px;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-activate-large {
  background: #48bb78;
  color: white;
}

.btn-activate-large:hover {
  background: #38a169;
  transform: translateY(-1px);
}

.btn-deactivate-large {
  background: #f59e0b;
  color: white;
}

.btn-deactivate-large:hover {
  background: #d97706;
}

.btn-delete-large {
  background: #e53e3e;
  color: white;
}

.btn-delete-large:hover {
  background: #c53030;
}

/* Responsive */
@media (max-width: 1200px) {
  .main-content {
    margin-left: 0;
  }
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .modal-grid {
    grid-template-columns: 1fr;
  }
  .info-grid {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 640px) {
  .products-grid {
    grid-template-columns: 1fr;
  }
  .stats-grid {
    grid-template-columns: 1fr;
  }
  .content-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  .header-actions {
    width: 100%;
    flex-wrap: wrap;
  }
  .search-box input {
    width: 100%;
  }
}
</style>
