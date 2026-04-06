<!-- VendorRegistration.vue -->
<template>
  <div class="register-container">
    <!-- Logo -->
    <router-link to="/" class="logo"
      ><img
        src="../../../public/bloomcraft-blankBg.png"
        alt="Bloomcraft Logo"
        width="60"
        height="60"
    /></router-link>

    <!-- Back Button -->
    <button v-if="currentStep > 1" @click="prevStep" class="back-btn">
      ← Back
    </button>

    <!-- Header -->
    <div class="header">
      <h1 class="title">Become a Vendor</h1>
      <p class="subtitle">
        Already registered?
        <router-link to="/guest/login" class="link">Log in</router-link>
      </p>
    </div>

    <!-- Progress Steps -->
    <div class="steps-indicator">
      <template v-for="(step, index) in steps" :key="step.number">
        <div
          class="step"
          :class="{
            active: currentStep >= step.number,
            completed: currentStep > step.number,
          }"
        >
          <div class="step-number">{{ step.number }}</div>
          <span class="step-label">{{ step.title }}</span>
        </div>
        <div
          v-if="index < steps.length - 1"
          class="step-line"
          :class="{ active: currentStep > step.number }"
        ></div>
      </template>
    </div>

    <!-- Form Content -->
    <div class="form-content">
      <!-- Step 1: Store Information -->
      <div v-if="currentStep === 1" class="form-step">
        <h2 class="step-title">Store Information</h2>

        <div class="input-group">
          <label class="input-label">Store / Shop Name *</label>
          <input
            v-model="formData.storeName"
            type="text"
            placeholder="Enter your store name"
            class="form-input"
            :class="{ error: errors.storeName }"
          />
          <div v-if="errors.storeName" class="error-message">
            {{ errors.storeName }}
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Store Description *</label>
          <textarea
            v-model="formData.storeDescription"
            placeholder="Describe your store and what makes it special"
            rows="4"
            class="form-input"
            :class="{ error: errors.storeDescription }"
          />
          <div v-if="errors.storeDescription" class="error-message">
            {{ errors.storeDescription }}
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Business Type *</label>
          <select
            v-model="formData.businessType"
            class="form-input"
            :class="{ error: errors.businessType }"
          >
            <option value="">Select business type</option>
            <option value="individual">Individual / Sole Proprietor</option>
            <option value="partnership">Partnership</option>
            <option value="corporation">Corporation</option>
          </select>
          <div v-if="errors.businessType" class="error-message">
            {{ errors.businessType }}
          </div>
        </div>

        <!-- CHANGED: Physical Store Address — Dropdown Only -->
        <div class="input-group">
          <label class="input-label">Physical Store Address *</label>
          <select
            v-model="formData.storeAddress"
            class="form-input"
            :class="{ error: errors.storeAddress }"
          >
            <option value="">Select your city / municipality</option>
            <optgroup label="── Cities ──">
              <option v-for="city in caviteCities" :key="city" :value="city">
                {{ city }}
              </option>
            </optgroup>
            <optgroup label="── Municipalities ──">
              <option
                v-for="muni in caviteMunicipalities"
                :key="muni"
                :value="muni"
              >
                {{ muni }}
              </option>
            </optgroup>
          </select>
          <p class="hint-text">
            Only cities and municipalities within Cavite are currently
            supported.
          </p>
          <div v-if="errors.storeAddress" class="error-message">
            {{ errors.storeAddress }}
          </div>
        </div>

        <!-- CHANGED: Service Areas — Restricted Checkbox Selection -->
        <div class="input-group">
          <label class="input-label">Service Areas *</label>
          <div
            class="service-areas-container"
            :class="{ 'container-error': errors.serviceAreas }"
          >
            <label
              v-for="area in serviceAreaOptions"
              :key="area.value"
              class="service-area-option"
            >
              <input
                type="checkbox"
                :value="area.value"
                v-model="selectedServiceAreas"
                class="checkbox-input"
              />
              <span class="service-area-label">{{ area.label }}</span>
            </label>
          </div>
          <p class="hint-text">Select one or both service regions.</p>
          <div v-if="errors.serviceAreas" class="error-message">
            {{ errors.serviceAreas }}
          </div>
        </div>

        <!-- CHANGED: Operating Hours — Day Checkboxes + Time Inputs -->
        <div class="input-group">
          <label class="input-label">Operating Hours *</label>
          <div
            class="operating-hours-container"
            :class="{ 'container-error': errors.operatingHours }"
          >
            <!-- Day Selection -->
            <p class="oh-section-label">Select operating days:</p>
            <div class="days-grid">
              <label
                v-for="day in daysOfWeek"
                :key="day"
                class="day-checkbox-label"
                :class="{ selected: operatingHoursConfig.days.includes(day) }"
              >
                <input
                  type="checkbox"
                  :value="day"
                  v-model="operatingHoursConfig.days"
                  class="day-checkbox-hidden"
                />
                <span>{{ day.substring(0, 3) }}</span>
              </label>
            </div>

            <!-- Quick Select Shortcuts -->
            <div class="day-shortcuts">
              <button
                type="button"
                class="shortcut-btn"
                @click="selectWeekdays"
              >
                Mon–Fri
              </button>
              <button
                type="button"
                class="shortcut-btn"
                @click="selectWeekends"
              >
                Sat–Sun
              </button>
              <button type="button" class="shortcut-btn" @click="selectAllDays">
                Everyday
              </button>
              <button
                type="button"
                class="shortcut-btn shortcut-clear"
                @click="clearDays"
              >
                Clear
              </button>
            </div>

            <!-- Time Range -->
            <p class="oh-section-label" style="margin-top: 16px">
              Set opening and closing times:
            </p>
            <div class="time-range-row">
              <div class="time-field">
                <label class="time-label">Opening Time</label>
                <input
                  type="time"
                  v-model="operatingHoursConfig.startTime"
                  class="form-input time-input"
                />
              </div>
              <span class="time-separator">–</span>
              <div class="time-field">
                <label class="time-label">Closing Time</label>
                <input
                  type="time"
                  v-model="operatingHoursConfig.endTime"
                  class="form-input time-input"
                />
              </div>
            </div>

            <!-- Live Preview -->
            <div v-if="formData.operatingHours" class="hours-preview">
              <span class="preview-icon">🕐</span>
              <span>{{ formData.operatingHours }}</span>
            </div>
          </div>
          <div v-if="errors.operatingHours" class="error-message">
            {{ errors.operatingHours }}
          </div>
        </div>
      </div>

      <!-- Step 2: Owner Information & Verification (UNCHANGED) -->
      <div v-if="currentStep === 2" class="form-step">
        <h2 class="step-title">Owner Information & Verification</h2>

        <div class="input-group">
          <label class="input-label">Owner's Full Name *</label>
          <input
            v-model="formData.ownerName"
            type="text"
            placeholder="Enter full name"
            class="form-input"
            :class="{ error: errors.ownerName }"
          />
          <div v-if="errors.ownerName" class="error-message">
            {{ errors.ownerName }}
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Position / Role *</label>
          <input
            v-model="formData.position"
            type="text"
            placeholder="e.g., Owner, Manager, CEO"
            class="form-input"
            :class="{ error: errors.position }"
          />
          <div v-if="errors.position" class="error-message">
            {{ errors.position }}
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Contact Number *</label>
          <input
            v-model="formData.contactNumber"
            type="tel"
            placeholder="+63 912 345 6789"
            class="form-input"
            :class="{ error: errors.contactNumber }"
          />
          <div v-if="errors.contactNumber" class="error-message">
            {{ errors.contactNumber }}
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Email Address *</label>
          <input
            v-model="formData.email"
            type="email"
            placeholder="your.email@example.com"
            class="form-input"
            :class="{ error: errors.email }"
          />
          <div v-if="errors.email" class="error-message">
            {{ errors.email }}
          </div>
        </div>

        <div class="section-divider">
          <h3 class="section-title">Identity Verification</h3>
        </div>

        <div class="input-group">
          <label class="input-label">Government-Issued ID *</label>
          <input
            v-model="formData.governmentIdNumber"
            type="text"
            placeholder="Enter ID Number"
            class="form-input id-number-input"
            :class="{ error: errors.governmentIdNumber }"
          />
          <div v-if="errors.governmentIdNumber" class="error-message">
            {{ errors.governmentIdNumber }}
          </div>
          <div class="file-upload-container">
            <div
              class="file-drop-zone"
              @click="$refs.government_idInput.click()"
              @dragover.prevent="handleDragOver"
              @dragleave.prevent="handleDragLeave"
              @drop.prevent="handleDrop($event, 'government_id')"
              :class="{
                'drag-over': dragOver.government_id,
                'has-file': fileInfo.government_id,
              }"
            >
              <div v-if="!fileInfo.government_id" class="upload-placeholder">
                <svg class="upload-icon" viewBox="0 0 24 24">
                  <path
                    d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"
                  />
                </svg>
                <p class="upload-text">Drop file here or click to upload</p>
                <p class="upload-hint">
                  Max size: 5MB • Formats: JPG, PNG, PDF
                </p>
              </div>
              <div v-else class="file-preview">
                <div class="preview-icon">
                  <span v-if="isImage(fileInfo.government_id.type)">🖼️</span>
                  <span v-else>📄</span>
                </div>
                <div class="file-details">
                  <div class="file-name">{{ fileInfo.government_id.name }}</div>
                  <div class="file-meta">
                    <span class="file-size">{{
                      formatFileSize(fileInfo.government_id.size)
                    }}</span>
                    <span class="file-type">{{
                      getFileType(fileInfo.government_id.type)
                    }}</span>
                  </div>
                </div>
                <button
                  type="button"
                  @click.stop="removeFile('government_id')"
                  class="remove-file-btn"
                  title="Remove file"
                >
                  ×
                </button>
              </div>
            </div>
            <input
              type="file"
              @change="handleFileUpload($event, 'government_id')"
              accept=".jpg,.jpeg,.png,.pdf,image/*"
              class="file-input-hidden"
              ref="government_idInput"
              :class="{ error: errors.government_id }"
            />
          </div>
          <p class="hint-text">
            Accepted: Driver's License, Passport, National ID
          </p>
          <div v-if="errors.government_id" class="error-message">
            {{ errors.government_id }}
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Selfie with Government ID *</label>
          <div class="file-upload-container">
            <div
              class="file-drop-zone"
              @click="$refs.selfie_with_idInput.click()"
              @dragover.prevent="handleDragOver"
              @dragleave.prevent="handleDragLeave"
              @drop.prevent="handleDrop($event, 'selfie_with_id')"
              :class="{
                'drag-over': dragOver.selfie_with_id,
                'has-file': fileInfo.selfie_with_id,
              }"
            >
              <div v-if="!fileInfo.selfie_with_id" class="upload-placeholder">
                <svg class="upload-icon" viewBox="0 0 24 24">
                  <path
                    d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"
                  />
                </svg>
                <p class="upload-text">Drop photo here or click to upload</p>
                <p class="upload-hint">Max size: 5MB • Formats: JPG, PNG</p>
              </div>
              <div v-else class="file-preview">
                <div class="preview-icon">
                  <span>📸</span>
                </div>
                <div class="file-details">
                  <div class="file-name">
                    {{ fileInfo.selfie_with_id.name }}
                  </div>
                  <div class="file-meta">
                    <span class="file-size">{{
                      formatFileSize(fileInfo.selfie_with_id.size)
                    }}</span>
                    <span class="file-type">{{
                      getFileType(fileInfo.selfie_with_id.type)
                    }}</span>
                  </div>
                </div>
                <button
                  type="button"
                  @click.stop="removeFile('selfie_with_id')"
                  class="remove-file-btn"
                  title="Remove file"
                >
                  ×
                </button>
              </div>
            </div>
            <input
              type="file"
              @change="handleFileUpload($event, 'selfie_with_id')"
              accept=".jpg,.jpeg,.png,image/*"
              class="file-input-hidden"
              ref="selfie_with_idInput"
              :class="{ error: errors.selfie_with_id }"
            />
          </div>
          <div v-if="errors.selfie_with_id" class="error-message">
            {{ errors.selfie_with_id }}
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Proof of Address *</label>
          <div class="file-upload-container">
            <div
              class="file-drop-zone"
              @click="$refs.proof_of_addressInput.click()"
              @dragover.prevent="handleDragOver"
              @dragleave.prevent="handleDragLeave"
              @drop.prevent="handleDrop($event, 'proof_of_address')"
              :class="{
                'drag-over': dragOver.proof_of_address,
                'has-file': fileInfo.proof_of_address,
              }"
            >
              <div v-if="!fileInfo.proof_of_address" class="upload-placeholder">
                <svg class="upload-icon" viewBox="0 0 24 24">
                  <path
                    d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"
                  />
                </svg>
                <p class="upload-text">Drop file here or click to upload</p>
                <p class="upload-hint">
                  Max size: 5MB • Formats: JPG, PNG, PDF
                </p>
              </div>
              <div v-else class="file-preview">
                <div class="preview-icon">
                  <span v-if="isImage(fileInfo.proof_of_address.type)">🏠</span>
                  <span v-else>📄</span>
                </div>
                <div class="file-details">
                  <div class="file-name">
                    {{ fileInfo.proof_of_address.name }}
                  </div>
                  <div class="file-meta">
                    <span class="file-size">{{
                      formatFileSize(fileInfo.proof_of_address.size)
                    }}</span>
                    <span class="file-type">{{
                      getFileType(fileInfo.proof_of_address.type)
                    }}</span>
                  </div>
                </div>
                <button
                  type="button"
                  @click.stop="removeFile('proof_of_address')"
                  class="remove-file-btn"
                  title="Remove file"
                >
                  ×
                </button>
              </div>
            </div>
            <input
              type="file"
              @change="handleFileUpload($event, 'proof_of_address')"
              accept=".jpg,.jpeg,.png,.pdf,image/*"
              class="file-input-hidden"
              ref="proof_of_addressInput"
              :class="{ error: errors.proof_of_address }"
            />
          </div>
          <p class="hint-text">
            Utility Bill, Barangay Certificate, or Lease Contract
          </p>
          <div v-if="errors.proof_of_address" class="error-message">
            {{ errors.proof_of_address }}
          </div>
        </div>
      </div>

      <!-- Step 3: Business Registration (UNCHANGED) -->
      <div v-if="currentStep === 3" class="form-step">
        <h2 class="step-title">Business Registration</h2>
        <p class="subtitle-text">
          If available, please provide your business registration details
        </p>

        <div class="input-group">
          <label class="input-label">DTI Registration Number *</label>
          <input
            v-model="formData.dtiNumber"
            type="text"
            placeholder="Enter DTI number"
            class="form-input"
            :class="{ error: errors.dtiNumber }"
          />
          <div v-if="errors.dtiNumber" class="error-message">
            {{ errors.dtiNumber }}
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">SEC Registration Number *</label>
          <input
            v-model="formData.secNumber"
            type="text"
            placeholder="Enter SEC number"
            class="form-input"
            :class="{ error: errors.secNumber }"
          />
          <div v-if="errors.secNumber" class="error-message">
            {{ errors.secNumber }}
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Barangay Clearance Number *</label>
          <input
            v-model="formData.barangayClearanceNumber"
            type="text"
            placeholder="Enter Barangay Clearance Number"
            class="form-input"
            :class="{ error: errors.barangayClearanceNumber }"
          />
          <div v-if="errors.barangayClearanceNumber" class="error-message">
            {{ errors.barangayClearanceNumber }}
          </div>
          <div class="file-upload-container">
            <div
              class="file-drop-zone"
              @click="$refs.barangay_clearanceInput.click()"
              @dragover.prevent="handleDragOver"
              @dragleave.prevent="handleDragLeave"
              @drop.prevent="handleDrop($event, 'barangay_clearance')"
              :class="{
                'drag-over': dragOver.barangay_clearance,
                'has-file': fileInfo.barangay_clearance,
              }"
            >
              <div
                v-if="!fileInfo.barangay_clearance"
                class="upload-placeholder"
              >
                <svg class="upload-icon" viewBox="0 0 24 24">
                  <path
                    d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"
                  />
                </svg>
                <p class="upload-text">Drop file here or click to upload</p>
                <p class="upload-hint">
                  Max size: 10MB • Formats: JPG, PNG, PDF
                </p>
              </div>
              <div v-else class="file-preview">
                <div class="preview-icon">
                  <span v-if="isImage(fileInfo.barangay_clearance.type)"
                    >🏛️</span
                  >
                  <span v-else>📄</span>
                </div>
                <div class="file-details">
                  <div class="file-name">
                    {{ fileInfo.barangay_clearance.name }}
                  </div>
                  <div class="file-meta">
                    <span class="file-size">{{
                      formatFileSize(fileInfo.barangay_clearance.size)
                    }}</span>
                    <span class="file-type">{{
                      getFileType(fileInfo.barangay_clearance.type)
                    }}</span>
                  </div>
                </div>
                <button
                  type="button"
                  @click.stop="removeFile('barangay_clearance')"
                  class="remove-file-btn"
                  title="Remove file"
                >
                  ×
                </button>
              </div>
            </div>
            <input
              type="file"
              @change="handleFileUpload($event, 'barangay_clearance')"
              accept=".jpg,.jpeg,.png,.pdf,image/*"
              class="file-input-hidden"
              ref="barangay_clearanceInput"
              :class="{ error: errors.barangay_clearance }"
            />
          </div>
          <div v-if="errors.barangay_clearance" class="error-message">
            {{ errors.barangay_clearance }}
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Mayor's Permit Number *</label>
          <input
            v-model="formData.mayorPermitNumber"
            type="text"
            placeholder="Enter Mayor's Permit Number"
            class="form-input"
            :class="{ error: errors.mayorPermitNumber }"
          />
          <div v-if="errors.mayorPermitNumber" class="error-message">
            {{ errors.mayorPermitNumber }}
          </div>
          <div class="file-upload-container">
            <div
              class="file-drop-zone"
              @click="$refs.mayor_permitInput.click()"
              @dragover.prevent="handleDragOver"
              @dragleave.prevent="handleDragLeave"
              @drop.prevent="handleDrop($event, 'mayor_permit')"
              :class="{
                'drag-over': dragOver.mayor_permit,
                'has-file': fileInfo.mayor_permit,
              }"
            >
              <div v-if="!fileInfo.mayor_permit" class="upload-placeholder">
                <svg class="upload-icon" viewBox="0 0 24 24">
                  <path
                    d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"
                  />
                </svg>
                <p class="upload-text">Drop file here or click to upload</p>
                <p class="upload-hint">
                  Max size: 10MB • Formats: JPG, PNG, PDF
                </p>
              </div>
              <div v-else class="file-preview">
                <div class="preview-icon">
                  <span v-if="isImage(fileInfo.mayor_permit.type)">🏢</span>
                  <span v-else>📄</span>
                </div>
                <div class="file-details">
                  <div class="file-name">{{ fileInfo.mayor_permit.name }}</div>
                  <div class="file-meta">
                    <span class="file-size">{{
                      formatFileSize(fileInfo.mayor_permit.size)
                    }}</span>
                    <span class="file-type">{{
                      getFileType(fileInfo.mayor_permit.type)
                    }}</span>
                  </div>
                </div>
                <button
                  type="button"
                  @click.stop="removeFile('mayor_permit')"
                  class="remove-file-btn"
                  title="Remove file"
                >
                  ×
                </button>
              </div>
            </div>
            <input
              type="file"
              @change="handleFileUpload($event, 'mayor_permit')"
              accept=".jpg,.jpeg,.png,.pdf,image/*"
              class="file-input-hidden"
              ref="mayor_permitInput"
              :class="{ error: errors.mayor_permit }"
            />
          </div>
          <div v-if="errors.mayor_permit" class="error-message">
            {{ errors.mayor_permit }}
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">BIR Registration / TIN *</label>
          <input
            v-model="formData.birTin"
            type="text"
            placeholder="Enter TIN"
            class="form-input"
            :class="{ error: errors.birTin }"
          />
          <div v-if="errors.birTin" class="error-message">
            {{ errors.birTin }}
          </div>
        </div>

        <div class="info-box">
          <p>
            💡 Providing business registration documents helps build trust with
            customers and may unlock additional features.
          </p>
        </div>
      </div>

      <!-- Step 4: Legal & Optional Info (UNCHANGED) -->
      <div v-if="currentStep === 4" class="form-step">
        <h2 class="step-title">Legal Agreements & Optional Info</h2>

        <div class="legal-box">
          <label class="legal-label">
            <input
              v-model="formData.acceptTerms"
              type="checkbox"
              class="checkbox-input"
              :class="{ error: errors.acceptTerms }"
            />
            <span
              >I accept the
              <a href="#" @click.prevent="showModal('terms')" class="link"
                >Terms & Conditions</a
              >
              *</span
            >
          </label>
          <div v-if="errors.acceptTerms" class="error-message">
            {{ errors.acceptTerms }}
          </div>

          <label class="legal-label">
            <input
              v-model="formData.acceptVendorAgreement"
              type="checkbox"
              class="checkbox-input"
              :class="{ error: errors.acceptVendorAgreement }"
            />
            <span
              >I accept the
              <a
                href="#"
                @click.prevent="showModal('vendorAgreement')"
                class="link"
                >Vendor Agreement</a
              >
              *</span
            >
          </label>
          <div v-if="errors.acceptVendorAgreement" class="error-message">
            {{ errors.acceptVendorAgreement }}
          </div>

          <label class="legal-label">
            <input
              v-model="formData.acceptDataPrivacy"
              type="checkbox"
              class="checkbox-input"
              :class="{ error: errors.acceptDataPrivacy }"
            />
            <span
              >I consent to the
              <a href="#" @click.prevent="showModal('privacy')" class="link"
                >Data Privacy Policy</a
              >
              *</span
            >
          </label>
          <div v-if="errors.acceptDataPrivacy" class="error-message">
            {{ errors.acceptDataPrivacy }}
          </div>
        </div>

        <div class="section-divider">
          <h3 class="section-title">Optional Information</h3>
        </div>

        <div class="input-group">
          <label class="input-label">Store Logo</label>
          <div class="file-upload-container">
            <div
              class="file-drop-zone"
              @click="$refs.store_logoInput.click()"
              @dragover.prevent="handleDragOver"
              @dragleave.prevent="handleDragLeave"
              @drop.prevent="handleDrop($event, 'store_logo')"
              :class="{
                'drag-over': dragOver.store_logo,
                'has-file': fileInfo.store_logo,
              }"
            >
              <div v-if="!fileInfo.store_logo" class="upload-placeholder">
                <svg class="upload-icon" viewBox="0 0 24 24">
                  <path
                    d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"
                  />
                </svg>
                <p class="upload-text">Drop logo here or click to upload</p>
                <p class="upload-hint">Max size: 2MB • Formats: JPG, PNG</p>
              </div>
              <div v-else class="file-preview">
                <div class="preview-icon">
                  <span>🏪</span>
                </div>
                <div class="file-details">
                  <div class="file-name">{{ fileInfo.store_logo.name }}</div>
                  <div class="file-meta">
                    <span class="file-size">{{
                      formatFileSize(fileInfo.store_logo.size)
                    }}</span>
                    <span class="file-type">{{
                      getFileType(fileInfo.store_logo.type)
                    }}</span>
                  </div>
                </div>
                <button
                  type="button"
                  @click.stop="removeFile('store_logo')"
                  class="remove-file-btn"
                  title="Remove file"
                >
                  ×
                </button>
              </div>
            </div>
            <input
              type="file"
              @change="handleFileUpload($event, 'store_logo')"
              accept=".jpg,.jpeg,.png,image/*"
              class="file-input-hidden"
              ref="store_logoInput"
            />
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Portfolio / Sample Photos</label>
          <div class="file-upload-container">
            <div
              class="file-drop-zone"
              @click="$refs.portfolio_photosInput.click()"
              @dragover.prevent="handleDragOver"
              @dragleave.prevent="handleDragLeave"
              @drop.prevent="handleDrop($event, 'portfolio_photos')"
              :class="{
                'drag-over': dragOver.portfolio_photos,
                'has-file':
                  fileInfo.portfolio_photos &&
                  fileInfo.portfolio_photos.length > 0,
              }"
            >
              <div
                v-if="
                  !fileInfo.portfolio_photos ||
                  fileInfo.portfolio_photos.length === 0
                "
                class="upload-placeholder"
              >
                <svg class="upload-icon" viewBox="0 0 24 24">
                  <path
                    d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"
                  />
                </svg>
                <p class="upload-text">Drop photos here or click to upload</p>
                <p class="upload-hint">
                  Max size: 5MB each • Formats: JPG, PNG
                </p>
              </div>
              <div v-else class="file-preview multiple">
                <div class="preview-icon">
                  <span>🖼️</span>
                </div>
                <div class="file-details">
                  <div class="file-name">
                    {{ fileInfo.portfolio_photos.length }} photo(s) selected
                  </div>
                  <div class="file-meta">
                    <span class="file-size"
                      >{{
                        formatFileSize(
                          fileInfo.portfolio_photos.reduce(
                            (sum, file) => sum + file.size,
                            0,
                          ),
                        )
                      }}
                      total</span
                    >
                  </div>
                  <div class="file-list">
                    <div
                      v-for="(file, index) in fileInfo.portfolio_photos"
                      :key="index"
                      class="file-item"
                    >
                      <span class="file-item-name">{{ file.name }}</span>
                      <span class="file-item-size">{{
                        formatFileSize(file.size)
                      }}</span>
                      <button
                        type="button"
                        @click.stop="removePortfolioFile(index)"
                        class="remove-file-item"
                        title="Remove file"
                      >
                        ×
                      </button>
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  @click.stop="removeFile('portfolio_photos')"
                  class="remove-file-btn"
                  title="Remove all files"
                >
                  ×
                </button>
              </div>
            </div>
            <input
              type="file"
              @change="handleFileUpload($event, 'portfolio_photos')"
              accept=".jpg,.jpeg,.png,image/*"
              class="file-input-hidden"
              ref="portfolio_photosInput"
              multiple
            />
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Facebook Page Link</label>
          <input
            v-model="formData.facebookPage"
            type="url"
            placeholder="https://facebook.com/yourpage"
            class="form-input"
          />
        </div>

        <div class="input-group">
          <label class="input-label">Instagram Page Link</label>
          <input
            v-model="formData.instagramPage"
            type="url"
            placeholder="https://instagram.com/yourpage"
            class="form-input"
          />
        </div>
      </div>

      <!-- Step 5: Review & Confirm (UNCHANGED) -->
      <div v-if="currentStep === 5" class="form-step review-step">
        <h2 class="step-title">Review Your Application</h2>
        <p class="subtitle-text">
          Please review all information before submitting
        </p>

        <div class="review-section">
          <h3 class="review-section-title">Store Information</h3>
          <div class="review-item">
            <strong>Store Name:</strong> {{ formData.storeName }}
          </div>
          <div class="review-item">
            <strong>Business Type:</strong>
            {{ formatBusinessType(formData.businessType) }}
          </div>
          <div class="review-item">
            <strong>Store Address:</strong> {{ formData.storeAddress }}
          </div>
          <div class="review-item">
            <strong>Service Areas:</strong> {{ formData.serviceAreas }}
          </div>
          <div class="review-item">
            <strong>Operating Hours:</strong> {{ formData.operatingHours }}
          </div>
        </div>

        <div class="review-section">
          <h3 class="review-section-title">Owner Information</h3>
          <div class="review-item">
            <strong>Owner Name:</strong> {{ formData.ownerName }}
          </div>
          <div class="review-item">
            <strong>Contact:</strong> {{ formData.contactNumber }}
          </div>
          <div class="review-item">
            <strong>Email:</strong> {{ formData.email }}
          </div>
        </div>

        <div class="review-section">
          <h3 class="review-section-title">Verification Documents</h3>
          <div class="review-item">
            <strong>Government ID No:</strong>
            {{ formData.governmentIdNumber || "Not provided" }}
          </div>
          <div class="review-item">
            <strong>Government ID:</strong>
            {{
              fileInfo.government_id
                ? fileInfo.government_id.name
                : "Not uploaded"
            }}
          </div>
          <div class="review-item">
            <strong>Selfie with ID:</strong>
            {{
              fileInfo.selfie_with_id
                ? fileInfo.selfie_with_id.name
                : "Not uploaded"
            }}
          </div>
          <div class="review-item">
            <strong>Proof of Address:</strong>
            {{
              fileInfo.proof_of_address
                ? fileInfo.proof_of_address.name
                : "Not uploaded"
            }}
          </div>
        </div>

        <div
          class="review-section"
          v-if="
            formData.dtiNumber ||
            formData.secNumber ||
            formData.birTin ||
            fileInfo.barangay_clearance ||
            fileInfo.mayor_permit
          "
        >
          <h3 class="review-section-title">Business Registration</h3>
          <div class="review-item" v-if="formData.dtiNumber">
            <strong>DTI Number:</strong> {{ formData.dtiNumber }}
          </div>
          <div class="review-item" v-if="formData.secNumber">
            <strong>SEC Number:</strong> {{ formData.secNumber }}
          </div>
          <div class="review-item" v-if="formData.barangayClearanceNumber">
            <strong>Barangay Clearance No:</strong>
            {{ formData.barangayClearanceNumber }}
          </div>
          <div class="review-item" v-if="fileInfo.barangay_clearance">
            <strong>Barangay Clearance:</strong>
            {{ fileInfo.barangay_clearance.name }}
          </div>
          <div class="review-item" v-if="formData.mayorPermitNumber">
            <strong>Mayor's Permit No:</strong>
            {{ formData.mayorPermitNumber }}
          </div>
          <div class="review-item" v-if="fileInfo.mayor_permit">
            <strong>Mayor's Permit:</strong> {{ fileInfo.mayor_permit.name }}
          </div>
          <div class="review-item" v-if="formData.birTin">
            <strong>TIN:</strong> {{ formData.birTin }}
          </div>
        </div>

        <div
          class="review-section"
          v-if="
            fileInfo.store_logo ||
            (fileInfo.portfolio_photos &&
              fileInfo.portfolio_photos.length > 0) ||
            formData.facebookPage ||
            formData.instagramPage
          "
        >
          <h3 class="review-section-title">Additional Information</h3>
          <div class="review-item" v-if="fileInfo.store_logo">
            <strong>Store Logo:</strong> {{ fileInfo.store_logo.name }}
          </div>
          <div
            class="review-item"
            v-if="
              fileInfo.portfolio_photos && fileInfo.portfolio_photos.length > 0
            "
          >
            <strong>Portfolio Photos:</strong>
            {{ fileInfo.portfolio_photos.length }} file(s)
          </div>
          <div class="review-item" v-if="formData.facebookPage">
            <strong>Facebook:</strong> {{ formData.facebookPage }}
          </div>
          <div class="review-item" v-if="formData.instagramPage">
            <strong>Instagram:</strong> {{ formData.instagramPage }}
          </div>
        </div>

        <div class="legal-box">
          <label class="legal-label">
            <input
              v-model="formData.confirmAccuracy"
              type="checkbox"
              class="checkbox-input"
              :class="{ error: errors.confirmAccuracy }"
            />
            <span
              >I confirm that all information provided is accurate and complete
              *</span
            >
          </label>
          <div v-if="errors.confirmAccuracy" class="error-message">
            {{ errors.confirmAccuracy }}
          </div>
        </div>
      </div>

      <!-- Navigation Buttons -->
      <div class="button-group">
        <button
          v-if="currentStep < totalSteps"
          @click="nextStep"
          type="button"
          class="action-btn"
          :disabled="isSubmitting"
        >
          {{ currentStep === 4 ? "Review Application" : "Next" }}
        </button>

        <button
          v-else
          @click="handleSubmit"
          type="button"
          class="action-btn submit-btn"
          :disabled="isSubmitting"
        >
          <span v-if="isSubmitting">Submitting...</span>
          <span v-else>Submit Registration</span>
        </button>
      </div>

      <!-- Progress Text -->
      <div class="progress-text">
        <p>Step {{ currentStep }} of {{ totalSteps }}</p>
      </div>

      <!-- Legal Modals -->
      <div v-if="activeModal" class="modal-overlay" @click="closeActiveModal">
        <div class="modal legal-modal" @click.stop>
          <button @click="closeActiveModal" class="close-modal">×</button>
          <div class="modal-content" v-html="modalContent"></div>
          <div class="modal-actions">
            <button @click="closeActiveModal" class="action-btn">Close</button>
          </div>
        </div>
      </div>

      <!-- Application Success Modal -->
      <div v-if="showApplicationModal" class="modal-overlay">
        <div class="modal">
          <h3>Application Submitted Successfully!</h3>
          <p>
            Your application ID: <strong>{{ applicationId }}</strong>
          </p>
          <p>Please save this ID for future reference.</p>
          <p>You will receive an email confirmation shortly.</p>
          <div class="modal-actions">
            <button @click="goToStatusPage" class="action-btn">
              Check Status
            </button>
            <button @click="closeModal" class="action-btn secondary">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  ref,
  reactive,
  onMounted,
  onUnmounted,
  computed,
  watchEffect,
} from "vue";
import { useRouter } from "vue-router";
import api from "../../plugins/axios";
import { toast } from "vue3-toastify";

