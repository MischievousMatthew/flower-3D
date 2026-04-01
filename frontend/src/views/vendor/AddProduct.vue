<template>
  <vendorHeader />
  <div class="product-layout">
    <VendorSidebar />

    <main class="main-content">
      <!-- Header -->
      <header class="content-header">
        <div class="header-left">
          <button @click="goBack" class="btn-back"><span>←</span></button>
          <h1 class="page-title">Add New Product</h1>
        </div>
        <div class="header-actions">
          <button
            @click="saveDraft"
            class="btn-secondary"
            :disabled="isSubmitting"
          >
            <span>📝</span><span>Save as Draft</span>
          </button>
          <button
            @click="publishProduct"
            class="btn-primary"
            :disabled="isSubmitting"
          >
            <span>✅</span
            ><span>{{
              isSubmitting ? "Publishing..." : "Publish Product"
            }}</span>
          </button>
        </div>
      </header>

      <!-- Form -->
      <div class="form-container">
        <form @submit.prevent="publishProduct">
          <!-- Basic Information -->
          <div class="form-section">
            <h2 class="section-title">Basic Information</h2>
            <div class="form-grid">
              <div class="form-group full-width">
                <label class="form-label">Product Name *</label>
                <input
                  v-model="formData.product_name"
                  type="text"
                  placeholder="e.g., Red Rose Bouquet"
                  class="form-input"
                  :class="{ 'is-invalid': errors.product_name }"
                  @input="clearError('product_name')"
                  required
                />
                <span v-if="errors.product_name" class="error-text">{{
                  errors.product_name
                }}</span>
              </div>
              <div class="form-group full-width">
                <label class="form-label">Product Description *</label>
                <textarea
                  v-model="formData.description"
                  placeholder="Describe your product in detail..."
                  rows="4"
                  class="form-textarea"
                  :class="{ 'is-invalid': errors.description }"
                  @input="clearError('description')"
                  required
                ></textarea>
                <span v-if="errors.description" class="error-text">{{
                  errors.description
                }}</span>
              </div>
              <div class="form-group">
                <label class="form-label">SKU (Stock Keeping Unit) *</label>
                <input
                  v-model="formData.sku"
                  type="text"
                  placeholder="e.g., ROSE-RED-001"
                  class="form-input"
                  :class="{ 'is-invalid': errors.sku }"
                  @input="clearError('sku')"
                  required
                />
                <span v-if="errors.sku" class="error-text">{{
                  errors.sku
                }}</span>
                <span class="hint-text"
                  >Unique identifier for this product</span
                >
              </div>
              <div class="form-group">
                <label class="form-label">Category / Type *</label>
                <select
                  v-model="formData.category"
                  class="form-select"
                  :class="{ 'is-invalid': errors.category }"
                  @change="clearError('category')"
                  required
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
                <span v-if="errors.category" class="error-text">{{
                  errors.category
                }}</span>
              </div>
              <div class="form-group">
                <label class="form-label">Flower Type *</label>
                <select
                  v-model="formData.flower_type"
                  class="form-select"
                  :class="{ 'is-invalid': errors.flower_type }"
                  @change="clearError('flower_type')"
                  required
                >
                  <option value="">Select flower type</option>
                  <option value="focal">Focal Flowers (Main attraction)</option>
                  <option value="secondary">
                    Secondary Flowers (Support & volume)
                  </option>
                  <option value="filler">Filler Flowers (Small texture)</option>
                  <option value="line">
                    Line Flowers (Height & direction)
                  </option>
                  <option value="greenery">Greenery (Foliage)</option>
                </select>
                <span v-if="errors.flower_type" class="error-text">{{
                  errors.flower_type
                }}</span>
              </div>
              <div class="form-group">
                <label class="form-label">Color *</label>
                <select
                  v-model="formData.color"
                  class="form-select"
                  :class="{ 'is-invalid': errors.color }"
                  @change="handleColorChange"
                  required
                >
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
                <span v-if="errors.color" class="error-text">{{
                  errors.color
                }}</span>
              </div>
              <div class="form-group" v-if="formData.color === 'other'">
                <label class="form-label">Specify Color *</label>
                <input
                  v-model="formData.color_other"
                  type="text"
                  placeholder="e.g., Burgundy, Lavender"
                  class="form-input"
                  :class="{ 'is-invalid': errors.color_other }"
                  @input="clearError('color_other')"
                  required
                />
                <span v-if="errors.color_other" class="error-text">{{
                  errors.color_other
                }}</span>
              </div>
            </div>
          </div>

          <!-- Pricing Information -->
          <div class="form-section">
            <h2 class="section-title">Pricing Information</h2>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Purchase Price / Cost *</label>
                <div class="input-with-prefix">
                  <span class="prefix">₱</span>
                  <input
                    v-model.number="formData.purchase_price"
                    type="number"
                    step="0.01"
                    placeholder="0.00"
                    class="form-input"
                    :class="{ 'is-invalid': errors.purchase_price }"
                    @input="
                      clearError('purchase_price');
                      calculateProfit();
                    "
                    required
                  />
                </div>
                <span v-if="errors.purchase_price" class="error-text">{{
                  errors.purchase_price
                }}</span>
                <span class="hint-text">Your cost to acquire this product</span>
              </div>
              <div class="form-group">
                <label class="form-label">Selling Price / Retail Price *</label>
                <div class="input-with-prefix">
                  <span class="prefix">₱</span>
                  <input
                    v-model.number="formData.selling_price"
                    type="number"
                    step="0.01"
                    placeholder="0.00"
                    class="form-input"
                    :class="{ 'is-invalid': errors.selling_price }"
                    @input="
                      clearError('selling_price');
                      calculateProfit();
                    "
                    required
                  />
                </div>
                <span v-if="errors.selling_price" class="error-text">{{
                  errors.selling_price
                }}</span>
                <span class="hint-text">Price customers will pay</span>
              </div>
              <div class="form-group">
                <label class="form-label">Profit Margin</label>
                <div class="profit-display">
                  <div class="profit-amount">
                    ₱{{ profitAmount.toFixed(2) }}
                  </div>
                  <div class="profit-percentage">
                    {{ profitPercentage.toFixed(1) }}% margin
                  </div>
                </div>
              </div>

              <!-- ── Discount Toggle (matches Edit modal style) ── -->
              <div class="form-group full-width">
                <div
                  class="discount-toggle-row"
                  :class="{ active: formData.has_discount }"
                >
                  <label class="toggle-switch">
                    <input
                      type="checkbox"
                      v-model="formData.has_discount"
                      @change="handleDiscountToggle"
                    />
                    <span class="toggle-slider"></span>
                  </label>
                  <div class="toggle-label-group">
                    <span class="toggle-label-main">Enable Discount Price</span>
                    <span class="toggle-label-sub"
                      >Show a crossed-out original price and a lower sale
                      price</span
                    >
                  </div>
                  <span
                    v-if="formData.has_discount"
                    class="discount-active-pill"
                    >🏷️ Sale Active</span
                  >
                </div>
              </div>

              <transition name="slide-down">
                <div v-if="formData.has_discount">
                  <div class="form-group">
                    <label class="form-label">Discount Price *</label>
                    <div class="input-with-prefix">
                      <span class="prefix">₱</span>
                      <input
                        v-model.number="formData.discount_price"
                        type="number"
                        step="0.01"
                        placeholder="0.00"
                        class="form-input"
                        :class="{ 'is-invalid': errors.discount_price }"
                        @input="clearError('discount_price')"
                        :required="formData.has_discount"
                      />
                    </div>
                    <span v-if="errors.discount_price" class="error-text">{{
                      errors.discount_price
                    }}</span>
                    <span class="hint-text"
                      >Must be less than selling price</span
                    >
                  </div>
                  <div class="form-group">
                    <label class="form-label">Discount Percentage</label>
                    <div class="discount-display">
                      <div class="discount-amount">
                        {{ discountPercentage.toFixed(1) }}%
                      </div>
                      <div class="discount-text">off selling price</div>
                    </div>
                  </div>
                </div>
              </transition>
            </div>
          </div>

          <!-- Stock Management -->
          <div class="form-section">
            <h2 class="section-title">Stock Management</h2>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Quantity in Stock *</label>
                <input
                  v-model.number="formData.quantity_in_stock"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="form-input"
                  :class="{ 'is-invalid': errors.quantity_in_stock }"
                  @input="clearError('quantity_in_stock')"
                  required
                />
                <span v-if="errors.quantity_in_stock" class="error-text">{{
                  errors.quantity_in_stock
                }}</span>
                <span class="hint-text">Current available units</span>
              </div>
              <div class="form-group">
                <label class="form-label">Minimum Stock Level *</label>
                <input
                  v-model.number="formData.min_stock_level"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="form-input"
                  :class="{ 'is-invalid': errors.min_stock_level }"
                  @input="clearError('min_stock_level')"
                  required
                />
                <span v-if="errors.min_stock_level" class="error-text">{{
                  errors.min_stock_level
                }}</span>
                <span class="hint-text"
                  >Alert when stock reaches this level</span
                >
              </div>
              <div class="form-group">
                <label class="form-label">Maximum Stock Level</label>
                <input
                  v-model.number="formData.max_stock_level"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="form-input"
                />
                <span class="hint-text">Avoid overstocking</span>
              </div>
              <div class="form-group">
                <label class="form-label">Season Availability</label>
                <select v-model="formData.season" class="form-select">
                  <option value="all-year">All Year Round</option>
                  <option value="spring">Spring</option>
                  <option value="summer">Summer</option>
                  <option value="autumn">Autumn</option>
                  <option value="winter">Winter</option>
                </select>
              </div>
            </div>
            <div class="info-banner">
              <span class="info-icon">🏭</span>
              <div>
                <strong>Storage & Freshness managed by Warehouse</strong>
                <p>
                  Storage location, harvest dates, expiration dates, and batch
                  tracking are handled in the Warehouse module when flowers
                  physically arrive.
                </p>
              </div>
            </div>
          </div>

          <!-- Selling Type -->
          <div class="form-section">
            <h2 class="section-title">Selling Type</h2>
            <div class="form-grid">
              <div class="form-group full-width">
                <label class="form-label">How is this product sold? *</label>
                <select
                  v-model="formData.selling_type"
                  class="form-select"
                  :class="{ 'is-invalid': errors.selling_type }"
                  @change="clearError('selling_type')"
                  required
                >
                  <option value="per_piece">Per Piece</option>
                  <option value="per_piece_customizable">
                    Per Piece (Customizable)
                  </option>
                  <option value="bouquet">Bouquet</option>
                </select>
                <span v-if="errors.selling_type" class="error-text">{{
                  errors.selling_type
                }}</span>
                <span class="hint-text">
                  <template v-if="formData.selling_type === 'per_piece'"
                    >Single stem or piece sold as-is</template
                  >
                  <template
                    v-else-if="
                      formData.selling_type === 'per_piece_customizable'
                    "
                    >Customer can customize quantity, color, or
                    arrangement</template
                  >
                  <template v-else-if="formData.selling_type === 'bouquet'"
                    >Pre-arranged bouquet ready for sale</template
                  >
                </span>
              </div>
            </div>
          </div>

          <!-- Supplier Information -->
          <div class="form-section">
            <h2 class="section-title">Supplier Information</h2>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Supplier Name</label>
                <input
                  v-model="formData.supplier_name"
                  type="text"
                  placeholder="e.g., Garden Wholesale Inc."
                  class="form-input"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Supplier Contact</label>
                <input
                  v-model="formData.supplier_contact"
                  type="text"
                  placeholder="Phone or email"
                  class="form-input"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Supplier SKU / Code</label>
                <input
                  v-model="formData.supplier_sku"
                  type="text"
                  placeholder="Supplier's product code"
                  class="form-input"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Lead Time (Days)</label>
                <input
                  v-model.number="formData.supplier_lead_time"
                  type="number"
                  min="0"
                  placeholder="0"
                  class="form-input"
                />
                <span class="hint-text">Time to receive from supplier</span>
              </div>
            </div>
          </div>

          <!-- 3D Model -->
          <div class="form-section">
            <h2 class="section-title">
              3D Model <span class="optional-label">(Optional)</span>
            </h2>
            <div class="model-upload-section">
              <div v-if="product3DModel" class="model-preview-container">
                <div class="model-preview">
                  <div class="model-info">
                    <span class="model-icon">🎨</span>
                    <div class="model-details">
                      <p class="model-name">{{ product3DModel.file.name }}</p>
                      <p class="model-size">
                        {{ formatFileSize(product3DModel.file.size) }}
                      </p>
                      <p class="model-type">
                        {{
                          product3DModel.file.name
                            .split(".")
                            .pop()
                            .toUpperCase()
                        }}
                      </p>
                    </div>
                  </div>
                  <button
                    type="button"
                    @click="remove3DModel"
                    class="remove-model-btn"
                  >
                    ✕ Remove
                  </button>
                </div>
              </div>
              <div
                v-else
                class="model-upload-placeholder"
                @click="trigger3DFileInput"
                @dragover.prevent
                @drop.prevent="handle3DFileDrop"
              >
                <span class="upload-icon">🎨</span>
                <span class="upload-text">Upload 3D Model</span>
                <span class="upload-hint">GLB, GLTF, OBJ, or FBX format</span>
                <span class="upload-size-hint">Max 50MB</span>
              </div>
              <input
                ref="modelFileInput"
                type="file"
                accept=".glb,.gltf,.obj,.fbx"
                @change="handle3DFileSelect"
                style="display: none"
              />
              <p class="hint-text">
                Upload a 3D model to give customers an interactive view.
                Supported: GLB (recommended), GLTF, OBJ, FBX
              </p>
            </div>
          </div>

          <!-- Product Images -->
          <div class="form-section">
            <h2 class="section-title">Product Images</h2>
            <div class="image-upload-section">
              <div class="image-grid">
                <div
                  v-for="(image, index) in productImages"
                  :key="index"
                  class="image-preview"
                >
                  <img :src="image.url" alt="Product" />
                  <button
                    type="button"
                    @click="removeImage(index)"
                    class="remove-image-btn"
                  >
                    ✕
                  </button>
                  <div v-if="index === 0" class="primary-badge">Primary</div>
                </div>
                <div
                  v-if="productImages.length < 5"
                  class="image-upload-placeholder"
                  @click="triggerFileInput"
                  @dragover.prevent
                  @drop.prevent="handleDrop"
                >
                  <span class="upload-icon">📷</span>
                  <span class="upload-text">Add Photo</span>
                  <span class="upload-hint">Click or drag image here</span>
                </div>
              </div>
              <input
                ref="fileInput"
                type="file"
                accept="image/*"
                multiple
                @change="handleFileSelect"
                style="display: none"
              />
              <p class="hint-text">
                Upload up to 5 photos. First image will be the primary product
                image.
              </p>
            </div>
          </div>

          <!-- Additional Information -->
          <div class="form-section">
            <h2 class="section-title">Additional Information</h2>
            <div class="form-grid">
              <div class="form-group full-width">
                <label class="form-label">Care Instructions</label>
                <textarea
                  v-model="formData.care_instructions"
                  placeholder="How to care for these flowers..."
                  rows="3"
                  class="form-textarea"
                ></textarea>
              </div>
              <div class="form-group full-width">
                <label class="form-label"
                  >Occasion Tags
                  <span class="optional-label">(Select up to 2)</span></label
                >
                <div class="tag-selector">
                  <label
                    v-for="tag in occasionTags"
                    :key="tag"
                    class="tag-option"
                    :class="{ disabled: isTagDisabled(tag) }"
                  >
                    <input
                      type="checkbox"
                      :value="tag"
                      v-model="formData.occasion_tags"
                      :disabled="isTagDisabled(tag)"
                      @change="handleTagChange"
                    />
                    <span>{{ tag }}</span>
                  </label>
                </div>
                <span v-if="errors.occasion_tags" class="error-text">{{
                  errors.occasion_tags
                }}</span>
                <span class="hint-text" v-if="formData.occasion_tags.length > 0"
                  >Selected: {{ formData.occasion_tags.join(", ") }}</span
                >
              </div>
              <div class="form-group full-width">
                <label class="form-label">Additional Notes</label>
                <textarea
                  v-model="formData.notes"
                  placeholder="Any extra information (e.g., fragile, limited edition, special handling)..."
                  rows="3"
                  class="form-textarea"
                ></textarea>
              </div>
              <div class="form-group full-width">
                <label class="checkbox-label">
                  <input type="checkbox" v-model="formData.is_fragile" />
                  <span>⚠️ Fragile — Handle with Care</span>
                </label>
              </div>
              <div class="form-group full-width">
                <label class="checkbox-label">
                  <input
                    type="checkbox"
                    v-model="formData.requires_refrigeration"
                  />
                  <span>❄️ Requires Refrigeration</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="form-actions">
            <button type="button" @click="goBack" class="btn-cancel">
              Cancel
            </button>
            <div class="action-group">
              <button
                type="button"
                @click="saveDraft"
                class="btn-secondary"
                :disabled="isSubmitting"
              >
                <span v-if="isSubmitting && isDraft">Saving...</span>
                <span v-else>📝 Save as Draft</span>
              </button>
              <button
                type="submit"
                class="btn-primary"
                :disabled="isSubmitting"
              >
                <span v-if="isSubmitting && !isDraft">Publishing...</span>
                <span v-else>✅ Publish Product</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from "vue";
