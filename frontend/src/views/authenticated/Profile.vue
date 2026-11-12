<template>
  <NavHeader
    ref="navHeaderRef"
    :cartCount="cartCount"
    :isAuthenticated="isAuthenticated"
    @scroll-to-section="scrollToSection"
  />
  <div class="pf-page bc-theme" :class="{ editing: isEditing }">
    <!-- ── Hero: photo, name, account chips ─────────────────────────────── -->
    <section class="pf-hero" aria-label="Your account">
      <div class="pf-wrap pf-hero__inner">
        <template v-if="isLoading">
          <span class="bc-skeleton pf-skel-avatar"></span>
          <span class="pf-skel-lines">
            <span class="bc-skeleton pf-skel-line w30"></span>
            <span class="bc-skeleton pf-skel-line w60 tall"></span>
            <span class="bc-skeleton pf-skel-line w40"></span>
          </span>
        </template>

        <template v-else>
          <div class="pf-avatar">
            <img v-if="user.profile_picture" :src="user.profile_picture" :alt="`${user.name} ${user.surname}`" @error="handleImageError" />
            <span v-else class="pf-avatar__initials" aria-hidden="true">{{ getInitials(user.name, user.surname) }}</span>
            <input ref="fileInput" type="file" accept="image/*" class="pf-hidden" @change="handleProfilePictureUpload" />
            <button type="button" class="pf-avatar__edit" aria-label="Change profile photo" title="Change photo" @click="triggerFileInput">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z" /><circle cx="12" cy="13" r="3.5" /></svg>
            </button>
          </div>

          <div class="pf-identity">
            <p class="bc-label">Your account</p>
            <h1 class="pf-name">{{ user.name }} <em>{{ user.surname }}</em></h1>
            <p v-if="user.username" class="pf-handle">@{{ user.username }}</p>
            <ul class="pf-chips">
              <li>
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M4 10h16M9 3v4M15 3v4" /></svg>
                Member since {{ formatDate(user.created_at) }}
              </li>
              <li v-if="user.is_verified" class="ok">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.4 3 8.3 7 9.5 4-1.2 7-5.1 7-9.5V6z" /><path d="m9 12 2 2 4-4" /></svg>
                Verified profile
              </li>
              <li v-if="user.email_verified_at" class="ok">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>
                Email verified
              </li>
            </ul>
          </div>

          <div class="pf-hero__actions">
            <button v-if="!isEditing" type="button" class="pf-btn pf-btn--primary" @click="toggleEditMode">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4Z" /><path d="m13.5 6.5 4 4" /></svg>
              Edit profile
            </button>
            <span v-else class="pf-editing-pill" role="status">
              <i aria-hidden="true"></i>
              Editing your profile
            </span>
          </div>
        </template>
      </div>
    </section>

    <div class="pf-wrap pf-layout">
      <!-- ── Side menu ──────────────────────────────────────────────────── -->
      <aside class="pf-side" aria-label="Profile sections">
        <nav class="pf-menu">
          <a
            v-for="s in sections"
            :key="s.id"
            :href="`#${s.id}`"
            class="pf-menu__link"
            :class="{ on: activeSection === s.id }"
            :aria-current="activeSection === s.id ? 'true' : undefined"
            @click.prevent="goToSection(s.id)"
          >
            <span class="pf-menu__ico" aria-hidden="true" v-html="s.icon"></span>
            {{ s.label }}
          </a>
        </nav>

        <div v-if="!isLoading" class="pf-meter">
          <div class="pf-meter__top">
            <strong>Profile {{ completeness.percent }}% complete</strong>
          </div>
          <div class="pf-meter__track" role="progressbar" :aria-valuenow="completeness.percent" aria-valuemin="0" aria-valuemax="100" aria-label="Profile complete">
            <span :style="{ width: `${completeness.percent}%` }"></span>
          </div>
          <p v-if="completeness.missing.length">Add your {{ completeness.missing.slice(0, 2).join(" and ") }} so shops can deliver without asking.</p>
          <p v-else>Everything's filled in. Shops have what they need.</p>
        </div>
      </aside>

      <main class="pf-main">
        <!-- Loading -->
        <template v-if="isLoading">
          <section class="pf-card" aria-label="Loading your profile">
            <span class="bc-skeleton pf-skel-line w30 tall"></span>
            <div class="pf-tiles">
              <div v-for="n in 6" :key="n" class="pf-tile">
                <span class="bc-skeleton pf-skel-ico"></span>
                <span class="pf-skel-lines">
                  <span class="bc-skeleton pf-skel-line w40"></span>
                  <span class="bc-skeleton pf-skel-line w60"></span>
                </span>
              </div>
            </div>
          </section>
        </template>

        <template v-else>
          <!-- ── Personal info ──────────────────────────────────────────── -->
          <section id="pf-personal" class="pf-card" aria-labelledby="pf-personal-title">
            <header class="pf-card__head">
              <div>
                <h2 id="pf-personal-title">Personal <em>info</em></h2>
                <p>How shops know you and reach you.</p>
              </div>
              <button v-if="!isEditing" type="button" class="pf-btn pf-btn--ghost" @click="toggleEditMode">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4Z" /><path d="m13.5 6.5 4 4" /></svg>
                Edit
              </button>
            </header>

            <!-- Reading -->
            <dl v-if="!isEditing" class="pf-tiles">
              <div v-for="row in infoRows" :key="row.label" class="pf-tile">
                <span class="pf-tile__ico" aria-hidden="true" v-html="row.icon"></span>
                <span class="pf-tile__body">
                  <dt>{{ row.label }}</dt>
                  <dd :class="{ empty: !row.value }">
                    {{ row.value || "Not added yet" }}
                    <span v-if="row.badge" class="pf-badge">{{ row.badge }}</span>
                  </dd>
                </span>
              </div>
            </dl>

            <!-- Editing -->
            <div v-else class="pf-form">
              <div class="pf-field">
                <label for="pf-name">First name</label>
                <input id="pf-name" v-model="editData.name" type="text" class="pf-input" autocomplete="given-name" />
              </div>
              <div class="pf-field">
                <label for="pf-surname">Last name</label>
                <input id="pf-surname" v-model="editData.surname" type="text" class="pf-input" autocomplete="family-name" />
              </div>
              <div class="pf-field">
                <label for="pf-username">Username</label>
                <input id="pf-username" v-model="editData.username" type="text" class="pf-input" autocomplete="username" />
              </div>
              <div class="pf-field">
                <label>Email</label>
                <p class="pf-locked">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
                  {{ user.email }}
                </p>
              </div>
              <div class="pf-field">
                <label for="pf-number">Mobile number</label>
                <!-- Types as 09XX XXX XXXX -->
                <input
                  id="pf-number"
                  v-model="editData.contact_number"
                  v-phone
                  type="tel"
                  class="pf-input"
                  :class="{ bad: contactNumberError }"
                  autocomplete="tel"
                  :aria-invalid="contactNumberError ? 'true' : 'false'"
                  :aria-describedby="contactNumberError ? 'pf-number-error' : undefined"
                />
                <span v-if="contactNumberError" id="pf-number-error" class="pf-error">{{ contactNumberError }}</span>
              </div>
              <div class="pf-field">
                <label for="pf-dob">Date of birth</label>
                <!-- max enforces the 18+ rule -->
                <input id="pf-dob" v-model="editData.date_of_birth" type="date" class="pf-input" :max="maxBirthDate" autocomplete="bday" />
              </div>
              <div class="pf-field">
                <label for="pf-gender">Gender</label>
                <select id="pf-gender" v-model="editData.gender" class="pf-input">
                  <option value="">Prefer not to say</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="pf-field">
                <label for="pf-nationality">Nationality</label>
                <input id="pf-nationality" v-model="editData.nationality" type="text" class="pf-input" placeholder="e.g. Filipino" />
              </div>
            </div>
          </section>

          <!-- ── Delivery address ───────────────────────────────────────── -->
          <section id="pf-address" class="pf-card" aria-labelledby="pf-address-title">
            <header class="pf-card__head">
              <div>
                <h2 id="pf-address-title">Delivery <em>address</em></h2>
                <p>
                  {{
                    isEditing
                      ? "Click the map or drag the pin to where your flowers should arrive, anywhere in Cavite or Metro Manila. Region, city and postal code fill in from the pin."
                      : "Where your flowers are delivered."
                  }}
                </p>
              </div>
              <button v-if="!isEditing" type="button" class="pf-btn pf-btn--ghost" @click="toggleEditMode">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z" /><circle cx="12" cy="9.5" r="2.5" /></svg>
                {{ hasSavedPin ? "Move pin" : "Add pin" }}
              </button>
            </header>

            <div class="pf-address">
              <div class="pf-map">
                <div ref="locationMapEl" class="location-map" role="region" aria-label="Map of your delivery location"></div>
                <button v-if="isEditing" type="button" class="pf-map__locate" :disabled="isLocating" @click="useCurrentLocation">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3" /><circle cx="12" cy="12" r="7" /></svg>
                  {{ isLocating ? "Finding you..." : "Use my current location" }}
                </button>
                <p v-if="!isEditing && !hasSavedPin" class="pf-map__empty">No location pinned yet. Choose Add pin to drop one.</p>
              </div>

              <div class="pf-address__fields">
                <div class="pf-field">
                  <label :for="isEditing ? 'pf-street' : undefined">Street, block or unit</label>
                  <input
                    v-if="isEditing"
                    id="pf-street"
                    v-model="editData.address"
                    type="text"
                    class="pf-input"
                    placeholder="e.g. Blk 5 Lot 12, Sampaguita St."
                    autocomplete="address-line1"
                  />
                  <p v-else class="pf-value" :class="{ empty: !user.address }">{{ user.address || "Not added yet" }}</p>
                  <span v-if="isEditing && pinStreetHint" class="pf-hint">Near {{ pinStreetHint }}</span>
                </div>

                <div class="pf-address__auto">
                  <div class="pf-field">
                    <label>City</label>
                    <p class="pf-value" :class="{ auto: isEditing, empty: !(isEditing ? editData.city : user.city) }">{{ (isEditing ? editData.city : user.city) || "—" }}</p>
                  </div>
                  <div class="pf-field">
                    <label :for="isEditing && postalNeedsTyping ? 'pf-postal' : undefined">Postal code</label>
                    <!-- Only typed by hand when the map has no postal code for the pin -->
                    <input
                      v-if="isEditing && postalNeedsTyping"
                      id="pf-postal"
                      v-model="editData.postal_code"
                      type="text"
                      inputmode="numeric"
                      maxlength="4"
                      class="pf-input"
                      placeholder="e.g. 4109"
                      autocomplete="postal-code"
                    />
                    <p v-else class="pf-value" :class="{ auto: isEditing, empty: !(isEditing ? editData.postal_code : user.postal_code) }">{{ (isEditing ? editData.postal_code : user.postal_code) || "—" }}</p>
                  </div>
                  <div class="pf-field pf-field--wide">
                    <label>Region</label>
                    <p class="pf-value" :class="{ auto: isEditing, empty: !(isEditing ? editData.region : user.region) }">{{ (isEditing ? editData.region : user.region) || "—" }}</p>
                  </div>
                </div>

                <p v-if="isEditing" class="pf-pin-status" :class="{ bad: lookupError }" aria-live="polite">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z" /><circle cx="12" cy="9.5" r="2.5" /></svg>
                  {{
                    isLookingUp
                      ? "Finding the address for this pin..."
                      : lookupError || (editData.latitude === null ? "Drop a pin on the map to fill these in." : "Filled in from your pin.")
                  }}
                </p>
              </div>
            </div>
          </section>

          <!-- ── Flowers you love (preferences, special days, favourites) ── -->
          <div v-if="user.role === 'customer'" id="pf-preferences" class="pf-prefs">
            <ProfilePreferences />
          </div>

          <!-- ── Security ───────────────────────────────────────────────── -->
          <section v-if="user.role === 'customer'" id="pf-security" class="pf-card" aria-labelledby="pf-security-title">
            <header class="pf-card__head">
              <div>
                <h2 id="pf-security-title">Sign-in <em>security</em></h2>
                <p>Keep your account yours.</p>
              </div>
            </header>

            <div class="pf-security">
              <span class="pf-security__ico" :class="{ on: twoFactorEnabled }" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.4 3 8.3 7 9.5 4-1.2 7-5.1 7-9.5V6z" /><rect x="9" y="11" width="6" height="5" rx="1.2" /><path d="M10.2 11V9.6a1.8 1.8 0 0 1 3.6 0V11" /></svg>
              </span>
              <div class="pf-security__text">
                <strong>
                  Two-step sign-in
                  <span class="pf-state" :class="{ on: twoFactorEnabled }">{{ twoFactorEnabled ? "On" : "Off" }}</span>
                </strong>
                <p>When it's on, signing in also asks for a six-digit passkey only you know.</p>
              </div>
              <!-- Keyed on the modal so the switch re-syncs if it is closed early -->
              <label :key="`2fa-${twoFactorEnabled}-${showTwoFactorModal}`" class="pf-switch">
                <input
                  type="checkbox"
                  role="switch"
                  :checked="twoFactorEnabled"
                  :disabled="twoFactorLoading"
                  :aria-checked="twoFactorEnabled"
                  aria-label="Two-step sign-in"
                  @change="beginTwoFactorChange"
                />
                <span aria-hidden="true"></span>
              </label>
            </div>
          </section>
        </template>
      </main>
    </div>

    <!-- ── Save bar while editing ────────────────────────────────────────── -->
    <Transition name="pf-bar">
      <div v-if="isEditing" class="pf-savebar" role="region" aria-label="Save your changes">
        <div class="pf-savebar__inner">
          <p>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4Z" /></svg>
            You're editing your profile.
          </p>
          <div class="pf-savebar__actions">
            <button type="button" class="pf-btn pf-btn--quiet" :disabled="isSaving" @click="cancelEdit">Cancel</button>
            <button type="button" class="pf-btn pf-btn--primary" :disabled="isSaving" @click="saveProfile">
              {{ isSaving ? "Saving..." : "Save changes" }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Two-step sign-in setup ────────────────────────────────────────── -->
    <div v-if="showTwoFactorModal" class="pf-overlay" @click.self="closeTwoFactorModal">
      <section class="pf-modal" role="dialog" aria-modal="true" aria-labelledby="two-factor-title" @keydown.esc="closeTwoFactorModal">
        <button type="button" class="pf-modal__close" aria-label="Close two-step sign-in setup" @click="closeTwoFactorModal">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
        </button>
        <span class="pf-modal__badge" aria-hidden="true">
          <svg v-if="twoFactorStep === 'email'" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>
          <svg v-else viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
        </span>

        <template v-if="twoFactorStep === 'email'">
          <p class="pf-modal__step">Step 1 of 2</p>
          <h2 id="two-factor-title">Check your <em>email</em></h2>
          <p class="pf-modal__text">Enter the six-digit code we sent to your email address.</p>
          <form @submit.prevent="verifyInitialEmailOtp">
            <div class="pf-code" @paste.prevent="pasteTwoFactorCode($event, 'otp')">
              <input v-for="(_, index) in twoFactorOtp" :key="`setup-otp-${index}`" :ref="(element) => setTwoFactorInput(element, 'otp', index)" v-model="twoFactorOtp[index]" class="pf-code__box" inputmode="numeric" maxlength="1" autocomplete="one-time-code" :aria-label="`Digit ${index + 1}`" @input="advanceTwoFactorCode('otp', index)" @keydown.backspace="retreatTwoFactorCode($event, 'otp', index)" />
            </div>
            <button class="pf-btn pf-btn--primary pf-btn--block" :disabled="twoFactorLoading">
              {{ twoFactorLoading ? "Checking..." : "Verify code" }}
            </button>
          </form>
        </template>

        <template v-else>
          <p class="pf-modal__step">Step 2 of 2</p>
          <h2 id="two-factor-title">Choose a <em>passkey</em></h2>
          <p class="pf-modal__text">Pick six digits only you know. You'll enter them each time you sign in.</p>
          <form @submit.prevent="saveTwoFactorPasskey">
            <div class="pf-code" @paste.prevent="pasteTwoFactorCode($event, 'passkey')">
              <input v-for="(_, index) in twoFactorPasskey" :key="`setup-passkey-${index}`" :ref="(element) => setTwoFactorInput(element, 'passkey', index)" v-model="twoFactorPasskey[index]" class="pf-code__box" type="password" inputmode="numeric" maxlength="1" autocomplete="new-password" :aria-label="`Digit ${index + 1}`" @input="advanceTwoFactorCode('passkey', index)" @keydown.backspace="retreatTwoFactorCode($event, 'passkey', index)" />
            </div>
            <button class="pf-btn pf-btn--primary pf-btn--block" :disabled="twoFactorLoading">
              {{ twoFactorLoading ? "Saving..." : "Save passkey" }}
            </button>
          </form>
        </template>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick, watch } from "vue";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";
