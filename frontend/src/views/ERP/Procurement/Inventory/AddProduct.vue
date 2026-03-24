<template>
  <div class="product-layout">
    <main class="main-content">
      <!-- ── Header ── -->
      <header class="content-header">
        <div class="header-left">
          <button @click="goBack" class="btn-back">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M19 12H5M12 5l-7 7 7 7" />
            </svg>
          </button>
          <div>
            <h1 class="page-title">Add New Product</h1>
            <p class="page-subtitle">
              Fill in the details below to list a new product
            </p>
          </div>
        </div>
        <div class="header-actions">
          <button
            @click="saveDraft"
            class="btn-secondary"
            :disabled="isSubmitting"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path
                d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
              />
              <path
                d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
              />
            </svg>
            <span>Save as Draft</span>
          </button>
          <button
            @click="publishProduct"
            class="btn-primary"
            :disabled="isSubmitting"
          >
            <svg
              v-if="!isSubmitting"
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span class="btn-spinner" v-if="isSubmitting"></span>
            <span>{{
              isSubmitting ? "Publishing..." : "Publish Product"
            }}</span>
          </button>
        </div>
      </header>

      <!-- ── Progress steps ── -->
      <div class="progress-bar">
        <div
          class="progress-step"
          :class="{ done: filledSections >= 1, active: filledSections === 0 }"
        >
          <span class="step-dot"></span
          ><span class="step-label">Basic Info</span>
        </div>
        <div class="progress-line"></div>
        <div
          class="progress-step"
          :class="{ done: filledSections >= 2, active: filledSections === 1 }"
        >
          <span class="step-dot"></span><span class="step-label">Pricing</span>
        </div>
        <div class="progress-line"></div>
        <div
          class="progress-step"
          :class="{ done: filledSections >= 3, active: filledSections === 2 }"
        >
          <span class="step-dot"></span><span class="step-label">Stock</span>
        </div>
        <div class="progress-line"></div>
        <div
          class="progress-step"
          :class="{ done: filledSections >= 4, active: filledSections === 3 }"
        >
          <span class="step-dot"></span><span class="step-label">Media</span>
        </div>
      </div>

      <form @submit.prevent="publishProduct">
        <div class="form-columns">
          <!-- ══════════ LEFT COLUMN ══════════ -->
          <div class="col-left">
            <!-- Basic Information -->
            <div class="form-card" id="section-basic">
              <div class="card-header">
                <div class="card-header-icon">🌸</div>
                <div>
                  <h2 class="card-title">Basic Information</h2>
                  <p class="card-subtitle">
                    Name, description, and classification
                  </p>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label"
                  >Product Name <span class="req">*</span></label
                >
                <input
                  v-model="formData.product_name"
                  type="text"
                  placeholder="e.g., Red Rose Bouquet"
                  class="form-input"
                  :class="{ 'is-invalid': errors.product_name }"
                  @input="clearError('product_name')"
                />
                <span v-if="errors.product_name" class="error-text">{{
                  errors.product_name
                }}</span>
              </div>

              <div class="form-group">
                <label class="form-label"
                  >Description <span class="req">*</span></label
                >
                <textarea
                  v-model="formData.description"
                  placeholder="Describe your product — variety, size, arrangement style, care tips..."
                  rows="4"
                  class="form-textarea"
                  :class="{ 'is-invalid': errors.description }"
                  @input="clearError('description')"
                ></textarea>
                <span v-if="errors.description" class="error-text">{{
                  errors.description
                }}</span>
              </div>

              <div class="two-col">
                <div class="form-group">
                  <label class="form-label"
                    >SKU <span class="req">*</span></label
                  >
                  <input
                    v-model="formData.sku"
                    type="text"
                    placeholder="e.g., ROSE-RED-001"
                    class="form-input"
                    :class="{ 'is-invalid': errors.sku }"
                    @input="clearError('sku')"
                  />
                  <span v-if="errors.sku" class="error-text">{{
                    errors.sku
                  }}</span>
                  <span class="hint-text">Unique product identifier</span>
                </div>
                <div class="form-group">
                  <label class="form-label"
                    >Category <span class="req">*</span></label
                  >
                  <select
                    v-model="formData.category"
                    class="form-select"
                    :class="{ 'is-invalid': errors.category }"
                    @change="clearError('category')"
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
                  <label class="form-label"
                    >Flower Type <span class="req">*</span></label
                  >
                  <select
                    v-model="formData.flower_type"
                    class="form-select"
                    :class="{ 'is-invalid': errors.flower_type }"
                    @change="clearError('flower_type')"
                  >
                    <option value="">Select type</option>
                    <option value="focal">Focal (Main attraction)</option>
                    <option value="secondary">
                      Secondary (Support & volume)
                    </option>
                    <option value="filler">Filler (Small texture)</option>
                    <option value="line">Line (Height & direction)</option>
                    <option value="greenery">Greenery (Foliage)</option>
                  </select>
                  <span v-if="errors.flower_type" class="error-text">{{
                    errors.flower_type
                  }}</span>
                </div>
                <div class="form-group">
                  <label class="form-label"
                    >Color <span class="req">*</span></label
                  >
                  <select
                    v-model="formData.color"
                    class="form-select"
                    :class="{ 'is-invalid': errors.color }"
                    @change="handleColorChange"
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
                  <label class="form-label"
                    >Specify Color <span class="req">*</span></label
                  >
                  <input
                    v-model="formData.color_other"
                    type="text"
                    placeholder="e.g., Burgundy, Lavender"
                    class="form-input"
                    :class="{ 'is-invalid': errors.color_other }"
                    @input="clearError('color_other')"
                  />
                  <span v-if="errors.color_other" class="error-text">{{
                    errors.color_other
                  }}</span>
                </div>
                <div class="form-group">
                  <label class="form-label"
                    >Selling Type <span class="req">*</span></label
                  >
                  <select
                    v-model="formData.selling_type"
                    class="form-select"
                    :class="{ 'is-invalid': errors.selling_type }"
                    @change="clearError('selling_type')"
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
                </div>
              </div>
            </div>

            <!-- Pricing -->
            <div class="form-card" id="section-pricing">
              <div class="card-header">
                <div class="card-header-icon">💰</div>
                <div>
                  <h2 class="card-title">Pricing</h2>
                  <p class="card-subtitle">
                    Set your cost, selling price, and optional discount
                  </p>
                </div>
              </div>

              <div class="two-col">
                <div class="form-group">
                  <label class="form-label"
                    >Purchase Price <span class="req">*</span></label
                  >
                  <div class="input-prefix-wrap">
                    <span class="input-prefix">₱</span>
                    <input
                      v-model.number="formData.purchase_price"
                      type="number"
                      step="0.01"
                      min="0"
                      placeholder="0.00"
                      class="form-input has-prefix"
                      :class="{ 'is-invalid': errors.purchase_price }"
                      @input="clearError('purchase_price')"
                    />
                  </div>
                  <span v-if="errors.purchase_price" class="error-text">{{
                    errors.purchase_price
                  }}</span>
                  <span class="hint-text">Your acquisition cost</span>
                </div>
                <div class="form-group">
                  <label class="form-label"
                    >Selling Price <span class="req">*</span></label
                  >
                  <div class="input-prefix-wrap">
                    <span class="input-prefix">₱</span>
                    <input
                      v-model.number="formData.selling_price"
                      type="number"
                      step="0.01"
                      min="0"
                      placeholder="0.00"
                      class="form-input has-prefix"
                      :class="{ 'is-invalid': errors.selling_price }"
                      @input="clearError('selling_price')"
                    />
                  </div>
                  <span v-if="errors.selling_price" class="error-text">{{
                    errors.selling_price
                  }}</span>
                  <span class="hint-text">Price shown to customers</span>
                </div>
              </div>

              <!-- Profit pill -->
              <div
                class="profit-pill"
                :class="profitAmount >= 0 ? 'green' : 'red'"
              >
                <div class="profit-pill-left">
                  <span class="profit-label">Estimated Profit</span>
                  <span class="profit-value"
                    >₱{{ profitAmount.toFixed(2) }}</span
                  >
                </div>
                <div class="profit-pill-right">
                  <span class="profit-pct"
                    >{{ profitPercentage.toFixed(1) }}%</span
                  >
                  <span class="profit-pct-label">margin</span>
                </div>
              </div>

              <!-- Discount toggle -->
              <div
                class="discount-toggle-card"
                :class="{ active: formData.has_discount }"
              >
                <label class="toggle-switch">
                  <input
                    type="checkbox"
                    v-model="formData.has_discount"
                    @change="handleDiscountToggle"
                  />
                  <span class="toggle-track">
                    <span class="toggle-thumb"></span>
                  </span>
                </label>
                <div class="toggle-text">
                  <span class="toggle-main">Enable Discount Price</span>
                  <span class="toggle-sub"
                    >Display a sale price with the original crossed out</span
                  >
                </div>
                <transition name="fade"
                  ><span v-if="formData.has_discount" class="sale-badge"
                    >🏷️ On Sale</span
                  ></transition
                >
              </div>

              <transition name="slide-down">
                <div
                  v-if="formData.has_discount"
                  class="discount-fields two-col"
                >
                  <div class="form-group">
                    <label class="form-label"
                      >Discount Price <span class="req">*</span></label
                    >
                    <div class="input-prefix-wrap">
                      <span class="input-prefix">₱</span>
                      <input
                        v-model.number="formData.discount_price"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        class="form-input has-prefix"
                        :class="{ 'is-invalid': errors.discount_price }"
                        @input="clearError('discount_price')"
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
                    <label class="form-label">Discount %</label>
                    <div class="discount-pct-pill">
                      <span class="discount-pct-num"
                        >{{ discountPercentage.toFixed(1) }}%</span
                      >
                      <span class="discount-pct-label">off regular price</span>
                    </div>
                  </div>
                </div>
              </transition>
            </div>

            <!-- Stock -->
            <div class="form-card" id="section-stock">
              <div class="card-header">
                <div class="card-header-icon">📦</div>
                <div>
                  <h2 class="card-title">Stock Management</h2>
                  <p class="card-subtitle">Inventory levels and availability</p>
                </div>
              </div>

              <div class="two-col">
                <div class="form-group">
                  <label class="form-label"
                    >Quantity in Stock <span class="req">*</span></label
                  >
                  <input
                    v-model.number="formData.quantity_in_stock"
                    type="number"
                    min="0"
                    placeholder="0"
                    class="form-input"
                    :class="{ 'is-invalid': errors.quantity_in_stock }"
                    @input="clearError('quantity_in_stock')"
                  />
                  <span v-if="errors.quantity_in_stock" class="error-text">{{
                    errors.quantity_in_stock
                  }}</span>
                  <span class="hint-text">Current available units</span>
                </div>
                <div class="form-group">
                  <label class="form-label"
                    >Min Stock Level <span class="req">*</span></label
                  >
                  <input
                    v-model.number="formData.min_stock_level"
                    type="number"
                    min="0"
                    placeholder="0"
                    class="form-input"
                    :class="{ 'is-invalid': errors.min_stock_level }"
                    @input="clearError('min_stock_level')"
                  />
                  <span v-if="errors.min_stock_level" class="error-text">{{
                    errors.min_stock_level
                  }}</span>
                  <span class="hint-text">Low-stock alert threshold</span>
                </div>
                <div class="form-group">
                  <label class="form-label">Max Stock Level</label>
                  <input
                    v-model.number="formData.max_stock_level"
                    type="number"
                    min="0"
                    placeholder="0"
                    class="form-input"
                  />
                  <span class="hint-text">Prevent overstocking</span>
                </div>
                <div class="form-group">
                  <label class="form-label">Season</label>
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
                <div class="info-banner-icon">🏭</div>
                <div>
                  <strong>Storage & Freshness → Warehouse Module</strong>
                  <p>
                    Harvest dates, expiration, storage location, and batch
                    tracking are recorded when flowers physically arrive in the
                    Warehouse module.
                  </p>
                </div>
              </div>
            </div>

            <!-- Supplier -->
            <div class="form-card">
              <div class="card-header">
                <div class="card-header-icon">🚚</div>
                <div>
                  <h2 class="card-title">Supplier Information</h2>
                  <p class="card-subtitle">Where this product comes from</p>
                </div>
              </div>
              <div class="two-col">
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
                  <label class="form-label">Supplier SKU</label>
                  <input
                    v-model="formData.supplier_sku"
                    type="text"
                    placeholder="Supplier's product code"
                    class="form-input"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label">Lead Time (days)</label>
                  <input
                    v-model.number="formData.supplier_lead_time"
                    type="number"
                    min="0"
                    placeholder="0"
                    class="form-input"
                  />
                  <span class="hint-text">Time from order to receipt</span>
                </div>
              </div>
            </div>

            <!-- Additional Info -->
            <div class="form-card">
              <div class="card-header">
                <div class="card-header-icon">📝</div>
                <div>
                  <h2 class="card-title">Additional Information</h2>
                  <p class="card-subtitle">
                    Care instructions, tags, and special notes
                  </p>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Care Instructions</label>
                <textarea
                  v-model="formData.care_instructions"
                  placeholder="How to keep these flowers fresh — water, light, temperature..."
                  rows="3"
                  class="form-textarea"
                ></textarea>
              </div>

              <div class="form-group">
                <label class="form-label"
                  >Occasion Tags
                  <span class="optional-chip">optional · max 2</span></label
                >
                <div class="tag-grid">
                  <label
                    v-for="tag in occasionTags"
                    :key="tag"
                    class="tag-chip"
                    :class="{
                      selected: formData.occasion_tags.includes(tag),
                      disabled: isTagDisabled(tag),
                    }"
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
                <span class="hint-text" v-if="formData.occasion_tags.length"
                  >Selected: {{ formData.occasion_tags.join(", ") }}</span
                >
              </div>

              <div class="form-group">
                <label class="form-label">Additional Notes</label>
                <textarea
                  v-model="formData.notes"
                  placeholder="Limited edition, special handling, known issues..."
                  rows="3"
                  class="form-textarea"
                ></textarea>
              </div>

              <div class="checkbox-row">
                <label
                  class="checkbox-card"
                  :class="{ checked: formData.is_fragile }"
                >
                  <input type="checkbox" v-model="formData.is_fragile" />
                  <span class="checkbox-card-icon">⚠️</span>
                  <div>
                    <span class="checkbox-card-title">Fragile</span>
                    <span class="checkbox-card-sub">Handle with care</span>
                  </div>
                </label>
                <label
                  class="checkbox-card"
                  :class="{ checked: formData.requires_refrigeration }"
                >
                  <input
                    type="checkbox"
                    v-model="formData.requires_refrigeration"
                  />
                  <span class="checkbox-card-icon">❄️</span>
                  <div>
                    <span class="checkbox-card-title"
                      >Requires Refrigeration</span
                    >
                    <span class="checkbox-card-sub">Cold storage needed</span>
                  </div>
                </label>
              </div>
            </div>
          </div>
          <!-- /col-left -->

          <!-- ══════════ RIGHT COLUMN ══════════ -->
          <div class="col-right">
            <!-- 3D Model -->
            <div class="form-card sticky-card" id="section-media">
              <div class="card-header">
                <div class="card-header-icon">🎨</div>
                <div>
                  <h2 class="card-title">
                    3D Model <span class="optional-chip">optional</span>
                  </h2>
                  <p class="card-subtitle">Interactive view for customers</p>
                </div>
              </div>

              <div v-if="product3DModel" class="model-preview-box">
                <div class="model-file-info">
                  <div class="model-file-icon">🎨</div>
                  <div class="model-file-details">
                    <p class="model-file-name">
                      {{ product3DModel.file.name }}
                    </p>
                    <p class="model-file-meta">
                      {{ formatFileSize(product3DModel.file.size) }} ·
                      {{
                        product3DModel.file.name.split(".").pop().toUpperCase()
                      }}
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  @click="remove3DModel"
                  class="btn-remove-model"
                >
                  Remove
                </button>
              </div>

              <div
                v-else
                class="drop-zone model-drop"
                @click="trigger3DFileInput"
                @dragover.prevent
                @dragenter="model3DDragover = true"
                @dragleave="model3DDragover = false"
                @drop.prevent="handle3DFileDrop"
                :class="{ dragging: model3DDragover }"
              >
                <div class="drop-zone-icon">🎨</div>
                <p class="drop-zone-title">Drop 3D model here</p>
                <p class="drop-zone-sub">GLB · GLTF · OBJ · FBX</p>
                <span class="drop-zone-btn">Browse file</span>
                <p class="drop-zone-limit">Max 50 MB</p>
              </div>
              <input
                ref="modelFileInput"
                type="file"
                accept=".glb,.gltf,.obj,.fbx"
                @change="handle3DFileSelect"
                style="display: none"
              />
            </div>

            <!-- Product Images -->
            <div class="form-card">
              <div class="card-header">
                <div class="card-header-icon">📷</div>
                <div>
                  <h2 class="card-title">Product Images</h2>
                  <p class="card-subtitle">Up to 5 photos · first is primary</p>
                </div>
              </div>

              <div class="image-grid">
                <!-- existing images -->
                <div
                  v-for="(image, idx) in productImages"
                  :key="idx"
                  class="img-thumb"
                  :class="{ 'is-primary': idx === 0 }"
                >
                  <img :src="image.url" alt="product" />
                  <button
                    type="button"
                    class="img-remove"
                    @click="removeImage(idx)"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="12"
                      height="12"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="3"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    >
                      <line x1="18" y1="6" x2="6" y2="18" />
                      <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                  </button>
                  <span v-if="idx === 0" class="img-primary-badge"
                    >Primary</span
                  >
                </div>

                <!-- upload slot -->
                <div
                  v-if="productImages.length < 5"
                  class="drop-zone img-drop"
                  @click="triggerFileInput"
                  @dragover.prevent
                  @dragenter="imgDragover = true"
                  @dragleave="imgDragover = false"
                  @drop.prevent="handleDrop"
                  :class="{ dragging: imgDragover }"
                >
                  <div class="drop-zone-icon" style="font-size: 28px">📷</div>
                  <p class="drop-zone-sub" style="margin: 4px 0 0">Add photo</p>
                  <p class="drop-zone-limit">
                    {{ 5 - productImages.length }} slot{{
                      5 - productImages.length !== 1 ? "s" : ""
                    }}
                    left
                  </p>
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
              <p class="hint-text" style="margin-top: 10px">
                JPEG, PNG, WEBP, GIF · Max 5 MB each
              </p>
            </div>

            <!-- Quick summary card -->
            <div class="summary-card">
              <h3 class="summary-title">Product Summary</h3>
              <div class="summary-row">
                <span>Name</span>
                <span>{{ formData.product_name || "—" }}</span>
              </div>
              <div class="summary-row">
                <span>SKU</span>
                <span class="mono">{{ formData.sku || "—" }}</span>
              </div>
              <div class="summary-row">
                <span>Category</span>
                <span>{{ formData.category || "—" }}</span>
              </div>
              <div class="summary-row">
                <span>Selling Price</span>
                <span class="green-val">{{
                  formData.selling_price
                    ? "₱" + parseFloat(formData.selling_price).toFixed(2)
                    : "—"
                }}</span>
              </div>
              <div
                class="summary-row"
                v-if="formData.has_discount && formData.discount_price"
              >
                <span>Discount Price</span>
                <span class="red-val"
                  >₱{{ parseFloat(formData.discount_price).toFixed(2) }}</span
                >
              </div>
              <div class="summary-row">
                <span>Stock</span>
                <span>{{ formData.quantity_in_stock }} units</span>
              </div>
              <div class="summary-row">
                <span>Images</span>
                <span>{{ productImages.length }} / 5</span>
              </div>
              <div class="summary-row">
                <span>3D Model</span>
                <span>{{ product3DModel ? "✅ Uploaded" : "None" }}</span>
              </div>
            </div>
          </div>
          <!-- /col-right -->
        </div>

        <!-- ── Bottom actions ── -->
        <div class="form-actions-bar">
          <button type="button" @click="goBack" class="btn-cancel">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
            Cancel
          </button>
          <div class="action-right">
            <button
              type="button"
              @click="saveDraft"
              class="btn-secondary"
              :disabled="isSubmitting"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="15"
                height="15"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path
                  d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
                />
                <path
                  d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                />
              </svg>
              <span v-if="isSubmitting && isDraft">Saving...</span>
              <span v-else>Save as Draft</span>
            </button>
            <button type="submit" class="btn-primary" :disabled="isSubmitting">
              <svg
                v-if="!isSubmitting"
                xmlns="http://www.w3.org/2000/svg"
                width="15"
                height="15"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <polyline points="20 6 9 17 4 12" />
              </svg>
              <span class="btn-spinner" v-if="isSubmitting && !isDraft"></span>
              <span v-if="isSubmitting && !isDraft">Publishing...</span>
              <span v-else>Publish Product</span>
            </button>
          </div>
        </div>
      </form>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../../../../composables/useAuth";
