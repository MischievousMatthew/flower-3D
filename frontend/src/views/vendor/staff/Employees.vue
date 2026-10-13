<template>
  <ShopLayout section="staff" title="Employees" subtitle="Who works with you, what each person can do in the ERP, and their HR details.">
    <template #actions>
      <button v-if="data" type="button" class="sp-btn sp-btn-primary" :disabled="!canAdd" :title="canAdd ? '' : data.limit?.message" @click="openAccount()">
        <span v-html="ICONS.plus"></span>Add employee
      </button>
    </template>

    <div v-if="settingUp" class="sp-banner info"><span v-html="ICONS.clock"></span>{{ settingUp }}</div>
    <div v-else-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Summary -->
    <section v-if="!settingUp" class="sp-stats" aria-label="Team summary">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <button type="button" class="sp-stat" :class="{ on: tab === 'accounts' && status === '' }" @click="show('accounts', '')">
          <span class="sp-stat-icon tone-green" v-html="ICONS.user"></span>
          <div><strong>{{ counts.active }}</strong><span>Active team members</span></div>
        </button>
        <button type="button" class="sp-stat" :class="{ on: tab === 'accounts' && status === 'On Leave' }" @click="show('accounts', 'On Leave')">
          <span class="sp-stat-icon tone-amber" v-html="ICONS.pause"></span>
          <div><strong>{{ counts.leave }}</strong><span>On leave</span></div>
        </button>
        <button type="button" class="sp-stat" :class="{ on: tab === 'profiles' }" @click="show('profiles')">
          <span class="sp-stat-icon tone-blue" v-html="ICONS.note"></span>
          <div><strong>{{ data.profiles.length }}</strong><span>HR profiles</span></div>
        </button>
        <div class="sp-stat">
          <span class="sp-stat-icon tone-violet" v-html="ICONS.lock"></span>
          <div>
            <strong>{{ data.limit?.unlimited ? data.limit.usage : `${data.limit?.usage ?? data.accounts.length} / ${data.limit?.limit ?? "—"}` }}</strong>
            <span>{{ data.limit?.unlimited ? "Staff accounts" : "Staff accounts in your plan" }}</span>
          </div>
        </div>
      </template>
    </section>

    <section v-if="!settingUp" class="sp-card">
      <div class="sp-toolbar em-toolbar">
        <div class="sp-seg" role="tablist" aria-label="View">
          <button type="button" role="tab" :class="{ on: tab === 'accounts' }" :aria-selected="tab === 'accounts'" @click="tab = 'accounts'">
            Team accounts <span class="count">{{ data?.accounts.length ?? "…" }}</span>
          </button>
          <button type="button" role="tab" :class="{ on: tab === 'profiles' }" :aria-selected="tab === 'profiles'" @click="tab = 'profiles'">
            HR profiles <span class="count">{{ data?.profiles.length ?? "…" }}</span>
          </button>
        </div>
        <div class="em-filters">
          <select v-if="tab === 'accounts'" v-model="roleFilter" class="sp-select" aria-label="Role">
            <option value="">All roles</option>
            <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
            <option value="__none">No role</option>
          </select>
          <select v-if="tab === 'accounts'" v-model="status" class="sp-select" aria-label="Status">
            <option value="">Everyone</option>
            <option value="Active">Active</option>
            <option value="On Leave">On leave</option>
            <option value="Resign">Left the shop</option>
          </select>
          <label class="sp-search">
            <span v-html="ICONS.search"></span>
            <input v-model="search" class="sp-input" type="search" :placeholder="tab === 'accounts' ? 'Name, username or email' : 'Name, position or department'" aria-label="Search people" />
          </label>
        </div>
      </div>

      <!-- Team accounts -->
      <template v-if="tab === 'accounts'">
        <ul v-if="!data" class="em-sk">
          <li v-for="n in 4" :key="n"><div class="bc-skeleton st-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <div v-else-if="!accounts.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.user"></span>
          <h3>{{ data.accounts.length ? "Nobody matches" : "No team accounts yet" }}</h3>
          <p>{{ data.accounts.length ? "Try another role, status or search." : "Add the people who help you. Each gets a username to sign in to the ERP, and a role that decides what they see." }}</p>
        </div>
        <div v-else class="st-scroll">
          <table class="st-table cards">
            <colgroup>
              <col style="width: 30%" />
              <col style="width: 20%" />
              <col style="width: 14%" />
              <col style="width: 14%" />
              <col style="width: 14%" />
              <col style="width: 8%" />
            </colgroup>
            <thead>
              <tr><th>Person</th><th>Role</th><th>Status</th><th>Joined</th><th>Last change</th><th><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody>
              <tr v-for="a in accounts" :key="a.id" class="clickable" @click="openAccount(a)">
                <td>
                  <div class="st-person">
                    <span class="st-avatar" :style="{ background: a.role?.color || personColor(a.name) }">{{ a.initials }}</span>
                    <div>
                      <strong>{{ a.name }}</strong>
                      <small>@{{ a.username }}<template v-if="a.title"> · {{ a.title }}</template></small>
                    </div>
                  </div>
                </td>
                <td data-label="Role">
                  <span v-if="a.role" class="st-role"><i :style="{ background: a.role.color || '#a0aec0' }"></i>{{ a.role.name }}</span>
                  <span v-else class="sp-chip amber">No role</span>
                </td>
                <td data-label="Status"><span class="sp-chip" :class="STATUS_TONE[a.status] ?? 'slate'">{{ STATUS_LABEL[a.status] ?? a.status }}</span></td>
                <td data-label="Joined">{{ a.joining_date ? formatDate(a.joining_date) : "—" }}</td>
                <td data-label="Last change" :class="{ muted: !a.last_active_at }">{{ a.last_active_at ? relativeTime(a.last_active_at) : "None yet" }}</td>
                <td class="num">
                  <button type="button" class="sp-icon-btn" :aria-label="`Edit ${a.name}`" @click.stop="openAccount(a)"><span v-html="ICONS.edit"></span></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- HR profiles -->
      <template v-else>
        <p v-if="data && !data.hr_available" class="sp-banner warn em-note">
          <span v-html="ICONS.lock"></span>Adding and editing HR profiles needs the Professional plan. You can still see the profiles you already have.
        </p>
        <div v-if="!data" class="em-grid">
          <div v-for="n in 3" :key="n" class="em-profile"><div class="bc-skeleton st-avatar"></div><div class="bc-skeleton sp-sk-line w70"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
        </div>
        <div v-else-if="!profiles.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.note"></span>
          <h3>{{ data.profiles.length ? "Nobody matches" : "No HR profiles yet" }}</h3>
          <p>{{ data.profiles.length ? "Try another search." : "HR profiles hold job details, usual hours and pay. Your HR staff add them in the ERP under HR → Employees." }}</p>
        </div>
        <div v-else class="em-grid">
          <button v-for="p in profiles" :key="p.id" type="button" class="em-profile" @click="profile = p">
            <div class="st-person">
              <span class="st-avatar" :style="{ background: personColor(p.name) }">
                <img v-if="p.avatar" :src="p.avatar" alt="" />
                <template v-else>{{ p.initials }}</template>
              </span>
              <div>
                <strong>{{ p.name }}</strong>
                <small>{{ [...new Set([p.position, p.department].filter(Boolean))].join(" · ") || "No position set" }}</small>
              </div>
            </div>
            <div class="em-tags">
              <span class="sp-chip" :class="isActiveProfile(p) ? 'green' : 'slate'">{{ p.employment_status || "Active" }}</span>
              <span v-if="p.employment_type" class="sp-chip slate">{{ p.employment_type }}</span>
              <span v-if="p.account?.role" class="st-role">{{ p.account.role }}</span>
            </div>
            <dl class="em-facts">
              <div><dt>Usual hours</dt><dd>{{ p.usual_hours ? range12(p.usual_hours) : "Not set" }}</dd></div>
              <div><dt>Rest days</dt><dd>{{ p.rest_days.length ? shortDays(p.rest_days) : "None" }}</dd></div>
              <div><dt>Pay</dt><dd>{{ p.salary ? `${money(p.salary)} ${PER[p.salary_type] ?? ""}` : "Not set" }}</dd></div>
              <div>
                <dt>Today</dt>
                <dd v-if="p.today" class="em-in"><i></i>In {{ time12(p.today.time_in) }}<template v-if="p.today.time_out"> · out {{ time12(p.today.time_out) }}</template></dd>
                <dd v-else class="muted">Not timed in</dd>
              </div>
            </dl>
          </button>
        </div>
      </template>
    </section>

    <!-- Account form -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="form" class="sp-backdrop" @click="closeAccount"></div></Transition>
      <Transition name="sp-slide">
        <form v-if="form" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="em-title" novalidate @submit.prevent="saveAccount">
          <div class="sp-drawer-head">
            <div>
              <h3 id="em-title">{{ form.id ? form.original : "Add an employee" }}</h3>
              <p>{{ form.id ? `Signs in to the ERP as @${form.username}` : "They sign in to the ERP with the username and password you set here." }}</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeAccount"><span v-html="ICONS.close"></span></button>
          </div>

          <div class="sp-drawer-body">
            <h4 class="st-section-title first">Role</h4>
            <div v-if="!roles.length" class="sp-banner warn">
              <span v-html="ICONS.alert"></span>Create a role first, so you can choose what this person can do.
              <router-link :to="{ name: 'VendorStaffRoles' }" class="st-link">Go to Roles</router-link>
            </div>
            <div v-else class="em-roles" role="radiogroup" aria-label="Role">
              <label v-for="r in roles" :key="r.id" class="em-role" :class="{ on: form.staff_role_id === r.id }">
                <input v-model="form.staff_role_id" type="radio" name="role" :value="r.id" />
                <i :style="{ background: r.color || '#a0aec0' }"></i>
                <span><strong>{{ r.name }}</strong><small>{{ r.permissions.length }} {{ r.permissions.length === 1 ? "permission" : "permissions" }}</small></span>
              </label>
            </div>
            <small v-if="errors.staff_role_id" class="sp-error">{{ errors.staff_role_id }}</small>
            <p v-if="roles.length" class="sp-hint">Change what a role can do in <router-link :to="{ name: 'VendorStaffRoles' }" class="st-link">Roles &amp; permissions</router-link>.</p>

            <h4 class="st-section-title">Details</h4>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span>Full name</span>
                <input v-model="form.name" class="sp-input" :class="{ invalid: errors.name }" maxlength="255" autocomplete="off" />
                <small v-if="errors.name" class="sp-error">{{ errors.name }}</small>
              </label>
              <label class="sp-field">
                <span>Job title <small>(optional)</small></span>
                <input v-model="form.title" class="sp-input" maxlength="255" placeholder="e.g. Florist" />
              </label>
              <label class="sp-field">
                <span>Email</span>
                <input v-model="form.email" type="email" class="sp-input" :class="{ invalid: errors.email }" maxlength="255" autocomplete="off" />
                <small v-if="errors.email" class="sp-error">{{ errors.email }}</small>
              </label>
              <label class="sp-field">
                <span>Phone <small>(optional)</small></span>
                <input v-model="form.phone" class="sp-input" maxlength="20" inputmode="tel" />
              </label>
              <label class="sp-field">
                <span>Started on</span>
                <input v-model="form.joining_date" type="date" class="sp-input" :class="{ invalid: errors.joining_date }" />
                <small v-if="errors.joining_date" class="sp-error">{{ errors.joining_date }}</small>
              </label>
              <label v-if="form.id" class="sp-field">
                <span>Status</span>
                <select v-model="form.status" class="sp-select">
                  <option value="Active">Active</option>
                  <option value="On Leave">On leave</option>
                  <option value="Resign">Left the shop</option>
                </select>
              </label>
              <label class="sp-field sp-span-all">
                <span>Address <small>(optional)</small></span>
                <input v-model="form.address" class="sp-input" maxlength="500" />
              </label>
            </div>
            <p v-if="form.id && form.status === 'Resign'" class="sp-banner warn em-note"><span v-html="ICONS.alert"></span>They'll be signed out and can't sign in again until you set them back to Active.</p>

            <h4 class="st-section-title">Sign-in</h4>
            <div class="sp-grid-2">
              <label v-if="!form.id" class="sp-field">
                <span>Username</span>
                <input v-model="form.username" class="sp-input" :class="{ invalid: errors.username }" maxlength="50" autocomplete="off" autocapitalize="off" spellcheck="false" />
                <small v-if="errors.username" class="sp-error">{{ errors.username }}</small>
              </label>
              <label class="sp-field">
                <span>{{ form.id ? "New password" : "Password" }} <small v-if="form.id">(leave blank to keep)</small></span>
                <div class="em-pass">
                  <input v-model="form.password" :type="showPass ? 'text' : 'password'" class="sp-input" :class="{ invalid: errors.password }" maxlength="100" autocomplete="new-password" />
                  <button type="button" class="st-link" @click="showPass = !showPass">{{ showPass ? "Hide" : "Show" }}</button>
                </div>
                <small v-if="errors.password" class="sp-error">{{ errors.password }}</small>
                <small v-else class="st-sub">At least 8 characters.{{ form.id ? " Changing it signs them out." : "" }}</small>
              </label>
            </div>
            <p v-if="formError" class="sp-error">{{ formError }}</p>
          </div>

          <div class="sp-drawer-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="closeAccount">Cancel</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="saving || !roles.length">{{ saving ? "Saving…" : form.id ? "Save changes" : "Add employee" }}</button>
          </div>
        </form>
      </Transition>
    </Teleport>

    <!-- HR profile -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="profile" class="sp-backdrop" @click="profile = null"></div></Transition>
      <Transition name="sp-slide">
        <aside v-if="profile" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="pf-title">
          <div class="sp-drawer-head">
            <div class="st-person">
              <span class="st-avatar" :style="{ background: personColor(profile.name) }">
                <img v-if="profile.avatar" :src="profile.avatar" alt="" />
                <template v-else>{{ profile.initials }}</template>
              </span>
              <div>
                <h3 id="pf-title">{{ profile.name }}</h3>
                <p>{{ [...new Set([profile.position, profile.department].filter(Boolean))].join(" · ") || "No position set" }}</p>
              </div>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="profile = null"><span v-html="ICONS.close"></span></button>
          </div>
          <div class="sp-drawer-body">
            <h4 class="st-section-title first">Work</h4>
            <dl class="st-dl">
              <dt>Status</dt><dd>{{ profile.employment_status || "Active" }}</dd>
              <dt>Type</dt><dd>{{ profile.employment_type || "—" }}</dd>
              <dt>Hired</dt><dd>{{ profile.date_hired ? formatDate(profile.date_hired) : "—" }}</dd>
              <dt>Usual hours</dt><dd>{{ profile.usual_hours ? range12(profile.usual_hours) : "Not set" }}</dd>
              <dt>Rest days</dt><dd>{{ profile.rest_days.length ? profile.rest_days.join(", ") : "None" }}</dd>
              <dt>Pay</dt><dd>{{ profile.salary ? `${money(profile.salary)} ${PER[profile.salary_type] ?? ""}` : "Not set" }}</dd>
            </dl>
            <h4 class="st-section-title">Contact</h4>
            <dl class="st-dl">
              <dt>Email</dt><dd>{{ profile.email || "—" }}</dd>
              <dt>Mobile</dt><dd>{{ profile.mobile || "—" }}</dd>
            </dl>
            <h4 class="st-section-title">ERP access</h4>
            <p v-if="profile.account" class="em-access">
              Signs in as <b>{{ profile.account.name }}</b><template v-if="profile.account.role"> with the <b>{{ profile.account.role }}</b> role</template>.
              <button type="button" class="st-link" @click="editLinked(profile)">Edit account</button>
            </p>
            <p v-else class="sp-hint">No team account uses this person's email, so they can't sign in to the ERP. Add one with the same email to link them.</p>
            <div class="em-jump">
              <router-link :to="{ name: 'VendorStaffAttendance' }" class="sp-btn sp-btn-ghost sp-btn-sm"><span v-html="ICONS.clock"></span>Attendance</router-link>
              <router-link :to="{ name: 'VendorStaffSchedules' }" class="sp-btn sp-btn-ghost sp-btn-sm"><span v-html="ICONS.calendar"></span>Schedule</router-link>
              <router-link :to="{ name: 'VendorStaffPayroll' }" class="sp-btn sp-btn-ghost sp-btn-sm"><span v-html="ICONS.card"></span>Payroll</router-link>
            </div>
          </div>
        </aside>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute } from "vue-router";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { formatDate, money, ymd } from "../../../components/vendor/finance/financeUi";
