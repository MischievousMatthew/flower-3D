<template>
  <ShopLayout section="staff" title="Attendance devices" subtitle="Where attendance can be taken, on which devices (up to 3), and the rules for timing in and overtime.">
    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <div class="ad-grid">
      <!-- Location -->
      <section class="sp-card">
        <div class="sp-card-head">
          <div>
            <h2>Shop location</h2>
            <p>Scanners must be within this distance of the pin.</p>
          </div>
        </div>

        <template v-if="!data">
          <div class="bc-skeleton ad-map"></div>
          <div class="bc-skeleton sp-sk-line w70"></div>
          <div class="bc-skeleton sp-sk-line w50"></div>
        </template>
        <template v-else>
          <iframe v-if="hasPin" class="ad-map" :src="mapUrl" title="Shop location map" loading="lazy" referrerpolicy="no-referrer"></iframe>
          <div v-else class="ad-map ad-map-empty">
            <span v-html="ICONS.pin"></span>
            <p>No location yet. Use the store pin, or stand at the shop and use where you are.</p>
          </div>

          <div class="ad-pin-actions">
            <button v-if="data.store_pin" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="useStorePin"><span v-html="ICONS.store"></span>Use my store pin</button>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="locating" @click="useHere"><span v-html="ICONS.pin"></span>{{ locating ? "Finding you…" : "Use where I am now" }}</button>
            <a v-if="hasPin" class="st-link" :href="`https://www.google.com/maps?q=${form.latitude},${form.longitude}`" target="_blank" rel="noopener">Check on Google Maps</a>
          </div>
          <p v-if="locateNote" class="sp-hint">{{ locateNote }}</p>

          <div class="sp-grid-2 ad-fields">
            <label class="sp-field">
              <span class="sp-label">Latitude</span>
              <input v-model.number="form.latitude" type="number" step="0.000001" class="sp-input" :class="{ invalid: errors.latitude }" />
            </label>
            <label class="sp-field">
              <span class="sp-label">Longitude</span>
              <input v-model.number="form.longitude" type="number" step="0.000001" class="sp-input" :class="{ invalid: errors.longitude }" />
            </label>
            <label class="sp-field sp-span-all">
              <span class="sp-label">Allowed distance: <b>{{ form.radius_m }} m</b></span>
              <input v-model.number="form.radius_m" type="range" min="30" max="500" step="10" class="ad-range" aria-label="Allowed distance in metres" />
              <small class="sp-hint">Keep it small — just your shop. 100 m suits most shops; GPS is rarely better than 20–30 m indoors.</small>
            </label>
            <label class="sp-field">
              <span class="sp-label">Late after</span>
              <div class="ad-inline">
                <input v-model.number="form.late_grace_minutes" type="number" min="0" max="60" class="sp-input" />
                <span>minutes</span>
              </div>
              <small class="sp-hint">Time-ins within this many minutes of the start aren't counted late.</small>
            </label>
          </div>

          <h3 class="ad-sub">Clock-in &amp; overtime rules</h3>
          <div class="sp-grid-2">
            <label class="sp-field">
              <span class="sp-label">Earliest time-in</span>
              <div class="ad-inline">
                <input v-model.number="form.early_clock_in_minutes" type="number" min="0" max="60" class="sp-input" />
                <span>min before the shift</span>
              </div>
              <small class="sp-hint">0 means nobody can time in before their shift starts.</small>
            </label>
            <label class="sp-field">
              <span class="sp-label">Count overtime after</span>
              <div class="ad-inline">
                <input v-model.number="form.overtime_min_minutes" type="number" min="1" max="240" class="sp-input" />
                <span>min past the shift</span>
              </div>
              <small class="sp-hint">Shorter stays aren't overtime. HR approves overtime before it's paid.</small>
            </label>
            <label class="sp-field">
              <span class="sp-label">Overtime pay</span>
              <div class="ad-inline">
                <input v-model.number="form.overtime_rate" type="number" min="1" max="3" step="0.05" class="sp-input" />
                <span>× the hourly rate ({{ Math.round((form.overtime_rate || 0) * 100) }}%)</span>
              </div>
              <small class="sp-hint">1.25 (125%) is the Labor Code minimum for a regular day.</small>
            </label>
          </div>
          <p v-if="formError" class="sp-error">{{ formError }}</p>
          <div class="ad-save">
            <button type="button" class="sp-btn sp-btn-primary" :disabled="saving || !hasPin" @click="save">{{ saving ? "Saving…" : "Save" }}</button>
          </div>
        </template>
      </section>

      <!-- Devices -->
      <section class="sp-card">
        <div class="sp-card-head">
          <div>
            <h2>Devices</h2>
            <p>HR asks from the ERP scanner page while at the shop. You approve here.</p>
          </div>
          <span v-if="data" class="sp-chip" :class="approvedCount >= data.max_devices ? 'amber' : 'green'">{{ approvedCount }} / {{ data.max_devices }} approved</span>
        </div>

        <ul v-if="!data" class="ad-list">
          <li v-for="n in 3" :key="n" class="ad-device"><div class="bc-skeleton st-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <div v-else-if="!data.devices.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.lock"></span>
          <h3>No devices yet</h3>
          <p>On the shop's tablet or computer, HR opens ERP → HR → Attendance → Scanner and taps "Ask the owner to approve".</p>
        </div>
        <ul v-else class="ad-list">
          <li v-for="d in data.devices" :key="d.id" class="ad-device" :class="d.status">
            <span class="ad-icon" :class="`tone-${STATUS[d.status].tone}`" v-html="ICONS[STATUS[d.status].icon]"></span>
            <div class="grow">
              <div class="ad-name">
                <input v-if="renaming === d.id" v-model="newName" class="sp-input ad-rename" maxlength="80" aria-label="Device name" @keydown.enter.prevent="rename(d)" @keydown.esc="renaming = null" />
                <strong v-else>{{ d.name }}</strong>
                <span class="sp-chip" :class="STATUS[d.status].tone">{{ STATUS[d.status].label }}</span>
              </div>
              <small>
                {{ d.browser || "Unknown browser" }} · asked by {{ d.requested_by || "someone" }} {{ relativeTime(d.requested_at) }}
                <template v-if="d.request_distance_m !== null"> · {{ d.request_distance_m }} m from the pin</template>
              </small>
              <small v-if="d.last_used_at">Last scan {{ relativeTime(d.last_used_at) }}</small>
            </div>
            <div class="ad-actions">
              <template v-if="renaming === d.id">
                <button type="button" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="!newName.trim() || busyId === d.id" @click="rename(d)">Save</button>
                <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="renaming = null">Cancel</button>
              </template>
              <template v-else>
                <button v-if="d.status !== 'approved'" type="button" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="busyId === d.id || approvedCount >= data.max_devices" :title="approvedCount >= data.max_devices ? 'Remove an approved device first' : ''" @click="act(d, 'approve')">Approve</button>
                <button v-if="d.status === 'approved'" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busyId === d.id" @click="act(d, 'revoke')">Remove access</button>
                <button type="button" class="sp-icon-btn" :aria-label="`Rename ${d.name}`" @click="startRename(d)"><span v-html="ICONS.edit"></span></button>
                <button type="button" class="sp-icon-btn" :aria-label="`Delete ${d.name}`" :disabled="busyId === d.id" @click="remove(d)"><span v-html="ICONS.trash"></span></button>
              </template>
            </div>
          </li>
        </ul>
        <p v-if="data && approvedCount >= data.max_devices" class="sp-hint ad-note">You've approved the most devices allowed. Remove one to approve another.</p>
      </section>
    </div>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { toast } from "@/utils/snackbar";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { relativeTime } from "../../../components/vendor/staff/staffUi";
