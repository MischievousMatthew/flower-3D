<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="show" class="rm-backdrop" @click.self="$emit('close')">
        <div class="rm-modal" role="dialog" aria-modal="true">
          <!-- Loading -->
          <div v-if="loadingProducts" class="rm-loading">
            <span class="rm-spinner rm-spinner--dark"></span>
            <p>Loading…</p>
          </div>

          <!-- No products -->
          <template v-else-if="resolvedProducts.length === 0">
            <div class="rm-header">
              <div>
                <p class="rm-label">Rate Your Order</p>
                <h3 class="rm-product-name" style="font-size: 15px">
                  No products to review
                </h3>
              </div>
              <button
                class="rm-close"
                @click="$emit('close')"
                aria-label="Close"
              >
                <svg
                  viewBox="0 0 20 20"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  width="18"
                >
                  <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
                </svg>
              </button>
            </div>
            <div class="rm-body">
              <p
                style="
                  color: #94a3b8;
                  font-size: 14px;
                  text-align: center;
                  padding: 24px 0;
                "
              >
                No reviewable products found for this order.
              </p>
            </div>
            <div class="rm-footer">
              <button
                class="rm-btn rm-btn--cancel"
                @click="$emit('close')"
                type="button"
              >
                Close
              </button>
            </div>
          </template>

          <!-- Product picker (only when multiple products AND none auto-selected) -->
          <template v-else-if="!activeProduct">
            <div class="rm-header">
              <div>
                <p class="rm-label">Rate Your Order</p>
                <h3 class="rm-product-name" style="font-size: 15px">
                  Select a product to review
                </h3>
                <p class="rm-order-ref">Order #{{ orderNumber }}</p>
              </div>
              <button
                class="rm-close"
                @click="$emit('close')"
                aria-label="Close"
              >
                <svg
                  viewBox="0 0 20 20"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  width="18"
                >
                  <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
                </svg>
              </button>
            </div>
            <div class="rm-body">
              <div class="rm-product-list">
                <div
                  v-for="p in resolvedProducts"
                  :key="p.id"
                  class="rm-product-row"
                  @click="selectProduct(p)"
                >
                  <img
                    v-if="p.image"
                    :src="p.image"
                    :alt="p.name"
                    class="rm-product-img"
                  />
                  <div
                    v-else
                    class="rm-product-img rm-product-img--placeholder"
                  >
                    <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                      width="22"
                    >
                      <rect x="3" y="3" width="18" height="18" rx="2" />
                      <circle cx="8.5" cy="8.5" r="1.5" />
                      <path d="M21 15l-5-5L5 21" />
                    </svg>
                  </div>
                  <div class="rm-product-row__info">
                    <span class="rm-product-row__name">{{ p.name }}</span>
                    <span class="rm-product-row__qty"
                      >Qty: {{ p.quantity }}</span
                    >
                  </div>
                  <div class="rm-product-row__status">
                    <span v-if="p.reviewed" class="rm-reviewed-badge"
                      >✓ Reviewed</span
                    >
                    <span v-else class="rm-rate-cta">Rate →</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="rm-footer">
              <button
                class="rm-btn rm-btn--cancel"
                @click="$emit('close')"
                type="button"
              >
                Close
              </button>
            </div>
          </template>

          <!-- Review form -->
          <template v-else>
            <div class="rm-header">
              <div class="rm-product-info">
                <img
                  v-if="activeProduct.image"
                  :src="activeProduct.image"
                  :alt="activeProduct.name"
                  class="rm-product-img"
                />
                <div v-else class="rm-product-img rm-product-img--placeholder">
                  <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="22"
                  >
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <circle cx="8.5" cy="8.5" r="1.5" />
                    <path d="M21 15l-5-5L5 21" />
                  </svg>
                </div>
                <div style="min-width: 0">
                  <p class="rm-label">
                    {{ isEditing ? "Edit Your Review" : "Rate this Product" }}
                  </p>
                  <h3 class="rm-product-name">{{ activeProduct.name }}</h3>
                  <p class="rm-order-ref">Order #{{ orderNumber }}</p>
                </div>
              </div>
              <button class="rm-close" @click="handleClose" aria-label="Close">
                <svg
                  viewBox="0 0 20 20"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  width="18"
                >
                  <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
                </svg>
              </button>
            </div>

            <div class="rm-body">
              <div v-if="errors._global" class="rm-global-error">
                {{ errors._global }}
              </div>

              <!-- Stars -->
              <div class="rm-section">
                <label class="rm-field-label"
                  >Your Rating <span class="rm-required">*</span></label
                >
                <div class="rm-stars-row">
                  <button
                    v-for="n in 5"
                    :key="n"
                    class="rm-star-btn"
                    :class="{ active: n <= (hoverRating || form.rating) }"
                    @click="form.rating = n"
                    @mouseenter="hoverRating = n"
                    @mouseleave="hoverRating = 0"
                    type="button"
                  >
                    <svg viewBox="0 0 24 24" width="32" height="32">
                      <path
                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
                        :fill="
                          n <= (hoverRating || form.rating) ? '#f59e0b' : 'none'
                        "
                        :stroke="
                          n <= (hoverRating || form.rating)
                            ? '#f59e0b'
                            : '#d1d5db'
                        "
                        stroke-width="1.5"
                      />
                    </svg>
                  </button>
                  <span class="rm-rating-label">{{ ratingLabel }}</span>
                </div>
                <p v-if="errors.rating" class="rm-error">{{ errors.rating }}</p>
              </div>

              <!-- Comment -->
              <div class="rm-section">
                <label class="rm-field-label" for="rm-comment">Comment</label>
                <textarea
                  id="rm-comment"
                  v-model="form.comment"
                  class="rm-textarea"
                  :class="{ 'rm-textarea--error': errors.comment }"
                  placeholder="Share your experience with this product…"
                  rows="4"
                  maxlength="2000"
                ></textarea>
                <div class="rm-textarea-footer">
                  <p v-if="errors.comment" class="rm-error">
                    {{ errors.comment }}
                  </p>
                  <span class="rm-char-count"
                    >{{ form.comment.length }}/2000</span
                  >
                </div>
              </div>

              <!-- Image -->
              <div class="rm-section">
                <label class="rm-field-label"
                  >Photo <span class="rm-optional">(optional)</span></label
                >
                <div
                  v-if="
                    imagePreview ||
                    (isEditing &&
                      activeProduct.existingReview?.image_url &&
                      !removeImage)
                  "
                  class="rm-img-preview"
                >
                  <img
                    :src="
                      imagePreview || activeProduct.existingReview?.image_url
                    "
                    alt="Review photo"
                    class="rm-preview-img"
                  />
                  <button
                    class="rm-remove-img"
                    @click="clearImage"
                    type="button"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      width="14"
                    >
                      <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
                    </svg>
                  </button>
                </div>
                <div
                  v-else
                  class="rm-upload-zone"
                  :class="{ 'rm-upload-zone--drag': isDragging }"
                  @click="$refs.fileInput.click()"
                  @dragover.prevent="isDragging = true"
                  @dragleave="isDragging = false"
                  @drop.prevent="onDrop"
                >
                  <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    width="28"
                    class="rm-upload-icon"
                  >
                    <path
                      d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                  </svg>
                  <p class="rm-upload-text">
                    Drop an image here or <span>browse</span>
                  </p>
                  <p class="rm-upload-hint">JPG, PNG, WebP — max 5 MB</p>
                </div>
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  class="rm-hidden-input"
                  @change="onFileChange"
                />
                <p v-if="errors.image" class="rm-error">{{ errors.image }}</p>
              </div>

              <!-- Anonymous -->
              <div class="rm-section rm-anon-row">
                <label
                  class="rm-toggle-label"
                  @click="form.is_anonymous = !form.is_anonymous"
                >
                  <div
                    class="rm-toggle"
                    :class="{ 'rm-toggle--on': form.is_anonymous }"
                  >
                    <div class="rm-toggle-knob"></div>
                  </div>
                  <div>
                    <span class="rm-toggle-text">Post anonymously</span>
                    <span class="rm-toggle-sub"
                      >Your name will appear as "Anonymous User"</span
                    >
                  </div>
                </label>
              </div>
            </div>

            <div class="rm-footer">
              <button
                v-if="resolvedProducts.length > 1"
                class="rm-btn rm-btn--cancel"
                @click="backToList"
                type="button"
              >
                ← Back
              </button>
              <button
                v-else
                class="rm-btn rm-btn--cancel"
                @click="$emit('close')"
                type="button"
              >
                Cancel
              </button>
              <button
                class="rm-btn rm-btn--submit"
                :disabled="submitting || !form.rating"
                @click="submit"
                type="button"
              >
                <span v-if="submitting" class="rm-spinner"></span>
                <span v-else>{{
                  isEditing ? "Update Review" : "Submit Review"
                }}</span>
              </button>
            </div>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import api from "../../plugins/axios.js";