import {
  isInServiceArea,
  serviceAreaBounds,
  serviceAreaRings,
  SERVICE_AREA_MESSAGE,
} from "../../utils/serviceArea";
import { useRouter } from "vue-router";
import api from "../../plugins/axios";
import { PHONE_MESSAGE, isPhone } from "../../utils/phone";
import NavHeader from "../../layouts/NavHeader.vue";
import ProfilePreferences from "../../components/customer/ProfilePreferences.vue";
import { useAuth } from "../../composables/useAuth";
import { toast } from "@/utils/snackbar";
import "../../styles/bloomcraft.css";
import {
  buildMultipartFormData,
  clearFileInput,
  getSelectedFile,
  validateImageFile,
} from "../../utils/imageUpload";

const router = useRouter();
const { isAuthenticated, user: authUser } = useAuth();
const fileInput = ref(null);
const { logout } = useAuth();

// ─── Contact number helpers ───────────────────────────────────────────────────

/** Live error message shown beneath the field */
const contactNumberError = computed(() => {
  const val = editData.contact_number;
  if (!val) return ""; // empty is allowed (nullable on backend)
  return isPhone(val) ? "" : PHONE_MESSAGE;
});

// ─── Delivery location (map pin → region, city, postal code) ──────────────────
const DEFAULT_CENTER = [14.2822, 120.8684]; // Cavite, where the shops are
const locationMapEl = ref(null);
const isLookingUp = ref(false);
const isLocating = ref(false);
const lookupError = ref("");
const pinStreetHint = ref("");
const postalNeedsTyping = ref(false);
let locationMap = null;
let locationMarker = null;
let lookupTimer = null;
let lookupRequestId = 0;

