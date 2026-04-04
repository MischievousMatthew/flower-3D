<template>
  <vendorHeader title="Vendor Profile" />
  <div class="vendor-profile-layout">
    <!-- Sidebar -->
    <VendorSidebar />

    <!-- Main Content -->
    <main class="profile-main">
      <!-- Profile Completion Progress -->
      <div class="completion-card">
        <div class="completion-header">
          <div class="completion-title-section">
            <h3 class="completion-title">Profile Completion</h3>
            <p class="completion-subtitle">
              Complete your profile to unlock all features
            </p>
          </div>
          <div class="completion-percentage">
            {{ profileData.profile_completion_percentage || 0 }}%
          </div>
        </div>

        <div class="completion-bar-container">
          <div
            class="completion-bar-fill"
            :style="{
              width: (profileData.profile_completion_percentage || 0) + '%',
            }"
          ></div>
        </div>

        <div class="completion-items">
          <div
            class="completion-item"
            :class="{ completed: profileData.payment_details_completed }"
          >
            <span class="completion-icon">{{
              profileData.payment_details_completed ? "✓" : "○"
            }}</span>
            <span class="completion-text">Payment Details</span>
            <button
              v-if="!profileData.payment_details_completed"
              class="completion-action"
              @click="activeTab = 'payment'"
            >
              Complete →
            </button>
          </div>
          <div
            class="completion-item"
            :class="{ completed: profileData.product_details_completed }"
          >
            <span class="completion-icon">{{
              profileData.product_details_completed ? "✓" : "○"
            }}</span>
            <span class="completion-text">Product Details</span>
            <button
              v-if="!profileData.product_details_completed"
              class="completion-action"
              @click="activeTab = 'product'"
            >
              Complete →
            </button>
          </div>
          <div
            class="completion-item"
            :class="{ completed: profileData.delivery_details_completed }"
          >
            <span class="completion-icon">{{
              profileData.delivery_details_completed ? "✓" : "○"
            }}</span>
            <span class="completion-text">Delivery Details</span>
            <button
              v-if="!profileData.delivery_details_completed"
              class="completion-action"
              @click="activeTab = 'delivery'"
            >
              Complete →
            </button>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="profile-tabs">
        <button
          class="tab-button"
          :class="{ active: activeTab === 'general' }"
          @click="activeTab = 'general'"
        >
          <span class="tab-icon">🏪</span><span>General Info</span>
        </button>
        <button
          class="tab-button"
          :class="{ active: activeTab === 'payment' }"
          @click="activeTab = 'payment'"
        >
          <span class="tab-icon">💳</span><span>Payment Details</span>
          <span v-if="!profileData.payment_details_completed" class="tab-badge"
            >!</span
          >
        </button>
        <button
          class="tab-button"
          :class="{ active: activeTab === 'product' }"
          @click="activeTab = 'product'"
        >
          <span class="tab-icon">🌸</span><span>Product Details</span>
          <span v-if="!profileData.product_details_completed" class="tab-badge"
            >!</span
          >
        </button>
        <button
          class="tab-button"
          :class="{ active: activeTab === 'delivery' }"
          @click="activeTab = 'delivery'"
        >
          <span class="tab-icon">🚚</span><span>Delivery Details</span>
          <span v-if="!profileData.delivery_details_completed" class="tab-badge"
            >!</span
          >
        </button>
        <button
          class="tab-button"
          :class="{ active: activeTab === 'password' }"
          @click="activeTab = 'password'"
        >
          <span class="tab-icon">🔒</span><span>Password</span>
        </button>
      </div>

      <!-- Loading Overlay -->
      <LoadingOverlay :visible="isLoading" :message="loadingMessage" />

      <!-- Tab Content -->
      <div v-if="!isLoading" class="tab-content">
        <!-- ══════════ General Info ══════════ -->
        <div v-if="activeTab === 'general'" class="tab-panel">
          <div class="panel-header">
            <h2 class="panel-title">General Information</h2>
            <p class="panel-description">
              Update your store details and contact information
            </p>
          </div>

          <!-- ── Alert banners ── -->
          <div class="logo-card">
            <div class="logo-card__preview">
              <img
                v-if="profileData.store_logo_url"
                :src="profileData.store_logo_url"
                alt="Store logo"
              />
              <div v-else class="logo-card__fallback">BC</div>
            </div>
            <div class="logo-card__content">
              <h3 class="logo-card__title">Store Logo</h3>
              <p class="logo-card__text">
                Update your store logo here. This uses the existing Cloudinary upload flow.
              </p>
            </div>
            <button
              type="button"
              class="btn-secondary logo-card__action"
              @click="openLogoUploadModal"
            >
              Change Logo
            </button>
          </div>

          <transition name="alert-slide">
            <div v-if="generalForm.successMsg" class="alert-banner success">
              <span class="alert-icon">✅</span>
              <span>{{ generalForm.successMsg }}</span>
              <button
                class="alert-dismiss"
                @click="generalForm.successMsg = ''"
              >
                ✕
              </button>
            </div>
          </transition>
          <transition name="alert-slide">
            <div v-if="generalForm.errorMsg" class="alert-banner error">
              <span class="alert-icon">❌</span>
              <span>{{ generalForm.errorMsg }}</span>
              <button class="alert-dismiss" @click="generalForm.errorMsg = ''">
                ✕
              </button>
            </div>
          </transition>

          <form @submit.prevent="updateGeneralInfo" class="profile-form">
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Store Name *</label>
                <input
                  type="text"
                  v-model="formData.general.store_name"
                  class="form-input"
                  :class="{ 'is-invalid': generalForm.errors.store_name }"
                  placeholder="Your store name"
                  required
                  @input="generalForm.clearError('store_name')"
                />
                <span
                  v-if="generalForm.errors.store_name"
                  class="field-error"
                  >{{ generalForm.errors.store_name }}</span
                >
              </div>
              <div class="form-group">
                <label class="form-label">Owner Name *</label>
                <input
                  type="text"
                  v-model="formData.general.owner_name"
                  class="form-input"
                  :class="{ 'is-invalid': generalForm.errors.owner_name }"
                  placeholder="Your full name"
                  required
                  @input="generalForm.clearError('owner_name')"
                />
                <span
                  v-if="generalForm.errors.owner_name"
                  class="field-error"
                  >{{ generalForm.errors.owner_name }}</span
                >
              </div>
              <div class="form-group full-width">
                <label class="form-label">Store Description</label>
                <textarea
                  v-model="formData.general.store_description"
                  class="form-input"
                  rows="4"
                  placeholder="Describe your store..."
                ></textarea>
              </div>
              <div class="form-group full-width">
                <label class="form-label">Store Address *</label>
                <input
                  type="text"
                  v-model="formData.general.store_address"
                  class="form-input"
                  :class="{ 'is-invalid': generalForm.errors.store_address }"
                  placeholder="Complete store address"
                  required
                  @input="generalForm.clearError('store_address')"
                />
                <span
                  v-if="generalForm.errors.store_address"
                  class="field-error"
                  >{{ generalForm.errors.store_address }}</span
                >
              </div>
              <div class="form-group">
                <label class="form-label">Contact Number *</label>
                <input
                  type="tel"
                  v-model="formData.general.contact_number"
                  class="form-input"
                  :class="{ 'is-invalid': generalForm.errors.contact_number }"
                  placeholder="+63 912 345 6789"
                  required
                  @input="generalForm.clearError('contact_number')"
                />
                <span
                  v-if="generalForm.errors.contact_number"
                  class="field-error"
                  >{{ generalForm.errors.contact_number }}</span
                >
              </div>
              <div class="form-group">
                <label class="form-label">Service Areas</label>
                <input
                  type="text"
                  v-model="formData.general.service_areas"
                  class="form-input"
                  placeholder="e.g., Manila, Quezon City"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Operating Hours</label>
                <input
                  type="text"
                  v-model="formData.general.operating_hours"
                  class="form-input"
                  placeholder="Mon-Fri: 9AM-6PM"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Position/Role</label>
                <input
                  type="text"
                  v-model="formData.general.position"
                  class="form-input"
                  placeholder="Owner, Manager, etc."
                />
              </div>
              <div class="form-group">
                <label class="form-label">Facebook Page</label>
                <input
                  type="url"
                  v-model="formData.general.facebook_page"
                  class="form-input"
                  placeholder="https://facebook.com/yourpage"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Instagram Page</label>
                <input
                  type="url"
                  v-model="formData.general.instagram_page"
                  class="form-input"
                  placeholder="https://instagram.com/yourpage"
                />
              </div>
            </div>
            <div class="form-actions">
              <button
                type="submit"
                class="btn-primary"
                :disabled="generalForm.isSubmitting"
              >
                <span v-if="generalForm.isSubmitting" class="btn-spinner-wrap"
                  ><span class="btn-spinner"></span> Saving...</span
                >
                <span v-else>Save Changes</span>
              </button>
            </div>
          </form>
        </div>

        <!-- ══════════ Payment Details ══════════ -->
        <div v-if="activeTab === 'payment'" class="tab-panel">
          <div class="panel-header">
            <h2 class="panel-title">Payment Details</h2>
            <p class="panel-description">
              Configure your payout and billing information
            </p>
          </div>

          <transition name="alert-slide">
            <div v-if="paymentForm.successMsg" class="alert-banner success">
              <span class="alert-icon">✅</span>
              <span>{{ paymentForm.successMsg }}</span>
              <button
                class="alert-dismiss"
                @click="paymentForm.successMsg = ''"
              >
                ✕
              </button>
            </div>
          </transition>
          <transition name="alert-slide">
            <div v-if="paymentForm.errorMsg" class="alert-banner error">
              <span class="alert-icon">❌</span>
              <span>{{ paymentForm.errorMsg }}</span>
              <button class="alert-dismiss" @click="paymentForm.errorMsg = ''">
                ✕
              </button>
            </div>
          </transition>

          <form @submit.prevent="updatePaymentDetails" class="profile-form">
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Payout Method *</label>
                <select
                  v-model="formData.payment.payout_method"
                  class="form-input"
                  required
                >
                  <option value="">Select payout method</option>
                  <option value="bank">Bank Transfer</option>
                  <option value="gcash">GCash</option>
                  <option value="maya">Maya</option>
                </select>
                <span
                  v-if="paymentForm.errors.payout_method"
                  class="field-error"
                  >{{ paymentForm.errors.payout_method }}</span
                >
              </div>
              <div class="form-group">
                <label class="form-label">Account Holder Name *</label>
                <input
                  type="text"
                  v-model="formData.payment.account_holder_name"
                  class="form-input"
                  :class="{
                    'is-invalid': paymentForm.errors.account_holder_name,
                  }"
                  placeholder="Full name on account"
                  required
                  @input="paymentForm.clearError('account_holder_name')"
                />
                <span
                  v-if="paymentForm.errors.account_holder_name"
                  class="field-error"
                  >{{ paymentForm.errors.account_holder_name }}</span
                >
              </div>
              <div class="form-group">
                <label class="form-label">Account Number *</label>
                <input
                  type="text"
                  v-model="formData.payment.account_number"
                  class="form-input"
                  :class="{ 'is-invalid': paymentForm.errors.account_number }"
                  placeholder="Account/mobile number"
                  required
                  @input="paymentForm.clearError('account_number')"
                />
                <span
                  v-if="paymentForm.errors.account_number"
                  class="field-error"
                  >{{ paymentForm.errors.account_number }}</span
                >
              </div>
              <div class="form-group">
                <label class="form-label">Bank/Provider Name *</label>
                <input
                  type="text"
                  v-model="formData.payment.bank_name"
                  class="form-input"
                  :class="{ 'is-invalid': paymentForm.errors.bank_name }"
                  :placeholder="
                    formData.payment.payout_method === 'bank'
                      ? 'e.g., BDO, BPI, Metrobank'
                      : 'e.g., GCash, Maya'
                  "
                  required
                  @input="paymentForm.clearError('bank_name')"
                />
                <span v-if="paymentForm.errors.bank_name" class="field-error">{{
                  paymentForm.errors.bank_name
                }}</span>
              </div>
              <div class="form-group full-width">
                <label class="form-label">Billing Address *</label>
                <textarea
                  v-model="formData.payment.billing_address"
                  class="form-input"
                  rows="3"
                  placeholder="Complete billing address"
                  required
                ></textarea>
                <span
                  v-if="paymentForm.errors.billing_address"
                  class="field-error"
                  >{{ paymentForm.errors.billing_address }}</span
                >
              </div>
            </div>
            <div class="info-box">
              <span class="info-icon">ℹ️</span>
              <p>
                Your payment information is stored securely and used to process
                vendor payouts and enable payment options for customers.
              </p>
            </div>
            <div class="form-actions">
              <button
                type="submit"
                class="btn-primary"
                :disabled="paymentForm.isSubmitting"
              >
                <span v-if="paymentForm.isSubmitting" class="btn-spinner-wrap"
                  ><span class="btn-spinner"></span> Saving...</span
                >
                <span v-else>Save Payment Details</span>
              </button>
            </div>
          </form>
        </div>

        <!-- ══════════ Product Details ══════════ -->
        <div v-if="activeTab === 'product'" class="tab-panel">
          <div class="panel-header">
            <h2 class="panel-title">Product Details</h2>
            <p class="panel-description">
              Specify the types of products you offer
            </p>
          </div>

          <transition name="alert-slide">
            <div v-if="productForm.successMsg" class="alert-banner success">
              <span class="alert-icon">✅</span>
              <span>{{ productForm.successMsg }}</span>
              <button
                class="alert-dismiss"
                @click="productForm.successMsg = ''"
              >
                ✕
              </button>
            </div>
          </transition>
          <transition name="alert-slide">
            <div v-if="productForm.errorMsg" class="alert-banner error">
              <span class="alert-icon">❌</span>
              <span>{{ productForm.errorMsg }}</span>
              <button class="alert-dismiss" @click="productForm.errorMsg = ''">
                ✕
              </button>
            </div>
          </transition>

          <form @submit.prevent="updateProductDetails" class="profile-form">
            <div class="form-grid">
              <div class="form-group full-width">
                <label class="form-label">Product Types *</label>
                <div class="checkbox-group">
                  <label
                    v-for="type in productTypeOptions"
                    :key="type.value"
                    class="checkbox-label"
                  >
                    <input
                      type="checkbox"
                      :value="type.value"
                      v-model="formData.product.product_types"
                      class="checkbox-input"
                    />
                    <span>{{ type.label }}</span>
                  </label>
                </div>
                <span
                  v-if="productForm.errors.product_types"
                  class="field-error"
                  >{{ productForm.errors.product_types }}</span
                >
              </div>
              <div class="form-group">
                <label class="form-label">Minimum Price (₱) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  v-model.number="formData.product.price_min"
                  class="form-input"
                  :class="{ 'is-invalid': productForm.errors.price_min }"
                  placeholder="e.g., 500.00"
                  required
                  @input="productForm.clearError('price_min')"
                />
                <span v-if="productForm.errors.price_min" class="field-error">{{
                  productForm.errors.price_min
                }}</span>
              </div>
              <div class="form-group">
                <label class="form-label">Maximum Price (₱) *</label>
                <input
                  type="number"
                  step="0.01"
                  :min="formData.product.price_min || 0"
                  v-model.number="formData.product.price_max"
                  class="form-input"
                  :class="{ 'is-invalid': productForm.errors.price_max }"
                  placeholder="e.g., 5000.00"
                  required
                  @input="productForm.clearError('price_max')"
                />
                <span v-if="productForm.errors.price_max" class="field-error">{{
                  productForm.errors.price_max
                }}</span>
              </div>
              <div class="form-group full-width">
                <label class="form-label">Same-Day Delivery *</label>
                <div class="radio-group">
                  <label class="radio-label">
                    <input
                      type="radio"
                      :value="true"
                      v-model="formData.product.same_day_delivery"
                      class="radio-input"
                    />
                    <span>Yes, I offer same-day delivery</span>
                  </label>
                  <label class="radio-label">
                    <input
                      type="radio"
                      :value="false"
                      v-model="formData.product.same_day_delivery"
                      class="radio-input"
                    />
                    <span>No, standard delivery only</span>
                  </label>
                </div>
              </div>
              <div class="form-group full-width">
                <label class="form-label">
                  Order Cutoff Times
                  <span class="label-hint"
                    >(Define cutoff times for different days)</span
                  >
                </label>
                <div class="cutoff-times-list">
                  <div
                    v-for="(cutoff, index) in formData.product.cutoff_times"
                    :key="index"
                    class="cutoff-time-item"
                  >
                    <select
                      v-model="cutoff.day"
                      class="form-input cutoff-day"
                      required
                    >
                      <option value="">Select Day</option>
                      <option value="Monday">Monday</option>
                      <option value="Tuesday">Tuesday</option>
                      <option value="Wednesday">Wednesday</option>
                      <option value="Thursday">Thursday</option>
                      <option value="Friday">Friday</option>
                      <option value="Saturday">Saturday</option>
                      <option value="Sunday">Sunday</option>
                    </select>
                    <input
                      type="time"
                      v-model="cutoff.time"
                      class="form-input cutoff-time"
                      required
                    />
                    <button
                      type="button"
                      @click="removeCutoffTime(index)"
                      class="btn-remove"
                      title="Remove"
                    >
                      ✕
                    </button>
                  </div>
                </div>
                <button
                  type="button"
                  @click="addCutoffTime"
                  class="btn-add-cutoff"
                >
                  <span class="add-icon">+</span> Add Cutoff Day
                </button>
              </div>
            </div>
            <div class="form-actions">
              <button
                type="submit"
                class="btn-primary"
                :disabled="productForm.isSubmitting"
              >
                <span v-if="productForm.isSubmitting" class="btn-spinner-wrap"
                  ><span class="btn-spinner"></span> Saving...</span
                >
                <span v-else>Save Product Details</span>
              </button>
            </div>
          </form>
        </div>

        <!-- ══════════ Delivery Details ══════════ -->
        <div v-if="activeTab === 'delivery'" class="tab-panel">
          <div class="panel-header">
            <h2 class="panel-title">Delivery Details</h2>
            <p class="panel-description">
              Configure your delivery and logistics settings
            </p>
          </div>

          <transition name="alert-slide">
            <div v-if="deliveryForm.successMsg" class="alert-banner success">
              <span class="alert-icon">✅</span>
              <span>{{ deliveryForm.successMsg }}</span>
              <button
                class="alert-dismiss"
                @click="deliveryForm.successMsg = ''"
              >
                ✕
              </button>
            </div>
          </transition>
          <transition name="alert-slide">
            <div v-if="deliveryForm.errorMsg" class="alert-banner error">
              <span class="alert-icon">❌</span>
              <span>{{ deliveryForm.errorMsg }}</span>
              <button class="alert-dismiss" @click="deliveryForm.errorMsg = ''">
                ✕
              </button>
            </div>
          </transition>

          <form @submit.prevent="updateDeliveryDetails" class="profile-form">
            <div class="form-grid">
              <div class="form-group full-width">
                <label class="form-label">Delivery Method</label>
                <div class="info-box" style="margin-top: 8px">
                  <span class="info-icon">✓</span>
                  <p>
                    All deliveries are handled by you (Self Delivery). Delivery
                    fees will be determined per order during checkout.
                  </p>
                </div>
                <input
                  type="hidden"
                  v-model="formData.delivery.delivery_handled_by"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Max Orders Per Day *</label>
                <input
                  type="number"
                  v-model.number="formData.delivery.max_orders_per_day"
                  class="form-input"
                  :class="{
                    'is-invalid': deliveryForm.errors.max_orders_per_day,
                  }"
                  placeholder="e.g., 20"
                  min="1"
                  required
                  @input="deliveryForm.clearError('max_orders_per_day')"
                />
                <span
                  v-if="deliveryForm.errors.max_orders_per_day"
                  class="field-error"
                  >{{ deliveryForm.errors.max_orders_per_day }}</span
                >
              </div>
              <div class="form-group">
                <label class="form-label">Lead Time Required *</label>
                <input
                  type="text"
                  v-model="formData.delivery.lead_time"
                  class="form-input"
                  :class="{ 'is-invalid': deliveryForm.errors.lead_time }"
                  placeholder="e.g., 24 hours, 2 days"
                  required
                  @input="deliveryForm.clearError('lead_time')"
                />
                <span
                  v-if="deliveryForm.errors.lead_time"
                  class="field-error"
                  >{{ deliveryForm.errors.lead_time }}</span
                >
              </div>
              <div class="form-group full-width">
                <label class="form-label">Cancellation & Refund Policy *</label>
                <textarea
                  v-model="formData.delivery.cancellation_policy"
                  class="form-input"
                  rows="4"
                  placeholder="Describe your cancellation and refund policy..."
                  required
                ></textarea>
                <span
                  v-if="deliveryForm.errors.cancellation_policy"
                  class="field-error"
                  >{{ deliveryForm.errors.cancellation_policy }}</span
                >
              </div>
            </div>
            <div class="form-actions">
              <button
                type="submit"
                class="btn-primary"
                :disabled="deliveryForm.isSubmitting"
              >
                <span v-if="deliveryForm.isSubmitting" class="btn-spinner-wrap"
                  ><span class="btn-spinner"></span> Saving...</span
                >
                <span v-else>Save Delivery Details</span>
              </button>
            </div>
          </form>
        </div>

        <!-- ══════════ Change Password ══════════ -->
        <div v-if="activeTab === 'password'" class="tab-panel">
          <div class="panel-header">
            <h2 class="panel-title">Change Password</h2>
            <p class="panel-description">
              Keep your account secure with a strong password
            </p>
          </div>

          <transition name="alert-slide">
            <div v-if="passwordForm.successMsg" class="alert-banner success">
              <span class="alert-icon">✅</span>
              <span>{{ passwordForm.successMsg }}</span>
              <button
                class="alert-dismiss"
                @click="passwordForm.successMsg = ''"
              >
                ✕
              </button>
            </div>
          </transition>
          <transition name="alert-slide">
            <div v-if="passwordForm.errorMsg" class="alert-banner error">
              <span class="alert-icon">❌</span>
              <span>{{ passwordForm.errorMsg }}</span>
              <button class="alert-dismiss" @click="passwordForm.errorMsg = ''">
                ✕
              </button>
            </div>
          </transition>

          <div class="password-security-note">
            <span class="security-icon">🔒</span>
            <div>
              <strong>Keep your account secure</strong>
              <p>
                Use at least 8 characters including uppercase letters, numbers,
                and symbols.
              </p>
            </div>
          </div>

          <form @submit.prevent="changePassword" class="profile-form">
            <div class="form-grid password-grid">
              <div class="form-group full-width">
                <label class="form-label">Current Password *</label>
                <div class="input-password-wrap">
                  <input
                    v-model="passwordData.current_password"
                    :type="showPasswords.current ? 'text' : 'password'"
                    class="form-input"
                    :class="{
                      'is-invalid': passwordForm.errors.current_password,
                    }"
                    placeholder="Enter your current password"
                    @input="passwordForm.clearError('current_password')"
                  />
                  <button
                    type="button"
                    class="eye-btn"
                    @click="showPasswords.current = !showPasswords.current"
                  >
                    {{ showPasswords.current ? "🙈" : "👁️" }}
                  </button>
                </div>
                <span
                  v-if="passwordForm.errors.current_password"
                  class="field-error"
                  >{{ passwordForm.errors.current_password }}</span
                >
              </div>
              <div class="form-group">
                <label class="form-label">New Password *</label>
                <div class="input-password-wrap">
                  <input
                    v-model="passwordData.password"
                    :type="showPasswords.new ? 'text' : 'password'"
                    class="form-input"
                    :class="{ 'is-invalid': passwordForm.errors.password }"
                    placeholder="Minimum 8 characters"
                    @input="onNewPasswordInput"
                  />
                  <button
                    type="button"
                    class="eye-btn"
                    @click="showPasswords.new = !showPasswords.new"
                  >
                    {{ showPasswords.new ? "🙈" : "👁️" }}
                  </button>
                </div>
                <span v-if="passwordForm.errors.password" class="field-error">{{
                  passwordForm.errors.password
                }}</span>
                <div v-if="passwordData.password" class="strength-bar-wrap">
                  <div class="strength-bar">
                    <div
                      class="strength-fill"
                      :class="passwordStrength.level"
                      :style="{ width: passwordStrength.pct + '%' }"
                    ></div>
                  </div>
                  <span
                    class="strength-label"
                    :class="passwordStrength.level"
                    >{{ passwordStrength.label }}</span
                  >
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Confirm New Password *</label>
                <div class="input-password-wrap">
                  <input
                    v-model="passwordData.password_confirmation"
                    :type="showPasswords.confirm ? 'text' : 'password'"
                    class="form-input"
                    :class="{
                      'is-invalid': passwordForm.errors.password_confirmation,
                      'is-match':
                        passwordsMatch && passwordData.password_confirmation,
                    }"
                    placeholder="Repeat new password"
                    @input="passwordForm.clearError('password_confirmation')"
                  />
                  <button
                    type="button"
                    class="eye-btn"
                    @click="showPasswords.confirm = !showPasswords.confirm"
                  >
                    {{ showPasswords.confirm ? "🙈" : "👁️" }}
                  </button>
                </div>
                <span
                  v-if="passwordForm.errors.password_confirmation"
                  class="field-error"
                  >{{ passwordForm.errors.password_confirmation }}</span
                >
                <span
                  v-else-if="
                    passwordData.password_confirmation && passwordsMatch
                  "
                  class="match-hint"
                  >✅ Passwords match</span
                >
                <span
                  v-else-if="
                    passwordData.password_confirmation && !passwordsMatch
                  "
                  class="mismatch-hint"
                  >❌ Passwords do not match</span
                >
              </div>
            </div>
            <div class="form-actions">
              <button
                type="submit"
                class="btn-primary"
                :disabled="passwordForm.isSubmitting || !passwordsMatch"
              >
                <span v-if="passwordForm.isSubmitting" class="btn-spinner-wrap"
                  ><span class="btn-spinner"></span> Updating...</span
                >
                <span v-else>🔑 Update Password</span>
              </button>
            </div>
          </form>
        </div>
      </div>
      <!-- end tab-content -->
    </main>

    <!-- Store Logo Upload Modal -->
    <transition name="modal-fade">
      <div
        v-if="showLogoUploadModal"
        class="modal-overlay"
        @click="closeLogoUploadModal"
      >
        <div class="modal-container" @click.stop>
          <div class="modal-header">
            <h3 class="modal-title">Update Store Logo</h3>
            <button class="modal-close" @click="closeLogoUploadModal">✕</button>
          </div>
          <div class="modal-body">
            <div class="photo-upload-area" @click="$refs.logoInput.click()">
              <div v-if="logoPreview" class="photo-preview">
                <img :src="logoPreview" alt="Preview" />
              </div>
              <div v-else class="photo-placeholder">
                <span class="upload-icon">🏪</span>
                <p class="upload-text">Click to select logo</p>
                <p class="upload-hint">JPG, PNG • Max 2MB</p>
              </div>
            </div>
            <input
              type="file"
              ref="logoInput"
              @change="handleLogoSelect"
              accept="image/jpeg,image/jpg,image/png"
              class="hidden-input"
            />
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" @click="closeLogoUploadModal">
              Cancel
            </button>
            <button
              class="btn-primary"
              @click="uploadStoreLogo"
              :disabled="!selectedLogo || isUploading"
            >
              <span v-if="isUploading">Uploading...</span>
              <span v-else>Upload Logo</span>
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from "vue";
import vendorHeader from "../../layouts/vendorHeader.vue";
import { useRouter } from "vue-router";
import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";
import api from "../../plugins/axios";
import { toast } from "vue3-toastify";
import { useFormSubmit } from "../../composables/useFormSubmit";