import DynamicSidebar from "../../../../layouts/Sidebar/DynamicSidebar.vue";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import api from "../../../../plugins/axios";

const router = useRouter();
const { user } = useAuth();
const fileInput = ref(null);
const modelFileInput = ref(null);
const isSubmitting = ref(false);
const isDraft = ref(false);
const product3DModel = ref(null);
const imgDragover = ref(false);
const model3DDragover = ref(false);
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

// ── Progress ─────────────────────────────────────────────────────────────
const filledSections = computed(() => {
  let n = 0;
  if (formData.product_name && formData.category && formData.flower_type) n++;
  if (formData.selling_price > 0 && formData.purchase_price > 0) n++;
  if (formData.quantity_in_stock >= 0 && formData.min_stock_level >= 0) n++;
  if (productImages.value.length > 0) n++;
  return n;
});

// ── Computed ──────────────────────────────────────────────────────────────
const profitAmount = computed(
  () =>
    (parseFloat(formData.selling_price) || 0) -
    (parseFloat(formData.purchase_price) || 0),
);
const profitPercentage = computed(() => {
  const p = parseFloat(formData.purchase_price) || 0;
  return p === 0 ? 0 : (profitAmount.value / p) * 100;
});
const discountPercentage = computed(() => {
  if (!formData.has_discount || !formData.discount_price) return 0;
  const s = parseFloat(formData.selling_price) || 0;
  const d = parseFloat(formData.discount_price) || 0;
  return s === 0 ? 0 : ((s - d) / s) * 100;
});