import { currentPosition } from "../../../services/attendanceKiosk";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";

const STATUS = {
  pending: { label: "Waiting for you", tone: "amber", icon: "clock" },
  approved: { label: "Approved", tone: "green", icon: "check" },
  revoked: { label: "Removed", tone: "slate", icon: "lock" },
};

const data = ref(null);
const error = ref("");
const form = reactive({ latitude: null, longitude: null, radius_m: 100, late_grace_minutes: 10, early_clock_in_minutes: 0, overtime_min_minutes: 15, overtime_rate: 1.25 });
const errors = reactive({});
const formError = ref("");
const saving = ref(false);
const locating = ref(false);
const locateNote = ref("");
const busyId = ref(null);
const renaming = ref(null);
const newName = ref("");

const hasPin = computed(() => Number.isFinite(form.latitude) && Number.isFinite(form.longitude));
const approvedCount = computed(() => (data.value?.devices ?? []).filter((d) => d.status === "approved").length);
const mapUrl = computed(() => {
  const d = 0.004;
  const bbox = [form.longitude - d, form.latitude - d, form.longitude + d, form.latitude + d].join(",");
  return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${form.latitude},${form.longitude}`;
});

async function load() {
  error.value = "";
  try {
    const res = await api.get("vendor/staff/attendance/setup");
    data.value = res.data.data;
    Object.assign(form, {
      latitude: data.value.settings.latitude,
      longitude: data.value.settings.longitude,
      radius_m: data.value.settings.radius_m,
      late_grace_minutes: data.value.settings.late_grace_minutes,
      early_clock_in_minutes: data.value.settings.early_clock_in_minutes ?? 0,
      overtime_min_minutes: data.value.settings.overtime_min_minutes ?? 15,
      overtime_rate: data.value.settings.overtime_rate ?? 1.25,
    });
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load the attendance setup.");
  }
}

function useStorePin() {
  form.latitude = Number(data.value.store_pin.latitude);
  form.longitude = Number(data.value.store_pin.longitude);
  locateNote.value = `From your store address: ${data.value.store_pin.address ?? "your application"}. Save to use it.`;
}

async function useHere() {
  locating.value = true;
  locateNote.value = "";
  try {
    const p = await currentPosition();
    form.latitude = Math.round(p.latitude * 1e6) / 1e6;
    form.longitude = Math.round(p.longitude * 1e6) / 1e6;
    locateNote.value = p.accuracy > 60
      ? `Your location is only accurate to about ${p.accuracy} m. Try near a window or on your phone, or use the store pin.`
      : `Accurate to about ${p.accuracy} m. Save to use it.`;
  } catch (e) {
    locateNote.value = e.message;
  } finally {
    locating.value = false;
  }
}

async function save() {
  saving.value = true;
  formError.value = "";
  Object.keys(errors).forEach((k) => delete errors[k]);
  try {
    const res = await api.put("vendor/staff/attendance/setup", { ...form });
    toast.success(res.data.message);
    locateNote.value = "";
  } catch (e) {
    for (const [k, list] of Object.entries(e?.response?.data?.errors ?? {})) errors[k] = [].concat(list)[0];
    formError.value = apiMessage(e, "Couldn't save.");
  } finally {
    saving.value = false;
  }
}

async function act(d, action) {
  busyId.value = d.id;
  try {
    const res = await api.post(`vendor/staff/attendance/devices/${d.id}/${action}`);
    data.value.devices = res.data.data;
    toast.success(res.data.message);
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't change the device."));
  } finally {
    busyId.value = null;
  }
}

function startRename(d) {
  renaming.value = d.id;
  newName.value = d.name;
}

async function rename(d) {
  if (!newName.value.trim()) return;
  busyId.value = d.id;
  try {
    const res = await api.put(`vendor/staff/attendance/devices/${d.id}`, { name: newName.value.trim() });
    data.value.devices = res.data.data;
    renaming.value = null;
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't rename it."));
  } finally {
    busyId.value = null;
  }
}

async function remove(d) {
  busyId.value = d.id;
  try {
    const res = await api.delete(`vendor/staff/attendance/devices/${d.id}`);
    data.value.devices = res.data.data;
    toast.success(res.data.message);
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't delete it."));
  } finally {
    busyId.value = null;
  }
}

onMounted(load);
</script>

<style scoped>
.ad-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
  gap: 16px;
  align-items: start;
}
.ad-map {
  display: block;
  width: 100%;
  height: 220px;
  margin-bottom: 12px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-subtle);
}
.ad-map-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px;
  color: var(--v-muted);
  font-size: 13px;
  text-align: center;
}
.ad-map-empty p {
  margin: 0;
  max-width: 300px;
}
.ad-pin-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.ad-pin-actions a {
  margin-left: auto;
  font-size: 12.5px;
}
.ad-fields {
  margin-top: 14px;
}
.ad-range {
  width: 100%;
  accent-color: var(--bc-accent);
}
.ad-inline {
  display: flex;
  align-items: center;
  gap: 8px;
}
.ad-inline .sp-input {
  width: 90px;
}
.ad-inline span {
  font-size: 13px;
  color: var(--v-muted);
}
.ad-sub {
  margin: 20px 0 10px;
  padding-top: 16px;
  border-top: 1px solid var(--v-border);
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
}
.ad-save {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}
.ad-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.ad-device {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
}
.ad-device.pending {
  border-color: color-mix(in srgb, #ed8936 45%, var(--v-border));
}
.ad-device.revoked {
  opacity: 0.7;
}
.ad-device .grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.ad-device small {
  font-size: 12px;
  color: var(--v-muted);
}
.ad-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 12px;
}
.ad-icon :deep(svg) {
  width: 18px;
  height: 18px;
}
.ad-name {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.ad-name strong {
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
  overflow-wrap: anywhere;
}
.ad-rename {
  max-width: 220px;
  padding: 6px 10px;
}
.ad-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 6px;
}
.ad-note {
  margin-top: 12px;
}
@media (max-width: 980px) {
  .ad-grid {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 560px) {
  .ad-device {
    flex-wrap: wrap;
  }
  .ad-actions {
    width: 100%;
    justify-content: flex-start;
  }
}
</style>
