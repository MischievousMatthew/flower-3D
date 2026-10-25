<template>
  <HrPage title="Employee profiles" subtitle="Contact details, job, emergency contacts, pay and documents. The shop owner sees and edits the same profiles in the vendor portal." :tabs="EMPLOYEE_TABS">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-primary" :disabled="!canCreate" :title="canCreate ? '' : NO_PERMISSION.create" @click="openForm()">
        <span v-html="ICONS.plus"></span>Add profile
      </button>
    </template>

    <p v-if="readOnly" class="sp-banner warn pf-note"><span v-html="ICONS.lock"></span>You can look at profiles, but your role can't add, edit or remove them.</p>

    <!-- Summary -->
    <section class="sp-stats" aria-label="Summary">
      <template v-if="!stats">
        <div v-for="n in 4" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <button v-for="s in statTiles" :key="s.label" type="button" class="sp-stat" :class="{ on: filters.status === s.status }" @click="setStatus(s.status)">
          <span class="sp-stat-icon" :class="`tone-${s.tone}`" v-html="ICONS[s.icon]"></span>
          <div><strong>{{ s.count }}</strong><span>{{ s.label }}</span></div>
        </button>
      </template>
    </section>

    <section class="sp-card">
      <div class="sp-toolbar pf-toolbar">
        <label class="sp-search">
          <span v-html="ICONS.search"></span>
          <input v-model="search" class="sp-input" type="search" placeholder="Name, ID, email or mobile" aria-label="Search profiles" />
        </label>
        <div class="pf-filters">
          <select v-model="filters.department" class="sp-select" aria-label="Department">
            <option value="">All departments</option>
            <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
          </select>
          <select v-model="filters.location" class="sp-select" aria-label="Work location">
            <option value="">All locations</option>
            <option v-for="l in locations" :key="l" :value="l">{{ l }}</option>
          </select>
          <select v-model="filters.status" class="sp-select" aria-label="Status">
            <option value="">Every status</option>
            <option v-for="s in EMPLOYMENT_STATUSES" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
      </div>

      <p v-if="loadError" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ loadError }}<button type="button" @click="load()">Try again</button></p>

      <ul v-else-if="employees === null" class="pf-sk">
        <li v-for="n in 6" :key="n"><div class="bc-skeleton st-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
      </ul>

      <div v-else-if="!employees.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.users"></span>
        <h3>{{ filtered ? "Nobody matches" : "No profiles yet" }}</h3>
        <p>{{ filtered ? "Try another search or filter." : "Add the people who work at the shop. The shop owner sees them in the vendor portal too." }}</p>
        <button v-if="!filtered && canCreate" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="openForm()"><span v-html="ICONS.plus"></span>Add profile</button>
      </div>

      <template v-else>
        <div class="st-scroll">
          <table class="st-table cards">
            <colgroup>
              <col style="width: 30%" />
              <col style="width: 24%" />
              <col style="width: 15%" />
              <col style="width: 13%" />
              <col style="width: 12%" />
              <col style="width: 6%" />
            </colgroup>
            <thead>
              <tr><th>Person</th><th>Job</th><th>Location</th><th>Status</th><th>Hired</th><th><span class="pf-sr">Actions</span></th></tr>
            </thead>
            <tbody>
              <tr v-for="e in employees" :key="e.id" class="clickable" @click="openDetails(e)">
                <td>
                  <div class="st-person">
                    <span class="st-avatar" :style="{ background: personColor(e.full_name) }">
                      <img v-if="e.avatar_url" :src="e.avatar_url" alt="" />
                      <template v-else>{{ initialsOf(e.full_name) }}</template>
                    </span>
                    <div>
                      <strong>{{ e.full_name }}</strong>
                      <small>{{ e.employee_id }} · added by {{ e.added_by || "HR" }}</small>
                    </div>
                  </div>
                </td>
                <td data-label="Job">
                  <span class="pf-two"><b>{{ e.position || "—" }}</b><small>{{ e.department }}</small></span>
                </td>
                <td data-label="Location">{{ e.work_location || "—" }}</td>
                <td data-label="Status"><span class="sp-chip" :class="statusTone(e.employment_status)">{{ e.employment_status }}</span></td>
                <td data-label="Hired">{{ e.date_hired ? formatDate(day10(e.date_hired)) : "—" }}</td>
                <td class="num" @click.stop>
                  <div class="pf-menu-wrap">
                    <button type="button" class="sp-icon-btn" :aria-label="`More for ${e.full_name}`" :aria-expanded="menuId === e.id" @click="menuId = menuId === e.id ? null : e.id"><span v-html="ICONS.more"></span></button>
                    <div v-if="menuId === e.id" class="pf-menu" role="menu">
                      <button type="button" role="menuitem" @click="openDetails(e)"><span v-html="ICONS.eye"></span>View</button>
                      <button type="button" role="menuitem" :disabled="!canEdit" :title="canEdit ? '' : NO_PERMISSION.edit" @click="openForm(e)"><span v-html="ICONS.edit"></span>Edit</button>
                      <button v-for="s in quickStatuses(e)" :key="s" type="button" role="menuitem" :disabled="!canEdit" @click="setEmploymentStatus(e, s)"><span v-html="ICONS.check"></span>Mark as {{ s }}</button>
                      <button type="button" role="menuitem" class="danger" :disabled="!canDelete" :title="canDelete ? '' : NO_PERMISSION.delete" @click="askDelete(e)"><span v-html="ICONS.trash"></span>Delete</button>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 10 people, then 10 more, then 20 at a time -->
        <ShowMore v-if="employees && employees.length" tone="vendor" :has-more="pager.hasMore.value" :loading="pager.loadingMore.value" :shown="employees.length" :total="pager.total.value" @more="pager.showMore()" />
      </template>
    </section>

    <!-- Profile details -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="selected" class="sp-backdrop" @click="selected = null"></div></Transition>
      <Transition name="sp-slide">
        <aside v-if="selected" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="pf-title">
          <div class="sp-drawer-head">
            <div class="st-person">
              <EmployeePhoto
                :profile-id="selected.id"
                :src="selected.avatar_url || ''"
                :name="selected.full_name"
                :color="personColor(selected.full_name)"
                :size="56"
                :can-edit="canEdit"
                @update:src="photoChanged(selected.id, $event)"
              />
              <div>
                <h3 id="pf-title">{{ selected.full_name }}</h3>
                <p>{{ [selected.position, selected.department].filter(Boolean).join(" · ") }}</p>
                <div class="pf-tags">
                  <span class="sp-chip" :class="statusTone(selected.employment_status)">{{ selected.employment_status }}</span>
                  <span class="sp-chip slate">{{ selected.employee_id }}</span>
                </div>
              </div>
            </div>
            <div class="pf-head-actions">
              <button v-if="canEdit" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="openForm(selected)"><span v-html="ICONS.edit"></span>Edit</button>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="selected = null"><span v-html="ICONS.close"></span></button>
            </div>
          </div>

          <div class="sp-drawer-body">
            <div class="pf-quick">
              <a v-if="selected.work_email" class="sp-btn sp-btn-ghost sp-btn-sm" :href="`mailto:${selected.work_email}`"><span v-html="ICONS.send"></span>Email</a>
              <a v-if="selected.mobile_number" class="sp-btn sp-btn-ghost sp-btn-sm" :href="`tel:${selected.mobile_number}`"><span v-html="ICONS.phone"></span>Call</a>
              <span class="pf-by">Added by {{ selected.added_by || "HR" }}</span>
            </div>

            <div class="sp-seg pf-seg" role="tablist" aria-label="Profile sections">
              <button v-for="t in DETAIL_TABS" :key="t.key" type="button" role="tab" :class="{ on: detailTab === t.key }" :aria-selected="detailTab === t.key" @click="detailTab = t.key">{{ t.label }}</button>
            </div>

            <template v-if="detailTab === 'profile'">
              <h4 class="st-section-title">Personal</h4>
              <dl class="st-dl">
                <dt>Full name</dt><dd>{{ selected.full_name }}</dd>
                <dt>Gender</dt><dd>{{ selected.gender || "—" }}</dd>
                <dt>Birthday</dt><dd>{{ selected.date_of_birth ? formatDate(day10(selected.date_of_birth)) : "—" }}</dd>
                <dt>Civil status</dt><dd>{{ selected.civil_status || "—" }}</dd>
              </dl>
              <h4 class="st-section-title">Contact</h4>
              <dl class="st-dl">
                <dt>Work email</dt><dd>{{ selected.work_email || "—" }}</dd>
                <dt>Personal email</dt><dd>{{ selected.personal_email || "—" }}</dd>
                <dt>Mobile</dt><dd>{{ selected.mobile_number || "—" }}</dd>
                <dt>Address</dt><dd>{{ selected.address || "—" }}</dd>
              </dl>
              <h4 class="st-section-title">Emergency contact</h4>
              <dl class="st-dl">
                <dt>Name</dt><dd>{{ selected.emergency_contact_name || "—" }}</dd>
                <dt>Relationship</dt><dd>{{ selected.emergency_relationship || "—" }}</dd>
                <dt>Number</dt><dd>{{ selected.emergency_contact_number || "—" }}</dd>
              </dl>
            </template>

            <template v-else-if="detailTab === 'job'">
              <h4 class="st-section-title">Employment</h4>
              <dl class="st-dl">
                <dt>Employee ID</dt><dd>{{ selected.employee_id }}</dd>
                <dt>Status</dt><dd>{{ selected.employment_status }}</dd>
                <dt>Type</dt><dd>{{ selected.employment_type || "—" }}</dd>
                <dt>Hired</dt><dd>{{ selected.date_hired ? formatDate(day10(selected.date_hired)) : "—" }}</dd>
                <dt>Location</dt><dd>{{ selected.work_location || "—" }}</dd>
                <dt>Reports to</dt><dd>{{ selected.reporting_manager || "—" }}</dd>
              </dl>
              <h4 class="st-section-title">Usual hours</h4>
              <dl class="st-dl">
                <dt>Schedule</dt><dd>{{ selected.work_schedule || "—" }}</dd>
                <dt>Hours</dt><dd>{{ selected.shift_start ? `${time12(hm5(selected.shift_start))} – ${time12(hm5(selected.shift_end))}` : "—" }}</dd>
                <dt>Rest days</dt><dd>{{ selected.rest_days || "None" }}</dd>
              </dl>
              <template v-if="canSeePay">
              <h4 class="st-section-title">Pay</h4>
              <dl class="st-dl">
                <dt>Basic salary</dt><dd>{{ selected.basic_salary ? `${money(selected.basic_salary)} ${PER[selected.salary_type] ?? ""}` : "Not set" }}</dd>
                <dt>Hourly rate</dt><dd>{{ hourlyRate(selected) ? `${money(hourlyRate(selected))} an hour` : "—" }}</dd>
                <dt>Paid by</dt><dd>{{ selected.payment_method || "—" }}<template v-if="selected.payment_method === 'Bank' && selected.bank_name"> · {{ selected.bank_name }}</template></dd>
                <dt>Account number</dt><dd>{{ selected.account_number ? masked(selected.account_number) : "—" }}</dd>
                <dt>Tax status</dt><dd>{{ selected.tax_status || "—" }}</dd>
                <dt>Allowance</dt><dd>{{ selected.allowance ? money(selected.allowance) : "—" }}</dd>
              </dl>
              </template>
            </template>

            <template v-else-if="detailTab === 'documents'">
              <h4 class="st-section-title">Employment documents</h4>
              <EmployeeDocuments :profile-id="selected.id" :can-edit="canEdit" />
            </template>

            <div v-else class="pf-qr">
              <EmployeeQRCode :employee_id="selected.id" :owner_id="selected.owner_id" :employee_name="selected.full_name" />
            </div>
          </div>
        </aside>
      </Transition>
    </Teleport>

    <!-- Add / edit -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="form" class="sp-backdrop" @click="closeForm"></div></Transition>
      <Transition name="sp-slide">
        <form v-if="form" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="pf-form-title" novalidate @submit.prevent="save">
          <div class="sp-drawer-head">
            <div>
              <h3 id="pf-form-title">{{ form.id ? `Edit ${form.original}` : "Add a profile" }}</h3>
              <p>The shop owner sees this profile in the vendor portal too.</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeForm"><span v-html="ICONS.close"></span></button>
          </div>

          <fieldset class="sp-drawer-body pf-fieldset" :disabled="form.id ? !canEdit : !canCreate">
            <h4 class="st-section-title first">Photo</h4>
            <div class="pf-photo">
              <span class="st-avatar pf-avatar-lg" :style="{ background: personColor(`${form.first_name} ${form.last_name}`) }">
                <img v-if="form.avatarPreview" :src="form.avatarPreview" alt="" />
                <template v-else>{{ initialsOf(`${form.first_name} ${form.last_name}`) || "?" }}</template>
              </span>
              <label class="sp-btn sp-btn-ghost sp-btn-sm pf-upload">
                <span v-html="ICONS.upload"></span>{{ form.avatarPreview ? "Change photo" : "Upload photo" }}
                <input type="file" accept="image/*" @change="pickPhoto" />
              </label>
              <small class="sp-hint">Up to 5 MB.</small>
            </div>

            <h4 class="st-section-title">Personal</h4>
            <div class="sp-grid-2">
              <label v-for="f in PERSONAL_FIELDS" :key="f.key" class="sp-field">
                <span class="sp-label">{{ f.label }} <small v-if="f.optional">(optional)</small></span>
                <select v-if="f.options" v-model="form[f.key]" class="sp-select" :class="{ invalid: errors[f.key] }">
                  <option value="" disabled>Choose…</option>
                  <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
                </select>
                <input v-else v-model="form[f.key]" :type="f.type || 'text'" class="sp-input" :class="{ invalid: errors[f.key] }" :maxlength="f.max" :max="f.type === 'date' ? today : undefined" />
                <small v-if="errors[f.key]" class="sp-error">{{ errors[f.key] }}</small>
              </label>
            </div>

            <h4 class="st-section-title">Contact</h4>
            <div class="sp-grid-2">
              <label v-for="f in CONTACT_FIELDS" :key="f.key" class="sp-field" :class="{ 'sp-span-all': f.wide }">
                <span class="sp-label">{{ f.label }}</span>
                <input v-model="form[f.key]" :type="f.type || 'text'" class="sp-input" :class="{ invalid: errors[f.key] }" :maxlength="f.max" :inputmode="f.type === 'tel' ? 'tel' : undefined" />
                <small v-if="errors[f.key]" class="sp-error">{{ errors[f.key] }}</small>
              </label>
            </div>

            <h4 class="st-section-title">Emergency contact</h4>
            <div class="sp-grid-2">
              <label v-for="f in EMERGENCY_FIELDS" :key="f.key" class="sp-field">
                <span class="sp-label">{{ f.label }}</span>
                <input v-model="form[f.key]" :type="f.type || 'text'" class="sp-input" :class="{ invalid: errors[f.key] }" :maxlength="f.max" :placeholder="f.placeholder" />
                <small v-if="errors[f.key]" class="sp-error">{{ errors[f.key] }}</small>
              </label>
            </div>

            <h4 class="st-section-title">Job</h4>
            <div class="sp-grid-2">
              <label v-for="f in JOB_FIELDS" :key="f.key" class="sp-field">
                <span class="sp-label">{{ f.label }}</span>
                <select v-if="f.options" v-model="form[f.key]" class="sp-select" :class="{ invalid: errors[f.key] }">
                  <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
                </select>
                <input v-else v-model="form[f.key]" :type="f.type || 'text'" class="sp-input" :class="{ invalid: errors[f.key] }" :maxlength="f.max" :placeholder="f.placeholder" />
                <small v-if="errors[f.key]" class="sp-error">{{ errors[f.key] }}</small>
              </label>
            </div>

            <h4 class="st-section-title">Usual hours</h4>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span class="sp-label">Schedule</span>
                <select v-model="form.work_schedule" class="sp-select">
                  <option value="Fixed">Fixed</option>
                  <option value="Shifting">Shifting</option>
                </select>
              </label>
              <div class="sp-field">
                <span class="sp-label">Hours</span>
                <div class="pf-hours">
                  <input v-model="form.shift_start" type="time" class="sp-input" :class="{ invalid: errors.shift_start }" aria-label="Starts" />
                  <span>to</span>
                  <input v-model="form.shift_end" type="time" class="sp-input" :class="{ invalid: errors.shift_end }" aria-label="Ends" />
                </div>
                <small v-if="errors.shift_start || errors.shift_end" class="sp-error">{{ errors.shift_start || errors.shift_end }}</small>
              </div>
              <div class="sp-field sp-span-all">
                <span class="sp-label">Rest days</span>
                <div class="pf-days" role="group" aria-label="Rest days">
                  <label v-for="d in DAYS" :key="d" class="pf-day" :class="{ on: form.rest_days.includes(d) }">
                    <input v-model="form.rest_days" type="checkbox" :value="d" />{{ d.slice(0, 3) }}
                  </label>
                </div>
              </div>
            </div>

            <template v-if="canEditPay">
            <h4 class="st-section-title">Pay</h4>
            <p class="sp-hint pf-hint">Payroll uses these to work out pay. Leave them blank if you're not running payroll for this person yet.</p>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span class="sp-label">Paid</span>
                <select v-model="form.salary_type" class="sp-select" :class="{ invalid: errors.salary_type }">
                  <option value="">Not set</option>
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                </select>
              </label>
              <label class="sp-field">
                <span class="sp-label">Basic salary</span>
                <input v-model="form.basic_salary" type="number" min="0" step="0.01" class="sp-input" :class="{ invalid: errors.basic_salary }" :disabled="!form.salary_type" />
                <small v-if="errors.basic_salary" class="sp-error">{{ errors.basic_salary }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Hours a day</span>
                <input v-model="form.standard_work_hours_per_day" type="number" min="1" max="24" step="0.5" class="sp-input" :class="{ invalid: errors.standard_work_hours_per_day }" />
                <small v-if="errors.standard_work_hours_per_day" class="sp-error">{{ errors.standard_work_hours_per_day }}</small>
              </label>
              <label v-if="form.salary_type === 'weekly'" class="sp-field">
                <span class="sp-label">Days a week</span>
                <input v-model="form.working_days_per_week" type="number" min="1" max="7" class="sp-input" :class="{ invalid: errors.working_days_per_week }" />
                <small v-if="errors.working_days_per_week" class="sp-error">{{ errors.working_days_per_week }}</small>
              </label>
              <label v-else class="sp-field">
                <span class="sp-label">Days a month</span>
                <input v-model="form.working_days_per_month" type="number" min="1" max="31" class="sp-input" :class="{ invalid: errors.working_days_per_month }" />
                <small v-if="errors.working_days_per_month" class="sp-error">{{ errors.working_days_per_month }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Paid by</span>
                <select v-model="form.payment_method" class="sp-select">
                  <option value="">Not set</option>
                  <option value="Cash">Cash</option>
                  <option value="Bank">Bank</option>
                </select>
              </label>
              <template v-if="form.payment_method === 'Bank'">
                <label class="sp-field">
                  <span class="sp-label">Bank</span>
                  <input v-model="form.bank_name" class="sp-input" maxlength="150" />
                </label>
                <label class="sp-field">
                  <span class="sp-label">Account number</span>
                  <input v-model="form.account_number" class="sp-input" maxlength="50" autocomplete="off" />
                </label>
              </template>
              <label class="sp-field">
                <span class="sp-label">Tax status <small>(optional)</small></span>
                <input v-model="form.tax_status" class="sp-input" maxlength="20" placeholder="e.g. S, ME1" />
              </label>
              <label class="sp-field">
                <span class="sp-label">Allowance <small>(optional)</small></span>
                <input v-model="form.allowance" type="number" min="0" step="0.01" class="sp-input" />
              </label>
            </div>
            <div v-if="hourlyRate(form)" class="pf-rate">
              <span v-html="ICONS.wallet"></span>
              <div><strong>{{ money(hourlyRate(form)) }} an hour</strong><small>Worked out from the salary, hours a day and working days.</small></div>
            </div>
            </template>

            <p v-if="!form.id" class="sp-hint pf-hint">You can add documents (contracts, IDs) after you save.</p>
            <p v-if="formError" class="sp-error">{{ formError }}</p>
          </fieldset>

          <div class="sp-drawer-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="closeForm">Cancel</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="saving || (form.id ? !canEdit : !canCreate)">{{ saving ? "Saving…" : form.id ? "Save changes" : "Add profile" }}</button>
          </div>
        </form>
      </Transition>
    </Teleport>

    <!-- Delete -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="deleting" class="sp-backdrop" @click="deleting = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="deleting" class="sp-modal-wrap">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="pf-del-title">
            <div class="sp-modal-head">
              <div>
                <h3 id="pf-del-title">Delete {{ deleting.full_name }}?</h3>
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
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { toast } from "@/utils/snackbar";
import HrPage from "../../../../components/hr/HrPage.vue";
import EmployeeDocuments from "../../../../components/hr/EmployeeDocuments.vue";
import EmployeeQRCode from "../../../../layouts/components/EmployeeQRCode.vue";
import employeeInfoService from "../../../../services/employeeInfoService";
import ShowMore from "../../../../components/common/ShowMore.vue";
import { useShowMorePaged } from "../../../../composables/useShowMore";
import { useAssignment } from "../../../../composables/useAssignment";
import { SHOP_ICONS as ICONS } from "../../../../components/vendor/shop/shopIcons";
import { formatDate, money, ymd } from "../../../../components/vendor/finance/financeUi";
import { personColor, time12 } from "../../../../components/vendor/staff/staffUi";
import EmployeePhoto from "../../../../components/hr/EmployeePhoto.vue";
import { buildMultipartFormData, getSelectedFile, readImagePreview, validateImageFile } from "../../../../utils/imageUpload";
import {
  CONTACT_FIELDS, DAYS, EMERGENCY_FIELDS, EMPLOYEE_TABS, EMPLOYMENT_STATUSES, JOB_FIELDS, PER, PERSONAL_FIELDS, PROFILE_KEYS,
  REQUIRED_FIELDS, day10, hm5, hourlyRate, initialsOf, restDaysList, statusTone,
} from "../../../../components/hr/hrUi";

const NO_PERMISSION = {
  create: "Your role can't add profiles.",
  edit: "Your role can't edit profiles.",
  delete: "Your role can't delete profiles.",
};
const DETAIL_TABS = [
  { key: "profile", label: "Profile" },
  { key: "job", label: "Job & pay" },
  { key: "documents", label: "Documents" },
  { key: "qr", label: "QR code" },
];
const today = ymd(new Date());

const route = useRoute();
const { can } = useAssignment();
const canCreate = computed(() => can("employees", "create"));
const canEdit = computed(() => can("employees", "edit"));
const canDelete = computed(() => can("employees", "delete"));
const readOnly = computed(() => can("employees", "view") && !canCreate.value && !canEdit.value && !canDelete.value);
// Salary and bank details are only for payroll and finance staff (the server hides them too)
const canSeePay = computed(() => can("payroll", "view") || can("payroll_requests", "view"));
const canEditPay = computed(() => can("payroll", "create"));

const employees = ref(null);
const loadError = ref("");
const page = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const stats = ref(null);
const search = ref("");
const filters = reactive({ status: "", department: "", location: "" });
const menuId = ref(null);
const selected = ref(null);

/** Keeps the list and the open profile in step after a photo change */
function photoChanged(id, url) {
  const row = employees.value?.find((e) => e.id === id);
  if (row) row.avatar_url = url || null;
  if (selected.value?.id === id) selected.value = { ...selected.value, avatar_url: url || null };
}
const detailTab = ref("profile");
const form = ref(null);
const errors = reactive({});
const formError = ref("");
const saving = ref(false);
const deleting = ref(null);
const busy = ref(false);

const filtered = computed(() => !!(search.value.trim() || filters.status || filters.department || filters.location));
const departments = computed(() => (stats.value?.by_department ?? []).map((d) => d.department).filter(Boolean));
const locations = computed(() => (stats.value?.by_location ?? []).map((l) => l.work_location).filter(Boolean));
const statTiles = computed(() => {
  const s = stats.value?.by_status ?? {};
  return [
    { label: "Everyone", status: "", count: stats.value?.total_employees ?? 0, tone: "blue", icon: "users" },
    { label: "Regular", status: "Regular", count: s.regular ?? 0, tone: "green", icon: "check" },
    { label: "Probationary", status: "Probationary", count: s.probationary ?? 0, tone: "amber", icon: "clock" },
    { label: "Contractual", status: "Contractual", count: s.contractual ?? 0, tone: "violet", icon: "note" },
  ];
});

const masked = (n) => (String(n).length > 4 ? `•••• ${String(n).slice(-4)}` : n);
const quickStatuses = (e) => ["Regular", "Probationary"].filter((s) => s !== e.employment_status);

/** One page of profiles from the server, for the "Show more" list */
async function fetchPage(p) {
  const res = await employeeInfoService.getAll({
    page: p,
    per_page: 15,
    search: search.value.trim() || undefined,
    status: filters.status || undefined,
    department: filters.department || undefined,
    location: filters.location || undefined,
  });
  page.value = res.pagination ?? page.value;
  return { items: res.data ?? [], lastPage: page.value.last_page ?? 1, total: page.value.total };
}
const pager = useShowMorePaged(fetchPage);
watch(() => pager.visible.value, (rows) => {
  if (employees.value !== null) employees.value = rows;
});
async function load() {
  loadError.value = "";
  employees.value = null;
  try {
    await pager.load();
    if (pager.failure.value) throw pager.failure.value;
    employees.value = pager.visible.value;
  } catch (e) {
    employees.value = [];
    loadError.value = e?.message || "Couldn't load the profiles.";
  }
}

async function loadStats() {
  try {
    const res = await employeeInfoService.getStatistics();
    stats.value = res.data;
  } catch (_) {
    stats.value = { total_employees: 0, by_status: {}, by_department: [], by_location: [] };
  }
}

function setStatus(status) {
  filters.status = filters.status === status ? "" : status;
}

let searchTimer;
watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => load(1), 350);
});
watch(filters, () => load(1));