const router = useRouter();
const currentStep = ref(1);
const totalSteps = 5;
const isSubmitting = ref(false);
const showApplicationModal = ref(false);
const applicationId = ref("");
const saveInterval = ref(null);
const activeModal = ref(null);

// ─────────────────────────────────────────────────────────────────────────────
// Form data — identical shape; serviceAreas & operatingHours now auto-updated
// via watchEffect above instead of manual text entry.
// ─────────────────────────────────────────────────────────────────────────────
const formData = reactive({
  // Step 1
  storeName: "",
  storeDescription: "",
  businessType: "",
  storeAddress: "", // Populated by dropdown
  serviceAreas: "", // Auto-populated via watchEffect ← selectedServiceAreas
  operatingHours: "", // Auto-populated via watchEffect ← operatingHoursConfig

  // Step 2
  ownerName: "",
  position: "",
  contactNumber: "",
  email: "",
  governmentIdNumber: "",

  // Step 3
  dtiNumber: "",
  secNumber: "",
  barangayClearanceNumber: "",
  mayorPermitNumber: "",
  birTin: "",

  // Step 4
  acceptTerms: false,
  acceptVendorAgreement: false,
  acceptDataPrivacy: false,
  facebookPage: "",
  instagramPage: "",

  // Step 5
  confirmAccuracy: false,
});

