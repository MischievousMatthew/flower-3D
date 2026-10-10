<template>
  <NavHeader
    ref="navHeaderRef"
    :cartCount="cartCount"
    :isAuthenticated="isAuthenticated"
    @scroll-to-section="scrollToSection"
  />
  <div class="profile-page bc-theme">
    <main class="bc-container profile-container">
      <!-- Skeleton while the profile loads -->
      <template v-if="isLoading">
        <div class="profile-header" aria-label="Loading your profile">
          <div class="profile-info">
            <span class="bc-skeleton skeleton-avatar"></span>
            <div class="skeleton-lines">
              <span class="bc-skeleton skeleton-line skeleton-line--label"></span>
              <span class="bc-skeleton skeleton-line skeleton-line--name"></span>
              <span class="bc-skeleton skeleton-line skeleton-line--short"></span>
            </div>
          </div>
        </div>
        <section class="profile-card">
          <span class="bc-skeleton skeleton-line skeleton-line--title"></span>
          <div class="details-grid">
            <div v-for="n in 8" :key="n" class="detail-item">
              <span class="bc-skeleton skeleton-icon"></span>
              <div class="skeleton-lines">
                <span class="bc-skeleton skeleton-line skeleton-line--label"></span>
                <span class="bc-skeleton skeleton-line"></span>
              </div>
            </div>
          </div>
        </section>
      </template>

      <template v-else>
        <!-- Profile header -->
        <header class="profile-header">
          <div class="profile-info">
            <div class="avatar-wrapper">
              <img
                v-if="user.profile_picture"
                :src="user.profile_picture"
                :alt="user.name"
                class="avatar"
                @error="handleImageError"
              />
              <div v-else class="avatar avatar-placeholder">
                {{ getInitials(user.name, user.surname) }}
              </div>
              <input
                type="file"
                ref="fileInput"
                accept="image/*"
                @change="handleProfilePictureUpload"
                class="file-input"
                style="display: none"
              />
              <button
                v-if="isEditing"
                class="btn-change-photo"
                @click="triggerFileInput"
              >
                Change photo
              </button>
            </div>
            <div class="user-details">
              <p class="bc-label">Your account</p>
              <h1 class="user-name">
                {{ user.name }} <em>{{ user.surname }}</em>
              </h1>
              <p class="user-meta">
                <span>Member since {{ formatDate(user.created_at) }}</span>
                <span v-if="user.is_verified" class="verified-badge">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m5 12.5 4.5 4.5L19 7.5" />
                  </svg>
                  Verified profile
                </span>
              </p>
            </div>
          </div>
          <button
            class="bc-btn"
            :class="isEditing ? 'profile-btn-outline' : 'bc-btn--dark'"
            @click="toggleEditMode"
          >
            {{ isEditing ? "Cancel editing" : "Edit profile" }}
          </button>
        </header>

        <!-- Profile details -->
        <section class="profile-card" aria-labelledby="details-title">
          <div class="section-header">
            <h2 id="details-title">Profile <em>details</em></h2>
            <button v-if="!isEditing" class="bc-link btn-edit" @click="toggleEditMode">
              Edit
            </button>
          </div>

          <div class="details-grid">
            <!-- Full Name -->
            <div class="detail-item">
              <span class="detail-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5" /><path d="M5 20a7 7 0 0 1 14 0" /></svg>
              </span>
              <div class="detail-content">
                <label :for="isEditing ? 'pf-name' : undefined">Full name</label>
                <div v-if="isEditing" class="name-fields">
                  <input
                    id="pf-name"
                    v-model="editData.name"
                    type="text"
                    class="input-field"
                    placeholder="First Name"
                  />
                  <input
                    v-model="editData.surname"
                    type="text"
                    class="input-field"
                    placeholder="Last Name"
                    aria-label="Last name"
                  />
                </div>
                <span v-else class="detail-value"
                  >{{ user.name }} {{ user.surname }}</span
                >
              </div>
            </div>

            <!-- Email -->
            <div class="detail-item">
              <span class="detail-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>
              </span>
              <div class="detail-content">
                <label>Email</label>
                <div class="value-with-badge">
                  <span class="detail-value">{{ user.email }}</span>
                  <span v-if="user.email_verified_at" class="badge verified"
                    >Verified</span
                  >
                </div>
              </div>
            </div>

            <!-- Date of Birth -->
            <div class="detail-item">
              <span class="detail-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M4 10h16M9 3v4M15 3v4" /></svg>
              </span>
              <div class="detail-content">
                <label :for="isEditing ? 'pf-dob' : undefined">Date of birth</label>
                <!-- max enforces the 18+ rule -->
                <input
                  v-if="isEditing"
                  id="pf-dob"
                  v-model="editData.date_of_birth"
                  type="date"
                  class="input-field"
                  :max="maxBirthDate"
                />
                <span v-else class="detail-value">{{
                  formatDate(user.date_of_birth) || "-"
                }}</span>
              </div>
            </div>

            <!-- Username -->
            <div class="detail-item">
              <span class="detail-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" /><path d="M16 12v1.5a2.5 2.5 0 0 0 5 0V12a9 9 0 1 0-3.5 7.1" /></svg>
              </span>
              <div class="detail-content">
                <label :for="isEditing ? 'pf-username' : undefined">Username</label>
                <input
                  v-if="isEditing"
                  id="pf-username"
                  v-model="editData.username"
                  type="text"
                  class="input-field"
                  placeholder="Username"
                />
                <span v-else class="detail-value">{{
                  user.username || "-"
                }}</span>
              </div>
            </div>

            <!-- Contact number -->
            <div class="detail-item">
              <span class="detail-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="7" y="3" width="10" height="18" rx="2.5" /><path d="M11 18h2" /></svg>
              </span>
              <div class="detail-content">
                <label :for="isEditing ? 'pf-number' : undefined">Number</label>
                <!-- Numbers only, capped at 11 digits -->
                <input
                  v-if="isEditing"
                  id="pf-number"
                  v-model="editData.contact_number"
                  type="tel"
                  class="input-field"
                  :class="{ 'input-error': contactNumberError }"
                  placeholder="e.g. 09123456789"
                  maxlength="11"
                  @input="sanitizeContactNumber"
                />
                <span v-if="isEditing && contactNumberError" class="field-error">
                  {{ contactNumberError }}
                </span>
                <span v-else-if="!isEditing" class="detail-value">{{
                  user.contact_number || "-"
                }}</span>
              </div>
            </div>

            <!-- Plan -->
            <div class="detail-item">
              <span class="detail-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.8z" /></svg>
              </span>
              <div class="detail-content">
                <label>Plan</label>
                <span class="detail-value">{{ user.plan || "Free Plan" }}</span>
              </div>
            </div>

            <!-- Gender -->
            <div class="detail-item">
              <span class="detail-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" /></svg>
              </span>
              <div class="detail-content">
                <label :for="isEditing ? 'pf-gender' : undefined">Gender</label>
                <select
                  v-if="isEditing"
                  id="pf-gender"
                  v-model="editData.gender"
                  class="input-field"
                >
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
                <span v-else class="detail-value">{{ user.gender || "-" }}</span>
              </div>
            </div>

            <!-- Nationality -->
            <div class="detail-item">
              <span class="detail-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" /><path d="M3.5 12h17M12 3.5a13 13 0 0 1 0 17M12 3.5a13 13 0 0 0 0 17" /></svg>
              </span>
              <div class="detail-content">
                <label :for="isEditing ? 'pf-nationality' : undefined">Nationality</label>
                <input
                  v-if="isEditing"
                  id="pf-nationality"
                  v-model="editData.nationality"
                  type="text"
                  class="input-field"
                  placeholder="Nationality"
                />
                <span v-else class="detail-value">{{
                  user.nationality || "-"
                }}</span>
              </div>
            </div>

            <!-- Address -->
            <div class="detail-item detail-item--wide">
              <span class="detail-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M19 10c0 5.5-7 11-7 11s-7-5.5-7-11a7 7 0 0 1 14 0Z" /><circle cx="12" cy="10" r="2.5" /></svg>
              </span>
              <div class="detail-content">
                <label :for="isEditing ? 'pf-address' : undefined">Address</label>
                <input
                  v-if="isEditing"
                  id="pf-address"
                  v-model="editData.address"
                  type="text"
                  class="input-field"
                  placeholder="Address"
                />
                <span v-else class="detail-value">{{ user.address || "-" }}</span>
              </div>
            </div>

            <!-- Region (UI-only, drives the city list) -->
            <div class="detail-item">
              <span class="detail-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M9 4 3 6.5v13.5L9 17.5l6 2.5 6-2.5V4l-6 2.5L9 4Z" /><path d="M9 4v13.5M15 6.5V20" /></svg>
              </span>
              <div class="detail-content">
                <label :for="isEditing ? 'pf-region' : undefined">Region</label>
                <select
                  v-if="isEditing"
                  id="pf-region"
                  v-model="editData.region"
                  class="input-field"
                  @change="editData.city = ''"
                >
                  <option value="">Select Region</option>
                  <option value="NCR">National Capital Region (NCR)</option>
                  <option value="CALABARZON">Region IV-A (CALABARZON)</option>
                </select>
                <span v-else class="detail-value">{{
                  getRegionLabel(inferRegionFromCity(user.city)) || "-"
                }}</span>
              </div>
            </div>

            <!-- City (depends on region) -->
            <div class="detail-item">
              <span class="detail-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 20V9l8-5 8 5v11" /><path d="M9 20v-6h6v6" /></svg>
              </span>
              <div class="detail-content">
                <label :for="isEditing ? 'pf-city' : undefined">City</label>
                <select
                  v-if="isEditing"
                  id="pf-city"
                  v-model="editData.city"
                  class="input-field"
                  :disabled="!editData.region"
                >
                  <option value="">
                    {{
                      editData.region ? "Select City" : "Select a region first"
                    }}
                  </option>
                  <option
                    v-for="city in filteredCities"
                    :key="city"
                    :value="city"
                  >
                    {{ city }}
                  </option>
                </select>
                <span v-else class="detail-value">{{ user.city || "-" }}</span>
              </div>
            </div>

            <!-- Postal Code -->
            <div class="detail-item">
              <span class="detail-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M7 10h4M7 14h6M16 10h1" /></svg>
              </span>
              <div class="detail-content">
                <label :for="isEditing ? 'pf-postal' : undefined">Postal code</label>
                <input
                  v-if="isEditing"
                  id="pf-postal"
                  v-model="editData.postal_code"
                  type="text"
                  class="input-field"
                  placeholder="Postal Code"
                />
                <span v-else class="detail-value">{{
                  user.postal_code || "-"
                }}</span>
              </div>
            </div>
          </div>

          <div v-if="isEditing" class="action-buttons">
            <button class="bc-link btn-cancel" @click="cancelEdit">Cancel</button>
            <button
              class="bc-btn bc-btn--dark"
              @click="saveProfile"
              :disabled="isSaving"
            >
              {{ isSaving ? "Saving..." : "Save changes" }}
            </button>
          </div>
        </section>

        <!-- Two-factor authentication -->
        <section
          v-if="user.role === 'customer'"
          class="profile-card two-factor-section"
          aria-labelledby="security-title"
        >
          <span class="detail-icon detail-icon--lg" aria-hidden="true">
            <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
          </span>
          <div class="two-factor-content">
            <p class="bc-label">Security</p>
            <h2 id="security-title">Two-factor <em>authentication</em></h2>
            <p>
              Add an extra layer of security to your account with two-factor
              authentication.
            </p>
          </div>
          <!-- Keyed on the modal so the switch re-syncs if it is closed early -->
          <label
            :key="`2fa-${twoFactorEnabled}-${showTwoFactorModal}`"
            class="toggle-switch"
          >
            <input
              type="checkbox"
              role="switch"
              :checked="twoFactorEnabled"
              :disabled="twoFactorLoading"
              :aria-checked="twoFactorEnabled"
              aria-label="Two-factor authentication"
              @change="beginTwoFactorChange"
            />
            <span class="toggle-slider" aria-hidden="true"></span>
            <span class="toggle-label">{{ twoFactorEnabled ? "On" : "Off" }}</span>
          </label>
        </section>
      </template>

      <div
        v-if="showTwoFactorModal"
        class="two-factor-modal-backdrop"
        @click.self="closeTwoFactorModal"
      >
        <section
          class="two-factor-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="two-factor-title"
        >
          <button
            class="two-factor-modal-close"
            aria-label="Close two-factor verification"
            @click="closeTwoFactorModal"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
          </button>

          <template v-if="twoFactorStep === 'email'">
            <p class="bc-label">Step one</p>
            <h2 id="two-factor-title">Verify your <em>email</em></h2>
            <p>
              Enter the six-digit verification code sent to your registered
              email address.
            </p>
            <form @submit.prevent="verifyInitialEmailOtp">
              <div class="two-factor-code-boxes" @paste.prevent="pasteTwoFactorCode($event, 'otp')">
                <input v-for="(_, index) in twoFactorOtp" :key="`setup-otp-${index}`" :ref="(element) => setTwoFactorInput(element, 'otp', index)" v-model="twoFactorOtp[index]" class="two-factor-code-box" inputmode="numeric" maxlength="1" autocomplete="one-time-code" :aria-label="`Digit ${index + 1}`" @input="advanceTwoFactorCode('otp', index)" @keydown.backspace="retreatTwoFactorCode($event, 'otp', index)" />
              </div>
              <button class="bc-btn bc-btn--dark btn-two-factor-primary" :disabled="twoFactorLoading">
                {{ twoFactorLoading ? "Verifying..." : "Verify code" }}
              </button>
            </form>
          </template>

          <template v-else>
            <p class="bc-label">Step two</p>
            <h2 id="two-factor-title">Create a security <em>passkey</em></h2>
            <p>Choose a six-digit passkey to turn on two-factor authentication.</p>
            <form @submit.prevent="saveTwoFactorPasskey">
              <div class="two-factor-code-boxes" @paste.prevent="pasteTwoFactorCode($event, 'passkey')">
                <input v-for="(_, index) in twoFactorPasskey" :key="`setup-passkey-${index}`" :ref="(element) => setTwoFactorInput(element, 'passkey', index)" v-model="twoFactorPasskey[index]" class="two-factor-code-box" type="password" inputmode="numeric" maxlength="1" autocomplete="new-password" :aria-label="`Digit ${index + 1}`" @input="advanceTwoFactorCode('passkey', index)" @keydown.backspace="retreatTwoFactorCode($event, 'passkey', index)" />
              </div>
              <button class="bc-btn bc-btn--dark btn-two-factor-primary" :disabled="twoFactorLoading">
                {{ twoFactorLoading ? "Saving..." : "Save passkey" }}
              </button>
            </form>
          </template>
        </section>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "../../plugins/axios";
