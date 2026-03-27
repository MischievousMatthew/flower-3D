<template>
  <NavHeader
    ref="navHeaderRef"
    :cartCount="cartCount"
    :isAuthenticated="isAuthenticated"
    @scroll-to-section="scrollToSection"
  />
  <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />
  <div class="profile-page">
    <div class="profile-container">
      <!-- Profile Header -->
      <div class="profile-header">
        <div class="header-content">
          <div class="profile-info">
            <div class="avatar-wrapper">
              <img
                :src="user.profile_picture"
                :alt="user.name"
                class="avatar"
              />
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
                Change Photo
              </button>
            </div>
            <div class="user-details">
              <div class="name-section">
                <h1 class="user-name">{{ user.name }} {{ user.surname }}</h1>
                <span v-if="user.is_verified" class="verified-badge">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path
                      d="M5 8L7 10L11 6"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                  </svg>
                  Verified Profile
                </span>
              </div>
              <div class="start-date">
                <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                  <line x1="16" y1="2" x2="16" y2="6" />
                  <line x1="8" y1="2" x2="8" y2="6" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                <span>Start Date: {{ formatDate(user.created_at) }}</span>
              </div>
            </div>
          </div>
          <button class="btn-edit-header" @click="toggleEditMode">
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
              />
              <path
                d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
              />
            </svg>
            {{ isEditing ? "Cancel Editing" : "Edit Profile" }}
          </button>
        </div>
      </div>

      <!-- Profile Details -->
      <div class="profile-details">
        <div class="section-header">
          <h2>Profile details</h2>
          <button v-if="!isEditing" class="btn-edit" @click="toggleEditMode">
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
              />
              <path
                d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
              />
            </svg>
            Edit
          </button>
        </div>

        <div class="details-grid">
          <!-- Full Name -->
          <div class="detail-item">
            <div class="detail-icon">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
            </div>
            <div class="detail-content">
              <label>Full Name</label>
              <div v-if="isEditing" class="name-fields">
                <input
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
                />
              </div>
              <span v-else class="detail-value"
                >{{ user.name }} {{ user.surname }}</span
              >
            </div>
          </div>

          <!-- Email -->
          <div class="detail-item">
            <div class="detail-icon">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                />
                <polyline points="22,6 12,13 2,6" />
              </svg>
            </div>
            <div class="detail-content">
              <label>Email</label>
              <div class="value-with-badge">
                <span class="detail-value">{{ user.email }}</span>
                <span v-if="user.email_verified_at" class="badge verified"
                  >Email Verified</span
                >
              </div>
            </div>
          </div>

          <!-- Date of Birth -->
          <div class="detail-item">
            <div class="detail-icon">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
              </svg>
            </div>
            <div class="detail-content">
              <label>Date of birth</label>
              <!-- ✅ max enforces 18+ rule: only dates ≤ 18 years ago are selectable -->
              <input
                v-if="isEditing"
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
            <div class="detail-icon">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
            </div>
            <div class="detail-content">
              <label>Username</label>
              <input
                v-if="isEditing"
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

          <!-- Number (now editable) -->
          <div class="detail-item">
            <div class="detail-icon">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"
                />
              </svg>
            </div>
            <div class="detail-content">
              <label>Number</label>
              <!-- ✅ Editable; numbers only, capped at 11 digits -->
              <input
                v-if="isEditing"
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
              <div v-else class="value-with-badge">
                <span class="detail-value">{{
                  user.contact_number || "-"
                }}</span>
                <span v-if="user.contact_number" class="badge verified"
                  >Number Verified</span
                >
              </div>
            </div>
          </div>

          <!-- Plan -->
          <div class="detail-item">
            <div class="detail-icon">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M3 3h18v18H3zM9 3v18" />
              </svg>
            </div>
            <div class="detail-content">
              <label>Plan</label>
              <span class="detail-value">{{ user.plan || "Free Plan" }}</span>
            </div>
          </div>

          <!-- Gender -->
          <div class="detail-item">
            <div class="detail-icon">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <circle cx="12" cy="12" r="10" />
              </svg>
            </div>
            <div class="detail-content">
              <label>Gender</label>
              <select
                v-if="isEditing"
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
            <div class="detail-icon">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <circle cx="12" cy="12" r="10" />
                <line x1="2" y1="12" x2="22" y2="12" />
                <path
                  d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"
                />
              </svg>
            </div>
            <div class="detail-content">
              <label>Nationality</label>
              <input
                v-if="isEditing"
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
          <div class="detail-item">
            <div class="detail-icon">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
            </div>
            <div class="detail-content">
              <label>Address</label>
              <input
                v-if="isEditing"
                v-model="editData.address"
                type="text"
                class="input-field"
                placeholder="Address"
              />
              <span v-else class="detail-value">{{ user.address || "-" }}</span>
            </div>
          </div>

          <!-- Region (NEW – UI-only for city filtering, not persisted) -->
          <div class="detail-item">
            <div class="detail-icon">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <polygon points="3 11 22 2 13 21 11 13 3 11" />
              </svg>
            </div>
            <div class="detail-content">
              <label>Region</label>
              <!-- ✅ Region drives the city dropdown; selecting a new region resets city -->
              <select
                v-if="isEditing"
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

          <!-- City (now a dependent dropdown) -->
          <div class="detail-item">
            <div class="detail-icon">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
              </svg>
            </div>
            <div class="detail-content">
              <label>City</label>
              <!-- ✅ Cities filtered by selected region; disabled until region is chosen -->
              <select
                v-if="isEditing"
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
            <div class="detail-icon">
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
            </div>
            <div class="detail-content">
              <label>Postal Code</label>
              <input
                v-if="isEditing"
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

        <!-- Save/Cancel Buttons -->
        <div v-if="isEditing" class="action-buttons">
          <button class="btn-cancel" @click="cancelEdit">Cancel</button>
          <button class="btn-save" @click="saveProfile" :disabled="isSaving">
            {{ isSaving ? "Saving..." : "Save Changes" }}
          </button>
        </div>
      </div>

      <!-- 2-Factor Authentication -->
      <div class="two-factor-section">
        <div class="two-factor-icon">
          <svg
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
        </div>
        <div class="two-factor-content">
          <h3>2-Factor Authentication</h3>
          <p>
            Add an extra layer of security to your account with two-Factor
            authentication.
          </p>
        </div>
        <button class="btn-manage">Manage</button>
      </div>

      <!-- Access History -->
      <div class="access-history">
        <div class="history-header">
          <h2>Access History</h2>
          <div class="history-actions">
            <label class="toggle-switch">
              <input type="checkbox" v-model="newLoginAlerts" />
              <span class="toggle-slider"></span>
              <span class="toggle-label">New Login Alerts</span>
            </label>
            <button class="btn-logout-all">
              Logout of all others sessions
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "../../plugins/axios";
import NavHeader from "../../layouts/NavHeader.vue";
import { useAuth } from "../../composables/useAuth";
import { toast } from "vue3-toastify";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";

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
});

