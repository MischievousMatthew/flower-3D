<template>
  <HrPage title="Employee directory" subtitle="Everyone at the shop and how to reach them. Profiles added by the shop owner show here too." :tabs="EMPLOYEE_TABS">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="load"><span v-html="ICONS.history"></span>{{ loading ? "Refreshing…" : "Refresh" }}</button>
      <router-link v-if="canCreate" :to="{ name: 'EmployeeProfiles', query: { add: 1 } }" class="sp-btn sp-btn-primary dr-link"><span v-html="ICONS.plus"></span>Add profile</router-link>
    </template>

    <section class="sp-card">
      <div class="sp-toolbar dr-toolbar">
        <label class="sp-search">
          <span v-html="ICONS.search"></span>
          <input v-model="search" class="sp-input" type="search" placeholder="Name, position, department or location" aria-label="Search people" />
        </label>
        <div class="dr-filters">
          <select v-model="department" class="sp-select" aria-label="Department">
            <option value="">All departments</option>
            <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
          </select>
          <select v-model="status" class="sp-select" aria-label="Status">
            <option value="">Every status</option>
            <option v-for="s in EMPLOYMENT_STATUSES" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
      </div>

      <p v-if="error" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ error }}<button type="button" @click="load">Try again</button></p>

      <div v-else-if="employees === null" class="dr-grid">
        <div v-for="n in 6" :key="n" class="dr-card"><div class="bc-skeleton dr-sk-avatar"></div><div class="bc-skeleton sp-sk-line w70"></div><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div>
      </div>

      <div v-else-if="!shown.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.users"></span>
        <h3>{{ employees.length ? "Nobody matches" : "No one here yet" }}</h3>
        <p>{{ employees.length ? "Try another search or filter." : "Profiles that HR or the shop owner add show up here." }}</p>
      </div>

      <div v-else class="dr-grid">
        <article v-for="e in shown" :key="e.id" class="dr-card" :class="{ inactive: e.employment_status === 'Inactive' }">
          <div class="dr-menu-wrap">
            <button type="button" class="sp-icon-btn" :aria-label="`More for ${e.full_name}`" :aria-expanded="menuId === e.id" @click.stop="menuId = menuId === e.id ? null : e.id"><span v-html="ICONS.more"></span></button>
            <div v-if="menuId === e.id" class="dr-menu" role="menu" @click.stop>
              <button type="button" role="menuitem" @click="view(e)"><span v-html="ICONS.eye"></span>View profile</button>
              <button type="button" role="menuitem" :disabled="!canEdit" :title="canEdit ? '' : 'Your role can\'t edit profiles.'" @click="edit(e)"><span v-html="ICONS.edit"></span>Edit</button>
              <button type="button" role="menuitem" :disabled="!canEdit" @click="toggleActive(e)"><span v-html="ICONS.check"></span>Mark as {{ e.employment_status === "Inactive" ? "Active" : "Inactive" }}</button>
              <button type="button" role="menuitem" class="danger" :disabled="!canDelete" :title="canDelete ? '' : 'Your role can\'t delete profiles.'" @click="askDelete(e)"><span v-html="ICONS.trash"></span>Delete</button>
            </div>
          </div>

          <button type="button" class="dr-main" @click="view(e)">
            <span class="dr-avatar" :style="{ background: personColor(e.full_name) }">
              <img v-if="e.avatar_url" :src="e.avatar_url" alt="" />
              <template v-else>{{ initialsOf(e.full_name) }}</template>
              <i :class="statusTone(e.employment_status)" aria-hidden="true"></i>
            </span>
            <strong>{{ e.full_name }}</strong>
            <small>{{ e.position || "No position set" }}</small>
            <span class="sp-chip" :class="statusTone(e.employment_status)">{{ e.employment_status }}</span>
          </button>

          <dl class="dr-facts">
            <div><dt>Department</dt><dd>{{ e.department || "—" }}</dd></div>
            <div><dt>Reports to</dt><dd>{{ e.reporting_manager || "—" }}</dd></div>
            <div><dt>Location</dt><dd>{{ e.work_location || "—" }}</dd></div>
            <div><dt>Hired</dt><dd>{{ e.date_hired ? formatDate(day10(e.date_hired)) : "—" }}</dd></div>
          </dl>

          <div class="dr-contact">
            <a v-if="e.mobile_number" :href="`tel:${e.mobile_number}`"><span v-html="ICONS.phone"></span>{{ e.mobile_number }}</a>
            <a v-if="e.work_email" :href="`mailto:${e.work_email}`"><span v-html="ICONS.send"></span>{{ e.work_email }}</a>
          </div>
          <small class="dr-by">Added by {{ e.added_by || "HR" }}</small>
        </article>
      </div>
    </section>

    <!-- Delete -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="deleting" class="sp-backdrop" @click="deleting = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="deleting" class="sp-modal-wrap">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="dr-del-title">
            <div class="sp-modal-head">
              <div>
                <h3 id="dr-del-title">Delete {{ deleting.full_name }}?</h3>
                <p>Their profile, documents and QR code stop working here and in the vendor portal. Past attendance and payroll stay.</p>
              </div>
            </div>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="deleting = null">Cancel</button>
              <button type="button" class="sp-btn sp-btn-danger" :disabled="busy" @click="confirmDelete">{{ busy ? "Deleting…" : "Delete" }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, onActivated, onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import HrPage from "../../../../components/hr/HrPage.vue";
import employeeInfoService from "../../../../services/employeeInfoService";
import { useAssignment } from "../../../../composables/useAssignment";
import { SHOP_ICONS as ICONS } from "../../../../components/vendor/shop/shopIcons";
import { formatDate } from "../../../../components/vendor/finance/financeUi";
import { personColor } from "../../../../components/vendor/staff/staffUi";
import { EMPLOYEE_TABS, EMPLOYMENT_STATUSES, day10, initialsOf, statusTone } from "../../../../components/hr/hrUi";

const router = useRouter();
const { can } = useAssignment();
const canCreate = computed(() => can("employees", "create"));
const canEdit = computed(() => can("employees", "edit"));
const canDelete = computed(() => can("employees", "delete"));

const employees = ref(null);
const loading = ref(false);
const error = ref("");
const search = ref("");
const department = ref("");
const status = ref("");
const menuId = ref(null);
const deleting = ref(null);
const busy = ref(false);

const departments = computed(() => [...new Set((employees.value ?? []).map((e) => e.department).filter(Boolean))].sort());
const shown = computed(() => {
  const q = search.value.trim().toLowerCase();
  return (employees.value ?? []).filter(
    (e) =>
      (!department.value || e.department === department.value)
      && (!status.value || e.employment_status === status.value)
      && (!q || [e.full_name, e.position, e.department, e.work_location].some((v) => v && v.toLowerCase().includes(q))),
  );
});

async function load() {
  if (loading.value) return;
  loading.value = true;
  error.value = "";
  try {
    const res = await employeeInfoService.getAll({ per_page: 1000, sort_by: "first_name", sort_order: "asc" });
    employees.value = res.data ?? [];
  } catch (e) {
    if (employees.value === null) employees.value = [];
    error.value = e?.message || "Couldn't load the directory.";
  } finally {
    loading.value = false;
  }
}

const view = (e) => router.push({ name: "EmployeeProfiles", query: { view: e.id } });
const edit = (e) => router.push({ name: "EmployeeProfiles", query: { edit: e.id } });

async function toggleActive(e) {
  menuId.value = null;
  const next = e.employment_status === "Inactive" ? "Active" : "Inactive";
  try {
    await employeeInfoService.updateStatus(e.id, next);
    e.employment_status = next;
    toast.success(`${e.full_name} is now ${next}.`);
  } catch (err) {
    toast.error(err?.message || "Couldn't change the status.");
  }
}

function askDelete(e) {
  menuId.value = null;
  deleting.value = e;
}

async function confirmDelete() {
  if (busy.value) return;
  busy.value = true;
  const e = deleting.value;
  try {
    await employeeInfoService.delete(e.id);
    employees.value = employees.value.filter((x) => x.id !== e.id);
    toast.success(`${e.full_name} was deleted.`);
    deleting.value = null;
  } catch (err) {
    toast.error(err?.message || "Couldn't delete the profile.");
  } finally {
    busy.value = false;
  }
}

const closeMenu = () => (menuId.value = null);
const onKey = (e) => {
  if (e.key !== "Escape") return;
  if (deleting.value) deleting.value = null;
  else menuId.value = null;
};

onMounted(() => {
  document.addEventListener("click", closeMenu);
  document.addEventListener("keydown", onKey);
  load();
});
onActivated(load);
onBeforeUnmount(() => {
  document.removeEventListener("click", closeMenu);
  document.removeEventListener("keydown", onKey);
});
</script>

<style scoped>
.dr-link {
  text-decoration: none;
}
.dr-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 14px;
}
.dr-toolbar .sp-search {
  flex: 1 1 240px;
  max-width: 360px;
}
.dr-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.dr-filters .sp-select {
  width: auto;
  min-width: 150px;
}
.dr-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 14px;
}
.dr-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 0;
  padding: 18px 16px 14px;
  border: 1px solid var(--v-border);
  border-radius: 18px;
  background: var(--v-surface);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.dr-card:hover {
  border-color: var(--bc-accent-border, var(--v-border-strong));
  box-shadow: 0 6px 18px var(--v-shadow);
}
.dr-card.inactive .dr-avatar {
  filter: grayscale(1);
  opacity: 0.7;
}
.dr-sk-avatar {
  width: 64px;
  height: 64px;
  margin: 0 auto 4px;
  border-radius: 50%;
}
.dr-card .sp-sk-line {
  margin: 0 auto;
}
.dr-main {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  text-align: center;
  cursor: pointer;
}
.dr-main:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 4px;
  border-radius: 12px;
}
.dr-main strong {
  margin-top: 6px;
  max-width: 100%;
  font-size: 15px;
  font-weight: 600;
  color: var(--v-text);
  overflow-wrap: anywhere;
}
.dr-main small {
  margin-bottom: 4px;
  font-size: 12.5px;
  color: var(--v-muted);
}
.dr-avatar {
  position: relative;
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  border-radius: 50%;
  color: #fff;
  font-size: 20px;
  font-weight: 600;
}
.dr-avatar img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
}
.dr-avatar i {
  position: absolute;
  right: 2px;
  bottom: 2px;
  width: 13px;
  height: 13px;
  border: 2px solid var(--v-surface);
  border-radius: 50%;
  background: var(--v-faint);
}
.dr-avatar i.green { background: #48bb78; }
.dr-avatar i.amber { background: #ed8936; }
.dr-avatar i.blue { background: #4299e1; }
.dr-avatar i.violet { background: #9f7aea; }
.dr-facts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin: 0;
  padding-top: 12px;
  border-top: 1px solid var(--v-border);
}
.dr-facts div {
  min-width: 0;
}
.dr-facts dt {
  font-size: 11px;
  color: var(--v-faint);
}
.dr-facts dd {
  margin: 2px 0 0;
  font-size: 12.5px;
  color: var(--v-text);
  overflow-wrap: anywhere;
}
.dr-contact {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.dr-contact a {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  padding: 7px 10px;
  border-radius: 10px;
  background: var(--v-subtle);
  color: var(--v-text-2);
  font-size: 12.5px;
  text-decoration: none;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  transition: background 0.15s, color 0.15s;
}
.dr-contact a:hover {
  background: var(--v-subtle-2);
  color: var(--v-text);
}
.dr-contact a :deep(svg) {
  flex: none;
  width: 14px;
  height: 14px;
  color: var(--v-muted);
}
.dr-by {
  font-size: 11.5px;
  color: var(--v-faint);
  text-align: center;
}
.dr-menu-wrap {
  position: absolute;
  top: 10px;
  right: 10px;
}
.dr-menu {
  position: absolute;
  right: 0;
  top: calc(100% + 4px);
  z-index: 20;
  display: flex;
  flex-direction: column;
  min-width: 190px;
  padding: 6px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-raised);
  box-shadow: 0 12px 32px var(--v-shadow);
}
.dr-menu button {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 10px;
  border: 0;
  border-radius: 8px;
  background: none;
  color: var(--v-text);
  font: inherit;
  font-size: 13px;
  text-align: left;
  white-space: nowrap;
  cursor: pointer;
}
.dr-menu button :deep(svg) {
  width: 15px;
  height: 15px;
  color: var(--v-muted);
}
.dr-menu button:hover:not(:disabled) {
  background: var(--v-subtle);
}
.dr-menu button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.dr-menu button.danger,
.dr-menu button.danger :deep(svg) {
  color: var(--v-red-text);
}
@media (max-width: 760px) {
  .dr-toolbar .sp-search {
    max-width: none;
  }
  .dr-filters,
  .dr-filters .sp-select {
    flex: 1 1 140px;
  }
}
</style>