const props = defineProps({
  show: { type: Boolean, default: false },
  orderId: { type: Number, default: null },
  orderNumber: { type: String, default: "" },
  // Pass order.items directly from OrderTracking.vue — avoids extra API call
  // Each item needs: { id, product_id (or falls back to id when product_name present), product_name, product_image, quantity }
  orderItems: { type: Array, default: () => [] },
});

const emit = defineEmits(["close", "reviewed"]);

// ── State ──────────────────────────────────────────────────────────────────────
const loadingProducts = ref(false);
const fetchedItems = ref([]);
const activeProduct = ref(null);
const hoverRating = ref(0);
const isDragging = ref(false);
const submitting = ref(false);
const imagePreview = ref(null);
const removeImage = ref(false);
const errors = ref({});
const fileInput = ref(null);
const form = ref({ rating: 0, comment: "", is_anonymous: false, image: null });

// ── Resolve product list ───────────────────────────────────────────────────────
// Uses passed-in orderItems first; falls back to API-fetched items
const resolvedProducts = computed(() => {
  const source =
    props.orderItems?.length > 0 ? props.orderItems : fetchedItems.value;
  return source
    .map((item) => {
      // Your API returns order items where `id` = order_item row id AND product_name is present.
      // product_id is the correct field; fall back to id only when product_name confirms it's a real product row.
      // NEVER use item.id as product_id — item.id is the order_item row PK, not the product PK
      const productId = item.product_id ?? item.product?.id ?? null;
      if (!productId) return null;
      return {
        id: productId,
        name:
          item.product_name ??
          item.product?.product_name ??
          item.product?.name ??
          "Product",
        image:
          item.product_image ?? item.product?.primary_image?.image_url ?? null,
        quantity: item.quantity ?? 1,
        reviewed: !!item.review,
        existingReview: item.review ?? null,
      };
    })
    .filter(Boolean);
});