import { useRouter } from "vue-router";
import vendorHeader from "../../layouts/vendorHeader.vue";
import { useAuth } from "../../composables/useAuth";
import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";

import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import api from "../../plugins/axios";

const router = useRouter();
const { user } = useAuth();
const fileInput = ref(null);
const modelFileInput = ref(null);
const isSubmitting = ref(false);
const isDraft = ref(false);
const product3DModel = ref(null);
const errors = reactive({});

const formData = reactive({
  owner_id: null,
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
  supplier_lead_time: null,
  care_instructions: "",
  occasion_tags: [],
  notes: "",
  is_fragile: false,
  requires_refrigeration: false,
  status: "active",
});

const productImages = ref([]);

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

// ── Computed ───────────────────────────────────────────────────────────────

const profitAmount = computed(() => {
  const selling = parseFloat(formData.selling_price) || 0;
  const purchase = parseFloat(formData.purchase_price) || 0;
  return selling - purchase;
});
const profitPercentage = computed(() => {
  const purchase = parseFloat(formData.purchase_price) || 0;
  if (purchase === 0) return 0;
  return (profitAmount.value / purchase) * 100;
});
const discountPercentage = computed(() => {
  if (!formData.has_discount || !formData.discount_price) return 0;
  const selling = parseFloat(formData.selling_price) || 0;
  const discount = parseFloat(formData.discount_price) || 0;
  if (selling === 0) return 0;
  return ((selling - discount) / selling) * 100;
});

