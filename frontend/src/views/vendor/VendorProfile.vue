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
              @click="router.push({ name: 'VendorShopSettings' })"
            >
              Complete →
            </button>
          </div>
        </div>
      </div>

      <section v-if="profileData.subscription?.plan_name" class="subscription-card" aria-label="Current subscription">
        <div>
          <p class="subscription-card__eyebrow">Current subscription</p>
          <h2>{{ profileData.subscription.plan_name }} Plan</h2>
          <p v-if="profileData.subscription.status === 'trialing'">
            Free Trial · Ends {{ formatSubscriptionDate(profileData.subscription.trial_ends_at) }}
          </p>
          <p v-else>
            <template v-if="profileData.subscription.monthly_price !== null">
              {{ formatSubscriptionPrice(profileData.subscription.monthly_price, profileData.subscription.currency) }}/month ·
            </template>
            Status: {{ formatSubscriptionStatus(profileData.subscription.status) }}
          </p>
        </div>
        <span class="subscription-card__status" :class="`status-${profileData.subscription.status}`">
          {{ profileData.subscription.status === 'trialing' ? 'Trial' : formatSubscriptionStatus(profileData.subscription.status) }}
        </span>
        <router-link :to="{ name: 'VendorSubscription' }" class="subscription-card__manage">Manage plan →</router-link>
      </section>

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
        <!-- Delivery and password moved to Subscription & account -->
        <button
          class="tab-button"
          @click="router.push({ name: 'VendorShopSettings' })"
        >
          <span class="tab-icon">🚚</span><span>Delivery Details</span>
          <span v-if="!profileData.delivery_details_completed" class="tab-badge"
            >!</span
          >
        </button>
        <button
          class="tab-button"
          @click="router.push({ name: 'VendorSecurity' })"
        >
          <span class="tab-icon">🔒</span><span>Password &amp; Security</span>
        </button>
      </div>

      <!-- Loading skeleton, shaped like a tab panel with a form -->
      <div v-if="isLoading" class="tab-content profile-skeleton" aria-busy="true">
        <span class="sr-only">Loading profile…</span>
        <div class="bc-skeleton sk-heading"></div>
        <div class="bc-skeleton sk-subheading"></div>
        <div class="bc-skeleton sk-media"></div>
        <div class="sk-fields">
          <div v-for="n in 6" :key="n" class="sk-field">
            <div class="bc-skeleton sk-label"></div>
            <div class="bc-skeleton sk-input"></div>
          </div>
        </div>
      </div>

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
          <div class="store-media">
            <!-- Cover photo, shown across the top of the storefront -->
            <div class="store-media__cover">
              <img
                v-if="profileData.store_cover_url"
                :src="profileData.store_cover_url"
                alt="Store cover photo"
              />
              <p v-else class="store-media__cover-empty">
                No cover photo yet. Customers will see a plain banner.
              </p>
              <div class="store-media__cover-actions">
                <button
                  type="button"
                  class="store-media__btn"
                  @click="openUploadModal('cover')"
                >
                  {{ profileData.store_cover_url ? "Change Cover" : "Add Cover Photo" }}
                </button>
                <button
                  v-if="profileData.store_cover_url"
                  type="button"
                  class="store-media__btn"
                  :disabled="isRemovingCover"
                  @click="removeStoreCover"
                >
                  {{ isRemovingCover ? "Removing…" : "Remove" }}
                </button>
              </div>
            </div>

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
                <h3 class="logo-card__title">Store Logo &amp; Cover</h3>
                <p class="logo-card__text">
                  Your logo sits on your cover photo at the top of your storefront.
                  Covers look best at 1500 × 500.
                </p>
              </div>
              <button
                type="button"
                class="btn-secondary logo-card__action"
                @click="openUploadModal('logo')"
              >
                Change Logo
              </button>
            </div>
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
                  v-phone
                  class="form-input"
                  :class="{ 'is-invalid': generalForm.errors.contact_number }"
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

      </div>
      <!-- end tab-content -->
    </main>

    <!-- Store Logo / Cover Upload Modal -->
    <transition name="modal-fade">
      <div
        v-if="showUploadModal"
        class="modal-overlay"
        @click="closeUploadModal"
      >
        <div class="modal-container" @click.stop>
          <div class="modal-header">
            <h3 class="modal-title">{{ uploadConfig.title }}</h3>
            <button class="modal-close" @click="closeUploadModal">✕</button>
          </div>
          <div class="modal-body">
            <div
              class="photo-upload-area"
              :class="{ 'photo-upload-area--cover': uploadKind === 'cover' }"
              @click="$refs.imageInput.click()"
            >
              <div v-if="imagePreview" class="photo-preview">
                <img :src="imagePreview" alt="Preview" />
              </div>
              <div v-else class="photo-placeholder">
                <span class="upload-icon">{{ uploadKind === "cover" ? "🖼️" : "🏪" }}</span>
                <p class="upload-text">{{ uploadConfig.prompt }}</p>
                <p class="upload-hint">{{ uploadConfig.hint }}</p>
              </div>
            </div>
            <input
              type="file"
              ref="imageInput"
              @change="handleImageSelect"
              :accept="uploadConfig.accept"
              class="hidden-input"
            />
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" @click="closeUploadModal">
              Cancel
            </button>
            <button
              class="btn-primary"
              @click="uploadStoreImage"
              :disabled="!selectedImage || isUploading"
            >
              <span v-if="isUploading">Uploading...</span>
              <span v-else>{{ uploadConfig.button }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from "vue";
import { passwordOk } from "../../utils/passwordRules";
import vendorHeader from "../../layouts/vendorHeader.vue";
import { useRoute, useRouter } from "vue-router";
import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";
import "../../styles/bloomcraft.css";
import api from "../../plugins/axios";
import { toast } from "@/utils/snackbar";
import { useFormSubmit } from "../../composables/useFormSubmit";
import { useVendorProfile } from "../../composables/useVendorProfile";

const router = useRouter();
const route = useRoute();
const { updateLocalProfile } = useVendorProfile({
  autoFetch: false,
  showToast: false,
});

// Each tab gets its own isolated form state
const generalForm = useFormSubmit();
const paymentForm = useFormSubmit();
const productForm = useFormSubmit();
const deliveryForm = useFormSubmit();
const passwordForm = useFormSubmit();

// ── Page state ────────────────────────────────────────────────────────────
const activeTab = ref("general");
const isLoading = ref(false);
const showUploadModal = ref(false);
const uploadKind = ref("logo");
const selectedImage = ref(null);
const imagePreview = ref(null);
const isUploading = ref(false);
const isRemovingCover = ref(false);
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
  store_cover_url: null,
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
  subscription: null,
});

