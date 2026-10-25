<!-- VendorRegistration.vue -->
<template>
  <AuthShell
    class="bc-palette-classic"
    wide
    title="Sell with BloomCraft"
    lead="Tell us about your flower shop. Our team reviews every application, usually within two working days, and emails you when your storefront is ready."
  >
    <template #top>
      Already a vendor? <router-link to="/guest/login" class="af-link">Sign in</router-link>
    </template>
    <template #caption>
      <i>Your shop</i>, open online:<br />
      a storefront, 3D bouquets, orders, deliveries and your whole team in one place.
    </template>

    <!-- Applications closed by an admin (Security → Platform settings) -->
    <AuthNotice
      v-if="!platform.vendor_applications_open"
      tone="info"
      title="We're not taking new store applications right now"
      body="Please check back soon. If you already applied, we'll email you about your application."
    />


    <!-- Sent: a full page that says what happens next -->
    <section v-if="showApplicationModal" class="vr-done" aria-live="polite">
      <span class="vr-done-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="m6 12.5 4 4 8-9" /></svg>
      </span>
      <h2>Application sent</h2>
      <p class="vr-done-lead">Thank you{{ formData.ownerName ? `, ${formData.ownerName.split(" ")[0]}` : "" }}. We've emailed a copy to {{ formData.email || "you" }}.</p>
      <div class="vr-id">
        <span>Application ID</span>
        <b>{{ applicationId }}</b>
        <button type="button" class="af-plain" @click="copyApplicationId">{{ copied ? "Copied" : "Copy" }}</button>
      </div>
      <ol class="vr-next" aria-label="What happens next">
        <li><b>We check your documents</b><span>Usually within two working days.</span></li>
        <li><b>You get an email with the decision</b><span>If something needs fixing, the email says exactly what to send again.</span></li>
        <li>
          <b>Sign in and open your shop</b>
          <span v-if="pendingPlanName">We'll take you straight to checkout for the {{ pendingPlanName }} plan.</span>
          <span v-else>Add your products and your storefront goes live.</span>
        </li>
      </ol>
      <button type="button" class="af-btn vr-done-btn" @click="closeModal">Back to BloomCraft</button>
    </section>

    <!-- Kept on the page (not removed) so the store map can still set itself up -->
    <div v-show="platform.vendor_applications_open && !showApplicationModal" class="vr">
      <!-- Where you are: finished steps can be reopened -->
      <ol class="vr-steps" aria-label="Application steps">
        <li v-for="step in steps" :key="step.number" :class="{ on: currentStep === step.number, done: currentStep > step.number }" :aria-current="currentStep === step.number ? 'step' : undefined">
          <button type="button" :disabled="step.number >= currentStep" @click="goToStep(step.number)">
            <span class="vr-dot" aria-hidden="true">
              <svg v-if="currentStep > step.number" viewBox="0 0 24 24"><path d="m6 12.5 4 4 8-9" /></svg>
              <template v-else>{{ step.number }}</template>
            </span>
            <span class="vr-step-name">{{ step.title }}</span>
          </button>
        </li>
      </ol>

      <!-- ── Step 1: the shop ─────────────────────────────────────────── -->
      <div v-show="currentStep === 1" class="vr-step">
        <header class="vr-head">
          <h2>Your shop</h2>
          <p>What customers will see, and where and when you deliver.</p>
        </header>

        <section class="vr-card">
          <h3>About the shop</h3>
          <div class="vr-field">
            <label class="vr-label" for="vr-store">Shop name</label>
            <input id="vr-store" v-model="formData.storeName" name="storeName" type="text" class="vr-input" :class="{ error: errors.storeName }" placeholder="e.g. Petal & Stem Flower Studio" maxlength="255" />
            <p class="vr-error" aria-live="polite">{{ errors.storeName }}</p>
          </div>
          <div class="vr-field">
            <label class="vr-label" for="vr-desc">Describe your shop <small>{{ formData.storeDescription.length }}/1000</small></label>
            <textarea id="vr-desc" v-model="formData.storeDescription" name="storeDescription" rows="4" maxlength="1000" class="vr-input" :class="{ error: errors.storeDescription }" placeholder="The flowers you love working with, how you arrange them, how you deliver…"></textarea>
            <p class="vr-error" aria-live="polite">{{ errors.storeDescription }}</p>
          </div>
        </section>

        <section class="vr-card">
          <h3>How your business is registered</h3>
          <p class="vr-sub">This decides which certificate we ask for later.</p>
          <div class="vr-choices" role="radiogroup" aria-label="Business structure">
            <label v-for="(r, key) in REGISTRATIONS" :key="key" class="vr-choice" :class="{ on: formData.businessType === key }">
              <input v-model="formData.businessType" type="radio" name="businessType" :value="key" />
              <span class="vr-badge">{{ r.agency }}</span>
              <b>{{ STRUCTURE_LABELS[key].title }}</b>
              <small>{{ STRUCTURE_LABELS[key].hint }}</small>
            </label>
          </div>
          <p class="vr-error" aria-live="polite">{{ errors.businessType }}</p>
        </section>

        <section class="vr-card">
          <h3>Where your shop is</h3>
          <div class="vr-field">
            <label class="vr-label" for="vr-address">Store address</label>
            <input id="vr-address" v-model="formData.storeAddress" name="storeAddress" list="cavite-addresses" type="text" class="vr-input" :class="{ error: errors.storeAddress }" placeholder="Pin your shop on the map, then edit the address if needed" />
            <datalist id="cavite-addresses">
              <option v-for="location in caviteLocations" :key="location" :value="location" />
            </datalist>
          </div>
          <div class="store-location-map" :class="{ 'map-loading': mapLoading }">
            <div ref="storeLocationMap" class="store-location-map-canvas"></div>
            <p v-if="mapLoading" class="map-status">Loading the map of Cavite…</p>
          </div>
          <p class="vr-hint">Click or drag the pin onto your shop. It has to be inside Cavite; you can still edit the address text.</p>
          <p class="vr-error" aria-live="polite">{{ errors.storeAddress }}</p>
        </section>

        <section class="vr-card">
          <h3>Where you deliver</h3>
          <div class="vr-chips" :class="{ error: errors.serviceAreas }">
            <label v-for="area in serviceAreaOptions" :key="area.value" class="vr-chip" :class="{ on: selectedServiceAreas.includes(area.value) }">
              <input v-model="selectedServiceAreas" type="checkbox" :value="area.value" />
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 12.5 4 4 8-9" /></svg>{{ area.label }}
            </label>
          </div>
          <p class="vr-error" aria-live="polite">{{ errors.serviceAreas }}</p>
        </section>

        <section class="vr-card">
          <h3>Opening hours</h3>
          <p class="vr-sub">Switch on the days you're open and set the hours for each.</p>
          <div class="vr-week">
            <div v-for="day in daysOfWeek" :key="day" class="vr-dayrow" :class="{ closed: !weekHours[day].open }">
              <label class="vr-switch">
                <input type="checkbox" :checked="weekHours[day].open" :aria-label="`Open on ${day}`" @change="setDayOpen(day, $event.target.checked)" />
                <span aria-hidden="true"></span>
                <b>{{ day }}</b>
              </label>
              <div v-if="weekHours[day].open" class="vr-times">
                <input v-model="weekHours[day].from" type="time" class="vr-input vr-time" :aria-label="`${day} opens at`" />
                <span>to</span>
                <input v-model="weekHours[day].to" type="time" class="vr-input vr-time" :aria-label="`${day} closes at`" />
              </div>
              <span v-else class="vr-closed">Closed</span>
            </div>
          </div>
          <div class="vr-quick">
            <button type="button" @click="openOnly(WEEKDAYS)">Weekdays</button>
            <button type="button" @click="openOnly(daysOfWeek.slice(0, 6))">Mon–Sat</button>
            <button type="button" @click="openOnly(daysOfWeek)">Every day</button>
            <button v-if="firstOpenDay && openDayCount > 1" type="button" @click="useHoursEverywhere(firstOpenDay)">Use {{ firstOpenDay }}'s hours for every open day</button>
          </div>
          <p class="vr-error" aria-live="polite">{{ errors.operatingHours || errors.operating_schedules }}</p>
        </section>
      </div>

      <!-- ── Step 2: the owner ────────────────────────────────────────── -->
      <div v-if="currentStep === 2" class="vr-step">
        <header class="vr-head">
          <h2>About you</h2>
          <p>The person responsible for the shop. We use this to confirm it's really you.</p>
        </header>

        <section class="vr-card">
          <h3>Owner</h3>
          <div class="vr-grid">
            <div class="vr-field">
              <label class="vr-label" for="vr-owner">Full name</label>
              <input id="vr-owner" v-model="formData.ownerName" name="ownerName" type="text" autocomplete="name" class="vr-input" :class="{ error: errors.ownerName }" placeholder="As shown on your ID" />
              <p class="vr-error" aria-live="polite">{{ errors.ownerName }}</p>
            </div>
            <div class="vr-field">
              <label class="vr-label" for="vr-position">Role in the business</label>
              <input id="vr-position" v-model="formData.position" name="position" type="text" class="vr-input" :class="{ error: errors.position }" placeholder="Owner, manager, president…" />
              <p class="vr-error" aria-live="polite">{{ errors.position }}</p>
            </div>
            <div class="vr-field">
              <label class="vr-label" for="vr-phone">Mobile or landline</label>
              <input id="vr-phone" v-model="formData.contactNumber" name="contactNumber" type="tel" inputmode="tel" autocomplete="tel" class="vr-input" :class="{ error: errors.contactNumber }" placeholder="0917 123 4567" />
              <p class="vr-error" aria-live="polite">{{ errors.contactNumber }}</p>
            </div>
            <div class="vr-field">
              <label class="vr-label" for="vr-email">Email</label>
              <input id="vr-email" v-model="formData.email" name="email" type="email" autocomplete="email" class="vr-input" :class="{ error: errors.email }" placeholder="you@yourshop.com" />
              <p class="vr-error" aria-live="polite">{{ errors.email }}</p>
            </div>
          </div>
          <p class="vr-hint">Your sign-in details are sent to this email once you're approved.</p>
        </section>

        <section class="vr-card">
          <h3>Identity</h3>
          <div class="vr-grid">
            <div class="vr-field">
              <label class="vr-label" for="vr-idtype">Government ID</label>
              <select id="vr-idtype" v-model="formData.governmentIdType" name="governmentIdType" class="vr-input" :class="{ error: errors.governmentIdType }">
                <option value="">Choose your ID</option>
                <option v-for="(label, key) in GOVERNMENT_IDS" :key="key" :value="key">{{ label }}</option>
              </select>
              <p class="vr-error" aria-live="polite">{{ errors.governmentIdType }}</p>
            </div>
            <div class="vr-field">
              <label class="vr-label" for="vr-idno">ID number</label>
              <input id="vr-idno" v-model="formData.governmentIdNumber" name="governmentIdNumber" type="text" class="vr-input" :class="{ error: errors.governmentIdNumber }" placeholder="As printed on the ID" />
              <p class="vr-error" aria-live="polite">{{ errors.governmentIdNumber }}</p>
            </div>
          </div>
          <div class="vr-docs">
            <div class="vr-doc">
              <span class="vr-label">Photo of the ID</span>
              <DocDrop field="government_id" label="ID photo" hint="All four corners visible, no glare" :info="fileInfo.government_id" :over="!!dragOver.government_id" :error="errors.government_id" @change="handleFileUpload($event, 'government_id')" @drop="handleDrop($event, 'government_id')" @dragenter="handleDragEnter($event, 'government_id')" @dragover="handleDragOver($event, 'government_id')" @dragleave="handleDragLeave($event, 'government_id')" @remove="removeFile('government_id')" />
            </div>
            <div class="vr-doc">
              <span class="vr-label">Selfie holding the ID</span>
              <DocDrop field="selfie_with_id" label="selfie" hint="Your face and the ID both clear" accept=".jpg,.jpeg,.png,image/*" :info="fileInfo.selfie_with_id" :over="!!dragOver.selfie_with_id" :error="errors.selfie_with_id" @change="handleFileUpload($event, 'selfie_with_id')" @drop="handleDrop($event, 'selfie_with_id')" @dragenter="handleDragEnter($event, 'selfie_with_id')" @dragover="handleDragOver($event, 'selfie_with_id')" @dragleave="handleDragLeave($event, 'selfie_with_id')" @remove="removeFile('selfie_with_id')" />
            </div>
            <div class="vr-doc">
              <span class="vr-label">Proof of address</span>
              <DocDrop field="proof_of_address" label="proof of address" hint="Utility bill, barangay certificate or lease" :info="fileInfo.proof_of_address" :over="!!dragOver.proof_of_address" :error="errors.proof_of_address" @change="handleFileUpload($event, 'proof_of_address')" @drop="handleDrop($event, 'proof_of_address')" @dragenter="handleDragEnter($event, 'proof_of_address')" @dragover="handleDragOver($event, 'proof_of_address')" @dragleave="handleDragLeave($event, 'proof_of_address')" @remove="removeFile('proof_of_address')" />
            </div>
          </div>
        </section>
      </div>

      <!-- ── Step 3: business documents ───────────────────────────────── -->
      <div v-if="currentStep === 3" class="vr-step">
        <header class="vr-head">
          <h2>Business documents</h2>
          <p>What a {{ registration.who }} in the Philippines operates with. Clear photos or PDFs of the originals; only our review team sees them.</p>
        </header>

        <section class="vr-card">
          <h3><span class="vr-badge">{{ registration.agency }}</span> Registration</h3>
          <div class="vr-grid">
            <div class="vr-field">
              <label class="vr-label" for="vr-regname">Registered business name</label>
              <input id="vr-regname" v-model="formData.registeredBusinessName" name="registeredBusinessName" type="text" class="vr-input" :class="{ error: errors.registeredBusinessName }" placeholder="Exactly as on the certificate" />
              <p v-if="errors.registeredBusinessName" class="vr-error">{{ errors.registeredBusinessName }}</p>
              <p v-else class="vr-hint">Can be different from your shop name.</p>
            </div>
            <div class="vr-field">
              <label class="vr-label" for="vr-regno">{{ registration.numberLabel }}</label>
              <input id="vr-regno" v-model="formData[registration.field]" :name="registration.field" type="text" class="vr-input" :class="{ error: errors[registration.field] }" :placeholder="registration.example" />
              <p class="vr-error" aria-live="polite">{{ errors[registration.field] }}</p>
            </div>
          </div>
          <div class="vr-field">
            <span class="vr-label">{{ registration.certificate }}</span>
            <DocDrop field="business_registration" label="your certificate" :info="fileInfo.business_registration" :over="!!dragOver.business_registration" :error="errors.business_registration" @change="handleFileUpload($event, 'business_registration')" @drop="handleDrop($event, 'business_registration')" @dragenter="handleDragEnter($event, 'business_registration')" @dragover="handleDragOver($event, 'business_registration')" @dragleave="handleDragLeave($event, 'business_registration')" @remove="removeFile('business_registration')" />
          </div>
        </section>

        <section class="vr-card">
          <h3><span class="vr-badge">BIR</span> Taxes</h3>
          <div class="vr-field">
            <label class="vr-label" for="vr-tin">Taxpayer Identification Number (TIN)</label>
            <input id="vr-tin" :value="formData.birTin" name="birTin" type="text" inputmode="numeric" class="vr-input vr-narrow" :class="{ error: errors.birTin }" placeholder="123-456-789-00000" @input="formData.birTin = formatTin($event.target.value); $event.target.value = formData.birTin" />
            <p v-if="errors.birTin" class="vr-error">{{ errors.birTin }}</p>
            <p v-else class="vr-hint">9 digits, plus a branch code if you have one.</p>
          </div>
          <div class="vr-field">
            <span class="vr-label">Certificate of Registration (BIR Form 2303)</span>
            <DocDrop field="bir_certificate" label="BIR Form 2303" :info="fileInfo.bir_certificate" :over="!!dragOver.bir_certificate" :error="errors.bir_certificate" @change="handleFileUpload($event, 'bir_certificate')" @drop="handleDrop($event, 'bir_certificate')" @dragenter="handleDragEnter($event, 'bir_certificate')" @dragover="handleDragOver($event, 'bir_certificate')" @dragleave="handleDragLeave($event, 'bir_certificate')" @remove="removeFile('bir_certificate')" />
          </div>
        </section>

        <section class="vr-card">
          <h3><span class="vr-badge">LGU</span> Mayor's / Business Permit</h3>
          <div class="vr-grid">
            <div class="vr-field">
              <label class="vr-label" for="vr-permit">Permit number</label>
              <input id="vr-permit" v-model="formData.mayorPermitNumber" name="mayorPermitNumber" type="text" class="vr-input" :class="{ error: errors.mayorPermitNumber }" placeholder="As printed on the permit" />
              <p class="vr-error" aria-live="polite">{{ errors.mayorPermitNumber }}</p>
            </div>
            <div class="vr-field">
              <label class="vr-label" for="vr-valid">Valid until</label>
              <input id="vr-valid" v-model="formData.mayorPermitValidUntil" name="mayorPermitValidUntil" type="date" class="vr-input" :class="{ error: errors.mayorPermitValidUntil }" :min="todayYmd" />
              <p v-if="errors.mayorPermitValidUntil" class="vr-error">{{ errors.mayorPermitValidUntil }}</p>
              <p v-else class="vr-hint">Permits are renewed every January.</p>
            </div>
          </div>
          <DocDrop field="mayor_permit" label="your business permit" :info="fileInfo.mayor_permit" :over="!!dragOver.mayor_permit" :error="errors.mayor_permit" @change="handleFileUpload($event, 'mayor_permit')" @drop="handleDrop($event, 'mayor_permit')" @dragenter="handleDragEnter($event, 'mayor_permit')" @dragover="handleDragOver($event, 'mayor_permit')" @dragleave="handleDragLeave($event, 'mayor_permit')" @remove="removeFile('mayor_permit')" />
        </section>

        <section class="vr-card">
          <h3><span class="vr-badge">Barangay</span> Business Clearance</h3>
          <div class="vr-field">
            <label class="vr-label" for="vr-brgy">Clearance number</label>
            <input id="vr-brgy" v-model="formData.barangayClearanceNumber" name="barangayClearanceNumber" type="text" class="vr-input vr-narrow" :class="{ error: errors.barangayClearanceNumber }" placeholder="As printed on the clearance" />
            <p class="vr-error" aria-live="polite">{{ errors.barangayClearanceNumber }}</p>
          </div>
          <DocDrop field="barangay_clearance" label="your barangay clearance" :info="fileInfo.barangay_clearance" :over="!!dragOver.barangay_clearance" :error="errors.barangay_clearance" @change="handleFileUpload($event, 'barangay_clearance')" @drop="handleDrop($event, 'barangay_clearance')" @dragenter="handleDragEnter($event, 'barangay_clearance')" @dragover="handleDragOver($event, 'barangay_clearance')" @dragleave="handleDragLeave($event, 'barangay_clearance')" @remove="removeFile('barangay_clearance')" />
        </section>
      </div>

      <!-- ── Step 4: agreements and extras ────────────────────────────── -->
      <div v-if="currentStep === 4" class="vr-step">
        <header class="vr-head">
          <h2>Agreements and extras</h2>
          <p>Read and accept the three agreements. The rest is optional but helps customers find you.</p>
        </header>

        <section class="vr-card">
          <h3>Agreements</h3>
          <div v-for="a in AGREEMENTS" :key="a.key" class="vr-agree" :class="{ on: formData[a.field], error: errors[a.field] }">
            <div class="vr-agree-text">
              <b>{{ a.title }}</b>
              <small>{{ a.summary }}</small>
              <span class="vr-agree-foot">
                <button type="button" class="af-plain vr-small" @click="showModal(a.modal)">Read it</button>
                <span class="vr-error" aria-live="polite">{{ errors[a.field] }}</span>
              </span>
            </div>
            <label class="vr-switch">
              <input v-model="formData[a.field]" type="checkbox" :name="a.field" />
              <span aria-hidden="true"></span>
              <em>{{ formData[a.field] ? "Accepted" : "I agree" }}</em>
            </label>
          </div>
        </section>

        <section class="vr-card">
          <h3>Make your shop shine <small>optional</small></h3>
          <div class="vr-docs two">
            <div class="vr-doc">
              <span class="vr-label">Logo</span>
              <DocDrop field="store_logo" label="your logo" hint="Square works best · up to 2 MB" accept=".jpg,.jpeg,.png,image/*" :info="fileInfo.store_logo" :over="!!dragOver.store_logo" :error="errors.store_logo" @change="handleFileUpload($event, 'store_logo')" @drop="handleDrop($event, 'store_logo')" @dragenter="handleDragEnter($event, 'store_logo')" @dragover="handleDragOver($event, 'store_logo')" @dragleave="handleDragLeave($event, 'store_logo')" @remove="removeFile('store_logo')" />
            </div>
            <div class="vr-doc">
              <span class="vr-label">Sample work</span>
              <div class="vr-gallery">
                <figure v-for="(file, index) in fileInfo.portfolio_photos || []" :key="index">
                  <img v-if="file.url" :src="file.url" :alt="file.name" />
                  <button type="button" :aria-label="`Remove ${file.name}`" @click="removePortfolioFile(index)">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                  </button>
                </figure>
                <label class="vr-gallery-add" aria-label="Add sample photos" @dragover.prevent @drop.prevent="handleDrop($event, 'portfolio_photos')">
                  <input ref="portfolio_photosInput" type="file" multiple accept=".jpg,.jpeg,.png,image/*" @change="handleFileUpload($event, 'portfolio_photos')" />
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                  <span>Add</span>
                </label>
              </div>
              <p class="vr-hint">Bouquets you've made. They show on your storefront.</p>
            </div>
          </div>
          <div class="vr-grid">
            <div class="vr-field">
              <label class="vr-label" for="vr-fb">Facebook page</label>
              <input id="vr-fb" v-model="formData.facebookPage" name="facebookPage" type="url" class="vr-input" :class="{ error: errors.facebookPage }" placeholder="https://facebook.com/yourshop" />
              <p class="vr-error" aria-live="polite">{{ errors.facebookPage }}</p>
            </div>
            <div class="vr-field">
              <label class="vr-label" for="vr-ig">Instagram</label>
              <input id="vr-ig" v-model="formData.instagramPage" name="instagramPage" type="url" class="vr-input" :class="{ error: errors.instagramPage }" placeholder="https://instagram.com/yourshop" />
              <p class="vr-error" aria-live="polite">{{ errors.instagramPage }}</p>
            </div>
          </div>
        </section>
      </div>

      <!-- ── Step 5: review ───────────────────────────────────────────── -->
      <div v-if="currentStep === 5" class="vr-step">
        <header class="vr-head">
          <h2>Check and send</h2>
          <p>Make sure everything is right. You can change anything before you send it.</p>
        </header>

        <section v-for="part in reviewParts" :key="part.step" class="vr-card vr-review">
          <div class="vr-review-head">
            <h3>{{ part.title }}</h3>
            <button type="button" class="af-plain vr-small" @click="goToStep(part.step)">Edit</button>
          </div>
          <dl>
            <template v-for="row in part.rows" :key="row[0]">
              <dt>{{ row[0] }}</dt>
              <dd :class="{ missing: !row[1] }">{{ row[1] || "Not provided" }}</dd>
            </template>
          </dl>
        </section>

        <section class="vr-card">
          <h3>Documents</h3>
          <ul class="vr-checklist">
            <li v-for="doc in reviewDocs" :key="doc.field" :class="{ ok: doc.ok }">
              <svg v-if="doc.ok" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 12.5 4 4 8-9" /></svg>
              <svg v-else viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" /></svg>
              <span>{{ doc.label }}</span>
              <small>{{ doc.ok ? doc.name : "Missing" }}</small>
            </li>
          </ul>
        </section>

        <label class="vr-confirm" :class="{ on: formData.confirmAccuracy, error: errors.confirmAccuracy }">
          <input v-model="formData.confirmAccuracy" type="checkbox" name="confirmAccuracy" />
          <span>I confirm that everything in this application is true and complete, and that the documents are mine to share.</span>
        </label>
        <p class="vr-error" aria-live="polite">{{ errors.confirmAccuracy }}</p>
      </div>

      <!-- Moving between steps -->
      <footer class="vr-nav">
        <button v-if="currentStep > 1" type="button" class="af-btn ghost" :disabled="isSubmitting" @click="prevStep">Back</button>
        <span class="vr-saved">Step {{ currentStep }} of {{ totalSteps }}. Saved on this device.</span>
        <button v-if="currentStep < totalSteps" type="button" class="af-btn" :disabled="isSubmitting" @click="nextStep">
          {{ currentStep === 4 ? "Review application" : "Continue" }}
        </button>
        <button v-else type="button" class="af-btn" :class="{ busy: isSubmitting }" :disabled="isSubmitting" @click="handleSubmit">
          {{ isSubmitting ? "Sending your application…" : "Send application" }}
        </button>
      </footer>
    </div>

    <!-- The full agreement, to read -->
    <Teleport to="body">
      <div v-if="activeModal" class="bc-theme bc-palette-classic vr-modal-back" @click.self="closeActiveModal">
        <div class="vr-modal" role="dialog" aria-modal="true" @keydown.esc="closeActiveModal">
          <button type="button" class="vr-modal-x" aria-label="Close" @click="closeActiveModal">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
          </button>
          <div class="vr-modal-body" v-html="modalContent"></div>
          <div class="vr-modal-foot">
            <button type="button" class="af-btn" @click="closeActiveModal">Done reading</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AuthShell>
