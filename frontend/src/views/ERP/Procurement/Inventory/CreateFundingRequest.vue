<template>
  <div class="create-funding-request">
    
    <div class="form-content">
      <div class="form-header">
        <div>
          <h1 class="form-title">New Funding Request</h1>
          <p class="form-subtitle">
            Fill in the details to request funds from Finance
          </p>
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

      <form @submit.prevent="handleSubmit" class="funding-form">
        <fieldset class="form-fieldset" :disabled="!canEditFunding">
        <p v-if="!canEditFunding" class="permission-notice">
          You have view-only access to Inventory Funding. Creating and submitting requests is disabled.
        </p>
        <!-- Request Identity -->
        <div class="form-section">
          <h3 class="section-title">Request Identity</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Finance Approver *</label>
              <select v-model="formData.approver_id" required>
                <option value="">Select Finance Approver</option>
                <option
                  v-for="approver in financeApprovers"
                  :key="approver.id"
                  :value="approver.id"
                >
                  {{ approver.label }}
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

        <!-- Product Selection -->
        <div class="form-section">
          <h3 class="section-title">Product & Procurement Details</h3>

          <div v-if="loadingProducts" class="loading-state">
            <div class="spinner-small"></div>
            <span>Loading products...</span>
          </div>

          <div v-else class="form-group">
            <label>Select Product *</label>
            <select
              v-model="selectedProductId"
              @change="onProductChange"
              required
              class="product-select"
            >
              <option value="">
                -- Select a product from your inventory --
              </option>
              <optgroup
                v-for="(groupedProducts, category) in groupedProductsByCategory"
                :key="category"
                :label="category"
              >
                <option
                  v-for="product in groupedProducts"
                  :key="product.id"
                  :value="product.id"
                >
                  {{ product.product_name }} | {{ product.sku || "No SKU" }} |
                  Stock: {{ product.quantity_in_stock }} | ₱{{
                    formatNumber(product.purchase_price)
                  }}
                </option>
              </optgroup>
            </select>
            <span class="help-text" v-if="products.length === 0"
              >No products found. Please add products to your inventory
              first.</span
            >
            <span class="help-text" v-else
              >{{ products.length }} product(s) available</span
            >
          </div>

          <!-- Auto-Filled Product Info -->
          <div v-if="selectedProduct" class="auto-filled-section">
            <div class="auto-fill-header">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <span>Auto-filled from database (Read-Only)</span>
            </div>
            <div class="info-grid">
              <div class="info-item">
                <span class="label">Product Name</span
                ><span class="value">{{ selectedProduct.product_name }}</span>
              </div>
              <div class="info-item">
                <span class="label">SKU / Product Code</span
                ><span class="value">{{ selectedProduct.sku || "N/A" }}</span>
              </div>
              <div class="info-item">
                <span class="label">Flower Category</span
                ><span class="value">{{
                  selectedProduct.category || selectedProduct.flower_type
                }}</span>
              </div>
              <div class="info-item">
                <span class="label">Variant/Grade</span
                ><span class="value">{{
                  selectedProduct.color ||
                  selectedProduct.color_other ||
                  "Standard"
                }}</span>
              </div>
              <div class="info-item">
                <span class="label">Unit of Measure</span
                ><span class="value">{{
                  getUOM(selectedProduct.selling_type)
                }}</span>
              </div>
              <div class="info-item">
                <span class="label">Supplier Name</span
                ><span class="value">{{
                  selectedProduct.supplier_name || "N/A"
                }}</span>
              </div>
              <div class="info-item">
                <span class="label">Current Stock</span>
                <span
                  class="value"
                  :class="
                    getStockClass(
                      selectedProduct.quantity_in_stock,
                      selectedProduct.min_stock_level,
                    )
                  "
                >
                  {{ selectedProduct.quantity_in_stock }}
                  {{ getUOM(selectedProduct.selling_type) }}
                </span>
              </div>
              <div class="info-item">
                <span class="label">Min Stock Level</span
                ><span class="value"
                  >{{ selectedProduct.min_stock_level }}
                  {{ getUOM(selectedProduct.selling_type) }}</span
                >
              </div>
              <div class="info-item">
                <span class="label">Max Stock Level</span
                ><span class="value"
                  >{{ selectedProduct.max_stock_level }}
                  {{ getUOM(selectedProduct.selling_type) }}</span
                >
              </div>
              <div class="info-item" v-if="selectedProduct.supplier_lead_time">
                <span class="label">Supplier Lead Time</span
                ><span class="value"
                  >{{ selectedProduct.supplier_lead_time }} days</span
                >
              </div>
              <div class="info-item" v-if="selectedProduct.freshness_days">
                <span class="label">Shelf Life</span
                ><span class="value"
                  >{{ selectedProduct.freshness_days }} days</span
                >
              </div>
            </div>
            <div
              class="additional-info"
              v-if="selectedProduct.notes || selectedProduct.care_instructions"
            >
              <div class="info-row" v-if="selectedProduct.care_instructions">
                <span class="label">Care Instructions</span
                ><span class="value">{{
                  selectedProduct.care_instructions
                }}</span>
              </div>
              <div class="info-row" v-if="selectedProduct.notes">
                <span class="label">Product Notes</span
                ><span class="value">{{ selectedProduct.notes }}</span>
              </div>
            </div>
          </div>

          <!-- Manual Input Fields -->
          <div v-if="selectedProduct" class="manual-input-section">
            <h4 class="subsection-title">Request Details</h4>
            <div class="form-row">
              <div class="form-group">
                <label>Requested Quantity *</label>
                <div class="input-with-unit">
                  <input
                    type="number"
                    v-model.number="formData.requested_qty"
                    placeholder="0"
                    required
                    min="1"
                    :max="selectedProduct.max_stock_level"
                    @input="validateQuantity"
                  />
                  <span class="unit">{{
                    getUOM(selectedProduct.selling_type)
                  }}</span>
                </div>
                <span class="help-text error" v-if="quantityError">{{
                  quantityError
                }}</span>
                <span class="help-text" v-else-if="calculatedShortage > 0"
                  >Shortage: {{ calculatedShortage }}
                  {{ getUOM(selectedProduct.selling_type) }}</span
                >
                <span class="help-text" v-else
                  >Maximum order: {{ selectedProduct.max_stock_level }}
                  {{ getUOM(selectedProduct.selling_type) }}</span
                >
              </div>
              <div class="form-group">
                <label>Required Delivery Date *</label>
                <input
                  type="date"
                  v-model="formData.required_delivery_date"
                  required
                />
              </div>
            </div>
            <div class="form-group">
              <label>Alternative Suppliers (Optional)</label>
              <input
                type="text"
                v-model="formData.alternative_suppliers"
                placeholder="Comma-separated list of alternative suppliers"
              />
            </div>
          </div>
        </div>

        <!-- Inventory Justification -->
        <div class="form-section" v-if="selectedProduct">
          <h3 class="section-title">Inventory Justification</h3>
          <div class="auto-filled-section">
            <div class="auto-fill-header">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <span>Auto-calculated</span>
            </div>
            <div class="info-grid">
              <div class="info-item">
                <span class="label">Current Stock Level</span>
                <span
                  class="value"
                  :class="
                    getStockClass(
                      selectedProduct.quantity_in_stock,
                      selectedProduct.min_stock_level,
                    )
                  "
                  >{{ selectedProduct.quantity_in_stock }}
                  {{ getUOM(selectedProduct.selling_type) }}</span
                >
              </div>
              <div class="info-item">
                <span class="label">Minimum Stock Level</span
                ><span class="value"
                  >{{ selectedProduct.min_stock_level }}
                  {{ getUOM(selectedProduct.selling_type) }}</span
                >
              </div>
              <div class="info-item">
                <span class="label">Required Quantity</span
                ><span class="value font-bold"
                  >{{ formData.requested_qty || 0 }}
                  {{ getUOM(selectedProduct.selling_type) }}</span
                >
              </div>
              <div class="info-item">
                <span class="label">Stock Shortage</span
                ><span class="value font-bold text-error"
                  >{{ calculatedShortage }}
                  {{ getUOM(selectedProduct.selling_type) }}</span
                >
              </div>
            </div>
          </div>
          <div class="form-group" style="margin-top: 20px">
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
          <div class="form-group" style="margin-top: 20px">
            <label>Incoming Stock (POs)</label>
            <div class="input-with-unit">
              <input
                type="number"
                v-model.number="formData.incoming_stock"
                placeholder="0"
                min="0"
              />
              <span class="unit">{{
                getUOM(selectedProduct.selling_type)
              }}</span>
            </div>
            <span class="help-text"
              >Enter quantity from pending purchase orders (if any)</span
            >
          </div>
        </div>

        <!-- Financial Breakdown -->
        <div class="form-section" v-if="selectedProduct">
          <h3 class="section-title">Financial Breakdown</h3>
          <div class="editable-financial-section">
            <div class="section-header">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
                ></path>
                <path
                  d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                ></path>
              </svg>
              <span>Editable (leave blank to use defaults from database)</span>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Unit Cost (Purchase Price)</label>
                <div class="input-with-unit">
                  <span class="currency">₱</span>
                  <input
                    type="number"
                    v-model.number="formData.estimated_unit_cost"
                    :placeholder="formatNumber(selectedProduct.purchase_price)"
                    step="0.01"
                    min="0"
                    @input="recalculateFinancials"
                  />
                </div>
                <span class="help-text"
                  >Default: ₱{{
                    formatNumber(selectedProduct.purchase_price)
                  }}</span
                >
              </div>
              <div class="form-group">
                <label>Unit Selling Price</label>
                <div class="input-with-unit">
                  <span class="currency">₱</span>
                  <input
                    type="number"
                    v-model.number="formData.expected_selling_price"
                    :placeholder="formatNumber(selectedProduct.selling_price)"
                    step="0.01"
                    min="0"
                    @input="recalculateFinancials"
                  />
                </div>
                <span class="help-text"
                  >Default: ₱{{
                    formatNumber(selectedProduct.selling_price)
                  }}</span
                >
              </div>
            </div>
          </div>

          <div class="auto-filled-section">
            <div class="auto-fill-header">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <span>Auto-calculated based on inputs above</span>
            </div>
            <div class="info-grid">
              <div class="info-item">
                <span class="label">Total Estimated Cost</span
                ><span class="value font-bold"
                  >₱{{ formatNumber(calculatedTotalCost) }}</span
                >
              </div>
              <div class="info-item">
                <span class="label">Expected Revenue</span
                ><span class="value"
                  >₱{{ formatNumber(calculatedRevenue) }}</span
                >
              </div>
              <div class="info-item">
                <span class="label">Estimated Gross Margin</span
                ><span class="value" :class="getMarginClass(calculatedMargin)"
                  >{{ calculatedMargin }}%</span
                >
              </div>
            </div>
          </div>

          <div class="form-row" style="margin-top: 20px">
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

        <!-- Urgency & Risk -->
        <div class="form-section" v-if="selectedProduct">
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
          </div>
          <div class="form-row">
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
            <div class="form-group">
              <label>Seasonal Tag</label>
              <input
                type="text"
                v-model="formData.seasonal_tag"
                placeholder="e.g., Valentine's Day"
              />
            </div>
          </div>
          <div class="form-row">
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

        <!-- Finance Recommendation -->
        <div class="form-section" v-if="selectedProduct">
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
          <div class="form-row" style="margin-top: 20px">
            <div class="form-group">
              <label>Recommended Quantity *</label>
              <div class="input-with-unit">
                <input
                  type="number"
                  v-model.number="formData.recommended_qty"
                  placeholder="0"
                  required
                  min="0"
                  :max="selectedProduct.max_stock_level"
                />
                <span class="unit">{{
                  getUOM(selectedProduct.selling_type)
                }}</span>
              </div>
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
          <div class="form-group" style="margin-top: 20px">
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

        <div class="form-actions" v-if="selectedProduct">
          <button type="button" class="btn-cancel" @click="goBack">
            Cancel
          </button>
          <button
            type="button"
            class="btn-draft"
            @click="saveAsDraft"
            :disabled="submitting || !!quantityError"
          >
            {{ submitting ? "Saving..." : "Save as Draft" }}
          </button>
          <button
            type="submit"
            class="btn-submit"
            :disabled="submitting || !!quantityError"
          >
            {{ submitting ? "Submitting..." : "Submit to Finance" }}
          </button>
        </div>
        </fieldset>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import api from "../../../../plugins/axios";