const isEditing = computed(() => !!activeProduct.value?.existingReview?.id);
const ratingLabel = computed(() => {
  const labels = {
    0: "",
    1: "Poor",
    2: "Fair",
    3: "Good",
    4: "Very Good",
    5: "Excellent",
  };
  return labels[hoverRating.value || form.value.rating] ?? "";
});

// ── Watch: open / close ────────────────────────────────────────────────────────
watch(
  () => props.show,
  async (visible) => {
    if (visible) {
      fullReset();
      if (!props.orderItems?.length && props.orderId) {
        await fetchOrderProducts();
      } else {
        autoSelectIfSingle();
      }
    }
  },
);

// Re-run auto-select whenever orderItems updates (e.g. parent loads them async)
watch(
  () => props.orderItems,
  () => {
    if (props.show && !activeProduct.value) autoSelectIfSingle();
  },
  { deep: true },
);

// ── Auto-select single product ─────────────────────────────────────────────────
function autoSelectIfSingle() {
  if (resolvedProducts.value.length === 1) {
    selectProduct(resolvedProducts.value[0]);
  }
}

// ── Fetch from API (fallback) ─────────────────────────────────────────────────
async function fetchOrderProducts() {
  loadingProducts.value = true;
  try {
    const res = await api.get(`customer/orders/${props.orderId}`);
    const order = res.data?.data ?? res.data;
    fetchedItems.value = order?.items ?? [];
    autoSelectIfSingle();
  } catch (e) {
    console.error("ReviewModal: fetch failed", e);
    errors.value._global = "Could not load order items. Please try again.";
  } finally {
    loadingProducts.value = false;
  }
}

// ── Product selection ──────────────────────────────────────────────────────────
function selectProduct(p) {
  activeProduct.value = p;
  resetForm();
  if (p.existingReview) {
    form.value.rating = p.existingReview.rating ?? 0;
    form.value.comment = p.existingReview.comment ?? "";
    form.value.is_anonymous = p.existingReview.is_anonymous ?? false;
  }
}

function backToList() {
  activeProduct.value = null;
  resetForm();
}

function handleClose() {
  if (resolvedProducts.value.length <= 1) emit("close");
  else backToList();
}

// ── Image ──────────────────────────────────────────────────────────────────────
function onFileChange(e) {
  handleFile(e.target.files?.[0]);
}
function onDrop(e) {
  isDragging.value = false;
  handleFile(e.dataTransfer.files?.[0]);
}