// Deliveries cover Cavite and Metro Manila only. Clicks are checked against
// the real outline (src/assets/serviceArea.json) before the pin moves; the
// address lookup is a second check right at the border.
const SERVICE_AREA_LIMITS = L.latLngBounds(serviceAreaBounds);
const isInAddressArea = (address = {}) =>
  /cavite/i.test(address.state || address.province || "") ||
  /metro manila|national capital/i.test(address.region || "");

// One toast at a time, however many times the customer clicks outside
const warnOutsideArea = () => {
  toast.error(SERVICE_AREA_MESSAGE, { toastId: "outside-service-area", autoClose: 3500 });
};

// The last pin that passed both checks, restored if the address lookup
// rejects a new one
let lastValidPin = null;
const rememberValidPin = () => {
  lastValidPin =
    editData.latitude === null
      ? null
      : {
          latitude: editData.latitude,
          longitude: editData.longitude,
          region: editData.region,
          city: editData.city,
          postal_code: editData.postal_code,
        };
};
const restoreLastValidPin = () => {
  pinStreetHint.value = "";
  if (lastValidPin) {
    editData.region = lastValidPin.region;
    editData.city = lastValidPin.city;
    editData.postal_code = lastValidPin.postal_code;
    postalNeedsTyping.value = !lastValidPin.postal_code;
    setPin(lastValidPin.latitude, lastValidPin.longitude, { lookUp: false });
  } else {
    editData.latitude = null;
    editData.longitude = null;
    editData.region = "";
    editData.city = "";
    editData.postal_code = "";
    postalNeedsTyping.value = false;
    locationMarker?.remove();
    locationMarker = null;
  }
};