// ── Helpers ───────────────────────────────────────────────────────────────
const clearError = (f) => {
  if (errors[f]) delete errors[f];
};
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

// ── 3D Model ──────────────────────────────────────────────────────────────
const trigger3DFileInput = () => modelFileInput.value?.click();
const handle3DFileSelect = (e) => {
  const f = e.target.files[0];
  if (f) validate3DModel(f);
};
const handle3DFileDrop = (e) => {
  model3DDragover.value = false;
  const f = e.dataTransfer.files[0];
  if (f) validate3DModel(f);
};
const validate3DModel = (file) => {
  if (
    ![".glb", ".gltf", ".obj", ".fbx"].some((ext) =>
      file.name.toLowerCase().endsWith(ext),
    )
  ) {
    toast.error("Invalid format. Upload GLB, GLTF, OBJ, or FBX.");
    return;
  }
  if (file.size > 50 * 1024 * 1024) {
    toast.error("File exceeds 50 MB limit.");
    return;
  }
  product3DModel.value = {
    file,
    type: file.name.split(".").pop().toLowerCase(),
    size: file.size,
  };
  toast.success("3D model ready!");
};
const remove3DModel = () => {
  product3DModel.value = null;
  if (modelFileInput.value) modelFileInput.value.value = "";
};
const formatFileSize = (b) => {
  if (!b) return "0 B";
  const k = 1024,
    s = ["B", "KB", "MB", "GB"],
    i = Math.floor(Math.log(b) / Math.log(k));
  return parseFloat((b / Math.pow(k, i)).toFixed(2)) + " " + s[i];
};

