<template>
  <ShopLayout section="account" title="Profile" subtitle="How your shop looks to customers, and how you get paid.">
    <!-- ══════════ Storefront header ══════════ -->
    <section class="vp-hero sp-card" aria-label="Your storefront">
      <div class="vp-cover" :class="{ empty: !profileData.store_cover_url }">
        <div v-if="isLoading" class="bc-skeleton vp-cover-sk"></div>
        <img v-else-if="profileData.store_cover_url" :src="profileData.store_cover_url" alt="Store cover photo" />
        <div v-else class="vp-cover-empty">
          <span v-html="ICONS.image"></span>
          <p>Add a cover photo. Customers see it across the top of your shop.</p>
        </div>
        <div class="vp-cover-actions">
          <button type="button" class="vp-glass-btn" @click="openUploadModal('cover')">
            <span v-html="ICONS.image"></span>{{ profileData.store_cover_url ? "Change cover" : "Add cover" }}
          </button>
          <button v-if="profileData.store_cover_url" type="button" class="vp-glass-btn" :disabled="isRemovingCover" aria-label="Remove cover photo" @click="removeStoreCover">
            <span v-html="ICONS.trash"></span>{{ isRemovingCover ? "Removing…" : "" }}
          </button>
        </div>
      </div>

      <div class="vp-id">
        <button type="button" class="vp-logo" :aria-label="profileData.store_logo_url ? 'Change store logo' : 'Add store logo'" @click="openUploadModal('logo')">
          <span v-if="isLoading" class="bc-skeleton vp-logo-sk"></span>
          <img v-else-if="profileData.store_logo_url" :src="profileData.store_logo_url" alt="" />
          <span v-else class="vp-logo-fallback">{{ initials }}</span>
          <span class="vp-logo-edit" aria-hidden="true" v-html="ICONS.edit"></span>
        </button>

        <div class="vp-id-text">
          <template v-if="isLoading">
            <span class="bc-skeleton sp-sk-line w50"></span>
            <span class="bc-skeleton sp-sk-line w30"></span>
          </template>
          <template v-else>
            <h2>{{ profileData.store_name || "Your shop" }}</h2>
            <p>
              <span v-if="profileData.owner_name">{{ profileData.owner_name }}<template v-if="profileData.position"> · {{ profileData.position }}</template></span>
              <router-link v-if="profileData.subscription?.plan_name" :to="{ name: 'VendorSubscription' }" class="vp-plan" :class="`status-${profileData.subscription.status}`">
                <span v-html="ICONS.sparkle"></span>{{ profileData.subscription.plan_name }}{{ profileData.subscription.status === "trialing" ? " · Trial" : "" }}
              </router-link>
            </p>
          </template>
        </div>

        <!-- Completion ring -->
        <div class="vp-ring" role="img" :aria-label="`Profile ${completion}% complete`" :style="{ '--p': completion }">
          <span class="vp-ring-text"><b>{{ completion }}%</b><small>complete</small></span>
        </div>
      </div>

      <!-- What's left to fill in -->
      <ul v-if="!isLoading && completion < 100" class="vp-steps" aria-label="Finish your profile">
        <li v-for="s in steps" :key="s.key" :class="{ done: s.done }">
          <button type="button" :disabled="s.done" @click="s.go()">
            <span class="vp-step-dot" v-html="s.done ? ICONS.check : ICONS[s.icon]"></span>
            <span>{{ s.label }}</span>
            <small>{{ s.done ? "Done" : "Add" }}</small>
          </button>
        </li>
      </ul>
    </section>

    <div class="vp-layout">
      <!-- ══════════ Settings menu ══════════ -->
      <nav class="vp-nav" aria-label="Profile settings">
        <button v-for="t in TABS" :key="t.key" type="button" class="vp-nav-item" :class="{ on: activeTab === t.key }" :aria-current="activeTab === t.key ? 'page' : undefined" @click="t.to ? router.push(t.to) : openTab(t.key)">
          <span class="vp-nav-icon" v-html="ICONS[t.icon]"></span>
          <span class="vp-nav-text"><b>{{ t.label }}</b><small>{{ t.hint }}</small></span>
          <span v-if="t.todo && !profileData[t.todo]" class="vp-nav-dot" aria-label="Needs details"></span>
          <span v-else-if="t.to" class="vp-nav-go" aria-hidden="true" v-html="ICONS.chevronRight"></span>
        </button>
      </nav>

      <!-- ══════════ Panels ══════════ -->
      <div class="vp-panel">
        <!-- Loading -->
        <section v-if="isLoading" class="sp-card vp-card" aria-busy="true">
          <span class="sr-only">Loading profile…</span>
          <div class="bc-skeleton sp-sk-line w30"></div>
          <div class="bc-skeleton sp-sk-line w50"></div>
          <div class="vp-sk-grid"><div v-for="n in 6" :key="n" class="bc-skeleton vp-sk-input"></div></div>
        </section>

        <!-- ── Store profile ── -->
        <form v-else-if="activeTab === 'general'" class="sp-card vp-card" novalidate @submit.prevent="saveActive">
          <header class="vp-card-head">
            <h2>Store profile</h2>
            <p>What customers see on your shop page.</p>
          </header>
          <div v-if="generalForm.errorMsg" class="sp-banner error vp-banner" role="alert">
            <span v-html="ICONS.alert"></span>{{ generalForm.errorMsg }}
            <button type="button" aria-label="Dismiss" @click="generalForm.errorMsg = ''" v-html="ICONS.close"></button>
          </div>

          <fieldset class="vp-group">
            <legend><span v-html="ICONS.store"></span>Your shop</legend>
            <div class="sp-grid-2">
              <label class="sp-field" data-change="store_name">
                <span>Store name</span>
                <input v-model="formData.general.store_name" class="sp-input" :class="{ invalid: generalForm.errors.store_name }" placeholder="Your store name" required @input="generalForm.clearError('store_name')" />
                <small v-if="generalForm.errors.store_name" class="sp-error">{{ generalForm.errors.store_name }}</small>
              </label>
              <label class="sp-field" data-change="operating_hours">
                <span>Operating hours</span>
                <input v-model="formData.general.operating_hours" class="sp-input" placeholder="Mon–Fri, 9 AM – 6 PM" />
              </label>
              <label class="sp-field sp-span-all" data-change="store_description">
                <span>About your shop <em class="vp-count">{{ (formData.general.store_description || "").length }}</em></span>
                <textarea v-model="formData.general.store_description" class="sp-textarea" rows="4" placeholder="What you make, what makes it special, who you make it for…"></textarea>
              </label>
            </div>
          </fieldset>

          <fieldset class="vp-group">
            <legend><span v-html="ICONS.pin"></span>Location &amp; contact</legend>
            <div class="sp-grid-2">
              <label class="sp-field sp-span-all" data-change="store_address">
                <span>Store address</span>
                <input v-model="formData.general.store_address" class="sp-input" :class="{ invalid: generalForm.errors.store_address }" placeholder="Street, barangay, city" required @input="generalForm.clearError('store_address')" />
                <small v-if="generalForm.errors.store_address" class="sp-error">{{ generalForm.errors.store_address }}</small>
              </label>
              <label class="sp-field" data-change="contact_number">
                <span>Contact number</span>
                <div class="vp-affix"><i v-html="ICONS.phone"></i><input v-model="formData.general.contact_number" v-phone type="tel" class="sp-input" :class="{ invalid: generalForm.errors.contact_number }" required @input="generalForm.clearError('contact_number')" /></div>
                <small v-if="generalForm.errors.contact_number" class="sp-error">{{ generalForm.errors.contact_number }}</small>
              </label>
              <label class="sp-field" data-change="service_areas">
                <span>Areas you deliver to</span>
                <input v-model="formData.general.service_areas" class="sp-input" placeholder="e.g. Manila, Quezon City" />
              </label>
            </div>
          </fieldset>

          <fieldset class="vp-group">
            <legend><span v-html="ICONS.user"></span>Owner</legend>
            <div class="sp-grid-2">
              <label class="sp-field" data-change="owner_name">
                <span>Owner name</span>
                <input v-model="formData.general.owner_name" class="sp-input" :class="{ invalid: generalForm.errors.owner_name }" placeholder="Your full name" required @input="generalForm.clearError('owner_name')" />
                <small v-if="generalForm.errors.owner_name" class="sp-error">{{ generalForm.errors.owner_name }}</small>
              </label>
              <label class="sp-field" data-change="position">
                <span>Your role</span>
                <input v-model="formData.general.position" class="sp-input" placeholder="Owner, manager…" />
              </label>
            </div>
          </fieldset>

          <fieldset class="vp-group">
            <legend><span v-html="ICONS.globe"></span>Social pages <em>optional</em></legend>
            <div class="sp-grid-2">
              <label class="sp-field" data-change="facebook_page">
                <span>Facebook</span>
                <div class="vp-affix"><i class="vp-affix-text">fb</i><input v-model="formData.general.facebook_page" type="url" class="sp-input" placeholder="https://facebook.com/yourpage" /></div>
              </label>
              <label class="sp-field" data-change="instagram_page">
                <span>Instagram</span>
                <div class="vp-affix"><i class="vp-affix-text">ig</i><input v-model="formData.general.instagram_page" type="url" class="sp-input" placeholder="https://instagram.com/yourpage" /></div>
              </label>
            </div>
          </fieldset>
        </form>

        <!-- ── Payouts ── -->
        <form v-else-if="activeTab === 'payment'" class="sp-card vp-card" novalidate @submit.prevent="saveActive">
          <header class="vp-card-head">
            <h2>Payouts</h2>
            <p>Where we send the money from your sales.</p>
          </header>
          <div v-if="paymentForm.errorMsg" class="sp-banner error vp-banner" role="alert">
            <span v-html="ICONS.alert"></span>{{ paymentForm.errorMsg }}
            <button type="button" aria-label="Dismiss" @click="paymentForm.errorMsg = ''" v-html="ICONS.close"></button>
          </div>

          <fieldset class="vp-group" data-change="payout_method">
            <legend><span v-html="ICONS.wallet"></span>Payout method</legend>
            <div class="vp-choices" role="radiogroup" aria-label="Payout method">
              <label v-for="m in PAYOUT_METHODS" :key="m.value" class="vp-choice" :class="{ on: formData.payment.payout_method === m.value }">
                <input v-model="formData.payment.payout_method" type="radio" name="payout" :value="m.value" />
                <span class="vp-choice-icon" v-html="ICONS[m.icon]"></span>
                <span class="vp-choice-text"><b>{{ m.label }}</b><small>{{ m.hint }}</small></span>
                <span class="vp-choice-tick" aria-hidden="true" v-html="ICONS.check"></span>
              </label>
            </div>
            <small v-if="paymentForm.errors.payout_method" class="sp-error">{{ paymentForm.errors.payout_method }}</small>
          </fieldset>

          <fieldset class="vp-group">
            <legend><span v-html="ICONS.card"></span>Account</legend>
            <div class="sp-grid-2">
              <label class="sp-field" data-change="account_holder_name">
                <span>Account holder name</span>
                <input v-model="formData.payment.account_holder_name" class="sp-input" :class="{ invalid: paymentForm.errors.account_holder_name }" placeholder="Full name on the account" required @input="paymentForm.clearError('account_holder_name')" />
                <small v-if="paymentForm.errors.account_holder_name" class="sp-error">{{ paymentForm.errors.account_holder_name }}</small>
              </label>
              <label class="sp-field" data-change="bank_name">
                <span>{{ formData.payment.payout_method === "bank" ? "Bank" : "Provider" }}</span>
                <input v-model="formData.payment.bank_name" class="sp-input" :class="{ invalid: paymentForm.errors.bank_name }" :placeholder="formData.payment.payout_method === 'bank' ? 'e.g. BDO, BPI, Metrobank' : 'e.g. GCash, Maya'" required @input="paymentForm.clearError('bank_name')" />
                <small v-if="paymentForm.errors.bank_name" class="sp-error">{{ paymentForm.errors.bank_name }}</small>
              </label>
              <label class="sp-field sp-span-all" data-change="account_number">
                <span>{{ formData.payment.payout_method === "bank" ? "Account number" : "Account or mobile number" }}</span>
                <input v-model="formData.payment.account_number" class="sp-input" :class="{ invalid: paymentForm.errors.account_number }" inputmode="numeric" autocomplete="off" required @input="paymentForm.clearError('account_number')" />
                <small v-if="paymentForm.errors.account_number" class="sp-error">{{ paymentForm.errors.account_number }}</small>
              </label>
              <label class="sp-field sp-span-all" data-change="billing_address">
                <span>Billing address</span>
                <textarea v-model="formData.payment.billing_address" class="sp-textarea" rows="3" placeholder="Complete billing address" required></textarea>
                <small v-if="paymentForm.errors.billing_address" class="sp-error">{{ paymentForm.errors.billing_address }}</small>
              </label>
            </div>
          </fieldset>

          <p class="vp-note"><span v-html="ICONS.lock"></span>Stored securely. Only used to send your payouts and to let customers pay you.</p>
        </form>

        <!-- ── Products & pricing ── -->
        <form v-else-if="activeTab === 'product'" class="sp-card vp-card" novalidate @submit.prevent="saveActive">
          <header class="vp-card-head">
            <h2>Products &amp; pricing</h2>
            <p>What you sell and your usual price range.</p>
          </header>
          <div v-if="productForm.errorMsg" class="sp-banner error vp-banner" role="alert">
            <span v-html="ICONS.alert"></span>{{ productForm.errorMsg }}
            <button type="button" aria-label="Dismiss" @click="productForm.errorMsg = ''" v-html="ICONS.close"></button>
          </div>

          <fieldset class="vp-group" data-change="product_types">
            <legend><span v-html="ICONS.flower"></span>What you sell <em>{{ formData.product.product_types.length }} picked</em></legend>
            <div class="vp-chips">
              <label v-for="type in productTypeOptions" :key="type.value" class="vp-chip" :class="{ on: formData.product.product_types.includes(type.value) }">
                <input v-model="formData.product.product_types" type="checkbox" :value="type.value" />
                <span v-if="formData.product.product_types.includes(type.value)" class="vp-chip-tick" v-html="ICONS.check"></span>{{ type.label }}
              </label>
            </div>
            <small v-if="productForm.errors.product_types" class="sp-error">{{ productForm.errors.product_types }}</small>
          </fieldset>

          <fieldset class="vp-group">
            <legend><span v-html="ICONS.tag"></span>Price range</legend>
            <div class="sp-grid-2">
              <label class="sp-field" data-change="price_min">
                <span>From</span>
                <div class="vp-affix"><i class="vp-affix-text">₱</i><input v-model.number="formData.product.price_min" type="number" step="0.01" min="0" class="sp-input" :class="{ invalid: productForm.errors.price_min }" placeholder="500.00" required @input="productForm.clearError('price_min')" /></div>
                <small v-if="productForm.errors.price_min" class="sp-error">{{ productForm.errors.price_min }}</small>
              </label>
              <label class="sp-field" data-change="price_max">
                <span>Up to</span>
                <div class="vp-affix"><i class="vp-affix-text">₱</i><input v-model.number="formData.product.price_max" type="number" step="0.01" :min="formData.product.price_min || 0" class="sp-input" :class="{ invalid: productForm.errors.price_max }" placeholder="5,000.00" required @input="productForm.clearError('price_max')" /></div>
                <small v-if="productForm.errors.price_max" class="sp-error">{{ productForm.errors.price_max }}</small>
              </label>
            </div>
          </fieldset>

          <fieldset class="vp-group" data-change="same_day_delivery">
            <legend><span v-html="ICONS.truck"></span>Same-day delivery</legend>
            <div class="vp-choices two" role="radiogroup" aria-label="Same-day delivery">
              <label class="vp-choice" :class="{ on: formData.product.same_day_delivery === true }">
                <input v-model="formData.product.same_day_delivery" type="radio" name="sameday" :value="true" />
                <span class="vp-choice-icon" v-html="ICONS.sparkle"></span>
                <span class="vp-choice-text"><b>Yes, same day</b><small>Orders can arrive today</small></span>
                <span class="vp-choice-tick" aria-hidden="true" v-html="ICONS.check"></span>
              </label>
              <label class="vp-choice" :class="{ on: formData.product.same_day_delivery === false }">
                <input v-model="formData.product.same_day_delivery" type="radio" name="sameday" :value="false" />
                <span class="vp-choice-icon" v-html="ICONS.calendar"></span>
                <span class="vp-choice-text"><b>Standard only</b><small>Delivered on a later day</small></span>
                <span class="vp-choice-tick" aria-hidden="true" v-html="ICONS.check"></span>
              </label>
            </div>
          </fieldset>

          <fieldset class="vp-group" data-change="cutoff_times">
            <legend><span v-html="ICONS.clock"></span>Order cutoff times <em>optional</em></legend>
            <p class="vp-group-hint">The latest time customers can order on each day.</p>
            <ul v-if="formData.product.cutoff_times.length" class="vp-cutoffs">
              <li v-for="(cutoff, index) in formData.product.cutoff_times" :key="index">
                <select v-model="cutoff.day" class="sp-select" aria-label="Day" required>
                  <option value="">Pick a day</option>
                  <option v-for="d in WEEKDAYS" :key="d" :value="d">{{ d }}</option>
                </select>
                <input v-model="cutoff.time" type="time" class="sp-input" aria-label="Cutoff time" required />
                <button type="button" class="sp-icon-btn" :aria-label="`Remove ${cutoff.day || 'this'} cutoff`" @click="removeCutoffTime(index)" v-html="ICONS.trash"></button>
              </li>
            </ul>
            <button type="button" class="vp-add" @click="addCutoffTime"><span v-html="ICONS.plus"></span>Add a cutoff day</button>
          </fieldset>
        </form>
      </div>
    </div>

    <!-- ══════════ Logo / cover upload ══════════ -->
    <Transition name="sp-fade"><div v-if="showUploadModal" class="sp-backdrop" @click="closeUploadModal"></div></Transition>
    <Transition name="sp-pop">
      <div v-if="showUploadModal" class="sp-modal-wrap">
        <div class="sp-modal vp-upload" role="dialog" aria-modal="true" aria-labelledby="vp-up-title">
          <div class="sp-modal-head">
            <div><h3 id="vp-up-title">{{ uploadConfig.title }}</h3><p>{{ uploadConfig.hint }}</p></div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeUploadModal" v-html="ICONS.close"></button>
          </div>
          <button type="button" class="vp-drop" :class="{ cover: uploadKind === 'cover', has: imagePreview }" @click="$refs.imageInput.click()">
            <img v-if="imagePreview" :src="imagePreview" alt="Preview" />
            <span v-else class="vp-drop-empty">
              <span class="vp-drop-icon" v-html="uploadKind === 'cover' ? ICONS.image : ICONS.store"></span>
              <b>{{ uploadConfig.prompt }}</b>
            </span>
          </button>
          <input ref="imageInput" type="file" :accept="uploadConfig.accept" class="sr-only" @change="handleImageSelect" />
          <div class="sp-modal-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="closeUploadModal">Cancel</button>
            <button type="button" class="sp-btn sp-btn-primary" :disabled="!selectedImage || isUploading" @click="uploadStoreImage">{{ isUploading ? "Uploading…" : uploadConfig.button }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </ShopLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from "vue";
import { passwordOk } from "../../utils/passwordRules";
import { useRoute, useRouter } from "vue-router";
import ShopLayout from "../../components/vendor/shop/ShopLayout.vue";
import "../../components/vendor/shop/shop.css";
import "../../styles/bloomcraft.css";
import api from "../../plugins/axios";
import { SHOP_ICONS as ICONS } from "../../components/vendor/shop/shopIcons";
import { toast } from "@/utils/snackbar";
import { useFormSubmit } from "../../composables/useFormSubmit";
import { useVendorProfile } from "../../composables/useVendorProfile";
import { guardUnsaved, useUnsavedChanges } from "../../composables/useUnsavedChanges";
import { formatPhone } from "../../utils/phone";

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

// ── Page layout ───────────────────────────────────────────────────────────
// The settings menu. "to" items live on their own pages; "todo" shows a dot until that part is filled in.
const TABS = [
  { key: "general", label: "Store profile", hint: "Name, about, contact", icon: "store" },
  { key: "payment", label: "Payouts", hint: "Where your money goes", icon: "wallet", todo: "payment_details_completed" },
  { key: "product", label: "Products & pricing", hint: "What you sell, price range", icon: "flower", todo: "product_details_completed" },
  { key: "delivery", label: "Delivery", hint: "In Shop settings", icon: "truck", to: { name: "VendorShopSettings" }, todo: "delivery_details_completed" },
  { key: "security", label: "Password & security", hint: "In Security", icon: "lock", to: { name: "VendorSecurity" } },
];
const PAYOUT_METHODS = [
  { value: "bank", label: "Bank transfer", hint: "Any local bank", icon: "store" },
  { value: "gcash", label: "GCash", hint: "To your GCash number", icon: "phone" },
  { value: "maya", label: "Maya", hint: "To your Maya number", icon: "phone" },
];
const WEEKDAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

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

// ── Forms ← saved profile ─────────────────────────────────────────────────
// Copies (never the saved arrays themselves), so editing a form can't change
// what's saved, and Discard can put the saved values back.
const FORM_FILLERS = {
  general: (data) => ({
    store_name: data.store_name || "",
    owner_name: data.owner_name || "",
    store_description: data.store_description || "",
    store_address: data.store_address || "",
    // Formatted like the phone field shows it, so loading never looks like an edit
    contact_number: data.contact_number ? formatPhone(data.contact_number) : "",
    service_areas: data.service_areas || "",
    operating_hours: data.operating_hours || "",
    position: data.position || "",
    facebook_page: data.facebook_page || "",
    instagram_page: data.instagram_page || "",
  }),
  payment: (data) => ({
    payout_method: data.payout_method || "",
    account_holder_name: data.account_holder_name || "",
    account_number: data.decrypted_account_number || "",
    bank_name: data.bank_name || "",
    billing_address: data.billing_address || "",
  }),
  product: (data) => ({
    product_types: Array.isArray(data.product_types) ? [...data.product_types] : [],
    price_min: data.price_min || null,
    price_max: data.price_max || null,
    same_day_delivery: data.same_day_delivery ?? null,
    cutoff_times: Array.isArray(data.cutoff_times) ? data.cutoff_times.map((c) => ({ day: c?.day || "", time: c?.time || "" })) : [],
  }),
};
function fillForms(data, parts = Object.keys(FORM_FILLERS)) {
  for (const part of parts) formData[part] = FORM_FILLERS[part](data);
}

// ── Unsaved changes: the shared bottom bar (View changes · Discard · Save) ──
// Each field is found on the page by its data-change key, so View changes can jump to it.
const FIELD_LABELS = {
  general: {
    store_name: "Store name", operating_hours: "Operating hours", store_description: "About your shop",
    store_address: "Store address", contact_number: "Contact number", service_areas: "Delivery areas",
    owner_name: "Owner name", position: "Your role", facebook_page: "Facebook", instagram_page: "Instagram",
  },
  payment: {
    payout_method: "Payout method", account_holder_name: "Account holder", bank_name: "Bank or provider",
    account_number: "Account number", billing_address: "Billing address",
  },
  product: {
    product_types: "What you sell", price_min: "Lowest price", price_max: "Highest price",
    same_day_delivery: "Same-day delivery", cutoff_times: "Order cutoff times",
  },
};
const SAVERS = { general: () => updateGeneralInfo(), payment: () => updatePaymentDetails(), product: () => updateProductDetails() };
const copy = (v) => JSON.parse(JSON.stringify(v ?? null));
// What each form held when last loaded or saved; Discard puts it back
const savedForms = {};

const { markClean } = useUnsavedChanges({
  fields: () =>
    Object.entries(FIELD_LABELS).flatMap(([part, labels]) =>
      Object.entries(labels).map(([key, label]) => ({ key: `${part}.${key}`, label, value: formData[part]?.[key], target: `[data-change~="${key}"]` })),
    ),
  save: saveActive,
  discard: () => {
    for (const part of Object.keys(FIELD_LABELS)) if (savedForms[part]) formData[part] = copy(savedForms[part]);
    [generalForm, paymentForm, productForm].forEach((f) => f.reset?.());
  },
  saving: () => generalForm.isSubmitting || paymentForm.isSubmitting || productForm.isSubmitting,
  enabled: () => !isLoading.value,
});

/** Remember what's on screen as saved, so the bar goes away */
function settleForms() {
  for (const part of Object.keys(FIELD_LABELS)) savedForms[part] = copy(formData[part]);
  markClean();
}
/** Save the open section; the bar stays if it didn't go through */
async function saveActive() {
  const ok = await SAVERS[activeTab.value]?.();
  if (ok) settleForms();
  return ok;
}
/** Switching sections first asks to save or discard what's unsaved */
const openTab = (key) => guardUnsaved(() => (activeTab.value = key));

// ── Fetch profile ─────────────────────────────────────────────────────────
const fetchProfile = async () => {
  try {
    isLoading.value = true;
    const response = await api.get("/vendor/profile");
    if (response.data.success) {
      const data = response.data.data;
      Object.assign(profileData, data);
      updateLocalProfile(data);
      fillForms(data);
      settleForms();
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
    return false;
  }
  if (!formData.product.price_min || !formData.product.price_max) {
    toast.error("Please enter both minimum and maximum prices");
    return false;
  }
  if (formData.product.price_max < formData.product.price_min) {
    toast.error("Maximum price must be ≥ minimum price");
    return false;
  }
  return productForm.submit(
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

// ── Header bits ───────────────────────────────────────────────────────────
// "Bloom & Co" → "BC", for a shop without a logo yet
const initials = computed(() => {
  const words = String(profileData.store_name || "").trim().split(/\s+/).filter((w) => /[a-z0-9]/i.test(w));
  return (words.length ? words.slice(0, 2).map((w) => w[0]).join("") : "BC").toUpperCase();
});
const completion = computed(() => Math.max(0, Math.min(100, Math.round(Number(profileData.profile_completion_percentage) || 0))));
const steps = computed(() => [
  { key: "payment", label: "Payouts", icon: "wallet", done: !!profileData.payment_details_completed, go: () => openTab("payment") },
  { key: "product", label: "Products & pricing", icon: "flower", done: !!profileData.product_details_completed, go: () => openTab("product") },
  { key: "delivery", label: "Delivery", icon: "truck", done: !!profileData.delivery_details_completed, go: () => router.push({ name: "VendorShopSettings" }) },
]);

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
/* ── Storefront header ─────────────────────────────────────────── */
.vp-hero {
  padding: 0;
  overflow: hidden;
  margin-bottom: 18px;
}
.vp-cover {
  position: relative;
  aspect-ratio: 4 / 1;
  min-height: 140px;
  max-height: 240px;
  width: 100%;
  background: linear-gradient(120deg, var(--bc-accent-soft), var(--v-subtle-2));
}
.vp-cover img,
.vp-cover-sk {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 0;
}
.vp-cover-empty {
  display: grid;
  place-items: center;
  align-content: center;
  gap: 6px;
  height: 100%;
  padding: 16px 16px 40px;
  text-align: center;
  color: var(--v-muted);
}
.vp-cover-empty :deep(svg) {
  width: 26px;
  height: 26px;
  color: var(--bc-accent-deep);
}
.vp-cover-empty p {
  margin: 0;
  font-size: 12.5px;
}
.vp-cover-actions {
  position: absolute;
  top: 12px;
  right: 12px;
  display: flex;
  gap: 8px;
}
/* Frosted buttons that read on any photo */
.vp-glass-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 36px;
  padding: 7px 12px;
  border: 1px solid rgba(255, 255, 255, 0.35);
  border-radius: 10px;
  background: rgba(17, 24, 39, 0.55);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  color: #fff;
  font: inherit;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}
.vp-glass-btn:hover:not(:disabled) {
  background: rgba(17, 24, 39, 0.72);
}
.vp-glass-btn:focus-visible {
  outline: 2px solid #fff;
  outline-offset: 2px;
}
.vp-glass-btn :deep(svg) {
  width: 15px;
  height: 15px;
}

.vp-id {
  display: flex;
  align-items: flex-end;
  gap: 18px;
  padding: 0 22px 18px;
}
.vp-logo {
  position: relative;
  flex: none;
  display: grid;
  place-items: center;
  width: 104px;
  height: 104px;
  margin-top: -52px;
  padding: 0;
  overflow: hidden;
  border: 4px solid var(--v-surface);
  border-radius: 24px;
  background: var(--v-subtle);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.16);
  cursor: pointer;
}
.vp-logo img,
.vp-logo-sk {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.vp-logo-fallback {
  font-size: 30px;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: var(--bc-accent-deep);
}
.vp-logo-edit {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(17, 24, 39, 0.5);
  color: #fff;
  opacity: 0;
  transition: opacity 0.15s;
}
.vp-logo-edit :deep(svg) {
  width: 22px;
  height: 22px;
}
.vp-logo:hover .vp-logo-edit,
.vp-logo:focus-visible .vp-logo-edit {
  opacity: 1;
}
.vp-logo:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.vp-id-text {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 6px;
  padding-top: 12px;
}
.vp-id-text h2 {
  margin: 0;
  overflow: hidden;
  font-size: 21px;
  font-weight: 700;
  line-height: 1.2;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.vp-id-text p {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 12px;
  margin: 0;
  font-size: 13px;
  color: var(--v-muted);
}
.vp-plan {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
}
.vp-plan:hover {
  text-decoration: underline;
}
.vp-plan :deep(svg) {
  width: 13px;
  height: 13px;
}
.vp-plan.status-expired,
.vp-plan.status-past_due,
.vp-plan.status-cancelled {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}

/* Completion ring */
.vp-ring {
  --p: 0;
  flex: none;
  position: relative;
  display: block;
  align-self: center;
  width: 78px;
  height: 78px;
  border-radius: 50%;
  background: conic-gradient(var(--bc-accent) calc(var(--p) * 1%), var(--v-subtle-2) 0);
}
.vp-ring::before {
  content: "";
  position: absolute;
  inset: 7px;
  border-radius: 50%;
  background: var(--v-surface);
}
/* Text on its own layer filling the ring, centred both ways */
.vp-ring-text {
  position: absolute;
  inset: 0;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1px;
  text-align: center;
}
.vp-ring b,
.vp-ring small {
  display: block;
  line-height: 1.1;
}
.vp-ring b {
  font-variant-numeric: tabular-nums;
  font-size: 17px;
  font-weight: 700;
  color: var(--v-text);
}
.vp-ring small {
  font-size: 10px;
  color: var(--v-muted);
}

/* What's left */
.vp-steps {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
  padding: 14px 22px 18px;
  border-top: 1px solid var(--v-border);
  list-style: none;
}
.vp-steps button {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 48px;
  padding: 8px 12px;
  border: 1px dashed var(--v-border-strong);
  border-radius: 12px;
  background: none;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.vp-steps button:hover:not(:disabled) {
  border-style: solid;
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.vp-steps button:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.vp-steps small {
  margin-left: auto;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--bc-accent-deep);
}
.vp-steps .done button {
  border-style: solid;
  border-color: var(--v-border);
  color: var(--v-muted);
  cursor: default;
}
.vp-steps .done small {
  color: var(--v-green-text);
}
.vp-step-dot {
  flex: none;
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 9px;
  background: var(--v-subtle-2);
  color: var(--v-text-2);
}
.done .vp-step-dot {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.vp-step-dot :deep(svg) {
  width: 15px;
  height: 15px;
}

/* ── Menu + panel ──────────────────────────────────────────────── */
.vp-layout {
  display: grid;
  grid-template-columns: 250px minmax(0, 1fr);
  gap: 18px;
  align-items: start;
}
.vp-nav {
  position: sticky;
  top: 16px;
  display: grid;
  gap: 4px;
  padding: 8px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
  background: var(--v-surface);
}
.vp-nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  min-height: 52px;
  padding: 8px 10px;
  border: 0;
  border-radius: 11px;
  background: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s;
}
.vp-nav-item:hover:not(.on) {
  background: var(--v-subtle);
}
.vp-nav-item.on {
  background: var(--bc-accent-soft);
}
.vp-nav-item:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 1px;
}
.vp-nav-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: var(--v-subtle-2);
  color: var(--v-text-2);
}
.on .vp-nav-icon {
  background: var(--bc-accent);
  color: var(--bc-accent-ink, #fff);
}
.vp-nav-icon :deep(svg) {
  width: 17px;
  height: 17px;
}
.vp-nav-text {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 1px;
}
.vp-nav-text b {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.vp-nav-text small {
  overflow: hidden;
  font-size: 11.5px;
  color: var(--v-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.vp-nav-dot {
  flex: none;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--v-amber-text);
}
.vp-nav-go {
  flex: none;
  color: var(--v-faint);
}
.vp-nav-go :deep(svg) {
  width: 16px;
  height: 16px;
}

.vp-panel {
  min-width: 0;
  /* Room for the unsaved-changes bar, so it never covers the last field */
  padding-bottom: 88px;
}
.vp-card {
  padding: 22px 22px 4px;
}
.vp-group:last-of-type {
  margin-bottom: 18px;
}
.vp-card-head {
  margin-bottom: 18px;
}
.vp-card-head h2 {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  color: var(--v-text);
}
.vp-card-head p {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--v-muted);
}
.vp-banner button {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  text-decoration: none;
}
.sp-banner.success {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}

/* Field groups */
.vp-group {
  min-width: 0;
  margin: 0 0 22px;
  padding: 18px 0 0;
  border: 0;
  border-top: 1px solid var(--v-border);
}
.vp-group:first-of-type {
  padding-top: 0;
  border-top: 0;
}
.vp-group legend {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  padding: 0;
  font-size: 13px;
  font-weight: 700;
  color: var(--v-text);
}
.vp-group legend :deep(svg) {
  width: 16px;
  height: 16px;
  color: var(--bc-accent-deep);
}
.vp-group legend em,
.vp-count {
  font-style: normal;
  font-size: 11.5px;
  font-weight: 500;
  color: var(--v-faint);
}
.vp-count {
  margin-left: auto;
  float: right;
}
.vp-group-hint {
  margin: -6px 0 12px;
  font-size: 12.5px;
  color: var(--v-muted);
}

/* Inputs with a small prefix (icon, ₱, fb) */
.vp-affix {
  position: relative;
}
.vp-affix i {
  position: absolute;
  top: 50%;
  left: 12px;
  display: grid;
  place-items: center;
  width: 18px;
  transform: translateY(-50%);
  font-style: normal;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--v-faint);
  pointer-events: none;
}
.vp-affix i :deep(svg) {
  width: 15px;
  height: 15px;
}
.vp-affix .sp-input {
  padding-left: 38px;
}

/* Choice cards (payout method, same-day) */
.vp-choices {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.vp-choices.two {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.vp-choice {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 64px;
  padding: 12px 14px;
  border: 1.5px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-surface);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}
.vp-choice:hover:not(.on) {
  border-color: var(--v-border-strong);
}
.vp-choice.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 14%, transparent);
}
.vp-choice input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.vp-choice:has(input:focus-visible) {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.vp-choice-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--v-subtle-2);
  color: var(--v-text-2);
}
.on .vp-choice-icon {
  background: var(--bc-accent);
  color: var(--bc-accent-ink, #fff);
}
.vp-choice-icon :deep(svg) {
  width: 18px;
  height: 18px;
}
.vp-choice-text {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 1px;
}
.vp-choice-text b {
  font-size: 13.5px;
  color: var(--v-text);
}
.vp-choice-text small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.vp-choice-tick {
  flex: none;
  display: grid;
  place-items: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--bc-accent);
  color: var(--bc-accent-ink, #fff);
  opacity: 0;
  transform: scale(0.6);
  transition: opacity 0.15s, transform 0.15s;
}
.on .vp-choice-tick {
  opacity: 1;
  transform: scale(1);
}
.vp-choice-tick :deep(svg) {
  width: 12px;
  height: 12px;
}

/* Product type chips */
.vp-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.vp-chip {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 38px;
  padding: 7px 14px;
  border: 1.5px solid var(--v-border);
  border-radius: 999px;
  background: var(--v-surface);
  font-size: 13px;
  font-weight: 500;
  color: var(--v-text-2);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, color 0.15s;
}
.vp-chip:hover:not(.on) {
  border-color: var(--v-border-strong);
}
.vp-chip.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-weight: 600;
}
.vp-chip input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.vp-chip:has(input:focus-visible) {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.vp-chip-tick :deep(svg) {
  display: block;
  width: 14px;
  height: 14px;
}

/* Cutoff rows */
.vp-cutoffs {
  display: grid;
  gap: 8px;
  margin: 0 0 10px;
  padding: 0;
  list-style: none;
}
.vp-cutoffs li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 150px auto;
  gap: 8px;
  align-items: center;
}
.vp-add {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 40px;
  padding: 8px 14px;
  border: 1.5px dashed var(--v-border-strong);
  border-radius: 12px;
  background: none;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  color: var(--bc-accent-deep);
  cursor: pointer;
}
.vp-add:hover {
  border-style: solid;
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.vp-add:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.vp-add :deep(svg) {
  width: 15px;
  height: 15px;
}

.vp-note {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 18px;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--v-subtle);
  font-size: 12.5px;
  color: var(--v-muted);
}
.vp-note :deep(svg) {
  flex: none;
  width: 15px;
  height: 15px;
  color: var(--bc-accent-deep);
}

/* Upload modal */
.vp-upload {
  max-width: 520px;
}
.vp-drop {
  display: grid;
  place-items: center;
  width: 100%;
  aspect-ratio: 1 / 1;
  max-height: 300px;
  margin: 4px 0 16px;
  padding: 0;
  overflow: hidden;
  border: 1.5px dashed var(--v-border-strong);
  border-radius: 16px;
  background: var(--v-subtle);
  font: inherit;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.vp-drop.cover {
  aspect-ratio: 3 / 1;
}
.vp-drop:hover {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.vp-drop:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.vp-drop.has {
  border-style: solid;
}
.vp-drop img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.vp-drop-empty {
  display: grid;
  justify-items: center;
  gap: 8px;
  padding: 20px;
  color: var(--v-text);
  font-size: 13.5px;
}
.vp-drop-icon {
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border-radius: 14px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
}
.vp-drop-icon :deep(svg) {
  width: 22px;
  height: 22px;
}

/* Skeletons */
.vp-sk-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin: 18px 0 22px;
}
.vp-sk-input {
  height: 42px;
  border-radius: 10px;
}
.vp-logo-sk {
  display: block;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

/* ── Smaller screens ───────────────────────────────────────────── */
@media (max-width: 960px) {
  .vp-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  /* The menu becomes a row you can swipe */
  .vp-nav {
    position: static;
    display: flex;
    overflow-x: auto;
    scrollbar-width: none;
  }
  .vp-nav::-webkit-scrollbar {
    display: none;
  }
  .vp-nav-item {
    flex: none;
    width: auto;
  }
  .vp-nav-text small {
    display: none;
  }
}
@media (max-width: 640px) {
  .vp-id {
    flex-wrap: wrap;
    padding: 0 16px 16px;
  }
  .vp-logo {
    width: 84px;
    height: 84px;
    margin-top: -42px;
  }
  .vp-ring {
    display: none;
  }
  .vp-steps,
  .vp-choices,
  .vp-choices.two,
  .vp-sk-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .vp-steps {
    padding: 12px 16px 16px;
  }
  .vp-card {
    padding: 18px 16px 4px;
  }
  .vp-cutoffs li {
    grid-template-columns: minmax(0, 1fr) 120px auto;
  }
}
@media (prefers-reduced-motion: reduce) {
  .vp-glass-btn,
  .vp-logo-edit,
  .vp-steps button,
  .vp-nav-item,
  .vp-choice,
  .vp-choice-tick,
  .vp-chip,
  .vp-drop {
    transition: none;
  }
}
</style>
