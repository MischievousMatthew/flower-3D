<template>
  <div class="product-card">
    <!-- ── Image ─────────────────────────────────────── -->
    <div class="product-image">
      <img
        :src="getProductImage(product)"
        :alt="product.product_name"
        @error="handleImageError"
      />
      <span v-if="product.discount_price" class="badge-discount">
        {{ discountPct }}% off
      </span>
    </div>

    <!-- ── Info ──────────────────────────────────────── -->
    <div class="product-info">
      <!-- Rating row + 3-dot menu -->
      <div class="product-rating-row">
        <div class="product-rating">
          <span class="star">⭐</span>
          <span class="rating-val">
            {{ product.average_rating?.toFixed(1) ?? "—" }}
          </span>
          <span v-if="soldLabel" class="sold-count">· {{ soldLabel }}</span>
          <span v-if="product.total_reviews" class="review-count"
            >({{ product.total_reviews }} reviews)</span
          >
        </div>

        <!-- 3-dot menu -->
        <div class="dot-menu-wrap" ref="dotMenuRef">
          <button
            class="dot-btn"
            :class="{ active: menuOpen }"
            @click.stop="menuOpen = !menuOpen"
            aria-label="Product options"
          >
            ⋮
          </button>
          <transition name="menu-pop">
            <div v-if="menuOpen" class="dot-menu">
              <button class="dot-menu-item report" @click="openReportModal">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <path
                    d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                  <line
                    x1="4"
                    y1="22"
                    x2="4"
                    y2="15"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                  />
                </svg>
                Report Product
              </button>
            </div>
          </transition>
        </div>
      </div>

      <p class="product-category">{{ product.category }}</p>
      <p
        v-if="product.vendor_name && !selectedVendor"
        class="product-vendor-tag"
        @click.stop="$emit('select-vendor', product.vendor_id)"
      >
        🌸 {{ product.vendor_name }}
      </p>
      <h3 class="product-name">{{ product.product_name }}</h3>
      <p class="product-description">{{ truncate(product.description) }}</p>

      <div class="product-footer">
        <div class="product-price">
          <template v-if="product.discount_price">
            <span class="price-current"
              >₱{{ fmt(product.discount_price) }}</span
            >
            <span class="price-original"
              >₱{{ fmt(product.selling_price) }}</span
            >
          </template>
          <template v-else>
            <span class="price-current">₱{{ fmt(product.selling_price) }}</span>
          </template>
        </div>
        <span :class="['stock-badge', stockClass]">{{ stockText }}</span>
      </div>

      <div class="product-actions-bottom">
        <button class="btn-view-details" @click="$emit('open-modal', product)">
          View Details
        </button>
        <button
          class="btn-add-to-cart"
          :disabled="product.quantity_in_stock === 0 || addingToCart"
          @click="$emit('add-to-cart', product)"
        >
          <span v-if="addingToCart" class="loading-spinner-small"></span>
          <span v-else>
            {{ product.quantity_in_stock > 0 ? "Add to Cart" : "Out of Stock" }}
          </span>
        </button>
      </div>
    </div>

    <!-- ── Report modal ──────────────────────────────── -->
    <teleport to="body">
      <transition name="modal-fade">
        <div
          v-if="reportModalOpen"
          class="report-modal-overlay"
          @click.self="closeReportModal"
        >
          <div class="report-modal">
            <div class="report-modal-header">
              <h3>Report Product</h3>
              <button class="rm-close" @click="closeReportModal">✕</button>
            </div>

            <div class="report-modal-body">
              <p class="report-product-name">
                📦 <strong>{{ product.product_name }}</strong>
              </p>

              <label class="rm-label">Reason *</label>
              <select v-model="reportForm.reason" class="rm-select">
                <option value="">Select a reason</option>
                <option value="counterfeit">Counterfeit / Fake Product</option>
                <option value="misleading">Misleading Description</option>
                <option value="inappropriate">Inappropriate Content</option>
                <option value="prohibited">Prohibited Item</option>
                <option value="spam">Spam / Duplicate Listing</option>
                <option value="other">Other</option>
              </select>
              <span v-if="reportErrors.reason" class="rm-error">
                {{ reportErrors.reason }}
              </span>

              <label class="rm-label" style="margin-top: 14px">
                Additional Details
                <span class="rm-optional">(optional)</span>
              </label>
              <textarea
                v-model="reportForm.description"
                class="rm-textarea"
                rows="3"
                placeholder="Describe the issue..."
                maxlength="500"
              />
              <span class="rm-char-count">
                {{ reportForm.description.length }}/500
              </span>
            </div>

            <div class="report-modal-footer">
              <button class="rm-btn-cancel" @click="closeReportModal">
                Cancel
              </button>
              <button
                class="rm-btn-submit"
                :disabled="submitting"
                @click="submitReport"
              >
                <span
                  v-if="submitting"
                  class="loading-spinner-small white"
                ></span>
                <span v-else>Submit Report</span>
              </button>
            </div>
          </div>
        </div>
      </transition>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { toast } from "vue3-toastify";