</template>

<script setup>
import {
  ref,
  reactive,
  onMounted,
  onUnmounted,
  nextTick,
  computed,
  watchEffect,
  watch,
} from "vue";
import { useRouter } from "vue-router";
import api from "../../plugins/axios";
import { usePlatformStatus } from "../../composables/usePlatformStatus";
import AuthShell from "../../components/auth/AuthShell.vue";
import AuthNotice from "../../components/auth/AuthNotice.vue";
import DocDrop from "../../components/vendor/register/DocDrop.vue";
import { toast } from "@/utils/snackbar";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";
import { findPlan, readPendingPlan } from "../../constants/subscriptionPlans";

const router = useRouter();

// Problems are explained in a snackbar, in words, with "Show me" to jump to
// the field; the field itself is marked too
const formProblem = ref(null);
const resumed = ref(false);
watch(resumed, (on) => {
  if (on) {
    toast.success("Welcome back. We kept what you entered on this device, so you can carry on. Documents may need to be added again.", {
      toastId: "vendor-resume",
      autoClose: 9000,
      action: { label: "Start over", onClick: () => startOver() },
    });
  }
});
const startOver = () => {
  clearProgress();
  resumed.value = false;
  window.scrollTo({ top: 0, behavior: "smooth" });
};
let problemField = null;
const explain = (title, body, { tone = "warn", field = null } = {}) => {
  formProblem.value = { tone, title, body, goTo: !!field, step: currentStep.value };
  problemField = field;
  toast[tone === "info" ? "info" : tone === "success" ? "success" : "error"](`${title.replace(/[.!]$/, "")}. ${body ?? ""}`.trim(), {
    toastId: "vendor-problem",
    action: field ? { label: "Show me", onClick: () => jumpToProblem() } : null,
  });
  nextTick(() => jumpToProblem());
};
const jumpToProblem = () => {
  const el = (problemField && document.querySelector(`[name="${problemField}"]`)) || document.querySelector(".input-group .error-message");
  el?.scrollIntoView({ behavior: "smooth", block: "center" });
  el?.focus?.({ preventScroll: true });
};
const currentStep = ref(1);
const totalSteps = 5;
const isSubmitting = ref(false);
const showApplicationModal = ref(false);
const applicationId = ref("");
// Plan picked on the landing page before registering (see subscriptionPlans.js).
const pendingPlanName = ref(findPlan(readPendingPlan())?.name || "");
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
  storeAddress: "",
  storeLatitude: null,
  storeLongitude: null,
  serviceAreas: "", // Auto-populated via watchEffect ← selectedServiceAreas
  operatingHours: "", // Auto-populated via watchEffect ← operatingHoursConfig

  // Step 2
  ownerName: "",
  position: "",
  contactNumber: "",
  email: "",
  governmentIdType: "",
  governmentIdNumber: "",

  // Step 3 — one of DTI / SEC / CDA, by business structure
  registeredBusinessName: "",
  dtiNumber: "",
  secNumber: "",
  cdaNumber: "",
  mayorPermitValidUntil: "",
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