const router = useRouter();

// Each tab gets its own isolated form state
const generalForm = useFormSubmit();
const paymentForm = useFormSubmit();
const productForm = useFormSubmit();
const deliveryForm = useFormSubmit();
const passwordForm = useFormSubmit();

// ── Page state ────────────────────────────────────────────────────────────
const activeTab = ref("general");
const isLoading = ref(false);
const loadingMessage = ref("");
const showLogoUploadModal = ref(false);
const selectedLogo = ref(null);
const logoPreview = ref(null);
const isUploading = ref(false);
const avatarDropdown = ref(null);
const showAvatarMenu = ref(false);

// ── Profile data ──────────────────────────────────────────────────────────
const profileData = reactive({
  store_name: "",
  store_description: "",
  store_address: "",
  service_areas: "",
  operating_hours: "",
  owner_name: "",
  position: "",
  contact_number: "",
  email: "",
  facebook_page: "",
  instagram_page: "",
  store_logo_url: null,
  profile_photo_url: null,
  payout_method: "",
  account_holder_name: "",
  account_number: "",
  bank_name: "",
  billing_address: "",
  product_types: [],
  price_min: null,
  price_max: null,
  same_day_delivery: null,
  cutoff_times: [],
  delivery_handled_by: "self",
  max_orders_per_day: null,
  lead_time: "",
  cancellation_policy: "",
  payment_details_completed: false,
  product_details_completed: false,
  delivery_details_completed: false,
  profile_fully_completed: false,
  profile_completion_percentage: 0,
});