import api from "../../plugins/axios";
import { useAuth } from "../../composables/useAuth";

// ── Props / emits ─────────────────────────────────────
const props = defineProps({
  product: { type: Object, required: true },
  selectedVendor: { type: Object, default: null },
  addingToCart: { type: Boolean, default: false },
});
defineEmits(["open-modal", "add-to-cart", "select-vendor"]);

const { isAuthenticated } = useAuth();

// ── 3-dot menu ────────────────────────────────────────
const menuOpen = ref(false);
const dotMenuRef = ref(null);

const closeMenu = (e) => {
  if (dotMenuRef.value && !dotMenuRef.value.contains(e.target))
    menuOpen.value = false;
};
onMounted(() => document.addEventListener("click", closeMenu));
onUnmounted(() => document.removeEventListener("click", closeMenu));

// ── Report modal ──────────────────────────────────────
const reportModalOpen = ref(false);
const submitting = ref(false);
const reportForm = ref({ reason: "", description: "" });
const reportErrors = ref({});

const openReportModal = () => {
  menuOpen.value = false;
  if (!isAuthenticated.value) {
    toast.warning("Please login to report a product", { autoClose: 3000 });
    return;
  }
  reportModalOpen.value = true;
};
const closeReportModal = () => {
  reportModalOpen.value = false;
  reportForm.value = { reason: "", description: "" };
  reportErrors.value = {};
};

const submitReport = async () => {
  reportErrors.value = {};
  if (!reportForm.value.reason) {
    reportErrors.value.reason = "Please select a reason.";
    return;
  }
  submitting.value = true;
  try {
    await api.post(`/products/${props.product.id}/report`, {
      reason: reportForm.value.reason,
      description: reportForm.value.description || null,
    });
    toast.success(
      "Report submitted. Thank you for helping keep BloomCraft safe!",
      {
        autoClose: 4000,
      },
    );
    closeReportModal();
  } catch (err) {
    const msg = err?.response?.data?.message;
    // 409 = already reported
    if (err?.response?.status === 409) {
      toast.info("You have already reported this product.", {
        autoClose: 3000,
      });
      closeReportModal();
    } else {
      toast.error(msg || "Failed to submit report. Please try again.", {
        autoClose: 3000,
      });
    }
  } finally {
    submitting.value = false;
  }
};

// ── Computed helpers ──────────────────────────────────
const discountPct = computed(() => {
  const orig = parseFloat(props.product.selling_price);
  const sale = parseFloat(props.product.discount_price);
  if (!orig || !sale || sale >= orig) return 0;
  return Math.round(((orig - sale) / orig) * 100);
});