// ── Methods ────────────────────────────────────────────────────────────────

const calculateProfit = () => {}; // triggered by @input — computed handles the rest

const handleColorChange = () => {
  clearError("color");
  if (formData.color !== "other") {
    formData.color_other = "";
    clearError("color_other");
  }
};

const handleDiscountToggle = () => {
  if (!formData.has_discount) {
    formData.discount_price = null;
    clearError("discount_price");
  }
};

const handleTagChange = () => {
  if (formData.occasion_tags.length > 2) {
    formData.occasion_tags = formData.occasion_tags.slice(0, 2);
    toast.warning("You can only select up to 2 occasion tags");
  }
  clearError("occasion_tags");
};
const isTagDisabled = (tag) =>
  formData.occasion_tags.length >= 2 && !formData.occasion_tags.includes(tag);

// 3D model
const trigger3DFileInput = () => modelFileInput.value?.click();
const handle3DFileSelect = (e) => {
  const file = e.target.files[0];
  if (file) validate3DModel(file);
};
const handle3DFileDrop = (e) => {
  e.preventDefault();
  const file = e.dataTransfer.files[0];
  if (file) validate3DModel(file);
};
const validate3DModel = (file) => {
  const allowed = [".glb", ".gltf", ".obj", ".fbx"];
  const name = file.name.toLowerCase();
  if (!allowed.some((ext) => name.endsWith(ext))) {
    toast.error(
      "Invalid file format. Please upload GLB, GLTF, OBJ, or FBX file.",
    );
    return;
  }
  if (file.size > 50 * 1024 * 1024) {
    toast.error("File size too large. Maximum size is 50MB.");
    return;
  }
  product3DModel.value = { file, type: name.split(".").pop(), size: file.size };
  toast.success("3D model uploaded successfully!");
};
const remove3DModel = () => {
  product3DModel.value = null;
  if (modelFileInput.value) modelFileInput.value.value = "";
};
const formatFileSize = (bytes) => {
  if (bytes === 0) return "0 Bytes";
  const k = 1024,
    sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
};