const steps = [
  { number: 1, title: "Store" },
  { number: 2, title: "Owner" },
  { number: 3, title: "Business" },
  { number: 4, title: "Legal" },
  { number: 5, title: "Review" },
];

// ─────────────────────────────────────────────────────────────────────────────
// NEW: Cavite location data (cities and municipalities)
// ─────────────────────────────────────────────────────────────────────────────
const caviteCities = [
  "Bacoor, Cavite",
  "Imus, Cavite",
  "Dasmariñas, Cavite",
  "General Trias, Cavite",
  "Trece Martires, Cavite",
  "Tagaytay, Cavite",
  "Carmona, Cavite",
  "Cavite City, Cavite",
];

const caviteMunicipalities = [
  "Silang, Cavite",
  "General Mariano Alvarez, Cavite",
  "Kawit, Cavite",
  "Noveleta, Cavite",
  "Rosario, Cavite",
  "Tanza, Cavite",
  "Naic, Cavite",
  "Maragondon, Cavite",
  "Ternate, Cavite",
  "Indang, Cavite",
  "Amadeo, Cavite",
  "Mendez, Cavite",
  "Alfonso, Cavite",
  "Magallanes, Cavite",
  "General Emilio Aguinaldo, Cavite",
];

// ─────────────────────────────────────────────────────────────────────────────
// NEW: Service area options (restricted list only)
// ─────────────────────────────────────────────────────────────────────────────
const serviceAreaOptions = [
  { value: "Region IV-A (CALABARZON)", label: "Region IV-A (CALABARZON)" },
  {
    value: "NCR (National Capital Region)",
    label: "NCR (National Capital Region)",
  },
];