// ── Images ────────────────────────────────────────────────────────────────
const triggerFileInput = () => fileInput.value?.click();
const handleFileSelect = (e) => addImages(Array.from(e.target.files));
const handleDrop = (e) => {
  imgDragover.value = false;
  addImages(Array.from(e.dataTransfer.files));
};
const addImages = (files) => {
  const imgs = files.filter((f) => f.type.startsWith("image/"));
  const rem = 5 - productImages.value.length;
  if (imgs.length > rem) {
    toast.info(`Only ${rem} slot(s) remaining.`);
    imgs.splice(rem);
  }
  imgs.forEach((file) => {
    const r = new FileReader();
    r.onload = (e) => productImages.value.push({ file, url: e.target.result });
    r.readAsDataURL(file);
  });
  if (fileInput.value) fileInput.value.value = "";
};
const removeImage = (i) => productImages.value.splice(i, 1);

// ── Validation ────────────────────────────────────────────────────────────
const validateForm = () => {
  Object.keys(errors).forEach((k) => delete errors[k]);
  let ok = true,
    first = null;

  const required = [
    { f: "product_name", l: "Product Name", v: formData.product_name },
    { f: "description", l: "Description", v: formData.description },
    { f: "sku", l: "SKU", v: formData.sku },
    { f: "category", l: "Category", v: formData.category },
    { f: "flower_type", l: "Flower Type", v: formData.flower_type },
    { f: "color", l: "Color", v: formData.color },
    { f: "selling_type", l: "Selling Type", v: formData.selling_type },
    {
      f: "purchase_price",
      l: "Purchase Price",
      v: formData.purchase_price,
      num: true,
    },
    {
      f: "selling_price",
      l: "Selling Price",
      v: formData.selling_price,
      num: true,
    },
    {
      f: "quantity_in_stock",
      l: "Quantity in Stock",
      v: formData.quantity_in_stock,
      num: true,
    },
    {
      f: "min_stock_level",
      l: "Min Stock Level",
      v: formData.min_stock_level,
      num: true,
    },
  ];
  for (const r of required) {
    if (r.num) {
      if (isNaN(parseFloat(r.v)) || parseFloat(r.v) < 0) {
        errors[r.f] = `${r.l} must be 0 or greater`;
        ok = false;
        if (!first) first = r.f;
      }
    } else if (!r.v?.toString().trim()) {
      errors[r.f] = `${r.l} is required`;
      ok = false;
      if (!first) first = r.f;
    }
  }
  if (formData.color === "other" && !formData.color_other?.trim()) {
    errors.color_other = "Please specify the color";
    ok = false;
    if (!first) first = "color_other";
  }
  const sp = parseFloat(formData.selling_price) || 0;
  const pp = parseFloat(formData.purchase_price) || 0;
  if (sp <= pp) {
    errors.selling_price = "Selling price must exceed purchase price";
    ok = false;
    if (!first) first = "selling_price";
  }
  if (formData.has_discount) {
    const dp = parseFloat(formData.discount_price) || 0;
    if (!dp || dp <= 0) {
      errors.discount_price = "Enter a valid discount price";
      ok = false;
      if (!first) first = "discount_price";
    } else if (dp >= sp) {
      errors.discount_price = "Discount price must be less than selling price";
      ok = false;
      if (!first) first = "discount_price";
    }
  }
  if (formData.occasion_tags.length > 2) {
    errors.occasion_tags = "Max 2 tags allowed";
    ok = false;
  }

  if (!ok && first) {
    setTimeout(() => {
      const el = document.querySelector(
        ".form-input.is-invalid, .form-select.is-invalid, .form-textarea.is-invalid",
      );
      el?.closest(".form-card")?.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });
      el?.focus();
    }, 80);
    toast.error(errors[first]);
  }
  return ok;
};