// ── Form data ─────────────────────────────────────────────────────────────
const formData = reactive({
  general: {
    store_name: "",
    owner_name: "",
    store_description: "",
    store_address: "",
    contact_number: "",
    service_areas: "",
    operating_hours: "",
    position: "",
    facebook_page: "",
    instagram_page: "",
  },
  payment: {
    payout_method: "",
    account_holder_name: "",
    account_number: "",
    bank_name: "",
    billing_address: "",
  },
  product: {
    product_types: [],
    price_min: null,
    price_max: null,
    same_day_delivery: null,
    cutoff_times: [],
  },
  delivery: {
    delivery_handled_by: "self",
    max_orders_per_day: null,
    lead_time: "",
    cancellation_policy: "",
  },
});

// ── Password ──────────────────────────────────────────────────────────────
const passwordData = reactive({
  current_password: "",
  password: "",
  password_confirmation: "",
});
const showPasswords = reactive({ current: false, new: false, confirm: false });

const productTypeOptions = [
  { label: "Bouquet", value: "bouquet" },
  { label: "Flower", value: "flower" },
  { label: "Bouquet and Flower", value: "bouquet_and_flower" },
];

// ── Computed ──────────────────────────────────────────────────────────────
const passwordsMatch = computed(
  () =>
    !!passwordData.password &&
    !!passwordData.password_confirmation &&
    passwordData.password === passwordData.password_confirmation,
);