// Reactive array that drives the checkboxes; synced → formData.serviceAreas
const selectedServiceAreas = ref([]);

// ─────────────────────────────────────────────────────────────────────────────
// NEW: Operating hours config — drives the day/time UI; synced → formData.operatingHours
// ─────────────────────────────────────────────────────────────────────────────
const daysOfWeek = [
  "Monday",
  "Tuesday",
  "Wednesday",
  "Thursday",
  "Friday",
  "Saturday",
  "Sunday",
];

const operatingHoursConfig = reactive({
  days: [], // Array of selected day strings
  startTime: "", // "HH:MM" 24-hour format
  endTime: "", // "HH:MM" 24-hour format
});

// ── Quick-select helpers ───────────────────────────────────────────────────────
const selectWeekdays = () => {
  operatingHoursConfig.days = [
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
  ];
};
const selectWeekends = () => {
  operatingHoursConfig.days = ["Saturday", "Sunday"];
};
const selectAllDays = () => {
  operatingHoursConfig.days = [...daysOfWeek];
};
const clearDays = () => {
  operatingHoursConfig.days = [];
};

// ── Time formatter: "HH:MM" → "H:MM AM/PM" ───────────────────────────────────
const formatTime = (time) => {
  if (!time) return "";
  const [h, m] = time.split(":").map(Number);
  const ampm = h >= 12 ? "PM" : "AM";
  const h12 = h % 12 || 12;
  return `${h12}:${String(m).padStart(2, "0")} ${ampm}`;
};

// ── Day formatter: selected days array → readable string ─────────────────────
const formatDays = (selectedDays) => {
  if (!selectedDays || selectedDays.length === 0) return "";
  const ordered = daysOfWeek.filter((d) => selectedDays.includes(d));
  if (ordered.length === 7) return "Everyday";

  const abbr = (d) => d.substring(0, 3);
  const indices = ordered.map((d) => daysOfWeek.indexOf(d));

  // Group consecutive indices into ranges
  const ranges = [];
  let rangeStart = 0;
  for (let i = 1; i <= indices.length; i++) {
    if (i === indices.length || indices[i] !== indices[i - 1] + 1) {
      const slice = indices.slice(rangeStart, i);
      if (slice.length >= 3) {
        ranges.push(
          `${abbr(daysOfWeek[slice[0]])}-${abbr(daysOfWeek[slice[slice.length - 1]])}`,
        );
      } else {
        slice.forEach((idx) => ranges.push(abbr(daysOfWeek[idx])));
      }
      rangeStart = i;
    }
  }
  return ranges.join(", ");
};

// ── Keep formData.serviceAreas in sync with checkbox selections ───────────────
watchEffect(() => {
  formData.serviceAreas = selectedServiceAreas.value.join(", ");
});