import { STATUS_LABEL, STATUS_TONE, personColor, range12, relativeTime, time12 } from "../../../components/vendor/staff/staffUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";

const PER = { daily: "a day", weekly: "a week", monthly: "a month", hourly: "an hour" };

const route = useRoute();
const data = ref(null);
const roles = ref([]);
const error = ref("");
const settingUp = ref("");
const tab = ref("accounts");
const search = ref("");
const status = ref("");
const roleFilter = ref(typeof route.query.role === "string" ? route.query.role : "");
const form = ref(null);
const errors = reactive({});
const formError = ref("");
const saving = ref(false);
const showPass = ref(false);
const profile = ref(null);

const canAdd = computed(() => !!data.value && (data.value.limit?.can_create ?? true));
const counts = computed(() => ({
  active: (data.value?.accounts ?? []).filter((a) => a.status === "Active").length,
  leave: (data.value?.accounts ?? []).filter((a) => a.status === "On Leave").length,
}));
const accounts = computed(() => {
  const q = search.value.trim().toLowerCase();
  return (data.value?.accounts ?? []).filter(
    (a) =>
      (!status.value || a.status === status.value)
      && (!roleFilter.value || (roleFilter.value === "__none" ? !a.role : a.role?.name === roleFilter.value))
      && (!q || [a.name, a.username, a.email, a.title].some((v) => v && v.toLowerCase().includes(q))),
  );
});
const profiles = computed(() => {
  const q = search.value.trim().toLowerCase();
  return (data.value?.profiles ?? []).filter((p) => !q || [p.name, p.position, p.department].some((v) => v && v.toLowerCase().includes(q)));
});

