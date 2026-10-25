<template>
  <ShopLayout title="Shop Profile" subtitle="Your shop's profile, photos and location, exactly as customers see them. Hours, delivery and order settings are in Shop settings.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="!dirty || saving" @click="reset">Discard</button>
      <button type="submit" form="shop-form" class="sp-btn sp-btn-primary" :disabled="!form || !dirty || saving">
        <span v-html="ICONS.check"></span>{{ saving ? "Saving…" : "Save changes" }}
      </button>
    </template>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="fetch">Try again</button>
    </div>

    <div class="shop-layout">
      <!-- Form -->
      <form id="shop-form" class="shop-form" novalidate @submit.prevent="save">
        <template v-if="!form && !loadError">
          <div v-for="n in 3" :key="n" class="sp-card"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-block"></div></div>
        </template>

        <template v-else-if="form">
          <!-- Profile -->
          <section class="sp-card">
            <div class="sp-card-head"><div><h2>Shop profile</h2><p>The name and story on your storefront.</p></div></div>
            <div class="sp-grid-2">
              <label class="sp-field sp-span-all">
                <span>Shop name</span>
                <input v-model.trim="form.store_name" class="sp-input" :class="{ invalid: errors.store_name }" maxlength="255" required />
                <small v-if="errors.store_name" class="sp-error">{{ errors.store_name }}</small>
              </label>
              <label class="sp-field sp-span-all">
                <span>About your shop</span>
                <textarea v-model="form.store_description" class="sp-textarea" maxlength="1000" rows="4" placeholder="What you're known for, the flowers you love working with, how you deliver…"></textarea>
                <small>{{ (form.store_description || "").length }}/1000</small>
              </label>
            </div>
          </section>

          <!-- Contact -->
          <section class="sp-card">
            <div class="sp-card-head"><div><h2>Contact</h2><p>How customers reach you.</p></div></div>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span>Phone</span>
                <input v-model.trim="form.contact_number" class="sp-input" :class="{ invalid: errors.contact_number }" maxlength="20" inputmode="tel" required />
                <small v-if="errors.contact_number" class="sp-error">{{ errors.contact_number }}</small>
              </label>
              <label class="sp-field">
                <span>Email</span>
                <input :value="form.email" class="sp-input" disabled />
                <small>Change it from your profile</small>
              </label>
              <label class="sp-field">
                <span>Facebook page</span>
                <input v-model.trim="form.facebook_page" class="sp-input" :class="{ invalid: errors.facebook_page }" type="url" placeholder="https://facebook.com/yourshop" />
                <small v-if="errors.facebook_page" class="sp-error">{{ errors.facebook_page }}</small>
              </label>
              <label class="sp-field">
                <span>Instagram</span>
                <input v-model.trim="form.instagram_page" class="sp-input" :class="{ invalid: errors.instagram_page }" type="url" placeholder="https://instagram.com/yourshop" />
                <small v-if="errors.instagram_page" class="sp-error">{{ errors.instagram_page }}</small>
              </label>
            </div>
          </section>

          <!-- Location -->
          <section class="sp-card">
            <div class="sp-card-head">
              <div><h2>Location</h2><p>Used for delivery fees and to show customers how far you are.</p></div>
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="locating" @click="locate">
                <span v-html="ICONS.pin"></span>{{ locating ? "Finding you…" : "Use my location" }}
              </button>
            </div>
            <div class="sp-grid-2">
              <label class="sp-field sp-span-all">
                <span>Street / Blk &amp; Lot</span>
                <input v-model.trim="form.store_street" class="sp-input" :class="{ invalid: errors.store_street }" maxlength="255" placeholder="e.g. Blk 5 Lot 12, Mabini St., Brgy. San Agustin" autocomplete="address-line1" />
                <small v-if="errors.store_street" class="sp-error">{{ errors.store_street }}</small>
                <small v-else>So customers and riders can find your door</small>
              </label>
              <label class="sp-field sp-span-all">
                <span>City / Province</span>
                <input v-model.trim="form.store_address" class="sp-input" :class="{ invalid: errors.store_address }" maxlength="500" placeholder="e.g. Trece Martires, Cavite" autocomplete="address-level2" required />
                <small v-if="errors.store_address" class="sp-error">{{ errors.store_address }}</small>
              </label>
              <div class="sp-field sp-span-all">
                <span>Areas you deliver to</span>
                <ServiceAreaPicker v-model="form.service_areas" />
                <small v-if="errors.service_areas" class="sp-error">{{ errors.service_areas }}</small>
                <small v-else>Pick a whole province or just the cities you cover.</small>
              </div>
              <div class="sp-field sp-span-all">
                <div class="pin-row">
                  <span class="pin-icon" v-html="ICONS.pin"></span>
                  <span v-if="hasPin" class="pin-text">Your shop is pinned on the map, used to work out delivery fees.</span>
                  <span v-else class="pin-text muted">Not pinned yet. Press “Use my location” while you're at the shop.</span>
                  <a v-if="hasPin" :href="mapUrl" target="_blank" rel="noopener" class="sp-btn sp-btn-ghost sp-btn-sm">Check pin</a>
                </div>
                <small v-if="locateError" class="sp-error">{{ locateError }}</small>
              </div>
            </div>
          </section>

          <!-- Hours: edited in Shop settings -->
          <section class="sp-card">
            <div class="sp-card-head">
              <div><h2>Business hours</h2><p>{{ hoursText || "Not set yet" }}</p></div>
              <router-link :to="{ name: 'VendorShopSettings' }" class="sp-btn sp-btn-ghost sp-btn-sm"><span v-html="ICONS.clock"></span>Edit in Shop settings</router-link>
            </div>
          </section>
        </template>
      </form>

      <!-- Preview -->
      <aside class="shop-preview" aria-label="How your shop looks">
        <div class="sp-card preview-card">
          <div class="cover" :style="coverStyle">
            <div v-if="!shop" class="bc-skeleton cover-sk"></div>
            <div class="cover-actions">
              <label class="sp-btn sp-btn-sm cover-btn">
                <input type="file" accept="image/png,image/jpeg,image/webp" :disabled="uploading === 'cover'" @change="upload('cover', $event)" />
                <span v-html="ICONS.image"></span>{{ uploading === "cover" ? "Uploading…" : shop?.store_cover_url ? "Change cover" : "Add cover" }}
              </label>
              <button v-if="shop?.store_cover_url" type="button" class="sp-btn sp-btn-sm cover-btn" :disabled="!!uploading" aria-label="Remove cover" @click="removeImage('cover')"><span v-html="ICONS.trash"></span></button>
            </div>
          </div>
          <div class="preview-body">
            <div class="logo-wrap">
              <img v-if="shop?.store_logo_url" :src="shop.store_logo_url" alt="Shop logo" class="logo" />
              <span v-else class="logo blank">{{ initials }}</span>
              <label class="logo-edit" :aria-label="shop?.store_logo_url ? 'Change logo' : 'Add logo'">
                <input type="file" accept="image/png,image/jpeg" :disabled="uploading === 'logo'" @change="upload('logo', $event)" />
                <span v-html="uploading === 'logo' ? ICONS.clock : ICONS.edit"></span>
              </label>
            </div>
            <h3>{{ form?.store_name || "Your shop" }}</h3>
            <p class="preview-meta">
              <span v-if="openNow !== null" class="sp-chip" :class="openNow ? 'green' : 'slate'">{{ openNow ? "Open now" : "Closed now" }}</span>
              <span v-if="shop?.business_type">{{ shop.business_type }}</span>
            </p>
            <p v-if="form?.store_description" class="preview-about">{{ form.store_description }}</p>
            <ul class="preview-facts">
              <li v-if="form?.store_address || form?.store_street"><span v-html="ICONS.pin"></span>{{ [form.store_street, form.store_address].filter(Boolean).join(", ") }}</li>
              <li v-if="hoursText"><span v-html="ICONS.clock"></span>{{ hoursText }}</li>
              <li v-if="form?.contact_number"><span v-html="ICONS.phone"></span>{{ form.contact_number }}</li>
              <li v-if="form?.facebook_page || form?.instagram_page"><span v-html="ICONS.globe"></span>{{ [form.facebook_page && "Facebook", form.instagram_page && "Instagram"].filter(Boolean).join(" · ") }}</li>
            </ul>
            <button v-if="shop?.store_logo_url" type="button" class="sp-btn sp-btn-ghost sp-btn-sm remove-logo" :disabled="!!uploading" @click="removeImage('logo')">Remove logo</button>
          </div>
        </div>
        <p class="sp-hint preview-hint">Logo: JPG or PNG up to 2 MB, square works best. Cover: JPG, PNG or WebP up to 5 MB, wide (3:1).</p>
      </aside>
    </div>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { onBeforeRouteLeave } from "vue-router";
