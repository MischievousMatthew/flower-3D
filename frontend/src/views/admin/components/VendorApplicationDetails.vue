<template>
  <div class="app-details">
    <!-- Documents first: they're what gets reviewed -->
    <section class="card">
      <div class="card-head">
        <span class="card-label">Documents</span>
        <span class="sub">{{ uploadedCount }} of {{ documents.length }} uploaded</span>
      </div>
      <div class="doc-grid">
        <button
          v-for="doc in documents"
          :key="doc.key"
          type="button"
          class="doc"
          :class="{ missing: !doc.url }"
          :disabled="!doc.url"
          @click="$emit('view-document', doc.url, doc.label)"
        >
          <span class="doc-thumb">
            <img v-if="doc.url && !isFile(doc.url)" :src="doc.url" :alt="doc.label" loading="lazy" />
            <svg v-else-if="doc.url" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /></svg>
            <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" /><line x1="8" y1="12" x2="16" y2="12" /></svg>
          </span>
          <span class="doc-label">{{ doc.label }}</span>
          <span class="doc-meta">{{ doc.number || (doc.url ? "View" : "Not uploaded") }}</span>
        </button>
      </div>
    </section>

    <!-- Resubmissions -->
    <section v-if="resubmissions.length" class="card">
      <div class="card-head">
        <span class="card-label">Resubmitted requirements</span>
      </div>
      <article v-for="item in resubmissions" :key="item.id" class="resub">
        <div class="resub-head">
          <span class="strong">{{ item.label }}</span>
          <span class="pill" :class="`rs-${item.status}`">{{ formatStatus(item.status) }}</span>
        </div>
        <p v-if="item.rejection_reason" class="sub">Reason: {{ item.rejection_reason }}</p>
        <div v-if="item.type === 'file'" class="resub-links">
          <button v-if="item.original_url" type="button" class="link-btn" @click="$emit('view-document', item.original_url, `${item.label} (original)`)">View original</button>
          <button v-if="item.resubmitted_url" type="button" class="link-btn" @click="$emit('view-document', item.resubmitted_url, `${item.label} (updated)`)">View updated</button>
        </div>
        <div v-else class="resub-values">
          <span><b>Original:</b> {{ item.original_value || "—" }}</span>
          <span v-if="item.resubmitted_value"><b>Updated:</b> {{ item.resubmitted_value }}</span>
        </div>
        <div v-if="item.status === 'resubmitted' && actionable" class="resub-actions">
          <button type="button" class="mini-btn ok" @click="$emit('approve-resubmission', item)">Accept</button>
          <button type="button" class="mini-btn" @click="$emit('request-again', item)">Ask again</button>
        </div>
      </article>
    </section>

    <section class="card">
      <span class="card-label">Store</span>
      <dl class="facts">
        <dt>Store name</dt><dd>{{ app.store_name }}</dd>
        <dt>Description</dt><dd>{{ app.store_description || "—" }}</dd>
        <dt>Business type</dt><dd>{{ businessType(app.business_type) }}</dd>
        <dt>Address</dt><dd>{{ app.store_address || "—" }}</dd>
        <dt>Service areas</dt><dd>{{ listText(app.service_areas) }}</dd>
        <dt>Operating hours</dt><dd>{{ listText(app.operating_hours) }}</dd>
      </dl>
    </section>

    <section class="card">
      <span class="card-label">Owner</span>
      <dl class="facts">
        <dt>Name</dt><dd>{{ app.owner_name }}</dd>
        <dt>Position</dt><dd>{{ app.position || "—" }}</dd>
        <dt>Contact</dt><dd>{{ app.contact_number || "—" }}</dd>
        <dt>Email</dt><dd>{{ app.email }}</dd>
        <dt>Submitted</dt><dd>{{ formatDateTime(app.submitted_at) }}</dd>
      </dl>
    </section>

    <section class="card">
      <span class="card-label">Business registration</span>
      <dl class="facts">
        <dt>DTI no.</dt><dd>{{ app.dti_number || "—" }}</dd>
        <dt>SEC no.</dt><dd>{{ app.sec_number || "—" }}</dd>
        <dt>BIR / TIN</dt><dd>{{ app.bir_tin || "—" }}</dd>
      </dl>
    </section>

    <section class="card">
      <div class="card-head">
        <span class="card-label">Profile setup</span>
        <span class="strong">{{ app.profile_completion_percentage || 0 }}%</span>
      </div>
      <div class="progress"><span :style="{ width: (app.profile_completion_percentage || 0) + '%' }"></span></div>
      <div class="checks">
        <span :class="{ done: app.payment_details_completed }">{{ app.payment_details_completed ? "✓" : "○" }} Payment</span>
        <span :class="{ done: app.product_details_completed }">{{ app.product_details_completed ? "✓" : "○" }} Products</span>
        <span :class="{ done: app.delivery_details_completed }">{{ app.delivery_details_completed ? "✓" : "○" }} Delivery</span>
      </div>
    </section>

    <section v-if="app.payment_details_completed" class="card">
      <span class="card-label">Payout</span>
      <dl class="facts">
        <dt>Method</dt><dd>{{ payoutMethod(app.payout_method) }}</dd>
        <dt>Account holder</dt><dd>{{ app.account_holder_name || "—" }}</dd>
        <dt>Account no.</dt>
        <dd>
          <span class="masked" title="Masked for security">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
            {{ app.masked_account_number || "Not set" }}
          </span>
        </dd>
        <dt>Bank / e-wallet</dt><dd>{{ app.bank_name || "—" }}</dd>
        <dt>Billing address</dt><dd>{{ app.billing_address || "—" }}</dd>
      </dl>
    </section>

    <section v-if="app.product_details_completed" class="card">
      <span class="card-label">Products & pricing</span>
      <dl class="facts">
        <dt>Product types</dt><dd>{{ listText(app.product_types) }}</dd>
        <dt>Price range</dt><dd>{{ app.formatted_price_range || priceRange(app.price_min, app.price_max) || "—" }}</dd>
        <dt>Same-day delivery</dt><dd>{{ app.same_day_delivery === null || app.same_day_delivery === undefined ? "—" : app.same_day_delivery ? "Yes" : "No" }}</dd>
        <dt>Order cut-offs</dt><dd>{{ cutoffs(app.cutoff_times) }}</dd>
      </dl>
    </section>

    <section v-if="app.delivery_details_completed" class="card">
      <span class="card-label">Delivery & operations</span>
      <dl class="facts">
        <dt>Delivered by</dt><dd>{{ app.delivery_handled_by === "vendor" ? "Vendor" : app.delivery_handled_by ? "Platform" : "—" }}</dd>
        <dt>Delivery fee</dt><dd>{{ app.delivery_fee || "—" }}</dd>
        <dt>Max orders / day</dt><dd>{{ app.max_orders_per_day || "—" }}</dd>
        <dt>Lead time</dt><dd>{{ app.lead_time || "—" }}</dd>
        <dt>Cancellation policy</dt><dd>{{ app.cancellation_policy || "—" }}</dd>
      </dl>
    </section>

    <section v-if="portfolio.length || app.facebook_page || app.instagram_page" class="card">
      <span class="card-label">Portfolio & social</span>
      <div v-if="portfolio.length" class="portfolio">
        <button v-for="(photo, i) in portfolio" :key="i" type="button" @click="$emit('view-document', photo, `Portfolio photo ${i + 1}`)">
          <img :src="photo" :alt="`Portfolio photo ${i + 1}`" loading="lazy" />
        </button>
      </div>
      <dl class="facts">
        <dt>Facebook</dt><dd>{{ app.facebook_page || "—" }}</dd>
        <dt>Instagram</dt><dd>{{ app.instagram_page || "—" }}</dd>
      </dl>
    </section>

    <section v-if="app.rejection_reason" class="card warn">
      <span class="card-label">Rejection reason</span>
      <p class="quote">{{ app.rejection_reason }}</p>
    </section>
    <section v-if="app.admin_notes" class="card">
      <span class="card-label">Admin notes</span>
      <p class="quote">{{ app.admin_notes }}</p>
    </section>
  </div>