function handleFile(file) {
  if (!file) return;
  if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
    errors.value.image = "Only JPG, PNG, or WebP images are allowed.";
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    errors.value.image = "Image must be under 5 MB.";
    return;
  }
  errors.value.image = null;
  form.value.image = file;
  removeImage.value = false;
  const reader = new FileReader();
  reader.onload = (ev) => (imagePreview.value = ev.target.result);
  reader.readAsDataURL(file);
}

function clearImage() {
  form.value.image = null;
  imagePreview.value = null;
  removeImage.value = true;
  if (fileInput.value) fileInput.value.value = "";
}

// ── Submit ─────────────────────────────────────────────────────────────────────
async function submit() {
  errors.value = {};
  if (!form.value.rating) {
    errors.value.rating = "Please select a rating.";
    return;
  }

  submitting.value = true;
  try {
    const fd = new FormData();
    fd.append("order_id", props.orderId);
    fd.append("rating", form.value.rating);
    fd.append("comment", form.value.comment ?? "");
    fd.append("is_anonymous", form.value.is_anonymous ? "1" : "0");
    if (form.value.image) fd.append("image", form.value.image);
    if (removeImage.value) fd.append("remove_image", "1");

    if (isEditing.value) {
      fd.append("_method", "PUT");
      await api.post(`reviews/${activeProduct.value.existingReview.id}`, fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    } else {
      await api.post(`customer/products/${activeProduct.value.id}/review`, fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    }

    emit("reviewed", props.orderId);
    if (resolvedProducts.value.length > 1) backToList();
    else emit("close");
  } catch (err) {
    const d = err.response?.data;
    errors.value = d?.errors ?? {};
    if (!d?.errors)
      errors.value._global =
        d?.message ?? "Something went wrong. Please try again.";
  } finally {
    submitting.value = false;
  }
}

// ── Reset ──────────────────────────────────────────────────────────────────────
function resetForm() {
  hoverRating.value = 0;
  imagePreview.value = null;
  removeImage.value = false;
  errors.value = {};
  submitting.value = false;
  form.value = { rating: 0, comment: "", is_anonymous: false, image: null };
}
function fullReset() {
  resetForm();
  activeProduct.value = null;
  fetchedItems.value = [];
  loadingProducts.value = false;
}
</script>

<style scoped>
.rm-backdrop {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.rm-modal {
  background: #fff;
  border-radius: 16px;
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.18);
  overflow: hidden;
  font-family: "Poppins", sans-serif;
}
.rm-loading {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 48px 24px;
  color: #718096;
  font-size: 14px;
}
.rm-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 20px 22px 16px;
  border-bottom: 1px solid #f1f5f9;
  gap: 12px;
  flex-shrink: 0;
}
.rm-product-info {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}
.rm-product-img {
  width: 52px;
  height: 52px;
  border-radius: 10px;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid #e2e8f0;
}
.rm-product-img--placeholder {
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
}
.rm-label {
  font-size: 11px;
  font-weight: 600;
  color: #48bb78;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0 0 2px;
}
.rm-product-name {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rm-order-ref {
  font-size: 12px;
  color: #94a3b8;
  margin: 0;
}
.rm-close {
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px;
  color: #94a3b8;
  border-radius: 6px;
  line-height: 0;
  flex-shrink: 0;
  transition:
    color 0.15s,
    background 0.15s;
}
.rm-close:hover {
  color: #ef4444;
  background: #fef2f2;
}
.rm-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.rm-product-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.rm-product-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition:
    border-color 0.15s,
    background 0.15s;
}
.rm-product-row:hover {
  border-color: #48bb78;
  background: #f0fff4;
}
.rm-product-row__info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.rm-product-row__name {
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rm-product-row__qty {
  font-size: 11px;
  color: #94a3b8;
}
.rm-product-row__status {
  flex-shrink: 0;
}
.rm-reviewed-badge {
  font-size: 11px;
  font-weight: 600;
  color: #276749;
  background: #f0fff4;
  border: 1px solid #c6f6d5;
  border-radius: 20px;
  padding: 3px 10px;
}
.rm-rate-cta {
  font-size: 12px;
  font-weight: 600;
  color: #48bb78;
}
.rm-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.rm-field-label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}
.rm-required {
  color: #ef4444;
  margin-left: 2px;
}
.rm-optional {
  font-weight: 400;
  color: #94a3b8;
  font-size: 12px;
}
.rm-stars-row {
  display: flex;
  align-items: center;
  gap: 4px;
}
.rm-star-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 2px;
  line-height: 0;
  border-radius: 4px;
  transition: transform 0.12s;
}
.rm-star-btn:hover,
.rm-star-btn.active {
  transform: scale(1.15);
}
.rm-rating-label {
  font-size: 13px;
  font-weight: 500;
  color: #f59e0b;
  margin-left: 6px;
  min-width: 72px;
}
.rm-textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  color: #1e293b;
  resize: vertical;
  transition:
    border-color 0.15s,
    box-shadow 0.15s;
  box-sizing: border-box;
  line-height: 1.6;
  background: #f8fafc;
}
.rm-textarea:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.12);
  background: #fff;
}
.rm-textarea--error {
  border-color: #ef4444;
}
.rm-textarea-footer {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.rm-char-count {
  font-size: 11px;
  color: #94a3b8;
}
.rm-upload-zone {
  border: 2px dashed #e2e8f0;
  border-radius: 10px;
  padding: 24px 16px;
  cursor: pointer;
  text-align: center;
  background: #f8fafc;
  transition:
    border-color 0.15s,
    background 0.15s;
}
.rm-upload-zone:hover,
.rm-upload-zone--drag {
  border-color: #48bb78;
  background: rgba(72, 187, 120, 0.04);
}
.rm-upload-icon {
  color: #94a3b8;
  margin: 0 auto 8px;
  display: block;
}
.rm-upload-text {
  font-size: 13px;
  color: #64748b;
  margin: 0 0 4px;
}
.rm-upload-text span {
  color: #48bb78;
  font-weight: 600;
}
.rm-upload-hint {
  font-size: 11px;
  color: #94a3b8;
  margin: 0;
}
.rm-img-preview {
  position: relative;
  display: inline-block;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}
.rm-preview-img {
  display: block;
  max-height: 160px;
  max-width: 100%;
  border-radius: 10px;
}
.rm-remove-img {
  position: absolute;
  top: 6px;
  right: 6px;
  background: rgba(0, 0, 0, 0.55);
  border: none;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  cursor: pointer;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;
}
.rm-remove-img:hover {
  background: rgba(239, 68, 68, 0.9);
}
.rm-hidden-input {
  display: none;
}
.rm-anon-row {
  background: #f8fafc;
  border-radius: 10px;
  padding: 14px;
}
.rm-toggle-label {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  user-select: none;
}
.rm-toggle {
  width: 40px;
  height: 22px;
  background: #e2e8f0;
  border-radius: 999px;
  position: relative;
  flex-shrink: 0;
  transition: background 0.2s;
}
.rm-toggle--on {
  background: #48bb78;
}
.rm-toggle-knob {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  transition: transform 0.2s;
}
.rm-toggle--on .rm-toggle-knob {
  transform: translateX(18px);
}
.rm-toggle-text {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}
.rm-toggle-sub {
  display: block;
  font-size: 11px;
  color: #94a3b8;
  margin-top: 2px;
}
.rm-error {
  font-size: 12px;
  color: #ef4444;
  margin: 0;
}
.rm-global-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #b91c1c;
}
.rm-footer {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid #f1f5f9;
  flex-shrink: 0;
}
.rm-btn {
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  font-weight: 600;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  padding: 9px 20px;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s;
}
.rm-btn--cancel {
  background: #f1f5f9;
  color: #64748b;
}
.rm-btn--cancel:hover {
  background: #e2e8f0;
}
.rm-btn--submit {
  background: #48bb78;
  color: #fff;
}
.rm-btn--submit:hover:not(:disabled) {
  background: #38a169;
}
.rm-btn--submit:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.rm-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  flex-shrink: 0;
}
.rm-spinner--dark {
  width: 20px;
  height: 20px;
  border: 2px solid #e2e8f0;
  border-top-color: #48bb78;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition:
    opacity 0.2s,
    transform 0.2s;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
.modal-fade-enter-from .rm-modal {
  transform: scale(0.96) translateY(8px);
}
.modal-fade-leave-to .rm-modal {
  transform: scale(0.96) translateY(8px);
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
@media (max-width: 560px) {
  .rm-modal {
    border-radius: 12px 12px 0 0;
    max-height: 95vh;
  }
  .rm-backdrop {
    align-items: flex-end;
    padding: 0;
  }
}
</style>