// ── Keep formData.operatingHours in sync with day/time config ─────────────────
watchEffect(() => {
  const { days, startTime, endTime } = operatingHoursConfig;
  if (days.length > 0 && startTime && endTime) {
    formData.operatingHours = `${formatDays(days)}: ${formatTime(startTime)} – ${formatTime(endTime)}`;
  } else {
    formData.operatingHours = "";
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// File input refs (unchanged)
// ─────────────────────────────────────────────────────────────────────────────
const government_idInput = ref(null);
const selfie_with_idInput = ref(null);
const proof_of_addressInput = ref(null);
const barangay_clearanceInput = ref(null);
const mayor_permitInput = ref(null);
const store_logoInput = ref(null);
const portfolio_photosInput = ref(null);

// Drag state tracking (unchanged)
const dragOver = reactive({});

// Store File objects (unchanged)
const fileObjects = reactive({
  government_id: null,
  selfie_with_id: null,
  proof_of_address: null,
  barangay_clearance: null,
  mayor_permit: null,
  store_logo: null,
  portfolio_photos: [],
});

const errors = reactive({});
const fileInfo = reactive({});

const hasStoredFileInfo = (fieldName) => {
  if (fieldName === "portfolio_photos") {
    return Array.isArray(fileInfo[fieldName]) && fileInfo[fieldName].length > 0;
  }

  return !!fileInfo[fieldName];
};

const hasLiveFileObject = (fieldName) => {
  if (fieldName === "portfolio_photos") {
    return (
      Array.isArray(fileObjects[fieldName]) &&
      fileObjects[fieldName].some((file) => file instanceof File)
    );
  }

  return fileObjects[fieldName] instanceof File;
};

const hasSelectedFile = (fieldName) =>
  hasLiveFileObject(fieldName) || hasStoredFileInfo(fieldName);

const toCamelCase = (value) =>
  value.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase());

const getStepForErrorField = (field) => {
  if (
    [
      "storeName",
      "storeDescription",
      "businessType",
      "storeAddress",
      "serviceAreas",
      "operatingHours",
    ].includes(field)
  ) {
    return 1;
  }

  if (
    [
      "ownerName",
      "position",
      "contactNumber",
      "email",
      "governmentId",
      "governmentIdNumber",
      "selfieWithId",
      "proofOfAddress",
      "government_id",
      "selfie_with_id",
      "proof_of_address",
    ].includes(field)
  ) {
    return 2;
  }

  if (
    [
      "dtiNumber",
      "secNumber",
      "birTin",
      "barangayClearanceNumber",
      "mayorPermitNumber",
      "barangayClearance",
      "mayorPermit",
      "barangay_clearance",
      "mayor_permit",
    ].includes(field)
  ) {
    return 3;
  }

  if (
    [
      "acceptTerms",
      "acceptVendorAgreement",
      "acceptDataPrivacy",
      "storeLogo",
      "portfolioPhotos",
      "facebookPage",
      "instagramPage",
      "store_logo",
      "portfolio_photos",
    ].includes(field)
  ) {
    return 4;
  }

  if (["confirmAccuracy"].includes(field)) {
    return 5;
  }

  return null;
};

const applyBackendErrors = (backendErrors = {}) => {
  Object.keys(errors).forEach((key) => delete errors[key]);

  const errorFields = Object.keys(backendErrors);

  errorFields.forEach((errorKey) => {
    const camelKey = toCamelCase(errorKey);
    const messages = backendErrors[errorKey];
    const firstMessage = Array.isArray(messages) ? messages[0] : messages;

    errors[camelKey] = firstMessage;

    if (camelKey !== errorKey && !errors[errorKey]) {
      errors[errorKey] = firstMessage;
    }
  });

  const firstErrorField = errorFields[0];
  if (!firstErrorField) return null;

  const mappedStep =
    getStepForErrorField(toCamelCase(firstErrorField)) ??
    getStepForErrorField(firstErrorField);

  if (mappedStep) {
    currentStep.value = mappedStep;
  }

  window.scrollTo({ top: 0, behavior: "smooth" });

  return Array.isArray(backendErrors[firstErrorField])
    ? backendErrors[firstErrorField][0]
    : backendErrors[firstErrorField];
};

// ─────────────────────────────────────────────────────────────────────────────
// Legal Content (unchanged)
// ─────────────────────────────────────────────────────────────────────────────
const legalContent = {
  terms: `
    <h2>Vendor Terms and Conditions</h2>
    <p>This Vendor Terms and Conditions ("Agreement") governs the relationship between BloomCraft ("Platform", "We", "Us") and the flower shop owner or seller ("Vendor", "You") who wishes to sell floral products on the Platform.</p>
    <p>By registering as a Vendor, you agree to comply with and be bound by these Terms.</p>
    <h3>1. VENDOR ELIGIBILITY</h3>
    <p>1.1 Vendors must be at least eighteen (18) years old.</p>
    <p>1.2 Vendors must operate a legitimate flower shop or floral business within the Philippines.</p>
    <p>1.3 Vendors must possess valid business registration and permits, including but not limited to DTI or SEC registration, Barangay Clearance, Mayor's Permit, and BIR registration.</p>
    <h3>2. VENDOR ACCOUNT REGISTRATION</h3>
    <p>2.1 Vendors must provide complete and accurate information during registration.</p>
    <p>2.2 Each Vendor is allowed only one account unless otherwise approved by the Platform.</p>
    <p>2.3 Vendors are responsible for all activities conducted under their account.</p>
    <h3>3. PRODUCTS AND LISTINGS</h3>
    <p>3.1 Vendors may sell flowers, floral arrangements, plants, and related items approved by the Platform.</p>
    <p>3.2 Product descriptions, prices, and images must be accurate and truthful.</p>
    <p>3.3 The Platform reserves the right to remove or suspend any listing that is misleading or violates quality standards.</p>
    <h3>4. PRICING, COMMISSION, AND FEES</h3>
    <p>4.1 Vendors may set their own prices.</p>
    <p>4.2 The Platform may charge commissions or service fees per sale.</p>
    <p>4.3 All applicable fees will be disclosed prior to selling.</p>
    <h3>5. ORDER FULFILLMENT</h3>
    <p>5.1 Vendors are responsible for preparing and fulfilling orders on time.</p>
    <p>5.2 Vendors must ensure freshness, quality, and proper packaging of flowers.</p>
    <p>5.3 Failure to fulfill orders may result in penalties or account suspension.</p>
    <h3>6. DELIVERY RESPONSIBILITIES</h3>
    <p>6.1 Vendors must deliver orders within the agreed timeframe.</p>
    <p>6.2 Vendors are responsible for errors, delays, or damages caused by improper handling.</p>
    <h3>7. CANCELLATIONS AND REFUNDS</h3>
    <p>7.1 Flowers are perishable goods; returns may be limited.</p>
    <p>7.2 Refunds due to Vendor fault may be deducted from Vendor earnings.</p>
    <h3>8. PAYMENTS AND PAYOUTS</h3>
    <p>8.1 Payments from customers are processed through the Platform.</p>
    <p>8.2 Vendor payouts shall follow the Platform's payout schedule.</p>
    <p>8.3 Vendors are responsible for providing correct payout details.</p>
    <h3>9. QUALITY STANDARDS</h3>
    <p>9.1 Vendors must maintain high product and service quality.</p>
    <p>9.2 Repeated complaints or poor ratings may result in account termination.</p>
    <h3>10. COMPLIANCE WITH PHILIPPINE LAWS</h3>
    <p>10.1 Vendors shall comply with all applicable Philippine laws including the Consumer Act of the Philippines and the Data Privacy Act of 2012.</p>
    <h3>11. INTELLECTUAL PROPERTY</h3>
    <p>11.1 Vendors retain ownership of their content.</p>
    <p>11.2 Vendors grant the Platform a non-exclusive license to use content for promotional purposes.</p>
    <h3>12. PROHIBITED ACTIVITIES</h3>
    <p>12.1 Vendors must not engage in fraudulent or deceptive practices.</p>
    <p>12.2 Vendors must not contact customers outside the Platform without permission.</p>
    <h3>13. ACCOUNT TERMINATION</h3>
    <p>13.1 The Platform may suspend or terminate Vendor accounts for violations of these Terms.</p>
    <h3>14. LIMITATION OF LIABILITY</h3>
    <p>14.1 The Platform shall not be liable for losses arising from Vendor actions.</p>
    <h3>15. INDEMNIFICATION</h3>
    <p>15.1 Vendors agree to indemnify and hold harmless the Platform from any claims arising from Vendor activities.</p>
    <h3>16. AMENDMENTS</h3>
    <p>16.1 The Platform may amend these Terms at any time.</p>
    <h3>17. GOVERNING LAW</h3>
    <p>17.1 These Terms shall be governed by the laws of the Republic of the Philippines.</p>
    <h3>18. CONTACT INFORMATION</h3>
    <p><strong>Email:</strong> sample@email.com</p>
    <p><strong>Business Name:</strong> BloomCraft</p>
  `,
  vendorAgreement: `
    <h2>Vendor Agreement</h2>
    <p>This Vendor Agreement ("Agreement") governs the relationship between BloomCraft ("Platform") and any flower shop owner or seller ("Vendor") who registers, accesses, or sells products on the Platform.</p>
    <p>By creating a Vendor account, checking the box indicating acceptance, and using the Platform, the Vendor confirms that they have read, understood, and agreed to be legally bound by this Agreement and the Vendor Terms and Conditions.</p>
    <h3>1. PURPOSE OF THE AGREEMENT</h3>
    <p>The Platform provides a web-based marketplace exclusively for flower shops operating within Cavite, Philippines. This Agreement defines the rights, responsibilities, and obligations of the Vendor and the Platform to ensure transparent and fair transactions.</p>
    <h3>2. VENDOR ELIGIBILITY AND REPRESENTATION</h3>
    <p>By accepting this Agreement, the Vendor represents that:</p>
    <ul>
      <li>The Vendor is at least eighteen (18) years old</li>
      <li>The Vendor operates a legitimate flower shop within Cavite</li>
      <li>The Vendor holds all required Philippine business registrations and permits</li>
      <li>All information submitted to the Platform is accurate and truthful</li>
    </ul>
    <h3>3. INDEPENDENT BUSINESS RELATIONSHIP</h3>
    <p>The Vendor is an independent business owner. Nothing in this Agreement shall be construed as creating an employer-employee, partnership, joint venture, or agency relationship between the Vendor and the Platform.</p>
    <h3>4. PRODUCT LISTINGS AND PRICING</h3>
    <p>Vendors may sell flowers, floral arrangements, plants, and related items approved by the Platform. Vendors may set their own prices. All listings must be accurate, truthful, and compliant with Platform standards.</p>
    <h3>5. ORDERS AND FULFILLMENT</h3>
    <p>Vendors are solely responsible for preparing and fulfilling customer orders in a timely manner while ensuring freshness, quality, and proper packaging.</p>
    <h3>6. DELIVERY RESPONSIBILITIES</h3>
    <p>Delivery responsibilities shall follow the Platform's Vendor Terms and Conditions. The Platform shall not be liable for delays, damages, or losses arising from delivery-related issues.</p>
    <h3>7. PAYMENTS, COMMISSIONS, AND PAYOUTS</h3>
    <p>Customer payments are processed through the Platform. The Platform may deduct applicable commissions, service fees, or subscription fees before releasing Vendor payouts, in accordance with Platform policies.</p>
    <h3>8. CANCELLATIONS AND REFUNDS</h3>
    <p>Flowers are perishable goods and returns may be limited. Refunds arising from Vendor fault may be deducted from Vendor earnings.</p>
    <h3>9. QUALITY STANDARDS AND CUSTOMER FEEDBACK</h3>
    <p>Vendors must maintain high product and service quality. Repeated complaints, poor ratings, or violations of Platform policies may result in suspension or termination.</p>
    <h3>10. INTELLECTUAL PROPERTY</h3>
    <p>Vendors retain ownership of their content but grant the Platform a non-exclusive, royalty-free license to use such content for marketing and promotional purposes.</p>
    <h3>11. PROHIBITED ACTIVITIES</h3>
    <p>Vendors shall not engage in fraudulent, deceptive, or unauthorized activities, including contacting customers outside the Platform without permission.</p>
    <h3>12. TERMINATION</h3>
    <p>The Platform may suspend or terminate Vendor access for violations of this Agreement or the Vendor Terms and Conditions.</p>
    <h3>13. LIMITATION OF LIABILITY</h3>
    <p>The Platform shall not be liable for losses, damages, or claims arising from Vendor actions, products, or services.</p>
    <h3>14. INDEMNIFICATION</h3>
    <p>The Vendor agrees to indemnify and hold harmless the Platform from claims, liabilities, or damages arising from the Vendor's activities or violations of law.</p>
    <h3>15. COMPLIANCE WITH PHILIPPINE LAWS</h3>
    <p>Vendors shall comply with all applicable Philippine laws, including the Consumer Act of the Philippines and the Data Privacy Act of 2012.</p>
    <h3>16. GOVERNING LAW</h3>
    <p>This Agreement shall be governed by and construed in accordance with the laws of the Republic of the Philippines.</p>
    <h3>17. ACCEPTANCE AND EFFECTIVITY</h3>
    <p>This Agreement takes effect immediately upon the Vendor's electronic acceptance through the Platform. Continued use of the Platform constitutes ongoing acceptance of this Agreement.</p>
    <p><strong>Contact Information:</strong></p>
    <p>Email: sample@email.com</p>
    <p>Business Name: BloomCraft</p>
  `,
  privacy: `
    <h2>Data Privacy Policy</h2>
    <p>This Data Privacy Policy ("Policy") explains how BloomCraft ("Platform", "We", "Us") collects, uses, stores, shares, and protects personal data in compliance with the Data Privacy Act of 2012 (Republic Act No. 10173) and its Implementing Rules and Regulations.</p>
    <p>By accessing, registering, or using the Platform, and by checking the box indicating acceptance, you ("User") confirm that you have read, understood, and agreed to this Policy.</p>
    <h3>1. SCOPE AND APPLICABILITY</h3>
    <p>This Policy applies to all users of the Platform, including vendors, customers, delivery partners, and website visitors. Philippine law shall prevail in case of any conflict with other international standards.</p>
    <h3>2. GOVERNING LAW AND REFERENCE STANDARDS</h3>
    <p>This Policy is primarily governed by:</p>
    <ul>
      <li>Republic Act No. 10173 (Data Privacy Act of 2012)</li>
      <li>National Privacy Commission (NPC) issuances and guidelines</li>
    </ul>
    <h3>3. TYPES OF PERSONAL DATA COLLECTED</h3>
    <p>The Platform may collect the following:</p>
    <ul>
      <li>Personal Information (name, contact number, email address, business details)</li>
      <li>Sensitive Personal Information, where applicable and permitted by law</li>
      <li>Transaction and order information</li>
      <li>Technical data (IP address, device information, usage data)</li>
    </ul>
    <h3>4. PURPOSE OF DATA COLLECTION</h3>
    <p>Personal data is collected and processed for the following purposes:</p>
    <ul>
      <li>Account registration and verification</li>
      <li>Order processing and fulfillment</li>
      <li>Payment processing and payouts</li>
      <li>Customer support and communication</li>
      <li>Platform improvement and security</li>
      <li>Compliance with legal and regulatory obligations</li>
    </ul>
    <h3>5. DATA SUBJECT RIGHTS</h3>
    <p>In accordance with the Data Privacy Act, Users have the right to be informed, access their data, object to processing, rectify inaccuracies, erase or block data, and file a complaint with the National Privacy Commission.</p>
    <h3>6. CONTACT INFORMATION</h3>
    <p><strong>Email:</strong> sample@email.com</p>
    <p><strong>Business Name:</strong> BloomCraft</p>
  `,
};

const modalContent = computed(() => {
  return legalContent[activeModal.value] || "";
});

// Modal methods (unchanged)
const showModal = (type) => {
  activeModal.value = type;
  document.body.style.overflow = "hidden";
};

const closeActiveModal = () => {
  activeModal.value = null;
  document.body.style.overflow = "";
};

// ─────────────────────────────────────────────────────────────────────────────
// File handling methods (unchanged)
// ─────────────────────────────────────────────────────────────────────────────
const handleDragOver = (event) => {
  event.preventDefault();
  const target = event.currentTarget;
  if (target) target.classList.add("drag-over");
};

const handleDragLeave = (event) => {
  event.preventDefault();
  const target = event.currentTarget;
  if (target) target.classList.remove("drag-over");
};

const handleDrop = (event, fieldName) => {
  event.preventDefault();
  const target = event.currentTarget;
  if (target) target.classList.remove("drag-over");
  const files = event.dataTransfer.files;
  if (files && files.length > 0) {
    handleFileUpload({ target: { files } }, fieldName);
  }
};

const handleFileUpload = async (event, fieldName) => {
  const files = event.target.files;
  if (!files || files.length === 0) return;

  if (errors[fieldName]) delete errors[fieldName];

  try {
    if (fieldName !== "portfolio_photos") {
      const file = files[0];
      const validation = validateFile(file, fieldName);
      if (!validation.valid) {
        toast.error(validation.message);
        event.target.value = "";
        return;
      }
      fileObjects[fieldName] = file;
      fileInfo[fieldName] = {
        name: file.name,
        size: file.size,
        type: file.type,
        lastModified: file.lastModified,
        url: URL.createObjectURL(file),
      };
    } else {
      const uploadedFiles = Array.from(files);
      const validFiles = [];
      const fileInfos = [];

      for (const file of uploadedFiles) {
        const validation = validateFile(file, fieldName);
        if (validation.valid) {
          validFiles.push(file);
          fileInfos.push({
            name: file.name,
            size: file.size,
            type: file.type,
            url: URL.createObjectURL(file),
          });
        } else {
          toast.error(`File "${file.name}" rejected: ${validation.message}`);
        }
      }

      if (validFiles.length > 0) {
        if (fileObjects[fieldName] && fileObjects[fieldName].length > 0) {
          fileObjects[fieldName] = [...fileObjects[fieldName], ...validFiles];
          fileInfo[fieldName] = [...(fileInfo[fieldName] || []), ...fileInfos];
        } else {
          fileObjects[fieldName] = validFiles;
          fileInfo[fieldName] = fileInfos;
        }
      }
    }

    saveProgress();
  } catch (error) {
    console.error("Error uploading file:", error);
    toast.error("Error uploading file. Please try again.");
  }
};

const validateFile = (file, fieldName) => {
  const maxSizes = {
    government_id: 5 * 1024 * 1024,
    selfie_with_id: 5 * 1024 * 1024,
    proof_of_address: 5 * 1024 * 1024,
    barangay_clearance: 10 * 1024 * 1024,
    mayor_permit: 10 * 1024 * 1024,
    store_logo: 2 * 1024 * 1024,
    portfolio_photos: 5 * 1024 * 1024,
  };

  const allowedTypes = {
    government_id: ["image/jpeg", "image/jpg", "image/png", "application/pdf"],
    selfie_with_id: ["image/jpeg", "image/jpg", "image/png"],
    proof_of_address: [
      "image/jpeg",
      "image/jpg",
      "image/png",
      "application/pdf",
    ],
    barangay_clearance: [
      "image/jpeg",
      "image/jpg",
      "image/png",
      "application/pdf",
    ],
    mayor_permit: ["image/jpeg", "image/jpg", "image/png", "application/pdf"],
    store_logo: ["image/jpeg", "image/jpg", "image/png"],
    portfolio_photos: ["image/jpeg", "image/jpg", "image/png"],
  };

  const maxSize = maxSizes[fieldName] || 5 * 1024 * 1024;
  const allowed = allowedTypes[fieldName] || [
    "image/jpeg",
    "image/jpg",
    "image/png",
    "application/pdf",
  ];

  if (file.size > maxSize) {
    return {
      valid: false,
      message: `File size exceeds ${formatFileSize(maxSize)} limit`,
    };
  }

  const fileType = file.type.toLowerCase();
  const isAllowed = allowed.some((type) => {
    if (type === "image/jpeg" || type === "image/jpg") {
      return fileType === "image/jpeg" || fileType === "image/jpg";
    }
    return fileType === type;
  });

  if (!isAllowed) {
    const extensions = allowed
      .map((type) => {
        if (type === "image/jpeg" || type === "image/jpg") return "JPG";
        if (type === "image/png") return "PNG";
        if (type === "application/pdf") return "PDF";
        return type.split("/")[1].toUpperCase();
      })
      .join(", ");
    return { valid: false, message: `Only ${extensions} files are allowed` };
  }

  return { valid: true };
};

const isImage = (fileType) => fileType && fileType.startsWith("image/");

const getFileType = (fileType) => {
  if (!fileType) return "";
  if (fileType === "application/pdf") return "PDF";
  if (fileType.startsWith("image/")) {
    const type = fileType.split("/")[1].toUpperCase();
    return type === "JPEG" ? "JPG" : type;
  }
  return "File";
};

const removeFile = (fieldName) => {
  if (fileInfo[fieldName]) {
    if (Array.isArray(fileInfo[fieldName])) {
      fileInfo[fieldName].forEach((file) => {
        if (file.url) URL.revokeObjectURL(file.url);
      });
    } else if (fileInfo[fieldName].url) {
      URL.revokeObjectURL(fileInfo[fieldName].url);
    }
  }

  fileObjects[fieldName] = fieldName === "portfolio_photos" ? [] : null;
  delete fileInfo[fieldName];

  const inputRefs = {
    government_id: government_idInput,
    selfie_with_id: selfie_with_idInput,
    proof_of_address: proof_of_addressInput,
    barangay_clearance: barangay_clearanceInput,
    mayor_permit: mayor_permitInput,
    store_logo: store_logoInput,
    portfolio_photos: portfolio_photosInput,
  };

  if (inputRefs[fieldName] && inputRefs[fieldName].value) {
    inputRefs[fieldName].value.value = "";
  }

  saveProgress();
};

const removePortfolioFile = (index) => {
  if (fileInfo.portfolio_photos && fileInfo.portfolio_photos[index]) {
    if (fileInfo.portfolio_photos[index].url) {
      URL.revokeObjectURL(fileInfo.portfolio_photos[index].url);
    }
    fileInfo.portfolio_photos.splice(index, 1);
    fileObjects.portfolio_photos.splice(index, 1);
    if (fileInfo.portfolio_photos.length === 0) {
      removeFile("portfolio_photos");
    }
    saveProgress();
  }
};

const formatFileSize = (bytes) => {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
};

const formatBusinessType = (type) => {
  const types = {
    individual: "Individual / Sole Proprietor",
    partnership: "Partnership",
    corporation: "Corporation",
  };
  return types[type] || type;
};

// ─────────────────────────────────────────────────────────────────────────────
// UPDATED: validateStep — Step 1 now validates dropdown + checkboxes + time
// All other steps are identical to the original.
// ─────────────────────────────────────────────────────────────────────────────
const validateStep = (step) => {
  Object.keys(errors).forEach((key) => delete errors[key]);
  let isValid = true;

  switch (step) {
    case 1:
      if (!formData.storeName.trim()) {
        errors.storeName = "Store name is required";
        isValid = false;
      }
      if (!formData.storeDescription.trim()) {
        errors.storeDescription = "Store description is required";
        isValid = false;
      }
      if (!formData.businessType) {
        errors.businessType = "Business type is required";
        isValid = false;
      }
      // NEW: dropdown address
      if (!formData.storeAddress) {
        errors.storeAddress = "Please select your city / municipality";
        isValid = false;
      }
      // NEW: service area checkboxes
      if (selectedServiceAreas.value.length === 0) {
        errors.serviceAreas = "Please select at least one service area";
        isValid = false;
      }
      // NEW: operating hours (days + times)
      if (operatingHoursConfig.days.length === 0) {
        errors.operatingHours = "Please select at least one operating day";
        isValid = false;
      } else if (!operatingHoursConfig.startTime) {
        errors.operatingHours = "Please set an opening time";
        isValid = false;
      } else if (!operatingHoursConfig.endTime) {
        errors.operatingHours = "Please set a closing time";
        isValid = false;
      } else if (
        operatingHoursConfig.startTime >= operatingHoursConfig.endTime
      ) {
        errors.operatingHours = "Closing time must be after opening time";
        isValid = false;
      }
      break;

    case 2:
      if (!formData.ownerName.trim()) {
        errors.ownerName = "Owner name is required";
        isValid = false;
      }
      if (!formData.position.trim()) {
        errors.position = "Position is required";
        isValid = false;
      }
      if (!formData.contactNumber.trim()) {
        errors.contactNumber = "Contact number is required";
        isValid = false;
      }
      if (!formData.email.trim()) {
        errors.email = "Email is required";
        isValid = false;
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
        errors.email = "Please enter a valid email address";
        isValid = false;
      }
      if (!formData.governmentIdNumber.trim()) {
        errors.governmentIdNumber = "Government ID number is required";
        isValid = false;
      }
      if (!hasSelectedFile("government_id")) {
        errors.government_id = "Government ID is required";
        isValid = false;
      }
      if (!hasSelectedFile("selfie_with_id")) {
        errors.selfie_with_id = "Selfie with ID is required";
        isValid = false;
      }
      if (!hasSelectedFile("proof_of_address")) {
        errors.proof_of_address = "Proof of address is required";
        isValid = false;
      }
      break;

    case 3:
      if (!formData.dtiNumber.trim()) {
        errors.dtiNumber = "DTI number is required";
        isValid = false;
      }
      if (!formData.secNumber.trim()) {
        errors.secNumber = "SEC number is required";
        isValid = false;
      }
      if (!formData.barangayClearanceNumber.trim()) {
        errors.barangayClearanceNumber = "Barangay Clearance number is required";
        isValid = false;
      }
      if (!hasSelectedFile("barangay_clearance")) {
        errors.barangay_clearance = "Barangay Clearance document is required";
        isValid = false;
      }
      if (!formData.mayorPermitNumber.trim()) {
        errors.mayorPermitNumber = "Mayor's Permit number is required";
        isValid = false;
      }
      if (!hasSelectedFile("mayor_permit")) {
        errors.mayor_permit = "Mayor's Permit document is required";
        isValid = false;
      }
      if (!formData.birTin.trim()) {
        errors.birTin = "BIR Registration / TIN is required";
        isValid = false;
      }
      break;

    case 4:
      if (!formData.acceptTerms) {
        errors.acceptTerms = "You must accept the Terms & Conditions";
        isValid = false;
      }
      if (!formData.acceptVendorAgreement) {
        errors.acceptVendorAgreement = "You must accept the Vendor Agreement";
        isValid = false;
      }
      if (!formData.acceptDataPrivacy) {
        errors.acceptDataPrivacy = "You must accept the Data Privacy Policy";
        isValid = false;
      }
      break;

    case 5:
      if (!formData.confirmAccuracy) {
        errors.confirmAccuracy =
          "You must confirm the accuracy of your application";
        isValid = false;
      }
      break;
  }

  return isValid;
};

// ─────────────────────────────────────────────────────────────────────────────
// Navigation — toast added on validation failure
// ─────────────────────────────────────────────────────────────────────────────
const nextStep = () => {
  if (validateStep(currentStep.value)) {
    if (currentStep.value < totalSteps) {
      currentStep.value++;
      window.scrollTo({ top: 0, behavior: "smooth" });
      saveProgress();
    }
  } else {
    // NEW: toast notification for validation errors
    const errorCount = Object.keys(errors).length;
    toast.error(
      errorCount === 1
        ? "Please fill in the required field to proceed."
        : `Please fill in all ${errorCount} required fields to proceed.`,
      { autoClose: 3500 },
    );

    const firstErrorKey = Object.keys(errors)[0];
    if (firstErrorKey) {
      const element =
        document.querySelector(`[name="${firstErrorKey}"]`) ||
        document.querySelector(".input-group .error-message");
      if (element)
        element.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }
};

const prevStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--;
    window.scrollTo({ top: 0, behavior: "smooth" });
    saveProgress();
  }
};

// ─────────────────────────────────────────────────────────────────────────────
// UPDATED: saveProgress — also persists selectedServiceAreas & operatingHoursConfig
// ─────────────────────────────────────────────────────────────────────────────
const saveProgress = () => {
  const progress = {
    currentStep: currentStep.value,
    formData: JSON.parse(JSON.stringify(formData)),
    fileInfo: {},
    // NEW: persist the raw config so UI state is restored on reload
    selectedServiceAreas: [...selectedServiceAreas.value],
    operatingHoursConfig: JSON.parse(JSON.stringify(operatingHoursConfig)),
    timestamp: new Date().getTime(),
  };

  Object.keys(fileInfo).forEach((key) => {
    if (Array.isArray(fileInfo[key])) {
      progress.fileInfo[key] = fileInfo[key].map((file) => ({
        name: file.name,
        size: file.size,
        type: file.type,
      }));
    } else if (fileInfo[key]) {
      progress.fileInfo[key] = {
        name: fileInfo[key].name,
        size: fileInfo[key].size,
        type: fileInfo[key].type,
      };
    }
  });

  localStorage.setItem("vendorRegistrationProgress", JSON.stringify(progress));
};

// ─────────────────────────────────────────────────────────────────────────────
// UPDATED: loadProgress — also restores selectedServiceAreas & operatingHoursConfig
// ─────────────────────────────────────────────────────────────────────────────
const loadProgress = () => {
  const saved = localStorage.getItem("vendorRegistrationProgress");
  if (saved) {
    try {
      const parsed = JSON.parse(saved);
      const oneDayAgo = new Date().getTime() - 24 * 60 * 60 * 1000;
      if (parsed.timestamp && parsed.timestamp > oneDayAgo) {
        currentStep.value = parsed.currentStep || 1;

        Object.keys(parsed.formData || {}).forEach((key) => {
          if (formData.hasOwnProperty(key)) {
            formData[key] = parsed.formData[key];
          }
        });

        Object.keys(parsed.fileInfo || {}).forEach((key) => {
          fileInfo[key] = parsed.fileInfo[key];
        });

        // NEW: restore checkbox/time state
        if (Array.isArray(parsed.selectedServiceAreas)) {
          selectedServiceAreas.value = parsed.selectedServiceAreas;
        }
        if (parsed.operatingHoursConfig) {
          Object.assign(operatingHoursConfig, parsed.operatingHoursConfig);
        }

        if (currentStep.value > 1) {
          const shouldContinue = confirm(
            "We found a previously saved application. Do you want to continue where you left off?",
          );
          if (!shouldContinue) {
            clearProgress();
            currentStep.value = 1;
          }
        }
      } else {
        clearProgress();
      }
    } catch (e) {
      console.error("Error loading saved progress:", e);
      clearProgress();
    }
  }
};

// ─────────────────────────────────────────────────────────────────────────────
// UPDATED: clearProgress — also resets selectedServiceAreas & operatingHoursConfig
// ─────────────────────────────────────────────────────────────────────────────
const clearProgress = () => {
  localStorage.removeItem("vendorRegistrationProgress");
  currentStep.value = 1;

  Object.keys(formData).forEach((key) => {
    if (typeof formData[key] === "string") {
      formData[key] = "";
    } else if (Array.isArray(formData[key])) {
      formData[key] = [];
    } else if (typeof formData[key] === "boolean") {
      formData[key] = false;
    } else if (formData[key] === null) {
      formData[key] = null;
    } else {
      formData[key] = "";
    }
  });

  // NEW: reset extra UI state
  selectedServiceAreas.value = [];
  operatingHoursConfig.days = [];
  operatingHoursConfig.startTime = "";
  operatingHoursConfig.endTime = "";

  Object.keys(fileObjects).forEach((key) => {
    fileObjects[key] = Array.isArray(fileObjects[key]) ? [] : null;
  });

  Object.keys(fileInfo).forEach((key) => delete fileInfo[key]);
};

// ─────────────────────────────────────────────────────────────────────────────
// handleSubmit (unchanged logic; toast added for success)
// ─────────────────────────────────────────────────────────────────────────────
const handleSubmit = async () => {
  if (!validateStep(5)) {
    toast.error(
      "Please confirm the accuracy of your application before submitting.",
      {
        autoClose: 3500,
      },
    );
    return;
  }

  const requiredUploadFields = [
    ["government_id", "Government ID"],
    ["selfie_with_id", "Selfie with ID"],
    ["proof_of_address", "Proof of Address"],
    ["barangay_clearance", "Barangay Clearance"],
    ["mayor_permit", "Mayor's Permit"],
  ];

  const staleUpload = requiredUploadFields.find(
    ([fieldName]) => hasStoredFileInfo(fieldName) && !hasLiveFileObject(fieldName),
  );

  if (staleUpload) {
    const [fieldName, label] = staleUpload;
    const step = getStepForErrorField(fieldName) ?? getStepForErrorField(toCamelCase(fieldName));

    if (step) {
      currentStep.value = step;
    }

    errors[fieldName] = `${label} needs to be uploaded again before submitting.`;
    errors[toCamelCase(fieldName)] = errors[fieldName];
    window.scrollTo({ top: 0, behavior: "smooth" });
    toast.error(`${label} needs to be uploaded again before submitting.`, {
      autoClose: 5000,
    });
    return;
  }

  isSubmitting.value = true;

  try {
    const submitData = new FormData();

    const toSnakeCase = (str) => str.replace(/([A-Z])/g, "_$1").toLowerCase();

    for (const [key, value] of Object.entries(formData)) {
      const snakeKey = toSnakeCase(key);

      if (
        value !== null &&
        value !== undefined &&
        value !== "" &&
        value !== false
      ) {
        if (Array.isArray(value)) {
          if (value.length > 0) {
            submitData.append(snakeKey, JSON.stringify(value));
          }
        } else if (typeof value === "boolean") {
          submitData.append(snakeKey, value ? "1" : "0");
        } else {
          submitData.append(snakeKey, value);
        }
      }
    }

    if (
      fileObjects.government_id &&
      fileObjects.government_id instanceof File
    ) {
      submitData.append("government_id", fileObjects.government_id);
    }
    if (
      fileObjects.selfie_with_id &&
      fileObjects.selfie_with_id instanceof File
    ) {
      submitData.append("selfie_with_id", fileObjects.selfie_with_id);
    }
    if (
      fileObjects.proof_of_address &&
      fileObjects.proof_of_address instanceof File
    ) {
      submitData.append("proof_of_address", fileObjects.proof_of_address);
    }
    if (
      fileObjects.barangay_clearance &&
      fileObjects.barangay_clearance instanceof File
    ) {
      submitData.append("barangay_clearance", fileObjects.barangay_clearance);
    }
    if (fileObjects.mayor_permit && fileObjects.mayor_permit instanceof File) {
      submitData.append("mayor_permit", fileObjects.mayor_permit);
    }
    if (fileObjects.store_logo && fileObjects.store_logo instanceof File) {
      submitData.append("store_logo", fileObjects.store_logo);
    }
    if (
      Array.isArray(fileObjects.portfolio_photos) &&
      fileObjects.portfolio_photos.length > 0
    ) {
      fileObjects.portfolio_photos.forEach((file) => {
        if (file instanceof File) {
          submitData.append("portfolio_photos[]", file);
        }
      });
    }

    const today = new Date().toISOString().split("T")[0];
    submitData.append("application_date", today);

    const response = await api.post("/vendor/register", submitData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });

    const data = response.data;

    if (data.success) {
      clearProgress();
      applicationId.value = data.data.application_id || data.data.id;
      // NEW: success toast before showing modal
      toast.success("Application submitted successfully! 🎉", {
        autoClose: 4000,
      });
      showApplicationModal.value = true;
      localStorage.setItem("lastVendorApplicationId", applicationId.value);
      localStorage.setItem("lastVendorApplicationEmail", formData.email);
    } else {
      if (data.errors) {
        const firstMessage = applyBackendErrors(data.errors);
        if (firstMessage) {
          toast.error(firstMessage, {
            autoClose: 4500,
          });
        }
      } else {
        toast.error(data.message || "Registration failed. Please try again.", {
          autoClose: 4000,
        });
      }
    }
  } catch (error) {
    console.error("Registration error:", error);
    const responseData = error?.response?.data;

    if (responseData?.errors) {
      const firstMessage = applyBackendErrors(responseData.errors);
      toast.error(
        firstMessage || responseData.message || "Please fix the highlighted fields and try again.",
        { autoClose: 5000 },
      );
    } else {
      toast.error(
        responseData?.message ||
          "An error occurred during registration. Please check your connection and try again.",
        { autoClose: 4000 },
      );
    }
  } finally {
    isSubmitting.value = false;
  }
};

