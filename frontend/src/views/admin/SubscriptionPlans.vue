<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <header class="content-header">
        <div class="header-left">
          <button class="mobile-toggle" @click="toggleMobile" aria-label="Toggle Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="3" y1="12" x2="21" y2="12" />
              <line x1="3" y1="6" x2="21" y2="6" />
              <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
          </button>
          <div>
            <h1 class="page-title">Plans &amp; Pricing</h1>
            <p class="page-subtitle">The plans shops subscribe to: their names, monthly prices, what the pricing page says, and their limits</p>
          </div>
        </div>
        <div class="header-right">
          <button type="button" class="ghost-btn" :disabled="loading" @click="guardUnsaved(load)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.64-6.36" /><path d="M21 3v6h-6" /></svg>
            Refresh
          </button>
        </div>
      </header>

      <div class="page-body">
        <p v-if="!canManage && plans.length" class="note">You can see the plans. Changing them needs the "Change a vendor's plan and edit plan names and prices" permission.</p>
        <p v-if="error" class="note bad" role="alert">{{ error }} <button type="button" class="link-btn" @click="load">Try again</button></p>

        <div class="plans">
          <template v-if="loading && !plans.length">
            <section v-for="n in 3" :key="n" class="plan-card">
              <div class="sk sk-line w40"></div>
              <div class="sk sk-value"></div>
              <div class="sk sk-line"></div>
              <div class="sk sk-line w70"></div>
              <div class="sk sk-block"></div>
            </section>
          </template>

          <form v-for="p in plans" v-else :key="p.key" class="plan-card" :class="{ featured: p.form.featured }" novalidate @submit.prevent="saveUnsaved">
            <div class="plan-top">
              <span class="plan-tier">{{ TIER[p.key] }}</span>
            </div>

            <label class="field" :data-change="`${p.key}.name`">
              <span class="label">Plan name</span>
              <input v-model.trim="p.form.name" class="input name-input" maxlength="40" :disabled="!canManage" :aria-invalid="!!p.errors.name" />
              <small v-if="p.errors.name" class="err">{{ p.errors.name }}</small>
            </label>

            <label class="field" :data-change="`${p.key}.monthly_price`">
              <span class="label">Monthly price</span>
              <span class="price-wrap">
                <span class="peso">₱</span>
                <input v-model.number="p.form.monthly_price" class="input price-input" type="number" min="20" step="1" inputmode="decimal" :disabled="!canManage" :aria-invalid="!!p.errors.monthly_price" />
                <span class="per">/month</span>
              </span>
              <small v-if="p.errors.monthly_price" class="err">{{ p.errors.monthly_price }}</small>
              <small v-else-if="Number(p.form.monthly_price) !== Number(p.monthly_price)" class="hint">Now {{ peso(p.monthly_price) }}. New checkouts use the new price; shops already on it keep theirs until they renew.</small>
            </label>

            <label class="toggle" :data-change="`${p.key}.featured`">
              <input v-model="p.form.featured" type="checkbox" :disabled="!canManage" />
              <span>Mark as <b>Most popular</b> on the pricing page</span>
            </label>

            <label class="field" :data-change="`${p.key}.description`">
              <span class="label">Description</span>
              <textarea v-model.trim="p.form.description" class="input" rows="2" maxlength="300" :disabled="!canManage"></textarea>
            </label>

            <fieldset class="field fs" :data-change="`${p.key}.highlights`">
              <legend class="label">Highlights <small>(shown as a checklist, up to 6)</small></legend>
              <div v-for="(h, i) in p.form.highlights" :key="i" class="hl-row">
                <input v-model="p.form.highlights[i]" class="input" maxlength="80" :disabled="!canManage" :aria-label="`Highlight ${i + 1}`" />
                <button v-if="canManage" type="button" class="icon-btn" :aria-label="`Remove highlight ${i + 1}`" @click="p.form.highlights.splice(i, 1)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
                </button>
              </div>
              <button v-if="canManage && p.form.highlights.length < 6" type="button" class="link-btn" @click="p.form.highlights.push('')">+ Add a highlight</button>
            </fieldset>

            <fieldset class="field fs" :data-change="`${p.key}.limits`">
              <legend class="label">Limits <small>(leave unlimited ticked for no limit)</small></legend>
              <div v-for="l in LIMITS" :key="l.key" class="limit-row">
                <span class="limit-name">{{ l.label }}</span>
                <input v-model.number="p.form.limits[l.key]" class="input limit-input" type="number" min="0" step="1" :disabled="!canManage || p.form.unlimited[l.key]" :aria-label="`${l.label} limit`" />
                <label class="unl"><input v-model="p.form.unlimited[l.key]" type="checkbox" :disabled="!canManage" />Unlimited</label>
              </div>
              <small v-if="p.errors.limits" class="err">{{ p.errors.limits }}</small>
            </fieldset>

            <div class="field">
              <span class="label">Includes</span>
              <div class="chips">
                <span v-for="m in p.modules" :key="m" class="chip">{{ MODULE_LABEL[m] || m }}</span>
              </div>
              <small v-if="p.free_trial_available" class="hint">Shops can try this plan free for {{ p.trial_duration_months }} {{ p.trial_duration_months === 1 ? "month" : "months" }}.</small>
            </div>

            <p v-if="p.updated_at" class="edited">Last changed {{ when(p.updated_at) }}<template v-if="p.updated_by_name"> by {{ p.updated_by_name }}</template></p>
            <p v-if="p.formError" class="err" role="alert">{{ p.formError }}</p>

            <div v-if="canManage" class="actions">
              <button type="button" class="ghost-btn" :disabled="p.busy || !p.updated_at" :title="p.updated_at ? '' : 'Already the original'" @click="reset(p)">Reset to original</button>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from "vue";
