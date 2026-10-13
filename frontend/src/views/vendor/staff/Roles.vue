<template>
  <ShopLayout section="staff" title="Roles & Permissions" subtitle="A role is a set of permissions. Give each person one role; the ERP only shows them what their role allows.">
    <template #actions>
      <button v-if="ready" type="button" class="sp-btn sp-btn-primary" @click="openEditor()"><span v-html="ICONS.plus"></span>New role</button>
    </template>

    <div v-if="settingUp" class="sp-banner info"><span v-html="ICONS.clock"></span>{{ settingUp }}</div>
    <div v-else-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <div v-if="!data && !settingUp" class="ro-grid">
      <div v-for="n in 3" :key="n" class="sp-card ro-card">
        <div class="bc-skeleton sp-sk-line w50"></div>
        <div class="bc-skeleton sp-sk-line w70"></div>
        <div class="bc-skeleton sp-sk-block"></div>
      </div>
    </div>

    <div v-else-if="data && !data.roles.length" class="sp-card sp-empty">
      <span class="sp-empty-icon" v-html="ICONS.lock"></span>
      <h3>No roles yet</h3>
      <p>Create a role like “Florist” or “Delivery rider”, choose what it can do, then give it to people in Employees.</p>
      <button type="button" class="sp-btn sp-btn-primary" @click="openEditor()"><span v-html="ICONS.plus"></span>New role</button>
    </div>

    <div v-else-if="data" class="ro-grid">
      <article v-for="r in data.roles" :key="r.id" class="sp-card ro-card">
        <header class="ro-head">
          <span class="ro-dot" :style="{ background: r.color || '#a0aec0' }"></span>
          <div class="ro-title">
            <h3>{{ r.name }}</h3>
            <p>{{ r.description || "No description" }}</p>
          </div>
          <router-link :to="{ name: 'VendorStaffList', query: { role: r.name } }" class="sp-chip slate ro-count">
            {{ r.members }} {{ r.members === 1 ? "person" : "people" }}
          </router-link>
        </header>

        <div class="ro-summary">
          <template v-for="g in summarize(r)" :key="g.group">
            <div class="ro-group">
              <span class="ro-group-label">{{ g.label }}</span>
              <ul>
                <li v-for="a in g.areas" :key="a.module" :class="{ full: a.full }">
                  <span class="ro-check" v-html="ICONS.check"></span>
                  <span class="ro-area">{{ a.label }}</span>
                  <span class="ro-acts">{{ a.full ? "Full access" : a.actions }}</span>
                </li>
              </ul>
            </div>
          </template>
          <p v-if="!r.permissions.length" class="sp-hint">This role can't do anything yet. People with it can sign in but see nothing.</p>
        </div>

        <footer class="ro-foot">
          <span class="ro-total">{{ r.permissions.length }} of {{ totalPerms }} permissions</span>
          <div class="ro-actions">
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="openEditor(r, true)">Duplicate</button>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="openEditor(r)"><span v-html="ICONS.edit"></span>Edit</button>
            <button
              type="button"
              class="sp-icon-btn danger"
              :disabled="r.members > 0"
              :title="r.members ? 'Move its people to another role first' : ''"
              :aria-label="`Delete ${r.name}`"
              @click="deleting = r"
            >
              <span v-html="ICONS.trash"></span>
            </button>
          </div>
        </footer>
      </article>
    </div>

    <!-- Role editor -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="form" class="sp-backdrop" @click="closeEditor"></div></Transition>
      <Transition name="sp-slide">
        <form v-if="form" class="sp-drawer ro-drawer" role="dialog" aria-modal="true" aria-labelledby="ro-title" novalidate @submit.prevent="save">
          <div class="sp-drawer-head">
            <div>
              <h3 id="ro-title">{{ form.id ? `Edit “${form.original}”` : "New role" }}</h3>
              <p>{{ form.id && form.members ? `Changes apply to ${form.members} ${form.members === 1 ? "person" : "people"} straight away.` : "Choose what people with this role can see and do in the ERP." }}</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeEditor"><span v-html="ICONS.close"></span></button>
          </div>

          <div class="sp-drawer-body">
            <div v-if="!form.id" class="sp-field">
              <span class="sp-label">Start from</span>
              <div class="ro-presets">
                <button v-for="p in ROLE_PRESETS" :key="p.name" type="button" class="sp-pick" :class="{ on: form.preset === p.name }" @click="applyPreset(p)">{{ p.name }}</button>
                <button type="button" class="sp-pick" :class="{ on: form.preset === '' }" @click="applyPreset(null)">Blank</button>
              </div>
            </div>

            <div class="sp-grid-2">
              <label class="sp-field">
                <span>Name</span>
                <input v-model="form.name" class="sp-input" :class="{ invalid: errors.name }" maxlength="60" placeholder="e.g. Florist" />
                <small v-if="errors.name" class="sp-error">{{ errors.name }}</small>
              </label>
              <div class="sp-field">
                <span class="sp-label">Colour</span>
                <div class="ro-colors">
                  <button
                    v-for="c in ROLE_COLORS"
                    :key="c"
                    type="button"
                    class="ro-swatch"
                    :class="{ on: form.color === c }"
                    :style="{ background: c }"
                    :aria-label="`Colour ${c}`"
                    :aria-pressed="form.color === c"
                    @click="form.color = c"
                  ></button>
                </div>
              </div>
              <label class="sp-field sp-span-all">
                <span>Description <small>(optional)</small></span>
                <input v-model="form.description" class="sp-input" maxlength="255" placeholder="What this person does in the shop" />
              </label>
            </div>

            <div class="ro-perm-head">
              <div>
                <h4>Permissions</h4>
                <p>{{ form.permissions.length }} selected · “See” is added for you when you pick anything else in an area.</p>
              </div>
              <button v-if="form.permissions.length" type="button" class="sp-btn sp-btn-sm link" @click="form.permissions = []">Clear all</button>
            </div>
            <small v-if="errors.permissions" class="sp-error">{{ errors.permissions }}</small>

            <section v-for="g in groups" :key="g.group" class="ro-edit-group">
              <h5>{{ g.label }}</h5>
              <div v-for="a in g.areas" :key="a.module" class="ro-area-row" :class="{ locked: !a.available && !hasAny(a) }">
                <div class="ro-area-top">
                  <div>
                    <strong>{{ a.label }} <span v-if="!a.available" class="sp-chip slate">🔒 Not in your plan</span></strong>
                    <small>{{ a.description }}</small>
                  </div>
                  <label v-if="a.available || hasAny(a)" class="ro-all">
                    <input type="checkbox" :checked="hasAll(a)" :indeterminate.prop="hasAny(a) && !hasAll(a)" @change="toggleArea(a, $event.target.checked)" />
                    <span>All</span>
                  </label>
                </div>
                <div class="ro-acts-edit">
                  <label v-for="p in a.perms" :key="p.key" class="ro-perm" :class="{ on: form.permissions.includes(p.key) }">
                    <input
                      type="checkbox"
                      :checked="form.permissions.includes(p.key)"
                      :disabled="!a.available && !form.permissions.includes(p.key)"
                      @change="toggle(a, p, $event.target.checked)"
                    />
                    <span>{{ p.label }}</span>
                  </label>
                </div>
              </div>
            </section>
            <p v-if="formError" class="sp-error">{{ formError }}</p>
          </div>

          <div class="sp-drawer-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="closeEditor">Cancel</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="saving">{{ saving ? "Saving…" : form.id ? "Save role" : "Create role" }}</button>
          </div>
        </form>
      </Transition>
    </Teleport>

    <!-- Delete -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="deleting" class="sp-backdrop" @click="deleting = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="deleting" class="sp-modal-wrap">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="rod-title">
            <div class="sp-modal-head">
              <div>
                <h3 id="rod-title">Delete “{{ deleting.name }}”?</h3>
                <p>Nobody has this role, so no one loses access.</p>
              </div>
            </div>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="deleting = null">Cancel</button>
              <button type="button" class="sp-btn sp-btn-danger" :disabled="saving" @click="remove">{{ saving ? "Deleting…" : "Delete role" }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { ACTION_WORD, ROLE_COLORS, ROLE_PRESETS, groupCatalog } from "../../../components/vendor/staff/staffUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";

const data = ref(null);
const error = ref("");
const settingUp = ref("");
const form = ref(null);
const errors = reactive({});
const formError = ref("");
const saving = ref(false);
const deleting = ref(null);

const ready = computed(() => !!data.value);
const groups = computed(() => groupCatalog(data.value?.catalog));
const totalPerms = computed(() => (data.value?.catalog ?? []).reduce((n, c) => n + c.actions.length, 0));

async function load() {
  error.value = "";
  try {
    const res = await api.get("vendor/staff/roles");
    data.value = res.data.data;
  } catch (e) {
    if (e?.response?.data?.code === "staff_setting_up") settingUp.value = e.response.data.message;
    else error.value = apiMessage(e, "Couldn't load your roles.");
  }
}

/** What a role allows, per group: areas with "See, add, edit" or "Full access". */
function summarize(role) {
  const has = new Set(role.permissions);
  return groups.value
    .map((g) => ({
      group: g.group,
      label: g.label,
      areas: g.areas
        .map((a) => {
          const on = a.perms.filter((p) => has.has(p.key));
          if (!on.length) return null;
          const words = on.map((p) => ACTION_WORD[p.action] ?? p.action);
          const text = [...new Set(words)].join(", ");
          return { module: a.module, label: a.label, full: on.length === a.perms.length && a.perms.length > 1, actions: text.charAt(0).toUpperCase() + text.slice(1) };
        })
        .filter(Boolean),
    }))
    .filter((g) => g.areas.length);
}

function openEditor(role = null, copy = false) {
  Object.keys(errors).forEach((k) => delete errors[k]);
  formError.value = "";
  form.value = role
    ? {
        id: copy ? null : role.id,
        original: role.name,
        members: copy ? 0 : role.members,
        name: copy ? `${role.name} copy` : role.name,
        description: role.description ?? "",
        color: role.color || ROLE_COLORS[0],
        permissions: [...role.permissions],
        preset: copy ? null : undefined,
      }
    : { id: null, name: "", description: "", color: ROLE_COLORS[(data.value?.roles.length ?? 0) % ROLE_COLORS.length], permissions: [], preset: "" };
}
function closeEditor() {
  if (!saving.value) form.value = null;
}
function applyPreset(p) {
  form.value.preset = p?.name ?? "";
  if (!p) {
    form.value.permissions = [];
    return;
  }
  const available = new Set((data.value?.catalog ?? []).filter((c) => c.available).map((c) => c.module));
  if (!form.value.name.trim() || ROLE_PRESETS.some((x) => x.name === form.value.name)) form.value.name = p.name;
  form.value.description = p.description;
  form.value.color = p.color;
  form.value.permissions = p.permissions.filter((k) => available.has(k.split(".")[0]));
}

const hasAny = (a) => a.perms.some((p) => form.value.permissions.includes(p.key));
const hasAll = (a) => a.perms.every((p) => form.value.permissions.includes(p.key));

function toggle(area, perm, on) {
  const set = new Set(form.value.permissions);
  if (on) {
    set.add(perm.key);
    // You can't approve what you can't see
    if (perm.action !== "view" && area.perms.some((p) => p.action === "view")) set.add(`${area.module}.view`);
  } else {
    set.delete(perm.key);
    if (perm.action === "view") area.perms.forEach((p) => set.delete(p.key));
  }
  form.value.permissions = [...set];
}
function toggleArea(area, on) {
  const set = new Set(form.value.permissions);
  area.perms.forEach((p) => (on ? set.add(p.key) : set.delete(p.key)));
  form.value.permissions = [...set];
}

async function save() {
  if (saving.value) return;
  Object.keys(errors).forEach((k) => delete errors[k]);
  formError.value = "";
  const f = form.value;
  if (f.name.trim().length < 2) {
    errors.name = "Give the role a name.";
    return;
  }
  saving.value = true;
  try {
    const body = { name: f.name.trim(), description: f.description.trim() || null, color: f.color, permissions: f.permissions };
    const { data: res } = f.id ? await api.put(`vendor/staff/roles/${f.id}`, body) : await api.post("vendor/staff/roles", body);
    toast.success(res.message);
    form.value = null;
    await load();
  } catch (e) {
    const fieldErrors = e?.response?.data?.errors ?? {};
    for (const [key, list] of Object.entries(fieldErrors)) errors[key] = [].concat(list)[0];
    formError.value = Object.keys(fieldErrors).length ? "" : apiMessage(e, "Couldn't save the role.");
  } finally {
    saving.value = false;
  }
}

async function remove() {
  saving.value = true;
  try {
    const { data: res } = await api.delete(`vendor/staff/roles/${deleting.value.id}`);
    toast.success(res.message);
    deleting.value = null;
    await load();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't delete the role."));
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
/* Masonry-style columns, so short role cards don't leave gaps */
.ro-grid {
  columns: 320px;
  column-gap: 16px;
}
.ro-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 0;
  margin-bottom: 16px;
  break-inside: avoid;
}
.ro-head {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.ro-dot {
  flex: none;
  width: 12px;
  height: 12px;
  margin-top: 6px;
  border-radius: 50%;
  box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.04);
}
.ro-title {
  flex: 1;
  min-width: 0;
}
.ro-title h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  overflow-wrap: anywhere;
}
.ro-title p {
  margin: 3px 0 0;
  font-size: 12.5px;
  color: #718096;
  overflow-wrap: anywhere;
}
.ro-count {
  flex: none;
  text-decoration: none;
}
.ro-count:hover {
  background: #e2e8f0;
}
.ro-summary {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ro-group-label {
  display: block;
  margin-bottom: 6px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #a0aec0;
}
.ro-group ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.ro-group li {
  display: grid;
  grid-template-columns: 16px minmax(0, auto) minmax(0, 1fr);
  align-items: baseline;
  gap: 8px;
  font-size: 13px;
}
.ro-check {
  display: inline-grid;
  place-items: center;
  width: 16px;
  height: 16px;
  border-radius: 5px;
  background: #e6f9ee;
  color: #2f855a;
  align-self: center;
}
.ro-check :deep(svg) {
  width: 11px;
  height: 11px;
}
.ro-area {
  font-weight: 500;
  white-space: nowrap;
}
.ro-acts {
  color: #718096;
  font-size: 12.5px;
  text-align: right;
  overflow-wrap: anywhere;
}
li.full .ro-acts {
  color: #2f855a;
  font-weight: 500;
}
.ro-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid #edf2f7;
}
.ro-total {
  font-size: 12px;
  color: #a0aec0;
}
.ro-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.ro-actions .sp-icon-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

/* Editor */
.ro-drawer {
  width: min(720px, 100vw);
}
.ro-presets,
.ro-colors {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.ro-swatch {
  width: 26px;
  height: 26px;
  border: 2px solid #fff;
  border-radius: 50%;
  box-shadow: 0 0 0 1px #e2e8f0;
  cursor: pointer;
}
.ro-swatch.on {
  box-shadow: 0 0 0 2px #1a202c;
}
.ro-perm-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  margin: 22px 0 6px;
  padding-top: 18px;
  border-top: 1px solid #edf2f7;
}
.ro-perm-head h4 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
}
.ro-perm-head p {
  margin: 3px 0 0;
  font-size: 12.5px;
  color: #718096;
}
.ro-edit-group {
  margin-top: 16px;
}
.ro-edit-group h5 {
  margin: 0 0 8px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #a0aec0;
}
.ro-area-row {
  padding: 12px 14px;
  border: 1px solid #edf2f7;
  border-radius: 14px;
  background: #fff;
}
.ro-area-row + .ro-area-row {
  margin-top: 8px;
}
.ro-area-row.locked {
  background: #fafbfc;
}
.ro-area-row.locked .ro-acts-edit {
  opacity: 0.55;
}
.ro-area-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}
.ro-area-top strong {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 13.5px;
  font-weight: 600;
}
.ro-area-top small {
  display: block;
  margin-top: 2px;
  font-size: 12px;
  line-height: 1.45;
  color: #718096;
}
.ro-all {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex: none;
  font-size: 12.5px;
  color: #4a5568;
  cursor: pointer;
}
.ro-acts-edit {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 10px;
}
.ro-perm {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  font-size: 12.5px;
  color: #4a5568;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.ro-perm.on {
  border-color: #9ae6b4;
  background: #f0fff4;
  color: #22543d;
}
.ro-perm input {
  margin: 0;
  accent-color: #48bb78;
}
.ro-perm:has(input:disabled) {
  cursor: not-allowed;
}
.ro-all input {
  accent-color: #48bb78;
}
@media (max-width: 640px) {
  .ro-grid {
    columns: 1;
  }
  .ro-group li {
    grid-template-columns: 16px minmax(0, 1fr);
  }
  .ro-acts {
    grid-column: 2;
    text-align: left;
  }
}
</style>