// Valid government IDs in the Philippines (same list as the server)
const GOVERNMENT_IDS = {
  philsys: "PhilSys National ID",
  passport: "Passport",
  drivers_license: "Driver's License",
  umid: "UMID",
  sss: "SSS ID",
  prc: "PRC ID",
  postal: "Postal ID",
  voters: "Voter's ID",
  philhealth: "PhilHealth ID",
  tin_id: "TIN ID",
  senior: "Senior Citizen ID",
  pwd: "PWD ID",
};

// Which registration the business has, from its structure
const REGISTRATIONS = {
  individual: { who: "sole proprietorship", agency: "DTI", field: "dtiNumber", numberLabel: "DTI Business Name registration number", certificate: "DTI Certificate of Business Name Registration", example: "e.g. 3456789" },
  partnership: { who: "partnership", agency: "SEC", field: "secNumber", numberLabel: "SEC registration number", certificate: "SEC Certificate of Registration", example: "e.g. PG201912345" },
  corporation: { who: "corporation", agency: "SEC", field: "secNumber", numberLabel: "SEC registration number", certificate: "SEC Certificate of Incorporation", example: "e.g. CS201912345" },
  cooperative: { who: "cooperative", agency: "CDA", field: "cdaNumber", numberLabel: "CDA registration number", certificate: "CDA Certificate of Registration", example: "e.g. 9520-1234567" },
};
const registration = computed(() => REGISTRATIONS[formData.businessType] ?? REGISTRATIONS.individual);
const todayYmd = new Date().toLocaleDateString("en-CA");

