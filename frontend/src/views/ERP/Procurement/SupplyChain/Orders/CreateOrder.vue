<template>
  <div class="form-page">
    
    <div class="form-header">
      <router-link to="/erp/orders" class="back-link">
        <svg
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          width="16"
        >
          <path d="M13 16l-6-6 6-6" />
        </svg>
        Back to Orders
      </router-link>
      <h1 class="form-title">Create Purchase Order</h1>
    </div>

    <div class="form-grid">
      <div class="form-card">
        <div class="section-title">
          <div class="section-dot"></div>
          Order Details
        </div>

        <div class="field">
          <label>Supplier <span class="req">*</span></label>
          <select
            v-model="form.supplier_id"
            :class="{ error: errors.supplier_id }"
          >
            <option value="" disabled>Select active supplier…</option>
            <option v-for="s in suppliers" :key="s.id" :value="s.id">
              {{ s.company_name }}
            </option>
          </select>
          <span v-if="errors.supplier_id" class="err-msg">{{
            errors.supplier_id
          }}</span>
        </div>

        <div class="section-title" style="margin-top: 4px">
          <div class="section-dot"></div>
          Order Items
          <button class="add-item-btn" @click="addItem">+ Add Item</button>
        </div>

        <div class="items-table-wrap">
          <table class="items-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Subtotal</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!form.items.length">
                <td colspan="5" class="empty-items">
                  No items added yet. Click "+ Add Item" above.
                </td>
              </tr>
              <tr v-for="(item, i) in form.items" :key="i">
                <td>
                  <input
                    v-model="item.product_name"
                    placeholder="Product name"
                    class="tbl-input"
                  />
                </td>
                <td>
                  <input
                    v-model.number="item.quantity"
                    type="number"
                    min="1"
                    placeholder="1"
                    class="tbl-input narrow"
                    @input="calcSubtotal(item)"
                  />
                </td>
                <td>
                  <input
                    v-model.number="item.price"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    class="tbl-input narrow"
                    @input="calcSubtotal(item)"
                  />
                </td>
                <td class="subtotal-cell">
                  ${{ formatAmount(item.quantity * item.price) }}
                </td>
                <td>
                  <button
                    class="remove-row-btn"
                    @click="form.items.splice(i, 1)"
                  >
                    ×
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="total-row">
          <span class="total-label">Order Total</span>
          <span class="total-value">${{ formatAmount(orderTotal) }}</span>
        </div>
      </div>

      <div class="side-panel">
        <div class="panel-card order-summary" v-if="selectedSupplier">
          <div class="panel-title">Supplier</div>
          <div class="sup-summary">
            <div class="sup-ava">{{ selectedSupplier.company_name[0] }}</div>
            <div>
              <div class="sup-sname">{{ selectedSupplier.company_name }}</div>
              <div class="sup-email">{{ selectedSupplier.email }}</div>
            </div>
          </div>
        </div>

        <div class="panel-card summary-nums">
          <div class="snum-row">
            <span>Items</span><span>{{ form.items.length }}</span>
          </div>
          <div class="snum-row">
            <span>Units</span><span>{{ totalUnits }}</span>
          </div>
          <div class="snum-row total">
            <span>Total</span><span>${{ formatAmount(orderTotal) }}</span>
          </div>
        </div>

        <div class="panel-card actions-card">
          <button
            class="btn-submit"
            :disabled="submitting || !form.items.length"
            @click="submit"
          >
            {{ submitting ? "Creating…" : "Create Order" }}
          </button>
          <router-link to="/erp/orders" class="btn-cancel">Cancel</router-link>
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
import { ref, reactive, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { orderService } from "../../../../../services/orderService";
import { supplierService } from "../../../../../services/supplierService";


const router = useRouter();
const suppliers = ref([]);
const submitting = ref(false);
const errors = reactive({});
const toast = ref({ show: false, type: "success", message: "" });

const form = reactive({ supplier_id: "", items: [] });

const selectedSupplier = computed(() =>
  suppliers.value.find((s) => s.id === form.supplier_id),
);
const orderTotal = computed(() =>
  form.items.reduce((sum, i) => sum + (i.quantity || 0) * (i.price || 0), 0),
);
const totalUnits = computed(() =>
  form.items.reduce((sum, i) => sum + (i.quantity || 0), 0),
);

function addItem() {
  form.items.push({ product_name: "", quantity: 1, price: 0 });
}

function calcSubtotal(item) {} // computed from template

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  if (!form.supplier_id) errors.supplier_id = "Please select a supplier";
  if (!form.items.length) errors.items = "Add at least one item";
  return !Object.keys(errors).length;
}

