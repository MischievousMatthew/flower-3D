<template>
  <ShopLayout title="My Shop" subtitle="Your shop's profile, photos, location and business hours, exactly as customers see them.">
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
              <label class="sp-field sp-span-all">
                <span>Areas you deliver to</span>
                <input v-model.trim="form.service_areas" class="sp-input" maxlength="500" placeholder="e.g. Imus, Bacoor, Dasmariñas" />
              </label>
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

          <!-- Hours -->
          <section class="sp-card">
            <div class="sp-card-head">
              <div><h2>Business hours</h2><p>Same-day orders close at your closing time.</p></div>
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="copyFirst">Copy first open day to all</button>
            </div>
            <ul class="hours">
              <li v-for="d in week" :key="d.day" :class="{ closed: !d.open }">
                <label class="hours-day">
                  <span class="sp-switch"><input v-model="d.open" type="checkbox" :aria-label="`Open on ${d.day}`" /><span></span></span>
                  <strong>{{ d.day }}</strong>
                </label>
                <template v-if="d.open">
                  <input v-model="d.opening_time" type="time" class="sp-input time" :aria-label="`${d.day} opening time`" required />
                  <span class="to">to</span>
                  <input v-model="d.closing_time" type="time" class="sp-input time" :class="{ invalid: d.opening_time >= d.closing_time }" :aria-label="`${d.day} closing time`" required />
                </template>
                <span v-else class="closed-text">Closed</span>
              </li>
            </ul>
            <p v-if="errors.operating_schedules" class="sp-error">{{ errors.operating_schedules }}</p>
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
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { useVendorProfile } from "../../../composables/useVendorProfile";
import "../../../components/vendor/shop/shop.css";

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
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

const weekFrom = (schedules) =>
  DAYS.map((day) => {
    const s = (schedules ?? []).find((x) => (x.days ?? []).includes(day));
    return { day, open: !!s, opening_time: s?.opening_time ?? "08:00", closing_time: s?.closing_time ?? "17:00" };
  });

// Group days with identical hours back into the stored schedule shape
// Best guess from the old free-text hours: which days, and the first time range in it
function weekFromText(text) {
  const t = String(text || "");
  const toH24 = (h, m, ap) => {
    let hour = Number(h) % 12;
    if (/p/i.test(ap)) hour += 12;
    return `${String(hour).padStart(2, "0")}:${m}`;
  };
  const times = [...t.matchAll(/(\d{1,2}):(\d{2})\s*([AaPp])\.?[Mm]/g)];
  const opening = times[0] ? toH24(times[0][1], times[0][2], times[0][3]) : "08:00";
  const closing = times[1] ? toH24(times[1][1], times[1][2], times[1][3]) : "17:00";
  const everyDay = /every\s*day|daily|mon\s*-\s*sun/i.test(t);
  return DAYS.map((day, i) => ({ day, open: everyDay || i < 6, opening_time: opening, closing_time: closing }));
}

const schedulesFrom = (days) => {
  const groups = [];
  for (const d of days.filter((x) => x.open)) {
    const g = groups.find((x) => x.opening_time === d.opening_time && x.closing_time === d.closing_time);
    if (g) g.days.push(d.day);
    else groups.push({ days: [d.day], opening_time: d.opening_time, closing_time: d.closing_time });
  }
  return groups;
};
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
  if (!week.value.some((d) => d.open)) errors.operating_schedules = "Open on at least one day.";
  else if (week.value.some((d) => d.open && d.opening_time >= d.closing_time)) errors.operating_schedules = "Closing time must be after opening time.";
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
      operating_schedules: schedulesFrom(week.value),
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

function copyFirst() {
  const first = week.value.find((d) => d.open);
  if (!first) return;
  week.value.forEach((d) => {
    d.open = true;
    d.opening_time = first.opening_time;
    d.closing_time = first.closing_time;
  });
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
const to12 = (t) => {
  const [h, m] = t.split(":").map(Number);
  return `${h % 12 || 12}:${String(m).padStart(2, "0")} ${h >= 12 ? "PM" : "AM"}`;
};
const hoursText = computed(() => {
  const groups = schedulesFrom(week.value);
  if (!groups.length) return "";
  // "Mon–Sat", "Mon–Wed, Fri": runs of 3+ days are shortened
  const short = (days) => {
    if (days.length === 7) return "Every day";
    const idx = days.map((d) => DAYS.indexOf(d)).sort((a, b) => a - b);
    const parts = [];
    for (let i = 0; i < idx.length; ) {
      let j = i;
      while (j + 1 < idx.length && idx[j + 1] === idx[j] + 1) j++;
      const name = (k) => DAYS[idx[k]].slice(0, 3);
      if (j - i >= 2) parts.push(`${name(i)}–${name(j)}`);
      else for (let k = i; k <= j; k++) parts.push(name(k));
      i = j + 1;
    }
    return parts.join(", ");
  };
  return groups.map((g) => `${short(g.days)} ${to12(g.opening_time)}–${to12(g.closing_time)}`).join(" · ");
});
const openNow = computed(() => {
  if (!week.value.length) return null;
  const now = new Date(new Date().toLocaleString("en-US", { timeZone: "Asia/Manila" }));
  const today = week.value[(now.getDay() + 6) % 7];
  const hhmm = `${String(now.getHours()).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}`;
  return today.open && hhmm >= today.opening_time && hhmm < today.closing_time;
});

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
  border: 1px solid #edf2f7;
  border-radius: 12px;
  background: #fbfdfc;
}
.pin-icon {
  display: inline-flex;
  color: #48bb78;
}
.pin-icon :deep(svg) {
  width: 18px;
  height: 18px;
}
.pin-text {
  flex: 1;
  font-size: 13px;
  color: #2d3748;
  font-variant-numeric: tabular-nums;
}
.pin-text.muted {
  color: #a0aec0;
}
.hours {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 6px;
}
.hours li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 12px;
  background: #fbfdfc;
}
.hours li.closed {
  background: #f8f9fa;
}
.hours-day {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 150px;
  cursor: pointer;
}
.hours-day strong {
  font-size: 13px;
  font-weight: 500;
  color: #1a202c;
}
.time {
  width: 130px;
  padding: 7px 10px;
}
.to {
  font-size: 12px;
  color: #a0aec0;
}
.closed-text {
  font-size: 12.5px;
  color: #a0aec0;
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
  background: rgba(255, 255, 255, 0.92);
  color: #1a202c;
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
  border: 4px solid #fff;
  border-radius: 22px;
  object-fit: cover;
  background: #fff;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.12);
  box-sizing: border-box;
}
.logo.blank {
  background: #48bb78;
  color: #fff;
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
  color: #1a202c;
}
.preview-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 12px;
  color: #718096;
}
.preview-about {
  margin: 12px 0 0;
  font-size: 13px;
  line-height: 1.55;
  color: #4a5568;
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
  border-top: 1px solid #f1f5f3;
  display: grid;
  gap: 9px;
}
.preview-facts li {
  display: flex;
  gap: 9px;
  font-size: 12.5px;
  color: #2d3748;
  line-height: 1.45;
}
.preview-facts :deep(svg) {
  flex: none;
  width: 15px;
  height: 15px;
  margin-top: 1px;
  color: #48bb78;
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
@media (max-width: 560px) {
  .hours li {
    flex-wrap: wrap;
  }
  .hours-day {
    width: 100%;
  }
  .time {
    flex: 1;
    width: auto;
  }
}
</style>