import NavHeader from "../../layouts/NavHeader.vue";
import { useAuth } from "../../composables/useAuth";
import { toast } from "vue3-toastify";
import "../../styles/bloomcraft.css";
import {
  buildMultipartFormData,
  clearFileInput,
  getSelectedFile,
  validateImageFile,
} from "../../utils/imageUpload";

const router = useRouter();
const { isAuthenticated } = useAuth();
const fileInput = ref(null);
const { logout } = useAuth();

// ─── Region / City data ───────────────────────────────────────────────────────
const regionCities = {
  NCR: [
    "Manila",
    "Quezon City",
    "Caloocan",
    "Las Piñas",
    "Makati",
    "Malabon",
    "Mandaluyong",
    "Marikina",
    "Muntinlupa",
    "Navotas",
    "Parañaque",
    "Pasay",
    "Pasig",
    "San Juan",
    "Taguig",
    "Valenzuela",
  ],
  CALABARZON: [
    // Cavite – Cities
    "Bacoor, Cavite",
    "Imus, Cavite",
    "Dasmariñas, Cavite",
    "General Trias, Cavite",
    "Trece Martires, Cavite",
    "Tagaytay, Cavite",
    "Carmona, Cavite",
    "Cavite City, Cavite",
    // Cavite – Municipalities
    "Silang, Cavite",
    "General Mariano Alvarez, Cavite",
    "Kawit, Cavite",
    "Noveleta, Cavite",
    "Rosario, Cavite",
    "Tanza, Cavite",
    "Naic, Cavite",
    "Maragondon, Cavite",
    "Ternate, Cavite",
    "Indang, Cavite",
    "Amadeo, Cavite",
    "Mendez, Cavite",
    "Alfonso, Cavite",
    "Magallanes, Cavite",
    "General Emilio Aguinaldo, Cavite",
    // Laguna – Cities
    "San Pedro, Laguna",
    "Biñan, Laguna",
    "Santa Rosa, Laguna",
    "Cabuyao, Laguna",
    "Calamba, Laguna",
    "San Pablo, Laguna",
    // Laguna – Municipalities
    "Los Baños, Laguna",
    "Bay, Laguna",
    "Calauan, Laguna",
    "Santa Cruz, Laguna",
    "Pila, Laguna",
    "Victoria, Laguna",
    "Nagcarlan, Laguna",
    "Magdalena, Laguna",
    "Majayjay, Laguna",
    "Luisiana, Laguna",
    "Lumban, Laguna",
    "Paete, Laguna",
    "Pakil, Laguna",
    "Pangil, Laguna",
    "Famy, Laguna",
    "Mabitac, Laguna",
    "Siniloan, Laguna",
    "Rizal, Laguna",
    "Kalayaan, Laguna",
    // Batangas – Cities
    "Batangas City, Batangas",
    "Lipa, Batangas",
    "Tanauan, Batangas",
    "Sto. Tomas, Batangas",
    "Calaca, Batangas",
    // Rizal – City
    "Antipolo, Rizal",
    // Rizal – Municipalities
    "Taytay, Rizal",
    "Cainta, Rizal",
    "Angono, Rizal",
    "Binangonan, Rizal",
    "Rodriguez, Rizal",
    "San Mateo, Rizal",
    "Teresa, Rizal",
    "Baras, Rizal",
    "Morong, Rizal",
    "Cardona, Rizal",
    "Jala-Jala, Rizal",
    "Tanay, Rizal",
    "Pililla, Rizal",
    // Quezon – City
    "Lucena, Quezon",
    // Quezon – Municipalities
    "Sariaya, Quezon",
    "Candelaria, Quezon",
    "Tiaong, Quezon",
    "Lucban, Quezon",
    "Tayabas, Quezon",
    "Infanta, Quezon",
    "Real, Quezon",
    "Mauban, Quezon",
    "Gumaca, Quezon",
    "Lopez, Quezon",
  ],
};

