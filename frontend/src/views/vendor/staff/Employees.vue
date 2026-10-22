<template>
  <ShopLayout section="staff" title="Employees" subtitle="Who works with you, what each person can do in the ERP, and their HR details.">
    <template #actions>
      <button v-if="data && tab === 'profiles'" type="button" class="sp-btn sp-btn-primary" :disabled="!data.hr_available" :title="data.hr_available ? '' : 'Needs the Professional plan'" @click="openProfileForm()">
        <span v-html="ICONS.plus"></span>Add HR profile
      </button>
      <button v-else-if="data" type="button" class="sp-btn sp-btn-primary" :disabled="!canAdd" :title="canAdd ? '' : data.limit?.message" @click="openAccount()">
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
                      <small>{{ a.username.includes("@") ? a.username : `@${a.username}` }}<template v-if="a.title"> · {{ a.title }}</template></small>
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
          <p>{{ data.profiles.length ? "Try another search." : "HR profiles hold contact details, emergency contacts, job details, documents and pay. Add them here, or your HR staff can add them in the ERP — both of you see the same profiles." }}</p>
          <button v-if="!data.profiles.length && data.hr_available" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="openProfileForm()"><span v-html="ICONS.plus"></span>Add HR profile</button>
        </div>
        <div v-else class="em-grid">
          <button v-for="p in profiles" :key="p.id" type="button" class="em-profile" @click="openProfile(p)">
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
            <small class="em-by">Added by {{ p.added_by || "HR" }}</small>
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
              <p>{{ form.id ? `Signs in to the ERP as ${form.username.includes("@") ? "" : "@"}${form.username}` : "They sign in to the ERP with their email as the username, and the password you set here." }}</p>
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
            <p v-if="form.id && !form.profile_id && data?.hr_available" class="sp-hint">
              No HR profile yet, so HR can't see their contact details, emergency contact or documents.
              <button type="button" class="st-link" @click="profileFromAccount">Create HR profile</button>
            </p>

            <h4 class="st-section-title">Sign-in</h4>
            <div class="sp-grid-2">
              <label v-if="!form.id" class="sp-field">
                <span>Username</span>
                <input :value="form.email.trim()" class="sp-input" :class="{ invalid: errors.username }" placeholder="Same as their email" readonly tabindex="-1" />
                <small v-if="errors.username" class="sp-error">{{ errors.username }}</small>
                <small v-else class="st-sub">Their email is their username.</small>
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
      <Transition name="sp-fade"><div v-if="profile" class="sp-backdrop" @click="closeProfile"></div></Transition>
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
            <div class="em-head-actions">
              <button v-if="data?.hr_available" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="!full" @click="openProfileForm(full)"><span v-html="ICONS.edit"></span>Edit</button>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeProfile"><span v-html="ICONS.close"></span></button>
            </div>
          </div>
          <div class="sp-drawer-body">
            <p class="em-by">{{ full?.employee_id ? `${full.employee_id} · ` : "" }}Added by {{ full?.added_by || profile.added_by || "HR" }}</p>
            <h4 class="st-section-title first">Work</h4>
            <dl class="st-dl">
              <dt>Status</dt><dd>{{ profile.employment_status || "Active" }}</dd>
              <dt>Type</dt><dd>{{ profile.employment_type || "—" }}</dd>
              <dt>Hired</dt><dd>{{ profile.date_hired ? formatDate(profile.date_hired) : "—" }}</dd>
              <template v-if="full">
                <dt>Location</dt><dd>{{ full.work_location || "—" }}</dd>
                <dt>Reports to</dt><dd>{{ full.reporting_manager || "—" }}</dd>
              </template>
              <dt>Usual hours</dt><dd>{{ profile.usual_hours ? range12(profile.usual_hours) : "Not set" }}</dd>
              <dt>Rest days</dt><dd>{{ profile.rest_days.length ? profile.rest_days.join(", ") : "None" }}</dd>
              <dt>Pay</dt><dd>{{ profile.salary ? `${money(profile.salary)} ${PER[profile.salary_type] ?? ""}` : "Not set" }}</dd>
            </dl>

            <p v-if="fullError" class="sp-banner error em-note"><span v-html="ICONS.alert"></span>{{ fullError }}</p>
            <div v-else-if="!full" class="em-sk-block">
              <div v-for="n in 4" :key="n" class="bc-skeleton sp-sk-line" :class="n % 2 ? 'w70' : 'w50'"></div>
            </div>
            <template v-else>
              <h4 class="st-section-title">Personal</h4>
              <dl class="st-dl">
                <dt>Gender</dt><dd>{{ full.gender || "—" }}</dd>
                <dt>Birthday</dt><dd>{{ full.date_of_birth ? formatDate(full.date_of_birth.slice(0, 10)) : "—" }}</dd>
                <dt>Civil status</dt><dd>{{ full.civil_status || "—" }}</dd>
              </dl>
              <h4 class="st-section-title">Contact</h4>
              <dl class="st-dl">
                <dt>Work email</dt><dd>{{ full.work_email || "—" }}</dd>
                <dt>Personal email</dt><dd>{{ full.personal_email || "—" }}</dd>
                <dt>Mobile</dt><dd>{{ full.mobile_number || "—" }}</dd>
                <dt>Address</dt><dd>{{ full.address || "—" }}</dd>
              </dl>
              <h4 class="st-section-title">Emergency contact</h4>
              <dl class="st-dl">
                <dt>Name</dt><dd>{{ full.emergency_contact_name || "—" }}</dd>
                <dt>Relationship</dt><dd>{{ full.emergency_relationship || "—" }}</dd>
                <dt>Number</dt><dd>{{ full.emergency_contact_number || "—" }}</dd>
              </dl>
              <h4 class="st-section-title">Employment documents</h4>
              <EmployeeDocuments :profile-id="full.id" :can-edit="!!data?.hr_available" />
            </template>
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

    <!-- HR profile form -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="pform" class="sp-backdrop" @click="closeProfileForm"></div></Transition>
      <Transition name="sp-slide">
        <form v-if="pform" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="pform-title" novalidate @submit.prevent="saveProfile">
          <div class="sp-drawer-head">
            <div>
              <h3 id="pform-title">{{ pform.id ? `Edit ${pform.original}` : "Add an HR profile" }}</h3>
              <p>Your HR staff see this profile in the ERP too, and anything they change shows here.</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeProfileForm"><span v-html="ICONS.close"></span></button>
          </div>

          <div class="sp-drawer-body">
            <h4 class="st-section-title first">Personal</h4>
            <div class="sp-grid-2">
              <label v-for="f in PERSONAL" :key="f.key" class="sp-field" :class="{ 'sp-span-all': f.wide }">
                <span>{{ f.label }} <small v-if="f.optional">(optional)</small></span>
                <select v-if="f.options" v-model="pform[f.key]" class="sp-select" :class="{ invalid: perrors[f.key] }">
                  <option value="" disabled>Choose…</option>
                  <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
                </select>
                <input v-else v-model="pform[f.key]" :type="f.type || 'text'" class="sp-input" :class="{ invalid: perrors[f.key] }" :maxlength="f.max" :max="f.type === 'date' ? today : undefined" />
                <small v-if="perrors[f.key]" class="sp-error">{{ perrors[f.key] }}</small>
              </label>
            </div>

            <h4 class="st-section-title">Contact</h4>
            <div class="sp-grid-2">
              <label v-for="f in CONTACT" :key="f.key" class="sp-field" :class="{ 'sp-span-all': f.wide }">
                <span>{{ f.label }}</span>
                <input v-model="pform[f.key]" :type="f.type || 'text'" class="sp-input" :class="{ invalid: perrors[f.key] }" :maxlength="f.max" :inputmode="f.type === 'tel' ? 'tel' : undefined" />
                <small v-if="perrors[f.key]" class="sp-error">{{ perrors[f.key] }}</small>
              </label>
            </div>

            <h4 class="st-section-title">Emergency contact</h4>
            <div class="sp-grid-2">
              <label v-for="f in EMERGENCY" :key="f.key" class="sp-field" :class="{ 'sp-span-all': f.wide }">
                <span>{{ f.label }}</span>
                <input v-model="pform[f.key]" :type="f.type || 'text'" class="sp-input" :class="{ invalid: perrors[f.key] }" :maxlength="f.max" :placeholder="f.placeholder" />
                <small v-if="perrors[f.key]" class="sp-error">{{ perrors[f.key] }}</small>
              </label>
            </div>

            <h4 class="st-section-title">Job</h4>
            <div class="sp-grid-2">
              <label v-for="f in JOB" :key="f.key" class="sp-field">
                <span>{{ f.label }}</span>
                <select v-if="f.options" v-model="pform[f.key]" class="sp-select" :class="{ invalid: perrors[f.key] }">
                  <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
                </select>
                <input v-else v-model="pform[f.key]" :type="f.type || 'text'" class="sp-input" :class="{ invalid: perrors[f.key] }" :maxlength="f.max" :placeholder="f.placeholder" />
                <small v-if="perrors[f.key]" class="sp-error">{{ perrors[f.key] }}</small>
              </label>
            </div>

            <h4 class="st-section-title">Usual hours</h4>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span>Schedule</span>
                <select v-model="pform.work_schedule" class="sp-select">
                  <option value="Fixed">Fixed</option>
                  <option value="Shifting">Shifting</option>
                </select>
              </label>
              <div class="sp-field">
                <span>Hours</span>
                <div class="em-hours">
                  <input v-model="pform.shift_start" type="time" class="sp-input" :class="{ invalid: perrors.shift_start }" aria-label="Starts" />
                  <span>to</span>
                  <input v-model="pform.shift_end" type="time" class="sp-input" :class="{ invalid: perrors.shift_end }" aria-label="Ends" />
                </div>
                <small v-if="perrors.shift_start || perrors.shift_end" class="sp-error">{{ perrors.shift_start || perrors.shift_end }}</small>
              </div>
              <div class="sp-field sp-span-all">
                <span>Rest days</span>
                <div class="em-days" role="group" aria-label="Rest days">
                  <label v-for="d in DAYS" :key="d" class="em-day" :class="{ on: pform.rest_days.includes(d) }">
                    <input v-model="pform.rest_days" type="checkbox" :value="d" />{{ d.slice(0, 3) }}
                  </label>
                </div>
                <small v-if="perrors.rest_days" class="sp-error">{{ perrors.rest_days }}</small>
              </div>
            </div>

            <h4 class="st-section-title">Pay <small>(optional)</small></h4>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span>Paid</span>
                <select v-model="pform.salary_type" class="sp-select">
                  <option value="">Not set</option>
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                </select>
              </label>
              <label class="sp-field">
                <span>Basic salary</span>
                <input v-model="pform.basic_salary" type="number" min="0" step="0.01" class="sp-input" :class="{ invalid: perrors.basic_salary }" :disabled="!pform.salary_type" />
                <small v-if="perrors.basic_salary" class="sp-error">{{ perrors.basic_salary }}</small>
              </label>
              <label class="sp-field">
                <span>Hours a day</span>
                <input v-model="pform.standard_work_hours_per_day" type="number" min="1" max="24" step="0.5" class="sp-input" :class="{ invalid: perrors.standard_work_hours_per_day }" />
              </label>
              <label v-if="pform.salary_type === 'weekly'" class="sp-field">
                <span>Days a week</span>
                <input v-model="pform.working_days_per_week" type="number" min="1" max="7" class="sp-input" :class="{ invalid: perrors.working_days_per_week }" />
              </label>
              <label v-else class="sp-field">
                <span>Days a month</span>
                <input v-model="pform.working_days_per_month" type="number" min="1" max="31" class="sp-input" :class="{ invalid: perrors.working_days_per_month }" />
              </label>
              <label class="sp-field">
                <span>Paid by</span>
                <select v-model="pform.payment_method" class="sp-select">
                  <option value="">Not set</option>
                  <option value="Cash">Cash</option>
                  <option value="Bank">Bank</option>
                </select>
              </label>
              <template v-if="pform.payment_method === 'Bank'">
                <label class="sp-field">
                  <span>Bank</span>
                  <input v-model="pform.bank_name" class="sp-input" maxlength="150" />
                </label>
                <label class="sp-field">
                  <span>Account number</span>
                  <input v-model="pform.account_number" class="sp-input" maxlength="50" autocomplete="off" />
                </label>
              </template>
            </div>
            <p v-if="!pform.id" class="sp-hint">You can add documents (contracts, IDs) after you save.</p>
            <p v-if="pformError" class="sp-error">{{ pformError }}</p>
          </div>

          <div class="sp-drawer-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="closeProfileForm">Cancel</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="psaving">{{ psaving ? "Saving…" : pform.id ? "Save changes" : "Add profile" }}</button>
          </div>
        </form>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute } from "vue-router";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import employeeInfoService from "../../../services/employeeInfoService";