async function submit() {
  if (!validate()) return;
  submitting.value = true;
  try {
    await orderService.create(form);
    showToast("Purchase order created!");
    setTimeout(() => router.push("/erp/orders"), 1000);
  } catch (e) {
    Object.assign(errors, e?.errors || {});
    showToast(e?.message || "Failed to create order", "error");
  } finally {
    submitting.value = false;
  }
}

function formatAmount(n) {
  return Number(n || 0).toLocaleString("en-US", { minimumFractionDigits: 2 });
}

function showToast(msg, type = "success") {
  toast.value = { show: true, type, message: msg };
  setTimeout(() => (toast.value.show = false), 3000);
}

onMounted(async () => {
  const res = await supplierService.list({ status: "active", per_page: 100 });
  suppliers.value = res.data || res;
});
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
  max-width: 940px;
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
.form-title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 240px;
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
.field select {
  padding: 9px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  font-size: 13.5px;
  color: #111827;
  outline: none;
  font-family: inherit;
  background: #fff;
}
.field select:focus {
  border-color: #10b981;
}
.field select.error {
  border-color: #ef4444;
}
.req {
  color: #ef4444;
}
.err-msg {
  font-size: 11.5px;
  color: #ef4444;
}

.add-item-btn {
  margin-left: auto;
  padding: 4px 12px;
  border-radius: 6px;
  border: 1px solid #10b981;
  background: #ecfdf5;
  color: #059669;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.items-table-wrap {
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
}
.items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.items-table th {
  padding: 9px 12px;
  text-align: left;
  font-size: 11.5px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}
.items-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #f3f4f6;
}
.items-table tbody tr:last-child td {
  border-bottom: none;
}
.empty-items {
  text-align: center;
  color: #9ca3af;
  padding: 28px !important;
}
.tbl-input {
  border: 1.5px solid #e5e7eb;
  border-radius: 7px;
  padding: 6px 10px;
  font-size: 13px;
  width: 100%;
  outline: none;
  font-family: inherit;
}
.tbl-input:focus {
  border-color: #10b981;
}
.tbl-input.narrow {
  width: 80px;
}
.subtotal-cell {
  font-weight: 600;
  color: #374151;
  font-variant-numeric: tabular-nums;
}
.remove-row-btn {
  border: none;
  background: none;
  font-size: 18px;
  color: #9ca3af;
  cursor: pointer;
}
.remove-row-btn:hover {
  color: #ef4444;
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0 0;
  border-top: 2px solid #e5e7eb;
}
.total-label {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}
.total-value {
  font-size: 20px;
  font-weight: 800;
  color: #111827;
  font-variant-numeric: tabular-nums;
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
.sup-summary {
  display: flex;
  align-items: center;
  gap: 10px;
}
.sup-ava {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  background: #ecfdf5;
  color: #059669;
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.sup-sname {
  font-size: 13.5px;
  font-weight: 600;
  color: #111827;
}
.sup-email {
  font-size: 12px;
  color: #9ca3af;
}

.summary-nums {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.snum-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: #374151;
}
.snum-row.total {
  font-weight: 700;
  font-size: 15px;
  padding-top: 8px;
  border-top: 1px solid #e5e7eb;
}
.snum-row span:last-child {
  font-weight: 600;
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
}
.btn-submit:hover {
  background: #059669;
}
.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-cancel {
  display: block;
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