// Images
const triggerFileInput = () => fileInput.value?.click();
const handleFileSelect = (e) => addImages(Array.from(e.target.files));
const handleDrop = (e) => {
  e.preventDefault();
  addImages(Array.from(e.dataTransfer.files));
};
const addImages = (files) => {
  const imageFiles = files.filter((f) => f.type.startsWith("image/"));
  const remaining = 5 - productImages.value.length;
  if (imageFiles.length > remaining) {
    toast.info(`You can only upload ${remaining} more image(s).`);
    imageFiles.splice(remaining);
  }
  imageFiles.forEach((file) => {
    const reader = new FileReader();
    reader.onload = (e) =>
      productImages.value.push({ file, url: e.target.result });
    reader.readAsDataURL(file);
  });
  if (fileInput.value) fileInput.value.value = "";
};
const removeImage = (index) => productImages.value.splice(index, 1);
const clearError = (field) => {
  if (errors[field]) delete errors[field];
};

const validateForm = () => {
  Object.keys(errors).forEach((k) => delete errors[k]);
  let isValid = true;
  let firstError = null;

  const required = [
    {
      field: "product_name",
      label: "Product Name",
      value: formData.product_name,
    },
    {
      field: "description",
      label: "Product Description",
      value: formData.description,
    },
    { field: "sku", label: "SKU", value: formData.sku },
    { field: "category", label: "Category", value: formData.category },
    { field: "flower_type", label: "Flower Type", value: formData.flower_type },
    { field: "color", label: "Color", value: formData.color },
    {
      field: "purchase_price",
      label: "Purchase Price",
      value: formData.purchase_price,
      type: "number",
    },
    {
      field: "selling_price",
      label: "Selling Price",
      value: formData.selling_price,
      type: "number",
    },
    {
      field: "quantity_in_stock",
      label: "Quantity in Stock",
      value: formData.quantity_in_stock,
      type: "number",
    },
    {
      field: "min_stock_level",
      label: "Min Stock Level",
      value: formData.min_stock_level,
      type: "number",
    },
    {
      field: "selling_type",
      label: "Selling Type",
      value: formData.selling_type,
    },
  ];

  for (const f of required) {
    if (f.type === "number") {
      if (isNaN(parseFloat(f.value)) || parseFloat(f.value) < 0) {
        errors[f.field] = `${f.label} is required and must be 0 or greater`;
        isValid = false;
        if (!firstError) firstError = f.field;
      }
    } else {
      if (!f.value || f.value.trim() === "") {
        errors[f.field] = `${f.label} is required`;
        isValid = false;
        if (!firstError) firstError = f.field;
      }
    }
  }

  if (
    formData.color === "other" &&
    (!formData.color_other || !formData.color_other.trim())
  ) {
    errors.color_other = "Please specify the color";
    isValid = false;
    if (!firstError) firstError = "color_other";
  }

  const selling = parseFloat(formData.selling_price) || 0;
  const purchase = parseFloat(formData.purchase_price) || 0;
  if (selling <= purchase) {
    errors.selling_price = "Selling price must be greater than purchase price";
    isValid = false;
    if (!firstError) firstError = "selling_price";
  }

  if (formData.has_discount) {
    const disc = parseFloat(formData.discount_price) || 0;
    if (!formData.discount_price || disc <= 0) {
      errors.discount_price =
        "Discount price is required when discount is enabled";
      isValid = false;
      if (!firstError) firstError = "discount_price";
    } else if (disc >= selling) {
      errors.discount_price = "Discount price must be less than selling price";
      isValid = false;
      if (!firstError) firstError = "discount_price";
    }
  }

  if (formData.occasion_tags.length > 2) {
    errors.occasion_tags = "You can only select up to 2 occasion tags";
    isValid = false;
  }

  if (!isValid && firstError) {
    setTimeout(() => {
      const el = document.querySelector(
        ".form-input.is-invalid, .form-select.is-invalid, .form-textarea.is-invalid",
      );
      if (el) {
        el.closest(".form-section")?.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
        el.focus();
      }
    }, 100);
    toast.error(errors[firstError]);
  }
  return isValid;
};