const regionLabels = {
  NCR: "National Capital Region (NCR)",
  CALABARZON: "Region IV-A (CALABARZON)",
};

/** Return the human-readable region label from a key */
const getRegionLabel = (key) => regionLabels[key] || "";

/** Infer the region key from a saved city value (for display in view mode) */
const inferRegionFromCity = (city) => {
  if (!city) return "";
  for (const [key, cities] of Object.entries(regionCities)) {
    if (cities.includes(city)) return key;
  }
  return "";
};

// ─── Contact number helpers ───────────────────────────────────────────────────

/** Strip any non-digit character and enforce 11-digit max in real time */
const sanitizeContactNumber = () => {
  editData.contact_number = editData.contact_number
    .replace(/\D/g, "")
    .slice(0, 11);
};

/** Live error message shown beneath the field */
const contactNumberError = computed(() => {
  const val = editData.contact_number;
  if (!val) return ""; // empty is allowed (nullable on backend)
  if (val.length > 0 && val.length < 11)
    return "Contact number must be exactly 11 digits.";
  return "";
});

// ─── Computed: cities filtered by the currently selected region ───────────────
const filteredCities = computed(() => {
  if (!editData.region) return [];
  return regionCities[editData.region] || [];
});

// ─── Computed: max date for birthday (user must be ≥ 18 years old) ────────────
const maxBirthDate = computed(() => {
  const d = new Date();
  d.setFullYear(d.getFullYear() - 18);
  return d.toISOString().split("T")[0]; // e.g. "2007-03-23"
});