const pinIcon = L.icon({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

const hasSavedPin = computed(
  () => Number.isFinite(Number(user.latitude)) && user.latitude !== null && user.longitude !== null,
);

// Nominatim's address parts for the Philippines: city or town, the province
// in `state` (absent in Metro Manila), and the region.
const describePlace = (address = {}) => {
  const town = address.city || address.town || address.municipality || address.village || "";
  const province = address.state || address.province || "";
  return {
    region: address.region || address.state_district || "",
    city: town && province && !town.includes(province) ? `${town}, ${province}` : town || province,
    postal: address.postcode || "",
    street: [address.road, address.quarter || address.suburb || address.neighbourhood]
      .filter(Boolean)
      .join(", "),
  };
};

const lookUpPin = async (latitude, longitude) => {
  const requestId = ++lookupRequestId;
  isLookingUp.value = true;
  lookupError.value = "";
  try {
    const params = new URLSearchParams({
      lat: String(latitude),
      lon: String(longitude),
      format: "jsonv2",
      addressdetails: "1",
      zoom: "18",
      "accept-language": "en",
    });
    const response = await fetch(`https://nominatim.openstreetmap.org/reverse?${params}`);
    if (!response.ok) throw new Error(`Lookup failed (${response.status})`);
    const data = await response.json();
    if (requestId !== lookupRequestId) return; // a newer pin replaced this one

    // Right on the border the map outline and the address can disagree
    if (data?.address?.country_code !== "ph" || !isInAddressArea(data.address)) {
      warnOutsideArea();
      restoreLastValidPin();
      return;
    }

    const place = describePlace(data.address);
    if (!place.city) {
      lookupError.value = "We couldn't tell which city this is. Try moving the pin a little.";
      restoreLastValidPin();
      return;
    }
    editData.region = place.region;
    editData.city = place.city;
    editData.postal_code = place.postal;
    postalNeedsTyping.value = !place.postal;
    pinStreetHint.value = place.street;
    rememberValidPin();
  } catch (error) {
    if (requestId !== lookupRequestId) return;
    console.error("Reverse geocoding failed:", error);
    lookupError.value = "We couldn't look up this pin. Check your connection and try again.";
    restoreLastValidPin();
  } finally {
    if (requestId === lookupRequestId) isLookingUp.value = false;
  }
};

let dragStartPosition = null;
const addMarker = (position) => {
  locationMarker = L.marker(position, { icon: pinIcon, draggable: isEditing.value }).addTo(locationMap);
  locationMarker.on("dragstart", () => {
    dragStartPosition = locationMarker.getLatLng();
  });
  locationMarker.on("dragend", onMarkerDragEnd);
};

const setPin = (latitude, longitude, { lookUp = true, pan = false } = {}) => {
  editData.latitude = Number(latitude.toFixed(7));
  editData.longitude = Number(longitude.toFixed(7));
  const position = [editData.latitude, editData.longitude];

  if (locationMarker) locationMarker.setLatLng(position);
  else if (locationMap) addMarker(position);
  if (pan && locationMap) locationMap.setView(position, Math.max(locationMap.getZoom(), 16));

  if (lookUp) {
    // Nominatim allows about one request a second; wait for the pin to settle
    clearTimeout(lookupTimer);
    isLookingUp.value = true;
    lookupTimer = setTimeout(() => lookUpPin(editData.latitude, editData.longitude), 600);
  }
};

// Outside the area the pin doesn't move at all; the customer gets a toast
const placePin = (latitude, longitude, options = {}) => {
  if (!isInServiceArea(latitude, longitude)) {
    warnOutsideArea();
    return;
  }
  lookupError.value = "";
  setPin(latitude, longitude, options);
};

const onMapClick = (event) => {
  if (isEditing.value) placePin(event.latlng.lat, event.latlng.lng);
};

// A pin dropped outside the area goes back to where the drag started
function onMarkerDragEnd() {
  const point = locationMarker.getLatLng();
  if (!isInServiceArea(point.lat, point.lng)) {
    warnOutsideArea();
    if (dragStartPosition) locationMarker.setLatLng(dragStartPosition);
    return;
  }
  lookupError.value = "";
  setPin(point.lat, point.lng);
}

// Red line along the Cavite / Metro Manila border, with everything outside
// it lightly tinted red
const drawServiceArea = () => {
  const world = [
    [-85, -180],
    [-85, 180],
    [85, 180],
    [85, -180],
  ];
  L.polygon([world, ...serviceAreaRings], {
    color: "#c53030",
    weight: 2,
    opacity: 0.9,
    fillColor: "#c53030",
    fillOpacity: 0.12,
    interactive: false, // clicks fall through to the map, which shows the toast
  }).addTo(locationMap);
};

const renderLocationMap = async () => {
  await nextTick();
  if (!locationMapEl.value) return;

  const latitude = isEditing.value ? editData.latitude : user.latitude;
  const longitude = isEditing.value ? editData.longitude : user.longitude;
  const hasPin = latitude !== null && latitude !== undefined && longitude !== null && longitude !== undefined;
  const center = hasPin ? [Number(latitude), Number(longitude)] : DEFAULT_CENTER;

  if (!locationMap) {
    locationMap = L.map(locationMapEl.value, {
      scrollWheelZoom: false,
      // Keep the map on Cavite and Metro Manila
      maxBounds: SERVICE_AREA_LIMITS.pad(0.2),
      maxBoundsViscosity: 1,
      minZoom: 9,
    }).setView(center, hasPin ? 16 : 10);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: "© OpenStreetMap contributors",
    }).addTo(locationMap);
    drawServiceArea();
    locationMap.on("click", onMapClick);
  } else {
    locationMap.setView(center, hasPin ? 16 : locationMap.getZoom());
    locationMap.invalidateSize();
  }

  locationMarker?.remove();
  locationMarker = null;
  if (hasPin) addMarker(center);
  locationMapEl.value.classList.toggle("is-editing", isEditing.value);
};

const useCurrentLocation = () => {
  if (!navigator.geolocation) {
    lookupError.value = "Your browser can't share your location. Drop the pin on the map instead.";
    return;
  }
  isLocating.value = true;
  navigator.geolocation.getCurrentPosition(
    ({ coords }) => {
      isLocating.value = false;
      placePin(coords.latitude, coords.longitude, { pan: true });
    },
    () => {
      isLocating.value = false;
      lookupError.value = "Location access was blocked. Drop the pin on the map instead.";
    },
    { enableHighAccuracy: true, timeout: 10000 },
  );
};