const goToStatusPage = () => {
  router.push(`/vendor/status?email=${encodeURIComponent(formData.email)}`);
};

const closeModal = () => {
  showApplicationModal.value = false;
  router.push("/");
};

// ─────────────────────────────────────────────────────────────────────────────
// Lifecycle hooks (unchanged)
// ─────────────────────────────────────────────────────────────────────────────
onMounted(() => {
  loadProgress();
  saveInterval.value = setInterval(saveProgress, 30000);

  const saved = localStorage.getItem("vendorRegistrationProgress");
  if (saved) {
    const parsed = JSON.parse(saved);
    const oneDayAgo = new Date().getTime() - 24 * 60 * 60 * 1000;
    if (parsed.timestamp && parsed.timestamp < oneDayAgo) {
      clearProgress();
    }
  }
});

onUnmounted(() => {
  if (saveInterval.value) clearInterval(saveInterval.value);

  Object.keys(fileInfo).forEach((key) => {
    if (fileInfo[key]) {
      if (Array.isArray(fileInfo[key])) {
        fileInfo[key].forEach((file) => {
          if (file.url) URL.revokeObjectURL(file.url);
        });
      } else if (fileInfo[key].url) {
        URL.revokeObjectURL(fileInfo[key].url);
      }
    }
  });
});
</script>