function openDetails(e) {
  menuId.value = null;
  detailTab.value = "profile";
  selected.value = e;
}

async function setEmploymentStatus(e, status) {
  menuId.value = null;
  try {
    await employeeInfoService.updateStatus(e.id, status);
    e.employment_status = status;
    toast.success(`${e.full_name} is now ${status}.`);
    loadStats();
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
    toast.success(`${e.full_name} was deleted.`);
    if (selected.value?.id === e.id) selected.value = null;
    deleting.value = null;
    await Promise.all([load(employees.value?.length === 1 && page.value.current_page > 1 ? page.value.current_page - 1 : page.value.current_page), loadStats()]);
  } catch (err) {
    toast.error(err?.message || "Couldn't delete the profile.");
  } finally {
    busy.value = false;
  }
}

// ── Form ──

function openForm(source = null) {
  if (source ? !canEdit.value : !canCreate.value) {
    toast.error(source ? NO_PERMISSION.edit : NO_PERMISSION.create);
    return;
  }
  menuId.value = null;
  Object.keys(errors).forEach((k) => delete errors[k]);
  formError.value = "";
  const f = Object.fromEntries(PROFILE_KEYS.map((k) => [k, ""]));
  Object.assign(f, {
    employment_status: "Probationary",
    employment_type: "Full-time",
    work_schedule: "Fixed",
    shift_start: "08:00",
    shift_end: "17:00",
    rest_days: ["Sunday"],
    standard_work_hours_per_day: 8,
    working_days_per_week: 6,
    working_days_per_month: 26,
    date_hired: today,
    avatar: null,
    avatarPreview: "",
  });
  if (source) {
    for (const k of PROFILE_KEYS) f[k] = source[k] ?? "";
    Object.assign(f, {
      id: source.id,
      original: source.full_name,
      date_of_birth: day10(source.date_of_birth),
      date_hired: day10(source.date_hired),
      shift_start: hm5(source.shift_start),
      shift_end: hm5(source.shift_end),
      rest_days: restDaysList(source.rest_days),
      avatarPreview: source.avatar_url || "",
    });
  }
  form.value = f;
}