// ── Submit ────────────────────────────────────────────────────────────────
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
  formData.owner_id = user.value.id; // backend overrides for employees via ResolvesOwner trait

  try {
    const fd = new FormData();

    ["is_fragile", "requires_refrigeration", "has_discount"].forEach((k) =>
      fd.append(k, formData[k] ? "1" : "0"),
    );

    Object.entries(formData).forEach(([k, v]) => {
      if (
        [
          "is_fragile",
          "requires_refrigeration",
          "has_discount",
          "occasion_tags",
          "owner_id",
        ].includes(k)
      )
        return;
      fd.append(k, v === null || v === undefined ? "" : v.toString());
    });

    fd.append("owner_id", user.value.id.toString());
    formData.occasion_tags.forEach((t) => fd.append("occasion_tags[]", t));
    if (product3DModel.value?.file)
      fd.append("model_file", product3DModel.value.file);
    productImages.value.forEach((img) => {
      if (img.file) fd.append("images[]", img.file);
    });

    const res = await api.post("vendor/products", fd);
    if (res.data.success) {
      toast.success(res.data.message || "Product added successfully!");
      router.push("/vendor/products");
    }
  } catch (err) {
    if (err.response?.data?.errors) {
      Object.keys(errors).forEach((k) => delete errors[k]);
      Object.entries(err.response.data.errors).forEach(([k, v]) => {
        errors[k] = Array.isArray(v) ? v[0] : v;
      });
      const first = Object.keys(err.response.data.errors)[0];
      if (first) toast.error(err.response.data.errors[first][0]);
    } else {
      toast.error(err.response?.data?.message || "Failed to add product.");
    }
  } finally {
    isSubmitting.value = false;
    isDraft.value = false;
  }
};