const isActiveProfile = (p) => !/resign|terminat|inactive/i.test(p.employment_status ?? "");
const shortDays = (days) => days.map((d) => d.slice(0, 3)).join(", ");

function show(which, s = "") {
  tab.value = which;
  status.value = s;
  roleFilter.value = "";
}

async function load() {
  error.value = "";
  try {
    const [people, roleRes] = await Promise.all([api.get("vendor/staff/employees"), api.get("vendor/staff/roles")]);
    data.value = people.data.data;
    roles.value = roleRes.data.data.roles;
  } catch (e) {
    if (e?.response?.data?.code === "staff_setting_up") settingUp.value = e.response.data.message;
    else error.value = apiMessage(e, "Couldn't load your team.");
  }
}

function openAccount(a = null) {
  Object.keys(errors).forEach((k) => delete errors[k]);
  formError.value = "";
  showPass.value = false;
  form.value = a
    ? { id: a.id, original: a.name, username: a.username, name: a.name, title: a.title ?? "", email: a.email ?? "", phone: a.phone ?? "", address: a.address ?? "", joining_date: a.joining_date ?? ymd(new Date()), status: a.status, staff_role_id: a.role?.id ?? null, password: "" }
    : { id: null, username: "", name: "", title: "", email: "", phone: "", address: "", joining_date: ymd(new Date()), status: "Active", staff_role_id: roles.value.length === 1 ? roles.value[0].id : null, password: "" };
}
function closeAccount() {
  if (!saving.value) form.value = null;
}
function editLinked(p) {
  const a = data.value.accounts.find((x) => x.id === p.account.id);
  profile.value = null;
  if (a) openAccount(a);
}

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  const f = form.value;
  if (!f.staff_role_id) errors.staff_role_id = "Choose a role.";
  if (!f.name.trim()) errors.name = "Enter their name.";
  if (!/^\S+@\S+\.\S+$/.test(f.email.trim())) errors.email = "Enter a valid email.";
  if (!f.joining_date) errors.joining_date = "Pick the day they started.";
  if (!f.id) {
    if (!/^[A-Za-z0-9._-]{3,50}$/.test(f.username.trim())) errors.username = "At least 3 letters or numbers (dots, dashes and underscores are fine).";
    if (f.password.length < 8) errors.password = "Use at least 8 characters.";
  } else if (f.password && f.password.length < 8) {
    errors.password = "Use at least 8 characters.";
  }
  return !Object.keys(errors).length;
}