</template>

<script setup>
import { computed } from "vue";

/** A vendor application's details and documents (read-only display) */
const props = defineProps({
  app: { type: Object, required: true },
  resubmissions: { type: Array, default: () => [] },
  actionable: { type: Boolean, default: false },
});
defineEmits(["view-document", "approve-resubmission", "request-again"]);

const documents = computed(() => [
  { key: "gov", label: "Government ID", url: props.app.government_id_url, number: props.app.government_id_number },
  { key: "selfie", label: "Selfie with ID", url: props.app.selfie_with_id_url },
  { key: "address", label: "Proof of address", url: props.app.proof_of_address_url },
  { key: "barangay", label: "Barangay clearance", url: props.app.barangay_clearance_url, number: props.app.barangay_clearance_number },
  { key: "mayor", label: "Mayor's permit", url: props.app.mayor_permit_url, number: props.app.mayor_permit_number },
  { key: "logo", label: "Store logo", url: props.app.store_logo_url },
]);
const uploadedCount = computed(() => documents.value.filter((d) => d.url).length);
const portfolio = computed(() => props.app.portfolio_photos_urls ?? []);

const isFile = (url) => /\.pdf|\/raw\//i.test(String(url));
const businessType = (t) => ({ individual: "Sole proprietor", partnership: "Partnership", corporation: "Corporation" })[t] ?? t ?? "—";
const payoutMethod = (m) => ({ bank: "Bank transfer", gcash: "GCash", maya: "Maya" })[m] ?? m ?? "—";
const formatStatus = (s) =>
  ({
    needs_resubmission: "Needs resubmission",
    pending_review: "Pending review",
    pending_resubmission: "Waiting for vendor",
    resubmitted: "Resubmitted",
    completed: "Accepted",
    approved: "Accepted",
  })[s] ?? s;