const goBack = () => {
  if (confirm("Leave? Unsaved changes will be lost.")) router.back();
};

onMounted(() => {
  if (user.value) formData.owner_id = user.value.id;
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
}

/* ── Layout ── */
.product-layout {
  display: flex;
  min-height: 100vh;
  background: #f0f4f8;
}
.main-content {
  flex: 1;
  padding: 28px 32px 60px;
}

/* ── Page header ── */
.content-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  gap: 20px;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}
.btn-back {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  background: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
  transition: all 0.2s;
  flex-shrink: 0;
}
.btn-back:hover {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}
.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #1a202c;
  margin: 0;
  line-height: 1.2;
}
.page-subtitle {
  font-size: 13px;
  color: #94a3b8;
  margin: 2px 0 0;
}
.header-actions {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
}

/* ── Progress bar ── */
.progress-bar {
  display: flex;
  align-items: center;
  gap: 0;
  margin-bottom: 24px;
  background: white;
  border-radius: 12px;
  padding: 14px 24px;
  border: 1px solid #e2e8f0;
}
.progress-step {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
.step-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #cbd5e0;
  transition: all 0.3s;
}
.progress-step.done .step-dot {
  background: #48bb78;
  box-shadow: 0 0 0 3px #d1fae5;
}
.progress-step.active .step-dot {
  background: #48bb78;
  animation: pulse-dot 1.4s ease-in-out infinite;
}
@keyframes pulse-dot {
  0%,
  100% {
    box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.3);
  }
  50% {
    box-shadow: 0 0 0 6px rgba(72, 187, 120, 0.1);
  }
}
.step-label {
  font-size: 12px;
  font-weight: 600;
  color: #94a3b8;
}
.progress-step.done .step-label,
.progress-step.active .step-label {
  color: #48bb78;
}
.progress-line {
  flex: 1;
  height: 2px;
  background: #e2e8f0;
  margin: 0 12px;
}

/* ── Two-column layout ── */
.form-columns {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 20px;
  align-items: start;
}
.col-left {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.col-right {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.sticky-card {
  position: sticky;
  top: 20px;
}

/* ── Cards ── */
.form-card {
  background: white;
  border-radius: 16px;
  padding: 24px;
  border: 1px solid #e8edf2;
}
.card-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  margin-bottom: 22px;
  padding-bottom: 18px;
  border-bottom: 1px solid #f1f5f9;
}
.card-header-icon {
  width: 42px;
  height: 42px;
  background: #f0fff4;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  flex-shrink: 0;
}
.card-title {
  font-size: 16px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 2px;
}
.card-subtitle {
  font-size: 12.5px;
  color: #94a3b8;
  margin: 0;
}

/* ── Form elements ── */
.two-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 16px;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}
.req {
  color: #e53e3e;
}
.optional-chip {
  font-size: 11px;
  font-weight: 400;
  color: #9ca3af;
  background: #f1f5f9;
  padding: 2px 7px;
  border-radius: 20px;
  margin-left: 6px;
}
.form-input,
.form-select,
.form-textarea {
  padding: 10px 13px;
  border: 1.5px solid #e2e8f0;
  border-radius: 9px;
  font-size: 13.5px;
  color: #1a202c;
  background: white;
  font-family: inherit;
  transition:
    border-color 0.18s,
    box-shadow 0.18s;
}
.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.12);
}
.form-input.is-invalid,
.form-select.is-invalid,
.form-textarea.is-invalid {
  border-color: #f87171;
}
.form-textarea {
  resize: vertical;
  min-height: 80px;
  line-height: 1.6;
}
.error-text {
  font-size: 11.5px;
  color: #ef4444;
  font-weight: 500;
}
.hint-text {
  font-size: 11.5px;
  color: #9ca3af;
}

