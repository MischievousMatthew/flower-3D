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
      <h1 class="form-title">Add New Supplier</h1>
    </div>

    <div class="form-grid">
      <!-- Main Form -->
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
              placeholder="e.g. Acme Corp Ltd."
            />
            <span v-if="errors.company_name" class="err-msg">{{
              errors.company_name
            }}</span>
          </div>
          <div class="field">
            <label>Contact Person <span class="req">*</span></label>
            <input v-model="form.contact_person" placeholder="Full name" />
          </div>
        </div>

        <div class="field-group">
          <div class="field">
            <label>Email Address <span class="req">*</span></label>
            <input
              v-model="form.email"
              type="email"
              :class="{ error: errors.email }"
              placeholder="contact@company.com"
            />
            <span v-if="errors.email" class="err-msg">{{ errors.email }}</span>
          </div>
          <div class="field">
            <label>Phone Number <span class="req">*</span></label>
            <input v-model="form.phone" placeholder="+1 (555) 000-0000" />
          </div>
        </div>

        <div class="field">
          <label>Address <span class="req">*</span></label>
          <textarea
            v-model="form.address"
            placeholder="Full business address"
            rows="3"
          ></textarea>
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

        <div class="section-title" style="margin-top: 8px">
          <div class="section-dot"></div>
          Additional Contacts
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
            <button class="remove-btn" @click="removeContact(i)">×</button>
          </div>
          <div class="field-group">
            <div class="field">
              <label>Company</label
              ><input v-model="c.company_name" placeholder="Company name" />
            </div>
            <div class="field">
              <label>Name</label
              ><input v-model="c.contact_person" placeholder="Full name" />
            </div>
          </div>
          <div class="field-group">
            <div class="field">
              <label>Email</label
              ><input
                v-model="c.email"
                type="email"
                placeholder="email@example.com"
              />
            </div>
            <div class="field">
              <label>Phone</label
              ><input v-model="c.phone" placeholder="+1 (555) 000-0000" />
            </div>
          </div>
          <div class="field">
            <label>Address</label
            ><input v-model="c.address" placeholder="Address" />
          </div>
        </div>
      </div>

      <!-- Sidebar Panel -->
      <div class="side-panel">
        <div class="panel-card">
          <div class="panel-title">Quick Tips</div>
          <ul class="tips-list">
            <li>Ensure the email is unique per supplier</li>
            <li>Active suppliers can receive purchase orders</li>
            <li>Add multiple contacts for large companies</li>
          </ul>
        </div>

        <div class="panel-card actions-card">
          <button class="btn-submit" :disabled="submitting" @click="submit">
            <svg
              v-if="submitting"
              class="spinner-icon"
              viewBox="0 0 24 24"
              fill="none"
              width="16"
            >
              <circle
                cx="12"
                cy="12"
                r="9"
                stroke="currentColor"
                stroke-width="2"
                stroke-dasharray="28"
                stroke-dashoffset="0"
              />
            </svg>
            {{ submitting ? "Saving…" : "Create Supplier" }}
          </button>
          <router-link
            to="/erp/procurement/supply-chain/suppliers"
            class="btn-cancel"
            >Cancel</router-link
          >
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
import { ref, reactive } from "vue";
import { useRouter } from "vue-router";
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

const router = useRouter();
const submitting = ref(false);
const errors = reactive({});
const logoInput = ref(null);
const logoPreview = ref(null);
const logoFile = ref(null);

const statusOptions = [
  { label: "Active", value: "active" },
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

function removeContact(i) {
  form.contacts.splice(i, 1);
}

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  if (!form.company_name) errors.company_name = "Company name is required";
  if (!form.email) errors.email = "Email is required";
  return !Object.keys(errors).length;
}

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

    await supplierService.create(fd);
    showToast("Supplier created successfully!");
    setTimeout(
      () => router.push("/erp/procurement/supply-chain/suppliers"),
      1000,
    );
  } catch (e) {
    Object.assign(errors, e?.errors || {});
    showToast(e?.message || "Failed to create supplier", "error");
  } finally {
    submitting.value = false;
  }
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
  transition: color 0.15s;
}
.back-link:hover {
  color: #111827;
}
.form-title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
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
.field input.error,
.field textarea.error {
  border-color: #ef4444;
}
.field textarea {
  resize: vertical;
}
.req {
  color: #ef4444;
}
.err-msg {
  font-size: 11.5px;
  color: #ef4444;
  margin-top: 2px;
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

/* Side Panel */
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
.tips-list {
  margin: 0;
  padding: 0 0 0 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.tips-list li {
  font-size: 12.5px;
  color: #6b7280;
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
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
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
  transition: background 0.15s;
}
.btn-cancel:hover {
  background: #f9fafb;
}
.spinner-icon {
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.logo-upload-wrap {
  width: 100%;
  height: 110px;
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
  padding: 8px;
}
.logo-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  color: #9ca3af;
}
.logo-placeholder span {
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
}
.logo-hint {
  font-size: 11px !important;
  color: #9ca3af !important;
  font-weight: 400 !important;
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
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
  white-space: nowrap;
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