// ─── User state ───────────────────────────────────────────────────────────────
const user = reactive({
  id: null,
  name: "",
  surname: "",
  username: "",
  email: "",
  contact_number: "",
  is_verified: false,
  role: "",
  vendor_data: null,
  email_verified_at: null,
  created_at: "",
  updated_at: "",
  date_of_birth: "",
  gender: "",
  nationality: "",
  address: "",
  city: "",
  postal_code: "",
  profile_picture: "",
  plan: "",
  two_factor_passkey_configured: false,
  two_factor_enabled: false,
});

const isEditing = ref(false);
const isLoading = ref(false);
const isSaving = ref(false);
const navHeaderRef = ref(null);
const showTwoFactorModal = ref(false);
const twoFactorStep = ref("email");
const twoFactorOtp = ref(Array(6).fill(""));
const twoFactorPasskey = ref(Array(6).fill(""));
const twoFactorEnabled = ref(false);
const twoFactorAction = ref(null);
const twoFactorInputs = { otp: [], passkey: [] };
const twoFactorLoading = ref(false);

const editData = reactive({
  name: "",
  surname: "",
  username: "",
  date_of_birth: "",
  gender: "",
  nationality: "",
  address: "",
  city: "",
  postal_code: "",
  contact_number: "", // ✅ Added for phone editing
  region: "", // ✅ Added for region/city cascade (UI-only; not persisted)
});