/* ── Input prefix ── */
.input-prefix-wrap {
  position: relative;
}
.input-prefix {
  position: absolute;
  left: 13px;
  top: 50%;
  transform: translateY(-50%);
  font-weight: 600;
  color: #94a3b8;
  font-size: 14px;
  pointer-events: none;
  z-index: 1;
}
.form-input.has-prefix {
  padding-left: 30px;
}

/* ── Profit pill ── */
.profit-pill {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border-radius: 12px;
  margin-top: 18px;
}
.profit-pill.green {
  background: linear-gradient(135deg, #d1fae5, #a7f3d0);
  border: 1px solid #6ee7b7;
}
.profit-pill.red {
  background: linear-gradient(135deg, #fee2e2, #fecaca);
  border: 1px solid #fca5a5;
}
.profit-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #047857;
  margin-bottom: 4px;
}
.profit-pill.red .profit-label {
  color: #b91c1c;
}
.profit-value {
  font-size: 22px;
  font-weight: 700;
  color: #065f46;
}
.profit-pill.red .profit-value {
  color: #991b1b;
}
.profit-pct {
  display: block;
  font-size: 20px;
  font-weight: 700;
  color: #065f46;
  text-align: right;
}
.profit-pill.red .profit-pct {
  color: #991b1b;
}
.profit-pct-label {
  display: block;
  font-size: 11px;
  color: #047857;
  text-align: right;
}
.profit-pill.red .profit-pct-label {
  color: #b91c1c;
}

/* ── Discount toggle ── */
.discount-toggle-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  margin-top: 16px;
  transition: all 0.2s;
  cursor: pointer;
}
.discount-toggle-card.active {
  border-color: #48bb78;
  background: #f0fff4;
}
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 46px;
  height: 26px;
  flex-shrink: 0;
}
.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}
.toggle-track {
  position: absolute;
  inset: 0;
  background: #cbd5e0;
  border-radius: 26px;
  transition: background 0.25s;
  cursor: pointer;
}
.toggle-track::after {
  content: "";
  position: absolute;
  width: 20px;
  height: 20px;
  left: 3px;
  top: 3px;
  background: white;
  border-radius: 50%;
  transition: transform 0.25s;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
}
.toggle-switch input:checked + .toggle-track {
  background: #48bb78;
}
.toggle-switch input:checked + .toggle-track::after {
  transform: translateX(20px);
}
.toggle-text {
  flex: 1;
}
.toggle-main {
  display: block;
  font-size: 13.5px;
  font-weight: 600;
  color: #1a202c;
}
.toggle-sub {
  display: block;
  font-size: 12px;
  color: #9ca3af;
  margin-top: 1px;
}
.sale-badge {
  padding: 5px 12px;
  background: #ef4444;
  color: white;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}
.discount-fields {
  margin-top: 14px;
}
.discount-pct-pill {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 14px;
  background: #fef9c3;
  border: 1px solid #fde68a;
  border-radius: 10px;
  min-height: 68px;
}
.discount-pct-num {
  font-size: 22px;
  font-weight: 700;
  color: #92400e;
}
.discount-pct-label {
  font-size: 12px;
  color: #b45309;
  margin-top: 2px;
}

/* ── Info banner ── */
.info-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-top: 20px;
  padding: 14px 18px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  border-left: 4px solid #3b82f6;
}
.info-banner-icon {
  font-size: 20px;
  flex-shrink: 0;
  margin-top: 1px;
}
.info-banner strong {
  display: block;
  font-size: 13px;
  color: #1e40af;
  margin-bottom: 4px;
}
.info-banner p {
  font-size: 12px;
  color: #2563eb;
  margin: 0;
  line-height: 1.6;
}

/* ── Tag grid ── */
.tag-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-top: 4px;
}
.tag-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  font-size: 12.5px;
  color: #4a5568;
  transition: all 0.15s;
  user-select: none;
}
.tag-chip input {
  display: none;
}
.tag-chip:hover:not(.disabled) {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}
.tag-chip.selected {
  border-color: #48bb78;
  background: #f0fff4;
  color: #22543d;
  font-weight: 600;
}
.tag-chip.selected::before {
  content: "✓ ";
}
.tag-chip.disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

/* ── Checkbox cards ── */
.checkbox-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 16px;
}
.checkbox-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
}
.checkbox-card input {
  display: none;
}
.checkbox-card:hover {
  border-color: #48bb78;
  background: #f9fffc;
}
.checkbox-card.checked {
  border-color: #48bb78;
  background: #f0fff4;
}
.checkbox-card-icon {
  font-size: 20px;
  flex-shrink: 0;
}
.checkbox-card-title {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #2d3748;
}
.checkbox-card-sub {
  display: block;
  font-size: 11.5px;
  color: #9ca3af;
}