import { toast } from "@/utils/snackbar";
import api from "../../plugins/axios";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import { useSidebarState } from "../../composables/useSidebarState";
import { useAdminAccess } from "../../composables/useAdminAccess";
import { guardUnsaved, saveUnsaved, useUnsavedChanges } from "../../composables/useUnsavedChanges";

const { toggleMobile } = useSidebarState();
const { can: adminCan } = useAdminAccess();
const canManage = computed(() => adminCan("subscriptions.manage"));

const TIER = { starter: "Tier 1", business: "Tier 2", professional: "Tier 3" };
const LIMITS = [
  { key: "products", label: "Products" },
  { key: "staff_employees", label: "Employee accounts" },
  { key: "warehouses", label: "Warehouses" },
];
const MODULE_LABEL = {
  products: "Products", reservations: "Reservations", calendar: "Calendar", finance: "Finance", staff: "Staff schedules",
  procurement: "Procurement", suppliers: "Suppliers", warehouse: "Warehouse", supply_chain: "Supply chain", orders: "Purchase orders",
  logistics: "Logistics", deliveries: "Deliveries", scanning: "Order scan", crm: "CRM", hr: "HR", employees: "Employees",
  attendance: "Attendance", payroll: "Payroll", leave: "Leave", photo_to_3d: "Photo to 3D",
};

const plans = ref([]);
const loading = ref(false);
const error = ref("");

const peso = (n) => `₱${Number(n || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const when = (d) => new Date(d).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" });

function formOf(p) {
  const unlimited = {};
  const limits = {};
  for (const l of LIMITS) {
    const v = p.limits?.[l.key];
    unlimited[l.key] = v === null || v === undefined;
    limits[l.key] = v ?? 0;
  }
  return { name: p.name, monthly_price: Number(p.monthly_price), description: p.description ?? "", featured: !!p.featured, highlights: [...(p.highlights ?? [])], limits, unlimited };
}

// What would be saved, to compare with what's there now
const payloadOf = (p) => ({
  name: p.form.name,
  monthly_price: Number(p.form.monthly_price),
  description: p.form.description || null,
  featured: !!p.form.featured,
  highlights: p.form.highlights.map((h) => h.trim()).filter(Boolean),
  limits: Object.fromEntries(LIMITS.map((l) => [l.key, p.form.unlimited[l.key] ? null : Number(p.form.limits[l.key])])),
});
const savedOf = (p) => JSON.stringify(payloadOf({ form: formOf(p) }));
const dirty = (p) => JSON.stringify(payloadOf(p)) !== p.saved;

function wrap(p) {
  const row = { ...p, form: formOf(p), errors: {}, formError: "", busy: false };
  row.saved = savedOf(p);
  return row;
}

async function load() {
  loading.value = true;
  error.value = "";
  try {
    const { data } = await api.get("/admin/subscription-plans");
    plans.value = (data.data?.plans ?? []).map(wrap);
    markClean();
  } catch {
    error.value = "The plans couldn't be loaded.";
  } finally {
    loading.value = false;
  }
}

function validate(p) {
  p.errors = {};
  const f = p.form;
  if (!f.name || f.name.length < 2) p.errors.name = "Give the plan a name.";
  else if (plans.value.some((o) => o.key !== p.key && o.form.name.trim().toLowerCase() === f.name.trim().toLowerCase())) p.errors.name = "Another plan has this name.";
  if (!(Number(f.monthly_price) >= 20)) p.errors.monthly_price = "At least ₱20, the smallest amount PayMongo accepts.";
  if (LIMITS.some((l) => !f.unlimited[l.key] && !(Number.isInteger(Number(f.limits[l.key])) && Number(f.limits[l.key]) >= 0))) p.errors.limits = "Limits are whole numbers, 0 or more.";
  return !Object.keys(p.errors).length;
}

// Saves every plan that was changed (from the unsaved-changes bar)
const saving = ref(false);
async function saveAll() {
  const changed = plans.value.filter(dirty);
  changed.forEach((p) => (p.formError = ""));
  // Check them all first, so nothing is half saved
  const bad = changed.filter((p) => !validate(p));
  if (bad.length) {
    toast.error("Fix the highlighted plan before saving.");
    nextTick(() => document.querySelector(".plan-card .err")?.scrollIntoView({ behavior: "smooth", block: "center" }));
    return;
  }
  saving.value = true;
  let saved = 0;
  try {
    for (const p of changed) {
      p.busy = true;
      try {
        await api.put(`/admin/subscription-plans/${p.key}`, payloadOf(p));
        saved++;
      } catch (e) {
        const errs = e?.response?.data?.errors ?? {};
        for (const [k, v] of Object.entries(errs)) p.errors[k.startsWith("limits") ? "limits" : k] = Array.isArray(v) ? v[0] : v;
        p.formError = e?.response?.data?.message || "Couldn't save the plan.";
        toast.error(`${p.form.name || "A plan"} couldn't be saved.`);
        return;
      } finally {
        p.busy = false;
      }
    }
  } finally {
    saving.value = false;
    // Marking one plan "Most popular" clears it on the others
    if (saved) {
      toast.success(saved === 1 ? "Plan saved." : `${saved} plans saved.`);
      await load();
    }
  }
}