const saveDraft = async () => {
  isDraft.value = true;
  formData.status = "draft";
  await submitProduct();
};
const publishProduct = async () => {
  isDraft.value = false;
  formData.status = "active";
  await submitProduct();
};

const submitProduct = async () => {
  if (isSubmitting.value) return;
  if (!validateForm()) return;
  if (!user.value?.id) {
    toast.error("Please login to continue");
    router.push("/login");
    return;
  }

  isSubmitting.value = true;
  formData.owner_id = user.value.id;

  try {
    const submitData = new FormData();

    // Booleans
    submitData.append("is_fragile", formData.is_fragile ? "1" : "0");
    submitData.append(
      "requires_refrigeration",
      formData.requires_refrigeration ? "1" : "0",
    );
    submitData.append("has_discount", formData.has_discount ? "1" : "0");

    // All scalar fields
    const scalarFields = [
      "product_name",
      "description",
      "sku",
      "category",
      "flower_type",
      "color",
      "color_other",
      "purchase_price",
      "selling_price",
      "discount_price",
      "quantity_in_stock",
      "min_stock_level",
      "max_stock_level",
      "selling_type",
      "season",
      "supplier_name",
      "supplier_contact",
      "supplier_sku",
      "supplier_lead_time",
      "care_instructions",
      "notes",
      "status",
    ];

    scalarFields.forEach((key) => {
      const value = formData[key];
      if (value !== null && value !== undefined && value !== "") {
        submitData.append(key, value.toString());
      }
    });

    // owner_id
    submitData.append("owner_id", user.value.id.toString());

    // Occasion tags
    if (
      Array.isArray(formData.occasion_tags) &&
      formData.occasion_tags.length > 0
    ) {
      formData.occasion_tags.forEach((tag) => {
        submitData.append("occasion_tags[]", tag);
      });
    }

    // ✅ Images — log each one so we can confirm they're attached
    if (productImages.value.length > 0) {
      productImages.value.forEach((img, index) => {
        if (img.file instanceof File) {
          submitData.append("images[]", img.file, img.file.name);
          console.log(
            `Appending image[${index}]:`,
            img.file.name,
            img.file.size,
            img.file.type,
          );
        }
      });
    }

    // ✅ 3D model
    if (product3DModel.value?.file instanceof File) {
      submitData.append(
        "model_file",
        product3DModel.value.file,
        product3DModel.value.file.name,
      );
      console.log(
        "Appending model:",
        product3DModel.value.file.name,
        product3DModel.value.file.size,
      );
    }

    // ✅ Log FormData entries for debugging
    console.log("=== FormData entries ===");
    for (let [key, value] of submitData.entries()) {
      if (value instanceof File) {
        console.log(
          key,
          "→ File:",
          value.name,
          value.size + "bytes",
          value.type,
        );
      } else {
        console.log(key, "→", value);
      }
    }

    const response = await api.post("vendor/products", submitData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });

    if (response.data.success) {
      toast.success(response.data.message || "Product added successfully!");
      router.push("/vendor/products");
    }
  } catch (error) {
    console.error("Submit error:", error.response?.data);
    if (error.response?.data?.errors) {
      Object.keys(errors).forEach((k) => delete errors[k]);
      Object.entries(error.response.data.errors).forEach(([k, v]) => {
        errors[k] = Array.isArray(v) ? v[0] : v;
      });
      const first = Object.keys(error.response.data.errors)[0];
      if (first) toast.error(error.response.data.errors[first][0]);
    } else {
      toast.error(
        error.response?.data?.message ||
          "Failed to add product. Please try again.",
      );
    }
  } finally {
    isSubmitting.value = false;
    isDraft.value = false;
  }
};