const soldLabel = computed(() => {
  const n = props.product.sold_count ?? 0;
  if (!n) return "";

  const formatNumber = (num) => {
    if (num >= 1_000_000) {
      return (num / 1_000_000).toFixed(num % 1_000_000 === 0 ? 0 : 1) + "M";
    }
    if (num >= 1_000) {
      return (num / 1_000).toFixed(num % 1_000 === 0 ? 0 : 1) + "k";
    }
    return num.toString();
  };

  return `${formatNumber(n)} sold`;
});

const stockClass = computed(() => {
  const q = props.product.quantity_in_stock;
  if (q <= 0) return "out-of-stock";
  if (q <= 10) return "low-stock";
  return "in-stock";
});

const stockText = computed(() => {
  const q = props.product.quantity_in_stock;
  if (q <= 0) return "Out of Stock";
  if (q <= 10) return "Low Stock";
  return "In Stock";
});

const fmt = (p) => (!p ? "0.00" : parseFloat(p).toFixed(2));
const truncate = (t, n = 60) =>
  !t ? "" : t.length > n ? t.slice(0, n) + "..." : t;

const getProductImage = (product) => {
  if (product?.primary_image?.image_url) return product.primary_image.image_url;
  if (product?.images?.length > 0) {
    const p = product.images.find((i) => i.is_primary);
    return p?.image_url ?? product.images[0].image_url;
  }
  const fallbacks = [
    "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=300&h=300&fit=crop",
    "https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=300&h=300&fit=crop",
    "https://images.unsplash.com/photo-1588423865281-316e8eab60ee?w=300&h=300&fit=crop",
    "https://images.unsplash.com/photo-1518709594023-6eab9bab7b23?w=300&h=300&fit=crop",
  ];
  return fallbacks[(product.id ?? 0) % fallbacks.length];
};
const handleImageError = (e) => {
  e.target.src =
    "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=300&h=300&fit=crop";
};
</script>

<style scoped>
* {
  font-family: "Poppins", sans-serif;
  box-sizing: border-box;
}

/* ── Card ───────────────────────────────────────────── */
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

/* Image */
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

/* Info */
.product-info {
  padding: 16px;
}

/* ── Rating row ──────────────────────────────────────── */
.product-rating-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.product-rating {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 14px;
  color: #2d3748;
  font-weight: 500;
}
.star {
  font-size: 13px;
}
.rating-val {
  font-weight: 600;
}
.sold-count {
  font-size: 12px;
  color: #48bb78;
  font-weight: 600;
  background: #f0fff4;
  padding: 1px 7px;
  border-radius: 20px;
  border: 1px solid #c6f6d5;
}
.review-count {
  font-size: 12px;
  color: #a0aec0;
  font-weight: 400;
}

/* ── 3-dot menu ──────────────────────────────────────── */
.dot-menu-wrap {
  position: relative;
  flex-shrink: 0;
}

.dot-btn {
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  border-radius: 6px;
  font-size: 18px;
  font-weight: 700;
  color: #a0aec0;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
  line-height: 1;
  letter-spacing: 0;
}
.dot-btn:hover,
.dot-btn.active {
  background: #f7fafc;
  color: #2d3748;
}

.dot-menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  min-width: 160px;
  background: white;
  border-radius: 10px;
  box-shadow:
    0 0 0 1px rgba(0, 0, 0, 0.05),
    0 8px 24px rgba(0, 0, 0, 0.12);
  overflow: hidden;
  z-index: 500;
}

.dot-menu-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 11px 14px;
  font-size: 13.5px;
  font-weight: 500;
  background: transparent;
  border: none;
  width: 100%;
  cursor: pointer;
  text-align: left;
  transition: background 0.15s;
  font-family: inherit;
  color: #374151;
}
.dot-menu-item:hover {
  background: #f9fafb;
}
.dot-menu-item.report {
  color: #e53e3e;
}
.dot-menu-item.report:hover {
  background: #fff5f5;
}