import EmployeeDocuments from "../../../components/hr/EmployeeDocuments.vue";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { formatDate, money, ymd } from "../../../components/vendor/finance/financeUi";
import { STATUS_LABEL, STATUS_TONE, personColor, range12, relativeTime, time12 } from "../../../components/vendor/staff/staffUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";
import { CONTACT_FIELDS as CONTACT, DAYS, EMERGENCY_FIELDS as EMERGENCY, JOB_FIELDS as JOB, PER, PERSONAL_FIELDS as PERSONAL, PROFILE_KEYS, REQUIRED_FIELDS as REQUIRED, day10, hm5 } from "../../../components/hr/hrUi";

const today = ymd(new Date());

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
const full = ref(null);
const fullError = ref("");
const pform = ref(null);
const perrors = reactive({});
const pformError = ref("");
const psaving = ref(false);

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
    ? { id: a.id, original: a.name, username: a.username, profile_id: a.profile_id, name: a.name, title: a.title ?? "", email: a.email ?? "", phone: a.phone ?? "", address: a.address ?? "", joining_date: a.joining_date ?? ymd(new Date()), status: a.status, staff_role_id: a.role?.id ?? null, password: "" }
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
      : await api.post("vendor/staff/employees", { ...body, username: body.email });
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

// ── HR profiles (the same records HR edits in the ERP) ──