<style scoped>
/* ─── All original styles (unchanged) ──────────────────────────────────────── */
.register-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #ffffff;
  padding: 40px 20px;
  position: relative;
}

.logo {
  text-decoration: none;
  position: absolute;
  top: 32px;
  left: 32px;
  font-size: 32px;
  animation: bounce 2s ease-in-out infinite;
}

@keyframes bounce {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-6px);
  }
}

.back-btn {
  position: absolute;
  top: 32px;
  right: 32px;
  background: none;
  border: none;
  color: #4a5568;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  padding: 8px 12px;
  transition: all 0.2s;
}

.back-btn:hover {
  color: #2d3748;
  transform: translateX(-4px);
}

.header {
  text-align: center;
  margin-bottom: 48px;
}

.title {
  font-size: 32px;
  font-weight: 400;
  color: #2d3748;
  margin-bottom: 12px;
  letter-spacing: -0.5px;
}

.subtitle {
  font-size: 14px;
  color: #718096;
  font-weight: 400;
}

.link {
  color: #2d3748;
  text-decoration: underline;
  font-weight: 400;
  transition: color 0.2s;
}

.link:hover {
  color: #000000;
}

.steps-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 48px;
  max-width: 900px;
  width: 100%;
  padding: 0 20px;
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

.step-number {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #e2e8f0;
  color: #a0aec0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 500;
  font-size: 14px;
  transition: all 0.3s ease;
}

.step.active .step-number {
  background: #2d3748;
  color: white;
}
.step.completed .step-number {
  background: #20734d;
  color: white;
}

.step-label {
  font-size: 11px;
  color: #a0aec0;
  font-weight: 400;
  white-space: nowrap;
}

.step.active .step-label {
  color: #2d3748;
  font-weight: 500;
}

.step-line {
  flex: 1;
  height: 1px;
  background: #e2e8f0;
  margin: 0 12px;
  transition: all 0.3s ease;
  min-width: 20px;
  max-width: 60px;
}

.step-line.active {
  background: #20734d;
}

.form-content {
  width: 100%;
  max-width: 600px;
}

.form-step {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.step-title {
  font-size: 20px;
  font-weight: 400;
  color: #2d3748;
  margin-bottom: 8px;
  text-align: center;
}

.subtitle-text {
  font-size: 13px;
  color: #718096;
  text-align: center;
  margin-bottom: 24px;
}

.section-divider {
  margin: 32px 0 24px 0;
  padding-top: 24px;
  border-top: 1px solid #e2e8f0;
}

.section-title {
  font-size: 16px;
  font-weight: 500;
  color: #2d3748;
  margin-bottom: 16px;
}

.input-group {
  margin-bottom: 20px;
}

.input-label {
  display: block;
  font-size: 14px;
  font-weight: 400;
  color: #2d3748;
  margin-bottom: 8px;
}

.form-input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 15px;
  color: #2d3748;
  background: #ffffff;
  transition: all 0.2s ease;
  font-family: inherit;
}

