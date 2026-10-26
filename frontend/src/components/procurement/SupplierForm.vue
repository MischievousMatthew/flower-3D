<template>
  <form class="sf" novalidate @submit.prevent="submit">
    <div class="sf-main">
      <!-- Business -->
      <section class="sp-card" aria-labelledby="sf-business">
        <div class="sp-card-head"><div><h2 id="sf-business">The business</h2><p>How the supplier shows up across the shop's records</p></div></div>
        <div class="sf-logo-row">
          <button type="button" class="sf-logo" :aria-label="logoPreview ? 'Change logo' : 'Add a logo'" @click="logoInput.click()">
            <img v-if="logoPreview" :src="logoPreview" alt="" />
            <span v-else v-html="ICONS.image"></span>
          </button>
          <div class="grow">
            <label class="sp-field">
              <span class="sp-label">Business name</span>
              <input ref="firstInput" v-model.trim="form.company_name" class="sp-input" :class="{ invalid: err.company_name }" maxlength="255" placeholder="e.g. Bloom Farms Benguet" :aria-invalid="!!err.company_name" />
              <small v-if="err.company_name" class="sp-error">{{ err.company_name }}</small>
            </label>
            <small class="sp-hint">Logo: PNG, JPG or WebP up to 2 MB. <button v-if="logoPreview" type="button" class="sf-link" @click="logoInput.click()">Change</button></small>
          </div>
          <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp,image/gif" hidden @change="pickLogo" />
        </div>

        <fieldset class="sf-kinds">
          <legend class="sp-label">What they supply</legend>
          <label v-for="t in SUPPLIER_TYPES" :key="t.value" class="sf-kind" :class="{ on: form.supplier_type === t.value }">
            <input v-model="form.supplier_type" type="radio" name="sf-kind" :value="t.value" />
            <span class="sf-kind-ic" :class="`tone-${t.tone}`" v-html="ICONS[t.icon]"></span>
            <span class="grow"><b>{{ t.label }}</b><small>{{ t.hint }}</small></span>
          </label>
        </fieldset>
      </section>

      <!-- Main contact -->
      <section class="sp-card" aria-labelledby="sf-contact">
        <div class="sp-card-head"><div><h2 id="sf-contact">Main contact</h2><p>Who the shop calls to order or follow up</p></div></div>
        <div class="sp-grid-2">
          <label class="sp-field">
            <span class="sp-label">Contact person</span>
            <input v-model.trim="form.contact_person" class="sp-input" :class="{ invalid: err.contact_person }" maxlength="255" autocomplete="off" placeholder="Full name" :aria-invalid="!!err.contact_person" />
            <small v-if="err.contact_person" class="sp-error">{{ err.contact_person }}</small>
          </label>
          <label class="sp-field">
            <span class="sp-label">Phone</span>
            <input v-model.trim="form.phone" class="sp-input" :class="{ invalid: err.phone }" type="tel" inputmode="tel" maxlength="20" placeholder="0917 123 4567" :aria-invalid="!!err.phone" />
            <small v-if="err.phone" class="sp-error">{{ err.phone }}</small>
          </label>
          <label class="sp-field sp-span-all">
            <span class="sp-label">Email</span>
            <input v-model.trim="form.email" class="sp-input" :class="{ invalid: err.email }" type="email" inputmode="email" maxlength="255" placeholder="orders@supplier.com" :aria-invalid="!!err.email" />
            <small v-if="err.email" class="sp-error">{{ err.email }}</small>
          </label>
          <label class="sp-field sp-span-all">
            <span class="sp-label">Address</span>
            <textarea v-model.trim="form.address" class="sp-textarea" :class="{ invalid: err.address }" rows="2" placeholder="Street, town or city, province" :aria-invalid="!!err.address"></textarea>
            <small v-if="err.address" class="sp-error">{{ err.address }}</small>
          </label>
        </div>
      </section>

      <!-- Delivery & terms -->
      <section class="sp-card" aria-labelledby="sf-delivery">
        <div class="sp-card-head"><div><h2 id="sf-delivery">Delivery schedule &amp; terms</h2><p>When they deliver, how long orders take, and what they ask for</p></div></div>
        <fieldset class="sf-days">
          <legend class="sp-label">Delivery days</legend>
          <div class="sf-day-row">
            <label v-for="d in DAYS" :key="d" class="sf-day" :class="{ on: form.delivery_days.includes(d) }">
              <input v-model="form.delivery_days" type="checkbox" :value="d" />{{ shortDay(d) }}
            </label>
          </div>
          <small class="sp-hint">{{ form.delivery_days.length ? `Delivers ${daysText(form.delivery_days)}` : "Leave all off if they deliver on request." }}</small>
        </fieldset>
        <div class="sp-grid-2">
          <label class="sp-field">
            <span class="sp-label">Lead time <small>(days from ordering to arrival)</small></span>
            <input v-model="form.lead_time_days" class="sp-input" :class="{ invalid: err.lead_time_days }" type="number" min="0" max="180" step="1" inputmode="numeric" placeholder="e.g. 3" />
            <small v-if="err.lead_time_days" class="sp-error">{{ err.lead_time_days }}</small>
          </label>
          <label class="sp-field">
            <span class="sp-label">Order cutoff <small>(optional)</small></span>
            <input v-model="form.order_cutoff" class="sp-input" type="time" />
            <small class="sp-hint">Orders after this time count as the next day.</small>
          </label>
          <label class="sp-field">
            <span class="sp-label">Minimum order <small>(₱, optional)</small></span>
            <input v-model="form.minimum_order" class="sp-input" :class="{ invalid: err.minimum_order }" type="number" min="0" step="0.01" inputmode="decimal" placeholder="e.g. 5000" />
            <small v-if="err.minimum_order" class="sp-error">{{ err.minimum_order }}</small>
          </label>
          <label class="sp-field">
            <span class="sp-label">Payment terms <small>(optional)</small></span>
            <input v-model.trim="form.payment_terms" class="sp-input" list="sf-terms" maxlength="60" placeholder="e.g. Cash on delivery" />
            <datalist id="sf-terms"><option v-for="t in TERMS" :key="t" :value="t" /></datalist>
          </label>
          <label class="sp-field sp-span-all">
            <span class="sp-label">Notes <small>(optional)</small></span>
            <textarea v-model.trim="form.notes" class="sp-textarea" rows="3" maxlength="2000" placeholder="Anything the team should know: best time to call, quality to check, rules for returns…"></textarea>
          </label>
        </div>
      </section>

      <!-- More contacts -->
      <section class="sp-card" aria-labelledby="sf-more">
        <div class="sp-card-head">
          <div><h2 id="sf-more">More contacts</h2><p>Other branches, a sales agent, or whoever handles billing</p></div>
          <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="addContact"><span v-html="ICONS.plus"></span>Add contact</button>
        </div>
        <p v-if="!form.contacts.length" class="sp-hint">No other contacts yet.</p>
        <div v-for="(c, i) in form.contacts" :key="c._key" class="sf-contact">
          <div class="sf-contact-head">
            <b>Contact {{ i + 1 }}</b>
            <button type="button" class="sp-icon-btn" :aria-label="`Remove contact ${i + 1}`" @click="form.contacts.splice(i, 1)"><span v-html="ICONS.trash"></span></button>
          </div>
          <div class="sp-grid-2">
            <label class="sp-field">
              <span class="sp-label">Name</span>
              <input v-model.trim="c.contact_person" class="sp-input" :class="{ invalid: err[`contacts.${i}.contact_person`] }" maxlength="255" />
              <small v-if="err[`contacts.${i}.contact_person`]" class="sp-error">{{ err[`contacts.${i}.contact_person`] }}</small>
            </label>
            <label class="sp-field">
              <span class="sp-label">Phone</span>
              <input v-model.trim="c.phone" class="sp-input" :class="{ invalid: err[`contacts.${i}.phone`] }" type="tel" maxlength="20" />
              <small v-if="err[`contacts.${i}.phone`]" class="sp-error">{{ err[`contacts.${i}.phone`] }}</small>
            </label>
            <label class="sp-field">
              <span class="sp-label">Email</span>
              <input v-model.trim="c.email" class="sp-input" :class="{ invalid: err[`contacts.${i}.email`] }" type="email" maxlength="255" />
              <small v-if="err[`contacts.${i}.email`]" class="sp-error">{{ err[`contacts.${i}.email`] }}</small>
            </label>
            <label class="sp-field">
              <span class="sp-label">Branch or company <small>(optional)</small></span>
              <input v-model.trim="c.company_name" class="sp-input" maxlength="255" :placeholder="form.company_name || 'Same as the business'" />
            </label>
            <label class="sp-field sp-span-all">
              <span class="sp-label">Address <small>(optional)</small></span>
              <input v-model.trim="c.address" class="sp-input" :placeholder="form.address || 'Same as the business'" />
            </label>
          </div>
        </div>
      </section>
    </div>

    <!-- Side: status and save -->
    <aside class="sf-side">
      <div class="sp-card sf-sticky">
        <template v-if="showStatus">
          <span class="sp-label">Status</span>
          <div class="sf-status" role="radiogroup" aria-label="Status">
            <label v-for="(s, key) in STATUS" :key="key" class="sf-status-opt" :class="[{ on: form.status === key }, key]">
              <input v-model="form.status" type="radio" name="sf-status" :value="key" />
              <span class="sf-dot"></span>{{ s.label }}
            </label>
          </div>
          <small class="sp-hint">{{ STATUS_HINT[form.status] }}</small>
        </template>
        <slot name="side" />
        <p v-if="formError" class="sp-error" role="alert">{{ formError }}</p>
        <button type="submit" class="sp-btn sp-btn-primary sf-save" :disabled="saving">{{ saving ? "Saving…" : submitLabel }}</button>
        <router-link :to="cancelTo" class="sp-btn sp-btn-ghost sf-save">Cancel</router-link>
      </div>
    </aside>
  </form>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from "vue";