onBeforeUnmount(() => {
  clearTimeout(lookupTimer);
  locationMap?.remove();
  locationMap = null;
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
  region: "",
  city: "",
  postal_code: "",
  latitude: null,
  longitude: null,
  profile_picture: "",
  two_factor_passkey_configured: false,
  two_factor_enabled: false,
});

const isEditing = ref(false);
// The save snackbar is showing: other snackbars move up above it
watch(isEditing, (on) => document.body.classList.toggle("pf-saving-open", on));
onBeforeUnmount(() => document.body.classList.remove("pf-saving-open"));
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
  // Filled from the map pin
  region: "",
  latitude: null,
  longitude: null,
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
    editData.region = user.region || "";
    editData.latitude = user.latitude ?? null;
    editData.longitude = user.longitude ?? null;
    lookupError.value = "";
    pinStreetHint.value = "";
    postalNeedsTyping.value = editData.latitude !== null && !editData.postal_code;
    rememberValidPin(); // the saved pin is where a rejected pin snaps back to
  }
  isEditing.value = !isEditing.value;
  renderLocationMap();
};

const cancelEdit = () => {
  clearTimeout(lookupTimer);
  lookupRequestId += 1; // ignore a lookup still in flight
  isLookingUp.value = false;
  isEditing.value = false;
  renderLocationMap();
};