function closeForm() {
  if (!saving.value) form.value = null;
}

async function pickPhoto(event) {
  try {
    const file = validateImageFile(getSelectedFile(event), { fieldLabel: "Photo", maxSizeMB: 5 });
    if (!file) return;
    form.value.avatar = file;
    form.value.avatarPreview = await readImagePreview(file);
  } catch (e) {
    event.target.value = "";
    toast.error(e?.message || "Couldn't read that photo.");
  }
}

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  const f = form.value;
  for (const k of REQUIRED_FIELDS) if (!String(f[k] ?? "").trim()) errors[k] = "Required.";
  for (const k of ["work_email", "personal_email"]) if (f[k] && !/^\S+@\S+\.\S+$/.test(String(f[k]).trim())) errors[k] = "Enter a valid email.";
  if (f.date_of_birth && f.date_of_birth >= today) errors.date_of_birth = "Pick a day in the past.";
  if (f.shift_start && f.shift_end && f.shift_end <= f.shift_start) errors.shift_end = "End after it starts.";
  if (f.salary_type && !f.basic_salary) errors.basic_salary = "Enter the salary.";
  return !Object.keys(errors).length;
}

async function save() {
  const f = form.value;
  if (saving.value || (f.id ? !canEdit.value : !canCreate.value)) return;
  if (!validate()) {
    formError.value = "Fill in the highlighted fields.";
    return;
  }
  saving.value = true;
  formError.value = "";
  const values = {};
  for (const k of PROFILE_KEYS) values[k] = typeof f[k] === "string" ? f[k].trim() : f[k];
  values.rest_days = DAYS.filter((d) => f.rest_days.includes(d)).join(", ") || "None";
  values.avatar = f.avatar;
  if (!values.salary_type) values.basic_salary = "";
  if (values.payment_method !== "Bank") {
    values.bank_name = "";
    values.account_number = "";
  }
  try {
    const payload = buildMultipartFormData(values, { fileFields: ["avatar"] });
    const res = f.id ? await employeeInfoService.update(f.id, payload) : await employeeInfoService.create(payload);
    toast.success(f.id ? "Profile saved." : "Profile added. The shop owner can see it too.");
    form.value = null;
    await Promise.all([load(f.id ? page.value.current_page : 1), loadStats()]);
    const fresh = employees.value?.find((e) => e.id === (res.data?.id ?? f.id));
    if (fresh) selected.value = fresh;
    else if (selected.value?.id === f.id && res.data) selected.value = res.data;
  } catch (err) {
    const fieldErrors = err?.errors ?? {};
    for (const [key, list] of Object.entries(fieldErrors)) errors[key] = [].concat(list)[0];
    formError.value = Object.keys(fieldErrors).length ? "Fix the highlighted fields." : err?.message || "Couldn't save.";
  } finally {
    saving.value = false;
  }
}