const goBack = () => {
  if (confirm("Are you sure you want to leave? Unsaved changes will be lost."))
    router.back();
};

onMounted(() => {
  if (user.value) formData.owner_id = user.value.id;
});
</script>

<style scoped>
* {
  font-family: "Poppins", sans-serif;
  box-sizing: border-box;
}

.product-layout {
  display: flex;
  min-height: 100vh;
  background: #f8f9fa;
}

.main-content {
  margin-left: 260px;
  flex: 1;
  padding: 24px;
}

/* Header */
.content-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}
.btn-back {
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
  transition: all 0.2s;
}
.btn-back:hover {
  border-color: #48bb78;
  color: #48bb78;
}
.page-title {
  font-size: 28px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}
.header-actions {
  display: flex;
  gap: 12px;
}

/* Form */
.form-container {
  max-width: 1200px;
  margin: 0 auto;
}
.form-section {
  background: white;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 20px;
  border: 1px solid #e2e8f0;
}
.section-title {
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 20px 0;
}
.optional-label {
  font-size: 13px;
  font-weight: 400;
  color: #9ca3af;
  margin-left: 6px;
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.form-group.full-width {
  grid-column: 1 / -1;
}
.form-label {
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
}
.form-input,
.form-select,
.form-textarea {
  padding: 10px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  color: #2d3748;
  transition: all 0.2s;
  background: white;
  font-family: inherit;
}
.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}
.form-textarea {
  resize: vertical;
  min-height: 80px;
}
.error-text {
  color: #e53e3e;
  font-size: 12px;
}
.form-input.is-invalid,
.form-select.is-invalid,
.form-textarea.is-invalid {
  border-color: #e53e3e;
}
.hint-text {
  font-size: 12px;
  color: #9ca3af;
}