const saveProfile = async () => {
  // Contact number must be empty or 09XX XXX XXXX
  if (editData.contact_number && !isPhone(editData.contact_number)) {
    toast.error(PHONE_MESSAGE);
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

  if (isLookingUp.value) {
    toast.info("Still finding the address for your pin. Try again in a moment.");
    return;
  }

  if (editData.latitude !== null && !editData.city) {
    toast.error("Move your pin so we can tell which city it's in.");
    return;
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
      region: editData.region || null,
      city: editData.city || null,
      postal_code: editData.postal_code || null,
      latitude: editData.latitude,
      longitude: editData.longitude,
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
      renderLocationMap();
      // Keep the signed-in user in sync so the Shop's "Close to you" uses the new pin
      if (authUser.value) {
        const { region, city, postal_code, address, latitude, longitude } = response.data.user;
        authUser.value = { ...authUser.value, region, city, postal_code, address, latitude, longitude };
        localStorage.setItem("user", JSON.stringify(authUser.value));
      }
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

// ── Side menu, profile meter and info tiles ─────────────────────────────────
const ICONS = {
  person: '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5" /><path d="M5 20a7 7 0 0 1 14 0" /></svg>',
  pin: '<svg viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z" /><circle cx="12" cy="9.5" r="2.5" /></svg>',
  flower: '<svg viewBox="0 0 24 24"><circle cx="12" cy="9" r="2.5" /><path d="M12 6.5a2.5 2.5 0 1 1 2.4 3.2M9.6 9.7A2.5 2.5 0 1 1 12 6.5M14.4 9.7a2.5 2.5 0 1 1-2.4 3.8M12 13.5a2.5 2.5 0 1 1-2.4-3.8M12 13.5V21M12 18c-2 0-3.5-1.2-4-3" /></svg>',
  shield: '<svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.4 3 8.3 7 9.5 4-1.2 7-5.1 7-9.5V6z" /><path d="m9 12 2 2 4-4" /></svg>',
  at: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" /><path d="M16 12v1.5a2.5 2.5 0 0 0 5 0V12a9 9 0 1 0-3.5 7.1" /></svg>',
  mail: '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>',
  phone: '<svg viewBox="0 0 24 24"><rect x="7" y="3" width="10" height="18" rx="2.5" /><path d="M11 18h2" /></svg>',
  cake: '<svg viewBox="0 0 24 24"><path d="M4 21h16v-8H4zM4 15c2.7 1.3 5.3 1.3 8 0s5.3-1.3 8 0M12 9V6M12 3.5v.01" /></svg>',
  gender: '<svg viewBox="0 0 24 24"><circle cx="10" cy="14" r="5" /><path d="M14 10l6-6M15 4h5v5" /></svg>',
  globe: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" /><path d="M3.5 12h17M12 3.5a13 13 0 0 1 0 17M12 3.5a13 13 0 0 0 0 17" /></svg>',
};

const sections = computed(() => [
  { id: "pf-personal", label: "Personal info", icon: ICONS.person },
  { id: "pf-address", label: "Delivery address", icon: ICONS.pin },
  ...(user.role === "customer"
    ? [
        { id: "pf-preferences", label: "Flowers you love", icon: ICONS.flower },
        { id: "pf-security", label: "Security", icon: ICONS.shield },
      ]
    : []),
]);

// The section in view is the one lit in the menu. On every scroll it is the
// last section whose top has passed the reading line just under the navbar;
// at the very bottom of the page it is the last section, however short it is.
const activeSection = ref("pf-personal");
// px from the top of the window: under the navbar, or under the pill menu on phones
const readingLine = () => (window.innerWidth <= 960 ? 190 : 140);
let clickedTarget = null; // a menu click: keep it lit while the page glides there
let clickedTimer = 0;

function updateActiveSection() {
  if (clickedTarget) return;
  const list = sections.value;
  if (!list.length) return;
  const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
  if (atBottom) {
    activeSection.value = list[list.length - 1].id;
    return;
  }
  let current = list[0].id;
  for (const section of list) {
    const el = document.getElementById(section.id);
    if (el && el.getBoundingClientRect().top <= readingLine()) current = section.id;
  }
  activeSection.value = current;
}

let spyFrame = 0;
const onSpyScroll = () => {
  cancelAnimationFrame(spyFrame);
  spyFrame = requestAnimationFrame(updateActiveSection);
};

// The glide from a menu click has ended: back to following the scroll
const releaseClick = () => {
  if (!clickedTarget) return;
  clickedTarget = null;
  clearTimeout(clickedTimer);
  updateActiveSection();
};

const goToSection = (id) => {
  activeSection.value = id;
  clickedTarget = id;
  clearTimeout(clickedTimer);
  clickedTimer = setTimeout(releaseClick, 1200); // in case the browser has no scrollend
  const still = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  document.getElementById(id)?.scrollIntoView({ behavior: still ? "auto" : "smooth", block: "start" });
};

function watchSections() {
  window.addEventListener("scroll", onSpyScroll, { passive: true });
  window.addEventListener("resize", onSpyScroll, { passive: true });
  window.addEventListener("scrollend", releaseClick);
  updateActiveSection();
}
onBeforeUnmount(() => {
  window.removeEventListener("scroll", onSpyScroll);
  window.removeEventListener("resize", onSpyScroll);
  window.removeEventListener("scrollend", releaseClick);
  cancelAnimationFrame(spyFrame);
  clearTimeout(clickedTimer);
});

// Personal info, as tiles while reading
const infoRows = computed(() => [
  { label: "Full name", value: [user.name, user.surname].filter(Boolean).join(" "), icon: ICONS.person },
  { label: "Username", value: user.username ? `@${user.username}` : "", icon: ICONS.at },
  { label: "Email", value: user.email, icon: ICONS.mail, badge: user.email_verified_at ? "Verified" : "" },
  { label: "Mobile number", value: user.contact_number, icon: ICONS.phone },
  { label: "Date of birth", value: user.date_of_birth ? formatDate(user.date_of_birth) : "", icon: ICONS.cake },
  { label: "Gender", value: user.gender, icon: ICONS.gender },
  { label: "Nationality", value: user.nationality, icon: ICONS.globe },
]);

// How much of what shops need is filled in, and what to add next
const completeness = computed(() => {
  const checks = [
    ["photo", !!user.profile_picture],
    ["mobile number", !!user.contact_number],
    ["delivery pin", user.latitude !== null && user.latitude !== undefined],
    ["street address", !!user.address],
    ["date of birth", !!user.date_of_birth],
    ["username", !!user.username],
  ];
  const done = checks.filter(([, ok]) => ok).length;
  return {
    percent: Math.round((done / checks.length) * 100),
    missing: checks.filter(([, ok]) => !ok).map(([label]) => label),
  };
});

onMounted(async () => {
  await fetchUserProfile();
  renderLocationMap(); // the map container exists once the loading skeleton is gone
  nextTick(() => {
    watchSections();
    // A link to one part of the profile (e.g. "Flowers you love" from a product page)
    const id = decodeURIComponent((window.location.hash || "").slice(1));
    if (/^pf-[a-z-]+$/.test(id) && document.getElementById(id)) goToSection(id);
  });
});
</script>

<style scoped>
/* Profile. Tokens, type and the navbar come from styles/bloomcraft.css
   (the logo's navy --ink and green --leaf, Cormorant headings, Poppins text). */

@layer bc-reset {
  .pf-page :is(p, h1, h2, h3, dl, dd, ul) {
    margin: 0;
  }
}

.pf-page {
  --card: #ffffff;
  --green-bg: #e4f3ea;
  --red: #b4332a;
  min-height: 100vh;
  padding-bottom: 96px;
  background: var(--ivory);
  color: var(--ink);
  font-family: var(--font-sans);
}

.pf-page.editing {
  padding-bottom: 160px; /* room for the save bar */
}

.pf-wrap {
  width: min(1120px, 100% - 32px);
  margin-inline: auto;
}

.pf-hidden {
  display: none;
}

/* ── Buttons ──────────────────────────────────────────────── */
.pf-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 20px;
  border: 1px solid transparent;
  border-radius: 999px;
  font: 500 14px var(--font-sans);
  white-space: nowrap;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.pf-btn svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pf-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.pf-btn--primary {
  background: var(--ink);
  color: var(--paper);
}

.pf-btn--primary:hover:not(:disabled) {
  background: var(--leaf-deep);
}

.pf-btn--ghost {
  min-height: 40px;
  padding: 0 16px;
  border-color: var(--line);
  background: var(--card);
  color: var(--ink);
  font-size: 13.5px;
}

.pf-btn--ghost:hover:not(:disabled) {
  border-color: var(--leaf);
  color: var(--leaf-deep);
}

.pf-btn--quiet {
  background: none;
  color: var(--ink-soft);
}

.pf-btn--quiet:hover:not(:disabled) {
  background: var(--ivory-deep);
  color: var(--ink);
}

.pf-btn--block {
  width: 100%;
}

:is(.pf-btn, .pf-menu__link, .pf-avatar__edit, .pf-map__locate, .pf-modal__close):focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

/* ── Hero ─────────────────────────────────────────────────── */
.pf-hero {
  padding: clamp(118px, 14vw, 140px) 0 36px;
  border-bottom: 1px solid var(--line);
  background:
    radial-gradient(60% 120% at 0% 0%, rgba(77, 177, 115, 0.14), transparent 60%),
    radial-gradient(50% 120% at 100% 0%, rgba(54, 63, 78, 0.08), transparent 60%),
    var(--paper);
}

.pf-hero__inner {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 28px;
}

.pf-avatar {
  position: relative;
  width: 120px;
  height: 120px;
}

.pf-avatar img,
.pf-avatar__initials {
  width: 100%;
  height: 100%;
  border: 4px solid var(--card);
  border-radius: 50%;
  object-fit: cover;
  box-shadow: 0 0 0 1px var(--line), 0 18px 40px -18px rgba(54, 63, 78, 0.45);
}

.pf-avatar__initials {
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, var(--leaf) 0%, var(--leaf-deep) 100%);
  color: #ffffff;
  font-family: var(--font-display);
  font-size: 44px;
  font-weight: 500;
}

.pf-avatar__edit {
  position: absolute;
  right: 2px;
  bottom: 2px;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 3px solid var(--card);
  border-radius: 50%;
  background: var(--ink);
  color: var(--paper);
  cursor: pointer;
  transition: background 0.2s ease;
}

.pf-avatar__edit:hover {
  background: var(--leaf-deep);
}

.pf-avatar__edit svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linejoin: round;
}

.pf-identity {
  min-width: 0;
}

.pf-name {
  margin-top: 8px !important;
  font-family: var(--font-display);
  font-size: clamp(36px, 4.6vw, 56px);
  font-weight: 500;
  line-height: 1;
  letter-spacing: -0.01em;
}

.pf-name em {
  color: var(--leaf-deep);
  font-style: italic;
}

.pf-handle {
  margin-top: 6px !important;
  color: var(--ink-soft);
  font-size: 15px;
}

.pf-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 14px !important;
  padding: 0;
  list-style: none;
}

.pf-chips li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--card);
  color: var(--ink-soft);
  font-size: 12.5px;
  font-weight: 500;
}

.pf-chips li.ok {
  border-color: transparent;
  background: var(--green-bg);
  color: var(--leaf-deep);
}

.pf-chips svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pf-editing-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 999px;
  background: var(--green-bg);
  color: var(--leaf-deep);
  font-size: 13.5px;
  font-weight: 600;
}

.pf-editing-pill i {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--leaf);
  animation: pf-blink 1.4s ease-in-out infinite;
}

@keyframes pf-blink {
  50% { opacity: 0.35; }
}

/* ── Layout ───────────────────────────────────────────────── */
.pf-layout {
  display: grid;
  grid-template-columns: 250px minmax(0, 1fr);
  align-items: start;
  gap: 28px;
  padding-top: 28px;
}

.pf-side {
  position: sticky;
  top: 116px;
  display: grid;
  gap: 16px;
}

