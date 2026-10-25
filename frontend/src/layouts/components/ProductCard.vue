<template>
  <article
    ref="cardRef"
    class="product-card"
    role="button"
    tabindex="0"
    @click="$emit('open-modal', product)"
    @keydown.enter="$emit('open-modal', product)"
    @keydown.space.prevent="$emit('open-modal', product)"
  >
    <!-- ── Image ─────────────────────────────────────── -->
    <div class="product-image">
      <img
        ref="imageRef"
        :src="getProductImage(product)"
        :alt="product.product_name"
        @error="handleImageError"
      />
      <span v-if="productBadge" :class="['badge-discount', productBadge.className]">
        {{ productBadge.label }}
      </span>
      <div class="image-actions" @click.stop>
        <button
          class="image-action image-action-cart"
          :disabled="product.quantity_in_stock === 0 || addingToCart"
          @click.stop="handleAddToCart"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h2l2.4 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
          <span>{{ addingToCart ? "Adding" : "Add to Cart" }}</span>
        </button>
        <button
          class="image-action image-action-buy"
          :disabled="product.quantity_in_stock === 0"
          @click.stop="$emit('buy-now', product)"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.6-7 10-7 10Z"/></svg>
          <span>Buy Now</span>
        </button>
        <button class="image-action image-action-view" @click.stop="$emit('open-modal', product)">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>
          <span>View Details</span>
        </button>
      </div>
    </div>

    <!-- ── Info ──────────────────────────────────────── -->
    <div class="product-info">
      <!-- Rating row + 3-dot menu -->
      <div class="product-rating-row">
        <div class="product-rating">
          <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.8z"/></svg>
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
        {{ product.vendor_name }}
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

    </div>

    <!-- ── Report modal ──────────────────────────────── -->
    <teleport to="body">
      <transition name="modal-fade">
        <div
          v-if="reportModalOpen"
          class="report-modal-overlay bc-theme"
          @click.self="closeReportModal"
        >
          <div class="report-modal">
            <div class="report-modal-header">
              <h3>Report Product</h3>
              <button class="rm-close" @click="closeReportModal">✕</button>
            </div>

            <div class="report-modal-body">
              <p class="report-product-name">
                <strong>{{ product.product_name }}</strong>
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
                {{ submitting ? "Submitting..." : "Submit Report" }}
              </button>
            </div>
          </div>
        </div>
      </transition>
    </teleport>
  </article>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { toast } from "@/utils/snackbar";
import api from "../../plugins/axios";
import { useAuth } from "../../composables/useAuth";

// ── Props / emits ─────────────────────────────────────
const props = defineProps({
  product: { type: Object, required: true },
  selectedVendor: { type: Object, default: null },
  addingToCart: { type: Boolean, default: false },
});
const emit = defineEmits(["open-modal", "add-to-cart", "buy-now", "select-vendor"]);

const { isAuthenticated } = useAuth();
const cardRef = ref(null);
const imageRef = ref(null);

const handleAddToCart = () => {
  emit("add-to-cart", props.product, {
    imageEl: imageRef.value,
    cardEl: cardRef.value,
  });
};

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

const productBadge = computed(() => {
  if (props.product.discount_price) {
    return { label: "Sale", className: "badge-sale" };
  }

  if ((props.product.sold_count ?? 0) >= 10) {
    return { label: "Best Seller", className: "badge-best-seller" };
  }

  return { label: "New", className: "badge-new" };
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
/* Uses the tokens from src/styles/bloomcraft.css (.bc-theme). */

/* ── Card ───────────────────────────────────────────── */
.product-card {
  position: relative;
  display: flex;
  flex-direction: column;
  min-width: 0;
  color: var(--ink);
  font-family: var(--font-sans);
  cursor: pointer;
}

.product-card:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 6px;
  border-radius: 16px;
}

/* Image */
.product-image {
  position: relative;
  aspect-ratio: 4 / 5;
  overflow: hidden;
  border-radius: 16px;
  background: var(--ivory-deep);
}

.product-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.9s var(--ease-out);
}

.product-card:hover .product-image img {
  transform: scale(1.04);
}

.badge-discount {
  position: absolute;
  top: 14px;
  left: 14px;
  padding: 5px 11px;
  border-radius: 999px;
  background: rgba(250, 251, 250, 0.92);
  color: var(--ink);
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.badge-sale {
  color: var(--leaf-deep);
}

/* Quick actions: revealed on hover (always shown on touch screens) */
.image-actions {
  position: absolute;
  left: 12px;
  right: 12px;
  bottom: 12px;
  display: flex;
  gap: 6px;
  opacity: 0;
  transform: translateY(10px);
  transition:
    opacity 0.4s var(--ease-out),
    transform 0.4s var(--ease-out);
}

.product-card:hover .image-actions,
.product-card:focus-within .image-actions {
  opacity: 1;
  transform: none;
}

.image-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 40px;
  padding: 0 14px;
  border: none;
  border-radius: 999px;
  background: rgba(250, 251, 250, 0.95);
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.3s var(--ease-out), color 0.3s;
}

.image-action svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.image-action-cart {
  flex: 1;
  background: var(--ink);
  color: var(--ivory);
}

.image-action-cart:hover:not(:disabled) {
  background: var(--leaf-deep);
}

.image-action-buy:hover:not(:disabled),
.image-action-view:hover {
  background: #fff;
}

/* View details is icon-only to keep the row compact */
.image-action-view span {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}

.image-action-view {
  width: 40px;
  padding: 0;
}