// 123456789 → 123-456-789; a branch code follows after another dash
const formatTin = (value) => {
  const d = String(value).replace(/\D/g, "").slice(0, 14);
  return [d.slice(0, 3), d.slice(3, 6), d.slice(6, 9), d.slice(9)].filter(Boolean).join("-");
};
const tinOk = (value) => /^\d{9}(\d{3}|\d{5})?$/.test(String(value).replace(/-/g, ""));
// Mobile (09XX… / +63 9XX…) or landline with area code
const phoneOk = (value) => {
  const d = String(value).replace(/[^\d+]/g, "");
  return /^(\+?63|0)9\d{9}$/.test(d) || /^(\+?63|0)\d{8,10}$/.test(d);
};

const steps = [
  { number: 1, title: "Your shop" },
  { number: 2, title: "About you" },
  { number: 3, title: "Documents" },
  { number: 4, title: "Agreements" },
  { number: 5, title: "Review" },
];

// The four ways a business can be registered, as cards
const STRUCTURE_LABELS = {
  individual: { title: "Sole proprietorship", hint: "One owner, registered with DTI" },
  partnership: { title: "Partnership", hint: "Two or more partners, registered with SEC" },
  corporation: { title: "Corporation", hint: "Including One Person Corporations, SEC" },
  cooperative: { title: "Cooperative", hint: "Member-owned, registered with CDA" },
};

const AGREEMENTS = [
  { key: "terms", field: "acceptTerms", modal: "terms", title: "Terms and Conditions", summary: "How selling on BloomCraft works: listings, orders, payouts and fees." },
  { key: "vendor", field: "acceptVendorAgreement", modal: "vendorAgreement", title: "Vendor Agreement", summary: "Your responsibilities for quality, delivery and customer care." },
  { key: "privacy", field: "acceptDataPrivacy", modal: "privacy", title: "Data Privacy Policy", summary: "How we keep your documents and your customers' data safe (RA 10173)." },
];

// Review: one card per step, with "Edit" going back to it
const reviewParts = computed(() => {
  const reg = registration.value;
  const agreed = AGREEMENTS.filter((a) => formData[a.field]).length;
  return [
    { step: 1, title: "Your shop", rows: [["Shop name", formData.storeName], ["Structure", formData.businessType ? formatBusinessType(formData.businessType) : ""], ["Address", formData.storeAddress], ["Delivers to", formData.serviceAreas], ["Hours", formData.operatingHours]] },
    { step: 2, title: "About you", rows: [["Owner", [formData.ownerName, formData.position].filter(Boolean).join(", ")], ["Phone", formData.contactNumber], ["Email", formData.email], ["ID", [GOVERNMENT_IDS[formData.governmentIdType], formData.governmentIdNumber].filter(Boolean).join(" ")]] },
    { step: 3, title: "Documents", rows: [["Registered name", formData.registeredBusinessName], [reg.numberLabel, formData[reg.field]], ["TIN", formData.birTin], ["Business permit", [formData.mayorPermitNumber, formData.mayorPermitValidUntil && `valid until ${formData.mayorPermitValidUntil}`].filter(Boolean).join(", ")], ["Barangay clearance", formData.barangayClearanceNumber]] },
    { step: 4, title: "Agreements and extras", rows: [["Agreements", agreed === AGREEMENTS.length ? "All three accepted" : agreed ? `${agreed} of 3 accepted` : ""], ["Facebook", formData.facebookPage || "—"], ["Instagram", formData.instagramPage || "—"]] },
  ];
});
const reviewDocs = computed(() =>
  [
    ["government_id", "Government ID"],
    ["selfie_with_id", "Selfie with ID"],
    ["proof_of_address", "Proof of address"],
    ["business_registration", registration.value.certificate],
    ["bir_certificate", "BIR Form 2303"],
    ["mayor_permit", "Mayor's / Business Permit"],
    ["barangay_clearance", "Barangay Business Clearance"],
  ].map(([field, label]) => ({ field, label, ok: hasSelectedFile(field), name: fileInfo[field]?.name })),
);

// Finished steps can be reopened from the step list or the review
const goToStep = (step) => {
  if (step >= currentStep.value) return;
  currentStep.value = step;
  window.scrollTo({ top: 0, behavior: "smooth" });
  saveProgress();
};

const copied = ref(false);
const copyApplicationId = async () => {
  try {
    await navigator.clipboard.writeText(String(applicationId.value));
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
  } catch (_) {
    // clipboard blocked: the ID is on screen to copy by hand
  }
};

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
const caviteLocations = [...caviteCities, ...caviteMunicipalities];

