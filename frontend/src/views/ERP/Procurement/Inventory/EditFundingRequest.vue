<template>
  <div class="edit-funding-request">
    <div class="form-content">
      <div class="form-header">
        <div>
          <h1 class="form-title">Edit Funding Request</h1>
          <p class="form-subtitle">Update draft funding request</p>
        </div>
        <button class="back-btn" @click="goBack">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          Back to List
        </button>
      </div>

      <div v-if="loading" class="loading-container">
        <div class="spinner"></div>
        <p>Loading request...</p>
      </div>

      <div v-else-if="error" class="error-container">
        <p>{{ error }}</p>
        <button @click="fetchRequest" class="retry-btn">Retry</button>
      </div>

      <form v-else @submit.prevent="handleSubmit" class="funding-form">
        <div class="form-section">
          <h3 class="section-title">Request Identity</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Accounting Manager *</label>
              <select v-model="formData.accounting_manager_id" required>
                <option value="">Select Accounting Manager</option>
                <option
                  v-for="manager in accountingManagers"
                  :key="manager.id"
                  :value="manager.id"
                >
                  {{ manager.name }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Related Sales Order ID</label>
              <input
                type="text"
                v-model="formData.related_sales_order_id"
                placeholder="SO-2024-XXX"
              />
            </div>
          </div>
        </div>

        <div class="form-section">
          <h3 class="section-title">Product & Procurement Details</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Product Name *</label>
              <input
                type="text"
                v-model="formData.product_name"
                placeholder="e.g., Red Roses"
                required
              />
            </div>
            <div class="form-group">
              <label>Flower Category *</label>
              <select v-model="formData.flower_category" required>
                <option value="">Select category</option>
                <option value="Fresh">Fresh</option>
                <option value="Dried">Dried</option>
                <option value="Artificial">Artificial</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Variant/Grade *</label>
              <select v-model="formData.variant" required>
                <option value="">Select variant</option>
                <option value="Premium">Premium</option>
                <option value="Grade A">Grade A</option>
                <option value="Grade B">Grade B</option>
                <option value="Standard">Standard</option>
              </select>
            </div>
            <div class="form-group">
              <label>UOM *</label>
              <select v-model="formData.uom" required>
                <option value="">Select UOM</option>
                <option value="Stems">Stems</option>
                <option value="Bunches">Bunches</option>
                <option value="Boxes">Boxes</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Requested Quantity *</label>
              <input
                type="number"
                v-model.number="formData.requested_qty"
                placeholder="0"
                required
                min="1"
              />
            </div>
            <div class="form-group">
              <label>Minimum Order Qty (MOQ)</label>
              <input
                type="number"
                v-model.number="formData.moq"
                placeholder="0"
                min="0"
              />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Preferred Supplier</label>
              <input
                type="text"
                v-model="formData.preferred_supplier"
                placeholder="Supplier name"
              />
            </div>
            <div class="form-group">
              <label>Alternative Suppliers</label>
              <input
                type="text"
                v-model="formData.alternative_suppliers"
                placeholder="Comma-separated"
              />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Required Delivery Date *</label>
              <input
                type="date"
                v-model="formData.required_delivery_date"
                required
              />
            </div>
          </div>
        </div>

        <div class="form-section">
          <h3 class="section-title">Inventory Justification</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Current Stock Level *</label>
              <input
                type="number"
                v-model.number="formData.current_stock"
                placeholder="0"
                required
                min="0"
              />
            </div>
            <div class="form-group">
              <label>Reserved Stock *</label>
              <input
                type="number"
                v-model.number="formData.reserved_stock"
                placeholder="0"
                required
                min="0"
              />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Required Quantity *</label>
              <input
                type="number"
                v-model.number="formData.required_quantity"
                placeholder="0"
                required
                min="0"
              />
            </div>
            <div class="form-group">
              <label>Incoming Stock (POs)</label>
              <input
                type="number"
                v-model.number="formData.incoming_stock"
                placeholder="0"
                min="0"
              />
            </div>
          </div>
          <div class="form-group">
            <label>Reason for Shortage *</label>
            <select v-model="formData.reason_for_shortage" required>
              <option value="">Select reason</option>
              <option value="New Order">New Order</option>
              <option value="Spoilage">Spoilage</option>
              <option value="Forecast Error">Forecast Error</option>
              <option value="Seasonal Demand">Seasonal Demand</option>
              <option value="Supplier Delay">Supplier Delay</option>
            </select>
          </div>
        </div>

        <div class="form-section">
          <h3 class="section-title">Financial Breakdown</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Estimated Unit Cost *</label>
              <div class="input-with-unit">
                <span class="currency">₱</span>
                <input
                  type="number"
                  v-model.number="formData.estimated_unit_cost"
                  placeholder="0.00"
                  step="0.01"
                  required
                  min="0"
                />
              </div>
            </div>
            <div class="form-group">
              <label>Payment Terms *</label>
              <select v-model="formData.payment_terms" required>
                <option value="">Select terms</option>
                <option value="Cash">Cash</option>
                <option value="7 Days">7 Days</option>
                <option value="15 Days">15 Days</option>
                <option value="30 Days">30 Days</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Expected Selling Price *</label>
              <div class="input-with-unit">
                <span class="currency">₱</span>
                <input
                  type="number"
                  v-model.number="formData.expected_selling_price"
                  placeholder="0.00"
                  step="0.01"
                  required
                  min="0"
                />
              </div>
            </div>
            <div class="form-group">
              <label>Tax/VAT Estimate</label>
              <div class="input-with-unit">
                <span class="currency">₱</span>
                <input
                  type="number"
                  v-model.number="formData.tax_vat_estimate"
                  placeholder="0.00"
                  step="0.01"
                  min="0"
                />
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Logistics Cost</label>
              <div class="input-with-unit">
                <span class="currency">₱</span>
                <input
                  type="number"
                  v-model.number="formData.logistics_cost"
                  placeholder="0.00"
                  step="0.01"
                  min="0"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="form-section">
          <h3 class="section-title">Urgency & Risk</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Urgency Level *</label>
              <select v-model="formData.urgency_level" required>
                <option value="">Select level</option>
                <option value="Normal">Normal</option>
                <option value="Urgent">Urgent</option>
                <option value="Critical">Critical</option>
              </select>
            </div>
            <div class="form-group">
              <label>Shelf Life (Days) *</label>
              <input
                type="number"
                v-model.number="formData.shelf_life"
                placeholder="0"
                required
                min="0"
              />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Expected Spoilage % *</label>
              <input
                type="number"
                v-model.number="formData.expected_spoilage"
                placeholder="0"
                min="0"
                max="100"
                required
              />
            </div>
            <div class="form-group">
              <label>Missed Sales Impact</label>
              <div class="input-with-unit">
                <span class="currency">₱</span>
                <input
                  type="number"
                  v-model.number="formData.missed_sales_impact"
                  placeholder="0.00"
                  step="0.01"
                  min="0"
                />
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Seasonal Tag</label>
              <input
                type="text"
                v-model="formData.seasonal_tag"
                placeholder="e.g., Valentine's Day"
              />
            </div>
            <div class="form-group">
              <label>Demand Confidence *</label>
              <select v-model="formData.demand_confidence" required>
                <option value="">Select confidence</option>
                <option value="High">High</option>
                <option value="Medium">Medium</option>
                <option value="Low">Low</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-section">
          <h3 class="section-title">Finance Recommendation</h3>
          <div class="form-group">
            <label>Recommendation *</label>
            <select v-model="formData.finance_recommendation" required>
              <option value="">Select recommendation</option>
              <option value="Approve">Approve</option>
              <option value="Approve Partial">Approve Partial</option>
              <option value="Reject">Reject</option>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Recommended Quantity *</label>
              <input
                type="number"
                v-model.number="formData.recommended_qty"
                placeholder="0"
                required
                min="0"
              />
            </div>
            <div class="form-group">
              <label>Price Ceiling (Max Unit Cost)</label>
              <div class="input-with-unit">
                <span class="currency">₱</span>
                <input
                  type="number"
                  v-model.number="formData.price_ceiling"
                  placeholder="0.00"
                  step="0.01"
                  min="0"
                />
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Suggested Supplier</label>
            <input
              type="text"
              v-model="formData.suggested_supplier"
              placeholder="Optional supplier recommendation"
            />
          </div>

          <div class="justification-card">
            <h4 class="justification-title">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
              </svg>
              Decision Brief
            </h4>
            <div class="form-group">
              <label class="brief-label"
                ><span class="label-icon">📦</span> What & Why *</label
              >
              <input
                type="text"
                v-model="formData.business_justification"
                placeholder="e.g., Valentine's Day stock - projected 500-stem shortage based on confirmed orders"
                maxlength="150"
                required
              />
              <span class="char-count"
                >{{ formData.business_justification?.length || 0 }}/150</span
              >
            </div>
            <div class="form-group">
              <label class="brief-label"
                ><span class="label-icon">✅</span> If Approved *</label
              >
              <input
                type="text"
                v-model="formData.approval_impact"
                placeholder="e.g., Fulfills all orders, captures ₱20k seasonal revenue, prevents customer loss"
                maxlength="150"
                required
              />
              <span class="char-count"
                >{{ formData.approval_impact?.length || 0 }}/150</span
              >
            </div>
            <div class="form-group">
              <label class="brief-label"
                ><span class="label-icon">⚠️</span> If Rejected/Delayed *</label
              >
              <input
                type="text"
                v-model="formData.rejection_risk"
                placeholder="e.g., Lost sales of ₱15k, 3 customer cancellations, competitor advantage"
                maxlength="150"
                required
              />
              <span class="char-count"
                >{{ formData.rejection_risk?.length || 0 }}/150</span
              >
            </div>
            <div class="form-group">
              <label class="brief-label optional"
                ><span class="label-icon">💡</span> Additional Context
                (Optional)</label
              >
              <textarea
                v-model="formData.additional_notes"
                rows="2"
                placeholder="Any other relevant details"
                maxlength="300"
              ></textarea>
              <span class="char-count"
                >{{ formData.additional_notes?.length || 0 }}/300</span
              >
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button type="button" class="btn-cancel" @click="goBack">
            Cancel
          </button>
          <button
            type="button"
            class="btn-draft"
            @click="saveAsDraft"
            :disabled="submitting"
          >
            {{ submitting ? "Saving..." : "Save as Draft" }}
          </button>
          <button type="submit" class="btn-submit" :disabled="submitting">
            {{ submitting ? "Submitting..." : "Submit to Accounting" }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import api from "../../../../plugins/axios";
import { toast } from "vue3-toastify";

const router = useRouter();
const route = useRoute();

const accountingManagers = ref([]);
const loading = ref(false);
const error = ref(null);
const submitting = ref(false);

const formData = ref({
  accounting_manager_id: "",
  related_sales_order_id: "",
  product_name: "",
  flower_category: "",
  variant: "",
  requested_qty: null,
  uom: "",
  moq: null,
  preferred_supplier: "",
  alternative_suppliers: "",
  required_delivery_date: "",
  current_stock: null,
  reserved_stock: null,
  required_quantity: null,
  incoming_stock: null,
  reason_for_shortage: "",
  estimated_unit_cost: null,
  payment_terms: "",
  expected_selling_price: null,
  tax_vat_estimate: null,
  logistics_cost: null,
  urgency_level: "",
  shelf_life: null,
  expected_spoilage: null,
  missed_sales_impact: null,
  seasonal_tag: "",
  demand_confidence: "",
  finance_recommendation: "",
  recommended_qty: null,
  price_ceiling: null,
  suggested_supplier: "",
  business_justification: "",
  approval_impact: "",
  rejection_risk: "",
  additional_notes: "",
});

const fetchAccountingManagers = async () => {
  try {
    const { data } = await api.get(
      "/procurement/inventory/accounting-managers",
    );
    if (data.success) accountingManagers.value = data.data;
  } catch (err) {
    toast.error("Failed to load accounting managers");
  }
};

const fetchRequest = async () => {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await api.get(
      `/procurement/inventory/funding-requests/${route.params.id}`,
    );
    if (data.success) {
      const r = data.data;
      formData.value = {
        accounting_manager_id: r.accounting_manager_id,
        related_sales_order_id: r.related_sales_order_id || "",
        product_name: r.product_name,
        flower_category: r.flower_category,
        variant: r.variant,
        requested_qty: r.requested_qty,
        uom: r.uom,
        moq: r.moq,
        preferred_supplier: r.preferred_supplier || "",
        alternative_suppliers: r.alternative_suppliers || "",
        required_delivery_date: r.required_delivery_date,
        current_stock: r.current_stock,
        reserved_stock: r.reserved_stock,
        required_quantity: r.required_quantity,
        incoming_stock: r.incoming_stock || 0,
        reason_for_shortage: r.reason_for_shortage,
        estimated_unit_cost: r.estimated_unit_cost,
        payment_terms: r.payment_terms,
        expected_selling_price: r.expected_selling_price,
        tax_vat_estimate: r.tax_vat_estimate,
        logistics_cost: r.logistics_cost,
        urgency_level: r.urgency_level,
        shelf_life: r.shelf_life,
        expected_spoilage: r.expected_spoilage,
        missed_sales_impact: r.missed_sales_impact,
        seasonal_tag: r.seasonal_tag || "",
        demand_confidence: r.demand_confidence,
        finance_recommendation: r.finance_recommendation,
        recommended_qty: r.recommended_qty,
        price_ceiling: r.price_ceiling,
        suggested_supplier: r.suggested_supplier || "",
        business_justification: r.business_justification,
        approval_impact: r.approval_impact,
        rejection_risk: r.rejection_risk,
        additional_notes: r.additional_notes || "",
      };
    }
  } catch (err) {
    error.value =
      err.response?.data?.message || "Failed to load funding request";
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
};

const handleSubmit = async () => {
  submitting.value = true;
  try {
    const { data } = await api.put(
      `/procurement/inventory/funding-requests/${route.params.id}`,
      { ...formData.value, is_draft: false },
    );
    if (data.success) {
      toast.success(data.message);
      const submitResult = await api.post(
        `/procurement/inventory/funding-requests/${route.params.id}/submit`,
      );
      if (submitResult.data.success)
        toast.success("Request submitted to Accounting");
      router.push("/erp/procurement/inventory/funding-request");
    }
  } catch (err) {
    toast.error(err.response?.data?.message || "Failed to update request");
    if (err.response?.data?.errors) {
      const firstError = Object.values(err.response.data.errors)[0];
      toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
    }
  } finally {
    submitting.value = false;
  }
};

const saveAsDraft = async () => {
  submitting.value = true;
  try {
    const { data } = await api.put(
      `/procurement/inventory/funding-requests/${route.params.id}`,
      formData.value,
    );
    if (data.success) {
      toast.success(data.message);
      router.push("/erp/procurement/inventory/funding-request");
    }
  } catch (err) {
    toast.error(err.response?.data?.message || "Failed to save draft");
  } finally {
    submitting.value = false;
  }
};

const goBack = () => router.push("/erp/procurement/inventory/funding-request");

onMounted(() => {
  fetchAccountingManagers();
  fetchRequest();
});
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.edit-funding-request {
  min-height: 100vh;
  background: #f7fafc;
  font-family: "Poppins", sans-serif;
}
.form-content {
  flex: 1;
  padding: 32px 40px;
  max-width: 1200px;
  margin: 0 auto;
}
.form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
}
.form-title {
  font-size: 32px;
  font-weight: 700;
  color: #2d3748;
  letter-spacing: -0.5px;
  margin-bottom: 4px;
}
.form-subtitle {
  font-size: 14px;
  color: #718096;
  font-weight: 500;
}

.back-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #ffffff;
  color: #4a5568;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}
.back-btn:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.loading-container,
.error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}
.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e2e8f0;
  border-top-color: #4299e1;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