// Directory and other pages link here with ?add=1, ?view=<id> or ?edit=<id>
async function openFromQuery() {
  if (route.query.add && canCreate.value) {
    openForm();
    return;
  }
  const viewId = route.query.view;
  const editId = route.query.edit || route.query.id;
  const id = viewId || editId;
  if (!id) return;
  let target = employees.value?.find((e) => String(e.id) === String(id) || e.employee_id === id);
  if (!target) {
    try {
      target = (await employeeInfoService.getById(id))?.data;
    } catch (_) {
      return;
    }
  }
  if (!target) return;
  if (editId && canEdit.value) openForm(target);
  else openDetails(target);
}

const closeMenu = (e) => {
  if (!e.target.closest?.(".pf-menu-wrap")) menuId.value = null;
};
const onKey = (e) => {
  if (e.key !== "Escape") return;
  if (deleting.value) deleting.value = null;
  else if (form.value) closeForm();
  else if (selected.value) selected.value = null;
  else menuId.value = null;
};

onMounted(async () => {
  document.addEventListener("click", closeMenu);
  document.addEventListener("keydown", onKey);
  loadStats();
  await load(1);
  openFromQuery();
});
onBeforeUnmount(() => {
  document.removeEventListener("click", closeMenu);
  document.removeEventListener("keydown", onKey);
  clearTimeout(searchTimer);
});
</script>