async function openProfile(p) {
  profile.value = p;
  full.value = null;
  fullError.value = "";
  try {
    const res = await employeeInfoService.getById(p.id);
    if (profile.value?.id === p.id) full.value = res.data;
  } catch (e) {
    if (profile.value?.id === p.id) fullError.value = e?.message || "Couldn't load the rest of this profile.";
  }
}
function closeProfile() {
  profile.value = null;
  full.value = null;
}


function openProfileForm(source = null, prefill = {}) {
  Object.keys(perrors).forEach((k) => delete perrors[k]);
  pformError.value = "";
  const blank = Object.fromEntries(PROFILE_KEYS.map((k) => [k, ""]));
  const f = { ...blank, employment_status: "Probationary", employment_type: "Full-time", work_schedule: "Fixed", shift_start: "08:00", shift_end: "17:00", rest_days: ["Sunday"], standard_work_hours_per_day: 8, working_days_per_week: 6, working_days_per_month: 26, date_hired: today, ...prefill };
  if (source) {
    for (const k of PROFILE_KEYS) f[k] = source[k] ?? "";
    f.id = source.id;
    f.original = source.full_name;
    f.date_of_birth = day10(source.date_of_birth);
    f.date_hired = day10(source.date_hired);
    f.shift_start = hm5(source.shift_start);
    f.shift_end = hm5(source.shift_end);
    f.rest_days = String(source.rest_days ?? "").split(",").map((d) => d.trim()).filter((d) => DAYS.includes(d));
  }
  pform.value = f;
}
function closeProfileForm() {
  if (!psaving.value) pform.value = null;
}
function profileFromAccount() {
  const f = form.value;
  const parts = f.name.trim().split(/\s+/);
  const last = parts.length > 1 ? parts.pop() : "";
  form.value = null;
  openProfileForm(null, { first_name: parts.join(" "), last_name: last, work_email: f.email, mobile_number: f.phone, address: f.address, position: f.title, date_hired: f.joining_date });
}