.loading-container p,
.error-container p {
  margin-top: 16px;
  font-size: 14px;
  color: #718096;
}
.retry-btn {
  margin-top: 12px;
  padding: 8px 20px;
  background: #4299e1;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
.retry-btn:hover {
  background: #2b6cb0;
}

.funding-form {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 32px;
}
.form-section {
  margin-bottom: 32px;
  padding-bottom: 32px;
  border-bottom: 2px solid #f7fafc;
}
.form-section:last-of-type {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}
.section-title {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 20px;
}
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
}
.form-row:last-child {
  margin-bottom: 0;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  position: relative;
}
.form-group label {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
}

.form-group input,
.form-group select,
.form-group textarea {
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  color: #2d3748;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #4299e1;
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.input-with-unit {
  position: relative;
  display: flex;
  align-items: center;
}
.input-with-unit input {
  flex: 1;
  padding-left: 36px;
}
.input-with-unit .currency {
  position: absolute;
  left: 14px;
  font-size: 14px;
  color: #718096;
  font-weight: 600;
  pointer-events: none;
}

.justification-card {
  background: #f0f7ff;
  border: 2px solid #4299e1;
  border-radius: 12px;
  padding: 20px;
  margin-top: 20px;
}
.justification-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid rgba(66, 153, 225, 0.2);
}
.justification-title svg {
  color: #4299e1;
}
.brief-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #2d3748;
}
.brief-label.optional {
  color: #718096;
}
.label-icon {
  font-size: 16px;
}
.char-count {
  display: block;
  text-align: right;
  font-size: 11px;
  color: #a0aec0;
  margin-top: -4px;
}
.justification-card .form-group {
  margin-bottom: 16px;
}
.justification-card .form-group:last-child {
  margin-bottom: 0;
}
.justification-card input,
.justification-card textarea {
  background: #ffffff;
  border: 1px solid #bee3f8;
}
.justification-card input:focus,
.justification-card textarea:focus {
  border-color: #4299e1;
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.form-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 32px;
  padding-top: 24px;
  border-top: 2px solid #f7fafc;
}
.btn-cancel,
.btn-draft,
.btn-submit {
  padding: 12px 28px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}
.btn-cancel {
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  color: #4a5568;
}
.btn-cancel:hover {
  background: #e2e8f0;
}
.btn-draft {
  background: #ffffff;
  border: 1px solid #4299e1;
  color: #4299e1;
}
.btn-draft:hover:not(:disabled) {
  background: #ebf8ff;
}
.btn-submit {
  background: #4299e1;
  border: 1px solid #4299e1;
  color: white;
}
.btn-submit:hover:not(:disabled) {
  background: #2b6cb0;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(66, 153, 225, 0.35);
}
.btn-draft:disabled,
.btn-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }
  .form-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  .form-actions {
    flex-direction: column;
  }
  .btn-cancel,
  .btn-draft,
  .btn-submit {
    width: 100%;
  }
}
</style>