// Cavite's towns and cities, kept in public/geo so the map doesn't depend on
// another site (from faeldon/philippines-json-maps, MIT)
const CAVITE_BOUNDARY_URL = `${import.meta.env.BASE_URL}geo/cavite-municities.json`;
const storeLocationMap = ref(null);
const mapLoading = ref(true);
let mapInstance = null;
let storeMarker = null;
let caviteBoundary = null;
let caviteBoundaryLayer = null;
const storeMarkerIcon = L.icon({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

const pointInRing = ([longitude, latitude], ring) => {
  let isInside = false;
  for (let current = 0, previous = ring.length - 1; current < ring.length; previous = current++) {
    const [currentLongitude, currentLatitude] = ring[current];
    const [previousLongitude, previousLatitude] = ring[previous];
    const crossesLatitude = (currentLatitude > latitude) !== (previousLatitude > latitude);
    const intersectionLongitude =
      ((previousLongitude - currentLongitude) * (latitude - currentLatitude)) /
        (previousLatitude - currentLatitude) +
      currentLongitude;

    if (crossesLatitude && longitude < intersectionLongitude) isInside = !isInside;
  }
  return isInside;
};

const pointInPolygon = (point, coordinates) =>
  pointInRing(point, coordinates[0]) && !coordinates.slice(1).some((hole) => pointInRing(point, hole));

const isInsideCavite = ({ lat, lng }) =>
  caviteBoundary?.features.some(({ geometry }) => {
    const point = [lng, lat];
    if (geometry.type === "Polygon") return pointInPolygon(point, geometry.coordinates);
    return geometry.coordinates.some((polygon) => pointInPolygon(point, polygon));
  }) ?? false;

const isCaviteReverseGeocode = (address) => {
  const parts = Object.values(address || {}).join(" ").toLowerCase();
  return parts.includes("cavite");
};

const reverseGeocodeStoreLocation = async ({ lat, lng }) => {
  const response = await fetch(
    `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&addressdetails=1`,
    { headers: { "Accept-Language": "en" } },
  );
  if (!response.ok) throw new Error("Unable to look up this location.");

  const result = await response.json();
  if (!isCaviteReverseGeocode(result.address)) {
    throw new Error("This location is not in Cavite.");
  }
  return result.display_name;
};

const restoreMarker = () => {
  if (storeMarker && formData.storeLatitude !== null && formData.storeLongitude !== null) {
    storeMarker.setLatLng([formData.storeLatitude, formData.storeLongitude]);
  }
};

const setStorePin = async (latLng) => {
  if (!isInsideCavite(latLng)) {
    restoreMarker();
    explain("Pin your store inside Cavite", "BloomCraft serves shops in Cavite for now. Move the pin onto your shop's location in Cavite.", { tone: "info", field: "storeAddress" });
    return;
  }

  try {
    const address = await reverseGeocodeStoreLocation(latLng);
    formData.storeLatitude = Number(latLng.lat.toFixed(7));
    formData.storeLongitude = Number(latLng.lng.toFixed(7));
    formData.storeAddress = address;

    if (!storeMarker) {
      storeMarker = L.marker(latLng, { draggable: true, icon: storeMarkerIcon }).addTo(mapInstance);
      storeMarker.on("dragend", () => setStorePin(storeMarker.getLatLng()));
    } else {
      storeMarker.setLatLng(latLng);
    }
  } catch (error) {
    restoreMarker();
    explain("We couldn't use that spot", error.message || "Try placing the pin again, a little closer to your shop.", { tone: "info", field: "storeAddress" });
  }
};

const initialiseStoreLocationMap = async () => {
  await nextTick();
  if (mapInstance || !storeLocationMap.value) return;

  try {
    const response = await fetch(CAVITE_BOUNDARY_URL);
    if (!response.ok) throw new Error("Could not load the Cavite boundary.");
    caviteBoundary = await response.json();

    mapInstance = L.map(storeLocationMap.value, { zoomControl: true, maxBoundsViscosity: 1 });
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 18,
      attribution: "© OpenStreetMap contributors",
    }).addTo(mapInstance);
    caviteBoundaryLayer = L.geoJSON(caviteBoundary, {
      style: { color: "#20734d", weight: 2, fillColor: "#48bb78", fillOpacity: 0.12 },
    }).addTo(mapInstance);
    const bounds = caviteBoundaryLayer.getBounds();
    mapInstance.fitBounds(bounds, { padding: [12, 12] });
    mapInstance.setMinZoom(mapInstance.getZoom());
    mapInstance.setMaxBounds(bounds.pad(0.05));
    mapInstance.on("click", ({ latlng }) => setStorePin(latlng));

    if (formData.storeLatitude !== null && formData.storeLongitude !== null) {
      const savedPin = L.latLng(formData.storeLatitude, formData.storeLongitude);
      if (isInsideCavite(savedPin)) {
        storeMarker = L.marker(savedPin, { draggable: true, icon: storeMarkerIcon }).addTo(mapInstance);
        storeMarker.on("dragend", () => setStorePin(storeMarker.getLatLng()));
      } else {
        formData.storeLatitude = null;
        formData.storeLongitude = null;
      }
    }
  } catch (error) {
    explain("The map didn't load", `${error.message || "Your connection may have dropped."} You can still type your shop's address.`, { tone: "offline" });
  } finally {
    mapLoading.value = false;
  }
};

// ─────────────────────────────────────────────────────────────────────────────
// NEW: Service area options (restricted list only)
// ─────────────────────────────────────────────────────────────────────────────
const serviceAreaOptions = [
  { value: "Cavite", label: "Cavite" },
  { value: "Metro Manila", label: "Metro Manila" },
];
const serviceAreaValues = serviceAreaOptions.map((area) => area.value);

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

const operatingSchedules = ref([{ id: 1, days: [], opening_time: "", closing_time: "" }]);

// One row per day: open or closed, with its own hours. Days with the same
// hours are grouped into operatingSchedules, the shape the server expects.
const WEEKDAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
const weekHours = reactive(Object.fromEntries(daysOfWeek.map((day) => [day, { open: false, from: "", to: "" }])));
const firstOpenDay = computed(() => daysOfWeek.find((day) => weekHours[day].open) || null);
const openDayCount = computed(() => daysOfWeek.filter((day) => weekHours[day].open).length);

const syncWeekFromSchedules = () => {
  for (const day of daysOfWeek) Object.assign(weekHours[day], { open: false, from: "", to: "" });
  for (const schedule of operatingSchedules.value) {
    for (const day of schedule.days) Object.assign(weekHours[day], { open: true, from: schedule.opening_time, to: schedule.closing_time });
  }
};
watch(
  weekHours,
  () => {
    const groups = new Map();
    for (const day of daysOfWeek) {
      const h = weekHours[day];
      if (!h.open) continue;
      const key = `${h.from}|${h.to}`;
      if (!groups.has(key)) groups.set(key, { id: groups.size + 1, days: [], opening_time: h.from, closing_time: h.to });
      groups.get(key).days.push(day);
    }
    operatingSchedules.value = [...groups.values()];
  },
  { deep: true },
);

// Hours a newly opened day starts with: the closest open day's, else 8 AM–5 PM
const suggestedHours = (day) => {
  const i = daysOfWeek.indexOf(day);
  const near = [...daysOfWeek].sort((x, y) => Math.abs(daysOfWeek.indexOf(x) - i) - Math.abs(daysOfWeek.indexOf(y) - i))
    .find((d) => d !== day && weekHours[d].open && weekHours[d].from && weekHours[d].to);
  return near ? { from: weekHours[near].from, to: weekHours[near].to } : { from: "08:00", to: "17:00" };
};
const setDayOpen = (day, open) => {
  if (open && !(weekHours[day].from && weekHours[day].to)) Object.assign(weekHours[day], suggestedHours(day));
  weekHours[day].open = open;
};
const openOnly = (days) => {
  const hours = firstOpenDay.value ? { from: weekHours[firstOpenDay.value].from || "08:00", to: weekHours[firstOpenDay.value].to || "17:00" } : { from: "08:00", to: "17:00" };
  for (const day of daysOfWeek) {
    const open = days.includes(day);
    if (open && !weekHours[day].open) Object.assign(weekHours[day], hours);
    weekHours[day].open = open;
  }
};
const useHoursEverywhere = (from) => {
  const { from: start, to: end } = weekHours[from];
  for (const day of daysOfWeek) if (weekHours[day].open) Object.assign(weekHours[day], { from: start, to: end });
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
  formData.operatingHours = operatingSchedules.value
    .filter((schedule) => schedule.days.length && schedule.opening_time && schedule.closing_time)
    .map((schedule) => `${formatDays(schedule.days)}: ${formatTime(schedule.opening_time)} – ${formatTime(schedule.closing_time)}`)
    .join("; ");
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
  business_registration: null,
  bir_certificate: null,
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
      "governmentIdType",
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
      "cdaNumber",
      "registeredBusinessName",
      "mayorPermitValidUntil",
      "businessRegistration",
      "business_registration",
      "birCertificate",
      "bir_certificate",
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
const hasDraggedFiles = (event) =>
  Array.from(event.dataTransfer?.types || []).includes("Files");

const clearDragOver = () => {
  Object.keys(dragOver).forEach((fieldName) => {
    dragOver[fieldName] = false;
  });
};

const handleDragEnter = (event, fieldName) => {
  if (hasDraggedFiles(event)) dragOver[fieldName] = true;
};

const handleDragOver = (event, fieldName) => {
  if (hasDraggedFiles(event)) dragOver[fieldName] = true;
};

const handleDragLeave = (event, fieldName) => {
  if (!event.currentTarget?.contains(event.relatedTarget)) dragOver[fieldName] = false;
};

const handleDrop = (event, fieldName) => {
  event.preventDefault();
  dragOver[fieldName] = false;
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
        explain("That file can't be used", validation.message, { tone: "info", field: fieldName });
        event.target.value = "";
        return;
      }
      if (fileInfo[fieldName]?.url) URL.revokeObjectURL(fileInfo[fieldName].url);
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
          explain(`"${file.name}" can't be used`, validation.message, { tone: "info", field: fieldName });
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
    explain("The file didn't attach", "Please choose it again. If it keeps happening, try a smaller photo or a PDF.", { tone: "info" });
  }
};