.pf-menu {
  display: grid;
  gap: 2px;
  padding: 8px;
  border: 1px solid var(--line);
  border-radius: 18px;
  background: var(--card);
}

.pf-menu__link {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 0 12px;
  border-radius: 12px;
  color: var(--ink-soft);
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  transition: background 0.2s ease, color 0.2s ease;
}

.pf-menu__link:hover {
  background: var(--ivory);
  color: var(--ink);
}

.pf-menu__link.on {
  background: var(--green-bg);
  color: var(--leaf-deep);
}

.pf-menu__ico {
  display: grid;
  place-items: center;
}

.pf-menu__ico :deep(svg) {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pf-meter {
  display: grid;
  gap: 10px;
  padding: 16px;
  border: 1px solid var(--line);
  border-radius: 18px;
  background: var(--card);
}

.pf-meter strong {
  font-size: 13.5px;
  font-weight: 600;
}

.pf-meter__track {
  height: 8px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--ivory-deep);
}

.pf-meter__track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--leaf-light), var(--leaf));
  transition: width 0.4s ease;
}

.pf-meter p {
  color: var(--ink-soft);
  font-size: 12.5px;
  line-height: 1.5;
}

.pf-main {
  display: grid;
  gap: 20px;
  min-width: 0;
}

/* ── Cards ────────────────────────────────────────────────── */
.pf-card {
  padding: clamp(20px, 2.6vw, 30px);
  border: 1px solid var(--line);
  border-radius: 22px;
  background: var(--card);
  scroll-margin-top: 116px;
}

.pf-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 22px;
}

.pf-card__head h2 {
  font-family: var(--font-display);
  font-size: clamp(28px, 2.6vw, 34px);
  font-weight: 500;
  line-height: 1.05;
}

.pf-card__head h2 em {
  color: var(--leaf-deep);
  font-style: italic;
}

.pf-card__head p {
  max-width: 560px;
  margin-top: 6px !important;
  color: var(--ink-soft);
  font-size: 14px;
  line-height: 1.6;
}

/* Reading: icon tiles */
.pf-tiles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.pf-tile {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px;
  border-radius: 16px;
  background: var(--ivory);
}

.pf-tile__ico {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: var(--card);
  color: var(--leaf-deep);
  box-shadow: 0 1px 2px rgba(54, 63, 78, 0.06);
}

.pf-tile__ico :deep(svg) {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pf-tile__body {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.pf-tile dt {
  color: var(--ink-soft);
  font-size: 12px;
  font-weight: 500;
}

.pf-tile dd {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  overflow-wrap: anywhere;
  font-size: 14.5px;
  font-weight: 500;
}

.pf-tile dd.empty,
.pf-value.empty {
  color: #8b929c;
  font-weight: 400;
  font-style: italic;
}

.pf-badge {
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--green-bg);
  color: var(--leaf-deep);
  font-size: 11px;
  font-style: normal;
  font-weight: 600;
}

/* Editing: labelled fields */
.pf-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px 18px;
}

.pf-field {
  display: grid;
  align-content: start;
  gap: 7px;
  min-width: 0;
}

.pf-field--wide {
  grid-column: 1 / -1;
}

.pf-field label {
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
}

.pf-input {
  width: 100%;
  min-height: 46px;
  padding: 0 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--card);
  color: var(--ink);
  font: 400 14.5px var(--font-sans);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.pf-input:focus {
  border-color: var(--leaf);
  outline: none;
  box-shadow: 0 0 0 4px rgba(77, 177, 115, 0.14);
}

.pf-input.bad {
  border-color: var(--red);
}

.pf-error {
  color: var(--red);
  font-size: 12.5px;
}

.pf-hint {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.pf-locked {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 46px;
  padding: 0 14px;
  overflow: hidden;
  border-radius: 12px;
  background: var(--ivory);
  color: var(--ink-soft);
  font-size: 14px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pf-locked svg {
  flex-shrink: 0;
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
}

.pf-value {
  font-size: 14.5px;
  font-weight: 500;
  line-height: 1.5;
}

.pf-value.auto {
  padding: 10px 14px;
  border-radius: 12px;
  background: var(--ivory);
}

/* ── Address ──────────────────────────────────────────────── */
.pf-address {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
  gap: 22px;
}

.pf-map {
  position: relative;
  overflow: hidden;
  min-height: 300px;
  border: 1px solid var(--line);
  border-radius: 18px;
}

.location-map {
  height: 100%;
  min-height: 300px;
  z-index: 0; /* keep Leaflet's panes under the fixed header */
}

.location-map.is-editing {
  cursor: crosshair;
}

.pf-map__locate {
  position: absolute;
  left: 12px;
  bottom: 12px;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 40px;
  padding: 0 14px;
  border: none;
  border-radius: 999px;
  background: var(--card);
  color: var(--ink);
  font: 500 13px var(--font-sans);
  box-shadow: 0 6px 18px rgba(54, 63, 78, 0.18);
  cursor: pointer;
}

.pf-map__locate svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
}

.pf-map__locate:disabled {
  opacity: 0.7;
  cursor: wait;
}

.pf-map__empty {
  position: absolute;
  inset: auto 12px 12px;
  z-index: 1;
  padding: 10px 14px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.94);
  color: var(--ink-soft);
  font-size: 13px;
}

.pf-address__fields {
  display: grid;
  align-content: start;
  gap: 16px;
}

.pf-address__auto {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.pf-pin-status {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--green-bg);
  color: var(--leaf-deep);
  font-size: 12.5px;
}

.pf-pin-status.bad {
  background: #fbe9e7;
  color: var(--red);
}

.pf-pin-status svg {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
}

/* ── Flowers you love: the preferences block, matched to these cards ── */
.pf-prefs {
  scroll-margin-top: 116px;
}

.pf-prefs :deep(.pp-card) {
  padding: clamp(20px, 2.6vw, 30px);
  border-color: var(--line);
  border-radius: 22px;
  background: var(--card);
}

.pf-prefs :deep(.pp-head h2) {
  font-size: clamp(28px, 2.6vw, 34px);
}

/* ── Security ─────────────────────────────────────────────── */
.pf-security {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border-radius: 16px;
  background: var(--ivory);
}

.pf-security__ico {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: var(--card);
  color: var(--ink-soft);
}

.pf-security__ico.on {
  background: var(--green-bg);
  color: var(--leaf-deep);
}