const passwordStrength = computed(() => {
  const pw = passwordData.password;
  if (!pw) return { level: "", label: "", pct: 0 };
  let score = 0;
  if (pw.length >= 8) score++;
  if (pw.length >= 12) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  if (score <= 1) return { level: "weak", label: "Weak", pct: 25 };
  if (score <= 2) return { level: "fair", label: "Fair", pct: 50 };
  if (score <= 3) return { level: "good", label: "Good", pct: 75 };
  return { level: "strong", label: "Strong", pct: 100 };
});

const onNewPasswordInput = () => {
  passwordForm.clearError("password");
  passwordForm.clearError("password_confirmation");
};

// ── Fetch profile ─────────────────────────────────────────────────────────
const fetchProfile = async () => {
  try {
    isLoading.value = true;
    loadingMessage.value = "Loading profile...";
    const response = await api.get("/vendor/profile");
    if (response.data.success) {
      const data = response.data.data;
      Object.assign(profileData, data);
      formData.general = {
        store_name: data.store_name || "",
        owner_name: data.owner_name || "",
        store_description: data.store_description || "",
        store_address: data.store_address || "",
        contact_number: data.contact_number || "",
        service_areas: data.service_areas || "",
        operating_hours: data.operating_hours || "",
        position: data.position || "",
        facebook_page: data.facebook_page || "",
        instagram_page: data.instagram_page || "",
      };
      formData.payment = {
        payout_method: data.payout_method || "",
        account_holder_name: data.account_holder_name || "",
        account_number: data.decrypted_account_number || "",
        bank_name: data.bank_name || "",
        billing_address: data.billing_address || "",
      };
      formData.product = {
        product_types: Array.isArray(data.product_types)
          ? data.product_types
          : [],
        price_min: data.price_min || null,
        price_max: data.price_max || null,
        same_day_delivery: data.same_day_delivery ?? null,
        cutoff_times: Array.isArray(data.cutoff_times) ? data.cutoff_times : [],
      };
      formData.delivery = {
        delivery_handled_by: "self",
        max_orders_per_day: data.max_orders_per_day || null,
        lead_time: data.lead_time || "",
        cancellation_policy: data.cancellation_policy || "",
      };
    } else {
      toast.error(response.data.message || "Failed to load profile");
    }
  } catch (error) {
    console.error("Error fetching profile:", error);
    toast.error("Failed to load profile data");
  } finally {
    isLoading.value = false;
  }
};