const validateFile = (file, fieldName) => {
  const maxSizes = {
    government_id: 5 * 1024 * 1024,
    selfie_with_id: 5 * 1024 * 1024,
    proof_of_address: 5 * 1024 * 1024,
    barangay_clearance: 10 * 1024 * 1024,
    mayor_permit: 10 * 1024 * 1024,
    business_registration: 10 * 1024 * 1024,
    bir_certificate: 10 * 1024 * 1024,
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
    individual: "Sole proprietorship (DTI)",
    partnership: "Partnership (SEC)",
    corporation: "Corporation (SEC)",
    cooperative: "Cooperative (CDA)",
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
        errors.storeAddress = "Please enter your physical store address";
        isValid = false;
      }
      if (formData.storeLatitude === null || formData.storeLongitude === null) {
        errors.storeAddress = "Pin your shop on the map, inside Cavite";
        isValid = false;
      } else if (!isInsideCavite({ lat: formData.storeLatitude, lng: formData.storeLongitude })) {
        errors.storeAddress = "The pin has to be inside Cavite";
        isValid = false;
      }
      // NEW: service area checkboxes
      if (selectedServiceAreas.value.length === 0) {
        errors.serviceAreas = "Please select at least one service area";
        isValid = false;
      }
      {
        const openDays = daysOfWeek.filter((day) => weekHours[day].open);
        const noTimes = openDays.find((day) => !weekHours[day].from || !weekHours[day].to);
        const backwards = openDays.find((day) => weekHours[day].from && weekHours[day].to && weekHours[day].from >= weekHours[day].to);
        if (!openDays.length) {
          errors.operatingHours = "Switch on at least one day you're open";
          isValid = false;
        } else if (noTimes) {
          errors.operatingHours = `Add the opening and closing time for ${noTimes}`;
          isValid = false;
        } else if (backwards) {
          errors.operatingHours = `On ${backwards}, the closing time must be after the opening time`;
          isValid = false;
        }
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
      } else if (!phoneOk(formData.contactNumber)) {
        errors.contactNumber = "Use a PH number, like 0917 123 4567";
        isValid = false;
      }
      if (!formData.governmentIdType) {
        errors.governmentIdType = "Choose the type of government ID";
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

    case 3: {
      const reg = registration.value;
      const need = (ok, key, message) => {
        if (!ok) {
          errors[key] = message;
          isValid = false;
        }
      };
      need(formData.registeredBusinessName.trim(), "registeredBusinessName", "Registered business name is required");
      need(String(formData[reg.field]).trim(), reg.field, "Registration number is required");
      need(hasSelectedFile("business_registration"), "business_registration", "Upload the certificate");
      need(formData.birTin.trim(), "birTin", "TIN is required");
      if (formData.birTin.trim()) need(tinOk(formData.birTin), "birTin", "Enter your 9-digit TIN");
      need(hasSelectedFile("bir_certificate"), "bir_certificate", "Upload your BIR Form 2303");
      need(formData.mayorPermitNumber.trim(), "mayorPermitNumber", "Permit number is required");
      need(formData.mayorPermitValidUntil, "mayorPermitValidUntil", "Enter the date the permit is valid until");
      if (formData.mayorPermitValidUntil) need(formData.mayorPermitValidUntil >= todayYmd, "mayorPermitValidUntil", "Expired. Upload your renewed permit");
      need(hasSelectedFile("mayor_permit"), "mayor_permit", "Upload your Mayor's / Business Permit");
      need(formData.barangayClearanceNumber.trim(), "barangayClearanceNumber", "Clearance number is required");
      need(hasSelectedFile("barangay_clearance"), "barangay_clearance", "Upload your Barangay Business Clearance");
      break;
    }

    case 4:
      if (!formData.acceptTerms) {
        errors.acceptTerms = "Accept to continue";
        isValid = false;
      }
      if (!formData.acceptVendorAgreement) {
        errors.acceptVendorAgreement = "Accept to continue";
        isValid = false;
      }
      if (!formData.acceptDataPrivacy) {
        errors.acceptDataPrivacy = "Accept to continue";
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
    const errorCount = Object.keys(errors).length;
    const firstErrorKey = Object.keys(errors)[0];
    explain(
      errorCount === 1 ? "One detail is still needed" : `${errorCount} details are still needed`,
      errorCount === 1 ? errors[firstErrorKey] : "They're marked below each field. Fill them in to continue — your progress is saved on this device.",
      { tone: "info", field: firstErrorKey },
    );
    nextTick(jumpToProblem);
  }
};

watch(currentStep, (step) => {
  if (step === 1) {
    nextTick(() => {
      mapInstance?.invalidateSize();
      if (mapInstance && caviteBoundaryLayer && formData.storeLatitude === null) mapInstance.fitBounds(caviteBoundaryLayer.getBounds(), { padding: [12, 12] });
    });
  }
  if (formProblem.value && formProblem.value.step !== step) formProblem.value = null;
});

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
    // Persist the structured weekly schedules so UI state is restored on reload.
    selectedServiceAreas: [...selectedServiceAreas.value],
    operatingSchedules: JSON.parse(JSON.stringify(operatingSchedules.value)),
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
          selectedServiceAreas.value = parsed.selectedServiceAreas.filter((area) =>
            serviceAreaValues.includes(area),
          );
        }
        if (Array.isArray(parsed.operatingSchedules) && parsed.operatingSchedules.length) {
          operatingSchedules.value = parsed.operatingSchedules.map((schedule, index) => ({
            id: schedule.id || index + 1,
            days: Array.isArray(schedule.days) ? schedule.days : [],
            opening_time: schedule.opening_time || "",
            closing_time: schedule.closing_time || "",
          }));
        } else if (parsed.operatingHoursConfig) {
          operatingSchedules.value = [{
            id: 1,
            days: parsed.operatingHoursConfig.days || [],
            opening_time: parsed.operatingHoursConfig.startTime || "",
            closing_time: parsed.operatingHoursConfig.endTime || "",
          }];
        }

        // Picked up where they left off; the note offers a fresh start
        if (currentStep.value > 1 || Object.values(parsed.formData || {}).some((v) => v)) {
          resumed.value = true;
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
  operatingSchedules.value = [{ id: 1, days: [], opening_time: "", closing_time: "" }];
  syncWeekFromSchedules();

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
    explain("One last check", "Tick the box confirming your details are accurate, then submit.", { tone: "info", field: "confirmAccuracy" });
    return;
  }

  const requiredUploadFields = [
    ["government_id", "Government ID"],
    ["selfie_with_id", "Selfie with ID"],
    ["proof_of_address", "Proof of Address"],
    ["barangay_clearance", "Barangay Clearance"],
    ["mayor_permit", "Mayor's Permit"],
    ["business_registration", "registration certificate"],
    ["bir_certificate", "BIR Form 2303"],
  ];

  const staleUpload = requiredUploadFields.find(
    ([fieldName]) =>
      hasStoredFileInfo(fieldName) && !hasLiveFileObject(fieldName),
  );

  if (staleUpload) {
    const [fieldName, label] = staleUpload;
    const step =
      getStepForErrorField(fieldName) ??
      getStepForErrorField(toCamelCase(fieldName));

    if (step) {
      currentStep.value = step;
    }

    errors[fieldName] =
      `${label} needs to be uploaded again before submitting.`;
    errors[toCamelCase(fieldName)] = errors[fieldName];
    window.scrollTo({ top: 0, behavior: "smooth" });
    explain(`Add your ${label} again`, "Files can't be kept after the page reloads, so please choose this one again before you submit.", { tone: "info", field: fieldName });
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
    submitData.append("physical_store_address", formData.storeAddress);

    submitData.append(
      "operating_schedules",
      JSON.stringify(
        operatingSchedules.value.map(({ days, opening_time, closing_time }) => ({
          days,
          opening_time,
          closing_time,
        })),
      ),
    );

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
    for (const field of ["business_registration", "bir_certificate"]) {
      if (fileObjects[field] instanceof File) submitData.append(field, fileObjects[field]);
    }
    // Only the registration number that matches the business structure
    const kept = toSnakeCase(registration.value.field);
    for (const key of ["dti_number", "sec_number", "cda_number"]) {
      if (key !== kept) submitData.delete(key);
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
        if (firstMessage) explain("A few details need another look", firstMessage, { field: Object.keys(data.errors)[0] });
      } else {
        explain("We couldn't send your application", data.message || "Please try again in a moment. Nothing you entered was lost.");
      }
    }
  } catch (error) {
    console.error("Registration error:", error);
    const responseData = error?.response?.data;
    // Applications were closed while the form was open
    if (error?.response?.status === 403) refreshPlatform();

    if (responseData?.errors) {
      const firstMessage = applyBackendErrors(responseData.errors);
      explain("A few details need another look", firstMessage || responseData.message || "They're marked on the form.", { field: Object.keys(responseData.errors)[0] });
    } else if (!error?.response) {
      explain("BloomCraft didn't answer", "Your connection dropped or the server is waking up. Wait a moment and submit again — nothing you entered was lost.", { tone: "offline" });
    } else {
      explain("We couldn't send your application", responseData?.message || "Please try again in a moment. Nothing you entered was lost.");
    }
  } finally {
    isSubmitting.value = false;
  }
};

const closeModal = () => {
  showApplicationModal.value = false;
  router.push("/");
};