<style scoped>
.pf-note {
  margin-bottom: 16px;
}
.sp-stats {
  margin-bottom: 18px;
}
.sp-stat .grow {
  flex: 1;
}
.pf-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 8px;
}
.pf-toolbar .sp-search {
  flex: 1 1 240px;
  max-width: 340px;
}
.pf-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.pf-filters .sp-select {
  width: auto;
  min-width: 140px;
}
.pf-sk {
  margin: 0;
  padding: 0;
  list-style: none;
}
.pf-sk li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
}
.pf-sk .grow {
  flex: 1;
}
.pf-two {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.pf-two b {
  font-weight: 500;
  color: var(--v-text);
}
.pf-two small {
  font-size: 12px;
  color: var(--v-muted);
}
.pf-menu-wrap {
  position: relative;
  display: inline-block;
}
.pf-menu {
  position: absolute;
  right: 0;
  top: calc(100% + 4px);
  z-index: 20;
  display: flex;
  flex-direction: column;
  min-width: 200px;
  padding: 6px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-raised);
  box-shadow: 0 12px 32px var(--v-shadow);
}
.pf-menu button {
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
.pf-menu button :deep(svg) {
  width: 15px;
  height: 15px;
  color: var(--v-muted);
}
.pf-menu button:hover:not(:disabled) {
  background: var(--v-subtle);
}
.pf-menu button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.pf-menu button.danger,
.pf-menu button.danger :deep(svg) {
  color: var(--v-red-text);
}
.pf-pages {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 14px;
  margin-top: 6px;
  border-top: 1px solid var(--v-border);
  font-size: 12.5px;
  color: var(--v-muted);
}
.pf-pages div {
  display: flex;
  gap: 6px;
}
.pf-sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}