function validateProfile() {
  Object.keys(perrors).forEach((k) => delete perrors[k]);
  const f = pform.value;
  for (const k of REQUIRED) if (!String(f[k] ?? "").trim()) perrors[k] = "Required.";
  for (const k of ["work_email", "personal_email"]) if (f[k] && !/^\S+@\S+\.\S+$/.test(f[k].trim())) perrors[k] = "Enter a valid email.";
  if (f.date_of_birth && f.date_of_birth >= today) perrors.date_of_birth = "Pick a day in the past.";
  if (f.shift_start && f.shift_end && f.shift_end <= f.shift_start) perrors.shift_end = "End after it starts.";
  if (f.salary_type && !f.basic_salary) perrors.basic_salary = "Enter the salary.";
  return !Object.keys(perrors).length;
}

async function saveProfile() {
  if (psaving.value) return;
  if (!validateProfile()) {
    pformError.value = "Fill in the highlighted fields.";
    return;
  }
  psaving.value = true;
  pformError.value = "";
  const f = pform.value;
  const body = {};
  for (const k of PROFILE_KEYS) {
    const v = typeof f[k] === "string" ? f[k].trim() : f[k];
    body[k] = v === "" ? null : v;
  }
  body.rest_days = DAYS.filter((d) => f.rest_days.includes(d)).join(", ") || "None";
  if (!body.salary_type) body.basic_salary = null;
  if (body.payment_method !== "Bank") {
    body.bank_name = null;
    body.account_number = null;
  }
  try {
    const res = f.id ? await employeeInfoService.update(f.id, body) : await employeeInfoService.create(body);
    toast.success(f.id ? "Profile saved." : "Profile added. HR can see it in the ERP.");
    pform.value = null;
    await load();
    const saved = data.value?.profiles.find((p) => p.id === (res.data?.id ?? f.id));
    if (saved) openProfile(saved);
  } catch (e) {
    const fieldErrors = e?.errors ?? {};
    for (const [key, list] of Object.entries(fieldErrors)) perrors[key] = [].concat(list)[0];
    pformError.value = Object.keys(fieldErrors).length ? "Fix the highlighted fields." : e?.message || "Couldn't save.";
  } finally {
    psaving.value = false;
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
  border: 1px solid var(--v-border);
  border-radius: 16px;
  background: var(--v-surface);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
button.em-profile:hover {
  border-color: var(--bc-accent-border);
  box-shadow: 0 4px 14px color-mix(in srgb, var(--bc-accent) 12%, transparent);
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
  border-top: 1px solid var(--v-border);
}
.em-facts div {
  min-width: 0;
}
.em-facts dt {
  font-size: 11px;
  color: var(--v-faint);
}
.em-facts dd {
  margin: 2px 0 0;
  font-size: 12.5px;
  color: var(--v-text);
  overflow-wrap: anywhere;
}
.em-facts dd.muted {
  color: var(--v-faint);
}
.em-in {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--v-green-text) !important;
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
  border: 1px solid var(--v-border-strong);
  border-radius: 12px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.em-role.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  box-shadow: 0 0 0 1px var(--bc-accent);
}
.em-role input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.em-role:has(input:focus-visible) {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 30%, transparent);
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
  color: var(--v-muted);
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
.em-by {
  margin: 0;
  font-size: 11.5px;
  color: var(--v-faint);
}
.sp-drawer-body > .em-by {
  margin-bottom: 14px;
}
.em-head-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: none;
}
.em-sk-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 22px;
}
.em-hours {
  display: flex;
  align-items: center;
  gap: 8px;
}
.em-hours .sp-input {
  flex: 1;
  min-width: 0;
}
.em-hours > span {
  font-size: 12.5px;
  color: var(--v-muted);
}
.em-days {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.em-day {
  position: relative;
  padding: 7px 12px;
  border: 1px solid var(--v-border-strong);
  border-radius: 999px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--v-muted);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.em-day.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  color: var(--v-text);
}
.em-day input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.em-day:has(input:focus-visible) {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 30%, transparent);
}
.st-section-title small {
  font-weight: 400;
  text-transform: none;
  letter-spacing: 0;
}
.em-access {
  margin: 0;
  font-size: 13px;
  line-height: 1.6;
  color: var(--v-text);
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