// ── Update handlers ───────────────────────────────────────────────────────
const updateGeneralInfo = () =>
  generalForm.submit(
    () =>
      api.put("/vendor/profile/general-info", {
        store_name: (formData.general.store_name || "").trim(),
        store_description: (formData.general.store_description || "").trim(),
        store_address: (formData.general.store_address || "").trim(),
        service_areas: (formData.general.service_areas || "").trim(),
        operating_hours: (formData.general.operating_hours || "").trim(),
        owner_name: (formData.general.owner_name || "").trim(),
        position: (formData.general.position || "").trim(),
        contact_number: (formData.general.contact_number || "").trim(),
        facebook_page: (formData.general.facebook_page || "").trim(),
        instagram_page: (formData.general.instagram_page || "").trim(),
      }),
    {
      successMsg: "General info updated successfully!",
      onSuccess: (res) => {
        if (res.data?.data) Object.assign(profileData, res.data.data);
        toast.success("Profile updated!");
      },
    },
  );

const updatePaymentDetails = () =>
  paymentForm.submit(
    () =>
      api.put("/vendor/profile/payment-details", {
        payout_method: (formData.payment.payout_method || "").trim(),
        account_holder_name: (formData.payment.account_holder_name || "").trim(),
        account_number: (formData.payment.account_number || "").trim(),
        bank_name: (formData.payment.bank_name || "").trim(),
        billing_address: (formData.payment.billing_address || "").trim(),
      }),
    {
      successMsg: "Payment details updated successfully!",
      onSuccess: (res) => {
        if (res.data?.data) Object.assign(profileData, res.data.data);
        toast.success("Payment details saved!");
      },
    },
  );