/* Menu transition */
.menu-pop-enter-active {
  transition:
    opacity 0.15s ease,
    transform 0.15s cubic-bezier(0.16, 1, 0.3, 1);
}
.menu-pop-leave-active {
  transition:
    opacity 0.1s ease,
    transform 0.1s ease;
}
.menu-pop-enter-from,
.menu-pop-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.96);
}

/* Product text */
.product-category {
  font-size: 13px;
  color: #718096;
  margin-bottom: 4px;
}
.product-vendor-tag {
  font-size: 12px;
  color: #48bb78;
  font-weight: 500;
  cursor: pointer;
  margin: 0 0 4px;
  transition: color 0.2s;
}
.product-vendor-tag:hover {
  color: #276749;
  text-decoration: underline;
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

/* Footer */
.product-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
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

/* Stock */
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

/* Action buttons */
.product-actions-bottom {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.btn-view-details {
  height: 40px;
  padding: 0 16px;
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
  font-family: inherit;
}
.btn-view-details:hover {
  background: #f7fafc;
}
.btn-add-to-cart {
  height: 40px;
  padding: 0 16px;
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
  font-family: inherit;
}
.btn-add-to-cart:hover:not(:disabled) {
  background: #38a169;
  transform: translateY(-2px);
}
.btn-add-to-cart:disabled {
  background: #cbd5e0;
  cursor: not-allowed;
}

/* ── Report Modal ─────────────────────────────────────── */
.report-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}
.report-modal {
  background: white;
  border-radius: 16px;
  width: 100%;
  max-width: 460px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  overflow: hidden;
}
.report-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #f1f5f9;
}
.report-modal-header h3 {
  font-size: 17px;
  font-weight: 700;
  color: #1a202c;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.report-modal-header h3::before {
  content: "🚩";
  font-size: 16px;
}
.rm-close {
  width: 32px;
  height: 32px;
  background: #f7fafc;
  border: none;
  border-radius: 50%;
  cursor: pointer;
  font-size: 16px;
  color: #718096;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.rm-close:hover {
  background: #fee2e2;
  color: #e53e3e;
}

.report-modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
}
.report-product-name {
  font-size: 13px;
  color: #4a5568;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 18px;
}

.rm-label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}
.rm-optional {
  font-weight: 400;
  color: #9ca3af;
  font-size: 12px;
  margin-left: 4px;
}

.rm-select,
.rm-textarea {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 9px;
  font-size: 14px;
  font-family: inherit;
  color: #1a202c;
  background: white;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}
.rm-select:focus,
.rm-textarea:focus {
  outline: none;
  border-color: #e53e3e;
  box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.1);
}
.rm-textarea {
  resize: vertical;
  min-height: 80px;
}
.rm-error {
  font-size: 12px;
  color: #e53e3e;
  margin-top: 4px;
  font-weight: 500;
}
.rm-char-count {
  font-size: 11px;
  color: #a0aec0;
  margin-top: 4px;
  text-align: right;
}

.report-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid #f1f5f9;
}
.rm-btn-cancel {
  padding: 10px 22px;
  background: white;
  color: #4a5568;
  border: 1.5px solid #e2e8f0;
  border-radius: 9px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
}
.rm-btn-cancel:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}
.rm-btn-submit {
  padding: 10px 24px;
  background: #e53e3e;
  color: white;
  border: none;
  border-radius: 9px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 130px;
  justify-content: center;
}
.rm-btn-submit:hover:not(:disabled) {
  background: #c53030;
  transform: translateY(-1px);
}
.rm-btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Modal transition */
.modal-fade-enter-active {
  transition: opacity 0.2s ease;
}
.modal-fade-leave-active {
  transition: opacity 0.15s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

/* Spinner */
.loading-spinner-small {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
.loading-spinner-small.white {
  border-color: rgba(255, 255, 255, 0.3);
  border-top-color: white;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