import { toast } from "@/utils/snackbar";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import ServiceAreaPicker from "../../../components/vendor/shop/ServiceAreaPicker.vue";
import { joinServiceAreas, parseServiceAreas } from "../../../constants/serviceAreas";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { useVendorProfile } from "../../../composables/useVendorProfile";
import { hoursText as describeHours, openNow as isOpenNow, weekFrom, weekFromText } from "../../../components/vendor/shop/businessHours";
import "../../../components/vendor/shop/shop.css";

const FIELDS = ["store_name", "store_description", "store_street", "store_address", "service_areas", "contact_number", "email", "facebook_page", "instagram_page", "store_latitude", "store_longitude"];

const { fetchProfile } = useVendorProfile({ autoFetch: false, showToast: false });

const shop = ref(null);
const form = ref(null);
const week = ref([]);
const loadError = ref("");
const saving = ref(false);
const uploading = ref(null);
const locating = ref(false);
const locateError = ref("");
const errors = reactive({});
let snapshot = "";

const state = () => JSON.stringify([form.value, week.value]);
const dirty = computed(() => !!form.value && state() !== snapshot);

function apply(data) {
  shop.value = data;
  form.value = Object.fromEntries(FIELDS.map((k) => [k, data[k] ?? (k.startsWith("store_l") ? null : "")]));
  week.value = weekFrom(data.operating_schedules);
  // Older shops only have the text version of their hours (e.g. "Everyday: 8:00 AM – 5:00 PM")
  if (!data.operating_schedules?.length) week.value = weekFromText(data.operating_hours);
  snapshot = state();
}