/* ── Drop zones ── */
.drop-zone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 2px dashed #cbd5e0;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
  background: #f8fafc;
  text-align: center;
}
.drop-zone:hover,
.drop-zone.dragging {
  border-color: #48bb78;
  background: #f0fff4;
}
.model-drop {
  padding: 36px 24px;
}
.drop-zone-icon {
  font-size: 36px;
  margin-bottom: 10px;
}
.drop-zone-title {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 4px;
}
.drop-zone-sub {
  font-size: 12.5px;
  color: #94a3b8;
  margin: 0 0 12px;
}
.drop-zone-btn {
  display: inline-block;
  padding: 8px 20px;
  background: #48bb78;
  color: white;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
}
.drop-zone-limit {
  font-size: 11.5px;
  color: #94a3b8;
  margin: 10px 0 0;
}

/* ── 3D model preview ── */
.model-preview-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  background: #f7fafc;
  border-radius: 12px;
  border: 1.5px solid #e2e8f0;
}
.model-file-info {
  display: flex;
  align-items: center;
  gap: 12px;
}
.model-file-icon {
  font-size: 28px;
}
.model-file-name {
  font-size: 13.5px;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}
.model-file-meta {
  font-size: 12px;
  color: #94a3b8;
  margin: 2px 0 0;
}
.btn-remove-model {
  padding: 7px 14px;
  background: #fee2e2;
  color: #991b1b;
  border: none;
  border-radius: 7px;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: background 0.2s;
}
.btn-remove-model:hover {
  background: #fecaca;
}

/* ── Image grid ── */
.image-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}
.img-thumb {
  position: relative;
  aspect-ratio: 1;
  border-radius: 10px;
  overflow: hidden;
  border: 2px solid #e2e8f0;
  transition: border-color 0.2s;
}
.img-thumb.is-primary {
  border-color: #48bb78;
}
.img-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.img-remove {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 22px;
  height: 22px;
  background: rgba(239, 68, 68, 0.9);
  border: none;
  border-radius: 50%;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s;
}
.img-thumb:hover .img-remove {
  opacity: 1;
}
.img-primary-badge {
  position: absolute;
  bottom: 6px;
  left: 6px;
  padding: 2px 7px;
  background: #48bb78;
  color: white;
  font-size: 10px;
  font-weight: 700;
  border-radius: 4px;
}
.img-drop {
  aspect-ratio: 1;
  padding: 12px;
}

/* ── Summary card ── */
.summary-card {
  background: #1a202c;
  border-radius: 16px;
  padding: 22px;
}
.summary-title {
  font-size: 13px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0 0 14px;
}
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid #2d3748;
  font-size: 13px;
}
.summary-row:last-child {
  border-bottom: none;
}
.summary-row span:first-child {
  color: #94a3b8;
}
.summary-row span:last-child {
  color: #e2e8f0;
  font-weight: 500;
  text-align: right;
  max-width: 60%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.summary-row .green-val {
  color: #68d391;
  font-weight: 700;
}
.summary-row .red-val {
  color: #fc8181;
}
.summary-row .mono {
  font-family: monospace;
  font-size: 12px;
  color: #a0aec0;
}

/* ── Bottom actions bar ── */
.form-actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 24px;
  padding: 18px 24px;
  background: white;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
}
.action-right {
  display: flex;
  gap: 10px;
}

/* ── Buttons ── */
.btn-primary,
.btn-secondary,
.btn-cancel {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 22px;
  border-radius: 9px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  font-family: inherit;
  transition: all 0.2s;
}
.btn-primary {
  background: #48bb78;
  color: white;
}
.btn-primary:hover:not(:disabled) {
  background: #38a169;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(72, 187, 120, 0.35);
}
.btn-primary:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.btn-secondary {
  background: white;
  color: #374151;
  border: 1.5px solid #e2e8f0;
}
.btn-secondary:hover:not(:disabled) {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}
.btn-secondary:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.btn-cancel {
  background: white;
  color: #6b7280;
  border: 1.5px solid #e2e8f0;
}
.btn-cancel:hover {
  border-color: #f87171;
  color: #ef4444;
  background: #fff5f5;
}

/* ── Spinner ── */
.btn-spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* ── Transitions ── */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.25s ease;
  overflow: hidden;
}
.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  max-height: 0;
  margin-top: 0;
}
.slide-down-enter-to,
.slide-down-leave-from {
  opacity: 1;
  max-height: 400px;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* ── Responsive ── */
@media (max-width: 1280px) {
  .form-columns {
    grid-template-columns: 1fr 340px;
  }
}
@media (max-width: 1100px) {
  .main-content {
    margin-left: 0;
  }
}
@media (max-width: 960px) {
  .form-columns {
    grid-template-columns: 1fr;
  }
  .sticky-card {
    position: static;
  }
}
@media (max-width: 768px) {
  .main-content {
    padding: 16px;
  }
  .two-col {
    grid-template-columns: 1fr;
  }
  .tag-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .checkbox-row {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 600px) {
  .form-columns {
    grid-template-columns: 1fr;
  }
  .image-grid {
    grid-template-columns: repeat(3, 1fr);
  }
  .tag-grid {
    grid-template-columns: 1fr 1fr;
  }
  .form-actions-bar {
    flex-direction: column;
    gap: 12px;
  }
  .action-right {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
