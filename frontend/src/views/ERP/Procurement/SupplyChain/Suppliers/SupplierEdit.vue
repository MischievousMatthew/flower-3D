<template>
  <div class="form-page">
    
    <div class="form-header">
      <router-link
        to="/erp/procurement/supply-chain/suppliers"
        class="back-link"
      >
        <svg
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          width="16"
        >
          <path d="M13 16l-6-6 6-6" />
        </svg>
        Back to Suppliers
      </router-link>
      <div class="title-row">
        <h1 class="form-title">Edit Supplier</h1>
        <span v-if="supplier" class="status-badge" :class="supplier.status">{{
          supplier.status
        }}</span>
      </div>
    </div>

    <div v-if="pageLoading" class="loading-state">
      <div class="spinner-lg"></div>
    </div>

    <div v-else-if="supplier" class="form-grid">
      <div class="form-card">
        <div class="section-title">
          <div class="section-dot"></div>
          Company Information
        </div>

        <div class="field-group">
          <div class="field">
            <label>Company Name <span class="req">*</span></label>
            <input
              v-model="form.company_name"
              :class="{ error: errors.company_name }"
            />
            <span v-if="errors.company_name" class="err-msg">{{
              errors.company_name
            }}</span>
          </div>
          <div class="field">
            <label>Contact Person <span class="req">*</span></label>
            <input v-model="form.contact_person" />
          </div>
        </div>

        <div class="field-group">
          <div class="field">
            <label>Email Address <span class="req">*</span></label>
            <input
              v-model="form.email"
              type="email"
              :class="{ error: errors.email }"
            />
          </div>
          <div class="field">
            <label>Phone Number</label>
            <input v-model="form.phone" />
          </div>
        </div>

        <div class="field">
          <label>Address</label>
          <textarea v-model="form.address" rows="3"></textarea>
        </div>

        <div class="field">
          <label>Status</label>
          <div class="radio-group">
            <label
              v-for="opt in statusOptions"
              :key="opt.value"
              class="radio-option"
              :class="{ chosen: form.status === opt.value }"
            >
              <input
                type="radio"
                v-model="form.status"
                :value="opt.value"
                hidden
              />
              <span class="radio-dot" :class="opt.value"></span>
              {{ opt.label }}
            </label>
          </div>
        </div>

        <!-- Logo Upload -->
        <div class="field">
          <label>Company Logo</label>
          <div class="logo-upload-wrap" @click="$refs.logoInput.click()">
            <img
              v-if="logoPreview"
              :src="logoPreview"
              class="logo-preview"
              alt="Logo preview"
            />
            <div v-else class="logo-placeholder">
              <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                width="28"
              >
                <rect x="3" y="3" width="18" height="18" rx="3" />
                <circle cx="8.5" cy="8.5" r="1.5" />
                <path d="M21 15l-5-5L5 21" />
              </svg>
              <span>Click to upload logo</span>
              <span class="logo-hint">PNG, JPG up to 2MB</span>
            </div>
          </div>
          <input
            ref="logoInput"
            type="file"
            accept="image/*"
            hidden
            @change="handleLogoChange"
          />
        </div>

        <!-- Contacts -->
        <div class="section-title" style="margin-top: 4px">
          <div class="section-dot"></div>
          Contacts
          <button class="add-contact-btn" @click="addContact">
            <svg viewBox="0 0 20 20" fill="currentColor" width="12">
              <path
                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
              />
            </svg>
            Add
          </button>
        </div>

        <div v-for="(c, i) in form.contacts" :key="i" class="contact-block">
          <div class="contact-block-header">
            <span>Contact #{{ i + 1 }}</span>
            <button class="remove-btn" @click="form.contacts.splice(i, 1)">
              ×
            </button>
          </div>
          <div class="field-group">
            <div class="field">
              <label>Company</label><input v-model="c.company_name" />
            </div>
            <div class="field">
              <label>Name</label><input v-model="c.contact_person" />
            </div>
          </div>
          <div class="field-group">
            <div class="field">
              <label>Email</label><input v-model="c.email" type="email" />
            </div>
            <div class="field">
              <label>Phone</label><input v-model="c.phone" />
            </div>
          </div>
          <div class="field">
            <label>Address</label><input v-model="c.address" />
          </div>
        </div>
      </div>

      <div class="side-panel">
        <!-- Quick actions -->
        <div class="panel-card quick-actions">
          <div class="panel-title">Quick Actions</div>
          <button
            v-if="supplier.status !== 'active'"
            class="qa-btn green"
            @click="quickStatus('active')"
          >
            ✓ Activate Supplier
          </button>
          <button
            v-if="supplier.status === 'active'"
            class="qa-btn yellow"
            @click="quickStatus('inactive')"
          >
            ⏸ Deactivate Supplier
          </button>
          <button class="qa-btn red" @click="quickStatus('blacklisted')">
            🚫 Blacklist Supplier
          </button>
        </div>

        <div class="panel-card actions-card">
          <button class="btn-submit" :disabled="submitting" @click="submit">
            {{ submitting ? "Saving…" : "Save Changes" }}
          </button>
          <router-link
            to="/erp/procurement/supply-chain/suppliers"
            class="btn-cancel"
            >Cancel</router-link
          >
        </div>

        <div class="panel-card info-card" v-if="supplier">
          <div class="info-row">
            <span>Created</span
            ><span>{{ formatDate(supplier.created_at) }}</span>
          </div>
          <div class="info-row">
            <span>Updated</span
            ><span>{{ formatDate(supplier.updated_at) }}</span>
          </div>
          <div class="info-row">
            <span>Orders</span
            ><span>{{ supplier.purchase_orders?.length ?? 0 }}</span>
          </div>
        </div>
      </div>
    </div>

    <transition name="toast-slide">
      <div v-if="toast.show" class="toast" :class="toast.type">
        {{ toast.message }}
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import { supplierService } from "../../../../../services/supplierService";
import {
  buildMultipartFormData,
  clearFileInput,
  getSelectedFile,
  readImagePreview,
  validateImageFile,
} from "../../../../../utils/imageUpload";