const formatSubscriptionStatus = (status) => (status || "pending").replace(/_/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase());
const formatSubscriptionDate = (date) => date ? new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(new Date(date)) : "—";
const formatSubscriptionPrice = (amount, currency = "PHP") => new Intl.NumberFormat("en-PH", {
  style: "currency",
  currency,
  maximumFractionDigits: 0,
}).format(Number(amount));

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
    const response = await api.get("/vendor/profile");
    if (response.data.success) {
      const data = response.data.data;
      Object.assign(profileData, data);
      updateLocalProfile(data);
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

// The backend redirect intentionally contains no payment state. The checkout
// ID was recorded before leaving this Vercel origin and is verified again via
// the authenticated API before displaying a one-time success message.
const notifyVerifiedSubscriptionCheckout = async () => {
  let pendingCheckout;
  try {
    pendingCheckout = JSON.parse(sessionStorage.getItem("vendor_subscription_checkout") || "null");
  } catch (_) {
    sessionStorage.removeItem("vendor_subscription_checkout");
    return;
  }
  if (!pendingCheckout?.checkoutId) return;

  try {
    const { data } = await api.get(`/vendor/subscription/checkout/${pendingCheckout.checkoutId}`);
    if (data?.data?.status === "paid" && profileData.subscription?.plan_key === data.data.plan_key) {
      toast.success(`Successfully subscribed to the ${profileData.subscription.plan_name} Plan!`);
      sessionStorage.removeItem("vendor_subscription_checkout");
    } else if (["failed", "cancelled"].includes(data?.data?.status)) {
      sessionStorage.removeItem("vendor_subscription_checkout");
    }
  } catch (_) {
    // Keep it for a later profile load when the network is temporarily unavailable.
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
        if (res.data?.data) {
          Object.assign(profileData, res.data.data);
          updateLocalProfile(res.data.data);
        }
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
        if (res.data?.data) {
          Object.assign(profileData, res.data.data);
          updateLocalProfile(res.data.data);
        }
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
        if (res.data?.data) {
          Object.assign(profileData, res.data.data);
          updateLocalProfile(res.data.data);
        }
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
        if (res.data?.data) {
          Object.assign(profileData, res.data.data);
          updateLocalProfile(res.data.data);
        }
        toast.success("Delivery details saved!");
      },
    },
  );