// ─── API calls ────────────────────────────────────────────────────────────────
const fetchUserProfile = async () => {
  try {
    isLoading.value = true;
    const token = localStorage.getItem("auth_token");

    if (!token) {
      console.warn("No token found, redirecting to login");
      router.push("/guest/login");
      return;
    }

    const response = await api.get("profile/user-profile", {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    });

    if (response.data.success) {
      Object.assign(user, response.data.user);
      twoFactorEnabled.value = Boolean(response.data.user.two_factor_enabled);
      console.log("Profile loaded successfully:", user.name);
    } else {
      toast.error("Failed to load profile");
    }
  } catch (error) {
    console.error("Error fetching profile:", error);

    if (error.response?.status === 401) {
      console.warn("Unauthorized - logging out");
      localStorage.removeItem("token");
      logout();
      router.push("/guest/login");
    } else {
      toast.error("Failed to load profile. Please try again.");
    }
  } finally {
    isLoading.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
};

const toggleEditMode = () => {
  if (!isEditing.value) {
    editData.name = user.name;
    editData.surname = user.surname;
    editData.username = user.username;
    editData.date_of_birth = user.date_of_birth
      ? new Date(user.date_of_birth).toISOString().split("T")[0]
      : "";
    editData.gender = user.gender || "";
    editData.nationality = user.nationality || "";
    editData.address = user.address || "";
    editData.city = user.city || "";
    editData.postal_code = user.postal_code || "";
    editData.contact_number = user.contact_number || ""; // ✅ populate phone
    editData.region = inferRegionFromCity(user.city); // ✅ infer region from saved city
  }
  isEditing.value = !isEditing.value;
};

const cancelEdit = () => {
  isEditing.value = false;
};

const saveProfile = async () => {
  // ✅ Contact number must be empty or exactly 11 digits
  if (editData.contact_number && editData.contact_number.length !== 11) {
    toast.error("Contact number must be exactly 11 digits.");
    return;
  }

  // ✅ Client-side 18+ validation guard (belt-and-suspenders alongside max attr)
  if (editData.date_of_birth) {
    const birthDate = new Date(editData.date_of_birth);
    const maxAllowed = new Date(maxBirthDate.value);
    if (birthDate > maxAllowed) {
      toast.error("You must be at least 18 years old.");
      return;
    }
  }

  try {
    isSaving.value = true;
    const token = localStorage.getItem("auth_token");

    const updateData = {
      name: editData.name,
      surname: editData.surname,
      username: editData.username,
      date_of_birth: editData.date_of_birth || null,
      gender: editData.gender || null,
      nationality: editData.nationality || null,
      address: editData.address || null,
      city: editData.city || null,
      postal_code: editData.postal_code || null,
      contact_number: editData.contact_number || null,
    };

    const response = await api.put("/profile/user-details", updateData, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
        "Content-Type": "application/json",
      },
    });

    if (response.data.success) {
      Object.assign(user, response.data.user);
      isEditing.value = false;
      toast.success("Profile updated successfully!");
    } else {
      toast.error("Failed to update profile: " + response.data.message);
    }
  } catch (error) {
    console.error("Error updating profile:", error);

    if (error.response?.status === 422) {
      const errors = error.response.data.errors;
      let errorMessage = "Validation errors:\n";
      for (const field in errors) {
        errorMessage += `${field}: ${errors[field].join(", ")}\n`;
      }
      toast.error(errorMessage);
    } else if (error.response?.status === 401) {
      toast.error("Session expired. Please login again.");
      localStorage.removeItem("token");
      logout();
      router.push("/guest/login");
    } else {
      toast.error("Failed to update profile. Please try again.");
    }
  } finally {
    isSaving.value = false;
  }
};