async function saveAccount() {
  if (saving.value || !validate()) return;
  saving.value = true;
  formError.value = "";
  const f = form.value;
  const body = { name: f.name.trim(), title: f.title.trim() || null, email: f.email.trim(), phone: f.phone.trim() || null, address: f.address.trim() || null, joining_date: f.joining_date, staff_role_id: f.staff_role_id };
  if (f.password) body.password = f.password;
  try {
    const { data: res } = f.id
      ? await api.put(`vendor/staff/employees/${f.id}`, { ...body, status: f.status })
      : await api.post("vendor/staff/employees", { ...body, username: f.username.trim() });
    toast.success(res.message);
    form.value = null;
    await load();
  } catch (e) {
    const fieldErrors = e?.response?.data?.errors ?? {};
    for (const [key, list] of Object.entries(fieldErrors)) errors[key] = [].concat(list)[0];
    formError.value = Object.keys(fieldErrors).length ? "" : apiMessage(e, "Couldn't save.");
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.em-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 8px;
}
.em-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  flex: 1 1 360px;
  justify-content: flex-end;
  min-width: 0;
}
.em-filters .sp-select {
  width: auto;
  min-width: 130px;
}
.em-filters .sp-search {
  flex: 1 1 200px;
  max-width: 300px;
}
.em-sk {
  margin: 0;
  padding: 0;
  list-style: none;
}
.em-sk li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
}
.em-sk .grow {
  flex: 1;
}
.em-note {
  margin: 4px 0 12px;
}
.em-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 12px;
  padding-top: 6px;
}
.em-profile {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
  padding: 16px;
  border: 1px solid #edf2f7;
  border-radius: 16px;
  background: #fff;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
button.em-profile:hover {
  border-color: #9ae6b4;
  box-shadow: 0 4px 14px rgba(72, 187, 120, 0.12);
}
.em-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.em-facts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin: 0;
  padding-top: 12px;
  border-top: 1px solid #f1f5f9;
}
.em-facts div {
  min-width: 0;
}
.em-facts dt {
  font-size: 11px;
  color: #a0aec0;
}
.em-facts dd {
  margin: 2px 0 0;
  font-size: 12.5px;
  color: #2d3748;
  overflow-wrap: anywhere;
}
.em-facts dd.muted {
  color: #a0aec0;
}
.em-in {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #22753f !important;
}
.em-in i {
  flex: none;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #48bb78;
}
.st-section-title.first {
  margin-top: 0;
}
.em-roles {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 8px;
}
.em-role {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.em-role.on {
  border-color: #48bb78;
  background: #f0fff4;
  box-shadow: 0 0 0 1px #48bb78;
}
.em-role input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.em-role:has(input:focus-visible) {
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.3);
}
.em-role i {
  flex: none;
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.em-role span {
  min-width: 0;
}
.em-role strong {
  display: block;
  font-size: 13px;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.em-role small {
  font-size: 11.5px;
  color: #718096;
}
.em-pass {
  position: relative;
}
.em-pass .sp-input {
  padding-right: 56px;
}
.em-pass .st-link {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
}
.em-access {
  margin: 0;
  font-size: 13px;
  line-height: 1.6;
  color: #2d3748;
}
.em-jump {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 22px;
}
.em-jump a {
  text-decoration: none;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}
.sp-drawer-head h3 {
  overflow-wrap: anywhere;
}
@media (max-width: 760px) {
  .em-filters {
    justify-content: stretch;
  }
  .em-filters .sp-search {
    max-width: none;
  }
}
</style>