async function fetch() {
  loadError.value = "";
  try {
    const { data } = await api.get("vendor/shop");
    apply(data.data);
  } catch (e) {
    loadError.value = apiMessage(e, "Couldn't load your shop.");
  }
}
function reset() {
  if (shop.value) apply(shop.value);
  Object.keys(errors).forEach((k) => delete errors[k]);
}

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  const f = form.value;
  if (!f.store_name) errors.store_name = "Your shop needs a name.";
  if (!f.store_address) errors.store_address = "Add your shop's address.";
  if (!f.contact_number) errors.contact_number = "Add a phone number.";
  for (const key of ["facebook_page", "instagram_page"]) {
    if (f[key] && !/^https?:\/\/\S+\.\S+/.test(f[key])) errors[key] = "Use a full link starting with https://";
  }
  return !Object.keys(errors).length;
}

async function save() {
  if (saving.value || !validate()) {
    if (Object.keys(errors).length) toast.error(Object.values(errors)[0]);
    return;
  }
  saving.value = true;
  try {
    const { email, ...rest } = form.value;
    const { data } = await api.put("vendor/shop", {
      ...rest,
      facebook_page: rest.facebook_page || null,
      instagram_page: rest.instagram_page || null,
      // Only places on the list are kept
      service_areas: joinServiceAreas(parseServiceAreas(rest.service_areas)) || null,
    });
    apply(data.data);
    toast.success(data.message);
    fetchProfile?.().catch?.(() => {});
  } catch (e) {
    const fieldErrors = e?.response?.data?.errors ?? {};
    for (const [key, list] of Object.entries(fieldErrors)) errors[key.split(".")[0]] = [].concat(list)[0];
    toast.error(apiMessage(e, "Couldn't save your shop."));
  } finally {
    saving.value = false;
  }
}

const hasPin = computed(() => Number.isFinite(form.value?.store_latitude) && Number.isFinite(form.value?.store_longitude));
const mapUrl = computed(() => `https://www.openstreetmap.org/?mlat=${form.value.store_latitude}&mlon=${form.value.store_longitude}#map=17/${form.value.store_latitude}/${form.value.store_longitude}`);
function locate() {
  locateError.value = "";
  if (!navigator.geolocation) {
    locateError.value = "This browser can't share your location.";
    return;
  }
  locating.value = true;
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      form.value.store_latitude = Math.round(pos.coords.latitude * 1e7) / 1e7;
      form.value.store_longitude = Math.round(pos.coords.longitude * 1e7) / 1e7;
      locating.value = false;
    },
    (err) => {
      locateError.value = err.code === 1 ? "Location access was blocked. Allow it in your browser and try again." : "Couldn't find your location. Try again.";
      locating.value = false;
    },
    { enableHighAccuracy: true, timeout: 15000 },
  );
}