// ─── Profile picture ──────────────────────────────────────────────────────────
const triggerFileInput = () => {
  if (fileInput.value) {
    fileInput.value.click();
  }
};

const handleProfilePictureUpload = async (event) => {
  let file = null;

  try {
    file = validateImageFile(getSelectedFile(event), {
      fieldLabel: "Profile picture",
      maxSizeMB: 2,
    });

    if (!file) return;

    const token = localStorage.getItem("auth_token");
    const formData = buildMultipartFormData(
      { profile_picture: file },
      { fileFields: ["profile_picture"] },
    );

    const response = await api.post("/profile/picture", formData, {
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "multipart/form-data",
      },
    });

    if (response.data.success) {
      user.profile_picture = response.data.profile_picture;
      toast.success("Profile picture updated successfully!");
    } else {
      toast.error("Failed to update profile picture: " + response.data.message);
    }
  } catch (error) {
    console.error("Error uploading profile picture:", error);
    if (error.response?.status === 422) {
      toast.error(
        "Invalid image. Please upload a JPEG, PNG, or GIF file under 2MB.",
      );
    } else if (error.response?.status === 401) {
      toast.error("Session expired. Please login again.");
      localStorage.removeItem("token");
      logout();
      router.push("/guest/login");
    } else if (error instanceof Error && !error.response) {
      toast.error(error.message);
    } else {
      toast.error("Failed to upload profile picture");
    }
  } finally {
    clearFileInput(fileInput);
  }
};