.form-input:focus {
  outline: none;
  border-color: #cbd5e0;
}

.form-input::placeholder {
  color: #cbd5e0;
}
.form-input.error {
  border-color: #e53e3e;
}

.id-number-input {
  margin-bottom: 12px;
}
textarea.form-input {
  resize: vertical;
  min-height: 80px;
}

.file-upload-container {
  position: relative;
  margin-top: 8px;
}

.file-drop-zone {
  border: 2px dashed #e2e8f0;
  border-radius: 8px;
  padding: 32px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
  background: #f8fafc;
  position: relative;
  overflow: hidden;
}

.file-drop-zone:hover {
  border-color: #cbd5e0;
  background: #edf2f7;
}
.file-drop-zone.drag-over {
  border-color: #20734d;
  background: rgba(32, 115, 77, 0.05);
}
.file-drop-zone.has-file {
  border-color: #20734d;
  background: rgba(32, 115, 77, 0.05);
}

.upload-icon {
  width: 48px;
  height: 48px;
  fill: #cbd5e0;
  margin-bottom: 12px;
}
.file-drop-zone:hover .upload-icon {
  fill: #a0aec0;
}

.upload-text {
  font-size: 16px;
  color: #4a5568;
  margin-bottom: 4px;
  font-weight: 500;
}
.upload-hint {
  font-size: 12px;
  color: #a0aec0;
}

.file-input-hidden {
  display: none;
}

.file-preview {
  display: flex;
  align-items: center;
  padding: 12px;
  background: white;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  animation: slideIn 0.3s ease;
}

.file-preview.multiple {
  flex-direction: column;
  align-items: stretch;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.preview-icon {
  font-size: 24px;
  margin-right: 12px;
  flex-shrink: 0;
}
.file-preview.multiple .preview-icon {
  margin-right: 0;
  margin-bottom: 12px;
  text-align: center;
}

.file-details {
  flex: 1;
  min-width: 0;
}

.file-name {
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.file-preview.multiple .file-name {
  text-align: center;
}

.file-meta {
  display: flex;
  gap: 12px;
  font-size: 12px;
  color: #718096;
  margin-bottom: 8px;
}
.file-preview.multiple .file-meta {
  justify-content: center;
}

.remove-file-btn {
  background: none;
  border: none;
  color: #e53e3e;
  cursor: pointer;
  font-size: 24px;
  line-height: 1;
  padding: 4px 8px;
  margin-left: 8px;
  transition: all 0.2s;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  flex-shrink: 0;
}

.file-preview.multiple .remove-file-btn {
  margin-left: 0;
  margin-top: 12px;
  align-self: center;
}
.remove-file-btn:hover {
  background: #fff5f5;
  color: #c53030;
}

.file-list {
  margin-top: 8px;
  max-height: 100px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  padding: 4px;
}

.file-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 8px;
  font-size: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.file-item:last-child {
  border-bottom: none;
}
.file-item-name {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-right: 8px;
}
.file-item-size {
  color: #718096;
  font-size: 11px;
  margin-right: 8px;
}

.remove-file-item {
  background: none;
  border: none;
  color: #e53e3e;
  cursor: pointer;
  font-size: 16px;
  line-height: 1;
  padding: 2px 6px;
  transition: all 0.2s;
  border-radius: 3px;
}

.remove-file-item:hover {
  background: #fff5f5;
}

.hint-text {
  font-size: 12px;
  color: #a0aec0;
  margin-top: 6px;
}
.error-message {
  color: #e53e3e;
  font-size: 12px;
  margin-top: 4px;
}

.info-box {
  padding: 16px;
  background: #edf2f7;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  margin-top: 16px;
}

.info-box p {
  font-size: 13px;
  color: #4a5568;
  margin: 0;
  line-height: 1.5;
}

.legal-box {
  padding: 20px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.legal-label {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  cursor: pointer;
  font-size: 13px;
  color: #2d3748;
}
.legal-label input {
  margin-top: 2px;
}

.checkbox-input {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: #2d3748;
}
.checkbox-input.error {
  outline: 2px solid #e53e3e;
  border-radius: 2px;
}

.review-step .review-section {
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e2e8f0;
}
.review-section:last-child {
  border-bottom: none;
}
.review-section-title {
  font-size: 16px;
  font-weight: 500;
  color: #2d3748;
  margin-bottom: 12px;
}
.review-item {
  margin-bottom: 8px;
  font-size: 14px;
  line-height: 1.5;
}
.review-item strong {
  font-weight: 500;
  color: #2d3748;
  margin-right: 8px;
}

.button-group {
  display: flex;
  gap: 12px;
  margin-top: 32px;
}

.action-btn {
  flex: 1;
  padding: 14px 32px;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.action-btn:hover:not(:disabled) {
  background: #1a202c;
}
.action-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.action-btn.secondary {
  background: #718096;
}
.action-btn.secondary:hover:not(:disabled) {
  background: #4a5568;
}

.submit-btn {
  background: #20734d;
}
.submit-btn:hover:not(:disabled) {
  background: #1a5a3b;
}

.progress-text {
  text-align: center;
  margin-top: 24px;
}
.progress-text p {
  font-size: 13px;
  color: #a0aec0;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.3s ease-out;
  padding: 20px;
}

.modal {
  background: white;
  border-radius: 8px;
  padding: 32px;
  max-width: 400px;
  width: 90%;
  animation: slideUp 0.3s ease-out;
  position: relative;
  max-height: 90vh;
  overflow-y: auto;
}

.legal-modal {
  max-width: 700px;
}

.modal-content {
  max-height: 60vh;
  overflow-y: auto;
  padding-right: 10px;
}
.modal-content h2 {
  margin-top: 0;
  margin-bottom: 16px;
  color: #2d3748;
  font-size: 24px;
}
.modal-content h3 {
  margin-top: 20px;
  margin-bottom: 12px;
  color: #2d3748;
  font-size: 18px;
}
.modal-content p,
.modal-content ul,
.modal-content li {
  margin-bottom: 12px;
  color: #4a5568;
  line-height: 1.6;
}
.modal-content ul {
  padding-left: 20px;
}

.close-modal {
  position: absolute;
  top: 16px;
  right: 16px;
  background: none;
  border: none;
  color: #718096;
  font-size: 32px;
  cursor: pointer;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s;
  line-height: 1;
}

.close-modal:hover {
  background: #f7fafc;
  color: #2d3748;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal h3 {
  margin-top: 0;
  margin-bottom: 16px;
  color: #2d3748;
}
.modal p {
  margin-bottom: 12px;
  color: #4a5568;
}
.modal-actions {
  display: flex;
  gap: 12px;
  margin-top: 24px;
}
.modal-actions .action-btn {
  flex: 1;
}

/* ─── NEW styles: Service Areas checkboxes ──────────────────────────────────── */
.service-areas-container {
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 4px 0;
  background: #ffffff;
  transition: border-color 0.2s;
}

.service-areas-container.container-error {
  border-color: #e53e3e;
}

.service-area-option {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  cursor: pointer;
  transition: background 0.15s;
  border-bottom: 1px solid #f1f5f9;
}

.service-area-option:last-child {
  border-bottom: none;
}

.service-area-option:hover {
  background: #f7fafc;
}

.service-area-option input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: #20734d;
  cursor: pointer;
  flex-shrink: 0;
}

.service-area-label {
  font-size: 14px;
  color: #2d3748;
  user-select: none;
}

/* ─── NEW styles: Operating Hours ───────────────────────────────────────────── */
.operating-hours-container {
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 16px;
  background: #fafafa;
  transition: border-color 0.2s;
}

.operating-hours-container.container-error {
  border-color: #e53e3e;
}

.oh-section-label {
  font-size: 12px;
  font-weight: 500;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 10px;
}

/* Days grid */
.days-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.day-checkbox-label {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 40px;
  border: 1.5px solid #e2e8f0;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #718096;
  cursor: pointer;
  user-select: none;
  transition: all 0.15s ease;
  background: #ffffff;
}

.day-checkbox-label:hover {
  border-color: #20734d;
  color: #20734d;
  background: rgba(32, 115, 77, 0.04);
}

.day-checkbox-label.selected {
  border-color: #20734d;
  background: #20734d;
  color: #ffffff;
}

.day-checkbox-hidden {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
  pointer-events: none;
}

/* Quick-select shortcuts */
.day-shortcuts {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 4px;
}

.shortcut-btn {
  padding: 4px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  font-size: 12px;
  color: #4a5568;
  background: #ffffff;
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
}

.shortcut-btn:hover {
  border-color: #20734d;
  color: #20734d;
  background: rgba(32, 115, 77, 0.05);
}

.shortcut-clear {
  color: #e53e3e;
  border-color: #fed7d7;
}

.shortcut-clear:hover {
  border-color: #e53e3e;
  background: #fff5f5;
  color: #c53030;
}

/* Time range */
.time-range-row {
  display: flex;
  align-items: flex-end;
  gap: 12px;
}

.time-field {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.time-label {
  font-size: 12px;
  color: #718096;
}

.time-input {
  padding: 10px 12px;
  font-size: 14px;
}

.time-separator {
  font-size: 18px;
  color: #a0aec0;
  padding-bottom: 10px;
  flex-shrink: 0;
}

/* Live preview */
.hours-preview {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 14px;
  padding: 10px 14px;
  background: rgba(32, 115, 77, 0.08);
  border: 1px solid rgba(32, 115, 77, 0.2);
  border-radius: 6px;
  font-size: 13px;
  color: #1a5a3b;
  font-weight: 500;
  animation: fadeIn 0.2s ease;
}

/* ─── Responsive (unchanged + minor additions) ──────────────────────────────── */
@media (max-width: 768px) {
  .steps-indicator {
    padding: 0 10px;
  }
  .step-label {
    font-size: 9px;
  }
  .step-number {
    width: 28px;
    height: 28px;
    font-size: 12px;
  }
  .step-line {
    margin: 0 8px;
    min-width: 15px;
    max-width: 40px;
  }
  .modal {
    padding: 24px;
  }
  .time-range-row {
    flex-direction: column;
    gap: 8px;
  }
  .time-separator {
    display: none;
  }
}

@media (max-width: 640px) {
  .logo {
    top: 20px;
    left: 20px;
    font-size: 28px;
  }
  .back-btn {
    top: 20px;
    right: 20px;
  }
  .title {
    font-size: 28px;
  }
  .form-content {
    max-width: 100%;
  }
  .file-drop-zone {
    padding: 24px 16px;
  }
  .button-group {
    flex-direction: column;
  }
  .step-label {
    font-size: 8px;
  }
  .step-number {
    width: 26px;
    height: 26px;
    font-size: 11px;
  }
  .step-line {
    margin: 0 6px;
    min-width: 10px;
    max-width: 30px;
  }
  .modal-actions {
    flex-direction: column;
  }
  .days-grid {
    gap: 6px;
  }
  .day-checkbox-label {
    width: 42px;
    height: 36px;
    font-size: 12px;
  }
}

@media (max-width: 480px) {
  .register-container {
    padding: 20px 16px;
  }
  .header {
    margin-bottom: 32px;
  }
  .steps-indicator {
    margin-bottom: 32px;
  }
  .form-input,
  .file-drop-zone {
    padding: 10px 14px;
  }
  .action-btn {
    padding: 12px 24px;
  }
}
</style>