const updateProductDetails = () => {
  if (!formData.product.product_types.length) {
    toast.error("Please select at least one product type");
    return;
  }
  if (!formData.product.price_min || !formData.product.price_max) {
    toast.error("Please enter both minimum and maximum prices");
    return;
  }
  if (formData.product.price_max < formData.product.price_min) {
    toast.error("Maximum price must be ≥ minimum price");
    return;
  }
  productForm.submit(
    () =>
      api.put("/vendor/profile/product-details", {
        product_types: Array.isArray(formData.product.product_types)
          ? formData.product.product_types
          : [],
        price_min: formData.product.price_min,
        price_max: formData.product.price_max,
        same_day_delivery: formData.product.same_day_delivery,
        cutoff_times: Array.isArray(formData.product.cutoff_times)
          ? formData.product.cutoff_times
              .map((cutoff) => ({
                day: (cutoff?.day || "").trim(),
                time: (cutoff?.time || "").trim(),
              }))
              .filter((cutoff) => cutoff.day && cutoff.time)
          : [],
      }),
    {
      successMsg: "Product details updated successfully!",
      onSuccess: (res) => {
        if (res.data?.data) Object.assign(profileData, res.data.data);
        toast.success("Product details saved!");
      },
    },
  );
};

const updateDeliveryDetails = () =>
  deliveryForm.submit(
    () =>
      api.put("/vendor/profile/delivery-details", {
        delivery_handled_by: (formData.delivery.delivery_handled_by || "self").trim(),
        max_orders_per_day: formData.delivery.max_orders_per_day,
        lead_time: (formData.delivery.lead_time || "").trim(),
        cancellation_policy: (formData.delivery.cancellation_policy || "").trim(),
      }),
    {
      successMsg: "Delivery details updated successfully!",
      onSuccess: (res) => {
        if (res.data?.data) Object.assign(profileData, res.data.data);
        toast.success("Delivery details saved!");
      },
    },
  );

const changePassword = () => {
  if (!passwordData.current_password) {
    passwordForm.errors.current_password = "Current password is required";
    return;
  }
  if (!passwordData.password || passwordData.password.length < 8) {
    passwordForm.errors.password = "New password must be at least 8 characters";
    return;
  }
  if (!passwordsMatch.value) {
    passwordForm.errors.password_confirmation = "Passwords do not match";
    return;
  }
  passwordForm.submit(
    () =>
      api.put("/vendor/profile/change-password", {
        current_password: passwordData.current_password,
        password: passwordData.password,
        password_confirmation: passwordData.password_confirmation,
      }),
    {
      successMsg: "Password updated successfully!",
      onSuccess: () => {
        passwordData.current_password = "";
        passwordData.password = "";
        passwordData.password_confirmation = "";
        toast.success("Password changed!");
      },
    },
  );
};

// ── Cutoff times ──────────────────────────────────────────────────────────
const addCutoffTime = () =>
  formData.product.cutoff_times.push({ day: "", time: "" });
const removeCutoffTime = (index) =>
  formData.product.cutoff_times.splice(index, 1);