const handleImageError = (event) => {
  console.warn("Profile image failed to load, falling back to avatars API");
  const name = user.name || "";
  const surname = user.surname || "";
  event.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(
    name + " " + surname,
  )}&background=ebe5dd&color=8e443a&size=256`;
};

const getInitials = (name, surname) => {
  if (!name && !surname) return "?";
  return (name?.charAt(0) || "") + (surname?.charAt(0) || "");
};

const scrollToSection = (sectionId) => {
  console.log("Scroll to section:", sectionId);
};

const twoFactorErrorMessage = (error, fallback) => {
  const errors = error.response?.data?.errors;
  if (errors) {
    return Object.values(errors).flat()[0] || fallback;
  }

  return error.response?.data?.message || fallback;
};

const resetTwoFactorCode = (field) => {
  (field === "otp" ? twoFactorOtp : twoFactorPasskey).value = Array(6).fill("");
};

const closeTwoFactorModal = () => {
  showTwoFactorModal.value = false;
  twoFactorStep.value = "email";
  twoFactorAction.value = null;
  resetTwoFactorCode("otp");
  resetTwoFactorCode("passkey");
};

const setTwoFactorInput = (element, field, index) => {
  if (element) twoFactorInputs[field][index] = element;
};

const advanceTwoFactorCode = (field, index) => {
  const values = field === "otp" ? twoFactorOtp.value : twoFactorPasskey.value;
  values[index] = values[index].replace(/\D/g, "").slice(-1);
  if (values[index] && index < 5) twoFactorInputs[field][index + 1]?.focus();
};

const retreatTwoFactorCode = (event, field, index) => {
  const values = field === "otp" ? twoFactorOtp.value : twoFactorPasskey.value;
  if (!values[index] && index > 0) {
    event.preventDefault();
    twoFactorInputs[field][index - 1]?.focus();
  }
};

const pasteTwoFactorCode = (event, field) => {
  const digits = event.clipboardData.getData("text").replace(/\D/g, "").slice(0, 6);
  if (!digits) return;
  const values = field === "otp" ? twoFactorOtp.value : twoFactorPasskey.value;
  digits.split("").forEach((digit, index) => { values[index] = digit; });
  twoFactorInputs[field][Math.min(digits.length, 6) - 1]?.focus();
};

const beginTwoFactorChange = async (event) => {
  const action = event.target.checked ? "enable" : "disable";
  try {
    twoFactorLoading.value = true;
    await api.post("/profile/two-factor/manage/start", { action });
    twoFactorAction.value = action;
    twoFactorStep.value = "email";
    resetTwoFactorCode("otp");
    showTwoFactorModal.value = true;
    toast.success("A verification code was sent to your registered email.");
  } catch (error) {
    toast.error(twoFactorErrorMessage(error, "Unable to send a verification code."));
  } finally {
    twoFactorLoading.value = false;
  }
};

const verifyInitialEmailOtp = async () => {
  const otp = twoFactorOtp.value.join("");
  if (!/^\d{6}$/.test(otp)) {
    toast.error("Enter the six-digit verification code.");
    return;
  }

  try {
    twoFactorLoading.value = true;
    const response = await api.post("/profile/two-factor/manage/verify-email-otp", {
      otp,
    });
    resetTwoFactorCode("otp");
    if (twoFactorAction.value === "disable") {
      twoFactorEnabled.value = false;
      user.two_factor_enabled = false;
      closeTwoFactorModal();
      toast.success("Two-factor authentication is now off.");
    } else {
      twoFactorStep.value = "passkey";
    }
  } catch (error) {
    toast.error(twoFactorErrorMessage(error, "The verification code could not be verified."));
  } finally {
    twoFactorLoading.value = false;
  }
};

const saveTwoFactorPasskey = async () => {
  const passkey = twoFactorPasskey.value.join("");
  if (!/^\d{6}$/.test(passkey)) {
    toast.error("Passkey must be exactly six digits.");
    return;
  }

  try {
    twoFactorLoading.value = true;
    await api.post("/profile/two-factor/manage/passkey", {
      passkey,
    });
    twoFactorEnabled.value = true;
    user.two_factor_enabled = true;
    closeTwoFactorModal();
    toast.success("Two-factor authentication is now on.");
  } catch (error) {
    toast.error(twoFactorErrorMessage(error, "Unable to save the security passkey."));
  } finally {
    twoFactorLoading.value = false;
  }
};

onMounted(() => {
  fetchUserProfile();
});
</script>

<style scoped>
/* Tokens, type, labels, buttons, links, skeletons and the navbar come from
   src/styles/bloomcraft.css (shared with the landing page and shop pages). */

@layer bc-reset {
  .profile-page :is(p, h1, h2, h3) {
    margin: 0;
  }
}

:global(body) {
  margin: 0;
}

.profile-page {
  min-height: 100vh;
  background: var(--ivory);
}

.profile-page button {
  font-family: var(--font-sans);
}

.profile-btn-outline {
  background: transparent;
  color: var(--ink);
  border-color: var(--line);
}

.profile-btn-outline:hover {
  border-color: var(--ink);
}

.profile-container {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding-top: 150px;
  padding-bottom: var(--section-space);
}

/* ---- Header ---- */
.profile-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 28px;
  margin-bottom: 32px;
}

.profile-info {
  display: flex;
  align-items: center;
  gap: 32px;
}

.avatar-wrapper {
  position: relative;
  flex-shrink: 0;
}

.avatar {
  width: 132px;
  height: 132px;
  border-radius: 50%;
  object-fit: cover;
  background: var(--ivory-deep);
}

.avatar-placeholder {
  display: grid;
  place-items: center;
  color: var(--rosewood-dark);
  font-family: var(--font-display);
  font-size: 48px;
  font-weight: 500;
}

.btn-change-photo {
  position: absolute;
  left: 50%;
  bottom: -10px;
  padding: 7px 14px;
  border: none;
  border-radius: 999px;
  background: var(--ink);
  color: var(--ivory);
  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;
  transform: translateX(-50%);
  cursor: pointer;
  transition: background 0.3s;
}

.btn-change-photo:hover {
  background: var(--rosewood-dark);
}

.user-name {
  margin-top: 18px;
  font-size: clamp(44px, 5vw, 76px);
}

.user-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px 18px;
  margin-top: 12px;
  color: var(--ink-soft);
  font-size: 14px;
}

.verified-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--rosewood-dark);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.verified-badge svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

/* ---- Cards ---- */
.profile-card {
  padding: clamp(28px, 3vw, 44px);
  border: 1px solid var(--line-soft);
  border-radius: 22px;
  background: var(--paper);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 26px;
}

.section-header h2,
.two-factor-content h2 {
  font-size: clamp(32px, 3vw, 44px);
}

.btn-edit {
  border: none;
  background: none;
  cursor: pointer;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  column-gap: 40px;
  border-top: 1px solid var(--line);
}

.detail-item {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 18px 0;
  border-bottom: 1px solid var(--line-soft);
}

.detail-item--wide {
  grid-column: 1 / -1;
}

.detail-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: var(--ivory);
  color: var(--ink);
}

.detail-icon svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.detail-icon--lg {
  width: 52px;
  height: 52px;
}

.detail-icon--lg svg {
  width: 22px;
  height: 22px;
}

.detail-content {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.detail-content label {
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.detail-value {
  font-size: 15px;
  word-break: break-word;
}

.value-with-badge {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.badge {
  padding: 2px 10px;
  border-radius: 999px;
  background: rgba(125, 143, 105, 0.14);
  color: #5d7150;
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.name-fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.input-field {
  width: 100%;
  margin-top: 4px;
  padding: 11px 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: #fff;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 14px;
}

.input-field:focus {
  outline: none;
  border-color: var(--ink);
}

.input-field:disabled {
  background: var(--ivory);
  color: var(--ink-soft);
  cursor: not-allowed;
}

.input-error {
  border-color: var(--rosewood-dark);
}

.field-error {
  color: var(--rosewood-dark);
  font-size: 12.5px;
}

.action-buttons {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 24px;
  margin-top: 28px;
}

.btn-cancel {
  border: none;
  background: none;
  cursor: pointer;
}

.action-buttons .bc-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ---- Two-factor ---- */
.two-factor-section {
  display: flex;
  align-items: center;
  gap: 24px;
}

.two-factor-content {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 8px;
}

.two-factor-content h2 {
  margin-top: 6px;
}

.two-factor-content p:last-child {
  max-width: 520px;
  color: var(--ink-soft);
  font-size: 14.5px;
}

.toggle-switch {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
  cursor: pointer;
}

.toggle-switch input {
  position: absolute;
  opacity: 0;
  width: 1px;
  height: 1px;
}

.toggle-slider {
  position: relative;
  width: 48px;
  height: 28px;
  border-radius: 999px;
  background: var(--line);
  transition: background 0.3s;
}

.toggle-slider::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 2px 6px rgba(30, 27, 24, 0.18);
  transition: transform 0.35s var(--ease-out);
}

.toggle-switch input:checked + .toggle-slider {
  background: var(--ink);
}

.toggle-switch input:checked + .toggle-slider::after {
  transform: translateX(20px);
}

.toggle-switch input:disabled + .toggle-slider {
  opacity: 0.5;
}

.toggle-switch input:focus-visible + .toggle-slider {
  outline: 2px solid var(--rosewood);
  outline-offset: 3px;
}

.toggle-label {
  min-width: 26px;
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

/* ---- Two-factor modal ---- */
.two-factor-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(27, 22, 18, 0.45);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

.two-factor-modal {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  max-width: 460px;
  padding: 40px 34px 32px;
  border-radius: 26px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(27, 22, 18, 0.25);
}

.two-factor-modal h2 {
  font-size: 34px;
}

.two-factor-modal > p:not(.bc-label) {
  margin-bottom: 8px;
  color: var(--ink-soft);
  font-size: 14.5px;
  line-height: 1.7;
}

.two-factor-modal-close {
  position: absolute;
  top: 16px;
  right: 16px;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 50%;
  background: var(--ivory);
  color: var(--ink);
  cursor: pointer;
}

.two-factor-modal-close svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
}

.two-factor-code-boxes {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 18px;
}

.two-factor-code-box {
  width: 100%;
  height: 56px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: #fff;
  color: var(--ink);
  font-family: var(--font-display);
  font-size: 26px;
  text-align: center;
}

.two-factor-code-box:focus {
  outline: none;
  border-color: var(--ink);
}

.btn-two-factor-primary {
  justify-content: center;
  width: 100%;
}

.btn-two-factor-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-change-photo:focus-visible,
.btn-edit:focus-visible,
.btn-cancel:focus-visible,
.two-factor-modal-close:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 3px;
}

/* ---- Skeleton ---- */
.skeleton-avatar {
  width: 132px;
  height: 132px;
  border-radius: 50%;
}

.skeleton-lines {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 10px;
}

.skeleton-line {
  display: block;
  height: 13px;
}

.skeleton-line--label {
  width: 90px;
  height: 10px;
}

.skeleton-line--name {
  width: 280px;
  max-width: 60vw;
  height: 44px;
}

.skeleton-line--short {
  width: 160px;
}

.skeleton-line--title {
  width: 220px;
  height: 32px;
  margin-bottom: 26px;
}

.skeleton-icon {
  width: 38px;
  height: 38px;
  border-radius: 50%;
}

/* ---- Responsive ---- */
@media (max-width: 768px) {
  .profile-container {
    padding-top: 120px;
  }

  .profile-info {
    flex-direction: column;
    align-items: flex-start;
    gap: 22px;
  }

  .avatar,
  .skeleton-avatar {
    width: 104px;
    height: 104px;
  }

  .details-grid {
    grid-template-columns: 1fr;
  }

  .name-fields {
    grid-template-columns: 1fr;
  }

  .two-factor-section {
    flex-direction: column;
    align-items: flex-start;
  }

  .action-buttons {
    flex-direction: column-reverse;
    align-items: stretch;
  }

  .action-buttons .bc-btn {
    justify-content: center;
  }

  .btn-cancel {
    align-self: center;
  }

  .two-factor-modal-backdrop {
    align-items: flex-end;
    padding: 0;
  }

  .two-factor-modal {
    border-radius: 22px 22px 0 0;
  }
}
</style>