import { SHOP_ICONS as ICONS } from "../vendor/shop/shopIcons";
import { SUPPLIER_TYPES, STATUS, DAYS, shortDay, daysText } from "./supplierUi";
import { buildMultipartFormData, clearFileInput, getSelectedFile, readImagePreview, validateImageFile } from "../../utils/imageUpload";
import { toast } from "@/utils/snackbar";

const props = defineProps({
  initial: { type: Object, default: null },
  saving: { type: Boolean, default: false },
  serverErrors: { type: Object, default: () => ({}) },
  formError: { type: String, default: "" },
  showStatus: { type: Boolean, default: false },
  submitLabel: { type: String, default: "Save supplier" },
  cancelTo: { type: [String, Object], default: () => ({ name: "SupplierList" }) },
});
const emit = defineEmits(["submit"]);

const TERMS = ["Cash on delivery", "Pay before delivery", "7 days", "15 days", "30 days"];
const STATUS_HINT = {
  active: "The team can order from this supplier.",
  inactive: "Kept on file but not ordered from for now.",
  blacklisted: "Don't order from them again. Their history stays.",
};

let keySeed = 0;
const blankContact = () => ({ _key: ++keySeed, contact_person: "", phone: "", email: "", company_name: "", address: "", status: "active" });

const form = reactive({
  company_name: "", contact_person: "", email: "", phone: "", address: "", status: "active",
  supplier_type: "", delivery_days: [], lead_time_days: "", order_cutoff: "", minimum_order: "", payment_terms: "", notes: "",
  contacts: [],
});
const err = reactive({});
const firstInput = ref(null);
const logoInput = ref(null);
const logoFile = ref(null);
const logoPreview = ref(null);