const route = useRoute();
const router = useRouter();
const supplier = ref(null);
const pageLoading = ref(true);
const submitting = ref(false);
const errors = reactive({});
const logoInput = ref(null);
const logoPreview = ref(null);
const logoFile = ref(null);

const statusOptions = [
  { label: "Active", value: "active" },
  { label: "Inactive", value: "inactive" },
  { label: "Blacklisted", value: "blacklisted" },
];

const form = reactive({
  company_name: "",
  contact_person: "",
  email: "",
  phone: "",
  address: "",
  status: "active",
  contacts: [],
});

onMounted(async () => {
  try {
    const s = await supplierService.find(route.params.id);
    supplier.value = s.data ?? s;
    Object.assign(form, {
      company_name: supplier.value.company_name,
      contact_person: supplier.value.contact_person,
      email: supplier.value.email,
      phone: supplier.value.phone,
      address: supplier.value.address,
      status: supplier.value.status,
      contacts: (supplier.value.contacts || []).map((c) => ({ ...c })),
    });
    if (supplier.value.logo_url) {
      logoPreview.value = supplier.value.logo_url;
    }
  } catch {
    showToast("Failed to load supplier", "error");
  } finally {
    pageLoading.value = false;
  }
});

async function handleLogoChange(event) {
  try {
    const file = validateImageFile(getSelectedFile(event), {
      fieldLabel: "Company logo",
      maxSizeMB: 2,
    });

    if (!file) return;

    logoFile.value = file;
    logoPreview.value = await readImagePreview(file);
  } catch (error) {
    logoFile.value = null;
    clearFileInput(logoInput);
    showToast(error.message || "Failed to read the selected logo", "error");
  }
}

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  if (!form.company_name) errors.company_name = "Company name is required";
  if (!form.email) errors.email = "Email is required";
  return !Object.keys(errors).length;
}

async function submit() {
  if (!validate()) return;
  submitting.value = true;
  try {
    const fd = buildMultipartFormData(
      {
        ...form,
        logo: logoFile.value,
      },
      {
        fileFields: ["logo"],
        jsonFields: ["contacts"],
      },
    );

    await supplierService.update(route.params.id, fd);
    showToast("Supplier updated successfully!");
    setTimeout(
      () => router.push("/erp/procurement/supply-chain/suppliers"),
      1000,
    );
  } catch (e) {
    Object.assign(errors, e?.errors || {});
    showToast(e?.message || "Update failed", "error");
  } finally {
    submitting.value = false;
  }
}

async function quickStatus(status) {
  try {
    const svc = {
      active: "activate",
      inactive: "deactivate",
      blacklisted: "blacklist",
    };
    await supplierService[svc[status]](route.params.id);
    supplier.value.status = status;
    form.status = status;
    showToast(`Status updated to ${status}`);
  } catch {
    showToast("Failed to update status", "error");
  }
}

function addContact() {
  form.contacts.push({
    company_name: "",
    contact_person: "",
    email: "",
    phone: "",
    address: "",
    status: "active",
  });
}