function discardAll() {
  for (const p of plans.value) {
    p.form = formOf(p);
    p.errors = {};
    p.formError = "";
  }
}

const FIELD_LABEL = { name: "Plan name", monthly_price: "Monthly price", featured: "Most popular", description: "Description", highlights: "Highlights", limits: "Limits" };
const { markClean } = useUnsavedChanges({
  look: "admin",
  saveLabel: "Save",
  saving: () => saving.value,
  save: saveAll,
  discard: discardAll,
  enabled: () => canManage.value,
  fields: () =>
    plans.value.flatMap((p) => {
      const now = payloadOf(p);
      return Object.keys(FIELD_LABEL).map((k) => ({ key: `${p.key}.${k}`, label: `${p.name}: ${FIELD_LABEL[k]}`, value: now[k] }));
    }),
});

function reset(p) {
  // Reloading replaces every card, so unsaved edits are dealt with first
  guardUnsaved(() => resetPlan(p));
}
async function resetPlan(p) {
  p.busy = true;
  try {
    const { data } = await api.post(`/admin/subscription-plans/${p.key}/reset`);
    toast.success(data.message || "Reset.");
    await load();
  } catch {
    p.formError = "Couldn't reset the plan.";
  } finally {
    p.busy = false;
  }
}

onMounted(load);
</script>

<style scoped>
* {
  font-family: "Poppins", "Segoe UI", sans-serif;
  box-sizing: border-box;
}
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: var(--a-subtle);
}
.main-content {
  margin-left: 240px;
  flex: 1;
  min-width: 0;
  min-height: 100vh;
  transition: margin-left 0.3s;
}
.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 28px;
  background: var(--a-surface);
  border-bottom: 1px solid var(--a-border);
  position: sticky;
  top: 0;
  z-index: 100;
  flex-wrap: wrap;
}
.header-left,
.header-right {
  display: flex;
  align-items: center;
  gap: 14px;
}
.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: var(--a-muted);
  cursor: pointer;
  padding: 4px;
}
.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.2;
  color: var(--a-text);
}
.page-subtitle {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--a-faint);
}
.page-body {
  padding: 20px 28px 40px;
}
.note {
  margin: 0 0 16px;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--a-amber-soft);
  color: var(--a-amber-text);
  font-size: 12.5px;
}
.note.bad {
  background: var(--a-red-soft);
  color: var(--a-red-text);
}