import { toast } from "vue3-toastify";
import { useAssignment } from "../../../../composables/useAssignment";


const router = useRouter();
const { canEdit } = useAssignment();
const financeApprovers = ref([]);
const products = ref([]);
const selectedProductId = ref("");
const submitting = ref(false);
const loadingProducts = ref(false);
const quantityError = ref("");
const canEditFunding = computed(() => canEdit("inventory_funding"));

const formData = ref({
  approver_id: "",
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
  reserved_stock: 0,
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

const selectedProduct = computed(() => {
  if (!selectedProductId.value) return null;
  return products.value.find((p) => p.id === selectedProductId.value);
});

const groupedProductsByCategory = computed(() => {
  const grouped = {};
  products.value.forEach((product) => {
    const category = product.category || product.flower_type || "Other";
    if (!grouped[category]) grouped[category] = [];
    grouped[category].push(product);
  });
  return grouped;
});

const calculatedShortage = computed(() => {
  if (!selectedProduct.value || !formData.value.requested_qty) return 0;
  return Math.max(
    0,
    formData.value.requested_qty - selectedProduct.value.quantity_in_stock,
  );
});

const effectiveUnitCost = computed(() => {
  if (!selectedProduct.value) return 0;
  return formData.value.estimated_unit_cost !== null &&
    formData.value.estimated_unit_cost !== ""
    ? parseFloat(formData.value.estimated_unit_cost)
    : parseFloat(selectedProduct.value.purchase_price);
});

const effectiveSellingPrice = computed(() => {
  if (!selectedProduct.value) return 0;
  return formData.value.expected_selling_price !== null &&
    formData.value.expected_selling_price !== ""
    ? parseFloat(formData.value.expected_selling_price)
    : parseFloat(selectedProduct.value.selling_price);
});

const calculatedTotalCost = computed(() => {
  if (!selectedProduct.value || !formData.value.requested_qty) return 0;
  return effectiveUnitCost.value * formData.value.requested_qty;
});

const calculatedRevenue = computed(() => {
  if (!selectedProduct.value || !formData.value.requested_qty) return 0;
  return effectiveSellingPrice.value * formData.value.requested_qty;
});

const calculatedMargin = computed(() => {
  if (
    !selectedProduct.value ||
    !formData.value.requested_qty ||
    effectiveSellingPrice.value === 0
  )
    return 0;
  return (
    ((effectiveSellingPrice.value - effectiveUnitCost.value) /
      effectiveSellingPrice.value) *
    100
  ).toFixed(1);
});

const getUOM = (sellingType) =>
  ({ per_piece: "Stems", per_bunch: "Bunches", per_box: "Boxes" })[
    sellingType
  ] || "Stems";
const getStockClass = (current, min) => {
  if (current <= 0) return "text-error";
  if (current <= min) return "text-warning";
  return "text-success";
};
const getMarginClass = (margin) => {
  if (margin >= 30) return "text-success";
  if (margin >= 20) return "text-warning";
  return "text-error";
};
const formatNumber = (num) => {
  if (!num && num !== 0) return "0.00";
  return parseFloat(num).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};
const recalculateFinancials = () => {};

const validateQuantity = () => {
  quantityError.value = "";
  if (!selectedProduct.value || !formData.value.requested_qty) return;
  const requestedQty = parseFloat(formData.value.requested_qty);
  const maxStockLevel = parseFloat(selectedProduct.value.max_stock_level);
  if (requestedQty > maxStockLevel) {
    quantityError.value = `Quantity cannot exceed maximum stock level of ${maxStockLevel} ${getUOM(selectedProduct.value.selling_type)}`;
    toast.error(quantityError.value);
  }
  if (formData.value.requested_qty)
    formData.value.required_quantity = formData.value.requested_qty;
};

const fetchFinanceApprovers = async () => {
  try {
    const { data } = await api.get("/procurement/inventory/eligible-approvers");
    if (data.success) financeApprovers.value = data.data;
  } catch (err) {
    toast.error("Failed to load finance approvers");
  }
};

const fetchProducts = async () => {
  loadingProducts.value = true;
  try {
    const { data } = await api.get("/procurement/inventory/funding-requests/products");
    if (data.success) {
      products.value = data.data;
      if (data.data.length === 0)
        toast.info(
          "No products found in your inventory. Please add products first.",
        );
    }
  } catch (err) {
    toast.error(err.response?.data?.message || "Failed to load products");
  } finally {
    loadingProducts.value = false;
  }
};

const mapFlowerCategory = (category) => {
  if (!category) return "Fresh";
  const lc = category.toLowerCase();
  if (lc.includes("dried")) return "Dried";
  if (lc.includes("artificial")) return "Artificial";
  return "Fresh";
};

const mapVariant = (color) => {
  if (!color) return "Standard";
  return (
    { white: "Premium", red: "Grade A", pink: "Grade A", yellow: "Grade B" }[
      color.toLowerCase()
    ] || "Standard"
  );
};

const onProductChange = () => {
  if (!selectedProduct.value) return;
  quantityError.value = "";
  formData.value.product_name = selectedProduct.value.product_name;
  formData.value.flower_category = mapFlowerCategory(
    selectedProduct.value.category || selectedProduct.value.flower_type,
  );
  formData.value.variant = mapVariant(
    selectedProduct.value.color || selectedProduct.value.color_other,
  );
  formData.value.uom = getUOM(selectedProduct.value.selling_type);
  formData.value.moq = selectedProduct.value.min_stock_level || 1;
  formData.value.preferred_supplier = selectedProduct.value.supplier_name || "";
  formData.value.current_stock = selectedProduct.value.quantity_in_stock;
  formData.value.reserved_stock = 0;
  formData.value.estimated_unit_cost = null;
  formData.value.expected_selling_price = null;
  formData.value.shelf_life = selectedProduct.value.freshness_days || 7;
  if (formData.value.requested_qty)
    formData.value.recommended_qty = formData.value.requested_qty;
  toast.success(`Product "${selectedProduct.value.product_name}" selected`);
};

const preparePayload = () => ({
  ...formData.value,
  estimated_unit_cost:
    formData.value.estimated_unit_cost !== null &&
    formData.value.estimated_unit_cost !== ""
      ? formData.value.estimated_unit_cost
      : selectedProduct.value.purchase_price,
  expected_selling_price:
    formData.value.expected_selling_price !== null &&
    formData.value.expected_selling_price !== ""
      ? formData.value.expected_selling_price
      : selectedProduct.value.selling_price,
});

const handleSubmit = async () => {
  if (!canEditFunding.value) {
    toast.error("You do not have permission to create funding requests");
    return;
  }
  if (!selectedProduct.value) {
    toast.error("Please select a product");
    return;
  }
  if (quantityError.value) {
    toast.error("Please fix quantity errors before submitting");
    return;
  }
  submitting.value = true;
  try {
    const { data } = await api.post("/procurement/inventory/funding-requests", {
      ...preparePayload(),
      is_draft: false,
    });
    if (data.success) {
      toast.success(data.message);
      router.push("/erp/procurement/inventory/funding-request");
    }
  } catch (err) {
    toast.error(err.response?.data?.message || "Failed to submit request");
    if (err.response?.data?.errors) {
      const firstError = Object.values(err.response.data.errors)[0];
      toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
    }
  } finally {
    submitting.value = false;
  }
};

const saveAsDraft = async () => {
  if (!canEditFunding.value) {
    toast.error("You do not have permission to create funding requests");
    return;
  }
  if (!selectedProduct.value) {
    toast.error("Please select a product");
    return;
  }
  if (quantityError.value) {
    toast.error("Please fix quantity errors before saving");
    return;
  }
  submitting.value = true;
  try {
    const { data } = await api.post("/procurement/inventory/funding-requests", {
      ...preparePayload(),
      is_draft: true,
    });
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

watch(
  () => formData.value.requested_qty,
  (newVal) => {
    if (newVal && !formData.value.recommended_qty)
      formData.value.recommended_qty = newVal;
    validateQuantity();
  },
);

onMounted(() => {
  fetchFinanceApprovers();
  fetchProducts();
});
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.create-funding-request {
  min-height: 100vh;
  background: #f7fafc;
  font-family: "Poppins", sans-serif;
}
.form-content {
  flex: 1;
  padding: 32px 40px;
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
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

.loading-state {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: #f7fafc;
  border-radius: 10px;
  margin-bottom: 20px;
}
.spinner-small {
  width: 20px;
  height: 20px;
  border: 3px solid #e2e8f0;
  border-top-color: #4299e1;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.funding-form {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 32px;
}
.form-fieldset {
  border: 0;
  margin: 0;
  padding: 0;
  min-width: 0;
}
.permission-notice {
  margin-bottom: 20px;
  padding: 14px 16px;
  background: #fff5f5;
  border: 1px solid #feb2b2;
  border-radius: 10px;
  color: #9b2c2c;
  font-size: 13px;
  font-weight: 600;
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
.subsection-title {
  font-size: 15px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid #f7fafc;
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
  margin-bottom: 0;
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
  background: #ffffff;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #4299e1;
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.product-select {
  font-family: "Poppins", monospace;
  font-size: 13px;
}
.help-text {
  font-size: 12px;
  color: #718096;
  margin-top: 4px;
  display: block;
}
.help-text.error {
  color: #e53e3e;
  font-weight: 600;
}

.input-with-unit {
  position: relative;
  display: flex;
  align-items: center;
}
.input-with-unit input {
  flex: 1;
  margin-left: 15px;
  padding-right: 80px;
}
.input-with-unit .currency {
  position: absolute;
  font-size: 14px;
  color: #718096;
  font-weight: 600;
  pointer-events: none;
  width: 100%;
}
.currency {
  margin-left: 18px;
}
.input-with-unit .unit {
  position: absolute;
  right: 14px;
  font-size: 14px;
  color: #718096;
  font-weight: 600;
  pointer-events: none;
}

.auto-filled-section {
  background: #ebf8ff;
  border: 2px solid #4299e1;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
  margin-top: 20px;
}
.auto-fill-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: #2c5282;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid rgba(66, 153, 225, 0.2);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.auto-fill-header svg {
  color: #4299e1;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}
.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.info-item .label {
  font-size: 11px;
  font-weight: 600;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.info-item .value {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
}
.font-bold {
  font-weight: 700 !important;
}
.text-success {
  color: #2b6cb0 !important;
}
.text-warning {
  color: #dd6b20 !important;
}
.text-error {
  color: #e53e3e !important;
}

.additional-info {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 2px solid rgba(66, 153, 225, 0.2);
}
.info-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}
.info-row:last-child {
  margin-bottom: 0;
}
.info-row .label {
  font-size: 11px;
  font-weight: 600;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.info-row .value {
  font-size: 13px;
  color: #2d3748;
  line-height: 1.5;
}

.manual-input-section {
  background: #ffffff;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  margin-top: 20px;
}

.editable-financial-section {
  background: #fffaf0;
  border: 2px solid #ed8936;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
}
.section-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: #7c2d12;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid rgba(237, 137, 54, 0.2);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.section-header svg {
  color: #ed8936;
}

.justification-card {
  background: #ebf8ff;
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
  border: none;
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
  transform: none;
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
  .info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