// ── Logo handling ─────────────────────────────────────────────────────────
const openLogoUploadModal = () => {
  showAvatarMenu.value = false;
  showLogoUploadModal.value = true;
};
const closeLogoUploadModal = () => {
  showLogoUploadModal.value = false;
  selectedLogo.value = null;
  logoPreview.value = null;
};
const handleLogoSelect = (event) => {
  const file = event.target.files[0];
  if (!file) return;
  if (!file.type.match(/image\/(jpeg|jpg|png)/)) {
    toast.error("Please select a JPG or PNG image");
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error("Image size must be less than 2MB");
    return;
  }
  selectedLogo.value = file;
  const reader = new FileReader();
  reader.onload = (e) => (logoPreview.value = e.target.result);
  reader.readAsDataURL(file);
};
const uploadStoreLogo = async () => {
  if (!selectedLogo.value) return;
  try {
    isUploading.value = true;
    const data = new FormData();
    data.append("store_logo", selectedLogo.value);
    const response = await api.post("/vendor/profile/store-logo", data, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    if (response.data.success) {
      Object.assign(profileData, response.data.data);
      toast.success("Store logo updated!");
      closeLogoUploadModal();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to upload logo");
  } finally {
    isUploading.value = false;
  }
};

const handleClickOutside = (event) => {
  if (avatarDropdown.value && !avatarDropdown.value.contains(event.target))
    showAvatarMenu.value = false;
};

onMounted(() => {
  fetchProfile();
  document.addEventListener("click", handleClickOutside);
});
onUnmounted(() => document.removeEventListener("click", handleClickOutside));
</script>

<style scoped>
* {
  font-family: "Poppins", sans-serif;
  box-sizing: border-box;
}

.vendor-profile-layout {
  display: flex;
  min-height: 100vh;
  background: linear-gradient(135deg, #f5f7fa 0%, #e8f0f7 100%);
}
.profile-main {
  margin-left: 260px;
  flex: 1;
  padding: 28px;
  max-width: 1400px;
}

/* Completion card */
.completion-card {
  background: white;
  border-radius: 16px;
  padding: 28px;
  margin-bottom: 28px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  border: 1px solid #e2e8f0;
}

.logo-card {
  display: grid;
  grid-template-columns: 88px 1fr auto;
  gap: 18px;
  align-items: center;
  padding: 20px;
  margin-bottom: 22px;
  border: 1px solid #dbe7f3;
  border-radius: 16px;
  background: linear-gradient(135deg, #f8fffb 0%, #eef7ff 100%);
}

.logo-card__preview {
  width: 88px;
  height: 88px;
  border-radius: 20px;
  overflow: hidden;
  background: #ffffff;
  border: 1px solid #d9e7ef;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-card__preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.logo-card__fallback {
  font-size: 28px;
  font-weight: 700;
  color: #2f855a;
}

.logo-card__title {
  margin: 0 0 6px;
  font-size: 18px;
  color: #1f2937;
}

.logo-card__text {
  margin: 0;
  color: #5f6c7b;
  line-height: 1.5;
}

.logo-card__action {
  white-space: nowrap;
}
.completion-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.completion-title {
  font-size: 20px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 4px 0;
}
.completion-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}
.completion-percentage {
  font-size: 36px;
  font-weight: 800;
  background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.completion-bar-container {
  height: 10px;
  background: #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 20px;
}
.completion-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #48bb78 0%, #38a169 100%);
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
  border-radius: 10px;
  box-shadow: 0 0 10px rgba(72, 187, 120, 0.5);
}
.completion-items {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 14px;
}
.completion-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  background: #f8fafc;
  border-radius: 12px;
  border: 2px solid transparent;
  transition: all 0.3s;
}
.completion-item.completed {
  background: #d1fae5;
  border-color: #48bb78;
}
.completion-icon {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  background: #e2e8f0;
  color: #94a3b8;
  font-weight: 700;
  flex-shrink: 0;
}
.completion-item.completed .completion-icon {
  background: #48bb78;
  color: white;
}
.completion-text {
  flex: 1;
  font-size: 14px;
  font-weight: 600;
  color: #334155;
}
.completion-action {
  padding: 6px 14px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.completion-action:hover {
  background: #38a169;
  transform: translateX(2px);
}

/* Tabs */
.profile-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 28px;
  padding: 6px;
  background: white;
  border-radius: 14px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow-x: auto;
}
.tab-button {
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  background: transparent;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  white-space: nowrap;
  font-family: inherit;
}
.tab-button:hover {
  color: #334155;
  background: #f8fafc;
}
.tab-button.active {
  background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.25);
}
.tab-icon {
  font-size: 18px;
}
.tab-badge {
  width: 18px;
  height: 18px;
  background: #ef4444;
  color: white;
  border-radius: 50%;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tab-button.active .tab-badge {
  background: white;
  color: #48bb78;
}
.tab-content {
  animation: fadeInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Tab panel */
.tab-panel {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  border: 1px solid #e2e8f0;
}
.panel-header {
  margin-bottom: 32px;
  padding-bottom: 20px;
  border-bottom: 2px solid #e2e8f0;
}
.panel-title {
  font-size: 24px;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 8px 0;
}
.panel-description {
  font-size: 15px;
  color: #64748b;
  margin: 0;
}

/* Alert banners */
.alert-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 18px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 14px;
  font-weight: 500;
  border: 1px solid transparent;
}
.alert-banner.success {
  background: #d1fae5;
  color: #065f46;
  border-color: #a7f3d0;
}
.alert-banner.error {
  background: #fee2e2;
  color: #991b1b;
  border-color: #fca5a5;
}
.alert-icon {
  font-size: 18px;
  flex-shrink: 0;
}
.alert-banner span:nth-child(2) {
  flex: 1;
}
.alert-dismiss {
  margin-left: auto;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 16px;
  color: inherit;
  opacity: 0.6;
  padding: 2px 6px;
  border-radius: 4px;
  transition:
    opacity 0.2s,
    background 0.2s;
}
.alert-dismiss:hover {
  opacity: 1;
  background: rgba(0, 0, 0, 0.06);
}
.alert-slide-enter-active {
  transition: all 0.25s ease;
}
.alert-slide-leave-active {
  transition: all 0.2s ease;
}
.alert-slide-enter-from,
.alert-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
  max-height: 0;
}
.alert-slide-enter-to,
.alert-slide-leave-from {
  opacity: 1;
  transform: translateY(0);
  max-height: 80px;
}

/* Form */
.profile-form {
  max-width: 100%;
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
  margin-bottom: 28px;
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
  font-weight: 600;
  color: #334155;
}
.form-input {
  padding: 12px 16px;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  font-size: 15px;
  color: #1a202c;
  background: white;
  transition: all 0.3s;
  font-family: inherit;
  width: 100%;
}
.form-input:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 4px rgba(72, 187, 120, 0.1);
}
.form-input::placeholder {
  color: #cbd5e0;
}
.form-input.is-invalid {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}
.form-input.is-match {
  border-color: #48bb78;
}
textarea.form-input {
  resize: vertical;
  min-height: 100px;
}
select.form-input {
  cursor: pointer;
}
.field-error {
  font-size: 12px;
  color: #ef4444;
  font-weight: 500;
}