const isEditing = ref(false);
const isLoading = ref(false);
const isLoadingMessage = ref("");
const isSaving = ref(false);
const newLoginAlerts = ref(true);
const navHeaderRef = ref(null);

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
    isLoadingMessage.value = "Preparing your profile...";
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
      contact_number: editData.contact_number || null, // ✅ include phone in payload
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
  const file = event.target.files[0];
  if (!file) return;

  try {
    const token = localStorage.getItem("auth_token");
    const formData = new FormData();
    formData.append("profile_picture", file);

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
    } else {
      toast.error("Failed to upload profile picture");
    }
  } finally {
    if (fileInput.value) {
      fileInput.value.value = "";
    }
  }
};

const scrollToSection = (sectionId) => {
  console.log("Scroll to section:", sectionId);
};

onMounted(() => {
  fetchUserProfile();
});
</script>

<style scoped>
.avatar-wrapper {
  position: relative;
  display: inline-block;
}

.btn-change-photo {
  position: absolute;
  bottom: 0;
  right: 0;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 4px;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-change-photo:hover {
  background: #38a169;
}

.name-fields {
  display: flex;
  gap: 0.5rem;
}

.name-fields .input-field {
  flex: 1;
}

.btn-save:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

* {
  font-family: "Poppins", "Poppins", sans-serif;
}

.logo {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 24px;
  font-weight: 600;
  color: #2d3748;
  text-decoration: none;
}

.profile-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #f0f4ff 0%, #fef5ff 100%);
  padding: 2rem;
}

.profile-container {
  max-width: 1200px;
  margin-top: 70px;
  margin-left: auto;
  margin-right: auto;
}