const changePassword = () => {
  if (!passwordData.current_password) {
    passwordForm.errors.current_password = "Current password is required";
    return;
  }
  if (!passwordOk(passwordData.password || "")) {
    passwordForm.errors.password = "Use at least 8 characters with an uppercase letter, a lowercase letter and a number.";
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

// ── Logo & cover handling ─────────────────────────────────────────────────
const UPLOAD_KINDS = {
  logo: {
    title: "Update Store Logo",
    prompt: "Click to select logo",
    hint: "JPG, PNG • Max 2MB",
    accept: "image/jpeg,image/jpg,image/png",
    types: /image\/(jpeg|jpg|png)/,
    typeError: "Please select a JPG or PNG image",
    maxMb: 2,
    field: "store_logo",
    endpoint: "/vendor/profile/store-logo",
    button: "Upload Logo",
    success: "Store logo updated!",
    failure: "Failed to upload logo",
  },
  cover: {
    title: "Update Cover Photo",
    prompt: "Click to select a cover photo",
    hint: "JPG, PNG, WEBP • Max 5MB • Best at 1500 × 500",
    accept: "image/jpeg,image/jpg,image/png,image/webp",
    types: /image\/(jpeg|jpg|png|webp)/,
    typeError: "Please select a JPG, PNG or WEBP image",
    maxMb: 5,
    field: "store_cover",
    endpoint: "/vendor/profile/store-cover",
    button: "Upload Cover",
    success: "Cover photo updated!",
    failure: "Failed to upload cover photo",
  },
};
const uploadConfig = computed(() => UPLOAD_KINDS[uploadKind.value]);

const applyProfileResponse = (data) => {
  Object.assign(profileData, data);
  updateLocalProfile(data);
};

const openUploadModal = (kind) => {
  showAvatarMenu.value = false;
  uploadKind.value = kind;
  showUploadModal.value = true;
};
const closeUploadModal = () => {
  showUploadModal.value = false;
  selectedImage.value = null;
  imagePreview.value = null;
};
const handleImageSelect = (event) => {
  const file = event.target.files[0];
  event.target.value = "";
  if (!file) return;
  const config = uploadConfig.value;
  if (!file.type.match(config.types)) {
    toast.error(config.typeError);
    return;
  }
  if (file.size > config.maxMb * 1024 * 1024) {
    toast.error(`Image size must be less than ${config.maxMb}MB`);
    return;
  }
  selectedImage.value = file;
  const reader = new FileReader();
  reader.onload = (e) => (imagePreview.value = e.target.result);
  reader.readAsDataURL(file);
};
const uploadStoreImage = async () => {
  if (!selectedImage.value) return;
  const config = uploadConfig.value;
  try {
    isUploading.value = true;
    const data = new FormData();
    data.append(config.field, selectedImage.value);
    const response = await api.post(config.endpoint, data, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    if (response.data.success) {
      applyProfileResponse(response.data.data);
      toast.success(config.success);
      closeUploadModal();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || config.failure);
  } finally {
    isUploading.value = false;
  }
};
const removeStoreCover = async () => {
  try {
    isRemovingCover.value = true;
    const response = await api.delete("/vendor/profile/store-cover");
    if (response.data.success) {
      applyProfileResponse(response.data.data);
      toast.success("Cover photo removed");
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to remove cover photo");
  } finally {
    isRemovingCover.value = false;
  }
};

const handleClickOutside = (event) => {
  if (avatarDropdown.value && !avatarDropdown.value.contains(event.target))
    showAvatarMenu.value = false;
};

onMounted(() => {
  fetchProfile().then(async () => {
    await notifyVerifiedSubscriptionCheckout();
    const success = route.query.subscription_success;
    if (success === "business_trial") {
      toast.success("Successfully claimed your 1-month Business free trial!");
    } else if (success && profileData.subscription?.plan_name) {
      toast.success(`Successfully subscribed to the ${profileData.subscription.plan_name} Plan!`);
    }
  });
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
  background: linear-gradient(135deg, var(--v-bg) 0%, var(--v-subtle-2) 100%);
}
.profile-main {
  margin-left: 260px;
  flex: 1;
  padding: 28px;
  max-width: 1400px;
}
.subscription-card { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin: 0 0 22px; padding: 21px 24px; background: var(--v-surface); border: 1px solid var(--v-border-strong); border-radius: 14px; box-shadow: 0 3px 12px rgba(15, 23, 42, .05); }
.subscription-card__eyebrow { margin: 0 0 5px; color: var(--v-muted); font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.subscription-card h2 { margin: 0 0 4px; color: var(--v-text); font-size: 20px; }
.subscription-card p:not(.subscription-card__eyebrow) { margin: 0; color: var(--v-muted); font-size: 14px; }
.subscription-card__status { margin-left: auto; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.subscription-card__manage { color: var(--bc-accent-deep); font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap; }
.subscription-card__manage:hover { text-decoration: underline; }
.status-trialing { color: var(--v-blue-text); background: var(--v-blue-soft); }.status-active { color: var(--v-green-text); background: var(--v-green-soft); }.status-pending, .status-past_due { color: var(--v-amber-text); background: var(--v-amber-soft); }

/* Completion card */
.completion-card {
  background: var(--v-surface);
  border-radius: 16px;
  padding: 28px;
  margin-bottom: 28px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  border: 1px solid var(--v-border-strong);
}

/* ── Profile loading skeleton ─────────────────────────────────────────── */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
.sk-heading {
  height: 28px;
  width: 260px;
  max-width: 70%;
  margin-bottom: 10px;
}
.sk-subheading {
  height: 14px;
  width: 360px;
  max-width: 90%;
  margin-bottom: 28px;
}
.sk-media {
  height: 220px;
  border-radius: 16px;
  margin-bottom: 28px;
}
.sk-fields {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
}
.sk-label {
  height: 12px;
  width: 40%;
  margin-bottom: 10px;
}
.sk-input {
  height: 46px;
}
@media (max-width: 640px) {
  .sk-fields {
    grid-template-columns: 1fr;
  }
}

.store-media {
  margin-bottom: 22px;
  border: 1px solid var(--v-border-strong);
  border-radius: 16px;
  overflow: hidden;
  background: linear-gradient(135deg, var(--v-green-soft) 0%, var(--v-blue-soft) 100%);
}

.store-media__cover {
  position: relative;
  aspect-ratio: 3 / 1;
  max-height: 260px;
  width: 100%;
  background: linear-gradient(135deg, var(--v-green-soft) 0%, var(--v-blue-soft) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.store-media__cover img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.store-media__cover-empty {
  margin: 0;
  padding: 0 16px;
  color: var(--v-muted);
  font-size: 14px;
  text-align: center;
}

.store-media__cover-actions {
  position: absolute;
  right: 12px;
  bottom: 12px;
  display: flex;
  gap: 8px;
}

.store-media__btn {
  padding: 8px 14px;
  border: none;
  border-radius: 8px;
  background: var(--v-glass);
  color: var(--v-text);
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

.store-media__btn:hover {
  background: var(--v-surface);
}

.store-media__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.logo-card {
  display: grid;
  grid-template-columns: 88px 1fr auto;
  gap: 18px;
  align-items: center;
  padding: 0 20px 20px;
}

/* The logo overlaps the bottom of the cover, as on the storefront */
.logo-card__preview {
  margin-top: -36px;
  position: relative;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
}

.photo-upload-area--cover {
  aspect-ratio: 3 / 1;
}

@media (max-width: 640px) {
  .logo-card {
    grid-template-columns: 72px 1fr;
  }
  .store-media .logo-card__preview {
    width: 72px;
    height: 72px;
  }
  .logo-card__action {
    grid-column: 1 / -1;
  }
}

.logo-card__preview {
  width: 88px;
  height: 88px;
  border-radius: 20px;
  overflow: hidden;
  background: var(--v-surface);
  border: 1px solid var(--v-border-strong);
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
  color: var(--bc-accent-deep);
}

.logo-card__title {
  margin: 0 0 6px;
  font-size: 18px;
  color: var(--v-text);
}

.logo-card__text {
  margin: 0;
  color: var(--v-muted);
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
  color: var(--v-text);
  margin: 0 0 4px 0;
}
.completion-subtitle {
  font-size: 14px;
  color: var(--v-muted);
  margin: 0;
}
.completion-percentage {
  font-size: 36px;
  font-weight: 800;
  background: linear-gradient(135deg, var(--bc-accent) 0%, var(--bc-accent-hover) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.completion-bar-container {
  height: 10px;
  background: var(--v-subtle-2);
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 20px;
}
.completion-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--bc-accent) 0%, var(--bc-accent-hover) 100%);
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
  border-radius: 10px;
  box-shadow: 0 0 10px color-mix(in srgb, var(--bc-accent) 50%, transparent);
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
  background: var(--v-subtle);
  border-radius: 12px;
  border: 2px solid transparent;
  transition: all 0.3s;
}
.completion-item.completed {
  background: var(--v-green-soft);
  border-color: var(--bc-accent);
}
.completion-icon {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  background: var(--v-subtle-2);
  color: var(--v-faint);
  font-weight: 700;
  flex-shrink: 0;
}
.completion-item.completed .completion-icon {
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.completion-text {
  flex: 1;
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text-2);
}
.completion-action {
  padding: 6px 14px;
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
  border: none;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.completion-action:hover {
  background: var(--bc-accent-hover);
  transform: translateX(2px);
}

/* Tabs */
.profile-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 28px;
  padding: 6px;
  background: var(--v-surface);
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
  color: var(--v-muted);
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  white-space: nowrap;
  font-family: inherit;
}
.tab-button:hover {
  color: var(--v-text-2);
  background: var(--v-subtle);
}
.tab-button.active {
  background: linear-gradient(135deg, var(--bc-accent) 0%, var(--bc-accent-hover) 100%);
  color: var(--bc-accent-ink);
  box-shadow: 0 4px 12px color-mix(in srgb, var(--bc-accent) 25%, transparent);
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
  background: var(--v-surface);
  color: var(--bc-accent);
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
  background: var(--v-surface);
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  border: 1px solid var(--v-border-strong);
}
.panel-header {
  margin-bottom: 32px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--v-border-strong);
}
.panel-title {
  font-size: 24px;
  font-weight: 700;
  color: var(--v-text);
  margin: 0 0 8px 0;
}
.panel-description {
  font-size: 15px;
  color: var(--v-muted);
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
  background: var(--v-green-soft);
  color: var(--v-green-text);
  border-color: #a7f3d0;
}
.alert-banner.error {
  background: var(--v-red-soft);
  color: var(--v-red-text);
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
  color: var(--v-text-2);
}
.form-input {
  padding: 12px 16px;
  border: 2px solid var(--v-border-strong);
  border-radius: 10px;
  font-size: 15px;
  color: var(--v-text);
  background: var(--v-input);
  transition: all 0.3s;
  font-family: inherit;
  width: 100%;
}
.form-input:focus {
  outline: none;
  border-color: var(--bc-accent);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--bc-accent) 10%, transparent);
}
.form-input::placeholder {
  color: var(--v-faint);
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
  background: var(--v-subtle);
  border: 2px solid var(--v-border-strong);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.3s;
  font-size: 14px;
  color: var(--v-text-2);
}
.checkbox-label:hover,
.radio-label:hover {
  border-color: var(--bc-accent);
  background: var(--v-green-soft);
}
.checkbox-input,
.radio-input {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: var(--bc-accent);
}

/* Info box */
.info-box {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  background: var(--v-blue-soft);
  border: 1px solid var(--v-blue-soft);
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
  color: var(--v-blue-text);
  line-height: 1.6;
}

/* Form actions */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 24px;
  border-top: 1px solid var(--v-border-strong);
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
  background: linear-gradient(135deg, var(--bc-accent) 0%, var(--bc-accent-hover) 100%);
  color: var(--bc-accent-ink);
  box-shadow: 0 4px 12px color-mix(in srgb, var(--bc-accent) 25%, transparent);
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px color-mix(in srgb, var(--bc-accent) 35%, transparent);
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}
.btn-secondary {
  background: var(--v-surface);
  color: var(--v-text-2);
  border: 2px solid var(--v-border-strong);
}
.btn-secondary:hover {
  border-color: var(--bc-accent);
  color: var(--bc-accent);
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
  border-top-color: #fff;
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
  color: var(--v-faint);
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
  border: 2px solid var(--v-red-soft);
  background: var(--v-red-soft);
  color: #ef4444;
  font-size: 18px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-remove:hover {
  background: var(--v-red-soft);
  border-color: #fca5a5;
}
.btn-add-cutoff {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  background: var(--v-green-soft);
  border: 2px dashed var(--bc-accent);
  border-radius: 10px;
  color: var(--bc-accent);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  width: fit-content;
  font-family: inherit;
}
.btn-add-cutoff:hover {
  background: var(--bc-accent-soft);
  border-color: var(--bc-accent-hover);
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
  background: var(--v-green-soft);
  border: 1px solid #a7f3d0;
  border-radius: 12px;
  border-left: 4px solid var(--bc-accent);
}
.security-icon {
  font-size: 24px;
  flex-shrink: 0;
  margin-top: 1px;
}
.password-security-note strong {
  display: block;
  font-size: 15px;
  color: var(--v-green-text);
  margin-bottom: 3px;
}
.password-security-note p {
  font-size: 13px;
  color: var(--v-green-text);
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
  background: var(--v-subtle-2);
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
  color: var(--v-amber-text);
}
.strength-label.good {
  color: #3b82f6;
}
.strength-label.strong {
  color: var(--v-green-text);
}
.match-hint {
  font-size: 12px;
  color: var(--v-green-text);
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
  background: var(--v-raised);
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
  border-bottom: 1px solid var(--v-border-strong);
}
.modal-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--v-text);
  margin: 0;
}
.modal-close {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: var(--v-subtle);
  color: var(--v-muted);
  font-size: 20px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal-close:hover {
  background: var(--v-subtle-2);
  color: var(--v-text);
}
.modal-body {
  padding: 28px;
}
.photo-upload-area {
  width: 100%;
  aspect-ratio: 1;
  border: 3px dashed var(--v-border-strong);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s;
  overflow: hidden;
}
.photo-upload-area:hover {
  border-color: var(--bc-accent);
  background: var(--v-green-soft);
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
  color: var(--v-text-2);
  margin: 0 0 6px 0;
}
.upload-hint {
  font-size: 13px;
  color: var(--v-faint);
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
  border-top: 1px solid var(--v-border-strong);
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