.image-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.image-action:focus-visible,
.dot-btn:focus-visible,
.product-vendor-tag:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

@media (hover: none) {
  .image-actions {
    opacity: 1;
    transform: none;
  }
}

/* ── Info ───────────────────────────────────────────── */
.product-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-top: 16px;
}

.product-rating-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.product-rating {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--ink-soft);
  font-size: 12px;
}

.star {
  width: 13px;
  height: 13px;
  fill: var(--gold);
}

.rating-val {
  color: var(--ink);
  font-weight: 500;
}

.dot-menu-wrap {
  position: relative;
}

.dot-btn {
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 50%;
  background: none;
  color: var(--ink-soft);
  font-size: 16px;
  line-height: 1;
  cursor: pointer;
  transition: background 0.25s;
}

.dot-btn:hover,
.dot-btn.active {
  background: var(--ivory-deep);
  color: var(--ink);
}

.dot-menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 20;
  min-width: 170px;
  padding: 6px;
  border: 1px solid var(--line-soft);
  border-radius: 14px;
  background: var(--paper);
  box-shadow: 0 18px 40px rgba(54, 63, 78, 0.12);
}

.dot-menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 10px 12px;
  border: none;
  border-radius: 10px;
  background: none;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 13px;
  text-align: left;
  cursor: pointer;
}

.dot-menu-item:hover {
  background: var(--ivory);
}

.dot-menu-item.report {
  color: var(--leaf-deep);
}

.menu-pop-enter-active,
.menu-pop-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}

.menu-pop-enter-from,
.menu-pop-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

.product-category {
  margin: 0;
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.18em;
  text-transform: uppercase;
}

.product-vendor-tag {
  align-self: flex-start;
  margin: 0;
  color: var(--leaf-deep);
  font-size: 12.5px;
  cursor: pointer;
}

.product-vendor-tag:hover {
  text-decoration: underline;
  text-underline-offset: 3px;
}

.product-name {
  margin: 2px 0 0;
  font-family: var(--font-display);
  font-size: 24px;
  font-weight: 500;
  line-height: 1.12;
  letter-spacing: -0.01em;
  color: var(--ink);
}

.product-description {
  display: -webkit-box;
  margin: 0;
  overflow: hidden;
  color: var(--ink-soft);
  font-size: 13px;
  line-height: 1.6;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.product-footer {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 10px;
  margin-top: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--line-soft);
}

.product-price {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 8px;
}

.price-current {
  font-family: var(--font-display);
  font-size: 24px;
  font-weight: 500;
  line-height: 1;
}

.price-original {
  color: var(--ink-soft);
  font-size: 12.5px;
  text-decoration: line-through;
}

.stock-badge {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  white-space: nowrap;
}

.stock-badge.in-stock {
  color: #5d7150;
}

.stock-badge.low-stock {
  color: #9a6a1f;
}

.stock-badge.out-of-stock {
  color: var(--leaf-deep);
}

/* ── Report modal (teleported to <body>, carries .bc-theme itself) ── */
.report-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(38, 45, 57, 0.45);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

.report-modal {
  width: 100%;
  max-width: 460px;
  overflow: hidden;
  border-radius: 24px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(38, 45, 57, 0.25);
}

.report-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 26px 28px 0;
}

.report-modal-header h3 {
  margin: 0;
  font-size: 30px;
}

.rm-close {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 50%;
  background: var(--ivory);
  color: var(--ink);
  cursor: pointer;
}

.report-modal-body {
  display: flex;
  flex-direction: column;
  padding: 18px 28px 8px;
}

.report-product-name {
  margin: 0 0 18px;
  color: var(--ink-soft);
  font-size: 14px;
}

.report-product-name strong {
  color: var(--ink);
  font-weight: 500;
}

.rm-label {
  margin-bottom: 8px;
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.rm-optional {
  text-transform: none;
  letter-spacing: 0;
}

.rm-select,
.rm-textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: #fff;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 14px;
  box-sizing: border-box;
}

.rm-select:focus,
.rm-textarea:focus {
  outline: none;
  border-color: var(--ink);
}

.rm-textarea {
  resize: vertical;
}

.rm-error {
  margin-top: 6px;
  color: var(--leaf-deep);
  font-size: 12.5px;
}

.rm-char-count {
  align-self: flex-end;
  margin-top: 6px;
  color: var(--ink-soft);
  font-size: 11.5px;
}

.report-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 18px 28px 26px;
}

.rm-btn-cancel,
.rm-btn-submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 120px;
  padding: 13px 22px;
  border-radius: 999px;
  font-family: var(--font-sans);
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.3s, border-color 0.3s;
}

.rm-btn-cancel {
  border: 1px solid var(--line);
  background: none;
  color: var(--ink);
}

.rm-btn-cancel:hover {
  border-color: var(--ink);
}

.rm-btn-submit {
  border: none;
  background: var(--ink);
  color: var(--ivory);
}

.rm-btn-submit:hover:not(:disabled) {
  background: var(--leaf-deep);
}

.rm-btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.rm-close:focus-visible,
.rm-btn-cancel:focus-visible,
.rm-btn-submit:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.25s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

@media (max-width: 640px) {
  .review-count {
    display: none;
  }

  .product-name {
    font-size: 20px;
  }

  .price-current {
    font-size: 20px;
  }

  .image-action {
    height: 36px;
    padding: 0 10px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .product-image img,
  .image-actions {
    transition: none;
  }
}
</style>