/* Checkbox / radio */
.checkbox-group,
.radio-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.checkbox-label,
.radio-label {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #f8fafc;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.3s;
  font-size: 14px;
  color: #334155;
}
.checkbox-label:hover,
.radio-label:hover {
  border-color: #48bb78;
  background: #f0fdf4;
}
.checkbox-input,
.radio-input {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: #48bb78;
}

/* Info box */
.info-box {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  margin-bottom: 24px;
}
.info-icon {
  font-size: 20px;
  flex-shrink: 0;
}
.info-box p {
  margin: 0;
  font-size: 14px;
  color: #1e40af;
  line-height: 1.6;
}

/* Form actions */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 24px;
  border-top: 1px solid #e2e8f0;
}
.btn-primary,
.btn-secondary {
  padding: 13px 32px;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border: none;
  font-family: inherit;
}
.btn-primary {
  background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.25);
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.35);
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}
.btn-secondary {
  background: white;
  color: #334155;
  border: 2px solid #e2e8f0;
}
.btn-secondary:hover {
  border-color: #48bb78;
  color: #48bb78;
}
.btn-spinner-wrap {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.btn-spinner {
  display: inline-block;
  width: 15px;
  height: 15px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Cutoff times */
.label-hint {
  font-size: 12px;
  font-weight: 400;
  color: #94a3b8;
  margin-left: 8px;
}
.cutoff-times-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 16px;
}
.cutoff-time-item {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  gap: 12px;
  align-items: center;
}
.btn-remove {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  border: 2px solid #fee2e2;
  background: #fef2f2;
  color: #ef4444;
  font-size: 18px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-remove:hover {
  background: #fecaca;
  border-color: #fca5a5;
}
.btn-add-cutoff {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  background: #f0fdf4;
  border: 2px dashed #48bb78;
  border-radius: 10px;
  color: #48bb78;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  width: fit-content;
  font-family: inherit;
}
.btn-add-cutoff:hover {
  background: #dcfce7;
  border-color: #38a169;
}
.add-icon {
  font-size: 18px;
  font-weight: 700;
}

/* Password tab */
.password-security-note {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 16px 18px;
  margin-bottom: 28px;
  background: #f0fdf4;
  border: 1px solid #a7f3d0;
  border-radius: 12px;
  border-left: 4px solid #48bb78;
}
.security-icon {
  font-size: 24px;
  flex-shrink: 0;
  margin-top: 1px;
}
.password-security-note strong {
  display: block;
  font-size: 15px;
  color: #065f46;
  margin-bottom: 3px;
}
.password-security-note p {
  font-size: 13px;
  color: #047857;
  margin: 0;
}
.password-grid {
  grid-template-columns: 1fr;
  max-width: 500px;
}
.input-password-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.input-password-wrap .form-input {
  padding-right: 48px;
}
.eye-btn {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  padding: 4px;
  line-height: 1;
}
.strength-bar-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 6px;
}
.strength-bar {
  flex: 1;
  height: 5px;
  background: #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
}
.strength-fill {
  height: 100%;
  border-radius: 10px;
  transition:
    width 0.4s,
    background 0.4s;
}
.strength-fill.weak {
  background: #ef4444;
}
.strength-fill.fair {
  background: #f59e0b;
}
.strength-fill.good {
  background: #3b82f6;
}
.strength-fill.strong {
  background: #48bb78;
}
.strength-label {
  font-size: 12px;
  font-weight: 700;
  min-width: 46px;
  text-align: right;
}
.strength-label.weak {
  color: #ef4444;
}
.strength-label.fair {
  color: #f59e0b;
}
.strength-label.good {
  color: #3b82f6;
}
.strength-label.strong {
  color: #48bb78;
}
.match-hint {
  font-size: 12px;
  color: #48bb78;
  font-weight: 600;
}
.mismatch-hint {
  font-size: 12px;
  color: #ef4444;
  font-weight: 600;
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
  z-index: 9999;
  padding: 20px;
  backdrop-filter: blur(4px);
}
.modal-container {
  background: white;
  border-radius: 20px;
  max-width: 480px;
  width: 100%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  overflow: hidden;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 28px;
  border-bottom: 1px solid #e2e8f0;
}
.modal-title {
  font-size: 20px;
  font-weight: 700;
  color: #1a202c;
  margin: 0;
}
.modal-close {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: #f1f5f9;
  color: #64748b;
  font-size: 20px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal-close:hover {
  background: #e2e8f0;
  color: #1a202c;
}
.modal-body {
  padding: 28px;
}
.photo-upload-area {
  width: 100%;
  aspect-ratio: 1;
  border: 3px dashed #cbd5e0;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s;
  overflow: hidden;
}
.photo-upload-area:hover {
  border-color: #48bb78;
  background: #f0fdf4;
}
.photo-preview {
  width: 100%;
  height: 100%;
}
.photo-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.photo-placeholder {
  text-align: center;
  padding: 20px;
}
.upload-icon {
  font-size: 64px;
  margin-bottom: 16px;
  display: block;
}
.upload-text {
  font-size: 16px;
  font-weight: 600;
  color: #334155;
  margin: 0 0 6px 0;
}
.upload-hint {
  font-size: 13px;
  color: #94a3b8;
  margin: 0;
}
.hidden-input {
  display: none;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 28px;
  border-top: 1px solid #e2e8f0;
}
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
  transform: scale(0.95);
}

@media (max-width: 1024px) {
  .profile-main {
    margin-left: 0;
    padding: 20px;
  }
  .form-grid {
    grid-template-columns: 1fr;
  }
  .completion-items {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 768px) {
  .profile-tabs {
    overflow-x: auto;
  }
  .tab-panel {
    padding: 20px;
  }
  .panel-title {
    font-size: 20px;
  }
  .cutoff-time-item {
    grid-template-columns: 1fr;
  }
  .password-grid {
    max-width: 100%;
  }
}
</style>