watch(
  () => props.initial,
  (s) => {
    if (!s) return;
    Object.assign(form, {
      company_name: s.company_name ?? "",
      contact_person: s.contact_person ?? "",
      email: s.email ?? "",
      phone: s.phone ?? "",
      address: s.address ?? "",
      status: s.status ?? "active",
      supplier_type: s.supplier_type ?? "",
      delivery_days: [...(s.delivery_days ?? [])],
      lead_time_days: s.lead_time_days ?? "",
      order_cutoff: s.order_cutoff ?? "",
      minimum_order: s.minimum_order ?? "",
      payment_terms: s.payment_terms ?? "",
      notes: s.notes ?? "",
      contacts: (s.contacts ?? []).map((c) => ({ ...blankContact(), ...c, _key: ++keySeed })),
    });
    logoPreview.value = s.logo_url || null;
  },
  { immediate: true },
);

// Server messages ("email has already been taken") land on their fields
watch(
  () => props.serverErrors,
  (e) => {
    Object.keys(err).forEach((k) => delete err[k]);
    for (const [k, v] of Object.entries(e ?? {})) err[k] = Array.isArray(v) ? v[0] : v;
  },
);

function addContact() {
  form.contacts.push(blankContact());
}

async function pickLogo(event) {
  try {
    const file = validateImageFile(getSelectedFile(event), { fieldLabel: "Logo", maxSizeMB: 2 });
    if (!file) return;
    logoFile.value = file;
    logoPreview.value = await readImagePreview(file);
  } catch (e) {
    logoFile.value = null;
    clearFileInput(logoInput);
    toast.error(e.message || "That file couldn't be used as a logo.");
  }
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
function validate() {
  Object.keys(err).forEach((k) => delete err[k]);
  if (!form.company_name) err.company_name = "Enter the business name.";
  if (!form.contact_person) err.contact_person = "Enter who to talk to.";
  if (!form.phone) err.phone = "Enter a phone number.";
  if (!form.email) err.email = "Enter an email address.";
  else if (!EMAIL.test(form.email)) err.email = "That email doesn't look right. Check for typos.";
  if (!form.address) err.address = "Enter the supplier's address.";
  if (form.lead_time_days !== "" && (Number(form.lead_time_days) < 0 || Number(form.lead_time_days) > 180)) err.lead_time_days = "Use a number of days from 0 to 180.";
  if (form.minimum_order !== "" && Number(form.minimum_order) < 0) err.minimum_order = "Use 0 or more.";
  form.contacts.forEach((c, i) => {
    if (!c.contact_person) err[`contacts.${i}.contact_person`] = "Enter a name.";
    if (!c.phone) err[`contacts.${i}.phone`] = "Enter a phone number.";
    if (!c.email) err[`contacts.${i}.email`] = "Enter an email address.";
    else if (!EMAIL.test(c.email)) err[`contacts.${i}.email`] = "That email doesn't look right.";
  });
  return !Object.keys(err).length;
}

async function submit() {
  if (!validate()) {
    toast.error("A few fields need a look before saving.", { toastId: "supplier-form" });
    await nextTick();
    document.querySelector(".sf .invalid")?.focus();
    return;
  }
  const blank = (v) => (v === "" || v === null || v === undefined ? null : v);
  const fd = buildMultipartFormData(
    {
      company_name: form.company_name,
      contact_person: form.contact_person,
      email: form.email,
      phone: form.phone,
      address: form.address,
      status: form.status,
      logo: logoFile.value,
      // Sent together so a cleared field reaches the server as empty
      profile: {
        supplier_type: blank(form.supplier_type),
        delivery_days: form.delivery_days,
        lead_time_days: blank(form.lead_time_days) === null ? null : Number(form.lead_time_days),
        order_cutoff: blank(form.order_cutoff),
        minimum_order: blank(form.minimum_order) === null ? null : Number(form.minimum_order),
        payment_terms: blank(form.payment_terms),
        notes: blank(form.notes),
      },
      contacts: form.contacts.map(({ contact_person, phone, email, company_name, address, status }) => ({ contact_person, phone, email, company_name, address, status })),
    },
    { fileFields: ["logo"], jsonFields: ["profile", "contacts"] },
  );
  emit("submit", fd);
}

defineExpose({ focus: () => firstInput.value?.focus() });
</script>

<style scoped>
.sf {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 280px;
  gap: 16px;
  align-items: start;
}
.sf-main {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.sf-logo-row {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 16px;
}
.sf-logo {
  flex: none;
  display: grid;
  place-items: center;
  width: 76px;
  height: 76px;
  overflow: hidden;
  border: 1.5px dashed var(--v-border-strong);
  border-radius: 18px;
  background: var(--v-subtle);
  color: var(--v-muted);
  cursor: pointer;
  transition: border-color 0.15s;
}
.sf-logo:hover,
.sf-logo:focus-visible {
  border-color: var(--bc-accent);
  outline: none;
}
.sf-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.sf-logo :deep(svg) {
  width: 24px;
  height: 24px;
}
.sf-link {
  padding: 0;
  border: 0;
  background: none;
  color: var(--bc-accent-deep);
  font: inherit;
  text-decoration: underline;
  cursor: pointer;
}
.sf-kinds,
.sf-days {
  margin: 0;
  padding: 0;
  border: 0;
}
.sf-kinds {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
.sf-kinds legend,
.sf-days legend {
  margin-bottom: 8px;
}
.sf-kind {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1.5px solid var(--v-border);
  border-radius: 14px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.sf-kind:hover {
  border-color: var(--v-border-strong);
}
.sf-kind.on {
  border-color: var(--bc-accent);
  background: color-mix(in srgb, var(--bc-accent) 7%, transparent);
}
.sf-kind:focus-within {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.sf-kind input,
.sf-day input,
.sf-status-opt input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.sf-kind-ic {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
}
.sf-kind-ic :deep(svg) {
  width: 17px;
  height: 17px;
}
.sf-kind b {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.sf-kind small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.sf-days {
  margin-bottom: 16px;
}
.sf-day-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 6px;
}
.sf-day {
  position: relative;
  display: grid;
  place-items: center;
  min-width: 56px;
  min-height: 40px;
  padding: 0 12px;
  border: 1.5px solid var(--v-border-strong);
  border-radius: 999px;
  color: var(--v-text-2);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  user-select: none;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.sf-day.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.sf-day:focus-within {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.sf-contact {
  padding: 14px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
}
.sf-contact + .sf-contact {
  margin-top: 10px;
}
.sf-contact-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
  font-size: 13px;
  color: var(--v-text);
}

/* Side */
.sf-side {
  min-width: 0;
}
.sf-sticky {
  position: sticky;
  top: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.sf-status {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.sf-status-opt {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 42px;
  padding: 0 12px;
  border: 1.5px solid var(--v-border);
  border-radius: 12px;
  color: var(--v-text);
  font-size: 13px;
  cursor: pointer;
}
.sf-status-opt.on {
  border-color: var(--v-border-strong);
  background: var(--v-subtle);
  font-weight: 600;
}
.sf-status-opt:focus-within {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.sf-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: var(--v-faint);
}
.sf-status-opt.active .sf-dot { background: #10b981; }
.sf-status-opt.blacklisted .sf-dot { background: var(--v-red-text); }
.sf-save {
  justify-content: center;
  width: 100%;
}

@media (max-width: 1000px) {
  .sf {
    grid-template-columns: minmax(0, 1fr);
  }
  .sf-sticky {
    position: static;
  }
}
@media (max-width: 640px) {
  .sf-kinds {
    grid-template-columns: minmax(0, 1fr);
  }
  .sf-logo-row {
    flex-direction: column;
  }
}
</style>