function formatDate(d) {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function showToast(msg, type = "success") {
  const map = {
    success: toast.success,
    error: toast.error,
    warn: toast.warning,
  };
  (map[type] ?? toast.success)(msg, {
    autoClose: 3000,
    position: toast.POSITION.TOP_RIGHT,
  });
}
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}
/* Reuse pattern from SupplierCreate with extensions */
.form-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 900px;
}
.form-header {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #6b7280;
  text-decoration: none;
  font-size: 13px;
}
.back-link:hover {
  color: #111827;
}
.title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}
.form-title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}
.status-badge.active {
  background: #dcfce7;
  color: #16a34a;
}
.status-badge.inactive {
  background: #fef3c7;
  color: #92400e;
}
.status-badge.blacklisted {
  background: #fee2e2;
  color: #dc2626;
}

.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 300px;
}
.spinner-lg {
  width: 40px;
  height: 40px;
  border: 3px solid #e5e7eb;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 260px;
  gap: 20px;
  align-items: start;
}
.form-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e8ecf0;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  font-weight: 700;
  color: #111827;
}
.section-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
}
.field-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.field label {
  font-size: 12.5px;
  font-weight: 600;
  color: #374151;
}
.field input,
.field textarea {
  padding: 9px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  font-size: 13.5px;
  color: #111827;
  outline: none;
  transition: border-color 0.15s;
  font-family: inherit;
}
.field input:focus,
.field textarea:focus {
  border-color: #10b981;
}
.field textarea {
  resize: vertical;
}

.logo-upload-wrap {
  width: 100%;
  height: 130px;
  border: 2px dashed #e5e7eb;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  overflow: hidden;
  transition:
    border-color 0.15s,
    background 0.15s;
  background: #f9fafb;
}
.logo-upload-wrap:hover {
  border-color: #10b981;
  background: #f0fdf4;
}
.logo-preview {
  width: 100%;
  height: 100%;
  object-fit: contain;
  padding: 10px;
}
.logo-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #9ca3af;
  text-align: center;
  pointer-events: none;
}
.logo-placeholder svg {
  color: #d1d5db;
}
.logo-placeholder span {
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  display: block;
}
.logo-hint {
  font-size: 11px !important;
  color: #9ca3af !important;
  font-weight: 400 !important;
  display: block;
}

.req {
  color: #ef4444;
}
.err-msg {
  font-size: 11.5px;
  color: #ef4444;
}
.radio-group {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.radio-option {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 7px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  transition: all 0.15s;
}
.radio-option.chosen {
  border-color: #10b981;
  background: #ecfdf5;
  color: #059669;
}
.radio-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.radio-dot.active {
  background: #10b981;
}
.radio-dot.inactive {
  background: #f59e0b;
}
.radio-dot.blacklisted {
  background: #ef4444;
}
.add-contact-btn {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid #10b981;
  background: #ecfdf5;
  color: #059669;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.contact-block {
  border: 1.5px dashed #e5e7eb;
  border-radius: 10px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.contact-block-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12.5px;
  font-weight: 600;
  color: #6b7280;
}
.remove-btn {
  border: none;
  background: none;
  font-size: 18px;
  color: #9ca3af;
  cursor: pointer;
  line-height: 1;
}
.remove-btn:hover {
  color: #ef4444;
}

.side-panel {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.panel-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e8ecf0;
  padding: 18px;
}
.panel-title {
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 10px;
}
.quick-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.qa-btn {
  width: 100%;
  padding: 9px 14px;
  border-radius: 8px;
  border: none;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  text-align: left;
  transition: opacity 0.15s;
}
.qa-btn.green {
  background: #dcfce7;
  color: #15803d;
}
.qa-btn.yellow {
  background: #fef9c3;
  color: #92400e;
}
.qa-btn.red {
  background: #fee2e2;
  color: #dc2626;
}
.qa-btn:hover {
  opacity: 0.8;
}
.actions-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.btn-submit {
  width: 100%;
  padding: 11px;
  border-radius: 10px;
  border: none;
  background: #10b981;
  color: #fff;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-submit:hover {
  background: #059669;
}
.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-cancel {
  width: 100%;
  padding: 10px;
  border-radius: 10px;
  border: 1.5px solid #e5e7eb;
  background: #fff;
  color: #374151;
  font-weight: 500;
  font-size: 13.5px;
  cursor: pointer;
  text-align: center;
  text-decoration: none;
  display: block;
}
.info-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12.5px;
}
.info-row span:first-child {
  color: #6b7280;
}
.info-row span:last-child {
  font-weight: 600;
  color: #374151;
}
.toast {
  position: fixed;
  bottom: 28px;
  left: 50%;
  transform: translateX(-50%);
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 500;
  z-index: 500;
}
.toast.success {
  background: #111827;
  color: #fff;
}
.toast.error {
  background: #ef4444;
  color: #fff;
}
.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.3s;
}
.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(12px);
}
</style>