// ─────────────────────────────────────────────────────────────────────────────
// Lifecycle hooks (unchanged)
// ─────────────────────────────────────────────────────────────────────────────
const { status: platform, refresh: refreshPlatform } = usePlatformStatus();

onMounted(async () => {
  refreshPlatform();
  loadProgress();
  syncWeekFromSchedules();
  await initialiseStoreLocationMap();
  saveInterval.value = setInterval(saveProgress, 30000);

  const saved = localStorage.getItem("vendorRegistrationProgress");
  if (saved) {
    const parsed = JSON.parse(saved);
    const oneDayAgo = new Date().getTime() - 24 * 60 * 60 * 1000;
    if (parsed.timestamp && parsed.timestamp < oneDayAgo) {
      clearProgress();
    }
  }
  window.addEventListener("dragend", clearDragOver);
});

onUnmounted(() => {
  if (saveInterval.value) clearInterval(saveInterval.value);
  window.removeEventListener("dragend", clearDragOver);
  mapInstance?.remove();

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
/* ── Vendor application (inside AuthShell) ────────────────────────────── */
.vr-problem {
  margin-bottom: 22px;
}

/* Steps: numbered, because it is a sequence; finished ones reopen */
.vr-steps {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 6px;
  margin: 0 0 30px;
  padding: 0;
  list-style: none;
}
.vr-steps button {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 10px 12px;
  border: 0;
  border-bottom: 3px solid var(--line);
  background: none;
  font: inherit;
  text-align: left;
  color: var(--ink-soft);
  cursor: pointer;
}
.vr-steps button:disabled {
  cursor: default;
}
.vr-steps button:not(:disabled):hover .vr-step-name {
  color: var(--leaf-deep);
}
.vr-steps .on button {
  border-color: var(--leaf-deep);
  color: var(--ink);
}
.vr-steps .done button {
  border-color: rgba(142, 68, 58, 0.45);
}
.vr-dot {
  flex: none;
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: #fff;
  font-size: 13px;
  font-weight: 600;
}
.vr-steps .on .vr-dot {
  border-color: var(--espresso);
  background: var(--espresso);
  color: #f7f2ea;
}
.vr-steps .done .vr-dot {
  border-color: rgba(142, 68, 58, 0.45);
  color: var(--leaf-deep);
}
.vr-dot svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.vr-step-name {
  min-width: 0;
  overflow: hidden;
  font-size: 14px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Step header */
.vr-head {
  margin-bottom: 18px;
}
.vr-head h2 {
  margin: 0 0 6px;
  font-size: 36px;
}
.vr-head p {
  margin: 0;
  max-width: 60ch;
  font-size: 15px;
  line-height: 1.6;
  color: var(--ink-soft);
}

/* Cards */
.vr-card {
  margin-bottom: 16px;
  padding: 22px 24px;
  border: 1px solid var(--line-soft);
  border-radius: 20px;
  background: #fff;
}
.vr-card h3 {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 16px;
  font-family: var(--font-sans);
  font-size: 17px;
  font-weight: 600;
  letter-spacing: 0;
  line-height: 1.3;
  color: var(--ink);
}
.vr-card h3 small {
  font-size: 13px;
  font-weight: 400;
  color: var(--ink-soft);
}
.vr-sub {
  margin: -10px 0 16px;
  font-size: 14px;
  color: var(--ink-soft);
}
.vr-badge {
  display: inline-grid;
  place-items: center;
  min-width: 42px;
  height: 24px;
  padding: 0 8px;
  border-radius: 7px;
  background: #f4e7e2;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.04em;
  color: var(--leaf-deep);
}

/* Fields */
.vr-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 8px 18px;
}
.vr-field {
  display: flex;
  flex-direction: column;
  gap: 7px;
  margin-bottom: 8px;
}
.vr-grid .vr-field {
  margin-bottom: 0;
}
.vr-grid + .vr-field,
.vr-grid + .vr-docs,
.vr-grid + .dd,
.vr-field + .dd,
.vr-docs + .vr-grid {
  margin-top: 16px;
}
.vr-label {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 14px;
  font-weight: 500;
  color: var(--ink);
}
.vr-label small {
  font-weight: 400;
  font-variant-numeric: tabular-nums;
  color: var(--ink-soft);
}
/* One size for every box on the form */
.vr-input {
  width: 100%;
  height: 48px;
  padding: 0 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--paper);
  font: 400 15px/1.4 var(--font-sans);
  color: var(--ink);
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
textarea.vr-input {
  height: auto;
  padding: 12px 14px;
  resize: vertical;
}
select.vr-input {
  cursor: pointer;
}
.vr-input:hover {
  border-color: rgba(30, 27, 24, 0.22);
}
.vr-input:focus {
  outline: none;
  border-color: var(--leaf-deep);
  background: #fff;
  box-shadow: 0 0 0 4px rgba(142, 68, 58, 0.12);
}
.vr-input.error {
  border-color: rgba(214, 69, 69, 0.7);
  background: #fffaf8;
}
.vr-narrow {
  max-width: 340px;
}
.vr-hint {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--ink-soft);
}
/* Always rendered with one line reserved, so a message never pushes the form down */
.vr-error {
  min-height: calc(13px * 1.5);
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: #b4413a;
}
.vr-small {
  font-size: 13.5px;
}

/* Business structure cards */
.vr-choices {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.vr-choice {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 16px 18px;
  border: 1.5px solid var(--line);
  border-radius: 16px;
  background: var(--paper);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.vr-choice:hover {
  border-color: rgba(30, 27, 24, 0.25);
}
.vr-choice.on {
  border-color: var(--leaf-deep);
  background: #fdf6f3;
}
.vr-choice input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.vr-choice:has(input:focus-visible) {
  box-shadow: 0 0 0 3px rgba(142, 68, 58, 0.3);
}
.vr-choice .vr-badge {
  align-self: flex-start;
  margin-bottom: 6px;
}
.vr-choice b {
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
}
.vr-choice small {
  font-size: 13px;
  color: var(--ink-soft);
}

/* Map */
.store-location-map {
  position: relative;
  height: 300px;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 16px;
  background: var(--paper);
}
.store-location-map + .vr-hint {
  margin-top: 10px;
}
.store-location-map-canvas {
  width: 100%;
  height: 100%;
}
.map-status {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  margin: 0;
  background: rgba(250, 248, 244, 0.88);
  font-size: 13px;
  color: var(--ink-soft);
  pointer-events: none;
}

/* Chips (delivery areas) */
.vr-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.vr-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 48px;
  padding: 0 18px;
  border: 1.5px solid var(--line);
  border-radius: 999px;
  background: var(--paper);
  font-size: 14.5px;
  font-weight: 500;
  color: var(--ink);
  cursor: pointer;
}
.vr-chip input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.vr-chip svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.4;
  stroke-linecap: round;
  stroke-linejoin: round;
  opacity: 0.25;
}
.vr-chip.on {
  border-color: var(--leaf-deep);
  background: #fdf6f3;
  color: var(--leaf-deep);
}
.vr-chip.on svg {
  opacity: 1;
}
.vr-chip:has(input:focus-visible),
.vr-day:has(input:focus-visible) {
  box-shadow: 0 0 0 3px rgba(142, 68, 58, 0.3);
}

/* Opening hours: a row per day */
.vr-week {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--line);
  border-radius: 16px;
  overflow: hidden;
}
.vr-dayrow {
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 64px;
  padding: 8px 16px;
  background: #fff;
}
.vr-dayrow + .vr-dayrow {
  border-top: 1px solid var(--line-soft);
}
.vr-dayrow.closed {
  background: var(--paper);
}
.vr-dayrow .vr-switch {
  flex: none;
  width: 172px;
}
.vr-dayrow .vr-switch b {
  font-size: 14.5px;
  font-weight: 500;
  color: var(--ink);
}
.vr-dayrow.closed .vr-switch b {
  color: var(--ink-soft);
}
.vr-times {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 10px;
  font-size: 13.5px;
  color: var(--ink-soft);
}
.vr-time {
  max-width: 170px;
}
.vr-closed {
  font-size: 14px;
  color: var(--ink-soft);
}
.vr-quick {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 12px 0 0;
}
.vr-quick button {
  height: 34px;
  padding: 0 14px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: #fff;
  font: 500 13px/1 var(--font-sans);
  color: var(--ink-soft);
  cursor: pointer;
}
.vr-quick button:hover {
  border-color: var(--leaf-deep);
  color: var(--leaf-deep);
}

/* Document tiles */
.vr-docs {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 16px;
}
.vr-docs.two {
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}
.vr-doc {
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-width: 0;
}

/* Sample photos */
.vr-gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, 68px);
  gap: 10px;
}
.vr-gallery figure {
  position: relative;
  margin: 0;
  aspect-ratio: 1;
  overflow: hidden;
  border-radius: 16px;
  background: var(--ivory);
}
.vr-gallery img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.vr-gallery figure button {
  position: absolute;
  top: 4px;
  right: 4px;
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border: 0;
  border-radius: 50%;
  background: rgba(27, 22, 18, 0.7);
  color: #fff;
  cursor: pointer;
}
.vr-gallery svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
}
.vr-gallery-add {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  aspect-ratio: 1;
  border: 1.5px dashed rgba(30, 27, 24, 0.2);
  border-radius: 16px;
  background: var(--paper);
  font-size: 11.5px;
  color: var(--ink-soft);
  cursor: pointer;
}
.vr-gallery-add:hover {
  border-color: var(--leaf-deep);
  color: var(--leaf-deep);
}
.vr-gallery-add input {
  display: none;
}
.vr-gallery-add svg {
  width: 20px;
  height: 20px;
  stroke-width: 1.8;
}