/* Info banner */
.info-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-top: 20px;
  padding: 14px 18px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  border-left: 4px solid #3b82f6;
}
.info-icon {
  font-size: 20px;
  flex-shrink: 0;
  margin-top: 1px;
}
.info-banner strong {
  display: block;
  font-size: 14px;
  color: #1e40af;
  margin-bottom: 4px;
}
.info-banner p {
  font-size: 13px;
  color: #3b82f6;
  margin: 0;
}

/* Input prefix */
.input-with-prefix {
  position: relative;
  display: flex;
  align-items: center;
}
.prefix {
  position: absolute;
  left: 14px;
  color: #9ca3af;
  font-weight: 500;
  z-index: 1;
  pointer-events: none;
}
.input-with-prefix .form-input {
  padding-left: 32px;
}

/* Profit display */
.profit-display {
  padding: 16px;
  background: #d1fae5;
  border-radius: 8px;
  border: 1px solid #a7f3d0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 64px;
}
.profit-amount {
  font-size: 24px;
  font-weight: 700;
  color: #065f46;
  margin-bottom: 4px;
}
.profit-percentage {
  font-size: 13px;
  color: #047857;
}

/* Discount display */
.discount-display {
  padding: 16px;
  background: #fef3c7;
  border-radius: 8px;
  border: 1px solid #fde68a;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 64px;
}
.discount-amount {
  font-size: 24px;
  font-weight: 700;
  color: #92400e;
  margin-bottom: 4px;
}
.discount-text {
  font-size: 13px;
  color: #b45309;
}

/* ─── Toggle Switch (matches edit modal) ──────────────── */
.discount-toggle-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  background: #f7fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  transition: all 0.2s;
}
.discount-toggle-row.active {
  border-color: #48bb78;
  background: #f0fff4;
}
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
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
  background: #cbd5e0;
  border-radius: 24px;
  transition: 0.3s;
}
.toggle-slider::before {
  content: "";
  position: absolute;
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background: white;
  border-radius: 50%;
  transition: 0.3s;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}
.toggle-switch input:checked + .toggle-slider {
  background: #48bb78;
}
.toggle-switch input:checked + .toggle-slider::before {
  transform: translateX(20px);
}
.toggle-label-group {
  flex: 1;
}
.toggle-label-main {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
}
.toggle-label-sub {
  display: block;
  font-size: 12px;
  color: #9ca3af;
  margin-top: 2px;
}
.discount-active-pill {
  padding: 5px 12px;
  background: #e53e3e;
  color: white;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}