const listText = (value) => {
  if (value === null || value === undefined || value === "") return "—";
  let parsed = value;
  if (typeof value === "string") {
    try {
      parsed = JSON.parse(value);
    } catch {
      return value;
    }
  }
  if (Array.isArray(parsed)) return parsed.map((v) => (typeof v === "object" ? Object.values(v).join(" ") : v)).join(", ") || "—";
  if (parsed && typeof parsed === "object") return Object.entries(parsed).map(([k, v]) => `${k}: ${typeof v === "object" ? Object.values(v ?? {}).join("–") : v}`).join(", ");
  return String(parsed);
};
const priceRange = (min, max) => {
  if (min === null || min === undefined || max === null || max === undefined) return null;
  const fmt = (n) => `₱${Number(n).toLocaleString("en-PH", { minimumFractionDigits: 2 })}`;
  return `${fmt(min)} – ${fmt(max)}`;
};
const cutoffs = (list) => {
  if (!Array.isArray(list) || !list.length) return "—";
  return list.map((c) => [c.day || c.weekday, c.time || c.cutoff || c.cutoff_time].filter(Boolean).join(": ")).join(", ");
};
const formatDateTime = (v) =>
  v ? new Date(v).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "—";
</script>

<style scoped>
.app-details {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.card {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 14px 16px;
  min-width: 0;
}
.card.warn {
  border-color: #fecaca;
  background: #fff7f7;
}
.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}
.card-label {
  display: block;
  font-size: 10.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: #94a3b8;
  margin-bottom: 8px;
}
.card-head .card-label {
  margin: 0;
}
.sub {
  font-size: 11.5px;
  color: #94a3b8;
}
.strong {
  font-weight: 600;
  color: #1e2739;
  font-size: 13px;
}
.facts {
  display: grid;
  grid-template-columns: 130px 1fr;
  gap: 7px 12px;
  margin: 0;
  font-size: 13px;
}
.facts dt {
  color: #94a3b8;
}
.facts dd {
  margin: 0;
  color: #1e2739;
  overflow-wrap: anywhere;
}
.doc-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.doc {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 8px;
  border: 1px solid #e9edf3;
  border-radius: 10px;
  background: #fbfcfe;
  cursor: pointer;
  text-align: left;
  transition: border-color 0.15s, box-shadow 0.15s;
  min-width: 0;
}
.doc:hover:not(:disabled) {
  border-color: #c7c2fb;
  box-shadow: 0 4px 14px rgba(124, 111, 247, 0.12);
}
.doc.missing {
  cursor: default;
  opacity: 0.7;
}
.doc-thumb {
  width: 100%;
  aspect-ratio: 4 / 3;
  border-radius: 7px;
  background: #f1f5f9;
  color: #94a3b8;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  margin-bottom: 6px;
}
.doc-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.doc-label {
  font-size: 12px;
  font-weight: 500;
  color: #1e2739;
}
.doc-meta {
  font-size: 11px;
  color: #94a3b8;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
}
.doc.missing .doc-meta {
  color: #dc2626;
}
.progress {
  height: 6px;
  border-radius: 6px;
  background: #f1f5f9;
  overflow: hidden;
}
.progress span {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, #a79ffb, #6d5ff0);
}
.checks {
  display: flex;
  gap: 14px;
  margin-top: 10px;
  font-size: 12.5px;
  color: #94a3b8;
}
.checks .done {
  color: #15803d;
}
.masked {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  font-size: 12px;
  padding: 2px 7px;
  border-radius: 6px;
  background: #f8fafc;
  border: 1px solid #eef1f6;
  color: #475569;
}
.portfolio {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 10px;
}
.portfolio button {
  padding: 0;
  border: none;
  border-radius: 8px;
  overflow: hidden;
  aspect-ratio: 1;
  cursor: pointer;
}
.portfolio img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.quote {
  margin: 0;
  font-size: 13px;
  color: #334155;
  white-space: pre-wrap;
}
.resub {
  padding: 10px 0;
  border-top: 1px solid #f1f5f9;
}
.resub:first-of-type {
  border-top: none;
  padding-top: 0;
}
.resub-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.resub-links,
.resub-actions {
  display: flex;
  gap: 8px;
  margin-top: 6px;
}
.resub-values {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12.5px;
  color: #475569;
  margin-top: 4px;
}
.pill {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 10.5px;
  font-weight: 600;
  background: #f1f5f9;
  color: #64748b;
}
.rs-resubmitted { background: #dbeafe; color: #1d4ed8; }
.rs-needs_resubmission,
.rs-pending_resubmission { background: #fef3c7; color: #b45309; }
.rs-completed,
.rs-approved { background: #dcfce7; color: #15803d; }
.link-btn {
  border: none;
  background: none;
  color: #6d5ff0;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
}
.mini-btn {
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 7px;
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
}
.mini-btn.ok {
  border-color: transparent;
  background: #16a34a;
  color: #fff;
}
@media (max-width: 640px) {
  .doc-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .facts {
    grid-template-columns: 1fr;
    gap: 2px;
  }
  .facts dd {
    margin-bottom: 8px;
  }
}
</style>