/* Profile Header */
.profile-header {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  background: linear-gradient(
    135deg,
    rgba(72, 187, 120, 0.1) 0%,
    rgba(85, 60, 154, 0.1) 100%
  );
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.profile-info {
  display: flex;
  gap: 1.5rem;
  align-items: center;
}

.avatar-wrapper {
  position: relative;
}

.avatar {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  object-fit: cover;
  border: 4px solid white;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.user-details {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.name-section {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-name {
  font-size: 2rem;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.verified-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.75rem;
  background: #2d3748;
  color: white;
  border-radius: 20px;
  font-size: 0.813rem;
  font-weight: 500;
}

.start-date {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #718096;
  font-size: 0.938rem;
}

.btn-edit-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 1.25rem;
  background: white;
  color: #2d3748;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.938rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-edit-header:hover {
  background: #f7fafc;
  border-color: #2d3748;
  color: #2d3748;
}

.profile-details {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.section-header h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.btn-edit {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: transparent;
  color: #718096;
  border: none;
  border-radius: 8px;
  font-size: 0.938rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-edit:hover {
  background: #f7fafc;
  color: #2d3748;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
}

.detail-item {
  display: flex;
  gap: 1rem;
}

.detail-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f7fafc;
  border-radius: 8px;
  color: #718096;
  flex-shrink: 0;
}

.detail-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
}

.detail-content label {
  font-size: 0.813rem;
  color: #718096;
  font-weight: 500;
}

.detail-value {
  font-size: 0.938rem;
  color: #2d3748;
  font-weight: 500;
  word-wrap: break-word;
}

.value-with-badge {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.badge {
  padding: 0.25rem 0.625rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
}

.badge.verified {
  background: rgba(45, 55, 72, 0.4);
  color: #2d3748;
}

.input-field {
  padding: 0.625rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.938rem;
  color: #2d3748;
  transition: all 0.3s;
  width: 100%;
}

.input-field:focus {
  outline: none;
  border-color: #2d3748;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

/* ✅ Inline field error */
.field-error {
  font-size: 0.75rem;
  color: #e53e3e;
  margin-top: 0.25rem;
}

.input-error {
  border-color: #e53e3e !important;
  box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.1) !important;
}

/* ✅ Disabled select styling for locked city dropdown */
.input-field:disabled {
  background: #f7fafc;
  color: #a0aec0;
  cursor: not-allowed;
  border-color: #e2e8f0;
}

.action-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid #e2e8f0;
}

.btn-cancel {
  padding: 0.625rem 1.5rem;
  background: #f7fafc;
  color: #2d3748;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.938rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-cancel:hover {
  background: #e2e8f0;
}

.btn-save {
  padding: 0.625rem 1.5rem;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 0.938rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-save:hover {
  background: #38a169;
}

/* 2-Factor Authentication */
.two-factor-section {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  gap: 1.5rem;
  background: rgba(72, 187, 120, 0.05);
}

.two-factor-icon {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #2d3748;
  border-radius: 12px;
  color: white;
  flex-shrink: 0;
}

.two-factor-content {
  flex: 1;
}

.two-factor-content h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 0.375rem 0;
}

.two-factor-content p {
  font-size: 0.938rem;
  color: #718096;
  margin: 0;
}

.btn-manage {
  padding: 0.625rem 2rem;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 0.938rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-manage:hover {
  background: #38a169;
}

/* Access History */
.access-history {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.history-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.history-header h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
}

.history-actions {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.toggle-switch {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
}

.toggle-switch input {
  position: absolute;
  opacity: 0;
}

.toggle-slider {
  position: relative;
  width: 48px;
  height: 24px;
  background: #e2e8f0;
  border-radius: 24px;
  transition: all 0.3s;
}

.toggle-slider::before {
  content: "";
  position: absolute;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: white;
  top: 2px;
  left: 2px;
  transition: all 0.3s;
}

.toggle-switch input:checked + .toggle-slider {
  background: #2d3748;
}

.toggle-switch input:checked + .toggle-slider::before {
  transform: translateX(24px);
}

.toggle-label {
  font-size: 0.938rem;
  color: #2d3748;
  font-weight: 500;
}

.btn-logout-all {
  padding: 0.625rem 1.25rem;
  background: white;
  color: #e53e3e;
  border: 1px solid #e53e3e;
  border-radius: 8px;
  font-size: 0.938rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-logout-all:hover {
  background: #e53e3e;
  color: white;
}

/* Responsive */
@media (max-width: 1024px) {
  .details-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .profile-page {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    gap: 1rem;
  }

  .details-grid {
    grid-template-columns: 1fr;
  }

  .history-header {
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }

  .history-actions {
    flex-direction: column;
    align-items: flex-start;
    width: 100%;
    gap: 1rem;
  }

  .btn-logout-all {
    width: 100%;
  }
}
</style>