/* Agreements */
.vr-agree {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 6px 18px;
  padding: 14px 16px;
  border: 1.5px solid var(--line);
  border-radius: 16px;
  background: var(--paper);
}
.vr-agree + .vr-agree {
  margin-top: 10px;
}
.vr-agree.on {
  border-color: rgba(63, 122, 85, 0.45);
  background: #f4f9f5;
}
.vr-agree.error {
  border-color: rgba(214, 69, 69, 0.6);
}
.vr-agree-text {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
}
.vr-agree-text b {
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
}
.vr-agree-text small {
  font-size: 13px;
  color: var(--ink-soft);
}
.vr-agree-foot {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 14px;
}
.vr-agree-foot .vr-error {
  min-height: 0;
}
.vr-switch {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}
.vr-switch input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.vr-switch span {
  position: relative;
  width: 46px;
  height: 26px;
  border-radius: 999px;
  background: rgba(30, 27, 24, 0.2);
  transition: background 0.2s;
}
.vr-switch span::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  transition: transform 0.2s;
}
.vr-switch input:checked + span {
  background: #3f7a55;
}
.vr-switch input:checked + span::after {
  transform: translateX(20px);
}
.vr-switch input:focus-visible + span {
  box-shadow: 0 0 0 3px rgba(142, 68, 58, 0.3);
}
.vr-switch em {
  min-width: 64px;
  font-size: 13.5px;
  font-style: normal;
  font-weight: 500;
  color: var(--ink);
}

/* Review */
.vr-review-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 12px;
}
.vr-review-head h3 {
  margin: 0;
}
.vr-review dl {
  display: grid;
  grid-template-columns: minmax(130px, 34%) minmax(0, 1fr);
  gap: 8px 16px;
  margin: 0;
  font-size: 14px;
}
.vr-review dt {
  color: var(--ink-soft);
}
.vr-review dd {
  margin: 0;
  overflow-wrap: anywhere;
  color: var(--ink);
}
.vr-review dd.missing {
  color: #b4413a;
}
.vr-checklist {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 8px 18px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.vr-checklist li {
  display: grid;
  grid-template-columns: 20px minmax(0, 1fr);
  column-gap: 8px;
  font-size: 14px;
  color: #b4413a;
}
.vr-checklist li.ok {
  color: var(--ink);
}
.vr-checklist svg {
  grid-row: span 2;
  width: 18px;
  height: 18px;
  margin-top: 1px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.vr-checklist li.ok svg {
  color: #3f7a55;
}
.vr-checklist small {
  overflow: hidden;
  font-size: 12.5px;
  color: var(--ink-soft);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.vr-confirm {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin: 4px 0 8px;
  padding: 16px 18px;
  border: 1.5px solid var(--line);
  border-radius: 16px;
  background: #fff;
  font-size: 14.5px;
  line-height: 1.55;
  color: var(--ink);
  cursor: pointer;
}
.vr-confirm.on {
  border-color: rgba(63, 122, 85, 0.45);
  background: #f4f9f5;
}
.vr-confirm.error {
  border-color: rgba(214, 69, 69, 0.6);
}
.vr-confirm input {
  flex: none;
  width: 20px;
  height: 20px;
  margin-top: 1px;
  accent-color: #3f7a55;
}

/* Moving between steps */
.vr-nav {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 26px;
  padding-top: 22px;
  border-top: 1px solid var(--line);
}
.vr-nav .af-btn {
  width: auto;
  min-width: 150px;
  padding: 0 20px;
  white-space: nowrap;
}
.vr-saved {
  flex: 1;
  font-size: 13px;
  color: var(--ink-soft);
}
.vr-nav .af-btn:first-child:not(.ghost) {
  margin-left: auto;
}

/* Sent */
.vr-done {
  max-width: 560px;
  padding: 10px 0 20px;
}
.vr-done-mark {
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  margin-bottom: 20px;
  border-radius: 50%;
  background: #e6efe7;
  color: #3f7a55;
}
.vr-done-mark svg {
  width: 30px;
  height: 30px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.4;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.vr-done h2 {
  margin: 0 0 8px;
  font-size: 44px;
}
.vr-done-lead {
  margin: 0 0 22px;
  font-size: 15.5px;
  color: var(--ink-soft);
}
.vr-id {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 6px 14px;
  margin-bottom: 26px;
  padding: 16px 18px;
  border-radius: 16px;
  background: #fff;
  border: 1px solid var(--line-soft);
}
.vr-id span {
  font-size: 13px;
  color: var(--ink-soft);
}
.vr-id b {
  flex: 1;
  font-size: 18px;
  font-weight: 600;
  letter-spacing: 0.02em;
  color: var(--ink);
}
.vr-next {
  margin: 0 0 30px;
  padding: 0;
  list-style: none;
  counter-reset: next;
}
.vr-next li {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 0 0 18px 40px;
  counter-increment: next;
}
.vr-next li::before {
  content: counter(next);
  position: absolute;
  top: 0;
  left: 0;
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: var(--espresso);
  font-size: 12.5px;
  font-weight: 600;
  color: #f7f2ea;
}
.vr-next li:not(:last-child)::after {
  content: "";
  position: absolute;
  top: 30px;
  bottom: 4px;
  left: 12px;
  width: 2px;
  background: var(--line);
}
.vr-next b {
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
}
.vr-next span {
  font-size: 14px;
  color: var(--ink-soft);
}
.vr-done-btn {
  width: auto;
  padding: 0 30px;
}

/* Reading an agreement */
.vr-modal-back {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(27, 22, 18, 0.5);
  backdrop-filter: blur(4px);
}
.vr-modal {
  position: relative;
  display: flex;
  flex-direction: column;
  width: min(720px, 100%);
  max-height: 86vh;
  border-radius: 22px;
  background: var(--paper);
  box-shadow: 0 40px 80px -30px rgba(27, 22, 18, 0.6);
}
.vr-modal-x {
  position: absolute;
  top: 14px;
  right: 14px;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border: 0;
  border-radius: 12px;
  background: none;
  color: var(--ink-soft);
  cursor: pointer;
}
.vr-modal-x:hover {
  background: var(--ivory);
  color: var(--ink);
}
.vr-modal-x svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
}
.vr-modal-body {
  padding: 30px 34px 10px;
  overflow-y: auto;
  font-size: 14.5px;
  line-height: 1.7;
  color: #4a433d;
}
.vr-modal-body :deep(h2) {
  margin: 0 0 14px;
  font-size: 30px;
}
.vr-modal-body :deep(h3) {
  margin: 22px 0 8px;
  font-family: var(--font-sans);
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0;
  color: var(--ink);
}
.vr-modal-body :deep(ul) {
  padding-left: 20px;
}
.vr-modal-foot {
  display: flex;
  justify-content: flex-end;
  padding: 16px 34px 24px;
  border-top: 1px solid var(--line);
}
.vr-modal-foot .af-btn {
  width: auto;
  padding: 0 26px;
}

@media (max-width: 1100px) {
  .vr-docs {
    grid-template-columns: minmax(0, 1fr);
  }
  .vr-step-name {
    font-size: 12.5px;
  }
}
@media (max-width: 640px) {
  .vr-card {
    padding: 18px 16px;
    border-radius: 16px;
  }
  .vr-grid,
  .vr-choices,
  .vr-docs.two,
  .vr-checklist {
    grid-template-columns: minmax(0, 1fr);
  }
  .vr-steps button {
    justify-content: center;
    padding: 8px 2px 10px;
  }
  .vr-step-name {
    display: none;
  }
  .vr-steps .on .vr-step-name {
    display: none;
  }
  .vr-head h2 {
    font-size: 30px;
  }
  .vr-dayrow {
    flex-wrap: wrap;
    gap: 8px 12px;
    padding: 12px 14px;
  }
  .vr-dayrow .vr-switch {
    width: auto;
    flex: 1;
  }
  .vr-times {
    flex-basis: 100%;
  }
  .vr-time {
    width: 0;
    min-width: 0;
    max-width: none;
    flex: 1;
    padding: 0 6px 0 10px;
    font-size: 14px;
  }
  .vr-week {
    margin: 0 -4px;
  }
  .vr-times {
    gap: 6px;
  }
  .vr-agree {
    grid-template-columns: minmax(0, 1fr);
  }
  .vr-review dl {
    grid-template-columns: minmax(0, 1fr);
    gap: 2px;
  }
  .vr-review dd {
    margin-bottom: 8px;
  }
  .vr-nav {
    flex-wrap: wrap;
  }
  .vr-saved {
    order: -1;
    flex-basis: 100%;
  }
  .vr-nav .af-btn {
    flex: 1;
    min-width: 0;
  }
}
</style>