// Logo and cover upload straight away (they have their own endpoints)
async function upload(kind, event) {
  const file = event.target.files?.[0];
  event.target.value = "";
  if (!file) return;
  const limit = kind === "logo" ? 2 : 5;
  if (file.size > limit * 1024 * 1024) {
    toast.error(`The ${kind} must be ${limit} MB or smaller.`);
    return;
  }
  uploading.value = kind;
  try {
    const fd = new FormData();
    fd.append(kind === "logo" ? "store_logo" : "store_cover", file);
    const { data } = await api.post(`vendor/profile/store-${kind}`, fd);
    shop.value = { ...shop.value, store_logo_url: data.data?.store_logo_url, store_cover_url: data.data?.store_cover_url };
    toast.success(kind === "logo" ? "Logo updated." : "Cover photo updated.");
    if (kind === "logo") fetchProfile?.().catch?.(() => {});
  } catch (e) {
    toast.error(apiMessage(e, `Couldn't upload the ${kind}.`));
  } finally {
    uploading.value = null;
  }
}
async function removeImage(kind) {
  uploading.value = kind;
  try {
    await api.delete(`vendor/profile/store-${kind}`);
    shop.value = { ...shop.value, [kind === "logo" ? "store_logo_url" : "store_cover_url"]: null };
    toast.success(kind === "logo" ? "Logo removed." : "Cover photo removed.");
    if (kind === "logo") fetchProfile?.().catch?.(() => {});
  } catch (e) {
    toast.error(apiMessage(e, `Couldn't remove the ${kind}.`));
  } finally {
    uploading.value = null;
  }
}

const coverStyle = computed(() =>
  shop.value?.store_cover_url ? { backgroundImage: `url("${shop.value.store_cover_url}")` } : {},
);
const initials = computed(() =>
  (form.value?.store_name || "S").split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase(),
);
const hoursText = computed(() => describeHours(week.value));
const openNow = computed(() => isOpenNow(week.value));

onBeforeRouteLeave(() => {
  if (dirty.value && !saving.value) {
    return window.confirm("You have unsaved changes to your shop. Leave without saving?");
  }
  return true;
});

onMounted(fetch);
</script>

<style scoped>
.shop-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 16px;
  align-items: start;
}
.shop-form {
  display: grid;
  gap: 16px;
}
.pin-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-subtle);
}
.pin-icon {
  display: inline-flex;
  color: var(--bc-accent);
}
.pin-icon :deep(svg) {
  width: 18px;
  height: 18px;
}
.pin-text {
  flex: 1;
  font-size: 13px;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.pin-text.muted {
  color: var(--v-faint);
}
/* Preview */
.shop-preview {
  position: sticky;
  top: 16px;
}
.preview-card {
  padding: 0;
  overflow: hidden;
}
.cover {
  position: relative;
  height: 130px;
  background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 45%, #fbb6ce 100%);
  background-size: cover;
  background-position: center;
}
.cover-sk {
  position: absolute;
  inset: 0;
  border-radius: 0;
}
.cover-actions {
  position: absolute;
  right: 10px;
  bottom: 10px;
  display: flex;
  gap: 6px;
}
.cover-btn {
  position: relative;
  background: var(--v-glass);
  color: var(--v-text);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}
.cover-btn input,
.logo-edit input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.preview-body {
  position: relative;
  padding: 0 18px 18px;
}
.logo-wrap {
  position: relative;
  width: 76px;
  height: 76px;
  margin-top: -38px;
}
.logo {
  display: grid;
  place-items: center;
  width: 76px;
  height: 76px;
  border: 4px solid var(--v-surface);
  border-radius: 22px;
  object-fit: cover;
  background: var(--v-surface);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.12);
  box-sizing: border-box;
}
.logo.blank {
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
  font-size: 24px;
  font-weight: 600;
}
.logo-edit {
  position: absolute;
  right: -6px;
  bottom: -6px;
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  border: 2px solid #fff;
  border-radius: 50%;
  background: #1a202c;
  color: #fff;
  cursor: pointer;
}
.logo-edit :deep(svg) {
  width: 14px;
  height: 14px;
}
.preview-body h3 {
  margin: 12px 0 4px;
  font-size: 18px;
  font-weight: 600;
  color: var(--v-text);
}
.preview-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 12px;
  color: var(--v-muted);
}
.preview-about {
  margin: 12px 0 0;
  font-size: 13px;
  line-height: 1.55;
  color: var(--v-text-2);
  white-space: pre-line;
  display: -webkit-box;
  -webkit-line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.preview-facts {
  list-style: none;
  margin: 14px 0 0;
  padding: 14px 0 0;
  border-top: 1px solid var(--v-border);
  display: grid;
  gap: 9px;
}
.preview-facts li {
  display: flex;
  gap: 9px;
  font-size: 12.5px;
  color: var(--v-text);
  line-height: 1.45;
}
.preview-facts :deep(svg) {
  flex: none;
  width: 15px;
  height: 15px;
  margin-top: 1px;
  color: var(--bc-accent);
}
.remove-logo {
  margin-top: 14px;
}
.preview-hint {
  margin: 10px 4px 0;
}
@media (max-width: 1100px) {
  .shop-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .shop-preview {
    position: static;
    order: -1;
  }
}
</style>