/* Slide transition */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.25s ease;
  overflow: hidden;
}
.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  max-height: 0;
  transform: translateY(-8px);
}
.slide-down-enter-to,
.slide-down-leave-from {
  opacity: 1;
  max-height: 200px;
  transform: translateY(0);
}

/* 3D model */
.model-upload-section {
  width: 100%;
}
.model-preview-container {
  margin-bottom: 16px;
}
.model-preview {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: #f7fafc;
  border-radius: 12px;
  border: 2px solid #e2e8f0;
}
.model-info {
  display: flex;
  align-items: center;
  gap: 16px;
}
.model-icon {
  font-size: 32px;
}
.model-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.model-name {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}
.model-size,
.model-type {
  font-size: 12px;
  color: #718096;
  margin: 0;
}
.remove-model-btn {
  padding: 8px 16px;
  background: #fee2e2;
  color: #991b1b;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}
.remove-model-btn:hover {
  background: #fecaca;
}
.model-upload-placeholder {
  padding: 48px;
  border: 2px dashed #cbd5e0;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  background: #f7fafc;
}
.model-upload-placeholder:hover {
  border-color: #48bb78;
  background: #d1fae5;
}
.upload-icon {
  font-size: 40px;
  margin-bottom: 10px;
}
.upload-text {
  font-size: 15px;
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 6px;
}
.upload-hint {
  font-size: 13px;
  color: #718096;
  margin-bottom: 4px;
}
.upload-size-hint {
  font-size: 12px;
  color: #9ca3af;
}

/* Images */
.image-upload-section {
  width: 100%;
}
.image-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 16px;
  margin-bottom: 12px;
}
.image-preview {
  position: relative;
  aspect-ratio: 1;
  border-radius: 12px;
  overflow: hidden;
  border: 2px solid #e2e8f0;
  transition: all 0.2s;
}
.image-preview:hover {
  border-color: #48bb78;
}
.image-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.remove-image-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: none;
  background: rgba(239, 68, 68, 0.9);
  color: white;
  cursor: pointer;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: all 0.2s;
}
.image-preview:hover .remove-image-btn {
  opacity: 1;
}
.primary-badge {
  position: absolute;
  bottom: 8px;
  left: 8px;
  padding: 3px 8px;
  background: #48bb78;
  color: white;
  font-size: 10px;
  font-weight: 700;
  border-radius: 4px;
}
.image-upload-placeholder {
  aspect-ratio: 1;
  border: 2px dashed #cbd5e0;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  background: #f7fafc;
}
.image-upload-placeholder:hover {
  border-color: #48bb78;
  background: #d1fae5;
}

/* Tags */
.tag-selector {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  padding: 4px;
}
.tag-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 13px;
  background: white;
}
.tag-option:hover:not(.disabled) {
  border-color: #48bb78;
  background: #f0fdf4;
}
.tag-option.disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.tag-option input[type="checkbox"] {
  cursor: pointer;
  accent-color: #48bb78;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-size: 14px;
  color: #2d3748;
  padding: 8px;
  border-radius: 6px;
  transition: background-color 0.2s;
}
.checkbox-label:hover {
  background: #f7fafc;
}
.checkbox-label input[type="checkbox"] {
  cursor: pointer;
  width: 18px;
  height: 18px;
  accent-color: #48bb78;
}

/* Actions */
.form-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  margin-top: 20px;
}
.action-group {
  display: flex;
  gap: 12px;
}
.btn-primary,
.btn-secondary,
.btn-cancel {
  padding: 12px 24px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
  border: none;
  font-family: inherit;
}
.btn-primary {
  background: #48bb78;
  color: white;
}
.btn-primary:hover:not(:disabled) {
  background: #38a169;
  transform: translateY(-1px);
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-secondary {
  background: white;
  color: #2d3748;
  border: 1px solid #e2e8f0;
}
.btn-secondary:hover:not(:disabled) {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fdf4;
}
.btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-cancel {
  background: white;
  color: #6b7280;
  border: 1px solid #e2e8f0;
}
.btn-cancel:hover {
  border-color: #ef4444;
  color: #ef4444;
  background: #fff5f5;
}

@media (max-width: 1024px) {
  .main-content {
    margin-left: 0;
  }
  .form-grid {
    grid-template-columns: 1fr;
  }
  .tag-selector {
    grid-template-columns: repeat(2, 1fr);
  }
  .form-actions {
    flex-direction: column;
    gap: 16px;
  }
  .action-group {
    width: 100%;
    justify-content: space-between;
  }
}
@media (max-width: 768px) {
  .main-content {
    padding: 16px;
  }
  .form-section {
    padding: 16px;
  }
}
@media (max-width: 640px) {
  .page-title {
    font-size: 22px;
  }
  .action-group {
    flex-direction: column;
    gap: 8px;
  }
  .tag-selector {
    grid-template-columns: 1fr;
  }
}
</style>