.plans {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
  align-items: start;
}
.plan-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 20px;
  border: 1px solid var(--a-border);
  border-radius: 16px;
  background: var(--a-surface);
}
.plan-card.featured {
  border-color: var(--a-accent);
  box-shadow: 0 0 0 3px var(--a-accent-soft);
}
.plan-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.plan-tier {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--a-accent-text);
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}
.fs {
  margin: 0;
  padding: 0;
  border: 0;
}
.fs legend {
  margin-bottom: 6px;
  padding: 0;
}
.label {
  font-size: 12px;
  font-weight: 600;
  color: var(--a-text-2);
}
.label small {
  font-weight: 400;
  color: var(--a-faint);
}
.input {
  width: 100%;
  min-height: 40px;
  padding: 9px 12px;
  border: 1px solid var(--a-border-strong);
  border-radius: 10px;
  background: var(--a-surface);
  color: var(--a-text);
  font-size: 13px;
  resize: vertical;
}
.input:focus {
  outline: none;
  border-color: var(--a-accent);
  box-shadow: 0 0 0 3px var(--a-accent-soft);
}
.input:disabled {
  background: var(--a-subtle);
  color: var(--a-muted);
}
.input[aria-invalid="true"] {
  border-color: var(--a-red-text);
}
.name-input {
  font-size: 16px;
  font-weight: 600;
}
.price-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}
.peso {
  font-size: 20px;
  font-weight: 600;
  color: var(--a-text);
}
.price-input {
  max-width: 150px;
  font-size: 20px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.per {
  font-size: 12px;
  color: var(--a-muted);
}
.toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: var(--a-text-2);
  cursor: pointer;
}
.toggle input,
.unl input {
  width: 16px;
  height: 16px;
  accent-color: var(--a-accent);
}
.hl-row {
  display: flex;
  gap: 6px;
}
.icon-btn {
  flex: none;
  display: grid;
  place-items: center;
  width: 40px;
  border: 1px solid var(--a-border);
  border-radius: 10px;
  background: var(--a-surface);
  color: var(--a-muted);
  cursor: pointer;
}
.icon-btn:hover {
  color: var(--a-red-text);
  border-color: var(--a-red-border);
}
.limit-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 96px auto;
  align-items: center;
  gap: 8px;
}
.limit-name {
  font-size: 12.5px;
  color: var(--a-text);
}
.limit-input {
  min-height: 36px;
  padding: 6px 10px;
  text-align: right;
}
.unl {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--a-muted);
  cursor: pointer;
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}
.chip {
  padding: 3px 9px;
  border-radius: 999px;
  background: var(--a-accent-soft);
  color: var(--a-accent-text);
  font-size: 11px;
}
.hint,
.edited {
  margin: 0;
  font-size: 11.5px;
  color: var(--a-faint);
}
.err {
  margin: 0;
  font-size: 11.5px;
  color: var(--a-red-text);
}
.link-btn {
  align-self: flex-start;
  padding: 0;
  border: 0;
  background: none;
  color: var(--a-accent-text);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--a-border);
}
.ghost-btn,
.primary-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 38px;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}
.ghost-btn {
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  color: var(--a-text-2);
}
.ghost-btn:hover:not(:disabled) {
  border-color: var(--a-accent-muted);
  color: var(--a-accent-text);
}
.primary-btn {
  border: none;
  background: var(--a-accent);
  color: var(--a-accent-ink);
}
.primary-btn:hover:not(:disabled) {
  background: var(--a-accent-deep);
}
.ghost-btn:disabled,
.primary-btn:disabled {
  opacity: 0.55;
  cursor: default;
}
.input:focus-visible,
.ghost-btn:focus-visible,
.primary-btn:focus-visible,
.link-btn:focus-visible,
.icon-btn:focus-visible {
  outline: 2px solid var(--a-accent);
  outline-offset: 2px;
}

.sk {
  border-radius: 6px;
  background: linear-gradient(100deg, var(--a-skel) 30%, var(--a-skel-shine) 50%, var(--a-skel) 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
.sk-line {
  height: 12px;
}
.sk-value {
  height: 32px;
  width: 60%;
}
.sk-block {
  height: 160px;
}
.w40 {
  width: 40%;
}
.w70 {
  width: 70%;
}
@keyframes shimmer {
  from {
    background-position: 100% 0;
  }
  to {
    background-position: 0 0;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
}
@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
}
@media (max-width: 1100px) {
  .plans {
    grid-template-columns: minmax(0, 1fr);
    max-width: 640px;
  }
}
@media (max-width: 968px) {
  .main-content {
    margin-left: 0;
  }
  .mobile-toggle {
    display: flex;
  }
}
@media (max-width: 640px) {
  .content-header {
    padding: 16px;
  }
  .page-body {
    padding: 16px 16px 32px;
  }
}
</style>