.pf-security__ico svg {
  width: 24px;
  height: 24px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pf-security__text strong {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 600;
}

.pf-security__text p {
  margin-top: 3px !important;
  color: var(--ink-soft);
  font-size: 13.5px;
  line-height: 1.5;
}

.pf-state {
  padding: 2px 9px;
  border-radius: 999px;
  background: var(--ivory-deep);
  color: var(--ink-soft);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.pf-state.on {
  background: var(--green-bg);
  color: var(--leaf-deep);
}

.pf-switch {
  position: relative;
  display: inline-block;
  width: 52px;
  height: 30px;
  cursor: pointer;
}

.pf-switch input {
  position: absolute;
  inset: 0;
  z-index: 1;
  width: 100%;
  height: 100%;
  margin: 0;
  opacity: 0;
  cursor: pointer;
}

.pf-switch span {
  position: absolute;
  inset: 0;
  border-radius: 999px;
  background: #cfd5d1;
  transition: background 0.2s ease;
}

.pf-switch span::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #ffffff;
  box-shadow: 0 2px 6px rgba(38, 45, 57, 0.25);
  transition: transform 0.2s ease;
}

.pf-switch input:checked + span {
  background: var(--leaf);
}

.pf-switch input:checked + span::after {
  transform: translateX(22px);
}

.pf-switch input:disabled + span {
  opacity: 0.6;
}

.pf-switch input:focus-visible + span {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

/* ── Save bar ─────────────────────────────────────────────── */
/* A snackbar, like the site's other messages: floating at the bottom centre */
.pf-savebar {
  position: fixed;
  left: 50%;
  bottom: calc(24px + env(safe-area-inset-bottom));
  z-index: 900;
  width: min(560px, calc(100vw - 32px));
  padding: 10px 10px 10px 16px;
  border: 1px solid rgba(54, 63, 78, 0.12);
  border-radius: 14px;
  background: #ffffff;
  box-shadow: 0 12px 32px rgba(30, 39, 57, 0.16), 0 2px 6px rgba(30, 39, 57, 0.08);
  transform: translateX(-50%);
}

.pf-savebar__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

/* While it shows, other snackbars stack above it instead of covering it */
:global(body.pf-saving-open .sb-host) {
  bottom: calc(100px + env(safe-area-inset-bottom)) !important;
}

.pf-savebar p {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--ink-soft);
  font-size: 14px;
}

.pf-savebar p svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: var(--leaf-deep);
  stroke-width: 1.8;
  stroke-linejoin: round;
}

.pf-savebar__actions {
  display: flex;
  gap: 8px;
}

.pf-bar-enter-active,
.pf-bar-leave-active {
  transition: transform 0.25s ease, opacity 0.25s ease;
}

.pf-bar-enter-from,
.pf-bar-leave-to {
  opacity: 0;
  transform: translate(-50%, 16px);
}

/* ── Two-step setup ───────────────────────────────────────── */
.pf-overlay {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(38, 45, 57, 0.45);
  backdrop-filter: blur(4px);
}

.pf-modal {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 10px;
  width: min(440px, 100%);
  padding: 32px 26px 26px;
  border-radius: 22px;
  background: var(--paper);
  text-align: center;
  box-shadow: 0 30px 80px rgba(38, 45, 57, 0.25);
}

.pf-modal__close {
  position: absolute;
  top: 14px;
  right: 14px;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 50%;
  background: var(--ivory-deep);
  color: var(--ink);
  cursor: pointer;
}

.pf-modal__close svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
}

.pf-modal__badge {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--green-bg);
  color: var(--leaf-deep);
}

.pf-modal__badge svg {
  width: 26px;
  height: 26px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pf-modal__step {
  color: var(--leaf-deep);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.pf-modal h2 {
  font-family: var(--font-display);
  font-size: 30px;
  font-weight: 500;
  line-height: 1.1;
}

.pf-modal h2 em {
  color: var(--leaf-deep);
}

.pf-modal__text {
  color: var(--ink-soft);
  font-size: 14px;
  line-height: 1.6;
}

.pf-modal form {
  display: grid;
  gap: 18px;
  width: 100%;
  margin-top: 8px;
}

.pf-code {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 8px;
}

.pf-code__box {
  width: 100%;
  height: 54px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--card);
  color: var(--ink);
  font: 600 22px var(--font-sans);
  text-align: center;
}

.pf-code__box:focus {
  border-color: var(--leaf);
  outline: none;
  box-shadow: 0 0 0 4px rgba(77, 177, 115, 0.14);
}

/* ── Loading ──────────────────────────────────────────────── */
.pf-skel-avatar {
  width: 120px;
  height: 120px;
  border-radius: 50%;
}

.pf-skel-lines {
  display: grid;
  gap: 10px;
  flex: 1;
}

.pf-skel-line {
  display: block;
  height: 12px;
  border-radius: 6px;
}

.pf-skel-line.tall { height: 30px; }
.pf-skel-line.w30 { width: 30%; }
.pf-skel-line.w40 { width: 40%; }
.pf-skel-line.w60 { width: 60%; }

.pf-skel-ico {
  width: 42px;
  height: 42px;
  border-radius: 12px;
}

/* ── Smaller screens ──────────────────────────────────────── */
@media (max-width: 960px) {
  .pf-layout {
    grid-template-columns: 1fr;
  }

  /* The menu becomes a row of pills under the hero */
  .pf-side {
    position: sticky;
    top: 92px;
    z-index: 5;
    gap: 0;
  }

  .pf-menu {
    display: flex;
    overflow-x: auto;
    padding: 6px;
    scrollbar-width: none;
  }

  .pf-menu::-webkit-scrollbar {
    display: none;
  }

  .pf-menu__link {
    flex-shrink: 0;
    border-radius: 999px;
  }

  .pf-meter {
    display: none;
  }

  /* Sections stop below the navbar and the pill menu */
  .pf-card,
  .pf-prefs {
    scroll-margin-top: 170px;
  }

  .pf-address {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 680px) {
  .pf-hero__inner {
    grid-template-columns: auto minmax(0, 1fr);
    gap: 18px;
  }

  .pf-avatar,
  .pf-skel-avatar {
    width: 88px;
    height: 88px;
  }

  .pf-avatar__initials {
    font-size: 32px;
  }

  .pf-hero__actions {
    grid-column: 1 / -1;
  }

  .pf-hero__actions .pf-btn {
    width: 100%;
  }

  .pf-tiles,
  .pf-form {
    grid-template-columns: 1fr;
  }

  .pf-card__head {
    flex-wrap: wrap;
  }

  .pf-security {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .pf-security .pf-switch {
    grid-column: 2;
  }

  .pf-savebar p {
    display: none;
  }

  .pf-savebar__actions {
    width: 100%;
  }

  .pf-savebar__actions .pf-btn {
    flex: 1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .pf-editing-pill i {
    animation: none;
  }

  .pf-bar-enter-active,
  .pf-bar-leave-active,
  .pf-meter__track span,
  .pf-switch span,
  .pf-switch span::after {
    transition: none;
  }
}
</style>