/* Drawer */
.pf-avatar-lg {
  width: 52px;
  height: 52px;
  font-size: 17px;
}
.pf-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}
.pf-head-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: none;
}
.sp-drawer-head h3 {
  overflow-wrap: anywhere;
}
.pf-quick {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 16px;
}
.pf-quick a {
  text-decoration: none;
}
.pf-by {
  margin-left: auto;
  font-size: 12px;
  color: var(--v-faint);
}
.pf-seg {
  display: flex;
  width: 100%;
  margin-bottom: 4px;
  overflow-x: auto;
}
.pf-seg button {
  flex: 1;
  white-space: nowrap;
}
.pf-qr {
  margin-top: 18px;
}

/* Form */
.pf-fieldset {
  margin: 0;
  border: 0;
  min-width: 0;
}
.st-section-title.first {
  margin-top: 0;
}
.pf-photo {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.pf-upload {
  position: relative;
  overflow: hidden;
}
.pf-upload input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.pf-upload:has(input:focus-visible) {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 30%, transparent);
}
.pf-hours {
  display: flex;
  align-items: center;
  gap: 8px;
}
.pf-hours .sp-input {
  flex: 1;
  min-width: 0;
}
.pf-hours > span {
  font-size: 12.5px;
  color: var(--v-muted);
}
.pf-days {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.pf-day {
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
.pf-day.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  color: var(--v-text);
}
.pf-day input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.pf-day:has(input:focus-visible) {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 30%, transparent);
}
.pf-hint {
  margin: -4px 0 12px;
}
.pf-rate {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 14px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.pf-rate div {
  display: flex;
  flex-direction: column;
}
.pf-rate strong {
  font-size: 14px;
}
.pf-rate small {
  font-size: 12px;
  opacity: 0.85;
}
@media (max-width: 760px) {
  .pf-toolbar .sp-search {
    max-width: none;
  }
  .pf-filters,
  .pf-filters .sp-select {
    flex: 1 1 140px;
  }
}
</style>
